<?php
namespace Reg_Man_RC\Control\Plugin_Update;


use Reg_Man_RC\Model\Error_Log;
use const Reg_Man_RC\PLUGIN_VERSION;
use const Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME;

class Plugin_Update_Controller {
	
	/**
	 * Register this controller
	 * 
	 * @since 0.9.0
	 */
	
	const PLUGIN_SLUG				= 'reg-man-rc';
	const UPDATE_INFO_TRANSIENT_KEY	= self::PLUGIN_SLUG . '_plugin_update_info';
	const UPDATE_INFO_URL			= 'https://plugins.repaircafetoronto.ca/' . self::PLUGIN_SLUG . '/plugin_info.php';

	/**
	 * Register this controller
	 */
	public static function register() {

		// Filter the response for the current Plugin Installation API request
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api_request' ), 20, 3 );
		
		// Filter the value of the update_plugins site transient to add our plugin info
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'filter_update_plugins_transient' ) );
		
		// Handle the action when the upgrader process is complete
		add_action( 'upgrader_process_complete', array( __CLASS__, 'handle_upgrader_process_complete' ), 10, 2 );
		
	} // function

	/**
	 * Filter the response for the current Installation API request.
	 * @param boolean|object|array	$result	The result object or array. Default false.
	 * @param string				$action	The type of information being requested from the Plugin Installation API.
	 * @param object				$args	Plugin API arguments.
	 */
	public static function filter_plugins_api_request( $result, $action, $args ) {
		
//		Error_Log::var_dump( $result, $action, $args );

		// Make sure we're getting plugin information for our plugin
		if ( ( 'plugin_information' == $action ) && ( self::PLUGIN_SLUG === $args->slug ) ) {

			$update_info_obj = self::get_plugin_update_info();
			
			$update_info_obj->sections = ( array ) $update_info_obj->sections; // This must be an array
			
//			Error_Log::var_dump( $update_info_obj );
			
			$result = $update_info_obj;
			
		} // endif

		return $result;
		
	} // function
	
	/**
	 * Filter the value of the update_plugins site transient to add our plugin info
	 * @param	mixed $value	The transient value
	 * @return	mixed
	 */
	public static function filter_update_plugins_transient( $value ) {

//		Error_Log::var_dump( $value );

		if ( empty( $value->checked ) ) {
			return $value;
		} // endif

		$update_info = self::get_plugin_update_info();
//		Error_Log::var_dump( $update_info );

		$slug = 'reg-man-rc';
		$id = $slug . '/' . basename( PLUGIN_BOOTSTRAP_FILENAME );
//		Error_Log::var_dump( $id );

		if ( $update_info
			&& version_compare( PLUGIN_VERSION, $update_info->new_version, '<' )
			&& version_compare( $update_info->requires, get_bloginfo( 'version' ), '<=' )
			&& version_compare( $update_info->requires_php, PHP_VERSION, '<' )
		) {
			
			$value->response[ $id ] = $update_info;
			
		} else {

			// No update is available for auto-update
			$item = (object) array(
				'id'            => $id,
				'slug'          => $slug,
				'plugin'        => $id,
				'new_version'   => PLUGIN_VERSION,
				'url'           => '',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new \stdClass(),
			);
			// Adding the "mock" item to the `no_update` property is required
			// for the enable/disable auto-updates links to correctly appear in UI.
			$value->no_update[ $id ] = $item;
		
	    } // endif
		
		return $value;
		
	} // function

	/**
	 * Handle the action when the upgrader process is complete
	 * @param \WP_Upgrader 	$upgrader
	 * @param array			$hook_extra
	 */
	public static function handle_upgrader_process_complete( $upgrader, $hook_extra ) {
		
//		Error_Log::var_dump( $upgrader, $hook_extra );
		
		if ( ( 'update' === $hook_extra[ 'action' ] ) && ( 'plugin' === $hook_extra[ 'type' ] ) ) {

			// Remove my transient when the update is complete
			delete_transient( self::UPDATE_INFO_TRANSIENT_KEY );
			
		} // endif
		
	} // function
	
	/**
	 * Get the plugin update info
	 * @return boolean|mixed
	 */
	public static function get_plugin_update_info() {
		
		$result = FALSE; // assume we can't get the info
		
		// Get the cached copy if available
		$json = get_transient( self::UPDATE_INFO_TRANSIENT_KEY );
//		Error_Log::var_dump( $json );

		// If we don't have the json already stored in the transient then try to get it
		if ( $json === FALSE ) {

//			Error_Log::log_msg( 'Retrieving plugin update info remotely' );
			
			$json = wp_remote_get(
				self::UPDATE_INFO_URL,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			// Make sure the remote get was successful
			if ( ! is_wp_error( $json ) && ( wp_remote_retrieve_response_code( $json ) === 200 ) ) {
				
				// Retieve the body of the response and make sure it's not empty
				$body = wp_remote_retrieve_body( $json );
				
				if ( ! empty( $body ) ) {

					// Save the transient for re-use later
					set_transient( self::UPDATE_INFO_TRANSIENT_KEY, $json, DAY_IN_SECONDS );
					
				} // endif
	
			} // endif

		} // endif

		// If we successfully retrieved the json then decode and return it
		if ( $json !== FALSE ) {
			
			// FALSE in the second argument causes the result to be returned as an object
			$result = json_decode( wp_remote_retrieve_body( $json ), FALSE );
				
		} // endif
		
		return $result;

	} // function
		
} // class