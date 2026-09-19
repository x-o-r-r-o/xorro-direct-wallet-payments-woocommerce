<?php
/**
 * Customer email: partial crypto payment received (HTML).
 *
 * Override by copying to yourtheme/woocommerce/emails/xdwp-partial-payment.php.
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var array    $details
 * @var string   $email_heading
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p>
	<?php
	/* translators: %s: customer first name */
	echo esc_html( sprintf( __( 'Hi %s,', 'xorro-direct-wallet-payments-woocommerce' ), $order->get_billing_first_name() ) );
	?>
</p>
<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: order number */
			__( 'Thank you — part of the payment for order #%s has arrived. This often happens when a wallet or exchange deducts its fee from the amount sent. Please send the remaining amount below to complete your order; the payment window has been restarted.', 'xorro-direct-wallet-payments-woocommerce' ),
			$order->get_order_number()
		)
	);
	?>
</p>
<?php
if ( $details ) {
	wc_get_template( 'emails/xdwp-payment-details.php', array( 'order' => $order, 'details' => $details ), '', XDWP_PATH . 'templates/' );
}

do_action( 'woocommerce_email_order_details', $order, false, false, $email );

if ( $email->get_additional_content() ) {
	echo wp_kses_post( wpautop( wptexturize( $email->get_additional_content() ) ) );
}

do_action( 'woocommerce_email_footer', $email );
