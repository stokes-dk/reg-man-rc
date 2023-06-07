<?php
namespace Reg_Man_RC\View\Editable;

use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\View\Forms\Radio_Group;
use Reg_Man_RC\View\Forms\Form_Input;

/**
 * An editor form that appears inside a popup
 *
 */
class Editable_Item_Status {

	private $ajax_action; // The ajax action for the form
	private $item_id_input; // The input for the id of the item whose status will be modified
	
	private $editable;
	private $editor_form;
	private $radio_group;

	private function __construct() { }

	/**
	 * Create an instance of this class to display and edit the item status for an item
	 * @param string $ajax_action	The action attribute for the form to indicate where it will be submitted
	 * @return Editable_Item_Status
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
	 * Get the display value for the specified item status
	 * @param Item_Status $item_status
	 * @return string
	 */
	public static function get_display_value_for( $item_status ) {
		if ( ! isset( $item_status ) ) {
			$result = '';
		} else {
			$result = ( $item_status->get_id() !== Item_Status::REGISTERED ) ? $item_status->get_name() : '';
		} // endif
		return $result;
	} // endif
	
	/**
	 * Set the current item status to be displayed
	 * @param Item_Status $item_status
	 */
	public function set_item_status( $item_status ) {
		$editable = $this->get_editable();
		$id = isset( $item_status ) ? $item_status->get_id() : '';
		$value = self::get_display_value_for( $item_status );
		$editable->set_display_value( $value );
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
			$item_status_array = Item_Status::get_all_item_statuses();
			
			foreach( $item_status_array as $item_status ) {
			
				$id = $item_status->get_id();
				// Move registered to the bottom
				if ( $id !== Item_Status::REGISTERED ) {
					$name = $item_status->get_name();
					$options_array[ $name ] = $id;
				} // endif
				
			} // endfor
			
			// Move registered to the bottom
//			$registered_status = Item_Status::get_item_status_by_id( Item_Status::REGISTERED );
//			$name = $registered_status->get_name();
			$name = __( 'Repair outcome not reported', 'reg-man-rc' );
			$options_array[ $name ] = Item_Status::REGISTERED;
			
			$this->radio_group = Radio_Group::create( 'item-status', $options_array );
			
		} // endif
		
		return $this->radio_group;
		
	} // function
	
	/**
	 * Get the editable object
	 * @return Popup_Editable
	 */
	private function get_editable() {
		if ( ! isset( $this->editable ) ) {
			$editable = Popup_Editable::create( '', $this->get_editor_form() );
			$this->editable = $editable;
			$editable->add_class( 'reg-man-rc-editable-item-status' );
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
