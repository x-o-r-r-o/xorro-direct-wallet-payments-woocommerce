<?php
/**
 * Order queries have to mean the same thing on both of WooCommerce's order stores.
 *
 * WooCommerce keeps orders either in its own tables (HPOS) or in posts. Only the first
 * understands `meta_query` in `wc_get_orders()`; the post store's
 * `WC_Data_Store_WP::get_wp_query_args()` skips the key outright, so the filter never reaches
 * the database and the query silently answers a much wider question. Since WooCommerce 9.2 it
 * also raises a `doing_it_wrong` notice on the way past.
 *
 * These tests stand up a small model of both stores — close enough to the real ones on the two
 * points that matter, dropping the key and offering the
 * `woocommerce_order_data_store_cpt_get_orders_query` filter — and check that the plugin's own
 * queries pick out the same orders either way.
 *
 * Run: php tests/order-query-tests.php
 *
 * @package Xdwp
 */

define( 'ABSPATH', __DIR__ );
define( 'XDWP_VERSION', 'test' );

$GLOBALS['xdwp_t'] = array(
	'store'         => 'hpos',
	'orders'        => array(),
	'filters'       => array(),
	'query_vars'    => null,
	'wp_query_args' => null,
	'notice'        => false,
	'reentrant'     => null,
);

// --- hooks ----------------------------------------------------------------

/**
 * Register a filter.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted argument count.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ][] = $callback;
	ksort( $GLOBALS['xdwp_t']['filters'][ $hook ] );
	return true;
}

/**
 * Remove a filter.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function remove_filter( $hook, $callback, $priority = 10 ) {
	if ( ! isset( $GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ] as $index => $registered ) {
		if ( $registered === $callback ) {
			unset( $GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ][ $index ] );
			if ( ! $GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ] ) {
				unset( $GLOBALS['xdwp_t']['filters'][ $hook ][ $priority ] );
			}
			if ( ! $GLOBALS['xdwp_t']['filters'][ $hook ] ) {
				unset( $GLOBALS['xdwp_t']['filters'][ $hook ] );
			}
			return true;
		}
	}
	return false;
}

/**
 * Run a filter.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	$extra = array_slice( func_get_args(), 2 );
	foreach ( isset( $GLOBALS['xdwp_t']['filters'][ $hook ] ) ? $GLOBALS['xdwp_t']['filters'][ $hook ] : array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $extra ) );
		}
	}
	return $value;
}

/**
 * How many filters are still registered on a hook.
 *
 * @param string $hook Hook name.
 * @return int
 */
function xdwp_t_filter_count( $hook ) {
	$count = 0;
	foreach ( isset( $GLOBALS['xdwp_t']['filters'][ $hook ] ) ? $GLOBALS['xdwp_t']['filters'][ $hook ] : array() as $callbacks ) {
		$count += count( $callbacks );
	}
	return $count;
}

// --- a model of the two order data stores ---------------------------------

/**
 * Stand-in for the post-table order store.
 */
class WC_Order_Data_Store_CPT {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Stand-in for the HPOS order store. Deliberately unrelated to the post store, as the real one is.
 */
class Xdwp_Test_Hpos_Store {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Stand-in for WooCommerce's data store loader.
 */
class WC_Data_Store { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Load a store.
	 *
	 * @param string $type Store type.
	 * @return WC_Data_Store
	 * @throws Exception When the type is unknown.
	 */
	public static function load( $type ) {
		if ( 'order' !== $type ) {
			throw new Exception( 'unknown store' );
		}
		return new self();
	}

	/**
	 * The class WooCommerce would actually run.
	 *
	 * @return string
	 */
	public function get_current_class_name() {
		return 'cpt' === $GLOBALS['xdwp_t']['store'] ? 'WC_Order_Data_Store_CPT' : 'Xdwp_Test_Hpos_Store';
	}
}

/**
 * Does one meta clause hold for an order?
 *
 * @param array $meta   Order meta.
 * @param array $clause Clause.
 * @return bool
 */
function xdwp_t_clause( array $meta, array $clause ) {
	$key     = (string) $clause['key'];
	$compare = isset( $clause['compare'] ) ? strtoupper( (string) $clause['compare'] ) : '=';
	$present = array_key_exists( $key, $meta ) && '' !== (string) $meta[ $key ];
	$actual  = $present ? (string) $meta[ $key ] : '';

	switch ( $compare ) {
		case 'EXISTS':
			return $present;
		case 'NOT EXISTS':
			return ! $present;
		case 'IN':
			return $present && in_array( $actual, array_map( 'strval', (array) $clause['value'] ), true );
		case '<':
			return $present && (float) $actual < (float) $clause['value'];
		default:
			return $present && $actual === (string) $clause['value'];
	}
}

/**
 * Does a whole meta_query hold, at any nesting depth?
 *
 * @param array $meta       Order meta.
 * @param array $meta_query Meta query.
 * @return bool
 */
function xdwp_t_meta_match( array $meta, array $meta_query ) {
	$relation = 'AND';
	$results  = array();

	foreach ( $meta_query as $key => $clause ) {
		if ( 'relation' === $key ) {
			$relation = strtoupper( (string) $clause );
			continue;
		}
		if ( ! is_array( $clause ) ) {
			continue;
		}
		$results[] = isset( $clause['key'] ) ? xdwp_t_clause( $meta, $clause ) : xdwp_t_meta_match( $meta, $clause );
	}

	if ( ! $results ) {
		return true;
	}
	return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
}

/**
 * A model of wc_get_orders() on whichever store the test has selected.
 *
 * @param array $args Query arguments.
 * @return array
 */
function wc_get_orders( $args = array() ) {
	$GLOBALS['xdwp_t']['query_vars']    = $args;
	$GLOBALS['xdwp_t']['wp_query_args'] = null;
	// WooCommerce 9.2 raises doing_it_wrong for exactly this, on the post store only.
	$GLOBALS['xdwp_t']['notice']        = 'cpt' === $GLOBALS['xdwp_t']['store'] && ! empty( $args['meta_query'] );

	// Both stores turn a `payment_method` argument into a meta condition of their own, which is
	// what gives the merge something to merge with.
	$own = array();
	if ( isset( $args['payment_method'] ) ) {
		$own[] = array(
			'key'   => '_payment_method',
			'value' => $args['payment_method'],
		);
	}

	if ( 'cpt' === $GLOBALS['xdwp_t']['store'] ) {
		$wp_query_args = array(
			'errors'     => array(),
			'meta_query' => $own,
		);
		foreach ( $args as $key => $value ) {
			// WC_Data_Store_WP::get_wp_query_args() skips this key and never looks back.
			if ( 'meta_query' === $key || 'payment_method' === $key ) {
				continue;
			}
			$wp_query_args[ $key ] = $value;
		}

		$wp_query_args = apply_filters( 'woocommerce_order_data_store_cpt_get_orders_query', $wp_query_args, $args, null );

		// A second, unrelated order query running while ours is open must come back untouched.
		if ( $GLOBALS['xdwp_t']['reentrant'] ) {
			$GLOBALS['xdwp_t']['reentrant'] = apply_filters(
				'woocommerce_order_data_store_cpt_get_orders_query',
				array( 'meta_query' => array() ),
				array( 'limit' => 1 ),
				null
			);
		}

		$GLOBALS['xdwp_t']['wp_query_args'] = $wp_query_args;
		$meta_query                         = isset( $wp_query_args['meta_query'] ) ? $wp_query_args['meta_query'] : array();
	} else {
		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		if ( $own ) {
			$meta_query = $meta_query ? array( 'relation' => 'AND', $own, $meta_query ) : $own;
		}
	}

	$exclude = array_map( 'intval', isset( $args['exclude'] ) ? (array) $args['exclude'] : array() );
	$found   = array();
	foreach ( $GLOBALS['xdwp_t']['orders'] as $id => $meta ) {
		if ( in_array( (int) $id, $exclude, true ) ) {
			continue;
		}
		if ( ! xdwp_t_meta_match( $meta, $meta_query ) ) {
			continue;
		}
		$found[] = (int) $id;
		if ( isset( $args['limit'] ) && $args['limit'] > 0 && count( $found ) >= (int) $args['limit'] ) {
			break;
		}
	}
	return $found;
}

require_once __DIR__ . '/../includes/class-xdwp-order-query.php';

// --- harness --------------------------------------------------------------

$xdwp_failures = 0;

/**
 * Assert.
 *
 * @param bool   $condition Condition.
 * @param string $label     Description.
 */
function xdwp_assert( $condition, $label ) {
	global $xdwp_failures;
	if ( $condition ) {
		echo "  ok   {$label}\n";
		return;
	}
	++$xdwp_failures;
	echo "  FAIL {$label}\n";
}

/**
 * Choose which store the model runs as, and forget the cached answer.
 *
 * @param string $store 'cpt' or 'hpos'.
 */
function xdwp_t_store( $store ) {
	$GLOBALS['xdwp_t']['store'] = $store;
	Xdwp_Order_Query::forget_storage_mode();
}

// Four orders on one shop. Only #12 carries the txid we will ask about.
$GLOBALS['xdwp_t']['orders'] = array(
	10 => array(
		'_payment_method' => 'xdwp',
		'_xdwp_coin'      => 'BTC',
		'_xdwp_status'    => 'awaiting',
		'_xdwp_address'   => 'bc1qalpha',
		'_xdwp_started'   => '1000',
	),
	11 => array(
		'_payment_method' => 'xdwp',
		'_xdwp_coin'      => 'ETH',
		'_xdwp_status'    => 'awaiting',
		'_xdwp_address'   => '0xbeta',
		'_xdwp_started'   => '900',
	),
	12 => array(
		'_payment_method' => 'xdwp',
		'_xdwp_coin'      => 'BTC',
		'_xdwp_status'    => 'paid',
		'_xdwp_address'   => 'bc1qalpha',
		'_xdwp_txid'      => 'abc123',
	),
	13 => array(
		'_payment_method' => 'stripe',
	),
);

$txid_query = array(
	'limit'      => 1,
	'return'     => 'ids',
	'exclude'    => array( 0 ),
	'meta_query' => array(
		array(
			'key'   => '_xdwp_txid',
			'value' => 'abc123',
		),
	),
);

$peer_query = array(
	'limit'          => 20,
	'payment_method' => 'xdwp',
	'meta_query'     => array(
		'relation' => 'AND',
		array(
			'key'     => '_xdwp_status',
			'value'   => array( 'awaiting', 'underpaid' ),
			'compare' => 'IN',
		),
		array(
			'key'   => '_xdwp_coin',
			'value' => 'BTC',
		),
	),
);

echo "Order queries on HPOS\n";
xdwp_t_store( 'hpos' );

xdwp_assert( false === Xdwp_Order_Query::uses_post_storage(), 'HPOS is not post storage' );
xdwp_assert( array( 12 ) === Xdwp_Order_Query::get( $txid_query ), 'txid lookup finds only the order holding it' );
xdwp_assert( array( 10 ) === Xdwp_Order_Query::get( $peer_query ), 'peer lookup finds only the matching coin and status' );
xdwp_assert(
	isset( $GLOBALS['xdwp_t']['query_vars']['meta_query'] ),
	'meta_query is handed to HPOS untouched'
);
xdwp_assert(
	! isset( $GLOBALS['xdwp_t']['query_vars'][ Xdwp_Order_Query::MARKER ] ),
	'no marker argument is added on HPOS'
);
xdwp_assert( 0 === xdwp_t_filter_count( Xdwp_Order_Query::CPT_FILTER ), 'no filter is registered on HPOS' );

echo "\nOrder queries on the post store\n";
xdwp_t_store( 'cpt' );

xdwp_assert( true === Xdwp_Order_Query::uses_post_storage(), 'the post store is recognised' );

// Without the fix this returns order 10 — the newest order that is not excluded — and every
// payment is rejected as a duplicate.
xdwp_assert( array( 12 ) === Xdwp_Order_Query::get( $txid_query ), 'txid lookup finds only the order holding it' );
xdwp_assert( false === $GLOBALS['xdwp_t']['notice'], 'no unsupported-argument notice is raised' );
xdwp_assert(
	! isset( $GLOBALS['xdwp_t']['query_vars']['meta_query'] ),
	'meta_query is kept out of the arguments the post store sees'
);
xdwp_assert(
	! isset( $GLOBALS['xdwp_t']['wp_query_args'][ Xdwp_Order_Query::MARKER ] ),
	'the marker does not leak into the WP_Query arguments'
);
xdwp_assert( 0 === xdwp_t_filter_count( Xdwp_Order_Query::CPT_FILTER ), 'the filter is removed afterwards' );

xdwp_assert( array( 10 ) === Xdwp_Order_Query::get( $peer_query ), 'peer lookup finds only the matching coin and status' );
xdwp_assert(
	xdwp_t_meta_match( $GLOBALS['xdwp_t']['orders'][13], $GLOBALS['xdwp_t']['wp_query_args']['meta_query'] ) === false,
	"WooCommerce's own payment_method condition still applies after the merge"
);

// Ordering the payment scan by when each order started only works if the named clause survives
// being nested inside WooCommerce's own conditions.
$named_query = array(
	'limit'          => 100,
	'payment_method' => 'xdwp',
	'meta_query'     => array(
		'relation'       => 'AND',
		'status_clause'  => array(
			'key'     => '_xdwp_status',
			'value'   => array( 'awaiting', 'underpaid' ),
			'compare' => 'IN',
		),
		'started_clause' => array(
			'key'     => '_xdwp_started',
			'compare' => 'EXISTS',
		),
	),
	'orderby'        => array( 'started_clause' => 'ASC' ),
);
$named_result = Xdwp_Order_Query::get( $named_query );
xdwp_assert( array( 10, 11 ) === $named_result, 'named-clause query finds both waiting orders' );

/**
 * Find a named clause anywhere in a meta_query.
 *
 * @param array  $meta_query Meta query.
 * @param string $name       Clause name.
 * @return bool
 */
function xdwp_t_has_named( array $meta_query, $name ) {
	foreach ( $meta_query as $key => $clause ) {
		if ( $key === $name ) {
			return true;
		}
		if ( is_array( $clause ) && ! isset( $clause['key'] ) && xdwp_t_has_named( $clause, $name ) ) {
			return true;
		}
	}
	return false;
}

xdwp_assert(
	xdwp_t_has_named( $GLOBALS['xdwp_t']['wp_query_args']['meta_query'], 'started_clause' ),
	'the clause the query orders by keeps its name through the merge'
);
xdwp_assert(
	isset( $GLOBALS['xdwp_t']['wp_query_args']['orderby']['started_clause'] ),
	'the orderby reaching WP_Query still points at that clause'
);

echo "\nQueries that are not ours\n";
$GLOBALS['xdwp_t']['reentrant'] = true;
Xdwp_Order_Query::get( $txid_query );
$foreign = $GLOBALS['xdwp_t']['reentrant'];
$GLOBALS['xdwp_t']['reentrant'] = null;
xdwp_assert(
	is_array( $foreign ) && array() === $foreign['meta_query'],
	'another order query running at the same time is left alone'
);

echo "\nQueries with nothing to filter\n";
$plain = Xdwp_Order_Query::get( array( 'limit' => 2 ) );
xdwp_assert( array( 10, 11 ) === $plain, 'a query with no meta_query still runs' );
xdwp_assert( 0 === xdwp_t_filter_count( Xdwp_Order_Query::CPT_FILTER ), 'and registers no filter' );

$empty = Xdwp_Order_Query::get(
	array(
		'limit'      => 1,
		'meta_query' => array( 'relation' => 'AND' ),
	)
);
xdwp_assert( array( 10 ) === $empty, 'a meta_query holding only a relation is treated as absent' );

echo "\n";
if ( $xdwp_failures ) {
	echo "{$xdwp_failures} failing\n";
	exit( 1 );
}
echo "All order-query tests passed.\n";
