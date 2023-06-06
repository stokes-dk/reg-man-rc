<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Calendar;
use const Reg_Man_RC\PLUGIN_VERSION;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Calendar_Entry;

/**
 * The calendar controller
 *
 * This class provides the controller function for working with calenders
 *
 * @since v0.1.0
 *
 */
class Calendar_Controller {

	public static function register() {

		// register the rest api endpoint
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_endpoint' ) );

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

	public static function get_rest_namespace() {
		// FIXME: This should maybe be a REST API version, not the PLUGIN Version ?
		// This API should be completely contained in one server, I mean the calendar 
		// is served by one server then then the client gets data from the same server
		// It's not used to communicate event info to other servers
		// The JSON data includes html markup (the info window) which requires css files from the server
		// So maybe PLUGIN Version is actually ok here
		// Also, this REST API provides events rather than event descriptors which I will need on the satellite server
		return 'reg-man-rc/' . PLUGIN_VERSION;
	} // function

	public static function get_rest_route() {
		return '/fullcalendar/json-events-feed/';
	} // function

	public static function get_json_events_feed_url() {
		$namespace = self::get_rest_namespace();
		$route = self::get_rest_route();
		$result = get_site_url( $blog_id = NULL, $path = 'wp-json/' . $namespace . $route );
		return $result;
	} // function

	/**
	 * Register the REST API endpoint to provide a JSON events feed to the calendar
	 */
	public static function register_rest_endpoint() {
		$namespace = self::get_rest_namespace();
		$route = self::get_rest_route();
		$args = array(
					'methods'				=> 'GET',
					'permission_callback'	=> '__return_true',
					'callback'				=> array( __CLASS__, 'handle_events_feed_request' ),
		);
		register_rest_route( $namespace, $route, $args );
	} // function

	/**
	 * Handle a calendar feed request for JSON events
	 * @param	\WP_REST_Request 	$request
	 */
	public static function handle_events_feed_request( $request ) {

//	$capability = 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
//	Error_Log::var_dump( get_current_user(), $capability, current_user_can( $capability ) );
		
		$calendar_id 				= isset( $request[ 'calendar_id' ] )			? $request[ 'calendar_id' ]			: NULL;
		$range_start_date_string	= isset( $request[ 'start' ] )					? $request[ 'start' ]				: '';
		$range_end_date_string		= isset( $request[ 'end' ] )					? $request[ 'end' ]					: '';
		$show_past_events_string	= isset( $request[ 'is_show_past_events' ] )	? $request[ 'is_show_past_events' ]	: 'false';

//Error_Log::var_dump( $calendar_id, $range_start_date_string, $range_end_date_string, $show_past_events_string );
		$calendar = isset( $calendar_id ) ? Calendar::get_calendar_by_id( $calendar_id ) : NULL;

		$is_show_past_events = ( strtolower( $show_past_events_string ) !== 'false' );
//Error_Log::var_dump( $show_past_events_string, $is_show_past_events );

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

//Error_Log::var_dump( $start_date_time->format( 'Y-m-d H:i:s e' ), $end_date_time->format( 'Y-m-d H:i:s e' ) ); // datetimes with timezone

		if ( isset( $calendar ) && isset( $start_date_time ) && isset( $end_date_time ) ) {

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

			$entry_array = $calendar->get_calendar_entries_in_date_range( $start_date_time, $end_date_time );
			$calendar_type = $calendar->get_calendar_type();
			$json_data = self::get_calendar_json_data( $entry_array, $calendar_type );

		} else {

			if ( ! isset( $calendar ) ) {
				/* translators: %1$s is an invalid calendar ID */
				$msg = sprintf( __( 'Missing or invalid calendar ID was supplied in events feed request: %1$s.', 'reg-man-rc' ), $calendar_id );
				Error_Log::log_msg( $msg );
			} // endif
			if ( ! isset( $range_start_date_string ) ) {
				/* translators: %1$s is an invalid date */
				$msg = __( 'Missing start date in events feed request.', 'reg-man-rc' );
				Error_Log::log_msg( $msg );
			} // endif
			if ( ! isset( $range_end_date_string ) ) {
				/* translators: %1$s is an invalid date value supplied for an event */
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