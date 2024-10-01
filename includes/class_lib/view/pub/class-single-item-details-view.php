<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item_Status;

class Single_Item_Details_View {

	private $event;
	private $item;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param Item		$item
	 * @return Single_Item_Details_View
	 */
	public static function create( $item = NULL ) {
		$result = new self();
		$result->item = $item;
		return $result;
	} // function

	/**
	 * Get the item
	 * @return Item
	 */
	private function get_item() {
		return $this->item;
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			
			echo '<h2 class="login-title">' . __('You must be logged in to use this form', 'reg-man-rc') . '</h2>';
			
		} else { // User is logged in so show the page content

			$this->render_load_item_update_form();
			
			$this->render_update_item_form();
			
		} // endif
		
	} // function
	
	private function render_load_item_update_form() {

		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Visitor_Registration_Controller::GET_ITEM_UPDATE_CONTENT_AJAX_ACTION;

		echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
				' class="visitor-reg-get-item-update-content-form reg-man-rc-ajax-form">';

			wp_nonce_field( $ajax_action );
		
			echo '<input type="hidden" name="item-id" value="">'; // Assigned on the client side
		
		echo '</form>';
		
	} // function
	
	private function render_update_item_form() {

		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Visitor_Registration_Controller::ITEM_UPDATE_AJAX_ACTION;

		echo '<div class="visitor-reg-form-container visitor-reg-update-item-form-container autocomplete-item-desc-container">';
		
			echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
					' class="visitor-reg-update-item-form visitor-reg-form reg-man-rc-ajax-form">';

				$close_icon = '<span class="dashicons dashicons-no"></span>';
				$cancel_text = __( 'Cancel', 'reg-man-rc' );
				$submit_text = __( 'Save', 'reg-man-rc' );
				
				$button_format = '<button type="%3$s" class="visitor-reg-form-%2$s reg-man-rc-button">%1$s</button>';
				$close_button = sprintf( $button_format, $close_icon, 'cancel', 'button' );
				$cancel_button = sprintf( $button_format, $cancel_text, 'cancel', 'button' );
				$submit_button = sprintf( $button_format, $submit_text, 'submit', 'submit' );
				
				echo '<div class="visitor-reg-form-header">';
					$subtitle = __( 'Update Item', 'reg-man-rc' );
					echo '<h3 class="visitor-reg-manager-subtitle">' . $subtitle . '</h3>';
					echo $close_button;
				echo '</div>';
				
				wp_nonce_field( $ajax_action );
				
				echo '<div class=visitor-reg-upate-item-inputs-container>';
				
					// The contents will be loaded from the client side when an item is selected

				echo '</div>';
				
				echo '<div class="visitor-reg-form-buttons-section visitor-reg-form-section">';
					echo $cancel_button;
					echo $submit_button;
				echo '</div>';
				
			echo '</form>';
		
		echo '</div>';
		
	} // function
	
	/**
	 * Get the contents of the form to update an item
	 */
	public function get_update_item_form_contents() {
		
		ob_start();
			$item = $this->get_item();
			
			if ( empty( $item ) ) {
	
				$msg = __( 'The item was not found', 'reg-man-rc' );
				echo '<div class="visitor-reg-name-section visitor-reg-form-section">';
					echo '<h4 class="visitor-details-name">' . $msg . '</h4>';
				echo '</div>';
				
			} else {
				
				$item_id = $item->get_id();
				$visitor = $item->get_visitor();
				$visitor_name = isset( $visitor ) ? $visitor->get_public_name() : '';
				echo '<div class="visitor-reg-name-section visitor-reg-form-section">';
					echo '<h4 class="visitor-details-name">' . $visitor_name . '</h4>';
				echo '</div>';
				
				echo "<input type=\"hidden\" name=\"item-id\" value=\"$item_id\">";
				
				echo '<div class="visitor-reg-items-section visitor-reg-form-section">';
					$this->render_item_inputs();
				echo '</div>';
	
			} // endif
		$result = ob_get_clean();
		
		return $result;
		
	} // function

	
	private function render_item_inputs() {

		$item = $this->get_item();
		$item_desc = $item->get_item_description();
		$fixer_station = $item->get_fixer_station();
		$item_type = $item->get_item_type();
		$item_status = $item->get_item_status();
		
		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );
		
		$fieldset = Form_Input_List::create();
		$fieldset->set_style_compact();
		$fieldset->set_required_inputs_flagged( FALSE );
		
		// Note that when this view is rendered on the server side there is no item assigned
		// The selected values for the inputs will be assigned on the client side
		
		$label = __( 'Fixer Station', 'reg-man-rc' );
		$name = 'fixer-station';
		$options = self::get_fixer_station_options();
		$selected = isset( $fixer_station ) ? $fixer_station->get_id() : '';
		$hint = '';
		$classes = '';
		$required = TRUE;
		$fieldset->add_select_input( $label, $name, $options, $selected, $hint, $classes, $required );
		
		$label = __( 'Item Type', 'reg-man-rc' );
		$name = 'item-type';
		$options = self::get_item_type_options();
		$selected = isset( $item_type ) ? $item_type->get_id() : '';
		$hint = '';
		$classes = '';
		$required = TRUE;
		$fieldset->add_select_input( $label, $name, $options, $selected, $hint, $classes, $required );
		
		$label = __( 'Status / Outcome', 'reg-man-rc' );
		$name = 'item-status';
		$options = Item_Status::get_item_status_options();
		$selected = isset( $item_status ) ? $item_status->get_id() : '';
		$hint = '';
		$classes = '';
		$required = TRUE;
		$fieldset->add_select_input( $label, $name, $options, $selected, $hint, $classes, $required );
		
		$hint = '';
		$classes = 'visitor-reg-item-details-fieldset';
		$input_list->add_fieldset( $item_desc, $fieldset, $hint, $classes );
		
		$input_list->render();

	} // function
	
	private static function get_fixer_station_options() {
	
		$result = array();
		
		$all_stations = Fixer_Station::get_all_fixer_stations();
		foreach( $all_stations as $fixer_station ) {
			$id = $fixer_station->get_id();
			$name = $fixer_station->get_name();
			$result[ $name ] = $id;
		} // endfor
		
		return $result;
		
	} // function
	
	private static function get_item_type_options() {
	
		$result = array();
		
		$all_types = Item_Type::get_all_item_types();
		foreach( $all_types as $item_type ) {
			$id = $item_type->get_id();
			$name = $item_type->get_name();
			$result[ $name ] = $id;
		} // endfor
		
		return $result;
		
	} // function
	

} // class
?>