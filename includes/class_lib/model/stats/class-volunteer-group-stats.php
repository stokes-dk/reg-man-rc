<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An instance of this class represents stats about how many volunteers of a particular group attended an event or events.
 * This class is used to represent fixer and non-fixer volunteers.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Group_Stats {

	private $group_name;
	private $head_count;
	private $apprentice_count;

	private function __construct() { }

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