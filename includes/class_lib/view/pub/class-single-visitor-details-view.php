<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\View\In_Place_Item_Status_Editor;

class Single_Visitor_Details_View {

	private $event;
	private $visitor;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param Event		$event
	 * @param Visitor	$visitor
	 * @return Single_Visitor_Details_View
	 */
	public static function create( $event, $visitor = NULL ) {
		$result = new self();
		$result->event = $event;
		$result->visitor = $visitor;
		return $result;
	} // function

	/**
	 * Get the event
	 * @return Event
	 */
	private function get_event() {
		return $this->event;
	} // function

	/**
	 * Get the visitor
	 * @return Visitor
	 */
	private function get_visitor() {
		return $this->visitor;
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			
			echo '<h2 class="login-title">' . __('You must be logged in to use this form', 'reg-man-rc') . '</h2>';
			
		} else { // User is logged in so show the page content
			
			echo '<div class="visitor-reg-form-container visitor-reg-update-visitor-form-container">';

				$this->render_view_contents();
				
			echo '</div>';
			
		} // endif
		
	} // function
	
	/**
	 * Render the contents of the form to add an item to the visitor
	 */
	private function render_view_contents() {
		// These are the contents of the view that are shown when the page is initially rendered
		
		$close_icon = '<span class="dashicons dashicons-no"></span>';
		
		$button_format = '<button type="%3$s" class="visitor-reg-form-%2$s reg-man-rc-button">%1$s</button>';
		$close_button = sprintf( $button_format, $close_icon, 'cancel', 'button' );
		
		echo '<div class="visitor-reg-form-header">';
			$subtitle = __( 'Update Visitor', 'reg-man-rc' );
			echo '<h3 class="visitor-reg-manager-subtitle">' . $subtitle . '</h3>';
			echo $close_button;
		echo '</div>';

		// Name
		echo '<div class="visitor-reg-name-section visitor-reg-form-section">';
			echo '<h4 class="visitor-details-name"></h4>';
		echo '</div>';
		
		// Items list
		echo '<div class="visitor-reg-visitor-item-list-section visitor-reg-form-section datatable-container">';
			echo '<div class="reg-man-rc-visitor-reg-busy"></div>'; // busy indicator
			$this->render_items_list();
		echo '</div>';
			
		// Add Item
		echo '<div class="visitor-reg-visitor-item-add-section visitor-reg-form-section">';
			$this->render_add_item_to_visitor_form();
		echo '</div>';
			
	} // function

	private function render_items_list() {
		
		$event = $this->get_event();
		$event_key = $event->get_key_string();

//		$class_names = 'visitor-reg-visitor-items-table';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Visitor_Registration_Controller::GET_VISITOR_ITEMS_LIST_AJAX_ACTION;
		$nonce = wp_create_nonce( $ajax_action );

		echo "<table class=\"display visitor-reg-visitor-items-table\" sytle=\"width:100%;\"" .
				" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" data-event-key=\"$event_key\"" .
				" data-ajax-nonce=\"$nonce\">";

	// Item Desc, Fixer Station, Status (editable), Status Type (for grouping), Status Order (for sorting)
	
			$row_format =
				'<tr>' .
					'<%1$s class="item-desc col-not-sortable">%2$s</%1$s>' .
					'<%1$s class="item-fixer-station col-not-sortable">%3$s</%1$s>' .
					'<%1$s class="item-status col-not-sortable">%4$s</%1$s>' .
					'<%1$s class="item-status-type col-hidden">%5$s</%1$s>' .
					'<%1$s class="item-status-order col-hidden">%6$s</%1$s>' .
					'<%1$s class="item-status-type-order col-hidden">%7$s</%1$s>' .
				'</tr>';
			echo '<thead>';
				printf(	$row_format, 'th',
						__( 'Item', 'reg-man-rc' ),
						__( 'Fixer Station', 'reg-man-rc' ),
						__( 'Status / Outcome', 'reg-man-rc' ),
						__( 'Status type', 'reg-man-rc' ), // hidden but used for grouping
						__( 'Status order', 'reg-man-rc' ), // hidden but used for ordering by status
						__( 'Status type order', 'reg-man-rc' ), // hidden but used for ordering by status
						);
			echo '</thead>';
			echo '<tbody>';
				// We are loading the table data using ajax rather than at page load
			echo '</tbody>';
		echo '</table>';
		
	} // function
	
	/**
	 * Get the visitor item list data
	 * @return string[]
	 */
	public function get_visitor_item_list_data() {
		$result = array();
		
		$event = $this->get_event();
		$visitor = $this->get_visitor();
		$visitor_id = $visitor->get_id();
		$event_key = $event->get_key_string();
		$items_array = Item::get_items_registered_by_visitor_for_event( $visitor_id, $event_key );
		
		$active_label = Item_Status::get_active_status_label();
		$complete_label = Item_Status::get_complete_status_label();
		$inactive_label = Item_Status::get_inactive_status_label();
		$status_types_array = array(
				Item_Status::IN_QUEUE			=> $active_label,
				Item_Status::IN_PROGRESS		=> $active_label,
				Item_Status::DONE_UNREPORTED	=> $complete_label,
				Item_Status::FIXED				=> $complete_label,
				Item_Status::REPAIRABLE			=> $complete_label,
				Item_Status::END_OF_LIFE		=> $complete_label,
				Item_Status::STANDBY			=> $inactive_label,
				Item_Status::WITHDRAWN			=> $inactive_label,
		);

		// This will establish the order type groups are displayed
		$status_types_order_array = array(
				Item_Status::get_active_status_label(),
				Item_Status::get_complete_status_label(),
				Item_Status::get_inactive_status_label(),
		);
		
		// This will establish the order items are displayed inside a group
		$status_order_array = array(
				Item_Status::IN_QUEUE,
				Item_Status::IN_PROGRESS,
				Item_Status::DONE_UNREPORTED,
				Item_Status::FIXED,
				Item_Status::REPAIRABLE,
				Item_Status::END_OF_LIFE,
				Item_Status::STANDBY,
				Item_Status::WITHDRAWN,
		);
		
	// Item Desc, Fixer Station, Status (editable), Status Type (for grouping), Status Order (for sorting)
		
		foreach( $items_array as $item ) {

			$item_desc = $item->get_item_description();

			$fixer_station = $item->get_fixer_station();
			$station_name = isset( $fixer_station ) ? $fixer_station->get_name() : __( '[No fixer station assigned]', 'reg-man-rc' );
			
			$status_editor = In_Place_Item_Status_Editor::create( $item );
			$editable_status_col = $status_editor->get_contents();

			$status = $item->get_item_status();
			$status_id = isset( $status ) ? $status->get_id() : '';
			
			$status_type = $status_types_array[ $status_id ];
			
			$status_order = array_search( $status_id, $status_order_array );

			$status_type_order = array_search( $status_type, $status_types_order_array );
			
			$result[] = array(
					$item_desc,
					$station_name,
					$editable_status_col,
					$status_type,
					$status_order,
					$status_type_order,
			);
			
		} // function
		
		return $result;
		
	} // function
	
	private function render_add_item_to_visitor_form() {

		$cancel_text = __( 'Close', 'reg-man-rc' );
		$submit_text = __( 'Add Item', 'reg-man-rc' );
		
		$button_format = '<button type="%3$s" class="visitor-reg-form-%2$s reg-man-rc-button">%1$s</button>';
		$cancel_button = sprintf( $button_format, $cancel_text, 'cancel', 'button' );
		$submit_button = sprintf( $button_format, $submit_text, 'submit', 'submit' );
		
		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Visitor_Registration_Controller::ADD_ITEM_TO_VISITOR_AJAX_ACTION;

		echo '<div class="visitor-reg-add-item-to-visitor-form-container autocomplete-item-desc-container">';
		
			echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
					' class="visitor-reg-add-item-to-visitor-form visitor-reg-form reg-man-rc-ajax-form">';

				wp_nonce_field( $ajax_action );

				$event = $this->get_event();
				$event_key = ($event !== NULL) ? $event->get_key_string() : NULL;
				echo '<input type="hidden" name="event-key" value="' . $event_key . '">'; // Pass the event key on all registrations
				
				echo '<input type="hidden" name="visitor-id" value="">'; // Assigned on the client side
				
				echo '<div class="visitor-reg-items-section visitor-reg-form-section">';
					$this->render_item_inputs();
				echo '</div>';
				
				echo '<div class="visitor-reg-form-buttons-section visitor-reg-form-section">';
					echo $cancel_button;
					echo $submit_button;
				echo '</div>';

			echo '</form>';
		
		echo '</div>';
		
	} // function
	
	
	
	private function render_item_inputs() {
		
		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );
//		$input_list->add_list_classes( 'visitor-reg-item-list' );
		
		// Note that the autocomplete data must be rendered by the main page
		
		$label = __( 'I brought another item', 'reg-man-rc' );
//		$dismiss_button = '<span class="reg-item-remove-button reg-man-button"><span class="dashicons dashicons-dismiss"></span></span>';
//		$legend = $label . $dismiss_button;
		$legend = $label;
		$item_input_list = Visitor_Reg_Ajax_Form::get_visitor_item_input_list();
		$hint = '';
		$classes = 'item-list-item-fieldset uninitialized';

		$input_list->add_fieldset( $legend, $item_input_list, $hint, $classes );
		
		$input_list->render();
/*
		// N.B. The jquery depends on the button being immediately after the list
		$button_text = __( 'I brought another item', 'reg-man-rc' );
		echo '<div class="visitor-reg-item-list-add-button-container">';
			echo '<button type="button" class="visitor-item-add reg-man-rc-button">' . $button_text . '</button>';
		echo '</div>';
*/		
	} // function
	
} // class
?>