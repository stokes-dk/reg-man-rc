<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\Term_Order_Controller;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteers
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 2 );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

		// Filter the clauses for my custom post type to allow custom filtering on metadata
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 1000, 2 );

	} // function

	/**
	 * Handle a post save event for my post type
	 *
	 * @param	int			$post_id	The ID of the post being saved
	 * @param	\WP_Post	$post		The post being saved
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_post_save( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Don't do anything during an autosave
			return;
		} else {
			$registration = Volunteer_Registration::get_registration_by_id( $post_id );
			if ( ! empty( $registration ) ) {

				// Update the event
				if ( isset( $_POST[ 'volunteer_reg_event_input_flag' ] ) ) {
					$event_key = isset( $_POST[ 'volunteer_event' ] ) ? $_POST[ 'volunteer_event' ] : 0;
					$event_key = wp_unslash( $event_key );
					$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
					$registration->set_event( $event );
				}// endif

				// Update the volunteer
				if ( isset( $_POST[ 'volunteer_reg_volunteer_input_flag' ] ) ) {
					$id = isset( $_POST[ 'volunteer_registration_volunteer' ] ) ? $_POST[ 'volunteer_registration_volunteer' ] : 0;
					if ( $id == '-1' ) {
						// This means that we are adding a new volunteer
						$public_name  = isset( $_POST[ 'volunteer_public_name' ] ) ? $_POST[ 'volunteer_public_name' ] : '';
						$full_name  = isset( $_POST[ 'volunteer_full_name' ] ) ? $_POST[ 'volunteer_full_name' ] : NULL;
						$email  = isset( $_POST[ 'volunteer_email' ] ) ? $_POST[ 'volunteer_email' ] : NULL;
						$volunteer = Volunteer::create_new_volunteer( $public_name, $full_name, $email );
					} else {
						$volunteer = ! empty( $id ) ? Volunteer::get_volunteer_by_id( $id ) : NULL;
					} // endif
					if ( isset( $volunteer ) && isset( $post ) ) {
						// Save the volunteer's public name as the post title to make the data a little more readable
						// Unhook this function so it doesn't loop infinitely
						remove_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );
						// Set the post status to draft since it doesn't have a valid item type
						$post_data = array(
							'ID'			=> $post_id,
							'post_title'	=> $volunteer->get_public_name(),
						);
						wp_update_post( $post_data );
					} // endif
					$registration->set_volunteer( $volunteer );
				} // endif

				// Update the fixer station
				if ( isset( $_POST[ 'fixer_station_selection_flag' ] ) ) {
					$fixer_station_id = isset( $_POST[ 'fixer_station' ] ) ? $_POST[ 'fixer_station' ] : 0;
					$fixer_station = ! empty( $fixer_station_id ) ? Fixer_Station::get_fixer_station_by_id( $fixer_station_id ) : NULL;
					$registration->set_fixer_station( $fixer_station );
					$is_apprentice = ( isset( $_POST[ 'is_apprentice' ] ) && isset( $fixer_station ) ) ? TRUE : FALSE;
					$registration->set_is_fixer_apprentice( $is_apprentice );
				} // endif

				// Update the roles
				if ( isset( $_POST[ 'volunteer_role_selection_flag' ] ) ) {
					$role_ids = isset( $_POST[ 'volunteer_role' ] ) ? $_POST[ 'volunteer_role' ] : array();
					$new_roles = array();
					foreach ( $role_ids as $role_id ) {
						$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
						if ( isset( $role ) ) {
							$new_roles[] = $role;
						} // endif
					} // endfor
					$registration->set_volunteer_roles_array( $new_roles );
				} // endif

			} // endif
		} // endif
	} // function

	/**
	 * Modify the query to show my custom posts based on filter settings.
	 * This is called during the pre_get_posts action hook.
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) && ( $post_type == Volunteer_Registration::POST_TYPE ) ) {

//			Error_Log::var_dump( $query );
			// Filter by metadata like event as necessary
			$meta_query_array = array();

			// Event
			$event_filter_name = Volunteer_Registration::EVENT_META_KEY;
			$selected_event_key = isset( $_REQUEST[ $event_filter_name ] ) ? wp_unslash( $_REQUEST[ $event_filter_name ] ) : 0;
			if ( ! empty( $selected_event_key ) ) {
				$meta_query_array[] = array(
						'key'		=> Volunteer_Registration::EVENT_META_KEY,
						'value'		=> $selected_event_key
				);
			} // endif

			// Volunteer
			$volunteer_filter_name = Volunteer_Registration::VOLUNTEER_META_KEY;
			$selected_volunteer_key = isset( $_REQUEST[ $volunteer_filter_name ] ) ? wp_unslash( $_REQUEST[ $volunteer_filter_name ] ) : 0;
			if ( ! empty( $selected_volunteer_key ) ) {
				$meta_query_array[] = array(
						'key'		=> Volunteer_Registration::VOLUNTEER_META_KEY,
						'value'		=> $selected_volunteer_key
				);
			} // endif

			if ( ! empty( $meta_query_array ) ) {
				$query->set( 'meta_query', $meta_query_array );
			} // endif

			// Add the taxonomy queries for filters
			$tax_query_array = array();
			$tax_name_array = array( Fixer_Station::TAXONOMY_NAME, Volunteer_Role::TAXONOMY_NAME );
			foreach ( $tax_name_array as $tax_name ) {
				$selected = isset( $_REQUEST[ $tax_name ] ) ? $_REQUEST[ $tax_name ] : 0;
				if ( ! empty( $selected ) ) {
					$tax_query_array[] =
							array(
									'taxonomy'	=> $tax_name,
									'field'		=> 'slug',
									'terms'		=> array( $selected )
							);
				} // endif
			} // endfor

			if ( ! empty( $tax_query_array ) ) {
				$query->query_vars[ 'tax_query' ] = $tax_query_array;
			} // endif

		} // endif

		return $query;

	} // function


	/**
	 * Filter the clauses of the WP_Query when we're dealing with my post type to allow sorting by taxonomies.
	 * This could be when showing a list of events on an admin page or on the front end.
	 *
	 * The parts of the query we will modify are these:
	 *   join		We may need to join the metadata table to order or filter events by start date or venue
	 *   orderby 	Modified when the user orders by start date
	 *
	 * @param	string[]	$clauses
	 * @param	\WP_Query	$query
	 * @return string		The $clauses argument modified as needed
	 */
	public static function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;

		$post_type = $query->get( 'post_type' );

		if ( $query->is_main_query() && ! is_single() && ( $post_type == Volunteer_Registration::POST_TYPE ) ) {

//			Log::var_log( 'Join', $clauses[ 'join' ] );
			$join_clause = self::modify_join_clause( $clauses[ 'join' ], $query );
			$clauses[ 'join' ] = $join_clause;

//			Log::var_log( 'Where clause', $clauses[ 'where' ] );
//			$where_clause = self::modify_where_clause( $clauses[ 'where' ], $query );
//			$clauses[ 'where' ] = $where_clause;

//			Log::var_log( 'Orderby', $clauses[ 'orderby' ] );
			$orderby_clause = self::modify_orderby_clause( $clauses[ 'orderby' ], $query );
			$clauses[ 'orderby' ] = $orderby_clause;

		} // endif

//		Error_Log::var_dump( $clauses );
		return $clauses;

	} // function

	private static function get_orderby() {
		$result = isset( $_REQUEST[ 'orderby' ] ) ? $_REQUEST[ 'orderby' ] : NULL;
		return $result;
	} // function

	private static function get_order() {
		$result = isset( $_REQUEST[ 'order' ] ) ? $_REQUEST[ 'order' ] : 'ASC';
		return $result;
	} // function

	private static function modify_join_clause( $join_clause, $query ) {
		global $wpdb;

		// I will need to join the taxonomy table if the ordering by a taxonomy like fixer station
		$orderby_key = self::get_orderby();

		$tax_order_names_array = array(
				Fixer_Station::TAXONOMY_NAME	=> 'taxonomy-' . Fixer_Station::TAXONOMY_NAME,
//				Volunteer_Role::TAXONOMY_NAME	=> 'taxonomy-' . Volunteer_Role::TAXONOMY_NAME,
		);

		$new_joins = array(); // create an array of joins and then implode them into a string later

		$tax_name = array_search( $orderby_key, $tax_order_names_array ); // returns the taxonomy name or FALSE
//		Error_Log::var_dump( $orderby_key, $tax_name );
		if ( $tax_name !== FALSE ) {
			// Join the taxonomy
			$meta_key = Term_Order_Controller::ORDER_INDEX_META_KEY;
			$tax_join_format =
				'LEFT JOIN (' .
					' SELECT object_id, meta_value AS reg_man_rc_term_order_index' .
						" FROM {$wpdb->term_relationships} " .
						" INNER JOIN {$wpdb->term_taxonomy} USING ( term_taxonomy_id ) " .
						" INNER JOIN {$wpdb->terms} USING ( term_id ) " .
						" INNER JOIN {$wpdb->termmeta} USING ( term_id ) " .
					' WHERE taxonomy = \'%1$s\'' .
               			" AND ( wp_termmeta.meta_key = '$meta_key' OR wp_termmeta.meta_key IS NULL )" .
					' GROUP BY object_id' .
				') AS reg_man_rc_tax_sub_query_terms ON ( wp_posts.ID = reg_man_rc_tax_sub_query_terms.object_id )';

			$tax_join = sprintf( $tax_join_format, $tax_name );
			$new_joins[] = $tax_join;

			if ( $tax_name === Fixer_Station::TAXONOMY_NAME ) {
				// Also join the metadata for apprentice so we can order them last
				$apprentice_key = Volunteer_Registration::IS_APPRENTICE_META_KEY;
				$meta_join =
					"LEFT JOIN {$wpdb->postmeta} AS is_apprentice_meta" .
					" ON ( {$wpdb->posts}.ID = is_apprentice_meta.post_id AND is_apprentice_meta.meta_key = '$apprentice_key' )";
				$new_joins[] = $meta_join;
			} // endif

		} // endif

		if ( ! empty( $new_joins ) ) {
			$new_joins[] = $join_clause; // tack on the original join
			$result = implode( ' ', $new_joins );
		} else {
			$result = $join_clause; // Just return the original
		} // endif

//		Error_Log::var_dump( $result );
		return $result;

	} // function

	private static function modify_orderby_clause( $orderby_clause, $query ) {
		global $wpdb;

		$orderby_key = self::get_orderby();
		$order = $query->get( 'order' );
//		Error_Log::var_dump( $orderby_key , $order );

		switch ( $orderby_key ) {

			case 'taxonomy-' . Fixer_Station::TAXONOMY_NAME:
				// Always put posts with missing meta value at the bottom of the list
				$result =
					'reg_man_rc_term_order_index IS NULL ASC, ' .
					" reg_man_rc_term_order_index $order, " .
					' is_apprentice_meta.meta_value ASC, ' . // Put apprentices last
					" {$wpdb->posts}.post_title ASC"; // Order by name
				break;

			default:
				$result = $orderby_clause;
				break;

		} // endswitch

		return $result;
	} // function



} // class