<?php
namespace Reg_Man_RC\Model;

/**
 * An Event_Key uniquely identifies an event within the system.
 * Events may be created using this plugin or other plugins like Event Calendar by WebDorado.
 * The Event_Key contains:
 *  the event date (can be used to identify an instance of a recurring event),
 *  the event descriptor ID (the ID used internally by the event provider, e.g. a post ID),
 *  and an optional ID for the provider (used for events created by plugins other than this one).
 *
 * An instance of this class may be converted to a string so that a reference to an event may be
 *  stored in the database or passed as an argument in an HTML request.
 * For example, an item registration contains a reference to its event; this is stored in the database as an event key.
 * 
 * Event descriptors are liable to change or even disappear.
 * For example, an event descriptor may have its date changed, its recurrence rule changed, or may be deleted altogether.
 * As a result, an event key like '20230609 1234' may refer to an event that cannot be found.
 * The event key contains the date of the event so that, even if the event descriptor disappears, we know the date of registration.
 * If the event descriptor CAN be found using its key then we can access its details like summary, location and so on.
 * Otherwise, we will construct a Placeholder Event object using just the event date in the key and omitting other details.
 * 
 * Note that the date in the event key is always in the local timezone for improved readability.
 * A key like '20230609 1234' refers to an event on June 9, 2023 in the local timezone.
 * However, the event's start date/time is always stored in UTC so if the event starts at 9:00 pm Toronto time then
 *  the UTC date/time stored in the database will be on June 10.
 * As a result, given an event key like '20230609 1234' you cannot search the database for an event using the specified date.
 * But in general, you cannot do that anyway because a recurring event descriptor represents a series of recurring
 *  events on different days and those dates are not stored in the database at all.
 * When using a key to find an event, we use the provider ID and descriptor ID to first find the descriptor.
 * Then we use the date in the key to verify that it represents a valid date.
 */
final class Event_Key implements \JsonSerializable {
	
	const EVENT_DATE_FORMAT = 'Ymd'; // The date in a key is always in the local timezone
	
	// The following constants are the associative array keys used with this object
	const EVENT_KEY_QUERY_ARG_NAME		= 'rc-key'; // Used in $_GET to specify an event
	
	// Used for event page URLs for recurring events like .../my-event?rc-date=20230609...
	const EVENT_DATE_QUERY_ARG_NAME		= 'rc-date';

	// The following are used to convert this object to an associative array
	private static $EVENT_DESC_ID_ASSOC_ARRAY_KEY	= 'rc-evt';
	private static $PROVIDER_ID_ASSOC_ARRAY_KEY		= 'rc-prv';
	private static $EVENT_DATE_ASSOC_ARRAY_KEY		= self::EVENT_DATE_QUERY_ARG_NAME;

	private $event_date_string;
	private $event_descriptor_id;
	private $provider_id;
	private $as_json_serializable; // this key represented as an object that can be serialized using json_encode()
	private $as_string; // this key represented as a string
	private $as_query_arg_array; // this key represented as an array of query args for a url
	private $as_assoc_array; // this key represented as an associative array

	/**
	 * Get an instance of this class which can be used to uniquely identify an event on the system.
	 * The caller must specify:
	 *  the event descriptor ID used internally by the event provider
	 *  the ID of the event provider if the provider is not this plugin
	 *  the event's recurrence date if the event is recurring
	 * @param	\DateTime|string	$event_date_time		The date and time of the event
	 * @param	string				$event_descriptor_id	The unique ID for the event descriptor within the domain of the specified provider, e.g. the event's post ID
	 * @param	string				$provider_id			(optional) The ID of the event provider if the event is provided by an external event provider like 'ecwd'.
	 *  If the provider_id is not specified, the event is assumed to come from this plugin
	 * @return	\Reg_Man_RC\Model\Event_Key		The key for the event
	 * @since v0.1.0
	 */
	public static function create( $event_date_time, $event_descriptor_id, $provider_id = NULL ) {
		
//	Error_Log::var_dump( $event_date_time, $event_descriptor_id, $provider_id );
//	Error_Log::log_backtrace();
		$result = new self();

		$result->initialize_event_date_string( $event_date_time );
			
		// The event ID (unique within the provider, e.g. the post ID for a custom post type)
		$result->event_descriptor_id = strval( $event_descriptor_id );

		// The provider ID, defaults to our internal event provider
		if ( isset( $provider_id ) && ! empty( $provider_id ) ) {
			$result->provider_id = strval( $provider_id );
		} else {
			$result->provider_id = Internal_Event_Descriptor::EVENT_PROVIDER_ID;
		} // endif
		
		if ( empty( $result->event_date_string ) ) {
			// When the event date string is not properly initialized we should try to fix it when possible
			$event_descriptor = Event_Descriptor_Factory::get_event_descriptor_by_id( $result->event_descriptor_id, $result->provider_id );
			if ( ! empty( $event_descriptor ) && ! $event_descriptor->get_event_is_recurring() ) {
				$start_date = $event_descriptor->get_event_start_date_time();
				if ( ! empty( $start_date ) ) {
					$result->initialize_event_date_string( $start_date );
				} // endif
			} // endif
		} // endif

		return $result;
	} // function

	/**
	 * Initialize the event date and time for this instance based on the specified DateTime object.
	 * @param \DateTime|string $input_event_date_time
	 */
	private function initialize_event_date_string( $input_event_date_time ) {

		$local_tz = wp_timezone();
//	Error_Log::var_dump( $input_event_date_time );
		
		$data_format = self::EVENT_DATE_FORMAT;
		
		if ( $input_event_date_time instanceof \DateTimeInterface ) {
			
		 	// We cannot change the timezone of the incoming object so we need to create a copy
			// Note that it would be nice to use DateTime::createFromInterface()
			// But this is not available until after PHP version 8
			
			$atom_format = \DateTimeInterface::ATOM; // We need a format that includes the timezone
			
			try {
	
				$date_string = $input_event_date_time->format( $atom_format );
				$event_date_time_object = \DateTime::createFromFormat( $atom_format, $date_string );
				$event_date_time_object->setTimezone( $local_tz );
	
			} catch( \Exception $exc ) {
				
				$msg = __( 'An invalid event date was supplied to event key.', 'reg-man-rc' );
				Error_Log::log_msg( $msg );
				
			} // endtry
			
			$this->event_date_string = $event_date_time_object->format( $data_format );

		} else {

			// In all other cases, this should just be a string
			$date_string = strval( $input_event_date_time );

			try {
	
				$event_date_time_object = new \DateTime( $date_string, $local_tz );

			} catch( \Exception $exc ) {
				
				// Translators: %1$s is a date string 
//				$msg = sprintf( __( 'An invalid event date string was supplied to event key: %1$s.', 'reg-man-rc' ), $date_string );
//				Error_Log::log_msg( $msg ); // This happens too often to report constantly
				
			} // endtry
			
			$this->event_date_string = isset( $event_date_time_object ) ? $event_date_time_object->format( $data_format ) : '';

		} // endif
		
	} // function
	
	/**
	 * Contruct an instance of this class from a string.
	 * The string should be one returned by get_as_string(), jsonSerialize() or __toString() in this class.
	 *
	 * @param	string	$key_string	The event key as a string previously obtained from an instance of this class.
	 * A valid key string contains 2 or 3 parts separated by a space character.
	 * For an created using this plugin the event key string is event date/time and post ID for the event descriptor,
	 *  e.g. "20230609T170000Z 1234".
	 * For an event from external providers the key string is the event date/time, event descriptor ID and event provider ID:
	 *  e.g. "20230609T170000Z 1234 ecwd"
	 * @return	Event_Key	The key for the event
	 * @since v0.1.0
	 */
	public static function create_from_string( $key_string ) {
		// TODO: Should we have an event keys cache???
		$parts = explode( ' ', $key_string, $limit = 3 );
		$date	= isset( $parts[ 0 ] ) ? $parts[ 0 ] : NULL;
		$id		= isset( $parts[ 1 ] ) ? $parts[ 1 ] : NULL;
		$prv	= isset( $parts[ 2 ] ) ? $parts[ 2 ] : NULL;
		$result = self::create( $date, $id, $prv );
		return $result;
	} // function
	
	/**
	 * Contruct an instance of this class from an associative array.
	 * The associative array should be one returned by geet_as_associative_array() in this class.
	 *
	 * @param	string	$assoc_array	The event key as a string previously obtained from an instance of this class.
	 * @return	Event_Key|NULL	The key for the event or NULL if the argument is not valid
	 * @since v0.6.0
	 */
	public static function create_from_assoc_array( $assoc_array ) {
		if ( ! is_array( $assoc_array ) ) {
			$result = NULL;
		} else {
			$date = isset( $assoc_array[ self::$EVENT_DATE_ASSOC_ARRAY_KEY ] ) ? $assoc_array[ self::$EVENT_DATE_ASSOC_ARRAY_KEY ] : NULL;
			$id = isset( $assoc_array[ self::$EVENT_DESC_ID_ASSOC_ARRAY_KEY ] ) ? $assoc_array[ self::$EVENT_DESC_ID_ASSOC_ARRAY_KEY ] : NULL;
			$prv = isset( $assoc_array[ self::$PROVIDER_ID_ASSOC_ARRAY_KEY ] ) ? $assoc_array[ self::$PROVIDER_ID_ASSOC_ARRAY_KEY ] : NULL;
			$result = ( isset( $date ) && isset( $id ) && isset( $prv ) ) ? self::create( $date, $id, $prv ) : NULL;
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
		$query_vars[] = self::EVENT_DATE_QUERY_ARG_NAME;
		return $query_vars;
	} // function

	/**
	 * Get the event date as a string
	 * @return string
	 */
	public function get_event_date_string() {
		return $this->event_date_string;
	} // function

	/**
	 * Get the event provider ID
	 * @return string
	 */
	public function get_provider_id() {
		return $this->provider_id;
	} // function

	/**
	 * Get the event descriptor ID
	 * @return string
	 */
	public function get_event_descriptor_id() {
		return $this->event_descriptor_id;
	} // function

	/**
	 * Get this key as a string.
	 * A valid key string contains 2 or 3 parts separated by a space character.
	 * For an event created using this plugin the event key string is event date and the post ID for the event descriptor,
	 *  e.g. "20230609 1234".
	 * For an event from an external provider the key string is the event date, event descriptor ID and event provider ID:
	 *  e.g. "20230609 5678 ecwd"
	 * @return string	This object converted to a string.
	 */
	public function get_as_string() {
		if ( ! isset( $this->as_string ) ) {
			$parts = array(); // create an array of parts then implode
			$parts[] = $this->get_event_date_string();
			$parts[] = $this->get_event_descriptor_id();
			$prv = $this->get_provider_id();
			if ( ! empty( $prv ) && $prv !== Internal_Event_Descriptor::EVENT_PROVIDER_ID ) {
				$parts[] = $prv;
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
	public function jsonSerialize() : string {
		$result = $this->get_as_string();
		return $result;
	} // function

	/**
	 * Get this key as an associative array of name value pairs.
	 * The result always contains the event date/time and event descriptor ID.
	 * For events from sources other than this plugin, the result also contains the provider ID.
	 * For example,
	 *   [ 'rc-date' => '20230609', 'rc-evt' => '1234', 'rc-prv' => 'ecwd' ]
	 * @return string[][]
	 */
	public function get_as_associative_array() {
		
		if ( ! isset( $this->as_assoc_array ) ) {

			$this->as_assoc_array = array();
			
			// Always include the event date/time
			$this->as_assoc_array[ self::$EVENT_DATE_ASSOC_ARRAY_KEY ] = $this->get_event_date_string();
			
			// Always include the event descriptor ID
			$this->as_assoc_array[ self::$EVENT_DESC_ID_ASSOC_ARRAY_KEY ] = $this->get_event_descriptor_id();
			
			$prv = $this->get_provider_id();
			// Only include the provider ID when it is assigned
			if ( ! empty( $prv ) && ( $prv !== Internal_Event_Descriptor::EVENT_PROVIDER_ID ) ) {
				$this->as_assoc_array[ self::$PROVIDER_ID_ASSOC_ARRAY_KEY ] = $prv;
			} // endif

		} // endif
		
		return $this->as_assoc_array;
		
	} // function

	public function __toString() {
		$result = $this->get_as_string();
		return $result;
	} // function
} // class