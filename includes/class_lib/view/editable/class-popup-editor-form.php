<?php
namespace Reg_Man_RC\View\Editable;

use Reg_Man_RC\View\Forms\Form_Component;

/**
 * An editor form that appears inside a popup
 *
 */
class Popup_Editor_Form {

//	private $content; // the form's content
	private $ajax_action; // The ajax action to receive the form when it is submitted
	private $class_array = array( 'reg-man-rc-popup-editor-form' );
	private $components_array = array();
	
	private static $CURR_ID_NUM = 0; // A counter used to generate unique IDs for elements

	private function __construct() { }

	public static function create( $ajax_action ) {
		$result = new self();
		$result->ajax_action = $ajax_action;
		return $result;
	} // function

	/**
	 * Generate an input ID
	 * @return	string	A unique input id
	 */
	private static function generate_id() {
		$id_start = 'reg-man-rc-popup-editor-form-';
		return $id_start . self::$CURR_ID_NUM++;
	} // function
	
	private function get_ajax_action() {
		return $this->ajax_action;
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
	 * Add a component to this list
	 * @param Form_Component $component
	 */
	public function add_form_component( $component ) {
		$this->components_array[] = $component;
	} // function
	
	/**
	 * Get the array of components in this list
	 * @return Form_Component[]
	 */
	public function get_form_components_array() {
		return $this->components_array;
	} // function
	
	/**
	 * Render the view
	 */
	public function render() {

		echo $this->get_content();

	} // function
	
	/**
	 * Render the view
	 */
	public function get_content() {

		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = $this->get_ajax_action();
		$classes = $this->get_class_names();
		$form_classes = 'reg-man-rc-popup-editor-form';
		$form_content = $this->get_form_content();
		
		$format = 
			'<div class="%3$s reg-man-rc-popup-editor">' .
				'<form action="%1$s" method="POST" data-ajax-action="%2$s" class="%4$s">%5$s</form>' .
			'</div>';
		
		$result = sprintf( $format, $form_action, $ajax_action, $classes, $form_classes, $form_content );

		return $result;

	} // function
	
	private function get_form_content() {
		
		$components_array = $this->get_form_components_array();
		$content_array = array();
		foreach( $components_array as $component ) {
			$content_array[] = $component->get_content();
		} // endfor
		$content = implode( '', $content_array );
		
		return $content;
		
	} // function

} // class
