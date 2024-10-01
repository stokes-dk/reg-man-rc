<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class represents a status used to assign an item's state.
 *
 * @since	v0.1.0
 *
 */
class Item_Status {

	// Repair outcome indicators used for the Open Repair Data Standard, we'll use these internally
	const ORDS_FIXED		= 'Fixed';
	const ORDS_REPAIRABLE	= 'Repairable';
	const ORDS_END_OF_LIFE	= 'End of life';
	const ORDS_UNKNOWN		= 'Unknown';
	
	const STANDBY			= 'Standby';				// The item is not yet in the queue for repair
	const IN_QUEUE			= 'In Queue';				// The item is in the queue for repair
	const IN_PROGRESS		= 'In Progress';			// The repair attempt is in progress
	const FIXED				= self::ORDS_FIXED;			// Fixed!
	const REPAIRABLE		= self::ORDS_REPAIRABLE;	// Not fixed but received advice on how to move forward with fix
	const END_OF_LIFE		= self::ORDS_END_OF_LIFE;	// Not able to be fixed
	const DONE_UNREPORTED	= 'Done';					// The item was completed but the repair outcome not reported
	const WITHDRAWN			= 'Withdrawn';				// The item was registered and then removed
	
	private static $ALL_STATUS_ARRAY; // an array of all the statuses that we'll create once and reuse

	private static $DEFAULT_STATUS_ID = self::STANDBY;
	private static $DEFAULT_STATUS; // The default status, used when an item has no status setting
	
	private static $ACTIVE_STATUS_LABEL;
	private static $COMPLETE_STATUS_LABEL;
	private static $INACTIVE_STATUS_LABEL;
	
	private $id;
	private $name;
	private $ords_name; // the status name used for the Open Repair Data standard
	private $desc;
	private $is_active; // TRUE if the status represents an active status, in queue or in progress
	private $is_complete; // TRUE if the status represents a complete status, fixed, repairable, eol or done
	private $ords_key; // The key for this status as defined by the Open Repair Data Standard
	private $is_repair_outcome_status;

	private function __construct( $id, $name, $desc, $is_active, $is_complete, $ords_key ) {
		$this->id = $id;
		$this->name = $name;
		$this->desc = $desc;
		$this->is_active = $is_active;
		$this->is_complete = $is_complete;
		$this->ords_key = $ords_key;
		$this->is_repair_outcome_status = isset( $ords_key );
	} // constructor

	/**
	 * Get all Item statuses
	 * @return	\Reg_Man_RC\Model\Item_Status[]
	 * @since	v0.1.0
	 */
	public static function get_all_item_statuses() {

		// FIXME = the status names and descriptions should be configurable
		
		if ( ! isset( self::$ALL_STATUS_ARRAY ) ) {

			self::$ALL_STATUS_ARRAY = array();

			self::$ALL_STATUS_ARRAY[ self::IN_QUEUE ] = new self(
				self::IN_QUEUE,
				__( 'Awaiting Fixer', 'reg-man-rc' ),
				__( 'The item is in the queue waiting for a fixer', 'reg-man-rc' ),
				$is_active = TRUE,
				$is_complete = FALSE,
				$ords_key = NULL
			);

			self::$ALL_STATUS_ARRAY[ self::IN_PROGRESS ] = new self(
				self::IN_PROGRESS,
				__( 'With Fixer', 'reg-man-rc' ),
				__( 'The item is with a fixer and the repair is ongoing', 'reg-man-rc' ),
				$is_active = TRUE,
				$is_complete = FALSE,
				$ords_key = NULL
			);

			self::$ALL_STATUS_ARRAY[ self::FIXED ] = new self(
				self::FIXED,
				__( 'Fixed', 'reg-man-rc' ),
				__( 'The item was fixed!', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = TRUE,
				$ords_key = self::ORDS_FIXED
			);

			self::$ALL_STATUS_ARRAY[ self::REPAIRABLE ] = new self(
				self::REPAIRABLE,
				__( 'Repairable / got advice', 'reg-man-rc' ),
				__( 'The item was not fully fixed but some progress was made or advice was given about how to move forward with the repair.', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = TRUE,
				$ords_key = self::ORDS_REPAIRABLE
			);

			self::$ALL_STATUS_ARRAY[ self::END_OF_LIFE ] = new self(
				self::END_OF_LIFE,
				__( 'End of life', 'reg-man-rc' ),
				__( 'The item could not be fixed.', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = TRUE,
				$ords_key = self::ORDS_END_OF_LIFE
			);
			
			self::$ALL_STATUS_ARRAY[ self::DONE_UNREPORTED ] = new self(
				self::DONE_UNREPORTED,
				__( 'Done, outcome not reported', 'reg-man-rc' ),
				__( 'The repair attempt is complete but the outcome was not reported.', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = TRUE,
				$ords_key = NULL
			);
			
			self::$ALL_STATUS_ARRAY[ self::STANDBY ] = new self(
				self::STANDBY,
				__( 'Standby', 'reg-man-rc' ),
				__( 'Waiting for the visitor\'s higher priority items to be complete', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = FALSE,
				$ords_key = NULL
			);
			
			self::$ALL_STATUS_ARRAY[ self::WITHDRAWN ] = new self(
				self::WITHDRAWN,
				__( 'Withdrawn', 'reg-man-rc' ),
				__( 'The item was registered and then removed', 'reg-man-rc' ),
				$is_active = FALSE,
				$is_complete = FALSE,
				$ords_key = NULL
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
	public static function get_default_item_status_id() {
		return self::$DEFAULT_STATUS_ID;
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
			self::$DEFAULT_STATUS = $all[ self::get_default_item_status_id() ];
		} // endif
		return self::$DEFAULT_STATUS;
	} // function

	/**
	 * Get the label used to group active statuses
	 * @return string
	 */
	public static function get_active_status_label() {
		if ( ! isset( self::$ACTIVE_STATUS_LABEL ) ) {
			self::$ACTIVE_STATUS_LABEL = __( 'In Progress', 'reg-man-rc' );
		} // endif
		return self::$ACTIVE_STATUS_LABEL;
	} // function
	
	/**
	 * Get the label used to group complete statuses
	 * @return string
	 */
	public static function get_complete_status_label() {
		if ( ! isset( self::$COMPLETE_STATUS_LABEL ) ) {
			self::$COMPLETE_STATUS_LABEL = __( 'Complete', 'reg-man-rc' );
		} // endif
		return self::$COMPLETE_STATUS_LABEL;
	} // function
	
	/**
	 * Get the label used to group complete statuses
	 * @return string
	 */
	public static function get_inactive_status_label() {
		if ( ! isset( self::$INACTIVE_STATUS_LABEL ) ) {
			self::$INACTIVE_STATUS_LABEL = __( 'Visitor\'s additional items', 'reg-man-rc' );
		} // endif
		return self::$INACTIVE_STATUS_LABEL;
	} // function
	
	/**
	 * Get the set of options for item status
	 * @return string[][] An array of arrays containing the item status names and ids grouped by active, complete and standby.
	 * E.g. array(
	 * 	'In Progress' => array(
	 * 		'Waiting for fixer'	=>	IN_QUEUE,
	 * 		'With fixer'		=>	IN_PROGRESS
	 * 	),
	 *  'Complete' => array(
	 *  	'Fixed'				=> FIXED,
	 *  ...
	 */
	public static function get_item_status_options() {

		$result = array();
		
		// Active
		$active_label = self::get_active_status_label();
		$active_group = array();

		// Complete
		$complete_label = self::get_complete_status_label();
		$complete_group = array();

		// Inactive
		$inactive_label = self::get_inactive_status_label();
		$inactive_group = array();

		$all_statuses = Item_Status::get_all_item_statuses();
		
		foreach( $all_statuses as $item_status ) {
			
			$id = $item_status->get_id();
			$name = $item_status->get_name();
			
			if ( $item_status->get_is_active() ) {
				
				$active_group[ $name ] = $id;
				
			} elseif ( $item_status->get_is_complete() ) {
				
				$complete_group[ $name ] = $id;
				
			} else {
				
				$inactive_group[ $name ] = $id;
				
			} // endif
			
		} // endif
		
		$result[ $active_label ] = $active_group;
		$result[ $complete_label ] = $complete_group;
		$result[ $inactive_label ] = $inactive_group;
		
		return $result;
		
	} // function
	/**
	 * Get the ID of this status
	 *
	 * @return	string	The ID of this item status
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
	 * Get the Open Repair Data Standard version of this status
	 *
	 * @return	string	The status used by ORDS
	 *
	 * @since v0.9.5
	 */
	public function get_ORDS_name() {
		if ( ! isset( $this->ords_name ) ) {
			switch( $this->id ) {
				
				case self::FIXED:
					$this->ords_name = self::ORDS_FIXED;
					break;
					
				case self::REPAIRABLE:
					$this->ords_name = self::ORDS_REPAIRABLE;
					break;
					
				case self::END_OF_LIFE:
					$this->ords_name = self::ORDS_END_OF_LIFE;
					break;
					
				default:
					$this->ords_name = self::ORDS_UNKNOWN;
					break;
					
			} // endswitch
		} // endif
		return $this->ords_name;
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

	/**
	 * Get a boolean indicating whether this status represents an active item
	 * @return	boolean	TRUE when this status is an active status like In Queue, FALSE otherwise
	 */
	public function get_is_active() {
		return $this->is_active;
	} // function

	/**
	 * Get a boolean indicating whether this status represents an item that is complete
	 * @return	boolean	TRUE when this status is a complete status like Done or Fixed, FALSE otherwise
	 */
	public function get_is_complete() {
		return $this->is_complete;
	} // function

	/**
	 * Get a boolean indicating whether this status is a repair outcome
	 * @return	boolean	TRUE when this status is a repair outcome status like Fixed,
	 * 	FALSE when the status does not indicate any repair outcome
	 */
	public function get_is_repair_outcome_status() {
		return $this->is_repair_outcome_status;
	} // function

} // class
