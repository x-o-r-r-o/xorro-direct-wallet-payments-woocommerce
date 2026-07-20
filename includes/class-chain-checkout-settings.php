<?php
/**
 * Plugin settings helper.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Settings
 */
class Chain_Checkout_Settings {

	const OPTION_KEY = 'chain_checkout_settings';

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$settings = get_option( self::OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings (merge).
	 *
	 * @param array<string, mixed> $data Data to merge.
	 * @return bool
	 */
	public static function update( array $data ) {
		$settings = self::all();
		$settings = array_merge( $settings, $data );
		return update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Sanitize and save full settings payload from admin.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ) {
		$clean = self::all();

		if ( isset( $input['payment_window'] ) ) {
			$clean['payment_window'] = max( 5, min( 1440, absint( $input['payment_window'] ) ) );
		}

		if ( isset( $input['order_status'] ) ) {
			$status = sanitize_key( $input['order_status'] );
			$clean['order_status'] = in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) ? $status : 'processing';
		}

		if ( isset( $input['underpayment_percent'] ) ) {
			$clean['underpayment_percent'] = max( 0, min( 10, (float) $input['underpayment_percent'] ) );
		}

		if ( isset( $input['min_confirmations'] ) ) {
			$clean['min_confirmations'] = max( 0, min( 64, absint( $input['min_confirmations'] ) ) );
		}

		if ( isset( $input['expiry_grace_minutes'] ) ) {
			$clean['expiry_grace_minutes'] = max( 0, min( 1440, absint( $input['expiry_grace_minutes'] ) ) );
		}

		foreach ( array( 'unique_amounts', 'wallet_rotation', 'auto_verify', 'price_coin_show' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$clean[ $flag ] = ( 'yes' === $input[ $flag ] || 1 === (int) $input[ $flag ] || true === $input[ $flag ] ) ? 'yes' : 'no';
			}
		}

		foreach ( array(
			'coingecko_api_key',
			'etherscan_api_key',
			'trongrid_api_key',
			'helius_api_key',
			'subscan_api_key',
			'viewblock_api_key',
			'bscscan_api_key',
			'polygonscan_api_key',
			'arbiscan_api_key',
			'optimistic_api_key',
			'snowtrace_api_key',
		) as $text_key ) {
			if ( isset( $input[ $text_key ] ) ) {
				$clean[ $text_key ] = sanitize_text_field( wp_unslash( $input[ $text_key ] ) );
			}
		}

		if ( isset( $input['description'] ) ) {
			$clean['description'] = sanitize_textarea_field( wp_unslash( $input['description'] ) );
		}

		$clean = Chain_Checkout_Branding::sanitize_from_input( $input, $clean );

		if ( isset( $input['price_coin_ticker'] ) ) {
			$ticker = sanitize_text_field( $input['price_coin_ticker'] );
			$clean['price_coin_ticker'] = Chain_Checkout_Coins::get( $ticker ) ? $ticker : 'BTC';
		}

		if ( isset( $input['enabled_coins'] ) && is_array( $input['enabled_coins'] ) ) {
			$valid = array_keys( Chain_Checkout_Coins::all() );
			$clean['enabled_coins'] = array_values(
				array_intersect(
					array_map( 'sanitize_text_field', $input['enabled_coins'] ),
					$valid
				)
			);
		}

		if ( isset( $input['wallets'] ) && is_array( $input['wallets'] ) ) {
			$submitted = Chain_Checkout_Wallets::sanitize_wallets( $input['wallets'] );
			$existing  = isset( $clean['wallets'] ) && is_array( $clean['wallets'] ) ? $clean['wallets'] : array();
			// Merge so saving a partial wallets form does not wipe addresses for coins not shown.
			$clean['wallets'] = array_merge( $existing, $submitted );
			// Explicit empty submission for a coin clears that coin only when key present with empty list.
			foreach ( $input['wallets'] as $coin_id => $raw ) {
				$coin_id = sanitize_text_field( $coin_id );
				if ( ! isset( $submitted[ $coin_id ] ) ) {
					// Posted but sanitized to empty → remove.
					$is_empty_string = is_string( $raw ) && '' === trim( $raw );
					$is_empty_array  = is_array( $raw ) && 0 === count( array_filter( array_map( 'trim', $raw ) ) );
					if ( $is_empty_string || $is_empty_array ) {
						unset( $clean['wallets'][ $coin_id ] );
					}
				}
			}
		}

		return $clean;
	}
}
