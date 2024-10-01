<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Events_Collection;

/**
 * This class provides factory methods for instances of Volunteer_R
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_Descriptor_Factory {

	/**
	 * Get the all volunteer registration descriptors for the set of events specified by the events collection.
	 * Note that this includes fixer and non-fixer volunteer registrations.
	 * @param	Events_Collection	$events_collection	A collection of events whose registered volunteers are to be returned
	 * @return	Volunteer_Registration_Descriptor[]
	 */
	public static function get_all_volunteer_registration_descriptors_for_events_collection( $events_collection ) {
		if ( $events_collection->get_is_empty() ) {
			$result = array();
		} else {
			$event_keys_array = $events_collection->get_is_all_events() ? NULL : $events_collection->get_event_keys_array();
			$internal = Volunteer_Registration::get_all_registrations_for_event_keys( $event_keys_array );
			$external = External_Volunteer_Registration::get_all_external_volunteer_registrations( $event_keys_array );
			$supplemental = Supplemental_Volunteer_Registration::get_all_supplemental_volunteer_registrations( $event_keys_array );
			$result = array_merge( $internal, $external, $supplemental );
		} // endif
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
	 * Get an array of event key strings for volunteers registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string ) {
		$internal = Volunteer_Registration::get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string );
		$external = External_Volunteer_Registration::get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string );
		$supplemental = Supplemental_Volunteer_Registration::get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string );
		$result = array_merge( $internal, $external, $supplemental );
		return $result;
	} // function

} // class