<?php
/**
 * Taking the settings out, and putting them back.
 *
 * A shop that has chosen coins, pasted extended public keys, set limits and confirmations has a
 * configuration worth an hour of anyone's time. It should be possible to move that to a staging
 * site, to a rebuilt server, or to the shop next door — without doing it all again from memory.
 *
 * Nothing secret leaves unless the merchant asks for it. API keys are the only secrets here —
 * there is never a private key in these settings — and they are held back by default.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings export and import.
 */
class Xdwp_Backup {

	/**
	 * What the file says it is, so a restore can tell one of ours from any other JSON.
	 */
	const FORMAT = 'xorro-direct-wallet-payments-settings';

	/**
	 * The current shape of that file. Bumped only if a restore would need to do more than
	 * hand the contents to the usual sanitiser.
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Settings that are secrets. Left out of an export unless asked for.
	 *
	 * @return array<int, string>
	 */
	public static function secret_keys() {
		return array(
			'blockchair_api_key',
			'coingecko_api_key',
			'etherscan_api_key',
			'trongrid_api_key',
			'helius_api_key',
			'aptos_api_key',
			'webhook_secret',
			'telegram_token',
		);
	}

	/**
	 * Register the two admin-post endpoints.
	 */
	public static function init() {
		add_action( 'admin_post_xdwp_export_settings', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_xdwp_import_settings', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * The settings, ready to be written to a file.
	 *
	 * @param bool $with_secrets Include API keys and tokens.
	 * @return array<string, mixed>
	 */
	public static function payload( $with_secrets = false ) {
		$settings = Xdwp_Settings::all();
		if ( ! $with_secrets ) {
			foreach ( self::secret_keys() as $key ) {
				unset( $settings[ $key ] );
			}
		}

		return array(
			'format'    => self::FORMAT,
			'version'   => self::FORMAT_VERSION,
			'plugin'    => XDWP_VERSION,
			'site'      => home_url(),
			'created'   => gmdate( 'c' ),
			'secrets'   => (bool) $with_secrets,
			'settings'  => $settings,
		);
	}

	/**
	 * Send the settings to the browser as a file.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}
		check_admin_referer( 'xdwp_export_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked immediately above.
		$with_secrets = isset( $_REQUEST['secrets'] ) && '1' === sanitize_text_field( wp_unslash( $_REQUEST['secrets'] ) );
		$json         = wp_json_encode( self::payload( $with_secrets ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$name         = 'xorro-wallet-settings-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		// The body is the shop's own settings, not markup — make sure no browser decides to
		// render it as something else.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( (string) $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON body, not markup.
		exit;
	}

	/**
	 * Read an uploaded file back into the settings.
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}
		check_admin_referer( 'xdwp_import_settings' );

		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a file, read below.
		$file = isset( $_FILES['xdwp_settings_file'] ) ? $_FILES['xdwp_settings_file'] : null;
		if ( ! is_array( $file ) || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'xdwp_restore', 'nofile', $back ) );
			exit;
		}
		// A settings file is a few kilobytes; anything much larger is not one.
		if ( isset( $file['size'] ) && (int) $file['size'] > 2 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'xdwp_restore', 'toobig', $back ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an upload, not a remote resource.
		$raw    = file_get_contents( $file['tmp_name'] );
		$result = self::restore( is_string( $raw ) ? $raw : '' );

		wp_safe_redirect( add_query_arg( 'xdwp_restore', $result, $back ) );
		exit;
	}

	/**
	 * Put a settings file back, through the same sanitiser the settings form uses.
	 *
	 * Nothing is trusted because it came from a file: every value goes through
	 * Xdwp_Settings::sanitize(), which is what rejects an invalid extended key or a confirmation
	 * count a chain cannot honour. A file from an older plugin simply carries fewer keys.
	 *
	 * @param string $raw File contents.
	 * @return string One of: done, notours, badjson, empty.
	 */
	public static function restore( $raw ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return 'badjson';
		}
		if ( ! isset( $data['format'] ) || self::FORMAT !== $data['format'] ) {
			return 'notours';
		}
		if ( empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return 'empty';
		}

		$incoming = $data['settings'];

		// A file exported without secrets must not wipe the keys this site already has.
		foreach ( self::secret_keys() as $key ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				unset( $incoming[ $key ] );
			}
		}

		Xdwp_Settings::update( Xdwp_Settings::sanitize( $incoming ) );
		return 'done';
	}

	/**
	 * What to tell the merchant after a restore.
	 *
	 * @param string $code Result code from restore().
	 * @return string
	 */
	public static function message( $code ) {
		switch ( $code ) {
			case 'done':
				return __( 'Settings restored. Check your wallet addresses before taking a payment.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'notours':
				return __( 'That file is not a Xorro Wallet Payments settings file.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'badjson':
				return __( 'That file could not be read. Upload the .json file exactly as it was downloaded.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'empty':
				return __( 'That file has no settings in it.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'toobig':
				return __( 'That file is too large to be a settings file.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'nofile':
				return __( 'Choose a settings file first.', 'xorro-direct-wallet-payments-woocommerce' );
		}
		return '';
	}
}
