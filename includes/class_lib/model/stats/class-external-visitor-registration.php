<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Event;

/**
 * Describes a visitor who registered (using an external system and not this plugin) one or more items at an event
 *
 * An instance of this class contains the information related to a visitor who registered one or more items at an event.
 *
 * @since v0.1.0
 *
 */
class External_Visitor_Registration implements Visitor_Registration_Descriptor {

	private $event_key_string;
	private $display_name;
	private $full_name;
	private $public_name;
	private $email;
	private $wp_user; // Optional, the registered user associated with this volunteer
	private $is_instance_for_current_wp_user; // TRUE if this visitor represents the current WP User
	private $is_first_event;
	private $is_join_mail_list;
	private $item_count;
	private $source;

	/**
	 * Get the external visitor registrations for the specified set of events and the specified visitor.
	 * These are visitor registrations stored in an external provider like Legacy data.
	 * Note that when retrieving registrations for a specific visitor you must provide either the email address or
	 *  full name for that visitor.  If neither is provided then registrations for all visitors will be returned.
	 *
	 * This method will return an array of instances of this class describing all visitors registered to the specified events supplied by
	 * active add-on plugins for external visitor providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	string[]|NULL	$event_keys_array	An array of event keys whose external visitor registrations are to be retrieved
	 *   OR NULL if visitor registrations for all events should be retrieved.
	 * @param	string|NULL		$email				The email address for the visitor whose external registrations are to be retrieved
	 *   OR NULL if the email address is not known or registrations for all visitors should be retrieved
	 * @param	string|NULL		$full_name			The full name of the visitor whose external registrations are to be retrieved
	 *   OR NULL if the full name is not known or registrations for all visitors should be retrieved
	 * @return	\Reg_Man_RC\Model\Stats\External_Visitor_Registration[]
	 */
	public static function get_external_visitor_registrations( $event_keys_array, $email = NULL, $full_name = NULL ) {
		/**
		 * Add all visitors defined under the external visitor data providers for the specified events
		 *
		 * Each external visitor data provider will extract its visitors and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one visitor.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 * @param	string[][]	$key_data_array		An array of event key descriptors whose visitors are to be returned.
		 *  Each array element is an associative array like, array( 'rc-date' => '20230619', 'rc-evt' => '1234', 'rc-prv => 'ecwd' );
		 */
		if ( $event_keys_array === NULL ) {
			$key_data_array = NULL;
		} else {
			$key_data_array = array();
			foreach( $event_keys_array as $event_key_string ) {
				$key_obj = Event_Key::create_from_string( $event_key_string );
				$key_data_array[] = $key_obj->get_as_associative_array();
			} // endfor
		} // endif
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_visitor_registrations', array(), $key_data_array, $email, $full_name );
		$result = array();
//	Error_Log::var_dump( $desc_data_arrays[ 0 ] );
		foreach ( $desc_data_arrays as $data_array ) {
			$visitor = self::instantiate_from_data_array( $data_array );
			if ( $visitor !== NULL ) {
				$result[] = $visitor;
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
	 * 		An associative array of strings describing the external visitor
	 *
	 * 		@type	string	'event-id'				The ID for the event used within its event provider domain
	 * 		@type	string	'event-provider'		The external event provider or NULL if the event is internal to this plugin
	 * 		@type	string	'event-date'			The event date if it is known, otherwise NULL
	 * 		@type	string	'full-name'				The full name of the visitor if known, e.g. "David Stokes"
	 * 		@type	string	'public-name'			The public name used for the visitor, e.g. "David S"
	 * 		@type	string	'email'					The visitor's email address if known
	 * 		@type	boolean	'is-first-event'		TRUE if this is the visitor's first event
	 * 		@type	boolean	'is-join-mail-list'		TRUE if this visitor has asked to join the mailing list
	 * 		@type	string	'source'				The source of this record, e.g. "legacy"
	 * }
	 * @return	External_Item		The External_Item object constructed from the data provided.
	 */

	private static function instantiate_from_data_array( $data_array ) {
		$result = new self();
//Error_Log::var_dump( $data_array );

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_desc_id = $data_array[ 'event-id' ];
			$provider_id = isset( $data_array[ 'event-provider' ] ) ? $data_array[ 'event-provider' ] : NULL;
			$recur_date = isset( $data_array[ 'event-date' ] ) ? $data_array[ 'event-date' ] : NULL;
			$event = Event::get_event_by_descriptor_id( $event_desc_id, $provider_id, $recur_date );
			if ( isset( $event ) ) {
//				$result->event = $event;
				$result->event_key_string = $event->get_key_string();
			} // endif
		} // endif

		$result->full_name		= isset( $data_array[ 'full-name' ] )		? $data_array[ 'full-name' ] : NULL;
		$result->public_name	= isset( $data_array[ 'public-name' ] )		? $data_array[ 'public-name' ] : NULL;
		$result->email			= isset( $data_array[ 'email' ] )			? $data_array[ 'email' ] : NULL;

		if ( isset( $data_array[ 'is-first-event' ] ) ) {
			$result->is_first_event = ( 'true' == strtolower( $data_array[ 'is-first-event' ] ) );
		} else {
			$result->is_first_event = FALSE;
		} // endif

		if ( isset( $data_array[ 'is-join-mail-list' ] ) ) {
			$result->is_join_mail_list = ( 'true' == strtolower( $data_array[ 'is-join-mail-list' ] ) );
		} else {
			$result->is_join_mail_list = FALSE;
		} // endif

		$result->item_count	= isset( $data_array[ 'item_count' ] )	? $data_array[ 'item_count' ] : NULL;

		$result->source		= isset( $data_array[ 'source' ] )		? $data_array[ 'source' ] : __( 'external', 'reg-man-rc' );

		return $result;
	} // function

	/**
	 * Get the key for the event that the visitor attended.
	 * @return	string|NULL		The key for the event
	 * @since	v0.1.0
	 */
	public function get_event_key_string() {
		return $this->event_key_string;
	} // function

	/**
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_display_name() {
		
		if ( ! isset( $this->display_name ) ) {
			if ( is_admin() && current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {
			
				$this->display_name = ! empty( $this->full_name ) ? $this->full_name : $this->public_name;
				
			} else {
				
				$this->display_name = $this->public_name;
				
			} // endif
			
			if ( empty( $this->display_name ) ) {
				$this->display_name =  __( '[No name]', 'reg-man-rc' );
			} // endif

		} // endif
		
		return $this->display_name;

	} // function
	
	/**
	 * Get the visitor's name as a single string.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {

		// Users who can edit others' visitor records can see the full name
		if ( current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {
		
			$result = ! empty( $this->full_name ) ? $this->full_name : $this->get_public_name();
			
		} else {
			
			$result = $this->get_public_name();
			
		} // endif
		
		return $result;

	} // function

	/**
	 * Get the visitor's public name.
	 * To protect the visitor's privacy this name is the one shown in public and should be something like
	 * the visitor's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name() {
		return $this->public_name;
	} // function

	/**
	 * Get a boolean indicating whether this is the first event the visitor has attended.
	 * @return	boolean|NULL	TRUE if it's the visitor's first event, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_first_event() {
		return $this->is_first_event;
	} // function

	/**
	 * Get the visitor's email, if supplied.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {
		
		if ( current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
				$this->get_is_instance_for_current_wp_user() ) {

			$result = $this->email;
			 
		} else {
			
			$result = NULL;
			
		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get a boolean indicating whether this instance represents the current WP User.
	 * Note that a WP User should be able to see the details of their own Visitor record.
	 * This function is used to implement that behaviour.
	 * @return	boolean	TRUE if the current WP User is represented by this instance, FALSE otherwise
	 * @since	v0.5.0
	 */
	private function get_is_instance_for_current_wp_user() {
		if ( ! isset( $this->is_instance_for_current_wp_user ) ) {
			$current_user_id = get_current_user_id(); // User's ID or 0 if not logged in
			if ( empty( $current_user_id ) ) {
				$this->is_instance_for_current_wp_user = FALSE;
			} else {
				$visitor_user = $this->get_wp_user();
				$this->is_instance_for_current_wp_user = ! empty( $visitor_user )  ? ( $visitor_user->ID === $current_user_id ) : FALSE;
			} // endif
		} // endif
		return $this->is_instance_for_current_wp_user;
	} // function

	/**
	 * Get the WP_User object for this visitor. 
	 * If there is no associated user for this visitor then this will return NULL.
	 * @return \WP_User|NULL
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
	 * Get a boolean indicating if the visitor has asked to join the mailing list.
	 * @return	boolean|NULL	TRUE if it's the visitor wants to join the mailing list, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_join_mail_list() {
		return $this->is_join_mail_list;
	} // function

	/**
	 * Get a count of the number of items registered by this visitor, if known.
	 * @return	int|NULL	A count of the number of items registered by this visitor or NULL if we don't know.
	 * @since	v0.1.0
	 */
	public function get_item_count() {
		return $this->item_count;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_visitor_registration_descriptor_source() {
		return $this->source;
	} // function



} // class