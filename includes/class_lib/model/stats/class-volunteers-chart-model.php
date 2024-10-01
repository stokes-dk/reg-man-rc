<?php

namespace Reg_Man_RC\Model\Stats;


use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Events_Collection;

class Volunteers_Chart_Model implements Chart_Model {
	
	const CHART_TYPE_FIXERS_PER_EVENT				= 'fixers-per-event';
	const CHART_TYPE_ITEMS_PER_FIXER_BY_STATION		= 'items-per-fixer-by-station';
	const CHART_TYPE_NON_FIXERS_PER_EVENT			= 'non-fixers-per-event';
	const CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE	= 'visitors-per-volunteer';
	
	private $chart_type;
	private $events_collection;
	private $volunteer_stats_col;
	
	private function __construct() {}
	
	/**
	 * Create an instance of this class showing fixer stats for the specified events
	 *
	 * @param	Events_Collection	$events_collection	The collection of  events whose stats are to be shown in the chart
	 * @param	string				$chart_type			The type of chart, one of the CHART_TYPE_* constants in this class
	 * @return	Volunteers_Chart_Model
	 * @since	v0.4.0
	 */
	public static function create_bar_chart_for_events_collection( $events_collection, $chart_type ) {

		$result = new self();
		$result->events_collection = $events_collection;
		$result->chart_type = $chart_type;
		
		switch( $chart_type ) {
			
			case self::CHART_TYPE_NON_FIXERS_PER_EVENT:
			case self::CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE:
				$group_by = Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE;
				break;
				
			default:
			case self::CHART_TYPE_FIXERS_PER_EVENT:
			case self::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION:
				$group_by = Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION;
				break;
				
		} // endswitch

		$result->volunteer_stats_col = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );

		return $result;
		
	} // function
	
	/**
	 * Get the marker for the type of chart
	 * @return string
	 */
	private function get_chart_type() {
		return $this->chart_type;
	} // function
	
	/**
	 * Get the collection of events for this chart
	 * @return Events_Collection
	 */
	private function get_events_collection() {
		return $this->events_collection;
	} // function
	
		/**
	 * Get the array of event keys for this chart
	 * @return string[]
	 */
	private function get_event_keys_array() {
		return $this->event_keys_array;
	} // function
	
/**
	 * Get the volunteer stats collection object for this chart
	 * @return Volunteer_Stats_Collection
	 */
	private function get_volunteer_stats_collection() {
		return $this->volunteer_stats_col;
	} // funciton
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {

		$chart_type = $this->get_chart_type();

		switch( $chart_type ) {

			default:
			case self::CHART_TYPE_FIXERS_PER_EVENT:
				$result = $this->get_fixers_by_station_chart_config();
				break;
			
			case self::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION:
				$result = $this->get_items_per_fixer_by_station_chart_config();
				break;

			case self::CHART_TYPE_NON_FIXERS_PER_EVENT:
				$result = $this->get_non_fixers_by_role_chart_config();
				break;

			case self::CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE:
				$result = $this->get_visitors_per_volunteer_by_role_chart_config();
				break;

		} // endswitch
		return $result;
	} // function
	
	/**
	 * Return a chart showing fixers by fixer_station
	 * @return	Chart_Config		The chart requested
	 */
	private function get_fixers_by_station_chart_config() {

		$vol_stats_col = $this->get_volunteer_stats_collection();
		$event_count = $vol_stats_col->get_event_count();
		$all_stats_array = $vol_stats_col->get_all_stats_array();

		$chart_config = Chart_Config::create_bar_chart();

//		$labels = array();

		$total_count = 0;
//		$no_station_name = __( 'No station specified', 'reg-man-rc' );
		foreach ( $all_stats_array as $station_id => $stats ) {
			$station = Fixer_Station::get_fixer_station_by_id( $station_id );
			if ( ! isset( $station ) ) {
				$station_name = __( 'No Fixer Station', 'reg-man-rc' );
				$colour = Chart_Model::DEFAULT_COLOUR;
			} else {
				$station_name = $station->get_name();
				$colour = $station->get_colour();
			} // endif
			$head_count = $stats->get_head_count();
			$total_count += $head_count;
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$avg = ( $event_count !== 0 ) ? round( $head_count / $event_count, 2 ) : $head_count;
			$dataset->add_datapoint( $colour, $avg );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $total_count, 'reg-man-rc' ), number_format_i18n( $total_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of fixers, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $fixer_count_text, $event_count_text );
		$chart_config->set_title( $title );

		return $chart_config;

	} // function
	
	/**
	 * Return a chart showing non-fixer volunteers by volunteer_role
	 * @return	Chart_Config		The chart requested
	 */
	private function get_non_fixers_by_role_chart_config() {

		$vol_stats_col = $this->get_volunteer_stats_collection();
		$event_count = $vol_stats_col->get_event_count();
		$all_stats_array = $vol_stats_col->get_all_stats_array();
		
		$chart_config = Chart_Config::create_bar_chart();
		$label = __( 'Volunteer Roles', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$total_head_count = 0; // I'll need to have a running count of everything
		$no_role_name = __( 'No role assigned', 'reg-man-rc' );
		foreach ( $all_stats_array as $role_id => $stats ) {
			$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
			if ( ! isset( $role ) ) {
//				Error_Log::var_dump( $role_id );
				$role_name = $no_role_name;
				$colour = Chart_Model::NO_VOLUNTEER_ROLE_COLOUR;
			} else {
				$role_name = $role->get_name();
				$colour = $role->get_colour();
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $role_name ), 'reg-man-rc' );
			$head_count = $stats->get_head_count();
			$total_head_count += $head_count;
			$avg = ( $event_count !== 0 ) ? round( $head_count / $event_count, 2 ) : $head_count;
			$dataset->add_datapoint( $colour, $avg );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$reg_count_text = sprintf( _n( '%s non-fixer role assigned', '%s non-fixer roles assigned', $total_head_count, 'reg-man-rc' ), number_format_i18n( $total_head_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of non-fixer volunteers, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $reg_count_text, $event_count_text );
		$chart_config->set_title( $title );
		$subtitle = __( 'Note that one volunteer may be assigned multiple roles', 'reg-man-rc' );
		$chart_config->set_subtitle( $subtitle );

		return $chart_config;

	} // function

	/**
	 * Get the chart config for the number of visitors per volunteer organized by role
	 * @return	Chart_Config
	 */
	private function get_visitors_per_volunteer_by_role_chart_config() {
		
		$events_collection = $this->get_events_collection();

		// break up the volunteers by role, note that one volunteer may play multiple roles
		$volunteer_stats_col = $this->get_volunteer_stats_collection();
		$all_stats_array = $volunteer_stats_col->get_all_stats_array();

		$group_by = Visitor_Stats_Collection::GROUP_BY_TOTAL;
		$visitor_stats_col = Visitor_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
		$visitor_stats_array = array_values( $visitor_stats_col->get_all_stats_array() );
		$total_visitor_stats = isset( $visitor_stats_array[ 0 ] ) ? $visitor_stats_array[ 0 ] : NULL;

		$visitor_count = isset( $total_visitor_stats ) ? $total_visitor_stats->get_visitor_count() : 0;

		$chart_config = Chart_Config::create_bar_chart();

		$label = __( 'Volunteer Roles', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$total_role_count = 0; // I'll need to have a running count of everything
		$no_role_name = __( 'No role assigned', 'reg-man-rc' );
		foreach ( $all_stats_array as $role_id => $stats ) {
			$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
			if ( ! isset( $role ) ) {
				$role_name = $no_role_name;
				$colour = Chart_Model::NO_VOLUNTEER_ROLE_COLOUR;
			} else {
				$role_name = $role->get_name();
				$colour = $role->get_colour();
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $role_name ), 'reg-man-rc' );
			$head_count = $stats->get_head_count();
			$total_role_count += $head_count;
			$avg = ( $head_count !== 0 ) ? round( $visitor_count / $head_count, 2 ) : 0;
			$dataset->add_datapoint( $colour, $avg );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$role_count_text = sprintf( _n( '%s non-fixer role assigned', '%s non-fixer roles assigned', $total_role_count, 'reg-man-rc' ), number_format_i18n( $total_role_count ) );
//		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of visitors, %2$s is a count of non-fixer volunteers, %3$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $visitor_count_text, $role_count_text );
		$chart_config->set_title( $title );
		$subtitle = __( 'Note that one volunteer may be assigned multiple roles', 'reg-man-rc' );
		$chart_config->set_subtitle( $subtitle );

		return $chart_config;

	} // function

	private function get_items_per_fixer_by_station_chart_config() {

		$events_collection = $this->get_events_collection();
		
		$chart_config = Chart_Config::create_bar_chart();
		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

//	Error_Log::var_dump( $filter );

		$fixer_stats_col = $this->get_volunteer_stats_collection();
		$total_fixer_stats_array = $fixer_stats_col->get_all_stats_array();
//	Error_Log::var_dump( $total_fixer_stats_array );
		$total_fixer_count = 0; // I need a total number of fixers
		foreach( $total_fixer_stats_array as $station_id => $fixer_group_stats ) {
			$total_fixer_count += $fixer_group_stats->get_head_count();
		} // endfor

		$group_by = Item_Stats_Collection::GROUP_BY_FIXER_STATION;
		$item_stats_collection = Item_Stats_Collection::create_for_events_collection( $events_collection , $group_by );
		$event_count = $item_stats_collection->get_event_count();
		$total_item_stats_array = $item_stats_collection->get_all_stats_array();
//	Error_Log::var_dump( $total_item_stats_array );

		$total_item_count = 0; // I'll need to have a running count of everything
		$no_station_name = __( 'No fixer station specified', 'reg-man-rc' );
		foreach ( $total_item_stats_array as $station_id => $item_stats ) {
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
//		Error_Log::var_dump( $station_id, $item_stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$item_count = $item_stats->get_item_count();
			$total_item_count += $item_count;
			$fixer_group_stats = isset( $total_fixer_stats_array[ $station_id ] ) ? $total_fixer_stats_array[ $station_id ] : NULL;
			$fixer_count = isset( $fixer_group_stats ) ? $fixer_group_stats->get_head_count() : 0;
			//Avoid a divide by zero error if there are no events or no fixers registered for the station
			$items_per_fixer = ( $fixer_count != 0 ) ? round( $item_count / $fixer_count, 2 ) : $item_count;
//		Error_Log::var_dump( $station_name, $item_count, $fixers_count );
			$dataset->add_datapoint( $colour, $items_per_fixer );
			$chart_config->add_dataset( $dataset );
		} // endfor

//		$station_array = Fixer_Station::get_all_fixer_stations();

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $total_fixer_count, 'reg-man-rc' ), number_format_i18n( $total_fixer_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of fixers, %3$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s, %3$s', 'reg-man-rc' ), $item_count_text, $fixer_count_text, $event_count_text );
		$chart_config->set_title( $title );

		return $chart_config;

	} // function

	
	
} // class
