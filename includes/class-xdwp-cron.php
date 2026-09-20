<?php
/**
 * WP-Cron payment polling and price refresh.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Cron
 */
class Xdwp_Cron {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'xdwp_check_payments', array( __CLASS__, 'check_pending_payments' ) );
		add_action( 'xdwp_refresh_prices', array( 'Xdwp_Prices', 'cron_refresh' ) );
	}

	/**
	 * How long a run is allowed to hold the mutex before a later tick may
	 * reclaim it as stale (seconds). Comfortably above the processing-time
	 * cap below so a healthy run never gets pre-empted by itself.
	 */
	const LOCK_TTL = 120;

	/**
	 * Stop picking up new orders after this many seconds so a single tick
	 * can't run indefinitely under `max_execution_time` pressure; anything
	 * left over is simply picked up by the next scheduled tick.
	 */
	const MAX_RUNTIME = 50;

	/**
	 * Poll awaiting crypto orders and verify on-chain.
	 */
	public static function check_pending_payments() {
		if ( 'yes' !== Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			return;
		}

		// Guard against overlapping runs: WordPress's own `doing_cron` lock is a
		// soft, time-based lock (60s default), not an "is a previous run still
		// alive" lock — sites where wp-cron.php is hit directly/frequently can
		// otherwise run this concurrently, multiplying outbound explorer-API
		// calls against the merchant's own rate limits and racing over the
		// same orders. Reuses the same real INSERT-only compare-and-set as the
		// payment lock (add_option() alone is not exclusive — see mark_paid()).
		$lock_key = 'xdwp_cron_running';
		// "<unix time>:<random>" — (int) still reads the age, and the random part lets this run
		// release only its own lock (see finally below).
		$now      = time() . ':' . wp_generate_password( 12, false );
		if ( ! Xdwp_Verifier::atomic_add_option( $lock_key, $now ) ) {
			$existing = Xdwp_Verifier::read_option_raw( $lock_key );
			if ( $existing && ( time() - (int) $existing ) < self::LOCK_TTL ) {
				return;
			}
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					$now,
					$lock_key,
					$existing
				)
			);
			Xdwp_Verifier::forget_option_cache( $lock_key );
			if ( 1 !== $updated ) {
				return;
			}
		}

		try {
			// A separate top-level meta_key/orderby=meta_value_num combined with
			// meta_query triggers WooCommerce's "not supported on the current
			// order datastore" doing_it_wrong notice under HPOS (harmless today
			// — verified the filter still applies correctly — but signals a
			// compatibility shim that could be removed later). Ordering by a
			// named meta_query clause is the officially-supported form for
			// both the legacy and HPOS order data stores.
			$orders = wc_get_orders(
				array(
					'limit'          => 100,
					'status'         => array( 'on-hold', 'pending' ),
					'payment_method' => XDWP_GATEWAY_ID,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation'       => 'AND',
						'status_clause'  => array(
							'key'     => '_xdwp_status',
							'value'   => array( 'awaiting', 'underpaid' ),
							'compare' => 'IN',
						),
						'started_clause' => array(
							'key'     => '_xdwp_started',
							'compare' => 'EXISTS',
						),
					),
					'orderby'        => array( 'started_clause' => 'ASC' ),
					'return'         => 'objects',
				)
			);

			$start = microtime( true );

			foreach ( (array) $orders as $order ) {
				if ( ( microtime( true ) - $start ) > self::MAX_RUNTIME ) {
					break;
				}

				Xdwp_Order::maybe_expire( $order );

				// Re-fetch in case expired.
				$order = wc_get_order( $order->get_id() );
				if ( ! $order || ! in_array( Xdwp_Order::meta( $order, 'status' ), array( 'awaiting', 'underpaid' ), true ) ) {
					continue;
				}

				if ( Xdwp_Verifier::verify_order( $order ) ) {
					Xdwp_Order::mark_paid( $order );
					continue;
				}

				self::maybe_send_reminder( wc_get_order( $order->get_id() ) );
			}

			if ( ( microtime( true ) - $start ) < self::MAX_RUNTIME - 10 ) {
				self::scan_late_payments( $start );
			}
		} finally {
			// Compare-and-delete: after a stale takeover, a slow earlier run must not remove the
			// newer run's lock and let a third run overlap it.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					$lock_key,
					$now
				)
			);
			wp_cache_delete( $lock_key, 'options' );
		}
	}

	/** Remind the customer this long before the payment window closes. */
	const REMINDER_LEAD = 15 * MINUTE_IN_SECONDS;

	/** How far back to look for payments that arrived after an order expired. */
	const LATE_LOOKBACK = 7 * DAY_IN_SECONDS;

	/** Re-check an expired order for a late payment at most this often. */
	const LATE_RECHECK = 6 * HOUR_IN_SECONDS;

	/** Expired orders checked per run (one explorer lookup each; some explorers need several calls). */
	const LATE_BATCH = 5;

	/**
	 * Send the "payment window closing" email once per order (and again after a partial
	 * payment restarts the window). Skipped for short windows, where it would arrive
	 * almost as soon as the order confirmation.
	 *
	 * @param WC_Order|false $order Order.
	 */
	private static function maybe_send_reminder( $order ) {
		if ( ! $order instanceof WC_Order || '' !== (string) Xdwp_Order::meta( $order, 'reminder_sent' ) ) {
			return;
		}
		$window = (int) Xdwp_Settings::get( 'payment_window', 60 ) * MINUTE_IN_SECONDS;
		if ( $window < 2 * self::REMINDER_LEAD ) {
			return;
		}
		$left = (int) Xdwp_Order::meta( $order, 'expires' ) - time();
		if ( $left <= 0 || $left > self::REMINDER_LEAD ) {
			return;
		}
		$order->update_meta_data( '_xdwp_reminder_sent', time() );
		$order->save();
		do_action( 'xdwp_send_payment_reminder', $order );
	}

	/**
	 * Look for payments that reached recently expired orders. Nothing is changed on the
	 * order: the transfer is reserved for it and the store owner is alerted to confirm it
	 * (goods may already be gone or restocked, so this stays a human decision).
	 *
	 * @param float $start Run start time.
	 */
	private static function scan_late_payments( $start ) {
		if ( 'yes' !== Xdwp_Settings::get( 'late_payment_scan', 'yes' ) ) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'limit'          => 30,
				'status'         => array( 'failed' ),
				'payment_method' => XDWP_GATEWAY_ID,
				'date_created'   => '>' . ( time() - self::LATE_LOOKBACK ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_xdwp_status',
						'value' => 'expired',
					),
					array(
						'key'     => '_xdwp_late_txid',
						'compare' => 'NOT EXISTS',
					),
					// Only orders not checked in the last LATE_RECHECK, so older expired orders are
					// reached too instead of the newest 30 being re-selected every run.
					array(
						'relation' => 'OR',
						array(
							'key'     => '_xdwp_late_checked',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_xdwp_late_checked',
							'value'   => time() - self::LATE_RECHECK,
							'compare' => '<',
							'type'    => 'NUMERIC',
						),
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'objects',
			)
		);

		$checked = 0;
		foreach ( $orders as $order ) {
			if ( $checked >= self::LATE_BATCH || ( microtime( true ) - $start ) > self::MAX_RUNTIME ) {
				break;
			}
			$last = (int) Xdwp_Order::meta( $order, 'late_checked' );
			if ( $last && ( time() - $last ) < self::LATE_RECHECK ) {
				continue;
			}
			++$checked;
			$order->update_meta_data( '_xdwp_late_checked', time() );
			$order->save();

			$hit = Xdwp_Verifier::find_late_payment( $order );
			if ( ! $hit || ! Xdwp_Verifier::reserve_txid( strtolower( $hit['txid'] ), $order->get_id() ) ) {
				continue;
			}
			$coin = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
			$order->update_meta_data( '_xdwp_late_txid', strtolower( $hit['txid'] ) );
			$order->update_meta_data( '_xdwp_late_amount', $hit['amount'] );
			$order->add_order_note(
				sprintf(
					/* translators: 1: amount, 2: symbol, 3: txid */
					__( 'Late crypto payment detected after the payment window closed: %1$s %2$s (txid: %3$s). Review it and use "Mark payment received" to complete the order, or refund the customer.', 'xorro-direct-wallet-payments-woocommerce' ),
					'' !== $hit['amount'] ? $hit['amount'] : '?',
					$coin ? $coin['symbol'] : '',
					strtolower( $hit['txid'] )
				)
			);
			$order->save();
			Xdwp_Order::log_event( $order, 'late', sprintf( /* translators: 1: amount, 2: transaction id */ __( 'Money arrived after the window closed: %1$s (%2$s)', 'xorro-direct-wallet-payments-woocommerce' ), $hit['amount'], $hit['txid'] ) );
			Xdwp_Order::set_flag( $order, 'paid_late' );
			do_action( 'xdwp_late_payment_detected', $order, $hit['txid'], $hit['amount'] );
		}
	}
}
