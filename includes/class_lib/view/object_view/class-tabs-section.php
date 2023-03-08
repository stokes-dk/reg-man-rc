<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * An instance of this class represents a collection of tabs for an object view.
 * Each tab is an Object_View_Section.
 *
 */
class Tabs_Section implements Object_View_Section {

	private $tab_sections_array = array();

	// Callers should use one of the static factory methods
	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @return	self
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Get the array of tab sections
	 * @return Object_View_Section[]
	 */
	private function get_tab_sections_array() {
		return $this->tab_sections_array;
	} // function

	/**
	 * Add a tab
	 * @param string				$title			The title for the tab, e.g. "Event Map"
	 * @param Object_View_Section	$tab_section	The tab section to be added, e.g. a Map_Section object
	 */
	public function add_tab( $title, $tab_section ) {
		$this->tab_sections_array[ $title ] = $tab_section;
	} // function

	/**
	 * Render this section
	 */
	public function render_section() {
		$tabs_array = $this->get_tab_sections_array();
		echo '<div class="reg-man-rc-object-view-section reg-man-rc-object-view-tabs-container initially-hidden">';
			if ( ! empty( $tabs_array ) ) {

				$sections_array = array(); // Create an array of sections with their IDs to make rendering them simpler
				$item_format = '<li><a href="#%1$s">%2$s</a></li>';
				echo '<ul>';
					foreach( $tabs_array as $title => $tab ) {
						$id = esc_attr( 'tabs-' . strtolower( sanitize_title_with_dashes( $title ) ) );
						$sections_array[ $id ] = $tab;
						printf( $item_format, $id, esc_html( $title ) );
					} // endfor
				echo '</ul>';

				foreach( $sections_array as $id => $tab ) {
					echo "<div id=\"$id\">";
						$tab->render_section();
					echo '</div>';
				} // endfor

			} // endif

		echo '</div>';
	} // function

} // class