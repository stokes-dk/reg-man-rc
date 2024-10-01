<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Visitor;

/**
 * Describes a registration for a volunteer who registered (using an external system and not this plugin) for an event
 *
 * An instance of this class contains the information related to a volunteer registration for an event.
 *
 * @since v0.1.0
 *
 */
class External_Volunteer_Registration implements Volunteer_Registration_Descriptor {

	private $display_name;
	private $full_name;
	private $public_name;
	private $email;
	private $wp_user; // Optional, the registered user associated with this volunteer
	private $is_authored_by_current_wp_user; // TRUE if this volunteer was authored by the current WP User
	private $is_instance_for_current_wp_user; // TRUE if this volunteer represents the current WP User
	private $event_key_string;
	private $event;
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
	 * @return \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor[]
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
		 *  Each array element is an associative array like, array( 'rc-date' => '20230619', 'rc-evt' => '1234', 'rc-prv => 'ecwd' );
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

	private static function get_event_key_data_array( $event_keys_array ) {
		if ( $event_keys_array === NULL ) {
			$result = NULL;
		} else {
//			Error_Log::var_dump( $event_keys_array );
			$result = array();
			foreach( $event_keys_array as $event_key_string ) {
				$key_obj = Event_Key::create_from_string( $event_key_string );
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
	 * @return \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor[]
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
	 * Get an array of event key for items registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string ) {
		/**
		 * Get all event keys for volunteer registrations defined under external item providers within the specified date range
		 *
		 * Each external provider will extract the event keys for its volunteer registrations and add them to the result.
		 * If the external provider is unable to determine its event keys then it will do nothing.
		 *
		 * @since v0.6.0
		 *
		 * @api
		 *
		 * @param	string	$min_key_date_string	The minimum date for the range in the format Ymd, e.g. 20230601
		 * @param	string	$max_key_date_string	The maximum date for the range in the format Ymd, e.g. 20230630
		 * @return	string[]	An array of event key strings for each event key with volunteers registered
		 */
		$keys_array = apply_filters( 'reg_man_rc_get_event_keys_for_volunteer_registrations_in_date_range', array(), $min_key_date_string, $max_key_date_string );
//	Error_Log::var_dump( $keys_array );
		$result = array();
		foreach ( $keys_array as $key_string ) {
			$key_object = Event_Key::create_from_string( $key_string );
			if ( ! empty( $key_object ) ) {
				$result[] = $key_object->get_as_string();
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
	 * 		@type	string	'event-date'				The event date if it is known, otherwise NULL
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

		$result->public_name	= isset( $data_array[ 'public-name' ] )		? $data_array[ 'public-name' ] : NULL;
		
		if ( current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
			$result->full_name	= isset( $data_array[ 'full-name' ] )	? $data_array[ 'full-name' ]	: NULL;
			$result->email		= isset( $data_array[ 'email' ] )		? $data_array[ 'email' ]		: NULL;
			if ( isset( $data_array[ 'volunteer-registration-comments' ] ) ) {
				$result->comments = $data_array[ 'volunteer-registration-comments' ];
			} // endif
		} // endif

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_desc_id = $data_array[ 'event-id' ];
			$provider_id = isset( $data_array[ 'event-provider' ] ) ? $data_array[ 'event-provider' ] : NULL;
			$recur_date = isset( $data_array[ 'event-date' ] ) ? $data_array[ 'event-date' ] : NULL;
			$event = Event::get_event_by_descriptor_id( $event_desc_id, $provider_id, $recur_date );
			if ( isset( $event ) ) {
				$result->event_key_string = $event->get_key_string();
			} // endif
		} // endif
		
		// We will attempt to convert external names for fixer stations and volunteer roles into our internal name
		if ( isset( $data_array[ 'preferred-fixer-station' ] ) ) {
			$station = Fixer_Station::get_fixer_station_by_name( $data_array[ 'preferred-fixer-station' ] );
			$result->preferred_fixer_station = isset( $station ) ? $station->get_name() : $data_array[ 'preferred-fixer-station' ];
		} // endif
		
		if ( isset( $data_array[ 'assigned-fixer-station' ] ) ) {
			$station = Fixer_Station::get_fixer_station_by_name( $data_array[ 'assigned-fixer-station' ] );
			$result->assigned_fixer_station = isset( $station ) ? $station->get_name() : $data_array[ 'assigned-fixer-station' ];
		} // endif
		
		
		if ( isset( $data_array[ 'is-fixer-apprentice' ] ) ) {
			$result->is_fixer_apprentice = ( 'true' == strtolower( $data_array[ 'is-fixer-apprentice' ] ) );
		} else {
			$result->is_fixer_apprentice = FALSE;
		} // endif

		$result->preferred_volunteer_roles_array = array();
		if ( isset( $data_array[ 'preferred-volunteer-roles' ] ) ) {
			$external_names_array = json_decode( $data_array[ 'preferred-volunteer-roles' ] );
			foreach ( $external_names_array as $external_name ) {
				$role = Volunteer_Role::get_volunteer_role_by_name( $external_name );
				$result->preferred_volunteer_roles_array[] = isset( $role ) ? $role->get_name() : $external_name;
			} // endfor
		} // endif

		$result->assigned_volunteer_roles_array = array();
		if ( isset( $data_array[ 'assigned-volunteer-roles' ] ) ) {
			$external_names_array = json_decode( $data_array[ 'assigned-volunteer-roles' ] );
			foreach ( $external_names_array as $external_name ) {
				$role = Volunteer_Role::get_volunteer_role_by_name( $external_name );
				$result->assigned_volunteer_roles_array[] = isset( $role ) ? $role->get_name() : $external_name;
			} // endfor
		} // endif

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
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_volunteer_display_name() {
		
		if ( ! isset( $this->display_name ) ) {

			$user_can_read_any_vol_name =
					current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) &&
					current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL );

			$event = $this->get_event();
			if ( empty( $event ) ) {
				$event_key_string = $this->get_event_key_string();
				$event = Event::create_placeholder_event( $event_key_string );
			} // endif
			
			$user_can_register_vols_for_event = ! empty( $event ) ? $event->get_is_current_user_able_to_register_volunteers() : FALSE;

			$user_can_read_this_vol_name =
					$user_can_register_vols_for_event ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user();
			
			if ( is_admin() && ( $user_can_read_any_vol_name || $user_can_read_this_vol_name ) ) {
			
				$this->display_name = ! empty( $this->full_name ) ? $this->full_name : $this->public_name;
				
			} else {
				
				if ( $user_can_read_any_vol_name || $user_can_read_this_vol_name ) {
				
					$this->display_name = $this->public_name;
	
				} else {
					
					$this->display_name = NULL;
					
				} // endif
				
			} // endif

		} // endif
		
		return $this->display_name;
		
	} // function
	
	/**
	 * Get the volunteer's name as a single string.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_full_name() {

		if ( is_admin() && current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
		
			$result = ! empty( $this->full_name ) ? $this->full_name : $this->get_volunteer_public_name();

		} else {
			
			$result = $this->get_volunteer_public_name();
			
		} // endif
		
		return $result;
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
	public function get_event_key_string() {
		return $this->event_key_string;
	} // function

	/**
	 * Get the volunteer's email, if supplied.

	 * @return	string|NULL		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_volunteer_email() {
		
		if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
				$this->get_is_instance_for_current_wp_user() ) {

			$result = $this->email;
			
		} else {
			
			$result = NULL;
			
		} // endif
		
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether this instance was authored by the current WP User.
	 * Note that a WP User should be able to see the details of the Volunteer registration records they authored.
	 * This function is used to implement that behaviour.
	 * @return	boolean	TRUE if the current WP User is represented by this instance, FALSE otherwise
	 * @since	v0.5.0
	 */
	public function get_is_authored_by_current_wp_user() {
		if ( ! isset( $this->is_authored_by_current_wp_user ) ) {
			return FALSE; // TODO, we do not have the data to implement this at the current time
		} // endif
		return $this->is_authored_by_current_wp_user;
	} // function

	/**
	 * Get a boolean indicating whether this instance represents the current WP User.
	 * Note that a WP User should be able to see the details of their own Volunteer registration record.
	 * This function is used to implement that behaviour.
	 * @return	boolean	TRUE if the current WP User is represented by this instance, FALSE otherwise
	 * @since	v0.5.0
	 */
	public function get_is_instance_for_current_wp_user() {
		if ( ! isset( $this->is_instance_for_current_wp_user ) ) {
			$current_user_id = get_current_user_id(); // User's ID or 0 if not logged in
			if ( empty( $current_user_id ) ) {
				$this->is_instance_for_current_wp_user = FALSE;
			} else {
				$volunteer_user = $this->get_wp_user();
				$this->is_instance_for_current_wp_user = ! empty( $volunteer_user )  ? ( $volunteer_user->ID === $current_user_id ) : FALSE;
			} // endif
		} // endif
		return $this->is_instance_for_current_wp_user;
	} // function

	/**
	 * Get the WP_User object for this volunteer. 
	 * If there is no associated user for this volunteer then this will return NULL.
	 * @return \WP_User|NULL|FALSE
	 */
	private function get_wp_user() {
		if ( ! isset( $this->wp_user ) ) {
//			Error_Log::var_dump( $this->email );
			if ( ! empty( $this->email ) ) {
				$this->wp_user = get_user_by( 'email', $this->email );
			} // endif
		} // endif
		return $this->wp_user;
	} // function

	/**
	 * Get the event for which this volunteer has registered
	 * @return	Event	The event for which this volunteer has registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			if ( isset( $this->event_key_string ) ) {
				$this->event = Event::get_event_by_key( $this->event_key_string );
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