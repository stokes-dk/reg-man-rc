<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Map_View;
use const Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Pub\Volunteer_Area;

/**
 * The settings admin page for the plugin
 *
 */

class Settings_Admin_Page {

	const MENU_SLUG					= 'reg-man-rc-settings';

	const VISITOR_REGISTRATION_TAB_ID	= 'visitor-reg';
	const EVENT_TAB_ID					= 'events';
//	const CALENDARS_TAB_ID				= 'calendars';
	const GOOGLE_MAPS_TAB_ID			= 'maps';
	const VOLUNTEER_AREA_TAB_ID			= 'volunteer-area';
	
	// The following key is used in the settings admin view to register the setting
	const ALLOW_VOLUNTEER_AREA_COMMENTS_OPTION_NAME		= 'reg-man-rc-volunteer-area-is-allow-comments';

	private $tabs_array; // the tab IDs and titles
	const DEFAULT_TAB = self::VISITOR_REGISTRATION_TAB_ID;

	private function __construct() {
	} // construct

	/**
	 * Create a new instance of this view
	 * @return Settings_Admin_Page
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function
	
	private static function get_active_tab() {
		$result = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : self::VISITOR_REGISTRATION_TAB_ID;
		return $result;
	} // function
	
	private function get_tabs_array() {
		if ( ! isset( $this->tabs_array ) ) {
			$this->tabs_array = array(
				self::VISITOR_REGISTRATION_TAB_ID	=> __( 'Visitor Registration', 'reg-man-rc' ),
				self::VOLUNTEER_AREA_TAB_ID			=> __( 'Volunteer Area', 'reg-man-rc' ),
				self::EVENT_TAB_ID					=> __( 'Events', 'reg-man-rc' ),
				self::GOOGLE_MAPS_TAB_ID			=> __( 'Maps', 'reg-man-rc' ),
			);
		} // endif
		return $this->tabs_array;
	} // function

	public function render() {

		global $wp_settings_sections; // I need this global in order to echo section headings
		
		// Heading
		$heading = esc_html( __( 'Registration Manager for Repair Café Settings', 'reg-man-rc' ) );
		echo "<h1>$heading</h1>";
		
		// Navigation Tabs
		$page_slug = self::MENU_SLUG;
		$active_tab = $this->get_active_tab();
		$tabs_array = $this->get_tabs_array();
		$tab_format = '<a href="%2$s" class="nav-tab %3$s">%1$s</a>';
		echo '<nav class="nav-tab-wrapper">';
		$href_args = array( 'page' => $page_slug );
			foreach( $tabs_array as $id => $title ) {
				$href_args[ 'tab' ] = $id;
				$href = add_query_arg( $href_args, '' );
				$class = ( $id == $active_tab ) ? 'nav-tab-active' : '';
				printf( $tab_format, $title, $href, $class );
			} // endfor
		echo '</nav>';

		$settings_sections = $wp_settings_sections[ $page_slug ];
		
		echo '<form method="post" action="options.php">';
		
			settings_fields( $active_tab ); // The tab ID is the group ID
			
//			Error_Log::var_dump( $settings_sections );
			
			foreach( $settings_sections as $section_id => $section ) {
				
//				Error_Log::var_dump( $active_tab, $section_id, strpos( $section_id, $active_tab ) );
				
				if ( strpos( $section_id, $active_tab ) === 0 ) {
					// If the current section ID starts with the active tab name then show it

					if ( $section['title'] ) {
						echo "<h2>{$section['title']}</h2>\n";
					} // endif
					
					if ( $section['callback'] ) {
						call_user_func( $section['callback'], $section );
					} // endif
					
					echo '<table class="form-table" role="presentation">';
						
						do_settings_fields( self::MENU_SLUG, $section_id );
						
					echo '</table>';
					
				} // endif
			
			} // endfor

			submit_button();
			
		echo '</form>';

	} // function

	/**
	 * Register this view
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {
		if ( is_admin() ) {

			// Use admin_init hook to set up my settings page tabs and fields
			add_action( 'admin_init', array( __CLASS__, 'handle_admin_init' ) );

			// My settings menu page is created during the admin_menu hook
			add_action( 'admin_menu', array( __CLASS__, 'handle_admin_menu' ) );

			// Regsiter to enqueue the necessary scripts and styles as needed
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

			// Add a 'settings' link to the actions for my plugin
			$file_name = plugin_basename( PLUGIN_BOOTSTRAP_FILENAME );
			add_filter( 'plugin_action_links_' . $file_name, array( __CLASS__, 'filter_action_links' ) );

		} // endif
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) && ( $screen->base == 'settings_page_' . self::MENU_SLUG ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			// I need to allow the user to select an image from the media library
			// To enable that I will enqueue the Wordpress media scripts, styles etc.
			wp_enqueue_media();
			// Google maps
			Scripts_And_Styles::enqueue_google_maps();
		} // endif
	} // function

	/**
	 * Handle the admin_menu hook to create the page and set up its rendering function
	 */
	public static function handle_admin_menu() {

		// Create my settings page
		$page_title = __( 'Registration Manager for Repair Café Settings', 'reg-man-rc' );
		$menu_title = __( 'Registration Manager Settings', 'reg-man-rc' );
		$capability = 'manage_options';
		$slug = self::MENU_SLUG;
		$callback = array( __CLASS__, 'render_settings_page' );
		add_options_page( $page_title, $menu_title, $capability, $slug, $callback );

	} // function

	/**
	 * Render the settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) )	{
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'reg-man-rc' ) );
		} // endif
		echo '<div class="wrap">';
			$view = self::create();
			$view->render();
		echo '</div>';
	} // function

	/**
	 * Handle the admin_init hook
	 */
	public static function handle_admin_init() {
		
		self::create_tabs();

	} // function
	
	private static function create_tabs() {
		
		self::create_visitor_registration_tab();
		self::create_volunteer_area_tab();
		self::create_events_tab();
		self::create_google_maps_tab();
		
	} // function

	/**
	 * Create the visitor registration settings tab
	 */
	private static function create_visitor_registration_tab() {

		$tab_id = self::VISITOR_REGISTRATION_TAB_ID;
		$page_slug = self::MENU_SLUG;
		$option_group = $tab_id; // For simplicity, the option group is the tab ID
		

		// Main section
		$section_id = "{$tab_id}-main";
		$title = __( 'Visitor registration page', 'reg-man-rc' );
		$desc_fn = array( __CLASS__, 'render_visitor_reg_main_section_desc' ); // used to echo description content between heading and fields
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );
		
		// Calendar
		$option_name = Settings::VISITOR_REG_CALENDAR_OPTION_KEY;
		$title = __( 'Event calendar', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_visitor_reg_calendar_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );
		
		// House Rules
		$option_name = Settings::HOUSE_RULES_POST_ID_OPTION_KEY;
		$title = __( 'House rules page', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_visitor_reg_house_rules_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );
		
	} // function
	
	public static function render_visitor_reg_main_section_desc() {
		// We will show the link for the registration page here
		$post = Visitor_Reg_Manager::get_post();
		if ( isset( $post ) ) {
			$post_id = $post->ID;
			$view_href = get_page_link( $post_id );
			$view_link = "<a href=\"$view_href\">$view_href</a>";
			echo "<p><b>$view_link</b></p>";
		} // endif
	} // function
	
	public static function render_visitor_reg_calendar_input() {
		
		$all_calendars_array = Calendar::get_all_calendars();
		
		$selected_calendar_id = Settings::get_visitor_registration_calendar_post_id();

		$classes = '';
		$input_name = Settings::VISITOR_REG_CALENDAR_OPTION_KEY;
		echo "<select class=\"$classes\" name=\"$input_name\" autocomplete=\"off\">";
		
			foreach ( $all_calendars_array as $calendar ) {
				$calendar_id = $calendar->get_id();
				$title = esc_html( $calendar->get_name() );
				$selected = selected( $selected_calendar_id, $calendar_id, FALSE );
				echo "<option value=\"$calendar_id\" $selected>$title</option>";
			} // endfor
		
		echo '</select>';

		echo '<p>';
			$msg = __( 'You will be able to register visitors and their items for any event on the selected calendar',
					'reg-man-rc' );
			echo $msg;
		echo '</p>';

	} // function

	public static function render_visitor_reg_house_rules_input() {

		$all_page_ids_array = get_all_page_ids();

		$selected_post_id = Settings::get_house_rules_post_id();
		
		$classes = '';
		$input_name = Settings::HOUSE_RULES_POST_ID_OPTION_KEY;
		// Disabled to start with until it is initialized on the client side
		echo "<select class=\"combobox $classes\" name=\"$input_name\" autocomplete=\"off\" disabled=\"disabled\">";
		
			$title = esc_html( __( '-- No house rules page --', 'reg-man-rc' ) );
			$selected = empty( $selected_post_id ) ? 'selected="selected"' : '';
			echo "<option value=\"0\" $selected>$title</option>";

			foreach ( $all_page_ids_array as $page_id ) {
				$post = get_post( $page_id );
				$title = $post->post_title;
				$selected = selected( $selected_post_id, $page_id, FALSE );
				echo "<option value=\"$page_id\" $selected>$title</option>";
			} // endfor
		
		echo '</select>';

		echo '<p>';
			$msg = __( 'Select the page containg the house rules to appear in the visitor registration form',
					'reg-man-rc' );
			echo $msg;
		echo '</p>';
		
	} // function

	
	/**
	 * Create the volunteer area settings tab
	 */
	private static function create_volunteer_area_tab() {

		$tab_id = self::VOLUNTEER_AREA_TAB_ID;
		$page_slug = self::MENU_SLUG;
		$option_group = $tab_id; // For simplicity, the option group is the tab ID

		// Main section
		$section_id = "{$tab_id}-main";		
		$title = __( 'Volunteer area page', 'reg-man-rc' );
		$desc_fn = array( __CLASS__, 'render_volunteer_area_main_section_desc' ); // used to echo description content between heading and fields
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		// Calendar
		$option_name = Settings::VOLUNTEER_REG_CALENDAR_OPTION_KEY;
		$title = __( 'Event calendar', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_volunteer_area_calendar_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );
		
		// Require Password
		$option_name = Settings::REQUIRE_VOLUNTEER_AREA_REGISTERED_USER_OPTION_KEY;
		$title = __( 'Require password', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_require_volunteer_area_registered_user_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Allow Comments
		$option_name = self::ALLOW_VOLUNTEER_AREA_COMMENTS_OPTION_NAME;
		$title = __( 'Allow comments', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_allow_volunteer_area_comments_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		$args = array( 'sanitize_callback' => array( __CLASS__, 'sanitize_allow_volunteer_area_comments' ) );
		register_setting( $option_group, $option_name, $args );

	} // function
	
	
	public static function render_volunteer_area_main_section_desc() {
		// We will show the link for the volunteer area page here
		$post = Volunteer_Area::get_post();
		if ( isset( $post ) ) {
			$post_id = $post->ID;
			$view_href = get_page_link( $post_id );
			$view_link = "<a href=\"$view_href\">$view_href</a>";
			echo "<p><b>$view_link</b></p>";
		} // endif
	} // function
	
	public static function render_volunteer_area_calendar_input() {
		
		$all_calendars_array = Calendar::get_all_calendars();
		
		$selected_calendar_id = Settings::get_volunteer_registration_calendar_post_id();

		$classes = '';
		$input_name = Settings::VOLUNTEER_REG_CALENDAR_OPTION_KEY;
		echo "<select class=\"$classes\" name=\"$input_name\" autocomplete=\"off\">";
		
			foreach ( $all_calendars_array as $calendar ) {
				$calendar_id = $calendar->get_id();
				$title = esc_html( $calendar->get_name() );
				$selected = selected( $selected_calendar_id, $calendar_id, FALSE );
				echo "<option value=\"$calendar_id\" $selected>$title</option>";
			} // endfor
		
		echo '</select>';

		echo '<p>';
			$msg = __( 'Volunteers will be able to register to attend any event on the selected calendar',
					'reg-man-rc' );
			echo $msg;
		echo '</p>';

	} // function

	
	
	/**
	 * "Sanitize" the value for the setting to allow volunteer area comments.
	 * This will be called when the settings are saved.
	 * Because this value is not stored in the options table, I will handle saving it myself here.
	 * @param string $value
	 * @return boolean
	 */
	public static function sanitize_allow_volunteer_area_comments( $value ) {
		if ( isset( $value ) ) {
			Settings::set_is_allow_volunteer_area_comments( $value == '1' );
		} // endif
		return FALSE; // This will prevent WP from storing the value in the options table, which is what I want here
	} // function

	/**
	 * Create the event settings tab
	 */
	private static function create_events_tab() {

		$tab_id = self::EVENT_TAB_ID;
		$page_slug = self::MENU_SLUG;
		$option_group = $tab_id; // For simplicity, the option group is the tab ID
		
		// Main section
		$section_id = "{$tab_id}-main";
		$title = ''; // __( 'Event settings', 'reg-man-rc' );
		$desc_fn = NULL; // used to echo description content between heading and fields
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		// Event start time
		$option_name = Settings::EVENT_START_TIME_OPTION_KEY;
		$title = __( 'Default start time', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_default_event_start_time_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Event duration
		$option_name = Settings::EVENT_DURATION_OPTION_KEY;
		$title = __( 'Default event duration', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_default_event_duration_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Allow multiple categories for events
		$option_name = Settings::ALLOW_MULTI_EVENT_CATS_OPTION_KEY;
		$title = __( 'Allow multiple event categories', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_allow_multi_event_cats_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

	} // function

	/**
	 * Create the Google mpas settings tab
	 */
	private static function create_google_maps_tab() {

		$tab_id = self::GOOGLE_MAPS_TAB_ID;
		$page_slug = self::MENU_SLUG;
		$option_group = $tab_id; // For simplicity, the option group is the tab ID
		
		// Main section
		$section_id = "{$tab_id}-main";		
		$title = ''; // __( 'Google Maps', 'reg-man-rc' );
		$desc_fn = array( __CLASS__, 'render_google_maps_tab_description' ); // used to echo description content between heading and fields
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		// Google Maps API Key
		$option_name = Settings::GOOGLE_MAPS_API_KEY_OPTION_KEY;
		$title = __( 'Google maps API key', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_google_maps_api_key_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// We will only show the remaining inputs once the API key is entered
		if ( Map_View::get_is_map_view_enabled() ) {

			// Place input
			$option_name = Settings::GOOGLE_MAPS_DEFAULT_CENTRE_PLACE_OPTION_KEY;
			$title = __( 'Your geographic region', 'reg-man-rc' );
			$render_fn = array( __CLASS__, 'render_google_maps_default_centre_input' );
			add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
			register_setting( $option_group, $option_name );

			// Geo input (will be rendered by function above but must be registered here)
			$option_name = Settings::GOOGLE_MAPS_DEFAULT_CENTRE_GEO_OPTION_KEY;
			register_setting( $option_group, $option_name );

			// Zoom input (will be rendered by function above but must be registered here)
			$option_name = Settings::GOOGLE_MAPS_DEFAULT_ZOOM_OPTION_KEY;
			register_setting( $option_group, $option_name );

		} // endif

	} // function


	public static function render_google_maps_tab_description() {
		if ( ! Map_View::get_is_map_view_enabled() ) {
			$desc = __( 'To use maps you will need to have a valid Google maps API key.', 'reg-man-rc' );
			echo '<p>' . $desc . '</p>';
		} // endif
	} // function

	public static function render_default_event_start_time_input() {
		$name = Settings::EVENT_START_TIME_OPTION_KEY;
		$value = Settings::get_default_event_start_time();
		echo "<input type='time' name='$name' value='$value' required=\"required\" autocomplete=\"off\">";
		$desc = __( 'Enter the default start time for events', 'reg-man-rc' );
		echo "<p class=\"description\">$desc</p>";
	} // function

	public static function render_default_event_duration_input() {
		$name = Settings::EVENT_DURATION_OPTION_KEY;
		$curr_val = Settings::get_default_event_duration_date_interval_string();
		$options = array(
				'PT30M'		=> __( '30 minutes', 'reg-man-rc' ),
				'PT1H'		=> __( '1 hour', 'reg-man-rc' ),
				'PT1H30M'	=> __( '1 hour, 30 minutes', 'reg-man-rc' ),
				'PT2H'		=> __( '2 hours', 'reg-man-rc' ),
				'PT2H30M'	=> __( '2 hours, 30 minutes', 'reg-man-rc' ),
				'PT3H'		=> __( '3 hours', 'reg-man-rc' ),
				'PT3H30M'	=> __( '3 hours, 30 minutes', 'reg-man-rc' ),
				'PT4H'		=> __( '4 hours', 'reg-man-rc' ),
				'PT4H30M'	=> __( '4 hours, 30 minutes', 'reg-man-rc' ),
				'PT5H'		=> __( '5 hours', 'reg-man-rc' ),
				'PT5H30M'	=> __( '5 hours, 30 minutes', 'reg-man-rc' ),
				'PT6H'		=> __( '6 hours', 'reg-man-rc' ),
				'PT6H30M'	=> __( '6 hours, 30 minutes', 'reg-man-rc' ),
				'PT7H'		=> __( '7 hours', 'reg-man-rc' ),
				'PT7H30M'	=> __( '7 hours, 30 minutes', 'reg-man-rc' ),
				'PT8H'		=> __( '8 hours', 'reg-man-rc' ),
		);
		$option_format = '<option value="%1$s" %3$s>%2$s</option>';
		echo "<select name=\"$name\" autocomplete=\"off\">";
			foreach( $options as $value => $label ) {
				$selected = selected( $curr_val, $value );
				printf( $option_format, $value, $label, $selected );
			} // endfor
		echo '</select>';
		$desc = __( 'Select the default duration for events', 'reg-man-rc' );
		echo "<p class=\"description\">$desc</p>";

	} // function

	public static function render_allow_multi_event_cats_input() {
		$input_name = Settings::ALLOW_MULTI_EVENT_CATS_OPTION_KEY;
		$value = Settings::get_is_allow_event_multiple_categories();
		$true_label = __( 'Yes, allow each event to have multiple categories', 'reg-man-rc' );
		$false_label = __( 'No, each event may have only one category (recommended)', 'reg-man-rc' );
		self::render_true_false_radio_buttons( $input_name, $value, $true_label, $false_label );
	} // function

	public static function render_google_maps_api_key_input() {
		$name = Settings::GOOGLE_MAPS_API_KEY_OPTION_KEY;
		$value = Settings::get_maps_api_key();
		echo "<input name='$name' value='$value' size=\"50\" autocomplete=\"off\">";
		if ( ! Map_View::get_is_map_view_enabled() ) {
			$desc = _x( 'Create a Google Maps API key',
						'Text for a link to a guide for creating a Google maps API key', 'reg-man-rc' );
			$url = 'https://developers.google.com/maps/documentation/embed/get-api-key';
			$link = "<a href=\"$url\" target=\"_blank\">$desc</a>";
			echo "<p class=\"description\">$link</p>";
		} // endif
	} // function

	public static function render_google_maps_default_centre_input() {
		$place = Settings::get_map_default_centre_place_name();
		$default_geo = Settings::get_map_default_centre_geo();
		$default_zoom = Settings::get_map_default_zoom();

		echo '<div class="reg-man-rc-settings-google-map-container google-places-autocomplete-place-change-listener google-map-zoom-change-listener">';
			// Place name input
			$name = Settings::GOOGLE_MAPS_DEFAULT_CENTRE_PLACE_OPTION_KEY;
			echo "<input name=\"$name\" value=\"$place\" size=\"50\" autocomplete=\"off\">";
			$desc = __( 'This is used to help find event venues in your area',
						'reg-man-rc' );
			echo "<p class=\"description\">$desc</p>";

			// Geo input (hidden)
			$name = Settings::GOOGLE_MAPS_DEFAULT_CENTRE_GEO_OPTION_KEY;
			$val = isset( $default_geo ) ? $default_geo->get_as_google_map_marker_position_string() : '';
			$val = esc_attr( $val );
			echo "<input name=\"$name\" type=\"hidden\" value=\"$val\" autocomplete=\"off\">";

			// Zoom input (hidden)
			$name = Settings::GOOGLE_MAPS_DEFAULT_ZOOM_OPTION_KEY;
			$val = ! empty( $default_zoom ) ? $default_zoom : '1';
			$val = esc_attr( $val );
			echo "<input name=\"$name\" type=\"hidden\" value=\"$val\" autocomplete=\"off\">";

			// Show a map
			echo '<div class="reg-man-rc-admin-settings-map-container">';
			// FIXME - this map is inside a form and it has a form!
			// We need to move the form to the footer, $map_view->set_is_render_form_in_footer()
			// Or maybe Map_View just does this all the time, or at least all the time on admin side
				$map_view = Map_View::create_for_object_page();
				$map_view->render();
			echo '</div>';

		echo '</div>';
	} // function

	/**
	 * Get the URL for the admin UI for this taxonomy
	 * @return string
	 */
	public static function get_google_maps_settings_admin_url() {
		$page = self::MENU_SLUG;
		$base_url = admin_url( 'options-general.php?' );
		$result = add_query_arg( array( 'page' => $page ), $base_url );
		return $result;
	} // function
	
	public static function render_require_volunteer_area_registered_user_input() {
		$input_name = Settings::REQUIRE_VOLUNTEER_AREA_REGISTERED_USER_OPTION_KEY;
		$value = Settings::get_is_require_volunteer_area_registered_user();
		$true_label = __( 'Yes, volunteers must provide a WP User password to access the volunteer area', 'reg-man-rc' );
		$false_label = __( 'No, volunteers can access the volunteer area using a valid Fixer or Volunteer email address and NO password', 'reg-man-rc' );
		self::render_true_false_radio_buttons( $input_name, $value, $true_label, $false_label );
		$note = __(
			'To require password login you must create a WP User with matching email address for each Fixer & Volunteer.',
			'reg-man-rc' );
		echo "<p><i>$note</i></p>";
	} // function
	

	public static function render_allow_volunteer_area_comments_input() {
		$input_name = self::ALLOW_VOLUNTEER_AREA_COMMENTS_OPTION_NAME;
		$value = Settings::get_is_allow_volunteer_area_comments();
		$true_label = __( 'Yes, allow volunteers to post comments on event pages in the volunteer area', 'reg-man-rc' );
		$false_label = __( 'No, do not allow volunteers to post comments in the volunteer area', 'reg-man-rc' );
		self::render_true_false_radio_buttons( $input_name, $value, $true_label, $false_label );
		$note = __(
			'When allowing comments, please also check your Discussion settings for restrictions on logged-in users etc.' .
			'  Those settings will apply to comments in the volunteer area.', 
			'reg-man-rc' );
		echo "<p><i>$note</i></p>";
	} // function
	
	private static function render_true_false_radio_buttons( $input_name, $value, $true_label, $false_label ) {
		$true_checked = ( $value ) ? 'checked="checked"' : '';
		$false_checked = ( ! $value ) ? 'checked="checked"' : '';
		$on_id = "{$input_name}_on";
		$off_id = "{$input_name}_off";

		echo '<div class="true-false-radio-group-container">';

			echo '<div class="true-false-radio-container">';
				echo "<input id=\"$on_id\" type='radio' name=\"$input_name\" value=\"1\" $true_checked/>";
				echo "<label for=\"$on_id\">$true_label</label>";
			echo '</div>';
			
			echo '<div class="true-false-radio-container">';
				echo "<input class=\"true-false-radio\" id=\"$off_id\" type='radio' name='$input_name' value='0' $false_checked/>";
				echo "<label for=\"$off_id\">$false_label</label>";
			echo '</div>';
			
		echo '</div>';
	} // function


	public static function filter_action_links( $links ) {
		// Add "Settings" link so that the settings page is easily accessible from plugins page
		$label = __( 'Settings', 'reg-man-rc' );
		$url = admin_url( 'options-general.php?page=' . self::MENU_SLUG );
		$my_links = array(
			"<a href=\"$url\">$label</a>",
		);
		$result = array_merge( $my_links, $links );
		return $result;
	} // function

} // class