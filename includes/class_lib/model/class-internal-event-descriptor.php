<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_Descriptor_View;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;

/**
 * Describes a single event or a collection of recurring events defined internally by this plugin.
 *
 * An instance of this class contains the information related to a single event or a collection of recurring events
 * including its summary, description, start and end date/time, location and so on.
 *
 * For recurring events it will also contain a recurrence rule describing all the recurring instances of the event.
 *
 * @since v0.1.0
 *
 */
class Internal_Event_Descriptor implements Event_Descriptor {

	const POST_TYPE							= 'reg-man-rc-event';
	const EVENT_PROVIDER_ID					= 'rmrc';

	const UID_META_KEY						= self::POST_TYPE . '-uid';
	const STATUS_META_KEY					= self::POST_TYPE . '-status';
	const START_META_KEY					= self::POST_TYPE . '-start';
	const END_META_KEY						= self::POST_TYPE . '-end';
	const VENUE_META_KEY					= self::POST_TYPE . '-venue';
	const NON_REPAIR_EVENT_META_KEY			= self::POST_TYPE . '-is-non-repair-event';
	const VOLUNTEER_PRE_REG_NOTE_META_KEY	= self::POST_TYPE . '-volunteer-pre-reg-note';
	
	const RRULE_META_KEY					= self::POST_TYPE . '-rrule';
//	const EXDATES_META_KEY					= self::POST_TYPE . '-exdates';
//	const RDATES_META_KEY					= self::POST_TYPE . '-rdates';
	const CANCEL_DATES_META_KEY				= self::POST_TYPE . '-cancel-dates';
	
	const DATE_DB_FORMAT					= 'Y-m-d H:i:s'; // This is how we format dates to store in the database

	private static $UTC_TIMEZONE; // We use UTC timezone for all DateTime objects stored in the database
	
	private static $EVENT_EDITORS_ARRAY;

	private $uid;
	private $post;
	private $event_object_array; // An array of Event objects for this descriptor (may be recurring event)
	private $event_status_id;
	private $event_status;
	private $event_class;
	private $dtstart; // Date and time for the event's start as a string
	private $start_date_time; // DateTime object for the event's start date and time
	private $dtend; // Date and time for the event's start as a string
	private $end_date_time; // DateTime object for the event's end date and time
	private $venue;
	private $category_object_array; // An array of Event_Category objects
	private $categories; // An array of names of categories as required by Event_Descriptor (similar to VEVENT)
	private $is_non_repair_event; // A flag set to TRUE if items will not be repaired at this event
	private $fixer_stations; // An array of fixer stations assigned to this event
	private $volunteer_pre_reg_note; // Note from the organizer shown only in the volunteer area before registration
	private $url; // The url for this event
	private $rrule; // The text version of the recurrence rule provided upon instantiation
	private $recurrence_rule; // The object representation of the recurrence rule
	private $exdates; // exclusion set dates and times as a string
	private $exclusion_date_time_array; // an array of DateTime objects representing the exclusion dates for a recurring event
	private $rdates; // inclusion dates and times as a string
	private $inclusion_date_time_array; // an array of DateTime objects representing the dates to be added to a recurring event
	private $cancel_date_strings_array; // An array of recurring event dates marked as cancelled
	private $cancel_date_times_array; // An array of DateTime objects representing the cancelled dates for a recurring event
	private $map_marker_colour; // The colour for the marker used to mark this event on a map

	private $events_collection; // Used to determine stats like number of items
	private $total_item_stats_collection;
	private $total_visitor_stats_collection;
	private $total_volunteer_stats_collection;
	private $total_items_count;
	private $total_visitors_count;
	private $total_volunteers_count;
	
	/**
	 * Instantiate and return a new instance of this class using the specified post data
	 *
	 * @param	\WP_Post	$post	The post data for the new event
	 * @return	self
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post so I can't process it
		} else {
			$result = new self();
			$result->post = $post;
		} // endif
		return $result;
	} // function

	/**
	 * Create a new internal event descriptor
	 *
	 * @param	string				$event_summary		The summary (or title) of the event
	 * @param	\DateTime			$start_date_time	The event start date and time
	 * @param	\DateTime			$end_date_time		The event end date and time
	 * @param	Event_Category[]	$category_array		An array of categories for the event
	 * @return	Visitor|null
	 */
	public static function create_internal_event_descriptor( $event_summary, $start_date_time, $end_date_time, $category_array = NULL ) {

		$args = array(
				'post_title'	=> $event_summary,
				'post_status'	=> 'publish',
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			
			Error_Log::log_wp_error( __( 'Unable to create new inernal event descriptor', 'reg-man-rc' ), $post_id );
			$result = NULL;
			
		} else {
			
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			
			if ( ! empty( $result ) ) {

				$result->set_event_start_date_time( $start_date_time );
				$result->set_event_end_date_time( $end_date_time );
				if ( ! empty( $category_array ) ) {
					$result->set_event_category_array( $category_array );
				} // endif

			} // endif
			
		} // endif

		return $result;

	} // function
	
	/**
	 * Determine if the current user is allowed to duplicate the event descriptor for the specified post
	 * @param \WP_Post $post
	 */
	public static function current_user_can_duplicate( $event_descriptor_id ) {
		$create_cap = 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		$edit_source_cap = 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_SINGULAR;
		$result = ( current_user_can( $create_cap ) && current_user_can( $edit_source_cap, $event_descriptor_id ) );
		return $result;
	} // function
	
	/**
	 * Create a duplicate of the specified event descriptor
	 * @param	Internal_Event_Descriptor $source_event_descriptor
	 * @return	Internal_Event_Descriptor
	 */
	public static function create_duplicate_event_descriptor( $source_event_descriptor ) {
		$result = NULL;
		
		if ( ( $source_event_descriptor instanceof Internal_Event_Descriptor )
				&& self::current_user_can_duplicate( $source_event_descriptor->get_post_id() ) ) {
			
			$post = $source_event_descriptor->get_post();
			$post_id = $post->ID;
//			Error_Log::var_dump( $post_id, $post->post_title );

			// Insert the new post with the same values and draft status
			$args = array(
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
					'post_author'    => $post->post_author,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_name'      => $post->post_name,
					'post_parent'    => $post->post_parent,
					'post_password'  => $post->post_password,
					'post_status'    => 'draft',
					'post_title'     => $post->post_title,
					'post_type'      => $post->post_type,
					'to_ping'        => $post->to_ping,
					'menu_order'     => $post->menu_order
			);			
			
			$new_post_id = wp_insert_post( $args );
			
			// Copy the taxonomies over
			$taxonomies = get_object_taxonomies( get_post_type( $post ) ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
			} // endif
			
			// Copy the metadata over
			
			// We should NOT copy over the UID!!!
			$ignore_meta_key_array = array(
					'_wp_old_slug',
					Internal_Event_Descriptor::UID_META_KEY,
			);
			$post_meta = get_post_meta( $post_id );
			if ( $post_meta ) {
	
				foreach ( $post_meta as $meta_key => $meta_values ) {

					if ( in_array( $meta_key, $ignore_meta_key_array ) ) {
						continue;
					} // endif
	
					foreach ( $meta_values as $meta_value ) {
						add_post_meta( $new_post_id, $meta_key, $meta_value );
					} // endfor
					
				} // endfor
				
			} // endif
			
			// Construct the new object from the new post so we can return it
			$new_post = get_post( $new_post_id );
			$result = self::instantiate_from_post( $new_post );
			
		} // endif
		
		return $result;
	} // function
	
	/**
	 * Get all events defined internally
	 *
	 * This method will return an array of instances of this class describing all events defined under this plugin
	 *
	 * @return \Reg_Man_RC\Model\Internal_Event_Descriptor[]
	 */
	public static function get_all_internal_event_descriptors() {

		$result = array();
		
		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
		);
		
		// The admin calendar should show draft events (at least the current user's own drafts)
		// But the admin calendar events are retrieved via JSON feed which is a non-admin context
		//  so WP_Query uses post_type = 'publish' ONLY
		// To include draft events in the admin calendar, we'll modify the post_status argument here
		//  for users who have authorization to create events
		// Individual calendars will determine which of the resulting events to show

		if ( current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			$args[ 'post_status' ] = array( 'publish', 'private', 'future', 'draft', 'pending' );
		} // endif

		$query = new \WP_Query( $args );
		$post_array = $query->posts;

//		Error_Log::var_dump( get_current_user(), is_admin() );
		foreach ( $post_array as $post ) {
			$event = self::instantiate_from_post( $post );
			if ( $event !== NULL ) {
				$result[] = $event;
			} // endif
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !

		return $result;

	} // function

	/**
	 * Get internal event descriptors whose categories include the one specified.
	 *
	 * This method will return an array of instances of this class containing events whose categories include the one specified
	 *
	 * @param	int|string	$event_category_id	The ID of the event categories whose descriptors are to be returned
	 * @return	Internal_Event_Descriptor[]
	 */
	public static function get_internal_event_descriptors_for_event_category( $event_category_id ) {

		$result = array();

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'tax_query' => array(
						array(
								'taxonomy'	=> Event_Category::TAXONOMY_NAME,
								'field'		=> 'term_id',
								'terms'		=> $event_category_id
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
	 * Get internal event descriptors for whose venue is the one specified.
	 *
	 * This method will return an array of instances of this class containing events whose venue is the one specified
	 *
	 * @param	int|string	$venue_id	The ID of the venue whose descriptors are to be returned
	 * @return	Internal_Event_Descriptor[]
	 */
	public static function get_internal_event_descriptors_for_venue( $venue_id ) {
		$result = array();

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'meta_key'				=> self::VENUE_META_KEY,
				'meta_query'			=> array(
							array(
									'key'		=> self::VENUE_META_KEY,
									'value'		=> $venue_id,
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

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;

	} // function

	/**
	 * Get a count of internal event descriptors for whose venue is the one specified.
	 *
	 * @param	int|string	$venue_id	The ID of the venue whose descriptors are to be returned
	 * @return	int
	 */
	public static function get_count_internal_event_descriptors_for_venue( $venue_id ) {
		$result = array();

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'meta_key'				=> self::VENUE_META_KEY,
				'meta_query'			=> array(
							array(
									'key'		=> self::VENUE_META_KEY,
									'value'		=> $venue_id,
									'compare'	=> '=',
							)
				)
		);

		$query = new \WP_Query( $args );
		$result = $query->found_posts;

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;

	} // function

	/**
	 * Get a single internal event descriptor using its ID (i.e. post ID), e.g. 2456
	 *
	 * This method will return a single instance of this class describing the event specified by the event ID.
	 * If the event is not found, this method will return NULL
	 *
	 * @param string|int	$event_desc_id	The ID for the event descriptor
	 * @return \Reg_Man_RC\Model\Internal_Event_Descriptor
	 */
	public static function get_internal_event_descriptor_by_id( $event_desc_id ) {

		$result = NULL;

		$post = ! empty( $event_desc_id ) ? get_post( $event_desc_id ) : NULL;
		
		// I want this to behave in the same way get_all_internal_event_desc does
		// get_post() will normally return a post of any status including trash
		// I want to make sure that posts with status that's not visible are not returned
		if ( ! empty( $post ) ) {
			$post_status = $post->post_status;
			if ( current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
				// Note that when a user is creating a new event the initial status will be auto-draft and it must be found
				$visible_statuses = array( 'publish', 'private', 'future', 'draft', 'pending', 'auto-draft' );
			} else {
				$visible_statuses = array( 'publish' );
			} // endif
		
			if ( in_array( $post_status, $visible_statuses ) ) {
				$result = self::instantiate_from_post( $post );
			} // endif
		} // endif
		
//		Error_Log::var_dump( $event_desc_id, ! empty( $post ), ! empty( $result ) );
		return $result;

	} // function

	/**
	 * Get an array of display names keyed by user ID for the users who have edit capability for internal events
	 * @return string[][]	An array of display names keyed by user ID, e.g. [ 1 => 'Dave', 2 => 'Lisa' ] 
	 */
	public static function get_event_editors_array() {
		
		if ( ! isset( self::$EVENT_EDITORS_ARRAY ) ) {
			
			$args = array( 'fields' => array( 'ID', 'display_name' ) );
			$all_users = get_users( $args );
			self::$EVENT_EDITORS_ARRAY = array();
			$capability = 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
			foreach( $all_users as $user ) {
				if ( user_can( $user->ID, $capability ) ) {
					self::$EVENT_EDITORS_ARRAY[ $user->ID ] = $user->display_name;
				} // endif
			} // endfor
	//		Error_Log::var_dump( $event_editors );
			
		} // endif
		return self::$EVENT_EDITORS_ARRAY;
	} // function
	
	/**
	 * Get the post object for this event.
	 * @return	\WP_Post	The post object for this event
	 * @since v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this event.
	 * @return	int		The post ID for this event
	 * @since v0.1.0
	 */
	private function get_post_id() {
		return $this->get_post()->ID;
	} // function

	/**
	 * Get the post status.
	 * @return	string		The post's status, e.g. public, private, draft etc.
	 * @since v0.1.0
	 */
	private function get_post_status() {
		return $this->get_post()->post_status;
	} // function

	/**
	 * Get has post password.
	 * @return	boolean		TRUE if the post has a password, FALSE otherwise.
	 * @since v0.1.0
	 */
	private function get_has_post_password() {
		return ( $this->get_post()->post_password !== '' ) ? TRUE : FALSE;
	} // function

	/**
	 * Get the array of event objects for this descriptor
	 * @return Event[]
	 */
	public function get_event_object_array() {
		if ( ! isset( $this->event_object_array ) ) {
			$this->event_object_array = Event::get_events_array_for_event_descriptor( $this );
		} // endif
		return $this->event_object_array;
	} // endif

	/**
	 * Get the globally unique id for the event, i.e. unique across domains.
	 *
	 * This is used when events are shared across systems, for example imported and exported, or used in an iCalendar feed.
	 * The UID is the post's GUID.
	 *
	 * @return	string		The globally unique id for the event.
	 * @since v0.1.0
	 */
	public function get_event_uid() {
		if ( ! isset( $this->uid ) ) {
			$post_id = $this->get_post_id();
			$this->uid = get_post_meta( $post_id, self::UID_META_KEY, $single = TRUE );
			if ( empty( $this->uid ) ) {
				$this->uid = wp_generate_uuid4();
				update_post_meta( $post_id, self::UID_META_KEY, $this->uid );
			} // endif
		} // endif
		return $this->uid;
	} // function

	/**
	 * Get the unique id of the provider for this event.
	 * @return	string		The unique id representing this event provider
	 * @since v0.1.0
	 */
	public function get_provider_id() {
		return self::EVENT_PROVIDER_ID;
	} // function

	/**
	 * Get the ID for this event descriptor that is unique within the event provider's domain.
	 * @return	string		The event ID which is unique for the event provider's implementation
	 * In this implementation each event descriptor is a custom post so the provider event id is the post ID.
	 * @since v0.1.0
	 */
	public function get_event_descriptor_id() {
		return $this->get_post_id();
	} // function

	/**
	 * Get the WordPress user ID of the author of this event, if known
	 * @return	int|string	The WordPress user ID of the author of this event if it is known, otherwise NULL or 0.
	 * @since v0.6.0
	 */
	public function get_event_author_id() {
		return $this->get_post()->post_author;
	} // function
	
	/**
	 * Get the event summary, e.g. "Repair Café at Toronto Reference Library".
	 * @return	string		The event summary if one is assigned, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_summary() {
		return $this->get_post()->post_title;
	} // function

	/**
	 * Get the event's status represented as an ID, one of CONFIRMED, TENTATIVE or CANCELLED.
	 *
	 * @return	string	The event's status ID.
	 * @since v0.1.0
	 */
	private function get_event_status_id() {
		if ( ! isset( $this->event_status_id ) ) {
			$meta = get_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $single = TRUE );
			// The default status is CONFIRMED if no metadata is stored
			$this->event_status_id = ! empty( $meta  ) ? $meta : Event_Status::CONFIRMED;
		} // endif
		return $this->event_status_id;
	} // function

	/**
	 * Set the event's status using an ID, one of CONFIRMED, TENTATIVE or CANCELLED.
	 *
	 * @return	string	The event's status ID.
	 * @since v0.1.0
	 */
	private function set_event_status_id( $status_id ) {
		if ( $status_id == Event_Status::CONFIRMED ) {
			// Confirmed is the default status so in that case we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::STATUS_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $status_id );
		} // endif
		$this->event_status_id = NULL; // allow it to be re-acquired
	} // function

	
	/**
	 * Get the date strings for cancelled event instances if this is a recurring event
	 * @return string[]
	 */
	public function get_cancelled_event_date_strings_array() {
		if ( ! isset( $this->cancel_date_strings_array ) ) {
			$post_id = $this->get_post_id();
			$this->cancel_date_strings_array = get_post_meta( $post_id, self::CANCEL_DATES_META_KEY, $single = TRUE );
			if ( empty( $this->cancel_date_strings_array ) ) {
				$this->cancel_date_strings_array = array();
			} // endif
		} // endif
		return $this->cancel_date_strings_array;
	} // function
	
	/**
	 * Set the cancel dates for the recurring event
	 * @param string[] $cancel_date_strings_array	An array of event dates as strings formatted as 'Ymd'
	 * @return void
	 */
	public function set_cancelled_event_date_strings_array( $cancel_date_strings_array ) {
		if ( empty( $cancel_date_strings_array ) ) {
			delete_post_meta( $this->get_post_id(), self::CANCEL_DATES_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::CANCEL_DATES_META_KEY, $cancel_date_strings_array );
		} // endif
	} // function
	
	/**
	 * Get the dates for cancelled events
	 * @return \DateTime[]
	 */
	public function get_cancelled_event_dates() {
		if ( ! isset( $this->cancel_date_times_array ) ) {
			$this->cancel_date_times_array = array();
			$local_tz = wp_timezone();
			$cancel_date_strings = $this->get_cancelled_event_date_strings_array();
			foreach( $cancel_date_strings as $date_string ) {
				try {
					
					$date_time = new \DateTime( $date_string, $local_tz );
					$this->cancel_date_times_array[] = $date_time;
					
				} catch( \Exception $exc ) {
					
					// Translators: %1$s is a date string 
					$msg = sprintf( __( 'An invalid cancel event date string was stored: %1$s.', 'reg-man-rc' ), $date_string );
					Error_Log::log_msg( $msg );
					
				} // endry
			} // endfor
		} // endif
		return $this->cancel_date_times_array;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_status()
	 */
	public function get_event_status( $event_date = NULL ) {

		// Normally, we'll return the status from the event descriptor
		$result = $this->get_event_descriptor_status();

		// If the event is cancelled then return that status regardless of the event date
		if ( $result->get_id() !== Event_Status::CANCELLED ) {

			// For a recurring event, we need to check if the specified instance has been cancelled
			if ( $this->get_event_is_recurring() && ! empty( $event_date ) ) {
				$date_str = $event_date->format( Event_Key::EVENT_DATE_FORMAT );
				$cancelled_dates = $this->get_cancelled_event_date_strings_array();
				if ( in_array( $date_str, $cancelled_dates ) ) {
					$result = Event_Status::get_event_status_by_id( Event_Status::CANCELLED );
				} // endif
			} // endif

		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get the status assigned to the event descriptor
	 * @return Event_Status
	 */
	private function get_event_descriptor_status() {
		if ( ! isset( $this->event_status ) ) {
			$this->event_status = Event_Status::get_event_status_by_id( $this->get_event_status_id() );
			if ( $this->event_status === NULL ) {
				$this->event_status = Event_Status::get_default_event_status();
			} // endif
		} // endif
		return $this->event_status;
	} // function

	/**
	 * Set the event's status.
	 *
	 * @param	Event_Status	$event_status	The event's status.
	 * @since v0.1.0
	 */
	public function set_event_status( $event_status ) {
		if ( $event_status instanceof Event_Status ) {
			$this->set_event_status_id( $event_status->get_id() );
		} // endif
		$this->event_status = NULL; // allow it to be re-acquired
	} // function

	/**
	 * Get the event's class represented as an instance of Event_Class.
	 *
	 * @return	Event_Class		The event's class.
	 * Will be PRIVATE if the post is not published, CONFIDENTIAL if it's published with a password and otherwise PUBLIC.
	 * @since v0.1.0
	 */
	public function get_event_class() {
		if ( ! isset( $this->event_class ) ) {
			$post_status = $this->get_post_status();
			if ( $post_status === 'publish' ) {
				if ( $this->get_has_post_password() ) {
					$this->event_class = Event_Class::get_event_class_by_id( Event_Class::CONFIDENTIAL );
				} else {
					$this->event_class = Event_Class::get_event_class_by_id( Event_Class::PUBLIC );
				} // endif
			} else {
				$this->event_class = Event_Class::get_event_class_by_id( Event_Class::PRIVATE );
			} // endif
		} // endif
		return $this->event_class;
	} // function

	private static function get_utc_timezone() {
		if ( ! isset( self::$UTC_TIMEZONE ) ) {
			self::$UTC_TIMEZONE = new \DateTimeZone( 'UTC' );
		} // endif
		return self::$UTC_TIMEZONE;
	} // function

	/**
	 * Get the event's start date and time represented as a string
	 * @return	string	The event's start date and time as a string in UTC time.
	 * @since v0.1.0
	 */
	private function get_event_dtstart() {
		if ( ! isset( $this->dtstart ) ) {
			$meta = get_post_meta( $this->get_post_id(), self::START_META_KEY, $single = TRUE );
			$this->dtstart = ( $meta !== FALSE ) ? $meta : NULL;
		} // endif
		return $this->dtstart;
	} // function

	/**
	 * Get the event's start date and time as a \DateTimeImmutable object using the local timezone as assigned by Wordpress.
	 * @return	\DateTimeImmutable	Event start date and time.  May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_start_date_time() {
		if ( ! isset( $this->start_date_time ) ) {
			$dtstart = $this->get_event_dtstart();
			if ( ! empty( $dtstart ) ) {
				try {
					// The date and time are stored in UTC but will be returned in local timezone
					// So we need to create a DateTime with UTC tz then change to local tz then create immutable
					$mutable_date_time = new \DateTime( $dtstart, self::get_utc_timezone() );
					$mutable_date_time->setTimezone( wp_timezone() );
					$this->start_date_time = \DateTimeImmutable::createFromMutable( $mutable_date_time );
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid dtstart value supplied for an event description */
					$msg = sprintf( __( 'Invalid dtstart: %1$s.', 'reg-man-rc' ), $dtstart );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->start_date_time;
	} // function

	/**
	 * Set the event's start date and time as a \DateTime object
	 * @param	\DateTime	$start_date_time	The DateTime object representing the start date and time for the event
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_event_start_date_time( $start_date_time ) {
		if ( $start_date_time == NULL ) {
			delete_post_meta( $this->get_post_id(), self::START_META_KEY );
		} else {
			// Convert the DateTime object's timezone to UTC for storage
			$start_date_time->setTimezone( self::get_utc_timezone() );
			$start_string = $start_date_time->format( self::DATE_DB_FORMAT );
			update_post_meta( $this->get_post_id(), self::START_META_KEY, $start_string );
			$this->dtstart = NULL; // Allow the new start DateTime to be re-acquired
			$this->start_date_time = NULL;
		} // endif
	} // function

	/**
	 * Get the event's start date and time represented as a string
	 * @return	string	The event's start date and time as a string in UTC time.
	 * @since v0.1.0
	 */
	private function get_event_dtend() {
		if ( !isset( $this->dtend ) ) {
			$meta = get_post_meta( $this->get_post_id(), self::END_META_KEY, $single = TRUE );
			$this->dtend = ( $meta !== FALSE ) ? $meta : NULL;
		} // endif
		return $this->dtend;
	} // function

	/**
	 * Get the event's end date and time as a \DateTimeImmutable object
	 * @return	\DateTimeImmutable	Event end date and time.  May be NULL if no end time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_end_date_time() {
		if ( !isset( $this->end_date_time ) ) {
			$dtend = $this->get_event_dtend();
			if ( ! empty( $dtend ) ) {
				try {
					// The date and time are stored in UTC but will be returned in local timezone
					// So we need to create a DateTime with UTC tz then change to local tz then create immutable
					$mutable_date_time = new \DateTime( $dtend, self::get_utc_timezone() );
					$mutable_date_time->setTimezone( wp_timezone() );
					$this->end_date_time = \DateTimeImmutable::createFromMutable( $mutable_date_time );
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid dtend value supplied for an event description */
					$msg = sprintf( __( 'Invalid dtend: %1$s.', 'reg-man-rc' ), $dtend );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->end_date_time;
	} // function

	/**
	 * Set the event's end date and time as a \DateTimeInterface object, e.g. \DateTime instance
	 * @param	\DateTime	$end_date_time	The DateTime object representing the end date and time for the event
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_event_end_date_time( $end_date_time ) {
		if ( $end_date_time == NULL ) {
			delete_post_meta( $this->get_post_id(), self::END_META_KEY );
		} else {
			// Convert the DateTime object's timezone to UTC for storage
			$end_date_time->setTimezone( self::get_utc_timezone() );
			$end_string = $end_date_time->format( self::DATE_DB_FORMAT );
			update_post_meta( $this->get_post_id(), self::END_META_KEY, $end_string );
			$this->dtend = NULL; // Allow the new start DateTime to be re-acquired
			$this->end_date_time = NULL;
		} // endif
	} // function

	/**
	 * Get the event venue object
	 * @return	Venue	Event venue if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_venue() {
		if ( !isset( $this->venue ) ) {
			$meta = get_post_meta($this->get_post_id(), self::VENUE_META_KEY, $single = TRUE);
			$this->venue = ( $meta !== FALSE ) ? Venue::get_venue_by_id( $meta ) : NULL;
		} // endif
		return $this->venue;
	} // function


	/**
	 * Set the event's venue using an ID, i.e. the post ID of the venue
	 * @return	void
	 * @since v0.1.0
	 */
	private function set_event_venue_id( $venue_id ) {
		if ( empty( $venue_id ) ) {
			// NULL or otherwise empty ID means there is none, so in that case we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::VENUE_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::VENUE_META_KEY, $venue_id );
		} // endif
	} // function

	/**
	 * Set the event's venue.
	 *
	 * @param	Venue	$event_venue	The event's venue.  If NULL is passed, the current event venue will be removed.
	 * @since v0.1.0
	 */
	public function set_event_venue( $event_venue ) {
		if ( $event_venue instanceof Venue ) {
			$this->set_event_venue_id( $event_venue->get_id() );
		} elseif ( $event_venue === NULL ) {
			$this->set_event_venue_id( NULL ); // this means remove the current venue
		} // endif
		$this->venue = NULL; // allow it to be re-acquired
	} // function


	/**
	 * Get the event location, e.g. "Toronto Reference Library, 789 Yonge Street, Toronto, ON, Canada".
	 * This is delegated to the venue object.
	 * @return	string		Event location if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_location() {
		$venue = $this->get_event_venue();
		if ( ! isset( $venue ) ) {
			$result = NULL;
		} else {
			$name = $venue->get_name();
			$location = $venue->get_location();
			if ( ! empty( $name ) && ! empty( $location ) ) {
				/* Translators: %1$s is a venue name like "Toronto Reference Library" and %2$s is a location like "789 Yonge St" */
				$format = __( '%1$s – %2$s', 'reg-man-rc' );
				$result = sprintf( $format, $name, $location );
			} else {
				// One or both of name and location are empty, return the first one that's not empty or just the empty value
				$result = ! empty( $name ) ? $name : $location;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get the event's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position is not known.
	 * This is delegated to the venue object.
	 *
	 * @return	Geographic_Position	The event's position (co-ordinates) used to map the event if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_event_geo() {
		$venue = $this->get_event_venue();
		$result = ( $venue !== NULL ) ? $venue->get_geo() : NULL;
		return $result;
	} // function

	/**
	 * Get the description of the event as a string, e.g. "Fixing all kinds of items!"
	 * @return	string		Event description if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_description() {
		return $this->get_post()->post_content;
	} // function

	/**
	 * Get the event's category objects as an array
	 * @return	Event_Category[]	An array of Event_Category objects specifying the event's categories.
	 * This may be NULL or an empty array if there are no categories
	 * @since v0.1.0
	 */
	public function get_event_category_object_array() {
		if ( ! isset( $this->category_object_array ) ) {
			$post_id = $this->get_post_id();
			$post_cats = ! empty( $post_id ) ? Event_Category::get_event_categories_for_post( $post_id ) : array();
			if ( ! empty( $post_cats ) ) {
				$this->category_object_array = $post_cats;
			} else {
				/* DKS May 29, 2024 - we have a request to require users to select a category
				 *  rather than assigning the default.
				$default_cat = Event_Category::get_default_event_category();
				$this->category_object_array = ! empty( $default_cat ) ? array( $default_cat ) : array();
				*/
				$this->category_object_array = array();
			} // endif
		} // endif
		return $this->category_object_array;
	} // function

	/**
	 * Get the event's categories as an array of strings, e.g. { "Repair Cafe", "Mini Event" }
	 * @return	string[]	An array of strings listing the event's categories.
	 * Returns an empty array if there are no categories
	 * @since v0.1.0
	 */
	public function get_event_categories() {
		if ( ! isset( $this->categories ) ) {
			$cat_obj_array = $this->get_event_category_object_array();
			$this->categories = array();
			foreach ( $cat_obj_array as $category ) {
				$this->categories[] = $category->get_name();
			} // endfor
		} // endif
		return $this->categories;
	} // function

	/**
	 * Set the categories for this event
	 *
	 * @param	Event_Category[]	$event_category_array	An array of categories to assign to this event
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public function set_event_category_array( $event_category_array ) {
		$event_desc_id = $this->get_post_id();
		if ( ! is_array( $event_category_array ) || ( empty( $event_category_array ) ) ) {
			// If the new category array is NULL or empty then that means to unset or remove the current setting
			wp_delete_object_term_relationships( $event_desc_id, Event_Category::TAXONOMY_NAME );
		} else {
			$category_id_array = array();
			foreach ( $event_category_array as $category ) {
				if ( $category instanceof Event_Category ) {
					$category_id_array[] = intval( $category->get_id() );
				} // endif
			} // endfor
			wp_set_post_terms( $event_desc_id, $category_id_array, Event_Category::TAXONOMY_NAME );
		} // endif
		$this->category_object_array = NULL; // reset my internal vars so they can be re-acquired
		$this->categories = NULL;
	} // function

	/**
	 * Get a flag indicating whether this is a non-repair event like a volunteer appreciation dinner where items are not fixed.
	 *
	 * @return	boolean		TRUE if this is a non-repair event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_non_repair() {
		if ( !isset( $this->is_non_repair_event ) ) {
			$val = get_post_meta( $this->get_post_id(), self::NON_REPAIR_EVENT_META_KEY, $single = TRUE );
			$this->is_non_repair_event = ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) ? TRUE : FALSE;
		} // endif
		return $this->is_non_repair_event;
	} // function

	/**
	 * Assign the boolean indicating whether this is a non-repair event
	 * @param	boolean	$is_non_repair	TRUE if the event is non-repair, FALSE otherwise
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_event_is_non_repair( $is_non_repair ) {
		if ( ! $is_non_repair ) {
			// This meta value is only present to indicate the positive, that the event is non-repair
			// When it's false we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::NON_REPAIR_EVENT_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::NON_REPAIR_EVENT_META_KEY, '1' );
		} // endif
		unset( $this->is_non_repair_event ); // allow it to be re-acquired
	} // function

	
	/**
	 * Get the note for the event shown only in the volunteer area before registration
	 *
	 * @return	string
	 * @since v0.8.7
	 */
	public function get_volunteer_pre_reg_note() {
		if ( !isset( $this->volunteer_pre_reg_note ) ) {
			$val = get_post_meta( $this->get_post_id(), self::VOLUNTEER_PRE_REG_NOTE_META_KEY, $single = TRUE );
			$this->volunteer_pre_reg_note = ( ( $val !== FALSE ) && ( $val !== NULL ) && is_string( $val ) ) ? $val : '';
		} // endif
		return $this->volunteer_pre_reg_note;
	} // function

	/**
	 * Set the note for the event shown in the volunteer area
	 *
	 * @param	string	$volunteer_pre_reg_note
	 * @since v0.8.7
	 */
	public function set_volunteer_pre_reg_note( $volunteer_pre_reg_note ) {
		if ( empty( $volunteer_pre_reg_note ) || ! is_string( $volunteer_pre_reg_note ) ) {
			delete_post_meta( $this->get_post_id(), self::VOLUNTEER_PRE_REG_NOTE_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			// wp_kses_post() Sanitizes content for allowed HTML tags for post content
			$meta = wp_kses_post( $volunteer_pre_reg_note );
			update_post_meta( $this->get_post_id(), self::VOLUNTEER_PRE_REG_NOTE_META_KEY, $meta );
		} // endif
		unset( $this->volunteer_pre_reg_note ); // allow it to be re-acquired
	} // function

	
	
	/**
	 * Get the fixer stations for this event
	 *
	 * @return	Fixer_Station[]
	 */
	public function get_event_fixer_station_array() {
		if ( ! isset( $this->fixer_stations ) ) {
			$this->fixer_stations = Fixer_Station::get_fixer_stations_for_post( $this->get_post_id() );
		} // endif
		return $this->fixer_stations;
	} // function

	/**
	 * Set the fixer stations for this event
	 *
	 * @param	Fixer_Station[]	$fixer_station_array	An array of fixer stations to assign to this event
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public function set_event_fixer_station_array( $fixer_station_array ) {
		$event_desc_id = $this->get_post_id();
		if ( !is_array( $fixer_station_array ) || ( empty( $fixer_station_array ) ) ) {
			// If the new fixer station array is NULL or empty then that means to unset or remove the current setting
			wp_delete_object_term_relationships( $event_desc_id, Fixer_Station::TAXONOMY_NAME );
		} else {
			$station_id_array = array();
			foreach ( $fixer_station_array as $fixer_station ) {
				if ( $fixer_station instanceof Fixer_Station ) {
					$station_id_array[] = intval( $fixer_station->get_id() );
				} // endif
			} // endfor
			wp_set_post_terms( $event_desc_id, $station_id_array, Fixer_Station::TAXONOMY_NAME );
		} // endif
		$this->fixer_stations = NULL; // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get the url for the event descriptor page if one exists
	 * @return	string	The url for the page that shows this event descriptor if it exists, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	private function get_event_descriptor_page_url() {
		if ( ! isset( $this->url ) ) {
			$this->url = get_permalink( $this->get_post() );
		} // endif
		return $this->url;
	} // function

	/**
	 * Get the url for the event descriptor page or event recurrence page when $recur_date is specified.
	 * @param	string|NULL	$recur_date	An event recurrence date.
	 *  When NULL or empty the result of this method is the url for the page showing the event descriptor, if such a page exists.
	 *  If $recur_date is specified then the result is the url for the page showing the specified recurrence, if it exists.
	 *  If no separate page exists for the event recurrence then the result is the same as when no $recur_date is specified.
	 * @return	string			The url for the page that shows this event descriptor or event recurrence if one exists,
	 *  otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_page_url( $recur_date = NULL ) {
		$base_url = $this->get_event_descriptor_page_url();
		if ( empty( $recur_date ) ) {
			$result = $base_url; // There's no recur date specified so return the URL to the descriptor page
		} else {
			$arg_name = Event_Key::EVENT_DATE_QUERY_ARG_NAME;
			$query_arg_array = array( $arg_name => $recur_date );
			$result = add_query_arg( $query_arg_array, $base_url );
		} // endif
		return $result;
	} // function

	/**
	 * Get the url to edit the event descriptor, if the page exists.
	 * @return	string	The url for the page to edit this event descriptor if one exists, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_edit_url() {
		$post_id = $this->get_post_id();
		if ( ! current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_SINGULAR, $post_id ) ) {
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
	 * Get the url to add a new event descriptor
	 * @return	string	The url for a link to add a new event descriptor
	 * @since v0.9.9
	 */
	public static function get_add_event_url() {
		if ( ! current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			$result = NULL;
		} else {
			$base_url = admin_url( 'post-new.php' );
			$query_args = array(
					'post_type'		=> self::POST_TYPE,
			);
			$result = add_query_arg( $query_args, $base_url );
		} // endif
		return $result;
	} // function

	
	/**
	 * Get a boolean flag indicating whether the event is recurring.
	 * @return	boolean	TRUE for a recurring event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_recurring() {
		$result = ( ! empty( $this->get_event_recurrence_rule() ) );
		return $result;
	} // endif

	/**
	 * Get the recurrence rule for a repeating event.  Non-repeating events will return NULL.
	 * @return	Recurrence_Rule	For a repeating event this method returns an instance of Recurrence_Rule that specifies how the event repeats.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_recurrence_rule() {
		if ( ! isset( $this->recurrence_rule ) ) {
			$rrule = $this->get_rrule();
			if ( ! empty( $rrule ) ) {
				$this->recurrence_rule = Recurrence_Rule::create_from_string( $rrule );
				$this->recurrence_rule->set_start_date_time( $this->get_event_start_date_time() );
				$this->recurrence_rule->set_end_date_time( $this->get_event_end_date_time() );
				$this->recurrence_rule->set_exclude_dates( $this->get_event_exclusion_dates() );
				$this->recurrence_rule->set_include_dates( $this->get_event_inclusion_dates() );
			} // endif
		} // endif
		return $this->recurrence_rule;
	} // function
	
	/**
	 * Set the recurrence rule for a repeating event.  For a non-repeating events this is set to NULL.
	 * @param	Recurrence_Rule	$recurrence_rule
	 * @since v0.5.0
	 */
	public function set_event_recurrence_rule( $recurrence_rule ) {
		if ( empty( $recurrence_rule ) ) {
			delete_post_meta( $this->get_post_id(), self::RRULE_META_KEY );
		} else {
			$rrule_string = $recurrence_rule->get_as_string();
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::RRULE_META_KEY, $rrule_string );
		} // endif
		unset( $this->is_non_repair_event ); // allow it to be re-acquired
		
	} // funciton

	/**
	 * Get the RRULE text representation, e.g. 'FREQ=MONTHLY;BYMONTHDAY=1;INTERVAL=1'
	 * @return	string	The text representation for the recurrence rule.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	private function get_rrule() {
		if ( !isset( $this->rrule ) ) {
			$meta = get_post_meta( $this->get_post_id(), self::RRULE_META_KEY, $single = TRUE );
			$this->rrule = ( $meta !== FALSE ) ? $meta : NULL;
		} // endif
		return $this->rrule;
	} // function

	/**
	 * Get the dates and times to be excluded from the recurrence set as an array of \DateTimeInterface objects, e.g. \DateTime instances
	 * @return	\DateTimeInterface[]	Dates and times to be excluded.  May be NULL if this is a non-recurring event.
	 * @since v0.1.0
	 */
	 public function get_event_exclusion_dates() {
	 	if ( !isset( $this->exclusion_date_time_array ) ) {
			if ( !empty( $this->exdates ) ) {
				$this->exclusion_date_time_array = array();
				try {
					$dates = explode( ',', $this->exdates );
					$utc_tz = self::get_utc_timezone();
					foreach ( $dates as $date ) {
						$date_time = new \DateTime( $date, $utc_tz );
						if ( !empty( $date_time ) ) {
							$this->exclusion_date_time_array[] = $date_time;
						} // endif
					} // endfor
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid exdate value supplied for an event description */
					$msg = sprintf( __( 'Invalid exdate: %1$s.', 'reg-man-rc' ), $this->exdates );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->exclusion_date_time_array;
	} // function

	/**
	 * Get the dates and times to be added to the recurrence set as an array of \DateTimeInterface objects, e.g. \DateTime instances
	 * @return	\DateTimeInterface[]	Dates and times to be added.  May be NULL if this is a non-recurring event.
	 * @since v0.1.0
	 */
	 public function get_event_inclusion_dates() {
		if ( !isset( $this->inclusion_date_time_array ) ) {
			if ( !empty( $this->rdates ) ) {
				$this->inclusion_date_time_array = array();
				try {
					$dates = explode( ',', $this->rdates );
					$utc_tz = self::get_utc_timezone();
					foreach ( $dates as $date ) {
						$date_time = new \DateTime( $date, $utc_tz );
						if ( !empty( $date_time ) ) {
							$this->inclusion_date_time_array[] = $date_time;
						} // endif
					} // endfor
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid rdate value supplied for an event description */
					$msg = sprintf( __( 'Invalid rdate: %1$s.', 'reg-man-rc' ), $this->rdates );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->inclusion_date_time_array;
	} // function

	// The following methods (get_map_marker...()) provide the implementation of the Map_Marker interface

	/**
	 * Get the marker title as a string.
	 * This string is shown on the map when the user hovers over the marker, similar to an element's title attribute.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_title( $map_type ) {
		$result = $this->get_event_summary();
		return $result;
	} // function

	/**
	 * Get the marker label as a string.  May return NULL if no label is required.
	 * This string, if provided, is shown as text next to the marker.
	 * It can be used to indicate some special condition or information about the marker, e.g. "Event Cancelled"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_label( $map_type ) {
		// Show the event status if it's not confirmed
		$status = $this->get_event_status();
		$status_id = $status->get_id();
		$result = ( $status_id !== Event_Status::CONFIRMED ) ? $status->get_name() : NULL;
		return $result;
	} // function

	/**
	 * Get the marker location as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL	The marker location if it is known, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_location( $map_type ) {
		$venue = $this->get_event_venue();
		$result = isset( $venue ) ? $venue->get_map_marker_location( $map_type ) : NULL;
		return $result;
	} // function

	/**
	 * Get the marker ID as a string.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type ) {
		return self::POST_TYPE . '-' . $this->get_post_id();
	} // function

	/**
	 * Get the marker position as an instance of Geographic_Position.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	Geographic_Position	The geographic position of the map marker
	 * @since v0.1.0
	 */
	public function get_map_marker_geographic_position( $map_type ) {
		$result = $this->get_event_geo();
		return $result;
	} // function

	/**
	 * Get the zoom level for the map when the venue is shown on a map by itself.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	int	The zoom level for the map when this venue is shown by itself.
	 * @since v0.1.0
	 */
	public function get_map_marker_zoom_level( $map_type ) {
		$venue = $this->get_event_venue();
		$result = isset( $venue ) ? $venue->get_map_marker_zoom_level( $map_type ) : NULL;
		return $result;
	} // function

	/**
	 * Get the colour used for the map marker or NULL if the default colour should be used.
	 * The result can be any string that is a valid colour in CSS.
	 * For example, '#f00', 'red', 'rgba( 255, 0, 0, 0.5)'.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_colour( $map_type ) {
		if ( ! isset( $this->map_marker_colour ) ) {
			$categories = $this->get_event_category_object_array();
			if ( is_array( $categories) && isset( $categories[ 0 ] ) ) {
				$top_category = $categories[ 0 ];
				$this->map_marker_colour = $top_category->get_colour();
			} // endif
			if ( ! isset( $this->map_marker_colour ) ) {
				$this->map_marker_colour = Map_View::DEFAULT_MARKER_COLOUR;
			} // endif
		} // endif
		return $this->map_marker_colour;
	} // function

	/**
	 * Get the opacity used for the map marker or NULL if the default opacity of 1 should be used.
	 * The result must be a number between 0 and 1, zero being completely transparent, 1 being completely opaque.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|float|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_opacity( $map_type ) {
		return NULL;
	} // endif

	/**
	 * Get the content shown in the info window for the marker including any necessary html markup or NULL if no info is needed.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_info( $map_type ) {
		$view = Event_Descriptor_View::create_for_map_info_window( $this, $map_type );
		$result = $view->get_object_view_content();
		return $result;
	} // function

	
	private function get_events_collection() {
		if ( ! isset( $this->events_collection ) ) {
			$this->events_collection = Events_Collection::create_for_events_array( $this->get_event_object_array() );
		} // endif
		return $this->events_collection;
	} // function
	
	/**
	 * Get the Item_Stats_Collection object for this event grouped by total
	 * @return Item_Stats_Collection
	 */
	public function get_total_item_stats_collection() {
		if ( ! isset( $this->total_item_stats_collection ) ) {
			$events_collection = $this->get_events_collection();
			$group_by = Item_Stats_Collection::GROUP_BY_TOTAL;
			$this->total_item_stats_collection = Item_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
		} // endif
		return $this->total_item_stats_collection;
	} // function
	
	/**
	 * Get the Visitor_Stats_Collection object for this event grouped by total
	 * @return Visitor_Stats_Collection
	 */
	public function get_total_visitor_stats_collection() {
		if ( ! isset( $this->total_visitor_stats_collection ) ) {
			$events_collection = $this->get_events_collection();
			$group_by = Visitor_Stats_Collection::GROUP_BY_TOTAL;
			$this->total_visitor_stats_collection = Visitor_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
		} // endif
		return $this->total_visitor_stats_collection;
	} // function
	
	/**
	 * Get the Volunteer_Stats_Collection object for this event grouped by total
	 * @return Volunteer_Stats_Collection
	 */
	public function get_total_volunteer_stats_collection() {
		if ( ! isset( $this->total_volunteer_stats_collection ) ) {
			$events_collection = $this->get_events_collection();
			$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL;
			$this->total_volunteer_stats_collection = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
		} // endif
		return $this->total_volunteer_stats_collection;
	} // function
	
	/**
	 * Get the total count of items for this event
	 * @return int
	 */
	public function get_total_items_count() {
		if ( ! isset( $this->total_items_count ) ) {
			$stats_collection = $this->get_total_item_stats_collection();
			$totals_array = array_values( $stats_collection->get_all_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$this->total_items_count = isset( $total_stats ) ? $total_stats->get_item_count() : 0;			
		} // endif
		return $this->total_items_count;
	} // function

	/**
	 * Get the total count of visitors for this event
	 * @return int
	 */
	public function get_total_visitors_count() {
		if ( ! isset( $this->total_visitors_count ) ) {
			$stats_collection = $this->get_total_visitor_stats_collection();
			$totals_array = array_values( $stats_collection->get_all_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$this->total_visitors_count = isset( $total_stats ) ? $total_stats->get_visitor_count() : 0;			
		} // endif
		return $this->total_visitors_count;
	} // function

	/**
	 * Get the total count of volunteers for to this event
	 * @return int
	 */
	public function get_total_volunteers_count() {
		if ( ! isset( $this->total_volunteers_count ) ) {
			$stats_collection = $this->get_total_volunteer_stats_collection();
			$totals_array = array_values( $stats_collection->get_all_stats_array() );
			$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
			$this->total_volunteers_count = isset( $total_stats ) ? $total_stats->get_head_count() : 0;			
		} // endif
		return $this->total_volunteers_count;
	} // function
	

	/**
	 *  Register the custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {
		$labels = array(
				'name'					=> _x( 'Event Descriptions', 'Event post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Event Description', 'Event post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Event Description' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Event Description', 'reg-man-rc'),
				'new_item'				=> __( 'New Event Description', 'reg-man-rc'),
				'all_items'				=> __( 'Event Descriptions', 'reg-man-rc'),
				'view_item'				=> __( 'View Event Description', 'reg-man-rc'),
				'search_items'			=> __( 'Search Event Descriptions', 'reg-man-rc'),
				'not_found'				=> __( 'No event descriptions found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'No event descriptions found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Repair Café Event Descriptions', 'reg-man-rc' )
		);

		global $wp_version;

		$icon = ( version_compare( $wp_version, '5.5', '>=' ) ) ? 'dashicons-coffee' : 'dashicons-calendar';

		// TODO: Right now the block editor (gutenberg) does not handle required inputs properly
		// This could cause problems like missing recurrence rule inputs and so on
		// For now, we'll turn it off
		$show_in_rest = FALSE;
		
		$supports = array( 'title', 'editor', 'thumbnail', 'comments' );

		$capability_singular = User_Role_Controller::EVENT_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;

		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Events', // Internal description, not visible externally
				'public'				=> TRUE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> FALSE, // exclude from regular search results?
				'publicly_queryable'	=> TRUE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> $show_in_rest, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> TRUE, // Whether to include this post type in the admin bar
				'menu_position'			=> Admin_Menu_Page::get_menu_position(), // Menu order position
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> $supports,
				'taxonomies'			=> array(
												Event_Category::TAXONOMY_NAME,
												Fixer_Station::TAXONOMY_NAME,
				),
				'has_archive'			=> FALSE, // is there an archive page?
				// FIXME - I need to figure out how to show events in general, not just our internal ones
				
				// Rewrite determines how the public page url will look when permalinks are NOT plain.
				// When the permalinks structure setting is "Plain" the CPT permalink will always be:
				//   http://site/?{cpt-type-name}={this-cpt-post-id} (regardless of the setting for rewrite)
				// When permalinks is anything other than "Plain" then you get the following results:
				//   http://site/{cpt-type-name}/{this-cpt-post-slug} (rewrite TRUE)
				//   http://site/?{cpt-type-name}={this-cpt-post-slug} (rewrite FALSE)
				//   http://site/?{cpt-slug}/{this-cpt-post-slug} (rewrite [ 'cpt-slug' ] )
				// Note that if permalinks is "Custom" with some static front part like "/some_front/%postname%/"
				//  then 'with_front' set to TRUE below will include the "some_front" part
				//   http://site/some_front/{cpt-slug}/{this-cpt-post-slug}
//				'rewrite'	=> TRUE, // TODO: Do I override the default rewrite?
//				'rewrite'	=> FALSE,
				'rewrite'	=> array(
						'slug'			=> Settings::get_events_slug(),
						'with_front'	=> FALSE
				),

				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		$post_type = register_post_type( self::POST_TYPE, $args );

		if ( is_wp_error( $post_type ) ) {
			$msg = __( 'Failure to register custom post type for Internal Event Descriptor', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $post_type );
		} // endif

	} // function

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