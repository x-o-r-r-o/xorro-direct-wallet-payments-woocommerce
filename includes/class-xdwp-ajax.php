<?php
/**
 * AJAX endpoints for checkout coin list and payment status.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Ajax
 */
class Xdwp_Ajax {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_xdwp_status', array( __CLASS__, 'payment_status' ) );
		add_action( 'wp_ajax_nopriv_xdwp_status', array( __CLASS__, 'payment_status' ) );
		add_action( 'wp_ajax_xdwp_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wp_ajax_nopriv_xdwp_quote', array( __CLASS__, 'quote' ) );
		// Frontend calls go through WooCommerce's ?wc-ajax= endpoint: it skips admin_init, so
		// other plugins' activation/onboarding redirects (which hook admin_init and also fire
		// on admin-ajax.php) cannot turn a quote/status response into an HTML page, and
		// security plugins that restrict admin-ajax.php for visitors do not affect checkout.
		// The admin-ajax hooks above stay for pages cached before this change.
		add_action( 'wc_ajax_xdwp_status', array( __CLASS__, 'payment_status' ) );
		add_action( 'wc_ajax_xdwp_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wp_ajax_xdwp_renew', array( __CLASS__, 'renew' ) );
		add_action( 'wp_ajax_nopriv_xdwp_renew', array( __CLASS__, 'renew' ) );
		add_action( 'wc_ajax_xdwp_renew', array( __CLASS__, 'renew' ) );
		add_action( 'wp_ajax_xdwp_sent', array( __CLASS__, 'sent' ) );
		add_action( 'wp_ajax_nopriv_xdwp_sent', array( __CLASS__, 'sent' ) );
		add_action( 'wc_ajax_xdwp_sent', array( __CLASS__, 'sent' ) );
		add_action( 'wp_ajax_xdwp_wallet_sent', array( __CLASS__, 'wallet_sent' ) );
		add_action( 'wp_ajax_nopriv_xdwp_wallet_sent', array( __CLASS__, 'wallet_sent' ) );
		add_action( 'wc_ajax_xdwp_wallet_sent', array( __CLASS__, 'wallet_sent' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register frontend assets (enqueued on demand).
	 */
	public static function register_assets() {
		$css_ver = XDWP_VERSION;
		$js_ver  = XDWP_VERSION;
		$css     = XDWP_PATH . 'assets/css/frontend.css';
		$js      = XDWP_PATH . 'assets/js/frontend.js';
		if ( is_readable( $css ) ) {
			$css_ver = XDWP_VERSION . '.' . (string) filemtime( $css );
		}
		if ( is_readable( $js ) ) {
			$js_ver = XDWP_VERSION . '.' . (string) filemtime( $js );
		}

		wp_register_style(
			'xdwp-frontend',
			XDWP_URL . 'assets/css/frontend.css',
			array(),
			$css_ver
		);

		wp_register_script(
			'xdwp-qrcode',
			XDWP_URL . 'assets/js/qrcode.min.js',
			array(),
			'1.0.0',
			true
		);

		wp_register_script(
			'xdwp-frontend',
			XDWP_URL . 'assets/js/frontend.js',
			array( 'xdwp-qrcode' ),
			$js_ver,
			true
		);

		$checkout_js_ver = XDWP_VERSION;
		$checkout_js     = XDWP_PATH . 'assets/js/checkout.js';
		if ( is_readable( $checkout_js ) ) {
			$checkout_js_ver = XDWP_VERSION . '.' . (string) filemtime( $checkout_js );
		}

		wp_register_script(
			'xdwp-checkout',
			XDWP_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			$checkout_js_ver,
			true
		);
	}

	/**
	 * Frontend endpoint URL for one of this plugin's AJAX actions.
	 *
	 * @param string $action xdwp_quote or xdwp_status.
	 * @return string
	 */
	public static function endpoint( $action ) {
		if ( class_exists( 'WC_AJAX' ) ) {
			return WC_AJAX::get_endpoint( $action );
		}
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * The visitor's address, as every limit in this plugin sees it.
	 *
	 * REMOTE_ADDR, except behind Cloudflare, where the visitor's own address is read from
	 * Cloudflare's header — believed only when the connection really came from Cloudflare. Any
	 * other CDN or proxy can be handled by filtering the result to the header it sets.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = class_exists( 'Xdwp_Proxy' ) ? Xdwp_Proxy::client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$ip = '' === $ip ? 'unknown' : $ip;
		/**
		 * Filter the client identifier used for rate limiting (e.g. to trust a CDN's
		 * client-IP header on sites behind a proxy).
		 *
		 * @param string $ip Client IP (REMOTE_ADDR by default).
		 */
		$ip = apply_filters( 'xdwp_rate_limit_client_ip', $ip );
		return is_scalar( $ip ) ? (string) $ip : 'unknown';
	}

	/**
	 * Something stable that distinguishes one shopper from another behind a shared IP.
	 *
	 * @return string
	 */
	private static function client_scope() {
		if ( function_exists( 'WC' ) && WC()->session && WC()->session->get_customer_id() ) {
			return (string) WC()->session->get_customer_id();
		}
		if ( is_user_logged_in() ) {
			return 'u' . get_current_user_id();
		}
		return '';
	}

	/**
	 * Fixed one-minute-window request limit per client IP.
	 *
	 * The window is part of the key. Re-setting a single transient with a fresh 60s TTL on
	 * every hit (the previous approach) never let the counter expire under steady traffic,
	 * so after N requests spread over any length of time every visitor on that IP — all
	 * customers, behind a CDN or proxy — was refused until traffic stopped for a minute.
	 *
	 * @param string     $prefix Key prefix.
	 * @param string|int $scope  Extra key scope (e.g. order ID).
	 * @param int        $limit  Requests allowed per minute.
	 * @return bool True when over the limit.
	 */
	private static function rate_limited( $prefix, $scope, $limit ) {
		$ip    = self::client_ip();
		$key   = $prefix . md5( $ip . '|' . $scope . '|' . (int) floor( time() / MINUTE_IN_SECONDS ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Poll payment status for an order.
	 */
	public static function payment_status() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny     = static function () {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) ), 403 );
		};

		if ( ! $order_id ) {
			$deny();
			return;
		}

		// Explicit $die=false so an invalid nonce falls through to the same
		// uniform JSON denial as every other failure path below, rather than
		// wp_die()'s bare "-1" — a different response shape/status than the
		// rest of this endpoint (does not itself leak order existence, since
		// nonce validity depends on the requester's own session, not a
		// per-order secret, but keeping the shape consistent regardless).
		if ( ! check_ajax_referer( 'xdwp_status_' . $order_id, 'nonce', false ) ) {
			$deny();
			return;
		}

		if ( self::rate_limited( 'xdwp_status_', $order_id, 120 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}

		$order = wc_get_order( $order_id );

		// Uniform denial — avoid leaking whether an order ID is a Xorro Wallet Payments order.
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			$deny();
			return;
		}

		// Allow order owner or guests with matching order key.
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$allowed   = false;
		if ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
			$allowed = true;
		} elseif ( $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
			$allowed = true;
		} elseif ( current_user_can( 'manage_woocommerce' ) ) {
			$allowed = true;
		}

		if ( ! $allowed ) {
			$deny();
			return;
		}

		Xdwp_Order::maybe_expire( $order );
		$order = wc_get_order( $order_id );

		$status = Xdwp_Order::meta( $order, 'status' );

		// Throttle live chain checks from the browser poll (cron remains primary).
		// Only while WooCommerce still expects payment (blocks cancelled/refunded resurrection).
		if (
			in_array( $status, array( 'awaiting', 'underpaid' ), true )
			&& in_array( $order->get_status(), array( 'pending', 'on-hold' ), true )
			&& 'yes' === Xdwp_Settings::get( 'auto_verify', 'yes' )
		) {
			$throttle_key = 'xdwp_ajax_verify_' . $order_id;
			// Site-wide budget for browser-triggered chain checks: each one can make several
			// outbound explorer calls, and many open payment pages (or scripted guest orders)
			// would otherwise multiply that without limit. Cron still checks every order.
			$budget_key  = Xdwp_Order::LOOK_BUDGET_KEY;
			$budget_used = (int) get_transient( $budget_key );
			if ( ! get_transient( $throttle_key ) && $budget_used < Xdwp_Order::LOOK_BUDGET ) {
				set_transient( $budget_key, $budget_used + 1, MINUTE_IN_SECONDS );
				set_transient( $throttle_key, 1, 45 );
				if ( Xdwp_Verifier::verify_order( $order ) ) {
					Xdwp_Order::mark_paid( $order );
					// Report what was actually stored: mark_paid() returns early when another
					// worker (cron / a second tab) holds the payment lock.
					$order  = wc_get_order( $order_id );
					$status = (string) Xdwp_Order::meta( $order, 'status' );
				}
			}
		}

		// Tell a waiting customer as soon as their transfer is visible on-chain, even before it
		// has the confirmations needed to mark the order paid, so they don't send it twice.
		$detected = false;
		if ( in_array( $status, array( 'awaiting', 'underpaid' ), true ) && 'yes' === Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			// Detection makes its own explorer calls, so it shares the store-wide budget with
			// verification: a few hundred abandoned orders being polled must not burn through
			// the merchant's API quota and stop real payments being confirmed.
			$budget_key  = Xdwp_Order::LOOK_BUDGET_KEY;
			$budget_used = (int) get_transient( $budget_key );
			if ( $budget_used < Xdwp_Order::LOOK_BUDGET ) {
				set_transient( $budget_key, $budget_used + 1, MINUTE_IN_SECONDS );
				$detected = Xdwp_Verifier::detect_incoming( $order );
			} else {
				$detected = '' !== (string) Xdwp_Order::meta( $order, 'seen_txid' );
			}
		}

		wp_send_json_success(
			array(
				'detected' => $detected,
				'status'  => $status,
				'expires' => (int) Xdwp_Order::meta( $order, 'expires' ),
				'paid'    => ( 'paid' === $status ),
				'expired' => ( 'expired' === $status ),
			)
		);
	}

	/**
	 * A browser wallet says it sent a transaction for this order.
	 *
	 * The hash is recorded so the customer can see it and the shop owner can trace it — and
	 * nothing more. A wallet can return a hash for a transaction that never mines, or one
	 * that pays somebody else entirely, so the order is still only marked paid by the same
	 * chain verification used for a payment typed in by hand.
	 */
	public static function wallet_sent() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny     = static function () {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) ), 403 );
		};
		if ( ! $order_id || ! check_ajax_referer( 'xdwp_status_' . $order_id, 'nonce', false ) ) {
			$deny();
			return;
		}
		if ( self::rate_limited( 'xdwp_wallet_', $order_id, 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			$deny();
			return;
		}
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$allowed   = ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() )
			|| ( $order_key && hash_equals( $order->get_order_key(), $order_key ) )
			|| current_user_can( 'manage_woocommerce' );
		if ( ! $allowed ) {
			$deny();
			return;
		}

		$txid = isset( $_POST['txid'] ) ? sanitize_text_field( wp_unslash( $_POST['txid'] ) ) : '';
		if ( ! preg_match( '#^(0x)?[A-Fa-f0-9]{32,80}$#', $txid ) ) {
			wp_send_json_error( array( 'message' => __( 'That does not look like a transaction.', 'xorro-direct-wallet-payments-woocommerce' ) ), 400 );
			return;
		}

		$status = (string) Xdwp_Order::meta( $order, 'status' );
		if ( ! in_array( $status, array( 'awaiting', 'underpaid' ), true ) ) {
			wp_send_json_success( array( 'recorded' => false ) );
			return;
		}

		$order->update_meta_data( '_xdwp_wallet_txid', $txid );
		$order->save();
		Xdwp_Order::log_event( $order, 'wallet', sprintf( /* translators: %s: transaction id */ __( 'Paid from a browser wallet, which reported transaction %s', 'xorro-direct-wallet-payments-woocommerce' ), $txid ) );
		$order->add_order_note(
			sprintf(
				/* translators: %s: transaction id */
				__( 'The customer paid from a browser wallet, which reported transaction %s. The order will be confirmed once that payment is found on chain, exactly as any other would be.', 'xorro-direct-wallet-payments-woocommerce' ),
				$txid
			)
		);

		// Look now rather than at the next scheduled check.
		delete_transient( 'xdwp_ajax_verify_' . $order_id );

		wp_send_json_success( array( 'recorded' => true ) );
	}

	/**
	 * "I've sent the payment": look now instead of waiting for the next scheduled check.
	 *
	 * This only clears this order's own throttles — the store-wide budget on browser-triggered
	 * chain lookups still applies, so an impatient customer (or a script) cannot turn the
	 * payment page into a way to hammer the explorers.
	 */
	public static function sent() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny     = static function () {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) ), 403 );
		};
		if ( ! $order_id || ! check_ajax_referer( 'xdwp_status_' . $order_id, 'nonce', false ) ) {
			$deny();
			return;
		}
		if ( self::rate_limited( 'xdwp_sent_', $order_id, 3 ) ) {
			wp_send_json_error( array( 'message' => __( 'Thanks — we are already looking. Please give it a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			$deny();
			return;
		}
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$allowed   = ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() )
			|| ( $order_key && hash_equals( $order->get_order_key(), $order_key ) )
			|| current_user_can( 'manage_woocommerce' );
		if ( ! $allowed ) {
			$deny();
			return;
		}

		// The customer may also give the transaction id their wallet showed them. It is never
		// proof of anything — only a transfer to this shop's address for the right amount marks
		// an order paid — but it is what the shop needs to trace a payment that went astray.
		$txid = isset( $_POST['txid'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['txid'] ) ) ) : '';
		if ( '' !== $txid && ! preg_match( '#^[A-Za-z0-9._:@-]{6,128}$#', $txid ) ) {
			wp_send_json_error(
				array(
					'verdict' => 'bad_txid',
					'message' => __( 'That does not look like a transaction id. Copy it from your wallet — it is a long string of letters and numbers.', 'xorro-direct-wallet-payments-woocommerce' ),
				),
				400
			);
		}

		$status = (string) Xdwp_Order::meta( $order, 'status' );
		if ( ! in_array( $status, array( 'awaiting', 'underpaid' ), true ) ) {
			$verdict = Xdwp_Order::payment_verdict( $order );
			wp_send_json_success(
				array(
					'checking' => false,
					'verdict'  => $verdict['verdict'],
					'message'  => $verdict['message'],
				)
			);
		}

		// Worth recording: if anything goes wrong later, the store owner can see the customer
		// said they had paid, and when.
		if ( '' === (string) Xdwp_Order::meta( $order, 'sent_at' ) ) {
			$order->update_meta_data( '_xdwp_sent_at', time() );
			$order->save();
		}

		if ( '' !== $txid && $txid !== (string) Xdwp_Order::meta( $order, 'customer_txid' ) ) {
			$order->update_meta_data( '_xdwp_customer_txid', $txid );
			$order->save();
			Xdwp_Order::log_event(
				$order,
				'customer',
				sprintf(
					/* translators: %s: transaction id the customer gave */
					__( 'The customer says they paid with transaction %s', 'xorro-direct-wallet-payments-woocommerce' ),
					$txid
				)
			);
			$order->add_order_note(
				sprintf(
					/* translators: %s: transaction id the customer gave */
					__( 'The customer gave transaction %s as their payment. This is what they told us, not something read from the chain — the order is still only marked paid by the usual check.', 'xorro-direct-wallet-payments-woocommerce' ),
					$txid
				)
			);
		}

		// Only the ordinary check is brought forward. The wide scan and the detection probe
		// keep their own throttles, so this button cannot be used to multiply explorer calls.
		delete_transient( 'xdwp_ajax_verify_' . $order_id );

		$verdict = Xdwp_Order::payment_verdict( $order );

		wp_send_json_success(
			array(
				'checking' => true,
				'verdict'  => $verdict['verdict'],
				'message'  => $verdict['message'],
			)
		);
	}

	/**
	 * Re-quote an order whose payment window closed with nothing received.
	 */
	public static function renew() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny     = static function () {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) ), 403 );
		};
		if ( ! $order_id || ! check_ajax_referer( 'xdwp_status_' . $order_id, 'nonce', false ) ) {
			$deny();
			return;
		}
		if ( self::rate_limited( 'xdwp_renew_', $order_id, 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			$deny();
			return;
		}
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$allowed   = ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() )
			|| ( $order_key && hash_equals( $order->get_order_key(), $order_key ) )
			|| current_user_can( 'manage_woocommerce' );
		if ( ! $allowed ) {
			$deny();
			return;
		}

		if ( ! Xdwp_Order::renew_payment( $order ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This order cannot be re-quoted. Please contact us.', 'xorro-direct-wallet-payments-woocommerce' ) ),
				400
			);
		}
		wp_send_json_success( array( 'renewed' => true ) );
	}

	/**
	 * Live quote for selected coin at checkout.
	 */
	public static function quote() {
		check_ajax_referer( 'xdwp_checkout', 'nonce' );

		// Scoped by cart as well as IP: behind a proxy or CDN every shopper can share one
		// address, and an unscoped bucket would take quotes down for the whole shop.
		if ( self::rate_limited( 'xdwp_quote_', self::client_scope(), 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}

		$coin_id = isset( $_POST['coin'] ) ? sanitize_text_field( wp_unslash( $_POST['coin'] ) ) : '';
		$coin    = Xdwp_Coins::get( $coin_id );

		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'xorro-direct-wallet-payments-woocommerce' ) ), 400 );
		}

		$total   = (float) WC()->cart->get_total( 'edit' );
		$payable = Xdwp_Coins::payable_for_total( $total );

		if ( ! $coin || ! isset( $payable[ $coin_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Coin not available for this order total.', 'xorro-direct-wallet-payments-woocommerce' ) ), 400 );
		}

		$amount = Xdwp_Prices::checkout_quote( $total, $coin_id );

		if ( '' === $amount ) {
			wp_send_json_error( array( 'message' => __( 'Unable to fetch exchange rate. Try again shortly.', 'xorro-direct-wallet-payments-woocommerce' ) ), 503 );
		}

		wp_send_json_success(
			array(
				'coin'    => $coin_id,
				'name'    => $coin['name'],
				'amount'  => $amount,
				'symbol'  => $coin['symbol'],
				'fiat'    => wc_price( $total ),
				'approx'  => false,
				'message' => __( 'Exact amount due if you place the order now.', 'xorro-direct-wallet-payments-woocommerce' ),
			)
		);
	}
}
