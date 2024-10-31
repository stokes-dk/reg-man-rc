<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor_Factory;
use Reg_Man_RC\Model\Encryption\Encryption;

/**
 * An instance of this class represents a volunteer.
 *
 * Some of the data associated with this class are personal information.
 * That personal information is stored in a separate table outside the usual Wordpress database tables so that
 * other plugins are less likely to access it.
 *
 * @since	v0.1.0
 *
 */
class Volunteer {

	const POST_TYPE							= 'reg-man-rc-volunteer';
	const DEFAULT_PAGE_SLUG					= 'rc-volunteers';

	const IS_APPRENTICE_META_KEY			= self::POST_TYPE . '-is-apprentice';
	// Some volunteers do not supply an email address (and so can't enter the volunteer area) but allow another volunteer
	//  to act as a proxy and perform event registration on their behalf.
	// The ID of the proxy volunteer is stored in metadata.  I.e. the ID of the volunteer who acts as my proxy.
	const MY_PROXY_VOLUNTEER_ID_META_KEY	= self::POST_TYPE . '-my-proxy-id';

	const EMAIL_META_KEY					= self::POST_TYPE . '-email';
	const EMAIL_HAHSCODE_META_KEY			= self::POST_TYPE . '-email-code';
	const FULL_NAME_META_KEY				= self::POST_TYPE . '-full-name';
	const FULL_NAME_HAHSCODE_META_KEY		= self::POST_TYPE . '-full-name-code';
	
	const VOLUNTEER_AREA_LAST_ACCESS_DATETIME_META_KEY	= self::POST_TYPE . '-vol-area-last-access';
	
	// An access key can be used to identify a volunteer for things like getting their events feed
	// The key is generated and stored in postmeta so that we can use it to retrieve a volunteer from the db
//	const ACCESS_KEY_META_KEY				= self::POST_TYPE . '-access-key';

	const VOLUNTEER_EMAIL_COOKIE_NAME		= 'reg-man-rc-volunteer'; // encrypted email
	
	const DATE_DB_FORMAT					= 'Y-m-d H:i:s'; // This is how we format dates to store in the database
	private static $UTC_TIMEZONE; // We use UTC timezone for all DateTime objects stored in the database
	
	private static $VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST; // The email for the volunteer in the current request
	private static $VOLUNTEER_FOR_CURRENT_REQUEST; // The volunteer object for the current request

	private static $VOLUNTEER_FOR_CURRENT_WP_USER; // The volunteer object for the current WP User
	
	private $post;
	private $post_id;
	private $display_name; // A displayable name for this volunteer
	private $public_name; // The name used for the volunteer when shown in public places, e.g. "Dave S"
	private $full_name; // The volunteer's full name, e.g. "Dave Stokes", encrypted before stored in db
	private $encrypted_full_name; // The encrypted version of the full name, read out of db
	private $email; // Optional, the volunteer's email address, encrypted before stored in db
	private $display_email; // A displayable version of the email for this volunteer which may be
	// partially obscurred depending on the current user's authority and the current context
	private $encrypted_email; // The encrypted version of the email address, read out of db
	private $wp_user; // Optional, the registered user associated with this volunteer
	private $is_authored_by_current_wp_user; // TRUE if this volunteer was authored by the current WP User
	private $is_instance_for_current_wp_user; // TRUE if this volunteer represents the current WP User
//	private $access_key; // An identifier that can be used to access this volunteer, e.g. to get their events feed
	private $preferred_roles; // An array of Volunteer_Role objects indicating which roles this volunteer prefers
	private $preferred_fixer_station; // A Fixer_Station object indicating which station this fixer prefers
	private $is_apprentice; // A flag set to TRUE if this volunteer prefers to work as an apprentice
//	private $registration_array; // An array of registration records for this volunteer
	private $my_proxy_volunteer_id; // The ID of another volunteer who acts as a proxy for registering this volunteer
	private $proxy_for_array; // The array of other volunteers this volunteer acts as a proxy for
	private $vol_area_last_access_datetime; // The date and time the volunteer last accessed the volunteer area
	private $reg_count; // a count of volunteer registrations
	
	/**
	 * Private constructor for the class.  Users of the class must call one of the static factory methods.
	 * @return	Volunteer
	 * @since 	v0.1.0
	 */
	private function __construct() {
	} // constructor

	/**
	 * Create an instance of this class using the specified post
	 * @param	\WP_Post $post
	 * @return	Volunteer|NULL
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
	 * Get the volunteer for the current request.
	 * The volunteer is stored in a cookie with the request.
	 * If the cookie is not present or the volunteer cannot be found based on the cookie value then NULL is returned.
	 * @return	Volunteer|NULL	The volunteer making the current request or NULL if no volunteer is identified in the request.
	 */
	public static function get_volunteer_for_current_request() {
		if ( ! isset( self::$VOLUNTEER_FOR_CURRENT_REQUEST ) ) {

			// Get the email for the current volunteer
			$volunteer_email = self::get_volunteer_email_for_current_request();
			
			if ( ! empty( $volunteer_email ) ) {

				// Get the volunteer from the email
				$is_login_required = self::get_is_exist_registered_user_for_email( $volunteer_email );
				if ( $is_login_required ) {
					
					// If this volunteer requires a login then make sure they are logged in
					if ( is_user_logged_in() ) {
						$user = wp_get_current_user();
						$user_email = $user->user_email;
						if ( $user_email == $volunteer_email ) {
							self::$VOLUNTEER_FOR_CURRENT_REQUEST = self::get_volunteer_by_email( $volunteer_email );
						} // endif
					} // endif
					
				} else {
					
					// If no login is required then just return the volunteer based on their email
					// Note that not every user is a volunteer so the following may return NULL
					self::$VOLUNTEER_FOR_CURRENT_REQUEST = self::get_volunteer_by_email( $volunteer_email );

				} // endif

			} // endif

		} // endif

		return self::$VOLUNTEER_FOR_CURRENT_REQUEST;
		
	} // function

	/**
	 * Get the volunteer for the current WP user.
	 * If no volunteer record exists for the current WP user then NULL is returned.
	 * @return	Volunteer|NULL	The volunteer instance for the current WP user or NULL if no volunteer exists for this user
	 */
	public static function get_volunteer_for_current_wp_user() {

		$user = wp_get_current_user();
		if ( ! isset( self::$VOLUNTEER_FOR_CURRENT_WP_USER ) && ( ! empty( $user ) ) ) {
			
			self::$VOLUNTEER_FOR_CURRENT_WP_USER = self::get_volunteer_by_email( $user->user_email );
			
		} // endif
		return self::$VOLUNTEER_FOR_CURRENT_WP_USER;
	} // function

	
	/**
	 * Get the email address for the volunteer who made the current request
	 * @return	string		The email address for the volunteer for the current request
	 */
	public static function get_volunteer_email_for_current_request() {
		
		if ( ! isset( self::$VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST ) ) {

			self::$VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST = self::get_volunteer_email_cookie();

			if ( empty( self::$VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST ) ) {

				// If there is no cookie stored then check if there is a user logged in
				$user = wp_get_current_user();
				if ( isset( $user ) && ( $user->ID !== 0 ) && ! empty( $user->user_email ) ) {

					// Note that not every user is a volunteer
					$volunteer = self::get_volunteer_by_email( $user->user_email );
					
					if ( isset( $volunteer ) ) {

						self::$VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST = $user->user_email;

					} // endif

				} // endif

			} // endif

		} // endif
		
		return self::$VOLUNTEER_EMAIL_FOR_CURRENT_REQUEST;
		
	} // function

	
	/**
	 * Get the email address stored in the cookie
	 * @return	string		The email address for the volunteer viewing this page
	 */
	private static function get_volunteer_email_cookie() {
		
		$encrypted_email = Cookie::get_cookie( self::VOLUNTEER_EMAIL_COOKIE_NAME );
		$result = Encryption::decrypt( $encrypted_email );
		return $result;

	} // function

	public static function set_volunteer_email_cookie( $email, $is_remember_me = FALSE ) {
//		Error_Log::var_dump( $email, $is_remember_me, headers_sent() );
		if ( empty( $email ) ) {
			// This means we need to remove the current volunteer
			$result = Cookie::remove_cookie( self::VOLUNTEER_EMAIL_COOKIE_NAME );
		} else {
			$name = self::VOLUNTEER_EMAIL_COOKIE_NAME;
			$value = Encryption::encrypt( $email );
			$expires = $is_remember_me ? YEAR_IN_SECONDS : 0;
			$result = Cookie::set_cookie( $name, $value, $expires );
		} // endif
		return $result;
	} // function

	/**
	 * Get all volunteers
	 * @return	Volunteer[]	An array containing all existing volunteer records
	 * @since 	v0.1.0
	 */
	public static function get_all_volunteers() {
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
	 * Get a volunteer record by their ID
	 * @param	string|int	$volunteer_id		The ID of the volunteer to be retrieved
	 * @return	Volunteer|NULL		The volunteer with the specified ID, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_volunteer_by_id( $volunteer_id ) {
		$post = get_post( $volunteer_id );
		$result = self::instantiate_from_post( $post );
		return $result;
	} // function

	/**
	 * Get the volunteer record with the specified email address.
	 * Note that it is possible for the administrator to mistakenly create two volunteer records with the same email address.
	 * If that is the case, this method will return the first one it finds
	 * @param	string	$email	The email address of the volunteer to be retrieved
	 * @return	Volunteer|NULL	The volunteer with the specified email address, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_volunteer_by_email( $email ) {
		
		$result = NULL;
		if ( ! empty( $email ) ) {
			
			// We must always search by lowercase email since that's what is stored
			$email = strtolower( $email );
			
			$hashcode = Encryption::hash( $email );
			$volunteer_array = self::get_volunteers_by_hashcode( self::EMAIL_HAHSCODE_META_KEY, $hashcode );
			foreach ( $volunteer_array as $volunteer ) {
				$enc = $volunteer->get_encrypted_email();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( $dec == $email ) ) {
					$result = $volunteer;
					break;
				} // endif
			} // endif
		} // endif
			
		return $result;

	} // function

	/**
	 * Get an array of volunteer records with the specified email address excluding the specified volunteer ID.
	 * Note that it is possible for the administrator to mistakenly create two volunteer records with the same email address.
	 * @param	string		$email		The email address of the volunteer records to be retrieved
	 * @param	string|int	$exclude_id	An optional ID of the record to be ignored in the results.  This is used to find duplicates.
	 * @return	Volunteer[]	The array of volunteers with the specified email address
	 * @since 	v0.9.9
	 */
	public static function get_all_volunteers_by_email( $email, $exclude_id = NULL ) {
		
		$result = array();
		if ( ! empty( $email ) ) {
			
			// We must always search by lowercase email since that's what is stored
			$email = strtolower( $email );
			
			$hashcode = Encryption::hash( $email );
			$volunteer_array = self::get_volunteers_by_hashcode( self::EMAIL_HAHSCODE_META_KEY, $hashcode, $exclude_id );
			foreach ( $volunteer_array as $volunteer ) {
				$enc = $volunteer->get_encrypted_email();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( $dec == $email ) ) {
					$result[] = $volunteer;
				} // endif
			} // endif
		} // endif
			
		return $result;

	} // function

	/**
	 * Get a volunteer record by their full name
	 * @param	string	$full_name	The full name of the volunteer to be retrieved
	 * @return	Volunteer|NULL		The volunteer with the specified full name,
	 *  or NULL if the volunteer is not found
	 * @since 	v0.1.0
	 */
	public static function get_volunteer_by_full_name( $full_name ) {
		
		$result = NULL;
		if ( ! empty( $full_name ) ) {
			$lower_full_name = strtolower( $full_name ); // always compare in lower case
			$hashcode = Encryption::hash( $lower_full_name );
			$volunteer_array = self::get_volunteers_by_hashcode( self::FULL_NAME_HAHSCODE_META_KEY, $hashcode );
			foreach ( $volunteer_array as $volunteer ) {
				$enc = $volunteer->get_encrypted_full_name();
				$dec = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				if ( ! empty( $dec ) && ( strtolower( $dec ) == $lower_full_name ) ) {
					$result = $volunteer;
					break;
				} // endif
			} // endif
		} // endif
			
		return $result;

	} // function
	
	/**
	 * Get the volunteers with the specified hashcode value for the specified meta key.
	 * This is used to find a volunteer with a specific email or full name.
	 * Rather than decrypting every email in the database, we store a hashcode and find those records.
	 * Note that it is possible (although extremely unlikely) for two volunteers with different emails
	 *   or different full names to have the same hashcode
	 * @param	string		$meta_key	The key for the metadata to be searched
	 * @param	string		$hashcode	The hashcode value for the metadata
	 * @param	string|int	$exclude_id	An optional ID of the record to be ignored in the search
	 * @return	Volunteer[]	An array of instances of this class whose hahcode is the one specified 
	 * @since	v0.5.0
	 */
	private static function get_volunteers_by_hashcode( $meta_key, $hashcode, $exclude_id = NULL ) {

		$result = array();
		
		if ( ! empty ( $hashcode ) ) {

			// N.B. All volunteer records are private so we need to include private status explicitly
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
	 * Get the set of volunteers whose proxy is the one specified.
	 *
	 * This method will return an array of instances of this class containing volunteers whose proxy ID is the one specified.
	 * In other words, the volunteer with specified ID acts as a proxy for the collection of volunteers returned.
	 *
	 * @param	int|string	$proxy_volunteer_id		The ID of the volunteer who acts as proxy
	 * @return	Volunteer[]	The volunteers whose proxy was specified
	 */
	private static function get_volunteers_with_proxy_id( $proxy_volunteer_id ) {

		$result = array();

		// A volunteer may act as proxy for other volunteers whose record is marked as private
		// I still need to be able to access those records, but just for the case of selecting proxies
		$statuses = array( 'publish', 'private' );

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'post_status'			=> $statuses,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'meta_query'			=> array(
							array(
									'key'		=> self::MY_PROXY_VOLUNTEER_ID_META_KEY,
									'value'		=> $proxy_volunteer_id,
									'compare'	=> '=',
							)
				)
		);

		$query = new \WP_Query( $args );
		$post_array = $query->posts;

		foreach ( $post_array as $post ) {
			$instance = self::instantiate_from_post( $post );
			if ( $instance !== NULL ) {
				$result[] = $instance;
			} // endif
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !

		return $result;

	} // function

	/**
	 * Create a new volunteer
	 *
	 * @param	string			$public_name			The volunteer's preferred public name, e.g. first name and last initial
	 * @param	string			$full_name				The volunteer's full name
	 * @param	string			$email					The volunteer's email address
	 * @return	Volunteer|null
	 */
	public static function create_new_volunteer( $public_name, $full_name = NULL, $email = NULL ) {

		$result = NULL;
		
		if ( is_admin() && current_user_can( 'create_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
		
			$args = array(
					'post_title'	=> $public_name,
					'post_status'	=> 'private',
					'post_type'		=> self::POST_TYPE,
			);
	
			$post_id = wp_insert_post( $args, $wp_error = TRUE );
	
			if ( $post_id instanceof \WP_Error ) {

				Error_Log::log_wp_error( __( 'Unable to create new volunteer', 'reg-man-rc' ), $post_id );

			} else {
				
				$post = get_post( $post_id );
				$result = self::instantiate_from_post( $post );
				if ( ! empty( $result ) && ( ( ! empty( $full_name) ) || ( ! empty( $email ) ) ) ) {
					$result->set_personal_info( $full_name, $email );
				} // endif
				
			} // endif

		} // endif
		return $result;

	} // function

	/**
	 * Get the post object for this volunteer.
	 * @return	\WP_Post	The post object for this volunteer
	 * @since v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this volunteer.
	 * @return	int		The post ID for this volunteer
	 * @since v0.1.0
	 */
	private function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the ID for the volunteer record
	 * @return	string
	 * @since 	v0.1.0
	 */
	public function get_id() {
		return $this->post_id;
	} // function
	
	/**
	 * Get an ID for this volunteer that can be used as an argument for the volunteer calendar events feed.
	 * The ID allows us to return the events for a specific volunteer rather than the entire calendar of events.
	 * The ID includes the post ID of the volunteer as well as a hashcode so that it is very difficult to guess.
	 * @return string
	 */
	public function get_icalendar_feed_volunteer_id() {
		$id = $this->get_id();
		$hash = $this->get_icalendar_feed_volunteer_id_hash_code();
		$result = "{$id}-{$hash}";
		return $result;
	} // function
	
	private function get_icalendar_feed_volunteer_id_hash_code() {
		$email = $this->get_email();
		$result = Encryption::hash( $email );
		return $result;
	} // function
	
	/**
	 * Get the volunteer with the specified icalendar feed volunteer ID.
	 * @param string $ical_feed_volunteer_id
	 * @return NULL|self	The specified volunteer or NULL if the volunteer is not found
	 */
	public static function get_volunteer_for_icalendar_feed_id( $ical_feed_volunteer_id ) {
		$result = NULL;
		if ( ! empty( $ical_feed_volunteer_id ) ) {
			$parts = explode( '-', $ical_feed_volunteer_id ); 
			if ( count( $parts )  === 2 ) {
				$id = $parts[ 0 ];
				$hash = $parts[ 1 ];
				$volunteer = self::get_volunteer_by_id( $id );
				if ( ! empty( $hash ) && ( $hash == $volunteer->get_icalendar_feed_volunteer_id_hash_code() ) ) {
					$result = $volunteer;
				} // endif
			} // endif
		} // endif
		return $result;
	} // function
	
	/**
	 * Get the volunteer's public name
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name() {
		$result = $this->public_name;
		return $result;
	} // function

	/**
	 * Set the public name for this volunteer
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
			
			$user_can_see_any_vol_name = current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL );

			$user_can_see_this_vol_name = ( $this->get_is_authored_by_current_wp_user() || $this->get_is_instance_for_current_wp_user() );

			if ( is_admin() && ( $user_can_see_any_vol_name || $user_can_see_this_vol_name ) ) {
			
				$full_name = $this->get_full_name();
				$this->display_name = ! empty( $full_name ) ? $full_name : $this->public_name;
				
			} elseif ( $user_can_see_any_vol_name || $user_can_see_this_vol_name ) {
				
				$this->display_name = $this->public_name;
	
			} else {
				
				$this->display_name = NULL;
				
			} // endif
			
			if ( empty( $this->display_name ) ) {
				$this->display_name = '' . $this->get_id(); // as a last resort when nothing else is available
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
	 * Get the volunteer's full name.
	 * To protect the volunteer's privacy their full name is returned only for users allowed to see it
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {

		if ( ! isset( $this->full_name ) ) {
			
			$this->full_name = NULL; // Assume we don't have it or can't access it

			// Users viewing their own record or users with 'edit others' capability can see the full name
			if ( is_admin() &&
				(	current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user() ) ) {
			
				$enc = $this->get_encrypted_full_name();
				$this->full_name = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
						
			} // endif

		} // endif
		
		return $this->full_name;
		
	} // function
	
	/**
	 * Set the volunteer's full name
	 * @param	string		$full_name	The volunteer's full_name if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_full_name( $full_name ) {
		
		if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
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
	 * Get the volunteer's email, if supplied
	 * @return	string|NULL		The volunteer's email address if it is known and visible to this user, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {

		if ( ! isset( $this->email ) ) {
			
			$this->email = NULL; // Assume we don't have it or can't access it

			if ( is_admin() &&
					( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
					$this->get_is_authored_by_current_wp_user() ||
					$this->get_is_instance_for_current_wp_user() ) ) {
			
				$enc = $this->get_encrypted_email();
				$this->email = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				
			} // endif
			
		} // endif
		
		return $this->email;

	} // function
	
	/**
	 * Get the most descriptive version of the email available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full email then
	 *   it will be returned (if known), otherwise the partially obscurred version is returned
	 * @return string
	 */
	public function get_display_email() {

		if ( ! isset( $this->display_email ) ) {
			
			$user_can_see_any_vol_email = current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL );

			$user_can_see_this_vol_email = ( $this->get_is_authored_by_current_wp_user() || $this->get_is_instance_for_current_wp_user() );

			if ( ! is_admin() ) {

				$this->display_email = ''; // Nothing if we're not inside the admin back end

			} else if ( $user_can_see_any_vol_email || $user_can_see_this_vol_email ) {
			
				$this->display_email = $this->get_email();
				
			} else {
				
				$enc = $this->get_encrypted_email();
				$email = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
				
				$this->display_email = Visitor::get_partially_obscured_form_of_email( $email );
				
			} // endif
			
		} // endif
		
		return $this->display_email;

	} // function
	
	
	
	/**
	 * Get the email address of a volunteer who has registered for an event
	 * @param Volunteer_Registration $volunteer_registration
	 */
	public static function get_email_for_volunteer_registration( $volunteer_registration ) {
		$volunteer = $volunteer_registration->get_volunteer();
		$event = $volunteer_registration->get_event();
		$result = NULL; // assume the user can't access it
		if ( isset( $volunteer ) && isset( $event ) && is_admin() ) {
			if ( $event->get_is_current_user_able_to_view_registered_volunteer_emails() ) {
				$enc = $volunteer->get_encrypted_email();
				$result = ! empty( $enc ) ? Encryption::decrypt( $enc ) : NULL;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Set the email address for this volunteer
	 * @param	string	$email
	 */
	public function set_email( $email ) {

		if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
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
	 * Note that a WP User should be able to see the details of their own Volunteer record.
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
			$enc = $this->get_encrypted_email();
			$email = isset( $enc ) ? Encryption::decrypt( $enc ) : NULL;
//			Error_Log::var_dump( $email );
			if ( ! empty( $email ) ) {
				$this->wp_user = get_user_by( 'email', $email );
			} // endif
		} // endif
		return $this->wp_user;
	} // function

	/**
	 * Get the display name for the WP_User object for this volunteer. 
	 * If there is no associated user for this volunteer then this will return NULL.
	 * @return string|NULL
	 */
	public function get_wp_user_display_name() {
		$wp_user = $this->get_wp_user();
		$result = ! empty( $wp_user ) ? $wp_user->display_name : NULL;
		return $result;
	} // function

	/**
	 * Get a flag indicating whether the volunteer with the specified email has a corresponding 
	 *  registered user ID and password.
	 * If so, the password is required to access the volunteer area.
	 * The result is TRUE when there is a WP user with the same email address otherwise FALSE.
	 * Note that this is not an instance method because the volunteer's email is not always available inside the instance,
	 *  for example when there is not logged in user or the user does not have sufficient admin authority.
	 * @param	string	$email	The email address of the volunteer to be checked
	 * @return	boolean			A flag set to TRUE when the volunteer must provide a password to acces the volunteer area, FALSE otherwise
	 * @since	v0.1.0
	 */
	public static function get_is_exist_registered_user_for_email( $email ) {
		$wp_user = ! empty( $email ) ? get_user_by( 'email', $email ) : FALSE;
		$result = ! empty( $wp_user );
		return $result;
	} // function

	/**
	 * Set the volunteer's full name and email address, if supplied
	 * @param	string		$full_name	The volunteer's full_name if it is known, NULL otherwise
	 * @param	string		$email		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_personal_info( $full_name, $email ) {

		if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
			$this->get_is_authored_by_current_wp_user() ||
			$this->get_is_instance_for_current_wp_user() ) {
		
			$this->set_full_name( $full_name );
			$this->set_email( $email );
			
		} // endif

	} // function


	/**
	 * Get all registration descriptors for this volunteer
	 * @return Volunteer_Registration_Descriptor[]
	 */
	public function get_registration_descriptors() {
		$reg_array = Volunteer_Registration_Descriptor_Factory::get_volunteer_registration_descriptors_for_volunteer( $this );
		return $reg_array;
	} // function

	public function get_registration_descriptor_count() {
		if ( ! isset( $this->reg_count ) ) {
			$reg_array = $this->get_registration_descriptors();
			$this->reg_count = is_array( $reg_array ) ? count( $reg_array ) : 0;
		} // endif
		return $this->reg_count;
	} // function
	
	/**
	 * Get the array of roles the volunteer prefers to perform at events.
	 * @return	Volunteer_Role[]	An array of Volunteer_Role objects describing the roles this volunteer usually performs.
	 * @since	v0.1.0
	 */
	public function get_preferred_roles() {
		if ( ! isset( $this->preferred_roles ) ) {
			$this->preferred_roles = Volunteer_Role::get_volunteer_roles_for_post( $this->get_post_id() );
		} // endif
		return $this->preferred_roles;
	} // function

	/**
	 * Set the array of roles the volunteer is offering to perform at the event.
	 * @param	Volunteer_Role[]	$volunteer_roles_array		The new array of roles for this volunteer at this event.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_preferred_roles( $volunteer_roles ) {
		Volunteer_Role::set_volunteer_roles_for_post( $this->get_id(), $volunteer_roles );
		unset( $this->preferred_roles ); // allow it to be re-acquired
	} // function


	/**
	 * Get the fixer station the volunteer prefers to work at during events.
	 *
	 * @return	Fixer_Station|NULL	The fixer station this volunteer prefers to work at during an event
	 * or NULL if this volunteer is not a fixer or has no preferred fixer station.
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_fixer_station() {
		if ( ! isset( $this->preferred_fixer_station ) ) {
			$station_array = Fixer_Station::get_fixer_stations_for_post( $this->get_post_id() );
			$this->preferred_fixer_station = ( is_array( $station_array ) && isset( $station_array[ 0 ] ) ) ? $station_array[ 0 ] : NULL;
		} // endif
		return $this->preferred_fixer_station;
	} // function

	/**
	 * Assign the fixer station for this volunteer registration
	 *
	 * @param	Fixer_Station	$fixer_station	The fixer station being assigned to this volunteer registration
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public function set_preferred_fixer_station( $fixer_station ) {
		Fixer_Station::set_fixer_stations_for_post( $this->get_id(), array( $fixer_station ) );
		unset( $this->preferred_fixer_station ); // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get a boolean indicating whether the volunteer has asked to act as an apprentice fixer for the event
	 *
	 * @return	boolean		TRUE if the volunteer has asked to act as an apprentice fixer, FALSE otherwise
	 *
	 * @since v0.1.0
	 */
	public function get_is_fixer_apprentice() {
		if ( ! isset( $this->is_apprentice ) ) {
			$val = get_post_meta( $this->get_post_id(), self::IS_APPRENTICE_META_KEY, $single = TRUE );
			if ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) {
				$this->is_apprentice = TRUE;
			} else {
				$this->is_apprentice = FALSE;
			} // endif
		} // endif
		return $this->is_apprentice;
	} // function

	/**
	 * Assign the boolean indicating whether the volunteer will be an apprentice fixer for the event
	 * @param	boolean	$is_apprentice	TRUE if the volunteer is an apprentice, FALSE otherwise
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_is_fixer_apprentice( $is_apprentice ) {
		if ( ! $is_apprentice ) {
			// This meta value is only present to indicate the positive, that the fixer is an apprentice
			// When it's false we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::IS_APPRENTICE_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::IS_APPRENTICE_META_KEY, '1' );
		} // endif
		unset( $this->is_apprentice ); // allow it to be re-acquired
	} // function

	/**
	 * Get the ID of the volunteer who acts as a proxy for registering this volunteer,
	 *  or NULL if this volunteer has no proxy
	 *
	 * @return	string|NULL
	 *
	 * @since v0.1.0
	 */
	public function get_my_proxy_volunteer_id() {
		if ( ! isset( $this->my_proxy_volunteer_id ) ) {
			$val = get_post_meta( $this->get_post_id(), self::MY_PROXY_VOLUNTEER_ID_META_KEY, $single = TRUE );
			$this->my_proxy_volunteer_id = ! empty( $val ) ? $val : '';
		} // endif
		return $this->my_proxy_volunteer_id;
	} // function

	/**
	 * Assign the ID of the volunteer who acts as a proxy for registering this volunteer,
	 *  or NULL if this volunteer has no proxy
	 * @param	string|int	$my_proxy_volunteer_id
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_my_proxy_volunteer_id( $my_proxy_volunteer_id ) {
		if ( empty(  $my_proxy_volunteer_id ) ) {
			// This meta value is only present to identify the proxy
			// When there is no proxy we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::MY_PROXY_VOLUNTEER_ID_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::MY_PROXY_VOLUNTEER_ID_META_KEY, $my_proxy_volunteer_id );
		} // endif
		unset( $this->my_proxy_volunteer_id ); // allow it to be re-acquired
	} // function

	/**
	 * Get the array of volunteers that this volunteer acts as a proxy for.
	 * In other words, this volunteer is able to perform event registration on behalf of the returned volunteers.
	 *
	 * @return	Volunteer[]
	 *
	 * @since v0.1.0
	 */
	public function get_proxy_for_array() {
		if ( ! isset( $this->proxy_for_array ) ) {
			$this->proxy_for_array = self::get_volunteers_with_proxy_id( $this->get_id() );
		} // endif
		return $this->proxy_for_array;
	} // function


	private static function get_utc_timezone() {
		if ( ! isset( self::$UTC_TIMEZONE ) ) {
			self::$UTC_TIMEZONE = new \DateTimeZone( 'UTC' );
		} // endif
		return self::$UTC_TIMEZONE;
	} // function
	
	/**
	 * Get a DateTime object representing the last date and time this volunteer accessed the volunteer area
	 * @return	\DateTime|NULL
	 * @since v0.8.4
	 */
	public function get_volunteer_area_last_login_datetime() {
		if ( ! isset( $this->vol_area_last_access_datetime ) ) {
			$val = get_post_meta( $this->get_post_id(), self::VOLUNTEER_AREA_LAST_ACCESS_DATETIME_META_KEY, $single = TRUE );
			if ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) {
				try {
					// The date and time are stored in UTC but will be returned in local timezone
					// So we need to create a DateTime with UTC tz then change to local tz then create immutable
					$mutable_date_time = new \DateTime( $val, self::get_utc_timezone() );
					$mutable_date_time->setTimezone( wp_timezone() );
					$this->vol_area_last_access_datetime = \DateTimeImmutable::createFromMutable( $mutable_date_time );
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid dtend value supplied for an event description */
					$msg = sprintf( __( 'Invalid volunteer area access date and time: %1$s.', 'reg-man-rc' ), $val );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->vol_area_last_access_datetime;
	} // function

	/**
	 * Set a DateTime object representing the last date and time this volunteer accessed the volunteer area
	 * @return	\DateTime|NULL
	 * @since v0.8.4
	 */
	public function set_volunteer_area_last_login_datetime() {
		
		$mutable_date_time = new \DateTime( 'now', self::get_utc_timezone() );
		$datetime_str = $mutable_date_time->format( self::DATE_DB_FORMAT );
		// Update will add the meta data if it does not exist
		update_post_meta( $this->get_post_id(), self::VOLUNTEER_AREA_LAST_ACCESS_DATETIME_META_KEY, $datetime_str );
		
		$mutable_date_time->setTimezone( wp_timezone() ); // Change the timezone to local and then assign our value
		$this->vol_area_last_access_datetime = \DateTimeImmutable::createFromMutable( $mutable_date_time );
		
	} // function
	

	/**
	 * Get a descriptive label for the volunteer.
	 * If we are rendering the admin interface then this may include
	 *  the full name and email address if it is visible to the current user,
	 *  otherwise it will be just the person's public name like, 'Dave S.'
	 * @return string	A volunteer label that quickly identifies a person to a human user, suitable for use as a select option
	 * @since v0.1.0
	 */
	public function get_label() {

		if ( ! is_admin() ) {

			$result = $this->get_public_name();
			if ( empty( $result ) ) {
				$result = '' . $this->get_id(); // as a last resort when there is no public name
			} // endif
			
		} else {
			
			// E.g. "Dave Stokes <stokes@...> Appliances"
			$name = $this->get_display_name();
			$fixer_station = $this->get_preferred_fixer_station();
			$roles_array = $this->get_preferred_roles();
//			$email = $this->get_email();
			$email = $this->get_display_email();
			
			if ( ! empty( $fixer_station ) ) {
				
				if ( $this->get_is_fixer_apprentice() ) {
					
					/* Translators: %1$s is a fixer station like Appliances */
					$format = __( '%1$s (Apprentice)', 'reg-man-rc' );
					$roles = sprintf( $format, $fixer_station->get_name() );
					
				} else {

					$roles = $fixer_station->get_name();
					
				} // endif

			} elseif ( ! empty( $roles_array ) ) {

				$role_names_array = array();
				foreach( $roles_array as $role ) {
					$role_names_array[] = $role->get_name();
				} // endfor
				
				$separator = _x( ', ', 'Used to separate role names in a list', 'reg-man-rc' );
				$roles = implode( $separator, $role_names_array );
					
			} else {
				
				$roles = NULL;
				
			} // endif
			
			if ( ! empty( $email ) && ! empty( $roles ) ) {
				
				/* Translators: %1$s is a volunteer's name, %2$s is an email address, %3$s is a fixer station or list of roles */
				$format = __( '%1$s <%2$s> %3$s', 'reg-man-rc' );
				$result = sprintf( $format, $name, $email, $roles );
				
			} elseif ( ! empty( $email ) ) {

				/* Translators: %1$s is a volunteer's name, %2$s is an email address */
				$format = __( '%1$s <%2$s>', 'reg-man-rc' );
				$result = sprintf( $format, $name, $email );
				
			} else {
				
				$result = $name;
				
			} // endif
				
		} // endif

		return $result;

	} // function

	/**
	 * Get the url to edit this custom post.
	 * @return	string|NULL		The url for the page to edit this custom post if the user is authorized, otherwise NULL.
	 * @since v0.9.9
	 */
	public function get_edit_url() {
		$post_id = $this->get_post_id();
		if ( ! current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_SINGULAR, $post_id ) ) {
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
	 *  Register the Volunteer custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		self::register_post_type();

	} // function

	/**
	 *  Register the Volunteer custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	private static function register_post_type() {

		$labels = array(
				'name'					=> _x( 'Fixers & Volunteers', 'Volunteer post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Fixer / Volunteer', 'Volunteer post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Fixer / Volunteer' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Fixer / Volunteer', 'reg-man-rc'),
				'new_item'				=> __( 'New Fixer / Volunteer', 'reg-man-rc'),
				'all_items'				=> __( 'Fixers & Volunteers', 'reg-man-rc'), // This is the menu item title
				'view_item'				=> __( 'View Fixer / Volunteer', 'reg-man-rc'),
				'search_items'			=> __( 'Search Fixers / Volunteers (by public name only)', 'reg-man-rc'),
				'not_found'				=> __( 'Nothing found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'Nothing found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Fixers & Volunteers', 'reg-man-rc' )
		);

		$icon = 'dashicons-admin-users';
		$capability_singular = User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Fixers & Volunteers', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus (Appearance > Menus)?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> Admin_Menu_Page::get_menu_position(), // Menu order position
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				// Note that page-attributes allows ordering of the posts
				'supports'				=> array( 'title', 'author' ),
				'taxonomies'			=> array(
												Fixer_Station::TAXONOMY_NAME,
												Volunteer_Role::TAXONOMY_NAME,
											 ),
				'has_archive'			=> TRUE, // is there an archive page?
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