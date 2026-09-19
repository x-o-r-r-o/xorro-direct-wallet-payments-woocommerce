<?php
/**
 * Store-owner email: a crypto payment needs attention (partial, overpaid, late).
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Email_Payment_Alert
 */
class Xdwp_Email_Payment_Alert extends WC_Email {

	/** @var string underpaid | overpaid | late | underpaid_expired */
	public $reason = '';

	/** @var string */
	public $amount = '';

	/** @var string */
	public $txid = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'xdwp_payment_alert';
		$this->title          = __( 'Crypto payment needs attention', 'xorro-direct-wallet-payments-woocommerce' );
		$this->description    = __( 'Sent to the store owner when a crypto payment is short, over the amount due, or arrives after the payment window closed.', 'xorro-direct-wallet-payments-woocommerce' );
		$this->template_html  = 'emails/xdwp-payment-alert.php';
		$this->template_plain = 'emails/plain/xdwp-payment-alert.php';
		$this->template_base  = XDWP_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
			'{issue}'        => '',
		);
		parent::__construct();
		$recipient       = trim( (string) $this->get_option( 'recipient', '' ) );
		$this->recipient = '' !== $recipient ? $recipient : get_option( 'admin_email' );
	}

	/**
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Order #{order_number}: {issue}', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Crypto payment needs attention', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * Short label for the issue.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	public static function issue_label( $reason ) {
		$labels = array(
			'underpaid'         => __( 'partial payment received', 'xorro-direct-wallet-payments-woocommerce' ),
			'underpaid_expired' => __( 'partial payment, window expired', 'xorro-direct-wallet-payments-woocommerce' ),
			'overpaid'          => __( 'overpayment received', 'xorro-direct-wallet-payments-woocommerce' ),
			'late'              => __( 'payment arrived after expiry', 'xorro-direct-wallet-payments-woocommerce' ),
			'ambiguous'         => __( 'unassigned payment to check', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : __( 'crypto payment issue', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * What happened and what to do, in one paragraph.
	 *
	 * @param string $reason    Reason key.
	 * @param string $amount    Amount involved in this event.
	 * @param string $symbol    Coin symbol.
	 * @param string $due       Order amount.
	 * @param string $received  Total received.
	 * @param string $remainder Amount still due.
	 * @return string
	 */
	public static function describe( $reason, $amount, $symbol, $due, $received, $remainder ) {
		switch ( $reason ) {
			case 'underpaid':
				/* translators: 1: received, 2: symbol, 3: due, 4: remaining */
				return sprintf( __( 'The customer sent %1$s %2$s of %3$s %2$s. They have been emailed to send the remaining %4$s %2$s and the payment window was restarted. The order completes automatically if the rest arrives; otherwise it expires and you can refund the partial payment.', 'xorro-direct-wallet-payments-woocommerce' ), '' !== $received ? $received : $amount, $symbol, $due, $remainder );
			case 'underpaid_expired':
				/* translators: 1: received, 2: symbol, 3: due */
				return sprintf( __( 'The payment window closed after only %1$s %2$s of %3$s %2$s arrived. The order is marked failed. Refund the customer, or complete the order manually with "Mark payment received" if you accept the shortfall.', 'xorro-direct-wallet-payments-woocommerce' ), '' !== $received ? $received : $amount, $symbol, $due );
			case 'overpaid':
				/* translators: 1: received, 2: symbol, 3: due, 4: excess */
				return sprintf( __( 'The customer sent %1$s %2$s for %3$s %2$s due. The order was completed; %4$s %2$s was paid on top, which you may want to refund.', 'xorro-direct-wallet-payments-woocommerce' ), $received, $symbol, $due, $amount );
			case 'late':
				/* translators: 1: amount, 2: symbol, 3: due */
				return sprintf( __( '%1$s %2$s arrived for this order after its payment window had closed (%3$s %2$s was due). The order was not changed. Check the payment, then complete it with "Mark payment received" (the transaction ID is filled in) or refund the customer.', 'xorro-direct-wallet-payments-woocommerce' ), '' !== $amount ? $amount : '?', $symbol, $due );
			case 'ambiguous':
				/* translators: 1: amount, 2: symbol */
				return sprintf( __( 'A transfer of %1$s %2$s arrived on this order\'s address, but it does not match any order exactly and could belong to more than one open order (for example a customer whose exchange deducted a fee). It was not credited automatically. Check which customer sent it, then use "Mark payment received" on that order.', 'xorro-direct-wallet-payments-woocommerce' ), $amount, $symbol );
		}
		return '';
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $reason Reason.
	 * @param string   $amount Amount involved.
	 * @param string   $txid   Txid.
	 */
	public function trigger( $order, $reason = '', $amount = '', $txid = '' ) {
		$this->setup_locale();
		if ( $order instanceof WC_Order ) {
			$this->object                         = $order;
			$this->reason                         = (string) $reason;
			$this->amount                         = (string) $amount;
			$this->txid                           = (string) $txid;
			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{issue}']        = self::issue_label( $this->reason );
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
		$this->restore_locale();
	}

	/**
	 * Template variables.
	 *
	 * @param bool $plain Plain text.
	 * @return array
	 */
	private function template_args( $plain ) {
		$coin = Xdwp_Coins::get( (string) Xdwp_Order::meta( $this->object, 'coin' ) );
		return array(
			'order'         => $this->object,
			'reason'        => $this->reason,
			'amount'        => $this->amount,
			'txid'          => $this->txid,
			'symbol'        => $coin ? $coin['symbol'] : '',
			'due'           => (string) Xdwp_Order::meta( $this->object, 'amount' ),
			'received'      => (string) Xdwp_Order::meta( $this->object, 'received' ),
			'remainder'     => (string) Xdwp_Order::meta( $this->object, 'remainder' ),
			'edit_url'      => $this->object->get_edit_order_url(),
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => $plain,
			'email'         => $this,
		);
	}

	/**
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	/**
	 * Recipient field in WooCommerce → Settings → Emails.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array_slice( $this->form_fields, 0, 1, true ),
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'xorro-direct-wallet-payments-woocommerce' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Comma-separated. Defaults to %s.', 'xorro-direct-wallet-payments-woocommerce' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => false,
				),
			),
			array_slice( $this->form_fields, 1, null, true )
		);
	}
}
