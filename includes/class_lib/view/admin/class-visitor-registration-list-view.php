<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor;
use Reg_Man_RC\Model\Event;

/**
 * An instance of this class will render a list (in table format) of visitor registration descriptors.
 * It can be used to show the registrations for a specific visitor
 *
 * @since v0.1.0
 *
 */
class Visitor_Registration_List_View {
	private $reg_descriptor_array; // An array of visitor registration descriptors shown in this view

	/** A private constructor forces users to use one of the factory methods */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class.
	 * @param	Visitor_Registration_Descriptor	$visitor_registration_descriptor_array	An array of
	 *  visitor registration descriptors to be shown in this view
	 * @return	Visitor_Registration_List_View	An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create( $visitor_registration_descriptor_array ) {
		$result = new self();
		$result->reg_descriptor_array = is_array( $visitor_registration_descriptor_array ) ? $visitor_registration_descriptor_array : array();
		return $result;
	} // function

	/**
	 * Get the visitor registration descriptors
	 * @return	Visitor_Registration_Descriptor[]
	 */
	private function get_registration_descriptor_array() {
		return $this->reg_descriptor_array;
	} // function

	public function render() {
		$row_format =
			'<tr>' .
				'<%1$s class="name">%2$s</%1$s>' .
				'<%1$s class="date"">%3$s</%1$s>' .
				'<%1$s class="event">%4$s</%1$s>' .
				'<%1$s class="is-first-event">%5$s</%1$s>' .
				'<%1$s class="is-join-mail-list">%6$s</%1$s>' .
				'<%1$s class="item-count">%7$s</%1$s>' .
				'<%1$s class="iso-8601-date">%8$s</%1$s>' .
				'</tr>';
		echo '<div class="datatable-container visitor-reg-table-container">';
			// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
			$dom_setting = '';
			echo '<table class="datatable reg-man-rc-admin-object-list-datatable visitor-my-reg-table" style="width:100%"' .
				" data-dom-setting=\"$dom_setting\">";
			echo '<thead>';
	// Name | Date | Event | First Event? | Join Mail List? | Item Count
				printf( $row_format,
							'th',
							esc_html__( 'Name',							'reg-man-rc' ),
							esc_html__( 'Date',							'reg-man-rc' ),
							esc_html__( 'Event',						'reg-man-rc' ),
							esc_html__( 'First Event?',					'reg-man-rc' ),
							esc_html__( 'Join Mail List?',				'reg-man-rc' ),
							esc_html__( 'Item Count',					'reg-man-rc' ),
							esc_html__( 'Numeric Event Date & Time',	'reg-man-rc' ),
						);
				echo '</thead>';
				echo '<tbody>';

					$reg_array = $this->get_registration_descriptor_array();
					$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
					$yes_text = __( 'Yes', 'reg-man-rc' );
					$no_text = __( 'No', 'reg-man-rc' );
					
					foreach ( $reg_array as $registration ) {
						$name = $registration->get_full_name();
						$event_key = $registration->get_event_key();
						$event = Event::get_event_by_key( $event_key );
						if ( ! empty( $event ) ) {
							$event_date_text = $event->get_start_date();
							$event_text = $event->get_summary();
							$event_date = $event->get_start_date_time_object();
							$event_date_iso_8601 = isset( $event_date ) ? $event_date->format( \DateTime::ISO8601 ) : '';
						} else {
							$event_date_text = '';
							/* Translators %1$s is a key for an event */
							$event_text = sprintf( __( '[Event not found: %1$s]', 'reg-man-rc' ), $event_key );
							$event_date_iso_8601 = '';
						} // endif
						$is_first_event = $registration->get_is_first_event();
						if ( ! isset( $is_first_event ) ) {
							$is_first_event_text = $em_dash;
						} else {
							$is_first_event_text = $is_first_event ? $yes_text : $no_text;
						} // endif
						$is_join_mail_list = $registration->get_is_join_mail_list();
						if ( ! isset( $is_join_mail_list ) ) {
							$is_join_mail_list_text = $em_dash;
						} else {
							$is_join_mail_list_text = $is_join_mail_list ? $yes_text : $no_text;
						} // endif
						$item_count = $registration->get_item_count();
						$item_count_text = isset( $item_count ) ? strval( $item_count ) : $em_dash;
	// Name | Date | Event | First Event? | Join Mail List? | Item Count
						printf( $row_format,
									'td',
									esc_html( $name ),
									esc_html( $event_date_text ),
									esc_html( $event_text ),
									esc_html( $is_first_event_text ),
									esc_html( $is_join_mail_list_text ),
									esc_html( $item_count_text ),
									esc_html( $event_date_iso_8601 )
								);
					} // endfor
				echo '</tbody>';
			echo '</table>';
		echo '</div>';
	} // function
} // class
?>