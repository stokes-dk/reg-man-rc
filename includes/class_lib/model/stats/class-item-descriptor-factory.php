<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;

/**
 * This class provides factory methods for instances of Item_Descriptor
 *
 * @since v0.1.0
 *
 */
class Item_Descriptor_Factory {

	/**
	 * Get the item descriptors for items registered to the set of events specified by the event keys.
	 * If the argument is NULL then all item descriptors will be returned.
	 * @param	string[]|NULL	$filter		An array of event keys whose
	 *  registered items are to be returned, or NULL if all registered items are to be returned.
	 * @return	Item_Descriptor[]	An array of descriptors for the items registered to the specified set of events
	 */
	public static function get_item_descriptors_for_event_keys_array( $event_keys_array ) {
		$internal = Item::get_items_registered_for_event_keys_array( $event_keys_array );
		$external = External_Item::get_external_items( $event_keys_array );
		$supplemental = Supplemental_Item::get_all_supplemental_item_descriptors( $event_keys_array );
		$result = array_merge( $internal, $external, $supplemental );
		return $result;
	} // function

	/**
	 * Get the item descriptors for items registered to the set of events derived from the specified filter.
	 * If the event filter is NULL then all item descriptors will be returned.
	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  registered items are to be returned, or NULL if all registered items are to be returned.
	 * @return	Item_Descriptor[]	An array of descriptors for the items registered to the specified set of events
	 */
	public static function get_item_descriptors_for_filter( $filter ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$result = self::get_item_descriptors_for_event_keys_array( $keys_array );
		return $result;
	} // function

} // class