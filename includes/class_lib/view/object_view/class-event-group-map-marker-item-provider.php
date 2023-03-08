<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Event_Group_Map_Marker;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Volunteer_Registration_View;

/**
 * An instance of this class provides List_Item instances for displaying the details of an event
 *
 */
class Event_Group_Map_Marker_Item_Provider implements List_Item_Provider {

	private $event_group_map_marker;
	private $object_view;
	private $delegate_item_provider;

	private function __construct() {
	} // funciton

	/**
	 * Create a new instance of this class for the specified event
	 * @param Event_Group_Map_Marker	$event_group_map_marker
	 * @param Object_View			$object_view
	 */
	public static function create( $event_group_map_marker, $object_view ) {
		$result = new self();
		$result->event_group_map_marker = $event_group_map_marker;
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Get the event locaiton group object for this item provider
	 * @return	Event_Group_Map_Marker
	 * @since	v0.1.0
	 */
	private function get_event_group_map_marker() {
		return $this->event_group_map_marker;
	} // function

	/**
	 * Get the object view client for this item provider
	 * @return	Object_View		The object view object using this item provider
	 * @since	v0.1.0
	 */
	private function get_object_view() {
		return $this->object_view;
	} // function

	/**
	 * Get the delegate item provider if one is available.
	 * In the case that this group consists of exactly 1 event then the delegate is Event_Item_Provider
	 * If the group has only events from exactly 1 descriptor then the delegate is Event_Descriptor_Item_Provider
	 * If the group has multiple event descriptors then I will handle it all myself
	 * @return	List_Item_Provider		The delegate item provider
	 * @since	v0.1.0
	 */
	private function get_delegate_item_provider() {
		if ( ! isset( $this->delegate_item_provider ) ) {

			$loc_group = $this->get_event_group_map_marker();
			$sole_event = $loc_group->get_sole_event();
			$sole_event_desc = $loc_group->get_sole_event_descriptor();
			$object_view = $this->get_object_view();

			if ( isset( $sole_event ) ) {

				$this->delegate_item_provider = Event_Item_Provider::create( $sole_event, $object_view );

			} elseif ( isset( $sole_event_desc ) ) {

				$this->delegate_item_provider = Event_Descriptor_Item_Provider::create( $sole_event_desc, $object_view );

			} else {

				$loc_group = $this->get_event_group_map_marker();
				$location_name = NULL;
				$location_address = $loc_group->get_location();
				$this->delegate_item_provider = Location_Item_Provider::create( $location_name, $location_address, $object_view );

			} // endif
		} // endif
		return $this->delegate_item_provider;
	} // function

	/**
	 * Get a list item based on its name.  May return NULL if the name is not known or there's no content to display.
	 * @return List_Item|NULL
	 */
	public function get_list_item( $item_name ) {
		$result = NULL; // Assume we can't handle this name

		// Note that, even when there are many event descriptors things like location are handled by Location Item provider

		switch( $item_name ) {

			case List_Item::VOLUNTEER_REG_STATUS:
				$result = $this->get_registration_status_item();
				break;

			case List_Item::EVENT_CATEGORIES:
				$result = $this->get_event_categories_item();
				break;

			case List_Item::EVENT_DATE:
				$result = $this->get_event_dates_item();
				break;

			case List_Item::EVENT_FIXER_STATIONS:
				// TODO: Try to make a list of fixer stations?
				break;

			case List_Item::VOLUNTEER_REG_DETAILS_LINK:
				$result = $this->get_volunteer_reg_details_link_item();
				break;

		} // endswitch

		if ( ! isset( $result ) ) {
			$delegate = $this->get_delegate_item_provider();
			$result = $delegate->get_list_item( $item_name );
		} // endif

		return $result;
	} // function

	/**
	 * Get a registration status item for this group if possible
	 * @return List_Item|NULL
	 */
	private function get_registration_status_item() {
		$result = NULL;
		$group = $this->get_event_group_map_marker();
		$sole_event = $group->get_sole_event();

		if ( isset( $sole_event ) ) {
			$volunteer_registration = $sole_event->get_volunteer_registration();
			$result = Volunteer_Registration_View::create_registration_status_item( $sole_event, $this->get_object_view() );
		} // endif

		return $result;
	} // function



	/**
	 * Get the list item for the group categories
	 * @return List_Item|NULL
	 */
	private function get_event_categories_item() {
		$result = NULL; // assume nothing
		if ( Settings::get_show_event_category() ) {

			$category_array = array();
			$event_desc_array = $this->get_event_group_map_marker()->get_event_descriptors_array();
			foreach( $event_desc_array as $desc ) {
				$desc_cat_array = $desc->get_event_categories();
				foreach( $desc_cat_array as $desc_cat ) {
					$category_array[ $desc_cat ] = TRUE;
				} // endfor
			} // endfor

			if ( ! empty( $category_array ) ) {
				$separator = _x( ', ', 'A separator for a list of items like "a, b, c"', 'reg-man-rc' );
				$category_text = implode( $separator, array_keys( $category_array ) );
				$icon = 'category';
				$icon_title = esc_attr__( 'Event Category', 'reg-man-rc' );
				$classes = 'reg-man-rc-object-view-details-event-categories';
				$result = List_Item::create( $category_text, $icon, $icon_title, $classes );
			} // endif

		} // endif

		return $result;

	} // function


	/**
	 * Get an event dates item
	 * @return	List_Item|NULL	An item showing the list of event dates.
	 * @since	v0.1.0
	 */
	private function get_event_dates_item() {
		$result = NULL; // Assume nothing
		$event_loc_group = $this->get_event_group_map_marker();
		$events_array = $event_loc_group->get_events_array();

		if ( ! empty( $events_array ) && ( count( $events_array ) > 1 ) ) {

			$object_view = $this->get_object_view();
			$map_type = $object_view->get_info_window_map_type(); // This is only ever inside a map

			// I need to sort these so the dates are not in random order
			$event_filter = Event_Filter::create();
			$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
			$events_array = $event_filter->apply_filter( $events_array );

			switch ( $map_type ) {

				case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
					$date_group_title	= __( 'Volunteer event registration details', 'reg-man-rc' );
					$icon = 'text-page';
					$icon_title = __( 'Volunteer event registration details pages', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_VOLUNTEER_REG;
					// Mark my registered events with special classes
					$date_class_array = $this->get_event_date_vol_reg_class_array();
					break;

				case Map_View::MAP_TYPE_CALENDAR_ADMIN:
					$date_group_title	= __( 'View', 'reg-man-rc' );
					$icon = 'text-page';
					$icon_title = __( 'Event pages', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_EVENT;
					// Mark my registered events with special classes
					$date_class_array = $this->get_event_date_vol_reg_class_array();
					break;

				default:
					$date_group_title	= __( 'Event dates', 'reg-man-rc' );
					$icon = 'calendar-alt';
					$icon_title = __( 'Event dates', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_EVENT;
					$date_class_array = array();
					break;

			} // endswitch

			$is_open = TRUE;
			$item_content = Event_View::create_event_date_group_details_element(
							$date_group_title, $events_array, $link_type, $is_open, $exclude_event = NULL, $date_class_array );

			$classes = 'reg-man-rc-object-view-details-event-location-group-dates';

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

		} // endif

		return $result;

	} // function

	/**
	 * Get an array of strings of class names keyed by event key to be applied to the event dates array.
	 * E.g. array( '[1234]' => 'vol-reg-registered'
	 * This allows us to mark event dates as registered or not for the volunteer
	 * @return string[]
	 */
	private function get_event_date_vol_reg_class_array() {
		$result = array();
		$events = $this->get_event_group_map_marker()->get_events_array();
		foreach( $events as $event ) {
			$vol_reg = $event->get_volunteer_registration();
			if ( isset( $vol_reg ) ) {
				$key = $event->get_key();
				$result[ $key ] = 'vol-reg-registered';
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get an item with a link to the volunteer registration details page
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	private function get_volunteer_reg_details_link_item() {
		$result = NULL;
		$group = $this->get_event_group_map_marker();
		$sole_event = $group->get_sole_event();
		if ( isset( $sole_event ) ) {
			$result = Volunteer_Registration_View::create_volunteer_reg_details_link_item( $sole_event );
		} // endif
		return $result;
	} // function



} // class