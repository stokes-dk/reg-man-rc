<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item_Group_Stats;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
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
		$rowFormat =
			'<tr>' .
				'<%1$s class="name">%2$s</%1$s>' .
				'<%1$s class="date-and-time">%3$s</%1$s>' .
				'<%1$s class="status">%4$s</%1$s>' .
				'<%1$s class="event-class">%5$s</%1$s>' .
				'<%1$s class="category">%6$s</%1$s>' .
				'<%1$s class="items">%7$s</%1$s>' .
				'<%1$s class="provider">%8$s</%1$s>' .
				'<%1$s class="event_id">%9$s</%1$s>' .
				'<%1$s class="iso-8601-date">%10$s</%1$s>' .
				'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_EVENTS;
		$dom_setting = '';
		$group_by = '';

		$title = __( 'Events', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view events-admin-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table events-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"$group_by\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
				echo '<thead>';
					printf( $rowFormat,
								'th',
								esc_html__( 'Summary',					'reg-man-rc' ),
								esc_html__( 'Date & Time',				'reg-man-rc' ),
								esc_html__( 'Status',					'reg-man-rc' ),
								esc_html__( 'Class',					'reg-man-rc' ),
								esc_html__( 'Categories',				'reg-man-rc' ),
								esc_html__( 'Items',					'reg-man-rc' ),
								esc_html__( 'Event Source',				'reg-man-rc' ),
								esc_html__( 'ID',						'reg-man-rc' ),
								'' // The ISO 8601 date will be hidden and does not need a column header
							);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Get the table data array for the specified array of Item_Group_Stats objects
	 *
	 * @param	Event[]	$event_array	An array of Event objects whose data are to be shown in the table.
	 * @param	Item_Group_Stats[]	$fixed_stats_array	An array of fixed data for each event (grouped by Event)
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
		// Name | Status | Date/Time | Location | Categories | Event Source
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
//		$local_provider = __( 'registration manager', 'reg-man-rc' );
		foreach( $event_array as $event ) {
			$key = $event->get_key();
			$fixed_stats = isset( $keyed_stats[ $key ] ) ? $keyed_stats[ $key ] : NULL;
			$status = $event->get_status();
			$class = $event->get_class();
			$provider = $event->get_provider_id();
//			$provider_text = ( $provider == Internal_Event_Descriptor::EVENT_PROVIDER_ID ) ? $local_provider : $provider;
			$provider_text = $event->get_provider_name();
			$event_id = $event->get_event_descriptor_id();
			$start_date = $event->get_start_date_time_local_timezone_object();
			$end_date = $event->get_end_date_time_local_timezone_object();
			$date_text = Event::create_label_for_event_dates_and_times( $start_date, $end_date );
			$event_date_iso_8601 = isset( $start_date ) ? $start_date->format( \DateTime::ISO8601 ) : '';
			$row = array();
			$row[] = $event->get_summary();
			$row[] = $date_text;
			$row[] = isset( $status ) ? $status->get_name() : $em_dash;
			$row[] = isset( $class ) ? $class->get_name() : $em_dash;
			$row[] = $event->get_categories();
			$row[] = isset( $fixed_stats ) ? $fixed_stats->get_item_count() : $em_dash;
			$row[] = $provider_text;
			$row[] = $event_id;
			$row[] = $event_date_iso_8601;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class