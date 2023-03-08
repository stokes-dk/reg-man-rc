<?php
namespace Reg_Man_RC\Model;

/**
 * An AJAX form response is created by control logic to contain a response to a form post
 * It may contain one or more of the following:
 *	- a collection of error messages associated with form fields (e.g. wrong password)
 *	- html (e.g. a wizard page does an ajax post, render the next page and send it back in the response)
 *	- a redirect url (redirect to this page, usually on success, e.g. go to user's home page after successful login)
 *	- a result dataset (this could contain a list of new items for the user)
 * The object can be used in the following ways:
 *	- passed directly in a method call to a view object for processing in php
 *	- json encoded and used to respond to an ajax post then processed with javascript
 */
class Ajax_Form_Response implements \JsonSerializable {

	private $error_array = array();
	private $html_data; // can be used in an ajax post to reply with html to replace an element on the page
	private $redirect_url; // to do http redirect in javascript use: window.location.replace(...)
	private $result_data; // currently used to return new datatable contents from ajax form submit like add new row

	private function __construct() { }

	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Add an error to the form response
	 * @param	string	$field_name		Specifies the name of the field with the error, e.g. 'user_name'
	 * @param	string	$field_val		Specifies the field value that is in error, e.g. 'fake@gmail.com'
	 * @param	string	$error_msg		Specifies the error message shown to the user, e.g. 'The user name is not known'
	 */
	public function add_error( $field_name, $field_val, $error_msg ) {
		$error_desc = array(
				'field_val'		=> $field_val,
				'error_msg'		=> $error_msg
		);
		$this->error_array[ $field_name ] = $error_desc;
	} // function

	/**
	 * Get the array of errors
	 * @return	string[][]	An associative array of errors keyed by field name.
	 * E.g. array( 'user_name' => array( 'field_val' => 'fake@gmail.com', 'error_msg' => 'The user name is not known' ) );
	 */
	public function get_error_array() {
		return $this->error_array;
	} // function

	/**
	 * Returns a boolean indicating whether the form response contains error messages
	 * @return boolean	TRUE if the form response contains error messages, FALSE otherwise
	 */
	public function get_has_errors() {
		return ! empty( $this->error_array );
	} // function

	/**
	 * Get the redirect URL assigned to this form response
	 * @return	string	The url the client should redirect the browser to when this form response is received
	 */
	public function get_redirect_url() {
		return $this->redirect_url;
	} // function

	/**
	 * Assign the redirect URL for this form response
	 * @param	string	$url	The url the client should redirect the browser to when this form response is received
	 * @return	void
	 */
	public function set_redirect_url( $url ) {
		$this->redirect_url = $url;
	} // function

	/**
	 * Get the html data assigned to this form response
	 * @return	string	The html the client should display in the the browser when this form response is received
	 */
	public function get_html_data() {
		return $this->html_data;
	} // function

	/**
	 * Assign the html data for this form response
	 * @param	string	$html_data	The html the client should display in the the browser when this form response is received
	 * @return	void
	 */
	public function set_html_data( $html_data ) {
		$this->html_data = $html_data;
	} // function

	/**
	 * Assign the result data assigned to this form response
	 * @param	string	$result_data	The data the client should display in the browser when this form response is received
	 * @return	void
	 */
	public function set_result_data( $result_data ) {
		$this->result_data = $result_data;
	} // function

	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string[][]	An associative array describing this object
	 * @since v0.1.0
	 */
	public function jsonSerialize() {
		$result = array();

		if ( isset( $this->error_array ) ) {
			$result[ 'error_array' ]	= $this->error_array;
		} // endif

		if ( isset( $this->html_data ) ) {
			$result[ 'html_data' ]		= $this->html_data;
		} // endif

		if ( isset( $this->redirect_url ) ) {
			$result[ 'redirect_url' ]	= $this->redirect_url;
		} // endif

		if ( isset( $this->result_data ) ) {
			$result[ 'result_data' ]	= $this->result_data;
		} // endif

		return $result;

	} // function

} // class
?>