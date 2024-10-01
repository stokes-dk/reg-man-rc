<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Events_Collection;

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
	
	private $events_collection;
	private $group_by;
	private $event_counts_array; // An associative array of event counts keyed based on the grouping, e.g. category
	
	private function __construct() { }

	/**
	 * Create the items stats object for the specified events and grouped in the specified way
	 *
	 * @param	Events_Collection	$events_collection	The collection specifying which event's items are to be included
	 * @param	string				$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 *
	 * @return Event_Stats_Collection	An instance of this class which provides the item group stats and their related data.
	 */
	public static function create_for_events_collection( $events_collection, $group_by ) {
		$result = new self();
		$result->events_collection = $events_collection;
		$result->group_by = $group_by;
		return $result;
	} // endif

	/**
	 * Get the collection of events
	 * @return Events_Collection
	 */
	private function get_events_collection() {
		return $this->events_collection;
	} // function

	private function get_group_by() {
		return $this->group_by;
	} // function

	/**
	 * A total count of all the events in every group
	 * @return	int		The total count of events
	 */
	public function get_total_event_count() {
		$events_collection = $this->get_events_collection();
		return $events_collection->get_event_count();
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
					$events_collection = $this->get_events_collection();
					$counts_array = self::get_category_count_array_for_events_collection( $events_collection );
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
	 * Get the array of event counts keyed by event category for the specified collection of events
	 * @param Events_Collection	$events_collection
	 * @return int[]
	 */
	private static function get_category_count_array_for_events_collection( $events_collection ) {
		$result = array();

		$events_array = $events_collection->get_events_array();
		
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