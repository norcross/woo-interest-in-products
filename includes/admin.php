<?php
/**
 * The functionality tied to the settings tab.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Admin;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;

/**
 * Start our engines.
 */
add_action( 'admin_notices', __NAMESPACE__ . '\activation_prompt_notice' );
add_action( 'admin_notices', __NAMESPACE__ . '\activation_prompt_results' );
add_action( 'admin_init', __NAMESPACE__ . '\activation_prompt_request' );

/**
 * Check for the prompt key to enable all items.
 *
 * @return void
 */
function activation_prompt_notice() {

	// Check for the prompt key.
	$prompt = get_option( Core\OPTION_PREFIX . 'enable_all_prompt', false );

	// Bail if the key isn't there.
	if ( empty( $prompt ) ) {
		return;
	}

	// Set my base links.
	$en_yes = add_query_arg( array( 'prod-interest-enable-all' => 'yes' ), admin_url( '/' ) );
	$en_no  = add_query_arg( array( 'prod-interest-enable-all' => 'no' ), admin_url( '/' ) );

	// Set my text.
	$msgtxt = __( 'Would you like to enable product interest signups on all your products now?', 'woo-interest-in-products' );

	// Set my links.
	$links = '<a href="' . esc_url( $en_yes ) . '">' . __( 'Yes', 'woo-interest-in-products' ) . '</a> | <a href="' . esc_url( $en_no ) . '">' . __( 'No', 'woo-interest-in-products' ) . '</a>';

	// Set an empty.
	$field = '';

	// Start the notice markup.
	$field .= '<div class="notice notice-info">';

		// Display the actual message.
		$field .= '<p><strong>' . wp_kses_post( $msgtxt ) . '</strong>&nbsp;&nbsp;&nbsp;' . wp_kses_post( $links ) . '</p>';

	// And close the div.
	$field .= '</div>';

	// Echo it.
	echo $field; // WPCS: XSS ok.
}

/**
 * Check for the prompt key to enable all items.
 *
 * @return void
 */
function activation_prompt_results() {

	// Check for the query string before proceeding.
	if ( empty( $_GET['prod-interest-enable-result'] ) ) {
		return;
	}

	// Handle my different action types.
	switch ( sanitize_text_field( $_GET['prod-interest-enable-result'] ) ) {

		case 'enabled':
			$msgtxt = __( 'All your current products have been enabled.', 'woo-interest-in-products' );
			break;

		case 'done':
			$msgtxt = __( 'Your selection has been recorded.', 'woo-interest-in-products' );
			break;
	}

	// Set an empty.
	$field = '';

	// Start the notice markup.
	$field .= '<div class="notice notice-success is-dismissible">';

		// Display the actual message.
		$field .= '<p><strong>' . wp_kses_post( $msgtxt ) . '</strong></p>';

	// And close the div.
	$field .= '</div>';

	// Echo it.
	echo $field; // WPCS: XSS ok.
}

/**
 * Check for the request to enable all the products.
 *
 * @return void
 */
function activation_prompt_request() {

	// Check for the query string before proceeding.
	if ( empty( $_GET['prod-interest-enable-all'] ) ) {
		return;
	}

	// Handle my different action types.
	switch ( sanitize_text_field( $_GET['prod-interest-enable-all'] ) ) {

		case 'yes':
			activation_prompt_enable();
			break;

		case 'no':
			activation_prompt_dismiss();
			break;
	}
}

/**
 * Handle our admin redirect based on the request.
 *
 * @param  string $key  Which result key we want.
 *
 * @return void
 */
function activation_prompt_redirect( $key = '' ) {

	// Set my redirect link.
	$redirect_link = add_query_arg( array( 'prod-interest-enable-result' => sanitize_text_field( $key ) ), admin_url( '/' ) );

	// Do the redirect.
	wp_safe_redirect( $redirect_link );
	exit;
}

/**
 * Enable all the current products products.
 *
 * @return void
 */
function activation_prompt_enable() {

	// Get all my product IDs.
	$products = Queries\get_all_products();

	// Loop my IDs and add the meta key.
	foreach ( $products as $product_id ) {
		update_post_meta( $product_id, Core\PROD_META_KEY, true );
	}

	// Delete the option key.
	delete_option( Core\OPTION_PREFIX . 'enable_all_prompt' );

	// And redirect.
	activation_prompt_redirect( 'enabled' );
}

/**
 * Enable all the current products products.
 *
 * @return void
 */
function activation_prompt_dismiss() {

	// Delete the option key.
	delete_option( Core\OPTION_PREFIX . 'enable_all_prompt' );

	// And redirect.
	activation_prompt_redirect( 'done' );
}
