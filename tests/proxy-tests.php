#!/usr/bin/env php
<?php
/**
 * Knowing who the visitor is when Cloudflare sits in front of the shop.
 *
 * Two failures matter, and they pull in opposite directions. Believing Cloudflare's visitor
 * header from anyone lets a stranger pick whichever address they like and walk around every
 * limit. Never believing it makes every customer behind Cloudflare the same few addresses, so
 * they throttle one another. The header is believed only from Cloudflare's own addresses.
 *
 * Run: php tests/proxy-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stand-in.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * Stand-in.
	 *
	 * @return bool
	 */
	function __return_false() {
		return false;
	}
}

require_once XDWP_PATH . 'includes/class-xdwp-proxy.php';
require_once XDWP_PATH . 'includes/class-xdwp-selftest.php';

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

// ---------------------------------------------------------------- ranges

t( 'IPv4 inside a range', Xdwp_Proxy::in_range( '104.21.7.161', '104.16.0.0/13' ) );
t( 'IPv4 at the first address', Xdwp_Proxy::in_range( '104.16.0.0', '104.16.0.0/13' ) );
t( 'IPv4 at the last address', Xdwp_Proxy::in_range( '104.23.255.255', '104.16.0.0/13' ) );
t( 'IPv4 one past the end', ! Xdwp_Proxy::in_range( '104.24.0.0', '104.16.0.0/13' ) );
t( 'IPv4 one before the start', ! Xdwp_Proxy::in_range( '104.15.255.255', '104.16.0.0/13' ) );
t( 'a /32 is one address', Xdwp_Proxy::in_range( '1.2.3.4', '1.2.3.4/32' ) && ! Xdwp_Proxy::in_range( '1.2.3.5', '1.2.3.4/32' ) );
t( 'a /0 is everything', Xdwp_Proxy::in_range( '8.8.8.8', '0.0.0.0/0' ) );
t( 'IPv6 inside a range', Xdwp_Proxy::in_range( '2606:4700:3033::6815:7a1', '2606:4700::/32' ) );
t( 'IPv6 written long-hand inside a range', Xdwp_Proxy::in_range( '2606:4700:0000:0000:0000:0000:0000:0001', '2606:4700::/32' ) );
t( 'IPv6 outside a range', ! Xdwp_Proxy::in_range( '2606:4701::1', '2606:4700::/32' ) );
t( 'an odd-length IPv6 prefix', Xdwp_Proxy::in_range( '2a06:98c7::1', '2a06:98c0::/29' ) && ! Xdwp_Proxy::in_range( '2a06:98c8::1', '2a06:98c0::/29' ) );
t( 'IPv4 never matches an IPv6 range', ! Xdwp_Proxy::in_range( '104.16.0.1', '2606:4700::/32' ) );
t( 'garbage is never in range', ! Xdwp_Proxy::in_range( 'not-an-ip', '104.16.0.0/13' ) );
t( 'a malformed range matches nothing', ! Xdwp_Proxy::in_range( '104.16.0.1', '104.16.0.0/x' ) && ! Xdwp_Proxy::in_range( '104.16.0.1', '104.16.0.0' ) && ! Xdwp_Proxy::in_range( '104.16.0.1', '104.16.0.0/33' ) );
t( 'a non-string matches nothing', ! Xdwp_Proxy::in_range( array( '104.16.0.1' ), '104.16.0.0/13' ) );

t( 'the live shop\'s Cloudflare address is recognised', Xdwp_Proxy::is_cloudflare( '104.21.7.161' ) && Xdwp_Proxy::is_cloudflare( '172.67.136.243' ) );
t( 'a Cloudflare IPv6 address is recognised', Xdwp_Proxy::is_cloudflare( '2400:cb00:2049:1::a29f:1804' ) );
t( 'an ordinary address is not Cloudflare', ! Xdwp_Proxy::is_cloudflare( '161.97.152.1' ) && ! Xdwp_Proxy::is_cloudflare( '8.8.8.8' ) );
t( 'nor is this machine', ! Xdwp_Proxy::is_cloudflare( '127.0.0.1' ) && ! Xdwp_Proxy::is_cloudflare( '::1' ) );
t( 'every published range parses', count( Xdwp_Proxy::cloudflare_ranges() ) === 22 );
foreach ( Xdwp_Proxy::cloudflare_ranges() as $range ) {
	list( $net ) = explode( '/', $range );
	t( "the range {$range} contains its own start", Xdwp_Proxy::in_range( $net, $range ) );
}

// ---------------------------------------------------------------- who the visitor is

$visitor = '81.2.69.160';
$cf_edge = '162.158.22.10';

t( 'no CDN: the connecting address', $visitor === Xdwp_Proxy::client_ip( array( 'remote' => $visitor, 'real' => '', 'cf' => '' ) ) );
t( 'from Cloudflare: the visitor Cloudflare names', $visitor === Xdwp_Proxy::client_ip( array( 'remote' => $cf_edge, 'real' => '', 'cf' => $visitor ) ) );
t( 'from Cloudflare over IPv6: the visitor Cloudflare names', '2001:db8::7' === Xdwp_Proxy::client_ip( array( 'remote' => '2a06:98c0::1', 'real' => '', 'cf' => '2001:db8::7' ) ) );

// The whole point of checking the range: a forged header from anywhere else changes nothing.
t( 'a forged header sent straight to the server is ignored', '203.0.113.9' === Xdwp_Proxy::client_ip( array( 'remote' => '203.0.113.9', 'real' => '', 'cf' => '1.1.1.1' ) ) );
t( 'a forged X-Real-IP from outside is ignored too', '203.0.113.9' === Xdwp_Proxy::client_ip( array( 'remote' => '203.0.113.9', 'real' => $cf_edge, 'cf' => '1.1.1.1' ) ) );
t( 'a malformed Cloudflare header falls back to the connection', $cf_edge === Xdwp_Proxy::client_ip( array( 'remote' => $cf_edge, 'real' => '', 'cf' => '1.2.3.4, 5.6.7.8' ) ) );
t( 'script in the header is never returned', $cf_edge === Xdwp_Proxy::client_ip( array( 'remote' => $cf_edge, 'real' => '', 'cf' => '<script>' ) ) );

// nginx in front of Apache or PHP-FPM, as control panels like HestiaCP set up.
t( 'behind a local nginx and Cloudflare: still the visitor', $visitor === Xdwp_Proxy::client_ip( array( 'remote' => '127.0.0.1', 'real' => $cf_edge, 'cf' => $visitor ) ) );
t( 'behind a local nginx with no CDN: the visitor, not 127.0.0.1', $visitor === Xdwp_Proxy::client_ip( array( 'remote' => '127.0.0.1', 'real' => $visitor, 'cf' => '' ) ) );
t( 'behind a private-network proxy: the address it names', $visitor === Xdwp_Proxy::client_ip( array( 'remote' => '10.0.0.5', 'real' => $visitor, 'cf' => '' ) ) );
t( 'the local hop is one hop: a forged CF header behind it is ignored', '198.51.100.4' === Xdwp_Proxy::client_ip( array( 'remote' => '127.0.0.1', 'real' => '198.51.100.4', 'cf' => '1.1.1.1' ) ) );

t( 'no request at all: no address', '' === Xdwp_Proxy::client_ip( array( 'remote' => '', 'real' => '', 'cf' => '' ) ) );

add_filter( 'xdwp_trust_cloudflare', '__return_false' );
t( 'switched off: Cloudflare\'s header is not read', $cf_edge === Xdwp_Proxy::client_ip( array( 'remote' => $cf_edge, 'real' => '', 'cf' => $visitor ) ) );
remove_filter( 'xdwp_trust_cloudflare', '__return_false' );
unset( $GLOBALS['xdwp_stub']['filters']['xdwp_trust_cloudflare'] );

// ---------------------------------------------------------------- the setup check

$cases = array(
	'direct'    => array( 'remote' => $visitor, 'real' => '', 'cf' => '' ),
	'restored'  => array( 'remote' => $visitor, 'real' => '', 'cf' => $visitor ),
	'handled'   => array( 'remote' => $cf_edge, 'real' => '', 'cf' => $visitor ),
	'no_header' => array( 'remote' => $cf_edge, 'real' => '', 'cf' => '' ),
	'untrusted' => array( 'remote' => '203.0.113.9', 'real' => '', 'cf' => $visitor ),
	'unknown'   => array( 'remote' => '', 'real' => '', 'cf' => '' ),
);
foreach ( $cases as $expect => $request ) {
	$got = Xdwp_Proxy::diagnose( $request );
	t( "diagnosed as {$expect}", $expect === $got, $got );
}
t( 'nginx in front, Cloudflare beyond it: handled', 'handled' === Xdwp_Proxy::diagnose( array( 'remote' => '127.0.0.1', 'real' => $cf_edge, 'cf' => $visitor ) ) );
t( 'nginx in front that restored the address itself: restored, not untrusted', 'restored' === Xdwp_Proxy::diagnose( array( 'remote' => '127.0.0.1', 'real' => $visitor, 'cf' => $visitor ) ) );
t( 'restored is recognised however IPv6 is written', 'restored' === Xdwp_Proxy::diagnose( array( 'remote' => '2001:db8::7', 'real' => '', 'cf' => '2001:0db8:0000:0000:0000:0000:0000:0007' ) ) );

add_filter( 'xdwp_trust_cloudflare', '__return_false' );
t( 'diagnosed as off when switched off', 'off' === Xdwp_Proxy::diagnose( $cases['handled'] ) );
unset( $GLOBALS['xdwp_stub']['filters']['xdwp_trust_cloudflare'] );

t( 'no CDN: the setup check says nothing', null === Xdwp_Selftest::check_proxy( $cases['direct'] ) );
t( 'no request: the setup check says nothing', null === Xdwp_Selftest::check_proxy( $cases['unknown'] ) );
$restored = Xdwp_Selftest::check_proxy( $cases['restored'] );
t( 'restored: the setup check is happy', is_array( $restored ) && 'ok' === $restored['status'] );
foreach ( array( 'handled', 'no_header', 'untrusted' ) as $case ) {
	$check = Xdwp_Selftest::check_proxy( $cases[ $case ] );
	t( "{$case}: the setup check reports it", is_array( $check ) && 'warn' === $check['status'], is_array( $check ) ? $check['status'] : 'null' );
	t( "{$case}: in a sentence a merchant can act on", is_array( $check ) && strlen( $check['detail'] ) > 120 );
}
$handled = Xdwp_Selftest::check_proxy( $cases['handled'] );
t( 'handled: says this plugin already copes', false !== strpos( $handled['detail'], 'already reads the real visitor address' ) );
t( 'handled: and names the server-side fix', false !== strpos( $handled['detail'], 'mod_remoteip' ) );

// ---------------------------------------------------------------- wired in

$ajax = file_get_contents( XDWP_PATH . 'includes/class-xdwp-ajax.php' );
t( 'the checkout\'s limits use it', false !== strpos( $ajax, 'Xdwp_Proxy::client_ip()' ) );
t( 'and the existing override still applies after it', strpos( $ajax, 'Xdwp_Proxy::client_ip()' ) < strpos( $ajax, "apply_filters( 'xdwp_rate_limit_client_ip'" ) );
t( 'the refund page uses the same address', false !== strpos( file_get_contents( XDWP_PATH . 'includes/class-xdwp-refunds.php' ), 'Xdwp_Ajax::client_ip()' ) );
$loader = file_get_contents( XDWP_PATH . 'includes/class-xdwp.php' );
t( 'it is loaded before the code that uses it', false !== strpos( $loader, 'class-xdwp-proxy.php' ) && strpos( $loader, 'class-xdwp-proxy.php' ) < strpos( $loader, 'class-xdwp-ajax.php' ) );
t( 'the setup check is on the list', false !== strpos( file_get_contents( XDWP_PATH . 'includes/class-xdwp-selftest.php' ), '$proxy = self::check_proxy();' ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL PROXY TESTS PASSED ({$pass})\n";
exit( 0 );
