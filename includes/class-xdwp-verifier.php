<?php
/**
 * On-chain payment verification via public blockchain APIs.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Verifier
 */
class Xdwp_Verifier {

	/**
	 * Verify whether a payment matching order meta has been received.
	 * On success, stores confirming txid on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function verify_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( 'awaiting' !== Xdwp_Order::meta( $order, 'status' ) ) {
			return false;
		}

		// Never auto-verify cancelled/refunded/trash orders (even if meta was left awaiting).
		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return false;
		}

		$coin_id = Xdwp_Order::meta( $order, 'coin' );
		$address = Xdwp_Order::meta( $order, 'address' );
		$amount  = Xdwp_Order::meta( $order, 'amount' );
		$started = (int) Xdwp_Order::meta( $order, 'started' );

		if ( ! $coin_id || ! $address || ! $amount || ! $started ) {
			return false;
		}

		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin ) {
			return false;
		}

		$band = self::match_band( $amount, $coin );
		$min  = $band['min'];
		$max  = $band['max'];

		// Shared-wallet safety: require unique target amount among awaiting/recent-expired orders on this address.
		if ( ! self::can_safely_match_shared_address( $coin, $address, $order->get_id(), $amount ) ) {
			return false;
		}

		// Allow at most 30s clock skew — wide negative windows enabled payment reuse across expired orders.
		$since = max( 0, $started - 30 );
		$txid  = self::find_payment( $coin, $address, $min, $max, $since );
		if ( ! $txid ) {
			return false;
		}

		$txid = strtolower( sanitize_text_field( $txid ) );
		if ( ! self::claim_txid( $txid, $order->get_id() ) ) {
			return false;
		}

		$order->update_meta_data( '_xdwp_txid', $txid );
		$order->save();

		return true;
	}

	/**
	 * Build an absolute match band that cannot overwhelm unique dust.
	 *
	 * Percentage underpayment is capped so concurrent shared-wallet orders remain distinguishable.
	 *
	 * @param string $amount Order crypto amount string.
	 * @param array  $coin   Coin def.
	 * @return array{min:float,max:float}
	 */
	private static function match_band( $amount, array $coin ) {
		$decimals = isset( $coin['decimals'] ) ? min( (int) $coin['decimals'], 18 ) : 8;
		$target   = (float) $amount;
		$unit     = pow( 10, -$decimals );

		// Absolute epsilon: 1 base unit for low-decimal assets (GUSD/EOS); dust-oriented floor only when unique dust applies.
		$abs_eps = ( $decimals <= 4 ) ? $unit : max( $unit * 50, $unit );

		$tolerance_pct = max( 0.0, (float) Xdwp_Settings::get( 'underpayment_percent', 1 ) );
		$pct_under     = $target * ( $tolerance_pct / 100 );
		$pct_over      = $target * ( max( $tolerance_pct, 0.5 ) / 100 );

		// Never let % tolerance exceed half a unique-dust step when unique amounts are on.
		$max_band = $abs_eps;
		if ( 'yes' === Xdwp_Settings::get( 'unique_amounts', 'yes' ) && $decimals > 4 ) {
			// Dust steps are 1000 units apart; keep band well below that gap.
			$max_band = max( $abs_eps, $unit * 400 );
		} else {
			$max_band = max( $abs_eps, min( $pct_under > 0 ? $pct_under : $abs_eps, $target * 0.02 ) );
		}

		// 0% underpayment tolerance must mean exact (no absolute floor bypass for GUSD/etc.).
		if ( $tolerance_pct <= 0 ) {
			$under = 0.0;
			$over  = min( $abs_eps, $max_band );
		} else {
			$under = min( $pct_under > 0 ? $pct_under : $abs_eps, $max_band );
			$over  = min( $pct_over > 0 ? $pct_over : $abs_eps, $max_band );
		}

		return array(
			'min' => max( 0.0, $target - $under ),
			'max' => $target + $over,
		);
	}

	/**
	 * Public wrapper: whether an amount can safely share an address with other awaiting orders.
	 *
	 * @param string $coin_id           Coin ID.
	 * @param string $address           Deposit address.
	 * @param string $amount            Exact crypto amount.
	 * @param int    $exclude_order_id  Order to exclude (usually the one being assigned).
	 * @return bool
	 */
	public static function amount_safe_for_address( $coin_id, $address, $amount, $exclude_order_id = 0 ) {
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin || ! $address ) {
			return false;
		}
		return self::can_safely_match_shared_address( $coin, $address, $exclude_order_id, $amount );
	}

	/**
	 * Whether shared-address matching is safe for this order.
	 *
	 * Paginates all awaiting peers on the address (fail-closed if the set is huge).
	 * A fixed small limit previously hid peers past the first page → wrong-order attribution.
	 *
	 * @param array  $coin       Coin def.
	 * @param string $address    Address.
	 * @param int    $order_id   Current order.
	 * @param string $amount     Exact crypto amount for this order.
	 * @return bool
	 */
	private static function can_safely_match_shared_address( array $coin, $address, $order_id, $amount = '' ) {
		$others     = array();
		$page       = 1;
		$per_page   = 100;
		$max_peers  = 500; // Beyond this, refuse matching (fail closed).
		$base_args  = array(
			'limit'          => $per_page,
			'status'         => array( 'on-hold', 'pending', 'failed', 'cancelled', 'refunded' ),
			'payment_method' => XDWP_GATEWAY_ID,
			'exclude'        => array( absint( $order_id ) ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_xdwp_status',
					'value'   => array( 'awaiting', 'expired', 'cancelled' ),
					'compare' => 'IN',
				),
				array(
					'key'   => '_xdwp_address',
					'value' => $address,
				),
			),
			'return'         => 'objects',
		);

		do {
			$batch = wc_get_orders(
				array_merge(
					$base_args,
					array( 'page' => $page )
				)
			);
			if ( empty( $batch ) ) {
				break;
			}
			foreach ( $batch as $peer ) {
				$others[] = $peer;
			}
			if ( count( $others ) > $max_peers ) {
				return false;
			}
			++$page;
		} while ( count( $batch ) === $per_page );

		if ( empty( $others ) ) {
			return true;
		}

		$decimals = isset( $coin['decimals'] ) ? (int) $coin['decimals'] : 8;
		$unique   = ( 'yes' === Xdwp_Settings::get( 'unique_amounts', 'yes' ) );

		// Low-decimal / no unique dust: only one awaiting order may share an address.
		if ( ! $unique || $decimals <= 4 ) {
			return false;
		}

		// High decimals with unique dust: still refuse if another order has the same exact amount.
		$amount     = (string) $amount;
		$retain_ttl = WEEK_IN_SECONDS + ( (int) Xdwp_Settings::get( 'payment_window', 60 ) * MINUTE_IN_SECONDS );
		foreach ( $others as $other ) {
			if ( ! $other instanceof WC_Order ) {
				continue;
			}
			$other_status = (string) Xdwp_Order::meta( $other, 'status' );
			if ( in_array( $other_status, array( 'expired', 'cancelled' ), true ) ) {
				$other_started = (int) Xdwp_Order::meta( $other, 'started' );
				if ( ! $other_started || ( time() - $other_started ) > $retain_ttl ) {
					continue;
				}
			}
			$other_amount = (string) Xdwp_Order::meta( $other, 'amount' );
			if ( $amount && hash_equals( $amount, $other_amount ) ) {
				return false;
			}
			// Overlapping bands are unsafe even with different strings after formatting.
			$band_a = self::match_band( $amount, $coin );
			$band_b = self::match_band( $other_amount, $coin );
			if ( $band_a['min'] <= $band_b['max'] && $band_b['min'] <= $band_a['max'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Atomically reserve an (address, amount) slot so concurrent checkouts cannot publish colliding quotes.
	 *
	 * @param string $address  Deposit address.
	 * @param string $amount   Exact crypto amount string.
	 * @param int    $order_id Order ID.
	 * @return bool
	 */
	public static function reserve_amount_slot( $address, $amount, $order_id ) {
		$address = strtolower( trim( (string) $address ) );
		$amount  = (string) $amount;
		if ( '' === $address || '' === $amount ) {
			return false;
		}

		$key     = 'xdwp_amt_' . md5( $address . '|' . $amount );
		$payload = absint( $order_id ) . '|' . time();
		// Keep slots only through payment window + grace + one day (not a full week).
		$window  = (int) Xdwp_Settings::get( 'payment_window', 60 );
		$grace   = (int) Xdwp_Settings::get( 'expiry_grace_minutes', 30 );
		$ttl     = max( HOUR_IN_SECONDS, ( ( $window + $grace ) * MINUTE_IN_SECONDS ) + DAY_IN_SECONDS );

		if ( ! add_option( $key, $payload, '', 'no' ) ) {
			$existing = (string) get_option( $key, '' );
			$parts    = explode( '|', $existing, 2 );
			$owner    = isset( $parts[0] ) ? (int) $parts[0] : 0;
			$claimed  = isset( $parts[1] ) ? (int) $parts[1] : 0;

			if ( $owner === absint( $order_id ) ) {
				return true;
			}
			if ( $claimed && ( time() - $claimed ) > $ttl ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
						$payload,
						$key,
						$existing
					)
				);
				return 1 === $updated;
			}
			return false;
		}

		return true;
	}

	/**
	 * Release an (address, amount) reservation when the order is paid (txid claim still blocks reuse).
	 *
	 * @param string $address  Deposit address.
	 * @param string $amount   Exact crypto amount string.
	 * @param int    $order_id Owning order ID (must match payload owner).
	 * @return void
	 */
	public static function release_amount_slot( $address, $amount, $order_id ) {
		$address = strtolower( trim( (string) $address ) );
		$amount  = (string) $amount;
		$order_id = absint( $order_id );
		if ( '' === $address || '' === $amount || ! $order_id ) {
			return;
		}
		$key      = 'xdwp_amt_' . md5( $address . '|' . $amount );
		$existing = (string) get_option( $key, '' );
		if ( '' === $existing ) {
			return;
		}
		$parts = explode( '|', $existing, 2 );
		$owner = isset( $parts[0] ) ? (int) $parts[0] : 0;
		if ( $owner !== $order_id ) {
			return;
		}
		delete_option( $key );
	}

	/**
	 * Reserve a txid for an order (public entry for manual mark-paid).
	 *
	 * @param string $txid     Txid.
	 * @param int    $order_id Order ID.
	 * @return bool
	 */
	public static function reserve_txid( $txid, $order_id ) {
		return self::claim_txid( $txid, $order_id );
	}

	/**
	 * Release a txid claim when mark-paid fails after reservation.
	 *
	 * @param string $txid     Txid.
	 * @param int    $order_id Owning order ID.
	 * @return void
	 */
	public static function release_txid( $txid, $order_id ) {
		$txid     = strtolower( trim( (string) $txid ) );
		$order_id = absint( $order_id );
		if ( '' === $txid || ! $order_id ) {
			return;
		}
		$key      = 'xdwp_txid_claim_' . md5( $txid );
		$existing = (string) get_option( $key, '' );
		if ( '' === $existing ) {
			return;
		}
		$parts = explode( '|', $existing, 2 );
		$owner = isset( $parts[0] ) ? (int) $parts[0] : 0;
		if ( $owner !== $order_id ) {
			return;
		}
		delete_option( $key );
	}

	/**
	 * Atomically claim a txid for an order. Returns false if already claimed by another order.
	 * Claims expire after payment window + 7 days so the options table does not grow forever.
	 *
	 * @param string $txid     Txid.
	 * @param int    $order_id Order ID.
	 * @return bool
	 */
	private static function claim_txid( $txid, $order_id ) {
		$txid = strtolower( trim( (string) $txid ) );
		if ( '' === $txid ) {
			return false;
		}

		if ( self::txid_already_used( $txid, $order_id ) ) {
			return false;
		}

		$claim_key = 'xdwp_txid_claim_' . md5( $txid );
		$payload   = absint( $order_id ) . '|' . time();

		if ( ! add_option( $claim_key, $payload, '', 'no' ) ) {
			$existing = (string) get_option( $claim_key, '' );
			$parts    = explode( '|', $existing, 2 );
			$owner    = isset( $parts[0] ) ? (int) $parts[0] : 0;
			$claimed  = isset( $parts[1] ) ? (int) $parts[1] : 0;
			$ttl      = WEEK_IN_SECONDS + ( (int) Xdwp_Settings::get( 'payment_window', 60 ) * MINUTE_IN_SECONDS );

			if ( $owner === absint( $order_id ) ) {
				return true;
			}
			// Stale orphaned claim — reclaim only via compare-and-swap (no TOCTOU race).
			if ( $claimed && ( time() - $claimed ) > $ttl ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
						$payload,
						$claim_key,
						$existing
					)
				);
				if ( 1 !== $updated ) {
					return false;
				}
				return ! self::txid_already_used( $txid, $order_id );
			}
			return false;
		}

		// Double-check WC meta after claim.
		if ( self::txid_already_used( $txid, $order_id ) ) {
			delete_option( $claim_key );
			return false;
		}

		return true;
	}

	/**
	 * Per-request tip height cache (seconds).
	 *
	 * @var array<string, array{h:int,t:int}>
	 */
	private static $tip_cache = array();

	/**
	 * Minimum confirmations required before accepting a payment.
	 *
	 * @return int
	 */
	private static function min_confirmations() {
		return max( 0, min( 64, (int) Xdwp_Settings::get( 'min_confirmations', 1 ) ) );
	}

	/**
	 * Fail-closed soft finality for explorers that only expose success/validated flags (no tip depth).
	 * Zero confirmations still requires a successful/validated tx — never accept explicit failures.
	 * When the merchant asks for more than one confirmation, refuse rather than pretend depth was checked.
	 *
	 * @param bool $validated Explorer reports success / validated / irreversible.
	 * @return bool
	 */
	private static function soft_finality_ok( $validated ) {
		$need = self::min_confirmations();
		if ( $need > 1 ) {
			return false;
		}
		return (bool) $validated;
	}

	/**
	 * Fail-closed confirmation depth check.
	 *
	 * @param int|string|null $have Observed confirmations (null/empty = unknown).
	 * @return bool
	 */
	private static function confirmations_ok( $have ) {
		$need = self::min_confirmations();
		if ( $need <= 0 ) {
			return true;
		}
		if ( null === $have || '' === $have ) {
			return false;
		}
		return (int) $have >= $need;
	}

	/**
	 * Confirmations from tx block height vs tip (null when either is missing).
	 *
	 * @param int $tx_block Tx block height.
	 * @param int $tip_block Chain tip height.
	 * @return int|null
	 */
	private static function tip_depth( $tx_block, $tip_block ) {
		$tx_block  = (int) $tx_block;
		$tip_block = (int) $tip_block;
		if ( $tx_block <= 0 || $tip_block <= 0 ) {
			return null;
		}
		return $tip_block - $tx_block + 1;
	}

	/**
	 * Whether block depth meets min_confirmations (fail closed if tip/tx unknown).
	 *
	 * @param int $tx_block  Tx block height.
	 * @param int $tip_block Tip height.
	 * @return bool
	 */
	private static function block_depth_ok( $tx_block, $tip_block ) {
		return self::confirmations_ok( self::tip_depth( $tx_block, $tip_block ) );
	}

	/**
	 * Cached tip height for a chain key.
	 *
	 * @param string   $key     Cache key.
	 * @param callable $fetcher Returns int tip or 0/null.
	 * @return int Tip height or 0.
	 */
	private static function cached_tip( $key, $fetcher ) {
		$now = time();
		if ( isset( self::$tip_cache[ $key ] ) && ( $now - self::$tip_cache[ $key ]['t'] ) < 30 ) {
			return (int) self::$tip_cache[ $key ]['h'];
		}
		$height = (int) call_user_func( $fetcher );
		if ( $height > 0 ) {
			self::$tip_cache[ $key ] = array(
				'h' => $height,
				't' => $now,
			);
		}
		return $height;
	}

	/**
	 * Whether an Etherscan tx row has enough confirmations (when the field is present).
	 *
	 * @param array $tx Tx row.
	 * @return bool
	 */
	private static function etherscan_confirmed( array $tx ) {
		// Fail closed when the explorer omits confirmation depth.
		return self::confirmations_ok( isset( $tx['confirmations'] ) ? $tx['confirmations'] : null );
	}

	/**
	 * Whether amount falls inside the accepted band.
	 *
	 * @param float|string $value Value.
	 * @param float        $min   Min.
	 * @param float        $max   Max.
	 * @return bool
	 */
	private static function amount_in_band( $value, $min, $max ) {
		if ( function_exists( 'bccomp' ) ) {
			$v  = is_string( $value ) ? $value : number_format( (float) $value, 18, '.', '' );
			$mn = number_format( (float) $min, 18, '.', '' );
			$mx = number_format( (float) $max, 18, '.', '' );
			return bccomp( $v, $mn, 18 ) >= 0 && bccomp( $v, $mx, 18 ) <= 0;
		}
		$value = (float) $value;
		return ( ( $value + 1e-12 ) >= $min ) && ( ( $value - 1e-12 ) <= $max );
	}

	/**
	 * Decode a Cosmos LCD attribute if it looks like base64; otherwise return as-is.
	 *
	 * @param string $value Raw attribute.
	 * @return string
	 */
	private static function maybe_base64_decode( $value ) {
		$value = (string) $value;
		if ( '' === $value || ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $value ) ) {
			return $value;
		}
		$decoded = base64_decode( $value, true );
		if ( false === $decoded || '' === $decoded ) {
			return $value;
		}
		// Prefer printable decoded strings (e.g. recipient / amount).
		if ( ! preg_match( '/^[\x20-\x7E]+$/', $decoded ) ) {
			return $value;
		}
		return $decoded;
	}

	/**
	 * Compare an integer base-unit amount against a float band using BCMath when available.
	 *
	 * @param string $raw      Integer string in base units.
	 * @param int    $decimals Token decimals.
	 * @param float  $min      Min human amount.
	 * @param float  $max      Max human amount.
	 * @return bool
	 */
	private static function raw_amount_in_band( $raw, $decimals, $min, $max ) {
		$raw      = preg_replace( '/\D/', '', (string) $raw );
		$decimals = max( 0, (int) $decimals );
		if ( '' === $raw ) {
			return false;
		}
		if ( function_exists( 'bcdiv' ) && function_exists( 'bcpow' ) ) {
			$scale = min( 18, $decimals + 6 );
			$value = bcdiv( $raw, bcpow( '10', (string) $decimals, 0 ), $scale );
			return self::amount_in_band( $value, $min, $max );
		}
		return self::amount_in_band( ( (float) $raw ) / pow( 10, $decimals ), $min, $max );
	}

	/**
	 * Check if a txid is already claimed by another Xorro Wallet Payments order.
	 *
	 * @param string $txid          Transaction id.
	 * @param int    $exclude_order Order to exclude.
	 * @return bool
	 */
	private static function txid_already_used( $txid, $exclude_order = 0 ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'exclude'    => array( absint( $exclude_order ) ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_xdwp_txid',
						'value' => $txid,
					),
				),
			)
		);
		return ! empty( $orders );
	}

	/**
	 * Find a matching incoming payment.
	 *
	 * @param array  $coin    Coin definition.
	 * @param string $address Receiving address.
	 * @param float  $min     Minimum amount.
	 * @param float  $max     Maximum amount.
	 * @param int    $since   Unix timestamp (payments after this).
	 * @return string|false Txid on match, false otherwise.
	 */
	public static function find_payment( array $coin, $address, $min, $max, $since ) {
		$verifier = $coin['verifier'];

		switch ( $verifier ) {
			case 'btc':
				$found = self::check_mempool( $address, $min, $max, $since );
				if ( $found ) {
					return $found;
				}
				return self::check_blockstream( 'https://blockstream.info/api', $address, $min, $max, $since, 8 );
			case 'bch':
				return self::check_blockchair( 'bitcoin-cash', $address, $min, $max, $since );
			case 'ltc':
				return self::check_blockchair( 'litecoin', $address, $min, $max, $since );
			case 'doge':
				return self::check_blockchair( 'dogecoin', $address, $min, $max, $since );
			case 'eth':
			case 'ethereum':
				return self::check_evm( 1, $address, $min, $max, $since, $coin );
			case 'arbitrum':
				return self::check_evm( 42161, $address, $min, $max, $since, $coin );
			case 'optimism':
				return self::check_evm( 10, $address, $min, $max, $since, $coin );
			case 'base':
				return self::check_evm( 8453, $address, $min, $max, $since, $coin );
			case 'bsc':
				return self::check_evm( 56, $address, $min, $max, $since, $coin );
			case 'matic':
				return self::check_evm( 137, $address, $min, $max, $since, $coin );
			case 'avax':
				return self::check_evm( 43114, $address, $min, $max, $since, $coin );
			case 'ftm':
				return self::check_evm( 250, $address, $min, $max, $since, $coin );
			case 'cro':
				return self::check_evm( 25, $address, $min, $max, $since, $coin );
			case 'etc':
				return self::check_evm( 61, $address, $min, $max, $since, $coin );
			case 'sol':
			case 'solana':
				return self::check_solana( $address, $min, $max, $since, $coin );
			case 'trx':
			case 'tron':
				return self::check_tron( $address, $min, $max, $since, $coin );
			case 'xrp':
				return self::check_xrp( $address, $min, $max, $since );
			case 'xlm':
				return self::check_stellar( $address, $min, $max, $since );
			case 'algo':
				return self::check_algo( $address, $min, $max, $since );
			case 'hbar':
				return self::check_hbar( $address, $min, $max, $since );
			case 'near':
				return self::check_near( $address, $min, $max, $since );
			case 'atom':
				return self::check_atom( $address, $min, $max, $since );
			case 'egld':
				return self::check_egld( $address, $min, $max, $since );
			case 'fil':
				return self::check_fil( $address, $min, $max, $since );
			case 'eos':
				return self::check_eos( $address, $min, $max, $since );
			case 'dot':
				return self::check_dot( $address, $min, $max, $since );
			case 'zil':
				return self::check_zil( $address, $min, $max, $since );
			case 'xmr':
				// Monero requires a private view key for inbound detection — kept manual.
				return false;
			default:
				return false;
		}
	}

	/**
	 * Blockstream-style UTXO explorer (BTC).
	 *
	 * @param string $base    API base URL.
	 * @param string $address Address.
	 * @param float  $min     Min amount.
	 * @param int    $since   Since timestamp.
	 * @param int    $decimals Decimals.
	 * @return bool
	 */
	private static function check_blockstream( $base, $address, $min, $max, $since, $decimals = 8 ) {
		$url      = trailingslashit( $base ) . 'address/' . rawurlencode( $address ) . '/txs';
		$response = self::http_get( $url );
		if ( ! is_array( $response ) ) {
			return false;
		}

		$need = self::min_confirmations();
		$tip  = 0;
		if ( $need > 0 ) {
			$tip = self::cached_tip(
				'bs:' . $base,
				static function () use ( $base ) {
					return self::http_get_int( trailingslashit( $base ) . 'blocks/tip/height' );
				}
			);
			// Fail closed when depth is required but tip is unavailable.
			if ( $tip <= 0 ) {
				return false;
			}
		}

		foreach ( $response as $tx ) {
			$status = isset( $tx['status'] ) ? $tx['status'] : array();
			// Require at least one confirmation — never accept 0-conf.
			if ( empty( $status['confirmed'] ) ) {
				continue;
			}
			$block_height = isset( $status['block_height'] ) ? (int) $status['block_height'] : 0;
			if ( $need > 0 && ! self::block_depth_ok( $block_height, $tip ) ) {
				continue;
			}
			$time = isset( $status['block_time'] ) ? (int) $status['block_time'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( empty( $tx['vout'] ) || ! is_array( $tx['vout'] ) ) {
				continue;
			}
			$sum = 0.0;
			foreach ( $tx['vout'] as $vout ) {
				$addr = '';
				if ( ! empty( $vout['scriptpubkey_address'] ) ) {
					$addr = $vout['scriptpubkey_address'];
				}
				if ( 0 === strcasecmp( $addr, $address ) && isset( $vout['value'] ) ) {
					$sum += ( (float) $vout['value'] ) / pow( 10, $decimals );
				}
			}
			if ( self::amount_in_band( $sum, $min, $max ) ) {
				return ! empty( $tx['txid'] ) ? (string) $tx['txid'] : 'btc-' . md5( wp_json_encode( $tx ) );
			}
		}

		return false;
	}

	/**
	 * Blockchair address transactions.
	 *
	 * @param string $chain   Chain slug.
	 * @param string $address Address.
	 * @param float  $min     Min amount.
	 * @param int    $since   Since timestamp.
	 * @return bool
	 */
	private static function check_blockchair( $chain, $address, $min, $max, $since ) {
		$lookup = $address;
		// Bitcoin Cash CashAddr may include a prefix Blockchair rejects in the path.
		if ( 'bitcoin-cash' === $chain && 0 === stripos( $address, 'bitcoincash:' ) ) {
			$lookup = substr( $address, strlen( 'bitcoincash:' ) );
		}

		$url      = sprintf( 'https://api.blockchair.com/%s/dashboards/address/%s?limit=25', rawurlencode( $chain ), rawurlencode( $lookup ) );
		$response = self::http_get( $url );
		if ( ! is_array( $response ) || empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return false;
		}

		$row = null;
		if ( isset( $response['data'][ $lookup ] ) && is_array( $response['data'][ $lookup ] ) ) {
			$row = $response['data'][ $lookup ];
		} elseif ( isset( $response['data'][ $address ] ) && is_array( $response['data'][ $address ] ) ) {
			$row = $response['data'][ $address ];
		}
		// Fail closed: never fall back to an unrelated address payload from Blockchair.
		if ( ! $row || empty( $row['transactions'] ) || ! is_array( $row['transactions'] ) ) {
			return false;
		}

		$need = self::min_confirmations();
		$txs  = $row['transactions'];
		foreach ( array_slice( $txs, 0, 15 ) as $txid ) {
			$tx_url = sprintf( 'https://api.blockchair.com/%s/dashboards/transaction/%s', rawurlencode( $chain ), rawurlencode( $txid ) );
			$tx     = self::http_get( $tx_url );
			if ( ! is_array( $tx ) || empty( $tx['data'][ $txid ] ) ) {
				continue;
			}
			$data = $tx['data'][ $txid ];
			$time = isset( $data['transaction']['time'] ) ? strtotime( $data['transaction']['time'] ) : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			$block_id = isset( $data['transaction']['block_id'] ) ? (int) $data['transaction']['block_id'] : 0;
			if ( $block_id <= 0 ) {
				continue;
			}
			if ( $need > 0 ) {
				$tip = isset( $response['context']['state'] ) ? (int) $response['context']['state'] : 0;
				// Fail closed when tip is missing — do not accept at 1-conf when merchant asked for more depth.
				if ( ! self::block_depth_ok( $block_id, $tip ) ) {
					continue;
				}
			}
			$sum = 0.0;
			if ( ! empty( $data['outputs'] ) && is_array( $data['outputs'] ) ) {
				foreach ( $data['outputs'] as $out ) {
					if ( empty( $out['recipient'] ) ) {
						continue;
					}
					$recipient = (string) $out['recipient'];
					$recv_bare = $recipient;
					if ( 0 === stripos( $recv_bare, 'bitcoincash:' ) ) {
						$recv_bare = substr( $recv_bare, strlen( 'bitcoincash:' ) );
					}
					if (
						0 === strcasecmp( $recipient, $address )
						|| 0 === strcasecmp( $recipient, $lookup )
						|| 0 === strcasecmp( $recv_bare, $lookup )
						|| 0 === strcasecmp( 'bitcoincash:' . $recv_bare, $address )
						|| 0 === strcasecmp( 'bitcoincash:' . $lookup, $recipient )
					) {
						$sum += ( (float) $out['value'] ) / 1e8;
					}
				}
			}
			if ( self::amount_in_band( $sum, $min, $max ) ) {
				return (string) $txid;
			}
		}

		return false;
	}

	/**
	 * Resolve Etherscan API V2 key.
	 *
	 * @return string
	 */
	private static function etherscan_api_key() {
		$key = Xdwp_Settings::get( 'etherscan_api_key', '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * EVM native/token verification via Etherscan API V2 (one key, many chains).
	 *
	 * @param int    $chain_id Chain ID.
	 * @param string $address  Address.
	 * @param float  $min      Min.
	 * @param float  $max      Max.
	 * @param int    $since    Since.
	 * @param array  $coin     Coin def.
	 * @return string|false
	 */
	private static function check_evm( $chain_id, $address, $min, $max, $since, array $coin ) {
		$type = isset( $coin['type'] ) ? $coin['type'] : 'native';
		if ( in_array( $type, array( 'erc20', 'bep20' ), true ) && ! empty( $coin['contract'] ) ) {
			return self::check_etherscan_v2_token( $chain_id, $address, $coin['contract'], $min, $max, $since, (int) $coin['decimals'] );
		}
		return self::check_etherscan_v2_native( $chain_id, $address, $min, $max, $since );
	}

	/**
	 * Etherscan V2 native transfers.
	 *
	 * @param int    $chain_id Chain ID.
	 * @param string $address  Address.
	 * @param float  $min      Min.
	 * @param float  $max      Max.
	 * @param int    $since    Since.
	 * @return string|false
	 */
	private static function check_etherscan_v2_native( $chain_id, $address, $min, $max, $since ) {
		$api_key = self::etherscan_api_key();
		if ( ! $api_key ) {
			// Without a real key Etherscan V2 rejects requests — skip quietly.
			return false;
		}
		$query   = array(
			'chainid'    => (int) $chain_id,
			'module'     => 'account',
			'action'     => 'txlist',
			'address'    => $address,
			'startblock' => 0,
			'endblock'   => 99999999,
			'page'       => 1,
			'offset'     => 100,
			'sort'       => 'desc',
			'apikey'     => $api_key,
		);
		$url      = 'https://api.etherscan.io/v2/api?' . http_build_query( $query );
		$response = self::http_get( $url );
		if ( ! is_array( $response ) || empty( $response['result'] ) || ! is_array( $response['result'] ) ) {
			return false;
		}

		foreach ( $response['result'] as $tx ) {
			if ( empty( $tx['to'] ) || 0 !== strcasecmp( $tx['to'], $address ) ) {
				continue;
			}
			if ( ! empty( $tx['isError'] ) && '0' !== (string) $tx['isError'] ) {
				continue;
			}
			if ( ! self::etherscan_confirmed( $tx ) ) {
				continue;
			}
			$time = isset( $tx['timeStamp'] ) ? (int) $tx['timeStamp'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( ! isset( $tx['value'] ) ) {
				continue;
			}
			if ( self::raw_amount_in_band( $tx['value'], 18, $min, $max ) ) {
				return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : false;
			}
		}

		return false;
	}

	/**
	 * Etherscan V2 token transfers.
	 *
	 * @param int    $chain_id Chain ID.
	 * @param string $address  Address.
	 * @param string $contract Contract.
	 * @param float  $min      Min.
	 * @param float  $max      Max.
	 * @param int    $since    Since.
	 * @param int    $decimals Decimals.
	 * @return string|false
	 */
	private static function check_etherscan_v2_token( $chain_id, $address, $contract, $min, $max, $since, $decimals ) {
		if ( ! $contract ) {
			return false;
		}
		$api_key = self::etherscan_api_key();
		if ( ! $api_key ) {
			return false;
		}
		$query   = array(
			'chainid'         => (int) $chain_id,
			'module'          => 'account',
			'action'          => 'tokentx',
			'contractaddress' => $contract,
			'address'         => $address,
			'page'            => 1,
			'offset'          => 100,
			'sort'            => 'desc',
			'apikey'          => $api_key,
		);
		$url      = 'https://api.etherscan.io/v2/api?' . http_build_query( $query );
		$response = self::http_get( $url );
		if ( ! is_array( $response ) || empty( $response['result'] ) || ! is_array( $response['result'] ) ) {
			return false;
		}

		foreach ( $response['result'] as $tx ) {
			if ( empty( $tx['to'] ) || 0 !== strcasecmp( $tx['to'], $address ) ) {
				continue;
			}
			// Defensive: never match a different token even if the API ignores contractaddress.
			if ( empty( $tx['contractAddress'] ) || 0 !== strcasecmp( (string) $tx['contractAddress'], (string) $contract ) ) {
				continue;
			}
			if ( ! self::etherscan_confirmed( $tx ) ) {
				continue;
			}
			$time = isset( $tx['timeStamp'] ) ? (int) $tx['timeStamp'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			$dec = (int) $decimals;
			if ( ! isset( $tx['value'] ) ) {
				continue;
			}
			if ( self::raw_amount_in_band( $tx['value'], $dec, $min, $max ) ) {
				return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : false;
			}
		}

		return false;
	}

	/**
	 * Bitcoin via mempool.space (Blockstream-compatible API).
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_mempool( $address, $min, $max, $since ) {
		return self::check_blockstream( 'https://mempool.space/api', $address, $min, $max, $since, 8 );
	}

	/**
	 * Solana: check recent signatures / balances via public RPC.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min amount.
	 * @param int    $since   Since timestamp.
	 * @param array  $coin    Coin def.
	 * @return bool
	 */
	private static function check_solana( $address, $min, $max, $since, array $coin ) {
		$helius      = Xdwp_Settings::get( 'helius_api_key', '' );
		$rpc_headers = array();
		// Helius RPC authenticates via ?api-key= (documented); also send X-Api-Key for gateways that accept it.
		$rpc = 'https://api.mainnet-beta.solana.com';
		if ( $helius ) {
			$rpc                          = 'https://mainnet.helius-rpc.com/?api-key=' . rawurlencode( $helius );
			$rpc_headers['X-Api-Key']     = $helius;
		}

		$need       = self::min_confirmations();
		$commitment = ( $need <= 0 ) ? 'confirmed' : 'finalized';
		$tip_slot   = 0;
		if ( $need > 1 ) {
			$tip_slot = self::cached_tip(
				'sol:' . md5( $rpc ),
				static function () use ( $rpc, $commitment, $rpc_headers ) {
					$res = self::http_post_json(
						$rpc,
						array(
							'jsonrpc' => '2.0',
							'id'      => 1,
							'method'  => 'getSlot',
							'params'  => array( array( 'commitment' => $commitment ) ),
						),
						$rpc_headers
					);
					return isset( $res['result'] ) ? (int) $res['result'] : 0;
				}
			);
			if ( $tip_slot <= 0 ) {
				return false;
			}
		}
		if ( 'spl' === $coin['type'] && ! empty( $coin['contract'] ) ) {
			$watch = array( $address );
			// Resolve associated token accounts for this mint so SPL transfers are visible.
			$ata_body = array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'getTokenAccountsByOwner',
				'params'  => array(
					$address,
					array( 'mint' => $coin['contract'] ),
					array( 'encoding' => 'jsonParsed' ),
				),
			);
			$ata_res = self::http_post_json( $rpc, $ata_body, $rpc_headers );
			if ( ! empty( $ata_res['result']['value'] ) && is_array( $ata_res['result']['value'] ) ) {
				foreach ( $ata_res['result']['value'] as $acct ) {
					if ( ! empty( $acct['pubkey'] ) ) {
						$watch[] = $acct['pubkey'];
					}
				}
			}
			$watch = array_values( array_unique( $watch ) );

			foreach ( $watch as $watch_addr ) {
				$body = array(
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => 'getSignaturesForAddress',
					'params'  => array( $watch_addr, array( 'limit' => 40 ) ),
				);
				$sigs = self::http_post_json( $rpc, $body, $rpc_headers );
				if ( empty( $sigs['result'] ) || ! is_array( $sigs['result'] ) ) {
					continue;
				}
				foreach ( $sigs['result'] as $sig ) {
					if ( ! empty( $sig['err'] ) ) {
						continue;
					}
					$block_time = isset( $sig['blockTime'] ) ? (int) $sig['blockTime'] : 0;
					if ( ! $block_time || $block_time < $since ) {
						continue;
					}
					$txid = isset( $sig['signature'] ) ? $sig['signature'] : '';
					if ( ! $txid ) {
						continue;
					}
					$tx_body = array(
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'getTransaction',
						'params'  => array(
							$txid,
							array(
								'encoding'                       => 'jsonParsed',
								'maxSupportedTransactionVersion' => 0,
								'commitment'                     => $commitment,
							),
						),
					);
					$tx = self::http_post_json( $rpc, $tx_body, $rpc_headers );
					if ( empty( $tx['result']['meta'] ) ) {
						continue;
					}
					if ( $need > 1 ) {
						$slot = isset( $tx['result']['slot'] ) ? (int) $tx['result']['slot'] : 0;
						if ( ! self::block_depth_ok( $slot, $tip_slot ) ) {
							continue;
						}
					}
					$meta = $tx['result']['meta'];
					if ( isset( $meta['err'] ) && null !== $meta['err'] ) {
						continue;
					}
					$pre   = isset( $meta['preTokenBalances'] ) ? $meta['preTokenBalances'] : array();
					$post  = isset( $meta['postTokenBalances'] ) ? $meta['postTokenBalances'] : array();
					$delta = self::solana_token_delta( $pre, $post, $address, $coin['contract'] );
					if ( self::amount_in_band( $delta, $min, $max ) ) {
						return (string) $txid;
					}
				}
			}
			return false;
		}

		// Native SOL.
		$body = array(
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'getSignaturesForAddress',
			'params'  => array( $address, array( 'limit' => 40 ) ),
		);
		$sigs = self::http_post_json( $rpc, $body, $rpc_headers );
		if ( empty( $sigs['result'] ) || ! is_array( $sigs['result'] ) ) {
			return false;
		}
		foreach ( $sigs['result'] as $sig ) {
			if ( ! empty( $sig['err'] ) ) {
				continue;
			}
			$block_time = isset( $sig['blockTime'] ) ? (int) $sig['blockTime'] : 0;
			if ( ! $block_time || $block_time < $since ) {
				continue;
			}
			$txid = isset( $sig['signature'] ) ? $sig['signature'] : '';
			if ( ! $txid ) {
				continue;
			}
			$tx_body = array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'getTransaction',
				'params'  => array(
					$txid,
					array(
						'encoding'                       => 'jsonParsed',
						'maxSupportedTransactionVersion' => 0,
						'commitment'                     => $commitment,
					),
				),
			);
			$tx = self::http_post_json( $rpc, $tx_body, $rpc_headers );
			if ( empty( $tx['result']['meta'] ) || ! is_array( $tx['result']['meta'] ) ) {
				continue;
			}
			if ( $need > 1 ) {
				$slot = isset( $tx['result']['slot'] ) ? (int) $tx['result']['slot'] : 0;
				if ( ! self::block_depth_ok( $slot, $tip_slot ) ) {
					continue;
				}
			}
			$meta = $tx['result']['meta'];
			if ( isset( $meta['err'] ) && null !== $meta['err'] ) {
				continue;
			}
			$message = isset( $tx['result']['transaction']['message'] ) ? $tx['result']['transaction']['message'] : array();
			$keys    = array();
			if ( ! empty( $message['accountKeys'] ) ) {
				foreach ( $message['accountKeys'] as $k ) {
					$keys[] = is_array( $k ) ? $k['pubkey'] : $k;
				}
			}
			$idx = array_search( $address, $keys, true );
			if ( false === $idx || ! isset( $meta['preBalances'][ $idx ], $meta['postBalances'][ $idx ] ) ) {
				continue;
			}
			$delta = ( (float) $meta['postBalances'][ $idx ] - (float) $meta['preBalances'][ $idx ] ) / 1e9;
			if ( self::amount_in_band( $delta, $min, $max ) ) {
				return (string) $txid;
			}
		}

		return false;
	}

	/**
	 * Compute SPL token balance delta for owner.
	 *
	 * @param array  $pre      Pre balances.
	 * @param array  $post     Post balances.
	 * @param string $owner    Owner address.
	 * @param string $mint     Mint address.
	 * @return float
	 */
	private static function solana_token_delta( $pre, $post, $owner, $mint ) {
		$get = static function ( $list, $owner, $mint ) {
			foreach ( (array) $list as $row ) {
				if ( empty( $row['mint'] ) || empty( $row['owner'] ) ) {
					continue;
				}
				if ( $row['mint'] === $mint && $row['owner'] === $owner ) {
					$ui = isset( $row['uiTokenAmount']['uiAmount'] ) ? (float) $row['uiTokenAmount']['uiAmount'] : 0;
					return $ui;
				}
			}
			return 0.0;
		};
		return $get( $post, $owner, $mint ) - $get( $pre, $owner, $mint );
	}

	/**
	 * TRON / TRC20 verification via TronGrid.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min amount.
	 * @param int    $since   Since timestamp (seconds).
	 * @param array  $coin    Coin def.
	 * @return bool
	 */
	private static function check_tron( $address, $min, $max, $since, array $coin ) {
		$tron_headers = array();
		$tron_key     = Xdwp_Settings::get( 'trongrid_api_key', '' );
		if ( $tron_key ) {
			$tron_headers['TRON-PRO-API-KEY'] = $tron_key;
		}

		if ( 'trc20' === $coin['type'] && ! empty( $coin['contract'] ) ) {
			$url = sprintf(
				'https://api.trongrid.io/v1/accounts/%s/transactions/trc20?only_to=true&only_confirmed=true&limit=100&contract_address=%s',
				rawurlencode( $address ),
				rawurlencode( $coin['contract'] )
			);
			$response = self::http_get( $url, $tron_headers );
			if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
				return false;
			}
			foreach ( $response['data'] as $tx ) {
				$to = '';
				if ( ! empty( $tx['to'] ) ) {
					$to = (string) $tx['to'];
				} elseif ( ! empty( $tx['to_address'] ) ) {
					$to = (string) $tx['to_address'];
				}
				if ( '' === $to || 0 !== strcasecmp( $to, $address ) ) {
					continue;
				}
				$contract = '';
				if ( ! empty( $tx['token_info']['address'] ) ) {
					$contract = (string) $tx['token_info']['address'];
				} elseif ( ! empty( $tx['contract_address'] ) ) {
					$contract = (string) $tx['contract_address'];
				}
				if ( '' === $contract || 0 !== strcasecmp( $contract, (string) $coin['contract'] ) ) {
					continue;
				}
				$time = isset( $tx['block_timestamp'] ) ? (int) floor( $tx['block_timestamp'] / 1000 ) : 0;
				if ( $time < $since ) {
					continue;
				}
				if ( ! self::tron_tx_confirmed( $tx ) ) {
					continue;
				}
				$value_raw = isset( $tx['value'] ) ? $tx['value'] : '';
				if ( self::raw_amount_in_band( $value_raw, (int) $coin['decimals'], $min, $max ) ) {
					return ! empty( $tx['transaction_id'] ) ? (string) $tx['transaction_id'] : ( ! empty( $tx['txID'] ) ? (string) $tx['txID'] : false );
				}
			}
			return false;
		}

		$url      = sprintf( 'https://api.trongrid.io/v1/accounts/%s/transactions?only_to=true&only_confirmed=true&limit=100', rawurlencode( $address ) );
		$response = self::http_get( $url, $tron_headers );
		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return false;
		}
		foreach ( $response['data'] as $tx ) {
			// Native TRX must be a TransferContract (fail closed when type is missing).
			$contract_type = '';
			if ( ! empty( $tx['raw_data']['contract'][0]['type'] ) ) {
				$contract_type = (string) $tx['raw_data']['contract'][0]['type'];
			}
			if ( 'TransferContract' !== $contract_type ) {
				continue;
			}
			$to = '';
			if ( ! empty( $tx['to'] ) ) {
				$to = (string) $tx['to'];
			} elseif ( ! empty( $tx['to_address'] ) ) {
				$to = (string) $tx['to_address'];
			} elseif ( ! empty( $tx['raw_data']['contract'][0]['parameter']['value']['to_address'] ) ) {
				$to = (string) $tx['raw_data']['contract'][0]['parameter']['value']['to_address'];
			}
			if ( ! self::tron_destination_matches( $to, $address ) ) {
				continue;
			}
			$time = isset( $tx['block_timestamp'] ) ? (int) floor( $tx['block_timestamp'] / 1000 ) : 0;
			if ( $time < $since ) {
				continue;
			}
			if ( ! self::tron_tx_confirmed( $tx ) ) {
				continue;
			}
			$raw = 0;
			if ( ! empty( $tx['raw_data']['contract'][0]['parameter']['value']['amount'] ) ) {
				$raw = $tx['raw_data']['contract'][0]['parameter']['value']['amount'];
			}
			if ( self::raw_amount_in_band( (string) $raw, 6, $min, $max ) ) {
				return ! empty( $tx['txID'] ) ? (string) $tx['txID'] : ( ! empty( $tx['transaction_id'] ) ? (string) $tx['transaction_id'] : false );
			}
		}
		return false;
	}

	/**
	 * Whether a TronGrid destination equals our base58 address (accepts T… or 41… hex).
	 *
	 * @param string $candidate To field from API.
	 * @param string $address   Expected base58 address.
	 * @return bool
	 */
	private static function tron_destination_matches( $candidate, $address ) {
		$candidate = trim( (string) $candidate );
		$address   = trim( (string) $address );
		if ( '' === $candidate || '' === $address ) {
			return false;
		}
		if ( 0 === strcasecmp( $candidate, $address ) ) {
			return true;
		}
		// Hex form (41 + 20 bytes) — convert to base58check before comparing.
		$hex = preg_replace( '/^0x/i', '', $candidate );
		if ( is_string( $hex ) && preg_match( '/^41[0-9a-fA-F]{40}$/', $hex ) ) {
			$base58 = self::tron_hex_to_base58( $hex );
			return ( '' !== $base58 && 0 === strcasecmp( $base58, $address ) );
		}
		return false;
	}

	/**
	 * Convert a TRON hex address (41…) to base58check (T…).
	 *
	 * @param string $hex Hex address without 0x.
	 * @return string Base58 address or empty on failure.
	 */
	private static function tron_hex_to_base58( $hex ) {
		$hex = strtolower( (string) $hex );
		if ( ! preg_match( '/^41[0-9a-f]{40}$/', $hex ) ) {
			return '';
		}
		$bin = hex2bin( $hex );
		if ( false === $bin || 21 !== strlen( $bin ) ) {
			return '';
		}
		$hash0    = hash( 'sha256', $bin, true );
		$hash1    = hash( 'sha256', $hash0, true );
		$checksum = substr( $hash1, 0, 4 );
		return self::base58_encode( $bin . $checksum );
	}

	/**
	 * Bitcoin/TRON base58 encode (no leading-zero special-case beyond TRON 21-byte payloads).
	 *
	 * @param string $data Binary.
	 * @return string
	 */
	private static function base58_encode( $data ) {
		$alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
		$bytes    = array_values( unpack( 'C*', $data ) );
		if ( empty( $bytes ) ) {
			return '';
		}

		$digits = array( 0 );
		foreach ( $bytes as $byte ) {
			$carry = $byte;
			foreach ( $digits as $i => $digit ) {
				$carry       = $carry + ( $digit << 8 );
				$digits[ $i ] = $carry % 58;
				$carry       = intdiv( $carry, 58 );
			}
			while ( $carry > 0 ) {
				$digits[] = $carry % 58;
				$carry    = intdiv( $carry, 58 );
			}
		}

		// Preserve leading zero bytes as '1'.
		$encoded = '';
		foreach ( $bytes as $byte ) {
			if ( 0 !== $byte ) {
				break;
			}
			$encoded .= '1';
		}
		for ( $i = count( $digits ) - 1; $i >= 0; $i-- ) {
			$encoded .= $alphabet[ $digits[ $i ] ];
		}
		return $encoded;
	}

	/**
	 * Whether a TronGrid tx row is confirmed enough for our min_confirmations setting.
	 *
	 * TRC20 list rows often omit block height; resolve via gettransactioninfobyid.
	 * only_confirmed=true list responses are already irreversible on TronGrid.
	 *
	 * @param array $tx Tx row.
	 * @return bool
	 */
	private static function tron_tx_confirmed( array $tx ) {
		$need = self::min_confirmations();
		// Never accept rows explicitly marked unconfirmed (even at 0-conf).
		if ( isset( $tx['confirmed'] ) && ! $tx['confirmed'] ) {
			return false;
		}
		if ( $need <= 0 ) {
			return true;
		}
		// Prefer explicit confirmation count when TronGrid provides it.
		if ( isset( $tx['confirmations'] ) ) {
			return self::confirmations_ok( $tx['confirmations'] );
		}
		$tx_block = 0;
		if ( isset( $tx['block'] ) ) {
			$tx_block = (int) $tx['block'];
		} elseif ( isset( $tx['blockNumber'] ) ) {
			$tx_block = (int) $tx['blockNumber'];
		} elseif ( isset( $tx['block_number'] ) ) {
			$tx_block = (int) $tx['block_number'];
		}
		if ( $tx_block <= 0 ) {
			$txid = '';
			if ( ! empty( $tx['transaction_id'] ) ) {
				$txid = (string) $tx['transaction_id'];
			} elseif ( ! empty( $tx['txID'] ) ) {
				$txid = (string) $tx['txID'];
			}
			if ( $txid ) {
				$tx_block = self::tron_tx_block_number( $txid );
			}
		}
		if ( $tx_block <= 0 ) {
			// Fail closed: without a block height we cannot prove confirmation depth.
			return false;
		}
		$tip = self::cached_tip(
			'tron',
			static function () {
				$res = self::http_post_json( 'https://api.trongrid.io/wallet/getnowblock', array() );
				if ( isset( $res['block_header']['raw_data']['number'] ) ) {
					return (int) $res['block_header']['raw_data']['number'];
				}
				return 0;
			}
		);
		return self::block_depth_ok( $tx_block, $tip );
	}

	/**
	 * Resolve TRON tx block height via TronGrid full-node API.
	 *
	 * @param string $txid Transaction id.
	 * @return int
	 */
	private static function tron_tx_block_number( $txid ) {
		$txid = (string) $txid;
		if ( '' === $txid ) {
			return 0;
		}
		static $cache = array();
		if ( isset( $cache[ $txid ] ) ) {
			return $cache[ $txid ];
		}
		$res   = self::http_post_json(
			'https://api.trongrid.io/wallet/gettransactioninfobyid',
			array( 'value' => $txid )
		);
		$block = isset( $res['blockNumber'] ) ? (int) $res['blockNumber'] : 0;
		$cache[ $txid ] = $block;
		return $block;
	}

	/**
	 * Ripple (XRP Ledger) native XRP payments only.
	 *
	 * @param string $address Classic address (r...).
	 * @param float  $min     Min XRP.
	 * @param float  $max     Max XRP.
	 * @param int    $since   Since timestamp.
	 * @return string|false
	 */
	private static function check_xrp( $address, $min, $max, $since ) {
		$url      = sprintf( 'https://api.xrpscan.com/api/v1/account/%s/transactions?type=Payment&limit=25', rawurlencode( $address ) );
		$response = self::http_get( $url );
		if ( ! is_array( $response ) ) {
			return false;
		}
		$list = isset( $response['transactions'] ) ? $response['transactions'] : $response;
		if ( ! is_array( $list ) ) {
			return false;
		}
		foreach ( $list as $tx ) {
			$time = 0;
			if ( ! empty( $tx['date'] ) ) {
				$time = is_numeric( $tx['date'] ) ? (int) $tx['date'] : strtotime( $tx['date'] );
			} elseif ( ! empty( $tx['close_time_iso'] ) ) {
				$time = strtotime( $tx['close_time_iso'] );
			}
			if ( ! $time || $time < $since ) {
				continue;
			}
			// Require tesSUCCESS when present; never accept validated===false even if result says success.
			$hash_ok       = ! empty( $tx['hash'] ) || ! empty( $tx['tx']['hash'] );
			$has_result    = isset( $tx['meta']['TransactionResult'] );
			$has_validated = isset( $tx['validated'] );
			$validated     = $hash_ok;
			if ( $has_validated && ! $tx['validated'] ) {
				$validated = false;
			} elseif ( $has_result ) {
				$validated = $validated && ( 'tesSUCCESS' === $tx['meta']['TransactionResult'] );
			} elseif ( $has_validated ) {
				$validated = $validated && (bool) $tx['validated'];
			} else {
				$validated = false;
			}
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			$dest = '';
			if ( ! empty( $tx['Destination'] ) ) {
				$dest = $tx['Destination'];
			} elseif ( ! empty( $tx['tx']['Destination'] ) ) {
				$dest = $tx['tx']['Destination'];
			}
			if ( $dest && 0 !== strcasecmp( $dest, $address ) ) {
				continue;
			}
			if ( ! $dest ) {
				continue;
			}
			$amount = 0;
			$raw    = isset( $tx['Amount'] ) ? $tx['Amount'] : ( isset( $tx['tx']['Amount'] ) ? $tx['tx']['Amount'] : 0 );
			// Native XRP only (drops as numeric string). Skip issued IOUs.
			if ( is_numeric( $raw ) ) {
				$amount = ( (float) $raw ) / 1e6;
			} else {
				continue;
			}
			if ( self::amount_in_band( $amount, $min, $max ) ) {
				return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : ( ! empty( $tx['tx']['hash'] ) ? (string) $tx['tx']['hash'] : false );
			}
		}
		return false;
	}

	/**
	 * Stellar payments to account.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min amount.
	 * @param int    $since   Since timestamp.
	 * @return bool
	 */
	private static function check_stellar( $address, $min, $max, $since ) {
		$url      = sprintf( 'https://horizon.stellar.org/accounts/%s/payments?order=desc&limit=50', rawurlencode( $address ) );
		$response = self::http_get( $url );
		if ( empty( $response['_embedded']['records'] ) || ! is_array( $response['_embedded']['records'] ) ) {
			return false;
		}
		foreach ( $response['_embedded']['records'] as $tx ) {
			if ( empty( $tx['type'] ) || 'payment' !== $tx['type'] ) {
				continue;
			}
			if ( empty( $tx['to'] ) || 0 !== strcasecmp( $tx['to'], $address ) ) {
				continue;
			}
			$time = ! empty( $tx['created_at'] ) ? strtotime( $tx['created_at'] ) : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			$validated = isset( $tx['transaction_successful'] ) && $tx['transaction_successful']
				&& ( ! empty( $tx['transaction_hash'] ) || ! empty( $tx['id'] ) );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			if ( ! empty( $tx['asset_type'] ) && 'native' !== $tx['asset_type'] ) {
				continue;
			}
			$amount = isset( $tx['amount'] ) ? (float) $tx['amount'] : 0;
			if ( self::amount_in_band( $amount, $min, $max ) ) {
				return ! empty( $tx['transaction_hash'] ) ? (string) $tx['transaction_hash'] : ( ! empty( $tx['id'] ) ? (string) $tx['id'] : false );
			}
		}
		return false;
	}

	/**
	 * HTTP GET JSON helper.
	 *
	 * @param string               $url            URL.
	 * @param array<string,string> $extra_headers Extra headers.
	 * @return array|null
	 */
	private static function http_get( $url, $extra_headers = array() ) {
		$headers = array_merge(
			array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			),
			is_array( $extra_headers ) ? $extra_headers : array()
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * HTTP GET that returns a plain integer body (e.g. Blockstream tip height).
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private static function http_get_int( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'text/plain, application/json',
					'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return 0;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return 0;
		}
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( is_numeric( $body ) ) {
			return (int) $body;
		}
		$json = json_decode( $body, true );
		if ( is_numeric( $json ) ) {
			return (int) $json;
		}
		return 0;
	}

	/**
	 * HTTP POST JSON helper.
	 *
	 * @param string               $url            URL.
	 * @param array                $body           Body.
	 * @param array<string,string> $extra_headers Extra headers.
	 * @return array|null
	 */
	private static function http_post_json( $url, array $body, array $extra_headers = array() ) {
		$headers = array_merge(
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			),
			is_array( $extra_headers ) ? $extra_headers : array()
		);
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Algorand — free AlgoNode indexer.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_algo( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://mainnet-idx.algonode.cloud/v2/accounts/%s/transactions?limit=30&tx-type=pay',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		if ( empty( $response['transactions'] ) || ! is_array( $response['transactions'] ) ) {
			return false;
		}

		$need = self::min_confirmations();
		$tip  = 0;
		if ( $need > 0 ) {
			$tip = self::cached_tip(
				'algo',
				static function () {
					$status = self::http_get( 'https://mainnet-idx.algonode.cloud/v2/status' );
					if ( isset( $status['round'] ) ) {
						return (int) $status['round'];
					}
					if ( isset( $status['last-round'] ) ) {
						return (int) $status['last-round'];
					}
					return 0;
				}
			);
			if ( $tip <= 0 ) {
				return false;
			}
		}

		foreach ( $response['transactions'] as $tx ) {
			$time = isset( $tx['round-time'] ) ? (int) $tx['round-time'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( $need > 0 ) {
				$confirmed_round = isset( $tx['confirmed-round'] ) ? (int) $tx['confirmed-round'] : 0;
				if ( ! self::block_depth_ok( $confirmed_round, $tip ) ) {
					continue;
				}
			}
			$pay = isset( $tx['payment-transaction'] ) ? $tx['payment-transaction'] : array();
			if ( empty( $pay['receiver'] ) || 0 !== strcasecmp( $pay['receiver'], $address ) ) {
				continue;
			}
			$raw = isset( $pay['amount'] ) ? (string) $pay['amount'] : '0';
			if ( self::raw_amount_in_band( $raw, 6, $min, $max ) ) {
				return ! empty( $tx['id'] ) ? (string) $tx['id'] : false;
			}
		}
		return false;
	}

	/**
	 * Hedera — public Mirror Node.
	 *
	 * @param string $address Account ID (0.0.x) or alias.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_hbar( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://mainnet-public.mirrornode.hedera.com/api/v1/transactions?account.id=%s&transactiontype=CRYPTOTRANSFER&limit=25&order=desc',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		if ( empty( $response['transactions'] ) || ! is_array( $response['transactions'] ) ) {
			return false;
		}
		foreach ( $response['transactions'] as $tx ) {
			$time = 0;
			if ( ! empty( $tx['consensus_timestamp'] ) ) {
				$time = (int) floor( (float) $tx['consensus_timestamp'] );
			}
			if ( ! $time || $time < $since ) {
				continue;
			}
			$validated = ! empty( $tx['consensus_timestamp'] )
				&& isset( $tx['result'] )
				&& 'SUCCESS' === strtoupper( (string) $tx['result'] );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			if ( empty( $tx['transfers'] ) || ! is_array( $tx['transfers'] ) ) {
				continue;
			}
			$received = 0;
			foreach ( $tx['transfers'] as $tr ) {
				if ( empty( $tr['account'] ) || 0 !== strcasecmp( (string) $tr['account'], $address ) ) {
					continue;
				}
				$amt = isset( $tr['amount'] ) ? (int) $tr['amount'] : 0;
				if ( $amt > 0 ) {
					$received += $amt;
				}
			}
			if ( $received <= 0 ) {
				continue;
			}
			if ( self::raw_amount_in_band( (string) $received, 8, $min, $max ) ) {
				return ! empty( $tx['transaction_id'] ) ? (string) $tx['transaction_id'] : false;
			}
		}
		return false;
	}

	/**
	 * NEAR — NearBlocks public API.
	 *
	 * @param string $address Account.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_near( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://api.nearblocks.io/v1/account/%s/txns?per_page=25&order=desc',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		$list     = array();
		if ( ! empty( $response['txns'] ) && is_array( $response['txns'] ) ) {
			$list = $response['txns'];
		} elseif ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$list = $response['data'];
		}
		foreach ( $list as $tx ) {
			$time = 0;
			if ( ! empty( $tx['block_timestamp'] ) ) {
				// Often nanoseconds.
				$ts = (float) $tx['block_timestamp'];
				$time = ( $ts > 1e12 ) ? (int) floor( $ts / 1e9 ) : (int) $ts;
			} elseif ( ! empty( $tx['block_time'] ) ) {
				$time = (int) $tx['block_time'];
			}
			if ( ! $time || $time < $since ) {
				continue;
			}
			$receiver = '';
			if ( ! empty( $tx['receiver_account_id'] ) ) {
				$receiver = $tx['receiver_account_id'];
			} elseif ( ! empty( $tx['receiver'] ) ) {
				$receiver = $tx['receiver'];
			}
			if ( ! $receiver || 0 !== strcasecmp( $receiver, $address ) ) {
				continue;
			}
			$status = isset( $tx['outcomes']['status'] ) ? $tx['outcomes']['status'] : ( isset( $tx['status'] ) ? $tx['status'] : null );
			// Require explicit success (boolean true or SuccessValue/SuccessReceiptId). Missing/false/Failure fail closed.
			$status_ok = false;
			if ( true === $status || 1 === $status ) {
				$status_ok = true;
			} elseif ( is_array( $status ) ) {
				$status_ok = ( isset( $status['SuccessValue'] ) || isset( $status['SuccessReceiptId'] ) )
					&& ! isset( $status['Failure'] );
			}
			$validated = $status_ok && ( ! empty( $tx['transaction_hash'] ) || ! empty( $tx['hash'] ) );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			// Prefer non-zero deposit fields (actions_agg.deposit can be 0 while actions[].deposit is real).
			$raw                = '0';
			$deposit_candidates = array();
			if ( ! empty( $tx['actions'] ) && is_array( $tx['actions'] ) ) {
				foreach ( $tx['actions'] as $action ) {
					if ( isset( $action['deposit'] ) ) {
						$deposit_candidates[] = (string) $action['deposit'];
					} elseif ( isset( $action['Transfer']['deposit'] ) ) {
						$deposit_candidates[] = (string) $action['Transfer']['deposit'];
					}
				}
			}
			if ( isset( $tx['deposit'] ) ) {
				$deposit_candidates[] = (string) $tx['deposit'];
			}
			if ( isset( $tx['actions_agg']['deposit'] ) ) {
				$deposit_candidates[] = (string) $tx['actions_agg']['deposit'];
			}
			foreach ( $deposit_candidates as $candidate ) {
				$candidate = preg_replace( '/\D/', '', (string) $candidate );
				if ( ! is_string( $candidate ) || '' === $candidate || preg_match( '/^0+$/', $candidate ) ) {
					continue;
				}
				$raw = $candidate;
				break;
			}
			if ( '0' === $raw || '' === $raw ) {
				continue;
			}
			if ( self::raw_amount_in_band( $raw, 24, $min, $max ) ) {
				if ( ! empty( $tx['transaction_hash'] ) ) {
					return (string) $tx['transaction_hash'];
				}
				if ( ! empty( $tx['hash'] ) ) {
					return (string) $tx['hash'];
				}
			}
		}
		return false;
	}

	/**
	 * Cosmos Hub ATOM — public LCD.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_atom( $address, $min, $max, $since ) {
		// Strict bech32-ish chars only — reject query injection via crafted wallet saves.
		if ( ! preg_match( '/^[a-z0-9]{10,128}$/', $address ) ) {
			return false;
		}
		$url = add_query_arg(
			array(
				'query'            => "transfer.recipient='" . $address . "'",
				'order_by'         => 'ORDER_BY_DESC',
				'pagination.limit' => 20,
			),
			'https://cosmos-rest.publicnode.com/cosmos/tx/v1beta1/txs'
		);
		$response = self::http_get( $url );
		if ( empty( $response['tx_responses'] ) || ! is_array( $response['tx_responses'] ) ) {
			return false;
		}
		foreach ( $response['tx_responses'] as $tx ) {
			$time = ! empty( $tx['timestamp'] ) ? strtotime( $tx['timestamp'] ) : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			$code      = isset( $tx['code'] ) ? (int) $tx['code'] : -1;
			$height    = isset( $tx['height'] ) ? (int) $tx['height'] : 0;
			$validated = ( 0 === $code ) && ( $height > 0 || ! empty( $tx['txhash'] ) );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			$events = isset( $tx['events'] ) ? $tx['events'] : array();
			$amount_uatom = 0;
			foreach ( $events as $event ) {
				if ( empty( $event['type'] ) || 'transfer' !== $event['type'] ) {
					continue;
				}
				$attrs = array();
				if ( ! empty( $event['attributes'] ) && is_array( $event['attributes'] ) ) {
					foreach ( $event['attributes'] as $attr ) {
						$key = isset( $attr['key'] ) ? (string) $attr['key'] : '';
						$val = isset( $attr['value'] ) ? (string) $attr['value'] : '';
						$attrs[ $key ] = $val;
						// Some Cosmos LCD endpoints base64-encode attribute keys/values.
						$decoded_key = self::maybe_base64_decode( $key );
						$decoded_val = self::maybe_base64_decode( $val );
						if ( $decoded_key !== $key || $decoded_val !== $val ) {
							$attrs[ $decoded_key ] = $decoded_val;
						}
					}
				}
				$recipient = isset( $attrs['recipient'] ) ? $attrs['recipient'] : '';
				if ( $recipient && 0 !== strcasecmp( $recipient, $address ) ) {
					continue;
				}
				if ( empty( $attrs['amount'] ) ) {
					continue;
				}
				if ( preg_match( '/(\d+)uatom/', $attrs['amount'], $m ) ) {
					$amount_uatom += (int) $m[1];
				}
			}
			if ( $amount_uatom <= 0 ) {
				continue;
			}
			if ( self::raw_amount_in_band( (string) $amount_uatom, 6, $min, $max ) ) {
				return ! empty( $tx['txhash'] ) ? (string) $tx['txhash'] : false;
			}
		}
		return false;
	}

	/**
	 * MultiversX EGLD — public API.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_egld( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://api.multiversx.com/accounts/%s/transactions?status=success&size=25',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		if ( ! is_array( $response ) ) {
			return false;
		}
		foreach ( $response as $tx ) {
			$time = isset( $tx['timestamp'] ) ? (int) $tx['timestamp'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( empty( $tx['receiver'] ) || 0 !== strcasecmp( $tx['receiver'], $address ) ) {
				continue;
			}
			$validated = isset( $tx['status'] ) && 'success' === strtolower( (string) $tx['status'] )
				&& ! empty( $tx['txHash'] );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			$raw = isset( $tx['value'] ) ? (string) $tx['value'] : '0';
			if ( self::raw_amount_in_band( $raw, 18, $min, $max ) ) {
				return ! empty( $tx['txHash'] ) ? (string) $tx['txHash'] : false;
			}
		}
		return false;
	}

	/**
	 * Filecoin — Filfox explorer API.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_fil( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://filfox.info/api/v1/address/%s/messages?pageSize=25&page=0',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		$list     = array();
		if ( ! empty( $response['messages'] ) && is_array( $response['messages'] ) ) {
			$list = $response['messages'];
		}
		foreach ( $list as $tx ) {
			$time = isset( $tx['timestamp'] ) ? (int) $tx['timestamp'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			$to = isset( $tx['to'] ) ? $tx['to'] : '';
			if ( ! $to || 0 !== strcasecmp( $to, $address ) ) {
				continue;
			}
			$validated = isset( $tx['receipt']['exitCode'] ) && 0 === (int) $tx['receipt']['exitCode']
				&& ! empty( $tx['cid'] );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			// Filfox value is often in attoFIL (1 FIL = 1e18).
			$raw = isset( $tx['value'] ) ? (string) $tx['value'] : '0';
			if ( false !== strpos( $raw, '.' ) ) {
				if ( self::amount_in_band( (float) $raw, $min, $max ) ) {
					return ! empty( $tx['cid'] ) ? (string) $tx['cid'] : false;
				}
			} elseif ( self::raw_amount_in_band( $raw, 18, $min, $max ) ) {
				return ! empty( $tx['cid'] ) ? (string) $tx['cid'] : false;
			}
		}
		return false;
	}

	/**
	 * EOS — Hyperion history API.
	 *
	 * @param string $address Account.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_eos( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://eos.greymass.com/v2/history/get_actions?account=%s&filter=eosio.token:transfer&skip=0&limit=30',
			rawurlencode( $address )
		);
		$response = self::http_get( $url );
		if ( empty( $response['actions'] ) || ! is_array( $response['actions'] ) ) {
			return false;
		}
		foreach ( $response['actions'] as $row ) {
			$act = isset( $row['act'] ) ? $row['act'] : array();
			$data = isset( $act['data'] ) ? $act['data'] : array();
			$time = 0;
			if ( ! empty( $row['timestamp'] ) ) {
				$time = strtotime( $row['timestamp'] );
			} elseif ( ! empty( $row['@timestamp'] ) ) {
				$time = strtotime( $row['@timestamp'] );
			}
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( empty( $data['to'] ) || 0 !== strcasecmp( $data['to'], $address ) ) {
				continue;
			}
			$validated = isset( $row['irreversible'] ) && $row['irreversible']
				&& ( ! empty( $row['trx_id'] ) || ! empty( $row['trxid'] ) );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			$qty = isset( $data['quantity'] ) ? $data['quantity'] : '';
			if ( ! preg_match( '/^([0-9.]+)\s+EOS$/', trim( $qty ), $m ) ) {
				continue;
			}
			if ( self::amount_in_band( (float) $m[1], $min, $max ) ) {
				if ( ! empty( $row['trx_id'] ) ) {
					return (string) $row['trx_id'];
				}
				if ( ! empty( $row['trxid'] ) ) {
					return (string) $row['trxid'];
				}
			}
		}
		return false;
	}

	/**
	 * Polkadot — Subscan transfers API (optional free API key improves limits).
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_dot( $address, $min, $max, $since ) {
		$headers = array();
		$api_key = Xdwp_Settings::get( 'subscan_api_key', '' );
		if ( $api_key ) {
			$headers['x-api-key'] = $api_key;
		}
		$body = array(
			'address' => $address,
			'row'     => 25,
			'page'    => 0,
		);
		$response = self::http_post_json_headers(
			'https://polkadot.api.subscan.io/api/v2/scan/transfers',
			$body,
			$headers
		);
		if ( empty( $response['data']['transfers'] ) || ! is_array( $response['data']['transfers'] ) ) {
			return false;
		}
		foreach ( $response['data']['transfers'] as $tx ) {
			$time = isset( $tx['block_timestamp'] ) ? (int) $tx['block_timestamp'] : 0;
			if ( ! $time || $time < $since ) {
				continue;
			}
			if ( empty( $tx['to'] ) || 0 !== strcasecmp( $tx['to'], $address ) ) {
				continue;
			}
			// Require explicit success + hash; confirmations alone must not accept failed transfers.
			if ( empty( $tx['hash'] ) || empty( $tx['success'] ) ) {
				continue;
			}
			if ( isset( $tx['confirmations'] ) ) {
				if ( ! self::confirmations_ok( $tx['confirmations'] ) ) {
					continue;
				}
			} elseif ( ! self::soft_finality_ok( true ) ) {
				continue;
			}
			// Subscan amount is often human-readable string; or planck via amount_v2.
			if ( isset( $tx['amount'] ) && is_numeric( $tx['amount'] ) ) {
				if ( self::amount_in_band( (float) $tx['amount'], $min, $max ) ) {
					return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : false;
				}
			}
			if ( ! empty( $tx['amount_v2'] ) && self::raw_amount_in_band( (string) $tx['amount_v2'], 10, $min, $max ) ) {
				return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : false;
			}
		}
		return false;
	}

	/**
	 * Zilliqa — ViewBlock public address txs.
	 *
	 * @param string $address Address.
	 * @param float  $min     Min.
	 * @param float  $max     Max.
	 * @param int    $since   Since.
	 * @return string|false
	 */
	private static function check_zil( $address, $min, $max, $since ) {
		$url = sprintf(
			'https://api.viewblock.io/v1/zilliqa/addresses/%s/txs?network=mainnet&page=1',
			rawurlencode( $address )
		);
		$headers = array();
		$vb_key  = Xdwp_Settings::get( 'viewblock_api_key', '' );
		if ( $vb_key ) {
			$headers['X-APIKEY'] = $vb_key;
		}
		$response = self::http_get( $url, $headers );
		$list     = array();
		if ( isset( $response['docs'] ) && is_array( $response['docs'] ) ) {
			$list = $response['docs'];
		} elseif ( is_array( $response ) && isset( $response[0] ) ) {
			$list = $response;
		}
		foreach ( $list as $tx ) {
			$time = 0;
			if ( ! empty( $tx['timestamp'] ) ) {
				$time = (int) $tx['timestamp'];
				if ( $time > 1e12 ) {
					$time = (int) floor( $time / 1000 );
				}
			}
			if ( ! $time || $time < $since ) {
				continue;
			}
			$to = '';
			if ( ! empty( $tx['to'] ) ) {
				$to = is_array( $tx['to'] ) ? ( isset( $tx['to'][0] ) ? $tx['to'][0] : '' ) : $tx['to'];
			}
			if ( ! $to || 0 !== strcasecmp( $to, $address ) ) {
				continue;
			}
			$hash_ok   = ! empty( $tx['hash'] ) || ! empty( $tx['ID'] );
			$has_receipt = isset( $tx['receiptSuccess'] );
			$has_success = isset( $tx['success'] );
			// Require at least one success flag; if both exist, both must be true (no OR bypass).
			$validated = $hash_ok && ( $has_receipt || $has_success )
				&& ( ! $has_receipt || $tx['receiptSuccess'] )
				&& ( ! $has_success || $tx['success'] );
			if ( ! self::soft_finality_ok( $validated ) ) {
				continue;
			}
			if ( ! isset( $tx['value'] ) || ! is_numeric( $tx['value'] ) ) {
				continue;
			}
			// ViewBlock values are Qa (10^12). Always interpret as chain units — never guess human ZIL.
			$raw = preg_replace( '/\D/', '', (string) $tx['value'] );
			if ( ! is_string( $raw ) || '' === $raw || preg_match( '/^0+$/', $raw ) ) {
				continue;
			}
			if ( self::raw_amount_in_band( $raw, 12, $min, $max ) ) {
				return ! empty( $tx['hash'] ) ? (string) $tx['hash'] : ( ! empty( $tx['ID'] ) ? (string) $tx['ID'] : false );
			}
		}
		return false;
	}

	/**
	 * HTTP POST JSON with extra headers.
	 *
	 * @param string $url     URL.
	 * @param array  $body    Body.
	 * @param array  $headers Extra headers.
	 * @return array|null
	 */
	private static function http_post_json_headers( $url, array $body, array $headers = array() ) {
		$headers = array_merge(
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			),
			$headers
		);
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}
}
