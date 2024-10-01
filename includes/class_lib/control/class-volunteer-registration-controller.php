<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\View\Volunteer_Registration_Form;
use Reg_Man_RC\Model\Error_Log;

/**
 * The volunteer registration controller
 *
 * This class provides the controller function associated with Volunteer_Registration objects.
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_Controller {

	const AJAX_VOLUNTEER_REGISTRATION_ACTION	= 'reg_man_rc_volunteer_registration_ajax';
	const AJAX_VOLUNTEER_PREFERENCES_ACTION		= 'reg_man_rc_volunteer_reg_preferences_ajax';

	/**
	 *  Register the Volunteer Registration controller.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
		
		// Add handler methods for my form posts.
		// Note that these can be posted by logged-in users or non-logged-in users
		add_action( 'wp_ajax_' . self::AJAX_VOLUNTEER_REGISTRATION_ACTION, array(__CLASS__, 'handle_registration_form_post' ) );
		add_action( 'wp_ajax_nopriv_'  . self::AJAX_VOLUNTEER_REGISTRATION_ACTION, array(__CLASS__, 'handle_registration_form_post' ) );

		add_action( 'wp_ajax_' . self::AJAX_VOLUNTEER_PREFERENCES_ACTION, array(__CLASS__, 'handle_preferences_form_post' ) );
		add_action( 'wp_ajax_nopriv_'  . self::AJAX_VOLUNTEER_PREFERENCES_ACTION, array(__CLASS__, 'handle_preferences_form_post' ) );

	} // function

/**
 * Handle a post save event for my post type
 *
 * @param	int			$post_id	The ID of the post being saved
 * @param	\WP_Post	$post		The post being saved
 * @param	boolean		$is_update	TRUE if the post is being updated, FALSE if it's a new post
 * @return	void
 *
 * @since v0.1.0
 *
 */
	public static function handle_post_save( $post_id, $post, $is_update ) {
	
//		Error_Log::var_dump( $is_update, $post_id );
		
		// When we're creating a new post, I'll set the post title to the post ID and possibly assign the volunteer
		if ( ! $is_update ) {
			
			// To make sure we don't have an infinite loop, I need to unhook this function
			remove_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

			// Update the post so the title is the ID of this record
			wp_update_post( array(
					'ID'			=> $post_id,
					'post_title'	=> $post_id
			));
			
			// Now add the action back
			add_action( 'save_post_' . Volunteer_Registration::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
			
			// If this WP user is a volunteer who is NOT allowed to read private volunteer registrations
			// then we will assume the volunteer is creating a registration record for herself
			// In that case, we will assign the volunteer, fixer station and volunteer roles to this new record
			if ( ! current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {
				
				$volunteer = Volunteer::get_volunteer_for_current_wp_user();
				if ( ! empty( $volunteer ) ) {
					
					$volunteer_id = $volunteer->get_id();
					update_post_meta( $post_id, Volunteer_Registration::VOLUNTEER_META_KEY, $volunteer_id );
					
					$station = $volunteer->get_preferred_fixer_station();
					if ( ! empty( $station ) ) {
						Fixer_Station::set_fixer_stations_for_post( $post_id, array( $station ) );
					} // endif
					
					$volunteer_roles = $volunteer->get_preferred_roles();
					if ( ! empty( $volunteer_roles ) ) {
						Volunteer_Role::set_volunteer_roles_for_post( $post_id, $volunteer_roles );
					} // endif
					
				} // endif
				
			} // endif
			
		} // endif
		
	} // function
	
	/**
	 * Handle a registration form post
	 */
	public static function handle_registration_form_post() {

		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

//		Error_Log::var_dump( $form_data );

		$ajax_response = Ajax_Form_Response::create();

		$nonce				= isset( $form_data[ '_wpnonce' ] )			? $form_data[ '_wpnonce' ]				: NULL;
		$is_quick_signup	= isset( $form_data[ 'is-quick-signup' ] );
		$event_key			= isset( $form_data[ 'event-key' ] )		? trim( $form_data[ 'event-key' ] )		: NULL;
		$register_val		= isset( $form_data[ 'is-register' ] )		? trim( $form_data[ 'is-register' ] )	: NULL;
		$target_vol_id		= isset( $form_data[ 'volunteer-id' ] )		? trim( $form_data[ 'volunteer-id' ] )	: NULL;
		$is_use_defaults	= isset( $form_data[ 'use-volunteer-defaults' ] ) && ( $form_data[ 'use-volunteer-defaults' ] == '1' ) ;

		// We don't currently have comments on the registration form
//		$comments		= isset( $form_data[ 'comments' ] )		? trim( $form_data[ 'comments' ] )	: NULL;

		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_VOLUNTEER_REGISTRATION_ACTION );

		$current_volunteer = Volunteer::get_volunteer_for_current_request();
		$event = isset( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
		// The target volunteer for the registration is assigned in the volunteer-id post data
		// The current volunteer must either be that volunteer or be a proxy for that volunteer
		$target_volunteer = NULL;
		if ( $current_volunteer->get_id() == $target_vol_id ) {
			$target_volunteer = $current_volunteer;
		} else {
			$proxies = $current_volunteer->get_proxy_for_array();
			foreach( $proxies as $proxy ) {
				if ( $target_vol_id == $proxy->get_id() ) {
					$target_volunteer = $proxy; // The current volunteer can act as a proxy for the target
					break;
				} // endif
			} // endfor
		} // endif

		if ( ! $is_valid_nonce || empty( $current_volunteer ) || empty( $target_volunteer ) || empty( $event ) || ( $register_val === NULL ) ) {

			if ( ! $is_valid_nonce ) {
				$error_msg = __( 'Invalid or missing security code.  Please refresh this page and try again.', 'reg-man-rc' );
				$ajax_response->add_error( '_wpnonce', $nonce, $error_msg );
			} // endif

			if( empty( $current_volunteer ) ) {
				$error_msg = __( 'Volunteer not found.  Please exit the area and log in again. ', 'reg-man-rc' );
				$ajax_response->add_error( '', NULL, $error_msg );
			} // endif

			if( empty( $target_volunteer ) ) {
				$error_msg = __( 'You are not authorized to register that volunteer. ', 'reg-man-rc' );
				$ajax_response->add_error( 'volunteer-id', NULL, $error_msg );
			} // endif

			if( empty( $event ) ) {
				$error_msg = __( 'The event was not found.  Please try again. ', 'reg-man-rc' );
				$ajax_response->add_error( 'event_key', $event_key, $error_msg );
			} // endif

			if( $register_val === NULL ) {
				$error_msg = __( 'Please select an attendance option.', 'reg-man-rc' );
				$ajax_response->add_error( 'is-register', '', $error_msg );
			} // endif

		} else {

			// For a quick registration, there are no selection options for fixer station etc.
			// In that case $is_use_defaults is TRUE and I will assign based on the volunteer's preferences
			// Except in the case of non-repair events in which case we always assign NULL for station and roles
			$is_non_repair = $event->get_event_descriptor()->get_event_is_non_repair();

			// Fixer stations
			if ( $is_non_repair ) {
				$fixer_station = NULL;
			} elseif ( isset( $form_data[ 'station-id' ] ) ) {
				$station_id = trim( $form_data[ 'station-id' ] );
				$fixer_station = isset( $station_id ) ? Fixer_Station::get_fixer_station_by_id( $station_id ) : NULL;
			} else {
				$fixer_station = $is_use_defaults ? $target_volunteer->get_preferred_fixer_station() : NULL;
			} // endif

			// Is apprentice?
			if ( $is_non_repair ) {
				$is_apprentice = NULL;
			} elseif ( isset( $form_data[ 'is-apprentice' ] ) ) {
				$is_apprentice	= TRUE;
			} else {
				$is_apprentice = $is_use_defaults ? $target_volunteer->get_is_fixer_apprentice() : NULL;
			} // endif

			// Volunteer roles
			if ( $is_non_repair ) {
				$roles = NULL;
			} elseif ( isset( $form_data[ 'role-id' ] ) ) {
				$role_id_array = $form_data[ 'role-id' ];
				$roles = array();
				foreach( $role_id_array as $volunteer_role_id ) {
					$role = Volunteer_Role::get_volunteer_role_by_id( $volunteer_role_id );
					if ( isset( $role ) ) {
						$roles[] = $role;
					} // endif
				} // endfor
			} else {
				$roles = $is_use_defaults ? $target_volunteer->get_preferred_roles() : NULL;
			} // endif

			// Comments
			$volunteer_comments = isset( $form_data[ 'volunteer-comments' ] ) ? trim( $form_data[ 'volunteer-comments' ] ) : NULL;
			
			
			// Get the current volunteer registration record if one exists
			$vol_reg = Volunteer_Registration::get_registration_for_volunteer_and_event( $target_volunteer, $event_key );

			$is_register = ( isset( $register_val ) && ( $register_val == '1' ) );

//			Error_Log::var_dump( $register_val, $is_register, $event_key );

			if ( $is_register ) {

				// We are adding or updating a registration record

				if ( isset( $vol_reg ) ) {
					
					// Updating
					$vol_reg->set_fixer_station( $fixer_station );
					$is_apprentice = ! empty( $fixer_station ) ? $is_apprentice : FALSE; // not an apprentice if no station
					$vol_reg->set_is_fixer_apprentice( $is_apprentice );
					$vol_reg->set_volunteer_roles_array( $roles );
					$vol_reg->set_volunteer_registration_comments( $volunteer_comments );
					
				} else {
					
					// Adding a new registration
					Volunteer_Registration::create_new_registration( $target_volunteer, $event, $roles, $fixer_station, $is_apprentice, $volunteer_comments );
					
				} // endif

			} else {

				// We are removing a registration record if it exists
				if ( isset( $vol_reg ) ) {
					Volunteer_Registration::delete_volunteer_registration( $vol_reg );
				} // endif

			} // endif

			// For quick signup we're done
			// For regular posts we will load the new ajax form content into the response
			if ( ! $is_quick_signup ) {
				$vol_reg_form = Volunteer_Registration_Form::create( $target_volunteer, $event );
				$content = $vol_reg_form->get_form_content( $is_open = TRUE );
				$ajax_response->set_html_data( $content );
			} // endif

		} // endif

		echo json_encode( $ajax_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function

	/**
	 * Handle update of volunteer preferences form post
	 */
	public static function handle_preferences_form_post() {

		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

//		Error_Log::var_dump( $form_data );

		$ajax_response = Ajax_Form_Response::create();

		$nonce			= isset( $form_data[ '_wpnonce' ] )		? $form_data[ '_wpnonce' ]			: NULL;

		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_VOLUNTEER_PREFERENCES_ACTION );
		$current_volunteer = Volunteer::get_volunteer_for_current_request();

		if ( ! $is_valid_nonce || empty( $current_volunteer ) ) {

			if ( ! $is_valid_nonce ) {
				$error_msg = __( 'Invalid or missing security code.  Please refresh this page and try again.', 'reg-man-rc' );
				$ajax_response->add_error( '_wpnonce', $nonce, $error_msg );
			} // endif

			if( empty( $current_volunteer ) ) {
				$error_msg = __( 'Volunteer not found.  Please exit the area and log in again. ', 'reg-man-rc' );
				$ajax_response->add_error( 'volunteer', NULL, $error_msg );
			} // endif

		} else {

			// Fixer stations
			if ( isset( $form_data[ 'station-id' ] ) ) {
				$station_id = trim( $form_data[ 'station-id' ] );
				$fixer_station = isset( $station_id ) ? Fixer_Station::get_fixer_station_by_id( $station_id ) : NULL;
			} else {
				$fixer_station = NULL;
			} // endif

			// Is apprentice?
			$is_apprentice = ( isset( $form_data[ 'is-apprentice' ] ) );

			// Volunteer roles
			if ( isset( $form_data[ 'role-id' ] ) ) {
				$role_id_array	= $form_data[ 'role-id' ];
				$roles = array();
				foreach( $role_id_array as $volunteer_role_id ) {
					$role = Volunteer_Role::get_volunteer_role_by_id( $volunteer_role_id );
					if ( isset( $role ) ) {
						$roles[] = $role;
					} // endif
				} // endfor
			} else {
				$roles = NULL;
			} // endif

			$current_volunteer->set_preferred_fixer_station( $fixer_station );
			$current_volunteer->set_is_fixer_apprentice( $is_apprentice );
			$current_volunteer->set_preferred_roles( $roles );

		} // endif

		echo json_encode( $ajax_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function



} // class