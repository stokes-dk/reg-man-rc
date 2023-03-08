<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;

/**
 * Represents a fixer or volunteer who has registered for an event.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Registration implements Volunteer_Registration_Descriptor {

	const POST_TYPE = 'reg-man-rc-vol-reg';

	const EVENT_META_KEY					= self::POST_TYPE . '-event';
	const VOLUNTEER_META_KEY				= self::POST_TYPE . '-volunteer';
	const IS_APPRENTICE_META_KEY			= self::POST_TYPE . '-is-apprentice';
	const ATTENDANCE_META_KEY				= self::POST_TYPE . '-attendance';

	private $post;
	private $post_id;
	private $event;
	private $volunteer;
	private $comments; // The volunteer-provided comments during registration, e.g. "I could use a ride to this event"
	private $volunteer_roles_array; // Roles the volunteer will perform at the event
	private $fixer_station; // The fixer station the volunteer will work at the event
	private $is_apprentice; // TRUE if the volunteer has asked to act as an apprentice fixer for the event
	private $attendance; // A flag to indicate whether the fixer or volunteer attended the event they were registered for

	/**
	 * Instantiate and return a new instance of this class using the specified post data
	 *
	 * @param	\WP_Post	$post	The post data for the new calendar
	 * @return	Volunteer_Registration
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post so I can't process it
		} else {
			$result = new self();
			$result->post				= $post;
			$result->post_id			= $post->ID;
//			$result->name				= $post->post_title; // Should we use post_title
			$result->comments			= $post->post_content;
		} // endif
		return $result;
	} // function

	/**
	 * Create a new item
	 *
	 * @param	Volunteer			$volunteer		The volunteer registering
	 * @param	string				$event			The event being that the volunteer will attend
	 * @param	Volunteer_Role[]	$roles			The roles that the volunteer will perform at the event, may be empty
	 * @param	Fixer_Station|NULL	$fixer_station	The fixer station the volunteer will work, or NULL if the volunteer is not a fixer
	 * @param	boolean				$is_apprentice	(optional) TRUE if the volunteer wants to work as a fixer apprentice, FALSE otherwise
	 * @return	Volunteer_Registration|NULL
	 */
	public static function create_new_registration( $volunteer, $event, $roles, $fixer_station, $is_apprentice = FALSE ) {

		$args = array(
//				'post_title'	=> ???, // Should we use post_title?
				'post_status'	=> 'publish',
//				'post_content'	=> $comments, // Should we allow comments?
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( __( 'Unable to create new volunteer registration', 'reg-man-rc' ), $post_id );
			$result = NULL;
		} else {
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			if ( ! empty( $result ) ) {
				$result->set_volunteer( $volunteer );
				$result->set_event( $event );
				$result->set_volunteer_roles_array( $roles );
				$result->set_fixer_station( $fixer_station );
				$result->set_is_fixer_apprentice( $is_apprentice );
			} // endif
		} // endif

		return $result;

	} // function

	/**
	 * Create a new item
	 *
	 * @param	Volunteer_Registration	$volunteer_registration		The volunteer registration being deleted
	 * @return	boolean		TRUE if the delete was successful, FALSE otherwise
	 */
	public static function delete_volunteer_registration( $volunteer_registration ) {
		$post_id = $volunteer_registration->get_post_id();
		$force_delete = TRUE; // Bypass the trash
		$result = wp_delete_post( $post_id, $force_delete );
		if ( is_wp_error( $result ) || empty( $result ) )  {
			/* Translators: %1$s is a post ID */
			$format = __( 'Failed to delete volunteer registration record with post ID: %1$s' );
			$msg = sprintf( $format, $post_id );
			if ( is_wp_error( $result ) ) {
				Error_Log::log_wp_error( $msg, $result );
			} else {
				Error_Log::log_msg( $msg );
			} // endif
			$result = FALSE;
		} else {
			$result = TRUE;
		} // endif
		return $result;
	} // function



	/**
	 * Get all registrations visible in the current context
	 *
	 * This method will return an array of instances of this class describing all registrations defined under this plugin.
	 * If the frontend website is being rendered then this will include only public instances,
	 *  if it's the backend admin interface then this will also include otherwise hidden instances like private and draft.
	 *
	 * @return	Volunteer_Registration[]
	 */
	public static function get_all_registrations( ) {
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
			$instance = self::instantiate_from_post( $post );
			if ( $instance !== NULL ) {
				$result[] = $instance;
			} // endif
		} // endfor
		return $result;
	} // function


	/**
	 * Get all volunteer registrations (fixers and non-fixers) for the specified events.
	 *
	 * This method will return an array of instances of this class describing all volunteer registrations
	 *
	 * @param	string[]	$event_keys_array	An array of keys for the events whose fixer registrations are to be returned
	 *   OR NULL to return all known fixer registrations
	 * @return	Volunteer_Registration[]
	 */
	public static function get_all_registrations_for_event_keys( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0 ) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
			);

			if ( is_array( $event_keys_array ) ) {
				$args[ 'meta_key' ]		= self::EVENT_META_KEY;
				$args[ 'meta_query' ]	= array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
					);
			} // endif

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function

	/**
	 * Get the volunteer registrations for the specified volunteer.
	 *
	 * This method will return an array of instances of this class describing all volunteer registrations
	 *
	 * @param	Volunteer	$volunteer	A volunteer whose registrations are to be returned
	 * @return	Volunteer_Registration[]
	 */
	public static function get_registrations_for_volunteer( $volunteer ) {
		if ( empty( $volunteer ) || ( ! $volunteer instanceof Volunteer ) ) {
			$result = array(); // The argument is not a valid volunteer so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
			);

			$args[ 'meta_key' ]		= self::VOLUNTEER_META_KEY;
			$args[ 'meta_query' ]	= array(
						array(
								'key'		=> self::VOLUNTEER_META_KEY,
								'value'		=> $volunteer->get_id(),
								'compare'	=> '=',
						)
				);

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function

	/**
	 * Get the volunteer registration (if one exists) for the specified volunteer and the specified event.
	 *
	 * @param	Volunteer	$volunteer	The volunteer whose registration is to be returned if found
	 * @param	string		$event_key	The event
	 * @return	Volunteer_Registration|NULL	The volunteer registration if one exists, otherwise NULL
	 */
	public static function get_registration_for_volunteer_and_event( $volunteer, $event_key ) {
		if ( empty( $event_key ) ) {
			$result = NULL;
		} else {
			$reg_array = self::get_registrations_for_volunteer_and_event_keys_array( $volunteer, array( $event_key ) );
			$result = isset( $reg_array[ 0 ] ) ? $reg_array[ 0 ] : NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Get the volunteer registrations for the specified volunteer and the specified array of events.
	 *
	 * This method will return an array of instances of this class describing all volunteer registrations
	 *
	 * @param	Volunteer	$volunteer			A volunteer whose registrations are to be returned
	 * @param	string[]	$event_keys_array	An array of event keys
	 * @return	Volunteer_Registration[]
	 */
	public static function get_registrations_for_volunteer_and_event_keys_array( $volunteer, $event_keys_array ) {
		if ( empty( $volunteer ) || ( ! $volunteer instanceof Volunteer ) ) {
			$result = array(); // The argument is not a valid volunteer so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
			);

			$args[ 'meta_query' ]	= array(
						'relation'	=> 'AND',
							array(
									'key'		=> self::VOLUNTEER_META_KEY,
									'value'		=> $volunteer->get_id(),
									'compare'	=> '=',
							),
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
			);

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function

	/**
	 * Get fixer registrations.
	 *
	 * Note that this may include fixers who also registered for a volunteer role like "Setup & Cleanup".
	 *
	 * @param	string[]	$event_keys_array	An array of keys for the events whose fixer registrations are to be returned
	 *   OR NULL to return all known fixer registrations
	 * @return	Volunteer_Registration[]	A list of registrations with a fixer role for the specified events.
	 */
	public static function get_fixer_registrations_for_event_keys( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0 ) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'tax_query' => array(
							array(
									'taxonomy'	=> Fixer_Station::TAXONOMY_NAME,
									'operator'	=> 'EXISTS',
							)
					)
			);

			if ( is_array( $event_keys_array ) ) {
				$args[ 'meta_key' ]		= self::EVENT_META_KEY;
				$args[ 'meta_query' ]	= array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
					);
			} // endif

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function

	/**
	 * Get registrations for volunteers who registered for a volunteer role (i.e. non-fixer role like "Registration")
	 *  for any of the events specified in the event keys array.
	 *
	 * Note that this may include fixers who also registered for a volunteer role like "Setup & Cleanup".
	 *
	 * This method will return an array of instances of this class describing registrations with volunteer roles
	 *
	 * @param	string[]	$event_keys_array	An array of keys for the events whose registrations are to be returned
	 *   OR NULL to return all volunteer role registrations
	 * @return	Volunteer_Registration[]
	 */
/*
	public static function get_volunteer_role_registrations_for_event_keys( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0 ) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'tax_query' => array(
							array(
									'taxonomy'	=> Volunteer_Role::TAXONOMY_NAME,
									'operator'	=> 'EXISTS',
							)
					)
			);

			if ( is_array( $event_keys_array ) ) {
				$args[ 'meta_key' ]		= self::EVENT_META_KEY;
				$args[ 'meta_query' ]	= array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
					);
			} // endif

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function
*/
	/**
	 * Get volunteer registration descriptors for volunteer who registered for a non-fixer role like "Registration".
	 *
	 * Note that some volunteers may have a fixer role AND a non-fixer role like "Setup & Cleanup".  Those registrations
	 *   are INCLUDED in the result of this method.
	 *
	 * Also note that some volunteers select no role at all intending to show up and perform a task assigned at the event.
	 * Those registrations are also included in the result of this method.
	 *
	 * @param	string[]	$event_keys_array	An array of keys for the events whose registrations are to be returned
	 *   OR NULL to return all volunteer role registrations
	 * @return	Volunteer_Registration[]		An array of Volunteer_Registration_Descriptor objects describing
	 *  all non-fixer registrations known to the system and limited to the events described by the filter.
	 */
	public static function get_non_fixer_registrations_for_event_keys( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0 ) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			$result = array();
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'tax_query' => array(
							array(	'relation'	=>	'OR',
									array(
											'taxonomy'	=> Volunteer_Role::TAXONOMY_NAME,
											'operator'	=> 'EXISTS',
									),
									array(	'relation'	=>	'AND',
											array(
													'taxonomy'	=> Volunteer_Role::TAXONOMY_NAME,
													'operator'	=> 'NOT EXISTS',
											),
											array(
													'taxonomy'	=> Fixer_Station::TAXONOMY_NAME,
													'operator'	=> 'NOT EXISTS',
											)
									)
							)
					)
			);

			if ( is_array( $event_keys_array ) ) {
				$args[ 'meta_key' ]		= self::EVENT_META_KEY;
				$args[ 'meta_query' ]	= array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
				);
			} // endif

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

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
	 * Get all registrations for a single event.
	 *
	 * This method will return an array of instances of this class describing all registrations for the specified event
	 *
	 * @param	string|Event_Key	$event_key	The key for the event whose fixer and volunteer registrations are to be returned
	 * @return	Volunteer_Registration[]
	 */
	public static function get_all_registrations_for_event( $event_key ) {
		$result = array();
		if ( is_string( $event_key ) ) {
			$key_string = $event_key; // the argument is the string I want
		} else {
			$key_string = ( $event_key instanceof Event_Key ) ? $event_key->get_as_string() : NULL;
		} // endif
		if ( !empty ( $key_string ) ) {
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'  => self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'meta_key'   => self::EVENT_META_KEY,
					'meta_query' => array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $key_string,
									'compare'	=> '=',
							)
					)
			);
			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$reg = self::instantiate_from_post( $post );
				if ( $reg !== NULL ) {
					$result[] = $reg;
				} // endif
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;

	} // function

	/**
	 * Get a single registration using its ID (i.e. post ID)
	 *
	 * This method will return a single instance of this class describing the registration specified by the ID.
	 * If the registration is not found, this method will return NULL
	 *
	 * @param	int|string	$registration_id	The ID of the registration to be returned
	 * @return	Volunteer_Registration
	 */
	public static function get_registration_by_id( $registration_id ) {
		$post = get_post( $registration_id );
		if ( ( $post !== NULL ) && ( $post instanceof \WP_Post ) && ( $post->post_type == self::POST_TYPE ) ) {
			$result = self::instantiate_from_post( $post );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Get the post associated with this registration.
	 * @return	\WP_Post		The post for this registration
	 * @since	v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this registration.
	 * @return	int		The post ID for this registration
	 * @since	v0.1.0
	 */
	private function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the ID of this registration.  The ID is the post ID.
	 * @return	int		The ID for the registration
	 * @since	v0.1.0
	 */
	public function get_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the event for this registration.
	 * @return	Event		The Event object for this registration
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			$key = get_post_meta( $this->get_post_id(), self::EVENT_META_KEY, $single = TRUE );
			if ( ( $key !== FALSE ) && ( $key !== NULL ) && ( $key != '' ) ) {
				$this->event = Event::get_event_by_key( $key );
			} // endif
		} // endif
		return $this->event;
	} // function

	/**
	 * Assign the event for this registration
	 * @param	Event	$event	The event to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_event( $event ) {
		if ( empty( $event ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::EVENT_META_KEY );
		} else {
			$event_key = $event->get_key();
			update_post_meta( $this->get_post_id(), self::EVENT_META_KEY, $event_key );
		} // endif
		unset( $this->event ); // allow it to be re-acquired
	} // function

	/**
	 * Get the volunteer for this registration.
	 * @return	Volunteer		The Volunteer object for this registration
	 * @since	v0.1.0
	 */
	public function get_volunteer() {
		if ( ! isset( $this->volunteer ) ) {
			$id = get_post_meta( $this->get_post_id(), self::VOLUNTEER_META_KEY, $single = TRUE );
			if ( ( $id !== FALSE ) && ( $id !== NULL ) && ( $id != '' ) ) {
				$this->volunteer = Volunteer::get_volunteer_by_id( $id );
			} // endif
		} // endif
		return $this->volunteer;
	} // function

	/**
	 * Assign the volunteer for this registration
	 * @param	Volunteer	$volunteer	The volunteer to be assigned to this registration
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_volunteer( $volunteer ) {
		if ( empty( $volunteer ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::VOLUNTEER_META_KEY );
		} else {
			$id = $volunteer->get_id();
			update_post_meta( $this->get_post_id(), self::VOLUNTEER_META_KEY, $id );
		} // endif
		unset( $this->volunteer ); // allow it to be re-acquired
	} // function

	/**
	 * Get the comments supplied by the fixer or volunteer for this registration.
	 * 	The comments are the post content.
	 * @return	string		Any comments supplied by the volunteer during registration
	 * @since	v0.1.0
	 */
	public function get_volunteer_registration_comments() {
		return $this->comments;
	} // function

	/**
	 * Get the array of roles the volunteer is offering to perform at the event.
	 * @return	Volunteer_Role[]	An array of Volunteer_Role objects describing the roles this volunteer is offering to perform.
	 * @since	v0.1.0
	 */
	public function get_volunteer_roles_array() {
		if ( ! isset( $this->volunteer_roles_array ) ) {
			$this->volunteer_roles_array = Volunteer_Role::get_volunteer_roles_for_post( $this->get_post_id() );
		} // endif
		return $this->volunteer_roles_array;
	} // function

	/**
	 * Set the array of roles the volunteer is offering to perform at the event.
	 * @param	Volunteer_Role[]	$volunteer_roles_array		The new array of roles for this volunteer at this event.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_volunteer_roles_array( $volunteer_roles_array ) {
		Volunteer_Role::set_volunteer_roles_for_post( $this->get_id(), $volunteer_roles_array );
		unset( $this->volunteer_roles_array ); // allow it to be re-acquired
	} // function


	/**
	 * Get the fixer station for this volunteer at this event or NULL if no fixer station is assigned
	 *
	 * @return	Fixer_Station|NULL	The fixer station assigned to the volunteer for this event
	 * or NULL if this volunteer will not be fixing items at the event
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_station() {
		if ( ! isset( $this->fixer_station ) ) {
			$station_array = Fixer_Station::get_fixer_stations_for_post( $this->get_post_id() );
			$this->fixer_station = ( is_array( $station_array ) && isset( $station_array[ 0 ] ) ) ? $station_array[ 0 ] : NULL;
		} // endif
		return $this->fixer_station;
	} // function

	/**
	 * Assign the fixer station for this volunteer registration
	 *
	 * @param	Fixer_Station	$fixer_station	The fixer station being assigned to this volunteer registration
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public function set_fixer_station( $fixer_station ) {
		Fixer_Station::set_fixer_stations_for_post( $this->get_id(), array( $fixer_station ) );
		unset( $this->fixer_station ); // reset my internal var so it can be re-acquired
	} // function


	/**
	 * Get the volunteer's public name.
	 * To protect the volunteer's privacy this name is the one shown in public and should be something like
	 * the volunteer's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_public_name() {
		$volunteer = $this->get_volunteer();
		return isset( $volunteer ) ? $volunteer->get_public_name() : NULL;
	} // function

	/**
	 * Get the volunteer's full name as a single string.
	 * To protect the volunteer's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_full_name() {
		$volunteer = $this->get_volunteer();
		return isset( $volunteer ) ? $volunteer->get_full_name() : NULL;
	} // function

	/**
	 * Get the volunteer's email, if supplied.
	 * To protect the volunteer's privacy their email is never shown in public.
	 * The email is used only to identify returning volunteers and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The volunteer's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_volunteer_email() {
		$volunteer = $this->get_volunteer();
		return isset( $volunteer ) ? $volunteer->get_email() : NULL;
	} // function

	/**
	 * Get the key for the event that the volunteer is registered for.
	 * @return	string|NULL		The key for the event for this volunteer registration
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		$event = $this->get_event();
		return isset( $event ) ? $event->get_key() : NULL;
	} // function

	/**
	 * Get the name of the volunteer's preferred fixer station.
	 * Note that this is stored with the volunteer and not this registration object.
	 * This method is implemented here as part of the Volunteer_Registration_Descriptor interface.
	 *
	 * @return	string	The name of the fixer station the volunteer has set as her preferred station
	 * 	or NULL if this volunteer has no preferred fixer station, i.e. they are not a fixer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_fixer_station_name() {
		$volunteer = $this->get_volunteer();
		$station = isset( $volunteer ) ? $volunteer->get_preferred_fixer_station() : NULL;
		return isset( $station ) ? $station->get_name() : NULL;
	} // function

	/**
	 * Get the name of the fixer station assigned to the volunteer for this event.
	 * This method is implemented here as part of the Volunteer_Registration_Descriptor interface.
	 *
	 * @return	string	The name of the fixer station for this registration
	 * 	or NULL if no fixer station has been assigned
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_fixer_station_name() {
		$station = $this->get_fixer_station();
		return isset( $station ) ? $station->get_name() : NULL;
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
	 * Get the array of names of volunteer roles the volunteer prefers to perform at events.
	 * Note that this is stored with the Volunteer and not this object.
	 * This method is implemented here as part of the Volunteer_Registration_Descriptor interface.
	 *
	 * @return	string	The array of strings representing the preferred volunteer roles for this event
	 *	or NULL if no volunteer roles were requested by the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_volunteer_role_names_array() {
		$result = array();
		$volunteer = $this->get_volunteer();
		$roles_array = isset( $volunteer ) ? $volunteer->get_preferred_roles() : array();
		foreach ( $roles_array as $role ) {
			$result[] = $role->get_name();
		} // endfor
		return $result;
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
		$result = array();
		$roles = $this->get_volunteer_roles_array();
		if ( isset( $roles ) && is_array( $roles ) ) {
			foreach( $roles as $role ) {
				$result[] = $role->get_name();
			} // endfor
		} // endif
		return $result;
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
		if ( ! isset( $this->attendance ) ) {
			$flag = get_post_meta( $this->get_post_id(), self::ATTENDANCE_META_KEY, $single = TRUE );
			if ( ( $flag !== FALSE ) && ( $flag !== NULL ) && ( $flag != '' ) ) {
				$this->attendance = boolval( $flag );
			} // endif
		} // endif
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
		return __( 'registered', 'reg-man-rc' );
	} // function

	/**
	 *  Register the custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {
		$labels = array(
				'name'					=> _x( 'Fixer / Volunteer Registrations', 'Volunteer Registration post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Fixer / Volunteer Registration', 'Volunteer Registration post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Fixer / Volunteer Registration' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Fixer / Volunteer Registration', 'reg-man-rc'),
				'new_item'				=> __( 'New Fixer / Volunteer Registration', 'reg-man-rc'),
				'all_items'				=> __( 'Fixer / Volunteer Registrations', 'reg-man-rc'),
				'view_item'				=> __( 'View Fixer / Volunteer Registration', 'reg-man-rc'),
				'search_items'			=> __( 'Search Fixer / Volunteer Registrations', 'reg-man-rc'),
				'not_found'				=> __( 'Nothing found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'Nothing found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Fixer / Volunteer Registrations', 'reg-man-rc')
		);

		$icon = 'dashicons-thumbs-up';
		$capability_singular = User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Fixer / Volunteer Registration', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> array( /* 'title', */ 'editor' ),
				'taxonomies'			=> array(
												Fixer_Station::TAXONOMY_NAME,
												Volunteer_Role::TAXONOMY_NAME,
											 ),
				'has_archive'			=> FALSE, // is there an archive page?
				// Rewrite determines how the public page url will look.
				'rewrite'				=> FALSE,
//				'capabilities'			=> self::get_all_capabilities(),
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities (plus Admin)
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		register_post_type( self::POST_TYPE, $args );

	} // function

	public static function handle_plugin_uninstall() {
		// When the plugin is uninstalled, remove all posts of my type
		$stati = get_post_stati(); // I need all post statuses
		$posts = get_posts(array(
				'post_type'			=> self::POST_TYPE,
				'post_status'		=> $stati,
				'posts_per_page'	=> -1 // get all
		));
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID );
		} // endfor
	} // function

} // class