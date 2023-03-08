<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Settings;

/**
 * An instance of this class calculates a Wilson Confidence Interval based on surveyed data
 *
 * @since v0.1.0
 *
 */
class Wilson_Confidence_Interval {

	private $observed_occurrence_count;
	private $sample_size;
	private $population_size;
	private $confidence_level;

	private $p_hat; // The proportion of observed occurrence
	private $n_prime; // The sample size or adjusted sample size based on a finite population
	private $z_value; // A constant computed based on the confidence level

	private $adjusted_proportion;
	private $corrected_standard_deviation;

	private $lower_bound;
	private $upper_bound;

	const  CONFIDENCE_90 = 90;
	const  CONFIDENCE_95 = 95;
	const  CONFIDENCE_99 = 99;

	private static $Z_VALUES = array(
			90 => 1.645,	// 90% confidence
			95 => 1.96,		// 95% confidence
			99 => 2.576,	// 99% confidence
	);

	private function __construct() { }

	public static function create( $observed_occurrence_count, $sample_size, $population_size ) {
		$result = new self();
		$result->observed_occurrence_count = $observed_occurrence_count;
		$result->sample_size = $sample_size;
		$result->population_size = $population_size;
		$result->confidence_level = Settings::get_confidence_level_for_interval_estimate();
		return $result;
	} // function

	private function get_p_hat() {
		if ( ! isset( $this->p_hat ) ) {
			$this->p_hat = ( $this->sample_size !== 0 ) ? $this->observed_occurrence_count / $this->sample_size : 0;
		} // endif
		return $this->p_hat;
	} // function

	private function get_n_prime() {
		if ( ! isset( $this->n_prime ) ) {
			$n = $this->sample_size;
			$N = $this->population_size;
			if ( ( $n !== 0 ) && ( $N > 1 ) ) {
				$v = sqrt( ( $N - $n ) / ( $N - 1) );
				$this->n_prime = ( $v > 0 ) ? $n / pow( $v, 2 ) : $n;
			} else {
				$this->n_prime = $n;
			} // endif
		} // endif
		return $this->n_prime;
	} // function

	private function get_z_value() {
		if ( ! isset( $this->z_value ) ) {
			$this->z_value = self::$Z_VALUES[ $this->confidence_level ];
		} // endif
		return $this->z_value;
	} // function

	public function get_adjusted_proportion() {
		if ( ! isset( $this->adjusted_proportion ) ) {
			$p_hat = $this->get_p_hat();
			$z_val = $this->get_z_value();
			$z_squared = pow( $z_val, 2 );
			$n = $this->get_n_prime();
			if ( intval( $n ) !== 0 ) {
				$this->adjusted_proportion = ( $p_hat + ( $z_squared / ( 2 * $n ) ) ) / ( 1 + ( $z_squared / $n ) );
			} else {
				$this->adjusted_proportion = 0;
			} // endif
		} // endif
		return $this->adjusted_proportion;
	} // function

	private function get_corrected_standard_deviation() {
		if ( ! isset( $this->corrected_standard_deviation ) ) {
			$n = $this->get_n_prime();
			if ( intval( $n ) !== 0 ) {
				$p_hat = $this->get_p_hat();
				$z_val = $this->get_z_value();
				$z_squared = pow( $z_val, 2 );
				$n_squared = pow( $n, 2 );
				$numerator = sqrt( ( ( $p_hat * ( 1 - $p_hat) ) / $n ) + ( $z_squared / ( 4 * $n_squared ) ) );
				$denomonator = 1 + ( $z_squared / $n );
				$this->corrected_standard_deviation = $numerator / $denomonator;
			} else {
				$this->corrected_standard_deviation = 0;
			} // endif
		} // endif
		return $this->corrected_standard_deviation;
	} // function

	public function get_lower_bound() {
		if ( ! isset( $this->lower_bound ) ) {
			// First make sure that we haven't sampled 100%, if so then we already know exactly how many were successful
			if ( $this->sample_size < $this->population_size ) {
				$p_prime = $this->get_adjusted_proportion();
				$s_prime = $this->get_corrected_standard_deviation();
				$z_val = $this->get_z_value();
				// I use abs() below only because it is possible to get a very small negative number
				$this->lower_bound = abs( $p_prime - ( $z_val * $s_prime ) );
			} else {
				if ( $this->sample_size > 0 ) {
					$this->lower_bound = $this->observed_occurrence_count / $this->sample_size;
				} else {
					$this->lower_bound = 0;
				} // endif
			} // endif
		} // endif
		return $this->lower_bound;
	} // function

	public function get_upper_bound() {
		if ( ! isset( $this->upper_bound ) ) {
			// First make sure that we haven't sampled 100%, if so then we already know exactly how many were successful
			if ( $this->sample_size < $this->population_size ) {
				$p_prime = $this->get_adjusted_proportion();
				$s_prime = $this->get_corrected_standard_deviation();
				$z_val = $this->get_z_value();
				$this->upper_bound = $p_prime + ( $z_val * $s_prime );
			} else {
				if ( $this->sample_size > 0 ) {
					$this->upper_bound = $this->observed_occurrence_count / $this->sample_size;
				} else {
					$this->upper_bound = 0;
				} // endif
			} // endif
		} // endif
		return $this->upper_bound;
	} // function

	public function get_sample_rate() {
		$result = ( $this->sample_size !== 0 ) ? $this->sample_size / $this->population_size : 0;
		return $result;
	} // function

	public function get_estimated_occurrence_count() {
		$p_prime = $this->get_adjusted_proportion();
		$est_count = round( $p_prime * $this->population_size );
		return $est_count;
	} // function

	public function get_confidence_interval_as_count_string() {
		$lower_bound = $this->get_lower_bound();
		$upper_bound = $this->get_upper_bound();
		$population_count = $this->population_size;
		// The lower count for the range should not be less than the actual observed occurrences
		$lower_count = max( round( $lower_bound * $population_count ), $this->observed_occurrence_count );
		$upper_count = round( $upper_bound * $population_count );
		$lower_text = number_format_i18n( $lower_count );
		$upper_text = number_format_i18n( $upper_count );
		if ( $lower_bound == $upper_bound ) {
			$result = $upper_text;
		} else {
			/* Translators: %1$s is the lower bound for a range, %2$s is the upper bound */
			$format = _x( '%1$s–%2$s', 'Show a range of values like 8–10', 'reg-man-rc' );
			$result = sprintf( $format, $lower_text, $upper_text );
		} // endif
		return $result;
	} // function

} // class