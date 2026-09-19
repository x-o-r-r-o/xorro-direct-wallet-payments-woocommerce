<?php
/**
 * Customer email: crypto payment window closing soon (plain text).
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var array    $details
 * @var string   $email_heading
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
/* translators: %s: customer first name */
echo esc_html( sprintf( __( 'Hi %s,', 'xorro-direct-wallet-payments-woocommerce' ), $order->get_billing_first_name() ) ) . "\n\n";
echo esc_html(
	sprintf(
		/* translators: %s: order number */
		__( 'Your order #%s is still waiting for payment, and the payment window closes soon. If you have already sent it, you can ignore this email — the order updates as soon as the transfer arrives.', 'xorro-direct-wallet-payments-woocommerce' ),
		$order->get_order_number()
	)
) . "\n";

if ( $details ) {
	wc_get_template( 'emails/plain/xdwp-payment-details.php', array( 'order' => $order, 'details' => $details ), '', XDWP_PATH . 'templates/' );
}

if ( $email->get_additional_content() ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $email->get_additional_content() ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
