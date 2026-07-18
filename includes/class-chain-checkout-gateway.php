<?php
/**
 * WooCommerce payment gateway.
 *
 * @package ChainCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Chain_Checkout_Gateway
 */
class Chain_Checkout_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = CHAIN_CHECKOUT_GATEWAY_ID;
		$this->method_title       = __( 'Chain Checkout', 'chain-checkout' );
		$this->method_description = __( 'Accept cryptocurrency payments directly to your own wallets with automatic on-chain verification.', 'chain-checkout' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = Chain_Checkout_Branding::title();
		$this->description = $this->get_option( 'description', Chain_Checkout_Settings::get( 'description', '' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );
		$this->icon        = Chain_Checkout_Branding::icon_url();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
	}

	/**
	 * Sized / mode-aware icon for classic checkout.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = Chain_Checkout_Branding::get_icon_html();
		/**
		 * Filter gateway icon HTML.
		 *
		 * @param string $icon Icon HTML.
		 * @param string $id   Gateway ID.
		 */
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Hide visible title when display mode is icon-only (kept in icon alt / aria).
	 *
	 * @param string $title Gateway title.
	 * @param string $id    Gateway ID.
	 * @return string
	 */
	public function filter_gateway_title( $title, $id ) {
		if ( $id !== $this->id ) {
			return $title;
		}
		if ( 'icon' === Chain_Checkout_Branding::display_mode() ) {
			return '';
		}
		return Chain_Checkout_Branding::title();
	}

	/**
	 * Gateway settings fields (WooCommerce → Payments).
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'chain-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Chain Checkout', 'chain-checkout' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'chain-checkout' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'chain-checkout' ),
				'default'     => __( 'Pay with Cryptocurrency', 'chain-checkout' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'chain-checkout' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'chain-checkout' ),
				'default'     => __( 'Pay directly to our wallet with cryptocurrency. No third-party processor.', 'chain-checkout' ),
			),
			'instructions'=> array(
				'title'       => __( 'Configuration', 'chain-checkout' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: admin URL */
					__( 'Configure coins, wallets, prices, and API keys under %s.', 'chain-checkout' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=chain-checkout' ) ) . '">' . esc_html__( 'Chain Checkout settings', 'chain-checkout' ) . '</a>'
				),
			),
		);
	}

	/**
	 * Sync title/description into plugin settings when saved.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$parent = parent::process_admin_options();
		Chain_Checkout_Settings::update(
			array(
				'title'       => $this->get_option( 'title' ),
				'description' => $this->get_option( 'description' ),
			)
		);
		return $parent;
	}

	/**
	 * Checkout assets when gateway is available.
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() || ! $this->is_available() ) {
			return;
		}

		if ( ! wp_style_is( 'chain-checkout-frontend', 'registered' ) ) {
			wp_register_style(
				'chain-checkout-frontend',
				CHAIN_CHECKOUT_URL . 'assets/css/frontend.css',
				array(),
				CHAIN_CHECKOUT_VERSION
			);
		}
		if ( ! wp_script_is( 'chain-checkout-checkout', 'registered' ) ) {
			wp_register_script(
				'chain-checkout-checkout',
				CHAIN_CHECKOUT_URL . 'assets/js/checkout.js',
				array( 'jquery' ),
				CHAIN_CHECKOUT_VERSION,
				true
			);
		}

		wp_enqueue_style( 'chain-checkout-frontend' );
		wp_enqueue_script( 'chain-checkout-checkout' );

		$branding = Chain_Checkout_Branding::frontend_data();
		$css      = sprintf(
			'.payment_method_%1$s img.chain-checkout-gateway-icon,.wc-block-components-radio-control-accordion-option[id*="chain_checkout"] img.chain-checkout-gateway-icon{width:%2$dpx!important;height:%3$dpx!important;max-width:%2$dpx!important;max-height:%3$dpx!important;object-fit:contain;}' .
			'.payment_method_%1$s label img:not(.chain-checkout-gateway-icon){max-width:%2$dpx;max-height:%3$dpx;width:auto;height:auto;object-fit:contain;vertical-align:middle;}',
			esc_attr( $this->id ),
			(int) $branding['iconWidth'],
			(int) $branding['iconHeight']
		);
		wp_add_inline_style( 'chain-checkout-frontend', $css );

		wp_localize_script(
			'chain-checkout-checkout',
			'chainCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'chain_checkout_checkout' ),
				'gateway' => $this->id,
			)
		);
	}

	/**
	 * Only available when at least one coin has a wallet.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}
		return parent::is_available() && ! empty( Chain_Checkout_Coins::get_payable() );
	}

	/**
	 * Resolve selected coin from classic checkout or Blocks payment data.
	 *
	 * @return string
	 */
	private function get_selected_coin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['chain_checkout_coin'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['chain_checkout_coin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['payment_data'] ) && is_array( $_POST['payment_data'] ) ) {
			$payment_data = wp_unslash( $_POST['payment_data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Associative form used by some Blocks paths.
			if ( isset( $payment_data['chain_checkout_coin'] ) && is_string( $payment_data['chain_checkout_coin'] ) ) {
				return sanitize_text_field( $payment_data['chain_checkout_coin'] );
			}

			foreach ( $payment_data as $row ) {
				if ( ! is_array( $row ) || empty( $row['key'] ) ) {
					continue;
				}
				if ( 'chain_checkout_coin' === $row['key'] && isset( $row['value'] ) ) {
					return sanitize_text_field( $row['value'] );
				}
			}
		}

		return '';
	}

	/**
	 * Coin picker fields on checkout.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p class="chain-checkout-desc">' . wp_kses_post( wpautop( $this->description ) ) . '</p>';
		}

		$coins = Chain_Checkout_Coins::get_payable();
		if ( empty( $coins ) ) {
			echo '<p>' . esc_html__( 'No cryptocurrencies are configured.', 'chain-checkout' ) . '</p>';
			return;
		}

		$selected = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['chain_checkout_coin'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_POST['chain_checkout_coin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		echo '<fieldset class="chain-checkout-coins" id="chain-checkout-coins">';
		echo '<legend>' . esc_html__( 'Select cryptocurrency', 'chain-checkout' ) . '</legend>';
		echo '<div class="chain-checkout-coin-grid">';

		$first = true;
		foreach ( $coins as $id => $coin ) {
			$checked = ( $selected === $id ) || ( ! $selected && $first );
			$first   = false;
			$icons   = Chain_Checkout_Coins::icon_meta( $id );
			$classes = array( 'chain-checkout-coin-option' );
			$style   = '';
			$inner   = '<span class="chain-checkout-coin-option__sr">' . esc_html( $coin['name'] ) . '</span>';

			if ( ! empty( $icons['icon'] ) ) {
				$style = 'background-image:url(' . esc_url( $icons['icon'] ) . ')';
				if ( ! empty( $icons['badge'] ) ) {
					$classes[] = 'chain-checkout-coin-option--stable';
					$style    .= ';--cc-badge:url(' . esc_url( $icons['badge'] ) . ')';
				}
			} else {
				$classes[] = 'chain-checkout-coin-option--text';
				$inner    .= '<span class="chain-checkout-coin-option__text" aria-hidden="true">' . esc_html( $coin['symbol'] ) . '</span>';
			}

			printf(
				'<label class="%1$s" style="%2$s" title="%3$s"><input type="radio" name="chain_checkout_coin" value="%4$s" %5$s />%6$s</label>',
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $style ),
				esc_attr( $coin['name'] ),
				esc_attr( $id ),
				checked( $checked, true, false ),
				$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
		}

		echo '</div>';
		echo '<p class="chain-checkout-quote" id="chain-checkout-quote" aria-live="polite"></p>';
		echo '</fieldset>';
	}

	/**
	 * Validate coin selection.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$coin_id = $this->get_selected_coin();
		$payable = Chain_Checkout_Coins::get_payable();

		if ( ! $coin_id || ! isset( $payable[ $coin_id ] ) ) {
			wc_add_notice( __( 'Please select a cryptocurrency to pay with.', 'chain-checkout' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Process checkout payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Invalid order.', 'chain-checkout' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$coin_id = $this->get_selected_coin();
		$payable = Chain_Checkout_Coins::get_payable();

		if ( ! $coin_id || ! isset( $payable[ $coin_id ] ) ) {
			wc_add_notice( __( 'Selected cryptocurrency is not available.', 'chain-checkout' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$ok = Chain_Checkout_Order::assign_payment( $order, $coin_id );
		if ( ! $ok ) {
			wc_add_notice( __( 'Unable to create crypto payment. Check wallet and price settings, then try again.', 'chain-checkout' ), 'error' );
			return array( 'result' => 'failure' );
		}

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
