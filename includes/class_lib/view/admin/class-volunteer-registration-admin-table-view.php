<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Fixer_Station;

/**
 * The administrative view for an item stats table
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Registration_Admin_Table_View {

	private function __construct() { }

	public static function create() {
		$result = new self();
		return $result;
	} // function

// Name (email) | Event | Fixer Station (apprentice) | Volunteer Roles | Attendance | Source | ISO 8601 Date
	public function render() {
		$rowFormat =
			'<tr>' .
				'<%1$s class="name">%2$s</%1$s>' .
				'<%1$s class="event">%3$s</%1$s>' .
				'<%1$s class="fixer-station">%4$s</%1$s>' .
				'<%1$s class="volunteer-roles">%5$s</%1$s>' .
				'<%1$s class="attendance">%6$s</%1$s>' .
				'<%1$s class="source">%7$s</%1$s>' .
				'<%1$s class="iso-8601-date">%8$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_VOLUNTEER_REGISTRATIONS;
		$dom_setting = '';

		$title = __( 'Fixer / Volunteer Registrations', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view vol-reg-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table vol-reg-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
				echo '<thead>';
// Name (email) | Event | Fixer Station (apprentice) | Volunteer Roles | Attendance | Source | ISO 8601 Date
				printf( $rowFormat,
								'th',
								esc_html__( 'Volunteer Name (email)',			'reg-man-rc' ),
								esc_html__( 'Event',							'reg-man-rc' ),
								esc_html__( 'Fixer Station',					'reg-man-rc' ),
								esc_html__( 'Volunteer Roles',					'reg-man-rc' ),
								esc_html__( 'Attendance',						'reg-man-rc' ),
								esc_html__( 'Source',							'reg-man-rc' ),
								'' // The ISO 8601 date will be hidden and does not need a column header
						);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Get the table data array for the specified array of Volunteer_Registration_Descriptor objects
	 *
	 * @param	Volunteer_Registration_Descriptor[]	$vol_reg_desc_array	An array registrations to be shown in the table.
	 * whose data are to be shown in the table.
	 * @result	string[][]	An array of string arrays containing the table data for the specified stats.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_volunteer_registration_descriptors( $vol_reg_desc_array ) {
		$result = array();
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$source_unknown_text = __( '[source not specified]', 'reg-man-rc' );
		$events[] = array(); // There will be a huge amount of duplication in the events so just save the ones I find
// Name (email) | Event | Fixer Station (apprentice) | Volunteer Roles | Attendance | Source | ISO 8601 Date
		foreach( $vol_reg_desc_array as $vol_reg_desc ) {
			$name = $vol_reg_desc->get_volunteer_full_name();
			$email = $vol_reg_desc->get_volunteer_email();
			if ( ! empty( $email ) ) {
				/* Translators: %1$s is a person's name, %2$s is their email address */
				$name_text = sprintf( __( '%1$s (%2$s)', 'reg-man-rc' ), $name, $email );
			} else {
				if ( ! empty( $name ) ) {
					$name_text = $name;
				} else {
					$name_text = $em_dash;
				} // endif
			} // endif

			$event_key = $vol_reg_desc->get_event_key();
			$event_text = $event_key; // As a last resort we'll just show the key
			$event_date_iso_8601 = '';
			if ( ! empty( $event_key ) ) {
				if ( isset( $events[ $event_key ] ) ) {
					$event = $events[ $event_key ];
				} else {
					$event = Event::get_event_by_key( $event_key );
					if ( ! empty( $event ) ) {
						$events[ $event_key ] = $event;
					} // endif
				} // endif
				if ( isset( $event ) ) {
					$event_text = $event->get_label();
					$dt = $event->get_start_date_time_object();
					$event_date_iso_8601 = isset( $dt ) ? $dt->format( \DateTime::ISO8601 ) : '';
				} // endif
			} // endif

			$fixer_station = $vol_reg_desc->get_assigned_fixer_station_name();
			if ( ! empty( $fixer_station ) ) {
				if ( $vol_reg_desc->get_is_fixer_apprentice() ) {
					/* Translators: %1$s is a fixer station */
					$fixer_station_text = sprintf( __( '%1$s (apprentice)', 'reg-man-rc' ), $fixer_station );
				} else {
					$fixer_station_text = $fixer_station;
				} // endif
			} else {
				$fixer_station_text = $em_dash;
			} // endif

			$vol_roles = $vol_reg_desc->get_assigned_volunteer_role_names_array();
			if ( ! empty( $vol_roles ) ) {
				$roles_text = implode( ', ', $vol_roles );
			} else {
				$roles_text = $em_dash;
			} // endif

			$attendance = $vol_reg_desc->get_volunteer_attendance();
			if ( ! isset( $attendance ) ) {
				$attendance_text = $em_dash;
			} else {
				$attendance_text = $attendance ? __( 'Yes', 'reg-man-rc' ) : __( 'NO', 'reg-man-rc' );
			} // endif

			$source = $vol_reg_desc->get_volunteer_registration_descriptor_source();
			$source_text = isset( $source ) ? $source : $source_unknown_text;

			$row = array();
			$row[] = $name_text;
			$row[] = $event_text;
			$row[] = $fixer_station_text;
			$row[] = $roles_text;
			$row[] = $attendance_text;
			$row[] = $source_text;
			$row[] = $event_date_iso_8601;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class