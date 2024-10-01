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
				
				// Update the post status (privacy setting)
				$new_status = 'private'; // Always private status
				
				// I need to remove this action from save_post to avoid an infinite loop
				remove_action( 'save_post_' . Visitor::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

				// Modify the post status
				wp_update_post( array(
					'ID' 			=> $post_id,
					'post_status'	=> $new_status
				) );
				
				// Add the action back
				add_action( 'save_post_' . Visitor::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
					
				
				// Update the personal info
				if ( isset( $_POST[ 'visitor_details_input_flag' ] ) ) {

					// Full name and email
					$full_name = isset( $_POST[ 'visitor_full_name' ] ) ? stripslashes( trim( $_POST[ 'visitor_full_name' ] ) ) : NULL;
					$email = isset( $_POST[ 'visitor_email' ] ) ? stripslashes( trim( $_POST[ 'visitor_email' ] ) ) : NULL;
					$visitor->set_personal_info( $full_name, $email );

					// Join Mail List?
					$is_join = isset( $_POST[ 'visitor_join_mail_list' ] ) ? boolval( $_POST[ 'visitor_join_mail_list' ] ) : FALSE;
					$visitor->set_is_join_mail_list( $is_join );
					
					// First event
					$first_event_key = isset( $_POST[ 'visitor_first_event_key' ] ) ? $_POST[ 'visitor_first_event_key' ] : NULL;
					$visitor->set_first_event_key( $first_event_key );
					
				} // endif

			} // endif
			
		} // endif
	} // function

} // class