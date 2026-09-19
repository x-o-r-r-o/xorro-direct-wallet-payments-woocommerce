<?php
/**
 * Backup exchange-rate sources.
 *
 * CoinGecko is the primary source. When it is rate-limited or down, checkout would otherwise
 * stop, so these public exchange tickers are used as a fallback. When two of them answer, the
 * rates must agree within MAX_DISAGREEMENT or no rate is returned — a wrong rate would quote a
 * wrong amount, which is worse than asking the customer to try again.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Rates
 */
class Xdwp_Rates {

	/** Reject fallback rates that differ by more than this share (5%). */
	const MAX_DISAGREEMENT = 0.05;

	/** Cache successful fallback lookups for this long. */
	const CACHE_TTL = 120;

	/** Stop calling a provider for this long after it fails. */
	const PROVIDER_BACKOFF = 300;

	/**
	 * Stablecoins that may be treated as exactly one unit of the fiat they track.
	 *
	 * @return array<string, string> Coin symbol => fiat code.
	 */
	public static function stablecoin_pegs() {
		return array(
			'USDT'  => 'USD',
			'USDC'  => 'USD',
			'DAI'   => 'USD',
			'TUSD'  => 'USD',
			'USDP'  => 'USD',
			'GUSD'  => 'USD',
			'PYUSD' => 'USD',
			'USDD'  => 'USD',
			'USDE'  => 'USD',
			'USDJ'  => 'USD',
			'EURT'  => 'EUR',
		);
	}

	/**
	 * 1.0 when the merchant asked for stablecoins to be priced at face value and this coin
	 * tracks the store's own currency, otherwise 0.
	 *
	 * @param string $symbol   Coin symbol.
	 * @param string $currency Store currency.
	 * @return float
	 */
	public static function pegged_rate( $symbol, $currency ) {
		if ( 'yes' !== Xdwp_Settings::get( 'stablecoin_peg', 'yes' ) ) {
			return 0.0;
		}
		$pegs   = self::stablecoin_pegs();
		$symbol = strtoupper( (string) $symbol );
		return ( isset( $pegs[ $symbol ] ) && $pegs[ $symbol ] === strtoupper( (string) $currency ) ) ? 1.0 : 0.0;
	}

	/**
	 * Rate for one coin from the backup sources, or 0 when none is trustworthy.
	 *
	 * @param string $symbol   Coin symbol (BTC, ETH…).
	 * @param string $currency Fiat code.
	 * @return float
	 */
	public static function fallback_rate( $symbol, $currency ) {
		$symbol   = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $symbol ) );
		$currency = strtoupper( (string) $currency );
		if ( '' === $symbol || '' === $currency ) {
			return 0.0;
		}

		$cache_key = 'xdwp_fb_' . strtolower( $symbol . '_' . $currency );
		$cached    = get_transient( $cache_key );
		if ( is_numeric( $cached ) && (float) $cached > 0 ) {
			return (float) $cached;
		}

		$rates = array();
		foreach ( array( 'coinbase', 'kraken', 'binance' ) as $provider ) {
			$rate = self::provider_rate( $provider, $symbol, $currency );
			if ( $rate > 0 ) {
				$rates[ $provider ] = $rate;
			}
			// Two agreeing sources are enough; no need to call the third.
			if ( count( $rates ) >= 2 ) {
				break;
			}
		}

		if ( empty( $rates ) ) {
			return 0.0;
		}

		if ( count( $rates ) >= 2 ) {
			$min = min( $rates );
			$max = max( $rates );
			if ( $min <= 0 || ( ( $max - $min ) / $min ) > self::MAX_DISAGREEMENT ) {
				self::log(
					sprintf(
						'Backup rate sources disagree for %1$s/%2$s (%3$s) — no rate used. Checkout in this coin fails until sources agree.',
						$symbol,
						$currency,
						wp_json_encode( $rates )
					)
				);
				return 0.0;
			}
			// Middle of the agreeing sources.
			$rate = array_sum( $rates ) / count( $rates );
		} else {
			$rate = (float) reset( $rates );
		}

		set_transient( $cache_key, $rate, self::CACHE_TTL );
		self::log( sprintf( 'Used backup rate source(s) %1$s for %2$s/%3$s: %4$s', implode( ', ', array_keys( $rates ) ), $symbol, $currency, $rate ), 'info' );
		return (float) $rate;
	}

	/**
	 * One provider's rate, or 0.
	 *
	 * @param string $provider coinbase | kraken | binance.
	 * @param string $symbol   Coin symbol.
	 * @param string $currency Fiat code.
	 * @return float
	 */
	private static function provider_rate( $provider, $symbol, $currency ) {
		$backoff_key = 'xdwp_fb_down_' . $provider;
		if ( get_transient( $backoff_key ) ) {
			return 0.0;
		}

		$body = null;
		switch ( $provider ) {
			case 'coinbase':
				// One call returns the coin's price in every supported fiat.
				$body = self::get_json( 'https://api.coinbase.com/v2/exchange-rates?currency=' . rawurlencode( $symbol ) );
				$rate = ( is_array( $body ) && isset( $body['data']['rates'][ $currency ] ) ) ? (float) $body['data']['rates'][ $currency ] : 0.0;
				// Coinbase quotes how much fiat one coin buys, already the right way round.
				break;

			case 'kraken':
				$pair = self::kraken_symbol( $symbol ) . $currency;
				$body = self::get_json( 'https://api.kraken.com/0/public/Ticker?pair=' . rawurlencode( $pair ) );
				$rate = 0.0;
				if ( is_array( $body ) && empty( $body['error'] ) && ! empty( $body['result'] ) && is_array( $body['result'] ) ) {
					$first = reset( $body['result'] );
					$rate  = isset( $first['c'][0] ) ? (float) $first['c'][0] : 0.0;
				}
				break;

			case 'binance':
				// Binance quotes against USDT, so it only stands in for USD prices.
				if ( 'USD' !== $currency ) {
					return 0.0;
				}
				$body = self::get_json( 'https://api.binance.com/api/v3/ticker/price?symbol=' . rawurlencode( $symbol . 'USDT' ) );
				$rate = ( is_array( $body ) && isset( $body['price'] ) ) ? (float) $body['price'] : 0.0;
				break;

			default:
				return 0.0;
		}

		// No response at all means the provider is unreachable (an unknown symbol is not an
		// outage), so stop calling it for a while.
		if ( null === $body ) {
			set_transient( $backoff_key, 1, self::PROVIDER_BACKOFF );
			return 0.0;
		}
		return ( $rate > 0 && is_finite( $rate ) ) ? $rate : 0.0;
	}

	/**
	 * Kraken uses XBT for Bitcoin and XDG for Dogecoin.
	 *
	 * @param string $symbol Coin symbol.
	 * @return string
	 */
	private static function kraken_symbol( $symbol ) {
		$map = array(
			'BTC'  => 'XBT',
			'DOGE' => 'XDG',
		);
		return isset( $map[ $symbol ] ) ? $map[ $symbol ] : $symbol;
	}

	/**
	 * GET + decode JSON, or null on failure (also marks the provider as down).
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	private static function get_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * Log to WooCommerce → Status → Logs.
	 *
	 * @param string $message Message.
	 * @param string $level   info | warning.
	 */
	private static function log( $message, $level = 'warning' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'xorro-wallet-payments' ) );
		}
	}

	/**
	 * Fiat currencies CoinGecko can price in (cached for a week).
	 *
	 * @return array<int, string> Uppercase codes, empty when unknown.
	 */
	public static function supported_currencies() {
		$cached = get_transient( 'xdwp_vs_currencies' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$body = self::get_json( 'https://api.coingecko.com/api/v3/simple/supported_vs_currencies' );
		if ( ! is_array( $body ) || empty( $body ) ) {
			return array();
		}
		$list = array_map( 'strtoupper', array_filter( $body, 'is_string' ) );
		set_transient( 'xdwp_vs_currencies', $list, WEEK_IN_SECONDS );
		return $list;
	}
}
