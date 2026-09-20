/**
 * Admin JS — branding media picker + ready state.
 * Wallets Add/Remove is handled by assets/js/wallets.js.
 */
(function () {
	'use strict';

	var cfg = window.xdwpAdmin || {};

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function initIconPicker() {
		var uploadBtn = document.getElementById('xdwp-icon-upload');
		var resetBtn = document.getElementById('xdwp-icon-reset');
		var idInput = document.getElementById('xdwp-icon-id');
		var preview = document.getElementById('xdwp-icon-preview');
		if (!uploadBtn || !idInput || !preview) {
			return;
		}

		var frame = null;

		uploadBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (typeof wp === 'undefined' || !wp.media) {
				window.alert(cfg.mediaUnavailable || 'Media library is not available.');
				return;
			}
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: cfg.mediaTitle || 'Select checkout icon',
				button: { text: cfg.mediaButton || 'Use this icon' },
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				if (!attachment || !attachment.id) {
					return;
				}
				idInput.value = String(attachment.id);
				preview.src =
					(attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ||
					attachment.url;
			});
			frame.open();
		});

		if (resetBtn) {
			resetBtn.addEventListener('click', function (e) {
				e.preventDefault();
				idInput.value = '0';
				preview.src = cfg.defaultIcon || preview.getAttribute('data-default') || preview.src;
			});
		}
	}

	ready(function () {
		var admin = document.querySelector('.xdwp-admin, .xdwp-options-wrap');
		if (admin) {
			admin.classList.add('xdwp-admin--ready');
		}
		initIconPicker();
		initTxidCopy();
		initHelp();
		initSelfTest();
	});

	/**
	 * "Test this coin" — run the setup checks and show what, if anything, is wrong.
	 */
	function initSelfTest() {
		document.addEventListener('click', function (event) {
			var button = event.target.closest ? event.target.closest('.xdwp-test-setup') : null;
			if (!button || !window.xdwpAdmin || !xdwpAdmin.ajaxUrl) {
				return;
			}
			event.preventDefault();

			var panel = button.parentNode.querySelector('.xdwp-test-results');
			var label = button.textContent;
			button.disabled = true;
			button.textContent = xdwpAdmin.testing || 'Checking…';
			if (panel) {
				panel.innerHTML = '';
			}

			var body = new FormData();
            body.append('action', 'xdwp_selftest');
			body.append('nonce', xdwpAdmin.selftestNonce);
			body.append('coin', button.getAttribute('data-coin') || '');

			fetch(xdwpAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					button.disabled = false;
					button.textContent = xdwpAdmin.testAgain || label;
					if (!res || !res.success || !res.data || !res.data.coins || !res.data.coins.length) {
						renderTestError(panel, (res && res.data && res.data.message) || xdwpAdmin.testFailed);
						return;
					}
					renderTestResult(panel, res.data.coins[0]);
				})
				.catch(function () {
					button.disabled = false;
					button.textContent = xdwpAdmin.testAgain || label;
					renderTestError(panel, xdwpAdmin.testFailed);
				});
		});
	}

	/**
	 * @param {Element} panel   Where to draw.
	 * @param {string}  message What went wrong.
	 */
	function renderTestError(panel, message) {
		if (!panel) {
			return;
		}
		panel.innerHTML = '';
		var p = document.createElement('p');
		p.className = 'xdwp-test-line xdwp-test-line--fail';
		p.textContent = message || '';
		panel.appendChild(p);
	}

	/**
	 * @param {Element} panel  Where to draw.
	 * @param {Object}  result One coin's checks.
	 */
	function renderTestResult(panel, result) {
		if (!panel) {
			return;
		}
		panel.innerHTML = '';

		var heading = document.createElement('p');
		heading.className = 'xdwp-test-heading ' + (result.ok ? 'is-ok' : 'is-bad');
		heading.textContent = result.ok
			? (xdwpAdmin.testAllGood || '')
			: (xdwpAdmin.testProblems || '');
		panel.appendChild(heading);

		var list = document.createElement('ul');
		list.className = 'xdwp-test-list';
		(result.checks || []).forEach(function (check) {
			var item = document.createElement('li');
			item.className = 'xdwp-test-line xdwp-test-line--' + check.status;

			var mark = document.createElement('span');
			mark.className = 'xdwp-test-mark';
			mark.setAttribute('aria-hidden', 'true');
			mark.textContent = check.status === 'ok' ? '✓' : (check.status === 'fail' ? '✕' : (check.status === 'warn' ? '!' : '–'));
			item.appendChild(mark);

			var text = document.createElement('span');
			var strong = document.createElement('strong');
			strong.textContent = check.label + ': ';
			text.appendChild(strong);
			text.appendChild(document.createTextNode(check.detail));
			item.appendChild(text);

			list.appendChild(item);
		});
		panel.appendChild(list);
	}

	/**
	 * Help screen: filter the sections as you type, and keep the contents list in step with
	 * whichever section you are reading.
	 */
	function initHelp() {
		var filter = document.getElementById('xdwp-help-filter');
		var sections = [].slice.call(document.querySelectorAll('.xdwp-help__section'));
		if (!sections.length) {
			return;
		}
		var links = [].slice.call(document.querySelectorAll('.xdwp-help__index a'));
		var empty = document.getElementById('xdwp-help-empty');

		if (filter) {
			filter.addEventListener('input', function () {
				var term = filter.value.trim().toLowerCase();
				var shown = 0;
				sections.forEach(function (section) {
					var match = '' === term || section.textContent.toLowerCase().indexOf(term) !== -1;
					section.hidden = !match;
					if (match) {
						shown++;
					}
					var link = links.filter(function (a) {
						return a.getAttribute('href') === '#' + section.id;
					})[0];
					if (link) {
						link.hidden = !match;
					}
				});
				if (empty) {
					empty.hidden = shown !== 0;
				}
			});
		}

		if (!('IntersectionObserver' in window)) {
			return;
		}
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}
				links.forEach(function (a) {
					a.classList.toggle('is-current', a.getAttribute('href') === '#' + entry.target.id);
				});
			});
		}, { rootMargin: '-60px 0px -70% 0px' });
		sections.forEach(function (section) {
			observer.observe(section);
		});
	}

	/**
	 * Copy a transaction id from the payments overview.
	 */
	function initTxidCopy() {
		document.addEventListener('click', function (event) {
			var button = event.target.closest ? event.target.closest('.xdwp-copy-txid') : null;
			if (!button) {
				return;
			}
			event.preventDefault();
			var txid = button.getAttribute('data-txid') || '';
			var done = function () {
				var original = button.getAttribute('data-original') || button.textContent;
				button.setAttribute('data-original', original);
				// Held at its current width: the confirmation is a longer word, and this button
				// sits in a table cell beside the transaction id it copies.
				var held = button.getBoundingClientRect().width;
				if (held) { button.style.width = held + 'px'; }
				button.textContent = button.getAttribute('data-copied') || 'Copied';
				window.setTimeout(function () {
					button.textContent = original;
					button.style.width = '';
				}, 1500);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(txid).then(done, fallback);
			} else {
				fallback();
			}
			function fallback() {
				var field = document.createElement('textarea');
				field.value = txid;
				field.setAttribute('readonly', 'readonly');
				field.style.position = 'fixed';
				field.style.opacity = '0';
				document.body.appendChild(field);
				field.select();
				try {
					document.execCommand('copy');
					done();
				} catch (e) {}
				document.body.removeChild(field);
			}
		});
	}
})();
