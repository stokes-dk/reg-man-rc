<?php

namespace Reg_Man_RC\View\Editable;

/**
 * An instance of this class is a renderable element containing text that can be modified via a popup editor
 * @author dave
 *
 */
class Popup_Editable {

	private $display_value;
	private $editor_form;
	private $class_array = array( 'reg-man-rc-popup-editable-container' );
	
	private function __construct() {
	} // function
	
	/**
	 * Create a new instance
	 * @param string $t$display_valueext
	 * @param Popup_Editor_Form $popup_editor_form
	 * @return Popup_Editable
	 */
	public static function create( $display_value, $popup_editor_form = NULL ) {
		$result = new self();
		$result->set_display_value( $display_value );
		$result->editor_form = $popup_editor_form;
		return $result;
	} // function

	/**
	 * Get the value to display
	 * @return string
	 */
	public function get_display_value() {
		return $this->display_value;
	} // function
	
	/**
	 * Set the value to be displayed
	 * @param string $value
	 */
	public function set_display_value( $value ) {
		$this->display_value = ! empty( $value ) ? $value : '';
	} // function
	
	/**
	 * Get the popup editor form
	 * @return Popup_Editor_Form
	 */
	public function get_editor_form() {
		return $this->editor_form;
	} // function
	
	/**
	 * Set the editor form
	 * @param Popup_Editor_Form $popup_editor_form
	 */
	public function set_editor_form( $popup_editor_form ) {
		$this->editor_form = $popup_editor_form;
	} // function
	
	private function get_class_names() {
		return implode( ' ', $this->get_class_array() );
	} // function

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
	 * Get the view contents
	 */
	public function get_content() {

		$classes = $this->get_class_names();
		$display_value = $this->get_display_value();
		$editor_form = $this->get_editor_form();
		
		$form_content = isset( $editor_form ) ? $editor_form->get_content() : '';

		$format = 		
			'<div class="%1$s">' .
				'<div class="reg-man-rc-popup-editable">%2$s</div>' .
				'%3$s' .
			'</div>';
		
		$result = sprintf( $format, $classes, $display_value, $form_content );
		
		return $result;

	} // function
		
	/**
	 * Render the view
	 */
	public function render() {

		$classes = $this->get_class_names();
		$display_value = $this->get_display_value();
		$editor_form = $this->get_editor_form();

		echo "<div class=\"$classes reg-man-rc-popup-editable-container\">";
			echo "<div class=\"reg-man-rc-popup-editable\">$display_value</div>";
			if ( isset( $editor_form ) ) {
				$editor_form->render();
			} // endif
		echo '</div>';

	} // function
		
} // class

