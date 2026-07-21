(function ($) {
	'use strict';

	var quoteSeq = 0;
	var quoteXhr = null;

	function selectedCoin() {
		return $('input[name="xdwp_coin"]:checked').val() || '';
	}

	function gatewaySelected() {
		return (
			$('input[name="payment_method"]:checked').val() ===
			(xdwp && xdwp.gateway)
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
		if (!coin || typeof xdwp === 'undefined') {
			return;
		}

		if (attempt === 0 && quoteXhr && typeof quoteXhr.abort === 'function') {
			quoteXhr.abort();
		}

		if (attempt === 0) {
			setQuoteText('…');
		}

		quoteXhr = $.post(xdwp.ajaxUrl, {
			action: 'xdwp_quote',
			nonce: xdwp.nonce,
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
					setQuoteText('≈ ' + res.data.amount + ' ' + res.data.symbol);
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
				setQuoteText('');
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
				setQuoteText('');
			})
			.always(function () {
				if (seq === quoteSeq) {
					quoteXhr = null;
				}
			});
	}

	function fetchQuote() {
		if (!gatewaySelected()) {
			return;
		}
		var coin = selectedCoin();
		if (!coin) {
			return;
		}
		startQuoteRequest(coin, ++quoteSeq, 0);
	}

	$(document.body).on('change', 'input[name="xdwp_coin"]', fetchQuote);
	$(document.body).on('updated_checkout payment_method_selected', fetchQuote);

	$(function () {
		fetchQuote();
	});
})(jQuery);
