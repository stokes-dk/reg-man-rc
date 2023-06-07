<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Map_View;

/**
 * An instance of this class represents a duration that can be shown on a calendar,
 *  for example a month as a grid of days.
 *
 * @since	v0.1.0
 *
 */
class Calendar_Duration {

	const ONE_MONTH			= 'duration_1_month';
	const TWO_MONTHS		= 'duration_2_months';
	const THREE_MONTHS		= 'duration_3_months';
	const FOUR_MONTHS		= 'duration_4_months';
	const SIX_MONTHS		= 'duration_6_months';
	const TWELVE_MONTHS		= 'duration_12_months';
	const ONE_YEAR			= 'duration_1_year';

	private static $ALL_DURATIONS_ARRAY;

	private $id;
	private $name;
	private $desc;

	private function __construct() { }

	public static function get_all_calendar_durations() {
		if ( !isset( self::$ALL_DURATIONS_ARRAY ) ) {

			$one_month = new self();
			$one_month->id			= self::ONE_MONTH;
			$one_month->name		= __( '1 month', 'reg-man-rc' );
			$one_month->desc		= __( 'One calendar month.', 'reg-man-rc' );

			$two_months = new self();
			$two_months->id			= self::TWO_MONTHS;
			$two_months->name		= __( '2 months', 'reg-man-rc' );
			$two_months->desc		= __( 'Two calendar months.', 'reg-man-rc' );
			
			$three_months = new self();
			$three_months->id			= self::THREE_MONTHS;
			$three_months->name		= __( '3 months', 'reg-man-rc' );
			$three_months->desc		= __( 'Three calendar months.', 'reg-man-rc' );
			
			$four_months = new self();
			$four_months->id			= self::FOUR_MONTHS;
			$four_months->name		= __( '4 months', 'reg-man-rc' );
			$four_months->desc		= __( 'Four calendar months.', 'reg-man-rc' );
			
			$six_months = new self();
			$six_months->id			= self::SIX_MONTHS;
			$six_months->name		= __( '6 months', 'reg-man-rc' );
			$six_months->desc		= __( 'Six calendar months.', 'reg-man-rc' );
			
			$twelve_months = new self();
			$twelve_months->id		= self::TWELVE_MONTHS;
			$twelve_months->name	= __( '12 months', 'reg-man-rc' );
			$twelve_months->desc	= __( 'Twelve calendar months (may span two years).', 'reg-man-rc' );
			
			$one_year = new self();
			$one_year->id		= self::ONE_YEAR;
			$one_year->name	= __( 'Calendar year', 'reg-man-rc' );
			$one_year->desc	= __( 'One calendar year.', 'reg-man-rc' );
			
			self::$ALL_DURATIONS_ARRAY = array(
					self::ONE_MONTH		=> $one_month,
					self::TWO_MONTHS	=> $two_months,
					self::THREE_MONTHS	=> $three_months,
					self::FOUR_MONTHS	=> $four_months,
					self::SIX_MONTHS	=> $six_months,
					self::TWELVE_MONTHS	=> $twelve_months,
					self::ONE_YEAR		=> $one_year,
			);

		} // endif
		return self::$ALL_DURATIONS_ARRAY;
	} // function

	/**
	 * Get the duration with the specified ID which must be one of the constants defined in this class.
	 *
	 * @param	int|string			$duration_id		The ID of the duration to be returned.
	 * The ID must be one of the constants defined in this class.
	 * If the specified ID is unrecognized, NULL will be returned.
	 * @return	Calendar_Duration|NULL	An instance of this class with the specified ID, or NULL if the ID is not recognized.
	 *
	 * @since v0.1.0
	 */
	public static function get_duration_by_id( $duration_id ) {
		$all = self::get_all_calendar_durations();
		$result = isset( $all[ $duration_id ] ) ? $all[ $duration_id ] : NULL;
		return $result;
	} // function

	/**
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this object
	 *
	 * @return	string	The description of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->desc;
	} // function

} // class
