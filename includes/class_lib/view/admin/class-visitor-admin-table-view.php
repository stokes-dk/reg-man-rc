<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Admin\Table_View_Admin_Controller;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Error_Log;

/**
 * The administrative view for an item stats table
 *
 * @since	v0.1.0
 *
 */
class Visitor_Admin_Table_View {

	private $single_event; // The event object when showing data for a single event
	
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
			$result = __( sprintf( 'Visitors - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Visitors', 'reg-man-rc' );
		} // endif
		return $result;
	} // function

	private function get_export_file_name() {
		$event = $this->get_single_event();
		if ( ! empty( $event ) ) {
			$label = $event->get_label();
			/* Translators: %1$s is a label for an event used in the title of a page */
			$result = __( sprintf( 'Visitors - %1$s', $label ), 'reg-man-rc' );
		} else {
			$result = __( 'Visitors', 'reg-man-rc' );
		} // endif
		$result = sanitize_file_name( $result );
		return $result;
	} // function

	public function render() {
		$single_event = $this->get_single_event();
		$event_col_class = $this->get_is_event_column_hidden() ? 'col-hidden' : '';
		
		// Name | Email | Event | Event Date ISO 8601 | First Event? | Mail list? | Source
		
		$rowFormat =
			'<tr>' .
				'<%1$s class="visitor-display-name">%2$s</%1$s>' .
				'<%1$s class="visitor-email col-hidden">%3$s</%1$s>' .
				'<%1$s class="event-date-text ' . $event_col_class . '">%4$s</%1$s>' . // This column will be sorted by the next col's data
				'<%1$s class="event-date-iso-8601 col-hidden always-hidden col-not-searchable">%5$s</%1$s>' . // Must be after date
				'<%1$s class="visitor-first-event">%6$s</%1$s>' .
				'<%1$s class="visitor-is-join-mail-list">%7$s</%1$s>' .
				'<%1$s class="visitor-source">%8$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$ajax_nonce = wp_create_nonce( $ajax_action );
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_VISITORS;
		$dom_setting = '';
		$print_page_title = $this->get_print_page_title();
		$export_file_name = $this->get_export_file_name();
		
		$title = __( 'Visitor Registrations', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view visitors-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
			
				$data_array = array();
				$data_array[] = "data-ajax-url=\"$ajax_url\"";
				$data_array[] = "data-ajax-action=\"$ajax_action\"";
				$data_array[] = "data-ajax-nonce=\"$ajax_nonce\"";
				$data_array[] = "data-table-type=\"$object_type\"";
				if ( ! empty( $single_event ) ) {
					$single_event_key = $this->get_single_event_key();
					$data_array[] = "data-event-key=\"$single_event_key\"";
					$data_array[] = 'data-supplemental-data-button-class="supplemental-visitors-button"';
				} // endif
				$data_array[] = "data-print-page-title=\"$print_page_title\"";
				$data_array[] = "data-export-file-name=\"$export_file_name\"";
				$data_array[] = "data-scope=\"\"";
				$data_array[] = "data-dom-setting=\"$dom_setting\"";
				$data = implode( ' ', $data_array );
				
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo "<table class=\"datatable admin-stats-table visitors-admin-table\" style=\"width:100%\" $data>";
				echo '<thead>';

		// Name | Email | Event | Event Date ISO 8601 | First Event? | Mail list? | Source
				
					printf( $rowFormat,
								'th',
								esc_html__( 'Visitor',							'reg-man-rc' ),
								esc_html__( 'Email',							'reg-man-rc' ),
								esc_html__( 'Event',							'reg-man-rc' ),
								esc_html__( 'Numeric Event Date & Time',		'reg-man-rc' ),
								esc_html__( 'First Event?',						'reg-man-rc' ),
								esc_html__( 'Join Mail List?',					'reg-man-rc' ),
								esc_html__( 'Source',							'reg-man-rc' ),
						);
					echo '</thead>';
					echo '<tbody>';
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Get the table data array for the specified array of Visitor_Registration_Descriptor objects
	 *
	 * @param	Visitor_Registration_Descriptor[]	$visitor_reg_desc_array	An array registrations to be shown in the table.
	 * whose data are to be shown in the table.
	 * @result	string[][]	An array of string arrays containing the table data for the specified stats.
	 * Each element in the result represents one row and each row is an array of column values.
	 */
	public static function get_table_data_array_for_visitor_reg_descriptors( $visitor_reg_desc_array ) {
		
//		Error_Log::var_dump( $visitor_reg_desc_array ); 
		$result = array();
		/* Translators: %1$s is a key for an event that is not found in the system */
		$missing_event_format = __( '[ Event not found: %1$s ]', 'reg-man-rc' );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$source_unknown_text = __( '[source not specified]', 'reg-man-rc' );

		$events[] = array(); // There will be a huge amount of duplication in the events so just save the ones I find

		foreach( $visitor_reg_desc_array as $visitor_desc ) {

			$event_key = $visitor_desc->get_event_key_string();
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
					$start_date = $event->get_start_date_time_object();
					$event_date_iso_8601 = isset( $start_date ) ? $start_date->format( \DateTime::ISO8601 ) : '';
				} else {
					$event_text = sprintf( $missing_event_format, $event_key );
					$event_date_iso_8601 = '';
				} // endif
			} // endif

			$visitor_name = $visitor_desc->get_display_name();
			$visitor_name_text = ! empty( $visitor_name ) ? $visitor_name : $em_dash;
			$email = $visitor_desc->get_email();
			$email_text = ! empty( $email ) ? $email : $em_dash;

			$first_event = $visitor_desc->get_is_first_event();
			if ( ! isset( $first_event ) ) {
				$first_event_text = $em_dash;
			} else {
				$first_event_text = $first_event ? __( 'Yes', 'reg-man-rc' ) : __( 'No', 'reg-man-rc' );
			} // endif

			$mail_list = $visitor_desc->get_is_join_mail_list();
			if ( ! isset( $mail_list ) ) {
				$mail_list_text = $em_dash;
			} else {
				$mail_list_text = $mail_list ? __( 'Yes', 'reg-man-rc' ) : __( 'No', 'reg-man-rc' );
			} // endif

			$source = $visitor_desc->get_visitor_registration_descriptor_source();
			$source_text = isset( $source ) ? $source : $source_unknown_text;

		// Name | Email | Event | Event Date ISO 8601 | First Event? | Mail list? | Source
			
			$row = array();
			$row[] = $visitor_name_text;
			$row[] = $email_text;
			$row[] = $event_text;
			$row[] = $event_date_iso_8601;
			$row[] = $first_event_text;
			$row[] = $mail_list_text;
			$row[] = $source_text;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class