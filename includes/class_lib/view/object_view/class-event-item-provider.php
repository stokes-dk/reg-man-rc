<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Control\Volunteer_Registration_Controller;
use Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_View;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\View\Admin\Item_Admin_View;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\View\Pub\Volunteer_Area;

/**
 * An instance of this class provides List_Item instances for displaying the details of an event
 *
 */
class Event_Item_Provider implements List_Item_Provider {

	private $event;
	private $object_view;
	private $delegate_item_provider;

	private function __construct() {
	} // funciton

	/**
	 * Create a new instance of this class for the specified event
	 * @param Event 		$event
	 * @param Object_View	$object_view
	 */
	public static function create( $event, $object_view ) {
		$result = new self();
		$result->event = $event;
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Get the event object for this item provider
	 * @return	Event		The event object shown in this item provider
	 * @since	v0.1.0
	 */
	private function get_event() {
		return $this->event;
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
	 * Get the event descriptor item provider used as a delegate for this item provider
	 * @return	Event_Descriptor_Item_Provider		The event descriptor item provider delegate
	 * @since	v0.1.0
	 */
	private function get_delegate_item_provider() {
		if ( ! isset( $this->delegate_item_provider ) ) {
			$event_descriptor = $this->get_event()->get_event_descriptor();
			$object_view = $this->get_object_view();
			$this->delegate_item_provider = Event_Descriptor_Item_Provider::create( $event_descriptor, $object_view );
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

			case List_Item::ADMIN_EVENT_VIEW_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_view_link_item();
				break;

			case List_Item::ADMIN_EVENT_VOLUNTEERS_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_volunteers_item();
				break;

			case List_Item::ADMIN_EVENT_ITEMS_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_items_item();
				break;

			case List_Item::ADMIN_EVENT_VOL_AREA_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_volunteer_area_link_item();
				break;

			case List_Item::VISITOR_REG_LAUNCH_LINK:
				$is_found = TRUE;
				$result = $this->get_visitor_reg_launch_link_item();
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

		$date_label = Event_View::create_event_date_label_for_event( $event );
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

			$event_descriptor = $this->get_event_descriptor();

			$icon = 'calendar-alt';
			$icon_title = esc_attr__( 'Event Date', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-date-time';

			$date_label = Event_View::create_event_date_label_for_event( $event );
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
				$addn_content = Object_View::create_event_date_group_details_element( $title, $event_array, $link_type, $is_open, $exclude_event );

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
	private function get_more_details_link_item() {
		$event = $this->get_event();
		$href = $event->get_event_page_url();
		$result = List_Item::create_more_details_link_item( $href );
		return $result;
	} // function

	private function get_admin_event_view_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = $event->get_event_page_url();
		if ( ! empty( $href ) ) {
			$link_text = __( 'View', 'reg-man-rc' );
			$icon = 'text-page';
			$icon_title = __( 'Go to event page', 'reg-man-rc' );
			$result = List_Item::create_admin_text_and_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function

	private function get_admin_event_volunteers_item() {
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key();
		$filter_array = array( Volunteer_Registration::EVENT_META_KEY => $event_key );
		$href = Volunteer_Registration_Admin_View::get_admin_view_href( $filter_array );

		if ( ! empty( $href ) ) {
			$event_keys_array = array( $event_key );
			$fixer_reg = Volunteer_Registration::get_fixer_registrations_for_event_keys( $event_keys_array );
			$fixer_count = count( $fixer_reg );
			$fixer_text = sprintf( _n( '%s fixer', '%s fixers', $fixer_count, 'reg-man-rc' ), number_format_i18n( $fixer_count ) );
			$non_fixer_reg = Volunteer_Registration::get_non_fixer_registrations_for_event_keys( $event_keys_array );
			$non_fixer_count = count( $non_fixer_reg );
			$non_fixer_text = sprintf( _n( '%s non-fixer', '%s non-fixers', $non_fixer_count, 'reg-man-rc' ), number_format_i18n( $non_fixer_count ) );
			/* Translators: %1$s is text showing a count of fixers like "3 fixers", %2$s is same for non-fixers */
			$link_text = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $fixer_text, $non_fixer_text );
			$icon = 'admin-users';
			$icon_title = __( 'Volunteers', 'reg-man-rc' );
			$result = List_Item::create_admin_text_and_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function

	private function get_admin_event_items_item() {
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key();
		$filter_array = array( Item::EVENT_META_KEY => $event_key );
		$href = Item_Admin_View::get_admin_view_href( $filter_array );

		if ( ! empty( $href ) ) {
			$item_reg = Item::get_items_registered_for_event( $event_key );
			$item_count = count( $item_reg );
			$link_text = sprintf( _n( '%s item', '%s items', $item_count, 'reg-man-rc' ), number_format_i18n( $item_count ) );
			$icon = 'clipboard';
			$icon_title = __( 'Items', 'reg-man-rc' );
			$result = List_Item::create_admin_text_and_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function

	private function get_admin_event_volunteer_area_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = Volunteer_Area::get_href_for_event_page( $event );

		if ( ! empty( $href ) ) {
			$link_text = __( 'Volunteer area registration page' );
			$icon = 'welcome-write-blog';
			$icon_title = __( 'Volunteer registration', 'reg-man-rc' );
			$result = List_Item::create_admin_text_and_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function

	private function get_visitor_reg_launch_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$event_key_string = $event->get_key();
		$href = Visitor_Reg_Manager::get_event_registration_href( $event_key_string );
		$classes = 'reg-man-rc-visitor-registration-launch-link-container reg-man-rc-icon-text-container';
		$label_text = esc_html__( 'Launch Registration', 'reg-man-rc' );
		$label = "<span class=\"reg-man-rc-object-view-item-icon dashicons dashicons-clipboard icon\"></span><span class=\"text\">$label_text</span>";
		$content = "<a href=\"$href\" class=\"reg-man-rc-visitor-registration-launch-link\">$label</a>";
		$result = List_Item::create( $content, $icon = '', $icon_title = '', $classes );
		return $result;
	} // function


} // class