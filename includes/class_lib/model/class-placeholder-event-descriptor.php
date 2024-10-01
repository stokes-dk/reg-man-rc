<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_Descriptor_View;

/**
 * A placeholder used when an event descriptor cannot be found.
 * This may happen if some object like an Item is registered to an event and then the event is deleted.
 *
 * @since v0.6.0
 *
 */
class Placeholder_Event_Descriptor implements Event_Descriptor {
	
	private $provider_id;
	private $event_descriptor_id;
	private $summary;

	private function _construct() {
	} // function

	/**
	 * Create an instance of this class for the specified proivder and descriptor IDs
	 *
	 * @since v0.6.0
	 * @return	Placeholder_Event_Descriptor	The event descriptor object constructed from the data provided.
	 */
	public static function create( $provider_id, $descriptor_id ) {
		$result = new self();
		$result->provider_id = $provider_id;
		$result->event_descriptor_id = $descriptor_id;
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
		$prov = $this->get_provider_id();
		$desc = $this->get_event_descriptor_id();
		return "$prov $desc"; // This is never shared so it doesn't need to be "globally" unique
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
		$prov = $this->get_provider_id();
		$desc = $this->get_event_descriptor_id();
		if ( $prov == Internal_Event_Descriptor::EVENT_PROVIDER_ID ) {
			/* Translators: %1$s is an event descriptor ID */
			$format = __( '[ Event not found: %1$s ]', 'reg-man-rc' );
			$result = sprintf( $format, $desc );
		} else {
			/* Translators: %1$s is an event descriptor ID, %2$s is an event provider ID */
			$format = __( '[ Event not found: %1$s %2$s ]', 'reg-man-rc' );
			$result = sprintf( $format, $desc, $prov );
		} // endif
		return $result;
	} // function

	/**
	 * Get the WordPress user ID of the author of this event, if known
	 * @return	int|string	The WordPress user ID of the author of this event if it is known, otherwise NULL or 0.
	 * @since v0.6.0
	 */
	public function get_event_author_id() {
		return 0;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_status()
	 */
	public function get_event_status( $event_date = NULL ) {
		return Event_Status::get_default_event_status();
	} // function

	/**
	 * Get the event's class represented as an instance of the Event_Class.
	 * @return	string		The event's class.
	 * @since v0.1.0
	 */
	public function get_event_class() {
		return Event_Class::get_default_event_class();
	} // function

	/**
	 * Get the event's start date and time as a \DateTimeInterface object, e.g. \DateTime instance
	 * @return	\DateTimeInterface	Event start date and time.  May be NULL if no start time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_start_date_time() {
		return NULL;
	} // function

	/**
	 * Get the event's end date and time as a \DateTimeInterface object, e.g. \DateTime instance
	 * @return	\DateTimeInterface	Event end date and time.  May be NULL if no end time is assigned.
	 * @since v0.1.0
	 */
	public function get_event_end_date_time() {
		return NULL;
	} // function

	/**
	 * Get the venue for the event as a Venue Object.
	 *
	 * @return	Venue	The venue object for the event.
	 * This may be NULL if the Venue object cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_venue() {
		return NULL;
	} // function

	/**
	 * Get the event location, e.g. "Toronto Reference Library, 789 Yonge Street, Toronto, ON, Canada"
	 * @return	string		Event location if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_location() {
		return NULL;
	} // function

	/**
	 * Get the event's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position was not specified in the event data.
	 *
	 * @return	Geographic_Position	The event's position (co-ordinates) used to map the event if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_event_geo() {
		return NULL;
	} // function

	/**
	 * Get the description of the event as a string, e.g. "Fixing all kinds of items!"
	 * @return	string		Event description if available, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	public function get_event_description() {
		return NULL;
	} // function

	/**
	 * Get the event's categories as an array of strings, e.g. { "Repair Cafe", "Mini Event" }
	 * @return	string[]	An array of strings listing the event's categories.
	 * This may be NULL or an empty array if there are no categories or this is not supported by the event provider
	 * @since v0.1.0
	 */
	public function get_event_categories() {
		return NULL;
	} // function

	/**
	 * Get the fixer stations for this event as an array of Fixer_Station objects.
	 *
	 * @return	Fixer_Station[]	An array of Fixer_Station objects.
	 * This may be NULL or an empty array if the fixer stations cannot be determined by the event provider
	 * @since v0.1.0
	 */
	public function get_event_fixer_station_array() {
		return NULL;
	} // function

	/**
	 * Get a flag indicating whether this is a non-repair event like a volunteer appreciation dinner where items are not fixed.
	 *
	 * @return	boolean		TRUE if this is a non-repair event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_non_repair() {
		return FALSE;
	} // function

	/**
	 * Get the url for the event descriptor page if one exists
	 * @return	string	The url for the page that shows this event descriptor if it exists, otherwise NULL or empty string
	 * @since v0.1.0
	 */
	private function get_event_descriptor_page_url() {
		return NULL;
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
		return NULL;
	} // function

	/**
	 * Get the url to edit the event descriptor, if the page exists.
	 * @return	string		The url for the page to edit this event descriptor if one exists, otherwise NULL or empty string.
	 * @since v0.1.0
	 */
	public function get_event_edit_url() {
		return NULL;
	} // function

	/**
	 * Get a boolean flag indicating whether the event is recurring.
	 * @return	boolean	TRUE for a recurring event, FALSE otherwise.
	 * @since v0.1.0
	 */
	public function get_event_is_recurring() {
		return FALSE;
	} // endif

	/**
	 * Get the recurrence rule for a repeating events.  Non-repeating events will return NULL.
	 * @return	Recurrence_Rule	For a repeating event this method returns an instance of Recurrence_Rule that specifies how the event repeats.
	 * For non-repeating events this method will return NULL.
	 * @since v0.1.0
	 */
	public function get_event_recurrence_rule() {
		return NULL;
	} // function

	/**
	 * Get the dates and times to be excluded from the recurrence set as an array of \DateTimeInterface objects, e.g. \DateTime instances
	 * @return	\DateTimeInterface[]	Dates and times to be excluded.  May be NULL if this is a non-recurring event.
	 * @since v0.1.0
	 */
	 public function get_event_exclusion_dates() {
		return NULL;
	} // function

	/**
	 * Get the dates and times to be added to the recurrence set as an array of \DateTimeInterface objects, e.g. \DateTime instances
	 * @return	\DateTimeInterface[]	Dates and times to be added.  May be NULL if this is a non-recurring event.
	 * @since v0.1.0
	 */
	 public function get_event_inclusion_dates() {
		return NULL;
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
		return Map_View::DEFAULT_MARKER_COLOUR;
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