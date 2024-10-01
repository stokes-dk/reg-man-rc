<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class represents a status used to classify events.
 *
 * @since	v0.1.0
 *
 */
class Event_Status {

	/**
	 * @var	string	Indicating an event status as CONFIRMED
	 * @since v0.1.0
	 */
	const CONFIRMED = 'CONFIRMED';

	/**
	 * A string indicating an event status as TENTATIVE
	 * @since v0.1.0
	 */
	const TENTATIVE = 'TENTATIVE';

	/**
	 * A string indicating an event status as CANCELLED
	 * @since v0.1.0
	 */
	const CANCELLED = 'CANCELLED';

	private static $ALL_STATUS_ARRAY;

	private $id;
	private $name;
	private $desc;
	private $marker_text;

	private function __construct() { }

	public static function get_all_event_statuses() {
		if ( !isset( self::$ALL_STATUS_ARRAY ) ) {

			$confirmed = new self();
			$confirmed->id			= self::CONFIRMED;
			$confirmed->name		= __( 'Confirmed', 'reg-man-rc' );
			$confirmed->desc		= __( 'The event will take place (or has already taken place).', 'reg-man-rc' );

			$tentative = new self();
			$tentative->id			= self::TENTATIVE;
			$tentative->name		= __( 'Tentative', 'reg-man-rc' );
			$tentative->desc		= __( 'The event has not yet been confirmed.', 'reg-man-rc' );

			$cancelled = new self();
			$cancelled->id			= self::CANCELLED;
			$cancelled->name		= __( 'Cancelled', 'reg-man-rc' );
			$cancelled->desc		= __( 'The event will not take place (or did not take place).', 'reg-man-rc' );

			self::$ALL_STATUS_ARRAY = array(
				self::CONFIRMED	=> $confirmed,
				self::TENTATIVE	=> $tentative,
				self::CANCELLED	=> $cancelled,
			);

		} // endif
		return self::$ALL_STATUS_ARRAY;
	} // function

	/**
	 * Get the event status with the specified ID which must be one of the constants defined in this class: CONFIRMED, TENTATIVE or CANCELLED.
	 *
	 * @param	int|string			$event_status_id	The ID of the event status to be returned.
	 * The ID must be one of the constants defined in this class: CONFIRMED, TENTATIVE or CANCELLED.
	 * If the specified ID is unrecognized, NULL will be returned.
	 * @return	Event_Status|NULL	An instance of this class with the specified ID, or NULL if the ID is not recognized.
	 *
	 * @since v0.1.0
	 */
	public static function get_event_status_by_id( $event_status_id ) {
		$all = self::get_all_event_statuses();
		$result = isset( $all[ $event_status_id ] ) ? $all[ $event_status_id ] : NULL;
		return $result;
	} // function

	/**
	 * Get the default event status object, to be used when an event that has no status assigned.
	 *
	 * @return	Event_Status	The default event status object.
	 *
	 * @since v0.6.0
	 */
	public static function get_default_event_status() {
		$all = self::get_all_event_statuses();
		$event_status_id = self::get_default_event_status_id();
		$result = $all[ $event_status_id ];
		return $result;
	} // function

	/**
	 * Get the default event status ID, to be used when an event that has no status assigned.
	 *
	 * @return	string	The ID of the default event status.
	 *
	 * @since v0.1.0
	 */
	public static function get_default_event_status_id() {
		return self::CONFIRMED;
	} // function

	/**
	 * Get the ID of this object
	 *
	 * @return	string	The ID of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this object
	 *
	 * @return	string	The description of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->desc;
	} // function

	/**
	 * Get the marker text to use for this status.
	 * This can be applied to the event title like: "CANCELLED Creative Reuse Toronto Drop-in Event"
	 * @return	string
	 * @since	v0.5.0
	 */
	public function get_event_marker_text() {
		if ( ! isset( $this->marker_text ) ) {

			// TODO: We could have a configuration option for this
			switch ( $this->id ) {

				default:
				case self::CONFIRMED:
					$this->marker_text = '';
					break;
					
				case self::TENTATIVE:
					$this->marker_text = __( 'TENTATIVE', 'reg-man-rc' );
					break;
					
				case self::CANCELLED:
					$this->marker_text = __( 'CANCELLED!', 'reg-man-rc' );
					break;
			
			} // endswitch

		} // endif
		
		return $this->marker_text;
		
	} // function
	
} // class
