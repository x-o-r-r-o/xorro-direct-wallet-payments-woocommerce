#!/usr/bin/env php
<?php
/**
 * What a customer is told after pressing "I have sent the payment".
 *
 * These sentences are the feature. Two of them can cost real money if they are wrong: telling
 * someone their payment arrived when it has not, and failing to tell someone whose payment is
 * already in the mempool not to send it again. So the wording is decided by a pure function and
 * checked here, apart from any chain, database or order.
 *
 * Run: php tests/verdict-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

/**
 * Plural form, as WordPress would resolve it for English.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Count.
 * @param string $domain Text domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

require_once XDWP_PATH . 'includes/class-xdwp-order.php';

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
 * Shorthand.
 *
 * @param array $state State.
 * @return array
 */
function v( array $state ) {
	return Xdwp_Order::verdict_for( $state );
}

// ---------------------------------------------------------------- the settled cases

$paid = v( array( 'paid' => true ) );
t( 'a paid order says so', 'paid' === $paid['verdict'] );
t( 'and does not ask for anything else', false !== stripos( $paid['message'], 'nothing more is needed' ), $paid['message'] );

$just = v(
	array(
		'paid'      => true,
		'just_paid' => true,
	)
);
t( 'a payment found by this very check is worded as a find', 'paid' === $just['verdict'] && false !== stripos( $just['message'], 'found it' ), $just['message'] );

$settled = v( array( 'settled' => true ) );
t( 'a cancelled order does not pretend to still be waiting', 'settled' === $settled['verdict'] );
t( 'and tells the customer who to talk to', false !== stripos( $settled['message'], 'contact the shop' ), $settled['message'] );

$gone = v( array( 'gone' => true ) );
t( 'a missing order produces no sentence at all', 'settled' === $gone['verdict'] && '' === $gone['message'] );

// ---------------------------------------------------------------- the paying cases

$busy = v( array( 'busy' => true ) );
t( 'when the shop is out of lookups the answer is still reassuring', 'busy' === $busy['verdict'] );
t( 'and says the page keeps working unattended', false !== stripos( $busy['message'], 'close it' ), $busy['message'] );

$short = v(
	array(
		'underpaid' => true,
		'remainder' => '0.00042',
		'symbol'    => 'BTC',
	)
);
t( 'a part payment is reported as short', 'short' === $short['verdict'] );
t( 'with the exact amount still owed', false !== strpos( $short['message'], '0.00042' ), $short['message'] );
t( 'and the coin it is owed in', false !== strpos( $short['message'], 'BTC' ), $short['message'] );
t( 'and says to use the same address', false !== stripos( $short['message'], 'same address' ), $short['message'] );

// A remainder the shop could not work out must not print an empty gap in the sentence.
$short_unknown = v( array( 'underpaid' => true ) );
t( 'a part payment with no figure still makes sense', 'short' === $short_unknown['verdict'] && false === strpos( $short_unknown['message'], '  ' ), $short_unknown['message'] );

// The one that stops a customer paying twice.
$confirming = v(
	array(
		'detected'      => true,
		'confirmations' => 3,
	)
);
t( 'a payment seen on chain is reported as confirming', 'confirming' === $confirming['verdict'] );
t( 'with the number of confirmations it waits for', false !== strpos( $confirming['message'], '3' ), $confirming['message'] );
t( 'and tells them not to send it again', false !== stripos( $confirming['message'], 'do not send it again' ), $confirming['message'] );

$one = v(
	array(
		'detected'      => true,
		'confirmations' => 1,
	)
);
t( 'one confirmation is not written as "1 confirmations"', false === strpos( $one['message'], '1 confirmations' ), $one['message'] );

$instant = v(
	array(
		'detected'      => true,
		'confirmations' => 0,
	)
);
t( 'a chain with no confirmation depth still warns against paying twice', 'confirming' === $instant['verdict'] && false !== stripos( $instant['message'], 'do not send it again' ), $instant['message'] );

// Part-paid outranks seen-on-chain: still owing something is the more useful thing to hear.
$both = v(
	array(
		'underpaid'     => true,
		'remainder'     => '1.5',
		'symbol'        => 'XLM',
		'detected'      => true,
		'confirmations' => 5,
	)
);
t( 'owing money is said before "we can see it"', 'short' === $both['verdict'], $both['verdict'] );

// ---------------------------------------------------------------- nothing found yet

$memo = v( array( 'memo' => 'XDWP-AB12CD34' ) );
t( 'nothing found on a memo chain names the memo', 'looking' === $memo['verdict'] && false !== strpos( $memo['message'], 'XDWP-AB12CD34' ), $memo['message'] );
t( 'and explains what happens without it', false !== stripos( $memo['message'], 'cannot be matched' ), $memo['message'] );

$network = v( array( 'network' => 'TRON (TRC-20)' ) );
t( 'nothing found elsewhere names the network to check', 'looking' === $network['verdict'] && false !== strpos( $network['message'], 'TRON (TRC-20)' ), $network['message'] );
t( 'and says a transfer on another network will not arrive', false !== stripos( $network['message'], 'another network' ), $network['message'] );

// The memo is the more dangerous mistake, so it is named even when a network is known too.
$memo_first = v(
	array(
		'memo'    => 'XDWP-ZZ99',
		'network' => 'XRP Ledger',
	)
);
t( 'a memo chain warns about the memo, not the network', false !== strpos( $memo_first['message'], 'XDWP-ZZ99' ), $memo_first['message'] );

$bare = v( array() );
t( 'knowing nothing still produces a usable sentence', 'looking' === $bare['verdict'] && '' !== $bare['message'] );
t( 'with no leftover placeholder in it', false === strpos( $bare['message'], '%' ), $bare['message'] );

// ---------------------------------------------------------------- every sentence is sound

$all = array(
	'paid'       => $paid,
	'just paid'  => $just,
	'settled'    => $settled,
	'busy'       => $busy,
	'short'      => $short,
	'confirming' => $confirming,
	'memo'       => $memo,
	'network'    => $network,
	'bare'       => $bare,
);
$known = array( 'paid', 'short', 'confirming', 'looking', 'busy', 'settled' );
foreach ( $all as $name => $result ) {
	t( "the {$name} verdict is one the page knows how to style", in_array( $result['verdict'], $known, true ), $result['verdict'] );
	t( "the {$name} message has no unfilled placeholder", false === strpos( $result['message'], '%s' ) && false === strpos( $result['message'], '%d' ), $result['message'] );
}

// A verdict must never be a bare status word: it is read by someone worried about money.
foreach ( $all as $name => $result ) {
	if ( 'settled' === $result['verdict'] && '' === $result['message'] ) {
		continue;
	}
	t( "the {$name} message is a sentence, not a label", strlen( $result['message'] ) > 40, $result['message'] );
}

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL VERDICT TESTS PASSED ({$pass})\n";
exit( 0 );
