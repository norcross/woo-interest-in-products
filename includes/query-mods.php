<?php
/**
 * Functions that alter or otherwise modify a query.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\QueryMods;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;

/**
 * Start our engines.
 */
add_action( 'init', __NAMESPACE__ . '\add_account_rewrite_endpoint' );
add_filter( 'removable_query_args', __NAMESPACE__ . '\add_removable_arg' );
add_filter( 'query_vars', __NAMESPACE__ . '\add_account_endpoint_vars', 0 );

/**
 * Register new endpoint to use inside My Account page.
 *
 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
 */
function add_account_rewrite_endpoint() {
	add_rewrite_endpoint( Core\FRONT_VAR, EP_ROOT | EP_PAGES );
}

/**
 * Add our custom strings to the vars.
 *
 * @param  array $args  The existing array of args.
 *
 * @return array $args  The modified array of args.
 */
function add_removable_arg( $args ) {

	// Include my new args and return.
	return wp_parse_args( array( 'prod-interest-enable-all', 'prod-interest-enable-result' ), $args );
}

/**
 * Add new query var for the GDPR endpoint.
 *
 * @param  array $vars  The existing query vars.
 *
 * @return array
 */
function add_account_endpoint_vars( $vars ) {

	// Add our new endpoint var if we don't already have it.
	if ( ! in_array( Core\FRONT_VAR, $vars ) ) {
		$vars[] = Core\FRONT_VAR;
	}

	// And return it.
	return $vars;
}
