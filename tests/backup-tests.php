#!/usr/bin/env php
<?php
/**
 * Carrying the configuration between sites, in whole or in part.
 *
 * The thing that must not go wrong here is a partial file quietly wiping what it does not
 * mention: a merchant who exports wallet addresses from staging and restores them on the live
 * shop must not lose that shop's API keys, limits and confirmations in the process. The other
 * is a file of API keys being produced when nobody asked for one.
 *
 * Run: php tests/backup-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

/**
 * The site's own address.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

/**
 * WordPress strips slashes from request data; these tests hand values over directly.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Textarea sanitiser, as WordPress would.
 *
 * @param mixed $value Value.
 * @return string
 */
function sanitize_textarea_field( $value ) {
	return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
}

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
// Xdwp_Settings::sanitize() hands the checkout branding fields to Xdwp_Branding, so a restore
// needs it loaded — in the plugin the two always arrive together.
require_once XDWP_PATH . 'includes/class-xdwp-branding.php';
require_once XDWP_PATH . 'includes/class-xdwp-hd.php';
require_once XDWP_PATH . 'includes/class-xdwp-wallets.php';
require_once XDWP_PATH . 'includes/class-xdwp-backup.php';

$pass = 0;
$fail = 0;

/**
 * Assert.
 *
 * @param string $label Assertion.
 * @param bool   $cond  Result.
 * @param string $info  Detail shown on failure.
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
 * A shop that has been set up: wallets, an extended key, credentials and its own preferences.
 */
function seed_settings() {
	update_option(
		'xdwp_settings',
		array(
			'wallets'              => array(
				'BTC' => array( 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ),
				'ETH' => array( '0x00000000000000000000000000000000000000e1' ),
			),
			'xpubs'                => array( 'BTC' => 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs' ),
			'coingecko_api_key'    => 'CG-SECRETVALUE1',
			'etherscan_api_key'    => 'ES-SECRETVALUE2',
			'telegram_token'       => 'TG-SECRETVALUE3',
			'payment_window'       => 45,
			'min_confirmations'    => 3,
			'underpayment_percent' => 2,
			'order_status'         => 'completed',
		)
	);
}

seed_settings();

// ---------------------------------------------------------------- what each scope carries

$all = Xdwp_Backup::payload( false, Xdwp_Backup::SCOPE_ALL );
t( 'everything: carries the wallets', isset( $all['settings']['wallets']['BTC'] ) );
t( 'everything: carries the shop preferences', 45 === $all['settings']['payment_window'] );
t( 'everything: holds the API keys back by default', ! isset( $all['settings']['coingecko_api_key'] ) );
t( 'everything: and says so in the file', false === $all['secrets'] );

$all_secrets = Xdwp_Backup::payload( true, Xdwp_Backup::SCOPE_ALL );
t( 'everything + keys: carries them when asked', 'CG-SECRETVALUE1' === $all_secrets['settings']['coingecko_api_key'] );
t( 'everything + keys: and says so in the file', true === $all_secrets['secrets'] );

$wallets = Xdwp_Backup::payload( false, Xdwp_Backup::SCOPE_WALLETS );
t( 'wallets only: carries the addresses', isset( $wallets['settings']['wallets']['BTC'] ) );
t( 'wallets only: carries the extended key', isset( $wallets['settings']['xpubs']['BTC'] ) );
t( 'wallets only: leaves the shop preferences out', ! isset( $wallets['settings']['payment_window'] ) );
t( 'wallets only: leaves the API keys out', ! isset( $wallets['settings']['coingecko_api_key'] ) );

// Ticking "include API keys" and choosing "wallets only" is a contradiction. The file is about
// addresses; it must not become a credential file because a box was left ticked.
$wallets_forced = Xdwp_Backup::payload( true, Xdwp_Backup::SCOPE_WALLETS );
$wallets_json   = wp_json_encode( $wallets_forced );
t( 'wallets only: still has no keys even if secrets were ticked', ! isset( $wallets_forced['settings']['coingecko_api_key'] ) );
t( 'wallets only: and no secret appears anywhere in the file', false === strpos( $wallets_json, 'SECRETVALUE' ), $wallets_json );
t( 'wallets only: the file does not claim to hold secrets', false === $wallets_forced['secrets'] );

$keys = Xdwp_Backup::payload( false, Xdwp_Backup::SCOPE_KEYS );
t( 'keys only: carries the keys even though secrets was not ticked', 'CG-SECRETVALUE1' === $keys['settings']['coingecko_api_key'] );
t( 'keys only: carries the other credentials too', 'TG-SECRETVALUE3' === $keys['settings']['telegram_token'] );
t( 'keys only: leaves the wallets out', ! isset( $keys['settings']['wallets'] ) );
t( 'keys only: leaves the shop preferences out', ! isset( $keys['settings']['payment_window'] ) );
t( 'keys only: the file admits it holds secrets', true === $keys['secrets'] );

// ---------------------------------------------------------------- naming and scope handling

t( 'the download is named for what is in it', 'xorro-wallet-wallets-' . gmdate( 'Y-m-d' ) . '.json' === Xdwp_Backup::filename( Xdwp_Backup::SCOPE_WALLETS ) );
t( 'a keys file is named differently again', 0 === strpos( Xdwp_Backup::filename( Xdwp_Backup::SCOPE_KEYS ), 'xorro-wallet-api-keys-' ) );
t( 'an unknown scope falls back to everything', Xdwp_Backup::SCOPE_ALL === Xdwp_Backup::scope( 'wallets; drop table' ) );
t( 'so does a scope that is not text', Xdwp_Backup::SCOPE_ALL === Xdwp_Backup::scope( array( 'keys' ) ) );
t( 'every offered scope is one the exporter accepts', array_keys( Xdwp_Backup::scopes() ) === array_map( array( 'Xdwp_Backup', 'scope' ), array_keys( Xdwp_Backup::scopes() ) ) );

// ---------------------------------------------------------------- restoring a partial file

// The one that matters: a wallets file from another site must not take this site's keys or
// preferences with it.
seed_settings();
$from_staging = wp_json_encode(
	array(
		'format'   => 'xorro-direct-wallet-payments-settings',
		'version'  => 1,
		'scope'    => 'wallets',
		'secrets'  => false,
		'settings' => array(
			'wallets' => array( 'BTC' => array( 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq' ) ),
		),
	)
);
$result = Xdwp_Backup::restore( $from_staging );
$after  = Xdwp_Settings::all();
t( 'restoring wallets: reports what kind of file it was', 'done_wallets' === $result, (string) $result );
t( 'restoring wallets: the address is updated', array( 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq' ) === $after['wallets']['BTC'] );
t( 'restoring wallets: the API keys are still here', 'CG-SECRETVALUE1' === $after['coingecko_api_key'] );
t( 'restoring wallets: the shop preferences are still here', 45 === $after['payment_window'] );
t( 'restoring wallets: a coin the file never mentioned is untouched', isset( $after['wallets']['ETH'] ) );

// A file is not a reason to trust an address. Money sent to a wrong one cannot come back, so a
// restore checks each address exactly as the Wallets form does.
seed_settings();
Xdwp_Backup::restore(
	wp_json_encode(
		array(
			'format'   => 'xorro-direct-wallet-payments-settings',
			'scope'    => 'wallets',
			'settings' => array( 'wallets' => array( 'BTC' => array( 'not-a-bitcoin-address' ) ) ),
		)
	)
);
$after = Xdwp_Settings::all();
t(
	'restoring wallets: an address that is not one is refused, and the real one kept',
	array( 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ) === $after['wallets']['BTC'],
	wp_json_encode( $after['wallets']['BTC'] )
);

// And a keys file must not disturb the wallets.
seed_settings();
$keys_file = wp_json_encode(
	array(
		'format'   => 'xorro-direct-wallet-payments-settings',
		'version'  => 1,
		'scope'    => 'keys',
		'secrets'  => true,
		'settings' => array( 'coingecko_api_key' => 'CG-REPLACED' ),
	)
);
$result = Xdwp_Backup::restore( $keys_file );
$after  = Xdwp_Settings::all();
t( 'restoring keys: reports what kind of file it was', 'done_keys' === $result, (string) $result );
t( 'restoring keys: the key is updated', 'CG-REPLACED' === $after['coingecko_api_key'] );
t( 'restoring keys: the wallets are untouched', array( 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4' ) === $after['wallets']['BTC'] );
t( 'restoring keys: the shop preferences are untouched', 45 === $after['payment_window'] );

// A file with no scope is an older export, from before scopes existed. It is a full one.
seed_settings();
$legacy = wp_json_encode(
	array(
		'format'   => 'xorro-direct-wallet-payments-settings',
		'version'  => 1,
		'settings' => array( 'payment_window' => 90 ),
	)
);
t( 'a file from before scopes existed still restores', 'done' === Xdwp_Backup::restore( $legacy ) );
t( 'and applies what it carries', 90 === Xdwp_Settings::all()['payment_window'] );

// ---------------------------------------------------------------- files that are not ours

t( 'not JSON is refused', 'badjson' === Xdwp_Backup::restore( 'not a file' ) );
t( 'an empty string is refused', 'badjson' === Xdwp_Backup::restore( '' ) );
t( 'a JSON array of the wrong shape is refused', 'notours' === Xdwp_Backup::restore( '{"format":"something-else","settings":{"payment_window":5}}' ) );
t( 'our format with nothing in it is refused', 'empty' === Xdwp_Backup::restore( '{"format":"xorro-direct-wallet-payments-settings","settings":[]}' ) );

// A hostile file must not be able to set a value the settings form would never accept.
seed_settings();
Xdwp_Backup::restore(
	wp_json_encode(
		array(
			'format'   => 'xorro-direct-wallet-payments-settings',
			'settings' => array(
				'payment_window'    => 999999,
				'min_confirmations' => -5,
				'order_status'      => 'refunded',
			),
		)
	)
);
$after = Xdwp_Settings::all();
t( 'a hostile file cannot set an out-of-range window', $after['payment_window'] <= 1440, (string) $after['payment_window'] );
t( 'nor a negative confirmation count', $after['min_confirmations'] >= 0, (string) $after['min_confirmations'] );
t( 'nor an order status the shop does not use', in_array( $after['order_status'], array( 'processing', 'completed', 'on-hold' ), true ), (string) $after['order_status'] );

// ---------------------------------------------------------------- every secret is accounted for

// A key added to the settings form but forgotten here would be exported in a "wallets" file.
$declared = Xdwp_Backup::secret_keys();
$in_form  = array();
preg_match( '/foreach \(\s*array\(\s*((?:\s*\x27[a-z0-9_]+\x27,\s*)+)\)\s*as \$text_key/', file_get_contents( XDWP_PATH . 'includes/class-xdwp-settings.php' ), $m );
if ( ! empty( $m[1] ) ) {
	preg_match_all( '/\x27([a-z0-9_]+)\x27/', $m[1], $found );
	$in_form = $found[1];
}
sort( $declared );
sort( $in_form );
t( 'every credential the settings form handles is declared a secret', $declared === $in_form, wp_json_encode( array_diff( $in_form, $declared ) ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL BACKUP TESTS PASSED ({$pass})\n";
exit( 0 );
