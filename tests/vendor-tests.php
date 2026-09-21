#!/usr/bin/env php
<?php
/**
 * Paying a marketplace vendor directly.
 *
 * Almost every shop running this plugin is not a marketplace, so the first thing these tests
 * establish is that nothing changes for them: no filter, no lookup, no difference in the address
 * a customer is given. After that they attack the thing that would actually cost money — sending
 * a payment to the wrong person — from every direction I could find.
 *
 * Run: php tests/vendor-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );

$GLOBALS['xdwp_users']  = array();
$GLOBALS['xdwp_notes']  = array();
$GLOBALS['xdwp_stub']['filters'] = isset( $GLOBALS['xdwp_stub']['filters'] ) ? $GLOBALS['xdwp_stub']['filters'] : array();

/**
 * Read user meta.
 *
 * @param int    $user_id User.
 * @param string $key     Key.
 * @param bool   $single  Single.
 * @return mixed
 */
function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['xdwp_users'][ $user_id ][ $key ] ) ? $GLOBALS['xdwp_users'][ $user_id ][ $key ] : '';
}

/**
 * Write user meta.
 *
 * @param int    $user_id User.
 * @param string $key     Key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['xdwp_users'][ $user_id ][ $key ] = $value;
	return true;
}

/**
 * A user record.
 *
 * @param int $user_id User.
 * @return object|false
 */
function get_userdata( $user_id ) {
	return 7 === (int) $user_id ? (object) array( 'display_name' => 'Acme Supplies' ) : false;
}

/**
 * Enough of an order item to name a product.
 */
class Xdwp_Test_Item { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Product id.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Constructor.
	 *
	 * @param int $product_id Product id.
	 */
	public function __construct( $product_id ) {
		$this->product_id = (int) $product_id;
	}

	/**
	 * Product id.
	 *
	 * @return int
	 */
	public function get_product_id() {
		return $this->product_id;
	}
}

/**
 * Enough of an order for address routing.
 */
class WC_Order { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Items.
	 *
	 * @var array
	 */
	public $items = array();

	/**
	 * Meta.
	 *
	 * @var array
	 */
	public $meta = array();

	/**
	 * Constructor.
	 *
	 * @param array $product_ids Product ids in the order.
	 */
	public function __construct( array $product_ids = array() ) {
		foreach ( $product_ids as $id ) {
			$this->items[] = new Xdwp_Test_Item( $id );
		}
	}

	/**
	 * Items.
	 *
	 * @return array
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Read meta.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( $key ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	/**
	 * Write meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Persist.
	 */
	public function save() {}

	/**
	 * Note.
	 *
	 * @param string $text Text.
	 */
	public function add_order_note( $text ) {
		$GLOBALS['xdwp_notes'][] = $text;
	}
}

require_once XDWP_PATH . 'includes/class-xdwp-settings.php';
require_once XDWP_PATH . 'includes/class-xdwp-coins.php';
require_once XDWP_PATH . 'includes/class-xdwp-testmode.php';
require_once XDWP_PATH . 'includes/class-xdwp-hd.php';
require_once XDWP_PATH . 'includes/class-xdwp-wallets.php';
require_once XDWP_PATH . 'includes/class-xdwp-vendors.php';

$pass = 0;
$fail = 0;

/**
 * Assert.
 *
 * @param string $label Assertion.
 * @param bool   $cond  Result.
 * @param string $info  Detail on failure.
 */
function t( $label, $cond, $info = '' ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}
	++$fail;
	echo "[FAIL] {$label}" . ( '' !== $info ? " — {$info}" : '' ) . "\n";
}

$shop_btc   = 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';
$vendor_btc = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';

/**
 * Set the plugin up as a shop, with or without vendor payouts asked for.
 *
 * @param bool $want_vendors Whether the merchant switched it on.
 */
function shop( $want_vendors = false ) {
	global $shop_btc;
	update_option(
		'xdwp_settings',
		array(
			'enabled_coins'  => array( 'BTC' ),
			'wallets'        => array( 'BTC' => array( $shop_btc ) ),
			'vendor_payouts' => $want_vendors ? 'yes' : 'no',
		)
	);
	$GLOBALS['xdwp_notes'] = array();
}

// ---------------------------------------------------------------- an ordinary shop

shop( false );
t( 'no marketplace plugin is detected on an ordinary shop', '' === Xdwp_Vendors::detect() );
t( 'so vendor payouts are not in force', ! Xdwp_Vendors::enabled() );

$before = $GLOBALS['xdwp_stub']['filters'];
Xdwp_Vendors::init();
t( 'and init() hooks nothing at all', $before === $GLOBALS['xdwp_stub']['filters'] );

// The thing that actually matters to an ordinary shop: the address is its own, unchanged.
t( 'the shop is paid its own address', $shop_btc === Xdwp_Wallets::pick_address( 'BTC' ) );
t( 'and the same with an order in hand', $shop_btc === Xdwp_Wallets::pick_address( 'BTC', new WC_Order( array( 1 ) ) ) );

// Even if a merchant ticks the box, with no marketplace installed nothing happens.
shop( true );
t( 'ticking the box without a marketplace changes nothing', ! Xdwp_Vendors::enabled() );
t( 'and the shop is still paid its own address', $shop_btc === Xdwp_Wallets::pick_address( 'BTC', new WC_Order( array( 1 ) ) ) );

// ---------------------------------------------------------------- with a marketplace

// Declared inside a conditional on purpose. PHP defines an unconditional top-level function at
// compile time, so a plain declaration here would already exist while the tests above were
// checking that an ordinary shop detects no marketplace — and they would pass for the wrong
// reason, or fail confusingly. This defers it to exactly this point in the run.
if ( ! function_exists( 'dokan_get_seller_id_by_product' ) ) {
	/**
	 * Stand in for Dokan. Products 10 and 11 are vendor 7's; product 20 is vendor 9's;
	 * product 30 belongs to the shop.
	 *
	 * @param int $product_id Product.
	 * @return int
	 */
	function dokan_get_seller_id_by_product( $product_id ) {
		$map = array(
			10 => 7,
			11 => 7,
			20 => 9,
		);
		return isset( $map[ $product_id ] ) ? $map[ $product_id ] : 0;
	}
}

shop( true );
t( 'a marketplace is now detected', 'dokan' === Xdwp_Vendors::detect() );
t( 'and vendor payouts are in force', Xdwp_Vendors::enabled() );

// A vendor with no address saved must not stop the sale.
$order = new WC_Order( array( 10 ) );
t( 'a vendor with no address falls back to the shop', $shop_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );
t( 'and the order says why, by name', ! empty( $GLOBALS['xdwp_notes'] ) && false !== strpos( $GLOBALS['xdwp_notes'][0], 'Acme Supplies' ), wp_json_encode( $GLOBALS['xdwp_notes'] ) );

// Now give the vendor an address.
Xdwp_Vendors::save_addresses( 7, array( 'BTC' => $vendor_btc ) );
t( 'the vendor\'s address is kept', $vendor_btc === Xdwp_Vendors::address_for( 7, 'BTC' ) );

$order = new WC_Order( array( 10 ) );
t( 'their order is paid to them, not to the shop', $vendor_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );
t( 'and the order records who was paid', 7 === (int) $order->get_meta( Xdwp_Vendors::ORDER_META ) );

// Two items, same vendor, is still one vendor.
$order = new WC_Order( array( 10, 11 ) );
t( 'two items from one vendor still pay that vendor', $vendor_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );

// ---------------------------------------------------------------- what must never happen

// One transfer cannot be split. Paying it all to whichever vendor was found first would take
// money from the other one.
$GLOBALS['xdwp_notes'] = array();
Xdwp_Vendors::save_addresses( 9, array( 'BTC' => 'bc1q0000000000000000000000000000000000000' ) );
$order = new WC_Order( array( 10, 20 ) );
t( 'an order from two vendors is paid to the shop', $shop_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );
t( 'and nobody is recorded as having been paid', '' === (string) $order->get_meta( Xdwp_Vendors::ORDER_META ) );
t( 'and the order explains it', ! empty( $GLOBALS['xdwp_notes'] ) && false !== strpos( $GLOBALS['xdwp_notes'][0], 'more than one vendor' ), wp_json_encode( $GLOBALS['xdwp_notes'] ) );

// The shop's own product, on a marketplace, is still the shop's.
$order = new WC_Order( array( 30 ) );
t( 'the shop\'s own product pays the shop', $shop_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );

// An address that is not an address must never be quoted, however it got into user meta.
update_user_meta( 7, Xdwp_Vendors::USER_META, array( 'BTC' => 'not-an-address' ) );
t( 'a malformed stored address is ignored', '' === Xdwp_Vendors::address_for( 7, 'BTC' ) );
$order = new WC_Order( array( 10 ) );
t( 'and the shop is paid instead', $shop_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', $order ) );

// An address for the wrong chain is the most expensive mistake available here.
update_user_meta( 7, Xdwp_Vendors::USER_META, array( 'BTC' => '0x00000000000000000000000000000000000000e1' ) );
t( 'an Ethereum address saved under Bitcoin is refused', '' === Xdwp_Vendors::address_for( 7, 'BTC' ) );

// Saving is filtered the same way.
$kept = Xdwp_Vendors::save_addresses( 7, array( 'BTC' => 'nonsense', 'NOTACOIN' => $vendor_btc ) );
t( 'saving keeps neither a bad address nor an unknown coin', array() === $kept, wp_json_encode( $kept ) );

// Nothing without an order, and nothing without a vendor.
t( 'no order means the shop is paid', $shop_btc === Xdwp_Vendors::route( $shop_btc, 'BTC', null ) );
t( 'a vendor id of zero has no address', '' === Xdwp_Vendors::address_for( 0, 'BTC' ) );
t( 'and neither does a negative one', '' === Xdwp_Vendors::address_for( -1, 'BTC' ) );

// ---------------------------------------------------------------- the guard rail above it

// Even if routing returned rubbish, pick_address() refuses to quote it.
add_filter(
	'xdwp_receiving_address',
	static function () {
		return 'definitely-not-an-address';
	}
);
t( 'pick_address refuses an address that is not one, whoever supplied it', $shop_btc === Xdwp_Wallets::pick_address( 'BTC', new WC_Order( array( 10 ) ) ) );

$src = file_get_contents( XDWP_PATH . 'includes/class-xdwp-wallets.php' );
t( 'and that refusal is in the code, not only in this test', false !== strpos( $src, 'Fail back to the shop rather than quoting an address nothing checked' ) );

// A renamed upstream function must switch routing off, not break a checkout.
$vendors_src = file_get_contents( XDWP_PATH . 'includes/class-xdwp-vendors.php' );
foreach ( array( 'dokan_get_seller_id_by_product', 'wcfm_get_vendor_id_by_post' ) as $fn ) {
	t( "the {$fn} call is guarded", false !== strpos( $vendors_src, "function_exists( '" . $fn . "' )" ) );
}
t( 'the WC Vendors call is guarded too', false !== strpos( $vendors_src, "method_exists( 'WCV_Vendors', 'get_vendor_from_product' )" ) );

// ---------------------------------------------------------------- entering an address

$vsrc = file_get_contents( XDWP_PATH . 'includes/class-xdwp-vendors.php' );
t( 'a vendor can enter addresses on their own profile', false !== strpos( $vsrc, "add_action( 'show_user_profile'" ) );
t( 'and the marketplace owner can enter one for them', false !== strpos( $vsrc, "add_action( 'edit_user_profile'" ) );
// Editing somebody else's payout address is exactly the thing to get wrong.
t( 'editing another user\'s address needs the capability for it', 2 === substr_count( $vsrc, "current_user_can( 'edit_users' )" ), (string) substr_count( $vsrc, "current_user_can( 'edit_users' )" ) );
t( 'saving is behind a nonce', false !== strpos( $vsrc, "wp_verify_nonce(" ) && false !== strpos( $vsrc, "'xdwp_vendor_wallets'" ) );
t( 'and everything saved goes through the same validation', false !== strpos( $vsrc, 'self::save_addresses( $user_id' ) );
// Those fields exist only where they mean something.
t( 'the fields are hooked only when vendor payouts are on', strpos( $vsrc, 'if ( ! self::enabled() ) {' ) < strpos( $vsrc, "add_action( 'show_user_profile'" ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL VENDOR TESTS PASSED ({$pass})\n";
exit( 0 );
