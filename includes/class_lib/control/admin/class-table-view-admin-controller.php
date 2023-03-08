<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Admin\Items_Fixed_Admin_Table_View;
use Reg_Man_RC\View\Admin\Events_Admin_Table_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_Table_View;
use Reg_Man_RC\Model\Stats\Volunteer_Registration_Descriptor_Factory;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Model\Stats\Item_Statistics;
use Reg_Man_RC\View\Admin\Items_Admin_Table_View;
use Reg_Man_RC\Model\Stats\Item_Descriptor_Factory;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor_Factory;
use Reg_Man_RC\View\Admin\Visitor_Admin_Table_View;

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

		$filter = Event_Filter_Input_Form::get_filter_object_from_request( $_REQUEST );
		$table_type = isset( $_REQUEST[ 'table_type'] ) ? $_REQUEST[ 'table_type'] : NULL;
		$group_by = isset( $_REQUEST[ 'group_by'] ) ? $_REQUEST[ 'group_by'] : NULL;

		switch ( $table_type ) {

			case self::TABLE_TYPE_EVENTS:
				$events_array = Event::get_all_events_by_filter( $filter );
				$item_stats = Item_Statistics::create_item_stats_for_filter( $filter, Item_Statistics::GROUP_BY_EVENT );
				$item_group_stats_array = array_values( $item_stats->get_total_stats_array() );
				$data = Events_Admin_Table_View::get_table_data_array_for_events( $events_array, $item_group_stats_array );
				break;

			case self::TABLE_TYPE_ITEMS:
				$item_desc_array = Item_Descriptor_Factory::get_item_descriptors_for_filter( $filter );
				$data = Items_Admin_Table_View::get_table_data_array_for_item_descriptors( $item_desc_array );
				break;

			case self::TABLE_TYPE_ITEMS_FIXED:
				$item_stats = Item_Statistics::create_item_stats_for_filter( $filter, $group_by );
				$item_group_stats_array = array_values( $item_stats->get_total_stats_array() );
				$data = Items_Fixed_Admin_Table_View::get_table_data_array_for_item_group_stats( $item_group_stats_array, $group_by );
				break;

			case self::TABLE_TYPE_VOLUNTEER_REGISTRATIONS:
				// Get all the registered volunteers
				$vol_reg_array = Volunteer_Registration_Descriptor_Factory::get_all_volunteer_registration_descriptors_for_filter( $filter );
				$data = Volunteer_Registration_Admin_Table_View::get_table_data_array_for_volunteer_registration_descriptors( $vol_reg_array );
				break;

			case self::TABLE_TYPE_VISITORS:
				// Get all the visitor registrations
				$visitor_reg_array = Visitor_Registration_Descriptor_Factory::get_visitor_registrations_for_filter( $filter );
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