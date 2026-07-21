<?php
/**
 * GitHub Releases auto-updater for WordPress.
 *
 * Uses the Update URI header + update_plugins_github.com filter so Dashboard
 * and Enable auto-updates work against published release ZIPs.
 * Downloads are host-allowlisted and verified against the release SHA-256 asset.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Updater
 */
class Xdwp_Updater {

	const REPO         = 'x-o-r-r-o/xorro-direct-wallet-payments-woocommerce';
	const CACHE_KEY    = 'xdwp_github_latest_release';
	const CACHE_TTL    = 12 * HOUR_IN_SECONDS;
	const UPDATE_HOST  = 'github.com';
	const ASSET_PREFIX = 'xorro-direct-wallet-payments-woocommerce';

	/**
	 * Hook into WordPress update APIs.
	 */
	public static function init() {
		add_filter( 'update_plugins_' . self::UPDATE_HOST, array( __CLASS__, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 20, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_transient' ) );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_and_download' ), 10, 3 );
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
			'slug'         => dirname( XDWP_BASENAME ),
			'version'      => $new_version,
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'tested'       => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
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
	 * Download our package only from allowlisted hosts and verify SHA-256 when available.
	 *
	 * @param bool|WP_Error $reply    Short-circuit value.
	 * @param string        $package  Package URL.
	 * @param WP_Upgrader   $upgrader Upgrader.
	 * @return bool|string|WP_Error Local file path on success.
	 */
	public static function verify_and_download( $reply, $package, $upgrader ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( false !== $reply ) {
			return $reply;
		}

		$release = self::get_latest_release();
		$ours    = $release && ! empty( $release['package'] ) && hash_equals( (string) $release['package'], (string) $package );
		if ( ! $ours && ! self::is_our_package_url( $package ) ) {
			return $reply;
		}

		if ( ! self::is_allowed_package_url( $package ) ) {
			return new WP_Error(
				'xdwp_bad_package_host',
				__( 'Refused plugin update: package host is not an allowlisted GitHub download host.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = download_url( $package, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$expected = ( $release && ! empty( $release['sha256'] ) ) ? strtolower( (string) $release['sha256'] ) : '';
		if ( '' === $expected ) {
			$cached_sha = get_transient( 'xdwp_pkg_sha_' . md5( (string) $package ) );
			if ( is_string( $cached_sha ) && '' !== $cached_sha ) {
				$expected = strtolower( $cached_sha );
			}
		}
		// Fail closed: never install our package without a verified digest.
		if ( '' === $expected ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error(
				'xdwp_missing_checksum',
				__( 'Refused plugin update: release SHA-256 checksum is unavailable.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		$actual = strtolower( (string) hash_file( 'sha256', $tmp ) );
		if ( ! $actual || ! hash_equals( $expected, $actual ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error(
				'xdwp_hash_mismatch',
				__( 'Refused plugin update: ZIP SHA-256 does not match the GitHub release checksum.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		return $tmp;
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
	 * @return array{version:string,package:string,sha256:string,html_url:string,body:string,published_at:string}|null
	 */
	private static function get_latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['package'] ) && ! empty( $cached['sha256'] ) ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
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

		$version = ltrim( (string) $body['tag_name'], 'vV' );
		if ( '' === $version || ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
			return null;
		}

		$assets = isset( $body['assets'] ) && is_array( $body['assets'] ) ? $body['assets'] : array();
		$picked = self::pick_zip_asset( $assets, $version );
		if ( empty( $picked['package'] ) || ! self::is_allowed_package_url( $picked['package'] ) ) {
			return null;
		}

		$sha256 = '';
		if ( ! empty( $picked['sha256_url'] ) && self::is_allowed_package_url( $picked['sha256_url'] ) ) {
			$sha256 = self::fetch_sha256( $picked['sha256_url'] );
		}
		if ( '' === $sha256 ) {
			return null;
		}

		$data = array(
			'version'      => $version,
			'package'      => $picked['package'],
			'sha256'       => $sha256,
			'html_url'     => isset( $body['html_url'] ) ? (string) $body['html_url'] : ( 'https://github.com/' . self::REPO . '/releases' ),
			'body'         => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published_at' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		// Longer-lived checksum keyed by package URL so downloads stay verifiable if the release cache expires.
		set_transient( 'xdwp_pkg_sha_' . md5( $data['package'] ), $data['sha256'], WEEK_IN_SECONDS );

		return $data;
	}

	/**
	 * Choose ZIP + checksum URLs from release assets.
	 *
	 * @param array  $assets  GitHub assets.
	 * @param string $version Release version.
	 * @return array{package:string,sha256_url:string}
	 */
	private static function pick_zip_asset( array $assets, $version ) {
		$preferred = self::ASSET_PREFIX . '-' . $version . '.zip';
		$fallback  = self::ASSET_PREFIX . '.zip';
		$zips      = array();
		$sums      = array();

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
				$sums[ $name ] = $url;
				continue;
			}
			if ( '.zip' === substr( $name, -4 ) ) {
				$zips[ $name ] = $url;
			}
		}

		$package = '';
		if ( isset( $zips[ $preferred ] ) ) {
			$package = $zips[ $preferred ];
		} elseif ( isset( $zips[ $fallback ] ) ) {
			$package = $zips[ $fallback ];
			$preferred = $fallback;
		} else {
			foreach ( $zips as $name => $url ) {
				if ( 0 === strpos( $name, self::ASSET_PREFIX ) ) {
					$package   = $url;
					$preferred = $name;
					break;
				}
			}
		}

		$sha_name = $preferred . '.sha256';
		$sha_url  = isset( $sums[ $sha_name ] ) ? $sums[ $sha_name ] : '';

		return array(
			'package'    => $package,
			'sha256_url' => $sha_url,
		);
	}

	/**
	 * Download and parse a shasum -a 256 checksum file.
	 *
	 * @param string $url Checksum asset URL.
	 * @return string Lowercase hex digest or empty.
	 */
	private static function fetch_sha256( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'Xdwp/' . XDWP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( '' === $body ) {
			return '';
		}
		// Formats: "<hash>  <filename>" or bare "<hash>".
		if ( preg_match( '/\b([a-fA-F0-9]{64})\b/', $body, $m ) ) {
			return strtolower( $m[1] );
		}
		return '';
	}

	/**
	 * Whether a URL is a GitHub release download for this plugin.
	 *
	 * @param string $url Package URL.
	 * @return bool
	 */
	private static function is_our_package_url( $url ) {
		if ( ! self::is_allowed_package_url( $url ) ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		// Only GitHub Releases download ZIPs for this plugin — not arbitrary paths containing the repo slug.
		return (bool) preg_match(
			'#^/' . preg_quote( self::REPO, '#' ) . '/releases/download/[^/]+/' . preg_quote( self::ASSET_PREFIX, '#' ) . '(-[0-9.]+)?\\.zip$#',
			$path
		);
	}

	/**
	 * Allow only GitHub download hosts.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_allowed_package_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$ok   = array(
			'github.com',
			'objects.githubusercontent.com',
			'release-assets.githubusercontent.com',
		);
		return in_array( $host, $ok, true );
	}
}
