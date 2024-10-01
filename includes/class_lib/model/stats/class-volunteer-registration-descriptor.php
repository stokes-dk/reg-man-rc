<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An implementor of this interface represents a volunteer or fixer (or both) registered for an event.
 * Note that one person may perform a volunteer role like "Setup and Cleanup" and also act as a fixer during the event.
 * This plugin provides an implementation for registrations created within it (the Volunteer_Registration class)
 * and an implementation for items created externally (External_Volunteer_Registration class).
 *
 * @since	v0.1.0
 */
interface Volunteer_Registration_Descriptor {

	/**
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_volunteer_display_name();
	
	/**
	 * Get the volunteer's public name.
	 * To protect the volunteer's privacy this name is the one shown in public and should be something like
	 * the volunteer's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_public_name();

	/**
	 * Get the volunteer's full name as a single string.
	 * To protect the volunteer's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_full_name();

	/**
	 * Get the volunteer's email, if supplied.
	 * To protect the volunteer's privacy their email is never shown in public.
	 * The email is used only to identify returning volunteers and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_volunteer_email();

	/**
	 * Get the key for the event that the volunteer is registered for.
	 * @return	string|NULL		The key for the event for this volunteer registration
	 * @since	v0.1.0
	 */
	public function get_event_key_string();

	/**
	 * Get the name fixer station the volunteer has requested as her preferred station
	 *
	 * @return	string	The name of the fixer station the volunteer requested as her preferred station
	 * 	or NULL if no fixer station was requested by the fixer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_fixer_station_name();

	/**
	 * Get the name fixer station the volunteer has been assigned to for this event
	 *
	 * @return	string	The name of the fixer station the volunteer has been assigned to for this event
	 * 	or NULL if no fixer station has been assigned
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_fixer_station_name();

	/**
	 * Get a boolean indicating whether the volunteer has asked to act as an apprentice fixer for the event
	 *
	 * @return	boolean		TRUE if the volunteer has asked to act as an apprentice fixer, FALSE otherwise
	 *
	 * @since v0.1.0
	 */
	public function get_is_fixer_apprentice();

	/**
	 * Get the array of names of volunteer roles the volunteer has offered to perform for this event
	 *
	 * @return	string	The array of strings representing the preferred volunteer roles for this event
	 *	or NULL if no volunteer roles were requested by the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_volunteer_role_names_array();

	/**
	 * Get the array of names of volunteer roles the volunteer has been assigned to perform for this event
	 *
	 * @return	string	The array of strings representing the roles assigned to this volunteer for this event
	 *	or NULL if no volunteer roles were assigned to the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_volunteer_role_names_array();

	/**
	 * Get the registration comments supplied by the volunteer, for example "I need a ride to this event"
	 *
	 * @return	string	The comments supplied by the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_registration_comments();

	/**
	 * Get a boolean indicating whether the volunteer attended the event
	 *
	 * @return	boolean		TRUE if the volunteer attended the event, FALSE if the volunteer DID NOT attend,
	 * 	or NULL if it is not known whether the volunteer attended
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_attendance();

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_registration_descriptor_source();

} // interface