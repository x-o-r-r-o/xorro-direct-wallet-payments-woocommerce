<?php
/**
 * GitHub Releases auto-updater for WordPress.
 *
 * Uses the Update URI header + update_plugins_github.com filter so Dashboard
 * and Enable auto-updates work against published release ZIPs.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Updater
 */
class Xdwp_Updater {

	const REPO          = 'x-o-r-r-o/xorro-direct-wallet-payments-woocommerce';
	const CACHE_KEY     = 'xdwp_github_latest_release';
	const CACHE_TTL     = 12 * HOUR_IN_SECONDS;
	const UPDATE_HOST   = 'github.com';
	const ASSET_PREFIX  = 'xorro-direct-wallet-payments-woocommerce';

	/**
	 * Hook into WordPress update APIs.
	 */
	public static function init() {
		add_filter( 'update_plugins_' . self::UPDATE_HOST, array( __CLASS__, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 20, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_transient' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
	}

	/**
	 * Provide update payload for Update URI hostname github.com.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Requested locales.
	 * @return array|false|null
	 */
	public static function filter_update( $update, $plugin_data, $plugin_file, $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( XDWP_BASENAME !== $plugin_file ) {
			return $update;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return false;
		}

		$new_version = $release['version'];
		$installed   = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : XDWP_VERSION;

		if ( version_compare( $installed, $new_version, '>=' ) ) {
			return false;
		}

		return array(
			'slug'    => dirname( XDWP_BASENAME ),
			'version' => $new_version,
			'url'     => $release['html_url'],
			'package' => $release['package'],
			'tested'  => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : XDWP_MIN_PHP,
		);
	}

	/**
	 * Inject update / no_update entries so Dashboard + Enable auto-updates work.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( empty( $transient->checked ) || ! isset( $transient->checked[ XDWP_BASENAME ] ) ) {
			return $transient;
		}

		$installed = (string) $transient->checked[ XDWP_BASENAME ];
		$release   = self::get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => XDWP_BASENAME,
			'slug'          => dirname( XDWP_BASENAME ),
			'plugin'        => XDWP_BASENAME,
			'new_version'   => $release['version'],
			'url'           => $release['html_url'],
			'package'       => $release['package'],
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '',
			'requires_php'  => XDWP_MIN_PHP,
			'compatibility' => new stdClass(),
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( version_compare( $installed, $release['version'], '<' ) ) {
			$transient->response[ XDWP_BASENAME ] = $item;
			unset( $transient->no_update[ XDWP_BASENAME ] );
		} else {
			$transient->no_update[ XDWP_BASENAME ] = $item;
			unset( $transient->response[ XDWP_BASENAME ] );
		}

		return $transient;
	}

	/**
	 * Plugin information modal (View details).
	 *
	 * @param false|object|array $result Response.
	 * @param string             $action Action name.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = dirname( XDWP_BASENAME );
		if ( empty( $args->slug ) || $slug !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		$version = $release ? $release['version'] : XDWP_VERSION;
		$package = $release ? $release['package'] : '';
		$notes   = $release && ! empty( $release['body'] ) ? $release['body'] : '';

		return (object) array(
			'name'          => 'Xorro Direct Wallet Payments for WooCommerce',
			'slug'          => $slug,
			'version'       => $version,
			'author'        => '<a href="https://github.com/x-o-r-r-o">xorro</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => XDWP_MIN_WP,
			'requires_php'  => XDWP_MIN_PHP,
			'downloaded'    => 0,
			'last_updated'  => $release ? $release['published_at'] : '',
			'sections'      => array(
				'description' => __( 'Accept cryptocurrency payments directly to your own wallets — updates are delivered from GitHub Releases.', 'xorro-direct-wallet-payments-woocommerce' ),
				'changelog'   => $notes
					? '<pre style="white-space:pre-wrap;font-family:inherit;">' . esc_html( $notes ) . '</pre>'
					: esc_html__( 'See GitHub Releases for changelog details.', 'xorro-direct-wallet-payments-woocommerce' ),
			),
			'download_link' => $package,
			'banners'       => array(),
			'icons'         => array(),
		);
	}

	/**
	 * Clear cached release after this plugin is updated.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Result options.
	 */
	public static function clear_cache_after_upgrade( $upgrader, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
		if ( in_array( XDWP_BASENAME, $plugins, true ) ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Fetch and cache the latest published GitHub release with an installable ZIP.
	 *
	 * @return array{version:string,package:string,html_url:string,body:string,published_at:string}|null
	 */
	private static function get_latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['package'] ) ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'headers'    => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
				/**
				 * Allow hosts that block api.github.com via HTTP API filters to still fail closed.
				 */
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		if ( ! empty( $body['draft'] ) || ! empty( $body['prerelease'] ) ) {
			return null;
		}

		$version = ltrim( (string) $body['tag_name'], "vV" );
		if ( '' === $version || ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
			return null;
		}

		$package = self::pick_zip_asset( isset( $body['assets'] ) && is_array( $body['assets'] ) ? $body['assets'] : array(), $version );
		if ( ! $package ) {
			return null;
		}

		$data = array(
			'version'      => $version,
			'package'      => $package,
			'html_url'     => isset( $body['html_url'] ) ? (string) $body['html_url'] : ( 'https://github.com/' . self::REPO . '/releases' ),
			'body'         => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published_at' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Choose the WordPress install ZIP from release assets.
	 *
	 * Prefers xorro-direct-wallet-payments-woocommerce-{version}.zip, then the unversioned zip.
	 *
	 * @param array  $assets  GitHub assets.
	 * @param string $version Release version.
	 * @return string Empty if none.
	 */
	private static function pick_zip_asset( array $assets, $version ) {
		$preferred = self::ASSET_PREFIX . '-' . $version . '.zip';
		$fallback  = self::ASSET_PREFIX . '.zip';
		$found     = array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( '' === $name || '' === $url ) {
				continue;
			}
			if ( '.sha256' === substr( $name, -7 ) ) {
				continue;
			}
			if ( '.zip' !== substr( $name, -4 ) ) {
				continue;
			}
			$found[ $name ] = $url;
		}

		if ( isset( $found[ $preferred ] ) ) {
			return $found[ $preferred ];
		}
		if ( isset( $found[ $fallback ] ) ) {
			return $found[ $fallback ];
		}

		// Last resort: first zip whose name starts with the plugin slug.
		foreach ( $found as $name => $url ) {
			if ( 0 === strpos( $name, self::ASSET_PREFIX ) ) {
				return $url;
			}
		}

		return '';
	}
}
