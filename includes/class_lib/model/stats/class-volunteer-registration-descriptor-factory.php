<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Volunteer_Registration;

/**
 * This class provides factory methods for instances of Volunteer_R
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_Descriptor_Factory {

	/**
	 * Get all volunteer registration descriptors known to the system including those defined by both internal and external providers.
	 *
	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  registered volunteers are to be returned, or NULL if all registered volunteers are to be returned.
	 * @return	Volunteer_Registration_Descriptor[]		An array of Volunteer_Registration_Descriptor objects describing
	 *  all volunteer registrations known to the system and limited to the events described by the filter.
	 */
	public static function get_all_volunteer_registration_descriptors_for_filter( $filter ) {
		// Get all the internal and external event descriptors
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$internal = Volunteer_Registration::get_all_registrations_for_event_keys( $keys_array );
		$external = External_Volunteer_Registration::get_all_external_volunteer_registrations( $keys_array );
		$supplemental = Supplemental_Volunteer_Registration::get_all_supplemental_volunteer_registrations( $keys_array );
		$result = array_merge( $internal, $external, $supplemental );
		return $result;
	} // function

	/**
	 * Get volunteer registration descriptors for the specified Volunteer
	 *
	 * @param	Volunteer	$volunteer		A Volunteer instance whose registrations are to be returned
	 * @return	Volunteer_Registration_Descriptor[]		An array of Volunteer_Registration_Descriptor objects describing
	 *  all volunteer registrations for the specified volunteer
	 */
	public static function get_volunteer_registration_descriptors_for_volunteer( $volunteer ) {
		// Get all the internal and external descriptors
		$internal = Volunteer_Registration::get_registrations_for_volunteer( $volunteer );
		$external = External_Volunteer_Registration::get_external_volunteer_registrations_for_email( $volunteer->get_email() );
		$result = array_merge( $internal, $external );
		return $result;
	} // function

	/**
	 * Get fixer volunteer registration descriptors including those defined internally
	 *  and by external providers like the legacy registration data add-on.
	 *
	 * This method returns volunteer registrations that have a fixer role.
	 *
	 * Note that some volunteers have a fixer role AND a non-fixer role like Setup & Cleanup.
	 * Those registrations are INCLUDED in the result of this method.
	 *
	 * Registrations with no fixer role are excluded.
	 *
	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  registered fixers are to be returned, or NULL if all registered fixers are to be returned.
	 * @return	Volunteer_Registration_Descriptor[]		An array of Volunteer_Registration_Descriptor objects describing
	 *  all fixer registrations known to the system and limited to the events described by the filter.
	 */
	public static function get_fixers_for_filter( $filter ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$internal = Volunteer_Registration::get_fixer_registrations_for_event_keys( $keys_array );
		$external = External_Volunteer_Registration::get_external_fixer_registrations( $keys_array );
		$result = array_merge( $internal, $external );
		return $result;
	} // function

	/**
	 * Get volunteer registration descriptors for volunteers who registered for a non-fixer role like "Setup & Cleanup"
	 *  including those defined internally and by external providers like the legacy registration data add-on.
	 *
	 * Note that some volunteers may have a fixer role AND a non-fixer role like "Setup & Cleanup".  Those registrations
	 *   are INCLUDED in the result of this method.
	 *
	 * Also note that some volunteers select no role at all intending to show up and perform a task assigned at the event.
	 * Those registrations are also INCLUDED in the result of this method.
	 *
	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  registered non-fixers are to be returned, or NULL if all registered non-fixers are to be returned.
	 * @return	Volunteer_Registration_Descriptor[]		An array of Volunteer_Registration_Descriptor objects describing
	 *  all non-fixer registrations known to the system and limited to the events described by the filter.
	 */
	public static function get_non_fixers_for_filter( $filter ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$internal = Volunteer_Registration::get_non_fixer_registrations_for_event_keys( $keys_array );
		$external = External_Volunteer_Registration::get_external_non_fixer_registrations( $keys_array );
		$result = array_merge( $internal, $external );
		return $result;
	} // function


} // class