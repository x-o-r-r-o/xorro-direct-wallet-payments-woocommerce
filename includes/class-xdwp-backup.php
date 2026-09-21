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
	 * Everything: coins, wallets, keys, limits, confirmations, prices, alerts.
	 */
	const SCOPE_ALL = 'all';

	/**
	 * Where the money goes: receiving addresses and extended public keys, nothing else.
	 */
	const SCOPE_WALLETS = 'wallets';

	/**
	 * The service credentials, and only those.
	 */
	const SCOPE_KEYS = 'keys';

	/**
	 * The parts of the configuration that can be carried on their own.
	 *
	 * Moving a shop to a new server wants everything. Pointing a staging site at the same
	 * wallets wants the addresses without the shop's own limits and alerts. Handing a
	 * developer the API keys — or taking them back out — wants neither.
	 *
	 * @return array<string, string> Scope => label.
	 */
	public static function scopes() {
		return array(
			self::SCOPE_ALL     => __( 'Everything', 'xorro-direct-wallet-payments-woocommerce' ),
			self::SCOPE_WALLETS => __( 'Wallet addresses and extended keys only', 'xorro-direct-wallet-payments-woocommerce' ),
			self::SCOPE_KEYS    => __( 'API keys and tokens only', 'xorro-direct-wallet-payments-woocommerce' ),
		);
	}

	/**
	 * A scope name that is certainly one of ours.
	 *
	 * @param mixed $scope Requested scope.
	 * @return string
	 */
	public static function scope( $scope ) {
		$scope = is_scalar( $scope ) ? (string) $scope : '';
		return array_key_exists( $scope, self::scopes() ) ? $scope : self::SCOPE_ALL;
	}

	/**
	 * Settings that say where money should be sent.
	 *
	 * @return array<int, string>
	 */
	public static function wallet_keys() {
		return array( 'wallets', 'xpubs' );
	}

	/**
	 * Settings that are secrets. Left out of an export unless asked for.
	 *
	 * @return array<int, string>
	 */
	public static function secret_keys() {
		return array(
			'blockchair_api_key',
			'kaiascan_api_key',
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
	public static function payload( $with_secrets = false, $scope = self::SCOPE_ALL ) {
		$scope    = self::scope( $scope );
		$settings = Xdwp_Settings::all();

		if ( self::SCOPE_WALLETS === $scope ) {
			$settings = array_intersect_key( $settings, array_flip( self::wallet_keys() ) );
			// A wallets file holds no credentials by definition, whatever was ticked.
			$with_secrets = false;
		} elseif ( self::SCOPE_KEYS === $scope ) {
			$settings = array_intersect_key( $settings, array_flip( self::secret_keys() ) );
			// Asking for the keys and then holding them back would hand over an empty file.
			$with_secrets = true;
		}

		if ( ! $with_secrets ) {
			foreach ( self::secret_keys() as $key ) {
				unset( $settings[ $key ] );
			}
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::FORMAT_VERSION,
			'plugin'   => XDWP_VERSION,
			'site'     => home_url(),
			'created'  => gmdate( 'c' ),
			'scope'    => $scope,
			'secrets'  => (bool) $with_secrets,
			'settings' => $settings,
		);
	}

	/**
	 * What to call the downloaded file, so a folder of them can be told apart.
	 *
	 * @param string $scope Scope.
	 * @return string
	 */
	public static function filename( $scope ) {
		$suffix = array(
			self::SCOPE_ALL     => 'settings',
			self::SCOPE_WALLETS => 'wallets',
			self::SCOPE_KEYS    => 'api-keys',
		);
		$scope = self::scope( $scope );
		return 'xorro-wallet-' . $suffix[ $scope ] . '-' . gmdate( 'Y-m-d' ) . '.json';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked immediately above.
		$scope = self::scope( isset( $_REQUEST['scope'] ) ? sanitize_key( wp_unslash( $_REQUEST['scope'] ) ) : self::SCOPE_ALL );
		$json  = wp_json_encode( self::payload( $with_secrets, $scope ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$name  = self::filename( $scope );

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

		// A file exported without secrets — or a wallets-only file — carries fewer keys than a
		// full one. Nothing extra is needed to protect what is missing: Xdwp_Settings::sanitize()
		// starts from the settings this site already has and only overwrites keys the file
		// actually contains, so anything left out is kept rather than blanked.
		Xdwp_Settings::update( Xdwp_Settings::sanitize( $incoming ) );

		$scope = self::scope( isset( $data['scope'] ) ? $data['scope'] : self::SCOPE_ALL );
		if ( self::SCOPE_WALLETS === $scope ) {
			return 'done_wallets';
		}
		if ( self::SCOPE_KEYS === $scope ) {
			return 'done_keys';
		}
		return 'done';
	}

	/**
	 * Whether a result code means the restore worked.
	 *
	 * @param string $code Result code from restore().
	 * @return bool
	 */
	public static function is_success( $code ) {
		return 0 === strpos( (string) $code, 'done' );
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
			case 'done_wallets':
				return __( 'Wallet addresses and extended keys restored. Everything else on this site was left as it was. Check the addresses on the Wallets tab before taking a payment.', 'xorro-direct-wallet-payments-woocommerce' );
			case 'done_keys':
				return __( 'API keys restored. No other setting was changed. Use "Test this coin" on the Coins tab to confirm each service answers.', 'xorro-direct-wallet-payments-woocommerce' );
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
