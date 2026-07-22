<?php
/**
 * Plugin Name:       Xorro Direct Wallet Payments for WooCommerce
 * Plugin URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce
 * Description:       Accept cryptocurrency payments directly to your own wallets — no third-party payment processor. Supports BTC, BCH, ETH, USDT, USDC, DAI and 70+ coins/tokens with automatic on-chain verification.
 * Version:           1.5.16
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            xorro
 * Author URI:        https://github.com/x-o-r-r-o
 * Update URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xorro-direct-wallet-payments-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 10.0
 * WC tested up to:   10.8
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

define( 'XDWP_VERSION', '1.5.16' );
define( 'XDWP_FILE', __FILE__ );
define( 'XDWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'XDWP_URL', plugin_dir_url( __FILE__ ) );
define( 'XDWP_BASENAME', plugin_basename( __FILE__ ) );
define( 'XDWP_GATEWAY_ID', 'xdwp' );
define( 'XDWP_MIN_WP', '6.9' );
define( 'XDWP_MIN_WC', '10.0' );
define( 'XDWP_MIN_PHP', '7.4' );

// GitHub Releases updater (runs even if WooCommerce is temporarily inactive).
require_once XDWP_PATH . 'includes/class-xdwp-updater.php';
Xdwp_Updater::init();

/**
 * Declare WooCommerce feature compatibility early (HPOS + Checkout Blocks).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', XDWP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', XDWP_FILE, true );
		}
	}
);

/**
 * Activation: schedule cron, set defaults.
 */
register_activation_hook(
	__FILE__,
	static function () {
		if ( version_compare( PHP_VERSION, XDWP_MIN_PHP, '<' ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( XDWP_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required PHP 2: current PHP */
						__( 'Xorro Wallet Payments requires PHP %1$s or higher. You are running %2$s.', 'xorro-direct-wallet-payments-woocommerce' ),
						XDWP_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}

		require_once XDWP_PATH . 'includes/class-xdwp-install.php';
		Xdwp_Install::activate();
	}
);

/**
 * Deactivation: clear scheduled events.
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once XDWP_PATH . 'includes/class-xdwp-install.php';
		Xdwp_Install::deactivate();
	}
);

/**
 * Bootstrap after plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Xorro Wallet Payments requires WooCommerce to be installed and active.', 'xorro-direct-wallet-payments-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, XDWP_MIN_WP, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html(
						sprintf(
							/* translators: %s: WordPress version */
							__( 'Xorro Wallet Payments requires WordPress %s or higher (WordPress 7.0 recommended).', 'xorro-direct-wallet-payments-woocommerce' ),
							XDWP_MIN_WP
						)
					);
					echo '</p></div>';
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, XDWP_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html(
						sprintf(
							/* translators: %s: WooCommerce version */
							__( 'Xorro Wallet Payments requires WooCommerce %s or higher.', 'xorro-direct-wallet-payments-woocommerce' ),
							XDWP_MIN_WC
						)
					);
					echo '</p></div>';
				}
			);
			return;
		}

		require_once XDWP_PATH . 'includes/class-xdwp-install.php';
		Xdwp_Install::maybe_upgrade();

		require_once XDWP_PATH . 'includes/class-xdwp.php';
		Xdwp::instance();
	},
	20
);
