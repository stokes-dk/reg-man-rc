<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Map_Marker;
use Reg_Man_RC\View\Ajax_Form;

/**
 * An instance of this class represents an ajax form section displayed inside an object view.
 * The section may contain one or more forms.
 */
class Ajax_Form_Section implements Object_View_Section {

	private $ajax_form_array = array();

	// Callers should use one of the static factory methods
	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @param	Ajax_Form	$ajax_form	An optional form to be shown in the section
	 * @return	self
	 */
	public static function create( $ajax_form = NULL ) {
		$result = new self();
		if ( isset( $ajax_form ) ) {
			$result->add_ajax_form( $ajax_form );
		} // endif
		return $result;
	} // function

	/**
	 * Get the ajax form array
	 * @return Ajax_Form[]
	 */
	private function get_ajax_form_array() {
		return $this->ajax_form_array;
	} // function

	/**
	 * Add the ajax form
	 * @param Ajax_Form	$ajax_form	The form to be shown in this section
	 */
	public function add_ajax_form( $ajax_form ) {
		$this->ajax_form_array[] = $ajax_form;
	} // function

	/**
	 * Render this section
	 */
	public function render_section() {
		$ajax_form_array = $this->get_ajax_form_array();
		foreach ( $ajax_form_array as $ajax_form ) {
			echo '<div class="reg-man-rc-object-view-section reg-man-rc-object-view-ajax-form-section">';
				$ajax_form->render();
			echo '</div>';
		} // endfor
	} // function

} // class