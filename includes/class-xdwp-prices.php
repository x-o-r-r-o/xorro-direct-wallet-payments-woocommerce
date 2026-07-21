<?php
/**
 * Fiat ↔ crypto price conversion via CoinGecko.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Prices
 */
class Xdwp_Prices {

	const TRANSIENT_KEY = 'xdwp_price_cache';
	/** Prefer fresh rates within this window. */
	const CACHE_TTL = 120;
	/** Keep cached rates available for stale fallback (must be >= CACHE_TTL). */
	const STALE_TTL = 600;
	/** WC session key for reserved checkout crypto amount. */
	const CHECKOUT_QUOTE_SESSION = 'xdwp_checkout_quote';

	/**
	 * Convert fiat order total to crypto amount.
	 *
	 * @param float  $fiat_amount   Fiat amount.
	 * @param string $coin_id       Coin ID.
	 * @param string $currency      Fiat currency code.
	 * @param bool   $unique_amount Whether to apply unique dust (order creation only).
	 * @return string Crypto amount string, or empty on failure.
	 */
	public static function fiat_to_crypto( $fiat_amount, $coin_id, $currency = '', $unique_amount = false ) {
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return '';
		}

		if ( '' === $currency ) {
			$currency = get_woocommerce_currency();
		}

		$rate = self::get_rate( $coin['coingecko_id'], $currency );
		if ( $rate <= 0 ) {
			return '';
		}

		$amount = (float) $fiat_amount / $rate;

		if ( $unique_amount && 'yes' === Xdwp_Settings::get( 'unique_amounts', 'yes' ) ) {
			$amount = self::apply_unique_dust( $amount, $coin_id );
		}

		return Xdwp_Coins::format_amount( $amount, $coin_id );
	}

	/**
	 * Fingerprint for a checkout quote reservation.
	 *
	 * @param float  $fiat_amount Fiat total.
	 * @param string $coin_id     Coin ID.
	 * @param string $currency    Fiat currency.
	 * @return string
	 */
	private static function checkout_quote_fingerprint( $fiat_amount, $coin_id, $currency ) {
		return md5(
			strtoupper( (string) $coin_id ) . '|' .
			strtoupper( (string) $currency ) . '|' .
			number_format( (float) $fiat_amount, 2, '.', '' )
		);
	}

	/**
	 * Reserve (or reuse) an exact checkout crypto amount in the WC session.
	 *
	 * Unique dust is allocated once per cart fingerprint so AJAX polls do not
	 * burn the global dust sequence, and place-order can reuse the same amount.
	 *
	 * @param float  $fiat_amount Fiat total.
	 * @param string $coin_id     Coin ID.
	 * @param string $currency    Fiat currency.
	 * @return string Crypto amount or empty.
	 */
	public static function checkout_quote( $fiat_amount, $coin_id, $currency = '' ) {
		if ( '' === $currency ) {
			$currency = get_woocommerce_currency();
		}
		$fiat_amount = (float) $fiat_amount;
		$fingerprint = self::checkout_quote_fingerprint( $fiat_amount, $coin_id, $currency );

		$session  = ( function_exists( 'WC' ) && WC()->session ) ? WC()->session : null;
		$existing = $session ? $session->get( self::CHECKOUT_QUOTE_SESSION ) : null;

		if (
			is_array( $existing )
			&& isset( $existing['fingerprint'], $existing['amount'], $existing['expires'] )
			&& (string) $existing['fingerprint'] === $fingerprint
			&& (int) $existing['expires'] > time()
			&& '' !== (string) $existing['amount']
			&& (float) $existing['amount'] > 0
		) {
			return (string) $existing['amount'];
		}

		$amount = self::fiat_to_crypto( $fiat_amount, $coin_id, $currency, true );
		if ( '' === $amount ) {
			return '';
		}

		if ( $session ) {
			$window = max( 5, (int) Xdwp_Settings::get( 'payment_window', 60 ) );
			$session->set(
				self::CHECKOUT_QUOTE_SESSION,
				array(
					'fingerprint' => $fingerprint,
					'coin_id'     => $coin_id,
					'currency'    => $currency,
					'fiat'        => $fiat_amount,
					'amount'      => $amount,
					'expires'     => time() + ( $window * MINUTE_IN_SECONDS ),
				)
			);
		}

		return $amount;
	}

	/**
	 * Consume a reserved checkout quote at order creation (or mint a fresh one).
	 *
	 * @param float  $fiat_amount Fiat total.
	 * @param string $coin_id     Coin ID.
	 * @param string $currency    Fiat currency.
	 * @return string Crypto amount or empty.
	 */
	public static function take_checkout_quote( $fiat_amount, $coin_id, $currency = '' ) {
		if ( '' === $currency ) {
			$currency = get_woocommerce_currency();
		}
		$fiat_amount = (float) $fiat_amount;
		$fingerprint = self::checkout_quote_fingerprint( $fiat_amount, $coin_id, $currency );

		$session  = ( function_exists( 'WC' ) && WC()->session ) ? WC()->session : null;
		$existing = $session ? $session->get( self::CHECKOUT_QUOTE_SESSION ) : null;
		$amount   = '';

		if (
			is_array( $existing )
			&& isset( $existing['fingerprint'], $existing['amount'], $existing['expires'] )
			&& (string) $existing['fingerprint'] === $fingerprint
			&& (int) $existing['expires'] > time()
			&& '' !== (string) $existing['amount']
			&& (float) $existing['amount'] > 0
		) {
			$amount = (string) $existing['amount'];
		} else {
			$amount = self::fiat_to_crypto( $fiat_amount, $coin_id, $currency, true );
		}

		if ( $session ) {
			$session->set( self::CHECKOUT_QUOTE_SESSION, null );
		}

		return $amount;
	}

	/**
	 * Add a tiny unique offset so reused addresses can be matched by amount.
	 *
	 * @param float  $amount  Base amount.
	 * @param string $coin_id Coin ID.
	 * @return float
	 */
	public static function apply_unique_dust( $amount, $coin_id ) {
		$coin     = Xdwp_Coins::get( $coin_id );
		$decimals = $coin ? min( (int) $coin['decimals'], 8 ) : 8;

		// Low-decimal assets cannot safely encode unique dust without large overcharge.
		if ( $decimals <= 4 ) {
			return $amount;
		}

		$counter = self::next_amount_seq();
		// Space dust by 1000 units so match bands (~±400 units) never overlap across concurrent orders.
		$step       = 1000;
		$slots      = 9; // 1000..9000 inclusive in steps of 1000.
		$dust_units = $step + ( ( $counter % $slots ) * $step );
		$dust       = $dust_units / pow( 10, $decimals );

		return $amount + $dust;
	}

	/**
	 * Atomic sequence for unique dust (avoids duplicate dust under concurrent checkout).
	 *
	 * @return int
	 */
	private static function next_amount_seq() {
		global $wpdb;

		$option = 'xdwp_amount_seq';
		// Ensure row exists.
		add_option( $option, 0, '', 'no' );

		// Atomic increment + return via LAST_INSERT_ID (connection-local).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID( ( CAST(option_value AS UNSIGNED) + 1 ) ) WHERE option_name = %s",
				$option
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $option, 'options' );

		return $value;
	}

	/**
	 * Get crypto price in fiat.
	 *
	 * @param string $coingecko_id CoinGecko ID.
	 * @param string $currency     Fiat currency.
	 * @return float
	 */
	public static function get_rate( $coingecko_id, $currency ) {
		$currency = strtolower( $currency );
		$cache    = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		$key     = $coingecko_id . '_' . $currency;
		$updated = (int) get_transient( self::TRANSIENT_KEY . '_updated' );
		$fresh   = $updated && ( time() - $updated ) < self::CACHE_TTL;

		if ( $fresh && isset( $cache[ $key ] ) && is_numeric( $cache[ $key ] ) ) {
			return (float) $cache[ $key ];
		}

		// Prefer rates returned by this refresh so a concurrent cache write cannot drop them.
		$fetched = self::refresh_rates( array( $coingecko_id ), $currency );
		if ( isset( $fetched[ $key ] ) && is_numeric( $fetched[ $key ] ) && (float) $fetched[ $key ] > 0 ) {
			return (float) $fetched[ $key ];
		}

		$cache = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		// Serve stale rates for up to STALE_TTL when a live refresh fails.
		if ( isset( $cache[ $key ] ) && is_numeric( $cache[ $key ] ) && $updated && ( time() - $updated ) < self::STALE_TTL ) {
			return (float) $cache[ $key ];
		}

		return 0.0;
	}

	/**
	 * Refresh rates for given CoinGecko IDs.
	 *
	 * @param array  $ids      CoinGecko IDs.
	 * @param string $currency Fiat currency.
	 * @return array Map of "{coingecko_id}_{currency}" => rate for rates fetched in this call.
	 */
	public static function refresh_rates( array $ids = array(), $currency = '' ) {
		if ( '' === $currency ) {
			$currency = get_woocommerce_currency();
		}
		$currency = strtolower( $currency );

		if ( empty( $ids ) ) {
			$ids = array();
			foreach ( Xdwp_Coins::all() as $coin ) {
				$ids[] = $coin['coingecko_id'];
			}
			$ids = array_values( array_unique( $ids ) );
		}

		// CoinGecko allows comma-separated ids; chunk to stay under URL limits.
		$chunks    = array_chunk( $ids, 50 );
		$new_rates = array();

		$api_key = Xdwp_Settings::get( 'coingecko_api_key', '' );
		// Free/Demo keys use api.coingecko.com + x-cg-demo-api-key.
		// Paid Pro keys use pro-api.coingecko.com + x-cg-pro-api-key.
		$is_pro = $api_key && self::coingecko_key_is_pro( $api_key );
		$base   = $is_pro
			? 'https://pro-api.coingecko.com/api/v3/simple/price'
			: 'https://api.coingecko.com/api/v3/simple/price';

		foreach ( $chunks as $chunk ) {
			$url = add_query_arg(
				array(
					'ids'           => implode( ',', $chunk ),
					'vs_currencies' => $currency,
				),
				$base
			);

			$args = array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			);

			if ( $api_key ) {
				if ( $is_pro ) {
					$args['headers']['x-cg-pro-api-key'] = $api_key;
				} else {
					$args['headers']['x-cg-demo-api-key'] = $api_key;
				}
			}

			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 !== (int) $code || ! is_array( $body ) ) {
				// If Pro endpoint rejected the key, retry once as Demo on the free host.
				if ( $api_key && $is_pro && in_array( (int) $code, array( 401, 403 ), true ) ) {
					$retry_url  = add_query_arg(
						array(
							'ids'           => implode( ',', $chunk ),
							'vs_currencies' => $currency,
						),
						'https://api.coingecko.com/api/v3/simple/price'
					);
					$retry_args = array(
						'timeout' => 15,
						'headers' => array(
							'Accept'            => 'application/json',
							'x-cg-demo-api-key' => $api_key,
						),
					);
					$response   = wp_remote_get( $retry_url, $retry_args );
					if ( is_wp_error( $response ) ) {
						continue;
					}
					$code = wp_remote_retrieve_response_code( $response );
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( 200 !== (int) $code || ! is_array( $body ) ) {
						continue;
					}
				} else {
					continue;
				}
			}

			foreach ( $body as $id => $prices ) {
				if ( isset( $prices[ $currency ] ) ) {
					$new_rates[ $id . '_' . $currency ] = (float) $prices[ $currency ];
				}
			}
		}

		if ( ! empty( $new_rates ) ) {
			// Re-read before write so concurrent coin quotes do not clobber each other.
			$latest = get_transient( self::TRANSIENT_KEY );
			if ( ! is_array( $latest ) ) {
				$latest = array();
			}
			$cache = array_merge( $latest, $new_rates );
			set_transient( self::TRANSIENT_KEY, $cache, self::STALE_TTL );
			set_transient( self::TRANSIENT_KEY . '_updated', time(), DAY_IN_SECONDS );
		}

		return $new_rates;
	}

	/**
	 * Heuristic: CoinGecko Pro keys are typically longer; Demo keys often start with CG-.
	 * Prefer Demo/free host unless the key clearly looks like a Pro subscription key.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	private static function coingecko_key_is_pro( $api_key ) {
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return false;
		}
		// Official Demo keys are prefixed CG- and work on the public API.
		if ( 0 === strpos( $api_key, 'CG-' ) ) {
			return false;
		}
		// Longer non-CG keys are treated as Pro; shorter/unknown stay on Demo host.
		return strlen( $api_key ) >= 32;
	}

	/**
	 * Cron callback to warm price cache for enabled coins.
	 */
	public static function cron_refresh() {
		$ids      = array();
		$enabled  = Xdwp_Settings::get( 'enabled_coins', array() );
		if ( ! is_array( $enabled ) ) {
			return;
		}
		foreach ( $enabled as $coin_id ) {
			$coin = Xdwp_Coins::get( $coin_id );
			if ( $coin ) {
				$ids[] = $coin['coingecko_id'];
			}
		}
		if ( ! empty( $ids ) ) {
			self::refresh_rates( array_unique( $ids ) );
		}
	}
}
