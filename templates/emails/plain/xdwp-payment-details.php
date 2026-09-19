<?php
/**
 * Crypto payment details block (plain-text emails).
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var array    $details See Xdwp_Emails::details().
 */

defined( 'ABSPATH' ) || exit;

$xdwp_symbol = $details['coin']['symbol'];

echo "\n==========\n";
echo esc_html( $details['underpaid'] ? __( 'Complete your crypto payment', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Crypto payment details', 'xorro-direct-wallet-payments-woocommerce' ) ) . "\n\n";
if ( $details['underpaid'] ) {
	echo esc_html(
		sprintf(
			/* translators: 1: received, 2: symbol, 3: full amount, 4: remaining */
			__( 'We received %1$s %2$s of %3$s %2$s. Please send the remaining %4$s %2$s to the same address.', 'xorro-direct-wallet-payments-woocommerce' ),
			$details['received'],
			$xdwp_symbol,
			$details['due'],
			$details['amount']
		)
	) . "\n\n";
} else {
	echo esc_html__( 'Send exactly this amount to the address below. Network fees are paid on top, by you.', 'xorro-direct-wallet-payments-woocommerce' ) . "\n\n";
}
echo esc_html( ( $details['underpaid'] ? __( 'Remaining amount', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ) ) . ': ' . $details['amount'] . ' ' . $xdwp_symbol ) . "\n";
echo esc_html( __( 'Network', 'xorro-direct-wallet-payments-woocommerce' ) . ': ' . $details['coin']['name'] . ' — ' . $details['network'] ) . "\n";
echo esc_html( __( 'Address', 'xorro-direct-wallet-payments-woocommerce' ) . ': ' . $details['address'] ) . "\n";
if ( '' !== $details['memo'] ) {
	echo esc_html( ( 'tag' === $details['memo_kind'] ? __( 'Destination tag', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Memo', 'xorro-direct-wallet-payments-woocommerce' ) ) . ': ' . $details['memo'] ) . "\n";
	echo esc_html__( 'Include this with the payment so we know it is yours.', 'xorro-direct-wallet-payments-woocommerce' ) . "\n";
}
if ( '' !== $details['expires'] ) {
	echo esc_html( __( 'Pay before', 'xorro-direct-wallet-payments-woocommerce' ) . ': ' . $details['expires'] ) . "\n";
}
echo "\n" . esc_html__( 'Payment page (QR code):', 'xorro-direct-wallet-payments-woocommerce' ) . ' ' . esc_url_raw( $details['pay_url'] ) . "\n";
echo esc_html__( 'Only send on the network shown. Your order is confirmed automatically once the payment arrives.', 'xorro-direct-wallet-payments-woocommerce' ) . "\n";
echo "==========\n\n";
