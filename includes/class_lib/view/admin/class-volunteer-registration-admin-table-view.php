<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The administrative view for an item stats table
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Registration_Admin_Table_View {

	private $single_event; // The event object when showing data for a single event
	private $group_by;
	
	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @param	Event	$single_event
	 * @return Items_Admin_Table_View
	 */
	public static function create( $single_event = NULL ) {
		$result = new self();
		if ( ! empty( $single_event ) && $single_event instanceof Event ) {
			$result->single_event = $single_event;
//			$result->group_by = 'fixer-station';
			$result->group_by = ''; // Users tend to view this with no grouping
		} else {
			$result->group_by = '';
		} // endif
		return $result;
	} // function
	
	/**
	 * Get the Event object when this table is showing data for a single event
	 * @return Event
	 */
	private function get_single_event() {
		return $this->single_event;
	} // function
	
	private function get_single_event_key() {
		$single_event = $this->get_single_event();
		return ! empty( $single_event ) ? $single_event->get_key_string() : '';
	} // function
	
	private function get_is_event_column_hidden() {
		return ! empty( $this->get_single_event() );
	} // function
	
	private function get_print_page_title() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Volunteers - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Volunteers', 'reg-man-rc' );
		} // endif
		return $result;
	} // function

	private function get_export_file_name() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Volunteers - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Volunteers', 'reg-man-rc' );
		} // endif
		$result = sanitize_file_name( $result );
		return $result;
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		$single_event = $this->get_single_event();
		$event_col_class = $this->get_is_event_column_hidden() ? 'col-hidden' : '';
		// Name | ID | email | Event | ISO 8601 Date | Fixer Station (apprentice) | Volunteer Roles | Comments | Attendance | Source
		$rowFormat =
			'<tr>' .
				'<%1$s class="volunteer-display-name string-with-empty-placeholder">%2$s</%1$s>' .
				'<%1$s class="record-id always-hidden col-hidden col-not-searchable num-with-empty-placeholder">%3$s</%1$s>' .
				'<%1$s class="volunteer-email col-hidden">%4$s</%1$s>' .
				'<%1$s class="event-date-text ' . $event_col_class . '">%5$s</%1$s>' . // This column will be sorted by the next col's data
				'<%1$s class="event-date-iso-8601 col-hidden always-hidden col-not-searchable">%6$s</%1$s>' . // Must be after date
				'<%1$s class="fixer-station string-with-empty-placeholder">%7$s</%1$s>' .
				'<%1$s class="volunteer-roles string-with-empty-placeholder">%8$s</%1$s>' .
				'<%1$s class="volunteer-comments string-with-empty-placeholder">%9$s</%1$s>' .
				'<%1$s class="volunteer-attendance col-hidden string-with-empty-placeholder">%10$s</%1$s>' .
				'<%1$s class="volunteer-source string-with-empty-placeholder">%11$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$ajax_nonce = wp_create_nonce( $ajax_action );
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_VOLUNTEER_REGISTRATIONS;
		$group_by = $this->group_by;
		$dom_setting = '';
		$print_page_title = $this->get_print_page_title();
		$export_file_name = $this->get_export_file_name();
		
		$title = __( 'Fixer / Volunteer Registrations', 'reg-man-rc' );

		$group_by_title = __( 'Group by', 'reg-man-rc' );
		
		echo '<div class="reg-man-rc-stats-table-view vol-reg-table row-grouping-table-view event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
				echo '<div class="toolbar-container">';
					echo "<label><span>$group_by_title</span>";
						echo '<select class="group-by-select" autocomplete="off">';
						
							$option_format = '<option value="%2$s" %3$s data-order-by-column="%4$s">%1$s</option>';
						
							if ( empty( $single_event ) ) {
								
								// It's NOT a single event, so allow grouping by event
								$group_col_class = 'event-date-text';
								$order_col_class = 'event-date-iso-8601';
								$sel = selected( $group_by, $group_col_class, FALSE );
								$title = __( 'Event', 'reg-man-rc' );
								printf( $option_format, $title, $group_col_class, $sel, $order_col_class );
								
							} // endif

							$group_col_class = 'fixer-station';
							$order_col_class = $group_col_class;
							$sel = selected( $group_by, $group_col_class, FALSE );
							$title = __( 'Fixer Station', 'reg-man-rc' );
							printf( $option_format, $title, $group_col_class, $sel, $order_col_class );

							$group_col_class = 'volunteer-roles';
							$order_col_class = $group_col_class;
							$sel = selected( $group_by, $group_col_class, FALSE );
							$title = __( 'Volunteer Role', 'reg-man-rc' );
							printf( $option_format, $title, $group_col_class, $sel, $order_col_class );
							
							$group_col_class = '';
							$order_col_class = $group_col_class;
							$sel = selected( $group_by, $group_col_class, FALSE );
							$title = __( '[None]', 'reg-man-rc' );
							printf( $option_format, $title, $group_col_class, $sel, $order_col_class );
							
						echo '</select>';
					echo '</label>';
				echo '</div>';
				
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container admin-cpt-vol-reg-list-change-listener">';
			
				$data_array = array();
				$data_array[] = "data-ajax-url=\"$ajax_url\"";
				$data_array[] = "data-ajax-action=\"$ajax_action\"";
				$data_array[] = "data-ajax-nonce=\"$ajax_nonce\"";
				$data_array[] = "data-table-type=\"$object_type\"";
				if ( ! empty( $single_event ) ) {
					$single_event_key = $this->get_single_event_key();
					$data_array[] = "data-event-key=\"$single_event_key\"";
					$data_array[] = 'data-supplemental-data-button-class="supplemental-volunteers-button"';
					// Add Emails button if the current user has the authority
					if ( $single_event->get_is_current_user_able_to_view_registered_volunteer_emails() ) {
						$data_array[] = 'data-email-list-button-class="volunteer-reg-email-list-button"';
						$data_array[] = 'data-add-record-button-class="add-volunteer-reg-button"';
//						$data_array[] = 'data-update-record-button-class="update-volunteer-reg-button"';
//						$data_array[] = 'data-delete-record-button-class="delete-volunteer-reg-button"';
					} // endif
				} // endif
				$data_array[] = "data-print-page-title=\"$print_page_title\"";
				$data_array[] = "data-export-file-name=\"$export_file_name\"";
				$data_array[] = "data-scope=\"\"";
				$data_array[] = "data-dom-setting=\"$dom_setting\"";
				$data_array[] = "data-row-group-column-class-name=\"$group_by\"";
				
				$data = implode( ' ', $data_array );
				
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo "<table class=\"datatable admin-stats-table vol-reg-admin-table\" style=\"width:100%\" $data>";
				echo '<thead>';

		// ID | Name | email | Event | ISO 8601 Date | Fixer Station (apprentice) | Volunteer Roles | Comments | Attendance | Source
				
				printf( $rowFormat,
								'th',
								esc_html__( 'Name',								'reg-man-rc' ),
								esc_html__( 'ID',								'reg-man-rc' ),
								esc_html__( 'Email',							'reg-man-rc' ),
								esc_html__( 'Event',							'reg-man-rc' ),
								esc_html__( 'Numeric Event Date & Time',		'reg-man-rc' ), // Will be hidden
								esc_html__( 'Fixer Station',					'reg-man-rc' ),
								esc_html__( 'Volunteer Roles',					'reg-man-rc' ),
								esc_html__( 'Volunteer Note',					'reg-man-rc' ),
								esc_html__( 'Attendance',						'reg-man-rc' ),
								esc_html__( 'Source',							'reg-man-rc' ),
						);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
		
		// Render my add new dialog
		$this->render_add_new_volunteer_reg_dialog();
		
		// Render my email list dialog
		$this->render_volunteer_emails_dialog();
		
	} // function

	/**
	 * Render the dialog for adding a volunteer registration
	 * @param Event $event
	 */
	private function render_add_new_volunteer_reg_dialog() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) && $event->get_is_current_user_able_to_register_volunteers() ) {
			$title = __( 'Add New Volunteer Event Registration', 'reg-man-rc' );
	
			echo "<div class=\"reg-man-rc-add-new-cpt-dialog add-new-volunteer-reg-dialog dialog-container\" title=\"$title\">";

				$form = Add_Volunteer_Registration_Form::create( $event );
				$form->render();
				
			echo '</div>';

		} // endif
	} // function

	/**
	 * Render the dialog for volunteer emails
	 * @param Event $event
	 */
	private function render_volunteer_emails_dialog() {
		$single_event = $this->get_single_event();
		if ( ! empty( $single_event ) && $single_event->get_is_current_user_able_to_view_registered_volunteer_emails() ) {
			$title = __( 'Registered Volunteers Email Address List', 'reg-man-rc' );
	
			$emails_array = $single_event->get_registered_volunteer_emails();
			echo "<div class=\"email-list-dialog volunteer-reg-email-list-dialog dialog-container\" title=\"$title\">";
			
			if ( empty( $emails_array ) ) {
				
				$msg = __( 'No volunteers are registered for this event', 'reg-man-rc' );
				echo '<p>';
					echo $msg;
				echo '</p>';
				
			} else {

				$msg = __( 'For the privacy of our volunteers please remember to <b>blind copy</b> these email addresses in your note', 'reg-man-rc' );
				echo '<p>';
					echo $msg;
				echo '</p>';
				
				// I think the comma separator for email addresses is universal and should not be translated
				$list = implode( ', ', $emails_array );
				
				echo '<p class="reg-man-rc-email-list">';
					echo $list;
				echo '</p>';
				
			} // endif
			
			echo '</div>';

		} // endif
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
		/* Translators: %1$s is a key for an event that is not found in the system */
		$missing_event_format = __( '[ Event not found: %1$s ]', 'reg-man-rc' );
		$source_unknown_text = __( '[source not specified]', 'reg-man-rc' );
		$events[] = array(); // There will be a huge amount of duplication in the events so just save the ones I find

		foreach( $vol_reg_desc_array as $vol_reg_desc ) {
			$id = $vol_reg_desc->get_volunteer_registration_id();
			$name = $vol_reg_desc->get_volunteer_display_name();
			$name_text = ! empty( $name ) ? $name : $em_dash;
			$email = $vol_reg_desc->get_volunteer_email();
			$email_text = ! empty( $email ) ? $email : $em_dash;

			$event_key = $vol_reg_desc->get_event_key_string();
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
				} else {
					$event_text = sprintf( $missing_event_format, $event_key );
					$event_date_iso_8601 = '';
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

			$comments = $vol_reg_desc->get_volunteer_registration_comments();
			$comments = esc_html( $comments );
			
			$attendance = $vol_reg_desc->get_volunteer_attendance();
			if ( ! isset( $attendance ) ) {
				$attendance_text = $em_dash;
			} else {
				$attendance_text = $attendance ? __( 'Yes', 'reg-man-rc' ) : __( 'NO', 'reg-man-rc' );
			} // endif

			$source = $vol_reg_desc->get_volunteer_registration_descriptor_source();
			$source_text = isset( $source ) ? $source : $source_unknown_text;
			
		// Name | ID | email | Event | ISO 8601 Date | Fixer Station (apprentice) | Volunteer Roles | Comments | Attendance | Source
			
			$row = array();
			$row[] = $name_text;
			$row[] = $id;
			$row[] = $email_text;
			$row[] = $event_text;
			$row[] = $event_date_iso_8601;
			$row[] = $fixer_station_text;
			$row[] = $roles_text;
			$row[] = $comments;
			$row[] = $attendance_text;
			$row[] = $source_text;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class