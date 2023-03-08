<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An instance of this class a dataset for a chart
 *
 * @since v0.1.0
 *
 */
class Chart_Dataset implements \JsonSerializable {

	private $label = '';
	private $colours = array();
	private $data = array();
	private $stack = NULL;

	private function __construct() { }

	public static function create( $label ) {
		$result = new self();
		$result->label = $label;
		return $result;
	} // function

	private function get_label() {
		return $this->label;
	} // function

	public function set_label( $label ) {
		$this->label = $label;
	} // function

	private function get_colours() {
		return $this->colours;
	} // function

	public function set_colours( $colours_array ) {
		$this->colours = $colours_array;
	} // function

	private function get_data() {
		return $this->data;
	} // function

	private function get_stack() {
		return $this->stack;
	} // function

	public function set_stack( $stack ) {
		$this->stack = $stack;
	} // function

	public function add_datapoint( $colour, $data ) {
		$this->colours[] = $colour;
		$this->data[] = $data;
	} // function

	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string[][]	An associative array of chart attributes including type, data and options.
	 * @since v0.1.0
	 */
	public function jsonSerialize() {

		$colours = $this->get_colours();
		$data = $this->get_data();

		$dataset_label = $this->get_label();

		$result = array(
				'label'				=> $dataset_label,
				'data'				=> $data,
				'backgroundColor'	=> $colours,
		);

		$stack = $this->get_stack();
		if ( ! empty( $stack ) ) {
			$result[ 'stack' ]	= $stack;
		} // endif

		return $result;
	} // function
} // class