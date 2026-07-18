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
				<button type="button" class="chain-checkout-copy button" id="chain-checkout-copy-amount" data-copy-text="<?php echo esc_attr( $amount ); ?>"><?php esc_html_e( 'Copy', 'chain-checkout' ); ?></button>
				<span class="chain-checkout-box__fiat"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			</div>
			<div class="chain-checkout-box__field">
				<span class="chain-checkout-box__label"><?php esc_html_e( 'Address', 'chain-checkout' ); ?></span>
				<code id="chain-checkout-address" class="chain-checkout-box__value chain-checkout-box__address"><?php echo esc_html( $address ); ?></code>
				<button type="button" class="chain-checkout-copy button" id="chain-checkout-copy-address" data-copy-text="<?php echo esc_attr( $address ); ?>"><?php esc_html_e( 'Copy', 'chain-checkout' ); ?></button>
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
					<button type="button" class="button-link chain-checkout-copy" data-copy-text="<?php echo esc_attr( $uri ); ?>">
						<?php esc_html_e( 'Copy payment link', 'chain-checkout' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</div>

		<p class="chain-checkout-box__status" id="chain-checkout-status-text"><?php esc_html_e( 'Waiting for payment…', 'chain-checkout' ); ?></p>
		<input type="hidden" id="chain-checkout-order-key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
	<?php endif; ?>
</div>
