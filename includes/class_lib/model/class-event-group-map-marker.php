<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Event_Descriptor_View;
use Reg_Man_RC\View\Volunteer_Registration_View;
use Reg_Man_RC\View\Object_View\Event_Descriptor_Group_View;

/**
 * An instance of this class represents a group of events at a single location.
 * It implements Map_Marker and can be placed on a map.
 *
 * Rather than stacking several separate event markers on top of each other at the same location on the map,
 * this class allows one marker to be used to represent several events.
 *
 * @since v0.1.0
 *
 */
class Event_Group_Map_Marker implements Map_Marker {

	private $events_array = array(); // The set of events represented by this group
	private $event_descriptors_array = array(); // The set of separate event descriptors included in the group keyed by event key

	private $sole_event; // Will contain the Event object in the case that this group has only 1 event, NULL otherwise
	private $sole_event_descriptor; // Will contain the Event_Descriptor object in the case that there is only 1, NULL otherwise
	private $is_event_group_complete; // A flag set to TRUE if all events in this group are complete (in the past)
	private $is_event_group_cancelled; // A flag set to TRUE if all events in this group are cancelled
	private $is_event_group_tentative; // A flag set to TRUE if all events in this group are tentative
	private $is_event_group_private; // A flag set to TRUE if all events in this group are private
	private $is_event_group_confidential; // A flag set to TRUE if all events in this group are confidential
	private $is_volunteer_registered; // A flag set to TRUE if the current volunteer is registered to any event in the group
	private $map_marker_label_class_names; // A list of HTML class names applied to the map marker label for this group
	
	private $id; // An ID for the group
	private $map_marker_title; // A title for the map marker, this may be used more than once
	private $place_name = FALSE; // Use FALSE as a flag to indicate that we should try to get it later
	private $location = FALSE;
	private $geo_pos;

	private function __construct() {
	} // function

	/**
	 * Create an associative array of instances of this class using the specified array of events.
	 * The resulting array is keyed by:
	 *  venue ID for events with a venue,
	 *  geographic location string for events with a location but no venue
	 *  or the a unique "TBD" ID for each event with no location or venue (1 TBD event per group)
	 * @param Event[] $events_array
	 * @return Event_Group_Map_Marker[]
	 */
	public static function create_array_for_events_array( $events_array ) {
		$result = array();

		// I need to start with the venues and create those groups first
		// If anything is left over I can put them in existing groups based on geographical position
		$no_venue_events = array(); // an array of groups with no venue

		foreach( $events_array as $event ) {

			$venue = $event->get_venue();
			if ( isset( $venue ) ) {

				// Find or create the Group for this venue
				$group_id = $venue->get_id();
				if ( ! isset( $result[ $group_id ] ) ) {
					$result[ $group_id ] = self::create_for_venue( $venue );
				} // endif

				$current_group = $result[ $group_id ];
				$current_group->add_event( $event );

			} else {
				
				$no_venue_events[] = $event;

			} // endif

		} // endfor

		// Search by geo position for any events left over
		//  and create new groups for anything that doesn't fit
		foreach( $no_venue_events as $event ) {

			$geo_pos = $event->get_geo();
			if ( isset( $geo_pos ) ) {

				// Find or create the Group for this geographic position
				$group_id = $geo_pos->get_as_string();
				if ( ! isset( $result[ $group_id ] ) ) {

					$precision = Settings::get_map_location_comparison_precision(); // Used to compare locations
					// Check if there is an existing group with a geo position that is equal to this one
					$create_flag = TRUE; // Assume we need to create a new group
					foreach( $result as $existing_group_id => $existing_group ) {

						$existing_group_geo = $existing_group->geo_pos;

						// If the current geo position is equal to an existing group then put this event in that group
						if ( $geo_pos->get_is_equal_to( $existing_group_geo, $precision ) ) {
							$group_id = $existing_group_id;
							$create_flag = FALSE;
							break;
						} // endif

					} // endfor

					if ( $create_flag ) {
						$location = $event->get_location(); // We'll use this for the whole group
						$result[ $group_id ] = self::create_for_geo_position( $geo_pos, $location );
					} // endif

				} // endif

			} else {

				// The event has no venue or geographic position
				// Put it in a group by itself so we can list event individual summaries with no location
				$group_id = 'TBD-' . $event->get_key_string();
				if ( ! isset( $result[ $group_id ] ) ) {
//					$place_name = NULL; // Without a venue there is no place name
					$location = $event->get_location(); // We'll use this for the whole group
					$result[ $group_id ] = self::create_for_TBD_location( $group_id );
				} // endif

			} // endif

			$current_group = $result[ $group_id ];
			$current_group->add_event( $event );

		} // endfor

//		Error_Log::var_dump( count( $events_array ), count( $result ) );
//		Error_Log::var_dump( $result );
		return $result;

	} // function

	/**
	 * Create a new instance of this class using the specified venue
	 * @param Venue $venue
	 * @return Event_Group_Map_Marker
	 */
	private static function create_for_venue( $venue ) {
		$result = new self();
//		$result->venue = $venue;
		$result->id = $venue->get_id();
		$result->place_name = $venue->get_name();
		$result->location = $venue->get_location();
		$result->geo_pos = $venue->get_geo();
		return $result;
	} // function

	/**
	 * Create a new instance of this class using a location (when an event specifies a location but has no venue)
	 * @param Geographic_Position	$geo_pos	The geographic position object representing the markers latitude and longitude
	 * @param string				$place_name	The name of the location if one is available, e.g. "Toronto Reference Library", or NULL if not known
	 * @param string				$location	The location, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @return Event_Group_Map_Marker
	 */
	private static function create_for_geo_position( $geo_pos, $location  ) {
		$result = new self();
		$result->id = $geo_pos->get_as_string();
		$result->location = $location;
		$result->geo_pos = $geo_pos;
		return $result;
	} // function

	/**
	 * Create a new instance of this class using the specified venue
	 * @param string	$tbd_id		The ID to be used for this TBD group.
	 * We want 1 TBD event per group so the ID is something like "TBD-event_key"
	 * @return Event_Group_Map_Marker
	 */
	private static function create_for_TBD_location( $tbd_id) {
		$result = new self();
		$result->id = $tbd_id;
		$result->place_name = NULL;
		$result->location = __( 'Location to be determined', 'reg-man-rc' );
		return $result;
	} // function

	/**
	 * Returns the ID of this group.
	 * When the group is created using a Venue, the ID is the venue ID.
	 * When the group is created using a geographic position, the ID is the string version of that position.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	} // function


	/**
	 * Returns the place name of this group.
	 * This is set at create time if it's known, otherwise we have to find a name in the event descriptors
	 * @return string|NULL
	 */
	public function get_place_name() {
		if ( $this->place_name === FALSE ) {
			$this->place_name = NULL; // Don't try to get it twice
			$event_desc_array = $this->get_event_descriptors_array();
			foreach( $event_desc_array as $event_desc ) {
				$venue = $event_desc->get_event_venue();
				$name = isset( $venue ) ? $venue->get_name() : NULL;
				if ( isset( $name ) ) {
					$this->place_name = $name;
					break;
				} // endif
			} // endfor
		} // endif
		return $this->place_name;
	} // function

	/**
	 * Returns the location of this group.
	 * This is set at create time if it's known, otherwise we have to find a location in the event descriptors
	 * @return string
	 */
	public function get_location() {
		if ( $this->location === FALSE ) {
			$this->location = NULL; // Don't try to get it twice
			$event_desc_array = $this->get_event_descriptors_array();
			foreach( $event_desc_array as $event_desc ) {
				$location = $event_desc->get_event_location();
				if ( isset( $location ) ) {
					$this->location = $location;
					break;
				} // endif
			} // endfor
		} // endif
		return $this->location;
	} // function

	/**
	 * Returns the geographical position of this group.
	 * @return Geographic_Position
	 */
	public function get_geographical_position() {
		return $this->geo_pos;
	} // function

	/**
	 * Add an event to this group
	 * @param Event $event
	 */
	private function add_event( $event ) {
		$this->events_array[] = $event;
		$event_descriptor = $event->get_event_descriptor();
		$event_descriptor_id = $event_descriptor->get_event_descriptor_id();
		$provider_id = $event_descriptor->get_provider_id();
		// The event key contains the event date so event keys are different even when they have the same descriptor
		// To differentiate event descriptors we need to invent an event descriptor key string
		$descriptor_key_string = "$event_descriptor_id $provider_id";
		$this->event_descriptors_array[ $descriptor_key_string ] = $event_descriptor;
	} // function

	/**
	 * Get the array of events for this map marker
	 * @return	Event[]
	 * @since v0.1.0
	 */
	public function get_events_array() {
		return $this->events_array;
	} // function

	/**
	 * Get the sole Event object if this group contains exactly 1, otherwise FALSE
	 * @return Event|FALSE
	 */
	private function get_sole_event() {
		if ( ! isset( $this->sole_event ) ) {
			if ( count( $this->events_array ) === 1 ) {
				$events_array = array_values( $this->events_array );
				$this->sole_event = $events_array[ 0 ];
			} else {
				$this->sole_event = FALSE;
			} // endif
		} // endif
		return $this->sole_event;
	} // function

	/**
	 * Get the array of event descriptors for this map marker
	 * @return	Event_Descriptor[]
	 * @since v0.1.0
	 */
	public function get_event_descriptors_array() {
		return $this->event_descriptors_array;
	} // function

	/**
	 * Get the sole Event_Descriptor object if this group contains exactly 1, otherwise FALSE
	 * @return Event_Descriptor|FALSE
	 */
	private function get_sole_event_descriptor() {
		if ( ! isset( $this->sole_event_descriptor ) ) {
			if ( count( $this->event_descriptors_array ) === 1 ) {
				$desc_array = array_values( $this->event_descriptors_array );
				$this->sole_event_descriptor = $desc_array[ 0 ];
			} else {
				$this->sole_event_descriptor = FALSE;
			} // endif
		} // endif
		return $this->sole_event_descriptor;
	} // function
	
	/**
	 * Get the array of event objects that belong to the specified event descriptor
	 * @param string $event_descriptor_id
	 * @param string $event_provider_id
	 * @return Event[]
	 */
	public function get_events_by_descriptor( $event_descriptor ) {
		$result = array();
		$events_array = $this->get_events_array();
		$desc_id = $event_descriptor->get_event_descriptor_id();
		$prov_id = $event_descriptor->get_provider_id();
		foreach( $events_array as $event ) {
			if ( ( $event->get_event_descriptor_id() == $desc_id ) &&
				 ( $event->get_provider_id() == $prov_id ) ) {
				$result[] = $event;
			} // endif
		} // function
		return $result;
	} // function

	/**
	 * Get a flag indicating whether all events in this group are complete (in the past)
	 * @return boolean	TRUE if all events are complete, FALSE otherwise
	 */
	private function get_is_event_group_complete() {
		if ( ! isset( $this->is_event_group_complete ) ) {
			$events_array = $this->get_events_array();
			$result = TRUE; // Assume yes unless we discover otherwise
			foreach( $events_array as $event ) {
				if ( ! $event->get_is_event_complete() ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			$this->is_event_group_complete = $result;
		} // endif
		return $this->is_event_group_complete;
	} // endif
	
	/**
	 * Get a flag indicating whether all events in this group are cancelled
	 * @return boolean	TRUE if all events are cancelled, FALSE otherwise
	 */
	private function get_is_event_group_cancelled() {
		if ( ! isset( $this->is_event_group_cancelled ) ) {
			$event_desc_array = $this->get_event_descriptors_array();
			$result = TRUE; // Assume yes unless we discover otherwise
			foreach( $event_desc_array as $event_desc ) {
				$status = $event_desc->get_event_status();
				$status_id = isset( $status ) ? $status->get_id() : Event_Status::CONFIRMED; // Defensive
				if ( $status_id !== Event_Status::CANCELLED ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			$this->is_event_group_cancelled = $result;
		} // endif
		return $this->is_event_group_cancelled;
	} // endif
	
	/**
	 * Get a flag indicating whether all events in this group are cancelled
	 * @return boolean	TRUE if all events are cancelled, FALSE otherwise
	 */
	private function get_is_event_group_tentative() {
		if ( ! isset( $this->is_event_group_tentative ) ) {
			$event_desc_array = $this->get_event_descriptors_array();
			$result = TRUE; // Assume yes unless we discover otherwise
			foreach( $event_desc_array as $event_desc ) {
				$status = $event_desc->get_event_status();
				$status_id = isset( $status ) ? $status->get_id() : Event_Status::CONFIRMED; // Defensive
				if ( $status_id !== Event_Status::TENTATIVE ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			$this->is_event_group_tentative = $result;
		} // endif
		return $this->is_event_group_tentative;
	} // endif

	/**
	 * Get a flag indicating whether all events in this group are private
	 * @return boolean	TRUE if all events are cancelled, FALSE otherwise
	 */
	private function get_is_event_group_private() {
		if ( ! isset( $this->is_event_group_private ) ) {
			$event_desc_array = $this->get_event_descriptors_array();
			$result = TRUE; // Assume yes unless we discover otherwise
			foreach( $event_desc_array as $event_desc ) {
				$class = $event_desc->get_event_class();
				$class_id = isset( $class ) ? $class->get_id() : Event_CLASS::PUBLIC; // Defensive
				if ( $class_id !== Event_Class::PRIVATE ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			$this->is_event_group_private = $result;
		} // endif
		return $this->is_event_group_private;
	} // endif
	
	/**
	 * Get a flag indicating whether all events in this group are private
	 * @return boolean	TRUE if all events are cancelled, FALSE otherwise
	 */
	private function get_is_event_group_confidential() {
		if ( ! isset( $this->is_event_group_confidential ) ) {
			$event_desc_array = $this->get_event_descriptors_array();
			$result = TRUE; // Assume yes unless we discover otherwise
			foreach( $event_desc_array as $event_desc ) {
				$class = $event_desc->get_event_class();
				$class_id = isset( $class ) ? $class->get_id() : Event_CLASS::PUBLIC; // Defensive
				if ( $class_id !== Event_Class::CONFIDENTIAL ) {
					$result = FALSE;
					break;
				} // endif
			} // endfor
			$this->is_event_group_confidential = $result;
		} // endif
		return $this->is_event_group_confidential;
	} // endif
	
	
	/**
	 * Get a flag indicating whether the current volunteer is registered to any event in the group
	 * @return boolean	TRUE if the volunteer is registered to any event in the group, FALSE otherwise
	 */
	private function get_is_volunteer_registered() {
		if ( ! isset( $this->is_volunteer_registered ) ) {
			$events_array = $this->get_events_array();
			$result = FALSE; // Assume no unless we discover otherwise
			foreach( $events_array as $event ) {
				$vol_reg = $event->get_volunteer_registration_for_current_request();
				if ( ! empty( $vol_reg ) ) {
					$result = TRUE;
					break;
				} // endif
			} // endfor
			$this->is_volunteer_registered = $result;
		} // endif
		return $this->is_volunteer_registered;
	} // endif
	
	
	/**
	 * Get the html class names to be assigned to the entry or NULL if no classes are needed.
	 * Multiple class names should be contained in a single string separated by spaces.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string|NULL
	 * @since v0.1.0
	 */
	private function get_map_marker_label_class_names( $map_type ) {
		
		if ( ! isset( $this->map_marker_label_class_names ) ) {

			$class_array = array();

			// Completed / Upcoming
			$class_array[] = $this->get_is_event_group_complete() ? 'completed' : 'upcoming';

			// Status
			if ( $this->get_is_event_group_cancelled() ) {
				$class_array[] = 'cancelled-status';
			} elseif( $this->get_is_event_group_tentative() ) {
				$class_array[] = 'tentative-status';
			} // endif

			// Volunteer registration status
			if ( $map_type == Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG ) {
				$class_array[] = $this->get_is_volunteer_registered() ? 'vol-reg-registered' : 'vol-reg-not-registered';
			} // endif

			// implode the array
			$this->map_marker_label_class_names = implode( ' ', $class_array );

		} // endif
		
		return $this->map_marker_label_class_names;
		
	} // function

	
	/**
	 * Get the marker title as a string, e.g. "Toronto Reference Library".
	 * This string is shown as rollover text for the marker, similar to an element's title attribute.
	 * Its main purpose is for accessibility, e.g. screen readers.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_title( $map_type ) {

		if ( ! isset( $this->map_marker_title ) ) {

			$sole_event = $this->get_sole_event();
			if ( ! empty( $sole_event ) ) {
				
				// A single event so just use its label
				$this->map_marker_title = $sole_event->get_map_marker_title( $map_type );

			} else {

				$sole_desc = $this->get_sole_event_descriptor();
				$event_count = count( $this->get_events_array() );
				if ( ! empty( $sole_desc ) ) {
					$summary = $sole_desc->get_event_summary();
				} else {
					$summary = __( 'Multiple events', 'reg-man-rc' );
				} // endif

				/* Translators: %1$s a count of events, %2$s is an event title */
				$format = _n( '%1$s event : %2$s', '%1$s events : %2$s', $event_count, 'reg-man-rc' );
				$this->map_marker_title = sprintf( $format, number_format_i18n( $event_count ), $summary );
					
			}  // endif

		} // endif

		return $this->map_marker_title;
	} // function

	private function get_event_group_marker_text() {
		// Mark group as appropriate based on its status and class

		if ( $this->get_is_event_group_cancelled() ) {
			$status = Event_Status::get_event_status_by_id( Event_Status::CANCELLED );
		} elseif( $this->get_is_event_group_tentative() ) {
			$status = Event_Status::get_event_status_by_id( Event_Status::TENTATIVE );
		} else {
			$status = Event_Status::get_event_status_by_id( Event_Status::CONFIRMED );
		} // endif

		if ( $this->get_is_event_group_private() ) {
			$class = Event_Class::get_event_class_by_id( Event_Class::PRIVATE );
		} elseif( $this->get_is_event_group_confidential() ) {
			$class = Event_Class::get_event_class_by_id( Event_Class::CONFIDENTIAL );
		} else {
			$class = Event_Class::get_event_class_by_id( Event_Class::PUBLIC );
		} // endif

		$status_id = $status->get_id();
		$class_id = $class->get_id();
		
		$text_values_array = Event_Descriptor_View::get_event_marker_text_values_array();
		$result = isset( $text_values_array[ $status_id ][ $class_id ] ) ? $text_values_array[ $status_id ][ $class_id ] : NULL;
		
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

		$result = NULL;

		// For the admin stats maps we don't want any labels because there could be hundreds of events
		if ( $map_type !== Map_View::MAP_TYPE_ADMIN_STATS ) {

			$sole_event = $this->get_sole_event();
			if ( ! empty( $sole_event ) ) {
			
				// A single event so just use its label
				$result = $sole_event->get_map_marker_label( $map_type );

			} else {
			
				$event_count = count( $this->get_events_array() );
				/* Translators: %1$s is an event title, %2$s is a count of events */
				$format = _n( '%1$s event', '%1$s events', $event_count, 'reg-man-rc' );
				$text = sprintf( $format, number_format_i18n( $event_count ) ); // start with count of events
				$classes = $this->get_map_marker_label_class_names( $map_type );
				
				$marker_text = $this->get_event_group_marker_text();
				
				if ( ! empty( $marker_text ) ) {
					
					/* Translators: %1$s is a status marker text like "TENTATIVE", %2$s is an event summary */
					$label_with_marker_format = _x( '%1$s %2$s', 'A map marker label for an event group with its status, e.g. TENTATIVE Reference Library Repair Cafe', 'reg-man-rc' );
					$text = sprintf( $label_with_marker_format, $marker_text, $text );
					
				} // endif

				$result = Map_Marker_Label::create( $text, $classes );
					
			} // endif
			
		} // endif
		
		return $result;

	} // function

	/**
	 * Get the marker location as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL	The marker location if it is known, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_location( $map_type ) {
		return $this->location; // This is set when the object is created
	} // function

	/**
	 * Get the marker ID as a string.  This should be unique for a map that may contain multiple markers.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type ) {
		return $this->id; // This is set when the object is created
	} // function

	/**
	 * Get the marker position as an instance of Geographic_Position.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	Geographic_Position	The geographic position of the map marker
	 * @since v0.1.0
	 */
	public function get_map_marker_geographic_position( $map_type ) {
		return $this->geo_pos; // This is set when the object is created
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
		NULL;
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
		
		$sole_desc = $this->get_sole_event_descriptor();
		if ( ! empty( $sole_desc ) ) {

			$result = $sole_desc->get_map_marker_colour( $map_type );

		} else {
			// It's possible that there are several descriptors all using the same colour, we should check
			$desc_array = $this->get_event_descriptors_array();
			$result = empty( $desc_array ) ? Map_View::DEFAULT_MARKER_COLOUR : NULL; // use default when no descriptors (defensive)
			foreach( $desc_array as $descriptor ) {

				$colour = $descriptor->get_map_marker_colour( $map_type );
				if ( ! isset( $result ) ) {
					$result = $colour; // this is the first one so save it
				} elseif ( $colour !== $result ) {
					$result = Map_View::DEFAULT_MARKER_COLOUR; // We have at least 2 different colours, just use grey
					break;
				} // endif

			} // endfor

		} // endif

		
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

		$result = NULL;
		// For the admin stats maps we don't want to change opacity for hundreds of past events
		if ( $map_type !== Map_View::MAP_TYPE_ADMIN_STATS ) {

			$sole_event = $this->get_sole_event();
			if ( ! empty( $sole_event ) ) {
				
				$result = $sole_event->get_map_marker_opacity( $map_type );
	
			} else {
			
				// But for an event descriptor we need to check if the events for the current timeframe are complete 
				$result = $this->get_is_event_group_complete() ? 0.5 : 1;
				
			} // endif
		} // endif

		return $result;
	} // endif

	/**
	 * Get the content shown in the info window for the marker including any necessary html markup or NULL if no info is needed.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_info( $map_type ) {

		$sole_event = $this->get_sole_event();
		if ( ! empty( $sole_event ) ) {
			
			$result = $sole_event->get_map_marker_info( $map_type );

		} else {

			$sole_desc = $this->get_sole_event_descriptor();
			if ( ! empty( $sole_desc ) ) {
				
				$view = Event_Descriptor_View::create_for_map_info_window( $sole_desc, $map_type );
				$events_array = $this->get_events_by_descriptor( $sole_desc );
				$view->set_events_array( $events_array );
				$result = $view->get_object_view_content();
				
			} else {

				$view = Event_Descriptor_Group_View::create_for_map_info_window( $this, $map_type );
				$result = $view->get_object_view_content();
				
			} // endif
			
		} // endif
		
		return $result;

	} // function

} // class