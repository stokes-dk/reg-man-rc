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

			// Status metabox
			add_meta_box(
					'reg-man-rc-event-status-metabox',
					__( 'Event Statuses', 'reg-man-rc' ),
					array( __CLASS__, 'render_event_status_metabox'),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
					);
			
			// Category metabox
			$view = Event_Category_Admin_View::create();
			add_meta_box(
					'reg-man-rc-event-category-metabox',
					__( 'Event Categories', 'reg-man-rc' ),
					array( $view, 'render_post_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
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
					__( 'Timespans', 'reg-man-rc' ),
					array( __CLASS__, 'render_durations_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
					);
			
			// Class metabox
			add_meta_box(
					'reg-man-rc-event-class-metabox',
					__( 'Event Classes', 'reg-man-rc' ),
					array( __CLASS__, 'render_event_class_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);
			
			
/* FIXME - NOT USED, these are assigned in the settings
			// Registration metabox
			add_meta_box(
					'reg-man-rc-calendar-purpose-metabox',
					__( 'Registration', 'reg-man-rc' ),
					array( __CLASS__, 'render_registration_calendars_metabox' ),
					Calendar::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
					);
*/

		} // endif
	} // function

	/**
	 * Render the event status metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_status_metabox( $post ) {
		$calendar = Calendar::get_calendar_by_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_event_status_array() : array();
		self::render_status_checkboxes( $selected_id_array );
		$msg = __( 'Select the event statuses to be included in this calendar', 'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_status_checkboxes( $selected_id_array ) {

		// We need a flag to distinguish the case where no event statuses were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="event_status_selection_flag" value="TRUE">';

		$event_statuses = Event_Status::get_all_event_statuses();
		$input_name = 'event_status';

		$format =
			'<div><label title="%1$s">' .
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
	 * Render the event class metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_class_metabox( $post ) {
		$calendar = Calendar::get_calendar_by_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_event_class_array() : array();
		self::render_class_checkboxes( $selected_id_array );

		$msg = __(
				'Public events are always included in the calendar, confidential events are never included.' .
				'  Private events are only visible to logged-in users who have the authority to see private events.'
				, 'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_class_checkboxes( $selected_id_array ) {

		$event_classes = Event_Class::get_all_event_classes();
		$input_name = 'event_class';

		$format =
			'<div><label title="%1$s">' .
				'<input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s autocomplete="off" disabled="disabled">' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $event_classes as $class_obj ) {
			$id = $class_obj->get_id();
			$name = $class_obj->get_name();
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

		$calendar = Calendar::get_calendar_by_id( $post->ID );
		$is_show_past_events = isset( $calendar ) ? $calendar->get_is_show_past_events() : FALSE;

		$radio_format =
			'<div><label>' .
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
		$calendar = Calendar::get_calendar_by_id( $post->ID );
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
			'<div><label title="%1$s">' .
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
		$calendar = Calendar::get_calendar_by_id( $post->ID );
		$selected_id_array = isset( $calendar ) ? $calendar->get_duration_ids_array() : array();
		self::render_duration_checkboxes( $selected_id_array );
		$msg = __(
				'Select the timespans used to display events in this calendar.' .
				'  The calendar may show one or more months at a time.',
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
		'<div><label title="%1$s">' .
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
	 * Render the registration calendars metabox for the calendar
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
/* FIXME - NOT USED
	public static function render_registration_calendars_metabox( $post ) {
		self::render_registration_select_inputs( $post->ID );
		$msg = __(
				'Select the calendars used to provide events during registration.',
				'reg-man-rc'
		);
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_registration_select_inputs( $calendar_post_id ) {

		// We need a flag to distinguish the case where no special purposes were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="registration_calendars_input_flag" value="TRUE">';

		$calendars_array = Calendar::get_all_calendars();
		$calendar_names_array = array();
		$calendar_names_array[ $calendar_post_id ] = __( '[ Use this calendar ]', 'reg-man-rc' ); // Put this at top
		foreach( $calendars_array as $calendar ) {
			$id = $calendar->get_post_id();
			if ( $id !== $calendar_post_id ) {
				$calendar_names_array[ $id ] = $calendar->get_name();
			} // endif
		} // endfor

		$input_name = Calendar::CALENDAR_TYPE_VISITOR_REG;
		$label = __( 'Visitor / Item Registration', 'reg-man-rc' );
		$selected_id = Settings::get_visitor_registration_calendar_post_id();
		echo '<p>';
			echo "<label for=\"$input_name\">$label";
				self::render_calendar_select( $calendar_names_array, $input_name, $selected_id );
			echo '<label>';
		echo '</p>';

		$input_name = Calendar::CALENDAR_TYPE_VOLUNTEER_REG;
		$label = __( 'Volunteer Registration', 'reg-man-rc' );
		$selected_id = Settings::get_volunteer_registration_calendar_post_id();
		echo '<p>';
			echo "<label for=\"$input_name\">$label";
				self::render_calendar_select( $calendar_names_array, $input_name, $selected_id );
			echo '<label>';
		echo '</p>';

	} // function


	private static function render_calendar_select( $calendar_names_array, $input_name, $selected_id, $is_required = TRUE ) {

		$input_name = esc_attr( $input_name );
		$input_id = esc_attr( $input_name );
		$required = $is_required ? 'required="required"' : '';
		echo "<select id=\"$input_id\" name=\"$input_name\" $required autocomplete=\"off\">";
			foreach ( $calendar_names_array as $id => $name ) {
				$html_name = esc_html( $name );
				$selected = selected( $id, $selected_id, $echo = FALSE );
				echo "<option value=\"$id\" $selected>$html_name</option>";
			} // endfor
		echo '</select>';

	} // function

*/

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

	public static function filter_admin_UI_columns( $columns ) {

		$event_category_tax_col = 'taxonomy-' . Event_Category::TAXONOMY_NAME;

		$result = array(
			'cb'						=> $columns[ 'cb' ],
			'title'						=> __( 'Calendar Title',			'reg-man-rc' ),
			$event_category_tax_col		=> __( 'Event Categories',			'reg-man-rc' ),
			'event_statuses'			=> __( 'Event Statuses',			'reg-man-rc' ),
			'view_formats'				=> __( 'Views',						'reg-man-rc' ),
			'durations'					=> __( 'Timespans',					'reg-man-rc' ),
			'date'						=> __( 'Last Update',				'reg-man-rc' ),
			'author'					=> __( 'Author',					'reg-man-rc' ),
		);
		return $result;

	} // function

	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$calendar = Calendar::get_calendar_by_id( $post_id );
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



} // class