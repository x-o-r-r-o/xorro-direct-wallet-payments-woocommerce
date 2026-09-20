<?php
/**
 * Payments overview: every crypto order in one place, and a column on the orders list.
 *
 * WooCommerce's own order list shows a status but not which coin was chosen, how much is
 * owed on chain, or which orders are waiting on the store owner. This adds both.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Payments_Admin
 */
class Xdwp_Payments_Admin {

	/** Orders per page on the overview. */
	const PER_PAGE = 20;

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Orders list column, for both the legacy post table and HPOS.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_column' ), 20, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_column' ), 20, 2 );

		add_action( 'admin_post_xdwp_export_payments', array( __CLASS__, 'export_csv' ) );
		// The count on the menu is recalculated when an order's payment state changes.
		// The flag itself is kept up to date by Xdwp_Order, which runs during cron too.
	}

	/**
	 * How many orders are waiting on the store owner, cached — this is read on every admin
	 * page load, and the underlying query is not free.
	 *
	 * @return int
	 */
	public static function attention_count() {
		$cached = get_transient( 'xdwp_attention_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$result = self::query( array( 'filter' => 'attention' ) );
		$count  = (int) $result['total'];
		set_transient( 'xdwp_attention_count', $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Drop the cached count after anything that could change it.
	 */
	public static function forget_attention_count() {
		delete_transient( 'xdwp_attention_count' );
	}

	/**
	 * Download the current view as a spreadsheet.
	 */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to export payments.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}
		check_admin_referer( 'xdwp_export_payments' );

		$filter = isset( $_GET['xdwp_filter'] ) ? sanitize_key( wp_unslash( $_GET['xdwp_filter'] ) ) : 'all';
		$coin   = isset( $_GET['xdwp_coin'] ) ? sanitize_text_field( wp_unslash( $_GET['xdwp_coin'] ) ) : '';
		if ( ! in_array( $filter, array( 'all', 'awaiting', 'underpaid', 'paid', 'expired', 'attention' ), true ) ) {
			$filter = 'all';
		}
		if ( '' !== $coin && ! Xdwp_Coins::get( $coin ) ) {
			$coin = '';
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=xorro-wallet-payments-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv(
			$out,
			array(
				__( 'Order', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Date', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Order status', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Customer', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Email', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Order total', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Currency', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Coin', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Network', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Expected', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Received', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Payment state', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Needs attention', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Address', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Destination tag / memo', 'xorro-direct-wallet-payments-woocommerce' ),
				__( 'Transaction', 'xorro-direct-wallet-payments-woocommerce' ),
			)
		);

		// Paged so a store with thousands of crypto orders does not load them all at once.
		for ( $page = 1; $page <= 250; $page++ ) {
			$result = self::query(
				array(
					'filter' => $filter,
					'coin'   => $coin,
					'paged'  => $page,
				)
			);
			if ( empty( $result['orders'] ) ) {
				break;
			}
			foreach ( $result['orders'] as $order ) {
				$coin_def = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
				$row      = array(
						$order->get_order_number(),
						$order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
						$order->get_status(),
						trim( $order->get_formatted_billing_full_name() ),
						$order->get_billing_email(),
						$order->get_total(),
						$order->get_currency(),
						$coin_def ? $coin_def['symbol'] : '',
						$coin_def ? $coin_def['network'] : '',
						(string) Xdwp_Order::meta( $order, 'amount' ),
						(string) Xdwp_Order::meta( $order, 'received' ),
						(string) Xdwp_Order::meta( $order, 'status' ),
						self::needs_attention( $order ) ? 'yes' : 'no',
						(string) Xdwp_Order::meta( $order, 'address' ),
						(string) Xdwp_Order::meta( $order, 'memo' ),
						(string) Xdwp_Order::meta( $order, 'txid' ),
				);
				fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $row ) );
			}
			if ( $page >= (int) $result['pages'] ) {
				break;
			}
		}
		fclose( $out );
		exit;
	}

	/**
	 * One cell, safe to open in a spreadsheet.
	 *
	 * Excel, LibreOffice and Numbers run a cell beginning with =, +, - or @ as a formula.
	 * Customer names and memos come from whoever placed the order, so a name like
	 * =HYPERLINK(...) would otherwise execute on the merchant's machine, with the rest of
	 * this export — every customer's email, address and transaction — in reach.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Add the crypto payment column after the order status.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['xdwp_payment'] = __( 'Crypto payment', 'xorro-direct-wallet-payments-woocommerce' );
			}
		}
		if ( ! isset( $new['xdwp_payment'] ) ) {
			$new['xdwp_payment'] = __( 'Crypto payment', 'xorro-direct-wallet-payments-woocommerce' );
		}
		return $new;
	}

	/**
	 * Render the column for one order.
	 *
	 * @param string           $column Column key.
	 * @param int|WC_Order     $order  Order or order ID (HPOS passes the order).
	 */
	public static function render_column( $column, $order = null ) {
		if ( 'xdwp_payment' !== $column ) {
			return;
		}
		$order = ( $order instanceof WC_Order ) ? $order : wc_get_order( $order );
		if ( ! $order instanceof WC_Order || ! Xdwp_Order::is_ours( $order ) ) {
			echo '&ndash;';
			return;
		}
		$coin   = Xdwp_Coins::get( (string) Xdwp_Order::meta( $order, 'coin' ) );
		$status = (string) Xdwp_Order::meta( $order, 'status' );
		$amount = (string) Xdwp_Order::meta( $order, 'amount' );

		echo '<span class="xdwp-order-col">';
		if ( $coin && '' !== $amount ) {
			echo '<span class="xdwp-order-col__amount">' . esc_html( $amount . ' ' . $coin['symbol'] ) . '</span><br />';
		}
		echo self::status_pill( $order, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		echo '</span>';
	}

	/**
	 * Status pill markup for an order.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $status Plugin status.
	 * @return string
	 */
	public static function status_pill( $order, $status ) {
		$labels = array(
			'awaiting'  => __( 'Waiting', 'xorro-direct-wallet-payments-woocommerce' ),
			'underpaid' => __( 'Part paid', 'xorro-direct-wallet-payments-woocommerce' ),
			'paid'      => __( 'Paid', 'xorro-direct-wallet-payments-woocommerce' ),
			'expired'   => __( 'Expired', 'xorro-direct-wallet-payments-woocommerce' ),
			'cancelled' => __( 'Cancelled', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		$classes = array(
			'awaiting'  => 'xdwp-pill--wait',
			'underpaid' => 'xdwp-pill--warn',
			'paid'      => 'xdwp-pill--paid',
			'expired'   => 'xdwp-pill--dead',
			'cancelled' => 'xdwp-pill--dead',
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$class = isset( $classes[ $status ] ) ? $classes[ $status ] : 'xdwp-pill--dead';

		$out = '<span class="xdwp-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
		if ( self::needs_attention( $order ) ) {
			$out .= ' <span class="xdwp-pill xdwp-pill--attn">' . esc_html__( 'Check', 'xorro-direct-wallet-payments-woocommerce' ) . '</span>';
		}
		return $out;
	}

	/**
	 * Orders the store owner still has to look at: money arrived late, more than was due, or a
	 * transfer that could belong to more than one order.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	/**
	 * Orders the store owner still has to look at: money arrived late, more than was due, or a
	 * transfer that could belong to more than one order.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function needs_attention( $order ) {
		return Xdwp_Order::needs_attention( $order );
	}

	/**
	 * Orders for the overview, newest first.
	 *
	 * @param array $args filter (all|awaiting|underpaid|paid|expired|attention), coin, paged, search.
	 * @return array{orders:WC_Order[],total:int,pages:int}
	 */
	public static function query( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'filter' => 'all',
				'coin'   => '',
				'paged'  => 1,
			)
		);

		$meta_query = array(
			array(
				'key'     => '_xdwp_coin',
				'compare' => 'EXISTS',
			),
		);
		if ( '' !== $args['coin'] ) {
			$meta_query[] = array(
				'key'   => '_xdwp_coin',
				'value' => $args['coin'],
			);
		}
		if ( in_array( $args['filter'], array( 'awaiting', 'underpaid', 'paid', 'expired' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_xdwp_status',
				'value' => $args['filter'],
			);
		}

		// Orders needing attention carry a flag of their own, so one from months ago is still
		// found. (Before 1.13.0 this sifted the 200 most recent orders in PHP, which meant a
		// late payment on a busy shop could scroll out of sight and never be seen.)
		if ( 'attention' === $args['filter'] ) {
			$meta_query[] = array(
				'key'   => '_xdwp_attention',
				'value' => '1',
			);
		}
		$attention = false;
		$paged     = max( 1, (int) $args['paged'] );
		$query     = array(
			'limit'      => $attention ? 200 : self::PER_PAGE,
			'paged'      => $attention ? 1 : $paged,
			'paginate'   => ! $attention,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'status'     => 'any',
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		if ( $attention ) {
			$orders = wc_get_orders( $query );
			$orders = is_array( $orders ) ? array_values( array_filter( $orders, array( __CLASS__, 'needs_attention' ) ) ) : array();
			$total  = count( $orders );
			$offset = ( $paged - 1 ) * self::PER_PAGE;
			return array(
				'orders' => array_slice( $orders, $offset, self::PER_PAGE ),
				'total'  => $total,
				'pages'  => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
			);
		}

		$result = wc_get_orders( $query );
		return array(
			'orders' => isset( $result->orders ) ? $result->orders : array(),
			'total'  => isset( $result->total ) ? (int) $result->total : 0,
			'pages'  => isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
		);
	}

	/**
	 * Counts for the summary cards.
	 *
	 * @return array<string, int>
	 */
	public static function summary() {
		$counts = array();
		foreach ( array( 'awaiting', 'underpaid', 'paid', 'expired' ) as $status ) {
			$result            = self::query(
				array(
					'filter' => $status,
					'paged'  => 1,
				)
			);
			$counts[ $status ] = (int) $result['total'];
		}
		$attention              = self::query( array( 'filter' => 'attention' ) );
		$counts['attention']    = (int) $attention['total'];
		return $counts;
	}

	/**
	 * Coins that actually appear on orders, for the filter dropdown.
	 *
	 * @return array<string, string> Coin ID => label.
	 */
	public static function used_coins() {
		global $wpdb;
		$ids = array();

		// HPOS and the legacy post table store the same meta key in different tables; ask both
		// so the filter is right whichever one this store uses.
		$tables = array( $wpdb->postmeta => 'meta_key' );
		$hpos   = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
			$tables[ $hpos ] = 'meta_key';
		}
		foreach ( array_keys( $tables ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$table} WHERE meta_key = '_xdwp_coin' LIMIT 250" );
			if ( is_array( $rows ) ) {
				$ids = array_merge( $ids, $rows );
			}
		}

		$out = array();
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$coin = Xdwp_Coins::get( (string) $id );
			if ( $coin ) {
				$out[ (string) $id ] = $coin['symbol'] . ' — ' . $coin['name'];
			}
		}
		asort( $out );
		return $out;
	}
}
