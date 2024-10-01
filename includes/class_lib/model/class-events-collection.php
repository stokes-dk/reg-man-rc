<?php

namespace Reg_Man_RC\Model;

// FIXME - Do I really need this???

class Events_Collection {

	private $events_array;
	private $event_count;
	private $event_keys_array;
	private $is_include_placeholder_events = FALSE; // TRUE when the colleciton includes placeholder events
	// i.e. events derived from item and volunteer registrations records but not found in the set of known events
	private $is_all_events = FALSE; // TRUE when the colleciton is created for ALL events rather than some subset
	
	private function __construct() {
	} // function

	/**
	 * Create a collection containing a single event 
	 * @param string $event_key
	 * @return Events_Collection
	 */
	public static function create_for_single_event_key( $event_key ) {
		return self::create_for_event_keys_array( array( $event_key ) );
	} // function
	
	/**
	 * Create a collection containing the specified array of event keys
	 * @param string[] $event_keys_array
	 * @return Events_Collection
	 */
	private static function create_for_event_keys_array( $event_keys_array ) {
		$result = new self();
		$result->event_keys_array = is_array( $event_keys_array ) ? $event_keys_array : array(); // Defensive
		return $result;
	} // function
	
	/**
	 * Create a collection containing the specified array of events
	 * @param Event[] $events_array
	 * @return Events_Collection
	 */
	public static function create_for_events_array( $events_array ) {
		$result = new self();
		$result->events_array = is_array( $events_array ) ? $events_array : array(); // Defensive
		$result->event_keys_array = array();
		foreach( $events_array as $event ) {
			$result->event_keys_array[] = $event->get_key_string();
		} // endfor
		return $result;
	} // function
	
	/**
	 * Create a collection for the specified event filter 
	 * @param Event_Filter	$event_filter
	 * @param boolean		$is_include_placeholder_events	Flag to indicate whether the result should include
	 * placeholder events derived from item and volunteer registrations but not found in the set of declared events
	 * @return Events_Collection
	 */
	public static function create_for_event_filter( $event_filter, $is_include_placeholder_events ) {
		if ( empty( $event_filter ) ) {
			$result = self::create_for_all_events( $is_include_placeholder_events );
		} else {
			$events_array = Event::get_all_events_by_filter( $event_filter, $is_include_placeholder_events );
			$result = self::create_for_events_array( $events_array );
		} // endif
		$result->is_include_placeholder_events = $is_include_placeholder_events;
		return $result;
	} // function
	
	/**
	 * Create a collection containing the specified array of events 
	 * @param string[] $event_keys_array
	 * @return Events_Collection
	 */
	public static function create_for_all_events( $is_include_placeholder_events ) {
		$events_array = Event::get_all_events( $is_include_placeholder_events );
		$result = self::create_for_events_array( $events_array );
		$result->is_include_placeholder_events = $is_include_placeholder_events;
		$result->is_all_events = TRUE; // In all other cases this will be FALSE
		return $result;
	} // function
	
	/**
	 * Create an instance of this class for the collection of events determined by an event filter
	 * @param	Event_Filter	$filter
	 * @return	Events_Collection
	 */
	public static function create_for_filter( $filter, $is_include_placeholder_events ) {

		$events_array = Event::get_all_events_by_filter( $filter, $is_include_placeholder_events );
		$result = self::create_for_events_array( $events_array );
		$result->is_include_placeholder_events = $is_include_placeholder_events;
		return $result;
		
	} // function
	
	/**
	 * Get the array of events
	 * @return Event[]
	 */
	public function get_events_array() {
		if ( ! isset( $this->events_array ) ) {
			$keys_array = $this->get_event_keys_array();
			$this->events_array = array();
			foreach( $keys_array as $event_key ) {
				$event = Event::get_event_by_key( $event_key );
				if ( ! empty( $event ) ) {
					$this->events_array[] = $event;
				} // endif
			} // endfor
		} // endif
		return $this->events_array;
	} // function
	
	/**
	 * Get the count of events
	 * @return int
	 */
	public function get_event_count() {
		if ( ! isset( $this->event_count ) ) {
			$this->event_count = count( $this->get_events_array() );
		} // endif
		return $this->event_count;
	} // function
	
	/**
	 * Get the array of event key strings
	 * @return string[]
	 */
	public function get_event_keys_array() {
		return $this->event_keys_array;
	} // function

	/**
	 * Get the flag indicating whether this collection includes placeholder events
	 * @return boolean
	 */
	public function get_is_include_placeholder_events() {
		return $this->is_include_placeholder_events;
	} // function

	/**
	 * Get the flag set to TRUE when collection is for all events, FALSE when it is some subset of events
	 * @return boolean
	 */
	public function get_is_all_events() {
		return $this->is_all_events;
	} // function

	public function get_is_empty() {
		$result = ( count( $this->event_keys_array ) == 0 );
		return $result;
	} // efunction
	
} // class
