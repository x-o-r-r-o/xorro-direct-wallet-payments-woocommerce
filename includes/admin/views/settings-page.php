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

$tabs = array(
	'general' => array(
		'label' => __( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
		'url'   => admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' ),
		'icon'  => 'dashicons-admin-generic',
		'title' => __( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
		'desc'  => __( 'Payment window, order status, checkout branding, and gateway options.', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'coins'   => array(
		'label' => __( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
		'url'   => admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-coins' ),
		'icon'  => 'dashicons-cart',
		'title' => __( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
		'desc'  => __( 'Enable the coins and networks you want to accept. Add at least one wallet for each enabled coin.', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'wallets' => array(
		'label' => __( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
		'url'   => admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-wallets' ),
		'icon'  => 'dashicons-money-alt',
		'title' => __( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
		'desc'  => __( 'Receiving addresses for enabled coins. Use multiple addresses for rotation.', 'xorro-direct-wallet-payments-woocommerce' ),
	),
	'prices'  => array(
		'label' => __( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
		'url'   => admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-prices' ),
		'icon'  => 'dashicons-chart-area',
		'title' => __( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
		'desc'  => __( 'Exchange rates and blockchain API keys for quotes and auto-verification.', 'xorro-direct-wallet-payments-woocommerce' ),
	),
);

$enabled = isset( $settings['enabled_coins'] ) && is_array( $settings['enabled_coins'] ) ? $settings['enabled_coins'] : array();
$wallets = isset( $settings['wallets'] ) && is_array( $settings['wallets'] ) ? $settings['wallets'] : array();
$active  = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tabs['general'];
?>
<div class="wrap xdwp-admin">
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
											<p class="description"><?php esc_html_e( 'Required on-chain confirmations before marking an order paid. Applied across supported explorers/RPCs (fail closed when depth cannot be verified).', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
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
								<p class="cc-lead"><?php esc_html_e( 'Auto-verify uses public blockchain APIs. Monero (XMR) remains Manual — use “Mark payment received” on the order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
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
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $groups[ $section_key ] as $id => $coin ) : ?>
													<?php $icons = Xdwp_Coins::icon_meta( $id ); ?>
													<tr>
														<td>
															<input type="checkbox" name="xdwp[enabled_coins][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $enabled, true ) ); ?> />
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
															echo Xdwp_Coins::supports_auto_verify( $id )
																? '<span class="cc-pill cc-pill--yes">' . esc_html__( 'Yes', 'xorro-direct-wallet-payments-woocommerce' ) . '</span>'
																: '<span class="cc-pill cc-pill--manual">' . esc_html__( 'Manual', 'xorro-direct-wallet-payments-woocommerce' ) . '</span>';
															?>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endforeach; ?>

							<?php elseif ( 'wallets' === $tab ) : ?>
								<?php include XDWP_PATH . 'includes/admin/views/wallets-ui.php'; ?>

							<?php elseif ( 'prices' === $tab ) : ?>
								<table class="form-table cc-form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'CoinGecko API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[coingecko_api_key]" value="<?php echo esc_attr( $settings['coingecko_api_key'] ?? '' ); ?>" autocomplete="new-password" />
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
										<th scope="row"><?php esc_html_e( 'Etherscan API V2 key', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[etherscan_api_key]" value="<?php echo esc_attr( $settings['etherscan_api_key'] ?? '' ); ?>" autocomplete="new-password" />
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
											<input type="password" class="regular-text cc-input" name="xdwp[trongrid_api_key]" value="<?php echo esc_attr( $settings['trongrid_api_key'] ?? '' ); ?>" autocomplete="new-password" />
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
											<input type="password" class="regular-text cc-input" name="xdwp[helius_api_key]" value="<?php echo esc_attr( $settings['helius_api_key'] ?? '' ); ?>" autocomplete="new-password" />
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
										<th scope="row"><?php esc_html_e( 'Subscan API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[subscan_api_key]" value="<?php echo esc_attr( $settings['subscan_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Improves Polkadot (DOT) auto-verify rate limits. Free at %s', 'xorro-direct-wallet-payments-woocommerce' ),
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
										<th scope="row"><?php esc_html_e( 'ViewBlock API key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?></th>
										<td>
											<input type="password" class="regular-text cc-input" name="xdwp[viewblock_api_key]" value="<?php echo esc_attr( $settings['viewblock_api_key'] ?? '' ); ?>" autocomplete="new-password" />
											<p class="description">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: %s: URL */
														__( 'Improves Zilliqa (ZIL) auto-verify reliability. Free at %s', 'xorro-direct-wallet-payments-woocommerce' ),
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
												foreach ( array( 'BTC', 'ETH', 'USDT_ETH', 'USDC_ETH' ) as $opt ) {
													$c = Xdwp_Coins::get( $opt );
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
	</div>
</div>
