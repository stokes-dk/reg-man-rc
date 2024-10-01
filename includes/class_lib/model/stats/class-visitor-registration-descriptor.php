<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An implementor of this interface represents a visitor who registered one or more items for an event.
 * This plugin provides an implementation for visitor regstrations created with this plugin (the Visitor_Registration class)
 * and an implementation for visitors defined externally (External_Visitor_Registration class).
 *
 * @since	v0.1.0
 *
 */
interface Visitor_Registration_Descriptor {

	/**
	 * Get the key for the event that the visitor attended.
	 * @return	string|NULL		The key for the event
	 * @since	v0.1.0
	 */
	public function get_event_key_string();

	/**
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_display_name();
	
	/**
	 * Get the visitor's name as a string.
	 * To protect the visitor's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name();

	/**
	 * Get the visitor's public name.
	 * To protect the visitor's privacy this name is the one shown in public and should be something like
	 * the visitor's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name();

	/**
	 * Get a boolean indicating whether this is the first event the visitor has attended.
	 * @return	boolean|NULL	TRUE if it's the visitor's first event, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_first_event();

	/**
	 * Get the visitor's email, if supplied.
	 * To protect the visitor's privacy their email is never shown in public.
	 * The email is used only to identify returning visitors and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email();

	/**
	 * Get a boolean indicating whether the visitor has asked to join the mailing list.
	 * @return	boolean|NULL	TRUE if it's the visitor wants to join the mailing list, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_join_mail_list();

	/**
	 * Get a count of the number of items registered by this visitor, if known.
	 * @return	int|NULL	A count of the number of items registered by this visitor or NULL if we don't know.
	 * @since	v0.1.0
	 */
	public function get_item_count();
	
	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_visitor_registration_descriptor_source();

} // interface