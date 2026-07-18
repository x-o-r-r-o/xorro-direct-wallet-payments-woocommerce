(function () {
	'use strict';

	if (typeof chainCheckoutData === 'undefined') {
		return;
	}

	var data = chainCheckoutData;
	var timerEl = document.getElementById('chain-checkout-timer');
	var statusEl = document.getElementById('chain-checkout-status-text');
	var box = document.getElementById('chain-checkout-box');
	var pollTimer = null;

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function updateTimer() {
		if (!timerEl || !data.expires) {
			return;
		}
		var left = data.expires - Math.floor(Date.now() / 1000);
		if (left <= 0) {
			timerEl.textContent = data.i18n.expired;
			if (statusEl) {
				statusEl.textContent = data.i18n.expired;
			}
			if (pollTimer) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
			data.status = 'expired';
			return;
		}
		var m = Math.floor(left / 60);
		var s = left % 60;
		timerEl.textContent = pad(m) + ':' + pad(s);
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.top = '0';
		ta.style.left = '0';
		ta.style.width = '1px';
		ta.style.height = '1px';
		ta.style.padding = '0';
		ta.style.border = 'none';
		ta.style.outline = 'none';
		ta.style.boxShadow = 'none';
		ta.style.background = 'transparent';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.focus();
		ta.select();
		ta.setSelectionRange(0, text.length);
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	function copyRaw(text) {
		text = String(text || '').trim();
		if (!text) {
			return Promise.resolve(false);
		}
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			return navigator.clipboard.writeText(text).then(
				function () {
					return true;
				},
				function () {
					return fallbackCopy(text);
				}
			);
		}
		return Promise.resolve(fallbackCopy(text));
	}

	function markCopied(btn) {
		var original = btn.getAttribute('data-label') || btn.textContent;
		btn.setAttribute('data-label', original);
		btn.textContent = (data.i18n && data.i18n.copied) || 'Copied!';
		btn.classList.add('is-copied');
		btn.setAttribute('aria-live', 'polite');
		window.setTimeout(function () {
			btn.textContent = original;
			btn.classList.remove('is-copied');
		}, 1500);
	}

	function resolveCopyText(btn) {
		var raw = btn.getAttribute('data-copy-text');
		if (raw !== null && String(raw).length) {
			return String(raw);
		}
		var selector = btn.getAttribute('data-copy');
		if (selector) {
			var el = document.querySelector(selector);
			if (el) {
				return String(el.textContent || '').trim();
			}
		}
		if (btn.id === 'chain-checkout-copy-amount') {
			return data.amount || '';
		}
		if (btn.id === 'chain-checkout-copy-address') {
			return data.address || '';
		}
		return data.qrValue || data.address || data.amount || '';
	}

	document.querySelectorAll('.chain-checkout-copy').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			copyRaw(resolveCopyText(btn)).then(function (ok) {
				if (ok) {
					markCopied(btn);
				}
			});
		});
	});

	function renderQr() {
		var host = document.getElementById('chain-checkout-qrcode');
		if (!host) {
			return;
		}

		var payload = (data.qrValue && String(data.qrValue).trim()) || data.address || '';
		if (!payload) {
			host.textContent = data.i18n.qrFail || 'QR unavailable';
			return;
		}

		host.innerHTML = '';
		host.setAttribute('title', payload);

		if (typeof QRCode === 'undefined') {
			host.textContent = data.i18n.qrFail || 'QR unavailable';
			return;
		}

		try {
			var len = payload.length;
			var size = len > 160 ? 240 : len > 100 ? 200 : 180;
			var level = len > 160 ? QRCode.CorrectLevel.L : QRCode.CorrectLevel.M;
			new QRCode(host, {
				text: payload,
				width: size,
				height: size,
				colorDark: '#000000',
				colorLight: '#ffffff',
				correctLevel: level
			});
		} catch (err) {
			host.innerHTML = '';
			try {
				new QRCode(host, {
					text: data.address || payload,
					width: 180,
					height: 180,
					correctLevel: QRCode.CorrectLevel.L
				});
			} catch (err2) {
				host.textContent = data.i18n.qrFail || 'QR unavailable';
			}
		}
	}

	function pollStatus() {
		if (data.status === 'paid' || data.status === 'expired') {
			return;
		}
		if (statusEl) {
			statusEl.textContent = data.i18n.checking;
		}

		var body = new FormData();
		body.append('action', 'chain_checkout_status');
		body.append('nonce', data.nonce);
		body.append('order_id', String(data.orderId));
		var keyEl = document.getElementById('chain-checkout-order-key');
		if (keyEl) {
			body.append('order_key', keyEl.value);
		}

		fetch(data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (res) {
				if (!res || !res.success || !res.data) {
					if (statusEl) {
						statusEl.textContent = data.i18n.waiting || 'Waiting for payment…';
					}
					return;
				}
				data.status = res.data.status;
				if (box) {
					box.setAttribute('data-status', data.status);
				}
				if (res.data.paid) {
					if (statusEl) {
						statusEl.textContent = data.i18n.paid;
					}
					if (pollTimer) {
						clearInterval(pollTimer);
					}
					window.setTimeout(function () {
						window.location.reload();
					}, 1200);
					return;
				}
				if (res.data.expired) {
					if (statusEl) {
						statusEl.textContent = data.i18n.expired;
					}
					if (pollTimer) {
						clearInterval(pollTimer);
					}
					return;
				}
				if (statusEl) {
					statusEl.textContent = data.i18n.waiting || 'Waiting for payment…';
				}
			})
			.catch(function () {
				if (statusEl) {
					statusEl.textContent = data.i18n.waiting || 'Waiting for payment…';
				}
			});
	}

	renderQr();
	updateTimer();
	window.setInterval(updateTimer, 1000);

	if (data.status === 'awaiting') {
		if (statusEl) {
			statusEl.textContent = data.i18n.waiting || 'Waiting for payment…';
		}
		pollTimer = window.setInterval(pollStatus, 20000);
		window.setTimeout(pollStatus, 5000);
	}
})();
