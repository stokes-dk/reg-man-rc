<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Stats\Supplemental_Item;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;

/**
 * An instance of this class represents stats about how many items been fixed, not fixed and so on.
 * Statistics may be grouped by item description, item type, event or total.
 *
 * @since	v0.1.0
 *
 */
class Item_Statistics {

	const GROUP_BY_FIXER_STATION	= 'station';	// group items by their fixer station, e.g. "Appliances & Housewares"
	const GROUP_BY_ITEM_DESC		= 'desc';		// group items by their description, e.g. "Toaster"
	const GROUP_BY_ITEM_TYPE		= 'type';		// group by item type, e.g. "Electric / Electronic"
	const GROUP_BY_EVENT			= 'event';		// group by event
	const GROUP_BY_TOTAL			= 'total';		// group all items together so we can count the totals

	private $event_array_key;
	private $group_by;
	private $event_count;

	private $total_stats_array;
	private $internal_stats_array;
	private $external_stats_array;
	private $registered_stats_array;
	private $supplemental_stats_array;

	private function __construct() { }

	/**
	 * Create the items stats object for the specified events and grouped in the specified way
	 *
	 * @param	string[]|NULL	$event_key_array	An array of event key strings specifying which event's items are to be included
	 *  or NULL to get all item stats (from all events)
	 * @param	string			$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 *
	 * @return Item_Statistics	An instance of this class which provides the item group stats and their related data.
	 */
	public static function create_for_event_key_array( $event_key_array, $group_by ) {
		$result = new self();
		$result->event_array_key = $event_key_array;
		$result->event_count = is_array( $event_key_array ) ? count( $event_key_array ) : 0;
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
	 * @return Item_Statistics	An instance of this class which provides the item group stats and their related data.
	 *
	 */
	public static function create_item_stats_for_filter( $filter, $group_by ) {
		// If the filter is NULL then I will pass an event key array of NULL to signify that we want everything
		$keys_array = isset( $filter ) ? Event_Key::get_event_keys_for_filter( $filter ) : NULL;
		$result = self::create_for_event_key_array( $keys_array, $group_by );
		return $result;
	} // function

	private function get_event_key_array() {
		return $this->event_array_key;
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
	 * @return Item_Group_Stats[]	An array of instances of Item_Group_Stats describing the items and their related data.
	 */
	public function get_total_stats_array() {
		if ( ! isset( $this->total_stats_array ) ) {
			// Merge the registered and supplemental stats arrays
			$reg_stats_array = $this->get_registered_stats_array();
			$sup_stats_array = $this->get_supplemental_stats_array();
//	Error_Log::var_dump( $reg_stats_array );
//	Error_Log::var_dump( $sup_stats_array );

			$merged_stats = self::merge_item_stats_arrays( $reg_stats_array, $sup_stats_array );

			$group_by = $this->get_group_by();
//	Error_Log::var_dump( $group_by );
			switch ( $group_by ) {
				case self::GROUP_BY_ITEM_TYPE:
					$this->total_stats_array = self::sort_items_by_type( $merged_stats );
					break;
				case self::GROUP_BY_FIXER_STATION:
					$this->total_stats_array = self::sort_items_by_station( $merged_stats );
					break;
				default:
					$this->total_stats_array = $merged_stats; // Just don't fail if there's no group by
			} // endswitch

		} // endif
		return $this->total_stats_array;
	} // function

	private static function sort_items_by_type( $stats_array ) {
		$result = array();
		$all_types = Item_Type::get_all_item_types(); // this gives me the correct order
		foreach( $all_types as $type ) {
			$id = $type->get_id();
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Item_Group_Stats::create( $id );
		} // endfor

		// Include a row for "not specified"
		$not_specified_id = 0;
		if ( isset( $stats_array[ $not_specified_id ] ) ) {
			$result[ $not_specified_id ] = $stats_array[ $not_specified_id ];
		} // endif

		return $result;
	} // function

	private static function sort_items_by_station( $stats_array ) {
		$result = array();
		$all_stations = Fixer_Station::get_all_fixer_stations(); // this gives me the correct order
		foreach( $all_stations as $station ) {
			$id = $station->get_id();
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Item_Group_Stats::create( $id );
		} // endfor

		// Include a row for "not specified"
		$not_specified_id = 0;
		if ( isset( $stats_array[ $not_specified_id ] ) ) {
			$result[ $not_specified_id ] = $stats_array[ $not_specified_id ];
		} // endif

		return $result;
	} // function

	/**
	 * Get the item stats for all registered items for the specified events and grouped in the specified way
	 * @return Item_Group_Stats[]	An array of instances of Item_Group_Stats describing the items and their related data.
	 */
	public function get_registered_stats_array() {
		if ( ! isset( $this->registered_stats_array ) ) {

			// Merge the internal and external stats arrays
			$int_stats_array = $this->get_internal_stats_array();
			$ext_stats_array = $this->get_external_stats_array();

			$this->registered_stats_array = self::merge_item_stats_arrays( $int_stats_array, $ext_stats_array );
// Error_Log::var_dump( $int_stats_array, $ext_stats_array, $this->registered_stats_array );
		} // endif

		return $this->registered_stats_array;
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
				$result[ $name ] = Item_Group_Stats::create(
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
	 * @return Item_Group_Stats[]	An array of instances of Item_Group_Stats describing the items and their related data.
	 */
	public function get_supplemental_stats_array() {
		if ( ! isset( $this->supplemental_stats_array ) ) {
			$event_key_array = $this->get_event_key_array();
			$group_by = $this->get_group_by();
			$this->supplemental_stats_array = Supplemental_Item::get_supplemental_group_stats_array( $event_key_array, $group_by );
		} // endif
		return $this->supplemental_stats_array;
	} // function


	/**
	 * Get the internal items stats (stats for items registered using this plugin)
	 *  for the specified events and grouped in the specified way
	 * @return Item_Group_Stats[]	An array of instances of Item_Group_Stats describing the items and their related data.
	 */
	private function get_internal_stats_array() {
		if ( ! isset( $this->internal_stats_array ) ) {

			$event_key_array = $this->get_event_key_array();
			$group_by = $this->get_group_by();

			if ( is_array( $event_key_array) && ( count( $event_key_array ) == 0 ) ) {
				$this->internal_stats_array = array(); // The request is for an empty set of events so return an empty set
			} else {
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
						$name_col = 't.term_id';
						break;

					case self::GROUP_BY_ITEM_TYPE:
						$name_col = 't.term_id';
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

				if ( $group_by == self::GROUP_BY_FIXER_STATION ) {
//					$tax_name = Fixer_Station::TAXONOMY_NAME;
					$tt_ids = Fixer_Station::get_all_term_taxonomy_ids();
//					Error_Log::var_dump( $tt_ids );
					$from .=
						" LEFT JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						'   AND tr.term_taxonomy_id in ( ' . implode( ',', $tt_ids ) . ' ) ' .
						" LEFT JOIN $term_tax_table AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id " .
//						"   AND tt.taxonomy = '$tax_name' " .
						" LEFT JOIN $terms_table AS t ON t.term_id = tt.term_id ";
				} // endif

				if ( $group_by == self::GROUP_BY_ITEM_TYPE ) {
//					$tax_name = Item_Type::TAXONOMY_NAME;
					$tt_ids = Item_Type::get_all_term_taxonomy_ids();
//					Error_Log::var_dump( $tt_ids );
					$from .=
						" LEFT JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						'   AND tr.term_taxonomy_id in ( ' . implode( ',', $tt_ids ) . ' ) ' .
						" LEFT JOIN $term_tax_table AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id " .
//						"   AND tt.taxonomy = '$tax_name' " .
		  				" LEFT JOIN $terms_table AS t ON t.term_id = tt.term_id ";
				} // endif

				if ( ( $group_by == self::GROUP_BY_EVENT ) || ( ! empty( $event_key_array ) ) ) {
					// We need to join the meta table for the event key to group by event or get data for certain events
					$from .= " LEFT JOIN $meta_table AS event_meta ON p.ID = event_meta.post_id ";
					$where .= " AND event_meta.meta_key = '$event_meta_key' ";
				} // endif

				if ( empty( $event_key_array ) ) {
					$query = "$select $from $where $group_clause";
					$data_array = $wpdb->get_results( $query, ARRAY_A );
				} else {
					$placeholder_array = array_fill( 0, count( $event_key_array ), '%s' );
					$placeholders = implode( ',', $placeholder_array );
					$where .= " AND event_meta.meta_value IN ( $placeholders )";
					$query = "$select $from $where $group_clause";
					$stmt = $wpdb->prepare( $query, $event_key_array );
					$data_array = $wpdb->get_results( $stmt, ARRAY_A );
				} // endif

//Error_Log::var_dump( $query );
//Error_Log::var_dump( $event_key_array );
//Error_Log::var_dump( $data_array );

				$this->internal_stats_array = array();
				// When grouping by station or type and there is no station / item type assigned then name should be 0
				if ( $group_by == self::GROUP_BY_FIXER_STATION ) {
					$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				} elseif ( $group_by == self::GROUP_BY_ITEM_TYPE ) {
					$no_name = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
				} else {
					$no_name = '';
				} // endif
				if ( is_array( $data_array ) ) {
					foreach ( $data_array as $data ) {
						$name		= isset( $data[ 'name' ] )				? $data[ 'name' ]				: $no_name;
						$item_count	= isset( $data[ 'item_count' ] )		? $data[ 'item_count' ] 		: 0;
						$fixed		= isset( $data[ 'fixed_count' ] )		? $data[ 'fixed_count' ] 		: 0;
						$repairable	= isset( $data[ 'repairable_count' ] )	? $data[ 'repairable_count' ]	: 0;
						$eol		= isset( $data[ 'eol_count' ] )			? $data[ 'eol_count' ]			: 0;
						$instance = Item_Group_Stats::create( $name, $item_count, $fixed, $repairable, $eol );
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
	 * @return Item_Group_Stats[]	An array of instances of Item_Group_Stats describing the items and their related data.
	 */
	private function get_external_stats_array() {
		if ( ! isset( $this->external_stats_array ) ) {
			$this->external_stats_array = array();

			$event_key_array = $this->get_event_key_array();
			$group_by = $this->group_by;

			if ( $event_key_array === NULL ) {
				$key_data_array = NULL;
			} else {
				// Convert the Event_Key objects into associative arrays that can be understood by external sources
				$key_data_array = array();
				foreach( $event_key_array as $event_key ) {
					$key_obj = Event_Key::create_from_string( $event_key );
					$key_data_array[] = $key_obj->get_as_associative_array();
				} // endfor
			} // endif

			$ext_data = apply_filters( 'reg_man_rc_get_item_stats', array(), $key_data_array, $group_by );
			// The above returns an array of data arrays.  I will convert those into my internal objects
//			Error_Log::var_dump( $ext_data );

			$no_name = ( $group_by == self::GROUP_BY_ITEM_TYPE ) ? Item_Type::UNSPECIFIED_ITEM_TYPE_ID : '';
			$has_unspecified = FALSE; // if the external data contains item types we don't know then we need a row for that
			$unspecified_item_count	= 0; // counts for unspecified items
			$unspecified_fixed		= 0;
			$unspecified_repairable	= 0;
			$unspecified_eol		= 0;
//			Error_Log::var_dump( $group_by );
			foreach( $ext_data as $ext_data_row ) {
				$is_unspecified = FALSE;
				$name		= isset( $ext_data_row[ 'name' ] )	? $ext_data_row[ 'name' ]	: $no_name;
				// If items were grouped by type or station then external systems may use other names
				// We need to use the internal names so try to find the right one
				if ( $group_by == self::GROUP_BY_FIXER_STATION ) {
					$station = Fixer_Station::get_fixer_station_by_name( $name );
					if ( isset( $station ) ) {
						$name = $station->get_id(); // use our internal IDs
					} else {
						// Otherwise it's an unknown fixer station
						$is_unspecified = TRUE;
					} // endif
				} // endif
				if ( $group_by == self::GROUP_BY_ITEM_TYPE ) {
					$item_type = Item_Type::get_item_type_by_name( $name );
					if ( isset( $item_type ) ) {
						$name = $item_type->get_id(); // use our internal IDs
					} else {
						// Otherwise it's an unknown type
						$is_unspecified = TRUE;
					} // endif
				} // endif
				$item_count	= isset( $ext_data_row[ 'item_count' ] )		? intval( $ext_data_row[ 'item_count' ] ) 		: 0;
				$fixed		= isset( $ext_data_row[ 'fixed_count' ] )		? intval( $ext_data_row[ 'fixed_count' ] ) 		: 0;
				$repairable	= isset( $ext_data_row[ 'repairable_count' ] )	? intval( $ext_data_row[ 'repairable_count' ] )	: 0;
				$eol		= isset( $ext_data_row[ 'eol_count' ] )			? intval( $ext_data_row[ 'eol_count' ] )		: 0;
				if ( $is_unspecified ) {
					$has_unspecified = TRUE;
					$unspecified_item_count	+= $item_count;
					$unspecified_fixed		+= $fixed;
					$unspecified_repairable	+= $repairable;
					$unspecified_eol		+= $eol;
				} else {
					// Two external names may be used for the same internal name so we need to check if we already have a count
					if ( ! isset( $this->external_stats_array[ $name ] ) ) {
						$instance = Item_Group_Stats::create( $name, $item_count, $fixed, $repairable, $eol );
						$this->external_stats_array[ $name ] = $instance;
					} else {
						// We already have stats for this name so we need to add in the new values
						$instance = $this->external_stats_array[ $name ];
						$instance->add_to_counts( $item_count, $fixed, $repairable, $eol );
					} // endif
				} // endif
			} // endfor
			if ( $has_unspecified ) {
				$name = $no_name;
				$instance = Item_Group_Stats::create( $name, $unspecified_item_count, $unspecified_fixed, $unspecified_repairable, $unspecified_eol );
				$this->external_stats_array[ $name ] = $instance;
			} // endif

		} // endif

		return $this->external_stats_array;
	} // function



} // class