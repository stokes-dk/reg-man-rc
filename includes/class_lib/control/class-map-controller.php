<?php
namespace Reg_Man_RC\Control;


use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Event_Group_Map_Marker;

/**
 * The chart view controller
 *
 * This class provides the controller function for maps
 *
 * @since v0.1.0
 *
 */
class Map_Controller {

	const AJAX_GET_STATS_MARKER_DATA	= 'reg-man-rc-get-stats-marker-data';
	const AJAX_GET_CALENDAR_MARKER_DATA	= 'reg-man-rc-get-calendar-marker-data';

	const MIN_DATE_INPUT_FIELD_NAME				= 'min_date';
	const MAX_DATE_INPUT_FIELD_NAME				= 'max_date';
	const CALENDAR_ID_INPUT_FIELD_NAME			= 'calendar_id';
	const IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME	= 'is_show_past_events';
	const MAP_TYPE_INPUT_FIELD_NAME				= 'map_type';

	public static function register() {

		// Register the handler for an AJAX request to get marker data for stats for any user, logged-in or not (any user can do this)
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GET_STATS_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_stats' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_STATS_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_stats' ) );

		// Register the handler for an AJAX request to get marker data for a claendar for any user, logged-in or not (any user can do this)
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GET_CALENDAR_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_calendar' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_CALENDAR_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_calendar' ) );

	} // function

	public static function handle_ajax_get_event_marker_data_for_stats() {

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		if ( isset( $form_data[ 'event_filter_year' ] ) && ( $form_data[ 'event_filter_year' ] > 0 ) ) {
			$year = $form_data[ 'event_filter_year' ];
			$local_tz = wp_timezone(); // Make sure we use local timezone for dates
			$start_date_time = new \DateTime( "$year-01-01", $local_tz );
			$end_date_time = new \DateTime( "$year-12-31 23:59:59", $local_tz );
		} else {
			$start_date_time = NULL;
			$end_date_time = NULL;
		} // endif
		$map_type	= isset( $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ] )	? $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ]	: NULL;

		$filter = Event_Filter_Input_Form::get_filter_object_from_request( $form_data ); // May return NULL if no filters set
		if ( ! isset( $filter ) ) {
			$filter = Event_Filter::create();
		} // endif

		$all_events = Event::get_all_events();

		$filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
		$filtered_events = $filter->apply_filter( $all_events );

		// What I have is an array of events but what I really want is an array of Event_Group_Map_Markers
		$event_groups = Event_Group_Map_Marker::create_array_for_events_array( $filtered_events );
		$json = Map_View::get_marker_json_data( $event_groups, $map_type );

		echo $json;
		wp_die(); // THIS IS REQUIRED!

	} // function

	public static function handle_ajax_get_event_marker_data_for_calendar() {

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
//Error_Log::var_dump( $form_data );

		$range_start_date_string	= isset( $form_data[ self::MIN_DATE_INPUT_FIELD_NAME ] )	? $form_data[ self::MIN_DATE_INPUT_FIELD_NAME ]		: NULL;
		$range_end_date_string		= isset( $form_data[ self::MAX_DATE_INPUT_FIELD_NAME ] )	? $form_data[ self::MAX_DATE_INPUT_FIELD_NAME ]		: NULL;
		$calendar_id				= isset( $form_data[ self::CALENDAR_ID_INPUT_FIELD_NAME ] )	? $form_data[ self::CALENDAR_ID_INPUT_FIELD_NAME ]	: NULL;
		$show_past_events_string	= isset( $form_data[ self::IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME ] )	? $form_data[ self::IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME ]	: NULL;
		$map_type					= isset( $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ] )	? $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ]	: NULL;

		$calendar = isset( $calendar_id ) ? Calendar::get_calendar_by_id( $calendar_id ) : NULL;
//		$cal_type = isset( $calendar ) ? $calendar->get_calendar_type() : NULL;
//		Error_Log::var_dump( $cal_type, $map_type );

		$is_show_past_events = ( strtolower( $show_past_events_string ) !== 'false' );
//Error_Log::var_dump( $show_past_events_string, $is_show_past_events );

		$local_tz = wp_timezone(); // Make sure we use local timezone for dates
		try {
			$start_date_time = new \DateTime( $range_start_date_string );
			$start_date_time->setTimezone( $local_tz );
		} catch ( \Exception $exc ) {
			/* translators: %1$s is an invalid date string */
			$msg = sprintf( __( 'An invalid start date was supplied to map: %1$s.', 'reg-man-rc' ), $range_start_date_string );
			Error_Log::log_exception( $msg, $exc );
			$start_date_time = NULL;
		} // endtry
		try {
			$end_date_time = new \DateTime( $range_end_date_string );
			$end_date_time->setTimezone( $local_tz );
		} catch ( \Exception $exc ) {
			/* translators: %1$s is an invalid date string */
			$msg = sprintf( __( 'An invalid end date was supplied to map: %1$s.', 'reg-man-rc' ), $range_end_date_string );
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

			$marker_array = $calendar->get_map_markers_in_date_range( $start_date_time, $end_date_time );

		} else {

			if ( ! isset( $calendar ) ) {
				/* translators: %1$s is an invalid calendar ID */
				$msg = sprintf( __( 'Missing or invalid calendar ID was supplied to map: %1$s.', 'reg-man-rc' ), $calendar_id );
				Error_Log::log_msg( $msg );
			} // endif

			$marker_array = array();

		} // endif

		$json = Map_View::get_marker_json_data( $marker_array, $map_type );

		echo $json;
		wp_die(); // THIS IS REQUIRED!

	} // function


} // class