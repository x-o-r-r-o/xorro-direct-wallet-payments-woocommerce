<?php
/**
 * Store-owner email: a crypto payment needs attention (plain text).
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var string   $reason
 * @var string   $amount
 * @var string   $txid
 * @var string   $symbol
 * @var string   $due
 * @var string   $received
 * @var string   $remainder
 * @var string   $edit_url
 * @var string   $email_heading
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
/* translators: 1: order number, 2: issue */
echo esc_html( sprintf( __( 'Order #%1$s: %2$s.', 'xorro-direct-wallet-payments-woocommerce' ), $order->get_order_number(), Xdwp_Email_Payment_Alert::issue_label( $reason ) ) ) . "\n\n";
echo esc_html( Xdwp_Email_Payment_Alert::describe( $reason, $amount, $symbol, $due, $received, $remainder ) ) . "\n\n";
if ( '' !== $txid ) {
	echo esc_html__( 'Transaction ID:', 'xorro-direct-wallet-payments-woocommerce' ) . ' ' . esc_html( $txid ) . "\n";
}
echo esc_html__( 'Open the order', 'xorro-direct-wallet-payments-woocommerce' ) . ': ' . esc_url_raw( $edit_url ) . "\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
