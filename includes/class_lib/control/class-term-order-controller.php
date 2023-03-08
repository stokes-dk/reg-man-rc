<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Model\Ajax_Form_Response;

/**
 * The administrative controller for ordering custom taxonomy terms
 *
 * @since	v0.1.0
 *
 */
class Term_Order_Controller {

	const ASSIGN_ORDER_FORM_ACTION		= 'reg-man-rc-order-terms';

	const ORDER_INDEX_META_KEY			= 'reg-man-rc-term-order-index'; // Used in term metadata table to store order indexes

	const ORDER_INDEX_ORDER_BY			= 'order_index'; // used as orderby marker in GET requests for the terms

	// Each supported taxonomy may or may not use a custom ordering (as chosen by the user)
	// The state of each taxonomy is stored in the options table using a key that starts with the taxonomy name
	//  and ends with the suffix below , e.g. reg-man-rc-event-category-custom-ordering = 1
	const CUSTOM_ORDER_TAX_OPTION_KEY_SUFFIX	= '-custom-ordering'; // The key suffix used to read the options table

	private static $META_TABLE_ALIAS	= 'reg_man_rc_order_index_meta'; // used as alias for term meta table in JOIN

	const SUPPORTED_TAXONOMIES = array(
		Event_Category::TAXONOMY_NAME,
		Item_Type::TAXONOMY_NAME,
		Fixer_Station::TAXONOMY_NAME,
		Volunteer_Role::TAXONOMY_NAME,
	);

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
			if ( current_user_can( 'manage_categories' ) ) {
				// Register the AJAX call to allow administrators to re-order terms
				add_action( 'wp_ajax_' . self::ASSIGN_ORDER_FORM_ACTION, array( __CLASS__, 'handle_update_term_order' ) );
			} // endif
		} // endif

		// Modify the clauses for queries on terms to order them the way we want
		add_filter( 'pre_get_terms', array( __CLASS__, 'filter_pre_get_terms_query' ), 10, 1 );

		// Modify the clauses for queries on terms to order them the way we want
		add_filter( 'terms_clauses', array( __CLASS__, 'filter_terms_clauses' ), 10, 3 );

	} // function

	/**
	 * Get a boolean indicating whether the specified taxonomy is one that allows term ordering.
	 * @param	string		$taxonomy	A taxonomy name
	 * @return	boolean		TRUE if the specified taxonomy name is one that allows for custom ordering of terms, FALSE otherwise.
	 */
	public static function get_is_supported_taxonomy( $taxonomy ) {
		$result = in_array( $taxonomy, self::SUPPORTED_TAXONOMIES );
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether the specified taxonomy is using a custom ordering, or uses the default ordering (by term name).
	 * @param	string		$taxonomy	A taxonomy name
	 * @return	boolean		TRUE if the specified taxonomy name is assigned to use a custom ordering,
	 * 	FALSE if the taxonomy displays its terms in the default order (alphabetically by name).
	 */
	public static function get_is_taxonomy_using_custom_order( $taxonomy ) {
		$key = $taxonomy . self::CUSTOM_ORDER_TAX_OPTION_KEY_SUFFIX;
		$val = get_option( $key );
		$result = ( ! empty( $val ) ) ? TRUE : FALSE;
		return $result;
	} // function

	/**
	 * Set the flag to indicate whether the specified taxonomy should use a custom ordering, or uses the default ordering (by term name).
	 * @param	string		$taxonomy				A taxonomy name
	 * @param	boolean		$is_using_custom_order	TRUE if the specified taxonomy will use a custom ordering,
	 * 	FALSE if the taxonomy displays its terms in the default order (alphabetically by name).
	 * @return	void
	 */
	public static function set_is_taxonomy_using_custom_order( $taxonomy, $is_using_custom_order ) {
		$key = $taxonomy . self::CUSTOM_ORDER_TAX_OPTION_KEY_SUFFIX;
		if ( $is_using_custom_order ) {
			update_option( $key, 1 );
		} else {
			delete_option( $key );
		} // endif
	} // function

	/**
	 * Filter the terms query to use our custom ordering when the query is for a supported taxonomy, and that taxonomy
	 * 	is currently using a custom ordering.
	 * @param	\WP_Term_Query	$query	The query object representing the terms query.
	 * @return	\WP_Term_Query	The same query, modified as necessary
	 */
	public static function filter_pre_get_terms_query( $query ) {
		$taxonomy = $query->query_vars[ 'taxonomy' ] ? $query->query_vars[ 'taxonomy' ] : '';
		// query_vars may contain an array of taxonomies.  In that case I only want the first one
		if ( is_array( $taxonomy ) ) {
			$taxonomy = isset( $taxonomy[ 0 ] ) ? $taxonomy[ 0 ] : '';
		} // endif
//		Error_Log::var_dump( $query );
		if ( self::get_is_supported_taxonomy( $taxonomy ) && self::get_is_taxonomy_using_custom_order( $taxonomy ) ) {
			// At this point orderby already has a default of 'name' if no ordering was requested by the user
			// The only way to tell if the user has asked to order by name is to check the $_GET arguments
//			Error_Log::log_msg( 'Examining orderby' );
			$orderby_get_arg = isset( $_GET[ 'orderby' ] ) ? $_GET[ 'orderby' ] : '';
			if ( empty( $orderby_get_arg ) ) {
				// The user has not specified an orderby argument, so I will insert my own by default
//				Error_Log::log_msg( 'Modifying orderby' );
				$query->query_vars[ 'orderby' ] = self::ORDER_INDEX_ORDER_BY;
//				Error_Log::var_dump( $query );
			} // endif
		} // endif
		return $query;
	} // function

	/**
	 * Filter the clauses for a query to get taxonomy terms for our custom taxonomy so that it is possible to order
	 * 	terms using our custom ordering.
	 * When the taxonomy being queried is one of the custom taxonomies created by this plugin, we will change
	 * the JOIN, ORDERBY and ORDER pieces to apply our custom term ordering to the result
	 *
	 * @param	string[]	$pieces			The pieces of the query, e.g. fields, join, where etc.
	 * @param	string[]	$taxonomies		An array of taxonomy names being queried
	 * @param	string[]	$args			An array of arguments for the query
	 * @return	string[]	The pieces array, modified if necessary for our re-ordering.
	 */
	public static function filter_terms_clauses( $pieces, $taxonomies, $args ) {
		global $wpdb;
//		$order_by = isset( $pieces[ 'orderby' ] ) ? $pieces[ 'orderby' ] : '';
		$order_by = isset( $args[ 'orderby' ] ) ? $args[ 'orderby' ] : '';
//		Error_Log::var_dump( $order_by, $taxonomies );
		// $taxonomies is an array but I only want the first element
		$taxonomy = isset( $taxonomies[ 0 ] ) ? $taxonomies[ 0 ] : '';
//		Error_Log::var_dump( $order_by, $taxonomy, $args );
		if ( $order_by == self::ORDER_INDEX_ORDER_BY ) {
//			Error_Log::var_dump( $pieces, $taxonomies, $args );
			if ( self::get_is_supported_taxonomy( $taxonomy ) ) {
				// When the taxonomy is one of ours we'll JOIN the term meta table to get our order index value for each term
				// Then we'll order by that meta value and fall back on ordering by term name when order indexes are equal
//				Error_Log::log_msg( 'Modifying pieces' );
				$meta_alias = self::$META_TABLE_ALIAS;
				$meta_key = self::ORDER_INDEX_META_KEY;
				$order = isset( $_GET[ 'order' ] ) ? $_GET[ 'order' ] : 'ASC'; // Order ascending by default
				$join = " LEFT JOIN {$wpdb->termmeta} AS $meta_alias ON ( t.term_id = {$meta_alias}.term_id AND {$meta_alias}.meta_key = '$meta_key' ) ";
				$pieces[ 'join' ]		.= $join;
				$pieces[ 'orderby' ]	= " ORDER BY {$meta_alias}.meta_value + 0 $order, t.name";
				$pieces[ 'order' ]		= $order;
//				Error_Log::var_dump( $pieces[ 'orderby'] );
			} // endif
		} // endif
		return $pieces;
	} // function

	/**
	 * Handle an AJAX term re-ordering form post.
	 *
	 * This method is called when the ajax form is submitted to update the order of terms on one of our custom taxonomies,
	 *  e.g. Event_Category.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_update_term_order() {
		$form_response = Ajax_Form_Response::create();

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : '';
		$is_valid_nonce = wp_verify_nonce( $nonce, self::ASSIGN_ORDER_FORM_ACTION );
		if ( ! $is_valid_nonce ) {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} else {
			$order_option = isset( $form_data[ 'order_option' ] ) ? $form_data[ 'order_option' ] : NULL;
			$taxonomy = isset( $form_data[ 'taxonomy' ] ) ? $form_data[ 'taxonomy' ] : NULL;
			if ( ! self::get_is_supported_taxonomy( $taxonomy ) ) {
				$msg = __( 'The taxonomy is not supported', 'reg-man-rc' ); // This should never happen
				$form_response->add_error( 'taxonomy', $taxonomy, $msg );
			} else {
				switch( $order_option ) {
					case 'default_order':
						$term_id_array = isset( $form_data[ 'term_id' ] ) ? $form_data[ 'term_id' ] : array();
						foreach( $term_id_array as $term_id ) {
							self::set_term_order_index( $term_id, NULL );
						} // endfor
						self::set_is_taxonomy_using_custom_order( $taxonomy, FALSE );
						break;
					case 'custom_order':
						$term_id_array = isset( $form_data[ 'term_id' ] ) ? $form_data[ 'term_id' ] : array();
						$curr_order_index = 1;
						foreach( $term_id_array as $term_id ) {
							self::set_term_order_index( $term_id, $curr_order_index );
							$curr_order_index++;
						} // endfor
						self::set_is_taxonomy_using_custom_order( $taxonomy, TRUE );
						break;
					default:
						// Do nothing, there's some kind of problem
						break;
				} // endswitch
			} // endif
		} // endif

		$form_response->set_redirect_url( $_SERVER[ 'HTTP_REFERER' ] ); // Reload the page
		$result = json_encode( $form_response->jsonSerialize() );
		echo $result;

		wp_die(); // THIS IS REQUIRED!

	} // function

	/**
	 * Get the order index for the specified taxonomy term
	 *
	 * @param	int		$term_id	The ID for the term whose index is to be returned
	 * @return	int		The order index for the specified term
	 *
	 * @since v0.1.0
	 */
	public static function get_term_order_index( $term_id ) {
		$meta_val = get_term_meta( $term_id, self::ORDER_INDEX_META_KEY, $single = TRUE );
		if ( ( $meta_val !== FALSE ) && ( $meta_val !== NULL ) ) {
			$result = intval( $meta_val );
		} else {
			$result = 0; // Default to 0
		} // endif
		return $result;
	} // function

	/**
	 * Set the order index for the specified taxonomy term
	 *
	 * @param	int		$term_id		The ID for the term whose index is to be assigned
	 * @param	int		$order_index	The new order index for the term
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function set_term_order_index( $term_id, $order_index ) {
		if ( ( $order_index === '' ) || ( $order_index === NULL ) || ( $order_index === FALSE ) ) {
			// Note that zero is an acceptable value but would also equate to TRUE for empty()
			delete_term_meta( $term_id, self::ORDER_INDEX_META_KEY );
		} else {
			// We need to make sure there is only one value so if none exists add it, otherwise update it
			$curr_val = get_term_meta( $term_id, self::ORDER_INDEX_META_KEY, $single = TRUE);
			if ( ( $curr_val === '' ) || ( $curr_val === NULL ) || ( $curr_val === FALSE ) ) {
				add_term_meta( $term_id, self::ORDER_INDEX_META_KEY, $order_index );
			} else {
				update_term_meta( $term_id, self::ORDER_INDEX_META_KEY, $order_index, $curr_val );
			} // endif
		} // endif
	} // function

} // class
