<?php
/**
 * The individual post meta setup.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\PostMeta;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Layout as Layout;
use LiquidWeb\WooInterestInProducts\Queries as Queries;

/**
 * Start our engines.
 */
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\load_post_meta_assets' );
add_action( 'add_meta_boxes_product', __NAMESPACE__ . '\load_subscribed_customers_metabox', 11 );
add_action( 'woocommerce_product_options_advanced', __NAMESPACE__ . '\display_product_subscribe_checkbox' );
add_action( 'woocommerce_process_product_meta', __NAMESPACE__ . '\save_product_subscribe' );

/**
 * Load our admin side post editor JS and CSS.
 *
 * @param $hook  Admin page hook we are current on.
 *
 * @return void
 */
function load_post_meta_assets( $hook ) {

	// Run the check for being on the post editor on products.
	$is_editor = Helpers\check_admin_screen( 'post_type', 'product' );

	// Bail if we aren't there.
	if ( ! $is_editor ) {
		return;
	}

	// Set my handle.
	$handle = 'woo-interest-in-products-editor';

	// Set a file suffix structure based on whether or not we want a minified version.
	$file = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? $handle : $handle . '.min';

	// Set a version for whether or not we're debugging.
	$vers = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : Core\VERS;

	// Load our CSS file.
	wp_enqueue_style( $handle, Core\ASSETS_URL . '/css/' . $file . '.css', false, $vers, 'all' );
}

/**
 * Display a metabox with all the subscribed users.
 *
 * @param  object $post  The post object.
 *
 * @return void
 */
function load_subscribed_customers_metabox( $post ) {

	// Bail if we don't have an enabled product.
	if ( ! Helpers\maybe_product_enabled( $post->ID ) ) {
		return;
	}

	// Load the box.
	add_meta_box( 'list-product-subs', __( 'Interested Customers', 'woo-interest-in-products' ), __NAMESPACE__ . '\display_interested_customers', 'product', 'side', 'default' );
}

/**
 * Load the metabox for all the subscribed users.
 *
 * @param  object $post  The post object we are currently working with.
 *
 * @return HTML
 */
function display_interested_customers( $post ) {

	// Grab the customers.
	$customers = Queries\get_customers_for_product( $post->ID );

	// If no customers exist, display a message and bail.
	if ( ! $customers ) {

		// Output the actual message.
		echo '<p class="howto">' . esc_html__( 'No customers have signed up to this product.', 'woo-interest-in-products' ) . '</p>';

		// And bail.
		return;
	}

	// We have customers, so lets set up a list.
	Layout\get_subscribed_customers_admin_list( $customers, true );
}

/**
 * Display the checkbox for including an opt-in.
 *
 * @return HTML
 */
function display_product_subscribe_checkbox() {

	// Call our global.
	global $post;

	// Output our checkbox all Woo style.
	woocommerce_wp_checkbox(
		array(
			'id'            => 'include_product_subscribe',
			'value'         => Helpers\maybe_product_enabled( $post->ID, true ),
			'wrapper_class' => 'show_if_simple show_if_variable show_if_external hide_if_grouped',
			'label'         => __( 'Include product signup', 'woo-interest-in-products' ),
			'description'   => __( 'Displays a checkbox for this particular product at checkout.', 'woo-interest-in-products' ),
			'cbvalue'       => 'yes',
		)
	);

	// Include the nonce field.
	wp_nonce_field( 'woo_prodsubs_nonce_action', 'woo_prodsubs_nonce_name', false, true );
}

/**
 * Save whether or not the product should display an opt-in.
 *
 * @param  integer $post_id  The individual post ID.
 *
 * @return void
 */
function save_product_subscribe( $post_id ) {

	// Bail if it isn't an actual product.
	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}

	// Make sure the current user has the ability to save.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Check to see if our nonce was provided.
	if ( empty( $_POST['woo_prodsubs_nonce_name'] ) || ! wp_verify_nonce( $_POST['woo_prodsubs_nonce_name'], 'woo_prodsubs_nonce_action' ) ) {
		return;
	}

	// Get our product.
	$prod = wc_get_product( $post_id );

	// Handle the meta based on what was passed.
	if ( ! empty( $_POST['include_product_subscribe'] ) ) {
		$prod->update_meta_data( Core\PROD_META_KEY, true );
	} else {
		$prod->delete_meta_data( Core\PROD_META_KEY );
	}

	// And save the product.
	$prod->save();

	// Delete the transient storing the enabled products.
	delete_transient( 'woo_product_interest_ids' );

	// @@todo add the product ID to some other option?
}
