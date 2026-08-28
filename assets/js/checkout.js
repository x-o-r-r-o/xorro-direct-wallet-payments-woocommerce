(function ($) {
	'use strict';

	var quoteSeq = 0;
	var quoteXhr = null;
	// The coin the shopper actually clicked, independent of whatever the
	// server last rendered as "checked" (see restoreUserCoin() below).
	var lastUserCoin = null;

	function selectedCoin() {
		return $('input[name="xdwp_coin"]:checked').val() || '';
	}

	/**
	 * WooCommerce re-renders the whole payment_fields() fragment on every
	 * update_order_review (billing/shipping field changes, etc.), and those
	 * requests are not sequenced — a slower request started *before* the
	 * shopper picked a coin can still resolve *after*, silently reverting
	 * the visible selection back to whatever coin was checked when that
	 * particular request was sent. Re-assert the shopper's actual last
	 * choice against whatever the fragment swap just rendered.
	 */
	function restoreUserCoin() {
		if (!lastUserCoin) {
			return;
		}
		var $target = $('input[name="xdwp_coin"][value="' + lastUserCoin + '"]');
		if ($target.length && !$target.prop('checked')) {
			$target.prop('checked', true);
		}
	}

	function gatewaySelected() {
		var cfg = typeof window.xdwp !== 'undefined' ? window.xdwp : null;
		return (
			cfg &&
			$('input[name="payment_method"]:checked').val() === cfg.gateway
		);
	}

	function setQuoteText(text) {
		var $quote = $('#xdwp-quote');
		if ($quote.length) {
			$quote.text(text || '');
		}
	}

	/**
	 * @param {string} coin
	 * @param {number} seq
	 * @param {number} attempt
	 */
	function startQuoteRequest(coin, seq, attempt) {
		if (!coin || typeof window.xdwp === 'undefined') {
			return;
		}

		if (attempt === 0 && quoteXhr && typeof quoteXhr.abort === 'function') {
			quoteXhr.abort();
		}

		if (attempt === 0) {
			setQuoteText('…');
		}

		quoteXhr = $.post(window.xdwp.ajaxUrl, {
			action: 'xdwp_quote',
			nonce: window.xdwp.nonce,
			coin: coin
		})
			.done(function (res) {
				if (seq !== quoteSeq || coin !== selectedCoin()) {
					return;
				}
				if (
					res &&
					res.success &&
					res.data &&
					res.data.amount &&
					(!res.data.coin || res.data.coin === coin)
				) {
					var prefix = res.data.approx ? '≈ ' : '';
					var label = prefix + res.data.amount + ' ' + res.data.symbol;
					if (res.data.message) {
						label += ' — ' + res.data.message;
					}
					setQuoteText(label);
					return;
				}
				// Soft-fail once: rate APIs flake under concurrent coin switches.
				if (attempt < 1) {
					window.setTimeout(function () {
						if (seq === quoteSeq && coin === selectedCoin()) {
							startQuoteRequest(coin, seq, attempt + 1);
						}
					}, 400);
					return;
				}
				setQuoteText('Unable to load rate. Try another coin or refresh.');
			})
			.fail(function (_xhr, status) {
				if (status === 'abort' || seq !== quoteSeq || coin !== selectedCoin()) {
					return;
				}
				if (attempt < 1) {
					window.setTimeout(function () {
						if (seq === quoteSeq && coin === selectedCoin()) {
							startQuoteRequest(coin, seq, attempt + 1);
						}
					}, 400);
					return;
				}
				setQuoteText('Unable to load rate. Try another coin or refresh.');
			})
			.always(function () {
				if (seq === quoteSeq) {
					quoteXhr = null;
				}
			});
	}

	function fetchQuote() {
		restoreUserCoin();
		if (!gatewaySelected()) {
			return;
		}
		var coin = selectedCoin();
		if (!coin) {
			return;
		}
		startQuoteRequest(coin, ++quoteSeq, 0);
	}

	$(document.body).on('change', 'input[name="xdwp_coin"]', function () {
		lastUserCoin = selectedCoin();
		fetchQuote();
	});
	$(document.body).on('updated_checkout payment_method_selected', fetchQuote);

	$(function () {
		fetchQuote();
	});
})(jQuery);
