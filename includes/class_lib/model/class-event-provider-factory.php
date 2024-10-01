<?php
namespace Reg_Man_RC\Model;

/**
 * This class is a factory for Event_Provider instances.
 *
 * @since v0.6.0
 *
 */
class Event_Provider_Factory {
	
	private static $PROVIDERS_ARRAY;
	private static $EXTERNAL_PROVIDERS_ARRAY; // All external providers
	private static $PROVIDER_NAMES_ARRAY;
	
	/**
	 * Get an array of all event providers
	 * @return	Event_Provider[]	An array of event providers indexed by their ID.
	 */
	public static function get_all_event_providers() {
		if ( ! isset( self::$PROVIDERS_ARRAY ) ) {

			// Get the internal provider
			self::$PROVIDERS_ARRAY = array( 
					Internal_Event_Provider::ID	=>	Internal_Event_Provider::get_internal_event_provider()
			);

			// Get all external plugin providers
			$ext_plugin_providers_array = self::get_external_plugin_event_providers();
			
			// Merge
			self::$PROVIDERS_ARRAY = array_merge( self::$PROVIDERS_ARRAY, $ext_plugin_providers_array );

		} // endif
		return self::$PROVIDERS_ARRAY;
	} // function
	
	/**
	 * Get the event provider with the specified ID
	 * @param 	string	$provider_id
	 * @return	Event_Provider|NULL	The specified event provider or NULL if it is not found
	 */
	public static function get_event_provider_by_id( $provider_id ) {
		$providers_array = self::get_all_event_providers();
		$result = isset( $providers_array[ $provider_id ] ) ? $providers_array[ $provider_id ] : NULL;
		return $result;
	} // function
	
	/**
	 * Get an array of the external plugin event providers.
	 * External plugin event providers are accessed via hooks.
	 * @return	Event_Provider[]	An array of event providers indexed by their ID.
	 */
	public static function get_external_event_providers() {

		if ( ! isset( self::$EXTERNAL_PROVIDERS_ARRAY ) ) {
			
			// Get the external plugin providers
			$ext_plugin_providers = self::get_external_plugin_event_providers();
			
			// What others?
			
			
			// Merge everything together
			self::$EXTERNAL_PROVIDERS_ARRAY = array_merge( $ext_plugin_providers );
			
		} // endif
		
		return self::$EXTERNAL_PROVIDERS_ARRAY;
		
	} // function

	
	/**
	 * Get an array of the external plugin event providers.
	 * External plugin event providers are accessed via hooks.
	 * @return	Event_Provider[]	An array of event providers indexed by their ID.
	 */
	private static function get_external_plugin_event_providers() {

		/**
		 * Get all external plugin event provider IDs
		 *
		 * Each external plugin event provider will add its ID to the resulting array.
		 *   E.g. array( 'ecwd' => 'Event Calendar WD' );
		 *
		 * @since v0.5.0
		 *
		 * @api
		 *
		 * @param	array	$provider_ids_arrays	An array of string IDs of local event providers.
		 */
		$provider_ids_array = apply_filters( 'reg_man_rc_poll_event_providers', array() );
		
		$result = array();
		
		foreach( $provider_ids_array as $provider_id => $provider_name ) {
			
			$provider = External_Plugin_Event_Provider::create( $provider_id, $provider_name );
			$result[ $provider_id ] = $provider;
			
		} // endfor
			
		return $result;
		
	} // function

	/**
	 * Get a provider name based on its ID
	 * @param string $provider_id
	 * @return string	The name of the provider if the ID is recognized, otherwise the ID itself is returned
	 */
	public static function get_provider_name_by_id( $provider_id ) {
		$names_array = self::get_provider_names_array();
		$result = isset( $names_array[ $provider_id ] ) ? $names_array[ $provider_id ] : $provider_id;
		return $result;
	} // function

	/**
	 * Get the array of provider names keyed by ID
	 * @return string
	 */
	private static function get_provider_names_array() {
		if ( ! isset( self::$PROVIDER_NAMES_ARRAY ) ) {
			self::$PROVIDER_NAMES_ARRAY = array();
			$providers_array = self::get_all_event_providers();
			foreach( $providers_array as $provider ) {
				self::$PROVIDER_NAMES_ARRAY[ $provider->get_event_provider_id() ] = $provider->get_event_provider_name();
			} // endfor
		} // endif
		return self::$PROVIDER_NAMES_ARRAY;
	} // function
	
} // class