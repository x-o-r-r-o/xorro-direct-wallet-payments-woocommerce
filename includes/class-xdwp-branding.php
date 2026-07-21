<?php
/**
 * Checkout branding helpers (icon / title display).
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Branding
 */
class Xdwp_Branding {

	const DEFAULT_WIDTH  = 32;
	const DEFAULT_HEIGHT = 32;
	const MIN_SIZE       = 16;
	const MAX_SIZE       = 128;

	/**
	 * Display modes.
	 *
	 * @return array<string, string>
	 */
	public static function display_modes() {
		return array(
			'both' => __( 'Icon and text', 'xorro-direct-wallet-payments-woocommerce' ),
			'icon' => __( 'Icon only', 'xorro-direct-wallet-payments-woocommerce' ),
			'text' => __( 'Text only', 'xorro-direct-wallet-payments-woocommerce' ),
		);
	}

	/**
	 * Current display mode.
	 *
	 * @return string both|icon|text
	 */
	public static function display_mode() {
		$mode = Xdwp_Settings::get( 'checkout_display', 'both' );
		$mode = is_string( $mode ) ? sanitize_key( $mode ) : 'both';
		return isset( self::display_modes()[ $mode ] ) ? $mode : 'both';
	}

	/**
	 * Checkout title text.
	 *
	 * @return string
	 */
	public static function title() {
		$title = Xdwp_Settings::get( 'title', __( 'Pay with Cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' ) );
		$title = is_string( $title ) ? trim( $title ) : '';
		return '' !== $title ? $title : __( 'Pay with Cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' );
	}

	/**
	 * Icon width in pixels.
	 *
	 * @return int
	 */
	public static function icon_width() {
		return self::clamp_size( (int) Xdwp_Settings::get( 'checkout_icon_width', self::DEFAULT_WIDTH ) );
	}

	/**
	 * Icon height in pixels.
	 *
	 * @return int
	 */
	public static function icon_height() {
		return self::clamp_size( (int) Xdwp_Settings::get( 'checkout_icon_height', self::DEFAULT_HEIGHT ) );
	}

	/**
	 * Clamp icon dimension.
	 *
	 * @param int $size Size.
	 * @return int
	 */
	public static function clamp_size( $size ) {
		$size = absint( $size );
		if ( $size < self::MIN_SIZE ) {
			return self::DEFAULT_WIDTH;
		}
		if ( $size > self::MAX_SIZE ) {
			return self::MAX_SIZE;
		}
		return $size;
	}

	/**
	 * Default bundled icon URL.
	 *
	 * @return string
	 */
	public static function default_icon_url() {
		return XDWP_URL . 'assets/images/xdwp-icon.svg';
	}

	/**
	 * Resolved icon URL (empty when text-only or missing).
	 *
	 * @return string
	 */
	public static function icon_url() {
		if ( 'text' === self::display_mode() ) {
			return '';
		}

		$attachment_id = absint( Xdwp_Settings::get( 'checkout_icon_id', 0 ) );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return self::default_icon_url();
	}

	/**
	 * HTML for classic checkout gateway icon.
	 *
	 * @return string
	 */
	public static function get_icon_html() {
		$url = self::icon_url();
		if ( '' === $url ) {
			return '';
		}

		$w     = self::icon_width();
		$h     = self::icon_height();
		$title = self::title();

		return sprintf(
			'<img src="%1$s" alt="%2$s" class="xdwp-gateway-icon" width="%3$d" height="%4$d" style="width:%3$dpx;height:%4$dpx;max-width:%3$dpx;max-height:%4$dpx;object-fit:contain;vertical-align:middle;" />',
			esc_url( $url ),
			esc_attr( $title ),
			$w,
			$h
		);
	}

	/**
	 * Data for Blocks / JS.
	 *
	 * @return array<string, mixed>
	 */
	public static function frontend_data() {
		return array(
			'title'       => self::title(),
			'display'     => self::display_mode(),
			'icon'        => self::icon_url(),
			'iconWidth'   => self::icon_width(),
			'iconHeight'  => self::icon_height(),
		);
	}

	/**
	 * Sanitize branding fields from admin input into $clean.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param array<string, mixed> $clean Clean settings (by ref conceptually — return merged).
	 * @return array<string, mixed>
	 */
	public static function sanitize_from_input( array $input, array $clean ) {
		if ( isset( $input['title'] ) ) {
			$clean['title'] = sanitize_text_field( wp_unslash( $input['title'] ) );
		}

		if ( isset( $input['checkout_display'] ) ) {
			$mode = sanitize_key( wp_unslash( (string) $input['checkout_display'] ) );
			$clean['checkout_display'] = isset( self::display_modes()[ $mode ] ) ? $mode : 'both';
		}

		if ( isset( $input['checkout_icon_width'] ) ) {
			$clean['checkout_icon_width'] = self::clamp_size( (int) $input['checkout_icon_width'] );
		}

		if ( isset( $input['checkout_icon_height'] ) ) {
			$clean['checkout_icon_height'] = self::clamp_size( (int) $input['checkout_icon_height'] );
		}

		if ( array_key_exists( 'checkout_icon_id', $input ) ) {
			$id = absint( $input['checkout_icon_id'] );
			if ( $id > 0 ) {
				$mime = get_post_mime_type( $id );
				$ok   = is_string( $mime ) && 0 === strpos( $mime, 'image/' );
				// Prefer raster; allow svg+xml only as image attachment (rendered via <img>, not inline).
				if ( $ok && get_post_type( $id ) === 'attachment' ) {
					$clean['checkout_icon_id'] = $id;
				} else {
					$clean['checkout_icon_id'] = 0;
				}
			} else {
				$clean['checkout_icon_id'] = 0;
			}
		}

		return $clean;
	}
}
