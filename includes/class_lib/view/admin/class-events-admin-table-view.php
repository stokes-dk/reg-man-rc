<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Stats\Item_Stats;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;

/**
 * The administrative view for a table of events.
 * Note that an event descriptor may be for a recurring event.
 * So an instance of Internal_Event_Descriptor will be represented as a single row in the standard Wordpress admin interface
 * for the custom its custom post type but may be represented as multiple rows in this table.
 * Also, events supplied by external event providers will show up in this table.
 * So this interface provides a convenient way to view all events defined to the system.
 *
 * @since	v0.1.0
 *
 */
class Events_Admin_Table_View {

	private function __construct() { }

	public static function create( ) {
		$result = new self();
		return $result;
	} // function

	public function render() {
		// Event summary | Date & Time | ISO8601 Date/Time | Status | Class | Categories | Item Count | Provider | ID
		$rowFormat =
			'<tr>' .
				'<%1$s class="event-summary">%2$s</%1$s>' .
				'<%1$s class="event-date-text">%3$s</%1$s>' . // This column will be sorted by the next col's data
				'<%1$s class="event-date-iso-8601 col-hidden always-hidden not-searchable">%4$s</%1$s>' . // Must be after date
				'<%1$s class="event-status">%5$s</%1$s>' .
				'<%1$s class="event-class">%6$s</%1$s>' .
				'<%1$s class="event-category">%7$s</%1$s>' .
				'<%1$s class="event-items-count num-with-empty-placeholder">%8$s</%1$s>' .
				'<%1$s class="event-provider">%9$s</%1$s>' .
				'<%1$s class="event-id col-hidden">%10$s</%1$s>' .
				'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$ajax_nonce = wp_create_nonce( $ajax_action );
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_EVENTS;
		$dom_setting = '';
		$group_by = '';

		$title = __( 'Events', 'reg-man-rc' );
		$print_page_title = $title;
		$export_file_name = $title;

		echo '<div class="reg-man-rc-stats-table-view events-admin-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table events-admin-table" style="width:100%"' .
					" data-ajax-nonce=\"$ajax_nonce\"" .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"$group_by\" " .
					" data-scope=\"\"" .
					" data-print-page-title=\"$print_page_title\" " .
					" data-export-file-name=\"$export_file_name\" " .
					" data-dom-setting=\"$dom_setting\">";
					echo '<thead>';
						$is_allow_multi_cats = Settings::get_is_allow_event_multiple_categories();
						$cat_label = $is_allow_multi_cats ? esc_html__( 'Categories', 'reg-man-rc' ) : esc_html__( 'Category', 'reg-man-rc' );
						printf( $rowFormat,
								'th',
								esc_html__( 'Summary',						'reg-man-rc' ),
								esc_html__( 'Date & Time',					'reg-man-rc' ),
								esc_html__( 'Numeric Event Date & Time',	'reg-man-rc' ), // will be hidden
								esc_html__( 'Status',						'reg-man-rc' ),
								esc_html__( 'Class',						'reg-man-rc' ),
								$cat_label,
								esc_html__( 'Items',						'reg-man-rc' ),
								esc_html__( 'Source',						'reg-man-rc' ),
								esc_html__( 'ID',							'reg-man-rc' ),
						);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Get the table data array for the specified array of Item_Stats objects
	 *
	 * @param	Event[]	$event_array	An array of Event objects whose data are to be shown in the table.
	 * @param	Item_Stats[]	$fixed_stats_array	An array of fixed data for each event (grouped by Event)
	 * @result	string[][]	An array of string arrays containing the table data for the specified events.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_events( $event_array, $fixed_stats_array ) {
		$result = array();
//		Error_Log::var_dump( $fixed_stats_array );
		$keyed_stats = array(); // I will make an associative array keyed by event key
		foreach ( $fixed_stats_array as $stats ) {
			$key = $stats->get_group_name(); // this is the key
			$keyed_stats[ $key ] = $stats;
		} // endfor
		// Event summary | Date & Time | ISO8601 Date/Time | Status | Class | Categories | Item Count | Provider | ID
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$categories_glue = _x( ', ', 'A separator for the list of category names', 'reg-man-rc' );
		foreach( $event_array as $event ) {
			$key = $event->get_key_string();
			$fixed_stats = isset( $keyed_stats[ $key ] ) ? $keyed_stats[ $key ] : NULL;
			$status = $event->get_status();
			$class = $event->get_class();
			$categories = $event->get_categories();
			$categories_text = is_array( $categories ) ? implode( $categories_glue, $categories ) : $em_dash;
			$provider_text = $event->get_provider_name();
			$event_desc_id = $event->get_event_descriptor_id();
//			$start_date = $event->get_start_date_time_object();
//			$end_date = $event->get_end_date_time_object();
//			$date_text = Event::create_label_for_event_dates_and_times( $start_date, $end_date );
//			Error_Log::var_dump( $event->get_is_placeholder_event() );
//			if ( $event->get_is_placeholder_event() ) $date_text .= ' placeholder';
			$date_text = $event->get_event_dates_and_times_label();
			$start_date = $event->get_start_date_time_object();
			$event_date_iso_8601 = isset( $start_date ) ? $start_date->format( \DateTime::ISO8601 ) : '';
			$row = array();
			$row[] = $event->get_summary();
			$row[] = $date_text;
			$row[] = $event_date_iso_8601;
			$row[] = isset( $status ) ? $status->get_name() : $em_dash;
			$row[] = isset( $class ) ? $class->get_name() : $em_dash;
			$row[] = $categories_text;
			$row[] = isset( $fixed_stats ) ? $fixed_stats->get_item_count() : $em_dash;
			$row[] = $provider_text;
			$row[] = $event_desc_id;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class