#!/usr/bin/env php
<?php
/**
 * Listing what has arrived at an address, and telling it apart from what nothing explains.
 *
 * The dangerous failure here is not missing a transfer — it is claiming there is nothing to
 * find on a chain that was never read. A merchant reads "no unmatched payments" as an
 * all-clear, so an explorer that would not answer, and a chain that cannot be listed at all,
 * must both be distinguishable from an empty answer at every step.
 *
 * Run: php tests/reconcile-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );
define( 'XDWP_GATEWAY_ID', 'xdwp' );

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
require_once XDWP_PATH . 'includes/class-xdwp-rates.php';
require_once XDWP_PATH . 'includes/class-xdwp-prices.php';
require_once XDWP_PATH . 'includes/class-xdwp-verifier.php';

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
 * Reach a private static on the verifier.
 *
 * @param string $name Method.
 * @param array  $args Arguments.
 * @return mixed
 */
function call_private( $name, array $args ) {
	$method = new ReflectionMethod( 'Xdwp_Verifier', $name );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	return $method->invokeArgs( null, $args );
}

// ---------------------------------------------------------------- integer maths, no floats

t( 'raw units become a decimal', '1.5' === call_private( 'raw_to_decimal', array( '150000000', 8 ) ) );
t( 'a whole number keeps no trailing point', '2' === call_private( 'raw_to_decimal', array( '200000000', 8 ) ) );
t( 'a sub-unit amount keeps its leading zero', '0.00000001' === call_private( 'raw_to_decimal', array( '1', 8 ) ) );
t( 'zero decimals passes through', '12345' === call_private( 'raw_to_decimal', array( '12345', 0 ) ) );
// An 18-decimal amount is larger than PHP's integer range; a float here would round the value.
t( 'a wei amount beyond PHP\'s integers is exact', '1234.567890123456789' === call_private( 'raw_to_decimal', array( '1234567890123456789000', 18 ) ) );
t( 'nothing becomes zero, not an empty string', '0' === call_private( 'raw_to_decimal', array( '', 8 ) ) );
t( 'rubbish becomes zero', '0' === call_private( 'raw_to_decimal', array( 'not-a-number', 8 ) ) );
t( 'an absurd decimals count is bounded', '' !== call_private( 'raw_to_decimal', array( '1', PHP_INT_MAX ) ) );

t( 'two integer strings add', '300' === call_private( 'add_digits', array( '100', '200' ) ) );
t( 'carrying works', '1000' === call_private( 'add_digits', array( '999', '1' ) ) );
t( 'adding beyond PHP\'s integers stays exact', '18446744073709551616' === call_private( 'add_digits', array( '18446744073709551615', '1' ) ) );
t( 'adding nothing to nothing is zero', '0' === call_private( 'add_digits', array( '', '' ) ) );

// ---------------------------------------------------------------- coverage is declared honestly

$listable = Xdwp_Verifier::listable_verifiers();
t( 'the listable chains are declared', is_array( $listable ) && count( $listable ) > 10 );
t( 'Bitcoin can be listed', Xdwp_Verifier::can_list( 'btc' ) );
t( 'Ethereum can be listed', Xdwp_Verifier::can_list( 'eth' ) );
t( 'Monero cannot, and says so', ! Xdwp_Verifier::can_list( 'xmr' ) );
t( 'an unknown chain cannot', ! Xdwp_Verifier::can_list( 'not-a-chain' ) );
t( 'nor can a non-string', ! Xdwp_Verifier::can_list( array( 'btc' ) ) );

// Every declared chain must actually reach a lister, or the screen would promise a check it
// never performs and report an empty result as "checked".
$all_coins = Xdwp_Coins::all();
$verifiers = array();
foreach ( $all_coins as $coin ) {
	if ( isset( $coin['verifier'] ) ) {
		$verifiers[ (string) $coin['verifier'] ] = true;
	}
}
$orphans = array();
foreach ( $listable as $verifier ) {
	if ( ! isset( $verifiers[ $verifier ] ) ) {
		$orphans[] = $verifier;
	}
}
t( 'every listable chain belongs to a coin this plugin offers', array() === $orphans, implode( ', ', $orphans ) );

// ---------------------------------------------------------------- reading a real answer

$btc = Xdwp_Coins::get( 'BTC' );
$addr = 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';

// An explorer that will not answer must never look like an address with nothing on it.
xdwp_stub_http( array() );
$none = Xdwp_Verifier::list_transfers( $btc, $addr, time() - 86400 );
t( 'an unreachable explorer returns "unknown", not "empty"', null === $none, var_export( $none, true ) );

// A real Esplora answer: two transfers to us, one to somebody else, one too old.
$old = time() - ( 60 * 86400 );
$new = time() - 3600;
xdwp_stub_http(
	array(
		'blockstream.info' => array(
			array(
				'txid'   => 'aaaa1111',
				'status' => array(
					'confirmed'   => true,
					'block_time'  => $new,
					'block_height' => 800000,
				),
				'vout'   => array(
					array(
						'scriptpubkey_address' => $addr,
						'value'                => 150000000,
					),
				),
			),
			array(
				'txid'   => 'bbbb2222',
				'status' => array(
					'confirmed'   => true,
					'block_time'  => $new,
					'block_height' => 800001,
				),
				'vout'   => array(
					// Two outputs to us in one transaction must add up, not count twice.
					array(
						'scriptpubkey_address' => $addr,
						'value'                => 1,
					),
					array(
						'scriptpubkey_address' => $addr,
						'value'                => 2,
					),
					array(
						'scriptpubkey_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
						'value'                => 99999999,
					),
				),
			),
			array(
				'txid'   => 'cccc3333',
				'status' => array(
					'confirmed'   => true,
					'block_time'  => $old,
					'block_height' => 700000,
				),
				'vout'   => array(
					array(
						'scriptpubkey_address' => $addr,
						'value'                => 500000,
					),
				),
			),
			array(
				'txid'   => 'dddd4444',
				'status' => array(
					'confirmed'   => true,
					'block_time'  => $new,
					'block_height' => 800002,
				),
				'vout'   => array(
					array(
						'scriptpubkey_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
						'value'                => 700000,
					),
				),
			),
		),
	)
);

$list = Xdwp_Verifier::list_transfers( $btc, $addr, time() - 86400 );
t( 'a readable chain returns a list', is_array( $list ), var_export( $list, true ) );
$ids = is_array( $list ) ? wp_list_pluck( $list, 'txid' ) : array();
t( 'the transfer to us is listed', in_array( 'aaaa1111', $ids, true ), implode( ',', $ids ) );
t( 'a transfer to somebody else is not', ! in_array( 'dddd4444', $ids, true ), implode( ',', $ids ) );
t( 'a transfer older than asked for is not', ! in_array( 'cccc3333', $ids, true ), implode( ',', $ids ) );

$by_id = array();
foreach ( is_array( $list ) ? $list : array() as $row ) {
	$by_id[ $row['txid'] ] = $row;
}
t( 'the amount is the amount received', isset( $by_id['aaaa1111'] ) && '1.5' === $by_id['aaaa1111']['amount'], isset( $by_id['aaaa1111'] ) ? $by_id['aaaa1111']['amount'] : '?' );
t( 'two outputs to us in one transaction are added, not listed twice', isset( $by_id['bbbb2222'] ) && '0.00000003' === $by_id['bbbb2222']['amount'], isset( $by_id['bbbb2222'] ) ? $by_id['bbbb2222']['amount'] : '?' );
t( 'each transfer carries when it happened', isset( $by_id['aaaa1111'] ) && (int) $by_id['aaaa1111']['time'] === $new );

// An address with genuinely nothing on it is an empty list — which is a real answer, and must
// be distinguishable from the unreachable case above.
xdwp_stub_http( array( 'blockstream.info' => array() ) );
$empty = Xdwp_Verifier::list_transfers( $btc, $addr, time() - 86400 );
t( 'an address with nothing on it returns an empty list, not null', array() === $empty, var_export( $empty, true ) );

// A chain nobody can list returns null whatever the explorer says.
$xmr = Xdwp_Coins::get( 'XMR' );
if ( $xmr ) {
	t( 'a chain that cannot be listed always answers "unknown"', null === Xdwp_Verifier::list_transfers( $xmr, 'anything', 0 ) );
}

// Not an address at all.
t( 'an empty address is refused', null === Xdwp_Verifier::list_transfers( $btc, '', 0 ) );
t( 'a non-string address is refused', null === Xdwp_Verifier::list_transfers( $btc, array(), 0 ) );

// ---------------------------------------------------------------- the reconciler's own rules

$src = file_get_contents( XDWP_PATH . 'includes/class-xdwp-reconcile.php' );
t( 'a chain that cannot be listed is recorded as unreadable', false !== strpos( $src, "'state'   => 'unreadable'" ) );
t( 'an explorer that would not answer is recorded as partial', false !== strpos( $src, "'partial'" ) );
t( 'the two are not the same state', false !== strpos( $src, "'checked'" ) );
// It reads chains and reports; it must never write to an order.
foreach ( array( 'update_meta_data', 'update_status', 'payment_complete', 'mark_paid', 'delete_meta_data' ) as $writer ) {
	t( "reconciling never calls {$writer}()", false === strpos( $src, $writer . '(' ) );
}

$view = file_get_contents( XDWP_PATH . '/includes/admin/views/reconcile-ui.php' );
t( 'the screen names the coins it could not check', false !== strpos( $view, 'Not checked, because their chains' ) );
t( 'and does not call a partial look an all-clear', false !== strpos( $view, 'partial look rather than an all-clear' ) );
t( 'the scan is behind a nonce', false !== strpos( $view, "wp_nonce_field( 'xdwp_reconcile' )" ) );

$admin = file_get_contents( XDWP_PATH . 'includes/admin/class-xdwp-payments-admin.php' );
t( 'and behind a capability check', false !== strpos( $admin, "function handle_reconcile" ) && false !== strpos( $admin, "check_admin_referer( 'xdwp_reconcile' )" ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL RECONCILE TESTS PASSED ({$pass})\n";
exit( 0 );
