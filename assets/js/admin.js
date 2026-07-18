/**
 * Admin JS — branding media picker + ready state.
 * Wallets Add/Remove is handled by the inline script in wallets-ui.php.
 */
(function () {
	'use strict';

	var cfg = window.chainCheckoutAdmin || {};

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function initIconPicker() {
		var uploadBtn = document.getElementById('chain-checkout-icon-upload');
		var resetBtn = document.getElementById('chain-checkout-icon-reset');
		var idInput = document.getElementById('chain-checkout-icon-id');
		var preview = document.getElementById('chain-checkout-icon-preview');
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
		var admin = document.querySelector('.chain-checkout-admin, .chain-checkout-options-wrap');
		if (admin) {
			admin.classList.add('chain-checkout-admin--ready');
		}
		initIconPicker();
	});
})();
