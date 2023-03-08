<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item_Group_Stats;
use Reg_Man_RC\Model\Stats\Item_Statistics;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;

/**
 * The administrative view for an item stats table
 *
 * @since	v0.1.0
 *
 */
class Items_Fixed_Admin_Table_View {

	private $group_by;

	private function __construct() { }

	public static function create( $group_by = Item_Statistics::GROUP_BY_FIXER_STATION ) {
		$result = new self();
		$result->group_by = $group_by;
		return $result;
	} // function

	public function render() {
		$heading_format = '<th class="%1$s" title="%3$s">%2$s</th>';

		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_ITEMS_FIXED;
		$group_by = $this->group_by;
		$dom_setting = '';

		$title = __( 'Repairs', 'reg-man-rc' );

		$group_by_title = __( 'Group repairs by', 'reg-man-rc' );
		$conf_level = Settings::get_confidence_level_for_interval_estimate();
		echo '<div class="reg-man-rc-stats-table-view items-fixed-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
				echo '<div class="toolbar-container">';
					echo "<label><span>$group_by_title</span>";
						echo '<select class="group-by-select" autocomplete="off">';

							$val = Item_Statistics::GROUP_BY_ITEM_DESC;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Item Description', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Statistics::GROUP_BY_FIXER_STATION;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Fixer Station', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Statistics::GROUP_BY_ITEM_TYPE;
							$sel = selected( $group_by, $val, FALSE );
							$title = __( 'Item Type', 'reg-man-rc' );
							echo "<option value=\"$val\" $sel>$title</option>";

							$val = Item_Statistics::GROUP_BY_TOTAL;
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
			$range_desc = sprintf( __( 'We can say with %s%% confidence that the true number of items diverted from landfill lies within this range', 'reg-man-rc' ), $conf_level );

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table items-fixed-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"$group_by\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
					echo '<thead>';

						printf( $heading_format, 'name',				esc_html__( 'Desc', 'reg-man-rc' ), '' );
						printf( $heading_format, 'name',				esc_html__( 'Fixer Station', 'reg-man-rc' ), '' );
						printf( $heading_format, 'name',				esc_html__( 'Item Type', 'reg-man-rc' ), '' );
						printf( $heading_format, 'item-count', 			esc_html__( 'Total Items', 'reg-man-rc' ), '' );
						printf( $heading_format, 'fixed-count', 		esc_html__( 'Fixed', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as fixed', 'reg-man-rc' ) );
						printf( $heading_format, 'partial-fix-count', 	esc_html__( 'Repairable', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'eol-count', 			esc_html__( 'End of Life', 'reg-man-rc' ),
								esc_attr__( 'Count of items reported as not repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'sampled_percent', 	esc_html__( '% Repair Status Reported', 'reg-man-rc' ),
								esc_attr__( 'Percentage of items whose repair status was reported', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-percent',	esc_html__( 'Est. % Diverted From Landfill', 'reg-man-rc' ),
								esc_attr__( 'Estimated percentage of items diverted from landfill based on items reported as fixed or repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-count',		esc_html__( 'Est. # Diverted', 'reg-man-rc' ),
								esc_attr__( 'Estimated count of items diverted from landfill based on items reported as fixed or repairable', 'reg-man-rc' ) );
						printf( $heading_format, 'diverted-range', 		esc_html( $range_title ), esc_attr__( $range_desc ) );

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
	 * @param	Item_Group_Stats[]	$item_group_stats_array	An array of Item_Group_Stats objects whose data are
	 * to be shown in the table.
	 * @param	string				$group_by	A string describing how the stats are grouped
	 * to be shown in the table.
	 * @result	string[][]	An array of string arrays containing the table data for the specified stats.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_item_group_stats( $item_group_stats_array, $group_by ) {
		$result = array();
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$no_name = __( '[Unspecified]', 'reg-man-rc' );
		foreach( $item_group_stats_array as $item_group_stats ) {
			$row = array();
			// Each row is an array of columns.
			// The first few columns are assigned differently based on how we are grouping
			switch( $group_by ) {

				case Item_Statistics::GROUP_BY_ITEM_DESC:
					$group_name = $item_group_stats->get_group_name();
					$row[] = ! empty( $group_name ) ? $group_name : $no_name; // Item Desc
					$row[] = $em_dash; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

				case Item_Statistics::GROUP_BY_FIXER_STATION:
					$row[] = $em_dash; // Item Desc
					$station_id = $item_group_stats->get_group_name();
					$station = Fixer_Station::get_fixer_station_by_id( $station_id );
					$row[] = isset( $station ) ? $station->get_name() : $no_name; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

				case Item_Statistics::GROUP_BY_ITEM_TYPE:
					$row[] = $em_dash; // Item Desc
					$row[] = $em_dash; // Fixer Station
					$type_id = $item_group_stats->get_group_name();
					$item_type = Item_Type::get_item_type_by_id( $type_id );
					$row[] = isset( $item_type ) ? $item_type->get_name() : $no_name; // Item Type
					break;

					default:
					$row[] = __( 'Total', 'reg-man-rc' ); // Item Desc
					$row[] = $em_dash; // Fixer Station
					$row[] = $em_dash; // Item Type
					break;

			} // endswitch
			$row[] = number_format_i18n( $item_group_stats->get_item_count() );
			$row[] = number_format_i18n( $item_group_stats->get_fixed_count() );
			$row[] = number_format_i18n( $item_group_stats->get_repairable_count() );
			$row[] = number_format_i18n( $item_group_stats->get_end_of_life_count() );
			$row[] = $item_group_stats->get_sample_percent_as_string();
			$row[] = $item_group_stats->get_estimated_diversion_rate_as_percent_string();
			$row[] = $item_group_stats->get_estimated_diversion_count_as_string();
			$row[] = $item_group_stats->get_estimated_diversion_count_range_as_string();
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class