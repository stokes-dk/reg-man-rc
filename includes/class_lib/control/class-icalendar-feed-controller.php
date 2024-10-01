<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\ICalendar_Feed_Writer;
use Reg_Man_RC\Model\Settings;

/**
 * The iCalendar Feed controller
 *
 * This class provides the controller function for managing iCalendar feeds
 *
 * @since v0.1.0
 *
 */
class ICalendar_Feed_Controller {

	/**
	 * Register this controller
	 * 
	 * @since 0.1.0
	 */
	public static function register() {

		// Create the feeds for calendars that are configured for them
//		$all_feed_names = array(); // Keep track of the feed names we create

		$ical_feed_calendars = Calendar::get_all_calendars_with_icalendar_feed();
		foreach( $ical_feed_calendars as $calendar ) {
			$feed_name = $calendar->get_icalendar_feed_name();
//			$all_feed_names[] = $feed_name;
			$writer = ICalendar_Feed_Writer::create_for_calendar( $calendar );
			add_feed( $feed_name, array( $writer, 'handle_feed_request' ) );
		} // endfor
		
		// We have a separate feed for volunteer registrations
		if ( Settings::get_is_create_volunteer_calendar_feed() ) {
			$feed_name = Settings::get_volunteer_calendar_feed_name();
			$calendar = Calendar::get_volunteer_registration_calendar();
			$writer = ICalendar_Feed_Writer::create_for_calendar( $calendar );
			add_feed( $feed_name, array( $writer, 'handle_feed_request' ) );
		} // endif
		
	} // function
	
} // class