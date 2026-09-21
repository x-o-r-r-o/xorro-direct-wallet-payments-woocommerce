<?php
/**
 * Money that arrived and belongs to no order.
 *
 * The important thing this screen does is refuse to overstate itself. Only some chains can be
 * read this way, so it says which coins it checked and which it could not, by name. "Nothing
 * unmatched" from a screen that silently skipped half a shop's coins would be read as an
 * all-clear, and that is worse than not having the screen.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

$xdwp_scan = Xdwp_Reconcile::last();
?>
<section class="xdwp-reconcile" aria-labelledby="xdwp-reconcile-title">
	<div class="xdwp-reconcile__head">
		<h3 class="xdwp-reconcile__title" id="xdwp-reconcile-title">
			<?php esc_html_e( 'Money with no order', 'xorro-direct-wallet-payments-woocommerce' ); ?>
		</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="xdwp_reconcile" />
			<?php wp_nonce_field( 'xdwp_reconcile' ); ?>
			<button type="submit" class="cc-btn cc-btn-secondary">
				<?php echo $xdwp_scan ? esc_html__( 'Look again', 'xorro-direct-wallet-payments-woocommerce' ) : esc_html__( 'Look for unmatched payments', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</button>
		</form>
	</div>

	<p class="xdwp-reconcile__lead">
		<?php esc_html_e( 'Reads your receiving addresses and subtracts every transfer this plugin can already account for. What is left is money you have that no order explains — a customer paying from an address they saved, a second payment for an order already settled, or a payment that arrived after an order was cancelled. Nothing here changes an order: what an unexplained transfer means is your decision.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
	</p>

	<?php if ( ! $xdwp_scan ) : ?>
		<p class="xdwp-reconcile__empty">
			<?php esc_html_e( 'No scan yet. Looking reads each chain and uses your explorer allowance, so it only happens when you ask.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
		</p>
	<?php else : ?>
		<?php
		$xdwp_unmatched  = isset( $xdwp_scan['unmatched'] ) && is_array( $xdwp_scan['unmatched'] ) ? $xdwp_scan['unmatched'] : array();
		$xdwp_coin_rows  = isset( $xdwp_scan['coins'] ) && is_array( $xdwp_scan['coins'] ) ? $xdwp_scan['coins'] : array();
		$xdwp_unreadable = array();
		$xdwp_partial    = array();
		$xdwp_checked    = 0;
		foreach ( $xdwp_coin_rows as $xdwp_row ) {
			if ( 'unreadable' === $xdwp_row['state'] ) {
				$xdwp_unreadable[] = $xdwp_row['symbol'];
			} elseif ( 'partial' === $xdwp_row['state'] ) {
				$xdwp_partial[] = $xdwp_row['symbol'];
			} elseif ( 'checked' === $xdwp_row['state'] ) {
				++$xdwp_checked;
			}
		}
		?>

		<p class="xdwp-reconcile__when">
			<?php
			printf(
				/* translators: 1: how long ago the scan ran, 2: number of addresses read, 3: number of coins checked */
				esc_html__( 'Last looked %1$s ago, across %2$s and %3$s.', 'xorro-direct-wallet-payments-woocommerce' ),
				esc_html( human_time_diff( (int) $xdwp_scan['ran'] ) ),
				esc_html( sprintf( /* translators: %d: number of addresses */ _n( '%d address', '%d addresses', (int) $xdwp_scan['addresses'], 'xorro-direct-wallet-payments-woocommerce' ), (int) $xdwp_scan['addresses'] ) ),
				esc_html( sprintf( /* translators: %d: number of coins */ _n( '%d coin', '%d coins', $xdwp_checked, 'xorro-direct-wallet-payments-woocommerce' ), $xdwp_checked ) )
			);
			?>
		</p>

		<?php if ( ! empty( $xdwp_scan['truncated'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'This scan stopped at its address limit before it had read everything, so treat it as a partial look rather than an all-clear. Shops that give every order its own address reach this quickly.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $xdwp_unreadable || $xdwp_partial ) : ?>
			<div class="notice notice-info inline">
				<?php if ( $xdwp_unreadable ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: comma-separated coin symbols */
							esc_html__( 'Not checked, because their chains have no way to list what has arrived at an address: %s. This scan says nothing about those — money could be sitting there and it would not show here.', 'xorro-direct-wallet-payments-woocommerce' ),
							esc_html( implode( ', ', $xdwp_unreadable ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $xdwp_partial ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: comma-separated coin symbols */
							esc_html__( 'Checked only in part, because an explorer would not answer: %s. Try again in a few minutes.', 'xorro-direct-wallet-payments-woocommerce' ),
							esc_html( implode( ', ', $xdwp_partial ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( empty( $xdwp_unmatched ) ) : ?>
			<p class="xdwp-reconcile__clear">
				<?php esc_html_e( 'Every transfer found is accounted for by an order.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		<?php else : ?>
			<table class="widefat striped xdwp-reconcile__table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Transfers that no order accounts for', 'xorro-direct-wallet-payments-woocommerce' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'To address', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Transaction', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $xdwp_unmatched as $xdwp_hit ) : ?>
						<tr>
							<td>
								<?php
								echo esc_html(
									wp_date(
										get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
										(int) $xdwp_hit['time']
									)
								);
								?>
							</td>
							<td><bdi dir="ltr"><?php echo esc_html( $xdwp_hit['amount'] . ' ' . $xdwp_hit['symbol'] ); ?></bdi></td>
							<td class="xdwp-reconcile__addr"><bdi dir="ltr"><?php echo esc_html( $xdwp_hit['address'] ); ?></bdi></td>
							<td class="xdwp-reconcile__txid">
								<?php if ( '' !== $xdwp_hit['url'] ) : ?>
									<a href="<?php echo esc_url( $xdwp_hit['url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<bdi dir="ltr"><?php echo esc_html( $xdwp_hit['txid'] ); ?></bdi>
									</a>
								<?php else : ?>
									<bdi dir="ltr"><?php echo esc_html( $xdwp_hit['txid'] ); ?></bdi>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="xdwp-reconcile__note">
				<?php esc_html_e( 'A transfer can appear here for an innocent reason — your own test payment, or a transfer you made between your own wallets. Search your orders by the transaction id or the address before assuming a customer is owed something.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</section>
