<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Admin\Volunteer_Admin_Controller;
use Reg_Man_RC\View\Form_Input_List;

class Remove_Volunteer_Form {
	
	private $volunteer;

	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param Volunteer	$volunteer
	 * @return self
	 */
	public static function create( $volunteer = NULL ) {
		$result = new self();
		$result->volunteer = $volunteer;
		return $result;
	} // function

	/**
	 * Get the volunteer
	 * @return Volunteer
	 */
	private function get_volunteer() {
		return $this->volunteer;
	} // function
	
	/**
	 * Set the volunteer
	 * @param Volunteer $volunteer
	 */
	public function set_volunteer( $volunteer ) {
		$this->volunteer = $volunteer;
	} // function
	
	/**
	 * Render this view
	 */
	public function render() {

		echo '<div class="rc-reg-man-dynamic-remove-form-container">';
		
			// The remove form content will be loaded dynamically on the client side when the user clicks the 'Remove..."
			//  button for a specific record
			// We will use two separate forms: one to retrieve the content for the remove form and another for the
			//  dynamically loaded remove form itself
			
			$this->render_remove_form_content_loader(); // Loader to retrieve the remove form for record X
			
			// The remove for content will be loaded dynamically from the client side based on the ID selected
			$this->render_remove_form(); // This will create the form itself and its buttons

		echo '</div>';

	} // function
	
	private function render_remove_form_content_loader() {
		
		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Volunteer_Admin_Controller::GET_REMOVE_FORM_CONTENT;

		echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
				' class="cpt-remove-form-loader reg-man-rc-ajax-form">';

			wp_nonce_field( $ajax_action );
		
			echo '<input type="hidden" name="record-id" value="">'; // Assigned on the client side
		
		echo '</form>';
		
	} // function
	
	private function render_remove_form() {
		
		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Volunteer_Admin_Controller::REMOVE_VOLUNTEER;

		echo '<div class="reg-man-rc-cpt-remove-form-container volunteer-remove-form-container">';
		
			echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
					' class="reg-man-rc-cpt-remove-form volunteer-remove-form reg-man-rc-ajax-form">';

				wp_nonce_field( $ajax_action );
				
				echo '<div class="reg-man-rc-remove-form-inputs-container volunteer-remove-form-inputs-container">';
				
					// The form inputs will be loaded from the client side when a record is selected
					$this->render_remove_form_volunteer_details();
					
					$this->render_remove_form_registration_options();

				echo '</div>';
				
			echo '</form>';
		
		echo '</div>';
		
	} // function
	
	private function render_remove_form_volunteer_details() {
		$volunteer = $this->get_volunteer();
		
		$input_list = Form_Input_List::create();
		$input_list->add_list_classes( 'volunteer-remove-form-volunteer-details' );
		
		$label = __( 'Full name', 'reg-man-rc' );
		$full_name = isset( $volunteer ) ? $volunteer->get_full_name() : '';
		$info_html = '<span class="volunteer-full-name">' . $full_name . '</span>';
		$input_list->add_information( $label, $info_html );
		
		$input_list->render();
		
	} // function

	private function render_remove_form_registration_options() {

		$input_list = Form_Input_List::create();
		$input_list->add_list_classes( 'volunteer-remove-form-reg-options' );
		
		$radio_group = Form_Input_List::create();
		$radio_format = '<label for="%4$s"><input type="radio" id="%4$s" name="%2$s" value="%3$s" required="required">%1$s</label>';
		
		$label = __( 'Trash event registrations', 'reg-man-rc' );
		$name = 'vol_reg_action';
		$val = 'delete';
		$id = 'vol_reg_action_delete';
		$html = sprintf( $radio_format, $label, $name, $val, $id );
		$hint = __( 'The event registrations for this volunteer will be moved to the trash', 'reg-man-rc' );
		$radio_group->add_custom_html_input( '', $name, $html, $hint );

		$label = __( 'Do nothing', 'reg-man-rc' );
		$name = 'vol_reg_action';
		$val = 'nothing';
		$id = 'vol_reg_action_nothing';
		$html = sprintf( $radio_format, $label, $name, $val, $id );
		$hint = __( 'The event registrations for this volunteer will remain in the system and will be orphaned (attached to no volunteer)', 'reg-man-rc' );
		$radio_group->add_custom_html_input( '', $name, $html, $hint );

		$label = __( 'Transfer to another volunteer', 'reg-man-rc' );
		$name = 'vol_reg_action';
		$val = 'transfer';
		$id = 'vol_reg_action_transfer';
		$html = sprintf( $radio_format, $label, $name, $val, $id );
		$hint = __( 'The event registrations for this volunteer will be transfered to the selected volunteer', 'reg-man-rc' );
		$radio_group->add_custom_html_input( '', $name, $html, $hint );

		$label = __( 'What to do with the event registrations for this volunteer?', 'reg-man-rc' );
		$input_list->add_fieldset( $label, $radio_group );
		$input_list->render();
	} // function
	
	/**
	 * Get the content of the remove form
	 * @return string
	 */
	public function get_remove_form_content() {
		ob_start();
			$this->render_remove_form_inputs();
		$result = ob_get_clean();
		return $result;
	} // function

	private function render_remove_form_inputs() {
		$volunteer = $this->get_volunteer();
		
		$input_list = Form_Input_List::create();

		$label = __( 'Transfer registrations to', 'reg-man-rc' );
		$input_name = 'to-vol-id';
		$input_id = 'to-vol-id';
		$vol_select = $this->get_volunteer_select( $input_name, $input_id );
		$hint = '';
		$classes = '';
		$is_required = TRUE;
		$input_list->add_custom_html_input( $label, $input_name, $vol_select, $hint, $classes, $is_required, $input_id );
		
		$input_list->render();
		
	} // function
	
	
	private function get_volunteer_select( $input_name, $input_id ) {

		// I will exclude the volunteer being removed from the select
		$volunteer = $this->get_volunteer();
		$from_vol_id = $volunteer->get_id();
		
		ob_start();

			// Disabled to start with until it is initialized on the client side
			echo "<select required=\"required\" class=\"combobox\" name=\"$input_name\" id=\"$input_id\" autocomplete=\"off\"  disabled=\"disabled\" >";

				$label = __( '-- Please select --', 'reg-man-rc' );
				$html_name = esc_html( $label );
				echo "<option value=\"0\" xxxdisabled=\"disabled\" selected=\"selected\">$html_name</option>";
			
				$all_volunteers = Volunteer::get_all_volunteers();
				
				/* Translators: %1$s volunteer's name, %2$s is their email address, %3$s is a count of events like "2 events" */
				$option_label_format = _x( '%1$s &lt;%2$s&gt; : %3$s', 'An option label identifying a volunteer by name and email and showing a count of event registrations', 'reg-man-rc' );
				
				$option_format = '<option value=%2$s" data-full-name="%3$s" data-email="%4$s" data-event-count="%5$s">%1$s</option>';
				
				foreach( $all_volunteers as $volunteer ) {
					$id = $volunteer->get_id();
					$email = $volunteer->get_email();
					$full_name = $volunteer->get_full_name();
					if ( ( $id !== $from_vol_id ) && ! empty( $email ) && ! empty( $full_name ) ) {
						
						$reg_count = $volunteer->get_registration_descriptor_count();
						$reg_count_text = sprintf( _n( '%s event', '%s events', $reg_count, 'reg-man-rc' ), number_format_i18n( $reg_count ) );
				
						$option_label = sprintf( $option_label_format, esc_html( $full_name ), esc_html( $email ), $reg_count_text );
						printf( $option_format, $option_label, $id, esc_attr( $full_name ), esc_attr( $email ), $reg_count );
						
					} // endif
				} // endfor
			
			echo '</select>';
			
		$result = ob_get_clean();
		
		return $result;
	} // function
	
	
	private static function get_volunteer_select_option_array() {

		ob_start();
	
			$label = __( '-- Please select --', 'reg-man-rc' );
			$html_name = esc_html( $label );
			echo "<option value=\"0\" xxxdisabled=\"disabled\" selected=\"selected\">$html_name</option>";

			$all_volunteers = Volunteer::get_all_volunteers();

			
			/* Translators: %1$s volunteer's name, %2$s is their email address, %3$s is a count of events like "2 events" */
			$option_label_format = _x( '%1$s &lt;%2$s&gt; : %3$s', 'An option label identifying a volunteer by name and email and showing a count of event registrations', 'reg-man-rc' );
			
			$option_format = '<option value=%2$s" data-full-name="%3$s" data-email="%4$s" data-event-count="%5$s">%1$s</option>';
			
			foreach( $all_volunteers as $volunteer ) {
				$id = $volunteer->get_id();
				$email = $volunteer->get_email();
				$full_name = $volunteer->get_full_name();
				if ( ! empty( $email ) && ! empty( $full_name ) ) {
					
					$reg_array = $volunteer->get_registration_descriptors();
					$reg_count = count( $reg_array );
					$reg_count_text = sprintf( _n( '%s event', '%s events', $reg_count, 'reg-man-rc' ), number_format_i18n( $reg_count ) );
			
					$option_label = sprintf( $option_label_format, esc_html( $full_name ), esc_html( $email ), $reg_count_text );
					printf( $option_format, $option_label, $id, esc_attr( $full_name ), esc_attr( $email ), $reg_count );
					
				} // endif
			} // endfor
				
		$result = ob_get_clean();
		
		return $result;
		
	} // function
	
} // class