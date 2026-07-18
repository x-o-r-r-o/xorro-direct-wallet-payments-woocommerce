<?php
/**
 * AJAX endpoints for checkout coin list and payment status.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Ajax
 */
class Chain_Checkout_Ajax {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_chain_checkout_status', array( __CLASS__, 'payment_status' ) );
		add_action( 'wp_ajax_nopriv_chain_checkout_status', array( __CLASS__, 'payment_status' ) );
		add_action( 'wp_ajax_chain_checkout_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wp_ajax_nopriv_chain_checkout_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_admin_assets' ) );
	}

	/**
	 * Register frontend assets (enqueued on demand).
	 */
	public static function register_assets() {
		$css_ver = CHAIN_CHECKOUT_VERSION;
		$js_ver  = CHAIN_CHECKOUT_VERSION;
		$css     = CHAIN_CHECKOUT_PATH . 'assets/css/frontend.css';
		$js      = CHAIN_CHECKOUT_PATH . 'assets/js/frontend.js';
		if ( is_readable( $css ) ) {
			$css_ver = CHAIN_CHECKOUT_VERSION . '.' . (string) filemtime( $css );
		}
		if ( is_readable( $js ) ) {
			$js_ver = CHAIN_CHECKOUT_VERSION . '.' . (string) filemtime( $js );
		}

		wp_register_style(
			'chain-checkout-frontend',
			CHAIN_CHECKOUT_URL . 'assets/css/frontend.css',
			array(),
			$css_ver
		);

		wp_register_script(
			'chain-checkout-qrcode',
			CHAIN_CHECKOUT_URL . 'assets/js/qrcode.min.js',
			array(),
			'1.0.0',
			true
		);

		wp_register_script(
			'chain-checkout-frontend',
			CHAIN_CHECKOUT_URL . 'assets/js/frontend.js',
			array( 'chain-checkout-qrcode' ),
			$js_ver,
			true
		);

		wp_register_script(
			'chain-checkout-checkout',
			CHAIN_CHECKOUT_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			CHAIN_CHECKOUT_VERSION,
			true
		);
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function register_admin_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_plugin_page = ( 0 === strpos( $page, 'chain-checkout' ) ) || ( false !== strpos( (string) $hook, 'chain-checkout' ) );

		if ( ! $is_plugin_page && 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$ver = CHAIN_CHECKOUT_VERSION;
		$css = CHAIN_CHECKOUT_PATH . 'assets/css/admin.css';
		if ( is_readable( $css ) ) {
			$ver = CHAIN_CHECKOUT_VERSION . '.' . (string) filemtime( $css );
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'chain-checkout-admin',
			CHAIN_CHECKOUT_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			$ver
		);

		wp_enqueue_script(
			'chain-checkout-admin',
			CHAIN_CHECKOUT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CHAIN_CHECKOUT_VERSION,
			true
		);

		if ( $is_plugin_page ) {
			wp_enqueue_media();
		}

		wp_localize_script(
			'chain-checkout-admin',
			'chainCheckoutAdmin',
			array(
				'placeholder'         => __( 'Paste wallet address', 'chain-checkout' ),
				'copy'                => __( 'Copy', 'chain-checkout' ),
				'copied'              => __( 'Copied', 'chain-checkout' ),
				'remove'              => __( 'Remove address', 'chain-checkout' ),
				'invalidFormat'       => __( 'Invalid format', 'chain-checkout' ),
				'duplicate'           => __( 'Duplicate address', 'chain-checkout' ),
				/* translators: %d: number of wallet addresses */
				'addressesConfigured' => __( '%d addresses', 'chain-checkout' ),
				/* translators: %d: coins still missing a wallet */
				'missingWallets'      => __( '%d coin(s) still need an address', 'chain-checkout' ),
				'defaultIcon'         => Chain_Checkout_Branding::default_icon_url(),
				'mediaTitle'          => __( 'Select checkout icon', 'chain-checkout' ),
				'mediaButton'         => __( 'Use this icon', 'chain-checkout' ),
				'mediaUnavailable'    => __( 'Media library is not available.', 'chain-checkout' ),
			)
		);
	}

	/**
	 * Poll payment status for an order.
	 */
	public static function payment_status() {
		check_ajax_referer( 'chain_checkout_status', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== CHAIN_CHECKOUT_GATEWAY_ID ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'chain-checkout' ) ), 400 );
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
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'chain-checkout' ) ), 403 );
		}

		Chain_Checkout_Order::maybe_expire( $order );
		$order = wc_get_order( $order_id );

		$status = $order->get_meta( '_chain_checkout_status' );

		// Throttle live chain checks from the browser poll (cron remains primary).
		if ( 'awaiting' === $status && 'yes' === Chain_Checkout_Settings::get( 'auto_verify', 'yes' ) ) {
			$throttle_key = 'chain_checkout_ajax_verify_' . $order_id;
			if ( ! get_transient( $throttle_key ) ) {
				set_transient( $throttle_key, 1, 45 );
				if ( Chain_Checkout_Verifier::verify_order( $order ) ) {
					Chain_Checkout_Order::mark_paid( $order );
					$order  = wc_get_order( $order_id );
					$status = 'paid';
				}
			}
		}

		wp_send_json_success(
			array(
				'status'  => $status,
				'expires' => (int) $order->get_meta( '_chain_checkout_expires' ),
				'paid'    => ( 'paid' === $status ),
				'expired' => ( 'expired' === $status ),
			)
		);
	}

	/**
	 * Live quote for selected coin at checkout.
	 */
	public static function quote() {
		check_ajax_referer( 'chain_checkout_checkout', 'nonce' );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rate_key = 'chain_checkout_quote_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count > 60 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'chain-checkout' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$coin_id = isset( $_POST['coin'] ) ? sanitize_text_field( wp_unslash( $_POST['coin'] ) ) : '';
		$coin    = Chain_Checkout_Coins::get( $coin_id );
		$payable = Chain_Checkout_Coins::get_payable();

		if ( ! $coin || ! isset( $payable[ $coin_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Coin not available.', 'chain-checkout' ) ), 400 );
		}

		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'chain-checkout' ) ), 400 );
		}

		$total  = (float) WC()->cart->get_total( 'edit' );
		$amount = Chain_Checkout_Prices::fiat_to_crypto( $total, $coin_id );

		if ( '' === $amount ) {
			wp_send_json_error( array( 'message' => __( 'Unable to fetch exchange rate. Try again shortly.', 'chain-checkout' ) ), 503 );
		}

		wp_send_json_success(
			array(
				'coin'   => $coin_id,
				'name'   => $coin['name'],
				'amount' => $amount,
				'symbol' => $coin['symbol'],
				'fiat'   => wc_price( $total ),
				'approx' => true,
			)
		);
	}
}
