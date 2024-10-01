<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\View\Admin\Items_Fixed_Admin_Table_View;
use Reg_Man_RC\View\Admin\Events_Admin_Table_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_Table_View;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor_Factory;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\View\Admin\Items_Admin_Table_View;
use Reg_Man_RC\Model\Stats\Item_Descriptor_Factory;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor_Factory;
use Reg_Man_RC\View\Admin\Visitor_Admin_Table_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Events_Collection;

/**
 * The table view controller for the admin interface
 *
 * This class provides the controller function for tables showing data in the admin interface
 *
 * @since v0.1.0
 *
 */
class Table_View_Admin_Controller {

	const AJAX_GET_DATA_ACTION					= 'reg-man-rc-get-admin-table-data';

	const TABLE_TYPE_EVENTS						= 'events';
	const TABLE_TYPE_ITEMS						= 'items';
	const TABLE_TYPE_ITEMS_FIXED				= 'items-fixed';
	const TABLE_TYPE_VOLUNTEER_REGISTRATIONS	= 'volunteer-registrations';
	const TABLE_TYPE_VISITORS					= 'visitors';

	public static function register() {

		// Register the handler for an AJAX request to get stats from a logged-in user
		add_action( 'wp_ajax_' . self::AJAX_GET_DATA_ACTION, array( __CLASS__, 'handle_priv_ajax_get_data' ) );

	} // function

	/**
	 * Handle a request to get stats from a logged-in user
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_priv_ajax_get_data() {

		$nonce		= isset( $_REQUEST[ '_wpnonce'] )		? $_REQUEST[ '_wpnonce' ] : NULL;
		$event_key	= isset( $_REQUEST[ 'event_key'] )		? $_REQUEST[ 'event_key' ] : NULL;
		$table_type	= isset( $_REQUEST[ 'table_type' ] )	? $_REQUEST[ 'table_type' ] : NULL;
		$group_by	= isset( $_REQUEST[ 'group_by' ] )		? $_REQUEST[ 'group_by' ] : NULL;

		$filter		= Event_Filter_Input_Form::get_filter_object_from_request( $_REQUEST );
		
//		Error_Log::var_dump( $event_key, $nonce );
		
		$is_valid_nonce = wp_verify_nonce( $nonce, self::AJAX_GET_DATA_ACTION );
		if ( ! $is_valid_nonce ) {
			$error = __( 'Your security token has expired.  Please reload the page.', 'reg-man-rc' );
			echo json_encode( array( 
					'data' => array(), // This is for the datatables row data (if requested)
					'success' => FALSE, // This is for my ajax requests
					'text' => __( 'ERROR', 'reg-man-rc' ), // This is for my ajax requests 
					'error' => $error ) // This is for both, mine and datatables
				);
			wp_die(); // THIS IS REQUIRED! <== EXIT POINT!!!
		} // endif
			
		if ( ! empty( $event_key ) ) {
			$events_collection = Events_Collection::create_for_single_event_key( $event_key );
		} else {
			$is_include_placeholder_events = TRUE;
			if ( ! empty( $filter ) ) {
				$events_collection = Events_Collection::create_for_filter( $filter, $is_include_placeholder_events );
			} else {
				$events_collection = Events_Collection::create_for_all_events( $is_include_placeholder_events );
			} // endif
		} // endif
		
//		Error_Log::var_dump( $events_collection->get_event_keys_array() );
		
		switch ( $table_type ) {

			case self::TABLE_TYPE_EVENTS:
				$group_items_by = Item_Stats_Collection::GROUP_BY_EVENT;
				$item_stats_col = Item_Stats_Collection::create_for_events_collection( $events_collection, $group_items_by );
				$item_stats_array = array_values( $item_stats_col->get_all_stats_array() );
				$events_array = $events_collection->get_events_array();
				$data = Events_Admin_Table_View::get_table_data_array_for_events( $events_array, $item_stats_array );
				break;

			case self::TABLE_TYPE_ITEMS:
				$item_desc_array = Item_Descriptor_Factory::get_item_descriptors_for_events_collection( $events_collection );
				$data = Items_Admin_Table_View::get_table_data_array_for_item_descriptors( $item_desc_array );
				break;

			case self::TABLE_TYPE_ITEMS_FIXED:
				$item_stats_col = Item_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
				$item_stats_array = array_values( $item_stats_col->get_all_stats_array() );
				$data = Items_Fixed_Admin_Table_View::get_table_data_array_for_item_stats( $item_stats_array, $group_by );
				break;

			case self::TABLE_TYPE_VOLUNTEER_REGISTRATIONS:
				$vol_reg_array = Volunteer_Registration_Descriptor_Factory::get_all_volunteer_registration_descriptors_for_events_collection( $events_collection );
				$data = Volunteer_Registration_Admin_Table_View::get_table_data_array_for_volunteer_registration_descriptors( $vol_reg_array );
				break;

			case self::TABLE_TYPE_VISITORS:
				$visitor_reg_array = Visitor_Registration_Descriptor_Factory::get_visitor_registration_descriptors_for_events_collection( $events_collection );
				$data = Visitor_Admin_Table_View::get_table_data_array_for_visitor_reg_descriptors( $visitor_reg_array );
				break;

			default:
				$data = array();
				break;
				
		} // endswitch

		$result = array( 'data' => $data );

		echo json_encode( $result );
		wp_die(); // THIS IS REQUIRED!

	} // function

} // class