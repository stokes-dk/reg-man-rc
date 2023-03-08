<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * Describes a supplemental registration record for a volunteer for an event
 *
 * An instance of this class contains the information related to a supplemental visitor record for an event.
 *
 * @since v0.1.0
 *
 */
class Supplemental_Visitor_Registration implements Visitor_Registration_Descriptor {

	const SUPPLEMENTAL_VISITOR_REG_TABLE_NAME		= 'reg_man_rc_sup_visitors';

	private $event_key;
	private $is_first_event;

	/**
	 * Get all the supplemental visitors registered to any event in the specified event key array
	 *
	 * This method will return an array of instances of this class describing visitors
	 *  for the supplemental records stored in the database.
	 *
	 * @param	Event_Key[]		$event_keys_array	An array of Event_Key objects whose visitors are to be returned.
	 * @return Visitor_Registration_Descriptor[]
	 */
	public static function get_all_supplemental_visitor_registrations( $event_keys_array ) {

		$result = array();
		if ( is_array( $event_keys_array ) && count( $event_keys_array ) > 0 ) {
			global $wpdb;
			$table = $wpdb->prefix . self::SUPPLEMENTAL_VISITOR_REG_TABLE_NAME;

			$cols = 'id, event_key, first_time_count, returning_count, unreported_count ';
			$placeholder_array = array_fill( 0, count( $event_keys_array ), '%s' );
			$placehold_string = implode( ', ', $placeholder_array );
			$where_clause = "( event_key IN ( $placehold_string ) )";

			$query = "SELECT $cols FROM $table WHERE $where_clause";
			$stmt = $wpdb->prepare( $query, $event_keys_array );
			$desc_data_arrays = $wpdb->get_results( $stmt, ARRAY_A );
	//Error_Log::var_dump( $query, $desc_data_arrays );

			foreach ( $desc_data_arrays as $data_array ) {
				$inst_array = self::create_instance_array_from_data_array( $data_array );
				if ( ! empty( $inst_array ) ) {
					$result = array_merge( $result, $inst_array );
				} // endif
			} // endfor
		} // endif
		return $result;
	} // function

	/**
	 * Instantiate this class using the data array provided.
	 *
	 * This method will return an array of instances of this class each describing one visitor for an event.
	 * Note that each record in the database table specifies a number of visitors at an event
	 *  who didn't register through the system.
	 * For example, "We had 2 first time and 1 return visitor at event X who did not register in through the system".
	 * That row in the dtabase table will correspond to 3 instances of this class.
	 *
	 * @since v0.1.0
	 * @param	string[]	$data_array	{
	 * 		An associative array of strings conaining the supplemental volunteer registration record
	 *
	 * 		@type	string	'event_key'					The key for the event
	 * 		@type	string	'first_time_count'			The number of first time visitors at the event
	 * 		@type	string	'returning_count'			The number of returning visitors
	 * 		@type	string	'unreported_count'			The number of visitors whose first-time/returning status is not known
	 * }
	 * @return	\Reg_Man_RC\Model\Supplemental_Visitor[]		An array of instances of this class.
	 */
	private static function create_instance_array_from_data_array( $data_array ) {

//		Error_Log::var_dump( $data_array );

		$result = array();
		$event_key		= isset( $data_array[ 'event_key' ] )			? $data_array[ 'event_key' ]		: NULL;
		$first_count	= isset( $data_array[ 'first_time_count' ] )	? intval( $data_array[ 'first_time_count' ] )	: 0;
		$return_count	= isset( $data_array[ 'returning_count' ] )		? intval( $data_array[ 'returning_count' ] )	: 0;
		$unknown_count	= isset( $data_array[ 'unreported_count' ] )	? intval( $data_array[ 'unreported_count' ] )	: 0;

//		Error_Log::var_dump( $event_key, $first_count, $return_count, $unknown_count );

		// First time visitors
		if ( $first_count > 0 ) {
			for ( $index = 0; $index < $first_count; $index++  ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->is_first_event = TRUE;
				$result[] = $curr;
			} // endfor
		} // endif

		// Returning
		if ( $return_count > 0 ) {
			for ( $index = 0; $index < $return_count; $index++ ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->is_first_event = FALSE;
				$result[] = $curr;
			} // endfor
		} // endif

		// Unknown
		if ( $unknown_count > 0 ) {
			for ( $index = 0; $index < $unknown_count; $index++ ) {
				$curr = new self();
				$curr->event_key = $event_key;
				$curr->is_first_event = NULL;
				$result[] = $curr;
			} // endfor
		} // endif

		//		Error_Log::var_dump( $result );
		return $result;
	} // function


	/**
	 * Get the supplemental visitor group stats for the specified events and grouped in the specified way
	 * @param	Event_Key[]		$event_key_array	An array of event keys whose group stats are to be returned
	 * @param	string			$group_by			One of the "GROUP_BY" constants from Visitor_Statistics
	 * @return	Volunteer_Group_Stats[]	An array of instances of Volunteer_Group_Stats describing the volunteers and their related head counts.
	 */
	public static function get_supplemental_group_stats_array( $event_key_array, $group_by ) {

		$result = array(); // Start with an empty set and then add to it
		if ( is_array( $event_key_array) && ( count( $event_key_array ) > 0 ) ) {

			global $wpdb;
			$result = array();
			$table = $wpdb->prefix . self::SUPPLEMENTAL_VISITOR_REG_TABLE_NAME;
			switch ( $group_by ) {
				case Visitor_Statistics::GROUP_BY_EVENT:
					$name_col = 'event_key';
					break;
				default:
					$name_col = "''";
					break;
			} // endswitch
			$cols = "$name_col AS name, " .
					'COALESCE( first_time_count, 0 ) AS first_count, ' . // Convert NULL into 0
					'COALESCE( returning_count, 0 ) AS return_count, ' .
					'COALESCE( unreported_count,  0 ) AS unrep_count ';

			if ( empty( $event_key_array ) ) {
				$where_clause = '1'; // get everything
			} else {
				$placeholder_array = array_fill( 0, count( $event_key_array ), '%s' );
				$placeholders = implode( ',', $placeholder_array );
				$where_clause = "event_key IN ( $placeholders )";
			} // endif

			$query = "SELECT $cols FROM $table WHERE $where_clause GROUP BY name";
			$stmt = $wpdb->prepare( $query, $event_key_array );
			$data_array = $wpdb->get_results( $stmt, ARRAY_A );
//Error_Log::var_dump( $query, $event_key_array, $data_array );

			if ( is_array( $data_array ) ) {
				$email_count = 0; // We have no info about email addresses
				$join_count = 0; //  or joining the mailing list
				foreach ( $data_array as $data ) {
					$name			= isset( $data[ 'name' ] )			? $data[ 'name' ]			: ''; //$em_dash;
					$visitor_count	= isset( $data[ 'visitor_count' ] )	? $data[ 'visitor_count' ] 	: 0;
					$first_count	= isset( $data[ 'first_count' ] )	? $data[ 'first_count' ] 	: 0;
					$return_count	= isset( $data[ 'return_count' ] )	? $data[ 'return_count' ] 	: 0;
					$unknown_count	= isset( $data[ 'unrep_count' ] )	? $data[ 'unrep_count' ]	: 0;
					$instance = Visitor_Group_Stats::create( $name, $first_count, $return_count, $unknown_count, $email_count, $join_count );
					$result[ $name ] = $instance;
				} // endfor
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Set the supplemental visitor registration counts for this event
	 * @param	int		$first_time_count	The count of first-time visitors for the event
	 * @since	v0.1.0
	 */
	public static function set_supplemental_visitor_reg_counts( $event_key, $first_time_count, $returning_count, $unreported_count ) {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VISITOR_REG_TABLE_NAME;

		$first_time_count	= max( 0, intval( $first_time_count ) ); // It must be a positive integer,
		$returning_count	= max( 0, intval( $returning_count ) );
		$unreported_count	= max( 0, intval( $unreported_count ) );

		$query = "SELECT id FROM $table WHERE event_key=%s LIMIT 1";
		$stmt = $wpdb->prepare( $query, $event_key );
		$obj = $wpdb->get_row( $stmt, OBJECT );
		$existing_id = ( isset( $obj ) && isset( $obj->id ) ) ? $obj->id : NULL;

		if ( empty( $first_time_count ) && empty( $returning_count ) && empty( $unreported_count) ) {
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
			// We have to either update an existing record or insert a new one
			$vals = array(
				'first_time_count'		=> max( 0, intval( $first_time_count ) ), // It must be a positive integer,
				'returning_count'		=> max( 0, intval( $returning_count ) ),
				'unreported_count'		=> max( 0, intval( $unreported_count ) ),
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
				$vals[ 'event_key' ] = $event_key;
				$types[] = '%s';
				$insert_result = $wpdb->insert( $table, $vals, $types );
				$result = ( $insert_result == 1 ) ? TRUE : FALSE;
			} // endif
		} // endif

		return $result;
	} // function

	/**
	 * Perform the necessary steps for this class when the plugin is activated.
	 * For this class this means conditionally creating its database tables using dbDelta().
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_activation() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Visitors table
		$table = $wpdb->prefix . self::SUPPLEMENTAL_VISITOR_REG_TABLE_NAME;
		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(256) DEFAULT NULL,
			first_time_count bigint(20) unsigned DEFAULT 0,
			returning_count bigint(20) unsigned DEFAULT 0,
			unreported_count bigint(20) unsigned DEFAULT 0,
			PRIMARY KEY	(id)
		) $charset_collate;";
		dbDelta( $sql );

	} // function

	/**
	 * Perform the necessary steps for this class when the plugin is uninstalled.
	 * For this class this means removing its tables.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		global $wpdb;

		$table = $wpdb->prefix . self::SUPPLEMENTAL_VISITOR_REG_TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

	} // function




// The following methods provide the implementation for Visitor_Registration_Descriptor

	/**
	 * Get the key for the event that the visitor attended
	 * @return	string|NULL		The key for the event
	 * @since	v0.1.0
	 */
	public function get_event_key() {
		return $this->event_key;
	} // function


	/**
	 * Get the visitor's name as a single string.
	 * To protect the visitor's privacy their full name is never shown in public.
	 * The full name is used only if we are rendering the administrative interface.
	 *
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_full_name() {
		return '';
	} // function

	/**
	 * Get the visitor's public name.
	 * To protect the visitor's privacy this name is the one shown in public and should be something like
	 * the visitor's first name and last initial.
	 * @return	string
	 * @since	v0.1.0
	 */
	public function get_public_name() {
		return '';
	} // function

	/**
	 * Get a boolean indicating whether this is the first event the visitor has attended.
	 * @return	boolean|NULL	TRUE if it's the visitor's first event, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_first_event() {
		return $this->is_first_event;
	} // function

	/**
	 * Get the visitor's email, if supplied.
	 * To protect the visitor's privacy their email is never shown in public.
	 * The email is used only to identify returning visitors and show only if we are rendering the administrative interface.

	 * @return	string|NULL		The visitor's email address if it is known, NULL otherwise
	 * @since	v0.1.0
	 */
	public function get_email() {
		return NULL;
	} // function

	/**
	 * Get a boolean indicating if the visitor has asked to join the mailing list.
	 * @return	boolean|NULL	TRUE if it's the visitor wants to join the mailing list, FALSE if not, NULL if we don't know
	 * @since	v0.1.0
	 */
	public function get_is_join_mail_list() {
		return FALSE;
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_visitor_registration_descriptor_source() {
		return __( 'supplemental', 'reg-man-rc' );
	} // function


} // class