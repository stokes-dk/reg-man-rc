<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\View\Admin\Item_Suggestion_Admin_View;
use Reg_Man_RC\View\Admin\Event_Category_Admin_View;
use Reg_Man_RC\View\Admin\Item_Type_Admin_View;
use Reg_Man_RC\View\Admin\Fixer_Station_Admin_View;
use Reg_Man_RC\View\Admin\Volunteer_Role_Admin_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Admin\Calendar_Admin_View;
use Reg_Man_RC\View\Admin\Admin_Event_Calendar_Page;
use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\View\Admin\Internal_Event_Descriptor_Admin_View;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\View\Admin\Item_Admin_View;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_View;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\View\Admin\Venue_Admin_View;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Admin\Volunteer_Admin_View;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Admin\Visitor_Admin_View;
use Reg_Man_RC\Model\Visitor;

/**
 * The administrative controller for providing help
 *
 * @since	v0.9.5
 *
 */
class Admin_Help_Controller {

	/**
	 * Register this controller
	 */
	public static function register() {

		// Use the current_screen action to put "Help" tabs in the right places
		add_action( 'current_screen', array( __CLASS__, 'handle_current_screen') );
		
	} // function

	/**
	 * Handle the current_screen action to show help tabs for my types (custom post types and custom taxonomies)
	 *  and custom admin menu pages
	 *  @param \WP_Screen $screen
	 */
	public static function handle_current_screen( $screen ) {

		// First, we need to figure out which page we're on
		
		$event_calendar_page_hook_name = Admin_Menu_Page::get_event_calendar_page_hook_name();

//		Error_Log::var_dump( $screen->base );
		switch( $screen->base ) {
			
			case 'edit':
			case 'post':
				$page_id = $screen->post_type;
				break;
				
			case 'edit-tags':
			case 'term':
				$page_id = $screen->taxonomy;
				break;
		
			case $event_calendar_page_hook_name:
				$page_id = Admin_Event_Calendar_Page::PAGE_SLUG;
				break;
				
			default:
				$page_id = NULL;
				
		} // endswitch

		// For this page, get the help tabs
		switch( $page_id ) {
			
			case Internal_Event_Descriptor::POST_TYPE:
				$tabs_array = Internal_Event_Descriptor_Admin_View::get_help_tabs();
				break;
				
			case Item::POST_TYPE:
				$tabs_array = Item_Admin_View::get_help_tabs();
				break;
				
			case Volunteer_Registration::POST_TYPE:
				$tabs_array = Volunteer_Registration_Admin_View::get_help_tabs();
				break;
				
			case Venue::POST_TYPE:
				$tabs_array = Venue_Admin_View::get_help_tabs();
				break;
				
			case Calendar::POST_TYPE:
				$tabs_array = Calendar_Admin_View::get_help_tabs();
				break;
				
			case Visitor::POST_TYPE:
				$tabs_array = Visitor_Admin_View::get_help_tabs();
				break;
				
			case Volunteer::POST_TYPE:
				$tabs_array = Volunteer_Admin_View::get_help_tabs();
				break;
				
			case Item_Suggestion::POST_TYPE:
				$tabs_array = Item_Suggestion_Admin_View::get_help_tabs();
				break;
				
			case Event_Category::TAXONOMY_NAME:
				$tabs_array = Event_Category_Admin_View::get_help_tabs();
				break;
			
			case Fixer_Station::TAXONOMY_NAME:
				$tabs_array = Fixer_Station_Admin_View::get_help_tabs();
				break;
			
			case Volunteer_Role::TAXONOMY_NAME:
				$tabs_array = Volunteer_Role_Admin_View::get_help_tabs();
				break;
			
			case Item_Type::TAXONOMY_NAME:
				$tabs_array = Item_Type_Admin_View::get_help_tabs();
				break;
			
			case Admin_Event_Calendar_Page::PAGE_SLUG:
				$tabs_array = Admin_Event_Calendar_Page::get_help_tabs();
				break;
			
			default:
				$tabs_array = NULL;
				break;
				
		} // endswitch

		// Add the tabs to the page	
		if ( ! empty( $tabs_array ) ) {

			// Add each tab
			foreach( $tabs_array as $tab_args ) {
				$screen->add_help_tab( $tab_args );
			} // endfor

		} // endif
		
	} // function

} // class