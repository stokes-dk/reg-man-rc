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
		// Get all the internal and external event descriptors
		$internal_descriptor_array = Internal_Event_Descriptor::get_all_internal_event_descriptors();
		$external_descriptor_array = External_Event_Descriptor::get_all_external_event_descriptors();
		$result = array_merge( $internal_descriptor_array, $external_descriptor_array );
		return $result;
	} // function

	/**
	 * Get event descriptors in a given event category known to the system
	 *  including those defined by both internal and external event providers.
	 * @param	int	$event_category_id	The ID for a category of events whose event descriptors are to be returned.
	 * @return	Event_Descriptor[]	An array of Event_Descriptor objects describing all events which have the specified
	 *  event category listed among their categories.
	 */
	public static function get_event_descriptors_in_category( $event_category_id ) {
		$internal_descriptor_array = Internal_Event_Descriptor::get_internal_event_descriptors_for_event_category( $event_category_id );
		// We can't ask external event providers to get events by category, so we'll get all and then look through them
		// TODO: Add a filter to get events for a given array of event category names
		$external_descriptor_array = External_Event_Descriptor::get_all_external_event_descriptors();

		// Start with an empty array (for external event descriptors) and add those whose category matches
		$trimmed_external_descriptor_array = array();
		foreach ( $external_descriptor_array as $ext_desc ) {
			$ext_cat_names = $ext_desc->get_event_categories();
			if ( is_array( $ext_cat_names ) ) {
				foreach ( $ext_cat_names as $ext_name ) {
					$int_cat = Event_Category::get_event_category_by_name( $ext_name );
					if ( isset( $int_cat ) && ( $int_cat->get_id() == $event_category_id ) ) {
						$trimmed_external_descriptor_array[] = $ext_desc;
						break;
					} // endif
				} // endfor
			} // endif
		} // endfor

		$result = array_merge( $internal_descriptor_array, $trimmed_external_descriptor_array );

		return $result;
	} // function


	/**
	 * Get event descriptors whose venue is the one specified including those defined by both internal and external event providers.
	 *
	 * Note that when checking external events we will compare the event's location and geo (geographical location)
	 *  with those of the specified venue.
	 * If either location or geo matches then we will return it is an event at the specified venue.
	 *
	 * @param	Venue	$venue	The Venue object whose events are to be returned
	 * @return	Event_Descriptor[]	An array of Event_Descriptor objects describing all events at the specified venue.
	 */
	public static function get_event_descriptors_for_venue( $venue ) {
		if ( ! isset( $venue ) ) {
			$result = array();
		} else {

			$venue_id = $venue->get_id();
			$internal_descriptor_array = Internal_Event_Descriptor::get_internal_event_descriptors_for_venue( $venue_id );

			// We can't ask external event providers to get events by venue, so we'll get all and then look through them
			// TODO: Add a filter to get events for a given location
			$external_descriptor_array = External_Event_Descriptor::get_all_external_event_descriptors();

			// Start with an empty array (for external event descriptors) and add those whose category matches
			$trimmed_external_descriptor_array = array();
			$venue_location = strtolower( strval( $venue->get_location() ) ); // Make sure we have a lower case string to compare
			$venue_geo = $venue->get_geo();
			foreach ( $external_descriptor_array as $ext_desc ) {
				$event_location = strtolower( strval( $ext_desc->get_event_location() ) );
				$event_geo = $ext_desc->get_event_geo();
				if ( ( ( $venue_location !== '' ) && ( $venue_location == $event_location ) ) ||
					   ( isset( $venue_geo ) && ( $venue_geo->get_is_equal_to( $event_geo ) ) ) ) {
					$trimmed_external_descriptor_array[] = $ext_desc;
				} // endif
			} // endfor

			$result = array_merge( $internal_descriptor_array, $trimmed_external_descriptor_array );
		} // endif
		return $result;
	} // function


} // class