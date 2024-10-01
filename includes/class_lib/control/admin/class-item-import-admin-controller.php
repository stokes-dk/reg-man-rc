<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Item_Import_Admin_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative controller for importing Items
 *
 * @since	v0.1.0
 *
 */
class Item_Import_Admin_Controller {

	const IMPORTER_ID = Item::POST_TYPE . '-importer';

	const AJAX_ACTION = Item::POST_TYPE . '-import';

	private static $CLEANUP_ACTION = 'reg_man_rc_item_importer_scheduled_cleanup';

	private static $REQUIRED_CSV_COLUMNS = array(
			'Description',
			'Visitor', // full name
			'Email',
			'Join Mail List?',
			'First Time?',
			'Fixer Station',
			'Item Type',
			'Status ID'
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
		if ( self::current_user_can_import_items() ) {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'do_ajax_import' ) );
		} // endif

	} // function

	/**
	 * Get a boolean indicating whether the current user has authorization to perform this action
	 * @return boolean
	 */
	public static function current_user_can_import_items() {
		// Only users who are able to read private emails should be able to do this
		//  because when we import we need to look them up by email address
		
		$result =
			current_user_can( 'create_'			. User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) &&
			current_user_can( 'read_private_'	. User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL );
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

		$nonce				= isset( $_REQUEST[ '_wpnonce' ] )					? $_REQUEST[ '_wpnonce' ] : '';
		$is_reload			= isset( $_REQUEST[ 'reload' ] )					? filter_var( $_REQUEST[ 'reload' ], FILTER_VALIDATE_BOOLEAN ) : FALSE;
		$attachment_id		= isset( $_REQUEST[ 'item-import-attachment-id' ] )	? $_REQUEST[ 'item-import-attachment-id' ] : NULL;
		$event_key			= isset( $_REQUEST[ 'item-import-event' ] )			? wp_unslash( $_REQUEST[ 'item-import-event' ] ) : NULL;
		$file_upload_desc	= isset( $_FILES[ 'item-import-file-name' ] )		? $_FILES[ 'item-import-file-name' ] : NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_ACTION );
		if ( ! $is_valid_nonce ) {
			
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
			
		} else {
			
			if ( $is_reload ) {
				
				$view = Item_Import_Admin_View::create();
				// FIXME I want the user to select the event if they're reloading
				if ( isset( $event_key ) ) {
					$view->set_event_key_string( $event_key );
				} // endif
				$content = $view->get_form_contents();
				$form_response->set_html_data( $content );
			
			} elseif ( isset( $attachment_id ) ) {
				
				// This is the second step, so do the import here
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
				if ( ! isset( $file_upload_desc ) ) {
					$err_msg = __( 'Please select a file for importing.', 'reg-man-rc' );
					$form_response->add_error( 'item-import-file-name', '', $err_msg );
				} else {
					if ( ! is_array( $file_upload_desc ) || ! isset( $file_upload_desc[ 'type' ] ) || ! isset( $file_upload_desc[ 'tmp_name' ] ) ) {
						$err_msg = __( 'The file could not be uploaded to the server.', 'reg-man-rc' );
						$form_response->add_error( 'item-import-file-name', '', $err_msg );
						$result = FALSE;
					} else {

						$file_name = $file_upload_desc[ 'tmp_name' ];
						$is_valid_file = self::get_is_valid_csv_file( $file_name, $form_response );
						if ( $is_valid_file !== FALSE ) {
							$file_move_result = self::move_file_to_uploads( $file_upload_desc, $form_response );
							if ( $file_move_result !== FALSE ) {
								$attachment_id = $file_move_result;
								$view = Item_Import_Admin_View::create();
								if ( isset( $event_key ) ) {
									$view->set_event_key_string( $event_key );
								} // endif
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
				$form_response->add_error( 'item-import-file-name', '', $err_msg );
				$result = FALSE;
			} else {
				$result = TRUE; // Assume it's ok then look for a problem
				$required_columns = self::$REQUIRED_CSV_COLUMNS;
				foreach ( $required_columns as $col ) {
					if ( ! in_array( $col, $header_array ) ) {
						/* translators: %s is the name of a CSV column for item registration import data like 'Email' or 'Item Desc' */
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
//		Error_Log::var_dump( $upload_result );

		if ( is_array( $upload_result ) && isset( $upload_result['error'] ) ) {
			/* translators: %s is an error message returned from Wordpress function wp_handle_upload */
			$err_msg = sprintf( __( 'The file could not be uploaded to the server. Error message: %s.', 'reg-man-rc' ), $upload_result['error'] );
			$form_response->add_error( 'item-import-file-name', '', $err_msg );
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
						/* translators: %s is the name of a CSV column for item registration import data like 'Email' or 'Item Desc' */
						$err_msg = sprintf( __( 'The CSV file does not contain the required item data for "%s".', 'reg-man-rc' ), $col );
						$form_response->add_error( 'item-import-file-name[' . $col . ']', '', $err_msg );
						$result = FALSE;
					} // endif
				} // endfor
				$event_key = $event->get_key_string();

				$item_desc_index = array_search( 'Description', $header_array );
				$full_name_index = array_search( 'Visitor', $header_array );
				$email_index = array_search( 'Email', $header_array );
				$join_mail_list_index = array_search( 'Join Mail List?', $header_array );
				$is_first_time_index = array_search( 'First Time?', $header_array );
				$fixer_station_index = array_search( 'Fixer Station', $header_array );
				$item_type_index = array_search( 'Item Type', $header_array );
				$status_id_index = array_search( 'Status ID', $header_array );
				
				if ( $result === TRUE ) {
					$record_count = 0;
					$line_number = 2; // line 2 is the first row of data after the header

					// Visitors may bring multiple items so rather than creating a new instance each time, I'll cache them
					$visitor_cache = array();

					while ( ( $data_array = fgetcsv( $handle ) ) !== FALSE ) {
						
						// First, get or create the visitor record
						$visitor = NULL; // Make sure we get a new visitor record each time
						
						$full_name = $data_array[ $full_name_index ];
						$email = $data_array[ $email_index ];
						$is_join = filter_var( $data_array[ $join_mail_list_index ], FILTER_VALIDATE_BOOLEAN );
						$is_first_time = filter_var( $data_array[ $is_first_time_index ], FILTER_VALIDATE_BOOLEAN );
						
						$public_name = Visitor::get_default_public_name( $full_name );
						$first_event_key = $is_first_time ? $event_key : NULL;

						$cache_key = ! empty( $email ) ? $email : $full_name;
						if ( isset( $visitor_cache[ $cache_key ] ) ) {
							$visitor = $visitor_cache[ $cache_key ]; 
						} else {
							$visitor = Visitor::get_visitor_by_email_or_full_name( $email, $full_name );
							if ( isset( $visitor ) ) {
								$visitor_cache[ $cache_key ] = $visitor;
							} // endif
						} // endif

						if ( ! isset( $visitor ) ) {

							// Create the new visitor record
							$visitor = Visitor::create_visitor( $public_name, $full_name, $email, $first_event_key, $is_join );
							if ( ! isset( $visitor ) ) {
								
								/* translators: %s is a line number in a file */
								$err_msg = sprintf( __( 'Unable to create visitor record for the item at line number %s.', 'reg-man-rc' ), $file_path );
								$form_response->add_error( "item-import-attachment-id[$line_number]" , '', $err_msg );

							} else {
								
								$visitor_cache[ $cache_key ] = $visitor;
								
							} // endif
							
						} // endif

						if ( isset( $visitor ) ) {
							
							// We can only create item if we have the visitor record
							$item_desc = $data_array[ $item_desc_index ];
							
							$fixer_station_text = trim( $data_array[ $fixer_station_index ] );
							$item_type_text = trim( $data_array[ $item_type_index ] );
							$item_status_id = trim( $data_array[ $status_id_index ] );

							$fixer_station = Fixer_Station::get_fixer_station_by_name( $fixer_station_text );
							$item_type = Item_Type::get_item_type_by_name( $item_type_text );
							$item_status = Item_Status::get_item_status_by_id( $item_status_id );
							
							$insert_result = Item::create_new_item( $item_desc, $fixer_station, $item_type, $event_key, $visitor, $item_status );

							if ( empty( $insert_result ) ) {
								/* translators: %s is a line number in a file */
								$err_msg = sprintf( __( 'Unable to import the item record at line number %s.', 'reg-man-rc' ), $file_path );
								$form_response->add_error( "item-import-attachment-id[$line_number]" , '', $err_msg);
							} else {
								$record_count++;
							} // endif
							
						} // endif
						
						$line_number++;
						
					} // endwhile
					
					$head = __( 'Item import complete', 'reg-man-rc' );
					
					$details = array();
					
					$line_count = $line_number - 2;
					/* Translators: %s is a count of lines in a file */
					$details[] = sprintf( _n( '%s line read from the file.', '%s lines read from the file.', $line_count, 'reg-man-rc' ), number_format_i18n( $line_count ) );

					/* Translators: %s is a count of lines in a file */
					$details[] = sprintf( _n( '%s new item created.', '%s new items created.', $record_count, 'reg-man-rc' ), number_format_i18n( $record_count ) );
					
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
							
							// Pass the event key -- right now we only do this inside the Admin Calendar
							$input_list->add_hidden_input( 'item-import-event', $event_key );
							
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
			$msg_format = __( 'Unable to delete item importer attachment with ID: %1$s', 'reg-man-rc' );
			$msg = sprintf( $msg_format, $attachment_id );
			Error_Log::log_msg( $msg );
		} // endif
	} // function
} // class