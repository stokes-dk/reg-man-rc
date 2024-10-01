<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\View\Pub\Visitor_List_View;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Pub\Single_Item_Details_View;
use Reg_Man_RC\View\Pub\Single_Visitor_Details_View;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Event_Category;

/**
 * The visitor registration controller
 *
 * This class provides the controller function for working with visitor registration
 *
 * @since v0.1.0
 *
 */
class Visitor_Registration_Controller {

	const ADD_EVENT_AJAX_ACTION					= 'reg-man-rc-visitor-reg-add-event';

	const DATATABLE_LOAD_AJAX_ACTION			= 'reg-man-rc-visitor-reg-datatable-load';

	const ITEM_STATUS_UPDATE_AJAX_ACTION		= 'reg-man-rc-visitor-reg-item-status-update';

	const ITEM_TYPE_UPDATE_AJAX_ACTION 			= 'reg-man-rc-visitor-reg-item-type-update';

	const FIXER_STATION_UPDATE_AJAX_ACTION		= 'reg-man-rc-visitor-reg-fixer-station-update';

	const NEW_VISITOR_REG_AJAX_ACTION			= 'reg-man-rc-visitor-reg-add-new-visitor';

	const GET_ITEM_UPDATE_CONTENT_AJAX_ACTION	= 'reg-man-rc-visitor-reg-get-item-update-content';

	const ITEM_UPDATE_AJAX_ACTION				= 'reg-man-rc-visitor-reg-item-update';

	const GET_VISITOR_ITEMS_LIST_AJAX_ACTION	= 'reg-man-rc-visitor-reg-get-visitor-items';
	
	const ADD_ITEM_TO_VISITOR_AJAX_ACTION		= 'reg-man-rc-visitor-reg-add-item-to-visitor';

	
	public static function register() {

		// Add handler methods for adding a new event
		add_action( 'wp_ajax_' .		self::ADD_EVENT_AJAX_ACTION, array(__CLASS__, 'handle_add_event_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::ADD_EVENT_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for AJAX datatable load
		add_action( 'wp_ajax_' .		self::DATATABLE_LOAD_AJAX_ACTION, array(__CLASS__, 'handle_datatable_load_ajax_get_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::DATATABLE_LOAD_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for status update
		add_action( 'wp_ajax_' .		self::ITEM_STATUS_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_item_status_update_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::ITEM_STATUS_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for item type update
		add_action( 'wp_ajax_' .		self::ITEM_TYPE_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_item_type_update_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::ITEM_TYPE_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for fixer station update
		add_action( 'wp_ajax_' .		self::FIXER_STATION_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_fixer_station_update_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::FIXER_STATION_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handlers for new visitor registrations
		add_action( 'wp_ajax_' .		self::NEW_VISITOR_REG_AJAX_ACTION, array(__CLASS__, 'handle_new_registration_priv') );
		add_action( 'wp_ajax_nopriv_' .	self::NEW_VISITOR_REG_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );
		
		// Add handler for getting the content of the form to update a single item
		add_action( 'wp_ajax_' .		self::GET_ITEM_UPDATE_CONTENT_AJAX_ACTION, array(__CLASS__, 'handle_get_item_update_content_priv' ) );
		add_action( 'wp_ajax_nopriv_' .	self::GET_ITEM_UPDATE_CONTENT_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler for updating a single item
		add_action( 'wp_ajax_' .		self::ITEM_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_update_item_priv' ) );
		add_action( 'wp_ajax_nopriv_' .	self::ITEM_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler for getting a visitor's items list
		add_action( 'wp_ajax_' .		self::GET_VISITOR_ITEMS_LIST_AJAX_ACTION, array(__CLASS__, 'handle_get_visitor_items_list_priv' ) );
		add_action( 'wp_ajax_nopriv_' .	self::GET_VISITOR_ITEMS_LIST_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler for adding an item to a visitor
		add_action( 'wp_ajax_' .		self::ADD_ITEM_TO_VISITOR_AJAX_ACTION, array(__CLASS__, 'handle_add_item_to_visitor_priv' ) );
		add_action( 'wp_ajax_nopriv_' .	self::ADD_ITEM_TO_VISITOR_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

	} // function

	/**
	 * Get the datatable data
	 */
	public static function handle_add_event_priv() {

		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array);

		$form_response = Ajax_Form_Response::create();
		
		$nonce			= isset( $form_data_array[ '_wpnonce' ] )		? $form_data_array[ '_wpnonce' ]		: '';
		$title			= isset( $form_data_array[ 'event-title' ] )	? $form_data_array[ 'event-title' ]		: '';
		$date			= isset( $form_data_array[ 'event-date' ] )		? $form_data_array[ 'event-date' ]		: '';
		$category_id	= isset( $form_data_array[ 'event-category' ] )	? $form_data_array[ 'event-category' ]	: NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::ADD_EVENT_AJAX_ACTION );
		$user_can_add_events = current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL );
		
		$local_timezone = wp_timezone();

		$start_time = Settings::get_default_event_start_time();
		$start_date_time = new \DateTime( "$date $start_time", $local_timezone );

		$end_time = Settings::get_default_event_end_time();
		$end_date_time = new \DateTime( "$date $end_time", $local_timezone );

		$category = Event_Category::get_event_category_by_id( $category_id );
//		Error_Log::var_dump( $is_valid_nonce, $user_can_add_events, $title, $start_date_time, $end_date_time, $category );

		if ( ! $is_valid_nonce || ! $user_can_add_events || empty( $category ) ) {
			
			if ( ! $is_valid_nonce ) {
				
				$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
				$form_response->add_error( '_wpnonce', '', __( $error_message, 'reg-man-rc' ) );
				
			} // endif
			
			if ( ! $user_can_add_events ) {
				
				$error_message = __( 'ERROR: You are not authorized to create events.' , 'reg-man-rc' );
				$form_response->add_error( '', '', __( $error_message, 'reg-man-rc' ) );
				
			} // endif
			
			if ( empty( $category ) ) {
				
				$error_message = __( 'ERROR: The event category is not found.' , 'reg-man-rc' );
				$form_response->add_error( 'event-category', $category_id, __( $error_message, 'reg-man-rc' ) );
				
			} // endif
			
		} else {
			
			$category_array = array( $category );
			$new_event = Internal_Event_Descriptor::create_internal_event_descriptor( $title, $start_date_time, $end_date_time, $category_array );

			if ( empty( $new_event ) ) {
				
				$error_message = __( 'ERROR: Unable to create the event.' , 'reg-man-rc' );
				$form_response->add_error( '', '', __( $error_message, 'reg-man-rc' ) );
				
			} // endif

		} // endif
			
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
		
	} // function

	/**
	 * Get the datatable data
	 */
	public static function handle_datatable_load_ajax_get_priv() {

		$nonce		= isset( $_GET[ '_wpnonce' ] )		? $_GET[ '_wpnonce' ]		: '';
		$key		= isset( $_GET[ 'event_key' ] )		? $_GET[ 'event_key' ]		: '';
		$station_id = isset( $_GET[ 'fixer_station' ] )	? $_GET[ 'fixer_station' ]	: 0;
		
		$event = Event::get_event_by_key( $key );

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::DATATABLE_LOAD_AJAX_ACTION );
		$user_can_register = ! empty( $event ) ? $event->get_is_current_user_able_to_register_items() : FALSE;
		
		$fixer_station = ! empty( $station_id ) ? Fixer_Station::get_fixer_station_by_id( $station_id ) : NULL;
		
		$result = array();
		
		if ( ! $is_valid_nonce || empty( $key ) || empty( $event ) || ! $user_can_register ) {
			
			$result[ 'data' ] = array();
		
			if ( ! $is_valid_nonce ) {

				$result[ 'error' ] = __( 'ERROR: Invalid security token.  Please refresh the page.', 'reg-man-rc' );

			} elseif ( empty( $key ) ) {

				$result[ 'error' ] = __( 'ERROR: Missing event key', 'reg-man-rc' );

			} elseif( empty( $event ) ) {
				
				$result[ 'error' ] = __( 'ERROR: Event not found', 'reg-man-rc' );

			} elseif( ! $user_can_register ) {
				
				$result[ 'error' ] = __( 'ERROR: You are not authorized to register visitors for this event', 'reg-man-rc' );

			} // endif
			
		} else {

	//		Error_Log::var_dump( $_GET, $key, $event );
			$row_data = ( $event === NULL ) ? array() : Visitor_List_View::get_registration_data( $event, $fixer_station );
	
			$result[ 'data' ] = $row_data; // This is how datatables expects the result
			
		} // endif
		
		echo json_encode( $result );

		wp_die(); // THIS IS REQUIRED!
		
	} // function

	public static function handle_item_status_update_priv() {
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array);

		$nonce =		isset( $form_data_array[ '_wpnonce' ] )		? $form_data_array[ '_wpnonce' ] : '';
		$item_id =		isset( $form_data_array[ 'item-id' ] )		? $form_data_array[ 'item-id' ] : '';
		$status_id =	isset( $form_data_array[ 'item-status' ] )	? $form_data_array[ 'item-status' ] : '';

		$item = Item::get_item_by_id( $item_id );
		$item_status = Item_Status::get_item_status_by_id( $status_id );

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::ITEM_STATUS_UPDATE_AJAX_ACTION );
		$user_can = ! empty( $item ) ? $item->get_can_current_user_update_visitor_registration_details() : FALSE;
		
		if ( ! $is_valid_nonce || ! isset( $item ) || ! $user_can  || ! isset( $item_status ) ) {
			
			$response_success = FALSE;

			if ( ! $is_valid_nonce ) {
				
				$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
				
			} elseif ( ! isset( $item ) ) {
				
				$error_message = __( 'ERROR: Item not found.  Please refresh the list.' , 'reg-man-rc' );
				
			} elseif ( ! $user_can ) {

				$error_message = __( 'ERROR: You are not authorized to modify this item' , 'reg-man-rc' );
				
			} elseif ( ! isset( $item_status ) ) {

				$error_message = __( 'ERROR: Repair outcome not found' , 'reg-man-rc' );
				
			} // endif
			
		} else {
		
			$item->set_item_status( $item_status );
			$response_success = TRUE;
			$error_message = '';

			// Get the visitor and event so we can enforce the single active item rule
			$visitor = $item->get_visitor();
			$event_key = $item->get_event_key_string();

			if ( ! empty( $visitor ) && ! empty( $event_key ) ) {
				$visitor->enforce_single_active_item_rule( $event_key );
			} // endif
			
			$after_enforce_item_status = $item->get_item_status();
			if ( isset( $after_enforce_item_status )  && $status_id !== $after_enforce_item_status->get_id() ) {
				// After enforcing the rule above, the item status is not what the user assigned so return a message
				$response_success = FALSE;
				$error_message = __( 'Each visitor may have 1 item in progress at a time.' , 'reg-man-rc' );
			} // endif
			
		} // endif
		
		$response = array(
//				'text'		=> $response_text,
				'success'	=> $response_success,
				'error'		=> $error_message,
		);
		echo json_encode( $response );

		wp_die(); // THIS IS REQUIRED!
		
	} // function

	/**
	 * Update the fixer station for an item
	 */
	public static function handle_item_type_update_priv() {
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		
		$nonce =		isset( $form_data_array[ '_wpnonce' ] )		? $form_data_array[ '_wpnonce' ] : '';
		$item_id =		isset( $form_data_array[ 'item-id' ] )		? $form_data_array[ 'item-id' ] : '';
		$item_type_id =	isset( $form_data_array[ 'item-type' ] )	? $form_data_array[ 'item-type' ] : '';

		$item = Item::get_item_by_id( $item_id );
		$item_type = Item_Type::get_item_type_by_id( $item_type_id );
		
		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::ITEM_TYPE_UPDATE_AJAX_ACTION );
		$user_can = ! empty( $item ) ? $item->get_can_current_user_update_visitor_registration_details() : FALSE;
		
		if ( ! $is_valid_nonce || ! isset( $item ) || ! $user_can  || ! isset( $item_type ) ) {
			
			$response_success = FALSE;
			$response_text = __( 'ERROR', 'reg-man-rc' );

			if ( ! $is_valid_nonce ) {
				
				$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
				
			} elseif ( ! isset( $item ) ) {
				
				$error_message = __( 'ERROR: Item not found.  Please refresh the list.' , 'reg-man-rc' );
				
			} elseif ( ! $user_can ) {

				$error_message = __( 'ERROR: You are not authorized to modify this item' , 'reg-man-rc' );
				
			} elseif ( ! isset( $item_type ) ) {

				$error_message = __( 'ERROR: Item type not found' , 'reg-man-rc' );
				
			} // endif
			
		} else {
		
			$item->set_item_type( $item_type );
			$response_text = $item_type->get_name();
			$response_success = TRUE;
			$error_message = '';
			
		} // endif
		
		$response = array(
				'text'		=> $response_text,
				'success'	=> $response_success,
				'error'		=> $error_message,
		);

		echo json_encode( $response );
		
		wp_die(); // THIS IS REQUIRED!

	} // function

	/**
	 * Update the fixer station for an item
	 */
	public static function handle_fixer_station_update_priv() {
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		
		$nonce =			isset( $form_data_array[ '_wpnonce' ] )			? $form_data_array[ '_wpnonce' ] : '';
		$item_id = 			isset( $form_data_array[ 'object-id' ] )		? $form_data_array[ 'object-id' ] : '';
		$fixer_station_id =	isset( $form_data_array[ 'fixer-station' ] )	? $form_data_array[ 'fixer-station' ] : '';

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::FIXER_STATION_UPDATE_AJAX_ACTION );
		$item = Item::get_item_by_id( $item_id );
		$fixer_station = Fixer_Station::get_fixer_station_by_id( $fixer_station_id );
		$user_can = ! empty( $item ) ? current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL, $item_id ) : FALSE;
		
		if ( ! $is_valid_nonce || ! isset( $item ) || ! $user_can || ! isset( $fixer_station ) ) {

			$response_success = FALSE;
			$response_text = __( 'ERROR', 'reg-man-rc' );

			if ( ! $is_valid_nonce ) {
				
				$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
				
			} elseif ( ! isset( $item ) ) {
				
				$error_message = __( 'ERROR: Item not found.  Please refresh the list.' , 'reg-man-rc' );
				
			} elseif ( ! $user_can ) {

				$error_message = __( 'ERROR: You are not authorized to modify this item' , 'reg-man-rc' );
				
			} elseif ( ! isset( $fixer_station ) ) {

				$error_message = __( 'ERROR: Fixer Station not found' , 'reg-man-rc' );
				
			} // endif
			
		} else {
			
			$item->set_fixer_station( $fixer_station );
			$response_text = $fixer_station->get_name();
			$response_success = TRUE;
			$error_message = '';
			
		} // endif
		
		$response = array(
				'text'		=> $response_text,
				'success'	=> $response_success,
				'error'		=> $error_message,
		);
		
		echo json_encode( $response );

		wp_die(); // THIS IS REQUIRED!
		
	} // function

	/**
	 * Handle an ajax post for a new visitor registration
	 */
	public static function handle_new_registration_priv() {
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		self::handle_form_ajax_new_visitor_registration_post( $form_data_array, $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle new registration post
	 * @param string[]				$form_data
	 * @param Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_new_visitor_registration_post( $form_data, $form_response ) {

		// event-key - must represent an event in the db
		// item-desc, item-type arrays - at least 1
		// email - if empty then no-email must exist, use NULL for email in that case
		// full-name - required
		// first-time - must be present, either yes or no
		// join-list - if the setting to include this question is on then it must be present, either yes or no
		// rules-ack - must exist

//		Error_Log::var_dump( $form_data );
		$nonce				= isset( $form_data[ '_wpnonce' ] )		? $form_data[ '_wpnonce' ] : '';
		$event_key			= isset( $form_data[ 'event-key' ] )	? $form_data[ 'event-key' ] : NULL;
		$item_desc_array	= isset( $form_data[ 'item-desc' ] ) && is_array( $form_data[ 'item-desc' ] ) ? $form_data[ 'item-desc' ] : array();
		$item_type_array	= isset( $form_data[ 'item-type' ] ) && is_array( $form_data[ 'item-type' ] ) ? $form_data[ 'item-type' ] : array();
		$fixer_station_array= isset( $form_data[ 'fixer-station' ] ) && is_array( $form_data[ 'fixer-station' ] ) ? $form_data[ 'fixer-station' ] : array();
		$is_first_time		= isset( $form_data[ 'first-time' ] )	? ( $form_data[ 'first-time' ] == 'YES' ) : NULL;
		$visitor_id			= isset( $form_data[ 'visitor-id' ] )	? $form_data[ 'visitor-id' ] : NULL;
		$full_name			= isset( $form_data[ 'full-name' ] )	? stripslashes( trim( $form_data[ 'full-name' ] ) ) : NULL;
		$email				= isset( $form_data[ 'email' ] )		? stripslashes( trim( $form_data[ 'email' ] ) ) : NULL;
		$is_no_email		= isset( $form_data[ 'no-email' ] )		? TRUE : FALSE;
		$is_rules_ack		= isset( $form_data[ 'rules-ack' ] )	? TRUE : FALSE;

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::NEW_VISITOR_REG_AJAX_ACTION );
		
		if ( ! $is_valid_nonce ) {
			
			$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', __( $error_message, 'reg-man-rc' ) );
			return; // <== EXIT POINT!!!  There is no sense going on
			
		} // endif
				
		// Get the event
		$event = isset( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
		
		// Trim the item descriptions and then make sure that at least one was supplied
		$trimmed_item_desc_array = array(); // start with empty array
		$is_valid_item_array = TRUE; // assume it's ok and set to false if there are problems
		foreach( $item_desc_array as $item_desc ) {
			$trimmed_item_desc = stripslashes( trim( $item_desc ) );
			if ( ! empty( $trimmed_item_desc ) ) {
				$trimmed_item_desc_array[] = $trimmed_item_desc;
			} // endif
		} // endfor
		$is_valid_item_array = ( count( $trimmed_item_desc_array ) > 0 );

		if ( Settings::get_is_include_join_mail_list_question() ) {
			$is_join = isset( $form_data['mail-list'] ) ? ( $form_data['mail-list'] == 'YES' ) : NULL;
			// when we ask the question they have to make a selection so NULL will cause an error message
		} else {
			$is_join = FALSE; // when we don't ask the question we'll insert false
		} // endif

		// Get or construct the visitor object
		if ( ! empty( $visitor_id ) ) {
			$visitor = Visitor::get_visitor_by_id( $visitor_id );
			$is_valid_email = empty( $visitor ) ? FALSE : TRUE; // if we have a visitor then we have valid email
		} else {
			$is_valid_email = empty( $email ) ? FALSE : filter_var( $email, FILTER_VALIDATE_EMAIL );
			// Try to find the visitor by email address
			if ( $is_valid_email ) {
				$visitor = Visitor::get_visitor_by_email( $email );
			} // endif
			// If we have no visitor then we'll need to create a new one
			if ( ! isset( $visitor ) ) {
				if ( ! empty( $full_name ) && ( $is_valid_email || $is_no_email ) && ( $is_join !== NULL ) ) {
					$public_name = Visitor::get_default_public_name( $full_name );
					$first_event_key = $is_first_time ? $event_key : NULL;
					$visitor_email = $is_no_email ? NULL : $email;
					$visitor = Visitor::create_visitor( $public_name, $full_name, $visitor_email, $first_event_key, $is_join );
				} // endif
			} // endif
		} // endif

		if ( ! $is_valid_nonce
				|| empty( $event )								// The event can't be found
				|| ! $is_valid_item_array					// The items are not valid
				|| empty( $visitor )						// Could not find or create the visitor record
				|| ! $is_rules_ack 				) {			// The visitor didn't acknowledged the rules

			// Event
			if ( $event === NULL ) {
				$form_response->add_error( '', '', __( 'No event was specified.', 'reg-man-rc'));
			} elseif ( $event === FALSE ) {
				$form_response->add_error( '', '', __( 'The specified event could not be found.', 'reg-man-rc'));
			} // endif

			// Items
			if ( ! $is_valid_item_array ) {
				$form_response->add_error( 'item-desc[]', '', __( 'Please enter a description and type for each item.', 'reg-man-rc'));
			} // endif

			if ( empty( $visitor ) ) {
				if ( ! empty( $visitor_id ) ) {
					$form_response->add_error( 'visitor-id', '', __( 'Could not find the specified visitor.', 'reg-man-rc'));

				} else {
					// Is first time
					if ( $is_first_time === NULL ) {
						$form_response->add_error( 'first-time', '', __( 'Please indicate whether this is your first time visiting the repair café.', 'reg-man-rc'));
					} // endif

					// Name
					if ( empty( $full_name ) ) {
						$form_response->add_error( 'full-name', '', __( 'Please enter your full name.', 'reg-man-rc'));
					} // endif

					// Email
					if ( ! $is_valid_email && ! $is_no_email ) {
						$form_response->add_error( 'email', '', __( 'Please enter a valid email address or check the box to indicate you have no email.', 'reg-man-rc'));
					} // endif

					// Is join
					if ( $is_join === NULL ) {
						$form_response->add_error( 'mail-list', '', __( 'Please indicate whether you would like to join our mailing list.', 'reg-man-rc'));
					} // endif
				} // endif
			} // endif

			// Rules
			if ( ! $is_rules_ack ) {
				$form_response->add_error('rules-ack', '', __( 'Please acknowledge that you have read the house rules and safety procedures.', 'reg-man-rc'));
			} // endif

		} else {

			$item_count = count( $trimmed_item_desc_array );
			$success_array = array(); // $reg_id => $item_desc (items that were added to the db)
			$failed_array = array(); // $item_desc (just an array of item descriptions for those that didn't go into the db)
			for ( $index = 0; $index < $item_count; $index++ ) { // we may have to insert multiple rows
				$item_desc = $trimmed_item_desc_array[ $index ];
				$item_type_id = isset( $item_type_array[ $index ] ) ? $item_type_array[ $index ] : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
				$item_type = Item_Type::get_item_type_by_id( $item_type_id );
				$station_id = isset( $fixer_station_array[ $index ] ) ? $fixer_station_array[ $index ] : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				$fixer_station = Fixer_Station::get_fixer_station_by_id( $station_id );
				$item = Item::create_new_item( $item_desc, $fixer_station, $item_type, $event_key, $visitor );
				$reg_id = isset( $item ) ? $item->get_id() : NULL;
//				Error_Log::var_dump( $reg_id );
				if ( ! empty( $reg_id ) ) {
					$success_array[ $reg_id ] = $item_desc;
				} else {
					$failed_array[] = $item_desc;
				} // endif
			} // endfor
			if ( ! empty( $failed_array ) ) {
				$separator = _x(', ', 'Separator used between items in a list, e.g. a, b, c, d');
				$failed_text = implode( $separator, $failed_array );
				/* translators: %s is replaced with a list of item names */
				$msg = sprintf(__( 'We were unable to register the following item(s):  %s', 'reg-man-rc' ), $failed_text);
				$form_response->add_error( '', '', $msg );
			} // endif
			if ( ! empty( $success_array ) ) {
				// We had at least one successful.  We'll let the table refresh itself so nothing more to do
			} // endif
			
			// Get the visitor and event so we can enforce the single active item rule
			$visitor = $item->get_visitor();
			$event_key = $item->get_event_key_string();

			if ( ! empty( $visitor ) && ! empty( $event_key ) ) {
				$visitor->enforce_single_active_item_rule( $event_key );
				
			} // endif
			
		} // endif
		return;
	} // function


	/**
	 * Get the datatable data
	 */
	public static function handle_get_visitor_items_list_priv() {

		$nonce		= isset( $_GET[ '_wpnonce' ] )		? $_GET[ '_wpnonce' ]		: '';
		$key		= isset( $_GET[ 'event_key' ] )		? $_GET[ 'event_key' ]		: '';
		$visitor_id = isset( $_GET[ 'visitor_id' ] )	? $_GET[ 'visitor_id' ]		: 0;
		
		$event = Event::get_event_by_key( $key );

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::GET_VISITOR_ITEMS_LIST_AJAX_ACTION );
//		$user_can_register = ! empty( $event ) ? $event->get_is_current_user_able_to_register_items() : FALSE;
		
		$visitor = ! empty( $visitor_id ) ? Visitor::get_visitor_by_id( $visitor_id ) : NULL;
		
		$result = array();

		
//		if ( ! $is_valid_nonce || empty( $event ) || empty( $visitor ) || ! $user_can_register ) {
		if ( ! $is_valid_nonce || empty( $event ) || empty( $visitor_id ) || empty( $visitor ) ) {
			
			$result[ 'data' ] = array();
		
			if ( ! $is_valid_nonce ) {

				$result[ 'error' ] = __( 'ERROR: Invalid security token.  Please refresh the page.', 'reg-man-rc' );

			} elseif ( empty( $key ) ) {

				$result[ 'error' ] = __( 'ERROR: Missing event key', 'reg-man-rc' );

			} elseif( empty( $event ) ) {
				
				$result[ 'error' ] = __( 'ERROR: Event not found', 'reg-man-rc' );

			} elseif( empty( $visitor ) && ! empty( $visitor_id ) ) {
				// Note that the visitor ID will be empty when the table is first loaded onto the page and
				// That is not an error, just return an empty set
				$result[ 'error' ] = __( 'ERROR: Visitor not found', 'reg-man-rc' );
/*
			} elseif( ! $user_can_register ) {
				
				$result[ 'error' ] = __( 'ERROR: You are not authorized to register visitors for this event', 'reg-man-rc' );
*/
			} // endif
			
		} else {

	//		Error_Log::var_dump( $_GET, $key, $event );
			$view = Single_Visitor_Details_View::create( $event, $visitor );
			$row_data = $view->get_visitor_item_list_data();
	
			$result[ 'data' ] = $row_data; // This is how datatables expects the result
			
		} // endif
		
		echo json_encode( $result );

		wp_die(); // THIS IS REQUIRED!
		
	} // function

	
	
	
	/**
	 * Handle a request to get a visitor's details
	 */
/*
	public static function handle_get_visitor_details_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		self::handle_form_ajax_post_get_visitor_details( $form_data_array, $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function
*/
	
	/**
	 * Handle a request to add an item to a visitor
	 */
	public static function handle_add_item_to_visitor_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		self::handle_form_ajax_post_add_visitor_item( $form_data_array, $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function


	/**
	 * Handle form past for adding an item to a visitor
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_post_add_visitor_item( $form_data, $form_response ) {

//		Error_Log::var_dump( $form_data );

		$nonce				= isset( $form_data[ '_wpnonce' ] )		? $form_data[ '_wpnonce' ] : '';
		$event_key			= isset( $form_data[ 'event-key'] )		? $form_data[ 'event-key' ]		: NULL;
		$visitor_id			= isset( $form_data[ 'visitor-id'] )	? $form_data[ 'visitor-id' ]	: NULL;
		$item_desc_array	= isset( $form_data[ 'item-desc' ] ) && is_array( $form_data[ 'item-desc' ] ) ? $form_data[ 'item-desc' ] : array();
		$item_type_array	= isset( $form_data[ 'item-type' ] ) && is_array( $form_data[ 'item-type' ] ) ? $form_data[ 'item-type' ] : array();
		$fixer_station_array= isset( $form_data[ 'fixer-station' ] ) && is_array( $form_data[ 'fixer-station' ] ) ? $form_data[ 'fixer-station' ] : array();

		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::ADD_ITEM_TO_VISITOR_AJAX_ACTION );
		
		if ( ! $is_valid_nonce ) {
			
			$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', __( $error_message, 'reg-man-rc' ) );
			return; // <== EXIT POINT!!!  There is no sense going on
			
		} // endif
				
		// Get the event
		$event = isset( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;

		// Get the visitor
		$visitor = isset( $visitor_id ) ? Visitor::get_visitor_by_id( $visitor_id ) : NULL;

		// Trim the item descriptions and then make sure that at least one was supplied
		$trimmed_item_desc_array = array(); // start with empty array
		$is_valid_item_array = TRUE; // assume it's ok and set to false if there are problems
		foreach( $item_desc_array as $item_desc ) {
			$trimmed_item_desc = stripslashes( trim( $item_desc ) );
			if ( ! empty( $trimmed_item_desc ) ) {
				$trimmed_item_desc_array[] = $trimmed_item_desc;
			} // endif
		} // endfor
		$is_valid_item_array = ( count( $trimmed_item_desc_array ) > 0 );

		if ( empty( $visitor) || empty( $event ) || ! $is_valid_item_array ) {

			// Event
			if ( $event === NULL ) {
				$form_response->add_error( 'event-key', $event_key, __( 'The specified event could not be found.', 'reg-man-rc'));
			} // endif

			// Items
			if ( ! $is_valid_item_array ) {
				$form_response->add_error( 'item-desc[]', '', __( 'Please enter a description and type for each item.', 'reg-man-rc'));
			} // endif

			if ( empty( $visitor ) ) {
				$form_response->add_error( 'visitor-id', $visitor_id, __( 'The visitor could not be found.', 'reg-man-rc'));
			} // endif
			
		} else {

			$item_count = count( $trimmed_item_desc_array );
			$success_array = array(); // $reg_id => $item_desc (items that were added to the db)
			$failed_array = array(); // $item_desc (just an array of item descriptions for those that didn't go into the db)
			for ( $index = 0; $index < $item_count; $index++ ) { // we may have to insert multiple rows
				$item_desc = $trimmed_item_desc_array[ $index ];
				$item_type_id = isset( $item_type_array[ $index ] ) ? $item_type_array[ $index ] : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
				$item_type = Item_Type::get_item_type_by_id( $item_type_id );
				$station_id = isset( $fixer_station_array[ $index ] ) ? $fixer_station_array[ $index ] : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				$fixer_station = Fixer_station::get_fixer_station_by_id( $station_id );
				$item = Item::create_new_item( $item_desc, $fixer_station, $item_type, $event_key, $visitor );
				$reg_id = isset( $item ) ? $item->get_id() : NULL;
//				Error_Log::var_dump( $reg_id );
				if ( ! empty( $reg_id ) ) {
					$success_array[ $reg_id ] = $item_desc;
				} else {
					$failed_array[] = $item_desc;
				} // endif
			} // endfor
			
			$visitor->enforce_single_active_item_rule( $event_key );
			
			if ( ! empty( $failed_array ) ) {
				$separator = _x(', ', 'Separator used between items in a list, e.g. a, b, c, d');
				$failed_text = implode( $separator, $failed_array );
				/* translators: %s is replaced with a list of item names */
				$msg = sprintf(__( 'We were unable to register the following item(s):  %s', 'reg-man-rc' ), $failed_text);
				$form_response->add_error( '', '', $msg );
			} // endif

		} // endif

		return;
	} // function

	/**
	 * Handle a request to add an item to a visitor
	 */
	public static function handle_get_item_update_content_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		self::handle_form_ajax_post_get_update_item_content( $form_data_array, $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle form post for updating an item
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_post_get_update_item_content( $form_data, $form_response ) {
	
		$nonce				= isset( $form_data[ '_wpnonce' ] )			? $form_data[ '_wpnonce' ]		: '';
		$item_id			= isset( $form_data[ 'item-id'] )			? $form_data[ 'item-id' ]		: NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::GET_ITEM_UPDATE_CONTENT_AJAX_ACTION );
		
		if ( ! $is_valid_nonce ) {
			
			$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', __( $error_message, 'reg-man-rc' ) );
			return; // <== EXIT POINT!!!  There is no sense going on
			
		} // endif
				
		// Get the item
		$item = isset( $item_id ) ? Item::get_item_by_id( $item_id ) : NULL;

		if ( empty( $item) ) {

			if ( $item === NULL ) {
				$form_response->add_error( 'item-id', $item_id, __( 'The specified item could not be found.', 'reg-man-rc'));
			} // endif
			
		} else {
			
			$view = Single_Item_Details_View::create( $item );
			$content = $view->get_update_item_form_contents();
			
			$form_response->set_html_data( $content );
			
		} // endif

	} // function
	
	
	
	/**
	 * Handle a request to update an item
	 */
	public static function handle_update_item_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		self::handle_form_ajax_post_update_item( $form_data_array, $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function


	/**
	 * Handle form post for updating an item
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_post_update_item( $form_data, $form_response ) {
		
		// There is already a form to create new visitor registrations.  I'm doing the same thing
		//  based on an item that's already registered.  So I'll just call the existing method to take
		//  care of this (RC_Reg_Visitor_Reg_Ajax_Form) but first I need to add visitor info
		//  from the existing registration, i.e. first/last name, email etc.

		$nonce				= isset( $form_data[ '_wpnonce' ] )			? $form_data[ '_wpnonce' ]		: '';
		$item_id			= isset( $form_data[ 'item-id'] )			? $form_data[ 'item-id' ]		: NULL;
		$fixer_station_id	= isset( $form_data[ 'fixer-station' ] )	? $form_data[ 'fixer-station' ] : NULL;
		$item_type_id		= isset( $form_data[ 'item-type' ] )		? $form_data[ 'item-type' ] 	: NULL;
		$item_status_id		= isset( $form_data[ 'item-status' ] )		? $form_data[ 'item-status' ]	: NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, Visitor_Registration_Controller::ITEM_UPDATE_AJAX_ACTION );
		
		if ( ! $is_valid_nonce ) {
			
			$error_message = __( 'ERROR: Security token expired.  Please refresh the page.' , 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', __( $error_message, 'reg-man-rc' ) );
			return; // <== EXIT POINT!!!  There is no sense going on
			
		} // endif
				
		// Get the item
		$item = isset( $item_id ) ? Item::get_item_by_id( $item_id ) : NULL;

		// Get the fixer station
		$fixer_station = isset( $fixer_station_id ) ? Fixer_Station::get_fixer_station_by_id( $fixer_station_id ) : NULL;

		// Get the item type
		$item_type = isset( $item_type_id ) ? Item_Type::get_item_type_by_id( $item_type_id ) : NULL;

		// Get the item status
		$item_status = isset( $item_status_id ) ? Item_Status::get_item_status_by_id( $item_status_id ) : NULL;

		if ( empty( $item) || empty( $fixer_station ) || empty( $item_type ) || empty( $item_status ) ) {

			// Event
			if ( $item === NULL ) {
				$form_response->add_error( 'item-id', $item_id, __( 'The specified item could not be found.', 'reg-man-rc'));
			} // endif

			if ( empty( $fixer_station ) ) {
				$form_response->add_error( 'fixer-station', $fixer_station_id, __( 'The fixer station could not be found.', 'reg-man-rc'));
			} // endif
			
			if ( empty( $item_type ) ) {
				$form_response->add_error( 'item-type', $item_type_id, __( 'The item type could not be found.', 'reg-man-rc'));
			} // endif
			
			if ( empty( $item_status ) ) {
				$form_response->add_error( 'item-status', $item_status_id, __( 'The item status could not be found.', 'reg-man-rc'));
			} // endif
			
		} else {

			$item->set_fixer_station( $fixer_station );
			$item->set_item_type( $item_type );
			$item->set_item_status( $item_status );
			
			// Get the visitor and event so we can enforce the single active item rule
			$visitor = $item->get_visitor();
			$event_key = $item->get_event_key_string();

			if ( ! empty( $visitor ) && ! empty( $event_key ) ) {
				$visitor->enforce_single_active_item_rule( $event_key );
				
				$after_enforce_item_status = $item->get_item_status();
				if ( isset( $after_enforce_item_status )  && $item_status_id !== $after_enforce_item_status->get_id() ) {
					// After enforcing the rule above, the item status is not what the user assigned so return a message
					$msg =  __( 'The status couldn\'t be updated because the visitor may have only 1 item in progress at a time.', 'reg-man-rc' );
					$form_response->add_error( 'item-status', $item_status_id, $msg );
				} // endif
				
			} // endif

		} // endif

		return;
		
	} // function

	/**
	 * Handle an AJAX post for a user who is not logged in
	 */
	public static function handle_ajax_no_priv() {
		$error = __( 'ERROR: You are not logged in or your session has expired.  Please reload the page and log in again.', 'reg-man-rc' );
		echo json_encode( array( 
				'data' => array(), // This is for the datatables row data (if requested)
				'success' => FALSE, // This is for my ajax requests
				'text' => __( 'ERROR', 'reg-man-rc' ), // This is for my ajax requests 
				'error' => $error ) // This is for both, mine and datatables
			);
		wp_die(); // THIS IS REQUIRED!
	} // function

} // class