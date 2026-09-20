<?php
/**
 * Help: what every setting does, how payments are matched, and what to do when something
 * looks wrong. Written for the person running the shop, not for a developer.
 *
 * @package Xdwp
 *
 * @var string $tab
 */

defined( 'ABSPATH' ) || exit;

$tabs   = Xdwp_Admin::tabs();
$active = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tabs['help'];

$xdwp_url = static function ( $page ) {
	return admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' . ( '' === $page ? '' : '-' . $page ) );
};

/**
 * One setting explained: what it is, what it does, and what to set it to.
 *
 * @param string $name  Setting label as it appears on screen.
 * @param string $where Which tab it lives on.
 * @param string $what  What it does.
 */
$xdwp_setting = static function ( $name, $where, $what ) {
	?>
	<tr>
		<th scope="row">
			<?php echo esc_html( $name ); ?>
			<span class="xdwp-help__where"><?php echo esc_html( $where ); ?></span>
		</th>
		<td><?php echo wp_kses_post( $what ); ?></td>
	</tr>
	<?php
};

$xdwp_sections = array(
	'start'    => __( 'Getting paid in five minutes', 'xorro-direct-wallet-payments-woocommerce' ),
	'matching' => __( 'How a payment is recognised', 'xorro-direct-wallet-payments-woocommerce' ),
	'general'  => __( 'General settings', 'xorro-direct-wallet-payments-woocommerce' ),
	'coins'    => __( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
	'wallets'  => __( 'Wallets and addresses', 'xorro-direct-wallet-payments-woocommerce' ),
	'prices'   => __( 'Prices and API keys', 'xorro-direct-wallet-payments-woocommerce' ),
	'payments' => __( 'The Payments screen', 'xorro-direct-wallet-payments-woocommerce' ),
	'refunds'  => __( 'Refunds', 'xorro-direct-wallet-payments-woocommerce' ),
	'alerts'   => __( 'Alerts and webhooks', 'xorro-direct-wallet-payments-woocommerce' ),
	'customer' => __( 'What the customer sees', 'xorro-direct-wallet-payments-woocommerce' ),
	'emails'   => __( 'Emails', 'xorro-direct-wallet-payments-woocommerce' ),
	'problems' => __( 'When something looks wrong', 'xorro-direct-wallet-payments-woocommerce' ),
	'safety'   => __( 'Safety, privacy and updates', 'xorro-direct-wallet-payments-woocommerce' ),
	'devs'     => __( 'For developers', 'xorro-direct-wallet-payments-woocommerce' ),
);
?>
<div class="wrap xdwp-admin">
	<?php // Core moves admin notices after .wp-header-end; without it they land inside the header bar next to the h1. ?>
	<hr class="wp-header-end">
	<div class="xdwp-options-wrap">
		<div class="cc-header">
			<div class="cc-header-title">
				<h1><?php esc_html_e( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ); ?></h1>
				<span class="cc-version"><?php echo esc_html( 'v' . XDWP_VERSION ); ?></span>
			</div>
			<div class="cc-header-extra">
				<span class="cc-mode-badge"><?php esc_html_e( 'Direct to wallet', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
			</div>
		</div>

		<div class="cc-layout">
			<nav class="cc-tabs" aria-label="<?php esc_attr_e( 'Xorro Wallet Payments settings', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
				<?php foreach ( $tabs as $key => $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="cc-tab <?php echo $tab === $key ? 'is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?> cc-tab-icon" aria-hidden="true"></span>
						<span class="cc-tab-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="cc-panels">
				<div class="cc-panel is-active">
					<div class="cc-panel-head">
						<h2><?php echo esc_html( $active['title'] ); ?></h2>
						<p class="cc-panel-desc"><?php echo esc_html( $active['desc'] ); ?></p>
					</div>
					<div class="cc-panel-content xdwp-help">

						<div class="xdwp-help__search">
							<label for="xdwp-help-filter" class="screen-reader-text"><?php esc_html_e( 'Search this page', 'xorro-direct-wallet-payments-woocommerce' ); ?></label>
							<input type="search" id="xdwp-help-filter" placeholder="<?php esc_attr_e( 'Search help — try “confirmations”, “memo”, “refund”…', 'xorro-direct-wallet-payments-woocommerce' ); ?>" autocomplete="off" />
							<p class="xdwp-help__search-empty" id="xdwp-help-empty" hidden><?php esc_html_e( 'Nothing here matches that. Try a shorter word.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</div>

						<div class="xdwp-help__layout">
							<nav class="xdwp-help__index" aria-label="<?php esc_attr_e( 'On this page', 'xorro-direct-wallet-payments-woocommerce' ); ?>">
								<span class="xdwp-help__index-title"><?php esc_html_e( 'On this page', 'xorro-direct-wallet-payments-woocommerce' ); ?></span>
								<?php foreach ( $xdwp_sections as $id => $label ) : ?>
									<a href="#xdwp-help-<?php echo esc_attr( $id ); ?>" data-section="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
								<?php endforeach; ?>
							</nav>

							<div class="xdwp-help__body">

							<!-- ------------------------------------------------ getting started -->
						<section id="xdwp-help-start" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['start'] ); ?></h3>
							<p><?php esc_html_e( 'This plugin takes cryptocurrency payments straight into wallets you control. No processor holds the money, there is no account to open, and nothing is taken in fees — the customer pays your address and the order is confirmed once the payment appears on the chain.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<ol class="xdwp-help__steps">
								<li>
									<strong><?php esc_html_e( 'Choose the coins you want to accept.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong>
									<?php
									printf(
										/* translators: %s: link to the Coins tab */
										esc_html__( 'On %s, tick the coins and networks you are happy to receive. Start with one or two — you can add more later.', 'xorro-direct-wallet-payments-woocommerce' ),
										'<a href="' . esc_url( $xdwp_url( 'coins' ) ) . '">' . esc_html__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
									);
									?>
								</li>
								<li>
									<strong><?php esc_html_e( 'Add a receiving address for each one.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong>
									<?php
									printf(
										/* translators: %s: link to the Wallets tab */
										esc_html__( 'On %s, paste the address from your own wallet. Use an address only this shop uses — never an exchange deposit address shared with anything else.', 'xorro-direct-wallet-payments-woocommerce' ),
										'<a href="' . esc_url( $xdwp_url( 'wallets' ) ) . '">' . esc_html__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
									);
									?>
								</li>
								<li>
									<strong><?php esc_html_e( 'Turn the gateway on.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong>
									<?php
									printf(
										/* translators: %s: link to WooCommerce payment settings */
										esc_html__( 'WooCommerce keeps the on/off switch with its other payment methods: %s.', 'xorro-direct-wallet-payments-woocommerce' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xdwp' ) ) . '">' . esc_html__( 'Payments settings', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
									);
									?>
								</li>
								<li>
									<strong><?php esc_html_e( 'Place a test order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong>
									<?php esc_html_e( 'Buy something cheap from your own shop and pay it from your phone wallet. You will see exactly what a customer sees, and the order should confirm itself within a few minutes.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
								</li>
							</ol>
							<p class="xdwp-help__note"><?php esc_html_e( 'Most coins confirm payments automatically. Five coins have no free way to check the chain — Monero, IoTeX, Casper, Kaia and Starknet — so for those you confirm the payment yourself with "Mark payment received" on the order. The Coins tab says which is which.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ matching -->
						<section id="xdwp-help-matching" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['matching'] ); ?></h3>
							<p><?php esc_html_e( 'When a customer picks a coin, the order is quoted an exact amount and an address. From then on the plugin watches that address and credits the order when a matching payment arrives.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'An exact amount.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'Each order is quoted an amount that is slightly unique, so two customers paying the same address at the same time can still be told apart. This is what "Unique payment amounts" does.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Its own address.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'On Bitcoin, Litecoin and Dogecoin you can give the plugin your wallet\'s extended public key and every order gets an address of its own — the strongest option, because nothing has to be told apart at all.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'A tag or memo.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'On XRP, Stellar, Cosmos, Secret, Sei, Injective, EOS, Hedera and TON each order carries a reference. A payment with another order\'s reference is never credited here, and one carrying this order\'s reference is accepted even on a busy address.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Enough confirmations.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'A payment is only treated as final once the chain has buried it deep enough — Bitcoin 2 blocks, Ethereum 12, TRON 20, and so on. Chains that finalise a transaction outright need only one.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
							<h4><?php esc_html_e( 'If the amount is not exactly right', 'xorro-direct-wallet-payments-woocommerce' ); ?></h4>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'Slightly short:', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'covered by the underpayment tolerance, the order is paid as normal.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Clearly short:', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'the order is marked part paid, the customer is emailed the remaining amount, and it completes when the rest arrives.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Too much:', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'the order is paid and a note records the excess so you can refund it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'After the window closed:', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'the late-payment scan still finds it for a week and tells you, so nobody\'s money is quietly lost.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Could belong to two orders:', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'nothing is credited automatically. A note on the order asks you to decide, which is the safe answer when the chain cannot tell you whose money it is.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
						</section>

							<!-- ------------------------------------------------ general -->
						<section id="xdwp-help-general" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['general'] ); ?></h3>
							<table class="xdwp-help__table">
								<tbody>
								<?php
								$xdwp_setting(
									__( 'Payment window (minutes)', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'How long the quoted amount is held, in minutes. Crypto prices move, so this is a promise you can keep: 60 minutes is comfortable, 15–30 is tighter on a volatile coin. When it runs out the customer can ask for a new amount without losing the order.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Order status after payment', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Where a paid order lands. Processing suits physical goods you still have to send; Completed suits downloads and anything delivered instantly.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Underpayment tolerance (%)', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'How far under the quoted amount still counts as paid, as a percentage. Some wallets take their fee out of the amount sent, so a small tolerance saves a lot of support mail. It is capped automatically so orders stay distinguishable.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Minimum confirmations', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'The number of confirmations every coin must reach before an order is paid. Treat it as a floor: with "Confirmations per chain" on, each coin waits for whichever is higher, its own chain\'s figure or this one.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Confirmations per chain', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'One confirmation does not mean the same thing everywhere. Leave this on and each coin waits for a number suited to its own chain. Turn it off to use a single number for all of them.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Expiry grace (minutes)', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Keeps looking for a payment for this long after the window closes, for the customer who pressed send a minute too late. Orders fail rather than cancel, so late money can still be recovered.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Unique payment amounts', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Adds a tiny, invisible difference to each order\'s amount so concurrent payments to the same address can be told apart. Leave this on unless every coin you accept gives each order its own address.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Wallet rotation', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'With several addresses saved for a coin, orders take them in turn. It spreads payments out and gives you fewer collisions to worry about.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Automatic verification', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Checks the chain for you and confirms orders without you lifting a finger. Turn it off only if you would rather confirm every payment by hand.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Late payments', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Keeps an eye on expired orders for a week. If money turns up late you get an email and a note on the order instead of a confused customer.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Partial and over payments', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Handles the customer who sends too little or too much: they are asked for the remainder, or the excess is recorded for you to refund. With this off, such payments are left for you to sort out by hand.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Checkout title, description, icon and label style', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'General', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'The title, description and icon the customer sees at checkout. You can upload your own icon and choose whether to show the icon, the text, or both.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								?>
								</tbody>
							</table>
						</section>

							<!-- ------------------------------------------------ coins -->
						<section id="xdwp-help-coins" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['coins'] ); ?></h3>
							<p><strong><?php esc_html_e( 'Price adjustment', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'a percentage off or on an order paid in that coin. Enter -2 to take 2% off, 1.5 to add 1.5%. It appears on the order as its own line, so the total the customer sees is the total they pay, and the crypto amount is worked out from that same total.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<p><?php esc_html_e( 'Every coin and network the plugin knows is listed here, grouped by chain. Ticking one offers it at checkout, as long as it has somewhere to receive.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<table class="xdwp-help__table">
								<tbody>
								<?php
								$xdwp_setting(
									__( 'Auto-verify', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Whether payments in this coin can be confirmed for you. "Manual" means you confirm them yourself on the order — the coin still works, it just needs a moment of your time.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Order min / max', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Hides a coin for orders outside this range — useful where network fees make small orders silly. Leave both empty for no limit; a maximum below the minimum is ignored rather than hiding the coin everywhere.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Confirmations', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Coins', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'The grey number is what this coin waits for now. Type your own to override it — higher is safer and slower. A large-value shop may want more than the default; nobody should go below it without a reason.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								?>
								</tbody>
							</table>
						</section>

							<!-- ------------------------------------------------ wallets -->
						<section id="xdwp-help-wallets" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['wallets'] ); ?></h3>
							<p><?php esc_html_e( 'This is where the money goes, so it is worth being careful. Paste addresses from a wallet you control and check the first one with a small test payment.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<table class="xdwp-help__table">
								<tbody>
								<?php
								$xdwp_setting(
									__( 'Addresses', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'One or more receiving addresses per coin. Each is checked for the right shape as you save it, but no check can tell whether it is yours — that is what the test payment is for.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Extended public key', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Wallets', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Optional, for Bitcoin, Litecoin and Dogecoin. Paste your wallet\'s receiving account key (xpub, ypub, zpub, Ltub or dgub) and every order is given an address of its own. It is a public key: it can create addresses and nothing else. Never paste a private key (xprv, yprv, zprv) or a seed phrase — a private key is refused here, and anywhere else it would mean giving away your money.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								?>
								</tbody>
							</table>
							<p class="xdwp-help__note"><?php esc_html_e( 'Wallets only look a little way past their last used address — usually twenty. Unpaid orders still use one up, so on a busy shop using an extended public key, rescan your wallet if new payments stop appearing.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<p class="xdwp-help__warn"><?php esc_html_e( 'Never use an address that something else also uses — an exchange deposit address, or a wallet shared with another shop. Unrelated money arriving there can be mistaken for an order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ prices -->
						<section id="xdwp-help-prices" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['prices'] ); ?></h3>
							<table class="xdwp-help__table">
								<tbody>
								<?php
								$xdwp_setting(
									__( 'CoinGecko API key, and the backup rate sources', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Rates come from CoinGecko. If it is rate-limited or down, public exchange tickers (Coinbase, Kraken, Binance) stand in. When two of them answer they must agree within 5% or no rate is used at all — a wrong rate would quote a wrong amount, which is worse than asking the customer to try again.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Stablecoin pricing', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Prices stablecoins that track your shop currency at face value, so a 17.34 order asks for exactly 17.34 USDT instead of 17.3465. Turn it off to use the live market rate instead.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Show crypto price on products, Product price coin', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Adds an approximate crypto figure next to your normal prices. It is a rough guide for shoppers, not the amount they will be quoted at checkout.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								$xdwp_setting(
									__( 'Etherscan, TronGrid, Helius, Subscan, ViewBlock and Aptos keys', 'xorro-direct-wallet-payments-woocommerce' ),
									__( 'Prices & APIs', 'xorro-direct-wallet-payments-woocommerce' ),
									esc_html__( 'Most chains are read through free public explorers and need nothing from you. A few work better — or at all — with a free key of your own: Etherscan (for Ethereum and the chains it covers), TronGrid, Helius for Solana, Subscan for Polkadot, ViewBlock for Zilliqa. The plugin tells you on screen when a key is missing rather than failing quietly.', 'xorro-direct-wallet-payments-woocommerce' )
								);
								?>
								</tbody>
							</table>
													<p><strong><?php esc_html_e( 'Confirmations by order value', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'wait less on small orders and longer on large ones. A £10 order held for six blocks costs you the customer; a £10,000 order released on one costs you £10,000. The higher tier can only raise the number a coin already waits for, never lower it. Accepting an order "as soon as it is seen" means accepting it before the chain has settled it, which is a risk you are choosing to carry.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<p><strong><?php esc_html_e( 'Backup and restore', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'at the bottom of General. Downloads every setting as one file: coins, addresses, extended keys, limits, confirmations and prices. API keys are left out unless you tick the box, so a file you email or keep in a repository carries no secrets; there is never a private key in it, because this plugin does not hold one. Restoring puts everything through the same checks as typing it in by hand.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ payments screen -->
						<section id="xdwp-help-payments" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['payments'] ); ?></h3>
							<p>
								<?php
								printf(
									/* translators: %s: link to the Payments screen */
									esc_html__( '%s lists every crypto order: what was expected, what arrived, which coin, and the transaction on the chain. The transaction id links to that chain\'s public explorer, and Download CSV exports whatever you have filtered on screen.', 'xorro-direct-wallet-payments-woocommerce' ),
									'<a href="' . esc_url( $xdwp_url( 'payments' ) ) . '">' . esc_html__( 'Payments', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
								);
								?>
							</p>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'Needs you', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'the orders that will not finish on their own: money that arrived late, more than was due, or a part payment left behind when the window closed. The number beside the menu item is this same count.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Waiting for payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'quoted, nothing received yet. These look after themselves.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Part paid', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'some money arrived and the customer has been asked for the rest.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'confirmed on the chain and handed to WooCommerce.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Expired', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'the window closed with nothing received. The customer can still ask for a new amount.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'On each order itself you also get the coin, amount, address, any tag or memo, the transaction, and a "Mark payment received" button for payments you confirmed yourself.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ refunds -->
						<section id="xdwp-help-refunds" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['refunds'] ); ?></h3>
							<p><?php esc_html_e( 'A card refund goes back the way it came. A crypto payment cannot: the address it arrived from is often an exchange\'s wallet, or a contract, and money sent back there is usually gone for good. So the customer is asked where to send it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<ol class="xdwp-help__list">
								<li><?php esc_html_e( 'Open the order and choose "Create a refund link". The link is shown once, for fifteen minutes — it is never written into the order or its notes, because anyone holding it can name the address your money goes to.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'Send that link to the customer however you normally talk to them.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'They open it and give an address on the same network. It is checked as they type it, so an address for the wrong chain is refused rather than saved.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'The order moves into "Needs you" and shows the address. You send the money from your own wallet — this plugin never holds or moves it.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'Paste the transaction id back into the order. The link stops working, and the customer can see the transaction on the chain.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ol>
							<p><?php esc_html_e( 'A link lasts fourteen days. Making a new one replaces the old one. Repeated guesses at a link from one visitor are slowed down after ten tries.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ alerts -->
						<section id="xdwp-help-alerts" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['alerts'] ); ?></h3>
							<p>
								<?php
								printf(
									/* translators: %s: link to the Alerts screen */
									esc_html__( '%s sends what happens to a payment somewhere you will see it: a webhook of your own, a Telegram chat, or both. Nothing there changes an order — it only reports.', 'xorro-direct-wallet-payments-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-alerts' ) ) . '">' . esc_html__( 'Alerts', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
								);
								?>
							</p>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'Webhook', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'each event is POSTed as JSON. With a signing secret set, the request carries X-Xdwp-Signature (sha256 HMAC of the timestamp, a full stop, and the exact body) and X-Xdwp-Timestamp. Check both: the signature proves it came from this shop, and the timestamp is what stops somebody replaying an old request at you.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Telegram', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'message @BotFather to make a bot, paste its token, then message the bot once and read the chat id from getUpdates. For a group, add the bot to the group first.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Daily summary', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'one message a day: what was paid, what it came to, and how many orders still need you.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Messages go out on a scheduled event of their own, so a slow endpoint never holds up a customer at checkout. A refused message is tried again after one minute, five, then twenty-five, and then given up on. Expired orders are off by default because a busy shop produces a lot of them.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ customer -->
						<section id="xdwp-help-customer" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['customer'] ); ?></h3>
							<ul class="xdwp-help__list">
								<li><?php esc_html_e( 'A coin picker at checkout with a search box, and a live quote of the exact amount before they commit.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'A payment page with the amount, address, any tag or memo, a QR code, an "Open in wallet app" button and a countdown. It updates itself, so the page shows "payment spotted" as soon as the transfer appears on the chain, before it is fully confirmed.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( '"I have sent the payment" asks the plugin to look now rather than at the next scheduled check.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'If the window closes before they pay, one button gets them a fresh amount at today\'s rate and keeps the order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'The same details arrive by email, so nobody loses the address by closing a tab.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
						</section>

							<!-- ------------------------------------------------ emails -->
						<section id="xdwp-help-emails" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['emails'] ); ?></h3>
							<p>
								<?php
								printf(
									/* translators: %s: link to WooCommerce email settings */
									esc_html__( 'Every message is a normal WooCommerce email, so you can switch each one off or reword it under %s.', 'xorro-direct-wallet-payments-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Emails', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
								);
								?>
							</p>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'Payment details', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'added to the order emails WooCommerce already sends while payment is due.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Payment reminder', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'sent shortly before the window closes, to the customer who got distracted.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Part payment', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'tells the customer what arrived and what is still due.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Payment alert', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> — <?php esc_html_e( 'to you, when an order needs a decision: underpaid, overpaid, late, or a transfer that could belong to more than one order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
							<p class="xdwp-help__note"><?php esc_html_e( 'To change the wording or layout yourself, copy the templates into your theme under woocommerce/emails/ — they are ordinary WooCommerce template files.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							<!-- ------------------------------------------------ problems -->
						<section id="xdwp-help-problems" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['problems'] ); ?></h3>
							<dl class="xdwp-help__faq">
								<dt><?php esc_html_e( 'The coin is not offered at checkout.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd><?php esc_html_e( 'It needs to be ticked on the Coins tab and have either an address or an extended public key on the Wallets tab. Check the order total against any minimum or maximum you set for that coin, and make sure the gateway itself is enabled in WooCommerce.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dd>

								<dt><?php esc_html_e( 'Checkout says it could not prepare the payment.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd><?php esc_html_e( 'Usually no exchange rate was available at that moment, or too many open orders on one address wanted overlapping amounts. The order notes say which. Adding a second address for that coin, or an extended public key, removes the second problem for good.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dd>

								<dt><?php esc_html_e( 'The customer paid but the order is still waiting.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd><?php esc_html_e( 'Give it the confirmations that coin needs — the Coins tab shows the number. If the amount was not exact it may be sitting as part paid, or waiting for you under "Needs you". For a manual-only coin, confirm it yourself on the order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dd>

								<dt><?php esc_html_e( 'An order says a transfer could belong to more than one order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd><?php esc_html_e( 'Two open orders on the same address could both explain that payment, so nothing was credited. Look at the transaction, decide which customer sent it, and use "Mark payment received" on that order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dd>

								<dt><?php esc_html_e( 'Automatic checks are not happening.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd><?php esc_html_e( 'They ride on WordPress\' scheduler, which only runs when your site gets traffic. A quiet shop should use a real cron job on the server. Aggressive caching or a firewall in front of the site can also block the checks.', 'xorro-direct-wallet-payments-woocommerce' ); ?></dd>

								<dt><?php esc_html_e( 'Where do I see what went wrong?', 'xorro-direct-wallet-payments-woocommerce' ); ?></dt>
								<dd>
									<?php
									printf(
										/* translators: %s: link to the WooCommerce logs screen */
										esc_html__( 'Every rate failure, explorer refusal and recovery is written to %s, under the source "xorro-wallet-payments". Each order also keeps its own notes.', 'xorro-direct-wallet-payments-woocommerce' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'WooCommerce → Status → Logs', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
									);
									?>
								</dd>
							</dl>
						</section>

							<!-- ------------------------------------------------ safety -->
						<section id="xdwp-help-safety" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['safety'] ); ?></h3>
							<ul class="xdwp-help__list">
								<li><strong><?php esc_html_e( 'No private keys, ever.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'The plugin only ever holds addresses and public keys. It cannot move your money, and neither can anyone who breaks into your website.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Nothing is trusted from the browser.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'Amounts are worked out on the server, payments are confirmed against the chain, and a transaction can only ever pay one order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Customer data.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'The coin, address, amounts, tag or memo and transaction id are stored on the order and included in WordPress\' own export and erase tools, so a customer request covers them automatically.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'What leaves your site.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'Price lookups and chain checks go to public APIs and carry only a coin name or an address — never customer details. The Prices & APIs tab lists every service used.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Updates.', 'xorro-direct-wallet-payments-woocommerce' ); ?></strong> <?php esc_html_e( 'Updates come from the project\'s own releases, and every package must carry the maintainer\'s signature. An unsigned or altered package is refused rather than installed.', 'xorro-direct-wallet-payments-woocommerce' ); ?></li>
							</ul>
						</section>

							<!-- ------------------------------------------------ developers -->
						<section id="xdwp-help-devs" class="xdwp-help__section">
							<h3><?php echo esc_html( $xdwp_sections['devs'] ); ?></h3>
							<p><?php esc_html_e( 'Actions fire as payments progress, and filters let you change the numbers this plugin decides on:', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
							<table class="xdwp-help__table xdwp-help__table--code">
								<tbody>
									<tr><th scope="row"><code>xdwp_order_paid</code></th><td><?php esc_html_e( 'An order is confirmed paid.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_order_underpaid</code></th><td><?php esc_html_e( 'A part payment arrives.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_order_overpaid</code></th><td><?php esc_html_e( 'More was sent than was due.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_order_expired</code></th><td><?php esc_html_e( 'A payment window closes unpaid.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_payment_detected</code></th><td><?php esc_html_e( 'A transfer is visible on chain but not yet confirmed.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_payment_renewed</code></th><td><?php esc_html_e( 'A customer re-quotes an expired order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_late_payment_detected</code></th><td><?php esc_html_e( 'Money arrives for an order that already expired.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_ambiguous_payment</code></th><td><?php esc_html_e( 'A transfer could belong to more than one order.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_payment_window_minutes</code></th><td><?php esc_html_e( 'Change how long an order has to pay.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_confirmations_required</code></th><td><?php esc_html_e( 'Change the confirmations a coin waits for.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_coin_allowed_for_total</code></th><td><?php esc_html_e( 'Offer or hide a coin for a given order total.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_order_memo</code></th><td><?php esc_html_e( 'Change the destination tag or memo an order asks for.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_payment_uri</code></th><td><?php esc_html_e( 'Change the wallet link behind the QR code.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_explorer_tx_url</code></th><td><?php esc_html_e( 'Point transaction links at a different explorer.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
									<tr><th scope="row"><code>xdwp_coins</code></th><td><?php esc_html_e( 'Add to or change the coin list.', 'xorro-direct-wallet-payments-woocommerce' ); ?></td></tr>
								</tbody>
							</table>
							<p class="xdwp-help__note"><?php esc_html_e( 'Frontend templates (the payment box and the emails) can be overridden from your theme, the same way WooCommerce templates are.', 'xorro-direct-wallet-payments-woocommerce' ); ?></p>
						</section>

							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
