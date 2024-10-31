<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Event_Class;
use Reg_Man_RC\Model\Calendar_View_Format;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Calendar_Duration;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\View\Calendar_View;

/**
 * An instance of this class provides a user interfrace for a calendar.
 *
 * @since	v0.1.0
 *
 */
class Calendar_Admin_View {

	/**
	 * Perform the necessary steps to register this view with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function register() {

		// add my scripts and styles correctly for back end
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Add the metaboxes for things that are not taxonomies and therefore don't already have metaboxes like the event date
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_calendar_meta_boxes' ), 10, 2 );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'handle_post_updated_messages') );

		// Filter columns shown in the post list
		add_filter( 'manage_' . Calendar::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Render the column data shown in the post list
		add_action( 'manage_' . Calendar::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

	} // function

	/**
	 * Add event meta boxes
	 */
	public static function add_calendar_meta_boxes( $post_type, $post ) {
		if ( $post_type == Calendar::POST_TYPE ) {

			// Slug metabox
			// Because this custom post type is not queryable (does not have its own page) the slug is not shown
			//  automatically in the editor
			// But the slug can be used to reference the calendar in shortcodes and the user may wish to change it
			// So we will add our own slug metabox
			add_meta_box(
					'reg-man-rc-calendar-slug-metabox',
					__( 'Calendar Name', 'reg-man-rc' ),
					array( __CLASS__, 'render_slug_metabox'),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);
			
			// Status metabox
			add_meta_box(
					'reg-man-rc-event-status-metabox',
					__( 'Event Statuses', 'reg-man-rc' ),
					array( __CLASS__, 'render_event_status_metabox'),
					Calendar::POST_TYPE,
					'normal', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);
			
			// Category metabox
			$view = Event_Category_Admin_View::create();
			add_meta_box(
					'reg-man-rc-event-category-metabox',
					__( 'Event Categories', 'reg-man-rc' ),
					array( $view, 'render_post_metabox' ),
					Calendar::POST_TYPE,
					'normal', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);

			// iCalendar feed metabox
			add_meta_box(
					'reg-man-rc-ical-feed-metabox',
					__( 'Sharing', 'reg-man-rc' ),
					array( __CLASS__, 'render_ical_feed_metabox'),
					Calendar::POST_TYPE,
					'normal', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);
			
			// Show Past Events
			add_meta_box(
					'reg-man-rc-past-events-metabox',
					__( 'Show Past Events', 'reg-man-rc' ),
					array( __CLASS__, 'render_past_events_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);

			// Calendar views metabox
			add_meta_box(
					'reg-man-rc-view-format-metabox',
					__( 'Views', 'reg-man-rc' ),
					array( __CLASS__, 'render_view_format_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);

			// Calendar durations metabox
			add_meta_box(
					'reg-man-rc-durations-metabox',
					__( 'Months per page', 'reg-man-rc' ),
					array( __CLASS__, 'render_durations_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
					);
		} // endif
		
	} // function

	/**
	 * Render the iCalendar feed metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.7.0
	 */
	public static function render_slug_metabox( $post ) {
		
		echo '<div class="reg-man-rc-calendar-slug-metabox-container">';
			// We need a flag to distinguish the case where no slug is assigned by the user
			//  versus the case where no metabox was presented at all like in quick edit mode
			echo '<input type="hidden" name="slug_flag" value="TRUE">';
			
			$slug = $post->post_name;
			
			$input_list = Form_Input_List::create();
			$label = '';// __( 'Slug', 'reg-man-rc' );
			$name = 'post_name';
			$val = $slug;
			$shortcode = Calendar_View::CALENDAR_SHORTCODE;
			$shortcode = sprintf( '[%1$s calendar="%2$s"]', $shortcode, $slug ); // Code, not to be translated
			/* Translators: %1$s is a shortcode like "[rc-calendar calendar=upcoming-events]" */
			$hint_format = __( 'Use this name in shortcodes, e.g. %1$s', 'reg-man-rc' );
			$hint = sprintf( $hint_format, $shortcode );
			$classes = '';
			$is_required = FALSE; // The system will assign a default if it's not provided
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required );
			
			$input_list->render();

		echo '</div>';
			
	} // function

	/**
	 * Render the iCalendar feed metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.7.0
	 */
	public static function render_ical_feed_metabox( $post ) {
		
		echo '<div class="reg-man-rc-calendar-ical-feed-metabox-container">';
			// We need a flag to distinguish the case where no feed is assigned by the user
			//  versus the case where no metabox was presented at all like in quick edit mode
			echo '<input type="hidden" name="ical_feed_flag" value="TRUE">';
	
			$slug = $post->post_name;
			
			$calendar = Calendar::get_calendar_by_post_id( $post->ID );
			$feed_name = $calendar->get_icalendar_feed_name();
			$has_feed = ! empty( $feed_name );
	
			$input_list = Form_Input_List::create();
			
			$feed_input_list = Form_Input_List::create();
			$feed_input_list->add_list_classes( 'ical-feed-input-list' );
			
			$label = __( 'Allow people to subscribe to a feed of events on this calendar ', 'reg-man-rc' );
			$title = __( 'This will create a public iCalendar events feed', 'reg-man-rc' );
			$checked = $has_feed ? 'checked="checked"' : '';
			$checkbox_format =
					'<label title="%2$s">' .
						'<input type="checkbox" name="has-ical-feed" value="1" %3$s></input>' .
						'<span>%1$s</span>' .
					'</label>';
			$fieldset_label = sprintf( $checkbox_format, $label, $title, $checked );

			$label = __( 'Feed name', 'reg-man-rc' );
			$name = 'ical-feed-name';
			$val = $has_feed ? $feed_name : "$slug-ical";
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$addn_attrs = $has_feed ? '' : 'disabled="disabled"';
			$feed_input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

			$label = __( 'Include a "Subscribe" button when this calendar is shown on a public page', 'reg-man-rc' );
			$name = 'has-ical-feed-subscribe-button';
			$val = '1';
			$is_checked = $calendar->get_icalendar_is_show_subscribe_button();
			$hint = __( 'Note: To show a subscribe button in the volunteer area you must go to "Settings > Volunteer Area"', 'reg-man-rc' );
			$feed_input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint );
			
			$input_list->add_fieldset( $fieldset_label, $feed_input_list );
			
			$input_list->render();

		echo '</div>';
	} // function

	/**
	 * Render the event status metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_status_metabox( $post ) {
		$calendar = Calendar::get_calendar_by_post_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_event_status_array() : array();
		self::render_status_checkboxes( $selected_id_array );
		$msg = __( 'Select the statuses for events to be shown in this calendar', 'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_status_checkboxes( $selected_id_array ) {

		// We need a flag to distinguish the case where no event statuses were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="event_status_selection_flag" value="TRUE">';

		$event_statuses = Event_Status::get_all_event_statuses();
		$input_name = 'event_status';

		$format =
			'<div><label title="%1$s" class="reg-man-rc-metabox-radio-label">' .
				'<input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s>' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $event_statuses as $status_obj ) {
			$id = $status_obj->get_id();
			$name = $status_obj->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Render the metabox for past events for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_past_events_metabox( $post ) {

		// We need a flag to distinguish the case where nothing is chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="past_events_selection_flag" value="TRUE">';

		$calendar = Calendar::get_calendar_by_post_id( $post->ID );
		$is_show_past_events = isset( $calendar ) ? $calendar->get_is_show_past_events() : FALSE;

		$radio_format =
			'<div><label class="reg-man-rc-metabox-radio-label">' .
				'<input type="radio" name="is-show-past-events" value="%2$s" %3$s autocomplete="off">' .
				'<span>%1$s</span>' .
			'</label></div>';

		echo '<p>';
			// Yes
			$label = esc_html__( 'Yes, show past events', 'reg-man-rc' );
			$value = '1';
			$checked = $is_show_past_events ? 'checked="checked"' : '';
	//		Error_Log::var_dump( $is_show_past_events, $checked );
			printf( $radio_format, $label, $value, $checked );

			// No
			$label = esc_html__( 'No, do not show past events', 'reg-man-rc' );
			$value = '0';
			$checked = ! $is_show_past_events ? 'checked="checked"' : '';
	//		Error_Log::var_dump( $is_show_past_events, $checked );
			printf( $radio_format, $label, $value, $checked );
		echo '</p>';

	} // function

	/**
	 * Render the view format metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_view_format_metabox( $post ) {
		$calendar = Calendar::get_calendar_by_post_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_view_format_ids_array() : array();
		self::render_view_format_checkboxes( $selected_id_array );
		$msg = __(
				'Select the views used to display events in this calendar.',
				'reg-man-rc'
		);
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_view_format_checkboxes( $selected_id_array ) {

		// We need a flag to distinguish the case where no event statuses were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="view_format_selection_flag" value="TRUE">';

		$view_formats = Calendar_View_Format::get_all_calendar_view_formats();
		$input_name = 'view_format';

		$format =
			'<div><label title="%1$s" class="reg-man-rc-metabox-radio-label">' .
				'<input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s>' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $view_formats as $view_format_obj ) {
			$id = $view_format_obj->get_id();
			$name = $view_format_obj->get_name();
			$desc = $view_format_obj->get_description();
			$html_name = esc_html( $name );
			$attr_desc = esc_attr( $desc );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_desc, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Render the durations metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_durations_metabox( $post ) {
		$calendar = Calendar::get_calendar_by_post_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_duration_ids_array() : array();
		self::render_duration_checkboxes( $selected_id_array );
		$msg = __(
				'Select the options for viewing multiple months on the calendar page.',
				'reg-man-rc'
				);
		echo '<p>' . $msg . '</p>';
	} // function
	
	private static function render_duration_checkboxes( $selected_id_array ) {
		
		// We need a flag to distinguish the case where no durations were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="durations_selection_flag" value="TRUE">';
		
		$durations = Calendar_Duration::get_all_calendar_durations();
		$input_name = 'duration';
		
		$format =
			'<div><label title="%1$s" class="reg-man-rc-metabox-radio-label">' .
				'<input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s>' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $durations as $duration_obj ) {
			$id = $duration_obj->get_id();
			$name = $duration_obj->get_name();
			$desc = $duration_obj->get_description();
			$html_name = esc_html( $name );
			$attr_desc = esc_attr( $desc );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_desc, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Set up the messages for this post type
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_post_updated_messages( $messages ) {
		global $post, $post_ID;
//		$permalink = get_permalink( $post_ID );
		/* translators: %1$s is a date, %2$s is a time. */
		$date_time_format = sprintf( _x('%1$s at %2$s', 'Displaying a date and time', 'reg-man-rc' ),
										get_option( 'date_format' ), get_option('time_format') );
		$date = date_i18n( $date_time_format, strtotime( $post->post_date ) );
		$messages[ Calendar::POST_TYPE ] = array(
				0 => '',
				1 => __( 'Calendar updated.', 'reg-man-rc' ),
				2 => __( 'Custom field updated.', 'reg-man-rc' ),
				3 => __( 'Custom field deleted.', 'reg-man-rc' ),
				4 => __( 'Calendar updated.', 'reg-man-rc' ),
				5 => isset($_GET['revision']) ? sprintf( __( 'Calendar restored to revision from %s', 'reg-man-rc' ), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => __( 'Calendar published.', 'reg-man-rc' ),
				7 => __( 'Calendar saved.', 'reg-man-rc' ),
				8 => __( 'Calendar submitted.', 'reg-man-rc' ),
				9 => sprintf( __( 'Calendar scheduled for: <strong>%1$s</strong>', 'reg-man-rc' ) , $date ),
				10 => __( 'Calendar draft updated.', 'reg-man-rc' ),
		);
		return $messages;
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface on the backend when we're on the right page
	 *
	 * This method is called automatically when scripts are enqueued.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) &&
				( $screen->post_type == Calendar::POST_TYPE ) &&
				( empty( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
	} // function

	/**
	 * Filter the columns shown in the admin UI
	 * @param string[] $columns
	 * @return string[]
	 */
	public static function filter_admin_UI_columns( $columns ) {

		$event_category_tax_col = 'taxonomy-' . Event_Category::TAXONOMY_NAME;

		$result = array(
				'cb'						=> $columns[ 'cb' ],
				'title'						=> __( 'Calendar Title',			'reg-man-rc' ),
				$event_category_tax_col		=> __( 'Event Categories',			'reg-man-rc' ),
				'event_statuses'			=> __( 'Event Statuses',			'reg-man-rc' ),
				'shortcode'					=> __( 'Shortcode',					'reg-man-rc' ),
				'ical_feed'					=> __( 'iCalendar Feed',			'reg-man-rc' ),
				'view_formats'				=> __( 'Views',						'reg-man-rc' ),
				'durations'					=> __( 'Months per Page',			'reg-man-rc' ),
				'date'						=> __( 'Last Update',				'reg-man-rc' ),
				'author'					=> __( 'Author',					'reg-man-rc' ),
		);
		return $result;

	} // function

	/**
	 * Render the value shown in the specified column of the admin UI
	 * @param string $column_name
	 * @param string|int $post_id
	 */
	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$calendar = Calendar::get_calendar_by_post_id( $post_id );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$result = $em_dash; // show em-dash by default
		if ( $calendar !== NULL ) {
			switch ( $column_name ) {

				case 'event_statuses':
					$statuses = $calendar->get_event_status_array();
					$name_array = array();
					foreach( $statuses as $status_id ) {
						$status = Event_Status::get_event_status_by_id( $status_id );
						$name_array[] = isset( $status ) ? $status->get_name() : $status_id; // Defensive
					} // endform
					$result = ! empty( $name_array ) ? esc_html( implode( ', ', $name_array ) ) : $em_dash;
					break;

				case 'ical_feed':
					$feed_name = $calendar->get_icalendar_feed_name();
					if ( empty( $feed_name ) ) {
						$result = $em_dash;
					} else {
						$blog_id = NULL; // I believe this is used for multi-site
						$path = 'feed/' . $feed_name . '/?preview';
						$url = get_site_url( $blog_id, $path );
						$format = '<a target="_blank" href="%1$s">%2$s</a>';
						$result = sprintf( $format, $url, esc_html( $feed_name ) );
					} // endif
					break;

				case 'shortcode':
					$post = \WP_Post::get_instance( $post_id );
					$slug = $post->post_name;
					$shortcode = Calendar_View::CALENDAR_SHORTCODE;
					$result = sprintf( '[%1$s calendar="%2$s"]', $shortcode, $slug ); // Code, not to be translated
					break;
					
				case 'view_formats':
					$format_id_array = $calendar->get_view_format_ids_array();
					$name_array = array();
					foreach( $format_id_array as $format_id ) {
						$format = Calendar_View_Format::get_view_format_by_id( $format_id );
						$name_array[] = isset( $format ) ? $format->get_name() : $format_id; // Defensive
					} // endform
					$result = ! empty( $name_array ) ? esc_html( implode( ', ', $name_array ) ) : $em_dash;
					break;

				case 'durations':
					$duration_id_array = $calendar->get_duration_ids_array();
					$name_array = array();
					foreach( $duration_id_array as $duration_id ) {
						$duration = Calendar_Duration::get_duration_by_id( $duration_id );
						$name_array[] = isset( $duration ) ? $duration->get_name() : $duration_id; // Defensive
					} // endform
					$result = ! empty( $name_array ) ? esc_html( implode( ', ', $name_array ) ) : $em_dash;
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
			$heading = __( 'About Calendars', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A calendar represents a collection of events defined by event categories and statuses.' .
					'  The resulting calendar of events can be shown on a page on the public website using a shortcode.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'The system uses a visitor registration calendar (assigned in the plugin settings)' .
					' to determine which events to use in the visitor registration page when allowing visitors to register their items for an event.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'The system also uses a volunteer registration calendar (assigned in the plugin settings)' .
					' to determine which events to use in the volunteer area when allowing volunteers to register to attend events.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'The system provides an administrative calendar inside the WordPress admin area that shows' .
					' all the events defined in the system including all categories and all statuses.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Note that private events (draft or privately published event descriptions) are never shown on' .
					' any public calendar including the visitor registration and volunteer area calendars.' .
					'  Note also that the administrative calendar (shown only inside the WordPress admin area) always shows private events.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'A calendar includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Calendar Title', 'reg-man-rc' );
					$msg = esc_html__(
							'Used only to identify the calendar by name.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Event Categories', 'reg-man-rc' );
					$msg = esc_html__(
							'A list of event categories to be shown on this calendar, e.g. "Repair Café, Mini Event".' .
							'  Events in any of the listed categories will be shown;' .
							' events not in any of the listed categories will not be shown.' .
							'  For example, the visitor registration calendar may include "Repair Café" events and exclude "Volunteer Appreciation" events.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Event Statuses', 'reg-man-rc' );
					$msg = esc_html__(
							'A list of event statuses to be shown on this calendar, e.g. "Confirmed, Cancelled".' .
							'  Events whose status is among those listed will be shown;' .
							' other events will not be shown.' .
							'  For example, a public upcoming events calendar may include "Cancelled" events and exclude "Tentative" events.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Shortcode', 'reg-man-rc' );
					$msg = esc_html__(
							'The shortcode to use in a website page to show this calendar.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'iCalendar Feed', 'reg-man-rc' );
					$msg = esc_html__(
							'The calendar can provide an iCalendar feed so that its events can be shared with other systems.' .
							'  If a feed name is specified then other systems can download an ".ics" file containing the calendar\'s events.' .
							' This allows visitors or volunteers to subscribe and add our events to their personal calendar.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Views', 'reg-man-rc' );
					$msg = esc_html__(
							'The view options available to the end user when the calendar is displayed on a website page.' .
							'  View options include: Grid - a typical calendar view; List - a simple list of events; and Map - the events shown on a map by location.' .
							'  Note that the "Map" option is only available when a Google Maps API key has been saved in the plugin settings',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Months per Page', 'reg-man-rc' );
					$msg = esc_html__(
							'A list of options available to the end user when the calendar is displayed on a website page.' .
							'  The Months per Page options allow the user to select how many months are displayed on the page at one time.' .
							'  For example, the options may include: 1 month, 6 months, and A calendar year from January to December.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
				echo '</dl>';
			echo '</p>';

		$result = ob_get_clean();
		return $result;
	} // function
	
	
} // class