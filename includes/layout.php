<?php
/**
 * Our layout functions to use across the plugin.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Layout;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;
use LiquidWeb\WooInterestInProducts\Helpers as Helpers;
use LiquidWeb\WooInterestInProducts\Queries as Queries;

/**
 * Build out and return the checkboxes for the checkout.
 *
 * @param  array   $products  The array of product IDs.
 * @param  boolean $echo      Whether to echo it out or return.
 *
 * @return HTML
 */
function get_product_interest_checkout_field( $products = array(), $echo = false ) {

	// Bail without any data.
	if ( empty( $products ) || ! is_array( $products ) ) {
		return;
	}

	// Set my label.
	$label = apply_filters( Core\HOOK_PREFIX . 'checkout_label', __( 'Keep me informed about my products.', 'woo-interest-in-products' ), $products );

	// Set a string for my product IDs.
	$ids = implode( ',', $products );

	// Set an empty.
	$build = '';

	// Wrap the field in a paragraph.
	$build .= '<p class="form-row woo-interest-in-products-field">';

		// Output some label.
		$build .= '<label class="woocommerce-form__label woocommerce-form-woo-interest-in-products__label woocommerce-form__label-for-checkbox woo-interest-in-products-checkbox-label checkbox">';

			// Handle our field output.
			$build .= '<input class="woocommerce-form__input woocommerce-form-woo-interest-in-products__input-checkbox woocommerce-form__input-checkbox input-checkbox" name="woo-product-interest" id="" type="checkbox" value="' . esc_attr( $ids ) . '">';

			// Add the label text if present.
			$build .= '<span>' . esc_html( $label ) . '</span>';

		// And close the tag.
		$build .= '</label>';

		// Add a nonce field because nonce fields.
		$build .= wp_nonce_field( 'product_interest_nonce_action', 'product_interest_nonce_name', false, false );

	// Close the single paragraph.
	$build .= '</p>';

	// And echo it out if requested.
	if ( $echo ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Build out and return the list of signed up customers.
 *
 * @param  array   $customers  The array of customer data.
 * @param  boolean $echo       Whether to echo it out or return.
 *
 * @return HTML
 */
function get_subscribed_customers_admin_list( $customers = array(), $echo = false ) {

	// Bail without any customers.
	if ( empty( $customers ) || ! is_array( $customers ) ) {
		return;
	}

	// Grab my admin link and total count.
	$alink = Helpers\get_admin_menu_link();

	// Set an empty.
	$build = '';

	// Begin the table output.
	$build .= '<table class="product-interest-admin-sidebar-table fixed">';

	// Include the headers.
	$build .= get_subscribed_customers_admin_table_headers();

	// Now we loop the customer data and show them individually.
	foreach ( $customers as $customer_data ) {

		// Set my link title.
		$ltitle = sprintf( __( 'Customer ID: %d', 'woo-interest-in-products' ), absint( $customer_data['ID'] ) );

		// Get my formatted signup date.
		$sdate = Helpers\build_date_display( $customer_data['signup_date'], 'formatted' );

		// And the output.
		$build .= '<tr class="product-interest-admin-sidebar-list-item">';

			// Handle the link.
			$build     .= '<td class="product-interest-name-column">';
				$build .= '<a title="' . esc_attr( $ltitle ) . '" href="' . esc_url( $customer_data['user_edit_link'] ) . '">' . esc_html( $customer_data['display_name'] ) . '</a>';
			$build     .= '</td>';

			// Include the signup date.
			$build     .= '<td class="product-interest-date-column">';
				$build .= '<span class="product-interest-admin-sidebar-signup-date">' . esc_html( $sdate ) . '</span>';
			$build     .= '</td>';

		// And close the list item.
		$build .= '</tr>';
	}

	// Close the list.
	$build .= '</table>';

	// Include our "view all" link.
	$build     .= '<p class="product-interest-admin-sidebar-link">';
		$build .= '<a title="' . esc_attr__( 'Click here to view all product interest signups', 'woo-interest-in-products' ) . '" href="' . esc_url( $alink ) . '">' . esc_html__( 'View all product interest signups', 'woo-interest-in-products' ) . '</a>';
	$build     .= '</p>';

	// And echo it out if requested.
	if ( $echo ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Build and output the headers.
 *
 * @return HTML
 */
function get_subscribed_customers_admin_table_headers() {

	// Set an empty.
	$build = '';

	// Start the thead tag.
	$build .= '<thead>';

		// Set the single row.
		$build     .= '<tr>';
			$build .= '<th class="product-interest-name-column">' . esc_html__( 'Name', 'woo-interest-in-products' ) . '</th>';
			$build .= '<th class="product-interest-date-column">' . esc_html__( 'Signup', 'woo-interest-in-products' ) . '</th>';
		$build     .= '</tr>';

	// Close the thead tag.
	$build .= '</thead>';

	// And just return it.
	return $build;
}

/**
 * Build the markup for an account page notice.
 *
 * @param  string  $message      The actual message to display.
 * @param  string  $type         Which type of message it is.
 * @param  boolean $echo         Whether to echo out the markup or return it.
 *
 * @return HTML
 */
function get_account_message_markup( $message = '', $type = 'error', $wrap = false, $echo = false ) {

	// Bail without the required message text.
	if ( empty( $message ) ) {
		return;
	}

	// Get my dismiss link.
	$dslink = Helpers\get_account_tab_link();

	// Set an empty.
	$field = '';

	// Start the notice markup.
	$field .= '<div class="woo-product-interest-account-notice woo-product-interest-account-notice-' . esc_attr( $type ) . '">';

		// Display the actual message.
		$field .= '<p>' . wp_kses_post( $message ) . '</p>';

		// And our dismissal button.
		$field     .= '<a class="woo-product-interest-account-notice-dismiss" href="' . esc_url( $dslink ) . '">';
			$field .= screen_reader_text() . '<i class="dashicons dashicons-no"></i>';
		$field     .= '</a>';

	// And close the div.
	$field .= '</div>';

	// Add the optional wrapper.
	$build = ! $wrap ? $field : '<div class="woo-product-interest-account-notice-wrap">' . $field . '</div>';

	// Echo it if requested.
	if ( ! empty( $echo ) ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Our display when we have no items left.
 *
 * @param  boolean $echo  Whether to echo it out or return.
 *
 * @return HTML
 */
function get_customer_no_items( $echo = false ) {

	// Set an empty.
	$build = '';

	// Show the basic text.
	$build     .= '<div class="woo-interest-in-products-change-form-wrapper">';
		$build .= '<p>' . esc_html__( 'You have not signed up for any products.', 'woo-interest-in-products' ) . '</p>';
	$build     .= '</div>';

	// And echo it out if requested.
	if ( $echo ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Build out and return the list of signed up products by a single customer.
 *
 * @param  array   $dataset      The products the customer has signed up.
 * @param  integer $customer_id  The individual customer ID we're working with.
 * @param  boolean $echo         Whether to echo it out or return.
 *
 * @return HTML
 */
function get_single_customer_signups( $dataset = array(), $customer_id = 0, $echo = false ) {

	// If we don't have products, just bail.
	if ( ! $dataset && ! $customer_id ) {
		return;
	}

	// If we have the ID without a dataset, attempt to get one.
	if ( ! $dataset && $customer_id ) {
		$dataset = Queries\get_products_for_customer( absint( $customer_id ) );
	}

	// If we don't have products, just bail.
	if ( ! $dataset ) {
		return;
	}

	// Get my array of just relationship IDs.
	$relids = wp_list_pluck( $dataset, 'relationship_id' );
	$relstr = implode( ',', $relids );

	// Set my form page link.
	$flink = Helpers\get_account_tab_link();

	// Set an empty.
	$build = '';

	// Set the entire thing in a div for targeting.
	$build .= '<div class="woo-interest-in-products-change-form-wrapper">';

		// Describe what to do.
		$build .= '<p class="woo-interest-in-products-subtitle">' . esc_html__( 'Below are the products you requested to stay informed about. You can review and update your subscriptions at any time.', 'woo-interest-in-products' ) . '</p>';

		// Set the form.
		$build .= '<form class="woo-interest-in-products-change-form" action="' . esc_url( $flink ) . '" method="post">';

			// Create the list items.
			$build .= customer_interest_list_items( $dataset );

			// Open the paragraph for the submit button.
			$build .= '<p class="woo-interest-in-products-change-submit">';

				// Handle the nonce.
				$build .= wp_nonce_field( 'wc_customer_interests_change_action', 'wc_customer_interests_change_nonce', false, false );

				// The button / action combo.
				$build .= '<button class="woocommerce-Button button woo-interest-in-products-change-submit-button" type="submit">' . esc_html__( 'Update Your Selections', 'woo-interest-in-products' ) . '</button>';

				// Our little action field.
				$build .= '<input name="action" value="wc_product_interest_change" type="hidden">';

				// Hidden field for the user ID.
				$build .= '<input id="wc_product_interest_customer_id" name="wc_product_interest_customer_id" value="' . absint( $customer_id ) . '" type="hidden">';

				// Our hidden field of all original IDs.
				$build .= '<input id="wc_product_interest_original_ids" name="wc_product_interest_original_ids" value="' . esc_attr( trim( $relstr ) ) . '" type="hidden">';

			// Close the paragraph.
			$build .= '</p>';

		// Close the form.
		$build .= '</form>';

	// Close the div.
	$build .= '</div>';

	// And echo it out if requested.
	if ( $echo ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Build the individual list (which is Ajaxy).
 *
 * @param  array   $dataset  The dataset we're working with.
 * @param  boolean $echo     Whether to echo it out or return.
 *
 * @return HTML
 */
function customer_interest_list_items( $dataset = array(), $customer_id = 0, $echo = false ) {

	// If we don't have products, just bail.
	if ( ! $dataset && ! $customer_id ) {
		return;
	}

	// If we have the ID without a dataset, attempt to get one.
	if ( ! $dataset && $customer_id ) {
		$dataset = Queries\get_products_for_customer( absint( $customer_id ) );
	}

	// If we don't have products, just bail.
	if ( ! $dataset ) {
		return;
	}

	// Set an empty.
	$build = '';

	// Begin the list output.
	$build .= '<ul class="woo-interest-in-products-list-wrap">';

	// Now we loop the dataset and show them individually.
	foreach ( $dataset as $index => $details ) {

		// Grab a few variables.
		$pslug = get_post_field( 'post_name', absint( $details['product_id'] ) );
		$pname = get_the_title( absint( $details['product_id'] ) );
		$sdate = Helpers\build_date_display( $details['signup_date'], 'formatted' );

		// Set some additional items.
		$li_cls = 'woo-interest-in-products-list-item woo-interest-in-products-list-item-' . sanitize_html_class( $pslug );
		$inp_id = 'woo-interest-in-products-' . sanitize_html_class( $pslug );

		// And the output.
		$build .= '<li class="' . esc_attr( $li_cls ) . '">';

			// The big label wrapper.
			$build .= '<label class="woocommerce-form__label woocommerce-form-' . esc_attr( $pslug ) . '__label woocommerce-form__label-for-checkbox woo-interest-in-products-checkbox-label checkbox">';

				// Our actual checkbox.
				$build .= '<input class="woocommerce-form__input woo-interest-in-products-checkbox-input woocommerce-form-' . esc_attr( $pslug ) . '__input-checkbox woocommerce-form__input-checkbox input-checkbox" name="wc_product_interest_ids[]" id="' . esc_attr( $inp_id ) . '" type="checkbox" value="' . absint( $details['relationship_id'] ) . '" checked="checked" >';

				// Output the product name.
				$build .= '<span>' . esc_html( $pname ) . '</span>';

			// Close the big-ass label wrap.
			$build .= '</label>';

			// Include the signup date.
			$build     .= '<span class="woo-interest-in-products-signup-date">';
				$build .= '(' . sprintf( __( 'Signup Date: %s', 'woo-interest-in-products' ), esc_html( $sdate ) ) . ')';
			$build     .= '</span>';

		// And close the individual list item.
		$build .= '</li>';
	}

	// Close the list.
	$build .= '</ul>';

	// And echo it out if requested.
	if ( $echo ) {
		echo $build; // WPCS: XSS ok.
	}

	// Just return it.
	return $build;
}

/**
 * Set the markup for the screen reader text on dismiss.
 *
 * @return HTML
 */
function screen_reader_text() {
	return '<span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'lw-woo-gdpr-user-optins' ) . '</span>';
}
