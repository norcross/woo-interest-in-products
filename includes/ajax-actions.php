<?php
/**
 * Our various Ajax calls.
 *
 * @package WooGDPRUserOptIns
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\AjaxActions;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;
use LiquidWeb\WooInterestInProducts\Layout as Layout;

/**
 * Start our engines.
 */
add_action( 'wp_ajax_woo_update_customer_product_interest', __NAMESPACE__ . '\update_customer_product_interest' );

/**
 * Update our user opt-in values.
 *
 * @return mixed
 */
function update_customer_product_interest() {

	// Check our various constants.
	if ( ! Helpers\check_ajax_constants() ) {
		return;
	}

	// Check for the specific action.
	if ( empty( $_POST['action'] ) || 'woo_update_customer_product_interest' !== sanitize_text_field( $_POST['action'] ) ) { // WPCS: CSRF ok.
		return;
	}

	// Check to see if our nonce was provided.
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wc_customer_interests_change_action' ) ) {
		send_ajax_error_response( 'invalid-nonce' );
	}

	// Check for the customer ID field.
	if ( empty( $_POST['customer_id'] ) ) {
		send_ajax_error_response( 'no-customer-id' );
	}

	// Set my customer ID as a variable because I use it a lot.
	$customer_id = absint( $_POST['customer_id'] );

	// Determine my original IDs.
	$original_ids = ! empty( $_POST['original_ids'] ) ? explode( ',', sanitize_text_field( $_POST['original_ids'] ) ) : Queries\get_products_for_customer( $customer_id, 'relationship_id' );

	// Check for the original IDs to exist.
	if ( empty( $original_ids ) ) {
		send_ajax_error_response( 'no-original-ids' );
	}

	// If we don't have ANY IDs, then we're unsubscribing to everything.
	if ( ! isset( $_POST['interest_ids'] ) ) {

		// Remove everything.
		Queries\remove_single_relationships( $original_ids, $customer_id );

		// Send the success.
		send_ajax_success_response( false );
	}

	// Set our new IDs as a separate variable for comparisons.
	$interest_ids = explode( ',', sanitize_text_field( $_POST['interest_ids'] ) );

	// Check to see if we are matching.
	$compare_ids = Helpers\compare_id_arrays( $original_ids, $interest_ids );

	// If we have a matching set, just return nothing.
	if ( false !== $compare_ids ) {

		// Generate the markup.
		$markup = Layout\customer_interest_list_items( null, $customer_id );

		// And be done.
		send_ajax_success_response( $markup );
	}

	// Loop our original IDs and remove the ones that aren't in our group.
	foreach ( $original_ids as $original_id ) {

		// If the original ID is in the new ID array, we're good.
		if ( in_array( absint( $original_id ), (array) $interest_ids ) ) {
			continue;
		}

		// Didn't find it, so we remove it.
		Queries\remove_single_relationships( $original_id, $customer_id );
	}

	// Generate the markup.
	$markup = Layout\customer_interest_list_items( null, $customer_id );

	// And be done.
	send_ajax_success_response( $markup );
}

/**
 * Build and process our Ajax error handler.
 *
 * @param  string $errcode  The error code in question.
 *
 * @return array
 */
function send_ajax_error_response( $errcode = '' ) {

	// Get my message text.
	$msgtxt = Helpers\notice_text( $errcode );

	// Get my notice markup.
	$notice = Layout\get_account_message_markup( $msgtxt );

	// Build our return.
	$return = array(
		'errcode' => $errcode,
		'message' => $msgtxt,
		'notice'  => $notice,
	);

	// And handle my JSON return.
	wp_send_json_error( $return );
}

/**
 * Build and process our Ajax success handler.
 *
 * @param  mixed  $markup  The markup to display.
 * @param  string $code    The message code in question.
 *
 * @return array
 */
function send_ajax_success_response( $markup, $msgcode = 'success-change-interests' ) {

	// Determine if we are empty.
	$empty = ! $markup ? Layout\get_customer_no_items() : false;

	// Get my message text.
	$msgtxt = Helpers\notice_text( $msgcode );

	// Build our return.
	$return = array(
		'errcode' => null,
		'message' => $msgtxt,
		'markup'  => $markup,
		'empty'   => $empty,
		'notice'  => Layout\get_account_message_markup( $msgtxt, 'success', false ),
	);

	// And handle my JSON return.
	wp_send_json_success( $return );
}
