#!/usr/bin/env php
<?php
/**
 * Pairing with a wallet on another device.
 *
 * This is the only part of the plugin that runs code written by somebody else, on the page that
 * shows a customer where to send money. So the tests here are mostly about restraint: that it
 * does not exist until a merchant asks for it, that it is fetched only on a press rather than on
 * page load, and that the version is pinned so the code on a payment page cannot change without
 * a signed update of this plugin.
 *
 * Run: php tests/walletconnect-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );
define( 'XDWP_URL', 'https://example.test/wp-content/plugins/xdwp/' );

/**
 * Site URL.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

/**
 * Decode entities, as WordPress would.
 *
 * @param string $text  Text.
 * @param int    $quote Quote style.
 * @return string
 */
function wp_specialchars_decode( $text, $quote = ENT_NOQUOTES ) {
	return html_entity_decode( (string) $text, ENT_QUOTES );
}

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';

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

$eth  = Xdwp_Coins::get( 'ETH' );
$addr = '0x00000000000000000000000000000000000000e1';

// ---------------------------------------------------------------- off unless asked for

update_option( 'xdwp_settings', array() );
$plain = Xdwp_Coins::wallet_payment( $eth, $addr, '0.05' );
t( 'a wallet payment is still offered without a project id', is_array( $plain ) );
t( 'but nothing about WalletConnect reaches the page', ! isset( $plain['walletConnect'] ), wp_json_encode( array_keys( (array) $plain ) ) );

// An empty or malformed id is the same as none: a half-set value must not produce a button that
// cannot work.
foreach ( array( '', '   ', 'not a project id', 'zzzz', '<script>' ) as $bad ) {
	update_option( 'xdwp_settings', array( 'walletconnect_project_id' => $bad ) );
	$out = Xdwp_Coins::wallet_payment( $eth, $addr, '0.05' );
	t( 'a project id of "' . trim( $bad ) . '" offers nothing', ! isset( $out['walletConnect'] ) );
}

// ---------------------------------------------------------------- on, when it is set

update_option( 'xdwp_settings', array( 'walletconnect_project_id' => str_repeat( 'a1b2', 8 ) ) );
$on = Xdwp_Coins::wallet_payment( $eth, $addr, '0.05' );
t( 'a real project id switches it on', isset( $on['walletConnect'] ) );
t( 'and the id is carried through', str_repeat( 'a1b2', 8 ) === $on['walletConnect']['projectId'] );
t( 'with the shop named for the pairing dialog', isset( $on['walletConnect']['metadata']['name'] ) );
t( 'and the shop\'s own address', 'https://example.test/' === $on['walletConnect']['metadata']['url'] );

// The destination and the amount must still come from this plugin, not from anything loaded.
t( 'the address still comes from the page', strtolower( $addr ) === $on['to'] );
t( 'and so does the amount', isset( $on['value'] ) && '0x0' !== $on['value'] );

// ---------------------------------------------------------------- the version is pinned

$src = $on['walletConnect']['src'];
t( 'the library is fetched over https', 0 === strpos( $src, 'https://' ), $src );
t( 'at one exact version, not a range', (bool) preg_match( '/@\d+\.\d+\.\d+$/', $src ), $src );
t( 'and the version is written in the plugin, not in a setting', false !== strpos( file_get_contents( XDWP_PATH . 'includes/class-xdwp-coins.php' ), "const WALLETCONNECT_SRC" ) );

// A shop that would rather serve the file itself must be able to.
t( 'a shop can point it somewhere else', false !== strpos( file_get_contents( XDWP_PATH . 'includes/class-xdwp-coins.php' ), "apply_filters( 'xdwp_walletconnect_src'" ) );

// ---------------------------------------------------------------- nothing loads on page load

$js = file_get_contents( XDWP_PATH . 'assets/js/wallet.js' );
t( 'the library is imported inside a function, not at the top of the file', false === strpos( substr( $js, 0, strpos( $js, 'function' ) ), 'import(' ) );
t( 'and only from the address the server supplied', false !== strpos( $js, 'import(/* webpackIgnore: true */ cfg.src)' ) );
t( 'the button appears only when a project id is present', false !== strpos( $js, 'pay.walletConnect && pay.walletConnect.projectId' ) );
t( 'pairing reuses the ordinary paying code rather than a second copy of it', false !== strpos( $js, "payWith({ provider: provider, kind: 'evm'" ) );
t( 'a failure to load says what to do instead', false !== strpos( $js, 'walletConnectFailed' ) );

// ---------------------------------------------------------------- the merchant is told the trade-off

$view = file_get_contents( XDWP_PATH . 'includes/admin/views/settings-page.php' );
t( 'the setting exists on Prices & APIs', false !== strpos( $view, 'xdwp[walletconnect_project_id]' ) );
t( 'it says this is the one part that loads somebody else\'s code', false !== strpos( $view, 'loads code from somebody else' ) );
t( 'it says nothing loads on an ordinary payment page', false !== strpos( $view, 'never on an ordinary payment page' ) );
t( 'it says the project id is not a secret', false !== strpos( $view, 'not a secret' ) );
t( 'and that it is not needed for customers already on a phone', false !== strpos( $view, 'do not need this for customers already paying from a phone' ) );

// It is an identifier, not a credential — so it must not be filed with the API keys that are
// held back from an export, or a merchant would lose it on a restore without knowing.
$backup = file_get_contents( XDWP_PATH . 'includes/class-xdwp-backup.php' );
t( 'it is not filed as a secret, because it is public by design', false === strpos( $backup, 'walletconnect_project_id' ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL WALLETCONNECT TESTS PASSED ({$pass})\n";
exit( 0 );
