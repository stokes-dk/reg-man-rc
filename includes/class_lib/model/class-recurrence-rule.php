<?php
namespace Reg_Man_RC\Model;

use Recurr\Rule;
use Recurr\DateExclusion;
use Recurr\DateInclusion;

class Recurrence_Rule {

	const YEARLY	= 'YEARLY';
	const MONTHLY	= 'MONTHLY';
	const WEEKLY	= 'WEEKLY';
	const DAILY		= 'DAILY';
	const HOURLY	= 'HOURLY';
	const MINUTELY	= 'MINUTELY';
	const SECONDLY	= 'SECONDLY';

	private $rule;

	private function __construct( ) {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public static function create_from_string( $rrule_string ) {
		// FIXME - Do I need TRY CATCH here???  Rule throws exceptions
		$result = new self();
		$result->rule = new Rule( $rrule_string );
		return $result;
	} // endif

	/**
	 * Get the underlying Recurr Rule object
	 *
	 * @return	\Recurr\Rule
	 * @since	v0.1.0
	 */
	private function get_rule() {
		if ( ! isset( $this->rule ) ) {
			$this->rule = new Rule();
		} // endif
		return $this->rule;
	} // endif

	/**
	 * Set the start date and time of the first event in the recurrence set.
	 *
	 * @param	\DateTimeInterface	$start_date_time
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_start_date_time( $start_date_time ) {
		$this->get_rule()->setStartDate( $start_date_time );
	} // function

	private function get_start_date_time() {
		return $this->get_rule()->getStartDate();
	} // function

	/**
	 * Set the end date and time of the first event in the recurrence set.
	 *
	 * @param	\DateTimeInterface	$end_date_time
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_end_date_time( $end_date_time ) {
		$this->get_rule()->setEndDate( $end_date_time );
	} // function

	private function get_end_date_time() {
		return $this->get_rule()->getEndDate();
	} // function

	private function get_event_duration_interval( ) {
		$start = $this->get_start_date_time();
		$end = $this->get_end_date_time();
		if ( ( $start instanceof \DateTimeInterface ) && ( $end instanceof \DateTimeInterface ) ) {
			$result = $start->diff( $end );
		} else {
			$now = new \DateTime( 'now', wp_timezone() );
			$result = $now->diff( $now ); // we don't have one of the dates so just don't fail
		} // endif
		return $result;
	} // function

	public function get_frequency( ) {
		$result = $this->get_rule()->getFreqAsText();
		return $result;
	} // function

	public function get_frequency_as_translated_text() {
		switch ( $this->get_frequency() ) {
			case self::YEARLY:
				return __( 'Yearly', 'reg=man-rc' );
			case self::MONTHLY:
				return __( 'Monthly', 'reg=man-rc' );
			case self::WEEKLY:
				return __( 'Weekly', 'reg=man-rc' );
			case self::DAILY:
				return __( 'Daily', 'reg=man-rc' );
			case self::HOURLY:
				return __( 'Hourly', 'reg=man-rc' );
			case self::MINUTELY:
				return __( 'Minutely', 'reg=man-rc' );
			case self::SECONDLY:
				return __( 'Secondly', 'reg=man-rc' );
			default:
				return '';
		} // endswitch
	} // function

	public function set_frequency( $frequency ) {
		try {
			$this->get_rule()->setFreq( Rule::$freqs[$frequency] );
		} catch ( \Exception $exc ) {
			Error_Log::log_exception( $exc->getMessage(), $exc );
		} // endtry
	} // function

	/**
	 * Set the days of the week the recurring event should fall on.
	 * @param string	$week_start_day	The day to set as the first day of the week.
	 * Use the following shortforms for the days:
	 * SU => Sunday, MO => Monday, TU => Tuesday, WE => Wednesday, TH => Thursday, FR => Friday, SA => Saturday
	 */
	public function set_week_start( $week_start_day ) {
		try {
			$this->get_rule()->setWeekStart( $week_start_day );
		} catch ( \Exception $exc ) {
			Error_Log::log_exception( $exc->getMessage(), $exc );
		} // endtry
	} // function

	public function set_until( $until ) {
		$this->get_rule()->setUntil( $until );
	} // function

	public function set_count( $count ) {
		$this->get_rule()->setCount( $count );
	} // function

	public function set_interval( $interval ) {
		try {
			$this->get_rule()->setInterval( $interval );
		} catch ( \Exception $exc ) {
			Error_Log::log_exception( $exc->getMessage(), $exc );
		} // endtry
	} // function

	public function set_by_second( $second_array ) {
		$this->get_rule()->setBySecond( $second_array );
	} // function

	/**
	 * Set the days of the week the recurring event should fall on.
	 * @param string[]	$by_day_array	An array of strings representing some subset of the days of the week.
	 * Use the following shortforms for the days:
	 * SU => Sunday, MO => Monday, TU => Tuesday, WE => Wednesday, TH => Thursday, FR => Friday, SA => Saturday
	 */
	public function set_by_day( $by_day_array ) {
		try {
			$this->get_rule()->setByDay( $by_day_array );
		} catch ( \Exception $exc ) {
			Error_Log::log_exception( $exc->getMessage(), $exc );
		} // endtry
	} // function

	/**
	 * Set the months of the year the recurring event should fall on.
	 * @param string[]	$by_month_array	An array of integers between 1 and 12 inclusive
	 *  representing some subset of the months of the year in which the recurring event should occur.
	 */
	public function set_by_month( $by_month_array ) {
		try {
			$this->get_rule()->setByMonth( $by_month_array );
		} catch ( \Exception $exc ) {
			Error_Log::log_exception( $exc->getMessage(), $exc );
		} // endtry
	} // function

	/**
	 * Set the array of positions within a group of possible options.
	 * This must be used in conjunction with another set_by*() method.
	 *
	 * For example "the first and last Friday of every month" could be represented as:
	 *   RRULE:FREQ=MONTHLY;BYDAY=FR;BYSETPOS=1,-1
	 * In this case call set_by_position( array( 1, -1 ) ) and set_by_day( array ( 'FR' ) );
	 * @param array	$by_set_position_array	An array of strings representing some subset of the days of the week.
	 * Use the following shortforms for the days:
	 * SU => Sunday, MO => Monday, TU => Tuesday, WE => Wednesday, TH => Thursday, FR => Friday, SA => Saturday
	 */
	public function set_by_position( $by_set_position_array ) {
		$this->get_rule()->setBySetPosition( $by_set_position_array );
	} // function

	public function set_exclude_dates( $exclude_dates_array ) {
		if ( is_array( $exclude_dates_array ) ) {
			$date_exclusion_array = array();
			foreach ( $exclude_dates_array as $exclude_date ) {
				$date_exclusion_array[] = new DateExclusion( $exclude_date );
			} // endfor
			$this->get_rule()->setExDates( $date_exclusion_array );
		} // endif
	} // function

	public function set_include_dates( $include_dates_array ) {
		if ( is_array( $include_dates_array ) ) {
			$date_inclusion_array = array();
			foreach ( $include_dates_array as $include_date ) {
				$date_inclusion_array[] = new DateInclusion( $include_date );
			} // endfor
			$this->get_rule()->setRDates( $date_inclusion_array );
		} // endif
	} // funciton

	public function get_as_string() {
		// Because of daylight saving the recurrence rule cannot be in UTC.
		// UTC has no daylight saving so if an event starts in Jan at 12:00 EST the starting time in UTC
		// would be 17:00 but by April it will be 16:00.
		$rule = $this->get_rule();
		$rule_str = $rule->getString();
		// There is a problem with rrule in that it inserts DTEND into the string.
		// That is not valid and I will have to remove it.
		$parts = explode( ';', $rule_str );
		$new_parts = array();
		foreach ( $parts as $rule_part ) {
			if ( substr( $rule_part, 0, strlen( 'DTEND=' ) ) !== 'DTEND=' ) {
				$new_parts[] = $rule_part;
			} // endif
		} // endfor
		return implode( ';', $new_parts );
	} // function

	public function __toString() {
		return $this->get_as_string();
	} // function

	/**
	 * Get the recurring event dates for the event.
	 *
	 * @return	\DateTimeInterface[][]	Pairs of event start and end dates and times, e.g.
	 * ```
	 * 	array(
	 * 		array(
	 * 			'start'	=> \DateTimeInterface object with the event start date and time
	 * 			'end'	=> \DateTimeInterface object with the event end date and time
	 * 		)
	 * 	);
	 * ```
	 */
	public function get_recurring_event_dates( $range_min_date_time = NULL, $range_max_date_time = NULL ) {
		$rule = $this->get_rule();
		$duration = $this->get_event_duration_interval();
		$transformer = new \Recurr\Transformer\ArrayTransformer();
		$collection = $transformer->transform( $rule );
		$result = array();
		foreach ( $collection as $recurrence ) {
			$start = $recurrence->getStart();
			$end = $recurrence->getEnd();
			// There is a problem in Recurr with RDates in that Recurrence instances added by RDate rules
			//  have the same start and end date / time.
			// There is a fix for this but it's not in any build yet.
			// I will need to search for any of those recurrences and modify the end date/time
			if ( ( $start instanceof \DateTimeInterface ) && ( $end instanceof \DateTimeInterface ) ) {
				if ( $start == $end ) {
					$end->add( $duration );
				} // endif
			} // endif
			if ( isset( $range_min_date_time ) || isset( $range_max_date_time ) ) {
				// Only return dates that fall within the specified range.  Note that an event may span a range border.
				// This means the event must fall between the min and max times.  An event may span one of the borders
				// So the event end must be >= min date and time (the event won't have ended before min)
				//  and the event start must be <= the max date and time (it must start before max)
				$ok_min = ! empty( $range_min_date_time ) ? ( $end >= $range_min_date_time ) : TRUE;
				$ok_max = ! empty( $range_max_date_time ) ? ( $start <= $range_max_date_time ) : TRUE;
				if ( ! $ok_min || ! $ok_max ) {
					continue;
				} // endif
			} // endif
			$result[] = array( 'start' => $start, 'end' => $end );
		} // endfor
		// Sort the dates to make sure they're in order
		usort( $result, function( $date1, $date2 ) {
			return $date1['start'] <=> $date2['start'];
		});
		return $result;
	} // function

} // class