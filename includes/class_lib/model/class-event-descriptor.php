<?php
namespace Reg_Man_RC\Model;

/**
 * Provides a detailed description of an event or a series of recurring events.
 *
 * This interface is based on the VEVENT object definition from the ICalendar specification with several exclusions and
 * the addition of a provider ID and a provider event ID which specify how the event is identified within Wordpress.
 * For example, an event created using Events Calendar WD is a custom post and will have provider ID of "ecwd"
 * and provider event ID of the post's ID.
 *
 * Note that an Event_Descriptor defines an event or a series of recurring events.
 * This differs from an Event object which always describes a single event or a single instance of a recurring event.
 * A recurring event will have one Event_Descriptor and multiple associated Event instances.
 * For recurring events an instance of this class will contain a recurrence rule describing how the event's
 * recurring instances are determined.
 *
 * Note also that Event_Descriptor extends Map_Marker so that any event descriptor can be placed on a map, provided
 * it has its location defined and not left TBD.
 *
 * @since v0.1.0
 *
 */
interface Event_Descriptor extends Map_Marker {
	/**
	 * Get the globally unique id for the event descriptor, i.e. unique across domains.
	 *
	 * This is used when events are shared across systems, for example an iCalendar feed.
	 * Internally we use wp_generate_uuid4() but another event provider may use something else.
	 *
	 * Note that UID is required by ICalendar VEvent.
	 * But keep in mind that this is a unique ID for the descriptor which may represent a repeating event,
	 *  it's not a UID for an event instance.
	 *
	 * Note that for a recurring event multiple event instances will share the same uid but each will have a
	 * unique recurrence date and RECURRENCE-ID in the VEVENT data.
	 *
	 * @return	string		The globally unique id for the event descriptor.
	 * @since v0.1.0
	 */
	public function get_event_uid();

	/**
	 * Get the unique id of the provider for this event descriptor.
	 * @return	string		A unique id representing the provider (event or calendar plugin) that supplied this event.
	 * This will be an abbreviation of the event implementor, e.g. Event Calendar WD will return "ecwd".
	 * @since v0.1.0
	 */
	public function get_provider_id();

	/**
	 * Get the ID for this event descriptor that is unique within the event provider's domain.
	 * @return	string		The event descriptor ID which is unique for the event provider's implementation.
	 * This is usually the post ID for event implementors who use a custom post type to represent the event descriptor.
	 * Note that two separate event providers may, by coincidence, use the same ID within their respective domain
	 * so this ID alone is not sufficient to uniquely identify an event descriptor.
	 * This ID together with the provider ID, however, IS sufficient.
	 * @since v0.1.0
	 */
	public function get_event_descriptor_id();

	/**
	 * Get the event's start date and time as a \DateTimeInterface object, e.g. \DateTime instance.
	 * Note that the timezone MUST be set to local time, i.e. wp_timezone()
	 * @return	\DateTimeInterface	Event start date and time.  May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_start_date_time();

	/**
	 * Get the event's end date and time as a \DateTimeInterface object, e.g. \DateTime instance.
	 * Note that the timezone MUST be set to local time, i.e. wp_timezone()
	 * @return	\DateTimeInterface	Event end date and time.  May be NULL if no end time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_end_date_time();

	/**
	 * Get the event summary, e.g. "Repair Café at Toronto Reference Library".
	 * @return	string		The event summary if one is assigned, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_summary();

	/**
	 * Get the WordPress user ID of the author of this event, if known
	 * @return	int|string	The WordPress user ID of the author of this event if it is known, otherwise NULL or 0.
	 * @since v0.6.0
	 */
	public function get_event_author_id();

	/**
	 * Get the event's status represented as an instance of the Event_Status class.
	 * For a recurring event, get the status of the instance on the specified event date.
	 * If the event provider does not support cancelling of a recurring event instance then this argument is ignored
	 *  and the status of the event descriptor is returned.
	 * The default should be CONFIRMED.
	 *
	 * @param	\DateTime		$event_date	The event's date, or the event's start date/time in the local timezone
	 * @return	Event_Status	The event's status.
	 * @since v0.1.0
	 */
	public function get_event_status( $event_date = NULL );

	/**
	 * Get the event's class represented as an instance of the Event_Class class.
	 * The default should be PUBLIC.
	 *
	 * @return	Event_Class		The event's class.
	 * @since v0.1.0
	 */
	public function get_event_class();

	/**
	 * Get the venue for the event as a Venue Object.
	 *
	 * @return	Venue|NULL	The venue object for the event.
	 * This may be NULL if the Venue object cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_venue();

	/**
	 * Get the event location, e.g. "Toronto Reference Library, 789 Yonge Street, Toronto, ON, Canada"
	 * @return	string		Event location if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_location();

	/**
	 * Get the event's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position is not known.
	 *
	 * @return	Geographic_Position	The event's geographic position (co-ordinates) used to map the event if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_event_geo();

	/**
	 * Get the description of the event as a string, e.g. "Fixing all kinds of items!".
	 * @return	string		Event description if available, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_description();

	/**
	 * Get the event's categories as an array of strings, e.g. { "Repair Cafe", "Mini Event" }
	 * @return	string[]	An array of strings listing the event's categories.
	 * This may be NULL or an empty array if there are no categories or this is not supported by the event provider.
	 * @since v0.1.0
	 */
	public function get_event_categories();

	/**
	 * Get the fixer stations for this event as an array of Fixer_Station objects.
	 *
	 * @return	Fixer_Station[]	An array of Fixer_Station objects.
	 * This may be NULL or an empty array if there are no fixer stations or they cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_fixer_station_array();

	/**
	 * Get a flag indicating whether this is a non-repair event like a volunteer appreciation dinner where items are not fixed.
	 *
	 * @return	boolean		TRUE if this is a non-repair event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_non_repair();

	/**
	 * Get the url for the event descriptor page or event recurrence page when $recur_date is specified, if such a page exists.
	 * @param	string|NULL	$recur_date	An event recurrence date.
	 *  When NULL or empty the result of this method is the url for the page showing the event descriptor, if such a page exists.
	 *  If $recur_date is specified then the result is the url for the page showing the specified recurrence, if it exists.
	 *  If no separate page exists for the event recurrence then the result is the same as when no $recur_date is specified.
	 * @return	string		The url for the page that shows this event descriptor or event recurrence, if such a page exists,
	 *  otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	// FIXME - this should be a UTC event date-time, OR SHOULD IT???  What do we do with keys?  It should be the same!
	public function get_event_page_url( $recur_date = NULL );

	/**
	 * Get the url to edit the event descriptor, if the page exists.
	 * @return	string		The url for the page to edit this event descriptor if one exists, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_edit_url();

	/**
	 * Get a boolean flag indicating whether the event is recurring.
	 * @return	boolean	TRUE for a recurring event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_recurring();

	/**
	 * Get the recurrence rule for a repeating events.  Non-repeating events will return NULL.
	 * @return	Recurrence_Rule	For a repeating event this method returns an instance of Recurrence_Rule
	 * that specifies how the event repeats.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_recurrence_rule();

	/**
	 * Get the exclusion dates for a repeating events.  Non-repeating events will return NULL.
	 * @return	\DateTimeInterface[]	For a repeating event this method returns an array of dates and times
	 * to be excluded from the recurrence set of events.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_exclusion_dates();

	/**
	 * Get the dates to be added to the recurrence set for a repeating events.  Non-repeating events will return NULL.
	 * @return	\DateTimeInterface[]	For a repeating event this method returns an array of dates and times
	 * to be added to the recurrence set of events.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_inclusion_dates();

} // class