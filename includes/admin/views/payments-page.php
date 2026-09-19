<?php
/**
 * Payments overview.
 *
 * @package Xdwp
 *
 * @var string $tab
 * @var string $filter
 * @var string $coin
 * @var int    $paged
 * @var array  $results See Xdwp_Payments_Admin::query().
 * @var array  $summary See Xdwp_Payments_Admin::summary().
 * @var array  $coins   Coin ID => label.
 */

defined( 'ABSPATH' ) || exit;

$tabs   = Xdwp_Admin::tabs();
$active = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tabs['payments'];
$base   = admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-payments' );

$cards = array(
	'attention' => array(
		'label' => __( 'Needs you', 'xorro-direct-wallet-payments-woocommerce' ),
		'hint'  => __( 'Late, overpaid, or part-paid after the window closed', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'awaiting'  => array(
		'label' => __( 'Waiting for payment', 'xorro-direct-wallet-payments-woocommerce' ),
		'hint'  => __( 'Quoted, nothing received yet', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'underpaid' => array(
		'label' => __( 'Part paid', 'xorro-direct-wallet-payments-woocommerce' ),
		'hint'  => __( 'Customer was asked for the rest', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'paid'      => array(
		'label' => __( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ),
		'hint'  => __( 'Confirmed on chain', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'expired'   => array(
		'label' => __( 'Expired', 'xorro-direct-wallet-payments-woocommerce' ),
		'hint'  => __( 'Window closed with nothing received', 'xorro-direct-wallet-payments-woocommerce' ),
	),
);
?>
<div class="wrap xdwp-admin">
	<?php // Core moves admin notices after .wp-header-end; without it they land inside the header bar next to the h1. ?>
	<hr class="wp-header-end">
	<div class="xdwp-options-wrap">
		<div class="cc-header">
			<div class="cc-header-title">
				<h1><?php esc_html_e( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></h1>
				<span class="cc-version"><?php echo esc_html( 'v' . XDWP_VERSION ); ?></span>
			</div>
			<div class="cc-header-extra">
				<span class="cc-mode-badge"><?php esc_html_e( 'Direct to wallet', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
			</div>
		</div>

		<div class="cc-layout">
			<nav class="cc-tabs" aria-label="<?php esc_attr_e( 'Xorro Wallet Payments settings', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
				<?php foreach ( $tabs as $key => $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="cc-tab <?php echo $tab === $key ? 'is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?> cc-tab-icon" aria-hidden="true"></span>
						<span class="cc-tab-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="cc-panel">
				<div class="cc-panel-head">
					<h2><?php echo esc_html( $active['title'] ); ?></h2>
					<p class="cc-lead"><?php echo esc_html( $active['desc'] ); ?></p>
				</div>

				<div class="xdwp-cards">
					<?php foreach ( $cards as $key => $card ) : ?>
						<?php $count = isset( $summary[ $key ] ) ? (int) $summary[ $key ] : 0; ?>
						<a class="xdwp-card <?php echo $filter === $key ? 'is-active' : ''; ?><?php echo ( 'attention' === $key && $count > 0 ) ? ' xdwp-card--attn' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'xdwp_filter', $key, $base ) ); ?>">
							<span class="xdwp-card__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<span class="xdwp-card__label"><?php echo esc_html( $card['label'] ); ?></span>
							<span class="xdwp-card__hint"><?php echo esc_html( $card['hint'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<form method="get" action="" class="xdwp-filters">
					<input type="hidden" name="page" value="xorro-direct-wallet-payments-woocommerce-payments" />
					<label for="xdwp-filter-status" class="screen-reader-text"><?php esc_html_e( 'Payment state', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
					<select name="xdwp_filter" id="xdwp-filter-status">
						<option value="all" <?php selected( $filter, 'all' ); ?>><?php esc_html_e( 'All payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></option>
						<?php foreach ( $cards as $key => $card ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filter, $key ); ?>><?php echo esc_html( $card['label'] ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="xdwp-filter-coin" class="screen-reader-text"><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
					<select name="xdwp_coin" id="xdwp-filter-coin">
						<option value=""><?php esc_html_e( 'Every coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></option>
						<?php foreach ( $coins as $coin_id => $label ) : ?>
							<option value="<?php echo esc_attr( $coin_id ); ?>" <?php selected( $coin, $coin_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>

					<button type="submit" class="cc-btn cc-btn-secondary"><?php esc_html_e( 'Filter', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				</form>

				<?php if ( empty( $results['orders'] ) ) : ?>
					<p class="cc-lead"><?php esc_html_e( 'No orders here yet.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
				<?php else : ?>
					<table class="widefat striped xdwp-payments-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Placed', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Expected', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Received', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'State', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Transaction', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $results['orders'] as $order ) : ?>
								<?php
								$row_coin   = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
								$row_status = (string) Xdwp_Order::meta( $order, 'status' );
								$row_amount = (string) Xdwp_Order::meta( $order, 'amount' );
								$row_recv   = (string) Xdwp_Order::meta( $order, 'received' );
								$row_txid   = (string) Xdwp_Order::meta( $order, 'txid' );
								if ( '' === $row_txid ) {
									$row_txid = (string) Xdwp_Order::meta( $order, 'late_txid' );
								}
								$icons = $row_coin ? Xdwp_Coins::icon_meta( $row_coin['id'] ) : array();
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a>
										<div class="xdwp-payments-table__sub"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
									</td>
									<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ); ?></td>
									<td>
										<?php echo esc_html( $order->get_billing_email() ); ?>
									</td>
									<td>
										<span class="cc-coin-cell">
											<?php if ( ! empty( $icons['icon'] ) ) : ?>
												<span class="cc-coin-cell__icon" aria-hidden="true">
													<img src="<?php echo esc_url( $icons['icon'] ); ?>" alt="" width="20" height="20" decoding="async" style="width:20px;height:20px;object-fit:contain;display:block;" />
												</span>
											<?php endif; ?>
											<span><?php echo esc_html( $row_coin ? $row_coin['symbol'] : '—' ); ?></span>
										</span>
									</td>
									<td><code><?php echo esc_html( '' !== $row_amount ? $row_amount : '—' ); ?></code></td>
									<td><code><?php echo esc_html( '' !== $row_recv ? $row_recv : '—' ); ?></code></td>
									<td><?php echo Xdwp_Payments_Admin::status_pill( $order, $row_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></td>
									<td class="xdwp-payments-table__txid"><code><?php echo esc_html( '' !== $row_txid ? $row_txid : '—' ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( (int) $results['pages'] > 1 ) : ?>
						<div class="tablenav"><div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array( 'xdwp_filter' => $filter, 'xdwp_coin' => $coin ), $base ) ),
										'format'    => '',
										'current'   => max( 1, (int) $paged ),
										'total'     => (int) $results['pages'],
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
									)
								)
							);
							?>
						</div></div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
