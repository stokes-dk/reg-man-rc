<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class implements an event provider for this plugin.
 *
 * @since v0.6.0
 *
 */
class Internal_Event_Provider implements Event_Provider {
	
	const ID = 'rmrc';
	
	private static $SINGLETON; // There is always exactly one instance of this clas
	
	private $id;
	private $name;
//	private $type;
	
	private function __construct() {
	} // function
	
	public static function get_internal_event_provider() {
		$result = new self();
		$result->id = self::ID;
		$result->name = __( 'Registration Manager for Repair Café', 'reg-man-rc' );
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
		return Internal_Event_Descriptor::get_all_internal_event_descriptors();
	} // function
	
	/**
	 * Get a single event descriptor from this provider using its event descriptor ID
	 *
	 * @param	string	$event_descriptor_id	The ID for the event descriptor
	 * @return	Event_Descriptor
	 */
	public function get_event_descriptor_by_id( $event_descriptor_id ) {
		return Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $event_descriptor_id );
	} // function
	
} // class