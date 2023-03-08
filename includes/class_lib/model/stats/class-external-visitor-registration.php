<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;

/**
 * Describes a visitor who registered (using an external system and not this plugin) one or more items at an event
 *
 * An instance of this class contains the information related to a visitor who registered one or more items at an event.
 *
 * @since v0.1.0
 *
 */
class External_Visitor_Registration implements Visitor_Registration_Descriptor {

	private $event_key;
	private $full_name;
	private $public_name;
	private $email;
	private $partially_obscured_email;
	private $is_first_event;
	private $is_join_mail_list;
	private $source;

	/**
	 * Get the visitors defined by external visitor providers, e.g. Legacy data visitors, that are registered to the specified set of events
	 *
	 * This method will return an array of instances of this class describing all visitors registered to the specified events supplied by
	 * active add-on plugins for external visitor providers like Registration Manager for Repair Cafe Legacy data
	 *
	 * @param	NULL|string[]	$event_key_array	An array of event keys whose external visitors are to be retrieved
	 *   OR NULL if all external visitors should be retrieved.
	 * @return	\Reg_Man_RC\Model\External_Visitor_Registration[]
	 */
	public static function get_external_visitor_registrations( $event_key_array ) {
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
		 *  Each array element is an associative array like, array( 'rc-evt' => '1234', 'rc-prv => 'ecwd', 'rc-rcr' => '' );
		 */
		if ( $event_key_array === NULL ) {
			$key_data_array = NULL;
		} else {
			$key_data_array = array();
			foreach( $event_key_array as $event_key ) {
				$key_obj = Event_Key::create_from_string( $event_key );
				$key_data_array[] = $key_obj->get_as_associative_array();
			} // endfor
		} // endif
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_visitor_registrations', array(), $key_data_array );
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
	 * 		@type	string	'full-name'				The full name of the visitor if known, e.g. "David Stokes"
	 * 		@type	string	'public-name'			The public name used for the visitor, e.g. "David S"
	 * 		@type	string	'email'					The visitor's email address if known
	 * 		@type	string	'first-event-id'		The ID (used within its event provider domain) for the visitor's first event if known
	 * 		@type	string	'first-event-provider'	The external event provider or NULL if the event is internal to this plugin
	 * 		@type	string	'first-event-recur-id'	The recurrence ID if it's a repeating event, otherwise NULL
	 * 		@type	string	'is-join-mail-list'		"TRUE" if this visitor has asked to join the mailing list
	 * 		@type	string	'source'				The source of this record, e.g. "legacy"
	 * }
	 * @return	External_Item		The External_Item object constructed from the data provided.
	 */

	private static function instantiate_from_data_array( $data_array ) {
		$result = new self();
//Error_Log::var_dump( $data_array );

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_id = $data_array[ 'event-id' ];
			$provider_id = isset( $data_array[ 'event-provider' ] ) ? $data_array[ 'event-provider' ] : NULL;
			$recur_id = isset( $data_array[ 'event-recur-id' ] ) ? $data_array[ 'event-recur-id' ] : NULL;
			$event_key = Event_Key::create( $event_id, $provider_id, $recur_id );
			$result->event_key = $event_key->get_as_string();
		} // endif

		$result->full_name		= isset( $data_array[ 'full-name' ] )		? $data_array[ 'full-name' ] : NULL;
		$result->public_name	= isset( $data_array[ 'public-name' ] )		? $data_array[ 'public-name' ] : NULL;
		$result->email			= isset( $data_array[ 'email' ] )			? $data_array[ 'email' ] : NULL;
/*
		if ( isset( $data_array[ 'first-event-id' ] ) ) {
			$event_id = $data_array[ 'first-event-id' ];
			$provider_id = isset( $data_array[ 'first-event-provider' ] ) ? $data_array[ 'first-event-provider' ] : NULL;
			$recur_id = isset( $data_array[ 'first-event-recur-id' ] ) ? $data_array[ 'first-event-recur-id' ] : NULL;
			$event_key = Event_Key::create( $event_id, $provider_id, $recur_id );
			$result->first_event_key = $event_key->get_as_string();
		} // endif
*/
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

		$result->source		= isset( $data_array[ 'source' ] )		? $data_array[ 'source' ] : __( 'external', 'reg-man-rc' );

		return $result;
	} // function

	/**
	 * Get the key for the event that the visitor attended.
	 * @return	string|NULL		The key for the event
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		return $this->event_key;
	} // function

	/**
	 * Get the visitor's name as a single string.
	 * To protect the visitor's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		return $this->full_name;
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
	 * To protect the visitor's privacy their email is never shown in public.
	 * The email is used only to identify returning visitors and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		return ( current_user_can( $capability ) ) ? $this->email : $this->get_partially_obscured_email();
	} // function

	/**
	 * Get a partially obscured email address for this visitor. E.g. stok*****@yahoo.ca
	 * @return string
	 */
	private function get_partially_obscured_email() {
		if ( ! isset( $this->partially_obscured_email ) ) {
			$email = $this->get_email();
			$this->partially_obscured_email = Visitor::get_partially_obscured_form_of_email( $email );
		} // endif
		return $this->partially_obscured_email;
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