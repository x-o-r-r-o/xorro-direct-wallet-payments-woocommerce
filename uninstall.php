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
delete_option( 'woocommerce_xdwp_payment_reminder_settings' );
delete_option( 'woocommerce_xdwp_partial_payment_settings' );
delete_option( 'woocommerce_xdwp_payment_alert_settings' );

wp_clear_scheduled_hook( 'xdwp_check_payments' );
wp_clear_scheduled_hook( 'xdwp_refresh_prices' );

global $wpdb;

$xdwp_patterns = array(
	'_transient_xdwp_',
	'_transient_timeout_xdwp_',
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
