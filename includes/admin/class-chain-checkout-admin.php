<?php
/**
 * Admin settings pages.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Admin
 */
class Chain_Checkout_Admin {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'plugin_action_links_' . CHAIN_CHECKOUT_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Mark plugin screens for CSS scoping.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( self::is_plugin_screen() ) {
			$classes .= ' chain-checkout-admin-page';
		}
		return $classes;
	}

	/**
	 * Whether the current admin request is a Xorro Wallet Payments settings screen.
	 *
	 * @return bool
	 */
	private static function is_plugin_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( '' !== $page && 0 === strpos( $page, 'xorro-direct-wallet-payments-woocommerce' ) );
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			'xorro-direct-wallet-payments-woocommerce',
			array( __CLASS__, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		add_submenu_page(
			'xorro-direct-wallet-payments-woocommerce',
			__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			'xorro-direct-wallet-payments-woocommerce',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'xorro-direct-wallet-payments-woocommerce',
			__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			'xorro-direct-wallet-payments-woocommerce-coins',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'xorro-direct-wallet-payments-woocommerce',
			__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			'xorro-direct-wallet-payments-woocommerce-wallets',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'xorro-direct-wallet-payments-woocommerce',
			__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			'xorro-direct-wallet-payments-woocommerce-prices',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Current tab from page slug.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'xorro-direct-wallet-payments-woocommerce'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map  = array(
			'xorro-direct-wallet-payments-woocommerce'         => 'general',
			'xorro-direct-wallet-payments-woocommerce-coins'   => 'coins',
			'xorro-direct-wallet-payments-woocommerce-wallets' => 'wallets',
			'xorro-direct-wallet-payments-woocommerce-prices'  => 'prices',
			// Legacy slugs (pre-1.4.6) still resolve to the correct tab if bookmarked.
			'chain-checkout-coins'   => 'coins',
			'chain-checkout-wallets' => 'wallets',
			'chain-checkout-prices'  => 'prices',
		);
		return isset( $map[ $page ] ) ? $map[ $page ] : 'general';
	}

	/**
	 * Save settings.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['chain_checkout_save'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( 'chain_checkout_save_settings', 'chain_checkout_nonce' );

		$raw = isset( $_POST['chain_checkout'] ) && is_array( $_POST['chain_checkout'] )
			? wp_unslash( $_POST['chain_checkout'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		// Checkbox flags absent when unchecked.
		$tab = self::current_tab();
		if ( 'general' === $tab ) {
			foreach ( array( 'unique_amounts', 'wallet_rotation', 'auto_verify' ) as $flag ) {
				if ( ! isset( $raw[ $flag ] ) ) {
					$raw[ $flag ] = 'no';
				}
			}
		}
		if ( 'coins' === $tab && ! isset( $raw['enabled_coins'] ) ) {
			$raw['enabled_coins'] = array();
		}
		if ( 'prices' === $tab && ! isset( $raw['price_coin_show'] ) ) {
			$raw['price_coin_show'] = 'no';
		}

		$clean = Chain_Checkout_Settings::sanitize( $raw );
		Chain_Checkout_Settings::update( $clean );

		// Keep WooCommerce gateway title/description in sync when saved from plugin General tab.
		if ( 'general' === $tab ) {
			$wc_key = 'woocommerce_' . CHAIN_CHECKOUT_GATEWAY_ID . '_settings';
			$wc     = get_option( $wc_key, array() );
			if ( ! is_array( $wc ) ) {
				$wc = array();
			}
			if ( isset( $clean['title'] ) ) {
				$wc['title'] = $clean['title'];
			}
			if ( isset( $clean['description'] ) ) {
				$wc['description'] = $clean['description'];
			}
			update_option( $wc_key, $wc );
		}

		add_settings_error(
			'chain_checkout',
			'chain_checkout_saved',
			__( 'Settings saved.', 'xorro-direct-wallet-payments-woocommerce' ),
			'success'
		);
	}

	/**
	 * Setup notice when no wallets configured.
	 */
	public static function setup_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'xorro-direct-wallet-payments-woocommerce' ) ) {
			$payable = Chain_Checkout_Coins::get_payable();
			if ( ! empty( $payable ) ) {
				return;
			}
			// Only show on WooCommerce/plugins screens to avoid noise.
			if ( ! $screen || ( false === strpos( (string) $screen->id, 'woocommerce' ) && 'plugins' !== $screen->id ) ) {
				return;
			}
		} else {
			$payable = Chain_Checkout_Coins::get_payable();
			if ( ! empty( $payable ) ) {
				return;
			}
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: settings URL */
				__( 'Xorro Direct Wallet Payments for WooCommerce is installed. Enable coins and add wallet addresses in %s to start accepting payments.', 'xorro-direct-wallet-payments-woocommerce' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-coins' ) ) . '">' . esc_html__( 'Xorro Wallet Payments settings', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
			),
			array(
				'a' => array(
					'href' => true,
				),
			)
		);
		echo '</p></div>';
	}

	/**
	 * Render settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Assets are enqueued on admin_enqueue_scripts; print late if that missed.
		if ( ! wp_style_is( 'chain-checkout-admin', 'enqueued' ) ) {
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
		}
		if ( ! wp_script_is( 'chain-checkout-admin', 'enqueued' ) ) {
			wp_enqueue_script(
				'chain-checkout-admin',
				CHAIN_CHECKOUT_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				CHAIN_CHECKOUT_VERSION,
				true
			);
			wp_localize_script(
				'chain-checkout-admin',
				'chainCheckoutAdmin',
				array(
					'defaultIcon'      => class_exists( 'Chain_Checkout_Branding' ) ? Chain_Checkout_Branding::default_icon_url() : '',
					'mediaTitle'       => __( 'Select checkout icon', 'xorro-direct-wallet-payments-woocommerce' ),
					'mediaButton'      => __( 'Use this icon', 'xorro-direct-wallet-payments-woocommerce' ),
					'mediaUnavailable' => __( 'Media library is not available.', 'xorro-direct-wallet-payments-woocommerce' ),
				)
			);
		}
		wp_enqueue_media();

		if ( did_action( 'admin_print_styles' ) && wp_style_is( 'chain-checkout-admin', 'enqueued' ) ) {
			wp_print_styles( 'chain-checkout-admin' );
		}

		$tab      = self::current_tab();
		$settings = Chain_Checkout_Settings::all();
		$groups   = Chain_Checkout_Coins::grouped();

		settings_errors( 'chain_checkout' );

		include CHAIN_CHECKOUT_PATH . 'includes/admin/views/settings-page.php';
	}
}
