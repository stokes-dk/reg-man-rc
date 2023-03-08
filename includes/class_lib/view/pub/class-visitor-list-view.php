<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\Admin\Visitor_Registration_Admin_Controller;

/**
 *  This class provides a view of visitor registrations.  It uses Datatables to present the data in a table.
 *  Each row in the table represents one visitor and may show 1 or more items registered by that visitor
 */

class Visitor_List_View {

	const REGISTER_ITEM_EDITOR_CLASS	= 'visitor-reg-register-item-inline-editor';
	const ITEM_STATUS_EDITOR_CLASS		= 'visitor-reg-item-status-inline-editor';
	const FIXER_STATION_EDITOR_CLASS	= 'visitor-reg-fixer-station-inline-editor';

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
		$ajax_action = Visitor_Registration_Admin_Controller::DATATABLE_LOAD_AJAX_ACTION;
		$dom_setting = $this->get_dom_setting();
		echo "<table class=\"display visitor-reg-list-table $class_names\" sytle=\"width:100%;\"" .
				" data-ajax-url=\"$ajax_url\" data-ajax-action=\"$ajax_action\" data-event-key=\"$event_key\"" .
				" data-dom-setting=\"$dom_setting\">";
			// Note that identifying information (first name, last name, email) will be hidden
			// We need that information to count visitors and find all items one visitor brought
			$row_format =
				'<tr>' .
					'<%1$s class="visitor-short-name">%2$s</%1$s>' .
					'<%1$s class="item-desc">%3$s</%1$s>' .
					'<%1$s class="item-status">%4$s</%1$s>' .
					'<%1$s class="item-fixer-station">%5$s</%1$s>' .
					'<%1$s class="item-id">%6$s</%1$s>' .
					'<%1$s class="item-is-surveyed">%7$s</%1$s>' .
					'<%1$s class="visitor=id">%8$s</%1$s>' .
				'</tr>';
			echo '<thead>';
				printf(	$row_format, 'th', __('Name', 'reg-man-rc'),
						_x('#', 'Shortform of number, used as heading for item priority in visitor registration list ', 'reg-man-rc'),
						_x('Visitor / Item', 'A heading for a column showing the visitor name in some rows and item description in others', 'reg-man-rc'),
						__('Status', 'reg-man-rc'), __('Fixer Station', 'reg-man-rc'),
						__('Item ID', 'reg-man-rc'), __('Is Surveyed', 'reg-man-rc'),
						__('Visitor ID', 'reg-man-rc')
				);
			echo '</thead>';
			echo '<tbody>';
				// We are loading the table data using ajax rather than at page load
			echo '</tbody>';
		echo '</table>';
		self::render_item_status_inline_editor();
//		self::render_register_item_inline_editor();
		self::render_fixer_station_inline_editor();
	} // function

	public static function render_register_item_inline_editor() {
		$ajax_action = Visitor_Registration_Admin_Controller::REGISTER_ITEM_AJAX_ACTION;
		$form_input_list = Form_Input_List::create();
		$label = _x( 'Register this item', 'A checkbox label to register a pre-registered item', 'reg-man-rc' );
		$form_input_list->add_checkbox_input( $label, 'register-item' ); // The value will be the item's id
		$editor = Inline_Editor_Form::create( $form_input_list, $ajax_action );
		$editor->set_class_names( self::REGISTER_ITEM_EDITOR_CLASS );
		$editor->render();
	} // endif

	public static function render_item_status_inline_editor() {
		$ajax_action = Visitor_Registration_Admin_Controller::ITEM_STATUS_UPDATE_AJAX_ACTION;
		$form_input_list = Form_Input_List::create();
		$form_input_list->add_hidden_input( 'item-id', ' ');
//		$status_array = RC_Reg_Visitor_Reg::getStatusArray();
		$status_array = Item_Status::get_all_item_statuses();
//Error_Log::var_dump( $status_array );
		$select_options = array();
		foreach ( $status_array as $id => $status ) {
			$select_options[ $status->get_name() ] = $id;
		} // endfor
		$form_input_list->add_select_input( '', 'item-status', $select_options );
		$editor = Inline_Editor_Form::create( $form_input_list, $ajax_action );
		$editor->set_class_names( self::ITEM_STATUS_EDITOR_CLASS );
		$editor->render();
	} // endif

	public static function render_fixer_station_inline_editor() {
		$ajax_action = Visitor_Registration_Admin_Controller::FIXER_STATION_UPDATE_AJAX_ACTION;
		$form_input_list = Form_Input_List::create();
		$form_input_list->add_hidden_input('item-id', '');
		// FIXME - Figure out how and where to store fixer stations
//		$stations_array = RC_Reg_Fixer_Vol_Reg::getAllFixerStations();
		$stations_array = Fixer_Station::get_all_fixer_stations();
		$select_options = array();
		foreach ( $stations_array as $id => $station ) {
			$select_options[ $station->get_name() ] = $id;
		} // endfor
		$form_input_list->add_select_input( '', 'fixer-station', $select_options );
		$editor = Inline_Editor_Form::create( $form_input_list, $ajax_action );
		$editor->set_class_names( self::FIXER_STATION_EDITOR_CLASS );
		$editor->render();
	} // endif

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

		$status_array = Item_Status::get_all_item_statuses();

		// Translators: %1$s is replaced button icon, e.g. a pencil, %2$s with button text, e.g. "Edit"
		$button_label_format = _x('%1$s%2$s', 'Creating a label for a button with icon and text', 'reg-man-rc');
		$edit_icon = '<span class="dashicons dashicons-edit"></span>';
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
		$name_column_array = array(); // The name column content will be duplicated for each item the visitor registers
		// I'll create that content once and store it in the array for use on each item rather than re-making it each time
		$status_format = '<div class="visitor-item-status reg-man-rc-inline-editable" ' .
						'data-item-status="%2$s" data-reg-man-rc-inline-editor-selector="%3$s">' .
							'%1$s' . $edit_icon . '</div>';
		$station_format = '<div class="visitor-item-fixer-station reg-man-rc-inline-editable" ' .
						'data-item-fixer-station="%2$s" data-reg-man-rc-inline-editor-selector="%3$s">' .
							'%1$s' . $edit_icon . '</div>';
		foreach ( $visitor_item_group_array as $visitor_id => $visitor_item_array ) {
			// I will sort the visitor's items so that items that have been fixed are last
			//  and other items are in their priority order or order of registration
			usort( $visitor_item_array, function( Item $item_1, Item $item_2 ) {
				$is_repair_status_known_1 = ( $item_1->get_status() !== Item_Status::REGISTERED ) ;
				$is_repair_status_known_2 = ( $item_2->get_status() !== Item_Status::REGISTERED );
				if ( $is_repair_status_known_1 !== $is_repair_status_known_2 ) {
					$comp_val =  ( $is_repair_status_known_1 ) ? 1 : -1;
				} else {
					$id1 = $item_1->get_id();
					$id2 = $item_2->get_id();
					$comp_val = ( $id1 < $id2 ) ? -1 : 1; // This is the order they were registered
/* FIXME - we don't support item priorities
					$priority1 = $item_1->getItemPriority();
					$priority2 = $item_2->getItemPriority();
					if ($priority1 === $priority2) {
						$comp_val = ( $id1 < $id2 ) ? -1 : 1;
					} elseif (($priority1 !== NULL) && ($priority2 !== NULL)) {
						$comp_val = ($priority1 < $priority2) ? -1 : 1;
					} else { // in this case one priority is NULL the other is not
						$comp_val = ($priority1 === NULL) ? 1 : -1;
					} // endif
*/
				} // endif
				return $comp_val;
			}); // usort

			// FIXME - I don't think I need a priority number but let's make sure
			$priority = 0; // Order them
			$visitor = Visitor::get_visitor_by_id( $visitor_id );
			$surveyed_statuses = array( Item_Status::FIXED, Item_Status::REPAIRABLE, Item_Status::END_OF_LIFE );
			foreach ( $visitor_item_array as $registered_item ) {
				$priority++; // increment (we started at 0 but we'll show 1 as first prioroty)
				if ( ! isset( $name_column_array[ $visitor_id ] ) ) {
					$display_name = $visitor->get_public_name();
					$name_column_array[ $visitor_id ] = sprintf( $name_format, $display_name, $visitor_id );
				} // endif
				$name_column = $name_column_array[ $visitor_id ];
				$id = $registered_item->get_id();
				$item_desc = $registered_item->get_item_description();
				$status = $registered_item->get_status();
				$status_id = $status->get_id();
				$status_text = $status->get_name(); //isset($status_array[$status]) ? $status_array[$status] : __('Unknown', 'reg-man-rc'); // Defensive
//				if ( $status === Item_Status::PRE_REGISTERED_MARKER ) {
//					$editor_selector = '.' . self::REGISTER_ITEM_EDITOR_CLASS;
//				} else {
					$editor_selector = '.' . self::ITEM_STATUS_EDITOR_CLASS;
//				} // endif
				$status_col = sprintf( $status_format, $status_text, $status_id, $editor_selector );
				$is_pre_registered = FALSE;// $registered_item->getIsPreRegistered();
				$reg_class = $is_pre_registered ? 'item-pre-registered' : 'item-registered';
				$status = $registered_item->get_status();
				$status_id = $status->get_id();
				$is_surveyed = in_array( $status_id, $surveyed_statuses );
				$is_surveyed_class = $is_surveyed ? 'item-surveyed' : '';
				$classes = "$reg_class $is_surveyed_class";
				$fixer_station = $registered_item->get_fixer_station();
				$station_text = isset( $fixer_station ) ? $fixer_station->get_name() : '';
				$station_id = isset( $fixer_station ) ? $fixer_station->get_id() : Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				$editor_selector = '.' . self::FIXER_STATION_EDITOR_CLASS;
				$station_col = sprintf( $station_format, $station_text, $station_id, $editor_selector );
				$result[] = array( $name_column, $priority, $item_desc, $status_col, $station_col, $id, $is_surveyed, $visitor_id);
			} // endfor
		} // endfor
		return $result;
	} // function


} // class