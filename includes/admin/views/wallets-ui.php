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

$coins_url = admin_url( 'admin.php?page=chain-checkout-coins' );

$sections = array(
	'coins'  => __( 'Coins', 'chain-checkout' ),
	'usdt'   => __( 'USDT', 'chain-checkout' ),
	'usdc'   => __( 'USDC', 'chain-checkout' ),
	'tokens' => __( 'Tokens', 'chain-checkout' ),
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
			placeholder="<?php esc_attr_e( 'Paste wallet address', 'chain-checkout' ); ?>"
			autocomplete="off"
			spellcheck="false"
			data-coin="<?php echo esc_attr( $id ); ?>"
		/>
		<div class="chain-checkout-wallet-row__btns">
			<button type="button" class="button chain-checkout-wallet-copy" data-chain-checkout-action="copy">
				<?php esc_html_e( 'Copy', 'chain-checkout' ); ?>
			</button>
			<button type="button" class="button chain-checkout-wallet-remove" data-chain-checkout-action="remove" aria-label="<?php esc_attr_e( 'Remove address', 'chain-checkout' ); ?>">
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
			<h2 class="chain-checkout-wallets__heading"><?php esc_html_e( 'Wallet addresses', 'chain-checkout' ); ?></h2>
			<p><?php esc_html_e( 'Add one or more receiving addresses for each activated coin. Extra addresses rotate automatically when rotation is on.', 'chain-checkout' ); ?></p>
			<p class="chain-checkout-wallets__hero-link">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Coins settings URL */
						__( 'Manage which coins appear here on the %s tab.', 'chain-checkout' ),
						'<a href="' . esc_url( $coins_url ) . '">' . esc_html__( 'Coins', 'chain-checkout' ) . '</a>'
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
					<span class="chain-checkout-wallets__stat-label"><?php esc_html_e( 'Coins', 'chain-checkout' ); ?></span>
				</div>
				<div class="chain-checkout-wallets__stat">
					<span class="chain-checkout-wallets__stat-value" id="chain-checkout-wallet-counter-num"><?php echo esc_html( (string) $total ); ?></span>
					<span class="chain-checkout-wallets__stat-label"><?php esc_html_e( 'Addresses', 'chain-checkout' ); ?></span>
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
						__( 'No coins are activated yet. Enable coins and tokens under %s, then come back to add wallet addresses.', 'chain-checkout' ),
						'<a href="' . esc_url( $coins_url ) . '"><strong>' . esc_html__( 'Coins', 'chain-checkout' ) . '</strong></a>'
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
					<?php esc_html_e( 'Go to Coins', 'chain-checkout' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<div class="chain-checkout-wallets__toolbar">
			<label class="chain-checkout-wallets__search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search coins', 'chain-checkout' ); ?></span>
				<input type="search" id="chain-checkout-wallet-search" placeholder="<?php esc_attr_e( 'Filter by coin, symbol, or network…', 'chain-checkout' ); ?>" />
			</label>
			<p class="chain-checkout-wallets__missing" id="chain-checkout-wallet-missing" <?php echo $missing_count ? '' : 'hidden'; ?>>
				<?php
				if ( $missing_count ) {
					printf(
						/* translators: %d: coins missing addresses */
						esc_html( _n( '%d coin still needs an address', '%d coins still need an address', $missing_count, 'chain-checkout' ) ),
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
									esc_html( _n( '%d coin', '%d coins', count( $section_coins ), 'chain-checkout' ) ),
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
										<?php esc_html_e( '+ Add address', 'chain-checkout' ); ?>
									</button>
									<button type="button" class="button-link chain-checkout-wallet-clear" data-chain-checkout-action="clear" <?php disabled( 0 === $count ); ?>>
										<?php esc_html_e( 'Clear all', 'chain-checkout' ); ?>
									</button>
									<span class="chain-checkout-wallet-hint" aria-live="polite"></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			</section>
		<?php endforeach; ?>

		<p class="chain-checkout-wallets__empty" id="chain-checkout-wallets-empty" hidden><?php esc_html_e( 'No coins match your search.', 'chain-checkout' ); ?></p>

		<?php
		// Inline script so Add/Remove works even if the external admin.js asset fails to load.
		$cc_wallets_i18n = array(
			'placeholder'   => __( 'Paste wallet address', 'chain-checkout' ),
			'copy'          => __( 'Copy', 'chain-checkout' ),
			'copied'        => __( 'Copied', 'chain-checkout' ),
			'remove'        => __( 'Remove address', 'chain-checkout' ),
			'invalidFormat' => __( 'Invalid format', 'chain-checkout' ),
			'duplicate'     => __( 'Duplicate address', 'chain-checkout' ),
			'missing'       => __( '%d coin(s) still need an address', 'chain-checkout' ),
		);
		?>
		<script id="chain-checkout-wallets-inline">
		(function () {
			if (window.__chainCheckoutWalletsBound) { return; }
			window.__chainCheckoutWalletsBound = true;

			var i18n = <?php echo wp_json_encode( $cc_wallets_i18n ); ?>;
			var PATTERNS = {
				btc: /^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				ltc: /^(ltc1|[LM3])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				doge: /^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/,
				eth: /^0x[a-fA-F0-9]{40}$/,
				arbitrum: /^0x[a-fA-F0-9]{40}$/,
				optimism: /^0x[a-fA-F0-9]{40}$/,
				bsc: /^0x[a-fA-F0-9]{40}$/,
				bnb: /^0x[a-fA-F0-9]{40}$/,
				matic: /^0x[a-fA-F0-9]{40}$/,
				avax: /^0x[a-fA-F0-9]{40}$/,
				ftm: /^0x[a-fA-F0-9]{40}$/,
				cro: /^0x[a-fA-F0-9]{40}$/,
				etc: /^0x[a-fA-F0-9]{40}$/,
				sol: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
				solana: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
				trx: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
				tron: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
				xrp: /^r[1-9A-HJ-NP-Za-km-z]{24,34}$/,
				xlm: /^G[A-Z2-7]{55}$/
			};

			function el(tag, attrs, html) {
				var node = document.createElement(tag);
				if (attrs) {
					Object.keys(attrs).forEach(function (k) {
						if (k === 'className') { node.className = attrs[k]; }
						else if (k === 'text') { node.textContent = attrs[k]; }
						else { node.setAttribute(k, attrs[k]); }
					});
				}
				if (html) { node.innerHTML = html; }
				return node;
			}

			function isPlausible(verifier, address) {
				if (!address) return true;
				if (PATTERNS[verifier]) return PATTERNS[verifier].test(address);
				if (verifier === 'xmr') return address.length >= 95 && address.length <= 110;
				return address.length >= 8 && address.length <= 128;
			}

			function createRow(coinId) {
				var row = el('div', { className: 'chain-checkout-wallet-row' });
				var input = el('input', {
					type: 'text',
					className: 'chain-checkout-wallet-input regular-text code',
					name: 'chain_checkout[wallets][' + coinId + '][]',
					placeholder: i18n.placeholder,
					autocomplete: 'off',
					spellcheck: 'false',
					'data-coin': coinId
				});
				input.value = '';
				var btns = el('div', { className: 'chain-checkout-wallet-row__btns' });
				var copyBtn = el('button', { type: 'button', className: 'button chain-checkout-wallet-copy', 'data-chain-checkout-action': 'copy', text: i18n.copy });
				var removeBtn = el('button', { type: 'button', className: 'button chain-checkout-wallet-remove', 'data-chain-checkout-action': 'remove', 'aria-label': i18n.remove }, '<span aria-hidden="true">&times;</span>');
				var status = el('span', { className: 'chain-checkout-wallet-row__status', 'aria-hidden': 'true' });
				btns.appendChild(copyBtn);
				btns.appendChild(removeBtn);
				row.appendChild(input);
				row.appendChild(btns);
				row.appendChild(status);
				return row;
			}

			function countFilled(card) {
				var n = 0, inputs = card.querySelectorAll('.chain-checkout-wallet-input'), i;
				for (i = 0; i < inputs.length; i++) {
					if ((inputs[i].value || '').trim() !== '') n++;
				}
				return n;
			}

			function updateCard(card) {
				var count = countFilled(card);
				var badge = card.querySelector('[data-count]');
				if (badge) badge.textContent = String(count);
				card.classList.toggle('has-addresses', count > 0);
				card.classList.toggle('needs-address', count === 0);
				var clearBtn = card.querySelector('[data-chain-checkout-action="clear"]');
				if (clearBtn) clearBtn.disabled = count === 0;
			}

			function updateTotals() {
				var root = document.getElementById('chain-checkout-wallets');
				if (!root) return;
				var total = 0, missing = 0;
				var cards = root.querySelectorAll('.chain-checkout-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					var c = countFilled(cards[i]);
					total += c;
					if (c === 0) missing++;
				}
				var num = document.getElementById('chain-checkout-wallet-counter-num');
				if (num) num.textContent = String(total);
				root.setAttribute('data-total', String(total));

				var sections = root.querySelectorAll('.chain-checkout-wallets__section');
				for (i = 0; i < sections.length; i++) {
					var n = 0, sc = sections[i].querySelectorAll('.chain-checkout-wallet-card'), j;
					for (j = 0; j < sc.length; j++) n += countFilled(sc[j]);
					var elCount = sections[i].querySelector('.chain-checkout-wallets__section-count');
					if (elCount) elCount.textContent = n ? String(n) : '';
				}

				var miss = document.getElementById('chain-checkout-wallet-missing');
				if (miss) {
					if (missing > 0) {
						miss.hidden = false;
						miss.textContent = (i18n.missing || '%d coin(s) still need an address').replace('%d', String(missing));
					} else {
						miss.hidden = true;
						miss.textContent = '';
					}
				}
			}

			function highlight(card) {
				var seen = {}, verifier = card.getAttribute('data-verifier') || '', hint = '';
				var rows = card.querySelectorAll('.chain-checkout-wallet-row'), i;
				for (i = 0; i < rows.length; i++) {
					var row = rows[i];
					var input = row.querySelector('.chain-checkout-wallet-input');
					var status = row.querySelector('.chain-checkout-wallet-row__status');
					var val = input ? (input.value || '').trim() : '';
					row.classList.remove('is-duplicate', 'is-invalid');
					if (status) status.textContent = '';
					if (!val) continue;
					if (!isPlausible(verifier, val)) {
						row.classList.add('is-invalid');
						if (status) status.textContent = i18n.invalidFormat;
						hint = i18n.invalidFormat;
					} else if (seen[val.toLowerCase()]) {
						row.classList.add('is-duplicate');
						if (status) status.textContent = i18n.duplicate;
						if (!hint) hint = i18n.duplicate;
					} else {
						seen[val.toLowerCase()] = true;
					}
				}
				var hintEl = card.querySelector('.chain-checkout-wallet-hint');
				if (hintEl) hintEl.textContent = hint || '';
			}

			function actionFrom(target) {
				while (target && target !== document) {
					if (target.getAttribute && target.getAttribute('data-chain-checkout-action')) {
						return target;
					}
					target = target.parentNode;
				}
				return null;
			}

			function onClick(e) {
				var btn = actionFrom(e.target);
				if (!btn) return;
				var action = btn.getAttribute('data-chain-checkout-action');
				var card = btn;
				while (card && !(card.classList && card.classList.contains('chain-checkout-wallet-card'))) {
					card = card.parentNode;
				}
				if (!card) return;

				e.preventDefault();
				e.stopPropagation();

				if (action === 'add') {
					var coinId = card.getAttribute('data-coin');
					var rows = card.querySelector('.chain-checkout-wallet-rows');
					if (!coinId || !rows) return;
					var row = createRow(coinId);
					rows.appendChild(row);
					var input = row.querySelector('.chain-checkout-wallet-input');
					if (input) input.focus();
					updateCard(card);
					updateTotals();
					return;
				}

				if (action === 'remove') {
					var rowR = btn;
					while (rowR && !(rowR.classList && rowR.classList.contains('chain-checkout-wallet-row'))) {
						rowR = rowR.parentNode;
					}
					if (!rowR) return;
					var all = card.querySelectorAll('.chain-checkout-wallet-row');
					if (all.length <= 1) {
						var only = rowR.querySelector('.chain-checkout-wallet-input');
						if (only) only.value = '';
					} else {
						rowR.parentNode.removeChild(rowR);
					}
					highlight(card);
					updateCard(card);
					updateTotals();
					return;
				}

				if (action === 'clear') {
					var coinC = card.getAttribute('data-coin');
					var rowsC = card.querySelector('.chain-checkout-wallet-rows');
					if (!coinC || !rowsC) return;
					rowsC.innerHTML = '';
					rowsC.appendChild(createRow(coinC));
					highlight(card);
					updateCard(card);
					updateTotals();
					return;
				}

				if (action === 'copy') {
					var rowC = btn;
					while (rowC && !(rowC.classList && rowC.classList.contains('chain-checkout-wallet-row'))) {
						rowC = rowC.parentNode;
					}
					var inp = rowC ? rowC.querySelector('.chain-checkout-wallet-input') : null;
					var text = inp ? inp.value : '';
					if (!text) return;
					var done = function () {
						var prev = btn.textContent;
						btn.textContent = i18n.copied;
						setTimeout(function () { btn.textContent = prev; }, 1200);
					};
					var fallbackCopy = function (value) {
						var ta = document.createElement('textarea');
						ta.value = value;
						document.body.appendChild(ta);
						ta.select();
						try { document.execCommand('copy'); } catch (err) { /* ignore */ }
						document.body.removeChild(ta);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(done).catch(function () {
							fallbackCopy(text);
							done();
						});
					} else {
						fallbackCopy(text);
						done();
					}
				}
			}

			function onInput(e) {
				var t = e.target;
				if (!t || !t.classList || !t.classList.contains('chain-checkout-wallet-input')) return;
				var card = t;
				while (card && !(card.classList && card.classList.contains('chain-checkout-wallet-card'))) {
					card = card.parentNode;
				}
				if (!card) return;
				highlight(card);
				updateCard(card);
				updateTotals();
			}

			function onSearch() {
				var root = document.getElementById('chain-checkout-wallets');
				var searchInput = document.getElementById('chain-checkout-wallet-search');
				if (!root || !searchInput) return;
				var q = (searchInput.value || '').toLowerCase().trim();
				var any = false;
				var cards = root.querySelectorAll('.chain-checkout-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					var search = (cards[i].getAttribute('data-search') || '').toLowerCase();
					var show = !q || search.indexOf(q) !== -1;
					cards[i].style.display = show ? '' : 'none';
					if (show) any = true;
				}
				var sections = root.querySelectorAll('.chain-checkout-wallets__section');
				for (i = 0; i < sections.length; i++) {
					var visible = 0, sc = sections[i].querySelectorAll('.chain-checkout-wallet-card'), j;
					for (j = 0; j < sc.length; j++) {
						if (sc[j].style.display !== 'none') visible++;
					}
					sections[i].style.display = visible ? '' : 'none';
				}
				var empty = document.getElementById('chain-checkout-wallets-empty');
				if (empty) empty.hidden = any;
			}

			document.addEventListener('click', onClick, true);
			document.addEventListener('input', onInput, true);
			var searchEl = document.getElementById('chain-checkout-wallet-search');
			if (searchEl) searchEl.addEventListener('input', onSearch);

			var root = document.getElementById('chain-checkout-wallets');
			if (root) {
				var cards = root.querySelectorAll('.chain-checkout-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					highlight(cards[i]);
					updateCard(cards[i]);
				}
				updateTotals();
			}
		})();
		</script>
	<?php endif; ?>
</div>
