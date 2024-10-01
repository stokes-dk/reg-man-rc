<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Events_Collection;

/**
 * This class provides factory methods for instances of Visitor_Registration_Descriptor
 *
 * @since v0.1.0
 *
 */
class Visitor_Registration_Descriptor_Factory {

	/**
	 * Get the all volunteer registration descriptors for the set of events specified by the events collection.
	 * Note that this includes fixer and non-fixer volunteer registrations.
	 * @param	Events_Collection	$events_collection	A collection of events whose visitors are to be returned
	 * @return	Visitor_Registration_Descriptor[]
	 */
	public static function get_visitor_registration_descriptors_for_events_collection( $events_collection ) {
		if ( $events_collection->get_is_empty() ) {
			$result = array();
		} else {
			$event_keys_array = $events_collection->get_is_all_events() ? NULL : $events_collection->get_event_keys_array();
			$internal = Visitor_Registration::get_visitor_registrations( $event_keys_array );
			$external = External_Visitor_Registration::get_external_visitor_registrations( $event_keys_array );
			$supplemental = Supplemental_Visitor_Registration::get_all_supplemental_visitor_registrations( $event_keys_array );
			$result = array_merge( $internal, $external, $supplemental );
		} // endif
		return $result;
	} // function

	/**
	 * Get the visitor registration descriptors for the specified visitor.
	 *
	 * @param	Visitor[]|NULL	$visitor		An Event_Filter instance which limits the set of events whose
	 *  registered items are to be returned, or NULL if all registered items are to be returned.
	 * @return	Visitor_Registration_Descriptor[]	An array of registration descriptors for the visitors who attended the specified set of events
	 */
	public static function get_visitor_registrations_for_visitor( $visitor ) {
		// Passing an event key array of NULL signifies that we want everything
		$event_keys_array = NULL; // get registrations for all events
		$internal = Visitor_Registration::get_visitor_registrations( $event_keys_array, $visitor );
		$email = $visitor->get_email();
		$full_name = $visitor->get_full_name();
		if ( empty( $email ) && empty( $full_name ) ) {
			// If this visitor has no email or full name then we want to avoid getting registrations for everyone!
			$external = array();
		} else {
			$external = External_Visitor_Registration::get_external_visitor_registrations( $event_keys_array, $email, $full_name );
		} // endif
		// Note that supplemental visitor registrations do not specify any visitor so these are not needed
		$result = array_merge( $internal, $external );
		return $result;
	} // function
	
	
} // class