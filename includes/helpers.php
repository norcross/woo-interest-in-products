<?php
/**
 * Our helper functions to use across the plugin.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Helpers;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Database as Database;

/**
 * Check an code and (usually an error) return the appropriate text.
 *
 * @param  string $code  The code provided.
 *
 * @return string
 */
function notice_text( $code = '' ) {

	// Handle my different error codes.
	switch ( esc_attr( strtolower( $code ) ) ) {

		case 'success-change-interests':
			$msgtxt = __( 'Your interest selections have been updated.', 'woo-interest-in-products' );
			break;

		case 'success-general':
		case 'success':
			$msgtxt = __( 'Success! Your request has been completed.', 'woo-interest-in-products' );
			break;

		case 'update-error':
			$msgtxt = __( 'Your settings could not be updated.', 'woo-interest-in-products' );
			break;

		case 'missing-nonce':
			$msgtxt = __( 'The required nonce was missing.', 'woo-interest-in-products' );
			break;

		case 'bad-nonce':
			$msgtxt = __( 'The required nonce was invalid.', 'woo-interest-in-products' );
			break;

		case 'invalid-nonce':
			$msgtxt = __( 'The required nonce was missing or invalid.', 'woo-interest-in-products' );
			break;

		case 'missing-customer-id':
			$msgtxt = __( 'The required customer ID was not provided.', 'woo-interest-in-products' );
			break;

		case 'no-customer':
			$msgtxt = __( 'The current customer could not be determined.', 'woo-interest-in-products' );
			break;

		case 'no-original-ids':
			$msgtxt = __( 'No existing product signups were found.', 'woo-interest-in-products' );
			break;

		case 'customer-update-failed':
			$msgtxt = __( 'Your interest selections could not be updated.', 'woo-interest-in-products' );
			break;

		case 'missing-required-field':
			$msgtxt = __( 'Please review all the required fields.', 'woo-interest-in-products' );
			break;

		case 'unknown':
		case 'unknown-error':
			$msgtxt = __( 'There was an unknown error with your request.', 'woo-interest-in-products' );
			break;

		default:
			$msgtxt = __( 'There was an error with your request.', 'woo-interest-in-products' );

			// End all case breaks.
	}

	// Return it with a filter.
	return apply_filters( Core\HOOK_PREFIX . 'notice_text', $msgtxt, $code );
}

/**
 * Check a product ID to see if it enabled.
 *
 * @param  integer $product_id  The ID of the product.
 * @param  boolean $strings     Optional return of yes/no strings.
 *
 * @return mixed
 */
function maybe_product_enabled( $product_id = 0, $strings = false ) {

	// Check the meta.
	$meta = get_post_meta( $product_id, Core\PROD_META_KEY, true );

	// Return the string variant if requested.
	if ( $strings ) {
		return ! empty( $meta ) ? 'yes' : 'no';
	}

	// Return the boolean result.
	return ! empty( $meta ) ? true : false;
}

/**
 * Check if we are on the account privacy data page.
 *
 * @param  boolean $in_query  Whether to check inside the actual query.
 *
 * @return boolean
 */
function maybe_account_endpoint_page( $in_query = false ) {

	// Bail if we aren't on the right general place.
	if ( is_admin() || ! is_account_page() ) {
		return false;
	}

	// Bail if we aren't on the right general place.
	if ( $in_query && ! in_the_loop() || $in_query && ! is_main_query() ) {
		return false;
	}

	// Call the global query object.
	global $wp_query;

	// Return if we are on our specific var or not.
	return isset( $wp_query->query_vars[ Core\FRONT_VAR ] ) ? true : false;
}

/**
 * Get our "My Account" page to use in the plugin.
 *
 * @param  array $args  Any query args to add to the base URL.
 *
 * @return string
 */
function get_account_tab_link( $args = array() ) {

	// Set my base link.
	$page = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

	// Add our link.
	$link = rtrim( $page, '/' ) . '/' . Core\FRONT_VAR;

	// Return the link with or without args.
	return ! empty( $args ) ? add_query_arg( $args, $link ) : $link;
}

/**
 * Handle our redirect within the admin settings page.
 *
 * @param  array $args  The query args to include in the redirect.
 *
 * @return void
 */
function account_page_redirect( $args = array() ) {

	// Don't redirect if we didn't pass any args.
	if ( is_admin() || empty( $args ) ) {
		return;
	}

	// Set the link.
	$setup = get_account_tab_link( $args );

	// Do the redirect.
	wp_redirect( $setup );
	exit;
}

/**
 * Check the products provided for enabled items.
 *
 * @param  array $cart    The total array of cart data.
 * @param  array $enable  The enabled products.
 *
 * @return array
 */
function filter_product_cart( $cart = array(), $enable = array() ) {

	// Make sure we have everything required.
	if ( empty( $cart ) || empty( $enable ) ) {
		return false;
	}

	// Set an empty variable.
	$data = array();

	// Loop our cart and look for products.
	foreach ( $cart as $key => $item ) {

		// Set my ID.
		$id = absint( $item['product_id'] );

		// If we have meta, add to the data array.
		if ( in_array( $id, $enable ) ) {
			$data[] = $id;
		}
	}

	// Return the array (or empty).
	return ! empty( $data ) ? $data : false;
}

/**
 * Adjust the "My Account" menu to make sure login is at the bottom.
 *
 * @param  array $items  Our current array of items.
 *
 * @return array $items  The modified array.
 */
function adjust_account_tab_order( $items = array() ) {

	// If we don't have the logout link, just return what we have.
	if ( ! isset( $items['customer-logout'] ) ) {
		return $items;
	}

	// Set our logout link.
	$logout = $items['customer-logout'];

	// Remove the logout.
	unset( $items['customer-logout'] );

	// Now add it back in.
	$items['customer-logout'] = $logout;

	// And return the set.
	return $items;
}

/**
 * Return our base link, with function fallbacks.
 *
 * @return string
 */
function get_admin_menu_link() {

	// Bail if we aren't on the admin side.
	if ( ! is_admin() ) {
		return false;
	}

	// Set my slug.
	$slug = trim( Core\MENU_SLUG );

	// Build out the link if we don't have our function.
	if ( ! function_exists( 'menu_page_url' ) ) {

		// Set my args.
		$args = array(
			'post_type' => 'product',
			'page'      => $slug,
		);

		// Return the link with our args.
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	// Return using the function.
	return menu_page_url( $slug, false );
}

/**
 * Handle our redirect within the admin settings page.
 *
 * @param  array $args  The query args to include in the redirect.
 *
 * @return void
 */
function admin_page_redirect( $args = array(), $response = true ) {

	// Don't redirect if we didn't pass any args.
	if ( empty( $args ) ) {
		return;
	}

	// Handle the setup.
	$redirect_args = wp_parse_args(
		$args, array(
			'post_type' => 'product',
			'page'      => trim( Core\MENU_SLUG ),
		)
	);

	// Add the default args we need in the return.
	if ( $response ) {
		$redirect_args = wp_parse_args( array( 'wc-product-interest-response' => 1 ), $redirect_args );
	}

	// Now set my redirect link.
	$redirect_link = add_query_arg( $redirect_args, admin_url( 'edit.php' ) );

	// Do the redirect.
	wp_safe_redirect( $redirect_link );
	exit;
}

/**
 * Check if we are on the admin settings tab.
 *
 * @param  string $hook  Optional hook sent from some actions.
 *
 * @return boolean
 */
function maybe_admin_settings_page( $hook = '' ) {

	// Can't be the admin page if we aren't admin, or don't have a hook.
	if ( ! is_admin() || empty( $hook ) ) {
		return false;
	}

	// Check the hook if we passed one.
	return 'product_page_product-interest-list' === sanitize_text_field( $hook ) ? true : false;
}

/**
 * Check our various constants on an Ajax call.
 *
 * @param  boolean $check_admin  Check if the call is being made on admin side.
 *
 * @return boolean
 */
function check_ajax_constants( $check_admin = false ) {

	// Run the admin check if requested.
	if ( $check_admin && ! is_admin() ) {
		return false;
	}

	// Check for a REST API request.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	// Check for running an autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	// Check for running a cron, unless we've skipped that.
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	// We hit none of the checks, so proceed.
	return true;
}

/**
 * Do the whole 'check current screen' progressions.
 *
 * @param  string $check    The type of comparison we want to do.
 * @param  string $compare  What we want to compare against on the screen.
 * @param  string $action   If we want to return the value or compare it against something.
 *
 * @return boolean          Whether or not we are.
 */
function check_admin_screen( $check = 'post_type', $compare = '', $action = 'compare' ) {

	// Bail if not on admin or our function doesnt exist.
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	// Get my current screen.
	$screen = get_current_screen();

	// Bail without.
	if ( empty( $screen ) || ! is_object( $screen ) ) {
		return false;
	}

	// If the check is false, return the entire screen object.
	if ( empty( $check ) || ! empty( $action ) && 'object' === sanitize_key( $action ) ) {
		return $screen;
	}

	// Do the post type check.
	if ( 'post_type' === $check ) {

		// If we have no post type, it's false right off the bat.
		if ( empty( $screen->post_type ) ) {
			return false;
		}

		// Handle my different action types.
		switch ( $action ) {

			case 'compare':
				return ! empty( $compare ) && sanitize_key( $compare ) === $screen->post_type ? true : false;
				break;

			case 'return':
				return $screen->post_type;
				break;
		}
	}

	// Do the base check.
	if ( 'base' === $check ) {

		// If we have no base, it's false right off the bat.
		if ( empty( $screen->base ) ) {
			return false;
		}

		// Handle my different action types.
		switch ( $action ) {

			case 'compare':
				return ! empty( $compare ) && sanitize_key( $compare ) === $screen->base ? true : false;
				break;

			case 'return':
				return $screen->base;
				break;
		}
	}

	// Do the ID check.
	if ( in_array( $check, array( 'id', 'ID' ) ) ) {

		// If we have no ID, it's false right off the bat.
		if ( empty( $screen->id ) ) {
			return false;
		}

		// Handle my different action types.
		switch ( $action ) {

			case 'compare':
				return ! empty( $compare ) && sanitize_key( $compare ) === $screen->id ? true : false;
				break;

			case 'return':
				return $screen->id;
				break;
		}
	}

	// Nothing left. bail.
	return false;
}

/**
 * Set up a recursive callback for multi-dimensional text arrays.
 *
 * @param  array   $input   The data array.
 * @param  boolean $filter  Whether to filter the empty values out.
 *
 * @return array
 */
function sanitize_text_recursive( $input, $filter = false ) {

	// Set our base output.
	$output = array();

	// Loop the initial data input set.
	// If our data is an array, kick it again.
	foreach ( $input as $key => $data ) {

		// Handle the setup.
		$setup = is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : sanitize_text_field( $data );

		// Skip if are empty and said no filter.
		if ( empty( $setup ) && ! empty( $filter ) ) {
			continue;
		}

		// Add the setup to the data array.
		$output[ $key ] = $setup;
	}

	// Return the entire set.
	return $output;
}

/**
 * Run our individual strings through some clean up.
 *
 * @param  string $string  The data we wanna clean up.
 *
 * @return string
 */
function clean_export( $string ) {

	// Original PHP code by Chirp Internet: www.chirp.com.au
	// Please acknowledge use of this code by including this header.
	// Handle my different string checks.
	switch ( $string ) {

		case 't':
			$string = 'TRUE';
			break;

		case 'f':
			$string = 'FALSE';
			break;

		case preg_match( '/^0/', $string ):
		case preg_match( '/^\+?\d{8,}$/', $string ):
		case preg_match( '/^\d{4}.\d{1,2}.\d{1,2}/', $string ):
			$string = "'$string";
			break;

		case strstr( $string, '"' ):
			$string = '"' . str_replace( '"', '""', $string ) . '"';
			break;

		default:
			$string = mb_convert_encoding( $string, 'UTF-16LE', 'UTF-8' );

			// End all case breaks.
	}
}

/**
 * Take a date in string format and handle the nicer display.
 *
 * @param  string $date    The date string.
 * @param  string $key     Optional single key to return.
 * @param  string $format  What date formatting we want.
 *
 * @return mixed
 */
function build_date_display( $date = '', $key = '', $format = '' ) {

	// Bail without a date.
	if ( empty( $date ) ) {
		return false;
	}

	// Grab the desired date formatting.
	$formatting = ! empty( $format ) ? esc_attr( $format ) : get_option( 'date_format', 'Y-m-d' );

	// Set the date to a stamp.
	$timestamp = strtotime( $date );

	// Now set the formatting.
	$formatted = date( $formatting, $timestamp );

	// Get my relative date.
	$relative = sprintf( _x( '%s ago', '%s = human-readable time difference', 'woo-interest-in-products' ), human_time_diff( $timestamp, current_time( 'timestamp', 1 ) ) );

	// Now set an array of each value.
	$dataset = array(
		'timestamp' => $timestamp,
		'formatted' => $formatted,
		'relative'  => $relative,
	);

	// Return the entire array if didn't specify a key.
	if ( ! $key ) {
		return $dataset;
	}

	// Now return the single requested key.
	return isset( $dataset[ $key ] ) ? $dataset[ $key ] : false;
}

/**
 * Compare two arrays to see if they are identical.
 *
 * @param  array $source  The source array.
 * @param  array $update  The second array to compare to.
 *
 * @return boolean
 */
function compare_id_arrays( $source, $update ) {

	// First make sure both are set with integers.
	$source = array_map( 'absint', $source );
	$update = array_map( 'absint', $update );

	// First run our sorts.
	sort( $source );
	sort( $update );

	// Run our comparison.
	return $source === $update;
}
