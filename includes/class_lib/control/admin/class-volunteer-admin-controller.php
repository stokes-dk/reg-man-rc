<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Remove_Volunteer_Form;
use Reg_Man_RC\Model\Volunteer_Registration;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteers
 *
 * @since v0.1.0
 *
 */
class Volunteer_Admin_Controller {
	
	const REMOVE_VOLUNTEER_AJAX_ACTION		= 'reg_man_rc_remove_volunteer';
	const REMOVE_VOLUNTEER_REQUEST_GET_FORM	= 'get-form';
	const REMOVE_VOLUNTEER_REQUEST_TRASH	= 'trash';
	const VOL_REG_ACTION_NOTHING			= 'nothing';
	const VOL_REG_ACTION_TRASH				= 'trash';
	const VOL_REG_ACTION_TRANSFER			= 'transfer';
	
	/**
	 * Register this view
	 */
	public static function register() {

		// Add an action hook to update our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );

		// Check to make sure that an item type is selected and show an error message if not
		add_action( 'edit_form_top', array( __CLASS__, 'show_edit_form_messages' ) );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );
		
		add_action( 'wp_ajax_' . self::REMOVE_VOLUNTEER_AJAX_ACTION, array( __CLASS__, 'handle_remove_volunteer_request' ) );

	} // function

	/**
	 * Handle an AJAX request to remove a volunteer.
	 *
	 * @return	void
	 */
	public static function handle_remove_volunteer_request() {

		$form_response = Ajax_Form_Response::create();
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

		// The referer page is where the request came from.  On successful removal we'll get the client to reload this page
		$referer			= $_SERVER[ 'HTTP_REFERER' ];
		
		$nonce				= isset( $form_data[ '_wpnonce' ] )			? $form_data[ '_wpnonce' ]			: '';
		$record_id			= isset( $form_data[ 'record-id' ] )		? $form_data[ 'record-id' ]			: NULL;
		$request_type		= isset( $form_data[ 'request-type' ] )		? $form_data[ 'request-type' ]		: self::REMOVE_VOLUNTEER_ACTION_GET;
		$vol_reg_action		= isset( $form_data[ 'vol-reg-action' ] )	? $form_data[ 'vol-reg-action' ]	: self::VOL_REG_ACTION_NOTHING;
		$vol_reg_transfer_target	= isset( $form_data[ 'vol-reg-transfer-target' ] )	? $form_data[ 'vol-reg-transfer-target' ]	: NULL;

//		Error_Log::var_dump( $form_data, $nonce, $referer, $record_id, $request_type );
		$is_valid_nonce = wp_verify_nonce( $nonce, self::REMOVE_VOLUNTEER_AJAX_ACTION );
		$volunteer = Volunteer::get_volunteer_by_id( $record_id );
		
		if ( ! $is_valid_nonce || empty( $volunteer ) ) {

			if ( ! $is_valid_nonce ) {
				$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
				$form_response->add_error( '_wpnonce', '', $err_msg );
			} // endif

			if ( empty( $volunteer ) ) {
				if ( empty( $record_id ) ) {
					$err_msg = __( 'No volunteer ID was specified.', 'reg-man-rc' );
					$form_response->add_error( 'record-id', '', $err_msg );
				} else {
					/* Translators: %1$s is a volunteer ID */
					$err_msg = sprintf( __( 'The volunteer could not be found with ID %1$s.', 'reg-man-rc' ), $record_id );
					$form_response->add_error( 'record-id', $record_id, $err_msg );
				} // endif
			} // endif

		} else {

			switch( $request_type ) {
				
				case self::REMOVE_VOLUNTEER_REQUEST_GET_FORM:
				default:
					$view = Remove_Volunteer_Form::create( $volunteer );
					$content = $view->get_remove_form_content();
					
					$form_response->set_html_data( $content );
					break;
					
				case self::REMOVE_VOLUNTEER_REQUEST_TRASH:
					$result = self::handle_remove_volunteer( $volunteer, $form_response, $vol_reg_action, $vol_reg_transfer_target );
					if ( $result === TRUE ) {
						$form_response->set_redirect_url( $referer ); // reload the page on success
					} // endif
					break;
					
			} // endswitch
			
		} // endif

//		Error_Log::var_dump( $form_response );
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
		
	} // class
	
	/**
	 * Handle the actual removal of a volunteer record
	 * @param Volunteer				$volunteer					The volunteer to be removed
	 * @param Ajax_Form_Response	$form_response				The form response object for reporting errors
	 * @param string				$vol_reg_action				The action to perform on associated volunteer registrations
	 * @param string				$vol_reg_transfer_target	The target volunteer for transferring volunteer registrations
	 */
	private static function handle_remove_volunteer( $volunteer, $form_response, $vol_reg_action, $vol_reg_transfer_target ) {
		$result = FALSE; // Assume there's a problem until everything is successful
		$vol_id = $volunteer->get_id();
		$is_delete_authorized = current_user_can( 'delete_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_SINGULAR, $vol_id );
		$vol_reg_array = Volunteer_Registration::get_registrations_for_volunteer( $volunteer );

		// Assume these are OK unless we find a problem
		$is_vol_reg_action_authorized = TRUE;
		$is_transfer_target_valid = TRUE;
		$transfer_target_volunteer = NULL;

		if ( ! empty( $vol_reg_array ) ) {

			switch( $vol_reg_action ) {

				case self::VOL_REG_ACTION_TRASH:
					$vol_reg_auth_err_msg = __( 'You are not authorized to trash the event registration records.', 'reg-man-rc' );
					$is_vol_reg_action_authorized = self::get_current_user_can_delete_these_volunteer_registrations( $vol_reg_array );
					break;
					
				case self::VOL_REG_ACTION_TRANSFER:
					$is_vol_reg_action_authorized = self::get_current_user_can_edit_these_volunteer_registrations( $vol_reg_array );
					$vol_reg_auth_err_msg = __( 'You are not authorized to transfer the event registration records.', 'reg-man-rc' );
					// Make sure we can get the selected volunteer we're transferring to
					$transfer_target_volunteer = Volunteer::get_volunteer_by_id( $vol_reg_transfer_target );
					$is_transfer_target_valid = isset( $transfer_target_volunteer );
					break;
					
			} // endswitch
		} // endif
		
		if ( ! $is_delete_authorized || ! $is_vol_reg_action_authorized || ! $is_transfer_target_valid ) {

			if ( ! $is_delete_authorized ) {
				$err_msg = __( 'You are not authorized to trash this volunteer.', 'reg-man-rc' );
				$form_response->add_error( 'is-remove-volunteer', $vol_id, $err_msg );
			} // endif
			
			if ( ! $is_vol_reg_action_authorized ) {
				$form_response->add_error( 'is-remove-volunteer', $vol_id, $vol_reg_auth_err_msg );
			} // endif

			if ( ! $is_transfer_target_valid ) {
				$transfer_err_msg = __( 'The selected volunteer was not found.', 'reg-man-rc' );
				$form_response->add_error( 'vol-reg-transfer-target', $vol_reg_transfer_target, $transfer_err_msg );
			} // endif

		} else {
			
			// Trash the volunteer record, if this fails then we'll stop
			$trash_result = wp_trash_post( $vol_id );
			if ( empty( $trash_result ) ) {
				
				/* Translators: %s is the ID of a volunteer */
				$err_msg_format = __( 'An error occurred when trying to trash the volunteer with ID %s.', 'reg-man-rc' );
				$err_msg = sprintf( $err_msg_format, $vol_id );
				$form_response->add_error( 'is-remove-volunteer', $vol_id, $err_msg );
				
			} else {
				
				$result = self::handle_remove_volunteer_registrations( $vol_reg_array, $form_response, $vol_reg_action, $transfer_target_volunteer );
				
			} // endif
			
		} // endif
		
		return $result;

	} // function
	
	/**
	 * Get a boolean indicating whether the current user has authority to delete the volunteer registration records specified
	 * @param Volunteer_Registration[]	$vol_reg_array				The array of volunteer registrations to be removed
	 * @param Ajax_Form_Response		$form_response				The form response object for reporting errors
	 * @param string					$vol_reg_action				The action to perform on associated volunteer registrations
	 * @param Volunteer					$transfer_target_volunteer	The target volunteer for transferring volunteer registrations
	 */
	private static function handle_remove_volunteer_registrations( $vol_reg_array, $form_response, $vol_reg_action, $transfer_target_volunteer ) {
		if ( empty( $vol_reg_array ) || ( $vol_reg_action == self::VOL_REG_ACTION_NOTHING ) ) {

			$result = TRUE;

		} else {
			
			$result = TRUE; // unless we hit a problem

			foreach( $vol_reg_array as $vol_reg ) {
				$vol_reg_id = $vol_reg->get_id();
				
				switch( $vol_reg_action ) {
					case self::VOL_REG_ACTION_TRASH:
						
						$trash_result = wp_trash_post( $vol_reg_id );
						if ( empty( $trash_result ) ) {
							
							/* Translators: %s is the ID of a volunteer registration record */
							$err_msg_format = __( 'An error occurred when trying to trash the volunteer registration record with ID %s.', 'reg-man-rc' );
							$err_msg = sprintf( $err_msg_format, $vol_reg_id );
							$form_response->add_error( '', $vol_reg_id, $err_msg );
							$result = FALSE;
							break 2; // <== Exit the switch and the foreach loop
							
						} // endif
						break;
						
					case self::VOL_REG_ACTION_TRANSFER:
						$vol_reg->set_volunteer( $transfer_target_volunteer );
						break;
						
					default:
						// do nothing
						break;
						
				} // endswitch
			} // endfor
		} // endif
		
		return $result;
	} // function
	
	/**
	 * Get a boolean indicating whether the current user has authority to delete the volunteer registration records specified
	 * @param Volunteer_Registration[] $vol_reg_array
	 */
	public static function get_current_user_can_delete_these_volunteer_registrations( $vol_reg_array ) {

		$user_can_delete_any = current_user_can( 'delete_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
		$user_can_delete_published = current_user_can( 'delete_published_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		if ( $user_can_delete_any ) {

			$result = TRUE; // this user is allowed to delete all vol reg records

		} elseif ( ! $user_can_delete_published ) {

			$result = FALSE; // This user is NOT allowed to delete any vol reg records

		} else {

			// This user can delete SOME vol reg records, we need to check the current list
			$result = TRUE; // assume true and then look for any problems
			foreach( $vol_reg_array as $vol_reg ) {
				$vol_reg_id = $vol_reg->get_id();
				if ( ! current_user_can( 'delete_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, $vol_reg_id ) ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			
		} // endif

		return $result;
		
	} // function
	
	/**
	 * Get a boolean indicating whether the current user has authority to edit (transfer) the volunteer registration records specified
	 * @param Volunteer_Registration[] $vol_reg_array
	 */
	public static function get_current_user_can_edit_these_volunteer_registrations( $vol_reg_array ) {

		$user_can_edit_any = current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
		$user_can_edit_published = current_user_can( 'edit_published_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		if ( $user_can_edit_any ) {

			$result = TRUE; // this user is allowed to delete all vol reg records

		} elseif ( ! $user_can_edit_published ) {

			$result = FALSE; // This user is NOT allowed to delete any vol reg records

		} else {

			// This user can delete SOME vol reg records, we need to check the current list
			$result = TRUE; // assume true and then look for any problems
			foreach( $vol_reg_array as $vol_reg ) {
				$vol_reg_id = $vol_reg->get_id();
				if ( ! current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, $vol_reg_id ) ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			
		} // endif

		return $result;
		
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
	public static function handle_post_save( $post_id, $post, $is_update ) {
		$curr_status = $post->post_status;
		
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $curr_status == 'trash' ) {
			// Don't do anything during an autosave or if the post is being trashed
			return;
			
		} else {
			$volunteer = Volunteer::get_volunteer_by_id( $post_id );
			if ( ! empty( $volunteer ) ) {

				// If there are any missing required settings then I will set this post to DRAFT status later
				$new_status = 'private'; // Always private status so that volunteers are never visible to the public
				
				// Update the fixer station
				if ( isset( $_POST[ 'fixer_station_selection_flag' ] ) ) {
					$fixer_station_id = isset( $_POST[ 'fixer_station' ] ) ? $_POST[ 'fixer_station' ] : 0;
					$fixer_station = isset( $fixer_station_id ) ? Fixer_Station::get_fixer_station_by_id( $fixer_station_id ) : NULL;
					$volunteer->set_preferred_fixer_station( $fixer_station );
					$is_apprentice = ( isset( $_POST[ 'is_apprentice' ] ) && isset( $fixer_station ) ) ? TRUE : FALSE;
					$volunteer->set_is_fixer_apprentice( $is_apprentice );
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
					$volunteer->set_preferred_roles( $new_roles );
				} // endif

				// Check if there is a duplicate record ALWAYS, e.g. when doing untrash
				// To get the email we'll look whether one has been supplied during this save
				//  or (like during untrash) we need to get it from the record itself
				if ( isset( $_POST[ 'volunteer_details_input_flag' ] ) ) {
					$email = isset( $_POST[ 'volunteer_email' ] ) ? stripslashes( trim( $_POST[ 'volunteer_email' ] ) ) : NULL;
				} else {
					$email = $volunteer->get_email();
				} // endif
				
				$duplicate_array = Volunteer::get_all_volunteers_by_email( $email, $post_id );
				if ( ! empty( $duplicate_array ) ) {
					$new_status = 'draft';
				} // endif
				
				// Update the personal info if it's been supplied
				if ( isset( $_POST[ 'volunteer_details_input_flag' ] ) ) {

					$full_name = isset( $_POST[ 'volunteer_full_name' ] ) ? stripslashes( trim( $_POST[ 'volunteer_full_name' ] ) ) : NULL;
					$volunteer->set_personal_info( $full_name, $email );
					
				} // endif

				// Update the proxy
				if ( isset( $_POST[ 'volunteer_proxy_input_flag' ] ) ) {

					//Proxy
					$proxy_id = isset( $_POST[ 'volunteer_proxy' ] ) ? $_POST[ 'volunteer_proxy' ] : NULL;
					$volunteer->set_my_proxy_volunteer_id( $proxy_id );

				} // endif
				
				// I need to remove this action from save_post to avoid an infinite loop
				remove_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

				// Modify the post status
				wp_update_post( array(
					'ID' 			=> $post_id,
					'post_status'	=> $new_status
				) );
				
				// Add the action back
				add_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
					
			} // endif

		} // endif

	} // function

	
	/**
	 * Display a message at the top of the form if there is a missing required field
	 *
	 * @param	\WP_Post	$post	The ID of the post being saved
	 * @return	void
	 *
	 * @since v0.9.9
	 *
	 */
	public static function show_edit_form_messages( $post ) {
		if ( Volunteer::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$volunteer = Volunteer::get_volunteer_by_id( $post->ID );

			if ( ! empty( $volunteer ) ) {

				$error_format =		'<div class="error below-h2"><p>%s</p></div>';
				$warning_format =	'<div class="notice notice-warning below-h2 is-dismissible"><p>%s</p></div>';
				
				// If the email address is already in use then add an error message
				$email = $volunteer->get_email();
				$duplicate_array = Volunteer::get_all_volunteers_by_email( $email, $post->ID );
				
				foreach( $duplicate_array as $duplicate ) {
					$display_name = esc_html__( $duplicate->get_display_name() );
					$edit_url = $duplicate->get_edit_url();
					if ( ! empty( $edit_url ) ) {
						$display_name = "<a href=\"$edit_url\" target=\"_blank\">$display_name</a>";
					} // endif
					/* Translators: %1$s is a volunteer's name */
					$msg_fmt = __( 'A volunteer record with this email address already exists: %1$s', 'reg-man-rc' );
					$msg = sprintf( $msg_fmt, $display_name );
					printf( $error_format, $msg );
				} // endfor

				if ( ( $post->post_status == 'draft' ) && ( ! empty( $duplicate_array ) ) ) {
					$trash_url = get_delete_post_link( $post );
					$trash_text = esc_html__( 'trash', 'reg-man-rc' );
					$trash_link = "<a href=\"$trash_url\">$trash_text</a>";
					/* Translators: %1$s is a link to trash the record */
					$msg = __( 'The volunteer record is saved as a DRAFT until problems are resolved or you %1$s it.', 'reg-man-rc' );
					printf( $warning_format, sprintf( $msg, $trash_link ) );
				} // endif

			} // endif

		} // endif
	} // function

	
	/**
	 * Modify the query to based on the filter settings.
	 * This is called during the pre_get_posts action hook.
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) && ( $post_type == Volunteer::POST_TYPE ) ) {

			// Filter the taxonomies as necessary
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

} // class