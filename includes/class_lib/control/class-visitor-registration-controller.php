<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
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
use Reg_Man_RC\View\Editable\Editable_Item_Type;
use Reg_Man_RC\View\Editable\Editable_Item_Status;

/**
 * The visitor registration controller
 *
 * This class provides the controller function for working with visitor registration
 *
 * @since v0.1.0
 *
 */
class Visitor_Registration_Controller {

	const DATATABLE_LOAD_AJAX_ACTION = 'reg-man-rc-visitor-list-view-ajax-datatable-load';

	const REGISTER_ITEM_AJAX_ACTION = 'reg-man-rc-register-item-ajax-action';

	const ITEM_STATUS_UPDATE_AJAX_ACTION = 'reg-man-rc-visitor-reg-status-update-ajax-action';

	const ITEM_TYPE_UPDATE_AJAX_ACTION = 'reg-man-rc-visitor-reg-item-type-update-ajax-action';

	const FIXER_STATION_UPDATE_AJAX_ACTION = 'reg-man-rc-visitor-reg-fixer-station-update-ajax-action';

	const EVENT_SELECT_FORM_POST_ACTION = 'reg-man-rc-visitor-reg-manager-event-select-post';

	const AJAX_NEW_VISITOR_REG_ACTION = 'reg_man_rc_visitor_ajax_reg';

	const AJAX_ADD_ITEM_TO_VISITOR_ACTION = 'rc_reg_add_item_to_visitor_ajax';


	public static function register() {

		// Add handler methods for my form posts. This is only used when the user must select an event
		// Handle event selection, Logged-in users (Priv) and not logged in (NoPriv)
		add_action( 'admin_post_' . self::EVENT_SELECT_FORM_POST_ACTION, array(__CLASS__, 'handle_event_select_form_post_priv') );
		add_action( 'admin_post_nopriv_'  . self::EVENT_SELECT_FORM_POST_ACTION, array(__CLASS__, 'handle_event_select_form_post_no_priv') );

		// Add handler methods for AJAX datatable load
		add_action( 'wp_ajax_' . self::DATATABLE_LOAD_AJAX_ACTION, array(__CLASS__, 'handle_datatable_load_ajax_get_priv') );
		add_action( 'wp_ajax_nopriv_' . self::DATATABLE_LOAD_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for register item
		add_action( 'wp_ajax_' . self::REGISTER_ITEM_AJAX_ACTION, array(__CLASS__, 'handle_register_pre_registered_item_priv') );
		add_action( 'wp_ajax_nopriv_' . self::REGISTER_ITEM_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for status update
		add_action( 'wp_ajax_' . self::ITEM_STATUS_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_item_status_update_priv') );
		add_action( 'wp_ajax_nopriv_' . self::ITEM_STATUS_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for fixer station update
		add_action( 'wp_ajax_' . self::ITEM_TYPE_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_item_type_update_priv') );
		add_action( 'wp_ajax_nopriv_' . self::ITEM_TYPE_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler methods for fixer station update
		add_action( 'wp_ajax_' . self::FIXER_STATION_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_fixer_station_update_priv') );
		add_action( 'wp_ajax_nopriv_' . self::FIXER_STATION_UPDATE_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handlers for new visitor registrations
		add_action( 'wp_ajax_' . self::AJAX_NEW_VISITOR_REG_ACTION, array(__CLASS__, 'handle_new_registration_priv') );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_NEW_VISITOR_REG_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

		// Add handler for adding items to a visitor
		add_action( 'wp_ajax_' . self::AJAX_ADD_ITEM_TO_VISITOR_ACTION, array(__CLASS__, 'handle_add_item_to_visitor_priv' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_NEW_VISITOR_REG_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );

	} // function

	public static function handle_event_select_form_post_no_priv() {
		// You must be logged in to post data.  This person is not logged in (hence ...NoPriv)
		//	so just send them back to where they came from (the form page) it will require login
		$ref_page = $_SERVER['HTTP_REFERER']; // The referer page is where the request came from
		header("Location: $ref_page");
	} // function

	public static function handle_event_select_form_post_priv() {
		// This user is logged in so go ahead and handle this request
		self::handle_event_select_form_post();
	} // function

	private static function handle_event_select_form_post() {
		// When a post occurs it's because the user has selected an event
		// This only happens when no event was specified for the page, or the event can't be found
		// Get the event key from the post data then forward to this registration page with the event id specified

		$key_string = ( isset( $_POST[ 'event-select' ] ) ) ? wp_unslash( $_POST[ 'event-select' ] ) : '';
		$target_page = Visitor_Reg_Manager::get_event_registration_href( $key_string );
		header( "Location: $target_page" );

	} // function

	public static function handle_datatable_load_ajax_get_priv() {
		$key = isset( $_GET[ 'event_key' ]) ? $_GET[ 'event_key' ] : '';
		$event = ( $key === ' ') ? NULL : Event::get_event_by_key( $key );
//		Error_Log::var_dump( $_GET, $key, $event );
		$row_data = ( $event === NULL ) ? array() : Visitor_List_View::get_registration_data( $event );
		$result = array( 'data' => $row_data ); // This is how datatables expects the result
		echo json_encode($result);
		wp_die(); // THIS IS REQUIRED!
	} // function

	public static function handle_register_pre_registered_item_priv() {
/* FIXME - this needs to be implemented
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset($_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		$id = isset( $form_data_array[ 'register-item' ] ) ? $form_data_array[ 'register-item' ] : '';
		$status = Item_Status::REGISTERED;
		$result = RC_Reg_Visitor_Reg::updateVisitorRegStatus($id, $status); // FIXME!!!
		$response = ( $result === FALSE ) ? FALSE : TRUE; // Return TRUE (usually) or FALSE on error
		echo json_encode($response);
		wp_die(); // THIS IS REQUIRED!
*/
	} // function

	public static function handle_item_status_update_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset($_POST['formData']) ? $_POST['formData'] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array);
		$item_id = isset( $form_data_array[ 'item-id' ] ) ? $form_data_array[ 'item-id' ] : '';
		$item = Item::get_item_by_id( $item_id );
		$status_id = isset( $form_data_array[ 'item-status' ] ) ? $form_data_array[ 'item-status' ] : '';
		$item_status = Item_Status::get_item_status_by_id( $status_id );
//		$result = isset( $item ) ? $item->set_status( $item_status ) : FALSE;
//		$response = ($result === FALSE) ? FALSE : TRUE; // Return TRUE (usually) or FALSE on error
		if ( ! isset( $item ) ) {
			$response = ''; // There's no item so no result;
		} else {
			$item->set_status( $item_status );
			$response = Editable_Item_Status::get_display_value_for( $item_status );
		} // endif
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
		$item_id = isset( $form_data_array[ 'item-id' ] ) ? $form_data_array[ 'item-id' ] : '';
		$item = Item::get_item_by_id( $item_id );
		$item_type_id = isset( $form_data_array[ 'item-type' ] ) ? $form_data_array[ 'item-type' ] : '';
		$item_type = Item_Type::get_item_type_by_id( $item_type_id );
		if ( ! isset( $item ) ) {
			$response = ''; // There's no item so no result;
		} else {
			$item->set_item_type( $item_type );
			$response = $item_type->get_name();
		} // endif
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
		$item_id = isset( $form_data_array[ 'object-id' ] ) ? $form_data_array[ 'object-id' ] : '';
		$item = Item::get_item_by_id( $item_id );
		$fixer_station_id = isset( $form_data_array[ 'fixer-station' ] ) ? $form_data_array[ 'fixer-station' ] : '';
		$fixer_station = Fixer_Station::get_fixer_station_by_id( $fixer_station_id );
		if ( ! isset( $item ) ) {
			$response = ''; // There's no item so no result;
		} else {
			$item->set_fixer_station( $fixer_station );
			$response = $fixer_station->get_name();
		} // endif
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
//		$nonce = isset($form_data_array['ajax-nonce']) ? $form_data_array['ajax-nonce'] : NULL;
//		if (wp_verify_nonce($nonce, self::AJAX_NONCE_STR)) {
			self::handle_form_ajax_new_visitor_registration_post( $form_data_array, $form_response );
//		} else {
//			$form_response->add_error('', $nonce, "Invalid or missing security code: \"$nonce\"");
//		} // endif
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle new registration post
	 * @param string[]				$form_data
	 * @param Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_new_visitor_registration_post( $form_data, $form_response ) {
		// 	$nonce = wp_create_nonce( self::AJAX_NONCE_STR );
		//  echo "<input type=\"hidden\" name=\"ajax-nonce\" value=\"$nonce\">";

		// event-key - must represent an event in the db
		// item-desc, item-type arrays - at least 1
		// email - if empty then no-email must exist, use NULL for email in that case
		// full-name - required
		// first-time - must be present, either yes or no
		// join-list - if the setting to include this question is on then it must be present, either yes or no
		// rules-ack - must exist

//		Error_Log::var_dump( $form_data );
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

//		Error_Log::var_dump( $form_data, $event, $visitor, $trimmed_item_desc_array );
//				$form_response->add_error( '', '', __( 'TESTING', 'reg-man-rc' ) );
//		return;

		if ( empty( $event )								// The event can't be found
				|| ! $is_valid_item_array					// The items are not valid
				|| empty( $visitor )						// Could not find or create the visitor record
//				|| empty( $is_first_time )					// Is first time was not checked yes or no
//				|| empty( $full_name )						// Name not supplied
//				|| ( ! $is_valid_email && ! $is_no_email ) 	// The email is not valid
//				|| ( $is_join === NULL )					// Join mail list expected but not found
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
		} // endif
		return;
	} // function


	public static function handle_add_item_to_visitor_priv() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
//		$nonce = isset($form_data_array['ajax-nonce']) ? $form_data_array['ajax-nonce'] : NULL;
//		if (wp_verify_nonce($nonce, self::AJAX_NONCE_STR)) {
			self::handle_form_ajax_post_add_visitor_item( $form_data_array, $form_response );
//		} else {
//			$form_response->addError('', $nonce, "Invalid or missing security code: \"$nonce\"");
//		} // endif
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function


	/**
	 * Handle form past for adding an item to a visitor
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 */
	private static function handle_form_ajax_post_add_visitor_item($form_data, $form_response) {
		//	$nonce = wp_create_nonce( self::AJAX_NONCE_STR );
		//	echo "<input type=\"hidden\" name=\"ajax-nonce\" value=\"$nonce\">";

		// There is already a form to create new visitor registrations.  I'm doing the same thing
		//  based on an item that's already registered.  So I'll just call the existing method to take
		//  care of this (RC_Reg_Visitor_Reg_Ajax_Form) but first I need to add visitor info
		//  from the existing registration, i.e. first/last name, email etc.

		$event_key			= isset( $form_data[ 'event-key'] )		? $form_data[ 'event-key' ]		: NULL;
		$visitor_id			= isset( $form_data[ 'visitor-id'] )	? $form_data[ 'visitor-id' ]	: NULL;
		$item_desc_array	= isset( $form_data[ 'item-desc' ] ) && is_array( $form_data[ 'item-desc' ] ) ? $form_data[ 'item-desc' ] : array();
		$item_type_array	= isset( $form_data[ 'item-type' ] ) && is_array( $form_data[ 'item-type' ] ) ? $form_data[ 'item-type' ] : array();
		$fixer_station_array= isset( $form_data[ 'fixer-station' ] ) && is_array( $form_data[ 'fixer-station' ] ) ? $form_data[ 'fixer-station' ] : array();

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
				$item_type_id = isset( $item_type_array[ $index ] ) ? isset( $item_type_array[ $index ] ) : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
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
	 * Handle an AJAX post for a user who is not logged in
	 */
	public static function handle_ajax_no_priv() {
		$error = array( __( 'ERROR', 'reg-man-rc'), __( 'You are not logged in or your session has expired', 'reg-man-rc'),
				__( 'Please reload the page and log in again', 'reg-man-rc'), '');
		echo json_encode(array('data' => array($error)));
		wp_die(); // THIS IS REQUIRED!
	} // function

} // class