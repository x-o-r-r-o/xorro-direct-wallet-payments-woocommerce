<?php
/**
 * Crypto payment details block (HTML emails).
 *
 * Override by copying to yourtheme/woocommerce/emails/xdwp-payment-details.php.
 *
 * @package Xdwp
 *
 * @var WC_Order $order
 * @var array    $details See Xdwp_Emails::details().
 */

defined( 'ABSPATH' ) || exit;

$xdwp_symbol = $details['coin']['symbol'];
$xdwp_cell   = 'padding:8px 12px;border:1px solid #e5e5e5;text-align:left;vertical-align:top;';
?>
<h2><?php echo esc_html( $details['underpaid'] ? __( 'Complete your crypto payment', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Crypto payment details', 'xorro-direct-wallet-payments-woocommerce' ) ); ?></h2>
<?php if ( $details['underpaid'] ) : ?>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: received, 2: symbol, 3: full amount, 4: remaining */
				__( 'We received %1$s %2$s of %3$s %2$s. Please send the remaining %4$s %2$s to the same address.', 'xorro-direct-wallet-payments-woocommerce' ),
				$details['received'],
				$xdwp_symbol,
				$details['due'],
				$details['amount']
			)
		);
		?>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'Send exactly this amount to the address below. Network fees are paid on top, by you.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
<?php endif; ?>
<table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 16px;" border="0">
	<tr>
		<th scope="row" style="<?php echo esc_attr( $xdwp_cell ); ?>width:34%;"><?php echo esc_html( $details['underpaid'] ? __( 'Remaining amount', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ) ); ?></th>
		<td style="<?php echo esc_attr( $xdwp_cell ); ?>font-family:monospace;font-size:15px;"><strong><?php echo esc_html( $details['amount'] . ' ' . $xdwp_symbol ); ?></strong></td>
	</tr>
	<tr>
		<th scope="row" style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php esc_html_e( 'Network', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
		<td style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php echo esc_html( $details['coin']['name'] . ' — ' . $details['network'] ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php esc_html_e( 'Address', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
		<td style="<?php echo esc_attr( $xdwp_cell ); ?>font-family:monospace;word-break:break-all;"><?php echo esc_html( $details['address'] ); ?></td>
	</tr>
	<?php if ( '' !== $details['memo'] ) : ?>
		<tr>
			<th scope="row" style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php echo esc_html( 'tag' === $details['memo_kind'] ? __( 'Destination tag', 'xorro-direct-wallet-payments-woocommerce' ) : __( 'Memo', 'xorro-direct-wallet-payments-woocommerce' ) ); ?></th>
			<td style="<?php echo esc_attr( $xdwp_cell ); ?>font-family:monospace;"><strong><?php echo esc_html( $details['memo'] ); ?></strong><br /><span style="font-size:12px;color:#666666;"><?php esc_html_e( 'Include this with the payment so we know it is yours.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span></td>
		</tr>
	<?php endif; ?>
	<?php if ( '' !== $details['expires'] ) : ?>
		<tr>
			<th scope="row" style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php esc_html_e( 'Pay before', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
			<td style="<?php echo esc_attr( $xdwp_cell ); ?>"><?php echo esc_html( $details['expires'] ); ?></td>
		</tr>
	<?php endif; ?>
</table>
<p>
	<a href="<?php echo esc_url( $details['pay_url'] ); ?>" style="display:inline-block;padding:10px 18px;background:#1d2733;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Open payment page (QR code)', 'xorro-direct-wallet-payments-woocommerce' ); ?>
	</a>
</p>
<p style="font-size:12px;color:#666666;"><?php esc_html_e( 'Only send on the network shown. Your order is confirmed automatically once the payment arrives.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
