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
	});

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
				button.textContent = button.getAttribute('data-copied') || 'Copied';
				window.setTimeout(function () {
					button.textContent = original;
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
