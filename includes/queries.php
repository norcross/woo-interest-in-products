<?php
/**
 * Basic data queries used within the plugin.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Queries;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Database as Database;

// Bring in the other namespaced items.
use WP_Error;

/**
 * Get all the product IDs that have the signup enabled.
 *
 * @return array
 */
function get_all_products( $flush = false ) {

	// Call the global database.
	global $wpdb;

	// Set up our query.
	$setup = $wpdb->prepare(
		"
		SELECT   ID
		FROM     $wpdb->posts
		WHERE    post_type = '%s'
		AND      post_status = '%s'
	", esc_sql( 'product' ), esc_sql( 'publish' )
	);

	// Process the query.
	$query = $wpdb->get_col( $setup );

	// If the query didn't work, handle it.
	if ( ! $query ) {

		// Return the WP_Error item if we have it, otherwise a generic false.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
		} else {
			return false;
		}
	}

	// Make sure they're unique.
	$items = array_unique( $query );

	// Return the array of product IDs, filtering out the duplicates.
	return $items;
}

/**
 * Get all the product IDs that have the signup enabled.
 *
 * @param  boolean $flush  Whether to flush the cache first or not.
 *
 * @return array
 */
function get_enabled_products( $flush = false ) {

	// Set my transient key.
	$ky = 'woo_product_interest_ids';

	// If we don't want the cache'd version, delete the transient first.
	if ( ! empty( $flush ) || defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		delete_transient( $ky );
	}

	// Check the transient.
	if ( false === $items = get_transient( $ky ) ) {

		// Call the global database.
		global $wpdb;

		// Set up our query.
		$setup = $wpdb->prepare(
			"
			SELECT   post_id
			FROM     $wpdb->postmeta
			WHERE    meta_key = '%s'
		", esc_sql( Core\PROD_META_KEY )
		);

		// Process the query.
		$query = $wpdb->get_col( $setup );

		// If the query didn't work, handle it.
		if ( ! $query ) {

			// Return the WP_Error item if we have it, otherwise a generic false.
			if ( $wpdb->last_error ) {
				return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
			} else {
				return false;
			}
		}

		// Make sure they're unique.
		$items = array_unique( $query );

		// Set our transient with our data.
		set_transient( $ky, $items, HOUR_IN_SECONDS );
	}

	// Return the array of product IDs, filtering out the duplicates.
	return $items;
}

/**
 * Get an array of all the customer IDs subscribed to something.
 *
 * @param  string  $return  Return just the IDs or the whole data set.
 * @param  boolean $flush   Whether to flush the cache first or not.
 *
 * @return array
 */
function get_all_customers( $return = 'data', $flush = false ) {

	// Set my transient key.
	$ky = 'woo_product_interest_customers_all';

	// If we don't want the cache'd version, delete the transient first.
	if ( ! empty( $flush ) || defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		delete_transient( $ky );
	}

	// Check the transient.
	if ( false === $customer_data = get_transient( $ky ) ) {

		// Call the global database.
		global $wpdb;

		// Set our various table names.
		$table = $wpdb->prefix . Core\TABLE_NAME;

		// Set up our query.
		$setup = $wpdb->prepare(
			"
			SELECT   *
			FROM     $table
			ORDER BY '%s' ASC
		", esc_attr( 'signup_date' )
		);

		// Process the query.
		$query = $wpdb->get_results( $setup );

		// If we came back empty, check for an error return.
		if ( ! $query ) {

			// Return the WP_Error item if we have it, otherwise a generic false.
			if ( $wpdb->last_error ) {
				return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
			} else {
				return false;
			}
		}

		// Pull out the IDs and signup dates.
		$customer_ids = wp_list_pluck( $query, 'signup_date', 'customer_id' );

		// Set my empty array.
		$customer_data = array();

		// Now loop and set the data for each user.
		foreach ( $customer_ids as $customer_id => $signup_date ) {

			// Get our initial user object.
			$user = get_userdata( absint( $customer_id ) );

			// Set the user object as part of the array.
			$setup = (array) $user->data;

			// Include the user edit link and signup date.
			$setup['user_edit_link'] = get_edit_user_link( $customer_id );
			$setup['signup_date']    = esc_attr( $signup_date );

			// Now add the new data array into the larger one.
			$customer_data[ $customer_id ] = $setup;
		}

		// Set our transient with our data.
		set_transient( $ky, $customer_data, HOUR_IN_SECONDS );
	}

	// Return the array of user IDs, filtering out the duplicates.
	return 'ids' === sanitize_text_field( $return ) ? array_keys( $customer_data ) : $customer_data;
}

/**
 * Get the customers that have subscribed to a single product.
 *
 * @param  integer $product_id  The product ID to look up.
 * @param  string  $return      Whether to return the actual data or just the IDs.
 * @param  boolean $flush       Whether to flush the cache first or not.
 *
 * @return array
 */
function get_customers_for_product( $product_id = 0, $return = 'data', $flush = false ) {

	// Make sure we have a product ID.
	if ( empty( $product_id ) || 'product' !== get_post_type( $product_id ) ) {
		return new WP_Error( 'invalid_product_id', __( 'The required product ID is missing or invalid.', 'woo-interest-in-products' ) );
	}

	// Make sure we have an enabled product.
	if ( ! Helpers\maybe_product_enabled( $product_id ) ) {
		return new WP_Error( 'product_not_enabled', __( 'Subscriptions are not enabled for this product.', 'woo-interest-in-products' ) );
	}

	// Set my transient key.
	$ky = 'woo_product_interest_customers_' . absint( $product_id );

	// If we don't want the cache'd version, delete the transient first.
	if ( ! empty( $flush ) || defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		delete_transient( $ky );
	}

	// Check the transient.
	if ( false === $customer_data = get_transient( $ky ) ) {

		// Call the global database.
		global $wpdb;

		// Set our various table names.
		$table = $wpdb->prefix . Core\TABLE_NAME;

		// Set up our query.
		$setup = $wpdb->prepare(
			"
			SELECT   *
			FROM     $table
			WHERE    product_id = '%d'
			ORDER BY signup_date ASC
		", absint( $product_id )
		);

		// Process the query.
		$query = $wpdb->get_results( $setup );

		// If we came back empty, check for an error return.
		if ( ! $query ) {

			// Return the WP_Error item if we have it, otherwise a generic false.
			if ( $wpdb->last_error ) {
				return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
			} else {
				return false;
			}
		}

		// Pull out the IDs and signup dates.
		$customer_ids = wp_list_pluck( $query, 'signup_date', 'customer_id' );

		// Set my empty array.
		$customer_data = array();

		// Now loop and set the data for each user.
		foreach ( $customer_ids as $customer_id => $signup_date ) {

			// Get our initial user object.
			$user = get_userdata( absint( $customer_id ) );

			// Set the user object as part of the array.
			$setup = (array) $user->data;

			// Include the user edit link and signup date.
			$setup['user_edit_link'] = get_edit_user_link( $customer_id );
			$setup['signup_date']    = esc_attr( $signup_date );

			// Now add the new data array into the larger one.
			$customer_data[ $customer_id ] = $setup;
		}

		// Set our transient with our data.
		set_transient( $ky, $customer_data, HOUR_IN_SECONDS );
	}

	// Return the array of user IDs, filtering out the duplicates.
	return 'ids' === sanitize_text_field( $return ) ? array_keys( $customer_data ) : $customer_data;
}

/**
 * Get the products that have subscribed by a customer.
 *
 * @param  integer $customer_id  The user ID to look up.
 * @param  string  $column       Whether to return the actual data or just the IDs.
 * @param  boolean $flush        Whether to flush the cache first or not.
 *
 * @return array
 */
function get_products_for_customer( $customer_id = 0, $column = false, $flush = false ) {

	// Make sure we have a user ID.
	if ( empty( $customer_id ) ) {
		return new WP_Error( 'missing_customer_id', __( 'The required customer ID is missing.', 'woo-interest-in-products' ) );
	}

	// Set my transient key.
	$ky = 'woo_customer_interest_products_' . absint( $customer_id );

	// If we don't want the cache'd version, delete the transient first.
	if ( ! empty( $flush ) || defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		delete_transient( $ky );
	}

	// Check the transient.
	if ( false === $products = get_transient( $ky ) ) {

		// Call the global database.
		global $wpdb;

		// Set our various table names.
		$table = $wpdb->prefix . Core\TABLE_NAME;

		// Set up our query.
		$setup = $wpdb->prepare(
			"
			SELECT   *
			FROM     $table
			WHERE    customer_id = '%d'
			ORDER BY signup_date ASC
		", absint( $customer_id )
		);

		// Process the query.
		$query = $wpdb->get_results( $setup, ARRAY_A );

		// If we came back false, return the error.
		if ( ! $query ) {

			// Return the WP_Error item if we have it, otherwise a generic false.
			if ( $wpdb->last_error ) {
				return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
			} else {
				return false;
			}
		}

		// Make sure they're unique.
		$products = Helpers\sanitize_text_recursive( $query );

		// Set our transient with our data.
		set_transient( $ky, $products, HOUR_IN_SECONDS );
	}

	// Return the whole thing if requested.
	if ( ! $column ) {
		return $products;
	}

	// Return the plucked if we have that column.
	return in_array( $column, array( 'relationship_id', 'product_id', 'customer_id', 'signup_date' ) ) ? wp_list_pluck( $products, $column ) : false;
}

/**
 * Get the product and customer data from a relationship.
 *
 * @param  integer $relationship_id  The relationship ID tied to the subscription.
 *
 * @return array
 */
function get_data_by_relationship( $relationship_id = 0 ) {

	// Make sure we have a relationship ID.
	if ( empty( $relationship_id ) ) {
		return new WP_Error( 'missing_relationship_id', __( 'The required relationship ID is missing.', 'woo-interest-in-products' ) );
	}

	// Set my transient key.
	$ky = 'woo_customer_relationship_data_' . absint( $relationship_id );

	// If we don't want the cache'd version, delete the transient first.
	if ( ! empty( $flush ) || defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		delete_transient( $ky );
	}

	// Check the transient.
	if ( false === $relationship = get_transient( $ky ) ) {

		// Call the global database.
		global $wpdb;

		// Set our various table names.
		$table = $wpdb->prefix . Core\TABLE_NAME;

		// Set up our query.
		$setup = $wpdb->prepare(
			"
			SELECT   *
			FROM     $table
			WHERE    relationship_id = '%d'
			ORDER BY signup_date ASC
		", absint( $relationship_id )
		);

		// Process the query.
		$query = $wpdb->get_row( $setup, ARRAY_A );

		// If we came back false, return the error.
		if ( ! $query ) {

			// Return the WP_Error item if we have it, otherwise a generic false.
			if ( $wpdb->last_error ) {
				return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
			} else {
				return false;
			}
		}

		// Make sure they're unique and clean.
		$clean = Helpers\sanitize_text_recursive( $query );

		// Get our initial user object.
		$user = get_userdata( absint( $clean['customer_id'] ) );

		// Parse out the SKU.
		$sku = get_post_meta( absint( $clean['product_id'] ), '_sku', true );

		// Add the two IDs for easy array picking.
		$relationship['customer_id'] = absint( $clean['customer_id'] );
		$relationship['product_id']  = absint( $clean['product_id'] );

		// Get the customer data, the user object.
		$relationship['customer'] = (array) $user->data;

		// Get the product data, the WP_Post object.
		$relationship['product'] = (array) get_post( absint( $clean['product_id'] ) );
		$relationship['product'] = wp_parse_args( array( 'product_sku' => $sku ), $relationship['product'] );

		// And add the signup date.
		$relationship['signup_date'] = esc_attr( $clean['signup_date'] );

		// Set our transient with our data.
		set_transient( $ky, $relationship, HOUR_IN_SECONDS );
	}

	// Return the relationship data.
	return $relationship;
}

/**
 * Just get everything for all the things.
 *
 * @param  string $return  Whether to return the actual data or just the IDs.
 *
 * @return array
 */
function get_all_subscription_data( $return = 'data' ) {

	// Call the global database.
	global $wpdb;

	// Set our various table names.
	$table = $wpdb->prefix . Core\TABLE_NAME;

	// Set up our query.
	$setup = $wpdb->prepare(
		"
		SELECT   relationship_id
		FROM     $table
		ORDER BY '%s' ASC
	", esc_attr( 'signup_date' )
	);

	// Process the query.
	$query = $wpdb->get_col( $setup );

	// If we came back false, return the error.
	if ( ! $query ) {

		// Return the WP_Error item if we have it, otherwise a generic false.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_query_error', __( 'Could not execute query', 'woo-interest-in-products' ), $wpdb->last_error );
		} else {
			return false;
		}
	}

	// Make sure all the relationship IDs are valid.
	$relationship_ids = array_map( 'absint', $query );

	// If we want the counts, return that.
	if ( 'counts' === sanitize_text_field( $return ) ) {
		return count( $relationship_ids );
	}

	// If we don't want data, just return the IDs.
	if ( 'ids' === sanitize_text_field( $return ) ) {
		return $relationship_ids;
	}

	// Set our blank.
	$relationships = array();

	// Now loop and fetch all the data.
	foreach ( $relationship_ids as $id ) {
		$relationships[ $id ] = get_data_by_relationship( $id );
	}

	// Return the relationship data.
	return $relationships;
}

/**
 * Remove individual relationships by ID.
 *
 * @param  array   $relationships  The relationship IDs.
 * @param  integer $customer_id    The customer ID tied to the request.
 *
 * @return mixed
 */
function remove_single_relationships( $relationships = array(), $customer_id = 0 ) {

	// If we don't have relationships IDs, bail.
	if ( ! $relationships ) {
		return false;
	}

	// Now loop my relationships IDs and delete one by one.
	foreach ( (array) $relationships as $relationship_id ) {

		// Fire the action before an ID is deleted.
		do_action( Core\HOOK_PREFIX . 'delete_by_relationship_before', $relationship_id, $customer_id );

		// And delete the item.
		$delete = Database\delete_by_relationship( $relationship_id );

		// Fire the action after an ID is deleted.
		do_action( Core\HOOK_PREFIX . 'delete_by_relationship_after', $relationship_id, $customer_id );
	}

	// Now delete the transient tied to the customer and the all list.
	delete_transient( 'woo_customer_interest_products_' . absint( $customer_id ) );
	delete_transient( 'woo_product_interest_customers_all' );

	// A basic thing for now.
	return true;
}
