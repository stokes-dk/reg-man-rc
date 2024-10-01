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
		
//	Error_Log::var_dump( $event_keys_array );
		if ( is_array( $event_keys_array ) && count( $event_keys_array ) > 0 ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where_clause = "( event_key IN ( $placehold_string ) )";

			$query = "SELECT $cols FROM $table WHERE $where_clause";
			$query = $wpdb->prepare( $query, $event_keys_array );

		} else {
			
			$query = "SELECT $cols FROM $table";
			
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
		
		if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) > 0 ) ) {

			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where_clause = "( event_key IN ( $placehold_string ) )";

			// Get only records for fixers or non-fixers when grouping that way
			if ( $group_by == Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION ||
				 $group_by == Volunteer_Stats_Collection::GROUP_BY_TOTAL_FIXERS ) {
				$where_clause .= ' AND station_id IS NOT NULL';
			} elseif (  $group_by == Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE ||
						$group_by == Volunteer_Stats_Collection::GROUP_BY_TOTAL_NON_FIXERS ) {
				$where_clause .= ' AND role_id IS NOT NULL';
			} // endif

			$query = "SELECT $cols FROM $table WHERE $where_clause GROUP BY name";
//	Error_Log::var_dump( $query );
			$query = $wpdb->prepare( $query, $event_keys_array );
			
		} else {
			
			$query = "SELECT $cols FROM $table GROUP BY name";
			
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
		
		$where_parts_array = array();
		$where_args_array = array();

		if ( ! empty( $min_key_date_string ) ) {
			$where_parts_array[] = ' ( event_key >= %s ) ';
			$where_args_array[] = $min_key_date_string;
		} // endif
		
		if ( ! empty( $max_key_date_string ) ) {
			$where_parts_array[] = ' ( event_key <= %s ) ';
			$where_args_array[] = $max_key_date_string;
		} // endif
		
		if ( ! empty( $where_parts_array ) ) {
			$where_clause = ' WHERE ( ' . implode( ' AND ', $where_parts_array ) . ' ) ';
		} else {
			$where_clause = '';
		} // endif
		
		$query = "SELECT DISTINCT event_key FROM $table $where_clause";
//	Error_Log::var_dump( $query, $where_args_array );
		
		if ( count( $where_args_array ) > 0 )  {
			$query = $wpdb->prepare( $query, $where_args_array );
		} // endif
		$data_array = $wpdb->get_results( $query, OBJECT );

		foreach ( $data_array as $reg_data ) {
			$result[] = $reg_data->event_key;
		} // endif
		
//	Error_Log::var_dump( $result );
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
	 * Get the most descriptive name available to this user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return string
	 */
	public function get_volunteer_display_name() {
		return '';
	} // function

	/**
	 * Get the volunteer's name as a single string.
	 * To protect the volunteer's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_full_name() {
		return '';
	} // function

	/**
	 * Get the volunteer's public name.
	 * To protect the volunteer's privacy this name is the one shown in public and should be something like
	 * the volunteer's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_volunteer_public_name() {
		return '';
	} // function

	/**
	 * Get the key for the event that the volunteer has registered for
	 * @return	string|NULL		The key for the event for this volunteer registration
	 * @since	v0.1.0
	 */
	public function get_event_key_string() {
		return $this->event_key_string;
	} // function

	/**
	 * Get the volunteer's email, if supplied.
	 * To protect the volunteer's privacy their email is never shown in public.
	 * The email is used only to identify returning volunteers and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
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
	 * Get the name fixer station the volunteer has requested as her preferred station
	 *
	 * @return	string	The name of the fixer station the volunteer requested as her preferred station
	 * 	or NULL if no fixer station was requested by the fixer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_fixer_station_name() {
		return NULL;
	} // function

	/**
	 * Get the name fixer station the volunteer has been assigned to for this event
	 *
	 * @return	string	The name of the fixer station the volunteer has been assigned to for this event
	 * 	or NULL if no fixer station has been assigned
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_fixer_station_name() {
		return $this->assigned_fixer_station;
	} // function

	/**
	 * Get a boolean indicating if the volunteer has asked to participate as an apprentice fixer at the event
	 * @return	boolean|NULL	TRUE if the volunteer has asked to be an apprentice, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_fixer_apprentice() {
		return $this->is_fixer_apprentice;
	} // function

	/**
	 * Get the array of names of volunteer roles the volunteer has offered to perform for this event
	 *
	 * @return	string	The array of strings representing the preferred volunteer roles for this event
	 *	or NULL if no volunteer roles were requested by the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_preferred_volunteer_role_names_array() {
		return $this->NULL;
	} // function

	/**
	 * Get the array of names of volunteer roles the volunteer has been assigned to perform for this event
	 *
	 * @return	string	The array of strings representing the roles assigned to this volunteer for this event
	 *	or NULL if no volunteer roles were assigned to the volunteer
	 *
	 * @since v0.1.0
	 */
	public function get_assigned_volunteer_role_names_array() {
		return $this->assigned_volunteer_roles_array;
	} // function

	/**
	 * Get the comments supplied by the fixer or volunteer for this registration.
	 * @return	string		Any comments supplied by the volunteer during registration
	 * @since	v0.1.0
	 */
	public function get_volunteer_registration_comments() {
		return NULL;
	} // function

	/**
	 * Get a boolean indicating whether the volunteer attended the event
	 *
	 * @return	boolean		TRUE if the volunteer attended the event, FALSE if the volunteer DID NOT attend,
	 * 	or NULL if it is not known whether the volunteer attended
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_attendance() {
		return TRUE;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_registration_descriptor_source() {
		return __( 'supplemental', 'reg-man-rc' );
	} // function

} // class