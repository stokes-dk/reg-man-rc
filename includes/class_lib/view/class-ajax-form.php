<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class will render a <form> element with the correct content to be used with Wordpress' ajax
 *
 * This class provides the user interface
 *
 * @since v0.1.0
 *
 */
class Ajax_Form {

	const FORM_ID_PREFIX = 'reg-man-rc-ajax-form-';

	private static $AJAX_FORM_NUMBER = 0;

	/** @var	string	$ajax_action	The action string passed to the server to indicate how to handle the ajax request */
	private $ajax_action;

	/** @var	string	$form_id		The ID of the form element */
	private $form_id;

	/** @var	string	$form_method	The method string passed to the server to indicate how to handle the ajax request */
	private $form_method = 'POST';

	/** @var	string	$form_classes	A space-separated list of class names to be added tot he form element */
	private $form_classes = '';

	/** @var	string	$form_content	The content of the form */
	private $form_content = '';

	/** @var	boolean	$is_file_upload	A boolean indicating whether the form contains a file upload.
	 * This affects how the form should be encoded.
	 * If this is true then the form will include an attribute like 'encType="multipart/form-data"'.
	 * If it's false then the form will not include an encType attribute.
	 **/
	private $is_file_upload = FALSE;

	/** @var	boolean	$is_replace_content_with_response_html	A boolean flag indicating whether the form
	 * contents should be automatically replaced if HTML is returned as the form response.
	 * This affects how the form should be encoded.
	 * If this is true then the form's contents will be replaced with the html returned from an AJAX submit
	 * If it's false then the form will not be replaced
	 **/
	private $is_replace_content_with_response_html = TRUE;

	private $include_nonce_fields = TRUE;

	/**
	 * Getter for the ajax action property
	 * @return	string	The action string passed to the server to indicate how to handle the ajax request
	 */
	private function get_ajax_action() {
		return $this->ajax_action;
	} // function

	/**
	 * Get the form method property
	 * @return	string	The form method passed to the server to indicate the type of request, e.g. 'POST' or 'GET'
	 */
	private function get_form_method() {
		return $this->form_method;
	} // function

	public function get_form_id() {
		if ( ! isset( $this->form_id ) ) {
			$curr_num = self::$AJAX_FORM_NUMBER++;
			$this->form_id = self::FORM_ID_PREFIX . $curr_num;
		} // endif
		return $this->form_id;
	} // function

	/**
	 * Getter for the flag indicating whether to include nonce fields in the form
	 * @return	boolean		A flag set to TRUE if nonce fields are to be included in the form, FALSE otherwise
	 */
	private function get_include_nonce_fields() {
		return $this->include_nonce_fields;
	} // function

	/**
	 * Set the flag to indicate whether nonce fields should be included in the form
	 *
	 * By default, nonce fields will be included in the form.
	 * To prevent nonce fields from being included call this method with an argument of FALSE
	 *
	 * @param	boolean		$include_nonce_fields	TRUE if nonce fields should be included in the form, FALSE otherwise
	 * @return	void
	 */
	public function set_include_nonce_fields( $include_nonce_fields ) {
		$this->include_nonce_fields = $include_nonce_fields;
	} // function

	/**
	 * Getter for the classes property
	 * @return	string	A string separated list of classes to be added to the form element
	 */
	private function get_form_classes() {
		return $this->form_classes;
	} // function

	/**
	 * Set the classes for the form
	 *
	 * Use this method to insert assign the space-separated list of classes to use in the form element
	 *
	 * @param	string	$form_classes	The space-separated list of classes to be used in the form element
	 * @return	void
	 */
	public function set_form_classes($form_classes) {
		$this->form_classes = $form_classes;
	} // function

	/**
	 * Getter for the is_file_upload property
	 * @return	boolean		A flag indicating whether the form is used for a file upload
	 */
	private function get_is_file_upload() {
		return $this->is_file_upload;
	} // function

	/**
	 * Set the flag for a file upload form
	 *
	 * Use this method to indicate that the form is used for a file upload.
	 * Setting this to TRUE will cause the form to have an attribute like 'encType="multipart/form-data"'
	 *
	 * @param	boolean	$is_file_upload		TRUE if this form contains a file upload, FALSE otherwise.
	 * @return	void
	 */
	public function set_is_file_uplaod( $is_file_upload ) {
		$this->is_file_upload = boolval( $is_file_upload );
	} // function

	/**
	 * Getter for the property which tells the client side whether to
	 * replace the form contents when HTML is returned from the AJAX submit.
	 * By default this is true
	 * @return	boolean		A flag indicating whether the form should be replaced when HTML content is returned
	 */
	private function get_is_replace_content_with_response_html() {
		return $this->is_replace_content_with_response_html;
	} // function

	/**
	 * Set the flag to indicate whether the client side should replace the form contents when HTML is returned from the AJAX submit.
	 * By default this is true.
	 *
	 * Setting this to FALSE means that nothing will happen automatically when HTML is returned from the AJAX submit.
	 * Setting this to TRUE will cause the form content to be replaced when HTML is returned from the AJAX submit.
	 *
	 * @param	boolean	$is_replace_content_with_response_html	TRUE if this form's contents are to be replaced when
	 *  HTML is returned from the server.  FALSE if no action should be taken when HTML is returned.
	 * @return	void
	 */
	public function set_is_replace_content_with_response_html( $is_replace_content_with_response_html ) {
		$this->is_replace_content_with_response_html = boolval( $is_replace_content_with_response_html );
	} // function

	/**
	 * Getter for the form_content property
	 * @return	string	The contents of the form as a string
	 */
	private function get_form_content() {
		return $this->form_content;
	} // function

	/**
	 * Add html content to the form
	 *
	 * Use this method to insert html content into the form element.
	 * If this method is called multiple times the new content will be appended to the previous content
	 *
	 * @param	Form_Input_List		$input_list		An input list to be added to the form's content
	 * @return	void
	 */
	public function add_input_list_to_form_content( $input_list ) {
		ob_start();
			$input_list->render();
		$content = ob_get_clean();
		$this->form_content .= $content;
	} // function

	/**
	 * Add html content to the form
	 *
	 * Use this method to insert html content into the form element.
	 * If this method is called multiple times the new content will be appended to the previous content
	 *
	 * @param	string	$form_content	The html content to be added to the form
	 * @return	void
	 */
	public function add_form_content( $form_content ) {
		$this->form_content .= $form_content;
	} // function

	/** A private constructor forces users of this class to use one of the factory methods */
	private function __construct() { }

	/**
	 * Factory method to create an instance of this class
	 * @param	string	$ajax_action	The action string that will be passed to the server to indicate how to handle the ajax request
	 * @param	string	$form_classes	(optional) The html content to be added to the form
	 * @param	string	$form_content	(optional) The html content to be inserted inside the form
	 * @return	Ajax_Form	An instance of this class
	 */
	public static function create( $ajax_action, $form_method = 'POST', $form_classes = '', $form_content = '', $is_file_upload = FALSE ) {
		$result = new self();
		$result->ajax_action = $ajax_action;
		$result->form_method = $form_method;
		$result->form_classes = $form_classes;
		$result->form_content = $form_content;
		$result->is_file_upload = boolval( $is_file_upload );
		return $result;
	} // function

	/**
	 * Render the form in place.
	 * This generates a placeholder div and the form element is created on the client side but the end result is
	 *  a form element inserted immediately into the current context.
	 *
	 * @return	void
	 */
	public function render() {
		$this->render_form_initializer( $is_render_in_footer = FALSE );
	} // function

	/**
	 * Render the form in the footer rather than at the current location.
	 * This can be used when the form, if rendered in place, would be inside another form.
	 * For example, in the admin interface when creating a metabox that includes an ajax form.
	 * Events triggered by the form will be forwarded back to the original context where render_in_footer() is called.
	 * So a parent element will receive events like submit-start, submit-end etc.
	 *
	 * This works by inserting a placeholder div at the current context and rendering the form inside it.
	 * On the client side, the form will be created and the form contents moved to it.
	 * Finally, the new form element will be appended to the body.
	 *
	 * @return	void
	 */
	public function render_in_footer() {
		$this->render_form_initializer( $is_render_in_footer = TRUE );
	} // function


	/**
	 * Render the form container.
	 * To facilitate rendering the form in the footer, we will always render the form contents inside a div
	 * and then generate the actual form element on the client side and put it in the right place, footer or in place.
	 *
	 * @return	void
	 */
	private function render_form_initializer( $is_render_in_footer ) {
		$form_action = esc_url( admin_url( 'admin-ajax.php' ) );
		$form_method = $this->get_form_method();
		$form_id = $this->get_form_id();
		$ajax_action = $this->get_ajax_action();
		$replace_html = $this->get_is_replace_content_with_response_html() ? 'TRUE' : 'FALSE';
		$is_footer_data = $is_render_in_footer ? 'TRUE' : 'FALSE';
		$encType = $this->get_is_file_upload() ? 'encType="multipart/form-data"' : '';
		$form_content = $this->get_form_content();

		$classes = array();
		$classes[] = 'reg-man-rc-ajax-form-initializer';
		$classes[] = $this->get_form_classes();
		$classes = implode( ' ', $classes );
		$classes = "class=\"$classes\"";

		$data = array();
		$data[] = "data-form-action=\"$form_action\"";
		$data[] = "data-form-method=\"$form_method\"";
		$data[] = "data-form-id=\"$form_id\"";
		$data[] = "data-ajax-action=\"$ajax_action\"";
		$data[] = "data-auto-replace-html=\"$replace_html\"";
		$data[] = "data-is-render-in-footer=\"$is_footer_data\"";
		$data[] = "data-enc-type=\"$encType\"";
		$data = implode( ' ', $data );

//		echo "<form action=\"$form_action\" method=\"$form_method\" $encType class=\"reg-man-rc-ajax-form $classes\" $data>";
		echo "<div $classes $data>";
			if ( $this->get_include_nonce_fields() ) {
				wp_nonce_field( $ajax_action );
			} // endif
			echo $form_content;
		echo '</div>';
//		echo '</form>';
	} // function

	/**
	 * Get the form contents as a string
	 *
	 * @return	string	The ajax form element as a string
	 */
	public function get_form_as_string() {
		ob_start();
			$this->render();
		$result = ob_get_clean();
		return $result;
	} // function
} // class