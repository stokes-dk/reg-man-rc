<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor;
use Reg_Man_RC\Model\Event;

/**
 * An instance of this class will render a list (in table format) of volunteer registration descriptors.
 * It can be used to show the registrations for a specific volunteer
 *
 * @since v0.1.0
 *
 */
class Volunteer_Registration_List_View {
	private $reg_descriptor_array; // An array of volunteer registration descriptors shown in this view

	/** A private constructor forces users to use one of the factory methods */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class.
	 * @param	Volunteer_Registration_Descriptor	$volunteer_registration_descriptor_array	An array of
	 *  volunteer registration descriptors to be shown in this view
	 * @return	Volunteer_Registration_List_View	An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create( $volunteer_registration_descriptor_array ) {
		$result = new self();
		$result->reg_descriptor_array = is_array( $volunteer_registration_descriptor_array ) ? $volunteer_registration_descriptor_array : array();
		return $result;
	} // function

	/**
	 * Get the volunteer registration descriptors
	 * @return	Volunteer_Registration_Descriptor[]
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
				'<%1$s class="fixer-station">%5$s</%1$s>' .
				'<%1$s class="volunteer-roles">%6$s</%1$s>' .
				'<%1$s class="iso-8601-date">%7$s</%1$s>' .
				'</tr>';
		echo '<div class="datatable-container volunteer-reg-table-container">';
			// Using inline style width 100% allows Datatables to calculate the proper width, css doesn't work
			$dom_setting = '';
			echo '<table class="datatable reg-man-rc-admin-object-list-datatable volunteer-my-reg-table" style="width:100%"' .
				" data-dom-setting=\"$dom_setting\">";
			echo '<thead>';
	// Name | Date | Event | Fixer Station | Volunteer Roles
				printf( $row_format,
							'th',
							esc_html__( 'Name',							'reg-man-rc' ),
							esc_html__( 'Date',							'reg-man-rc' ),
							esc_html__( 'Event',						'reg-man-rc' ),
							esc_html__( 'Fixer Station',				'reg-man-rc' ),
							esc_html__( 'Volunteer Roles',				'reg-man-rc' ),
							esc_html__( 'Numeric Event Date & Time',	'reg-man-rc' ),
						);
				echo '</thead>';
				echo '<tbody>';
					$reg_array = $this->get_registration_descriptor_array();
					$date_order_format = \DateTime::ISO8601;
					foreach ( $reg_array as $registration ) {
						$name = is_admin() ? $registration->get_volunteer_full_name() : $registration->get_volunteer_public_name();
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
						$fixer_station = $registration->get_assigned_fixer_station_name();
						$volunteer_roles = $registration->get_assigned_volunteer_role_names_array();
						$roles_text = implode( ', ', $volunteer_roles );
						printf( $row_format,
									'td',
									esc_html( $name ),
									esc_html( $event_date_text ),
									esc_html( $event_text ),
									esc_html( $fixer_station ),
									esc_html( $roles_text ),
									esc_html( $event_date_iso_8601 )
								);
					} // endfor
				echo '</tbody>';
			echo '</table>';
		echo '</div>';
	} // function
} // class
?>