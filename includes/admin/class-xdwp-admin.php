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
		add_action( 'admin_notices', array( __CLASS__, 'api_key_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'confirmations_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'plugin_action_links_' . XDWP_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Enqueue admin shell CSS/JS on plugin screens (and print inline CSS backup).
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! self::is_plugin_screen() && false === strpos( (string) $hook, 'xorro-direct-wallet-payments-woocommerce' ) ) {
			return;
		}
		self::enqueue_shell_assets();
	}

	/**
	 * Register + enqueue the Cryptoniq-style admin shell assets.
	 *
	 * Uses wp_enqueue + wp_add_inline_style so the shell never renders unstyled
	 * if the external stylesheet request fails (wordpress.org compliant).
	 */
	public static function enqueue_shell_assets() {
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

		// Inline backup so the admin shell cannot disappear if the CSS URL 404s/blocked.
		if ( is_readable( $css ) && ! wp_style_is( 'xdwp-admin', 'done' ) ) {
			static $inlined = false;
			if ( ! $inlined ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin CSS.
				$css_body = file_get_contents( $css );
				if ( is_string( $css_body ) && '' !== $css_body ) {
					wp_add_inline_style( 'xdwp-admin', $css_body );
					$inlined = true;
				}
			}
		}

		if ( ! wp_script_is( 'xdwp-admin', 'enqueued' ) ) {
			$admin_js  = XDWP_PATH . 'assets/js/admin.js';
			$admin_ver = XDWP_VERSION;
			if ( is_readable( $admin_js ) ) {
				$admin_ver = XDWP_VERSION . '.' . (string) filemtime( $admin_js );
			}
			wp_enqueue_script(
				'xdwp-admin',
				XDWP_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				$admin_ver,
				true
			);
		}

		$wallets_js  = XDWP_PATH . 'assets/js/wallets.js';
		$wallets_ver = XDWP_VERSION;
		if ( is_readable( $wallets_js ) ) {
			$wallets_ver = XDWP_VERSION . '.' . (string) filemtime( $wallets_js );
		}
		if ( ! wp_script_is( 'xdwp-wallets', 'enqueued' ) ) {
			wp_enqueue_script(
				'xdwp-wallets',
				XDWP_URL . 'assets/js/wallets.js',
				array(),
				$wallets_ver,
				true
			);
		}

		$admin_i18n = array(
			'placeholder'         => __( 'Paste wallet address', 'xorro-direct-wallet-payments-woocommerce' ),
			'copy'                => __( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ),
			'copied'              => __( 'Copied', 'xorro-direct-wallet-payments-woocommerce' ),
			'remove'              => __( 'Remove address', 'xorro-direct-wallet-payments-woocommerce' ),
			'invalidFormat'       => __( 'Invalid format', 'xorro-direct-wallet-payments-woocommerce' ),
			'duplicate'           => __( 'Duplicate address', 'xorro-direct-wallet-payments-woocommerce' ),
			/* translators: %d: coins still missing a wallet */
			'missing'             => __( '%d coin(s) still need an address', 'xorro-direct-wallet-payments-woocommerce' ),
			/* translators: %d: number of wallet addresses */
			'addressesConfigured' => __( '%d addresses', 'xorro-direct-wallet-payments-woocommerce' ),
			/* translators: %d: coins still missing a wallet */
			'missingWallets'      => __( '%d coin(s) still need an address', 'xorro-direct-wallet-payments-woocommerce' ),
			'defaultIcon'         => class_exists( 'Xdwp_Branding' ) ? Xdwp_Branding::default_icon_url() : '',
			'mediaTitle'          => __( 'Select checkout icon', 'xorro-direct-wallet-payments-woocommerce' ),
			'mediaButton'         => __( 'Use this icon', 'xorro-direct-wallet-payments-woocommerce' ),
			'mediaUnavailable'    => __( 'Media library is not available.', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		wp_localize_script( 'xdwp-admin', 'xdwpAdmin', $admin_i18n );
		wp_localize_script( 'xdwp-wallets', 'xdwpAdmin', $admin_i18n );

		wp_enqueue_media();
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

		$rejected = class_exists( 'Xdwp_Wallets' ) ? Xdwp_Wallets::last_rejected_count() : 0;
		if ( 'wallets' === $tab && $rejected > 0 ) {
			add_settings_error(
				'xdwp',
				'xdwp_wallets_rejected',
				sprintf(
					/* translators: %d: number of invalid addresses */
					_n(
						'%d wallet address was rejected as invalid and was not saved.',
						'%d wallet addresses were rejected as invalid and were not saved.',
						$rejected,
						'xorro-direct-wallet-payments-woocommerce'
					),
					$rejected
				),
				'error'
			);
		}

		add_settings_error(
			'xdwp',
			'xdwp_saved',
			__( 'Settings saved.', 'xorro-direct-wallet-payments-woocommerce' ),
			'success'
		);
	}

	/**
	 * Warn when auto-verify needs an Etherscan key for enabled EVM coins.
	 */
	public static function api_key_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( 'yes' !== Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'xorro-direct-wallet-payments-woocommerce' ) ) {
			return;
		}
		$key = trim( (string) Xdwp_Settings::get( 'etherscan_api_key', '' ) );
		if ( '' !== $key ) {
			return;
		}
		$needs_etherscan = false;
		foreach ( Xdwp_Coins::get_payable() as $coin_id => $coin ) {
			$verifier = isset( $coin['verifier'] ) ? (string) $coin['verifier'] : '';
			if ( in_array( $verifier, array( 'eth', 'ethereum', 'arbitrum', 'optimism', 'base', 'bsc', 'bnb', 'matic', 'avax', 'ftm', 'cro', 'etc' ), true ) ) {
				$needs_etherscan = true;
				break;
			}
		}
		if ( ! $needs_etherscan ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: Prices & APIs settings URL */
				__( 'Automatic verification for ETH and other EVM coins requires an Etherscan API V2 key. Add one under %s.', 'xorro-direct-wallet-payments-woocommerce' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-prices' ) ) . '">' . esc_html__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
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
	 * Warn when min confirmations is 0 (accepts unconfirmed payments).
	 */
	public static function confirmations_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( 'yes' !== Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'xorro-direct-wallet-payments-woocommerce' ) ) {
			return;
		}
		$min = (int) Xdwp_Settings::get( 'min_confirmations', 1 );
		if ( 0 === $min ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Minimum confirmations is set to 0. Failed or unvalidated explorer rows are still rejected, but unconfirmed successful txs may mark orders paid. Raise this under General unless you intentionally accept zero-conf risk.', 'xorro-direct-wallet-payments-woocommerce' );
			echo '</p></div>';
			return;
		}
		if ( $min > 1 ) {
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'Minimum confirmations is above 1. Chains without tip-depth APIs (e.g. XRP, Stellar, NEAR, ATOM) fail closed and will not auto-verify until you lower this to 0 or 1, or mark paid manually.', 'xorro-direct-wallet-payments-woocommerce' );
			echo '</p></div>';
		}
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

		// Ensure shell assets exist even if admin_enqueue_scripts missed this screen.
		self::enqueue_shell_assets();

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
