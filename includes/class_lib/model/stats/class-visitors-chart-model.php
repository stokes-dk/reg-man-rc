<?php

namespace Reg_Man_RC\Model\Stats;


class Visitors_Chart_Model implements Chart_Model {
	
	const CHART_TYPE = 'visitors';
	
	private $event_keys_array;
	private $visitor_stats_col;
	
	private function __construct() {}
	
	/**
	 * Create an instance of this class showing visitor stats for the specified events in a bar chart
	 *
	 * @param	string[]	$event_keys_array	The collection of keys for events whose stats are to be shown in the chart
	 * @return	Items_Chart_Model
	 * @since	v0.4.0
	 */
	public static function create_bar_chart( $event_keys_array ) {
		$result = new self();
		$result->event_keys_array = $event_keys_array;
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
	 * Get the visitor stats collection object for this chart
	 * @return Visitor_Stats_Collection
	 */
	private function get_visitor_stats_collection() {
		if ( ! isset( $this->visitor_stats_col ) ) {

			$group_by = Visitor_Stats_Collection::GROUP_BY_TOTAL;
			$event_keys_array = $this->get_event_keys_array();
			$this->visitor_stats_col = Visitor_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );

		} // function
		return $this->visitor_stats_col;
	} // funciton
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {
		$result = $this->get_visitors_summary_chart_config();
		return $result;
	} // function
	
	/**
	 * Get the visitors summary chart
	 * @return	Chart_Config	The requested chart.
	 */
	private function get_visitors_summary_chart_config() {
		
		$visitor_stats_col = $this->get_visitor_stats_collection();
		$event_keys_array = $this->get_event_keys_array();

		// We will only show first-time visitors for a single event
		if ( is_array( $event_keys_array ) ) {
			$event_count = count( $event_keys_array );
		} else {
			$event_count = Event_Stats_Collection::get_all_known_events_count();
		} // endif

		$is_single_event = ( $event_count == 1 );
//		if ( $is_single_event ) {
//			$single_event_key = $event_keys_array[ 0 ]; // We need this to test for "is first time"
//		} // endif

		$group_by = Visitor_Stats_Collection::GROUP_BY_TOTAL;
		$visitor_stats_col = Visitor_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );
		$all_stats_array = array_values( $visitor_stats_col->get_all_stats_array() );
		$total_stats = isset( $all_stats_array[ 0 ] ) ? $all_stats_array[ 0 ] : NULL;

		$first_time_count = isset( $total_stats ) ? $total_stats->get_first_time_count() : 0;
//		$returning_count = isset( $total_stats ) ? $total_stats->get_returning_count() : 0;
//		$unknown_count = isset( $total_stats ) ? $total_stats->get_return_status_unknown_count() : 0;
		$visitor_count = isset( $total_stats ) ? $total_stats->get_visitor_count() : 0;
		$provided_email_count = isset( $total_stats ) ? $total_stats->get_provided_email_count() : 0;
		$join_mail_list_count = isset( $total_stats ) ? $total_stats->get_join_mail_list_count() : 0;

		$chart_config = Chart_Config::create_bar_chart();
		$label = __( 'Visitors', 'reg-man-rc' );
		$chart_config->set_labels( array( $label ) );

		$dataset = Chart_Dataset::create( __( 'Visitors per Event', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $visitor_count / $event_count, 2 ) : $visitor_count;
		$dataset->add_datapoint( Chart_Model::VISITOR_COLOUR, $avg );
		$chart_config->add_dataset( $dataset );

		if ( $is_single_event ) {
			$dataset = Chart_Dataset::create( __( 'First time', 'reg-man-rc' ) );
			$dataset->add_datapoint( Chart_Model::VISITOR_FIRST_TIME_COLOUR, $first_time_count );
			$chart_config->add_dataset( $dataset );
		} // endif

		$dataset = Chart_Dataset::create( __( 'Provided email', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $provided_email_count / $event_count, 2 ) : $provided_email_count;
		$dataset->add_datapoint( Chart_Model::VISITOR_WITH_EMAIL_COLOUR, $avg );
		$chart_config->add_dataset( $dataset );

		$dataset = Chart_Dataset::create( __( 'Join mailing list', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $join_mail_list_count / $event_count, 2 ) : $join_mail_list_count;
		$dataset->add_datapoint( Chart_Model::VISITOR_MAIL_LIST_COLOUR, $avg );
		$chart_config->add_dataset( $dataset );

		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of visitors, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $visitor_count_text, $event_count_text );

		$chart_config->set_title( $title );

		return $chart_config;

	} // function

	
} // class
