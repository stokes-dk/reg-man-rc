<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Object_View\List_Item;
use Reg_Man_RC\View\Object_View\List_Section;
use Reg_Man_RC\View\Object_View\Map_Section;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Event_Group_Map_Marker;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Object_View\Abstract_Object_View;
use Reg_Man_RC\View\Object_View\Event_Group_Map_Marker_Item_Provider;

/**
 * An instance of this class provides rendering for an Event object.
 *
 * @since	v0.1.0
 *
 */
class Event_Group_Map_Marker_View extends Abstract_Object_View {

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
	 * A factory method to create an instance of this class to display the map info window content for an event location group.
	 * @param	Event_Group_Map_Marker	$event_group_map_marker	The event location group shown in this view.
	 * @return	Event_View
	 * @since	v0.1.0
	 */
	public static function create_for_map_info_window( $event_group_map_marker, $map_type = Map_View::MAP_TYPE_OBJECT_PAGE ) {
		$result = new self();
		$result->event_group_map_marker = $event_group_map_marker;
		$result->set_title( $event_group_map_marker->get_map_marker_title( $map_type ) );
		$result->set_info_window_map_type( $map_type );
		return $result;
	} // function

	/**
	 * Get the event location group for this view
	 * @return Event_Group_Map_Marker
	 */
	private function get_event_group_map_marker() {
		return $this->event_group_map_marker;
	} // function

	/**
	 * Get the item provider for this view
	 * @return Event_Group_Map_Marker_Item_Provider
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {
			$this->item_provider = Event_Group_Map_Marker_Item_Provider::create( $this->get_event_group_map_marker(), $this );
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

		// Details section
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_details_item_names_array();
		$result[] = List_Section::create( $item_provider, $item_names );

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

				case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
					$result = array(
							List_Item::EVENT_STATUS,
							List_Item::EVENT_VISIBILITY,
							List_item::VOLUNTEER_REG_STATUS,
					);
					break;

				default:
					$result = array(
							List_Item::EVENT_STATUS,
							List_Item::EVENT_VISIBILITY,
					);
					break;

			} // endswitch
		} // endif
		return $result;
	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_details_item_names_array() {

		if ( ! $this->get_info_window_map_type() ) {
			$result = array(); // This view is only valid for a map

		} else {

			switch( $this->get_info_window_map_type() ) {

				case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
					$result = array(
							List_Item::EVENT_CATEGORIES,
							List_Item::EVENT_DATE,
							List_Item::LOCATION_NAME,
							List_Item::LOCATION_ADDRESS,
							List_Item::EVENT_FIXER_STATIONS,
							List_Item::VOLUNTEER_REG_DETAILS_LINK,
					);
					break;

				case Map_View::MAP_TYPE_CALENDAR_ADMIN:
					$result = array(
							List_Item::EVENT_CATEGORIES,
							List_Item::EVENT_DATE,
							List_Item::LOCATION_NAME,
							List_Item::LOCATION_ADDRESS,
							List_Item::EVENT_FIXER_STATIONS,
							List_Item::ADMIN_EVENT_VIEW_LINK,
							List_Item::ADMIN_EVENT_EDIT_LINK,
							List_Item::ADMIN_EVENT_VOLUNTEERS_LINK,
							List_Item::ADMIN_EVENT_ITEMS_LINK,
							List_Item::ADMIN_EVENT_VOL_AREA_LINK,
							);
					break;

				default:
					$result = array(
							List_Item::EVENT_CATEGORIES,
							List_Item::EVENT_DATE,
							List_Item::LOCATION_NAME,
							List_Item::LOCATION_ADDRESS,
							List_Item::GET_DIRECTIONS_LINK,
							List_Item::EVENT_FIXER_STATIONS,
							List_Item::MORE_DETAILS_LINK,
					);
					break;

			} // endswitch

		} // endif
		return $result;
	} // function

} // class