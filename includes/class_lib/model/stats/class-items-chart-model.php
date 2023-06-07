<?php

namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;

class Items_Chart_Model implements Chart_Model {
	
	const CHART_TYPE_ITEMS_BY_FIXER_STATION		= 'items-by-station';
	const CHART_TYPE_ITEMS_BY_ITEM_TYPE			= 'items-by-type';
	
	private $event_keys_array;
	private $chart_type;
	private $item_stats_col;
	
	private function __construct() {}
	
	/**
	 * Create an instance of this class showing repair stats for the specified events in a detailed bar chart
	 *
	 * @param	string[]	$event_keys_array	The collection of keys for events whose stats are to be shown in the chart
	 * @param	string		$chart_type			The type of chart, one of the CHAT_TYPE_* constants in this class
	 * @return	Items_Chart_Model
	 * @since	v0.4.0
	 */
	public static function create_bar_chart( $event_keys_array, $chart_type ) {
		$result = new self();
		$result->event_keys_array = $event_keys_array;
		$result->chart_type = $chart_type;
		return $result;
	} // function
	
	/**
	 * Get the array of event keys for this chart
	 * @return string[]
	 */
	private function get_event_keys_array() {
		return $this->event_keys_array;
	} // function
	
	/**
	 * Get the marker for the type of chart
	 * @return string
	 */
	private function get_chart_type() {
		return $this->chart_type;
	} // function
	
	/**
	 * Get the item stats collection object for this chart
	 * @return Item_Stats_Collection
	 */
	private function get_item_stats_collection() {
		if ( ! isset( $this->item_stats_col ) ) {
			
			switch( $this->get_chart_type() ) {
				
				case self::CHART_TYPE_ITEMS_BY_ITEM_TYPE:
					$group_by = Item_Stats_Collection::GROUP_BY_ITEM_TYPE;
					break;
				
				case self::CHART_TYPE_ITEMS_BY_FIXER_STATION:
				default:
					$group_by = Item_Stats_Collection::GROUP_BY_FIXER_STATION;
					break;
			} // endswitch

			$event_keys_array = $this->get_event_keys_array();
			$this->item_stats_col = Item_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );

		} // function
		return $this->item_stats_col;
	} // funciton
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {

		$chart_type = $this->get_chart_type();

		switch( $chart_type ) {

			case self::CHART_TYPE_ITEMS_BY_ITEM_TYPE:
				$result = $this->get_items_by_type_chart_config();
				break;
			
			default:
			case self::CHART_TYPE_ITEMS_BY_FIXER_STATION:
				$result = $this->get_items_by_fixer_station_chart_config();
				break;

		} // endswitch
		return $result;
	} // function
	
	/**
	 * Return a chart showing items organized by item_type
	 * @return	Chart_Config			The chart requested
	 */
	private function get_items_by_type_chart_config() {

		$stats_collection = $this->get_item_stats_collection();
		
		$chart_config = Chart_Config::create_bar_chart();
		$label = __( 'Item Types', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$event_count = $stats_collection->get_event_count();
		$all_stats_array = $stats_collection->get_all_stats_array();

		$total_item_count = 0; // I'll need to have a running count of everything
		$no_type_name = __( 'No type specified', 'reg-man-rc' );
		foreach ( $all_stats_array as $type_id => $stats ) {
			if ( empty( $type_id ) ) {
				$type_name = $no_type_name;
				$colour = Chart_Model::DEFAULT_COLOUR;
			} else {
				$item_type = Item_Type::get_item_type_by_id( $type_id );
				if ( isset( $item_type ) ) {
					$type_name = $item_type->get_name();
					$colour = $item_type->get_colour();
				} else {
					$type_name = $no_type_name;
					$colour = Chart_Model::DEFAULT_COLOUR;
				} // endif
			} // endif
//		Error_Log::var_dump( $type_id, $type_name, $stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $type_name ) );
			$count = $stats->get_item_count();
			$total_item_count += $count;
			// It wouldn't make sense if there are items but no events.  But check and show count in the worst case
			$avg = ( $event_count !== 0 ) ? round( $count / $event_count, 2 ) : $count;
			$dataset->add_datapoint( $colour, $avg );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_count_text, $event_count_text );
		$chart_config->set_title( $title );

		return $chart_config;

	} // function

	/**
	 * Return a chart showing items organized by fixer station
	 * @return	Chart_Config			The chart requested
	 */
	private function get_items_by_fixer_station_chart_config() {

		$stats_collection = $this->get_item_stats_collection();
		
		$chart_config = Chart_Config::create_bar_chart();
		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

//		Error_Log::var_dump( $statistics );
		$event_count = $stats_collection->get_event_count();
		$all_stats_array = $stats_collection->get_all_stats_array();

		// FIXME - pass the event filter, get stats for all items and just use that so it's consistent everywhere
		// Or maybe keep a running count for averages and display a total for orphaned items
		$total_item_count = 0; // I'll need to have a running count of everything
		$no_station_name = __( 'No fixer station specified', 'reg-man-rc' );
		
		foreach ( $all_stats_array as $station_id => $stats ) {
			if ( empty( $station_id ) ) {
				$station_name = $no_station_name;
				$colour = Chart_Model::DEFAULT_COLOUR;
			} else {
				$fixer_station = Fixer_Station::get_fixer_station_by_id( $station_id );
				if ( isset( $fixer_station ) ) {
					$station_name = $fixer_station->get_name();
					$colour = $fixer_station->get_colour();
				} else {
					$station_name = $no_station_name;
					$colour = Chart_Model::DEFAULT_COLOUR;
				} // endif
			} // endif
//		Error_Log::var_dump( $station_id, $station_name, $stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$count = $stats->get_item_count();
			$total_item_count += $count;
			// It wouldn't make sense if there are items but no events.  But check and show count in the worst case
			$avg = ( $event_count !== 0 ) ? round( $count / $event_count, 2 ) : $count;
			$dataset->add_datapoint( $colour, $avg );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_count_text, $event_count_text );
		$chart_config->set_title( $title );

		return $chart_config;

	} // function
	
} // class
