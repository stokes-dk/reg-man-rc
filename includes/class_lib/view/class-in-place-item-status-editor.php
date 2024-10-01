<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\View\Pub\Single_Item_Details_View;

/**
 *  This class provides an in-place editor for an item's status
 */

class In_Place_Item_Status_Editor {
	
	private $item; // the event being viewed

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param Item $item
	 * @return In_Place_Item_Status_Editor
	 */
	public static function create( $item ) {
		$result = new self();
		$result->item = $item;
		return $result;
	} // function

	/**
	 * Get the item whose status being shown
	 * @return Item
	 */
	private function get_item() {
		return $this->item;
	} // function
	
	/**
	 * Get the contents for this view
	 */
	public function get_contents() {

		ob_start();
		
			$this->render();
			
		$result = ob_get_clean();
		
		return $result;

	} // function
	
	/**
	 * Render this view
	 */
	public function render() {

		$item = $this->get_item();
		$item_id = $item->get_id();
		$curr_status = $item->get_item_status();
		$selected_id = isset( $curr_status ) ? $curr_status->get_id() : 0;
		
		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Visitor_Registration_Controller::ITEM_STATUS_UPDATE_AJAX_ACTION;
		
		$options = Item_Status::get_item_status_options();
		
		echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\" class=\"reg-man-rc-in-place-editor-form\">";
	
			wp_nonce_field( $ajax_action );
			
			echo "<input type=\"hidden\" name=\"item-id\" value=\"$item_id\">";
			
			echo "<select class=\"reg-man-rc-in-place-editor\" name=\"item-status\" autocomplete=\"off\">";

				$option_format = '<option value="%1$s" %3$s>%2$s</option>';

				foreach( $options as $group_label => $option_group ) {

					echo "<optgroup label=\"$group_label\">";
					
						foreach( $option_group as $status_name => $status_id ) {
							
							$selected = ( $status_id == $selected_id ) ? 'selected="selected"' : '';
							printf( $option_format, $status_id, esc_html( $status_name ), $selected );
							
						} // endfor

					echo '</optgroup>';

				} // endfor

			echo '</select>';
		
		echo '</form>';

	} // function
} // class
