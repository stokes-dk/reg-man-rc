<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Map_Marker;

/**
 * An instance of this class represents a map for an object view.
 *
 */
class Map_Section implements Object_View_Section {

	private $map_marker_array = array();

	// Callers should use one of the static factory methods
	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @param	Map_Marker	$map_marker	An optional map marker to be shown on the map
	 * @return	Map_Section
	 */
	public static function create( $map_marker = NULL ) {
		$result = new self();
		if ( isset( $map_marker ) ) {
			$result->add_map_marker( $map_marker );
		} // endif
		return $result;
	} // function

	/**
	 * Get the map marker array
	 * @return Map_Marker[]
	 */
	private function get_map_marker_array() {
		return $this->map_marker_array;
	} // function

	/**
	 * Add a marker to the map
	 * @param Map_Marker	$map_marker	The marker to be added
	 */
	public function add_map_marker( $map_marker ) {
		$this->map_marker_array[] = $map_marker;
	} // function

	/**
	 * Render this section
	 */
	public function render_section() {
		if ( Map_View::get_is_map_view_enabled() ) {
			$map_marker_array = $this->get_map_marker_array();
			if ( ! empty( $map_marker_array ) ) {
				echo '<div class="reg-man-rc-object-view-section reg-man-rc-object-view-map-container">';
					$map_view = Map_View::create_for_object_page();
					$map_view->set_map_markers( $map_marker_array );
					$map_view->render();
				echo '</div>';
			} // endif
		} // endif
	} // function

} // class