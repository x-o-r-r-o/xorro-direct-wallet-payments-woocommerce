<?php
/**
 * Wallet address storage and rotation.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Wallets
 */
class Chain_Checkout_Wallets {

	/**
	 * Sanitize wallets map: coin_id => array of addresses.
	 *
	 * @param array<string, mixed> $wallets Raw wallets.
	 * @return array<string, array<int, string>>
	 */
	public static function sanitize_wallets( array $wallets ) {
		$clean = array();
		$valid = array_keys( Chain_Checkout_Coins::all() );

		foreach ( $wallets as $coin_id => $addresses ) {
			$coin_id = sanitize_text_field( $coin_id );
			if ( ! in_array( $coin_id, $valid, true ) ) {
				continue;
			}

			if ( is_string( $addresses ) ) {
				$addresses = preg_split( '/[\r\n,]+/', $addresses );
			}

			if ( ! is_array( $addresses ) ) {
				continue;
			}

			$list = array();
			foreach ( $addresses as $address ) {
				$address = trim( sanitize_text_field( wp_unslash( (string) $address ) ) );
				if ( '' === $address ) {
					continue;
				}
				if ( ! self::is_plausible_address( $coin_id, $address ) ) {
					continue;
				}
				$list[] = $address;
			}

			$list = array_values( array_unique( $list ) );
			if ( ! empty( $list ) ) {
				$clean[ $coin_id ] = $list;
			}
		}

		return $clean;
	}

	/**
	 * Basic address shape validation (not cryptographic proof).
	 *
	 * @param string $coin_id Coin ID.
	 * @param string $address Address.
	 * @return bool
	 */
	public static function is_plausible_address( $coin_id, $address ) {
		$coin = Chain_Checkout_Coins::get( $coin_id );
		if ( ! $coin || '' === $address ) {
			return false;
		}

		$verifier = $coin['verifier'];
		$len      = strlen( $address );

		switch ( $verifier ) {
			case 'btc':
				return (bool) preg_match( '/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address );
			case 'ltc':
				return (bool) preg_match( '/^(ltc1|[LM3])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address );
			case 'doge':
				return (bool) preg_match( '/^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/', $address );
			case 'eth':
			case 'arbitrum':
			case 'optimism':
			case 'bsc':
			case 'bnb':
			case 'matic':
			case 'avax':
			case 'ftm':
			case 'cro':
			case 'etc':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'sol':
			case 'solana':
				return (bool) preg_match( '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address );
			case 'trx':
			case 'tron':
				return (bool) preg_match( '/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'xrp':
				return (bool) preg_match( '/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', $address );
			case 'xlm':
				return (bool) preg_match( '/^G[A-Z2-7]{55}$/', $address );
			case 'xmr':
				return $len >= 95 && $len <= 110;
			case 'dot':
				return (bool) preg_match( '/^[1-9A-HJ-NP-Za-km-z]{46,50}$/', $address );
			case 'atom':
				return (bool) preg_match( '/^cosmos1[a-z0-9]{38,58}$/', $address );
			case 'algo':
				return (bool) preg_match( '/^[A-Z2-7]{58}$/', $address );
			case 'near':
				return (bool) preg_match( '/^(([a-z0-9_-]{2,64}\.)*([a-z0-9_-]{2,64})\.near|[a-f0-9]{64})$/', $address )
					|| (bool) preg_match( '/^[a-z0-9._-]{2,64}$/', $address );
			case 'fil':
				return (bool) preg_match( '/^f[0-9a-zA-Z]{8,128}$/', $address );
			case 'hbar':
				return (bool) preg_match( '/^0\.0\.\d{1,10}$/', $address );
			case 'egld':
				return (bool) preg_match( '/^erd1[a-z0-9]{58}$/', $address );
			case 'zil':
				return (bool) preg_match( '/^zil1[a-z0-9]{38}$/', $address );
			case 'eos':
				return (bool) preg_match( '/^[a-z1-5.]{1,12}$/', $address );
			default:
				return $len >= 10 && $len <= 128;
		}
	}

	/**
	 * Get configured addresses for a coin.
	 *
	 * @param string $coin_id Coin ID.
	 * @return array<int, string>
	 */
	public static function get_addresses( $coin_id ) {
		$wallets = Chain_Checkout_Settings::get( 'wallets', array() );
		if ( ! is_array( $wallets ) || empty( $wallets[ $coin_id ] ) || ! is_array( $wallets[ $coin_id ] ) ) {
			return array();
		}
		return array_values( $wallets[ $coin_id ] );
	}

	/**
	 * Pick a receiving address (rotation or first).
	 *
	 * @param string $coin_id Coin ID.
	 * @return string
	 */
	public static function pick_address( $coin_id ) {
		$addresses = self::get_addresses( $coin_id );
		if ( empty( $addresses ) ) {
			return '';
		}

		if ( 'yes' !== Chain_Checkout_Settings::get( 'wallet_rotation', 'yes' ) || count( $addresses ) === 1 ) {
			return $addresses[0];
		}

		$index_key = 'chain_checkout_wallet_idx_' . sanitize_key( $coin_id );
		$count     = count( $addresses );
		$index     = self::next_wallet_index( $index_key, $count );
		return $addresses[ $index % $count ];
	}

	/**
	 * Atomic wallet rotation index.
	 *
	 * @param string $option Option name.
	 * @param int    $mod    Address count.
	 * @return int Index used for this pick.
	 */
	private static function next_wallet_index( $option, $mod ) {
		global $wpdb;

		$mod = max( 1, (int) $mod );
		add_option( $option, 0, '', 'no' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = ( CAST(option_value AS UNSIGNED) + 1 ) % %d WHERE option_name = %s",
				$mod,
				$option
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		wp_cache_delete( $option, 'options' );

		// Value is post-increment; return previous slot.
		return ( $value - 1 + $mod ) % $mod;
	}
}
