<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Event_Descriptor_View;
use Reg_Man_RC\Model\Stats\Wilson_Confidence_Interval;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Control\Admin_Bar_Controller;
use Reg_Man_RC\Model\Stats\ORDS_Feed_Writer;

/**
 * This class contains static methods used to access and set the settings for the plugin
 *
 * @since	v0.1.0
 *
 */
class Settings {

	// Option to store user request to skip initialization of taxonomy
	const IS_SKIP_OBJECT_TYPE_INIT_OPTION_KEY_BASE		= 'reg-man-rc-skip-object-type-init-'; // taxonomy or CPT name is appended to this


	// Options for visitor registration
	const HOUSE_RULES_POST_ID_OPTION_KEY				= 'reg-man-rc-house-rules-post-id';
	const IS_SHOW_ITEM_TYPE_IN_VISITOR_REG_OPTION_KEY	= 'reg-man-rc-is-show-item-type-in-vis-reg';
	
	// Settings for events
	const EVENT_START_TIME_OPTION_KEY					= 'reg-man-rc-default-event-start-time';
	const EVENT_DURATION_OPTION_KEY						= 'reg-man-rc-default-event-duration';
	const ALLOW_MULTI_EVENT_CATS_OPTION_KEY				= 'reg-man-rc-event-allow-multi-cats';
	
	// Settings for calendars
	const ADMIN_CALENDAR_VIEWS_OPTION_KEY				= 'reg-man-rc-admin-calendar-views';
	const ADMIN_CALENDAR_DURATIONS_OPTION_KEY			= 'reg-man-rc-admin-calendar-durations';
	
	// Option keys to store the post ID of special calendars
	const VISITOR_REG_CALENDAR_OPTION_KEY				= Calendar::POST_TYPE . '-visitor-reg-cal-id';
	const VOLUNTEER_REG_CALENDAR_OPTION_KEY				= Calendar::POST_TYPE . '-volunteer-reg-cal-id';
	
	// Settings for google maps
	const GOOGLE_MAPS_API_KEY_OPTION_KEY				= 'reg-man-rc-google-maps-api-key';
	const GOOGLE_MAPS_DEFAULT_CENTRE_PLACE_OPTION_KEY	= 'reg-man-rc-google-maps-default-centre-place';
	const GOOGLE_MAPS_DEFAULT_CENTRE_GEO_OPTION_KEY		= 'reg-man-rc-google-maps-default-centre-geo';
	const GOOGLE_MAPS_DEFAULT_ZOOM_OPTION_KEY			= 'reg-man-rc-google-maps-default-zoom';

	// Settings to determine how events are grouped together on a map
	const LOCATION_GROUP_COMPARE_PRECISION_OPTION_KEY	= 'reg-man-rc-location-group-precision';
	const LOCATION_GROUP_COMPARE_PRECISION_DEFAULT		= 4;

	// Settings for volunteer area
	const ALLOW_VOLUNTEER_REG_FOR_TENTATIVE_EVENTS_OPTION_KEY	= 'reg-man-rc-vol-area-allow-reg-tentative-events';
	
	// Note that allow volunteer area comments is stored in the post as comment_status = 'open'
	const REQUIRE_VOLUNTEER_AREA_REGISTERED_USER_OPTION_KEY	= 'reg-man-rc-vol-area-require-reg-user';
	
	const IS_CREATE_VOLUNTEER_CALENDAR_FEED_OPTION_KEY	= 'reg-man-rc-vol-reg-ical-feed';
	
	// Options for satellite registration systems
//	const SATELLITE_REGISTRATION_HUB_URL_KEY			= 'reg-man-rc-satellite-hub-url';
	
	// Permalink slugs for our custom post types
	const EVENTS_SLUG_OPTION_KEY						= 'reg-man-rc-events-slug';
	const CALENDARS_SLUG_OPTION_KEY						= 'reg-man-rc-calendars-slug';
//	const ITEMS_SLUG_OPTION_KEY							= 'reg-man-rc-items-slug';
//	const VENUES_SLUG_OPTION_KEY						= 'reg-man-rc-venues-slug';
	
	// Settings related to roles and capabilities
	const HIDE_ADMIN_BAR_ROLES_OPTION_KEY				= 'reg-man-rc-hide-admin-bar-roles';

	// Settings related to Open Repair Data
	const IS_CREATE_ORDS_FEED_OPTION_KEY				= 'reg-man-rc-is-create-ords-feed';
	const ORDS_FEED_NAME_OPTION_KEY						= 'reg-man-rc-ords-feed-name';
	const ORDS_FEED_COUNTRY_CODE_OPTION_KEY				= 'reg-man-rc-ords-feed-country-code';
	const ORDS_FEED_ITEM_TYPES_ARRAY_OPTION_KEY			= 'reg-man-rc-ords-feed-item-types';
	
	private static $maps_centre_geo; // Stores the Geographic Position object for the default map centre

	/**
	 * Show Item Type column in visitor registration list?
	 * @return int
	 */
	public static function get_is_show_item_type_in_visitor_registration_list() {
		$item_types = Item_Type::get_all_item_types();
		$default = empty( $item_types ) ? 0 : 1;
		$opt = get_option( self::IS_SHOW_ITEM_TYPE_IN_VISITOR_REG_OPTION_KEY, $default );
		$result = ( $opt == '1' );
		return $result;
	} // function

	/**
	 * Should we show an External or Alternate Names column for things like Event Categories and Item Types?
	 * @return boolean	TRUE when there exist external event providers or external data providers, otherwise FALSE
	 */
	public static function get_is_show_external_names() {
		$external_event_providers = Event_Provider_Factory::get_external_event_providers();
		$external_data_providers = apply_filters( 'reg_man_rc_get_external_data_providers', array() );
		$result = ( ! empty( $external_event_providers ) || ! empty( $external_data_providers ) );
		return $result;
	} // function

	/**
	 * Returns TRUE if the user has chosen to skip the step of initializing the event categories
	 * @return boolean
	 */
	public static function get_is_object_type_init_skipped( $object_type_name ) {
		$opt = get_option( self::IS_SKIP_OBJECT_TYPE_INIT_OPTION_KEY_BASE . $object_type_name, FALSE );
		$result = ( $opt == '1' );
		return $result;
	} // function

	public static function set_is_object_type_init_skipped( $object_type_name, $is_skipped ) {
		$val = boolval( $is_skipped );
		if ( $val == FALSE ) {
			delete_option( self::IS_SKIP_OBJECT_TYPE_INIT_OPTION_KEY_BASE . $object_type_name );
		} else {
			update_option( self::IS_SKIP_OBJECT_TYPE_INIT_OPTION_KEY_BASE . $object_type_name, '1' );
		} // endif
	} // function


	/**
	 * Get the default event start time as a string in 24-hour clock notation with leading zeros, e.g. '13:00'.
	 * This is used for time input when creating events
	 * @return string|mixed|boolean
	 */
	public static function get_default_event_start_time() {
		$default = '12:00'; // Must be 24-hour clock with leading zeros, format H:i
		$opt = get_option( self::EVENT_START_TIME_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : $default;
		return $result;
	} // function

	/**
	 * Get the default event duration as a string that can be used to construct a date interval, e.g. PT4H for plus 4 hours.
	 * @return string
	 */
	public static function get_default_event_duration_date_interval_string() {
		$default = 'PT4H'; // 4 hours
		$opt = get_option( self::EVENT_DURATION_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : $default;
		return $result;
	} // function
	
	/**
	 * Get the default event end time based on the default event duration and the optional specified start time.
	 * The result is in 24-hour clock notation with leading zeros so it can be used in a time input, e.g. "17:00"
	 * @return string
	 */
	public static function get_default_event_end_time() {

		$interval_string = self::get_default_event_duration_date_interval_string();
		try {
			$date_interval = new \DateInterval( $interval_string );
		} catch( \Exception $exc ) {
			$default = 'PT4H'; // 4 hours, this should never happen but defensive
			$date_interval = new \DateInterval( $default );
			/* translators: %1$s is an invalid date interval string  */
			$msg = sprintf( __( 'An invalid date interval string is stored in the options table: %1$s.', 'reg-man-rc' ), $interval_string );
			Error_Log::log_exception( $msg, $exc );
		} // endtry

		$start_time = self::get_default_event_start_time();
		$result_dt = new \DateTime( $start_time ); // This will use today's date by default but it won't matter, we just need time
		$result_dt->add( $date_interval ); // Add the duration
		$result_format = 'H:i';   // The result is formated using 24-hour clock with leading zeros for use in time input
		$result = $result_dt->format( $result_format );
		return $result;
	} // function
	
	public static function get_is_allow_recurring_events() {
		$result = TRUE;
		return $result;
	} // function

	public static function get_is_allow_event_multiple_categories() {
		$opt = get_option( self::ALLOW_MULTI_EVENT_CATS_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function

	public static function get_admin_calendar_views() {
		$opt = get_option( self::ADMIN_CALENDAR_VIEWS_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : Calendar::get_default_admin_calendar_view_format_ids_array();
		return $result;
	} // function
	
	public static function get_admin_calendar_durations() {
		$opt = get_option( self::ADMIN_CALENDAR_DURATIONS_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : Calendar::get_default_admin_calendar_duration_ids_array();
		return $result;
	} // function
	
	
	public static function get_is_allow_comments_on_new_items() {
		$result = FALSE;
		return $result;
	} // function

	public static function get_house_rules_post_id() {
		$result = get_option( self::HOUSE_RULES_POST_ID_OPTION_KEY, 0 );
		return $result;
	} // function

	public static function get_is_include_join_mail_list_question() {
		$result = FALSE;
		return $result;
	} // function

	public static function get_maps_api_key() {
		$val = trim( strval( get_option( self::GOOGLE_MAPS_API_KEY_OPTION_KEY ) ) );
		return $val;
//		if ( ! isset( self::$maps_api_key ) ) {
//			self::$maps_api_key = 'AIzaSyCbeJ1-BQgwNZyWhs-u-z5dCapTE7y3uAI';
//		} // endif
//		return self::$maps_api_key;
	} // function

	/**
	 * Get the place name representing the default centre for a map
	 * @return string
	 */
	public static function get_map_default_centre_place_name() {
		$val = trim( strval( get_option( self::GOOGLE_MAPS_DEFAULT_CENTRE_PLACE_OPTION_KEY ) ) );
		return $val;
	} // function

	/**
	 * Get the Geographic Position object representing the default centre for a map
	 * @return Geographic_Position
	 */
	public static function get_map_default_centre_geo() {
		if ( ! isset( self::$maps_centre_geo ) ) {
			$val = trim( strval( get_option( self::GOOGLE_MAPS_DEFAULT_CENTRE_GEO_OPTION_KEY ) ) );
			 // We will store the option as a map marker position string
			 // This is actually handled by Javascript (inserting the value into a hidden input field in Settings)
			 //  and the Wordpress settings API which will save registered settings automatically
			self::$maps_centre_geo = Geographic_Position::create_from_google_map_marker_position_json_string( $val );
		} // endif
		return self::$maps_centre_geo;
	} // function

	/**
	 * Get the zoom level for the default centre for a map
	 * @return int
	 */
	public static function get_map_default_zoom() {
		$val = intval( trim( strval( get_option( self::GOOGLE_MAPS_DEFAULT_ZOOM_OPTION_KEY ) ) ) );
		$val = ! empty( $val ) ? $val : 1;
		return $val;
	} // function

	/**
	 * Get the precision setting used to compare geographical positions when grouping location.
	 * Rather than stacking multiple map markers in the same spot on a map, we group them together in one marker.
	 * When events and other locations are grouped we need to compare their geographical position for equality.
	 * Whether two locations are equal depends greatly on the amount of rounding applied to their lat and long values.
	 * This setting assigns the rounding to be used for those comparisons expressed as a number digits after the decimal place.
	 * A single degree of latitude or longitude is about 111 kilometers.
	 * Setting this precision to 0 would group together everything within the same lat and long, or within about 100km.
	 * The following are the settings and the approximate size of the grouping area:
	 * 0 ~100 kilometers
	 * 1 ~10 kilometers
	 * 2 ~1 kilometer
	 * 3 ~100 meters
	 * 4 ~10 meters
	 * 5 ~1 meter
	 * In most circumstances only 3 and 4 are useful.
	 *
	 * Note that this is only used in situations like events with no venue assigned and only a geo position.
	 * @return int
	 */
	public static function get_map_location_comparison_precision() {
		$val = intval( trim( strval( get_option( self::LOCATION_GROUP_COMPARE_PRECISION_OPTION_KEY ) ) ) );
		$val = ! empty( $val ) ? $val : self::LOCATION_GROUP_COMPARE_PRECISION_DEFAULT;
		return $val;
	} // function

	public static function get_show_event_external_map_link() {
		return TRUE;
	} // function

	public static function get_show_event_category() {
		return TRUE;
	} // function

	public static function get_show_confirmed_event_marker() {
		return FALSE;
	} // function

	public static function get_events_slug() {
		$val = trim( strval( get_option( self::EVENTS_SLUG_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : Event_Descriptor_View::DEFAULT_PAGE_SLUG;
		return $result;
	} // function

	public static function set_events_slug( $slug ) {
		$val = trim( strval( $slug ) );
		if ( $val == '' ) {
			delete_option( self::EVENTS_SLUG_OPTION_KEY );
		} else {
			update_option( self::EVENTS_SLUG_OPTION_KEY, $val );
		} // endif
	} // function
/*
	public static function get_items_slug() {
		$val = trim( strval( get_option( self::ITEMS_SLUG_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : Item::DEFAULT_PAGE_SLUG;
		return $result;
	} // function

	public static function set_items_slug( $slug ) {
		$val = trim( strval( $slug ) );
		if ( $val == '' ) {
			delete_option( self::ITEMS_SLUG_OPTION_KEY );
		} else {
			update_option( self::ITEMS_SLUG_OPTION_KEY, $val );
		} // endif
	} // function

	public static function get_venues_slug() {
		$val = trim( strval( get_option( self::VENUES_SLUG_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : Venue::DEFAULT_PAGE_SLUG;
		return $result;
	} // function

	public static function set_venues_slug( $slug ) {
		$val = trim( strval( $slug ) );
		if ( $val == '' ) {
			delete_option( self::VENUES_SLUG_OPTION_KEY );
		} else {
			update_option( self::VENUES_SLUG_OPTION_KEY, $val );
		} // endif
	} // function
*/

	public static function get_calendars_slug() {
		$val = trim( strval( get_option( self::CALENDARS_SLUG_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : Calendar::DEFAULT_PAGE_SLUG;
		return $result;
	} // function

	public static function set_calendars_slug( $slug ) {
		$val = trim( strval( $slug ) );
		if ( $val == '' ) {
			delete_option( self::CALENDARS_SLUG_OPTION_KEY );
		} else {
			update_option( self::CALENDARS_SLUG_OPTION_KEY, $val );
		} // endif
	} // function

	public static function get_confidence_level_for_interval_estimate() {
		return Wilson_Confidence_Interval::CONFIDENCE_95;
	} // function

	public static function get_visitor_registration_calendar_post_id() {
		$result = get_option( self::VISITOR_REG_CALENDAR_OPTION_KEY );
		return $result;
	} // function

	public static function set_visitor_registration_calendar_post_id( $post_id ) {
		delete_option( self::VISITOR_REG_CALENDAR_OPTION_KEY );
		if ( ! empty( $post_id ) ) {
			update_option( self::VISITOR_REG_CALENDAR_OPTION_KEY, $post_id );
		} // endif
	} // function

	public static function get_volunteer_registration_calendar_post_id() {
		$result = get_option( self::VOLUNTEER_REG_CALENDAR_OPTION_KEY );
		return $result;
	} // function

	public static function set_volunteer_registration_calendar_post_id( $post_id ) {
		delete_option( self::VOLUNTEER_REG_CALENDAR_OPTION_KEY );
		if ( ! empty( $post_id ) ) {
			update_option( self::VOLUNTEER_REG_CALENDAR_OPTION_KEY, $post_id );
		} // endif
	} // function

	public static function get_is_allow_volunteer_registration_for_tentative_events() {
		$opt = get_option( self::ALLOW_VOLUNTEER_REG_FOR_TENTATIVE_EVENTS_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function
	
	public static function get_is_require_volunteer_area_registered_user() {
		$opt = get_option( self::REQUIRE_VOLUNTEER_AREA_REGISTERED_USER_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function
	
	public static function get_is_allow_volunteer_area_comments() {
		$post = Volunteer_Area::get_post();
		$result = isset( $post ) && ( $post->comment_status == 'open' );
		return $result;
	} // function
	
	public static function set_is_allow_volunteer_area_comments( $is_allow_comments ) {
		$post_id = Volunteer_Area::get_post_id();
		if ( ! empty( $post_id ) ) {
			$comment_status = $is_allow_comments ? 'open' : 'closed';
			$args = array(
					'ID'				=> $post_id,
					'comment_status'	=> $comment_status,
			);
			wp_update_post( $args );
		} // endif
	} // function
	
	public static function get_is_create_volunteer_calendar_feed() {
		$opt = get_option( self::IS_CREATE_VOLUNTEER_CALENDAR_FEED_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function
	
	public static function get_volunteer_calendar_feed_name() {
		$result = 'vol-reg-ical'; // Does this need to be configurable?
		return $result;
	} // function
	
	public static function get_is_allow_volunteer_registration_quick_signup() {
		return FALSE; // TODO: Make this a setting
	} // function

	public static function get_is_create_ORDS_feed() {
		$opt = get_option( self::IS_CREATE_ORDS_FEED_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function
	
	public static function get_ORDS_feed_name() {
		$val = trim( strval( get_option( self::ORDS_FEED_NAME_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : ORDS_Feed_Writer::DEFAULT_FEED_NAME;
		return $result;
	} // function
	
	public static function get_ORDS_feed_country_code() {
		$val = trim( strval( get_option( self::ORDS_FEED_COUNTRY_CODE_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : ORDS_Feed_Writer::DEFAULT_COUNTRY_CODE;
		return $result;
	} // function
	
	public static function get_ORDS_feed_item_type_ids_array() {
		$val = get_option( self::ORDS_FEED_ITEM_TYPES_ARRAY_OPTION_KEY );
		$result = ( $val !== '' ) ? $val : array();
		return $val;
	} // function
	
	
	public static function get_hide_admin_bar_roles() {
		$opt = get_option( self::HIDE_ADMIN_BAR_ROLES_OPTION_KEY );
		$result = ( $opt !== FALSE ) ? $opt : Admin_Bar_Controller::get_default_hide_admin_bar_roles();
		return $result;
	} // function
	
	public static function get_is_hide_visitor_registration_page_from_search() {
		return TRUE;
	} // function
	
	public static function get_is_hide_volunteer_area_page_from_search() {
		return TRUE;
	} // function
	
	/**
	 * Returns a flag indicating whether this install is a satellite registration system,
	 *  for example on a laptop or other portable device.
	 * In the normal case, the result is FALSE
	 * @return boolean
	 */
/* FIXME - NOT USED
	public static function get_is_satellite_registration_system() {
		$hub_url = self::get_satellite_registration_hub_url();
		$result = ! empty( $hub_url );
		return $result;
	} // function

	public static function get_satellite_registration_hub_url() {
		$default_value = NULL;
		$result = get_option( self::SATELLITE_REGISTRATION_HUB_URL_KEY, $default_value );
		return $result;
	} // function
	
	public static function set_satellite_registration_hub_url( $hub_url ) {
		$val = trim( strval( $hub_url ) );
		if ( $val == '' ) {
			delete_option( self::SATELLITE_REGISTRATION_HUB_URL_KEY );
		} else {
			update_option( self::IS_SATELLITE_REGISTRATION_SYSTEM_KEY, $val );
		} // endif
	} // function
*/

	
} // class