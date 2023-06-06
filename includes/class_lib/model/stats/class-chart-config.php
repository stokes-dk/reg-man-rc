<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class is a json serializable configuration object
 *  which describes a graphical chart like a pie chart or bar chart.
 *
 * @since v0.1.0
 *
 */
class Chart_Config implements \JsonSerializable {

	private $type;
	private $is_stacked = FALSE; // A flag used to indicate a stacked bar chart

	private $title;
	private $subtitle;
	private $labels = array();
	private $datasets = array();
	private $options = array();
	private $animation;

	private function __construct() { }

	public static function create_stacked_bar_chart() {
		$result = new self();
		$result->type = 'bar';
		$result->is_stacked = TRUE;
		return $result;
	} // function

	public static function create_bar_chart() {
		$result = new self();
		$result->type = 'bar';
		return $result;
	} // function

	public static function create_pie_chart() {
		$result = new self();
		$result->type = 'pie';
		return $result;
	} // function

	private function get_type() {
		return $this->type;
	} // function

	public function set_title( $title ) {
		$this->title = $title;
	} // function

	private function get_title() {
		return $this->title;
	} // function

	public function set_subtitle( $subtitle ) {
		$this->subtitle = $subtitle;
	} // function

	private function get_subtitle() {
		return $this->subtitle;
	} // function

	private function get_labels() {
		return $this->labels;
	} // function

	public function set_labels( $labels_array ) {
		$this->labels = $labels_array;
	} // function

	private function get_datasets() {
		return $this->datasets;
	} // function

	public function add_dataset( $dataset ) {
		$this->datasets[] = $dataset;
	} // function

	private function get_options() {
		return $this->options;
	} // function

	/**
	 * Set an option value
	 * @param	string	$name
	 * @param	mixed	$value
	 */
	public function set_option( $name, $value ) {
		$this->options[ $name ] = $value;
	} // function

	/**
	 * Set the font size in pixels
	 * @param	int		$size
	 */
	public function set_font_size( $size ) {
		$this->options[ 'plugins' ][ 'legend' ][ 'labels' ][ 'font' ][ 'size' ] = intval( $size );
	} // function

	/**
	 * Set the font weight
	 * @param	string		$weight
	 */
	public function set_font_weight( $weight ) {
		$this->options[ 'plugins' ][ 'legend' ][ 'labels' ][ 'font' ][ 'weight' ] = $weight;
	} // function

	/**
	 * Set whether or not the legend is displayed
	 * @param	boolean		$is_display_legend
	 */
	public function set_is_display_legend( $is_display_legend ) {
		$this->options[ 'plugins' ][ 'legend' ][ 'display' ] = boolval( $is_display_legend );
	} // function

	/**
	 * Set whether or not a tooltip is displayed when hovering over the chart
	 * @param	boolean		$is_display_tooltips
	 */
	public function set_is_display_tooltip( $is_display_tooltip ) {
		$this->options[ 'plugins' ][ 'tooltip' ][ 'enabled' ] = boolval( $is_display_tooltip );
	} // function

	/**
	 * Get the animation object
	 * @return Chart_Animation
	 */
	private function get_animation() {
		return $this->animation;
	} // function

	/**
	 * Set the animation object
	 * @param Chart_Animation	$animation
	 */
	public function set_animation( $animation ) {
		$this->animation = $animation;
	} // function

	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string[][]	An associative array of chart attributes including type, data and options.
	 * @since v0.1.0
	 */
	public function jsonSerialize() : array {
		$type = $this->get_type();

		$options = $this->get_options();
		$options[ 'responsive' ] = TRUE;
		
		$animation = $this->get_animation();
		if ( isset( $animation ) && ( $animation instanceof Chart_Animation ) ) {
			$options[ 'animation' ] = $animation;
		} // endif

		$title = $this->get_title();
		if ( ! empty( $title ) ) {
			$options[ 'plugins' ][ 'title' ] = array(
				'display'		=> TRUE,
				'text'			=> $title,
			);
		} // endif

		$subtitle = $this->get_subtitle();
		if ( ! empty( $subtitle ) ) {
			if ( ! isset( $options[ 'plugins' ] ) ) {
				$options[ 'plugins' ] = array();
			} // endif
			$options[ 'plugins' ][ 'subtitle' ] = array(
				'display'		=> TRUE,
				'text'			=> $subtitle,
			);
		} // endif

		$labels = $this->get_labels();

		$datasets = $this->get_datasets();

		if ( $this->type == 'bar' ) {
			if ( $this->is_stacked ) {
				$options[ 'scales' ] = array(
						'x'			=> array( 'stacked' => TRUE ),
						'y'			=> array( 'stacked' => TRUE ),
	//					'y'			=> array( 'beginAtZero' => TRUE ),
				);
				$options[ 'interaction' ] = array( 'intersect' => FALSE );
			} // endif
			$options[ 'maintainAspectRatio' ] = FALSE;
		} // endif
		
		$result = array(
			'type'		=> $type,
			'data'		=> array(
					'labels'	=> $labels,
					'datasets'	=> $datasets,
			),
			'options'	=> $options,
			'plugins'
		);
		
//		Error_Log::var_dump( $result );
		return $result;
		
	} // function
} // class