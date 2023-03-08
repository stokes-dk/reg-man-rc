<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Control\Chart_View_Controller;

/**
 * An instance of this class provides a view for a chart or graph like a pie chart or bar chart.
 *
 * @since v0.1.0
 *
 */
class Chart_View {

	private $heading; // The heading for the view

	private $object_type;
	private $organize_by;

	private function __construct() { }

	public static function create( $heading, $object_type = NULL, $organize_by = NULL ) {
		$result = new self();
		$result->heading = $heading;
		if ( isset( $object_type ) ) {
			$result->object_type = $object_type;
		} // endif
		if ( isset( $organize_by ) ) {
			$result->organize_by = $organize_by;
		} // endif
		return $result;
	} // function

	private function get_heading() {
		return $this->heading;
	} // function

	private function get_config() {
		return $this->config;
	} // function

	private function get_chart_type() {
		return isset( $this->config['type' ] ) ? $this->config[ 'type' ] : '';
	} // function

	private function get_object_type() {
		return $this->object_type;
	} // function

	public function set_object_type( $object_type ) {
		$this->object_type = $object_type;
	} // function

	private function get_organize_by() {
		return $this->organize_by;
	} // function

	public function set_organize_by( $organize_by ) {
		$this->organize_by = $organize_by;
	} // function

	public function render() {
		$heading = $this->get_heading();
		$heading_html = esc_html( $heading );
		$chart_type = $this->get_chart_type();
		echo '<div class="reg-man-rc-chart-view event-filter-change-listener" data-chart-type="' . esc_attr( $chart_type ) . '">';
			echo '<div class="reg-man-rc-chart-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$heading_html</h4>";
			echo '</div>';
			echo '<div class="reg-man-rc-chart-canvas-container">';
				echo '<canvas></canvas>';
			echo '</div>';
			$this->render_form();
		echo '</div>';
	} // function

	private function render_form() {
		$action = Chart_View_Controller::AJAX_GET_CHART_ACTION;
		$method = 'GET';

		$object_type = $this->get_object_type();
		$organize_by = $this->get_organize_by();

		 // Autocomplete off blocks browser from autofilling the field from a chached page
//		$scope_field = '<input type="hidden" name="scope" value="" autocomplete="off">';
//		$year_name =
		$year_field = '<input type="hidden" name="' . Event_Filter_Input_Form::YEAR_INPUT_NAME . '" value="" autocomplete="off">';
		$category_field = '<input type="hidden" name="' . Event_Filter_Input_Form::CATEGORY_INPUT_NAME . '" value="" autocomplete="off">';
		$type_field = '<input type="hidden" name="object_type" value="' . esc_attr( $object_type ) . '" autocomplete="off">';
		$organize_by_field = '<input type="hidden" name="organize_by" value="' . esc_attr( $organize_by ) . '" autocomplete="off">';
		$classes = 'reg-man-rc-get-chart-data-form no-busy'; // The no-busy class prevents the full-page busy indicator
		$content =  $year_field . $category_field . $type_field . $organize_by_field;

		$ajax_form = Ajax_Form::create( $action, $method, $classes );
		$ajax_form->add_form_content( $content );
		$ajax_form->render();
	} // function

} // class