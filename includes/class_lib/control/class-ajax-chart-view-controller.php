<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Events_Chart_Model;
use Reg_Man_RC\Model\Stats\Repairs_Chart_Model;
use Reg_Man_RC\Model\Stats\Items_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_And_Volunteers_Chart_Model;
use Reg_Man_RC\Model\Stats\Volunteers_Chart_Model;
use Reg_Man_RC\Model\Events_Collection;


/**
 * The AJAX chart view controller
 *
 * This class provides the controller function for AJAX charts showing stats like a chart for items or events
 *
 * @since v0.1.0
 *
 */
class Ajax_Chart_View_Controller {

	const AJAX_GET_CHART_ACTION = 'reg-man-rc-get-chart';

	public static function register() {

		// Register the handler for an AJAX request to get stats from a logged-in user
		add_action( 'wp_ajax_' . self::AJAX_GET_CHART_ACTION, array( __CLASS__, 'handle_priv_ajax_get_chart_config' ) );

		// Do we need a non-priv version so we can show charts on the front end?
		// Likely not.  Those charts should not use AJAX because that circumvents caching

	} // function

	/**
	 * Handle a request to get a chart from a logged-in user
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_priv_ajax_get_chart_config() {
		self::handle_ajax_get_chart_config();
	} // function

	private static function handle_ajax_get_chart_config() {
		$form_response = Ajax_Form_Response::create();

		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );
		
		// Get the events collection from the form data
		$event_key = isset( $form_data[ 'event-key'] ) ? $form_data[ 'event-key' ] : NULL;
		if ( ! empty( $event_key ) ) {
			$events_collection = Events_Collection::create_for_single_event_key( $event_key );
		} else {
			$is_include_placeholder_events = TRUE;
			$event_filter = Event_Filter_Input_Form::get_filter_object_from_request( $form_data );
			if ( ! empty( $event_filter ) ) {
				$events_collection = Events_Collection::create_for_event_filter( $event_filter, $is_include_placeholder_events );
			} else {
				$events_collection = Events_Collection::create_for_all_events( $is_include_placeholder_events );
			} // endif
		} // endif
		
		// The nonce is a hidden field in the form so check it first
		$nonce = isset( $form_data[ '_wpnonce' ] ) ? $form_data[ '_wpnonce' ] : NULL;
		
		if ( ! wp_verify_nonce( $nonce, self::AJAX_GET_CHART_ACTION ) ) {
			
			$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
			$form_response->add_error( '_wpnonce', '', $err_msg );
			
		} else {
			
			$chart_type = isset( $form_data[ 'chart_type'] ) ? $form_data[ 'chart_type'] : NULL;

			switch ( $chart_type ) {

				case Events_Chart_Model::CHART_TYPE:
					$chart_config = self::get_events_chart_config( $events_collection );
					break;

				case Items_Chart_Model::CHART_TYPE_ITEMS_BY_FIXER_STATION:
					$chart_config = self::get_items_chart_config( $events_collection );
					break;

				case Repairs_Chart_Model::CHART_TYPE_DETAILED:
					$chart_config = self::get_repairs_chart_config( $events_collection );
					break;

				case Visitors_Chart_Model::CHART_TYPE:
					$chart_config = self::get_visitors_chart_config( $events_collection );
					break;

				case Visitors_And_Volunteers_Chart_Model::CHART_TYPE:
					$chart_config = self::get_people_chart_config( $events_collection );
					break;

				case Volunteers_Chart_Model::CHART_TYPE_FIXERS_PER_EVENT:
					$chart_config = self::get_fixers_chart_config( $events_collection );
					break;

				case Volunteers_Chart_Model::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION:
					$chart_config = self::get_items_per_fixer_chart_config( $events_collection );
					break;

				case Volunteers_Chart_Model::CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE:
					$chart_config = self::get_visitors_per_volunteer_chart( $events_collection );
					break;

				case Volunteers_Chart_Model::CHART_TYPE_NON_FIXERS_PER_EVENT:
					$chart_config = self::get_non_fixers_chart_config( $events_collection );
					break;

				default:
					$form_response->add_error( 'chart_type', $chart_type, "Unknown chart type: \"$chart_type\"");
					break;

			} // endswitch

			if ( isset( $chart_config ) ) {
				$json_obj = $chart_config->jsonSerialize();
				$form_response->set_result_data( $json_obj );
			} // endif

		} // endif

		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function


	private static function get_events_chart_config( $events_collection ) {
		$chart = Events_Chart_Model::create_bar_chart_for_events_collection( $events_collection );
		$result = $chart->get_chart_config();
		return $result;
	} // function

	private static function get_items_chart_config( $events_collection ) {
		
		$chart_type = Items_Chart_Model::CHART_TYPE_ITEMS_BY_FIXER_STATION;
		$chart_model = Items_Chart_Model::create_bar_chart_for_events_collection( $events_collection, $chart_type );
		$result = $chart_model->get_chart_config();

		return $result;

	} // function


	private static function get_repairs_chart_config( $events_collection ) {

		$chart = Repairs_Chart_Model::create_detailed_bar_chart_for_events_collection( $events_collection );			
		$result = $chart->get_chart_config();
		
		return $result;

	} // function

	
	private static function get_fixers_chart_config( $events_collection ) {

		$chart_type = Volunteers_Chart_Model::CHART_TYPE_FIXERS_PER_EVENT;
		$chart_model = Volunteers_Chart_Model::create_bar_chart_for_events_collection( $events_collection, $chart_type );
		$result = $chart_model->get_chart_config();

		return $result;

	} // function


	private static function get_items_per_fixer_chart_config( $events_collection ) {

		$chart_type = Volunteers_Chart_Model::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION;
		$chart_model = Volunteers_Chart_Model::create_bar_chart_for_events_collection( $events_collection, $chart_type );
		$result = $chart_model->get_chart_config();
		
		return $result;

	} // function


	private static function get_non_fixers_chart_config( $events_collection ) {

		$chart_type = Volunteers_Chart_Model::CHART_TYPE_NON_FIXERS_PER_EVENT;
		$chart_model = Volunteers_Chart_Model::create_bar_chart_for_events_collection( $events_collection, $chart_type );
		$result = $chart_model->get_chart_config();

		return $result;

	} // function

	private static function get_visitors_chart_config( $events_collection ) {

		$chart_model = Visitors_Chart_Model::create_bar_chart_for_events_collection( $events_collection );
		$result = $chart_model->get_chart_config();
		
		return $result;

	} // function

	/**
	 * Get the chart
	 * @param	string[][]			$form_data
	 * @param	Ajax_Form_Response	$form_response
	 * @return NULL
	 */
	private static function get_visitors_per_volunteer_chart( $events_collection ) {

		$chart_type = Volunteers_Chart_Model::CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE;
		$chart_model = Volunteers_Chart_Model::create_bar_chart_for_events_collection( $events_collection, $chart_type );
		$result = $chart_model->get_chart_config();

		return $result;

	} // function

	private static function get_people_chart_config( $events_collection ) {

		$chart_model = Visitors_And_Volunteers_Chart_Model::create_bar_chart_for_events_collection( $events_collection );
		$result = $chart_model->get_chart_config();

		return $result;

	} // function


} // class