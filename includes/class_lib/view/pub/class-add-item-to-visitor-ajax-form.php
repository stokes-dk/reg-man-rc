<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\Visitor_Registration_Controller;

class Add_Item_To_Visitor_Ajax_Form {

//	const AJAX_NONCE_STR = 'reg-man-rc-add-item-to-visitor-reg-nonce'; // used to construct a nonce

	private $event;

	private function __construct() { }

	public static function create( $event ) {
		$result = new self();
		$result->event = $event;
		return $result;
	} // function

	/**
	 * Get the event
	 * @return Event
	 */
	private function get_event() {
		return $this->event;
	} // function

	private static function get_form_action() {
		$result = esc_url( admin_url('admin-ajax.php') );
		return $result;
	} // function
/*
	private static function getNonce() {
		$result = wp_create_nonce( self::AJAX_NONCE_STR );
		return $result;
	} // function
*/
	public function render() {
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			echo '<h2 class="login-title">' . __('You must be logged in to use this form', 'reg-man-rc') . '</h2>';
		} else { // User is logged in so show the page content
			$form_action = self::get_form_action();
//			$nonce = self::getNonce();
			$ajax_action = Visitor_Registration_Controller::AJAX_ADD_ITEM_TO_VISITOR_ACTION;
			echo '<div class="add-item-to-visitor-reg-form-container autocomplete-item-desc-container">';
				echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
						' class="add-item-to-visitor-reg-form reg-man-rc-ajax-form reg-man-rc-js-validation">';
//					echo "<input type=\"hidden\" name=\"ajax-nonce\" value=\"$nonce\">";
					echo self::render_form_contents();
				echo '</form>';
			echo '</div>';
		} // endif
	} // function

	private function render_form_contents() {
		// These are the contents of the form that are shown when the page is initially rendered

//		echo '<script class="visitor-reg-item-autocomplete-data" type="application/json">'; // json data for autocomplete
//			$suggestionArray = RC_Reg_Visitor_Reg::getNewRegistrationItemDescAutocompleteSuggestsions(''); // Match anything
//			echo json_encode($suggestionArray);
//		echo '</script>';

		$event = $this->get_event();
		$event_key = ($event !== NULL) ? $event->get_key() : NULL;

		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );

		$input_list->add_hidden_input( 'event-key', $event_key );

		$label = __( 'Visitor', 'reg-man-rc' );
		$info = '<span class="visitor-name"></span>'; // This is a placeholder for the visitor's name
		$input_list->add_information( $label, $info ); // Give us a place to put the visitor's name

		$input_list->add_hidden_input( 'visitor-id', '' );

		// A new item can be added by the visitor, those inputs will be shown in a field set
		// The new item inputs will only be the item description and type
		// To create a new item I need the visitor's ID which will be inserted on the client side

		$new_item_fieldset = Form_Input_List::create();
		ob_start();
			echo '<ol class="visitor-reg-item-list add-item-to-visitor-reg-new-item-list">';
				Visitor_Reg_Ajax_Form::render_visitor_item_input();
			echo '</ol>';
			// N.B. The jquery depends on the button being immediately after the list
			$button_text = __( 'I brought another item', 'reg-man-rc' );
			echo '<button type="button" class="visitor-item-add reg-man-rc-button">' . $button_text . '</button>';
		$new_item_list = ob_get_clean();
		$new_item_fieldset->add_custom_html_input( '', $name = 'item-list-group', $new_item_list, $hint = '', $classes = '', $id = '');

		$label = __( 'What other items did you bring today?', 'reg-man-rc' );
		$input_list->add_fieldset( $label, $new_item_fieldset );

		$input_list->render();
	} // function


} // class
?>