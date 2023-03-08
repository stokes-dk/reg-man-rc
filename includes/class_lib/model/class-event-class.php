<?php
namespace Reg_Man_RC\Model;

/**
 * An Event_Class represents a classification on an events.  There are only three event classes: PUBLIC, PRIVATE and CONFIDENTIAL.
 *
 * @since	v0.1.0
 *
 */
class Event_Class {

	/**
	 * A string indicating an event class as PUBLIC
	 * @since v0.1.0
	 */
	const PUBLIC = 'PUBLIC';

	/**
	 * A string indicating an event class as PRIVATE
	 * @since v0.1.0
	 */
	const PRIVATE = 'PRIVATE';

	/**
	 * A string indicating an event class as CONFIDENTIAL
	 * @since v0.1.0
	 */
	const CONFIDENTIAL = 'CONFIDENTIAL';

	private static $ALL_CLASS_ARRAY;

	private $id;
	private $name;
	private $desc;

	private function __construct() { }

	public static function get_all_event_classes() {
		if ( ! isset( self::$ALL_CLASS_ARRAY ) ) {

			$public = new self();
			$public->id			= self::PUBLIC;
			$public->name		= __( 'Public', 'reg-man-rc' );
			$public->desc		= __( 'The event is visible to the public.', 'reg-man-rc' );

			$private = new self();
			$private->id		= self::PRIVATE;
			$private->name		= __( 'Private', 'reg-man-rc' );
			$private->desc		= __( 'The event not visible to the public.', 'reg-man-rc' );

			$confidential = new self();
			$confidential->id	= self::CONFIDENTIAL;
			$confidential->name	= __( 'Confidential', 'reg-man-rc' );
			$confidential->desc	= __( 'The event is confidential.', 'reg-man-rc' );

			self::$ALL_CLASS_ARRAY = array(
				self::PUBLIC		=> $public,
				self::PRIVATE		=> $private,
				self::CONFIDENTIAL	=> $confidential,
			);

		} // endif
		return self::$ALL_CLASS_ARRAY;
	} // function


	/**
	 * Get the event class with the specified ID which must be one of the constants defined in this class: PUBLIC, PRIVATE, or CONFIDENTIAL.
	 *
	 * @param	int|string		$event_class_id	The ID of the event class to be returned.
	 * The ID must be one of the constants defined in this class: PUBLIC, PRIVATE, or CONFIDENTIAL.
	 * If the specified ID is unrecognized, PUBLIC will be returned.
	 * @return	Event_Class		An instance of this class with the specified ID.
	 *
	 * @since v0.1.0
	 */
	public static function get_event_class_by_id( $event_class_id ) {
		$all = self::get_all_event_classes();
		$result = isset( $all[ $event_class_id ] ) ? $all[ $event_class_id ] : $all[ self::PUBLIC ];
		return $result;
	} // function

	/**
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this event class
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this event class
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this object
	 *
	 * @return	string	The description of this event class
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->desc;
	} // function

} // class
