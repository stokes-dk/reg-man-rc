<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Visitor_Import_Admin_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;

/**
 * The administrative controller for importing Visitors
 *
 * @since	v0.1.0
 *
 */
class Visitor_Import_Admin_Controller {

	const IMPORTER_ID = Visitor::POST_TYPE . '-importer';

	const AJAX_ACTION = Visitor::POST_TYPE . '-import';

	private static $CLEANUP_ACTION = 'reg_man_rc_visitor_importer_scheduled_cleanup';

	// Should be Full Name, Public Name, Email, Join Mail List?, First Event Key
	private static $REQUIRED_CSV_COLUMNS = array(
		'Email', 'First Name', 'Last Name', 'Join Mail List?', 'First Event Key'
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

		if ( current_user_can( 'edit_posts' ) && current_user_can( 'import' ) ) {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'do_ajax_import' ) );
		} // endif

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
		} else {
//			Error_Log::var_dump( $_REQUEST );
			$attachment_id = isset( $_REQUEST[ 'visitor-import-attachment-id' ] ) ? $_REQUEST[ 'visitor-import-attachment-id' ] : NULL;
			if ( isset( $attachment_id ) ) {
				// This is the second step, so do the import here
				$attachment_file = get_attached_file( $attachment_id );
				$result = self::process_file( $attachment_file, $form_response );
			} else {
				// This is the first step, make sure the file is valid etc.
				$file_upload_desc = isset( $_FILES[ 'visitor-import-file-name' ] ) ? $_FILES[ 'visitor-import-file-name' ] : NULL;
				if ( ! isset( $file_upload_desc ) ) {
					$err_msg = __( 'Please select a file for importing.', 'reg-man-rc' );
					$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
				} else {
					if ( ! is_array( $file_upload_desc ) || ! isset( $file_upload_desc[ 'type' ] ) || ! isset( $file_upload_desc[ 'tmp_name' ] ) ) {
						$err_msg = __( 'The file could not be uploaded to the server.', 'reg-man-rc' );
						$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {
						$file_type = $file_upload_desc[ 'type' ];
						$valid_types = array( 'text/csv', 'text/plain' );
						if ( ! in_array( $file_type, $valid_types ) ) {
							$err_msg = __( 'The file is not a CSV file.', 'reg-man-rc' );
							$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
							$result = FALSE;
						} else {
							$file_name = $file_upload_desc[ 'tmp_name' ];
							$is_valid_file = self::get_is_valid_csv_file( $file_name, $form_response );
							if ( $is_valid_file !== FALSE ) {
								$file_move_result = self::move_file_to_uploads( $file_upload_desc, $form_response );
								if ( $file_move_result !== FALSE ) {
									$attachment_id = $file_move_result;
									$view = Visitor_Import_Admin_View::create();
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
				$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for visitor import data like 'Email' or 'Last Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required visitor data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'visitor-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				if ( $result === TRUE ) {
					$data_array = fgetcsv( $handle );
					if ( ! is_array( $data_array ) ) {
						$err_msg = __( 'The CSV file contains no visitor data records.', 'reg-man-rc' );
						$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
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

		$upload_overrides = array( 'test_form' => FALSE );

		$upload_result = wp_handle_upload( $file_upload_desc, $upload_overrides );

		if ( is_array( $upload_result ) && isset( $upload_result['error'] ) ) {
			/* translators: %s is an error message returned from Wordpress function wp_handle_upload */
			$err_msg = sprintf( __( 'The file could not be uploaded to the server. Error message: %s.', 'reg-man-rc' ), $upload_result['error'] );
			$form_response->add_error( 'visitor-import-file-name', '', $err_msg );
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
	 * Move the file to the upload directory
	 * @param	string				$file_path			The path to the CSV file with the visitors to be imported
	 * @param	Ajax_Form_Response	$form_response		The response from the current request used to log errors
	 * @return	int|FALSE			The count of visitors imported into the event or FALSE on major error
	 */
	private static function process_file( $file_path, $form_response ) {
		$handle = fopen( $file_path, 'r' );
		if ( $handle == FALSE ) {
			/* translators: %s is the a file name */
			$err_msg = sprintf( __( 'Unable to open the file $s.', 'reg-man-rc' ), $file_path );
			$form_response->add_error( 'visitor-import-attachment-id', '', $err_msg );
			$result = FALSE;
		} else {
			$header_array = fgetcsv( $handle );
			if ( ! is_array( $header_array ) ) {
				$err_msg = __( 'The file does not contain valid CSV data.', 'reg-man-rc' );
				$form_response->add_error( 'visitor-import-attachment-id', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for visitor import data like 'Email' or 'Last Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required visitor data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'visitor-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor

				$email_index = array_search( 'Email', $header_array );
				$first_name_index = array_search( 'First Name', $header_array );
				$last_name_index = array_search( 'Last Name', $header_array );
				$join_mail_list_index = array_search( 'Join Mail List?', $header_array );
				$first_event_key_index = array_search( 'First Event Key', $header_array );
				if ( $result === TRUE ) {
					$record_count = 0;
					$skipped_count = 0;
					$line_number = 2; // line 2 is the first row of data after the header

					// We want to make sure that the first event key refers to a valid event so we will get the Event object.
					// Rather than constructing a new Event object every time, we will keep a cache of keys and events.
					$event_cache = array();

					$false_values = array(
							__( 'false', 'reg-man-rc' ),
							__( 'no','reg-man-rc' ),
					);
					while ( ( $data_array = fgetcsv( $handle ) ) !== FALSE ) {
						
						$visitor = NULL; // Make sure we get a new visitor record each time
						$email = trim( $data_array[ $email_index ] );
						$first_name = $data_array[ $first_name_index ];
						$last_name = $data_array[ $last_name_index ];
						$join_mail_list_text = trim( strtolower( $data_array[ $join_mail_list_index ] ) );
						$first_event_key_string = trim( $data_array[ $first_event_key_index ] );

						if ( empty( $email ) && empty( $first_name ) && empty( $last_name ) ) {
							/* translators: %s is a line number in a file */
							$err_msg = sprintf( __( 'Unable to create visitor record for line number %s because no email or name was provided.', 'reg-man-rc' ), $file_path );
							$form_response->add_error( "visitor-import-attachment-id[$line_number]" , '', $err_msg );
							
						} else {
							$full_name = trim( "$first_name $last_name" );
							$public_name = Visitor::get_default_public_name( $full_name );
							$is_join = ( in_array( $join_mail_list_text , $false_values ) ) ? FALSE : boolval( $join_mail_list_text );

							// Find the event for the visitor's first event key
							if ( empty( $first_event_key_string ) ) {
								$first_event = NULL;
							} elseif ( isset( $event_cache[ $first_event_key_string ] ) ) {
								$first_event = $event_cache[ $first_event_key_string ];
							} else {
								$first_event = Event::get_event_by_key( $first_event_key_string );
								if ( isset( $first_event ) ) {
									$event_cache[ $first_event_key_string ] = $first_event;
								} // endif
							} // endif

							// Create the new visitor record
							$visitor = Visitor::get_visitor_by_email_or_full_name( $email, $full_name );
							if ( isset( $visitor ) ) {
								// In this case we already have a record for the visitor, we'll just bypass this record
								// TODO: We could have a setting allowing the user to update existing visitor records
								$skipped_count++;
							} else {
								$visitor = Visitor::create_visitor( $public_name, $full_name, $email );
								if ( ! isset( $visitor ) ) {
									/* translators: %s is a line number in a file */
									$err_msg = sprintf( __( 'Unable to create visitor record for line number %s.', 'reg-man-rc' ), $file_path );
									$form_response->add_error( "visitor-import-attachment-id[$line_number]" , '', $err_msg );
								} else {
									$visitor->set_is_join_mail_list( $is_join );
									$visitor->set_first_event_key( $first_event_key_string );
								} // endif
							} // endif
						} // endif

						$line_number++;
					} // endwhile
					$head = __( 'Visitor import complete', 'reg-man-rc' );
					/* translators:
						%1$s is the number of lines in a file,
						%2$s is the number of successfully created visitors
					 	%3$s is the number skipped because they already exist
					*/
					$details = sprintf(
								__( '%1$s lines read from the file.  %2$s new visitors created, %3$s existing visitors skipped.', 'reg-man-rc' ),
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