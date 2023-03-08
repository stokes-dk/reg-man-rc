<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Admin\Visitor_Registration_Admin_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Fixer_Station;

class Visitor_Reg_Ajax_Form {

	const DEFAULT_HOUSE_RULES_PAGE_PATH = 'house-rules-and-safety-procedures';

	const AJAX_NONCE_STR = 'reg-man-rc-visitor-reg-nonce'; // used to construct a nonce

	private $event;

	private function __construct() { }

	public static function create( $event ) {
		$result = new self();
		$result->event = $event;
		return $result;
	} // function

	/**
	 * Get the event we're registering to
	 * @return	Event
	 */
	private function get_event() {
		return $this->event;
	} // function

	private static function get_form_action() {
		$result = esc_url( admin_url( 'admin-ajax.php' ) );
		return $result;
	} // function

	private static function get_nonce() {
		$result = wp_create_nonce( self::AJAX_NONCE_STR );
		return $result;
	} // function

	public function render() {
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			$head = __( 'You must be logged in to use this form', 'reg-man-rc' );
			echo '<h2 class="login-title">' . $head . '</h2>';
		} else { // User is logged in so show the page content
			$form_action = self::get_form_action();
//			$nonce = self::get_nonce();
			$ajax_action = Visitor_Registration_Admin_Controller::AJAX_NEW_VISITOR_REG_ACTION;
			echo '<div class="visitor-reg-form-container autocomplete-item-desc-container autocomplete-visitor-name-container">';
				echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
						' class="visitor-reg-form reg-man-rc-ajax-form reg-man-rc-js-validation">';
//					echo "<input type=\"hidden\" name=\"ajax-nonce\" value=\"$nonce\">";
					echo self::render_form_contents();
				echo '</form>';
			echo '</div>';
		} // endif
	} // function

	private function render_form_contents() {
		// These are the contents of the form that are shown when the page is initially rendered
		// The form may contain an event select or if an event is specified as a GET argument then the form
		//  will contain the initial inputs for the visitor's items

		$event = $this->get_event();
		$event_key = ($event !== NULL) ? $event->get_key() : NULL;
		echo '<input type="hidden" name="event-key" value="' . $event_key . '">'; // Pass the event key on all registrations

		echo '<div class="visitor-reg-input-accordion reg-man-rc-accordion-container">';
			echo '<h3>' . __('Items', 'reg-man-rc') . '</h3>';
			echo '<div class="visitor-reg-items-section visitor-reg-accordion-section visitor-reg-item-validation reg-man-rc-js-validation">';
				$this->render_item_inputs();
			echo '</div>';
			echo '<h3>' . __('Name', 'reg-man-rc') . '</h3>';
			echo '<div class="visitor-reg-name-section visitor-reg-accordion-section reg-man-rc-js-validation">';
				$this->render_name_inputs();
			echo '</div>';
			echo '<h3>' . __('House Rules & Safety Procedures', 'reg-man-rc') . '</h3>';
			echo '<div class="visitor-reg-house-rules-section visitor-reg-accordion-section reg-man-rc-js-validation">';
				$this->render_house_rules_inputs();
			echo '</div>';
		echo '</div>';
	} // function

	private function render_item_inputs() {
		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );

		// Note that the autocomplete data must be rendered by the main page

		$item_input_list = Form_Input_List::create();
		$item_input_list->set_required_inputs_flagged( FALSE );
		ob_start();
			echo '<ol class="visitor-reg-item-list">';
				self::render_visitor_item_input();
			echo '</ol>';
			// N.B. The jquery depends on the button being immediately after the list
			$button_text = __( 'I brought another item', 'reg-man-rc' );
			echo '<button type="button" class="visitor-item-add reg-man-rc-button">' . $button_text . '</button>';
		$item_list = ob_get_clean();
		$item_input_list->add_custom_html_input( '', $name = 'item-list-group', $item_list, $hint = '', $classes = '', $id = '');

		$label = __( 'What item(s) did you bring in today?', 'reg-man-rc' );
		$input_list->add_fieldset( $label, $item_input_list, $hint = '', $classes = '' );

		$input_list->render();
		$this->render_accordion_buttons(FALSE, FALSE);
	} // function

	private function render_name_inputs() {

		$input_list = Form_Input_List::create();

		echo '<script class="visitor-reg-returning-visitor-data" type="application/json">'; // json data for returning visitors
			$visitor_array = Visitor::get_all_visitors();
			$data_array = array();
			foreach( $visitor_array as $visitor ) {
				$id = ($visitor instanceof Visitor) ? $visitor->get_id() : 0;
				$email = $visitor->get_email();
				$obscured_email = $visitor->get_partially_obscured_email();
				$full_name = $visitor->get_full_name();
				$public_name = $visitor->get_public_name();
				$join_mail_list = $visitor->get_is_join_mail_list() ? 1 : 0;
				$data_array[] = array(
						'id'			=> $id,
						'obs_email'		=> $obscured_email,
						'full_name'		=> $full_name,
						'public_name'	=> $public_name,
						'join_list'		=> $join_mail_list
				);
			} // endif
			echo json_encode( $data_array );
		echo '</script>';

		$label = __('Is this your first time?', 'reg-man-rc');
		$options = array(__('Yes', 'reg-man-rc') => 'YES', __('No', 'reg-man-rc') => 'NO');
		$input_list->add_radio_group($label, 'first-time', $options, $selected = NULL, $hint = '', $classes = 'required',
													$custom_label = NULL, $custom_value = NULL, $is_compact = TRUE);

		// Add a hidden input for the visitor's ID when a returning visitor is selected
		$name = 'visitor-id';
		$val = '';
		$input_list->add_hidden_input( $name, $val );
//		$input_list->add_text_input( 'ID', $name, $val, $hint = '', $classes = '', $is_req = FALSE, $addn_attrs = 'readonly="readonly"' );

		$button_label = __( 'Choose a different visitor', 'reg-man-rc' );
		$button = "<button type=\"button\" class=\"visitor-name-reset reg-man-rc-button\">$button_label</button>";
		$label = __( 'Full Name', 'reg-man-rc' );
		$name = 'full-name';
		$val = '';
		$hint = $button;
		$classes = 'auto-filled-input';
		$is_required = TRUE;
		$addn_attrs = 'autocomplete="off"';
		$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

/* TODO - For now we'll just construct the public name on the server but visitors may wish to supply
		$label = __( 'Public Name', 'reg-man-rc' );
		$name = 'public-name';
		$val = '';
		$hint = __( 'The name we will use to call you when it\'s your turn', 'reg-man-rc' );
		$classes = 'public-name-input';
		$is_required = FALSE;
		$addn_attrs = 'autocomplete="off"';
		$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );
*/
		$label = __('Email', 'reg-man-rc');
		$name = 'email';
		$val = '';
		$hint = '';
		$classes = 'auto-filled-input';
		$is_required = FALSE;
		$addn_attrs = 'autocomplete="off"';
		$input_list->add_email_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		$msg = __('We use your email address in place of a signature to verify that you understand the house rules and safety procedures.', 'reg-man-rc');
		$msg .= __('  We will not share your personal information with anyone or contact you without your consent.', 'reg-man-rc');
		$input_list->add_information( $msg, '' );

		$label = __('I have no email address', 'reg-man-rc');
		$name = 'no-email';
		$val = 'no-email';
		$is_checked = FALSE;
		$hint = '';
		$classes = 'auto-filled-input';
		$input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes );

		if ( Settings::get_is_include_join_mail_list_question() ) {
			$label = __( 'Would you like to receive monthly email notifications of upcoming events?', 'reg-man-rc' );
			$name = 'mail-list';
			$options = array(	__( 'Yes', 'reg-man-rc' ) => 'YES',
								__( 'No thanks', 'reg-man-rc' ) => 'NO',
								__( 'I\'m already on the mailing list', 'reg-man-rc' ) => 'ALREADY' );
			$selected = 'YES';
			$hint = '';
			$classes = '';
			$custom_label = NULL;
			$custom_value = NULL;
			$is_compact = TRUE;
			$input_list->add_radio_group( $label, $name, $options, $selected, $hint, $classes, $custom_label, $custom_value, $is_compact );
		} // endif

		$input_list->render();
		$this->render_accordion_buttons(TRUE, FALSE);
	} // function

	private  function render_house_rules_inputs() {
		$input_list = Form_Input_List::create();

		echo '<div class="house-rules">';
			$page_path = Settings::get_house_rules_page_path();
			$post = get_page_by_path( $page_path );
			if ($post === NULL) { // failure to get the rules page
				echo '<h2>' . __('Please see the intake volunteer for a copy of the house rules', 'reg-man-rc') . '</h2>';
			} else {
				echo do_shortcode( $post->post_content );
			} // endif
		echo '</div>';
		echo '<div class="text-fadeout"></div>'; // used to put a fade at the bottom so it's obvious that it must be scrolled
		$rulesLabel = __('I have read and understood the house rules and safety procedures', 'reg-man-rc');
		$input_list->add_checkbox_input( $rulesLabel, 'rules-ack', $val = 'rules-ack', $is_checked = FALSE,
				$hint = '', $classes = 'required check-list' );
		$input_list->render();
		$this->render_accordion_buttons(TRUE, TRUE);
	} // function

	/**
	 * Render a set of inputs for a visitor item
	 * Note that this is also used by the "Add Item to visitor" form
	 */
	public static function render_visitor_item_input( ) {
		// A visitor may bring in multiple items, each has a description, type
		// This function creates the inputs for one item, they can be duplicated for other items
		echo '<li class="item-list-item uninitialized">';

			echo '<ul class="form-input-list item-list-input-group">';
				// Item description
				echo '<li class="input-item required">';
					echo '<div class="item-list-input input-container-container">';
						echo '<label><span class="label-container">' . __( 'Item', 'reg-man-rc' ) . '</span>';
							echo "<input type=\"text\" name=\"item-desc[]\" required=\"required\">";
						echo '</label>';
					echo '</div>';
					echo '<div class="error-container"></div>';
				echo '</li>';

				// Item Type
				echo '<li class="input-item required">';
					echo '<div class="item-list-input input-container">';
						self::render_item_type_input();
					echo '</div>';
					echo '<div class="error-container"></div>';
				echo '</li>';

				// Fixer Station
				echo '<li class="input-item required">';
					echo '<div class="item-list-input input-container">';
						self::render_fixer_station_input();
					echo '</div>';
					echo '<div class="error-container"></div>';
				echo '</li>';

			echo '</ul>';

		echo '</li>';
	} // function

	private static function render_item_type_input() {
		$all_types = Item_Type::get_all_item_types();
		$type_label = __( 'Item Type', 'reg-man-rc' );
		// Render a select input
		echo '<label><span class="label-container">' . $type_label . '</span>';
			echo '<select name="item-type[]" required="required">';
				$label = esc_html__( '-- Please select --', 'reg-man-rc' );
				echo "<option value=\"0\" disabled=\"disabled\" selected=\"selected\">$label</option>";
				foreach ( $all_types as $type ) {
					$type_id = $type->get_id();
					$type_name = $type->get_name();
					$esc_name = esc_html( $type_name );
					echo "<option value=\"$type_id\">$esc_name</option>";
				} // endfor
			echo '</select>';
		echo '</label>';
	} // function

	private static function render_fixer_station_input() {
		$all_stations = Fixer_Station::get_all_fixer_stations();
		$station_label = __( 'Fixer Station', 'reg-man-rc' );
		// Render a select input
		echo '<label><span class="label-container">' . $station_label . '</span>';
			echo '<select name="fixer-station[]" required="required">';
				$label = esc_html__( '-- Please select --', 'reg-man-rc' );
				echo "<option value=\"0\" disabled=\"disabled\" selected=\"selected\">$label</option>";
				foreach ( $all_stations as $station ) {
					$station_id = $station->get_id();
					$station_name = $station->get_name();
					$esc_name = esc_html( $station_name );
					echo "<option value=\"$station_id\">$esc_name</option>";
				} // endfor
			echo '</select>';
		echo '</label>';
	} // function

	private function render_accordion_buttons($is_back_enabled = TRUE, $is_done_enabled = FALSE) {
		// By default both buttons are enabled.  To disable the Back button pass $is_back_enabled = FALSE.  Ditto for Next button
		echo '<div class="accordion-buttons">';
			$format = '<button type="button" class="reg-man-rc-button visitor-reg-%2$s-button" name="%2$s" %3$s>%1$s</button>';
			// The third printf argument is for disabling the button
			printf($format, __('Back', 'reg-man-rc'), 'back', $is_back_enabled ? '' : 'disabled="disabled"');
			if (!$is_done_enabled) {
				printf($format, __('Continue', 'reg-man-rc'), 'next', '');
			} else {
				printf($format, __('Register', 'reg-man-rc'), 'done', '');
			} // endif
		echo '</div>';
	} // function

} // class
?>