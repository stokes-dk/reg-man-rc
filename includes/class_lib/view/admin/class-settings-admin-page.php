<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Map_View;

/**
 * The settings admin page for the plugin
 *
 */

class Settings_Admin_Page {

	const MENU_SLUG					= 'reg-man-rc-settings';
	const OPTION_GROUP_ID			= 'reg-man-rc-option-group';

	const GENERAL_SECTION_ID		= 'reg-man-rc-general-settings';
	const EVENT_SECTION_ID			= 'reg-man-rc-event-settings';
	const GOOGLE_MAPS_SECTION_ID	= 'reg-man-rc-google-maps-settings';

	private function __construct() {
	} // construct

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public function render() {
		$heading = esc_html( __( 'Registration Manager for Repair Café Settings', 'reg-man-rc' ) );
		echo "<h1>$heading</h1>";
		echo '<form method="post" action="options.php">';
			settings_fields( self::OPTION_GROUP_ID );
			do_settings_sections( self::MENU_SLUG ); // do the settings for this page
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

			// Use admin_init hook to set up my settings page sections and fields
			add_action( 'admin_init', array( __CLASS__, 'handle_admin_init' ) );

			// My settings menu page is created during the admin_menu hook
			add_action( 'admin_menu', array( __CLASS__, 'handle_admin_menu' ) );

			// Regsiter to enqueue the necessary scripts and styles as needed
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

			// Add a 'settings' link to the actions for my plugin
			$file_name = plugin_basename( \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
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
		$page_title = __( 'Repair Café Settings', 'reg-man-rc' );
		$menu_title = __( 'Repair Café Settings', 'reg-man-rc' );
		$capability = 'manage_options';
		$slug = self::MENU_SLUG;
		$function = array( __CLASS__, 'render_settings_page' );
		add_options_page( $page_title, $menu_title, $capability, $slug, $function );

	} // function

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

		self::create_general_section();

		self::create_event_section();

		self::create_google_maps_section();

	} // function

	/**
	 * Create the general settings section
	 */
	private static function create_general_section() {

		// Add the sections to our settings page so we can add our fields to it
		$section_id = self::GENERAL_SECTION_ID;
		$title = __( 'General settings', 'reg-man-rc' );
		$desc_fn = NULL; // used to echo description content between heading and fields
		$page_slug = self::MENU_SLUG;
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		$option_group = self::OPTION_GROUP_ID;

		$option_name = Settings::REG_FORM_LOGO_OPTION_KEY;
		$title = __( 'Logo image', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_form_logo_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

	} // function

	/**
	 * Create the event settings section
	 */
	private static function create_event_section() {

		// Add the sections to our settings page so we can add our fields to it
		$section_id = self::EVENT_SECTION_ID;
		$title = __( 'Event settings', 'reg-man-rc' );
		$desc_fn = NULL; // used to echo description content between heading and fields
		$page_slug = self::MENU_SLUG;
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		$option_group = self::OPTION_GROUP_ID;

		// Event start time
		$option_name = Settings::EVENT_START_TIME_OPTION_KEY;
		$title = __( 'Start time', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_default_event_start_time_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Event end time
		$option_name = Settings::EVENT_END_TIME_OPTION_KEY;
		$title = __( 'End time', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_default_event_end_time_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Allow multiple categories for events
		$option_name = Settings::ALLOW_MULTI_EVENT_CATS_OPTION_KEY;
		$title = __( 'Allow multiple event categories', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_allow_multi_event_cats_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

		// Display sidebar for events
		$option_name = Settings::DISPLAY_EVENT_SIDEBAR_OPTION_KEY;
		$title = __( 'Display the sidebar on event pages', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_display_event_sidebar_input' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		register_setting( $option_group, $option_name );

	} // function

	/**
	 * Create the Google mpas settings section
	 */
	private static function create_google_maps_section() {

		// Add the section to our settings page so we can add our fields to it
		$section_id = self::GOOGLE_MAPS_SECTION_ID;
		$title = __( 'Google maps settings', 'reg-man-rc' );
		$desc_fn = array( __CLASS__, 'render_google_maps_section_description' ); // used to echo description content between heading and fields
		$page_slug = self::MENU_SLUG;
		add_settings_section( $section_id, $title, $desc_fn, $page_slug );

		$option_group = self::OPTION_GROUP_ID;

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


	public static function render_google_maps_section_description() {
		if ( ! Map_View::get_is_map_view_enabled() ) {
			$desc = __( 'To use Google maps with this plugin you will need to have a valid API key.', 'reg-man-rc' );
			echo '<p>' . $desc . '</p>';
		} // endif
	} // function

	public static function render_form_logo_input() {
		$name = Settings::REG_FORM_LOGO_OPTION_KEY;
		$attachment_id = Settings::get_reg_form_logo_image_attachment_id();

		echo '<div class="media-library-select-container reg-form-logo">';

			if ( ! empty( $attachment_id ) ) {
				$size = 'medium';
				$attr = array(
						'class' => 'reg-man-rc-logo-image',
				);
				$img_tag = wp_get_attachment_image( $attachment_id, $size, $icon = FALSE, $attr );
			} else {
				$img_tag = '';
			} // endif
			echo "<div class=\"media-library-img-container\">$img_tag</div>";

			$value = ! empty( $attachment_id ) ? esc_attr( $attachment_id ) : 0;

			$class = 'media-library-attachment-id'; // needed in js to find the input
			echo "<input type=\"hidden\" class=\"$class\" name=\"$name\" value=\"$value\">";

			$button_format = '<button type="button" name="%2$s" id="%3$s" class="%4$s">%1$s</button>';

			$button_label = __( 'Select…', 'reg-man-rc' );
			$input_id = 'reg-form-logo-select';
			$input_name = 'reg-form-logo-select';
			$classes = 'media-library-launch media-library-select button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

			$button_label = __( 'Change…', 'reg-man-rc' );
			$input_id = 'reg-form-logo-change';
			$input_name = 'reg-form-logo-change';
			$classes = 'media-library-launch media-library-change button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

			$button_label = __( 'Remove', 'reg-man-rc' );
			$input_id = 'reg-form-logo-remove';
			$input_name = 'reg-form-logo-remove';
			$classes = 'media-library-remove button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

			$desc = __( 'Select the logo image to be displayed in registration forms', 'reg-man-rc' );
			echo "<p class=\"description\">$desc</p>";
		echo '</div>';
	} // function

	public static function render_default_event_start_time_input() {
		$name = Settings::EVENT_START_TIME_OPTION_KEY;
		$value = Settings::get_default_event_start_time();
		echo "<input type='time' name='$name' value='$value' required=\"required\" autocomplete=\"off\">";
		$desc = __( 'Enter the default start time for events', 'reg-man-rc' );
		echo "<p class=\"description\">$desc</p>";
	} // function

	public static function render_default_event_end_time_input() {
		$name = Settings::EVENT_END_TIME_OPTION_KEY;
		$value = Settings::get_default_event_end_time();
		echo "<input type='time' name='$name' value='$value' required=\"required\" autocomplete=\"off\">";
		$desc = __( 'Enter the default end time for events', 'reg-man-rc' );
		echo "<p class=\"description\">$desc</p>";
	} // function

	public static function render_allow_multi_event_cats_input() {
		$input_name = Settings::ALLOW_MULTI_EVENT_CATS_OPTION_KEY;
		$value = Settings::get_is_allow_event_multiple_categories();
		$true_label = __( 'Yes, allow each event to have multiple categories', 'reg-man-rc' );
		$false_label = __( 'No, each event may have only one category (recommended)', 'reg-man-rc' );
		self::render_true_false_radio_buttons( $input_name, $value, $true_label, $false_label );
	} // function

	public static function render_display_event_sidebar_input() {
		$input_name = Settings::DISPLAY_EVENT_SIDEBAR_OPTION_KEY;
		$value = Settings::get_is_display_event_sidebar();
		$true_label = __( 'Yes, show the sidebar on event pages', 'reg-man-rc' );
		$false_label = __( 'No, do not show the sidebar on event pages', 'reg-man-rc' );
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