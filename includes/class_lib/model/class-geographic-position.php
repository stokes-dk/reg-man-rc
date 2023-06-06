<?php
namespace Reg_Man_RC\Model;

class Geographic_Position implements \JsonSerializable {

	private $latitude;
	private $longitude;

	private function __construct( $lat, $lng ) {
		$this->latitude = $lat;
		$this->longitude = $lng;
	} // function

	/**
	 * Create an instance of this class with the specified latitude and longitude
	 * @param	string	$latitude	The latitude of the form 43.692139
	 * @param	string	$longitude	The longitude of the form -79.329711
	 * @return	self	An instance of this class with the specified latitude and longitude
	 * @since v0.1.0
	 */
	public static function create( $latitude, $longitude ) {
		$result = new self( $latitude, $longitude );
		return $result;
	} // function

	/**
	 * Create an instance of this class using an iCalendar format string, e.g. '43.692139;-79.329711'
	 * @param	string	$iCalendar_string	The string representing the geographic position
	 * @return	self|NULL	An instance of this class with data from the specified string or NULL if the string is not valid
	 * @since v0.1.0
	 */
	public static function create_from_iCalendar_string( $iCalendar_string ) {
		$parts = explode( ';', $iCalendar_string );
		if ( count( $parts ) == 2 ) {
			$result = new self( $parts[ 0 ], $parts[ 1 ] );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Create an instance of this class using a map marker position JSON encoded as a string,
	 *   e.g. '{ lat: 43.692139, lng: -79.329711 }'
	 * @param	string	$marker_position_string	The string representing the map marker position
	 * @return	self|NULL	An instance of this class with data from the specified string or NULL if the string is not valid
	 * @since v0.1.0
	 */
	public static function create_from_google_map_marker_position_string( $marker_position_string ) {
		$pos = json_decode( $marker_position_string );
		if ( isset( $pos->lat ) && isset( $pos->lng ) ) {
			$result = new self( $pos->lat, $pos->lng );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Get the latitude for this geographich position
	 * @return float
	 */
	public function get_latitude() {
		return floatval( $this->latitude );
	} // function

	/**
	 * Get the longitude for this geographich position
	 * @return float
	 */
	public function get_longitude() {
		return floatval( $this->longitude );
	} // function

	/**
	 * Get a boolean indicating whether this object is the same position as another instance of the same class.
	 *
	 * @param	Geographic_Position	$geo	An instance of this class to be compared with this object.
	 * @param	int					$rounding_precision	Specifies the number of decimal points to be used when rounding
	 *  the latitude and longitude of the two geographical positions to be compared.
	 * One degree of latitude or longitude is about 111 kilometers.
	 * A rounding precision of 1 means the two positions are equal if they are within about 11 kilometers of each other.
	 *  A rounding precision of 2 is about 1 km, 3 is about 100 meters and 4 is 10 meters.
	 *  Four is the default.
	 * @return	boolean	TRUE if the specified object has the same latitude and longitude as this object
	 */
	public function get_is_equal_to( $geo, $rounding_precision = Settings::LOCATION_GROUP_COMPARE_PRECISION_DEFAULT ) {
		if ( $geo instanceof Geographic_Position ) {
			// Using round() causes unexpected results.
			// 43.6605554 rounds to 43.6606 whereas 43.6605437 rounds to 43.6605 so they are not equal despite
			//  being within just a few meters of each other.
			// What I really want is not round() but a way of truncating the floats after 4 decimal places which PHP does not provide.
			// Instead I will subtract the two values then move the decimal point 4 places and compare against 1.
			$lat_diff = abs( $this->get_latitude() - $geo->get_latitude() );
			$long_diff = abs( $this->get_longitude() - $geo->get_longitude() );
			$multiplier = pow( 10, $rounding_precision );
			$result = ( ( ( $lat_diff * $multiplier ) <= 1 ) && ( ( $long_diff * $multiplier ) <= 1 ) );
		} else {
			$result = FALSE;
		} // endif
		return $result;
	} // function

	/**
	 * Get a string version of this object suitable for use in an iCalendar
	 * @return string	A string version of this object of the form latitude;longitude, e.g. '43.692139;-79.329711'
	 * @since v0.1.0
	 */
	public function get_as_iCalendar_string() {
		return $this->get_as_string(); // The default string version is the iCalendar format
	} // function

	/**
	 * Get a version of this object suitable for use as position data for a Google map marker.
	 * @return array|object	An object or array of the form: { lat: 43.692139, lng: -79.329711 }
	 * @since v0.1.0
	 */
	public function get_as_google_map_marker_position() {
		return array(
			'lat' => floatval( $this->latitude ),
			'lng' => floatval( $this->longitude )
		);
	} // function

	/**
	 * Get this object as a Google map marker position string
	 * @return string	A json encoded object or array of the form: { lat: 43.692139, lng: -79.329711 }
	 * @since v0.1.0
	 */
	public function get_as_google_map_marker_position_string() {
		// Use strval() to convert the float to a string so that json_encode does not create 14-digit floats
		$obj = array(
			'lat' => strval( $this->latitude ),
			'lng' => strval( $this->longitude )
		);
		$result = json_encode( $obj );
		return $result;
	} // function

	/**
	 * Get an this geographic position as a string
	 * @return string	A string version of this object of the form latitude;longitude, e.g. '43.692139;-79.329711'
	 * @since v0.1.0
	 */
	public function get_as_string() {
		return $this->latitude . ';' . $this->longitude;
	} // function

	/**
	 * Get an object which can be serialized using json_encode()
	 * @return string	A string version of this object
	 * @since v0.1.0
	 */
	public function jsonSerialize() : string {
		return $this->get_as_string();
	} // function

} // class