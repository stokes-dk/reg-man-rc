<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Geographic_Position;

/**
 * The controller used on both the backend admin interface and front end for internal event descriptors
 *
 * This class provides the controller function for working with internal event descriptors
 *
 * @since v0.1.0
 *
 */
class Internal_Event_Descriptor_Controller {

	public static function register() {

		if ( is_admin() ) {

			// Add an action hook to upate our custom fields when a post is saved
			add_action( 'save_post_' . Internal_Event_Descriptor::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

			// Check to make sure that an item type is selected and show an error message if not
			add_action( 'edit_form_top', array( __CLASS__, 'show_edit_form_messages' ) );

			// Add a filter to change the query for the months UI filter, we want to show event date months rather than post edit months
			add_filter( 'pre_months_dropdown_query', array( __CLASS__, 'modify_months_dropdown_query' ), 10, 2 );

		} // endif

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

		// Filter the clauses for my custom post type to allow custom filtering on metadata
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 1000, 2 );

	} // function

	/**
	 * Filters the WHERE clause in the SQL for a next or previous post query.
	 *
	 * @since v0.1.0
	 *
	 * @param string	$where			The JOIN clause in the SQL.
	 * @param \WP_Post	$post			WP_Post object.
	 * @param bool		$is_next		Whether the link is for the next post or the previous one, TRUE for next.
	 */
	private static function modify_where_for_next_previous_links( $where, $post, $is_next ) {
		global $wpdb;
		$result = $where; // we'll return the original value by default
		if ( $post->post_type == Internal_Event_Descriptor::POST_TYPE )  {
			// Note that Wordpress uses an alias of "p" for the posts table
			// Note that we will not provide links to any events that have no start date
			$meta = get_post_meta( $post->ID, Internal_Event_Descriptor::START_META_KEY, $single = TRUE );
			$curr_date = ( $meta !== FALSE ) ? $meta : NULL;
			$direction = $is_next ? '>' : '<';
			$post_type = Internal_Event_Descriptor::POST_TYPE;
			$result = "WHERE p.post_type = '$post_type'";
			$result .= " AND start_date_meta.meta_value IS NOT NULL AND start_date_meta.meta_value $direction '$curr_date'";
		} // endif
		return $result;
	} // function

	/**
	 * Modify the query to show only events with the specified taxonomy terms.
	 * This is called during the pre_get_posts action hook.
	 * Note that query changes to allow for filtering (and sorting) by metadata are handled in filter_posts_clauses()
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';
		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) && ( $post_type == Internal_Event_Descriptor::POST_TYPE ) ) {

			// NOTE that I need to remove the 'm' setting so that WP does not filter by post date
			$date_filter = self::get_date_filter_value();
			if ( ! empty( $date_filter ) ) {
				$query->set( 'm', null );
			} // endif

			// Filter the taxonomies as necessary
			$tax_query_array = array();
			$tax_name_array = array( Event_Category::TAXONOMY_NAME, Fixer_Station::TAXONOMY_NAME );

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
	 * Filter the clauses of the WP_Query when we're dealing with my internal events to allow sorting by start date,
	 * searching by venue and so on.
	 * This could be when showing a list of events on an admin page or on the front end.

	 * Because start date and venue are both stored as metadata, it is more practical to use the posts_clauses hook
	 * rather than pre_get_posts and trying to search and order using two different metadata table joins.
	 * With the posts_clauses hook we can give each metatable join a specific alias and then use that alias in the
	 * WHERE and ORDER BY clauses.
	 *
	 * The parts of the query we will modify are these:
	 *   join		We may need to join the metadata table to order or filter events by start date or venue
	 *   orderby 	Modified when the user orders by start date
	 *   where		To search by venue or filter by date we need to modify the where clause, e.g. WHERE venue LIKE '%library%'
	 *
	 * @param	string[]	$clauses
	 * @param	\WP_Query	$query
	 * @return string		The $clauses argument modified as needed
	 */
	public static function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;

		$post_type = $query->get( 'post_type' );
//		$taxonomy = $query->get( 'taxonomy' );
//		$event_taxonomies = array( Event_Category::TAXONOMY_NAME, Fixer_Station::TAXONOMY_NAME );

		if ( $query->is_main_query() && ! is_single() &&
			( $post_type == Internal_Event_Descriptor::POST_TYPE ) ) {
			// I can't figure out why I would want to test for $taxonomy below and doing so will cause this
			//  to be triggered when I'm sorting anything (like volunteer registrations) by a taxonomy like fixer station
//			( ( $post_type == Internal_Event_Descriptor::POST_TYPE ) || ( in_array( $taxonomy, $event_taxonomies ) ) ) ) {
			// We're in the main query, showing a list of Events or showing one of our Taxonomies for Events

//			Log::var_log( 'Join', $clauses['join'] );
			$join_clause = self::modify_join_clause( $clauses['join'], $query );
			$clauses['join'] = $join_clause;

//			Log::var_log( 'Where clause', $clauses['where'] );
			$where_clause = self::modify_where_clause( $clauses['where'], $query );
			$clauses['where'] = $where_clause;

//			Log::var_log( 'Orderby', $clauses['orderby'] );
			$orderby_clause = self::modify_orderby_clause( $clauses['orderby'], $query );
			$clauses['orderby'] = $orderby_clause;

		} // endif

//		Error_Log::var_dump( $clauses );
		return $clauses;

	} // function

	private static function get_orderby() {
		$result = isset( $_REQUEST[ 'orderby' ] ) ? $_REQUEST[ 'orderby' ] : 'event_date';
		return $result;
	} // function

	private static function get_date_filter_value() {
		$name = 'm';
		$result = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : 0; // 0 here indicates 'show all dates'
		return $result;
	} // function

	private static function get_venue_filter_value() {
		$name = Internal_Event_Descriptor::VENUE_META_KEY;
		$result = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : 0; // 0 here indicates 'show all venues'
		return $result;
	} // function

	private static function modify_join_clause( $join_clause, $query ) {
		global $wpdb;

		// I may need to join the metadata table if the ordering or filtering by date
		$orderby_key = self::get_orderby();
		$date_filter = self::get_date_filter_value();

		// I may also need to join the metadata table if the filtering by venue
		$venue_id = self::get_venue_filter_value();

		$new_joins = array(); // create an array of joins and then implode them into a string later

		if ( ( $orderby_key == 'event_date' ) || ( ! empty( $date_filter ) ) ) {
			$start_key = Internal_Event_Descriptor::START_META_KEY;
			$join = "LEFT JOIN {$wpdb->postmeta} AS start_date_meta";
			$join .= " ON ( {$wpdb->posts}.ID = start_date_meta.post_id AND start_date_meta.meta_key = '$start_key' )";
			$new_joins[] = $join;
		} // endif

		if ( ! empty( $venue_id ) ) {
			$venue_key = Internal_Event_Descriptor::VENUE_META_KEY;
			$join = "LEFT JOIN {$wpdb->postmeta} AS venue_meta";
			$join .= " ON ( {$wpdb->posts}.ID = venue_meta.post_id AND venue_meta.meta_key = '$venue_key' )";
			$new_joins[] = $join;
		} // endif

		if ( ! empty( $join_clause ) ) {
			$new_joins[] = $join_clause; // tack on the original join
		} // endif

		$result = implode( ' ', $new_joins );

		return $result;

	} // function


	private static function modify_where_clause( $where_clause, $query ) {
		global $wpdb;

		$new_wheres = array(); // create an array of where clauses and then implode them into a string later

		$date_filter = self::get_date_filter_value();
		$venue_id = self::get_venue_filter_value();

		if ( ! empty( $date_filter ) && is_string( $date_filter ) && ( strlen( $date_filter ) == 6 ) ) {
			// The date filter values are 4-digit year and 2-digit month like this: "202108" for August 2021.
			$year = substr( $date_filter, 0, 4 );
			$month = substr( $date_filter, 4, 2 );
			$new_wheres[] = "start_date_meta.meta_value LIKE '$year-$month-%'";
			// NOTE that I also need to remove the 'm' setting in our pre_get_posts method so that WP does not filter by post date
		} // endif

		if ( ! empty( $venue_id ) ) {
			$new_wheres[] = "venue_meta.meta_value = '$venue_id'";
		} // endif

		if ( empty ( $new_wheres ) ) {
			$result = $where_clause; // just return the original WHERE clause
		} else {
			$result = implode( ' AND ', $new_wheres ); // Combine the where clauses with AND
			$result = $where_clause . " AND $result"; // Put my new clauses on the end of the original
		} // endif

		return $result;

	} // function


	private static function modify_orderby_clause( $orderby_clause, $query ) {
		global $wpdb;

		$orderby_key = self::get_orderby();
		$order = $query->get( 'order' );
//		Error_Log::var_dump( $orderby_key , $order );

		// Always put events with no start date at the top of the list
		$date_order = "start_date_meta.meta_value IS NULL DESC, start_date_meta.meta_value $order";

		switch ( $orderby_key ) {

			case 'event_date':
				$result = $date_order;
				break;

			default:
				$result = $orderby_clause;
				break;

		} // endswitch

		return $result;
	} // function



	/**
	 * Handle a post save event for my post type
	 *
	 * @param	int		$post_id	The ID of the post being saved
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_post_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Don't do anything during an autosave
			return;
		} else {
			$event_descriptor = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post_id );
			if ( !empty( $event_descriptor ) ) {

				// If there are any missing required settings then I will set this post to DRAFT status later
				$set_post_status_draft = FALSE;

				// Update the event dates
				if ( isset( $_POST[ 'event_dates_input_flag' ] ) ) {

					$start_date_string = isset( $_POST[ 'event_start_date' ] ) ? $_POST[ 'event_start_date' ] : NULL;
					$start_time_string = isset( $_POST[ 'event_start_time' ] ) ? $_POST[ 'event_start_time' ] : NULL;
					$end_time_string   = isset( $_POST[ 'event_end_time' ] )   ? $_POST[ 'event_end_time' ]   : NULL;

					// If there are no event dates posted then don't do anything, just leave it as-is
					// This could happen if they somehow blanked out the form inputs - just ignore that
					if ( ( $start_date_string !== NULL ) && ( $start_time_string !== NULL ) && ( $end_time_string !== NULL ) ) {
						$start_string = "$start_date_string $start_time_string";
						$end_string   = "$start_date_string $end_time_string";
						// All times will be specified by the user in the local timezone
						$local_tz = wp_timezone();
						try {
							$start_date_time = new \DateTime( $start_string, $local_tz );
						} catch ( \Exception $exc ) {
							/* translators: %1$s is an invalid date value supplied for an event */
							$msg = sprintf( __( 'An invalid event start date was supplied: %1$s.', 'reg-man-rc' ), $start_string );
							Error_Log::log_exception( $msg, $exc );
							$start_date_time = NULL;
						} // endtry
						try {
							$end_date_time = new \DateTime( $end_string, $local_tz );
						} catch ( \Exception $exc ) {
							/* translators: %1$s is an invalid date value supplied for an event */
							$msg = sprintf( __( 'An invalid event end date was supplied: %1$s.', 'reg-man-rc' ), $end_string );
							Error_Log::log_exception( $msg, $exc );
							$end_date_time = NULL;
						} // endtry
						if ( ( $start_date_time !== NULL ) && ( $end_date_time !== NULL ) ) {
							if ( $end_date_time < $start_date_time ) {
								// The event can't end before it starts so just replace end time with start
								$end_date_time = $start_date_time;
							} // endif
							$event_descriptor->set_event_start_date_time( $start_date_time );
							$event_descriptor->set_event_end_date_time( $end_date_time );
						} // endif
					} // endif
				} // endif

				// Update the event status
				if ( isset( $_POST[ 'item_status_input_flag' ] ) ) {
					$event_status_id = isset( $_POST['event_status'] ) ? $_POST['event_status'] : NULL;
					// If there is no event status posted then don't do anything, just leave it as-is
					if ( $event_status_id !== NULL ) {
						$event_status = Event_Status::get_event_status_by_id( $event_status_id );
						if ( $event_status !== NULL ) {
							$event_descriptor->set_event_status( $event_status );
						} // endif
					} // endif
				} // endif

				// update the event categories
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				if ( isset( $_POST['event_category_selection_flag'] ) ) {
					// First check to see if it's set then make it an array
					$category = isset( $_POST['event_category'] ) ? $_POST['event_category'] : NULL;
					if ( empty( $category ) || ( $category == '0' ) ) {
						// No event categories selected so set it to the default
						$default_event_category = Event_Category::get_default_event_category();
						$event_descriptor->set_event_category_array( array( $default_event_category ) );
					} else {
						// We will assign an array of categories even if the user only supplied one value
						$category_id_array = is_array( $category ) ? $category : array( $category );
						$category_array = array();
						foreach ( $category_id_array as $category_d ) {
							$category = Event_Category::get_event_category_by_id( $category_d );
							if ( isset( $category ) ) {
								$category_array[] = $category;
							} // endif
						} // endfor
						$event_descriptor->set_event_category_array( $category_array );
					} // endif
				} // endif

				// update the fixer stations
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				if ( isset( $_POST['fixer_station_selection_flag'] ) ) {
					$fixer_station_id_array = isset( $_POST['fixer_station'] ) ? $_POST['fixer_station'] : array();
					if ( ! is_array( $fixer_station_id_array ) || empty( $fixer_station_id_array ) ) {
						// No fixer stations selected so set it to NULL (remove any assignment)
						$event_descriptor->set_event_fixer_station_array( NULL );
					} else {
						$station_array = array();
						foreach ( $fixer_station_id_array as $station_id ) {
							$station = Fixer_Station::get_fixer_station_by_id( $station_id );
							if ( isset( $station ) ) {
								$station_array[] = $station;
							} // endif
						} // endfor
						$event_descriptor->set_event_fixer_station_array( $station_array );
					} // endif
					// Update the flag for non-repair events
					$is_non_repair = isset( $_POST[ 'is_non_repair_event' ] );
					$event_descriptor->set_event_is_non_repair( $is_non_repair );
				} // endif

				// Update the event venue
				if ( isset( $_POST['event_venue_input_flag'] ) ) {

					$venue_selection = isset( $_POST['event_venue_select'] ) ? $_POST['event_venue_select'] : 0;
					if ( $venue_selection == 0 ) {

						// The user selected "No venue"
						$event_descriptor->set_event_venue( NULL ); // remove any existing venue

					} elseif ( $venue_selection == -1 ) {

						// The user selected "Add venue"
						$event_descriptor->set_event_venue( NULL ); // remove any existing venue then create the new one
						$venue_name = isset( $_POST['venue_name'] ) ? sanitize_text_field( $_POST['venue_name'] ) : NULL;
						$loc = isset( $_POST[ 'venue_location' ] ) ? sanitize_text_field( $_POST[ 'venue_location' ] ) : NULL;
						$lat_lng = isset( $_POST[ 'venue_lat_lng' ] ) ? stripslashes( $_POST[ 'venue_lat_lng' ] ) : NULL;
						$geo = isset( $lat_lng ) ? Geographic_Position::create_from_google_map_marker_position_string( $lat_lng ) : NULL;
						$zoom = isset( $_POST[ 'venue_map_zoom' ] ) ? $_POST[ 'venue_map_zoom' ] : NULL;

						$venue = Venue::create_new_venue( $venue_name, $loc, $geo, $zoom );

						if ( ! empty( $venue ) ) {
							$event_descriptor->set_event_venue( $venue );
						} // endif

					} else {

						// The user selected a specific venue by ID
						$venue = Venue::get_venue_by_id( $venue_selection );
						$event_descriptor->set_event_venue( $venue );

					} // endif

				} // endif

				if ( $set_post_status_draft ) {
					// Unhook this function so it doesn't loop infinitely
					remove_action( 'save_post_' . Internal_Event_Descriptor::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );
					// Set the post status to draft since it doesn't have a valid item type
					$post_data = array(
						'ID'		  => $post_id,
						'post_status' => 'draft',
					);
					wp_update_post( $post_data );
				} // endif

			} // endif

		} // endif

	} // function


	/**
	 * Display a message at the top of the form if there is a missing required field
	 *
	 * @param	\WP_Post	$post	The ID of the post being saved
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function show_edit_form_messages( $post ) {
		if ( Internal_Event_Descriptor::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );

			if ( ! empty( $event_desc ) ) {

				// FIXME: If this is an event that appears in the Item Registration calendar then it should be
				//  required to have fixer stations.  Otherwise, it's not really even a warning.
//				$error_format = '<div class="error below-h2"><p>%s</p></div>';
				$warning_format = '<div class="notice notice-warning below-h2 is-dismissible"><p>%s</p></div>';

				if ( empty( $event_desc->get_event_fixer_station_array() ) ) {
					printf( $warning_format, esc_html__( __( 'No fixer stations assigned for this event', 'reg-man-rc' ) ) );
				} // endif

			} // endif

		} // endif
	} // function


	/**
	 * Modify the query for the months dropdown for my post type.
	 * We want to show event dates rather then post dates.
	 *
	 * @param	string[]	$months		The current array of months
	 * @param	string		$post_type	The current post type whose months dropdown will be displayed
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function modify_months_dropdown_query( $months, $post_type ) {
		if ( $post_type == Internal_Event_Descriptor::POST_TYPE ) {
			global $wpdb;
			$meta = $wpdb->postmeta;
			$key = Internal_Event_Descriptor::START_META_KEY;
			$query = 'SELECT DISTINCT YEAR( meta_value ) AS year, MONTH( meta_value ) AS month' .
						" FROM `$meta` WHERE meta_key = %s ORDER BY meta_value DESC";
			$stmt = $wpdb->prepare( $query, $key );
			$result = $wpdb->get_results( $stmt );
		} else {
			$result = $months;
		} // endif
		return $result;
	} // function

} // class