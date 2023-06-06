<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\View\Admin\Supplemental_Event_Data_Admin_View;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Stats\Supplemental_Volunteer_Registration;
use Reg_Man_RC\Model\Stats\Supplemental_Item;
use Reg_Man_RC\Model\Stats\Supplemental_Visitor_Registration;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Item_Type;

/**
 * The supplemental event data controller
 *
 * This class provides the controller function for working with supplemental event data
 *
 * @since v0.1.0
 *
 */
class Supplemental_Event_Data_Admin_Controller {

	const UPDATE_ITEM_DATA_AJAX_ACTION = 'reg-man-rc-update-item-sup-data-ajax-action';
	const UPDATE_VISITOR_DATA_AJAX_ACTION = 'reg-man-rc-update-visitor-sup-data-ajax-action';
	const UPDATE_VOLUNTEER_DATA_AJAX_ACTION = 'reg-man-rc-update-volunteer-sup-data-ajax-action';
	
	public static function register() {

		$post_types = Supplemental_Event_Data_Admin_View::get_supported_post_types();
		foreach( $post_types as $post_type ) {
			// Add an action hook to upate our custom fields when a post is saved
			add_action( 'save_post_' . $post_type, array( __CLASS__, 'handle_post_save' ) );
		} // endfor

		// Add handler methods for ajax posts
		add_action( 'wp_ajax_' . self::UPDATE_ITEM_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_update_item_data') );
		add_action( 'wp_ajax_nopriv_' . self::UPDATE_ITEM_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );
		
		add_action( 'wp_ajax_' . self::UPDATE_VISITOR_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_update_visitor_data') );
		add_action( 'wp_ajax_nopriv_' . self::UPDATE_VISITOR_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );
		
		add_action( 'wp_ajax_' . self::UPDATE_VOLUNTEER_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_update_volunteer_data') );
		add_action( 'wp_ajax_nopriv_' . self::UPDATE_VOLUNTEER_DATA_AJAX_ACTION, array(__CLASS__, 'handle_ajax_no_priv') );
		
	} // function

	/**
	 * Handle ajax update for supplemental item data.
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_ajax_update_item_data() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		if ( wp_verify_nonce( $nonce, self::UPDATE_ITEM_DATA_AJAX_ACTION ) ) {
			self::handle_update_item_data( $form_data, $form_response );
		} else {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} // endif
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle ajax update for supplemental visitor data.
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_ajax_update_visitor_data() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		if ( wp_verify_nonce( $nonce, self::UPDATE_VISITOR_DATA_AJAX_ACTION ) ) {
			self::handle_update_visitor_data( $form_data, $form_response );
		} else {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} // endif
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle ajax update for supplemental volunteer data.
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_ajax_update_volunteer_data() {
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		// The nonce is a hidden field in the form so check it first
		$form_response = Ajax_Form_Response::create();
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		if ( wp_verify_nonce( $nonce, self::UPDATE_VOLUNTEER_DATA_AJAX_ACTION ) ) {
			self::handle_update_volunteer_data( $form_data, $form_response );
		} else {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} // endif
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
	} // function

	/**
	 * Handle update for supplemental visitor data.
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	private static function handle_update_visitor_data( $form_data, $form_response = NULL ) {
		$event_key = isset( $form_data[ 'sup_data_visitor_event_key' ] ) ? wp_unslash( $form_data[ 'sup_data_visitor_event_key' ] ) : NULL;
		$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
		if ( empty( $event ) && isset( $form_response ) ) {
			$form_response->add_error( '', '', __( 'The event was not found.', 'reg-man-rc'));
		} else {
			$first_time = isset( $form_data[ 'first_time_visitors' ] ) ? $form_data[ 'first_time_visitors' ] : 0;
			$returning = isset( $form_data[ 'returning_visitors' ] ) ? $form_data[ 'returning_visitors' ] : 0;
			$unknown = isset( $form_data[ 'unknown_visitors' ] ) ? $form_data[ 'unknown_visitors' ] : 0;
			$result = Supplemental_Visitor_Registration::set_supplemental_visitor_reg_counts( $event_key, $first_time, $returning, $unknown );
			if ( ( $result === FALSE ) && ( isset( $form_response ) ) ) {
				$form_response->add_error( '', '', __( 'The data could not be updated.', 'reg-man-rc'));
			} // endif
		} // endif
	} // function

	/**
	 * Handle update for supplemental item data.
	 *
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	private static function handle_update_item_data( $form_data, $form_response = NULL ) {
		$event_key = isset( $form_data['sup_data_item_event_key'] ) ? wp_unslash( $form_data['sup_data_item_event_key'] ) : NULL;
		$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
//		Error_Log::var_dump( $form_data[ 'fixed_count' ][ 21 ] );
		if ( ! empty( $event ) ) {
			$fixed_count_array		= isset( $form_data[ 'fixed_count'] )		? $form_data[ 'fixed_count']		: array();
			$repairable_count_array	= isset( $form_data[ 'repairable_count'] )	? $form_data[ 'repairable_count']	: array();
			$eol_count_array		= isset( $form_data[ 'eol_count'] )			? $form_data[ 'eol_count']			: array();
			$unknown_count_array	= isset( $form_data[ 'unknown_count'] )		? $form_data[ 'unknown_count']		: array();

			$all_station_ids = array_keys( Fixer_Station::get_all_fixer_stations() );
			$all_station_ids[] = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID; // Add unspecified
			$all_type_ids = array_keys( Item_Type::get_all_item_types() );
			$all_type_ids[] = Item_Type::UNSPECIFIED_ITEM_TYPE_ID; // Add unspecified
//			Error_Log::var_dump( $all_station_ids, $all_type_ids );
			
			foreach( $all_station_ids as $station_id ) {
				// Update this station for all types
				foreach( $all_type_ids as $type_id ) {
					$fixed_count = isset( $fixed_count_array[ $station_id ][ $type_id ] ) ? $fixed_count_array[ $station_id ][ $type_id ] : 0;
					$repairable_count = isset( $repairable_count_array[ $station_id ][ $type_id ] ) ? $repairable_count_array[ $station_id ][ $type_id ] : 0;
					$eol_count = isset( $eol_count_array[ $station_id ][ $type_id ] ) ? $eol_count_array[ $station_id ][ $type_id ] : 0;
					$unknown_count = isset( $unknown_count_array[ $station_id ][ $type_id ] ) ? $unknown_count_array[ $station_id ][ $type_id ] : 0;
					Supplemental_Item::set_supplemental_item_counts( $event_key, $type_id, $station_id, $fixed_count, $repairable_count, $eol_count, $unknown_count );
				} // endfor
				
			} // endfor
			

		} // endif
	} // function

	/**
	 * Handle update for supplemental volunteer data.
	 * @param	string[]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	private static function handle_update_volunteer_data( $form_data, $form_response = NULL ) {
		$event_key = isset( $form_data['sup_data_volunteer_event_key'] ) ? wp_unslash( $form_data['sup_data_volunteer_event_key'] ) : NULL;
		$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
//		Error_Log::var_dump( $form_data );
		if ( ! empty( $event ) ) {
		
			// Update Fixers
			$all_station_ids = array_keys( Fixer_Station::get_all_fixer_stations() );
			$all_station_ids[] = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID; // Add unspecified station
			
			$fixer_count_array = isset( $form_data[ 'fixer_head_count' ] ) ? $form_data[ 'fixer_head_count' ] : array();
			$appr_count_array = isset( $form_data[ 'apprentice_head_count' ] ) ? $form_data[ 'apprentice_head_count' ] : array();

			// Update each fixer station
			foreach ( $all_station_ids as $station_id ) {
				$fixer_count = isset( $fixer_count_array[ $station_id ] ) ? $fixer_count_array[ $station_id ] : 0;
				$appr_count = isset( $appr_count_array[ $station_id ] ) ? $appr_count_array[ $station_id ] : 0;
				Supplemental_Volunteer_Registration::set_supplemental_fixer_count( $event_key, $station_id, $fixer_count, $appr_count );
			} // endfor

			// Update Non-fixers
			$all_role_ids = array_keys( Volunteer_Role::get_all_volunteer_roles() );
			$all_role_ids[] = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID; // Add unspecified
			
			$volunteer_head_count = isset( $form_data[ 'non_fixer_head_count' ] ) ? $form_data[ 'non_fixer_head_count' ] : array();

			// Update each volunteer role
			foreach ( $all_role_ids as $role_id ) {
				$head_count = isset( $volunteer_head_count[ $role_id ] ) ? $volunteer_head_count[ $role_id ] : 0;
				Supplemental_Volunteer_Registration::set_supplemental_non_fixer_count( $event_key, $role_id, $head_count );
			} // endfor

		} // endif
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

			// Update the supplemental visitor data if it was supplied
			self::handle_update_visitor_data( $_POST );

			// Update the item data
			self::handle_update_item_data( $_POST );

			// Update the volunteer data
			self::handle_update_volunteer_data( $_POST );

		} // endif

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