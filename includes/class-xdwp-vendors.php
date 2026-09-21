<?php
/**
 * Paying a marketplace vendor directly.
 *
 * On a marketplace the shop owner is not the person who sold the thing. Every other crypto
 * gateway handles this by taking the money itself and paying vendors later, which puts the
 * marketplace back in the business of holding other people's funds — the exact arrangement this
 * plugin exists to avoid.
 *
 * Non-custodial makes it easy instead of hard: if the money goes straight to an address, it can
 * go straight to the vendor's address. No payout ledger, no float, nothing owed.
 *
 * Three things keep this safe for the shops that are not marketplaces, which is almost all of
 * them. It does nothing at all unless a marketplace plugin is installed *and* the merchant has
 * switched it on. It only ever resolves an address through `xdwp_receiving_address`, which falls
 * back to the shop's own address whenever the answer is not a valid address for the coin. And an
 * order it cannot attribute to exactly one vendor is paid to the shop, because splitting one
 * transfer between several people is not something a blockchain will do.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routing an order's payment to the vendor who sold it.
 */
class Xdwp_Vendors {

	/**
	 * The setting that switches it on. Off until a merchant says otherwise, even on a
	 * marketplace: where money goes is not a decision to make on somebody's behalf.
	 */
	const SETTING = 'vendor_payouts';

	/**
	 * Where a vendor's own addresses live, on their user account.
	 */
	const USER_META = '_xdwp_vendor_wallets';

	/**
	 * Recorded on an order so it is afterwards clear who was paid, and why.
	 */
	const ORDER_META = '_xdwp_vendor';

	/**
	 * Hook the one filter this needs, and only when it is wanted.
	 */
	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'xdwp_receiving_address', array( __CLASS__, 'route' ), 10, 3 );

		// Addresses are entered on the user's own profile screen, which every marketplace
		// vendor already has and which needs no marketplace-specific hook to reach. A vendor
		// can set their own; the marketplace owner can set one for them.
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
	}

	/**
	 * The address fields on a vendor's profile.
	 *
	 * Only the coins the shop actually takes, because an address for a coin nobody can choose at
	 * checkout is just a place to make a mistake.
	 *
	 * @param WP_User $user The user whose profile is being shown.
	 */
	public static function profile_fields( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		// Editing somebody else's payout address is a privileged act; editing your own is not.
		if ( get_current_user_id() !== (int) $user->ID && ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$payable = Xdwp_Coins::get_payable();
		if ( empty( $payable ) ) {
			return;
		}

		$saved = get_user_meta( (int) $user->ID, self::USER_META, true );
		$saved = is_array( $saved ) ? $saved : array();

		echo '<h2>' . esc_html__( 'Crypto payout addresses', 'xorro-direct-wallet-payments-woocommerce' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'When a customer buys your products and pays in one of these coins, the money goes straight from them to the address you give here. Nobody holds it in between, so an address that is wrong cannot be undone — paste it from your wallet rather than typing it.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';
		wp_nonce_field( 'xdwp_vendor_wallets', 'xdwp_vendor_nonce' );
		echo '<table class="form-table" role="presentation">';

		foreach ( $payable as $coin_id => $coin ) {
			$value = isset( $saved[ $coin_id ] ) ? (string) $saved[ $coin_id ] : '';
			printf(
				'<tr><th><label for="xdwp-vendor-%1$s">%2$s</label></th><td><input type="text" class="regular-text code" id="xdwp-vendor-%1$s" name="xdwp_vendor_wallets[%1$s]" value="%3$s" autocomplete="off" spellcheck="false" /><p class="description">%4$s</p></td></tr>',
				esc_attr( $coin_id ),
				esc_html( $coin['name'] . ' (' . $coin['symbol'] . ')' ),
				esc_attr( $value ),
				esc_html( Xdwp_Coins::network_label( $coin ) )
			);
		}

		echo '</table>';
	}

	/**
	 * Save what a vendor typed.
	 *
	 * @param int $user_id The user being saved.
	 */
	public static function save_profile_fields( $user_id ) {
		$user_id = (int) $user_id;
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_users' ) ) {
			return;
		}
		if ( ! isset( $_POST['xdwp_vendor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xdwp_vendor_nonce'] ) ), 'xdwp_vendor_wallets' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitised in save_addresses().
		$raw = isset( $_POST['xdwp_vendor_wallets'] ) ? wp_unslash( $_POST['xdwp_vendor_wallets'] ) : array();
		self::save_addresses( $user_id, is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Which marketplace plugin is running, if any.
	 *
	 * Each is identified by something its own documentation treats as public API, so a private
	 * rename upstream turns this off rather than breaking it.
	 *
	 * @return string dokan | wcfm | wcvendors | '' when there is none.
	 */
	public static function detect() {
		// Paired checks throughout: the class says the plugin is here, the function says the
		// call below will actually work. These plugins move code between files across major
		// versions, and a gateway that assumes otherwise fatals on somebody's checkout.
		if ( class_exists( 'WeDevs_Dokan' ) && function_exists( 'dokan_get_vendor_by_product' ) ) {
			return 'dokan';
		}
		if ( defined( 'WCFM_VERSION' ) && function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
			return 'wcfm';
		}
		if ( class_exists( 'WC_Vendors' ) && class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_from_product' ) ) {
			return 'wcvendors';
		}
		return '';
	}

	/**
	 * Whether vendor payouts are actually in force.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === Xdwp_Settings::get( self::SETTING, 'no' ) && '' !== self::detect();
	}

	/**
	 * Answer "where should this order's money go?" with the vendor's address, when there is one.
	 *
	 * Deliberately conservative. Anything it is not sure about returns the address it was given,
	 * which is the shop's own — the wrong answer here sends a customer's money to a stranger.
	 *
	 * @param string        $address The shop's own address.
	 * @param string        $coin_id Coin ID.
	 * @param WC_Order|null $order   Order being quoted.
	 * @return string
	 */
	public static function route( $address, $coin_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $address;
		}

		$vendor = self::vendor_for_order( $order );
		if ( $vendor <= 0 ) {
			// Either the shop's own product, or more than one vendor in the order. Both are
			// paid to the shop; the note below says which.
			return $address;
		}

		$vendor_address = self::address_for( $vendor, $coin_id );
		if ( '' === $vendor_address ) {
			self::note(
				$order,
				sprintf(
					/* translators: 1: vendor name, 2: coin symbol */
					__( 'This order is %1$s\'s, but they have no %2$s address saved, so it is being paid to the shop. They can add one in their store settings.', 'xorro-direct-wallet-payments-woocommerce' ),
					self::vendor_name( $vendor ),
					self::symbol( $coin_id )
				)
			);
			return $address;
		}

		$order->update_meta_data( self::ORDER_META, $vendor );
		$order->save();

		return $vendor_address;
	}

	/**
	 * The one vendor an order belongs to, or 0.
	 *
	 * Returns 0 when the order has items from more than one seller. A single transfer cannot be
	 * split between several people, and quietly paying all of it to the first vendor found would
	 * be worse than paying the shop and letting the marketplace settle it.
	 *
	 * @param WC_Order $order Order.
	 * @return int Vendor user id, or 0.
	 */
	public static function vendor_for_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 0;
		}

		$vendors = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_callable( array( $item, 'get_product_id' ) ) ) {
				continue;
			}
			$product_id = (int) $item->get_product_id();
			if ( $product_id <= 0 ) {
				continue;
			}
			$vendor = self::vendor_for_product( $product_id );
			if ( $vendor > 0 ) {
				$vendors[ $vendor ] = true;
			}
		}

		if ( 1 !== count( $vendors ) ) {
			if ( count( $vendors ) > 1 ) {
				self::note(
					$order,
					__( 'This order has items from more than one vendor. One crypto transfer cannot be split between several people, so it is being paid to the shop, to settle with the vendors as usual.', 'xorro-direct-wallet-payments-woocommerce' )
				);
			}
			return 0;
		}

		return (int) array_keys( $vendors )[0];
	}

	/**
	 * Who sells a product, according to whichever marketplace is installed.
	 *
	 * Every call is guarded, so a function that has been renamed upstream means this returns 0
	 * and the shop is paid — never a fatal error on somebody's checkout.
	 *
	 * @param int $product_id Product.
	 * @return int Vendor user id, or 0.
	 */
	public static function vendor_for_product( $product_id ) {
		switch ( self::detect() ) {
			case 'dokan':
				if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
					// The second argument asks for the id rather than a Vendor object. Without
					// it this returns an object, and casting one to int is meaningless.
					return max( 0, (int) dokan_get_vendor_by_product( $product_id, true ) );
				}
				return 0;
			case 'wcfm':
				if ( function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
					return max( 0, (int) wcfm_get_vendor_id_by_post( $product_id ) );
				}
				return 0;
			case 'wcvendors':
				if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_from_product' ) ) {
					// This one does not answer 0 for "nobody". It answers -1 when the id is not
					// a product at all, and 1 — which on most sites is the administrator — when
					// the post has gone missing. Taking either at face value would quote a
					// customer somebody else's address, so the answer has to be confirmed to be
					// a vendor before it is believed.
					$vendor = (int) WCV_Vendors::get_vendor_from_product( $product_id );
					if ( $vendor <= 0 ) {
						return 0;
					}
					if ( method_exists( 'WCV_Vendors', 'is_vendor' ) && ! WCV_Vendors::is_vendor( $vendor ) ) {
						return 0;
					}
					return $vendor;
				}
				return 0;
		}
		return 0;
	}

	/**
	 * A vendor's own receiving address for one coin.
	 *
	 * Validated here as well as when it was saved: it is read straight out of user meta, which
	 * more than one thing can write, and it decides where a customer's money goes.
	 *
	 * @param int    $vendor_id Vendor user id.
	 * @param string $coin_id   Coin ID.
	 * @return string '' when there is not a usable one.
	 */
	public static function address_for( $vendor_id, $coin_id ) {
		$vendor_id = (int) $vendor_id;
		$coin_id   = is_scalar( $coin_id ) ? (string) $coin_id : '';
		if ( $vendor_id <= 0 || '' === $coin_id ) {
			return '';
		}

		$wallets = get_user_meta( $vendor_id, self::USER_META, true );
		if ( ! is_array( $wallets ) || empty( $wallets[ $coin_id ] ) ) {
			return '';
		}

		$address = is_scalar( $wallets[ $coin_id ] ) ? trim( (string) $wallets[ $coin_id ] ) : '';
		return Xdwp_Wallets::is_plausible_address( $coin_id, $address ) ? $address : '';
	}

	/**
	 * Save a vendor's addresses, keeping only the ones that are addresses.
	 *
	 * @param int   $vendor_id Vendor user id.
	 * @param array $raw       Coin id => address.
	 * @return array<string, string> What was kept.
	 */
	public static function save_addresses( $vendor_id, array $raw ) {
		$vendor_id = (int) $vendor_id;
		if ( $vendor_id <= 0 ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $coin_id => $address ) {
			$coin_id = sanitize_text_field( (string) $coin_id );
			$address = is_scalar( $address ) ? trim( sanitize_text_field( (string) $address ) ) : '';
			if ( '' === $address || ! Xdwp_Coins::get( $coin_id ) ) {
				continue;
			}
			if ( ! Xdwp_Wallets::is_plausible_address( $coin_id, $address ) ) {
				continue;
			}
			$clean[ $coin_id ] = $address;
		}

		update_user_meta( $vendor_id, self::USER_META, $clean );

		return $clean;
	}

	/**
	 * The vendor an order was paid to, if it was paid to one.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	public static function paid_vendor( $order ) {
		return $order instanceof WC_Order ? (int) $order->get_meta( self::ORDER_META ) : 0;
	}

	/**
	 * A vendor's name, for a note somebody has to read.
	 *
	 * @param int $vendor_id Vendor user id.
	 * @return string
	 */
	public static function vendor_name( $vendor_id ) {
		$user = get_userdata( (int) $vendor_id );
		if ( ! $user ) {
			/* translators: %d: vendor user id */
			return sprintf( __( 'vendor #%d', 'xorro-direct-wallet-payments-woocommerce' ), (int) $vendor_id );
		}
		return (string) $user->display_name;
	}

	/**
	 * A coin's symbol, or its id when it has none.
	 *
	 * @param string $coin_id Coin ID.
	 * @return string
	 */
	private static function symbol( $coin_id ) {
		$coin = Xdwp_Coins::get( $coin_id );
		return $coin && isset( $coin['symbol'] ) ? (string) $coin['symbol'] : (string) $coin_id;
	}

	/**
	 * Record on the order why the money went where it did — once per reason.
	 *
	 * Quoting can be retried several times when addresses collide, and the same note repeated
	 * five times reads like five problems.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $text  Note.
	 */
	private static function note( $order, $text ) {
		$seen = (array) $order->get_meta( '_xdwp_vendor_notes' );
		$key  = md5( $text );
		if ( in_array( $key, $seen, true ) ) {
			return;
		}
		$seen[] = $key;
		$order->update_meta_data( '_xdwp_vendor_notes', $seen );
		$order->save();
		$order->add_order_note( $text );
	}
}
