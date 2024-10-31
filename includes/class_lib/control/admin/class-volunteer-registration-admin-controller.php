<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Term_Order_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Add_Volunteer_Registration_Form;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteers
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_Admin_Controller {

	const ADD_VOLUNTEER_REG_AJAX_ACTION			= 'reg_man_rc_add_volunteer_reg';
	const ADD_VOLUNTEER_REG_REQUEST_GET_FORM	= 'get-form';
	const ADD_VOLUNTEER_REG_REQUEST_ADD			= 'add';
	
	/**
	 * Register this controller
	 */
	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 2 );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

		// Filter the clauses for my custom post type to allow custom filtering on metadata
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 1000, 2 );

		add_action( 'wp_ajax_' . self::ADD_VOLUNTEER_REG_AJAX_ACTION, array( __CLASS__, 'handle_add_volunteer_reg_request' ) );
		
	} // function

	/**
	 * Handle an AJAX request to get the remove form content for a specific record.
	 *
	 * @return	void
	 */
	public static function handle_add_volunteer_reg_request() {

		$form_response = Ajax_Form_Response::create();
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

		// The referer page is where the request came from.  On successful removal we'll get the client to reload this page
		$referer			= $_SERVER[ 'HTTP_REFERER' ];
		
		$nonce				= isset( $form_data[ '_wpnonce' ] )			? $form_data[ '_wpnonce' ]			: '';
		$event_key			= isset( $form_data[ 'event-key' ] )		? $form_data[ 'event-key' ]			: NULL;
		$volunteer_id		= isset( $form_data[ 'volunteer-id' ] )		? $form_data[ 'volunteer-id' ]		: NULL;
		$request_type		= isset( $form_data[ 'request-type' ] )		? $form_data[ 'request-type' ]		: self::REMOVE_VOLUNTEER_ACTION_GET;

//		Error_Log::var_dump( $form_data, $nonce, $referer, $event_key, $volunteer_id, $request_type );
		$is_valid_nonce = wp_verify_nonce( $nonce, self::ADD_VOLUNTEER_REG_AJAX_ACTION );
		$event = Event::get_event_by_key( $event_key );
		$volunteer = Volunteer::get_volunteer_by_id( $volunteer_id );
		$is_authorized = ! empty( $event ) ? $event->get_is_current_user_able_to_register_volunteers() : FALSE;
		
		// Volunteer is required when the request is not for "get form"
		$is_invalid_volunteer = ( $request_type !== self::ADD_VOLUNTEER_REG_REQUEST_GET_FORM ) && empty( $volunteer );
		
		if ( ! $is_valid_nonce || empty( $event ) || ! $is_authorized || $is_invalid_volunteer ) {

			if ( ! $is_valid_nonce ) {
				$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
				$form_response->add_error( '_wpnonce', '', $err_msg );
			} // endif

			if ( empty( $event ) ) {
				if ( empty( $event_key ) ) {
					$err_msg = __( 'No event key was specified.', 'reg-man-rc' );
					$form_response->add_error( 'event-key', '', $err_msg );
				} else {
					/* Translators: %1$s is an event key */
					$err_msg = sprintf( __( 'The event could not be found for event key %1$s.', 'reg-man-rc' ), $event_key );
					$form_response->add_error( 'event-key', $event_key, $err_msg );
				} // endif
			} else {
				if ( ! $is_authorized ) {
					$err_msg = __( 'You are not authorized to register volunteers for this event.', 'reg-man-rc' );
					$form_response->add_error( 'event-key', $event_key, $err_msg );
				} // endif
			} // endif

			if ( $is_invalid_volunteer ) {
				if ( empty( $volunteer_id ) ) {
					$err_msg = __( 'No volunteer was selected.', 'reg-man-rc' );
					$form_response->add_error( 'volunteer-d', '', $err_msg );
				} else {
					/* Translators: %1$s is a volunteer ID */
					$err_msg = sprintf( __( 'The volunteer could not be found with ID %1$s.', 'reg-man-rc' ), $volunteer_id );
					$form_response->add_error( 'volunteer-id', $volunteer_id, $err_msg );
				} // endif
			} // endif

		} else {

			switch( $request_type ) {
				
				case self::ADD_VOLUNTEER_REG_REQUEST_GET_FORM:
				default:
					$view = Add_Volunteer_Registration_Form::create( $event );
					$content = $view->get_add_form_content();
					
					$form_response->set_html_data( $content );
					break;
					
				case self::ADD_VOLUNTEER_REG_REQUEST_ADD:
					$result = self::handle_add_volunteer_reg( $event, $volunteer, $form_response );
					if ( $result === TRUE ) {
//						$form_response->set_redirect_url( $referer ); // reload the page on success
					} // endif
					break;
					
			} // endswitch
			
		} // endif

//		Error_Log::var_dump( $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
		
	} // class
	
	/**
	 * Handle request to add volunteer registration
	 * @param Event					$event
	 * @param Volunteer				$volunteer
	 * @param Ajax_Form_Response	$form_response
	 */
	private static function handle_add_volunteer_reg( $event, $volunteer, $form_response ) {

		// Make sure this volunteer is not already registered
		$event_key = $event->get_key_string();
		$volunteer_id = $volunteer->get_id();
//		Error_Log::var_dump( $volunteer_id );
		$vol_reg_array = Volunteer_Registration::get_all_registrations_for_event( $event_key );
		$is_already_registered = FALSE; // unless we find it
		foreach( $vol_reg_array as $vol_reg ) {
			$curr_vol_id = $vol_reg->get_volunteer_id();
//			Error_Log::var_dump( $curr_vol_id );
			if ( $curr_vol_id == $volunteer_id ) {
				$is_already_registered = TRUE;
				break;
			} // endif
		} // endfor

		if ( $is_already_registered ) {

			$err_msg = __( 'The volunteer is already registered for this event.', 'reg-man-rc' );
			$form_response->add_error( 'volunteer-id', $volunteer_id, $err_msg );

		} else {

			$roles = $volunteer->get_preferred_roles();
			$fixer_station = $volunteer->get_preferred_fixer_station();
			$is_apprentice = $volunteer->get_is_fixer_apprentice();
			$vol_reg = Volunteer_Registration::create_new_registration( $volunteer, $event, $roles, $fixer_station, $is_apprentice );
//			Error_Log::var_dump( $vol_reg );
			if ( empty( $vol_reg ) ) {

				$err_msg = __( 'ERROR: unable to create the volunteer registration record.', 'reg-man-rc' );
				$form_response->add_error( '', '', $err_msg );
				
			} // endif

		} // endif
		
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
					$event_key = isset( $_POST[ 'volunteer_registration_event' ] ) ? $_POST[ 'volunteer_registration_event' ] : 0;
					$event_key = wp_unslash( $event_key );
					$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
					$registration->set_event( $event );
				}// endif

				// Update the volunteer
				if ( isset( $_POST[ 'volunteer_reg_volunteer_input_flag' ] ) ) {
					
					// Get the volunteer ID for this registration
					$id = isset( $_POST[ 'volunteer_registration_volunteer' ] ) ? $_POST[ 'volunteer_registration_volunteer' ] : 0;

					if ( $id == '-1' ) {

						// This means that we are adding a new volunteer
						$public_name  = isset( $_POST[ 'volunteer_public_name' ] ) ? $_POST[ 'volunteer_public_name' ] : '';
						$full_name  = isset( $_POST[ 'volunteer_full_name' ] ) ? $_POST[ 'volunteer_full_name' ] : NULL;
						$email  = isset( $_POST[ 'volunteer_email' ] ) ? $_POST[ 'volunteer_email' ] : NULL;
						// Check if a volunteer with this email already exists!!!
						$volunteer = Volunteer::get_volunteer_by_email( $email );
						if ( empty( $volunteer ) ) {
							$volunteer = Volunteer::create_new_volunteer( $public_name, $full_name, $email );
						} // endif
						
					} else {
						
						$volunteer = ! empty( $id ) ? Volunteer::get_volunteer_by_id( $id ) : NULL;
						
						if ( isset( $_POST[ 'volunteer_reg_volunteer_details_update_flag' ] ) ) {
							
							// This means that the user is updating the details of the volunteer

							$public_name  = isset( $_POST[ 'volunteer_public_name' ] ) ? $_POST[ 'volunteer_public_name' ] : '';
							$full_name  = isset( $_POST[ 'volunteer_full_name' ] ) ? $_POST[ 'volunteer_full_name' ] : NULL;
							// Note that the email cannot be changed
							
							$volunteer->set_public_name( $public_name );
							$volunteer->set_full_name( $full_name );
							
						} // endif
						
					} // endif

					if ( ! isset( $_POST[ 'volunteer_reg_volunteer_details_update_flag' ] ) ) {
						
						// Assign the volunteer, if we are not updating
						$registration->set_volunteer( $volunteer );
						
					} // endif
					
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