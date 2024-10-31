<?php
namespace Reg_Man_RC\View;


use Reg_Man_RC\Model\Error_Log;

/**
 * Provides a standard facility for creating a list of input fields in a form.
 *
 * Using this class provides an easy way to render inputs into a form and ensures that all forms have the same look and feel
 */
class Form_Input_List {

	/**
	 * A marker for input field that allows the user
	 * to enter a custom value, e.g. Other, please specify: _____
	 *
	 * This marker is used as the value attribute for the radio button associated with the custom input field.
	 * The value needs to be something not likely to occur as a value for another button in the group.
	 * This is a public constant because other classes will need to find the input's value when the form is submitted
	 */
	const RADIO_GROUP_CUSTOM_INPUT_VALUE = '__custom__input__';
	/**
	 * The current id number for inputs.
	 *
	 * Each input must have an id attribute so that we can
	 * properly add labels to all inputs.  For inputs that do not have an id specified by the caller we will automatically
	 * generate one.  This static class member acts as a counter to number each input element.
	 * It is static rather than an instance variable because a page may contain multiple forms but the IDs must be unique on the page.
	 */
	private static $CURR_ID_NUM = 0;

	/**
	 * Generate an input ID
	 *
	 * @param	string	$id_start	(optional) A string prefix for the id
	 * @return	string	A unique input id
	 */
	private static function get_input_id( $id_start = 'gen-input-id' ) {
		$id_start = str_replace('[]', '', $id_start);
		return $id_start . self::$CURR_ID_NUM++;
	} // function

	/**
	 * The form inputs
	 *
	 * Inputs are defined by an aassociative array:
	 *		name => array(
	 *		'id' => input id -- each input must have an id so we can label
	 *		'label' => input label, e.g. 'Email Address'
	 *		'input' => input html '<input .../>'
	 *		'classes' => optional space-separated list of classes to apply to the input item
	 *		'hint' => optional hint text for the input, e.g. 'Please enter...'
	 *	);
	 */
	private $input_array = array();

	/** an array of errors for the form, usually from a FormResponse object */
/* TODO - Do I need to support errors on the server side.  If so then why not WP_Error?
	private $error_array;
*/
	/** The form's style or layout.  By default form inputs are shown in a vertical list */
	private $style = 'vertical';

	/** The space-separated list of classes for the form list's class attribute */
	private $list_classes = '';

	private $required_inputs_flagged; // TRUE if required inputs should be flagged for the user

	/**
	 * An array of button HTML (if any buttons are added to the form input list).
	 * E.g. array(
	 * 		'<button type="button" class="my-button-class" name="cancel" disabled="disabled">Cancel</button>',
	 * 		'<button type="submit" class="my-button-class" name="cancel" value="submit">Submit</button>'
	 * );
	 */
	private $button_array = array();

	// A set of classes to be added to the container for button
	private $button_container_classes = '';

	public static function create() {
		return new self();
	} // function

	private function get_list_classes() {
		return $this->list_classes;
	} // function

	/**
	 * Add classes to be assigned to this list
	 * @param string $list_classes
	 */
	public function add_list_classes( $list_classes ) {
		$this->list_classes .= $list_classes;
	} // function

	public function set_style_horizontal() { $this->style = 'horizontal'; }
	public function set_style_vertical() { $this->style = 'vertical'; }
	public function set_style_compact() { $this->style = 'compact'; }
	private function get_style() { return $this->style; }

	public function get_required_inputs_flagged() {
		if ( ! isset( $this->required_inputs_flagged ) ) {
			// By default we will flag required inputs on public website and not flag them on the admin side
			$this->required_inputs_flagged = is_admin() ? FALSE : TRUE;
		} // endif
		return $this->required_inputs_flagged;
	} // function
	
	public function set_required_inputs_flagged( $is_required_inputs_flagged ) {
		$this->required_inputs_flagged = boolval( $is_required_inputs_flagged );
	} // function

	private function get_input_array() {
		return $this->input_array;
	} // function

/* TODO - Do I need to support errors on the server side.  If so then why not WP_Error?
	private function get_error_array() { return $this->error_array; }
	public function set_error_array( $error_array ) { $this->error_array = $error_array; }
*/
	public function render() {
		$style = $this->get_style();
		$list_classes = $this->get_list_classes();
		$flag_req = $this->get_required_inputs_flagged() ? 'flag-required' : '';
		echo "<ul class=\"form-input-list $list_classes $flag_req $style\">";
			$format =
				'<li class="input-item %3$s">' .
					'<div class="label-container">%1$s</div>' .
					'<div class="input-container">%2$s</div>' .
					'<div class="hint-container">%4$s</div>' .
					'<div class="error-container">%5$s</div>' .
				'</li>';
			$input_array = $this->get_input_array();
/* TODO - do I need to support errors on server side
			$error_array = $this->get_error_array();
			if (!is_array($error_array)) $error_array = array();
*/
			foreach( $input_array as $details ) {
				$name = $details['name'];
				$id 			= isset( $details['id'] ) 		? $details['id']		: self::get_input_id($name);
				$label			= isset( $details['label'] )	? $details['label']		: '';
				$input			= isset( $details['input'] )	? $details['input']		: '';
				$classes		= isset( $details['classes'] )	? $details['classes']	: '';
				$is_required	= isset( $details['required'] )	? $details['required']	: FALSE;
				$hint			= isset( $details['hint'] )		? $details['hint']		: '';
				$error = '';
				if ( $is_required ) {
					$classes .= ' required';
				} // endif
				if ( ! empty( $label ) ) {
					$for = ( $id !== '' ) ? "for=\"$id\"" : '';
					$label = "<label $for>$label</label>";
				} else {
					$label = ''; // for a checkbox the label is in the input and already has 'for'
				} // endif
				printf( $format, $label, $input, $classes, $hint, $error );
			} // endfor
			if ( $this->get_required_inputs_flagged() ) {
				echo '<li class="input-item required-note">Indicates required field</li>';
			} // endif
		echo '</ul>';

		$button_array = $this->get_button_array();
		if ( ! empty( $button_array ) ) {
			$container_classes = $this->get_button_container_classes();
			$container_classes = "form-input-list-button-container $container_classes";
			echo "<div class=\"$container_classes\">";
				foreach( $button_array as $button ) {
					echo $button;
				} // endfor
			echo '</div>';
		} // endif
	} // function

	private function get_button_array() {
		return $this->button_array;
	} // function

	/**
	 * Add a custom input to the form by supplying its html
	 * @param	string	$label		The text to show in the label for the input
	 * @param	string	$name		The name of the input, i.e. the name attribute value
	 * @param	string	$input_html	The html for the input to be inserted into the form
	 * @param	string	$hint		(optional) The helpful hint text to show as a tooltip associated with the input
	 * @param	string	$classes	(optional) The classes to assign to the container element for the input
	 * @param	string	$id			(optional) The element's id attribute, used to assign the 'for' attribute of the label
	 * @return	void
	 */
	public function add_custom_html_input( $label, $name, $input_html, $hint = '', $classes = '', $is_required = FALSE, $id = NULL ) {
		if ($id === NULL) $id = self::get_input_id( $name );
		$details = array(
			'name' => $name,
			'id' => $id,
			'label' => $label,
			'input' => $input_html,
		);
		$details['classes'] = is_string( $classes ) ? $classes : '';
		$details['required'] = boolval( $is_required );
		$details['hint'] = is_string( $hint ) ? $hint : '';
		$this->input_array[] = $details;
	} // function

	private function add_input( $label, $name, $type, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {

		$id = self::get_input_id( $name ) ; // gen an input id using the name
/* TODO - do we need to allow error setting on the server side?
		$error_array = $this->get_error_array();
		$error = ( is_array( $error_array ) && isset( $error_array[$name] ) ) ? $error_array[$name] : NULL;
		// if there's an error we need to show the field with the error value in it
		if ( ! empty( $error ) ) {
			$val = htmlspecialchars( $error->getFieldValue() );
		} // endif
*/

		// Autocomplete on Firefox has caused so many problems that I need to turn it ofF ALWAYS!
		$addn_attrs .= ' autocomplete="off"';

		if ( $is_required ) {
			$addn_attrs .= ' required="required"';
		} // endif
		
		switch ( strtolower( $type ) ) {
			
			case 'textarea':
				$input_html = "<textarea name=\"$name\" id=\"$id\" $addn_attrs>$val</textarea>";
				$classes .= ' textarea';
				break;

			case 'checkbox':
				$input_html = "<label for=\"$id\">" .
								"<input name=\"$name\" type=\"$type\" id=\"$id\" value=\"$val\" $addn_attrs/>" .
								"<span>$label</span></label>";
				$label = '';
				break;

			case 'radio':
				$input_html = "<label for=\"$id\">" .
								"<input name=\"$name\" type=\"$type\" id=\"$id\" value=\"$val\" $addn_attrs/>" .
								"<span>$label</span></label>";
				$label = '';
				break;

			default:
				$input_html = "<input name=\"$name\" type=\"$type\" id=\"$id\" value=\"$val\" $addn_attrs/>";
				break;
				
		} // endswitch
		
		$this->add_custom_html_input( $label, $name, $input_html, $hint, $classes, $is_required, $id );
	
	} // function

	public function add_text_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'text', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_number_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'number', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_date_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'date', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_colour_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'color', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_time_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'time', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_email_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = ''  ) {
		$this->add_input( $label, $name, 'email', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_password_input( $label, $name, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$this->add_input( $label, $name, 'password', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_file_input( $label, $name, $hint = '', $classes = '', $is_required = FALSE, $accept = '' ) {
		$this->add_input( $label, $name, 'file', $val = '', $hint, $classes, $is_required, $addn_attrs = "accept=\"$accept\"" );
	} // function

	public function add_checkbox_input( $label, $name, $val = '', $is_checked = FALSE, $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$addn_attrs .= ( $is_checked ) ? ' checked="checked"' : '';
		$classes .= ' checkbox';
		$this->add_input( $label, $name, 'checkbox', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_radio_button_input( $label, $name, $val = '', $is_checked = FALSE, $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$addn_attrs .= ( $is_checked ) ? ' checked="checked"' : '';
		$classes .= ' checkbox';
		$this->add_input( $label, $name, 'radio', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_text_area_input( $label, $name, $rows, $val = '', $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '' ) {
		$rows = "rows=\"$rows\"";
		$addn_attrs ="$addn_attrs $rows";
		$this->add_input( $label, $name, 'textarea', $val, $hint, $classes, $is_required, $addn_attrs );
	} // function

	public function add_hidden_input( $name, $value ) {
		// This is used in rare instances where we're building a form input list and need a hidden field
		//	but we're not sure yet when the form will be rendered so we can't just echo the field in place
		$input_html = "<input type=\"hidden\" name=\"$name\" value=\"$value\" autocomplete=\"off\">";
		$this->add_custom_html_input( '', $name, $input_html, '', 'hidden-input', '' );
	} // function

	public function add_information( $label, $info_html, $hint = '', $classes = '' ) {
		// This can be used to show information or help text for an input form
		$name = self::get_input_id( 'info-' ); // everything needs a name internally so I'll gen one
		$classes .= ' info';
		$this->add_custom_html_input( $label, $name, $info_html, $hint, $classes, '' );
	} // function

	public function add_fieldset( $label, $form_input_list, $hint = '', $classes = '', $is_required = FALSE ) {
		// This can be used to add a group of fields under a single label
		// $form_input_list is an instance of this class which will be shown (kind of recursively) in this instance
		// I will generate a name for the fieldset because internally I need a name for each input
		$name = self::get_input_id('fieldset-');
		$classes .= ' fieldset';
		ob_start();
			echo "<fieldset class=\"form-input-list-fieldset\"><legend class=\"form-input-list-legend\">$label</legend>";
				$form_input_list->render();
			echo '</fieldset>';
		$fieldset_html = ob_get_clean();
		$this->add_custom_html_input('', $name, $fieldset_html, $hint, $classes, $is_required );
	} // function

	public function add_details_section( $label, $form_input_list, $is_open = FALSE, $hint = '', $classes = '', $is_required = FALSE ) {
		// This can be used to add a collapsible group of fields under a single label
		// $form_input_list is an instance of this class which will be shown (kind of recursively) in this instance
		// I will generate a name for the details section because internally I need a name for each input
		$name = self::get_input_id('details-');
		$classes .= ' details';
		ob_start();
			$open_attr = ( $is_open ) ? 'open="open"' : '';
			echo "<details $open_attr><summary>$label</summary>";
				$form_input_list->render();
			echo '</details>';
		$details_html = ob_get_clean();
		$this->add_custom_html_input( '', $name, $details_html, $hint, $classes, $is_required );
	} // function

	/**
	 * Add a select element to the input group
	 * @param	string	$label		The text to show in the label for the input
	 * @param	string	$name		The name of the input, i.e. the name attribute value
	 * @param	string[][]	$options	An associative array of options keyed by option text, i.e. option text => option value.
	 * To create an option group specify the option value as another associative array of labels and values.
	 * @param	string	$selected	(optional) The value of the option to be selected
	 * @param	string	$hint		(optional) Hint text to be displayed with the input
	 * @param	string	$classes	(optional) A list of class names for the element
	 * @param	boolean	$is_required A flag to indicate whether the input is required
	 * @param	string	$addn_attrs	Additional attributes to be applied to the element
	 * @return	void
	 */
	public function add_select_input( $label, $name, $options, $selected = NULL, $hint = '', $classes = '', $is_required = FALSE, $addn_attrs = '', $is_include_please_select = FALSE ) {
		$id = self::get_input_id( $name ); // gen an input id using the name
		$req_attr = $is_required ? 'required="required"' : '';
		ob_start();
			echo "<select name=\"$name\" id=\"$id\" autocomplete=\"off\" $req_attr $addn_attrs>";
				$option_format = '<option value="%2$s" %3$s %4$s>%1$s</option>';
				if ( $is_include_please_select ) {
					$text = __( '--Please select--', 'reg-man-rc' );
					$val = '';
					$sel = ( empty( $selected ) ) ? 'selected="selected"' : '';
					$attrs = 'disabled="disabled"';
					printf( $option_format, $text, $val, $sel, $attrs );
				} // endif
				$attrs = '';
				foreach ( $options as $option_text => $option_value ) {
					if ( is_array( $option_value ) ) {
						echo "<optgroup label=\"$option_text\">";
							foreach( $option_value as $sub_option_text => $sub_option_value ) {
								// If this is "multiple" then selected is an array
								if ( is_array( $selected ) ) {
									$sel = in_array( $sub_option_value, $selected ) ? 'selected="selected"' : '';
								} else {
									$sel = ( $sub_option_value === $selected ) ? 'selected="selected"' : '';
								} // endif
								$text = esc_html( $sub_option_text );
								$val = esc_attr( $sub_option_value );
								printf( $option_format, $text, $val, $sel, $attrs );
							} // endfor
						echo'</optgroup>';
					} else {
						// If this is "multiple" then selected is an array
						if ( is_array( $selected ) ) {
							$sel = in_array( $option_value, $selected ) ? 'selected="selected"' : '';
						} else {
							$sel = ( $option_value === $selected ) ? 'selected="selected"' : '';
						} // endif
						$text = esc_html( $option_text );
						$val = esc_attr( $option_value );
						printf( $option_format, $text, $val, $sel, $attrs );
					} // endif
				} // endfor
			echo '</select>';
		$input_html = ob_get_clean();
		$this->add_custom_html_input( $label, $name, $input_html, $hint, $classes, $is_required, $id );
	} // function

	public function add_radio_group( $label, $name, $options, $selected = NULL, $hint = '',
					$classes = '', $is_required = FALSE, $custom_label = NULL, $custom_value = NULL, $is_compact = FALSE ) {
		// $options - an associative array of options labels and values, e.g. array( "Yes" => 1 );
		$id = self::get_input_id($name); // gen an input id using the name
		$classes .= ' fieldset radio-group';
/* TODO - do I need to support errors on the server side?
		$error_array = $this->get_error_array();
		if ( is_array( $error_array ) && isset( $error_array[ $name ] ) ) $error = $error_array[ $name ];
		if ( ( $selected === NULL ) && ( ! empty( $error ) ) ) {
			// if there's an error then the selected item should be the error value
			$selected = htmlspecialchars( $error->getFieldValue() );
		} // endif
*/
		if ( ! is_array( $options ) ) {
			$options = array(); // defensive check
		} // endif
		$index = 0;
		$input_html = '<fieldset>';
		$input_html .= "<legend>$label</legend>";
		$list_class = ( $is_compact ) ? 'compact' : 'vertical';
		$input_html .= "<ul class=\"form-input-list radio-input-list $list_class\">";
		$req_attr = $is_required ? 'required="required"' : '';
		$format =
			'<li class="input-item %7$s">' .
				'<div class="input-container">' .
					'<label for="%1$s">' .
						'<input type="radio" name="%2$s" id="%1$s" value="%3$s" class="radio %7$s" %5$s autocomplete="off"><span>%4$s</span>' .
					'</label>' .
					'%6$s' .
				'</div>' .
		'</li>';
		foreach ( $options as $key => $value) {
			$attrs = ( $value === $selected ) ? "checked=\"checked\" $req_attr" : $req_attr;
			$for = $id . '_' . $index++;
			$input_html .= sprintf( $format, $for, $name, $value, $key, $attrs, '', '' );
		} // endfor
		if ( $custom_label !== NULL ) {
			$for = "{$id}_custom";
			$value = self::RADIO_GROUP_CUSTOM_INPUT_VALUE;
			if ( $value == $selected ) {
				$text_field_attrs = 'required="required"';
				$radio_attrs = "checked=\"checked\" $req_attr";
			} else {
				$text_field_attrs = 'disabled="disabled"';
				$radio_attrs = $req_attr;
			} // endif
			$custom_value = ( $custom_value !== NULL ) ? ' value="' . htmlspecialchars($custom_value) . '"' : '';
			$text_input = "<input class=\"radio-custom-input\" name=\"{$name}_custom\" type=\"text\" $custom_value $text_field_attrs>";
			// I will put the input together with the label
			$input_html .= sprintf( $format, $for, $name, $value, "$custom_label $text_input", $radio_attrs, '', 'custom' );
		} // endif
		$input_html .= '</ul></fieldset>';
		// There item in the input has a label that is not connected to a specific input
		//	so the last arg below is '' because the label should have no 'for' attribute
		$this->add_custom_html_input('', $name, $input_html, $hint, $classes, $is_required );
	} // function

	/**
	 * Add a button to the form input list
	 * @param string $label
	 * @param string $type
	 * @param string $classes
	 * @param string $addn_attrs
	 */
	public function add_form_button( $label, $type = 'button', $classes = '', $addn_attrs = '' ) {
		$class_attr = ! empty( $classes ) ? "class=\"$classes\"" : '';
		$format = '<button type="%2$s" %3$s %4$s>%1$s</button>';
		$button = sprintf( $format, $label, $type, $class_attr, $addn_attrs );
		$this->button_array[] = $button;
	} // function

	private function get_button_container_classes() {
		return $this->button_container_classes;
	} // function
	
	/**
	 * Add classes to the button container
	 * @param string $classes
	 */
	public function set_button_container_classes( $classes ) {
		$this->button_container_classes = $classes;
	} // function

} // class
?>
