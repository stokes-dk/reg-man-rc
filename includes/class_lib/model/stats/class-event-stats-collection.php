<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Event_Category;

/**
 * An instance of this class provides sets of event counts based on a set of event keys and a grouping.
 *
 * @since	v0.4.0
 *
 */
class Event_Stats_Collection {

	const CATEGORY_NOT_SPECIFIED	= -1; // Used to count events that do not specify any category
	
	const GROUP_BY_EVENT_CATEGORY	= 'category';			// group events by their category, e.g. "Mini Repair Cafe"
	const GROUP_BY_TOTAL			= 'total';				// group all items together so we can count the totals
	
	private $event_keys_array;
	private $group_by;
	private $event_counts_array; // An associative array of event counts keyed based on the grouping, e.g. category
	private $total_event_count; // A total count of all events, summing all groups
	
	private static $ALL_KNOWN_EVENTS_COUNT; // A count of all events known to the system, stored for easy re-use
	
	private function __construct() { }

	/**
	 * Create the items stats object for the specified events and grouped in the specified way
	 *
	 * @param	string[]|NULL	$event_keys_array	An array of event key strings specifying which event's items are to be included
	 *  or NULL to get all item stats (from all events)
	 * @param	string			$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 *
	 * @return Event_Stats_Collection	An instance of this class which provides the item group stats and their related data.
	 */
	public static function create_for_event_keys_array( $event_keys_array, $group_by ) {
		$result = new self();
		$result->event_keys_array = $event_keys_array;
		$result->total_event_count = is_array( $event_keys_array ) ? count( $event_keys_array ) : self::get_all_known_events_count();
		$result->group_by = $group_by;
		return $result;
	} // endif

	/**
	 * Get the items fixed stats for items registered to the set of events derived from the specified filter.
	 *
	 * If the event filter is NULL then all items fixed stats will be returned.

	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  items stats are to be returned, or NULL if all stats are to be returned.
	 * @param	string				$group_by	One of the GROUP_BY_* constants defined in this class which specifies
	 *  how the stats are to be grouped.
	 *  If this argument is GROUP_BY_ITEM_TYPE, for example, then the result will contain one row for each item type
	 *  and the stats will contain the data for that type.
	 * @return Item_Stats_Collection	An instance of this class which provides the item group stats and their related data.
	 *
	 */
	public static function create_for_event_filter( $filter, $group_by ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$result = self::create_for_event_keys_array( $keys_array, $group_by );
		return $result;
	} // function

	/**
	 * Get a count of all events known to the system.
	 * @return int
	 */
	public static function get_all_known_events_count() {
		if ( ! isset( self::$ALL_KNOWN_EVENTS_COUNT ) ) {
			$all_events = Event::get_all_events();
			self::$ALL_KNOWN_EVENTS_COUNT = count( $all_events );
		} // endif
		return self::$ALL_KNOWN_EVENTS_COUNT;
	} // endif

	private function get_event_keys_array() {
		return $this->event_keys_array;
	} // function

	private function get_group_by() {
		return $this->group_by;
	} // function

	/**
	 * A total count of all the events in every group
	 * @return	int		The total count of events
	 */
	public function get_total_event_count() {
		return $this->total_event_count;
	} // function

	/**
	 * Get the item stats for all items including registered items and supplemental for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_event_counts_array() {
		if ( ! isset( $this->event_counts_array ) ) {

			$group_by = $this->get_group_by();
			
			switch ( $group_by ) {
				
				case self::GROUP_BY_EVENT_CATEGORY:
					$event_keys_array = $this->get_event_keys_array();
					$counts_array = self::get_category_count_array_for_events( $event_keys_array );
					$this->event_counts_array = self::sort_and_fill_counts_by_category( $counts_array );
					break;

				case self::GROUP_BY_TOTAL:
				default:
					$this->event_counts_array = array( '' => $this->get_total_event_count() );
					break;
					
			} // endswitch

		} // endif
		return $this->event_counts_array;
	} // function

	/**
	 * Get the array of event counts keyed by event category for the specified array of event keys
	 * @param string[] $event_keys_array
	 * @return int[]
	 */
	private static function get_category_count_array_for_events( $event_keys_array ) {
		$result = array();

		if ( is_array( $event_keys_array ) ) {
			$events_array = array();
			foreach ( $event_keys_array as $event_key ) {
				$events_array[] = Event::get_event_by_key( $event_key );
			} // endfor
		} else {
			$events_array = Event::get_all_events();
		} // endif
		
		foreach( $events_array as $event ) {
			// Note that events may be supplied by external providers who don't have access to our category IDs
			$category_names = $event->get_categories(); // This is an array of category names for the event
			if ( empty( $category_names ) ) {
				$category_names = array( self::CATEGORY_NOT_SPECIFIED ); //marker for uncategorized events
			} // endif
			foreach( $category_names as $cat_name ) {
				// I need to group items together even when they use aka names
				$category = Event_Category::get_event_category_by_name( $cat_name );
				if ( isset( $category ) ) {
					$cat_id = $category->get_id();
				} // endif
				if ( ! isset( $result[ $cat_id ] ) ) {
					$result[ $cat_id ] = 1;
				} else {
					$result[ $cat_id ]++;
				} // endif
			} // endfor
		} // endfor

		return $result;
	} // function

	/**
	 * Sort the counts array so that they are always displayed in the same order and fill in any missing entries
	 * @param	int[] $counts_array
	 * @return 	int[]
	 */
	private static function sort_and_fill_counts_by_category( $counts_array ) {
		$result = array();
		$all_categories = Event_Category::get_all_event_categories(); // this gives me the correct order
		foreach( $all_categories as $category ) {
			$id = $category->get_id();
			$result[ $id ] = isset( $counts_array[ $id ] ) ? $counts_array[ $id ] : 0;
		} // endfor

		// Note that there are no uncategorized events internally but it is possible that an external provider
		//  may have events that have no category
		// Include a row for "not specified" type for this station if necessary
		$id = self::CATEGORY_NOT_SPECIFIED;
		if ( isset( $counts_array[ $id ] ) ) {
			$result[ $id ] = $counts_array[ $id ];
		} // endif
		
		return $result;
	} // function

} // class