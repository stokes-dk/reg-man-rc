<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor_Factory;
use Reg_Man_RC\Model\Encryption\Encryption;

/**
 * An instance of this class represents a single person who may attend multiple events and register items for repair.
 * A visitor is identified by their email address when it is provided or by full name when no email is given.
 * A Visitor Registration object represents a visitor who registered one or more items for an event.
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

	const EMAIL_META_KEY				= self::POST_TYPE . '-email';
	const EMAIL_HAHSCODE_META_KEY		= self::POST_TYPE . '-email-code';
	const FULL_NAME_META_KEY			= self::POST_TYPE . '-full-name';
	const FULL_NAME_HAHSCODE_META_KEY	= self::POST_TYPE . '-full-name-code';
	
	const FIRST_EVENT_KEY_META_KEY		= self::POST_TYPE . '-first-event-key';
	const IS_JOIN_MAIL_LIST_META_KEY	= self::POST_TYPE . '-is-join-mail-list';

	private $post;
	private $post_id;
	private $display_name; // A displayable name for this visitor
	private $public_name; // The name used for the visitor when shown in public places, e.g. "Dave S"
	private $full_name; // The visitor's full name, e.g. "Dave Stokes", encrypted before stored in db
	private $encrypted_full_name; // The encrypted version of the full name, read out of db
	private $email; // Optional, the visitor's email address, encrypted before stored in db
	private $encrypted_email; // The encrypted version of the email address, read out of db
	private $wp_user; // Optional, the registered user associated with this volunteer
	private $is_authored_by_current_wp_user; // TRUE if this visitor was authored by the current WP User
	private $is_instance_for_current_wp_user; // TRUE if this visitor represents the current WP User
	private $partially_obscured_email;
	private $is_join_mail_list; // Set to TRUE if the visitor wants to join the mailing list for the repair cafe
	private $first_event_key; // The key (string) for the first event attended by the visitor or NULL if the visitor's first event is not known
//	private $age_group;
//	private $geographic_zone;
	private $visitor_registration_array;
	private $event_count;
	private $item_count;

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
			
		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Get all visitors
	 * @return	Visitor[]	An array containing all existing visitor records visible to the current user
	 * @since 	v0.1.0
	 */
	public static function get_all_visitors() {

		$result = array();

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'orderby'				=> 'post_title',
				'order'					=> 'ASC',
				'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
				
		);

		$query = new \WP_Query( $args );
		$post_array = $query->posts;

		foreach ( $post_array as $post ) {
			$item = self::instantiate_from_post( $post );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !

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
	 * Get the visitors with the specified hashcode value for the specified meta key.
	 * This is used to find a visitor with a specific email or full name.
	 * Rather than decrypting every email in the database, we store a hashcode and find those records.
	 * Note that it is possible (although extremely unlikely) for two visitors with different emails
	 *   or different full names to have the same hashcode
	 * @param	string		$meta_key	The key for the metadata to be searched
	 * @param	string		$hashcode	The hashcode value for the metadata
	 * @param	string|int	$exclude_id	An optional ID of the record to be ignored in the search
	 * @return	Visitor[]	An array of instances of this class whose hahcode is the one specified 
	 * @since	v0.5.0
	 */
	private static function get_visitors_by_hashcode( $meta_key, $hashcode, $exclude_id = NULL ) {

		$result = array();
		
		if ( ! empty ( $hashcode ) ) {

			// N.B. All visitor records are private so we need to include private status explicitly
			//  even for users who would not normal be able to see private posts
			// This may be necessary, for example, when we need to get the volunteer record for
			//  someone who is not logged in but is trying to access the Volunteer Area
			// TODO: Is there any reason we should include 'draft' ??
			$post_status = array( 'publish', 'private' );
			
			$args = array(
					'post_type'			=> self::POST_TYPE,
					'posts_per_page'	=>	-1, // Get all posts
					'post_status'		=> $post_status, 
					'meta_key'			=> $meta_key,
					'meta_query'		=> array(
								array(
										'key'		=> $meta_key,
										'value'		=> $hashcode,
										'compare'	=> '=',
								)
					)
			);

			// Exclude the specified ID if one was supplied by the caller
			if ( ! empty( $exclude_id ) ) {
				$args[ 'post__not_in' ] = array( $exclude_id );
			} // endif

			$query = new \WP_Query( $args );
			$post_array = $query->posts;

			foreach ( $post_array as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor

//			wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !

		} // endif

		return $result;
	} // function
	
	/**
	 * Get a visitor record by their email address
	 * Note that it is possible for the administrator to mistakenly create two records with the same email address.
	 * If that is the case, this method will return the first one it finds
	 * @param	string	$email	The email address of the visitor to be retrieved
	 * @return	Visitor|NULL	The visitor with the specified email address, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_visitor_by_email( $email ) {
		
		$result = NULL;
		if ( ! empty( $email ) ) {
			
			// We must always search by lowercase email since that's what is stored
			$email = strtolower( $email );
			
			$hashcode = Encryption::hash( $email );
			$visitor_array = self::get_visitors_by_hashcode( self::EMAIL_HAHSCODE_META_KEY, $hashcode );
			foreach ( $visitor_array as $visitor ) {
				$enc = $visitor->get_encrypted_email();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( $dec == $email ) ) {
					$result = $visitor;
					break;
				} // endif
			} // endif
		} // endif
			
		return $result;

	} // function
	
	/**
	 * Get an array of visitor records with the specified email address excluding the specified volunteer ID.
	 * Note that it is possible for the administrator to mistakenly create two records with the same email address.
	 * @param	string		$email		The email address of the records to be retrieved
	 * @param	string|int	$exclude_id	An optional ID of the record to be ignored in the results.  This is used to find duplicates.
	 * @return	Visitor[]	The array of visitors with the specified email address
	 * @since 	v0.9.9
	 */
	public static function get_all_visitors_by_email( $email, $exclude_id = NULL ) {
		
		$result = array();
		if ( ! empty( $email ) ) {
			
			// We must always search by lowercase email since that's what is stored
			$email = strtolower( $email );
			
			$hashcode = Encryption::hash( $email );
			$visitor_array = self::get_visitors_by_hashcode( self::EMAIL_HAHSCODE_META_KEY, $hashcode, $exclude_id );
			foreach ( $visitor_array as $visotor ) {
				$enc = $visotor->get_encrypted_email();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( $dec == $email ) ) {
					$result[] = $visotor;
				} // endif
			} // endif
		} // endif
			
		return $result;

	} // function

	/**
	 * Get a visitor record by their full name
	 * @param	string	$full_name	The full name of the visitor to be retrieved
	 * @return	Visitor|NULL	The visitor with the specified full name, or NULL if the record is not found
	 * @since 	v0.5.0
	 */
	public static function get_visitor_by_full_name( $full_name ) {
		
		$result = NULL;
		if ( ! empty( $full_name ) ) {
			$lower_full_name = strtolower( $full_name ); // always compare in lower case
			$hashcode = Encryption::hash( $lower_full_name );
			$visitor_array = self::get_visitors_by_hashcode( self::FULL_NAME_HAHSCODE_META_KEY, $hashcode );
			foreach ( $visitor_array as $visitor ) {
				$enc = $visitor->get_encrypted_full_name();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( strtolower( $dec ) == $lower_full_name ) ) {
					$result = $visitor;
					break;
				} // endif
			} // endif
		} // endif
			
		return $result;
	} // function


	/**
	 * Get a visitor record by their email address or full name
	 * @param	string		$email			The email address of the visitor to be retrieved
	 * @param	string		$full_name		The full name of the visitor to be retrieved
	 * @return	Visitor|NULL	The visitor with the specified email address or full name, or NULL if the record is not found.
	 * The email argument takes first priority.  We will look for the visitor with the specified email and if not found
	 * then we will look for the one with the specified full name.
	 * @since 	v0.1.0
	 */
	public static function get_visitor_by_email_or_full_name( $email, $full_name ) {
		
//		global $wpdb;

		$email = isset( $email ) ? trim( $email ) : NULL;
		$full_name = isset( $full_name ) ? trim( $full_name ) : NULL;

		if ( empty( $email ) && empty( $full_name ) ) {
			
			$result = NULL;

		} elseif ( ! empty( $email ) ) {
			
			$result = self::get_visitor_by_email( $email ); // Use the email when available

		} else {
			
			$result = self::get_visitor_by_full_name( $full_name ); // otherwise, try by full name
			
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
	public static function create_visitor( $public_name, $full_name = NULL, $email = NULL, $first_event_key = NULL, $is_join = NULL ) {
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
			if ( ! empty( $result ) ) {
				if ( ! empty( $full_name) || ! empty( $email ) ) {
					$result->set_personal_info( $full_name, $email );
				} // endif
				if ( isset( $first_event_key ) ) {
					$result->set_first_event_key( $first_event_key );
				} // endif
				if ( isset( $is_join ) ) {
					$result->set_is_join_mail_list( $is_join );
				} // endif
			} // endif
		} // endif

		return $result;

	} // function
	
	/**
	 * Get the visitor record for the current WP user.
	 * If no user is logged in, this will return NULL.
	 * @param	boolean	$is_create_new_visitor	When TRUE causes this function to construct and return a new Visitor
	 * if there is a user logged in and no Visitor exists for the user and the user is able to edit Visitor records.
	 * If FALSE or the user is unable to edit Visitors then NULL is returned
	 * @return	Visitor|NULL
	 */
	public static function get_visitor_for_current_wp_user( $is_create_new_visitor = FALSE ) {
		
		$result = NULL; // Assume we can't find or create the visitor
		$wp_user = wp_get_current_user();
		if ( ! empty( $wp_user->ID ) ) {
			
			$user_email = $wp_user->user_email;
			if ( ! empty( $user_email ) ) {
			
				$result = self::get_visitor_by_email( $user_email );
				if ( empty( $result ) && $is_create_new_visitor ) {

					// There is no existing visitor, so create it if we can
					$capability = 'edit_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL;
					if ( current_user_can( $capability ) ) {
					
						if ( ! empty( $wp_user->first_name ) && ! empty( $wp_user->last_name ) ) {
							
							/* Translators: %1$s is the first name, %2$s is the last name */
							$full_name_format = _x( '%1$s %2$s', 'A format for constructing a full name from first and last names', 'reg-man-rc' );
							$full_name = sprintf( $full_name_format, $wp_user->first_name, $wp_user->last_name );
							
							$last_initial = substr( $wp_user->last_name, 0, 1 );
							/* Translators: %1$s is the first name, %2$s is the last name */
							$public_name_format = _x( '%1$s %2$s', 'A format for constructing a public name from first name and last initial', 'reg-man-rc' );
							$public_name = sprintf( $public_name_format, $wp_user->first_name, $last_initial );
							
						} elseif ( ! empty( $wp_user->first_name ) ) {
							
							$full_name = $wp_user->first_name;
							$public_name = $wp_user->first_name;
							
						} else {
							
							$full_name = '';
							$public_name = $wp_user->display_name;
							
						} // endif
						
						$result = self::create_visitor( $public_name, $full_name, $user_email );
						
					} // endif
					
				} // endif
				
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
			$last_initial = substr( $parts[ 1 ], 0, 1 );
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
	 * Set the public name for this visitor
	 * @param string $public_name
	 */
	public function set_public_name( $public_name ) {
		if ( ! empty( $public_name ) ) {
			$post_id = $this->get_post_id();
			$post_update_args = array(
				'ID'			=> $post_id,
				'post_title'	=> $public_name
			);
			wp_update_post( $post_update_args );
		} // endif
	} // function
	
	/**
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_display_name() {
		
		if ( ! isset( $this->display_name ) ) {
			
			if ( is_admin() &&
				( 	current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user() ) ) {
			
				$full_name = $this->get_full_name();
				$this->display_name = ! empty( $full_name ) ? $full_name : $this->public_name;

			} else {
				
				$this->display_name = ! empty( $this->public_name ) ? $this->public_name : $this->post_id; // ID as last resort
				
			} // endif

		} // endif
		
		return $this->display_name;

	} // function
	
	/**
	 * This private function gets the encrypted version of the full name out of metadata
	 *  so that we can do things like compare it with a full name we're looking for.
	 * @return NULL|string
	 */
	private function get_encrypted_full_name() {
		
		if ( ! isset( $this->encrypted_full_name ) ) {
			
			$val = get_post_meta( $this->get_post_id(), self::FULL_NAME_META_KEY, $single = TRUE );

			$this->encrypted_full_name = ! empty( $val ) ? $val : NULL;

		} // endif
		
		return $this->encrypted_full_name;
		
	} // function
	
	/**
	 * Get the visitor's full name.
	 * Note that the visitor's full name must be available on the front end, for example, during visitor registration
	 * so that we can find returning visitors and re-use those records.
	 * It is also available on the backend to someone looking at their own record
	 * Care must be taken not to display this name.
	 * For a displayable version, always use get_public_name() or get_display_name().
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		
		if ( ! isset( $this->full_name ) ) {
			
			$this->full_name = NULL; // Assume we don't have it or can't access it

			if ( current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
				 $this->get_is_authored_by_current_wp_user() ||
				 $this->get_is_instance_for_current_wp_user() ) {

				$enc = $this->get_encrypted_full_name();
				$this->full_name = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
						
			} else {
				
				$this->full_name = $this->get_public_name();
				
			} // endif

		} // endif
		
		return $this->full_name;
		
	} // function

	/**
	 * Set the visitor's full name
	 * @param	string		$full_name	The visitor's full_name if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_full_name( $full_name ) {
		
				if ( current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
			$this->get_is_authored_by_current_wp_user() ||
			$this->get_is_instance_for_current_wp_user() ) {

			if ( empty( $full_name ) ) {
				
				delete_post_meta( $this->get_post_id(), self::FULL_NAME_META_KEY );
				delete_post_meta( $this->get_post_id(), self::FULL_NAME_HAHSCODE_META_KEY );
				
			} else {
				
				// We need a hashcode so we can search encrypted emails without decrypting everything
				$encrypted_data = Encryption::encrypt( $full_name );
				$lower_full_name = strtolower( $full_name ); // store hashchode using lower case
				$hashcode = Encryption::hash( $lower_full_name );
				
				// Update will add the meta data if it does not exist
				update_post_meta( $this->get_post_id(), self::FULL_NAME_META_KEY, $encrypted_data );
				update_post_meta( $this->get_post_id(), self::FULL_NAME_HAHSCODE_META_KEY, $hashcode );
				
			} // endif
			
			 // allow these to be re-acquired
			unset( $this->full_name );
			unset( $this->encrypted_full_name );
			
		} // endif

	} // function

	/**
	 * This private function gets the encrypted version of the email address out of metadata
	 *  so that we can do things like compare it with an email address we're looking for.
	 * @return NULL|string
	 */
	private function get_encrypted_email() {
		
		if ( ! isset( $this->encrypted_email ) ) {
			
			$val = get_post_meta( $this->get_post_id(), self::EMAIL_META_KEY, $single = TRUE );

			$this->encrypted_email = ! empty( $val ) ? $val : NULL;

		} // endif
		
		return $this->encrypted_email;
		
	} // function
	
	/**
	 * Get the visitor's email, if supplied
	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {
		
		if ( ! isset( $this->email ) ) {
			
			$this->email = NULL; // Assume we don't have it or can't access it
			
			// Users viewing their own record or users who can edit others' records can see the email
			if ( is_admin() &&
				(	current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user() ) ) {
	
				$enc = $this->get_encrypted_email();
				$this->email = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				
			} // endif
			
		} // endif
		
		return $this->email;
		
	} // function

	/**
	 * Set the email address for this volunteer
	 * @param	string	$email
	 */
	public function set_email( $email ) {

		if ( current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
			$this->get_is_authored_by_current_wp_user() ||
			$this->get_is_instance_for_current_wp_user() ) {

			if ( empty( $email ) ) {
				
				delete_post_meta( $this->get_post_id(), self::EMAIL_META_KEY );
				delete_post_meta( $this->get_post_id(), self::EMAIL_HAHSCODE_META_KEY );
				
			} else {
				
				// The email will always be stored in lowercase
				$email = strtolower( $email );
				
				// We need a hashcode so we can search encrypted emails without decrypting everything
				$encrypted_data = Encryption::encrypt( $email );
				$hashcode = Encryption::hash( $email );
				
				// Update will add the meta data if it does not exist
				update_post_meta( $this->get_post_id(), self::EMAIL_META_KEY, $encrypted_data );
				update_post_meta( $this->get_post_id(), self::EMAIL_HAHSCODE_META_KEY, $hashcode );
				
			} // endif
			
			 // allow these to be re-acquired
			unset( $this->email );
			unset( $this->encrypted_email );
			
		} // endif
		
	} // function
	
	/**
	 * Get a boolean indicating whether the current WP User is the author of this post.
	 * @return	boolean	TRUE if the current WP User is the author of this post, FALSE otherwise
	 * @since	v0.5.0
	 */
	private function get_is_authored_by_current_wp_user() {
		if ( ! isset( $this->is_authored_by_current_wp_user ) ) {
			$current_user_id = get_current_user_id(); // User's ID or 0 if not logged in
			if ( empty( $current_user_id ) ) {
				$this->is_authored_by_current_wp_user = FALSE;
			} else {
				$post = $this->get_post();
				$post_author = $post->post_author;
				$this->is_authored_by_current_wp_user = ! empty( $post_author )  ? ( $post_author == $current_user_id ) : FALSE;
			} // endif
		} // endif
		return $this->is_authored_by_current_wp_user;
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
	 * Get the display name for the WP_User object for this visitor. 
	 * If there is no associated user for this visitor then this will return NULL.
	 * @return string|NULL
	 */
	public function get_wp_user_display_name() {
		$wp_user = $this->get_wp_user();
		$result = ! empty( $wp_user ) ? $wp_user->display_name : NULL;
		return $result;
	} // function

	
	/**
	 * Get a partially obscured email address for this visitor. E.g. stok*****@yahoo.ca
	 * @return string
	 */
	public function get_partially_obscured_email() {
		if ( ! isset( $this->partially_obscured_email ) ) {
			
			// We can show a partially obscured email address on the public side for people who can read private
			if ( current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user() ) {

				$enc = $this->get_encrypted_email();
				$email = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				$this->partially_obscured_email = self::get_partially_obscured_form_of_email( $email );

			} // endif

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
	 * Set the visitor's full name and email address, if supplied
	 * @param	string|int	$id			The post ID for the visitor record
	 * @param	string		$full_name	The visitor's full_name if it is known, NULL otherwise
	 * @param	string		$email		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_personal_info( $full_name, $email ) {

		if ( current_user_can( 'edit_others_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ||
			$this->get_is_authored_by_current_wp_user() ||
			$this->get_is_instance_for_current_wp_user() ) {
		
			$this->set_full_name( $full_name );
			$this->set_email( $email );
			
		} // endif
		
	} // function

	/**
	 * Get the array of registrations for this visitor
	 * @return	Visitor_Registration_Descriptor[]
	 */
	private function get_visitor_registration_array() {
		if ( ! isset( $this->visitor_registration_array ) ) {
			$this->visitor_registration_array = Visitor_Registration_Descriptor_Factory::get_visitor_registrations_for_visitor( $this );
		} // endif
		return $this->visitor_registration_array;
	} // function
	
	/**
	 * Get a count of events this visitor has attended
	 * @return int
	 */
	public function get_event_count() {
		if ( ! isset( $this->event_count ) ) {
			$reg_array = $this->get_visitor_registration_array();
			$this->event_count = is_array( $reg_array ) ? count( $reg_array ) : 0;
		} // endif
		return $this->event_count;
	} // function

	/**
	 * Get a count of items this visitor has registered
	 * @return int
	 */
	public function get_item_count() {
		if ( ! isset( $this->item_count ) ) {
			$this->item_count = 0;
			$reg_array = $this->get_visitor_registration_array();
			if ( is_array( $reg_array ) ) {
				foreach( $reg_array as $visitor_reg ) {
					$curr_item_count = $visitor_reg->get_item_count();
					if ( isset( $curr_item_count ) ) {
						$this->item_count += $curr_item_count;
					} // endif
				} // endfor
			} // endif
		} // endif
		return $this->item_count;
	} // function
	
	/**
	 * Enforce the rule that each visitor may have at most 1 item active (in the queue or with a fixer) at any time
	 * Secondary items will be marked as "Standby"
	 * @param string $event_key
	 */
	public function enforce_single_active_item_rule( $event_key ) {
		$visitor_id = $this->get_id();
		$items_array = Item::get_items_registered_by_visitor_for_event( $visitor_id, $event_key );

		$is_active_found = FALSE;
		$in_queue_status = Item_Status::get_item_status_by_id( Item_Status::IN_QUEUE );
		$standby_status = Item_Status::get_item_status_by_id( Item_Status::STANDBY );
		
		// Loop through the visitor's items
		// if we find an active item, set a flag so we can change subsequent active items to Standby
		foreach( $items_array as $item ) {

			$status = $item->get_item_status();
			
			if ( isset( $status ) ) {

				if ( $is_active_found ) {

					// A previous item is already active so set this one to Standby if it's also active
					if ( $status->get_is_active() ) {
						$item->set_item_status( $standby_status );
					} // endif
					
				} else {
					
					// No previous item is active so set this one to active if it is on Standby
					if ( $status->get_id() === Item_Status::STANDBY ) {
						$item->set_item_status( $in_queue_status );
						$is_active_found = TRUE; // We just made an item active
					} // endif
					
				} // endif

				if ( $status->get_is_active() ) {
					
					// This is the first active item
					$is_active_found = TRUE;
					
				} // endif

			} // endif

		} // endif
		
	} // function
	
	/**
	 * Get the url to edit this custom post.
	 * @return	string|NULL		The url for the page to edit this custom post if the user is authorized, otherwise NULL.
	 * @since v0.9.9
	 */
	public function get_edit_url() {
		$post_id = $this->get_post_id();
		if ( ! current_user_can( 'edit_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_SINGULAR, $post_id ) ) {
			$result = NULL;
		} else {
			$base_url = admin_url( 'post.php' );
			$query_args = array(
					'post'		=> $post_id,
					'action'	=> 'edit',
			);
			$result = add_query_arg( $query_args, $base_url );
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
		
		$capability_singular = User_Role_Controller::VISITOR_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL;
		$required_capability = 'read_private'; // Only users with this capability will see the admin UI

		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Visitors', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				// There is no reason to allow these to be accessible via REST
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				 // Whether and where to show in admin menu? The main menu page will determine this
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural, $required_capability ),
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> Admin_Menu_Page::get_menu_position(), // Menu order position
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				// Note that page-attributes allows ordering of the posts
				'supports'				=> array( 'title', 'author' ),
				'taxonomies'			=> array(),
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
	 * Perform the necessary steps for this class when the plugin is uninstalled.
	 * For this class this means removing its table.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		
		// When the plugin is uninstalled, remove all posts of my type
		$stati = get_post_stati(); // I need all post statuses
		$posts = get_posts( array(
				'post_type'			=> self::POST_TYPE,
				'post_status'		=> $stati,
				'posts_per_page'	=> -1 // get all
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID );
		} // endfor
		
	} // function

} // class