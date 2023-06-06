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

/**
 * The custom admin menu page for the plugin
 *
 */

class Admin_Menu_Page {

	const MENU_SLUG = 'reg-man-rc-admin';

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
//		if ( current_user_can( 'edit_posts' ) ) {
		if ( current_user_can( 'edit_posts' ) &&
				(	current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL )
				)
			) {
			$result = TRUE;
		} else {
			$result = FALSE;
		} // endif
		return $result;
	} // function

	/**
	 * Get a flag to indicate whether or not the main custom menu page will contain submenu pages
	 * @return boolean
	 */
	private static function get_has_submenu_pages() {
		if ( ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_categories' ) ) &&
				(	current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ||
					current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL )
				) ) {
			$result = TRUE;
		} else {
			$result = FALSE;
		} // endif
		return $result;
	} // function

	/**
	 * Get the correct value for my custom post types to use for 'show_in_menu' when they register.
	 *
	 * This value determines how my custom menu items appear in the admin menu.
	 * If this function returns my MENU_SLUG then the CPTs will have their UIs created as submenu pages under the custom menu.
	 * If this function returns TRUE then the CPT UIs will be top-level items in the admin menu.
	 * If this function returns FALSE then CPTs will have no UI shown at all.
	 * @param	string	$capability_type_plural	The plural version of the capability type used to register the CPT.
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function get_CPT_show_in_menu( $capability_type_plural ) {
		if ( self::get_is_create_main_custom_menu_page() ) {
			// When we are creating the custom menu, all CPTs will go inside it
			$result = self::MENU_SLUG;
		} elseif ( current_user_can( 'edit_others_' . $capability_type_plural ) ) {
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
		return $result;
	} // function

	/**
	 * Create the custom admin menu
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function create_menu() {
		global $wp_version;
		if ( self::get_is_create_main_custom_menu_page() ) {

			$main_menu_slug = self::MENU_SLUG; // This will be the slug for my main menu item

			$dashboard = Admin_Dashboard_Page::create();
			$main_page_callback = array( $dashboard, 'render_page_content' );

			$page_title = __( 'Repair Café', 'reg-man-rc' );
			$menu_title = __( 'Repair Café', 'reg-man-rc' );
			$notice_count = $dashboard->get_notification_count();
			if ( $notice_count > 0 ) {
				$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
				$menu_title .= $notification_bubble;
			} // endif
			$capability = 'edit_posts'; //edit_others_posts'; // This has to be very low capability to allow access to its child pages
			$curr_slug = $main_menu_slug;
			$icon_url = ( version_compare( $wp_version, '5.5', '>=' ) ) ? 'dashicons-coffee' : 'dashicons-calendar';
			$position = 6; // This is after "Posts"
			add_menu_page( $page_title, $menu_title, $capability, $curr_slug, $main_page_callback, $icon_url, $position );

			$parent_slug = $main_menu_slug; // The main menu item will be the parent for everything else

			if ( self::get_has_submenu_pages() ) {
				$position = 0;

				$page_title = __( 'Dashboard for Repair Café', 'reg-man-rc' );
				$menu_title = __( 'Dashboard', 'reg-man-rc' );
				$capability = 'edit_posts'; // TODO: Is that right? What should be here?
				$curr_slug = Admin_Dashboard_Page::PAGE_SLUG;
				add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $main_page_callback, $position );

				$position++;
				$page_title = __( 'Statistics for Repair Café ', 'reg-man-rc' );
				$menu_title = __( 'Statistics', 'reg-man-rc' );
				$capability = 'edit_posts'; // TODO: Is that right? What should be here?
				$curr_slug = Admin_Stats_View::PAGE_SLUG;
				$page_callback = array( Admin_Stats_View::class, 'render_stats_page' ); // Show stats
				add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $page_callback, $position );

			} // endif

			// Note that the custom post type UIs are added to this menu page automatically when the CPTs register themselves

			if ( current_user_can( 'manage_categories' ) ) {
				// Create submenu pages for the custom taxonomies
				$tax_position = 10;

				// Event Category
				if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
					$tax_name = Event_Category::TAXONOMY_NAME;
					$tax = get_taxonomy( $tax_name );
					$labels = $tax->labels;
					$page_title = $labels->menu_name;
					$menu_title = $labels->menu_name;
					$capability = 'manage_categories';
					$curr_slug = "edit-tags.php?taxonomy=$tax_name";
					$function = NULL; // use the default rendering provided by Wordpress
					$tax_position++;
					add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
				} // endif

				// Item Type
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
					$tax_position++;
					add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
				} // endif

				// Fixer Station
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
					$tax_position++;
					add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
				} // endif

				// Volunteer Role
				if ( current_user_can( 'edit_others_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {
					$tax_name = Volunteer_Role::TAXONOMY_NAME;
					$tax = get_taxonomy( $tax_name );
					$labels = $tax->labels;
					$page_title = $labels->menu_name;
					$menu_title = $labels->menu_name;
					$capability = 'manage_categories';
					$post_type = Internal_Event_Descriptor::POST_TYPE;
					$curr_slug = "edit-tags.php?taxonomy=$tax_name&post_type=$post_type";
					$function = NULL; // use the default rendering provided by Wordpress
					$tax_position++;
					add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $curr_slug, $function, $tax_position );
				} // endif

			} // endif

//			if ( $is_config_complete ) {
				$page_title = __( 'Settings', 'reg-man-rc' );
				$menu_title = __( 'Settings', 'reg-man-rc' );
				$capability = 'manage_options';
				$slug = 'options-general.php?page=' . Settings_Admin_Page::MENU_SLUG;
	//			$url = admin_url( 'options-general.php?page=' . Settings_Admin_Page::MENU_SLUG );
				$function = NULL;
				$position = $tax_position + 10; // put it at the bottom of my items
				add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $slug, $function, $position );
//			} // endif
		} // endif

	} // function

	/**
	 * Assign the correct parent file and submenu file for my menu items
	 * @param string $parent_file
	 * @return string
	 */
	public static function assign_current_submenu( $parent_file ) {
		global $submenu_file, $current_screen, $pagenow;

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
				$submenu_file = "edit.php?post_type={$current_screen->post_type}";

			} elseif ( ( ( $base == 'edit-tags' ) || ( $base == 'term' ) )
				&& ( in_array( $current_screen->taxonomy, $taxonomy_array ) ) ) {
				// This is one of my taxonomies

				$parent_file = self::MENU_SLUG; // All of the above are my taxonomies and their menu items are under the main menu slug

				// Some taxonomy urls contain the default post type of 'post' because they belong to multiple custom post types, e.g. Fixer Stations
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
