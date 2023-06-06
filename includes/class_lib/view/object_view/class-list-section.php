<?php
namespace Reg_Man_RC\View\Object_View;


/**
 * An instance of this class represents a list of details about an object like event's category, date, location etc.
 *
 */
class List_Section implements Object_View_Section {

	private $item_names_array = array(); // The array of names to be displayed in this section
	private $item_provider;

	// Callers should use one of the static factory methods
	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @return List_Section
	 */
	public static function create( $item_provider, $item_names ) {
		$result = new self();
		$result->item_provider = $item_provider;
		$result->item_names_array = $item_names;
		return $result;
	} // function

	/**
	 * Get the list item provider used by this section
	 * @return List_item_Provider[]
	 */
	private function get_item_provider() {
		return $this->item_provider;
	} // function

	/**
	 * Set the list item provider to be used by this section
	 * @param List_item_Provider	$item_provider	The list item provider for this section
	 */
	public function set_item_provider( $item_provider ) {
		$this->item_provider = $item_provider;
	} // function

	/**
	 * Get the array of list item names to be displayed this section
	 * @return string[]
	 */
	private function get_item_names_array() {
		return $this->item_names_array;
	} // function

	/**
	 * Set the array of list item names to be displayed this section
	 * @param string[]	$item_names_array	The array of item names to be displayed
	 */
	public function set_item_names_array( $item_names_array ) {
		$this->item_names_array = $item_names_array;
	} // function

	/**
	 * Get the array of List_Item objects for this section
	 * @return List_Item[]
	 */
	private function get_items_array() {
		$result = array();
		$provider = $this->get_item_provider();
		$names = $this->get_item_names_array();
		foreach( $names as $item_name ) {
			$item = $provider->get_list_item( $item_name );
			if ( isset( $item ) ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Render this section
	 */
	public function render_section() {
		$items_array = $this->get_items_array();
		if ( ! empty( $items_array ) ) {
			echo '<div class="reg-man-rc-object-view-section">';
				echo "<ul class=\"reg-man-rc-object-view-details-list\">";
					foreach( $items_array as $item ) {
						if ( isset( $item ) ) {
							$item->render_item();
						} // endif
					} // endfor
				echo '</ul>';
			echo '</div>';
		} // endif
	} // function

} // class