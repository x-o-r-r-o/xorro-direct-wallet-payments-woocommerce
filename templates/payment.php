<?php
/**
 * Frontend payment instructions template.
 *
 * @package Xdwp
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
<div class="xdwp-box" id="xdwp-box" data-status="<?php echo esc_attr( $status ); ?>">
	<div class="xdwp-box__header">
		<h2><?php echo esc_html( sprintf( /* translators: %s: coin name */ __( 'Pay with %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] ) ); ?></h2>
		<p class="xdwp-box__timer" id="xdwp-timer"></p>
	</div>

	<?php if ( 'paid' === $status ) : ?>
		<p class="xdwp-box__success"><?php esc_html_e( 'Payment confirmed. Thank you!', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
	<?php elseif ( 'expired' === $status ) : ?>
		<p class="xdwp-box__error"><?php esc_html_e( 'Payment window expired. Please place a new order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
	<?php else : ?>
		<ol class="xdwp-box__steps">
			<li><?php esc_html_e( 'Send exactly the amount below (network fees are extra).', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Use the matching network shown for this coin.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Wait for automatic confirmation — this page updates itself.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
		</ol>

		<div class="xdwp-box__row">
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php esc_html_e( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<code id="xdwp-amount" class="xdwp-box__value"><?php echo esc_html( $amount ); ?></code>
				<button
					type="button"
					class="xdwp-copy button"
					id="xdwp-copy-amount"
					data-copy-text="<?php echo esc_attr( $amount ); ?>"
					data-copy-target="xdwp-amount"
					onclick="return window.xdwpCopy ? window.xdwpCopy(this) : false;"
				><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				<span class="xdwp-box__fiat"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			</div>
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php esc_html_e( 'Address', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<code id="xdwp-address" class="xdwp-box__value xdwp-box__address"><?php echo esc_html( $address ); ?></code>
				<button
					type="button"
					class="xdwp-copy button"
					id="xdwp-copy-address"
					data-copy-text="<?php echo esc_attr( $address ); ?>"
					data-copy-target="xdwp-address"
					onclick="return window.xdwpCopy ? window.xdwpCopy(this) : false;"
				><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
			</div>
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php esc_html_e( 'Network', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<span class="xdwp-box__value"><?php echo esc_html( $coin['network'] . ' · ' . $coin['type'] ); ?></span>
			</div>
		</div>

		<div class="xdwp-box__qr">
			<div id="xdwp-qrcode" aria-hidden="true"></div>
			<p class="xdwp-box__hint"><?php esc_html_e( 'Scan with your wallet app', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<?php if ( ! empty( $uri ) && $uri !== $address ) : ?>
				<p class="xdwp-box__uri">
					<button
						type="button"
						class="button-link xdwp-copy"
						data-copy-text="<?php echo esc_attr( $uri ); ?>"
						onclick="return window.xdwpCopy ? window.xdwpCopy(this) : false;"
					>
						<?php esc_html_e( 'Copy payment link', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</div>

		<p class="xdwp-box__status" id="xdwp-status-text"><?php esc_html_e( 'Waiting for payment…', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		<input type="hidden" id="xdwp-order-key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
	<?php endif; ?>
</div>
