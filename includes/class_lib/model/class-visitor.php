<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * An instance of this class represents a visitor.
 *
 * Some of the data associated with this class are personal information.
 * That personal information is stored in a separate table outside the usual Wordpress database tables so that
 * other plugins are less likely to access it.
 *
 * @since	v0.1.0
 *
 */
class Visitor {

	const POST_TYPE						= 'reg-man-rc-visitor';

	// An access key can be used to identify a visitor for things like getting their events feed
	// The key is generated and stored in postmeta so that we can use it to retrieve a visitor from the db
//	const ACCESS_KEY_META_KEY				= self::POST_TYPE . '-access-key';

	// We use a side table to store personal info about the visitor include full name and email
	const VISITOR_SIDE_TABLE_NAME		= 'reg_man_rc_visitor';

	const FIRST_EVENT_KEY_META_KEY		= self::POST_TYPE . '-first-event-key';
	const IS_JOIN_MAIL_LIST_META_KEY	= self::POST_TYPE . '-is-join-mail-list';

//	const VISITOR_EMAIL_COOKIE_NAME	= 'reg-man-rc-visitor-email';

//	private static $CURRENT_VISITOR_EMAIL; // The email for the current visitor
//	private static $CURRENT_VISITOR; // The visitor object for the current visitor

	private $post;
	private $post_id;
	private $public_name; // The name used for the visitor when shown in public places, e.g. "Dave S"
	private $full_name; // The visitor's full name, e.g. "Dave Stokes"
	private $email; // Optional, the visitor's email address
//	private $access_key; // An identifier that can be used to access this visitor, e.g. to get their events feed
//	private $has_public_profile; // A flag indicating whether this visitor has a public profile page
	private $partially_obscured_email;
	private $is_join_mail_list; // Set to TRUE if the visitor wants to join the mailing list for the repair cafe
	private $first_event_key; // The key (string) for the first event attended by the visitor or NULL if the visitor's first event is not known
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
	 * Create an instance of this class using the specified post
	 * @param	\WP_Post $post
	 * @return	Visitor|NULL
	 * @since	v0.1.0
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post or not the right type so I can't process it
		} else {
			$result = new self();
			$result->post = $post;
			$result->post_id = $post->ID;
			$result->public_name = isset( $post->post_title ) ? $post->post_title : NULL;
			// Note that the side table contains full name and email so it will only be accessed
			//  when the user can see private posts
			// TODO: I should be able to view MY OWN full name and email address if I can't read private
			$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
			$is_join_allowed = current_user_can( $capability );
			if ( ! isset( $post->visitor_table_joined ) && $is_join_allowed ) {
				self::join_post_to_visitor_table( $post );
			} // endif
			$result->full_name = isset( $post->visitor_full_name ) ? $post->visitor_full_name : NULL;
			$result->email = isset( $post->visitor_email ) ? $post->visitor_email : NULL;
		} // endif
		return $result;
	} // function

	/**
	 *
	 * @param \WP_Post $post
	 */
	private static function join_post_to_visitor_table( $post ) {
		global $wpdb;
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {
			$visitor_table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
			$alias = 'reg_man_rc_visitor';
			$fields = 	'1 as visitor_table_joined, ' .
						"$alias.full_name as visitor_full_name, " .
						"$alias.email as visitor_email";

			$query = "SELECT $fields FROM $visitor_table $alias WHERE {$alias}.post_id = %s";

			$stmt = $wpdb->prepare( $query, $post->ID );

			$result = $wpdb->get_row( $stmt );

			$post->visitor_table_joined = 1;
			$post->visitor_full_name = isset( $result->visitor_full_name ) ? $result->visitor_full_name : '';
			$post->visitor_email = isset( $result->visitor_email ) ? $result->visitor_email : '';
		} // endif
	} // function

	/**
	 * Get the visitor who made the current request.
	 * The current visitor is stored in a cookie with the request.
	 * If the cookie is not present or the visitor cannot be found based on the cookie value then NULL is returned.
	 * @return	Visitor|NULL	The visitor making the current request or NULL if no visitor is identified in the request.
	 */
/*
	public static function get_current_visitor() {
		if ( ! isset( self::$CURRENT_VISITOR ) ) {
			// Get the email from the cookie
			$visitor_email = self::get_visitor_email_cookie();
			if ( ! empty( $visitor_email ) ) {
				// Get the visitor from the email
				$is_login_required = self::get_is_login_required_for_email( $visitor_email );
				if ( $is_login_required ) {
					// If this visitor requires a login then make sure they are logged in
					if ( is_user_logged_in() ) {
						$user = wp_get_current_user();
						$user_email = $user->user_email;
						if ( $user_email == $visitor_email ) {
							self::$CURRENT_VISITOR = self::get_visitor_by_email( $visitor_email );
						} // endif
					} // endif
				} else {
					// If no login is required then just return the visitor based on their email
					self::$CURRENT_VISITOR = self::get_visitor_by_email( $visitor_email );
				} // endif
			} // endif
		} // endif
		return self::$CURRENT_VISITOR;
	} // function
*/

	/**
	 * Get the email address for the visitor who made the current request
	 * @return	string		The email address for the visitor viewing this page
	 */
/*
	public static function get_visitor_email_cookie() {
		if ( ! isset( self::$CURRENT_VISITOR_EMAIL ) ) {
			self::$CURRENT_VISITOR_EMAIL = Cookie::get_cookie( self::VISITOR_EMAIL_COOKIE_NAME );
			if ( empty( self::$CURRENT_VISITOR_EMAIL ) ) {
				// If there is no cookie stored then check if there is a user logged in
				$user = wp_get_current_user();
				if ( isset( $user ) && ( $user->ID !== 0 ) && ! empty( $user->user_email ) ) {
					self::$CURRENT_VISITOR_EMAIL = $user->user_email;
					self::set_visitor_email_cookie( $user->user_email );
				} // endif
			} // endif
		} // endif
		return self::$CURRENT_VISITOR_EMAIL;
	} // function

	public static function set_visitor_email_cookie( $email, $is_remember_me = FALSE ) {
		if ( empty( $email ) ) {
			// This means we need to remove the current visitor
			$result = Cookie::remove_cookie( self::VISITOR_EMAIL_COOKIE_NAME );
		} else {
			$name = self::VISITOR_EMAIL_COOKIE_NAME;
			$value = $email;
			$expires = $is_remember_me ? YEAR_IN_SECONDS : 0;
			$result = Cookie::set_cookie( $name, $value, $expires );
		} // endif
		return $result;
	} // function
*/

	/**
	 * Get all visitors
	 * @return	Visitor[]	An array containing all existing visitor records
	 * @since 	v0.1.0
	 */
	public static function get_all_visitors() {
		$result = array();
		$statuses = self::get_visible_statuses();
		$post_array = get_posts( array(
						'post_type'				=> self::POST_TYPE,
						'post_status'			=> $statuses,
						'posts_per_page'		=> -1, // get all
						'orderby'				=> 'post_title',
						'order'					=> 'ASC',
						'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
		) );
		foreach ( $post_array as $post ) {
			$item = self::instantiate_from_post( $post );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get a visitor record by their ID
	 * @param	string|int	$visitor_id		The ID of the visitor to be retrieved
	 * @return	Visitor|NULL		The visitor with the specified ID, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_visitor_by_id( $visitor_id ) {
		$post = get_post( $visitor_id );
		$result = self::instantiate_from_post( $post );
		return $result;
	} // function

	/**
	 * Get a visitor record by their email address
	 * @param	string		$email			The email address of the visitor to be retrieved
	 * @param	boolean		$is_store_email TRUE if the email address should be stored in the resulting object.
	 *  The result does not contain private information like the email address unless the current logged-in WP user has authority to view it.
	 *  However, under certain circumstances, like logging in to the visitor area, the user may have already
	 *  supplied the email address and it is convenient to just store it in the visitor object.
	 *  T
	 * @return	Visitor|NULL	The visitor with the specified email address, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_visitor_by_email( $email ) {
		global $wpdb;
		$email = trim( $email );
		if ( empty( $email ) ) {
			$result = NULL;
		} else {
			// I will look in the side table for the specified email then use the post id to create the Visitor object
			$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
			$query = "SELECT post_id FROM $table WHERE email='%s' AND email IS NOT NULL AND email != ''";
//			Error_Log::var_dump( $query, $email );
			$stmt = $wpdb->prepare( $query, $email );
			$data = $wpdb->get_row( $stmt, OBJECT );
			$post_id = isset( $data ) ? $data->post_id : NULL;
			$result = self::get_visitor_by_id( $post_id );
		} // endif
		return $result;
	} // function


	/**
	 * Get a visitor record by their email address
	 * @param	string		$email			The email address of the visitor to be retrieved
	 * @param	boolean		$is_store_email TRUE if the email address should be stored in the resulting object.
	 *  The result does not contain private information like the email address unless the current logged-in WP user has authority to view it.
	 *  However, under certain circumstances, like logging in to the visitor area, the user may have already
	 *  supplied the email address and it is convenient to just store it in the visitor object.
	 *  T
	 * @return	Visitor|NULL	The visitor with the specified email address, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_visitor_by_email_or_full_name( $email, $full_name ) {
		global $wpdb;

		$email = trim( $email );
		$full_name = trim( $full_name );

		if ( empty( $email ) && empty( $full_name ) ) {
			$result = NULL;

		} elseif ( ! empty( $email ) ) {
			$result = self::get_visitor_by_email( $email ); // Use the email when available

		} else {
			// I will look in the side table for the specified full name then use the post id to create the Visitor object
			$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
			$query = "SELECT post_id FROM $table WHERE full_name='%s' AND full_name IS NOT NULL AND full_name != ''";
//			Error_Log::var_dump( $query, $full_name );
			$stmt = $wpdb->prepare( $query, $full_name );
			$data = $wpdb->get_row( $stmt, OBJECT );
			$post_id = isset( $data ) ? $data->post_id : NULL;
			$result = self::get_visitor_by_id( $post_id );
		} // endif

		return $result;

	} // function


	/**
	 * Get the visitor whose access key is the one specified.
	 *
	 * @param	int|string	$access_key		The access key of the visitor who acts as proxy
	 * @return	Visitor	The visitors whose access key was specified
	 */
/*
	private static function get_visitor_by_access_key( $access_key ) {
		$result = array();
		$statuses = self::get_visible_statuses();
		if ( ! in_array( 'private', $statuses ) ) {
			// A visitor may use their access key to access their own record which may be private and not public
			// We need to be able to access that private record in this case and not restrict access to only public
			$statuses[] = 'private';
		} // function
		$args = array(
				'post_type'				=> self::POST_TYPE,
				'post_status'			=> $statuses,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'meta_query'			=> array(
							array(
									'key'		=> self::ACCESS_KEY_META_KEY,
									'value'		=> $access_key,
									'compare'	=> '=',
							)
				)
		);
		$query = new \WP_Query( $args );
		$posts = $query->posts;
		if ( is_array( $posts ) && isset( $posts[ 0 ] ) ) {
			$result = self::instantiate_from_post( $posts[ 0 ] );
		} // endif
		wp_reset_postdata(); // Required after using WP_Query()
		return $result;

	} // function
*/


	/**
	 * Get an array of post statuses that indicates what is visible to the current user.
	 * @param boolean	$is_look_in_trash	A flag set to TRUE if posts in trash should be visible.
	 * @return string[]
	 */
	private static function get_visible_statuses( $is_look_in_trash = FALSE ) {
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {
			$result = array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ); // don't get auto-draft
			if ( $is_look_in_trash ) {
				$result[] = 'trash';
			} // endif
		} else {
			$result = array( 'publish' );
		} // endif
		return $result;
	} // function

	/**
	 * Create a new visitor
	 *
	 * @param	string			$public_name			The visitor's preferred public name, e.g. first name and last initial
	 * @param	string			$full_name				The visitor's full name
	 * @param	string			$email					The visitor's email address
	 * @return	Visitor|null
	 */
	public static function create_visitor( $public_name, $full_name = NULL, $email = NULL ) {

		$args = array(
				'post_title'	=> $public_name,
				'post_status'	=> 'private',
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( __( 'Unable to create new visitor', 'reg-man-rc' ), $post_id );
			$result = NULL;
		} else {
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			if ( ! empty( $result ) && ( ( ! empty( $full_name) ) || ( ! empty( $email ) ) ) ) {
				$result->set_personal_info( $full_name, $email );
			} // endif
		} // endif

		return $result;

	} // function

	/**
	 * Update the visitor record with the specified ID
	 * @param	string|int	$id					The ID of the visitor record to be updated
	 * @param	string		$full_name			The visitor's full name, e.g. Dave Stokes
	 * @param	string		$email				The visitor's email address or NULL if not known
	 * @return	boolean		TRUE if the update was successful, FALSE otherwise
	 * @since	v0.1.0
	 */
 	public static function update_visitor( $id,  $full_name, $email ) {
		global $wpdb;
		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
		$vals = array(
			'full_name'				=> is_string( $full_name ) 			? trim( $full_name )		: '',
			'email'					=> is_string( $email )				? trim( $email )			: NULL,
		);
		$types = array_fill( 0, count( $vals ), '%s');
		$where = array( 'id' => $id );
		$where_format = array( '%s' );
		$update_result = $wpdb->update( $table, $vals, $where, $types, $where_format );
		$result = ( $update_result == 1 ) ? TRUE : FALSE;
		return $result;
	} // function

	/**
	 * Delete a visitor record
	 * @param	string|int	$visitor_id		The ID of the record to be deleted
	 * @return	boolean		TRUE if the delete was successful, FALSE if not
	 * @since	v0.1.0
	 */
	public static function delete_visitor( $visitor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
		$count = $wpdb->delete( $table, array( 'id' => $visitor_id ) , array( '%s' ) );
		$result = ( $count !== 0 ) ? TRUE : FALSE;
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
	 * Get the post object for this visitor.
	 * @return	\WP_Post	The post object for this visitor
	 * @since v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this visitor.
	 * @return	int		The post ID for this visitor
	 * @since v0.1.0
	 */
	private function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the ID for the visitor record
	 * @return	string
	 * @since 	v0.1.0
	 */
	public function get_id() {
		return $this->post_id;
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
	 * To protect the visitor's privacy their full name is returned only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		return ( current_user_can( $capability ) ) ? $this->full_name : $this->get_public_name();
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
	public function get_partially_obscured_email() {
		if ( ! isset( $this->partially_obscured_email ) ) {
			$email = $this->get_email();
			$this->partially_obscured_email = self::get_partially_obscured_form_of_email( $email );
		} // endif
		return $this->partially_obscured_email;
	} // function

	/**
	 * Get a partially obscured email address for this visitor. E.g. stok*****@yahoo.ca
	 * @param	string	$email 	The email address to be partially obscured
	 * @return	string	The partially obscured form of the specified email address
	 */
	public static function get_partially_obscured_form_of_email( $email ) {
		if ( empty( $email ) ) {
			$result = '';
		} else {
			$parts = explode( '@', $email, 2 );
			$name = $parts[0];
			$domain = isset( $parts[1] ) ? '@' . $parts[1]  : '';

			$name_len = strlen( $name );
			if ( $name_len == 0 ) {
			    $masked_name = '';
			} elseif ( $name_len == 1 ) {
			    $masked_name = '*';
			} elseif ( $name_len == 2 ) {
			    $masked_name = substr( $name, 0, 1 ) . '*';
			} else {
	//		    $len  = ceil( $name_len / 2 ) - 1;
	//		    $masked_name = substr( $name, 0, $len ) . str_repeat( '*', $name_len - $len - 1 ) . substr( $name, $name_len - 1, 1 );
				$len  = floor( $name_len / 2 );
			    $masked_name = substr( $name, 0, $len ) . str_repeat( '*', $name_len - $len ) . substr( $name, $name_len, 1 );
			} // endif

			$result =  $masked_name . $domain;
		} // endif

		return $result;
	} // function

	/**
	 * Get the key for the event that the visitor first attended, or NULL if the event is not known
	 * @return	string|NULL		The key for the visitor's first event, NULL if we don't know which even the visitor first attended
	 * @since	v0.1.0
	 */
	public function get_first_event_key() {
		if ( ! isset( $this->first_event_key ) ) {
			$val = get_post_meta( $this->get_post_id(), self::FIRST_EVENT_KEY_META_KEY, $single = TRUE );
			if ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val != '' ) ) {
				$this->first_event_key = $val;
			} // endif
		} // endif
		return $this->first_event_key;
	} // function

	/**
	 * Assign the key for the event that the visitor first attended
	 * @param	string|NULL	$first_event_key	The key for the visitor's first event, NULL if we don't know which even the visitor first attended
	 * @since	v0.1.0
	 */
	public function set_first_event_key( $first_event_key ) {
		if ( empty( $first_event_key ) ) {
			// This meta value is only present to identify the event
			// When there is no known first event we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::FIRST_EVENT_KEY_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::FIRST_EVENT_KEY_META_KEY, $first_event_key );
		} // endif
		unset( $this->first_event_key ); // allow it to be re-acquired
	} // function

	/**
	 * Get a boolean indicating if the visitor has asked to join the mailing list
	 * @return	boolean|NULL	TRUE if it's the visitor wants to join the mailing list, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_join_mail_list() {
		if ( ! isset( $this->is_join_mail_list ) ) {
			$val = get_post_meta( $this->get_post_id(), self::IS_JOIN_MAIL_LIST_META_KEY, $single = TRUE );
			if ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) {
				$this->is_join_mail_list = TRUE;
			} else {
				$this->is_join_mail_list = FALSE;
			} // endif
		} // endif
		return $this->is_join_mail_list;
	} // function

	/**
	 * Assign the boolean indicating whether the visitor has asked to join the mailing list
	 * @param	boolean|NULL	$is_join_mail_list	TRUE if it's the visitor wants to join the mailing list, FALSE if not
	 * @since	v0.1.0
	 */
	public function set_is_join_mail_list( $is_join_mail_list ) {
		if ( ! $is_join_mail_list ) {
			// This meta value is only present to indicate the positive, that the visitor asked to join the mailing list
			// When it's false we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::IS_JOIN_MAIL_LIST_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::IS_JOIN_MAIL_LIST_META_KEY, '1' );
		} // endif
		unset( $this->is_join_mail_list ); // allow it to be re-acquired
	} // function


	/**
	 * Get the access key for the visitor
	 * @return	string		The access key for this visitor.
	 * The access key uniquely identifies a visitor in a way that is hard to guess and does not openly expose
	 *  the visitor's email address.
	 * @since	v0.1.0
	 */
/*
	public function get_access_key() {
		if ( ! isset( $this->access_key ) ) {
			$val = get_post_meta( $this->get_post_id(), self::ACCESS_KEY_META_KEY, $single = TRUE );
			if ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) {
				$this->access_key = $val;
			} else {
				$this->access_key = wp_generate_uuid4();
				update_post_meta( $this->get_post_id(), self::ACCESS_KEY_META_KEY, $this->access_key );
			} // endif
		} // endif
		return $this->access_key;
	} // function
*/

	/**
	 * Get a flag indicating whether the visitor with the specified email address requires a login with password
	 *  to access the visitor area.
	 * The result is TRUE when there is a WP user with the same email address otherwise FALSE.
	 * Note that this is not an instance method because the visitor's email is not always available inside the instance,
	 *  for example when there is not logged in user or the user does not have sufficient admin authority.
	 * @param	string	$email	The email address of the visitor to be checked
	 * @return	boolean			A flag set to TRUE when the visitor must provide a password to acces the visitor area, FALSE otherwise
	 * @since	v0.1.0
	 */
/*
	public static function get_is_login_required_for_email( $email ) {
		$wp_user = ! empty( $email ) ? get_user_by( 'email', $email ) : FALSE;
		$result = ! empty( $wp_user );
		return $result;
	} // function
*/

	/**
	 * Set the visitor's full name and email address, if supplied
	 * @param	string|int	$id			The post ID for the visitor record
	 * @param	string		$full_name	The visitor's full_name if it is known, NULL otherwise
	 * @param	string		$email		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_personal_info( $full_name, $email ) {

		global $wpdb;
		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;

		$id = $this->get_post_id();

		$data = array(
				'post_id'	=> $id,
				'full_name'	=> $full_name,
				'email'		=> $email
		);
		$wpdb->replace( $table, $data ); // Note that replace() will insert the record if it does not exist

	} // function

	/**
	 * Delete the visitor's personal information from the side table
	 * @since	v0.1.0
	 */
	public function delete_personal_info( ) {

		global $wpdb;
		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
		$id = $this->get_post_id();
		$args = array( 'post_id' => $id );

		$wpdb->delete( $table, $args );

	} // function

	/**
	 * Get a descriptive label for the visitor including their name and email.
	 * If we are rendering the admin interface for a user that can read private posts then this will include personal details,
	 *  otherwise it will be just the person's public name like, 'Dave S.'
	 * @return string	A visitor label that quickly identifies a person to a human user, suitable for use as a select option
	 * @since v0.1.0
	 */
	public function get_label() {

		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {
			/* translators: %1$s is a person's name, %2$s is the person's email address. */
			$label_format = _x( '%1$s : %2$s', 'A label for a person using their name and email address', 'reg-man-rc' );
			$name = $this->get_full_name();
			if ( empty( $name ) ) {
				$name = $this->get_public_name();
			} // endif
			$email = $this->get_email();
			if ( empty( $email ) ) {
				$email = __( '[No email]', 'reg-man-rc' );
			} // endif
			$result = sprintf( $label_format, $name, $email );
		} else {
			$name = $this->get_public_name();
			if ( empty( $name ) ) {
				$name = __( '[No name]', 'reg-man-rc' );
			} // endif
			$result = $name; // Only show the public name!
		} // endif

		return $result;
	} // function

	/**
	 *  Register the Visitor custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		self::register_post_type();

	} // function

	/**
	 *  Register the Visitor custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	private static function register_post_type() {

		$labels = array(
				'name'					=> _x( 'Visitors', 'Visitor post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Visitor', 'Visitor post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Visitor' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Visitor', 'reg-man-rc'),
				'new_item'				=> __( 'New Visitor', 'reg-man-rc'),
				'all_items'				=> __( 'Visitors', 'reg-man-rc'), // This is the menu item title
				'view_item'				=> __( 'View Visitor', 'reg-man-rc'),
				'search_items'			=> __( 'Search Visitors', 'reg-man-rc'),
				'not_found'				=> __( 'Nothing found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'Nothing found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Visitors', 'reg-man-rc' )
		);

		$icon = 'dashicons-groups';
		$capability_singular = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Visitors', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> TRUE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.  5 is below Posts
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				// Note that page-attributes allows ordering of the posts
//				'supports'				=> array( 'title', 'editor', 'thumbnail' ),
				'supports'				=> array( 'title' ),
				'taxonomies'			=> array(
											 ),
				'has_archive'			=> FALSE, // is there an archive page?
				// Rewrite determines how the public page url will look.
				'rewrite'				=> FALSE,
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		register_post_type( self::POST_TYPE, $args );

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

		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;

		$sql = "CREATE TABLE $table (
			post_id bigint(20) unsigned NOT NULL,
			full_name varchar(256) DEFAULT NULL,
			email varchar(256) DEFAULT NULL,
			PRIMARY KEY	(post_id)
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
		$table = $wpdb->prefix . self::VISITOR_SIDE_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	} // function

} // class