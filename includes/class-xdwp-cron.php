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
		$now      = (string) time();
		if ( ! Xdwp_Verifier::atomic_add_option( $lock_key, $now ) ) {
			$existing = (string) get_option( $lock_key, '' );
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
							'key'   => '_xdwp_status',
							'value' => 'awaiting',
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

			if ( empty( $orders ) ) {
				return;
			}

			$start = microtime( true );

			foreach ( $orders as $order ) {
				if ( ( microtime( true ) - $start ) > self::MAX_RUNTIME ) {
					break;
				}

				Xdwp_Order::maybe_expire( $order );

				// Re-fetch in case expired.
				$order = wc_get_order( $order->get_id() );
				if ( ! $order || 'awaiting' !== Xdwp_Order::meta( $order, 'status' ) ) {
					continue;
				}

				if ( Xdwp_Verifier::verify_order( $order ) ) {
					Xdwp_Order::mark_paid( $order );
				}
			}
		} finally {
			delete_option( $lock_key );
		}
	}
}
