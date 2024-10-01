<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Volunteer_Registration_View;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Stats\Item_Descriptor_Factory;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor_Factory;
use Reg_Man_RC\Model\Stats\Supplemental_Visitor_Registration;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\View\Event_Descriptor_View;

/**
 * Describes an instance of an event.
 *
 * An instance of this class contains the information related to one instance of an event.
 * Note that this differs from an Event_Descriptor which may describe a series of recurring events.
 *
 * A recurring event will have a single Event_Descriptor and multiple associated Event objects.
 * For recurring events, the Event object will include the specific start ande end date and time
 * for one event instance, and its recurrence date.
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
	 * A string used to format a \DateTime object and return a string suitable for use as a recurrence date.
	 * Use this constant to generate the recurrence date when the event time is not required to differentiate events,
	 * that is there is only one occurrence of the recurring event on any given date.
	 * @since v0.1.0
	 */
	private static $RECUR_DATE_FORMAT_DATE = 'Ymd';

	/**
	 * A string used to format a \DateTime object and return a string suitable for use as a recurrence date.
	 * Use this constant only if the recurrence date must contain a time, that is there are two occurrences of the
	 * recurring event on the same day.
	 * @since v0.1.0
	 */
	// This is the ICalendar date-time format (can have Z at the end for UTC time which I think this always is) 
	private static $RECUR_DATE_FORMAT_DATE_TIME = 'Ymd\THis';

	private static $DEFAULT_CATEGORIES_ARRAY; // Store the array of default categories so we can reuse it
	
	/**
	 * A flag used to indicate that the event key does not match any known event
	 * @var boolean
	 */
	private $is_placeholder_event = FALSE;

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
	 * A string containing the recurrence date for a repeating event.
	 * If an event is not repeating, this will be NULL or an empty string.
	 * @var	string
	 * @since v0.1.0
	 */
	private $recurrence_date;

	/**
	 * The event's summary, e.g. "Repair Café at Toronto Reference Library"
	 * @var	string
	 * @since v0.1.0
	 */
	private $summary;

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
	 * A string containing the start date formatted according to the website's date format and timezone
	 * @var	string
	 * @since v0.1.0
	 */
	private $start_date_string_in_display_format;

	/**
	 * A string containing the start date formatted so that it can be used as data in an event key
	 * @var	string
	 * @since v0.6.0
	 */
	private $start_date_string_in_data_format;

	/**
	 * A string containing a labelling showing the event's start and end date and time
	 * @var	string
	 * @since v0.1.0
	 */
	private $event_dates_and_times_label;

	/**
	 * The event's status
	 * @var	Event_Status
	 * @since v0.1.0
	 */
	private $event_status;

	/**
	 * A string containing the url for the event's internal page.
	 * Note that this will be a local virtual page created by this plugin showing an event which may be an instance of
	 * a repeating event or an event defined externally by another plugin or another system like Google Calendar
	 * @var	string
	 * @since v0.1.0
	 */
	private $event_page_url;

	/**
	 * A string containing a human-readable label for the event including it's date and summary.
	 * @var	string
	 * @since v0.1.0
	 */
	private $label;
	
	private $event_marker_text;

	/** A set of HTML class names associated with the calendar entry for this event */
	private $calendar_entry_class_names;

	/** The colour for the marker used to mark this event on a map */
	private $map_marker_colour;

	/** The volunteer registration record for the current volunteer (if one exists) for this event */
	private $current_volunteer_registration;
	
	private $events_collection; // used to determine stats
	private $total_item_stats_collection;
	private $total_visitor_stats_collection;
	private $total_volunteer_stats_collection;
	private $total_items_count;
	private $total_visitors_count;
	private $total_volunteers_count;
	
	/**
	 * Get all events currently defined in the system
	 * @param	boolean			$is_include_placeholder_events	Flag to indicate whether the result should include
	 * placeholder events derived from item and volunteer registrations but not found in the set of declared events
	 * @return	\Reg_Man_RC\Model\Event[]	An array of all events
	 * @since v0.1.0
	 */
	public final static function get_all_events( $is_include_placeholder_events = FALSE ) {
		
		$result = array();
		$descriptor_array = Event_Descriptor_Factory::get_all_event_descriptors();
		$result = self::get_events_array_for_event_descriptors_array( $descriptor_array );
		
		if ( $is_include_placeholder_events ) {
			$start_date_time = NULL;
			$end_date_time = NULL;
			$placeholder_events = self::get_placeholder_events( $start_date_time, $end_date_time, $result );
			$result = array_merge( $result, $placeholder_events );
		} // endif
		
		return $result;

	} // function

	/**
	 * Get the array of events described by the specified event descriptor
	 * @param	Event_Descriptor	$event_descriptor
	 * @return	Event[]
	 */
	public static function get_events_array_for_event_descriptor( $event_descriptor ) {
		if ( $event_descriptor instanceof Event_Descriptor ) {
			$result = self::get_events_array_for_event_descriptors_array( array( $event_descriptor ) );
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
	private static function get_events_array_for_event_descriptors_array( $descriptor_array ) {
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
	 * @param	boolean			$is_include_placeholder_events	Flag to indicate whether the result should include
	 * placeholder events derived from item and volunteer registrations but not found in the set of known events
	 * @return	Event[]	An array of events accepted by the specified filter
	 * @since v0.1.0
	 */
	public final static function get_all_events_by_filter( $event_filter, $is_include_placeholder_events = FALSE ) {
		
		$result = array();

		// TODO: I could have Event_Desc_Factory::get_event_descriptors_in( class_arr, status_arr, cat_arr )
		//  to make the list smaller, or get_event_desc_in_range( min_date, max_date ) or _in_filter( event_filter )
		//  which would start with non-repeating events selected and then add repeaters
		// It would be a performance improvement if there are problems
		
		$descriptor_array = Event_Descriptor_Factory::get_all_event_descriptors();
// Error_Log::var_dump( $descriptor_array );
		$result = self::get_events_array_for_event_descriptors_array( $descriptor_array );

		if ( $is_include_placeholder_events ) {
			$start_date_time = isset( $event_filter ) ? $event_filter->get_accept_minimum_date_time() : NULL;
			$end_date_time = isset( $event_filter ) ? $event_filter->get_accept_maximum_date_time() : NULL;
			$placeholder_events = self::get_placeholder_events( $start_date_time, $end_date_time, $result );
			$result = array_merge( $result, $placeholder_events );
		} // endif
		
		// Filter the final result if necessary
		if ( isset( $event_filter ) ) {
			$result = $event_filter->apply_filter( $result );
		} // endif

//	Error_Log::var_dump( count( $result ) );
		return $result;
	} // function

	/**
	 * Get the array of events that the current user is able to register items for
	 * @return Event[]
	 */
	public static function get_events_array_current_user_can_register_items() {

		$current_user_id = get_current_user_id();

		if ( empty( $current_user_id ) || ! current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {
			
			// There is no user logged in, or the current user cannot create/edit Items
			
			$result = array();

		} else {
			
			// This current user can register items, so figure out which events are valid

			if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

				// Users who can edit_others_events can register items to any event
				$result = self::get_all_events();
				
			} elseif ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
				// Users who can edit_events but not edit_others_events can only register items to their own events
				$all_events = self::get_all_events();
				$filter = Event_Filter::create();
				$filter->set_accept_event_author_id( $current_user_id );
				$result = $filter->apply_filter( $all_events );
				
			} elseif ( current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {
				
				// Users who cannot edit events but can edit items can register items to all events
				$result = self::get_all_events();
				
			} else {
				
				// Other users cannot register items to any events
				$result = array();
				
			} // endif

		} // endif
		
		return $result;

	} // function

	/**
	 * Get the array of events that the current user is able to register volunteers for
	 * @return Event[]
	 */
	public static function get_events_array_current_user_can_register_volunteers() {

		$current_user_id = get_current_user_id();

		if ( empty( $current_user_id ) || ! current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {
			
			// There is no user logged in, or the current user cannot create/edit Volunteer registrations
			
			$result = array();

		} else {
			
			// This current user can register volunteers, so figure out which events are valid

			if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

				// Users who can edit_others_events can register volunteers to any event
				$result = self::get_all_events();
				
			} elseif ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
				// Users who can edit_events but not edit_others_events can only register volunteers to their own events
				$all_events = self::get_all_events();
				$filter = Event_Filter::create();
				$filter->set_accept_event_author_id( $current_user_id );
				$result = $filter->apply_filter( $all_events );
				
			} // endif

		} // endif
		
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
//				$event_desc_id = $event_descriptor->get_event_descriptor_id();
//				$provider_id = $event_descriptor->get_provider_id();
				foreach ( $recur_dates as $date_pair ) {
//					Error_Log::var_dump( $date_pair['start'] );
					$event = self::create_for_event_descriptor( $event_descriptor, $date_pair['start'], $date_pair['end'] );
					$key = $event->get_key_string();
					$result[ $key ] = $event;
				} // endfor
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get a single event using its event key
	 * @param	string|Event_Key	$event_key	The key for the event as a string or an instance of EventKey, returned by Event::get_event_key_string();
	 * @return	Event|NULL			The event specified by the key or NULL if the event is not found or the key is invalid
	 * Note that if the event key includes a recurrence date then it must refer to a recurring event descriptor or NULL is returned.
	 * Similarly, if the event key specifies no recurrence date, it must refer to a non-recurring event descriptor or NULL is returned.
	 * @since v0.1.0
	 */
	public static final function get_event_by_key( $event_key ) {
		
		if ( ! ( $event_key instanceof Event_Key ) ) {
			$event_key = Event_Key::create_from_string( strval( $event_key ) );
		} // endif
		
		if ( $event_key instanceof Event_Key ) {
			
			// Event cache??? Cache events by key string?

			$event_key_date_string = $event_key->get_event_date_string();
			$event_desc_id = $event_key->get_event_descriptor_id();
			$provider_id = $event_key->get_provider_id();
			
			$event_descriptor = Event_Descriptor_Factory::get_event_descriptor_by_id( $event_desc_id, $provider_id,  );

			if ( $event_descriptor == NULL ) {

				$result = NULL;
				
			} else {
				
				if ( ! $event_descriptor->get_event_is_recurring() ) {
					
					// We have a non-recurring event so the event object can be contructed using the descriptor
					$event = self::create_for_event_descriptor( $event_descriptor );
					// Make sure the dates match, otherwise the event doesn't really exist
					$event_date_string = isset( $event ) ? $event->get_start_date_string_in_data_format() : NULL;
//					Error_Log::var_dump( $event, $event_date_string );
					$result = isset( $event_date_string ) && ( $event_date_string == $event_key_date_string ) ? $event : NULL;
					
				} else {
					
					// This is a recurring event, we need to find the right date
					$recurring_events = self::get_recurring_events( $event_descriptor );
					$result = NULL; // I'm giong to search through my recurring event instances and look for the date
					foreach( $recurring_events as $event ) {
						if ( $event->get_start_date_string_in_data_format() == $event_key_date_string ) {
							$result = $event;
							break;
						} // endif
					} // endfor
					
				} // endif
				
			} // endif

			if ( isset( $result ) ) {
				$result->key_object = $event_key; // We might as well save the key while we have it
			} // endif
			
		} else {

			$result = NULL; // The key was not an instance of Event_Key
			
		} // endif
				
		return $result;
	} // function

	/**
	 * Get an event object based on its descriptor ID, provider ID and optional recurrence date string
	 * @param string $descriptor_id
	 * @param string $provider_id
	 * @param string $recurrence_date_string
	 * @return Event
	 */
	public static function get_event_by_descriptor_id( $descriptor_id, $provider_id = NULL, $recurrence_date_string = NULL ) {
		
		$event_descriptor = Event_Descriptor_Factory::get_event_descriptor_by_id( $descriptor_id, $provider_id );
		
		if ( ! isset( $event_descriptor ) ) {
			
			$result = NULL;
			
		} elseif ( ! $event_descriptor->get_event_is_recurring() ) {
			
			// We have a non-recurring event so the event object can be contructed using the descriptor
			$result = self::create_for_event_descriptor( $event_descriptor );
			
		} else {
			
			// This is a recurring event, we need to find the right date
			$recurring_events = self::get_recurring_events( $event_descriptor );
			$result = NULL; // I'm giong to search through my recurring event instances and look for the date
			foreach( $recurring_events as $event ) {
				if ( $event->get_recurrence_date() == $recurrence_date_string ) {
					$result = $event;
					break;
				} // endif
			} // endfor
			
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

	/**
	 * Get the placeholder events for all known item and volunteer registrations in the specified date range,
	 *  and not appearing in the specified array of known events
	 * @param \DateTime	$start_date_time
	 * @param \DateTime $end_date_time
	 * @param Event[]	$known_events_array
	 * @return Event[]
	 */
	private static function get_placeholder_events( $start_date_time, $end_date_time, $known_events_array ) {
		
		$result = array();

		$min_key_date_string = isset( $start_date_time ) ? $start_date_time->format( Event_Key::EVENT_DATE_FORMAT ) : NULL;
		$max_key_date_string = isset( $end_date_time ) ? $end_date_time->format( Event_Key::EVENT_DATE_FORMAT ) : NULL;
		
		// Get event keys for any events that have Items or Volunteers registered
		
		$item_reg_key_strings_array = Item_Descriptor_Factory::get_event_key_strings_for_items_in_date_range( $min_key_date_string, $max_key_date_string );
//	Error_Log::var_dump( $item_reg_key_strings_array );
	
		$vol_reg_key_strings_array = Volunteer_Registration_Descriptor_Factory::get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string );
//	Error_Log::var_dump( $vol_reg_key_strings_array );

		// Note that visitor registrations are covered in the set of item registrations above
		//  with the exception of supplemental visitor info added for specific events.  We need to add those.
		$vis_reg_key_strings_array = Supplemental_Visitor_Registration::get_event_key_strings_for_visitor_registrations_in_date_range( $min_key_date_string, $max_key_date_string );
//	Error_Log::var_dump( $vis_reg_key_strings_array );

		$reg_key_strings_array = array_merge( $item_reg_key_strings_array, $vol_reg_key_strings_array, $vis_reg_key_strings_array );
//	Error_Log::var_dump( $item_reg_key_strings_array, $vol_reg_key_strings_array, $vis_reg_key_strings_array, $reg_key_strings_array );
		$reg_key_strings_array = array_unique( $reg_key_strings_array );
//	Error_Log::var_dump( $reg_key_strings_array );
		
		if ( ! empty( $reg_key_strings_array ) ) {

			// There are registrations in this timeframe
			// If any of their events are not already known then we need to add them
			$keyed_events_array = array();
			foreach( $known_events_array as $event ) {
				$key_string = $event->get_key_string();
				$keyed_events_array[ $key_string ] = $event;
			} // endfor
			
			foreach( $reg_key_strings_array as $reg_key_string ) {
				if ( ! in_array( $reg_key_string, $keyed_events_array ) ) {
//					Error_Log::var_dump( $reg_key_string );
					$placeholder = Event::create_placeholder_event( $reg_key_string ); // create placeholder
					$keyed_events_array[ $reg_key_string ] = $placeholder; // Mark the events we already know
					$result[] = $placeholder; // Add new placeholder to the result
				} // endif
			} // endfor
			
		} // endif
		
//	Error_Log::var_dump( count( $result ) );
		return $result;
		
	} // function

	
	/**
	 * Create a placeholder event for an event key that does not match any known event
	 *
	 * @param	Event_Key	$event_key_string	The key string for the event
	 * @return	Event	The newly created Event object
	 * @since v0.6.0
	 */
	public static function create_placeholder_event( $event_key_string ) {
		if ( empty( $event_key_string ) ) {
			$result = NULL;
		} else {
			$event_key = Event_Key::create_from_string( $event_key_string );
			$date_string = $event_key->get_event_date_string();
			$provider_id = $event_key->get_provider_id();
			$descriptor_id = $event_key->get_event_descriptor_id();
			$start_date_time = \DateTime::createFromFormat( Event_Key::EVENT_DATE_FORMAT, $date_string, wp_timezone() );
	//		Error_Log::var_dump( $start_date_time );
			if ( empty( $start_date_time ) ) {
	
				$start_date_time = NULL;
				$end_date_time = NULL;
	
			} else {
	
				$end_date_time = clone $start_date_time;
	
				// Set the start time
				$start_time = Settings::get_default_event_start_time();
				$time_parts = explode( ':', $start_time );
				$hours = isset( $time_parts[ 0 ] ) ? $time_parts[ 0 ] : 0;
				$minutes = isset( $time_parts[ 1 ] ) ? $time_parts[ 1 ] : 0;
				$start_date_time->setTime( $hours, $minutes );
				
				// Set the end time
				$end_time = Settings::get_default_event_end_time();
				$time_parts = explode( ':', $end_time );
				$hours = isset( $time_parts[ 0 ] ) ? $time_parts[ 0 ] : 0;
				$minutes = isset( $time_parts[ 1 ] ) ? $time_parts[ 1 ] : 0;
				$end_date_time->setTime( $hours, $minutes );
				
			} // endif
			
			$event_descriptor = Event_Descriptor_Factory::get_event_descriptor_by_id( $descriptor_id, $provider_id );
			if ( empty( $event_descriptor ) ) {
				// Get a placeholder descriptor if the real one is not found
				$event_descriptor = Placeholder_Event_Descriptor::create( $provider_id, $descriptor_id );
			} // endif
			
			$result = self::create_for_event_descriptor( $event_descriptor, $start_date_time, $end_date_time );
			$result->is_placeholder_event = TRUE;
			$result->event_descriptor = $event_descriptor;
		
		} // endif
		
		return $result;
	} // function

	/**
	 * Get the flag indicating whether this event is a placeholder for one whose key could not be found
	 * @return	boolean	TRUE if this event is a placeholder, FALSE otherwise
	 * @since v0.1.0
	 */
	public function get_is_placeholder_event() {
		return $this->is_placeholder_event;
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
	 * Get the system-unique key for this event as a string
	 * @return	string	A key that can be used to uniquely identify the event on the system
	 * @since v0.1.0
	 */
	public function get_key_string() {
		return $this->get_key_object()->get_as_string();
	} // function

	/**
	 * Get the object containing the system-unique key for this event
	 * @return	Event_Key	A system-unique key object containing the event id, provider id, and recurrence date
	 * @since v0.1.0
	 */
	public function get_key_object() {
		if ( ! isset( $this->key_object ) ) {
			$this->key_object = Event_Key::create(
				$this->get_start_date_time_object(),
				$this->get_event_descriptor_id(),
				$this->get_provider_id(),
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
		return Event_Provider_Factory::get_provider_name_by_id( $this->get_provider_id() );
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
	 * Get the recurrence date for the event if this is a recurring event, otherwise NULL.
	 *
	 * This method returns the recurrence date for this instance of the recurring event descriptor.
	 * The result is a string that specifies either a date or a date and time.
	 * For events that repeat multiple times on the same day, a date and time is used, otherwise just a date.
	 * A date (with no time) must be like this: 20201023.  A date with time must be like this: 20201023T120000.
	 * The recurrence date is used when events are shared across systems,
	 * for example imported and exported, or used in an iCalendar feed.
	 * Recurring events may have the same uid but then must have different recurrence dates
	 *
	 * @return	string		The recurrence date.
	 * @since v0.1.0
	 */
	public function get_recurrence_date() {
		if ( ! isset( $this->recurrence_date ) ) {
			$rule = $this->get_event_descriptor()->get_event_recurrence_rule();
			if ( $rule !== NULL ) {
				$freq = $rule->get_frequency();
				switch ( $freq ) {
					case Recurrence_Rule::SECONDLY:
					case Recurrence_Rule::MINUTELY:
					case Recurrence_Rule::HOURLY:
						$format = self::$RECUR_DATE_FORMAT_DATE_TIME;
						break;
					default:
						$format = self::$RECUR_DATE_FORMAT_DATE;
						break;
				} // endswitch
				// FIXME - this date/time is in local timezone!
				$start_date_time = $this->get_start_date_time_object(); 
				$this->recurrence_date = $start_date_time->format( $format );
			} else {
				$this->recurrence_date = NULL;
			} // endif
		} // endif
		return $this->recurrence_date;
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
	 * Get the WordPress user ID of the author of this event, if known
	 * @return	int|string	The WordPress user ID of the author of this event if it is known, otherwise NULL or 0.
	 * @since v0.6.0
	 */
	public function get_author_id() {
		return $this->get_event_descriptor()->get_event_author_id();
	} // function

	/**
	 * Get a boolean indicating whether the current user has authority to register items for this event
	 * @return boolean
	 * @since v0.6.0
	 */
	public function get_is_current_user_able_to_register_items() {
		
		$result = FALSE; // Assume this user cannot register items

		if ( current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {

			if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

				// Users who can edit_others_events can register items to any event
				$result = TRUE; // this user can edit anybody's events so they can register items
				
			} elseif ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
				// Users who can edit_events but not edit_others_events can only register items to their own events
				$result = $this->get_is_current_user_event_author();
				
			} else {
			
				// Users who cannot edit_events (but can edit_items) can register items to any event
				$result = TRUE;
				
			} // endif

		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get a boolean indicating whether the current user has authority to register volunteers for this event
	 * @return boolean
	 * @since v0.6.0
	 */
	public function get_is_current_user_able_to_register_volunteers() {
		
		$result = FALSE; // Assume this user cannot register volunteers

		if ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {

			if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

				// Users who can edit_others_events can register volunteers to any event
				$result = TRUE; // this user can edit anybody's events so they can register volunteers
				
			} elseif ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
				// Users who can edit_events but not edit_others_events can only register volunteers to their own events
				$result = $this->get_is_current_user_event_author();
				
			} else {
			
				// Users who cannot edit_events (but can edit_volunteer_reg) can register volunteers to any event
				$result = TRUE;
				
			} // endif

		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Get a boolean indicating whether the current user has authority to view the email addresses
	 *  of the registered volunteers for this event
	 * @return boolean
	 * @since v0.8.6
	 */
	public function get_is_current_user_able_to_view_registered_volunteer_emails() {
		
		$result = FALSE; // Assume this user cannot

		if ( is_admin() ) {

			if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {

				// Users who can edit_others_volunteers can see the email address of any volunteer
				$result = TRUE;

			} elseif ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
				// TODO: We may need a setting whether to allow event authors (like community event organizers)
				//  to see the emails of the volunteers who have registered for their event
				// Right now,they always can but some organizations may wish to restrict this
				
				// Users who can edit_events but not edit_others_events can only register volunteers to their own events
				$result = $this->get_is_current_user_event_author();
				
			} // endif

		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Get the email addresses of volunteers registered to attend this event, if the user has the authority to view them
	 * @return string[]
	 */
	public function get_registered_volunteer_emails() {
		$result = array();
		if ( $this->get_is_current_user_able_to_view_registered_volunteer_emails() ) {
			$reg_array = Volunteer_Registration::get_all_registrations_for_event( $this->get_key_string() );
			foreach( $reg_array as $vol_reg ) {
				$email = $vol_reg->get_volunteer_email();
				if ( ! empty( $email ) ) {
					$result[] = $email;
				} // endif
			} // endfor
		} // endif
		return $result;
	} // endif
	
	private function get_is_current_user_event_author() {
		$result = ( get_current_user_id() == $this->get_author_id() );
		return $result;
	} // function
	
	/**
	 * Get the event's status represented as an instance of the Event_Status class.
	 * @return	Event_Status	The event's status.
	 * @since v0.1.0
	 */
	public function get_status() {
		if ( ! isset( $this->event_status ) ) {
			$this->event_status = $this->get_event_descriptor()->get_event_status( $this->get_start_date_time_object() );
		} // endif
		return $this->event_status;
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
	public function get_start_date_string_in_display_format() {
		if ( ! isset( $this->start_date_string_in_display_format ) ) {
			$date_time = $this->get_start_date_time_object();
			if ( $date_time instanceof \DateTimeInterface ) {
				$date_format = get_option( 'date_format' );
				$this->start_date_string_in_display_format = $date_time->format( $date_format );
			} else {
				$this->start_date_string_in_display_format = __( '[No event date]', 'reg-man-rc' );
			} // endif
		} // endif
		return $this->start_date_string_in_display_format;
	} // function

	/**
	 * Get the event's start date formatted as a string suitable for use as data.
	 * If no start date is set for the event then this method returns .
	 * @return string	The event's start date formatted for use as data, e.g. "20190701"
	 * @since v0.1.0
	 */
	public function get_start_date_string_in_data_format() {
		if ( ! isset( $this->start_date_string_in_data_format ) ) {
			$date_time = $this->get_start_date_time_object();
			if ( $date_time instanceof \DateTimeInterface ) {
				$date_format = Event_Key::EVENT_DATE_FORMAT;
				$this->start_date_string_in_data_format = $date_time->format( $date_format );
			} else {
				$this->start_date_string_in_data_format = __( '00000000', 'reg-man-rc' );
			} // endif
		} // endif
		return $this->start_date_string_in_data_format;
	} // function

	/**
	 * Get the event's start date and time as a DateTimeInterface object.
	 * Note that the timezone for the date from the event descriptor is ALWAYS set to local time, i.e. wp_timezone()
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
	 * Note that the timezone for the date from the event descriptor is ALWAYS set to local time, i.e. wp_timezone()
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
	 * Get a boolean indicating whether volunteers are allowed to register for this event.
	 * TRUE when the event is not complete, is confirmed or is tentative and volunteers are allowed to register
	 *  for tentative events.
	 * Note that this does not test whether the event appears on the volunteer registration calendar.
	 * @return boolean
	 */
	public function get_is_allow_volunteer_registration() {

		if ( $this->get_is_event_complete() ) {
			
			$result = FALSE;
			
		} else {
			
			$status = $this->get_status();
			$status_ID = $status->get_id();
			switch( $status_ID ) {
				
				case Event_Status::CONFIRMED:
					$result = TRUE;
					break;

				case Event_Status::TENTATIVE:
					$result = Settings::get_is_allow_volunteer_registration_for_tentative_events();
					break;

				default:
				case Event_Status::CANCELLED:
					$result = FALSE;
					break;
				
			} // endswitch
		} // endif
		
		return $result;
		
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
			$start_date = $this->get_start_date_time_object();
			$end_date = $this->get_end_date_time_object();
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
			if ( $desc->get_event_is_recurring() ) {
				$recur_date = $this->get_start_date_string_in_data_format();
			} else {
				$recur_date = NULL;
			} // endif
			$this->event_page_url = $desc->get_event_page_url( $recur_date );
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
	 * Get the marker text for this event, e.g. "TENTATIVE".
	 * @return	string	The event's marker text.
	 * @since v0.9.5
	 */
	private function get_event_marker_text() {
		if ( ! isset( $this->event_marker_text ) ) {
			$event_descriptor = $this->get_event_descriptor();
			$event_date = $this->get_start_date_time_object();
			$this->event_marker_text = Event_Descriptor_View::get_event_marker_text( $event_descriptor, $event_date );
		} // endif
		return $this->event_marker_text;
	} // function

	
	
	/**
	 * Get a descriptive label for the event including its date and summary, e.g. "July 1, 2019 : Repair Cafe at Toronto Reference Library"
	 * @return string	An event label that quickly identifies an event to a human user, suitable for use as a select option or tree node
	 * @since v0.1.0
	 */
	public function get_label() {

		/* Translators: %1$s is the date of an event, %2$s is the name of an event. */
		$label_format = _x( '%1$s : %2$s', 'A label for an event using its date and name', 'reg-man-rc' );

		$date_text = $this->get_start_date_string_in_display_format();

		$is_placeholder = $this->get_is_placeholder_event();
		if ( $is_placeholder ) {
			// This is an event whose key does not match any known event
			$event_descriptor = $this->get_event_descriptor();
			if ( ! $event_descriptor instanceof Placeholder_Event_Descriptor ) {
				// This is an event whose descriptor was found but the date was invalid
				/* Translators: %1$s is an invalid date used to register items or volunteers that does not match the correct event date */
				$invalid_date_format = __( '[Invalid date] %1$s', 'reg-man-rc' );
				$date_text = sprintf( $invalid_date_format, $date_text );
			} // endif
		} // endif
		
		$summary = $this->get_summary();
		
		$marker_text = $this->get_event_marker_text();
		
		if ( ! empty( $marker_text ) ) {
			/* Translators: %1$s is marker text for an event like "TENTATIVE", %2$s is the name of an event. */
			$name_format = _x( '%1$s %2$s', 'A name for an event including its status marker text like "Tentative"', 'reg-man-rc' );
			$summary = sprintf( $name_format, $marker_text, $summary );
		} // endif
		
		$result = sprintf( $label_format, $date_text, $summary );
		return $result;
		
	} // function

	/**
	 * Get the volunteer registration record assigned to this event for the volunteer in the current request.
	 * If no volunteer registration has been assigned then this method returns FALSE.
	 * @return Volunteer_Registration|boolean
	 */
	public function get_volunteer_registration_for_current_request() {
		if ( ! isset( $this->current_volunteer_registration ) ) {
			$volunteer = Volunteer::get_volunteer_for_current_request();
			$event_key = $this->get_key_string();
			$vol_reg = Volunteer_Registration::get_registration_for_volunteer_and_event( $volunteer, $event_key );
			$this->current_volunteer_registration = isset( $vol_reg ) ? $vol_reg : FALSE; // Use FALSE to indicate none
		} // endif
		return $this->current_volunteer_registration;
	} // function
	
	private function get_events_collection() {
		if ( ! isset( $this->events_collection ) ) {
			$this->events_collection = Events_Collection::create_for_events_array( array( $this ) );
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
	 * Get the entry ID as a string.  This should be unique for a map that may contain multiple entries.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_id( $calendar_type ) {
		return $this->get_key_string();
	} // function

	/**
	 * Get the entry title as a string, e.g. "Toronto Reference Library".
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_title( $calendar_type ) {
		
		$event_summary = $this->get_summary();

		// Mark events if they are cancelled, tentative, private etc.
		$marker_text = $this->get_event_marker_text();
		if ( ! empty( $marker_text ) ) {
			
			/* Translators: %1$s is a status marker text like "TENTATIVE", %2$s is an event summary */
			$label_with_marker_format = _x( '%1$s %2$s', 'A calendar entry title for an event with its status, e.g. TENTATIVE Reference Library Repair Cafe', 'reg-man-rc' );
			$result = sprintf( $label_with_marker_format, $marker_text, $event_summary );

		} else {

			$result = $event_summary;
			
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
		$start_dt = $this->get_start_date_time_object();
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
		$end_dt = $this->get_end_date_time_object();
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
			
			switch( $calendar_type ) {
				
				case Calendar::CALENDAR_TYPE_ADMIN:
					// Placeholders
					$is_placeholder = $this->get_is_placeholder_event();
					if( $is_placeholder ) {
						$class_array[] = 'event-placeholder';
					} // endif
					break;
					
				case Calendar::CALENDAR_TYPE_VOLUNTEER_REG:
					// Volunteer registrations
					$vol_reg = $this->get_volunteer_registration_for_current_request();
					$class_array[] = ! empty( $vol_reg ) ? 'vol-reg-registered' : 'vol-reg-not-registered';
					break;

				case Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR:
					// Events with registered items
					$items_count = $this->get_total_items_count();
					$vol_count = $this->get_total_volunteers_count();
					if ( ( $items_count > 0 ) || ( $vol_count > 0 ) ) {
						$class_array[] = 'event-with-registrations';
					} // endif
					break;
					
			} // endswitch

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

			case Calendar::CALENDAR_TYPE_ADMIN:
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
			default:
				$result = NULL;
				break;
				
			case Map_View::MAP_TYPE_CALENDAR_ADMIN:
			case Map_View::MAP_TYPE_CALENDAR_VISITOR_REG:
			case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
			case Map_View::MAP_TYPE_CALENDAR_EVENTS:
				
				// Get the right calendar type for this map type
				// TODO: Should there be a method like get_calendar_type_by_map_type() ?
				if ( $map_type === Map_View::MAP_TYPE_CALENDAR_ADMIN ) {

					$calendar_type = Calendar::CALENDAR_TYPE_ADMIN;

				} elseif ( $map_type === Map_View::MAP_TYPE_CALENDAR_VISITOR_REG ) {

					$calendar_type = Calendar::CALENDAR_TYPE_VISITOR_REG;

				} elseif ( $map_type === Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG ) {

					$calendar_type = Calendar::CALENDAR_TYPE_VOLUNTEER_REG;

				} else {

					$calendar_type = Calendar::CALENDAR_TYPE_EVENTS;

				} // endif
				
				$classes = $this->get_calendar_entry_class_names( $calendar_type );
				
				$text = $this->get_start_date_string_in_display_format(); // start with just the date
				
				// Mark events if they are cancelled, tentative, private etc.
				$marker_text = $this->get_event_marker_text();
				if ( ! empty( $marker_text ) ) {
					/* Translators: %1$s is a status marker text like "TENTATIVE", %2$s is an event summary */
					$label_with_marker_format = _x( '%1$s %2$s', 'A map marker label for an event with its status, e.g. TENTATIVE Reference Library Repair Cafe', 'reg-man-rc' );
					$text = sprintf( $label_with_marker_format, $marker_text, $text );
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
		return $this->get_key_string();
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
			'key'				=> $this->get_key_string(),
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
		$result = $this->get_key_string();
		return $result;
	} // function

} // class