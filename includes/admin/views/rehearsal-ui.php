<?php
/**
 * A dry run of everything that happens after a payment is confirmed.
 *
 * Test mode proves the chain reading works but still needs a faucet and a wallet. Most of what
 * actually worries a merchant happens after the money is seen — the order status, the customer
 * email, the webhook — and none of that involves a blockchain. This rehearses that half in a
 * few seconds.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

$xdwp_rehearsal = Xdwp_Rehearsal::current();
$xdwp_payable   = Xdwp_Coins::payable_for_total( Xdwp_Rehearsal::AMOUNT );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$xdwp_result = isset( $_GET['xdwp_rehearsal'] ) ? sanitize_text_field( wp_unslash( $_GET['xdwp_rehearsal'] ) ) : '';
?>
<div class="xdwp-rehearsal">
	<h3 class="xdwp-rehearsal__title"><?php esc_html_e( 'Rehearse a payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></h3>
	<p class="xdwp-rehearsal__lead">
		<?php esc_html_e( 'Creates one order, quotes it against your real wallet and today\'s rate, then confirms it as though a payment had been found — through the same code that confirms a real one. Use it to check that the order reaches the right status, that the email arrives, and that your alerts fire. No money is involved and no chain is read, so it takes seconds. The order is yours to delete when you are done, and it never appears in your payments list or your figures.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
	</p>

	<?php if ( 0 === strpos( $xdwp_result, 'failed:' ) ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'The rehearsal could not be set up. That is worth knowing: the same thing would happen to a customer. Check the Coins tab, and that the coin you chose has a receiving address.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		</div>
	<?php elseif ( 'discarded' === $xdwp_result ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'The rehearsal order was deleted.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $xdwp_rehearsal ) : ?>
		<?php if ( empty( $xdwp_payable ) ) : ?>
			<p class="xdwp-rehearsal__empty">
				<?php esc_html_e( 'No coin can be quoted yet. Tick a coin on the Coins tab and give it a receiving address on Wallets, then come back.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="xdwp-rehearsal__form">
				<input type="hidden" name="action" value="xdwp_rehearse" />
				<input type="hidden" name="do" value="start" />
				<?php wp_nonce_field( 'xdwp_rehearse' ); ?>
				<label for="xdwp-rehearse-coin" class="xdwp-rehearsal__label"><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
				<select name="coin" id="xdwp-rehearse-coin">
					<?php foreach ( $xdwp_payable as $xdwp_coin_id => $xdwp_coin ) : ?>
						<option value="<?php echo esc_attr( $xdwp_coin_id ); ?>"><?php echo esc_html( $xdwp_coin['name'] . ' (' . $xdwp_coin['symbol'] . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="cc-btn cc-btn-secondary"><?php esc_html_e( 'Start a rehearsal', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
			</form>
		<?php endif; ?>
	<?php else : ?>
		<?php
		$xdwp_paid   = $xdwp_rehearsal->is_paid();
		$xdwp_coin   = Xdwp_Coins::get( (string) Xdwp_Order::meta( $xdwp_rehearsal, 'coin' ) );
		$xdwp_symbol = $xdwp_coin && isset( $xdwp_coin['symbol'] ) ? $xdwp_coin['symbol'] : '';
		?>
		<p class="xdwp-rehearsal__state">
			<?php
			printf(
				/* translators: 1: order number, 2: crypto amount, 3: coin symbol */
				esc_html__( 'Rehearsal order #%1$s is quoted at %2$s %3$s.', 'xorro-direct-wallet-payments-woocommerce' ),
				esc_html( $xdwp_rehearsal->get_order_number() ),
				esc_html( (string) Xdwp_Order::meta( $xdwp_rehearsal, 'amount' ) ),
				esc_html( $xdwp_symbol )
			);
			?>
			<a href="<?php echo esc_url( $xdwp_rehearsal->get_checkout_order_received_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'See the payment page a customer would get', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</a>
		</p>

		<?php if ( $xdwp_paid ) : ?>
			<ul class="xdwp-rehearsal__results">
				<?php foreach ( Xdwp_Rehearsal::outcome( $xdwp_rehearsal ) as $xdwp_step ) : ?>
					<li class="xdwp-rehearsal__result <?php echo $xdwp_step['ok'] ? 'is-ok' : 'is-note'; ?>">
						<strong><?php echo esc_html( $xdwp_step['label'] ); ?></strong>
						<span><?php echo esc_html( $xdwp_step['detail'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="xdwp-rehearsal__note">
				<?php esc_html_e( 'Delete the order when you have checked what you needed to. Nothing else is left behind.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<div class="xdwp-rehearsal__actions">
			<?php if ( ! $xdwp_paid ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="xdwp_rehearse" />
					<input type="hidden" name="do" value="confirm" />
					<?php wp_nonce_field( 'xdwp_rehearse' ); ?>
					<button type="submit" class="cc-btn cc-btn-primary"><?php esc_html_e( 'Confirm it, as a real payment would', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="xdwp_rehearse" />
				<input type="hidden" name="do" value="discard" />
				<?php wp_nonce_field( 'xdwp_rehearse' ); ?>
				<button type="submit" class="cc-btn cc-btn-secondary"><?php esc_html_e( 'Delete the rehearsal order', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
</div>
