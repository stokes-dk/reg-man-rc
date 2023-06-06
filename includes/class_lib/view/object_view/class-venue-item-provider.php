<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Venue;

/**
 * This class provides list items for a place like a venue, event or event descriptor.
 */
class Venue_Item_Provider implements List_item_Provider {

	private $venue;
	private $object_view;
	private $location_item_provider; // delegate for most operations

	private function __construct() {
	} // funciton

	/**
	 * Create an instance of this class with the specified address and name.
	 * @param Venue			$venue
	 * @param Object_View	$object_view
	 * @return	Venue_Item_Provider
	 */
	public static function create( $venue, $object_view ) {
		$result = new self();
		$result->venue = $venue;
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Get the venue object for this item provider.
	 * @return	Venue		The venue
	 * @since	v0.1.0
	 */
	private function get_venue() {
		return $this->venue;
	} // function

	private function get_location_item_provider() {
		if ( ! isset( $this->location_item_provider ) ) {
			$venue = $this->get_venue();
			$object_view = $this->get_object_view();
			$this->location_item_provider = Location_Item_Provider::create_for_venue( $venue, $object_view );
		} // endif
		return $this->location_item_provider;
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
		$is_found = FALSE;
		switch( $item_name ) {

			case List_Item::VENUE_DESCRIPTION:
				$is_found = TRUE;
				$result = $this->get_venue_description_item();
				break;

		} // endswitch

		if ( ! $is_found ) {
			$location_item_provider = $this->get_location_item_provider();
			$result = $location_item_provider->get_list_item( $item_name );
		} // endif

		return $result;

	} // function

	/**
	 * Get the event description item
	 * @return	List_Item|NULL	An item showing the event's description, or null if none is assigned
	 * @since	v0.1.0
	 */
	private function get_venue_description_item() {

		$result = NULL; // Assume nothing
		$venue_description = $this->get_venue()->get_description();

		if ( ! empty( $venue_description) ) {

			$item_content = __( 'Venue Details', 'reg-manr-rc');
			$icon = 'text-page';
			$icon_title = esc_attr__( 'Venue Details', 'reg-man-rc' );
			$classes = 'reg-man-rc-object-view-details-event-description';
			$addn_content = wpautop( $venue_description ); // add paragraph tags for line breaks
			$result = List_Item::create( $item_content, $icon, $icon_title, $classes, $addn_content );

		} // endif

		return $result;

	} // function

} // class