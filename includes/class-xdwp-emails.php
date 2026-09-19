<?php
/**
 * Customer and store-owner emails.
 *
 * All messages are regular WooCommerce emails (WooCommerce → Settings → Emails), so store
 * owners can switch each one off, change subjects/headings, and override the templates in
 * their theme under woocommerce/emails/.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Emails
 */
class Xdwp_Emails {

	/** WooCommerce emails that should carry the crypto payment details. */
	const DETAIL_EMAILS = array( 'customer_on_hold_order', 'customer_invoice', 'customer_pending_order' );

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'payment_details' ), 10, 4 );

		add_action( 'xdwp_send_payment_reminder', array( __CLASS__, 'send_reminder' ) );
		add_action( 'xdwp_order_underpaid', array( __CLASS__, 'on_underpaid' ), 10, 4 );
		add_action( 'xdwp_order_overpaid', array( __CLASS__, 'on_overpaid' ), 10, 2 );
		add_action( 'xdwp_late_payment_detected', array( __CLASS__, 'on_late_payment' ), 10, 3 );
		add_action( 'xdwp_order_expired', array( __CLASS__, 'on_expired' ), 10, 2 );
		add_action( 'xdwp_ambiguous_payment', array( __CLASS__, 'on_ambiguous' ), 10, 3 );
	}

	/**
	 * Register email classes (loaded here, once WC_Email exists).
	 *
	 * @param array $emails Email classes.
	 * @return array
	 */
	public static function register( $emails ) {
		require_once XDWP_PATH . 'includes/emails/class-xdwp-email-payment-reminder.php';
		require_once XDWP_PATH . 'includes/emails/class-xdwp-email-partial-payment.php';
		require_once XDWP_PATH . 'includes/emails/class-xdwp-email-payment-alert.php';
		$emails['Xdwp_Email_Payment_Reminder'] = new Xdwp_Email_Payment_Reminder();
		$emails['Xdwp_Email_Partial_Payment']  = new Xdwp_Email_Partial_Payment();
		$emails['Xdwp_Email_Payment_Alert']    = new Xdwp_Email_Payment_Alert();
		return $emails;
	}

	/**
	 * Values every payment-details block needs, or null if the order is not waiting for crypto.
	 *
	 * @param WC_Order $order Order.
	 * @return array|null
	 */
	public static function details( $order ) {
		if ( ! $order instanceof WC_Order || ! Xdwp_Order::is_ours( $order ) ) {
			return null;
		}
		$status = (string) Xdwp_Order::meta( $order, 'status' );
		if ( ! in_array( $status, array( 'awaiting', 'underpaid' ), true ) ) {
			return null;
		}
		$coin    = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
		$address = (string) Xdwp_Order::meta( $order, 'address' );
		$amount  = (string) Xdwp_Order::meta( $order, 'amount' );
		if ( ! $coin || '' === $address || '' === $amount ) {
			return null;
		}
		$remainder = (string) Xdwp_Order::meta( $order, 'remainder' );
		$expires   = (int) Xdwp_Order::meta( $order, 'expires' );
		return array(
			'coin'      => $coin,
			'address'   => $address,
			'amount'    => ( 'underpaid' === $status && '' !== $remainder ) ? $remainder : $amount,
			'due'       => $amount,
			'received'  => (string) Xdwp_Order::meta( $order, 'received' ),
			'underpaid' => ( 'underpaid' === $status ),
			'network'   => $coin['network'] . ( 'native' !== $coin['type'] ? ' (' . strtoupper( $coin['type'] ) . ')' : '' ),
			'expires'   => $expires ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires ) : '',
			'pay_url'   => $order->get_checkout_order_received_url(),
		);
	}

	/**
	 * Add payment details to WooCommerce's own customer emails while payment is due.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Admin email.
	 * @param bool     $plain_text    Plain text.
	 * @param WC_Email $email         Email.
	 */
	public static function payment_details( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $sent_to_admin || ! $email instanceof WC_Email || ! in_array( $email->id, self::DETAIL_EMAILS, true ) ) {
			return;
		}
		$details = self::details( $order );
		if ( ! $details ) {
			return;
		}
		wc_get_template(
			$plain_text ? 'emails/plain/xdwp-payment-details.php' : 'emails/xdwp-payment-details.php',
			array(
				'order'   => $order,
				'details' => $details,
			),
			'',
			XDWP_PATH . 'templates/'
		);
	}

	/**
	 * Get a registered email object.
	 *
	 * @param string $class Email class name.
	 * @return WC_Email|null
	 */
	private static function email( $class ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}
		$emails = WC()->mailer()->get_emails();
		return isset( $emails[ $class ] ) ? $emails[ $class ] : null;
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function send_reminder( $order ) {
		$email = self::email( 'Xdwp_Email_Payment_Reminder' );
		if ( $email ) {
			$email->trigger( $order );
		}
	}

	/**
	 * @param WC_Order $order     Order.
	 * @param string   $received  Amount of the partial transfer.
	 * @param string   $remainder Amount still due.
	 * @param string   $txid      Txid.
	 */
	public static function on_underpaid( $order, $received, $remainder, $txid ) {
		$customer = self::email( 'Xdwp_Email_Partial_Payment' );
		if ( $customer ) {
			$customer->trigger( $order );
		}
		self::alert( $order, 'underpaid', $received, $txid );
	}

	/**
	 * @param WC_Order $order    Order.
	 * @param string   $overpaid Excess amount.
	 */
	public static function on_overpaid( $order, $overpaid ) {
		self::alert( $order, 'overpaid', $overpaid, (string) Xdwp_Order::meta( $order, 'txid' ) );
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $txid   Txid.
	 * @param string   $amount Amount.
	 */
	public static function on_late_payment( $order, $txid, $amount ) {
		self::alert( $order, 'late', $amount, $txid );
	}

	/**
	 * @param WC_Order $order  Order it was found for.
	 * @param string   $txid   Txid.
	 * @param string   $amount Amount.
	 */
	public static function on_ambiguous( $order, $txid, $amount ) {
		self::alert( $order, 'ambiguous', $amount, $txid );
	}

	/**
	 * @param WC_Order $order       Order.
	 * @param string   $prev_status Plugin status before expiry.
	 */
	public static function on_expired( $order, $prev_status ) {
		if ( 'underpaid' === $prev_status ) {
			self::alert( $order, 'underpaid_expired', (string) Xdwp_Order::meta( $order, 'received' ), '' );
		}
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $reason underpaid | overpaid | late | underpaid_expired.
	 * @param string   $amount Amount involved.
	 * @param string   $txid   Txid.
	 */
	private static function alert( $order, $reason, $amount, $txid ) {
		$email = self::email( 'Xdwp_Email_Payment_Alert' );
		if ( $email ) {
			$email->trigger( $order, $reason, $amount, $txid );
		}
	}
}
