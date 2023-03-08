<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Geographic_Position;

/**
 * The venue controller
 *
 * This class provides the controller function for working with venues
 *
 * @since v0.1.0
 *
 */
class Venue_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Venue::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

	} // function

/**
 * Handle a post save event for my post type
 *
 * @param	int		$post_id	The ID of the post being saved
 * @return	void
 *
 * @since v0.1.0
 *
 */
	public static function handle_post_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Don't do anything during an autosave
			return;
		} else {
			$venue = Venue::get_venue_by_id( $post_id );
			if ( ! empty( $venue ) ) {
				if ( isset( $_POST[ 'venue_location_input_flag'] ) ) {
					// Update the info for this venue
					$loc = isset( $_POST[ 'venue_location' ] ) ? $_POST[ 'venue_location' ] : NULL;
					$lat_lng = isset( $_POST[ 'venue_lat_lng' ] ) ? stripslashes( $_POST[ 'venue_lat_lng' ] ) : NULL;
					$zoom = isset( $_POST[ 'venue_map_zoom' ] ) ? $_POST[ 'venue_map_zoom' ] : NULL;
					$venue->set_location( $loc );
					if ( ! empty( $loc ) ) {
						$geo = isset( $lat_lng ) ? Geographic_Position::create_from_google_map_marker_position_string( $lat_lng ) : NULL;
						$venue->set_geo( $geo );
						$venue->set_map_marker_zoom_level( $zoom );
					} else {
						$venue->set_geo( NULL );
						$venue->set_map_marker_zoom_level( NULL );
					} // endif
				} // endif
			} // endif
		} // endif
	} // function

} // class