#!/usr/bin/env php
<?php
/**
 * Rehearsing what happens after a payment is confirmed.
 *
 * Confirming a rehearsal marks an order paid without a payment. That is the most dangerous
 * capability in the plugin, and the only thing standing between it and a customer's order is
 * that Xdwp_Rehearsal acts on nothing it did not create and flag as its own. Most of what is
 * checked here is that guarantee, from several directions.
 *
 * Run: php tests/rehearsal-tests.php
 *
 * @package Xdwp
 */

require __DIR__ . '/stubs.php';

define( 'XDWP_VERSION', 'test' );
define( 'XDWP_PATH', dirname( __DIR__ ) . '/' );
define( 'XDWP_GATEWAY_ID', 'xdwp' );

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

/**
 * Enough of a WooCommerce order to answer what Xdwp_Rehearsal asks of one.
 */
class WC_Order { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Meta.
	 *
	 * @var array
	 */
	public $meta = array();

	/**
	 * Whether delete() was called with force.
	 *
	 * @var bool
	 */
	public $deleted = false;

	/**
	 * Whether the order counts as paid.
	 *
	 * @var bool
	 */
	public $paid = false;

	/**
	 * Read a meta value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function get_meta( $key ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	/**
	 * Write a meta value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Persist. Nothing to do here.
	 */
	public function save() {}

	/**
	 * Order id.
	 *
	 * @return int
	 */
	public function get_id() {
		return 4242;
	}

	/**
	 * Whether it is paid.
	 *
	 * @return bool
	 */
	public function is_paid() {
		return $this->paid;
	}

	/**
	 * Delete it.
	 *
	 * @param bool $force Force.
	 */
	public function delete( $force = false ) {
		$this->deleted = (bool) $force;
	}
}

require_once XDWP_PATH . 'includes/class-xdwp-rehearsal.php';

// ---------------------------------------------------------------- the guarantee

$plain = new WC_Order();
t( 'an ordinary order is not a rehearsal', ! Xdwp_Rehearsal::is_rehearsal( $plain ) );

$flagged = new WC_Order();
$flagged->update_meta_data( Xdwp_Rehearsal::FLAG, 1 );
t( 'an order this class created is', Xdwp_Rehearsal::is_rehearsal( $flagged ) );

t( 'null is not a rehearsal', ! Xdwp_Rehearsal::is_rehearsal( null ) );
t( 'nor is a string', ! Xdwp_Rehearsal::is_rehearsal( 'order 42' ) );
t( 'nor is an array', ! Xdwp_Rehearsal::is_rehearsal( array() ) );

// An empty flag is not a flag: meta that exists but is blank must not qualify.
$blank = new WC_Order();
$blank->update_meta_data( Xdwp_Rehearsal::FLAG, '' );
t( 'an empty flag does not make an order a rehearsal', ! Xdwp_Rehearsal::is_rehearsal( $blank ) );

// ---------------------------------------------------------------- what the code may do

$src = file_get_contents( XDWP_PATH . 'includes/class-xdwp-rehearsal.php' );

// confirm() must never reach mark_paid() without having established the order is its own.
$confirm = substr( $src, strpos( $src, 'public static function confirm()' ) );
$confirm = substr( $confirm, 0, strpos( $confirm, "\n\t}" ) );
t( 'confirming checks the order is a rehearsal', false !== strpos( $confirm, 'is_rehearsal(' ), $confirm );
t( 'and does so before marking anything paid', strpos( $confirm, 'is_rehearsal(' ) < strpos( $confirm, 'mark_paid(' ) );
t( 'and takes no order from the caller at all', false === strpos( $confirm, 'function confirm( $' ) );

// Deleting has the same rule: it must not be usable to delete a customer's order.
$delete = substr( $src, strpos( $src, 'private static function delete_order(' ) );
$delete = substr( $delete, 0, strpos( $delete, "\n\t}" ) );
t( 'deleting refuses anything that is not a rehearsal', false !== strpos( $delete, 'if ( ! self::is_rehearsal( $order ) ) {' ) );
t( 'and gives back the amount the order had reserved', false !== strpos( $delete, 'release_amount_slot(' ) );

// The simulated transaction id must be unmistakable.
t( 'the simulated transaction id is obviously not one', false !== strpos( $src, "'rehearsal-' . bin2hex( random_bytes( 8 ) )" ) );
t( 'and the order records that no chain was read', false !== strpos( $src, 'No payment was received and no chain was read' ) );

// A rehearsal must never email a customer.
t( 'the rehearsal order is addressed to the shop, not a customer', false !== strpos( $src, "get_option( 'admin_email' )" ) );
t( 'and says so in the code', false !== strpos( $src, 'never email a customer' ) );

// Only one at a time, so repeated rehearsals cannot pile up looking like sales.
t( 'starting a rehearsal clears any earlier one', false !== strpos( $src, 'self::discard();' ) );

// ---------------------------------------------------------------- it stays out of the figures

$admin = file_get_contents( XDWP_PATH . 'includes/admin/class-xdwp-payments-admin.php' );
t( 'rehearsal orders are kept out of the payments list', false !== strpos( $admin, "'key'     => '_xdwp_rehearsal'," ) && false !== strpos( $admin, "'compare' => 'NOT EXISTS'," ) );
t( 'and out of what the shop took', false !== strpos( $admin, 'rehearsal IS NULL' ) );

// ---------------------------------------------------------------- the way in is guarded

$adminsrc = file_get_contents( XDWP_PATH . 'includes/admin/class-xdwp-admin.php' );
$handler  = substr( $adminsrc, strpos( $adminsrc, 'public static function handle_rehearse()' ) );
$handler  = substr( $handler, 0, strpos( $handler, "\n\t}" ) );
t( 'the handler checks the capability', false !== strpos( $handler, "current_user_can( 'manage_woocommerce' )" ) );
t( 'and the nonce', false !== strpos( $handler, "check_admin_referer( 'xdwp_rehearse' )" ) );
t( 'and refuses before doing anything', strpos( $handler, 'current_user_can' ) < strpos( $handler, 'Xdwp_Rehearsal::' ) );

$view = file_get_contents( XDWP_PATH . 'includes/admin/views/rehearsal-ui.php' );
t( 'every form on the screen carries a nonce', 3 === substr_count( $view, "wp_nonce_field( 'xdwp_rehearse' )" ), (string) substr_count( $view, "wp_nonce_field( 'xdwp_rehearse' )" ) );
t( 'the screen says no money is involved', false !== stripos( $view, 'No money is involved' ) );

echo "\n";
if ( $fail > 0 ) {
	echo "FAILED: {$fail} assertion(s), {$pass} passed\n";
	exit( 1 );
}
echo "ALL REHEARSAL TESTS PASSED ({$pass})\n";
exit( 0 );
