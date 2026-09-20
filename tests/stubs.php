<?php
/**
 * Just enough WordPress to run the payment-matching code outside WordPress.
 *
 * The matching logic is the part that decides whether a customer's money is credited, so it is
 * worth testing on recorded explorer responses, in CI, without a database. Everything here is a
 * stand-in: HTTP is served from tests/fixtures, options and transients live in arrays.
 *
 * @package Xdwp
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

$GLOBALS['xdwp_stub'] = array(
	'options'    => array(),
	'transients' => array(),
	'http'       => array(),  // URL fragment => response body (string or array).
	'requests'   => array(),  // Every URL asked for, in order.
	'actions'    => array(),
);

// --- options / transients -------------------------------------------------

function get_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['xdwp_stub']['options'] ) ? $GLOBALS['xdwp_stub']['options'][ $name ] : $default_value;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['xdwp_stub']['options'][ $name ] = $value;
	return true;
}

function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $name, $GLOBALS['xdwp_stub']['options'] ) ) {
		return false;
	}
	$GLOBALS['xdwp_stub']['options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['xdwp_stub']['options'][ $name ] );
	return true;
}

function get_transient( $key ) {
	if ( ! isset( $GLOBALS['xdwp_stub']['transients'][ $key ] ) ) {
		return false;
	}
	list( $value, $expires ) = $GLOBALS['xdwp_stub']['transients'][ $key ];
	if ( $expires && $expires < time() ) {
		unset( $GLOBALS['xdwp_stub']['transients'][ $key ] );
		return false;
	}
	return $value;
}

function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['xdwp_stub']['transients'][ $key ] = array( $value, $ttl ? time() + $ttl : 0 );
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['xdwp_stub']['transients'][ $key ] );
	return true;
}

function wp_cache_get( $key, $group = '' ) {
	return false;
}
function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
	return true;
}

// --- hooks ----------------------------------------------------------------

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['xdwp_stub']['filters'][ $hook ][] = $callback;
	return true;
}

function remove_filter( $hook, $callback, $priority = 10 ) {
	if ( ! isset( $GLOBALS['xdwp_stub']['filters'][ $hook ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['xdwp_stub']['filters'][ $hook ] as $index => $registered ) {
		if ( $registered === $callback ) {
			unset( $GLOBALS['xdwp_stub']['filters'][ $hook ][ $index ] );
			return true;
		}
	}
	return false;
}

function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 2 );
	foreach ( isset( $GLOBALS['xdwp_stub']['filters'][ $hook ] ) ? $GLOBALS['xdwp_stub']['filters'][ $hook ] : array() as $callback ) {
		$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
	}
	return $value;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}

function do_action( $hook ) {
	$GLOBALS['xdwp_stub']['actions'][] = $hook;
}

function did_action( $hook ) {
	return 0;
}

// --- HTTP -----------------------------------------------------------------

/**
 * Serve a recorded response for any URL a check_* function asks for.
 *
 * A URL with no recorded response returns a WP_Error, which is what a chain being unreachable
 * looks like — so a test that forgets a fixture fails closed instead of passing by accident.
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['xdwp_stub']['requests'][] = $url;
	foreach ( $GLOBALS['xdwp_stub']['http'] as $fragment => $body ) {
		if ( false !== strpos( $url, $fragment ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			);
		}
	}
	return new WP_Error( 'no_fixture', 'No recorded response for ' . $url );
}

function wp_remote_post( $url, $args = array() ) {
	return wp_remote_get( $url, $args );
}

function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
}

function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

// --- odds and ends --------------------------------------------------------

function __( $text, $domain = '' ) {
	return $text;
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_attr( $text ) {
	return esc_html( $text );
}
function esc_url_raw( $url ) {
	return $url;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
function wp_rand( $min = 0, $max = 0 ) {
	return random_int( $min, $max ? $max : PHP_INT_MAX );
}
function add_query_arg( $args, $url = '' ) {
	if ( ! is_array( $args ) ) {
		$args = array( $args => func_get_arg( 1 ) );
		$url  = func_num_args() > 2 ? func_get_arg( 2 ) : '';
	}
	$join = ( false === strpos( $url, '?' ) ) ? '?' : '&';
	return $url . $join . http_build_query( $args );
}
function rawurlencode_deep( $value ) {
	return rawurlencode( $value );
}
function get_bloginfo( $what = '' ) {
	return 'test';
}
function wc_get_logger() {
	return null;
}
function function_exists_wc() {
	return false;
}
function wc_get_orders( $args = array() ) {
	return array();
}
function wc_get_order( $id ) {
	return false;
}
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}
function wp_list_pluck( $list, $field ) {
	return array_map( function ( $row ) use ( $field ) { return is_array( $row ) ? $row[ $field ] : $row->$field; }, $list );
}
function current_time( $type = 'timestamp' ) {
	return time();
}

/**
 * Serve these recorded responses to the next lookup, and forget earlier ones.
 *
 * @param array $map URL fragment => decoded body.
 */
function xdwp_stub_http( array $map ) {
	$GLOBALS['xdwp_stub']['http']     = $map;
	$GLOBALS['xdwp_stub']['requests'] = array();
}

/**
 * Load a recorded explorer response from tests/fixtures.
 *
 * @param string $name File name without .json.
 * @return array
 */
function xdwp_fixture( $name ) {
	$path = __DIR__ . '/fixtures/' . $name . '.json';
	if ( ! file_exists( $path ) ) {
		throw new RuntimeException( 'Missing fixture: ' . $name );
	}
	// Recorded responses keep their shape but not their timestamps: a payment from 2024 would
	// fall outside every payment window, so the times are filled in when the fixture is read.
	$raw = file_get_contents( $path );
	$raw = str_replace(
		array( '"@RECENT@"', '"@ISO_RECENT@"', '"@MS_RECENT@"', '"@NOW@"', '"@RIPPLE_RECENT@"' ),
		array(
			(string) ( time() - 120 ),
			'"' . gmdate( 'c', time() - 120 ) . '"',
			(string) ( ( time() - 120 ) * 1000 ),
			(string) time(),
			// The XRP Ledger counts from 2000-01-01, not 1970.
			(string) ( time() - 120 - 946684800 ),
		),
		$raw
	);
	return json_decode( $raw, true );
}
