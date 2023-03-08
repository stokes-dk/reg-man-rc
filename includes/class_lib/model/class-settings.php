<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Event_Descriptor_View;
use Reg_Man_RC\Model\Stats\Wilson_Confidence_Interval;

/**
 * This class contains static methods used to access and set the settings for the plugin
 *
 * @since	v0.1.0
 *
 */
class Settings {

	// Attachment ID for the logo shown on our minimal template for forms like visitor registration
	const REG_FORM_LOGO_OPTION_KEY						= 'reg-man-rc-reg-form-logo-id';

	// Option to store user request to skip initialization of taxo
	const IS_SKIP_OBJECT_TYPE_INIT_OPTION_KEY_BASE		= 'reg-man-rc-skip-object-type-init-'; // taxonomy or CPT name is appended to this

	// Flag to indicate that this is the public server rather than a satelite registration server
	const IS_PUBLIC_SERVER_OPTION_KEY					= 'reg-man-rc-is-public-server';

	// Flag to indicate that the block editor should be used for our custom post types
	const IS_USE_BLOCK_EDITOR_OPTION_KEY				= 'reg-man-rc-is-use-block-editor';

	// Settings for events
	const EVENT_START_TIME_OPTION_KEY					= 'reg-man-rc-default-event-start-time';
	const EVENT_END_TIME_OPTION_KEY						= 'reg-man-rc-default-event-end-time';
	const ALLOW_MULTI_EVENT_CATS_OPTION_KEY				= 'reg-man-rc-event-allow-multi-cats';
	const DISPLAY_EVENT_SIDEBAR_OPTION_KEY				= 'reg-man-rc-event-display-sidebar';

	// Permalink slugs for our custom post types
	const EVENTS_SLUG_OPTION_KEY						= 'reg-man-rc-events-slug';
	const ITEMS_SLUG_OPTION_KEY							= 'reg-man-rc-items-slug';
	const VOLUNTEERS_SLUG_OPTION_KEY					= 'reg-man-rc-volunteers-slug';
	const CALENDARS_SLUG_OPTION_KEY						= 'reg-man-rc-calendars-slug';
	const VENUES_SLUG_OPTION_KEY						= 'reg-man-rc-venues-slug';

	// Settings for google maps
	const GOOGLE_MAPS_API_KEY_OPTION_KEY				= 'reg-man-rc-google-maps-api-key';
	const GOOGLE_MAPS_DEFAULT_CENTRE_PLACE_OPTION_KEY	= 'reg-man-rc-google-maps-default-centre-place';
	const GOOGLE_MAPS_DEFAULT_CENTRE_GEO_OPTION_KEY		= 'reg-man-rc-google-maps-default-centre-geo';
	const GOOGLE_MAPS_DEFAULT_ZOOM_OPTION_KEY			= 'reg-man-rc-google-maps-default-zoom';

	// Settings to determine how events are grouped together on a map
	const LOCATION_GROUP_COMPARE_PRECISION_OPTION_KEY	= 'reg-man-rc-location-group-precision';
	const LOCATION_GROUP_COMPARE_PRECISION_DEFAULT		= 4;

	// The house rules page shown on visitor registration
	const HOUSE_RULES_DEFAULT_PAGE_PATH					= 'house-rules-and-safety-procedures';

	// Option keys to store the post ID of special calendars
	const VISITOR_REG_CALENDAR_OPTION_KEY				= Calendar::POST_TYPE . '-visitor-reg-cal-id';
	const VOLUNTEER_REG_CALENDAR_OPTION_KEY				= Calendar::POST_TYPE . '-volunteer-reg-cal-id';

	private static $maps_centre_geo; // Stores the Geographic Position object for the default map centre

	/**
	 * Get the attacment ID for the organization logo to show at the top of forms
	 * @return int
	 */
	public static function get_reg_form_logo_image_attachment_id() {
		$result = get_option( self::REG_FORM_LOGO_OPTION_KEY );
		if ( empty( $result ) ) {
			$result = get_theme_mod( 'custom_logo' ); // Ask for the theme modification value for logo
		} // endif
		return $result;
	} // function

	/**
	 * Should we show an External or Alternate Names column for things like Event Categories and Item Types?
	 * @return boolean	TRUE when there exist external event providers or external data providers, otherwise FALSE
	 */
	public static function get_is_show_external_names() {
		$external_event_providers = External_Event_Descriptor::get_all_external_event_providers();
		$external_data_providers = apply_filters( 'reg_man_rc_get_external_data_providers', array() );
		$result = ( ! empty( $external_event_providers ) || ! empty( $external_data_providers ) );
		return $result;
	} // function

	/**
	 * Get a flag indicating whether to use the block editor for my custom post types
	 *
	 * @return	boolean|NULL	Returns TRUE if the user has opted to use the block editor,
	 * 		FALSE if the user has opted to NOT use the block editor,
	 * 		or NULL if the user has selected no option and will use the system default
	 */
	// TODO: This should really be a user preference
	public static function get_is_use_block_editor() {
		return FALSE; // FIXME TESTING!!!
		$opt = get_option( self::IS_USE_BLOCK_EDITOR_OPTION_KEY, NULL );
		switch( $opt ) {
			case '1':
				$result = TRUE; // Use the block editor
				break;
			case '0':
				$result = FALSE; // Do not use the block editor
				break;
			default:
				$result = NULL; // Use the system default
				break;
		} // endswitch
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
	 * Returns a flag indicating whether this install is a publicly accessible central server (normal case)
	 *  or a satellite registration system on a laptop or other portable device.
	 * @return boolean
	 */
	public static function get_is_pubilc_server() {
		$opt = get_option( self::IS_PUBLIC_SERVER_OPTION_KEY, '1' );
		$result = ( $opt == '1' );
		return $result;
	} // function

	public static function set_is_public_server( $is_public_server ) {
		$val = trim( strval( $is_public_server ) );
		if ( $val == '' ) {
			delete_option( self::IS_PUBLIC_SERVER_OPTION_KEY );
		} else {
			$val = boolval( $val ) ? '1' : '0';
			update_option( self::IS_PUBLIC_SERVER_OPTION_KEY, $val );
		} // endif
	} // function


	public static function get_default_event_start_time() {
		$default = '12:00'; // Must be 24-hour clock with leading zeros, format H:i
		$opt = get_option( self::EVENT_START_TIME_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : $default;
		return $result;
	} // function

	public static function get_default_event_end_time() {
		$default = '16:00'; // Must be 24-hour clock with leading zeros, format H:i
		$opt = get_option( self::EVENT_END_TIME_OPTION_KEY );
		$result = ! empty( $opt ) ? $opt : $default;
		return $result;
	} // function

	public static function get_is_allow_recurring_events() {
		$result = FALSE;
		return $result;
	} // function

	public static function get_is_allow_event_multiple_categories() {
		$opt = get_option( self::ALLOW_MULTI_EVENT_CATS_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function

	public static function get_is_display_event_sidebar() {
		$opt = get_option( self::DISPLAY_EVENT_SIDEBAR_OPTION_KEY );
		$result = ( $opt == '1' );
		return $result;
	} // function

	public static function get_is_allow_event_comments() {
		$result = FALSE;
		return $result;
	} // function

	public static function get_is_allow_item_comments() {
		$result = FALSE;
		return $result;
	} // function

	public static function get_house_rules_page_path() {
		$result = self::HOUSE_RULES_DEFAULT_PAGE_PATH;
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
			self::$maps_centre_geo = Geographic_Position::create_from_google_map_marker_position_string( $val );
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

	public static function get_volunteers_slug() {
		$val = trim( strval( get_option( self::VOLUNTEERS_SLUG_OPTION_KEY ) ) );
		$result = ( $val !== '' ) ? $val : Volunteer::DEFAULT_PAGE_SLUG;
		return $result;
	} // function

	public static function set_volunteers_slug( $slug ) {
		$val = trim( strval( $slug ) );
		if ( $val == '' ) {
			delete_option( self::VOLUNTEERS_SLUG_OPTION_KEY );
		} else {
			update_option( self::VOLUNTEERS_SLUG_OPTION_KEY, $val );
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

	public static function get_is_visitor_registration_event_select_using_calendar() {
		return TRUE;
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

} // class