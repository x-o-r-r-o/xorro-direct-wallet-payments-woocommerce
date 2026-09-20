<?php
/**
 * Order lookups that mean the same thing on both of WooCommerce's order stores.
 *
 * Every question this plugin asks the database is really a question about order meta: which
 * order holds this txid, which addresses are already spoken for, which orders are still waiting
 * to be paid. The natural way to ask is `wc_get_orders( array( 'meta_query' => ... ) )`.
 *
 * That only works when WooCommerce keeps orders in its own tables (HPOS). On a site still
 * storing orders as posts, `WC_Data_Store_WP::get_wp_query_args()` skips the `meta_query` key
 * outright, so the filter never reaches the database and the query quietly answers a much
 * broader question than it was asked. Since WooCommerce 9.2 that mistake is at least audible —
 * `WC_Order_Data_Store_CPT::query()` raises a `doing_it_wrong` notice — but the behaviour is
 * years older than the warning.
 *
 * Losing the filter is not cosmetic. "Has another order already claimed this txid?" becomes
 * "does this shop have any other order?", which is true on every real shop, so no payment ever
 * confirms. "Which cancelled order can lend back its HD index?" stops checking that the order
 * has an index at all, and an empty meta reads back as index 0 — the first address, handed out
 * a second time.
 *
 * So the meta filter is put where the post store will actually read it: the WP_Query arguments,
 * through the one filter WooCommerce leaves open for exactly this. HPOS is left alone; it
 * understands meta_query natively, named clauses and all.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * wc_get_orders() with a meta_query that survives the legacy post store.
 */
class Xdwp_Order_Query {

	/**
	 * WooCommerce's last word on the WP_Query arguments the post store is about to run.
	 */
	const CPT_FILTER = 'woocommerce_order_data_store_cpt_get_orders_query';

	/**
	 * The private query argument that ties a filter call back to the query that added it.
	 */
	const MARKER = '_xdwp_meta_query';

	/**
	 * Whether orders live in posts, or null before the first look.
	 *
	 * @var bool|null
	 */
	private static $post_storage = null;

	/**
	 * Distinguishes one in-flight query from another.
	 *
	 * @var int
	 */
	private static $sequence = 0;

	/**
	 * Run an order query, keeping any meta_query meaningful on both stores.
	 *
	 * Takes and returns exactly what wc_get_orders() does, including the paginated object form.
	 *
	 * @param array $args wc_get_orders() arguments.
	 * @return array|object|stdClass Orders, ids, or the paginated result object.
	 */
	public static function get( array $args ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		if ( ! is_array( $meta_query ) || ! self::has_clause( $meta_query ) || ! self::uses_post_storage() ) {
			return wc_get_orders( $args );
		}

		// Handing meta_query to the post store would drop it and raise a notice, so it travels
		// as a marker instead and is put back as WP_Query arguments further down.
		unset( $args['meta_query'] );
		$token = 'xdwp-mq-' . ++self::$sequence;
		$args[ self::MARKER ] = $token;

		$callback = static function ( $wp_query_args, $query_vars = array() ) use ( $meta_query, $token ) {
			return self::inject( $wp_query_args, $query_vars, $meta_query, $token );
		};

		// Last, so a site's own adjustments to the query are already in place to merge with.
		add_filter( self::CPT_FILTER, $callback, PHP_INT_MAX, 3 );
		try {
			$result = wc_get_orders( $args );
		} finally {
			remove_filter( self::CPT_FILTER, $callback, PHP_INT_MAX );
		}

		return $result;
	}

	/**
	 * Add this query's meta filter to the WP_Query arguments the post store built.
	 *
	 * Other order queries can run while ours is open — a `pre_get_posts` listener, a nested
	 * lookup — so the marker decides whether these arguments are the ones we asked for.
	 *
	 * @param array  $wp_query_args WP_Query arguments.
	 * @param array  $query_vars    The wc_get_orders() arguments they came from.
	 * @param array  $meta_query    Meta filter to apply.
	 * @param string $token         Marker identifying our own query.
	 * @return array
	 */
	private static function inject( $wp_query_args, $query_vars, $meta_query, $token ) {
		if ( ! is_array( $wp_query_args ) ) {
			return $wp_query_args;
		}

		// The marker is ours and means nothing to WP_Query, so it does not travel any further.
		unset( $wp_query_args[ self::MARKER ] );

		$seen = is_array( $query_vars ) && isset( $query_vars[ self::MARKER ] ) ? $query_vars[ self::MARKER ] : '';
		if ( ! is_string( $seen ) || $seen !== $token ) {
			return $wp_query_args;
		}

		$existing = isset( $wp_query_args['meta_query'] ) ? $wp_query_args['meta_query'] : array();
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$wp_query_args['meta_query'] = self::merge( $existing, $meta_query );

		return $wp_query_args;
	}

	/**
	 * Combine WooCommerce's own meta filter with ours, both having to hold.
	 *
	 * Nesting rather than concatenating keeps whatever relation each side was written with, and
	 * WP_Meta_Query registers named clauses at any depth, so ordering by one still works.
	 *
	 * @param mixed $existing WooCommerce's meta_query, if it built one.
	 * @param array $addition Ours.
	 * @return array
	 */
	private static function merge( $existing, array $addition ) {
		if ( ! is_array( $existing ) || ! self::has_clause( $existing ) ) {
			return $addition;
		}
		return array(
			'relation' => 'AND',
			$existing,
			$addition,
		);
	}

	/**
	 * Whether a meta_query holds anything beyond a relation.
	 *
	 * @param array $meta_query Meta query.
	 * @return bool
	 */
	private static function has_clause( array $meta_query ) {
		foreach ( $meta_query as $key => $unused ) {
			if ( 'relation' !== $key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether this shop still keeps orders in the posts table.
	 *
	 * Read from the data store WooCommerce actually loaded rather than from the HPOS setting,
	 * because that is what wc_get_orders() will run — including while a shop is mid-migration
	 * with posts still authoritative.
	 *
	 * @return bool
	 */
	public static function uses_post_storage() {
		if ( null !== self::$post_storage ) {
			return self::$post_storage;
		}

		self::$post_storage = false;

		if ( ! class_exists( 'WC_Data_Store' ) || ! class_exists( 'WC_Order_Data_Store_CPT' ) ) {
			return self::$post_storage;
		}

		try {
			$store = WC_Data_Store::load( 'order' );
		} catch ( Exception $e ) {
			return self::$post_storage;
		}

		if ( ! is_object( $store ) || ! method_exists( $store, 'get_current_class_name' ) ) {
			return self::$post_storage;
		}

		$class = (string) $store->get_current_class_name();
		if ( '' === $class || ! class_exists( $class ) ) {
			return self::$post_storage;
		}

		self::$post_storage = is_a( $class, 'WC_Order_Data_Store_CPT', true );

		return self::$post_storage;
	}

	/**
	 * Look the storage mode up again. For tests, and for anything that switches it at runtime.
	 */
	public static function forget_storage_mode() {
		self::$post_storage = null;
	}
}
