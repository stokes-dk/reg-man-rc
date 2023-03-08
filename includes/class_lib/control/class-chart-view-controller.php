<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Stats\Chart;
use Reg_Man_RC\Model\Stats\Chart_Dataset;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Stats\Item_Group_Stats;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Model\Stats\Visitor_Statistics;
use Reg_Man_RC\Model\Stats\Item_Statistics;
use Reg_Man_RC\Model\Stats\Volunteer_Statistics;
use Reg_Man_RC\Model\Event_Filter;


/**
 * The chart view controller
 *
 * This class provides the controller function for charts showing stats like a chart for items
 *
 * @since v0.1.0
 *
 */
class Chart_View_Controller {

	const AJAX_GET_CHART_ACTION = 'reg-man-rc-get-chart';

	const GREY_COLOUR					= 'rgb( 204,	204,	204	)';
	const DARK_GREY_COLOUR				= 'rgb( 102,	102,	102	)';
	const RED_COLOUR					= 'rgb( 204,	0,		0	)';
	const MAGENTA_COLOUR				= 'rgb( 204,	0,		204	)';
	const YELLOW_COLOUR					= 'rgb( 204,	204, 	0	)';
	const YELLOW_GREEN_COLOUR			= 'rgb( 153,	204, 	0	)';
	const ORANGE_GREEN_COLOUR			= 'rgb( 204,	153, 	0	)';
	const SOFT_YELLOW_GREEN_COLOUR		= 'rgba( 153,	204, 	0,	0.5	)';
	const GREEN_COLOUR					= 'rgb(	0,		204,	0	)';
	const CYAN_COLOUR					= 'rgb(	0,		204,	204	)';
	const BLUE_COLOUR					= 'rgb(	0,		0,		204	)';

	const DEFAULT_COLOUR				= self::GREY_COLOUR;

	const VISITOR_COLOUR				= self::BLUE_COLOUR;
	const FIXER_COLOUR					= self::CYAN_COLOUR;
	const NON_FIXER_COLOUR				= self::MAGENTA_COLOUR;

	const VISITOR_WITH_EMAIL_COLOUR		= self::GREEN_COLOUR;
	const VISITOR_MAIL_LIST_COLOUR		= self::YELLOW_COLOUR;
	const VISITOR_FIRST_TIME_COLOUR		= self::RED_COLOUR;

	const FIXED_ITEM_COLOUR				= self::GREEN_COLOUR;
	const REPAIRABLE_ITEM_COLOUR		= self::YELLOW_COLOUR;
	const EOL_ITEM_COLOUR				= self::RED_COLOUR;
	const UNKNOWN_STATUS_ITEM_COLOUR	= self::GREY_COLOUR;

	const NO_VOLUNTEER_ROLE_COLOUR		= self::GREY_COLOUR;

	public static function register() {

		// Register the handler for an AJAX request to get stats from a logged-in user
		add_action( 'wp_ajax_' . self::AJAX_GET_CHART_ACTION, array( __CLASS__, 'handle_priv_ajax_get_stats' ) );

		// FIXME - there should be non-privileged version too so we can show stats on the website!

	} // function

	/**
	 * Handle a request to get stats from a logged-in user
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_priv_ajax_get_stats() {
		self::handle_ajax_get_stats();
	} // function

	private static function handle_ajax_get_stats() {
		$form_response = Ajax_Form_Response::create();

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		// The nonce is a hidden field in the form so check it first
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		if ( ! wp_verify_nonce( $nonce, self::AJAX_GET_CHART_ACTION ) ) {
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
		} else {
			$filter = Event_Filter_Input_Form::get_filter_object_from_request( $form_data );
			$object_type = isset( $form_data[ 'object_type'] ) ? $form_data[ 'object_type'] : NULL;

			switch ( $object_type ) {

				case 'event':
					$chart = self::get_events_chart( $filter, $form_data, $form_response );
					break;

				case 'item':
					$chart = self::get_items_chart( $filter, $form_data, $form_response );
					break;

				case 'fixed':
					$chart = self::get_fixed_chart( $filter, $form_data, $form_response );
					break;

				case 'visitor':
					$chart = self::get_visitors_chart( $filter, $form_data, $form_response );
					break;

				case 'people':
					$chart = self::get_people_chart( $filter, $form_data, $form_response );
					break;

				case 'fixer':
					$chart = self::get_fixers_chart( $filter, $form_data, $form_response );
					break;

				case 'items-per-fixer':
					$chart = self::get_items_per_fixer_chart( $filter, $form_data, $form_response );
					break;

				case 'visitors-per-volunteer':
					$chart = self::get_visitors_per_volunteer_chart( $filter, $form_data, $form_response );
					break;

				case 'non-fixer':
					$chart = self::get_non_fixers_chart( $filter, $form_data, $form_response );
					break;

				default:
					$form_response->add_error( 'object_type', $object_type, "Unknown object type for chart: \"$object_type\"");
					break;

			} // endswitch

			if ( isset( $chart ) ) {
				$json_obj = $chart->jsonSerialize();
				$form_response->set_result_data( $json_obj );
			} // endif

		} // endif

		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function


	private static function get_events_chart( $filter, $form_data, $form_response ) {

		$events = Event::get_all_events_by_filter( $filter );
		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'event_category':
				$result = self::get_events_by_category_chart( $events );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Gets a chart describing events organized by event category
	 *
	 * @param	Event[]	$events	The collection of events to be shown in the chart
	 * @return	Chart
	 * @since	v0.1.0
	 */
	private static function get_events_by_category_chart( $events ) {

		$category_count_array = self::get_category_count_array_for_events( $events );

		$chart = Chart::create_bar_chart();
		foreach ( $category_count_array as $cat_name => $count ) {
			if ( $cat_name == -1 ) {
				$cat_name = __( 'Uncategorized', 'reg-man-rc' );
				$colour = self::DEFAULT_COLOUR;
			} else {
				$category = Event_Category::get_event_category_by_name( $cat_name );
				if ( isset( $category ) ) {
					$colour = $category->get_colour();
				} else {
					$colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $cat_name ) );
			$dataset->add_datapoint( $colour, $count );
			$chart->add_dataset( $dataset );
		} // endfor

		$label = __( 'Event Categories', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$event_count = count( $events );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		$chart->set_title( $event_count_text );

		return $chart;

	} // function

	private static function get_category_count_array_for_events( $event_array ) {
		$result = array();
		// Make sure we show all categories (at least all the repair categories)
		$all_cats = Event_Category::get_all_event_categories();
		foreach ( $all_cats as $category ) {
			if ( $category->get_is_accept_item_registration() ) {
				$cat_name = $category->get_name();
				$result[ $cat_name ] = 0;
			} // endif
		} // endif
		foreach( $event_array as $event ) {
			$category_names = $event->get_categories(); // This is an array of category names
			if ( empty( $category_names ) ) {
				$category_names = array( -1 ); // use -1 as a marker for uncategorized events
			} // endif
			foreach( $category_names as $cat_name ) {
				// I need to group items together even when they use aka names
				$category = Event_Category::get_event_category_by_name( $cat_name );
				if ( isset( $category ) ) {
					$cat_name = $category->get_name();
				} // endif
				if ( ! isset( $result[ $cat_name ] ) ) {
					$result[ $cat_name ] = 1;
				} else {
					$result[ $cat_name ]++;
				} // endif
			} // endfor
		} // endfor
		return $result;
	} // function

	private static function get_items_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'item_type':
				$result = self::get_items_by_type_chart( $filter );
				break;
			case 'fixer_station':
				$result = self::get_items_by_fixer_station_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Return a chart showing items organized by item_type
	 * @param	Event_Filter	$filter		The filter whose events are to be shown
	 * @return	Chart			The chart requested
	 */
	private static function get_items_by_type_chart( $filter ) {

		$chart = Chart::create_bar_chart();
		$label = __( 'Item Types', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

//	Error_Log::var_dump( $filter );
		$group_by = Item_Statistics::GROUP_BY_ITEM_TYPE;
		$statistics = Item_Statistics::create_item_stats_for_filter( $filter, $group_by );
		$event_count = $statistics->get_event_count();
		$total_stats_array = $statistics->get_total_stats_array();
//	Error_Log::var_dump( $total_stats_array );

		$total_item_count = 0; // I'll need to have a running count of everything
		$no_type_name = __( 'No type specified', 'reg-man-rc' );
		foreach ( $total_stats_array as $fixer_station => $stats ) {
			if ( empty( $type_id ) ) {
				$type_name = $no_type_name;
				$colour = self::DEFAULT_COLOUR;
			} else {
				$item_type = Item_Type::get_item_type_by_id( $type_id );
				if ( isset( $item_type ) ) {
					$type_name = $item_type->get_name();
					$colour = $item_type->get_colour();
				} else {
					$type_name = $no_type_name;
					$colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
//		Error_Log::var_dump( $type_id, $type_name, $stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $type_name ) );
			$count = $stats->get_item_count();
			$total_item_count += $count;
			// It wouldn't make sense if there are items but no events.  But check and show count in the worst case
			$avg = ( $event_count !== 0 ) ? round( $count / $event_count, 2 ) : $count;
			$dataset->add_datapoint( $colour, $avg );
			$chart->add_dataset( $dataset );
		} // endfor

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_count_text, $event_count_text );
		$chart->set_title( $title );

		return $chart;

	} // function

	/**
	 * Return a chart showing items organized by fixer station
	 * @param	Event_Filter	$filter		The filter whose events are to be shown
	 * @return	Chart			The chart requested
	 */
	private static function get_items_by_fixer_station_chart( $filter ) {

		$chart = Chart::create_bar_chart();
		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

//	Error_Log::var_dump( $filter );
		$group_by = Item_Statistics::GROUP_BY_FIXER_STATION;
		$statistics = Item_Statistics::create_item_stats_for_filter( $filter, $group_by );
		$event_count = $statistics->get_event_count();
		$total_stats_array = $statistics->get_total_stats_array();
//	Error_Log::var_dump( $total_stats_array );

		$total_item_count = 0; // I'll need to have a running count of everything
		$no_station_name = __( 'No fixer station specified', 'reg-man-rc' );
		foreach ( $total_stats_array as $station_id => $stats ) {
			if ( empty( $station_id ) ) {
				$station_name = $no_station_name;
				$colour = self::DEFAULT_COLOUR;
			} else {
				$fixer_station = Fixer_Station::get_fixer_station_by_id( $station_id );
				if ( isset( $fixer_station ) ) {
					$station_name = $fixer_station->get_name();
					$colour = $fixer_station->get_colour();
				} else {
					$station_name = $no_station_name;
					$colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
//		Error_Log::var_dump( $station_id, $station_name, $stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$count = $stats->get_item_count();
			$total_item_count += $count;
			// It wouldn't make sense if there are items but no events.  But check and show count in the worst case
			$avg = ( $event_count !== 0 ) ? round( $count / $event_count, 2 ) : $count;
			$dataset->add_datapoint( $colour, $avg );
			$chart->add_dataset( $dataset );
		} // endfor

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $item_count_text, $event_count_text );
		$chart->set_title( $title );

		return $chart;

	} // function

	private static function get_station_count_array_for_items( $item_descriptors ) {
		$result = array();
		// Make sure we show all stations
		$all_stations = Fixer_Station::get_all_fixer_stations();
		foreach ( $all_stations as $station ) {
			$station_name = $station->get_name();
			$result[ $station_name ] = 0;
		} // endif
		foreach( $item_descriptors as $item ) {
			$station_name = $item->get_fixer_station_name();
			if ( ! empty( $station_name ) ) {
				// I need to group items together even when they use aka names like "Computers & Electronics" for "Computers"
				$station = Fixer_Station::get_fixer_station_by_name( $station_name ); // gives me the Item_Type object based on any name including aka
				if ( isset( $station ) ) {
					$station_name = $station->get_name();
				} // endif
			} else {
				// The item has no assigned station (often the case) so get the item type and use its default station
				$type_name = $item->get_item_type_name();
				if ( empty( $type_name ) ) {
					$station_name = -1;
				} else {
					$item_type = Item_type::get_item_type_by_name( $type_name ); // gives me the Item_Type object based on any name including aka
					if ( ! isset( $item_type ) ) {
						$station_name = -1;
					} else {
						$station = $item_type->get_fixer_station();
						$station_name = isset( $station ) ? $station->get_name() : -1; // use -1 as a marker for no station
					} // endif
				} // endif
			} // endif
			if ( ! isset( $result[ $station_name ] ) ) {
				$result[ $station_name ] = 1;
			} else {
				$result[ $station_name ]++;
			} // endif
		} // endfor
		return $result;
	} // function


	private static function get_fixed_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'summary':
				$result = self::get_fixed_summary_chart( $filter );
				break;
			default:
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Return a chart showing items fixed
	 * @param	string[]	$fixed_data		The data about items fixed
	 * @return	Chart						The chart requested
	 */
	private static function get_fixed_summary_chart( $filter ) {

		$item_stats = Item_Statistics::create_item_stats_for_filter( $filter, Item_Statistics::GROUP_BY_TOTAL );
		$fixed_stats_array = $item_stats->get_total_stats_array();
		$fixed_stats_array = array_values( $fixed_stats_array );

		// Since we grouped by total the fixed data is an array with one element only, i.e. the totals so we will get that element
		if ( isset( $fixed_stats_array[ 0 ] ) ) {
			$fixed_stats = $fixed_stats_array[ 0 ];
		} else {
			// There are no stats
			$fixed_stats = Item_Group_Stats::create( '', 0, 0, 0, 0 );
		} // endif

		$chart = Chart::create_stacked_bar_chart();

		$labels = array();

		$item_count 		= $fixed_stats->get_item_count();
		$fixed_count 		= $fixed_stats->get_fixed_count();
		$repairable_count	= $fixed_stats->get_repairable_count();
		$eol_count			= $fixed_stats->get_end_of_life_count();
		$sample_size		= $fixed_count + $repairable_count + $eol_count;
		$unsampled_count	= $item_count - $sample_size;
//		$diverted_count		= $fixed_stats->get_estimated_diversion_count();
		$diverted_lower		= $fixed_stats->get_estimated_diversion_range_lower_count();
		$diverted_upper		= $fixed_stats->get_estimated_diversion_range_upper_count();
		$diverted_range		= $fixed_stats->get_estimated_diversion_count_range_as_string();
		$diverted_percent	= $fixed_stats->get_estimated_diversion_rate_as_percent_string();

		$labels[] = __( 'Repair Status', 'reg-man-rc' );

		$fixed_dataset = Chart_Dataset::create( __( 'Fixed', 'reg-man-rc' ) );
		$fixed_dataset->set_stack( 'Reported' );
		$fixed_dataset->add_datapoint( self::FIXED_ITEM_COLOUR, $fixed_count );
		$chart->add_dataset( $fixed_dataset );

		$repairable_dataset = Chart_Dataset::create( __( 'Repairable', 'reg-man-rc' ) );
		$repairable_dataset->set_stack( 'Reported' );
		$repairable_dataset->add_datapoint( self::REPAIRABLE_ITEM_COLOUR, $repairable_count );
		$chart->add_dataset( $repairable_dataset );

		$eol_dataset = Chart_Dataset::create( __( 'End of Life', 'reg-man-rc' ) );
		$eol_dataset->set_stack( 'Reported' );
		$eol_dataset->add_datapoint( self::EOL_ITEM_COLOUR, $eol_count );
		$chart->add_dataset( $eol_dataset );

		$unknown_dataset = Chart_Dataset::create( __( 'Outcome Not Reported', 'reg-man-rc' ) );
		$unknown_dataset->set_stack( 'Reported' );
		$unknown_dataset->add_datapoint( self::UNKNOWN_STATUS_ITEM_COLOUR, $unsampled_count );
		$chart->add_dataset( $unknown_dataset );

		$est_diverted_dataset = Chart_Dataset::create( __( 'Estimated Minimum Items Diverted', 'reg-man-rc' ) );
		$est_diverted_dataset->set_stack( 'Estimated' );
		$colour = self::YELLOW_GREEN_COLOUR;
		$est_diverted_dataset->add_datapoint( $colour, $diverted_lower );
		$chart->add_dataset( $est_diverted_dataset );

		$est_range_diverted_dataset = Chart_Dataset::create( __( 'Estimated Range Span of Items Diverted', 'reg-man-rc' ) );
		$est_range_diverted_dataset->set_stack( 'Estimated' );
		$colour = self::SOFT_YELLOW_GREEN_COLOUR;
		$est_range_diverted_dataset->add_datapoint( $colour, ( $diverted_upper - $diverted_lower ) );
		$chart->add_dataset( $est_range_diverted_dataset );

		$chart->set_labels( $labels );

		$item_count_text = sprintf( _n( '%s Item', '%s Items', $item_count, 'reg-man-rc' ), number_format_i18n( $item_count ) );

		if ( $sample_size > 0 ) {
			/* translators: %1$s is a count of items like "1 item" or "5 items", %2$s is a percent "81%" */
			$title = sprintf( __( '%1$s, %2$s Diverted From Landfill (fixed + repairable)', 'reg-man-rc' ), $item_count_text, $diverted_percent );
			$conf = Settings::get_confidence_level_for_interval_estimate();
			/* translators: %1$s is a range like "15 - 23", %2$s is a confidence level between 1 and 100 like "95" */
			$subtitle = sprintf( __( '%1$s Items Diverted (%2$s%% confidence)', 'reg-man-rc' ), $diverted_range, $conf );
			$chart->set_subtitle( $subtitle );
		} else {
			$title = $item_count_text; // No items so just say that
		} // endif

		$chart->set_title( $title );

		return $chart;

	} // function


	private static function get_fixers_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'fixer_station':
				$result = self::get_fixers_by_station_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Return a chart showing fixers by fixer_station
	 * @param	Volunteer_Registration_Descriptor[]	$fixer_registrations
	 * @return	Chart		The chart requested
	 */
	private static function get_fixers_by_station_chart( $filter ) {

		$group_by = Volunteer_Statistics::GROUP_BY_FIXER_STATION;
		$statistics = Volunteer_Statistics::create_for_filter( $filter, $group_by );
		$event_count = $statistics->get_event_count();
		$total_stats_array = $statistics->get_total_stats_array();

		$chart = Chart::create_bar_chart();

		$labels = array();

		$total_count = 0;
		$no_station_name = __( 'No station specified', 'reg-man-rc' );
		foreach ( $total_stats_array as $station_id => $stats ) {
			$station = Fixer_Station::get_fixer_station_by_id( $station_id );
			if ( ! isset( $station ) ) {
				$station_name = __( 'No Fixer Station', 'reg-man-rc' );
				$colour = self::DEFAULT_COLOUR;
			} else {
				$station_name = $station->get_name();
				$colour = $station->get_colour();
			} // endif
			$head_count = $stats->get_head_count();
			$total_count += $head_count;
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$avg = ( $event_count !== 0 ) ? round( $head_count / $event_count, 2 ) : $head_count;
			$dataset->add_datapoint( $colour, $avg );
			$chart->add_dataset( $dataset );
		} // endfor

		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $total_count, 'reg-man-rc' ), number_format_i18n( $total_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of fixers, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $fixer_count_text, $event_count_text );
		$chart->set_title( $title );

		return $chart;

	} // function

	private static function get_station_count_array_for_fixers( $fixer_registrations ) {
		$result = array();
		// Make sure we show all stations
		$all_stations = Fixer_Station::get_all_fixer_stations();
		foreach ( $all_stations as $station ) {
			$station_name = $station->get_name();
			$result[ $station_name ] = 0;
		} // endif
		foreach( $fixer_registrations as $reg ) {
			$station_name = $reg->get_assigned_fixer_station_name();
			if ( empty( $station_name ) ) {
				$station_name = -1;
			} else {
				// I need to group items together even when they use aka names
				$station = Fixer_Station::get_fixer_station_by_name( $station_name );
				if ( isset( $station ) ) {
					$station_name = $station->get_name();
				} // endif
			} // endif
			if ( ! isset( $result[ $station_name ] ) ) {
				$result[ $station_name ] = 1;
			} else {
				$result[ $station_name ]++;
			} // endif
		} // endfor
		return $result;
	} // function


	private static function get_items_per_fixer_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'fixer_station':
				$result = self::get_items_per_fixer_by_station_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	private static function get_items_per_fixer_by_station_chart( $filter ) {

		$chart = Chart::create_bar_chart();
		$label = __( 'Fixer Stations', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

//	Error_Log::var_dump( $filter );

		$group_by = Volunteer_Statistics::GROUP_BY_FIXER_STATION;
		$fixer_stats = Volunteer_Statistics::create_for_filter( $filter, $group_by );
		$total_fixer_stats_array = $fixer_stats->get_total_stats_array();
//	Error_Log::var_dump( $total_fixer_stats_array );
		$total_fixer_count = 0; // I need a total number of fixers
		foreach( $total_fixer_stats_array as $station_id => $fixer_group_stats ) {
			$total_fixer_count += $fixer_group_stats->get_head_count();
		} // endfor

		$group_by = Item_Statistics::GROUP_BY_FIXER_STATION;
		$item_stats_array = Item_Statistics::create_item_stats_for_filter( $filter , $group_by );
		$event_count = $item_stats_array->get_event_count();
		$total_item_stats_array = $item_stats_array->get_total_stats_array();
//	Error_Log::var_dump( $total_item_stats_array );

		$total_item_count = 0; // I'll need to have a running count of everything
		$no_station_name = __( 'No fixer station specified', 'reg-man-rc' );
		foreach ( $total_item_stats_array as $station_id => $item_group_stats ) {
			if ( empty( $station_id ) ) {
				$station_name = $no_station_name;
				$colour = self::DEFAULT_COLOUR;
			} else {
				$fixer_station = Fixer_Station::get_fixer_station_by_id( $station_id );
				if ( isset( $fixer_station ) ) {
					$station_name = $fixer_station->get_name();
					$colour = $fixer_station->get_colour();
				} else {
					$station_name = $no_station_name;
					$colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
//		Error_Log::var_dump( $station_id, $item_group_stats );
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $station_name ) );
			$item_count = $item_group_stats->get_item_count();
			$total_item_count += $item_count;
			$fixer_group_stats = isset( $total_fixer_stats_array[ $station_id ] ) ? $total_fixer_stats_array[ $station_id ] : NULL;
			$fixer_count = isset( $fixer_group_stats ) ? $fixer_group_stats->get_head_count() : 0;
			//Avoid a divide by zero error if there are no events or no fixers registered for the station
			$items_per_fixer = ( $fixer_count != 0 ) ? round( $item_count / $fixer_count, 2 ) : $item_count;
//		Error_Log::var_dump( $station_name, $item_count, $fixers_count );
			$dataset->add_datapoint( $colour, $items_per_fixer );
			$chart->add_dataset( $dataset );
		} // endfor

		$station_array = Fixer_Station::get_all_fixer_stations();

		$item_count_text = sprintf( _n( '%s item', '%s items', $total_item_count, 'reg-man-rc' ), number_format_i18n( $total_item_count ) );
		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $total_fixer_count, 'reg-man-rc' ), number_format_i18n( $total_fixer_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of fixers, %3$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s, %3$s', 'reg-man-rc' ), $item_count_text, $fixer_count_text, $event_count_text );
		$chart->set_title( $title );

		return $chart;

	} // function


	private static function get_non_fixers_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'volunteer_role':
				$result = self::get_non_fixers_by_role_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Return a chart showing non-fixer volunteers by volunteer_role
	 * @param	Event_Filter	$filter	The fitler describing the set of events whose chart is to be returned
	 * @return	Chart		The chart requested
	 */
	private static function get_non_fixers_by_role_chart( $filter ) {

		$group_by = Volunteer_Statistics::GROUP_BY_VOLUNTEER_ROLE;
		$statistics = Volunteer_Statistics::create_for_filter( $filter, $group_by );
		$event_count = $statistics->get_event_count();
		$total_stats_array = $statistics->get_total_stats_array();
//	Error_Log::var_dump( $total_stats_array );

		$chart = Chart::create_bar_chart();
		$label = __( 'Volunteer Roles', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$total_head_count = 0; // I'll need to have a running count of everything
		$no_role_name = __( 'No role assigned', 'reg-man-rc' );
		foreach ( $total_stats_array as $role_id => $stats ) {
			$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
			if ( ! isset( $role ) ) {
//				Error_Log::var_dump( $role_id );
				$role_name = $no_role_name;
				$colour = self::NO_VOLUNTEER_ROLE_COLOUR;
			} else {
				$role_name = $role->get_name();
				$colour = $role->get_colour();
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $role_name ), 'reg-man-rc' );
			$head_count = $stats->get_head_count();
			$total_head_count += $head_count;
			$avg = ( $event_count !== 0 ) ? round( $head_count / $event_count, 2 ) : $head_count;
			$dataset->add_datapoint( $colour, $avg );
			$chart->add_dataset( $dataset );
		} // endfor

		$reg_count_text = sprintf( _n( '%s non-fixer role assigned', '%s non-fixer roles assigned', $total_head_count, 'reg-man-rc' ), number_format_i18n( $total_head_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of non-fixer volunteers, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $reg_count_text, $event_count_text );
		$chart->set_title( $title );
		$subtitle = __( 'Note that one volunteer may be assigned multiple roles', 'reg-man-rc' );
		$chart->set_subtitle( $subtitle );

		return $chart;

	} // function

	private static function get_visitors_chart( $filter, $form_data, $form_response ) {
		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'summary':
				$event_key_array = Event_Key::get_event_keys_for_filter( $filter );
				$result = self::get_visitors_summary_chart( $event_key_array );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Get the visitors summary chart
	 * @param	Event[]				$events		An array of events whose visitors are to be shown in the chart
	 * @return	Chart	The requested chart.
	 */
	private static function get_visitors_summary_chart( $event_key_array ) {

		// We will only show first-time visitors for a single event
		$event_count = count( $event_key_array );
		$is_single_event = ( $event_count == 1 );
		if ( $is_single_event ) {
			$single_event_key = $event_key_array[ 0 ]; // We need this to test for "is first time"
		} // endif

		$group_by = Visitor_Statistics::GROUP_BY_TOTAL;
		$visitor_stats = Visitor_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$total_stats_array = array_values( $visitor_stats->get_total_stats_array() );
		$total_stats = isset( $total_stats_array[ 0 ] ) ? $total_stats_array[ 0 ] : NULL;

		$first_time_count = isset( $total_stats ) ? $total_stats->get_first_time_count() : 0;
		$returning_count = isset( $total_stats ) ? $total_stats->get_returning_count() : 0;
		$unknown_count = isset( $total_stats ) ? $total_stats->get_return_status_unknown_count() : 0;
		$visitor_count = isset( $total_stats ) ? $total_stats->get_visitor_count() : 0;
		$provided_email_count = isset( $total_stats ) ? $total_stats->get_provided_email_count() : 0;
		$join_mail_list_count = isset( $total_stats ) ? $total_stats->get_join_mail_list_count() : 0;

		$chart = Chart::create_bar_chart();
		$label = __( 'Visitors', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$dataset = Chart_Dataset::create( __( 'Visitors per Event', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $visitor_count / $event_count, 2 ) : $visitor_count;
		$dataset->add_datapoint( self::VISITOR_COLOUR, $avg );
		$chart->add_dataset( $dataset );

		if ( $is_single_event ) {
			$dataset = Chart_Dataset::create( __( 'First time', 'reg-man-rc' ) );
			$dataset->add_datapoint( self::VISITOR_FIRST_TIME_COLOUR, $first_time_count );
			$chart->add_dataset( $dataset );
		} // endif

		$dataset = Chart_Dataset::create( __( 'Provided email', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $provided_email_count / $event_count, 2 ) : $provided_email_count;
		$dataset->add_datapoint( self::VISITOR_WITH_EMAIL_COLOUR, $avg );
		$chart->add_dataset( $dataset );

		$dataset = Chart_Dataset::create( __( 'Join mailing list', 'reg-man-rc' ) );
		$avg = ( $event_count !== 0 ) ? round( $join_mail_list_count / $event_count, 2 ) : $join_mail_list_count;
		$dataset->add_datapoint( self::VISITOR_MAIL_LIST_COLOUR, $avg );
		$chart->add_dataset( $dataset );

		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		/* translators: %1$s is a count of visitors, %2$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $visitor_count_text, $event_count_text );

		$chart->set_title( $title );

		return $chart;

	} // function

	/**
	 * Get the chart
	 * @param	Event_Filter		$filter
	 * @param	string[][]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 * @return NULL
	 */
	private static function get_visitors_per_volunteer_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'volunteer_role':
				$result = self::get_visitors_per_volunteer_by_role_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Get the chart organized by role
	 * @param	Event_Filter	$filter
	 * @return \Reg_Man_RC\Model\Chart
	 */
	private static function get_visitors_per_volunteer_by_role_chart( $filter ) {

		$event_keys_array = Event_Key::get_event_keys_for_filter( $filter );

		// break up the volunteers by role, note that one volunteer may play multiple roles
		$group_by = Volunteer_Statistics::GROUP_BY_VOLUNTEER_ROLE;
		$volunteer_statistics = Volunteer_Statistics::create_for_event_key_array( $event_keys_array, $group_by );
		$event_count = $volunteer_statistics->get_event_count();
		$total_stats_array = $volunteer_statistics->get_total_stats_array();

		$group_by = Visitor_Statistics::GROUP_BY_TOTAL;
		$visitor_stats = Visitor_Statistics::create_for_event_key_array( $event_keys_array, $group_by );
		$visitor_stats_array = array_values( $visitor_stats->get_total_stats_array() );
		$total_stats = isset( $visitor_stats_array[ 0 ] ) ? $visitor_stats_array[ 0 ] : NULL;

		$visitor_count = isset( $total_stats ) ? $total_stats->get_visitor_count() : 0;

		$chart = Chart::create_bar_chart();

		$label = __( 'Volunteer Roles', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$total_role_count = 0; // I'll need to have a running count of everything
		$no_role_name = __( 'No role assigned', 'reg-man-rc' );
		foreach ( $total_stats_array as $role_id => $stats ) {
			$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
			if ( ! isset( $role ) ) {
				$role_name = $no_role_name;
				$colour = self::NO_VOLUNTEER_ROLE_COLOUR;
			} else {
				$role_name = $role->get_name();
				$colour = $role->get_colour();
			} // endif
			$dataset = Chart_Dataset::create( wp_specialchars_decode( $role_name ), 'reg-man-rc' );
			$head_count = $stats->get_head_count();
			$total_role_count += $head_count;
			$avg = ( $head_count !== 0 ) ? round( $visitor_count / $head_count, 2 ) : 0;
			$dataset->add_datapoint( $colour, $avg );
			$chart->add_dataset( $dataset );
		} // endfor

		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$role_count_text = sprintf( _n( '%s non-fixer role assigned', '%s non-fixer roles assigned', $total_role_count, 'reg-man-rc' ), number_format_i18n( $total_role_count ) );
//		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );

		/* translators: %1$s is a count of visitors, %2$s is a count of non-fixer volunteers, %3$s is a count of events */
		$title = sprintf( __( '%1$s, %2$s', 'reg-man-rc' ), $visitor_count_text, $role_count_text );
		$chart->set_title( $title );
		$subtitle = __( 'Note that one volunteer may be assigned multiple roles', 'reg-man-rc' );
		$chart->set_subtitle( $subtitle );

		return $chart;

	} // function


	private static function get_people_chart( $filter, $form_data, $form_response ) {

		$organize_by = isset( $form_data[ 'organize_by'] ) ? $form_data[ 'organize_by'] : NULL;

		switch( $organize_by ) {
			case 'summary':
				$result = self::get_people_summary_chart( $filter );
				break;
			default:
				$form_response->add_error( 'organize_by', $organize_by, "Unknown organize_by for chart: \"$organize_by\"" );
				$result = NULL;
				break;
		} // endswitch

		return $result;

	} // function

	/**
	 * Return a chart showing a summary of visitors
	 * @param	Visitor[]	$visitors
	 * @return	Chart		The chart requested
	 */
	private static function get_people_summary_chart( $filter ) {

		$chart = Chart::create_bar_chart();

		$labels = array();

		$event_key_array = Event_Key::get_event_keys_for_filter( $filter );

		$event_count = count( $event_key_array );

		// Get visitor stats
		$group_by = Visitor_Statistics::GROUP_BY_TOTAL;
		$visitor_statistics = Visitor_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$visitor_stats_array = array_values( $visitor_statistics->get_total_stats_array() );
		// Since we grouped by total this is an array of size 1, or should be
		$visitor_total_stats = isset( $visitor_stats_array[ 0 ] ) ? $visitor_stats_array[ 0 ] : NULL;
		$visitor_count = isset( $visitor_total_stats ) ? $visitor_total_stats->get_visitor_count() : 0;

		// Get fixer stats
		$group_by = Volunteer_Statistics::GROUP_BY_TOTAL_FIXERS;
		$fixer_statistics = Volunteer_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$fixer_stats_array = array_values( $fixer_statistics->get_total_stats_array() );
		// Since we grouped by total this is an array of size 1, or should be
		$fixer_total_stats = isset( $fixer_stats_array[ 0 ] ) ? $fixer_stats_array[ 0 ] : NULL;
		$fixer_count = isset( $fixer_total_stats ) ? $fixer_total_stats->get_head_count() : 0;

		// Get non-fixer stats
		$group_by = Volunteer_Statistics::GROUP_BY_TOTAL_NON_FIXERS;
		$non_fixer_statistics = Volunteer_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$non_fixer_stats_array = array_values( $non_fixer_statistics->get_total_stats_array() );
		// Since we grouped by total this is an array of size 1, or should be
		$non_fixer_total_stats = isset( $non_fixer_stats_array[ 0 ] ) ? $non_fixer_stats_array[ 0 ] : NULL;
		$non_fixer_count = isset( $non_fixer_total_stats ) ? $non_fixer_total_stats->get_head_count() : 0;
// Error_Log::var_dump( $non_fixer_statistics );

		$avg_visitors = ( $event_count != 0 ) ? round( ( $visitor_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Visitors per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( self::VISITOR_COLOUR, $avg_visitors );
		$chart->add_dataset( $dataset );

		$avg_fixers = ( $event_count != 0 ) ? round( ( $fixer_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Fixers per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( self::FIXER_COLOUR, $avg_fixers );
		$chart->add_dataset( $dataset );

		$avg_non_fixers = ( $event_count != 0 ) ? round( ( $non_fixer_count / $event_count ), 2 ) : 0;
		$dataset = Chart_Dataset::create( __( 'Non-fixer Volunteers per Event', 'reg-man-rc' ) );
		$dataset->add_datapoint( self::NON_FIXER_COLOUR, $avg_non_fixers );
		$chart->add_dataset( $dataset );

		$label = __( 'Registrations', 'reg-man-rc' );
		$chart->set_labels( array( $label ) );

		$event_count_text = sprintf( _n( '%s event', '%s events', $event_count, 'reg-man-rc' ), number_format_i18n( $event_count ) );
		$visitor_count_text = sprintf( _n( '%s visitor', '%s visitors', $visitor_count, 'reg-man-rc' ), number_format_i18n( $visitor_count ) );
		$fixer_count_text = sprintf( _n( '%s fixer', '%s fixers', $fixer_count, 'reg-man-rc' ), number_format_i18n( $fixer_count ) );
		$non_fixer_count_text = sprintf( _n( '%s non-fixer volunteer', '%s non-fixer volunteers', $non_fixer_count, 'reg-man-rc' ), number_format_i18n( $non_fixer_count ) );
		/* translators: %1$s is a count of items, %2$s is a count of fixers, %3$s is a count of non-fixers, %4$s is similar a count of events */
		$title = sprintf( __( '%1$s, %2$s, %3$s, %4$s', 'reg-man-rc' ), $visitor_count_text, $fixer_count_text, $non_fixer_count_text, $event_count_text );
		$chart->set_title( $title );

		return $chart;

	} // function


} // class