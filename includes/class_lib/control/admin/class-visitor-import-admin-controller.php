<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Visitor_Import_Admin_View;
use Reg_Man_RC\Model\Event;

/**
 * The administrative controller for importing Visitors
 *
 * @since	v0.1.0
 *
 */
class Visitor_Import_Admin_Controller {

	const IMPORTER_ID = Visitor::POST_TYPE . '-importer';

	const AJAX_ACTION = Visitor::POST_TYPE . '-import';

	private static $CLEANUP_ACTION = 'reg_man_rc_item_importer_scheduled_cleanup';

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
			$attachment_id = isset( $_REQUEST[ 'item-import-attachment-id' ] ) ? $_REQUEST[ 'item-import-attachment-id' ] : NULL;
			if ( isset( $attachment_id ) ) {
				// This is the second step, so do the import here
				$event_key = isset( $_REQUEST[ 'item-import-event' ] ) ? wp_unslash( $_REQUEST[ 'item-import-event' ] ) : NULL;
				$event = Event::get_event_by_key( $event_key );
				if ( empty( $event ) ) {
					$err_msg = __( 'The event could not be found.', 'reg-man-rc' );
					$form_response->add_error( 'item-import-event', '', $err_msg );
				} else {
					$attachment_file = get_attached_file( $attachment_id );
					$result = self::process_file( $attachment_file, $event, $form_response );
				} // endif
			} else {
				// This is the first step, make sure the file is valid etc.
				$file_upload_desc = isset( $_FILES[ 'item-import-file-name' ] ) ? $_FILES[ 'item-import-file-name' ] : NULL;
				if ( ! isset( $file_upload_desc ) ) {
					$err_msg = __( 'Please select a file for importing.', 'reg-man-rc' );
					$form_response->add_error( 'item-import-file-name', '', $err_msg );
				} else {
					if ( ! is_array( $file_upload_desc ) || ! isset( $file_upload_desc[ 'type' ] ) || ! isset( $file_upload_desc[ 'tmp_name' ] ) ) {
						$err_msg = __( 'The file could not be uploaded to the server.', 'reg-man-rc' );
						$form_response->add_error( 'item-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {
						$file_type = $file_upload_desc[ 'type' ];
						$valid_types = array( 'text/csv', 'text/plain' );
						if ( ! in_array( $file_type, $valid_types ) ) {
							$err_msg = __( 'The file is not a CSV file.', 'reg-man-rc' );
							$form_response->add_error( 'item-import-file-name', '', $err_msg );
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
				$form_response->add_error( 'item-import-file-name', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for visitor import data like 'Email' or 'Last Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required item data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'item-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				if ( $result === TRUE ) {
					$data_array = fgetcsv( $handle );
					if ( ! is_array( $data_array ) ) {
						$err_msg = __( 'The CSV file contains no item data records.', 'reg-man-rc' );
						$form_response->add_error( 'item-import-file-name', '', $err_msg );
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
			$form_response->add_error( 'item-import-file-name', '', $err_msg );
			$result = FALSE;
		} else {
			// We have moved the uploaded file to the uploads directory.
			// Now we'll create an attachment record so that the file can be seen in the media library
			$attach_args = array(
				'post_title'     => basename( $upload_result[ 'file' ] ),
				'post_content'   => $upload_result[ 'url' ],
				'post_mime_type' => $upload_result[ 'type' ],
				'guid'           => $upload_result[ 'url' ],
				'context'        => 'import',
				'post_status'    => 'private',
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
	 * @param	string				$file_path			The path to the CSV file with the items to be imported
	 * @param	Event				$event				The event to import the items into
	 * @param	Ajax_Form_Response	$form_response		The response from the current request used to log errors
	 * @return	int|FALSE			The count of items imported into the event or FALSE on major error
	 */
	private static function process_file( $file_path, $event, $form_response ) {
		$handle = fopen( $file_path, 'r' );
		if ( $handle == FALSE ) {
			/* translators: %s is the a file name */
			$err_msg = sprintf( __( 'Unable to open the file $s.', 'reg-man-rc' ), $file_path );
			$form_response->add_error( 'item-import-attachment-id', '', $err_msg );
			$result = FALSE;
		} else {
			$header_array = fgetcsv( $handle );
			if ( ! is_array( $header_array ) ) {
				$err_msg = __( 'The file does not contain valid CSV data.', 'reg-man-rc' );
				$form_response->add_error( 'item-import-attachment-id', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for visitor import data like 'Email' or 'Last Name' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required item data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'item-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				$event_key = $event->get_key();

				$email_index = array_search( 'Email', $header_array );
				$first_name_index = array_search( 'First Name', $header_array );
				$last_name_index = array_search( 'Last Name', $header_array );
				$join_mail_list_index = array_search( 'Join Mail List?', $header_array );
				$is_first_time_index = array_search( 'First Event Key', $header_array );
				if ( $result === TRUE ) {
					$record_count = 0;
					$line_number = 2; // line 2 is the first row of data after the header
					$statuses = Item_Status::get_all_item_statuses(); // an associative array of status constants and objects

					// For visitors who do not provide an email address I do not want to create multiple records
					//  with the same full name.
					// So I will save an array of new visitor records keyed by full name for visitors at this event with no email
					//  and I will re-use those records rather than create new ones
					$visitor_cache = array();

					while ( ( $data_array = fgetcsv( $handle ) ) !== FALSE ) {
						// First, get or create the visitor record
						$visitor = NULL; // Make sure we get a new visitor record each time
						$email = $data_array[ $email_index ];
						$first_name = $data_array[ $first_name_index ];
						$last_name = $data_array[ $last_name_index ];
						$join_mail_list_text = trim( strtolower( $data_array[ $join_mail_list_index ] ) );
						$is_first_time_text = trim( strtolower( $data_array[ $is_first_time_index ] ) );

						$full_name = trim( "$first_name $last_name" );
						$last_initial = ! empty( $last_name ) ? substr( $last_name, 0, 1 ) : '';
						$public_name = trim( "$first_name $last_initial" );
						$is_join =( $join_mail_list_text === 'yes' );
						$is_first_time =( $is_first_time_text === 'yes' );
						$first_event_key = $is_first_time ? $event_key : NULL;

						if ( ! empty( $email ) ) {
							// Try to find the visitor record with the specified email address
							$visitor = Visitor::get_visitor_by_email( $email );
						} // endif
						if ( ! isset( $visitor ) ) {
							// There is no email address or we don't have an existing record for this visitor
							// Check if we have cached this visitor from a previous import record
							if ( ! empty( $last_name ) && isset( $visitor_cache[ $full_name ] ) ) {
								// Get the visitor record from the cache
								$visitor = $visitor_cache[ $full_name ];
							} else {
								// Create the new visitor record
								$visitor = Visitor::create_visitor( $public_name, $full_name, $email, $first_event_key, $is_join );
								if ( ! isset( $visitor ) ) {
									/* translators: %s is a line number in a file */
									$err_msg = sprintf( __( 'Unable to create visitor record for line number %s.', 'reg-man-rc' ), $file_path );
									$form_response->add_error( "visitor-import-attachment-id[$line_number]" , '', $error_msg );
								} elseif ( empty( $email ) && ! empty( $last_name ) ) {
									// If there's no email but we have a full name then save this record for later
									$visitor_cache[ $full_name ] = $visitor;
								} // endif
							} // endif
						} // endif

						if ( isset( $visitor ) ) {
							// We can only create item if we have the visitor record
							$item_desc = $data_array[ $item_desc_index ];
							$fixer_station_text = trim( $data_array[ $fixer_station_index ] );
							$item_type_text = trim( $data_array[ $item_type_index ] );
							$is_fixed_text = trim( strtolower( $data_array[ $is_fixed_index ] ) );

							switch ( $is_fixed_text ) {
								case 'fixed':
								case 'yes':
								case 'yes!':
									$status = $statuses[ Item_Status::FIXED ];
									break;
								case 'end of life':
								case 'no':
									$status = $statuses[ Item_Status::END_OF_LIFE ];
									break;
								case 'repairable':
								case 'not quite but made progress':
									$status = $statuses[ Item_Status::REPAIRABLE ];
									break;
								default:
									$status = NULL;
									break;
							} // endswitch

							$fixer_station = Fixer_Station::get_fixer_station_by_name( $fixer_station_text );

							$item_type = Item_Type::get_item_type_by_name( $item_type_text );

							$insert_result = Item::create_new_item( $item_desc, $fixer_station, $item_type, $event_key, $visitor, $status );
							if ( empty( $insert_result ) ) {
								/* translators: %s is a line number in a file */
								$err_msg = sprintf( __( 'Unable to import the item record at line number %s.', 'reg-man-rc' ), $file_path );
								$form_response->add_error( "item-import-attachment-id[$line_number]" , '', $error_msg);
							} else {
								$record_count++;
							} // endif
						} // endif
						$line_number++;
					} // endwhile
					$head = __( 'Item import complete', 'reg-man-rc' );
					/* translators: %1$s is the number of lines in a file, %2$s is the number of successfully created items */
					$details = sprintf( __( '%1$s lines read from the file.  %2$s new items created.', 'reg-man-rc' ), ( $line_number - 2 ), $record_count );
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