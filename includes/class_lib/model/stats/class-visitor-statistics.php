<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class represents stats about how many visitors attended events.
 *
 * @since	v0.1.0
 *
 */
class Visitor_Statistics {

	const GROUP_BY_EVENT		= 'event'; // group by event
	const GROUP_BY_TOTAL		= 'total'; // group all visitors together so we can count the totals

	private $event_key_array;
	private $group_by;

	private $event_count;

	private $total_stats_array;
	private $internal_stats_array;
	private $external_stats_array;
	private $registered_stats_array;
	private $supplemental_stats_array;

	private function __construct() { }

	/**
	 * Create an instance of this class providing stats for the events specified by the given event key array
	 * @param	string[]	$event_key_array	An array of event keys specifying the events whose stats are required
	 * @param	string			$group_by			A string specifying how the results should be grouped.
	 * The value must be one of the GROUP_BY_* constants defined in this class.
	 */
	public static function create_for_event_key_array( $event_key_array, $group_by ) {
		$result = new self();
		$result->event_key_array = $event_key_array;
		$result->group_by = $group_by;
		return $result;
	} // function

	private function get_event_key_array() {
		return $this->event_key_array;
	} // function

	private function get_group_by() {
		return $this->group_by;
	} // function

	public function get_event_count() {
		if ( ! isset( $this->event_count ) ) {
			$event_array = array_unique( $this->get_events_array() ); // remove duplicates if any
			$this->event_count = count( $event_array );
		} // endif
		return $this->event_count;
	} // function


	/**
	 * Get the stats for all visitors including registered and supplemental for the specified events and grouped in the specified way
	 * @return Visitor_Group_Stats[]	An array of instances of Visitor_Group_Stats describing the numbers of visitors.
	 */
	public function get_total_stats_array() {
		if ( ! isset( $this->total_stats_array ) ) {
			// Merge the registered and supplemental stats arrays
			$reg_stats_array = $this->get_registered_stats_array();
			$sup_stats_array = $this->get_supplemental_stats_array();

			$this->total_stats_array = self::merge_stats_arrays( $reg_stats_array, $sup_stats_array );
	// Error_Log::var_dump( $this->registered_stats_array, $this->supplemental_stats_array );
		} // endif
		return $this->total_stats_array;
	} // function

	/**
	 * Get the visitor stats based on all registered items for the specified events and grouped in the specified way
	 * @return Visitor_Group_Stats[]	An array of instances of Visitor_Group_Stats describing the numbers of visitors.
	 */
	public function get_registered_stats_array() {
		if ( ! isset( $this->registered_stats_array ) ) {

			// Merge the internal and external stats arrays
			$int_stats_array = $this->get_internal_stats_array();
			$ext_stats_array = $this->get_external_stats_array();

			$this->registered_stats_array = self::merge_stats_arrays( $int_stats_array, $ext_stats_array );
// Error_Log::var_dump( $this->internal_stats_array, $this->external_stats_array );
		} // endif

		return $this->registered_stats_array;
	} // function


	/**
	 * Get the supplemental stats for visitors not registered to the system
	 * @return Visitor_Group_Stats[]
	 */
	public function get_supplemental_stats_array() {
		if ( ! isset( $this->supplemental_stats_array ) ) {
			$event_key_array = $this->get_event_key_array();
			$group_by = $this->get_group_by();
			$this->supplemental_stats_array = Supplemental_Visitor_Registration::get_supplemental_group_stats_array( $event_key_array, $group_by );
		} // endif
		return $this->supplemental_stats_array;
	} // function

	/**
	 * Get the internal visitor stats (stats for visitors registered using this plugin)
	 *  for the specified events and grouped in the specified way.
	 * Note that the results represent Visitor Registrations rather than individual Visitors.
	 * If a single visitor registers items at two separate events then that counts as two Visitor Registrations.
	 * @return Visitor_Group_Stats[]	An array of instances of Visitor_Group_Stats describing the visitors and their related data.
	 */
	private function get_internal_stats_array() {
		if ( ! isset( $this->internal_stats_array ) ) {

			$event_key_array = $this->get_event_key_array();
			$group_by = $this->get_group_by();

			if ( is_array( $event_key_array) && ( count( $event_key_array ) == 0 ) ) {
				$this->internal_stats_array = array(); // The request is for an empty set of events so return an empty set
			} else {
				global $wpdb;

				// We need a subquery here because we're looking at items and counting visitors
				// We need to group items together by visitor id then count those rows

				$posts_table = $wpdb->posts;
				$meta_table = $wpdb->postmeta;
				$visitor_table = $wpdb->prefix . Visitor::VISITOR_TABLE_NAME;
				$event_meta_key = Item::EVENT_META_KEY;
				$visitor_meta_key = Item::VISITOR_META_KEY;
				$item_post_type = Item::POST_TYPE;

				switch ( $group_by ) {
					case self::GROUP_BY_EVENT:
						$name_col = 'event_meta.meta_value';
						break;
					case self::GROUP_BY_TOTAL:
					default:
						$name_col = "''";
						break;
				} // endswitch

				$subquery_select =
							"event_meta.meta_value as event_key, " .
							' CASE WHEN event_meta.meta_value = first_event_key THEN 1 ELSE 0 END AS is_first_time,' .
							' CASE WHEN visitor.email IS NOT NULL THEN 1 ELSE 0 END AS provided_email, ' .
							" CASE WHEN visitor.is_join_mail_list = 1 THEN 1 ELSE 0 END as join_mail_list ";
				$subquery_from =
						" $posts_table AS p " .
						" LEFT JOIN $meta_table AS visitor_meta ON p.ID = visitor_meta.post_id AND visitor_meta.meta_key = '$visitor_meta_key' " .
						" LEFT JOIN $meta_table AS event_meta ON p.ID = event_meta.post_id AND event_meta.meta_key = '$event_meta_key' " .
						" LEFT JOIN $visitor_table AS visitor ON visitor_meta.meta_value = visitor.id ";
				$subquery_where = " ( post_type = '$item_post_type' ) ";

				// Create the where clause for searching by event
				if ( ! empty( $event_key_array ) ) {
					$placeholder_array = array_fill( 0, count( $event_key_array ), '%s' );
					$placeholders = implode( ',', $placeholder_array );
					$subquery_where .= " AND ( event_meta.meta_value IN ( $placeholders ) ) ";
				} // endif

				$subquery = "SELECT $subquery_select FROM $subquery_from WHERE $subquery_where GROUP BY visitor.id, event_key";

				// Now create the main query using the subquer
				$select = "$name_col AS name, count(*) AS visitor_count, " .
							' SUM( is_first_time ) AS first_time_count, ' .
							' SUM( provided_email ) AS provided_email_count, ' .
							' SUM( join_mail_list ) AS join_mail_list_count ';

				$query = "SELECT $select FROM ( $subquery ) AS derived_table_1 GROUP BY name";
//		Error_Log::var_dump( $query );
				if ( empty( $event_key_array ) ) {
					$data_array = $wpdb->get_results( $query, ARRAY_A );
				} else {
					$stmt = $wpdb->prepare( $query, $event_key_array );
					$data_array = $wpdb->get_results( $stmt, ARRAY_A );
				} // endif

//				Error_Log::var_dump( $query, $event_key_array );
				$this->internal_stats_array = array();

				if ( is_array( $data_array ) ) {
					$unknown_count = 0; // assume all non-first-timers are returning
					foreach ( $data_array as $data ) {
						$name			= isset( $data[ 'name' ] )					? $data[ 'name' ]					: ''; //$em_dash;
						$visitor_count	= isset( $data[ 'visitor_count' ] )			? $data[ 'visitor_count' ] 			: 0;
						$first_count	= isset( $data[ 'first_time_count' ] )		? $data[ 'first_time_count' ] 		: 0;
//						$return_count	= isset( $data[ 'returning_count' ] )		? $data[ 'returning_count' ] 		: 0;
						$email_count	= isset( $data[ 'provided_email_count' ] )	? $data[ 'provided_email_count' ]	: 0;
						$join_count		= isset( $data[ 'join_mail_list_count' ] )	? $data[ 'join_mail_list_count' ]	: 0;
						$return_count = $visitor_count - $first_count;
						$instance = Visitor_Group_Stats::create( $name, $first_count, $return_count, $unknown_count, $email_count, $join_count );
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
			$group_by = $this->get_group_by();

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

			$ext_data = apply_filters( 'reg_man_rc_get_visitor_registration_stats', array(), $key_data_array, $group_by );
			// The above returns an array of data arrays.  I will convert those into my internal objects
			foreach( $ext_data as $ext_data_row ) {
				$name			= isset( $ext_data_row[ 'name' ] )					? $ext_data_row[ 'name' ]							: '';
				$first_count	= isset( $ext_data_row[ 'first_time_count' ] )		? intval( $ext_data_row[ 'first_time_count' ] )		: 0;
				$return_count	= isset( $ext_data_row[ 'returning_count' ] )		? intval( $ext_data_row[ 'returning_count' ] ) 		: 0;
				$unknown_count	= isset( $ext_data_row[ 'unknown_count' ] )			? intval( $ext_data_row[ 'unknown_count' ] )		: 0;
				$email_count	= isset( $ext_data_row[ 'provided_email_count' ] )	? intval( $ext_data_row[ 'provided_email_count' ] )	: 0;
				$join_count		= isset( $ext_data_row[ 'join_mail_list_count' ] )	? intval( $ext_data_row[ 'join_mail_list_count' ] )	: 0;
				$instance = Visitor_Group_Stats::create(
						$name, $first_count, $return_count, $unknown_count, $email_count, $join_count );
				$this->external_stats_array[ $name ] = $instance;
			} // endfor

		} // endif

		return $this->external_stats_array;
	} // function


	/**
	 * Merge two arrays of Visitor_Group_Stats objects
	 * @param	Visitor_Group_Stats[]	$array_1
	 * @param	Visitor_Group_Stats[]	$array_2
	 * @return	Visitor_Group_Stats[]	An array with with the two inputs merged and their values summed for overlapping keys
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
				$result[ $name ] = Visitor_Group_Stats::create(
						$name,
						$stats_1->get_first_time_count()			+ $stats_2->get_first_time_count(),
						$stats_1->get_returning_count()				+ $stats_2->get_returning_count(),
						$stats_1->get_return_status_unknown_count()	+ $stats_2->get_return_status_unknown_count(),
						$stats_1->get_provided_email_count()		+ $stats_2->get_provided_email_count(),
						$stats_1->get_join_mail_list_count()		+ $stats_2->get_join_mail_list_count()
						);
			} // endif
		} // endfor
		return $result;
	} // function


} // class