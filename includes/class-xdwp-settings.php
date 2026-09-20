<?php
/**
 * Plugin settings helper.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Settings
 */
class Xdwp_Settings {

	const OPTION_KEY = 'xdwp_settings';

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
	 * API keys prefer wp-config constants when defined (never stored in DB for that request path).
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$from_const = self::api_key_from_constant( $key );
		if ( null !== $from_const ) {
			return $from_const;
		}
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Map of settings keys to optional wp-config constant names.
	 *
	 * @return array<string, string>
	 */
	private static function api_key_constants() {
		return array(
			'coingecko_api_key' => 'XDWP_COINGECKO_API_KEY',
			'etherscan_api_key' => 'XDWP_ETHERSCAN_API_KEY',
			'trongrid_api_key'  => 'XDWP_TRONGRID_API_KEY',
			'helius_api_key'    => 'XDWP_HELIUS_API_KEY',
			'aptos_api_key'     => 'XDWP_APTOS_API_KEY',
			'blockchair_api_key' => 'XDWP_BLOCKCHAIR_API_KEY',
		);
	}

	/**
	 * Read API key from a defined constant, or null if unset.
	 *
	 * @param string $key Setting key.
	 * @return string|null
	 */
	private static function api_key_from_constant( $key ) {
		$map = self::api_key_constants();
		if ( ! isset( $map[ $key ] ) ) {
			return null;
		}
		$name = $map[ $key ];
		if ( ! defined( $name ) ) {
			return null;
		}
		$val = constant( $name );
		if ( ! is_string( $val ) || '' === $val ) {
			return null;
		}
		return $val;
	}

	/**
	 * Whether a submitted secret looks like a UI mask / empty (keep stored value).
	 *
	 * @param string $value Submitted value.
	 * @return bool
	 */
	public static function is_masked_secret( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return true;
		}
		// Masked display uses bullet prefix, e.g. ••••••••abcd.
		return 0 === strpos( $value, '••••' );
	}

	/**
	 * Placeholder hint for API key inputs (value attribute stays empty — secrets never echoed).
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function api_key_input_placeholder( $key ) {
		$map = self::api_key_constants();
		if ( isset( $map[ $key ] ) && defined( $map[ $key ] ) && is_string( constant( $map[ $key ] ) ) && '' !== constant( $map[ $key ] ) ) {
			return sprintf(
				/* translators: %s: PHP constant name */
				__( 'Set via %s in wp-config.php', 'xorro-direct-wallet-payments-woocommerce' ),
				$map[ $key ]
			);
		}
		$all = self::all();
		$raw = isset( $all[ $key ] ) ? (string) $all[ $key ] : '';
		if ( '' !== $raw ) {
			return __( 'Key saved — leave blank to keep, paste a new key, or enter - to clear', 'xorro-direct-wallet-payments-woocommerce' );
		}
		return '';
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

		// Risk-tiered confirmations: wait less on a small order, more on a large one. Both are
		// order totals in the shop's own currency; 0 turns that tier off.
		if ( isset( $input['risk_low_value'] ) ) {
			$clean['risk_low_value'] = max( 0, min( 1000000, (float) $input['risk_low_value'] ) );
		}
		if ( isset( $input['risk_high_value'] ) ) {
			$clean['risk_high_value'] = max( 0, min( 1000000, (float) $input['risk_high_value'] ) );
		}
		if ( isset( $input['risk_high_confirmations'] ) ) {
			$clean['risk_high_confirmations'] = max( 0, min( 64, absint( $input['risk_high_confirmations'] ) ) );
		}
		if ( isset( $input['risk_tiers'] ) ) {
			$clean['risk_tiers'] = ( 'yes' === $input['risk_tiers'] || 1 === (int) $input['risk_tiers'] || true === $input['risk_tiers'] ) ? 'yes' : 'no';
		}

		if ( isset( $input['expiry_grace_minutes'] ) ) {
			$clean['expiry_grace_minutes'] = max( 0, min( 1440, absint( $input['expiry_grace_minutes'] ) ) );
		}

		foreach ( array( 'unique_amounts', 'wallet_rotation', 'auto_verify', 'late_payment_scan', 'auto_partial_payments', 'stablecoin_peg', 'price_coin_show', 'recommended_confirmations' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$clean[ $flag ] = ( 'yes' === $input[ $flag ] || 1 === (int) $input[ $flag ] || true === $input[ $flag ] ) ? 'yes' : 'no';
			}
		}

		foreach ( array(
			'webhook_secret',
			'telegram_token',
			'blockchair_api_key',
			'coingecko_api_key',
			'etherscan_api_key',
			'trongrid_api_key',
			'helius_api_key',
			'aptos_api_key',
		) as $text_key ) {
			if ( ! isset( $input[ $text_key ] ) ) {
				continue;
			}
			$submitted = sanitize_text_field( wp_unslash( $input[ $text_key ] ) );
			// Single hyphen clears a stored key; empty / mask = keep existing.
			if ( '-' === $submitted ) {
				$clean[ $text_key ] = '';
				continue;
			}
			if ( self::is_masked_secret( $submitted ) ) {
				continue;
			}
			$clean[ $text_key ] = $submitted;
		}

		if ( isset( $input['webhook_url'] ) ) {
			$url = trim( sanitize_text_field( wp_unslash( $input['webhook_url'] ) ) );
			// Only somewhere this site can actually POST to. An unparseable or non-HTTP address
			// would fail on every event for ever without ever saying why.
			if ( '' === $url ) {
				$clean['webhook_url'] = '';
			} else {
				$valid = wp_http_validate_url( $url );
				if ( $valid ) {
					$clean['webhook_url'] = esc_url_raw( $valid );
				} else {
					add_settings_error(
						'xdwp',
						'xdwp_webhook',
						__( 'That webhook address was not saved: it must be a full http:// or https:// address this site is allowed to reach.', 'xorro-direct-wallet-payments-woocommerce' ),
						'error'
					);
				}
			}
		}

		if ( isset( $input['telegram_chat'] ) ) {
			$clean['telegram_chat'] = sanitize_text_field( wp_unslash( $input['telegram_chat'] ) );
		}

		if ( isset( $input['notify_events'] ) && is_array( $input['notify_events'] ) ) {
			$known                  = array_keys( Xdwp_Notify::events() );
			$clean['notify_events'] = array_values( array_intersect( array_map( 'sanitize_key', $input['notify_events'] ), $known ) );
		} elseif ( isset( $input['notify_events_present'] ) ) {
			// The form was shown and every box was cleared, which is not the same as never asked.
			$clean['notify_events'] = array();
		}

		if ( isset( $input['digest_daily'] ) ) {
			$clean['digest_daily'] = ( 'yes' === $input['digest_daily'] || 1 === (int) $input['digest_daily'] || true === $input['digest_daily'] ) ? 'yes' : 'no';
		}

		if ( isset( $input['description'] ) ) {
			$clean['description'] = sanitize_textarea_field( wp_unslash( $input['description'] ) );
		}

		$clean = Xdwp_Branding::sanitize_from_input( $input, $clean );

		if ( isset( $input['price_coin_ticker'] ) ) {
			$ticker = sanitize_text_field( $input['price_coin_ticker'] );
			$clean['price_coin_ticker'] = Xdwp_Coins::get( $ticker ) ? $ticker : 'BTC';
		}

		if ( isset( $input['enabled_coins'] ) && is_array( $input['enabled_coins'] ) ) {
			$valid = array_keys( Xdwp_Coins::all() );
			$clean['enabled_coins'] = array_values(
				array_intersect(
					array_map( 'sanitize_text_field', $input['enabled_coins'] ),
					$valid
				)
			);
		}

		if ( isset( $input['coin_limits'] ) && is_array( $input['coin_limits'] ) ) {
			$valid_coins = array_keys( Xdwp_Coins::all() );
			$limits      = array();
			foreach ( $input['coin_limits'] as $coin_id => $row ) {
				$coin_id = sanitize_text_field( $coin_id );
				if ( ! in_array( $coin_id, $valid_coins, true ) || ! is_array( $row ) ) {
					continue;
				}
				$min = isset( $row['min'] ) ? max( 0, (float) $row['min'] ) : 0;
				$max = isset( $row['max'] ) ? max( 0, (float) $row['max'] ) : 0;
				// A max below the min would hide the coin everywhere; treat it as "no maximum".
				if ( $max > 0 && $min > 0 && $max < $min ) {
					$max = 0;
				}
				if ( $min > 0 || $max > 0 ) {
					$limits[ $coin_id ] = array(
						'min' => $min,
						'max' => $max,
					);
				}
			}
			$clean['coin_limits'] = $limits;
		}

		if ( isset( $input['coin_adjustments'] ) && is_array( $input['coin_adjustments'] ) ) {
			$valid_coins = array_keys( Xdwp_Coins::all() );
			$adjustments = array();
			foreach ( $input['coin_adjustments'] as $coin_id => $value ) {
				$coin_id = sanitize_text_field( $coin_id );
				if ( ! in_array( $coin_id, $valid_coins, true ) || '' === trim( (string) $value ) ) {
					continue;
				}
				// A discount deeper than half the order, or a surcharge larger than it, is far
				// more likely to be a typo than an intention.
				$percent = round( max( -50, min( 50, (float) $value ) ), 2 );
				if ( abs( $percent ) >= 0.01 ) {
					$adjustments[ $coin_id ] = $percent;
				}
			}
			$clean['coin_adjustments'] = $adjustments;
		}

		if ( isset( $input['coin_confirmations'] ) && is_array( $input['coin_confirmations'] ) ) {
			$valid_coins   = array_keys( Xdwp_Coins::all() );
			$confirmations = array();
			foreach ( $input['coin_confirmations'] as $coin_id => $value ) {
				$coin_id = sanitize_text_field( $coin_id );
				if ( ! in_array( $coin_id, $valid_coins, true ) ) {
					continue;
				}
				$value = max( 0, min( 64, (int) $value ) );
				// Asking for depth on a chain that reports none would refuse every payment in
				// that coin instead of making anything safer, so it is refused here with an
				// explanation rather than saved and silently breaking verification.
				if ( $value > 1 && ! Xdwp_Coins::reports_depth( $coin_id ) ) {
					add_settings_error(
						'xdwp',
						'xdwp_confirmations',
						sprintf(
							/* translators: %s: coin ID */
							__( '%s settles a payment the moment it is validated, so it cannot wait for more confirmations. That number was not saved — leave it at 1 or empty.', 'xorro-direct-wallet-payments-woocommerce' ),
							$coin_id
						),
						'error'
					);
					continue;
				}
				// 0 means "no number of my own" — fall back to the chain's recommendation.
				if ( $value > 0 ) {
					$confirmations[ $coin_id ] = $value;
				}
			}
			$clean['coin_confirmations'] = $confirmations;
		}

		if ( isset( $input['xpubs'] ) && is_array( $input['xpubs'] ) ) {
			$keys     = array();
			$existing = isset( $clean['xpubs'] ) && is_array( $clean['xpubs'] ) ? $clean['xpubs'] : array();
			foreach ( $input['xpubs'] as $coin_id => $key ) {
				$coin_id = sanitize_text_field( $coin_id );
				$key     = trim( sanitize_text_field( is_string( $key ) ? $key : '' ) );
				if ( '' === $key ) {
					continue; // Cleared.
				}
				// Anything that is not a usable *public* key for this coin is dropped, with a
				// message — silently keeping a typo would send customers to nowhere.
				if ( ! Xdwp_Hd::is_valid( $key, $coin_id ) ) {
					add_settings_error(
						'xdwp',
						'xdwp_xpub',
						sprintf(
							/* translators: %s: coin ID */
							__( 'That extended public key for %s was not saved: it is not a public account key this plugin can use (xpub, ypub, zpub, Ltub or dgub). Never paste a private key.', 'xorro-direct-wallet-payments-woocommerce' ),
							$coin_id
						),
						'error'
					);
					if ( isset( $existing[ $coin_id ] ) ) {
						$keys[ $coin_id ] = $existing[ $coin_id ];
					}
					continue;
				}
				$keys[ $coin_id ] = $key;
			}
			$clean['xpubs'] = $keys;
		}

		if ( isset( $input['wallets'] ) && is_array( $input['wallets'] ) ) {
			$submitted = Xdwp_Wallets::sanitize_wallets( $input['wallets'] );
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
