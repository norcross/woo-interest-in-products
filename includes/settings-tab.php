<?php
/**
 * The functionality tied to the settings tab.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\SettingsTab;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;

/**
 * Start our engines.
 */
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\load_settings_assets' );
add_action( 'admin_menu', __NAMESPACE__ . '\load_settings_menu', 99 );

/**
 * Load our admin side JS and CSS.
 *
 * @param $hook  Admin page hook we are current on.
 *
 * @return void
 */
function load_settings_assets( $hook ) {

	// Confirm we are on the settings page.
	if ( ! Helpers\maybe_admin_settings_page( $hook ) ) {
		return;
	}

	// Set my handle.
	$handle = 'woo-interest-in-products-admin';

	// Set a file suffix structure based on whether or not we want a minified version.
	$file = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? $handle : $handle . '.min';

	// Set a version for whether or not we're debugging.
	$vers = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : Core\VERS;

	// Load our CSS file.
	wp_enqueue_style( $handle, Core\ASSETS_URL . '/css/' . $file . '.css', false, $vers, 'all' );
}

/**
 * Load our menu item.
 *
 * @return void
 */
function load_settings_menu() {

	// Add our submenu page.
	add_submenu_page(
		'edit.php?post_type=product',
		__( 'Product Interest Signups', 'woo-interest-in-products' ),
		__( 'Product Interest', 'woo-interest-in-products' ),
		'manage_options',
		Core\MENU_SLUG,
		__NAMESPACE__ . '\settings_page_view'
	);
}

/**
 * Our actual settings page for things.
 *
 * @return mixed
 */
function settings_page_view() {

	// Wrap the entire thing.
	echo '<div class="wrap wc-product-interest-admin-wrap">';

		// Handle the title.
		echo '<h1 class="wc-product-interest-admin-title">' . get_admin_page_title() . '</h1>';

		// Handle the table.
		echo interest_list_table();

	// Close the entire thing.
	echo '</div>';
}

/**
 * Create and return the table of interest signup data.
 *
 * @param  array $requests  The existing requests.
 *
 * @return HTML
 */
function interest_list_table() {

	// Pull our list of enabled products.
	$products = Queries\get_enabled_products();

	// Bail if we don't have any.
	if ( empty( $products ) ) {

		// Echo out the message.
		echo '<p>' . esc_html__( 'There are no enabled products.', 'woo-interest-in-products' ) . '</p>';

		// And be done.
		return;
	}

	// Fetch the action link.
	$action = Helpers\get_admin_menu_link();

	// Call our table class.
	$table = new \ProductInterestSignups_Table();

	// And output the table.
	$table->prepare_items();

	// And handle the display
	echo '<form class="wc-product-interest-admin-form" id="wc-product-interest-admin-form" action="' . esc_url( $action ) . '" method="post">';

	// The actual table itself.
	$table->display();

	// And close it up.
	echo '</form>';
}
