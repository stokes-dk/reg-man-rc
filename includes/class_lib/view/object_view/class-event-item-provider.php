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
use Reg_Man_RC\View\Admin\Admin_Event_Calendar_Page;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;
use Reg_Man_RC\Model\Event_Provider_Factory;
use Reg_Man_RC\Model\Placeholder_Event_Descriptor;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Events_Collection;
use Reg_Man_RC\Model\Stats\Volunteer_Stats;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\Model\Event_Key;

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

			case List_Item::EVENT_PLACEHOLDER_INFO:
				$is_found = TRUE;
				$result = $this->get_event_placeholder_info_item();
				break;

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
				
			case List_Item::ADMIN_EVENT_EDIT_LINK:
				$event = $this->get_event();
				if ( ! empty( $event ) && $event->get_is_placeholder_event() ) {
					// We should not offer an 'Edit' link for a placeholder event
					$is_found = TRUE;
					$result = NULL;
				} // endif
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
				$event = $this->get_event();
				if ( ! empty( $event ) && $event->get_is_placeholder_event() ) {
					// We should not offer a 'Volunteer area registration' link for a placeholder event
					$result = NULL;
				} else {
					$result = $this->get_admin_event_volunteer_area_link_item();
				} // endif
				break;
				
			case List_Item::ADMIN_EVENT_MORE_DETAILS_LINK:
				$is_found = TRUE;
				$result = $this->get_admin_event_more_details_link_item();
				break;
				
			case List_Item::ADMIN_RECUR_DATE_CANCEL_BUTTON:
				$is_found = TRUE;
				$result = $this->get_recur_date_cancel_button_item();
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
	private function get_recur_date_cancel_button_item() {
		$result = NULL; // Assume there's no item to return

		$event = $this->get_event();
		
		// Get the status assigned to the descriptor
		$event_descriptor = $event->get_event_descriptor();
		$descriptor_status = $event_descriptor->get_event_status( NULL );

		if ( $descriptor_status->get_id() !== Event_Status::CANCELLED ) {
			$event_status = $event->get_status();
			$event_date = $event->get_start_date_time_object();
			$button_format = '<button data-event-date="%2$s" class="recur-event-cancel-date-button %3$s reg-man-rc-button">%1$s</button>';
			$label_format = '<span class=" reg-man-rc-icon-text-container"><i class="icon dashicons dashicons-%2$s"></i><span class="text">%1$s</span></span>';
			if ( isset( $event_date ) ) {
				$event_date_str = $event_date->format( Event_Key::EVENT_DATE_FORMAT );
				if ( $event_status->get_id() !== Event_Status::CANCELLED ) {
					$label_text = __( 'Cancel this date', 'reg-man-rc' );
					$icon = 'no';
					$classes = 'cancel-date';
				} else {
					$label_text = __( 'Reinstate this date', 'reg-man-rc' );
					$icon = 'yes';
					$classes = 'uncancel-date';
				} // endif

				$label = sprintf( $label_format, $label_text, $icon );
				$item_content = sprintf( $button_format, $label, $event_date_str, $classes );
		
				$result = List_Item::create( $item_content );
				
			} // endif
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
	 * Get the event placeholder item.
	 * @return	List_Item|NULL	The list item for the event placeholder info or NULL if the event is not a placeholder.
	 * An event is a placeholder when its key cannot be found in the set of known events.
	 * @since	v0.6.0
	 */
	public function get_event_placeholder_info_item() {
		$event = $this->get_event();
		$is_placeholder = $event->get_is_placeholder_event();
		$result = $is_placeholder ? self::create_event_placeholder_info_item( $event ) : NULL;
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
		$event_key_string = $event->get_key_string();
		
		if ( $event->get_is_current_user_able_to_register_items() ) {
		
			$href = Visitor_Reg_Manager::get_event_registration_href( $event_key_string );
			$classes = 'reg-man-rc-visitor-registration-launch-link-container reg-man-rc-icon-text-container';
			$label_text = esc_html__( 'Launch Registration', 'reg-man-rc' );
			$label = "<span class=\"reg-man-rc-object-view-item-icon dashicons dashicons-clipboard icon\"></span><span class=\"text\">$label_text</span>";
			$content = "<a href=\"$href\" class=\"reg-man-rc-visitor-registration-launch-link\">$label</a>";
			$result = List_Item::create( $content, $icon = '', $icon_title = '', $classes );
			
		} else {
		
			// This user is not allowed to register items for this event
			$text = __( 'You are not authorized to register items for this event', 'reg-man-rc' );
			$result = List_Item::create( $text );
			
		} // endif
		
		return $result;
		
	} // function

	
	private function get_admin_event_view_link_item() {
		$result = NULL;
		$event = $this->get_event();
		$href = $event->get_event_page_url();
		if ( ! empty( $href ) ) {
			$link_text = __( 'Public page', 'reg-man-rc' );
			$icon = 'text-page';
			$icon_title = __( 'Go to the public event page', 'reg-man-rc' );
			$result = self::create_admin_link_item( $href, $link_text, $icon, $icon_title );
		} // endif
		return $result;
	} // function
	
	private function get_admin_event_items_item( $is_itemized = FALSE ) {
		
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key_string();
		
		$icon = 'clipboard';
		$icon_title = __( 'Items', 'reg-man-rc' );

		$classes = 'reg-man-rc-object-view-details-admin-stats-item';
		
		// Total Items
		$item_total = $event->get_total_items_count();
		/* Translators: %s is a count of items */
		$item_total_text = sprintf( _n( '%s item', '%s items', $item_total, 'reg-man-rc' ), number_format_i18n( $item_total ) );
		
		// Visitor Total
		$visitor_total = $event->get_total_visitors_count();
		/* Translators: %s is a count of visitors */
		$visitor_total_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_total, 'reg-man-rc' ), number_format_i18n( $visitor_total ) );
		
		// Total including items and visitors
		/* Translators: %1$s is the number of items like "25 items", %2$s is visitors like "20 visitors" */
		$total_text = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_total_text, $visitor_total_text );

		$sub_item_array = array();
		
		$user_can_register_items = $event->get_is_current_user_able_to_register_items();
		
		if ( $is_itemized & $user_can_register_items ) {
			$sub_item_format = '<li>%1$s</li>';
	
			$item_stats_collection = $event->get_total_item_stats_collection();
			$visitor_stats_col = $event->get_total_visitor_stats_collection();
			
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

			$button_text = __( 'modify&hellip;', 'reg-man-rc' );
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
	
	/**
	 * Render a table showing the count of fixers by station
	 * @param Volunteer_Stats_Collection $stats_by_fixer_station
	 */
	private function render_fixer_count_table( $stats_by_fixer_station ) {
		echo '<table class="reg-man-rc-item-provider-table">';
		
			$cell_format = '<%1$s class="%3$s">%2$s</%1$s>';
			$stats_array = $stats_by_fixer_station->get_all_stats_array();
//			Error_Log::var_dump( $stats_array );

			echo '<tr>';
				printf( $cell_format, 'th', __( 'Fixer Station', 'reg-man-rc' ), '' );
				printf( $cell_format, 'th', __( 'Fixers', 'reg-man-rc' ), '' );
				printf( $cell_format, 'th', __( 'Apprentices', 'reg-man-rc' ), '' );
			echo '</tr>';
				
			foreach( $stats_array as $station_id => $volunteer_stats ) {
				$station_id = $volunteer_stats->get_group_name();
				$station = Fixer_Station::get_fixer_station_by_id( $station_id );
				$station_name = isset( $station ) ? $station->get_name() : __( 'Other', 'reg-man-rc'); // Defensive
				$head_count = $volunteer_stats->get_head_count();
				$appr_count = $volunteer_stats->get_apprentice_count();
				$fixer_count = $head_count - $appr_count;

				echo '<tr>';
					printf( $cell_format, 'td', $station_name, '' );
					printf( $cell_format, 'td', $fixer_count, 'number-cell' );
					printf( $cell_format, 'td', $appr_count, 'number-cell' );
				echo '</tr>';
				
			} // endfor
			
		echo '</table>';
	} // function

	/**
	 * Render a table showing the count of non-fixer volunteers by
	 * @param Volunteer_Stats_Collection $stats_by_volunteer_role
	 */
	private function render_volunteer_count_table( $stats_by_volunteer_role ) {
		echo '<table class="reg-man-rc-item-provider-table">';
		
			$cell_format = '<%1$s class="%3$s">%2$s</%1$s>';
			$stats_array = $stats_by_volunteer_role->get_all_stats_array();
//			Error_Log::var_dump( $stats_array );

			echo '<tr>';
				printf( $cell_format, 'th', __( 'Role', 'reg-man-rc' ), '' );
				printf( $cell_format, 'th', __( 'Volunteers', 'reg-man-rc' ), '' );
			echo '</tr>';
				
			foreach( $stats_array as $role_id => $volunteer_stats ) {
				$role_id = $volunteer_stats->get_group_name();
				$vol_role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
				$role_name = isset( $vol_role ) ? $vol_role->get_name() : __( 'Other', 'reg-man-rc'); // Defensive
				$head_count = $volunteer_stats->get_head_count();

				echo '<tr>';
					printf( $cell_format, 'td', $role_name, '' );
					printf( $cell_format, 'td', $head_count, 'number-cell' );
				echo '</tr>';
				
			} // endfor
			
		echo '</table>';
	} // function

	private function get_admin_event_volunteers_item( $is_itemized = FALSE ) {
		$result = NULL;
		$event = $this->get_event();
		$event_key = $event->get_key_string();
		
		$icon = 'admin-users';
		$icon_title = __( 'Volunteers', 'reg-man-rc' );
		$classes = 'reg-man-rc-object-view-details-admin-stats-item';
		
		$events_collection = Events_Collection::create_for_single_event_key( $event_key );
		
		$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL;
		$stats_total = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );

		// Total
		$totals_array = array_values( $stats_total->get_all_stats_array() );
		$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
		$volunteer_total = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
		/* Translators: %s is a count of volunteers */
		$volunteer_total_text = sprintf( _n( '%s volunteer', '%s volunteers', $volunteer_total, 'reg-man-rc' ), number_format_i18n( $volunteer_total ) );
		
		$sub_item_array = array();
		$sub_item_format = '<li>%1$s</li>';
		
		$user_can_register_volunteers = $event->get_is_current_user_able_to_register_volunteers();
		
		if ( ( $volunteer_total > 0 ) && $user_can_register_volunteers ) {
			
			// Show the table of fixers by station
			$group_by = Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION;
			$stats_by_fixer_station = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
			// Error_Log::var_dump( $stats_by_total_fixers->get_all_stats_array() );
			ob_start();
				$this->render_fixer_count_table( $stats_by_fixer_station );
			$fixer_table = ob_get_clean();

			// Show the table of volunteers by role
			$group_by = Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE;
			$stats_by_volunteer_role = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
			// Error_Log::var_dump( $stats_by_volunteer_role->get_all_stats_array() );
			ob_start();
				$this->render_volunteer_count_table( $stats_by_volunteer_role );
			$volunteer_table = ob_get_clean();

			$volunteer_tables_item = sprintf( $sub_item_format, $fixer_table . $volunteer_table );			
			$sub_item_array[] = $volunteer_tables_item;
			
		} // endif
		
		if ( $is_itemized && $user_can_register_volunteers ) {
			
			// Registered Volunteers
			$filter_array = array( Volunteer_Registration::EVENT_META_KEY => $event_key );
			$reg_manage_href = Volunteer_Registration_Admin_View::get_admin_view_href( $filter_array );
			$totals_array = array_values( $stats_total->get_internal_registered_stats_array() );
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
			$totals_array = array_values( $stats_total->get_external_registered_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$ext_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;
			if ( ! empty( $ext_count ) ) {
				/* Translators: %s is a count of volunteers */
				$sub_item_text = sprintf( _n( 'Legacy: %s volunteer', 'Legacy: %s volunteers', $ext_count, 'reg-man-rc' ), number_format_i18n( $ext_count ) );
				$sub_item = sprintf( $sub_item_format, $sub_item_text );
				$sub_item_array[] = $sub_item;
			} // endif
	
			// Supplemental Volunteers
			$totals_array = array_values( $stats_total->get_supplemental_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$sup_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;

			/* Translators: %s is a count of volunteers */
			$sub_item_text = sprintf( _n( 'Supplemental: %s volunteer', 'Supplemental: %s volunteers', $sup_count, 'reg-man-rc' ), number_format_i18n( $sup_count ) );

			$button_text = __( 'modify&hellip;', 'reg-man-rc' );
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
		$href = Admin_Event_Calendar_Page::get_href_for_event_page( $event );
		
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
	
	/**
	 * Create an item with info about this placeholder, if the event is (in fact) a placeholder event.
	 * A placeholder event is one whose key cannot be found in the set of known events
	 * @param	Event	$event			The event
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	public static function create_event_placeholder_info_item( $event ) {
		if ( $event->get_is_placeholder_event() ) {

			$title_text = __( 'Event not found' , 'reg-man-rc' );
			$icon = 'no';
			$icon_title = __( 'Placeholder event information', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-placeholder-event-info-item';

			// There are three possible reasons the event cannot be found:
			// - the event provider does not exist
			// - the event descriptor cannot be found
			// - the event date is not valid
			
			$event_key = $event->get_key_object();
			$provider_id = $event_key->get_provider_id();
			$descriptor_id = $event_key->get_event_descriptor_id();
			$event_provider = Event_Provider_Factory::get_event_provider_by_id( $provider_id );
			$event_descriptor = $event->get_event_descriptor();

			$addn_content_lines = array();
			
			if ( empty( $event_provider ) ) {
				
				/* Translators: %1$s is an event provider ID */
				$format = __( 'The event provider ID is not valid: %1$s' , 'reg-man-rc' );
				$addn_content_lines[] = sprintf( $format, $provider_id );
				$addn_content_lines[] .= __( 'This may happen if an event provider has been deleted', 'reg-man-rc' );
				
			} elseif( $event_descriptor instanceof Placeholder_Event_Descriptor ) {
				
				if ( $provider_id == Internal_Event_Descriptor::EVENT_PROVIDER_ID ) {
					/* Translators: %1$s is an event descriptor ID */
					$format = __( 'The event ID is not valid: %1$s' , 'reg-man-rc' );
					$addn_content_lines[] = sprintf( $format, $descriptor_id );
					
				} else {
					
					$provider_name = $event_provider->get_event_provider_name();
					/* Translators: %1$s is an event descriptor ID, %2$s is an event provider name */
					$format = __( 'The event ID (%1$s) is not valid for the event provider: %2$s' , 'reg-man-rc' );
					$addn_content_lines[] = sprintf( $format, $descriptor_id, $provider_name );
					
				} // endif
				
				$addn_content_lines[] .= __( 'This may happen if an event has been trashed or permanently deleted', 'reg-man-rc' );
				
			} else {

				$reg_date_display_string = $event->get_start_date_string_in_display_format();
				$date_format = get_option( 'date_format' );
				
				$is_repeating = $event_descriptor->get_event_is_recurring();
				
				if ( $is_repeating ) {
					
					/* Translators: %1$s is a registration event date */
					$format = __( 'The date used to register items or volunteers (%1$s) does not match any date for this repeating event' , 'reg-man-rc' );
					$addn_content_lines[] = sprintf( $format, $reg_date_display_string );
					
				} else {
					
					$real_date = $event_descriptor->get_event_start_date_time();
					$real_date_display_string = isset( $real_date ) ? $real_date->format( $date_format ) : __( '[Missing event date]', 'reg-man-rc' );
					/* Translators: %1$s is a registration event date, %2$s is the real event date */
					$format = __( 'The date used to register items or volunteers (%1$s) does not match the correct event date: %2$s' , 'reg-man-rc' );
					$addn_content_lines[] = sprintf( $format, $reg_date_display_string, $real_date_display_string );
					
				} // endif

				$addn_content_lines[] .= __( 'This may happen if items or volunteers were registered to an event whose date or recurrence rule later changed', 'reg-man-rc' );
				$addn_content_lines[] .= __( 'Consider whether volunteers need to be notified of an event date change and registrations moved to a valid event', 'reg-man-rc' );
				
			} // endif

			$addn_content = '<ul>';
			$addn_content_format = '<li>%1$s</li>';
			foreach( $addn_content_lines as $line ) {
				$addn_content .= sprintf( $addn_content_format, $line );
			} // endfor
			$addn_content .= '</ul>';
			
//			$addn_content = implode( '<br/>', $addn_content_lines );
			$result = List_Item::create( $title_text, $icon, $icon_title, $classes, $addn_content );
			
		} else {
			
			$result = NULL;
			
		} // endif
		
		return $result;
		
	} // function
	
} // class