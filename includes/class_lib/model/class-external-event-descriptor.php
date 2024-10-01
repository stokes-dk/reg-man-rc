<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_Descriptor_View;

/**
 * Describes a single event or a collection of recurring events defined by an external event provider like ECWD.
 *
 * An instance of this class contains the information related to a single event or a collection of recurring events
 * including its summary, description, start and end date/time, location and so on.
 *
 * For recurring events it will also contain a recurrence rule describing all the recurring instances of the event.
 *
 * @since v0.1.0
 *
 */
class External_Event_Descriptor implements Event_Descriptor {
	
	private $uid;
	private $provider_id;
	private $event_descriptor_id;
	private $dtstart; // Date and time for the event's start as a string
	private $start_date_time; // DateTime object for the event's start date and time
	private $dtend; // Date and time for the event's start as a string
	private $end_date_time; // DateTime object for the event's end date and time
	private $summary;
	private $status_id; // The name of the status from the provider
	private $status; // The status object based on the provided name
	private $class_id;
	private $class;
	private $venue_name; // The name of the event venue as a string from the provider
	private $venue; // The venue object, if we can find the right one based on name or location
	private $location;
	private $geo_ical_string;
	private $geo;
	private $description;
	private $categories;
	private $fixer_station_names_array; // The names as strings from the provider
	private $fixer_station_array; // The array of fixer station objects
	private $url;
	private $rrule; // The text version of the recurrence rule provided upon instantiation
	private $recurrence_rule; // The object representation of the recurrence rule
	private $exdates; // exclusion set dates and times as a string
	private $exclusion_date_time_array; // an array of DateTime objects representing the exclusion dates for a recurring event
	private $rdates;
	private $inclusion_date_time_array; // an array of DateTime objects representing the dates to be added to a recurring event
	private $map_marker_colour;

	/**
	 * Instantiate this class based on the array of strings specified.
	 *
	 * This method will return an single instance of this class describing the external event specified by the data provided.
	 *
	 * @since v0.1.0
	 * @api
	 * @param	array	$data_array	{
	 * 		An associative array of strings describing the external event
	 *
	 * 		@type	string		'uid'					(required) The unique ID for the event.  This must be unique across domains so the event can be shared.
	 * 		@type	string		'provider-id'			(required) The ID for the event provider.  This must be unique among event providers.
	 * 		@type	string		'event-descriptor-id'	(required) The provider's ID for the event descriptor.  This must be unique within the provider's domain.
	 * 		@type	string		'dtstart'				The start date and time for the event with timezone, e.g. TZID=America/Toronto:20201115T120000
	 * 		@type	string		'dtend'					The end date and time for the event with timezone, e.g. TZID=America/Toronto:20201115T160000
	 * 		@type	string		'summary'				A brief summary of the event, e.g. a custom post's title
	 * 		@type	string		'status'				The status of the event.  Must be one of CONFIRMED, TENTATIVE, or CANCELLED
	 * 		@type	string		'class'					The class of the event.  Must be one of PUBLIC, PRIVATE, or CONFIDENTIAL
	 * 		@type	string		'venue-name'			The name of the venue for the event as a string, e.g. "Toronto Public Reference Library"
	 * 		@type	string		'location'				The location of the event as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * 		@type	string		'geo'					The event's geographic location (latitude and longitude) as a string, e.g. "43.692139;-79.329711"
	 * 		@type	string		'description'			The event's description, e.g. a custom post's content
	 * 		@type	string[]	'categories'			A json encoded array of categories for the event, e.g. ["Repair Cafe"]
	 * 		@type	string[]	'fixer-stations'		An json encoded array of fixer stations for the event, e.g. ["Appliances","Bikes"]
	 * 		@type	string		'url'					The url for the event's page if one exists
	 * 		@type	string		'rrule'					The RRULE for a recurring event (if applicable) specifying how the event recurrs written according to the ICalendar specification
	 * 		@type	string		'exdate'				Dates to be excluded from the recurrence set, comma-separated with timezone, e.g. TZID=America/Toronto:20201115T120000,TZID=America/Toronto:20201216T120000
	 * 		@type	string		'rdate'					Dates to be added to the recurrence set, comma-separated with timezone, e.g. TZID=America/Toronto:20201115T120000,TZID=America/Toronto:20201216T120000
	 * }
	 * @return	\Reg_Man_RC\Model\External_Event_Descriptor	The event descriptor object constructed from the data provided.  If the data
	 * 	are not valid, for example the uid, provider id or provider event id are missing, then the result is NULL.
	 */
	public static function instantiate_from_data_array( $data_array ) {
		// uid, provider id, and provider event id are required.  If they are missing we will return NULL
		if ( isset( $data_array['uid'] ) && isset( $data_array['provider-id'] ) && isset( $data_array['event-descriptor-id'] ) ) {
			$result = new self();
			$result->uid					= $data_array['uid'];
			$result->provider_id 			= $data_array['provider-id'];
			$result->event_descriptor_id	= $data_array['event-descriptor-id'];

			if ( isset( $data_array['dtstart'] ) ) {
				$result->dtstart = $data_array['dtstart'];
			} else {
				// This is not an error but should be logged as it's most likely a mistake in the data
				/* translators: %1$s is an event descriptor ID, %2$s is an event provider ID. */
				$msg = sprintf( __( 'Missing Start Date and Time in event descriptor for event ID %1$s from provider %2$s.', 'reg-man-rc' ),
						$result->event_descriptor_id, $result->provider_id);
				Error_Log::log_msg( $msg );
			} // endif

			if ( isset( $data_array['dtend'] ) ) {
				$result->dtend = $data_array['dtend'];
			} else {
				// This is not an error but should be logged as it's most likely a mistake in the data
				/* translators: %1$s is an event descriptor ID, %2$s is an event provider ID. */
				$msg = sprintf( __( 'Missing End Date and Time in event descriptor for event ID %1$s from provider %2$s.', 'reg-man-rc' ),
						$result->event_descriptor_id, $result->provider_id);
				Error_Log::log_msg( $msg );
			} // endif

			if ( isset( $data_array['summary'] ) ) {
				$result->summary = $data_array['summary'];
			} // endif

			if ( isset( $data_array['status'] ) ) {
				$result->status_id = $data_array['status'];
			} // endif

			if ( isset( $data_array['class'] ) ) {
				$result->class_id = $data_array['class'];
			} // endif

			if ( isset( $data_array['venue-name'] ) ) {
				$result->venue_name = $data_array['venue-name'];
			} // endif

			if ( isset( $data_array['location'] ) ) {
				$result->location = $data_array['location'];
			} // endif

			if ( isset( $data_array['geo'] ) ) {
				$result->geo_ical_string = $data_array['geo'];
			} // endif

			if ( isset( $data_array['description'] ) ) {
				$result->description = $data_array['description'];
			} // endif

			if ( isset( $data_array['categories'] ) ) {
				$result->categories = json_decode( $data_array['categories'] );
			} // endif
			if ( empty( $result->categories ) || ! is_array( $result->categories ) ) {
				// make sure the default is assigned if nothing else
				$result->categories = Event::get_default_event_category_names_array();
			} // endif

			if ( isset( $data_array['fixer-stations'] ) ) {
				$result->fixer_station_names_array = $data_array['fixer-stations'];
			} // endif

			if ( isset( $data_array['url'] ) ) {
				$result->url = $data_array['url'];
			} // endif

			if ( isset( $data_array['rrule'] ) ) {
				$result->rrule = $data_array['rrule'];
			} // endif

			if ( isset( $data_array['exdate'] ) ) {
				$result->exdates = $data_array['exdate'];
			} // endif

			if ( isset( $data_array['rdate'] ) ) {
				$result->rdates = $data_array['rdate'];
			} // endif

		} else {
			$result = NULL;
			if ( ! isset( $data_array['uid'] ) ) {
				$msg = sprintf( __( 'Missing UID in event external descriptor.', 'reg-man-rc' ) );
				Error_Log::log_msg( $msg );
			} // endif
			if ( ! isset( $data_array['provider-id'] ) ) {
				$msg = sprintf( __( 'Missing event provider ID in external event descriptor.', 'reg-man-rc' ) );
				Error_Log::log_msg( $msg );
			} // endif
			if ( ! isset( $data_array['event-descriptor-id'] ) ) {
				$msg = sprintf( __( 'Missing event descriptor ID in external event descriptor.', 'reg-man-rc' ) );
				Error_Log::log_msg( $msg );
			} // endif
		} // endif

		return $result;
	} // function

	/**
	 * Get the globally unique id for the event, i.e. unique across domains
	 * @return	string		The globally unique id for the event.
	 * This is used when events are shared across systems, for example imported and exported, or used in an iCalendar feed.
	 * The UID can be the post's GUID for event implementors who use a custom post type to represent the event.
	 * @since v0.1.0
	 */
	public function get_event_uid() {
		return $this->uid;
	} // function

	/**
	 * Get the unique id of the provider for this event.
	 * @return	string		A unique id representing the provider (event or calendar plugin) that supplied this event
	 * This will be an abbreviation of the event implementor, e.g. Event Calendar WD will return "ecwd"
	 * @since v0.1.0
	 */
	public function get_provider_id() {
		return $this->provider_id;
	} // function

	/**
	 * Get the ID for this event descriptor that is unique within the event provider's domain.
	 * @return	string		The event descriptor ID which is unique for the event provider's implementation
	 * This is usually the post ID for event implementors who use a custom post type to represent the event
	 * @since v0.1.0
	 */
	public function get_event_descriptor_id() {
		return $this->event_descriptor_id;
	} // function

	/**
	 * Get the event summary, e.g. "Repair Café at Toronto Reference Library".
	 * @return	string		The event summary if one is assigned, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_summary() {
		return $this->summary;
	} // function

	/**
	 * Get the WordPress user ID of the author of this event, if known
	 * @return	int|string	The WordPress user ID of the author of this event if it is known, otherwise NULL or 0.
	 * @since v0.6.0
	 */
	public function get_event_author_id() {
		// TODO: It may be possible to implement this in some cases
		return 0;
	} // function
	
	/**
	 * Get the event's status represented as an ID, one of CONFIRMED, TENTATIVE or CANCELLED.
	 *
	 * @return	string	The event's status ID.
	 * @since v0.1.0
	 */
	private function get_event_status_id() {
		return $this->status_id;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_status()
	 */
	public function get_event_status( $event_date = NULL) {
		// For now, external events do not support cancelling of individual recurring event instances,
		//  so the $event_date argument is ignored
		if ( ! isset( $this->status ) ) {
			$this->status = Event_Status::get_event_status_by_id( $this->get_event_status_id() );
			if ( $this->status === NULL ) {
				$this->status = Event_Status::get_event_status_by_id( Event_Status::CONFIRMED );
			} // endif
		} // endif
		return $this->status;
	} // function

	/**
	 * Get the event's class represented as an ID, one of PUBLIC, PRIVATE or CONFIDENTIAL.
	 *
	 * @return	string	The event's class ID.
	 * @since v0.1.0
	 */
	private function get_event_class_id() {
		return $this->class_id;
	} // function

	/**
	 * Get the event's class represented as an instance of the Event_Class.
	 * @return	string		The event's class.
	 * @since v0.1.0
	 */
	public function get_event_class() {
		if ( !isset( $this->class ) ) {
			$this->class = Event_Class::get_event_class_by_id( $this->get_event_class_id() );
			if ( $this->class === NULL ) {
				$this->class = Event_Class::get_event_class_by_id( Event_Class::PUBLIC );
			} // endif
		} // endif
		return $this->class;
	} // function

	/**
	 * Get the event's start date and time as a \DateTimeInterface object, e.g. \DateTime instance.
	 * Note that the timezone MUST be set to local time, i.e. wp_timezone()
	 * @return	\DateTimeInterface	Event start date and time.  May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_start_date_time() {
		if ( !isset( $this->start_date_time ) ) {
			if ( !empty( $this->dtstart ) ) {
				try {
					$this->start_date_time = self::parse_date_string( $this->dtstart );
					$this->start_date_time->setTimezone( wp_timezone() ); // Make sure it's in local timezone
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid dtstart value supplied for an event description */
					$msg = sprintf( __( 'Invalid dtstart: %1$s.', 'reg-man-rc' ), $this->dtstart );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->start_date_time;
	} // function

	/**
	 * Get the event's end date and time as a \DateTimeInterface object, e.g. \DateTime instance.
	 * Note that the timezone MUST be set to local time, i.e. wp_timezone()
	 * @return	\DateTimeInterface	Event end date and time.  May be NULL if no end time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_end_date_time() {
		if ( ! isset( $this->end_date_time ) ) {
			if ( ! empty( $this->dtend ) ) {
				try {
					$this->end_date_time = self::parse_date_string( $this->dtend );
					$this->end_date_time->setTimezone( wp_timezone() );
				} catch ( \Exception $exc ) {
					// We will not have a date and time here if the above fails so just log and continue
					/* translators: %1$s is an invalid dtend value supplied for an event description */
					$msg = sprintf( __( 'Invalid dtend: %1$s.', 'reg-man-rc' ), $this->dtend );
					Error_Log::log_exception( $msg, $exc );
				} // endtry
			} // endif
		} // endif
		return $this->end_date_time;
	} // function

	/**
	 * Returns a \DateTime object contructed from a string in the form: TZID=America/Toronto:20201115T120000
	 * @param	string		$dt_str
	 * @return	\DateTime	DateTime object with the correct date, time and timezone
	 * @throws	\Exception	If the date string cannot be parsed
	 */
	private static function parse_date_string( $dt_str ) {

		// Note that it would be nicer to use DateTime::createFromFormat() using '\T\Z\I\D\=e:Ymd\THis'
		// But when PHP version is below 8.2, it fails saying that the Timezone cannot be found
		// Even though the same timezone can be found when constructing it as below!
		
		$parts = explode( ':', $dt_str );
		if ( count( $parts ) !== 2 ) {
			$result = NULL;
		} else {
			$date_time_string = $parts[1];
			$tz_parts = explode( '=', $parts[0] );
			if ( count( $tz_parts ) !== 2 ) {
				$result = NULL;
			} else {
				$tz = new \DateTimeZone( $tz_parts[1] );
				$result = new \DateTime( $date_time_string, $tz );
			} // endif
		} // endif
		return $result;

	} // function

	/**
	 * Get the venue for the event as a Venue Object.
	 *
	 * @return	Venue	The venue object for the event.
	 * This may be NULL if the Venue object cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_venue() {
		if ( ! isset( $this->venue ) ) {
			// Try to find the venue by name and if not found then look by location
			$venue_name = $this->venue_name;
			$location = $this->location;
			$this->venue = Venue::get_venue_by_name( $venue_name );
			if ( ! isset( $this->venue ) ) {
				$this->venue = Venue::get_venue_by_location( $location );
			} // endif
		} // endif
		return $this->venue;
	} // function

	/**
	 * Get the event location, e.g. "Toronto Reference Library, 789 Yonge Street, Toronto, ON, Canada"
	 * @return	string		Event location if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_location() {
		return $this->location;
	} // function

	/**
	 * Get the event's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position was not specified in the event data.
	 *
	 * @return	Geographic_Position	The event's position (co-ordinates) used to map the event if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_event_geo() {
		if ( ! isset( $this->geo ) ) {
			$this->geo = Geographic_Position::create_from_iCalendar_string( $this->geo_ical_string );
		} // endif
		return $this->geo;
	} // function

	/**
	 * Get the description of the event as a string, e.g. "Fixing all kinds of items!"
	 * @return	string		Event description if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_description() {
		return $this->description;
	} // function

	/**
	 * Get the event's categories as an array of strings, e.g. { "Repair Cafe", "Mini Event" }
	 * @return	string[]	An array of strings listing the event's categories.
	 * This may be NULL or an empty array if there are no categories or this is not supported by the event provider
	 * @since v0.1.0
	 */
	public function get_event_categories() {
		return $this->categories;
	} // function

	/**
	 * Get the fixer stations for this event as an array of Fixer_Station objects.
	 *
	 * @return	Fixer_Station[]	An array of Fixer_Station objects.
	 * This may be NULL or an empty array if the fixer stations cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_fixer_station_array() {
		if ( ! isset( $this->fixer_station_array) ) {
			if ( is_array( $this->fixer_station_names_array ) ) {
				$this->fixer_station_array = array();
				foreach( $this->fixer_station_names_array as $fixer_station_name ) {
					$station = Fixer_Station::get_fixer_station_by_name( $fixer_station_name );
					if ( isset( $station) ) {
						$this->fixer_station_array[] = $station;
					} // endif
				} // endfor
			} // endif
		} // endif
		return $this->fixer_station_array;
	} // function

	/**
	 * Get a flag indicating whether this is a non-repair event like a volunteer appreciation dinner where items are not fixed.
	 *
	 * @return	boolean		TRUE if this is a non-repair event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_non_repair() {
		// TODO: put this in the original data
		$result = FALSE;
		return $result;
	} // function

	/**
	 * Get the url for the event descriptor page if one exists
	 * @return	string	The url for the page that shows this event descriptor if it exists, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	private function get_event_descriptor_page_url() {
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
		if ( empty( $recur_date ) || ! $this->get_event_is_recurring() ) {
			// No $recur_date was specified or this is not a recurring event
			$result = $base_url;

		} else {
			/**
			 * Ask the external event providers to filter the event URL so that it correctly includes the
			 * specified recurrence date.
			 *
			 * @since v0.1.0
			 *
			 * @api
			 *
			 * @param	string	$base_url		The base URL for the event provided in the original event descriptor.
			 * @param	string	$provider_id	The ID of the event provider for this event.
			 *   Providers must verify that the event belongs to them before modifying the URL.
			 * @param	string	$recur_date		The recurrence date as an event date in ISO 8601 format, e.g. '20230206'
			 * @param	string	$desc_id		The event descriptor ID provided in the original event descriptor.
			 * @param	string	$uid			The event UID provided in the original event descriptor.
			 */
			$provider_id = $this->get_provider_id();
			$desc_id = $this->get_event_descriptor_id();
			$uid = $this->get_event_uid();
			$result = apply_filters( 'reg_man_rc_filter_recurring_event_url', $base_url, $provider_id, $recur_date, $desc_id, $uid );

		} // endif

		return $result;

	} // function

	/**
	 * Get the url to edit the event descriptor, if the page exists.
	 * @return	string		The url for the page to edit this event descriptor if one exists, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_edit_url() {
		// TODO: this could be passed in the initial data
		return NULL;
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
	 * Get the recurrence rule for a repeating events.  Non-repeating events will return NULL.
	 * @return	Recurrence_Rule	For a repeating event this method returns an instance of Recurrence_Rule that specifies how the event repeats.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_recurrence_rule() {
		if ( !isset( $this->recurrence_rule ) ) {
			$rrule = $this->get_rrule();
			if ( !empty( $rrule ) ) {
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
	 * Get the RRULE text representation
	 * @return	string	The text representation for the recurrence rule. For non-repeating events this method will return NULL
	 * @since v0.1.0
	 */
	private function get_rrule() {
		return $this->rrule;
	} // endif

	/**
	 * Get the dates and times to be excluded from the recurrence set as an array of \DateTimeInterface objects, e.g. \DateTime instances
	 * @return	\DateTimeInterface[]	Dates and times to be excluded.  May be NULL if this is a non-recurring event.
	 * @since v0.1.0
	 */
	 public function get_event_exclusion_dates() {
	 	if ( ! isset( $this->exclusion_date_time_array ) ) {
			if ( ! empty( $this->exdates ) ) {
				$this->exclusion_date_time_array = array();
				try {
					$dates = explode( ',', $this->exdates );
					foreach ( $dates as $date ) {
						$date_time = self::parse_date_string( $date );
						if ( ! empty( $date_time ) ) {
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
					foreach ( $dates as $date ) {
						$date_time = self::parse_date_string( $date );
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
		return $this->get_event_location();
	} // function

	/**
	 * Get the marker ID as a string.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type ) {
		return $this->get_event_uid();
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
	 * Get the zoom level for the map marker.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	int	The zoom level for the map.
	 * @since v0.1.0
	 */
	public function get_map_marker_zoom_level( $map_type ) {
		return NULL;
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
			$categories = $this->get_event_categories();
			if ( is_array( $categories) && isset( $categories[ 0 ] ) ) {
				$top_category = Event_Category::get_event_category_by_name( $categories[ 0 ] );
				$this->map_marker_colour = isset( $top_category ) ? $top_category->get_colour() : NULL;
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

} // class