<?php
namespace Reg_Man_RC\Control;


use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Event_Group_Map_Marker;
use Reg_Man_RC\Model\Ajax_Form_Response;

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
	const IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME	= 'is_show_past_events';
	const EVENT_AUTHOR_FIELD_NAME				= 'event_author';
	const MAP_TYPE_INPUT_FIELD_NAME				= 'map_type';

	/**
	 * Register this controller
	 */
	public static function register() {

		// Register the handler for an AJAX request to get marker data for stats for any user, logged-in or not (any user can do this)
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GET_STATS_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_stats' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_STATS_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_stats' ) );

		// Register the handler for an AJAX request to get marker data for a claendar for any user, logged-in or not (any user can do this)
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GET_CALENDAR_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_calendar' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_CALENDAR_MARKER_DATA, array( __CLASS__, 'handle_ajax_get_event_marker_data_for_calendar' ) );

	} // function

	/**
	 * Handle an AJAX request to get event marker data for statistics
	 */
	public static function handle_ajax_get_event_marker_data_for_stats() {

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

		$map_type	= isset( $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ] ) ? $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ] : NULL;
		$nonce		= isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_GET_STATS_MARKER_DATA );
//		Error_Log::var_dump( $nonce, $is_valid_nonce );
		if ( ! $is_valid_nonce ) {

			$error_msg = __( 'Your security token has expired.  Please reload the page.', 'reg-man-rc' );
			$form_response = Ajax_Form_Response::create();
			$form_response->add_error( '_wpnonce', $nonce, $error_msg );

			echo json_encode( $form_response->jsonSerialize() ); 
			wp_die(); // THIS IS REQUIRED! <== EXIT POINT!!!

		} // endif		
		
		$filter = Event_Filter_Input_Form::get_filter_object_from_request( $form_data ); // May return NULL if no filters set

		$is_include_placeholder_events = TRUE; // The stats map should contain placeholder events
		$filtered_events = Event::get_all_events_by_filter( $filter, $is_include_placeholder_events );

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
//		$calendar_type				= isset( $form_data[ Calendar_Controller::CALENDAR_TYPE_INPUT_FIELD_NAME ] )	? $form_data[ Calendar_Controller::CALENDAR_TYPE_INPUT_FIELD_NAME ]	: NULL;
//		$calendar_id				= isset( $form_data[ Calendar_Controller::CALENDAR_ID_INPUT_FIELD_NAME ] )	? $form_data[ Calendar_Controller::CALENDAR_ID_INPUT_FIELD_NAME ]	: NULL;
		$show_past_events_string	= isset( $form_data[ self::IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME ] )	? $form_data[ self::IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME ]	: NULL;
		$author_string				= isset( $form_data[ self::EVENT_AUTHOR_FIELD_NAME ] )		? $form_data[ self::EVENT_AUTHOR_FIELD_NAME ]		: NULL;
		$map_type					= isset( $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ] )	? $form_data[ self::MAP_TYPE_INPUT_FIELD_NAME ]		: NULL;
		$nonce						= isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_GET_CALENDAR_MARKER_DATA );
		if ( ! $is_valid_nonce ) {

			$error_msg = __( 'Your security token has expired.  Please reload the page.', 'reg-man-rc' );
			$form_response = Ajax_Form_Response::create();
			$form_response->add_error( '_wpnonce', $nonce, $error_msg );

			echo json_encode( $form_response->jsonSerialize() ); 
			wp_die(); // THIS IS REQUIRED! <== EXIT POINT!!!

		} // endif
		
		$calendar = Calendar_Controller::get_calendar_for_request( $form_data );
		
		$is_show_past_events = ( strtolower( $show_past_events_string ) !== 'false' );

		$event_author_id = ( $author_string == 'author_mine' ) ? get_current_user_id() : 0;
		
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

			$marker_array = $calendar->get_map_markers_in_date_range( $start_date_time, $end_date_time, $event_author_id );

		} else {

			if ( ! isset( $calendar ) ) {
				
				$calendar_type	= isset( $form_data[ Calendar_Controller::CALENDAR_TYPE_INPUT_FIELD_NAME ] )	? $form_data[ Calendar_Controller::CALENDAR_TYPE_INPUT_FIELD_NAME ]	: NULL;
				$calendar_id 	= isset( $form_data[ Calendar_Controller::CALENDAR_ID_INPUT_FIELD_NAME ] )		? $form_data[ Calendar_Controller::CALENDAR_ID_INPUT_FIELD_NAME ]	: NULL;
				
				/* Translators: %1$s is a calendar type, %2$s is a calendar ID */
				$msg = sprintf( __( 'Missing or invalid calendar ID was supplied in events feed request: %1$s %2$s.', 'reg-man-rc' ), $calendar_type, $calendar_id );
				Error_Log::log_msg( $msg );
			} // endif

			$marker_array = array();

		} // endif

		$json = Map_View::get_marker_json_data( $marker_array, $map_type );

		echo $json;
		wp_die(); // THIS IS REQUIRED!

	} // function


} // class