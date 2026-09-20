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

	/**
	 * Minutes at which the remaining time is worth saying out loud. A per-second live region
	 * would make the page unusable with a screen reader.
	 */
	var announceAt = [10, 5, 2, 1];
	var announced = {};

	/**
	 * Write a sentence for screen readers without disturbing the visible layout.
	 *
	 * @param {string} text What to announce.
	 */
	function announce(text) {
		var region = document.getElementById('xdwp-time-announce');
		if (region && text && region.textContent !== text) {
			region.textContent = text;
		}
	}

	/**
	 * Set the payment status, but only when it actually changed — otherwise every poll would
	 * re-announce the same sentence.
	 *
	 * @param {string} text Status sentence.
	 */
	function setStatus(text) {
		if (statusEl && text && statusEl.textContent !== text) {
			statusEl.textContent = text;
		}
	}

	function updateTimer() {
		if (!timerEl || !data.expires) {
			return;
		}
		// Always derived from the server's expiry timestamp, so a backgrounded tab that missed
		// a hundred ticks still shows the right time the moment it comes back.
		var now = Math.floor(Date.now() / 1000);
		var left = data.expires - now;
		var pollUntil = data.pollUntil || data.expires;
		var visual = document.getElementById('xdwp-timer-text') || timerEl;
		if (left <= 0) {
			visual.textContent = data.i18n.expired;
			if (data.status !== 'paid') {
				setStatus(data.i18n.expired);
				announce(data.i18n.expired);
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
		visual.textContent = pad(m) + ':' + pad(s);

		var label = document.getElementById('xdwp-timer-label');
		if (label && data.i18n.timeLeft) {
			label.textContent = data.i18n.timeLeft.replace('%d', String(m + (s > 0 ? 1 : 0)));
		}

		var remaining = Math.ceil(left / 60);
		if (announceAt.indexOf(remaining) !== -1 && !announced[remaining] && s === 0) {
			announced[remaining] = true;
			if (data.i18n.timeLeft) {
				announce(data.i18n.timeLeft.replace('%d', String(remaining)));
			}
		}
	}

	/**
	 * @param {string|null} override Draw this instead of the payment link, when given.
	 */
	function renderQr(override) {
		var host = document.getElementById('xdwp-qrcode');
		if (!host) {
			return;
		}

		var payload = override
			? String(override)
			: ((data.qrValue && String(data.qrValue).trim()) || data.address || '');
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
		// Nothing to show a customer who is not looking — and every skipped call is one the
		// shop does not spend from its explorer budget. The check resumes the moment the page
		// is visible again, so nothing is missed, only deferred.
		if (document.hidden) {
			return;
		}
		setStatus(data.i18n.checking);

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
				if (res.data.status === 'underpaid' && data.status !== 'underpaid') {
					// A partial payment arrived: reload to show the remaining amount and new QR.
					window.location.reload();
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
					setStatus(data.i18n.expired);
					if (pollTimer) {
						clearInterval(pollTimer);
					}
					return;
				}
				setStatus(res.data.detected ? detectedMessage() : (data.i18n.waiting || 'Waiting for payment…'));
			})
			.catch(function () {
				setStatus(data.i18n.waiting || 'Waiting for payment…');
			});
	}

	/**
	 * What to say once the transfer is visible but not yet confirmed.
	 */
	function detectedMessage() {
		var needed = parseInt(data.confirmations, 10);
		if (needed > 1 && data.i18n.detectedWith) {
			return data.i18n.detectedWith.replace('%d', String(needed));
		}
		return data.i18n.detected || 'Payment detected — waiting for network confirmations…';
	}

	/**
	 * "I have sent the payment": ask for a check now, then watch more closely for a while.
	 */
	var sentBtn = document.getElementById('xdwp-sent');
	if (sentBtn && data.sentUrl) {
		sentBtn.addEventListener('click', function () {
			var note = document.getElementById('xdwp-sent-status');
			sentBtn.disabled = true;
			var body = new FormData();
			body.append('action', 'xdwp_sent');
			body.append('nonce', data.nonce);
			body.append('order_id', String(data.orderId));
			body.append('order_key', String(data.orderKey || ''));
			fetch(data.sentUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (note) {
						note.textContent = (res && res.success)
							? (data.i18n.checkingNow || '')
							: ((res && res.data && res.data.message) || data.i18n.checkFail || '');
					}
					watchClosely();
				})
				.catch(function () {
					if (note) {
						note.textContent = data.i18n.checkFail || '';
					}
					sentBtn.disabled = false;
				});
		});
	}

	/**
	 * Check every 10 seconds for the next five minutes, then go back to the normal pace.
	 */
	function watchClosely() {
		if (pollTimer) {
			clearInterval(pollTimer);
		}
		pollStatus();
		pollTimer = window.setInterval(pollStatus, 10000);
		window.setTimeout(function () {
			if (pollTimer) {
				clearInterval(pollTimer);
			}
			pollTimer = window.setInterval(pollStatus, 20000);
			if (sentBtn) {
				sentBtn.disabled = false;
			}
		}, 300000);
	}

	var renewBtn = document.getElementById('xdwp-renew');
	if (renewBtn && data.renewUrl) {
		renewBtn.addEventListener('click', function () {
			var note = document.getElementById('xdwp-renew-status');
			renewBtn.disabled = true;
			if (note) {
				note.textContent = data.i18n.renewing || 'Getting a new amount…';
			}
			var body = new FormData();
			body.append('action', 'xdwp_renew');
			body.append('nonce', data.nonce);
			body.append('order_id', String(data.orderId));
			body.append('order_key', String(data.orderKey || ''));
			fetch(data.renewUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						window.location.reload();
						return;
					}
					renewBtn.disabled = false;
					if (note) {
						note.textContent = (res && res.data && res.data.message) || data.i18n.renewFail || '';
					}
				})
				.catch(function () {
					renewBtn.disabled = false;
					if (note) {
						note.textContent = data.i18n.renewFail || '';
					}
				});
		});
	}

	/**
	 * Some wallets refuse a payment URI that carries an amount. Offer a code with just the
	 * address rather than leaving the customer stuck with a QR their app will not read.
	 */
	var plainToggle = document.getElementById('xdwp-qr-plain');
	if (plainToggle) {
		plainToggle.addEventListener('change', function () {
			renderQr(plainToggle.checked ? (data.address || '') : null);
		});
	}

	/**
	 * A phone is the screen you are holding, so the QR is no use there until asked for; and a
	 * backgrounded tab should stop polling rather than drain the battery and the shop's
	 * explorer budget while the customer is in their wallet app.
	 */
	var qrDetails = document.getElementById('xdwp-qr-details');
	if (qrDetails && window.matchMedia && window.matchMedia('(max-width: 600px)').matches) {
		qrDetails.open = false;
	}

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			if (pollTimer) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
			return;
		}
		// Back in view: correct the clock first, then look straight away rather than waiting
		// out an interval the customer never saw.
		updateTimer();
		if (data.status === 'awaiting' || data.status === 'underpaid') {
			if (!pollTimer) {
				pollTimer = window.setInterval(pollStatus, 20000);
			}
			pollStatus();
		}
	});

	renderQr();
	updateTimer();
	window.setInterval(updateTimer, 1000);

	if (data.status === 'awaiting' || data.status === 'underpaid') {
		setStatus(data.i18n.waiting || 'Waiting for payment…');
		pollTimer = window.setInterval(pollStatus, 20000);
		window.setTimeout(pollStatus, 5000);
	}
})();
