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
	/** Most dust slots searched (DUST_STEP..DUST_STEP*DUST_SLOTS). The lowest free one is used. */
	const DUST_SLOTS = 1000;
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
	/**
	 * The percentage this shop adds to, or takes off, an order paid in this coin.
	 *
	 * A shop may want to pass on what a chain costs it, or to nudge customers towards the coin
	 * it would rather hold. Negative is a discount, positive a surcharge.
	 *
	 * @param string $coin_id Coin ID.
	 * @return float Percentage, 0 when the shop has set none.
	 */
	public static function coin_adjustment( $coin_id ) {
		$all = Xdwp_Settings::get( 'coin_adjustments', array() );
		if ( ! is_array( $all ) || ! isset( $all[ $coin_id ] ) ) {
			return 0.0;
		}
		/**
		 * The discount or surcharge for paying in this coin.
		 *
		 * @param float  $percent Percentage from the settings.
		 * @param string $coin_id Coin ID.
		 */
		$percent = (float) apply_filters( 'xdwp_coin_adjustment', (float) $all[ $coin_id ], $coin_id );
		return max( -50.0, min( 50.0, $percent ) );
	}

	/**
	 * An order total with this coin's discount or surcharge applied.
	 *
	 * @param float  $fiat    Order total in shop currency.
	 * @param string $coin_id Coin ID.
	 * @return float
	 */
	public static function adjusted_fiat( $fiat, $coin_id ) {
		$percent = self::coin_adjustment( $coin_id );
		if ( 0.0 === $percent ) {
			return (float) $fiat;
		}
		return max( 0.0, round( (float) $fiat * ( 1 + ( $percent / 100 ) ), wc_get_price_decimals() ) );
	}

	public static function fiat_to_crypto( $fiat_amount, $coin_id, $currency = '', $unique_amount = false ) {
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return '';
		}

		if ( '' === $currency ) {
			$currency = get_woocommerce_currency();
		}

		// Stablecoins tracking the store currency are quoted 1:1 when the merchant asks for it,
		// so a $17.34 order is exactly 17.34 USDT rather than 17.3465 at the market rate.
		$rate = Xdwp_Rates::pegged_rate( $coin['symbol'], $currency );
		if ( $rate <= 0 ) {
			$rate = self::get_rate( $coin['coingecko_id'], $currency, ! $unique_amount, $coin['symbol'] );
		}
		if ( $rate <= 0 ) {
			return '';
		}

		// The coin's discount or surcharge is applied here and nowhere else, so a quote, a
		// re-quote and the fee line written onto the order can never disagree.
		$amount = self::adjusted_fiat( $fiat_amount, $coin_id ) / $rate;

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
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return $amount;
		}

		// Space orders at the finest amount a customer can actually send. Spacing them any
		// finer produces quotes an exchange withdrawal screen will not accept, and the
		// payment that does arrive then matches nothing.
		$decimals = Xdwp_Coins::payable_decimals( $coin );

		// Orders are spaced one unit apart at that precision — a cent on a stablecoin, a
		// satoshi on Bitcoin. Note who pays for this: the amount goes *up*, so the surcharge
		// lands on the customer. One step must therefore be small enough not to be noticed.
		if ( $decimals < 2 || self::step_fiat_value( $coin, $decimals ) > 0.05 ) {
			return $amount;
		}

		// And the surcharge must stay small however busy the shop is. Each concurrent order
		// on the same address takes the next step up, so the slots are bounded by what the
		// customer could reasonably be asked to pay over the odds: one per cent of the order,
		// and never less than two steps.
		$allowance = max( 2, (int) floor( ( (float) $amount * 0.01 ) / pow( 10, -$decimals ) ) );
		$max_slots = (int) min( self::DUST_SLOTS, $allowance );

		// Use the smallest dust slot not already reserved by an open/recent order for this coin,
		// so the extra charge stays at a few base units (10 sats on BTC) and only grows with real
		// concurrency. A fixed rotating cycle either overcharged (the old 1000..499000 units — up
		// to ~0.005 BTC) or, if kept small, ran out of free slots once enough orders were open.
		$unit     = pow( 10, -$decimals );
		$occupied = Xdwp_Verifier::occupied_amounts( $coin_id );
		if ( is_array( $occupied ) ) {
			for ( $slot = 1; $slot <= $max_slots; $slot++ ) {
				$candidate = Xdwp_Coins::format_amount( $amount + ( $slot * $unit ), $coin_id );
				$free      = ! Xdwp_Verifier::amount_slot_taken( $coin_id, $candidate );
				foreach ( $occupied as $taken ) {
					if ( Xdwp_Verifier::amounts_overlap( $candidate, $taken, $coin ) ) {
						$free = false;
						break;
					}
				}
				if ( $free ) {
					return (float) $candidate;
				}
			}
		}

		// Fallback (peer lookup failed / every slot taken): rotate so concurrent orders still
		// differ; assign_payment() rejects and retries any collision.
		$counter    = self::next_amount_seq();
		$dust_units = 1 + ( $counter % $max_slots );
		return $amount + ( $dust_units * $unit );
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
	/**
	 * Roughly what one unique-amount step costs the merchant, in store currency.
	 *
	 * Uniqueness is bought by giving a few base units away. That is nothing on Bitcoin and a
	 * cent on a stablecoin, but on a coin quoted in whole numbers it could be real money — so
	 * the cost is worked out rather than assumed.
	 *
	 * @param array $coin     Coin definition.
	 * @param int   $decimals Decimals the spacing uses.
	 * @return float Store-currency value of one step; 0 when no rate is known.
	 */
	private static function step_fiat_value( array $coin, $decimals ) {
		// One step is one unit at the payable precision.
		$currency = get_woocommerce_currency();
		$per_coin = Xdwp_Rates::pegged_rate( $coin['symbol'], $currency );
		if ( $per_coin <= 0 ) {
			$per_coin = (float) self::get_rate( $coin['coingecko_id'], strtolower( $currency ), true, $coin['symbol'] );
		}
		if ( $per_coin <= 0 ) {
			return 0.0;
		}
		return pow( 10, -$decimals ) * $per_coin;
	}

	public static function get_rate( $coingecko_id, $currency, $allow_stale = true, $symbol = '' ) {
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

		// CoinGecko did not answer: try the backup sources (exchange tickers) before falling
		// back to a stale cached rate.
		if ( '' !== (string) $symbol ) {
			$fallback = Xdwp_Rates::fallback_rate( $symbol, $currency );
			if ( $fallback > 0 ) {
				self::store_rate( $key, $fallback );
				return $fallback;
			}
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
	 * Save one rate (and its timestamp) into the shared cache.
	 *
	 * @param string $key  "{coingecko_id}_{currency}".
	 * @param float  $rate Rate.
	 */
	private static function store_rate( $key, $rate ) {
		$cache = get_transient( self::TRANSIENT_KEY );
		$cache = is_array( $cache ) ? $cache : array();
		$cache[ $key ] = (float) $rate;
		set_transient( self::TRANSIENT_KEY, $cache, self::STALE_TTL );

		$stamps = get_transient( self::TRANSIENT_KEY . '_ts' );
		$stamps = is_array( $stamps ) ? $stamps : array();
		$stamps[ $key ] = time();
		set_transient( self::TRANSIENT_KEY . '_ts', $stamps, self::STALE_TTL );
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
		// Which one a key is cannot be told by looking at it, so the answer is remembered from
		// whatever CoinGecko said last time and corrected below if it turns out to be wrong.
		$remembered = get_option( 'xdwp_coingecko_tier', '' );
		$is_pro     = $api_key && ( 'pro' === $remembered || ( '' === $remembered && self::coingecko_key_is_pro( $api_key ) ) );

		foreach ( $chunks as $chunk ) {
			$result = self::coingecko_request( $chunk, $currency, $api_key, $is_pro );
			$code   = $result['code'];
			$body   = $result['body'];

			if ( $api_key && ( 200 !== $code || ! is_array( $body ) ) ) {
				// Either CoinGecko named the host mismatch outright, or it simply refused a Pro
				// request; both are worth one attempt on the other host.
				$hint  = self::coingecko_host_hint( $body );
				$other = ( 10010 === $hint ) ? true : ( ( 10011 === $hint ) ? false : ! $is_pro );
				$worth = $hint > 0 || in_array( $code, array( 400, 401, 403 ), true );

				if ( $worth && $other !== $is_pro ) {
					$result = self::coingecko_request( $chunk, $currency, $api_key, $other );
					if ( 200 === $result['code'] && is_array( $result['body'] ) ) {
						// It works on that host, so stop guessing from now on.
						update_option( 'xdwp_coingecko_tier', $other ? 'pro' : 'demo', false );
						$is_pro = $other;
						$code   = $result['code'];
						$body   = $result['body'];
					}
				}
			}

			if ( 200 !== $code || ! is_array( $body ) ) {
				continue;
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
	 * Ask CoinGecko for a page of prices, on whichever host this key belongs to.
	 *
	 * @param array  $chunk    CoinGecko ids.
	 * @param string $currency Fiat currency.
	 * @param string $api_key  Key, or ''.
	 * @param bool   $pro      Use the Pro host and header.
	 * @return array{code:int,body:mixed}
	 */
	private static function coingecko_request( array $chunk, $currency, $api_key, $pro ) {
		$url = add_query_arg(
			array(
				'ids'           => implode( ',', $chunk ),
				'vs_currencies' => $currency,
			),
			$pro
				? 'https://pro-api.coingecko.com/api/v3/simple/price'
				: 'https://api.coingecko.com/api/v3/simple/price'
		);

		$args = array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		);
		if ( '' !== (string) $api_key ) {
			$args['headers'][ $pro ? 'x-cg-pro-api-key' : 'x-cg-demo-api-key' ] = $api_key;
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'code' => 0,
				'body' => null,
			);
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * Whether a reply is CoinGecko saying "right key, wrong host".
	 *
	 * Demo and Pro keys are both spelled CG-…, so a key alone cannot say which host it belongs
	 * to. CoinGecko will though: 10010 means a Pro key arrived at the free host, 10011 the other
	 * way round. Reading that is the only way to get a paid key onto the right host without
	 * asking the shop owner to know which kind they bought.
	 *
	 * @param mixed $body Decoded response.
	 * @return int 10010, 10011, or 0.
	 */
	private static function coingecko_host_hint( $body ) {
		if ( ! is_array( $body ) ) {
			return 0;
		}
		foreach ( array( $body, isset( $body['status'] ) && is_array( $body['status'] ) ? $body['status'] : array() ) as $where ) {
			if ( isset( $where['error_code'] ) && in_array( (int) $where['error_code'], array( 10010, 10011 ), true ) ) {
				return (int) $where['error_code'];
			}
		}
		return 0;
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
