<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class provides the details for a label on a map marker.
 *
 * @since v0.1.0
 *
 */
class Map_Marker_Label {
	public $text;
	public $className;
	// Other options if needed are: color, fontFamily, fontSize, and fontWeight

	private function __construct() {
	} // constructor

	/**
	 * Create a new marker label with the text and classes specified
	 * @param string $text
	 * @param string $classes
	 * @return \Reg_Man_RC\Model\Map_Marker_Label
	 */
	public static function create( $text, $classes = '' ) {
		$result = new self();
		$result->text = $text;
		$result->className = "reg-man-rc-map-marker-label $classes";
		return $result;
	} // function

} // class