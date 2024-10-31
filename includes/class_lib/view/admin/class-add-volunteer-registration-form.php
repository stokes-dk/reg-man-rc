<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Volunteer_Registration_Admin_Controller;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Volunteer;

class Add_Volunteer_Registration_Form {
	
	private $event;
	private $is_initial_page_render = FALSE;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param Event	$event
	 * @return self
	 */
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
	
	/**
	 * Render this view
	 * This is used on the server side during page rendering
	 */
	public function render() {

		echo '<div class="reg-man-rc-cpt-add-form-container volunteer-reg-add-form-container">';
		
			$this->set_is_initial_page_render( TRUE );
			$this->render_add_form(); // This will render the form itself

		echo '</div>';

	} // function

	private function render_add_form() {
		
		$ajax_action = Volunteer_Registration_Admin_Controller::ADD_VOLUNTEER_REG_AJAX_ACTION;
		$form_method = 'POST';
		$form_classes = 'reg-man-rc-cpt-add-form volunteer-reg-add-form';
		$ajax_form = Ajax_Form::create( $ajax_action, $form_method, $form_classes );
		$form_content = $this->get_add_form_content();
		$ajax_form->add_form_content( $form_content );
		$ajax_form->set_include_nonce_fields( FALSE ); // I will need to do this every time I render the form content
		$ajax_form->render();

	} // function
	
	/**
	 * Get the content of the add form
	 * @return string
	 */
	public function get_add_form_content() {
		ob_start();
			$this->render_form_content();
		$result = ob_get_clean();
		return $result;
	} // function
	
	private function get_is_initial_page_render() {
		return $this->is_initial_page_render;
	} // function
	
	private function set_is_initial_page_render( $is_initial_page_render ) {
		$this->is_initial_page_render = $is_initial_page_render;
	} // function
	
	private function render_form_content() {
		
		// This form is initially rendered on the server with no volunteer selection list (to save time during page load)
		// When the user wants to add a registration the client will send a request to get the form details
		wp_nonce_field( Volunteer_Registration_Admin_Controller::ADD_VOLUNTEER_REG_AJAX_ACTION );

		$event = $this->get_event();
		$event_key = $event->get_key_string();
		$is_initial_page_render = $this->get_is_initial_page_render();
		if ( $is_initial_page_render ) {
			$request_type = Volunteer_Registration_Admin_Controller::ADD_VOLUNTEER_REG_REQUEST_GET_FORM;
		} else {
			$request_type = Volunteer_Registration_Admin_Controller::ADD_VOLUNTEER_REG_REQUEST_ADD;
		} // endif

		echo "<input type=\"hidden\" name=\"event-key\" value=\"$event_key\" autocomplete=\"off\">";
		echo "<input type=\"hidden\" name=\"request-type\" value=\"$request_type\" autocomplete=\"off\">";
		
		echo '<div class="rc-reg-man-add-form-details-container">';
		
			// On the initial page render we will skip this step in order to save some time
			if ( ! $is_initial_page_render ) {
				$this->render_event_details();
			} // endif
			
		echo '</div>';
		
	} // function
	
	private function render_event_details() {
		$event = $this->get_event();

		if ( isset( $event ) ) {
			
			$input_list = Form_Input_List::create();
			
			$label = __( 'Event', 'reg-man-rc' );
			$info_html = $event->get_label();
			$input_list->add_information( $label, $info_html );

			$label = __( 'Volunteer', 'reg-man-rc' );
			$name = 'volunteer-id';
			$options = self::get_volunteer_select_option_array();
			$selected = '';
			$hint = ''; //__( 'Transfer the event registration records to the selected volunteer.  Note that this cannot be undone.', 'reg-man-rc' );
			$classes = '';
			$is_required = TRUE;
			$addn_attrs = 'disabled="disabled" class="combobox"';
			$is_show_please_select = TRUE;
			$input_list->add_select_input( $label, $name, $options, $selected, $hint, $classes, $is_required, $addn_attrs, $is_show_please_select );
			
			// Buttons
			$label = __( 'Cancel', 'reg-man-rc' );
			$type = 'button';
			$classes = 'reg-man-rc-button reg-man-rc-add-new-cpt-form-cancel';
			$input_list->add_form_button( $label, $type, $classes );
	
			$label = __( 'Register Volunteer', 'reg-man-rc' );
			$type = 'submit';
			$classes = 'reg-man-rc-button reg-man-rc-add-new-cpt-form-submit';
			$input_list->add_form_button( $label, $type, $classes );
	
			$input_list->render();
		
		} // endif
	} // function
	
	private function get_volunteer_select_option_array() {

		$result = array();

		$all_volunteers = Volunteer::get_all_volunteers();
		
		foreach( $all_volunteers as $volunteer ) {
			$id = $volunteer->get_id();
			$label = $volunteer->get_label();
			$result[ $label ] = $id;
		} // endfor
		return $result;
		
	} // function

} // class