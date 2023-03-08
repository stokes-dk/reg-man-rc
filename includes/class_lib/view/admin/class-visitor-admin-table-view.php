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

	private function __construct() { }

	public static function create() {
		$result = new self();
		return $result;
	} // function

// Full name | Event | Email | First Event? | Mail list? | Source | Event Date ISO 8601
	public function render() {
		$rowFormat =
			'<tr>' .
				'<%1$s class="full-name">%2$s</%1$s>' .
				'<%1$s class="event">%3$s</%1$s>' .
				'<%1$s class="email">%4$s</%1$s>' .
				'<%1$s class="first-event">%5$s</%1$s>' .
				'<%1$s class="mail-list">%6$s</%1$s>' .
				'<%1$s class="source">%7$s</%1$s>' .
				'<%1$s class="iso-8601-date">%8$s</%1$s>' .
			'</tr>';
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Table_View_Admin_Controller::AJAX_GET_DATA_ACTION;
		$object_type = Table_View_Admin_Controller::TABLE_TYPE_VISITORS;
		$dom_setting = '';

		$title = __( 'Visitor Registrations', 'reg-man-rc' );

		echo '<div class="reg-man-rc-stats-table-view visitors-table event-filter-change-listener">';
			echo '<div class="reg-man-rc-table-loading-indicator spinner"></div>';
			echo '<div class="heading-container">';
				echo "<h4>$title</h4>";
			echo '</div>';

			echo '<div class="datatable-container admin-stats-table-container">';
				// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
				echo '<table class="datatable admin-stats-table visitors-admin-table" style="width:100%"' .
					" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" " .
					" data-table-type=\"$object_type\" data-group-by=\"\" " .
					" data-scope=\"\"" .
					" data-dom-setting=\"$dom_setting\">";
				echo '<thead>';
// Full name | Event | Email | First Event? | Mail list? | Source | Event Date ISO 8601
				printf( $rowFormat,
								'th',
								esc_html__( 'Visitor Name',						'reg-man-rc' ),
								esc_html__( 'Event',							'reg-man-rc' ),
								esc_html__( 'Email',							'reg-man-rc' ),
								esc_html__( 'First Event?',						'reg-man-rc' ),
								esc_html__( 'Join Mail List?',					'reg-man-rc' ),
								esc_html__( 'Source',							'reg-man-rc' ),
								'' // The ISO 8601 event date will be hidden and does not need column header
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
		$result = array();
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$source_unknown_text = __( '[source not specified]', 'reg-man-rc' );

		$events[] = array(); // There will be a huge amount of duplication in the events so just save the ones I find

// Full name | Event | Email | First Event? | Mail list? | Source | Event Date ISO 8601
		foreach( $visitor_reg_desc_array as $visitor_desc ) {

			$event_key = $visitor_desc->get_event_key();
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
					$start_date = $event->get_start_date_time_local_timezone_object();
					$event_date_iso_8601 = isset( $start_date ) ? $start_date->format( \DateTime::ISO8601 ) : '';
				} // endif
			} // endif

			$full_name_text = ! empty( $full_name ) ? $full_name : $em_dash;
			$full_name = $visitor_desc->get_full_name();
			$full_name_text = ! empty( $full_name ) ? $full_name : $em_dash;
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

// Full name | Event | Email | First Event? | Mail list? | Source | Event Date ISO 8601
			$row = array();
			$row[] = $full_name_text;
			$row[] = $event_text;
			$row[] = $email_text;
			$row[] = $first_event_text;
			$row[] = $mail_list_text;
			$row[] = $source_text;
			$row[] = $event_date_iso_8601;
			$result[] = $row;
		} // endfor
		return $result;
	} // function

} // class