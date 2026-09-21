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
 * @var string   $received Amount received so far (partial payments).
 * @var string   $due      Full amount of the order.
 * @var bool     $can_renew Whether the customer may request a new quote.
 * @var string   $memo      Destination tag / memo the payment must carry, '' when the chain has none.
 * @var string   $memo_kind 'tag' | 'text' | ''.
 * @var string   $network_label How the customer's wallet or exchange names this network.
 * @var int      $confirmations Confirmations this coin waits for.
 * @var string   $wait_estimate Plain-words estimate of the wait, '' when unknown.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="xdwp-box" id="xdwp-box" data-status="<?php echo esc_attr( $status ); ?>">
	<div class="xdwp-box__header">
		<h2><?php
			// "Pay with Bitcoin" over a receipt asks for something that has already happened.
			echo esc_html(
				'paid' === $status
					/* translators: %s: coin name */
					? sprintf( __( 'Paid with %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] )
					/* translators: %s: coin name */
					: sprintf( __( 'Pay with %s', 'xorro-direct-wallet-payments-woocommerce' ), $coin['name'] )
			);
		?></h2>
		<?php // A confirmed or closed order has nothing left to count down to, and a clock
		// ticking beside "Payment confirmed" only makes a customer wonder what it means. ?>
		<?php if ( ! in_array( $status, array( 'paid', 'expired', 'cancelled' ), true ) ) : ?>
			<p class="xdwp-box__timer" id="xdwp-timer" role="timer">
				<span id="xdwp-timer-text" aria-hidden="true"></span>
				<span class="screen-reader-text" id="xdwp-timer-label"></span>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( 'paid' === $status ) : ?>
		<p class="xdwp-box__success"><?php esc_html_e( 'Payment confirmed. Thank you!', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
	<?php elseif ( 'expired' === $status && '' !== (string) $received ) : ?>
		<p class="xdwp-box__error"><?php esc_html_e( 'The payment window closed before the full amount arrived. We have received part of your payment — please contact us and we will complete or refund your order. Do not send more.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
	<?php elseif ( 'expired' === $status ) : ?>
		<?php if ( $can_renew ) : ?>
			<p class="xdwp-box__error"><?php esc_html_e( 'The payment window closed, so the amount shown is out of date. You can get a new amount at today\'s rate — your order is kept.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
			<p>
				<button type="button" class="button xdwp-renew" id="xdwp-renew"><?php esc_html_e( 'Get a new amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				<span class="xdwp-box__hint" id="xdwp-renew-status" role="status"></span>
			</p>
		<?php else : ?>
			<p class="xdwp-box__error"><?php esc_html_e( 'Payment window expired. Please place a new order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<?php if ( 'underpaid' === $status ) : ?>
			<p class="xdwp-box__partial" role="status">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: amount received, 2: symbol, 3: full amount due, 4: remaining amount */
						__( 'We received %1$s %2$s of %3$s %2$s. Please send the remaining %4$s %2$s to the same address to complete your order.', 'xorro-direct-wallet-payments-woocommerce' ),
						$received,
						$coin['symbol'],
						$due,
						$amount
					)
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $network_label ) : ?>
			<p class="xdwp-box__network" role="note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: coin symbol, 2: network name as wallets and exchanges write it */
						__( 'Send %1$s on %2$s only. Sending on any other network will lose the money — it cannot be recovered.', 'xorro-direct-wallet-payments-woocommerce' ),
						$coin['symbol'],
						$network_label
					)
				);
				?>
			</p>
		<?php endif; ?>


		<div class="xdwp-box__row">
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php echo 'underpaid' === $status ? esc_html__( 'Remaining amount', 'xorro-direct-wallet-payments-woocommerce' ) : esc_html__( 'Amount', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<span class="xdwp-box__line">
				<code id="xdwp-amount" class="xdwp-box__value" dir="ltr"><bdi><?php echo esc_html( $amount ); ?> <?php echo esc_html( $coin['symbol'] ); ?></bdi></code>
				<button
					type="button"
					class="xdwp-copy button"
					id="xdwp-copy-amount"
					data-copy-text="<?php echo esc_attr( $amount ); ?>"
					data-copy-target="xdwp-amount"
				><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				</span>
				<span class="xdwp-box__fiat">
					<?php
					// After a part payment the figure above is what is left, not the order — so
					// showing the order total beside it reads as though the whole thing is owed
					// again. Show what the remainder is worth instead.
					$xdwp_quoted = (string) Xdwp_Order::meta( $order, 'amount' );
					if ( 'underpaid' === $status && '' !== $xdwp_quoted && (float) $xdwp_quoted > 0 ) {
						$xdwp_share = (float) $amount / (float) $xdwp_quoted;
						echo wp_kses_post(
							sprintf(
								/* translators: %s: the part of the order still to pay, in store currency */
								__( '≈ %s still to pay', 'xorro-direct-wallet-payments-woocommerce' ),
								wc_price( (float) $order->get_total() * $xdwp_share, array( 'currency' => $order->get_currency() ) )
							)
						);
					} else {
						/* translators: %s: order total in store currency */
						echo wp_kses_post( sprintf( __( '≈ %s order total', 'xorro-direct-wallet-payments-woocommerce' ), $order->get_formatted_order_total() ) );
					}
					?>
				</span>
			</div>
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php esc_html_e( 'Address', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<span class="xdwp-box__line">
				<code id="xdwp-address" class="xdwp-box__value xdwp-box__address" dir="ltr"><bdi><?php echo esc_html( $address ); ?></bdi></code>
				<button
					type="button"
					class="xdwp-copy button"
					id="xdwp-copy-address"
					data-copy-text="<?php echo esc_attr( $address ); ?>"
					data-copy-target="xdwp-address"
				><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
				</span>
			</div>
			<?php if ( '' !== $memo ) : ?>
				<div class="xdwp-box__field">
					<span class="xdwp-box__label"><?php echo 'tag' === $memo_kind ? esc_html__( 'Destination tag', 'xorro-direct-wallet-payments-woocommerce' ) : esc_html__( 'Memo', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
					<span class="xdwp-box__line">
					<code id="xdwp-memo" class="xdwp-box__value" dir="ltr"><bdi><?php echo esc_html( $memo ); ?></bdi></code>
					<button
						type="button"
						class="xdwp-copy button"
						id="xdwp-copy-memo"
						data-copy-text="<?php echo esc_attr( $memo ); ?>"
						data-copy-target="xdwp-memo"
					><?php esc_html_e( 'Copy', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
					</span>
					<span class="xdwp-box__hint"><?php esc_html_e( 'Paste this into your wallet with the payment. It tells us the payment is yours.', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="xdwp-box__field">
				<span class="xdwp-box__label"><?php esc_html_e( 'Network', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
				<span class="xdwp-box__value"><?php
					// The name a wallet or an exchange uses, not the internal one: a customer
					// choosing a network in Binance looks for "TRON (TRC-20)", never "TRX · trc20".
					echo esc_html( '' !== $network_label ? $network_label : $coin['network'] . ' · ' . $coin['type'] );
				?></span>
			</div>
		</div>

		<?php // Paying is the next thing a customer wants to do once they have the address,
		// so the buttons sit with it rather than after the notes. ?>
		<div class="xdwp-box__actions">
			<?php if ( ! empty( $uri ) ) : ?>
				<a class="xdwp-open-wallet" href="<?php echo esc_url( $uri ); ?>"><?php esc_html_e( 'Open in wallet app', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
			<?php endif; ?>
			<button type="button" class="button xdwp-sent" id="xdwp-sent"><?php esc_html_e( 'I have sent the payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
		</div>

		<?php
		// A customer who has paid and sees nothing happen will either pay again or email the
		// shop. Asking for the transaction id gives the shop something to trace, and pressing
		// the button gives the customer a real answer instead of a spinner.
		?>
		<div class="xdwp-sent-form">
			<label class="xdwp-sent-form__label" for="xdwp-txid">
				<?php esc_html_e( 'Transaction ID (optional)', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</label>
			<input
				type="text"
				id="xdwp-txid"
				class="xdwp-sent-form__input"
				autocomplete="off"
				autocapitalize="none"
				autocorrect="off"
				spellcheck="false"
				maxlength="128"
				aria-describedby="xdwp-txid-help"
			/>
			<p class="xdwp-sent-form__help" id="xdwp-txid-help">
				<?php esc_html_e( 'If your wallet showed you a transaction id, paste it here before pressing the button above. The shop does not need it to find your payment — it is recorded so someone can trace the transfer if anything goes wrong.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		</div>

		<?php // Filled in only when a wallet in this browser actually answers. ?>
		<div class="xdwp-wallet-pay" id="xdwp-wallet-pay"></div>
		<p class="xdwp-box__hint" id="xdwp-sent-status" role="status"></p>

		<p class="xdwp-box__assurance">
			<?php esc_html_e( 'This payment goes straight to this shop\'s own wallet. No third party holds your money.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
		</p>

		<?php // The amount and the address come first: everything else on this page is
		// something to read once, and those two are what the customer came for. ?>
		<ol class="xdwp-box__steps">
			<li><?php esc_html_e( 'Send exactly the amount shown. Network fees are paid on top, by you.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
			<li>
				<?php
				if ( '' !== $wait_estimate ) {
					echo esc_html(
						sprintf(
							/* translators: 1: number of confirmations, 2: e.g. "usually about 20 minutes" */
							_n( 'Your order confirms after %1$d network confirmation — %2$s.', 'Your order confirms after %1$d network confirmations — %2$s.', max( 1, (int) $confirmations ), 'xorro-direct-wallet-payments-woocommerce' ),
							max( 1, (int) $confirmations ),
							$wait_estimate
						)
					);
				} else {
					esc_html_e( 'Your order confirms once the network has confirmed the payment.', 'xorro-direct-wallet-payments-woocommerce' );
				}
				?>
			</li>
			<li><?php esc_html_e( 'You can close this page — we will email you when it is confirmed.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
		</ol>


		<?php // Collapsed on a phone by the script — nobody scans a code on the screen they are holding. ?>
		<details class="xdwp-box__qr" id="xdwp-qr-details" open>
			<summary class="xdwp-box__qr-summary"><?php esc_html_e( 'Show QR code', 'xorro-direct-wallet-payments-woocommerce' ); ?></summary>
			<div id="xdwp-qrcode" aria-hidden="true"></div>
			<p class="xdwp-box__hint">
				<?php
				echo esc_html(
					'' !== $network_label
						/* translators: %s: network name, e.g. "TRON (TRC-20)" */
						? sprintf( __( 'Scan with your wallet app — %s only', 'xorro-direct-wallet-payments-woocommerce' ), $network_label )
						: __( 'Scan with your wallet app', 'xorro-direct-wallet-payments-woocommerce' )
				);
				?>
			</p>
			<?php if ( ! empty( $uri ) && $uri !== $address ) : ?>
				<p class="xdwp-box__qr-plain">
					<label>
						<input type="checkbox" id="xdwp-qr-plain" />
						<?php esc_html_e( 'My wallet will not scan this — show a code with the address only', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</label>
				</p>
				<p class="xdwp-box__uri">
					<button
						type="button"
						class="button-link xdwp-copy"
						data-copy-text="<?php echo esc_attr( $uri ); ?>"
					>
						<?php esc_html_e( 'Copy payment link', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</details>

		<?php // Announced to screen readers when it changes; a customer who cannot see the page still hears "payment detected". ?>
		<p class="xdwp-box__status" id="xdwp-status-text" role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'Waiting for payment…', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
		<p class="screen-reader-text" id="xdwp-time-announce" role="status" aria-live="polite" aria-atomic="true"></p>
		<input type="hidden" id="xdwp-order-key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
	<?php endif; ?>
</div>
