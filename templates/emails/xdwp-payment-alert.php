<?php
/**
 * Store-owner email: a crypto payment needs attention (HTML).
 *
 * Override by copying to yourtheme/woocommerce/emails/xdwp-payment-alert.php.
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var string   $reason    underpaid | overpaid | late | underpaid_expired.
 * @var string   $amount    Amount involved.
 * @var string   $txid      Transaction ID.
 * @var string   $symbol    Coin symbol.
 * @var string   $due       Order amount.
 * @var string   $received  Total received.
 * @var string   $remainder Amount still due.
 * @var string   $edit_url  Order edit URL.
 * @var string   $email_heading
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$xdwp_text = Xdwp_Email_Payment_Alert::describe( $reason, $amount, $symbol, $due, $received, $remainder );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p>
	<?php
	/* translators: 1: order number, 2: issue */
	echo esc_html( sprintf( __( 'Order #%1$s: %2$s.', 'xorro-direct-wallet-payments-woocommerce' ), $order->get_order_number(), Xdwp_Email_Payment_Alert::issue_label( $reason ) ) );
	?>
</p>
<p><?php echo esc_html( $xdwp_text ); ?></p>
<?php if ( '' !== $txid ) : ?>
	<p><?php esc_html_e( 'Transaction ID:', 'xorro-direct-wallet-payments-woocommerce' ); ?> <code style="word-break:break-all;"><?php echo esc_html( $txid ); ?></code></p>
<?php endif; ?>
<p><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Open the order', 'xorro-direct-wallet-payments-woocommerce' ); ?></a></p>
<?php
do_action( 'woocommerce_email_order_details', $order, true, false, $email );

if ( $email->get_additional_content() ) {
	echo wp_kses_post( wpautop( wptexturize( $email->get_additional_content() ) ) );
}

do_action( 'woocommerce_email_footer', $email );
