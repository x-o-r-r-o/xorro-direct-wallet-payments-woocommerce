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
 * @var array  $report  See Xdwp_Payments_Admin::report().
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

			<div class="cc-panels">
				<div class="cc-panel is-active">
					<div class="cc-panel-head">
						<h2><?php echo esc_html( $active['title'] ); ?></h2>
						<p class="cc-panel-desc"><?php echo esc_html( $active['desc'] ); ?></p>
					</div>
					<div class="cc-panel-content">

				<?php $xdwp_action_notice = Xdwp_Payments_Admin::row_action_notice(); ?>
				<?php if ( '' !== $xdwp_action_notice ) : ?>
					<div class="notice notice-info inline xdwp-action-notice"><p><?php echo esc_html( $xdwp_action_notice ); ?></p></div>
				<?php endif; ?>

				<?php $xdwp_store_checks = Xdwp_Selftest::store_checks(); ?>
				<?php
				$xdwp_bad = array_filter(
					$xdwp_store_checks,
					static function ( $check ) {
						return in_array( $check['status'], array( 'fail', 'warn' ), true );
					}
				);
				?>
				<?php if ( ! empty( $xdwp_bad ) ) : ?>
					<div class="xdwp-health">
						<h3 class="xdwp-health__title"><?php esc_html_e( 'Worth checking', 'xorro-direct-wallet-payments-woocommerce' ); ?></h3>
						<ul class="xdwp-test-list">
							<?php foreach ( $xdwp_bad as $xdwp_check ) : ?>
								<li class="xdwp-test-line xdwp-test-line--<?php echo esc_attr( $xdwp_check['status'] ); ?>">
									<span class="xdwp-test-mark" aria-hidden="true"><?php echo 'fail' === $xdwp_check['status'] ? '&#10005;' : '!'; ?></span>
									<span><strong><?php echo esc_html( $xdwp_check['label'] ); ?>:</strong> <?php echo esc_html( $xdwp_check['detail'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="xdwp-health__hint">
							<?php
							printf(
								/* translators: %s: link to the Wallets tab */
								esc_html__( 'You can test any single coin end to end on %s.', 'xorro-direct-wallet-payments-woocommerce' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-wallets' ) ) . '">' . esc_html__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
							);
							?>
						</p>
					</div>
				<?php endif; ?>

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

				<section class="xdwp-report" aria-labelledby="xdwp-report-title">
					<div class="xdwp-report__head">
						<h3 class="xdwp-report__title" id="xdwp-report-title"><?php esc_html_e( 'How payments are going', 'xorro-direct-wallet-payments-woocommerce' ); ?></h3>
						<div class="xdwp-report__periods">
							<?php foreach ( Xdwp_Payments_Admin::report_periods() as $xdwp_days => $xdwp_days_label ) : ?>
								<a class="xdwp-report__period <?php echo (int) $report['days'] === (int) $xdwp_days ? 'is-active' : ''; ?>"
									href="<?php echo esc_url( add_query_arg( array( 'xdwp_days' => $xdwp_days, 'xdwp_filter' => $filter, 'xdwp_coin' => $coin ), $base ) ); ?>"
									<?php echo (int) $report['days'] === (int) $xdwp_days ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $xdwp_days_label ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>

					<?php if ( 0 === (int) $report['quoted'] ) : ?>
						<p class="xdwp-report__empty"><?php esc_html_e( 'No crypto orders in this period yet. Figures appear here as soon as one is quoted.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
					<?php else : ?>
						<div class="xdwp-report__figures">
							<div class="xdwp-figure">
								<span class="xdwp-figure__value"><?php echo wp_kses_post( wc_price( (float) $report['value'] ) ); ?></span>
								<span class="xdwp-figure__label"><?php esc_html_e( 'Taken', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
								<span class="xdwp-figure__hint">
									<?php
									printf(
										/* translators: %s: number of paid orders */
										esc_html__( 'across %s paid orders', 'xorro-direct-wallet-payments-woocommerce' ),
										esc_html( number_format_i18n( (int) $report['paid'] ) )
									);
									?>
								</span>
							</div>
							<div class="xdwp-figure">
								<span class="xdwp-figure__value"><?php echo esc_html( Xdwp_Payments_Admin::duration( $report['settle'] ) ); ?></span>
								<span class="xdwp-figure__label"><?php esc_html_e( 'Typical wait', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
								<span class="xdwp-figure__hint"><?php esc_html_e( 'from quote to confirmed', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
							</div>
							<div class="xdwp-figure">
								<span class="xdwp-figure__value"><?php echo esc_html( Xdwp_Payments_Admin::rate( (int) $report['expired'], (int) $report['quoted'] ) ); ?></span>
								<span class="xdwp-figure__label"><?php esc_html_e( 'Walked away', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
								<span class="xdwp-figure__hint"><?php esc_html_e( 'quoted, then never paid', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
							</div>
							<div class="xdwp-figure">
								<span class="xdwp-figure__value"><?php echo esc_html( Xdwp_Payments_Admin::rate( (int) $report['short'], (int) $report['quoted'] ) ); ?></span>
								<span class="xdwp-figure__label"><?php esc_html_e( 'Sent too little', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
								<span class="xdwp-figure__hint"><?php esc_html_e( 'had to be asked for the rest', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
							</div>
						</div>

						<div class="xdwp-report__scroll">
							<table class="xdwp-report__table">
								<caption class="screen-reader-text"><?php esc_html_e( 'Payments by coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Received', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Value', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Typical wait', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Walked away', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Sent too little', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									// Ten is a glance; a shop with forty coins enabled gets the
									// busiest ten and a count of the rest, not a page of zeroes.
									$xdwp_shown  = array_slice( $report['coins'], 0, 10, true );
									$xdwp_hidden = count( $report['coins'] ) - count( $xdwp_shown );
									?>
									<?php foreach ( $xdwp_shown as $xdwp_coin_id => $xdwp_row ) : ?>
										<tr>
											<th scope="row" data-label="<?php esc_attr_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
												<a href="<?php echo esc_url( add_query_arg( array( 'xdwp_coin' => $xdwp_coin_id ), $base ) ); ?>"><?php echo esc_html( $xdwp_row['label'] ); ?></a>
											</th>
											<td data-label="<?php esc_attr_e( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
												<?php
												printf(
													/* translators: 1: paid orders, 2: orders quoted in this coin */
													esc_html__( '%1$s of %2$s', 'xorro-direct-wallet-payments-woocommerce' ),
													esc_html( number_format_i18n( (int) $xdwp_row['paid'] ) ),
													esc_html( number_format_i18n( (int) $xdwp_row['quoted'] ) )
												);
												?>
											</td>
											<td data-label="<?php esc_attr_e( 'Received', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
												<bdi dir="ltr"><?php echo esc_html( Xdwp_Coins::format_amount( $xdwp_row['amount'], $xdwp_coin_id ) . ' ' . $xdwp_row['symbol'] ); ?></bdi>
											</td>
											<td data-label="<?php esc_attr_e( 'Value', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php echo wp_kses_post( wc_price( (float) $xdwp_row['value'] ) ); ?></td>
											<td data-label="<?php esc_attr_e( 'Typical wait', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php echo esc_html( Xdwp_Payments_Admin::duration( $xdwp_row['settle'] ) ); ?></td>
											<td data-label="<?php esc_attr_e( 'Walked away', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php echo esc_html( Xdwp_Payments_Admin::rate( (int) $xdwp_row['expired'], (int) $xdwp_row['quoted'] ) ); ?></td>
											<td data-label="<?php esc_attr_e( 'Sent too little', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php echo esc_html( Xdwp_Payments_Admin::rate( (int) $xdwp_row['short'], (int) $xdwp_row['quoted'] ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<p class="xdwp-report__note">
							<?php
							esc_html_e( 'A coin quoted often and paid rarely is costing you checkouts — it may be worth turning off. Figures are refreshed hourly.', 'xorro-direct-wallet-payments-woocommerce' );
							if ( $xdwp_hidden > 0 ) {
								echo ' ';
								printf(
									/* translators: %s: number of coins */
									esc_html( _n( '%s quieter coin is not shown.', '%s quieter coins are not shown.', $xdwp_hidden, 'xorro-direct-wallet-payments-woocommerce' ) ),
									esc_html( number_format_i18n( $xdwp_hidden ) )
								);
							}
							if ( ! empty( $report['capped'] ) ) {
								echo ' ';
								printf(
									/* translators: %s: number of orders */
									esc_html__( 'Based on the most recent %s orders in this period.', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html( number_format_i18n( Xdwp_Payments_Admin::REPORT_MAX ) )
								);
							}
							?>
						</p>
					<?php endif; ?>
				</section>

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

					<a class="cc-btn cc-btn-secondary" href="<?php
						echo esc_url(
							wp_nonce_url(
								add_query_arg(
									array(
										'action'      => 'xdwp_export_payments',
										'xdwp_filter' => $filter,
										'xdwp_coin'   => $coin,
									),
									admin_url( 'admin-post.php' )
								),
								'xdwp_export_payments'
							)
						);
					?>"><?php esc_html_e( 'Download CSV', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
				</form>

				<?php if ( empty( $results['orders'] ) ) : ?>
					<p class="cc-lead"><?php esc_html_e( 'No orders here yet.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
				<?php else : ?>
					<div class="xdwp-table-wrap">
					<table class="widefat striped xdwp-payments-table">
						<thead>
							<tr>
								<th class="xdwp-col-order"><?php esc_html_e( 'Order', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th class="xdwp-col-customer"><?php esc_html_e( 'Customer', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th class="xdwp-col-amount"><?php esc_html_e( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th class="xdwp-col-state"><?php esc_html_e( 'State', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
								<th class="xdwp-col-tx"><?php esc_html_e( 'Transaction', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
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
									<td class="xdwp-col-order" data-label="<?php esc_attr_e( 'Order', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
										<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a>
										<?php $xdwp_in_queue = '' !== (string) $order->get_meta( '_xdwp_attention' ); ?>
										<?php if ( in_array( $row_status, array( 'awaiting', 'underpaid', 'expired' ), true ) || $xdwp_in_queue ) : ?>
											<div class="xdwp-row-actions">
												<?php if ( in_array( $row_status, array( 'awaiting', 'underpaid', 'expired' ), true ) ) : ?>
													<a href="<?php echo esc_url( Xdwp_Payments_Admin::row_action_url( $order, 'recheck' ) ); ?>"><?php esc_html_e( 'Check now', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
													<a href="<?php echo esc_url( Xdwp_Payments_Admin::row_action_url( $order, 'extend' ) ); ?>"><?php esc_html_e( '+1 hour', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
												<?php endif; ?>
												<?php if ( $xdwp_in_queue ) : ?>
													<?php // Late money and overpayments never stop being late or over, so without
													// this the "Needs you" list only ever grows and stops being read. ?>
													<a href="<?php echo esc_url( Xdwp_Payments_Admin::row_action_url( $order, 'handled' ) ); ?>" title="<?php esc_attr_e( 'Take this off the "Needs you" list. It comes back if anything else happens to the payment.', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php esc_html_e( 'Dealt with', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
												<?php endif; ?>
											</div>
										<?php endif; ?>
										<div class="xdwp-payments-table__sub"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
										<?php if ( $order->get_date_created() ) : ?>
											<div class="xdwp-payments-table__sub xdwp-payments-table__date"><?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ); ?><br /><?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'time_format' ) ) ); ?></div>
										<?php endif; ?>
									</td>
									<td class="xdwp-col-customer" data-label="<?php esc_attr_e( 'Customer', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
										<?php $row_name = trim( $order->get_formatted_billing_full_name() ); ?>
										<?php if ( '' !== $row_name ) : ?>
											<div><?php echo esc_html( $row_name ); ?></div>
										<?php endif; ?>
										<span class="xdwp-payments-table__sub"><?php echo esc_html( $order->get_billing_email() ); ?></span>
									</td>
									<td class="xdwp-col-amount" data-label="<?php esc_attr_e( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
										<span class="cc-coin-cell">
											<?php if ( ! empty( $icons['icon'] ) ) : ?>
												<span class="cc-coin-cell__icon" aria-hidden="true">
													<img src="<?php echo esc_url( $icons['icon'] ); ?>" alt="" width="18" height="18" decoding="async" style="width:18px;height:18px;object-fit:contain;display:block;" />
												</span>
											<?php endif; ?>
											<code><?php echo esc_html( '' !== $row_amount ? $row_amount : '—' ); ?></code>
											<span class="xdwp-payments-table__sub"><?php echo esc_html( $row_coin ? $row_coin['symbol'] : '' ); ?></span>
										</span>
										<?php if ( '' !== $row_recv ) : ?>
											<div class="xdwp-payments-table__sub">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: amount received on chain */
														__( 'received %s', 'xorro-direct-wallet-payments-woocommerce' ),
														$row_recv
													)
												);
												?>
											</div>
										<?php endif; ?>
									</td>
									<td class="xdwp-col-state" data-label="<?php esc_attr_e( 'State', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php echo Xdwp_Payments_Admin::status_pill( $order, $row_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></td>
									<td class="xdwp-col-tx xdwp-payments-table__txid" data-label="<?php esc_attr_e( 'Transaction', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
										<?php if ( '' === $row_txid ) : ?>
											<span class="xdwp-muted">&mdash;</span>
										<?php else : ?>
											<?php $row_url = $row_coin ? Xdwp_Coins::explorer_tx_url( $row_coin, $row_txid ) : ''; ?>
											<code class="xdwp-txid" title="<?php echo esc_attr( $row_txid ); ?>"><?php echo esc_html( $row_txid ); ?></code>
											<span class="xdwp-txid-actions">
												<button type="button" class="button-link xdwp-copy-txid" data-txid="<?php echo esc_attr( $row_txid ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
												<?php if ( '' !== $row_url ) : ?>
													<a href="<?php echo esc_url( $row_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Explorer', 'xorro-direct-wallet-payments-woocommerce' ); ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'xorro-direct-wallet-payments-woocommerce' ); ?></span></a>
												<?php endif; ?>
											</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>

					<div class="tablenav bottom">
						<div class="tablenav-pages<?php echo ( (int) $results['pages'] > 1 ) ? '' : ' one-page'; ?>">
							<span class="displaying-num">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of orders */
										_n( '%s order', '%s orders', (int) $results['total'], 'xorro-direct-wallet-payments-woocommerce' ),
										number_format_i18n( (int) $results['total'] )
									)
								);
								?>
							</span>
							<?php if ( (int) $results['pages'] > 1 ) : ?>
								<span class="pagination-links">
									<?php
									echo wp_kses_post(
										paginate_links(
											array(
												'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array( 'xdwp_filter' => $filter, 'xdwp_coin' => $coin ), $base ) ),
												'format'    => '',
												'current'   => max( 1, (int) $paged ),
												'total'     => (int) $results['pages'],
												'type'      => 'plain',
												'prev_text' => '&lsaquo;&nbsp;' . __( 'Previous', 'xorro-direct-wallet-payments-woocommerce' ),
												'next_text' => __( 'Next', 'xorro-direct-wallet-payments-woocommerce' ) . '&nbsp;&rsaquo;',
											)
										)
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
