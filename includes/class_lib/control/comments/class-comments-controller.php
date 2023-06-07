<?php
namespace Reg_Man_RC\Control\Comments;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Event_Key;

/**
 * This class handles contoller function for comments.
 *
 * @since v0.4.0
 *
 */
class Comments_Controller {

	private function __construct() {
	} // constructor
	
	/**
	 * Perform the necessary steps to register this controller with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 *
	 * @since	v0.4.0
	 */
	public static function register() {

		// Filter the data when inserting a post so we can turn comments on or off for new posts
		add_filter( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 10, 2 );
	
	} // function

	/**
	 * Determine whether comments are open for the specified post.
	 * @param boolean $open
	 * @param int $post_id
	 * @return boolean
	 */
	public static function filter_comments_open( $open, $post_id ) {
		
		return $open;
		
		/*
		 *  FIXME - I need to implement this properly
		 *    "Automatically close comments on past events"
		 *      - Use system setting (current Yes, after 14 days)
		 *      - If this is turned on the I need to close comments 14 days after an event
		 */
		
		$post_type = get_post_type( $post_id );
		switch( $post_type ) {

			case Internal_Event_Descriptor::POST_TYPE:
				$event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post_id );
				$is_recurring = $event_desc->get_event_is_recurring();
				Error_Log::var_dump( $is_recurring, get_query_var( Event_Key::RECUR_ID_QUERY_ARG_NAME ) );
				// FIXME - If this event is a repeating one then how do we tell if it's complete?  That method does not
				//  belong in the custom post type, it belongs in the Event class
				if ( ! isset( $event_desc )  /* || ( ! $event_desc->get_is_complete() ) */ ) {
					$result = $open;
				} else {
					$result = Settings::get_is_allow_event_comments();
				} // endif
				break;

			default:
				$result = $open; // don't change the value
				break;

		} // endswitch
		return $result;
	} // function
	
} // class

