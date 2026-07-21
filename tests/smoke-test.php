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
	'FTT', 'AVAX', 'LINK', 'DOT', 'CAKE', 'ATOM', 'EOS', 'ETC', 'ZIL', 'FIL', 'ALGO', 'HBAR', 'CRO', 'FTM', 'EGLD', 'NEAR', 'AXS', 'MANA', 'SAND', 'UNI', 'XLM',
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
xdwp_assert( Xdwp_Coins::supports_auto_verify( 'DOT' ), 'DOT auto-verify' );

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
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), '$slots      = 499' ), 'unique dust has 499 slots' );

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
xdwp_assert( false !== strpos( $verifier, "! \$has_receipt || \$tx['receiptSuccess']" ), 'ZIL requires all present success flags' );

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
xdwp_assert( false !== $zil_pos, 'check_zil present' );
$zil_slice = false !== $zil_pos ? substr( $verifier, $zil_pos, 2500 ) : '';
xdwp_assert( false !== strpos( $zil_slice, 'raw_amount_in_band( $raw, 12,' ), 'ZIL raw_amount_in_band uses 12 near check_zil' );
// Helius RPC requires ?api-key= (documented); also send X-Api-Key.
xdwp_assert( false !== strpos( $verifier, 'api-key=' ), 'Helius uses documented api-key query' );
xdwp_assert( false !== strpos( $verifier, 'X-Api-Key' ), 'Helius also sends X-Api-Key header' );
xdwp_assert( false !== strpos( $verifier, 'tron_destination_matches' ), 'tron destination matcher present' );
xdwp_assert( false !== strpos( $verifier, 'tron_hex_to_base58' ), 'tron hex to base58 helper' );
xdwp_assert( false !== strpos( $verifier, 'base58_encode' ), 'base58_encode helper' );
xdwp_assert( false !== strpos( $verifier, "isset( \$tx['transaction_successful'] ) && \$tx['transaction_successful']" ), 'stellar requires explicit success' );
xdwp_assert( false !== strpos( $verifier, "isset( \$row['irreversible'] ) && \$row['irreversible']" ), 'eos requires irreversible' );
xdwp_assert( false !== strpos( $verifier, "isset( \$tx['receipt']['exitCode'] ) && 0 === (int) \$tx['receipt']['exitCode']" ), 'fil requires explicit exitCode 0' );
xdwp_assert( false !== strpos( $verifier, "empty( \$tx['success'] )" ), 'check_dot rejects empty success' );
xdwp_assert( false === strpos( $verifier, 'tokenDecimal' ), 'tokenDecimal not preferred over configured decimals' );
xdwp_assert( false !== strpos( $verifier, '$dec = (int) $decimals' ), 'raw_amount uses configured decimals near contractAddress' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-updater.php' ), 'xdwp_pkg_sha_' ), 'updater caches package sha256' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/includes/class-xdwp-prices.php' ), '$allow_stale' ), 'prices support fail-closed fresh rates' );
xdwp_assert( false !== strpos( file_get_contents( $root . '/uninstall.php' ), 'xdwp_amt_' ), 'uninstall clears amount slots' );

// --- Headers ---
$main = file_get_contents( $root . '/xorro-direct-wallet-payments-woocommerce.php' );
xdwp_assert( false !== strpos( $main, 'Version:           1.5.15' ), 'plugin version 1.5.15' );
xdwp_assert( false !== strpos( $main, "XDWP_VERSION', '1.5.15'" ), 'XDWP_VERSION 1.5.15' );
xdwp_assert( false !== strpos( $main, 'Author:            xorro' ), 'author is xorro' );
xdwp_assert( false !== strpos( $main, 'Author URI:        https://github.com/x-o-r-r-o' ), 'author URI is GitHub profile' );
xdwp_assert( false === strpos( $main, 'Author URI:        https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce' ), 'author URI not the plugin repo' );
xdwp_assert( false === strpos( $main, 'Author URI:        https://wordpress.org/plugins/xdwp' ), 'author URI not same as old plugin URI' );
xdwp_assert( false !== strpos( $main, 'Requires at least: 6.9' ), 'Requires WP 6.9+' );
xdwp_assert( false !== strpos( $main, 'WC requires at least: 10.0' ), 'Requires WC 10.0+' );
xdwp_assert( false !== strpos( $main, 'WC tested up to:   10.8' ), 'WC tested up to 10.8' );
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
xdwp_assert( false !== strpos( $readme, 'Tested up to: 7.0' ), 'readme Tested up to WP 7.0' );
xdwp_assert( false !== strpos( $readme, 'Stable tag: 1.5.15' ), 'readme stable 1.5.15' );

$readme = file_get_contents( $root . '/readme.txt' );
xdwp_assert( false !== strpos( $readme, '== External services ==' ), 'readme external services section' );
xdwp_assert( false !== strpos( $readme, '1.5.15' ), 'readme 1.5.15 changelog' );
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
	$path = $file->getPathname();
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
xdwp_assert( false !== strpos( $readme, '== External services ==' ), 'readme external services section present' );
xdwp_assert( false !== strpos( $readme, 'XRPSCan' ), 'readme documents XRPSCan' );
xdwp_assert( false !== strpos( $readme, 'Subscan' ), 'readme documents Subscan' );
xdwp_assert( false !== strpos( $readme, 'Filfox' ), 'readme documents Filfox' );
xdwp_assert( false !== strpos( $readme, 'AlgoNode' ), 'readme documents AlgoNode' );
xdwp_assert( false !== strpos( $readme, 'Greymass' ), 'readme documents Greymass' );


echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s)\n";
	exit( 1 );
}
echo "ALL SMOKE TESTS PASSED\n";
exit( 0 );
