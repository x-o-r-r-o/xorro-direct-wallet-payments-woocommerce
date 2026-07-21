<?php
/**
 * Plugin installer / activator.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Install
 */
class Xdwp_Install {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::maybe_upgrade();

		// Register custom schedule before scheduling (activation runs before normal init hooks).
		add_filter(
			'cron_schedules',
			static function ( $schedules ) {
				$schedules['xdwp_every_minute'] = array(
					'interval' => 60,
					'display'  => __( 'Every Minute (Xorro Wallet Payments)', 'xorro-direct-wallet-payments-woocommerce' ),
				);
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( 'xdwp_check_payments' ) ) {
			wp_schedule_event( time() + 60, 'xdwp_every_minute', 'xdwp_check_payments' );
		}

		if ( ! wp_next_scheduled( 'xdwp_refresh_prices' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'xdwp_refresh_prices' );
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'xdwp_check_payments' );
		wp_clear_scheduled_hook( 'xdwp_refresh_prices' );
	}

	/**
	 * Ensure defaults exist.
	 */
	public static function maybe_upgrade() {
		$defaults = array(
			'payment_window'       => 60,
			'order_status'         => 'processing',
			'underpayment_percent' => 1,
			'min_confirmations'    => 1,
			'expiry_grace_minutes' => 30,
			'unique_amounts'       => 'yes',
			'wallet_rotation'      => 'yes',
			'auto_verify'          => 'yes',
			'coingecko_api_key'    => '',
			'etherscan_api_key'    => '',
			'trongrid_api_key'     => '',
			'helius_api_key'       => '',
			'subscan_api_key'      => '',
			'viewblock_api_key'    => '',
			// Older explorer-key fields kept for settings fallback only.
			'bscscan_api_key'      => '',
			'polygonscan_api_key'  => '',
			'arbiscan_api_key'     => '',
			'optimistic_api_key'   => '',
			'snowtrace_api_key'    => '',
			'enabled_coins'        => array( 'BTC', 'ETH' ),
			'wallets'              => array(),
			'price_coin_show'      => 'no',
			'price_coin_ticker'    => 'BTC',
			'title'                => __( 'Pay with Cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' ),
			'description'          => __( 'Pay directly to our wallet with cryptocurrency. No third-party processor.', 'xorro-direct-wallet-payments-woocommerce' ),
			'checkout_display'     => 'both',
			'checkout_icon_id'     => 0,
			'checkout_icon_width'  => 32,
			'checkout_icon_height' => 32,
		);

		$existing = get_option( 'xdwp_settings', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged = wp_parse_args( $existing, $defaults );

		// Prefer first available older explorer key if Etherscan V2 key is empty.
		if ( empty( $merged['etherscan_api_key'] ) ) {
			foreach ( array( 'bscscan_api_key', 'polygonscan_api_key', 'arbiscan_api_key', 'optimistic_api_key', 'snowtrace_api_key' ) as $legacy ) {
				if ( ! empty( $merged[ $legacy ] ) ) {
					$merged['etherscan_api_key'] = $merged[ $legacy ];
					break;
				}
			}
		}

		update_option( 'xdwp_settings', $merged );
		update_option( 'xdwp_version', XDWP_VERSION );
	}
}
