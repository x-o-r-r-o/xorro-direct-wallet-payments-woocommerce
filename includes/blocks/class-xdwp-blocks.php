<?php
/**
 * WooCommerce Checkout Blocks payment method integration.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class Xdwp_Blocks
 */
final class Xdwp_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name matching gateway ID.
	 *
	 * @var string
	 */
	protected $name = XDWP_GATEWAY_ID;

	/**
	 * Gateway instance.
	 *
	 * @var Xdwp_Gateway|null
	 */
	private $gateway;

	/**
	 * Initialize.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . XDWP_GATEWAY_ID . '_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = isset( $gateways[ XDWP_GATEWAY_ID ] ) ? $gateways[ XDWP_GATEWAY_ID ] : null;
	}

	/**
	 * Active check.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Script handles.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$handle = 'xdwp-blocks';

		$css_ver = XDWP_VERSION;
		$js_ver  = XDWP_VERSION;
		$css     = XDWP_PATH . 'assets/css/frontend.css';
		$js      = XDWP_PATH . 'assets/js/blocks.js';
		if ( is_readable( $css ) ) {
			$css_ver = XDWP_VERSION . '.' . (string) filemtime( $css );
		}
		if ( is_readable( $js ) ) {
			$js_ver = XDWP_VERSION . '.' . (string) filemtime( $js );
		}

		wp_enqueue_style(
			'xdwp-frontend',
			XDWP_URL . 'assets/css/frontend.css',
			array(),
			$css_ver
		);

		$branding = Xdwp_Branding::frontend_data();
		wp_add_inline_style(
			'xdwp-frontend',
			sprintf(
				'.xdwp-blocks-label img.xdwp-gateway-icon{width:%1$dpx!important;height:%2$dpx!important;max-width:%1$dpx!important;max-height:%2$dpx!important;object-fit:contain;vertical-align:middle;}',
				(int) $branding['iconWidth'],
				(int) $branding['iconHeight']
			)
		);

		wp_register_script(
			$handle,
			XDWP_URL . 'assets/js/blocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			$js_ver,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'xorro-direct-wallet-payments-woocommerce', XDWP_PATH . 'languages' );
		}

		return array( $handle );
	}

	/**
	 * Data passed to frontend script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$coins = array();
		foreach ( Xdwp_Coins::get_payable() as $id => $coin ) {
			$icons   = Xdwp_Coins::icon_meta( $id );
			$coins[] = array(
				'id'     => $id,
				'symbol' => $coin['symbol'],
				'name'   => $coin['name'],
				'icon'   => $icons['icon'],
				'badge'  => $icons['badge'],
			);
		}

		return array_merge(
			array(
				'description' => $this->gateway ? $this->gateway->get_description() : '',
				'supports'    => $this->gateway ? array_filter( $this->gateway->supports ) : array( 'products' ),
				'coins'       => $coins,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'xdwp_checkout' ),
			),
			Xdwp_Branding::frontend_data()
		);
	}
}
