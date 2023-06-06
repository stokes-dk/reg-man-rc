<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Stats\Item_Descriptor;
use Reg_Man_RC\Model\Item_Status;

/**
 * The administrative view for a table of items.
 *
 * @since	v0.1.0
 *
 */
class Items_Admin_Table_View {

	private $is_event_column_hidden = FALSE;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @return Items_Admin_Table_View
	 */
	public static function create( ) {
		$result = new self();
		return $result;
	} // function
	
	private function get_is_event_column_hidden() {
		return $this->is_event_column_hidden;
	} // function
	
	/**
	 * Set a flag to indicate whether the event column should be hidden
	 * @param boolean $is_event_column_hidden
	 */
	public function set_is_event_column_hidden( $is_event_column_hidden ) {
		$this->is_event_column_hidden = boolval( $is_event_column_hidden );
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		$event_col_class = $this->get_is_event_column_hidden() ? 'col-hidden' : '';
		// Desc | Event | ISO 8601 Event Date | Visitor Name | Item Type | Fixer Station | Status | Source 
		$rowFormat =
			'<tr>' .
				'<%1$s class="item-desc">%2$s</%1$s>' .
				'<%1$s class="event-date-text ' . $event_col_class . '">%3$s</%1$s>' . // This column will be sorted by the next col's data
				'<%1$s class="event-date-iso-8601 col-hidden always-hidden not-searchable">%4$s</%1$s>' . // Must be after date
				'<%1$s class="visitor-name">%5$s</%1$s>' .
				'<%1$s class="type">%6$s</%1$s>' .
				'<%1$s class="fixer-station">%7$s</%1$s>' .
				'<%1$s class="repair-status">%8$s</%1$s>' .
				'<%1$s class="source">%9$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_ITEMS;
		$dom_setting = '';
		$group_by = '';

		$title = __( 'Items', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view items-admin-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table items-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"$group_by\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
				echo '<thead>';
		// Desc | Event | Visitor Name | Item Type | Fixer Station | Status | Source | ISO 8601 Event Date
					printf( $rowFormat,
								'th',
								esc_html__( 'Description',					'reg-man-rc' ),
								esc_html__( 'Event',						'reg-man-rc' ),
								esc_html__( 'Numeric Event Date & Time',	'reg-man-rc' ), // Will be hidden
								esc_html__( 'Visitor',						'reg-man-rc' ),
								esc_html__( 'Item Type',					'reg-man-rc' ),
								esc_html__( 'Fixer Station',				'reg-man-rc' ),
								esc_html__( 'Status',						'reg-man-rc' ),
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
		$missing_event_format = __( '[ Missing event: %1$s ]', 'reg-man-rc' );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
//		$local_provider = __( 'internal', 'reg-man-rc' );
		foreach( $item_desc_array as $item_desc ) {
			$desc = $item_desc->get_item_description();
			$desc_text = ! empty( $desc ) ? $desc : $em_dash;
			$event = $item_desc->get_event();
			$event_label = isset( $event ) ? $event->get_label() : sprintf( $missing_event_format, $item_desc->get_event_key() );
			$event_date = isset( $event ) ? $event->get_start_date_time_object() : NULL;
			$event_date_iso_8601 = isset( $event_date ) ? $event_date->format( \DateTime::ISO8601 ) : $item_desc->get_event_key();  // For sorting
			$visitor_name = $item_desc->get_visitor_full_name();
			$type = $item_desc->get_item_type_name();
			$station = $item_desc->get_fixer_station_name();
			$status_name = $item_desc->get_status_name();
// Desc | Event | Visitor Name | Item Type | Fixer Station | Status | Source | ISO 8601 Event Date
			$row = array();
			$row[] = $desc_text;
			$row[] = $event_label;
			$row[] = $event_date_iso_8601;
			$row[] = ! empty( $visitor_name ) ? $visitor_name : $em_dash;
			$row[] = ! empty( $type) ? $type : $em_dash;
			$row[] = ! empty( $station) ? $station : $em_dash;
			$row[] = ( ! empty( $status_name ) && ( $status_name != Item_Status::REGISTERED ) ) ? $item_desc->get_status_name() : $em_dash;
			$row[] = $item_desc->get_item_descriptor_source();
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class