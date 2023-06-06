<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An instance of this class represents stats about how many volunteers of a particular group attended an event or events.
 * This class is used to represent fixer and non-fixer volunteers.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Stats {

	private $group_name;
	private $head_count;
	private $apprentice_count;

	private function __construct() { }

	/**
	 * Get the Volunteer_Stats object for the total of all fixers for the events specified
	 * @param string[] $event_keys_array
	 * @return Volunteer_Stats
	 */
	public static function get_total_fixer_stats_for_event_keys_array( $event_keys_array ) {
		$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL_FIXERS;
		$stats_collection = Volunteer_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );
		$stats_array = array_values( $stats_collection->get_all_stats_array() );
		$result = isset( $stats_array[ 0 ] ) ? $stats_array[ 0 ] : self::create( '', 0, 0 ); // Defensive
		return $result;
	} // function

	/**
	 * Get the Volunteer_Stats object for the total of all fixers for the events specified
	 * @param string[] $event_keys_array
	 * @return Volunteer_Stats
	 */
	public static function get_total_non_fixer_stats_for_event_keys_array( $event_keys_array ) {
		$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL_NON_FIXERS;
		$stats_collection = Volunteer_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );
		$stats_array = array_values( $stats_collection->get_all_stats_array() );
		$result = isset( $stats_array[ 0 ] ) ? $stats_array[ 0 ] : self::create( '', 0, 0 ); // Defensive
		return $result;
	} // function

	public static function create( $group_name, $head_count = 0, $apprentice_count = 0 ) {
		$result = new self();
		$result->group_name = $group_name;
		$result->head_count = $head_count;
		$result->apprentice_count = $apprentice_count;
		return $result;
	} // function

	public function get_group_name() {
		return $this->group_name;
	} // function

	public function get_head_count() {
		return $this->head_count;
	} // function

	public function get_apprentice_count() {
		return $this->apprentice_count;
	} // function

	public function add_to_counts( $head_count, $apprentice_count = 0 ) {
		$this->head_count += $head_count;
		$this->apprentice_count += $apprentice_count;
	} // function

} // class