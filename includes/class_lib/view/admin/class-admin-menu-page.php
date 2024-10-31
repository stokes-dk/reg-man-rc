<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;

/**
 * The custom admin menu page for the plugin
 *
 */

class Admin_Menu_Page {

	const MENU_SLUG = 'reg-man-rc-admin';
	
	private static $event_calendar_page_instance;
	private static $event_calendar_page_callback;
	private static $event_calendar_page_hook_name;
	private static $stats_page_hook_name;
	
	private function __construct() {
	} // construct

	/**
	 * Factory method to create an instance of this class
	 * @return	Admin_Menu_Page
	 * @since	v0.1.0
	 */
	private static function create() {
		$result = new self();
		return $result;
	} // function
	
	/**
	 * Register this view
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {
		if ( is_admin() ) {

			// My menu has to be created during the admin_menu hook
			add_action( 'admin_menu', array( __CLASS__, 'create_menu' ) );

			// To make sure the menu items are highlighted properly I have to use the parent_file filter
			add_filter( 'parent_file', array( __CLASS__, 'assign_current_submenu' ) );

		} // endif
	} // function

	/**
	 * Get a flag to indicate whether or not to create the main custom menu page for this plugin
	 * @return boolean
	 */
	private static function get_is_create_main_custom_menu_page() {

		// There is currently a bug ( #50284 ) that prevents the user from adding a new CPT when its page is a submenu
		//   and the user's role does not have 'edit_posts' capability.
		// In other words, if you can't create posts (of the built-in type) then you can't create posts of this CPT when it's in a submenu.
		// To get around that I will create the main custom menu page only when the user has 'edit_posts' capability.
		// Otherwise, when the role is Item Registrar for example, the CPTs that are visible will have their own menu items
		//   and will not be submenu items under my main custom menu page.
		
		$result = current_user_can( 'edit_posts' );
		return $result;

	} // function

	/**
	 * Get the correct value for my custom post types to use for 'show_in_menu' when they register.
	 *
	 * The result value determines whether and how my custom menu items appear in the admin menu.
	 * If this function returns my MENU_SLUG then the CPTs will have their UIs created as submenu pages under the custom menu.
	 * If this function returns TRUE then the CPT UIs will be top-level items in the admin menu.
	 * If this function returns FALSE then CPTs will have no UI shown at all.
	 * @param	string	$capability_type_plural	The plural version of the capability type used to register the CPT.
	 * @param	string	$capability				The capability to test if current_user_can.
	 *   If current_user_can( $capability . '_' . $capability_type_plural ) then the CPT will have an admin UI.
	 *   Default is 'edit_others'.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function get_CPT_show_in_menu( $capability_type_plural, $capability = 'edit' ) {
		
		// When we are creating the custom menu, all CPTs will go inside it, provided the user has authority
		if ( self::get_is_create_main_custom_menu_page() ) {

			$result = current_user_can( $capability . '_' . $capability_type_plural ) ? self::MENU_SLUG : FALSE;

		} elseif ( current_user_can( $capability . '_' . $capability_type_plural ) ) {
			
			// When there is no custom menu and this user is able to edit others posts for the specified capability type then
			//  the CPT UI will be a top-level item in the admin menu.
			// For example, if the user can edit others Items (Item Registrar) then the Items UI will be a top-level admin menu item.
			// Note, it is possible to change this to 'edit_posts' so that a Volunteer would see the interface for Volunteer Registrations
			// for example but that lets them see all other volunteer registrations which we may not want (even though they can't edit them)
			$result = TRUE;
			
		} else {
			
			// In all other cases the CPT will not be shown in the admin menu at all
			$result = FALSE;
			
		} // endif
//		Error_Log::var_dump( $capability_type_plural, $capability, $result );
		return $result;
	} // function
	
	/**
	 * Get the menu position for my admin menu items including custom post types
	 * @return int	The menu position to use for my menu items.  The result has the following meaning:
	 *     5 – below Posts
	 *     10 – below Media
	 *     15 – below Links
	 *     20 – below Pages
	 *     25 – below comments
	 *     60 – below first separator
	 *     65 – below Plugins
	 *     70 – below Users
	 *     75 – below Tools
	 *     80 – below Settings
	 *     100 – below second separator
	 */
	public static function get_menu_position() {
		return 20; // TODO: should this be a setting?
	} // function

	/**
	 * Get the event calendar page
	 * @return Admin_Event_Calendar_Page
	 */
	private static function get_event_calendar_page_instance() {
		if ( ! isset( self::$event_calendar_page_instance ) ) {
			self::$event_calendar_page_instance = Admin_Event_Calendar_Page::create();
		} // endif
		return self::$event_calendar_page_instance;
	} // function
	
	/**
	 * Get the callback for the event calendar page
	 * @return array
	 */
	private static function get_event_calendar_page_callback() {
		if ( ! isset( self::$event_calendar_page_callback ) ) {
			$event_calendar_page = self::get_event_calendar_page_instance();
			self::$event_calendar_page_callback = array( $event_calendar_page, 'render_page_content' );
		} // endif
		return self::$event_calendar_page_callback;
	} // function

	private static function add_event_calendar_page( $parent_slug, $position ) {

		$page_title = __( 'Administrative Calendar for Repair Café', 'reg-man-rc' );
		$menu_title = __( 'Admin Calendar', 'reg-man-rc' );
		
		// I want this page to be visible to people with different capabilities
		if ( current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) &&
			 current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {

			// This user can register items for any visitor
			$capability = 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
			
		} elseif ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) &&
					current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
			
			// This user can register any volunteer
			$capability = 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;
		
		} else {
			
			// All other users must be able to create events
			$capability = 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
			
		} // endif
		
		$page_slug = Admin_Event_Calendar_Page::PAGE_SLUG;
		$page_callback = self::get_event_calendar_page_callback();
		$icon_url = 'dashicons-calendar-alt';
		
		if ( empty( $parent_slug ) ) {
			
			// Add Event Calendar menu page 
			$hook_name = add_menu_page( $page_title, $menu_title, $capability, $page_slug, $page_callback, $icon_url, $position );
			self::$event_calendar_page_hook_name = $hook_name;
			
		} else {
			
			// Add Event Calendar submenu page
			$hook_name = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $page_slug, $page_callback, $position );
			self::$event_calendar_page_hook_name = $hook_name;
			
		} // endif
		
	} // function

	private static function add_stats_page( $parent_slug, $position ) {

		$page_title = __( 'Statistics for Repair Café ', 'reg-man-rc' );
		$menu_title = __( 'Statistics', 'reg-man-rc' );
		
		// I want this page to be visible to people with different capabilities
		if ( current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) &&
			 current_user_can( 'read_private_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {

			// This user can register items for any visitor
			$capability = 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
			
		} elseif ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) &&
					current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
			
			// This user can register any volunteer
			$capability = 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;

		} else {
			
			// All other users must be able to edit others items
			$capability = 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
			
		} // endif
		
		$page_slug = Admin_Stats_View::PAGE_SLUG;
		$page_callback = array( Admin_Stats_View::class, 'render_stats_page' ); // Show stats
		$icon_url = 'dashicons-chart-bar';
		
		if ( empty( $parent_slug ) ) {
			
			// Add Stats menu page 
			$hook_name = add_menu_page( $page_title, $menu_title, $capability, $page_slug, $page_callback, $icon_url, $position );
			self::$stats_page_hook_name = $hook_name;
			
		} else {
			
			// Add Stats submenu page
			$hook_name = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $page_slug, $page_callback, $position );
			self::$stats_page_hook_name = $hook_name;
			
		} // endif
		
	} // function
	
	/**
	 * Get the hook name for the event calendar page.  This is used to hook into specific events for this page
	 * @return string
	 */
	public static function get_event_calendar_page_hook_name() {
		return self::$event_calendar_page_hook_name;
	} // function
	
	/**
	 * Get the hook name for the stats page.  This is used to hook into specific events for this page
	 * @return string
	 */
	public static function get_stats_page_hook_name() {
		return self::$stats_page_hook_name;
	} // function
	
	private static function add_main_menu_page() {
		
		global $wp_version;

		$main_menu_slug = self::MENU_SLUG; // This will be the slug for my main menu item
		$event_calendar_page = self::get_event_calendar_page_instance();
		$event_calendar_page_callback = self::get_event_calendar_page_callback();

		// The capability here is 'edit_posts' rather than something like 'read'
		// See the note about current WP bug #50284 above
		$page_title = __( 'Repair Café', 'reg-man-rc' );
		$menu_title = __( 'Repair Café', 'reg-man-rc' );
		$notice_count = $event_calendar_page->get_notification_count();
		if ( $notice_count > 0 ) {
			$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
			$menu_title .= $notification_bubble;
		} // endif
		$capability = 'edit_posts'; // This has to be very low capability to allow access to its child pages
		$curr_slug = $main_menu_slug;
		$icon_url = ( version_compare( $wp_version, '5.5', '>=' ) ) ? 'dashicons-coffee' : 'dashicons-calendar';
		$position = self::get_menu_position();
		add_menu_page( $page_title, $menu_title, $capability, $curr_slug, $event_calendar_page_callback, $icon_url, $position );
		
	} // function

	/**
	 * Create the custom admin menu
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function create_menu() {
		
		if ( ! self::get_is_create_main_custom_menu_page() ) {
			
			// If there is no custom main menu page then we need to add the calendar and stats as menu pages
			// Otherwise, they will be submenu pages under our main page
			
			$position = self::get_menu_position();
			
			// Add Event Calendar menu page
			self::add_event_calendar_page( NULL, $position );
			
			// Add Statistics submenu page
			self::add_stats_page( NULL, $position );
			
			// Note that when there is no main custom menu page for Repair Cafe the custom post type UIs are added
			//  to the admin menu automatically when the CPTs register themselves
			//  and the taxonomies are added below the relevant CPT
			
		} else {

			// Add the main menu page
			self::add_main_menu_page();

			$parent_slug = self::MENU_SLUG; // The main menu item will be the parent for everything else

			$position = 0; // first child below the main menu page

			// Add Event Calendar submenu page
			self::add_event_calendar_page( $parent_slug, $position );

			// Add Statistics submenu page
			self::add_stats_page( $parent_slug, ++$position );

			// Note that the custom post type UIs are added to this menu page automatically when the CPTs register themselves

			// Add taxonomy submenu pages
			$tax_position = 9; // This currently puts my taxonomies after Fixers & Volunteers, and before Item Suggestions
			
			// Event Category
			self::add_event_categories_submenu_page( $parent_slug, $tax_position );

			// Fixer Station
			self::add_fixer_stations_submenu_page( $parent_slug, ++$tax_position );

			// Volunteer Role
			self::add_volunteer_roles_submenu_page( $parent_slug, ++$tax_position );

			// Item Type
			self::add_item_types_submenu_page( $parent_slug, ++$tax_position );

//			if ( $is_config_complete ) {
				$position = $tax_position + 10; // put it at the bottom of my items
				self::add_settings_submenu_page( $parent_slug, $position );
//			} // endif

		} // endif

	} // function
	
	private static function add_event_categories_submenu_page( $parent_slug, $tax_position ) {

		// FIXME - shouldn't we have capabilities for our taxonomies???
		if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			
			$tax_name = Event_Category::TAXONOMY_NAME;
			$tax = get_taxonomy( $tax_name );
			$labels = $tax->labels;
			$page_title = $labels->menu_name;
			$menu_title = $labels->menu_name;
			$capability = 'manage_categories';
			$curr_slug = "edit-tags.php?taxonomy=$tax_name";
			$function = NULL; // use the default rendering provided by Wordpress
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
			
		} // endif
		
	} // function
	
	private static function add_fixer_stations_submenu_page( $parent_slug, $tax_position ) {
	
		// FIXME - shouldn't we have capabilities for our taxonomies???
		if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ||
			 current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {

			 $tax_name = Fixer_Station::TAXONOMY_NAME;
			$tax = get_taxonomy( $tax_name );
			$labels = $tax->labels;
			$page_title = $labels->menu_name;
			$menu_title = $labels->menu_name;
			$capability = 'manage_categories';
			$curr_slug = "edit-tags.php?taxonomy=$tax_name";
			$function = NULL; // use the default rendering provided by Wordpress
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );

		} // endif
		
	} // function
	
	private static function add_volunteer_roles_submenu_page( $parent_slug, $tax_position ) {
	
		// FIXME - shouldn't we have capabilities for our taxonomies???
		if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {
			
			$tax_name = Volunteer_Role::TAXONOMY_NAME;
			$tax = get_taxonomy( $tax_name );
			$labels = $tax->labels;
			$page_title = $labels->menu_name;
			$menu_title = $labels->menu_name;
			$capability = 'manage_categories';
			$curr_slug = "edit-tags.php?taxonomy=$tax_name";
			$function = NULL; // use the default rendering provided by Wordpress
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
			
		} // endif
		
	} // function
	
	private static function add_item_types_submenu_page( $parent_slug, $tax_position ) {
	
		// FIXME - shouldn't we have capabilities for our taxonomies???
		if ( current_user_can( 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {

			$tax_name = Item_Type::TAXONOMY_NAME;
			$tax = get_taxonomy( $tax_name );
			$labels = $tax->labels;
			$page_title = $labels->menu_name;
			$menu_title = $labels->menu_name;
			$capability = 'manage_categories';
			$post_type = Item::POST_TYPE;
			$curr_slug = "edit-tags.php?taxonomy=$tax_name&post_type=$post_type";
			$function = NULL;
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );

		} // endif
		
	} // function
	
	private static function add_settings_submenu_page( $parent_slug, $position ) {

		$page_title = __( 'Registration Manager for Repair Café Settings', 'reg-man-rc' );
		$menu_title = __( 'Settings', 'reg-man-rc' );
		$capability = 'manage_options';
		$slug = 'options-general.php?page=' . Settings_Admin_Page::MENU_SLUG;
		$callback_function = NULL;
		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $slug, $callback_function, $position );
		
	} // function

	/**
	 * Assign the correct parent file and submenu file for my menu items
	 * @param string $parent_file
	 * @return string
	 */
	public static function assign_current_submenu( $parent_file ) {
		global $current_screen;
		global $submenu_file;
		// FIXME - pagenow is not necessary, I think
//		global $pagenow;

//		Error_Log::var_dump( $parent_file, $current_screen->base, $submenu_file );

		if ( self::get_is_create_main_custom_menu_page() ) {
			// Set the submenu as current (highlight it in the view) for the submenu items of this page

			$base = $current_screen->base;

			$cpt_array = array(
					Internal_Event_Descriptor::POST_TYPE,
					Item::POST_TYPE,
					Volunteer::POST_TYPE,
					Volunteer_Registration::POST_TYPE,
					Calendar::POST_TYPE,
					Venue::POST_TYPE,
			);

			$taxonomy_array = array(
					Event_Category::TAXONOMY_NAME,
					Item_Type::TAXONOMY_NAME,
					Fixer_Station::TAXONOMY_NAME,
					Volunteer_Role::TAXONOMY_NAME,
			);

			if ( ( $base == 'edit' ) && ( in_array( $current_screen->post_type, $cpt_array ) ) ) {
				
				// This is a list page for one of my custom post types and their menu items are under the main menu slug
				$parent_file = self::MENU_SLUG;

			} elseif ( ( $base == 'post' ) && ( in_array( $current_screen->post_type, $cpt_array ) ) ) {
				
				// This is an editor page for one of my CPTs and their menu items are under the main menu slug
				$parent_file = self::MENU_SLUG;
				
				// FIXME - submenu_file is not necessary here, I think
//				$submenu_file = "edit.php?post_type={$current_screen->post_type}";

			} elseif ( ( ( $base == 'edit-tags' ) || ( $base == 'term' ) ) && ( in_array( $current_screen->taxonomy, $taxonomy_array ) ) ) {

				// This is one of my taxonomies

				$parent_file = self::MENU_SLUG; // All of the above are my taxonomies and their menu items are under the main menu slug
//				Error_Log::var_dump( $parent_file, $submenu_file, $current_screen->post_type );
				// Some taxonomy urls use the default post type of 'post' because they belong to multiple custom post types, e.g. Fixer Stations
				// In any of those cases I will change the submenu file to remove the post_type portion and simply refer to the taxonomy
				if ( $current_screen->post_type == 'post' ) {
					$submenu_file = "edit-tags.php?taxonomy={$current_screen->taxonomy}";
				} else {
					$submenu_file = "edit-tags.php?taxonomy={$current_screen->taxonomy}&post_type={$current_screen->post_type}";
				} // endif
			} // endif

		} // endif

		return $parent_file;

	} // function

} // class
