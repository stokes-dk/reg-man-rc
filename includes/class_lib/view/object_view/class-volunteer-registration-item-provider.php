<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Volunteer_Registration_View;

/**
 * An instance of this class provides List_Item instances for displaying the details of a volunteer registration
 *
 */
class Volunteer_Registration_Item_Provider implements List_Item_Provider {

	private $event;
	private $volunteer_registration;
	private $object_view;
	private $delegate_item_provider;

	private function __construct() {
	} // funciton

	/**
	 * Create a new instance of this class for the specified event
	 * @param Event $event
	 * @param Object_View	$object_view
	 * @return	Volunteer_Registration_Item_Provider
	 */
	public static function create( $event, $object_view ) {
		$result = new self();
		$result->event = $event;
		$result->volunteer_registration = $event->get_volunteer_registration();
		$result->object_view = $object_view;
		return $result;
	} // function

	/**
	 * Get the event object for this item provider
	 * @return	Event
	 * @since	v0.1.0
	 */
	private function get_event() {
		return $this->event;
	} // function

	/**
	 * Get the object view object for this item provider
	 * @return	Object_View
	 * @since	v0.1.0
	 */
	private function get_object_view() {
		return $this->object_view;
	} // function

	/**
	 * Get the delegate item provider if one is available.
	 * Anything that I can't handle, I will pass on to the Event_Item_Provider
	 * @return	List_Item_Provider		The delegate item provider
	 * @since	v0.1.0
	 */
	private function get_delegate_item_provider() {
		if ( ! isset( $this->delegate_item_provider ) ) {

			$event = $this->get_event();

			if ( isset( $event ) ) {

				$this->delegate_item_provider = Event_Item_Provider::create( $event, $this->get_object_view() );

			} // endif

		} // endif
		return $this->delegate_item_provider;
	} // function

	/**
	 * Get a list item based on its name.  May return NULL if the name is not known or there's no content to display.
	 * @return List_Item|NULL
	 */
	public function get_list_item( $item_name ) {
		$result = NULL; // Assume we can't handle this name

		switch( $item_name ) {

			case List_Item::VOLUNTEER_REG_STATUS:
				$result = $this->get_registration_status_item();
				break;

			case List_Item::VOLUNTEER_REG_DETAILS_LINK:
				$result = $this->get_volunteer_reg_details_link_item();
				break;

		} // endswitch

		if (! isset( $result ) ) {
			$delegate = $this->get_delegate_item_provider();
			$result = $delegate->get_list_item( $item_name );
		} // endif

		return $result;
	} // function


	private function get_registration_status_item() {
		$event = $this->get_event();
		$object_view = $this->get_object_view();
		$result = Volunteer_Registration_View::create_registration_status_item( $event, $object_view );
		return $result;
	} // function

	/**
	 * Get an item with a link to the volunteer registration details page
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	private function get_volunteer_reg_details_link_item() {
		$event = $this->get_event();
		$result = Volunteer_Registration_View::create_volunteer_reg_details_link_item( $event );
		return $result;
	} // function

} // class