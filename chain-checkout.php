<?php
/**
 * Plugin Name:       Xorro Direct Wallet Payments for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/xorro-direct-wallet-payments-woocommerce
 * Description:       Accept cryptocurrency payments directly to your own wallets — no third-party payment processor. Supports BTC, BCH, ETH, USDT, USDC, DAI and 70+ coins/tokens with automatic on-chain verification.
 * Version:           1.4.6
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            xorro
 * Author URI:        https://github.com/x-o-r-r-o
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xorro-direct-wallet-payments-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 10.0
 * WC tested up to:   10.8
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

define( 'CHAIN_CHECKOUT_VERSION', '1.4.6' );
define( 'CHAIN_CHECKOUT_FILE', __FILE__ );
define( 'CHAIN_CHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHAIN_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'CHAIN_CHECKOUT_BASENAME', plugin_basename( __FILE__ ) );
define( 'CHAIN_CHECKOUT_GATEWAY_ID', 'chain_checkout' );
define( 'CHAIN_CHECKOUT_MIN_WP', '6.9' );
define( 'CHAIN_CHECKOUT_MIN_WC', '10.0' );
define( 'CHAIN_CHECKOUT_MIN_PHP', '7.4' );

/**
 * Declare WooCommerce feature compatibility early (HPOS + Checkout Blocks).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CHAIN_CHECKOUT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CHAIN_CHECKOUT_FILE, true );
		}
	}
);

/**
 * Activation: schedule cron, set defaults.
 */
register_activation_hook(
	__FILE__,
	static function () {
		if ( version_compare( PHP_VERSION, CHAIN_CHECKOUT_MIN_PHP, '<' ) ) {
			deactivate_plugins( CHAIN_CHECKOUT_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required PHP 2: current PHP */
						__( 'Xorro Wallet Payments requires PHP %1$s or higher. You are running %2$s.', 'xorro-direct-wallet-payments-woocommerce' ),
						CHAIN_CHECKOUT_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}

		require_once CHAIN_CHECKOUT_PATH . 'includes/class-chain-checkout-install.php';
		Chain_Checkout_Install::activate();
	}
);

/**
 * Deactivation: clear scheduled events.
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once CHAIN_CHECKOUT_PATH . 'includes/class-chain-checkout-install.php';
		Chain_Checkout_Install::deactivate();
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
		if ( isset( $wp_version ) && version_compare( $wp_version, CHAIN_CHECKOUT_MIN_WP, '<' ) ) {
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
							CHAIN_CHECKOUT_MIN_WP
						)
					);
					echo '</p></div>';
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, CHAIN_CHECKOUT_MIN_WC, '<' ) ) {
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
							CHAIN_CHECKOUT_MIN_WC
						)
					);
					echo '</p></div>';
				}
			);
			return;
		}

		require_once CHAIN_CHECKOUT_PATH . 'includes/class-chain-checkout-install.php';
		Chain_Checkout_Install::maybe_upgrade();

		require_once CHAIN_CHECKOUT_PATH . 'includes/class-chain-checkout.php';
		Chain_Checkout::instance();
	},
	20
);
