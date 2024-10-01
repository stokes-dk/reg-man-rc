<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The item controller
 *
 * This class provides the controller function for working with items
 *
 * @since v0.1.0
 *
 */
class Item_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Item::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );

		// Check to make sure that an item type is selected and show an error message if not
		add_action( 'edit_form_top', array( __CLASS__, 'show_edit_form_error_msgs' ) );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

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
	public static function handle_post_save( $post_id, $post, $is_update ) {

//		Error_Log::var_dump( $is_update, $post_id, defined( 'DOING_AUTOSAVE' ), ( defined( 'DOING_AUTOSAVE' ) ? DOING_AUTOSAVE : FALSE ) );
		
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			
			// Don't do anything during an autosave
			return;
			
		} elseif ( ! $is_update ) {
			
			// This is a new post created with "Add New" so we just need to assign the visitor if necessary
			// Everything else, like event, status, fixer station etc. will be assigned as an update when the user saves the post
			
			// If this WP user is NOT allowed to read private items then we will assume she is registering an item for herself
			// In that case, we will assign the volunteer, fixer station and volunteer roles to this new record
			if ( ! current_user_can( 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {
				
				$is_create_new_visitor = TRUE; // Create a new visitor record if none exists for this user
				$visitor = Visitor::get_visitor_for_current_wp_user( $is_create_new_visitor );
				if ( ! empty( $visitor ) ) {
					
					$visitor_id = $visitor->get_id();
					update_post_meta( $post_id, Item::VISITOR_META_KEY, $visitor_id );
					
				} // endif
				
			} // endif
			
		} else {
			
			// This is an update to a post
			
			$item = Item::get_item_by_id( $post_id );
			if ( ! empty( $item ) ) {
				
				$visitor_before_update = $item->get_visitor();
				$event_key_before_update = $item->get_event_key_string();

				// If there are any missing required settings then I will set this post to DRAFT status later
				$set_post_status_draft = FALSE;

				// Update the event
				if ( isset( $_POST[ 'item_event_selection_flag' ] ) ) {
					$event_key = isset( $_POST[ 'item_event' ] ) ? wp_unslash( $_POST[ 'item_event' ] ) : NULL;
					// If there is no item event posted then don't do anything, this may be a quick edit
					if ( isset( $_POST[ 'item_event' ] ) ) {
						if ( empty( $event_key ) ) {
							$item->set_event_key( NULL );
							$set_post_status_draft = TRUE;
						} else {
							$item->set_event_key( $event_key );
						} // endif
					} // endif
				} // endif

				// Update the visitor
				if ( isset( $_POST[ 'item_visitor_input_flag' ] ) ) {
					$item_visitor_id = isset( $_POST[ 'item_visitor' ] ) ? $_POST[ 'item_visitor' ] : '';
					if ( $item_visitor_id == '' ) {

						// The user selected "No visitor"
						$item->set_visitor( NULL ); // remove any existing visitor
						$set_post_status_draft = TRUE; // The record has no visitor

					} elseif ( $item_visitor_id == -1 ) {

						// The user selected "Add visitor"
						$item->set_visitor( NULL ); // remove any existing visitor then create the new one

						$first_event_key = isset( $_POST[ 'is_visitor_first_event' ] ) ? $event_key : NULL;
						$full_name = isset( $_POST[ 'visitor_full_name' ] ) ? trim( $_POST[ 'visitor_full_name' ] ) : NULL;
						$public_name = isset( $_POST[ 'visitor_public_name' ] ) ? trim( $_POST[ 'visitor_public_name' ] ) : NULL;
						$email = isset( $_POST[ 'visitor_email' ] ) ? trim( $_POST[ 'visitor_email' ] ) : NULL;
						$is_join = isset( $_POST[ 'visitor_join_mail_list' ] );
						$visitor = Visitor::create_visitor( $public_name, $full_name, $email, $first_event_key, $is_join );

						if ( empty( $visitor ) ) {
							
							$set_post_status_draft = TRUE; // The visitor could not be created successfully
							$item->set_visitor( NULL ); // Make sure we remove any setting for the visitor
							
						} else {
							
							$item->set_visitor( $visitor );
							
						} // endif

					} else {

						// The user selected a specific visitor by ID
						$visitor = Visitor::get_visitor_by_id( $item_visitor_id );
						if ( empty( $visitor ) ) {
							
							$set_post_status_draft = TRUE; // The visitor selection is not valid
							$item->set_visitor( NULL ); // Make sure we remove any setting for the visitor
							
						} else {
							
							$item->set_visitor( $visitor );
							
							if ( isset( $_POST[ 'item_visitor_details_update_flag' ] ) ) {
								
								// This means that the user is updating the details of the visitor
								
								$public_name  = isset( $_POST[ 'visitor_public_name' ] ) ? $_POST[ 'visitor_public_name' ] : '';
								$full_name  = isset( $_POST[ 'visitor_full_name' ] ) ? $_POST[ 'visitor_full_name' ] : '';
								$is_join_mailing_list = isset( $_POST[ 'visitor_join_mail_list' ] ) ? $_POST[ 'visitor_join_mail_list' ] : FALSE;
								// Note that the email cannot be changed
								
								$visitor->set_public_name( $public_name );
								$visitor->set_full_name( $full_name );
								$visitor->set_is_join_mail_list( $is_join_mailing_list );
																
							} // endif
							
						} // endif

					} // endif
				} // endif

				// Update the status
				if ( isset( $_POST[ 'item_status_input_flag' ] ) ) {
					$status = isset( $_POST['status_id'] ) ? Item_Status::get_item_status_by_id( $_POST['status_id'] ) : NULL;
					if ( empty( $status ) ) {
						$item->set_item_status( NULL );
						$set_post_status_draft = TRUE;
					} else {
						$item->set_item_status( $status );
					} // endif
				} // endif

				// Update the item type for this item.  An item type is required
				if ( isset( $_POST[ 'item_type_input_flag' ] ) ) {
					$item_type = isset( $_POST['item_type'] ) ? Item_Type::get_item_type_by_id( $_POST['item_type'] ) : NULL;
					if ( empty( $item_type ) ) {
						$item->set_item_type( NULL );
						// When there are item types defined, we'll require one to be selected
						if ( ! empty( Item_Type::get_all_item_types() ) ) {
							$set_post_status_draft = TRUE;
						} // endif
					} else {
						$item->set_item_type( $item_type );
					} // endif
				} // endif

				// update the fixer station if one is selected
				if ( isset( $_POST[ 'fixer_station_selection_flag' ] ) ) {
					$fixer_station_id = isset( $_POST[ 'fixer_station' ] ) ? $_POST[ 'fixer_station' ] : 0;
					$station = Fixer_Station::get_fixer_station_by_id( $fixer_station_id );
					if ( empty( $station ) ) {
						// If there is no station then we'll tell the user
						$item->set_fixer_station( NULL );
						$set_post_status_draft = TRUE;
					} else {
						$item->set_fixer_station( $station );
					} // endif
				} // endif

				if ( $set_post_status_draft ) {
					// Unhook this function so it doesn't loop infinitely
					remove_action( 'save_post_' . Item::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );
					// Set the post status to draft since it doesn't have a valid item type
					$post_data = array(
						'ID'		  => $post_id,
						'post_status' => 'draft',
					);
					wp_update_post( $post_data );
				} // endif

				
				// Enforce the rule that each visitor may have only 1 active item per event
				// If any of item status, visitor or event have been updated
				if ( isset( $_POST[ 'item_status_input_flag' ] ) || isset( $_POST[ 'item_event_selection_flag' ] ) || isset( $_POST[ 'item_visitor_input_flag' ] ) ) {

					// Apply this rule for the current item's visitor and event
					if(  ! empty( $visitor ) && ! empty( $event_key ) ) {
					
						$item_status_id = isset( $_POST[ 'status_id' ] ) ? $_POST[ 'status_id' ] : NULL; 
						
						$visitor->enforce_single_active_item_rule( $event_key );
						
						$after_enforce_item_status = $item->get_item_status();
						if ( isset( $after_enforce_item_status )  && $item_status_id !== $after_enforce_item_status->get_id() ) {
	
								// After enforcing the rule above, the item status is not what the user assigned so show a message
							$msg =  __( 'The status was reassigned because the visitor may have 1 item in progress per event.', 'reg-man-rc' );
	
							$user_id = get_current_user_id();
							$seconds = 60; // save the transient for 1 minute
							set_transient( "reg-man-rc-invalid-status-{$post_id}-{$user_id}", $msg, $seconds );
	
						} // endif

						// Also, apply the rule to the visitor and event before the update was made (if they have changed)
						// This may cause a Standby item to be changed to "In Progress" if the item was moved to a different
						//  visitor or a different event
						if ( ! empty( $visitor_before_update ) && ! empty( $event_key_before_update ) ) {
							$visitor_before_update->enforce_single_active_item_rule( $event_key_before_update );
						} // endif
						
					} // endif
					
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
	public static function show_edit_form_error_msgs( $post ) {
		
		if ( Item::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$item = Item::get_item_by_id( $post->ID );

			if ( ! empty( $item ) ) {

				$error_format = '<div class="notice notice-error below-h2"><p>%s</p></div>';
				$warning_format = '<div class="notice notice-warming is-dismissible below-h2"><p>%s</p></div>';
				
				$user_id = get_current_user_id();
				$status_msg = get_transient( "reg-man-rc-invalid-status-{$post->ID}-{$user_id}" );
				if ( ! empty( $status_msg ) ) {
					printf( $warning_format, esc_html__( $status_msg ) );
				} // endif

				if ( empty( $item->get_event_key_string() ) ) {
					printf( $warning_format, esc_html__( __( 'Event is required.', 'reg-man-rc' ) ) );
				} // endif

				$visitor = $item->get_visitor();
				if ( empty( $visitor ) ) {
					printf( $error_format, esc_html__( __( 'Visitor is required.', 'reg-man-rc' ) ) );
				} // endif

				if ( empty( $item->get_item_type() ) && ! empty( Item_Type::get_all_item_types() ) ) {
					printf( $error_format, esc_html__( __( 'Item type is required.', 'reg-man-rc' ) ) );
				} // endif

				if ( empty( $item->get_fixer_station() ) ) {
					printf( $error_format, esc_html__( __( 'Fixer station is required.', 'reg-man-rc' ) ) );
				} // endif

			} // endif

		} // endif
	} // function

	/**
	 * Modify the query to show only events with the specified terms
	 * This is called during the pre_get_posts action hook.
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) && ( $post_type == Item::POST_TYPE ) ) {

			// Filter by metadata like event as necessary
			$meta_query_array = array();

			// Event
			$event_filter_name = Item::EVENT_META_KEY;
			$selected_event_key = isset( $_REQUEST[ $event_filter_name ] ) ? wp_unslash( $_REQUEST[ $event_filter_name ] ) : 0;
			if ( ! empty( $selected_event_key ) ) {
				$meta_query_array[] = array(
						'key'		=> Item::EVENT_META_KEY,
						'value'		=> $selected_event_key
				);
			} // endif

			// Visitor
			$visitor_filter_name = Item::VISITOR_META_KEY;
			$selected_visitor_key = isset( $_REQUEST[ $visitor_filter_name ] ) ? wp_unslash( $_REQUEST[ $visitor_filter_name ] ) : 0;
			if ( ! empty( $selected_visitor_key ) ) {
				$meta_query_array[] = array(
						'key'		=> Item::VISITOR_META_KEY,
						'value'		=> $selected_visitor_key
				);
			} // endif

			if ( ! empty( $meta_query_array ) ) {
				$query->set( 'meta_query', $meta_query_array );
			} // endif

			// Filter the taxonomies as necessary
			$tax_query_array = array();

			// Filter by Item Type
			$type_tax_name = Item_Type::TAXONOMY_NAME;
			$selected_slug = isset( $_REQUEST[ $type_tax_name ] ) ? $_REQUEST[ $type_tax_name ] : 0;
			if ( ! empty( $selected_slug ) ) {
				$tax_query_array[] =
						array(
								'taxonomy'	=> $type_tax_name,
								'field'		=> 'slug',
								'terms'		=> array( $selected_slug )
						);
			} // endif

			// Filter by Fixer Station
			$station_tax_name = Fixer_Station::TAXONOMY_NAME;
			$selected_slug = isset( $_REQUEST[ $station_tax_name ] ) ? $_REQUEST[ $station_tax_name ] : 0;
			if ( ! empty( $selected_slug ) ) {
				$tax_query_array[] =
						array(
								'taxonomy'	=> $station_tax_name,
								'field'		=> 'slug',
								'terms'		=> array( $selected_slug )
						);
			} // endif


//			Error_Log::var_dump( $tax_query_array );
//			Error_Log::var_dump( $query );

			if ( ! empty( $tax_query_array ) ) {
				$query->query_vars[ 'tax_query' ] = $tax_query_array;
			} // endif

		} // endif

		return $query;

	} // function

} // class