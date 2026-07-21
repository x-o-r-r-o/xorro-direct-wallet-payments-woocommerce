<?php
/**
 * Wallets management UI (repeatable address rows).
 * Only coins enabled under the Coins tab are listed.
 *
 * @package ChainCheckout
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
		if ( 0 === $addr_count ) {
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
	<div class="chain-checkout-wallet-row">
		<input
			type="text"
			class="chain-checkout-wallet-input regular-text code"
			name="chain_checkout[wallets][<?php echo esc_attr( $id ); ?>][]"
			value="<?php echo esc_attr( $addr ); ?>"
			placeholder="<?php esc_attr_e( 'Paste wallet address', 'xorro-direct-wallet-payments-woocommerce' ); ?>"
			autocomplete="off"
			spellcheck="false"
			data-coin="<?php echo esc_attr( $id ); ?>"
		/>
		<div class="chain-checkout-wallet-row__btns">
			<button type="button" class="button chain-checkout-wallet-copy" data-chain-checkout-action="copy">
				<?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</button>
			<button type="button" class="button chain-checkout-wallet-remove" data-chain-checkout-action="remove" aria-label="<?php esc_attr_e( 'Remove address', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<span class="chain-checkout-wallet-row__status" aria-hidden="true"></span>
	</div>
	<?php
};
?>
<div class="chain-checkout-wallets" id="chain-checkout-wallets" data-total="<?php echo esc_attr( (string) $total ); ?>">

	<header class="chain-checkout-wallets__hero">
		<div class="chain-checkout-wallets__hero-text">
			<h2 class="chain-checkout-wallets__heading"><?php esc_html_e( 'Wallet addresses', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Add one or more receiving addresses for each activated coin. Extra addresses rotate automatically when rotation is on.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<p class="chain-checkout-wallets__hero-link">
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
			<div class="chain-checkout-wallets__stats">
				<div class="chain-checkout-wallets__stat">
					<span class="chain-checkout-wallets__stat-value"><?php echo esc_html( (string) $enabled_count ); ?></span>
					<span class="chain-checkout-wallets__stat-label"><?php esc_html_e( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				</div>
				<div class="chain-checkout-wallets__stat">
					<span class="chain-checkout-wallets__stat-value" id="chain-checkout-wallet-counter-num"><?php echo esc_html( (string) $total ); ?></span>
					<span class="chain-checkout-wallets__stat-label"><?php esc_html_e( 'Addresses', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				</div>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $enabled ) || 0 === $enabled_count ) : ?>
		<div class="chain-checkout-wallets__empty-state">
			<div class="chain-checkout-wallets__empty-icon" aria-hidden="true">◇</div>
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
		<div class="chain-checkout-wallets__toolbar">
			<label class="chain-checkout-wallets__search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<input type="search" id="chain-checkout-wallet-search" placeholder="<?php esc_attr_e( 'Filter by coin, symbol, or network…', 'xorro-direct-wallet-payments-woocommerce' ); ?>" />
			</label>
			<p class="chain-checkout-wallets__missing" id="chain-checkout-wallet-missing" <?php echo $missing_count ? '' : 'hidden'; ?>>
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
			<section class="chain-checkout-wallets__section" data-section="<?php echo esc_attr( $section_key ); ?>">
				<details open>
					<summary class="chain-checkout-wallets__section-title">
						<span class="chain-checkout-wallets__section-label"><?php echo esc_html( $section_label ); ?></span>
						<span class="chain-checkout-wallets__section-meta">
							<span class="chain-checkout-wallets__section-coins">
								<?php
								printf(
									/* translators: %d: number of coins in section */
									esc_html( _n( '%d coin', '%d coins', count( $section_coins ), 'xorro-direct-wallet-payments-woocommerce' ) ),
									count( $section_coins )
								);
								?>
							</span>
							<span class="chain-checkout-wallets__section-count"><?php echo $section_total ? esc_html( (string) $section_total ) : ''; ?></span>
						</span>
					</summary>
					<div class="chain-checkout-wallets__list">
						<?php foreach ( $section_coins as $id => $coin ) : ?>
							<?php
							$addrs  = isset( $wallets[ $id ] ) && is_array( $wallets[ $id ] ) ? array_values( $wallets[ $id ] ) : array();
							$count  = count( $addrs );
							$search = strtolower( $coin['name'] . ' ' . $coin['symbol'] . ' ' . $id . ' ' . $coin['network'] . ' ' . $coin['type'] . ' ' . $coin['platform'] );
							?>
							<div
								class="chain-checkout-wallet-card <?php echo $count ? 'has-addresses' : 'needs-address'; ?>"
								data-coin="<?php echo esc_attr( $id ); ?>"
								data-verifier="<?php echo esc_attr( (string) $coin['verifier'] ); ?>"
								data-search="<?php echo esc_attr( $search ); ?>"
							>
								<div class="chain-checkout-wallet-card__head">
									<div class="chain-checkout-wallet-card__title">
										<span class="chain-checkout-wallet-card__symbol"><?php echo esc_html( $coin['symbol'] ); ?></span>
										<span class="chain-checkout-wallet-card__name"><?php echo esc_html( $coin['name'] ); ?></span>
									</div>
									<div class="chain-checkout-wallet-card__badges">
										<span class="chain-checkout-pill"><?php echo esc_html( $coin['network'] ); ?></span>
										<span class="chain-checkout-pill chain-checkout-pill--muted"><?php echo esc_html( strtoupper( $coin['type'] ) ); ?></span>
										<span class="chain-checkout-wallet-card__count" data-count><?php echo esc_html( (string) $count ); ?></span>
									</div>
								</div>

								<div class="chain-checkout-wallet-rows">
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

								<div class="chain-checkout-wallet-card__actions">
									<button type="button" class="button button-secondary chain-checkout-wallet-add" data-chain-checkout-action="add">
										<?php esc_html_e( '+ Add address', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</button>
									<button type="button" class="button-link chain-checkout-wallet-clear" data-chain-checkout-action="clear" <?php disabled( 0 === $count ); ?>>
										<?php esc_html_e( 'Clear all', 'xorro-direct-wallet-payments-woocommerce' ); ?>
									</button>
									<span class="chain-checkout-wallet-hint" aria-live="polite"></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			</section>
		<?php endforeach; ?>

		<p class="chain-checkout-wallets__empty" id="chain-checkout-wallets-empty" hidden><?php esc_html_e( 'No coins match your search.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>

	<?php endif; ?>
</div>
