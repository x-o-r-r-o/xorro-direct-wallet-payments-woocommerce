<?php
/**
 * Addresses derived from a merchant's own extended public key (xpub / ypub / zpub).
 *
 * A store that gives this plugin an extended *public* key gets a fresh receiving address for
 * every order, which removes shared-address guesswork entirely: a payment to that address can
 * only belong to that order. Nothing here can spend: an extended public key derives addresses
 * and nothing else, and no private key is ever asked for, stored, or transmitted.
 *
 * The maths is plain secp256k1 point arithmetic over bcmath, so it works on any host without
 * extra extensions. One address costs a single scalar multiplication — a few tens of
 * milliseconds, once per order.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Hd
 */
class Xdwp_Hd {

	/** secp256k1 field prime. */
	const P = '115792089237316195423570985008687907853269984665640564039457584007908834671663';

	/** secp256k1 group order. */
	const N = '115792089237316195423570985008687907852837564279074904382605163141518161494337';

	/** Generator x. */
	const GX = '55066263022277343669578718895168534326250603453777594175500187360389116729240';

	/** Generator y. */
	const GY = '32670510020758816978083085130507043184471273380659243275938904335757337482424';

	/** Base58 alphabet. */
	const B58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/** Bech32 alphabet. */
	const BECH32 = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

	/**
	 * Version bytes of the extended public keys this understands, and the address they make.
	 *
	 * @return array<string, array{version:string, type:string, prefix:int, hrp:string, label:string}>
	 */
	public static function key_kinds() {
		return array(
			'xpub' => array(
				'version' => '0488b21e',
				'type'    => 'p2pkh',
				'prefix'  => 0x00,
				'hrp'     => '',
				'label'   => 'Bitcoin (addresses starting 1)',
			),
			'ypub' => array(
				'version' => '049d7cb2',
				'type'    => 'p2sh-p2wpkh',
				'prefix'  => 0x05,
				'hrp'     => '',
				'label'   => 'Bitcoin (addresses starting 3)',
			),
			'zpub' => array(
				'version' => '04b24746',
				'type'    => 'p2wpkh',
				'prefix'  => 0,
				'hrp'     => 'bc',
				'label'   => 'Bitcoin (addresses starting bc1)',
			),
			'Ltub' => array(
				'version' => '019da462',
				'type'    => 'p2pkh',
				'prefix'  => 0x30,
				'hrp'     => '',
				'label'   => 'Litecoin (addresses starting L)',
			),
			'dgub' => array(
				'version' => '02facafd',
				'type'    => 'p2pkh',
				'prefix'  => 0x1e,
				'hrp'     => '',
				'label'   => 'Dogecoin (addresses starting D)',
			),
		);
	}

	/**
	 * Coins that can use an extended public key here, and the key kinds each accepts.
	 *
	 * @return array<string, array<int, string>> Coin ID => key prefixes.
	 */
	public static function supported_coins() {
		return array(
			'BTC'  => array( 'xpub', 'ypub', 'zpub' ),
			'LTC'  => array( 'Ltub', 'xpub' ),
			'DOGE' => array( 'dgub', 'xpub' ),
		);
	}

	/**
	 * Is this a well-formed extended public key this plugin can use for that coin?
	 *
	 * @param string $key     Extended public key.
	 * @param string $coin_id Coin ID, or '' to accept any supported coin.
	 * @return bool
	 */
	public static function is_valid( $key, $coin_id = '' ) {
		return null !== self::parse( $key, $coin_id );
	}

	/**
	 * Decode an extended public key, or null when it is not one we can use.
	 *
	 * @param string $key     Extended public key.
	 * @param string $coin_id Coin ID, or '' for any.
	 * @return array{kind:string,chain_code:string,point:string}|null
	 */
	public static function parse( $key, $coin_id = '' ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! preg_match( '/^[' . preg_quote( self::B58, '/' ) . ']{100,120}$/', $key ) ) {
			return null;
		}

		$raw = self::base58check_decode( $key );
		// 4 version + 1 depth + 4 fingerprint + 4 index + 32 chain code + 33 key = 78 bytes.
		if ( null === $raw || 78 !== strlen( $raw ) ) {
			return null;
		}

		$version = bin2hex( substr( $raw, 0, 4 ) );
		$kind    = '';
		foreach ( self::key_kinds() as $name => $spec ) {
			if ( $spec['version'] === $version ) {
				$kind = $name;
				break;
			}
		}
		if ( '' === $kind ) {
			return null;
		}

		if ( '' !== $coin_id ) {
			$allowed = self::supported_coins();
			if ( ! isset( $allowed[ $coin_id ] ) || ! in_array( $kind, $allowed[ $coin_id ], true ) ) {
				return null;
			}
		}

		$chain_code = substr( $raw, 13, 32 );
		$point      = substr( $raw, 45, 33 );
		$first      = ord( $point[0] );
		// Only a compressed public key can be an account key; 0x00 would be a *private* key,
		// which this plugin must never be handed.
		if ( 0x02 !== $first && 0x03 !== $first ) {
			return null;
		}

		return array(
			'kind'       => $kind,
			'chain_code' => $chain_code,
			'point'      => $point,
		);
	}

	/**
	 * The receiving address at one index of an account key, or '' when it cannot be derived.
	 *
	 * Follows the usual account layout: the key is the account's own node, so the address is
	 * at change 0, index n — the same addresses the merchant's wallet shows as "receive".
	 *
	 * @param string $key   Extended public key.
	 * @param int    $index Address index (0-based).
	 * @return string
	 */
	public static function address( $key, $index ) {
		$parsed = self::parse( $key );
		$index  = (int) $index;
		if ( null === $parsed || $index < 0 || $index > 0x7fffffff ) {
			return '';
		}
		if ( ! function_exists( 'bcadd' ) ) {
			return '';
		}

		$node = self::child( $parsed['point'], $parsed['chain_code'], 0 );
		if ( null === $node ) {
			return '';
		}
		$node = self::child( $node['point'], $node['chain_code'], $index );
		if ( null === $node ) {
			return '';
		}

		$kinds = self::key_kinds();
		$spec  = $kinds[ $parsed['kind'] ];
		$hash  = self::hash160( $node['point'] );

		switch ( $spec['type'] ) {
			case 'p2wpkh':
				return self::bech32_address( $spec['hrp'], $hash );

			case 'p2sh-p2wpkh':
				// The redeem script is OP_0 <20-byte key hash>.
				$script = "\x00\x14" . $hash;
				return self::base58check_encode( chr( $spec['prefix'] ) . self::hash160_raw( $script ) );

			case 'p2pkh':
			default:
				return self::base58check_encode( chr( $spec['prefix'] ) . $hash );
		}
	}

	/**
	 * One non-hardened child of a public node (BIP32 CKDpub).
	 *
	 * @param string $point      33-byte compressed parent key.
	 * @param string $chain_code 32-byte parent chain code.
	 * @param int    $index      Child index, below 2^31.
	 * @return array{point:string,chain_code:string}|null
	 */
	private static function child( $point, $chain_code, $index ) {
		$data = $point . pack( 'N', $index );
		$i    = hash_hmac( 'sha512', $data, $chain_code, true );
		$il   = substr( $i, 0, 32 );
		$ir   = substr( $i, 32, 32 );

		$scalar = self::hex_to_dec( bin2hex( $il ) );
		// Vanishingly rare, but a scalar at or above the group order makes the child invalid.
		if ( bccomp( $scalar, self::N ) >= 0 ) {
			return null;
		}

		$parent = self::decompress( $point );
		if ( null === $parent ) {
			return null;
		}
		$added = self::mul_g_and_add( $scalar, $parent );
		if ( null === $added ) {
			return null;
		}

		return array(
			'point'      => self::compress( $added ),
			'chain_code' => $ir,
		);
	}

	// ---------------------------------------------------------------- curve maths

	/*
	 * Points are held in Jacobian coordinates (X, Y, Z), standing for the affine point
	 * (X/Z², Y/Z³). That matters for speed: in affine form every step needs a modular
	 * inverse, which on bcmath costs a 256-bit modular exponentiation — about eight seconds
	 * per address, far too slow to keep a customer waiting at checkout. In Jacobian form the
	 * whole multiplication needs exactly one inverse, at the very end.
	 */

	/**
	 * Add an affine point to a Jacobian one.
	 *
	 * @param array|null $j Jacobian point {X, Y, Z}, or null for the point at infinity.
	 * @param array      $a Affine point {x, y}.
	 * @return array|null
	 */
	private static function jacobian_add_affine( $j, $a ) {
		if ( null === $j ) {
			return array( $a[0], $a[1], '1' );
		}
		list( $x1, $y1, $z1 ) = $j;

		$z1z1 = self::modp( bcmul( $z1, $z1 ) );
		$u2   = self::modp( bcmul( $a[0], $z1z1 ) );
		$s2   = self::modp( bcmul( $a[1], self::modp( bcmul( $z1, $z1z1 ) ) ) );

		if ( 0 === bccomp( $u2, $x1 ) ) {
			if ( 0 === bccomp( $s2, $y1 ) ) {
				return self::jacobian_double( $j );
			}
			return null; // P + (-P).
		}

		$h  = self::modp( bcsub( $u2, $x1 ) );
		$hh = self::modp( bcmul( $h, $h ) );
		$i  = self::modp( bcmul( '4', $hh ) );
		$jj = self::modp( bcmul( $h, $i ) );
		$r  = self::modp( bcmul( '2', bcsub( $s2, $y1 ) ) );
		$v  = self::modp( bcmul( $x1, $i ) );

		$x3 = self::modp( bcsub( bcsub( bcmul( $r, $r ), $jj ), bcmul( '2', $v ) ) );
		$y3 = self::modp( bcsub( bcmul( $r, bcsub( $v, $x3 ) ), bcmul( '2', bcmul( $y1, $jj ) ) ) );
		$z3 = self::modp( bcmul( $h, bcmul( '2', $z1 ) ) );

		return array( $x3, $y3, $z3 );
	}

	/**
	 * Double a Jacobian point (secp256k1 has a = 0, which shortens this).
	 *
	 * @param array|null $j Jacobian point.
	 * @return array|null
	 */
	private static function jacobian_double( $j ) {
		if ( null === $j ) {
			return null;
		}
		list( $x1, $y1, $z1 ) = $j;
		if ( 0 === bccomp( $y1, '0' ) ) {
			return null;
		}

		$a = self::modp( bcmul( $x1, $x1 ) );
		$b = self::modp( bcmul( $y1, $y1 ) );
		$c = self::modp( bcmul( $b, $b ) );
		$d = self::modp( bcmul( '2', bcsub( bcsub( self::modp( bcmul( bcadd( $x1, $b ), bcadd( $x1, $b ) ) ), $a ), $c ) ) );
		$e = self::modp( bcmul( '3', $a ) );
		$f = self::modp( bcmul( $e, $e ) );

		$x3 = self::modp( bcsub( $f, bcmul( '2', $d ) ) );
		$y3 = self::modp( bcsub( bcmul( $e, bcsub( $d, $x3 ) ), bcmul( '8', $c ) ) );
		$z3 = self::modp( bcmul( '2', bcmul( $y1, $z1 ) ) );

		return array( $x3, $y3, $z3 );
	}

	/**
	 * Back to affine coordinates — the one place an inverse is needed.
	 *
	 * @param array|null $j Jacobian point.
	 * @return array{0:string,1:string}|null
	 */
	private static function to_affine( $j ) {
		if ( null === $j ) {
			return null;
		}
		list( $x, $y, $z ) = $j;
		if ( 0 === bccomp( $z, '0' ) ) {
			return null;
		}
		$zi  = self::inverse( $z );
		$zi2 = self::modp( bcmul( $zi, $zi ) );
		$zi3 = self::modp( bcmul( $zi2, $zi ) );
		return array( self::modp( bcmul( $x, $zi2 ) ), self::modp( bcmul( $y, $zi3 ) ) );
	}

	/**
	 * Generator multiplied by a scalar, plus an affine point, in one pass.
	 *
	 * Walks the scalar from its top bit down, doubling as it goes and adding the generator
	 * where a bit is set. The generator is a constant in affine form, so every step is a
	 * cheap mixed addition and the whole thing needs exactly one modular inverse — the one
	 * that converts the answer back to affine coordinates at the end.
	 *
	 * @param string $scalar Decimal scalar.
	 * @param array  $add    Affine point to add to the result.
	 * @return array{0:string,1:string}|null Affine point.
	 */
	private static function mul_g_and_add( $scalar, $add ) {
		$bits = self::scalar_bits( $scalar );
		if ( '' === $bits ) {
			return $add;
		}

		$g      = array( self::GX, self::GY );
		$result = null;
		$len    = strlen( $bits );
		for ( $i = 0; $i < $len; $i++ ) {
			$result = self::jacobian_double( $result );
			if ( '1' === $bits[ $i ] ) {
				$result = self::jacobian_add_affine( $result, $g );
			}
		}

		$result = self::jacobian_add_affine( $result, $add );
		return self::to_affine( $result );
	}

	/**
	 * A scalar as its binary digits, most significant first and without leading zeros.
	 *
	 * @param string $scalar Decimal scalar.
	 * @return string
	 */
	private static function scalar_bits( $scalar ) {
		$hex  = self::dec_to_hex( $scalar );
		$bits = '';
		$len  = strlen( $hex );
		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( hexdec( $hex[ $i ] ) ), 4, '0', STR_PAD_LEFT );
		}
		return ltrim( $bits, '0' );
	}

	/**
	 * Modular inverse (Fermat's little theorem — the field prime is, well, prime).
	 *
	 * @param string $a Value.
	 * @return string
	 */
	private static function inverse( $a ) {
		return bcpowmod( $a, bcsub( self::P, '2' ), self::P );
	}

	/**
	 * @param string $v Value.
	 * @return string Value reduced into [0, P).
	 */
	private static function modp( $v ) {
		$r = bcmod( $v, self::P );
		return ( bccomp( $r, '0' ) < 0 ) ? bcadd( $r, self::P ) : $r;
	}

	/**
	 * @param string $point 33-byte compressed key.
	 * @return array{0:string,1:string}|null
	 */
	private static function decompress( $point ) {
		if ( 33 !== strlen( $point ) ) {
			return null;
		}
		$sign = ord( $point[0] );
		$x    = self::hex_to_dec( bin2hex( substr( $point, 1 ) ) );
		// y² = x³ + 7
		$y2 = self::modp( bcadd( bcpowmod( $x, '3', self::P ), '7' ) );
		$y  = bcpowmod( $y2, bcdiv( bcadd( self::P, '1' ), '4', 0 ), self::P );
		if ( 0 !== bccomp( self::modp( bcmul( $y, $y ) ), $y2 ) ) {
			return null; // Not a point on the curve.
		}
		$odd = ( '1' === bcmod( $y, '2' ) );
		if ( ( 0x03 === $sign ) !== $odd ) {
			$y = bcsub( self::P, $y );
		}
		return array( $x, $y );
	}

	/**
	 * @param array{0:string,1:string} $p Point.
	 * @return string 33-byte compressed key.
	 */
	private static function compress( $p ) {
		$prefix = ( '1' === bcmod( $p[1], '2' ) ) ? "\x03" : "\x02";
		return $prefix . hex2bin( str_pad( self::dec_to_hex( $p[0] ), 64, '0', STR_PAD_LEFT ) );
	}

	// ---------------------------------------------------------------- encodings

	/**
	 * @param string $bin Binary.
	 * @return string 20-byte RIPEMD160(SHA256(bin)).
	 */
	private static function hash160( $bin ) {
		return self::hash160_raw( $bin );
	}

	/**
	 * @param string $bin Binary.
	 * @return string
	 */
	private static function hash160_raw( $bin ) {
		return hash( 'ripemd160', hash( 'sha256', $bin, true ), true );
	}

	/**
	 * @param string $bin Payload without checksum.
	 * @return string Base58Check string.
	 */
	public static function base58check_encode( $bin ) {
		$checksum = substr( hash( 'sha256', hash( 'sha256', $bin, true ), true ), 0, 4 );
		$full     = $bin . $checksum;

		$dec = self::hex_to_dec( bin2hex( $full ) );
		$out = '';
		while ( bccomp( $dec, '0' ) > 0 ) {
			$rem = bcmod( $dec, '58' );
			$dec = bcdiv( $dec, '58', 0 );
			$out = self::B58[ (int) $rem ] . $out;
		}
		// Every leading zero byte is one leading "1".
		for ( $i = 0; $i < strlen( $full ) && "\x00" === $full[ $i ]; $i++ ) {
			$out = '1' . $out;
		}
		return $out;
	}

	/**
	 * @param string $string Base58Check string.
	 * @return string|null Payload without checksum, or null when the checksum fails.
	 */
	public static function base58check_decode( $string ) {
		$dec = '0';
		$len = strlen( $string );
		for ( $i = 0; $i < $len; $i++ ) {
			$pos = strpos( self::B58, $string[ $i ] );
			if ( false === $pos ) {
				return null;
			}
			$dec = bcadd( bcmul( $dec, '58' ), (string) $pos );
		}
		$hex = self::dec_to_hex( $dec );
		if ( strlen( $hex ) % 2 ) {
			$hex = '0' . $hex;
		}
		$bin = ( '' === $hex ) ? '' : hex2bin( $hex );
		for ( $i = 0; $i < $len && '1' === $string[ $i ]; $i++ ) {
			$bin = "\x00" . $bin;
		}
		if ( strlen( $bin ) < 5 ) {
			return null;
		}
		$payload  = substr( $bin, 0, -4 );
		$checksum = substr( $bin, -4 );
		if ( ! hash_equals( substr( hash( 'sha256', hash( 'sha256', $payload, true ), true ), 0, 4 ), $checksum ) ) {
			return null;
		}
		return $payload;
	}

	/**
	 * Native segwit (BIP173) address for a 20-byte key hash.
	 *
	 * @param string $hrp  Human-readable part ("bc").
	 * @param string $hash 20-byte key hash.
	 * @return string
	 */
	private static function bech32_address( $hrp, $hash ) {
		$data = array_merge( array( 0 ), self::convert_bits( array_values( unpack( 'C*', $hash ) ), 8, 5, true ) );
		$sum  = self::bech32_checksum( $hrp, $data );
		$out  = $hrp . '1';
		foreach ( array_merge( $data, $sum ) as $value ) {
			$out .= self::BECH32[ $value ];
		}
		return $out;
	}

	/**
	 * @param array $data   Values.
	 * @param int   $from   Bits in.
	 * @param int   $to     Bits out.
	 * @param bool  $pad    Pad the tail.
	 * @return array
	 */
	private static function convert_bits( $data, $from, $to, $pad ) {
		$acc  = 0;
		$bits = 0;
		$out  = array();
		$max  = ( 1 << $to ) - 1;
		foreach ( $data as $value ) {
			$acc   = ( $acc << $from ) | $value;
			$bits += $from;
			while ( $bits >= $to ) {
				$bits -= $to;
				$out[] = ( $acc >> $bits ) & $max;
			}
		}
		if ( $pad && $bits > 0 ) {
			$out[] = ( $acc << ( $to - $bits ) ) & $max;
		}
		return $out;
	}

	/**
	 * @param string $hrp  Human-readable part.
	 * @param array  $data Data values.
	 * @return array Six checksum values.
	 */
	private static function bech32_checksum( $hrp, $data ) {
		$values = array_merge( self::bech32_hrp_expand( $hrp ), $data, array( 0, 0, 0, 0, 0, 0 ) );
		$polymod = self::bech32_polymod( $values ) ^ 1;
		$out     = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$out[] = ( $polymod >> ( 5 * ( 5 - $i ) ) ) & 31;
		}
		return $out;
	}

	/**
	 * @param string $hrp Human-readable part.
	 * @return array
	 */
	private static function bech32_hrp_expand( $hrp ) {
		$out = array();
		$len = strlen( $hrp );
		for ( $i = 0; $i < $len; $i++ ) {
			$out[] = ord( $hrp[ $i ] ) >> 5;
		}
		$out[] = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$out[] = ord( $hrp[ $i ] ) & 31;
		}
		return $out;
	}

	/**
	 * @param array $values Values.
	 * @return int
	 */
	private static function bech32_polymod( $values ) {
		$generator = array( 0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3 );
		$chk       = 1;
		foreach ( $values as $value ) {
			$top = $chk >> 25;
			$chk = ( ( $chk & 0x1ffffff ) << 5 ) ^ $value;
			for ( $i = 0; $i < 5; $i++ ) {
				if ( ( $top >> $i ) & 1 ) {
					$chk ^= $generator[ $i ];
				}
			}
		}
		return $chk;
	}

	// ---------------------------------------------------------------- number helpers

	/**
	 * @param string $hex Hex string.
	 * @return string Decimal string.
	 */
	private static function hex_to_dec( $hex ) {
		$dec = '0';
		$len = strlen( $hex );
		for ( $i = 0; $i < $len; $i++ ) {
			$dec = bcadd( bcmul( $dec, '16' ), (string) hexdec( $hex[ $i ] ) );
		}
		return $dec;
	}

	/**
	 * @param string $dec Decimal string.
	 * @return string Hex string.
	 */
	private static function dec_to_hex( $dec ) {
		$hex = '';
		while ( bccomp( $dec, '0' ) > 0 ) {
			$rem = (int) bcmod( $dec, '16' );
			$hex = dechex( $rem ) . $hex;
			$dec = bcdiv( $dec, '16', 0 );
		}
		return $hex;
	}
}
