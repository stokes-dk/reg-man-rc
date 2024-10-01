<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;

/**
 * The volunteer controller
 *
 * This class provides the controller function associated with the Volunteer custom post type.
 * It handles AJAX POST operations for things like volunteer login.
 * When getting a Volunteer from the database we must join our side table that contains the
 *  volunteer's personal data including full name and email address.
 *  This data is sensitive and is not stored in the main WP posts table.
 *
 * @since v0.1.0
 *
 */
class Volunteer_Controller {

	const AJAX_VOLUNTEER_LOGIN_ACTION	= 'reg_man_rc_volunteer_login_ajax';
	const VOLUNTEER_AREA_EXIT_ACTION	= 'reg_man_rc_volunteer_area_exit';

	/**
	 *  Register the Volunteer controller.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// Add handler methods for my form posts etc.
		// Handle user login post.
		// This is not a normal login form because volunteers may identify themselves by email only, and may not have a password
		// If a logged-in user, like the system administrator is also be a volunteer
		// So we need to accept both "priv" and "no_priv" posts.
		// This is normally done by a non-logged-in user.
		add_action( 'wp_ajax_' . self::AJAX_VOLUNTEER_LOGIN_ACTION, array(__CLASS__, 'handle_login_form_post' ) );
		add_action( 'wp_ajax_nopriv_'  . self::AJAX_VOLUNTEER_LOGIN_ACTION, array(__CLASS__, 'handle_login_form_post' ) );

		// Add handler for area exit action.  Users just supply an email so they don't really log out
		// The action is set up using admin_post but it's really a GET triggered by clicking a link
		$action = self::VOLUNTEER_AREA_EXIT_ACTION;
		add_action( "admin_post_{$action}", array( __CLASS__, 'handle_volunteer_area_exit_action' ) );
		add_action( "admin_post_nopriv_{$action}", array( __CLASS__, 'handle_volunteer_area_exit_action' ) );

	} // function

	/**
	 * Get the href for a link that allows the volunteer to exit the area
	 * @return string
	 */
	public static function get_volunteer_area_exit_href() {
		$action = self::VOLUNTEER_AREA_EXIT_ACTION;
		$result = admin_url( "admin-post.php?action={$action}" );
		return $result;
	} // function

	/**
	 * Handle the post of the volunteer login form from the volunteer area.
	 * This may be a login (which includes a password) or non-login which is just an email address.
	 * If an email address is specified and that address corresponds to a volunteer
	 */
	public static function handle_login_form_post() {

		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
//		Error_Log::var_dump( $form_data_array );
		$ajax_response = Ajax_Form_Response::create();
		$email			= isset( $form_data_array[ 'vol-email' ] )		? trim( $form_data_array[ 'vol-email' ] )	: NULL;
		$pwd			= isset( $form_data_array[ 'vol-pwd' ] )		? trim( $form_data_array[ 'vol-pwd' ] )		: NULL;
		$is_remember	= isset( $form_data_array[ 'is-remember' ] )	? $form_data_array[ 'is-remember' ] == '1'	: FALSE;
		$event_key		= isset( $form_data_array[ 'event-key' ] )		? trim( $form_data_array[ 'event-key' ] )	: NULL;
		$nonce			= isset( $form_data_array[ '_wpnonce' ] )		? $form_data_array[ '_wpnonce' ]			: NULL;

		$volunteer		= ! empty( $email ) ? Volunteer::get_volunteer_by_email( $email )	 : NULL;
		
		$is_registered_user = Volunteer::get_is_exist_registered_user_for_email( $email );
		
//		Error_Log::var_dump( $email, $is_remember );

		if ( ! wp_verify_nonce( $nonce, self::AJAX_VOLUNTEER_LOGIN_ACTION ) ) {

			$error_msg = __( 'Invalid or missing security code.  Please refresh this page and try again.', 'reg-man-rc' );
			$ajax_response->add_error( '_wpnonce', $nonce, $error_msg );

		} elseif( empty( $email ) ) {

			$error_msg = __( 'An email address is required', 'reg-man-rc' );
			$ajax_response->add_error( 'vol-email', $email, $error_msg );

		} elseif( empty( $volunteer ) ) {

			$error_msg = __( 'Volunteer email not found.', 'reg-man-rc' );
			$ajax_response->add_error( 'vol-email', $email, $error_msg );

		} elseif( Settings::get_is_require_volunteer_area_registered_user() && ! $is_registered_user ) {

			$error_msg = __( 'You must have a registered user ID and password to access the volunteer area.', 'reg-man-rc' );
			$ajax_response->add_error( 'vol-email', $email, $error_msg );

		} else {

			// We have a volunteer, check if we need a password and if so, was the correct one was supplied

			// If everything is fine then this is the page I will redirect to
			$redirect_url = Volunteer_Area::get_href_for_main_page();
			
			// If we have an event key then add that arg to the redirect
			if ( isset( $event_key ) ) {
				$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
				$redirect_url = add_query_arg( $args, $redirect_url );
			} // endif
//			Error_Log::var_dump( $redirect_url );

			if ( $is_registered_user ) {

				if ( $pwd === NULL ) {

					// No password field was presented in the form so create the form and return it
					$pwd_form_content = Volunteer_Area::get_volunteer_login_form_content( $email, $is_remember, $is_registered_user, $event_key );
					$ajax_response->set_html_data( $pwd_form_content );

				} else {

					// If a password was supplied we need to do a WP signon
					$credentials = array(
							'user_login'	=> $email,
							'user_password'	=> $pwd,
							'remember'		=> $is_remember,
					);
					$signon_result = wp_signon( $credentials );
					if ( is_wp_error( $signon_result ) ) {

						// The login failed so return an error message
						$error_msg = __( 'The email address or password is not correct.', 'reg-man-rc' );
						$ajax_response->add_error( 'vol-pwd', $email, $error_msg );

					} else {

						// There are no errors so we will identify the volunteer by storing a cookie
						Volunteer::set_volunteer_email_cookie( $email, $is_remember );
						$ajax_response->set_redirect_url( $redirect_url );
						$volunteer->set_volunteer_area_last_login_datetime();

					} // endif

				} // endif

			} else {

				// No password needed, we have the volunteer so redirect them

				// There are no errors so we will identify the volunteer by storing a cookie
				Volunteer::set_volunteer_email_cookie( $email, $is_remember );
				$ajax_response->set_redirect_url( $redirect_url );
				$volunteer->set_volunteer_area_last_login_datetime();
				
			} // endif

		} // endif

		echo json_encode( $ajax_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function

	/**
	 * Handled the admin_post action for volunteer area exit
	 */
	public static function handle_volunteer_area_exit_action() {
		// Do the actual logout
		Volunteer_Controller::handle_volunteer_logout();
		// Return the caller to the area main page
		$redirect_url = Volunteer_Area::get_href_for_main_page();
		$event_key = Volunteer_Area::get_event_key_from_request();
		if ( ! empty( $event_key ) ) {
			$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
			$redirect_url = add_query_arg( $args, $redirect_url );
		} // endif
		wp_safe_redirect( $redirect_url );
	} // function

	/**
	 * Perform a logout for the volunteer.
	 * @return boolean	TRUE if the logout was successful
	 */
	public static function handle_volunteer_logout() {
		$result = Volunteer::set_volunteer_email_cookie( NULL );
		return $result;
	} // function

} // class