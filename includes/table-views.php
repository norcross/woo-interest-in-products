<?php
/**
 * Our table setup for the handling the data pieces.
 *
 * @package WooInterestInProducts
 */

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Database as Database;
use LiquidWeb\WooInterestInProducts\Queries as Queries;
use LiquidWeb\WooInterestInProducts\DataExport as Export;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Create a new table class that will extend the WP_List_Table.
 */
class ProductInterestSignups_Table extends WP_List_Table {

	/**
	 * SingleProductSubscriptions_Table constructor.
	 *
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 */
	public function __construct() {

		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => __( 'Product Interest Signup', 'woo-interest-in-products' ),
				'plural'   => __( 'Product Interest Signups', 'woo-interest-in-products' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {

		// Roll out each part.
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$dataset  = $this->table_data();

		// Check for the _POST value to filter.
		if ( ! empty( $_POST['wc-product-interest-filter-submit'] ) ) {
			$dataset = $this->maybe_filter_dataset( $dataset );
		}

		// Handle our sorting.
		usort( $dataset, array( $this, 'sort_data' ) );

		// Load up the pagination settings.
		$paginate   = 10;
		$item_count = count( $dataset );
		$current    = $this->get_pagenum();

		// Set my pagination args.
		$this->set_pagination_args(
			array(
				'total_items' => $item_count,
				'per_page'    => $paginate,
				'total_pages' => ceil( $item_count / $paginate ),
			)
		);

		// Slice up our dataset.
		$dataset = array_slice( $dataset, ( ( $current - 1 ) * $paginate ), $paginate );

		// Do the column headers
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Make sure we have the single action running.
		$this->process_single_action();

		// Make sure we have the bulk action running.
		$this->process_bulk_action();

		// Check for the _POST value to export.
		if ( ! empty( $_POST['wc-product-interest-export-submit'] ) && 'all' === sanitize_text_field( $_POST['wc-product-interest-export-submit'] ) ) {
			$this->process_export_submit();
		}

		// And the result.
		$this->items = $dataset;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table.
	 *
	 * @return Array
	 */
	public function get_columns() {

		// Build our array of column setups.
		$setup = array(
			'cb'            => '<input type="checkbox" />',
			'customer_name' => __( 'Customer Name', 'woo-interest-in-products' ),
			'product_name'  => __( 'Product Name', 'woo-interest-in-products' ),
			'signup_date'   => __( 'Signup Date', 'woo-interest-in-products' ),
			'action_list'   => __( 'Actions', 'woo-interest-in-products' ),
		);

		// Return filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_column_items', $setup );
	}

	/**
	 * Display all the things.
	 *
	 * @return HTML
	 */
	public function display() {

		// Add a nonce for the bulk action.
		wp_nonce_field( 'wc_product_interest_nonce_action', 'wc_product_interest_nonce_name' );

		// And the parent display (which is most of it).
		parent::display();
	}

	/**
	 * Add extra markup in the toolbars before or after the list.
	 *
	 * @param string $which  Which markup area after (bottom) or before (top) the list.
	 */
	protected function extra_tablenav( $which ) {

		// Bail if we aren't on the top.
		if ( 'top' !== $which ) {
			return;
		}

		// Begin the markup.
		echo '<div class="alignleft actions">';

			// The product dropdown.
			$this->product_filter_dropdown();

			// The customer dropdown.
			$this->customer_filter_dropdown();

			// Handle adding additional table nav actions.
			do_action( Core\HOOK_PREFIX . 'extra_tablenav', '', $which );

			// And handle our filter button.
			echo '<button class="button action" name="wc-product-interest-filter-submit" type="submit" value="1">' . esc_html__( 'Filter', 'woo-interest-in-products' ) . '</button>';

			// And handle our export button.
			echo '<div class="wc-product-interest-tablenav-item wc-product-interest-export-submit-wrap">';
				echo '<button class="button action" name="wc-product-interest-export-submit" type="submit" value="all">' . esc_html__( 'Export All Entries', 'woo-interest-in-products' ) . '</button>';
			echo '</div>';

		// Close the div.
		echo '</div>';
	}

	/**
	 * Build and display the dropdown for products.
	 *
	 * @param  boolean $echo  Whether to echo out the markup or return.
	 *
	 * @return HTML
	 */
	protected function product_filter_dropdown( $echo = true ) {

		// Get our enabled products.
		$enabled_products = Queries\get_enabled_products();

		// Bail if we don't have any products to filter by.
		if ( ! $enabled_products ) {
			return;
		}

		// See if we have one selected already.
		$select = ! empty( $_POST['wc-product-interest-product-filter'] ) ? absint( $_POST['wc-product-interest-product-filter'] ) : 0;

		// Set an empty.
		$build = '';

		// Wrap the product dropdown in a div.
		$build .= '<div class="wc-product-interest-tablenav-item wc-product-interest-table-filter wc-product-interest-table-filter-products">';

			// Handle our screen reader label.
			$build .= '<label class="screen-reader-text" for="wc-product-interest-product-filter">' . esc_html__( 'Filter by product', 'woo-interest-in-products' ) . '</label>';

			// Begin the select dropdown.
			$build .= '<select name="wc-product-interest-product-filter" id="wc-product-interest-product-filter" class="postform">';

				// Load our null value.
				$build .= '<option value="0">' . esc_html__( 'All Products', 'woo-interest-in-products' ) . '</option>';

				// Now loop my product IDs and show them.
		foreach ( $enabled_products as $product_id ) {

			// Set our title.
			$pname = get_the_title( $product_id );

			// And load the dropdown.
			$build .= '<option value="' . absint( $product_id ) . '" ' . selected( $select, absint( $product_id ), 0 ) . '>' . esc_html( $pname ) . '</option>';
		}

			// Close the select.
			$build .= '</select>';

		// Close the div.
		$build .= '</div>';

		// And return the build.
		if ( ! $echo ) {
			return $build;
		}

		// Echo out the build.
		echo $build;
	}

	/**
	 * Build and display the dropdown for customers.
	 *
	 * @param  boolean $echo  Whether to echo out the markup or return.
	 *
	 * @return HTML
	 */
	protected function customer_filter_dropdown( $echo = true ) {

		// Get our current customers.
		$current_customers = Queries\get_all_customers();

		// Bail if we don't have any current customers to filter by.
		if ( ! $current_customers ) {
			return;
		}

		// See if we have one selected already.
		$select = ! empty( $_POST['wc-product-interest-customer-filter'] ) ? absint( $_POST['wc-product-interest-customer-filter'] ) : 0;

		// Set an empty.
		$build = '';

		// Wrap the product dropdown in a div.
		$build .= '<div class="wc-product-interest-tablenav-item wc-product-interest-table-filter wc-product-interest-table-filter-customers">';

			// Handle our screen reader label.
			$build .= '<label class="screen-reader-text" for="wc-product-interest-customer-filter">' . esc_html__( 'Filter by customer', 'woo-interest-in-products' ) . '</label>';

			// Begin the select dropdown.
			$build .= '<select name="wc-product-interest-customer-filter" id="wc-product-interest-customer-filter" class="postform">';

				// Load our null value.
				$build .= '<option value="0">' . esc_html__( 'All Customers', 'woo-interest-in-products' ) . '</option>';

				// Now loop my customers and show them.
		foreach ( $current_customers as $customer_id => $customer_data ) {
			$build .= '<option value="' . absint( $customer_id ) . '" ' . selected( $select, absint( $customer_id ), 0 ) . '>' . esc_html( $customer_data['display_name'] ) . '</option>';
		}

			// Close the select.
			$build .= '</select>';

		// Close the div.
		$build .= '</div>';

		// And return the build.
		if ( ! $echo ) {
			return $build;
		}

		// Echo out the build.
		echo $build;
	}

	/**
	 * Return null for our table, since no row actions exist.
	 *
	 * @param  object $item         The item being acted upon.
	 * @param  string $column_name  Current column name.
	 * @param  string $primary      Primary column name.
	 *
	 * @return null
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return apply_filters( Core\HOOK_PREFIX . 'table_row_actions', '', $item, $column_name, $primary );
	}

	/**
	 * Define the sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {

		// Build our array of sortable columns.
		$setup = array(
			'customer_name' => array( 'customer_name', false ),
			'product_name'  => array( 'product_name', true ),
			'signup_date'   => array( 'signup_date', true ),
		);

		// Return it, filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_sortable_columns', $setup );
	}

	/**
	 * Define which columns are hidden.
	 *
	 * @return array
	 */
	public function get_hidden_columns() {

		// Return a blank array, filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_hidden_columns', array() );
	}

	/**
	 * Return available bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {

		// Make a basic array of the actions we wanna include.
		$setup = array(
			'wc_product_interest_unsubscribe' => __( 'Unsubscribe', 'woo-interest-in-products' ),
			'wc_product_interest_export'      => __( 'Export', 'woo-interest-in-products' ),
		);

		// Return it filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_bulk_actions', $setup );
	}

	/**
	 * Handle bulk actions.
	 *
	 * @see $this->prepare_items()
	 */
	protected function process_bulk_action() {

		// Bail if we aren't on the page.
		if ( empty( $this->current_action() ) || ! in_array( $this->current_action(), array_keys( $this->get_bulk_actions() ) ) ) {
			return;
		}

		// Make sure we have the page we want.
		if ( empty( $_GET['page'] ) || Core\MENU_SLUG !== sanitize_text_field( $_GET['page'] ) ) {
			return;
		}

		// Fail on a missing or bad nonce.
		if ( empty( $_POST['wc_product_interest_nonce_name'] ) || ! wp_verify_nonce( $_POST['wc_product_interest_nonce_name'], 'wc_product_interest_nonce_action' ) ) {
			Helpers\admin_page_redirect(
				array(
					'success' => 0,
					'errcode' => 'bad_nonce',
				)
			);
		}

		// Check for the array of relationship IDs being passed.
		if ( empty( $_POST['wc_product_interest_ids'] ) ) {
			Helpers\admin_page_redirect(
				array(
					'success' => 0,
					'errcode' => 'no_ids',
				)
			);
		}

		// Set and sanitize my IDs.
		$relationship_ids = array_map( 'absint', $_POST['wc_product_interest_ids'] );

		// Handle my different bulk actions.
		switch ( $this->current_action() ) {

			case 'wc_product_interest_unsubscribe':
				$this->process_bulk_unsubscribe( $relationship_ids );
				break;

			case 'wc_product_interest_export':
				$this->process_bulk_export( $relationship_ids );
				break;

			// End all case breaks.
		}

		// Got to the end? Why?
		Helpers\admin_page_redirect(
			array(
				'success' => 0,
				'errcode' => 'unknown',
			)
		);
	}

	/**
	 * Handle the export from the button.
	 *
	 * @return void
	 */
	protected function process_export_submit() {

		// Make sure we have the page we want.
		if ( empty( $_GET['page'] ) || Core\MENU_SLUG !== sanitize_text_field( $_GET['page'] ) ) {
			return;
		}

		// Fail on a missing or bad nonce.
		if ( empty( $_POST['wc_product_interest_nonce_name'] ) || ! wp_verify_nonce( $_POST['wc_product_interest_nonce_name'], 'wc_product_interest_nonce_action' ) ) {
			Helpers\admin_page_redirect(
				array(
					'success' => 0,
					'errcode' => 'bad_nonce',
				)
			);
		}

		// Fetch our relationships.
		$relationship_ids = Queries\get_all_subscription_data( 'ids' );

		// Check for the array of relationship IDs being passed.
		if ( empty( $relationship_ids ) ) {
			Helpers\admin_page_redirect(
				array(
					'success' => 0,
					'errcode' => 'no_ids',
				)
			);
		}

		// Run the export bulk.
		$this->process_bulk_export( $relationship_ids );
	}

	/**
	 * Handle the unsubscribe bulk action.
	 *
	 * @param  array $relationship_ids  The IDs requested via bulk.
	 *
	 * @return void
	 */
	private function process_bulk_unsubscribe( $relationship_ids = array() ) {

		// Bail if no relationship IDs were passed.
		if ( empty( $relationship_ids ) ) {
			return false;
		}

		// Now loop and kill each one.
		foreach ( $relationship_ids as $relationship_id ) {
			Database\delete_by_relationship( $relationship_id );
		}

		// If we had customer IDs passed, filter and purge transients.
		if ( ! empty( $_POST['wc_product_interest_customer_ids'] ) ) {
			$this->purge_customer_transients( $_POST['wc_product_interest_customer_ids'] );
		}

		// If we had product IDs passed, filter and purge transients.
		if ( ! empty( $_POST['wc_product_interest_product_ids'] ) ) {
			$this->purge_product_transients( $_POST['wc_product_interest_product_ids'] );
		}

		// Redirect to the success.
		Helpers\admin_page_redirect(
			array(
				'success' => 1,
				'action'  => 'unsubscribed',
				'count'   => count( $relationship_ids ),
			)
		);
	}

	/**
	 * Handle the export bulk action.
	 *
	 * @param  array $relationship_ids  The IDs requested via bulk.
	 *
	 * @return void
	 */
	private function process_bulk_export( $relationship_ids = array() ) {

		// Bail if no relationship IDs were passed.
		if ( empty( $relationship_ids ) ) {
			return false;
		}

		// Add our option key to get later.
		update_option( Core\OPTION_PREFIX . 'export_ids', $relationship_ids, 'no' );

		// Set the nonce for the export.
		$nonce = wp_create_nonce( 'wc_product_interest_export' );

		// Redirect to trigger the export function.
		Helpers\admin_page_redirect(
			array(
				'wc_product_interest_export' => 1,
				'nonce'                      => esc_attr( $nonce ),
			), false
		);
	}

	/**
	 * Delete the transients for customer IDs.
	 *
	 * @param  array $customer_ids  The array of customer IDs we have.
	 *
	 * @return void
	 */
	protected function purge_customer_transients( $customer_ids = array() ) {

		// First sanitize, then filter.
		$customer_ids = array_map( 'absint', $customer_ids );
		$customer_ids = array_unique( $customer_ids );

		// Now loop and purge.
		foreach ( $customer_ids as $customer_id ) {
			delete_transient( 'woo_customer_subscribed_products_' . absint( $customer_id ) );
		}
	}

	/**
	 * Delete the transients for product IDs.
	 *
	 * @param  array $product_ids  The array of product IDs we have.
	 *
	 * @return void
	 */
	protected function purge_product_transients( $product_ids = array() ) {

		// First sanitize, then filter.
		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_unique( $product_ids );

		// Now loop and purge.
		foreach ( $product_ids as $product_id ) {
			delete_transient( 'woo_product_interest_customers_' . absint( $product_id ) );
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {

		// Set my ID.
		$id = absint( $item['id'] );

		// Return my checkbox.
		return '<input type="checkbox" name="wc_product_interest_ids[]" class="wc-product-interest-admin-checkbox" id="cb-' . $id . '" value="' . $id . '" /><label for="cb-' . $id . '" class="screen-reader-text">' . __( 'Select signup', 'woo-interest-in-products' ) . '</label>';
	}

	/**
	 * The visible name column.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_customer_name( $item ) {

		// Build my markup.
		$setup = '';

		// Set the display name.
		$setup     .= '<span class="wc-product-interest-admin-table-display wc-product-interest-admin-table-customer-name">';
			$setup .= esc_html( $item['customer_name'] );
		$setup     .= '</span>';

		// Create my formatted date.
		$setup = apply_filters( Core\HOOK_PREFIX . 'column_customer_name', $setup, $item );

		// Return, along with our row actions.
		return $setup . $this->row_actions( $this->setup_row_action_items( $item ) );
	}

	/**
	 * The product name column.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_product_name( $item ) {

		// Build my markup.
		$setup = '';

		// Set the product name.
		$setup     .= '<span class="wc-product-interest-admin-table-display wc-product-interest-admin-table-product-name">';
			$setup .= esc_html( $item['product_name'] );
		$setup     .= '</span>';

		// Include the SKU if we have one.
		if ( ! empty( $item['product_sku'] ) ) {

			// Output the SKU field.
			$setup     .= '<span class="wc-product-interest-admin-table-display wc-product-interest-admin-table-small-line wc-product-interest-admin-table-product-sku">';
				$setup .= '<label>' . esc_html__( 'SKU', 'woo-interest-in-products' ) . '</label>: ' . esc_html( $item['product_sku'] );
			$setup     .= '</span>';

		}

		// Return my formatted product name.
		return apply_filters( Core\HOOK_PREFIX . 'column_product_name', $setup, $item );
	}

	/**
	 * The signup date column.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_signup_date( $item ) {

		// Fetch our date setup.
		$date_setup = Helpers\build_date_display( $item['signup_date'] );

		// Build my markup.
		$setup = '';

		// Set the signup date formatted.
		$setup     .= '<span class="wc-product-interest-admin-table-display wc-product-interest-admin-table-signup-date">';
			$setup .= esc_html( $date_setup['formatted'] );
		$setup     .= '</span>';

		// Set the signup date relative.
		$setup     .= '<span class="wc-product-interest-admin-table-display wc-product-interest-admin-table-small-line wc-product-interest-admin-table-signup-relative">';
			$setup .= esc_html( $date_setup['relative'] );
		$setup     .= '</span>';

		// Return my formatted date.
		return apply_filters( Core\HOOK_PREFIX . 'column_signup_date', $setup, $item );
	}

	/**
	 * Our column with eventual actions we might have.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_action_list( $item ) {

		// Get my list of items.
		$action_list_args = $this->column_action_list_args( $item );

		// Bail if nothing.
		if ( ! $action_list_args ) {
			return apply_filters( Core\HOOK_PREFIX . 'column_action_list', '', $item );
		}

		// Build my markup.
		$setup = '';

		// Set up our list.
		$setup .= '<ul class="wc-product-interest-admin-list-wrap">';

		// Loop my list args and build out.
		foreach ( $action_list_args as $action => $args ) {

			// Create our class.
			$class = 'wc-product-interest-admin-list-item wc-product-interest-admin-list-item-' . sanitize_html_class( $action );

			// Create our label.
			$label = esc_html( $args['label'] );

			// Add the icon if we have it.
			if ( ! empty( $args['icon'] ) ) {
				$label = '<i class="wc-product-interest-admin-icon dashicons ' . esc_attr( $args['icon'] ) . '"></i> ' . $label;
			}

			// Open the individual list item.
			$setup .= '<li class="' . esc_attr( $class ) . '">';

				// Set the link.
				$setup .= '<a href="' . esc_url( $args['link'] ) . '">' . $label . '</a>';

			// Close the individual list item.
			$setup .= '</li>';
		}

		// Close up the list.
		$setup .= '</ul>';

		// Add a hidden field with the customer ID.
		$setup .= $this->column_action_hidden_ids( $item );

		// Return the whole thing, filtered.
		return apply_filters( Core\HOOK_PREFIX . 'column_action_list', $setup, $item );
	}

	/**
	 * The data for our actions column.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	private function column_action_list_args( $item ) {

		// Set up our array of items.
		$setup = array(

			// The "view customer" link.
			'view'    => array(
				'label' => __( 'View Customer', 'woo-interest-in-products' ),
				'link'  => $item['customer_edit'],
				'icon'  => 'dashicons-id',
			),

			// The "view orders" link.
			'orders'  => array(
				'label' => __( 'View Orders', 'woo-interest-in-products' ),
				'link'  => $item['customer_orders'],
				'icon'  => 'dashicons-cart',
			),

			// The "view product" link.
			'product' => array(
				'label' => __( 'View Product', 'woo-interest-in-products' ),
				'link'  => $item['product_edit'],
				'icon'  => 'dashicons-album',
			),

		);

		// Return my setup of links.
		return apply_filters( Core\HOOK_PREFIX . 'column_action_list_args', $setup, $item );
	}

	/**
	 * Our two hidden fields for the IDs we need.
	 *
	 * @param  array $item  The item from the data array.
	 *
	 * @return string
	 */
	private function column_action_hidden_ids( $item ) {

		// Bail if no item was passed.
		if ( ! $item ) {
			return;
		}

		// Set our empty.
		$build = '';

		// Add the customer ID, assuming we have one.
		if ( ! empty( $item['customer_id'] ) ) {
			$build .= '<input type="hidden" name="wc_product_interest_customer_ids[]" value="' . absint( $item['customer_id'] ) . '">';
		}

		// Add the product ID, assuming we have one.
		if ( ! empty( $item['product_id'] ) ) {
			$build .= '<input type="hidden" name="wc_product_interest_product_ids[]" value="' . absint( $item['product_id'] ) . '">';
		}

		// Return my hidden field builds.
		return apply_filters( Core\HOOK_PREFIX . 'column_action_hidden_ids', $build, $item );
	}

	/**
	 * Message to be displayed when there are no items
	 */
	public function no_items() {

		// If we have no filtering, return the default.
		if ( empty( $_POST['wc-product-interest-filter-submit'] ) ) {

			// Echo out the message.
			echo apply_filters( Core\HOOK_PREFIX . 'column_no_items_text', '<em>' . esc_html__( 'No current signups were found.', 'woo-interest-in-products' ) . '</em>', false );

			// And return, so we don't mess with it more.
			return;
		}

		// Echo out the 'no items' verbiage.
		echo apply_filters( Core\HOOK_PREFIX . 'column_no_items_text', '<em>' . esc_html__( 'No signups for the selected products or customers were found.', 'woo-interest-in-products' ) . '</em>', true );
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function table_data() {

		// Get all the relationship data.
		$relationships = Queries\get_all_subscription_data();

		// Bail with no data.
		if ( ! $relationships ) {
			return array();
		}

		// Set my empty.
		$data = array();

		// Now loop each customer info.
		foreach ( $relationships as $relationship_id => $relationship_data ) {

			// Set my order args.
			$order_args = array(
				'post_type'      => 'shop_order',
				'post_status'    => 'all',
				'_customer_user' => absint( $relationship_data['customer_id'] ),
			);

			// Set the array of the data we want.
			$setup = array(
				'id'              => absint( $relationship_id ),
				'product_id'      => absint( $relationship_data['product_id'] ),
				'product_name'    => esc_attr( $relationship_data['product']['post_title'] ),
				'product_edit'    => get_edit_post_link( absint( $relationship_data['product_id'] ), 'raw' ),
				'product_link'    => get_permalink( absint( $relationship_data['product_id'] ) ),
				'product_sku'     => esc_attr( $relationship_data['product']['product_sku'] ),
				'customer_id'     => absint( $relationship_data['customer_id'] ),
				'customer_name'   => esc_attr( $relationship_data['customer']['display_name'] ),
				'customer_edit'   => get_edit_user_link( absint( $relationship_data['product_id'] ), 'raw' ),
				'customer_email'  => esc_attr( $relationship_data['customer']['user_email'] ),
				'customer_orders' => add_query_arg( $order_args, admin_url( 'edit.php' ) ),
				'signup_date'     => esc_attr( $relationship_data['signup_date'] ),
			);

			// Run it through a filter.
			$data[] = apply_filters( Core\HOOK_PREFIX . 'table_data_item', $setup, $relationship_id, $relationship_data );
		}

		// Return our data.
		return apply_filters( Core\HOOK_PREFIX . 'table_data_array', $data, $relationships );
	}

	/**
	 * Take the default dataset and filter it.
	 *
	 * @param  array $dataset  The current dataset we have.
	 *
	 * @return array
	 */
	protected function maybe_filter_dataset( $dataset = array() ) {

		// Return the dataset we got if we don't have the submit.
		if ( empty( $_POST['wc-product-interest-filter-submit'] ) ) {
			return $dataset;
		}

		// Make sure we have the page we want.
		if ( empty( $_GET['page'] ) || Core\MENU_SLUG !== sanitize_text_field( $_GET['page'] ) ) {
			return $dataset;
		}

		// Fail on a missing or bad nonce.
		if ( empty( $_POST['wc_product_interest_nonce_name'] ) || ! wp_verify_nonce( $_POST['wc_product_interest_nonce_name'], 'wc_product_interest_nonce_action' ) ) {
			Helpers\admin_page_redirect(
				array(
					'success' => 0,
					'errcode' => 'bad_nonce',
				)
			);
		}

		// Handle a product ID filter.
		if ( ! empty( $_POST['wc-product-interest-product-filter'] ) && 'product' === get_post_type( absint( $_POST['wc-product-interest-product-filter'] ) ) ) {
			$dataset = $this->filter_dataset_by_id( $dataset, absint( $_POST['wc-product-interest-product-filter'] ), 'product_id' );
		}

		// Handle a customer ID filter.
		if ( ! empty( $_POST['wc-product-interest-customer-filter'] ) ) {
			$dataset = $this->filter_dataset_by_id( $dataset, absint( $_POST['wc-product-interest-customer-filter'] ), 'customer_id' );
		}

		// And return the dataset, however we have it.
		return $dataset;
	}

	/**
	 * Filter out the dataset by ID.
	 *
	 * @param  array   $dataset  The dataset we wanna filter.
	 * @param  integer $id       The specific ID we wanna check for.
	 * @param  string  $type     Which ID type. Either 'product_id', 'customer_id', or 'id'.
	 *
	 * @return array
	 */
	private function filter_dataset_by_id( $dataset = array(), $id = 0, $type = '' ) {

		// Bail without a dataset, ID, or type.
		if ( empty( $dataset ) || empty( $id ) || empty( $type ) ) {
			return;
		}

		// Loop the dataset.
		foreach ( $dataset as $index => $values ) {

			// If we do not have a match, unset it and go about our day.
			if ( absint( $id ) !== absint( $values[ $type ] ) ) {
				unset( $dataset[ $index ] );
			}
		}

		// Return thge dataset, with the array keys reset.
		return ! empty( $dataset ) ? array_values( $dataset ) : array();
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array  $dataset      Our entire dataset.
	 * @param  string $column_name  Current column name
	 *
	 * @return mixed
	 */
	public function column_default( $dataset, $column_name ) {

		// Run our column switch.
		switch ( $column_name ) {

			case 'customer_name':
			case 'product_name':
			case 'signup_date':
			case 'action_list':
				return ! empty( $dataset[ $column_name ] ) ? $dataset[ $column_name ] : '';

			default:
				return apply_filters( Core\HOOK_PREFIX . 'table_column_default', '', $dataset, $column_name );
		}
	}

	/**
	 * Handle the single row action.
	 *
	 * @return void
	 */
	protected function process_single_action() {
		// There will likely be something here.
	}

	/**
	 * Create the row actions we want.
	 *
	 * @param  array $item  The item from the dataset.
	 *
	 * @return array
	 */
	private function setup_row_action_items( $item ) {
		return apply_filters( Core\HOOK_PREFIX . 'table_row_actions', array(), $item );
	}

	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @return Mixed
	 */
	private function sort_data( $a, $b ) {

		// Set defaults and check for query strings.
		$ordby = ! empty( $_GET['orderby'] ) ? $_GET['orderby'] : 'signup_date';
		$order = ! empty( $_GET['order'] ) ? $_GET['order'] : 'asc';

		// Set my result up.
		$result = strcmp( $a[ $ordby ], $b[ $ordby ] );

		// Return it one way or the other.
		return 'asc' === $order ? $result : -$result;
	}
}
