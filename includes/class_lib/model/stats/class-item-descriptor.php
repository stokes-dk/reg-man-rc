<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An implementor of this interface represents an item registered by a visitor for an event.
 * This plugin provides an implementation for items created within it (the Item class) and an implementation
 * for items created externally (External_Item class).
 *
 * @since	v0.1.0
 *
 */
interface Item_Descriptor {

	/**
	 * Get the description of this item
	 * @return	string	The description of this item which may contain html formating
	 * @since	v0.1.0
	 */
	public function get_item_description();

	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event();

	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name();

	/**
	 * Get the public name of the visitor who registered the item.
	 * This is a name for the visitor that can be used in public like first name and last initial.
	 * @return	string		The visitor's public name.
	 * @since	v0.1.0
	 */
	public function get_visitor_public_name();

	/**
	 * Get the name of the item type for this item as a string, e.g. 'Appliance'
	 * @return	string	The name of the item's type
	 * @since	v0.1.0
	 */
	public function get_item_type_name();

	/**
	 * Get the name fixer station assigned to this item
	 *
	 * @return	string	The name of the fixer station assigned to this item or NULL if no fixer station is assigned
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_station_name();

	/**
	 * Get the status for this item, i.e. Fixed, Repairable etc.
	 *
	 * @return	string	The string representing the repair status for the item.
	 * Must be one of the constants defined in Item_Status: 'Fixed', 'Repairable', or 'End of life'
	 *
	 * @since v0.1.0
	 */
	public function get_status_name();

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_item_descriptor_source();

} // interface