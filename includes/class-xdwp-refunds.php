<?php
/**
 * Refunds, without ever holding the money.
 *
 * A card refund goes back the way it came. A crypto payment cannot: the address it arrived from
 * may be an exchange's hot wallet, a smart contract, or somewhere the customer cannot touch.
 * Sending money back there is how refunds are lost for good.
 *
 * So the customer is asked. The shop creates a claim link, the customer opens it and gives an
 * address they control, and the shop sends the refund from its own wallet by hand. This plugin
 * never moves money — it carries the address, records what was sent, and tells both sides.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Claim links for crypto refunds.
 */
class Xdwp_Refunds {

	/**
	 * The query argument a claim link carries.
	 */
	const QUERY_VAR = 'xdwp-claim';

	/**
	 * How long a claim link is good for.
	 */
	const TTL = 14 * DAY_IN_SECONDS;

	/**
	 * How many wrong tokens one visitor may try before being asked to wait.
	 */
	const MAX_ATTEMPTS = 10;

	/**
	 * Hook the front-end route and the admin actions.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_claim' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_post_xdwp_refund_action', array( __CLASS__, 'handle_admin_action' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}
	}

	/**
	 * Whether this order could be refunded through a claim link.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function refundable( $order ) {
		if ( ! $order instanceof WC_Order || ! Xdwp_Order::is_ours( $order ) ) {
			return false;
		}
		$coin = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
		if ( ! $coin ) {
			return false;
		}
		// Only money that actually arrived can be sent back.
		return in_array( (string) Xdwp_Order::meta( $order, 'status' ), array( 'paid', 'underpaid' ), true );
	}

	/**
	 * Where a refund has got to.
	 *
	 * @param WC_Order $order Order.
	 * @return string One of: none, waiting, ready, sent.
	 */
	public static function state( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 'none';
		}
		if ( '' !== (string) Xdwp_Order::meta( $order, 'refund_txid' ) ) {
			return 'sent';
		}
		if ( '' !== (string) Xdwp_Order::meta( $order, 'refund_address' ) ) {
			return 'ready';
		}
		if ( '' !== (string) Xdwp_Order::meta( $order, 'refund_token' ) ) {
			return 'waiting';
		}
		return 'none';
	}

	/**
	 * A token as it is stored: never the token itself.
	 *
	 * @param string $token Raw token from the link.
	 * @return string
	 */
	private static function fingerprint( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	/**
	 * Start a refund: mint a link for this order.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $amount Amount to refund, in the coin. Empty means everything received.
	 * @return string The claim URL, or '' when the order cannot be refunded this way.
	 */
	public static function create_claim( $order, $amount = '' ) {
		if ( ! self::refundable( $order ) ) {
			return '';
		}

		$token  = bin2hex( random_bytes( 24 ) );
		$amount = '' !== (string) $amount
			? (string) $amount
			: (string) ( Xdwp_Order::meta( $order, 'received' ) ? Xdwp_Order::meta( $order, 'received' ) : Xdwp_Order::meta( $order, 'amount' ) );

		$order->update_meta_data( '_xdwp_refund_token', self::fingerprint( $token ) );
		$order->update_meta_data( '_xdwp_refund_expires', time() + self::TTL );
		$order->update_meta_data( '_xdwp_refund_amount', $amount );
		$order->delete_meta_data( '_xdwp_refund_address' );
		$order->delete_meta_data( '_xdwp_refund_txid' );
		$order->save();

		Xdwp_Order::log_event(
			$order,
			'refund',
			__( 'A refund link was created for the customer to give a receiving address', 'xorro-direct-wallet-payments-woocommerce' )
		);
		Xdwp_Order::flag_attention( $order );

		return self::claim_url( $token );
	}

	/**
	 * The link itself.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function claim_url( $token ) {
		return add_query_arg( self::QUERY_VAR, rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * Find the order a token belongs to.
	 *
	 * The token is never stored, so the lookup is by its fingerprint — and the comparison is
	 * done in constant time so the database cannot be probed a character at a time.
	 *
	 * @param string $token Raw token from the link.
	 * @return WC_Order|null
	 */
	public static function order_for_token( $token ) {
		$token = (string) $token;
		if ( 48 !== strlen( $token ) || ! ctype_xdigit( $token ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 2,
				'status'     => 'any',
				'return'     => 'objects',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_xdwp_refund_token',
						'value' => self::fingerprint( $token ),
					),
				),
			)
		);
		if ( empty( $orders ) ) {
			return null;
		}

		$order = $orders[0];
		if ( ! hash_equals( (string) Xdwp_Order::meta( $order, 'refund_token' ), self::fingerprint( $token ) ) ) {
			return null;
		}
		if ( (int) Xdwp_Order::meta( $order, 'refund_expires' ) < time() ) {
			return null;
		}
		return $order;
	}

	/**
	 * Show the claim page, or take the address the customer typed into it.
	 */
	public static function maybe_handle_claim() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential; the form below carries its own nonce.
		$token = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		if ( '' === $token ) {
			return;
		}

		// A link is a guessable thing by nature, so guessing is made expensive.
		$bucket   = 'xdwp_claim_' . md5( (string) self::client_fingerprint() );
		$attempts = (int) get_transient( $bucket );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			self::render( null, '', __( 'Too many attempts. Please wait a few minutes and open the link again.', 'xorro-direct-wallet-payments-woocommerce' ) );
			return;
		}

		$order = self::order_for_token( $token );
		if ( ! $order ) {
			set_transient( $bucket, $attempts + 1, 10 * MINUTE_IN_SECONDS );
			self::render( null, '', __( 'This refund link is not valid, or it has expired. Ask the shop for a new one.', 'xorro-direct-wallet-payments-woocommerce' ) );
			return;
		}

		$error  = '';
		$notice = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately below.
		if ( isset( $_POST['xdwp_claim_submit'] ) ) {
			check_admin_referer( 'xdwp_claim_' . $order->get_id() );

			if ( '' !== (string) Xdwp_Order::meta( $order, 'refund_txid' ) ) {
				$error = __( 'This refund has already been sent.', 'xorro-direct-wallet-payments-woocommerce' );
			} else {
				$address = isset( $_POST['xdwp_refund_address'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['xdwp_refund_address'] ) ) ) : '';
				$coin_id = (string) Xdwp_Order::meta( $order, 'coin' );

				if ( '' === $address ) {
					$error = __( 'Enter the address you want the refund sent to.', 'xorro-direct-wallet-payments-woocommerce' );
				} elseif ( ! Xdwp_Wallets::is_plausible_address( $coin_id, $address ) ) {
					$coin  = Xdwp_Coins::get( $coin_id );
					$error = sprintf(
						/* translators: %s: coin name and network */
						__( 'That does not look like a %s address. Check it in your wallet and try again — money sent to a wrong address cannot be recovered.', 'xorro-direct-wallet-payments-woocommerce' ),
						$coin ? $coin['name'] : $coin_id
					);
				} else {
					self::record_address( $order, $address );
					$notice = __( 'Thank you. The shop has your address and will send the refund from their own wallet.', 'xorro-direct-wallet-payments-woocommerce' );
					$order  = wc_get_order( $order->get_id() );
				}
			}
		}

		self::render( $order, $notice, $error );
	}

	/**
	 * Save the address the customer gave, and tell the shop.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $address Receiving address.
	 */
	public static function record_address( $order, $address ) {
		$order->update_meta_data( '_xdwp_refund_address', $address );
		$order->update_meta_data( '_xdwp_refund_address_at', time() );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: %s: receiving address */
				__( 'The customer asked for their refund to be sent to %s. Send it from your own wallet, then record the transaction id on this order.', 'xorro-direct-wallet-payments-woocommerce' ),
				$address
			)
		);
		Xdwp_Order::log_event(
			$order,
			'refund',
			sprintf(
				/* translators: %s: receiving address */
				__( 'The customer gave a refund address (%s)', 'xorro-direct-wallet-payments-woocommerce' ),
				$address
			)
		);
		Xdwp_Order::flag_attention( $order );

		if ( class_exists( 'Xdwp_Notify' ) ) {
			Xdwp_Notify::report(
				'refund_address',
				$order,
				array(
					'refund_address' => $address,
					'refund_amount'  => (string) Xdwp_Order::meta( $order, 'refund_amount' ),
				)
			);
		}

		/**
		 * The customer has given an address for their refund.
		 *
		 * @param WC_Order $order   Order.
		 * @param string   $address Address they gave.
		 */
		do_action( 'xdwp_refund_address_given', $order, $address );
	}

	/**
	 * Record that the shop has sent the refund.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $txid  Transaction id of the refund.
	 * @return bool
	 */
	public static function mark_sent( $order, $txid ) {
		$txid = trim( (string) $txid );
		if ( ! $order instanceof WC_Order || '' === $txid ) {
			return false;
		}

		$order->update_meta_data( '_xdwp_refund_txid', $txid );
		$order->update_meta_data( '_xdwp_refund_sent_at', time() );
		// The link has done its job; it should not open again.
		$order->delete_meta_data( '_xdwp_refund_token' );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: amount and coin, 2: transaction id */
				__( 'Refund of %1$s sent, transaction %2$s.', 'xorro-direct-wallet-payments-woocommerce' ),
				trim( (string) Xdwp_Order::meta( $order, 'refund_amount' ) . ' ' . self::symbol( $order ) ),
				$txid
			),
			true
		);
		Xdwp_Order::log_event(
			$order,
			'refund',
			sprintf(
				/* translators: %s: transaction id */
				__( 'The shop sent the refund (%s)', 'xorro-direct-wallet-payments-woocommerce' ),
				$txid
			)
		);

		if ( class_exists( 'Xdwp_Notify' ) ) {
			Xdwp_Notify::report( 'refund_sent', $order, array( 'txid' => $txid ) );
		}

		/**
		 * The refund has been sent.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $txid  Transaction id.
		 */
		do_action( 'xdwp_refund_sent', $order, $txid );
		return true;
	}

	/**
	 * Admin actions: create a link, or record that the refund went out.
	 */
	public static function handle_admin_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		check_admin_referer( 'xdwp_refund_' . $order_id );

		$order  = wc_get_order( $order_id );
		$result = 'unknown';

		if ( $order && Xdwp_Order::is_ours( $order ) ) {
			if ( 'create' === $do && self::refundable( $order ) ) {
				$url = self::create_claim( $order );
				if ( '' !== $url ) {
					// Kept for this administrator only, and only long enough to copy: the link is
					// a credential, so it is never written into the order or its notes.
					set_transient( 'xdwp_refund_link_' . $order_id . '_' . get_current_user_id(), $url, 15 * MINUTE_IN_SECONDS );
					$result = 'created';
				}
			} elseif ( 'sent' === $do ) {
				$txid   = isset( $_POST['txid'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['txid'] ) ) ) : '';
				$result = self::mark_sent( $order, $txid ) ? 'sent' : 'notxid';
			} elseif ( 'cancel' === $do ) {
				$order->delete_meta_data( '_xdwp_refund_token' );
				$order->delete_meta_data( '_xdwp_refund_expires' );
				$order->save();
				$result = 'cancelled';
			}
		}

		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-payments' );
		wp_safe_redirect( add_query_arg( 'xdwp_refund', $result, $back ) );
		exit;
	}

	/**
	 * Say what just happened, wherever the merchant was sent back to.
	 */
	public static function admin_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; the action itself was nonced.
		$code = isset( $_GET['xdwp_refund'] ) ? sanitize_key( wp_unslash( $_GET['xdwp_refund'] ) ) : '';
		if ( '' === $code || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$messages = array(
			'created'   => array( 'success', __( 'Refund link created. Copy it from the order and send it to the customer — it is shown for fifteen minutes.', 'xorro-direct-wallet-payments-woocommerce' ) ),
			'sent'      => array( 'success', __( 'Refund recorded.', 'xorro-direct-wallet-payments-woocommerce' ) ),
			'notxid'    => array( 'error', __( 'Enter the transaction id of the refund you sent.', 'xorro-direct-wallet-payments-woocommerce' ) ),
			'cancelled' => array( 'success', __( 'That refund link will no longer open.', 'xorro-direct-wallet-payments-woocommerce' ) ),
			'unknown'   => array( 'error', __( 'That refund could not be dealt with.', 'xorro-direct-wallet-payments-woocommerce' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * The coin's ticker for this order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function symbol( $order ) {
		$coin = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
		return $coin ? $coin['symbol'] : '';
	}

	/**
	 * Something stable enough to rate-limit on, without keeping an address around.
	 *
	 * @return string
	 */
	private static function client_fingerprint() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip . '|' . wp_salt( 'auth' );
	}

	/**
	 * Show the claim page and stop: this URL is a page of its own, not part of the shop.
	 *
	 * @param WC_Order|null $order  Order, when the link was good.
	 * @param string        $notice Something that went right.
	 * @param string        $error  Something that went wrong.
	 */
	private static function render( $order, $notice = '', $error = '' ) {
		$coin   = $order ? Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) ) : null;
		$amount = $order ? (string) Xdwp_Order::meta( $order, 'refund_amount' ) : '';
		$given  = $order ? (string) Xdwp_Order::meta( $order, 'refund_address' ) : '';
		$sent   = $order ? (string) Xdwp_Order::meta( $order, 'refund_txid' ) : '';

		status_header( $order ? 200 : 404 );
		nocache_headers();

		wc_get_template(
			'xdwp-refund-claim.php',
			array(
				'order'  => $order,
				'coin'   => $coin,
				'amount' => $amount,
				'given'  => $given,
				'sent'   => $sent,
				'notice' => $notice,
				'error'  => $error,
			),
			'xorro-direct-wallet-payments-woocommerce/',
			XDWP_PATH . 'templates/'
		);
		exit;
	}
}
