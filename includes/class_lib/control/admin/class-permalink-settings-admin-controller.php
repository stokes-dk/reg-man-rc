<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Settings;

/**
 * The permalink settings admin controller (controller for the Permalink Settings page)
 *
 * This class provides the controller function adding my permalink fields to the permalink settings page
 *
 * @since v0.1.0
 *
 */
class Permalink_Settings_Admin_Controller {

	private static $EVENTS_FIELD_NAME			= 'reg_man_rc_events_slug';
	private static $CALENDARS_FIELD_NAME		= 'reg_man_rc_calendars_slug';
//	private static $ITEMS_FIELD_NAME			= 'reg_man_rc_items_slug';
//	private static $VENUES_FIELD_NAME			= 'reg_man_rc_venues_slug';

	public static function register() {

		// Add an action hook to output our permalink settings fields
		add_action( 'admin_init', array( __CLASS__, 'add_settings_fields' ) );

		// Add an action hook to save our permalink settings
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

	} // function

	/**
	 * Add my settings fields for permalinks
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function add_settings_fields( ) {

		$page = 'permalink';
		$section_id = 'reg-man-rc';

		// Registration Manager permalink settings section
		$label = __( 'Registration Manager for Repair Café', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_reg_man_section_header' );
		add_settings_section( $section_id, $label, $render_fn, $page );

		// Events slug
		$name = self::$EVENTS_FIELD_NAME;
		$label = __( 'Events page base', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_events_slug_field' );
		add_settings_field( $name, $label, $render_fn, $page, $section_id );

/*
		// Items slug
		$name = self::$ITEMS_FIELD_NAME;
		$label = __( 'Items page base', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_items_slug_field' );
		add_settings_field( $name, $label, $render_fn, $page, $section_id );

		// Venues slug
		$name = self::$VENUES_FIELD_NAME;
		$label = __( 'Venues page base', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_venues_slug_field' );
		add_settings_field( $name, $label, $render_fn, $page, $section_id );

		// Calendars slug
		$name = self::$CALENDARS_FIELD_NAME;
		$label = __( 'Calendars page base', 'reg-man-rc' );
		$render_fn = array( __CLASS__, 'render_calendars_slug_field' );
		add_settings_field( $name, $label, $render_fn, $page, $section_id );
*/

	} // function

	/**
	 * Render the header for my section of the permalink settings
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function render_reg_man_section_header( ) {
		$desc = __( 'The following settings apply to the Registration Manager for Repair Café plugin.', 'reg-man-rc' );
		echo '<p class="description">' . $desc . '</p>';
	} // function


	/**
	 * Render my settings field for the events slug permalink
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function render_events_slug_field( ) {
		$name = self::$EVENTS_FIELD_NAME;
		$val = Settings::get_events_slug();
		echo "<input name=\"$name\" type=\"text\" class=\"regular-text code\" value=\"$val\" />";
		$desc = __( 'Enter the base slug for pages showing events', 'reg-man-rc' );
		echo '<p class="description">' . $desc . '</p>';
	} // function

	/**
	 * Render my settings field for the items slug permalink
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
/*
	public static function render_items_slug_field( ) {
		$name = self::$ITEMS_FIELD_NAME;
		$val = Settings::get_items_slug();
		echo "<input name=\"$name\" type=\"text\" class=\"regular-text code\" value=\"$val\" />";
		$desc = __( 'Enter the base slug for pages showing items', 'reg-man-rc' );
		echo '<p class="description">' . $desc . '</p>';
	} // function
*/

	/**
	 * Render my settings field for the venues slug permalink
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
/*
	public static function render_venues_slug_field( ) {
		$name = self::$VENUES_FIELD_NAME;
		$val = Settings::get_venues_slug();
		echo "<input name=\"$name\" type=\"text\" class=\"regular-text code\" value=\"$val\" />";
		$desc = __( 'Enter the base slug for pages showing venues', 'reg-man-rc' );
		echo '<p class="description">' . $desc . '</p>';
	} // function
*/
	
	/**
	 * Render my settings field for the venues slug permalink
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
/*
	public static function render_calendars_slug_field( ) {
		$name = self::$CALENDARS_FIELD_NAME;
		$val = Settings::get_calendars_slug();
		echo "<input name=\"$name\" type=\"text\" class=\"regular-text code\" value=\"$val\" />";
		$desc = __( 'Enter the base slug for pages showing calendars', 'reg-man-rc' );
		echo '<p class="description">' . $desc . '</p>';
	} // function
*/
	/**
	 * Save my settings for permalinks
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function save_settings( ) {
		if ( isset( $_POST[ 'permalink_structure' ] ) ) {

			// Events slug
			$name = self::$EVENTS_FIELD_NAME;
			$val = isset( $_POST[ $name ] ) ? trim( $_POST[ $name ] ) : NULL;
			Settings::set_events_slug( $val );

/*
			// Items slug
			$name = self::$ITEMS_FIELD_NAME;
			$val = isset( $_POST[ $name ] ) ? trim( $_POST[ $name ] ) : NULL;
			Settings::set_items_slug( $val );

			// Venues slug
			$name = self::$VENUES_FIELD_NAME;
			$val = isset( $_POST[ $name ] ) ? trim( $_POST[ $name ] ) : NULL;
			Settings::set_venues_slug( $val );

			// Calendars slug
			$name = self::$CALENDARS_FIELD_NAME;
			$val = isset( $_POST[ $name ] ) ? trim( $_POST[ $name ] ) : NULL;
			Settings::set_calendars_slug( $val );
*/
		} // endif
	} // function
} // class