<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Events_Collection;

/**
 * An instance of this class provides sets of Item_Stats objects based on a set of event keys and a grouping.
 * For example, you can create an instance of this class for items from all events grouped by fixer station.
 * The get_all_stats_array() method of that instance will return an assoicative array of Item_Stats objects
 * keyed by fixer station ID.
 * The get_internal_registered_stats_array() method will return an associative array of Item_Stats objects 
 * only for the items registered internally, and not including supplemental item data or items registered externally.
 *
 * @since	v0.1.0
 *
 */
class Item_Stats_Collection {

	const GROUP_BY_FIXER_STATION	= 'station';			// group items by their fixer station, e.g. "Appliances & Housewares"
	const GROUP_BY_ITEM_DESC		= 'desc';				// group items by their description, e.g. "Toaster"
	const GROUP_BY_ITEM_TYPE		= 'type';				// group by item type, e.g. "Electric / Electronic"
	const GROUP_BY_STATION_AND_TYPE	= 'station_and_type';	// group by fixer station and item type, e.g. "Appliances|Electric / Electronic"
	const GROUP_BY_EVENT			= 'event';				// group by event
	const GROUP_BY_TOTAL			= 'total';				// group all items together so we can count the totals
	
	private $event_keys_array;
	private $group_by;
	private $event_count;

	private $all_stats_array;
	private $internal_stats_array;
	private $external_stats_array;
	private $all_registered_stats_array;
	private $supplemental_stats_array;

	private function __construct() { }

	/**
	 * Create an Item_Stats_Collection for the specified event collection and grouped in the specified way
	 *
	 * @param	Events_Collection	$events_collection	A collection specifying which event's items are to be included
	 * @param	string				$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 *
	 * @return Item_Stats_Collection	An instance of this class which provides the item group stats and their related data.
	 */
	public static function create_for_events_collection( $events_collection, $group_by ) {
		$result = new self();
		$result->event_count = $events_collection->get_event_count();
		// We will store NULL for the event keys array if this collection is for ALL events
		$result->event_keys_array = $events_collection->get_is_all_events() ? NULL : $events_collection->get_event_keys_array();
		$result->group_by = $group_by;
		return $result;
	} // endif

	private function get_event_keys_array() {
		return $this->event_keys_array;
	} // function

	private function get_group_by() {
		return $this->group_by;
	} // function

	/**
	 * A count of the events for this object
	 * @return	int		The count of events
	 */
	public function get_event_count() {
		return $this->event_count;
	} // function

	/**
	 * Get the item stats for all items including registered items and supplemental for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_all_stats_array() {
		if ( ! isset( $this->all_stats_array ) ) {
			// Merge the registered and supplemental stats arrays
			$reg_stats_array = $this->get_all_registered_stats_array();
			$sup_stats_array = $this->get_supplemental_stats_array();
//	Error_Log::var_dump( $reg_stats_array );
//	Error_Log::var_dump( $sup_stats_array );

			$merged_stats = self::merge_item_stats_arrays( $reg_stats_array, $sup_stats_array );

			$group_by = $this->get_group_by();
//	Error_Log::var_dump( $group_by );

			// Sort the stats so they always appear in the same order and fill in missing entries as necessary
			switch ( $group_by ) {
				
				case self::GROUP_BY_ITEM_TYPE:
					$this->all_stats_array = self::sort_and_fill_items_by_type( $merged_stats );
					break;
				
				case self::GROUP_BY_FIXER_STATION:
					$this->all_stats_array = self::sort_and_fill_items_by_station( $merged_stats );
					break;
				
				case self::GROUP_BY_STATION_AND_TYPE:
					$this->all_stats_array = self::sort_items_by_station_and_type( $merged_stats );
					break;
				
				default:
					$this->all_stats_array = $merged_stats; // Just don't fail if there's no group by
					break;
					
			} // endswitch

		} // endif
		return $this->all_stats_array;
	} // function

	/**
	 * Sort the stats array so that they are always displayed in the same order and fill in any missing entries
	 * @param	Item_Stats[] $stats_array
	 * @return 	Item_Stats[]
	 */
	private static function sort_and_fill_items_by_type( $stats_array ) {
		$result = array();
		$all_types = Item_Type::get_all_item_types(); // this gives me the correct order
		foreach( $all_types as $type ) {
			$id = $type->get_id();
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Item_Stats::create( $id );
		} // endfor

		// Include a row for "not specified" if necessary
		$not_specified_id = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
		if ( isset( $stats_array[ $not_specified_id ] ) ) {
			$result[ $not_specified_id ] = $stats_array[ $not_specified_id ];
		} // endif

		return $result;
	} // function

	/**
	 * Sort the stats array so that they are always displayed in the same order and fill in any missing entries
	 * @param	Item_Stats[] $stats_array
	 * @return 	Item_Stats[]
	 */
	private static function sort_and_fill_items_by_station( $stats_array ) {
		$result = array();
		$all_stations = Fixer_Station::get_all_fixer_stations(); // this gives me the correct order
		foreach( $all_stations as $station ) {
			$id = $station->get_id();
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Item_Stats::create( $id );
		} // endfor

		// Include a row for "not specified" if necessary
		$not_specified_id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
		if ( isset( $stats_array[ $not_specified_id ] ) ) {
			$result[ $not_specified_id ] = $stats_array[ $not_specified_id ];
		} // endif

		return $result;
	} // function

	/**
	 * Sort the stats array so that they are always displayed in the same order
	 * @param	Item_Stats[] $stats_array
	 * @return 	Item_Stats[]
	 */
	private static function sort_items_by_station_and_type( $stats_array ) {
		$result = array();

		// Start by grouping by fixer station then by item type within each station
		$all_stations = Fixer_Station::get_all_fixer_stations();
		$all_types = Item_Type::get_all_item_types();
		$unspecified_station_id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
		$unspecified_type_id = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
				
		foreach( $all_stations as $station ) {

			$station_id = $station->get_id();
			
			foreach( $all_types as $type ) {

				$type_id = $type->get_id();
				$name = "$station_id|$type_id";
				if ( isset( $stats_array[ $name ] ) ) {
					$result[ $name ] = $stats_array[ $name ];
				} // endif

			} // endfor

			// Include a row for "not specified" type for this station if necessary
			$name = "$station_id|$unspecified_type_id";
			if ( isset( $stats_array[ $name ] ) ) {
				$result[ $name ] = $stats_array[ $name ];
			} // endif
				
		} // endfor

		// Include rows for "not specified" station if necessary
		foreach( $all_types as $type ) {

			$type_id = $type->get_id();
			$name = "$unspecified_station_id|$type_id";
			if ( isset( $stats_array[ $name ] ) ) {
				$result[ $name ] = $stats_array[ $name ];
			} // endif

		} // endfor

		// Include a row for "not specified" station and "not specified" type if necessary
		$name = "$unspecified_station_id|$unspecified_type_id";
		if ( isset( $stats_array[ $name ] ) ) {
			$result[ $name ] = $stats_array[ $name ];
		} // endif
				
		return $result;
	} // function

	
	/**
	 * Get the item stats for all registered items for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_all_registered_stats_array() {
		if ( ! isset( $this->all_registered_stats_array ) ) {

			// Merge the internal and external stats arrays
			$int_stats_array = $this->get_internal_registered_stats_array();
			$ext_stats_array = $this->get_external_registered_stats_array();

			$this->all_registered_stats_array = self::merge_item_stats_arrays( $int_stats_array, $ext_stats_array );
// Error_Log::var_dump( $int_stats_array, $ext_stats_array, $this->all_registered_stats_array );
		} // endif

		return $this->all_registered_stats_array;
	} // function

	private static function merge_item_stats_arrays( $array_1, $array_2 ) {
		$result = $array_1 + $array_2; // union of the two arrays bassed on keys
		// For keys that are unique to each array, the values in the array are as we want them
		// We now need to sum the counts from the two arrays for any keys that overlap
		$result_keys = array_keys( $result );
		foreach( $result_keys as $name ) {
			if ( isset( $array_1[ $name ] ) && isset( $array_2[ $name ] ) ) {
				$stats_1 = $array_1[ $name ];
				$stats_2 = $array_2[ $name ];
				// Add the two matching rows together
				$result[ $name ] = Item_Stats::create(
						$name,
						$stats_1->get_item_count()			+ $stats_2->get_item_count(),
						$stats_1->get_fixed_count()			+ $stats_2->get_fixed_count(),
						$stats_1->get_repairable_count()	+ $stats_2->get_repairable_count(),
						$stats_1->get_end_of_life_count()	+ $stats_2->get_end_of_life_count()
					);
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get the supplemental item stats for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_supplemental_stats_array() {
		if ( ! isset( $this->supplemental_stats_array ) ) {
			$event_keys_array = $this->get_event_keys_array();
			$group_by = $this->get_group_by();
			$this->supplemental_stats_array = Supplemental_Item::get_supplemental_group_stats_array( $event_keys_array, $group_by );
		} // endif
		return $this->supplemental_stats_array;
	} // function

	/**
	 * Get the internal items stats (stats for items registered using this plugin)
	 *  for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_internal_registered_stats_array() {
		
		if ( ! isset( $this->internal_stats_array ) ) {

			$event_keys_array = $this->get_event_keys_array();
			$group_by = $this->get_group_by();
//			Error_Log::var_dump( $event_keys_array, $group_by );

			if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) == 0 ) ) {
				
				$this->internal_stats_array = array(); // The request is for an empty set of events so return an empty set

			} else {

				// Otherwise, the request is for ALL events (event keys array is NULL) or some subset

				global $wpdb;

				$posts_table = $wpdb->posts;
				$meta_table = $wpdb->postmeta;
				$term_rels_table = $wpdb->term_relationships;
				$term_tax_table = $wpdb->term_taxonomy;
				$terms_table = $wpdb->terms;

				$event_meta_key = Item::EVENT_META_KEY;
				$status_key = Item::STATUS_META_KEY;
				$fixed_val = Item_Status::FIXED;
				$repairable_val = Item_Status::REPAIRABLE;
				$eol_val = Item_Status::END_OF_LIFE;
				$item_post_type = Item::POST_TYPE;

				switch ( $group_by ) {

					case self::GROUP_BY_EVENT:
						$name_col = 'event_meta.meta_value';
						break;

					case self::GROUP_BY_ITEM_DESC:
						$name_col = 'p.post_title';
						break;

					case self::GROUP_BY_FIXER_STATION:
						$name_col = 'station_terms.term_id';
						break;

					case self::GROUP_BY_ITEM_TYPE:
						$name_col = 'type_terms.term_id';
						break;

					case self::GROUP_BY_STATION_AND_TYPE:
						$name_col = 
							'CONCAT( ' .
								" CASE WHEN station_terms.term_id IS NULL THEN '0' ELSE station_terms.term_id END," .
								" '|', " .
								" CASE WHEN type_terms.term_id IS NULL THEN '0' ELSE type_terms.term_id END " .
							')';
						break;

					default:
						$name_col = "''";
						break;

				} // endswitch

				$select = "SELECT $name_col AS name, count(*) AS item_count, " .
							" SUM( CASE WHEN fixed_meta.meta_value = '$fixed_val' THEN 1 ELSE 0 END ) AS fixed_count, " .
							" SUM( CASE WHEN fixed_meta.meta_value = '$repairable_val' THEN 1 ELSE 0 END ) AS repairable_count, " .
							" SUM( CASE WHEN fixed_meta.meta_value = '$eol_val' THEN 1 ELSE 0 END ) AS eol_count ";

				$from =	" FROM $posts_table AS p ";
				$from .= " LEFT JOIN $meta_table AS fixed_meta ON p.ID = fixed_meta.post_id AND fixed_meta.meta_key = '$status_key' ";
				$where = " WHERE post_type = '$item_post_type' AND p.post_status = 'publish' ";
				$group_clause = ' GROUP BY name ';

				if (( $group_by == self::GROUP_BY_FIXER_STATION ) ||
					( $group_by == self::GROUP_BY_STATION_AND_TYPE ) ) {
					$station_tt_ids = Fixer_Station::get_all_term_taxonomy_ids();
//					Error_Log::var_dump( $station_tt_ids );
					$from .=
						" LEFT JOIN $term_rels_table AS station_tr ON p.ID = station_tr.object_id " .
						'   AND station_tr.term_taxonomy_id in ( ' . implode( ',', $station_tt_ids ) . ' ) ' .
						" LEFT JOIN $term_tax_table AS station_tt ON station_tt.term_taxonomy_id = station_tr.term_taxonomy_id " .
						" LEFT JOIN $terms_table AS station_terms ON station_terms.term_id = station_tt.term_id ";
				} // endif

				if (( $group_by == self::GROUP_BY_ITEM_TYPE ) ||
					( $group_by == self::GROUP_BY_STATION_AND_TYPE ) ) {
					$type_tt_ids = Item_Type::get_all_term_taxonomy_ids();
//					Error_Log::var_dump( $type_tt_ids );
					$from .=
						" LEFT JOIN $term_rels_table AS type_tr ON p.ID = type_tr.object_id " .
						'   AND type_tr.term_taxonomy_id in ( ' . implode( ',', $type_tt_ids ) . ' ) ' .
						" LEFT JOIN $term_tax_table AS type_tt ON type_tt.term_taxonomy_id = type_tr.term_taxonomy_id " .
		  				" LEFT JOIN $terms_table AS type_terms ON type_terms.term_id = type_tt.term_id ";
				} // endif

				if ( ( $group_by == self::GROUP_BY_EVENT ) || ( ! empty( $event_keys_array ) ) ) {
					// We need to join the meta table for the event key to group by event or get data for certain events
					$from .= " LEFT JOIN $meta_table AS event_meta ON p.ID = event_meta.post_id ";
					$where .= " AND event_meta.meta_key = '$event_meta_key' ";
				} // endif

				if ( empty( $event_keys_array ) ) {
					$query = "$select $from $where $group_clause";
					$data_array = $wpdb->get_results( $query, ARRAY_A );
				} else {
					$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
					$placeholders = implode( ',', $placeholder_array );
					$where .= " AND event_meta.meta_value IN ( $placeholders )";
					$query = "$select $from $where $group_clause";
					$stmt = $wpdb->prepare( $query, $event_keys_array );
					$data_array = $wpdb->get_results( $stmt, ARRAY_A );
				} // endif

// Error_Log::var_dump( $query );
//Error_Log::var_dump( $event_keys_array );
//Error_Log::var_dump( $data_array );

				$this->internal_stats_array = array();
				
				// When grouping by station or type and there is no station / item type assigned then name should be 0
				switch( $group_by ) {
					
					case self::GROUP_BY_FIXER_STATION:
						$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
						break;
						
					case self::GROUP_BY_ITEM_TYPE:
						$no_name = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
						break;
						
					case self::GROUP_BY_STATION_AND_TYPE:
						$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID . '|' . Item_Type::UNSPECIFIED_ITEM_TYPE_ID ;
						break;
					
					default:
						$no_name = '';
						break;

				} // endswitch
				
				if ( is_array( $data_array ) ) {
					foreach ( $data_array as $data ) {
						$name		= isset( $data[ 'name' ] )				? $data[ 'name' ]				: $no_name;
						$item_count	= isset( $data[ 'item_count' ] )		? $data[ 'item_count' ] 		: 0;
						$fixed		= isset( $data[ 'fixed_count' ] )		? $data[ 'fixed_count' ] 		: 0;
						$repairable	= isset( $data[ 'repairable_count' ] )	? $data[ 'repairable_count' ]	: 0;
						$eol		= isset( $data[ 'eol_count' ] )			? $data[ 'eol_count' ]			: 0;
						$instance = Item_Stats::create( $name, $item_count, $fixed, $repairable, $eol );
						$this->internal_stats_array[ $name ] = $instance;
					} // endfor
				} // endif
			} // endif
		} // endif
		return $this->internal_stats_array;
	} // function

	/**
	 * Get the external items fixed stats (stats for items registered using an registration source other than this plugin)
	 *  for the specified events and grouped in the specified way
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public function get_external_registered_stats_array() {
		if ( ! isset( $this->external_stats_array ) ) {
			$this->external_stats_array = array();

			$event_keys_array = $this->get_event_keys_array();
			$group_by = $this->group_by;

			if ( $event_keys_array === NULL ) {
				$key_data_array = NULL;
			} else {
				// Convert the Event_Key objects into associative arrays that can be understood by external sources
				$key_data_array = array();
				foreach( $event_keys_array as $event_key ) {
					$key_obj = Event_Key::create_from_string( $event_key );
					$key_data_array[] = $key_obj->get_as_associative_array();
				} // endfor
			} // endif

//			Error_Log::var_dump( $key_data_array );
			$ext_data = apply_filters( 'reg_man_rc_get_item_stats', array(), $key_data_array, $group_by );
			// The above returns an array of data arrays.  I will convert those into my internal objects
//			Error_Log::var_dump( $ext_data );

//			Error_Log::var_dump( $group_by );
			switch( $group_by ) {
				
				case self::GROUP_BY_ITEM_TYPE:
					$no_name = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
					break;
					
				case self::GROUP_BY_FIXER_STATION:
					$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
					break;
					
				case self::GROUP_BY_STATION_AND_TYPE:
					$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID . '|' . Item_Type::UNSPECIFIED_ITEM_TYPE_ID ;
					break;
					
				default:
					$no_name = ''; // In other cases missing name data is replaced by empty string
					break;
					
			} // endswitch

			foreach( $ext_data as $ext_data_row ) {
//				$is_unspecified = FALSE;
				$name = isset( $ext_data_row[ 'name' ] ) ? $ext_data_row[ 'name' ] : $no_name;
				
				// If items were grouped by type or station then external systems may use other names
				// We need to use the internal names so try to find the right one
				// When grouping by event then we need to find the right internal event key
				switch( $group_by ) {
					
					case self::GROUP_BY_FIXER_STATION:
						$station = Fixer_Station::get_fixer_station_by_name( $name );
						$name = isset( $station ) ? $station->get_id() : $no_name;
						break;
						
					case self::GROUP_BY_ITEM_TYPE:
						$item_type = Item_Type::get_item_type_by_name( $name );
						$name = isset( $item_type ) ? $item_type->get_id() : $no_name;
						break;
						
					case self::GROUP_BY_STATION_AND_TYPE:
						$parts = explode( '|', $name );
						$station = isset( $parts[ 0 ] ) ? Fixer_Station::get_fixer_station_by_name( $parts[ 0 ] ) : NULL;
						$type = isset( $parts[ 1 ] ) ? Item_Type::get_item_type_by_name( $parts[ 1 ] ) : NULL;
						$station_id = isset( $station ) ? $station->get_id() : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
						$type_id = isset( $type ) ? $type->get_id() : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
						$name = "$station_id|$type_id";
						break;

					case self::GROUP_BY_EVENT:
						$key_obj = Event_Key::create_from_string( $name );
						$name = ! empty( $key_obj ) ? $key_obj->get_as_string() : $name;
//						Error_Log::var_dump( $name );
						break;
						
				} // endswitch
				
				$item_count	= isset( $ext_data_row[ 'item_count' ] )		? intval( $ext_data_row[ 'item_count' ] ) 		: 0;
				$fixed		= isset( $ext_data_row[ 'fixed_count' ] )		? intval( $ext_data_row[ 'fixed_count' ] ) 		: 0;
				$repairable	= isset( $ext_data_row[ 'repairable_count' ] )	? intval( $ext_data_row[ 'repairable_count' ] )	: 0;
				$eol		= isset( $ext_data_row[ 'eol_count' ] )			? intval( $ext_data_row[ 'eol_count' ] )		: 0;

				// Two external names may be used for the same internal name so we need to check if we already have a count
				if ( ! isset( $this->external_stats_array[ $name ] ) ) {
					$instance = Item_Stats::create( $name, $item_count, $fixed, $repairable, $eol );
					$this->external_stats_array[ $name ] = $instance;
				} else {
					// We already have stats for this name so we need to add in the new values
					$instance = $this->external_stats_array[ $name ];
					$instance->add_to_counts( $item_count, $fixed, $repairable, $eol );
				} // endif
			} // endfor
		} // endif

		return $this->external_stats_array;
	} // function

} // class