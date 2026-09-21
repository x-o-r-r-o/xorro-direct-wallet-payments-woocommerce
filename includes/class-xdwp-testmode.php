<?php
/**
 * Proving the setup works without spending anything.
 *
 * Until now the only way to know a shop would really take a crypto payment was to send one. That
 * is a poor place to discover that an address was pasted wrong, that an email never arrives, or
 * that a webhook goes nowhere — the money is gone either way, and on the wrong chain it is gone
 * for good.
 *
 * Test mode points the whole flow at test networks, where coins are free and handed out by
 * faucets. A real customer journey runs end to end: a quote, an address, a payment page, a
 * transfer read off a real chain, a confirmed order, an email, a webhook.
 *
 * Only three chains are offered, and deliberately so. A test network has to be one this plugin
 * can actually read, with a faucet a merchant can get coins from today; claiming more would mean
 * shipping endpoints that quietly fail. Bitcoin, Ethereum and TRON cover what almost anyone
 * rehearses with, and the rest of the shop's coins are hidden rather than half-working.
 *
 * Nothing about test mode is subtle from the outside: it is named on every admin screen, on the
 * checkout, and on the payment page, because a shop left in test mode is a shop taking orders
 * nobody can pay for.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Test networks, for rehearsing a payment with worthless coins.
 */
class Xdwp_Testmode {

	/**
	 * The setting that turns it on.
	 */
	const SETTING = 'test_mode';

	/**
	 * Whether the shop is currently pointed at test networks.
	 *
	 * @return bool
	 */
	public static function active() {
		return 'yes' === Xdwp_Settings::get( self::SETTING, 'no' );
	}

	/**
	 * The chains that have a test network this plugin can read.
	 *
	 * Keyed by the verifier the coin uses, so the checks below never have to know which coin id
	 * a shop happens to have enabled.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function networks() {
		return array(
			'btc'  => array(
				'label'  => __( 'Bitcoin testnet3', 'xorro-direct-wallet-payments-woocommerce' ),
				'faucet' => 'https://coinfaucet.eu/en/btc-testnet/',
				'regex'  => '/^(tb1|[mn2])[a-zA-HJ-NP-Z0-9]{24,62}$/',
			),
			'eth'  => array(
				'label'  => __( 'Ethereum Sepolia', 'xorro-direct-wallet-payments-woocommerce' ),
				'faucet' => 'https://www.alchemy.com/faucets/ethereum-sepolia',
				'regex'  => '/^0x[a-fA-F0-9]{40}$/',
			),
			'tron' => array(
				'label'  => __( 'TRON Nile', 'xorro-direct-wallet-payments-woocommerce' ),
				'faucet' => 'https://nileex.io/join/getJoinPage',
				'regex'  => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
			),
		);
	}

	/**
	 * Whether a chain has a test network here.
	 *
	 * @param string $verifier Verifier key.
	 * @return bool
	 */
	public static function supports( $verifier ) {
		if ( ! is_scalar( $verifier ) ) {
			return false;
		}
		return array_key_exists( (string) $verifier, self::networks() );
	}

	/**
	 * What to call the network a customer must send on.
	 *
	 * @param string $verifier Verifier key.
	 * @return string
	 */
	public static function network_label( $verifier ) {
		$networks = self::networks();
		$verifier = is_scalar( $verifier ) ? (string) $verifier : '';
		return isset( $networks[ $verifier ] ) ? (string) $networks[ $verifier ]['label'] : '';
	}

	/**
	 * Where a merchant gets coins to rehearse with.
	 *
	 * @param string $verifier Verifier key.
	 * @return string
	 */
	public static function faucet( $verifier ) {
		$networks = self::networks();
		$verifier = is_scalar( $verifier ) ? (string) $verifier : '';
		return isset( $networks[ $verifier ] ) ? (string) $networks[ $verifier ]['faucet'] : '';
	}

	/**
	 * Does this look like an address on the test network for this chain?
	 *
	 * A mainnet address saved while test mode is on would be checked against the wrong chain and
	 * never see a payment, so the shapes are enforced rather than merely preferred. Ethereum and
	 * TRON write test addresses exactly as they write real ones, which is why those two look
	 * unchanged here.
	 *
	 * @param string $verifier Verifier key.
	 * @param string $address  Address.
	 * @return bool
	 */
	public static function address_ok( $verifier, $address ) {
		$networks = self::networks();
		$verifier = is_scalar( $verifier ) ? (string) $verifier : '';
		$address  = is_scalar( $address ) ? (string) $address : '';
		if ( '' === $address || ! isset( $networks[ $verifier ] ) ) {
			return false;
		}
		return (bool) preg_match( $networks[ $verifier ]['regex'], $address );
	}

	/**
	 * The coins a shop may take while rehearsing.
	 *
	 * @return array<int, string> Coin ids.
	 */
	public static function coin_ids() {
		$ids = array();
		foreach ( Xdwp_Coins::all() as $coin_id => $coin ) {
			$verifier = isset( $coin['verifier'] ) ? (string) $coin['verifier'] : '';
			// Native coins only: a token needs its own test-network contract, which is a
			// different address on every test network and not something to guess at.
			$native = isset( $coin['type'] ) && 'native' === $coin['type'];
			if ( $native && self::supports( $verifier ) ) {
				$ids[] = (string) $coin_id;
			}
		}
		return $ids;
	}

	/**
	 * Sentence shown wherever test mode needs to be admitted to.
	 *
	 * @return string
	 */
	public static function notice() {
		return __( 'Test mode is on. This shop is pointed at test networks, so payments are made with worthless coins and no real money can be received. Turn it off under Xorro Wallet Payments → General before taking real orders.', 'xorro-direct-wallet-payments-woocommerce' );
	}
}
