<?php
namespace Reg_Man_RC\Model;

/**
 * This class provides factory methods for instances of Event_Descriptor
 *
 * @since v0.1.0
 *
 */
class Event_Descriptor_Factory {
	/**
	 * Get all event descriptors known to the system including those defined by both internal and external event providers.
	 * @return	Event_Descriptor[]	An array of Event_Descriptor objects describing all events known to the system.
	 */
	public static function get_all_event_descriptors() {
		$result = array();
		$providers_array = Event_Provider_Factory::get_all_event_providers();
		foreach( $providers_array as $provider ) {
			$desc_array = $provider->get_all_event_descriptors();
			$result = array_merge( $result, $desc_array );
		} // endfor
		return $result;
	} // function

	/**
	 * Get an event descriptor based on its descriptor and provider IDs
	 * @param	string	$descriptor_id
	 * @param	string	$provider_id
	 * @return	Event_Descriptor|NULL	The requested Event_Descriptor or NULL if it is not found
	 */
	public static function get_event_descriptor_by_id( $descriptor_id, $provider_id ) {
		$provider = Event_Provider_Factory::get_event_provider_by_id( $provider_id );
		$result = isset( $provider ) ? $provider->get_event_descriptor_by_id( $descriptor_id ) : NULL; 
		return $result;
	} // function

} // class