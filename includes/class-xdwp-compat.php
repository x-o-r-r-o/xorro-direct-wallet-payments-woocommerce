<?php
/**
 * Compatibility with performance / optimization plugins.
 *
 * "Delay JavaScript until user interaction" and similar options hold back every script
 * until the visitor moves the mouse or touches the screen. On the payment page that means
 * no QR code, no countdown and no live payment detection — a customer who opens their
 * wallet app straight away sees what looks like a broken page. Payment scripts are small
 * and only load on checkout / payment pages, so they opt out of delay, defer, combine and
 * minify rewriting, the same way hosted payment gateways do.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Compat
 */
class Xdwp_Compat {

	/**
	 * Payment-page scripts. Only scripts with no third-party dependencies may opt out:
	 * the checkout scripts depend on jQuery / WooCommerce Blocks, and running them ahead
	 * of dependencies an optimizer is still delaying would break the coin picker.
	 */
	const HANDLES = array( 'xdwp-qrcode', 'xdwp-frontend' );

	/** Substrings optimizers can match against script URLs or inline script content. */
	const KEYWORDS = array(
		'xorro-direct-wallet-payments-woocommerce/assets/js/qrcode.min.js',
		'xorro-direct-wallet-payments-woocommerce/assets/js/frontend.js',
		'xdwpData',
	);

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Generic attributes honoured by LiteSpeed Cache, SiteGround Optimizer, WP Rocket,
		// Jetpack Boost, Hummingbird and Cloudflare Rocket Loader.
		add_filter( 'script_loader_tag', array( __CLASS__, 'tag_attributes' ), 10, 2 );
		add_filter( 'wp_inline_script_attributes', array( __CLASS__, 'inline_attributes' ), 10, 2 );

		// Plugin-specific exclusion lists.
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'rocket_exclude_defer_js', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'rocket_exclude_js', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'perfmatters_delay_js_exclusions', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'perfmatters_defer_js_exclusions', array( __CLASS__, 'add_keywords' ) );
		add_filter( 'sgo_js_async_exclude', array( __CLASS__, 'add_handles' ) );
		add_filter( 'sgo_javascript_combine_exclude', array( __CLASS__, 'add_handles' ) );
		add_filter( 'sgo_js_minify_exclude', array( __CLASS__, 'add_handles' ) );
		// Keep checkout scripts out of Autoptimize's single combined bundle: one failing script
		// from another plugin earlier in that bundle stops everything after it, including the
		// coin picker. Excluded files keep their normal place and order (only combining is
		// skipped), so this carries none of the dependency risk of opting out of delay/defer.
		add_filter( 'autoptimize_filter_js_exclude', array( __CLASS__, 'autoptimize_exclude' ) );
	}

	/**
	 * Add opt-out attributes to this plugin's external script tags.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function tag_attributes( $tag, $handle ) {
		if ( ! in_array( $handle, self::HANDLES, true ) || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}
		return preg_replace(
			'/<script(?=[\s>])/',
			'<script data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-cfasync="false"',
			$tag
		);
	}

	/**
	 * Same attributes on the inline data (wp_localize_script / before / after) of our handles.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $data       Inline script content.
	 * @return array
	 */
	public static function inline_attributes( $attributes, $data = '' ) {
		$id = isset( $attributes['id'] ) ? (string) $attributes['id'] : '';
		foreach ( self::HANDLES as $handle ) {
			if ( 0 === strpos( $id, $handle . '-js' ) ) {
				$attributes['data-no-optimize'] = '1';
				$attributes['data-no-defer']    = '1';
				$attributes['data-no-minify']   = '1';
				$attributes['data-cfasync']     = 'false';
				break;
			}
		}
		return $attributes;
	}

	/**
	 * Append keyword exclusions to an optimizer's list.
	 *
	 * @param mixed $list Existing exclusions.
	 * @return array
	 */
	public static function add_keywords( $list ) {
		$list = is_array( $list ) ? $list : array();
		return array_values( array_unique( array_merge( $list, self::KEYWORDS ) ) );
	}

	/**
	 * Append handle exclusions (SiteGround Optimizer).
	 *
	 * @param mixed $list Existing exclusions.
	 * @return array
	 */
	public static function add_handles( $list ) {
		$list = is_array( $list ) ? $list : array();
		return array_values( array_unique( array_merge( $list, self::HANDLES ) ) );
	}

	/**
	 * Autoptimize takes a comma-separated string.
	 *
	 * @param string $exclude Existing exclusions.
	 * @return string
	 */
	public static function autoptimize_exclude( $exclude ) {
		$exclude = trim( (string) $exclude );
		$keywords = array_merge(
			self::KEYWORDS,
			array( 'xorro-direct-wallet-payments-woocommerce/assets/js/checkout.js', 'var xdwp =' )
		);
		return ( '' === $exclude ? '' : rtrim( $exclude, ', ' ) . ', ' ) . implode( ', ', $keywords );
	}
}
