<?php
/**
 * Take the configuration out, and put it back.
 *
 * Included by General, Wallets and Prices & APIs. Each tab opens it on the part a merchant is
 * already looking at — the Wallets tab offers the addresses, Prices & APIs offers the keys —
 * because someone moving wallets to a staging site does not think of it as "settings backup",
 * and would not find it on another screen.
 *
 * Expects $xdwp_backup_scope: the scope this tab defaults to.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

$xdwp_backup_scope = isset( $xdwp_backup_scope ) ? Xdwp_Backup::scope( $xdwp_backup_scope ) : Xdwp_Backup::SCOPE_ALL;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$xdwp_restored = isset( $_GET['xdwp_restore'] ) ? sanitize_key( wp_unslash( $_GET['xdwp_restore'] ) ) : '';
$xdwp_message  = '' !== $xdwp_restored ? Xdwp_Backup::message( $xdwp_restored ) : '';

$xdwp_backup_titles = array(
	Xdwp_Backup::SCOPE_ALL     => __( 'Backup and restore', 'xorro-direct-wallet-payments-woocommerce' ),
	Xdwp_Backup::SCOPE_WALLETS => __( 'Move these addresses to another site', 'xorro-direct-wallet-payments-woocommerce' ),
	Xdwp_Backup::SCOPE_KEYS    => __( 'Move these keys to another site', 'xorro-direct-wallet-payments-woocommerce' ),
);
$xdwp_backup_leads  = array(
	Xdwp_Backup::SCOPE_ALL     => __( 'Everything on these screens — coins, wallet addresses, extended keys, limits, confirmations and prices — in one file. Keep a copy before a big change, or use it to set up a second shop without doing it all again.', 'xorro-direct-wallet-payments-woocommerce' ),
	Xdwp_Backup::SCOPE_WALLETS => __( 'Just the receiving addresses and extended public keys, with none of this shop\'s own limits, confirmations or alerts. Useful for pointing a staging site at the same wallets, or for rebuilding a shop without re-typing every address.', 'xorro-direct-wallet-payments-woocommerce' ),
	Xdwp_Backup::SCOPE_KEYS    => __( 'Just the API keys and tokens, with nothing else. Useful for putting the same keys on a second site, or for handing them to someone setting one up for you — and for taking them back afterwards.', 'xorro-direct-wallet-payments-woocommerce' ),
);
?>
<div class="xdwp-backup">
	<h3 class="xdwp-backup__title"><?php echo esc_html( $xdwp_backup_titles[ $xdwp_backup_scope ] ); ?></h3>
	<p class="xdwp-backup__lead"><?php echo esc_html( $xdwp_backup_leads[ $xdwp_backup_scope ] ); ?></p>

	<?php if ( '' !== $xdwp_message ) : ?>
		<div class="notice notice-<?php echo Xdwp_Backup::is_success( $xdwp_restored ) ? 'success' : 'error'; ?> inline">
			<p><?php echo esc_html( $xdwp_message ); ?></p>
		</div>
	<?php endif; ?>

	<div class="xdwp-backup__row">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="xdwp-backup__form">
			<input type="hidden" name="action" value="xdwp_export_settings" />
			<?php wp_nonce_field( 'xdwp_export_settings' ); ?>

			<p class="xdwp-backup__field">
				<label for="xdwp-export-scope-<?php echo esc_attr( $xdwp_backup_scope ); ?>">
					<?php esc_html_e( 'What to include', 'xorro-direct-wallet-payments-woocommerce' ); ?>
				</label>
				<select name="scope" id="xdwp-export-scope-<?php echo esc_attr( $xdwp_backup_scope ); ?>" class="xdwp-backup__scope">
					<?php foreach ( Xdwp_Backup::scopes() as $xdwp_scope_key => $xdwp_scope_label ) : ?>
						<option value="<?php echo esc_attr( $xdwp_scope_key ); ?>" <?php selected( $xdwp_scope_key, $xdwp_backup_scope ); ?>>
							<?php echo esc_html( $xdwp_scope_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<button type="submit" class="cc-btn cc-btn-secondary"><?php esc_html_e( 'Download file', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>

			<label class="xdwp-backup__check">
				<input type="checkbox" name="secrets" value="1" <?php checked( Xdwp_Backup::SCOPE_KEYS, $xdwp_backup_scope ); ?> />
				<?php esc_html_e( 'Include API keys', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</label>

			<p class="description">
				<?php esc_html_e( 'API keys are left out unless you tick the box, so a file you email or store in a repository carries no secrets. Choosing "API keys and tokens only" always includes them — that is the point of it — so treat that file as a password. There is never a private key in any of these files: this plugin does not hold one.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'A key set in wp-config.php is not written to any file. It stays where you put it.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="xdwp-backup__form">
			<input type="hidden" name="action" value="xdwp_import_settings" />
			<?php wp_nonce_field( 'xdwp_import_settings' ); ?>
			<label for="xdwp-settings-file-<?php echo esc_attr( $xdwp_backup_scope ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Settings file', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</label>
			<input type="file" name="xdwp_settings_file" id="xdwp-settings-file-<?php echo esc_attr( $xdwp_backup_scope ); ?>" accept="application/json,.json" required />
			<button type="submit" class="cc-btn cc-btn-secondary"><?php esc_html_e( 'Restore from file', 'xorro-direct-wallet-payments-woocommerce' ); ?></button>
			<p class="description">
				<?php esc_html_e( 'Any of these files can be restored here — the file says which kind it is. Only what the file contains is changed; anything it leaves out stays exactly as it is on this site. Addresses and keys are checked again on the way in, as if you had typed them.', 'xorro-direct-wallet-payments-woocommerce' ); ?>
			</p>
		</form>
	</div>
</div>
