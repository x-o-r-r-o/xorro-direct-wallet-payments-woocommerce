<?php
/**
 * Admin settings pages.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Admin
 */
class Xdwp_Admin {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'plugin_action_links_' . XDWP_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Mark plugin screens for CSS scoping.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( self::is_plugin_screen() ) {
			$classes .= ' xdwp-admin-page';
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
		);
		return isset( $map[ $page ] ) ? $map[ $page ] : 'general';
	}

	/**
	 * Save settings.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['xdwp_save'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( 'xdwp_save_settings', 'xdwp_nonce' );

		$raw = isset( $_POST['xdwp'] ) && is_array( $_POST['xdwp'] )
			? wp_unslash( $_POST['xdwp'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

		$clean = Xdwp_Settings::sanitize( $raw );
		Xdwp_Settings::update( $clean );

		// Keep WooCommerce gateway title/description in sync when saved from plugin General tab.
		if ( 'general' === $tab ) {
			$wc_key = 'woocommerce_' . XDWP_GATEWAY_ID . '_settings';
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
			'xdwp',
			'xdwp_saved',
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
			$payable = Xdwp_Coins::get_payable();
			if ( ! empty( $payable ) ) {
				return;
			}
			// Only show on WooCommerce/plugins screens to avoid noise.
			if ( ! $screen || ( false === strpos( (string) $screen->id, 'woocommerce' ) && 'plugins' !== $screen->id ) ) {
				return;
			}
		} else {
			$payable = Xdwp_Coins::get_payable();
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
		if ( ! wp_style_is( 'xdwp-admin', 'enqueued' ) ) {
			$ver = XDWP_VERSION;
			$css = XDWP_PATH . 'assets/css/admin.css';
			if ( is_readable( $css ) ) {
				$ver = XDWP_VERSION . '.' . (string) filemtime( $css );
			}
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'xdwp-admin',
				XDWP_URL . 'assets/css/admin.css',
				array( 'dashicons' ),
				$ver
			);
		}
		if ( ! wp_script_is( 'xdwp-admin', 'enqueued' ) ) {
			wp_enqueue_script(
				'xdwp-admin',
				XDWP_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				XDWP_VERSION,
				true
			);
			wp_localize_script(
				'xdwp-admin',
				'xdwpAdmin',
				array(
					'defaultIcon'      => class_exists( 'Xdwp_Branding' ) ? Xdwp_Branding::default_icon_url() : '',
					'mediaTitle'       => __( 'Select checkout icon', 'xorro-direct-wallet-payments-woocommerce' ),
					'mediaButton'      => __( 'Use this icon', 'xorro-direct-wallet-payments-woocommerce' ),
					'mediaUnavailable' => __( 'Media library is not available.', 'xorro-direct-wallet-payments-woocommerce' ),
				)
			);
		}
		wp_enqueue_media();

		if ( did_action( 'admin_print_styles' ) && wp_style_is( 'xdwp-admin', 'enqueued' ) ) {
			wp_print_styles( 'xdwp-admin' );
		}

		$tab      = self::current_tab();
		$settings = Xdwp_Settings::all();
		$groups   = Xdwp_Coins::grouped();

		settings_errors( 'xdwp' );

		include XDWP_PATH . 'includes/admin/views/settings-page.php';
	}
}
