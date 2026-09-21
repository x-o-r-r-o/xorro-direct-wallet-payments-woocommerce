<?php
/**
 * A dry run of everything that happens after a customer pays.
 *
 * Test mode proves the chain reading works, but it still needs a faucet, a wallet and a few
 * minutes. Most of what a merchant actually worries about happens *after* the money is seen:
 * does the order move to the right status, does the customer get an email, does the webhook
 * reach its endpoint, does Telegram light up. None of that involves a blockchain at all.
 *
 * So this rehearses that half. A real order is created, quoted against the shop's real settings,
 * and then confirmed as though a payment had been found — through the same code that confirms a
 * real one, so what fires here is exactly what fires then.
 *
 * The line that must never be crossed: confirming is possible only for an order this class
 * created and marked as its own. A rehearsal is a way to prove the plumbing, never a way to mark
 * a customer's order paid without payment. Every entry point checks that, not just the UI.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rehearsing what happens once a payment is confirmed.
 */
class Xdwp_Rehearsal {

	/**
	 * Marks an order as this class's own. Nothing without it can ever be confirmed here.
	 */
	const FLAG = '_xdwp_rehearsal';

	/**
	 * What a rehearsal order is worth, in the shop's own currency. Small enough to be obviously
	 * not a sale, large enough that every coin can quote an amount for it.
	 */
	const AMOUNT = 10.0;

	/**
	 * Whether an order belongs to a rehearsal.
	 *
	 * @param WC_Order|null $order Order.
	 * @return bool
	 */
	public static function is_rehearsal( $order ) {
		return $order instanceof WC_Order && '' !== (string) $order->get_meta( self::FLAG );
	}

	/**
	 * Whoever is asking must be someone who could take a payment by hand anyway.
	 *
	 * The admin-post handler checks this before calling anything here, so on the plugin's own
	 * path it is asked twice. It is asked here as well because these are public static methods
	 * that create an order and mark it paid without a payment, and the next caller might not be
	 * that handler — another plugin, a WP-CLI command, a future screen. A capability check that
	 * lives only in one caller is a capability check waiting to be walked around.
	 *
	 * @return bool
	 */
	private static function allowed() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * The rehearsal order currently in progress, if there is one.
	 *
	 * @return WC_Order|null
	 */
	public static function current() {
		$orders = Xdwp_Order_Query::get(
			array(
				'limit'          => 1,
				'status'         => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'payment_method' => XDWP_GATEWAY_ID,
				'return'         => 'objects',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::FLAG,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$order = is_array( $orders ) && isset( $orders[0] ) ? $orders[0] : null;
		return self::is_rehearsal( $order ) ? $order : null;
	}

	/**
	 * Start a rehearsal: a real order, quoted with the shop's real wallet and real rates.
	 *
	 * @param string $coin_id Coin to rehearse with.
	 * @return WC_Order|WP_Error
	 */
	public static function start( $coin_id ) {
		if ( ! self::allowed() ) {
			return new WP_Error( 'xdwp_not_allowed', __( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'xdwp_no_woocommerce', __( 'WooCommerce is not available.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		$coin_id = is_scalar( $coin_id ) ? (string) $coin_id : '';
		$payable = Xdwp_Coins::payable_for_total( self::AMOUNT );
		if ( '' === $coin_id || ! isset( $payable[ $coin_id ] ) ) {
			return new WP_Error(
				'xdwp_coin_unavailable',
				__( 'That coin cannot be quoted for a rehearsal. Pick one that is enabled and has a receiving address.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		// Only ever one at a time: a shop that rehearses repeatedly should not accumulate orders
		// that look like sales.
		self::discard();

		$order = wc_create_order();
		if ( is_wp_error( $order ) || ! $order instanceof WC_Order ) {
			return new WP_Error( 'xdwp_order_failed', __( 'The rehearsal order could not be created.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		// Flagged before anything else, so an order can never exist in a state where it looks
		// like a real one to the code that confirms payments.
		$order->update_meta_data( self::FLAG, 1 );
		$order->save();

		$fee = new WC_Order_Item_Fee();
		$fee->set_name( __( 'Rehearsal — not a real sale', 'xorro-direct-wallet-payments-woocommerce' ) );
		$fee->set_amount( (string) self::AMOUNT );
		$fee->set_total( (string) self::AMOUNT );
		$fee->set_tax_status( 'none' );
		$fee->set_tax_class( '' );
		$order->add_item( $fee );

		$admin_email = (string) get_option( 'admin_email' );
		if ( '' !== $admin_email ) {
			// The shop's own address: a rehearsal must never email a customer.
			$order->set_billing_email( $admin_email );
		}
		$order->set_payment_method( XDWP_GATEWAY_ID );
		$order->calculate_totals( false );
		$order->save();

		if ( ! Xdwp_Order::assign_payment( $order, $coin_id ) ) {
			self::delete_order( $order );
			return new WP_Error(
				'xdwp_quote_failed',
				__( 'A payment could not be quoted for the rehearsal — the same thing would happen to a customer. Run "Test this coin" on the Coins tab to see why.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		$order = wc_get_order( $order->get_id() );
		$order->add_order_note( __( 'This is a rehearsal order created by Xorro Wallet Payments to check that emails, order status and alerts work. No money is involved. It can be deleted at any time.', 'xorro-direct-wallet-payments-woocommerce' ) );

		return $order;
	}

	/**
	 * Confirm the rehearsal order as though a payment had been found.
	 *
	 * Runs the ordinary confirmation, so the order status, the customer email, the webhook and
	 * the Telegram message are the real ones — that is the whole point of rehearsing.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function confirm() {
		if ( ! self::allowed() ) {
			return new WP_Error( 'xdwp_not_allowed', __( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		$order = self::current();
		if ( ! $order ) {
			return new WP_Error( 'xdwp_no_rehearsal', __( 'There is no rehearsal in progress.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		// Said twice on purpose. current() already filters, and this is the guarantee that
		// matters most in the plugin: nothing but a rehearsal order can be confirmed here.
		if ( ! self::is_rehearsal( $order ) ) {
			return new WP_Error( 'xdwp_not_a_rehearsal', __( 'That order is not a rehearsal.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		if ( $order->is_paid() ) {
			return $order;
		}

		// Obviously not a transaction id, and it cannot collide with a real one: no chain
		// produces a hash shaped like this, so it can never be confused for evidence.
		$txid = 'rehearsal-' . bin2hex( random_bytes( 8 ) );
		$order->update_meta_data( '_xdwp_txid', $txid );
		$order->update_meta_data( '_xdwp_received', (string) Xdwp_Order::meta( $order, 'amount' ) );
		$order->save();

		Xdwp_Order::log_event(
			$order,
			'rehearsal',
			__( 'Confirmed as a rehearsal. No payment was received and no chain was read.', 'xorro-direct-wallet-payments-woocommerce' )
		);

		Xdwp_Order::mark_paid( $order );

		return wc_get_order( $order->get_id() );
	}

	/**
	 * What a finished rehearsal actually did, in the order a merchant would check it.
	 *
	 * @param WC_Order $order Confirmed rehearsal order.
	 * @return array<int, array{label:string,ok:bool,detail:string}>
	 */
	public static function outcome( $order ) {
		$results = array();
		if ( ! $order instanceof WC_Order ) {
			return $results;
		}

		$target = (string) Xdwp_Settings::get( 'order_status', 'processing' );
		$actual = (string) $order->get_status();
		$results[] = array(
			'label'  => __( 'Order status', 'xorro-direct-wallet-payments-woocommerce' ),
			'ok'     => $order->is_paid() || 'completed' === $actual || 'on-hold' === $actual,
			'detail' => sprintf(
				/* translators: 1: the status the order reached, 2: the status configured for a paid order */
				__( 'The order moved to "%1$s". Your setting for a paid order is "%2$s".', 'xorro-direct-wallet-payments-woocommerce' ),
				wc_get_order_status_name( $actual ),
				wc_get_order_status_name( $target )
			),
		);

		$results[] = array(
			'label'  => __( 'Customer email', 'xorro-direct-wallet-payments-woocommerce' ),
			'ok'     => true,
			'detail' => sprintf(
				/* translators: %s: the email address the rehearsal order used */
				__( 'WooCommerce was asked to send its usual email for this status to %s — your own address, never a customer. If nothing arrives, the problem is email on this site rather than this plugin.', 'xorro-direct-wallet-payments-woocommerce' ),
				$order->get_billing_email()
			),
		);

		if ( class_exists( 'Xdwp_Notify' ) && Xdwp_Notify::configured() ) {
			$results[] = array(
				'label'  => __( 'Alerts', 'xorro-direct-wallet-payments-woocommerce' ),
				'ok'     => true,
				'detail' => __( 'Your webhook and Telegram alerts were sent the same message a real payment sends. Check they arrived.', 'xorro-direct-wallet-payments-woocommerce' ),
			);
		} else {
			$results[] = array(
				'label'  => __( 'Alerts', 'xorro-direct-wallet-payments-woocommerce' ),
				'ok'     => false,
				'detail' => __( 'No webhook or Telegram alert is set up, so nothing was sent. Set one up under Alerts if you want to be told when a payment arrives.', 'xorro-direct-wallet-payments-woocommerce' ),
			);
		}

		return $results;
	}

	/**
	 * Remove the rehearsal order, and give back anything it was holding.
	 *
	 * @return bool Whether there was one to remove.
	 */
	public static function discard() {
		if ( ! self::allowed() ) {
			return false;
		}
		$order = self::current();
		if ( ! $order ) {
			return false;
		}
		self::delete_order( $order );
		return true;
	}

	/**
	 * Delete one rehearsal order for good, releasing the amount it had reserved.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function delete_order( $order ) {
		if ( ! self::is_rehearsal( $order ) ) {
			return;
		}
		if ( class_exists( 'Xdwp_Verifier' ) ) {
			// Otherwise the unique amount it was quoted stays reserved, and a real customer
			// could be refused that amount for no reason.
			Xdwp_Verifier::release_amount_slot(
				(string) Xdwp_Order::meta( $order, 'address' ),
				(string) Xdwp_Order::meta( $order, 'amount' ),
				$order->get_id()
			);
		}
		$order->delete( true );
	}
}
