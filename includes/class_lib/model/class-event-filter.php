<?php
namespace Reg_Man_RC\Model;

/**
 * A filter for events.
 *
 * An instance of this class specifies a set of criteria for events like minimum and maximum start date or event categories.
 * You can filter an array of events to remove any that don't meet the specified criteria.
 * You can also specify a desired ordering for the resulting array, for example sorted by descending start date.
 *
 * @since v0.1.0
 *
 */
class Event_Filter {

	private $accept_classes; // The array of event classes to be accepted by the filter
	private $accept_statuses; // The array of event statuses to be accepted by the filter
	private $accept_category_names; // The array of event category names (strings) to be accepted by the filter
// FIXME - there are no uncategorized events any more
// But wait, there are no uncategorized events INTERNALLY but what about external events, can't they be uncategorized????
//	private $is_accept_uncategorized; // A flag indicating whether uncategorized events will be accepted by the filter
	private $accept_minimum_date_time; // Accept only events on or after the minimum date and time
	private $accept_maximum_date_time; // Accept only events on or before the maximum date and time
	private $is_accept_boundary_spanning_events = TRUE; // Accept only events on or before the maximum date and time
	private $search_string; // Accept only events whose label contains the specified search string
	private $accept_event_author_id; // Accept only events whose author ID is the specified ID

	const SORT_BY_DATE_ASCENDING = 'date_ascending';
	const SORT_BY_DATE_DESCENDING = 'date_descending';

	private $sort_order;

	/**
	 * Create an instance of this class.
	 *
	 * Use the set_accept*() methods to configure which events to accept and which to filter out.
	 * Use the set_sort_order() method to configure the order events are returned.
	 * Use apply_filter( &$events_array ) to apply this filter to an array of events.
	 * @return	Event_Filter	A new instance of this class.
	 * @since v0.1.0
	 */
	public static function create( ) {
		$result = new self();
		return $result;
	} // function

	/**
	 * Create an instance of this class containing only events with class 'public' and status 'confirmed'.
	 *
	 * @return	Event_Filter	A new instance of this class containing all the public confirmed events.
	 * @since v0.1.0
	 */
	public static function create_for_public_confirmed_events( ) {
		$result = new self();
		$result->accept_classes = array( Event_Class::PUBLIC );
		$result->accept_statuses = array ( Event_Status::CONFIRMED );
		return $result;
	} // function

	/**
	 * Create an instance of this class containing only events on the specified calendar.
	 *
	 * @param	Calendar	$calendar
	 */
	public static function create_for_calendar( $calendar ) {
		$result = new self();
		$result->set_accept_classes( $calendar->get_event_class_array() );
		$result->set_accept_statuses( $calendar->get_event_status_array() );
		$categories = $calendar->get_event_category_array();
		if ( ! empty( $categories ) ) {
			$result->set_accept_categories( $categories );
		} // endif
//		$result->set_accept_uncategorized_events( $calendar->get_show_uncategorized_events() );
		return $result;
	} // function

	/**
	 * Get an array of strings containing the Event_Class constants determining which classes of events will be accepted by the filter.
	 *
	 * @return	string[]|NULL	An array of strings specifying the event classes that will be accepted by the filter
	 *  OR NULL if ANY event class will be accepted.
	 * By default the filter will accept events of any class.
	 * @since v0.1.0
	 */
	public function get_accept_classes() {
		return $this->accept_classes;
	} // function

	/**
	 * Set the array of Event_Class constants determining which classes of events will be accepted by the filter.
	 *
	 * @param	string[]|string		$accept_event_class_array	An array of strings containing the Event_Class constants for events
	 * that will be accepted by the filter.
	 * For convenience, the caller may pass a single string rather than an array if only one class is to be accepted.
	 * Events whose class is not in this array will be excluded by the filter.
	 * If this argument is an empty array, meaning there are no event classes accepted, then the filter will exclude ALL events.
	 * If this argument is NULL then the event filter will accept events of any class.
	 * @since v0.1.0
	 */
	public function set_accept_classes( $accept_event_class_array ) {
		if ( ! is_array( $accept_event_class_array ) ) {
			$accept_event_class_array = array( $accept_event_class_array ); // Make it an arry if they only passed one thing
		} // endif
		$this->accept_classes = $accept_event_class_array;
	} // function

	/**
	 * Get an array of strings containing the Event_Status constants determining which statuses of events will be accepted by the filter.
	 *
	 * @return	string[]|NULL	An array of strings containing the Event_Status constants for events that will be accepted by the filter
	 *  OR NULL if ANY event status will be accepted.
	 * By default the filter will accept events of any status.
	 * @since v0.1.0
	 */
	public function get_accept_statuses() {
		return $this->accept_statuses;
	} // function

	/**
	 * Set the array of Event_Status constants determining which statuses of events will be accepted by the filter.
	 *
	 * @param	string[]	An array of strings containing the Event_Status constants for events that will be accepted by the filter.
	 * For convenience, the caller may pass a single string rather than an array if only one status is to be accepted.
	 * Events whose status is not in this array will be excluded by the filter.
	 * If this argument is an empty array, meaning there are no event statuses accepted, then the filter will exclude ALL events.
	 * If this argument is NULL then the filter will accept events of any status.
	 * @since v0.1.0
	 */
	public function set_accept_statuses( $accept_event_status_array ) {
		if ( ! is_array( $accept_event_status_array ) ) {
			$accept_event_status_array = array( $accept_event_status_array );
		} // endif
		$this->accept_statuses = $accept_event_status_array;
	} // function

	/**
	 * Get an array containing the event category names (as strings) determining which categories of events will be accepted by the filter.
	 *
	 * @return	string[]|NULL	An array of strings containing categories for events that will be accepted by the filter
	 *  OR NULL if events of ANY will be accepted.
	 * By default the filter will accept events of any category.
	 * @since v0.1.0
	 */
	public function get_accept_category_names() {
		return $this->accept_category_names;
	} // function

	/**
	 * Set the array of event categories (as strings) determining which categories of events will be accepted by the filter.
	 *
	 * @param	string[]|NULL	$accept_event_category_names_array	An array of strings containing the names of categories
	 * for events that will be accepted by the filter.
	 * For convenience, the caller may pass a single string rather than an array if only one category is to be accepted.
	 * Events whose categories do not match any of those provided in this array will be excluded by the filter.
	 * If this argument is an empty array, meaning there are no event categories accepted, then the filter will exclude ALL events
	 *  with any assigned category.
	 * If this argument is NULL then the filter will accept events of ANY category.
	 * FIXME - there are no uncategorized events any more
	 * By default the filter will exclude uncategorized events if an array of accepted categories has been assigned.
	 * For example, if you call set_accept_category_names( array( 'Repair Cafe' ) ) then uncategorized events will be excluded.
	 * The filter will accept uncategorized events if no array of accepted categories has been assigned.
	 * To filter or accept uncategorized events excplicitly, use the set_accept_uncategorized_events() method.
	 * @since v0.1.0
	 */
	public function set_accept_category_names( $accept_event_category_names_array ) {
		if ( ! is_array( $accept_event_category_names_array ) ) {
			$accept_event_category_names_array = array( $accept_event_category_names_array );
		} // endif
		$this->accept_category_names = $accept_event_category_names_array;
	} // function

	/**
	 * Set the array of event categories (as instances of Event_Category) determining which categories of events will be accepted by the filter.
	 *
	 * @param	Event_Category[]|NULL	$accept_event_categories_array	An array of strings containing the names of categories
	 * for events that will be accepted by the filter.
	 * For convenience, the caller may pass a single ID rather than an array if only one category is to be accepted.
	 * This method converts the specified category IDs into their corresponding names and then calls
	 * Event_Filter::set_accept_category_names().
	 * @see		Event_Filter::set_accept_category_names()
	 * @since	v0.1.0
	 */
	public function set_accept_categories( $accept_event_categories_array ) {
		if ( ! is_array( $accept_event_categories_array ) ) {
			$accept_event_categories_array = array( $accept_event_categories_array );
		} // endif
		$cat_names = array();
		foreach ( $accept_event_categories_array as $category ) {
			if ( $category instanceof Event_Category ) {
				$cat_names[] = $category->get_name();
				$ext_names_array = $category->get_external_names();
				foreach ( $ext_names_array as $ext_name ) {
					$cat_names[] = $ext_name;
				} // endfor
			} // endif
		} // endfor
		$this->set_accept_category_names( $cat_names );
	} // function

	/**
	 * Get a flag indicating whether events with NO category should be accepted by the filter.
	 *
	 * @return	boolean		A flag set to TRUE if events with no category will be accepted by the filter, FALSE if they will be excluded.
	 * By default the filter will exclude uncategorized events if an array of accepted categories has been assigned,
	 * and the filter will accept uncategorized events if no array of accept categories has been assigned.
	 * @since	v0.1.0
	 */
/* FIXME - there are no uncategorized events any more
	public function get_accept_uncategorized_events() {
		if ( isset( $this->is_accept_uncategorized ) ) {
			$result = $this->is_accept_uncategorized;
		} else {
			$accept_category_names = $this->get_accept_category_names();
			$result = ( ! is_array( $accept_category_names ) || empty( $accept_category_names ) );
		} // endif
		return $result;
	} // function
*/
	/**
	 * Set the flag indicating whether events with NO category should be accepted by the filter.
	 *
	 * @param	boolean	$is_accept_uncategorized	A flag set to TRUE if the filter should accept events with NO category,
	 * and FALSE if the list should exclude them
	 * @since	v0.1.0
	 */
/* FIXME - there are no uncategorized events any more
	public function set_accept_uncategorized_events( $is_accept_uncategorized ) {
		$this->is_accept_uncategorized = boolval( $is_accept_uncategorized );
	} // function
*/

	/**
	 * Get the search string to be used to filter events
	 *
	 * @return	string	The search string to be used to filter events.
	 * @since v0.1.0
	 */
	public function get_search_string() {
		return $this->search_string;
	} // function

	/**
	 * Set the string to be searched in the event list.
	 * Events whose label contains the specified search string will be returned, others will be excluded by the filter.
	 * @param	string	$search_string		Any string to be used to search event labels
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_search_string( $search_string ) {
		$this->search_string = $search_string;
	} // function

	/**
	 * Get the constant indicating how the list is to be sorted.
	 *
	 * @return	string	A string containing the SORT_BY_* constant (defined in this class) indicating how the list is to be sorted.
	 * @since v0.1.0
	 */
	public function get_sort_order() {
		return $this->sort_order;
	} // function

	/**
	 * Set the Event_Filter constant (defined in this class) indicating how the list is to be sorted.
	 * @param	string	$sort_order		The order used to sort this list.  The value must be one of the SORT_BY_* constants defined in this class.
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_sort_order( $sort_order ) {
		$this->sort_order = $sort_order;
	} // function

	/**
	 * Set the filter to accept events scheduled for the specified year.
	 * @param	string	$year	A string representing the year whose events are to be accepted by this filter.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_accept_dates_in_year( $year ) {
		// Create two DateTime objects, one for the start of the year and one for the end
		// Then assign those dates as the minimum and maximum dates for this filter
		$wp_tz = wp_timezone();

		$year_start = new \DateTime( 'now', $wp_tz ); // Get the current date
		$year_start->setDate( $year, 1, 1 ); // Jan 1 of the specified year
		$year_start->setTime( 0, 0, 0 ); // go back to midnight

		$year_end = new \DateTime( 'now', $wp_tz ); // Get the current date
		$year_end->setDate( $year, 12, 31 ); // Dec 31 of the specified year
		$year_end->setTime( 23, 59, 59 ); // go ahead to the end of the day

		$this->set_accept_minimum_date_time( $year_start );
		$this->set_accept_maximum_date_time( $year_end );
	} // function


	public function get_accept_minimum_date_time() {
		return $this->accept_minimum_date_time;
	} // function

	/**
	 * Set the minimum date and time for events to be accepted by the filter.
	 *
	 * Only events whose end date and time are equal to or greater than the specified date and time
	 *   will be accepted by the filter.
	 * By default the filter will accept events with any data.
	 * @param	\DateTimeInterface	$min_date_time	The minimum date and time for an event to be accepted
	 */
	public function set_accept_minimum_date_time( $min_date_time ) {
		if ( $min_date_time instanceof \DateTimeInterface ) {
			$this->accept_minimum_date_time = $min_date_time;
		} // endif
	} // function

	public function get_accept_maximum_date_time() {
		return $this->accept_maximum_date_time;
	} // function

	/**
	 * Set the maximum date and time for events to be accepted by the filter.
	 *
	 * Only events whose start date and time are equal to or less than the specified date and time
	 * will be accepted by the filter.
	 * By default the filter will accept events with any data.
	 * @param	\DateTimeInterface	$max_date_time	The maximum date and time for an event to be accepted.
	 */
	public function set_accept_maximum_date_time( $max_date_time ) {
		if ( $max_date_time instanceof \DateTimeInterface ) {
			$this->accept_maximum_date_time = $max_date_time;
		} // endif
	} // function

	/**
	 * Set a flag to indicate whether events spanning a boundary (minimum or maximum) should be accepted.
	 * This flag is only used when the filter has a minimum or maximum date and time assigned.
	 *
	 * If set to TRUE then the filter will accept events whose end is after the minimum date and time,
	 *  and whose start is before the maximum date and time.
	 * An event may span a minimum or maximum boundaray.  For example it may start before the minimum date and time
	 *  but end after the mimimum.  Or it may end after the maximum but start before the maximum.
	 * This flag indicates whether those boundary-spanning events should be accepted by the filter or excluded.
	 *
	 * By default this flag is set to TRUE and the filter will accept events which span a boundary.
	 *
	 * @param	boolean $is_accept_boundary_spanning_events	A flag to indicate whether events which span a boundary
	 *  should be accepted.
	 */
	public function set_is_accept_boundary_spanning_events( $is_accept_boundary_spanning_events ) {
		$this->is_accept_boundary_spanning_events = boolval( $is_accept_boundary_spanning_events );
	} // function

	private function get_is_accept_boundary_spanning_events() {
		return $this->is_accept_boundary_spanning_events;
	} // function

	public function set_accept_dates_on_or_before_today() {
		$date_cutoff = new \DateTime( 'now', wp_timezone() ); // Get the current date
		$date_cutoff->setTime( 23, 59, 59 ); // Set the time to the last second to accept anything on the same day as today
		$this->set_accept_maximum_date_time( $date_cutoff );
	} // function

	public function set_accept_dates_on_or_after_today() {
		$date_cutoff = new \DateTime( 'now', wp_timezone() ); // Get the current date
		$date_cutoff->setTime( 0, 0, 0 ); // Set the time to the first second to accept anything on the same day as today
		$this->set_accept_minimum_date_time( $date_cutoff );
	} // function

	/**
	 * Get the WordPress user ID for the author of events to be accepted by the filter
	 * @return	int|string
	 */
	public function get_accept_event_author_id() {
		return $this->accept_event_author_id;
	} // function

	/**
	 * Set the WordPress user ID for the author of events to be accepted by the filter
	 * @param	int|string	$wp_user_id
	 */
	public function set_accept_event_author_id( $wp_user_id ) {
		$this->accept_event_author_id = $wp_user_id;
	} // function

	/**
	 * Apply this filter to the specified array of events.
	 * @param	Event[]	$events_array	An array of events to be filtered
	 * @return	Event[]	A new array containg the events accpted by the filter.
	 */
	public function apply_filter( $events_array ) {

		$accept_classes = $this->get_accept_classes();
		$accept_statuses = $this->get_accept_statuses();
		$accept_category_names = $this->get_accept_category_names();
// FIXME - there are no uncategorized events any more
//		$accept_uncategorized = $this->get_accept_uncategorized_events();
		$accept_min_date_time = $this->get_accept_minimum_date_time();
		$accept_max_date_time = $this->get_accept_maximum_date_time();
		$search_string = $this->get_search_string();
		$accept_author_id = $this->get_accept_event_author_id();
//		Error_Log::var_dump( $accept_classes, $accept_statuses, $accept_category_names, $accept_min_date_time, $accept_max_date_time, $search_string );

		// If any of classes, statuses and categories are empty arrays then we can exclude everything
		// So shortcut the work and just return an empty array
		if ( ( is_array( $accept_classes ) && ( count( $accept_classes ) == 0 ) ) ||
			 ( is_array( $accept_statuses ) && ( count( $accept_statuses ) == 0 ) ) ||
//			 ( is_array( $accept_category_names ) && ( count( $accept_category_names ) == 0 ) && ( $accept_uncategorized == FALSE ) ) ) {
			 ( is_array( $accept_category_names ) && ( count( $accept_category_names ) == 0 ) ) ) {

			 $result = array();

		} else {

			 // Figure out which attributes of each event we need to check
			$class_flag = ( is_array( $accept_classes ) && ( count( $accept_classes ) !== 0 ) );
			$status_flag = ( is_array( $accept_statuses ) && ( count( $accept_statuses ) !== 0 ) );
//			$category_flag = ( ( is_array( $accept_category_names ) && ( count( $accept_category_names ) !== 0 ) ) || ( $accept_uncategorized == TRUE ) );
			$category_flag = ( ( is_array( $accept_category_names ) && ( count( $accept_category_names ) !== 0 ) ) );
			$date_flag = ( ( $accept_min_date_time !== NULL ) || ( $accept_max_date_time !== NULL ) );
			$is_accept_boundary_flag = $this->get_is_accept_boundary_spanning_events();
			$search_flag = ! empty( $search_string );
			$author_flag = ! empty( $accept_author_id );

			$result = array(); // start with an empty array and add the events that pass the filter
			foreach ( $events_array as $event ) {

				if ( $class_flag ) {
					$class = $event->get_class();
					$class_id = isset( $class ) ? $class->get_id() : NULL;
					if ( ( $class_id === NULL ) || ( ! in_array( $class_id, $accept_classes ) ) ) {
						continue;
					} // endif
				} // endif

				if ( $status_flag ) {
					$status = $event->get_status();
					$status_id = isset( $status ) ? $status->get_id() : NULL;
					if ( ( $status_id === NULL ) || ( ! in_array( $status_id, $accept_statuses ) ) ) {
						continue;
					} // endif
				} // endif

				if ( $category_flag ) {
					// The flag is turned on when we're accepting certain categories
					// FIXME - there are no uncategorized events any more
					// This appears to be working, is there anything I need to test?
					// The flag is turned on when we're accepting certain categories OR accepting uncategorized
					$event_categories = $event->get_categories();
					if ( empty( $event_categories ) ) {
						// The event has no categories
//						$accept = $accept_uncategorized;
						$accept = FALSE;
					} else {
						// The event has categories
						if ( $accept_category_names !== NULL ) {
							// we are only accepting certain categories
							$overlap = array_intersect( $event_categories, $accept_category_names );
							$accept = ! empty( $overlap );
						} else {
							// We are accepting any category (we should never get here)
							$accept = TRUE;
						} // endif
					} // endif
					if ( ! $accept ) {
						continue;
					} // endif
				} // endif

				if ( $date_flag ) {
					// The event must fall between the min and max times.  An event may span one of the borders
					// So the event end must be >= min date and time (the event won't have ended before min)
					//  and the event start must be <= the max date and time (it must start before max)
					$event_start_date = $event->get_start_date_time_object();
					$event_end_date = $event->get_end_date_time_object();
					$event_max_compare_date = $is_accept_boundary_flag ? $event_start_date : $event_end_date;
					$event_min_compare_date = $is_accept_boundary_flag ? $event_end_date : $event_start_date;
					if ( ( empty( $event_end_date ) && ! empty( $accept_min_date_time ) ) ||
						 ( empty( $event_start_date ) && ! empty( $accept_max_date_time ) ) ) {
						continue; // It is missing a date we need for comparison so we'll remove it
					} else {
						$ok_min = ! empty( $accept_min_date_time ) ? ( $event_min_compare_date >= $accept_min_date_time ) : TRUE;
						$ok_max = ! empty( $accept_max_date_time ) ? ( $event_max_compare_date <= $accept_max_date_time ) : TRUE;
						if ( ! $ok_min || ! $ok_max ) {
							continue;
						} // endif
					} // endif
				} // endif

				if ( $search_flag ) {
					$label = $event->get_label();
					if ( empty( $label ) || ( stristr( $label, $search_string ) === FALSE ) ) {
						continue; // This event does not meet the criteria
					} // endif
				} // endif

				if ( $author_flag ) {
					// The flag is set when we only accept events from a specific author
					// If the current event has no author then we cannot accept it
					// Or if the current author does not match the specified author to accept, we can't accept it
					$curr_author_id = $event->get_author_id();
					if ( empty( $curr_author_id ) || ( $curr_author_id != $accept_author_id ) ) {
						continue; // This event does not meet the criteria
					} // endif
				} // endif

				$result[] = $event; // It passed all the criteria so add it to the result

			} // endfor

		} // endif

		$sort_order = $this->get_sort_order();
		switch ( $sort_order ) {
			case self::SORT_BY_DATE_ASCENDING:
				$this->sort_events_by_date( $is_ascending = TRUE, $result );
				break;
			case self::SORT_BY_DATE_DESCENDING:
				$this->sort_events_by_date( $is_ascending = FALSE, $result );
				break;
			default:
				// If the sort order is not recognized, do nothing
				break;
		} // endswitch

		return $result;

	} // function

	private function sort_events_by_date( $is_ascending, &$events_array ) {
		usort( $events_array,
			function( $event1, $event2 ) use ( $is_ascending ) {
				$date1 = $event1->get_start_date_time_object();
				$date2 = $event2->get_start_date_time_object();
				return $is_ascending ? $date1 <=> $date2 : $date2 <=> $date1;
			} // function
		);
	} // function
} // class