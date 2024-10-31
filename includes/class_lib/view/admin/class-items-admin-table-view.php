<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Stats\Item_Descriptor;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\Admin\Item_Import_Admin_Controller;

/**
 * The administrative view for a table of items.
 *
 * @since	v0.1.0
 *
 */
class Items_Admin_Table_View {

	private $single_event; // The event object when showing data for a single event
	
	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param	Event	$single_event
	 * @return Items_Admin_Table_View
	 */
	public static function create( $single_event = NULL ) {
		$result = new self();
		if ( ! empty( $single_event ) && $single_event instanceof Event ) {
			$result->single_event = $single_event;
		} // endif
		return $result;
	} // function
	
	/**
	 * Get the Event object when this table is showing data for a single event
	 * @return Event
	 */
	private function get_single_event() {
		return $this->single_event;
	} // function
	
	private function get_single_event_key() {
		$single_event = $this->get_single_event();
		return ! empty( $single_event ) ? $single_event->get_key_string() : '';
	} // function
	
	private function get_is_event_column_hidden() {
		return ! empty( $this->get_single_event() );
	} // function
	
	private function get_print_page_title() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Items - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Items', 'reg-man-rc' );
		} // endif
		return $result;
	} // function

	private function get_export_file_name() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Items - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Items', 'reg-man-rc' );
		} // endif
		$result = sanitize_file_name( $result );
		return $result;
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		$single_event = $this->get_single_event();
		$event_col_class = $this->get_is_event_column_hidden() ? 'col-hidden' : '';
		// Desc | Event | ISO 8601 Event Date | Visitor Name | Email | Is First Time? | Is Join Mail List? | Fixer Station | Item Type | Status (name) | Status ID | Source
		$rowFormat =
			'<tr>' .
				'<%1$s class="item-desc always-export">%2$s</%1$s>' .
				'<%1$s class="event-date-text ' . $event_col_class . '">%3$s</%1$s>' . // This column will be sorted by the next col's data
				'<%1$s class="event-date-iso-8601 col-hidden always-hidden col-not-searchable">%4$s</%1$s>' . // Must be after date
				'<%1$s class="visitor-name always-export">%5$s</%1$s>' .
				'<%1$s class="visitor-email col-hidden always-export">%6$s</%1$s>' .
				'<%1$s class="visitor-is-first-event col-hidden always-export">%7$s</%1$s>' .
				'<%1$s class="visitor-is-join-mail-list col-hidden always-export">%8$s</%1$s>' .
				'<%1$s class="fixer-station always-export">%9$s</%1$s>' .
				'<%1$s class="type always-export">%10$s</%1$s>' .
				'<%1$s class="item-status-name">%11$s</%1$s>' .
				'<%1$s class="item-status-id col-hidden always-export">%12$s</%1$s>' .
				'<%1$s class="source">%13$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$ajax_nonce = wp_create_nonce( $ajax_action );
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_ITEMS;
		$dom_setting = '';
		$group_by = '';
		$print_page_title = $this->get_print_page_title();
		$export_file_name = $this->get_export_file_name();
		
		$title = __( 'Items', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view items-admin-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';

				$data_array = array();
				$data_array[] = "data-ajax-url=\"$ajax_url\"";
				$data_array[] = "data-ajax-nonce=\"$ajax_nonce\"";
				$data_array[] = "data-ajax-action=\"$ajax_action\"";
				$data_array[] = "data-table-type=\"$object_type\"";
				$data_array[] = "data-group-by=\"$group_by\"";
				if ( ! empty( $single_event ) && Item_Import_Admin_Controller::current_user_can_import_items() ) {
					$single_event_key = $this->get_single_event_key();
					$data_array[] = "data-event-key=\"$single_event_key\"";
					$data_array[] = 'data-supplemental-data-button-class="supplemental-items-button"';
					$data_array[] = 'data-import-data-button-class="import-items-button"';
//					$data_array[] = 'data-add-record-button-class="add-item-button"';
//					$data_array[] = 'data-update-record-button-class="update-item-button"';
//					$data_array[] = 'data-delete-record-button-class="delete-item-button"';
				} // endif
				$data_array[] = "data-print-page-title=\"$print_page_title\"";
				$data_array[] = "data-export-file-name=\"$export_file_name\"";
				$data_array[] = "data-scope=\"\"";
				$data_array[] = "data-dom-setting=\"$dom_setting\"";
				$data = implode( ' ', $data_array );
				
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo "<table class=\"datatable admin-stats-table items-admin-table\" style=\"width:100%\" $data>";
				echo '<thead>';

		// Desc | Event | ISO 8601 Event Date | Visitor Name | Email | Is First Time? | Is Join Mail List? | Fixer Station | Item Type | Status (name) | Status ID | Source
				printf( $rowFormat,
								'th',
								esc_html__( 'Description',					'reg-man-rc' ),
								esc_html__( 'Event',						'reg-man-rc' ),
								esc_html__( 'Numeric Event Date & Time',	'reg-man-rc' ), // Will be hidden
								esc_html__( 'Visitor',						'reg-man-rc' ),
								esc_html__( 'Email',						'reg-man-rc' ),
								esc_html__( 'First Time?',					'reg-man-rc' ),
								esc_html__( 'Join Mail List?',				'reg-man-rc' ),
								esc_html__( 'Fixer Station',				'reg-man-rc' ),
								esc_html__( 'Item Type',					'reg-man-rc' ),
								esc_html__( 'Status / Outcome',				'reg-man-rc' ),
								esc_html__( 'Status ID',					'reg-man-rc' ),
								esc_html__( 'Source',						'reg-man-rc' ),
					);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Get the table data array for the specified array of Item_Descriptor objects
	 *
	 * @param	Item_Descriptor[]	$item_desc_array	An array of item descriptors to be shown in the table
	 * @result	string[][]	An array of string arrays containing the table data for the specified item.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_item_descriptors( $item_desc_array ) {
		$result = array();
//		Error_Log::var_dump( $items_array );

		/* Translators: %1$s is a key for an event that is not found in the system */
		$missing_event_format = __( '[ Event not found: %1$s ]', 'reg-man-rc' );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
//		$local_provider = __( 'internal', 'reg-man-rc' );
		$yes = __( 'Yes', 'reg-man-rc' );
		$no = '';
		foreach( $item_desc_array as $item_desc ) {
			$desc = $item_desc->get_item_description();
			$desc_text = ! empty( $desc ) ? $desc : $em_dash;
			$event = $item_desc->get_event();
			$event_label = isset( $event ) ? $event->get_label() : sprintf( $missing_event_format, $item_desc->get_event_key_string() );
			$event_date = isset( $event ) ? $event->get_start_date_time_object() : NULL;
			$event_date_iso_8601 = isset( $event_date ) ? $event_date->format( \DateTime::ISO8601 ) : $item_desc->get_event_key_string();  // For sorting
			$visitor_name = $item_desc->get_visitor_display_name();
			$visitor_email = $item_desc->get_visitor_email();
			$is_first_time = $item_desc->get_visitor_is_first_time();
			$is_join_mail_list = $item_desc->get_visitor_is_join_mail_list();
			$station = $item_desc->get_fixer_station_name();
			$type = $item_desc->get_item_type_name();
			$item_status = $item_desc->get_item_status();

		// Desc | Event | ISO 8601 Event Date | Visitor Name | Email | Is First Time? | Is Join Mail List? | Fixer Station | Item Type | Status (name) | Status ID | Source
			$row = array();
			$row[] = $desc_text;
			$row[] = $event_label;
			$row[] = $event_date_iso_8601;
			$row[] = ! empty( $visitor_name ) ? $visitor_name : $em_dash;
			$row[] = ! empty( $visitor_email ) ? $visitor_email : $em_dash;
			$row[] = $is_first_time ? $yes : $no;
			$row[] = $is_join_mail_list ? $yes : $no;
			$row[] = ! empty( $station ) ? $station : $em_dash;
			$row[] = ! empty( $type ) ? $type : $em_dash;
			$row[] = isset( $item_status ) ? $item_status->get_name() : $em_dash;
			$row[] = isset( $item_status ) ? $item_status->get_id() : NULL;
			$row[] = $item_desc->get_item_descriptor_source();
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class