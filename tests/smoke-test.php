#!/usr/bin/env php
<?php
/**
 * Offline smoke tests for Xorro Wallet Payments (no WordPress bootstrap required).
 *
 * Run: php tests/smoke-test.php
 *
 * @package Xdwp
 */

$root = dirname( __DIR__ );
$fail = 0;

function xdwp_assert( $cond, $msg ) {
	global $fail;
	if ( $cond ) {
		echo "[PASS] {$msg}\n";
		return;
	}
	echo "[FAIL] {$msg}\n";
	$fail++;
}

// --- PHP syntax of all plugin files ---
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	// Normalize to forward slashes — RecursiveDirectoryIterator yields
	// backslash-separated paths on Windows, which would otherwise never
	// match this exclusion there.
	$path = str_replace( '\\', '/', $file->getPathname() );
	if ( false !== strpos( $path, '/tests/' ) ) {
		continue;
	}
	$output = array();
	$code   = 0;
	$php    = PHP_BINARY;
	if ( ! $php || false !== strpos( $php, 'php-cgi' ) ) {
		$php = trim( (string) shell_exec( 'command -v php 2>/dev/null' ) );
	}
	if ( ! $php ) {
		$php = 'php';
	}
	exec( escapeshellarg( $php ) . ' -l ' . escapeshellarg( $path ) . ' 2>&1', $output, $code );
	xdwp_assert( 0 === $code, 'php -l ' . str_replace( $root . '/', '', $path ) );
}

// --- Load coin catalog in isolation ---
define( 'ABSPATH', '/tmp/' );
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}
require_once $root . '/includes/class-xdwp-coins.php';

$all = Xdwp_Coins::all();
xdwp_assert( count( $all ) >= 75, 'coin catalog size >= 75 (got ' . count( $all ) . ')' );

$required = array(
	'BTC', 'BCH', 'ETH', 'ETH_ARB', 'ETH_OP', 'ETH_BASE', 'LTC', 'DOGE', 'ARB', 'OP', 'BNB', 'SOL', 'TRX', 'XMR', 'XRP', 'ATA', 'MATIC', 'TUSD', 'USDP', 'GUSD', 'DAI',
	'USDT_ETH', 'USDT_ARB', 'USDT_OP', 'USDT_BNB', 'USDT_MATIC', 'USDT_AVAX', 'USDT_BASE', 'USDT_SOL', 'USDT_TRX',
	'USDC_ETH', 'USDC_ARB', 'USDC_OP', 'USDC_BNB', 'USDC_MATIC', 'USDC_AVAX', 'USDC_BASE', 'USDC_SOL', 'USDC_TRX',
	'DAI_ARB', 'DAI_OP', 'DAI_MATIC', 'DAI_BASE',
	'WBTC', 'AAVE', 'MKR', 'LDO', 'CRV', 'COMP', 'APE', 'SHIB', 'PEPE', 'AAVE_ARB', 'AAVE_OP',
	'FTT', 'AVAX', 'LINK', 'CAKE', 'ATOM', 'EOS', 'ETC', 'FIL', 'ALGO', 'HBAR', 'CRO', 'FTM', 'EGLD', 'NEAR', 'AXS', 'MANA', 'SAND', 'UNI', 'XLM',
);
foreach ( $required as $id ) {
	xdwp_assert( isset( $all[ $id ] ), "required coin present: {$id}" );
}

xdwp_assert( Xdwp_Coins::supports_auto_verify( 'BTC' ), 'BTC auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'BCH' ), 'BCH auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'ETH' ), 'ETH auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'ETH_BASE' ), 'ETH_BASE auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'BNB' ), 'BNB auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'USDT_ETH' ), 'USDT_ETH auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'USDT_BASE' ), 'USDT_BASE auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'USDC_TRX' ), 'USDC_TRX auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'DAI' ), 'DAI auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'WBTC' ), 'WBTC auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'FTM' ), 'FTM auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'CRO' ), 'CRO auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'ETC' ), 'ETC auto-verify' );
xdwp_assert( ! Xdwp_Coins::supports_auto_verify( 'XMR' ), 'XMR is manual' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'ALGO' ), 'ALGO auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'HBAR' ), 'HBAR auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'NEAR' ), 'NEAR auto-verify' );
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'ATOM' ), 'ATOM auto-verify' );

// Coins added across the three most recent batches (219 -> 234 coins) — a
// representative sample per new verifier family, not an exhaustive list.
$recent_required = array(
	'BTG', 'FIRO', 'XZC', 'RVN', 'PIVX', 'NEO', 'GAS', 'THETA', 'TFUEL',
	'DGB', 'KMD', 'QTUM', 'ARK', 'AE', 'ICX', 'ONT', 'KLV', 'TET', 'XEM', 'XYM', 'RUNE', 'LGCY', 'IOTX',
	'LSK', 'STRAX', 'IOTA',
);
foreach ( $recent_required as $id ) {
	xdwp_assert( isset( $all[ $id ] ), "recently-added coin present: {$id}" );
}
xdwp_assert( count( $all ) >= 234, 'coin catalog size >= 234 (got ' . count( $all ) . ')' );

// One coin per newly-added auto-verified verifier group should report true;
// the deliberately manual-only groups (no free/keyless verification API)
// must report false, or a merchant would see "auto-verify: yes" for a coin
// that silently never gets marked paid automatically.
foreach ( array( 'BTG', 'NEO', 'THETA', 'DGB', 'KMD', 'QTUM', 'ARK', 'AE', 'ICX', 'ONT', 'KLV', 'TET', 'XEM', 'XYM', 'RUNE', 'LGCY', 'LSK', 'STRAX', 'IOTA' ) as $id ) {
	xdwp_assert( Xdwp_Coins::supports_auto_verify( $id ), "{$id} auto-verify" );
}
foreach ( array( 'XMR', 'IOTX', 'DOT' ) as $id ) {
	xdwp_assert( ! Xdwp_Coins::supports_auto_verify( $id ), "{$id} is manual (no viable keyless auto-verify API)" );
}

$base = Xdwp_Coins::to_base_units( '1.5', 6 );
xdwp_assert( '1500000' === $base, "EIP-681 base units 1.5@6 => {$base}" );

$btc_uri = Xdwp_Coins::payment_uri( 'BTC', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', '0.01' );
xdwp_assert( 0 === strpos( $btc_uri, 'bitcoin:' ), "BTC BIP-21 scheme => {$btc_uri}" );
xdwp_assert( false !== strpos( $btc_uri, 'amount=0.01' ), "BTC amount => {$btc_uri}" );
xdwp_assert( false === strpos( $btc_uri, 'btc:' ), 'BTC must not use btc: scheme' );

$bch_uri = Xdwp_Coins::payment_uri( 'BCH', 'bitcoincash:qp3wjpa3tjlj042z2wv7jabukkwz6x8n4y0x8x8x8x', '0.25' );
xdwp_assert( 0 === strpos( $bch_uri, 'bitcoincash:' ), "BCH CashAddr URI => {$bch_uri}" );
xdwp_assert( false !== strpos( $bch_uri, 'amount=0.25' ), "BCH amount => {$bch_uri}" );
xdwp_assert( 1 === substr_count( strtolower( $bch_uri ), 'bitcoincash:' ), 'BCH must not double bitcoincash: scheme' );

$eth_uri = Xdwp_Coins::payment_uri( 'ETH', '0xabcABC0000000000000000000000000000000001', '1.5' );
xdwp_assert( 0 === strpos( $eth_uri, 'ethereum:0xabcabc' ), "ETH EIP-681 => {$eth_uri}" );
xdwp_assert( false !== strpos( $eth_uri, '@1?' ), "ETH chain id 1 => {$eth_uri}" );
xdwp_assert( false !== strpos( $eth_uri, 'value=1500000000000000000' ), "ETH value wei => {$eth_uri}" );

$eth_base = Xdwp_Coins::payment_uri( 'ETH_BASE', '0xabcABC0000000000000000000000000000000001', '0.01' );
xdwp_assert( false !== strpos( $eth_base, '@8453?' ), "ETH_BASE chain id 8453 => {$eth_base}" );

$usdt_bnb = Xdwp_Coins::payment_uri( 'USDT_BNB', '0xabcABC0000000000000000000000000000000001', '10' );
xdwp_assert( false !== strpos( $usdt_bnb, '@56/transfer' ), "USDT_BNB chain 56 => {$usdt_bnb}" );
xdwp_assert( false !== strpos( $usdt_bnb, 'uint256=' ), "USDT_BNB uint256 => {$usdt_bnb}" );

$sol_uri = Xdwp_Coins::payment_uri( 'SOL', 'So11111111111111111111111111111111111111112', '2.5' );
xdwp_assert( 0 === strpos( $sol_uri, 'solana:' ), "SOL Solana Pay => {$sol_uri}" );

$usdt_sol = Xdwp_Coins::payment_uri( 'USDT_SOL', 'So11111111111111111111111111111111111111112', '5' );
xdwp_assert( false !== strpos( $usdt_sol, 'spl-token=' ), "USDT_SOL spl-token => {$usdt_sol}" );

$usdt_trx = Xdwp_Coins::payment_uri( 'USDT_TRX', 'TXYZabcdefghijklmnopqrstuvwxyz1234567', '1' );
xdwp_assert( $usdt_trx === 'TXYZabcdefghijklmnopqrstuvwxyz1234567', 'USDT_TRX bare address QR' );

// --- Verifier source checks ---
$verifier = file_get_contents( $root . '/includes/class-xdwp-verifier.php' );
xdwp_assert( false !== strpos( $verifier, 'api.etherscan.io/v2/api' ), 'Etherscan V2 endpoint present' );
xdwp_assert( false !== strpos( $verifier, 'mempool.space' ), 'mempool.space present' );
xdwp_assert( false !== strpos( $verifier, 'helius-rpc.com' ), 'Helius RPC present' );
xdwp_assert( false !== strpos( $verifier, 'TRON-PRO-API-KEY' ), 'TronGrid key header present' );
xdwp_assert( false !== strpos( $verifier, 'function match_band' ), 'verifier match_band helper' );
xdwp_assert( false !== strpos( $verifier, 'SuccessValue' ) || false !== strpos( $verifier, 'SuccessReceiptId' ), 'NEAR success status SuccessValue/SuccessReceiptId' );
xdwp_assert( false !== strpos( $verifier, 'tolerance_pct <= 0' ), 'match_band exact at 0% underpayment tolerance' );
xdwp_assert( false !== strpos( $verifier, "'TransferContract' !== \$contract_type" ) && false === strpos( $verifier, '$contract_type &&' ), 'native TRX TransferContract fail-closed (no truthy guard)' );
xdwp_assert( false !== strpos( $verifier, 'etherscan_confirmed' ), 'verifier confirmation check' );
xdwp_assert( false !== strpos( $verifier, 'confirmations_ok' ), 'verifier confirmations_ok helper' );
xdwp_assert( false !== strpos( $verifier, 'block_depth_ok' ), 'verifier block_depth_ok helper' );
xdwp_assert( false !== strpos( $verifier, 'http_get_int' ), 'verifier tip height helper' );
xdwp_assert( false !== strpos( $verifier, 'finalized' ), 'solana finalized commitment' );
xdwp_assert( false !== strpos( $verifier, 'only_confirmed=true' ), 'tron only_confirmed list' );
xdwp_assert( false !== strpos( $verifier, 'tron_tx_block_number' ), 'tron block lookup helper' );
xdwp_assert( false === strpos( $verifier, '$need <= 20' ), 'tron does not soft-accept without block height' );
xdwp_assert( false !== strpos( $verifier, 'amount_safe_for_address' ), 'shared-address amount safety helper' );
xdwp_assert( false !== strpos( $verifier, 'max_peers' ), 'shared-address peer pagination fail-closed' );
xdwp_assert( false !== strpos( $verifier, 'option_value = %s WHERE option_name = %s AND option_value = %s' ), 'txid claim compare-and-swap' );
xdwp_assert( false !== strpos( $verifier, 'function release_txid' ), 'release_txid helper' );
xdwp_assert( false !== strpos( $verifier, 'function claim_txid' ) && false !== strpos( $verifier, 'strtolower( trim( (string) $txid ) )' ), 'strtolower in claim_txid path' );
xdwp_assert( false !== strpos( $verifier, 'contractAddress' ), 'EVM token matcher checks contractAddress' );

// Verifier functions added across the three most recent batches — confirms
// the source still contains each dedicated helper rather than a coin
// silently falling through to a stub/default case.
$recent_verifier_functions = array(
	'check_blockbook', 'check_neo', 'check_theta',
	'check_esplora', 'check_insight', 'check_qtum',
	'check_ark', 'check_aeternity', 'check_icon', 'check_ontology', 'check_klever',
	'check_tectum', 'check_nem', 'check_symbol', 'check_thorchain',
	'check_blockscout_v2_token', 'check_iota', 'hex_to_decimal_string',
);
foreach ( $recent_verifier_functions as $fn ) {
	xdwp_assert( false !== strpos( $verifier, "function {$fn}(" ), "verifier defines {$fn}()" );
}
// Manual-only coins from recent batches must each have an explicit case
// (with a reason) rather than silently relying on the switch's default.
foreach ( array( 'iotx' ) as $manual_case ) {
	xdwp_assert( false !== strpos( $verifier, "case '{$manual_case}':" ), "find_payment has an explicit case for manual-only '{$manual_case}'" );
}
$prices_src = file_get_contents( $root . '/includes/class-xdwp-prices.php' );
preg_match( '/DUST_STEP = (\d+);/', $prices_src, $dust_step );
preg_match( '/DUST_SLOTS = (\d+);/', $prices_src, $dust_slots );
preg_match( '/DUST_BAND = (\d+);/', $prices_src, $dust_band );
xdwp_assert( ! empty( $dust_step ) && ! empty( $dust_band ) && 2 * (int) $dust_band[1] < (int) $dust_step[1], 'unique dust bands never overlap' );
xdwp_assert( false !== strpos( $prices_src, 'Xdwp_Verifier::occupied_amounts(' ), 'unique dust uses lowest free slot (no large overcharge)' );
$verifier_src = file_get_contents( $root . '/includes/class-xdwp-verifier.php' );
xdwp_assert( false !== strpos( $verifier_src, 'private static function to_e18(' ), 'amount matching uses exact fixed-point' );
xdwp_assert( false === strpos( $verifier_src, 'http://' ), 'no plain-HTTP verification endpoints' );
xdwp_assert( false !== strpos( $verifier_src, "'date_created'   => '>'" ), 'peer-order query bounded by age' );
xdwp_assert( false !== strpos( $verifier_src, '\'SUCCESS\' !== $tx[\'ret\'][0][\'contractRet\']' ), 'TRON native requires contractRet SUCCESS' );
xdwp_assert( false !== strpos( $verifier_src, '! array_key_exists( \'assetId\', $tx ) || null !== $tx[\'assetId\']' ), 'Waves fails closed on missing assetId' );
xdwp_assert( false !== strpos( $verifier_src, '\'eosio.token\' !== $act[\'account\']' ), 'EOS requires eosio.token contract' );
xdwp_assert( false !== strpos( $verifier_src, 'recipientAddress=' ), 'Symbol filters by recipient' );
xdwp_assert( false !== strpos( $verifier_src, '$row_master' ), 'TON jetton master re-checked per row' );
$ajax_src = file_get_contents( $root . '/includes/class-xdwp-ajax.php' );
xdwp_assert( false !== strpos( $ajax_src, "'wc_ajax_xdwp_quote'" ) && false !== strpos( $ajax_src, "'wc_ajax_xdwp_status'" ), 'frontend AJAX available via wc-ajax' );
// The ceiling lives on Xdwp_Order so the browser poll, the "I have sent it" button and the
// verdict all spend from one allowance rather than three.
$xdwp_order_src = file_get_contents( $root . '/includes/class-xdwp-order.php' );
xdwp_assert( false !== strpos( $xdwp_order_src, "const LOOK_BUDGET_KEY = 'xdwp_ajax_verify_budget'" ), 'the site-wide chain-lookup budget is defined once' );
xdwp_assert( false !== strpos( $ajax_src, 'Xdwp_Order::LOOK_BUDGET' ), 'site-wide budget on poll-triggered chain checks' );
xdwp_assert( false !== strpos( $xdwp_order_src, 'self::LOOK_BUDGET' ), 'and the customer-facing verdict spends from the same allowance' );
xdwp_assert( false !== strpos( $ajax_src, 'floor( time() / MINUTE_IN_SECONDS )' ), 'rate limits use fixed windows (no never-expiring counter)' );
xdwp_assert( false === strpos( file_get_contents( $root . '/templates/payment.php' ), 'onclick=' ), 'payment template has no inline handlers (CSP)' );
$updater_src = file_get_contents( $root . '/includes/class-xdwp-updater.php' );
$workflow    = file_get_contents( $root . '/.github/workflows/release.yml' );
xdwp_assert( 1 === preg_match( "/RELEASE_PUBLIC_KEYS = array\\(\\s*'[A-Za-z0-9+\\/]{43}='/", $updater_src ), 'updater embeds an Ed25519 release public key' );
xdwp_assert( false !== strpos( $updater_src, 'sodium_crypto_sign_verify_detached' ), 'updater verifies Ed25519 signatures' );
xdwp_assert( false !== strpos( $updater_src, "'xdwp_bad_signature'" ), 'updater refuses unsigned/invalid packages' );
xdwp_assert( false !== strpos( $workflow, 'openssl pkeyutl -sign' ) && false !== strpos( $workflow, '.zip.sig#' ), 'release workflow signs and publishes .sig' );
// The workflow manifest must match Xdwp_Updater::signed_manifest() byte for byte.
xdwp_assert( false !== strpos( $workflow, "printf 'xdwp-release:1\\nplugin:xorro-direct-wallet-payments-woocommerce\\nversion:%s\\nsha256:%s\\n'" ) && false !== strpos( $updater_src, "const MANIFEST_FORMAT = 'xdwp-release:1'" ), 'signed manifest format matches workflow' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), "'.svg?ver=' . rawurlencode( XDWP_VERSION )" ), 'coin icon URLs are versioned (cache busting)' );
$rates_src = file_get_contents( $root . '/includes/class-xdwp-rates.php' );
xdwp_assert( false !== strpos( $rates_src, 'MAX_DISAGREEMENT' ) && false !== strpos( $rates_src, 'api.kraken.com' ) && false !== strpos( $rates_src, 'api.coinbase.com' ), 'backup rate sources with agreement check' );
xdwp_assert( false !== strpos( $prices_src, 'Xdwp_Rates::pegged_rate(' ) && false !== strpos( $prices_src, 'Xdwp_Rates::fallback_rate(' ), 'prices use peg + fallback sources' );
$emails_src = file_get_contents( $root . '/includes/class-xdwp-emails.php' );
xdwp_assert( false !== strpos( $emails_src, 'woocommerce_email_before_order_table' ), 'payment details added to WooCommerce customer emails' );
foreach ( array( 'payment-reminder', 'partial-payment', 'payment-alert' ) as $xdwp_mail ) {
	xdwp_assert( is_file( $root . '/includes/emails/class-xdwp-email-' . $xdwp_mail . '.php' ), "email class $xdwp_mail" );
	xdwp_assert( is_file( $root . '/templates/emails/xdwp-' . $xdwp_mail . '.php' ) && is_file( $root . '/templates/emails/plain/xdwp-' . $xdwp_mail . '.php' ), "email templates (html + plain) $xdwp_mail" );
}
xdwp_assert( false !== strpos( $verifier_src, 'function scan_partial_or_overpayment(' ), 'partial / overpayment detection' );
xdwp_assert( false !== strpos( $verifier_src, 'is_own_partial( $order, $hit' ), 'a partial transfer is never counted twice' );
xdwp_assert( false !== strpos( $verifier_src, 'transfer_is_ambiguous( $coin, $address, $order->get_id(), $hit[' ), 'non-exact transfers credited only when unambiguous (shared-address safety)' );
xdwp_assert( false !== strpos( $verifier_src, 'function flag_ambiguous_transfer(' ), 'ambiguous transfers alert the store owner once' );
xdwp_assert( false !== strpos( $verifier_src, "=== strtolower( (string) Xdwp_Order::meta( \$order, 'txid' ) )" ), 'final payment recorded idempotently' );
$gateway_src = file_get_contents( $root . '/includes/class-xdwp-gateway.php' );
xdwp_assert( strpos( $gateway_src, 'already received a crypto payment' ) > strpos( $gateway_src, 'public function process_payment' ), 're-pay guard lives in process_payment' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp.php' ), "did_action( 'init' )" ), 'cron label not translated before init' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-cron.php' ), 'function scan_late_payments(' ), 'late payment scan' );
xdwp_assert( false !== strpos( $verifier_src, 'function read_option_raw(' ) && false === strpos( $verifier_src, "get_option( \$key, '' )" ), 'locks/claims read uncached (object-cache safe)' );
xdwp_assert( false !== strpos( $prices_src, 'amount_slot_taken(' ), 'unique amount skips reserved slots' );
$settings_view = file_get_contents( $root . '/includes/admin/views/settings-page.php' );
xdwp_assert( false !== strpos( $settings_view, 'wp-header-end' ), 'admin notices anchored above settings header' );

$order_php = file_get_contents( $root . '/includes/class-xdwp-order.php' );
xdwp_assert( false !== strpos( $order_php, 'expiry_grace_minutes' ), 'order expiry grace' );
xdwp_assert( false !== strpos( $order_php, "'failed'" ), 'expired orders fail not cancel' );
xdwp_assert( false !== strpos( $order_php, 'xdwp_status_' ), 'order-bound status nonce' );
xdwp_assert( false !== strpos( $order_php, 'REQUEST_METHOD' ), 'mark-paid POST only' );
xdwp_assert( false !== strpos( $order_php, 'xdwp_confirm_manual' ), 'manual mark-paid requires confirmation' );
xdwp_assert( false !== strpos( $order_php, 'reserve_txid' ), 'manual mark-paid reserves txid' );
xdwp_assert( false !== strpos( $order_php, 'release_txid' ), 'manual mark-paid releases txid on failure' );
xdwp_assert( false !== strpos( $order_php, 'Wrong status or cannot be marked' ) || false !== strpos( $order_php, 'wrong status' ), 'mark-paid eligibility check Wrong status or cannot be marked' );
xdwp_assert( false !== strpos( $order_php, 'amount_safe_for_address' ), 'assign_payment checks amount collisions' );
xdwp_assert( false !== strpos( $order_php, 'reserve_amount_slot' ), 'assign_payment reserves amount slot' );
xdwp_assert( false !== strpos( $order_php, 'verify_order' ), 'expire path last-chance verify' );
xdwp_assert( false !== strpos( $order_php, 'on_status_changed' ), 'order on_status_changed hook' );
xdwp_assert( false !== strpos( $order_php, 'on_order_terminal' ), 'order on_order_terminal helper' );
xdwp_assert( false !== strpos( $order_php, "'expired'" ) && false !== strpos( $order_php, 'can_mark' ), 'mark-paid available for expired' );
xdwp_assert( false !== strpos( $verifier, "'cancelled'" ) && false !== strpos( $verifier, 'max_peers' ), 'cancelled in peer status list' );
xdwp_assert( false !== strpos( $order_php, "_xdwp_status', 'cancelled'" ), 'xdwp_status cancelled on terminal' );
xdwp_assert( false !== strpos( $verifier, "has_validated && ! \$tx['validated']" ), 'XRP rejects validated===false' );
xdwp_assert( false !== strpos( $verifier, 'xrp_delivered_xrp' ), 'XRP uses delivered_amount helper' );
xdwp_assert( false !== strpos( $verifier, 'xrp_amount_to_xrp' ), 'XRP amount parser present' );
xdwp_assert( false !== strpos( $verifier, "return ( (float) \$raw['value'] ) / 1e6;" ), 'XRP object value is drops not whole XRP' );
xdwp_assert( false === strpos( $verifier, "isset( \$tx['Amount'] ) ? \$tx['Amount']" ), 'XRP does not credit Amount field' );
xdwp_assert( false !== strpos( $verifier, "if ( ! \$recipient || 0 !== strcasecmp( \$recipient, \$address ) )" ), 'ATOM requires recipient match' );

$settings_php = file_get_contents( $root . '/includes/class-xdwp-settings.php' );
xdwp_assert( false !== strpos( $settings_php, 'XDWP_ETHERSCAN_API_KEY' ), 'API keys support wp-config constants' );
xdwp_assert( false !== strpos( $settings_php, 'is_masked_secret' ), 'API key sanitize keeps blank submissions' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/views/settings-page.php' ), 'api_key_input_placeholder' ), 'settings do not echo API keys' );

$ajax = file_get_contents( $root . '/includes/class-xdwp-ajax.php' );
xdwp_assert( false !== strpos( $ajax, 'xdwp_status_' ), 'ajax order-bound nonce' );
xdwp_assert( false === strpos( $ajax, 'register_admin_assets' ), 'ajax has no duplicate admin asset enqueue' );
xdwp_assert( false === strpos( $settings_php, 'XDWP_BSCSCAN_API_KEY' ), 'settings dropped legacy explorer constants' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'bscscan_api_key' ), 'verifier has no legacy explorer fallback' );
xdwp_assert( ! is_file( $root . '/assets/svg/coins/usdt.svg' ), 'orphan usdt.svg removed' );
xdwp_assert( is_file( $root . '/assets/svg/coins/usdt-round.svg' ), 'usdt-round.svg kept' );
xdwp_assert( false !== strpos( $verifier, 'reserve_amount_slot' ), 'amount slot reservation helper' );
xdwp_assert( false !== strpos( $verifier, 'release_amount_slot' ), 'amount slot release helper' );
xdwp_assert( false !== strpos( $verifier, 'soft_finality_ok' ), 'soft finality helper' );
// soft_finality_ok must not short-circuit to true on need<=0; success path is return (bool) $validated.
if ( preg_match( '/function soft_finality_ok\s*\([^)]*\)\s*\{(.*?)\n\t\}/s', $verifier, $sfm ) ) {
	$sf_body = $sfm[1];
	xdwp_assert( false === strpos( $sf_body, 'if ( $need <= 0 ) { return true; }' ), 'soft_finality_ok no early need<=0 true' );
	xdwp_assert( false !== strpos( $sf_body, 'return (bool) $validated' ), 'soft_finality_ok returns validated bool' );
} else {
	xdwp_assert( false, 'soft_finality_ok body extractable' );
}
// ZIL amounts always as Qa (12 decimals) near check_zil.
$zil_pos = strpos( $verifier, 'function check_zil' );
$zil_slice = false !== $zil_pos ? substr( $verifier, $zil_pos, 2500 ) : '';
// Helius RPC requires ?api-key= (documented); also send X-Api-Key.
xdwp_assert( false !== strpos( $verifier, 'api-key=' ), 'Helius uses documented api-key query' );
xdwp_assert( false !== strpos( $verifier, 'X-Api-Key' ), 'Helius also sends X-Api-Key header' );
xdwp_assert( false !== strpos( $verifier, 'tron_destination_matches' ), 'tron destination matcher present' );
xdwp_assert( false !== strpos( $verifier, 'tron_hex_to_base58' ), 'tron hex to base58 helper' );
xdwp_assert( false !== strpos( $verifier, 'base58_encode' ), 'base58_encode helper' );
xdwp_assert( false !== strpos( $verifier, "isset( \$tx['transaction_successful'] ) && \$tx['transaction_successful']" ), 'stellar requires explicit success' );
xdwp_assert( false !== strpos( $verifier, "isset( \$row['irreversible'] ) && \$row['irreversible']" ), 'eos requires irreversible' );
xdwp_assert( false !== strpos( $verifier, "isset( \$tx['receipt']['exitCode'] ) && 0 === (int) \$tx['receipt']['exitCode']" ), 'fil requires explicit exitCode 0' );
xdwp_assert( false === strpos( $verifier, 'tokenDecimal' ), 'tokenDecimal not preferred over configured decimals' );
xdwp_assert( false !== strpos( $verifier, '$dec = (int) $decimals' ), 'raw_amount uses configured decimals near contractAddress' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'xdwp_pkg_sha_' ), 'updater caches package sha256' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), '$allow_stale' ), 'prices support fail-closed fresh rates' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/uninstall.php' ), 'xdwp_amt_' ), 'uninstall clears amount slots' );

// --- Headers ---
$main = file_get_contents( $root . '/xorro-direct-wallet-payments-woocommerce.php' );
preg_match( '/Version:\s+([0-9.]+)/', $main, $header_ver );
preg_match( "/XDWP_VERSION', '([0-9.]+)'/", $main, $const_ver );
xdwp_assert( ! empty( $header_ver ) && ! empty( $const_ver ) && $header_ver[1] === $const_ver[1], 'plugin header version matches XDWP_VERSION' );
xdwp_assert( false !== strpos( $main, 'Author:            xorro' ), 'author is xorro' );
xdwp_assert( false !== strpos( $main, 'Author URI:        https://github.com/x-o-r-r-o' ), 'author URI is GitHub profile' );
xdwp_assert( false === strpos( $main, 'Author URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce' ), 'author URI not the plugin repo' );
xdwp_assert( false === strpos( $main, 'Author URI:        https://wordpress.org/plugins/xdwp' ), 'author URI not same as old plugin URI' );
xdwp_assert( false !== strpos( $main, 'Requires at least: 6.9' ), 'Requires WP 6.9+' );
xdwp_assert( false !== strpos( $main, 'WC requires at least: 10.0' ), 'Requires WC 10.0+' );
xdwp_assert( false !== strpos( $main, 'WC tested up to:   11.1' ), 'WC tested up to 11.1' );
xdwp_assert( false !== strpos( $main, 'custom_order_tables' ), 'HPOS compatibility declared' );
xdwp_assert( false !== strpos( $main, 'cart_checkout_blocks' ), 'Blocks compatibility declared' );

$branding = file_get_contents( $root . '/includes/class-xdwp-branding.php' );
xdwp_assert( false !== strpos( $branding, 'checkout_display' ), 'branding display mode' );
xdwp_assert( false !== strpos( $branding, 'checkout_icon_width' ), 'branding icon width' );
xdwp_assert( false !== strpos( $branding, 'get_icon_html' ), 'branding icon html' );

$gateway = file_get_contents( $root . '/includes/class-xdwp-gateway.php' );
xdwp_assert( false !== strpos( $gateway, 'function get_icon' ), 'gateway get_icon override' );
xdwp_assert( false !== strpos( $gateway, 'filter_gateway_title' ), 'gateway title filter' );

$frontend_css = file_get_contents( $root . '/assets/css/frontend.css' );
xdwp_assert( false !== strpos( $frontend_css, 'xdwp-gateway-icon' ), 'frontend icon CSS' );
xdwp_assert( false !== strpos( $frontend_css, 'xdwp-box' ), 'classic payment box CSS' );
xdwp_assert( false !== strpos( $frontend_css, 'xdwp-coin-option__icon' ), 'coin option img icon CSS' );
xdwp_assert( false === strpos( $frontend_css, 'xdwp-paybox' ), 'cryptoniq paybox CSS removed' );
$payment_tpl = file_get_contents( $root . '/templates/payment.php' );
xdwp_assert( false !== strpos( $payment_tpl, 'xdwp-box' ), 'payment template uses classic box' );
xdwp_assert( false === strpos( $payment_tpl, 'xdwp-paybox' ), 'payment template has no paybox markup' );
$frontend_js = file_get_contents( $root . '/assets/js/frontend.js' );
xdwp_assert( false !== strpos( $frontend_js, 'xdwp-timer' ), 'frontend timer hook' );
xdwp_assert( false === strpos( $frontend_js, 'paybox' ), 'frontend JS has no paybox refs' );
$admin_css = file_get_contents( $root . '/assets/css/admin.css' );
xdwp_assert( false !== strpos( $admin_css, 'xdwp-options-wrap' ), 'cryptoniq-style admin shell CSS' );
xdwp_assert( false !== strpos( $admin_css, 'cc-header' ), 'admin header class' );
xdwp_assert( is_dir( $root . '/assets/svg/coins' ), 'coin svg directory' );
xdwp_assert( is_file( $root . '/assets/svg/coins/xmr.svg' ), 'XMR icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/link.svg' ), 'LINK icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/hbar.svg' ), 'HBAR icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/bch.svg' ), 'BCH icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/base.svg' ), 'Base icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/dai.svg' ), 'DAI icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/wbtc.svg' ), 'WBTC icon present' );
xdwp_assert( is_file( $root . '/assets/svg/coins/aave.svg' ), 'AAVE icon present' );
foreach ( array( 'btg', 'neo', 'theta', 'dgb', 'kmd', 'qtum', 'ark', 'icx', 'xem', 'lsk', 'iota', 'strax', 'tfuel' ) as $ticker ) {
	xdwp_assert( is_file( $root . "/assets/svg/coins/{$ticker}.svg" ), strtoupper( $ticker ) . ' icon present' );
}
xdwp_assert( false !== strpos( $verifier, "case 'bch'" ), 'verifier BCH case' );
xdwp_assert( false !== strpos( $verifier, "case 'base'" ), 'verifier Base case' );
xdwp_assert( false !== strpos( $verifier, 'bitcoin-cash' ), 'Blockchair bitcoin-cash' );
$coins_php = file_get_contents( $root . '/includes/class-xdwp-coins.php' );
xdwp_assert( false !== strpos( $coins_php, 'polygon-ecosystem-token' ), 'MATIC uses POL CoinGecko id' );
xdwp_assert( false !== strpos( $coins_php, '8453' ), 'EIP-155 Base chain id 8453' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'YourApiKeyToken' ), 'no placeholder Etherscan key' );

$blocks_js = file_get_contents( $root . '/assets/js/blocks.js' );
xdwp_assert( false !== strpos( $blocks_js, 'iconWidth' ), 'blocks icon width' );
xdwp_assert( false !== strpos( $blocks_js, "display === 'text'" ), 'blocks text-only mode' );

$readme_md = file_get_contents( $root . '/README.md' );
xdwp_assert( false !== strpos( $readme_md, 'Checkout branding' ), 'README.md branding section' );

$readme = file_get_contents( $root . '/readme.txt' );
xdwp_assert( false !== strpos( $readme, 'Tested up to: 7.1' ), 'readme Tested up to WP 7.1' );
xdwp_assert( ! empty( $const_ver ) && false !== strpos( $readme, 'Stable tag: ' . $const_ver[1] ), 'readme stable tag matches XDWP_VERSION' );

$readme = file_get_contents( $root . '/readme.txt' );
xdwp_assert( false !== strpos( $readme, '== External services ==' ), 'readme external services section' );
xdwp_assert( false !== strpos( $readme, '1.5.16' ), 'readme 1.5.16 changelog' );
$privacy = file_get_contents( $root . '/includes/class-xdwp-privacy.php' );
xdwp_assert( false !== strpos( $privacy, 'wp_add_privacy_policy_content' ), 'privacy policy content registered' );
xdwp_assert( is_file( $root . '/assets/js/qrcode.LICENSE.txt' ), 'qrcode license attribution' );
xdwp_assert( is_file( $root . '/includes/admin/index.php' ), 'admin index.php silence' );

xdwp_assert( false !== strpos( $main, 'Xorro Direct Wallet Payments for WooCommerce' ), 'plugin display name distinctive' );
xdwp_assert( false !== strpos( $main, 'Text Domain:       xorro-direct-wallet-payments-woocommerce' ), 'text domain matches slug' );
xdwp_assert( false !== strpos( $main, 'Plugin URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce' ), 'plugin URI uses GitHub repo' );
xdwp_assert( false !== strpos( $main, 'Update URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce' ), 'Update URI points at GitHub' );
// Plugin URI and Author URI must differ (wordpress.org).
preg_match( '/^\s*\*\s*Plugin URI:\s*(.+)$/m', $main, $pu );
preg_match( '/^\s*\*\s*Author URI:\s*(.+)$/m', $main, $au );
xdwp_assert( ! empty( $pu[1] ) && ! empty( $au[1] ) && trim( $pu[1] ) !== trim( $au[1] ), 'plugin URI differs from author URI' );
xdwp_assert( is_readable( $root . '/includes/class-xdwp-updater.php' ), 'GitHub updater class present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'update_plugins_github.com' ), 'updater hooks update_plugins_github.com' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'releases/latest' ), 'updater fetches GitHub latest release' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'hash_file' ), 'updater verifies ZIP sha256' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'is_allowed_package_url' ), 'updater allowlists package hosts' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'is_our_package_url' ), 'updater is_our_package_url present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'releases/download' ), 'updater package URL requires releases/download' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-wallets.php' ), 'LAST_INSERT_ID' ), 'wallet rotation uses LAST_INSERT_ID' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp.php' ), 'load_plugin_textdomain' ), 'no load_plugin_textdomain' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), "echo '<style" ), 'admin has no raw style echo' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), '<link rel="stylesheet"' ), 'admin has no raw stylesheet link' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), 'wp_add_inline_style' ), 'admin shells CSS via wp_add_inline_style backup' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), 'enqueue_shell_assets' ), 'admin enqueue_shell_assets present' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-gateway.php' ), "echo '<style" ), 'gateway has no raw style echo' );
xdwp_assert( false === strpos( $payment_tpl, '<script>' ), 'payment template has no inline script' );
xdwp_assert( is_file( $root . '/assets/js/wallets.js' ), 'wallets.js present' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/admin/views/wallets-ui.php' ), '<script' ), 'wallets UI has no inline script' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), 'xorro-direct-wallet-payments-woocommerce-coins' ), 'admin coins slug renamed' );
$legacy_snake = 'chain' . '_checkout';
$legacy_kebab = 'chain' . '-' . 'checkout';
xdwp_assert( false === strpos( file_get_contents( $root . '/assets/js/blocks.js' ), $legacy_kebab ), 'blocks.js has no old slug' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/blocks.js' ), 'xorro-direct-wallet-payments-woocommerce' ), 'blocks.js uses new text domain' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/xorro-direct-wallet-payments-woocommerce.php' ), "XDWP_GATEWAY_ID', 'xdwp'" ), 'gateway id is xdwp' );
xdwp_assert( is_file( $root . '/includes/class-xdwp-gateway.php' ), 'gateway class file renamed' );
xdwp_assert( is_file( $root . '/assets/images/xdwp-icon.svg' ), 'default icon renamed' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-install.php' ), $legacy_snake ), 'install has no legacy keys' );
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), $legacy_snake ), 'order has no legacy keys' );
xdwp_assert( false === strpos( file_get_contents( $root . '/uninstall.php' ), $legacy_snake ), 'uninstall has no legacy keys' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function meta' ), 'order meta helper present' );

// No legacy identifiers outside releases/ and .git/
$legacy_scan_fail = 0;
$scan = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $scan as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	// Normalize to forward slashes so this scan behaves the same on Windows
	// (RecursiveDirectoryIterator yields backslash-separated paths there) as
	// on Unix — a prior version of this check silently never matched on
	// Windows and flagged this very file's own intentional needle-building
	// as a false positive.
	$path = str_replace( '\\', '/', $file->getPathname() );
	if ( false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/releases/' ) ) {
		continue;
	}
	$ext = strtolower( $file->getExtension() );
	if ( ! in_array( $ext, array( 'php', 'js', 'css', 'md', 'txt', 'sh', 'yml', 'svg' ), true ) ) {
		continue;
	}
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		continue;
	}
	// Allow this smoke file to build legacy needles via concatenation only.
	if ( false !== strpos( $path, '/tests/smoke-test.php' ) ) {
		continue;
	}
	if ( false !== strpos( $contents, $legacy_snake ) || false !== strpos( $contents, $legacy_kebab ) || false !== stripos( $contents, 'Chain Checkout' ) ) {
		echo '[FAIL] legacy identifier in ' . str_replace( $root . '/', '', $path ) . "\n";
		$legacy_scan_fail++;
	}
}
xdwp_assert( 0 === $legacy_scan_fail, 'no legacy identifiers in source (' . $legacy_scan_fail . ' files)' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), 'LAST_INSERT_ID' ), 'atomic amount seq' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), 'STALE_TTL' ), 'stale price cache TTL' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/checkout.js' ), 'quoteSeq' ), 'checkout quote request sequencing' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/blocks.js' ), 'xdwp_quote' ), 'blocks live quote' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/blocks.js' ), 'getPaymentMethodData' ), 'blocks uses getPaymentMethodData' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp.php' ), "did_action( 'woocommerce_blocks_loaded' )" ), 'blocks registration handles late bootstrap' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), 'checkout_quote' ), 'session checkout quote reservation' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), 'take_checkout_quote' ), 'order consumes checkout quote' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), 'checkout_quote' ), 'ajax uses reserved checkout quote' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'take_checkout_quote' ), 'assign_payment reuses checkout quote' );
// Release 1.7.0 features.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'detect_incoming' ), 'unconfirmed payment detection' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), '$confirmations_override' ), 'detection uses a confirmations override, not the live setting' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), "'detected'" ), 'status endpoint reports detection' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function renew_payment' ), 'expired order can be re-quoted' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function can_renew' ), 're-quote is gated' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), 'wc_ajax_xdwp_renew' ), 're-quote reachable when admin-ajax is cached' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function payable_for_total' ), 'per-coin order limits' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-settings.php' ), 'coin_limits' ), 'coin limits are sanitized' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/checkout.js' ), 'bindSearch' ), 'classic checkout coin search' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/blocks.js' ), 'shownCoins' ), 'block checkout coin search' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'xdwp-open-wallet' ), 'open in wallet app button' );
// Release 1.8.0 features.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function confirmations_for' ), 'confirmations resolved per coin' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function chain_confirmations' ), 'per-chain confirmation table' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'Xdwp_Coins::confirmations_for' ), 'verifier uses the coin confirmations' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function memo_kind' ), 'memo chains known' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), "_xdwp_memo" ), 'orders carry a reference' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'function memo_ok' ), 'transfers are checked against the reference' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'xdwp-memo' ), 'payment page shows the reference' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/emails/xdwp-payment-details.php' ), "details['memo']" ), 'emails show the reference' );
// Release 1.9.0 features.
xdwp_assert( file_exists( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'payments overview class present' );
xdwp_assert( file_exists( $root . '/includes/admin/views/payments-page.php' ), 'payments overview view present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'manage_woocommerce_page_wc-orders_columns' ), 'orders column works with HPOS' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'manage_edit-shop_order_columns' ), 'orders column works with the legacy table' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp.php' ), 'Xdwp_Payments_Admin::init' ), 'payments admin is booted' );
foreach ( array( 'xdwp_payment_window_minutes', 'xdwp_confirmations_required', 'xdwp_coin_allowed_for_total', 'xdwp_order_memo', 'xdwp_payment_uri' ) as $xdwp_hook ) {
	xdwp_assert( false !== strpos( $readme_md, $xdwp_hook ), 'readme documents ' . $xdwp_hook );
}
// Release 6: translations, tests, CI.
xdwp_assert( file_exists( $root . '/languages/xorro-direct-wallet-payments-woocommerce.pot' ), 'translation template shipped' );
// WordPress loads the bundled .mo files by itself from /languages (verified on 6.9), so
// calling load_plugin_textdomain would be redundant — an assertion below checks it is absent.
xdwp_assert( count( glob( $root . '/languages/*.mo' ) ) > 0, 'compiled translations shipped' );
xdwp_assert( file_exists( $root . '/tests/matching-tests.php' ), 'payment-matching tests present' );
xdwp_assert( file_exists( $root . '/.github/workflows/ci.yml' ), 'CI workflow present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function explorer_tx_url' ), 'explorer links available' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-privacy.php' ), '_xdwp_memo' ), 'privacy tools cover the reference' );
// Release 1.10.0 features.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), 'wc_ajax_xdwp_sent' ), '"I have sent it" reachable when admin-ajax is cached' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'xdwp-sent' ), 'payment page offers to check now' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/frontend.js' ), 'watchClosely' ), 'page watches closely after the customer says they paid' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'function export_csv' ), 'payments can be exported' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'function attention_count' ), 'menu count available' );
// Release 1.11.0: addresses from an extended public key.
xdwp_assert( is_readable( $root . '/includes/class-xdwp-hd.php' ), 'key derivation class present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-wallets.php' ), 'function derive_address' ), 'wallets can derive an address' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-settings.php' ), "Xdwp_Hd::is_valid" ), 'saved keys are validated' );
xdwp_assert( file_exists( $root . '/tests/hd-tests.php' ), 'derivation is checked against published vectors' );
// Nothing here may ever accept or store a private key.
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-hd.php' ), '0488ade4' ), 'private-key version bytes are not recognised' );
// Release 1.12.0: the Help screen and shipped translations.
xdwp_assert( file_exists( $root . '/includes/admin/views/help-page.php' ), 'help screen present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), 'render_help_page' ), 'help screen is registered' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/views/help-page.php' ), 'xdwp-help-devs' ), 'help covers the developer hooks' );
xdwp_assert( count( glob( $root . '/languages/*.mo' ) ) >= 10, 'translations shipped for the common locales' );
// Release 1.13.0: fixes from the security audit.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-hd.php' ), 'function address_encodings' ), 'addresses are written the way each coin writes them' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-hd.php' ), '3 !== $depth' ), 'a master key cannot be used as an account key' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function reports_depth' ), 'chains that report no depth are known' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' ), 'function csv_cell' ), 'exported cells cannot run as formulas' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), "'seen_txid', 'seen_at', 'sent_at'" ), 'a new attempt forgets the previous one' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function flag_attention' ), 'orders needing attention are marked, not searched for' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-wallets.php' ), 'function recycled_index' ), 'abandoned orders give their address back' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'function memo_taken' ), 'references are checked for collisions' );
// Release 1.14.0: setup checks and payment-page clarity.
xdwp_assert( is_readable( $root . '/includes/class-xdwp-selftest.php' ), 'setup checks present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-selftest.php' ), 'function store_checks' ), 'store-wide checks present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/class-xdwp-admin.php' ), 'woocommerce_system_status_report' ), 'state reported in WooCommerce Status' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'function last_http' ), 'lookup outcomes are reportable' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function network_label' ), 'networks named the way exchanges name them' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function wait_estimate' ), 'wait estimated per coin' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'xdwp-box__network' ), 'wrong-network warning on the payment page' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/admin/views/settings-page.php' ), 'width="22" height="22" loading="lazy"' ), 'coin icons load lazily' );
// Release 1.15.0: accessibility and mobile.
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'aria-live="polite"' ), 'status changes are announced' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'role="timer"' ), 'countdown uses the timer role' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), '<bdi>' ), 'addresses are isolated from surrounding text direction' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/templates/payment.php' ), 'id="xdwp-qr-plain"' ), 'a plain address code is offered' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/css/frontend.css' ), '.xdwp-coin-option:focus-within' ), 'the coin picker shows keyboard focus' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/css/frontend.css' ), 'direction: ltr' ), 'the QR is never mirrored' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/frontend.js' ), 'document.hidden' ), 'polling stops while the page is hidden' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/blocks.js' ), "role: 'radiogroup'" ), 'block checkout names the coin group' );
// The shortfall, not the whole amount, is what an underpaid order asks for.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), "\$amount = (string) Xdwp_Order::meta( \$order, 'remainder' );" ), 'underpaid orders quote the remainder' );
// Release 1.16.0: amounts people can actually send, and payments that vanish.
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function payable_decimals' ), 'quotes are rounded to what can be sent' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), 'function step_fiat_value' ), 'the cost of unique amounts is measured' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'function transfer_vanished' ), 'a dropped transfer is noticed' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-verifier.php' ), 'xdwp_payment_dropped' ), 'a dropped transfer is announced to extensions' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function set_flag' ), 'what happened to a payment is recorded beside its status' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'Xdwp_Rates::pegged_rate' ), 'a pegged coin is given a longer window' );
// Release 1.17.0: paying from a wallet in the browser.
xdwp_assert( is_readable( $root . '/assets/js/wallet.js' ), 'wallet payment script present' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/wallet.js' ), 'eip6963:requestProvider' ), 'wallets are discovered, not guessed at' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/wallet.js' ), 'TIP6963:requestProvider' ), 'TronLink is discovered the same way' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/assets/js/wallet.js' ), 'wallet_switchEthereumChain' ), 'the wallet is put on the right chain' );
// An approval prompt on a checkout is what a drainer looks like, so only transfer(address,
// uint256) — selector 0xa9059cbb — is ever built. approve(address,uint256) is 0x095ea7b3.
$xdwp_wallet_js = file_get_contents( $root . '/assets/js/wallet.js' );
$xdwp_coins_php = file_get_contents( $root . '/includes/class-xdwp-coins.php' );
xdwp_assert( false === strpos( $xdwp_wallet_js, '095ea7b3' ) && false === strpos( $xdwp_coins_php, '095ea7b3' ), 'no token approval is ever requested' );
xdwp_assert( false !== strpos( $xdwp_coins_php, '0xa9059cbb' ), 'token payments are a plain transfer' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-coins.php' ), 'function wallet_payment' ), 'wallet payment data is built server-side' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), 'function wallet_sent' ), 'a wallet transaction can be recorded' );
// A hash from a wallet proves nothing: the chain still decides.
xdwp_assert( false === strpos( file_get_contents( $root . '/includes/class-xdwp-ajax.php' ), "update_meta_data( '_xdwp_txid'" ), 'a wallet hash never marks an order paid' );
// Release 1.18: finding a payment again, and knowing how payments are going.
$xdwp_payments_admin = file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'function search_fields' ), 'orders can be found by transaction id or address' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'function handle_row_action' ), 'a payment can be re-checked or given longer' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function log_event' ), 'what happened to a payment is recorded' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'TIMELINE_MAX' ), 'the timeline cannot grow without limit' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'function report' ), 'payments are summarised over a period' );
// Reporting reads order meta directly, so every part of that query must be prepared, bounded,
// and counted once even when HPOS mirrors its meta into the post table.
xdwp_assert( false !== strpos( $xdwp_payments_admin, '$wpdb->prepare' ), 'the report query is prepared' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'REPORT_MAX' ), 'the report reads a bounded number of orders' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, "\$rows[ (int) \$row['order_id'] ]" ), 'an order is counted once across both storage layouts' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'set_transient( $key' ), 'the report is cached rather than recounted on every page load' );
xdwp_assert( false !== strpos( $xdwp_payments_admin, 'function median' ), 'the typical wait is a median, not a mean' );
$xdwp_payments_view = file_get_contents( $root . '/includes/admin/views/payments-page.php' );
xdwp_assert( false !== strpos( $xdwp_payments_view, 'xdwp-report' ), 'the report is on the Payments screen' );
xdwp_assert( false === strpos( $xdwp_payments_view, '<?php echo $report' ), 'report values are escaped before they are printed' );

// Release 1.19: refunds, alerts, and settings a shop can carry with it.
$xdwp_refunds = file_get_contents( $root . '/includes/class-xdwp-refunds.php' );
xdwp_assert( is_readable( $root . '/includes/class-xdwp-refunds.php' ), 'refunds present' );
xdwp_assert( false !== strpos( $xdwp_refunds, 'random_bytes' ), 'a refund link is unguessable' );
xdwp_assert( false !== strpos( $xdwp_refunds, 'hash_hmac' ) && false !== strpos( $xdwp_refunds, 'hash_equals' ), 'a refund token is stored hashed and compared in constant time' );
xdwp_assert( false !== strpos( $xdwp_refunds, 'is_plausible_address' ), 'a refund address is checked against the coin it is for' );
xdwp_assert( false !== strpos( $xdwp_refunds, 'MAX_ATTEMPTS' ), 'guessing at refund links is rate limited' );
// The plugin must never appear to move money by itself.
xdwp_assert( false === strpos( $xdwp_refunds, 'eth_sendTransaction' ) && false === strpos( $xdwp_refunds, 'sendTransaction' ), 'a refund is never sent by the plugin' );
xdwp_assert( is_readable( $root . '/templates/xdwp-refund-claim.php' ), 'the claim page is a template a theme can override' );

$xdwp_notify = file_get_contents( $root . '/includes/class-xdwp-notify.php' );
xdwp_assert( is_readable( $root . '/includes/class-xdwp-notify.php' ), 'alerts present' );
xdwp_assert( false !== strpos( $xdwp_notify, "hash_hmac( 'sha256'" ), 'webhooks are signed' );
xdwp_assert( false !== strpos( $xdwp_notify, 'X-Xdwp-Timestamp' ), 'and carry a timestamp a receiver can check for replay' );
xdwp_assert( false !== strpos( $xdwp_notify, 'wp_schedule_single_event' ), 'a slow endpoint never holds up a checkout' );
xdwp_assert( false !== strpos( $xdwp_notify, 'wp_safe_remote_post' ), 'alerts use the safe HTTP helper' );
xdwp_assert( false === strpos( $xdwp_notify, 'billing_email' ), 'a customer email address is never sent to a third party' );

$xdwp_backup = file_get_contents( $root . '/includes/class-xdwp-backup.php' );
xdwp_assert( is_readable( $root . '/includes/class-xdwp-backup.php' ), 'settings backup present' );
xdwp_assert( false !== strpos( $xdwp_backup, 'function secret_keys' ), 'secrets are named, so they can be held back' );
xdwp_assert( false !== strpos( $xdwp_backup, 'Xdwp_Settings::sanitize' ), 'a restored file goes through the same checks as the form' );
xdwp_assert( false !== strpos( $xdwp_backup, 'is_uploaded_file' ), 'only a real upload is read' );

$xdwp_coins_src = file_get_contents( $root . '/includes/class-xdwp-coins.php' );
xdwp_assert( false !== strpos( $xdwp_coins_src, 'function confirmations_for_order' ), 'confirmations can depend on what an order is worth' );
xdwp_assert( false !== strpos( $xdwp_coins_src, 'max( $base,' ), 'a high-value tier can only raise the number, never lower it' );
$xdwp_prices_src = file_get_contents( $root . '/includes/class-xdwp-prices.php' );
xdwp_assert( false !== strpos( $xdwp_prices_src, 'function adjusted_fiat' ), 'a coin can carry a discount or a surcharge' );
xdwp_assert( 1 === substr_count( $xdwp_prices_src, 'adjusted_fiat( $fiat_amount, $coin_id )' ), 'and it is applied in exactly one place' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-order.php' ), 'function apply_coin_adjustment' ), 'the order total says so too' );

// Casper and Starknet were removed in 1.19.1: neither has a payment-detection API that does
// not need a paid or registered key, so they could only ever be confirmed by hand.
foreach ( array( 'CSPR', 'STRK', 'XVG', 'ZIL' ) as $xdwp_gone ) {
	xdwp_assert( ! isset( $all[ $xdwp_gone ] ), $xdwp_gone . ' was withdrawn and is gone from the registry' );
}

// A shop that completes an order in WooCommerce itself never touches this plugin's status, so
// the payment box has to read the order's own status or it keeps asking a customer to pay for
// something already done.
$xdwp_order_src = file_get_contents( $root . '/includes/class-xdwp-order.php' );
xdwp_assert( false !== strpos( $xdwp_order_src, '$order->is_paid()' ), 'a settled order is never asked to pay again' );
xdwp_assert( false !== strpos( $xdwp_order_src, 'function render_settled_notice' ), 'and is told plainly that there is nothing to pay' );
xdwp_assert( false !== strpos( $xdwp_order_src, 'function dismiss_attention' ), 'an order can be taken off the "needs you" list' );
$xdwp_payment_tpl = file_get_contents( $root . '/templates/payment.php' );
xdwp_assert( strpos( $xdwp_payment_tpl, 'xdwp-box__row' ) < strpos( $xdwp_payment_tpl, 'xdwp-box__steps' ), 'the amount comes before the instructions' );
xdwp_assert( strpos( $xdwp_payment_tpl, 'xdwp-box__actions' ) < strpos( $xdwp_payment_tpl, 'xdwp-box__steps' ), 'and so do the buttons' );
xdwp_assert( false !== strpos( $xdwp_payment_tpl, "'paid', 'expired', 'cancelled'" ), 'a settled order shows no countdown' );

// Kaia reads through Kaiascan, which needs a key of the shop's own: the key must travel in a
// header, never a query string, so it cannot end up in an access log or a diagnostic URL.
$xdwp_verifier_src = file_get_contents( $root . '/includes/class-xdwp-verifier.php' );
xdwp_assert( false !== strpos( $xdwp_verifier_src, 'function check_kaia' ), 'Kaia can be checked on chain' );
// Single-quoted on purpose: in double quotes PHP would read $api_key as a variable of this
// script's own — undefined, so the needle silently shrank to "'Authorization' => 'Bearer ' . "
// and the assertion passed without ever checking what the key is concatenated to.
xdwp_assert( false !== strpos( $xdwp_verifier_src, '\'Authorization\' => \'Bearer \' . $api_key' ), 'the Kaiascan key is sent as a header, not in the URL' );
xdwp_assert( false === strpos( $xdwp_verifier_src, 'kaiascan.io/api/v1/accounts/%s/transactions?key=' ), 'and never as a query parameter' );
xdwp_assert( false !== strpos( $xdwp_verifier_src, 'function to_raw_units' ), 'decimal amounts are converted without a float' );
// Polkadot is payable but confirmed by hand: it must not claim automatic verification.
xdwp_assert( isset( $all['DOT'] ), 'Polkadot is offered again' );
xdwp_assert( ! Xdwp_Coins::supports_auto_verify( 'DOT' ), 'Polkadot is manual, not automatic' );

xdwp_assert( false !== strpos( $readme, '== External services ==' ), 'readme external services section present' );
xdwp_assert( false !== strpos( $readme, 'XRP Ledger public cluster' ), 'readme documents the XRP data source' );
xdwp_assert( false !== strpos( $readme, 'Subscan' ), 'readme documents Subscan' );
xdwp_assert( false !== strpos( $readme, 'Filfox' ), 'readme documents Filfox' );
xdwp_assert( false !== strpos( $readme, 'AlgoNode' ), 'readme documents AlgoNode' );
xdwp_assert( false !== strpos( $readme, 'Greymass' ), 'readme documents Greymass' );

// Release 1.19.6: WooCommerce honours meta_query in wc_get_orders() only under HPOS. On a shop
// still storing orders as posts it drops the filter without applying it, and the query answers a
// far wider question while still returning rows — so every meta filter goes through
// Xdwp_Order_Query, which puts it where the post store will actually read it.
xdwp_assert( is_readable( $root . '/includes/class-xdwp-order-query.php' ), 'the order-query wrapper is present' );
xdwp_assert( file_exists( $root . '/tests/order-query-tests.php' ), 'both order stores are covered by tests' );
$xdwp_direct_meta_query = array();
$xdwp_query_scan        = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $xdwp_query_scan as $xdwp_file ) {
	if ( ! $xdwp_file->isFile() || 'php' !== strtolower( $xdwp_file->getExtension() ) ) {
		continue;
	}
	// Forward slashes so the exclusions below match on Windows too.
	$xdwp_path = str_replace( '\\', '/', $xdwp_file->getPathname() );
	if ( false !== strpos( $xdwp_path, '/.git/' ) || false !== strpos( $xdwp_path, '/releases/' ) || false !== strpos( $xdwp_path, '/tests/' ) ) {
		continue;
	}
	if ( false !== strpos( $xdwp_path, 'includes/class-xdwp-order-query.php' ) ) {
		continue;
	}
	$xdwp_src = (string) file_get_contents( $xdwp_path );
	if ( false !== strpos( $xdwp_src, 'wc_get_orders(' ) && false !== strpos( $xdwp_src, "'meta_query'" ) ) {
		$xdwp_direct_meta_query[] = str_replace( $root . '/', '', $xdwp_path );
	}
}
xdwp_assert( array() === $xdwp_direct_meta_query, 'no meta_query is handed straight to wc_get_orders(): ' . implode( ', ', $xdwp_direct_meta_query ) );

// The payment email is read on the device the customer's wallet is on, where a QR is useless —
// nobody can scan their own screen. The deep link is the shortest route from that email to a paid
// order, and it must reach both the HTML and the plain-text version.
$xdwp_emails_src = file_get_contents( $root . '/includes/class-xdwp-emails.php' );
xdwp_assert( false !== strpos( $xdwp_emails_src, "'wallet_uri'" ), 'the payment email carries a wallet link' );
xdwp_assert( false !== strpos( $xdwp_emails_src, 'Xdwp_Coins::payment_uri(' ), 'and builds it the same way the payment page does' );
foreach ( array( 'templates/emails/xdwp-payment-details.php', 'templates/emails/plain/xdwp-payment-details.php' ) as $xdwp_email_tpl ) {
	// Single-quoted: in double quotes PHP would read $details as a variable of this script's own.
	xdwp_assert( false !== strpos( file_get_contents( $root . '/' . $xdwp_email_tpl ), '$details[\'wallet_uri\']' ), "the wallet link reaches {$xdwp_email_tpl}" );
}
// A wallet URI is not http, so esc_url() would strip it silently unless its scheme is allowed.
xdwp_assert(
	false !== strpos( file_get_contents( $root . '/templates/emails/xdwp-payment-details.php' ), "'bitcoin', 'ethereum'" ),
	'and its scheme survives escaping rather than being stripped'
);

// WooCommerce asks a gateway whether it is ready before offering the enable toggle, and links
// the transaction id on the order screen only if the gateway says where the link goes.
$xdwp_gateway_src = file_get_contents( $root . '/includes/class-xdwp-gateway.php' );
xdwp_assert( false !== strpos( $xdwp_gateway_src, 'function needs_setup' ), 'the gateway tells WooCommerce when it is not set up yet' );
xdwp_assert( false !== strpos( $xdwp_gateway_src, 'Xdwp_Coins::get_payable()' ), 'and decides that on whether any coin has somewhere to receive' );
xdwp_assert( false !== strpos( $xdwp_gateway_src, 'function get_transaction_url' ), 'the order screen can link a payment to its explorer' );
xdwp_assert( false !== strpos( $xdwp_gateway_src, 'Xdwp_Coins::explorer_tx_url' ), 'and builds that link through the checked helper' );

// Exporting the configuration in parts is what makes it usable between sites.
xdwp_assert( is_readable( $root . '/includes/class-xdwp-backup.php' ), 'settings backup is present' );
xdwp_assert( file_exists( $root . '/tests/backup-tests.php' ), 'and what each export carries is covered by tests' );
xdwp_assert( is_readable( $root . '/includes/admin/views/backup-ui.php' ), 'the backup controls are a shared partial' );
$xdwp_settings_view = file_get_contents( $root . '/includes/admin/views/settings-page.php' );
foreach ( array( 'general', 'wallets', 'prices' ) as $xdwp_backup_tab ) {
	// Alignment whitespace varies with the longest key, so it is not part of the match.
	xdwp_assert(
		1 === preg_match( '/\'' . $xdwp_backup_tab . '\'\s*=>\s*Xdwp_Backup::SCOPE_/', $xdwp_settings_view ),
		"export and import are offered on the {$xdwp_backup_tab} tab"
	);
}

// One readme ships, not two: readme.txt is what WordPress reads for the plugin's "View details"
// screen, and README.md is for GitHub. Both in wp-content/plugins would say the same things twice.
$xdwp_build_src = file_get_contents( $root . '/bin/build-zip.sh' );
xdwp_assert( false !== strpos( $xdwp_build_src, "--exclude='README.md'" ), 'the release ZIP leaves README.md out' );
xdwp_assert( false !== strpos( $xdwp_build_src, 'test ! -f "${STAGE}/${PLUGIN_SLUG}/README.md"' ), 'and the build fails if it ever creeps back in' );
xdwp_assert( false !== strpos( $xdwp_build_src, 'test -f "${STAGE}/${PLUGIN_SLUG}/readme.txt"' ), 'while readme.txt is still required to be there' );

// PHP 8.4 deprecated relying on fputcsv()'s default escape character. A deprecation printed
// during a download lands inside the file, so the export must always pass it explicitly.
$xdwp_payments_src = file_get_contents( $root . '/includes/admin/class-xdwp-payments-admin.php' );
// A header with more or fewer cells than the rows beneath it shifts every column silently —
// the amounts still look like amounts, just in the wrong place. Counted here so adding a column
// to one and forgetting the other fails the build instead of the merchant's accounts.
preg_match( '/fputcsv\(\s*\$out,\s*array\(\s*(.*?)\s*\),\s*\',\'/s', $xdwp_payments_src, $xdwp_csv_head );
preg_match( '/\$row\s*=\s*array\(\s*(.*?)\s*\);/s', $xdwp_payments_src, $xdwp_csv_row );
$xdwp_head_cells = isset( $xdwp_csv_head[1] ) ? preg_match_all( '/__\(/', $xdwp_csv_head[1] ) : 0;
$xdwp_row_cells  = 0;
if ( isset( $xdwp_csv_row[1] ) ) {
	foreach ( explode( "\n", $xdwp_csv_row[1] ) as $xdwp_cell_line ) {
		$xdwp_cell_line = trim( $xdwp_cell_line );
		if ( '' !== $xdwp_cell_line && 0 !== strpos( $xdwp_cell_line, '//' ) ) {
			++$xdwp_row_cells;
		}
	}
}
xdwp_assert(
	$xdwp_head_cells > 0 && $xdwp_head_cells === $xdwp_row_cells,
	sprintf( 'the payments export has as many headings as it has values (%d headings, %d values)', $xdwp_head_cells, $xdwp_row_cells )
);
// The columns an accountant needs, which operational columns alone cannot give.
foreach ( array( 'Rate (order currency per coin)', 'Value received (order currency)', 'Confirmed at' ) as $xdwp_acct_col ) {
	xdwp_assert( false !== strpos( $xdwp_payments_src, $xdwp_acct_col ), "the export carries \"{$xdwp_acct_col}\"" );
}
xdwp_assert( false !== strpos( $xdwp_order_src, "update_meta_data( '_xdwp_rate'" ), 'the rate an order was quoted at is recorded, not recovered later' );

// Counted rather than parsed: a real call always opens with the handle variable, and the
// separator/enclosure/escape triple below is written for no other reason.
preg_match_all( '/fputcsv\s*\(\s*\$/', $xdwp_payments_src, $xdwp_csv_calls );
preg_match_all( '/,\s*\',\'\s*,\s*\'"\'\s*,\s*\'\'\s*\)/', $xdwp_payments_src, $xdwp_csv_escaped );
xdwp_assert(
	count( $xdwp_csv_calls[0] ) > 0 && count( $xdwp_csv_calls[0] ) === count( $xdwp_csv_escaped[0] ),
	sprintf( 'every fputcsv() names its escape character (%d of %d)', count( $xdwp_csv_escaped[0] ), count( $xdwp_csv_calls[0] ) )
);

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s)\n";
	exit( 1 );
}
echo "ALL SMOKE TESTS PASSED\n";
exit( 0 );
