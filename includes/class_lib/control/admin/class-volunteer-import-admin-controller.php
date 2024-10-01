<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\View\Admin\Volunteer_Import_Admin_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative controller for importing Volunteers
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Import_Admin_Controller {

	const IMPORTER_ID = Volunteer::POST_TYPE . '-importer';

	const AJAX_ACTION = Volunteer::POST_TYPE . '-import';

	private static $CLEANUP_ACTION = 'reg_man_rc_volunteer_importer_scheduled_cleanup';

	private static $REQUIRED_CSV_COLUMNS = array(
			'First Name', 'Last Name', 'Email', 'Preferred Volunteer Roles', 'Preferred Fixer Station', 'Apprentice'
	);


	/**
	 * Register the controller action and filter hooks.
	 *
	 * This method is called by the plugin controller to register this controller.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {

		if ( self::current_user_can_import_volunteers() ) {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'do_ajax_import' ) );
		} // endif

	} // function

	/**
	 * Get a boolean indicating whether the current user has authorization to perform this action
	 * @return boolean
	 */
	public static function current_user_can_import_volunteers() {
		// Only users who are able to read private emails should be able to do this
		//  because when we import we need to look them up by email address
		
		$result =
			current_user_can( 'import' ) &&
			current_user_can( 'create_'			. User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) &&
			current_user_can( 'read_private_'	. User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL );
		return $result;
	} // function
	
	/**
	 * Register the action for my cron job.
	 * Note that this must be done before 'init' so it's separate from the usual register() function above
	 */
	public static function register_action_handlers() {

		// Register the action to clean up my imported files
		add_action( self::$CLEANUP_ACTION, array( __CLASS__, 'importer_scheduled_cleanup' ) );
		
	} // function
	
	/**
	 * Handle an AJAX import form post.
	 *
	 * This method is called by the plugin controller to register this controller.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function do_ajax_import() {

		$form_response = Ajax_Form_Response::create();

		$nonce = isset( $_REQUEST[ '_wpnonce' ] ) ? $_REQUEST[ '_wpnonce' ] : '';
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_ACTION );
		if ( ! $is_valid_nonce ) {

			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
			
		} elseif ( ! self::current_user_can_import_volunteers() ) {

			$err_msg = __( 'You are not authorized to perform this action.', 'reg-man-rc' );
			$form_response->add_error( '', '', $err_msg );
			
		} else {
			$attachment_id = isset( $_REQUEST[ 'volunteer-import-attachment-id' ] ) ? $_REQUEST[ 'volunteer-import-attachment-id' ] : NULL;
			if ( isset( $attachment_id ) ) {
				// This is the second step, so do the import here
				$attachment_file = get_attached_file( $attachment_id );
				$result = self::process_file( $attachment_file, $form_response );
			} else {
				// This is the first step, make sure the file is valid etc.
				$file_upload_desc = isset( $_FILES[ 'volunteer-import-file-name' ] ) ? $_FILES[ 'volunteer-import-file-name' ] : NULL;
				if ( ! isset( $file_upload_desc ) ) {
					$err_msg = __( 'Please select a file for importing.', 'reg-man-rc' );
					$form_response->add_error( 'volunteer-import-file-name', '', $err_msg );
				} else {
					if ( ! is_array( $file_upload_desc ) || ! isset( $file_upload_desc[ 'type' ] ) || ! isset( $file_upload_desc[ 'tmp_name' ] ) ) {
						$err_msg = __( 'The file could not be uploaded to the server.', 'reg-man-rc' );
						$form_response->add_error( 'volunteer-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {
						$file_name = $file_upload_desc[ 'tmp_name' ];
						$is_valid_file = self::get_is_valid_csv_file( $file_name, $form_response );
						if ( $is_valid_file !== FALSE ) {
							$file_move_result = self::move_file_to_uploads( $file_upload_desc, $form_response );
							if ( $file_move_result !== FALSE ) {
								$attachment_id = $file_move_result;
								$view = Volunteer_Import_Admin_View::create();
								$view->set_import_file_attachment_id( $attachment_id );
								ob_start();
									$view->render_form_contents();
								$content = ob_get_clean();
								$form_response->set_html_data( $content );
							} // endif
						} // endif
					} // endif
				} // endif
			} // endif
		} // endif

		$result = json_encode( $form_response->jsonSerialize() );
		echo $result;

		wp_die(); // THIS IS REQUIRED!

	} // function

	/**
	 * Check if the file is valid
	 * @param	string[]			$file_upload_desc
	 * @param	Ajax_Form_Response	$form_response
	 * @return	boolean
	 */
	public static function get_is_valid_csv_file( $file_name, $form_response ) {
		$handle = fopen( $file_name, 'r' );
		if ( $handle !== FALSE ) {
			$header_array = fgetcsv( $handle );
			if ( ! is_array( $header_array ) ) {
				$err_msg = __( 'The file does not contain valid CSV data.', 'reg-man-rc' );
				$form_response->add_error( 'volunteer-import-file-name', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				$header_array = array_map( 'strtolower', $header_array );
				foreach ( $required_columns as $col ) {
					if ( ! in_array( strtolower( $col ), $header_array ) ) {
						/* translators: %s is the name of a CSV column for volunteer import data like 'Email' or 'Full Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required column "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'volunteer-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				if ( $result === TRUE ) {
					$data_array = fgetcsv( $handle );
					if ( ! is_array( $data_array ) ) {
						$err_msg = __( 'The CSV file contains no volunteer data records.', 'reg-man-rc' );
						$form_response->add_error( 'volunteer-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {
						$result = TRUE;
					} // endif
				} // endif
			} // endif
		} // endif
		fclose( $handle );
		return $result;
	} // function

	/**
	 * Move the file to the upload directory
	 * @param	string[]			$file_upload_desc	An array of strings describing the uploaded file, from $_FILES[]
	 * @param	Ajax_Form_Response	$form_response		The response from the current request used to log errors
	 * @return	string|FALSE		The name of the file if it is successfully moved to the uploads directory, FALSE otherwise
	 */
	private static function move_file_to_uploads( $file_upload_desc, $form_response ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		} // endif

		$upload_overrides = array( 'test_form' => false );

		$upload_result = wp_handle_upload( $file_upload_desc, $upload_overrides );

		if ( is_array( $upload_result ) && isset( $upload_result['error'] ) ) {
			/* translators: %s is an error message returned from Wordpress function wp_handle_upload */
			$err_msg = sprintf( __( 'The file could not be uploaded to the server. Error message: %s.', 'reg-man-rc' ), $upload_result['error'] );
			$form_response->add_error( 'volunteer-import-file-name', '', $err_msg );
			$result = FALSE;
		} else {
			// We have moved the uploaded file to the uploads directory.
			// Now we'll create an attachment record so that the file can be seen in the media library
			$attach_args = array(
				'post_title'		=> basename( $upload_result[ 'file' ] ),
				'post_content'		=> $upload_result[ 'url' ],
				'post_mime_type'	=> $upload_result[ 'type' ],
				'guid'				=> $upload_result[ 'url' ],
				'context'			=> 'import',
				'post_status'		=> 'private',
			);

			// Save the data.
			$attach_id = wp_insert_attachment( $attach_args, $upload_result['file'] );

			// Schedule a cleanup for one day from now to make sure the file goes away even if there are problems importing it
			$time = time() + DAY_IN_SECONDS;
			$cron = wp_schedule_single_event( $time, self::$CLEANUP_ACTION, array( $attach_id ) );

			$result = $attach_id;
		} // endif
		return $result;
	} // function

	/**
	 * Process the file
	 * @param	string				$file_path			The path to the CSV file with the volunteers to be imported
	 * @param	Ajax_Form_Response	$form_response		The response from the current request used to log errors
	 * @return	int|FALSE			The count of volunteers imported or FALSE on major error
	 */
	private static function process_file( $file_path, $form_response ) {
		$handle = fopen( $file_path, 'r' );
		if ( $handle == FALSE ) {
			/* translators: %s is the a file name */
			$err_msg = sprintf( __( 'Unable to open the file $s.', 'reg-man-rc' ), $file_path );
			$form_response->add_error( 'volunteer-import-attachment-id', '', $err_msg );
			$result = FALSE;
		} else {
			$header_array = fgetcsv( $handle );
			if ( ! is_array( $header_array ) ) {
				$err_msg = __( 'The file does not contain valid CSV data.', 'reg-man-rc' );
				$form_response->add_error( 'volunteer-import-attachment-id', '', $err_msg );
				$result = FALSE;
			} else {
				$header_array = array_map( 'strtolower', $header_array );
				$result = TRUE; // Assume it's ok then look for a problem

				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( strtolower( $col ), $header_array ) ) {
						/* translators: %s is the name of a CSV column for item registration import data like 'Email' or 'Full Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required volunteer data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'volunteer-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor

				//	'First Name', 'Last Name', 'Email', 'Preferred Roles', 'Preferred Fixer Station'

				$first_name_index = array_search( strtolower( 'First Name' ), $header_array );
				$last_name_index = array_search( strtolower( 'Last Name' ), $header_array );
				$email_index = array_search( strtolower( 'Email' ), $header_array );
				$roles_index = array_search( strtolower( 'Preferred Volunteer Roles' ), $header_array );
				$station_index = array_search( strtolower( 'Preferred Fixer Station' ), $header_array );
				$apprentice_index = array_search( strtolower( 'Apprentice' ), $header_array );
				
				if ( $result === TRUE ) {
					$record_count = 0;
					$skipped_count = 0;
					$line_number = 2; // line 2 is the first row of data after the header

					while ( ( $data_array = fgetcsv( $handle ) ) !== FALSE ) {

						$email = trim( $data_array[ $email_index ] );
						$first_name = trim( $data_array[ $first_name_index ] );
						$last_name = trim( $data_array[ $last_name_index ] );
						$roles_text = trim( $data_array[ $roles_index ] );
						$station_text = trim( $data_array[ $station_index ] );
						$apprentice_text = trim( $data_array[ $apprentice_index ] );
						
						// Full name (from first and last name)
						// TODO: The strategy for combining first and last name could be a setting on the page
						$full_name = trim( "$first_name $last_name" );

						// Public name 
						// Could be just first name or first name and last initial
						// TODO: The strategy for creating the public name could be a setting on the page
//						$last_initial = ! empty( $last_name ) ? substr( $last_name, 0, 1 ) : '';
//						$public_name = trim( "$first_name $last_initial." );
						$public_name = $first_name;

						// Try to find the volunteer with the specified email address or full name if no email is provided
						if ( ! empty( $email ) ) {
							$volunteer = Volunteer::get_volunteer_by_email( $email );
						} else {
							$volunteer = ! empty( $full_name ) ? Volunteer::get_volunteer_by_full_name( $full_name ) : NULL;
						} // endif

						if ( isset( $volunteer ) || empty( $full_name ) ) {
							// In this case we already have a record for the volunteer, we'll just bypass this record
							// Or we have a record with no full name, so we can't use it
							// TODO: We could have a setting allowing the user to update existing volunteer records
							$skipped_count++;
						} else {

							// There is no email address or we don't have an existing record for this volunteer
							// Create the new record, provided we have at least a full name
							$volunteer = Volunteer::create_new_volunteer( $public_name, $full_name, $email );

							if ( ! isset( $volunteer ) ) {
								
								/* translators: %s is a line number in a file */
								$err_msg = sprintf( __( 'Unable to import volunteer record for line number %s.', 'reg-man-rc' ), $line_number );
								$form_response->add_error( "volunteer-import-attachment-id[$line_number]" , '', $err_msg );
								
							} else {
								
								$record_count++;
								
								// Assign the fixer station
								// $station = Fixer_Station::get_fixer_station_by_name( $station_text );
								// We want to allow the import file to have multiple station names, we'll use the first one as preferred
								$station_array = explode( '|', $station_text );
								$station_name = isset( $station_array[ 0 ] ) ? trim( $station_array[ 0 ] ) : NULL;
								$station = Fixer_Station::get_fixer_station_by_name( $station_name );
								// Note that if $station is empty then setting it as preferred fixer station will delete any current setting
								$volunteer->set_preferred_fixer_station( $station );

								// Assign apprentice
								$is_apprentice = filter_var( $apprentice_text, FILTER_VALIDATE_BOOLEAN );
								$volunteer->set_is_fixer_apprentice( $is_apprentice );
								
								// Assign the array of preferred volunteer roles
								$role_name_array = explode( '|', $roles_text );
								$roles_array = array();
								foreach ( $role_name_array as $role_name ) {
									$role = Volunteer_Role::get_volunteer_role_by_name( $role_name );
									if ( ! empty( $role ) ) {
										$roles_array[] = $role;
									} // endif
								} // endfor
								$volunteer->set_preferred_roles( $roles_array );
							} // endif
						} // endif

						$line_number++;
					} // endwhile
					$head = __( 'Volunteer import complete', 'reg-man-rc' );
					/* translators:
					 %1$s is the number of lines in the file,
					 %2$s is the number of successfully created items,
					 %3$s is the number skipped because they already exist
					*/
					$details = sprintf(
							__( '%1$s lines read from the file, %2$s new volunteers created, %3$s existing volunteers skipped.', 'reg-man-rc' ),
							( $line_number - 2 ), $record_count, $skipped_count
					);
					ob_start();
						echo '<div>';
							echo "<h3>$head</h3>";
							echo "<p>$details</p>";
						echo '</div>';
					$content = ob_get_clean();
					$form_response->set_html_data( $content );
				} // endif
			} // endif
		} // endif
		fclose( $handle );
		return $result;
	} // function


	public static function importer_scheduled_cleanup( $attachment_id ) {
		$delete_result = wp_delete_attachment( $attachment_id, $force_delete = TRUE );
		// There's nothing more I can do if the delete fails.  Just move on.  If the user sees the attachment, she can delete.
	} // function
} // class