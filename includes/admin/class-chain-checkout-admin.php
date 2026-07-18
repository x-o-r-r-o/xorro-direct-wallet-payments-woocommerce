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
		add_filter( 'plugin_action_links_' . CHAIN_CHECKOUT_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=chain-checkout' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'chain-checkout' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Chain Checkout', 'chain-checkout' ),
			__( 'Chain Checkout', 'chain-checkout' ),
			'manage_woocommerce',
			'chain-checkout',
			array( __CLASS__, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		add_submenu_page(
			'chain-checkout',
			__( 'General', 'chain-checkout' ),
			__( 'General', 'chain-checkout' ),
			'manage_woocommerce',
			'chain-checkout',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'chain-checkout',
			__( 'Coins', 'chain-checkout' ),
			__( 'Coins', 'chain-checkout' ),
			'manage_woocommerce',
			'chain-checkout-coins',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'chain-checkout',
			__( 'Wallets', 'chain-checkout' ),
			__( 'Wallets', 'chain-checkout' ),
			'manage_woocommerce',
			'chain-checkout-wallets',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'chain-checkout',
			__( 'Prices & APIs', 'chain-checkout' ),
			__( 'Prices & APIs', 'chain-checkout' ),
			'manage_woocommerce',
			'chain-checkout-prices',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Current tab from page slug.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'chain-checkout'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map  = array(
			'chain-checkout'         => 'general',
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
			__( 'Settings saved.', 'chain-checkout' ),
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
		if ( ! $screen || false === strpos( (string) $screen->id, 'chain-checkout' ) ) {
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
				__( 'Chain Checkout is installed. Enable coins and add wallet addresses in %s to start accepting payments.', 'chain-checkout' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=chain-checkout-coins' ) ) . '">' . esc_html__( 'Chain Checkout settings', 'chain-checkout' ) . '</a>'
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
					'mediaTitle'       => __( 'Select checkout icon', 'chain-checkout' ),
					'mediaButton'      => __( 'Use this icon', 'chain-checkout' ),
					'mediaUnavailable' => __( 'Media library is not available.', 'chain-checkout' ),
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
