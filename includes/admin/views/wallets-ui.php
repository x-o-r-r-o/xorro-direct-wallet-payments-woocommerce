<?php
/**
 * Wallets management UI (repeatable address rows).
 * Only coins enabled under the Coins tab are listed.
 *
 * @package Xdwp
 *
 * @var array $settings
 * @var array $enabled
 * @var array $wallets
 * @var array $groups
 */

defined( 'ABSPATH' ) || exit;

$coins_url = admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-coins' );

$sections = array(
	'coins'  => __( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
	'usdt'   => __( 'USDT', 'xorro-direct-wallet-payments-woocommerce' ),
	'usdc'   => __( 'USDC', 'xorro-direct-wallet-payments-woocommerce' ),
	'dai'    => __( 'DAI', 'xorro-direct-wallet-payments-woocommerce' ),
	'tokens' => __( 'Tokens', 'xorro-direct-wallet-payments-woocommerce' ),
);

$visible_groups = array();
$total          = 0;
$enabled_count  = 0;
$missing_count  = 0;

foreach ( $sections as $section_key => $section_label ) {
	if ( empty( $groups[ $section_key ] ) || ! is_array( $groups[ $section_key ] ) ) {
		continue;
	}
	foreach ( $groups[ $section_key ] as $id => $coin ) {
		if ( ! in_array( $id, $enabled, true ) ) {
			continue;
		}
		if ( ! isset( $visible_groups[ $section_key ] ) ) {
			$visible_groups[ $section_key ] = array();
		}
		$visible_groups[ $section_key ][ $id ] = $coin;
		$enabled_count++;
		$addr_count = ( isset( $wallets[ $id ] ) && is_array( $wallets[ $id ] ) ) ? count( $wallets[ $id ] ) : 0;
		$total     += $addr_count;
		// A coin set up with an extended public key has somewhere to receive, even with no
		// address typed in here.
		if ( 0 === $addr_count && '' === Xdwp_Wallets::get_xpub( $id ) ) {
			$missing_count++;
		}
	}
}

/**
 * Render one address row.
 *
 * @param string $id   Coin ID.
 * @param string $addr Address value.
 */
$render_row = static function ( $id, $addr = '' ) {
	?>
	<div class="xdwp-wallet-row">
		<input
			type="text"
			class="xdwp-wallet-input regular-text code"
			name="xdwp[wallets][<?php echo esc_attr( $id ); ?>][]"
			value="<?php echo esc_attr( $addr ); ?>"
			placeholder="<?php esc_attr_e( 'Paste wallet address', 'xorro-direct-wallet-payments-woocommerce' ); ?>"
			autocomplete="off"
			spellcheck="false"
			data-coin="<?php echo esc_attr( $id ); ?>"
		/>
		<div class="xdwp-wallet-row__btns">
			<button type="button" class="button xdwp-wallet-copy" data-xdwp-action="copy">
				<?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</button>
			<button type="button" class="button xdwp-wallet-remove" data-xdwp-action="remove" aria-label="<?php esc_attr_e( 'Remove address', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<span class="xdwp-wallet-row__status" aria-hidden="true"></span>
	</div>
	<?php
};
?>
<div class="xdwp-wallets" id="xdwp-wallets" data-total="<?php echo esc_attr( (string) $total ); ?>">

	<header class="xdwp-wallets__hero">
		<div class="xdwp-wallets__hero-text">
			<h2 class="xdwp-wallets__heading"><?php esc_html_e( 'Wallet addresses', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Add one or more receiving addresses for each activated coin. Extra addresses rotate automatically when rotation is on.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<p class="xdwp-wallets__hero-link">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Coins settings URL */
						__( 'Manage which coins appear here on the %s tab.', 'xorro-direct-wallet-payments-woocommerce' ),
						'<a href="' . esc_url( $coins_url ) . '">' . esc_html__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
					),
					array( 'a' => array( 'href' => true ) )
				);
				?>
			</p>
		</div>
		<?php if ( $enabled_count > 0 ) : ?>
			<div class="xdwp-wallets__stats">
				<div class="xdwp-wallets__stat">
					<span class="xdwp-wallets__stat-value"><?php echo esc_html( (string) $enabled_count ); ?></span>
					<span class="xdwp-wallets__stat-label"><?php esc_html_e( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				</div>
				<div class="xdwp-wallets__stat">
					<span class="xdwp-wallets__stat-value" id="xdwp-wallet-counter-num"><?php echo esc_html( (string) $total ); ?></span>
					<span class="xdwp-wallets__stat-label"><?php esc_html_e( 'Addresses', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				</div>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $enabled ) || 0 === $enabled_count ) : ?>
		<div class="xdwp-wallets__empty-state">
			<div class="xdwp-wallets__empty-icon" aria-hidden="true">◇</div>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Coins settings URL */
						__( 'No coins are activated yet. Enable coins and tokens under %s, then come back to add wallet addresses.', 'xorro-direct-wallet-payments-woocommerce' ),
						'<a href="' . esc_url( $coins_url ) . '"><strong>' . esc_html__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong></a>'
					),
					array(
						'a'      => array( 'href' => true ),
						'strong' => array(),
					)
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $coins_url ); ?>">
					<?php esc_html_e( 'Go to Coins', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<div class="xdwp-wallets__toolbar">
			<label class="xdwp-wallets__search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<input type="search" id="xdwp-wallet-search" placeholder="<?php esc_attr_e( 'Filter by coin, symbol, or network…', 'xorro-direct-wallet-payments-woocommerce' ); ?>" />
			</label>
			<p class="xdwp-wallets__missing" id="xdwp-wallet-missing" <?php echo $missing_count ? '' : 'hidden'; ?>>
				<?php
				if ( $missing_count ) {
					printf(
						/* translators: %d: coins missing addresses */
						esc_html( _n( '%d coin still needs an address', '%d coins still need an address', $missing_count, 'xorro-direct-wallet-payments-woocommerce' ) ),
						(int) $missing_count
					);
				}
				?>
			</p>
		</div>

		<?php foreach ( $sections as $section_key => $section_label ) : ?>
			<?php
			if ( empty( $visible_groups[ $section_key ] ) ) {
				continue;
			}
			$section_coins = $visible_groups[ $section_key ];
			$section_total = 0;
			foreach ( $section_coins as $sid => $scoin ) {
				if ( isset( $wallets[ $sid ] ) && is_array( $wallets[ $sid ] ) ) {
					$section_total += count( $wallets[ $sid ] );
				}
			}
			?>
			<section class="xdwp-wallets__section" data-section="<?php echo esc_attr( $section_key ); ?>">
				<details open>
					<summary class="xdwp-wallets__section-title">
						<span class="xdwp-wallets__section-label"><?php echo esc_html( $section_label ); ?></span>
						<span class="xdwp-wallets__section-meta">
							<span class="xdwp-wallets__section-coins">
								<?php
								printf(
									/* translators: %d: number of coins in section */
									esc_html( _n( '%d coin', '%d coins', count( $section_coins ), 'xorro-direct-wallet-payments-woocommerce' ) ),
									count( $section_coins )
								);
								?>
							</span>
							<span class="xdwp-wallets__section-count"><?php echo $section_total ? esc_html( (string) $section_total ) : ''; ?></span>
						</span>
					</summary>
					<div class="xdwp-wallets__list">
						<?php foreach ( $section_coins as $id => $coin ) : ?>
							<?php
							$addrs  = isset( $wallets[ $id ] ) && is_array( $wallets[ $id ] ) ? array_values( $wallets[ $id ] ) : array();
							$count  = count( $addrs );
							$search = strtolower( $coin['name'] . ' ' . $coin['symbol'] . ' ' . $id . ' ' . $coin['network'] . ' ' . $coin['type'] . ' ' . $coin['platform'] );
							?>
							<div
								class="xdwp-wallet-card <?php echo $count ? 'has-addresses' : 'needs-address'; ?>"
								data-coin="<?php echo esc_attr( $id ); ?>"
								data-verifier="<?php echo esc_attr( (string) $coin['verifier'] ); ?>"
								data-search="<?php echo esc_attr( $search ); ?>"
							>
								<div class="xdwp-wallet-card__head">
									<div class="xdwp-wallet-card__title">
										<?php $card_icons = Xdwp_Coins::icon_meta( $id ); ?>
										<?php if ( ! empty( $card_icons['icon'] ) ) : ?>
											<span class="xdwp-wallet-card__icon" aria-hidden="true">
												<img src="<?php echo esc_url( $card_icons['icon'] ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" />
												<?php if ( ! empty( $card_icons['badge'] ) ) : ?>
													<img class="xdwp-wallet-card__badge" src="<?php echo esc_url( $card_icons['badge'] ); ?>" alt="" width="12" height="12" loading="lazy" decoding="async" />
												<?php endif; ?>
											</span>
										<?php endif; ?>
										<span class="xdwp-wallet-card__symbol"><?php echo esc_html( $coin['symbol'] ); ?></span>
										<span class="xdwp-wallet-card__name"><?php echo esc_html( $coin['name'] ); ?></span>
									</div>
									<div class="xdwp-wallet-card__badges">
										<span class="xdwp-pill"><?php echo esc_html( $coin['network'] ); ?></span>
										<span class="xdwp-pill xdwp-pill--muted"><?php echo esc_html( strtoupper( $coin['type'] ) ); ?></span>
										<span class="xdwp-wallet-card__count" data-count><?php echo esc_html( (string) $count ); ?></span>
									</div>
								</div>

								<?php if ( in_array( (string) $coin['verifier'], array( 'waves' ), true ) ) : ?>
									<p class="xdwp-wallet-card__warning">
										<?php esc_html_e( 'This network is matched by address + amount only — a destination tag/memo is not read here. Use an address only this store controls, never a shared or exchange-hosted address, or unrelated payments to the same address could be misattributed.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</p>
								<?php endif; ?>

								<?php if ( '' !== Xdwp_Coins::memo_kind( $coin ) ) : ?>
									<p class="xdwp-wallet-card__note">
										<?php esc_html_e( 'Each order also gets its own destination tag / memo, shown to the customer and checked against the payment — so an address shared with other orders still tells payments apart.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</p>
								<?php endif; ?>

								<?php $xdwp_hd_coins = Xdwp_Hd::supported_coins(); ?>
								<?php if ( isset( $xdwp_hd_coins[ $id ] ) ) : ?>
									<?php
									$xdwp_key   = Xdwp_Settings::get( 'xpubs', array() );
									$xdwp_key   = ( is_array( $xdwp_key ) && isset( $xdwp_key[ $id ] ) ) ? (string) $xdwp_key[ $id ] : '';
									$xdwp_kinds = implode( ', ', $xdwp_hd_coins[ $id ] );
									?>
									<div class="xdwp-wallet-card__hd">
										<label class="xdwp-wallet-card__hd-label" for="xdwp-xpub-<?php echo esc_attr( $id ); ?>">
											<?php esc_html_e( 'Extended public key (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?>
										</label>
										<input
											type="text"
											class="widefat code"
											id="xdwp-xpub-<?php echo esc_attr( $id ); ?>"
											name="xdwp[xpubs][<?php echo esc_attr( $id ); ?>]"
											value="<?php echo esc_attr( $xdwp_key ); ?>"
											spellcheck="false"
											autocomplete="off"
											placeholder="<?php echo esc_attr( $xdwp_kinds ); ?>"
										/>
										<p class="xdwp-wallet-card__hd-hint">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: accepted key prefixes, e.g. "xpub, ypub, zpub" */
													__( 'Paste the receiving account key from your own wallet (%s) and every order gets an address of its own, so no two payments can be confused. This is a public key: it can only create addresses, never spend. Never paste a private key (xprv, yprv, zprv) or a seed phrase.', 'xorro-direct-wallet-payments-woocommerce' ),
													$xdwp_kinds
												)
											);
											?>
										</p>
										<?php if ( '' !== $xdwp_key && Xdwp_Hd::is_valid( $xdwp_key, $id ) ) : ?>
											<p class="xdwp-wallet-card__hd-preview">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: a derived receiving address */
														__( 'The next order will be sent to: %s — check this against your wallet before taking payments.', 'xorro-direct-wallet-payments-woocommerce' ),
														Xdwp_Hd::address( $xdwp_key, (int) get_option( 'xdwp_hd_idx_' . sanitize_key( $id ), 0 ), $id )
													)
												);
												?>
											</p>
											<?php
											$xdwp_used = (int) get_option( 'xdwp_hd_idx_' . sanitize_key( $id ), 0 );
											$xdwp_gap  = Xdwp_Wallets::hd_gap( $id );
											?>
											<?php if ( $xdwp_used > 0 ) : ?>
												<p class="<?php echo $xdwp_gap >= 15 ? 'xdwp-wallet-card__warning' : 'xdwp-wallet-card__hd-hint'; ?>">
													<?php
													echo esc_html(
														sprintf(
															/* translators: 1: addresses handed out, 2: addresses ahead of the last payment */
															__( '%1$d addresses handed out, %2$d of them ahead of your last received payment.', 'xorro-direct-wallet-payments-woocommerce' ),
															$xdwp_used,
															$xdwp_gap
														)
													);
													?>
													<?php if ( $xdwp_gap >= 15 ) : ?>
														<?php esc_html_e( 'Most wallets only look twenty addresses ahead, so yours may be about to stop showing new payments. Rescan it (or raise its gap limit) and check nothing has been missed.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
													<?php else : ?>
														<?php esc_html_e( 'Unpaid orders use one up, though an expired order gives its address back. If your wallet ever stops showing new payments, rescan it.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
													<?php endif; ?>
												</p>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<div class="xdwp-wallet-rows">
									<?php
									if ( empty( $addrs ) ) {
										$render_row( $id, '' );
									} else {
										foreach ( $addrs as $addr ) {
											$render_row( $id, $addr );
										}
									}
									?>
								</div>

								<div class="xdwp-wallet-card__actions">
									<button type="button" class="button button-secondary xdwp-wallet-add" data-xdwp-action="add">
										<?php esc_html_e( '+ Add address', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</button>
									<button type="button" class="button-link xdwp-wallet-clear" data-xdwp-action="clear" <?php disabled( 0 === $count ); ?>>
										<?php esc_html_e( 'Clear all', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</button>
									<span class="xdwp-wallet-hint" aria-live="polite"></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			</section>
		<?php endforeach; ?>

		<p class="xdwp-wallets__empty" id="xdwp-wallets-empty" hidden><?php esc_html_e( 'No coins match your search.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>

	<?php endif; ?>
</div>
