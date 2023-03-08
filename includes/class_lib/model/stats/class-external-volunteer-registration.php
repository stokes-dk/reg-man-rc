<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;

/**
 * Describes a registration for a volunteer who registered (using an external system and not this plugin) for an event
 *
 * An instance of this class contains the information related to a volunteer registration for an event.
 *
 * @since v0.1.0
 *
 */
class External_Volunteer_Registration implements Volunteer_Registration_Descriptor {

	private $full_name;
	private $public_name;
	private $email;
	private $event_key;
	private $preferred_fixer_station;
	private $assigned_fixer_station;
	private $is_fixer_apprentice;
	private $preferred_volunteer_roles_array;
	private $assigned_volunteer_roles_array;
	private $comments;
	private $attendance;
	private $source; // the source for this external registration, e.g. "legacy"

	/**
	 * Get all the volunteer registrations (fixers and non-fixer volunteers)
	 *  defined by external volunteer registration providers, e.g. Legacy data volunteer registrations
	 *  for volunteers registered to any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing volunteer registrations supplied by
	 * active add-on plugins for external registration data providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose volunteer registrations are to be returned.
	 * @return \Reg_Man_RC\Model\Volunteer_Registration_Descriptor[]
	 */
	public static function get_all_external_volunteer_registrations( $event_keys_array ) {
		$key_data_array = self::get_event_key_data_array( $event_keys_array );
		/**
		 * Get all volunteer registrations (fixers and non-fixer volunteers) defined under the external providers for the specified set of events.
		 *
		 * Each external data provider will extract all its volunteer registrations and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external registration.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 * @param	string[][]	$key_data_array	An array of event key descriptors whose registered fixers are to be returned.
		 *  Each array element is an associative array like, array( 'rc-evt' => '1234', 'rc-prv => 'ecwd', 'rc-rcr' => '' );
		 */
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_all_volunteer_registrations', array(), $key_data_array );
		$result = array();
//		Error_Log::var_dump( $desc_data_arrays );
		foreach ( $desc_data_arrays as $data_array ) {
			$item = self::instantiate_from_data_array( $data_array );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get the fixer registrations (registrations that include a fixer role)
	 *  defined by external volunteer registration providers, e.g. Legacy data volunteer registrations
	 *  for fixers registered to any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing fixer registrations supplied by
	 * active add-on plugins for external registration data providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose registered items are to be returned.
	 * @return \Reg_Man_RC\Model\Volunteer_Registration_Descriptor[]
	 */
	public static function get_external_fixer_registrations( $event_keys_array ) {
		$key_data_array = self::get_event_key_data_array( $event_keys_array );
		/**
		 * Get fixer registrations defined under the external providers for the specified set of events.
		 *
		 * Each external data provider will extract all its fixer registrations and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external registration.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 * @param	string[][]	$key_data_array	An array of event key descriptors whose registered fixers are to be returned.
		 *  Each array element is an associative array like, array( 'rc-evt' => '1234', 'rc-prv => 'ecwd', 'rc-rcr' => '' );
		 */
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_fixer_registrations', array(), $key_data_array );
		$result = array();
		foreach ( $desc_data_arrays as $data_array ) {
			$item = self::instantiate_from_data_array( $data_array );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	private static function get_event_key_data_array( $event_keys_array ) {
		if ( $event_keys_array === NULL ) {
			$result = NULL;
		} else {
			$result = array();
			foreach( $event_keys_array as $event_key ) {
				$key_obj = Event_Key::create_from_string( $event_key );
				$result[] = $key_obj->get_as_associative_array();
			} // endfor
		} // endfor
		return $result;
	} // function

	/**
	 * Get the registrations for the volunteer with the specified email address.
	 *
	 * This method will return an array of instances of this class describing volunteer role registrations supplied by
	 * active add-on plugins for external registration data providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	string		$email	The email address of the volunteer whose registrations are to be returned
	 * @return \Reg_Man_RC\Model\Volunteer_Registration_Descriptor[]
	 */
	public static function get_external_volunteer_registrations_for_email( $email ) {
		/**
		 * Get volunteer role registrations defined under the external providers for the specified email address.
		 *
		 * Each external data provider will extract all its volunteer role registrations and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external registration.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 * @param	string		$email				The email address for the volunteer whose registerations are to be returned.
		 */
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_volunteer_registrations_for_email', array(), $email );
		$result = array();
		foreach ( $desc_data_arrays as $data_array ) {
			$item = self::instantiate_from_data_array( $data_array );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get the volunteer role registrations (registrations for a non-fixer role)
	 *  defined by external volunteer registration providers, e.g. Legacy data volunteer registrations
	 *  for volunteers registered to any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing volunteer registrations supplied by
	 * active add-on plugins for external registration data providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose registered items are to be returned.
	 * @return \Reg_Man_RC\Model\Volunteer_Registration_Descriptor[]

	 */
	public static function get_external_non_fixer_registrations( $event_keys_array ) {
		$key_data_array = self::get_event_key_data_array( $event_keys_array );
		/**
		 * Get volunteer registration descriptors for volunteer who registered for a non-fixer role like "Registration".
		 *
		 * Note that some volunteers may have a fixer role AND a non-fixer role like "Setup & Cleanup".  Those registrations
		 *  are INCLUDED in the result.
		 *
		 * Also note that some volunteers select no role at all intending to show up and perform a task assigned at the event.
		 * Those registrations are also included in the result of this method.
		 *
		 * Each external data provider will extract all its non-fixer role registrations and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external registration.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 * @param	string[][]	$key_data_array	An array of event key descriptors whose registered volunteers are to be returned.
		 *  Each array element is an associative array like, array( 'rc-evt' => '1234', 'rc-prv => 'ecwd', 'rc-rcr' => '' );
		 */
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_non_fixer_registrations', array(), $key_data_array );
		$result = array();
		foreach ( $desc_data_arrays as $data_array ) {
			$item = self::instantiate_from_data_array( $data_array );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function


	/**
	 * Instantiate this class using the data array provided.
	 *
	 * This method will return a single instance of this class describing the item specified by the provided data.
	 *
	 * @since v0.1.0
	 * @api
	 * @param	string[]	$data_array	{
	 * 		An associative array of strings describing the external volunteer registration
	 *
	 * 		@type	string	'full-name'					The full name of the volunteer if known, e.g. "David Stokes"
	 * 		@type	string	'public-name'				The public name used for the volunteer, e.g. "David S"
	 * 		@type	string	'email'						The volunteer's email address if known
	 * 		@type	string	'event-id'					The ID (used within its event provider domain) for the event
	 * 		@type	string	'event-provider'			The external event provider or NULL if the event is internal to this plugin
	 * 		@type	string	'event-recur-id'			The recurrence ID if it's a repeating event, otherwise NULL
	 * 		@type	string	'preferred-fixer=station'	The fixer station (if any) the volunteer has requested to work at the event
	 * 		@type	string	'assigned-fixer=station'	The fixer station (if any) the volunteer has been assigned to work at the event
	 * 		@type	string	'is-fixer-apprentice'		"TRUE" if this volunteer has asked to participate as an apprentice fixer
	 * 		@type	string	'attended-event'			"TRUE" if this volunteer attended the event, "FALSE" if they were a no-show,
	 * 			NULL (or absent) if this information is not known
	 * 		@type	string	'source'					A string indicating the source of this descriptor, e.g. "legacy"
	 * }
	 * @return	External_Item		The External_Item object constructed from the data provided.
	 */
	private static function instantiate_from_data_array( $data_array ) {
		$result = new self();

//		Error_Log::var_dump( $data_array );

		$result->full_name		= isset( $data_array[ 'full-name' ] )		? $data_array[ 'full-name' ] : NULL;
		$result->public_name	= isset( $data_array[ 'public-name' ] )		? $data_array[ 'public-name' ] : NULL;
		$result->email			= isset( $data_array[ 'email' ] )			? $data_array[ 'email' ] : NULL;

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_id = $data_array[ 'event-id' ];
			$provider_id = isset( $data_array[ 'event-provider' ] ) ? $data_array[ 'event-provider' ] : NULL;
			$recur_id = isset( $data_array[ 'event-recur-id' ] ) ? $data_array[ 'event-recur-id' ] : NULL;
			$event_key = Event_Key::create( $event_id, $provider_id, $recur_id );
			$result->event_key = $event_key->get_as_string();
		} // endif

		$result->preferred_fixer_station	= isset( $data_array[ 'preferred-fixer-station' ] )	? $data_array[ 'preferred-fixer-station' ] : NULL;
		$result->assigned_fixer_station	= isset( $data_array[ 'assigned-fixer-station' ] )	? $data_array[ 'assigned-fixer-station' ] : NULL;

		if ( isset( $data_array[ 'is-fixer-apprentice' ] ) ) {
			$result->is_fixer_apprentice = ( 'true' == strtolower( $data_array[ 'is-fixer-apprentice' ] ) );
		} else {
			$result->is_fixer_apprentice = FALSE;
		} // endif

		if ( isset( $data_array[ 'preferred-volunteer-roles' ] ) ) {
			$result->preferred_volunteer_roles_array = json_decode( $data_array[ 'preferred-volunteer-roles' ] );
		} else {
			$result->preferred_volunteer_roles_array = array();
		} // endif

		if ( isset( $data_array[ 'assigned-volunteer-roles' ] ) ) {
			$result->assigned_volunteer_roles_array = json_decode( $data_array[ 'assigned-volunteer-roles' ] );
		} else {
			$result->assigned_volunteer_roles_array = array();
		} // endif

		if ( isset( $data_array[ 'volunteer-registration-comments' ] ) ) {
			$result->comments = $data_array[ 'volunteer-registration-comments' ];
		} // endif

		$result->preferred_fixer_station	= isset( $data_array[ 'volunteer-registration-comments' ] )	? $data_array[ 'volunteer-registration-comments' ] : NULL;

		if ( isset( $data_array[ 'volunteer-attended-event' ] ) ) {
			if ( 'true' == strtolower( $data_array[ 'volunteer-attended-event' ] ) ) {
				$result->attendance = TRUE;
			} elseif ( 'false' == strtolower( $data_array[ 'volunteer-attended-event' ] ) ) {
				$result->attendance = FALSE;
			} // endif
		} // endif

		$result->source		= isset( $data_array[ 'source' ] )		? $data_array[ 'source' ] : __( 'external', 'reg-man-rc' );

		return $result;
	} // function

	/**
	 * Get the volunteer's name as a single string.
	 * To protect the volunteer's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_full_name() {
		return $this->full_name;
	} // function

	/**
	 * Get the volunteer's public name.
	 * To protect the volunteer's privacy this name is the one shown in public and should be something like
	 * the volunteer's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_public_name() {
		return $this->public_name;
	} // function

	/**
	 * Get the key for the event that the volunteer has registered for
	 * @return	string|NULL		The key for the event for this volunteer registration
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		return $this->event_key;
	} // function

	/**
	 * Get the volunteer's email, if supplied.
	 * To protect the volunteer's privacy their email is never shown in public.
	 * The email is used only to identify returning volunteers and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_volunteer_email() {
		return $this->email;
	} // function

	/**
	 * Get the event for which this volunteer has registered
	 * @return	Event	The event for which this volunteer has registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			if ( isset( $this->event_key ) ) {
				$this->event = Event::get_event_by_key( $event_key );
			} // endif
		} // endif
		return $this->event;
	} // function

	/**
	 * Get the name fixer station the volunteer has requested as her preferred station
	 *
	 * @return	string	The name of the fixer station the volunteer requested as her preferred station
	 * 	or NULL if no fixer station was requested by the fixer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_fixer_station_name() {
		return $this->preferred_fixer_station;
	} // function

	/**
	 * Get the name fixer station the volunteer has been assigned to for this event
	 *
	 * @return	string	The name of the fixer station the volunteer has been assigned to for this event
	 * 	or NULL if no fixer station has been assigned
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_fixer_station_name() {
		return $this->assigned_fixer_station;
	} // function

	/**
	 * Get a boolean indicating if the volunteer has asked to participate as an apprentice fixer at the event
	 * @return	boolean|NULL	TRUE if the volunteer has asked to be an apprentice, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_fixer_apprentice() {
		return $this->is_fixer_apprentice;
	} // function

	/**
	 * Get the array of names of volunteer roles the volunteer has offered to perform for this event
	 *
	 * @return	string	The array of strings representing the preferred volunteer roles for this event
	 *	or NULL if no volunteer roles were requested by the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_volunteer_role_names_array() {
		return $this->preferred_volunteer_roles_array;
	} // function

	/**
	 * Get the array of names of volunteer roles the volunteer has been assigned to perform for this event
	 *
	 * @return	string	The array of strings representing the roles assigned to this volunteer for this event
	 *	or NULL if no volunteer roles were assigned to the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_volunteer_role_names_array() {
		return $this->assigned_volunteer_roles_array;
	} // function

	/**
	 * Get the comments supplied by the fixer or volunteer for this registration.
	 * @return	string		Any comments supplied by the volunteer during registration
	 * @since	v0.1.0
	 */
	public function get_volunteer_registration_comments() {
		return $this->comments;
	} // function

	/**
	 * Get a boolean indicating whether the volunteer attended the event
	 *
	 * @return	boolean		TRUE if the volunteer attended the event, FALSE if the volunteer DID NOT attend,
	 * 	or NULL if it is not known whether the volunteer attended
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_attendance() {
		return $this->attendance;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_registration_descriptor_source() {
		return $this->source;
	} // function

} // class