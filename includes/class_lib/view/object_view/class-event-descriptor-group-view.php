<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event_Group_Map_Marker;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Map_View;

/**
 * An instance of this class provides rendering for a group of event descriptors.
 * Note that this is only used to produce content for a map info window in the case
 * where a single map marker represents several event descriptors.
 *
 * @since	v0.4.0
 *
 */
class Event_Descriptor_Group_View extends Abstract_Object_View {

	private $event_group_map_marker;
	private $item_provider;

	private $after_title_item_names_array;
	private $details_item_names_array;
	private $details_items_array;

	/**
	 * A private constructor forces users to use one of the factory methods
	 */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class to display the map info window content for
	 *  a group of event descriptors.
	 * @param	Event_Group_Map_Marker	$event_group_map_marker	The event group map marker for in this view.
	 * @return	Event_Descriptor_Group_View
	 * @since	v0.1.0
	 */
	public static function create_for_map_info_window( $event_group_map_marker, $map_type = Map_View::MAP_TYPE_OBJECT_PAGE ) {
		$result = new self();
		$result->event_group_map_marker = $event_group_map_marker;
		$result->initialize_title();
		$result->set_info_window_map_type( $map_type );
		return $result;
	} // function
	
	private function initialize_title() {
		$events_array = $this->get_events_array();
		$event_count = count( $events_array );
		/* Translators: %1$s is a count of events */
		$format = _n( '%1$s event at this location', '%1$s events at this location', $event_count, 'reg-man-rc' );
		$title = sprintf( $format, number_format_i18n( $event_count ) );
		$this->set_title( $title );
	} // function

	/**
	 * Get the event group map marker
	 * @return	Event_Group_Map_Marker
	 * @since	v0.4.0
	 */
	public function get_event_group_map_marker() {
		return $this->event_group_map_marker;
	} // function

	/**
	 * Get the array of event descriptor objects for this view.
	 * @return	Event_Descriptor[]
	 * @since	v0.4.0
	 */
	private function get_event_descriptors_array() {
		$group_map_marker = $this->get_event_group_map_marker();
		return $group_map_marker->get_event_descriptors_array();
	} // function

	/**
	 * Get the array of events for this item provider
	 * @return	Event[]
	 * @since	v0.4.0
	 */
	private function get_events_array() {
		$group_map_marker = $this->get_event_group_map_marker();
		return $group_map_marker->get_events_array();
	} // function

	/**
	 * Get the item provider for this view
	 * @return Location_Item_Provider
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {

			$event_group_map_marker = $this->get_event_group_map_marker();
			$location_name = $event_group_map_marker->get_place_name();
			$location_address = $event_group_map_marker->get_location();
			$object_view = $this;
			$this->item_provider = Location_Item_Provider::create( $location_name, $location_address, $object_view );
			
		} // endif
		return $this->item_provider;
	} // function

	/**
	 * Get the section (if any) to be rendered after the title
	 * @return Object_View_Section	The section to be displayed after the title
	 */
	public function get_object_view_after_title_section() {
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_after_title_item_names_array();
		$result = List_Section::create( $item_provider, $item_names );
		return $result;
	} // function

	
	/**
	 * Get the array of main content sections.
	 * @return Object_View_Section[]
	 */

	public function get_object_view_main_content_sections_array() {

		$result = array();
		// The descriptor contents are rendered by the template
		return $result;

	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_after_title_item_names_array() {
		if ( ! $this->get_is_map_info_window() ) {
			
			$result = array(); // This view is only valid for a map

		} else {
			
			switch( $this->get_info_window_map_type() ) {
					
				case Map_View::MAP_TYPE_CALENDAR_EVENTS:
				case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
				default:
					$result = array(
							List_Item::LOCATION_NAME,
							List_Item::LOCATION_ADDRESS,
							List_Item::GET_DIRECTIONS_LINK,
					);
					break;

				case Map_View::MAP_TYPE_ADMIN_STATS:
				case Map_View::MAP_TYPE_CALENDAR_ADMIN:
				case Map_View::MAP_TYPE_CALENDAR_VISITOR_REG:
				case Map_View::MAP_TYPE_OBJECT_PAGE:
					$result = array(
							List_Item::LOCATION_NAME,
							List_Item::LOCATION_ADDRESS,
					);
					break;
					
			} // endswitch
		} // endif
		return $result;
	} // function

	/**
	 * This provides a convenience for getting the contents of an object view already rendered by the template.
	 * @param	Object_View_Template	$template	The template used to render the content or NULL to use default template
	 * @param	string					$classes	Any classes to be added to the outermost element of the content
	 * @return	string	The content of the view as a string
	 */
	public function get_object_view_content( $template = NULL, $classes = NULL ) {
		if ( ! $template instanceof Object_View_Template ) {
			$template = Event_Descriptor_Group_View_Template::create( $this );
			$template->set_classes( $classes );
		} // endif
		$result = $template->get_content();
		return $result;
	} // function

} // class