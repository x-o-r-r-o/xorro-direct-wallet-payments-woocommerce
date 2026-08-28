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
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Order meta keys this plugin writes, with export labels.
	 *
	 * @return array<string, string>
	 */
	private static function meta_keys() {
		return array(
			'_xdwp_coin'         => __( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_address'      => __( 'Receiving address', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_amount'       => __( 'Quoted amount', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_status'       => __( 'Payment status', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_txid'         => __( 'On-chain transaction ID', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_started'      => __( 'Payment window started', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_expires'      => __( 'Payment window expires', 'xorro-direct-wallet-payments-woocommerce' ),
			'_xdwp_confirmed_at' => __( 'Confirmed at', 'xorro-direct-wallet-payments-woocommerce' ),
		);
	}

	/**
	 * Orders paid through this gateway for a given billing email, one page at a time.
	 *
	 * @param string $email_address Billing email.
	 * @param int    $page          1-indexed page.
	 * @return WC_Order[]
	 */
	private static function orders_for_email( $email_address, $page ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return wc_get_orders(
			array(
				'billing_email'  => $email_address,
				'payment_method' => defined( 'XDWP_GATEWAY_ID' ) ? XDWP_GATEWAY_ID : 'xdwp',
				'limit'          => 10,
				'page'           => max( 1, (int) $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'return'         => 'objects',
			)
		);
	}

	/**
	 * Register the "Export Personal Data" exporter (Tools → Export Personal Data).
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['xorro-direct-wallet-payments-woocommerce'] = array(
			'exporter_friendly_name' => __( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			'callback'               => array( __CLASS__, 'export_order_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the "Erase Personal Data" eraser (Tools → Erase Personal Data).
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['xorro-direct-wallet-payments-woocommerce'] = array(
			'eraser_friendly_name' => __( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			'callback'             => array( __CLASS__, 'erase_order_data' ),
		);
		return $erasers;
	}

	/**
	 * Export this plugin's per-order payment metadata for a customer's orders.
	 *
	 * Scoped to orders paid through this gateway matching the requested email,
	 * the same way WooCommerce's own core order exporter is scoped — never
	 * exposes another customer's data.
	 *
	 * @param string $email_address Billing email being exported.
	 * @param int    $page          1-indexed page.
	 * @return array{data: array, done: bool}
	 */
	public static function export_order_data( $email_address, $page = 1 ) {
		$orders = self::orders_for_email( $email_address, $page );
		$data   = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! Xdwp_Order::is_ours( $order ) ) {
				continue;
			}
			$items = array();
			foreach ( self::meta_keys() as $key => $label ) {
				$value = $order->get_meta( $key );
				if ( '' === $value || null === $value ) {
					continue;
				}
				$items[] = array(
					'name'  => $label,
					'value' => (string) $value,
				);
			}
			if ( empty( $items ) ) {
				continue;
			}
			$data[] = array(
				'group_id'    => 'xdwp-orders',
				'group_label' => __( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
				'item_id'     => 'xdwp-order-' . $order->get_id(),
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $orders ) < 10,
		);
	}

	/**
	 * Handle an "Erase Personal Data" request for this plugin's order metadata.
	 *
	 * Deliberately does not delete the payment fields: `uninstall.php` keeps
	 * this same data intact on plugin removal specifically for the
	 * merchant's accounting/dispute history, and an in-flight ("awaiting")
	 * order still needs its coin/address/amount to complete on-chain
	 * verification. Deleting it here would both contradict that documented
	 * retention decision and could break an active payment. Report the
	 * request as handled with the fields retained and why, matching how
	 * WooCommerce's own core eraser treats order/financial records.
	 *
	 * @param string $email_address Billing email being erased.
	 * @param int    $page          1-indexed page.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase_order_data( $email_address, $page = 1 ) {
		$orders   = self::orders_for_email( $email_address, $page );
		$retained = false;

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! Xdwp_Order::is_ours( $order ) ) {
				continue;
			}
			foreach ( array_keys( self::meta_keys() ) as $key ) {
				$value = $order->get_meta( $key );
				if ( '' !== $value && null !== $value ) {
					$retained = true;
					break;
				}
			}
		}

		$messages = array();
		if ( $retained ) {
			$messages[] = __( 'Xorro Wallet Payments: coin, address, amount, and transaction-ID metadata on crypto orders was retained for payment verification and merchant accounting/dispute records, consistent with how order data is kept after uninstalling the plugin.', 'xorro-direct-wallet-payments-woocommerce' );
		}

		return array(
			'items_removed'  => false,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => count( $orders ) < 10,
		);
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
