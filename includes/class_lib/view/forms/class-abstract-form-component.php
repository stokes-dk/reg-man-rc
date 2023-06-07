<?php

namespace Reg_Man_RC\View\Forms;

abstract class Abstract_Form_Component implements Form_Component {
	
	protected $class_array = array( 'reg-man-rc-form-field' );
	protected $id;
	protected $addn_attrs_array = array();
	
	protected static $CURR_ID_NUM = 0; // used to generate unique ids
	
	/**
	 * Get the element class array
	 * @return string
	 */
	public function get_class_array() {
		return $this->class_array;
	} // function
	
	/**
	 * Add a class to the element
	 * @param string	$class
	 */
	public function add_class( $class ) {
		if ( ! empty( $class ) ) {
			$this->class_array[] = $class;
		} // endif
	} // function
	
	/**
	 * Generate an element ID
	 * @return	string	A unique element id
	 */
	protected static function generate_element_id() {
		$id_start = 'reg-man-rc-form-field-';
		return $id_start . self::$CURR_ID_NUM++;
	} // function
	
	/**
	 * Get the element id
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	} // function
	
	/**
	 * Set the element id
	 * @param string	$id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	} // function
	
	/**
	 * Get the specified list of attributes as a string, e.g. 'required="required" size="30"'
	 * @param array		$attributes_array	An associative array of name / value attribute pairs, e.g. array( 'size' => '30 )
	 */
	protected static function get_attributes_as_string( $attributes_array ) {

		 // I'll first create an array of attribute assignment strings like 'required="required"'
		$assignment_array = array();
		foreach( $attributes_array as $name => $value ) {
			$value = esc_attr( $value );
			$assignment_array[] = "$name=\"$value\""; 
		} // endfor
		
		// Then join them together with spaces
		$result = implode( ' ', $assignment_array );
		
		return $result;
		
	} // function
	
	/**
	 * Add an attribute
	 * @param string	$name	The attribute name
	 * @param string	$value	The attribute value
	 */
	public function add_attribute( $name, $value ) {
		$this->addn_attrs_array[ $name ] = $value;
	} // function
	
	/**
	 * Add an array of attribute names and values
	 * @param string[][]	An associative array of attribute names and values,
	 *  e.g array( 'required' => 'required', 'size => '30' )
	 */
	public function add_attributes_as_array( $attr_name_value_array ) {
		foreach( $attr_name_value_array as $name => $value ) {
			$this->addn_attrs_array[ $name ] = $value;
		} // endfor
	} // function

	/**
	 * Get the array of base attributes for this input (excluding additional
	 * @return array
	 */
	protected function get_attributes_array() {

		$result = array();

		$id = $this->get_id();
		$class_array = $this->get_class_array();
		
		if ( ! empty( $id ) ) {
			$result[ 'id' ] = $id;
		} // endif
		if ( ! empty( $class_array ) ) {
			$result[ 'class' ] = implode( ' ', $class_array );
		} // endif
		
		$result = array_merge( $result, $this->addn_attrs_array );
		
		return $result;
		
	} // function

	/**
	 * Magic method to convert the object to a string.
	 * This allows clients to treat the object as a string to do concatenation for example.
	 * @return string
	 */
	public function __toString() {
		return $this->get_content();
	} // function
	
} // class

