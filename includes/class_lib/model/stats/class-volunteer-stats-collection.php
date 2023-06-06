<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;

/**
 * An instance of this class provides sets of Volunteer_Stats objects based on a set of event keys and a grouping.
 * For example, you can create an instance of this class for volunteers at all events grouped by volunteer_role.
 * The get_all_stats_array() method of that instance will return an assoicative array of Volunteer_Stats objects
 * keyed by volunteer role ID.
 * The get_internal_registered_stats_array() method will return an associative array of Volunteer_Stats objects 
 * only for the volunteers registered internally, and not including supplemental volunteer data or external
 * volunteer registrations like those from the legacy system.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Stats_Collection {

	const GROUP_BY_EVENT				= 'event'; // group volunteer registrations by event
	const GROUP_BY_VOLUNTEER_ROLE		= 'role'; // group registrations by their volunteer role, e.g. "Greeter"
	const GROUP_BY_FIXER_STATION		= 'station'; // group by their fixer station, e.g. "Computers"
	const GROUP_BY_TOTAL				= 'total'; // group all registrations together so we can count the totals
	const GROUP_BY_TOTAL_FIXERS			= 'total_fixers'; // get the total registrations for fixers
	const GROUP_BY_TOTAL_NON_FIXERS		= 'total_non_fixers'; // get the total registrations for non-fixers

	private $event_array_key;
	private $group_by;
	private $event_count;

	private $all_stats_array;
	private $internal_stats_array;
	private $external_stats_array;
	private $all_registered_stats_array;
	private $supplemental_stats_array;

	private function __construct() { }

	/**
	 * Create the stats object for the specified events and grouped in the specified way
	 *
	 * @param	string[]|NULL	$event_key_array	An array of event key strings specifying which event's volunteers are to be included
	 *  or NULL to get all volunteer stats (from all events)
	 * @param	string			$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 *
	 * @return Volunteer_Stats_Collection
	 */
	public static function create_for_event_key_array( $event_key_array, $group_by ) {
		$result = new self();
		$result->event_array_key = $event_key_array;
		if ( is_array( $event_key_array ) ) {
			$result->event_count = count( $event_key_array );
		} else {
			$result->event_count = Event_Stats_Collection::get_all_known_events_count();
		} // endif
		$result->group_by = $group_by;
		return $result;
	} // endif

	/**
	 * Get the stats for the set of events derived from the specified filter.
	 *
	 * If the event filter is NULL then all items fixed stats will be returned.

	 * @param	Event_Filter|NULL	$filter		An Event_Filter instance which limits the set of events whose
	 *  stats are to be returned, or NULL if all stats are to be returned.
	 * @param	string				$group_by	One of the GROUP_BY_* constants defined in this class which specifies
	 *  how the stats are to be grouped.
	 * @return Volunteer_Stats_Collection
	 *
	 */
	public static function create_for_filter( $filter, $group_by ) {
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
	 * Get the stats for all voluntters including registered and supplemental for the specified events and grouped in the specified way
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the number of volunteers.
	 */
	public function get_all_stats_array() {
		if ( ! isset( $this->all_stats_array ) ) {
			// Merge the registered and supplemental stats arrays
			$reg_stats_array = $this->get_all_registered_stats_array();
			$sup_stats_array = $this->get_supplemental_stats_array();

			$merged_stats = self::merge_stats_arrays( $reg_stats_array, $sup_stats_array );
			$group_by = $this->get_group_by();
//	Error_Log::var_dump( $group_by );
			switch ( $group_by ) {
				case self::GROUP_BY_VOLUNTEER_ROLE:
					$this->all_stats_array = self::sort_items_by_volunteer_role( $merged_stats );
					break;
				case self::GROUP_BY_FIXER_STATION:
					$this->all_stats_array = self::sort_items_by_station( $merged_stats );
					break;
				default:
					$this->all_stats_array = $merged_stats; // Just don't fail if there's no group by
			} // endswitch

		} // endif
		return $this->all_stats_array;
	} // function

	private static function sort_items_by_volunteer_role( $stats_array ) {
		$result = array();
		$all_roles = Volunteer_Role::get_all_volunteer_roles(); // this gives me the correct order
		foreach( $all_roles as $role ) {
			$id = $role->get_id();
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Volunteer_Stats::create( $id );
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
			$result[ $id ] = isset( $stats_array[ $id ] ) ? $stats_array[ $id ] : Volunteer_Stats::create( $id );
		} // endfor

		// Include a row for "not specified"
		$not_specified_id = 0;
		if ( isset( $stats_array[ $not_specified_id ] ) ) {
			$result[ $not_specified_id ] = $stats_array[ $not_specified_id ];
		} // endif

		return $result;
	} // function


	/**
	 * Get the stats for all registered volunteers for the specified events and grouped in the specified way
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the items and their related data.
	 */
	public function get_all_registered_stats_array() {
		if ( ! isset( $this->all_registered_stats_array ) ) {

			// Merge the internal and external stats arrays
			$int_stats_array = $this->get_internal_registered_stats_array();
			$ext_stats_array = $this->get_external_registered_stats_array();
//	Error_Log::var_dump( $ext_stats_array );

			$this->all_registered_stats_array = self::merge_stats_arrays( $int_stats_array, $ext_stats_array );
// Error_Log::var_dump( $int_stats_array, $ext_stats_array, $this->all_registered_stats_array );
		} // endif

		return $this->all_registered_stats_array;
	} // function

	/**
	 * Merge two arrays of stats
	 * @param	Volunteer_Stats[]	$array_1
	 * @param	Volunteer_Stats[]	$array_2
	 * @return	Volunteer_Stats[]	The two arrays merged with their totals summed
	 */
	private static function merge_stats_arrays( $array_1, $array_2 ) {
		$result = $array_1 + $array_2; // union of the two arrays bassed on keys
		// For keys that are unique to each array, the values in the array are as we want them
		// We now need to sum the counts from the two arrays for any keys that overlap
		$result_keys = array_keys( $result );
		foreach( $result_keys as $name ) {
			if ( isset( $array_1[ $name ] ) && isset( $array_2[ $name ] ) ) {
				$stats_1 = $array_1[ $name ];
				$stats_2 = $array_2[ $name ];
				// Add the two matching rows together
				$result[ $name ] = Volunteer_Stats::create(
						$name,
						$stats_1->get_head_count() + $stats_2->get_head_count()
					);
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get the supplemental stats for the specified events and grouped in the specified way
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the volunteers and their related head counts.
	 */
	public function get_supplemental_stats_array() {
		if ( ! isset( $this->supplemental_stats_array ) ) {
			$event_key_array = $this->get_event_key_array();
			$group_by = $this->get_group_by();
			$this->supplemental_stats_array = Supplemental_Volunteer_Registration::get_supplemental_group_stats_array( $event_key_array, $group_by );
		} // endif
		return $this->supplemental_stats_array;
	} // function

	/**
	 * Get the internal items stats (stats for items registered using this plugin)
	 *  for the specified events and grouped in the specified way
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the items and their related data.
	 */
	public function get_internal_registered_stats_array() {
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

				$event_meta_key = Volunteer_Registration::EVENT_META_KEY;
				$apprentice_meta_key = Volunteer_Registration::IS_APPRENTICE_META_KEY;
//				$attendance_meta_key = Volunteer_Registration::ATTENDANCE_META_KEY;
				$reg_post_type = Volunteer_Registration::POST_TYPE;
				$role_taxonomy = Volunteer_Role::TAXONOMY_NAME;
				$station_taxonomy = Fixer_Station::TAXONOMY_NAME;

				switch ( $group_by ) {

					case self::GROUP_BY_EVENT:
						$name_col = 'event_meta.meta_value';
						break;

					case self::GROUP_BY_VOLUNTEER_ROLE:
						$name_col = 'COALESCE( t.term_id, 0 )'; // We need NULL to become 0
						break;

					case self::GROUP_BY_FIXER_STATION:
						$name_col = 't.term_id';
						break;

					case self::GROUP_BY_TOTAL_FIXERS:
					case self::GROUP_BY_TOTAL_NON_FIXERS:
					case self::GROUP_BY_TOTAL:
					default:
						$name_col = "''";
						break;

				} // endswitch

				$select = "SELECT $name_col AS name, count(*) AS head_count";
				$from =	" FROM $posts_table AS p ";
				$where = " WHERE p.post_type = '$reg_post_type' AND p.post_status = 'publish' ";
//				$group_clause = ' GROUP BY name ';

				if ( ( $group_by == self::GROUP_BY_EVENT ) || ( ! empty( $event_key_array ) ) ) {
					// When grouping by event or when getting specific events we need to join the post meta table
					// We need to join the meta table for the event key to group by event or get data for certain events
					$from .= " LEFT JOIN $meta_table AS event_meta ON p.ID = event_meta.post_id AND event_meta.meta_key = '$event_meta_key' ";
				} // endif

				// When grouping by volunteer role or fixer station we also need to join taxonomy tables
				if ( $group_by == self::GROUP_BY_VOLUNTEER_ROLE ) {
					$from .=
						" LEFT JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						" LEFT JOIN $term_tax_table AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id " .
						" LEFT JOIN $terms_table AS t ON t.term_id = tt.term_id ";
					// Limit results to those with either a role, or no fixer station or no taxonomy at all
					$where .=
						" AND (	( ( tt.taxonomy = '$role_taxonomy' ) AND ( tt.term_taxonomy_id IS NOT NULL ) ) OR " .
						"		( ( tt.taxonomy = '$station_taxonomy' ) AND ( tt.term_taxonomy_id IS NULL ) ) OR " .
						'		( ( tt.taxonomy IS NULL ) AND ( tt.term_taxonomy_id IS NULL ) ) ) ';
				} elseif ( $group_by == self::GROUP_BY_TOTAL_NON_FIXERS ) {
					// I need all records where the taxonomy is either a role or there is nothing specified (no station, no role)
					// The only way I can think to make this work is using an inner query
					$tt_ids = Fixer_Station::get_all_term_taxonomy_ids(); // Get everything excluding fixer stations
					$where .=
						' AND p.ID IN ( ' .
							"  SELECT p_inner.ID FROM $posts_table AS p_inner " .
							" LEFT JOIN $term_rels_table AS tr ON p_inner.ID = tr.object_id " .
							' WHERE tr.term_taxonomy_id NOT IN ( ' . implode( ',', $tt_ids ) . ' ) OR tr.term_taxonomy_id IS NULL ' .
						' ) ';
				} elseif ( $group_by == self::GROUP_BY_FIXER_STATION ) {
					$tt_ids = Fixer_Station::get_all_term_taxonomy_ids();
					$from .=
						// limit the results to only those with a fixer station taxonomy, and not NULL ( INNER JOIN does that )
						" INNER JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						'   AND tr.term_taxonomy_id in ( ' . implode( ',', $tt_ids ) . ' ) ' .
						" LEFT JOIN $term_tax_table AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id " .
						" LEFT JOIN $terms_table AS t ON t.term_id = tt.term_id ";
				} elseif ( $group_by == self::GROUP_BY_TOTAL_FIXERS ) {
					$tt_ids = Fixer_Station::get_all_term_taxonomy_ids();
					$from .=
						// limit the results to only those with a fixer station taxonomy, and not NULL ( INNER JOIN does that )
						" INNER JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						'   AND tr.term_taxonomy_id in ( ' . implode( ',', $tt_ids ) . ' ) ';
					// In this case we don't need term IDs or any other info, just the relationships
				} // endif
/*
				if ( ( $group_by == self::GROUP_BY_VOLUNTEER_ROLE ) || ( $group_by == self::GROUP_BY_FIXER_STATION )  ||
					 ( $group_by == self::GROUP_BY_TOTAL_NON_FIXERS ) || ( $group_by == self::GROUP_BY_TOTAL_FIXERS ) ) {
					$from .=
						" LEFT JOIN $term_rels_table AS tr ON p.ID = tr.object_id " .
						" LEFT JOIN $term_tax_table AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id " .
						" LEFT JOIN $terms_table AS t ON t.term_id = tr.term_id ";
					if ( $group_by == self::GROUP_BY_FIXER_STATION ) {
						// limit the results to only those with a fixer station taxonomy
						$where .= " AND ( tt.taxonomy = '$station_taxonomy' ) ";
					} elseif ( $group_by == self::GROUP_BY_VOLUNTEER_ROLE ) {
						// limit results to those with volunteer role or where there is no taxonomy at all
						$where .= " AND ( ( tt.taxonomy = '$role_taxonomy' ) OR ( tt.taxonomy IS NULL ) ) ";
					} // endif
				} // endif
*/
				// Add a count of apprentice fixers when grouping by Fixer station or Total
				if ( ( $group_by == self::GROUP_BY_FIXER_STATION ) ||
					 ( $group_by == self::GROUP_BY_TOTAL_FIXERS ) ||
					 ( $group_by == self::GROUP_BY_TOTAL ) ) {
					$select .= ", SUM( COALESCE( appr_meta.meta_value, 0 ) ) as apprentice_count ";
					$from .= " LEFT JOIN $meta_table AS appr_meta ON p.ID = appr_meta.post_id AND appr_meta.meta_key = '$apprentice_meta_key' ";
				} // endif

				if ( empty( $event_key_array ) ) {
					$query = "$select $from $where GROUP BY name";
					$data_array = $wpdb->get_results( $query, ARRAY_A );
				} else {
					$placeholder_array = array_fill( 0, count( $event_key_array ), '%s' );
					$placeholders = implode( ',', $placeholder_array );
					$where .= " AND event_meta.meta_value IN ( $placeholders )";
					$query = "$select $from $where GROUP BY name";
					$stmt = $wpdb->prepare( $query, $event_key_array );
					$data_array = $wpdb->get_results( $stmt, ARRAY_A );
				} // endif

//	Error_Log::var_dump( $group_by, $query, $event_key_array );
//	Error_Log::var_dump( $data_array );

				$this->internal_stats_array = array();
				if ( is_array( $data_array ) ) {
					foreach ( $data_array as $data ) {
						$name		= isset( $data[ 'name' ] )				? $data[ 'name' ]				: '';
						$head_count	= isset( $data[ 'head_count' ] )		? $data[ 'head_count' ]			: 0;
						$appr_count	= isset( $data[ 'apprentice_count' ] )	? $data[ 'apprentice_count' ]	: 0;
						$instance = Volunteer_Stats::create( $name, $head_count, $appr_count );
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
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the items and their related data.
	 */
	public function get_external_registered_stats_array() {
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

			$ext_data = apply_filters( 'reg_man_rc_get_volunteer_registration_stats', array(), $key_data_array, $group_by );
			// The above returns an array of data arrays.  I will convert those into my internal objects

			$has_unspecified = FALSE;
			$unspecified_head_count = 0; // We need a special count for roles we don't know
			$unspecified_appr_count = 0;
			if ( $group_by == self::GROUP_BY_VOLUNTEER_ROLE ) {
				$no_name = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID;
			} elseif ( $group_by == self::GROUP_BY_FIXER_STATION ) {
				$no_name = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
			} else {
				$name = '';
			} // endif

			foreach( $ext_data as $ext_data_row ) {
				$is_unspecified = FALSE; // assume this is a valid role
				$name		= isset( $ext_data_row[ 'name' ] )	? $ext_data_row[ 'name' ]	: $no_name;
				// If registrations are grouped by role or station then external systems may use other names
				// We need to use the internal names so try to find the right one
				if ( $group_by == self::GROUP_BY_VOLUNTEER_ROLE ) {
					$role = Volunteer_Role::get_volunteer_role_by_name( $name );
					if ( isset( $role ) ) {
						$name = $role->get_id(); // use the standard internal ID rather than the external name
					} else {
						// Otherwise it's an unknown role, often something like "Anywhere!" just group together
						$is_unspecified = TRUE;
					} // endif
				} elseif ( $group_by == self::GROUP_BY_FIXER_STATION ) {
					$station = Fixer_Station::get_fixer_station_by_name( $name );
					if ( isset( $station ) ) {
						$name = $station->get_id(); // use the standard internal ID rather than the external name
					} else {
						// Otherwise it's an unknown fixer station
						$is_unspecified = TRUE;
					} // endif
				} // endif
				$head_count	= isset( $ext_data_row[ 'head_count' ] )		? intval( $ext_data_row[ 'head_count' ] ) 		: 0;
				$appr_count	= isset( $ext_data_row[ 'apprentice_count' ] )	? intval( $ext_data_row[ 'apprentice_count' ] )	: 0;
				if ( $is_unspecified ) {
					$has_unspecified = TRUE;
					$unspecified_head_count += $head_count;
					$unspecified_appr_count += $appr_count;
				} else {
					// Two external names may be used for the same internal name so we need to check if we already have a count
					if ( ! isset( $this->external_stats_array[ $name ] ) ) {
						$instance = Volunteer_Stats::create( $name, $head_count, $appr_count );
						$this->external_stats_array[ $name ] = $instance;
					} else {
						// We already have stats for this name so we need to add in the new values
						$instance = $this->external_stats_array[ $name ];
						$instance->add_to_counts( $head_count, $appr_count );
					} // endif
				} // endif
			} // endfor

			if ( $has_unspecified ) {
				$name = $no_name;
				$instance = Volunteer_Stats::create( $name, $unspecified_head_count, $unspecified_appr_count );
				$this->external_stats_array[ $name ] = $instance;
			} // endif
		} // endif

		return $this->external_stats_array;
	} // function

} // class