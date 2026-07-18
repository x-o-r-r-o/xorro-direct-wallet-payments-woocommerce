<?php
/**
 * Frontend payment instructions template.
 *
 * @package ChainCheckout
 *
 * @var WC_Order $order
 * @var array    $coin
 * @var string   $address
 * @var string   $amount
 * @var int      $expires
 * @var string   $status
 * @var string   $uri
 * @var string   $coin_id
 */

defined( 'ABSPATH' ) || exit;

$copied_label = esc_js( __( 'Copied!', 'chain-checkout' ) );
?>
<div class="chain-checkout-box" id="chain-checkout-box" data-status="<?php echo esc_attr( $status ); ?>">
	<div class="chain-checkout-box__header">
		<h2><?php echo esc_html( sprintf( /* translators: %s: coin name */ __( 'Pay with %s', 'chain-checkout' ), $coin['name'] ) ); ?></h2>
		<p class="chain-checkout-box__timer" id="chain-checkout-timer"></p>
	</div>

	<?php if ( 'paid' === $status ) : ?>
		<p class="chain-checkout-box__success"><?php esc_html_e( 'Payment confirmed. Thank you!', 'chain-checkout' ); ?></p>
	<?php elseif ( 'expired' === $status ) : ?>
		<p class="chain-checkout-box__error"><?php esc_html_e( 'Payment window expired. Please place a new order.', 'chain-checkout' ); ?></p>
	<?php else : ?>
		<ol class="chain-checkout-box__steps">
			<li><?php esc_html_e( 'Send exactly the amount below (network fees are extra).', 'chain-checkout' ); ?></li>
			<li><?php esc_html_e( 'Use the matching network shown for this coin.', 'chain-checkout' ); ?></li>
			<li><?php esc_html_e( 'Wait for automatic confirmation — this page updates itself.', 'chain-checkout' ); ?></li>
		</ol>

		<div class="chain-checkout-box__row">
			<div class="chain-checkout-box__field">
				<span class="chain-checkout-box__label"><?php esc_html_e( 'Amount', 'chain-checkout' ); ?></span>
				<code id="chain-checkout-amount" class="chain-checkout-box__value"><?php echo esc_html( $amount ); ?></code>
				<button
					type="button"
					class="chain-checkout-copy button"
					id="chain-checkout-copy-amount"
					data-copy-text="<?php echo esc_attr( $amount ); ?>"
					data-copy-target="chain-checkout-amount"
					onclick="return window.chainCheckoutCopy ? window.chainCheckoutCopy(this) : false;"
				><?php esc_html_e( 'Copy', 'chain-checkout' ); ?></button>
				<span class="chain-checkout-box__fiat"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			</div>
			<div class="chain-checkout-box__field">
				<span class="chain-checkout-box__label"><?php esc_html_e( 'Address', 'chain-checkout' ); ?></span>
				<code id="chain-checkout-address" class="chain-checkout-box__value chain-checkout-box__address"><?php echo esc_html( $address ); ?></code>
				<button
					type="button"
					class="chain-checkout-copy button"
					id="chain-checkout-copy-address"
					data-copy-text="<?php echo esc_attr( $address ); ?>"
					data-copy-target="chain-checkout-address"
					onclick="return window.chainCheckoutCopy ? window.chainCheckoutCopy(this) : false;"
				><?php esc_html_e( 'Copy', 'chain-checkout' ); ?></button>
			</div>
			<div class="chain-checkout-box__field">
				<span class="chain-checkout-box__label"><?php esc_html_e( 'Network', 'chain-checkout' ); ?></span>
				<span class="chain-checkout-box__value"><?php echo esc_html( $coin['network'] . ' · ' . $coin['type'] ); ?></span>
			</div>
		</div>

		<div class="chain-checkout-box__qr">
			<div id="chain-checkout-qrcode" aria-hidden="true"></div>
			<p class="chain-checkout-box__hint"><?php esc_html_e( 'Scan with your wallet app', 'chain-checkout' ); ?></p>
			<?php if ( ! empty( $uri ) && $uri !== $address ) : ?>
				<p class="chain-checkout-box__uri">
					<button
						type="button"
						class="button-link chain-checkout-copy"
						data-copy-text="<?php echo esc_attr( $uri ); ?>"
						onclick="return window.chainCheckoutCopy ? window.chainCheckoutCopy(this) : false;"
					>
						<?php esc_html_e( 'Copy payment link', 'chain-checkout' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</div>

		<p class="chain-checkout-box__status" id="chain-checkout-status-text"><?php esc_html_e( 'Waiting for payment…', 'chain-checkout' ); ?></p>
		<input type="hidden" id="chain-checkout-order-key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
	<?php endif; ?>
</div>
<?php // Inline copy bootstrap — works even if the enqueued frontend.js is delayed/blocked. ?>
<script>
(function () {
	if (window.chainCheckoutCopy) {
		return;
	}
	var copiedLabel = '<?php echo $copied_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js above. ?>';
	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.cssText = 'position:fixed;top:0;left:0;width:2em;height:2em;padding:0;border:0;outline:none;background:#fff;';
		document.body.appendChild(ta);
		ta.focus();
		ta.select();
		if (ta.setSelectionRange) {
			ta.setSelectionRange(0, text.length);
		}
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
		document.body.removeChild(ta);
		return ok;
	}
	function copyFromNode(node) {
		if (!node) return false;
		try {
			var range = document.createRange();
			range.selectNodeContents(node);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
			var ok = document.execCommand('copy');
			sel.removeAllRanges();
			return !!ok;
		} catch (e) {
			return false;
		}
	}
	window.chainCheckoutCopy = function (btn) {
		if (!btn) return false;
		var text = btn.getAttribute('data-copy-text') || '';
		var targetId = btn.getAttribute('data-copy-target');
		var node = targetId ? document.getElementById(targetId) : null;
		if (node && !text) {
			text = (node.textContent || '').trim();
		}
		if (!text) return false;
		var ok = false;
		if (node) {
			ok = copyFromNode(node);
		}
		if (!ok && navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				var prev = btn.getAttribute('data-label') || btn.textContent;
				btn.setAttribute('data-label', prev);
				btn.textContent = copiedLabel;
				setTimeout(function () { btn.textContent = prev; }, 1600);
			}).catch(function () {
				if (fallbackCopy(text)) {
					var prev2 = btn.getAttribute('data-label') || btn.textContent;
					btn.setAttribute('data-label', prev2);
					btn.textContent = copiedLabel;
					setTimeout(function () { btn.textContent = prev2; }, 1600);
				}
			});
			return false;
		}
		ok = ok || fallbackCopy(text);
		if (ok) {
			var prev = btn.getAttribute('data-label') || btn.textContent;
			btn.setAttribute('data-label', prev);
			btn.textContent = copiedLabel;
			setTimeout(function () { btn.textContent = prev; }, 1600);
		}
		return false;
	};
})();
</script>
