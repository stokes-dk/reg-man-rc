<?php

namespace Reg_Man_RC\View\Forms;

class Form_Fieldset extends Abstract_Form_Component {

	private $legend;
	private $components_array = array();
	
	private function __construct() {
	} // function
	
	/**
	 * Create a new instance of this class
	 * @param string $legend	The legend for the fieldset
	 * @param string $class		An optional class or space-separated list of class names to be applied to the set
	 * @param string $id		An optional ID for the fieldset element
	 * @return Form_Fieldset
	 */
	public static function create( $legend, $class = NULL, $id = NULL ) {
		$result = new self();
		$result->legend = $legend;
		$result->add_class( 'reg-man-rc-form-fieldset' );
		$result->add_class( $class );
		$result->id = $id;
		return $result;
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
	 * Add a component to this set
	 * @param Form_Component $component
	 */
	public function add_component( $component ) {
		$this->components_array[] = $component;
	} // function
	
	/**
	 * Get the array of components in this set
	 * @return Form_Component[]
	 */
	public function get_components_array() {
		return $this->components_array;
	} // function
	
	
	/**
	 * Get the content as a string
	 * @return string
	 */
	public function get_content() {
		
		$attrs_array = $this->get_attributes_array();
		$attrs_string = self::get_attributes_as_string( $attrs_array );
		
		// Each component will be a list item
		$components_array = $this->get_components_array();
		$item_format = '<li class="reg-man-rc-fieldset-item">%1$s</li>';
		$list_item_array = array();
		foreach( $components_array as $component ) {
			$list_item_array[] = sprintf( $item_format, $component->get_content() );
		} // endfor
		$list_item_content = implode( '', $list_item_array );
		
		$list_content = "<ul class=\"reg-man-rc-fieldset-list\">$list_item_content</ul>";
		
		$legend = $this->get_legend();
		
		// Create the fieldset with a legend
		$result = 
			"<fieldset $attrs_string>" .
				"<legend>$legend</legend>" .
				$list_content .
			'</fieldset>';
			
		return $result;
		
	} // function
	
} // function

