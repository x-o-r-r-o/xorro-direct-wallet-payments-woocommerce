<?php
/**
 * Order payment lifecycle helpers.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Order
 */
class Chain_Checkout_Order {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_thankyou_' . CHAIN_CHECKOUT_GATEWAY_ID, array( __CLASS__, 'render_payment_box' ), 10, 1 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'maybe_render_on_view' ), 5 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_metabox' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_info' ), 10, 1 );
		add_action( 'admin_post_chain_checkout_mark_paid', array( __CLASS__, 'handle_mark_paid' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'maybe_append_crypto_price' ), 20, 2 );
	}

	/**
	 * Assign payment details to order.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $coin_id Coin ID.
	 * @return bool
	 */
	public static function assign_payment( $order, $coin_id ) {
		$coin = Chain_Checkout_Coins::get( $coin_id );
		if ( ! $coin ) {
			return false;
		}

		$address = Chain_Checkout_Wallets::pick_address( $coin_id );
		if ( ! $address ) {
			return false;
		}

		$amount = Chain_Checkout_Prices::fiat_to_crypto( (float) $order->get_total(), $coin_id, $order->get_currency(), true );
		if ( '' === $amount || (float) $amount <= 0 ) {
			return false;
		}

		$window  = (int) Chain_Checkout_Settings::get( 'payment_window', 60 );
		$started = time();
		$expires = $started + ( $window * MINUTE_IN_SECONDS );

		$order->update_meta_data( '_chain_checkout_coin', $coin_id );
		$order->update_meta_data( '_chain_checkout_address', $address );
		$order->update_meta_data( '_chain_checkout_amount', $amount );
		$order->update_meta_data( '_chain_checkout_started', $started );
		$order->update_meta_data( '_chain_checkout_expires', $expires );
		$order->update_meta_data( '_chain_checkout_status', 'awaiting' );
		$order->save();

		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: 1: amount 2: coin 3: address */
				__( 'Awaiting crypto payment of %1$s %2$s to %3$s.', 'chain-checkout' ),
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
		$lock_key = 'chain_checkout_paying_' . $order_id;

		// Atomic-ish lock: add_option fails if key already exists.
		if ( ! add_option( $lock_key, (string) time(), '', 'no' ) ) {
			$existing = get_option( $lock_key );
			// Stale lock older than 2 minutes — take over.
			if ( $existing && ( time() - (int) $existing ) < 120 ) {
				return;
			}
			update_option( $lock_key, (string) time(), false );
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			$status = $order->get_meta( '_chain_checkout_status' );
			if ( 'paid' === $status || 'awaiting' !== $status ) {
				return;
			}

			// Re-check after reload to reduce double completion.
			$order->update_meta_data( '_chain_checkout_status', 'paid' );
			$order->update_meta_data( '_chain_checkout_confirmed_at', time() );
			$order->save();

			$order = wc_get_order( $order_id );
			if ( ! $order || 'paid' !== $order->get_meta( '_chain_checkout_status' ) ) {
				return;
			}
			if ( $order->is_paid() ) {
				return;
			}

			$target = Chain_Checkout_Settings::get( 'order_status', 'processing' );
			$order->payment_complete( $order->get_meta( '_chain_checkout_txid' ) );

			if ( 'completed' === $target && 'completed' !== $order->get_status() ) {
				$order->update_status( 'completed', __( 'Crypto payment confirmed on-chain.', 'chain-checkout' ) );
			} elseif ( 'on-hold' === $target ) {
				$order->update_status( 'on-hold', __( 'Crypto payment confirmed on-chain (held).', 'chain-checkout' ) );
			} else {
				$order->add_order_note( __( 'Crypto payment confirmed on-chain.', 'chain-checkout' ) );
			}
		} finally {
			delete_option( $lock_key );
		}
	}

	/**
	 * Expire unpaid order past payment window.
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
		$expires = (int) $order->get_meta( '_chain_checkout_expires' );
		if ( $expires && time() > $expires ) {
			$order->update_meta_data( '_chain_checkout_status', 'expired' );
			$order->save();
			$order->update_status( 'cancelled', __( 'Crypto payment window expired.', 'chain-checkout' ) );
		}
	}

	/**
	 * Thank-you / payment page box.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function render_payment_box( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== CHAIN_CHECKOUT_GATEWAY_ID ) {
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
		if ( ! $order || $order->get_payment_method() !== CHAIN_CHECKOUT_GATEWAY_ID ) {
			return;
		}
		if ( 'awaiting' !== $order->get_meta( '_chain_checkout_status' ) ) {
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

		$coin_id = $order->get_meta( '_chain_checkout_coin' );
		$coin    = Chain_Checkout_Coins::get( $coin_id );
		$address = $order->get_meta( '_chain_checkout_address' );
		$amount  = $order->get_meta( '_chain_checkout_amount' );
		$expires = (int) $order->get_meta( '_chain_checkout_expires' );
		$status  = $order->get_meta( '_chain_checkout_status' );

		if ( ! $coin || ! $address || ! $amount ) {
			echo '<p class="chain-checkout-error">' . esc_html__( 'Payment details are unavailable for this order.', 'chain-checkout' ) . '</p>';
			return;
		}

		$uri = Chain_Checkout_Coins::payment_uri( $coin_id, $address, $amount );

		// Ensure handles exist even if wp_enqueue_scripts already ran.
		if ( ! wp_style_is( 'chain-checkout-frontend', 'registered' ) ) {
			wp_register_style(
				'chain-checkout-frontend',
				CHAIN_CHECKOUT_URL . 'assets/css/frontend.css',
				array(),
				CHAIN_CHECKOUT_VERSION
			);
		}
		if ( ! wp_script_is( 'chain-checkout-qrcode', 'registered' ) ) {
			wp_register_script(
				'chain-checkout-qrcode',
				CHAIN_CHECKOUT_URL . 'assets/js/qrcode.min.js',
				array(),
				'1.0.0',
				true
			);
		}
		if ( ! wp_script_is( 'chain-checkout-frontend', 'registered' ) ) {
			wp_register_script(
				'chain-checkout-frontend',
				CHAIN_CHECKOUT_URL . 'assets/js/frontend.js',
				array( 'chain-checkout-qrcode' ),
				CHAIN_CHECKOUT_VERSION,
				true
			);
		}

		wp_enqueue_style( 'chain-checkout-frontend' );
		wp_enqueue_script( 'chain-checkout-qrcode' );
		wp_enqueue_script( 'chain-checkout-frontend' );

		wp_localize_script(
			'chain-checkout-frontend',
			'chainCheckoutData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'chain_checkout_status' ),
				'orderId'   => $order->get_id(),
				'expires'   => $expires,
				'qrValue'   => $uri,
				'address'   => $address,
				'amount'    => $amount,
				'status'    => $status,
				'i18n'      => array(
					'copied'   => __( 'Copied!', 'chain-checkout' ),
					'expired'  => __( 'Payment window expired.', 'chain-checkout' ),
					'paid'     => __( 'Payment confirmed! Thank you.', 'chain-checkout' ),
					'checking' => __( 'Checking…', 'chain-checkout' ),
					'waiting'  => __( 'Waiting for payment…', 'chain-checkout' ),
					'qrFail'   => __( 'QR unavailable — copy the address manually.', 'chain-checkout' ),
				),
			)
		);

		include CHAIN_CHECKOUT_PATH . 'templates/payment.php';
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
			'chain_checkout_order',
			__( 'Chain Checkout', 'chain-checkout' ),
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
		if ( ! $order || $order->get_payment_method() !== CHAIN_CHECKOUT_GATEWAY_ID ) {
			echo '<p>' . esc_html__( 'Not a Chain Checkout order.', 'chain-checkout' ) . '</p>';
			return;
		}

		$coin_id = $order->get_meta( '_chain_checkout_coin' );
		$coin    = Chain_Checkout_Coins::get( $coin_id );
		echo '<p><strong>' . esc_html__( 'Coin:', 'chain-checkout' ) . '</strong> ' . esc_html( $coin ? $coin['name'] : $coin_id ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Amount:', 'chain-checkout' ) . '</strong> ' . esc_html( $order->get_meta( '_chain_checkout_amount' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Address:', 'chain-checkout' ) . '</strong><br><code style="word-break:break-all;">' . esc_html( $order->get_meta( '_chain_checkout_address' ) ) . '</code></p>';
		echo '<p><strong>' . esc_html__( 'Status:', 'chain-checkout' ) . '</strong> ' . esc_html( $order->get_meta( '_chain_checkout_status' ) ) . '</p>';

		if ( 'awaiting' === $order->get_meta( '_chain_checkout_status' ) && current_user_can( 'manage_woocommerce' ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=chain_checkout_mark_paid&order_id=' . $order->get_id() ),
				'chain_checkout_mark_paid_' . $order->get_id()
			);
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Mark payment received', 'chain-checkout' ) . '</a></p>';
			echo '<p class="description">' . esc_html__( 'Use for chains without auto-verify, or if detection is delayed.', 'chain-checkout' ) . '</p>';
		}
	}

	/**
	 * Manual mark-paid handler.
	 */
	public static function handle_mark_paid() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'chain-checkout' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'chain_checkout_mark_paid_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( $order && $order->get_payment_method() === CHAIN_CHECKOUT_GATEWAY_ID ) {
			self::mark_paid( $order );
			$order->add_order_note( __( 'Payment marked as received manually by admin.', 'chain-checkout' ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
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
		if ( is_admin() || 'yes' !== Chain_Checkout_Settings::get( 'price_coin_show', 'no' ) ) {
			return $html;
		}
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}

		$coin_id = Chain_Checkout_Settings::get( 'price_coin_ticker', 'BTC' );
		$coin    = Chain_Checkout_Coins::get( $coin_id );
		if ( ! $coin ) {
			return $html;
		}

		$price = (float) $product->get_price();
		if ( $price <= 0 ) {
			return $html;
		}

		$amount = Chain_Checkout_Prices::fiat_to_crypto( $price, $coin_id, get_woocommerce_currency(), false );
		if ( '' === $amount ) {
			return $html;
		}

		$html .= ' <span class="chain-checkout-product-price">/ ' . esc_html( $amount . ' ' . $coin['symbol'] ) . '</span>';
		return $html;
	}

	/**
	 * Admin order billing section note.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_order_info( $order ) {
		if ( $order->get_payment_method() !== CHAIN_CHECKOUT_GATEWAY_ID ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Chain Checkout', 'chain-checkout' ) . ':</strong> ' . esc_html( $order->get_meta( '_chain_checkout_coin' ) ) . ' / ' . esc_html( $order->get_meta( '_chain_checkout_amount' ) ) . '</p>';
	}
}
