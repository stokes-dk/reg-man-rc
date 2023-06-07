<?php
namespace Reg_Man_RC\View\Editable;

use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\View\Forms\Radio_Group;
use Reg_Man_RC\View\Forms\Form_Input;

/**
 * An editor form that appears inside a popup
 *
 */
class Editable_Item_Type {

	private $ajax_action; // The ajax action for the form
	private $item_id_input; // The input for the id of the item whose type will be modified
	
	private $editable;
	private $editor_form;
	private $radio_group;

	private function __construct() { }

	/**
	 * Create an instance of this class to display and edit the item type for an item
	 * @param string $ajax_action	The action attribute for the form to indicate where it will be submitted
	 * @return Editable_Item_Type
	 */
	public static function create( $ajax_action ) {
		$result = new self();
		$result->ajax_action = $ajax_action;
		return $result;
	} // function
	
	private function get_ajax_action() {
		return $this->ajax_action;
	} // function
	
	/**
	 * Set the current item type to be displayed
	 * @param Item_Type $item_type
	 */
	public function set_item_type( $item_type ) {
		$editable = $this->get_editable();
		$id = isset( $item_type ) ? $item_type->get_id() : '';
		$text = isset( $item_type ) ? $item_type->get_name() : '';
		$editable->set_display_value( $text );
		$radio_group = $this->get_radio_group();
		$radio_group->set_selected( $id );
	} // function
	
	/**
	 * Set the ID of the object to be modified
	 * @param string $item_id
	 */
	public function set_item_id( $item_id ) {
		$input = $this->get_item_id_input();
		$input->set_value( $item_id );
	} // function
	
	private function get_item_id_input() {
		if ( ! isset( $this->item_id_input ) ) {
			$this->item_id_input = Form_Input::create_hidden( 'item-id' );
		} // endif
		return $this->item_id_input;
	} // function
	
	private function get_radio_group() {
		if ( ! isset( $this->radio_group ) ) {
			$options_array = array();
			$item_types_array = Item_Type::get_all_item_types();
			foreach( $item_types_array as $item_type ) {
				$id = $item_type->get_id();
				$name = $item_type->get_name();
				$options_array[ $name ] = $id;
			} // endfor
			$this->radio_group = Radio_Group::create( 'item-type', $options_array );
		} // endif
		return $this->radio_group;
	} // function
	
	/**
	 * Get the editable object
	 * @return Popup_Editable
	 */
	private function get_editable() {
		if ( ! isset( $this->editable ) ) {
			$this->editable = Popup_Editable::create( '', $this->get_editor_form() );
		} // endif
		return $this->editable;
	} // function

	/**
	 * Get the editor form
	 * @return Popup_Editor_Form
	 */
	private function get_editor_form() {
		if ( ! isset( $this->editor_form ) ) {
			$this->editor_form = Popup_Editor_Form::create( $this->get_ajax_action() );
			$this->editor_form->add_form_component( $this->get_item_id_input() );
			$this->editor_form->add_form_component( $this->get_radio_group() );
		} // endif
		return $this->editor_form;
	} // function

	/**
	 * Render the view
	 */
	public function render() {

		echo $this->get_content();

	} // function
	
	/**
	 * Get the content
	 * @return string
	 */
	public function get_content() {

		$editable = $this->get_editable();
		$result = $editable->get_content();
		
		return $result;
		
	} // function
	
} // class
