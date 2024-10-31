<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Admin\Volunteer_Admin_Controller;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Control\User_Role_Controller;

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

	/**
	 * Render this view
	 */
	public function render() {

		echo '<div class="reg-man-rc-cpt-remove-form-container volunteer-remove-form-container">';
		
			// The remove for content will be loaded dynamically from the client side based on the ID being trashed
			$this->render_remove_form(); // This will create the form itself and its buttons

		echo '</div>';

	} // function

	private function render_remove_form() {
		
		$ajax_action = Volunteer_Admin_Controller::REMOVE_VOLUNTEER_AJAX_ACTION;
		$form_method = 'POST';
		$form_classes = 'reg-man-rc-cpt-remove-form volunteer-remove-form';
		$ajax_form = Ajax_Form::create( $ajax_action, $form_method, $form_classes );
		$form_content = $this->get_remove_form_content();
		$ajax_form->add_form_content( $form_content );
		$ajax_form->set_include_nonce_fields( FALSE ); // I will need to do this every time I render the form content
		$ajax_form->render();

	} // function
	
	/**
	 * Get the content of the remove form
	 * @return string
	 */
	public function get_remove_form_content() {
		ob_start();
			$this->render_form_content();
		$result = ob_get_clean();
		return $result;
	} // function
	
	private function render_form_content() {
		
		// This form is re-used on the client side to remove any volunteer
		// The form is initially rendered on the server with no volunteer details
		// When a volunteer is selected for removal, the volunteer ID is inserted by the client and it sends
		//  a request to get the form details for that volunteer

		wp_nonce_field( Volunteer_Admin_Controller::REMOVE_VOLUNTEER_AJAX_ACTION );

		$volunteer = $this->get_volunteer();
		if ( isset( $volunteer ) ) {
			// There is a volunteer so set up the form with the volunteer details and a 'Trash' request
			$record_id = $volunteer->get_id();
			$request_type = Volunteer_Admin_Controller::REMOVE_VOLUNTEER_REQUEST_TRASH;
		} else {
			// There is NO volunteer, we are initially rendering the form on the server side, 
			//  so set up the form with a request to 'Get' the details for some volunteer later
			$record_id = '';
			$request_type = Volunteer_Admin_Controller::REMOVE_VOLUNTEER_REQUEST_GET_FORM;
		} // endif

		echo "<input type=\"hidden\" name=\"record-id\" value=\"$record_id\" autocomplete=\"off\">";
		echo "<input type=\"hidden\" name=\"request-type\" value=\"$request_type\" autocomplete=\"off\">";
		
		echo '<div class="rc-reg-man-remove-form-details-container">';
		
			if ( isset( $volunteer ) ) {
				$this->render_volunteer_details();
			} // endif
			
		echo '</div>';
		
	} // function
	
	private function render_volunteer_details() {
		$volunteer = $this->get_volunteer();

		if ( isset( $volunteer ) ) {
			
			$title = __( 'Are you sure?', 'reg-man-rc' );
			echo "<h2>$title</h2>";
			
			$input_list = Form_Input_List::create();

			/* Translators: %s is the name of a volunteer */
			$format = __( 'Yes, trash the volunteer record for: %s', 'reg-man-rc' );
			$vol_label = isset( $volunteer ) ? esc_html( $volunteer->get_display_name() ) : '';
			if( isset( $volunteer ) && empty( $vol_label ) ) {
				$vol_label = $volunteer->get_id();
			} // endif
			$label = sprintf( $format, $vol_label );
			$name = 'is-remove-volunteer';
			$val = 'TRUE';
			$is_checked = FALSE;
			$hint = __( 'The volunteer record can be restored later if you change your mind', 'reg-man-rc' );
			$classes = '';
			$is_required = TRUE;
			$input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes, $is_required );
				
			$this->add_registration_options( $input_list );
			
			$label = __( 'Cancel', 'reg-man-rc' );
			$type = 'button';
			$classes = 'reg-man-rc-button reg-man-rc-remove-cpt-form-cancel';
			$input_list->add_form_button( $label, $type, $classes );
	
			$label = __( 'Trash Volunteer', 'reg-man-rc' );
			$type = 'submit';
			$classes = 'reg-man-rc-button reg-man-rc-remove-cpt-form-submit';
			$input_list->add_form_button( $label, $type, $classes );
	
			$input_list->render();
		
		} // endif
	} // function

	/**
	 * Add the inputs for dealing with existing registration records
	 * @param Form_Input_List $input_list
	 */
	private function add_registration_options( $input_list ) {

		$volunteer = $this->get_volunteer();
		
		if ( isset( $volunteer ) ) {
			
			$vol_reg_array = Volunteer_Registration::get_registrations_for_volunteer( $volunteer );
			$vol_reg_count = count( $vol_reg_array );
			
			if ( ! empty( $vol_reg_count ) ) {
				
				/* Translators: %s is a count of event registration records */
				$format_singular =	__( 'This volunteer has %s event registration record', 'reg-man-rc' );
				/* Translators: %s is a count of event registration records */
				$format_plural =	__( 'This volunteer has %s event registration records', 'reg-man-rc' );
				$label = sprintf( _n( $format_singular, $format_plural, $vol_reg_count, 'reg-man-rc' ), number_format_i18n( $vol_reg_count ) );
				$info_html = '';
				$hint = '';
				$classes = 'reg-man-rc-remove-cpt-record-info';
				$input_list->add_information( $label, $info_html, $hint, $classes );
				
				$vol_reg_fieldset = Form_Input_List::create();

				$radio_format = '<label for="%4$s"><input type="radio" id="%4$s" name="%2$s" value="%3$s" required="required" %5$s>%1$s</label>';

				// DO NOTHING
				$label = __( 'Do nothing', 'reg-man-rc' );
				$name = 'vol-reg-action';
				$val = Volunteer_Admin_Controller::VOL_REG_ACTION_NOTHING;
				$id = 'vol-reg-action-nothing';
				$addn_attrs = '';
				$radio_input = sprintf( $radio_format, $label, $name, $val, $id, $addn_attrs );
				$hint = __( 'Leave the event registrations in the system but attached to no volunteer', 'reg-man-rc' );
				$classes = 'reg-man-rc-remove-cpt-form-radio-simple';
				$vol_reg_fieldset->add_custom_html_input( '', $name, $radio_input, $hint, $classes );

				// TRASH
				$label = __( 'Trash event registrations', 'reg-man-rc' );
				$name = 'vol-reg-action';
				$val = Volunteer_Admin_Controller::VOL_REG_ACTION_TRASH;
				$id = 'vol-reg-action-trash';
				$classes = 'reg-man-rc-remove-cpt-form-radio-simple';
				$user_can_delete_all = Volunteer_Admin_Controller::get_current_user_can_delete_these_volunteer_registrations( $vol_reg_array );
				if ( $user_can_delete_all ) {
					$addn_attrs = '';
					$hint = __( 'Trash the event registrations so they are no longer visible', 'reg-man-rc' );
				} else {
					$addn_attrs = 'disabled="disabled"';
					$hint = __( 'You are not authorized to trash the event registration records for this volunteer', 'reg-man-rc' );
					$classes .= ' disabled';
				} // endif
				$radio_input = sprintf( $radio_format, $label, $name, $val, $id, $addn_attrs );
				$vol_reg_fieldset->add_custom_html_input( '', $name, $radio_input, $hint, $classes );
				
				// TRANSFER
				$label = __( 'Transfer event registrations to another volunteer record', 'reg-man-rc' );
				$name = 'vol-reg-action';
				$val = Volunteer_Admin_Controller::VOL_REG_ACTION_TRANSFER;
				$id = 'vol-reg-action-transfer';
				$user_can_transfer_all = Volunteer_Admin_Controller::get_current_user_can_edit_these_volunteer_registrations( $vol_reg_array );
				$addn_attrs = $user_can_transfer_all ? '' : 'disabled="disabled"';
				$radio_input = sprintf( $radio_format, $label, $name, $val, $id, $addn_attrs );
				
				if ( $user_can_transfer_all ) {
					$addn_attrs = '';
					$transfer_fieldset = Form_Input_List::create();
		
					$label = __( 'Transfer to', 'reg-man-rc' );
					$name = 'vol-reg-transfer-target';
					$options = self::get_volunteer_select_option_array();
					$selected = '';
					$hint = __( 'Transfer the event registration records to the selected volunteer.  Note that this cannot be undone.', 'reg-man-rc' );
					$classes = '';
					$is_required = TRUE;
					$addn_attrs = 'disabled="disabled" class="combobox"';
					$transfer_fieldset->add_select_input( $label, $name, $options, $selected, $hint, $classes, $is_required, $addn_attrs );
					
					$classes = 'reg-man-rc-remove-cpt-form-radio-fieldset';
					$hint = __( 'Use this option when there are two volunteer records representing the same person', 'reg-man-rc' );
					$vol_reg_fieldset->add_fieldset( $radio_input, $transfer_fieldset, $hint, $classes );

				} else {
					
					$addn_attrs = 'disabled="disabled"';
					$hint = '';
					$classes .= ' disabled';
					$hint = __( 'You are not authorized to transfer the event registration records for this volunteer', 'reg-man-rc' );
					$vol_reg_fieldset->add_custom_html_input( '', $name, $radio_input, $hint, $classes );
					
				} // endif
	
				$label = __( 'How should we handle the event registration records for this volunteer?', 'reg-man-rc' );
				$input_list->add_fieldset( $label, $vol_reg_fieldset );
				
			} // endif

		} // endif

	} // function
	
	private function get_volunteer_select_option_array() {

		$result = array();
		
		// I will exclude the volunteer being removed from the select
		$volunteer = $this->get_volunteer();
		$to_be_removed_vol_id = $volunteer->get_id();

		$all_volunteers = Volunteer::get_all_volunteers();
		
		foreach( $all_volunteers as $volunteer ) {
			$id = $volunteer->get_id();
			$label = $volunteer->get_label();
			if ( $id !== $to_be_removed_vol_id ) {
				$result[ $label ] = $id;
			} // endif
		} // endfor
		return $result;
		
	} // function

} // class