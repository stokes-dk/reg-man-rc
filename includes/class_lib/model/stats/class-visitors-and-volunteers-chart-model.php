<?php

namespace Reg_Man_RC\Model\Stats;


use Reg_Man_RC\Model\Events_Collection;

class Visitors_And_Volunteers_Chart_Model implements Chart_Model {
	
	const CHART_TYPE = 'visitors-and-volunteers';
	
	private $events_collection;
	
	private function __construct() {}
	
	/**
	 * Create an instance of this class showing visitor stats for the specified events in a bar chart
	 *
	 * @param	Events_Collection	$events_collection	The collection of events whose stats are to be shown in the chart
	 * @return	Items_Chart_Model
	 * @since	v0.4.0
	 */
	public static function create_bar_chart_for_events_collection( $events_collection ) {
		$result = new self();
		$result->events_collection = $events_collection;
		return $result;
	} // function
	
	/**
	 * Get the collection of events for this chart
	 * @return Events_Collection
	 */
	private function get_events_collection() {
		return $this->events_collection;
	} // function
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {
		$result = $this->get_people_summary_chart_config();
		return $result;
	} // function
	
	/**
	 * Return a chart showing a summary of visitors
	 * @return	Chart_Config		The chart requested
	 */
	private function get_people_summary_chart_config() {

		$chart_config = Chart_Config::create_bar_chart();
		
		$events_collection = $this->get_events_collection();

		$event_count = $events_collection->get_event_count();
		
		// Get visitor stats
		$visitor_total_stats = Visitor_Stats::get_total_visitor_stats_for_events_collection( $events_collection );
		$visitor_count = $visitor_total_stats->get_visitor_count();

		// Get fixer stats
		$fixer_total_stats = Volunteer_Stats::get_total_fixer_stats_for_events_collection( $events_collection );
		$fixer_count = $fixer_total_stats->get_head_count();

		// Get non-fixer stats
		$non_fixer_total_stats = Volunteer_Stats::get_total_non_fixer_stats_for_events_collection( $events_collection );
		$non_fixer_count = $non_fixer_total_stats->get_head_count();

		$avg_visitors = ( $event_count != 0 ) ? round( ( $visitor_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Visitors per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( Chart_Model::VISITOR_COLOUR, $avg_visitors );
		$chart_config->add_dataset( $dataset );

		$avg_fixers = ( $event_count != 0 ) ? round( ( $fixer_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Fixers per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( Chart_Model::FIXER_COLOUR, $avg_fixers );
		$chart_config->add_dataset( $dataset );

		$avg_non_fixers = ( $event_count != 0 ) ? round( ( $non_fixer_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Non-fixer Volunteers per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( Chart_Model::NON_FIXER_COLOUR, $avg_non_fixers );
		$chart_config->add_dataset( $dataset );

		$label = __( 'Registrations', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $fixer_count, 'reg-man-rc' ), number_format_i18n( $fixer_count ) );
		$non_fixer_count_text = sprintf( _n( '%s non-fixer volunteer', '%s non-fixer volunteers', $non_fixer_count, 'reg-man-rc' ), number_format_i18n( $non_fixer_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of fixers, %3$s is a count of non-fixers, %4$s is similar a count of events */
		$title = sprintf( __( '%1$s, %2$s, %3$s, %4$s', 'reg-man-rc' ), $visitor_count_text, $fixer_count_text, $non_fixer_count_text, $event_count_text );
		$chart_config->set_title( $title );

		return $chart_config;

	} // function

} // class
