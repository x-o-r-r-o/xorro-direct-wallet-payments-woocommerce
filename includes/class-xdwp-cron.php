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
	 * Poll awaiting crypto orders and verify on-chain.
	 */
	public static function check_pending_payments() {
		if ( 'yes' !== Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => 100,
				'status'         => array( 'on-hold', 'pending' ),
				'payment_method' => XDWP_GATEWAY_ID,
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_xdwp_started', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_xdwp_status',
						'value' => 'awaiting',
					),
				),
				'return'         => 'objects',
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		foreach ( $orders as $order ) {
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
	}
}
