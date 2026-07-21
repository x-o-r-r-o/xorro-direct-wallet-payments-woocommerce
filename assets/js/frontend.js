(function () {
	'use strict';

	function getCopyLabel(btn) {
		return (window.xdwpData && xdwpData.i18n && xdwpData.i18n.copied) || 'Copied!';
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.cssText =
			'position:fixed;top:0;left:0;width:2em;height:2em;padding:0;margin:0;border:0;outline:none;box-shadow:none;background:#fff;color:#000;';
		document.body.appendChild(ta);
		ta.focus();
		ta.select();
		if (typeof ta.setSelectionRange === 'function') {
			ta.setSelectionRange(0, text.length);
		}
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (err) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	function copyFromNode(node) {
		if (!node) {
			return false;
		}
		try {
			var range = document.createRange();
			range.selectNodeContents(node);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
			var ok = document.execCommand('copy');
			sel.removeAllRanges();
			return !!ok;
		} catch (err) {
			return false;
		}
	}

	function resolveText(btn) {
		var raw = btn.getAttribute('data-copy-text');
		if (raw !== null && String(raw).length) {
			return String(raw);
		}
		var target = btn.getAttribute('data-copy-target');
		if (target) {
			var node = document.getElementById(target);
			if (node) {
				return String(node.textContent || '').trim();
			}
		}
		var selector = btn.getAttribute('data-copy');
		if (selector) {
			var el = document.querySelector(selector);
			if (el) {
				return String(el.textContent || '').trim();
			}
		}
		var data = window.xdwpData || {};
		if (btn.id === 'xdwp-copy-amount') {
			return data.amount || '';
		}
		if (btn.id === 'xdwp-copy-address') {
			return data.address || '';
		}
		return data.qrValue || data.address || data.amount || '';
	}

	function markCopied(btn) {
		var original = btn.getAttribute('data-label') || btn.textContent;
		btn.setAttribute('data-label', original);
		btn.textContent = getCopyLabel(btn);
		btn.classList.add('is-copied');
		window.setTimeout(function () {
			btn.textContent = original;
			btn.classList.remove('is-copied');
		}, 1600);
	}

	function copyForButton(btn) {
		var text = resolveText(btn);
		if (!text) {
			return;
		}

		var targetId = btn.getAttribute('data-copy-target');
		var targetNode = targetId ? document.getElementById(targetId) : null;

		function finish(ok) {
			if (ok) {
				markCopied(btn);
			}
		}

		// Prefer copying a visible node (most reliable inside user gesture).
		if (targetNode && copyFromNode(targetNode)) {
			finish(true);
			return;
		}

		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			navigator.clipboard.writeText(text).then(
				function () {
					finish(true);
				},
				function () {
					finish(fallbackCopy(text));
				}
			);
			return;
		}

		finish(fallbackCopy(text));
	}

	// Capture-phase delegation so theme/Woo handlers cannot swallow the click.
	document.addEventListener(
		'click',
		function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('.xdwp-copy') : null;
			if (!btn) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			copyForButton(btn);
		},
		true
	);

	// Expose for inline onclick fallback in the template.
	window.xdwpCopy = function (btn) {
		if (!btn) {
			return false;
		}
		copyForButton(btn);
		return false;
	};

	// ---- Payment status / QR (requires localized data) ----
	if (typeof xdwpData === 'undefined') {
		return;
	}

	var data = xdwpData;
	var timerEl = document.getElementById('xdwp-timer');
	var statusEl = document.getElementById('xdwp-status-text');
	var box = document.getElementById('xdwp-box');
	var pollTimer = null;

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function updateTimer() {
		if (!timerEl || !data.expires) {
			return;
		}
		var now = Math.floor(Date.now() / 1000);
		var left = data.expires - now;
		var pollUntil = data.pollUntil || data.expires;
		if (left <= 0) {
			timerEl.textContent = data.i18n.expired;
			if (statusEl && data.status !== 'paid') {
				statusEl.textContent = data.i18n.expired;
			}
			// Keep polling through grace so late on-chain payments can still confirm.
			if (now >= pollUntil && pollTimer) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
			return;
		}
		var m = Math.floor(left / 60);
		var s = left % 60;
		timerEl.textContent = pad(m) + ':' + pad(s);
	}

	function renderQr() {
		var host = document.getElementById('xdwp-qrcode');
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
		body.append('action', 'xdwp_status');
		body.append('nonce', data.nonce);
		body.append('order_id', String(data.orderId));
		if (data.orderKey) {
			body.append('order_key', String(data.orderKey));
		}
		var keyEl = document.getElementById('xdwp-order-key');
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
