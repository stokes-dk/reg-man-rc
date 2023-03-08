<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;

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

		// Add an action listener for post delete so that I can clean up any orphaned visitor side table records
		add_action( 'before_delete_post', array( __CLASS__, 'handle_before_delete_post' ), 10, 2 );

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
				// Update the personal info
				if ( isset( $_POST[ 'visitor_details_input_flag' ] ) ) {

					// Full name and email
					$full_name = isset( $_POST[ 'visitor_full_name' ] ) ? stripslashes( trim( $_POST[ 'visitor_full_name' ] ) ) : NULL;
					$email = isset( $_POST[ 'visitor_email' ] ) ? stripslashes( trim( $_POST[ 'visitor_email' ] ) ) : NULL;
					$visitor->set_personal_info( $full_name, $email );

				} // endif

			} // endif
		} // endif
	} // function

	/**
	 * Handle cleanup when deleting my custom post and make sure there are no orphaned visitor side table records
	 * @param	int			$post_id	The ID of the post being deleted
	 * @param	\WP_Post	$post		The post being deleted
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_before_delete_post( $post_id, $post ) {
		if ( $post->post_type === Visitor::POST_TYPE ) {
			$visitor = Visitor::get_visitor_by_id( $post_id );
			if ( isset( $visitor ) ) {
				$visitor->delete_personal_info();
			} // endif
		} // endif
	} // function

} // class