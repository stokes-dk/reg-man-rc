<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Term_Order_Controller;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The view to support custom term ordering
 *
 */

class Term_Order_Admin_View {

	const GET_ORDER_TERMS_FORM_CONTENT_AJAX_ACTION	= 'reg-man-rc-get-order-terms-form-content';

	const ORDER_INDEX_COLUMN_NAME	= 'order_index'; // used as the marker for the term list column containing our index
	// Note that the css has rules to match the name above

	/**
	 * Register the controller action and filter hooks.
	 *
	 * This method is called by the plugin controller to register this controller.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {

		if ( is_admin() ) {

			// Regsiter to enqueue the necessary scripts and styles as needed
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

/* There isn't really any need to show the Order column, is there?
			foreach ( Term_Order_Controller::SUPPORTED_TAXONOMIES as $taxonomy ) {

				// Modify columns in the admin UI term list - but do after the taxonomies themselves have done it
				add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ), 100, 1 );

				// Put values into my columns in the term list
				add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'get_admin_UI_column_values' ), 100, 3 );

				// Register my columns as sortable
				add_filter( 'manage_edit-' . $taxonomy . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

			} // endfor
*/
			if ( current_user_can( 'manage_categories' ) ) {

				// Register the AJAX call to allow administrators to access the term ordering form
				add_action( 'wp_ajax_' . self::GET_ORDER_TERMS_FORM_CONTENT_AJAX_ACTION, array( __CLASS__, 'handle_get_order_terms_form' ) );

				foreach ( Term_Order_Controller::SUPPORTED_TAXONOMIES as $taxonomy ) {
					// Add something to allow re-ordering
					add_action( 'after-' . $taxonomy . '-table', array( __CLASS__, 'render_after_table' ), 10, 1 );
				} // endfor

			} // endif

		} // endif

	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) &&
				( $screen->base == 'edit-tags' ) &&
				( Term_Order_Controller::get_is_supported_taxonomy( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_term_order_admin_scripts_and_styles();
		} // endif
	} // function


	public static function filter_admin_UI_columns( $columns ) {
		// This hook is triggered on the term edit page with empty columns for some reason so don't cause problems in that case
		if ( ! empty( $columns ) ) {
			$columns[ self::ORDER_INDEX_COLUMN_NAME ]	= __( 'Order', 'reg-man-rc' ); // Add our column
		} // endif
		return $columns;
	} // function

	public static function get_admin_UI_column_values( $content, $column_name, $term_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		switch ( $column_name ) {
			case self::ORDER_INDEX_COLUMN_NAME:
				$order_index = Term_Order_Controller::get_term_order_index( $term_id );
				$content = ( ! empty( $order_index ) ) ? $order_index : $em_dash;
				break;
		} // endswitch
		return $content;
	} // function

	/**
	 * Add my columns to the list of sortable columns.
	 * This is called during the manage_edit-TAXONOMY_sortable_columns filter hook.
	 * @param	string[]	$columns	The array of columns to be made sortable
	 * @return	string[]	$columns	The array of columns to be made sortable
	 */
	public static function add_sortable_columns( $columns ) {
		$columns[ self::ORDER_INDEX_COLUMN_NAME ] = Term_Order_Controller::ORDER_INDEX_ORDER_BY;
		return $columns;
	} // function

	/**
	 * Render the content required for term ordering after the taxonomy terms table has been rendered
	 * @param	string	$taxonomy	The name of the taxonomy
	 */
	public static function render_after_table( $taxonomy ) {
		// Wordpress does not have a filter for modifying the top of the terms list table, just the bottom
		// So I will put this element at the bottom of the table and hide it, then move it using javascript on the client side
		$label = __( 'Change Order…', 'reg-man-rc' );
		$value = esc_attr( $taxonomy );
		echo '<button id="reg-man-rc-term-order-action" type="button" class="button value="' . $value . '" style="display:none">';
			echo esc_html( $label );
		echo '</button>';
		self::render_term_order_dialog( $taxonomy );
	} // function

	private static function render_term_order_dialog( $tax_name ) {
		// The terms need to be loaded into a re-ordering form using AJAX because the user may delete a term before the page is refreshed
		$taxonomy = get_taxonomy( $tax_name );
		$terms_name = isset( $taxonomy->label ) ? $taxonomy->label : __( 'terms', 'reg-man-rc' );
		/* Translators: %s is a label for a custom taxonomy like "Event Categories" */
		$title = sprintf( __( 'Change Order of %s', 'reg-man-rc' ), $terms_name );
		echo '<div class="term-order-dialog" title="' . esc_attr( $title ) . '">';
			echo '<div class="term-order-dialog-contents-getter-form-container">';
				$action = self::GET_ORDER_TERMS_FORM_CONTENT_AJAX_ACTION;
				$method = 'GET';
				$classes = 'reg-man-rc-get-terms-order-form-content-form';
				$content = '<input type="hidden" name="taxonomy" value="' . $tax_name . '">';
				$ajax_form = Ajax_Form::create( $action, $method, $classes, $content );
				$ajax_form->set_is_replace_content_with_response_html( FALSE ); // I will handle my own HTML response
				$ajax_form->render();
			echo '</div>';

			// Create an ajax form and add the inputs as its contents
			$method = 'POST';
			$classes = 'reg-man-rc-assign-term-order-form';
			$ajax_form = Ajax_Form::create( Term_Order_Controller::ASSIGN_ORDER_FORM_ACTION, $method, $classes );
			$ajax_form->add_form_content( '<div class="term-order-dialog-contents"></div>' );
			$ajax_form->render();

		echo '</div>';
	} // function

	public static function handle_get_order_terms_form() {

		$form_response = Ajax_Form_Response::create();

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

//		Error_Log::var_dump( $form_data );
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : '';
		$is_valid_nonce = wp_verify_nonce( $nonce, self::GET_ORDER_TERMS_FORM_CONTENT_AJAX_ACTION );
		if ( ! $is_valid_nonce ) {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} else {
			$tax_name = isset( $form_data[ 'taxonomy' ] ) ? $form_data[ 'taxonomy' ] : '';
			$taxonomy = get_taxonomy( $tax_name );
			if ( empty( $taxonomy ) ) {
				$err_msg = __( 'Cannot find the specified taxonomy', 'reg-man-rc' ); // should never happen
				$form_response->add_error( 'taxonomy', $tax_name, $err_msg );
			} else {
				$input = '<input type="text">';
				$args = array(
						'taxonomy'		=> $tax_name,
						'hide_empty'	=> FALSE
				);
				$terms = get_terms( $args );
				if ( is_wp_error( $terms ) ) {
					$err_msg = __( 'Cannot get the terms for the specified taxonomy', 'reg-man-rc' ); // should never happen
					$form_response->add_error( 'taxonomy', $tax_name, $err_msg );
				} else {
					$content = self::get_term_order_form_contents( $tax_name, $terms );
					$form_response->set_html_data( $content );
				} // endif
			} // endif
		} // endif

		$result = json_encode( $form_response->jsonSerialize() );
		echo $result;

		wp_die(); // THIS IS REQUIRED!

	} // function

	private static function get_term_order_form_contents( $taxonomy_name, $terms ) {
		$input_list = Form_Input_List::create();

		// Taxonomy name
		$input_list->add_hidden_input( 'taxonomy', $taxonomy_name );

		$radio_format = '<label><input type="radio" name="order_option" value="%2$s" %3$s><span>%1$s</span></label>';

		$is_custom_order = Term_Order_Controller::get_is_taxonomy_using_custom_order( $taxonomy_name );

		// Default order radio button
		$label = __( 'Use the default ordering (alphabetically by name)', 'reg-man-rc' );
		$value = 'default_order';
		$checked = ( ! $is_custom_order ) ? 'checked="checked"' : '';
		$radio_html = sprintf( $radio_format, $label, $value, $checked );
		$input_list->add_custom_html_input( '', '', $radio_html, '', 'reg-man-rc-default-order-option' );

		// Custom order radio button and terms list
		$label = __( 'Use the following custom order', 'reg-man-rc' );
		$value = 'custom_order';
		$checked = ( $is_custom_order ) ? 'checked="checked"' : '';
		$radio_html = sprintf( $radio_format, $label, $value, $checked );
		ob_start();
			echo '<ul class="reg-man-rc-term-order-item-list">';
				$item_format = '<li><input type="hidden" name="term_id[]" value="%1$s">%2$s</li>';
				foreach( $terms as $term ) {
					$id = $term->term_id;
					$name = $term->name;
					printf( $item_format, $id, $name );
				} // endfor
			echo '</ul>';
		$term_list = ob_get_clean();
		$field_set = Form_Input_List::create();
		$field_set->add_custom_html_input( '', '', $term_list );
		$info = __( 'Drag items to change order', 'reg-man-rc' );
		$input_list->add_fieldset( $radio_html, $field_set, $info );

		// Get the form inputs as a string
		ob_start();
			$input_list->render();
		$result = ob_get_clean();

		return $result;
	} // function

} // class