/**
 * Wallets admin UI — add/remove/validate/search addresses.
 */
(function () {
			if (window.__chainCheckoutWalletsBound) { return; }
			window.__chainCheckoutWalletsBound = true;

			var i18n = window.chainCheckoutAdmin || {};
			var PATTERNS = {
				btc: /^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				bch: /^(bitcoincash:)?(q|p)[a-z0-9]{41}$|^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/,
				ltc: /^(ltc1|[LM3])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				doge: /^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/,
				eth: /^0x[a-fA-F0-9]{40}$/,
				ethereum: /^0x[a-fA-F0-9]{40}$/,
				arbitrum: /^0x[a-fA-F0-9]{40}$/,
				optimism: /^0x[a-fA-F0-9]{40}$/,
				base: /^0x[a-fA-F0-9]{40}$/,
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
