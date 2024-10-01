<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Calendar;
use const Reg_Man_RC\PLUGIN_VERSION;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Calendar_Entry;
use Reg_Man_RC\Model\Mutable_Event_Descriptor_Proxy;
use Reg_Man_RC\Model\Recurrence_Rule;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Settings;

/**
 * The calendar controller
 *
 * This class provides the controller function for working with calenders
 *
 * @since v0.1.0
 *
 */
class Calendar_Controller {

	const CALENDAR_TYPE_INPUT_FIELD_NAME		= 'calendar_type';
	const CALENDAR_ID_INPUT_FIELD_NAME			= 'calendar_id';
	
	/**
	 * Register this controller
	 * 
	 * @since 0.1.0
	 */
	public static function register() {

		// Remove the previous / next links for my CPT
		add_filter( 'previous_post_link', array( __CLASS__, 'filter_adjacent_link' ), 10, 5 );
		add_filter( 'next_post_link',  array( __CLASS__, 'filter_adjacent_link' ), 10, 5 );

	} // function

	/**
	 * Filters the adjacent post link for my custom post type.
	 *
	 * @since 0.0.1
	 *
	 * @param string	$output		The adjacent post link.
	 * @param string	$format		Link anchor format.
	 * @param string	$link		Link permalink format.
	 * @param \WP_Post	$post		The adjacent post.
	 * @param string	$adjacent	Whether the post is previous or next.
	 */
	public static function filter_adjacent_link( $output, $format, $link, $post, $adjacent ) {
//		Error_Log::var_dump( $output, $format, $link, $post );
		if ( ( $post instanceof \WP_Post ) && ( $post->post_type == Calendar::POST_TYPE ) ) {
			$result = FALSE;
		} else {
			$result = $output;
		} // endif
		return $result;
	} // function

	/**
	 * Get the REST API route for the events feed for FullCalendar
	 * @return string
	 */
	private static function get_fullcalendar_rest_route() {
		return '/fullcalendar/json-events-feed/';
	} // function

	/**
	 * Get the URL for the JSON events feed for FullCalendar
	 * Note that this is an events feed with format and content specifically for FullCalendar.
	 * This feed is used by pages served from this site to display the events for this calendar using FullCalendar on the client.
	 * It is not an iCalendar feed.
	 * @return string
	 */
	public static function get_fullcalendar_json_events_feed_url() {
		$namespace = REST_API_Controller::get_namespace();
		$route = self::get_fullcalendar_rest_route();
		$blog_id = NULL; // I believe this is used for multi-site
		$path = 'wp-json/' . $namespace . $route;
		$result = get_home_url( $blog_id, $path ); // This is a front-end request not backend (which would be site_url)
		return $result;
	} // function

	/**
	 * Register the REST API endpoint to provide a JSON events feed to the calendar
	 * This is called by the REST_API_Controller
	 */
	public static function register_rest_endpoints() {
		
		// FullCalendar events feed
		$namespace = REST_API_Controller::get_namespace();
		$route = self::get_fullcalendar_rest_route();
		$args = array(
					'methods'				=> 'GET',
					'permission_callback'	=> array( __CLASS__, 'handle_permission_callback_for_fullcalendar_events_feed_request' ),
					'callback'				=> array( __CLASS__, 'handle_fullcalendar_events_feed_request' ),
		);
		register_rest_route( $namespace, $route, $args );
		
	} // function

	/**
	 * Handle the permission callback for a calendar feed request for JSON events for FullCalendar
	 * @param	\WP_REST_Request 	$request
	 */
	public static function handle_permission_callback_for_fullcalendar_events_feed_request( $request ) {
		
		$calendar = self::get_calendar_for_request( $request );

		// Ask the calendar if a user is required to access it, if no calendar then assume TRUE
		$user_required = isset( $calendar ) ? $calendar->get_is_login_required_for_fullcalendar_feed() : TRUE;

		// If a user is required then make sure we have one logged in, if no user required then we're always fine
		$result = $user_required ? is_user_logged_in() : TRUE;
		
		return $result;
		
	} // function

	/**
	 * Handle a calendar feed request for JSON events for FullCalendar
	 * @param	\WP_REST_Request 	$request
	 */
	public static function handle_fullcalendar_events_feed_request( $request ) {
		
//	Error_Log::var_dump( get_current_user_id() );
//Error_Log::var_dump( $request );

		$range_start_date_string	= isset( $request[ 'start' ] )					? $request[ 'start' ]				: '';
		$range_end_date_string		= isset( $request[ 'end' ] )					? $request[ 'end' ]					: '';
		$show_past_events_string	= isset( $request[ 'is_show_past_events' ] )	? $request[ 'is_show_past_events' ]	: 'false';
		$author_string				= isset( $request[ 'event_author' ] )			? $request[ 'event_author' ]		: NULL;
		$nonce						= isset( $request[ '_wpnonce' ] )				? $request[ '_wpnonce' ]			: NULL;

		$calendar = self::get_calendar_for_request( $request );

		// Note that including a nonce allows the REST API to determine the user
		// If no nonce is present in the request then WordPress will always execute the request with no user
		// To require a nonce, we must check it in here in the request handler
		$is_nonce_required = isset( $calendar ) ? $calendar->get_is_nonce_required_for_fullcalendar_feed() : TRUE;
		$is_valid_nonce = $is_nonce_required ? wp_verify_nonce( $nonce, 'wp_rest' ) : TRUE;

//Error_Log::var_dump( $range_start_date_string, $range_end_date_string, $show_past_events_string, $author_string );
//Error_Log::var_dump( $calendar );
		
		$is_show_past_events = ( strtolower( $show_past_events_string ) !== 'false' );
//Error_Log::var_dump( $show_past_events_string, $is_show_past_events );

		$event_author_id = ( $author_string == 'author_mine' ) ? get_current_user_id() : 0;
//		Error_Log::var_dump( $author_string, $event_author_id );

		$local_tz = wp_timezone(); // Make sure we use local timezone for dates
		try {
			$start_date_time = new \DateTime( $range_start_date_string );
			$start_date_time->setTimezone( $local_tz );
		} catch ( \Exception $exc ) {
			/* translators: %1$s is an invalid date string */
			$msg = sprintf( __( 'An invalid start date was supplied to calendar: %1$s.', 'reg-man-rc' ), $range_start_date_string );
			Error_Log::log_exception( $msg, $exc );
			$start_date_time = NULL;
		} // endtry
		try {
			$end_date_time = new \DateTime( $range_end_date_string );
			$end_date_time->setTimezone( $local_tz );
		} catch ( \Exception $exc ) {
			/* translators: %1$s is an invalid date string  */
			$msg = sprintf( __( 'An invalid end date was supplied to calendar: %1$s.', 'reg-man-rc' ), $range_end_date_string );
			Error_Log::log_exception( $msg, $exc );
			$end_date_time = NULL;
		} // endtry

//Error_Log::var_dump( isset( $calendar), $start_date_time->format( 'Y-m-d H:i:s e' ), $end_date_time->format( 'Y-m-d H:i:s e' ) ); // datetimes with timezone

		if ( isset( $calendar ) && $is_valid_nonce && isset( $start_date_time ) && isset( $end_date_time ) ) {

			// When not showing past events we need to make sure that the start of the date range is no later than now
			// Also, make sure that the end of the data range is after the start, otherwise it doesn't make sense
			if ( ! $is_show_past_events ) {
				$now = new \DateTime( 'now', $local_tz );
				if ( $start_date_time < $now ) {
					$start_date_time = $now;
					// Make sure we haven't just moved the start date so it's after the end date
					if ( $end_date_time < $start_date_time ) {
						$end_date_time = $now;
					} // endif
				} // endif
			} // endif

			$entry_array = $calendar->get_calendar_entries_in_date_range( $start_date_time, $end_date_time, $event_author_id );
			$calendar_type = $calendar->get_calendar_type();
			$json_data = self::get_calendar_json_data( $entry_array, $calendar_type );

		} else {

			if ( ! isset( $calendar ) ) {
				$calendar_type	= isset( $request[ self::CALENDAR_TYPE_INPUT_FIELD_NAME ] )	? $request[ self::CALENDAR_TYPE_INPUT_FIELD_NAME ]	: NULL;
				$calendar_id 	= isset( $request[ self::CALENDAR_ID_INPUT_FIELD_NAME ] )	? $request[ self::CALENDAR_ID_INPUT_FIELD_NAME ]		: NULL;
				/* Translators: %1$s is a calendar type, %2$s is a calendar ID */
				$msg = sprintf( __( 'Missing or invalid calendar ID was supplied in events feed request: %1$s %2$s.', 'reg-man-rc' ), $calendar_type, $calendar_id );
				Error_Log::log_msg( $msg );
			} else {
				if ( ! $is_valid_nonce ) {
					$calendar_type	= $calendar->get_calendar_type();
					$calendar_id 	= $calendar->get_id();
					/* Translators: %1$s is a nonce, %2$s is a calendar type, %3$s is a calendar ID */
					$msg = sprintf( __( 'Missing or invalid security token "%1$s" was supplied in events feed request: %2$s %3$s.', 'reg-man-rc' ), $nonce, $calendar_type, $calendar_id );
					Error_Log::log_msg( $msg );
				} // endif
			} // endif
			
			if ( ! isset( $range_start_date_string ) ) {
				/* Translators: %1$s is an invalid date */
				$msg = __( 'Missing start date in events feed request.', 'reg-man-rc' );
				Error_Log::log_msg( $msg );
			} // endif
			
			if ( ! isset( $range_end_date_string ) ) {
				/* Translators: %1$s is an invalid date value supplied for an event */
				$msg = __( 'Missing end date in events feed request.', 'reg-man-rc' );
				Error_Log::log_msg( $msg );
			} // endif

			$json_data = array();

		} // endif

//Error_Log::var_dump( $json_data );
		$response = new \WP_REST_Response( $json_data );
		return $response;

	} // function

	/**
	 * Get the calendar object for the specified request
	 * @param	\WP_REST_Request 	$request
	 * @return	Calendar
	 */
	public static function get_calendar_for_request( $request ) {
		
		$calendar_type	= isset( $request[ 'calendar_type' ] )	? $request[ 'calendar_type' ]	: NULL;
		$calendar_id 	= isset( $request[ 'calendar_id' ] )	? $request[ 'calendar_id' ]		: NULL;
		
//Error_Log::var_dump( $calendar_type, $calendar_id );
		
		if ( $calendar_type == Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR ) {
			
			// In this case I will need to override things like the event date and recur settings for the
			// event descriptor and then return that calendar
			$result = self::get_event_descriptor_calendar_for_reqeust( $calendar_id, $request );
			
		} else {
			
			// This is the usual case, a request for an events calendar, visitor registration, volunteer area
			$result = isset( $calendar_id ) ? Calendar::get_calendar_by_type( $calendar_type, $calendar_id ) : NULL;
			
		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get an event descriptor calendar for the specified request
	 * @param	string				$calendar_id
	 * @param	\WP_REST_Request 	$request
	 * @return	Calendar
	 */
	private static function get_event_descriptor_calendar_for_reqeust( $calendar_id, $request ) {
		
//		Error_Log::var_dump( $calendar_id, $request );

		$recur_flag_value 				= isset( $request[ 'event_recur_flag' ] )			? $request[ 'event_recur_flag' ]			: NULL;
		$recur_weekly_by_day_json 		= isset( $request[ 'recur_weekly_by_day_json' ] )	? $request[ 'recur_weekly_by_day_json' ]	: NULL;
		$recur_monthly_by_day_json 		= isset( $request[ 'recur_monthly_by_day_json' ] )	? $request[ 'recur_monthly_by_day_json' ]	: NULL;
		$recur_yearly_by_month_json 	= isset( $request[ 'recur_yearly_by_month_json' ] )	? $request[ 'recur_yearly_by_month_json' ]	: NULL;
		$recur_yearly_by_day_json 		= isset( $request[ 'recur_yearly_by_day_json' ] )	? $request[ 'recur_yearly_by_day_json' ]	: NULL;
		$cancel_date_strings_json 		= isset( $request[ 'recur_cancel_dates' ] )			? $request[ 'recur_cancel_dates' ]			: NULL;
		
		$is_recur_event = $recur_flag_value == '1';

		$recur_weekly_by_day = isset( $recur_weekly_by_day_json ) ? json_decode( $recur_weekly_by_day_json ) : array();
		$request[ 'recur_weekly_by_day' ] = $recur_weekly_by_day;
//		Error_Log::var_dump( $recur_weekly_by_day_json, $recur_weekly_by_day );
		
		$recur_monthly_by_day = isset( $recur_monthly_by_day_json ) ? json_decode( $recur_monthly_by_day_json ) : array();
		$request[ 'recur_monthly_by_day' ] = $recur_monthly_by_day;
//		Error_Log::var_dump( $recur_monthly_by_day_json, $recur_monthly_by_day );
		
		$recur_yearly_by_month = isset( $recur_yearly_by_month_json ) ? json_decode( $recur_yearly_by_month_json ) : array();
		$request[ 'recur_yearly_by_month' ] = $recur_yearly_by_month;
//		Error_Log::var_dump( $recur_yearly_by_month_json, $recur_yearly_by_month );
		
		$recur_yearly_by_day = isset( $recur_yearly_by_day_json ) ? json_decode( $recur_yearly_by_day_json ) : array();
		$request[ 'recur_yearly_by_day' ] = $recur_yearly_by_day;
//		Error_Log::var_dump( $recur_yearly_by_day_json, $recur_yearly_by_day );

		$cancel_date_strings_array = isset( $cancel_date_strings_json ) ? json_decode( $cancel_date_strings_json ) : NULL;
//		Error_Log::var_dump( $cancel_date_strings_json, $cancel_date_strings_array );
		
		$event_descriptor = Calendar::get_event_descriptor_for_calendar_id( $calendar_id );
		
		if ( ! isset( $event_descriptor ) ) {
			
			/* translators: %1$s is an invalid calendar ID */
			$msg = sprintf( __( 'The specified calendar ID refers to an event descriptor that is not found: %1$s.', 'reg-man-rc' ), $calendar_id );
			Error_Log::log_msg( $msg );
			$result = NULL;

		} else {
			
			$proxy = Mutable_Event_Descriptor_Proxy::create( $event_descriptor );
			
			$event_dates = Internal_Event_Descriptor_Controller::get_event_dates_for_request( $request );
			
			$start_date_time	= isset( $event_dates[ 'start' ] )	? $event_dates[ 'start' ]	: NULL;
			$end_date_time		= isset( $event_dates[ 'end' ] )	? $event_dates[ 'end' ]		: NULL;
			
//			Error_Log::var_dump( $start_date_time, $end_date_time );
			
			if ( ! empty( $start_date_time ) && ! empty( $end_date_time ) ) {

				$proxy->set_event_start_date_time( $start_date_time );
				$proxy->set_event_end_date_time( $end_date_time );
				if ( isset( $cancel_date_strings_array ) ) {
					$proxy->set_cancelled_event_date_strings_array( $cancel_date_strings_array );
				} // endif
				
				if ( $is_recur_event ) {
					$recur_rule = Internal_Event_Descriptor_Controller::get_recurrence_rule_for_request( $request, $start_date_time, $end_date_time );
					$proxy->set_event_is_recurring( $is_recur_event ); // It must be true here
					$proxy->set_event_recurrence_rule( $recur_rule );
				} // endif
				
			} // endif
			
			$result = Calendar::get_event_descriptor_calendar( $proxy );
			
		} // endif
		
		return $result;
	
	} // function
	
	/**
	 * Convert an array of events into an array of plain arrays suitable for use as JSON data for FullCalendar
	 * @param Calendar_Entry[]	$calendar_entry_array
	 * @return string[][]
	 */
	private static function get_calendar_json_data( $calendar_entry_array, $calendar_type ) {

// Error_Log::var_dump( $calendar_entry_array );
		$entry_data_array = array();
		foreach ( $calendar_entry_array as $calendar_entry ) {
			$entry_data = array();
			if ( $calendar_entry instanceof Calendar_Entry ) {
				$entry_data[ 'id' ]			= $calendar_entry->get_calendar_entry_id( $calendar_type );
				$entry_data[ 'title' ]		= $calendar_entry->get_calendar_entry_title( $calendar_type );
				$entry_data[ 'start' ]		= $calendar_entry->get_calendar_entry_start_date_time_string( $calendar_type );
				$entry_data[ 'end' ]		= $calendar_entry->get_calendar_entry_end_date_time_string( $calendar_type );
				$entry_data[ 'color' ]		= $calendar_entry->get_calendar_entry_colour( $calendar_type );
				$entry_data[ 'classNames' ] = $calendar_entry->get_calendar_entry_class_names( $calendar_type );
				$info						= $calendar_entry->get_calendar_entry_info( $calendar_type );
				if ( ! empty( $info ) ) {
					$info = "<div class=\"reg-man-rc-info-window-container $calendar_type\">$info</div>";
					$entry_data[ 'info' ]	= $info;
				} // endif
				$entry_data_array[] = $entry_data;
			} // endif
		} // endfor
		return $entry_data_array;

	} // function

} // class