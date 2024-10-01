<?php

namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Events_Collection;

class Repairs_Chart_Model implements Chart_Model {
	
	const CHART_TYPE_DETAILED	= 'detailed-repairs';
	const CHART_TYPE_SIMPLIFIED	= 'simplified-repairs';
	
	private $item_stats;
	private $total_items_diverted;
	private $type;

	private function __construct() {}
	
	/**
	 * Create an instance of this class showing repair stats for the specified events in a detailed bar chart
	 *
	 * @param	Events_Collection	$events_collection	The collection of events whose stats are to be shown in the chart
	 * @return	Repairs_Chart_Model
	 * @since	v0.1.0
	 */
	public static function create_detailed_bar_chart_for_events_collection( $events_collection ) {
		$result = new self();
		$result->item_stats = Item_Stats::get_total_item_stats_for_events_collection( $events_collection );
		$result->type = self::CHART_TYPE_DETAILED;
		return $result;
	} // function
	
	/**
	 * Create an instance of this class showing repair stats for the specified events in a simplified bar chart
	 *
	 * @param	Events_Collection	$events_collection	The collection of events whose stats are to be shown in the chart
	 * @return	Repairs_Chart_Model
	 * @since	v0.1.0
	 */
	public static function create_simplified_bar_chart_for_events_collection( $events_collection ) {
		$result = new self();
		$result->item_stats = Item_Stats::get_total_item_stats_for_events_collection( $events_collection );
		$result->type = self::CHART_TYPE_SIMPLIFIED;
		return $result;
	} // function
		
	/**
	 * Get the item stats object for this chart
	 * @return Item_Stats
	 */
	private function get_item_stats() {
		return $this->item_stats;
	} // funciton
	
	/**
	 * Get the total items diverted for the current set of events
	 * @return int
	 */
	public function get_total_items_diverted() {
		if ( ! isset( $this->total_items_diverted ) ) {
			$item_stats = $this->get_item_stats();
			$this->total_items_diverted = $item_stats->get_estimated_diversion_count();
		} // endif
		return $this->total_items_diverted;
	} // funciton
	
	/**
	 * Get the type for this chart
	 * @return string
	 */
	private function get_type() {
		return $this->type;
	} // function
	
	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config() {

		$type = $this->get_type();

		switch( $type ) {

			case self::CHART_TYPE_DETAILED:
				$result = $this->get_detailed_bar_chart_config();
				break;
			
			default:
			case self::CHART_TYPE_SIMPLIFIED:
				$result = $this->get_simplified_bar_chart_config();
				break;

		} // endswitch
		return $result;
	} // function
	
	/**
	 * Return a chart a detailed view of items fixed including response rates and diversion estimates
	 * @return	Chart_Config		The chart requested
	 */
	private function get_detailed_bar_chart_config() {

		$item_stats = $this->get_item_stats();
//		Error_Log::var_dump( $item_stats );
		
		$chart_config = Chart_Config::create_stacked_bar_chart();

		$labels = array();

		$item_count 		= $item_stats->get_item_count();
		$fixed_count 		= $item_stats->get_fixed_count();
		$repairable_count	= $item_stats->get_repairable_count();
		$eol_count			= $item_stats->get_end_of_life_count();
		$sample_size		= $fixed_count + $repairable_count + $eol_count;
		$unsampled_count	= $item_count - $sample_size;
//		$diverted_count		= $item_stats->get_estimated_diversion_count();
		$diverted_lower		= $item_stats->get_estimated_diversion_range_lower_count();
		$diverted_upper		= $item_stats->get_estimated_diversion_range_upper_count();
		$diverted_range		= $item_stats->get_estimated_diversion_count_range_as_string();
		$diverted_percent	= $item_stats->get_estimated_diversion_rate_as_percent_string();

//		Error_Log::var_dump( $diverted_lower, $diverted_upper, $diverted_range, $diverted_percent );
		$labels[] = __( 'Repair Outcome', 'reg-man-rc' );

		$fixed_dataset = Chart_Dataset::create( __( 'Fixed', 'reg-man-rc' ) );
		$fixed_dataset->set_stack( 'Reported' );
		$fixed_dataset->add_datapoint( Chart_Model::FIXED_ITEM_COLOUR, $fixed_count );
		$chart_config->add_dataset( $fixed_dataset );

		$repairable_dataset = Chart_Dataset::create( __( 'Repairable', 'reg-man-rc' ) );
		$repairable_dataset->set_stack( 'Reported' );
		$repairable_dataset->add_datapoint( Chart_Model::REPAIRABLE_ITEM_COLOUR, $repairable_count );
		$chart_config->add_dataset( $repairable_dataset );

		$unknown_dataset = Chart_Dataset::create( __( 'Outcome Not Reported', 'reg-man-rc' ) );
		$unknown_dataset->set_stack( 'Reported' );
		$unknown_dataset->add_datapoint( Chart_Model::UNKNOWN_STATUS_ITEM_COLOUR, $unsampled_count );
		$chart_config->add_dataset( $unknown_dataset );

		$eol_dataset = Chart_Dataset::create( __( 'End of Life', 'reg-man-rc' ) );
		$eol_dataset->set_stack( 'Reported' );
		$eol_dataset->add_datapoint( self::EOL_ITEM_COLOUR, $eol_count );
		$chart_config->add_dataset( $eol_dataset );

		$est_diverted_dataset = Chart_Dataset::create( __( 'Estimated Minimum Items Diverted', 'reg-man-rc' ) );
		$est_diverted_dataset->set_stack( 'Estimated' );
		$colour = Chart_Model::YELLOW_GREEN_COLOUR;
		$est_diverted_dataset->add_datapoint( $colour, $diverted_lower );
		$chart_config->add_dataset( $est_diverted_dataset );

		$est_range_diverted_dataset = Chart_Dataset::create( __( 'Estimated Range Span of Items Diverted', 'reg-man-rc' ) );
		$est_range_diverted_dataset->set_stack( 'Estimated' );
		$colour = Chart_Model::SOFT_YELLOW_GREEN_COLOUR;
		$est_range_diverted_dataset->add_datapoint( $colour, ( $diverted_upper - $diverted_lower ) );
		$chart_config->add_dataset( $est_range_diverted_dataset );

		$chart_config->set_labels( $labels );

		$item_count_text = sprintf( _n( '%s Item', '%s Items', $item_count, 'reg-man-rc' ), number_format_i18n( $item_count ) );

		if ( $sample_size > 0 ) {
			/* translators: %1$s is a count of items like "1 item" or "5 items", %2$s is a percent "81%" */
			$title = sprintf( __( '%1$s, %2$s Diverted From Landfill (fixed + repairable)', 'reg-man-rc' ), $item_count_text, $diverted_percent );
			$conf = Settings::get_confidence_level_for_interval_estimate();
			/* translators: %1$s is a range like "15 - 23", %2$s is a confidence level between 1 and 100 like "95" */
			$subtitle = sprintf( __( '%1$s Items Diverted (%2$s%% confidence)', 'reg-man-rc' ), $diverted_range, $conf );
			$chart_config->set_subtitle( $subtitle );
		} else {
			$title = $item_count_text; // No items so just say that
		} // endif

		$chart_config->set_title( $title );
// Error_Log::var_dump( $item_stats_collection );
		return $chart_config;

	} // function

	
	/**
	 * Return a chart showing items fixed
	 * @return	Chart_Config		The chart requested
	 */
	private function get_simplified_bar_chart_config() {

		$item_stats = $this->get_item_stats();
		
		$chart_config = Chart_Config::create_bar_chart();

		$labels = array();

		$diverted_count		= $item_stats->get_estimated_diversion_count();
		
		$labels[] = ''; // We only want a label on the dataset, and not on the whole chart because it's redundant

		/* Translators: %s is a count of items fixed */
//		$diverted_count_text = sprintf( _n( '%s Item Fixed!', '%s Items Fixed!', $diverted_count, 'reg-man-rc' ), number_format_i18n( $diverted_count ) );
		$diverted_count_text = __( 'Items', 'reg-man-rc' );
		$chart_config->set_is_display_legend( FALSE );
		$chart_config->set_is_display_tooltip( FALSE );
		
		$fixed_dataset = Chart_Dataset::create( $diverted_count_text );
		$fixed_dataset->add_datapoint( Chart_Model::FIXED_ITEM_COLOUR, $diverted_count );
		$chart_config->add_dataset( $fixed_dataset );
		
		$chart_config->set_option(  'indexAxis', 'y' );
		
		// TODO: This should be attributes in the short code and properties of this class
		$duration = 3000;
		$animation = Chart_Animation::create( $duration );
//		$animation->set_easing_direction( Chart_Animation::EASING_DIRECTION_IN );
//		$animation->set_easing_direction( Chart_Animation::EASING_DIRECTION_OUT );
//		$animation->set_easing_direction( Chart_Animation::EASING_DIRECTION_IN_AND_OUT );

//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_BOUNCE );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_ELASTIC );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_QUAD );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_CUBIC );
		$animation->set_easing_style( Chart_Animation::EASING_STYLE_QUART );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_EXPO );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_SINE );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_CIRC );
//		$animation->set_easing_style( Chart_Animation::EASING_STYLE_LINEAR );
		
		$chart_config->set_animation( $animation );

		$chart_config->set_labels( $labels );
// Error_Log::var_dump( $item_stats_collection );
		return $chart_config;

	} // function
	
} // class
