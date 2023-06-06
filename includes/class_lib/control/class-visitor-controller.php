<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Visitor;

/**
 * The visitor controller
 *
 * This class provides the controller function associated with the Visitor custom post type.
 * It handles AJAX POST operations for things like visitor login.
 * When getting a Visitor from the database we must join our side table that contains the
 *  visitor's personal data including full name and email address.
 *  This data is sensitive and is not stored in the main WP posts table.
 *
 * @since v0.1.0
 *
 */
class Visitor_Controller {

	/**
	 *  Register the Visitor controller.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// Filter the clauses for visitors so that we can join our side table
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 1000, 2 );

	} // function

	/**
	 * Filter the clauses of the WP_Query when we're dealing with visitors.
	 *
	 * The parts of the query we will modify are these:
	 *   join 	To join our side table
	 *   fields	To add the fields from our side table
	 *
	 * @param	string[]	$clauses
	 * @param	\WP_Query	$query
	 * @return string
	 */
	public static function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;
		$post_type = Visitor::POST_TYPE;

//	Error_Log::log_msg( 'Filter posts clauses called' );
//	Error_Log::var_dump( $clauses, $query );

		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;

		// Update the clauses if necessary
		if ( is_admin() && current_user_can( $capability ) &&
			$query->is_main_query() && ( $query->get( 'post_type' ) == $post_type ) ) {
/*
			$orderby_key = self::get_orderby();
			if ( $orderby_key == 'is_public' ) {
				$order = $query->get( 'order' );
				$order_clause = " ( CASE WHEN {$wpdb->posts}.post_status='publish' THEN 0 ELSE 1 END ) ";
				$clauses[ 'orderby' ] = " $order_clause $order";
			} // endif
*/
//	Error_Log::log_msg( 'Joining visitor side table' );

			$visitor_table = $wpdb->prefix . Visitor::VISITOR_SIDE_TABLE_NAME;
			$alias = 'reg_man_rc_visitor';

			$join = "LEFT JOIN $visitor_table AS $alias";
			$join .= " ON ( {$wpdb->posts}.ID = {$alias}.post_id )";
			$clauses[ 'join' ] .= ' ' . $join;

			$fields = 	'1 as visitor_table_joined, ' .
						"$alias.full_name as visitor_full_name, " .
						"$alias.email as visitor_email";
			$clauses[ 'fields' ] .= ', ' . $fields;

			if ( is_search() ) {
				$query_vars = $query->query_vars;
				$search_string = isset( $query_vars[ 's' ] ) ? $query_vars[ 's' ] : NULL;
				if ( ( $search_string !== NULL ) && ( $search_string != '' ) ) {
					$search_terms = self::get_search_terms_from_string( $search_string );
					$parts_array = array(); // create an array of parts so we can search all terms and all places
					foreach ( $search_terms as $term ) {
						$esc_term = $wpdb->esc_like( $term );
//						$esc_term = addcslashes( $term, '_%' );
						$parts_array[] = "{$wpdb->posts}.post_title LIKE '%$esc_term%'";
						$parts_array[] = "{$wpdb->posts}.post_excerpt LIKE '%$esc_term%'";
						$parts_array[] = "{$wpdb->posts}.post_content LIKE '%$esc_term%'";
						$parts_array[] = "{$alias}.full_name LIKE '%$esc_term%'";
						$parts_array[] = "{$alias}.email LIKE '%$esc_term%'";
					} // endfor
					// Create the search clause for the search terms
					$new_wheres[] = implode( ' OR ', $parts_array );
					$search_where = implode( ' AND ', $new_wheres ); // Combine the where clauses with AND

					// Always search only inside our post type
					$post_where = " {$wpdb->posts}.post_type='$post_type' ";

					// Make sure that status is something valid
					$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
					$status_parts = array();
					foreach ( $statuses as $status ) {
						$status_parts[] = " {$wpdb->posts}.post_status='$status' ";
					} // endfor
					$status_where = ' ( ' . implode( 'OR', $status_parts ) . ' ) ';

					// Assign the complete where clause
					$clauses[ 'where' ] = " AND $post_where AND $status_where AND ( $search_where )";
				} // endif
			} // endif

		} // endif

//		Error_Log::var_dump( $clauses );

		return $clauses;
	} // function

	private static function get_orderby() {
		$result = isset( $_REQUEST[ 'orderby' ] ) ? $_REQUEST[ 'orderby' ] : 'is_public';
		return $result;
	} // function


	private static function get_search_terms_from_string( $search_string, $remove_stopwords = TRUE ) {
		$result = preg_split( '/\s+/', $search_string, -1, PREG_SPLIT_NO_EMPTY ); // split search string into terms separated by whitespace
		return $result;
	} // function


} // class