<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Internal_Event_Descriptor;

/**
 * The administrative view for venues
 *
 * @since	v0.1.0
 *
 */
class Venue_Admin_View {

	const INPUT_NAME = 'event_venue';

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Add the metabox for the location
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_location_metabox' ) );

		// Change the placeholder text for "Enter Title Here"
		add_filter( 'enter_title_here', array( __CLASS__, 'rewrite_enter_title_here' ) );

		// Filter columns shown in the post list
		add_filter( 'manage_' . Venue::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Render the column data shown in the post list
		add_action( 'manage_' . Venue::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

	} // function
/* FIXME - Do I need this?
	public static function add_form_top( $post ) {
		$post_type = get_post_type( $post );
		if ( Venue::POST_TYPE === $post_type ) {
//			$input_list = Form_Input_List::create();
//			$input_list->add_text_input( 'Search Google Places', 'google-places' );
//			$input_list->render();
//			echo '<input type="search" placeholder="Search for Venue in Google Places">';
			echo '<label><input type="checkbox"/>';
				echo __( 'Use autocomplete for venue name', 'reg-man-rc' );
			echo '</label>';
		} // endif
	} // function
*/
	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		$screen = get_current_screen();
		$post_types = array( // The following post types require Google maps
				Venue::POST_TYPE,
				Internal_Event_Descriptor::POST_TYPE,
		);
		if ( is_object( $screen ) && in_array( $screen->post_type, $post_types ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			Scripts_And_Styles::enqueue_google_maps();
		} // endif
	} // function

	/**
	 * Add location metabox
	 */
	public static function add_location_metabox( ) {
		add_meta_box(
			'reg-man-rc-venue-location-metabox',
			__( 'Location Details', 'reg-man-rc' ),
			array( __CLASS__, 'render_venue_location_metabox' ),
			Venue::POST_TYPE,
			'normal',	// usually normal, side or advanced
			'high'		// priority within the section (high, low or default)
		);
	} // function

	/**
	 * Render the venue location metabox
	 * @param \WP_Post $post
	 */
	public static function render_venue_location_metabox( $post ) {
		$venue = Venue::get_venue_by_id( $post->ID );
		self::render_venue_location_input( $venue );

		if ( ! Map_View::get_is_map_view_enabled() ) {
			$link_text = _x( 'Registration Manager for Repair Café Settings',
					'Text for a link to the settings page for this plugin', 'reg-man-rc' );
			$link_url = Settings_Admin_Page::get_google_maps_settings_admin_url();
			$link = "<a href=\"$link_url\">$link_text</a>";
			/* Translators: %s is a link to a settings page */
			$msg_format = __( 'To setup Google maps for use in this plugin go to: %s', 'reg-man-rc' );
			printf( $msg_format, $link );
		} // endif
	} // function

	/**
	 * Render the venue location input with map.
	 *
	 * This is used to render the same input when editing a venue and when editing an event and adding a new venue.
	 * @param	Venue	$venue	The venue whose location should be shown in the input, or NULL if no venue exists
	 */
	public static function render_venue_location_input( $venue, $include_name_input = FALSE ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="venue_location_input_flag" value="TRUE">';

		$classes = array(
			'reg-man-rc-venue-location-container',
			'google-places-autocomplete-place-change-listener',
			'google-map-zoom-change-listener',
		);
		$classes = implode( ' ', $classes );
		echo "<div class=\"$classes\">";
			$input_list = Form_Input_List::create();
			if ( $include_name_input ) {
				$label = __( 'Venue Name', 'reg-man-rc' );
				$val = isset( $venue ) ? $venue->get_name() : '';
				$classes = 'venue-name full-width';
				$is_required = FALSE;
				$addn_attrs = 'autocomplete="off"';
				$input_list->add_text_input( $label, 'venue_name', $val, $hint = '', $classes, $is_required, $addn_attrs );
			} // endif
			$label = __( 'Location', 'reg-man-rc' );
			$val = isset( $venue ) ? $venue->get_location() : '';
			$classes = 'venue-location full-width';
			$is_required = FALSE;
			$addn_attrs = 'autocomplete="off"';
			$input_list->add_text_input( $label, 'venue_location', $val, $hint = '', $classes, $is_required, $addn_attrs );
			$input_list->render();

			if ( Map_View::get_is_map_view_enabled() ) {
				echo '<div class="reg-man-rc-admin-metabox-google-map-container reg-man-rc-metabox">';
					$map_view = Map_View::create_for_object_page();
					$map_view->set_map_markers( array( $venue ) );
					$map_view->render();
					$geo = isset( $venue ) ? $venue->get_geo() : NULL;
					$lat_lng = isset( $geo ) ? esc_attr( json_encode( $geo->get_as_google_map_marker_position() ) ) : '';
					$map_type = Map_View::MAP_TYPE_OBJECT_PAGE;
					$zoom = isset( $venue ) ? $venue->get_map_marker_zoom_level( $map_type ) : NULL;
					$zoom_attr = isset( $zoom ) ? esc_attr( $zoom ) : 16; // Why 16?  It looks good
					echo '<input type="hidden" name="venue_lat_lng" value="' . $lat_lng . '">';
					echo '<input type="hidden" name="venue_map_zoom" value="' . $zoom_attr . '">';
				echo '</div>';
			} // endif
		echo '</div>';
	} // function

	public static function rewrite_enter_title_here( $input ) {
		// Change the placeholder text for "Enter Title Here" if the specified post is mine
		if ( Venue::POST_TYPE === get_post_type() ) {
			$input = __( 'Venue name', 'reg-man-rc' );
		} // endif
		return $input;
	} // function

	public static function update_post_messages( $messages ) {
		global $post, $post_ID;
		$permalink = get_permalink( $post_ID );
		/* translators: %1$s is a date, %2$s is a time. */
		$date_time_format = sprintf( _x('%1$s at %2$s', 'Displaying a date and time', 'reg-man-rc' ),
										get_option( 'date_format' ), get_option('time_format') );
		$date = date_i18n( $date_time_format, strtotime( $post->post_date ) );
		$messages[ Venue::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Venue updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Venue updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Venue restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Venue published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Venue saved.'),
				8 => sprintf( __('Venue submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Venue scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Venue draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
		);
		return $messages;
	} // function

	/**
	 * Set up the columns to show in the main admin list for this type
	 * @param	string[]	$columns	The default associative array of IDs (key) and titles (value) for the columns
	 * @return	string[]				The same associative array updated with the specific columns and titles to use for this type
	 * @since	v0.1.0
	 */
	public static function filter_admin_UI_columns( $columns ) {
		$result = array(
			'cb'						=> $columns['cb'],
			'title'						=> __( 'Name', 'reg-man-rc' ),
			'location'					=> __( 'Location', 'reg-man-rc' ),
			'event_count'				=> __( 'Events', 'reg-man-rc' ),
			'date'						=> __( 'Last Update', 'reg-man-rc' ),
			'author'					=> __( 'Author', 'reg-man-rc' ),
		);
		return $result;
	} // function

	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$venue = Venue::get_venue_by_id( $post_id );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$result = $em_dash; // show em-dash by default
		if ( $venue !== NULL ) {

			switch ( $column_name ) {

				case 'location':
					$loc = $venue->get_location();
					$result = ! empty( $loc ) ? esc_html( $loc ) : $em_dash;
					break;

				case 'event_count':
					$venue_id = $venue->get_id();
					$count = Internal_Event_Descriptor::get_count_internal_event_descriptors_for_venue( $venue_id );
					$result = ! empty( $count ) ? esc_html( $count ) : $em_dash;
					break;

				default:
					$result = $em_dash;
					break;

			} // endswitch

		} // endif

		echo $result;
	} // function

	/**
	 * Get the set of tabs to be shown in the help for this type
	 * @return array
	 */
	public static function get_help_tabs() {
		$result = array(
			array(
				'id'		=> 'reg-man-rc-about',
				'title'		=> __( 'About', 'reg-man-rc' ),
				'content'	=> self::get_about_content(),
			),
		);
		return $result;
	} // function
	
	/**
	 * Get the html content shown to the administrator in the "About" help for this post type
	 * @return string
	 */
	private static function get_about_content() {
		ob_start();
			$heading = __( 'About Venues', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A venue contains the details of a physical location used to host events.' .
					'  It includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Name', 'reg-man-rc' );
					$msg = esc_html__(
							'The name or title of the venue, "Toronto Reference Library".',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Location', 'reg-man-rc' );
					$msg = esc_html__( 'The address or physical location of the venue, e.g. "789 Yonge St, Toronto, ON M4W 2G8".', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Content', 'reg-man-rc' );
					$msg = esc_html__(
							'The text content to be shown for the venue.' .
							'  This may include parking or public transit instructions and other information that may be useful to visitors and volunteers.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
										
				echo '</dl>';
			echo '</p>';

		$result = ob_get_clean();
		return $result;
	} // function

} // class
