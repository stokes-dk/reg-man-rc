<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The visitor controller
 *
 * This class provides the controller function for working with visitors
 *
 * @since v0.1.0
 *
 */
class Visitor_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Visitor::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );

		// Check to make sure that an item type is selected and show an error message if not
		add_action( 'edit_form_top', array( __CLASS__, 'show_edit_form_messages' ) );

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
			
			$visitor = Visitor::get_visitor_by_id( $post_id );
			if ( ! empty( $visitor ) ) {
				
				// If there are any missing required settings then I will set this post to DRAFT status later
				$new_status = 'private'; // Always private status so visitors are never visible to the public
				
				// Check if there is a duplicate record ALWAYS, e.g. when doing untrash
				// To get the email we'll look whether one has been supplied during this save
				//  or (like during untrash) we need to get it from the record itself
				if ( isset( $_POST[ 'visitor_details_input_flag' ] ) ) {
					$email = isset( $_POST[ 'visitor_email' ] ) ? stripslashes( trim( $_POST[ 'visitor_email' ] ) ) : NULL;
				} else {
					$email = $visitor->get_email();
				} // endif
				
				$duplicate_array = Visitor::get_all_visitors_by_email( $email, $post_id );
				if ( ! empty( $duplicate_array ) ) {
					$new_status = 'draft';
				} // endif
				
				// Update the personal info
				if ( isset( $_POST[ 'visitor_details_input_flag' ] ) ) {

					// Full name and email
					$full_name = isset( $_POST[ 'visitor_full_name' ] ) ? stripslashes( trim( $_POST[ 'visitor_full_name' ] ) ) : NULL;
					$visitor->set_personal_info( $full_name, $email );

					// Join Mail List?
					$is_join = isset( $_POST[ 'visitor_join_mail_list' ] ) ? boolval( $_POST[ 'visitor_join_mail_list' ] ) : FALSE;
					$visitor->set_is_join_mail_list( $is_join );
					
					// First event
					$first_event_key = isset( $_POST[ 'visitor_first_event_key' ] ) ? $_POST[ 'visitor_first_event_key' ] : NULL;
					$visitor->set_first_event_key( $first_event_key );
					
				} // endif

				// I need to remove this action from save_post to avoid an infinite loop
				remove_action( 'save_post_' . Visitor::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

				// Modify the post status
				wp_update_post( array(
					'ID' 			=> $post_id,
					'post_status'	=> $new_status
				) );
				
				// Add the action back
				add_action( 'save_post_' . Visitor::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
					
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
		if ( Visitor::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$visitor = Visitor::get_visitor_by_id( $post->ID );

			if ( ! empty( $visitor ) ) {

				$error_format =		'<div class="error below-h2"><p>%s</p></div>';
				$warning_format =	'<div class="notice notice-warning below-h2 is-dismissible"><p>%s</p></div>';
				
				// If the email address is already in use then add an error message
				$email = $visitor->get_email();
				$duplicate_array = Visitor::get_all_visitors_by_email( $email, $post->ID );
				
				foreach( $duplicate_array as $duplicate ) {
					$display_name = esc_html__( $duplicate->get_display_name() );
					$edit_url = $duplicate->get_edit_url();
					if ( ! empty( $edit_url ) ) {
						$display_name = "<a href=\"$edit_url\" target=\"_blank\">$display_name</a>";
					} // endif
					/* Translators: %1$s is a visitor's name */
					$msg_fmt = __( 'A visitor record with this email address already exists: %1$s', 'reg-man-rc' );
					$msg = sprintf( $msg_fmt, $display_name );
					printf( $error_format, $msg );
				} // endfor

				if ( ( $post->post_status == 'draft' ) && ( ! empty( $duplicate_array ) ) ) {
					$trash_url = get_delete_post_link( $post );
					$trash_text = esc_html__( 'trash', 'reg-man-rc' );
					$trash_link = "<a href=\"$trash_url\">$trash_text</a>";
					/* Translators: %1$s is a link to trash the record */
					$msg = __( 'The visitor record is saved as a DRAFT until problems are resolved or you %1$s it.', 'reg-man-rc' );
					printf( $warning_format, sprintf( $msg, $trash_link ) );
				} // endif

			} // endif

		} // endif
		
	} // function
	
} // class