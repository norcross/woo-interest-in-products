<?php
/**
 * The functionality tied to the actual export.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\DataExport;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;

add_action( 'admin_init', __NAMESPACE__ . '\export_subscription_data', 1 );

/**
 * Take the incoming relationship IDs and generate the export data.
 *
 * @param  array $relationship_ids  The IDs requested via bulk.
 *
 * @return mixed
 */
function export_subscription_data() {

	// Bail if we don't have our flag.
	if ( empty( $_GET['wc_product_interest_export'] ) ) {
		return;
	}

	// Fail on a missing or bad nonce.
	if ( empty( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'wc_product_interest_export' ) ) {
		Helpers\admin_page_redirect(
			array(
				'success' => 0,
				'errcode' => 'bad_nonce',
			)
		);
	}

	// Fetch the dataset requested.
	$stored = get_option( 'wc_product_interest_export_ids', false );

	// Fail on missing data.
	if ( ! $stored ) {
		Helpers\admin_page_redirect(
			array(
				'success' => 0,
				'errcode' => 'missing_stored_data',
			)
		);
	}

	// Add our headers and filename for direct download.
	header( 'Content-type: text/csv' );
	header( 'Content-Disposition: attachment; filename="' . get_export_filename() . '"' );

	// Make sure we don't cache anything.
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	// Begin the file pointer setup.
	$export = fopen( 'php://output', 'w' );

	// Output our headers.
	fputcsv( $export, get_export_headers() );

	// Loop the ID we stored and create the data array.
	foreach ( $stored as $stored_id ) {

		// Get the setup for each ID we wanna export.
		$single = Queries\get_data_by_relationship( $stored_id );

		// Bail if there's no data for this ID.
		if ( ! $single ) {
			continue;
		}

		// Set up our row data for the CSV.
		$setup = array(
			$single['customer']['display_name'],
			$single['customer']['user_email'],
			$single['product']['post_title'],
			$single['product']['product_sku'],
			$single['signup_date'],
		);

		// Allow each array to be filtered before etting added.
		$setup = apply_filters( Core\HOOK_PREFIX . 'export_data_row', $setup, $stored_id, $single );

		// If this got bypassed somehow, skip.
		if ( ! $setup || ! is_array( $setup ) ) {
			continue;
		}

		// Include it in the output.
		fputcsv( $export, $setup );
	}

	// And ummm, die.
	exit();
}

/**
 * Create and return a proper filename for export.
 *
 * @return string
 */
function get_export_filename() {

	// Get our sitename.
	$sitename = get_option( 'blogname' );

	// Append the timestamp and export name.
	$filename = strtolower( $sitename ) . '-wc-product-interest-export-' . time() . '.csv';

	// Return filtered and sanitized.
	return apply_filters( Core\HOOK_PREFIX . 'export_filename', sanitize_file_name( $filename ) );
}

/**
 * Set up and return the headers for the export file.
 *
 * @return array
 */
function get_export_headers() {

	// Build our array of header titles.
	$setup = array(
		__( 'Customer Name', 'woo-interest-in-products' ),
		__( 'Customer Email', 'woo-interest-in-products' ),
		__( 'Product Name', 'woo-interest-in-products' ),
		__( 'Product SKU', 'woo-interest-in-products' ),
		__( 'Signup Date', 'woo-interest-in-products' ),
	);

	// Return filtered.
	return apply_filters( Core\HOOK_PREFIX . 'export_headers', $setup );
}
