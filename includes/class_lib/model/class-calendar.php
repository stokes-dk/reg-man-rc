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

	const CALENDAR_TYPE_EVENTS			= 'events-calendar';
	const CALENDAR_TYPE_VISITOR_REG		= 'visitor-reg-calendar';
	const CALENDAR_TYPE_VOLUNTEER_REG	= 'volunteer-reg-calendar';
	const CALENDAR_TYPE_ADMIN_EVENTS	= 'admin-events-calendar';

	private static $ADMIN_CALENDAR; // An instance of this class that contains all events
	private static $VISITOR_REG_CALENDAR; // An instance of this class used for visitor registration
	private static $VOLUNTEER_REG_CALENDAR; // An instance of this class used for volunteer registration

	// Option keys to store the post ID of special calendars in the db
//	private static $VISITOR_REG_CALENDAR_OPTION_KEY				= self::POST_TYPE . '-visitor-reg-cal-id';
//	private static $VOLUNTEER_REG_CALENDAR_OPTION_KEY			= self::POST_TYPE . '-volunteer-reg-cal-id';

	// Meta keys used to store calendar specific settings
	private static $EVENT_STATUSES_META_KEY						= self::POST_TYPE . '-event-statuses';
	// Note that categories are a taxonomy so we don't store as metadata, they're acquired through get_terms()
//	private static $SHOW_UNCATEGORIZED_META_KEY					= self::POST_TYPE . '-show-uncategorized';
	private static $VIEWS_META_KEY								= self::POST_TYPE . '-views';
	private static $IS_SHOW_PAST_EVENTS_META_KEY				= self::POST_TYPE . '-is-show-past-events';

	private $post;
	private $id;
	private $post_id; // Normally the id and post id are the same, except for special calendars like admin or visitor reg
	private $calendar_type;
	private $name;
	private $event_class_array;
	private $event_status_array;
	private $event_category_array;
//	private $show_uncategorized;
	private $view_format_ids_array;
	private $is_show_past_events;
	private $is_show_past_events_toggle_button;

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
			$calendar = self::instantiate_from_post( $post );
			if ( $calendar !== NULL ) {
				$result[] = $calendar;
			} // endif
		} // endfor
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
			$statuses = self::get_visible_statuses();
			$cat_id = $event_category->get_id();
			$post_array = get_posts( array(
							'post_type'				=> self::POST_TYPE,
							'post_status'			=> $statuses,
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
			) );
			foreach ( $post_array as $post ) {
				$calendar = self::instantiate_from_post( $post );
				if ( $calendar !== NULL ) {
					$result[] = $calendar;
				} // endif
			} // endfor
		} // endif
		return $result;
	} // function

	/**
	 * Get an instance of this class that shows all events.  For administrative purposes.
	 */
	public static function get_admin_calendar() {
		if ( ! isset( self::$ADMIN_CALENDAR ) ) {
			$capability = 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
			if ( current_user_can( $capability ) ) {
				$result = new self();
				$result->id = self::CALENDAR_TYPE_ADMIN_EVENTS; // we use the type name as the ID
				$result->calendar_type = self::CALENDAR_TYPE_ADMIN_EVENTS;
				$result->post_id = 0;
				$result->name = __( 'Administrative Calendar', 'reg-man-rc' );
				$result->description =  __( 'A calendar showing all events for administrative purposes', 'reg-man-rc' );
				$result->event_class_array = array( Event_Class::PUBLIC, Event_Class::PRIVATE, Event_Class::CONFIDENTIAL );
				$result->event_status_array = array_keys( Event_Status::get_all_event_statuses() );
				$result->event_category_array = Event_Category::get_all_event_categories();
				$result->view_format_ids_array = array_keys( Calendar_View_Format::get_all_calendar_view_formats() );
				self::$ADMIN_CALENDAR = $result;
			} else {
				// This should never happen but just return something instead of nothing
				self::$ADMIN_CALENDAR = self::get_visitor_registration_calendar();
			} // endif
		} // endif
		return self::$ADMIN_CALENDAR;
	} // function

	/**
	 * Get the calendar used for visitor registration.  This will be created if it does not yet exist.
	 * @return Calendar
	 */
	public static function get_visitor_registration_calendar() {
		if ( ! isset( self::$VISITOR_REG_CALENDAR ) ) {
			$id = Settings::get_visitor_registration_calendar_post_id();
			if ( ! empty( $id ) ) {
				$calendar = self::get_calendar_by_id( $id );
				if ( ! empty( $calendar ) ) {
					self::$VISITOR_REG_CALENDAR = $calendar;
				} else {
					// The ID is assigned but the actual calendar has been deleted
					// Remove the option and then we'll re-create the calendar below
					Settings::set_visitor_registration_calendar_post_id( NULL );
				} // endif
			} // endif
			if ( ! isset( self::$VISITOR_REG_CALENDAR ) ) {
				$calendar = self::create_visitor_registration_calendar();
				if ( isset( $calendar ) ) {
					self::$VISITOR_REG_CALENDAR = $calendar;
					Settings::set_visitor_registration_calendar_post_id( $calendar->get_post_id() );
				} // endif
			} // endif
			if ( isset( self::$VISITOR_REG_CALENDAR ) ) {
				// Make sure the ID and type are set so we know this is for a special purpose
				self::$VISITOR_REG_CALENDAR->id				= self::CALENDAR_TYPE_VISITOR_REG;
				self::$VISITOR_REG_CALENDAR->calendar_type	= self::CALENDAR_TYPE_VISITOR_REG;
				// A map view for visitor registration doesn't make much sense
				self::$VISITOR_REG_CALENDAR->remove_map_view_format();
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
				$calendar = self::get_calendar_by_id( $id );
//				Error_Log::var_dump( $calendar );
				if ( ! empty( $calendar ) ) {
					self::$VOLUNTEER_REG_CALENDAR = $calendar;
				} else {
					// The ID is assigned but the actual calendar has been deleted
					// Remove the option and then we'll re-create the calendar below
					Settings::set_volunteer_registration_calendar_post_id( NULL );
				} // endif
			} // endif
			if ( ! isset( self::$VOLUNTEER_REG_CALENDAR ) ) {
				// There is no volunteer registration calendar so we'll use the visitor reg calendar for both
				$calendar = self::get_visitor_registration_calendar();
				if ( isset( $calendar ) ) {
					self::$VOLUNTEER_REG_CALENDAR = $calendar;
					Settings::set_volunteer_registration_calendar_post_id( $calendar->get_post_id() );
				} // endif
			} // endif
			if ( isset( self::$VOLUNTEER_REG_CALENDAR ) ) {
				// Make sure the ID and type are set so we know this is for a special purpose
				self::$VOLUNTEER_REG_CALENDAR->id				= self::CALENDAR_TYPE_VOLUNTEER_REG;
				self::$VOLUNTEER_REG_CALENDAR->calendar_type	= self::CALENDAR_TYPE_VOLUNTEER_REG;
			} // endif
		} // endif
		return self::$VOLUNTEER_REG_CALENDAR;
	} // funciton

	private static function create_visitor_registration_calendar() {

		$title = _x( 'Upcoming Events', 'The title for the calendar showing events visitors can bring items for repair', 'reg-man-rc' );

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
			$msg = __( 'Failure creating visitor registration calendar', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $insert_result );
			$result = NULL;
		} else {
			$result = self::get_calendar_by_id( $insert_result );
			$result->set_event_category_array( array( Event_Category::get_default_event_category() ) );
		} // endif
		return $result;
	} // function

	/**
	 * Get an array of post statuses that indicates what is visible to the current user.
	 * @param boolean	$is_look_in_trash	A flag set to TRUE if posts in trash should be visible.
	 * @return string[]
	 */
	private static function get_visible_statuses( $is_look_in_trash = FALSE ) {
		$capability = 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
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
	 * Get a single calendar using its calendar ID (i.e. post ID)
	 *
	 * This method will return a single instance of this class describing the calendar specified by the ID.
	 * If the calendar is not found, this method will return NULL
	 *
	 * @param	int|string	$calendar_id	The ID of the calendar to be returned
	 * @return	Calendar|NULL
	 */
	public static function get_calendar_by_id( $calendar_id ) {
		switch( $calendar_id ) {

			case self::CALENDAR_TYPE_ADMIN_EVENTS:
				$result = self::get_admin_calendar();
				break;

			case self::CALENDAR_TYPE_VISITOR_REG:
				$result = self::get_visitor_registration_calendar();
				break;

			case self::CALENDAR_TYPE_VOLUNTEER_REG:
				$result = self::get_volunteer_registration_calendar();
				break;

			default:
				$visible_statuses = array( 'publish' ); // Only return published calendars here
				$post = get_post( $calendar_id );
				if ( ( $post !== NULL ) && ( in_array( $post->post_status, $visible_statuses ) ) ) {
					$post_type = $post->post_type; // make sure that the given post is the right type, there's no reason it shouldn't be
					if ( $post_type == self::POST_TYPE ) {
						$result = self::instantiate_from_post( $post );
					} else {
						$result = NULL;
					} // endif
				} else {
					$result = NULL;
				} // endif
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
		$post_array = get_posts( $args );
		$post = ( is_array( $post_array ) && isset( $post_array[ 0 ] ) ) ? $post_array[ 0 ] : NULL;
		if ( $post !== NULL ) {
			$result = self::instantiate_from_post( $post );
		} else {
			$result = NULL;
		} // endif
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

				case self::CALENDAR_TYPE_ADMIN_EVENTS:
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
	 * Get an array of all events for this calendar.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @return	Event[]		An array event objects representing all the events on this calendar.
	 */
	public function get_all_calendar_events() {
		$event_filter = Event_Filter::create_for_calendar( $this );
		$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_DESCENDING );
		$result = Event::get_all_events_by_filter( $event_filter );
		return $result;
	} // function

	/**
	 * Get the next upcoming event for this calendar.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @return	Event|NULL	An event object representing the next upcoming event or NULL if there is no event upcoming
	 */
	public function get_next_calendar_event() {
		$event_filter = Event_Filter::create_for_calendar( $this );
		$now = new \DateTime( 'now', wp_timezone() );
		$event_filter->set_accept_minimum_date_time( $now );
		$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
		$event_array = Event::get_all_events_by_filter( $event_filter );
		$result = ( is_array( $event_array ) && isset( $event_array[ 0 ] ) ) ? $event_array[ 0 ] : NULL;
		return $result;
	} // function

	/**
	 * Get an array of Calendar_Entry objects representing regular events on this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime			$start_date_time
	 * @param	\DateTime			$end_date_time
	 * @return	Calendar_Entry[]	An array of Calendar_Entry objects in the specified range
	 */
	 public function get_calendar_entries_in_date_range( $start_date_time, $end_date_time ) {

	 	$calendar_type = $this->get_calendar_type();
		// We will determine the type of calendar entries to return based on the calendar entry type
		// For special calendar IDs like the volunteer registration calendar we will return special entries.
		// But we may also have to ensure that the current user has the capability to view the special calendar
	 	switch ( $calendar_type ) {

			case self::CALENDAR_TYPE_VOLUNTEER_REG:
			case self::CALENDAR_TYPE_VISITOR_REG:
			case self::CALENDAR_TYPE_ADMIN_EVENTS:
	 		case self::CALENDAR_TYPE_EVENTS:
	 		default:
	 			$result = $this->get_calendar_events_in_date_range( $start_date_time, $end_date_time );
	 			break;

	 	} // endswitch

	 	return $result;

	} // function

	/**
	 * Get an array of Calendar_Entry objects representing volunteer registration events on this calendar
	 *  that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime			$start_date_time
	 * @param	\DateTime			$end_date_time
	 * @return	Calendar_Entry[]	An array of Calendar_Entry objects in the specified range
	 */
	 public function get_volunteer_registration_calendar_entries_in_date_range( $start_date_time, $end_date_time ) {

	 	$result = array();

		$event_array = $this->get_calendar_events_in_date_range( $start_date_time, $end_date_time );
		$event_keys_array = array();
		foreach( $event_array as $event ) {
			$event_keys_array[] = $event->get_key();
		} // endfor

		$volunteer = Volunteer::get_current_volunteer();
		$vol_reg_array = Volunteer_Registration::get_registrations_for_volunteer_and_event_keys_array( $volunteer, $event_keys_array );

		$assoc_vol_reg_array = array(); // key the array by event key to make them easy to find
		foreach( $vol_reg_array as $vol_reg ) {
			$assoc_vol_reg_array[ $vol_reg->get_event_key() ] = $vol_reg;
		} // endfor

		foreach( $event_array as $event ) {
			$event_key = $event->get_key();
			$vol_reg = isset( $assoc_vol_reg_array[ $event_key ] ) ? $assoc_vol_reg_array[ $event_key ] : NULL;
			$event->set_volunteer_registration( $vol_reg ); // Tell the event if there is a volunteer reg or not
			$result[] = $event;
		} // endfor

		return $result;
	} // function

	/**
	 * Get an array of Map_Marker objects for this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	string				$map_type	One of the Map_View::MAP_TYPE_* constants
	 * @param	\DateTime			$start_date_time
	 * @param	\DateTime			$end_date_time
	 * @return	Map_Marker[]		An array of Map_Marker objects in the specified range
	 */
	 public function get_map_markers_in_date_range( $map_type, $start_date_time, $end_date_time ) {

		$events_array = $this->get_calendar_events_in_date_range( $start_date_time, $end_date_time );

		// What I have now is an array of events but what I need is an array of event groups so that the map markers
		// for multiple events at the same location are not all stacked on top of each other.
		// Instead, events at one location are grouped together into a single marker.

		$group_markers = Event_Group_Map_Marker::create_array_for_events_array( $events_array );

		return array_values( $group_markers );

	} // function

	/**
	 * Get an array of events for this calendar that fall within the specified date range.
	 * This method will apply the calendar's settings for event status, categories and so on.
	 * @param	\DateTime	$start_date_time
	 * @param	\DateTime	$end_date_time
	 * @return	Event[]		An array of event objects in the specified range
	 */
	 private function get_calendar_events_in_date_range( $start_date_time, $end_date_time ) {

		if ( ! isset( $start_date_time ) || ! isset( $end_date_time ) ) {

			// One of both of the dates are invalid so return an empty set
			$result = array();

		} else {

			$event_filter = Event_Filter::create_for_calendar( $this );
			$event_filter->set_accept_minimum_date_time( $start_date_time );
			$event_filter->set_accept_maximum_date_time( $end_date_time );

			$result = Event::get_all_events_by_filter( $event_filter );

		} // endif

		return $result;

	} // function

	/**
	 * Get the default array of views to be shown when no specific set of views is defined for the calendar.
	 * @return	string[]	The viewss to be shown on a calendar by default, e.g. array( Calendar_View_Format::MONTH_GRID_VIEW )
	 * @since	v0.1.0
	 */
	public static function get_default_view_format_ids_array() {
		$result = array();
		$result[] = Calendar_View_Format::MONTH_GRID_VIEW; // Always show the month view
		$result[] = Calendar_View_Format::MONTH_LIST_VIEW; // Add month list view
		if ( Map_View::get_is_map_view_enabled() ) {
			$result[] = Calendar_View_Format::MAP_VIEW; // Show a map if we have an API key
		} // endif
		return $result;
	} // function

	/**
	 * Get the array of view format IDs to be shown on this calendar.
	 * View format ID values are defined as constants in the Calendar_View_Format, e.g. Calendar_View_Format::MONTH_GRID_VIEW.
	 * @return	string[]	The array of view constant strings to be shown on this calendar,
	 *  e.g. array( Calendar_View_Format::MONTH_GRID_VIEW, Calendar_View_Format::MAP_VIEW )
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
	 * View format ID values are defined as constants in the Calendar_View_Format, e.g. Calendar_View_Format::MONTH_GRID_VIEW.
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

	private function remove_map_view_format() {
		$format_ids_array = $this->get_view_format_ids_array();
		if ( in_array( Calendar_View_Format::MAP_VIEW, $format_ids_array ) ) {
			$format_ids_array = array_values( array_diff( $format_ids_array, array( Calendar_View_Format::MAP_VIEW ) ) );
			$this->view_format_ids_array = ! empty( $format_ids_array ) ? $format_ids_array : self::get_default_view_format_ids_array();
		} // endif
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
	 * By default a calendar will always show PUBLIC events, and never show CONFIDENTIAL.
	 * A calendar will include PRIVATE events only if the logged-in user has authority to view private events.
	 * @return	string[]	The event classes to be shown on a calendar by default, e.g. array( Event_Class::PUBLIC )
	 * @since	v0.1.0
	 */
	public static function get_default_class_array() {
		$result = array( Event_Class::PUBLIC );
		$capability = 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {
			$result[] = Event_Class::PRIVATE;
		} // endif
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
			$this->category_object_array = NULL; // reset my internal vars so they can be re-acquired
			$this->categories = NULL;
		} // endif
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
		$capability_singular = User_Role_Controller::EVENT_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Event Calendar', // Internal description, not visible externally
				'public'				=> TRUE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> FALSE, // exclude from regular search results?
				'publicly_queryable'	=> TRUE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> TRUE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.
				'menu_icon'				=> 'dashicons-calendar',
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> array( 'title', 'editor' ),
				'taxonomies'			=> array( Event_Category::TAXONOMY_NAME ),
				'has_archive'			=> FALSE, // is there an archive page?
				// Rewrite determines how the public page url will look.  Here it will be http://site/reg-man-rc-calendar
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