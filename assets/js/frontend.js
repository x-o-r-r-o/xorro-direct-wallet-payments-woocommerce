(function () {
	'use strict';

	if (typeof chainCheckoutData === 'undefined') {
		return;
	}

	var data = chainCheckoutData;
	var timerEl = document.getElementById('chain-checkout-timer');
	var statusEl = document.getElementById('chain-checkout-status-text');
	var statusBar = document.getElementById('chain-checkout-status-bar');
	var box = document.getElementById('chain-checkout-box');
	var pollTimer = null;

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function formatLeft(left) {
		if (left < 0) {
			left = 0;
		}
		var h = Math.floor(left / 3600);
		var m = Math.floor((left % 3600) / 60);
		var s = left % 60;
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}

	function setBarState(state) {
		if (!statusBar) {
			return;
		}
		statusBar.classList.remove(
			'chain-checkout-paybox__bottombar--checking',
			'chain-checkout-paybox__bottombar--success',
			'chain-checkout-paybox__bottombar--failed'
		);
		if (state) {
			statusBar.classList.add('chain-checkout-paybox__bottombar--' + state);
		}
	}

	function updateTimer() {
		if (!timerEl || !data.expires) {
			return;
		}
		var left = data.expires - Math.floor(Date.now() / 1000);
		if (left <= 0) {
			timerEl.textContent = '00:00:00';
			if (statusEl) {
				statusEl.textContent = data.i18n.expired;
			}
			setBarState('failed');
			if (pollTimer) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
			data.status = 'expired';
			return;
		}
		timerEl.textContent = formatLeft(left);
	}

	function copyText(selector) {
		var el = document.querySelector(selector);
		if (!el) {
			return;
		}
		copyRaw(el.textContent.trim());
	}

	function copyRaw(text) {
		if (!text) {
			return;
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text);
			return;
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
		} catch (e) {
			/* ignore */
		}
		document.body.removeChild(ta);
	}

	document.querySelectorAll('.chain-checkout-copy').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var raw = btn.getAttribute('data-copy-text');
			if (raw) {
				copyRaw(raw);
			} else {
				copyText(btn.getAttribute('data-copy'));
			}
			var label = btn.getAttribute('aria-label');
			if (btn.classList.contains('chain-checkout-paybox__link') || btn.textContent.trim()) {
				var original = btn.textContent;
				if (original) {
					btn.textContent = data.i18n.copied;
					setTimeout(function () {
						btn.textContent = original;
					}, 1500);
				}
			} else if (label) {
				btn.setAttribute('aria-label', data.i18n.copied);
				setTimeout(function () {
					btn.setAttribute('aria-label', label);
				}, 1500);
			}
		});
	});

	function bindHelp() {
		var toggle = document.getElementById('chain-checkout-help-toggle');
		var panel = document.getElementById('chain-checkout-instructions');
		var closeBtn = document.getElementById('chain-checkout-help-close');
		if (!toggle || !panel) {
			return;
		}
		function open() {
			panel.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
		}
		function close() {
			panel.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
		}
		toggle.addEventListener('click', function () {
			if (panel.hidden) {
				open();
			} else {
				close();
			}
		});
		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}
	}

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
			var size = len > 160 ? 220 : len > 100 ? 180 : 140;
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
					width: 140,
					height: 140,
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
		setBarState('checking');

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
					setBarState('success');
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
					setBarState('failed');
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

	bindHelp();
	renderQr();
	updateTimer();
	window.setInterval(updateTimer, 1000);

	if (data.status === 'awaiting') {
		if (statusEl) {
			statusEl.textContent = data.i18n.waiting || 'Waiting for payment…';
		}
		pollTimer = window.setInterval(pollStatus, 20000);
		window.setTimeout(pollStatus, 5000);
	} else if (data.status === 'paid') {
		setBarState('success');
	} else if (data.status === 'expired') {
		setBarState('failed');
	}
})();
