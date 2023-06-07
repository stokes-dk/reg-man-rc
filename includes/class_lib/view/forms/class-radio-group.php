<?php

namespace Reg_Man_RC\View\Forms;

use Reg_Man_RC\Model\Error_Log;

class Radio_Group extends Abstract_Form_Component {

	private $name;
	private $options_array = array();
	private $selected;
	private $legend;
	private $is_required = FALSE;
	// TODO
	// private $custom_label, $custom_value;
	
	private function __construct() {
	} // function
	
	/**
	 * Create a new instance of this class
	 * @param string $name		The name to be used for the radio inputs inside the group
	 * @param string[] $options_array	An array of options labels and values, e.g. array( "Yes" => 1 );
	 * @param string $selected	An optional value for the radio to be selected initially, if any
	 * @param string $legend	An optional legend for the group
	 * @param string $class		An optional class or space-separated list of class names to be applied to the group
	 * @param string $id		An optional ID for the group element
	 * @return Radio_Group
	 */
	public static function create( $name, $options_array = array(), $selected = NULL, $legend = NULL, $class = NULL, $id = NULL ) {
		$result = new self();
		$result->name = $name;
		$result->options_array = is_array( $options_array ) ? $options_array : array();
		$result->selected = $selected;
		$result->legend = $legend;
		$result->add_class( 'reg-man-rc-radio-group' );
		$result->add_class( $class );
		$result->id = $id;
		return $result;
	} // function
	
	/**
	 * Get the input name
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	} // function
	
	/**
	 * Set the input name
	 * @param string	$name
	 */
	public function set_name( $name ) {
		$this->name = $name;
	} // function
	
	/**
	 * Get the array of name value pairs for the radio buttons
	 * @return string[]
	 */
	public function get_options_array() {
		return $this->options_array;
	} // function
	
	/**
	 * Set the array of name value pairs for the radio buttons
	 * @param string[] $options_array	An array of options labels and values, e.g. array( "Yes" => 1 );
	 */
	public function set_options_array( $options_array ) {
		$this->options_array = $options_array;
	} // function
	
	/**
	 * Add an option to the array of label / value pairs for the radio buttons
	 * @param string $label	The label for the radio button
	 * @param string $value	The value of the radio button
	 */
	public function add_option( $label, $value ) {
		$this->options_array[ $label ] = $value;
	} // function
	
	/**
	 * Get the value of the selected radio
	 * @return string
	 */
	public function get_selected() {
		return $this->selected;
	} // function
	
	/**
	 * Set the value of the selected radio
	 * @param string $selected	The value of the radio to be selected initially
	 */
	public function set_selected( $selected ) {
		$this->selected = $selected;
	} // function
	
	/**
	 * Get the legend
	 * @return string
	 */
	public function get_legend() {
		return $this->legend;
	} // function
	
	/**
	 * Set the legend
	 * @param string	$legend
	 */
	public function set_legend( $legend ) {
		$this->legend = $legend;
	} // function
	
	/**
	 * Set the required attribute for the radio group.
	 * This means that the 'required' attribute will be applied to each radio button in the group.
	 * @param boolean $is_required
	 */
	public function set_is_required( $is_required ) {
		$this->is_required = $is_required;
	} // function
	
	/**
	 * Get the array of radio inputs
	 * @return Form_Input[]
	 */
	private function get_radio_input_array() {
		$result = array();
		$name = $this->get_name();
		$options_array = $this->get_options_array();
		$selected = $this->get_selected();
		foreach( $options_array as $label => $value ) {
			$is_checked = ( $selected === $value );
			$radio = Form_Input::create_radio( $name, $value, $label, $is_checked );
			$result[] = $radio;
		} // endfor
		return $result;
	} // function

	/**
	 * Get the field as a string
	 * @return string
	 */
	public function get_content() {
		
		$attrs_array = $this->get_attributes_array();
		$attrs_string = self::get_attributes_as_string( $attrs_array );
		
		// Each radio will be a list item
		$radio_array = $this->get_radio_input_array();
		$item_format = '<li class="reg-man-rc-radio-group-item">%1$s</li>';
		$list_item_array = array();
		foreach( $radio_array as $radio_input ) {
			$list_item_array[] = sprintf( $item_format, $radio_input->get_content() );
		} // endfor
		$list_item_content = implode( '', $list_item_array );
		
		$list_content = "<ul class=\"reg-man-rc-radio-group-list\">$list_item_content</ul>";
		
		$legend = $this->get_legend();
		
		if ( empty( $legend ) ) {

			// There is no legend
			$result = "<div $attrs_string>$list_content</div>";
			
		} else {
			
			// Put the group inside a fieldset with a legend
			$result = 
				"<fieldset $attrs_string>" .
					"<legend>$legend</legend>" .
					$list_content .
				'</fieldset>';
			
		} // endif

		return $result;
		
	} // function
	
} // function

