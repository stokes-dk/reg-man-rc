<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * Describes an item registered to an event by an external source.
 *
 * An instance of this class contains the information related to an item registered to an event
 * including its description, event, item type and so on.
 *
 * @since v0.1.0
 *
 */
class External_Item implements Item_Descriptor {

	private $description;
	private $event_key;
	private $event;
	private $visitor_full_name;
	private $visitor_public_name;
	private $item_type_name;
	private $fixer_station_name;
	private $status_name;
	private $source;

	/**
	 * Get the items defined by external item providers, e.g. Legacy data items, for any event specified in the argument
	 *
	 * This method will return an array of instances of this class describing all items registered to any of the specified events
	 * and supplied by active add-on plugins for external item providers like Repair Cafe Toronto Legacy data
	 *
	 * @param	Event_Key[]		$event_key_array	An array of Event_Key objects whose registered items are to be returned.
	 * @return	\Reg_Man_RC\Model\Stats\Item_Descriptor[]
	 */
	public static function get_external_items( $event_key_array ) {
		/**
		 * Get all items defined under external item providers for the specified set of events
		 *
		 * Each external item provider will extract its items and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$event_key_array	An array of event key descriptors whose registered items are to be returned.
		 *  Each array element is an associative array like, array( 'rc-evt' => '1234', 'rc-prv => 'ecwd', 'rc-rcr' => '' );
		 * @return	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external item.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 */
		if ( $event_key_array === NULL ) {
			$key_data_array = NULL;
		} else {
			$key_data_array = array();
			foreach( $event_key_array as $event_key ) {
				$key_obj = Event_Key::create_from_string( $event_key );
				$key_data_array[] = $key_obj->get_as_associative_array();
			} // endfor
		} // endif
		$desc_data_arrays = apply_filters( 'reg_man_rc_get_items', array(), $key_data_array );
		$result = array();
		foreach ( $desc_data_arrays as $data_array ) {
			$item = self::instantiate_from_data_array( $data_array );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Instantiate this class using the data array provided.
	 *
	 * This method will return a single instance of this class describing the item specified by the provided data.
	 *
	 * @since v0.1.0
	 * @api
	 * @param	string[]	$data_array	{
	 * 		An associative array of strings describing the external item
	 *
	 * 		@type	string	'description'		The item's description, e.g. "Toaster"
	 * 		@type	string	'event-id'			The ID for the event used within its event provider domain
	 * 		@type	string	'event-provider'	The external event provider or NULL if the event is internal to this plugin
	 * 		@type	string	'event-recur-id'	The recurrence ID if it's a repeating event, otherwise NULL
	 * 		@type	string	'item-type'			The item's type as a string, e.g. "Appliance"
	 * 		@type	string	'fixer-station'		The name the fixer station this item was assigned to or NULL if no station assigned
	 * 		@type	string	'status'			The status of the item provided as one of the constants defined in Item_Status
	 * 		@type	string	'source'			The source of this record, e.g. "legacy"
	 * }
	 * @return	\Reg_Man_RC\Model\Stats\External_Item	The External_Item object constructed from the data provided.
	 */
	private static function instantiate_from_data_array( $data_array ) {

		$result = new self();

		$result->description = isset( $data_array[ 'description' ] ) ? $data_array[ 'description' ] : NULL;

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_id = $data_array[ 'event-id' ];
			$provider_id = isset( $data_array[ 'event-provider' ] ) ? $data_array[ 'event-provider' ] : NULL;
			$recur_id = isset( $data_array[ 'event-recur-id' ] ) ? $data_array[ 'event-recur-id' ] : NULL;
			$event_key = Event_Key::create( $event_id, $provider_id, $recur_id );
			$result->event_key = $event_key;
		} // endif

		$result->visitor_full_name		= isset( $data_array[ 'visitor-full-name' ] )	? $data_array[ 'visitor-full-name' ] : NULL;
		$result->visitor_public_name 	= isset( $data_array[ 'visitor-public-name' ] )	? $data_array[ 'visitor-public-name' ] : NULL;

		// The external name provided may need to be converted into our internal name
		if ( isset( $data_array[ 'item-type' ] ) ) {
			$type = Item_Type::get_item_type_by_name( $data_array[ 'item-type' ] );
			$result->item_type_name = isset( $type ) ? $type->get_name() : NULL;
		} // endif

		// The external name provided may need to be converted into our internal name
		if ( isset( $data_array[ 'fixer-station' ] ) ) {
			$station = Fixer_Station::get_fixer_station_by_name( $data_array[ 'fixer-station' ] );
			$result->fixer_station_name = isset( $station ) ? $station->get_name() : NULL;
		} // endif

		$result->status_name = isset( $data_array[ 'status' ] ) ? $data_array[ 'status' ] : NULL;

		$result->source		= isset( $data_array[ 'source' ] )	? $data_array[ 'source' ] : __( 'external', 'reg-man-rc' );

	//	Error_Log::var_dump( $result );
		return $result;
	} // function

	/**
	 * Get the description of this item
	 * @return	string	The description of this item which may contain html formating
	 * @since	v0.1.0
	 */
	public function get_item_description() {
		return $this->description;
	} // function

	/**
	 * Get the key for the event for which this item was registered
	 * @return	string	The event key for the event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		return $this->event_key;
	} // function
	
	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			if ( isset( $this->event_key ) ) {
				$this->event = Event::get_event_by_key( $this->event_key );
			} // endif
		} // endif
		return $this->event;
	} // function

	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name() {
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		if ( is_admin() && current_user_can( $capability ) && ! empty( $this->visitor_full_name ) ) {
			$result = $this->visitor_full_name;
		} else {
			$result = $this->get_visitor_public_name();
		} // endif
		return $result;
	} // function

	/**
	 * Get the public name of the visitor who registered the item.
	 * This is a name for the visitor that can be used in public like first name and last initial.
	 * @return	string		The visitor's public name.
	 * @since	v0.1.0
	 */
	public function get_visitor_public_name() {
		return $this->visitor_public_name;
	} // function

	/**
	 * Get the item type name for this item
	 * @return	string	The name of this item's type
	 * @since	v0.1.0
	 */
	public function get_item_type_name() {
		return $this->item_type_name;
	} // endif

	/**
	 * Get the fixer station name assigned to this item
	 *
	 * @return	string	The name of the fixer station assigned to this item or NULL if no fixer station is assigned
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_station_name() {
		return $this->fixer_station_name;
	} // function

	/**
	 * Get name the status for this item, i.e. Fixed, Repairable etc.
	 *
	 * @return	string	The status assigned to this item.
	 *
	 * @since v0.1.0
	 */
	public function get_status_name() {
		return $this->status_name;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_item_descriptor_source() {
		return $this->source;
	} // function

} // class