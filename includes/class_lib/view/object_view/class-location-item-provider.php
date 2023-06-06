<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Venue;

/**
 * This class provides list items for a location like a venue or event descriptor.
 * Note that some event descriptors have a location address but no venue so this is a fallback when venue is missing.
 */
class Location_Item_Provider implements List_item_Provider {

	private $location_name;
	private $object_view;
	private $location_address;

	private function __construct() {
	} // funciton

	/**
	 * Create an instance of this class with the specified address and name.
	 * @param string		$location_address
	 * @param string		$location_name
	 * @param Object_View	$object_view
	 * @return	Location_Item_Provider
	 */
	public static function create( $location_name, $location_address, $object_view ) {
		$result = new self();
		$result->location_name = $location_name;
		$result->location_address = $location_address;
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Create an instance of this class for the specified venue
	 * @param Venue			$venue
	 * @param Object_View	$object_view
	 * @return	Location_Item_Provider
	 */
	public static function create_for_venue( $venue, $object_view ) {
		$result = new self();
		if ( isset( $venue ) ) {
			$result->location_name = $venue->get_name();
			$result->location_address = $venue->get_location();
			$result->object_view = $object_view;
		} // endif
		return $result;
	} // function

	/**
	 * Get the location name for this item provider.
	 * @return	string		The place name
	 * @since	v0.1.0
	 */
	private function get_location_name() {
		return $this->location_name;
	} // function

	/**
	 * Get the location address for this item provider.
	 * @return	string		The location address
	 * @since	v0.1.0
	 */
	private function get_location_address() {
		return $this->location_address;
	} // function

	/**
	 * Get the object view client for this item provider
	 * @return	Object_View		The object view object using this item provider
	 * @since	v0.1.0
	 */
	private function get_object_view() {
		return $this->object_view;
	} // function

	/**
	 * Get a list item based on its name.  May return NULL if the name is not known or there's no content to display.
	 * @return List_Item|NULL
	 */
	public function get_list_item( $item_name ) {
		$result = NULL;
		switch( $item_name ) {

			case List_Item::LOCATION_NAME:
				$result = $this->get_location_name_item();
				break;

			case List_Item::LOCATION_ADDRESS:
				$result = $this->get_location_address_item();
				break;

			case List_Item::GET_DIRECTIONS_LINK:
				$result = $this->get_place_directions_link_item();
				break;

		} // endswitch
		return $result;
	} // function

	/**
	 * Get the place name item
	 * @return	List_Item|NULL	An item showing the place name, or null if no place name is assigned
	 * @since	v0.1.0
	 */
	private function get_location_name_item() {
		$location_name = $this->get_location_name();
		if ( ! empty( $location_name ) ) {
			$icon = 'location';
			$icon_title = esc_attr__( 'Place', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-location-name';
			$result = List_Item::create( $location_name, $icon, $icon_title, $classes );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Get the location address item
	 * @return	List_Item	An item showing the location address or "TBD" if no address is assigned
	 * @since	v0.1.0
	 */
	private function get_location_address_item() {
		$location_address = $this->get_location_address();
		$classes = 'reg-man-rc-object-view-details-location-address';
		$icon = 'location-alt';
		$icon_title = __( 'Location', 'reg-man-rc' );
		if ( empty( $location_address ) ) {
			$location_address = __( 'Location to be determined', 'reg-man-rc' );
			$classes .= ' object-view-details-location_address-tbd';
		} // endif
		$result = List_Item::create( $location_address, $icon, $icon_title, $classes );
		return $result;
	} // function

	/**
	 * Get the "Get Direction" item
	 * @return	List_Item	An item showing an external link to a Google map with directions to this location
	 * @since	v0.1.0
	 */
	private function get_place_directions_link_item() {

		$result = NULL; // Assume there's no item to return and the replace if necessary

		$location_address = $this->get_location_address();

		if ( ! empty( $location_address ) && Settings::get_show_event_external_map_link() ) {

			$href = Map_View::get_external_google_maps_directions_href( $location_address );

			if ( ! empty( $href ) ) {

				$text = esc_html( __( 'Get directions', 'reg-man-rc' ) );
				$link = "<a target=\"_blank\" href=\"$href\">$text</a>";
				$icon = 'external';
				$icon_title = esc_attr__( 'Google map directions for this location', 'reg-man-rc' );
				$classes = 'reg-man-rc-object-view-details-get-directions';
				$result = List_Item::create( $link, $icon, $icon_title, $classes );

			} // endif

		} // endif

		return $result;

	} // function

} // class