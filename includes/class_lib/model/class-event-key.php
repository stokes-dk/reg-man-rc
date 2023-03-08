<?php
namespace Reg_Man_RC\Model;

/**
 * An Event_Key uniquely identifies an event within the system.
 * Events may be created using this plugin (internal events) or other plugins like Event Calendar by WebDorado (external events).
 * To uniquely identify events across providers the Event_Key stores an ID for the provider as well as the event ID used
 * internally by the provider, and a recurrence ID for recurring events.
 * For example, an event created using this plugin has a provider ID of 'rmrc' (defined in the Internal_Event_Descriptor class)
 * and an event ID of the custom post ID.
 * If the event is recurring then the Event_Key object also includes the recurrence ID.
 *
 * An instance of this class may be converted to a string so that a reference to an event may be stored in the database,
 *  or passed as an argument in an HTML request.
 */
final class Event_Key implements \JsonSerializable {

	// The following constants are the associative array keys used with this object
	const EVENT_KEY_QUERY_ARG_NAME		= 'rc-key'; // Used in $_GET to specify an event
	const RECUR_ID_QUERY_ARG_NAME		= 'rc-rcr'; // May be used in $_GET to specify an instance of recurring event

	private static $EVENT_DESC_ID_ASSOC_ARRAY_KEY	= 'rc-evt'; // Used to convert this object to associative array
	private static $PROVIDER_ID_ASSOC_ARRAY_KEY		= 'rc-prv'; // Used to convert this object to associative array

	private $event_descriptor_id;
	private $provider_id;
	private $recur_id;
	private $as_json_serializable; // this key represented as an object that can be serialized using json_encode()
	private $as_string; // this key represented as a string
	private $as_query_arg_array; // this key represented as an array of query args for a url
	private $as_assoc_array; // this key represented as an associative array

	/**
	 * Get an instance of this class which can be used to uniquely identify an event on the system.
	 * The caller must specify:
	 *  the event descriptor ID used internally by the event provider
	 *  the ID of the event provider if the provider is not this plugin
	 *  the event's recurrence ID if the event is recurring
	 * @param	string	$event_descriptor_id	The unique ID for the event descriptor within the domain of the specified provider, e.g. the event's post ID
	 * @param	string	$provider_id			(optional) The ID of the event provider if the event is provided by an external event provider like 'ecwd'.
	 *  If the provider_id is not specified, the event is assumed to come from this plugin
	 * @param	string	$recurrence_id			(optional) The unique ID for an instance of a recurring event, e.g. the event's start date / time as a string
	 * @return	\Reg_Man_RC\Model\Event_Key		The key for the event
	 * @since v0.1.0
	 */
	public static function create( $event_descriptor_id, $provider_id = NULL, $recurrence_id = NULL ) {
		$result = new self();

		// The event ID (unique within the provider, e.g. the post ID for a custom post type)
		$result->event_descriptor_id = strval( $event_descriptor_id );

		// The provider ID, defaults to our internal event provider
		if ( isset( $provider_id ) && ! empty( $provider_id ) ) {
			$result->provider_id = strval( $provider_id );
		} else {
			$result->provider_id = Internal_Event_Descriptor::EVENT_PROVIDER_ID;
		} // endif

		// The recurrence ID
		if ( isset( $recurrence_id ) && ( ! empty( $recurrence_id ) || ( $recurrence_id == 0 ) ) ) {
			$result->recur_id = strval( $recurrence_id );
		} // endif

		return $result;
	} // function

	/**
	 * Contruct an instance of this class from a string.
	 * The string should be one returned by get_as_string(), jsonSerialize() or __toString() in this class.
	 *
	 * @param	string	$key_string	The event key as a string previously obtained from an instance of this class.
	 * A valid key string contains 1, 2 or 3 parts separated by a space character.
	 * For a non-recurring event created using this plugin the event key string is simply the post ID for the event descriptor,
	 *  e.g. "1234".
	 * For non-recurring events from external providers the key string is the event ID and event provider ID:
	 *  e.g. "1234 ecwd"
	 * For all recurring events, including those created using this plugin the string contains all three parts,
	 *  e.g. "1234 rmrc 20220131"
	 * @return	Event_Key	The key for the event
	 * @since v0.1.0
	 */
	public static function create_from_string( $key_string ) {
		$parts = explode( ' ', $key_string, $limit = 3 );
		$id		= isset( $parts[ 0 ] ) ? $parts[ 0 ] : NULL;
		$prv	= isset( $parts[ 1 ] ) ? $parts[ 1 ] : NULL;
		$rcr	= isset( $parts[ 2 ] ) ? $parts[ 2 ] : NULL;
		$result = self::create( $id, $prv, $rcr );
		return $result;
	} // function

	/**
	 * Return all event keys for events defined by the specified filter.
	 *
	 * @param	Event_Filter|NULL	$filter		An event filter which defines the set of events whose keys are to be returned
	 *  or NULL if the keys for all events are to be returned.
	 * @return	string[]	An array of event key strings for the events defined by the specified filter
	 *  or for all events known to the system if the $filter argument is NULL.
	 */
	public static function get_event_keys_for_filter( $filter ) {
		// Because of recurring events we need to create all the event objects and request their keys
		$events = Event::get_all_events_by_filter( $filter );
		$result = array();
		foreach ( $events as $event ) {
			$result[] = $event->get_key();
		} // endif
		return $result;
	} // function

	/**
	 * Register this model class
	 */
	public static function register() {
		// Add filter for my query vars
		add_filter( 'query_vars', array( __CLASS__, 'filter_query_vars' ), 10, 1 );
	} // function

	/**
	 * Register the query vars used by this class
	 * @param	string[]	$query_vars
	 * @return	string[]	The query vars modified to include ours
	 */
	public static function filter_query_vars( $query_vars ) {
//		Error_Log::var_dump( $query_vars );
		$query_vars[] = self::EVENT_KEY_QUERY_ARG_NAME;
		$query_vars[] = self::RECUR_ID_QUERY_ARG_NAME;
		return $query_vars;
	} // function

	public function get_provider_id() {
		return $this->provider_id;
	} // function

	public function get_event_descriptor_id() {
		return $this->event_descriptor_id;
	} // function

	public function get_recurrence_id() {
		return $this->recur_id;
	} // function

	/**
	 * Get this key as a string.
	 * A key string contains 1, 2 or 3 parts separated by a space character.
	 * For a non-recurring event created using this plugin the event key string is simply the post ID for the event descriptor,
	 *  e.g. "1234".
	 * For non-recurring events from external providers the key string is the event ID and event provider ID:
	 *  e.g. "1234 ecwd"
	 * For all recurring events, including those created using this plugin the string contains all three parts,
	 *  e.g. "1234 rmrc 20220131"
	 * @return string	This object converted to a string.
	 */
	public function get_as_string() {
		if ( ! isset( $this->as_string ) ) {
			$parts = array(); // create an array of parts then implode
			$parts[] = $this->get_event_descriptor_id(); // We always need an ID
			$prv = $this->get_provider_id();
			$rcr = $this->get_recurrence_id();
			if ( empty( $rcr ) ) {
				// For a non-recurring event we will conditionally include the provider (if it's not this plugin)
				if ( ! empty( $prv ) && ( $prv !== Internal_Event_Descriptor::EVENT_PROVIDER_ID ) ) {
					$parts[] = $prv;
				} // endif
			} else {
				// This is a recurring event so always include the prv and the rcr
				if ( empty( $prv ) ) {
					$prv = Internal_Event_Descriptor::EVENT_PROVIDER_ID;
				} // endif
				$parts[] = $prv;
				$parts[] = $rcr;
			} // endif
			$this->as_string = implode( ' ', $parts );
		} // endif
		return $this->as_string;
	} // function

	/**
	 * Get an object which can be serialized using json_encode().
	 * The result is this object converted to a string.
	 *
	 * @return string	This object converted to a string.
	 * @since v0.1.0
	 */
	public function jsonSerialize() {
		$result = $this->get_as_string();
		return $result;
	} // function

	/**
	 * Get this key as an associative array of name value pairs.
	 * The result always contains the event descriptor ID.
	 * For events from sources other than this plugin, the result contains the provider ID.
	 * For recurring events, the result contains the recurrence ID.
	 * For example,
	 *   [ 'rc-evt' => '1234', 'rc-prv' => 'ecwd', 'rc-rcr' => '20221216' ]
	 * @return string[][]
	 */
	public function get_as_associative_array() {
		if ( ! isset( $this->as_assoc_array ) ) {
			$this->as_assoc_array = array();
			// Always include the event descriptor ID
			$this->as_assoc_array[ self::$EVENT_DESC_ID_ASSOC_ARRAY_KEY ] = $this->get_event_descriptor_id();
			$prv = $this->get_provider_id();
			$rcr = $this->get_recurrence_id();
			// Only include the provider and recurrence ID when those are assigned
			if ( ! empty( $prv ) && ( $prv !== Internal_Event_Descriptor::EVENT_PROVIDER_ID ) ) {
				$this->as_assoc_array[ self::$PROVIDER_ID_ASSOC_ARRAY_KEY ] = $prv;
			} // endif
			if ( ! empty( $rcr ) ) {
				$this->as_assoc_array[ self::RECUR_ID_QUERY_ARG_NAME ] = $rcr;
			} // endif
		} // endif
		return $this->as_assoc_array;
	} // function

	public function __toString() {
		$result = $this->get_as_string();
		return $result;
	} // function
} // class