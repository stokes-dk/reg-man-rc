<?php

namespace Reg_Man_RC\View\Forms;

class Form_Component_List extends Abstract_Form_Component {

	private $components_array = array();
	
	private function __construct() {
	} // function
	
	/**
	 * Create a new instance of this class
	 * @param string $class		An optional class or space-separated list of class names to be applied to the set
	 * @param string $id		An optional ID for the component list element
	 * @return Form_Component_List
	 */
	public static function create( $class = NULL, $id = NULL ) {
		$result = new self();
		$result->add_class( 'reg-man-rc-form-component-list' );
		$result->add_class( $class );
		$result->id = $id;
		return $result;
	} // function
	
	/**
	 * Add a component to this list
	 * @param Form_Component $component
	 */
	public function add_component( $component ) {
		$this->components_array[] = $component;
	} // function
	
	/**
	 * Get the array of components in this list
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
		$item_format = '<li class="reg-man-rc-component-list-item">%1$s</li>';
		$list_item_array = array();
		foreach( $components_array as $component ) {
			$list_item_array[] = sprintf( $item_format, $component->get_content() );
		} // endfor
		$list_item_content = implode( '', $list_item_array );
		
		$result = "<ul $attrs_string>$list_item_content</ul>";
		
		return $result;
		
	} // function
	
} // function

