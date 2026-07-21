<?php
/**
 * Privacy Policy suggested content for WordPress Settings → Privacy.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Privacy
 */
class Xdwp_Privacy {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy_content' ) );
	}

	/**
	 * Suggest privacy policy text (Guideline: third-party services).
	 */
	public static function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'Xorro Direct Wallet Payments for WooCommerce lets customers pay with cryptocurrency directly to store wallets. The plugin does not send data to the plugin author’s servers.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';

		$content .= '<p><strong>' . esc_html__( 'What is stored locally', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong></p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Public cryptocurrency receiving addresses you configure.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Order metadata needed to match payments (selected coin, quoted amount, assigned address, payment status).', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '</ul>';

		$content .= '<p><strong>' . esc_html__( 'Third-party services', 'xorro-direct-wallet-payments-woocommerce' ) . '</strong></p>';
		$content .= '<p>' . esc_html__( 'When a customer uses this payment method, or when automatic verification is enabled, the store may contact public blockchain and price APIs. Typical data includes coin identifiers, wallet addresses, transaction IDs, and fiat currency codes. Optional API keys you add are sent only to the matching provider.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'CoinGecko — exchange rates for crypto quotes.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Etherscan API V2 — EVM chain payment detection.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'mempool.space / Blockstream — Bitcoin payment detection.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Blockchair — Bitcoin Cash / Litecoin / Dogecoin payment detection.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'TronGrid, Solana RPC / Helius, and other public explorers/RPCs for supported networks.', 'xorro-direct-wallet-payments-woocommerce' ) . '</li>';
		$content .= '</ul>';

		$content .= '<p>' . esc_html__( 'Automatic on-chain verification can be disabled under Xorro Wallet Payments → General. Disabling the payment gateway stops these checkout-related requests.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';

		wp_add_privacy_policy_content( 'Xorro Wallet Payments', wp_kses_post( $content ) );
	}
}
