#!/usr/bin/env php
<?php
/**
 * Offline smoke tests for Chain Checkout (no WordPress bootstrap required).
 *
 * Run: php tests/smoke-test.php
 *
 * @package ChainCheckout
 */

$root = dirname( __DIR__ );
$fail = 0;

function chain_checkout_assert( $cond, $msg ) {
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
	$path = $file->getPathname();
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
	chain_checkout_assert( 0 === $code, 'php -l ' . str_replace( $root . '/', '', $path ) );
}

// --- Load coin catalog in isolation ---
define( 'ABSPATH', '/tmp/' );
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}
require_once $root . '/includes/class-chain-checkout-coins.php';

$all = Chain_Checkout_Coins::all();
chain_checkout_assert( count( $all ) >= 48, 'coin catalog size >= 48 (got ' . count( $all ) . ')' );

$required = array(
	'BTC', 'ETH', 'LTC', 'DOGE', 'ARB', 'OP', 'BNB', 'SOL', 'TRX', 'XMR', 'XRP', 'ATA', 'MATIC', 'TUSD', 'USDP', 'GUSD',
	'USDT_ETH', 'USDT_ARB', 'USDT_OP', 'USDT_BNB', 'USDT_SOL', 'USDT_TRX',
	'USDC_ETH', 'USDC_ARB', 'USDC_OP', 'USDC_BNB', 'USDC_SOL',
	'FTT', 'AVAX', 'LINK', 'DOT', 'CAKE', 'ATOM', 'EOS', 'ETC', 'ZIL', 'FIL', 'ALGO', 'HBAR', 'CRO', 'FTM', 'EGLD', 'NEAR', 'AXS', 'MANA', 'SAND', 'UNI', 'XLM',
);
foreach ( $required as $id ) {
	chain_checkout_assert( isset( $all[ $id ] ), "required coin present: {$id}" );
}

chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'BTC' ), 'BTC auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'ETH' ), 'ETH auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'BNB' ), 'BNB auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'USDT_ETH' ), 'USDT_ETH auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'FTM' ), 'FTM auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'CRO' ), 'CRO auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'ETC' ), 'ETC auto-verify' );
chain_checkout_assert( ! Chain_Checkout_Coins::supports_auto_verify( 'XMR' ), 'XMR is manual' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'ALGO' ), 'ALGO auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'HBAR' ), 'HBAR auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'NEAR' ), 'NEAR auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'ATOM' ), 'ATOM auto-verify' );
chain_checkout_assert( Chain_Checkout_Coins::supports_auto_verify( 'DOT' ), 'DOT auto-verify' );

$base = Chain_Checkout_Coins::to_base_units( '1.5', 6 );
chain_checkout_assert( '1500000' === $base, "EIP-681 base units 1.5@6 => {$base}" );

$btc_uri = Chain_Checkout_Coins::payment_uri( 'BTC', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', '0.01' );
chain_checkout_assert( 0 === strpos( $btc_uri, 'bitcoin:' ), "BTC BIP-21 scheme => {$btc_uri}" );
chain_checkout_assert( false !== strpos( $btc_uri, 'amount=0.01' ), "BTC amount => {$btc_uri}" );
chain_checkout_assert( false === strpos( $btc_uri, 'btc:' ), 'BTC must not use btc: scheme' );

$eth_uri = Chain_Checkout_Coins::payment_uri( 'ETH', '0xabcABC0000000000000000000000000000000001', '1.5' );
chain_checkout_assert( 0 === strpos( $eth_uri, 'ethereum:0xabcabc' ), "ETH EIP-681 => {$eth_uri}" );
chain_checkout_assert( false !== strpos( $eth_uri, '@1?' ), "ETH chain id 1 => {$eth_uri}" );
chain_checkout_assert( false !== strpos( $eth_uri, 'value=1500000000000000000' ), "ETH value wei => {$eth_uri}" );

$usdt_bnb = Chain_Checkout_Coins::payment_uri( 'USDT_BNB', '0xabcABC0000000000000000000000000000000001', '10' );
chain_checkout_assert( false !== strpos( $usdt_bnb, '@56/transfer' ), "USDT_BNB chain 56 => {$usdt_bnb}" );
chain_checkout_assert( false !== strpos( $usdt_bnb, 'uint256=' ), "USDT_BNB uint256 => {$usdt_bnb}" );

$sol_uri = Chain_Checkout_Coins::payment_uri( 'SOL', 'So11111111111111111111111111111111111111112', '2.5' );
chain_checkout_assert( 0 === strpos( $sol_uri, 'solana:' ), "SOL Solana Pay => {$sol_uri}" );

$usdt_sol = Chain_Checkout_Coins::payment_uri( 'USDT_SOL', 'So11111111111111111111111111111111111111112', '5' );
chain_checkout_assert( false !== strpos( $usdt_sol, 'spl-token=' ), "USDT_SOL spl-token => {$usdt_sol}" );

$usdt_trx = Chain_Checkout_Coins::payment_uri( 'USDT_TRX', 'TXYZabcdefghijklmnopqrstuvwxyz1234567', '1' );
chain_checkout_assert( $usdt_trx === 'TXYZabcdefghijklmnopqrstuvwxyz1234567', 'USDT_TRX bare address QR' );

// --- Verifier source checks ---
$verifier = file_get_contents( $root . '/includes/class-chain-checkout-verifier.php' );
chain_checkout_assert( false !== strpos( $verifier, 'api.etherscan.io/v2/api' ), 'Etherscan V2 endpoint present' );
chain_checkout_assert( false !== strpos( $verifier, 'mempool.space' ), 'mempool.space present' );
chain_checkout_assert( false !== strpos( $verifier, 'helius-rpc.com' ), 'Helius RPC present' );
chain_checkout_assert( false !== strpos( $verifier, 'TRON-PRO-API-KEY' ), 'TronGrid key header present' );
chain_checkout_assert( false !== strpos( $verifier, 'function match_band' ), 'verifier match_band helper' );
chain_checkout_assert( false !== strpos( $verifier, 'etherscan_confirmed' ), 'verifier confirmation check' );
chain_checkout_assert( false !== strpos( $verifier, 'finalized' ), 'solana finalized commitment' );

$order_php = file_get_contents( $root . '/includes/class-chain-checkout-order.php' );
chain_checkout_assert( false !== strpos( $order_php, 'expiry_grace_minutes' ), 'order expiry grace' );
chain_checkout_assert( false !== strpos( $order_php, "'failed'" ), 'expired orders fail not cancel' );
chain_checkout_assert( false !== strpos( $order_php, 'chain_checkout_status_' ), 'order-bound status nonce' );
chain_checkout_assert( false !== strpos( $order_php, 'REQUEST_METHOD' ), 'mark-paid POST only' );

$ajax = file_get_contents( $root . '/includes/class-chain-checkout-ajax.php' );
chain_checkout_assert( false !== strpos( $ajax, 'chain_checkout_status_' ), 'ajax order-bound nonce' );

// --- Headers ---
$main = file_get_contents( $root . '/chain-checkout.php' );
chain_checkout_assert( false !== strpos( $main, 'Version:           1.4.3' ), 'plugin version 1.4.3' );
chain_checkout_assert( false !== strpos( $main, 'Author:            xorro' ), 'author is xorro' );
chain_checkout_assert( false !== strpos( $main, 'Author URI:        https://github.com/x-o-r-r-o' ), 'author URI is GitHub' );
chain_checkout_assert( false === strpos( $main, 'Author URI:        https://wordpress.org/plugins/chain-checkout' ), 'author URI not same as plugin URI' );
chain_checkout_assert( false !== strpos( $main, 'Requires at least: 6.9' ), 'Requires WP 6.9+' );
chain_checkout_assert( false !== strpos( $main, 'WC requires at least: 10.0' ), 'Requires WC 10.0+' );
chain_checkout_assert( false !== strpos( $main, 'WC tested up to:   10.8' ), 'WC tested up to 10.8' );
chain_checkout_assert( false !== strpos( $main, 'custom_order_tables' ), 'HPOS compatibility declared' );
chain_checkout_assert( false !== strpos( $main, 'cart_checkout_blocks' ), 'Blocks compatibility declared' );

$branding = file_get_contents( $root . '/includes/class-chain-checkout-branding.php' );
chain_checkout_assert( false !== strpos( $branding, 'checkout_display' ), 'branding display mode' );
chain_checkout_assert( false !== strpos( $branding, 'checkout_icon_width' ), 'branding icon width' );
chain_checkout_assert( false !== strpos( $branding, 'get_icon_html' ), 'branding icon html' );

$gateway = file_get_contents( $root . '/includes/class-chain-checkout-gateway.php' );
chain_checkout_assert( false !== strpos( $gateway, 'function get_icon' ), 'gateway get_icon override' );
chain_checkout_assert( false !== strpos( $gateway, 'filter_gateway_title' ), 'gateway title filter' );

$frontend_css = file_get_contents( $root . '/assets/css/frontend.css' );
chain_checkout_assert( false !== strpos( $frontend_css, 'chain-checkout-gateway-icon' ), 'frontend icon CSS' );
chain_checkout_assert( false !== strpos( $frontend_css, 'chain-checkout-box' ), 'classic payment box CSS' );
chain_checkout_assert( false !== strpos( $frontend_css, 'chain-checkout-coin-option__icon' ), 'coin option img icon CSS' );
chain_checkout_assert( false === strpos( $frontend_css, 'chain-checkout-paybox' ), 'cryptoniq paybox CSS removed' );
$payment_tpl = file_get_contents( $root . '/templates/payment.php' );
chain_checkout_assert( false !== strpos( $payment_tpl, 'chain-checkout-box' ), 'payment template uses classic box' );
chain_checkout_assert( false === strpos( $payment_tpl, 'chain-checkout-paybox' ), 'payment template has no paybox markup' );
$frontend_js = file_get_contents( $root . '/assets/js/frontend.js' );
chain_checkout_assert( false !== strpos( $frontend_js, 'chain-checkout-timer' ), 'frontend timer hook' );
chain_checkout_assert( false === strpos( $frontend_js, 'paybox' ), 'frontend JS has no paybox refs' );
$admin_css = file_get_contents( $root . '/assets/css/admin.css' );
chain_checkout_assert( false !== strpos( $admin_css, 'chain-checkout-options-wrap' ), 'cryptoniq-style admin shell CSS' );
chain_checkout_assert( false !== strpos( $admin_css, 'cc-header' ), 'admin header class' );
chain_checkout_assert( is_dir( $root . '/assets/svg/coins' ), 'coin svg directory' );
chain_checkout_assert( is_file( $root . '/assets/svg/coins/xmr.svg' ), 'XMR icon present' );
chain_checkout_assert( is_file( $root . '/assets/svg/coins/link.svg' ), 'LINK icon present' );
chain_checkout_assert( is_file( $root . '/assets/svg/coins/hbar.svg' ), 'HBAR icon present' );
$coins_php = file_get_contents( $root . '/includes/class-chain-checkout-coins.php' );
chain_checkout_assert( false !== strpos( $coins_php, 'polygon-ecosystem-token' ), 'MATIC uses POL CoinGecko id' );
chain_checkout_assert( false === strpos( file_get_contents( $root . '/includes/class-chain-checkout-verifier.php' ), 'YourApiKeyToken' ), 'no placeholder Etherscan key' );

$blocks_js = file_get_contents( $root . '/assets/js/blocks.js' );
chain_checkout_assert( false !== strpos( $blocks_js, 'iconWidth' ), 'blocks icon width' );
chain_checkout_assert( false !== strpos( $blocks_js, "display === 'text'" ), 'blocks text-only mode' );

$readme_md = file_get_contents( $root . '/README.md' );
chain_checkout_assert( false !== strpos( $readme_md, 'Checkout branding' ), 'README.md branding section' );

$readme = file_get_contents( $root . '/readme.txt' );
chain_checkout_assert( false !== strpos( $readme, 'Tested up to: 7.0' ), 'readme Tested up to WP 7.0' );
chain_checkout_assert( false !== strpos( $readme, 'Stable tag: 1.4.3' ), 'readme stable 1.4.3' );

$readme = file_get_contents( $root . '/readme.txt' );
chain_checkout_assert( false !== strpos( $readme, '== External services ==' ), 'readme external services section' );
chain_checkout_assert( false !== strpos( $readme, '1.4.3' ), 'readme 1.4.3 changelog' );
$privacy = file_get_contents( $root . '/includes/class-chain-checkout-privacy.php' );
chain_checkout_assert( false !== strpos( $privacy, 'wp_add_privacy_policy_content' ), 'privacy policy content registered' );
chain_checkout_assert( is_file( $root . '/assets/js/qrcode.LICENSE.txt' ), 'qrcode license attribution' );
chain_checkout_assert( is_file( $root . '/includes/admin/index.php' ), 'admin index.php silence' );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s)\n";
	exit( 1 );
}
echo "ALL SMOKE TESTS PASSED\n";
exit( 0 );
