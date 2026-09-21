<?php
/**
 * Money that arrived and belongs to no order.
 *
 * A gateway only ever asks "has the amount this order expects turned up?". That is the right
 * question for confirming a payment and the wrong one for finding money nobody is looking for:
 * a customer paying from an address they saved months ago, a second transfer for an order
 * already settled, a payment for an order that was cancelled last week. None of it is visible
 * to a matcher, because a matcher is only ever asked about one expected amount.
 *
 * So this asks the other question. It reads what has arrived at the shop's own addresses and
 * subtracts every transfer the plugin can account for. Whatever is left is money the merchant
 * has and does not know about.
 *
 * Two things it deliberately will not do. It will not say "nothing unmatched" about a chain it
 * could not read — an all-clear that quietly skipped half the coins is worse than no screen at
 * all, so every coin is reported as checked or as unreadable, by name. And it changes nothing:
 * no order is credited, cancelled or altered by a scan. Deciding what an unexplained transfer
 * means is a person's job.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finding transfers that no order accounts for.
 */
class Xdwp_Reconcile {

	/**
	 * Where a finished scan is kept, so opening the screen again does not re-read every chain.
	 */
	const CACHE_KEY = 'xdwp_reconcile';

	/**
	 * How long that result stands before the screen offers to look again.
	 */
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * How far back to read. Beyond this a transfer is somebody's accounting problem, not a
	 * payment anyone is still waiting on.
	 */
	const LOOKBACK = 30 * DAY_IN_SECONDS;

	/**
	 * How many addresses one scan will read. Each costs explorer calls against the merchant's
	 * own quota, and a shop with an extended key has an address per order.
	 */
	const MAX_ADDRESSES = 30;

	/**
	 * Whether a scan is worth offering at all.
	 *
	 * @return bool
	 */
	public static function available() {
		foreach ( array_keys( Xdwp_Coins::get_payable() ) as $coin_id ) {
			$coin = Xdwp_Coins::get( $coin_id );
			if ( $coin && Xdwp_Verifier::can_list( isset( $coin['verifier'] ) ? $coin['verifier'] : '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The last scan, or null if there has not been one.
	 *
	 * @return array|null
	 */
	public static function last() {
		$cached = get_transient( self::CACHE_KEY );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Forget the last scan.
	 */
	public static function forget() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Read the shop's addresses and report what nothing accounts for.
	 *
	 * @return array{ran:int,coins:array,unmatched:array,addresses:int,truncated:bool}
	 */
	public static function scan() {
		$since     = time() - self::LOOKBACK;
		$claimed   = self::claimed_txids();
		$coins     = array();
		$unmatched = array();
		$looked    = 0;
		$truncated = false;

		foreach ( Xdwp_Coins::get_payable() as $coin_id => $coin ) {
			$verifier = isset( $coin['verifier'] ) ? (string) $coin['verifier'] : '';
			$symbol   = isset( $coin['symbol'] ) ? (string) $coin['symbol'] : $coin_id;

			if ( ! Xdwp_Verifier::can_list( $verifier ) ) {
				$coins[ $coin_id ] = array(
					'symbol'  => $symbol,
					'state'   => 'unreadable',
					'seen'    => 0,
					'unknown' => 0,
				);
				continue;
			}

			$addresses = self::addresses_for( $coin_id );
			if ( empty( $addresses ) ) {
				$coins[ $coin_id ] = array(
					'symbol'  => $symbol,
					'state'   => 'no_addresses',
					'seen'    => 0,
					'unknown' => 0,
				);
				continue;
			}

			$seen    = 0;
			$unknown = 0;
			$failed  = false;

			foreach ( $addresses as $address ) {
				if ( $looked >= self::MAX_ADDRESSES ) {
					$truncated = true;
					break 2;
				}
				++$looked;

				$transfers = Xdwp_Verifier::list_transfers( $coin, $address, $since );
				if ( null === $transfers ) {
					// The explorer would not answer. Not the same as "nothing arrived", and it
					// must not be reported as if it were.
					$failed = true;
					continue;
				}

				foreach ( $transfers as $transfer ) {
					++$seen;
					$txid = strtolower( (string) $transfer['txid'] );
					if ( '' === $txid || isset( $claimed[ $txid ] ) ) {
						continue;
					}
					++$unknown;
					$unmatched[] = array(
						'coin'    => $coin_id,
						'symbol'  => $symbol,
						'address' => $address,
						'txid'    => (string) $transfer['txid'],
						'amount'  => (string) $transfer['amount'],
						'time'    => (int) $transfer['time'],
						'url'     => (string) Xdwp_Coins::explorer_tx_url( $coin, (string) $transfer['txid'] ),
					);
				}
			}

			$coins[ $coin_id ] = array(
				'symbol'  => $symbol,
				'state'   => $failed ? 'partial' : 'checked',
				'seen'    => $seen,
				'unknown' => $unknown,
			);
		}

		usort(
			$unmatched,
			static function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);

		$result = array(
			'ran'       => time(),
			'coins'     => $coins,
			'unmatched' => $unmatched,
			'addresses' => $looked,
			'truncated' => $truncated,
		);

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Every address this shop could have been paid at for one coin.
	 *
	 * Both the fixed addresses and, for a shop deriving one per order, the addresses actually
	 * handed out — a transfer to an address nobody was ever given is not something this plugin
	 * can be asked about.
	 *
	 * @param string $coin_id Coin ID.
	 * @return array<int, string>
	 */
	private static function addresses_for( $coin_id ) {
		$addresses = Xdwp_Wallets::get_addresses( $coin_id );
		$addresses = is_array( $addresses ) ? $addresses : array();

		// Addresses given to recent orders, newest first: those are where money is plausibly
		// still arriving, and there can be thousands of older ones.
		$orders = Xdwp_Order_Query::get(
			array(
				'limit'          => 40,
				'status'         => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'payment_method' => XDWP_GATEWAY_ID,
				'return'         => 'objects',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_xdwp_coin',
						'value' => $coin_id,
					),
					array(
						'key'     => '_xdwp_address',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			$address = (string) Xdwp_Order::meta( $order, 'address' );
			if ( '' !== $address ) {
				$addresses[] = $address;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $addresses ) ) ) );
	}

	/**
	 * Every transaction this plugin can already account for, as a lookup.
	 *
	 * Includes the transfers that paid an order, the ones seen but not yet confirmed, late and
	 * part payments, what a customer told us they sent, and what a browser wallet reported —
	 * anything the merchant has already been shown. A transfer in this set is not a surprise.
	 *
	 * @return array<string, true>
	 */
	private static function claimed_txids() {
		$claimed = array();

		$orders = Xdwp_Order_Query::get(
			array(
				'limit'          => 500,
				'status'         => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'payment_method' => XDWP_GATEWAY_ID,
				'return'         => 'objects',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_xdwp_coin',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$fields = array( 'txid', 'seen_txid', 'late_txid', 'wallet_txid', 'customer_txid', 'refund_txid' );

		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			foreach ( $fields as $field ) {
				$value = strtolower( trim( (string) Xdwp_Order::meta( $order, $field ) ) );
				if ( '' !== $value ) {
					$claimed[ $value ] = true;
				}
			}
			// Part payments are recorded as a list, because there can be several.
			$partials = Xdwp_Verifier::partial_txids( $order );
			foreach ( is_array( $partials ) ? $partials : array() as $partial ) {
				$partial = strtolower( trim( (string) $partial ) );
				if ( '' !== $partial ) {
					$claimed[ $partial ] = true;
				}
			}
		}

		return $claimed;
	}
}
