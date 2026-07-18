<?php
/**
 * Admin settings UI — Cryptoniq-inspired options shell.
 *
 * @package ChainCheckout
 *
 * @var string $tab
 * @var array  $settings
 * @var array  $groups
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'general' => array(
		'label' => __( 'General', 'chain-checkout' ),
		'url'   => admin_url( 'admin.php?page=chain-checkout' ),
		'icon'  => 'dashicons-admin-generic',
		'title' => __( 'General', 'chain-checkout' ),
		'desc'  => __( 'Payment window, order status, checkout branding, and gateway options.', 'chain-checkout' ),
	),
	'coins'   => array(
		'label' => __( 'Coins', 'chain-checkout' ),
		'url'   => admin_url( 'admin.php?page=chain-checkout-coins' ),
		'icon'  => 'dashicons-cart',
		'title' => __( 'Coins', 'chain-checkout' ),
		'desc'  => __( 'Enable the coins and networks you want to accept. Add at least one wallet for each enabled coin.', 'chain-checkout' ),
	),
	'wallets' => array(
		'label' => __( 'Wallets', 'chain-checkout' ),
		'url'   => admin_url( 'admin.php?page=chain-checkout-wallets' ),
		'icon'  => 'dashicons-money-alt',
		'title' => __( 'Wallets', 'chain-checkout' ),
		'desc'  => __( 'Receiving addresses for enabled coins. Use multiple addresses for rotation.', 'chain-checkout' ),
	),
	'prices'  => array(
		'label' => __( 'Prices & APIs', 'chain-checkout' ),
		'url'   => admin_url( 'admin.php?page=chain-checkout-prices' ),
		'icon'  => 'dashicons-chart-area',
		'title' => __( 'Prices & APIs', 'chain-checkout' ),
		'desc'  => __( 'Exchange rates and blockchain API keys for quotes and auto-verification.', 'chain-checkout' ),
	),
);

$enabled = isset( $settings['enabled_coins'] ) && is_array( $settings['enabled_coins'] ) ? $settings['enabled_coins'] : array();
$wallets = isset( $settings['wallets'] ) && is_array( $settings['wallets'] ) ? $settings['wallets'] : array();
$active  = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tabs['general'];
?>
<div class="wrap chain-checkout-admin">
	<div class="chain-checkout-options-wrap">
		<div class="cc-header">
			<div class="cc-header-title">
				<h1><?php esc_html_e( 'Chain Checkout', 'chain-checkout' ); ?></h1>
				<span class="cc-version"><?php echo esc_html( 'v' . CHAIN_CHECKOUT_VERSION ); ?></span>
			</div>
			<div class="cc-header-extra">
				<span class="cc-mode-badge"><?php esc_html_e( 'Direct to wallet', 'chain-checkout' ); ?></span>
			</div>
		</div>

		<form method="post" action="" class="cc-form">
			<?php wp_nonce_field( 'chain_checkout_save_settings', 'chain_checkout_nonce' ); ?>

			<div class="cc-layout">
				<nav class="cc-tabs" aria-label="<?php esc_attr_e( 'Chain Checkout settings', 'chain-checkout' ); ?>">
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
										<th scope="row"><?php esc_html_e( 'Payment window (minutes)', 'chain-checkout' ); ?></th>
										<td>
											<input type="number" min="5" max="1440" name="chain_checkout[payment_window]" value="<?php echo esc_attr( (string) ( $settings['payment_window'] ?? 60 ) ); ?>" class="small-text cc-input" />
											<p class="description"><?php esc_html_e( 'Quoted crypto amount is valid for this duration. Default: 60.', 'chain-checkout' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Order status after payment', 'chain-checkout' ); ?></th>
										<td>
											<select name="chain_checkout[order_status]" class="cc-input cc-input-select">
												<?php
												$statuses = array(
													'processing' => __( 'Processing', 'chain-checkout' ),
													'completed'  => __( 'Completed', 'chain-checkout' ),
													'on-hold'    => __( 'On Hold', 'chain-checkout' ),
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
										<th scope="row"><?php esc_html_e( 'Underpayment tolerance (%)', 'chain-checkout' ); ?></th>
										<td>
											<input type="number" step="0.1" min="0" max="10" name="chain_checkout[underpayment_percent]" value="<?php echo esc_attr( (string) ( $settings['underpayment_percent'] ?? 1 ) ); ?>" class="small-text cc-input" />
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Unique payment amounts', 'chain-checkout' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="chain_checkout[unique_amounts]" value="yes" <?php checked( ( $settings['unique_amounts'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Add a tiny unique dust amount so payments to reused addresses can be matched reliably.', 'chain-checkout' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Wallet rotation', 'chain-checkout' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="chain_checkout[wallet_rotation]" value="yes" <?php checked( ( $settings['wallet_rotation'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Rotate through multiple addresses per coin when available.', 'chain-checkout' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Automatic verification', 'chain-checkout' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="chain_checkout[auto_verify]" value="yes" <?php checked( ( $settings['auto_verify'] ?? 'yes' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Poll public block explorers / RPCs and mark orders paid when payment is detected.', 'chain-checkout' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="chain-checkout-title"><?php esc_html_e( 'Checkout title', 'chain-checkout' ); ?></label></th>
										<td>
											<input type="text" class="regular-text cc-input" id="chain-checkout-title" name="chain_checkout[title]" value="<?php echo esc_attr( (string) ( $settings['title'] ?? __( 'Pay with Cryptocurrency', 'chain-checkout' ) ) ); ?>" />
											<p class="description"><?php esc_html_e( 'Payment method name shown at checkout (e.g. “Pay with Cryptocurrency”).', 'chain-checkout' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="chain-checkout-description"><?php esc_html_e( 'Checkout description', 'chain-checkout' ); ?></label></th>
										<td>
											<textarea class="large-text cc-input cc-input-textarea" rows="3" id="chain-checkout-description" name="chain_checkout[description]"><?php echo esc_textarea( (string) ( $settings['description'] ?? '' ) ); ?></textarea>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Checkout label style', 'chain-checkout' ); ?></th>
										<td>
											<div class="cc-radio-group">
												<?php
												$display = $settings['checkout_display'] ?? 'both';
												foreach ( Chain_Checkout_Branding::display_modes() as $mode => $label ) :
													?>
													<label class="cc-radio">
														<input type="radio" name="chain_checkout[checkout_display]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $display, $mode ); ?> />
														<span><?php echo esc_html( $label ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
											<p class="description"><?php esc_html_e( 'Choose how the payment method is identified on the checkout page.', 'chain-checkout' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Checkout icon', 'chain-checkout' ); ?></th>
										<td>
											<?php
											$icon_id  = absint( $settings['checkout_icon_id'] ?? 0 );
											$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : Chain_Checkout_Branding::default_icon_url();
											$iw       = absint( $settings['checkout_icon_width'] ?? 32 );
											$ih       = absint( $settings['checkout_icon_height'] ?? 32 );
											?>
											<div class="chain-checkout-icon-picker" id="chain-checkout-icon-picker">
												<input type="hidden" name="chain_checkout[checkout_icon_id]" id="chain-checkout-icon-id" value="<?php echo esc_attr( (string) $icon_id ); ?>" />
												<div class="chain-checkout-icon-picker__preview">
													<img src="<?php echo esc_url( $icon_url ? $icon_url : Chain_Checkout_Branding::default_icon_url() ); ?>" alt="" id="chain-checkout-icon-preview" width="48" height="48" />
												</div>
												<p class="cc-btn-row">
													<button type="button" class="cc-btn cc-btn-secondary" id="chain-checkout-icon-upload"><?php esc_html_e( 'Upload / replace icon', 'chain-checkout' ); ?></button>
													<button type="button" class="cc-btn cc-btn-secondary" id="chain-checkout-icon-reset"><?php esc_html_e( 'Use default icon', 'chain-checkout' ); ?></button>
												</p>
												<p class="description"><?php esc_html_e( 'PNG, JPG, GIF, WebP, or SVG. Default plugin icon is used when none is selected.', 'chain-checkout' ); ?></p>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Icon size (px)', 'chain-checkout' ); ?></th>
										<td>
											<label class="cc-inline">
												<?php esc_html_e( 'Width', 'chain-checkout' ); ?>
												<input type="number" class="small-text cc-input" min="16" max="128" name="chain_checkout[checkout_icon_width]" value="<?php echo esc_attr( (string) $iw ); ?>" />
											</label>
											<label class="cc-inline">
												<?php esc_html_e( 'Height', 'chain-checkout' ); ?>
												<input type="number" class="small-text cc-input" min="16" max="128" name="chain_checkout[checkout_icon_height]" value="<?php echo esc_attr( (string) $ih ); ?>" />
											</label>
											<p class="description"><?php esc_html_e( 'Recommended: 24–40px. Allowed range: 16–128.', 'chain-checkout' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'WooCommerce gateway', 'chain-checkout' ); ?></th>
										<td>
											<a class="cc-btn cc-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=chain_checkout' ) ); ?>">
												<?php esc_html_e( 'Enable gateway in Payments settings', 'chain-checkout' ); ?>
											</a>
										</td>
									</tr>
								</table>

							<?php elseif ( 'coins' === $tab ) : ?>
								<p class="cc-lead"><?php esc_html_e( 'Auto-verify uses public blockchain APIs. Monero (XMR) remains Manual — use “Mark payment received” on the order.', 'chain-checkout' ); ?></p>
								<?php
								$sections = array(
									'coins'  => __( 'Coins', 'chain-checkout' ),
									'usdt'   => __( 'USDT (multi-network)', 'chain-checkout' ),
									'usdc'   => __( 'USDC (multi-network)', 'chain-checkout' ),
									'tokens' => __( 'Tokens', 'chain-checkout' ),
								);
								foreach ( $sections as $section_key => $section_label ) :
									if ( empty( $groups[ $section_key ] ) ) {
										continue;
									}
									?>
									<div class="cc-coin-section">
										<h3 class="cc-coin-section__title"><?php echo esc_html( $section_label ); ?></h3>
										<table class="widefat striped chain-checkout-coins-table">
											<thead>
												<tr>
													<th class="cc-col-on"><?php esc_html_e( 'On', 'chain-checkout' ); ?></th>
													<th><?php esc_html_e( 'Coin', 'chain-checkout' ); ?></th>
													<th><?php esc_html_e( 'Network', 'chain-checkout' ); ?></th>
													<th><?php esc_html_e( 'Type', 'chain-checkout' ); ?></th>
													<th><?php esc_html_e( 'Auto-verify', 'chain-checkout' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $groups[ $section_key ] as $id => $coin ) : ?>
													<?php $icons = Chain_Checkout_Coins::icon_meta( $id ); ?>
													<tr>
														<td>
															<input type="checkbox" name="chain_checkout[enabled_coins][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $enabled, true ) ); ?> />
														</td>
														<td>
															<span class="cc-coin-cell">
																<?php if ( ! empty( $icons['icon'] ) ) : ?>
																	<span class="cc-coin-cell__icon" aria-hidden="true">
																		<img src="<?php echo esc_url( $icons['icon'] ); ?>" alt="" width="22" height="22" decoding="async" style="width:22px;height:22px;max-width:22px;max-height:22px;object-fit:contain;display:block;" />
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
															echo Chain_Checkout_Coins::supports_auto_verify( $id )
																? '<span class="cc-pill cc-pill--yes">' . esc_html__( 'Yes', 'chain-checkout' ) . '</span>'
																: '<span class="cc-pill cc-pill--manual">' . esc_html__( 'Manual', 'chain-checkout' ) . '</span>';
															?>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endforeach; ?>

							<?php elseif ( 'wallets' === $tab ) : ?>
								<?php include CHAIN_CHECKOUT_PATH . 'includes/admin/views/wallets-ui.php'; ?>

							<?php elseif ( 'prices' === $tab ) : ?>
								<table class="form-table cc-form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'CoinGecko API key (optional)', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[coingecko_api_key]" value="<?php echo esc_attr( $settings['coingecko_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Fiat↔crypto rates. Free without a key. Demo keys (CG-…) use the public API; Pro keys use the Pro API. Get a key at %s', 'chain-checkout' ),
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
										<th scope="row"><?php esc_html_e( 'Etherscan API V2 key', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[etherscan_api_key]" value="<?php echo esc_attr( $settings['etherscan_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'One free key covers ETH, BNB, Polygon, Arbitrum, Optimism, Avalanche, Fantom, Cronos, ETC and 50+ EVM chains. Get it at %s', 'chain-checkout' ),
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
										<th scope="row"><?php esc_html_e( 'TronGrid API key (optional)', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[trongrid_api_key]" value="<?php echo esc_attr( $settings['trongrid_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Recommended for TRX / USDT-TRC20 stability. Free at %s', 'chain-checkout' ),
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
										<th scope="row"><?php esc_html_e( 'Helius API key (optional)', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[helius_api_key]" value="<?php echo esc_attr( $settings['helius_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'More stable Solana RPC than the public endpoint. Free tier at %s', 'chain-checkout' ),
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
										<th scope="row"><?php esc_html_e( 'Subscan API key (optional)', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[subscan_api_key]" value="<?php echo esc_attr( $settings['subscan_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Improves Polkadot (DOT) auto-verify rate limits. Free at %s', 'chain-checkout' ),
														'<a href="https://www.subscan.io/" target="_blank" rel="noopener noreferrer">Subscan</a>'
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
										<th scope="row"><?php esc_html_e( 'ViewBlock API key (optional)', 'chain-checkout' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="chain_checkout[viewblock_api_key]" value="<?php echo esc_attr( $settings['viewblock_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Improves Zilliqa (ZIL) auto-verify reliability. Free at %s', 'chain-checkout' ),
														'<a href="https://viewblock.io/api" target="_blank" rel="noopener noreferrer">ViewBlock</a>'
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
										<th scope="row"><?php esc_html_e( 'Show crypto price on products', 'chain-checkout' ); ?></th>
										<td>
											<label class="cc-check">
												<input type="checkbox" name="chain_checkout[price_coin_show]" value="yes" <?php checked( ( $settings['price_coin_show'] ?? 'no' ), 'yes' ); ?> />
												<span><?php esc_html_e( 'Display an approximate crypto equivalent near product prices.', 'chain-checkout' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Product price coin', 'chain-checkout' ); ?></th>
										<td>
											<select name="chain_checkout[price_coin_ticker]" class="cc-input cc-input-select">
												<?php
												$ticker = $settings['price_coin_ticker'] ?? 'BTC';
												foreach ( array( 'BTC', 'ETH', 'USDT_ETH', 'USDC_ETH' ) as $opt ) {
													$c = Chain_Checkout_Coins::get( $opt );
													if ( ! $c ) {
														continue;
													}
													printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $ticker, $opt, false ), esc_html( $c['name'] ) );
												}
												?>
											</select>
										</td>
									</tr>
								</table>
								<p class="description cc-footnote">
									<?php esc_html_e( 'Bitcoin uses mempool.space with Blockstream fallback (no key needed). LTC/DOGE use Blockchair. XRP/XLM and most alt chains use public APIs. Monero (XMR) stays manual because inbound detection requires a private view key.', 'chain-checkout' ); ?>
								</p>
							<?php endif; ?>
						</div>

						<div class="cc-footer">
							<button type="submit" name="chain_checkout_save" class="cc-btn cc-btn-primary" value="1">
								<?php esc_html_e( 'Save changes', 'chain-checkout' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>
