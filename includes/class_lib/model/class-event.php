<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Volunteer_Registration_View;
use Reg_Man_RC\View\Map_View;

/**
 * Describes an instance of an event.
 *
 * An instance of this class contains the information related to one instance of an event.
 * Note that this differs from an Event_Descriptor which may describe a series of recurring events.
 *
 * A recurring event will have a single Event_Descriptor and multiple associated Event objects.
 * For recurring events, the Event object will include the specific date and time for one event instance, and its recurrence ID.
 *
 * This class also provides human-readable forms of the event details such as formatted string representations of
 * the start and end date/times (in addition to the DateTime objects), and placeholder strings for missing information,
 * e.g. "No Event Location".
 *
 * This class has many of the same properties as the VEVENT object defined in the iCalendar specification.
 * The following properties of VEVENT are currently NOT used in this class:
 * 		created, last_mod, organizer, priority, seq, transp, contact
 *
 * @since v0.1.0
 *
 */

final class Event implements Calendar_Entry, Map_Marker, \JsonSerializable {

	const DEFAULT_EVENT_COLOUR = '#AAAAAA';

	private static $DATE_NOW; // The current date and time, used to determine if this event is completed

	/**
	 * A string used to format a \DateTime object and return a string suitable for use as a recurrence id.
	 * Use this constant if the recurrence ID when the event time is not required to differentiate events,
	 * that is there is only one occurrence of the recurring event on any given date.
	 * @since v0.1.0
	 */
	private static $RECUR_ID_FORMAT_DATE = 'Ymd';

	/**
	 * A string used to format a \DateTime object and return a string suitable for use as a recurrence id.
	 * Use this constant only if the recurrence ID must contain a time, that is there are two occurrences of the
	 * recurring event on the same day.
	 * @since v0.1.0
	 */
	private static $RECUR_ID_FORMAT_DATE_TIME = 'Ymd\THis';

	private static $DEFAULT_CATEGORIES_ARRAY; // Store the array of default categories so we can reuse it

	/**
	 * The Event_Descriptor which contains the raw data for this event
	 * @var	\Reg_Man_RC\Model\Event_Descriptor
	 * @since v0.1.0
	 */
	private $event_descriptor;

	/**
	 * A key identifying this object uniquely in the system.
	 * @var Event_Key
	 * @since v0.1.0
	 */
	private $key_object;

	/**
	 * A string containing the recurrence id for a repeating event.
	 * If an event is not repeating, this will be NULL or an empty string.
	 * @var	string
	 * @since v0.1.0
	 */
	private $recurrence_id;

	/**
	 * The event's summary, e.g. "Repair Café at Toronto Reference Library"
	 * @var	string
	 * @since v0.1.0
	 */
	private $summary;

	/**
	 * The event's status represented as a translated string, e.g. Confirmed, Tentative, or Cancelled
	 * @var	string
	 * @since v0.1.0
	 */
	private $status;

	/**
	 * The event's class represented as a translated string, e.g. Public, Private, or Confidential
	 * @var	string
	 * @since v0.1.0
	 */
	private $class;

	/**
	 * The \DateTimeInterface object representing the start date and time of the event.
	 *
	 * Note that if this is a recurring Event then the start date and time for this event
	 * may be different from the start date and time found in the Event_Descriptor object for this Event.
	 * So we must store the actual event date and time in this object and not rely on the date and time
	 * in the underlying Event_Descriptor.
	 * @var	\DateTimeInterface
	 * @since v0.1.0
	 */
	private $start_date_time_object;

	/**
	 * The \DateTimeInterface object representing the end date and time of the event.
	 *
	 * Note that if this is a recurring Event then the end date and time for this event
	 * may be different from the end date and time found in the Event_Descriptor object for this Event.
	 * @var	\DateTimeInterface
	 * @since v0.1.0
	 */
	private $end_date_time_object;

	/**
	 * The \DateTimeInterface object representing the start date and time of the event using the timezone
	 * assigned in the settings for this Wordpress site.
	 *
	 * Note that event provider implementations may use any timezone to store events.
	 * When event dates and times are displayed to end users they are always converted to the local timezone
	 * assigned in the Wordpress settings.
	 * @var	\DateTimeInterface
	 * @since v0.1.0
	 */
	private $start_date_time_local_timezone_object;

	/**
	 * The \DateTimeInterface object representing the end date and time of the event using the timezone
	 * assigned in the settings for this Wordpress site.
	 * @var	\DateTimeInterface
	 * @since v0.1.0
	 */
	private $end_date_time_local_timezone_object;

	/**
	 * A string containing the start date formatted according to the website's date format and timezone
	 * @var	string
	 * @since v0.1.0
	 */
	private $start_date;

	/**
	 * A string containing the start time formatted according to the website's time format and timezone
	 * @var	string
	 * @since v0.1.0
	 */
	private $start_time;

	/**
	 * A string containing the end date formatted according to the website's date format and timezone
	 * @var	string
	 * @since v0.1.0
	 */
	private $end_date;

	/**
	 * A string containing the end time formatted according to the website's time format and timezone
	 * @var	string
	 * @since v0.1.0
	 */
	private $end_time;

	/**
	 * A string containing a labelling showing the event's start and end date and time
	 * @var	string
	 * @since v0.1.0
	 */
	private $event_dates_and_times_label;

	/**
	 * A string containing the event's location, e.g. "Toronto Reference Library"
	 * @var	string
	 * @since v0.1.0
	 */
	private $location;

	/**
	 * A string containing the url for the event's internal page.
	 * Note that this will be a local virtual page created by this plugin showing an event which may be an instance of
	 * a repeating event or an event defined externally by another plugin or another system like Google Calendar
	 * @var	string
	 * @since v0.1.0
	 */
	private $event_page_url;

	/**
	 * A string containing a human-readable label for the event including it's date and location.
	 * @var	string
	 * @since v0.1.0
	 */
	private $label;

	/** An array of Item objects representing the items registered for this event */
	private $items;

	/** A set of HTML class names associated with the calendar entry for this event */
	private $calendar_entry_class_names;

	/** The colour for the marker used to mark this event on a map */
	private $map_marker_colour;

	/** The volunteer registration record for the current volunteer (if one exists) for this event */
	private $volunteer_registration = FALSE; // initialize to FALSE to indicate we have not tried to find it yet

	private static $PROVIDER_NAME_ARRAY;

	/**
	 * Get all events currently defined to the system
	 * @return	\Reg_Man_RC\Model\Event[]	An array of all events
	 * @since v0.1.0
	 */
	public final static function get_all_events() {
		$result = array();
		$descriptor_array = Event_Descriptor_Factory::get_all_event_descriptors();
		$result = self::get_events_for_descriptors( $descriptor_array );
		return $result;
	} // function

	public static function get_events_array_for_event_descriptor( $event_descriptor ) {
		if ( $event_descriptor instanceof Event_Descriptor ) {
			$result = self::get_events_for_descriptors( array( $event_descriptor ) );
		} else {
			$result = array();
		} // endif
		return $result;
	} // function

	/**
	 * Get the events for the specified array of event descriptors
	 * @param	Event_Descriptor[]	$descriptor_array
	 * @return	Event[]
	 */
	private static function get_events_for_descriptors( $descriptor_array ) {
		// Create an event (or set of recurring events) for each descriptor and add it to the result
		$result = array();
		foreach ( $descriptor_array as $event_descriptor ) {
			if ( ! $event_descriptor->get_event_is_recurring() ) {
				$event = self::create_for_event_descriptor( $event_descriptor );
				$result[] = $event;
			} else {
				$recurring_events = self::get_recurring_events( $event_descriptor );
				$result = array_merge( $result, $recurring_events );
			} // function
		} // endfor
		return $result;
	} // function

	/**
	 * Get all events currently defined to the system which are accepted by the specified filter.
	 * @param	Event_Filter	$event_filter A filter object specifying which events should be included in the results.
	 * @return	Event[]	An array of events accepted by the specified filter
	 * @since v0.1.0
	 */
	public final static function get_all_events_by_filter( $event_filter ) {
		// Get the event providers currently being used and request all their events
		$result = array();
		// TODO: I could have Event_Desc_Factory::get_event_descriptors_in( class_arr, status_arr, cat_arr )
		//  to make the list smaller, or get_event_desc_in_range( min_date, max_date ) or _in_filter( event_filter )
		//  which would start with non-repeating events selected and then add repeaters
		// It would be a performance improvement if there are problems
		$descriptor_array = Event_Descriptor_Factory::get_all_event_descriptors();
//Error_Log::var_dump( $descriptor_array );
		$result = self::get_events_for_descriptors( $descriptor_array );

		// Filter the final result if necessary
		if ( isset( $event_filter ) ) {
			$result = $event_filter->apply_filter( $result );
		} // endif

		return $result;
	} // function


	/**
	 * Get the events in the specified event category
	 * @param	int|string	$event_category_id	The ID of the event category whose events are to be returned.
	 * @return	\Reg_Man_RC\Model\Event[]	An array of events with the specified category
	 * @since v0.1.0
	 */
	public final static function get_events_in_category( $event_category_id ) {
		// Get the event providers currently being used and request all their events
		$result = array();
		$category = Event_Category::get_event_category_by_id( $event_category_id );

		if ( isset( $category ) ) {
			$descriptor_array = Event_Descriptor_Factory::get_event_descriptors_in_category( $event_category_id );
			$result = self::get_events_for_descriptors( $descriptor_array );
		} // endif

		return $result;
	} // function

	/**
	 * Get the events whose venue is the one specified
	 * @param	Venue	$venue			The Venue object whose events are to be returned
	 * @param	boolean	$upcoming_only	A flag set to TRUE to request only upcoming events, FALSE for all (past and future)
	 * @return	Event[]	An array of events
	 * @since v0.1.0
	 */
	public final static function get_events_for_venue( $venue, $upcoming_only ) {
		$result = array();

		// TODO: It's probably better to get upcoming events first because that is a MUCH smaller collection
		$descriptor_array = Event_Descriptor_Factory::get_event_descriptors_for_venue( $venue );
		$result = self::get_events_for_descriptors( $descriptor_array );

		if ( $upcoming_only ) {
			$filter = Event_Filter::create();
			$filter->set_accept_dates_on_or_after_today();
			$result = $filter->apply_filter( $result );
		} // endif

		return $result;
	} // function


	/**
	 * Get the next event on or after the current date and time.
	 *
	 * This is used to determine the most likely event candidate for registering new items.
	 * @return	NULL|\Reg_Man_RC\Model\Event	The next event on or after the current date and time
	 * 	or NULL if there are no upcoming events
	 * @since	v0.1.0
	 */
	public final static function get_next_upcoming_event() {
		$result = self::get_upcoming_events( $count = 1 );
		return $result;
	} // function

	/**
	 * Get the specified number of upcoming events (on or after the current date and time).
	 *
	 * @return	NULL|\Reg_Man_RC\Model\Event	The next $count events on or after the current date and time
	 * 	or NULL if there are no upcoming events
	 * @since	v0.1.0
	 */
	public final static function get_upcoming_events( $count ) {
		$event_filter = Event_Filter::create();
		$event_filter->set_accept_dates_on_or_after_today();
		$event_filter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
		$events = self::get_all_events_by_filter( $event_filter );
		$result = array_slice( $events, 0, $count );
		return $result;
	} // function

	/**
	 * Get an array of event objects representing all instances of a recurring event
	 * @param	\Reg_Man_RC\Model\Event_Descriptor	$event_descriptor	The descriptor object representing a recurring event
	 * @return	\Reg_Man_RC\Model\Event[]	An associative array of events
	 * 	representing all instances of a recurring event keyed by event key
	 * @since v0.1.0
	 */
	private static function get_recurring_events( $event_descriptor ) {
		$result = array();
		$start_date = $event_descriptor->get_event_start_date_time();
		$end_date = $event_descriptor->get_event_end_date_time();
		// Don't create bogus recurring events if there is no specified start and end time (defensive)
		if ( ( $start_date instanceof \DateTimeInterface) && ( $end_date instanceof \DateTimeInterface ) ) {
			if ( $event_descriptor->get_event_is_recurring() ) {
				$recurrence_rule = $event_descriptor->get_event_recurrence_rule();
				$recur_dates = $recurrence_rule->get_recurring_event_dates();
				foreach ( $recur_dates as $date_pair ) {
					$event = self::create_for_event_descriptor( $event_descriptor, $date_pair['start'], $date_pair['end'] );
					$key = $event->get_key();
					$result[ $key ] = $event;
				} // endfor
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get a single event using its event key
	 * @param	string|Event_Key	$event_key	The key for the event as a string or an instance of EventKey, returned by Event::get_event_key();
	 * @return	Event|NULL			The event specified by the arguments or NULL if the event is not found or the key is invalid
	 * @since v0.1.0
	 */
	public static final function get_event_by_key( $event_key ) {
		if ( ! ( $event_key instanceof Event_Key ) ) {
			$event_key = Event_Key::create_from_string( strval( $event_key ) );
		} // endif
		if ( $event_key instanceof Event_Key ) {
			$event_id = $event_key->get_event_descriptor_id();
			$provider_id = $event_key->get_provider_id();
			if ( $provider_id === Internal_Event_Descriptor::EVENT_PROVIDER_ID ) {
				$event_descriptor = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $event_id );
			} else {
				$event_descriptor = External_Event_Descriptor::get_external_event_descriptor_by_key( $event_key );
			} // endif

			if ( $event_descriptor == NULL ) {
				$result = NULL;
			} else {
				$recurrence_id = $event_key->get_recurrence_id();

				if ( ( $recurrence_id == NULL ) || ( $recurrence_id == FALSE ) || ( $recurrence_id == '' ) ) {
					// What if the event is recurring but no recurrence ID is specified?
					// This may happen if an event is created and then later changed to recurring
					// In this case the key does not refer to any specific event so we need to return NULL
					// The user interface should attempt to stop users from making this mistake
					//  by not allowing an event to be changed to repeating or non-repeating when registrations exist
					if ( $event_descriptor->get_event_is_recurring() ) {
						$result = NULL;
					} else {
						$result = self::create_for_event_descriptor( $event_descriptor );
					} // endif
				} else {
					$recurring_events = self::get_recurring_events( $event_descriptor );
					$result = NULL; // I'm giong to search through my recurring event instances and look for the id
					foreach( $recurring_events as $event ) {
						if ( $event->get_recurrence_id() == $recurrence_id ) {
							$result = $event;
							break;
						} // endif
					} // endfor
				} // endif
			} // endif
		} else {
			$result = NULL; // The key was not an instance of Event_Key
		} // endif
		return $result;
	} // function

	/**
	 * Create an instance of this class using an Event_Descriptor.
	 *
	 * @param	Event_Descriptor	The descriptor containing the event's data
	 * @param	\DateTimeInterface	$recurring_event_start_date_time	(optional) The DateTime obejct representing
	 * the start date and time for a particual instance of a recurring event
	 * @param	\DateTimeInterface	$recurring_event_end_date_time		(optional) The DateTime obejct representing
	 * the end date and time for a particual instance of a recurring event
	 * @return	Event	The newly created Event object
	 * @since v0.1.0
	 */
	private static function create_for_event_descriptor( $event_descriptor,
			$recurring_event_start_date_time = NULL, $recurring_event_end_date_time = NULL ) {
		$result = new self();
		$result->event_descriptor = $event_descriptor;
		$result->start_date_time_object = $recurring_event_start_date_time;
		$result->end_date_time_object = $recurring_event_end_date_time;
		return $result;
	} // function

	private static function get_provider_name_by_id( $provider_id ) {
		if ( ! isset( self::$PROVIDER_NAME_ARRAY ) ) {
			self::$PROVIDER_NAME_ARRAY = External_Event_Descriptor::get_all_external_event_providers();
			self::$PROVIDER_NAME_ARRAY[ Internal_Event_Descriptor::EVENT_PROVIDER_ID ] = __( 'Registration Manager', 'reg-man-rc' );
		} // endif
		$result = isset( self::$PROVIDER_NAME_ARRAY[ $provider_id ] ) ? self::$PROVIDER_NAME_ARRAY[ $provider_id ] : $provider_id;
		return $result;
	} // function

	/**
	 * Get the Event_Descriptor object containing the data used to create this event
	 * @return	\Reg_Man_RC\Model\Event_Descriptor
	 * @since v0.1.0
	 */
	public function get_event_descriptor() {
		return $this->event_descriptor;
	} // function

	/**
	 * Get the system-unique key for this event
	 * @return	string	A key that can be used to uniquely identify the event on the system
	 * @since v0.1.0
	 */
	public function get_key() {
		return $this->get_key_object()->get_as_string();
	} // function

	/**
	 * Get the object containing the system-unique key for this event
	 * @return	Event_Key	A system-unique key object containing the event id, provider id, and recurrence id
	 * @since v0.1.0
	 */
	public function get_key_object() {
		if ( ! isset( $this->key_object ) ) {
			$event_descriptor = $this->get_event_descriptor();
			$this->key_object = Event_Key::create(
				$event_descriptor->get_event_descriptor_id(),
				$event_descriptor->get_provider_id(),
				$this->get_recurrence_id()
			);
		} // endif
		return $this->key_object;
	} // function

	/**
	 * Get the unique id of the provider for this event.
	 * @return	string		A unique id representing the provider.
	 * An event provider is typically an add-on plugin to support an event or calendar plugin that supplies events
	 * This will be an abbreviation of the event implementor, e.g. Event Calendar WD will return "ecwd"
	 * @since v0.1.0
	 */
	public function get_provider_id() {
		return $this->get_event_descriptor()->get_provider_id();
	} // function

	/**
	 * Get the the name of the event provider for this event.
	 * @return	string		A displayable name for the event provider.
	 * E.g. "Event Calendar WD"
	 * @since v0.1.0
	 */
	public function get_provider_name() {
		return self::get_provider_name_by_id( $this->get_provider_id() );
	} // function

	/**
	 * Get the ID for the event descriptor that is unique within the event provider's domain.
	 * @return	string		The event descriptor ID which is unique for the event provider's implementation.
	 * This would be the post ID for event implementors who use a custom post type to represent the event.
	 * @since v0.1.0
	 */
	public function get_event_descriptor_id() {
		return $this->get_event_descriptor()->get_event_descriptor_id();
	} // function

	/**
	 * Get the recurrence id for the event if this is a recurring event, otherwise NULL.
	 *
	 * This method returns the recurrence ID for this instance of the event.
	 * The result is a string that specifies either a date or a date and time.
	 * For events that repeat multiple times on the same day, a date and time is used, otherwise just a date.
	 * A date (with no time) must be like this: 20201023.  A date with time must be like this: 20201023T120000.
	 * The recurrence id is used when events are shared across systems,
	 * for example imported and exported, or used in an iCalendar feed.
	 * Recurring events may have the same uid but then must have different recurrence IDs
	 *
	 * @return	string		The recurrence ID.
	 * @since v0.1.0
	 */
	public function get_recurrence_id() {
		if ( ! isset( $this->recurrence_id ) ) {
			$rule = $this->get_event_descriptor()->get_event_recurrence_rule();
			if ( $rule !== NULL ) {
				$freq = $rule->get_frequency();
				switch ( $freq ) {
					case Recurrence_Rule::SECONDLY:
					case Recurrence_Rule::MINUTELY:
					case Recurrence_Rule::HOURLY:
						$format = self::$RECUR_ID_FORMAT_DATE_TIME;
						break;
					default:
						$format = self::$RECUR_ID_FORMAT_DATE;
						break;
				} // endswitch
				$start_date_time = $this->get_start_date_time_object();
				$this->recurrence_id = $start_date_time->format( $format );
			} else {
				$this->recurrence_id = NULL;
			} // endif
		} // endif
		return $this->recurrence_id;
	} // function


	/**
	 * Get the event's summary, e.g. "Repair Café at Toronto Reference Library".
	 * @return	string	The event summary if one is assigned, otherwise a special string indicating there is no summary
	 * @since v0.1.0
	 */
	public function get_summary() {
		if ( ! isset( $this->summary ) ) {
			$summary = $this->get_event_descriptor()->get_event_summary();
			$this->summary = ( ! empty( $summary ) ) ? $summary : __( '[No Event Summary]', 'reg-man-rc' );
		} // endif
		return $this->summary;
	} // function

	/**
	 * Get the event's status represented as an instance of the Event_Status class.
	 * @return	Event_Status	The event's status.
	 * @since v0.1.0
	 */
	public function get_status() {
		return $this->get_event_descriptor()->get_event_status();
	} // function

	/**
	 * Get the event's class represented as an instance of Event_Class.
	 * @return	Event_Class		The event's class.
	 * @since v0.1.0
	 */
	public function get_class() {
		return $this->get_event_descriptor()->get_event_class();
	} // function

	/**
	 * Get the event's start date formatted as a string suitable for showing on the website (note this does not include the time).
	 * If no start date is set for the event then this method returns a special string indicating such.
	 * @return string	The event's start date formatted using the site's date format, e.g. "July 1, 2019"
	 * @since v0.1.0
	 */
	public function get_start_date() {
		if ( ! isset( $this->start_date ) ) {
			$date_time = $this->get_start_date_time_local_timezone_object();
			if ( $date_time instanceof \DateTimeInterface ) {
				$date_format = get_option( 'date_format' );
				$this->start_date = $date_time->format( $date_format );
			} else {
				$this->start_date = __( '[No Event Date]', 'reg-man-rc' );
			} // endif
		} // endif
		return $this->start_date;
	} // function

	/**
	 * Get the event's start time formatted as a string suitable for showing on the website (note this does not include the time)
	 * If no start time is set for the event then this method returns a special string indicating such.
	 * @return string	The event's start time formatted using the site's time format, e.g. "12:00 PM"
	 * @since v0.1.0
	 */
	public function get_start_time() {
		$time_format = get_option( 'time_format ');
		$date_time = $this->get_start_date_time_object();
		return ( $date_time !== NULL ) ? $date_time->format( $time_format ) : __( '[No Event Time]', 'reg-man-rc' );
	} // function

	/**
	 * Get the event's end date formatted as a string suitable for showing on the website (note this does not include the time)
	 * If no end date is set for the event then this method returns a special string indicating such.
	 * @return string	The event's end date formatted using the site's date format, e.g. "July 1, 2019"
	 * @since v0.1.0
	 */
	public function get_end_date() {
		$date_format = get_option('date_format');
		$date_time = $this->get_end_date_time_object();
		return ( $date_time !== NULL ) ? $date_time->format( $date_format ) : __('[No Event Date]', 'reg-man-rc');
	} // function

	/**
	 * Get the event's end time formatted as a string suitable for showing on the website (note this does not include the time)
	 * If no end time is set for the event then this method returns a special string indicating such.
	 * @return string		The event's end time formatted using the site's time format, e.g. "4:00 PM"
	 * @since v0.1.0
	 */
	public function get_end_time() {
		$time_format = get_option('time_format');
		$date_time = $this->get_end_date_time_object();
		return ( $date_time !== NULL ) ? $date_time->format( $time_format ) : __('[No Event Time]', 'reg-man-rc');
	} // function

	/**
	 * Get the event's start date and time as a DateTimeInterface object
	 * @return	\DateTimeInterface	Event start date and time.  May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_start_date_time_object() {
		if ( ! isset( $this->start_date_time_object ) ) {
			$this->start_date_time_object = $this->get_event_descriptor()->get_event_start_date_time();
		} // endif
		return $this->start_date_time_object;
	} // function

	/**
	 * Get the event's end date and time as a DateTimeInterface object
	 * @return	\DateTimeInterface	Event end date and time.  May be NULL if no end time is assigned.
	 * @since v0.1.0
	 */
	public function get_end_date_time_object() {
		if ( ! isset( $this->end_date_time_object ) ) {
			$this->end_date_time_object = $this->get_event_descriptor()->get_event_end_date_time();
		} // endif
		return $this->end_date_time_object;
	} // function

	private static function get_date_time_now() {
		if ( ! isset( self::$DATE_NOW ) ) {
			self::$DATE_NOW = new \DateTime( 'now', wp_timezone() );
		} // endif
		return self::$DATE_NOW;
	} // function

	/**
	 * Get a boolean indicating whether the event is complete (i.e. in the past)
	 * @return	boolean		TRUE if the event is over, i.e. its end date and time are before the current time, FALSE otherwise
	 * @since v0.1.0
	 */
	public function get_is_event_complete() {
		$end_date_time = $this->get_end_date_time_object();
		$result = ( ! empty( $end_date_time ) && ( $end_date_time < self::get_date_time_now() ) );
//		Error_Log::var_dump( $end_date_time, self::get_date_time_now(), $result );
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether the event is confirmed
	 * @return	boolean		TRUE if the event status is CONFIRMED, otherwise FALSE
	 * @since v0.1.0
	 */
	public function get_is_event_confirmed() {
		$status = $this->get_status();
		$result = ( $status->get_id() === Event_Status::CONFIRMED );
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether the event is tentative
	 * @return	boolean		TRUE if the event status is TENTATIVE, otherwise FALSE
	 * @since v0.1.0
	 */
	public function get_is_event_tentative() {
		$status = $this->get_status();
		$result = ( $status->get_id() === Event_Status::TENTATIVE );
		return $result;
	} // function

	/**
	 * Get a boolean indicating whether the event is cancelled
	 * @return	boolean		TRUE if the event status is CANCELLED, otherwise FALSE
	 * @since v0.1.0
	 */
	public function get_is_event_cancelled() {
		$status = $this->get_status();
		$result = ( $status->get_id() === Event_Status::CANCELLED );
		return $result;
	} // function



	/**
	 * Get the event's start date and time as a DateTimeInterface object with the Wordpress defined local timezone
	 * @return	\DateTimeInterface	Event start date and time object with timezone set to the Wordpress local timezone.
	 * May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_start_date_time_local_timezone_object() {
		if ( ! isset( $this->start_date_time_local_timezone_object ) ) {
			$date_time = $this->get_start_date_time_object();
			if ( $date_time instanceof \DateTimeInterface ) {
				// Make a copy of the date time object then change the copy's timezone
				$this->start_date_time_local_timezone_object = new \DateTime( $date_time->format( \DateTime::ATOM ) );
				$tz_string = get_option( 'timezone_string' );
				try {
					$tz = new \DateTimeZone( $tz_string );
					$this->start_date_time_local_timezone_object->setTimezone( $tz );
				} catch ( \Exception $exc ) {
					/* translators: %1$s is the setting value for the Wordpress timezone. */
					$msg = sprintf( __( 'ERROR: Unknown or invalid timezone setting: %1$s.', 'reg-man-rc' ), $tz_string );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->start_date_time_local_timezone_object;
	} // function

	/**
	 * Get the event's end date and time as a DateTimeInterface object with the Wordpress defined local timezone
	 * @return	\DateTimeInterface	Event end date and time object with timezone set to the Wordpress local timezone.
	 * May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_end_date_time_local_timezone_object() {
		if ( !isset( $this->end_date_time_local_timezone_object ) ) {
			$date_time = $this->get_end_date_time_object();
			if ( $date_time instanceof \DateTimeInterface ) {
				// Make a copy of the date time object then change the copy's timezone
				$this->end_date_time_local_timezone_object = new \DateTime( $date_time->format( \DateTime::ATOM ) );
				$tz_string = get_option( 'timezone_string' );
				try {
					$tz = new \DateTimeZone( $tz_string );
					$this->end_date_time_local_timezone_object->setTimezone( $tz );
				} catch ( \Exception $exc ) {
					/* translators: %1$s is the setting value for the Wordpress timezone. */
					$msg = sprintf( __( 'ERROR: Unknown or invalid timezone setting: %1$s.', 'reg-man-rc' ), $tz_string );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->end_date_time_local_timezone_object;
	} // function


	/**
	 * Get a label containing this event's start and end date and time, e.g. "Sat August 28 2021, 12:00 pm - 4:00 pm".
	 * @param	\DateTime 	$start_date		The DateTime object containing the start date and time for the event
	 * @param	\DateTime 	$end_date		The DateTime object containing the end date and time for the event
	 * @return	string		A string label for the start and end date and time.
	 * @since v0.1.0
	 */
	public function get_event_dates_and_times_label() {
		if ( ! isset( $this->event_dates_and_times_label ) ) {
			$start_date = $this->get_start_date_time_local_timezone_object();
			$end_date = $this->get_end_date_time_local_timezone_object();
			$this->event_dates_and_times_label = self::create_label_for_event_dates_and_times( $start_date, $end_date );
		} // endif
		return $this->event_dates_and_times_label;
	} // function

	/**
	 * Create a label containing an event's start and end date and time, e.g. "Sat August 28 2021, 12:00 pm - 4:00 pm".
	 * @param	\DateTime 	$start_date		The DateTime object containing the start date and time for the event
	 * @param	\DateTime 	$end_date		The DateTime object containing the end date and time for the event
	 * @return	string		A string label for the start and end date and time.
	 * @since v0.1.0
	 */
	public static function create_label_for_event_dates_and_times( $start_date, $end_date ) {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		/* translators: %1$s is the date of an event, %2$s is the start and end times of an event. */
		$date_time_format = _x( '%1$s %2$s', 'A label showing the date and time of an event', 'reg-man-rc' );

		/* translators: %1$s is the start time, or date and time of an event, %2$s is the end time, or date and time. */
		$start_end_format = _x( '%1$s – %2$s', 'A label showing the start and end of an event', 'reg-man-rc' );

		// I will try to separate the dates and the times by containing the different parts within spans
		// This helps to get line breaks correct when the dates are split on multiple lines like in the admin view
		$part_format = '<span class="event-date-time-part">%1$s</span>';

		if ( ! isset( $start_date ) ) {
			$addn_classes = 'no-start';
			$result = '—'; // No start date at all so just print a dash
		} elseif ( ! isset( $end_date ) ) {
			// start date but no end date so just show start date and time
			$date_part = sprintf( $part_format, $start_date->format( $date_format ) );
			$time_part = sprintf( $part_format, $start_date->format( $time_format ) );
			$addn_classes = 'no-end';
			$result = sprintf( $date_time_format, $date_part, $time_part );
		} else {
			$is_complete = ( ! empty( $end_date ) && ( $end_date < self::get_date_time_now() ) );
			$addn_classes = $is_complete ? 'complete' : '';
			$start_date_str = $start_date->format( $date_format );
			$end_date_str = $end_date->format( $date_format );
			$start_time_str = $start_date->format( $time_format );
			$end_time_str = $end_date->format( $time_format );
			if ( $start_date_str == $end_date_str ) {
				// Single day event
				$date_part = sprintf( $part_format, $start_date_str );
				$start_end_times = sprintf( $start_end_format, $start_time_str, $end_time_str );
				$time_part = sprintf( $part_format, $start_end_times );
				$result = sprintf( $date_time_format, $date_part, $time_part );
			} else {
				// Multi-day event
				$start_date_part = sprintf( $part_format, $start_date_str );
				$start_time_part = sprintf( $part_format, $start_time_str );
				$start_date_time = sprintf( $date_time_format, $start_date_part, $start_time_part );
				$end_date_part = sprintf( $part_format, $end_date_str );
				$end_time_part = sprintf( $part_format, $end_time_str );
				$end_date_time = sprintf( $date_time_format, $end_date_part, $end_time_part );
				$result = sprintf( $start_end_format, $start_date_time, $end_date_time );
			} // endif
		} // endif
		return "<span class=\"event-date-time $addn_classes\">$result</span>";
	} // function

	/**
	 * Get the event venue as a Venue object if available
	 * @return	Venue|NULL		The event Venue object if available, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_venue() {
		$result = $this->get_event_descriptor()->get_event_venue();
		return $result;
	} // function

	/**
	 * Get the event location, e.g. "Toronto Reference Library, 789 Yonge Street, Toronto, ON, Canada"
	 * @return	string		Event location if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_location() {
		$result = $this->get_event_descriptor()->get_event_location();
		return $result;
	} // function

	/**
	 * Get the url for the event page if one exists
	 * @return	string		The url for the page that shows this event if it exists, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_page_url() {
		if ( ! isset( $this->event_page_url ) ) {
			$desc = $this->get_event_descriptor();
			$recur_id = ( $desc->get_event_is_recurring() ) ? $this->get_recurrence_id() : NULL;
			$this->event_page_url = $desc->get_event_page_url( $recur_id );
		} // endif
		return $this->event_page_url;
	} // function

	/**
	 * Get the event's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position is not known.
	 *
	 * @return	Geographic_Position	The event's position (co-ordinates) used to map the event if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_geo() {
		return $this->get_event_descriptor()->get_event_geo();
	} // function

	/**
	 * Get the description of the event as a string, e.g. "Fixing all kinds of items!"
	 * @return	string		Event description if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->get_event_descriptor()->get_event_description();
	} // function

	/**
	 * Get the event's categories as an array of strings, e.g. { "Repair Cafe", "Mini Event" }
	 * @return	string[]	An array of strings listing the event's categories.
	 * This may be an empty array if there are no categories or this is not supported by the event descriptor
	 * @since v0.1.0
	 */
	public function get_categories() {
		$desc_cats = $this->get_event_descriptor()->get_event_categories();
		$result = ! empty( $desc_cats ) ? $desc_cats : self::get_default_event_category_names_array();
		return $result;
	} // function

	/**
	 * Get the event's fixer stations as an array of Fixer_Station objects, or an empty array if they are not available
	 * @return	Fixer_Station[]		An array of Fixer_Station objects with the event's fixer stations.
	 * This may be an empty array if there are no categories or this is not supported by the event descriptor
	 * @since v0.1.0
	 */
	public function get_fixer_stations() {
		$stations_array = $this->get_event_descriptor()->get_event_fixer_station_array();
		$result = ! empty( $stations_array ) ? $stations_array : array(); // Make sure it's an array
		return $result;
	} // function

	/**
	 * Get an array containing the name of the default event category to be used in case no category is assigned
	 * @return	string[]	An array of length 1 containing the name of the default category
	 */
	public static function get_default_event_category_names_array() {
		if ( ! isset( self::$DEFAULT_CATEGORIES_ARRAY ) ) {
			$category = Event_Category::get_default_event_category();
			// There should always be a default category but just check and be defensive
			self::$DEFAULT_CATEGORIES_ARRAY = isset( $category ) ? array( $category->get_name() ) : array();
		} // endif
		return self::$DEFAULT_CATEGORIES_ARRAY;
	} // function

	/**
	 * Get a descriptive label for the event including its date and name, e.g. "July 1, 2019 : Repair Cafe at Toronto Reference Library"
	 * @return string	An event label that quickly identifies an event to a human user, suitable for use as a select option or tree node
	 * @since v0.1.0
	 */
	public function get_label() {
		$date_text = $this->get_start_date();
		$summary = $this->get_summary();
		$status = $this->get_status();
		if ( $status === NULL ) {
			// Defensive, the status should always be a valid object
			$status = Event_Status::get_event_status_by_id( Event_Status::CONFIRMED );
		} // endif
		$status_id = $status->get_id();

		$class = $this->get_class();
		if ( $class === NULL ) {
			// Defensive, the status should always be a valid object
			$class = Event_Class::get_event_class_by_id( Event_Class::PUBLIC );
		} // endif
		$class_id = $class->get_id();

		/* translators: %1$s is the date of an event, %2$s is the name of an event. */
		$label_format = _x('%1$s : %2$s', 'A label for an event using its date and name', 'reg-man-rc' );

		/* translators: %1$s is the date of an event, %2$s is the name of an event, %3$s is the class of an event e.g. Private. */
		$label_with_class_indicator_format = _x('%1$s : [%3$s] %2$s',
				'A label for an event using its date, name and event class', 'reg-man-rc' );

		/* translators: %1$s is the date of an event, %2$s is the name of an event, %3$s is the status of an event e.g. Tentative. */
		$label_with_status_indicator_format = _x('%1$s : %2$s (%3$s)',
				'A label for and event using its date, name and event status', 'reg-man-rc' );

		/* translators: %1$s is the date of an event,
		 * %2$s is the name of an event,
		 * %3$s is the class of an event e.g. Private.
		 * %4$s is the status of an event e.g. Tentative.
		 */
		$label_with_class_and_status_indicator_format = _x('%1$s : [%3$s] %2$s (%4$s)',
				'A label for an event using its date, name, event class, and status', 'reg-man-rc' );

		if ( ( $status_id == Event_Status::CONFIRMED ) && ( $class_id == Event_Class::PUBLIC )  ) {
			// Confirmed, public events just have a date and summary
			$result = sprintf( $label_format, $date_text, $summary );
		} elseif ( $status_id == Event_Status::CONFIRMED ) {
			// In this case the event is confirmed but not public so show its class
			$class_text = $class->get_name();
			$result = sprintf( $label_with_class_indicator_format, $date_text, $summary, $class_text );
		} elseif ( $class_id == Event_Class::PUBLIC ) {
			// In this case we know it's public but not confirmed so show its status
			$status_text = $status->get_name();
			$result = sprintf( $label_with_status_indicator_format, $date_text, $summary, $status_text );
		} else {
			// It's not public or confirmed so show its class and status
			$class_text = $class->get_name();
			$status_text = $status->get_name();
			$result = sprintf( $label_with_class_and_status_indicator_format, $date_text, $summary, $class_text, $status_text );
		} // endif
		return $result;
	} // function

	/**
	 * Get the volunteer registration record assigned to this event using set_volunteer_registration().
	 * If no volunteer registration has been assigned then this method returns NULL.
	 * @return Volunteer_Registration|NULL
	 */
	public function get_volunteer_registration() {
		if ( $this->volunteer_registration === FALSE ) {
			$volunteer = Volunteer::get_current_volunteer();
			$event_key = $this->get_key();
			$this->volunteer_registration = Volunteer_Registration::get_registration_for_volunteer_and_event( $volunteer, $event_key );
		} // endif
		return $this->volunteer_registration;
	} // function

	/**
	 * Set the volunteer registration record for this event.
	 * @param Volunteer_Registration	$volunteer_registration
	 */
	public function set_volunteer_registration( $volunteer_registration ) {
		$this->volunteer_registration = $volunteer_registration;
	} // function

	/**
	 * Get the entry ID as a string.  This should be unique for a map that may contain multiple entries.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_id( $calendar_type ) {
		return $this->get_key();
	} // function

	/**
	 * Get the entry title as a string, e.g. "Toronto Reference Library".
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_title( $calendar_type ) {
		
		$result = $this->get_summary(); // start with just the summary

		// Mark events if they are cancelled or tentative
		$status = $this->get_status();
		$status_id = isset( $status ) ? $status->get_id() : Event_Status::CONFIRMED; // Defensive
		if ( ( $status_id == Event_Status::CANCELLED ) || ( $status_id == Event_Status::TENTATIVE ) ) {
			$label_with_status_format = _x( '(%1$s) %2$s ', 'A calendar label for an event using its status and details, e.g. (Tentative) Reference Library Repair Cafe', 'reg-man-rc' );
			$result = sprintf( $label_with_status_format, $status->get_name(), $result );
		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get the entry's start date and time as a string, e.g. "2021-09-28 12:00".
	 *
	 * This interface provides the DATE_FORMAT constant which can be used to format a DateTime object correctly.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string	The entry's start date and time.
	 * Note that if no valid date & time string is provided by the implementor then the calendar will not display the entry.
	 * @since v0.1.0
	 */
	public function get_calendar_entry_start_date_time_string( $calendar_type ) {
		$start_dt = $this->get_start_date_time_local_timezone_object();
		$result = ( ! empty( $start_dt ) ) ? $start_dt->format( Calendar_Entry::DATE_FORMAT ) : NULL;
		return $result;
	} // function

	/**
	 * Get the entry's end date and time as a string, e.g. "2021-09-28 16:00".
	 *
	 * This interface provides the DATE_FORMAT constant which can be used to format a DateTime object correctly.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string	The entry's end date and time.
	 * Note that if no valid date & time string is provided by the implementor then the calendar will not display the entry.
	 * @since v0.1.0
	 */
	public function get_calendar_entry_end_date_time_string( $calendar_type ) {
		$end_dt = $this->get_end_date_time_local_timezone_object();
		$result = ( ! empty( $end_dt ) ) ? $end_dt->format( Calendar_Entry::DATE_FORMAT ) : NULL;
		return $result;
	} // function

	/**
	 * Get the colour used for the map entry or NULL if the default colour should be used.
	 * The result can be any string that is a valid colour in CSS.
	 * For example, '#f00', 'red', 'rgba( 255, 0, 0, 0.5)'.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_colour( $calendar_type ) {
		$category_names = $this->get_categories();
		$first_category_name = isset( $category_names[ 0 ] ) ? $category_names[ 0 ] : NULL;
		if ( ! empty( $first_category_name ) ) {
			$category = Event_Category::get_event_category_by_name( $first_category_name );
			$colour = isset( $category ) ? $category->get_colour() : NULL;
			// Use the categories colour if there is such a thing
			$result = isset( $colour ) ? $colour : self::DEFAULT_EVENT_COLOUR;
		} else {
			$result = self::DEFAULT_EVENT_COLOUR; // if no category then use default colour
		} // endif
		return $result;
	} // function

	/**
	 * Get the html class names to be assigned to the entry or NULL if no classes are needed.
	 * Multiple class names should be contained in a single string separated by spaces.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_class_names( $calendar_type ) {
		if ( ! isset( $this->calendar_entry_class_names ) ) {

			$class_array = array();

			// Completed / Upcoming
			$class_array[] = $this->get_is_event_complete() ? 'completed' : 'upcoming';

			// Status
			$status = $this->get_status();
			$class_array[] = strtolower( $status->get_id() ) . '-status'; // e.g. 'confirmed-status'

			// Class
			$event_class = $this->get_class();
			$class_array[] = strtolower( $event_class->get_id() ) . '-class'; // e.g. 'public-class'

			// Volunteer registration status
			if ( $calendar_type == Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
				$vol_reg = $this->get_volunteer_registration();
				$class_array[] = isset( $vol_reg ) ? 'vol-reg-registered' : 'vol-reg-not-registered';
			} // endif

			// implode the array
			$this->calendar_entry_class_names = implode( ' ', $class_array );

		} // endif
		return $this->calendar_entry_class_names;
	} // function

	/**
	 * Get the content shown in the info window for the entry including any necessary html markup or NULL if no info is needed.
	 * @param string $calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined in the Calendar class
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_info( $calendar_type ) {

		switch( $calendar_type ) {

			case Calendar::CALENDAR_TYPE_VOLUNTEER_REG:
				$view = Volunteer_Registration_View::create_for_calendar_info_window( $this, $calendar_type );
				break;

			case Calendar::CALENDAR_TYPE_ADMIN_EVENTS:
			default:
				$view = Event_View::create_for_calendar_info_window( $this, $calendar_type );
				break;

		} // endswitch

		$result = $view->get_object_view_content();
		return $result;

	} // function


	/**
	 * Get the marker title as a string, e.g. "Toronto Reference Library".
	 * This string is shown on the map when the user hovers over the marker, similar to an element's title attribute.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_title( $map_type ) {
		return $this->get_label();
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

		switch( $map_type ) {
			
			case Map_View::MAP_TYPE_ADMIN_STATS:
			case Map_View::MAP_TYPE_OBJECT_PAGE:
				$result = NULL;
				break;
				
			case Map_View::MAP_TYPE_CALENDAR_ADMIN:
			case Map_View::MAP_TYPE_CALENDAR_EVENTS:
			case Map_View::MAP_TYPE_CALENDAR_VISITOR_REG:
			case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
				
				$calendar_type = Calendar::CALENDAR_TYPE_EVENTS; // This won't matter but is required arg
				$classes = $this->get_calendar_entry_class_names( $calendar_type );
				
				$text = $this->get_start_date(); // start with just the date
				
				// Mark events if they are cancelled or tentative
				$status = $this->get_status();
				$status_id = isset( $status ) ? $status->get_id() : Event_Status::CONFIRMED; // Defensive
				if ( ( $status_id == Event_Status::CANCELLED ) || ( $status_id == Event_Status::TENTATIVE ) ) {
					/* Translators: %1$s is a status like "Cancelled", %2$s is an event date */
					$label_with_status_format = _x( '(%1$s) %2$s ', 'A map marker label for an event using its status and date, e.g. (Tentative) Sat Jun 24, 2023', 'reg-man-rc' );
					$text = sprintf( $label_with_status_format, $status->get_name(), $text );
				} // endif
		
				if ( $map_type === Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG ) {

					$vol_reg = $this->get_volunteer_registration();
					$is_registered = isset( $vol_reg );
					if ( $is_registered ) {
						$classes .= ' vol-reg-registered';
					} // endif

				} // endif

				$result = Map_Marker_Label::create( $text, $classes );
				
				break;
				
		} // endswitch

		return $result;		

	} // function

	/**
	 * Get the marker location as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL	The marker location if it is known, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_location( $map_type ) {
		$event_descriptor = $this->get_event_descriptor();
		$result = $event_descriptor->get_map_marker_location( $map_type );
		return $result;
	} // function
	/**
	 * Get the marker ID as a string.  This should be unique for a map that may contain multiple markers.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type ) {
		return $this->get_key();
	} // function

	/**
	 * Get the marker position as an instance of Geographic_Position.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	Geographic_Position	The geographic position of the map marker
	 * @since v0.1.0
	 */
	public function get_map_marker_geographic_position( $map_type ) {
		$event_descriptor = $this->get_event_descriptor();
		$result = $event_descriptor->get_map_marker_geographic_position( $map_type );
		return $result;
	} // function

	/**
	 * Get the map zoom level to use when this marker is shown on a map by itself.
	 *
	 * This will determine the zoom setting for the map when no other markers are present.
	 * 0 is the entire world, 22 is the maximum zoom.
	 * If NULL is returned then some default zoom level will be used.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	int|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_zoom_level( $map_type ) {
		$event_descriptor = $this->get_event_descriptor();
		$result = $event_descriptor->get_map_marker_zoom_level( $map_type );
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
	public function get_map_marker_colour( $map_type )	{
		$event_descriptor = $this->get_event_descriptor();
		$result = $event_descriptor->get_map_marker_colour( $map_type );
		return $result;
	} // function

	/**
	 * Get the opacity used for the map marker or NULL if the default opacity of 1 should be used.
	 * The result must be a number between 0 and 1, zero being completely transparent, 1 being completely opaque.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|float|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_opacity( $map_type ) {
		return $this->get_is_event_complete() ? 0.5 : 1;
	} // endif

	/**
	 * Get the content shown in the info window for the marker including any necessary html markup or NULL if no info is needed.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_info( $map_type ) {

		switch( $map_type ) {

			case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
				$view = Volunteer_Registration_View::create_for_map_info_window( $this, $map_type );
				$result = $view->get_object_view_content();
				break;

			default:
				$view = Event_View::create_for_map_info_window( $this, $map_type );
				$result = $view->get_object_view_content();
				break;

		} // endswitch
		
		return $result;
		
	} // function


	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string[][]	An associative array of event attributes including key, event name, start date and time etc.
	 * @since v0.1.0
	 */
	public function jsonSerialize() : array {
		$descriptor = $this->get_event_descriptor();
		$status = $this->get_status();
		$status_id = isset( $status ) ? $status->get_id() : Event_Status::CONFIRMED;
		$class = $this->get_class();
		$class_id = isset( $class ) ? $class->get_id() : Event_Class::PUBLIC;

		$result = array(
			'key'				=> $this->get_key(),
			'summary'			=> $descriptor->get_event_summary(), // avoid getting 'No Summary' from this object's get_summary()
			'status'			=> $status_id,
			'class'				=> $class_id,
			'dtstart'			=> $this->get_start_date_time_object(),
			'dtend'				=> $this->get_end_date_time_object(),
			'location'			=> $descriptor->get_event_location(),
			'geo'				=> $descriptor->get_event_geo(),
			'description'		=> $descriptor->get_event_description(),
			'url'				=> $this->get_event_page_url(), // a link to my internal page showing the event
			'uid'				=> $descriptor->get_event_uid(),
			'categories'		=> $descriptor->get_event_categories(),
		);
		return $result;
	} // function
	
	/**
	 * Get a string to represent this event.
	 * Note that this is used to compare events for equality, array_diff() and so on.
	 * @return string	The event's key as a string
	 */
	public function __toString() {
		$result = $this->get_key();
		return $result;
	} // function

} // class