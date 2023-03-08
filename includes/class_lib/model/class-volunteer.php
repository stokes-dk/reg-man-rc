<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor_Factory;

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

	const POST_TYPE						= 'reg-man-rc-volunteer';
	const DEFAULT_PAGE_SLUG				= 'rc-volunteers';

	const IS_APPRENTICE_META_KEY			= self::POST_TYPE . '-is-apprentice';
	// Some volunteers do not supply an email address (and so can't enter the volunteer area) but allow another volunteer
	//  to act as a proxy and perform event registration on their behalf.
	// The ID of the proxy volunteer is stored in metadata.  I.e. the ID of the volunteer who acts as my proxy.
	const MY_PROXY_VOLUNTEER_ID_META_KEY	= self::POST_TYPE . '-my-proxy-id';

	// An access key can be used to identify a volunteer for things like getting their events feed
	// The key is generated and stored in postmeta so that we can use it to retrieve a volunteer from the db
	const ACCESS_KEY_META_KEY				= self::POST_TYPE . '-access-key';

	// We use a side table to store personal info about the volunteer include full name and email
	const VOLUNTEER_SIDE_TABLE_NAME		= 'reg_man_rc_volunteer';

	const VOLUNTEER_EMAIL_COOKIE_NAME	= 'reg-man-rc-volunteer-email';

	private static $CURRENT_VOLUNTEER_EMAIL; // The email for the current volunteer
	private static $CURRENT_VOLUNTEER; // The volunteer object for the current volunteer

	private $post;
	private $post_id;
	private $public_name; // The name used for the volunteer when shown in public places, e.g. "Dave S"
	private $full_name; // The volunteer's full name, e.g. "Dave Stokes"
	private $email; // Optional, the volunteer's email address
	private $access_key; // An identifier that can be used to access this volunteer, e.g. to get their events feed
	private $has_public_profile; // A flag indicating whether this volunteer has a public profile page
	private $preferred_roles; // An array of Volunteer_Role objects indicating which roles this volunteer prefers
	private $preferred_fixer_station; // A Fixer_Station object indicating which station this fixer prefers
	private $is_apprentice; // A flag set to TRUE if this volunteer prefers to work as an apprentice
	private $registration_array; // An array of registration records for this volunteer
	private $my_proxy_volunteer_id; // The ID of another volunteer who acts as a proxy for registering this volunteer
	private $proxy_for_array; // The array of other volunteers this volunteer acts as a proxy for

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
			// Note that the side table contains full name and email so it will only be accessed
			//  when we're rendering the admin interface and the user can see private posts
			// TODO: I should be able to view MY OWN full name and email address if I can't read private
			$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
			$is_join_allowed = is_admin() && current_user_can( $capability );
			if ( ! isset( $post->volunteer_table_joined ) && $is_join_allowed ) {
				self::join_post_to_volunteer_table( $post );
			} // endif
			$result->full_name = isset( $post->volunteer_full_name ) ? $post->volunteer_full_name : NULL;
			$result->email = isset( $post->volunteer_email ) ? $post->volunteer_email : NULL;
		} // endif
		return $result;
	} // function

	/**
	 *
	 * @param \WP_Post $post
	 */
	private static function join_post_to_volunteer_table( $post ) {
		global $wpdb;
		$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		if ( is_admin() && current_user_can( $capability ) ) {
			$volunteer_table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
			$alias = 'reg_man_rc_volunteer';
			$fields = 	'1 as volunteer_table_joined, ' .
						"$alias.full_name as volunteer_full_name, " .
						"$alias.email as volunteer_email";

			$query = "SELECT $fields FROM $volunteer_table $alias WHERE {$alias}.post_id = %s";

			$stmt = $wpdb->prepare( $query, $post->ID );

			$result = $wpdb->get_row( $stmt );

			$post->volunteer_table_joined = 1;
			$post->volunteer_full_name = isset( $result->volunteer_full_name ) ? $result->volunteer_full_name : '';
			$post->volunteer_email = isset( $result->volunteer_email ) ? $result->volunteer_email : '';
		} // endif
	} // function

	/**
	 * Get the volunteer who made the current request.
	 * The current volunteer is stored in a cookie with the request.
	 * If the cookie is not present or the volunteer cannot be found based on the cookie value then NULL is returned.
	 * @return	Volunteer|NULL	The volunteer making the current request or NULL if no volunteer is identified in the request.
	 */
	public static function get_current_volunteer() {
		if ( ! isset( self::$CURRENT_VOLUNTEER ) ) {
			// Get the email from the cookie
			$volunteer_email = self::get_volunteer_email_cookie();
			if ( ! empty( $volunteer_email ) ) {
				// Get the volunteer from the email
				$is_login_required = self::get_is_login_required_for_email( $volunteer_email );
				if ( $is_login_required ) {
					// If this volunteer requires a login then make sure they are logged in
					if ( is_user_logged_in() ) {
						$user = wp_get_current_user();
						$user_email = $user->user_email;
						if ( $user_email == $volunteer_email ) {
							self::$CURRENT_VOLUNTEER = self::get_volunteer_by_email( $volunteer_email );
						} // endif
					} // endif
				} else {
					// If no login is required then just return the volunteer based on their email
					self::$CURRENT_VOLUNTEER = self::get_volunteer_by_email( $volunteer_email );
				} // endif
			} // endif
		} // endif
		return self::$CURRENT_VOLUNTEER;
	} // function

	/**
	 * Get the email address for the volunteer who made the current request
	 * @return	string		The email address for the volunteer viewing this page
	 */
	public static function get_volunteer_email_cookie() {
		if ( ! isset( self::$CURRENT_VOLUNTEER_EMAIL ) ) {
			self::$CURRENT_VOLUNTEER_EMAIL = Cookie::get_cookie( self::VOLUNTEER_EMAIL_COOKIE_NAME );
			if ( empty( self::$CURRENT_VOLUNTEER_EMAIL ) ) {
				// If there is no cookie stored then check if there is a user logged in
				$user = wp_get_current_user();
				if ( isset( $user ) && ( $user->ID !== 0 ) && ! empty( $user->user_email ) ) {
					self::$CURRENT_VOLUNTEER_EMAIL = $user->user_email;
					self::set_volunteer_email_cookie( $user->user_email );
				} // endif
			} // endif
		} // endif
		return self::$CURRENT_VOLUNTEER_EMAIL;
	} // function

	public static function set_volunteer_email_cookie( $email, $is_remember_me = FALSE ) {
		if ( empty( $email ) ) {
			// This means we need to remove the current volunteer
			$result = Cookie::remove_cookie( self::VOLUNTEER_EMAIL_COOKIE_NAME );
		} else {
			$name = self::VOLUNTEER_EMAIL_COOKIE_NAME;
			$value = $email;
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
	 * Get a volunteer record by their email address
	 * @param	string		$email			The email address of the volunteer to be retrieved
	 * @param	boolean		$is_store_email TRUE if the email address should be stored in the resulting object.
	 *  The result does not contain private information like the email address unless the current logged-in WP user has authority to view it.
	 *  However, under certain circumstances, like logging in to the volunteer area, the user may have already
	 *  supplied the email address and it is convenient to just store it in the volunteer object.
	 *  T
	 * @return	Volunteer|NULL	The volunteer with the specified email address, or NULL if the record is not found
	 * @since 	v0.1.0
	 */
	public static function get_volunteer_by_email( $email ) {
		global $wpdb;
		$email = trim( $email );
		if ( empty( $email ) ) {
			$result = NULL;
		} else {
			// I will look in the side table for the specified email then use the post id to create the Volunteer object
			$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
			$query = "SELECT post_id FROM $table WHERE email=%s AND email IS NOT NULL AND email != ''";
//			Error_Log::var_dump( $query, $email );
			$stmt = $wpdb->prepare( $query, $email );
			$data = $wpdb->get_row( $stmt, OBJECT );
			$post_id = isset( $data ) ? $data->post_id : NULL;
			$result = self::get_volunteer_by_id( $post_id );
		} // endif
		return $result;
	} // function


	/**
	 * Get the volunteer whose access key is the one specified.
	 *
	 * @param	int|string	$access_key		The access key of the volunteer who acts as proxy
	 * @return	Volunteer	The volunteers whose access key was specified
	 */
	private static function get_volunteer_by_access_key( $access_key ) {
		$result = array();
		$statuses = self::get_visible_statuses();
		if ( ! in_array( 'private', $statuses ) ) {
			// A volunteer may use their access key to access their own record which may be private and not public
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
		$statuses = self::get_visible_statuses();
		if ( ! in_array( 'private', $statuses ) ) {
			// A volunteer may act as proxy for other volunteers whose record is marked as private
			// I still need to be able to access those records, but just for the case of selecting proxies
			$statuses[] = 'private';
		} // function
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
		$posts = $query->posts;
		foreach ( $posts as $post ) {
			$instance = self::instantiate_from_post( $post );
			if ( $instance !== NULL ) {
				$result[] = $instance;
			} // endif
		} // endfor
		wp_reset_postdata(); // Required after using WP_Query()
		return $result;

	} // function


	/**
	 * Get an array of post statuses that indicates what is visible to the current user.
	 * @param boolean	$is_look_in_trash	A flag set to TRUE if posts in trash should be visible.
	 * @return string[]
	 */
	private static function get_visible_statuses( $is_look_in_trash = FALSE ) {
		$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;
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
	 * Create a new volunteer
	 *
	 * @param	string			$public_name			The volunteer's preferred public name, e.g. first name and last initial
	 * @param	string			$full_name				The volunteer's full name
	 * @param	string			$email					The volunteer's email address
	 * @return	Volunteer|null
	 */
	public static function create_new_volunteer( $public_name, $full_name = NULL, $email = NULL ) {

		$args = array(
				'post_title'	=> $public_name,
				'post_status'	=> 'private',
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( __( 'Unable to create new volunteer', 'reg-man-rc' ), $post_id );
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
	 * Update the volunteer record with the specified ID
	 * @param	string|int	$id					The ID of the volunteer record to be updated
	 * @param	string		$full_name			The volunteer's full name, e.g. Dave Stokes
	 * @param	string		$email				The volunteer's email address or NULL if not known
	 * @return	boolean		TRUE if the update was successful, FALSE otherwise
	 * @since	v0.1.0
	 */
 	public static function update_volunteer( $id,  $full_name, $email ) {
		global $wpdb;
		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
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
	 * Delete a volunteer record
	 * @param	string|int	$volunteer_id		The ID of the record to be deleted
	 * @return	boolean		TRUE if the delete was successful, FALSE if not
	 * @since	v0.1.0
	 */
/* FIXME = Do I need this???
	public static function delete_volunteer( $volunteer_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
		$count = $wpdb->delete( $table, array( 'id' => $volunteer_id ) , array( '%s' ) );
		$result = ( $count !== 0 ) ? TRUE : FALSE;
		return $result;
	} // function
*/

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
	 * Get the volunteer's public name
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name() {
		return $this->public_name;
	} // function

	/**
	 * Get the volunteer's name as a single string.
	 * To protect the volunteer's privacy their full name is returned only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		return ( is_admin() && current_user_can( $capability ) ) ? $this->full_name : $this->get_public_name();
	} // function

	/**
	 * Get the volunteer's email, if supplied
	 * @return	string|NULL		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {
		$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		return ( is_admin() && current_user_can( $capability ) ) ? $this->email : $this->get_partially_obscured_email();
	} // function

	/**
	 * Get the access key for the volunteer
	 * @return	string		The access key for this volunteer.
	 * The access key uniquely identifies a volunteer in a way that is hard to guess and does not openly expose
	 *  the volunteer's email address.
	 * @since	v0.1.0
	 */
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


	/**
	 * Get a flag indicating whether the volunteer with the specified email address requires a login with password
	 *  to access the volunteer area.
	 * The result is TRUE when there is a WP user with the same email address otherwise FALSE.
	 * Note that this is not an instance method because the volunteer's email is not always available inside the instance,
	 *  for example when there is not logged in user or the user does not have sufficient admin authority.
	 * @param	string	$email	The email address of the volunteer to be checked
	 * @return	boolean			A flag set to TRUE when the volunteer must provide a password to acces the volunteer area, FALSE otherwise
	 * @since	v0.1.0
	 */
	public static function get_is_login_required_for_email( $email ) {
		$wp_user = ! empty( $email ) ? get_user_by( 'email', $email ) : FALSE;
		$result = ! empty( $wp_user );
		return $result;
	} // function

	/**
	 * Set the volunteer's full name and email address, if supplied
	 * @param	string|int	$id			The post ID for the volunteer record
	 * @param	string		$full_name	The volunteer's full_name if it is known, NULL otherwise
	 * @param	string		$email		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function set_personal_info( $full_name, $email ) {

		global $wpdb;
		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;

		$id = $this->get_post_id();

		$data = array(
				'post_id'	=> $id,
				'full_name'	=> $full_name,
				'email'		=> $email
		);
		$wpdb->replace( $table, $data ); // Note that replace() will insert the record if it does not exist

	} // function

	/**
	 * Delete the volunteer's personal information from the side table
	 * @since	v0.1.0
	 */
	public function delete_personal_info( ) {

		global $wpdb;
		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
		$id = $this->get_post_id();
		$args = array( 'post_id' => $id );

		$wpdb->delete( $table, $args );

	} // function

	private function get_partially_obscured_email() {
		$email = $this->email;
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
		    $len  = ceil( $name_len / 2 ) - 1;
		    $masked_name = substr( $name, 0, $len ) . str_repeat( '*', $name_len - $len - 1 ) . substr( $name, $name_len - 1, 1 );
		} // endif

		$result =  $masked_name . $domain;

		return $result;
	} // function

	/**
	 * Get a flag indicating whether this Volunteer has a public profile page
	 * @return	boolean		A flag set as TRUE if this Volunteer has a public profile page and FALSE otherwise
	 * @since	v0.1.0
	 */
	public function get_has_public_profile() {
		if ( ! isset( $this->has_public_profile ) ) {
			$post = $this->get_post();
			$this->has_public_profile = ( $post->post_status == 'publish' ) ;
		} // endif
		return $this->has_public_profile;
	} // function

	/**
	 * Get all registration descriptors for this volunteer
	 * @return Volunteer_Registration_Descriptor[]
	 */
	public function get_registration_descriptors() {
		$reg_array = Volunteer_Registration_Descriptor_Factory::get_volunteer_registration_descriptors_for_volunteer( $this );
		return $reg_array;
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


	/**
	 * Get a descriptive label for the volunteer including their name and email.
	 * If we are rendering the admin interface for a user that can read private posts then this will include personal details,
	 *  otherwise it will be just the person's public name like, 'Dave S.'
	 * @return string	A volunteer label that quickly identifies a person to a human user, suitable for use as a select option
	 * @since v0.1.0
	 */
	public function get_label() {

		$capability = 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		if ( is_admin() && current_user_can( $capability ) ) {
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
				'search_items'			=> __( 'Search Fixers / Volunteers', 'reg-man-rc'),
				'not_found'				=> __( 'Nothing found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'Nothing found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Fixers & Volunteers', 'reg-man-rc' )
		);

		$icon = 'dashicons-groups';
		$capability_singular = User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Fixers & Volunteers', // Internal description, not visible externally
				'public'				=> TRUE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> TRUE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> TRUE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.  5 is below Posts
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				// Note that page-attributes allows ordering of the posts
				'supports'				=> array( 'title', 'editor', 'thumbnail' ),
				'taxonomies'			=> array(
												Fixer_Station::TAXONOMY_NAME,
												Volunteer_Role::TAXONOMY_NAME,
											 ),
				'has_archive'			=> TRUE, // is there an archive page?
				// Rewrite determines how the public page url will look.
				'rewrite'				=> array(
					'slug'			=> Settings::get_volunteers_slug(),
					'with_front'	=> FALSE,
				),
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

		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;

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
		$table = $wpdb->prefix . self::VOLUNTEER_SIDE_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	} // function

} // class