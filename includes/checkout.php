<?php
/**
 * Our functions related to the checkout.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Checkout;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Layout as Layout;
use LiquidWeb\WooInterestInProducts\Queries as Queries;
use LiquidWeb\WooInterestInProducts\Database as Database;

/**
 * Start our engines.
 */
add_action( 'woocommerce_review_order_before_submit', __NAMESPACE__ . '\display_product_interest_fields' );
add_filter( 'woocommerce_checkout_posted_data', __NAMESPACE__ . '\merge_product_interest_data' );
add_action( 'woocommerce_after_checkout_validation', __NAMESPACE__ . '\validate_product_interest_data', 10, 2 );
add_action( 'woocommerce_checkout_update_user_meta', __NAMESPACE__ . '\update_customer_product_interest', 10, 2 );

/**
 * Add our new opt-in boxes to the checkout.
 *
 * @return HTML
 */
function display_product_interest_fields() {

	// Don't run any of this on a guest user.
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Bail without some cart action.
	if ( ! WC()->cart->get_cart_contents() ) {
		return;
	}

	// Get my array of enabled products.
	$enable = Queries\get_enabled_products();

	// No enabled products exist, so bail.
	if ( empty( $enable ) ) {
		return;
	}

	// Filter my cart products.
	$filter = Helpers\filter_product_cart( WC()->cart->get_cart_contents(), $enable );

	// Bail without having any items come back.
	if ( ! $filter ) {
		return;
	}

	// And spit out the fields.
	Layout\get_product_interest_checkout_field( $filter, true );
}

/**
 * Merge in our posted field data.
 *
 * @param  array $data  The post data that comes by default.
 *
 * @return array  $data  The possibly modified posted data.
 */
function merge_product_interest_data( $data ) {

	// Don't run any of this on a guest user.
	if ( ! is_user_logged_in() ) {
		return $data;
	}

	// Bail if we have no posted data.
	if ( empty( $_POST['woo-product-interest'] ) ) { // WPCS: CSRF ok.
		return $data;
	}

	// Check to see if our nonce was provided.
	if ( empty( $_POST['product_interest_nonce_name'] ) || ! wp_verify_nonce( $_POST['product_interest_nonce_name'], 'product_interest_nonce_action' ) ) {
		return $data;
	}

	// Explode the string of IDs into an array..
	$array = explode( ',', sanitize_text_field( $_POST['woo-product-interest'] ) );

	// Clean each entry.
	$items = array_map( 'absint', $array );

	// Merge our opt-in items to the overall data array and return it.
	return array_merge( $data, array( 'product-interest' => $items ) );
}

/**
 * Validate the opt-in fields.
 *
 * @param  array  $data    The post data that comes by default.
 * @param  object $errors  The existing error object.
 *
 * @return mixed
 */
function validate_product_interest_data( $data, $errors ) {

	// Don't run any of this on a guest user.
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Bail if we have no signup data.
	if ( empty( $data['product-interest'] ) ) {
		return;
	}

	// Now loop my products and make sure they are actually products.
	foreach ( $data['product-interest'] as $product_id ) {

		// First make sure it is a product.
		if ( 'product' !== get_post_type( $product_id ) ) {

			// Set our error message.
			$error_text = sprintf( __( 'ID %d is not a valid product', 'woo-interest-in-products' ), absint( $product_id ) );

			// And add my error.
			$errors->add( 'invalid_product_id', $error_text );
		}

		// Now check to make sure it's enabled..
		$enable = Helpers\maybe_product_enabled( $product_id );

		// Now make sure it is an enabled product.
		if ( ! $enable ) {

			// Set our error message.
			$error_text = sprintf( __( 'Signups have not been enabled for product ID %d', 'woo-interest-in-products' ), absint( $product_id ) );

			// And add my error.
			$errors->add( 'product_not_enabled', $error_text );
		}
	}

	// And just be done.
	return;
}

/**
 * Update the product interest field choices for the customer.
 *
 * @param  integer $customer_id  The WooCommerce customer ID.
 * @param  array   $data         The post data from the order.
 *
 * @return void
 */
function update_customer_product_interest( $customer_id, $data ) {

	// Don't run any of this on a guest user.
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Bail without data or customer info.
	if ( empty( $customer_id ) || empty( $data ) || ! is_array( $data ) ) {
		return;
	}

	// Include an action for before the signup.
	do_action( Core\HOOK_PREFIX . 'before_signup', $customer_id, $data );

	// If we don't have any products, just bail.
	if ( empty( $data['product-interest'] ) ) {
		return;
	}

	// Filter out anything we're already signed up for.
	$setup = sanitize_filter_duplicate_signups( (array) $data['product-interest'], $customer_id );

	// If we have no items left, just bail.
	if ( ! $setup ) {
		return;
	}

	// Run the inserts.
	$update = Database\insert( $customer_id, (array) $setup );

	// Handle our potential WP Error return.
	if ( is_wp_error( $update ) ) {

	}

	// Include an action for after the interest is processed.
	do_action( Core\HOOK_PREFIX . 'after_signup', $setup, $customer_id, $data );
	// @@todo  handle error / empty return?
}

/**
 * Filter out the duplicate items where a customer has signed up.
 *
 * @param  array   $product_ids  The existing array of product IDs.
 * @param  integer $customer_id  The WooCommerce customer ID.
 *
 * @return array   $product_ids  The potentially modified array of items.
 */
function sanitize_filter_duplicate_signups( $product_ids = array(), $customer_id = 0 ) {

	// Bail if we don't have our pieces.
	if ( empty( $product_ids ) || empty( $customer_id ) ) {
		return $product_ids;
	}

	// Get all the existing relationship data.
	$existing = Queries\get_products_for_customer( $customer_id, 'ids' );

	// Bail with no data.
	if ( ! $existing ) {
		return $product_ids;
	}

	// Now loop each product ID.
	foreach ( $product_ids as $index => $product_id ) {

		// If our ID isn't in the array of existing data, go to the next.
		if ( ! in_array( $product_id, $existing ) ) {
			continue;
		}

		// Unset the index.
		unset( $product_ids[ $index ] );
	}

	// Filter any duplicates that exist for some reason.
	$product_ids = array_unique( $product_ids );

	// Return false if we unset everything or the IDs with the index reset.
	return ! empty( $product_ids ) ? array_values( $product_ids ) : false;
}
