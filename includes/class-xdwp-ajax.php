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
	 * Poll payment status for an order.
	 */
	public static function payment_status() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny     = static function () {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'xorro-direct-wallet-payments-woocommerce' ) ), 403 );
		};

		if ( ! $order_id ) {
			$deny();
		}

		check_ajax_referer( 'xdwp_status_' . $order_id, 'nonce' );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rate_key = 'xdwp_status_' . md5( $ip . '|' . $order_id );
		$count    = (int) get_transient( $rate_key );
		if ( $count > 120 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$order = wc_get_order( $order_id );

		// Uniform denial — avoid leaking whether an order ID is a Xorro Wallet Payments order.
		if ( ! $order || ! Xdwp_Order::is_ours( $order ) ) {
			$deny();
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
		}

		Xdwp_Order::maybe_expire( $order );
		$order = wc_get_order( $order_id );

		$status = Xdwp_Order::meta( $order, 'status' );

		// Throttle live chain checks from the browser poll (cron remains primary).
		// Only while WooCommerce still expects payment (blocks cancelled/refunded resurrection).
		if (
			'awaiting' === $status
			&& in_array( $order->get_status(), array( 'pending', 'on-hold' ), true )
			&& 'yes' === Xdwp_Settings::get( 'auto_verify', 'yes' )
		) {
			$throttle_key = 'xdwp_ajax_verify_' . $order_id;
			if ( ! get_transient( $throttle_key ) ) {
				set_transient( $throttle_key, 1, 45 );
				if ( Xdwp_Verifier::verify_order( $order ) ) {
					Xdwp_Order::mark_paid( $order );
					$order  = wc_get_order( $order_id );
					$status = 'paid';
				}
			}
		}

		wp_send_json_success(
			array(
				'status'  => $status,
				'expires' => (int) Xdwp_Order::meta( $order, 'expires' ),
				'paid'    => ( 'paid' === $status ),
				'expired' => ( 'expired' === $status ),
			)
		);
	}

	/**
	 * Live quote for selected coin at checkout.
	 */
	public static function quote() {
		check_ajax_referer( 'xdwp_checkout', 'nonce' );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rate_key = 'xdwp_quote_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count > 60 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'xorro-direct-wallet-payments-woocommerce' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$coin_id = isset( $_POST['coin'] ) ? sanitize_text_field( wp_unslash( $_POST['coin'] ) ) : '';
		$coin    = Xdwp_Coins::get( $coin_id );
		$payable = Xdwp_Coins::get_payable();

		if ( ! $coin || ! isset( $payable[ $coin_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Coin not available.', 'xorro-direct-wallet-payments-woocommerce' ) ), 400 );
		}

		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'xorro-direct-wallet-payments-woocommerce' ) ), 400 );
		}

		$total  = (float) WC()->cart->get_total( 'edit' );
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
