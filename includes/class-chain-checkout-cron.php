<?php
/**
 * WP-Cron payment polling and price refresh.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Cron
 */
class Chain_Checkout_Cron {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'chain_checkout_check_payments', array( __CLASS__, 'check_pending_payments' ) );
		add_action( 'chain_checkout_refresh_prices', array( 'Chain_Checkout_Prices', 'cron_refresh' ) );
	}

	/**
	 * Poll awaiting crypto orders and verify on-chain.
	 */
	public static function check_pending_payments() {
		if ( 'yes' !== Chain_Checkout_Settings::get( 'auto_verify', 'yes' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => 100,
				'status'         => array( 'on-hold', 'pending' ),
				'payment_method' => CHAIN_CHECKOUT_GATEWAY_ID,
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_chain_checkout_started', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_chain_checkout_status',
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
			Chain_Checkout_Order::maybe_expire( $order );

			// Re-fetch in case expired.
			$order = wc_get_order( $order->get_id() );
			if ( ! $order || 'awaiting' !== $order->get_meta( '_chain_checkout_status' ) ) {
				continue;
			}

			if ( Chain_Checkout_Verifier::verify_order( $order ) ) {
				Chain_Checkout_Order::mark_paid( $order );
			}
		}
	}
}
