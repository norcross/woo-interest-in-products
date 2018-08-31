<?php
/**
 * The functionality tied to the WP-CLI stuff.
 *
 * @package WooInterestInProducts
 */

// Call our namepsace.
namespace LiquidWeb\WooInterestInProducts;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;

// Pull in the CLI items.
use WP_CLI;
use WP_CLI_Command;

/**
 * Extend the CLI command class with our own.
 */
class Commands extends WP_CLI_Command {

	/**
	 * Get the array of arguments for the runcommand function.
	 *
	 * @param  array $custom  Any custom args to pass.
	 *
	 * @return array
	 */
	protected function get_command_args( $custom = array() ) {

		// Set my base args.
		$args = array(
			'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
			'parse'      => 'json', // Parse captured STDOUT to JSON array.
			'launch'     => false,  // Reuse the current process.
			'exit_error' => false,   // Halt script execution on error.
		);

		// Return either the base args, or the merged item.
		return ! empty( $custom ) ? wp_parse_args( $args, $custom ) : $args;
	}

	/**
	 * Run and set all the product flags.
	 *
	 * ## OPTIONS
	 *
	 * [--active]
	 * : Whether to set the product as active or not.
	 * ---
	 * default: true
	 * options:
	 *   - true
	 *   - false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-product-interest enable_all_products
	 *
	 * @when after_wp_load
	 */
	function enable_all_products( $args = array(), $assoc_args = array() ) {

		// Parse out the associatives.
		$parsed = wp_parse_args(
			$assoc_args, array(
				'active' => true,
			)
		);

		// Get my products.
		$products = WP_CLI::runcommand( 'post list --post_type=product --post_status=publish --field=ID --format=json', $this->get_command_args() );

		// Bail on empty or error.
		if ( empty( $products ) || is_wp_error( $products ) ) {
			WP_CLI::error( __( 'No product IDs could be retrieved.', 'woo-interest-in-products' ) );
		}

		// Set a counter.
		$update = 0;

		// Set up the progress bar.
		$ticker = \WP_CLI\Utils\make_progress_bar( __( 'Updating products...', 'woo-interest-in-products' ), count( $products ) );

		// Now loop my product IDs and either set or delete the key.
		foreach ( $products as $product_id ) {

			// Set the command based on what was requested.
			$setcommand = $parsed['active'] ? 'add' : 'delete';

			// Now run the actual command.
			WP_CLI::runcommand( 'post meta ' . escapeshellarg( $setcommand ) . ' ' . absint( $product_id ) . ' ' . Core\PROD_META_KEY . ' 1 --quiet=true' );

			// Increment the counter.
			$update++;

			// Add to the progress bar status.
			$ticker->tick();
		}

		// And done.
		$ticker->finish();

		// Show the result and bail.
		WP_CLI::success( sprintf( _n( '%d product has been updated.', '%d products have been updated.', absint( $update ), 'woo-interest-in-products' ), absint( $update ) ) );
		WP_CLI::halt( 0 );
	}

	/**
	 * This is a placeholder function for testing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-product-interest runtests
	 *
	 * @when after_wp_load
	 */
	function runtests() {
		// This is blank, just here when I need it.
	}

	// End all custom CLI commands.
}
