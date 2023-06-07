<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class represents animation settings for a chart
 *
 * @since v0.1.0
 *
 */
class Chart_Animation implements \JsonSerializable {

	const EASING_STYLE_LINEAR	= 'linear';
	const EASING_STYLE_QUAD		= 'Quad';
	const EASING_STYLE_CUBIC	= 'Cubic';
	const EASING_STYLE_QUART	= 'Quart';
	const EASING_STYLE_QUINT	= 'Quint';
	const EASING_STYLE_SINE		= 'Sine';
	const EASING_STYLE_EXPO		= 'Expo';
	const EASING_STYLE_CIRC		= 'Circ';
	const EASING_STYLE_ELASTIC	= 'Elastic';
	const EASING_STYLE_BACK		= 'Back';
	const EASING_STYLE_BOUNCE	= 'Bounce';
	
	const EASING_DIRECTION_IN			= 'In';
	const EASING_DIRECTION_OUT			= 'Out';
	const EASING_DIRECTION_IN_AND_OUT	= 'InOut';
	
	private $duration = 1000;
	private $delay = 0;
	private $easing;
	private $easing_style		= self::EASING_STYLE_QUART;
	private $easing_direction 	= self::EASING_DIRECTION_OUT;
//	private $is_loop = FALSE;

	private function __construct() { }

	/**
	 * Create an instance of this class with the specified duration and delay
	 * @param int $duration		The number of milliseconds the animation lasts
	 * @param int $delay		The delay in milliseconds before the animation starts
	 * @return Chart_Animation
	 */
	public static function create( $duration = 1000, $delay = 0 ) {
		$result = new self();
		$result->duration = intval( $duration );
		$result->delay = intval( $delay );
		return $result;
	} // function

	private function get_duration() {
		return $this->duration;
	} // function

	/**
	 * Set the duration for this animation
	 * @param int $duration		The number of milliseconds the animation lasts
	 */
	public function set_duration( $duration ) {
		$this->duration = intval( $duration );
	} // function

	private function get_delay() {
		return $this->delay;
	} // function

	/**
	 * Set the dealy for this animation
	 * @param int $delay		The delay in milliseconds before the animation starts
	 */
	public function set_delay( $delay ) {
		$this->delay = $delay;
	} // function

	private function get_easing() {
		if ( ! isset( $this->easing ) ) {
			$style = $this->get_easing_style();

			if ( ! empty( $style ) ) {
				$direction = $this->get_easing_direction();
				$this->easing = "ease{$direction}{$style}";
//				Error_Log::var_dump( $this->easing );
			} // endif

		} // endif
		return $this->easing;
	} // function

	private function get_easing_style() {
		return $this->easing_style;
	} // function

	/**
	 * Set the easing style for this animation
	 * @param string $easing_style	Must be one of the EASING_STYLE_* constants defined in this class
	 */
	public function set_easing_style( $easing_style ) {
		$this->easing_style = $easing_style;
	} // function

	private function get_easing_direction() {
		return $this->easing_direction;
	} // function

	/**
	 * Set the easing direction for this animation
	 * @param string $easing_direction	Must be one of the EASING_DIRECTION_* constants defined in this class
	 */
	public function set_easing_direction( $easing_direction ) {
		$this->easing_direction = $easing_direction;
	} // function

	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string[][]	An associative array of chart animation attributes
	 * @since v0.1.0
	 */
	public function jsonSerialize() : array {

		$duration = $this->get_duration();
		$delay = $this->get_delay();
		$easing = $this->get_easing();

		$result = array(
				'duration'			=> $duration,
				'delay'				=> $delay,
		);
		if ( isset( $easing ) ) {
			$result[ 'easing' ] = $easing;
		} // endif

//		Error_Log::var_dump( $result );
		
		return $result;
	} // function
} // class