<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item;

class Visitor_Reg_Ajax_Form {

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

	/**
	 * Render this form
	 */
	public function render() {
		
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			
			$head = __( 'You must be logged in to use this form', 'reg-man-rc' );
			echo '<h2 class="login-title">' . $head . '</h2>';
			
		} else { // User is logged in so show the page content
			
			$form_action = self::get_form_action();
			$ajax_action = Visitor_Registration_Controller::NEW_VISITOR_REG_AJAX_ACTION;

			echo '<div class="visitor-reg-form-container visitor-reg-add-visitor-form-container autocomplete-item-desc-container autocomplete-visitor-name-container">';

				echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
						' class="visitor-reg-add-visitor-form visitor-reg-form reg-man-rc-ajax-form">';

					wp_nonce_field( $ajax_action );

					echo self::render_form_contents();
					
				echo '</form>';
				
			echo '</div>';
			
		} // endif
		
	} // function

	private function render_form_contents() {

		// These are the contents of the form that are shown when the page is initially rendered

		$close_icon = '<span class="dashicons dashicons-no"></span>';
		$cancel_text = __( 'Cancel', 'reg-man-rc' );
		$submit_text = __( 'Add Visitor', 'reg-man-rc' );
		
		$close_button  = '<button type="button" class="visitor-reg-form-cancel reg-man-rc-button">' . $close_icon . '</button>';
		$cancel_button = '<button type="button" class="visitor-reg-form-cancel reg-man-rc-button">' . $cancel_text . '</button>';
		$submit_button = '<button type="submit" class="visitor-reg-form-submit reg-man-rc-button">' . $submit_text . '</button>';
		
		echo '<div class="visitor-reg-form-header">';
			echo '<h3 class="visitor-reg-manager-subtitle">' . __( 'Add Visitor', 'reg-man-rc' ) . '</h3>';
			echo $close_button;
		echo '</div>';
	
		$event = $this->get_event();
		$event_key = ($event !== NULL) ? $event->get_key_string() : NULL;
		echo '<input type="hidden" name="event-key" value="' . $event_key . '">'; // Pass the event key on all registrations

		echo '<div class="visitor-reg-items-section visitor-reg-form-section">';
			$this->render_item_inputs();
		echo '</div>';

		echo '<div class="visitor-reg-name-section visitor-reg-form-section">';
			$this->render_visitor_inputs();
		echo '</div>';

		echo '<div class="visitor-reg-house-rules-section visitor-reg-form-section">';
			$this->render_house_rules_inputs();
		echo '</div>';


		echo '<div class="visitor-reg-form-buttons-section visitor-reg-form-section">';
			echo $cancel_button;
			echo $submit_button;
		echo '</div>';
		
	} // function

	private function render_item_inputs() {

		echo '<div class="visitor-reg-item-list-msg-container">';
			$msg = __( 'Please order your items by priority', 'reg-man-rc' );
			echo '<span>' . $msg . '</span>';
		echo '</div>';
		
		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );
		$input_list->add_list_classes( 'visitor-reg-item-list' );
		
		// Note that the autocomplete data must be rendered by the main page
		
		$label = __( 'Item', 'reg-man-rc' );
		$dismiss_button = '<span class="reg-item-remove-button reg-man-button"><span class="dashicons dashicons-dismiss"></span></span>';
		$legend = $label . $dismiss_button;
		$item_input_list = self::get_visitor_item_input_list();
		$hint = '';
		$classes = 'item-list-item-fieldset uninitialized';
		$input_list->add_fieldset( $legend, $item_input_list, $hint, $classes );
		
		$input_list->render();

		// N.B. The jquery depends on the button being immediately after the list
		$button_text = __( 'I brought another item', 'reg-man-rc' );
		echo '<div class="visitor-reg-item-list-add-button-container">';
			echo '<button type="button" class="visitor-item-add reg-man-rc-button">' . $button_text . '</button>';
		echo '</div>';
		
	} // function

	private function render_visitor_inputs() {

		echo '<script class="visitor-reg-returning-visitor-data" type="application/json">'; // json data for returning visitors
			$visitor_array = Visitor::get_all_visitors();
			$data_array = array();
			foreach( $visitor_array as $visitor ) {
				$id = $visitor->get_id();
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

		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );

		$visitor_fieldset = Form_Input_List::create();
		$visitor_fieldset->set_required_inputs_flagged( FALSE );
		$visitor_fieldset->set_style_compact();
		
		$label = __('Is this your first time?', 'reg-man-rc');
		$options = array(__('Yes', 'reg-man-rc') => 'YES', __('No', 'reg-man-rc') => 'NO');
		$visitor_fieldset->add_radio_group( $label, 'first-time', $options, $selected = NULL, $hint = '', $classes = 'required',
										$is_required = TRUE, $custom_label = NULL, $custom_value = NULL, $is_compact = FALSE );

		// Add a hidden input for the visitor's ID when a returning visitor is selected
		$name = 'visitor-id';
		$val = '';
		$visitor_fieldset->add_hidden_input( $name, $val );

		
		$button_label = __( 'Choose a different visitor', 'reg-man-rc' );
		$button = "<button type=\"button\" class=\"visitor-name-reset reg-man-rc-button\">$button_label</button>";
		
		$label = __( 'Full Name', 'reg-man-rc' );
		$name = 'full-name';
		$val = '';
		$hint = $button;
		$classes = 'auto-filled-input';
		$is_required = TRUE;
		$addn_attrs = 'autocomplete="off"';
		$visitor_fieldset->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		
		$checkbox_label = __( 'I have no email address', 'reg-man-rc' );
		$checkbox =
				'<div class="visitor-reg-man-no-email-container">' .
					'<label>' .
						'<input name="no-email" type="checkbox" value="no-email" autocomplete="off">' . $checkbox_label .
					'</label>' .
				'</div>';
		
		$label = __( 'Email', 'reg-man-rc' );
		$name = 'email';
		$val = '';
		$hint = $checkbox;
		$classes = 'auto-filled-input';
		$is_required = TRUE;
		$addn_attrs = 'autocomplete="off"';
		$visitor_fieldset->add_email_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );
		
		$label = __( 'Visitor', 'reg-man-rc' );
		$input_list->add_fieldset( $label, $visitor_fieldset );

		$msg = __('We use your email address in place of a signature to verify that you understand the house rules and safety procedures.', 'reg-man-rc');
		$msg .= __('  We will not share your personal information with anyone or contact you without your consent.', 'reg-man-rc');
		$input_list->add_information( $msg, '' );

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
		
//		$this->render_accordion_buttons(TRUE, FALSE);
		
	} // function

	private  function render_house_rules_inputs() {
		$input_list = Form_Input_List::create();
		$input_list->set_required_inputs_flagged( FALSE );

		echo '<div class="house-rules">';
			$post_id = Settings::get_house_rules_post_id();
			$post = ! empty( $post_id ) ? get_post( $post_id ) : NULL;
			if ( $post === NULL ) { // failure to get the rules page
				$msg = __( 'Please see the intake volunteer for a copy of the house rules', 'reg-man-rc' );
				echo "<h2>$msg</h2>";
			} else {
				echo do_shortcode( $post->post_content ); // run any shortcodes on the page
			} // endif
		echo '</div>';
//		echo '<div class="text-fadeout"></div>'; // used to put a fade at the bottom so it's obvious that it must be scrolled
		$rules_label = __( 'I have read and understood the house rules and safety procedures', 'reg-man-rc' );
		$name = 'rules-ack';
		$val = $name; // it's just a checkbox, the value could be anything
		$is_checked = FALSE;
		$hint = '';
		$classes = 'required check-list';
		$is_required = TRUE;
		$input_list->add_checkbox_input( $rules_label, $name, $val, $is_checked, $hint, $classes, $is_required );

		$input_list->render();
		
//		$this->render_accordion_buttons(TRUE, TRUE);
		
	} // function

	/**
	 * Get a Form_Input_List (fieldset) for an item
	 * @param	Item	$item
	 * @return	Form_Input_List
	 */
	public static function get_visitor_item_input_list( $item = NULL ) {

		$result = Form_Input_List::create();
		$result->set_style_compact();
		$result->set_required_inputs_flagged( FALSE );
		$result->add_list_classes( 'item-list-item' );

		$label = __( 'Description', 'reg-man-rc' );
		$name = 'item-desc[]';
		$val = '';
		$hint = '';
		$classes = '';
		$required = TRUE;
		$result->add_text_input( $label, $name, $val, $hint, $classes, $required );

		$label = __( 'Fixer Station', 'reg-man-rc' );
		$name = 'fixer-station[]';
		ob_start();
			self::render_fixer_station_input();
		$fixer_station_select = ob_get_clean();
		$result->add_custom_html_input( $label, $name, $fixer_station_select );
		
		$label = __( 'Item type', 'reg-man-rc' );
		$name = 'item-type[]';
		ob_start();
			self::render_item_type_input();
		$item_type_select = ob_get_clean();
		$result->add_custom_html_input( $label, $name, $item_type_select );
		
		return $result;
		
	} // function

	private static function render_fixer_station_input() {
		$all_stations = Fixer_Station::get_all_fixer_stations();
		echo '<select name="fixer-station[]" required="required" autocomplete="off">';
			$label = esc_html__( '-- Please select --', 'reg-man-rc' );
			// Note that the value must be an empty string "" to trigger a validation warning
			echo "<option value=\"\" disabled=\"disabled\" selected=\"selected\">$label</option>";
			foreach ( $all_stations as $station ) {
				$station_id = $station->get_id();
				$station_name = $station->get_name();
				$esc_name = esc_html( $station_name );
				echo "<option value=\"$station_id\">$esc_name</option>";
			} // endfor
		echo '</select>';
	} // function

	private static function render_item_type_input() {
		$all_types = Item_Type::get_all_item_types();
		echo '<select name="item-type[]" required="required" autocomplete="off">';
			$label = esc_html__( '-- Please select --', 'reg-man-rc' );
			// Note that the value must be an empty string "" to trigger a validation warning
			echo "<option value=\"\" disabled=\"disabled\" selected=\"selected\">$label</option>";
			foreach ( $all_types as $type ) {
				$type_id = $type->get_id();
				$type_name = $type->get_name();
				$esc_name = esc_html( $type_name );
				echo "<option value=\"$type_id\">$esc_name</option>";
			} // endfor
		echo '</select>';
	} // function

} // class
?>