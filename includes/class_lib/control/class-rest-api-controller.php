<?php
namespace Reg_Man_RC\Control;

/**
 * The REST API controller
 *
 * This class provides the controller function for the plugin's REST API
 *
 * @since v0.5.0
 *
 */
class REST_API_Controller {
	
	/**
	 * The version for the REST API for this plugin
	 * @var string
	 */
	const REST_API_VERSION		= '1.0';
	
	/**
	 * Register this controller
	 * 
	 * @since 0.5.0
	 */
	public static function register() {

		// register the rest api endpoint
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_endpoints' ) );

	} // function

	/**
	 * Get the namespace for the REST API
	 * @return string
	 */
	public static function get_namespace() {
		return 'reg-man-rc/' . self::REST_API_VERSION;
	} // function

	
	/**
	 * Register the REST API endpoints for the plugin
	 */
	public static function register_rest_endpoints() {

		Calendar_Controller::register_rest_endpoints();
		
	} // function

	
} // class