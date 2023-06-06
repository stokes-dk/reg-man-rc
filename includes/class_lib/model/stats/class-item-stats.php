<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An instance of this class represents stats about how many items of a particular group have been fixed, not fixed and so on.
 * Statistics may be grouped by item description, item type, event or total.
 *
 * @since	v0.1.0
 *
 */
class Item_Stats {

	private $group_name;
	private $item_count;
	private $fixed_count;
	private $repairable_count;
	private $eol_count;
	private $repair_status_unknown_count;
	
	private $ci_calculator;

	private function __construct() { }

	/**
	 * Get the Item_Stats object for the total of all events specified
	 * @param string[] $event_keys_array
	 * @return Item_Stats
	 */
	public static function get_total_item_stats_for_event_keys_array( $event_keys_array ) {
		$group_by = Item_Stats_Collection::GROUP_BY_TOTAL;
		$stats_collection = Item_Stats_Collection::create_for_event_key_array( $event_keys_array, $group_by );
		$stats_array = array_values( $stats_collection->get_all_stats_array() );
		$result = isset( $stats_array[ 0 ] ) ? $stats_array[ 0 ] : self::create( '', 0, 0, 0, 0 ); // Defensive
		return $result;
	} // function

	/**
	 * Create an Item_Stats object
	 * @param string $group_name
	 * @param int $item_count
	 * @param int $fixed_count
	 * @param int $repairable_count
	 * @param int $end_of_life_count
	 * @return Item_Stats
	 */
	public static function create( $group_name, $item_count = 0, $fixed_count = 0, $repairable_count = 0, $end_of_life_count = 0 ) {
		$result = new self();
		$result->group_name = $group_name;
		$result->item_count = $item_count;
		$result->fixed_count = $fixed_count;
		$result->repairable_count = $repairable_count;
		$result->eol_count = $end_of_life_count;
		return $result;
	} // function

	/**
	 * Get the group name
	 * @return string
	 */
	public function get_group_name() {
		return $this->group_name;
	} // function

	/**
	 * Get the item count
	 * @return int
	 */
	public function get_item_count() {
		return $this->item_count;
	} // function

	/**
	 * Get the fixed count
	 * @return int
	 */
	public function get_fixed_count() {
		return $this->fixed_count;
	} // function

	/**
	 * Get the repairable count
	 * @return int
	 */
	public function get_repairable_count() {
		return $this->repairable_count;
	} // function

	/**
	 * Get the end-of-life count
	 * @return int
	 */
	public function get_end_of_life_count() {
		return $this->eol_count;
	} // function

	/**
	 * Get the count of items whose repair status was not reported
	 * @return int
	 */
	public function get_repair_status_unknown_count() {
		if ( ! isset( $this->repair_status_unknown_count ) ) {
			$fixed = $this->get_fixed_count();
			$repairable = $this->get_repairable_count();
			$eol = $this->get_end_of_life_count();
			$total = $this->get_item_count();
			$this->repair_status_unknown_count = $total - ( $fixed + $repairable + $eol );
		} // endif
		return $this->repair_status_unknown_count;
	} // function

	/**
	 * Add the specified values to the existing counts for this group
	 * @param int $item_count
	 * @param int $fixed_count
	 * @param int $repairable_count
	 * @param int $end_of_life_count
	 * @return void
	 */
	public function add_to_counts( $item_count, $fixed_count, $repairable_count, $end_of_life_count ) {
		$this->item_count += $item_count;
		$this->fixed_count += $fixed_count;
		$this->repairable_count += $repairable_count;
		$this->eol_count += $end_of_life_count;
		unset( $this->repair_status_unknown_count );
	} // function

	private function get_ci_calculator() {
		if ( ! isset( $this->ci_calculator ) ) {
			$fixed = $this->get_fixed_count();
			$repairable = $this->get_repairable_count();
			$eol = $this->get_end_of_life_count();
			$total = $this->get_item_count();
			$observed_occurrence_count = $fixed + $repairable;
			$sample_size = $observed_occurrence_count + $eol;
			$population_size = $total;
			$this->ci_calculator = Wilson_Confidence_Interval::create( $observed_occurrence_count, $sample_size, $population_size );
		} // endif
		return $this->ci_calculator;
	} // function

	public function get_sample_rate() {
		$ci = $this->get_ci_calculator();
		$result = $ci->get_sample_rate();
		return $result;
	} // function

	public function get_sample_percent_as_string() {
		$ci = $this->get_ci_calculator();
		$rate = $ci->get_sample_rate();
		/* Translators: %1$s is a proportion which will be shown as a percentage */
		$format = _x( '%1$s%%', 'Show a proportion as a percentage', 'reg-man-rc' );
		$result = sprintf( $format, round( ( $rate * 100 ) ) );
		return $result;
	} // function

	public function get_estimated_diversion_rate_as_percent_string() {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$ci = $this->get_ci_calculator();
		$sample = $ci->get_sample_rate();
		if ( $sample == 0 ) {
			// We have no sample on which to estimate a diversion rate
			$result = $em_dash;
		} else {
			$p_prime = $ci->get_adjusted_proportion();
			/* Translators: %1$s is a proportion which will be shown as a percentage */
			$format = _x( '%1$s%%', 'Show a proportion as a percentage', 'reg-man-rc' );
			$result = sprintf( $format, round( ( $p_prime * 100 ) ) );
		} // endif
		return $result;
	} // function

	public function get_estimated_diversion_count() {
		$ci = $this->get_ci_calculator();
		$result = $ci->get_estimated_occurrence_count();
		return $result;
	} // function

	public function get_estimated_diversion_count_as_string() {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$ci = $this->get_ci_calculator();
		$sample = $ci->get_sample_rate();
		if ( $sample == 0 ) {
			// We have no sample on which to estimate a diversion rate
			$result = $em_dash;
		} else {
			$result = number_format_i18n( $ci->get_estimated_occurrence_count() );
		} // endif
		return $result;
	} // function

	public function get_estimated_diversion_range_lower_count() {
		$ci = $this->get_ci_calculator();
		$proportion = $ci->get_lower_bound();
		$count = round( $this->get_item_count() * $proportion );
		return $count;
	} // function

	public function get_estimated_diversion_range_upper_count() {
		$ci = $this->get_ci_calculator();
		$proportion = $ci->get_upper_bound();
		$count = round( $this->get_item_count() * $proportion );
		return $count;
	} // function

	public function get_estimated_diversion_count_range_as_string() {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$ci = $this->get_ci_calculator();
		$sample = $ci->get_sample_rate();
		if ( $sample == 0 ) {
			// We have no sample on which to estimate a diversion rate
			$result = $em_dash;
		} else {
			$result = $ci->get_confidence_interval_as_count_string();
		} // endif
		return $result;
	} // function

} // class