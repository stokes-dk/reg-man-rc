<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\Model\Stats\Item_Stats;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\View\Editable\Editable_Fixer_Station;
use Reg_Man_RC\View\Editable\Editable_Item_Type;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Editable\Editable_Item_Status;

/**
 *  This class provides a view of visitor registrations.  It uses Datatables to present the data in a table.
 *  Each row in the table represents one visitor and may show 1 or more items registered by that visitor
 */

class Visitor_List_View {

	private $event; // the event being viewed
	private $class_names; // a list of class names to be added to the list so that the DOM object can be found
	private $dom_setting = "i<'#visitor-list-table-toolbar'>frt"; // Moved info to the top

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
		return $this->dom_setting;
	} // function

	public function set_dom_setting( $dom_setting ) {
		// The DOM setting controls how the datatable elements are placed and in which order, e.g. search, info etc.
		// By default the DOM setting contains a placeholder for a toolbar element with id 'visitor-list-table-toolbar'
		// A valid value for this setting could be: <'#visitor-reg-mgr-toolbar'>frit
		$dom_setting = str_replace('"', '\'', $dom_setting); // Replace double quotes with singles, we'll store inside doubles
		$this->dom_setting = $dom_setting;
	} // function

	public function render() {
		$this->render_registration_list();
	} // function


	private function render_registration_list() {
		$event = $this->get_event();
		$event_key = $event->get_key();

		$class_names = $this->get_class_names();
		$ajax_url = esc_url( admin_url('admin-ajax.php') );
		$ajax_action = Visitor_Registration_Controller::DATATABLE_LOAD_AJAX_ACTION;
		$dom_setting = $this->get_dom_setting();

		echo "<table class=\"display visitor-reg-list-table $class_names\" sytle=\"width:100%;\"" .
				" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" data-event-key=\"$event_key\"" .
				" data-dom-setting=\"$dom_setting\">";
			// Note that identifying information (first name, last name, email) will be hidden
			// We need that information to count visitors and find all items one visitor brought

	// Visitor Name, Item Desc, Item Type, Fixer Station, Status, Item ID, Visitor ID, Is Repair Outcome Reported?
	
			$is_show_item_type = Settings::get_is_show_item_type_in_visitor_registration_list();
			$item_type_vis = $is_show_item_type ? '' : 'col-hidden';
			
			$row_format =
				'<tr>' .
					'<%1$s class="visitor-short-name group-by">%2$s</%1$s>' .
					'<%1$s class="item-desc col-not-sortable">%3$s</%1$s>' .
					'<%1$s class="item-type ' . $item_type_vis . ' col-not-sortable">%4$s</%1$s>' .
					'<%1$s class="item-fixer-station col-not-sortable">%5$s</%1$s>' .
					'<%1$s class="item-status col-not-sortable">%6$s</%1$s>' .
					'<%1$s class="item-id col-hidden">%7$s</%1$s>' .
					'<%1$s class="visitor-id col-hidden">%8$s</%1$s>' .
					'<%1$s class="item-is-reported col-hidden">%9$s</%1$s>' .
				'</tr>';
			echo '<thead>';
				printf(	$row_format, 'th',
						__('Name', 'reg-man-rc'),
						_x( 'Visitor / Item', 'A heading for a column showing the visitor name in some rows and item description in others.', 'reg-man-rc'),
						__( 'Item Type', 'reg-man-rc' ),
						__( 'Fixer Station', 'reg-man-rc' ),
						__( 'Repair Outcome', 'reg-man-rc' ),
						__( 'Item ID', 'reg-man-rc' ),
						__( 'Visitor ID', 'reg-man-rc' ),
						__( 'Is Outcome Reported?', 'reg-man-rc' ),
				);
			echo '</thead>';
			echo '<tbody>';
				// We are loading the table data using ajax rather than at page load
			echo '</tbody>';
		echo '</table>';
	} // function

	/**
	 * Get the registration data for an event
	 * @param	Event	$event
	 * @return
	 */
	public static function get_registration_data( $event ) {
		
		$result = array();
		$event_key = $event->get_key();
		$item_array = Item::get_items_registered_for_event( $event_key );
		$visitor_item_group_array = array(); // An array keyed by visitor id containing arrays of items for each visitor
		foreach( $item_array as $item ) {
			$visitor = $item->get_visitor();
			$visitor_id = $visitor->get_id();
			if ( isset( $visitor_item_group_array[ $visitor_id ] ) ) {
				$visitor_item_group_array[ $visitor_id ][] = $item; // Add the item to the existing array
			} else {
				$visitor_item_group_array[ $visitor_id ] = array( $item ); // create a new array of items for this visitor
			} // endif
		} // endfor

//		Error_Log::var_dump( $visitor_item_group_array );


		// Translators: %1$s is replaced button icon, e.g. a pencil, %2$s with button text, e.g. "Edit"
		$button_label_format = _x('%1$s%2$s', 'Creating a label for a button with icon and text', 'reg-man-rc');
		$add_icon = '<span class="dashicons dashicons-plus"></span>';
		$add_item_button_text = '<span class="button-text">' .
								_x('Add Item', 'Button label to add an item to a visitor who is already registered', 'reg-man-rc') .
							'</span>';
		$add_item_button_label = sprintf($button_label_format, $add_icon, $add_item_button_text);
		$add_item_button = "<button class=\"reg-man-rc-button visitor-reg-add-visitor-item-button\">$add_item_button_label</button>";
		$visitor_buttons = '<div class="visitor-button-container">' . $add_item_button . '</div>';
		// The rows for each visitor will be grouped on the client side based on the content of this column
		// I'll add the visitor's id (email or first/last name) in the column text to make sure it's unique
		// Otherwise two visitors with the same first name and last initial will be grouped together
		// That content (the visitor's id) will be hidden from view
		$name_format = 	'<div class="visitor-item-visitor-name-column" data-visitor-id="%2$s">' .
							'<span class="visitor-reg-list-visitor-short-name">%1$s</span>' .
							'<span class="visitor-reg-list-visitor-id">%2$s</span>' . $visitor_buttons .
						'</div>';

		// I'll create the name content once and store it in an array for use on each item rather than re-making it each time
		$name_column_array = array(); // The name column content will be duplicated for each item the visitor registers

		// Status Editable
		$ajax_action = Visitor_Registration_Controller::ITEM_STATUS_UPDATE_AJAX_ACTION;
		$status_editable = Editable_Item_Status::create( $ajax_action );
			
		// Item Type Editable
		$is_show_item_type = Settings::get_is_show_item_type_in_visitor_registration_list();
		if ( $is_show_item_type ) {
			$ajax_action = Visitor_Registration_Controller::ITEM_TYPE_UPDATE_AJAX_ACTION;
			$type_editable = Editable_Item_Type::create( $ajax_action );
		} // endif
		
		// Fixer Station Editable
		$ajax_action = Visitor_Registration_Controller::FIXER_STATION_UPDATE_AJAX_ACTION;
		$station_editable = Editable_Fixer_Station::create( $ajax_action );
			
		foreach ( $visitor_item_group_array as $visitor_id => $visitor_item_array ) {
			$visitor = Visitor::get_visitor_by_id( $visitor_id );
			
	// Visitor Name, Item Desc, Item Type, Fixer Station, Status, Item ID, Visitor ID, Is Repair Outcome Reported?
			
			foreach ( $visitor_item_array as $registered_item ) {
				if ( ! isset( $name_column_array[ $visitor_id ] ) ) {
					$display_name = $visitor->get_public_name();
					$name_column_array[ $visitor_id ] = sprintf( $name_format, $display_name, $visitor_id );
				} // endif
				$name_column = $name_column_array[ $visitor_id ];
				$id = $registered_item->get_id();
				$item_desc = $registered_item->get_item_description();
				
				$item_type = $registered_item->get_item_type();
//				$item_type_id = isset( $item_type ) ? $item_type->get_id() : Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
				if ( $is_show_item_type ) {
					$type_editable->set_item_id( $id );
					$type_editable->set_item_type( $item_type );
					$item_type_col = $type_editable->get_content();
				} else {
					$item_type_col = isset( $item_type ) ? $item_type->get_name() : '';
				} // endif
				
				$fixer_station = $registered_item->get_fixer_station();
//				$station_id = isset( $fixer_station ) ? $fixer_station->get_id() : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				$station_editable->set_object_id( $id );
				$station_editable->set_fixer_station( $fixer_station );
				$station_col = $station_editable->get_content();
				
				$status = $registered_item->get_status();
//				$status_id = $status->get_id();
				$status_editable->set_item_id( $id );
				$status_editable->set_item_status( $status );
				$status_col = $status_editable->get_content();
				
				$is_outcome_reported = $registered_item->get_is_repair_outcome_reported();
				
				$result[] = array( $name_column, $item_desc, $item_type_col, $station_col, $status_col, $id, $visitor_id, $is_outcome_reported );
			} // endfor
		} // endfor
		return $result;
	} // function


} // class