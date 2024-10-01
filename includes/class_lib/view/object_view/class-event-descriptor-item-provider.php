<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\Model\Event_Class;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * An instance of this class provides List_Item instances for displaying the details of an event descriptor
 *
 */
class Event_Descriptor_Item_Provider implements List_item_Provider {

	private $event_descriptor;
	private $events_array; // An array of events (for this descriptor) whose details are to be included
	private $object_view;
	private $delegate_item_provider;

	private function __construct() {
	} // funciton

	/**
	 * Create a new instance of this class for the specified event descriptor
	 * @param Event $event_descriptor
	 * @param Object_View	$object_view
	 */
	public static function create( $event_descriptor, $object_view ) {
		$result = new self();
		$result->event_descriptor = $event_descriptor;
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Get the event descriptor object for this view.
	 * @return	Event_Descriptor
	 * @since	v0.1.0
	 */
	private function get_event_descriptor() {
		return $this->event_descriptor;
	} // function

	/**
	 * Get the array of events for this item provider
	 * @return	Event[]
	 * @since	v0.4.0
	 */
	private function get_events_array() {
		if ( ! isset( $this->events_array ) ) {
			$event_descriptor = $this->get_event_descriptor();
			$this->events_array = Event::get_events_array_for_event_descriptor( $event_descriptor );
		} // endif
		return $this->events_array;
	} // function

	/**
	 * Set the array of events for this item provider
	 * @param	Event[]
	 * @since	v0.4.0
	 */
	public function set_events_array( $events_array ) {
		$this->events_array = $events_array;
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
	 * Get the array of delegate item providers for this view.
	 * @return	List_item_Provider[]
	 * @since	v0.1.0
	 */
	private function get_delegate_item_provider() {
		if ( ! isset( $this->delegate_item_provider ) ) {
			$event_descriptor = $this->get_event_descriptor();
			$venue = $event_descriptor->get_event_venue();
			$object_view = $this->get_object_view();
			if ( isset( $venue ) ) {
				$this->delegate_item_provider = Venue_Item_Provider::create( $venue, $object_view );
			} else {
				$location_name = NULL; // with no venue there's no location name
				$location_address = $event_descriptor->get_event_location();
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
		$result = NULL;
		$is_found = FALSE;
		switch( $item_name ) {

			case List_Item::EVENT_STATUS:
				$is_found = TRUE;
				$result = $this->get_event_status_item();
				break;

			case List_Item::EVENT_VISIBILITY:
				$is_found = TRUE;
				$result = $this->get_event_visibility_item();
				break;

			case List_Item::EVENT_CATEGORIES:
				$is_found = TRUE;
				$result = $this->get_event_categories_item();
				break;

			case List_Item::EVENT_UPCOMING_DATES:
				$is_found = TRUE;
				$result = $this->get_event_upcoming_dates_item();
				break;

			case List_Item::EVENT_DATE:
				$is_found = TRUE;
				$result = $this->get_event_dates_item();
				break;

			case List_Item::EVENT_FIXER_STATIONS:
				$is_found = TRUE;
				$result = $this->get_event_fixer_stations_item();
				break;

			case List_Item::EVENT_DESCRIPTION:
				$is_found = TRUE;
				$result = $this->get_event_description_item();
				break;

			case List_Item::MORE_DETAILS_LINK:
				$is_found = TRUE;
				$result = $this->get_more_details_link_item();
				break;

			case List_Item::ADMIN_EVENT_EDIT_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_edit_link_item();
				break;

		} // endswitch

		if ( ! $is_found ) {
			$delegate_provider = $this->get_delegate_item_provider();
			$result = $delegate_provider->get_list_item( $item_name );
		} // endif

		return $result;

	} // function

	/**
	 * Get the event status item.
	 * @return	List_Item|NULL	The list item for the event status or NULL if the status should not be display
	 *  for example it is confirmed and the setting is off for those markers
	 * @since	v0.1.0
	 */
	public function get_event_status_item() {
		$event_status = $this->get_event_descriptor()->get_event_status();
		$is_event_complete = FALSE; // For an event descriptor there is no event so the status should exclude "is complete"
		$result = self::create_event_status_item( $event_status, $is_event_complete );
		return $result;
	} // function

	/**
	 * Create an event status item using the specified event descriptor, and a flag for whether the event is complete.
	 * @param	Event_Status	$event_status		The event status object
	 * @param	boolean			$is_event_complete	A flag set to TRUE to indicate the event is in the past, FALSE otherwise
	 * @return	List_Item|NULL	The list item for the event status or NULL if the status should not be show,
	 *  for example if the event status is Confirmed and the site is set up to not show those markers.
	 * @since	v0.1.0
	 */
	public static function create_event_status_item( $event_status, $is_event_complete ) {

		$result = NULL; // Assume we'll show nothing
		$status_id = $event_status->get_id();
		$event_status_class = 'status-' . strtolower( $status_id );

		if ( $is_event_complete ) {
			$event_status_class .= ' status-complete';
		} // endif

		$show_confirmed = Settings::get_show_confirmed_event_marker();

		/*  We will show the event status under any of the following conditions
		 *  - The event is complete, "This event has ended"
		 *  - The status is anything other than confirmed, "This event is cancelled"
		 *  - The setting is turned on to show a marker for confirmed events (and this event is confirmed), "Confirmed"
		 */
		if ( $is_event_complete || $status_id !== Event_Status::CONFIRMED || $show_confirmed ) {

			if ( $status_id === Event_Status::CONFIRMED ) {
				$icon = 'info';
				$text = $is_event_complete ? __( 'This event has ended', 'reg-man-rc' ) : __( 'Confirmed', 'reg-man-rc' );

			} elseif ( $status_id === Event_Status::CANCELLED ) {
				$icon = 'dismiss';
				$text = $is_event_complete ? __( 'This event was cancelled', 'reg-man-rc' ) : __( 'This event is cancelled', 'reg-man-rc' );

			} else {
				$icon = 'warning';
				$text = $is_event_complete ? __( 'This event was tentatively scheduled', 'reg-man-rc' ) : __( 'This event is tentatively scheduled', 'reg-man-rc' );

			} // endif

			$classes = "event-status $event_status_class";
			$icon_title = __( 'Event status', 'reg-man-rc' );
			$result = List_Item::create( $text, $icon, $icon_title, $classes );

		} // endif

		return $result;
	} // function

	/**
	 * Render the event visibility
	 */
	private function get_event_visibility_item() {

		$result = NULL; // Assume nothing
		$event_class = $this->get_event_descriptor()->get_event_class();

		if ( ! empty( $event_class ) ) {
			$event_class_id = $event_class->get_id();
			$icon = 'hidden';
			$icon_text = __( 'Event visibility', 'reg-man-rc' );
			$classes = "reg-man-rc-object-view-details-event-visibility event-class-{$event_class_id}";

			switch( $event_class_id ) {

				case Event_Class::PUBLIC:
				defaut:
					$result = NULL; // In this case we won't show any visibility marker
					break;

				case Event_Class::PRIVATE:
				case Event_Class::CONFIDENTIAL:
					$text = __( 'This event is not visible to the public or volunteers.', 'reg-man-rc' );
					$result = List_Item::create( $text, $icon, $icon_text, $classes );
					break;

			} // endswitch

		} // endif

		return $result;

	} // function


	/**
	 * Get the event categories item
	 * @return	List_Item|NULL	An item showing the event's categories if any are assigned, NULL otherwise
	 * @since	v0.1.0
	 */
	private function get_event_categories_item() {
		$result = NULL; // Assume nothing
		if ( Settings::get_show_event_category() ) {

			$event_category_array = $this->get_event_descriptor()->get_event_categories();
			if ( ! empty( $event_category_array ) ) {

				$separator = _x( ', ', 'A separator for a list of items like "a, b, c"', 'reg-man-rc' );
				$category_text = implode( $separator, $event_category_array );
				$icon = 'category';
				$icon_title = esc_attr__( 'Event Category', 'reg-man-rc' );
				$classes = 'reg-man-rc-object-view-details-event-categories';
				$result = List_Item::create( $category_text, $icon, $icon_title, $classes );

			} // endif

		} // endif

		return $result;
	} // function

	/**
	 * Get an upcoming event dates item
	 * @return	List_Item|NULL	An item showing the event's upcoming dates if this is a recurring event that has upcoming dates,
	 *   NULL otherwise
	 * @since	v0.1.0
	 */
	private function get_event_upcoming_dates_item() {
		$result = NULL; // Assume nothing
		$event_descriptor = $this->get_event_descriptor();
		if ( $event_descriptor->get_event_is_recurring() ) {

			$icon = 'calendar-alt';
			$icon_title = __( 'Upcoming event dates', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-recurring-event-upcoming-dates';

			$now = new \DateTime();
			$event_array = Event::get_events_array_for_event_descriptor( $event_descriptor );
			$upcoming_event_filter = Event_Filter::create();
			$upcoming_event_filter->set_accept_minimum_date_time( $now );
			$upcoming_event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
			$upcoming_event_array = $upcoming_event_filter->apply_filter( $event_array );

			if ( ! empty( $upcoming_event_array ) ) {

				// Include an item to indicate its a recurring event with its upcoming dates
				$date_group_title	= __( 'Upcoming event dates', 'reg-man-rc' );
				$link_type = Object_View::OBJECT_PAGE_TYPE_EVENT;
				$is_open = FALSE;
				$item_content = Event_View::create_event_date_group_details_element( $date_group_title, $upcoming_event_array, $link_type, $is_open );
				$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

			} else {

				// Include an item to indicate its a recurring event with no upcoming dates
				$item_content = __( 'No upcoming dates scheduled for this recurring event', 'reg-man-rc' );
				$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

			} // endif

		} // endif

		return $result;

	} // function

	
	/**
	 * Get an item showing the dates for the events assinged to the events array.
	 * Note that this only makes sense on a calendar map info window or another situation where
	 *  the array of events has been assigned for this descriptor.
	 * @return	List_Item|NULL	An item showing the list of event dates.
	 * @since	v0.4.0
	 */
	private function get_event_dates_item() {
		$result = NULL; // Assume nothing
		$events_array = $this->get_events_array();

		if ( ! empty( $events_array ) && ( count( $events_array ) > 1 ) ) {

			$object_view = $this->get_object_view();
			$map_type = $object_view->get_info_window_map_type(); // This is only ever inside a map

			// I need to sort these so the dates are not in random order
			$event_filter = Event_Filter::create();
			$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
			$events_array = $event_filter->apply_filter( $events_array );

			switch ( $map_type ) {
				
				case Map_View::MAP_TYPE_OBJECT_PAGE:
				case Map_View::MAP_TYPE_CALENDAR_EVENTS:
				default:
					$date_group_title	= __( 'Event dates', 'reg-man-rc' ); // Note that not all events have links
					$icon = 'calendar-alt';
					$icon_title = __( 'Event dates', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_EVENT;
					$is_open = TRUE;
					$date_class_array = array();
					break;

				case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
					$date_group_title	= __( 'Event dates (tap/click to view registration)', 'reg-man-rc' );
					$icon = 'text-page';
					$icon_title = __( 'Volunteer event registration details pages', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_VOLUNTEER_REGISTRATION;
					$is_open = TRUE;
					// Mark my registered events with special classes
					$date_class_array = $this->get_event_date_vol_reg_class_array();
					break;

				case Map_View::MAP_TYPE_CALENDAR_ADMIN:
					$date_group_title	= __( 'Event dates (tap/click for more details)', 'reg-man-rc' );
					$icon = 'text-page';
					$icon_title = __( 'Event dates', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_ADMIN_CALENDAR_EVENT_DETAILS;
					$is_open = FALSE;
					$date_class_array = array();
					break;

				case Map_View::MAP_TYPE_ADMIN_STATS:
					$date_group_title	= __( 'Event dates', 'reg-man-rc' );
					$icon = 'calendar-alt';
					$icon_title = __( 'Event dates', 'reg-man-rc' );
					$link_type = NULL;
					$is_open = FALSE;
					$date_class_array = array();
					break;

				case Map_View::MAP_TYPE_CALENDAR_VISITOR_REG:
					$date_group_title	= __( 'Event dates (tap/click to launch registration)', 'reg-man-rc' );
					$icon = 'calendar-alt';
					$icon_title = __( 'Event dates', 'reg-man-rc' );
					$link_type = Object_View::OBJECT_PAGE_TYPE_VISITOR_REGISTRATION;
					$is_open = TRUE;
					$date_class_array = array();
					break;

			} // endswitch

			$item_content = Event_View::create_event_date_group_details_element(
							$date_group_title, $events_array, $link_type, $is_open, $exclude_event = NULL, $date_class_array );

			$classes = 'reg-man-rc-object-view-details-event-location-group-dates';

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

		} // endif

		return $result;

	} // function

	
	/**
	 * Get the fixer stations item
	 * @return	List_Item|NULL	An item showing the event's fixer stations, or null if no stations are assigned
	 * @since	v0.1.0
	 */
	private function get_event_fixer_stations_item() {

		$result = NULL; // Assume nothing
		$event_desc = $this->get_event_descriptor();
		$fixer_station_array = $event_desc->get_event_fixer_station_array();
		$is_non_repair = $event_desc->get_event_is_non_repair();

		if ( $is_non_repair ) {

			// TODO: maybe this message should be a setting
			$item_content = __( 'Non-repair event', 'reg-man-rc' );
			$icon = 'admin-tools';
			$icon_title  = __( 'Fixing', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-fixer-stations';

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

		} elseif ( ! is_array( $fixer_station_array ) ) {
			
			// The fixer stations are set to NULL or some other non-array thing
			// In that case we won't say anything, maybe the event description says what stations there are

		} elseif ( empty( $fixer_station_array ) ) {
			// This is an empty array for a repair event which we will interpret as TBD

			$item_content = __( 'Fixing - To be determined', 'reg-man-rc' );
			$icon = 'admin-tools';
			$icon_title  = __( 'Fixing', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-fixer-stations';

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes );

		} else {
			// This is a non-empty array of stations, the normal case
			$icon = 'admin-tools';
			$title  = __( 'Fixing', 'reg-man-rc' );
			$icon_title = $title;
			$item_content = $title;
			$item_format = '<li class="%2$s">%1$s</li>';
			ob_start();
				echo '<ul class="object-view-details-fixer-station-list">';
					foreach( $fixer_station_array as $station ) {
						$image_attachment_array = $station->get_icon_image_attachment_array();
						if ( empty( $image_attachment_array ) ) {
							$name = $station->get_name();
							printf( $item_format, $name, 'reg-man-rc-object-view-fixer-station-text' );
						} else {
							// When there is only 1 icon, use the station name as the caption
							// When there are multiple icons, we'll let the icon determine its caption
							$caption = ( count( $image_attachment_array ) === 1 ) ? $station->get_name() : NULL;
							foreach( $image_attachment_array as $image_attachment ) {
								$figure = $image_attachment->get_thumbnail_figure_element( $caption );
								printf( $item_format, $figure, '' );
							} // endfor
						} // endif
					} // endfor
				echo '</ul>';
			$addn_content = ob_get_clean();

			$classes = 'reg-man-rc-object-view-details-event-fixer-stations';

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes, $addn_content );

		} // endif

		return $result;

	} // function

	/**
	 * Get the event description item
	 * @return	List_Item|NULL	An item showing the event's description, or null if none is assigned
	 * @since	v0.1.0
	 */
	private function get_event_description_item() {

		$result = NULL; // Assume nothing
		$event_description = $this->get_event_descriptor()->get_event_description();

		if ( ! empty( $event_description) ) {

			$item_content = __( 'Event details', 'reg-man-rc' );
			$icon = 'text-page';
			$icon_title = esc_attr__( 'Event Description', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-description';
			$addn_content = wpautop( $event_description ); // add paragraph tags for line breaks
			$result = List_Item::create( $item_content, $icon, $icon_title, $classes, $addn_content );

		} // endif

		return $result;

	} // function

	/**
	 * Get the venue description item
	 * @return	List_Item|NULL	An item showing the venue description, or null if none is assigned
	 * @since	v0.1.0
	 */
	private function get_event_venue_description_item() {

		$result = NULL; // Assume nothing
		$venue = $this->get_event_descriptor()->get_event_venue();
		$venue_description = isset( $venue ) ? $venue->get_description() : NULL;

		if ( ! empty( $venue_description) ) {

			$item_content = __( 'Venue details', 'reg-man-rc' );
			$icon = 'text-page';
			$icon_title = esc_attr__( 'Venue Description', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-venue-description';
			$addn_content = wpautop( $venue_description ); // add paragraph tags for line breaks
			$result = List_Item::create( $item_content, $icon, $icon_title, $classes, $addn_content );

		} // endif

		return $result;

	} // function

	/**
	 * Get an item with a link to a page to see more details.
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	private function get_more_details_link_item( ) {
		$event_descriptor = $this->get_event_descriptor();
		$href = $event_descriptor->get_event_page_url();
		$result = List_Item::create_more_details_link_item( $href );
		return $result;
	} // function

	private function get_admin_event_edit_link_item() {
		$result = NULL;
		$event_descriptor = $this->get_event_descriptor();
		$href = $event_descriptor->get_event_edit_url();
		if ( ! empty( $href ) ) {
			$link_text = __( 'Edit event details', 'reg-man-rc' );
			$icon = 'edit';
			$icon_title = __( 'Edit the details of this event', 'reg-man-rc' );
			$result = Event_Item_Provider::create_admin_link_item( $href, $link_text, $icon, $icon_title );
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
		$events = $this->get_events_array();
		foreach( $events as $event ) {
			$vol_reg = $event->get_volunteer_registration_for_current_request();
			if ( ! empty( $vol_reg ) ) {
				$key = $event->get_key_string();
				$result[ $key ] = 'vol-reg-registered';
			} // endif
		} // endif
		return $result;
	} // function

} // class