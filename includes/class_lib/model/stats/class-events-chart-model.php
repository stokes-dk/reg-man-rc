<?php

namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Events_Collection;

class Events_Chart_Model implements Chart_Model {

	const CHART_TYPE = 'events';
	
	private $event_stats_collection;

	private function __construct() {}
	
	/**
	 * Create an instance of this class showing the specified events in a bar chart
	 *
	 * @param	Events_Collection	$events_collection		The collection of events to be shown in the chart
	 * @return	Events_Chart_Model
	 * @since	v0.1.0
	 */
	public static function create_bar_chart_for_events_collection( $events_collection ) {
		$result = new self();
		$group_by = Event_Stats_Collection::GROUP_BY_EVENT_CATEGORY;
		$result->event_stats_collection = Event_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
		return $result;
	} // function
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {
		$result = $this->get_bar_chart_config(); // We only support bar chart for now
		return $result;
	} // function
	
	/**
	 * Get the event stats collection
	 * @return Event_Stats_Collection
	 */
	private function get_event_stats_collection() {
		return $this->event_stats_collection;
	} // function
	
	/**
	 * Gets a chart config object for a bar chart of events organized by event category
	 *
	 * @return	Chart_Config
	 * @since	v0.1.0
	 */
	private function get_bar_chart_config() {

		$event_stats_col = $this->get_event_stats_collection();
		$category_count_array = $event_stats_col->get_event_counts_array();
//		Error_Log::var_dump( $event_stats_col, $category_count_array );

		$chart_config = Chart_Config::create_bar_chart();
		foreach ( $category_count_array as $cat_id => $count ) {
			if ( $cat_id == Event_Stats_Collection::CATEGORY_NOT_SPECIFIED ) {
				$cat_name = __( 'Uncategorized', 'reg-man-rc' );
				$colour = self::DEFAULT_COLOUR;
			} else {
				$category = Event_Category::get_event_category_by_id( $cat_id );
				if ( isset( $category ) ) {
					$cat_name = $category->get_name();
					$colour = $category->get_colour();
				} else {
					$cat_name = __( '[ Category not found ]', 'reg-man-rc' );
					$colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $cat_name ) );
			$dataset->add_datapoint( $colour, $count );
			$chart_config->add_dataset( $dataset );
		} // endfor

		$label = __( 'Event Categories', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$event_count = $event_stats_col->get_total_event_count();
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		$chart_config->set_title( $event_count_text );

		return $chart_config;

	} // function

	
} // class
