<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor;
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

	const VISITOR_TABLE_NAME	= 'reg_man_rc_visitor';

	private $id;
	private $event_key; // The key for the event that the visitor attended
	private $public_name; // Name used when shown in public places like on a whiteboard of registered items, e.g. "Dave S Toaster"
	private $full_name; // The visitor's full name, e.g. "Dave Stokes"
	private $email; // Optional, the visitor's email address
	private $partially_obscured_email;
	private $is_join_mail_list; // Set to TRUE if the visitor wants to join the mailing list for the repair cafe
	private $is_first_event; // Set to TRUE if this is the visitor's first event, FALSE if returning, or NULL if not known
//	private $age_group;
//	private $geographic_zone;

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
		if ( !isset( $data->id ) ) {
			$result = NULL; // Must have at least an ID
		} else {
			$result = new self();
			$result->id					= $data->id;
			$result->event_key			= isset( $data->event_key )			? $data->event_key : NULL;
			$result->full_name			= isset( $data->full_name )			? $data->full_name : NULL;
			$result->public_name		= isset( $data->public_name )		? $data->public_name : NULL;
			$result->email				= isset( $data->email )				? $data->email : NULL;
			$result->is_first_event		= isset( $data->is_first_event )	? boolval( $data->is_first_event ) : NULL;
			$result->is_join_mail_list	= isset( $data->is_join_mail_list )	? boolval( $data->is_join_mail_list ) : NULL;
		} // endif
//		Error_Log::var_dump( $result );
		return $result;
	} // function

	/**
	 * Get the visitor IDs associated with the specified event, returned as an array.
	 * @param	NULL|string[]	$event_keys_array	An array of keys for the events whose visitor ids are to be returned
	 *  OR NULL to return all visitors
	 * @return	string[]	An array of IDs for visitors who registered at any of the specified events.
	 * @since 	v0.1.0
	 */
			/*
			 * SELECT visitor_meta.meta_value AS visitor_id
			 * FROM wp_posts as posts
			 * LEFT JOIN wp_postmeta AS visitor_meta ON posts.ID = visitor_meta.post_id AND visitor_meta.meta_key = 'reg-man-rc-item-visitor'
			 * LEFT JOIN wp_postmeta AS event_meta ON posts.ID = event_meta.post_id AND event_meta.meta_key = 'reg-man-rc-item-event'
			 * WHERE posts.post_type = 'reg-man-rc-item' AND event_meta.meta_value = $event_key
			 * GROUP BY visitor_meta.meta_value, event_meta.meta_value
			 *
			*/
/* FIXME - I don't think this is needed since I need to do get_visitors using a JOIN
	private static function get_visitor_id_array_by_event_keys_array( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_key_array ) == 0) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			global $wpdb;
			$item_post_type = Item::POST_TYPE;
			$visitor_meta_key = Item::VISITOR_META_KEY;
			$event_meta_key = Item::EVENT_META_KEY;
			$select = 'SELECT visitor_meta.meta_value AS visitor_id';
			$from = "FROM {$wpdb->posts} AS posts";
			$join1 = "LEFT JOIN {$wpdb->postmeta} AS visitor_meta ON posts.ID = visitor_meta.post_id AND visitor_meta.meta_key = '$visitor_meta_key'";
			$join2 = "LEFT JOIN {$wpdb->postmeta} AS event_meta ON posts.ID = event_meta.post_id AND event_meta.meta_key = '$event_meta_key'";
			$where = "WHERE posts.post_type = '$item_post_type' ";
			$group_by = 'GROUP BY visitor_meta.meta_value, event_meta.meta_value';

			if ( ! is_array( $event_keys_array ) ) {
				$stmt = "$select $from $join1 $join2 $where $group_by";
			} else {
				$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
				$placeholders = implode( ',', $placeholder_array );
				$where = "WHERE posts.post_type = '$item_post_type' AND event_meta.meta_value IN ( $placeholders )";
				$stmt = $wpdb->prepare( "$select $from $join1 $join2 $where $group_by", $event_keys_array );
			} // endif

			$data_array = $wpdb->get_results( $stmt, OBJECT );
			$result = array();
			foreach ( $data_array as $data ) {
				$result[] = $data->visitor_id;
			} // endif
		} // endif
		return $result;
	} // function
*/
	/**
	 * Get the visitor registrations for the specified array of events.
	 * Note that visitor registrations are not explicitly stored in the database, they are derived from items and visitors.
	 *
	 * @param	$event_keys_array	An array of event keys specifying which events' visitors are to be returned
	 *  OR NULL to return all visitors
	 * @return	Visitor[]	An array of Visitor registrations for people who registered one or more items
	 *  at any of the specified events.
	 * @since 	v0.1.0
	 */
	// TODO: IS this working properly??? Suppose a visitor goes to two events and always uses their email address
	// That is supposed to count as two visitors with two records in the result.  Is that what we get?
	public static function get_visitor_registrations_by_event_keys_array( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			global $wpdb;
			$visitor_table = $wpdb->prefix . Visitor::VISITOR_TABLE_NAME;
			$item_post_type = Item::POST_TYPE;
			$visitor_meta_key = Item::VISITOR_META_KEY;
			$event_meta_key = Item::EVENT_META_KEY;
			/*
			 * SELECT visitor.id as id, visitor.full_name as full_name, visitor.email as email,
			 *   visitor.is_join_mail_list as is_join_mail_list, visitor.first_event_key as first_event_key
			 * FROM wp_posts as posts
			 * LEFT JOIN wp_postmeta AS visitor_meta ON posts.ID = visitor_meta.post_id AND visitor_meta.meta_key = 'reg-man-rc-item-visitor'
			 * LEFT JOIN wp_postmeta AS event_meta ON posts.ID = event_meta.post_id AND event_meta.meta_key = 'reg-man-rc-item-event'
			 * LEFT JOIN wp_reg_man_rc_visitor as visitor ON visitor_meta.meta_value = visitor.id
			 * WHERE posts.post_type = 'reg-man-rc-item' AND event_meta.meta_value IN ( $event_keys_array )
			 * GROUP BY visitor_meta.meta_value, event_meta.meta_value
			 *
			*/

			$select =
				'SELECT ' .
				'visitor.id as id, ' .
				'visitor.public_name as public_name, ' .
				'event_meta.meta_value as event_key, ' .
				'visitor.is_join_mail_list as is_join_mail_list, ' .
				'CASE WHEN ( visitor.first_event_key = event_meta.meta_value ) THEN 1 ELSE 0 END AS is_first_event';
			// Users who can read private items can also read the visitors full name and email address
			$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
			if ( current_user_can( $capability ) ) {
				$select .=
					', ' .
					'visitor.full_name as full_name, ' .
					'visitor.email as email';
			} // endif

			$from = "FROM {$wpdb->posts} AS posts";
			$join1 = "LEFT JOIN {$wpdb->postmeta} AS visitor_meta ON posts.ID = visitor_meta.post_id AND visitor_meta.meta_key = '$visitor_meta_key'";
			$join2 = "LEFT JOIN {$wpdb->postmeta} AS event_meta ON posts.ID = event_meta.post_id AND event_meta.meta_key = '$event_meta_key'";
			$join3 = "LEFT JOIN $visitor_table AS visitor ON visitor_meta.meta_value = visitor.id";
			$where = "WHERE posts.post_type = '$item_post_type' ";
			$group_by = 'GROUP BY visitor_meta.meta_value, event_meta.meta_value';

			if ( ! is_array( $event_keys_array ) ) {
				$stmt = "$select $from $join1 $join2 $join3 $where $group_by";
			} else {
				$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
				$placeholders = implode( ',', $placeholder_array );
				$where = "WHERE posts.post_type = '$item_post_type' AND event_meta.meta_value IN ( $placeholders )";
				$stmt = $wpdb->prepare( "$select $from $join1 $join2 $join3 $where $group_by", $event_keys_array );
			} // endif

//			Error_Log::var_dump( $stmt );

			$data_array = $wpdb->get_results( $stmt, OBJECT );
			$result = array();
			foreach ( $data_array as $data ) {
				$obj = self::instantiate_from_data( $data );
				$result[] = $obj;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Returns a default version of the visitor's public name based on the full name specified
	 * @param	string	$full_name
	 * @return	string
	 */
	public static function get_default_public_name( $full_name ) {
		$parts = explode( ' ', $full_name ); // find space between first and last names
		if ( count( $parts ) > 1 ) {
			// When there are two or more names (separated by space) use first name plus last initial
			$last_initial = substr( $parts[1], 0, 1 );
			$result = $parts[ 0 ] . ' ' . $last_initial;
		} else {
			$result = $full_name; // give up and just use the full name
		} // endif
		return $result;
	} // function


	/**
	 * Get the ID for the visitor record
	 * @return	string
	 * @since 	v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the key for the event that the visitor attended
	 * @return	string|NULL		The key for the event
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		return $this->event_key;
	} // function

	/**
	 * Get the visitor's name as a single string.
	 * To protect the visitor's privacy their full name is returned only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) && ! empty( $this->full_name ) ) {
			$result = $this->full_name;
		} else {
			$result = $this->get_public_name();
		} // endif
		return $result;
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
	 * Get the visitor's email, if supplied
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
	 * Get a descriptive label for the visitor including their name and email.
	 * If we are rendering the admin interface then this will include personal details,
	 *  otherwise it will be something like, "Dave S (stoke****@yahoo.ca)"
	 * @return string	A visitor label that quickly identifies a person to a human user, suitable for use as a select option
	 * @since v0.1.0
	 */
	public function get_label() {

		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
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

	/**
	 * Perform the necessary steps for this class when the plugin is activated.
	 * For this class this means conditionally creating its database table using dbDelta().
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_activation() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$table = $wpdb->prefix . self::VISITOR_TABLE_NAME;

		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			full_name varchar(256) DEFAULT NULL,
			public_name varchar(256) NOT NULL,
			email varchar(256) DEFAULT NULL,
			is_join_mail_list tinyint(1) unsigned DEFAULT 0,
			first_event_key varchar(256) DEFAULT NULL,
			PRIMARY KEY	(id)
		) $charset_collate;";

		dbDelta( $sql );

	} // function

	/**
	 * Perform the necessary steps for this class when the plugin is uninstalled.
	 * For this class this means removing its table.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		global $wpdb;
		$table = $wpdb->prefix . self::VISITOR_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	} // function

} // class