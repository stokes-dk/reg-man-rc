<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_View;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\View\Admin\Item_Admin_View;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\View\Admin\Admin_Dashboard_Page;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;

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

			case List_Item::VISITOR_REG_LAUNCH_LINK:
				$is_found = TRUE;
				$result = $this->get_visitor_reg_launch_link_item();
				break;

			case List_Item::ADMIN_EVENT_VIEW_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_view_link_item();
				break;
				
			case List_Item::ADMIN_EVENT_VOLUNTEERS:
				$is_found = TRUE;
				$result = $this->get_admin_event_volunteers_item();
				break;
				
			case List_Item::ADMIN_EVENT_VOLUNTEERS_ITEMIZED:
				$is_found = TRUE;
				$result = $this->get_admin_event_volunteers_item( $is_itemized = TRUE );
				break;
				
			case List_Item::ADMIN_EVENT_ITEMS:
				$is_found = TRUE;
				$result = $this->get_admin_event_items_item();
				break;
				
			case List_Item::ADMIN_EVENT_ITEMS_ITEMIZED:
				$is_found = TRUE;
				$result = $this->get_admin_event_items_item( $is_itemized = TRUE );
				break;

			case List_Item::ADMIN_EVENT_VOL_AREA_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_volunteer_area_link_item();
				break;
				
			case List_Item::ADMIN_EVENT_MORE_DETAILS_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_more_details_link_item();
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

	
	private function get_admin_event_view_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = $event->get_event_page_url();
		if ( ! empty( $href ) ) {
			$link_text = __( 'View public page', 'reg-man-rc' );
			$icon = 'text-page';
			$icon_title = __( 'Go to the public event page', 'reg-man-rc' );
			$result = self::create_admin_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function
	
	private function get_admin_event_items_item( $is_itemized = FALSE ) {
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key();
		
		$icon = 'clipboard';
		$icon_title = __( 'Items', 'reg-man-rc' );

		$classes = 'reg-man-rc-object-view-details-admin-stats-item';

		$event_keys_array = array( $event_key );
		
		$group_by = Item_Stats_Collection::GROUP_BY_TOTAL;
		$item_stats_collection = Item_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );

		// Total Items
		$item_totals_array = array_values( $item_stats_collection->get_all_stats_array() );
		$item_total_stats = isset( $item_totals_array[ 0 ] ) ? $item_totals_array[ 0 ] : NULL;
		$item_total = isset( $item_total_stats ) ? $item_total_stats->get_item_count() : 0;
		/* Translators: %s is a count of items */
		$item_total_text = sprintf( _n( '%s item', '%s items', $item_total, 'reg-man-rc' ), number_format_i18n( $item_total ) );
		
		$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL;
		$visitor_stats_col = Visitor_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );

		// Visitor Total
		$visitor_totals_array = array_values( $visitor_stats_col->get_all_stats_array() );
		$visitor_total_stats = isset( $visitor_totals_array[ 0 ] ) ? $visitor_totals_array[ 0 ] : NULL;
		$visitor_total = isset( $visitor_total_stats ) ? $visitor_total_stats->get_visitor_count() : 0;
		/* Translators: %s is a count of visitors */
		$visitor_total_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_total, 'reg-man-rc' ), number_format_i18n( $visitor_total ) );
		
		// Total including items and visitors
		/* Translators: %1$s is the number of items like "25 items", %2$s is visitors like "20 visitors" */
		$total_text = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_total_text, $visitor_total_text );

		$sub_item_array = array();
		if ( $is_itemized ) {
			$sub_item_format = '<li>%1$s</li>';
	
			// Registered Items and visitors
			$item_totals_array = array_values( $item_stats_collection->get_internal_registered_stats_array() );
			$item_total_stats = isset( $item_totals_array[ 0 ] ) ? $item_totals_array[ 0 ] : NULL;
			$item_internal_count = isset( $item_total_stats ) ? $item_total_stats->get_item_count() : 0;
			$item_total_text = sprintf( _n( '%s item', '%s items', $item_internal_count, 'reg-man-rc' ), number_format_i18n( $item_internal_count ) );
			
			$visitor_totals_array = array_values( $visitor_stats_col->get_internal_registered_stats_array() );
			$visitor_total_stats = isset( $visitor_totals_array[ 0 ] ) ? $visitor_totals_array[ 0 ] : NULL;
			$visitor_reg_count = isset( $visitor_total_stats ) ? $visitor_total_stats->get_visitor_count() : 0;
			$visitor_total_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_reg_count, 'reg-man-rc' ), number_format_i18n( $visitor_reg_count ) );
			
			/* Translators: %1$s is the number of items like "25 items", %2$s is visitors like "20 visitors" */
			$sub_item_text = sprintf( __( 'Registered: %1$s, %2$s', 'reg-man-rc' ), $item_total_text, $visitor_total_text );
			
			$filter_array = array( Item::EVENT_META_KEY => $event_key );
			$reg_manage_href = Item_Admin_View::get_admin_view_href( $filter_array );
			if ( ! empty( $reg_manage_href ) ) {
				$link_text = __( 'manage', 'reg-man-rc' );
				$link_format = '<a class="object-view-admin-link" href="%2$s">%1$s</a>';
				$link = sprintf( $link_format, $link_text, $reg_manage_href );
				/* Translators: %1$s is a count like "5 registered items, 2 visitors", %2$s is link to manage the items */
				$count_and_link_format = __( '%1$s (%2$s)', 'reg-man-rc' );
				$sub_item_text = sprintf( $count_and_link_format, $sub_item_text, $link );
			} // endif
			$sub_item = sprintf( $sub_item_format, $sub_item_text );
			$sub_item_array[] = $sub_item;
			
			// External Items and visitors
			$item_totals_array = array_values( $item_stats_collection->get_external_registered_stats_array() );
			$item_total_stats = isset( $item_totals_array[ 0 ] ) ? $item_totals_array[ 0 ] : NULL;
			$item_ext_count = isset( $item_total_stats ) ? $item_total_stats->get_item_count() : 0;
			$visitor_totals_array = array_values( $visitor_stats_col->get_external_registered_stats_array() );
			$visitor_total_stats = isset( $visitor_totals_array[ 0 ] ) ? $visitor_totals_array[ 0 ] : NULL;
			$visitor_ext_count = isset( $visitor_total_stats ) ? $visitor_total_stats->get_visitor_count() : 0;
			if ( ! empty( $item_ext_count ) || ! empty( $visitor_ext_count ) ) {
				$item_count_text = sprintf( _n( '%s item', '%s items', $item_ext_count, 'reg-man-rc' ), number_format_i18n( $item_ext_count ) );
				$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_ext_count, 'reg-man-rc' ), number_format_i18n( $visitor_ext_count ) );
				/* Translators: %1$s is the number of items like "25 items", %2$s is visitors like "20 visitors" */
				$sub_item_text = sprintf( __( 'Legacy: %1$s, %2$s', 'reg-man-rc' ), $item_count_text, $visitor_count_text );
				$sub_item = sprintf( $sub_item_format, $sub_item_text );
				$sub_item_array[] = $sub_item;
			} // endif

			// Supplemental Items and visitors

			$button_text = __( 'manage...', 'reg-man-rc' );
			$button_format = '<button class="object-view-admin-supplemental-stats-button %2$s" type="button" data-event-key="%3$s">%1$s</button>';
			/* Translators: %1$s is a count like "5 supplemental items, %2$s is button to manage the items */
			$sup_count_and_button_format = __( '%1$s %2$s', 'reg-man-rc' );
			
			$item_totals_array = array_values( $item_stats_collection->get_supplemental_stats_array() );
			$item_total_stats = isset( $item_totals_array[ 0 ] ) ? $item_totals_array[ 0 ] : NULL;
			$item_sup_count = isset( $item_total_stats ) ? $item_total_stats->get_item_count() : 0;
			$sub_item_text = sprintf( _n( '%s item', '%s items', $item_sup_count, 'reg-man-rc' ), number_format_i18n( $item_sup_count ) );
			$button_class = 'supplemental-items-button';
			$button = sprintf( $button_format, $button_text, $button_class, $event_key );
			$item_sup_content = sprintf( $sup_count_and_button_format, $sub_item_text, $button );
	
			// Supplemental Visitors
			$visitor_totals_array = array_values( $visitor_stats_col->get_supplemental_stats_array() );
			$visitor_total_stats = isset( $visitor_totals_array[ 0 ] ) ? $visitor_totals_array[ 0 ] : NULL;
			$visitor_sup_count = isset( $visitor_total_stats ) ? $visitor_total_stats->get_visitor_count() : 0;
			$sub_item_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_sup_count, 'reg-man-rc' ), number_format_i18n( $visitor_sup_count ) );
			$button_class = 'supplemental-visitors-button';
			$button = sprintf( $button_format, $button_text, $button_class, $event_key );
			$visitor_sup_content = sprintf( $sup_count_and_button_format, $sub_item_text, $button );

			/* Translators: %1$s is the number of items like "25 items", %2$s is visitors like "20 visitors" */
			$sub_item_content = sprintf( __( 'Supplemental: %1$s, %2$s', 'reg-man-rc' ), $item_sup_content, $visitor_sup_content );
			$sub_item = sprintf( $sub_item_format, $sub_item_content );
			$sub_item_array[] = $sub_item;
			
		} // endif
		
		$addn_content = ! empty( $sub_item_array ) ? '<ul>' . implode( '', $sub_item_array ) . '</ul>' : '';
			
		$result = List_Item::create( $total_text, $icon, $icon_title, $classes, $addn_content );
			
		return $result;
	} // function
	

	private function get_admin_event_volunteers_item( $is_itemized = FALSE ) {
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key();
		
		$icon = 'admin-users';
		$icon_title = __( 'Volunteers', 'reg-man-rc' );
		$classes = 'reg-man-rc-object-view-details-admin-stats-item';
		
		$event_keys_array = array( $event_key );
		$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL;
		$stats = Volunteer_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );

		// Total
		$totals_array = array_values( $stats->get_all_stats_array() );
		$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
		$volunteer_total = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
		/* Translators: %s is a count of volunteers */
		$volunteer_total_text = sprintf( _n( '%s volunteer', '%s volunteers', $volunteer_total, 'reg-man-rc' ), number_format_i18n( $volunteer_total ) );
		
		$sub_item_array = array();
		if ( $is_itemized ) {
			$sub_item_format = '<li>%1$s</li>';
			
			// Registered Volunteers
			$filter_array = array( Volunteer_Registration::EVENT_META_KEY => $event_key );
			$reg_manage_href = Volunteer_Registration_Admin_View::get_admin_view_href( $filter_array );
			$totals_array = array_values( $stats->get_internal_registered_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$internal_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
			if ( ! empty( $reg_manage_href ) ) {
				/* Translators: %s is a count of volunteers */
				$sub_item_text = sprintf( _n( 'Registered: %s volunteer', 'Registered: %s volunteers', $internal_count, 'reg-man-rc' ), number_format_i18n( $internal_count ) );
				$link_text = __( 'manage', 'reg-man-rc' );
				$link_format = '<a class="object-view-admin-link" href="%2$s">%1$s</a>';
				$link = sprintf( $link_format, $link_text, $reg_manage_href );
				/* Translators: %1$s is a count like "5 registered volunteers, %2$s is link to manage the volunteers */
				$count_and_link_format = __( '%1$s (%2$s)', 'reg-man-rc' );
				$sub_item_content = sprintf( $count_and_link_format, $sub_item_text, $link );
				$sub_item = sprintf( $sub_item_format, $sub_item_content );
				$sub_item_array[] = $sub_item;
			} // endif
	
			// External Volunteers
			$totals_array = array_values( $stats->get_external_registered_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$ext_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
			if ( ! empty( $ext_count ) ) {
				/* Translators: %s is a count of volunteers */
				$sub_item_text = sprintf( _n( 'Legacy: %s volunteer', 'Legacy: %s volunteers', $ext_count, 'reg-man-rc' ), number_format_i18n( $ext_count ) );
				$sub_item = sprintf( $sub_item_format, $sub_item_text );
				$sub_item_array[] = $sub_item;
			} // endif
	
			// Supplemental Volunteers
			$totals_array = array_values( $stats->get_supplemental_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$sup_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
			/* Translators: %s is a count of volunteers */
			$sub_item_text = sprintf( _n( 'Supplemental: %s volunteer', 'Supplemental: %s volunteers', $sup_count, 'reg-man-rc' ), number_format_i18n( $sup_count ) );
			$button_text = __( 'manage...', 'reg-man-rc' );
			$button_format = '<button class="object-view-admin-supplemental-stats-button %2$s" type="button" data-event-key="%3$s">%1$s</button>';
			$button_class = 'supplemental-volunteers-button';
			$button = sprintf( $button_format, $button_text, $button_class, $event_key );
			/* Translators: %1$s is a count like "5 supplemental volunteers, %2$s is button to manage the volunteers */
			$count_and_button_format = __( '%1$s %2$s', 'reg-man-rc' );
			$sub_item_content = sprintf( $count_and_button_format, $sub_item_text, $button );
			$sub_item = sprintf( $sub_item_format, $sub_item_content );
			$sub_item_array[] = $sub_item;

		} // endif
		
		$addn_content = ! empty( $sub_item_array ) ? '<ul>' . implode( '', $sub_item_array ) . '</ul>' : '';
			
		$result = List_Item::create( $volunteer_total_text, $icon, $icon_title, $classes, $addn_content );
			
		return $result;
	} // function
	
	
	private function get_admin_event_volunteer_area_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = Volunteer_Area::get_href_for_event_page( $event );
		if ( ! empty( $href ) ) {
			$link_text = __( 'Volunteer area registration page' );
			$icon = 'welcome-write-blog';
			$icon_title = __( 'Volunteer area', 'reg-man-rc' );
			$result = self::create_admin_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function
	
	private function get_admin_event_more_details_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = Admin_Dashboard_Page::get_href_for_event_page( $event );
		
		if ( ! empty( $href ) ) {
			$link_text = __( 'More details' );
			$icon = 'text-page';
			$icon_title = __( 'More admin details', 'reg-man-rc' );
			$result = self::create_admin_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function
	
	/**
	 * Create an item with a link to an admin page.
	 * @param	string	$href			The href attribute for the link
	 * @param	string	$link_text		The text for the link.
	 * @param	string	$icon			The name of the dashicons icon to be used for this item, e.g. 'location-alt'
	 * @param	string	$icon_title		The rollover title for the icon, e.g. 'Location'
	 * @param	string	$classes		Optional classes to be applied to the item
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	public static function create_admin_link_item( $href, $link_text, $icon, $icon_title, $classes = '' ) {
		if ( ! empty( $href ) ) {
			$link = "<a class=\"object-view-admin-link\" href=\"$href\">$link_text</a>";
			$classes = "reg-man-rc-object-view-details-admin-link-item $classes";
			$result = List_Item::create( $link, $icon, $icon_title, $classes );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function
	
} // class