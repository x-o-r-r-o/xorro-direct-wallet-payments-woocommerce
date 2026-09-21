#!/usr/bin/env php
<?php
/**
 * Rehearsing on a test network.
 *
 * Two mistakes here cost real money, and they point in opposite directions. Leaving test mode on
 * means a shop quietly takes orders nobody can pay for. Letting a mainnet address through while
 * test mode is on — or reading mainnet while quoting a test address — means a customer's real
 * coins go to a chain nobody is watching. Both are checked below.
 *
 * Run: php tests/testmode-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-testmode.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
require_once XDWP_PATH . 'includes/class-xdwp-hd.php';
require_once XDWP_PATH . 'includes/class-xdwp-wallets.php';

$pass = 0;
$fail = 0;

/**
 * Assert.
 *
 * @param string $label Assertion.
 * @param bool   $cond  Result.
 * @param string $info  Detail on failure.
 */
function t( $label, $cond, $info = '' ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}
	++$fail;
	echo "[FAIL] {$label}" . ( '' !== $info ? " — {$info}" : '' ) . "\n";
}

/**
 * Turn test mode on or off.
 *
 * @param bool  $on      Whether to switch it on.
 * @param array $enabled Coins the shop has ticked.
 */
function mode( $on, array $enabled = array( 'BTC', 'ETH', 'TRX', 'LTC', 'XMR' ) ) {
	update_option(
		'xdwp_settings',
		array(
			'test_mode'     => $on ? 'yes' : 'no',
			'enabled_coins' => $enabled,
			'wallets'       => array(
				'BTC' => array( 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ),
				'ETH' => array( '0x00000000000000000000000000000000000000e1' ),
				'TRX' => array( 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' ),
				'LTC' => array( 'ltc1qgdjqv0av3q56jvd82tkdjpy7gdp9ut8tlqmgrpmv24sq90ecnvqqjwvw97' ),
				'XMR' => array( str_repeat( '4', 95 ) ),
			),
		)
	);
}

// ---------------------------------------------------------------- off by default

mode( false );
t( 'test mode is off unless asked for', ! Xdwp_Testmode::active() );

// ---------------------------------------------------------------- which chains are offered

$networks = Xdwp_Testmode::networks();
// Counted through offered(), not networks(): the map is keyed by verifier and one chain can
// answer to more than one key, so counting keys would drift from the number of real networks.
t( 'three test networks are offered', 3 === count( Xdwp_Testmode::offered() ), wp_json_encode( array_keys( Xdwp_Testmode::offered() ) ) );
t( 'Bitcoin has one', Xdwp_Testmode::supports( 'btc' ) );
t( 'Ethereum has one', Xdwp_Testmode::supports( 'eth' ) );
t( 'TRON has one, under the verifier its native coin really uses', Xdwp_Testmode::supports( 'trx' ) );
t( 'Monero does not', ! Xdwp_Testmode::supports( 'xmr' ) );
t( 'nor does a chain that does not exist', ! Xdwp_Testmode::supports( 'nonsense' ) );
t( 'nor does a non-string', ! Xdwp_Testmode::supports( array( 'btc' ) ) );

foreach ( $networks as $key => $net ) {
	t( "the {$key} test network is named", '' !== $net['label'] );
	t( "the {$key} test network says where to get coins", 0 === strpos( $net['faucet'], 'https://' ), $net['faucet'] );
}

// ---------------------------------------------------------------- addresses

// A real Bitcoin address must not be accepted while testing: it is on a chain nobody is reading,
// so a customer's payment to it would never be seen.
mode( true );
t( 'a mainnet Bitcoin address is refused in test mode', ! Xdwp_Wallets::is_plausible_address( 'BTC', 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ) );
t( 'a testnet bech32 address is accepted', Xdwp_Wallets::is_plausible_address( 'BTC', 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kxpjzsx' ) );
t( 'a testnet legacy address is accepted', Xdwp_Wallets::is_plausible_address( 'BTC', 'mipcBbFg9gMiCh81Kj8tqqdgoZub1ZJRfn' ) );
t( 'a testnet p2sh address is accepted', Xdwp_Wallets::is_plausible_address( 'BTC', '2N2JD6wb56AfK4tfmM6PwdVmoYk2dCKf4Br' ) );
t( 'rubbish is still rubbish', ! Xdwp_Wallets::is_plausible_address( 'BTC', 'not-an-address' ) );

// Ethereum and TRON write test addresses exactly as they write real ones.
t( 'an Ethereum address is accepted in test mode', Xdwp_Wallets::is_plausible_address( 'ETH', '0x00000000000000000000000000000000000000e1' ) );
t( 'a TRON address is accepted in test mode', Xdwp_Wallets::is_plausible_address( 'TRX', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' ) );

// And the other way round: with test mode off, a testnet address must not be saved as if real.
mode( false );
t( 'a testnet address is refused when not testing', ! Xdwp_Wallets::is_plausible_address( 'BTC', 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kxpjzsx' ) );
t( 'and the real one is accepted again', Xdwp_Wallets::is_plausible_address( 'BTC', 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ) );

// ---------------------------------------------------------------- which coins reach checkout

mode( false );
$live = array_keys( Xdwp_Coins::get_payable() );
t( 'every configured coin is payable normally', in_array( 'LTC', $live, true ) && in_array( 'XMR', $live, true ), implode( ',', $live ) );

mode( true );
$testing = array_keys( Xdwp_Coins::get_payable() );
t( 'Bitcoin is payable while testing', in_array( 'BTC', $testing, true ), implode( ',', $testing ) );
// A coin with no test network must disappear rather than take a payment nobody is watching for.
t( 'a coin with no test network is hidden', ! in_array( 'LTC', $testing, true ), implode( ',', $testing ) );
t( 'and so is a manual one', ! in_array( 'XMR', $testing, true ), implode( ',', $testing ) );

// Tokens are left out: a token has a different contract on every test network, and guessing one
// would send a customer's transfer to an address that means nothing there.
$token_in_test = array();
foreach ( Xdwp_Testmode::coin_ids() as $id ) {
	$coin = Xdwp_Coins::get( $id );
	if ( $coin && isset( $coin['type'] ) && 'native' !== $coin['type'] ) {
		$token_in_test[] = $id;
	}
}
t( 'only native coins are offered in test mode', array() === $token_in_test, implode( ',', $token_in_test ) );

// ---------------------------------------------------------------- the keys match real coins

// The bug this exists to prevent: supports() was asserted against the network map's own keys,
// which tests the map against itself. TRON was listed as 'tron', the verifier its TRC-20 tokens
// use — native TRX uses 'trx' — so the TRON test network was never reachable by any coin that
// could actually be offered, and every assertion still passed.
$networks_by_label = array();
foreach ( Xdwp_Testmode::networks() as $key => $net ) {
	$networks_by_label[ $net['label'] ][] = $key;
}
$unreachable = array();
foreach ( $networks_by_label as $label => $keys ) {
	$reachable = false;
	foreach ( Xdwp_Coins::all() as $coin_id => $coin ) {
		$verifier = isset( $coin['verifier'] ) ? (string) $coin['verifier'] : '';
		$native   = isset( $coin['type'] ) && 'native' === $coin['type'];
		if ( $native && in_array( $verifier, $keys, true ) ) {
			$reachable = true;
			break;
		}
	}
	if ( ! $reachable ) {
		$unreachable[] = $label;
	}
}
t( 'every test network is reachable by a native coin', array() === $unreachable, implode( ', ', $unreachable ) );

// And each offered network must produce at least one payable coin once test mode is on.
mode( true, array( 'BTC', 'ETH', 'TRX' ) );
$testing_ids = Xdwp_Testmode::coin_ids();
foreach ( array( 'BTC', 'ETH', 'TRX' ) as $expect ) {
	t( "{$expect} is offered in test mode", in_array( $expect, $testing_ids, true ), implode( ',', $testing_ids ) );
}
t( 'the offered list names each network once', 3 === count( Xdwp_Testmode::offered() ), wp_json_encode( array_keys( Xdwp_Testmode::offered() ) ) );

// The reader must know the same keys the network map does, or a test-mode order is checked
// against nothing and looks exactly like an unpaid one.
$verifier_src = file_get_contents( XDWP_PATH . 'includes/class-xdwp-verifier.php' );
$router = substr( $verifier_src, strpos( $verifier_src, 'function find_payment_on_testnet' ), 1400 );
foreach ( array_keys( Xdwp_Testmode::networks() ) as $key ) {
	t( "the testnet reader handles '{$key}'", false !== strpos( $router, "case '" . $key . "':" ), $key );
}
mode( false );

// ---------------------------------------------------------------- it is never quiet about itself

t( 'test mode has a sentence to show', strlen( Xdwp_Testmode::notice() ) > 60 );
t( 'and that sentence says no real money can arrive', false !== stripos( Xdwp_Testmode::notice(), 'no real money' ), Xdwp_Testmode::notice() );
t( 'and says where to turn it off', false !== stripos( Xdwp_Testmode::notice(), 'General' ), Xdwp_Testmode::notice() );

$admin = file_get_contents( XDWP_PATH . 'includes/admin/class-xdwp-admin.php' );
t( 'every admin screen says so', false !== strpos( $admin, "function test_mode_notice" ) );
// A dismissible banner is one a merchant dismisses and then forgets.
t( 'and the banner cannot be dismissed', false === strpos( $admin, 'is-dismissible' ) || false === strpos( $admin, 'Xdwp_Testmode::notice()' ) || false === strpos( $admin, 'notice-warning is-dismissible' ) );

$selftest = file_get_contents( XDWP_PATH . 'includes/class-xdwp-selftest.php' );
t( 'the readiness check refuses to call a test shop ready', false !== strpos( $selftest, "'testmode'" ) );

$template = file_get_contents( XDWP_PATH . 'templates/payment.php' );
t( 'the payment page warns before anyone sends anything', false !== strpos( $template, 'xdwp-box__network--test' ) );
t( 'and says real coins sent there are lost', false !== strpos( $template, 'Real coins sent here are lost' ) );
t( 'and shows the test network in the Network field', false !== strpos( $template, 'Xdwp_Testmode::network_label' ) );

// ---------------------------------------------------------------- the chain that is read

$verifier = file_get_contents( XDWP_PATH . 'includes/class-xdwp-verifier.php' );
t( 'test mode is decided before any mainnet lookup', false !== strpos( $verifier, 'Xdwp_Testmode::active()' ) );
t( 'a chain with no test network finds nothing rather than reading mainnet', false !== strpos( $verifier, 'if ( ! Xdwp_Testmode::supports( $verifier ) ) {' ) );
t( 'Bitcoin is read on testnet', false !== strpos( $verifier, 'blockstream.info/testnet/api' ) );
t( 'Ethereum is read on Sepolia', false !== strpos( $verifier, '11155111' ) );
t( 'TRON is read on Nile', false !== strpos( $verifier, 'nile.trongrid.io' ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL TEST-MODE TESTS PASSED ({$pass})\n";
exit( 0 );
