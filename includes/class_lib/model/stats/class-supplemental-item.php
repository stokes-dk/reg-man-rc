<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Error_Log;

/**
 * Describes an item that was brought to an event and seen by a fixer but not registered to the system.
 *
 * @since v0.1.0
 *
 */
class Supplemental_Item implements Item_Descriptor {

	const SUPPLEMENTAL_ITEMS_TABLE_NAME		= 'reg_man_rc_sup_items';

	private $event;
	private $event_key;
	private $item_type_name;
	private $fixer_station_name;
	private $item_status;
	private $status_name;
	
	private static $VALID_ITEM_TYPE_IDS_LIST; // a string containing a list of valid item types (used in SQL queries)
	private static $VALID_FIXER_STATION_IDS_LIST; // a string containing a list of valid fixer stations (used in SQL queries)
	
	private function __construct() {
	} // function
	
	/**
	 * Get all the item descriptors for supplemental items for any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing items for
	 *  the supplemental records stored in the database.
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose item descriptors are to be returned.
	 * @return	Item_Descriptor[]
	 */
	public static function get_all_supplemental_item_descriptors( $event_keys_array ) {

		global $wpdb;
		$result = array();
		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		$cols = 'id, event_key, fixed_count, repairable_count, eol_count, unreported_count, item_type_id, fixer_station_id';

		// Only use valid type IDs and fixer station IDs
		$type_ids_list = self::get_valid_item_type_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "item_type_id IN ( $type_ids_list ) AND fixer_station_id IN ( $stations_ids_list )";
		
		if ( is_array( $event_keys_array ) && count( $event_keys_array ) > 0 ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where .= " AND event_key IN ( $placehold_string ) ";
			$query = "SELECT $cols FROM $table WHERE $where";
			$query = $wpdb->prepare( $query, $event_keys_array );
			
		} else {
			
			$query = "SELECT $cols FROM $table WHERE $where";
			
		} // endif
		
//		Error_Log::var_dump( $query );
		
		$desc_data_arrays = $wpdb->get_results( $query, ARRAY_A );
//		Error_Log::var_dump( count( $desc_data_arrays ) );

		foreach ( $desc_data_arrays as $data_array ) {
			$inst_array = self::create_instance_array_from_data_array( $data_array );
			if ( ! empty( $inst_array ) ) {
				$result = array_merge( $result, $inst_array );
			} // endif
		} // endfor

		return $result;
		
	} // function
	
	private static function get_valid_item_type_ids_list() {
		if ( ! isset( self::$VALID_ITEM_TYPE_IDS_LIST ) ) {

			$type_ids = Item_Type::get_all_term_taxonomy_ids();
			$type_ids[] = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
			self::$VALID_ITEM_TYPE_IDS_LIST = implode( ',', $type_ids );
		
		} // endif
		return self::$VALID_ITEM_TYPE_IDS_LIST;
	} // function

	private static function get_valid_fixer_station_ids_list() {
		if ( ! isset( self::$VALID_FIXER_STATION_IDS_LIST ) ) {
		
			$station_ids = Fixer_Station::get_all_term_taxonomy_ids();
			$station_ids[] = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
			self::$VALID_FIXER_STATION_IDS_LIST = implode( ',', $station_ids );

		} // endif
		return self::$VALID_FIXER_STATION_IDS_LIST;
	} // function

	/**
	 * Instantiate this class using the data array provided.
	 *
	 * This method will return an array of instances of this class each describing one item.
	 * Note that each record in the database table specifies a number of items brought to an event
	 *  but not register through the system.
	 * For example, "We had 3 bikes that were fixed and 2 end of life".
	 * That row in the database table will correspond to 5 instances of this class.
	 *
	 * @since v0.1.0
	 * @param	string[]	$data_array	{
	 * 		An associative array of strings conaining the supplemental volunteer registration record
	 *
	 * 		@type	string	'event_key'			The key for the event
	 * 		@type	string	'item_type_id'		The ID for the item type
	 * 		@type	string	'fixer_station_id'	The ID for the fixer station
	 * 		@type	string	'fixed_count'		The number of items fixed (for the specified item type and fixer station)
	 * 		@type	string	'repairable_count'	The number of items repairable
	 * 		@type	string	'eol_count'			The number of items at end of life
	 * 		@type	string	'unreported_count'	The number of items whose repair status was unreported
	 * }
	 * @return	Supplemental_Item[]		An array of instances of this class.
	 */
	private static function create_instance_array_from_data_array( $data_array ) {

//		Error_Log::var_dump( $data_array );

		$result = array();
		$event_key			= isset( $data_array[ 'event_key' ] )			? $data_array[ 'event_key' ]			: NULL;
		$type_id			= isset( $data_array[ 'item_type_id' ] )		? $data_array[ 'item_type_id' ]			: NULL;
		$station_id			= isset( $data_array[ 'fixer_station_id' ] )	? $data_array[ 'fixer_station_id' ]		: NULL;
		$fixed_count		= isset( $data_array[ 'fixed_count' ] )			? intval( $data_array[ 'fixed_count' ] )		: 0;
		$repairable_count	= isset( $data_array[ 'repairable_count' ] )	? intval( $data_array[ 'repairable_count' ] )	: 0;
		$eol_count			= isset( $data_array[ 'eol_count' ] )			? intval( $data_array[ 'eol_count' ] )			: 0;
		$unreported_count	= isset( $data_array[ 'unreported_count' ] )	? intval( $data_array[ 'unreported_count' ] )	: 0;

//		Error_Log::var_dump( $event_key, $type_id, $station_id, $fixed_count, $repairable_count, $eol_count );

		$type = isset( $type_id ) ? Item_Type::get_item_type_by_id( $type_id ) : NULL;
		$type_name = isset( $type ) ? $type->get_name() : NULL;
		
		$station = isset( $station_id ) ? Fixer_Station::get_fixer_station_by_id( $station_id ) : NULL;
		$station_name = isset( $station ) ? $station->get_name() : NULL;

		// Fixed
		$item_status = Item_Status::get_item_status_by_id( Item_Status::FIXED );
		$status_name = $item_status->get_name();
		if ( $fixed_count > 0 ) {
			for ( $index = 0; $index < $fixed_count; $index++  ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->item_type_name = $type_name;
				$curr->fixer_station_name = $station_name;
				$curr->item_status = $item_status;
				$curr->status_name = $status_name;
				$result[] = $curr;
			} // endfor
		} // endif

		// Repairable
		$item_status = Item_Status::get_item_status_by_id( Item_Status::REPAIRABLE );
		$status_name = $item_status->get_name();
		if ( $repairable_count > 0 ) {
			for ( $index = 0; $index < $repairable_count; $index++ ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->item_type_name = $type_name;
				$curr->fixer_station_name = $station_name;
				$curr->item_status = $item_status;
				$curr->status_name = $status_name;
				$result[] = $curr;
			} // endfor
		} // endif

		// EOL
		$item_status = Item_Status::get_item_status_by_id( Item_Status::END_OF_LIFE );
		$status_name = $item_status->get_name();
		if ( $eol_count > 0 ) {
			for ( $index = 0; $index < $eol_count; $index++ ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->item_type_name = $type_name;
				$curr->fixer_station_name = $station_name;
				$curr->item_status = $item_status;
				$curr->status_name = $status_name;
				$result[] = $curr;
			} // endfor
		} // endif

		// Unreported
		$item_status = Item_Status::get_item_status_by_id( Item_Status::DONE_UNREPORTED );
		$status_name = $item_status->get_name();
		if ( $unreported_count > 0 ) {
			for ( $index = 0; $index < $unreported_count; $index++ ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->item_type_name = $type_name;
				$curr->fixer_station_name = $station_name;
				$curr->item_status = $item_status;
				$curr->status_name = $status_name;
				$result[] = $curr;
			} // endfor
		} // endif

//		Error_Log::var_dump( $result );
		return $result;
	} // function


	/**
	 * Get the supplemental item stats for the specified events and grouped in the specified way
	 * @param	Event_Key[]		$event_keys_array	An array of event keys whose group stats are to be returned
	 * @param	string			$group_by			One of the "GROUP_BY" constants from Item_Stats_Collection
	 * @return Item_Stats[]	An array of instances of Item_Stats describing the items and their related data.
	 */
	public static function get_supplemental_group_stats_array( $event_keys_array, $group_by ) {

		global $wpdb;
		$result = array(); // Start with an empty set and then add to it

		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		switch( $group_by ) {

			case Item_Stats_Collection::GROUP_BY_EVENT:
				$name_col = 'event_key';
				break;

			case Item_Stats_Collection::GROUP_BY_FIXER_STATION:
				$name_col = 'fixer_station_id';
				break;

			case Item_Stats_Collection::GROUP_BY_ITEM_TYPE:
				$name_col = 'item_type_id';
				break;

			case Item_Stats_Collection::GROUP_BY_STATION_AND_TYPE:
				$name_col = "CONCAT( fixer_station_id, '|', item_type_id )";
				break;

			case Item_Stats_Collection::GROUP_BY_ITEM_DESC:
				// Note that we have no item description in the supplemental items database table
				$name_col = "''"; // must be a quoted string for SQL otherwise illegal column name
				break;

			case Item_Stats_Collection::GROUP_BY_TOTAL:
			default:
				$name_col = "''"; // must be a quoted string for SQL otherwise illegal column name
				break;

		} // endswitch

		$cols = "$name_col as name, " .
				'SUM( fixed_count ) as fixed_count, ' .
				'SUM( repairable_count ) as repairable_count, ' .
				'SUM( eol_count ) as eol_count, ' .
				'SUM( unreported_count ) as unreported_count, ' .
				'SUM( fixed_count + repairable_count + eol_count + unreported_count ) as total_count ';
		
		// Only use valid type IDs and fixer station IDs
		$type_ids_list = self::get_valid_item_type_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "item_type_id IN ( $type_ids_list ) AND fixer_station_id IN ( $stations_ids_list )";
//		Error_Log::var_dump( $where );
		
		if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) > 0 ) ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where .= " AND event_key IN ( $placehold_string )";
			$query = "SELECT $cols FROM $table WHERE $where GROUP BY name";
			$query = $wpdb->prepare( $query, $event_keys_array );

		} else {
			
			$query = "SELECT $cols FROM $table WHERE $where GROUP BY name";
			
		} // endif
			
		$data_array = $wpdb->get_results( $query, OBJECT_K );
//		Error_Log::var_dump( $query, $data_array );
		foreach( $data_array as $name => $obj ) {
			$result[ $name ] = Item_Stats::create(
					$name, $obj->total_count, $obj->fixed_count, $obj->repairable_count, $obj->eol_count
				);
		} // endfor

		return $result;
			
	} // function

	/**
	 * Set the supplemental counts for items of the specified type, fixer station and event
	 * @param	int|string	$event_key			The key for the event whose counts are to be assigned
	 * @param	int|string	$item_type_id		The ID of the item type whose counts are to be assigned
	 * @param	int|string	$station_id			The ID of the fixer station whose counts are to be assigned
	 * @param	int|string	$fixed_count		The count of fixed items of this type for the event
	 * @param	int|string	$repairable_count	The count of fixed items of this type for the event
	 * @param	int|string	$eol_count			The count of fixed items of this type for the event
	 * @param	int|string	$unreported_count	The count of items of this type whose repair status is not known for the event
	 * @since	v0.1.0
	 */
	public static function set_supplemental_item_counts( $event_key, $item_type_id, $station_id, $fixed_count, $repairable_count, $eol_count, $unreported_count ) {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		
		$fixed_count		= max( 0, intval( $fixed_count ) ); // It must be a positive integer,
		$repairable_count	= max( 0, intval( $repairable_count ) );
		$eol_count			= max( 0, intval( $eol_count ) );
		$unreported_count	= max( 0, intval( $unreported_count ) );

		$query = "SELECT id FROM $table WHERE event_key=%s AND item_type_id=%s AND fixer_station_id = %s LIMIT 1";
		$stmt = $wpdb->prepare( $query, $event_key, $item_type_id, $station_id );
		$obj = $wpdb->get_row( $stmt, OBJECT );
		$existing_id = ( isset( $obj ) && isset( $obj->id ) ) ? $obj->id : NULL;

		if ( empty( $fixed_count ) && empty( $repairable_count ) && empty( $eol_count) && empty( $unreported_count ) ) {
			// If all the values are 0 then we should have no record at all so remove it if one exists
			if ( isset( $existing_id ) ) {
				$where = array( 'id' => $existing_id );
				$delete_result = $wpdb->delete( $table, $where );
				$result = ( $delete_result == 1 ) ? TRUE : FALSE;
			} else {
				// There is no existing record so nothing to do
				$result = TRUE;
			} // endif
		} else {
			// Otherwise, we need to record the supplied data so either update an existing record or insert a new one
			// 'id, event_key, item_type_id, fixed_count, repairable_count, eol_count, unreported_count';
			$vals = array(
				'fixed_count'		=> $fixed_count,
				'repairable_count'	=> $repairable_count,
				'eol_count'			=> $eol_count,
				'unreported_count'	=> $unreported_count,
			);
			$types = array_fill( 0, count( $vals ), '%s');

			if ( isset( $existing_id ) ) {
				// We must update an existing record
				$where = array( 'id' => $existing_id );
				$where_format = array( '%s' );
				$update_result = $wpdb->update( $table, $vals, $where, $types, $where_format );
				$result = ( $update_result == 1 ) ? TRUE : FALSE;
			} else {
				// We must insert a new record
				$vals[ 'event_key' ]		= $event_key;
				$vals[ 'item_type_id' ]		= $item_type_id;
				$vals[ 'fixer_station_id' ]	= $station_id;
				$types[] = '%s';
				$insert_result = $wpdb->insert( $table, $vals, $types );
				$result = ( $insert_result == 1 ) ? TRUE : FALSE;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Add the specified supplemental counts for items of the specified type, fixer station, and event
	 * If a record already exists for the specified type, station and event then add the item counts.
	 * If no record exists then create one.
	 * @param	int|string	$event_key			The key for the event whose counts are to be assigned
	 * @param	int|string	$item_type_id		The ID of the item type whose counts are to be assigned
	 * @param	int|string	$station_id			The ID of the fixer station whose counts are to be assigned
	 * @param	int|string	$fixed_count		The count of fixed items of this type for the event
	 * @param	int|string	$repairable_count	The count of fixed items of this type for the event
	 * @param	int|string	$eol_count			The count of fixed items of this type for the event
	 * @param	int|string	$unreported_count	The count of items of this type whose repair status is not known for the event
	 * @since	v0.9.9
	 */
//	private static function add_supplemental_item_counts( $event_key, $item_type_id, $station_id, $fixed_count, $repairable_count, $eol_count, $unreported_count ) {
	private static function add_supplemental_item_counts( $event_key, $item_type_id, $station_id, $fixed_count, $repairable_count, $eol_count, $unreported_count ) {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		
		$fixed_count		= max( 0, intval( $fixed_count ) ); // It must be a positive integer,
		$repairable_count	= max( 0, intval( $repairable_count ) );
		$eol_count			= max( 0, intval( $eol_count ) );
		$unreported_count	= max( 0, intval( $unreported_count ) );

		$query = "SELECT id FROM $table WHERE event_key=%s AND item_type_id=%s AND fixer_station_id = %s LIMIT 1";
		$stmt = $wpdb->prepare( $query, $event_key, $item_type_id, $station_id );
		$obj = $wpdb->get_row( $stmt, OBJECT );
		$existing_id = ( isset( $obj ) && isset( $obj->id ) ) ? $obj->id : NULL;

		if ( empty( $fixed_count ) && empty( $repairable_count ) && empty( $eol_count) && empty( $unreported_count ) ) {
			// If all the values are 0 then there's nothing to do
			$result = TRUE;
		} else {
			// Otherwise, we need to record the supplied data so either update an existing record or insert a new one
			// 'id, event_key, item_type_id, fixed_count, repairable_count, eol_count, unreported_count';
			$vals = array(
				'fixed_count'		=> $fixed_count,
				'repairable_count'	=> $repairable_count,
				'eol_count'			=> $eol_count,
				'unreported_count'	=> $unreported_count,
			);
			$types = array_fill( 0, count( $vals ), '%s');

			if ( isset( $existing_id ) ) {
				// We must update an existing record
				$where = array( 'id' => $existing_id );
				$where_format = array( '%s' );
				$update_result = $wpdb->update( $table, $vals, $where, $types, $where_format );
				$result = ( $update_result == 1 ) ? TRUE : FALSE;
			} else {
				// We must insert a new record
				$vals[ 'event_key' ]		= $event_key;
				$vals[ 'item_type_id' ]		= $item_type_id;
				$vals[ 'fixer_station_id' ]	= $station_id;
				$types[] = '%s';
				$insert_result = $wpdb->insert( $table, $vals, $types );
				$result = ( $insert_result == 1 ) ? TRUE : FALSE;
			} // endif
		} // endif
		return $result;
	} // function
	
	/**
	 * Get an array of event key for supplemental items registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_keys_for_items_in_date_range( $min_key_date_string, $max_key_date_string ) {
		
		global $wpdb;
		
		$result = array();

		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		
		// Only use valid type IDs and fixer station IDs
		$type_ids_list = self::get_valid_item_type_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "item_type_id IN ( $type_ids_list ) AND fixer_station_id IN ( $stations_ids_list )";
		
		$event_where_parts_array = array();
		$event_where_args_array = array();

		// Note that the event key always starts with the date so we can compare using >= AND <=
		if ( ! empty( $min_key_date_string ) ) {
			$event_where_parts_array[] = ' ( event_key >= %s ) ';
			$event_where_args_array[] = $min_key_date_string;
		} // endif
		
		if ( ! empty( $max_key_date_string ) ) {
			$event_where_parts_array[] = ' ( event_key <= %s ) ';
			$event_where_args_array[] = $max_key_date_string;
		} // endif
		
		if ( ! empty( $event_where_parts_array ) ) {
			$where .= ' AND ( ' . implode( ' AND ', $event_where_parts_array ) . ' ) ';
		} // endif
		
		$query = "SELECT DISTINCT event_key FROM $table WHERE $where";
		
		if ( count( $event_where_args_array ) > 0 )  {
			$query = $wpdb->prepare( $query, $event_where_args_array );
		} // endif
		$data_array = $wpdb->get_results( $query, OBJECT );

		foreach ( $data_array as $reg_data ) {
			$result[] = $reg_data->event_key;
		} // endif
		
//	Error_Log::var_dump( $result );
		return $result;
		
	} // function
	
	/**
	 * When an item type is deleted we need to remove references to it from our table.
	 * @param	int	$item_type_id
	 * @since	v0.9.9
	 */
	public static function handle_item_type_deleted( $item_type_id ) {
		
		self::handle_term_deleted( $item_type_id, 'item_type_id' );

	} // function
	
	/**
	 * When a fixer station is deleted we need to remove references to it from our table.
	 * @param	int	$fixer_station_id
	 * @since	v0.9.9
	 */
	public static function handle_fixer_station_deleted( $fixer_station_id ) {
		
		self::handle_term_deleted( $fixer_station_id, 'fixer_station_id' );

	} // function
	
	/**
	 * When a fixer station or item type is deleted we need to remove references to it from our table.
	 * We need to find all the rows in the table using this deleted (now invalid) term ID
	 *  and apply those totals to the UNSPECIFIED count for the event
	 * @param	int		$invalid_term_id	The ID of the term (item_type or fixer_station) that has been deleted
	 * @param	string	$invalid_column		The name of the table column containing the deleted ID, either 'item_type_id' or 'fixer_station_id'
	 * @since	v0.9.9
	 */
	private static function handle_term_deleted( $invalid_term_id, $invalid_column ) {
		global $wpdb;
		
		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		$cols = 'id, event_key, fixed_count, repairable_count, eol_count, unreported_count, item_type_id, fixer_station_id';

		$is_invalid_item_type = $invalid_column === 'item_type_id'; // which column has the invalid value?
		
		// Find all the rows using this item type
		$invalid_rows_query = "SELECT $cols FROM $table WHERE $invalid_column=%s";
		$invalid_rows_stmt = $wpdb->prepare( $invalid_rows_query, $invalid_term_id );

		$invalid_rows_array = $wpdb->get_results( $invalid_rows_stmt, OBJECT_K );
//		Error_Log::var_dump( $invalid_rows_query, $invalid_rows_array );

		$existing_unspecified_row_query = "SELECT $cols FROM $table WHERE event_key=%s AND item_type_id=%s AND fixer_station_id = %s LIMIT 1";

		// In reality, this is '0' either way but let's assume it could change
		$unspecified_value = $is_invalid_item_type ? Item_Type::UNSPECIFIED_ITEM_TYPE_ID : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
		
		// For each row using this invalid ID
		//  look the row with the same event and valid item type or fixer station but '0' for the invalid column
		foreach( $invalid_rows_array as $invalid_row_id => $invalid_row ) {
			
			$event_key = $invalid_row->event_key;
			if ( $is_invalid_item_type ) {
				$item_type_id = $unspecified_value;
				$fixer_station_id = $invalid_row->fixer_station_id;
			} else {
				$item_type_id = $invalid_row->item_type_id;
				$fixer_station_id = $unspecified_value;
			} // endif
			$existing_unspecified_row_stmt = $wpdb->prepare( $existing_unspecified_row_query, $event_key, $item_type_id, $fixer_station_id );
			$existing_unspecified_row_obj = $wpdb->get_row( $existing_unspecified_row_stmt, OBJECT );
//			Error_Log::var_dump( $existing_unspecified_row_obj );

			if ( empty( $existing_unspecified_row_obj ) ) {
				
				// There is NO existing row using UNSPECIFIED in the invalid column
				//  so we can just change the invalid row's invalid column to UNSPECIFIED
				$values_array = array( $invalid_column => $unspecified_value );
				self::update_row_values( $invalid_row_id, $values_array );
				
			} else {
				
				// There is an existing row for UNSPECIFIED
				//  so we have to add in the values from the invalid row then delete the invalid row
				$existing_unspecified_row_id = $existing_unspecified_row_obj->id;
				$fixed_count =		intval( $existing_unspecified_row_obj->fixed_count )		+ intval( $invalid_row->fixed_count );
				$repairable_count =	intval( $existing_unspecified_row_obj->repairable_count )	+ intval( $invalid_row->repairable_count );
				$eol_count =		intval( $existing_unspecified_row_obj->eol_count )			+ intval( $invalid_row->eol_count );
				$unreported_count =	intval( $existing_unspecified_row_obj->unreported_count )	+ intval( $invalid_row->unreported_count );

				$values_array = array(
						'fixed_count'		=> $fixed_count,
						'repairable_count'	=> $repairable_count,
						'eol_count'			=> $eol_count,
						'unreported_count'	=> $unreported_count,
				);
				self::update_row_values( $existing_unspecified_row_id, $values_array );
				
				self::delete_row( $invalid_row_id );
				
			} // endif
		} // endfor
		
	} // function
	
	private static function delete_row( $row_id ) {
		global $wpdb;
		
//		Error_Log::var_dump( $row_id );
		
		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		$where = array( 'id' => $row_id );
		
		$result = $wpdb->delete( $table, $where );
		
		if ( $result === FALSE ) {
			/* Translators: %1$s is a database table row ID, %2$s is an error message from the WordPress database ($wpdb) object */
			$format = __( 'Unable to delete supplemental item record for row ID %1$s.  Error message: %2$s', 'reg-man-rc' );
			$msg = sprintf( $format, $row_id, $wpdb->last_error );
			Error_Log::log_msg( $msg );
		} // endif
		
		return $result;
		
	} // function
	
	private static function update_row_values( $row_id, $values_array ) {
		global $wpdb;
		
//		Error_Log::var_dump( $row_id, $values_array );

		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		
		$types = array_fill( 0, count( $values_array ), '%s' );
		$where = array( 'id' => $row_id );
		$where_format = array( '%s' );
		
		$result = $wpdb->update( $table, $values_array, $where, $types, $where_format );
		
		if ( $result === FALSE ) {
			/* Translators: %1$s is a database table row ID, %2$s is an error message from the WordPress database ($wpdb) object */
			$format = __( 'Unable to update supplemental item record for row ID: %1$s.  Error message: %2$s', 'reg-man-rc' );
			$msg = sprintf( $format, $row_id, $wpdb->last_error );
			Error_Log::log_msg( $msg );
		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Perform the necessary steps for this class when the plugin is activated.
	 * For this class this means conditionally creating its database table using dbDelta().
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_activation() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Items table
		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(256) DEFAULT NULL,
			fixer_station_id bigint(20) unsigned DEFAULT 0,
			item_type_id bigint(20) unsigned DEFAULT 0,
			fixed_count bigint(20) unsigned DEFAULT 0,
			repairable_count bigint(20) unsigned DEFAULT 0,
			eol_count bigint(20) unsigned DEFAULT 0,
			unreported_count bigint(20) unsigned DEFAULT 0,
			PRIMARY KEY	(id)
		) $charset_collate;";
		dbDelta( $sql );

	} // function

	/**
	 * Perform the necessary steps for this class when the plugin is uninstalled.
	 * For this class this means removing its table.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		global $wpdb;

		// Items table
		$table = $wpdb->prefix . self::SUPPLEMENTAL_ITEMS_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

	} // function


	/**
	 * Get the ID of this item
	 * @return	string	The ID of this item
	 * @since	v0.1.0
	 */
	public function get_item_id() {
		return '';
	} // function

	/**
	 * Get the description of this item
	 * @return	string	The description of this item
	 * @since	v0.1.0
	 */
	public function get_item_description() {
		return '';
	} // function

	/**
	 * Get the key for the event for which this item was registered
	 * @return	string	The event key for the event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event_key_string() {
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
	 * Get the most descriptive name available to the current user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_display_name() {
		return '';
	} // function
	
	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name() {
		return '';
	} // function

	/**
	 * Get the public name of the visitor who registered the item.
	 * This is a name for the visitor that can be used in public like first name and last initial.
	 * @return	string		The visitor's public name.
	 * @since	v0.1.0
	 */
	public function get_visitor_public_name() {
		return '';
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
		return __( 'supplemental', 'reg-man-rc' );
	} // function


} // class