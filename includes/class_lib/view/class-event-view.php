<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Object_View\List_Item;
use Reg_Man_RC\View\Object_View\List_Section;
use Reg_Man_RC\View\Object_View\Map_Section;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Object_View\Abstract_Object_View;
use Reg_Man_RC\View\Object_View\Event_Item_Provider;
use Reg_Man_RC\View\Object_View\Object_View;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\View\Object_View\Event_Descriptor_Item_Provider;
use Reg_Man_RC\Model\Event_Filter;

/**
 * An instance of this class provides rendering for an Event object.
 *
 * @since	v0.1.0
 *
 */
class Event_View extends Abstract_Object_View {

	private $event;
	private $item_provider;

	/**
	 * A private constructor forces users to use one of the factory methods
	 */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class to display the page content for an event.
	 * @param	Event	$event	The event object shown in this view.
	 * @return	Event_View		An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create_for_page_content( $event ) {
		$result = new self();
		$result->event = $event;
		$result->set_object_page_type( Object_View::OBJECT_PAGE_TYPE_EVENT );
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class to display the calendar info window content for an event.
	 * @param	Event	$event	The event object shown in this view.
	 * @return	Event_View
	 * @since	v0.1.0
	 */
	public static function create_for_calendar_info_window( $event, $calendar_type ) {
		$result = new self();
		$result->event = $event;
		$result->set_title( $event->get_summary() );
		$result->set_info_window_calendar_type( $calendar_type );
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class to display the map info window content for an event.
	 * @param	Event	$event	The event object shown in this view.
	 * @return	Event_View
	 * @since	v0.1.0
	 */
	public static function create_for_map_info_window( $event, $map_type = Map_View::MAP_TYPE_OBJECT_PAGE ) {
		$result = new self();
		$result->event = $event;
		$result->set_title( $event->get_summary() );
		$result->set_info_window_map_type( $map_type );
		return $result;
	} // function


	/**
	 * Render a select element for events
	 *
	 * @param	string		$input_name			The name
	 * @param	string		$classes			The css classes to be used in the select element
	 * @param	Calendar	$calendar			The calendar whose events are to be shown in the select
	 * @param	string		$selected_event_key	The key for the event to be initially selected
	 * @param	string		$first_option		The contents of the first option to appear in the select
	 * @param	boolean		$is_required		TRUE if the element should be marked as required, FALSE otherwise
	 * @return	void
	 */
	public static function render_event_select( $input_name, $classes, $calendar, $selected_event_key, $first_option = NULL, $is_required = FALSE ) {

		// Disabled to start with until it is initialized on the client side
		echo "<select class=\"combobox $classes\" name=\"$input_name\" autocomplete=\"off\" disabled=\"disabled\">";

			if ( ! empty( $first_option ) ) {
				echo $first_option;
			} // endif

			$now = new \DateTime( 'now', wp_timezone() );

			$event_filter = Event_Filter::create_for_calendar( $calendar );
			$event_filter->set_accept_minimum_date_time( $now );
			$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
			$upcoming_events_array = Event::get_all_events_by_filter( $event_filter );

			$event_filter = Event_Filter::create();
			$event_filter->set_accept_maximum_date_time( $now );
			$event_filter->set_is_accept_boundary_spanning_events( FALSE ); // ongoing events are in "upcoming" array
			$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_DESCENDING );
			$past_events_array = Event::get_all_events_by_filter( $event_filter );

			if ( ! empty( $upcoming_events_array ) ) {
				$label = esc_attr__( 'Upcoming Events', 'reg-man-rc' );
				echo "<optgroup label=\"$label\">";
					foreach( $upcoming_events_array as $event ) {
						$name = esc_html( $event->get_label() );
						$id = $event->get_key();
						$id_attr = esc_attr( $id );
						$selected = selected( $selected_event_key, $id, FALSE );
						echo "<option value=\"$id_attr\" $selected>$name</option>";
					} // endfor
				echo '</optgroup>';
			} // endif

			if ( ! empty( $past_events_array ) ) {
				$label = esc_attr__( 'Past Events', 'reg-man-rc' );
				echo "<optgroup label=\"$label\">";
					foreach( $past_events_array as $event ) {
						$name = esc_html( $event->get_label() );
						$id = $event->get_key();
						$id_attr = esc_attr( $id );
						$selected = selected( $selected_event_key, $id, FALSE );
						echo "<option value=\"$id_attr\" $selected>$name</option>";
					} // endfor
				echo '</optgroup>';
			} // endif

		echo '</select>';

	} // function





	/**
	 * Get the event object for this view
	 * @return	Event		The event object shown in this view
	 * @since	v0.1.0
	 */
	private function get_event() {
		return $this->event;
	} // function

	/**
	 * Get the event descriptor item provider for this view
	 * @return Event_Descriptor_Item_Provider
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {
			$this->item_provider = Event_Item_Provider::create( $this->get_event(), $this );
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
		$event = $this->get_event();

		if ( $this->get_is_object_page() ) {
			// Map
			$result[] = Map_Section::create( $event );
		} // endif

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
		$result = array(
				List_Item::EVENT_STATUS,
				List_Item::EVENT_VISIBILITY,
		);
		return $result;
	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_details_item_names_array() {

		if ( $this->get_is_object_page() ) {
			$result = array(
					List_Item::EVENT_CATEGORIES,
					List_Item::EVENT_DATE,
					List_Item::LOCATION_NAME,
					List_Item::LOCATION_ADDRESS,
					List_Item::GET_DIRECTIONS_LINK,
					List_Item::EVENT_FIXER_STATIONS,
					List_Item::EVENT_DESCRIPTION,
					List_Item::VENUE_DESCRIPTION,
			);
		} elseif ( $this->get_is_calendar_info_window() ) {
			$result = $this->get_details_item_names_array_for_calendar();

		} else {
			$result = $this->get_details_item_names_array_for_map();

		} // endif
		return $result;
	} // function


	/**
	 * Get the item names array for the details section in a calendar info window
	 */
	private function get_details_item_names_array_for_calendar() {
		$calendar_type = $this->get_info_window_calendar_type();
		switch( $calendar_type ) {

			case Calendar::CALENDAR_TYPE_ADMIN_EVENTS:
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

			case Calendar::CALENDAR_TYPE_VISITOR_REG:
				$result = array(
						List_Item::EVENT_CATEGORIES,
						List_Item::EVENT_DATE,
						List_Item::LOCATION_NAME,
						List_Item::LOCATION_ADDRESS,
						List_Item::EVENT_FIXER_STATIONS,
						List_Item::VISITOR_REG_LAUNCH_LINK,
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

		return $result;

	} // function


	/**
	 * Get the item names array for the details section in a map info window
	 */
	private function get_details_item_names_array_for_map() {
		$map_type = $this->get_info_window_map_type();
		switch( $map_type ) {

			case Map_View::MAP_TYPE_OBJECT_PAGE:
			default:
				// We're showing an info window on the object's page
				$result = array(
						List_Item::EVENT_CATEGORIES,
						List_Item::LOCATION_NAME,
						List_Item::LOCATION_ADDRESS,
						List_Item::GET_DIRECTIONS_LINK,
				);
				break;

		} // endswitch

		return $result;

	} // function



	/**
	 * Get the label for an event date.
	 * @param	Event	$event	An event whose date label is to be returned
	 */
	public static function create_event_date_label_for_event( $event, $href = NULL ) {

		$start_dt = $event->get_start_date_time_local_timezone_object();
		$end_dt = $event->get_end_date_time_local_timezone_object();
		$classes = 'reg-man-rc-object-view-event-date-label';

		if ( ! isset( $start_dt ) && ! isset( $end_dt ) ) {

			$date_label = esc_html__( 'Date to be determined', 'reg-man-rc' );
			$classes .= ' object-view-event-date-tbd';

		} else {

			$date_label = Event::create_label_for_event_dates_and_times( $start_dt, $end_dt );

		} // endif

		$date_markup = ! empty( $href ) ? "<a href=\"$href\">$date_label</a>" : $date_label;

		$result = "<span class=\"$classes\">$date_markup</span>";

		return $result;

	} // function

	/**
	 * Get an html details element that contains a group of dates
	 * @param	string		$title				The title for to appear in the summary section
	 * @param	Event[]		$event_array		An array of events whose dates are to be inserted in the element
	 * @param	string		$link_type			To render the title as a link to a page, the type of object link.
	 *   One of the Object_View::OBJECT_PAGE_TYPE_* constants.  To render as plain text set this to NULL or FALSE
	 * @param	boolean		$is_open			TRUE if the event dates group should be open by default
	 * @param	Event		$exclude_event		An event to be excluded from the list, e.g. an event page does not show its own date
	 * @param	string[]	$date_class_array	An array of class strings to be applied to each event, keyed by event key.
	 *   E.g. array( '1234' => 'is-registered' )
	 * @return	List_Item|NULL	An item showing the event's upcoming dates if this is a recurring event that has upcoming dates,
	 *   NULL otherwise
	 * @since	v0.1.0
	 */
	public static function create_event_date_group_details_element( $title, $event_array, $link_type, $is_open, $exclude_event = NULL, $date_class_array = array() ) {

		$html_title = esc_html( $title );

		$exclude_key = isset( $exclude_event ) ? $exclude_event->get_key() : NULL;
		ob_start();
			$open_attr = $is_open ? 'open="open"' : '';
			echo "<details class=\"reg-man-rc-object-view-event-group-details\" $open_attr>";
				echo '<summary>';
					echo "<span class=\"reg-man-rc-object-view-details-text\">$html_title</span>";
				echo '</summary>';
				echo '<ul class="reg-man-rc-object-view-event-date-list">';
					if ( empty( $event_array ) ) {
						$no_events_label = __( 'No events scheduled', 'reg-man-rc' );
						echo '<li class="object-view-event-list-date-time-item">' . $no_events_label . '<li>';
					} else {
						foreach ( $event_array as $event ) {
							$event_key = $event->get_key();
							$event_status = $event->get_status();
							$class_array = array();
							$class_array[] = strtolower( $event_status->get_id() );
							if ( $event->get_is_event_complete() ) {
								$class_array[] = 'completed';
							} // endif
							if ( isset( $date_class_array[ $event_key ] ) ) {
								$class_array[] = $date_class_array[ $event_key ];
							} // endif
							$classes = implode( ' ', $class_array );
							if ( ! isset( $exclude_key ) || ( $exclude_key !== $event_key ) ) {
								$date_label = self::create_event_date_label_for_event( $event );
								echo "<li class=\"object-view-event-list-date-time-item $classes\">";

									if ( ! empty( $link_type ) ) {
										switch( $link_type ) {

											case Object_View::OBJECT_PAGE_TYPE_VOLUNTEER_REG:
												$link_url = Volunteer_Area::get_href_for_event_page( $event );
												break;

											case Object_View::OBJECT_PAGE_TYPE_EVENT:
											default:
												$link_url = $event->get_event_page_url();
												break;

										} // endswitch
									} else {
										$link_url = NULL;
									} // endif

									if ( ! empty( $link_url ) ) {
										$date_label = "<a href=\"$link_url\">$date_label</a>";
									} // endif

									echo $date_label;

								echo '</li>';
							} // endif
						} // endfor
					} // endif
				echo '</ul>';
			echo '</details>';
		$result = ob_get_clean();

		return $result;

	} // function

	/* Item provider implementation */

	/**
	 * Get the event descriptor item provider used as a delegate for this item provider
	 * @return	Event_Descriptor_Item_Provider		The event descriptor item provider delegate
	 * @since	v0.1.0
	 */
	private function get_delegate_item_provider() {
		if ( ! isset( $this->delegate_item_provider ) ) {
			$event_descriptor = $this->get_event()->get_event_descriptor();
			$this->delegate_item_provider = Event_Descriptor_Item_Provider::create( $event_descriptor, $this );
		} // endif
		return $this->delegate_item_provider;
	} // function

	/**
	 * Get a list item based on its name.  May return NULL if the name is not known or there's no content to display.
	 * @return List_Item|NULL
	 */
	public function get_list_item( $item_name ) {
		$result = NULL;
		$is_found = FALSE; // don't look for the same name twice if we find it but have a NULL item

		switch( $item_name ) {

			case List_Item::EVENT_STATUS:
				$is_found = TRUE;
				$result = $this->get_event_status_item();
				break;

			case List_Item::EVENT_DATE:
				$is_found = TRUE;
				$result = $this->get_date_item();
				break;

			case List_Item::MORE_DETAILS_LINK:
				$is_found = TRUE;
				$result = $this->get_more_details_link_item();
				break;

		} // endswitch

		if ( ! $is_found ) {
			$event_desc_provider = $this->get_delegate_item_provider();
			$result = $event_desc_provider->get_list_item( $item_name );
		} // endif

		return $result;
	} // function

	/**
	 * Get a single event date as an item.
	 * @param	boolean		$is_render_link	TRUE if the date should be rendered as a link to the event page
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	private function get_date_item() {

		$result = NULL; // Assume there's no item to return

		$event = $this->get_event();

		$icon = 'calendar-alt';
		$icon_title = esc_attr__( 'Event Date', 'reg-man-rc' );
		$classes = 'reg-man-rc-object-view-details-event-date-time';

		$date_label = self::create_event_date_label_for_event( $event );
		$item_content = "<span class=\"reg-man-rc-custom-post-details-text $classes\">$date_label</span>";

		$result = List_Item::create( $item_content, $icon, $icon_title, $classes );
		return $result;

	} // function


	/**
	 * Get a single event date as an item.
	 * @param	boolean		$is_render_link	TRUE if the date should be rendered as a link to the event page
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	public function create_event_date_item( $event, $href ) {

		$result = NULL; // Assume there's no item to return

		if ( isset( $event ) ) {
			// If there's no event assigned then we can't display an event date

			$event_descriptor = $this->get_event()->get_event_descriptor();

			$icon = 'calendar-alt';
			$icon_title = esc_attr__( 'Event Date', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-date-time';

			$date_label = self::create_event_date_label_for_event( $event );
			$date_label = esc_html( $date_label );

			$text = ( ! empty( $href ) ) ? "<a href=\"$href\">$date_label</a>" : $date_label;
			$item_content = "<span class=\"reg-man-rc-custom-post-details-text $classes\">$text</span>";

			if ( $is_render_other_dates && $event_descriptor->get_event_is_recurring() ) {

				$now = new \DateTime();
				$event_array = Event::get_events_array_for_event_descriptor( $event_descriptor );
				$upcoming_event_filter = Event_Filter::create();
				$upcoming_event_filter->set_accept_minimum_date_time( $now );
				$upcoming_event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
				$event_array = $upcoming_event_filter->apply_filter( $event_array );

				$link_type = Object_View::OBJECT_PAGE_TYPE_EVENT;
				$is_open = FALSE;
				$title = __( 'Other Upcoming Dates', 'reg-man-rc' );
				$exclude_event = $event;
				$addn_content = self::create_event_date_group_details_element( $title, $event_array, $link_type, $is_open, $exclude_event );

			} else {

				$addn_content = NULL;

			} // endif

			$result = List_Item::create( $item_content, $icon, $icon_title, $classes, $addn_content );

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
		$event = $this->get_event();
		$event_status = $event->get_status();
		$is_event_complete = $event->get_is_event_complete();
		$result = Event_Descriptor_Item_Provider::create_event_status_item( $event_status, $is_event_complete );
		return $result;
	} // function

	/**
	 * Get an item with a link to a page to see more details.
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	private function get_more_details_link_item( ) {
		$event = $this->get_event();
		$href = $event->get_event_page_url();
		$result = List_Item::create_more_details_link_item( $href );
		return $result;
	} // function





} // class