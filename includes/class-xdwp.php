<?php
/**
 * Main plugin bootstrap.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp
 */
final class Xdwp {

	/**
	 * Singleton instance.
	 *
	 * @var Xdwp|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Xdwp
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
		require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
		require_once XDWP_PATH . 'includes/class-xdwp-branding.php';
		require_once XDWP_PATH . 'includes/class-xdwp-privacy.php';
		require_once XDWP_PATH . 'includes/class-xdwp-prices.php';
		require_once XDWP_PATH . 'includes/class-xdwp-wallets.php';
		require_once XDWP_PATH . 'includes/class-xdwp-verifier.php';
		require_once XDWP_PATH . 'includes/class-xdwp-order.php';
		require_once XDWP_PATH . 'includes/class-xdwp-cron.php';
		require_once XDWP_PATH . 'includes/class-xdwp-ajax.php';
		require_once XDWP_PATH . 'includes/class-xdwp-gateway.php';

		if ( is_admin() ) {
			require_once XDWP_PATH . 'includes/admin/class-xdwp-admin.php';
		}
	}

	/**
	 * Register hooks.
	 */
	private function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_support' ) );

		Xdwp_Cron::init();
		Xdwp_Ajax::init();
		Xdwp_Order::init();
		Xdwp_Privacy::init();

		if ( is_admin() ) {
			Xdwp_Admin::init();
		}
	}

	/**
	 * Custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['xdwp_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute (Xorro Wallet Payments)', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		return $schedules;
	}

	/**
	 * Register payment gateway.
	 *
	 * @param array $gateways Gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'Xdwp_Gateway';
		return $gateways;
	}

	/**
	 * Register Checkout Blocks integration.
	 */
	public function register_blocks_support() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		require_once XDWP_PATH . 'includes/blocks/class-xdwp-blocks.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new Xdwp_Blocks() );
			}
		);
	}
}
