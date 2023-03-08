<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Internal_Event_Descriptor;

/**
 * The controller for comments
 *
 * This class provides the controller function for comments
 *
 * @since v0.1.0
 *
 */
class Comments_Controller {

	public static function register() {

		// register the filter to determine whether comments should be open
		add_action( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 10, 2 );

		// See also Volunteer_Comments_View

	} // function

	public static function filter_comments_open( $open, $post_id ) {
		$post_type = get_post_type( $post_id );
		switch( $post_type ) {

			case Internal_Event_Descriptor::POST_TYPE:
				$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post_id );
				// FIXME - If this event is a repeating one then how do we tell if it's complete?  That method does not
				//  belong in the custom post type, it belongs in the Event class
				if ( ! isset( $event )  /* || ( ! $event->get_is_complete() ) */ ) {
					$result = $open;
				} else {
					$result = Settings::get_is_allow_event_comments();
				} // endif
				break;

			case Item::POST_TYPE:
				$result = Settings::get_is_allow_item_comments();
				break;

			default:
				$result = $open; // don't change the value
				break;

		} // endswitch
		return $result;
	} // function

} // class