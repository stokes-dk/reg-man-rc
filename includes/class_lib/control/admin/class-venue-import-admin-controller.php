<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Venue_Import_Admin_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Geographic_Position;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative controller for importing Items
 *
 * @since	v0.8.5
 *
 */
class Venue_Import_Admin_Controller {

	const IMPORTER_ID = Venue::POST_TYPE . '-importer';

	const AJAX_ACTION = Venue::POST_TYPE . '-import';

	private static $CLEANUP_ACTION = 'reg_man_rc_venue_importer_scheduled_cleanup';

	private static $REQUIRED_CSV_COLUMNS = array(
			'Name',
			'Description',
			'Location',
			'Geographic Position',
			'Map Zoom Level',
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

		if ( self::current_user_can_import_venues() ) {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'do_ajax_import' ) );
		} // endif

	} // function

	/**
	 * Get a boolean indicating whether the current user has authorization to perform this action
	 * @return boolean
	 */
	public static function current_user_can_import_venues() {
		$result = current_user_can( 'import' ) && current_user_can( 'create_' . User_Role_Controller::VENUE_CAPABILITY_TYPE_PLURAL );
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

		$nonce				= isset( $_REQUEST[ '_wpnonce' ] )						? $_REQUEST[ '_wpnonce' ] : '';
		$is_reload			= isset( $_REQUEST[ 'reload' ] )						? filter_var( $_REQUEST[ 'reload' ], FILTER_VALIDATE_BOOLEAN ) : FALSE;
		$attachment_id		= isset( $_REQUEST[ 'venue-import-attachment-id' ] )	? $_REQUEST[ 'venue-import-attachment-id' ] : NULL;
		$file_upload_desc	= isset( $_FILES[ 'venue-import-file-name' ] )			? $_FILES[ 'venue-import-file-name' ] : NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_ACTION );
		if ( ! $is_valid_nonce ) {
			
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
			
		} elseif ( ! self::current_user_can_import_venues() ) {
			
			$err_msg = __( 'You are not authorized to perform this action.', 'reg-man-rc' );
			$form_response->add_error( '', '', $err_msg );
			
		} else {
			
			if ( $is_reload ) {
				
				$view = Venue_Import_Admin_View::create();
				$content = $view->get_form_contents();
				$form_response->set_html_data( $content );
			
			} elseif ( isset( $attachment_id ) ) {
				
				$attachment_file = get_attached_file( $attachment_id );
				$result = self::process_file( $attachment_file, $form_response );
				
			} else {
				// This is the first step, make sure the file is valid etc.
				if ( ! isset( $file_upload_desc ) ) {
					$err_msg = __( 'Please select a file for importing.', 'reg-man-rc' );
					$form_response->add_error( 'venue-import-file-name', '', $err_msg );
				} else {
					if ( ! is_array( $file_upload_desc ) || ! isset( $file_upload_desc[ 'type' ] ) || ! isset( $file_upload_desc[ 'tmp_name' ] ) ) {
						$err_msg = __( 'The file could not be uploaded to the server.', 'reg-man-rc' );
						$form_response->add_error( 'venue-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {

						$file_name = $file_upload_desc[ 'tmp_name' ];
						$is_valid_file = self::get_is_valid_csv_file( $file_name, $form_response );
						if ( $is_valid_file !== FALSE ) {
							$file_move_result = self::move_file_to_uploads( $file_upload_desc, $form_response );
							if ( $file_move_result !== FALSE ) {
								$attachment_id = $file_move_result;
								$view = Venue_Import_Admin_View::create();
								$view->set_import_file_attachment_id( $attachment_id );
								$content = $view->get_form_contents();
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
				$form_response->add_error( 'venue-import-file-name', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for venue import data like 'Name' or 'Location' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required venue data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'venue-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				if ( $result === TRUE ) {
					$data_array = fgetcsv( $handle );
					if ( ! is_array( $data_array ) ) {
						$err_msg = __( 'The CSV file contains no venue data records.', 'reg-man-rc' );
						$form_response->add_error( 'venue-import-file-name', '', $err_msg );
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
//		Error_Log::var_dump( $upload_result );

		if ( is_array( $upload_result ) && isset( $upload_result['error'] ) ) {
			/* translators: %s is an error message returned from Wordpress function wp_handle_upload */
			$err_msg = sprintf( __( 'The file could not be uploaded to the server. Error message: %s.', 'reg-man-rc' ), $upload_result['error'] );
			$form_response->add_error( 'venue-import-file-name', '', $err_msg );
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
			$hook = self::$CLEANUP_ACTION;
			$args = array( $attach_id );
			$is_wp_error = TRUE;
			$sched_event_result = wp_schedule_single_event( $time, $hook, $args, $is_wp_error );
			if ( $sched_event_result instanceof \WP_Error ) {
				$msg = __( 'Unable to schedule event for attachment cleanup', 'reg-man-rc' );
				Error_Log::log_wp_error( $msg, $sched_event_result );
			} // endif

			$result = $attach_id;
		} // endif
		return $result;
	} // function

	/**
	 * Process the file and perform the import
	 * @param	string				$file_path			The path to the CSV file with the venues to be imported
	 * @param	Ajax_Form_Response	$form_response		The response from the current request used to log errors
	 * @return	int|FALSE			The count of venues imported or FALSE on major error
	 */
	private static function process_file( $file_path, $form_response ) {
		$handle = fopen( $file_path, 'r' );
		if ( $handle == FALSE ) {
			/* translators: %s is the a file name */
			$err_msg = sprintf( __( 'Unable to open the file $s.', 'reg-man-rc' ), $file_path );
			$form_response->add_error( 'venue-import-attachment-id', '', $err_msg );
			$result = FALSE;
		} else {
			$header_array = fgetcsv( $handle );
			if ( ! is_array( $header_array ) ) {
				$err_msg = __( 'The file does not contain valid CSV data.', 'reg-man-rc' );
				$form_response->add_error( 'venue-import-attachment-id', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for venue import data like 'Name' or 'Location' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required venue data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'venue-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor

				$name_index = array_search( 'Name', $header_array );
				$desc_index = array_search( 'Description', $header_array );
				$location_index = array_search( 'Location', $header_array );
				$geo_index = array_search( 'Geographic Position', $header_array );
				$map_zoom_index = array_search( 'Map Zoom Level', $header_array );
				
				if ( $result === TRUE ) {
					$record_count = 0;
					$line_number = 2; // line 2 is the first row of data after the header

					while ( ( $data_array = fgetcsv( $handle ) ) !== FALSE ) {
						
						$name = $data_array[ $name_index ];
						$desc = $data_array[ $desc_index ];
						$location = $data_array[ $location_index ];
						$geo_string = $data_array[ $geo_index ];
						$zoom = $data_array[ $map_zoom_index ];
						
						if ( ! empty( $geo_string ) ) {
							$geo_string = str_replace( ',', ';', $geo_string ); // If the separator is a comma then switch to semi-colon
							$geo = Geographic_Position::create_from_iCalendar_string( $geo_string );
						} else {
							$geo = NULL;
						} // endif
						
						$insert_result = Venue::create_new_venue( $name, $location, $desc, $geo, $zoom );

						if ( empty( $insert_result ) ) {

							/* translators: %s is a line number in a file */
							$err_msg = sprintf( __( 'Unable to import the venue at line number %s.', 'reg-man-rc' ), $file_path );
							$form_response->add_error( "venue-import-attachment-id[$line_number]" , '', $err_msg);

						} else {
							
							$record_count++;
							
						} // endif
							
						
						$line_number++;
						
					} // endwhile
					
					$head = __( 'Venue import complete', 'reg-man-rc' );
					
					$details = array();
					
					$line_count = $line_number - 2;
					/* Translators: %s is a count of lines in a file */
					$details[] = sprintf( _n( '%s line read from the file.', '%s lines read from the file.', $line_count, 'reg-man-rc' ), number_format_i18n( $line_count ) );

					/* Translators: %s is a count of lines in a file */
					$details[] = sprintf( _n( '%s new venue created.', '%s new venues created.', $record_count, 'reg-man-rc' ), number_format_i18n( $record_count ) );
					
					ob_start();
						echo '<div>';
						
							echo "<h3>$head</h3>";
							foreach ( $details as $details_line ) {
								echo "<p>$details_line</p>";
							} // endfor
							
							$input_list = Form_Input_List::create();

							$nonce = wp_create_nonce( self::AJAX_ACTION );
							$input_list->add_hidden_input( '_wpnonce', $nonce );

							// The user can reload the form and start over
							$input_list->add_hidden_input( 'reload', 'TRUE' );
							
							$button_format = '<span class=" reg-man-rc-icon-text-container"><i class="icon dashicons dashicons-%2$s"></i><span class="text">%1$s</span></span>';
							$text = __( 'Import another file', 'reg-man-rc' );
							$label = sprintf( $button_format, $text, 'arrow-left-alt2' );
							$type = 'button';
							$classes = 'reg-man-rc-button import-reload-button';
							$input_list->add_form_button( $label, $type, $classes );
							$input_list->render();
							
						echo '</div>';
					$content = ob_get_clean();
					$form_response->set_html_data( $content );
				} // endif
			} // endif
		} // endif
		fclose( $handle );
		return $result;
	} // function


	/**
	 * Execute the scheduled clean up for the importer
	 * @param int $attachment_id
	 */
	public static function importer_scheduled_cleanup( $attachment_id ) {
//		Error_Log::var_dump( $attachment_id );
		$delete_result = wp_delete_attachment( $attachment_id, $force_delete = TRUE );
		// There's nothing more I can do if the delete fails.  Just move on.  If the user sees the attachment, she can delete.
		if ( $delete_result === FALSE ) {
			/* Translators: %1$s is an attachment ID */
			$msg_format = __( 'Unable to delete venue importer attachment with ID: %1$s', 'reg-man-rc' );
			$msg = sprintf( $msg_format, $attachment_id );
			Error_Log::log_msg( $msg );
		} // endif
	} // function
} // class