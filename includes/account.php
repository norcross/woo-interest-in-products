<?php
/**
 * Our functions related to the "My Account" page.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\AccountPage;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;
use LiquidWeb\WooInterestInProducts\Layout as Layout;

/**
 * Start our engines.
 */
add_action( 'init', __NAMESPACE__ . '\check_user_product_interest_changes' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\load_endpoint_assets' );
add_action( 'woocommerce_before_account_navigation', __NAMESPACE__ . '\add_endpoint_notices', 15 );
add_filter( 'the_title', __NAMESPACE__ . '\add_endpoint_title' );
add_filter( 'woocommerce_account_menu_items', __NAMESPACE__ . '\add_endpoint_menu_item' );
add_action( 'woocommerce_account_product-interest_endpoint', __NAMESPACE__ . '\add_endpoint_content' );

/**
 * Look for our users changing their opt-in statuses.
 *
 * @return void
 */
function check_user_product_interest_changes() {

	// The nonce check. ALWAYS A NONCE CHECK.
	if ( ! isset( $_POST['wc_customer_interests_change_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['wc_customer_interests_change_nonce'] ), 'wc_customer_interests_change_action' ) ) {
		return;
	}

	// Make sure we have the action we want.
	if ( empty( $_POST['action'] ) || 'wc_product_interest_change' !== sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) {
		return;
	}

	// No customer ID? nothing.
	if ( empty( $_POST['wc_product_interest_customer_id'] ) ) {
		redirect_account_page_action( 'missing-customer-id' );
	}

	// Determine my original IDs.
	$original_ids   = ! empty( $_POST['wc_product_interest_original_ids'] ) ? explode( ',', sanitize_text_field( $_POST['wc_product_interest_original_ids'] ) ) : Queries\get_products_for_customer( absint( $_POST['wc_product_interest_customer_id'] ) );

	// If we don't have ANY IDs, then we're unsubscribing to everything.
	if ( ! isset( $_POST['wc_product_interest_ids'] ) ) {

		// Remove everything.
		Helpers\remove_single_relationships( $original_ids );

		// And redirect.
		redirect_account_page_action( null, 1 );
	}

	// Set our new IDs as a separate variable for comparisons.
	$new_ids    = array_map( 'absint', $_POST['wc_product_interest_ids'] );

	// Loop our original IDs and remove the ones that aren't in our group.
	foreach ( $original_ids as $original_id ) {

		// If the original ID is in the new ID array, we're good.
		if ( in_array( absint( $original_id ), (array) $new_ids ) ) {
			continue;
		}

		// Didn't find it, so we remove it.
		Helpers\remove_single_relationships( $original_id );
	}

}

/**
 * Handle redirecting an error on the account page.
 *
 * @param  string  $error_code  The error code we hit.
 * @param  integer $success     Whether the redirect is a success or not.
 * @param  array   $args        Optional args that can override it.
 *
 * @return void
 */
function redirect_account_page_action( $error_code = '', $success = 0, $args = array() ) {

	// Set up our basic redirect args.
	$basic  = array( 'success' => absint( $success ), 'woo-interest-in-products-action' => 1 );
	$basic  = ! empty( $error_code ) ? wp_parse_args( $basic, array( 'errcode' => esc_attr( $error_code ) ) ) : $basic;

	// Merge the args we have.
	$setup  = ! empty( $args ) ? wp_parse_args( $args, $basic ) : $basic;

	// Redirect with our error code.
	Helpers\account_page_redirect( $setup );
}

/**
 * Load our front-end side JS and CSS.
 *
 * @return void
 */
function load_endpoint_assets() {

	// Bail if we aren't on the account page.
	if ( ! is_account_page() ) {
		return;
	}

	// Set my handle.
	$handle = 'woo-interest-in-products-front';

	// Set a file suffix structure based on whether or not we want a minified version.
	$file   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'woo-interest-in-products-front' : 'woo-interest-in-products-front.min';

	// Set a version for whether or not we're debugging.
	$vers   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : Core\VERS;

	// Load our CSS file.
	wp_enqueue_style( $handle, Core\ASSETS_URL . '/css/' . $file . '.css', false, $vers, 'all' );

	// And our JS.
	/*
	wp_enqueue_script( $handle, Core\ASSETS_URL . '/js/' . $file . '.js', array( 'jquery' ), $vers, true );
	wp_localize_script( $handle, 'frontWooUserGDPR',
		array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) )
	);
	*/
}

/**
 * Add the notices above the "my account" area.
 *
 * @return HTML
 */
function add_endpoint_notices() {

	// Bail if we aren't on the right general place.
	if ( ! Helpers\maybe_account_endpoint_page() ) {
		return;
	}

	// Bail without our result flag.
	if ( empty( $_GET['ARG-TO-CHECK'] ) ) {

		// Echo out the blank placeholder for Ajax calls.
		echo '<div class="wc-product-interest-account-notice"></div>';

		// And just be done.
		return;
	}

	// @@todo show some actual notices.
}

/**
 * Set a title for the individual endpoint we just made.
 *
 * @param  string $title  The existing page title.
 *
 * @return string
 */
function add_endpoint_title( $title ) {

	// Bail if we aren't on the page.
	if ( ! Helpers\maybe_account_endpoint_page( true ) ) {
		return $title;
	}

	// Set our new page title.
	$title  = apply_filters( Core\HOOK_PREFIX . 'endpoint_title', __( 'My Product Interest Signups', 'woo-interest-in-products' ) );

	// Remove the filter so we don't loop endlessly.
	remove_filter( 'the_title', __NAMESPACE__ . '\add_endpoint_title' );

	// Return the title.
	return $title;
}

/**
 * Merge in our new enpoint into the existing "My Account" menu.
 *
 * @param  array $items  The existing menu items.
 *
 * @return array
 */
function add_endpoint_menu_item( $items ) {

	// Set up our menu item title.
	$title  = apply_filters( Core\HOOK_PREFIX . 'endpoint_menu_item', __( 'Product Interest', 'woo-interest-in-products' ) );

	// Add it to the array.
	$items  = wp_parse_args( array( Core\FRONT_VAR => esc_attr( $title ) ), $items );

	// Return our tabs.
	return Helpers\adjust_account_tab_order( $items );
}

/**
 * Add the content for our endpoint to display.
 *
 * @return HTML
 */
function add_endpoint_content() {

	// Fetch the products the user has signed up for.
	$dataset    = Queries\get_products_for_customer( get_current_user_id() );

	// If we don't have products, just return the
	if ( ! $dataset ) {

		// Output the message.
		echo '<p>' . esc_html__( 'You have not signed up for any products.', 'woo-interest-in-products' ) . '</p>';

		// And be done.
		return;
	}

	// Output the signups.
	echo Layout\get_single_customer_signups( $dataset, get_current_user_id() );
}
