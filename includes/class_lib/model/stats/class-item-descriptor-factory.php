<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Events_Collection;

/**
 * This class provides factory methods for instances of Item_Descriptor
 *
 * @since v0.1.0
 *
 */
class Item_Descriptor_Factory {

	/**
	 * Get the item descriptors for items registered to the set of events specified by the events collection.
	 * @param	Events_Collection	$events_collection		A collection of events whose registered items are to be returned
	 * @return	Item_Descriptor[]	An array of descriptors for the items registered to the specified set of events
	 */
	public static function get_item_descriptors_for_events_collection( $events_collection ) {
		if ( $events_collection->get_is_empty() ) {
			$result = array();
		} else {
			$event_keys_array = $events_collection->get_is_all_events() ? NULL : $events_collection->get_event_keys_array();
			$internal = Item::get_items_registered_for_event_keys_array( $event_keys_array );
			$external = External_Item::get_external_items( $event_keys_array );
			$supplemental = Supplemental_Item::get_all_supplemental_item_descriptors( $event_keys_array );
			$result = array_merge( $internal, $external, $supplemental );
		} // endif
		return $result;
	} // function
	
	/**
	 * Get an array of event key strings for items registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_key_strings_for_items_in_date_range( $min_key_date_string, $max_key_date_string ) {
		$internal = Item::get_event_key_strings_for_items_in_date_range( $min_key_date_string, $max_key_date_string );
		$external = External_Item::get_event_keys_for_items_in_date_range( $min_key_date_string, $max_key_date_string );
		$supplemental = Supplemental_Item::get_event_keys_for_items_in_date_range( $min_key_date_string, $max_key_date_string );
		$result = array_merge( $internal, $external, $supplemental );
		return $result;
	} // function

} // class