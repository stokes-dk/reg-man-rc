<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Volunteer_Registration_Controller;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;

class Volunteer_Registration_Form {

	const FORM_TYPE_EVENT_REGISTRATION	= 'event-reg';
	const FORM_TYPE_VOLUNTEER_PREF		= 'volunteer-pref';

	private $event;
	private $volunteer;
	private $volunteer_registration;
	private $input_list;
	private $ajax_form;
	private $ajax_action;
	private $form_type;

	private function __construct() {
	} // function

	/**
	 * Create an instance of this class for registration to the specified event.
	 * @param Volunteer	$volunteer
	 * @param Event		$event
	 * @return Volunteer_Registration_Form
	 */
	public static function create( $volunteer, $event ) {
		$result = new self();
		$result->volunteer = $volunteer;
		$result->event = $event;
		$event_key = $event->get_key();
		$result->volunteer_registration = Volunteer_Registration::get_registration_for_volunteer_and_event( $volunteer, $event_key );
		$result->form_type = self::FORM_TYPE_EVENT_REGISTRATION;
		return $result;
	} // function

	/**
	 * Create an instance of this class for updating the current volunteer's prefernces
	 * The volunteer registration record (if any) will be found by the event object based on the current volunteer (if any)
	 * @param Event		$event
	 * @return Volunteer_Registration_Form
	 */
	public static function create_for_volunteer_preferences( $volunteer ) {
		$result = new self();
		$result->form_type = self::FORM_TYPE_VOLUNTEER_PREF;
		$result->volunteer = $volunteer;
		return $result;
	} // function

	private function get_form_type() {
		return $this->form_type;
	} // function

	private function get_ajax_action() {
		if ( ! isset( $this->ajax_action ) ) {
			$form_type = $this->get_form_type();
			switch( $form_type ) {

				case self::FORM_TYPE_EVENT_REGISTRATION:
				default;
					$this->ajax_action = Volunteer_Registration_Controller::AJAX_VOLUNTEER_REGISTRATION_ACTION;
					break;

				case self::FORM_TYPE_VOLUNTEER_PREF:
					$this->ajax_action = Volunteer_Registration_Controller::AJAX_VOLUNTEER_PREFERENCES_ACTION;
					break;

			} // endswitch
		} // endif
		return $this->ajax_action;
	} // function

	/**
	 * Get the event object
	 * @return	Event
	 */
	private function get_event() {
		return $this->event;
	} // function

	private function get_volunteer() {
		if ( ! isset( $this->volunteer ) ) {
			$this->volunteer = Volunteer::get_current_volunteer();
		} // endif
		return $this->volunteer;
	} // function

	/**
	 * Get the volunteer registration object
	 * @return Volunteer_Registration
	 */
	private function get_volunteer_registration() {
		return $this->volunteer_registration;
	} // function

	public function get_ajax_form() {
		if ( ! isset( $this->ajax_form ) ) {
			$ajax_action = $this->get_ajax_action();
			$ajax_form = Ajax_Form::create( $ajax_action );
			// Because the form contents are replaced on the client side, I need to add a new nonce in the input list every time
			$ajax_form->set_include_nonce_fields( FALSE );
			$classes = 'reg-man-rc-volunteer-registration-form';
			$ajax_form->set_form_classes( $classes );
			$content = $this->get_form_content();
			$ajax_form->add_form_content( $content );
			$this->ajax_form = $ajax_form;
		} // endif
		return  $this->ajax_form;
	} // function

	/**
	 * Get the content for this form
	 * @param	boolean	$is_open	TRUE if the details section should be open, FALSE otherwise
	 * @return string
	 */
	public function get_form_content( $is_open = FALSE ) {
		$input_list = $this->get_input_list( $is_open );
		ob_start();
			// Add a nonce
			$name = '_wpnonce';
			$ajax_action = $this->get_ajax_action();
			$value = wp_nonce_field( $ajax_action ); // Note that this renders the nonce in place
			// Render the input list
			$input_list->render();
		$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Get the input list for the form
	 * @param	boolean	$is_open	TRUE if the details section should be open, FALSE otherwise
	 * @return Form_Input_List
	 */
	private function get_input_list( $is_open = FALSE ) {
		$form_type = $this->get_form_type();
		if ( $form_type === self::FORM_TYPE_VOLUNTEER_PREF ) {
			$result = self::get_preferences_input_list();
		} else {
			$result = self::get_registration_input_list( $is_open );
		} // endif
		return $result;
	} // function

	/**
	 * Get the input list for the form
	 * @param	boolean	$is_open	TRUE if the details section should be open, FALSE otherwise
	 * @return Form_Input_List
	 */
	private function get_registration_input_list( $is_open = FALSE ) {

		if ( ! isset( $this->input_list ) ) {

			$event = $this->get_event();
			$is_non_repair = $event->get_event_descriptor()->get_event_is_non_repair();
			$vol_reg = $this->get_volunteer_registration();

			$is_registered = isset( $vol_reg );
			$is_event_complete = $event->get_is_event_complete();
			$is_event_cancelled = $event->get_is_event_cancelled();

			$volunteer = $this->get_volunteer();
			$fixer_stations = $event->get_fixer_stations(); // If this is empty then it's a non-repair event

			$input_list = Form_Input_List::create();
			$classes = $is_registered ? '' : 'not-registered';
			$classes .= $is_non_repair ? ' non-repair-event' : '';
			$input_list->add_list_classes( $classes );
			// Note that this form will not be rendered if the event is in the past, so we will only use present/future tense

			// We ALWAYS show the registration status on the volunteer event page
			//  even when the event is in the past or has been cancelled and there is no way to modify the registration

			// Registration status
			$this->add_registration_status( $input_list, $is_registered, $volunteer );

			if ( ( ! $is_event_complete ) && ( ! $is_event_cancelled ) ) {

				// Event
				$name = 'event-key';
				$event_key = $event->get_key();
				$input_list->add_hidden_input( $name, $event_key );

				// Volunteer
				$name = 'volunteer-id';
				$volunteer_id = $volunteer->get_id();
				$input_list->add_hidden_input( $name, $volunteer_id );

				// Is registered?
				// Add a hidden input with value always 1 so the volunteer can do signup or change registration
				// If the volunteer wants to cancel their registration then we will modify this input on the client side
				$name = 'is-register';
				$value = 1;
				$input_list->add_hidden_input( $name, $value );

				if ( ( $is_registered ) && ( ! $is_non_repair ) ) {

					// Tell the controller not to use the volunteer's defaults when they are updating their registration
					$name = 'use-volunteer-defaults';
					$value = 0;
					$input_list->add_hidden_input( $name, $value );

					$details_list = Form_Input_List::create();

						// Fixer station
						$this->add_fixer_station( $details_list, $volunteer, $vol_reg );

						// Volunteer roles
						$this->add_volunteer_roles( $details_list, $volunteer, $vol_reg );

					$open_attr = $is_open ? 'open="open"' : '';
					ob_start();
						$title = __( 'Details', 'reg-man-rc' );
						echo "<details $open_attr>";
							echo '<summary>';
								echo $title;
							echo '</summary>';
							$details_list->render();
						echo '</details>';
					$details = ob_get_clean();
					$input_list->add_custom_html_input( '', '', $details );

				} else {

					// Tell the controller to use the volunteer's defaults when they are creating a new registration
					$name = 'use-volunteer-defaults';
					$value = 1;
					$input_list->add_hidden_input( $name, $value );

				} // endif

				// Action button (Register or Cancel, depending on the current registration status)
				$this->add_action_button( $input_list, $is_registered, $volunteer );

			} // endif

			// Don't bother with the marker for required fields
			$input_list->set_required_inputs_flagged( FALSE );

			$this->input_list = $input_list;

		} // endif

		return $this->input_list;

	} // function

	/**
	 * Get the input list for the form
	 * @return Form_Input_List
	 */
	private function get_preferences_input_list() {
		if ( ! isset( $this->input_list ) ) {

			$input_list = Form_Input_List::create();
			$volunteer = $this->get_volunteer();

			// Info
			$label = '';
			$info_html = __( 'Select your preferred fixer station and volunteer roles', 'reg-man-rc' );
			$hint = __( 'These will be automatically applied when you register for events', 'reg-man-rc' );
			$classes = 'reg-man-rc-vol-reg-preferences-info';
			$input_list->add_information( $label, $info_html, $hint, $classes );

			// Fixer station
			$this->add_fixer_station( $input_list, $volunteer );

			// Volunteer roles
			$this->add_volunteer_roles( $input_list, $volunteer );

			$this->input_list = $input_list;

		} // endif

		return $this->input_list;

	} // function

	/**
	 * Add the registration status item to the input list
	 * @param Form_Input_List	$input_list
	 * @param boolean			$is_registered
	 * @param Volunteer			$volunteer
	 */
	private function add_registration_status( $input_list, $is_registered, $volunteer ) {

		$event = $this->get_event();
		$is_event_complete = $event->get_is_event_complete();

		$format =
			'<span class="reg-man-rc-icon-text-container">' .
				'<span class="dashicons dashicons-%2$s icon"></span><span class="text">%1$s</span>' .
			'</span>';

		// When the status is for the current volunteer the label should be like "You are registered"
		// When it's for someone other than the current volunteer it should be like "Dave is registered"
		$curr_vol = Volunteer::get_current_volunteer();
		$vol_for_label = ( $curr_vol->get_id() == $volunteer->get_id() ) ? NULL : $volunteer;
		$label_text = Volunteer_Registration_View::create_registration_status_label( $event, $is_registered, $vol_for_label );
		$icon = $is_registered ? 'yes' : 'no';

		$label = sprintf( $format, $label_text, $icon );
		$info_html = '';
		$input_list->add_information( $label, $info_html );

	} // function

	/**
	 * Add the action button to the input list
	 * @param Form_Input_List	$input_list
	 * @param boolean			$is_registered
	 * @param Volunteer			$volunteer
	 */
	private function add_action_button( $input_list, $is_registered, $volunteer ) {

		$current_volunteer = Volunteer::get_current_volunteer();
		$is_current_volunteer = ( $current_volunteer->get_id() == $volunteer->get_id() );

		if ( $is_registered ) {

			$label_text = ( $is_current_volunteer ) ? __( 'Not able to make it?', 'reg-man-rc' ) : '';
			$button_text = esc_html__( 'Cancel registration', 'reg-man-rc' );
			$label = "<span class=\"dashicons dashicons-dismiss\"></span><span class=\"label-text\">$button_text</span>";
			$classes = 'reg-man-rc-volunteer-registration-form-cancel-button reg-man-rc-icon-text-container';
			$info_html = "<button type=\"button\" class=\"$classes\">$label</button>";

		} else {

			$label_text = '';
			$button_text = esc_html__( 'Register', 'reg-man-rc' );
			$label = "<span class=\"dashicons dashicons-welcome-write-blog\"></span><span class=\"label-text\">$button_text</span>";
			$classes = 'reg-man-rc-volunteer-registration-form-signup-button reg-man-rc-icon-text-container';
			$info_html = "<button type=\"submit\" class=\"$classes\">$label</button>";

		} // endif

		$label = "<span class=\"label-text\">$label_text</span>";
		$input_list->add_information( $label, $info_html );

	} // function

	/**
	 * Add the fixer station input
	 * @param Form_Input_List			$input_list
	 * @param Volunteer					$volunteer
	 * @param Volunteer_Registration	$vol_reg
	 */
	private function add_fixer_station( $input_list, $volunteer, $vol_reg = NULL ) {

		$label = __( 'Fixer Station', 'reg-man-rc' );
		$name = 'station-id';
		$options = array();
		// Add "none" option
		$options[ __( '– none –', 'reg-man-rc' ) ] = 0;
		$all_fixer_stations = Fixer_Station::get_all_fixer_stations();
		foreach( $all_fixer_stations as $station ) {
			$options[ $station->get_name() ] = $station->get_id();
		} // endfor
		$station = isset( $vol_reg ) ? $vol_reg->get_fixer_station() : $volunteer->get_preferred_fixer_station();
		$selected_station_id = isset( $station ) ? $station->get_id() : 0;
//		Error_Log::var_dump( $volunteer->get_preferred_fixer_station() );
		$hint = '';
		$classes = 'reg-man-rc-volunteer-registration-form-part fixer-station';
		$classes .= ' volunteer-reg-form-hide-on-not-registered volunteer-reg-form-hide-on-non-repair-event';
		$is_required = FALSE; // Not required if the person is un-registering
		$input_list->add_select_input( $label, $name, $options, $selected_station_id, $hint, $classes, $is_required );

		// Is apprentice
		$label = __( 'Apprentice', 'reg-man-rc' );
		$name = 'is-apprentice';
		$val = 1;
		$is_checked = ( isset( $vol_reg ) && $vol_reg->get_is_fixer_apprentice() ) ? 1 : $volunteer->get_is_fixer_apprentice();
		$hint = 'Apprentice alongside an experienced fixer';
		$classes = 'reg-man-rc-volunteer-registration-form-part is-apprentice';
		$classes .= ' volunteer-reg-form-hide-on-not-registered volunteer-reg-form-hide-on-non-repair-event';
		$is_required = FALSE; // Not required if the person is un-registering
		$custom_label = NULL;
		$custom_value = NULL;
		$is_compact = TRUE;
		$addn_attrs = ( $selected_station_id == 0 ) ? 'disabled="disabled"' : '';
		$input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes, $is_required, $addn_attrs );

	} // function

	/**
	 * Add the fixer station input
	 * @param Form_Input_List			$input_list
	 * @param Volunteer					$volunteer
	 * @param Volunteer_Registration	$vol_reg
	 */
	private function add_volunteer_roles( $input_list, $volunteer, $vol_reg = NULL ) {

		$label = __( 'Volunteer Roles', 'reg-man-rc' );
		$roles_fieldset = Form_Input_List::create();
		$input_name = 'role-id[]';
		$options = array();
		$all_roles = Volunteer_Role::get_all_volunteer_roles();
		$selected_roles = isset( $vol_reg ) ? $vol_reg->get_volunteer_roles_array() : $volunteer->get_preferred_roles();
		$selected_role_ids = array();
		foreach( $selected_roles as $role ) {
			$selected_role_ids[] = $role->get_id();
		} // endfor
		foreach( $all_roles as $role ) {
			$role_name = $role->get_name();
			$role_id = $role->get_id();
			$is_checked = in_array( $role_id, $selected_role_ids );
			$roles_fieldset->add_checkbox_input( $role_name, $input_name, $role_id, $is_checked );
		} // endfor
		$hint = '';
		$classes = 'reg-man-rc-volunteer-registration-form-part volunteer-roles';
		$classes .= ' volunteer-reg-form-hide-on-not-registered volunteer-reg-form-hide-on-non-repair-event';
		$input_list->add_fieldset( $label, $roles_fieldset, $hint, $classes );

	} // function

} // class