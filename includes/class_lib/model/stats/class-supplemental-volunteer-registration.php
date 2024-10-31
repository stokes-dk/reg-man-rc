<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;

/**
 * Describes a supplemental registration record for a volunteer for an event
 *
 * An instance of this class contains the information related to a supplemental volunteer registration for an event.
 *
 * @since v0.1.0
 *
 */
class Supplemental_Volunteer_Registration implements Volunteer_Registration_Descriptor {

	const SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME		= 'reg_man_rc_sup_volunteers';

	private $event_key_string;
	private $assigned_fixer_station;
	private $is_fixer_apprentice;
	private $assigned_volunteer_roles_array;

	private static $VALID_VOLUNTEER_ROLE_IDS_LIST; // a string containing a list of valid volunteer roles (used in SQL queries)
	private static $VALID_FIXER_STATION_IDS_LIST; // a string containing a list of valid fixer stations (used in SQL queries)
	
	private function __construct() {
	} // function
	
	/**
	 * Get all the supplemental volunteer registrations (fixers and non-fixer volunteers)
	 *  for volunteers at any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing volunteer registrations for
	 *  the supplemental records stored in the database.
	 *
	 * @param	string[]		$event_keys_array	An array of keys for events whose volunteer registrations are to be returned.
	 * @return	Volunteer_Registration_Descriptor[]
	 */
	public static function get_all_supplemental_volunteer_registrations( $event_keys_array ) {

		global $wpdb;
		$result = array();
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		$cols = 'id, event_key, role_id, station_id, head_count, apprentice_count ';
		
		// Only use valid role IDs and fixer station IDs
		$role_ids_list = self::get_valid_volunteer_role_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "( role_id IS NULL OR role_id IN ( $role_ids_list ) ) AND ( station_id IS NULL OR station_id IN ( $stations_ids_list ) )";
		
		//	Error_Log::var_dump( $event_keys_array );
		if ( is_array( $event_keys_array ) && count( $event_keys_array ) > 0 ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where .= " AND ( event_key IN ( $placehold_string ) )";

			$query = "SELECT $cols FROM $table WHERE $where";
			$query = $wpdb->prepare( $query, $event_keys_array );

		} else {
			
			$query = "SELECT $cols FROM $table WHERE $where";
			
		} // endif

		$desc_data_arrays = $wpdb->get_results( $query, ARRAY_A );
//	Error_Log::var_dump( $query, $desc_data_arrays );

		foreach ( $desc_data_arrays as $data_array ) {
			$inst_array = self::create_instance_array_from_data_array( $data_array );
			if ( ! empty( $inst_array ) ) {
				$result = array_merge( $result, $inst_array );
			} // endif
		} // endfor

		return $result;

	} // function

	private static function get_valid_volunteer_role_ids_list() {
		if ( ! isset( self::$VALID_VOLUNTEER_ROLE_IDS_LIST ) ) {

			$role_ids = Volunteer_Role::get_all_term_taxonomy_ids();
			$role_ids[] = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID;
			self::$VALID_VOLUNTEER_ROLE_IDS_LIST = implode( ',', $role_ids );
		
		} // endif
		return self::$VALID_VOLUNTEER_ROLE_IDS_LIST;
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
	 * This method will return an array of instances of this class each describing one volunteer or fixer registration for an event.
	 * Note that each record in the database table specifies a number of people who worked at a role or station for an event
	 *  but didn't register through the system in advance.
	 * For example, "We had 5 bike fixers at event X who did not register in through the system".
	 * That row in the dtabase table will correspond to 5 instances of this class.
	 *
	 * @since v0.1.0
	 * @param	string[]	$data_array	{
	 * 		An associative array of strings conaining the supplemental volunteer registration record
	 *
	 * 		@type	string	'event_key'					The key for the event
	 * 		@type	string	'role_id'					The ID for the volunteer role
	 * 		@type	string	'station_id'				The ID for the fixer station
	 * 		@type	string	'head_count'				The number of volunteers registered to perform this role or station
	 * 		@type	string	'apprentice_count'			The number of apprentices registered for this station (does not apply to roles)
	 * }
	 * @return	Supplemental_Volunteer_Registration[]	An array of instances of this class.
	 */
	private static function create_instance_array_from_data_array( $data_array ) {

//		Error_Log::var_dump( $data_array );

		$result = array();
		$event_key	= isset( $data_array[ 'event_key' ] )			? $data_array[ 'event_key' ]		: NULL;
		$role_id	= isset( $data_array[ 'role_id' ] )				? $data_array[ 'role_id' ]			: NULL;
		$station_id	= isset( $data_array[ 'station_id' ] )			? $data_array[ 'station_id' ]		: NULL;
		$head_count	= isset( $data_array[ 'head_count' ] )			? intval( $data_array[ 'head_count' ] )			: 0;
		$appr_count	= isset( $data_array[ 'apprentice_count' ] )	? intval( $data_array[ 'apprentice_count' ] )	: 0;

//		Error_Log::var_dump( $event_key, $role_id, $station_id, $head_count, $appr_count );

		$role = isset( $role_id ) ? Volunteer_Role::get_volunteer_role_by_id( $role_id ) : NULL;
		$role_name_array = isset( $role ) ? array( $role->get_name() ) : array();
		$station = isset( $station_id ) ? Fixer_Station::get_fixer_station_by_id( $station_id ) : NULL;
		$station_name = isset( $station ) ? $station->get_name() : NULL;
		
		$non_appr_count = $head_count - $appr_count;

		// Non-apprentices
		if ( $non_appr_count > 0 ) {
			for ( $index = 0; $index < $non_appr_count; $index++  ) {
				$curr = new self();
				$curr->event_key_string = $event_key;
				$curr->assigned_fixer_station = $station_name;
				$curr->assigned_volunteer_roles_array = $role_name_array;
				$curr->is_fixer_apprentice = FALSE;
				$result[] = $curr;
			} // endfor
		} // endif

		// Apprentices
		if ( $appr_count > 0 ) {
			for ( $index = 0; $index < $appr_count; $index++ ) {
				$curr = new self();
				$curr->event_key_string = $event_key;
				$curr->assigned_fixer_station = $station_name;
				$curr->assigned_volunteer_roles_array = $role_name_array;
				$curr->is_fixer_apprentice = TRUE;
				$result[] = $curr;
			} // endfor
		} // endif

//		Error_Log::var_dump( $result );
		return $result;
	} // function


	/**
	 * Get the supplemental volunteer group stats for the specified events and grouped in the specified way
	 * @param	Event_Key[]		$event_keys_array	An array of event keys whose group stats are to be returned
	 * @param	string			$group_by			One of the "GROUP_BY" constants from Volunteer_Stats_Collection
	 * @return Volunteer_Stats[]	An array of instances of Volunteer_Stats describing the volunteers and their related head counts.
	 */
	public static function get_supplemental_group_stats_array( $event_keys_array, $group_by ) {

		global $wpdb;
		$result = array(); // Start with an empty set and then add to it
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		
		switch( $group_by ) {

			case Volunteer_Stats_Collection::GROUP_BY_EVENT:
				$name_col = 'event_key';
				break;

			case Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE:
				$name_col = 'role_id';
				break;

			case Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION:
				$name_col = 'station_id';
				break;

			case Volunteer_Stats_Collection::GROUP_BY_TOTAL_FIXERS:
			case Volunteer_Stats_Collection::GROUP_BY_TOTAL_NON_FIXERS:
			case Volunteer_Stats_Collection::GROUP_BY_TOTAL:
			default:
				$name_col = "''"; // must be a quoted string for SQL otherwise illegal column name
				break;

		} // endswitch
		
		$cols = "$name_col as name, " .
				'SUM( head_count ) as head_count, ' .
				'SUM( apprentice_count ) as apprentice_count ';
		
		// Only use valid role IDs and fixer station IDs
		$role_ids_list = self::get_valid_volunteer_role_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "( role_id IS NULL OR role_id IN ( $role_ids_list ) ) AND ( station_id IS NULL OR station_id IN ( $stations_ids_list ) )";
		
		if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) > 0 ) ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where .= " AND ( event_key IN ( $placehold_string ) )";

			// Get only records for fixers or non-fixers when grouping that way
			if ( $group_by == Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION ||
				 $group_by == Volunteer_Stats_Collection::GROUP_BY_TOTAL_FIXERS ) {
				$where .= ' AND station_id IS NOT NULL';
			} elseif (  $group_by == Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE ||
						$group_by == Volunteer_Stats_Collection::GROUP_BY_TOTAL_NON_FIXERS ) {
				$where .= ' AND role_id IS NOT NULL';
			} // endif

			$query = "SELECT $cols FROM $table WHERE $where GROUP BY name";
//	Error_Log::var_dump( $query );
			$query = $wpdb->prepare( $query, $event_keys_array );
			
		} else {
			
			$query = "SELECT $cols FROM $table WHERE $where GROUP BY name";
			
		} // endif
			
		$data_array = $wpdb->get_results( $query, OBJECT_K );
//	Error_Log::var_dump( $query, $data_array );

		foreach( $data_array as $name => $obj ) {
			$result[ $name ] = Volunteer_Stats::create( $name, $obj->head_count, $obj->apprentice_count );
		} // endfor

		return $result;
			
	} // function

	/**
	 * Set the head count for fixers of the specified station for this event
	 * @param	int|string	$fixer_station_id		The fixer station whose count is to be assigned
	 * @param	int|string	$head_count				The count of fixers for the specified station for the event
	 * @since	v0.1.0
	 */
	public static function set_supplemental_fixer_count( $event_key, $fixer_station_id, $fixer_count, $appr_count ) {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;

		$fixer_count = max( 0, intval( $fixer_count ) ); // It must be a positive integer
		$appr_count = max( 0, intval( $appr_count ) ); // It must be a positive integer
		$head_count = $fixer_count + $appr_count;

		$query = "SELECT id FROM $table WHERE event_key=%s AND station_id=%s LIMIT 1";
		$stmt = $wpdb->prepare( $query, $event_key, $fixer_station_id );
		$obj = $wpdb->get_row( $stmt, OBJECT );
		$existing_id = ( isset( $obj ) && isset( $obj->id ) ) ? $obj->id : NULL;

		if ( empty( $head_count ) ) {
			// If the value is 0 then we should have no record at all so remove it if one exists
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
			$vals = array(
				'head_count'		=> $head_count,
				'apprentice_count'	=> $appr_count,
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
				$vals[ 'station_id' ]	= $fixer_station_id;
				$vals[ 'event_key' ]	= $event_key;
				$types[] = '%s';
				$insert_result = $wpdb->insert( $table, $vals, $types );
				$result = ( $insert_result == 1 ) ? TRUE : FALSE;
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Set the head count for non-fixers of the specified role for this event
	 * @param	int|string	$volunteer_role_id		The volunteer role whose count is to be assigned
	 * @param	int|string	$head_count				The count of non-fixers for the specified role for the event
	 * @since	v0.1.0
	 */
	public static function set_supplemental_non_fixer_count( $event_key, $volunteer_role_id, $head_count ) {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;

		$head_count = max( 0, intval( $head_count ) ); // It must be a positive integer,

		$query = "SELECT id FROM $table WHERE event_key=%s AND role_id=%s LIMIT 1";
		$stmt = $wpdb->prepare( $query, $event_key, $volunteer_role_id );
		$obj = $wpdb->get_row( $stmt, OBJECT );
		$existing_id = ( isset( $obj ) && isset( $obj->id ) ) ? $obj->id : NULL;

		if ( empty( $head_count ) ) {
			// If the value is 0 then we should have no record at all so remove it if one exists
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
			$vals = array(
				'head_count'		=> $head_count,
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
				$vals[ 'role_id' ]		= $volunteer_role_id;
				$vals[ 'event_key' ]	= $event_key;
				$types[] = '%s';
				$insert_result = $wpdb->insert( $table, $vals, $types );
				$result = ( $insert_result == 1 ) ? TRUE : FALSE;
			} // endif
		} // endif
		return $result;
	} // function

	
	/**
	 * Get an array of event key for supplemental volunteers registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_key_strings_for_volunteer_registrations_in_date_range( $min_key_date_string, $max_key_date_string ) {
		
		global $wpdb;
		
		$result = array();

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		
		// Only use valid role IDs and fixer station IDs
		$role_ids_list = self::get_valid_volunteer_role_ids_list();
		$stations_ids_list = self::get_valid_fixer_station_ids_list();

		$where = "( role_id IS NULL OR role_id IN ( $role_ids_list ) ) AND ( station_id IS NULL OR station_id IN ( $stations_ids_list ) )";

		$event_where_parts_array = array();
		$event_where_args_array = array();

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
//	Error_Log::var_dump( $query, $event_where_args_array );
		
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
	 * When a volunteer role is deleted we need to remove references to it from our table.
	 * @param	int	$volunteer_role_id
	 * @since	v0.9.9
	 */
	public static function handle_volunteer_role_deleted( $volunteer_role_id ) {
		
		self::handle_term_deleted( $volunteer_role_id, 'role_id' );

	} // function
	
	/**
	 * When a fixer station is deleted we need to remove references to it from our table.
	 * @param	int	$fixer_station_id
	 * @since	v0.9.9
	 */
	public static function handle_fixer_station_deleted( $fixer_station_id ) {
		
		self::handle_term_deleted( $fixer_station_id, 'station_id' );

	} // function
	
	/**
	 * When a volunteer role or fixer station is deleted we need to remove references to it from our table.
	 * We need to find all the rows in the table using this deleted (now invalid) term ID
	 *  and apply those totals to the UNSPECIFIED count for the event
	 * @param	int		$invalid_term_id	The ID of the term (item_type or fixer_station) that has been deleted
	 * @param	string	$invalid_column		The name of the table column containing the deleted ID, either 'role_id' or 'station_id'
	 * @since	v0.9.9
	 */
	private static function handle_term_deleted( $invalid_term_id, $invalid_column ) {
		global $wpdb;
		
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		$cols = 'id, event_key, role_id, station_id, head_count, apprentice_count';

		$is_invalid_role_id = $invalid_column === 'role_id'; // which column has the invalid value?
		
		// Find all the rows using this item type
		$invalid_rows_query = "SELECT $cols FROM $table WHERE $invalid_column=%s";
		$invalid_rows_stmt = $wpdb->prepare( $invalid_rows_query, $invalid_term_id );

		$invalid_rows_array = $wpdb->get_results( $invalid_rows_stmt, OBJECT_K );
//		Error_Log::var_dump( $invalid_rows_query, $invalid_rows_array );

		if ( $is_invalid_role_id ) {
			$existing_unspecified_row_query = "SELECT $cols FROM $table WHERE event_key=%s AND role_id=%s AND station_id IS NULL LIMIT 1";
		} else {
			$existing_unspecified_row_query = "SELECT $cols FROM $table WHERE event_key=%s AND role_id IS NULL AND station_id=%s LIMIT 1";
		} // endif
		
		// In reality, this is '0' either way but let's assume it could change
		$unspecified_value = $is_invalid_role_id ? Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
		
		// For each row using this invalid ID
		//  look the row with the same event and valid volunteer role or fixer station but '0' for the invalid column
		foreach( $invalid_rows_array as $invalid_row_id => $invalid_row ) {
			
			$event_key = $invalid_row->event_key;
			$existing_unspecified_row_stmt = $wpdb->prepare( $existing_unspecified_row_query, $event_key, $unspecified_value );
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
				$head_count =		intval( $existing_unspecified_row_obj->head_count )			+ intval( $invalid_row->head_count );
				$apprentice_count =	intval( $existing_unspecified_row_obj->apprentice_count )	+ intval( $invalid_row->apprentice_count );

				$values_array = array(
						'head_count'		=> $head_count,
						'apprentice_count'	=> $apprentice_count,
				);
				self::update_row_values( $existing_unspecified_row_id, $values_array );
				
				self::delete_row( $invalid_row_id );
				
			} // endif
			
		} // endfor
		
	} // function
	
	private static function delete_row( $row_id ) {
		global $wpdb;
		
//		Error_Log::var_dump( $row_id );
		
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		$where = array( 'id' => $row_id );
		
		$result = $wpdb->delete( $table, $where );
		
		if ( $result === FALSE ) {
			/* Translators: %1$s is a database table row ID, %2$s is an error message from the WordPress database ($wpdb) object */
			$format = __( 'Unable to delete supplemental volunteer record for row ID %1$s.  Error message: %2$s', 'reg-man-rc' );
			$msg = sprintf( $format, $row_id, $wpdb->last_error );
			Error_Log::log_msg( $msg );
		} // endif
		
		return $result;
		
	} // function
	
	private static function update_row_values( $row_id, $values_array ) {
		global $wpdb;
		
//		Error_Log::var_dump( $row_id, $values_array );

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		
		$types = array_fill( 0, count( $values_array ), '%s' );
		$where = array( 'id' => $row_id );
		$where_format = array( '%s' );
		
		$result = $wpdb->update( $table, $values_array, $where, $types, $where_format );
		
		if ( $result === FALSE ) {
			/* Translators: %1$s is a database table row ID, %2$s is an error message from the WordPress database ($wpdb) object */
			$format = __( 'Unable to update supplemental volunteer record for row ID: %1$s.  Error message: %2$s', 'reg-man-rc' );
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

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(256) DEFAULT NULL,
			role_id bigint(20) unsigned DEFAULT NULL,
			station_id bigint(20) unsigned DEFAULT NULL,
			head_count bigint(20) unsigned DEFAULT 0,
			apprentice_count bigint(20) unsigned DEFAULT 0,
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
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VOLUNTEERS_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

	} // function


// The following methods provide the implementation for Volunteer_Registration_Descriptor

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_registration_id()
	 */
	public function get_volunteer_registration_id() {
		return NULL;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_display_name()
	 */
	public function get_volunteer_display_name() {
		return '';
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_full_name()
	 */
	public function get_volunteer_full_name() {
		return '';
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_public_name()
	 */
	public function get_volunteer_public_name() {
		return '';
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_event_key_string()
	 */
	public function get_event_key_string() {
		return $this->event_key_string;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_email()
	 */
	public function get_volunteer_email() {
		return NULL;
	} // function

	/**
	 * Get the event for which this volunteer has registered
	 * @return	Event	The event for which this volunteer has registered.
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
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_preferred_fixer_station_name()
	 */
	public function get_preferred_fixer_station_name() {
		return NULL;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_assigned_fixer_station_name()
	 */
	public function get_assigned_fixer_station_name() {
		return $this->assigned_fixer_station;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_is_fixer_apprentice()
	 */
	public function get_is_fixer_apprentice() {
		return $this->is_fixer_apprentice;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_preferred_volunteer_role_names_array()
	 */
	public function get_preferred_volunteer_role_names_array() {
		return $this->NULL;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_assigned_volunteer_role_names_array()
	 */
	public function get_assigned_volunteer_role_names_array() {
		return $this->assigned_volunteer_roles_array;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_registration_comments()
	 */
	public function get_volunteer_registration_comments() {
		return NULL;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_attendance()
	 */
	public function get_volunteer_attendance() {
		return TRUE;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor::get_volunteer_registration_descriptor_source()
	 */
	public function get_volunteer_registration_descriptor_source() {
		return __( 'supplemental', 'reg-man-rc' );
	} // function

} // class