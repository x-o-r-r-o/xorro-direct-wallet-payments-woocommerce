<?php
/**
 * Admin settings UI — Cryptoniq-inspired options shell.
 *
 * @package Xdwp
 *
 * @var string $tab
 * @var array  $settings
 * @var array  $groups
 */

defined( 'ABSPATH' ) || exit;

$tabs = Xdwp_Admin::tabs();

$enabled = isset( $settings['enabled_coins'] ) && is_array( $settings['enabled_coins'] ) ? $settings['enabled_coins'] : array();
$wallets = isset( $settings['wallets'] ) && is_array( $settings['wallets'] ) ? $settings['wallets'] : array();
$active  = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tabs['general'];
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

		<form method="post" action="" class="cc-form">
			<?php wp_nonce_field( 'xdwp_save_settings', 'xdwp_nonce' ); ?>

			<div class="cc-layout">
				<nav class="cc-tabs" aria-label="<?php esc_attr_e( 'Xorro Wallet Payments settings', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
					<?php foreach ( $tabs as $key => $item ) : ?>
						<a
							href="<?php echo esc_url( $item['url'] ); ?>"
							class="cc-tab <?php echo $tab === $key ? 'is-active' : ''; ?>"
						>
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
							<?php if ( 'general' === $tab ) : ?>
								<table class="form-table cc-form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Payment window (minutes)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="number" min="5" max="1440" name="xdwp[payment_window]" value="<?php echo esc_attr( (string) ( $settings['payment_window'] ?? 60 ) ); ?>" class="small-text cc-input" />
											<p class="description"><?php esc_html_e( 'Quoted crypto amount is valid for this duration. Default: 60.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Order status after payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<select name="xdwp[order_status]" class="cc-input cc-input-select">
												<?php
												$statuses = array(
													'processing' => __( 'Processing', 'xorro-direct-wallet-payments-woocommerce' ),
													'completed'  => __( 'Completed', 'xorro-direct-wallet-payments-woocommerce' ),
													'on-hold'    => __( 'On Hold', 'xorro-direct-wallet-payments-woocommerce' ),
												);
												$current = $settings['order_status'] ?? 'processing';
												foreach ( $statuses as $value => $label ) {
													printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Underpayment tolerance (%)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="number" step="0.1" min="0" max="10" name="xdwp[underpayment_percent]" value="<?php echo esc_attr( (string) ( $settings['underpayment_percent'] ?? 1 ) ); ?>" class="small-text cc-input" />
											<p class="description"><?php esc_html_e( 'Capped automatically when unique amounts are enabled so concurrent orders on a shared wallet stay distinguishable.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Minimum confirmations', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="number" min="0" max="64" name="xdwp[min_confirmations]" value="<?php echo esc_attr( (string) ( $settings['min_confirmations'] ?? 1 ) ); ?>" class="small-text cc-input" />
											<p class="description"><?php esc_html_e( 'Required on-chain confirmations before marking an order paid. Chains without tip-depth APIs only accept payments when this is 0 or 1 (fail closed above that).', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Confirmations per chain', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label>
												<input type="checkbox" name="xdwp[recommended_confirmations]" value="yes" <?php checked( ( $settings['recommended_confirmations'] ?? 'yes' ), 'yes' ); ?> />
												<?php esc_html_e( 'Wait for the confirmations that suit each chain', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</label>
											<p class="description"><?php esc_html_e( 'One confirmation does not mean the same thing on every chain. With this on, each coin waits for a number suited to its own chain (Bitcoin 2, Ethereum 12, TRON 20 and so on) and never less than the number above. Set your own per coin on the Coins tab.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Confirmations by order value', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label>
												<input type="checkbox" name="xdwp[risk_tiers]" value="yes" <?php checked( ( $settings['risk_tiers'] ?? 'no' ), 'yes' ); ?> />
												<?php esc_html_e( 'Wait less on small orders and longer on large ones', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</label>
											<p class="cc-risk-row">
												<label class="cc-inline">
													<?php
													printf(
														/* translators: %s: store currency symbol */
														esc_html__( 'Accept as soon as it is seen, up to (%s)', 'xorro-direct-wallet-payments-woocommerce' ),
														esc_html( get_woocommerce_currency_symbol() )
													);
													?>
													<input type="number" min="0" step="0.01" name="xdwp[risk_low_value]" value="<?php echo esc_attr( (string) ( $settings['risk_low_value'] ?? 0 ) ); ?>" class="small-text cc-input" />
												</label>
												<label class="cc-inline">
													<?php
													printf(
														/* translators: %s: store currency symbol */
														esc_html__( 'Wait longer from (%s)', 'xorro-direct-wallet-payments-woocommerce' ),
														esc_html( get_woocommerce_currency_symbol() )
													);
													?>
													<input type="number" min="0" step="0.01" name="xdwp[risk_high_value]" value="<?php echo esc_attr( (string) ( $settings['risk_high_value'] ?? 0 ) ); ?>" class="small-text cc-input" />
												</label>
												<label class="cc-inline">
													<?php esc_html_e( 'and require', 'xorro-direct-wallet-payments-woocommerce' ); ?>
													<input type="number" min="0" max="64" name="xdwp[risk_high_confirmations]" value="<?php echo esc_attr( (string) ( $settings['risk_high_confirmations'] ?? 0 ) ); ?>" class="small-text cc-input" />
													<?php esc_html_e( 'confirmations', 'xorro-direct-wallet-payments-woocommerce' ); ?>
												</label>
											</p>
											<p class="description"><?php esc_html_e( 'A £10 order held for six blocks costs you the customer; a £10,000 order released on one costs you £10,000. Leave a box at 0 to turn that tier off. The higher tier can only ever raise the number a coin already waits for, never lower it — and accepting a payment the moment it is seen means accepting it before the chain has settled it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Expiry grace (minutes)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="number" min="0" max="1440" name="xdwp[expiry_grace_minutes]" value="<?php echo esc_attr( (string) ( $settings['expiry_grace_minutes'] ?? 30 ) ); ?>" class="small-text cc-input" />
											<p class="description"><?php esc_html_e( 'Keep looking for payment after the window ends. Orders fail (not cancel) after grace so late funds can still be recovered.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Unique payment amounts', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[unique_amounts]" value="yes" <?php checked( ( $settings['unique_amounts'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Add a tiny unique dust amount so payments to reused addresses can be matched reliably.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Wallet rotation', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[wallet_rotation]" value="yes" <?php checked( ( $settings['wallet_rotation'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Rotate through multiple addresses per coin when available.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Automatic verification', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[auto_verify]" value="yes" <?php checked( ( $settings['auto_verify'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Poll public block explorers / RPCs and mark orders paid when payment is detected.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Test mode', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[test_mode]" value="yes" <?php checked( ( $settings['test_mode'] ?? 'no' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Point this shop at test networks, so a payment can be rehearsed with worthless coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
											<p class="description">
												<?php
												printf(
													/* translators: %s: comma-separated list of test networks */
													esc_html__( 'Runs the whole thing for real — a quote, an address, a payment page, a transfer read off a live chain, a confirmed order, an email, a webhook — on networks where coins are free. Available on %s, and only those: a test network has to be one this plugin can actually read, with a faucet you can get coins from today. Your other coins are hidden from checkout while this is on, rather than left half-working.', 'xorro-direct-wallet-payments-woocommerce' ),
													esc_html( implode( ', ', wp_list_pluck( Xdwp_Testmode::networks(), 'label' ) ) )
												);
												?>
											</p>
											<p class="description">
												<strong><?php esc_html_e( 'Use a separate wallet address for this.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong>
												<?php esc_html_e( 'A test network address is not a real one, and the Wallets tab will only accept a test address while this is on. Turning test mode off again puts your real addresses back in charge — they are not overwritten.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</p>
											<?php if ( Xdwp_Testmode::active() ) : ?>
												<p class="description">
													<?php esc_html_e( 'Faucets:', 'xorro-direct-wallet-payments-woocommerce' ); ?>
													<?php
													$xdwp_faucets = array();
													foreach ( Xdwp_Testmode::networks() as $xdwp_net ) {
														$xdwp_faucets[] = '<a href="' . esc_url( $xdwp_net['faucet'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $xdwp_net['label'] ) . '</a>';
													}
													echo wp_kses_post( implode( ' · ', $xdwp_faucets ) );
													?>
												</p>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Partial and over payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[auto_partial_payments]" value="yes" <?php checked( ( $settings['auto_partial_payments'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Handle them automatically: at least 50% received → the customer is asked for the rest; up to 10% too much → the order completes and the excess is noted.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'A transfer that could belong to more than one open order is never credited automatically — you get an email instead. Turn this off if your payment addresses also receive other money (e.g. exchange withdrawals), since such a transfer could otherwise be taken for an order payment.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Late payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[late_payment_scan]" value="yes" <?php checked( ( $settings['late_payment_scan'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Check orders that expired in the last 7 days and email me if a payment arrives late.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'The order is not changed automatically — you decide whether to complete it or refund.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="xdwp-title"><?php esc_html_e( 'Checkout title', 'xorro-direct-wallet-payments-woocommerce' ); ?></label></th>
										<td>
											<input type="text" class="regular-text cc-input" id="xdwp-title" name="xdwp[title]" value="<?php echo esc_attr( (string) ( $settings['title'] ?? __( 'Pay with Cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' ) ) ); ?>" />
											<p class="description"><?php esc_html_e( 'Payment method name shown at checkout (e.g. “Pay with Cryptocurrency”).', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="xdwp-description"><?php esc_html_e( 'Checkout description', 'xorro-direct-wallet-payments-woocommerce' ); ?></label></th>
										<td>
											<textarea class="large-text cc-input cc-input-textarea" rows="3" id="xdwp-description" name="xdwp[description]"><?php echo esc_textarea( (string) ( $settings['description'] ?? '' ) ); ?></textarea>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Checkout label style', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<div class="cc-radio-group">
												<?php
												$display = $settings['checkout_display'] ?? 'both';
												foreach ( Xdwp_Branding::display_modes() as $mode => $label ) :
													?>
													<label class="cc-radio">
														<input type="radio" name="xdwp[checkout_display]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $display, $mode ); ?> />
														<span><?php echo esc_html( $label ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
											<p class="description"><?php esc_html_e( 'Choose how the payment method is identified on the checkout page.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Checkout icon', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<?php
											$icon_id  = absint( $settings['checkout_icon_id'] ?? 0 );
											$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : Xdwp_Branding::default_icon_url();
											$iw       = absint( $settings['checkout_icon_width'] ?? 32 );
											$ih       = absint( $settings['checkout_icon_height'] ?? 32 );
											?>
											<div class="xdwp-icon-picker" id="xdwp-icon-picker">
												<input type="hidden" name="xdwp[checkout_icon_id]" id="xdwp-icon-id" value="<?php echo esc_attr( (string) $icon_id ); ?>" />
												<div class="xdwp-icon-picker__preview">
													<img src="<?php echo esc_url( $icon_url ? $icon_url : Xdwp_Branding::default_icon_url() ); ?>" alt="" id="xdwp-icon-preview" width="48" height="48" />
												</div>
												<p class="cc-btn-row">
													<button type="button" class="cc-btn cc-btn-secondary" id="xdwp-icon-upload"><?php esc_html_e( 'Upload / replace icon', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
													<button type="button" class="cc-btn cc-btn-secondary" id="xdwp-icon-reset"><?php esc_html_e( 'Use default icon', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
												</p>
												<p class="description"><?php esc_html_e( 'PNG, JPG, GIF, WebP, or SVG. Default plugin icon is used when none is selected.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Icon size (px)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-inline">
												<?php esc_html_e( 'Width', 'xorro-direct-wallet-payments-woocommerce' ); ?>
												<input type="number" class="small-text cc-input" min="16" max="128" name="xdwp[checkout_icon_width]" value="<?php echo esc_attr( (string) $iw ); ?>" />
											</label>
											<label class="cc-inline">
												<?php esc_html_e( 'Height', 'xorro-direct-wallet-payments-woocommerce' ); ?>
												<input type="number" class="small-text cc-input" min="16" max="128" name="xdwp[checkout_icon_height]" value="<?php echo esc_attr( (string) $ih ); ?>" />
											</label>
											<p class="description"><?php esc_html_e( 'Recommended: 24–40px. Allowed range: 16–128.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'WooCommerce gateway', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<a class="cc-btn cc-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xdwp' ) ); ?>">
												<?php esc_html_e( 'Enable gateway in Payments settings', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</a>
										</td>
									</tr>
								</table>

							<?php elseif ( 'coins' === $tab ) : ?>
								<p class="cc-lead"><?php esc_html_e( 'Leave min / max empty for no limit. Coins outside the limits are hidden at checkout — useful where network fees make small orders impractical.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
												<p class="cc-lead"><?php esc_html_e( 'Confirmations shown in grey are what this coin waits for now. Type a number to require your own instead — higher is safer and slower.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
								<p class="cc-lead"><?php esc_html_e( 'A price adjustment is a percentage off (or on) an order paid in that coin — enter -2 to take 2% off, 1.5 to add 1.5%. It appears on the order as its own line, so the total the customer sees is the total they pay.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
								<p class="cc-lead"><?php esc_html_e( 'Auto-verify uses public blockchain APIs. Coins marked Manual (such as Monero) have no free way to detect payments — confirm those with “Mark payment received” on the order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
								<?php
								$sections = array(
									'coins'  => __( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
									'usdt'   => __( 'USDT (multi-network)', 'xorro-direct-wallet-payments-woocommerce' ),
									'usdc'   => __( 'USDC (multi-network)', 'xorro-direct-wallet-payments-woocommerce' ),
									'dai'    => __( 'DAI (multi-network)', 'xorro-direct-wallet-payments-woocommerce' ),
									'tokens' => __( 'Tokens', 'xorro-direct-wallet-payments-woocommerce' ),
								);
								foreach ( $sections as $section_key => $section_label ) :
									if ( empty( $groups[ $section_key ] ) ) {
										continue;
									}
									?>
									<div class="cc-coin-section">
										<h3 class="cc-coin-section__title"><?php echo esc_html( $section_label ); ?></h3>
										<table class="widefat striped xdwp-coins-table">
											<thead>
												<tr>
													<th class="cc-col-on"><?php esc_html_e( 'On', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Network', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Type', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Auto-verify', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th>
														<?php
														echo esc_html(
															sprintf(
																/* translators: %s: store currency code */
																__( 'Order min / max (%s)', 'xorro-direct-wallet-payments-woocommerce' ),
																get_woocommerce_currency()
															)
														);
														?>
													</th>
													<th><?php esc_html_e( 'Confirmations', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
													<th title="<?php esc_attr_e( 'Minus for a discount, plus for a surcharge. Shown to the customer as its own line on the order.', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php esc_html_e( 'Price adjustment', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $groups[ $section_key ] as $id => $coin ) : ?>
													<?php
													$icons       = Xdwp_Coins::icon_meta( $id );
													$coin_limits = Xdwp_Coins::limits( $id );
													$coin_confs  = Xdwp_Settings::get( 'coin_confirmations', array() );
													$coin_conf   = ( is_array( $coin_confs ) && ! empty( $coin_confs[ $id ] ) ) ? (int) $coin_confs[ $id ] : 0;
													$conf_now    = Xdwp_Coins::confirmations_for( $coin );
													$conf_depth  = Xdwp_Coins::reports_depth( $coin );
													$coin_adjust = Xdwp_Prices::coin_adjustment( $id );
													?>
													<tr>
														<td>
															<input type="checkbox" name="xdwp[enabled_coins][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $enabled, true ) ); ?> />
														</td>
														<td>
															<span class="cc-coin-cell">
																<?php if ( ! empty( $icons['icon'] ) ) : ?>
																	<span class="cc-coin-cell__icon" aria-hidden="true">
																		<img src="<?php echo esc_url( $icons['icon'] ); ?>" alt="" width="22" height="22" loading="lazy" decoding="async" style="width:22px;height:22px;max-width:22px;max-height:22px;object-fit:contain;display:block;" />
																	</span>
																<?php endif; ?>
																<span>
																	<strong><?php echo esc_html( $coin['symbol'] ); ?></strong>
																	<span class="cc-coin-cell__name"> — <?php echo esc_html( $coin['name'] ); ?></span>
																</span>
															</span>
														</td>
														<td><code><?php echo esc_html( $coin['network'] ); ?></code></td>
														<td><?php echo esc_html( $coin['type'] ); ?></td>
														<td>
															<?php
															echo Xdwp_Coins::supports_auto_verify( $id )
																? '<span class="cc-pill cc-pill--yes">' . esc_html__( 'Yes', 'xorro-direct-wallet-payments-woocommerce' ) . '</span>'
																: '<span class="cc-pill cc-pill--manual">' . esc_html__( 'Manual', 'xorro-direct-wallet-payments-woocommerce' ) . '</span>';
															?>
														</td>
														<td class="cc-coin-limits">
															<input type="number" min="0" step="0.01" class="small-text" name="xdwp[coin_limits][<?php echo esc_attr( $id ); ?>][min]" value="<?php echo esc_attr( $coin_limits['min'] > 0 ? (string) $coin_limits['min'] : '' ); ?>" placeholder="<?php esc_attr_e( 'min', 'xorro-direct-wallet-payments-woocommerce' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: coin name */ __( 'Minimum order total for %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] ) ); ?>" />
															<input type="number" min="0" step="0.01" class="small-text" name="xdwp[coin_limits][<?php echo esc_attr( $id ); ?>][max]" value="<?php echo esc_attr( $coin_limits['max'] > 0 ? (string) $coin_limits['max'] : '' ); ?>" placeholder="<?php esc_attr_e( 'max', 'xorro-direct-wallet-payments-woocommerce' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: coin name */ __( 'Maximum order total for %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] ) ); ?>" />
														</td>
														<td class="cc-coin-confs">
															<input type="number" min="0" max="<?php echo esc_attr( $conf_depth ? '64' : '1' ); ?>" step="1" class="small-text" name="xdwp[coin_confirmations][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $coin_conf > 0 ? (string) $coin_conf : '' ); ?>" placeholder="<?php echo esc_attr( (string) $conf_now ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: coin name */ __( 'Confirmations required for %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] ) ); ?>" />
															<?php if ( ! $conf_depth ) : ?>
																<span class="cc-coin-confs__note" title="<?php esc_attr_e( 'This network settles a payment the moment it is validated, so there is no depth to wait for.', 'xorro-direct-wallet-payments-woocommerce' ); ?>"><?php esc_html_e( 'final on validation', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
															<?php endif; ?>
														</td>
														<td class="cc-coin-adjust">
															<input type="number" min="-50" max="50" step="0.01" class="small-text" name="xdwp[coin_adjustments][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( 0.0 !== $coin_adjust ? (string) $coin_adjust : '' ); ?>" placeholder="0" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: coin name */ __( 'Discount or surcharge for paying in %s, as a percentage', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] ) ); ?>" />
															<span class="cc-coin-adjust__unit" aria-hidden="true">%</span>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endforeach; ?>

							<?php elseif ( 'alerts' === $tab ) : ?>
								<?php
								// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
								$xdwp_test = isset( $_GET['xdwp_alert_test'] ) ? sanitize_key( wp_unslash( $_GET['xdwp_alert_test'] ) ) : '';
								$xdwp_chosen = Xdwp_Settings::get( 'notify_events', null );
								if ( ! is_array( $xdwp_chosen ) ) {
									$xdwp_chosen = Xdwp_Notify::default_events();
								}
								?>
								<p class="cc-lead"><?php esc_html_e( 'A payment that arrives short, or two days late, needs a person. This sends those events somewhere you will actually see them. Nothing here changes an order — it only reports.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>

								<?php if ( 'sent' === $xdwp_test ) : ?>
									<div class="notice notice-success inline"><p><?php esc_html_e( 'Test alert sent. If nothing arrived, check the address and the secret.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p></div>
								<?php elseif ( 'failed' === $xdwp_test ) : ?>
									<div class="notice notice-error inline"><p><?php esc_html_e( 'The test alert could not be delivered. The endpoint refused it, or this site could not reach it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p></div>
								<?php elseif ( 'none' === $xdwp_test ) : ?>
									<div class="notice notice-warning inline"><p><?php esc_html_e( 'Nothing is set up to receive alerts yet. Add a webhook address or a Telegram bot below, save, then test.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p></div>
								<?php endif; ?>

								<table class="form-table cc-table">
									<tr>
										<th scope="row"><?php esc_html_e( 'Webhook address', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="url" class="regular-text cc-input" name="xdwp[webhook_url]" value="<?php echo esc_attr( (string) Xdwp_Settings::get( 'webhook_url', '' ) ); ?>" placeholder="https://example.com/hooks/crypto" />
											<p class="description"><?php esc_html_e( 'Every event is POSTed there as JSON. Your endpoint should answer 2xx; anything else is retried after one minute, five, then twenty-five, and then given up on.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Signing secret', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[webhook_secret]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'webhook_secret' ) ); ?>" />
											<p class="description">
												<?php esc_html_e( 'With a secret set, each request carries X-Xdwp-Signature: sha256=HMAC(secret, timestamp + "." + body) and X-Xdwp-Timestamp. Check both before you trust a request — the timestamp is what stops an old one being replayed at you. Leave blank to keep the current secret, or type a single hyphen to clear it.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Telegram bot token', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[telegram_token]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'telegram_token' ) ); ?>" />
											<p class="description"><?php esc_html_e( 'Create a bot by messaging @BotFather on Telegram; it gives you a token. Leave blank to keep the current one, or type a single hyphen to clear it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Telegram chat ID', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="text" class="regular-text cc-input" name="xdwp[telegram_chat]" value="<?php echo esc_attr( (string) Xdwp_Settings::get( 'telegram_chat', '' ) ); ?>" placeholder="-1001234567890" />
											<p class="description"><?php esc_html_e( 'Message your bot once, then open api.telegram.org/bot<token>/getUpdates to find the chat id. For a group, add the bot to it first.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'What to report', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="hidden" name="xdwp[notify_events_present]" value="1" />
											<fieldset class="xdwp-events">
												<legend class="screen-reader-text"><?php esc_html_e( 'Events to report', 'xorro-direct-wallet-payments-woocommerce' ); ?></legend>
												<?php foreach ( Xdwp_Notify::events() as $xdwp_event => $xdwp_label ) : ?>
													<label class="xdwp-events__item">
														<input type="checkbox" name="xdwp[notify_events][]" value="<?php echo esc_attr( $xdwp_event ); ?>" <?php checked( in_array( $xdwp_event, $xdwp_chosen, true ) ); ?> />
														<?php echo esc_html( $xdwp_label ); ?>
													</label>
												<?php endforeach; ?>
											</fieldset>
											<p class="description"><?php esc_html_e( 'Expired orders are noisy on a busy shop, which is why they are off to begin with.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Daily summary', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label>
												<input type="checkbox" name="xdwp[digest_daily]" value="yes" <?php checked( Xdwp_Settings::get( 'digest_daily', 'no' ), 'yes' ); ?> />
												<?php esc_html_e( 'Send one message a day: what was paid, what it came to, and what still needs you', 'xorro-direct-wallet-payments-woocommerce' ); ?>
											</label>
											<?php
											$xdwp_next = wp_next_scheduled( 'xdwp_daily_digest' );
											if ( $xdwp_next ) :
												?>
												<p class="description">
													<?php
													printf(
														/* translators: %s: date and time */
														esc_html__( 'Next summary: %s', 'xorro-direct-wallet-payments-woocommerce' ),
														esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $xdwp_next ) )
													);
													?>
												</p>
											<?php endif; ?>
										</td>
									</tr>
								</table>

								<p class="description cc-footnote">
									<?php esc_html_e( 'Save your changes first — the test uses what is stored, not what is on screen.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									<a class="cc-btn cc-btn-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=xdwp_test_alert' ), 'xdwp_test_alert' ) ); ?>"><?php esc_html_e( 'Send a test alert', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
								</p>

							<?php elseif ( 'wallets' === $tab ) : ?>
								<?php include XDWP_PATH . 'includes/admin/views/wallets-ui.php'; ?>

							<?php elseif ( 'prices' === $tab ) : ?>
								<table class="form-table cc-form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'CoinGecko API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[coingecko_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'coingecko_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Fiat↔crypto rates. Free without a key. Demo keys (CG-…) use the public API; Pro keys use the Pro API. Get a key at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://www.coingecko.com/en/api" target="_blank" rel="noopener noreferrer">CoinGecko</a>'
													),
													array(
														'a' => array(
															'href'   => true,
															'target' => true,
															'rel'    => true,
														),
													)
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Kaiascan API key', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[kaiascan_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'kaiascan_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Needed to confirm Kaia (KAIA) payments automatically. Kaia runs no free public index of its own, so without this key payments in Kaia must be confirmed by hand. Free tier at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://kaiascan.io" target="_blank" rel="noopener noreferrer">kaiascan.io</a>'
													),
													array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Blockchair API key', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[blockchair_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'blockchair_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Optional, and only worth it on a busy shop. Dogecoin, Bitcoin Cash, Zcash, Dash and eCash are read through Blockchair, which stops answering once the day\'s free allowance is used — at which point payments in those coins are no longer seen until the next day. A key raises that limit. Get one at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://blockchair.com/api/plans" target="_blank" rel="noopener noreferrer">blockchair.com/api/plans</a>'
													),
													array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Etherscan API V2 key', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[etherscan_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'etherscan_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'One free key covers ETH, BNB, Polygon, Arbitrum, Optimism, Avalanche, Fantom, Cronos, ETC and 50+ EVM chains. Get it at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://etherscan.io/apis" target="_blank" rel="noopener noreferrer">etherscan.io/apis</a>'
													),
													array(
														'a' => array(
															'href'   => true,
															'target' => true,
															'rel'    => true,
														),
													)
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'TronGrid API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[trongrid_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'trongrid_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Recommended for TRX / USDT-TRC20 stability. Free at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://www.trongrid.io/" target="_blank" rel="noopener noreferrer">TronGrid</a>'
													),
													array(
														'a' => array(
															'href'   => true,
															'target' => true,
															'rel'    => true,
														),
													)
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Helius API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[helius_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'helius_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'More stable Solana RPC than the public endpoint. Free tier at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://www.helius.dev/" target="_blank" rel="noopener noreferrer">Helius</a>'
													),
													array(
														'a' => array(
															'href'   => true,
															'target' => true,
															'rel'    => true,
														),
													)
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Aptos API key', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[aptos_api_key]" value="" placeholder="<?php echo esc_attr( Xdwp_Settings::api_key_input_placeholder( 'aptos_api_key' ) ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Required for APT auto-verify — Aptos&#8217;s public indexer rate-limits anonymous requests too aggressively to use without one. Free at %s', 'xorro-direct-wallet-payments-woocommerce' ),
														'<a href="https://geomi.dev/" target="_blank" rel="noopener noreferrer">Aptos Build (geomi.dev)</a>'
													),
													array(
														'a' => array(
															'href'   => true,
															'target' => true,
															'rel'    => true,
														),
													)
												);
												?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Stablecoin pricing', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[stablecoin_peg]" value="yes" <?php checked( ( $settings['stablecoin_peg'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Price stablecoins that track your store currency 1:1 (a 17.34 order asks for exactly 17.34 USDT).', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'Off: the live market rate is used, so the amount is slightly different (e.g. 17.3465 USDT). Applies to USDT, USDC, DAI, TUSD, USDP, GUSD, PYUSD, USDD, USDe and USDJ in USD stores, and EURT in EUR stores.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Show crypto price on products', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="xdwp[price_coin_show]" value="yes" <?php checked( ( $settings['price_coin_show'] ?? 'no' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Display an approximate crypto equivalent near product prices.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Product price coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<select name="xdwp[price_coin_ticker]" class="cc-input cc-input-select">
												<?php
												$ticker = $settings['price_coin_ticker'] ?? 'BTC';
												// This is a pure price-reference display (shown as just the coin's
												// symbol next to the product price, e.g. "/ 50.00 USDT") — the
												// underlying chain used to price it is an implementation detail, so
												// the stablecoin options are labeled by symbol alone rather than
												// their (otherwise meaningful) per-network coin name.
												$labels = array(
													'USDT_ETH' => __( 'USDT', 'xorro-direct-wallet-payments-woocommerce' ),
													'USDC_ETH' => __( 'USDC', 'xorro-direct-wallet-payments-woocommerce' ),
												);
												foreach ( array( 'BTC', 'ETH', 'USDT_ETH', 'USDC_ETH' ) as $opt ) {
													$c = Xdwp_Coins::get( $opt );
													if ( ! $c ) {
														continue;
													}
													$label = isset( $labels[ $opt ] ) ? $labels[ $opt ] : $c['name'];
													printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $ticker, $opt, false ), esc_html( $label ) );
												}
												?>
											</select>
										</td>
									</tr>
								</table>
								<p class="description cc-footnote">
									<?php esc_html_e( 'Bitcoin uses mempool.space with Blockstream fallback (no key needed). BCH/LTC/DOGE use Blockchair. Base/Arbitrum/Optimism and other EVMs use Etherscan V2. XRP/XLM and most alt chains use public APIs. Monero (XMR) stays manual because inbound detection requires a private view key.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
								</p>
							<?php endif; ?>
						</div>

						<div class="cc-footer">
							<button type="submit" name="xdwp_save" class="cc-btn cc-btn-primary" value="1">
								<?php esc_html_e( 'Save changes', 'xorro-direct-wallet-payments-woocommerce' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</form>

	<?php
	// Its own forms, outside the settings form: a form cannot contain another, and a file
	// upload needs a different encoding from the rest of this page. Offered on the three tabs
	// whose contents it can carry, each opening on the part that tab is about.
	$xdwp_backup_tabs = array(
		'general' => Xdwp_Backup::SCOPE_ALL,
		'wallets' => Xdwp_Backup::SCOPE_WALLETS,
		'prices'  => Xdwp_Backup::SCOPE_KEYS,
	);
	if ( isset( $xdwp_backup_tabs[ $tab ] ) && class_exists( 'Xdwp_Backup' ) ) {
		$xdwp_backup_scope = $xdwp_backup_tabs[ $tab ];
		require XDWP_PATH . 'includes/admin/views/backup-ui.php';
	}
	?>

	</div>
</div>
