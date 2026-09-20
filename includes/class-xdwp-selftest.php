<?php
/**
 * "Test your setup" — prove a coin will actually work before a customer pays with it.
 *
 * Setting up a crypto gateway means typing an address you cannot verify, trusting a rate
 * source you have not seen, and hoping a scheduled task you know nothing about will run. The
 * only way most merchants find out that one of those is wrong is a customer's money going
 * somewhere they cannot reach. This runs the same steps a real payment would, without any
 * money, and names the step that fails.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Selftest
 */
class Xdwp_Selftest {

	/** A check that passed. */
	const OK = 'ok';

	/** A check that failed and will stop payments working. */
	const FAIL = 'fail';

	/** Something worth knowing that is not broken. */
	const WARN = 'warn';

	/** Not applicable to this coin. */
	const SKIP = 'skip';

	/**
	 * Run every check for one coin.
	 *
	 * @param string $coin_id Coin ID.
	 * @return array{coin:string,name:string,ok:bool,checks:array<int,array{key:string,label:string,status:string,detail:string}>}
	 */
	public static function run( $coin_id ) {
		$coin   = Xdwp_Coins::get( $coin_id );
		$checks = array();

		if ( ! $coin ) {
			return array(
				'coin'   => $coin_id,
				'name'   => $coin_id,
				'ok'     => false,
				'checks' => array( self::result( 'coin', __( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ), self::FAIL, __( 'This coin is not one the plugin knows about.', 'xorro-direct-wallet-payments-woocommerce' ) ) ),
			);
		}

		$checks[] = self::check_enabled( $coin_id );
		$address  = self::receiving_address( $coin_id );
		$checks[] = self::check_address( $coin_id, $address );
		$checks[] = self::check_rate( $coin_id );
		$checks[] = self::check_chain( $coin, $address );
		$checks[] = self::check_confirmations( $coin );

		$ok = true;
		foreach ( $checks as $check ) {
			if ( self::FAIL === $check['status'] ) {
				$ok = false;
			}
		}

		return array(
			'coin'   => $coin_id,
			'name'   => $coin['name'],
			'ok'     => $ok,
			'checks' => $checks,
		);
	}

	/**
	 * Checks that apply to the whole shop rather than one coin.
	 *
	 * @return array<int, array{key:string,label:string,status:string,detail:string}>
	 */
	public static function store_checks() {
		$checks = array();

		// The gateway being switched on in WooCommerce is separate from this plugin's settings,
		// and forgetting it is the most common "why is nothing happening" cause.
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$enabled  = isset( $gateways[ XDWP_GATEWAY_ID ] ) && 'yes' === $gateways[ XDWP_GATEWAY_ID ]->enabled;
		$checks[] = self::result(
			'gateway',
			__( 'Gateway switched on', 'xorro-direct-wallet-payments-woocommerce' ),
			$enabled ? self::OK : self::FAIL,
			$enabled
				? __( 'Customers can choose it at checkout.', 'xorro-direct-wallet-payments-woocommerce' )
				: __( 'The gateway is off in WooCommerce → Settings → Payments, so nobody can pay with it yet.', 'xorro-direct-wallet-payments-woocommerce' )
		);

		// Payments are confirmed by a scheduled task. If WordPress' scheduler is not running,
		// orders sit unpaid however well everything else is configured.
		$next = wp_next_scheduled( 'xdwp_check_payments' );
		if ( ! $next ) {
			$checks[] = self::result( 'cron', __( 'Payment checks scheduled', 'xorro-direct-wallet-payments-woocommerce' ), self::FAIL, __( 'The recurring payment check is not scheduled. Deactivating and reactivating the plugin restores it.', 'xorro-direct-wallet-payments-woocommerce' ) );
		} elseif ( $next < time() - 15 * MINUTE_IN_SECONDS ) {
			$checks[] = self::result(
				'cron',
				__( 'Payment checks scheduled', 'xorro-direct-wallet-payments-woocommerce' ),
				self::WARN,
				sprintf(
					/* translators: %s: human-readable time difference */
					__( 'The check was due %s ago and has not run. WordPress only runs scheduled tasks when someone visits the site — a quiet shop should use a real cron job on the server.', 'xorro-direct-wallet-payments-woocommerce' ),
					human_time_diff( $next )
				)
			);
		} else {
			$checks[] = self::result(
				'cron',
				__( 'Payment checks scheduled', 'xorro-direct-wallet-payments-woocommerce' ),
				self::OK,
				sprintf(
					/* translators: %s: human-readable time difference */
					__( 'Next check in %s.', 'xorro-direct-wallet-payments-woocommerce' ),
					human_time_diff( $next )
				)
			);
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$checks[] = self::result( 'wpcron', __( 'WordPress scheduler', 'xorro-direct-wallet-payments-woocommerce' ), self::WARN, __( 'WP-Cron is disabled in wp-config.php. That is fine as long as a real cron job calls wp-cron.php — if one does not, payments will never be confirmed automatically.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		// Automatic verification off is a deliberate choice, but worth saying out loud.
		if ( 'yes' !== Xdwp_Settings::get( 'auto_verify', 'yes' ) ) {
			$checks[] = self::result( 'autoverify', __( 'Automatic verification', 'xorro-direct-wallet-payments-woocommerce' ), self::WARN, __( 'Automatic verification is switched off, so every payment must be confirmed by hand on the order.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		$currency = get_woocommerce_currency();
		$rate     = Xdwp_Prices::get_rate( 'bitcoin', strtolower( $currency ), false, 'BTC' );
		$checks[] = self::result(
			'rates',
			__( 'Exchange rates', 'xorro-direct-wallet-payments-woocommerce' ),
			$rate > 0 ? self::OK : self::FAIL,
			$rate > 0
				/* translators: %s: store currency code */
				? sprintf( __( 'Rates are available in %s.', 'xorro-direct-wallet-payments-woocommerce' ), $currency )
				/* translators: %s: store currency code */
				: sprintf( __( 'No rate could be fetched in %s. Checkout will refuse to quote until this works.', 'xorro-direct-wallet-payments-woocommerce' ), $currency )
		);

		if ( ! is_ssl() ) {
			$checks[] = self::result( 'https', __( 'HTTPS', 'xorro-direct-wallet-payments-woocommerce' ), self::WARN, __( 'This site is not served over HTTPS. Copy-to-clipboard and wallet links do not work reliably without it.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		return $checks;
	}

	/**
	 * @param string $coin_id Coin ID.
	 * @return array
	 */
	private static function check_enabled( $coin_id ) {
		$enabled = Xdwp_Settings::get( 'enabled_coins', array() );
		$on      = is_array( $enabled ) && in_array( $coin_id, $enabled, true );
		return self::result(
			'enabled',
			__( 'Offered at checkout', 'xorro-direct-wallet-payments-woocommerce' ),
			$on ? self::OK : self::WARN,
			$on
				? __( 'Ticked on the Coins tab.', 'xorro-direct-wallet-payments-woocommerce' )
				: __( 'Not ticked on the Coins tab, so customers will not see it.', 'xorro-direct-wallet-payments-woocommerce' )
		);
	}

	/**
	 * The address a payment in this coin would actually be sent to.
	 *
	 * @param string $coin_id Coin ID.
	 * @return string
	 */
	private static function receiving_address( $coin_id ) {
		$key = Xdwp_Wallets::get_xpub( $coin_id );
		if ( '' !== $key ) {
			// The next index, without consuming it — a check must not use up an address.
			$index = (int) get_option( 'xdwp_hd_idx_' . sanitize_key( $coin_id ), 0 );
			return Xdwp_Hd::address( $key, $index, $coin_id );
		}
		$addresses = Xdwp_Wallets::get_addresses( $coin_id );
		return empty( $addresses ) ? '' : (string) $addresses[0];
	}

	/**
	 * @param string $coin_id Coin ID.
	 * @param string $address Address.
	 * @return array
	 */
	private static function check_address( $coin_id, $address ) {
		$key = Xdwp_Wallets::get_xpub( $coin_id );

		if ( '' === $address ) {
			return self::result(
				'address',
				__( 'Somewhere to receive', 'xorro-direct-wallet-payments-woocommerce' ),
				self::FAIL,
				'' === $key
					? __( 'No address and no extended public key are set for this coin, so an order in it cannot be created.', 'xorro-direct-wallet-payments-woocommerce' )
					: __( 'An extended public key is saved but no address could be derived from it. Check the key is the account key your wallet shows.', 'xorro-direct-wallet-payments-woocommerce' )
			);
		}

		if ( ! Xdwp_Wallets::is_plausible_address( $coin_id, $address ) ) {
			return self::result(
				'address',
				__( 'Somewhere to receive', 'xorro-direct-wallet-payments-woocommerce' ),
				self::FAIL,
				sprintf(
					/* translators: %s: receiving address */
					__( '%s does not look like an address for this network. Money sent to it would be lost.', 'xorro-direct-wallet-payments-woocommerce' ),
					$address
				)
			);
		}

		return self::result(
			'address',
			__( 'Somewhere to receive', 'xorro-direct-wallet-payments-woocommerce' ),
			self::OK,
			'' !== $key
				? sprintf(
					/* translators: %s: receiving address */
					__( 'The next order will be sent to %s. Check that address appears in your own wallet.', 'xorro-direct-wallet-payments-woocommerce' ),
					$address
				)
				: sprintf(
					/* translators: %s: receiving address */
					__( 'Payments go to %s. Only you can confirm it is yours — send a small amount to be certain.', 'xorro-direct-wallet-payments-woocommerce' ),
					$address
				)
		);
	}

	/**
	 * @param string $coin_id Coin ID.
	 * @return array
	 */
	private static function check_rate( $coin_id ) {
		$currency = get_woocommerce_currency();
		$amount   = Xdwp_Prices::fiat_to_crypto( 10.00, $coin_id, $currency, false );
		$coin     = Xdwp_Coins::get( $coin_id );

		if ( '' === $amount || (float) $amount <= 0 ) {
			return self::result(
				'rate',
				__( 'Price in this coin', 'xorro-direct-wallet-payments-woocommerce' ),
				self::FAIL,
				sprintf(
					/* translators: %s: store currency code */
					__( 'No exchange rate is available for this coin in %s, so checkout cannot quote an amount.', 'xorro-direct-wallet-payments-woocommerce' ),
					$currency
				)
			);
		}

		return self::result(
			'rate',
			__( 'Price in this coin', 'xorro-direct-wallet-payments-woocommerce' ),
			self::OK,
			sprintf(
				/* translators: 1: fiat amount, 2: crypto amount, 3: coin symbol */
				__( '%1$s converts to %2$s %3$s right now.', 'xorro-direct-wallet-payments-woocommerce' ),
				// The detail is shown as plain text, so strip wc_price()'s markup.
				wp_strip_all_tags( html_entity_decode( wc_price( 10.00 ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ),
				$amount,
				$coin ? $coin['symbol'] : $coin_id
			)
		);
	}

	/**
	 * Ask the chain about the merchant's own address, the way a real check would.
	 *
	 * @param array  $coin    Coin definition.
	 * @param string $address Address.
	 * @return array
	 */
	private static function check_chain( array $coin, $address ) {
		$label = __( 'Reading the blockchain', 'xorro-direct-wallet-payments-woocommerce' );

		if ( ! Xdwp_Coins::supports_auto_verify( $coin['id'] ) ) {
			return self::result( 'chain', $label, self::SKIP, __( 'This coin has no free way to check payments, so you confirm them yourself with "Mark payment received" on the order.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		if ( '' === $address ) {
			return self::result( 'chain', $label, self::SKIP, __( 'Nothing to look up until a receiving address is set.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		// Some chains are read through a service that will not answer without a free key. The
		// lookup then never leaves the site at all, so say that plainly rather than reporting
		// a mysterious silence.
		$missing_key = self::missing_api_key( $coin );
		if ( '' !== $missing_key ) {
			return self::result(
				'chain',
				$label,
				self::FAIL,
				sprintf(
					/* translators: %s: name of the service whose key is missing */
					__( 'Payments in this coin cannot be checked automatically until a free %s key is saved under Prices & APIs. Until then you would have to confirm each payment by hand.', 'xorro-direct-wallet-payments-woocommerce' ),
					$missing_key
				)
			);
		}

		Xdwp_Verifier::forget_last_http();
		// A band no real payment can fall into: this asks the explorer a real question about
		// the merchant's real address without any chance of matching an actual payment.
		Xdwp_Verifier::find_payment( $coin, $address, '0.00000000000000001', '0.00000000000000002', time() - 600 );
		$http = Xdwp_Verifier::last_http();

		if ( '' === $http['url'] ) {
			return self::result( 'chain', $label, self::WARN, __( 'The explorer was not contacted — a cached result was used. Try again in a minute for a live answer.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		if ( 0 === $http['code'] ) {
			return self::result(
				'chain',
				$label,
				self::FAIL,
				sprintf(
					/* translators: 1: host, 2: error message */
					__( 'Could not reach %1$s: %2$s. Payments in this coin will not be confirmed automatically.', 'xorro-direct-wallet-payments-woocommerce' ),
					wp_parse_url( $http['url'], PHP_URL_HOST ),
					$http['error']
				)
			);
		}
		if ( 401 === $http['code'] || 403 === $http['code'] ) {
			return self::result(
				'chain',
				$label,
				self::FAIL,
				sprintf(
					/* translators: %s: host */
					__( '%s refused the request. This chain usually needs a free API key — add one under Prices & APIs.', 'xorro-direct-wallet-payments-woocommerce' ),
					wp_parse_url( $http['url'], PHP_URL_HOST )
				)
			);
		}
		if ( 429 === $http['code'] ) {
			return self::result(
				'chain',
				$label,
				self::WARN,
				sprintf(
					/* translators: %s: host */
					__( '%s is rate-limiting this site. Payments will still be found, just more slowly — an API key raises the limit.', 'xorro-direct-wallet-payments-woocommerce' ),
					wp_parse_url( $http['url'], PHP_URL_HOST )
				)
			);
		}
		// On XRP, Stellar and other chains with an account-reserve, an address does not exist
		// on chain until something is sent to it, and the explorer answers 404. For a shop
		// testing a brand-new receiving address that is the normal state of affairs, not a
		// fault — saying "could not reach the explorer" would send them hunting for a problem
		// that is not there.
		if ( 404 === (int) $http['code'] ) {
			return self::result(
				'chain',
				$label,
				self::WARN,
				sprintf(
					/* translators: %s: explorer host */
					__( '%s has no record of this address yet. That is normal for an address nothing has ever been sent to — some chains only create an account once it receives its first payment. It will be found as soon as money arrives.', 'xorro-direct-wallet-payments-woocommerce' ),
					wp_parse_url( $http['url'], PHP_URL_HOST )
				)
			);
		}

		if ( $http['code'] < 200 || $http['code'] >= 300 ) {
			return self::result(
				'chain',
				$label,
				self::FAIL,
				sprintf(
					/* translators: 1: host, 2: HTTP status code */
					__( '%1$s answered with HTTP %2$d. Payments in this coin may not be confirmed automatically.', 'xorro-direct-wallet-payments-woocommerce' ),
					wp_parse_url( $http['url'], PHP_URL_HOST ),
					$http['code']
				)
			);
		}

		return self::result(
			'chain',
			$label,
			self::OK,
			sprintf(
				/* translators: %s: host */
				__( '%s answered normally, so incoming payments can be seen.', 'xorro-direct-wallet-payments-woocommerce' ),
				wp_parse_url( $http['url'], PHP_URL_HOST )
			)
		);
	}

	/**
	 * The service key this coin's chain needs but does not have, or '' when nothing is missing.
	 *
	 * @param array $coin Coin definition.
	 * @return string Service name.
	 */
	private static function missing_api_key( array $coin ) {
		$verifier = isset( $coin['verifier'] ) ? (string) $coin['verifier'] : '';
		$evm      = array( 'eth', 'ethereum', 'arbitrum', 'optimism', 'base', 'bsc', 'matic', 'avax', 'ftm', 'cro', 'etc' );

		if ( in_array( $verifier, $evm, true ) && '' === trim( (string) Xdwp_Settings::get( 'etherscan_api_key', '' ) ) ) {
			return 'Etherscan';
		}
		// Chains with no free public index of their own: without the key the checker can do
		// nothing at all, so say which one is missing rather than report a silent failure.
		$keyed = array(
			'kaia' => array( 'kaiascan_api_key', 'Kaiascan' ),
			'apt'  => array( 'aptos_api_key', 'Aptos' ),
		);
		if ( isset( $keyed[ $verifier ] ) && '' === trim( (string) Xdwp_Settings::get( $keyed[ $verifier ][0], '' ) ) ) {
			return $keyed[ $verifier ][1];
		}
		return '';
	}

	/**
	 * @param array $coin Coin definition.
	 * @return array
	 */
	private static function check_confirmations( array $coin ) {
		$need  = Xdwp_Coins::confirmations_for( $coin );
		$label = __( 'Confirmations before an order is paid', 'xorro-direct-wallet-payments-woocommerce' );

		if ( ! Xdwp_Coins::reports_depth( $coin ) ) {
			return self::result( 'confirmations', $label, self::OK, __( 'This network settles a payment the moment it is validated, so one is all there is to wait for.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}
		if ( $need < 1 ) {
			return self::result( 'confirmations', $label, self::WARN, __( 'Set to zero: an order is marked paid as soon as a payment is seen, before the network has confirmed it. A payment can still disappear at that point.', 'xorro-direct-wallet-payments-woocommerce' ) );
		}

		return self::result(
			'confirmations',
			$label,
			self::OK,
			sprintf(
				/* translators: %d: number of confirmations */
				_n( 'Waiting for %d confirmation.', 'Waiting for %d confirmations.', $need, 'xorro-direct-wallet-payments-woocommerce' ),
				$need
			)
		);
	}

	/**
	 * @param string $key    Check key.
	 * @param string $label  What it checks.
	 * @param string $status ok | fail | warn | skip.
	 * @param string $detail Sentence explaining the result.
	 * @return array{key:string,label:string,status:string,detail:string}
	 */
	private static function result( $key, $label, $status, $detail ) {
		return array(
			'key'    => $key,
			'label'  => $label,
			'status' => $status,
			'detail' => $detail,
		);
	}
}
