<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\View\Admin\Event_Category_Admin_View;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;

/**
 * The controller used on the backend admin interface for calendars
 *
 * This class provides the controller function for working with calendars on the backend
 *
 * @since v0.1.0
 *
 */
class Calendar_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Calendar::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

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
			$calendar = Calendar::get_calendar_by_post_id( $post_id );
			if ( !empty( $calendar ) ) {

				// Update the iCal feed settings
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options, so don't delete anything
				$feed_flag = isset( $_POST[ 'ical_feed_flag' ] ) ? TRUE : FALSE;
				if ( $feed_flag ) {
					$has_feed		= isset( $_POST[ 'has-ical-feed' ] )	? ( $_POST[ 'has-ical-feed' ] == '1' )	: FALSE;
					$feed_name		= isset( $_POST[ 'ical-feed-name' ] )	? trim( $_POST[ 'ical-feed-name' ] )	: NULL;
					$has_subscribe	= isset( $_POST[ 'has-ical-feed-subscribe-button' ] );
					if ( $has_feed && ! empty( $feed_name ) ) {
						$calendar->set_icalendar_feed_name( $feed_name );
					} else {
						$calendar->set_icalendar_feed_name( NULL ); // Remove the feed name (and thus the feed)
					} // endif
					flush_rewrite_rules(); // This needs to be done before the feed can be used
					if ( $has_feed ) {
						$calendar->set_icalendar_is_show_subscribe_button( $has_subscribe );
					} // endif
				} // endif

				// Update the event status
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				$categories_flag = isset( $_POST[ 'event_status_selection_flag' ] ) ? TRUE : FALSE;
				if ( $categories_flag ) {
					// First check to see if it's set then make it an array
					$posted_statuses = isset( $_POST['event_status'] ) ? $_POST['event_status'] : NULL;
					if ( empty( $posted_statuses ) ) {
						// No event statuses selected so set it to NULL (remove any assignment)
						$calendar->set_event_status_array( NULL );
					} else {
						// We will assign an array of statuses even if the user only supplied one value
						$status_id_array = is_array( $posted_statuses ) ? $posted_statuses : array( $posted_statuses );
						$calendar->set_event_status_array( $status_id_array );
					} // endif
				} // endif

				// update the event categories
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				$categories_flag = isset( $_POST['event_category_selection_flag'] ) ? TRUE : FALSE;
				if ( $categories_flag ) {
					// First check to see if it's set then make it an array
					$input_name = Event_Category_Admin_View::INPUT_NAME;
					$category = isset( $_POST[ $input_name ] ) ? $_POST[ $input_name ] : NULL;
					if ( empty( $category ) ) {
						// No event categories selected so assign the default
						$category_array = array( Event_Category::get_default_event_category() );
						$calendar->set_event_category_array( $category_array );
					} else {
						// We will assign an array of categories even if the user only supplied one value
						$category_id_array = is_array( $category ) ? $category : array( $category );
						$category_array = array();
						foreach ( $category_id_array as $category_id ) {
							$category = Event_Category::get_event_category_by_id( $category_id );
							if ( isset( $category ) ) {
								$category_array[] = $category;
							} // endif
						} // endfor
						// Make sure the calendar has at least the default category
						if ( empty( $category_array ) ) {
							$category_array = array( Event_Category::get_default_event_category() );
						} // endif
						$calendar->set_event_category_array( $category_array );
					} // endif
				} // endif

				// Update the settings for show past events
				$past_events_flag = isset( $_POST[ 'past_events_selection_flag' ] ) ? TRUE : FALSE;
//				Error_Log::var_dump( $past_events_flag );
				if ( $past_events_flag ) {
					// Show past events? Default to YES
					$posted_flag_val = isset( $_POST[ 'is-show-past-events' ] ) ? $_POST[ 'is-show-past-events' ] : '1';
					$is_show_past_events = ( $posted_flag_val !== '0' );
//					Error_Log::var_dump( $posted_flag_val, $is_show_past_events );
					$calendar->set_is_show_past_events( $is_show_past_events );
				} // endif

				// Update the view formats
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				$formats_flag = isset( $_POST[ 'view_format_selection_flag' ] ) ? TRUE : FALSE;
				if ( $formats_flag ) {
					// First check to see if it's set then make it an array
					$posted_formats = isset( $_POST['view_format'] ) ? $_POST['view_format'] : NULL;
					if ( empty( $posted_formats ) ) {
						// No view formats selected so set it to NULL (remove any assignment)
						$calendar->set_view_format_ids_array( NULL );
					} else {
						// We will assign an array of formats even if the user only supplied one value
						$format_id_array = is_array( $posted_formats ) ? $posted_formats : array( $posted_formats );
						$calendar->set_view_format_ids_array( $format_id_array );
					} // endif
				} // endif

				// Update the durations
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't delete anything
				$durations_flag = isset( $_POST[ 'durations_selection_flag' ] ) ? TRUE : FALSE;
				if ( $durations_flag ) {
					// First check to see if it's set then make it an array
					$posted_durations = isset( $_POST['duration'] ) ? $_POST['duration'] : NULL;
					if ( empty( $posted_durations ) ) {
						// No durations selected so set it to NULL (remove any assignment)
						$calendar->set_duration_ids_array( NULL );
					} else {
						// We will assign an array of formats even if the user only supplied one value
						$duration_id_array = is_array( $posted_durations ) ? $posted_durations : array( $posted_durations );
						$calendar->set_duration_ids_array( $duration_id_array );
					} // endif
				} // endif

				// Update the registration calendars
				// Make sure the selection flag is there and we're not doing a quick edit
				// If the flag is not set then the user has no options to select, so don't do anything
				$reg_flag = isset( $_POST[ 'registration_calendars_input_flag' ] ) ? TRUE : FALSE;
				if ( $reg_flag ) {
					if ( isset( $_POST[ Calendar::CALENDAR_TYPE_VISITOR_REG ] ) ) {
						Settings::set_visitor_registration_calendar_post_id( $_POST[ Calendar::CALENDAR_TYPE_VISITOR_REG ] );
					} // endif
					if ( isset( $_POST[ Calendar::CALENDAR_TYPE_VOLUNTEER_REG ] ) ) {
						Settings::set_volunteer_registration_calendar_post_id( $_POST[ Calendar::CALENDAR_TYPE_VOLUNTEER_REG ] );
					} // endif
				} // endif

			} // endif
		} // endif
	} // function

} // class