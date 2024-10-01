<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\View\Map_View;

/**
 * Describes an event calendar defined internally by this plugin.
 *
 * An instance of this class contains the information related to an calendar including its name and what types of events it contains.
 *
 * @since	v0.1.0
 *
 */
class Calendar {

	const POST_TYPE = 'reg-man-rc-calendar';
	const DEFAULT_PAGE_SLUG = 'rc-calendars';

	const CALENDAR_TYPE_EVENTS				= 'events-calendar'; // usual event calendar
	const CALENDAR_TYPE_VISITOR_REG			= 'visitor-reg-calendar'; // used for visitor registration
	const CALENDAR_TYPE_VOLUNTEER_REG		= 'volunteer-reg-calendar'; // volunteer registration
	const CALENDAR_TYPE_ADMIN				= 'admin-calendar'; // for admin, shows everything
	const CALENDAR_TYPE_EVENT_DESCRIPTOR	= 'event-descriptor-calendar'; // shows events for a descriptor
	
	private static $ADMIN_CALENDAR; // An instance of this class that contains all events
	private static $VISITOR_REG_CALENDAR; // An instance of this class used for visitor registration
	private static $VOLUNTEER_REG_CALENDAR; // An instance of this class used for volunteer registration

	// Meta keys used to store calendar specific settings
	private static $ICAL_FEED_META_KEY							= self::POST_TYPE . '-ical-feed';
	private static $ICAL_FEED_SUBSCRIBE_BUTTON_META_KEY			= self::POST_TYPE . '-ical-show-subscribe-button';
	private static $EVENT_STATUSES_META_KEY						= self::POST_TYPE . '-event-statuses';
	// Note that categories are a taxonomy so we don't store as metadata, they're acquired through get_terms()
//	private static $SHOW_UNCATEGORIZED_META_KEY					= self::POST_TYPE . '-show-uncategorized';
	private static $VIEWS_META_KEY								= self::POST_TYPE . '-views';
	private static $DURATIONS_META_KEY							= self::POST_TYPE . '-durations';
	private static $IS_SHOW_PAST_EVENTS_META_KEY				= self::POST_TYPE . '-is-show-past-events';

	private $post;
	private $id;
	private $post_id; // Normally the id and post id are the same, except for special calendars like admin or visitor reg
	private $calendar_type;
	private $name;
	private $icalendar_feed_name; // The name portion of the feed url, when this is assigned there is a feed
	private $icalendar_feed_title; // The title used to describe the feed
	private $icalendar_feed_url; // The URL of this calendar's iCal feed
	private $icalendar_feed_file_name; // The file name for this calendar's iCal feed
	private $icalendar_is_show_subscribe_button; // Whether to show a subscribe button for this calendar
	private $event_class_array;
	private $event_status_array;
	private $event_category_array;
	private $event_category_names_array;
//	private $show_uncategorized;
	private $view_format_ids_array;
	private $duration_ids_array;
	private $multi_month_min_width; // The minimum width of a month in a multi-month display (in pixels)
	private $is_show_past_events;
	private $is_show_past_events_toggle_button;
	private $event_descriptor; // a reference to the event descriptor used to create the calendar (when used)
	private $is_login_required_for_fullcalendar_feed; // TRUE if the FullCalendar feed requires a logged in user to access it, FALSE when it's public
	private $is_nonce_required_for_fullcalendar_feed; // TRUE if the FullCalendar feed requires a valid nonce to access it, FALSE when it's public
	// Note that the volunteer reg calendar does not necessarily require a logged in user but does require a nonce
	
	/**
	 * Instantiate and return a new instance of this class using the specified post data
	 *
	 * @param	\WP_Post	$post	The post data for the new calendar
	 * @return	self
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post so I can't process it
		} else {
			$result = new self();
			$result->post				= $post;
			$result->id					= $post->ID;
			$result->post_id			= $post->ID;
			$result->calendar_type		= self::CALENDAR_TYPE_EVENTS;
			$result->name				= $post->post_title;
		} // endif
		return $result;
	} // function

	/**
	 * Get all calendars visible in the current context
	 *
	 * This method will return an array of instances of this class describing all calendars defined under this plugin.
	 * If the frontend website is being rendered then this will include only public instances,
	 *  if it's the backend admin interface then this will also include otherwise hidden instances like private and draft.
	 *
	 * @return	Calendar[]
	 */
	public static function get_all_calendars() {
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
			$calendar = self::instantiate_from_post( $post );
			if ( $calendar !== NULL ) {
				$result[] = $calendar;
			} // endif
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;
	} // function

	/**
	 * Get all calendars that include the specified event category
	 * This method will return an array of instances of this class describing all calendars that show events of the specified category.
	 * @param	Event_Category	$event_category	The event category whose calendars are to be returned
	 * @return	Calendar[]
	 */
	public static function get_calendars_with_event_category( $event_category ) {

		$result = array();
		
		if ( ! empty( $event_category ) ) {
			
			$cat_id = $event_category->get_id();
			
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'posts_per_page'		=> -1, // get all
					'orderby'				=> 'post_title',
					'order'					=> 'ASC',
					'tax_query'				=> array(
							array(
									'taxonomy'	=> Event_Category::TAXONOMY_NAME,
									'field'		=> 'term_id',
									'terms'		=> $cat_id,
							)
					),
					'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
					
			);

			$query = new \WP_Query( $args );
			$post_array = $query->posts;

			foreach ( $post_array as $post ) {
				$calendar = self::instantiate_from_post( $post );
				if ( $calendar !== NULL ) {
					$result[] = $calendar;
				} // endif
			} // endfor

//			wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
			
		} // endif
		
		return $result;
		
	} // function

	
	/**
	 * Get calendars with an iCalendar event feed
	 *
	 * This method will return an array of instances of this class for calendars configured to provide
	 *  a public iCalendar event feed
	 *
	 * @return	Calendar[]
	 */
	public static function get_all_calendars_with_icalendar_feed() {
		$result = array();

		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
				'meta_key'				=> self::$ICAL_FEED_META_KEY,
				'meta_query'			=> array(
							array(
									'key'		=> self::$ICAL_FEED_META_KEY,
									'compare'	=> 'EXISTS',
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
	 * Get an instance of this class that shows all events.  For administrative purposes.
	 */
	public static function get_admin_calendar() {
		if ( ! isset( self::$ADMIN_CALENDAR ) ) {

			// NOTE - An admin calendar is used to do lots of things like populate the events dropdown on the Items admin page.
			// We allow anybody to get an admin calendar, but only populate the events that are visible to that user
			// So an admin calendar acquired by a user who cannot see private events, will not contain private events
			// But what we do not want here, is to return NULL.  We need a calendar object, it just may have no events.

			$result = new self();
			$result->id = self::CALENDAR_TYPE_ADMIN; // we use the type name as the ID
			$result->calendar_type = self::CALENDAR_TYPE_ADMIN;
			$result->post_id = 0; // There is no post
			$result->name = __( 'Administrative Calendar', 'reg-man-rc' );
			$result->event_class_array = array( Event_Class::PUBLIC, Event_Class::PRIVATE, Event_Class::CONFIDENTIAL );
			$result->event_status_array = array_keys( Event_Status::get_all_event_statuses() );
			$result->event_category_array = Event_Category::get_all_event_categories();
			$result->view_format_ids_array = Settings::get_admin_calendar_views();
			$result->duration_ids_array = Settings::get_admin_calendar_durations();
			self::$ADMIN_CALENDAR = $result;

		} // endif
		return self::$ADMIN_CALENDAR;
	} // function

	/**
	 * Get an instance of this class that shows all event instances for the specified event descriptor
	 * @param	Event_Descriptor	$event_descriptor
	 * @return	Calendar
	 */
	public static function get_event_descriptor_calendar( $event_descriptor ) {

//		Error_Log::var_dump( $event_descriptor );
		$result = new self();
		$result->calendar_type = self::CALENDAR_TYPE_EVENT_DESCRIPTOR;
		$result->event_descriptor = $event_descriptor;
		$result->id = self::get_calendar_id_for_event_descriptor( $event_descriptor );
		$result->post_id = 0;
		$result->name = __( 'Event Descriptor Calendar', 'reg-man-rc' );
		$result->event_category_array = array();
		$result->view_format_ids_array = array( Calendar_View_Format::GRID_VIEW, Calendar_View_Format::LIST_VIEW );
		$result->duration_ids_array = array( Calendar_Duration::CALENDAR_YEAR );
		
		return $result;

	} // function
	
	/**
	 * Get a calendar ID for the specified event descriptor
	 * @param	Event_Descriptor	$event_descriptor
	 * @return	string
	 */
	private static function get_calendar_id_for_event_descriptor( $event_descriptor ) {
		// The calendar needs to find the event descriptor during an ajax call so the events can be determined
		// To do that, we will construct a string ID for the calendar using the event descriptor's provider and ID
		// Later, those can be used to find the descriptor again
		if ( isset( $event_descriptor ) ) {
			$prov_id = $event_descriptor->get_provider_id();
			$desc_id = $event_descriptor->get_event_descriptor_id();
			$result = "$prov_id $desc_id";
		} else {
			$result = '';
		} // endif
		return $result;
	} // function

	/**
	 * Get the event descriptor from a calendar ID
	 * @param	string	$calendar_id
	 * @return	Event_Descriptor
	 */
	public static function get_event_descriptor_for_calendar_id( $calendar_id ) {
		$parts = explode( ' ', $calendar_id, $limit = 2 );
		$prov_id	= isset( $parts[ 0 ] ) ? $parts[ 0 ] : NULL;
		$desc_id	= isset( $parts[ 1 ] ) ? $parts[ 1 ] : NULL;
		$result = Event_Descriptor_Factory::get_event_descriptor_by_id( $desc_id, $prov_id );
		return $result;
	} // function

	/**
	 * Get the calendar used for visitor registration.  This will be created if it does not yet exist.
	 * @return Calendar
	 */
	public static function get_visitor_registration_calendar() {
		if ( ! isset( self::$VISITOR_REG_CALENDAR ) ) {
			
			$id = Settings::get_visitor_registration_calendar_post_id();
			if ( ! empty( $id ) ) {
				$calendar = self::get_calendar_by_post_id( $id );
				if ( ! empty( $calendar ) ) {
					self::$VISITOR_REG_CALENDAR = $calendar;
				} else {
					// The ID is assigned but the actual calendar has been deleted
					// Remove the option and then we'll re-create the calendar below
					Settings::set_visitor_registration_calendar_post_id( NULL );
				} // endif
			} // endif
			
			if ( ! isset( self::$VISITOR_REG_CALENDAR ) ) {
				// There is no Visitor Registration calendar, we need to find or create one
				$all_calendars = self::get_all_calendars();
				$calendar = isset( $all_calendars[ 0 ] ) ? $all_calendars[ 0 ] : self::create_default_calendar();
				if ( isset( $calendar ) ) {
					self::$VISITOR_REG_CALENDAR = $calendar;
					Settings::set_visitor_registration_calendar_post_id( $calendar->get_post_id() );
				} // endif
			} // endif
			
			if ( isset( self::$VISITOR_REG_CALENDAR ) ) {
				// Make sure the type is set so we know this is for a special purpose
				self::$VISITOR_REG_CALENDAR->calendar_type	= self::CALENDAR_TYPE_VISITOR_REG;
			} // endif
			
		} // endif
		
		return self::$VISITOR_REG_CALENDAR;
		
	} // funciton

	/**
	 * Get the calendar used for volunteer registration.
	 * @return Calendar
	 */
	public static function get_volunteer_registration_calendar() {
		if ( ! isset( self::$VOLUNTEER_REG_CALENDAR ) ) {
			
			$id = Settings::get_volunteer_registration_calendar_post_id();
			if ( ! empty( $id ) ) {
				$calendar = self::get_calendar_by_post_id( $id );
				if ( ! empty( $calendar ) ) {
					self::$VOLUNTEER_REG_CALENDAR = $calendar;
				} else {
					// The ID is assigned but the actual calendar has been deleted
					// Remove the option and then we'll re-create the calendar below
					Settings::set_volunteer_registration_calendar_post_id( NULL );
				} // endif
			} // endif
			
			if ( ! isset( self::$VOLUNTEER_REG_CALENDAR ) ) {
				// There is no volunteer registration calendar, we need to find or create one
				$all_calendars = self::get_all_calendars();
				$calendar = isset( $all_calendars[ 0 ] ) ? $all_calendars[ 0 ] : self::create_default_calendar();
				if ( isset( $calendar ) ) {
					self::$VOLUNTEER_REG_CALENDAR = $calendar;
					Settings::set_volunteer_registration_calendar_post_id( $calendar->get_post_id() );
				} // endif
			} // endif
			
			if ( isset( self::$VOLUNTEER_REG_CALENDAR ) ) {
				// Make sure the type is set so we know this is for a special purpose
				self::$VOLUNTEER_REG_CALENDAR->calendar_type	= self::CALENDAR_TYPE_VOLUNTEER_REG;
			} // endif
			
		} // endif
		
		return self::$VOLUNTEER_REG_CALENDAR;
		
	} // funciton

	/**
	 * When no calendars are present in the system we will create one to use for visitor and volunteer registration
	 * @return Calendar
	 */
	private static function create_default_calendar() {

		$title = _x( 'Upcoming Events', 'The title for the default calendar created when none exist', 'reg-man-rc' );

		$post_data_array = array(
				'post_type'		=> self::POST_TYPE,
				'post_title'	=> $title,
				'post_status'	=> 'publish',
				'tax_input'		=> array(
					Event_Category::TAXONOMY_NAME	=> array( Event_Category::get_default_term_id() ),
				),
		);

		$insert_result = wp_insert_post( $post_data_array, $wp_error = TRUE );

		if ( is_wp_error( $insert_result ) ) {
			$msg = __( 'Failure creating default calendar', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $insert_result );
			$result = NULL;
		} else {
			$result = self::get_calendar_by_post_id( $insert_result );
			$result->set_event_category_array( Event_Category::get_all_event_categories() );
		} // endif
		return $result;
	} // function

	/**
	 * Get a single calendar using its calendar ID (i.e. post ID)
	 *
	 * This method will return a single instance of this class describing the calendar specified by the ID.
	 * If the calendar is not found, this method will return NULL
	 *
	 * @param	int|string	$calendar_id	The ID of the calendar to be returned
	 * @return	Calendar|NULL
	 */
	public static function get_calendar_by_post_id( $calendar_id ) {

		$post = get_post( $calendar_id );
		$result = self::instantiate_from_post( $post );

		return $result;

	} // function

	/**
	 * Get a single calendar using its calendar type and ID
	 *
	 * This method will return a single instance of this class describing the calendar specified by the type and ID.
	 * If the calendar is not found, this method will return NULL
	 *
	 * @param	string		$calendar_type	The type of the calendar, one of CALENDAR_TYPE_* constants in this class
	 * @param	int|string	$calendar_id	The ID of the calendar to be returned
	 * @return	Calendar|NULL
	 */
	public static function get_calendar_by_type( $calendar_type, $calendar_id ) {
		switch( $calendar_type ) {

			case self::CALENDAR_TYPE_ADMIN:
				$result = self::get_admin_calendar();
				break;

			case self::CALENDAR_TYPE_VISITOR_REG:
				$result = self::get_visitor_registration_calendar();
				break;

			case self::CALENDAR_TYPE_VOLUNTEER_REG:
				$result = self::get_volunteer_registration_calendar();
				break;

			case self::CALENDAR_TYPE_EVENT_DESCRIPTOR:
				$event_descriptor = self::get_event_descriptor_for_calendar_id( $calendar_id );
				$result = self::get_event_descriptor_calendar( $event_descriptor );
				break;

			default:
				$result = self::get_calendar_by_post_id( $calendar_id );
				break;

		} // endswitch

		return $result;
	} // function

	/**
	 * Get a single calendar using its calendar slug (i.e. post slug)
	 *
	 * This method will return a single instance of this class describing the calendar specified by the slug.
	 * If the calendar is not found, this method will return NULL
	 *
	 * @param	int|string	$calendar_slug	The slug of the calendar to be returned
	 * @return	Calendar|NULL
	 */
	public static function get_calendar_by_slug( $calendar_slug ) {

		$args = array(
				'name'				=> $calendar_slug,
				'post_type'			=> self::POST_TYPE,
				'post_status'		=> 'publish',
				'posts_per_page'	=> 1
		);
		
		$query = new \WP_Query( $args );
		$post_array = $query->posts;
		
		$post = ( is_array( $post_array ) && isset( $post_array[ 0 ] ) ) ? $post_array[ 0 ] : NULL;

		$result = ( $post !== NULL ) ? self::instantiate_from_post( $post ) : NULL;

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;
	} // function

	/**
	 * Get the post associated with this calendar.
	 * @return	\WP_Post		The post for this calendar
	 * @since	v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function
	
	private function get_event_descriptor() {
		return $this->event_descriptor;
	} // function

	/**
	 * Get the post ID of this calendar.
	 * @return	int		The post ID for this calendar
	 * @since	v0.1.0
	 */
	public function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the ID of this calendar.
	 * The ID is normally the post ID except in the case of a special calendar like ADMIN.
	 * @return	int|string		The ID for the calendar
	 * @since	v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the entry type for this calendar as one of the CALENDAR_TYPE_* constants defined in this class.
	 * @return	string		The calendar type for this calendar
	 * @since	v0.1.0
	 */
	public function get_calendar_type() {
		if ( ! isset( $this->calendar_type ) ) {
			$id = $this->get_id();
			switch ( $id ) {

				case self::CALENDAR_TYPE_ADMIN:
				case self::CALENDAR_TYPE_VISITOR_REG:
				case self::CALENDAR_TYPE_VOLUNTEER_REG:
					$this->calendar_type = $id;
					break;

				default:
					$this->calendar_type = self::CALENDAR_TYPE_EVENTS;
					break;

			} // endswitch
		} // endif
		return $this->calendar_type;
	} // function

	/**
	 * Get the name of this calendar.  The name is the post title.
	 * @return	int		The name of the calendar
	 * @since	v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the next $count number of upcoming events for this calendar.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * If $is_exclude_cancelled is TRUE then cancelled events will be excluded from the result, even if they
	 * are normally included on this calendar.
	 * @param	int|NULL	$count	The count of events to return as a positive integer, 0 to return all upcoming events.
	 * @param	boolean		$is_exclude_cancelled	TRUE if cancelled events should be excluded from the result
	 * @return	Event[]		An array of event objects representing the upcoming events, limited to the count specified.
	 *  Note that if there are no events upcoming the array will be empty.
	 */
	public function get_upcoming_calendar_events( $count, $is_exclude_cancelled = FALSE ) {
		$event_filter = Event_Filter::create_for_calendar( $this );
		if ( $is_exclude_cancelled ) {
			$calendar_status_array = $this->get_event_status_array();
			if ( in_array( Event_Status::CANCELLED, $calendar_status_array ) ) {
				$new_status_array = array_diff( $calendar_status_array, array( Event_Status::CANCELLED ) );
				$event_filter->set_accept_statuses( $new_status_array );
			} // endif
		} // endif
		$now = new \DateTime( 'now', wp_timezone() );
		$event_filter->set_accept_minimum_date_time( $now );
		$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
		$event_array = Event::get_all_events_by_filter( $event_filter );
		$count = intval( $count );
		$result = ( $count > 0 ) ? array_splice( $event_array, 0, $count ) : $event_array;
		return $result;
	} // function

	/**
	 * Get an array of Calendar_Entry objects representing regular events on this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime			$start_date_time
	 * @param	\DateTime			$end_date_time
	 * @return	Calendar_Entry[]	An array of Calendar_Entry objects in the specified range
	 */
	 public function get_calendar_entries_in_date_range( $start_date_time, $end_date_time, $event_author_id = 0 ) {
		$result = $this->get_calendar_events_in_date_range( $start_date_time, $end_date_time, $event_author_id );
		return $result;
	} // function

	
	/**
	 * Get an array of Map_Marker objects for this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime			$start_date_time
	 * @param	\DateTime			$end_date_time
	 * @return	Map_Marker[]		An array of Map_Marker objects in the specified range
	 */
	public function get_map_markers_in_date_range( $start_date_time, $end_date_time, $event_author_id = 0 ) {

		$events_array = $this->get_calendar_events_in_date_range( $start_date_time, $end_date_time, $event_author_id );

		// What I have now is an array of events but what I need is an array of event groups so that the map markers
		// for multiple events at the same location are not all stacked on top of each other.
		// Events at one location are grouped together into a single marker.
		
		$group_markers = Event_Group_Map_Marker::create_array_for_events_array( $events_array );

		return array_values( $group_markers );

	} // function

	/**
	 * Get an array of events for this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime	$start_date_time
	 * @param	\DateTime	$end_date_time
	 * @param	int|string	$event_author_id	A WordPress User ID whose events are to be shown
	 * @return	Event[]		An array of event objects in the specified range, with the specified author
	 */
	 private function get_calendar_events_in_date_range( $start_date_time, $end_date_time, $event_author_id = 0 ) {

		if ( ! isset( $start_date_time ) || ! isset( $end_date_time ) ) {

			// One or both of the dates are invalid so return an empty set
			$result = array();

		} else {

			if ( $this->get_calendar_type() === self::CALENDAR_TYPE_EVENT_DESCRIPTOR ) {
				
				$event_descriptor = $this->get_event_descriptor();
				$result = Event::get_events_array_for_event_descriptor( $event_descriptor );
				
			} else {
				
				$event_filter = Event_Filter::create_for_calendar( $this );
				$event_filter->set_accept_minimum_date_time( $start_date_time );
				$event_filter->set_accept_maximum_date_time( $end_date_time );
				if ( ! empty( $event_author_id ) ) {
					$event_filter->set_accept_event_author_id( $event_author_id );
				} // endif
				$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );

				// If this is the admin calendar then we need to add in placeholder events for registrations
				//  whose original event objects do not exist
				$calendar_type = $this->get_calendar_type();
				$is_include_placeholder_events = ( $calendar_type == self::CALENDAR_TYPE_ADMIN );
				
				$result = Event::get_all_events_by_filter( $event_filter, $is_include_placeholder_events );

			} // endif

		} // endif

		return $result;

	} // function

	/**
	 * Get the default array of views to be shown when no specific set of views is defined for the calendar.
	 * @return	string[]	The viewss to be shown on a calendar by default, e.g. array( Calendar_View_Format::GRID_VIEW )
	 * @since	v0.1.0
	 */
	public static function get_default_view_format_ids_array() {
		$result = array(
				Calendar_View_Format::GRID_VIEW,
				Calendar_View_Format::LIST_VIEW,
				Calendar_View_Format::MAP_VIEW,
		);
		return $result;
	} // function

	/**
	 * Get the array of view format IDs to be shown on this calendar.
	 * View format ID values are defined as constants in the Calendar_View_Format, e.g. Calendar_View_Format::GRID_VIEW.
	 * @return	string[]	The array of view constant strings to be shown on this calendar,
	 *  e.g. array( Calendar_View_Format::GRID_VIEW, Calendar_View_Format::MAP_VIEW )
	 * @since	v0.1.0
	 */
	public function get_view_format_ids_array() {
		if ( ! isset( $this->view_format_ids_array ) ) {
			$post_id = $this->get_post_id();
			$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$VIEWS_META_KEY, $single = TRUE ) : NULL;
			$format_ids_array = ! empty( $meta ) ? $meta : self::get_default_view_format_ids_array();
			$is_maps_active = Map_View::get_is_map_view_enabled();
			if ( in_array( Calendar_View_Format::MAP_VIEW, $format_ids_array ) && ! $is_maps_active ) {
				$format_ids_array = array_values( array_diff( $format_ids_array, array( Calendar_View_Format::MAP_VIEW ) ) );
//				Error_Log::var_dump( $format_ids_array );
			} // endif
			$this->view_format_ids_array = $format_ids_array;
		} // endif
		return $this->view_format_ids_array;
	} // function

	/**
	 * Set the array of view format IDs to be shown on this calendar.
	 * @param	string|string[]		$view_format_ids_array		The new array of view format IDs to be shown on this calendar.
	 * View format ID values are defined as constants in the Calendar_View_Format, e.g. Calendar_View_Format::GRID_VIEW.
	 * For convenience the caller may pass a single value rather than an array.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_view_format_ids_array( $view_format_ids_array ) {
		if ( ! is_array( $view_format_ids_array ) ) {
			$view_format_ids_array = array( $view_format_ids_array ); // convert single value to an array for processing
		} // endif
		$all_views = Calendar_View_Format::get_all_calendar_view_formats();
		$new_views = array();
		foreach ( $view_format_ids_array as $view_id ) {
			if ( array_key_exists( $view_id, $all_views ) ) {
				$new_views[] = $view_id;
			} // endif
		} // endif
		if ( empty( $new_views ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::$VIEWS_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$VIEWS_META_KEY, $new_views );
		} // endif
		unset( $this->view_format_ids_array ); // allow it to be re-acquired
	} // function

	/**
	 * Get the default array of durations to be shown when no specific set is defined for the calendar.
	 * @return	string[]	The durations to be shown on a calendar by default, e.g. array( Calendar_Duration::ONE_MONTH )
	 * @since	v0.1.0
	 */
	public static function get_default_duration_ids_array() {
		$result = array(
				Calendar_Duration::ONE_MONTH,
				Calendar_Duration::CALENDAR_YEAR,
		);
		return $result;
	} // function
	
	
	/**
	 * Get the default array of views to be shown on the admin calendar when no specific set of views is defined.
	 * @return	string[]	The views to be shown on the admin calendar by default.
	 * @since	v0.1.0
	 */
	public static function get_default_admin_calendar_view_format_ids_array() {
		$result = array();
		$result[] = Calendar_View_Format::GRID_VIEW; // Always show the month view
		$result[] = Calendar_View_Format::LIST_VIEW; // Add month list view
		if ( Map_View::get_is_map_view_enabled() ) {
			$result[] = Calendar_View_Format::MAP_VIEW; // Show a map if we have an API key
		} // endif
		return $result;
	} // function
	
	/**
	 * Get the default array of durations to be shown on the admin when no specific set is defined.
	 * @return	string[]	The durations to be shown on the admin calendar by default.
	 * @since	v0.1.0
	 */
	public static function get_default_admin_calendar_duration_ids_array() {
		$result = array(
				Calendar_Duration::ONE_MONTH,
//				Calendar_Duration::TWO_MONTHS,
//				Calendar_Duration::THREE_MONTHS,
//				Calendar_Duration::SIX_MONTHS,
				Calendar_Duration::CALENDAR_YEAR,
		);
		return $result;
	} // function
	
	/**
	 * Get the array of duration IDs to be shown on this calendar.
	 * Duration ID values are defined as constants in the Calendar_Duration class, e.g. Calendar_Duration::ONE_MONTH.
	 * @return	string[]	The array of duration constant strings to be shown on this calendar,
	 *  e.g. array( Calendar_Duration::ONE_MONTH, Calendar_Duration::THEE_MONTHS )
	 * @since	v0.1.0
	 */
	public function get_duration_ids_array() {
		if ( ! isset( $this->duration_ids_array ) ) {
			$post_id = $this->get_post_id();
			$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$DURATIONS_META_KEY, $single = TRUE ) : NULL;
			$this->duration_ids_array = ! empty( $meta ) ? $meta : self::get_default_duration_ids_array();
		} // endif
		return $this->duration_ids_array;
	} // function
	
	/**
	 * Set the array of duration IDs to be shown on this calendar.
	 * @param	string|string[]		$duration_ids_array		The new array of duration IDs to be shown on this calendar.
	 * Duration ID values are defined as constants in the Calendar_Duration, e.g. Calendar_Duration::ONE_MONTH.
	 * For convenience the caller may pass a single value rather than an array.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_duration_ids_array( $duration_ids_array ) {
		if ( ! is_array( $duration_ids_array ) ) {
			$duration_ids_array = array( $duration_ids_array ); // convert single value to an array for processing
		} // endif
		$all_durations = Calendar_Duration::get_all_calendar_durations();
		$new_durations = array();
		foreach ( $duration_ids_array as $duration_id ) {
			if ( array_key_exists( $duration_id, $all_durations ) ) {
				$new_durations[] = $duration_id;
			} // endif
		} // endif
		if ( empty( $new_durations ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::$DURATIONS_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$DURATIONS_META_KEY, $new_durations );
		} // endif
		unset( $this->duration_ids_array ); // allow it to be re-acquired
	} // function
	
	/**
	 * Get the minimum width in pixels for a month when showing a multi-month view like Calendar Year 
	 * @return int
	 */
	public function get_multi_month_min_width() {
		
		if ( ! isset( $this->multi_month_min_width ) ) {

			// TODO: We could provide ways for the user to configure this
			// For now, we just assign it based on the calendar type
			$calendar_type = $this->get_calendar_type();
			
			switch( $calendar_type ) {
				
				case Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR:
					$this->multi_month_min_width = 280;
					break;
					
				default:
					$this->multi_month_min_width = 360;
					break;

			} // endswitch
			
		} // endif
		
		return $this->multi_month_min_width;

	} // function
	
	/**
	 * Get the array of classes to be shown in the volunteer registration calendar.
	 * @return	string[]	The event classes to be shown on the volunteer registration calendar
	 * @since	v0.1.0
	 */
	private static function get_volunteer_registration_class_array() {
		$result = array( Event_Class::PUBLIC );
		return $result;
	} // function

	/**
	 * Get the array of classes to be shown on this calendar.  Class values are defined as constants in the Event_Class class.
	 * @return	string[]	The event classes to be shown on this calendar, e.g. array( Event_Class::PUBLIC )
	 * @since	v0.1.0
	 */
	public function get_event_class_array() {
		if ( ! isset( $this->event_class_array ) ) {
			$this->event_class_array = self::get_default_class_array();
		} // endif
		return $this->event_class_array;
	} // function

	/**
	 * Get the default array of classes to be shown for the calendar.
	 * By default a calendar will always show PUBLIC events, and not PRIVATE or CONFIDENTIAL.
	 * @return	string[]	The event classes to be shown on a calendar by default, e.g. array( Event_Class::PUBLIC )
	 * @since	v0.1.0
	 */
	public static function get_default_class_array() {
		$result = array( Event_Class::PUBLIC );
		return $result;
	} // function

	/**
	 * Get the default array of statuses to be shown when no specific set of statuses is defined for the calendar.
	 * By default a calendar will only show confirmed events.
	 * @return	string[]	The event statuses to be shown on a calendar by default, e.g. array( Event_Status::CONFIRMED )
	 * @since	v0.1.0
	 */
	public static function get_default_status_array() {
		$result = array( Event_Status::CONFIRMED, Event_Status::CANCELLED );
		return $result;
	} // function

	/**
	 * Get the array of statuses to be shown on the volunteer registration calendar
	 * @return	string[]	The event statuses to be shown on the volunteer registration calendar
	 * @since	v0.1.0
	 */
	private static function get_volunteer_registration_status_array() {
		$result = array( Event_Status::CONFIRMED, Event_Status::CANCELLED );
		return $result;
	} // function

	/**
	 * Get the name of the iCalendar feed for this calendar or an empty string if there is no feed
	 * @return	string	The name of the iCalendar feed for this calendar or empty string if there is no feed
	 * @since	v0.7.0
	 */
	public function get_icalendar_feed_name() {
		if ( ! isset( $this->icalendar_feed_name ) ) {
			switch( $this->get_calendar_type() ) {

				case self::CALENDAR_TYPE_EVENTS:
					$post_id = $this->get_post_id();
					$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$ICAL_FEED_META_KEY, $single = TRUE ) : NULL;
					$this->icalendar_feed_name = ! empty( $meta ) ? $meta : '';
					break;
					
				case self::CALENDAR_TYPE_VOLUNTEER_REG:
					$this->icalendar_feed_name = Settings::get_volunteer_calendar_feed_name();
					break;
					
				default:
					$this->icalendar_feed_name = '';
					break;
					
			} // endswitch
		} // endif
		return $this->icalendar_feed_name;
	} // function
	
	/**
	 * Set the iCalendar feed name 
	 * @param	string	$icalendar_feed_name	The name of the iCalendar feed for this calendar, or any empty value if the calendar has no feed.
	 * @return	void
	 * @since	v0.7.0
	 */
	public function set_icalendar_feed_name( $icalendar_feed_name ) {
		$icalendar_feed_name = is_string( $icalendar_feed_name ) ? trim( $icalendar_feed_name ) : '';
		if ( empty( $icalendar_feed_name ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::$ICAL_FEED_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$ICAL_FEED_META_KEY, $icalendar_feed_name );
		} // endif
		unset( $this->icalendar_feed_name ); // allow it to be re-acquired
	} // function

	/**
	 * Get a boolean flag indicating whether a "Subscribe" button should be shown for the iCalendar feed
	 *  of this calendar when it appears on a public page
	 * @return	boolean	TRUE if a subscribe button should be shown, FALSE otherwise
	 * @since	v0.7.0
	 */
	public function get_icalendar_is_show_subscribe_button() {
		if ( ! isset( $this->icalendar_is_show_subscribe_button ) ) {
			switch ( $this->get_calendar_type() ) {
				
				case self::CALENDAR_TYPE_EVENTS:
					$post_id = $this->get_post_id();
					$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$ICAL_FEED_SUBSCRIBE_BUTTON_META_KEY, $single = TRUE ) : NULL;
					// We will assume this should be shown by default, so missing meta or anything other than FALSE means TRUE
					$this->icalendar_is_show_subscribe_button = ! empty( $meta ) ? ( $meta !== 'FALSE' ) : TRUE;
					break;

				case self::CALENDAR_TYPE_VOLUNTEER_REG:
					$this->icalendar_is_show_subscribe_button = Settings::get_is_create_volunteer_calendar_feed();
					break;
					
				default:
					$this->icalendar_is_show_subscribe_button = FALSE;
					break;
					
			} // endswitch
		} // endif
		return $this->icalendar_is_show_subscribe_button;
	} // function
	
	/**
	 * Set the flag indicating whether a "Subscribe" button should be shown for the iCalendar feed
	 *  of this calendar when it appears on a public page
	 * @param	boolean	$is_show_subscribe_button	TRUE when the button should be shown, FALSE otherwise
	 * @return	void
	 * @since	v0.7.0
	 */
	public function set_icalendar_is_show_subscribe_button( $is_show_subscribe_button ) {
		if ( $is_show_subscribe_button ) {
			// We will assume this should be shown by default
			delete_post_meta( $this->get_post_id(), self::$ICAL_FEED_SUBSCRIBE_BUTTON_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$ICAL_FEED_SUBSCRIBE_BUTTON_META_KEY, 'FALSE' );
		} // endif
		unset( $this->icalendar_is_show_subscribe_button ); // allow it to be re-acquired
	} // function

	/**
	 * Get the title used in the iCalendar feed for this calendar
	 * @return	string	The title of the iCalendar feed
	 * @since	v0.7.0
	 */
	public function get_icalendar_feed_title() {
		if ( ! isset( $this->icalendar_feed_title ) ) {
			$blog_name = get_bloginfo( 'name' );
			$calendar_name = strip_tags( $this->get_name() ); // remove any tags from the name
			/* Translators: %1$s is the blog name, %2$s is a calendar title */
			$feed_title_format = _x( '%1$s %2$s', 'An iCalendar feed title using the blog name and calendar title, e.g. "Repair Café Toronto Upcoming Events"', 'reg-man-rc' );
			$this->icalendar_feed_title = sprintf( $feed_title_format, $blog_name, $calendar_name );
		} // endif
		return $this->icalendar_feed_title;
	} // function
	
	/**
	 * Get the URL for the iCalendar feed for this calendar
	 * @return	string	The URL of the iCalendar feed
	 * @since	v0.7.0
	 */
	public function get_icalendar_feed_url() {
		if ( ! isset( $this->icalendar_feed_url ) ) {
			$feed_name = $this->get_icalendar_feed_name();
			$blog_id = NULL; // I believe this is used for multi-site
			$path = 'feed/' . $feed_name;
			$this->icalendar_feed_url = get_site_url( $blog_id, $path );
		} // endif
		return $this->icalendar_feed_url;
	} // function
	
	/**
	 * Get the file name for the iCalendar feed for this calendar
	 * @return	string	The file name of the iCalendar feed
	 * @since	v0.7.0
	 */
	public function get_icalendar_feed_file_name() {
		if ( ! isset( $this->icalendar_feed_file_name ) ) {
			$feed_title = $this->get_icalendar_feed_title(); // The feed title includes the blog name
			$date_time = new \DateTime();
			$date_time->setTimezone( wp_timezone() );
			$date = $date_time->format( 'Y-m-d' );
			$this->icalendar_feed_file_name = sanitize_file_name( "$feed_title {$date}.ics" );
		} // endif
		return $this->icalendar_feed_file_name;
	} // function
	
	/**
	 * Get the array of statuses to be shown on this calendar.  Status values are defined as constants in the Event_Status class.
	 * @return	string[]	The event statuses to be shown on this calendar, e.g. array( Event_Status::CONFIRMED, Event_Status::TENTATIVE )
	 * @since	v0.1.0
	 */
	public function get_event_status_array() {
		if ( !isset( $this->event_status_array ) ) {
			$post_id = $this->get_post_id();
			$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$EVENT_STATUSES_META_KEY, $single = TRUE ) : NULL;
			$this->event_status_array = ! empty( $meta ) ? $meta : self::get_default_status_array();
		} // endif
		return $this->event_status_array;
	} // function

	/**
	 * Set the array of event statuses to be shown on this calendar.
	 * @param	string|string[]		$event_status_array		The new array of statuses to be shown on this calendar.
	 * Status values are defined as constants in the Event_Status class, e.g. array( Event_Status::CONFIRMED, Event_Status::TENTATIVE ).
	 * For convenience the caller may pass a single value rather than an array.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_event_status_array( $event_status_array ) {
		if ( ! is_array( $event_status_array ) ) {
			$event_status_array = array( $event_status_array ); // convert single value to an array for processing
		} // endif
		$all_statuses = Event_Status::get_all_event_statuses();
		$new_statuses = array();
		foreach ( $event_status_array as $status_const ) {
			if ( array_key_exists( $status_const, $all_statuses ) ) {
				$new_statuses[] = $status_const;
			} // endif
		} // endif
		if ( empty( $new_statuses ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::$EVENT_STATUSES_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$EVENT_STATUSES_META_KEY, $new_statuses );
		} // endif
		unset( $this->event_status_array ); // allow it to be re-acquired
	} // function

	/**
	 * Get array of event categories to be shown on this calendar.
	 * @return	Event_Category[]	An array of Event_Category objects specifying the event categories to be shown on this calendar.
	 * This may be an empty array if there are no categories associated with the calendar.
	 * @since	v0.1.0
	 */
	public function get_event_category_array() {
		if ( ! isset( $this->event_category_array ) ) {
			$post_id = $this->get_post_id();
			$post_cats = ! empty( $post_id ) ? Event_Category::get_event_categories_for_post( $post_id ) : array();
			if ( ! empty( $post_cats ) ) {
				$this->event_category_array = $post_cats;
			} else {
				$default_cat = Event_Category::get_default_event_category();
				$this->event_category_array = ! empty( $default_cat ) ? array( $default_cat ) : array();
			} // endif
		} // endif
		return $this->event_category_array;
	} // function

	/**
	 * Set the event categories for this calendar
	 *
	 * @param	Event_Category[]	$event_category_array	An array of categories to be shown for this calendar
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_event_category_array( $event_category_array ) {
		$post_id = $this->get_post_id();
		// We can only set the event categories when we have a valid post ID
		if ( ! empty( $post_id ) ) {
			if ( ! is_array( $event_category_array ) || ( empty( $event_category_array ) ) ) {
				// If the new category array is NULL or empty then that means to unset or remove the current setting
				wp_delete_object_term_relationships( $post_id, Event_Category::TAXONOMY_NAME );
			} else {
				$category_id_array = array();
				foreach ( $event_category_array as $category ) {
					if ( $category instanceof Event_Category ) {
						$category_id_array[] = intval( $category->get_id() );
					} // endif
				} // endfor
				wp_set_post_terms( $post_id, $category_id_array, Event_Category::TAXONOMY_NAME );
			} // endif
			$this->event_category_array = NULL; // reset my internal vars so they can be re-acquired
		} // endif
	} // function
	
	/**
	 * Add an event category to this calendar
	 * @param	Event_Category	$event_category
	 */
	public function add_event_category( $event_category ) {
		$post_id = $this->get_post_id();
		$event_category_id = $event_category->get_id();
		wp_add_object_terms( $post_id, $event_category_id, Event_Category::TAXONOMY_NAME );
		$this->event_category_array = NULL; // reset my internal vars so they can be re-acquired
	} // function

	/**
	 * Remove an event category from this calendar
	 * @param	Event_Category	$event_category
	 */
	public function remove_event_category( $event_category ) {
		$post_id = $this->get_post_id();
		$event_category_id = $event_category->get_id();
		wp_remove_object_terms( $post_id, $event_category_id, Event_Category::TAXONOMY_NAME );
		$this->event_category_array = NULL; // reset my internal vars so they can be re-acquired
	} // function

	/**
	 * Get array of names of the event categories to be shown on this calendar.
	 * @return	string[]	An array of names for the event categories to be shown on this calendar.
	 * This may be an empty array if there are no categories associated with the calendar.
	 * @since	v0.1.0
	 */
	private function get_event_category_names_array() {
		if ( ! isset( $this->event_category_names_array ) ) {
			$this->event_category_names_array = array();
			$categories = $this->get_event_category_array();
			foreach( $categories as $category ) {
				$this->event_category_names_array[] = $category->get_name();
			} // endfor
		} // endif
		return $this->event_category_names_array;
	} // function
	
	
	/**
	 * Get a flag indicating whether past events are to be shown on this calendar.
	 * @return	boolean		A flag set to TRUE if past events are to be shown on the calendar.
	 * @since	v0.1.0
	 */
	public function get_is_show_past_events() {
		if ( ! isset( $this->is_show_past_events ) ) {
			$post_id = $this->get_post_id();
			$meta = ! empty( $post_id ) ? get_post_meta( $post_id, self::$IS_SHOW_PAST_EVENTS_META_KEY, $single = TRUE ) : NULL;
			$this->is_show_past_events = ( $meta !== '0' ); // FALSE if the meta is '0', TRUE otherwise
//			Error_Log::var_dump( $meta, $this->is_show_past_events );
		} // endif
		return $this->is_show_past_events;
	} // function

	/**
	 * Set a flag indicating whether past events are to be shown on this calendar.
	 * @param	boolean	$is_show_past_events	A flag set to TRUE if past events are to be shown on the calendar.
	 * @since	v0.1.0
	 */
	public function set_is_show_past_events( $is_show_past_events ) {
//		Error_Log::var_dump( $is_show_past_events );
		if ( $is_show_past_events ) {
			// This meta value is only present to indicate the negative, that past events ARE NOT to be shown
			// When the argument is TRUE (the default) we'll remove the meta data
			delete_post_meta( $this->get_post_id(), self::$IS_SHOW_PAST_EVENTS_META_KEY );
		} else {
			// Update will add the meta data if it does not exist
			update_post_meta( $this->get_post_id(), self::$IS_SHOW_PAST_EVENTS_META_KEY, '0' );
		} // endif
		unset( $this->is_show_past_events ); // allow it to be re-acquired
	} // function

	/**
	 * Get a boolean indicating whether this calendar will display the specified event descriptor.
	 * This requires checking the event descriptor's categories, status and class to make sure they are all valid for this calendar.
	 * @param	Event_Descriptor	$event_descriptor
	 * @return	boolean	TRUE if the event descriptor appears on this calendar, FALSE otherwise
	 */
	public function get_is_event_descriptor_contained_in_calendar( $event_descriptor ) {
		if ( ! isset( $event_descriptor ) ) {
			$result = FALSE; // Defensive
		} else {
			$event_category_names = $event_descriptor->get_event_categories();
			$event_status = $event_descriptor->get_event_status();
			$event_class = $event_descriptor->get_event_class();
			$calendar_category_names_array = $this->get_event_category_names_array();
			$calendar_status_array = $this->get_event_status_array();
			$calendar_class_array = $this->get_event_class_array();
			$result = 
				! empty( array_intersect( $event_category_names, $calendar_category_names_array ) ) &&
				in_array( $event_status->get_id(), $calendar_status_array ) &&
				in_array( $event_class->get_id(), $calendar_class_array );
		} // endif
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether this calendar contains the specified event.
	 * This requires checking the event's categories, status and class to make sure they are all valid for this calendar.
	 * @param	Event	$event
	 * @return	boolean	TRUE if the event appears on this calendar, FALSE otherwise
	 */
	public function get_is_event_contained_in_calendar( $event ) {
		if ( ! isset( $event ) ) {
			$result = FALSE; // Defensive
		} else {
			$result = $this->get_is_event_descriptor_contained_in_calendar( $event->get_event_descriptor() );
		} // endif
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether a logged in user is required in order to access the FullCalendar
	 *  event feed for this calendar
	 * @return boolean
	 */
	public function get_is_login_required_for_fullcalendar_feed() {
		if ( ! isset( $this->is_login_required_for_fullcalendar_feed ) ) {
			
			switch( $this->get_calendar_type() ) {
				
				case Calendar::CALENDAR_TYPE_EVENTS:
					$post = $this->get_post();
					// If the calendar is publicly visible then the feed is too, otherwise it requires a user
					$is_public = isset( $post ) ? ( $post->post_status === 'publish' ) : FALSE;
					$this->is_login_required_for_fullcalendar_feed = ! $is_public;
					break;

				case Calendar::CALENDAR_TYPE_ADMIN:
				case Calendar::CALENDAR_TYPE_VISITOR_REG:
				case Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR:
					$this->is_login_required_for_fullcalendar_feed = TRUE;
					break;
				
				case Calendar::CALENDAR_TYPE_VOLUNTEER_REG:
					// If the configuration requires a logged in user then make sure we have one,
					//  otherwise we need to make this feed accessible to non-logged in people too
					$user_required = Settings::get_is_require_volunteer_area_registered_user();
					$this->is_login_required_for_fullcalendar_feed = $user_required;
					break;
					
				default:
					// Defensive, there currently are no other options
					$this->is_login_required_for_fullcalendar_feed = TRUE;
					break;
					
			} // endswitch
			
		} // endif
		return $this->is_login_required_for_fullcalendar_feed;
	} // function
	
	/**
	 * Get a boolean indicating whether a nonce is required in order to access the event feed for this calendar
	 * @return boolean
	 */
	public function get_is_nonce_required_for_fullcalendar_feed() {
		if ( ! isset( $this->is_nonce_required_for_fullcalendar_feed ) ) {
			
			switch( $this->get_calendar_type() ) {
				
				case Calendar::CALENDAR_TYPE_EVENTS:
					$post = $this->get_post();
					// If the calendar is publicly visible then the feed is too, otherwise it requires a user
					$is_public = isset( $post ) ? ( $post->post_status === 'publish' ) : FALSE;
					$this->is_nonce_required_for_fullcalendar_feed = ! $is_public;
					break;

				case Calendar::CALENDAR_TYPE_ADMIN:
				case Calendar::CALENDAR_TYPE_VISITOR_REG:
				case Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR:
					$this->is_nonce_required_for_fullcalendar_feed = TRUE;
					break;
				
				case Calendar::CALENDAR_TYPE_VOLUNTEER_REG:
					// We should always use a nonce to access the volunteer reg calendar, whether there's a user or not
					// We do not want to allow anybody with a browser and the right URL to see this feed,
					//  we want to make sure the user is viewing the calendar on the Volunteer Area page
					$this->is_nonce_required_for_fullcalendar_feed = TRUE;
					break;
					
				default:
					// Defensive, there currently are no other options
					$this->is_nonce_required_for_fullcalendar_feed = TRUE;
					break;
					
			} // endswitch
			
		} // endif
		return $this->is_nonce_required_for_fullcalendar_feed;
	} // function
	
	/**
	 *  Register the Calendar custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {
		$labels = array(
				'name'					=> _x( 'Calendars', 'Calendar post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Calendar', 'Calendar post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Calendar' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Calendar', 'reg-man-rc'),
				'new_item'				=> __( 'New Calendar', 'reg-man-rc'),
				'all_items'				=> __( 'Calendars', 'reg-man-rc'),
				'view_item'				=> __( 'View Calendar', 'reg-man-rc'),
				'search_items'			=> __( 'Search Calendars', 'reg-man-rc'),
				'not_found'				=> __( 'No items found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'No items found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __('Calendars', 'reg-man-rc')
		);
		$capability_singular = User_Role_Controller::CALENDAR_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::CALENDAR_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Event Calendar', // Internal description, not visible externally
				'public'				=> FALSE, // is it for public use?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // Does it have a public page, is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> TRUE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> Admin_Menu_Page::get_menu_position(), // Menu order position
				'menu_icon'				=> 'dashicons-calendar',
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> array( 'title' ),
				'taxonomies'			=> array( Event_Category::TAXONOMY_NAME ),
				'has_archive'			=> FALSE, // is there an archive page?
				// Rewrite determines how the public page url will look
				'rewrite'				=> array(
					'slug'			=> Settings::get_calendars_slug(),
					'with_front'	=> FALSE,
				),
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		register_post_type( self::POST_TYPE, $args );

		// Note that the visitor and volunteer registration calendars are initialized by the plugin controller
		// This is to ensure that both the CPTs and taxonomies are set up before we create any instances

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