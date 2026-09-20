#!/usr/bin/env php
<?php
/**
 * Address derivation checked against the published BIP32 / BIP44 / BIP49 / BIP84 vectors.
 *
 * A wrong address here would send a customer's money somewhere the merchant cannot reach, so
 * every supported key type is checked against known-good addresses before release.
 *
 * Run: php tests/hd-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

require_once XDWP_PATH . 'includes/class-xdwp-hd.php';

$pass = 0;
$fail = 0;

/**
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
	echo "[FAIL] {$label}" . ( '' !== $info ? "  ({$info})" : '' ) . "\n";
}

// The BIP84 test vector mnemonic ("abandon abandon … about") and its published account keys
// and first receiving addresses.
$zpub = 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs';
$xpub = 'xpub6BosfCnifzxcFwrSzQiqu2DBVTshkCXacvNsWGYJVVhhawA7d4R5WSWGFNbi8Aw6ZRc1brxMyWMzG3DSSSSoekkudhUd9yLb6qx39T9nMdj';
$ypub = 'ypub6Ww3ibxVfGzLrAH1PNcjyAWenMTbbAosGNB6VvmSEgytSER9azLDWCxoJwW7Ke7icmizBMXrzBx9979FfaHxHcrArf3zbeJJJUZPf663zsP';

t(
	'a native-segwit account key is accepted',
	Xdwp_Hd::is_valid( $zpub, 'BTC' ),
	'zpub rejected'
);
t(
	'first bc1 address matches the published vector',
	'bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu' === Xdwp_Hd::address( $zpub, 0 ),
	Xdwp_Hd::address( $zpub, 0 )
);
t(
	'second bc1 address matches the published vector',
	'bc1qnjg0jd8228aq7egyzacy8cys3knf9xvrerkf9g' === Xdwp_Hd::address( $zpub, 1 ),
	Xdwp_Hd::address( $zpub, 1 )
);

t(
	'first legacy address matches the published vector',
	'1LqBGSKuX5yYUonjxT5qGfpUsXKYYWeabA' === Xdwp_Hd::address( $xpub, 0 ),
	Xdwp_Hd::address( $xpub, 0 )
);
t(
	'second legacy address matches the published vector',
	'1Ak8PffB2meyfYnbXZR9EGfLfFZVpzJvQP' === Xdwp_Hd::address( $xpub, 1 ),
	Xdwp_Hd::address( $xpub, 1 )
);

t(
	'first wrapped-segwit address matches the published vector',
	'37VucYSaXLCAsxYyAPfbSi9eh4iEcbShgf' === Xdwp_Hd::address( $ypub, 0 ),
	Xdwp_Hd::address( $ypub, 0 )
);

// Every address must be different, and stable between calls.
$seen = array();
for ( $i = 0; $i < 5; $i++ ) {
	$seen[] = Xdwp_Hd::address( $zpub, $i );
}
t( 'each index gives a different address', count( array_unique( $seen ) ) === 5, implode( ',', $seen ) );
t( 'the same index always gives the same address', Xdwp_Hd::address( $zpub, 3 ) === $seen[3] );

// Rejections: a private key, a typo, another coin's key, nonsense.
$xprv = 'xprv9s21ZrQH143K3QTDL4LXw2F7HEK3wJUD2nW2nRk4stbPy6cq3jPPqjiChkVvvNKmPGJxWUtg6LnF5kejMRNNU3TGtRBeJgk33yuGBxrMPHi';
t( 'a private key is refused', ! Xdwp_Hd::is_valid( $xprv, 'BTC' ) );
t( 'a key with a broken checksum is refused', ! Xdwp_Hd::is_valid( substr( $zpub, 0, -1 ) . 'x', 'BTC' ) );
t( 'an empty value is refused', ! Xdwp_Hd::is_valid( '', 'BTC' ) );
t( 'a plain address is refused', ! Xdwp_Hd::is_valid( 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', 'BTC' ) );
t( 'a Bitcoin key is refused for Litecoin', ! Xdwp_Hd::is_valid( $zpub, 'LTC' ) );
t( 'no address comes back for a key we refused', '' === Xdwp_Hd::address( $xprv, 0 ) );
t( 'a negative index gives no address', '' === Xdwp_Hd::address( $zpub, -1 ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL DERIVATION TESTS PASSED ({$pass})\n";
exit( 0 );
