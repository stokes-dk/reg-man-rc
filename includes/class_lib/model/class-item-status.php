<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class represents a status used to classify items.
 *
 * @since	v0.1.0
 *
 */
class Item_Status {

	private static $ORDS_FIXED			= 'Fixed';
	private static $ORDS_REPAIRABLE		= 'Repairable';
	private static $ORDS_END_OF_LIFE	= 'End of life';

//	const UNKNOWN			= 'Unknown';		// The status of the item is not known
//	const PRE_REGISTERED	= 'Pre-reg';		// The item is pre-registered for an event
	const REGISTERED		= 'Registered';		// The item is registered and waiting for a repair attempt
//	const IN_PROGRESS		= 'In progress';	// The repair attempt is in progress
	const FIXED				= 'Fixed';			// Fixed!
	const REPAIRABLE		= 'Repairable';		// Not fixed but received advice on how to move forward with fix
	const END_OF_LIFE		= 'End of life';	// Not able to be fixed

	private static $ALL_STATUS_ARRAY; // an array of all the statuses that we'll create once and reuse
	private static $DEFAULT_STATUS; // The default status, used when an item has no status setting

	private $id;
	private $name;
	private $desc;
	private $ords_key; // The key for this status as defined by the Open Repair Data Standard

	private function __construct( $id, $name, $desc, $ords_key ) {
		$this->id = $id;
		$this->name = $name;
		$this->desc = $desc;
		$this->ords_key = $ords_key;
	} // constructor

	/**
	 * Get all Item statuses
	 * @return	\Reg_Man_RC\Model\Item_Status[]
	 * @since	v0.1.0
	 */
	public static function get_all_item_statuses() {
		// FIXME = the status names and descriptions should be configurable
		// Also they will need associated colours for charts
		if ( ! isset( self::$ALL_STATUS_ARRAY ) ) {

			self::$ALL_STATUS_ARRAY = array();

			self::$ALL_STATUS_ARRAY[ self::REGISTERED ] = new self(
				self::REGISTERED,
				__( 'Registered', 'reg-man-rc' ),
				__( 'The item is registered but the repair has not been attempted or the repair result is not known.', 'reg-man-rc' ),
				''
			);

			self::$ALL_STATUS_ARRAY[ self::FIXED ] = new self(
				self::FIXED,
				__( 'Fixed', 'reg-man-rc' ),
				__( 'The item was fixed!', 'reg-man-rc' ),
				self::$ORDS_FIXED
			);

			self::$ALL_STATUS_ARRAY[ self::REPAIRABLE ] = new self(
				self::REPAIRABLE,
				__( 'Repairable / got advice', 'reg-man-rc' ),
				__( 'The item was not fully fixed but some progress was made or advice was given about how to move forward with the repair.', 'reg-man-rc' ),
				self::$ORDS_REPAIRABLE
			);

			self::$ALL_STATUS_ARRAY[ self::END_OF_LIFE ] = new self(
				self::END_OF_LIFE,
				__( 'End of life', 'reg-man-rc' ),
				__( 'The item could not be fixed.', 'reg-man-rc' ),
				self::$ORDS_END_OF_LIFE
			);

		} // endif

		return self::$ALL_STATUS_ARRAY;

	} // function

	/**
	 * Get the item status with the specified ID
	 *
	 * @param	int|string	$item_status_id	The ID of the item status to be returned
	 * @return	Item_Status	An instance of this class with the specified ID or NULL if the item status does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_item_status_by_id( $item_status_id ) {
		$all = self::get_all_item_statuses();
		$result = isset( $all[ $item_status_id ] ) ? $all[ $item_status_id ] : self::get_default_item_status();
		return $result;
	} // function

	/**
	 * Get the default item status, used when an item has no status assigned
	 *
	 * @return	Item_Status	An instance of this class with the default item status
	 *
	 * @since v0.1.0
	 */
	public static function get_default_item_status() {
		if ( ! isset( self::$DEFAULT_STATUS ) ) {
			$all = self::get_all_item_statuses();
			self::$DEFAULT_STATUS = $all[ self::REGISTERED ];
		} // endif
		return self::$DEFAULT_STATUS;
	} // function

	/**
	 * Get the ID of this status
	 *
	 * @return	int	The ID of this item status
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the name of this status
	 *
	 * @return	string	The name of this item status
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this status
	 *
	 * @return	string	The description of this item status
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->desc;
	} // function

} // class
