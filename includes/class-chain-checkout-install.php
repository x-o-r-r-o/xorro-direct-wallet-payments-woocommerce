<?php
/**
 * Plugin installer / activator.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Install
 */
class Chain_Checkout_Install {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::maybe_upgrade();

		// Register custom schedule before scheduling (activation runs before normal init hooks).
		add_filter(
			'cron_schedules',
			static function ( $schedules ) {
				$schedules['chain_checkout_every_minute'] = array(
					'interval' => 60,
					'display'  => __( 'Every Minute (Chain Checkout)', 'chain-checkout' ),
				);
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( 'chain_checkout_check_payments' ) ) {
			wp_schedule_event( time() + 60, 'chain_checkout_every_minute', 'chain_checkout_check_payments' );
		}

		if ( ! wp_next_scheduled( 'chain_checkout_refresh_prices' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'chain_checkout_refresh_prices' );
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'chain_checkout_check_payments' );
		wp_clear_scheduled_hook( 'chain_checkout_refresh_prices' );
	}

	/**
	 * Ensure defaults exist and migrate older settings.
	 */
	public static function maybe_upgrade() {
		$defaults = array(
			'payment_window'         => 60,
			'order_status'           => 'processing',
			'underpayment_percent'   => 1,
			'min_confirmations'      => 1,
			'expiry_grace_minutes'   => 30,
			'unique_amounts'         => 'yes',
			'wallet_rotation'        => 'yes',
			'auto_verify'            => 'yes',
			'coingecko_api_key'      => '',
			'etherscan_api_key'      => '',
			'trongrid_api_key'       => '',
			'helius_api_key'         => '',
			'subscan_api_key'        => '',
			'viewblock_api_key'      => '',
			// Legacy keys retained for migration / fallback only.
			'bscscan_api_key'        => '',
			'polygonscan_api_key'    => '',
			'arbiscan_api_key'       => '',
			'optimistic_api_key'     => '',
			'snowtrace_api_key'      => '',
			'enabled_coins'          => array( 'BTC', 'ETH' ),
			'wallets'                => array(),
			'price_coin_show'        => 'no',
			'price_coin_ticker'      => 'BTC',
			'title'                  => __( 'Pay with Cryptocurrency', 'chain-checkout' ),
			'description'            => __( 'Pay directly to our wallet with cryptocurrency. No third-party processor.', 'chain-checkout' ),
			'checkout_display'       => 'both',
			'checkout_icon_id'       => 0,
			'checkout_icon_width'    => 32,
			'checkout_icon_height'   => 32,
		);

		$existing = get_option( 'chain_checkout_settings', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged = wp_parse_args( $existing, $defaults );

		// Migrate first available legacy explorer key into Etherscan V2 key.
		if ( empty( $merged['etherscan_api_key'] ) ) {
			foreach ( array( 'bscscan_api_key', 'polygonscan_api_key', 'arbiscan_api_key', 'optimistic_api_key', 'snowtrace_api_key' ) as $legacy ) {
				if ( ! empty( $merged[ $legacy ] ) ) {
					$merged['etherscan_api_key'] = $merged[ $legacy ];
					break;
				}
			}
		}

		update_option( 'chain_checkout_settings', $merged );
		update_option( 'chain_checkout_version', CHAIN_CHECKOUT_VERSION );
	}
}
