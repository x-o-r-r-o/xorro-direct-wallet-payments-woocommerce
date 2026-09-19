<?php
/**
 * Customer email: partial crypto payment received (plain text).
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
		__( 'Thank you — part of the payment for order #%s has arrived. This often happens when a wallet or exchange deducts its fee from the amount sent. Please send the remaining amount below to complete your order; the payment window has been restarted.', 'xorro-direct-wallet-payments-woocommerce' ),
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
