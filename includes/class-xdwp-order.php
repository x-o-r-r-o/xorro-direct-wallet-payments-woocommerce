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
		add_action( 'woocommerce_view_order', array( __CLASS__, 'maybe_render_on_view' ), 5 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_metabox' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_info' ), 10, 1 );
		add_action( 'admin_post_xdwp_mark_paid', array( __CLASS__, 'handle_mark_paid' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'maybe_append_crypto_price' ), 20, 2 );
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

		$address = Xdwp_Wallets::pick_address( $coin_id );
		if ( ! $address ) {
			return false;
		}

		$amount = Xdwp_Prices::fiat_to_crypto( (float) $order->get_total(), $coin_id, $order->get_currency(), true );
		if ( '' === $amount || (float) $amount <= 0 ) {
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

			$status = Xdwp_Order::meta( $order, 'status' );
			if ( 'paid' === $status ) {
				return;
			}
			if ( ! in_array( $status, array( 'awaiting', 'expired' ), true ) ) {
				return;
			}

			if ( $order->is_paid() ) {
				$order->update_meta_data( '_xdwp_status', 'paid' );
				$order->update_meta_data( '_xdwp_confirmed_at', time() );
				$order->save();
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

			$target = Xdwp_Settings::get( 'order_status', 'processing' );

			if ( 'completed' === $target && 'completed' !== $order->get_status() ) {
				$order->update_status( 'completed', __( 'Crypto payment confirmed on-chain.', 'xorro-direct-wallet-payments-woocommerce' ) );
			} elseif ( 'on-hold' === $target ) {
				$order->update_status( 'on-hold', __( 'Crypto payment confirmed on-chain (held).', 'xorro-direct-wallet-payments-woocommerce' ) );
			} else {
				$order->add_order_note( __( 'Crypto payment confirmed on-chain.', 'xorro-direct-wallet-payments-woocommerce' ) );
			}
		} finally {
			delete_option( $lock_key );
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
		if ( 'awaiting' !== Xdwp_Order::meta( $order, 'status' ) ) {
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

		$order->update_meta_data( '_xdwp_status', 'expired' );
		$order->save();
		$order->update_status(
			'failed',
			__( 'Crypto payment window expired. Contact the store if you already sent funds.', 'xorro-direct-wallet-payments-woocommerce' )
		);
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
		if ( 'awaiting' !== Xdwp_Order::meta( $order, 'status' ) ) {
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

		wp_localize_script(
			'xdwp-frontend',
			'xdwpData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'xdwp_status_' . $order->get_id() ),
				'orderId'   => $order->get_id(),
				'orderKey'  => $order->get_order_key(),
				'expires'   => $expires,
				'qrValue'   => $uri,
				'address'   => $address,
				'amount'    => $amount,
				'status'    => $status,
				'i18n'      => array(
					'copied'   => __( 'Copied!', 'xorro-direct-wallet-payments-woocommerce' ),
					'expired'  => __( 'Payment window expired.', 'xorro-direct-wallet-payments-woocommerce' ),
					'paid'     => __( 'Payment confirmed! Thank you.', 'xorro-direct-wallet-payments-woocommerce' ),
					'checking' => __( 'Checking…', 'xorro-direct-wallet-payments-woocommerce' ),
					'waiting'  => __( 'Waiting for payment…', 'xorro-direct-wallet-payments-woocommerce' ),
					'qrFail'   => __( 'QR unavailable — copy the address manually.', 'xorro-direct-wallet-payments-woocommerce' ),
				),
			)
		);

		include XDWP_PATH . 'templates/payment.php';
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
		echo '<p><strong>' . esc_html__( 'Status:', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong> ' . esc_html( Xdwp_Order::meta( $order, 'status' ) ) . '</p>';

		if ( 'awaiting' === Xdwp_Order::meta( $order, 'status' ) && current_user_can( 'manage_woocommerce' ) ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="xdwp_mark_paid" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
				<?php wp_nonce_field( 'xdwp_mark_paid_' . $order->get_id() ); ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Mark payment received', 'xorro-direct-wallet-payments-woocommerce' ); ?></button></p>
			</form>
			<p class="description"><?php esc_html_e( 'Use for chains without auto-verify, or if detection is delayed.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<?php
		}
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

		$order = wc_get_order( $order_id );
		if ( $order && Xdwp_Order::is_ours( $order ) ) {
			self::mark_paid( $order );
			$order->add_order_note( __( 'Payment marked as received manually by admin.', 'xorro-direct-wallet-payments-woocommerce' ) );
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
