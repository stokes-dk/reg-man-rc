<?php

namespace Reg_Man_RC\View\Forms;

class Form_Input extends Abstract_Form_Component {
	
	private $type;
	private $name;
	private $value = '';
	private $label;
	
	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @param string $type
	 * @param string $name
	 * @param string $value
	 * @param string $label
	 * @param string $class
	 * @param string $id
	 * @return Form_Input
	 */
	public static function create( $type, $name, $value = NULL, $label = NULL, $class = NULL, $id = NULL ) {
		$result = new self();
		$result->type = $type;
		$result->name = $name;
		$result->value = $value;
		$result->label = $label;
		$result->add_class( $class );
		$result->id = $id;
		$result->add_attribute( 'autocomplete', 'off' ); // So sick of this thing!!!
		return $result;
	} // function

	/**
	 * Create a new text input
	 * @param string $name
	 * @param string $value
	 * @param string $label
	 * @param string $class
	 * @param string $id
	 * @return Form_Input
	 */
	public static function create_text( $name, $value = NULL, $label = NULL, $class = NULL, $id = NULL ) {
		$type = 'text';
		$result = self::create( $type, $name, $value, $label, $class, $id );
		return $result;
	} // function
	
	/**
	 * Create a new hidden input
	 * @param string $name
	 * @param string $value
	 * @param string $class
	 * @param string $id
	 * @return Form_Input
	 */
	public static function create_hidden( $name, $value = NULL, $class = NULL, $id = NULL ) {
		$type = 'hidden';
		$result = self::create( $type, $name, $value, $label = NULL, $class, $id );
		return $result;
	} // function
	
	/**
	 * Create a new radio input
	 * @param string $name
	 * @param string $value
	 * @param string $label
	 * @param string $class
	 * @param string $id
	 * @return Form_Input
	 */
	public static function create_radio( $name, $value, $label, $is_checked = FALSE, $class = NULL, $id = NULL ) {
		$type = 'radio';
		$result = self::create( $type, $name, $value, $label, $class, $id );
		$result->add_class( 'reg-man-rc-radio-input' );
		if ( $is_checked ) {
			$result->add_attribute( 'checked', "checked" );
		} // endif
		return $result;
	} // function
	
	/**
	 * Get the input type
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	} // function
	
	/**
	 * Set the input type
	 * @param string	$type
	 */
	public function set_type( $type ) {
		$this->type = $type;
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
	 * Get the input value
	 * @return string
	 */
	public function get_value() {
		return $this->value;
	} // function
	
	/**
	 * Set the input value
	 * @param string	$value
	 */
	public function set_value( $value ) {
		$this->value = $value;
	} // function
	
	/**
	 * Get the input label
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	} // function
	
	/**
	 * Set the input label
	 * @param string	$label
	 */
	public function set_label( $label ) {
		$this->label = $label;
	} // function
	
	/**
	 * Set the required attribute for this input
	 * @param boolean $is_required
	 */
	public function set_is_required( $is_required ) {
		if ( $is_required ) {
			$this->addn_attrs_array[ 'required' ] = 'required';
		} else {
			unset( $this->addn_attrs_array[ 'required' ] );
		} // endif
	} // function

	/**
	 * Get the input input content as a string, e.g. '<input type="text"></input>'
	 * @return string
	 */
	public function get_content() {

		$type = $this->get_type();
		switch( $type ) {
			
			case 'radio':
				$result = $this->get_radio_input_as_string();
				break;
				
			default:
				$result = $this->get_basic_input_as_string();
				break;

		} // endswitch

		return $result;
		
	} // function

	/**
	 * Get the array of base attributes for this input (excluding additional
	 * @return array
	 */
	protected function get_attributes_array() {
		
		$result = parent::get_attributes_array();
		$type = $this->get_type();
		$name = $this->get_name();
		$value = $this->get_value();
		
		$result[ 'type' ]	= $type;
		$result[ 'name' ]	= $name;
		if ( ! empty( $value ) ) {
			$result[ 'value' ] = $value;
		} // endif
		
		return $result;
	} // function
	
	/**
	 * Get a basic input element as a string, e.g. '<input type="text"></input>'
	 * @return string
	 */
	private function get_basic_input_as_string() {

		$basic_attrs = $this->get_attributes_array();
		$attrs_string = self::get_attributes_as_string( $basic_attrs );
		
		$label = $this->get_label();
		
		if ( empty( $label ) ) {

			// There is no label
			$result = "<input $attrs_string/>";
			
		} else {
			
			// Include a label
			$id = $this->get_id();
			if ( empty( $id ) ) {
				
				// In this case there is no id in the attributes string so we must add one
				$id = self::generate_element_id();
				$format = '<label for="%1$s">%3$s</label><input id="%1$s" %2$s/>';
				
			} else {
				
				// In this case the attributes already include an id
				$format = '<label for="%1$s">%3$s</label><input %2$s/>';
				
			} // endif
			
			$result = sprintf( $format, $id, $attrs_string, $label );
			
		} // endif

		return $result;
		
	} // function

	/**
	 * Get a radio input element as a string, 
	 *   e.g. '<label for="radio-1">Test</label><input id="radio-1" type="radio" name="test" value="x" checked="checked">'
	 * @return string
	 */
	private function get_radio_input_as_string() {
		
		$basic_attrs = $this->get_attributes_array();
		$attrs_string = self::get_attributes_as_string( $basic_attrs );
		
		$label = $this->get_label();
		$id = $this->get_id();
		if ( empty( $id ) ) {
			$id = self::generate_element_id(); // I need an id for the label
		} // endif
		
		$format = '<input id="%1$s" %2$s/><label class="reg-man-rc-radio-label" for="%1$s">%3$s</label>';
		$result = sprintf( $format, $id, $attrs_string, $label );
			
		return $result;
		
	} // function

} // class

