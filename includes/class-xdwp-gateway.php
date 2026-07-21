<?php
/**
 * WooCommerce payment gateway.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Gateway
 */
class Xdwp_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = XDWP_GATEWAY_ID;
		$this->method_title       = __( 'Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' );
		$this->method_description = __( 'Accept cryptocurrency payments directly to your own wallets with automatic on-chain verification.', 'xorro-direct-wallet-payments-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = Xdwp_Branding::title();
		$this->description = $this->get_option( 'description', Xdwp_Settings::get( 'description', '' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );
		$this->icon        = Xdwp_Branding::icon_url();

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
		$icon = Xdwp_Branding::get_icon_html();
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
		if ( 'icon' === Xdwp_Branding::display_mode() ) {
			return '';
		}
		return Xdwp_Branding::title();
	}

	/**
	 * Gateway settings fields (WooCommerce → Payments).
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'xorro-direct-wallet-payments-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Xorro Wallet Payments', 'xorro-direct-wallet-payments-woocommerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'xorro-direct-wallet-payments-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'xorro-direct-wallet-payments-woocommerce' ),
				'default'     => __( 'Pay with Cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'xorro-direct-wallet-payments-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'xorro-direct-wallet-payments-woocommerce' ),
				'default'     => __( 'Pay directly to our wallet with cryptocurrency. No third-party processor.', 'xorro-direct-wallet-payments-woocommerce' ),
			),
			'instructions'=> array(
				'title'       => __( 'Configuration', 'xorro-direct-wallet-payments-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: admin URL */
					__( 'Configure coins, wallets, prices, and API keys under %s.', 'xorro-direct-wallet-payments-woocommerce' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce' ) ) . '">' . esc_html__( 'Xorro Wallet Payments settings', 'xorro-direct-wallet-payments-woocommerce' ) . '</a>'
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
		Xdwp_Settings::update(
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
		if ( is_admin() || ! $this->should_load_checkout_assets() ) {
			return;
		}

		$this->register_checkout_assets();
		wp_enqueue_style( 'xdwp-frontend' );
		wp_enqueue_script( 'xdwp-checkout' );

		$branding = Xdwp_Branding::frontend_data();
		$css      = sprintf(
			'.payment_method_%1$s img.xdwp-gateway-icon,.wc-block-components-radio-control-accordion-option[id*="xdwp"] img.xdwp-gateway-icon{width:%2$dpx!important;height:%3$dpx!important;max-width:%2$dpx!important;max-height:%3$dpx!important;object-fit:contain;}' .
			'.payment_method_%1$s .xdwp-coin-option__icon img,.payment_method_%1$s .xdwp-coin-option__badge img,.xdwp-blocks .xdwp-coin-option__icon img,.xdwp-blocks .xdwp-coin-option__badge img{max-width:none!important;width:28px!important;height:28px!important;object-fit:contain!important;}' .
			'.payment_method_%1$s .xdwp-coin-option__badge img,.xdwp-blocks .xdwp-coin-option__badge img{width:14px!important;height:14px!important;}',
			esc_attr( $this->id ),
			(int) $branding['iconWidth'],
			(int) $branding['iconHeight']
		);
		wp_add_inline_style( 'xdwp-frontend', $css );

		wp_localize_script(
			'xdwp-checkout',
			'xdwp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'xdwp_checkout' ),
				'gateway' => $this->id,
			)
		);
	}

	/**
	 * Whether checkout CSS/JS should load on this request.
	 *
	 * @return bool
	 */
	private function should_load_checkout_assets() {
		if ( is_checkout() || is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		global $post;
		if ( $post instanceof WP_Post && function_exists( 'has_shortcode' ) && has_shortcode( (string) $post->post_content, 'woocommerce_checkout' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Register checkout style/script handles.
	 */
	private function register_checkout_assets() {
		$ver = XDWP_VERSION;
		$css = XDWP_PATH . 'assets/css/frontend.css';
		if ( is_readable( $css ) ) {
			$ver = XDWP_VERSION . '.' . (string) filemtime( $css );
		}

		if ( ! wp_style_is( 'xdwp-frontend', 'registered' ) ) {
			wp_register_style(
				'xdwp-frontend',
				XDWP_URL . 'assets/css/frontend.css',
				array(),
				$ver
			);
		}
		if ( ! wp_script_is( 'xdwp-checkout', 'registered' ) ) {
			$js_ver = XDWP_VERSION;
			$js     = XDWP_PATH . 'assets/js/checkout.js';
			if ( is_readable( $js ) ) {
				$js_ver = XDWP_VERSION . '.' . (string) filemtime( $js );
			}
			wp_register_script(
				'xdwp-checkout',
				XDWP_URL . 'assets/js/checkout.js',
				array( 'jquery' ),
				$js_ver,
				true
			);
		}
	}

	/**
	 * Critical coin-picker CSS via wp_add_inline_style (works even if the stylesheet is late).
	 */
	private function print_critical_coin_css() {
		static $added = false;
		if ( $added ) {
			return;
		}
		$added = true;

		if ( ! wp_style_is( 'xdwp-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'xdwp-frontend' );
		}

		$css = '.xdwp-coin-grid{display:flex;flex-wrap:wrap;margin:-5px;}'
			. '.xdwp-coin-option{position:relative;display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:48px;height:54px;margin:5px;padding:0;cursor:pointer;border:1px solid #ddd;border-radius:5px;background:#f7f7f7;overflow:hidden;}'
			. '.xdwp-coin-option input{position:absolute;opacity:0;width:0;height:0;margin:0;padding:0;appearance:none;}'
			. '.xdwp-coin-option:hover,.xdwp-coin-option:has(input:checked),.xdwp-coin-option.is-selected{border-color:#000;}'
			. '.xdwp-coin-option__icon{display:flex;align-items:center;justify-content:center;width:100%;height:100%;pointer-events:none;}'
			. '.xdwp-coin-option__icon img{width:28px!important;height:28px!important;max-width:28px!important;max-height:28px!important;object-fit:contain!important;display:block;}'
			. '.xdwp-coin-option__badge{position:absolute;top:-1px;right:-1px;width:22px;height:22px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #ddd;border-radius:5px 0 5px 0;pointer-events:none;}'
			. '.xdwp-coin-option__badge img{width:14px!important;height:14px!important;object-fit:contain!important;display:block;}'
			. '.xdwp-coin-option__sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;}'
			. '.xdwp-coin-option--text{width:auto;min-width:48px;height:auto;min-height:54px;padding:8px 10px;}'
			. '.xdwp-coin-option__text{font-size:11px;font-weight:700;line-height:1.2;color:#111;}';

		wp_add_inline_style( 'xdwp-frontend', $css );
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
		return parent::is_available() && ! empty( Xdwp_Coins::get_payable() );
	}

	/**
	 * Resolve selected coin from classic checkout or Blocks payment data.
	 *
	 * @return string
	 */
	private function get_selected_coin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['xdwp_coin'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['xdwp_coin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['payment_data'] ) && is_array( $_POST['payment_data'] ) ) {
			$payment_data = wp_unslash( $_POST['payment_data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Associative form used by some Blocks paths.
			if ( isset( $payment_data['xdwp_coin'] ) && is_string( $payment_data['xdwp_coin'] ) ) {
				return sanitize_text_field( $payment_data['xdwp_coin'] );
			}

			foreach ( $payment_data as $row ) {
				if ( ! is_array( $row ) || empty( $row['key'] ) ) {
					continue;
				}
				if ( 'xdwp_coin' === $row['key'] && isset( $row['value'] ) ) {
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
		// Ensure styles even on custom checkout pages where is_checkout() is false.
		$this->register_checkout_assets();
		if ( ! wp_style_is( 'xdwp-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'xdwp-frontend' );
		}
		$this->print_critical_coin_css();

		if ( $this->description ) {
			echo '<p class="xdwp-desc">' . wp_kses_post( wpautop( $this->description ) ) . '</p>';
		}

		$coins = Xdwp_Coins::get_payable();
		if ( empty( $coins ) ) {
			echo '<p>' . esc_html__( 'No cryptocurrencies are configured.', 'xorro-direct-wallet-payments-woocommerce' ) . '</p>';
			return;
		}

		$selected = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['xdwp_coin'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_POST['xdwp_coin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		echo '<fieldset class="xdwp-coins" id="xdwp-coins">';
		echo '<legend>' . esc_html__( 'Select cryptocurrency', 'xorro-direct-wallet-payments-woocommerce' ) . '</legend>';
		echo '<div class="xdwp-coin-grid">';

		$first = true;
		foreach ( $coins as $id => $coin ) {
			$checked = ( $selected === $id ) || ( ! $selected && $first );
			$first   = false;
			$icons   = Xdwp_Coins::icon_meta( $id );
			$classes = array( 'xdwp-coin-option' );
			$inner   = '<span class="xdwp-coin-option__sr">' . esc_html( $coin['name'] ) . '</span>';

			if ( ! empty( $icons['icon'] ) ) {
				$inner .= sprintf(
					'<span class="xdwp-coin-option__icon" aria-hidden="true"><img src="%1$s" alt="" width="28" height="28" decoding="async" style="width:28px;height:28px;max-width:28px;max-height:28px;object-fit:contain;display:block;" /></span>',
					esc_url( $icons['icon'] )
				);
				if ( ! empty( $icons['badge'] ) ) {
					$classes[] = 'xdwp-coin-option--stable';
					$inner    .= sprintf(
						'<span class="xdwp-coin-option__badge" aria-hidden="true"><img src="%1$s" alt="" width="14" height="14" decoding="async" style="width:14px;height:14px;max-width:14px;max-height:14px;object-fit:contain;display:block;" /></span>',
						esc_url( $icons['badge'] )
					);
				}
			} else {
				$classes[] = 'xdwp-coin-option--text';
				$inner    .= '<span class="xdwp-coin-option__text" aria-hidden="true">' . esc_html( $coin['symbol'] ) . '</span>';
			}

			printf(
				'<label class="%1$s" title="%2$s"><input type="radio" name="xdwp_coin" value="%3$s" %4$s />%5$s</label>',
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $coin['name'] ),
				esc_attr( $id ),
				checked( $checked, true, false ),
				$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
		}

		echo '</div>';
		echo '<p class="xdwp-quote" id="xdwp-quote" aria-live="polite"></p>';
		echo '</fieldset>';
	}

	/**
	 * Validate coin selection.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$coin_id = $this->get_selected_coin();
		$payable = Xdwp_Coins::get_payable();

		if ( ! $coin_id || ! isset( $payable[ $coin_id ] ) ) {
			wc_add_notice( __( 'Please select a cryptocurrency to pay with.', 'xorro-direct-wallet-payments-woocommerce' ), 'error' );
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
			wc_add_notice( __( 'Invalid order.', 'xorro-direct-wallet-payments-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$coin_id = $this->get_selected_coin();
		$payable = Xdwp_Coins::get_payable();

		if ( ! $coin_id || ! isset( $payable[ $coin_id ] ) ) {
			wc_add_notice( __( 'Selected cryptocurrency is not available.', 'xorro-direct-wallet-payments-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$ok = Xdwp_Order::assign_payment( $order, $coin_id );
		if ( ! $ok ) {
			wc_add_notice( __( 'Unable to create crypto payment. Check wallet and price settings, then try again.', 'xorro-direct-wallet-payments-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
