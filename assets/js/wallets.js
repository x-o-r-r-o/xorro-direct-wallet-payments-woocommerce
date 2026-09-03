/**
 * Wallets admin UI — add/remove/validate/search addresses.
 */
(function () {
			if (window.__xdwpWalletsBound) { return; }
			window.__xdwpWalletsBound = true;

			var i18n = window.xdwpAdmin || {};
			var PATTERNS = {
				btc: /^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				bch: /^(bitcoincash:)?(q|p)[a-z0-9]{41}$|^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/,
				ltc: /^(ltc1|[LM3])[a-zA-HJ-NP-Z0-9]{25,62}$/,
				doge: /^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/,
				dash: /^[X7][1-9A-HJ-NP-Za-km-z]{25,34}$/,
				zec: /^t[13][1-9A-HJ-NP-Za-km-z]{33,34}$/,
				xec: /^(ecash:)?(q|p)[a-z0-9]{41}$/,
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
				one: /^0x[a-fA-F0-9]{40}$/,
				pls: /^0x[a-fA-F0-9]{40}$/,
				sysevm: /^0x[a-fA-F0-9]{40}$/,
				boba: /^0x[a-fA-F0-9]{40}$/,
				brise: /^0x[a-fA-F0-9]{40}$/,
				kaia: /^0x[a-fA-F0-9]{40}$/,
				xdc: /^(xdc|0x)?[a-fA-F0-9]{40}$/,
				sol: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
				solana: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
				trx: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
				tron: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
				xrp: /^r[1-9A-HJ-NP-Za-km-z]{24,34}$/,
				xlm: /^G[A-Z2-7]{55}$/,
				dot: /^[1-9A-HJ-NP-Za-km-z]{46,50}$/,
				atom: /^cosmos1[a-z0-9]{38,58}$/,
				scrt: /^secret1[a-z0-9]{38,58}$/,
				sei: /^sei1[a-z0-9]{38,58}$/,
				inj_native: /^inj1[a-z0-9]{38,58}$/,
				algo: /^[A-Z2-7]{58}$/,
				near: /^(([a-z0-9_-]{2,64}\.)*([a-z0-9_-]{2,64})\.near|[a-f0-9]{64})$|^[a-z0-9._-]{2,64}$/,
				fil: /^f[0-9a-zA-Z]{8,128}$/,
				hbar: /^0\.0\.\d{1,10}$/,
				egld: /^erd1[a-z0-9]{58}$/,
				zil: /^zil1[a-z0-9]{38}$/,
				eos: /^[a-z1-5.]{1,12}$/,
				ton: /^-?\d+:[0-9a-fA-F]{64}$|^[A-Za-z0-9_-]{48}$/,
				ada: /^addr1[a-z0-9]{50,110}$/,
				apt: /^(0x)?[0-9a-fA-F]{1,64}$/,
				kas: /^kaspa:[a-z0-9]{61,64}$/,
				xtz: /^tz[1-3][1-9A-HJ-NP-Za-km-z]{33}$/,
				xno: /^(nano|xrb)_[13456789abcdefghijkmnopqrstuwxyz]{60}$/,
				waves: /^3P[1-9A-HJ-NP-Za-km-z]{33}$/,
				btg: /^[GA][1-9A-HJ-NP-Za-km-z]{33}$/,
				firo: /^a[1-9A-HJ-NP-Za-km-z]{33}$/,
				xzc: /^a[1-9A-HJ-NP-Za-km-z]{33}$/,
				rvn: /^R[1-9A-HJ-NP-Za-km-z]{33}$/,
				pivx: /^[DS][1-9A-HJ-NP-Za-km-z]{33}$/,
				neo: /^N[1-9A-HJ-NP-Za-km-z]{33}$/,
				gas: /^N[1-9A-HJ-NP-Za-km-z]{33}$/,
				theta: /^0x[a-fA-F0-9]{40}$/,
				tfuel: /^0x[a-fA-F0-9]{40}$/,
				strk: /^(0x)?[0-9a-fA-F]{1,64}$/,
				dgb: /^D[1-9A-HJ-NP-Za-km-z]{33}$/,
				kmd: /^R[1-9A-HJ-NP-Za-km-z]{33}$/,
				xvg: /^D[1-9A-HJ-NP-Za-km-z]{33}$/,
				qtum: /^Q[1-9A-HJ-NP-Za-km-z]{33}$/,
				ark: /^A[1-9A-HJ-NP-Za-km-z]{33}$/,
				ae: /^ak_[1-9A-HJ-NP-Za-km-z]{48,50}$/,
				icx: /^hx[0-9a-f]{40}$/,
				ont: /^A[1-9A-HJ-NP-Za-km-z]{33}$/,
				klv: /^klv1[ac-hj-np-z02-9]{58}$/,
				tet: /^0x[a-fA-F0-9]{40}$/,
				xem: /^N[A-Z2-7]{39}$/,
				xym: /^N[A-Z2-7]{38}$/,
				rune: /^thor1[ac-hj-np-z02-9]{38}$/,
				iotx: /^(io1[ac-hj-np-z02-9]{38,40}|0x[a-fA-F0-9]{40})$/,
				cspr: /^(01[0-9a-fA-F]{64}|02[0-9a-fA-F]{66})$/
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
				var row = el('div', { className: 'xdwp-wallet-row' });
				var input = el('input', {
					type: 'text',
					className: 'xdwp-wallet-input regular-text code',
					name: 'xdwp[wallets][' + coinId + '][]',
					placeholder: i18n.placeholder,
					autocomplete: 'off',
					spellcheck: 'false',
					'data-coin': coinId
				});
				input.value = '';
				var btns = el('div', { className: 'xdwp-wallet-row__btns' });
				var copyBtn = el('button', { type: 'button', className: 'button xdwp-wallet-copy', 'data-xdwp-action': 'copy', text: i18n.copy });
				var removeBtn = el('button', { type: 'button', className: 'button xdwp-wallet-remove', 'data-xdwp-action': 'remove', 'aria-label': i18n.remove }, '<span aria-hidden="true">&times;</span>');
				var status = el('span', { className: 'xdwp-wallet-row__status', 'aria-hidden': 'true' });
				btns.appendChild(copyBtn);
				btns.appendChild(removeBtn);
				row.appendChild(input);
				row.appendChild(btns);
				row.appendChild(status);
				return row;
			}

			function countFilled(card) {
				var n = 0, inputs = card.querySelectorAll('.xdwp-wallet-input'), i;
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
				var clearBtn = card.querySelector('[data-xdwp-action="clear"]');
				if (clearBtn) clearBtn.disabled = count === 0;
			}

			function updateTotals() {
				var root = document.getElementById('xdwp-wallets');
				if (!root) return;
				var total = 0, missing = 0;
				var cards = root.querySelectorAll('.xdwp-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					var c = countFilled(cards[i]);
					total += c;
					if (c === 0) missing++;
				}
				var num = document.getElementById('xdwp-wallet-counter-num');
				if (num) num.textContent = String(total);
				root.setAttribute('data-total', String(total));

				var sections = root.querySelectorAll('.xdwp-wallets__section');
				for (i = 0; i < sections.length; i++) {
					var n = 0, sc = sections[i].querySelectorAll('.xdwp-wallet-card'), j;
					for (j = 0; j < sc.length; j++) n += countFilled(sc[j]);
					var elCount = sections[i].querySelector('.xdwp-wallets__section-count');
					if (elCount) elCount.textContent = n ? String(n) : '';
				}

				var miss = document.getElementById('xdwp-wallet-missing');
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
				var rows = card.querySelectorAll('.xdwp-wallet-row'), i;
				for (i = 0; i < rows.length; i++) {
					var row = rows[i];
					var input = row.querySelector('.xdwp-wallet-input');
					var status = row.querySelector('.xdwp-wallet-row__status');
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
				var hintEl = card.querySelector('.xdwp-wallet-hint');
				if (hintEl) hintEl.textContent = hint || '';
			}

			function actionFrom(target) {
				while (target && target !== document) {
					if (target.getAttribute && target.getAttribute('data-xdwp-action')) {
						return target;
					}
					target = target.parentNode;
				}
				return null;
			}

			function onClick(e) {
				var btn = actionFrom(e.target);
				if (!btn) return;
				var action = btn.getAttribute('data-xdwp-action');
				var card = btn;
				while (card && !(card.classList && card.classList.contains('xdwp-wallet-card'))) {
					card = card.parentNode;
				}
				if (!card) return;

				e.preventDefault();
				e.stopPropagation();

				if (action === 'add') {
					var coinId = card.getAttribute('data-coin');
					var rows = card.querySelector('.xdwp-wallet-rows');
					if (!coinId || !rows) return;
					var row = createRow(coinId);
					rows.appendChild(row);
					var input = row.querySelector('.xdwp-wallet-input');
					if (input) input.focus();
					updateCard(card);
					updateTotals();
					return;
				}

				if (action === 'remove') {
					var rowR = btn;
					while (rowR && !(rowR.classList && rowR.classList.contains('xdwp-wallet-row'))) {
						rowR = rowR.parentNode;
					}
					if (!rowR) return;
					var all = card.querySelectorAll('.xdwp-wallet-row');
					if (all.length <= 1) {
						var only = rowR.querySelector('.xdwp-wallet-input');
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
					var rowsC = card.querySelector('.xdwp-wallet-rows');
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
					while (rowC && !(rowC.classList && rowC.classList.contains('xdwp-wallet-row'))) {
						rowC = rowC.parentNode;
					}
					var inp = rowC ? rowC.querySelector('.xdwp-wallet-input') : null;
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
				if (!t || !t.classList || !t.classList.contains('xdwp-wallet-input')) return;
				var card = t;
				while (card && !(card.classList && card.classList.contains('xdwp-wallet-card'))) {
					card = card.parentNode;
				}
				if (!card) return;
				highlight(card);
				updateCard(card);
				updateTotals();
			}

			function onSearch() {
				var root = document.getElementById('xdwp-wallets');
				var searchInput = document.getElementById('xdwp-wallet-search');
				if (!root || !searchInput) return;
				var q = (searchInput.value || '').toLowerCase().trim();
				var any = false;
				var cards = root.querySelectorAll('.xdwp-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					var search = (cards[i].getAttribute('data-search') || '').toLowerCase();
					var show = !q || search.indexOf(q) !== -1;
					cards[i].style.display = show ? '' : 'none';
					if (show) any = true;
				}
				var sections = root.querySelectorAll('.xdwp-wallets__section');
				for (i = 0; i < sections.length; i++) {
					var visible = 0, sc = sections[i].querySelectorAll('.xdwp-wallet-card'), j;
					for (j = 0; j < sc.length; j++) {
						if (sc[j].style.display !== 'none') visible++;
					}
					sections[i].style.display = visible ? '' : 'none';
				}
				var empty = document.getElementById('xdwp-wallets-empty');
				if (empty) empty.hidden = any;
			}

			document.addEventListener('click', onClick, true);
			document.addEventListener('input', onInput, true);
			var searchEl = document.getElementById('xdwp-wallet-search');
			if (searchEl) searchEl.addEventListener('input', onSearch);

			var root = document.getElementById('xdwp-wallets');
			if (root) {
				var cards = root.querySelectorAll('.xdwp-wallet-card'), i;
				for (i = 0; i < cards.length; i++) {
					highlight(cards[i]);
					updateCard(cards[i]);
				}
				updateTotals();
			}
		})();
