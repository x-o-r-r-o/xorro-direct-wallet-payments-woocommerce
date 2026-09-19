<?php
/**
 * Order payment lifecycle helpers.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Order
 */
class Xdwp_Order {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_thankyou_' . XDWP_GATEWAY_ID, array( __CLASS__, 'render_payment_box' ), 10, 1 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( __CLASS__, 'payment_callout' ), 10, 2 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'maybe_render_on_view' ), 5 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_metabox' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_info' ), 10, 1 );
		add_action( 'admin_post_xdwp_mark_paid', array( __CLASS__, 'handle_mark_paid' ) );
		add_action( 'admin_notices', array( __CLASS__, 'mark_paid_notice' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'maybe_append_crypto_price' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'on_order_terminal' ), 10, 1 );
	}

	/**
	 * Stop auto-verify when a crypto order reaches a terminal WC status.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public static function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'cancelled', 'refunded', 'trash' ), true ) ) {
			return;
		}
		self::on_order_terminal( $order_id, $order );
	}

	/**
	 * Mark plugin payment as cancelled so AJAX/cron cannot resurrect the order.
	 *
	 * Called both from `on_status_changed()` (cancelled/refunded, and trash on
	 * setups where it fires `woocommerce_order_status_changed`) and directly
	 * from the `woocommerce_trash_order` hook: under HPOS — WooCommerce's
	 * default order storage — trashing an order writes status via a raw
	 * `$wpdb->update()` and only fires `woocommerce_trash_order`, never
	 * `woocommerce_order_status_changed`, so relying on the status-changed
	 * hook alone silently skips this safeguard whenever a stale order is
	 * trashed and later restored. Safe to call from both hooks — this is a
	 * no-op once `_xdwp_status` is already outside `awaiting`/`expired`.
	 *
	 * @param int            $order_id Order ID.
	 * @param WC_Order|null  $order    Order object.
	 */
	public static function on_order_terminal( $order_id, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order || ! self::is_ours( $order ) ) {
			return;
		}
		$status = (string) self::meta( $order, 'status' );
		if ( ! in_array( $status, array( 'awaiting', 'underpaid', 'expired' ), true ) ) {
			return;
		}
		$order->update_meta_data( '_xdwp_status', 'cancelled' );
		$order->save();
		$order->add_order_note( __( 'Xorro crypto payment cancelled — auto-verify stopped.', 'xorro-direct-wallet-payments-woocommerce' ) );
	}

	/**
	 * Whether this order uses Xorro Wallet Payments.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_ours( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		return XDWP_GATEWAY_ID === $order->get_payment_method();
	}

	/**
	 * Read plugin order meta (`_xdwp_{suffix}`).
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $suffix Meta suffix (e.g. status, amount).
	 * @return mixed
	 */
	public static function meta( $order, $suffix ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		return $order->get_meta( '_xdwp_' . sanitize_key( $suffix ) );
	}

	/**
	 * Whether an expired order may be re-quoted by the customer (nothing was received yet).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function can_renew( $order ) {
		if ( ! $order instanceof WC_Order || ! self::is_ours( $order ) ) {
			return false;
		}
		if ( 'expired' !== (string) self::meta( $order, 'status' ) ) {
			return false;
		}
		if ( '' !== (string) self::meta( $order, 'received' ) || '' !== (string) self::meta( $order, 'late_txid' ) ) {
			return false;
		}
		if ( ! in_array( $order->get_status(), array( 'failed', 'pending', 'on-hold' ), true ) ) {
			return false;
		}
		return (bool) Xdwp_Coins::get( (string) self::meta( $order, 'coin' ) );
	}

	/**
	 * Quote the same order again at today's rate (customer clicked "get a new amount").
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function renew_payment( $order ) {
		if ( ! self::can_renew( $order ) ) {
			return false;
		}
		$coin_id = (string) self::meta( $order, 'coin' );
		if ( ! self::assign_payment( $order, $coin_id ) ) {
			return false;
		}
		$order = wc_get_order( $order->get_id() );
		if ( ! $order ) {
			return false;
		}
		if ( 'on-hold' !== $order->get_status() ) {
			$order->update_status( 'on-hold', __( 'Customer requested a new crypto payment quote.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		do_action( 'xdwp_payment_renewed', $order );
		return true;
	}

	/**
	 * Record on the order why crypto payment setup failed (the customer only sees a generic retry message).
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $coin_id Coin ID.
	 * @param string   $reason  Reason.
	 */
	private static function note_setup_failure( $order, $coin_id, $reason ) {
		if ( $order instanceof WC_Order ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: coin ID, 2: reason */
					__( 'Crypto payment could not be set up for %1$s: %2$s', 'xorro-direct-wallet-payments-woocommerce' ),
					$coin_id,
					$reason
				)
			);
		}
	}

	/**
	 * Point the customer at the payment box, which WooCommerce renders below the order
	 * details — easy to miss while a payment timer is running.
	 *
	 * @param string        $text  Thank-you text.
	 * @param WC_Order|null $order Order.
	 * @return string
	 */
	public static function payment_callout( $text, $order ) {
		$status = $order instanceof WC_Order && self::is_ours( $order ) ? (string) self::meta( $order, 'status' ) : '';
		if ( ! in_array( $status, array( 'awaiting', 'underpaid' ), true ) ) {
			return $text;
		}
		$lead = 'underpaid' === $status
			? __( 'Part of your payment has arrived.', 'xorro-direct-wallet-payments-woocommerce' )
			: __( 'Your order is waiting for payment.', 'xorro-direct-wallet-payments-woocommerce' );
		$link = 'underpaid' === $status
			? __( 'Send the remaining amount below', 'xorro-direct-wallet-payments-woocommerce' )
			: __( 'Complete your crypto payment below', 'xorro-direct-wallet-payments-woocommerce' );
		return $text . ' <span class="xdwp-pay-callout">' . esc_html( $lead ) . ' <a href="#xdwp-box">' . esc_html( $link ) . ' &darr;</a></span>';
	}

	/**
	 * Assign payment details to order.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $coin_id Coin ID.
	 * @return bool
	 */
	public static function assign_payment( $order, $coin_id ) {
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return false;
		}

		$order_id = $order->get_id();
		$address  = '';
		$amount   = '';
		$reserved = false;

		// A new payment attempt (e.g. paying a failed order again) starts clean.
		foreach ( array( 'txid', 'received', 'partial_received', 'remainder', 'partial_txids', 'partial_since', 'overpaid', 'late_txid', 'late_amount', 'late_checked', 'reminder_sent' ) as $stale ) {
			$order->delete_meta_data( '_xdwp_' . $stale );
		}

		// A collision with another open order on the same address (overlapping match bands)
		// is retried with the next rotated address and a freshly minted unique amount rather
		// than failing the checkout. The first attempt uses the quote the customer saw.
		for ( $attempt = 0; $attempt < 5 && ! $reserved; $attempt++ ) {
			$address = Xdwp_Wallets::pick_address( $coin_id );
			if ( ! $address ) {
				self::note_setup_failure( $order, $coin_id, __( 'no wallet address is configured for this coin.', 'xorro-direct-wallet-payments-woocommerce' ) );
				return false;
			}

			$amount = 0 === $attempt
				? Xdwp_Prices::take_checkout_quote( (float) $order->get_total(), $coin_id, $order->get_currency() )
				: Xdwp_Prices::fiat_to_crypto( (float) $order->get_total(), $coin_id, $order->get_currency(), true );
			if ( '' === $amount || (float) $amount <= 0 ) {
				self::note_setup_failure( $order, $coin_id, __( 'no live exchange rate was available (CoinGecko rate limit or outage — see WooCommerce → Status → Logs, source "xorro-wallet-payments").', 'xorro-direct-wallet-payments-woocommerce' ) );
				return false;
			}

			// Atomic (address, amount) reservation closes the concurrent-checkout TOCTOU window.
			$reserved = Xdwp_Verifier::amount_safe_for_address( $coin_id, $address, $amount, $order_id )
				&& Xdwp_Verifier::reserve_amount_slot( $address, $amount, $order_id );
		}

		if ( ! $reserved ) {
			self::note_setup_failure( $order, $coin_id, __( 'other open orders on the same address expect overlapping amounts, so payments could not be told apart. Adding more addresses for this coin (with rotation on) avoids this.', 'xorro-direct-wallet-payments-woocommerce' ) );
			return false;
		}

		$window  = (int) Xdwp_Settings::get( 'payment_window', 60 );
		$started = time();
		$expires = $started + ( $window * MINUTE_IN_SECONDS );

		$order->update_meta_data( '_xdwp_coin', $coin_id );
		$order->update_meta_data( '_xdwp_address', $address );
		$order->update_meta_data( '_xdwp_amount', $amount );
		$order->update_meta_data( '_xdwp_started', $started );
		$order->update_meta_data( '_xdwp_expires', $expires );
		$order->update_meta_data( '_xdwp_status', 'awaiting' );
		$order->save();

		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: 1: amount 2: coin 3: address */
				__( 'Awaiting crypto payment of %1$s %2$s to %3$s.', 'xorro-direct-wallet-payments-woocommerce' ),
				$amount,
				$coin['symbol'],
				$address
			)
		);

		return true;
	}

	/**
	 * Mark order as paid after on-chain confirmation.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function mark_paid( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();
		$lock_key = 'xdwp_paying_' . $order_id;
		$now      = time() . ':' . wp_generate_password( 8, false ); // (int) still reads the age; token proves ownership.

		// Atomic lock via a real INSERT-only compare-and-set; stale takeover uses compare-and-swap.
		if ( ! Xdwp_Verifier::atomic_add_option( $lock_key, $now ) ) {
			$existing = Xdwp_Verifier::read_option_raw( $lock_key );
			// Fresh lock younger than 2 minutes — another worker owns it.
			if ( $existing && ( time() - (int) $existing ) < 120 ) {
				return;
			}
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					$now,
					$lock_key,
					$existing
				)
			);
			Xdwp_Verifier::forget_option_cache( $lock_key );
			if ( 1 !== $updated ) {
				return;
			}
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			$status = Xdwp_Order::meta( $order, 'status' );
			if ( 'paid' === $status ) {
				return;
			}
			if ( ! in_array( $status, array( 'awaiting', 'underpaid', 'expired' ), true ) ) {
				return;
			}

			// Awaiting/underpaid: only while WC still expects payment. Expired: allow recovery on failed/on-hold/pending.
			$wc_status = $order->get_status();
			if ( in_array( $status, array( 'awaiting', 'underpaid' ), true ) && ! in_array( $wc_status, array( 'pending', 'on-hold' ), true ) ) {
				return;
			}
			if ( 'expired' === $status && ! in_array( $wc_status, array( 'failed', 'pending', 'on-hold' ), true ) ) {
				return;
			}

			if ( $order->is_paid() ) {
				$order->update_meta_data( '_xdwp_status', 'paid' );
				$order->update_meta_data( '_xdwp_confirmed_at', time() );
				$order->save();
				if ( class_exists( 'Xdwp_Verifier' ) ) {
					Xdwp_Verifier::release_amount_slot(
						(string) self::meta( $order, 'address' ),
						(string) self::meta( $order, 'amount' ),
						$order_id
					);
				}
				return;
			}

			$txid = Xdwp_Order::meta( $order, 'txid' );
			$order->payment_complete( $txid ? $txid : '' );

			// Only mark plugin status after WooCommerce no longer needs payment.
			$order = wc_get_order( $order_id );
			if ( ! $order || $order->needs_payment() ) {
				return;
			}

			$order->update_meta_data( '_xdwp_status', 'paid' );
			$order->update_meta_data( '_xdwp_confirmed_at', time() );
			$order->save();

			// Free the dust slot so shared wallets can reuse amounts after wrap (txid claim still blocks double-spend).
			if ( class_exists( 'Xdwp_Verifier' ) ) {
				Xdwp_Verifier::release_amount_slot(
					(string) self::meta( $order, 'address' ),
					(string) self::meta( $order, 'amount' ),
					$order_id
				);
			}

			$target = Xdwp_Settings::get( 'order_status', 'processing' );

			if ( 'completed' === $target && 'completed' !== $order->get_status() ) {
				$order->update_status( 'completed', __( 'Crypto payment confirmed on-chain.', 'xorro-direct-wallet-payments-woocommerce' ) );
			} elseif ( 'on-hold' === $target ) {
				$order->update_status( 'on-hold', __( 'Crypto payment confirmed on-chain (held).', 'xorro-direct-wallet-payments-woocommerce' ) );
			} else {
				$order->add_order_note( __( 'Crypto payment confirmed on-chain.', 'xorro-direct-wallet-payments-woocommerce' ) );
			}

			$overpaid = (string) self::meta( $order, 'overpaid' );
			if ( '' !== $overpaid ) {
				$coin = Xdwp_Coins::get( (string) self::meta( $order, 'coin' ) );
				$order->add_order_note(
					sprintf(
						/* translators: 1: amount received, 2: symbol, 3: amount due, 4: excess */
						__( 'Overpayment: received %1$s %2$s for %3$s %2$s due (%4$s %2$s extra). Refund the difference if appropriate.', 'xorro-direct-wallet-payments-woocommerce' ),
						(string) self::meta( $order, 'received' ),
						$coin ? $coin['symbol'] : '',
						(string) self::meta( $order, 'amount' ),
						$overpaid
					)
				);
				do_action( 'xdwp_order_overpaid', $order, $overpaid );
			}
			do_action( 'xdwp_order_paid', $order, (string) self::meta( $order, 'txid' ) );
		} finally {
			// Release only our own lock: after a stale takeover, a slow earlier worker must not
			// delete the newer worker's lock.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock_key, $now ) );
			Xdwp_Verifier::forget_option_cache( $lock_key );
		}
	}

	/**
	 * Expire unpaid order past payment window.
	 *
	 * Uses a grace period after the quoted window so late on-chain payments can still confirm.
	 * Does not auto-cancel WooCommerce orders (avoids customers losing funds that arrive late).
	 *
	 * @param WC_Order $order Order.
	 */
	public static function maybe_expire( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( ! in_array( $order->get_status(), array( 'on-hold', 'pending' ), true ) ) {
			return;
		}
		$xdwp_status = (string) Xdwp_Order::meta( $order, 'status' );
		if ( ! in_array( $xdwp_status, array( 'awaiting', 'underpaid' ), true ) ) {
			return;
		}
		$expires = (int) Xdwp_Order::meta( $order, 'expires' );
		if ( ! $expires || time() <= $expires ) {
			return;
		}

		$grace = max( 0, (int) Xdwp_Settings::get( 'expiry_grace_minutes', 30 ) ) * MINUTE_IN_SECONDS;
		if ( time() <= ( $expires + $grace ) ) {
			// Still within grace — keep awaiting so verifier/cron can recover late txs.
			return;
		}

		// Last-chance verify before expiring so on-chain funds are claimed (not reusable by a later order).
		if ( 'yes' === Xdwp_Settings::get( 'auto_verify', 'yes' ) && class_exists( 'Xdwp_Verifier' ) && Xdwp_Verifier::verify_order( $order ) ) {
			self::mark_paid( $order );
			return;
		}

		// The last-chance check may have just recorded a partial payment (which restarts the
		// window), or another request may have changed the order: never expire a stale copy.
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh
			|| (string) self::meta( $fresh, 'status' ) !== $xdwp_status
			|| (int) self::meta( $fresh, 'expires' ) !== $expires
			|| ! in_array( $fresh->get_status(), array( 'on-hold', 'pending' ), true ) ) {
			return;
		}
		$order = $fresh;

		$order->update_meta_data( '_xdwp_status', 'expired' );
		$order->save();
		if ( 'underpaid' === $xdwp_status ) {
			$coin = Xdwp_Coins::get( (string) self::meta( $order, 'coin' ) );
			$sym  = $coin ? $coin['symbol'] : '';
			$order->update_status(
				'failed',
				sprintf(
					/* translators: 1: amount received, 2: symbol, 3: amount due */
					__( 'Crypto payment window expired with a partial payment: received %1$s %2$s of %3$s %2$s. Refund the customer or complete the order manually.', 'xorro-direct-wallet-payments-woocommerce' ),
					(string) self::meta( $order, 'received' ),
					$sym,
					(string) self::meta( $order, 'amount' )
				)
			);
		} else {
			$order->update_status(
				'failed',
				__( 'Crypto payment window expired. Contact the store if you already sent funds.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}
		do_action( 'xdwp_order_expired', $order, $xdwp_status );
	}

	/**
	 * Thank-you / payment page box.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function render_payment_box( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			return;
		}
		self::load_template( $order );
	}

	/**
	 * Also show on My Account view order while awaiting.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function maybe_render_on_view( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			return;
		}
		if ( ! in_array( (string) Xdwp_Order::meta( $order, 'status' ), array( 'awaiting', 'underpaid', 'expired' ), true ) ) {
			return;
		}
		self::load_template( $order );
	}

	/**
	 * Load payment template.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function load_template( $order ) {
		// Expire past-window orders before rendering so QR/amount are not shown.
		self::maybe_expire( $order );
		$order = wc_get_order( $order->get_id() );
		if ( ! $order ) {
			return;
		}

		$coin_id = Xdwp_Order::meta( $order, 'coin' );
		$coin    = Xdwp_Coins::get( $coin_id );
		$address = Xdwp_Order::meta( $order, 'address' );
		$amount  = Xdwp_Order::meta( $order, 'amount' );
		$expires = (int) Xdwp_Order::meta( $order, 'expires' );
		$status  = Xdwp_Order::meta( $order, 'status' );

		if ( ! $coin || ! $address || ! $amount ) {
			echo '<p class="xdwp-error">' . esc_html__( 'Payment details are unavailable for this order.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';
			return;
		}

		// After a partial payment the box asks for the remaining amount only.
		$received = (string) Xdwp_Order::meta( $order, 'received' );
		$due      = $amount;
		if ( 'underpaid' === $status && '' !== (string) Xdwp_Order::meta( $order, 'remainder' ) ) {
			$amount = (string) Xdwp_Order::meta( $order, 'remainder' );
		}

		$uri = Xdwp_Coins::payment_uri( $coin_id, $address, $amount );

		// Ensure handles exist even if wp_enqueue_scripts already ran.
		if ( ! wp_style_is( 'xdwp-frontend', 'registered' ) ) {
			wp_register_style(
				'xdwp-frontend',
				XDWP_URL . 'assets/css/frontend.css',
				array(),
				XDWP_VERSION
			);
		}
		if ( ! wp_script_is( 'xdwp-qrcode', 'registered' ) ) {
			wp_register_script(
				'xdwp-qrcode',
				XDWP_URL . 'assets/js/qrcode.min.js',
				array(),
				'1.0.0',
				true
			);
		}
		if ( ! wp_script_is( 'xdwp-frontend', 'registered' ) ) {
			wp_register_script(
				'xdwp-frontend',
				XDWP_URL . 'assets/js/frontend.js',
				array( 'xdwp-qrcode' ),
				XDWP_VERSION,
				true
			);
		}

		wp_enqueue_style( 'xdwp-frontend' );
		wp_enqueue_script( 'xdwp-qrcode' );
		wp_enqueue_script( 'xdwp-frontend' );

		// Ensure footer prints these even when thank-you runs after the normal enqueue pass.
		add_action(
			'wp_footer',
			static function () {
				if ( ! wp_script_is( 'xdwp-frontend', 'done' ) ) {
					wp_print_scripts( 'xdwp-qrcode' );
					wp_print_scripts( 'xdwp-frontend' );
				}
			},
			5
		);

		$grace = max( 0, (int) Xdwp_Settings::get( 'expiry_grace_minutes', 30 ) ) * MINUTE_IN_SECONDS;

		wp_localize_script(
			'xdwp-frontend',
			'xdwpData',
			array(
				'ajaxUrl'   => Xdwp_Ajax::endpoint( 'xdwp_status' ),
				'nonce'     => wp_create_nonce( 'xdwp_status_' . $order->get_id() ),
				'orderId'   => $order->get_id(),
				'orderKey'  => $order->get_order_key(),
				'expires'   => $expires,
				'pollUntil' => $expires + $grace,
				'qrValue'   => $uri,
				'address'   => $address,
				'amount'    => $amount,
				'status'    => $status,
				'renewUrl'  => Xdwp_Ajax::endpoint( 'xdwp_renew' ),
				'i18n'      => array(
					'detected' => __( 'Payment detected — waiting for network confirmations…', 'xorro-direct-wallet-payments-woocommerce' ),
					'renewing' => __( 'Getting a new amount…', 'xorro-direct-wallet-payments-woocommerce' ),
					'renewFail' => __( 'Could not get a new amount. Please contact us.', 'xorro-direct-wallet-payments-woocommerce' ),
					'copied'   => __( 'Copied!', 'xorro-direct-wallet-payments-woocommerce' ),
					'expired'  => __( 'Payment window expired.', 'xorro-direct-wallet-payments-woocommerce' ),
					'paid'     => __( 'Payment confirmed! Thank you.', 'xorro-direct-wallet-payments-woocommerce' ),
					'checking' => __( 'Checking…', 'xorro-direct-wallet-payments-woocommerce' ),
					'waiting'  => __( 'Waiting for payment…', 'xorro-direct-wallet-payments-woocommerce' ),
					'qrFail'   => __( 'QR unavailable — copy the address manually.', 'xorro-direct-wallet-payments-woocommerce' ),
				),
			)
		);

		wc_get_template(
			'payment.php',
			array(
				'order'    => $order,
				'coin'     => $coin,
				'address'  => $address,
				'amount'   => $amount,
				'expires'  => $expires,
				'status'   => $status,
				'uri'      => $uri,
				'coin_id'  => $coin_id,
				'received'  => $received,
				'due'       => $due,
				'can_renew' => self::can_renew( $order ),
			),
			'xorro-direct-wallet-payments-woocommerce/',
			XDWP_PATH . 'templates/'
		);
	}

	/**
	 * Admin metabox.
	 */
	public static function add_order_metabox() {
		$screen = 'shop_order';

		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			try {
				$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
				if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
					$screen = wc_get_page_screen_id( 'shop-order' );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fall back to classic screen.
			}
		}

		add_meta_box(
			'xdwp_order',
			__( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			array( __CLASS__, 'render_metabox' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Metabox content.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order.
	 */
	public static function render_metabox( $post_or_order ) {
		$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			echo '<p>' . esc_html__( 'Not a Xorro Wallet Payments order.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';
			return;
		}

		$coin_id = Xdwp_Order::meta( $order, 'coin' );
		$coin    = Xdwp_Coins::get( $coin_id );
		echo '<p><strong>' . esc_html__( 'Coin:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( $coin ? $coin['name'] : $coin_id ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Amount:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( Xdwp_Order::meta( $order, 'amount' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Address:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong><br><code style="word-break:break-all;">' . esc_html( Xdwp_Order::meta( $order, 'address' ) ) . '</code></p>';
		$xdwp_status = (string) Xdwp_Order::meta( $order, 'status' );
		$labels      = array(
			'awaiting'  => __( 'Waiting for payment', 'xorro-direct-wallet-payments-woocommerce' ),
			'underpaid' => __( 'Partially paid', 'xorro-direct-wallet-payments-woocommerce' ),
			'paid'      => __( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ),
			'expired'   => __( 'Expired', 'xorro-direct-wallet-payments-woocommerce' ),
			'cancelled' => __( 'Cancelled', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		echo '<p><strong>' . esc_html__( 'Status:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( isset( $labels[ $xdwp_status ] ) ? $labels[ $xdwp_status ] : $xdwp_status ) . '</p>';

		$symbol    = $coin ? $coin['symbol'] : '';
		$received  = (string) Xdwp_Order::meta( $order, 'received' );
		$remainder = (string) Xdwp_Order::meta( $order, 'remainder' );
		$overpaid  = (string) Xdwp_Order::meta( $order, 'overpaid' );
		if ( '' !== $received ) {
			echo '<p><strong>' . esc_html__( 'Received:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( $received . ' ' . $symbol ) . '</p>';
		}
		if ( 'underpaid' === $xdwp_status && '' !== $remainder ) {
			echo '<p><strong>' . esc_html__( 'Still due:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( $remainder . ' ' . $symbol ) . '</p>';
		}
		if ( '' !== $overpaid ) {
			echo '<p><strong>' . esc_html__( 'Overpaid by:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( $overpaid . ' ' . $symbol ) . '</p>';
		}
		$partials = Xdwp_Verifier::partial_txids( $order );
		if ( $partials ) {
			echo '<p><strong>' . esc_html__( 'Partial payment txids:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong><br><code style="word-break:break-all;">' . esc_html( implode( ', ', $partials ) ) . '</code></p>';
		}
		$late_txid = (string) Xdwp_Order::meta( $order, 'late_txid' );
		if ( '' !== $late_txid && 'paid' !== $xdwp_status ) {
			echo '<div class="notice notice-warning inline"><p style="overflow-wrap:anywhere;"><strong>' . esc_html__( 'Payment received after the window closed', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong><br>' . esc_html(
				sprintf(
					/* translators: 1: amount, 2: symbol, 3: txid */
					__( '%1$s %2$s arrived in transaction %3$s. Check it, then use "Mark payment received" below (the transaction ID is filled in) or refund the customer.', 'xorro-direct-wallet-payments-woocommerce' ),
					(string) Xdwp_Order::meta( $order, 'late_amount' ),
					$symbol,
					$late_txid
				)
			) . '</p></div>';
		}

		$can_mark    = in_array( $xdwp_status, array( 'awaiting', 'underpaid', 'expired' ), true )
			&& current_user_can( 'manage_woocommerce' )
			&& in_array( $order->get_status(), array( 'pending', 'on-hold', 'failed' ), true );

		if ( $can_mark ) {
			/*
			 * This metabox is rendered inside WordPress's single overarching
			 * #post edit-order <form>. A nested <form> here is invalid HTML —
			 * browsers drop the inner <form> tag while parsing, so these
			 * fields would silently submit as part of the *order-edit* form
			 * (posting to admin.php?page=wc-orders, not admin-post.php) and
			 * "Mark payment received" would appear to do nothing. We render
			 * plain markup instead and build a real, detached <form> in JS
			 * on click, appended directly to <body> so it isn't nested.
			 */
			$box_id = 'xdwp-mark-paid-' . (int) $order->get_id();
			?>
			<div class="xdwp-mark-paid-box" id="<?php echo esc_attr( $box_id ); ?>">
				<input type="hidden" name="action" value="xdwp_mark_paid" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
				<?php wp_nonce_field( 'xdwp_mark_paid_' . $order->get_id() ); ?>
				<p>
					<label for="xdwp_manual_txid"><strong><?php esc_html_e( 'On-chain transaction ID', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong></label><br />
					<input type="text" class="widefat" id="xdwp_manual_txid" name="xdwp_txid" required minlength="8" autocomplete="off" value="<?php echo esc_attr( 'paid' !== $xdwp_status ? $late_txid : '' ); ?>" />
				</p>
				<p>
					<label>
						<input type="checkbox" name="xdwp_confirm_manual" value="1" required />
						<?php esc_html_e( 'I confirm this transaction paid this order on-chain.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</label>
				</p>
				<p><button type="button" class="button button-primary xdwp-mark-paid-submit"><?php esc_html_e( 'Mark payment received', 'xorro-direct-wallet-payments-woocommerce' ); ?></button></p>
			</div>
			<p class="description"><?php esc_html_e( 'Requires a txid. Use for chains without auto-verify, delayed detection, or late payments after expiry.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<script>
			( function () {
				var box = document.getElementById( <?php echo wp_json_encode( $box_id ); ?> );
				if ( ! box ) {
					return;
				}
				box.querySelector( '.xdwp-mark-paid-submit' ).addEventListener( 'click', function () {
					var sourceFields = Array.prototype.slice.call( box.querySelectorAll( 'input' ) );
					// Validate the real, visible fields in place first — reportValidity()
					// works on form-associated elements even without a <form> ancestor
					// and shows the browser's normal native validation UI.
					var valid = sourceFields.every( function ( field ) {
						return field.reportValidity();
					} );
					if ( ! valid ) {
						return;
					}
					var form = document.createElement( 'form' );
					form.method = 'post';
					form.action = <?php echo wp_json_encode( esc_url_raw( admin_url( 'admin-post.php' ) ) ); ?>;
					form.style.display = 'none';
					// Copy current values into fresh fields; the visible box is left untouched.
					sourceFields.forEach( function ( field ) {
						if ( field.type === 'checkbox' && ! field.checked ) {
							// Native forms omit unchecked checkboxes entirely.
							return;
						}
						var copy = document.createElement( 'input' );
						copy.type = 'hidden';
						copy.name = field.name;
						copy.value = field.value;
						form.appendChild( copy );
					} );
					document.body.appendChild( form );
					form.submit();
				} );
			} )();
			</script>
			<?php
		}
	}

	/**
	 * Confirmation after "Mark payment received".
	 */
	public static function mark_paid_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		if ( empty( $_GET['xdwp_marked_paid'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Payment marked as received. The order status has been updated.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p></div>';
	}

	/**
	 * Manual mark-paid handler (POST only).
	 */
	public static function handle_mark_paid() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'xorro-direct-wallet-payments-woocommerce' ), 405 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'xdwp_mark_paid_' . $order_id );

		$txid = isset( $_POST['xdwp_txid'] ) ? sanitize_text_field( wp_unslash( $_POST['xdwp_txid'] ) ) : '';
		$txid = strtolower( preg_replace( '/\s+/', '', (string) $txid ) );
		// Letters/digits plus the separators real txids use: Hedera 0.0.x-sec-nanos, TON base64 (+/=),
		// Aptos version ids, and the pre-filled late-payment IDs.
		if ( strlen( $txid ) < 8 || strlen( $txid ) > 160 || ! preg_match( '#^[a-z0-9.\-_@+/=:]+$#', $txid ) ) {
			wp_die( esc_html__( 'A valid on-chain transaction ID is required.', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}
		if ( empty( $_POST['xdwp_confirm_manual'] ) ) {
			wp_die( esc_html__( 'Manual confirmation checkbox is required.', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::is_ours( $order ) ) {
			wp_die( esc_html__( 'Order not found.', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}

		// Same eligibility as the admin UI — never squat a txid on cancelled/ineligible orders.
		$xdwp_status = (string) self::meta( $order, 'status' );
		$wc_status   = $order->get_status();
		$eligible    = in_array( $xdwp_status, array( 'awaiting', 'underpaid', 'expired' ), true );
		if ( $eligible && in_array( $xdwp_status, array( 'awaiting', 'underpaid' ), true ) ) {
			$eligible = in_array( $wc_status, array( 'pending', 'on-hold' ), true );
		}
		if ( $eligible && 'expired' === $xdwp_status ) {
			$eligible = in_array( $wc_status, array( 'failed', 'pending', 'on-hold' ), true );
		}
		if ( ! $eligible ) {
			wp_die( esc_html__( 'This order cannot be marked paid (wrong status).', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}

		if ( ! Xdwp_Verifier::reserve_txid( $txid, $order_id ) ) {
			wp_die( esc_html__( 'That transaction ID is already linked to another order.', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}

		$order->update_meta_data( '_xdwp_txid', $txid );
		$order->save();
		self::mark_paid( $order );

		$order = wc_get_order( $order_id );
		if ( ! $order || 'paid' !== self::meta( $order, 'status' ) ) {
			Xdwp_Verifier::release_txid( $txid, $order_id );
			$order && $order->delete_meta_data( '_xdwp_txid' );
			$order && $order->save();
			wp_die( esc_html__( 'Could not complete payment for this order. The transaction ID was not kept.', 'xorro-direct-wallet-payments-woocommerce' ), '', array( 'back_link' => true, 'response' => 400 ) );
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: transaction id */
				__( 'Payment marked as received manually by admin (txid: %s).', 'xorro-direct-wallet-payments-woocommerce' ),
				$txid
			),
			false,
			true
		);

		// get_edit_order_url() is correct for both HPOS and legacy post storage.
		wp_safe_redirect( add_query_arg( 'xdwp_marked_paid', '1', wp_get_referer() ? wp_get_referer() : $order->get_edit_order_url() ) );
		exit;
	}

	/**
	 * Optionally append crypto equivalent to product price HTML.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function maybe_append_crypto_price( $html, $product ) {
		if ( is_admin() || 'yes' !== Xdwp_Settings::get( 'price_coin_show', 'no' ) ) {
			return $html;
		}
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}

		$coin_id = Xdwp_Settings::get( 'price_coin_ticker', 'BTC' );
		$coin    = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return $html;
		}

		$price = (float) $product->get_price();
		if ( $price <= 0 ) {
			return $html;
		}

		$amount = Xdwp_Prices::fiat_to_crypto( $price, $coin_id, get_woocommerce_currency(), false );
		if ( '' === $amount ) {
			return $html;
		}

		$html .= ' <span class="xdwp-product-price">/ ' . esc_html( $amount . ' ' . $coin['symbol'] ) . '</span>';
		return $html;
	}

	/**
	 * Admin order billing section note.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_order_info( $order ) {
		if ( ! Xdwp_Order::is_ours( $order ) ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ) . ':</strong> ' . esc_html( Xdwp_Order::meta( $order, 'coin' ) ) . ' / ' . esc_html( Xdwp_Order::meta( $order, 'amount' ) ) . '</p>';
	}
}
