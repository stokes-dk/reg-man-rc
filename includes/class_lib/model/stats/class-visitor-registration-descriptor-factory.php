<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Visitor;

/**
 * This class provides factory methods for instances of Visitor_Registration_Descriptor
 *
 * @since v0.1.0
 *
 */
class Visitor_Registration_Descriptor_Factory {

	/**
	 * Get the visitor registration descriptors for visitors who attended the set of events derived from the specified filter.
	 *
	 * If the event filter is NULL then all visitor descriptors will be returned.
	 *
	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  registered items are to be returned, or NULL if all registered items are to be returned.
	 * @return	Visitor_Registration_Descriptor[]	An array of registration descriptors for the visitors who attended the specified set of events
	 */
	public static function get_visitor_registrations_for_filter( $filter ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$event_key_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$result = self::get_visitor_registrations_for_events( $event_key_array );
		return $result;
	} // function

	/**
	 * Get the visitor registration descriptors for visitors who attended the set of events specified by the event key array.
	 *
	 * If the event key array is NULL then all visitor descriptors will be returned.
	 *
	 * @param	Event_Key[]|NULL	$event_key_array	An array of event keys specifying the set of events whose
	 *  registered items are to be returned, or NULL if all registered items are to be returned.
	 * @return	Visitor_Registration_Descriptor[]	An array of registration descriptors for the visitors who attended the specified set of events
	 */
	public static function get_visitor_registrations_for_events( $event_key_array ) {
		// Passing an event key array of NULL signifies that we want everything
		$internal = Visitor_Registration::get_visitor_registrations( $event_key_array );
		$external = External_Visitor_Registration::get_external_visitor_registrations( $event_key_array );
		$supplemental = Supplemental_Visitor_Registration::get_all_supplemental_visitor_registrations( $event_key_array );
		$result = array_merge( $internal, $external, $supplemental );
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
		$event_key_array = NULL; // get registrations for all events
		$internal = Visitor_Registration::get_visitor_registrations( $event_key_array, $visitor );
		$email = $visitor->get_email();
		$full_name = $visitor->get_full_name();
		if ( empty( $email ) && empty( $full_name ) ) {
			// If this visitor has no email or full name then we want to avoid getting registrations for everyone!
			$external = array();
		} else {
			$external = External_Visitor_Registration::get_external_visitor_registrations( $event_key_array, $email, $full_name );
		} // endif
		// Note that supplemental visitor registrations do not specify any visitor so these are not needed
		$result = array_merge( $internal, $external );
		return $result;
	} // function
	
	
} // class