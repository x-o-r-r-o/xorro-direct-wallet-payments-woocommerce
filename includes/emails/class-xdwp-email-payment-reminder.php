<?php
/**
 * Customer email: the crypto payment window is about to close.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Email_Payment_Reminder
 */
class Xdwp_Email_Payment_Reminder extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'xdwp_payment_reminder';
		$this->customer_email = true;
		$this->title          = __( 'Crypto payment reminder', 'xorro-direct-wallet-payments-woocommerce' );
		$this->description    = __( 'Sent to the customer about 15 minutes before the crypto payment window closes, if the order is still unpaid. Only sent when the payment window is 30 minutes or longer.', 'xorro-direct-wallet-payments-woocommerce' );
		$this->template_html  = 'emails/xdwp-payment-reminder.php';
		$this->template_plain = 'emails/plain/xdwp-payment-reminder.php';
		$this->template_base  = XDWP_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
		);
		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your order #{order_number} is waiting for payment', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Complete your crypto payment', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public function trigger( $order ) {
		$this->setup_locale();
		if ( $order instanceof WC_Order && Xdwp_Emails::details( $order ) ) {
			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_number}'] = $order->get_order_number();
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
		$this->restore_locale();
	}

	/**
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'details'       => Xdwp_Emails::details( $this->object ),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'details'       => Xdwp_Emails::details( $this->object ),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
