<?php
/**
 * All functions related to the custom tables.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Database;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;

// Bring in the other namespaced items.
use WP_Error;

/**
 * Start our engines.
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\register_table', 11 );

/**
 * Registers the table with $wpdb so the metadata api can find it.
 *
 * @return void
 */
function register_table() {

	// Load the global DB.
	global $wpdb;

	// Set the messages.
	$wpdb->wc_product_interest = $wpdb->prefix . Core\TABLE_NAME;
}

/**
 * Confirm that the table itself actually exists.
 *
 * @return boolean
 */
function maybe_table_exists() {

	// Call the global class.
	global $wpdb;

	// Set my table name.
	$table = $wpdb->wc_product_interest;

	// Run the lookup.
	$lookup = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) );

	// We have the table, no need to install it.
	$wpdb->get_var( $lookup ) === $table ? true : false;
}

/**
 * Install the table if it doesn't already exist.
 *
 * @return void
 */
function maybe_install_table() {

	// Run the check.
	$check = maybe_table_exists();

	// If we don't have the table, run the install.
	if ( ! $check ) {
		install_table();
	}

	// @@todo some sort of return?
	return;
}

/**
 * Compare the stored version of the database schema.
 *
 * @return boolean
 */
function maybe_update_table() {

	// We're already updated and current, so nothing here.
	if ( (int) get_option( Core\SCHEMA_KEY ) === (int) Core\DB_VERS ) {
		return;
	}

	// Run the install setup.
	install_table();
}

/**
 * Create our custom table to store the subscription relationships.
 *
 * @return void
 */
function install_table() {

	// Pull in the upgrade function.
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Load the WPDB global.
	global $wpdb;

	// Pull our character set and collating.
	$char_coll = $wpdb->get_charset_collate();

	// Set our various table names.
	$table_name = $wpdb->prefix . Core\TABLE_NAME;

	// Write the SQL syntax.
	$setup = "
		CREATE TABLE {$table_name} (
			relationship_id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id bigint(20) NOT NULL,
			customer_id bigint(20) NOT NULL,
			signup_date datetime NOT NULL,
		PRIMARY KEY  (relationship_id),
		KEY product_id (product_id),
		KEY customer_id (customer_id)
	) $char_coll;";

	// Create the actual table.
	dbDelta( $setup );

	// And run the update.
	update_option( Core\SCHEMA_KEY, Core\DB_VERS );

	// And return true because it exists.
	return true;
}

/**
 * Create a new record of a subscription.
 *
 * @param  integer $customer_id  The customer ID doing the purchasing.
 * @param  array   $products     The array of product IDs being passed.
 *
 * @return integer
 */
function insert( $customer_id = 0, $products = array() ) {

	// Make sure we have a customer ID.
	if ( empty( $customer_id ) ) {
		return new WP_Error( 'missing_customer_id', __( 'The required customer ID is missing.', 'woo-interest-in-products' ) );
	}

	// Make sure we have products.
	if ( empty( $products ) || ! is_array( $products ) ) {
		return new WP_Error( 'missing_invalid_products', __( 'The required product IDs are missing or invalid.', 'woo-interest-in-products' ) );
	}

	// Run the action before doing anything.
	do_action( Core\HOOK_PREFIX . 'before_all_inserts', $customer_id, $products );

	// Call the global database.
	global $wpdb;

	// Set our created time.
	$signup = current_time( 'mysql' );

	// Loop my products and confirm each one.
	foreach ( $products as $product_id ) {

		// Make sure we have a product ID.
		if ( empty( $product_id ) || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'invalid_product_id', __( 'The required product ID is missing or invalid.', 'woo-interest-in-products' ) );
		}

		// Set my insert data.
		$insert = array(
			'product_id'  => $product_id,
			'customer_id' => $customer_id,
			'signup_date' => $signup,
		);

		// Filter our inserted data.
		$insert = apply_filters( Core\HOOK_PREFIX . 'insert_data', $insert, $customer_id, $product_id );

		// Run our action after it has been inserted.
		do_action( Core\HOOK_PREFIX . 'before_single_insert', $insert, $customer_id, $product_id );

		// Set our format clauses
		$format = apply_filters( Core\HOOK_PREFIX . 'insert_data_format', array( '%d', '%d', '%s' ) );

		// Run my insert function.
		$wpdb->insert( $wpdb->wc_product_interest, $insert, $format );

		// Check for the ID and throw an error if we don't have it.
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'db_insert_error', __( 'There was an error adding this to the database.', 'woo-interest-in-products' ) );
		}

		// Run our action after it has been inserted.
		do_action( Core\HOOK_PREFIX . 'after_single_insert', $wpdb->insert_id, $insert, $customer_id, $product_id );
	}

	// Run the action after doing everything.
	do_action( Core\HOOK_PREFIX . 'after_all_inserts', $customer_id, $products );

	// Return true.
	return true;
}

/**
 * Delete all existing subscriptions for a customer.
 *
 * @param  integer $customer_id  The customer ID tied to the subscription.
 *
 * @return void
 */
function delete_by_customer( $customer_id = 0 ) {

	// Make sure we have a customer ID.
	if ( empty( $customer_id ) ) {
		return new WP_Error( 'missing_customer_id', __( 'The required customer ID is missing.', 'woo-interest-in-products' ) );
	}

	// Call the global database.
	global $wpdb;

	// Run my delete function.
	$delete = $wpdb->delete( $wpdb->wc_product_interest, array( 'customer_id' => absint( $customer_id ) ) );

	// Delete the transient tied to the user.
	delete_transient( 'woo_customer_interest_products_' . absint( $customer_id ) );
}

/**
 * Delete all existing customers for a product.
 *
 * @param  integer $product_id  The product ID tied to the customers.
 *
 * @return void
 */
function delete_by_product( $product_id = 0 ) {

	// Make sure we have a product ID.
	if ( empty( $product_id ) || 'product' !== get_post_type( $product_id ) ) {
		return new WP_Error( 'invalid_product_id', __( 'The required product ID is missing or invalid.', 'woo-interest-in-products' ) );
	}

	// Make sure we have an enabled product.
	if ( ! Helpers\maybe_product_enabled( $product_id ) ) {
		return new WP_Error( 'product_not_enabled', __( 'Subscriptions are not enabled for this product.', 'woo-interest-in-products' ) );
	}

	// Call the global database.
	global $wpdb;

	// Run my delete function.
	$delete = $wpdb->delete( $wpdb->wc_product_interest, array( 'product_id' => absint( $product_id ) ) );

	// Delete the transient tied to the user.
	delete_transient( 'woo_product_interest_customers_' . absint( $product_id ) );
}

/**
 * Delete a row based on the relationship ID.
 *
 * @param  integer $product_id  The product ID tied to the customers.
 *
 * @return void
 */
function delete_by_relationship( $relationship_id = 0 ) {

	// Make sure we have a relationship ID.
	if ( empty( $relationship_id ) ) {
		return new WP_Error( 'missing_relationship_id', __( 'The required relationship ID is missing.', 'woo-interest-in-products' ) );
	}

	// Call the global database.
	global $wpdb;

	// Run my delete function.
	$delete = $wpdb->delete( $wpdb->wc_product_interest, array( 'relationship_id' => absint( $relationship_id ) ) );

	// Delete the transient tied to the relationship.
	delete_transient( 'woo_customer_relationship_data_' . absint( $relationship_id ) );
}
