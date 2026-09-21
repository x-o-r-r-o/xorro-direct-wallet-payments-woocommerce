<?php
/**
 * The setup wizard's three screens.
 *
 * Each asks one question and nothing else. Anything with a sensible default is left to the
 * settings tabs, where somebody who cares can find it.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

$xdwp_step  = Xdwp_Wizard::current_step();
$xdwp_steps = Xdwp_Wizard::steps();
$xdwp_post  = admin_url( 'admin-post.php' );

// The coin being set up: whichever was just chosen, else the first enabled one.
$xdwp_enabled = Xdwp_Settings::get( 'enabled_coins', array() );
$xdwp_enabled = is_array( $xdwp_enabled ) ? array_values( $xdwp_enabled ) : array();
$xdwp_coin_id = ! empty( $xdwp_enabled ) ? (string) $xdwp_enabled[0] : '';
$xdwp_coin    = '' !== $xdwp_coin_id ? Xdwp_Coins::get( $xdwp_coin_id ) : null;

// A short list to start from. The Coins tab has all 234; a first-time setup wants the ones
// most shops actually take, and anything else is one click away afterwards.
$xdwp_suggested = array( 'BTC', 'ETH', 'USDT_TRON', 'USDT_ETH', 'USDC_ETH', 'LTC', 'DOGE', 'SOL', 'TRX', 'BCH' );
?>
<div class="wrap xdwp-admin">
	<hr class="wp-header-end">
	<div class="xdwp-options-wrap xdwp-wizard">
		<div class="cc-header">
			<div class="cc-header-title">
				<h1><?php esc_html_e( 'Set up Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></h1>
			</div>
		</div>

		<ol class="xdwp-wizard__steps">
			<?php foreach ( $xdwp_steps as $xdwp_i => $xdwp_s ) : ?>
				<li class="xdwp-wizard__step <?php echo $xdwp_s['done'] ? 'is-done' : ''; ?><?php echo $xdwp_s['key'] === $xdwp_step ? ' is-current' : ''; ?>">
					<span class="xdwp-wizard__num" aria-hidden="true"><?php echo $xdwp_s['done'] ? '&#10003;' : esc_html( (string) ( $xdwp_i + 1 ) ); ?></span>
					<span><?php echo esc_html( $xdwp_s['title'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<div class="xdwp-wizard__panel">

			<?php if ( 'coins' === $xdwp_step ) : ?>
				<h2><?php esc_html_e( 'Which coin do you want to take first?', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
				<p class="xdwp-wizard__lead">
					<?php esc_html_e( 'Pick one to get going. You can add any of the other 233 on the Coins tab afterwards, and turn this one off again if you change your mind.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( $xdwp_post ); ?>">
					<input type="hidden" name="action" value="xdwp_wizard" />
					<input type="hidden" name="do" value="coins" />
					<?php wp_nonce_field( 'xdwp_wizard' ); ?>
					<p>
						<label for="xdwp-wizard-coin" class="screen-reader-text"><?php esc_html_e( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
						<select name="coin" id="xdwp-wizard-coin" class="cc-input cc-input-select">
							<?php
							foreach ( $xdwp_suggested as $xdwp_id ) {
								$xdwp_def = Xdwp_Coins::get( $xdwp_id );
								if ( ! $xdwp_def ) {
									continue;
								}
								printf(
									'<option value="%s">%s</option>',
									esc_attr( $xdwp_id ),
									esc_html( $xdwp_def['name'] . ' (' . $xdwp_def['symbol'] . ') — ' . Xdwp_Coins::network_label( $xdwp_def ) )
								);
							}
							?>
						</select>
					</p>
					<p><button type="submit" class="cc-btn cc-btn-primary"><?php esc_html_e( 'Next', 'xorro-direct-wallet-payments-woocommerce' ); ?></button></p>
				</form>

			<?php elseif ( 'wallet' === $xdwp_step ) : ?>
				<h2><?php esc_html_e( 'Where should the money go?', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
				<?php if ( ! $xdwp_coin ) : ?>
					<p><?php esc_html_e( 'Choose a coin first.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
					<p><a class="cc-btn cc-btn-secondary" href="<?php echo esc_url( add_query_arg( 'step', 'coins', Xdwp_Wizard::url() ) ); ?>"><?php esc_html_e( 'Back', 'xorro-direct-wallet-payments-woocommerce' ); ?></a></p>
				<?php else : ?>
					<p class="xdwp-wizard__lead">
						<?php
						printf(
							/* translators: 1: coin name, 2: network name as a wallet or exchange writes it */
							esc_html__( 'Paste a %1$s receiving address from your own wallet. Customers pay it directly — this plugin never holds the money, and it never asks for a private key or a seed phrase. Make sure it is an address on %2$s.', 'xorro-direct-wallet-payments-woocommerce' ),
							esc_html( $xdwp_coin['name'] ),
							esc_html( Xdwp_Coins::network_label( $xdwp_coin ) )
						);
						?>
					</p>
					<?php
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
					$xdwp_retry = isset( $_GET['step'] ) && 'wallet' === $_GET['step'] && ! empty( Xdwp_Settings::get( 'enabled_coins', array() ) ) && empty( Xdwp_Coins::get_payable() );
					?>
					<?php if ( $xdwp_retry ) : ?>
						<div class="notice notice-error inline">
							<p><?php esc_html_e( 'That did not look like an address for this coin, so nothing was saved. Copy it straight from your wallet rather than typing it — money sent to a wrong address cannot be recovered.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</div>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( $xdwp_post ); ?>">
						<input type="hidden" name="action" value="xdwp_wizard" />
						<input type="hidden" name="do" value="wallet" />
						<input type="hidden" name="coin" value="<?php echo esc_attr( $xdwp_coin_id ); ?>" />
						<?php wp_nonce_field( 'xdwp_wizard' ); ?>
						<p>
							<label for="xdwp-wizard-address" class="screen-reader-text"><?php esc_html_e( 'Receiving address', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
							<input type="text" name="address" id="xdwp-wizard-address" class="regular-text code" autocomplete="off" spellcheck="false" required style="width:100%;max-width:520px;" />
						</p>
						<p><button type="submit" class="cc-btn cc-btn-primary"><?php esc_html_e( 'Save and continue', 'xorro-direct-wallet-payments-woocommerce' ); ?></button></p>
					</form>
				<?php endif; ?>

			<?php elseif ( 'enable' === $xdwp_step ) : ?>
				<h2><?php esc_html_e( 'Ready to switch on', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
				<p class="xdwp-wizard__lead">
					<?php esc_html_e( 'Before it goes in front of customers, here is what the shop can do right now.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</p>
				<?php
				// The real checks, not a claim that it works: rate, address and chain, run live.
				$xdwp_checks = $xdwp_coin ? Xdwp_Selftest::run( $xdwp_coin_id ) : array();
				?>
				<ul class="xdwp-test-list">
					<?php foreach ( $xdwp_checks as $xdwp_check ) : ?>
						<li class="xdwp-test-line xdwp-test-line--<?php echo esc_attr( $xdwp_check['status'] ); ?>">
							<span class="xdwp-test-mark" aria-hidden="true"><?php echo 'fail' === $xdwp_check['status'] ? '&#10005;' : ( 'warn' === $xdwp_check['status'] ? '!' : '&#10003;' ); ?></span>
							<span><strong><?php echo esc_html( $xdwp_check['label'] ); ?>:</strong> <?php echo esc_html( $xdwp_check['detail'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<form method="post" action="<?php echo esc_url( $xdwp_post ); ?>">
					<input type="hidden" name="action" value="xdwp_wizard" />
					<input type="hidden" name="do" value="enable" />
					<?php wp_nonce_field( 'xdwp_wizard' ); ?>
					<p><button type="submit" class="cc-btn cc-btn-primary"><?php esc_html_e( 'Switch the gateway on', 'xorro-direct-wallet-payments-woocommerce' ); ?></button></p>
				</form>

			<?php else : ?>
				<h2><?php esc_html_e( 'That is it — you can take a payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></h2>
				<p class="xdwp-wizard__lead">
					<?php esc_html_e( 'Customers will see the coin at checkout. Nothing else is required, but three things are worth knowing about:', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</p>
				<ul class="xdwp-wizard__after">
					<li>
						<strong><?php esc_html_e( 'Rehearse a payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> —
						<?php esc_html_e( 'at the foot of General. Runs an order through to confirmed so you can check your emails and alerts arrive, without any money.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'API keys', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> —
						<?php esc_html_e( 'under Prices & APIs. Everything works without them, but free keys raise the rate limits, and a few chains need one to confirm payments automatically.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Add more coins', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> —
						<?php esc_html_e( 'on the Coins tab. Each one needs an address on the Wallets tab, the same as this one did.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
					</li>
				</ul>
				<p>
					<a class="cc-btn cc-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' ) ); ?>"><?php esc_html_e( 'Go to the settings', 'xorro-direct-wallet-payments-woocommerce' ); ?></a>
				</p>
			<?php endif; ?>

		</div>
	</div>
</div>
