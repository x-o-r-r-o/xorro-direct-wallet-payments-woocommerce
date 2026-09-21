<?php
/**
 * Who is actually asking, when a CDN sits in front of the shop.
 *
 * Behind Cloudflare, every request reaches the server from one of Cloudflare's own addresses.
 * Unless the server is set up to restore the visitor's address, PHP sees Cloudflare's instead,
 * and everything this plugin limits "per visitor" is really limited per Cloudflare data centre:
 * one customer's retries throttle the next, and ten wrong guesses at a refund link by anyone
 * lock every customer out of it.
 *
 * Cloudflare puts the visitor's address in the CF-Connecting-IP header. Anyone can send that
 * header, so it is believed only when the connection itself came from an address Cloudflare
 * publishes as its own. A request sent straight to the server with the header forged is
 * treated as coming from wherever it really came from.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolving the visitor's address behind a CDN.
 */
class Xdwp_Proxy {

	/**
	 * Cloudflare's published ranges, from https://www.cloudflare.com/ips/ (checked 2026-09-22,
	 * matching https://api.cloudflare.com/client/v4/ips). They change rarely; a shop can
	 * correct them without an update through the xdwp_cloudflare_ranges filter.
	 *
	 * @return array<int, string>
	 */
	public static function cloudflare_ranges() {
		$ranges = array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		);

		/**
		 * Cloudflare's address ranges, used to decide whether CF-Connecting-IP can be believed.
		 *
		 * @param array<int, string> $ranges CIDR ranges.
		 */
		$ranges = apply_filters( 'xdwp_cloudflare_ranges', $ranges );
		return is_array( $ranges ) ? array_values( array_filter( $ranges, 'is_string' ) ) : array();
	}

	/**
	 * Whether an address lies inside a CIDR range. IPv4 and IPv6 alike; a mismatch of the two
	 * is simply "no".
	 *
	 * @param string $ip   Address.
	 * @param string $cidr Range, e.g. "104.16.0.0/13".
	 * @return bool
	 */
	public static function in_range( $ip, $cidr ) {
		if ( ! is_string( $ip ) || ! is_string( $cidr ) || false === strpos( $cidr, '/' ) ) {
			return false;
		}
		list( $net, $bits ) = explode( '/', $cidr, 2 );
		if ( ! ctype_digit( $bits ) ) {
			return false;
		}
		$ip_bin  = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed address is an answer, not an error.
		$net_bin = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}
		$bits = (int) $bits;
		if ( $bits > strlen( $ip_bin ) * 8 ) {
			return false;
		}
		$whole = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $whole ) !== substr( $net_bin, 0, $whole ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $ip_bin[ $whole ] ) & $mask ) === ( ord( $net_bin[ $whole ] ) & $mask );
	}

	/**
	 * Whether the connection came from Cloudflare.
	 *
	 * @param string $ip Address the connection came from.
	 * @return bool
	 */
	public static function is_cloudflare( $ip ) {
		foreach ( self::cloudflare_ranges() as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A clean IP address, or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function valid_ip( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		return false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}

	/**
	 * The raw request facts this class decides from, gathered in one place so the decision
	 * itself can be tested without a web server.
	 *
	 * @return array{remote:string, real:string, cf:string}
	 */
	public static function request() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an IP address below; nothing else survives.
		return array(
			'remote' => isset( $_SERVER['REMOTE_ADDR'] ) ? self::valid_ip( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'real'   => isset( $_SERVER['HTTP_X_REAL_IP'] ) ? self::valid_ip( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) : '',
			'cf'     => isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? self::valid_ip( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '',
		);
		// phpcs:enable
	}

	/**
	 * Whether an address is this machine or a private network: somewhere only the server's own
	 * software can connect from.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function is_local( $ip ) {
		return '' !== self::valid_ip( $ip )
			&& false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * The address that connected to the server's front door.
	 *
	 * Usually REMOTE_ADDR. On a server that runs nginx in front of Apache or PHP-FPM without
	 * restoring addresses (a common control-panel setup), REMOTE_ADDR is the local proxy, and the
	 * address that actually connected is the one that proxy wrote into X-Real-IP. That header is
	 * read only when the connection came from this machine or a private network — nobody outside
	 * can make a connection appear to come from there — and only one hop deep.
	 *
	 * @param array $request Facts from request().
	 * @return string
	 */
	private static function edge( array $request ) {
		$remote = isset( $request['remote'] ) ? self::valid_ip( $request['remote'] ) : '';
		$real   = isset( $request['real'] ) ? self::valid_ip( $request['real'] ) : '';
		if ( '' !== $remote && '' !== $real && self::is_local( $remote ) ) {
			return $real;
		}
		return $remote;
	}

	/**
	 * Whether two addresses are the same address, however each is written.
	 *
	 * @param string $a Address.
	 * @param string $b Address.
	 * @return bool
	 */
	private static function same_ip( $a, $b ) {
		$pa = '' !== self::valid_ip( $a ) ? inet_pton( $a ) : false;
		$pb = '' !== self::valid_ip( $b ) ? inet_pton( $b ) : false;
		return false !== $pa && $pa === $pb;
	}

	/**
	 * The visitor's address, as best this request can tell it.
	 *
	 * @param array|null $request Facts from request(); the live request when null.
	 * @return string An IP address, or '' when there is none (WP-CLI, cron).
	 */
	public static function client_ip( $request = null ) {
		$request = is_array( $request ) ? $request : self::request();
		$remote  = isset( $request['remote'] ) ? self::valid_ip( $request['remote'] ) : '';
		$edge    = self::edge( $request );
		$cf      = isset( $request['cf'] ) ? self::valid_ip( $request['cf'] ) : '';

		if ( self::trusts_cloudflare() && '' !== $cf && '' !== $edge && self::is_cloudflare( $edge ) ) {
			return $cf;
		}
		// No Cloudflare: whoever connected to the front door. Behind a local nginx that did not
		// restore the address, that is the visitor rather than 127.0.0.1 for everyone.
		return '' !== $edge ? $edge : $remote;
	}

	/**
	 * Whether Cloudflare's visitor header is believed at all.
	 *
	 * @return bool
	 */
	public static function trusts_cloudflare() {
		/**
		 * Whether to believe Cloudflare's visitor header when the connection comes from Cloudflare.
		 *
		 * @param bool $trust Default true.
		 */
		return (bool) apply_filters( 'xdwp_trust_cloudflare', true );
	}

	/**
	 * What this request says about the path from visitor to server, for the setup check.
	 *
	 * - direct:     no CDN header, nothing to do.
	 * - restored:   behind Cloudflare, and the server already hands PHP the visitor's address.
	 * - handled:    behind Cloudflare, the server does not restore the address, and this plugin
	 *               reads it from Cloudflare itself.
	 * - untrusted:  the Cloudflare header arrived from an address that is not Cloudflare's, and
	 *               is not the visitor's own either — another proxy in between, or a forgery.
	 * - no_header:  the connection came from Cloudflare without saying who the visitor was.
	 * - off:        behind Cloudflare, the server does not restore the address, and the shop has
	 *               told this plugin not to read Cloudflare's header (xdwp_trust_cloudflare).
	 * - unknown:    no request to judge (WP-CLI, cron).
	 *
	 * @param array|null $request Facts from request(); the live request when null.
	 * @return string
	 */
	public static function diagnose( $request = null ) {
		$request = is_array( $request ) ? $request : self::request();
		$remote  = isset( $request['remote'] ) ? self::valid_ip( $request['remote'] ) : '';
		$edge    = self::edge( $request );
		$cf      = isset( $request['cf'] ) ? self::valid_ip( $request['cf'] ) : '';

		if ( '' === $remote ) {
			return 'unknown';
		}
		if ( self::is_cloudflare( $edge ) ) {
			if ( '' === $cf ) {
				return 'no_header';
			}
			return self::trusts_cloudflare() ? 'handled' : 'off';
		}
		if ( '' === $cf ) {
			return 'direct';
		}
		return self::same_ip( $cf, $edge ) ? 'restored' : 'untrusted';
	}
}
