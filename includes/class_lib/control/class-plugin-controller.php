<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use const Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME;

/**
 * Sets up the action and filter hooks for the plugin like plugin activation, deactivation, init hook and so on
 *
 * @since	v0.1.0
 *
 */
class Plugin_Controller {

// TODO - This was for running initial configuration during plugin activation
// But I couldn't get it to work propery
//	private static	$INITIAL_CONFIG_OPTION_NAME	= 'reg-man-rc-initial-config';
//	private static	$INITIAL_CONFIG_TRIGGER		= 'trigger';
//	private static	$INITIAL_CONFIG_COMPLETE	= 'complete';

	/**
	 * Register the plugin action and filter hooks.
	 *
	 * This method is called by the plugin's bootstrap file to set up the plugin's action and filter hooks.
	 * This is the only method in this class that should be called directly, all other methods are invoked
	 * automatically by an action or filter hook.
	 *
	 * @return	void
	 * @param	string	$plugin_bootstrap_filename	The filename (__FILE__) of the plugin's bootstrap file
	 *
	 * @since	v0.1.0
	 */
	public static function register() {

		$plugin_bootstrap_filename = PLUGIN_BOOTSTRAP_FILENAME;

		register_activation_hook( $plugin_bootstrap_filename, array( __CLASS__, 'handle_plugin_activation' ) );

		register_deactivation_hook( $plugin_bootstrap_filename, array( __CLASS__, 'handle_plugin_deactivation' ) );

		// Register our template controller
		\Reg_Man_RC\Control\Template_Controller::register();

		add_action( 'init', array( __CLASS__, 'handle_init' ) );

		add_filter( 'display_post_states', array( __CLASS__, 'filter_display_post_states' ), 10, 2 );
		
		add_filter( 'add_meta_boxes', array( __CLASS__, 'remove_unwanted_metaboxes' ), 10, 2 );

		// FIXME - avoid doing this in the testing environment by mistake
//		register_uninstall_hook( $plugin_bootstrap_filename,  array( __CLASS__, 'handle_plugin_uninstall' ) );

	} // function

	/**
	 * Filter the "state" displayed for posts (and pages).
	 * We use this to mark our automatically generated pages like Visitor Registration
	 * @param string[]	$post_states
	 * @param \WP_Post	$post
	 * @return string[]
	 */
	public static function filter_display_post_states( $post_states, $post ) {
		$reg_man_page_id = Visitor_Reg_Manager::get_post_id();
		$vol_area_page_id = Volunteer_Area::get_post_id();
		$visitor_reg_calendar = Calendar::get_visitor_registration_calendar();
		$visitor_reg_calendar_id = isset( $visitor_reg_calendar ) ? $visitor_reg_calendar->get_post_id() : NULL;
		$volunteer_reg_calendar = Calendar::get_volunteer_registration_calendar();
		$volunteer_reg_calendar_id = isset( $volunteer_reg_calendar ) ? $volunteer_reg_calendar->get_post_id() : NULL;
		if ( $post->ID == $reg_man_page_id ) {
			$post_states[] = __( 'Repair Café Visitor Registration Page', 'reg-man-rc' );
		} elseif ( $post->ID == $vol_area_page_id ) {
			$post_states[] = __( 'Repair Café Volunteer Area', 'reg-man-rc' );
		} elseif ( ( $post->ID == $visitor_reg_calendar_id ) && ( $visitor_reg_calendar_id == $volunteer_reg_calendar_id ) ) {
			$post_states[] = __( 'Visitor and Volunteer Registration Calendar', 'reg-man-rc' );
		} elseif ( $post->ID == $visitor_reg_calendar_id ) {
			$post_states[] = __( 'Visitor Registration Calendar', 'reg-man-rc' );
		} elseif ( $post->ID == $volunteer_reg_calendar_id ) {
			$post_states[] = __( 'Volunteer Registration Calendar', 'reg-man-rc' );
		} // endif
		return $post_states;
	} // function

	/**
	 * Handle activation of the plugin
	 *
	 * This method is called automatically when the plugin is activated.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function handle_plugin_activation() {
		// Model elements
		\Reg_Man_RC\Model\Visitor::handle_plugin_activation();
		\Reg_Man_RC\Model\Volunteer::handle_plugin_activation();
		// View elements
		\Reg_Man_RC\View\Pub\Volunteer_Area::handle_plugin_activation();
		// Control elements
		\Reg_Man_RC\Control\User_Role_Controller::handle_plugin_activation();
		\Reg_Man_RC\Model\Stats\Supplemental_Item::handle_plugin_activation();
		\Reg_Man_RC\Model\Stats\Supplemental_Visitor_Registration::handle_plugin_activation();
		\Reg_Man_RC\Model\Stats\Supplemental_Volunteer_Registration::handle_plugin_activation();
	} // function

	/**
	 * Perform plugin initialization for init hook.
	 *
	 * This method is called automatically when the init hook is triggered.
	 *
	 * @since v0.1.0
	 */
	public static function handle_init() {
		// Load our text domain
		self::load_textdomain();

		// Register the scripts and styles used by the plugin
		\Reg_Man_RC\Control\Scripts_And_Styles::register();

		// Initialize the parts that are used on both the front and back ends

		// Register the Model elements to set up custom post types etc.
		// Note that the order here can affect the order these things show up in the admin menu
		\Reg_Man_RC\Model\Internal_Event_Descriptor::register();
		\Reg_Man_RC\Model\Item::register();
		\Reg_Man_RC\Model\Volunteer_Registration::register();
		\Reg_Man_RC\Model\Venue::register();
		\Reg_Man_RC\Model\Calendar::register();
		\Reg_Man_RC\Model\Visitor::register();
		\Reg_Man_RC\Model\Volunteer::register();
		\Reg_Man_RC\Model\Item_Suggestion::register();
		
		// Taxonomies
		\Reg_Man_RC\Model\Event_Category::register();
		\Reg_Man_RC\Model\Item_Type::register();
		\Reg_Man_RC\Model\Fixer_Station::register();
		\Reg_Man_RC\Model\Volunteer_Role::register();

		// Create required elements if they don't already exist
		\Reg_Man_RC\Model\Calendar::get_visitor_registration_calendar();
		\Reg_Man_RC\Model\Calendar::get_volunteer_registration_calendar();

		// Misc
		\Reg_Man_RC\Model\Event_Key::register();

		// Register shared view elements
		\Reg_Man_RC\View\Calendar_View::register();
		\Reg_Man_RC\View\Event_Descriptor_View::register();
		\Reg_Man_RC\View\Venue_View::register();
		
		// Register the calendar controller which includes a REST API registration
		\Reg_Man_RC\Control\Calendar_Controller::register();

		// Register the controllers which may be used on both front and back end
		\Reg_Man_RC\Control\Internal_Event_Descriptor_Controller::register();
		\Reg_Man_RC\Control\Ajax_Chart_View_Controller::register();
		\Reg_Man_RC\Control\Map_Controller::register();
		\Reg_Man_RC\Control\Term_Order_Controller::register();
		\Reg_Man_RC\Control\Visitor_Controller::register();
		\Reg_Man_RC\Control\Volunteer_Controller::register();
		\Reg_Man_RC\Control\Volunteer_Registration_Controller::register();
		\Reg_Man_RC\Control\Comments\Comments_Controller::register();
		\Reg_Man_RC\Control\Comments\Volunteer_Area_Comments_Controller::register();
		\Reg_Man_RC\Control\Visitor_Registration_Controller::register();
		
		// Initialize the parts used only on back end or front end, depending on what's being rendered right now
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin

			// Register the controllers used on the admin side
			\Reg_Man_RC\Control\Admin\Permalink_Settings_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Venue_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Item_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Item_Suggestion_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Item_Type_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Event_Category_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Fixer_Station_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Calendar_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Volunteer_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Visitor_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Volunteer_Registration_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Supplemental_Event_Data_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Volunteer_Role_Admin_Controller::register();
			// TODO: Is Item Import necessary?
//			\Reg_Man_RC\Control\Admin\Item_Import_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Visitor_Import_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Volunteer_Import_Admin_Controller::register();
			\Reg_Man_RC\Control\Admin\Table_View_Admin_Controller::register();

			// Register the views
			\Reg_Man_RC\View\Admin\Admin_Menu_Page::register();
			\Reg_Man_RC\View\Admin\Admin_Dashboard_Page::register();
			\Reg_Man_RC\View\Admin\Admin_Help_View::register();
			\Reg_Man_RC\View\Admin\Admin_Stats_View::register();
			\Reg_Man_RC\View\Admin\Calendar_Admin_View::register();
			\Reg_Man_RC\View\Admin\Internal_Event_Descriptor_Admin_View::register();
			\Reg_Man_RC\View\Admin\Venue_Admin_View::register();
			\Reg_Man_RC\View\Admin\Item_Admin_View::register();
			\Reg_Man_RC\View\Admin\Item_Suggestion_Admin_View::register();
			\Reg_Man_RC\View\Admin\Item_Type_Admin_View::register();
			\Reg_Man_RC\View\Admin\Event_Category_Admin_View::register();
			\Reg_Man_RC\View\Admin\Fixer_Station_Admin_View::register();
			\Reg_Man_RC\View\Admin\Visitor_Admin_View::register();
			\Reg_Man_RC\View\Admin\Volunteer_Admin_View::register();
			\Reg_Man_RC\View\Admin\Volunteer_Role_Admin_View::register();
			\Reg_Man_RC\View\Admin\Volunteer_Registration_Admin_View::register();
			\Reg_Man_RC\View\Admin\Supplemental_Event_Data_Admin_View::register();
			// TODO: Is Item Import necessary?
//			\Reg_Man_RC\View\Admin\Item_Import_Admin_View::register();
			\Reg_Man_RC\View\Admin\Visitor_Import_Admin_View::register();
			\Reg_Man_RC\View\Admin\Volunteer_Import_Admin_View::register();
			\Reg_Man_RC\View\Admin\Term_Order_Admin_View::register();

			\Reg_Man_RC\View\Admin\Settings_Admin_Page::register();

		} else {

			// Register the views used on the public side
//			\Reg_Man_RC\View\Pub\Virtual_Page_View::register();
			\Reg_Man_RC\View\Pub\Visitor_Reg_Manager::register(); // The UI is on the public side but user must be logged in to use it
//			\Reg_Man_RC\View\Pub\Internal_Event_Descriptor_View::register();
			\Reg_Man_RC\View\Pub\Volunteer_View::register();
			\Reg_Man_RC\View\Pub\Volunteer_Area::register();
			\Reg_Man_RC\View\Stats\Repairs_Chart_View::register();
			
		} // endif
	} // function

	/**
	 * Remove unwanted metaboxes from my post types
	 * @param string	$post_type
	 * @param \WP_Post	$post
	 */
	public static function remove_unwanted_metaboxes( $post_type, $post ) {
		$post_types_array = array(
				\Reg_Man_RC\Model\Internal_Event_Descriptor::POST_TYPE,
				\Reg_Man_RC\Model\Item::POST_TYPE,
				\Reg_Man_RC\Model\Volunteer_Registration::POST_TYPE,
				\Reg_Man_RC\Model\Venue::POST_TYPE,
				\Reg_Man_RC\Model\Calendar::POST_TYPE,
				\Reg_Man_RC\Model\Visitor::POST_TYPE,
				\Reg_Man_RC\Model\Volunteer::POST_TYPE,
				\Reg_Man_RC\Model\Item_Suggestion::POST_TYPE,
		);
		if ( in_array( $post_type, $post_types_array ) ) {
			$metaboxes_array = array(
					// ID => position
					'postcustom'	=> 'normal',
					'slugdiv'		=> 'normal',
			);
			foreach( $metaboxes_array as $metabox_id => $metabox_position ) {
				remove_meta_box( $metabox_id, $post_type, $metabox_position );
			} // endfor
		} // endif
	} // function
	
	/**
	 * Load the plugin text domain for translation
	 *
	 * @since v0.1.0
	 */
	private static function load_textdomain() {
		$lang_dir = dirname( dirname( dirname( dirname( plugin_basename(__FILE__) ) ) ) ) . '/languages';
		load_plugin_textdomain( 'reg-man-rc', FALSE, $lang_dir );
	} // function

	/**
	 * Handle deactivation of the plugin
	 *
	 * This method is called automatically when the plugin is deactivated.
	 *
	 * @since v0.1.0
	 */
	public static function handle_plugin_deactivation() {
		// View
		\Reg_Man_RC\View\Pub\Visitor_Reg_Manager::handle_plugin_deactivation();
		\Reg_Man_RC\View\Pub\Volunteer_Area::handle_plugin_deactivation();
		// Control
		\Reg_Man_RC\Control\User_Role_Controller::handle_plugin_deactivation();
	} // function

	/**
	 * Handle plugin uninstall by removing custom posts, taxonomies, database tables and so on.
	 *
	 * This method is called automatically when the plugin is uninstalled.
	 *
	 * @since v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		\Reg_Man_RC\Model\Event_Category::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Fixer_Station::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Internal_Event_Descriptor::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Item::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Venue::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Visitor::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Volunteer::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Stats\Supplemental_Item::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Stats\Supplemental_Visitor_Registration::handle_plugin_uninstall();
		\Reg_Man_RC\Model\Stats\Supplemental_Volunteer_Registration::handle_plugin_uninstall();
	} // function


} // class