<?php
/**
 * Getting from "installed" to "taking payments".
 *
 * There are seven tabs of settings here and almost all of them have a sensible default. Exactly
 * three things do not, and a shop cannot take a single payment until all three are done: pick a
 * coin, give it an address, switch the gateway on. Everything else can wait.
 *
 * So this asks for those three, in that order, and proves the result works before saying so. It
 * writes into the ordinary settings at each step rather than keeping a draft of its own — there
 * is no half-finished wizard state to reconcile, and leaving halfway simply means the shop is
 * configured as far as you got.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * The setup wizard.
 */
class Xdwp_Wizard {

	/**
	 * The page slug.
	 */
	const PAGE = 'xorro-direct-wallet-payments-woocommerce-setup';

	/**
	 * Set once the merchant has finished or dismissed it, so it stops asking.
	 */
	const DONE = 'xdwp_setup_done';

	/**
	 * Hook the page and the prompt.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ), 20 );
		add_action( 'admin_post_xdwp_wizard', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'prompt' ) );
	}

	/**
	 * A hidden page: reachable by link, not a menu entry of its own.
	 */
	public static function register() {
		add_submenu_page(
			'',
			__( 'Set up Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
			__( 'Setup', 'xorro-direct-wallet-payments-woocommerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Where to send somebody who wants to start.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Whether the shop could take a payment right now.
	 *
	 * @return bool
	 */
	public static function ready() {
		return ! empty( Xdwp_Coins::get_payable() ) && self::gateway_on();
	}

	/**
	 * Whether WooCommerce is offering the gateway at checkout.
	 *
	 * @return bool
	 */
	public static function gateway_on() {
		$settings = get_option( 'woocommerce_' . XDWP_GATEWAY_ID . '_settings', array() );
		return is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	/**
	 * Offer the wizard until the shop can take a payment, or until it is waved away.
	 */
	public static function prompt() {
		if ( ! current_user_can( 'manage_woocommerce' ) || self::ready() || get_option( self::DONE ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		// Guideline 11: contextual, not everywhere. WooCommerce screens, the plugins list, and
		// this plugin's own pages — nowhere else.
		if ( false === strpos( $id, 'woocommerce' ) && false === strpos( $id, 'xorro-direct-wallet-payments' ) && 'plugins' !== $id ) {
			return;
		}
		// Its own page would be an odd place to be invited to it.
		if ( false !== strpos( $id, self::PAGE ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p><p><a href="%s" class="button button-primary">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'Xorro Wallet Payments is installed.', 'xorro-direct-wallet-payments-woocommerce' ),
			esc_html__( 'Three things are needed before it can take a payment: a coin, an address to receive it, and the gateway switched on. This walks through them.', 'xorro-direct-wallet-payments-woocommerce' ),
			esc_url( self::url() ),
			esc_html__( 'Set it up', 'xorro-direct-wallet-payments-woocommerce' ),
			esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'xdwp_wizard', 'do' => 'dismiss' ), admin_url( 'admin-post.php' ) ), 'xdwp_wizard' ) ),
			esc_html__( 'I will do it myself', 'xorro-direct-wallet-payments-woocommerce' )
		);
	}

	/**
	 * The steps, and whether each is done.
	 *
	 * @return array<int, array{key:string,title:string,done:bool}>
	 */
	public static function steps() {
		$enabled = Xdwp_Settings::get( 'enabled_coins', array() );
		return array(
			array(
				'key'   => 'coins',
				'title' => __( 'Choose a coin', 'xorro-direct-wallet-payments-woocommerce' ),
				'done'  => is_array( $enabled ) && ! empty( $enabled ),
			),
			array(
				'key'   => 'wallet',
				'title' => __( 'Say where the money goes', 'xorro-direct-wallet-payments-woocommerce' ),
				'done'  => ! empty( Xdwp_Coins::get_payable() ),
			),
			array(
				'key'   => 'enable',
				'title' => __( 'Switch it on', 'xorro-direct-wallet-payments-woocommerce' ),
				'done'  => self::gateway_on(),
			),
		);
	}

	/**
	 * Which step to show: the first unfinished one, unless asked for another.
	 *
	 * @return string
	 */
	public static function current_step() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chooses a screen, changes nothing.
		$asked = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		foreach ( self::steps() as $step ) {
			if ( $step['key'] === $asked ) {
				return $asked;
			}
		}
		foreach ( self::steps() as $step ) {
			if ( ! $step['done'] ) {
				return $step['key'];
			}
		}
		return 'done';
	}

	/**
	 * Take what a step was given.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}
		check_admin_referer( 'xdwp_wizard' );

		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : ( isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '' );
		$next = '';

		if ( 'dismiss' === $do ) {
			update_option( self::DONE, 1, false );
			wp_safe_redirect( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' ) );
			exit;
		}

		if ( 'coins' === $do ) {
			$coin = isset( $_POST['coin'] ) ? sanitize_text_field( wp_unslash( $_POST['coin'] ) ) : '';
			if ( Xdwp_Coins::get( $coin ) ) {
				$enabled = Xdwp_Settings::get( 'enabled_coins', array() );
				$enabled = is_array( $enabled ) ? $enabled : array();
				if ( ! in_array( $coin, $enabled, true ) ) {
					$enabled[] = $coin;
				}
				Xdwp_Settings::update( array( 'enabled_coins' => array_values( $enabled ) ) );
			}
			$next = 'wallet';
		}

		if ( 'wallet' === $do ) {
			$coin    = isset( $_POST['coin'] ) ? sanitize_text_field( wp_unslash( $_POST['coin'] ) ) : '';
			$address = isset( $_POST['address'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['address'] ) ) ) : '';
			// Through the ordinary sanitiser, so an address the Wallets tab would refuse is
			// refused here too — a wizard that accepts what the settings form rejects would be
			// worse than no wizard.
			if ( Xdwp_Coins::get( $coin ) && '' !== $address ) {
				$clean = Xdwp_Settings::sanitize( array( 'wallets' => array( $coin => array( $address ) ) ) );
				Xdwp_Settings::update( $clean );
			}
			$next = Xdwp_Wallets::is_plausible_address( $coin, $address ) ? 'enable' : 'wallet';
		}

		if ( 'enable' === $do ) {
			$settings = get_option( 'woocommerce_' . XDWP_GATEWAY_ID . '_settings', array() );
			$settings = is_array( $settings ) ? $settings : array();
			$settings['enabled'] = 'yes';
			update_option( 'woocommerce_' . XDWP_GATEWAY_ID . '_settings', $settings );
			update_option( self::DONE, 1, false );
			$next = 'done';
		}

		wp_safe_redirect( add_query_arg( 'step', $next, self::url() ) );
		exit;
	}

	/**
	 * Draw the wizard.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		require XDWP_PATH . 'includes/admin/views/wizard.php';
	}
}
