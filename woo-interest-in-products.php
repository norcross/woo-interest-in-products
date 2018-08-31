<?php
/**
 * Plugin Name: WooCommerce Interest In Products
 * Plugin URI:  https://github.com/liquidweb/woo-interest-in-products
 * Description: Allow customers to opt-in to notices about individual products.
 * Version:     0.0.1
 * Author:      Liquid Web
 * Author URI:  https://www.liquidweb.com
 * Text Domain: woo-interest-in-products
 * Domain Path: /languages
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts;

// Call our CLI namespace.
use WP_CLI;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define our plugin version.
define( __NAMESPACE__ . '\VERS', '0.0.1' );

// Define our database version.
define( __NAMESPACE__ . '\DB_VERS', '1' );

// Plugin root file.
define( __NAMESPACE__ . '\FILE', __FILE__ );

// Define our file base.
define( __NAMESPACE__ . '\BASE', plugin_basename( __FILE__ ) );

// Plugin Folder URL.
define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

// Set our assets directory constant.
define( __NAMESPACE__ . '\ASSETS_URL', URL . 'assets' );

// Set our tab base slug constant.
define( __NAMESPACE__ . '\MENU_SLUG', 'product-interest-list' );

// Set our front menu endpoint constant.
define( __NAMESPACE__ . '\FRONT_VAR', 'product-interest' );

// Set our custom table name.
define( __NAMESPACE__ . '\TABLE_NAME', 'wc_product_interest' );

// Set the prefix for our actions and filters.
define( __NAMESPACE__ . '\HOOK_PREFIX', 'woo_products_interest_' );

// Set the prefix for our stored options.
define( __NAMESPACE__ . '\OPTION_PREFIX', 'wc_product_interest_' );

// Set the option key used to store the schema.
define( __NAMESPACE__ . '\SCHEMA_KEY', HOOK_PREFIX . 'db_version' );

// Set the meta key name for products.
define( __NAMESPACE__ . '\PROD_META_KEY', '_product_interest_enabled' );

// Load the multi-use files.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/query-mods.php';

// Load the triggered file loads.
require_once __DIR__ . '/includes/activate.php';
require_once __DIR__ . '/includes/deactivate.php';
require_once __DIR__ . '/includes/uninstall.php';

// Load the individual files.
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/post-meta.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/ajax-actions.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/export.php';
require_once __DIR__ . '/includes/table-views.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/settings-tab.php';


// Check that we have the constant available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	// Load our commands file.
	require_once dirname( __FILE__ ) . '/includes/commands.php';

	// And add our command.
	WP_CLI::add_command( 'woo-product-interest', Commands::class );
}
