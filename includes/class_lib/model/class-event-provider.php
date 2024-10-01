<?php
namespace Reg_Man_RC\Model;

/**
 * An event provider implements a mechanism for access event descriptors from some source.
 *
 * @since v0.6.0
 *
 */
interface Event_Provider {
	
	/**
	 * Get the unique ID for this event provider
	 * @return string
	 */
	public function get_event_provider_id();
	
	/**
	 * Get the name of this event provider
	 * @return string
	 */
	public function get_event_provider_name();
	
	/**
	 * Get all event descriptors from this provider
	 * @return Event_Descriptor[]
	 */
	public function get_all_event_descriptors();
	
	/**
	 * Get a single event descriptor from this provider using its event descriptor ID
	 *
	 * @param	string	$event_descriptor_id	The ID for the event descriptor
	 * @return	Event_Descriptor
	 */
	public function get_event_descriptor_by_id( $event_descriptor_id );
	
} // interface