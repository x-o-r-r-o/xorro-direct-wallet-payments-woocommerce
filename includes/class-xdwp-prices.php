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
	/** Unique dust spacing, in base units at min(decimals, 8) decimals. */
	const DUST_STEP = 10;
	/** Distinct dust values (DUST_STEP..DUST_STEP*DUST_SLOTS) before the cycle repeats. */
	const DUST_SLOTS = 100;
	/** Verifier match band half-width in the same units; must stay below DUST_STEP / 2. */
	const DUST_BAND = 4;
	/** Longest a pre-order checkout quote is honoured, in minutes. */
	const QUOTE_HOLD_MINUTES = 15;
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

		$rate = self::get_rate( $coin['coingecko_id'], $currency, ! $unique_amount );
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
			// Hold the pre-order quote briefly: the payment window starts again once the order
			// exists, so reusing the full window here let a customer sit on a quote for up to
			// twice the window and only order if the price moved in their favour.
			$window = min( self::QUOTE_HOLD_MINUTES, max( 5, (int) Xdwp_Settings::get( 'payment_window', 60 ) ) );
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
		// 10..1000 base units (at most 1000 sats ≈ a few cents to ~$1 on BTC). The old
		// 1000..499000 range could add ~0.005 BTC (hundreds of dollars) to a small order.
		// Bands (±DUST_BAND) never overlap across steps; assign_payment rejects collisions.
		$dust_units = self::DUST_STEP + ( ( $counter % self::DUST_SLOTS ) * self::DUST_STEP );
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
	 * @param bool   $allow_stale  Whether to serve STALE_TTL rates when live refresh fails.
	 * @return float
	 */
	public static function get_rate( $coingecko_id, $currency, $allow_stale = true ) {
		$currency = strtolower( $currency );
		$cache    = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		$key     = $coingecko_id . '_' . $currency;
		// Freshness is per rate: a global "last refresh" timestamp let a coin whose own
		// refresh failed keep serving an old rate as fresh whenever any other coin refreshed.
		$stamps  = get_transient( self::TRANSIENT_KEY . '_ts' );
		$updated = ( is_array( $stamps ) && isset( $stamps[ $key ] ) ) ? (int) $stamps[ $key ] : 0;
		$fresh   = $updated && ( time() - $updated ) < self::CACHE_TTL;

		if ( $fresh && isset( $cache[ $key ] ) && is_numeric( $cache[ $key ] ) ) {
			return (float) $cache[ $key ];
		}

		// Refresh every enabled coin in the same request (one call, up to 50 ids) so the next
		// coin a customer picks is already cached — one request per coin burned CoinGecko's
		// free rate limit after a handful of checkouts. Back off briefly after a failure
		// instead of re-hitting a rate-limited API on every quote.
		$fetched = array();
		if ( ! get_transient( self::TRANSIENT_KEY . '_backoff' ) ) {
			// Prefer rates returned by this refresh so a concurrent cache write cannot drop them.
			$fetched = self::refresh_rates( array_values( array_unique( array_merge( array( $coingecko_id ), self::enabled_coingecko_ids() ) ) ), $currency );
			if ( ! isset( $fetched[ $key ] ) ) {
				set_transient( self::TRANSIENT_KEY . '_backoff', 1, 30 );
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->warning(
						sprintf( 'Could not fetch a live %s/%s rate from CoinGecko (rate-limited or unreachable). Checkouts in this coin fail until it recovers; adding a CoinGecko API key under Prices & APIs raises the limit.', $coingecko_id, strtoupper( $currency ) ),
						array( 'source' => 'xorro-wallet-payments' )
					);
				}
			}
		}
		if ( isset( $fetched[ $key ] ) && is_numeric( $fetched[ $key ] ) && (float) $fetched[ $key ] > 0 ) {
			return (float) $fetched[ $key ];
		}

		$cache = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		// Stale rates are OK for display; order creation / checkout quotes must fail closed.
		if ( $allow_stale && isset( $cache[ $key ] ) && is_numeric( $cache[ $key ] ) && $updated && ( time() - $updated ) < self::STALE_TTL ) {
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
			$stamps = get_transient( self::TRANSIENT_KEY . '_ts' );
			$stamps = is_array( $stamps ) ? $stamps : array();
			$now    = time();
			foreach ( array_keys( $new_rates ) as $rate_key ) {
				$stamps[ $rate_key ] = $now;
			}
			set_transient( self::TRANSIENT_KEY . '_ts', $stamps, self::STALE_TTL );
			set_transient( self::TRANSIENT_KEY . '_updated', $now, DAY_IN_SECONDS );
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
		$ids = self::enabled_coingecko_ids();
		if ( ! empty( $ids ) ) {
			self::refresh_rates( $ids );
		}
	}

	/**
	 * CoinGecko ids for the coins the merchant has enabled.
	 *
	 * @return array<int, string>
	 */
	private static function enabled_coingecko_ids() {
		$ids     = array();
		$enabled = Xdwp_Settings::get( 'enabled_coins', array() );
		if ( ! is_array( $enabled ) ) {
			return $ids;
		}
		foreach ( $enabled as $coin_id ) {
			$coin = Xdwp_Coins::get( $coin_id );
			if ( $coin && ! empty( $coin['coingecko_id'] ) ) {
				$ids[] = $coin['coingecko_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
