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
		add_action( 'admin_post_xdwp_row_action', array( __CLASS__, 'handle_row_action' ) );

		// Legacy order storage and HPOS ask for the same thing in two different filters.
		add_filter( 'woocommerce_shop_order_search_fields', array( __CLASS__, 'search_fields' ) );
		add_filter( 'woocommerce_order_table_search_query_meta_keys', array( __CLASS__, 'search_fields' ) );
		// The count on the menu is recalculated when an order's payment state changes.
		// The flag itself is kept up to date by Xdwp_Order, which runs during cron too.
	}

	/**
	 * Let WooCommerce's own order search find an order by what the chain knows about it.
	 *
	 * When money arrives that did not match automatically, the merchant has a transaction id
	 * or an address and nothing else. Without this they cannot get from that to the order, and
	 * the usual answer in this corner of the ecosystem is to write a snippet by hand.
	 *
	 * @param array $keys Meta keys WooCommerce already searches.
	 * @return array
	 */
	public static function search_fields( $keys ) {
		$ours = array(
			'_xdwp_txid',
			'_xdwp_wallet_txid',
			'_xdwp_late_txid',
			'_xdwp_address',
			'_xdwp_memo',
		);
		return array_values( array_unique( array_merge( is_array( $keys ) ? $keys : array(), $ours ) ) );
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
	 * Act on one payment from the Payments screen.
	 *
	 * Two things a shop owner needs when an order is in limbo: look again now rather than
	 * waiting for the next scheduled check, and give a customer who is mid-payment more time.
	 */
	public static function handle_row_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'xorro-direct-wallet-payments-woocommerce' ), 403 );
		}

		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$do       = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		check_admin_referer( 'xdwp_row_' . $order_id . '_' . $do );

		$order  = wc_get_order( $order_id );
		$notice = 'unknown';

		if ( $order && Xdwp_Order::is_ours( $order ) ) {
			if ( 'recheck' === $do ) {
				// Clear this order's own throttles so the look happens now.
				delete_transient( 'xdwp_ajax_verify_' . $order_id );
				delete_transient( 'xdwp_detect_' . $order_id );
				delete_transient( 'xdwp_wide_scan_' . $order_id );
				delete_transient( 'xdwp_dropped_' . $order_id );

				if ( Xdwp_Verifier::verify_order( $order ) ) {
					Xdwp_Order::mark_paid( wc_get_order( $order_id ) );
					$notice = 'paid';
				} else {
					Xdwp_Order::log_event( $order, 'checked', __( 'The shop owner checked the chain by hand; nothing new was found', 'xorro-direct-wallet-payments-woocommerce' ) );
					$notice = 'nothing';
				}
			} elseif ( 'extend' === $do ) {
				$status = (string) Xdwp_Order::meta( $order, 'status' );
				if ( in_array( $status, array( 'awaiting', 'underpaid', 'expired' ), true ) ) {
					$until = max( time(), (int) Xdwp_Order::meta( $order, 'expires' ) ) + HOUR_IN_SECONDS;
					$order->update_meta_data( '_xdwp_expires', $until );
					if ( 'expired' === $status ) {
						// Back to waiting: the amount and address it already has still stand.
						$order->update_meta_data( '_xdwp_status', 'awaiting' );
					}
					$order->save();
					Xdwp_Order::log_event( $order, 'extended', __( 'The shop owner gave this payment another hour', 'xorro-direct-wallet-payments-woocommerce' ) );
					$notice = 'extended';
				} else {
					$notice = 'cannot';
				}
			} elseif ( 'handled' === $do ) {
				$notice = Xdwp_Order::dismiss_attention( $order ) ? 'handled' : 'cannot';
			}
		}

		Xdwp_Payments_Admin::forget_attention_count();

		wp_safe_redirect(
			add_query_arg(
				'xdwp_done',
				$notice,
				wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=xorro-direct-wallet-payments-woocommerce-payments' )
			)
		);
		exit;
	}

	/**
	 * The result of the last row action, for the screen to show.
	 *
	 * @return string
	 */
	public static function row_action_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$done = isset( $_GET['xdwp_done'] ) ? sanitize_key( wp_unslash( $_GET['xdwp_done'] ) ) : '';
		$map  = array(
			'paid'     => __( 'Checked — the payment was found and the order is now paid.', 'xorro-direct-wallet-payments-woocommerce' ),
			'nothing'  => __( 'Checked — nothing has arrived for that order yet.', 'xorro-direct-wallet-payments-woocommerce' ),
			'extended' => __( 'That payment has another hour.', 'xorro-direct-wallet-payments-woocommerce' ),
			'handled'  => __( 'Taken off the list. It comes back if anything else happens to that payment.', 'xorro-direct-wallet-payments-woocommerce' ),
			'cannot'   => __( 'That order is finished, so there is nothing to extend.', 'xorro-direct-wallet-payments-woocommerce' ),
			'unknown'  => __( 'That order could not be found.', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		return isset( $map[ $done ] ) ? $map[ $done ] : '';
	}

	/**
	 * A link that performs one row action.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $do    recheck | extend.
	 * @return string URL.
	 */
	public static function row_action_url( $order, $do ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'xdwp_row_action',
					'order'  => $order->get_id(),
					'do'     => $do,
				),
				admin_url( 'admin-post.php' )
			),
			'xdwp_row_' . $order->get_id() . '_' . $do
		);
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

		// Say what happened, not just that something did.
		$flags = array(
			'underpaid' => __( 'Part paid', 'xorro-direct-wallet-payments-woocommerce' ),
			'overpaid'  => __( 'Overpaid', 'xorro-direct-wallet-payments-woocommerce' ),
			'paid_late' => __( 'Paid late', 'xorro-direct-wallet-payments-woocommerce' ),
			'dropped'   => __( 'Payment vanished', 'xorro-direct-wallet-payments-woocommerce' ),
		);
		$flag  = (string) $order->get_meta( '_xdwp_flag' );
		if ( isset( $flags[ $flag ] ) ) {
			$out .= ' <span class="xdwp-pill xdwp-pill--attn">' . esc_html( $flags[ $flag ] ) . '</span>';
		} elseif ( self::needs_attention( $order ) ) {
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
			$orders = Xdwp_Order_Query::get( $query );
			$orders = is_array( $orders ) ? array_values( array_filter( $orders, array( __CLASS__, 'needs_attention' ) ) ) : array();
			$total  = count( $orders );
			$offset = ( $paged - 1 ) * self::PER_PAGE;
			return array(
				'orders' => array_slice( $orders, $offset, self::PER_PAGE ),
				'total'  => $total,
				'pages'  => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
			);
		}

		$result = Xdwp_Order_Query::get( $query );
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
	 * How many quoted orders a single report will look at.
	 *
	 * A report is a glance, not an accounting export — the CSV is there for that. The cap keeps
	 * the Payments screen quick on a shop with years of orders behind it.
	 */
	const REPORT_MAX = 5000;

	/**
	 * Periods offered on the Payments screen, in days.
	 *
	 * @return array<int, string>
	 */
	public static function report_periods() {
		return array(
			7  => __( 'Last 7 days', 'xorro-direct-wallet-payments-woocommerce' ),
			30 => __( 'Last 30 days', 'xorro-direct-wallet-payments-woocommerce' ),
			90 => __( 'Last 90 days', 'xorro-direct-wallet-payments-woocommerce' ),
		);
	}

	/**
	 * What the shop's crypto payments actually did over a period.
	 *
	 * Four questions a shop owner asks and no gateway screen answers: how much came in per coin,
	 * how long customers waited, which coins get quoted and then abandoned, and how often people
	 * send the wrong amount. A coin that is quoted fifty times and paid twice is costing the shop
	 * checkouts, and the only way to know is to count.
	 *
	 * Read straight from order meta rather than through wc_get_orders, because loading five
	 * thousand order objects to count them is how an admin screen becomes a two-second wait.
	 *
	 * @param int $days How far back to look.
	 * @return array Report data; see the keys assembled below.
	 */
	public static function report( $days = 30 ) {
		global $wpdb;

		$days  = max( 1, min( 365, (int) $days ) );
		$since = time() - ( $days * DAY_IN_SECONDS );
		$key   = 'xdwp_report_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = array();
		$keys = array( '_xdwp_coin', '_xdwp_status', '_xdwp_flag', '_xdwp_started', '_xdwp_confirmed_at', '_xdwp_amount', '_order_total' );
		$in   = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		$sources = array(
			array(
				'table' => $wpdb->postmeta,
				'id'    => 'post_id',
				'total' => "MAX( CASE WHEN m.meta_key = '_order_total' THEN m.meta_value END )",
				'join'  => '',
			),
		);
		$hpos = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
			// With HPOS the order total is a column on the orders table, not a meta row.
			$sources[] = array(
				'table' => $hpos,
				'id'    => 'order_id',
				'total' => 'MAX( o.total_amount )',
				'join'  => 'LEFT JOIN ' . $wpdb->prefix . 'wc_orders o ON o.id = m.order_id',
			);
		}

		foreach ( $sources as $source ) {
			// Table and column names cannot be placeholders, so they are interpolated from the two
			// fixed shapes above and never from anything a request can reach. Every value in the
			// query is a placeholder, and the result is cached in a transient below.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$sql = $wpdb->prepare(
				"SELECT m.{$source['id']} AS order_id,
					MAX( CASE WHEN m.meta_key = '_xdwp_coin' THEN m.meta_value END ) AS coin,
					MAX( CASE WHEN m.meta_key = '_xdwp_status' THEN m.meta_value END ) AS status,
					MAX( CASE WHEN m.meta_key = '_xdwp_flag' THEN m.meta_value END ) AS flag,
					MAX( CASE WHEN m.meta_key = '_xdwp_started' THEN m.meta_value END ) AS started,
					MAX( CASE WHEN m.meta_key = '_xdwp_confirmed_at' THEN m.meta_value END ) AS confirmed,
					MAX( CASE WHEN m.meta_key = '_xdwp_amount' THEN m.meta_value END ) AS amount,
					{$source['total']} AS total
				FROM {$source['table']} m
				{$source['join']}
				WHERE m.meta_key IN ( {$in} )
				GROUP BY m.{$source['id']}
				HAVING coin IS NOT NULL AND started >= %d
				ORDER BY started DESC
				LIMIT %d",
				array_merge( $keys, array( $since, self::REPORT_MAX ) )
			);
			$found = $wpdb->get_results( $sql, ARRAY_A );
			// phpcs:enable
			if ( is_array( $found ) ) {
				foreach ( $found as $row ) {
					// A store running HPOS in compatibility mode writes the same meta to both
					// tables. Order ids are shared, so keying by them counts each order once.
					$rows[ (int) $row['order_id'] ] = $row;
				}
			}
		}

		if ( count( $rows ) > self::REPORT_MAX ) {
			$rows = array_slice( $rows, 0, self::REPORT_MAX, true );
		}

		$report = array(
			'days'    => $days,
			'quoted'  => 0,
			'paid'    => 0,
			'expired' => 0,
			'short'   => 0,
			'value'   => '0',
			'settle'  => null,
			'capped'  => count( $rows ) >= self::REPORT_MAX,
			'coins'   => array(),
			'made_at' => time(),
		);
		$settle_all = array();

		foreach ( $rows as $row ) {
			$coin_id = (string) $row['coin'];
			$coin    = Xdwp_Coins::get( $coin_id );
			if ( ! isset( $report['coins'][ $coin_id ] ) ) {
				$report['coins'][ $coin_id ] = array(
					'label'   => $coin ? $coin['symbol'] . ' — ' . $coin['name'] : $coin_id,
					'symbol'  => $coin ? $coin['symbol'] : $coin_id,
					'quoted'  => 0,
					'paid'    => 0,
					'expired' => 0,
					'short'   => 0,
					'amount'  => '0',
					'value'   => '0',
					'settle'  => null,
					'times'   => array(),
				);
			}
			$entry = &$report['coins'][ $coin_id ];

			++$report['quoted'];
			++$entry['quoted'];

			$status = (string) $row['status'];
			$flag   = (string) $row['flag'];

			// A part payment counts wherever it ends up: an order that arrived short and was then
			// completed still cost the customer a second attempt, which is the thing being counted.
			if ( 'underpaid' === $status || 'underpaid' === $flag ) {
				++$report['short'];
				++$entry['short'];
			}

			if ( 'paid' === $status ) {
				++$report['paid'];
				++$entry['paid'];
				$entry['amount'] = bcadd( $entry['amount'], self::numeric( $row['amount'] ), 18 );
				$entry['value']  = bcadd( $entry['value'], self::numeric( $row['total'] ), 6 );
				$report['value'] = bcadd( $report['value'], self::numeric( $row['total'] ), 6 );

				$started   = (int) $row['started'];
				$confirmed = (int) $row['confirmed'];
				if ( $started > 0 && $confirmed > $started ) {
					$entry['times'][] = $confirmed - $started;
					$settle_all[]     = $confirmed - $started;
				}
			} elseif ( 'expired' === $status || 'cancelled' === $status ) {
				++$report['expired'];
				++$entry['expired'];
			}
			unset( $entry );
		}

		foreach ( $report['coins'] as $coin_id => $entry ) {
			$report['coins'][ $coin_id ]['settle'] = self::median( $entry['times'] );
			unset( $report['coins'][ $coin_id ]['times'] );
		}
		$report['settle'] = self::median( $settle_all );

		// Busiest coin first: the one taking the most money is the one worth looking at.
		uasort(
			$report['coins'],
			static function ( $a, $b ) {
				if ( $a['paid'] === $b['paid'] ) {
					return $b['quoted'] - $a['quoted'];
				}
				return $b['paid'] - $a['paid'];
			}
		);

		set_transient( $key, $report, HOUR_IN_SECONDS );
		return $report;
	}

	/**
	 * A number from a meta value, whatever a theme or an import left in there.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Decimal string safe for bcmath.
	 */
	private static function numeric( $value ) {
		$value = preg_replace( '/[^0-9.\-]/', '', (string) $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '0';
		}
		return $value;
	}

	/**
	 * The middle value — not the mean, which one abandoned order left open all weekend ruins.
	 *
	 * @param array<int, int> $values Seconds.
	 * @return int|null
	 */
	private static function median( $values ) {
		if ( empty( $values ) ) {
			return null;
		}
		sort( $values );
		$count  = count( $values );
		$middle = (int) floor( ( $count - 1 ) / 2 );
		if ( 0 === $count % 2 ) {
			return (int) round( ( $values[ $middle ] + $values[ $middle + 1 ] ) / 2 );
		}
		return (int) $values[ $middle ];
	}

	/**
	 * A duration a person reads at a glance: "4 min", "1 h 12 min".
	 *
	 * @param int|null $seconds Duration.
	 * @return string
	 */
	public static function duration( $seconds ) {
		if ( null === $seconds ) {
			return '—';
		}
		$seconds = max( 0, (int) $seconds );
		if ( $seconds < 90 ) {
			/* translators: %d: seconds */
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'xorro-direct-wallet-payments-woocommerce' ), $seconds );
		}
		$minutes = (int) round( $seconds / 60 );
		if ( $minutes < 90 ) {
			/* translators: %d: minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'xorro-direct-wallet-payments-woocommerce' ), $minutes );
		}
		$hours = floor( $minutes / 60 );
		$rest  = $minutes % 60;
		/* translators: 1: hours, 2: minutes */
		return sprintf( __( '%1$dh %2$dm', 'xorro-direct-wallet-payments-woocommerce' ), $hours, $rest );
	}

	/**
	 * A percentage of a total, or an em dash when there is nothing to divide by.
	 *
	 * @param int $part  Part.
	 * @param int $total Total.
	 * @return string
	 */
	public static function rate( $part, $total ) {
		if ( $total <= 0 ) {
			return '—';
		}
		/* translators: %s: a percentage */
		return sprintf( __( '%s%%', 'xorro-direct-wallet-payments-woocommerce' ), number_format_i18n( ( $part / $total ) * 100, 1 ) );
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
