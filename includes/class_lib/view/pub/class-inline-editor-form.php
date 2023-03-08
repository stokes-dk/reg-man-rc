<?php
namespace Reg_Man_RC\View\Pub;

class Inline_Editor_Form {
	/* This class represents the form used for inline editing
	 */
	private $form_input_list; // the form's inputs
	private $ajax_action; // The ajax action to receive the form when it is submitted
	private $class_names = ''; // Any additional classes the client wants to add to this form

	private function __construct() { }

	public static function create($form_input_list, $ajax_action) {
		$result = new self();
		$result->form_input_list = $form_input_list;
		$result->ajax_action = $ajax_action;
		return $result;
	} // function

	private function get_form_input_list() { return $this->form_input_list; }
	private function get_ajax_action() { return $this->ajax_action; }
	private function get_class_names() { return $this->class_names; }
	public function set_class_names( $class_names ) { $this->class_names = $class_names; }

	public function render() {
		$form_action = esc_url( admin_url('admin-ajax.php') );
		$ajax_action = $this->get_ajax_action();
		$form_input_list = $this->get_form_input_list();
		$classes = $this->get_class_names();
		echo "<div class=\"$classes reg-man-rc-inline-editor template\">";
			echo "<form action=\"$form_action\" method=\"POST\" data-ajax-action=\"$ajax_action\"" .
					" class=\"reg-man-rc-inline-editor-form reg-man-rc-ajax-form\">";
				$form_input_list->render();
//				echo '<div class="reg-man-rc-inline-editor-buttons">';
//					echo '<span class="cancel"><span class="dashicons dashicons-no"></span></span>';
//					echo '<span class="submit"><span class="dashicons dashicons-yes"></span></span>';
//				echo '</div>';
			echo '</form>';
		echo '</div>';

	} // function
} // class
