<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Stats\Item_Stats;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;

/**
 * The administrative view for an item stats table
 *
 * @since	v0.1.0
 *
 */
class Items_Fixed_Admin_Table_View {

	private $single_event; // The event object when showing data for a single event
	private $group_by;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param	Event	$single_event
	 * @return Items_Fixed_Admin_Table_View
	 */
	public static function create( $single_event = NULL ) {
		$result = new self();
		if ( ! empty( $single_event ) && $single_event instanceof Event ) {
			$result->single_event = $single_event;
		} // endif
		$result->group_by = Item_Stats_Collection::GROUP_BY_FIXER_STATION;
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
	
	private function get_print_page_title() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Repairs - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Repairs', 'reg-man-rc' );
		} // endif
		return $result;
	} // function

	private function get_export_file_name() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Repairs - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Repairs', 'reg-man-rc' );
		} // endif
		$result = sanitize_file_name( $result );
		return $result;
	} // function

	public function render() {
		$heading_format = '<th class="%1$s" title="%3$s">%2$s</th>';

		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$ajax_nonce = wp_create_nonce( $ajax_action );
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_ITEMS_FIXED;
		$group_by = $this->group_by;
		$dom_setting = '';
		$single_event = $this->get_single_event();
		$print_page_title = $this->get_print_page_title();
		$export_file_name = $this->get_export_file_name();
		
		$title = __( 'Repairs', 'reg-man-rc' );

		$group_by_title = __( 'Group by', 'reg-man-rc' );
		$conf_level = Settings::get_confidence_level_for_interval_estimate();
		echo '<div class="reg-man-rc-stats-table-view items-fixed-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
				echo '<div class="toolbar-container">';
					echo "<label><span>$group_by_title</span>";
						echo '<select class="group-by-select" autocomplete="off">';

							$val = Item_Stats_Collection::GROUP_BY_ITEM_DESC;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Item Description', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Stats_Collection::GROUP_BY_FIXER_STATION;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Fixer Station', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Stats_Collection::GROUP_BY_ITEM_TYPE;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Item Type', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Stats_Collection::GROUP_BY_STATION_AND_TYPE;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Fixer Station & Item Type', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Stats_Collection::GROUP_BY_TOTAL;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Total', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

						echo '</select>';
					echo '</label>';
				echo '</div>';
			echo '</div>';

			/* translators: %s is the level of confidence in an interval (or range) like "7-10". That is, the actual number lies within this range with 95% confidence */
			$range_title = sprintf( __( 'Diverted Confidence Interval (%s%%)', 'reg-man-rc' ), $conf_level );
			/* translators: %s is the level of confidence in an interval (or range) like "7-10". That is, the actual number lies within this range with 95% confidence */
			$range_desc = sprintf(
					__( 'We can say with %s%% confidence that the true number of items diverted from landfill lies within this range', 'reg-man-rc' ),
					$conf_level );

			echo '<div class="datatable-container admin-stats-table-container">';
			
				$data_array = array();
				$data_array[] = "data-ajax-url=\"$ajax_url\"";
				$data_array[] = "data-ajax-action=\"$ajax_action\"";
				$data_array[] = "data-ajax-nonce=\"$ajax_nonce\"";
				$data_array[] = "data-table-type=\"$object_type\"";
				$data_array[] = "data-group-by=\"$group_by\"";
				if ( ! empty( $single_event ) ) {
					$single_event_key = $this->get_single_event_key();
					$data_array[] = "data-event-key=\"$single_event_key\"";
					// Add Supplemental button if user is authorized to register items
					$event = $this->get_single_event();
					if ( $event->get_is_current_user_able_to_register_items() ) {
						$data_array[] = 'data-supplemental-data-button-class="supplemental-items-button"';
					} // endif
				} // endif
				$data_array[] = "data-print-page-title=\"$print_page_title\"";
				$data_array[] = "data-export-file-name=\"$export_file_name\"";
				$data_array[] = "data-scope=\"\"";
				$data_array[] = "data-dom-setting=\"$dom_setting\"";
				$data = implode( ' ', $data_array );
				
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo "<table class=\"datatable admin-stats-table items-fixed-admin-table\" style=\"width:100%\" $data>";
/*
				echo '<table class="datatable admin-stats-table items-fixed-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"$group_by\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
*/
					echo '<thead>';

						printf( $heading_format, 'desc',	esc_html__( 'Item Description', 'reg-man-rc' ), '' );
						printf( $heading_format, 'station',	esc_html__( 'Fixer Station', 'reg-man-rc' ), '' );
						printf( $heading_format, 'type',	esc_html__( 'Item Type', 'reg-man-rc' ), '' );
						printf( $heading_format, 'item-count text-align-right',
								esc_html__( 'Total Items', 'reg-man-rc' ), '' );
						printf( $heading_format, 'fixed-count text-align-right',
								esc_html__( 'Fixed', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as fixed', 'reg-man-rc' ) );
						printf( $heading_format, 'partial-fix-count text-align-right',
								esc_html__( 'Repairable', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'eol-count text-align-right',
								esc_html__( 'End of Life', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as not repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'sampled_percent text-align-right col-hidden',
								esc_html__( '% Repair Outcome Reported', 'reg-man-rc' ),
								esc_attr__( 'Percentage of items whose repair outcome was reported', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-percent text-align-right num-with-empty-placeholder',
								esc_html__( 'Est. % Diverted From Landfill', 'reg-man-rc' ),
								esc_attr__( 'Estimated percentage of items diverted from landfill based on items reported as fixed or repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-count text-align-right num-with-empty-placeholder',
								esc_html__( 'Est. # Diverted', 'reg-man-rc' ),
								esc_attr__( 'Estimated count of items diverted from landfill based on items reported as fixed or repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-range col-hidden text-align-center col-not-sortable',
								esc_html( $range_title ), esc_attr__( $range_desc ) );

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
	 * @param	Item_Stats[]	$item_stats_array	An array of Item_Stats objects whose data are
	 * to be shown in the table.
	 * @param	string				$group_by	A string describing how the stats are grouped
	 * to be shown in the table.
	 * @result	string[][]	An array of string arrays containing the table data for the specified stats.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_item_stats( $item_stats_array, $group_by ) {
		$result = array();
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$no_name = __( '[Unspecified]', 'reg-man-rc' );
		foreach( $item_stats_array as $item_stats ) {
			$row = array();
			// Each row is an array of columns.
			// The first few columns are assigned differently based on how we are grouping
			switch( $group_by ) {

				case Item_Stats_Collection::GROUP_BY_ITEM_DESC:
					$group_name = $item_stats->get_group_name();
					$row[] = ! empty( $group_name ) ? $group_name : $no_name; // Item Desc
					$row[] = $em_dash; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

				case Item_Stats_Collection::GROUP_BY_FIXER_STATION:
					$station_id = $item_stats->get_group_name();
					$station = Fixer_Station::get_fixer_station_by_id( $station_id );
					$row[] = $em_dash; // Item Desc
					$row[] = isset( $station ) ? $station->get_name() : $no_name; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

				case Item_Stats_Collection::GROUP_BY_ITEM_TYPE:
					$type_id = $item_stats->get_group_name();
					$item_type = Item_Type::get_item_type_by_id( $type_id );
					$row[] = $em_dash; // Item Desc
					$row[] = $em_dash; // Fixer Station
					$row[] = isset( $item_type ) ? $item_type->get_name() : $no_name; // Item Type
					break;

				case Item_Stats_Collection::GROUP_BY_STATION_AND_TYPE:
					$name = $item_stats->get_group_name(); // e.g. '21|123' -- station|type
					$parts = explode( '|', $name );
					$station_id =	isset( $parts[ 0 ] ) ? $parts[ 0 ] : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
					$type_id =		isset( $parts[ 1 ] ) ? $parts[ 1 ] : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
					$station = Fixer_Station::get_fixer_station_by_id( $station_id );
					$item_type = Item_Type::get_item_type_by_id( $type_id );
					$row[] = $em_dash; // Item Desc
					$row[] = isset( $station ) ? $station->get_name() : $no_name; // Fixer Station
					$row[] = isset( $item_type ) ? $item_type->get_name() : $no_name; // Item Type
					break;

				default:
					$row[] = __( 'Total', 'reg-man-rc' ); // Item Desc
					$row[] = $em_dash; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

			} // endswitch
			$row[] = number_format_i18n( $item_stats->get_item_count() );
			$row[] = number_format_i18n( $item_stats->get_fixed_count() );
			$row[] = number_format_i18n( $item_stats->get_repairable_count() );
			$row[] = number_format_i18n( $item_stats->get_end_of_life_count() );
			$row[] = $item_stats->get_sample_percent_as_string();
			$row[] = $item_stats->get_estimated_diversion_rate_as_percent_string();
			$row[] = $item_stats->get_estimated_diversion_count_as_string();
			$row[] = $item_stats->get_estimated_diversion_count_range_as_string();
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class