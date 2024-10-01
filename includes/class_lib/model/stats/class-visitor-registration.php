<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class represents a visitor who registered one or more items for an event.
 *
 * Some of the data associated with this class is personal information.
 * That information is stored in a separate table outside the usual Wordpress database tables so that
 * other plugins are less likely to access it.
 *
 * @since	v0.1.0
 *
 */
class Visitor_Registration implements Visitor_Registration_Descriptor {

	private $event_key_string; // The key for the event that the visitor attended
	private $display_name; // The displayable name for this visitor
	private $public_name; // Name used when shown in public places like on a whiteboard of registered items, e.g. "Dave S Toaster"
	private $full_name; // The visitor's full name, e.g. "Dave Stokes"
	private $email; // Optional, the visitor's email address
	private $wp_user; // Optional, the registered user associated with this volunteer
	private $is_instance_for_current_wp_user; // TRUE if this visitor represents the current WP User
	private $is_join_mail_list; // Set to TRUE if the visitor wants to join the mailing list for the repair cafe
	private $is_first_event; // Set to TRUE if this is the visitor's first event, FALSE if returning, or NULL if not known
	private $item_count;

	/**
	 * Private constructor for the class.  Users of the class must call one of the static factory methods.
	 * @return	Visitor
	 * @since 	v0.1.0
	 */
	private function __construct() {
	} // constructor

	/**
	 * Instantiate this class using data from the database table or JSON serialized object
	 * @param	string[]	$data	The data for the new instance
	 * @return	Visitor
	 * @since 	v0.1.0
	 */

	private static function instantiate_from_data( $data ) {
//		Error_Log::var_dump( $data );
		$result = new self();
		$result->event_key_string	= isset( $data->event_key )			? $data->event_key : NULL;
		$result->full_name			= isset( $data->full_name )			? $data->full_name : NULL;
		$result->public_name		= isset( $data->public_name )		? $data->public_name : NULL;
		$result->email				= isset( $data->email )				? $data->email : NULL;
		$result->is_first_event		= isset( $data->is_first_event )	? boolval( $data->is_first_event ) : NULL;
		$result->is_join_mail_list	= isset( $data->is_join_mail_list )	? boolval( $data->is_join_mail_list ) : NULL;
		$result->item_count			= isset( $data->item_count )		? intval( $data->item_count ) : NULL;
//		Error_Log::var_dump( $result );
		return $result;
	} // function


	/**
	 * Get the visitor registrations for the specified array of events and the specified visitors.
	 * Note that visitor registrations are not explicitly stored in the database,
	 *  they are derived from items registered at events and visitor records.
	 *
	 * @param	string[]	$event_keys_array	An array of event keys specifying which events' visitor registrations
	 *  are to be returned OR NULL to return registrations from all events
	 * @param	Visitor		$visitor			The visitor whose registrations are to be returned
	 *  or NULL if registrations for all visitors should be returned
	 * @return	Visitor_Registration[]	An array of Visitor registrations for people who registered one or more items
	 *  at any of the specified events.
	 * @since 	v0.1.0
	 */
	public static function get_visitor_registrations( $event_keys_array, $visitor = NULL ) {
		
		if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) == 0) ) {
			
			$result = array(); // The request is for an empty set of events so return an empty set
			
		} else {

			// Otherwise, the request is for ALL events (event keys array is NULL) or some subset

			global $wpdb;

			$item_post_type = Item::POST_TYPE;
			$visitor_meta_key = Item::VISITOR_META_KEY;
			$event_meta_key = Item::EVENT_META_KEY;
			$first_event_meta_key = Visitor::FIRST_EVENT_KEY_META_KEY;
			$is_join_meta_key = Visitor::IS_JOIN_MAIL_LIST_META_KEY;
			
			/*
			 * Visitor registrations are derived from items registered at events.  These are custom posts.
			 * We need to join the Item posts with post meta to get the event and visitor,
			 * we need to join again with post meta to get the visitor's first event and flag for joining the mailing list,
			 * and we need to join the posts table to get the visitor's public name from the visitor post.
			 */

			$select =
				'SELECT ' .
				'count( * ) as item_count, ' .
				'visitor_posts.post_title as public_name, ' .
				'event_meta.meta_value as event_key, ' .
				"CASE WHEN ( first_event_meta.meta_value IS NOT NULL AND first_event_meta.meta_value != '' AND first_event_meta.meta_value = event_meta.meta_value ) THEN 1 ELSE 0 END as is_first_event, " .
				"CASE WHEN ( is_join_meta.meta_value IS NOT NULL AND is_join_meta.meta_value != '' ) THEN 1 ELSE 0 END as is_join_mail_list";

			$from = "FROM {$wpdb->posts} AS posts";

			$join_array = array();
			$join_array[] = "LEFT JOIN {$wpdb->postmeta} AS visitor_meta ON posts.ID = visitor_meta.post_id AND visitor_meta.meta_key = '$visitor_meta_key'";
			$join_array[] = "LEFT JOIN {$wpdb->postmeta} AS event_meta ON posts.ID = event_meta.post_id AND event_meta.meta_key = '$event_meta_key'";
			$join_array[] = "LEFT JOIN {$wpdb->postmeta} AS first_event_meta ON first_event_meta.post_id = visitor_meta.meta_value AND first_event_meta.meta_key = '$first_event_meta_key'";
			$join_array[] = "LEFT JOIN {$wpdb->postmeta} AS is_join_meta ON is_join_meta.post_id = visitor_meta.meta_value  AND is_join_meta.meta_key = '$is_join_meta_key'";
			$join_array[] = "LEFT JOIN {$wpdb->posts} AS visitor_posts ON visitor_posts.ID = visitor_meta.meta_value";
			$joins = implode( ' ', $join_array );
			
			$where = "WHERE posts.post_type = '$item_post_type' AND posts.post_status = 'publish' ";
			$group_by = 'GROUP BY visitor_meta.meta_value, event_meta.meta_value';
			
			if ( ! is_array( $event_keys_array ) && ! isset( $visitor ) ) {
				// We are getting all registrations for all events and all visitors so no need to prepare the statement
				$stmt = "$select $from $joins $where $group_by";
			} else {
				if ( is_array( $event_keys_array ) ) {
					$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
					$placeholders = implode( ',', $placeholder_array );
					$where .= " AND event_meta.meta_value IN ( $placeholders )";
					$args_array = $event_keys_array;
				} else {
					$args_array = array();
				} // endif
				if ( isset( $visitor ) ) {
					$visitor_id = $visitor->get_id();
					$where .= " AND visitor_meta.meta_value = '%s' ";
					$args_array[] = $visitor_id;
				} // endif
				$stmt = $wpdb->prepare( "$select $from $joins $where $group_by", $args_array );
			} // endif

//			Error_Log::var_dump( $stmt );

			$data_array = $wpdb->get_results( $stmt, OBJECT );
//			Error_Log::var_dump( $data_array );
			$result = array();
			foreach ( $data_array as $data ) {
				$obj = self::instantiate_from_data( $data );
				$result[] = $obj;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get the key for the event that the visitor attended
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
	 * Get the visitor's public name
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name() {
		return $this->public_name;
	} // function

	/**
	 * Get the visitor's name as a single string.
	 * To protect the visitor's privacy their full name is returned only to users who can view it
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		
		// Users viewing their own record or users with read private capability can see the full name
		if ( current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
				$this->get_is_instance_for_current_wp_user() ) {
		
			// This user can edit anyone's record OR this record belongs to this user
			$result = ! empty( $this->full_name ) ? $this->full_name : $this->get_public_name();
			
		} else {
			
			$result = $this->get_public_name();
			
		} // endif
		
		return $result;

	} // function

	/**
	 * Get the visitor's email, if supplied
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
	 * Get a boolean indicating whether this is the first event the visitor has attended.
	 * @return	boolean|NULL	TRUE if it's the visitor's first event, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_first_event() {
		return $this->is_first_event;
	} // function

	/**
	 * Get a boolean indicating if the visitor has asked to join the mailing list
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
	 * Get a descriptive label for the visitor including their name and email.
	 * If we are rendering the admin interface then this will include personal details,
	 *  otherwise it will be something like, "Dave S (stoke****@yahoo.ca)"
	 * @return string	A visitor label that quickly identifies a person to a human user, suitable for use as a select option
	 * @since v0.1.0
	 */
	public function get_label() {

		$capability = 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {

			$name = $this->get_full_name();
			if ( empty( $name ) ) {
				$name = __( '[No name provided]', 'reg-man-rc' ); // Just in case they provide no name, this shouldn't happen
			} // endif
			
			$email = $this->get_email();
			if ( empty( $email ) ) {
				$email = __( '[No email]', 'reg-man-rc' ); // Just in case they provide no email, this happens often
			} // endif

			/* translators: %1$s is a person's name, %2$s is the person's email address. */
			$label_format = _x( '%1$s : %2$s', 'A label for a person using their name and email address', 'reg-man-rc' );
			$result = sprintf( $label_format, $name, $email );

		} else {

			$name = $this->get_public_name();
			if ( empty( $name ) ) {
				$name = __( '[No name provided]', 'reg-man-rc' ); // Just in case they provide no name, this shouldn't happen
			} // endif
			$result = $name;

		} // endif

		return $result;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_visitor_registration_descriptor_source() {
		return __( 'registered', 'reg-man-rc' );
	} // function

} // class