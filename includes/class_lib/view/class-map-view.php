<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Map_Marker;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\Map_Controller;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Map_Marker_Label;

/**
 * An instance of this class is a renderable map.
 *
 * @since	v0.1.0
 *
 */
class Map_View {

	const MARKER_SVG_PATH = 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z';

	const DEFAULT_MARKER_COLOUR = '#AAAAAA';

	const MAP_TYPE_OBJECT_PAGE				= 'object-page'; 			// A map on an object's page like an event page or venue page
	const MAP_TYPE_CALENDAR_EVENTS			= 'events-cal-map';			// A map inside an events calendar like upcoming events
	const MAP_TYPE_CALENDAR_VISITOR_REG		= 'visitor-reg-cal-map';	// In the visitor registration calendar
	const MAP_TYPE_CALENDAR_VOLUNTEER_REG	= 'volunteer-reg-cal-map';	// In the volunteer registration calendar
	const MAP_TYPE_CALENDAR_ADMIN			= 'admin-cal-map';			// In the administrative dashboard calendar
	const MAP_TYPE_ADMIN_STATS				= 'admin-stats';			// In the admin statistics page

	private $classes;
	private $map_type;
	private $lat;
	private $lng;
	private $zoom;
	private $no_markers_message; // An optional message shown on the map when there are no markers to display
	private $show_missing_location_message = FALSE; // Should the map show a message for markers with no location
	private $map_markers;
	private $legend_order; // An array of marker type names specifying the desired order for legend items
	private $ajax_form; // The ajax form used to dynamically retrieve markers
	private $ajax_marker_provider_action; // an action used to dynamically retrieve markers
	private $is_show_loading_indicator = FALSE; // a flag to have an indicator (spinner) while the map is loading

	private function __construct() {
	} // function

	/**
	 * Create a new map to be shown on an object's page like an event page
	 * @return \Reg_Man_RC\View\Map_View
	 */
	public static function create_for_object_page() {
		$result = self::create( self::MAP_TYPE_OBJECT_PAGE );
		return $result;
	} // function

	/**
	 * Create a new map to be shown in a calendar
	 * @param	Calendar	$calendar
	 * @return \Reg_Man_RC\View\Map_View
	 */
	public static function create_for_calendar( $calendar ) {
		$result = self::create( self::MAP_TYPE_CALENDAR_EVENTS );
		switch( $calendar->get_calendar_type() ) {

			case Calendar::CALENDAR_TYPE_ADMIN_EVENTS:
				$map_type = self::MAP_TYPE_CALENDAR_ADMIN;
				break;

			case Calendar::CALENDAR_TYPE_VISITOR_REG:
				$map_type = self::MAP_TYPE_CALENDAR_VISITOR_REG;
				break;

			case Calendar::CALENDAR_TYPE_VOLUNTEER_REG:
				$map_type = self::MAP_TYPE_CALENDAR_VOLUNTEER_REG;
				break;

			case Calendar::CALENDAR_TYPE_EVENTS:
			default:
				$map_type = self::MAP_TYPE_CALENDAR_EVENTS;
				break;

		} // endswitch

		$result->map_type = $map_type;
		return $result;

	} // function

	/**
	 * Create a new map to be shown in the admininstrative stats page
	 * @return \Reg_Man_RC\View\Map_View
	 */
	public static function create_for_admin_stats() {
		$result = self::create( self::MAP_TYPE_ADMIN_STATS );
		return $result;
	} // function

	/**
	 * Create a new instance of this class
	 * @param string $map_type	One of the MAP_TYPE_* constants defined in this class
	 * @param number $lat		An optional latitude for the map centre
	 * @param number $lng		An optional longitude for the map centre
	 * @param number $zoom		An optional zoom level for the map
	 */
	private static function create( $map_type, $lat = 0, $lng = 0, $zoom = 1 ) {
		$result = new self();
		$result->map_type = $map_type;
		switch( $map_type ) {
			case self::MAP_TYPE_ADMIN_STATS:
				$result->ajax_marker_provider_action = Map_Controller::AJAX_GET_STATS_MARKER_DATA;
				break;
			default:
				$result->ajax_marker_provider_action = Map_Controller::AJAX_GET_CALENDAR_MARKER_DATA;
				break;
		} // endswitch
		$default_geo = Settings::get_map_default_centre_geo();
		$default_zoom = Settings::get_map_default_zoom();
		$result->lat = isset( $default_geo ) ? $default_geo->get_latitude() : 0;
		$result->lng = isset( $default_geo ) ? $default_geo->get_longitude() : 0;
		$result->zoom = ! empty( $default_zoom ) ? $default_zoom : 1;
		return $result;
	} // function

	/**
	 * Get a flag indicating whether or not maps are enabled on the site
	 * @return boolean	TRUE if maps are enabled, FALSE if maps will not work on the site
	 */
	public static function get_is_map_view_enabled() {
		$key = Settings::get_maps_api_key();
		$result = ! empty( $key ) ? TRUE : FALSE;
		return $result;
	} // function

	/**
	 * Get the href (url) for a link to a google maps page that will show the specified map location
	 * @param	string	$map_location
	 */
	public static function get_external_google_maps_href( $map_location ) {
		$base = 'https://www.google.com/maps';
		$result = NULL;
		if ( ! empty( $map_location ) ) {
			$loc = urlencode( $map_location );
			$result = $base . "/search/?api=1&query=$loc";
		} // endif
		return $result;
	} // function

	/**
	 * Get the href (url) for a link to a google maps page that will show the specified map location
	 * @param	string	$map_location
	 */
	public static function get_external_google_maps_directions_href( $map_location ) {
		$base = 'https://www.google.com/maps';
		$result = NULL;
		if ( ! empty( $map_location ) ) {
			$loc = urlencode( $map_location );
			$result = $base . "/dir/?api=1&destination=$loc";
		} // endif
		return $result;
	} // function

	/**
	 * Get the type of this map
	 * @return	string	One of the Map_Type_* constants defined in this class indicating what kind of map this is and where it is used
	 */
	public function get_map_type() {
		return $this->map_type;
	} // function

	private function get_ajax_marker_provider_action() {
		if ( ! isset( $this->ajax_marker_provider_action ) ) {
			$this->set_ajax_marker_provider_action = Map_Controller::AJAX_GET_STATS_MARKER_DATA;
		} // endif
		return $this->ajax_marker_provider_action;
	} // function

	private function get_lat() {
		return $this->lat;
	} // function

	private function get_lng() {
		return $this->lng;
	} // function

	private function get_zoom() {
		return $this->zoom;
	} // function

	private function get_no_markers_message() {
		return $this->no_markers_message;
	} // function

	public function set_no_markers_message( $no_markers_message ) {
		$this->no_markers_message = $no_markers_message;
	} // function

	private function get_show_missing_location_message() {
		return $this->show_missing_location_message;
	} // function

	public function set_show_missing_location_message( $show_missing_location_message ) {
		$this->show_missing_location_message = boolval( $show_missing_location_message );
	} // function

	/**
	 * Get the array of map markers
	 * @return Map_Marker[]
	 */
	private function get_map_markers() {
		if ( !isset( $this->map_markers ) ) {
			$this->map_markers = array();
		} // endif
		return $this->map_markers;
	} // function

	/**
	 * Set the array of map markers
	 * @param Map_Marker[] $map_marker_array
	 */
	public function set_map_markers( $map_marker_array ) {
		if ( is_array( $map_marker_array ) ) {
			$this->map_markers = $map_marker_array;
		} // endif
	} // function

	/**
	 * Get the flag for whether the map should include a spinner indicator while the map is loading
	 * @return boolean
	 */
	private function get_is_show_loading_indicator() {
		return $this->is_show_loading_indicator;
	} // function

	/**
	 * Set the flag for whether the map should include a spinner indicator while the map is loading
	 * @param boolean	$is_show_loading_indicator
	 */
	public function set_is_show_loading_indicator( $is_show_loading_indicator ) {
		$this->is_show_loading_indicator = $is_show_loading_indicator;
	} // function

	/**
	 * Render this UI element
	 */
	public function render() {

		if ( self::get_is_map_view_enabled() ) {

			$marker_form_id = $this->get_map_marker_ajax_form_id();
			$svg_path = esc_attr( self::MARKER_SVG_PATH );
			$default_marker_colour = esc_attr( self::DEFAULT_MARKER_COLOUR );
			$no_marker_message = esc_attr( $this->get_no_markers_message() );
			$missing_loc_flag = $this->get_show_missing_location_message();

			$data = array();
			$data[] = "data-marker-form-id=\"$marker_form_id\"";
			$data[] = "data-marker-svg-path=\"$svg_path\"";
			$data[] = "data-default-marker-colour=\"$default_marker_colour\"";
			$data[] = "data-no-markers-message=\"$no_marker_message\"";
			if ( $missing_loc_flag ) {
				$data[] = 'data-show-missing-location-message="true"';
			} // endif

			$data = implode( ' ', $data );
			echo "<div class=\"reg-man-rc-google-map-container reg-man-rc-ajax-form-listener\" $data>";

				if ( $this->get_is_show_loading_indicator() ) {
					// This is a busy spinner which is shown (on the client side) when the map is loading
					echo '<div class="reg-man-rc-map-loading-indicator spinner"></div>';
				} // endif

				$marker_array = $this->get_map_markers(); // array of markers to be placed on the map
				$json_data = self::get_marker_json_data( $marker_array, $this->get_map_type() );
				$lat = esc_attr( $this->get_lat() );
				$lng = esc_attr( $this->get_lng() );
				$zoom = esc_attr( $this->get_zoom() );

				// The map will be constructed on the client side
				echo "<div class=\"reg-man-rc-google-map\" data-lat=\"$lat\" data-lng=\"$lng\" data-zoom=\"$zoom\">";
				echo '</div>';

				// Render the form to retrieve markers dynamically
				$this->render_ajax_form();

				// This script element will contain the json data for any markers on the map
				echo '<script class="reg-man-rc-map-marker-json">'; // Script tag for map markers as json
					echo $json_data;
				echo '</script>';

			echo '</div>';
		} // endif
	} // function

	/**
	 * Get the ajax form used to retrieve markers.
	 * We need to be able to create this before rendering so we can get its ID and make it available to users of this class.
	 * @return \Reg_Man_RC\View\Ajax_Form
	 */
	private function get_ajax_form() {
		if ( ! isset( $this->ajax_form ) ) {
			// TODO: This object needs an ID and the form has to be told what it is so it can find me later!!!
			// Or I could create ajax-form-listener and ajax form could always trigger on all listeners passing itself
			// Ideally it would trigger and pass its ID, the listener would have form ID data ?
			$action = $this->get_ajax_marker_provider_action();
			$method = 'GET';
			$classes = 'reg-man-rc-map-marker-data-form no-busy';
			$this->ajax_form = Ajax_Form::create( $action, $method, $classes );
		} // endif
		return $this->ajax_form;
	} // function

	/**
	 * Get the ID for the ajax form used to retrieve marker data
	 * @return string
	 */
	public function get_map_marker_ajax_form_id() {
		$ajax_form = $this->get_ajax_form();
		$result = $ajax_form->get_form_id();
		return $result;
	} // function

	private function render_ajax_form() {

		$ajax_form = $this->get_ajax_form();
		$action = $this->get_ajax_marker_provider_action();
		$form_id = $ajax_form->get_form_id();

//		$ajax_form->set_include_nonce_fields( FALSE );

		 // Autocomplete off blocks browser from autofilling the field from a cached page
		$field_format = '<input type="hidden" name="%1$s" value="%2$s" autocomplete="off">';

		$map_type = $this->get_map_type();

		switch( $action ) {

			case Map_Controller::AJAX_GET_STATS_MARKER_DATA:
				 // Autocomplete off blocks browser from autofilling the field from a cached page
				 // We use the same input names as the filter form just to keep things simple
				$fields = array();
				$fields[] = sprintf( $field_format, Event_Filter_Input_Form::YEAR_INPUT_NAME, '' );
				$fields[] = sprintf( $field_format, Event_Filter_Input_Form::CATEGORY_INPUT_NAME, '' );
				$fields[] = sprintf( $field_format, Map_Controller::MAP_TYPE_INPUT_FIELD_NAME, $map_type );
				$content = implode( ' ', $fields );
				break;

			case Map_Controller::AJAX_GET_CALENDAR_MARKER_DATA:
				// I will provide these fields but the calendar will fill them in as appropriate
				$fields = array();
				$fields[] = sprintf( $field_format, Map_Controller::MIN_DATE_INPUT_FIELD_NAME, '' );
				$fields[] = sprintf( $field_format, Map_Controller::MAX_DATE_INPUT_FIELD_NAME, '' );
				$fields[] = sprintf( $field_format, Map_Controller::CALENDAR_ID_INPUT_FIELD_NAME, '' );
				$fields[] = sprintf( $field_format, Map_Controller::IS_SHOW_PAST_EVENTS_INPUT_FIELD_NAME, '' );
				$fields[] = sprintf( $field_format, Map_Controller::MAP_TYPE_INPUT_FIELD_NAME, $map_type );
				$content = implode( ' ', $fields );
				break;

			default:
				Error_Log::log_msg( "Unknown AJAX action for Google Map: $action" );
				$content = '';

		} // endswitch

		$ajax_form->add_form_content( $content );
		$ajax_form->render_in_footer();

	} // function

	/**
	 * Convert the specified array of map markers into json data that can be understood by a google map
	 * @param	Map_Marker[]	$marker_array
	 * @return	string			The json data derived from the specified map markers
	 * @since	v0.1.0
	 */
	public static function get_marker_json_data( $marker_array, $map_type = self::MAP_TYPE_OBJECT_PAGE ) {
		$marker_data_array = array(); // we'll encode the markers as a data array at the end
//		Error_Log::var_dump( $marker_array );
		foreach ( $marker_array as $marker ) {
			if ( $marker instanceof Map_Marker ) {
				// Note that markers with no position WILL be included so that the map can generate a message
				$geo = $marker->get_map_marker_geographic_position( $map_type );
				$pos = isset( $geo ) ? $geo->get_as_google_map_marker_position() : NULL;
				$info = $marker->get_map_marker_info( $map_type );
				$label = $marker->get_map_marker_label( $map_type );
				if ( ! ( $label instanceof Map_Marker_Label ) && is_string( $label ) ) {
					// I will always add my class so markers have an opaque background etc.
					$label = Map_Marker_Label::create( $label );
				} // endif
				$opacity = 1; // TODO: add opacity to map marker
				$opacity = ! empty( $opacity ) ? floatval( $opacity ) : 1;
				$marker_data = array();
				$marker_data[ 'id' ]			= $marker->get_map_marker_id( $map_type );
				$marker_data[ 'title' ]			= $marker->get_map_marker_title( $map_type );
				$marker_data[ 'label' ]			= $label;
				$marker_data[ 'location' ]		= $marker->get_map_marker_location( $map_type );
				$marker_data[ 'position' ]		= $pos;
				$marker_data[ 'zoom' ]			= $marker->get_map_marker_zoom_level( $map_type );
				$marker_data[ 'colour' ]		= $marker->get_map_marker_colour( $map_type );
				$marker_data[ 'opacity' ]		= $marker->get_map_marker_opacity( $map_type );
				if ( ! empty( $info ) ) {
					// Avoid having an empty info window
					$info = "<div class=\"reg-man-rc-info-window-container $map_type\">$info</div>";
					$marker_data[ 'infocontent' ]	= $info;
				} // endif
				$marker_data_array[] = $marker_data;
			} // endif
		} // endfor
//		Error_Log::var_dump( $marker_data_array );
		$result = json_encode( array_values( $marker_data_array ) );
		return $result;
	} // function
} // class