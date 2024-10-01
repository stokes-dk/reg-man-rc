<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item_Status;

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
	 * Get the ID of this item
	 * @return	string	The ID of this item
	 * @since	v0.9.5
	 */
	public function get_item_ID();

	/**
	 * Get the description of this item
	 * @return	string	The description of this item
	 * @since	v0.1.0
	 */
	public function get_item_description();

	/**
	 * Get the key for the event for which this item was registered
	 * @return	string	The event key for the event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event_key_string();

	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event();

	/**
	 * Get the most descriptive name available to the current user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_display_name();
	
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
	 * Get the email address of the visitor who registered the item.
	 * This may be NULL or an empty string if the email address is not known or not visible to the current user.
	 * @return	string|NULL		The visitor's email address.
	 * @since	v0.5.0
	 */
	public function get_visitor_email();

	/**
	 * Get a boolean indicating whether this event is the visitor's first.
	 * This may be NULL if it is not known.
	 * @return	boolean|NULL	TRUE if this is the visitor's first event, FALSE if it is not, NULL if not known.
	 * @since	v0.5.0
	 */
	public function get_visitor_is_first_time();

	/**
	 * Get a boolean indicating whether this visitor has requested to join the mailing list.
	 * This may be NULL if it is not known.
	 * @return	boolean|NULL	TRUE if the visitor has asked to join the mailing list, FALSE if not, NULL if not known.
	 * @since	v0.5.0
	 */
	public function get_visitor_is_join_mail_list();

	/**
	 * Get the name of the item type for this item as a string, e.g. 'Electrical / Electronic'
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
	 * Get the status for this item
	 *
	 * @return	Item_Status|NULL	The status for this item or NULL if not known
	 *
	 * @since v0.5.0
	 */
	public function get_item_status();

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_item_descriptor_source();

} // interface