<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class implements an event provider for an external plugin ( normally an add-on for this plugin).
 * External plugin event providers interact with this plugin via hooks.
 *
 * @since v0.6.0
 *
 */
class External_Plugin_Event_Provider implements Event_Provider {
	
	private $id;
	private $name;
//	private $type;
	
	private function __construct() {
	} // function
	
	public static function create( $id, $name ) {
		$result = new self();
		$result->id = $id;
		$result->name = $name;
		return $result;
	} // function
	
	/**
	 * Get the unique ID for this event provider
	 * @return string
	 */
	public function get_event_provider_id() {
		return $this->id;
	} // function
	
	public function get_event_provider_name() {
		return $this->name;
	} // function
	
	/**
	 * Get all event descriptors from this provider
	 * @return Event_Descriptor[]
	 */
	public function get_all_event_descriptors() {
		/**
		 * Get all events defined under this event provider
		 *
		 * @since v0.6.0
		 *
		 * @api
		 *
		 * @param	array	$desc_data_arrays	An array of string arrays where each string array provides the details of one external event.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of Event_Descriptor.
		 */
		
		$provider_id = $this->get_event_provider_id();

		$desc_data_arrays = apply_filters( 'reg_man_rc_get_all_events-' . $provider_id, array() );
		
		$result = array();
		foreach ( $desc_data_arrays as $data_array ) {
			$event = External_Event_Descriptor::instantiate_from_data_array( $data_array );
			if ( $event !== NULL ) {
				$result[] = $event;
			} // endif
		} // endfor
		return $result;
		
	} // function
	
	/**
	 * Get a single event descriptor from this provider using its event descriptor ID
	 *
	 * @param	string	$event_descriptor_id	The ID for the event descriptor
	 * @return	Event_Descriptor
	 */
	public function get_event_descriptor_by_id( $event_descriptor_id ) {

		$provider_id = $this->get_event_provider_id();

		$descriptor = apply_filters( 'reg_man_rc_get_event-' . $provider_id, NULL, $event_descriptor_id );
		$result = ! empty( $descriptor ) ? External_Event_Descriptor::instantiate_from_data_array( $descriptor ) : NULL;

		return $result;
		
	} // function

	
} // class