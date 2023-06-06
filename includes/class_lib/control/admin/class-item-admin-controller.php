<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Visitor;

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
		add_action( 'save_post_' . Item::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

		// Check to make sure that an item type is selected and show an error message if not
		add_action( 'edit_form_top', array( __CLASS__, 'show_required_field_error_msg' ) );

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
	public static function handle_post_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Don't do anything during an autosave
			return;
		} else {
			$item = Item::get_item_by_id( $post_id );
			if ( ! empty( $item ) ) {

				// If there are any missing required settings then I will set this post to DRAFT status later
				$set_post_status_draft = FALSE;

				// Update the event
				if ( isset( $_POST[ 'item_event_selection_flag' ] ) ) {
					$event_key = isset( $_POST['item_event'] ) ? wp_unslash( $_POST['item_event'] ) : NULL;
					// If there is no item event posted then don't do anything, this may be a quick edit
					if ( isset( $_POST[ 'item_event' ] ) ) {
						if ( $event_key == '-1' ) {
							// -1 indicates no event selected
							$item->set_event_key( NULL );
							$set_post_status_draft = TRUE;
						} else {
							$item->set_event_key( $event_key );
						} // endif
					} // endif
				} // endif

				// Update the visitor
				if ( isset( $_POST[ 'item_visitor_input_flag' ] ) ) {
					$visitor_selection = isset( $_POST[ 'item_visitor_select' ] ) ? $_POST[ 'item_visitor_select' ] : '';
					if ( $visitor_selection == '' ) {

						// The user selected "No visitor"
						$item->set_visitor( NULL ); // remove any existing visitor
						$set_post_status_draft = TRUE; // The record has no visitor

					} elseif ( $visitor_selection == -1 ) {

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
						$visitor = Visitor::get_visitor_by_id( $visitor_selection );
						if ( empty( $visitor ) ) {
							$set_post_status_draft = TRUE; // The visitor selection is not valid
							$item->set_visitor( NULL ); // Make sure we remove any setting for the visitor
						} else {
							$item->set_visitor( $visitor );
						} // endif

					} // endif
				} // endif

				// Update the status
				if ( isset( $_POST[ 'item_status_input_flag' ] ) ) {
					$status = isset( $_POST['status_id'] ) ? Item_Status::get_item_status_by_id( $_POST['status_id'] ) : NULL;
					if ( empty( $status ) ) {
						$item->set_status( NULL );
						$set_post_status_draft = TRUE;
					} else {
						$item->set_status( $status );
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
						// Use the item type's default station if there is one
						$item_type = $item->get_item_type();
						$station = ! empty( $item_type ) ? $item_type->get_fixer_station() : NULL;
					} // endif
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
	public static function show_required_field_error_msg( $post ) {
		if ( Item::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$item = Item::get_item_by_id( $post->ID );

			if ( ! empty( $item ) ) {

				$error_format = '<div class="error below-h2"><p>%s</p></div>';

				if ( empty( $item->get_event_key() ) ) {
					printf( $error_format, esc_html__( __( 'Event is required.', 'reg-man-rc' ) ) );
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
				/* FIXME = Option for NONE is not working
				// -1 is the option for none
				if ( $selected_slug == '-1' ) {
					$tax_query_array[] =
							array(
									'taxonomy'	=> $type_tax_name,
									'operator'	=> 'NOT EXISTS',
							);
				} else {
				*/
					$tax_query_array[] =
							array(
									'taxonomy'	=> $type_tax_name,
									'field'		=> 'slug',
									'terms'		=> array( $selected_slug )
							);
//				} // endif
			} // endif

			// Filter by Fixer Station
			$station_tax_name = Fixer_Station::TAXONOMY_NAME;
			$selected_slug = isset( $_REQUEST[ $station_tax_name ] ) ? $_REQUEST[ $station_tax_name ] : 0;
			if ( ! empty( $selected_slug ) ) {
				/* FIXME = Option for NONE is not working
				// -1 is the option for none
				if ( $selected_slug == '-1' ) {
					$tax_query_array[] =
							array(
									'taxonomy'	=> $station_tax_name,
									'operator'	=> 'NOT EXISTS'
							);
				} else {
				*/
					$tax_query_array[] =
							array(
									'taxonomy'	=> $station_tax_name,
									'field'		=> 'slug',
									'terms'		=> array( $selected_slug )
							);
//				} // endif
			} // endif


			// FIXME!!!! The fixer station is always assigned or it just missing - the following is OLD
/*
			// Filter by Fixer Station which is more complicated because it's not always assigned
			//  Sometimes the fixer station is explicitly assigned but mostly we use the default for the item type
			$station_tax_name = Fixer_Station::TAXONOMY_NAME;
			$selected_slug = isset( $_REQUEST[ $station_tax_name ] ) ? $_REQUEST[ $station_tax_name ] : 0;
			if ( ! empty( $selected_slug ) ) {
				// I need to find items where the assigned fixer station is the one selected OR
				//  ( there is no assigned station AND item type IN any type whose default station is the one selected )

				$station = Fixer_Station::get_fixer_station_by_slug( $selected_slug );
				if ( ! empty( $station ) ) {
					$item_types = Item_Type::get_item_types_by_default_fixer_station( $station->get_id() );
				} else {
					$item_types = array(); // No station so no item types
				} // endif
				$item_type_ids = array();
				foreach ( $item_types as $type ) {
					$item_type_ids[] = $type->get_id();
				} // endfor

//				Error_Log::var_dump( $selected_slug, $station->get_id(), $item_type_ids );

				$tax_query_array[] = array(
						'relation' => 'OR',

								array(
										'taxonomy'	=> $station_tax_name,
										'field'		=> 'id',
										'terms'		=> array( $station->get_id() )
								),

								array(
										'relation' => 'AND',
										array(
												'taxonomy'	=> $station_tax_name,
												'field'		=> 'id',
												'operator'	=> 'NOT EXISTS'
										),
										array(
												'taxonomy'	=> $type_tax_name,
												'field'		=> 'id',
												'terms'		=> $item_type_ids
										),
								)
					);
				// By default Wordpress will ALWAYS filter showing only posts where fixer station is the one requested
				//   AND whatever I have added above.
				// To undo that and search using ONLY what I have, I need to turn off the query var for the station taxonomy
				$query->query_vars[ $station_tax_name ] = '0'; // Tell the query not to require the specified station
			} // endif
*/

//			Error_Log::var_dump( $tax_query_array );
//			Error_Log::var_dump( $query );

			if ( ! empty( $tax_query_array ) ) {
				$query->query_vars[ 'tax_query' ] = $tax_query_array;
			} // endif

		} // endif

		return $query;

	} // function

} // class