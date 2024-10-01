<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\View\In_Place_Item_Status_Editor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Fixer_Station;

/**
 *  This class provides a view of visitor registrations.  It uses Datatables to present the data in a table.
 *  Each row in the table represents one visitor and may show 1 or more items registered by that visitor
 */

class Visitor_List_View {

	private $event; // the event being viewed
	private $class_names; // a list of class names to be added to the list so that the DOM object can be found
	private $dom_setting;

	private function __construct() { }

	public static function create( $event, $class_names = '' ) {
		$result = new self();
		$result->event = $event;
		$result->class_names = $class_names;
		return $result;
	} // function

	/**
	 * Get the event being shown
	 * @return Event
	 */
	private function get_event() {
		return $this->event;
	} // function

	private function get_class_names() {
		return $this->class_names;
	} // function

	/**
	 * Assign any required class names for this list
	 * @param	string	$class_names	A space-separated list of class names that will be added to the DOM element
	 */
	public function set_class_names( $class_names ) {
		//  e.g. 'my-class another-class'
		$this->class_names = $class_names;
	} // function

	public function get_dom_setting() {
		if ( ! isset( $this->dom_setting ) ) {
//			$this->dom_setting = 'lfritip';
			$this->dom_setting = "<'reg-man-rc-datatable-header-wrapper'if>rti";
		} // endif
		return $this->dom_setting;
	} // function

	/**
	 * Render this view
	 */
	public function render() {
		echo '<div class="reg-man-rc-visitor-reg-busy"></div>'; // busy indicator
		$this->render_toolbar();
		$this->render_registration_list();
	} // function
	
	private function render_toolbar() {
		
		// Translators: %1$s is replaced button icon, e.g. a pencil, %2$s with button text, e.g. "Edit"
		$button_label_format = _x('%1$s%2$s', 'Creating a label for a button with icon and text', 'reg-man-rc');
		$refresh_button_icon = '<span class="dashicons dashicons-update"></span>';
		$refresh_button_text = '<span class="button-text">' . __( 'Refresh', 'reg-man-rc' ) . '</span>';
//		var surveyButtonIcon = '<span class="dashicons dashicons-clipboard"></span>';
//		var surveyButtonText = '<span class="button-text">' + __('Visitor Feedback', 'reg-man-rc') + '</span>';
		$add_button_icon = '<span class="dashicons dashicons-plus-alt"></span>';
		$add_button_text = '<span class="button-text">' . __( 'Add Visitor', 'reg-man-rc' ) . '</span>';
		
		echo '<div class="datatable-toolbar visitor-list-table-toolbar">';

			$all_stations = Fixer_Station::get_all_fixer_stations();
			echo '<select class="reg-man-rc-button reg-manager-show-station toolbar-control" autocomplete="off">';
				echo '<option value="0" selected="selected">All Fixer Stations</option>';
				$option_format = '<option value="%1$s">%2$s</option>';
				foreach( $all_stations as $fixer_station ) {
					$station_id = $fixer_station->get_id();
					$station_name = $fixer_station->get_name();
					printf( $option_format, $station_id, esc_html( $station_name ) ); 
				} // endfor
			echo '</select>';
/*
			echo '<select class="reg-man-rc-button reg-manager-sort-by toolbar-control" autocomplete="off">';
				echo '<option value="item-status" selected="selected">Sort by Status</option>';
//				echo '<option value="item-fixer-station">Sort by Fixer Station</option>';
				echo '<option value="visitor-short-name">Sort by Visitor</option>';
				echo '<option value="item-id">Sort by Arrival</option>';
			echo '</select>';
*/
			echo '<button class="reg-man-rc-button reg-manager-button toolbar-control reg-manager-refresh">';
				printf( $button_label_format, $refresh_button_icon, $refresh_button_text );
			echo '</button>';
			
//			'<button class="reg-man-rc-button reg-manager-button toolbar-control reg-manager-survey">' +
//				sprintf(buttonLabelFormat, surveyButtonIcon, surveyButtonText) +
//			'</button>' +

			echo '<button class="reg-man-rc-button reg-manager-button toolbar-control reg-manager-add">';
				printf( $button_label_format, $add_button_icon, $add_button_text );
			echo '</button>';
		
		echo '</div>';
	} // function


	private function render_registration_list() {
		$event = $this->get_event();
		$event_key = $event->get_key_string();

		$class_names = $this->get_class_names();
		$ajax_url = esc_url( admin_url('admin-ajax.php') );
		$ajax_action = Visitor_Registration_Controller::DATATABLE_LOAD_AJAX_ACTION;
		$dom_setting = $this->get_dom_setting();
		$nonce = wp_create_nonce( $ajax_action );

		echo "<table class=\"display visitor-reg-list-table $class_names\" sytle=\"width:100%;\"" .
				" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" data-event-key=\"$event_key\"" .
				" data-dom-setting=\"$dom_setting\" data-ajax-nonce=\"$nonce\">";
			// Note that identifying information (first name, last name, email) will be hidden
			// We need that information to count visitors and find all items one visitor brought

	// Visitor Name, Item Desc, Fixer Station, Status (editable), Item ID, Visitor ID, Is Repair Outcome Reported?, Status Text (for search), Status Order (for sorting)
	
			// The displayed fixer station and item status are not searchable because those columns
			//  contain a control that ALWAYS include words like "Fixed" or "Jewellery" so a search
			//  for a term like that will always return ALL rows - hence, not really searchable
			// To allow searching for those terms, we'll put the text value alone in a separate searchable column
			$row_format =
				'<tr>' .
					'<%1$s></%1$s>' . // column for responsive control
					'<%1$s class="visitor-short-name col-not-sortable">%2$s</%1$s>' .
					'<%1$s class="item-desc col-not-sortable">%3$s</%1$s>' .
					'<%1$s class="item-fixer-station col-not-sortable">%4$s</%1$s>' .
					'<%1$s class="item-status col-not-sortable col-not-searchable">%5$s</%1$s>' .
					'<%1$s class="item-id col-hidden col-not-sortable">%6$s</%1$s>' .
					'<%1$s class="visitor-id col-hidden">%7$s</%1$s>' .
					'<%1$s class="item-is-reported col-hidden">%8$s</%1$s>' .
					'<%1$s class="item-status-text col-searchable-hidden">%9$s</%1$s>' .
					'<%1$s class="item-status-order col-hidden">%10$s</%1$s>' .
					'<%1$s class="item-date-time-enqueued col-hidden">%11$s</%1$s>' .
				'</tr>';
			echo '<thead>';
				printf(	$row_format, 'th',
						__( 'Visitor', 'reg-man-rc' ),
						__( 'Item', 'reg-man-rc' ),
						__( 'Fixer Station', 'reg-man-rc' ),
						__( 'Status / Outcome', 'reg-man-rc' ),
						__( 'Item Number', 'reg-man-rc' ),
						__( 'Visitor ID', 'reg-man-rc' ),
						__( 'Is Outcome Reported?', 'reg-man-rc' ),
						__( 'Outcome text', 'reg-man-rc' ), // hidden but used for searching
						__( 'Status order', 'reg-man-rc' ), // hidden but used for ordering by status
						__( 'Date/Time enqueued', 'reg-man-rc' ), // hidden but used to order In Queue items
						);
			echo '</thead>';
			echo '<tbody>';
				// We are loading the table data using ajax rather than at page load
			echo '</tbody>';
		echo '</table>';
	} // function

	/**
	 * Get the registration data for an event
	 * @param	Event			$event			The event whose registration data is requested
	 * @param	Fixer_Station	$fixer_station	An optional fixer station within the event
	 * @return
	 */
	public static function get_registration_data( $event, $fixer_station = NULL ) {
		
		$result = array();
		$event_key = $event->get_key_string();
		$station_id = ! empty( $fixer_station ) ? $fixer_station->get_id() : 0;
		$item_array = Item::get_items_registered_for_event( $event_key, $station_id );

		$base_button_classes = 'reg-man-rc-button visitor-reg-list-button';
		
		$name_button_classes = "$base_button_classes visitor-reg-single-visitor-details-button";
		$name_format =
		'<div class="visitor-reg-list-button-text-container">' .
			'<button class="' . $name_button_classes . '" value="%2$s">' .
				'<span class="dashicons dashicons-edit"></span>' .
			'</button>' .
			'<span class="visitor-reg-list-visitor-name">%1$s</span>' .
		'</div>';
		
		$item_button_classes = "$base_button_classes visitor-reg-view-item-button";
		$item_format =
		'<div class="visitor-reg-list-button-text-container">' .
			'<button class="' . $item_button_classes  . '" value="%2$s">' .
				'<span class="dashicons dashicons-edit"></span>' .
			'</button>' .
			'<span class="visitor-reg-list-item">%1$s</span>' .
		'</div>';
		
		// This will establish the order items are displayed inside a fixer station with in queue at the top
		$status_order_array = array(
				Item_Status::IN_QUEUE,
				Item_Status::IN_PROGRESS,
				Item_Status::DONE_UNREPORTED,
				Item_Status::FIXED,
				Item_Status::REPAIRABLE,
				Item_Status::END_OF_LIFE,
				Item_Status::STANDBY,
				Item_Status::WITHDRAWN,
		);
		
		foreach ( $item_array as $registered_item ) {

			$visitor = $registered_item->get_visitor();
			if ( ! empty( $visitor ) ) {
				// If there is no visitor then exclude it from the list
				$visitor_id = $visitor->get_id(); 
			
	// Visitor Name, Item Desc, Fixer Station, Status (editable), Item ID, Visitor ID, Is Repair Outcome Reported?, Status Text (for search), Status Order (for sorting)
			
				$fixer_station = $registered_item->get_fixer_station();
//				$fixer_station_id = isset( $fixer_station ) ? $fixer_station->get_id() : '';
//				$item_type = $registered_item->get_item_type();
//				$item_type_id = isset( $item_type ) ? $item_type->get_id() : '';
				$status = $registered_item->get_item_status();
				$status_id = isset( $status ) ? $status->get_id() : '';
				
				$name_column = sprintf( $name_format, $visitor->get_public_name(), $visitor_id );
				$item_id = $registered_item->get_id();
				$item_desc = $registered_item->get_item_description();
				$item_column = sprintf( $item_format, $item_desc, $item_id );

				$station_name = isset( $fixer_station ) ? $fixer_station->get_name() : __( '[No fixer station assigned]', 'reg-man-rc' );

				$status_editor = In_Place_Item_Status_Editor::create( $registered_item );
				$editable_status_col = $status_editor->get_contents();

				$is_outcome_reported = isset( $status ) ? $status->get_is_repair_outcome_status() : FALSE;
				
				$status_name = isset( $status ) ? $status->get_name() : '';
				$status_order = array_search( $status_id, $status_order_array );

				$date_time_enqueued = $registered_item->get_date_time_enqueued();
				
				$result[] = array(
						'', // column for responsive control
						$name_column,
						$item_column,
						$station_name,
						$editable_status_col,
						$item_id,
						$visitor_id,
						$is_outcome_reported,
						$status_name,
						$status_order,
						$date_time_enqueued,
				);
				
//			} // endfor

			} // endif

		} // endfor
		
		return $result;
		
	} // function

} // class