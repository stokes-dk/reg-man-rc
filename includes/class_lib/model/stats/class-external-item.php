<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Item_Status;

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

	private $id;
	private $description;
	private $event_key_string;
	private $event;
	private $visitor_display_name;
	private $visitor_full_name;
	private $visitor_public_name;
	private $item_type_name;
	private $fixer_station_name;
	private $item_status;
	private $source;

	/**
	 * Get the items defined by external item providers, e.g. Legacy data items, for any event specified in the argument
	 *
	 * This method will return an array of instances of this class describing all items registered to any of the specified events
	 * and supplied by active add-on plugins for external item providers like Repair Cafe Toronto Legacy data
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose registered items are to be returned.
	 * @return	\Reg_Man_RC\Model\Stats\Item_Descriptor[]
	 */
	public static function get_external_items( $event_keys_array ) {
		/**
		 * Get all items defined under external item providers for the specified set of events
		 *
		 * Each external item provider will extract its items and add them to the result
		 *
		 * @since v0.1.0
		 *
		 * @api
		 *
		 * @param	string[][]	$event_keys_array	An array of event key descriptors whose registered items are to be returned.
		 *  Each array element is an associative array like, array( 'rc-date' => '2023...', 'rc-evt' => '1234', 'rc-prv => 'ecwd' );
		 * @return	string[][]	$desc_data_arrays	An array of string arrays where each string array provides the details of one external item.
		 * 	The details of the array are documented in the instantiate_from_data_array() method of this class.
		 */
		if ( $event_keys_array === NULL ) {
			$key_data_array = NULL;
		} else {
			$key_data_array = array();
			foreach( $event_keys_array as $event_key_string ) {
				$key_obj = Event_Key::create_from_string( $event_key_string );
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
	 * Get an array of event key for items registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_keys_for_items_in_date_range( $min_key_date_string, $max_key_date_string ) {
		/**
		 * Get all event keys for items defined under external item providers within the specified date range
		 *
		 * Each external item provider will extract the event keys for its items and add them to the result.
		 * If the external provider is unable to determine its event keys then it will do nothing.
		 *
		 * @since v0.6.0
		 *
		 * @api
		 *
		 * @param	string	$min_key_date_string	The minimum date for the range in the format Ymd, e.g. 20230601
		 * @param	string	$max_key_date_string	The maximum date for the range in the format Ymd, e.g. 20230630
		 * @return	string[]	An array of event key strings for each event key with items registered
		 */
		$keys_array = apply_filters( 'reg_man_rc_get_event_keys_for_items_in_date_range', array(), $min_key_date_string, $max_key_date_string );
//	Error_Log::var_dump( $keys_array );
		$result = array();
		foreach ( $keys_array as $key_string ) {
			$key_object = Event_Key::create_from_string( $key_string );
			if ( ! empty( $key_object ) ) {
				$result[] = $key_object->get_as_string();
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
	 * 		@type	string	'event-date'		The event date if it is known, otherwise NULL
	 * 		@type	string	'item-type'			The item's type as a string, e.g. "Electric"
	 * 		@type	string	'fixer-station'		The name the fixer station this item was assigned to or NULL if no station assigned
	 * 		@type	string	'status'			The status of the item provided as one of the constants defined in Item_Status
	 * 		@type	string	'source'			The source of this record, e.g. "legacy"
	 * }
	 * @return	\Reg_Man_RC\Model\Stats\External_Item	The External_Item object constructed from the data provided.
	 */
	private static function instantiate_from_data_array( $data_array ) {

		$result = new self();

		$result->id			 = isset( $data_array[ 'id' ] )			 ? $data_array[ 'id' ]			: NULL;
		$result->description = isset( $data_array[ 'description' ] ) ? $data_array[ 'description' ] : NULL;

		if ( isset( $data_array[ 'event-id' ] ) ) {
			$event_desc_id = $data_array[ 'event-id' ];
			$provider_id =	isset( $data_array[ 'event-provider' ] )	? $data_array[ 'event-provider' ] : NULL;
			$recur_date =	isset( $data_array[ 'event-date' ] )		? $data_array[ 'event-date' ] : NULL;
			$event = Event::get_event_by_descriptor_id( $event_desc_id, $provider_id, $recur_date );
			if ( isset( $event ) ) {
				$result->event = $event;
				$result->event_key_string = $event->get_key_string();
			} // endif
		} else {
			// FIXME We can't find the event so... we should make one?
			// The problem is that we do not always know the event date, only for recurring events, right?
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

		// Item status
		if ( isset( $data_array[ 'status' ] ) ) {
			$item_status = Item_Status::get_item_status_by_id( $data_array[ 'status' ] );
			$result->item_status = $item_status;
		} // endif

		$result->source		= isset( $data_array[ 'source' ] )	? $data_array[ 'source' ] : __( 'external', 'reg-man-rc' );

	//	Error_Log::var_dump( $result );
		return $result;
	} // function

	/**
	 * Get the ID of this item
	 * @return	string	The ID of this item
	 * @since	v0.9.5
	 */
	public function get_item_id() {
		return $this->id;
	} // function

	/**
	 * Get the description of this item
	 * @return	string	The description of this item
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
	public function get_event_key_string() {
		return $this->event_key_string;
	} // function
	
	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			if ( isset( $this->event_key_string ) ) {
				$this->event = Event::get_event_by_key( $this->event_key_string );
			} // endif
		} // endif
		return $this->event;
	} // function

	/**
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_visitor_display_name() {
		
		if ( ! isset( $this->visitor_display_name ) ) {
			if ( is_admin() && current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {
			
				$this->visitor_display_name = ! empty( $this->visitor_full_name ) ? $this->visitor_full_name : $this->visitor_public_name;
				
			} else {
				
				$this->visitor_display_name = $this->visitor_public_name;
				
			} // endif
			
			if ( empty( $this->visitor_display_name ) ) {
				$this->visitor_display_name =  __( '[No name]', 'reg-man-rc' );
			} // endif

		} // endif
		
		return $this->visitor_display_name;

	} // function
	
	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name() {
		
		// Users who can edit others' visitor records can see the full name
		if ( current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {
		
			$result = $this->visitor_full_name;
			
		} else {
			
			$result = NULL;
			
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
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_email()
	 */
	public function get_visitor_email() {
		return '';
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_is_first_time()
	 */
	public function get_visitor_is_first_time() {
		return NULL;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_is_join_mail_list()
	 */
	public function get_visitor_is_join_mail_list() {
		return NULL;
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
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_item_status()
	 */
	public function get_item_status() {
		return $this->item_status;
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