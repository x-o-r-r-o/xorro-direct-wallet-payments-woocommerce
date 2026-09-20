#!/usr/bin/env php
<?php
/**
 * Payment-matching tests against recorded explorer responses. No WordPress, no database.
 *
 * Run: php tests/matching-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
require_once XDWP_PATH . 'includes/class-xdwp-rates.php';
require_once XDWP_PATH . 'includes/class-xdwp-prices.php';
require_once XDWP_PATH . 'includes/class-xdwp-verifier.php';

$pass = 0;
$fail = 0;

/**
 * @param string $label Assertion.
 * @param bool   $cond  Result.
 * @param string $info  Extra detail on failure.
 */
function t( $label, $cond, $info = '' ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}
	++$fail;
	echo "[FAIL] {$label}" . ( '' !== $info ? "  ({$info})" : '' ) . "\n";
}

/**
 * @param array $settings Plugin settings for this case.
 */
function settings( array $settings ) {
	update_option( 'xdwp_settings', array_merge( array( 'min_confirmations' => 1 ), $settings ) );
	$GLOBALS['xdwp_stub']['transients'] = array();
	// The chain tip is cached for half a minute inside one request; each case starts fresh.
	$cache = new ReflectionProperty( 'Xdwp_Verifier', 'tip_cache' );
	$cache->setAccessible( true );
	$cache->setValue( null, array() );
}

// ---------------------------------------------------------------- exact amounts

// match_band() is internal, so reach it the way the tests need to without loosening the class.
$band_of = function ( $amount, $coin ) {
	$method = new ReflectionMethod( 'Xdwp_Verifier', 'match_band' );
	$method->setAccessible( true );
	return $method->invoke( null, $amount, $coin );
};

$band = $band_of( '0.125', Xdwp_Coins::get( 'BTC' ) );
t( 'match band is a range around the amount', isset( $band['min'], $band['max'] ) && $band['min'] <= '0.125' && $band['max'] >= '0.125', wp_json_encode( $band ) );

// An 18-decimal coin with a large amount used to collapse its band when floats were used.
$eth_band = $band_of( '12.345678901234567', Xdwp_Coins::get( 'ETH' ) );
t( 'large 18-decimal amounts keep a usable band', $eth_band['min'] !== $eth_band['max'], wp_json_encode( $eth_band ) );

// ---------------------------------------------------------------- Bitcoin

settings( array( 'min_confirmations' => 1 ) );
xdwp_stub_http(
	array(
		'mempool.space/api/blocks/tip/height'  => '870000',
		'mempool.space/api/address'            => xdwp_fixture( 'btc-mempool-address-txs' ),
	)
);
$btc  = Xdwp_Coins::get( 'BTC' );
$addr = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';
$hit  = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.124', '0.126', time() - 3600 );
t( 'Bitcoin: the matching transfer is found', $hit && '6f8a1c2b4d5e7f90a1b2c3d4e5f60718293a4b5c6d7e8f9012a3b4c5d6e7f801' === $hit['txid'], wp_json_encode( $hit ) );
t( 'Bitcoin: the amount credited is what arrived', $hit && '0.125' === $hit['amount'], $hit ? $hit['amount'] : '' );

$hit = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.999', '1.0', time() - 3600 );
t( 'Bitcoin: an unconfirmed transfer is never credited', false === $hit, wp_json_encode( $hit ) );

$hit = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.124', '0.126', time() + 600 );
t( 'Bitcoin: a transfer older than the order is ignored', false === $hit );

// Depth: the newest block is only 1 deep, so asking for 10 confirmations must not match.
settings( array( 'min_confirmations' => 10, 'recommended_confirmations' => 'no' ) );
xdwp_stub_http(
	array(
		'mempool.space/api/blocks/tip/height' => '870000',
		'mempool.space/api/address'           => xdwp_fixture( 'btc-mempool-address-txs' ),
	)
);
$hit = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.069', '0.071', time() - 3600 );
t( 'Bitcoin: a shallow block does not satisfy 10 confirmations', false === $hit, wp_json_encode( $hit ) );

// With the tip unavailable the check must fail closed rather than assume depth.
xdwp_stub_http( array( 'mempool.space/api/address' => xdwp_fixture( 'btc-mempool-address-txs' ) ) );
settings( array( 'min_confirmations' => 2, 'recommended_confirmations' => 'no' ) );
$hit = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.124', '0.126', time() - 3600 );
t( 'Bitcoin: no chain tip means no match', false === $hit, wp_json_encode( $hit ) );

// ---------------------------------------------------------------- TRON

settings( array( 'min_confirmations' => 1, 'recommended_confirmations' => 'yes' ) );
xdwp_stub_http( array( 'api.trongrid.io' => xdwp_fixture( 'tron-transactions' ) ) );
$trx  = Xdwp_Coins::get( 'TRX' );
$taddr = 'TNXoiAJ3dct8Fjg4M9fkLFh9S2v9TXc32G';
$hit  = Xdwp_Verifier::find_payment_detailed( $trx, $taddr, '27.937946', '27.937948', time() - 3600 );
t( 'TRON: a well-confirmed transfer is credited', $hit && 'flyvz8qlshhtmlnwqkyk8muwhdhamb7geh43kz0baczpxw3uksxea6upzgfrvlqi' === $hit['txid'], wp_json_encode( $hit ) );

// The second transfer in the fixture has 3 confirmations; TRON asks for 20.
$hit = Xdwp_Verifier::find_payment_detailed( $trx, $taddr, '49.9', '50.1', time() - 3600 );
t( 'TRON: a barely-confirmed transfer waits', false === $hit, wp_json_encode( $hit ) );

// ---------------------------------------------------------------- XRP destination tags

settings( array( 'min_confirmations' => 1 ) );
xdwp_stub_http( array( 'xrplcluster.com' => xdwp_fixture( 'xrp-payments' ) ) );
$xrp   = Xdwp_Coins::get( 'XRP' );
$xaddr = 'rMdG3ju8pgyVh29ELPWaDuA74CpWW6Fxns';
$hit   = Xdwp_Verifier::find_payment_detailed( $xrp, $xaddr, '6.737658', '6.73766', time() - 3600, '3927730452' );
t( 'XRP: the transfer with this order\'s tag is credited', $hit && 'F1B2C3D4E5F60718293A4B5C6D7E8F9012A3B4C5D6E7F8091A2B3C4D5E6F7081' === $hit['txid'], wp_json_encode( $hit ) );
t( 'XRP: a tagged match is marked as certain', $hit && ! empty( $hit['referenced'] ) );

$hit = Xdwp_Verifier::find_payment_detailed( $xrp, $xaddr, '6.737658', '6.73766', time() - 3600, '55555' );
t( 'XRP: another order\'s tag is never credited here', false === $hit, wp_json_encode( $hit ) );

$hit = Xdwp_Verifier::find_payment_detailed( $xrp, $xaddr, '6.737658', '6.73766', time() - 3600 );
t( 'XRP: an order with no tag still matches on amount', $hit && empty( $hit['referenced'] ), wp_json_encode( $hit ) );

// ---------------------------------------------------------------- Stellar memos

xdwp_stub_http( array( 'horizon.stellar.org' => xdwp_fixture( 'stellar-payments' ) ) );
$xlm   = Xdwp_Coins::get( 'XLM' );
$saddr = 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN';
$hit   = Xdwp_Verifier::find_payment_detailed( $xlm, $saddr, '42.1234566', '42.1234568', time() - 3600, 'XDWP-AB12CD34' );
t( 'Stellar: the payment carrying this memo is credited', $hit && ! empty( $hit['referenced'] ), wp_json_encode( $hit ) );

$hit = Xdwp_Verifier::find_payment_detailed( $xlm, $saddr, '42.1234566', '42.1234568', time() - 3600, 'XDWP-ZZ99ZZ99' );
t( 'Stellar: a payment for another order is left alone', false === $hit, wp_json_encode( $hit ) );

// The memo comparison must not be case-sensitive — wallets uppercase freely.
$hit = Xdwp_Verifier::find_payment_detailed( $xlm, $saddr, '42.1234566', '42.1234568', time() - 3600, 'xdwp-ab12cd34' );
t( 'Stellar: memo matching ignores capitalisation', $hit && ! empty( $hit['referenced'] ) );

// ---------------------------------------------------------------- unreachable chain

xdwp_stub_http( array() );
$hit = Xdwp_Verifier::find_payment_detailed( $btc, $addr, '0.124', '0.126', time() - 3600 );
t( 'an unreachable explorer never credits a payment', false === $hit );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL MATCHING TESTS PASSED ({$pass})\n";
exit( 0 );
