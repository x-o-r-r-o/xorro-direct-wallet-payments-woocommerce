<?php
/**
 * Where a customer gives the shop an address to refund them at.
 *
 * This page is reached by a link the shop sent, and nothing else. It shows as little as it can:
 * anyone holding the link can open it, so it says what is being refunded and nothing about who
 * bought what.
 *
 * Copy to yourtheme/xorro-direct-wallet-payments-woocommerce/xdwp-refund-claim.php to change it.
 *
 * @package Xdwp
 *
 * @var WC_Order|null $order  Order, when the link was good.
 * @var array|null    $coin   Coin definition.
 * @var string        $amount Amount being refunded, in the coin.
 * @var string        $given  Address the customer already gave, if any.
 * @var string        $sent   Transaction id, once the shop has sent it.
 * @var string        $notice Something that went right.
 * @var string        $error  Something that went wrong.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="xdwp-claim-wrap">
	<div class="xdwp-claim">
		<h1 class="xdwp-claim__title"><?php esc_html_e( 'Your refund', 'xorro-direct-wallet-payments-woocommerce' ); ?></h1>

		<?php if ( '' !== $error ) : ?>
			<p class="xdwp-claim__error" role="alert"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>

		<?php if ( ! $order ) : ?>
			<p class="xdwp-claim__lead"><?php esc_html_e( 'Nothing can be refunded from this link.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		<?php else : ?>

			<?php if ( '' !== $notice ) : ?>
				<p class="xdwp-claim__notice" role="status"><?php echo esc_html( $notice ); ?></p>
			<?php endif; ?>

			<dl class="xdwp-claim__facts">
				<dt><?php esc_html_e( 'Order', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( '#' . $order->get_order_number() ); ?></dd>
				<?php if ( '' !== $amount && $coin ) : ?>
					<dt><?php esc_html_e( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
					<dd><bdi dir="ltr"><?php echo esc_html( $amount . ' ' . $coin['symbol'] ); ?></bdi></dd>
					<dt><?php esc_html_e( 'Network', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
					<dd><?php echo esc_html( Xdwp_Coins::network_label( $coin ) ); ?></dd>
				<?php endif; ?>
			</dl>

			<?php if ( '' !== $sent ) : ?>
				<p class="xdwp-claim__lead"><?php esc_html_e( 'This refund has been sent. Its transaction is:', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
				<p class="xdwp-claim__txid"><bdi dir="ltr"><?php echo esc_html( $sent ); ?></bdi></p>
				<?php
				$xdwp_explorer = $coin ? Xdwp_Coins::explorer_tx_url( $coin, $sent ) : '';
				if ( '' !== $xdwp_explorer ) :
					?>
					<p><a href="<?php echo esc_url( $xdwp_explorer ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'View it on the blockchain', 'xorro-direct-wallet-payments-woocommerce' ); ?></a></p>
				<?php endif; ?>

			<?php elseif ( '' !== $given ) : ?>
				<p class="xdwp-claim__lead">
					<?php esc_html_e( 'The shop has your address and will send the refund from their own wallet. You can change it below until they do.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</p>
			<?php else : ?>
				<p class="xdwp-claim__lead">
					<?php
					echo esc_html(
						$coin
							? sprintf(
								/* translators: %s: network name, e.g. "TRON (TRC-20)" */
								__( 'Give the address you would like your refund sent to. It must be an address you control on %s — a refund sent anywhere else cannot be recovered.', 'xorro-direct-wallet-payments-woocommerce' ),
								Xdwp_Coins::network_label( $coin )
							)
							: __( 'Give the address you would like your refund sent to.', 'xorro-direct-wallet-payments-woocommerce' )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( '' === $sent ) : ?>
				<form method="post" class="xdwp-claim__form">
					<?php wp_nonce_field( 'xdwp_claim_' . $order->get_id() ); ?>
					<label for="xdwp-refund-address" class="xdwp-claim__label"><?php esc_html_e( 'Your receiving address', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
					<input type="text" id="xdwp-refund-address" name="xdwp_refund_address" value="<?php echo esc_attr( $given ); ?>" autocomplete="off" spellcheck="false" dir="ltr" required />
					<button type="submit" name="xdwp_claim_submit" value="1" class="button xdwp-claim__button">
						<?php echo '' !== $given ? esc_html__( 'Change this address', 'xorro-direct-wallet-payments-woocommerce' ) : esc_html__( 'Send my refund here', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</button>
					<p class="xdwp-claim__hint"><?php esc_html_e( 'Paste it from your wallet rather than typing it. Nobody can reverse a payment to the wrong address.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
				</form>
			<?php endif; ?>

		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
