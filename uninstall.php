<?php
/**
 * Uninstall Xorro Wallet Payments — remove options and transients only.
 * Order meta is left intact for accounting history.
 *
 * @package Xdwp
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'xdwp_settings' );
delete_option( 'xdwp_version' );
delete_option( 'xdwp_amount_seq' );
delete_option( 'woocommerce_xdwp_settings' );
delete_option( 'xdwp_cron_running' );
delete_option( 'xdwp_explorer_errors' );
delete_option( 'xdwp_coingecko_tier' );
delete_option( 'woocommerce_xdwp_payment_reminder_settings' );
delete_option( 'woocommerce_xdwp_partial_payment_settings' );
delete_option( 'woocommerce_xdwp_payment_alert_settings' );

wp_clear_scheduled_hook( 'xdwp_check_payments' );
wp_clear_scheduled_hook( 'xdwp_refresh_prices' );
wp_clear_scheduled_hook( 'xdwp_daily_digest' );
wp_clear_scheduled_hook( 'xdwp_send_notification' );

global $wpdb;

// Deliberately NOT removed: xdwp_hd_idx_* and xdwp_hd_paid_idx_*, which record how far along
// the merchant's extended public key addresses have been handed out. Deleting those would send
// a reinstall back to index 0 and derive addresses that already belong to past orders — a late
// payment to one of them could then be read as payment for a new order. They are kept for the
// same reason order meta is: they describe money that already moved.
$xdwp_patterns = array(
	'_transient_xdwp_',
	'_transient_timeout_xdwp_',
	// Rotation position within the merchant's own fixed address list — safe to reset.
	'xdwp_wallet_idx_',
	'xdwp_paying_',
	'xdwp_txid_claim_',
	'xdwp_amt_',
	'xdwp_ambiguous_',
	'xdwp_partial_',
);

foreach ( $xdwp_patterns as $xdwp_prefix ) {
	$xdwp_like = $wpdb->esc_like( $xdwp_prefix ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$xdwp_like
		)
	);
}
