<?php
namespace Reg_Man_RC\Model;


/**
 * An instance of this interface provides the details required to mark something on a map, including title, position and so on.
 *
 * @since v0.1.0
 *
 */
interface Map_Marker {

	/**
	 * Get the marker title as a string, e.g. "Toronto Reference Library".
	 * This string is shown as rollover text for the marker, similar to an element's title attribute.
	 * Its main purpose is for accessibility, e.g. screen readers.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_title( $map_type );

	/**
	 * Get the marker label as a string.  May return NULL if no label is required.
	 * May return a string with the label text or an instance of Map_Marker_Label if settings like class are needed.
	 * This label, if provided, is displayed as text next to the marker on the map.
	 * It can be used to indicate some special condition or information about the marker, e.g. "Event Cancelled"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|Map_Marker_Label
	 * @since v0.1.0
	 */
	public function get_map_marker_label( $map_type );

	/**
	 * Get the marker location as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL	The marker location if it is known, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_location( $map_type );

	/**
	 * Get the marker ID as a string.  This should be unique for a map that may contain multiple markers.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type );

	/**
	 * Get the marker position as an instance of Geographic_Position.
	 * @return	Geographic_Position	The geographic position of the map marker
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @since v0.1.0
	 */
	public function get_map_marker_geographic_position( $map_type );

	/**
	 * Get the map zoom level to use when this marker is shown on a map by itself.
	 *
	 * This will determine the zoom setting for the map when no other markers are present.
	 * 0 is the entire world, 22 is the maximum zoom.
	 * If NULL is returned then some default zoom level will be used.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	int|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_zoom_level( $map_type );

	/**
	 * Get the colour used for the map marker or NULL if the default colour should be used.
	 * The result can be any string that is a valid colour in CSS.
	 * For example, '#f00', 'red', 'rgba( 255, 0, 0, 0.5)'.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_colour( $map_type );

	/**
	 * Get the opacity used for the map marker or NULL if the default opacity of 1 should be used.
	 * The result must be a number between 0 and 1, zero being completely transparent, 1 being completely opaque.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|float|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_opacity( $map_type );

	/**
	 * Get the content shown in the info window for the marker including any necessary html markup or NULL if no info is needed.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_info( $map_type );
} // interface