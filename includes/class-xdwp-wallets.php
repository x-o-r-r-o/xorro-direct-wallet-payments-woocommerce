<?php
/**
 * Wallet address storage and rotation.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Wallets
 */
class Xdwp_Wallets {

	/**
	 * Count of addresses dropped as invalid during the last sanitize_wallets() call.
	 *
	 * @var int
	 */
	private static $last_rejected = 0;

	/**
	 * Addresses rejected on last sanitize (for admin notices).
	 *
	 * @return int
	 */
	public static function last_rejected_count() {
		return self::$last_rejected;
	}

	/**
	 * Sanitize wallets map: coin_id => array of addresses.
	 *
	 * @param array<string, mixed> $wallets Raw wallets.
	 * @return array<string, array<int, string>>
	 */
	public static function sanitize_wallets( array $wallets ) {
		$clean               = array();
		$valid               = array_keys( Xdwp_Coins::all() );
		self::$last_rejected = 0;

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
					self::$last_rejected++;
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
		$coin = Xdwp_Coins::get( $coin_id );
		if ( ! $coin || '' === $address ) {
			return false;
		}

		$verifier = $coin['verifier'];
		$len      = strlen( $address );

		switch ( $verifier ) {
			case 'btc':
				return (bool) preg_match( '/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address );
			case 'bch':
				$addr = $address;
				if ( 0 === stripos( $addr, 'bitcoincash:' ) ) {
					$addr = substr( $addr, strlen( 'bitcoincash:' ) );
				}
				return (bool) preg_match( '/^(q|p)[a-z0-9]{41}$/', $addr )
					|| (bool) preg_match( '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $addr );
			case 'ltc':
				return (bool) preg_match( '/^(ltc1|[LM3])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address );
			case 'doge':
				return (bool) preg_match( '/^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/', $address );
			case 'dash':
				return (bool) preg_match( '/^[X7][1-9A-HJ-NP-Za-km-z]{25,34}$/', $address );
			case 'zec':
				// Transparent (t1/t3) addresses only — shielded z-addresses (zs.../zc...)
				// aren't visible to Blockchair's transparent-chain lookup and would
				// silently break payment detection if accepted here.
				return (bool) preg_match( '/^t[13][1-9A-HJ-NP-Za-km-z]{33,34}$/', $address );
			case 'xec':
				$addr = $address;
				if ( 0 === stripos( $addr, 'ecash:' ) ) {
					$addr = substr( $addr, strlen( 'ecash:' ) );
				}
				return (bool) preg_match( '/^(q|p)[a-z0-9]{41}$/', $addr );
			case 'eth':
			case 'ethereum':
			case 'arbitrum':
			case 'optimism':
			case 'base':
			case 'bsc':
			case 'bnb':
			case 'matic':
			case 'avax':
			case 'ftm':
			case 'cro':
			case 'etc':
			case 'one':
			case 'pls':
			case 'sysevm':
			case 'boba':
			case 'brise':
			case 'kaia':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'xdc':
				// Same account as a normal 0x address, just commonly written with
				// an "xdc" prefix instead — accept either.
				$xdc_addr = 0 === stripos( $address, 'xdc' ) ? substr( $address, 3 ) : $address;
				return 0 === strpos( $xdc_addr, '0x' )
					? (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $xdc_addr )
					: (bool) preg_match( '/^[a-fA-F0-9]{40}$/', $xdc_addr );
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
			case 'scrt':
				return (bool) preg_match( '/^secret1[a-z0-9]{38,58}$/', $address );
			case 'sei':
				return (bool) preg_match( '/^sei1[a-z0-9]{38,58}$/', $address );
			case 'inj_native':
				return (bool) preg_match( '/^inj1[a-z0-9]{38,58}$/', $address );
			case 'ton':
				// Accept any TON address form (raw "workchain:hex", or 48-char
				// friendly base64/base64url, bounceable or not) — the verifier
				// itself normalizes whichever form is stored here.
				return (bool) preg_match( '/^-?\d+:[0-9a-fA-F]{64}$/', $address )
					|| (bool) preg_match( '/^[A-Za-z0-9_-]{48}$/', $address );
			case 'ada':
				return (bool) preg_match( '/^addr1[a-z0-9]{50,110}$/', $address );
			case 'apt':
				// Accept with or without 0x and with any zero-padding — the
				// verifier normalizes to full 66-char form before matching.
				$addr = 0 === stripos( $address, '0x' ) ? substr( $address, 2 ) : $address;
				return (bool) preg_match( '/^[0-9a-fA-F]{1,64}$/', $addr );
			case 'kas':
				return (bool) preg_match( '/^kaspa:[a-z0-9]{61,64}$/', $address );
			case 'xtz':
				return (bool) preg_match( '/^tz[1-3][1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'xno':
				return (bool) preg_match( '/^(nano|xrb)_[13456789abcdefghijkmnopqrstuwxyz]{60}$/', $address );
			case 'waves':
				return (bool) preg_match( '/^3P[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'btg':
				return (bool) preg_match( '/^[GA][1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'firo':
			case 'xzc':
				return (bool) preg_match( '/^a[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'rvn':
				return (bool) preg_match( '/^R[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'pivx':
				return (bool) preg_match( '/^[DS][1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'neo':
			case 'gas':
				return (bool) preg_match( '/^N[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'theta':
			case 'tfuel':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'dgb':
				return (bool) preg_match( '/^D[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'kmd':
				return (bool) preg_match( '/^R[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'xvg':
				return (bool) preg_match( '/^D[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'qtum':
				return (bool) preg_match( '/^Q[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'ark':
				return (bool) preg_match( '/^A[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'ae':
				return (bool) preg_match( '/^ak_[1-9A-HJ-NP-Za-km-z]{48,50}$/', $address );
			case 'icx':
				return (bool) preg_match( '/^hx[0-9a-f]{40}$/', $address );
			case 'ont':
				return (bool) preg_match( '/^A[1-9A-HJ-NP-Za-km-z]{33}$/', $address );
			case 'klv':
				return (bool) preg_match( '/^klv1[ac-hj-np-z02-9]{58}$/', $address );
			case 'tet':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'xem':
				return (bool) preg_match( '/^N[A-Z2-7]{39}$/', $address );
			case 'xym':
				return (bool) preg_match( '/^N[A-Z2-7]{38}$/', $address );
			case 'rune':
				return (bool) preg_match( '/^thor1[ac-hj-np-z02-9]{38}$/', $address );
			case 'iotx':
				return (bool) preg_match( '/^io1[ac-hj-np-z02-9]{38,40}$/', $address )
					|| (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'cspr':
				return (bool) preg_match( '/^01[0-9a-fA-F]{64}$/', $address )
					|| (bool) preg_match( '/^02[0-9a-fA-F]{66}$/', $address );
			case 'lsk':
			case 'strax':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
			case 'iota':
				return (bool) preg_match( '/^0x[a-fA-F0-9]{64}$/', $address );
			case 'strk':
				// Starknet account addresses are felt252 values — like Aptos,
				// commonly rendered with leading zero bytes trimmed.
				$addr = 0 === stripos( $address, '0x' ) ? substr( $address, 2 ) : $address;
				return (bool) preg_match( '/^[0-9a-fA-F]{1,64}$/', $addr );
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
		$wallets = Xdwp_Settings::get( 'wallets', array() );
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

		if ( 'yes' !== Xdwp_Settings::get( 'wallet_rotation', 'yes' ) || count( $addresses ) === 1 ) {
			return $addresses[0];
		}

		$index_key = 'xdwp_wallet_idx_' . sanitize_key( $coin_id );
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

		// Atomic increment via LAST_INSERT_ID (connection-local), then return previous slot.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID( ( CAST(option_value AS UNSIGNED) + 1 ) % %d ) WHERE option_name = %s",
				$mod,
				$option
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $option, 'options' );

		return ( $value - 1 + $mod ) % $mod;
	}
}
