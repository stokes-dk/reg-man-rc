<?php
namespace Reg_Man_RC\View\Stats;

use Reg_Man_RC\Control\Ajax_Chart_View_Controller;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\View\Ajax_Form;

/**
 * An instance of this class provides a view for an AJAX chart or graph like a pie chart or bar chart.
 * These charts acquire their chart configuration via AJAX.
 *
 * @since v0.1.0
 *
 */
class Ajax_Chart_View {
	
	private $heading; // The heading for the view

	private $chart_type;
	private $classes = '';

	private function __construct() { }

	/**
	 * Create a new instance of this class
	 * @param string $heading
	 * @param string $chart_type
	 * @return Ajax_Chart_View
	 */
	public static function create( $heading, $chart_type = NULL ) {
		$result = new self();
		$result->heading = $heading;
		if ( isset( $chart_type ) ) {
			$result->chart_type = $chart_type;
		} // endif
		return $result;
	} // function
	
	private function get_heading() {
		return $this->heading;
	} // function

	private function get_chart_type() {
		return $this->chart_type;
	} // function

	private function get_classes() {
		return $this->classes;
	} // function

	/**
	 * Set the classes for the element
	 * @param string $classes	A space-separated list of class names for the element
	 */
	public function set_classes( $classes ) {
		$this->classes = $classes;
	} // function

	public function render() {
		$heading = $this->get_heading();
		$heading_html = esc_html( $heading );
		$classes = $this->get_classes();
		echo "<div class=\"reg-man-rc-ajax-chart-view $classes\">";
			echo '<div class="reg-man-rc-chart-loading-indicator spinner"></div>';
			if ( ! empty( $heading ) ) {
				echo '<div class="heading-container">';
					echo "<h4>$heading_html</h4>";
				echo '</div>';
			} // endif
			echo '<div class="reg-man-rc-chart-canvas-container">';
				echo '<canvas></canvas>';
			echo '</div>';

			$chart_type = $this->get_chart_type();
			self::render_ajax_form( $chart_type );
			
		echo '</div>';
	} // function

	private static function render_ajax_form( $chart_type ) {
		$action = Ajax_Chart_View_Controller::AJAX_GET_CHART_ACTION;
		$method = 'GET';

		// Autocomplete off blocks browser from autofilling the field from a chached page

		$fields_array = array();
		// Event
		$fields_array[] = '<input type="hidden" name="event-key" value="" autocomplete="off">';
		//Year
		$fields_array[] = '<input type="hidden" name="' . Event_Filter_Input_Form::YEAR_INPUT_NAME . '" value="" autocomplete="off">';
		// Category
		$fields_array[] = '<input type="hidden" name="' . Event_Filter_Input_Form::CATEGORY_INPUT_NAME . '" value="" autocomplete="off">';
		// Type
		$fields_array[] = '<input type="hidden" name="chart_type" value="' . esc_attr( $chart_type ) . '" autocomplete="off">';

		$content =  implode( '', $fields_array );
		
		$classes = 'reg-man-rc-get-chart-data-form no-busy'; // The no-busy class prevents the full-page busy indicator

		$ajax_form = Ajax_Form::create( $action, $method, $classes );
		$ajax_form->add_form_content( $content );
		$ajax_form->render();
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface on the frontend if the shortcode is present
	 *
	 * This method is called automatically when scripts are enqueued.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		global $post;
		if ( $post instanceof \WP_Post ) {
			if ( has_shortcode( $post->post_content, self::ITEMS_FIXED_SHORTCODE ) ) {
				Scripts_And_Styles::enqueue_stats_view_script_and_styles();
			} // endif
		} // endif
	} // function

} // class