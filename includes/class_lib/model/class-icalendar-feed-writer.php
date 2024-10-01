<?php
declare( strict_types = 1 );
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Pub\Volunteer_Area;

/**
 * An object used to write an icalendar feed
 *
 * @since v0.7.0
 *
 */

class ICalendar_Feed_Writer {

	const VERSION = '2.0';
	const CALSCALE = 'GEORGIAN';
	const DATETIME_FORMAT = 'Ymd\THis\Z'; // The format used for all date/time values
	
	const EVENT_TRANSPARANCY_TRANSPARENT	= 'TRANSPARENT';
	const EVENT_TRANSPARANCY_OPAQUE			= 'OPAQUE';
	
	private $calendar;
	private $utc_timezone;
	
	private function __construct() {
	} // function
	
	/**
	 * Create an instance of this class for a calendar
	 * @param Calendar $calendar
	 * @return self
	 */
	public static function create_for_calendar( $calendar ) {
		$result = new self();
		$result->calendar = $calendar;
		return $result;
	} // function

	/**
	 * Get the calendar used to create this instance
	 * @return Calendar
	 */
	private function get_calendar() {
		return $this->calendar;
	} // function
	
	/**
	 * Get the array of events for this calendar
	 * Note that if the current request includes authentication cookies then we may get private events
	 * Two requests on the same instance of this class may result in two different arrays of events
	 * @return Event[]
	 */
	private function get_events_array() {
		
		$calendar = $this->get_calendar();
		$event_filter = Event_Filter::create_for_calendar( $calendar );
		// Is there any reason to feed events in the past???
		$event_filter->set_accept_dates_on_or_after_today();
		$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_DESCENDING );
		$result = Event::get_all_events_by_filter( $event_filter );

		return $result;
		
	} // function
	
	private function get_feed_name() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_name();
		return $result;
	} // function
	
	private function get_feed_title() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_title();
		return $result;
	} // function
	
	private function get_file_name() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_file_name();
		return $result;
	} // function
	
	/**
	 * Handle a request for this feed
	 */
	public function handle_feed_request() {
		
		$data = $this->get_feed_content();
		
		if ( isset( $_REQUEST[ 'preview' ] ) ) {
			header( 'Content-type: text/html' );
			echo "<textarea readonly='true' style='width:100%; height:100%'>$data</textarea>";
		} else {
			$filename = self::get_file_name();
			header( 'Content-Description: File Transfer' );
			header( "Content-Disposition: attachment; filename=$filename" );
			header( 'Content-type: text/calendar' );
			// header( 'Pragma: 0' );
			// header( 'Expires: 0' );
			echo $data;
		} // endif
		
	} // function
	
	private function get_prod_id() {
		$blog_name = get_bloginfo( 'name' );
		$feed_name = $this->get_feed_name();
		$result = "-//$blog_name//$feed_name//EN";
		return $result;
	} // function

	private function get_feed_timestamp() {
		$utc = $this->get_utc_timezone();
		$now = new \DateTime( 'now', $utc );
		$result = $now->format( self::DATETIME_FORMAT );
		return $result;
	} // function
	
	/**
	 * Get the Volunteer specified in this request or NULL if no volunteer was specified
	 * @return	Volunteer|NULL
	 */
	private function get_request_volunteer() {

		$result = NULL; // assume there is no volunteer or it can't be found

		$calendar = $this->get_calendar();

		if ( $calendar->get_calendar_type() === Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
			$vol_id = isset( $_REQUEST[ 'vol-id' ] ) ? trim( $_REQUEST[ 'vol-id' ] ) : '';
			$result = Volunteer::get_volunteer_for_icalendar_feed_id( $vol_id );
		} // endif
		
		return $result;
		
	} // function
	
	private function get_feed_content() {

		$timestamp = $this->get_feed_timestamp();
		
		$volunteer = $this->get_request_volunteer();
		
		$calendar = $this->get_calendar();
		if ( empty( $volunteer ) && ( $calendar->get_calendar_type() === Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) ) {
			// If the calendar is for the volunteer area and there is no volunteer in the request then return no events
			$events_array = array();
		} else {
			// In the normal case we'll get all the event for this calendar
			// If the calendar is for the volunteer area and there IS a volunteer then we'll filter events as we build them
			$events_array = $this->get_events_array();
		} // endif
		
		$lines = array(); // we'll create an array of lines then concatenate them with CRLFs
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:' . self::VERSION;
		$lines[] = self::build_text_property( 'PRODID', $this->get_prod_id() );
		$lines[] = 'CALSCALE:' . self::CALSCALE;

		// TODO: X-WR-CALDESC or X-WR-CALNAME ???
		// Is this a feed title, calendar name or what?  Do we have a calendar description?
		$lines[] = self::build_text_property( 'X-WR-CALNAME', $this->get_feed_title() );

		$timezone_string = wp_timezone_string();
		if ( ! empty( $timezone_string ) ) {
			$lines[] = "X-WR-TIMEZONE:$timezone_string";
		} // endif
		
		foreach( $events_array as $event ) {
			$event_data = $this->get_vevent_for_event( $event, $timestamp, $volunteer );
			if ( ! empty( $event_data ) ) {
				$lines[] = $event_data;
			} // endif
		} // endfor
		
		$lines[] = 'END:VCALENDAR';
		$result = implode( "\r\n", $lines );
		
		return $result;
		
	} // function
	
	private function get_utc_timezone() {
		if ( ! isset( $this->utc_timezone ) ) {
			$this->utc_timezone = new \DateTimeZone( 'UTC' );
		} // endif
		return $this->utc_timezone;
	} // function

	/**
	 * Get a VEVENT object string for the specified event
	 * @param	Event		$event
	 * @param	string		$timestamp
	 * @param	Volunteer	$volunteer
	 * @return NULL|string
	 */
	private function get_vevent_for_event( $event, $timestamp, $volunteer ) {

		$utc = $this->get_utc_timezone();
		
		$vol_reg = NULL; // Assume there's no volunteer registration
		if ( ! empty( $volunteer ) ) {
			$event_key = $event->get_key_string();
			$vol_reg = Volunteer_Registration::get_registration_for_volunteer_and_event( $volunteer, $event_key );
			if ( empty( $vol_reg ) ) {
				// This volunteer is not registered for this event
				return NULL; // <== EXIT POINT!
			} // endif
		} // endif
		
		$event_descriptor = $event->get_event_descriptor();
		$uid = $event_descriptor->get_event_uid();
		$is_recurring = $event_descriptor->get_event_is_recurring();
		
		$start_local = $event->get_start_date_time_object();
		$end_local = $event->get_end_date_time_object();
		
		if ( ! ( $start_local instanceof \DateTimeInterface ) || ! ( $end_local instanceof \DateTimeInterface ) ) {
			$result = NULL; // We must have at least start and end times
		} else {
			$start_utc = new \DateTime( $start_local->format( \DateTimeInterface::ISO8601 ) );
			$start_utc->setTimezone( $utc );
			$end_utc = new \DateTime( $end_local->format( \DateTimeInterface::ISO8601 ) );
			$end_utc->setTimezone( $utc );

			$summary = $event->get_summary();
			if ( ! empty( $summary ) ) {
				$remove_breaks = TRUE;
				$summary = wp_strip_all_tags( $summary, $remove_breaks ); // Wordpress allows html tags in title, remove any
				$summary = trim( $summary );
			} // endif
			
			$class_obj = $event->get_class();
			$status_obj = $event->get_status();

			$desc = $event->get_description();
			if ( ! empty( $desc ) ) {
				$remove_breaks = TRUE;
				$desc = wp_strip_all_tags( $desc, $remove_breaks );
				$desc = trim( $desc );
			} // endif
			
			$location = $event->get_location();
			if ( ! empty( $location ) ) {
				$remove_breaks = TRUE;
				$location = wp_strip_all_tags( $location, $remove_breaks );
				$location = trim( $location );
			} // endif
			
			$geo = $event->get_geo();

			// Use "TRANSP" (Time Transparancy) to mark as free (transparent) or busy (opaque)
			// Volunteer registrations are opaque, all others are transparent
			if ( isset( $volunteer ) ) {
				
				$event_page_url = Volunteer_Area::get_href_for_event_page( $event );
				$transparancy = isset( $vol_reg ) ? self::EVENT_TRANSPARANCY_OPAQUE : self::EVENT_TRANSPARANCY_TRANSPARENT;

			} else {
				
				$event_page_url = $event->get_event_page_url();
				$transparancy = self::EVENT_TRANSPARANCY_TRANSPARENT;
					
			} // endif
			
			$category_array = $event->get_categories();
			$lines = array(); // we'll create an array of lines then concatenate them with CRLFs
			$lines[] = 'BEGIN:VEVENT';

			$lines[] = 'DTSTART:' . $start_utc->format( self::DATETIME_FORMAT );
			$lines[] = 'DTEND:' . $end_utc->format( self::DATETIME_FORMAT );

			$lines[] = 'DTSTAMP:' . $timestamp;
			
			$lines[] = self::build_text_property( 'UID', $uid ); // In case of long UIDs from other providers
			if ( $is_recurring ) {
				$lines[] = self::build_text_property( 'RECURRENCE-ID', $start_utc->format( self::DATETIME_FORMAT ) );
			} // endif

			// Use "TRANSP" (Time Transparancy) to mark as free (transparent) or busy (opaque)
			if ( isset( $transparancy ) ) {
				$lines[] = 'TRANSP:' . $transparancy;	
			} // endif

			$lines[] = self::build_text_property( 'SUMMARY', $summary );

			if ( isset( $class_obj ) ) {
				$lines[] = 'CLASS:' . $class_obj->get_id();
			} // endif

			if ( isset( $status_obj ) ) {
				$lines[] = 'STATUS:' . $status_obj->get_id();
			} // endif

			if ( ! empty( $location ) ) {
				$lines[] = self::build_text_property( 'LOCATION', $location );
			} // endif

			if ( ! empty( $desc ) ) {
				$desc = strip_tags( $desc ); // remove the html tags if any exist
				$lines[] = self::build_text_property( 'DESCRIPTION', $desc );
			} // endif

			if ( ! empty( $geo ) ) {
				$lines[] = 'GEO:' . $geo->get_as_iCalendar_string();
			} // endif

			if ( is_array( $category_array ) && ( ! empty( $category_array ) ) ) {
				$lines[] = self::build_text_property_from_array( 'CATEGORIES', $category_array );
			} // endif

			if ( ! empty( $event_page_url ) ) {
				$lines[] = self::build_text_property( 'URL', $event_page_url );
			} // endif
			
			$lines[] = 'END:VEVENT';

			$result = implode( "\r\n", $lines );

		} // endif
		
		return $result;
		
	} // function
	
	private static function build_text_property( $property_name, $text_value ) {
		// A text property value must have its slashes, commas and semicolons escaped
		// It must be a single line of text so all line breaks must be replaced with the characters '\n'
		// They must also be limited to 75 octets in length and 'folded' onto multiple lines if they exceed that length
		$text_value = html_entity_decode( $text_value );
		$esc_text_value = str_replace( ['\\', ',', ';'], ['\\\\', '\,', '\;'], $text_value ); // escape slashes, commas and semis
		$single_line = str_replace( ["\r\n", "\n\r", "\n", "\r"], '\n', $esc_text_value ); // replace all line breaks with '\n' chars
		$result = self::multibyte_fold_text( "$property_name:$single_line" );
		return $result;
	} // function

	private static function build_text_property_from_array( $property_name, $text_value_array ) {
		// A text property value must have its slashes, commas and semicolons escaped
		// It must be a single line of text so all line breaks must be replaced with the characters '\n'
		// They must also be limited to 75 octets in length and 'folded' onto multiple lines if they exceed that length
		$sanitized_values = array();
		foreach ( $text_value_array as $text_value ) {
			$text_value = html_entity_decode( $text_value );
			$esc_text_value = str_replace(['\\', ',', ';'], ['\\\\', '\,', '\;'], $text_value); // escape slashes, commas and semis
			// an array of text values is not likely to contain line breaks but we'll cover this case anyway
			$single_line = str_replace( ["\r\n", "\n\r", "\n", "\r"], '\n', $esc_text_value ); // replace all line breaks with '\n' chars
			$sanitized_values[] = $single_line;
		} // endfor
		$comma_separated = implode(',', $sanitized_values );
		$result = self::multibyte_fold_text( "$property_name:$comma_separated" );
		return $result;
	} // function

	private static function multibyte_fold_text($str, $max_line_len = 75) {
		// This function 'folds' a string into multiple lines if its length exceeds the maximum (75 octets by default)
		// The algorithm is made more complicated because we have to handle multibyte characters and we must fold
		//  lines on a character boundary.  The "chunk_split" function will not work properly because it may split
		//  multibyte characters between bytes.
		$sep = "\r\n "; // The separator we will insert between lines
		if ( strlen( $str ) <= $max_line_len ) {
			$result = $str; // the string does not require folding
		} else {
			$chars = preg_split( "//u", $str, -1, PREG_SPLIT_NO_EMPTY ); // divide string into an array of characters
			$curr_line_len = 0;
			$index = 0;
			foreach ( $chars as $char ) {
				$curr_char_len = strlen($char); // strlen counts the bytes not the characters
				if ( ( $curr_line_len + $curr_char_len ) > $max_line_len ) {
					// if this char would exceed the boundary we have to insert a separator
					array_splice( $chars, $index, 0, $sep );
					$curr_line_len = 1 + $curr_char_len; // for the blank space we inserted plus this char on the new line
					$index += 2; // the index of the next character has increased by one because of the insert above
				} else {
					// otherwise add the character to the current line
					$curr_line_len += $curr_char_len;
					$index++;
				} // endif
			} // endfor
			$result = implode( '', $chars ); // Put the chars back together as a single string
		} // endif
		return $result;
	} // function
	
} // class