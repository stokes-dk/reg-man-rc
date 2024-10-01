<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Event_Class;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Model\Events_Collection;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Recurrence_Rule;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;
use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\Control\Internal_Event_Descriptor_Controller;

/**
 * The administrative view for internal event descriptors
 *
 * @since	v0.1.0
 *
 */
class Internal_Event_Descriptor_Admin_View {

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Add the metaboxes for things that are not taxonomies and therefore don't already have metaboxes like the event date
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_event_meta_boxes' ), 10, 2 );
		
		// Change the placeholder text for "Enter Title Here"
		add_filter( 'enter_title_here', array( __CLASS__, 'rewrite_enter_title_here' ) );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'update_post_messages') );

		// Add columns to the admin UI
		add_filter( 'manage_' . Internal_Event_Descriptor::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the custom values into the columns
		add_action( 'manage_' . Internal_Event_Descriptor::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Register my columns as sortable
		add_filter( 'manage_edit-' . Internal_Event_Descriptor::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

	} // function

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
		if ( is_object( $screen ) &&
				( $screen->post_type == Internal_Event_Descriptor::POST_TYPE ) &&
				( empty( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			Scripts_And_Styles::enqueue_select2();
			Scripts_And_Styles::enqueue_fullcalendar();
		} // endif
	} // function

	/**
	 * Add event meta boxes
	 * @param	string		$post_type
	 * @param	\WP_Post	$post
	 */
	public static function add_event_meta_boxes( $post_type, $post ) {
		if ( $post_type == Internal_Event_Descriptor::POST_TYPE ) {

			if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
				// Don't bother showing the owner metabox if there's only one option
				$event_editors = Internal_Event_Descriptor::get_event_editors_array();
				if ( count( $event_editors ) > 1 ) {
					add_meta_box(
						'reg-man-rc-event-owner-metabox',
						__( 'Event Owner', 'reg-man-rc' ),
						array( __CLASS__, 'render_event_owner_metabox' ),
						Internal_Event_Descriptor::POST_TYPE,
						'side', // section to place the metabox (normal, side or advanced)
						'default' // priority within the section (high, low or default)
					);
				} // endif
			} // endif

			add_meta_box(
				'reg-man-rc-event-date-metabox',
				__( 'Event Date and Time', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_date_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'normal', // section to place the metabox (normal, side or advanced)
				'high' // priority within the section (high, low or default)
			);

			add_meta_box(
				'reg-man-rc-event-venue-metabox',
				__( 'Venue', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_venue_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'normal', // section to place the metabox (normal, side or advanced)
				'high' // priority within the section (high, low or default)
			);

			add_meta_box(
				'reg-man-rc-event-status-metabox',
				__( 'Event Status', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_status_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'default' // priority within the section (high, low or default)
			);

			$view = Event_Category_Admin_View::create();
			$is_multi = Settings::get_is_allow_event_multiple_categories();
			$label = $is_multi ? __( 'Event Categories', 'reg-man-rc' ) : __( 'Event Category', 'reg-man-rc' );
			add_meta_box(
				'reg-man-rc-event-category-metabox',
				$label,
				array( $view, 'render_post_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'default' // priority within the section (high, low or default)
			);

			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Fixer Stations', 'reg-man-rc' );
			add_meta_box(
				'reg-man-rc-event-fixer-station-metabox',
				$label,
				array( $view, 'render_post_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'default' // priority within the section (high, low or default)
			);
			
			$label = __( 'Volunteer Pre-Registration Notes', 'reg-man-rc' );
			add_meta_box(
				'reg-man-rc-event-volunteer-pre-reg-note-metabox',
				$label,
				array( __CLASS__, 'render_volunteer_pre_reg_note_post_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'default' // priority within the section (high, low or default)
			);
			
		} // endif
	} // function

	/**
	 * Render the event owner metabox for the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.5.0
	 */
	public static function render_event_owner_metabox( $post ) {
//		$curr_event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post->ID );
		$curr_event_author_id = $post->post_author;
		
		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="event_owner_input_flag" value="TRUE">';
		
		$event_editors = Internal_Event_Descriptor::get_event_editors_array();
		
		$input_name = 'post_author_override'; // WordPress will handle this automatically as author

		echo "<select name=\"$input_name\" autocomplete=\"off\">";
		
			foreach( $event_editors as $editor_id => $editor_name ) {
				$selected = ( $curr_event_author_id == $editor_id ) ? 'selected="selected"' : '';
				$html_name = esc_html( $editor_name );
				echo "<option value=\"$editor_id\" $selected>$html_name</option>";
			} // endfor
			
		echo '</select>';

		$msg = __( 'Delegate this event to the selected user.  You will retain the ability to edit the event details, including its owner.',
				'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
		
	} // function
	
	/**
	 * Render the metabox for the note to be shown only in the volunteer area the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.8.7
	 */
	public static function render_volunteer_pre_reg_note_post_metabox( $post ) {
		$curr_event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post->ID );
		
		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_pre_reg_note_input_flag" value="TRUE">';
		
		$note = $curr_event_desc->get_volunteer_pre_reg_note();
		
		$input_name = 'vol_pre_reg_note';

		echo "<textarea name=\"$input_name\" autocomplete=\"off\">$note</textarea>";

		$msg = __( 'This note will be shown only in the volunteer area before the volunteer registers for the event.',
				'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
		
	} // function
	
	/**
	 * Render the event status metabox for the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_status_metabox( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="event_status_input_flag" value="TRUE">';

		$desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post->ID );
		$status = isset( $desc ) ? $desc->get_event_status() : NULL;
		$selected_id = isset( $status ) ? $status->get_id() : Event_Status::get_default_event_status_id();
		self::render_status_radio_buttons( $selected_id );
	} // function

	private static function render_status_radio_buttons( $selected_id ) {
		$all_status = Event_Status::get_all_event_statuses();
		$input_name = 'event_status';
		// Note that we can't use the same input id for multiple radio buttons
		$format =
			'<div><label title="%1$s" class="reg-man-rc-metabox-radio-label">' .
				'<input type="radio" name="' . $input_name . '" value="%2$s" %3$s>' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $all_status as $status ) {
			$id = $status->get_id();
			$name = $status->get_name();
			$desc = $status->get_description();
			$html_name = esc_html( $name );
			$attr_desc = esc_attr( $desc );
			$checked = checked( $id, $selected_id, $echo = FALSE );
			printf( $format, $attr_desc, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Render the event date meta box
	 * @param \WP_Post $post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_date_metabox( $post ) {

		$event_descriptor = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post->ID );
//		Error_Log::var_dump( $event_descriptor );

		$item_total = $event_descriptor->get_total_items_count();
		$visitor_total = $event_descriptor->get_total_visitors_count();
		$volunteer_total = $event_descriptor->get_total_volunteers_count();
	
//		Error_Log::var_dump( $item_total, $visitor_total, $volunteer_total );
	
		$has_registrations = ( $item_total !== 0 || $visitor_total !== 0 || $volunteer_total !== 0 );
		
		$addn_classes = $has_registrations ? 'inputs-require-enablement' : '';
		
		echo "<div class=\"event-dates-times-metabox-container $addn_classes\">";
		
			if ( $has_registrations ) {
	
				$warning_title = __( 'Warning: Items and/or volunteers have been registered to this event', 'reg-man-rc' );
				echo '<h3>' . $warning_title . '</h3>';
				
				$warning_msg = __(
						'Each registration includes the event date.' . 
						'  Changing the date of this event or the settings for a repeating event may cause registrations to become orphaned.',
						'reg-man-rc' );
				echo '<p>' . $warning_msg . '</p>';
				
				$input_list = Form_Input_List::create();
				$label = __( 'Modify event date settings', 'reg-man-rc' );
				$name = 'event_dates_input_flag';
				$val = 'TRUE';
				$is_checked = FALSE;
				$hint = __( 'To modify the event date settings you must check this box', 'reg-man-rc' );
				$classes = 'event-dates-input-unlock-item';
				$is_required = FALSE;
				$addn_attrs = 'class="event-dates-input-unlock"';
				$input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes, $is_required, $addn_attrs );
				
				$input_list->render();
				
			} else {
				
				// We need a flag to distinguish the case where no user input is provided
				//  versus the case where no inputs were shown at all like in quick edit mode
				echo '<input type="hidden" name="event_dates_input_flag" value="TRUE">';
				
			} // endif

			echo '<div class="event-dates-times-input-container">';
			
				self::render_event_dates_times_inputs( $event_descriptor );
	
			echo '</div>';
		
		echo '</div>';
		
	} // function


	/**
	 * Render the inputs for the event's dates and times
	 * @param	Internal_Event_Descriptor	$event_descriptor
	 * @return	void
	 * @since	v0.5.0
	 */
	public static function render_event_dates_times_inputs( $event_descriptor ) {
		
		if ( isset( $event_descriptor ) && ( $event_descriptor instanceof Internal_Event_Descriptor ) ) {
			$start_date_time = $event_descriptor->get_event_start_date_time();
			$end_date_time = $event_descriptor->get_event_end_date_time();
		} else {
			$start_date_time = NULL;
			$end_date_time = NULL;
		} // endif

		$input_list = Form_Input_List::create();
		$input_list->set_style_compact();
		$input_list->add_list_classes( 'event-dates-times-input-list basic-event-dates-times-input-list' );
		
		$date_input_format = 'Y-m-d'; // The date input requires the value to be formated using ISO 8601
		$time_input_format = 'H:i';   // The time input requires the value to be formated using 24-hour clock with leading zeros

		$label = __( 'Event date', 'reg-man-rc' );
		$name = 'event_start_date';
		$val = isset( $start_date_time ) ? $start_date_time->format( $date_input_format ) : '';
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';  // used to construct recurring event date/times
		$is_required = TRUE;
		$input_list->add_date_input( $label, $name, $val, $hint, $classes, $is_required );

		$label = __( 'Start time', 'reg-man-rc' );
		$name = 'event_start_time';
		$val = isset( $start_date_time ) ? $start_date_time->format( $time_input_format ) : Settings::get_default_event_start_time();
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';  // used to construct recurring event date/times
		$is_required = TRUE;
		$addn_attrs = 'step="1800"'; // 60 seconds * 30 minutes
		$input_list->add_time_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		$label = __( 'End time', 'reg-man-rc' );
		$name = 'event_end_time';
		$val = isset( $end_date_time ) ? $end_date_time->format( $time_input_format ) : Settings::get_default_event_end_time();
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';  // used to construct recurring event date/times
		$is_required = TRUE;
		$addn_attrs = 'step="1800"'; // 60 seconds * 30 minutes
		$input_list->add_time_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		// Is recurring
		$is_allow_recurring = Settings::get_is_allow_recurring_events();
		if ( $is_allow_recurring ) {
			$label = __( 'Repeat this event', 'reg-man-rc' );
			$name = 'event_recur_flag';
			$rrule = $event_descriptor->get_event_recurrence_rule();
			$val = '1';
			$is_checked = ! empty( $rrule );
			$hint = __( 'E.g. every month until the end of the year', 'reg-man-rc' );
			$classes = 'recur-rule-input event-recur-flag rrule-text-input'; // used to construct recurring event date/times
			$is_required = FALSE;
			$addn_attrs = 'autocomplete="off"';
			$input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes, $is_required, $addn_attrs );
		} // endif

		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_action = Internal_Event_Descriptor_Controller::GET_RECUR_EVENT_DATES_AJAX_ACTION;
		$nonce = wp_create_nonce( $ajax_action );
		$data = array();
		$data[] = "data-get-recur-event-dates-ajax-url=\"$ajax_url\"";
		$data[] = "data-get-recur-event-dates-ajax-action=\"$ajax_action\"";
		$data[] = "data-get-recur-event-dates-ajax-nonce=\"$nonce\"";
		$data_str = implode( ' ', $data );
		
		echo "<div class=\"event-dates-and-times-container\" $data_str>";

			$input_list->render();
	
			if ( $is_allow_recurring ) {
				
				echo '<div class="recurring-event-input-container">';

					self::render_recurrence_inputs( $event_descriptor );

				echo '</div>';
					
			} // endif
		
		echo '</div>';
		
	} // function
	
	/**
	 * Render the inputs for a recurring event
	 * @param	Internal_Event_Descriptor	$event_descriptor
	 */
	private static function render_recurrence_inputs( $event_descriptor ) {
		
		$rrule = $event_descriptor->get_event_recurrence_rule();
		
		$input_list = Form_Input_List::create();
		$list_classes = 'recurring-event-input-list event-dates-times-input-list';
		$input_list->add_list_classes( $list_classes );
		$input_list->set_style_compact();
		
		// Until
		$label = __( 'Repeat until', 'reg-man-rc' );
		$name = 'event_recur_until_date';
		$recur_until_date_time = isset( $rrule ) ? $rrule->get_until() : NULL;
		$date_input_format = 'Y-m-d'; // The date input requires the value to be formated using ISO 8601
		$val = isset( $recur_until_date_time ) ? $recur_until_date_time->format( $date_input_format ) : '';
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';
		$is_required = TRUE;
		$addn_attrs = 'disabled="disabled"';
		$input_list->add_date_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );
		
		// Repeat Every...
		$freq_input_list = Form_Input_List::create();
		$freq_input_list->set_style_compact();

		$label = __( 'Interval', 'reg-man-rc' );
		$name = 'event_recur_interval';
		$val = isset( $rrule ) ? $rrule->get_interval() : 1;
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';
		$is_required = TRUE;
		$addn_attrs = 'min=1 size=3 disabled="disabled"';
		$freq_input_list->add_number_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		$label = __( 'Frequency', 'reg-man-rc' );
		$name = 'event_recur_frequency';
		$options = array(
				__( 'Day(s)', 'reg-man-rc' )	=> Recurrence_Rule::DAILY,
				__( 'Week(s)', 'reg-man-rc' )	=> Recurrence_Rule::WEEKLY,
				__( 'Month(s)', 'reg-man-rc' )	=> Recurrence_Rule::MONTHLY,
				__( 'Year(s)', 'reg-man-rc' )	=> Recurrence_Rule::YEARLY,
		);
		$selected = isset( $rrule ) ? $rrule->get_frequency() : Recurrence_Rule::MONTHLY;
		$hint = '';
		$classes = 'recur-rule-input rrule-text-input';
		$is_required = TRUE;
		$addn_attrs = 'disabled="disabled"';
		$freq_input_list->add_select_input( $label, $name, $options, $selected, $hint, $classes, $is_required, $addn_attrs );
		
		$label = __( 'Repeat every', 'reg-man-rc' );
		$hint = '';
		$classes = '';
		$is_required = TRUE;
		$input_list->add_fieldset( $label, $freq_input_list, $hint, $classes, $is_required );

		// On...
		self::render_recur_weekly_on_inputs( $input_list, $rrule );

		self::render_recur_monthly_on_inputs( $input_list, $rrule );
		
		self::render_recur_yearly_on_inputs( $input_list, $rrule );
		
		// TODO: Add inputs for additional dates and exclude dates (RDATE and EXDATE)
		//  Maybe also support cancellation dates?  I.e. dates marked on the calendar but cancelled

		$input_list->render();

		self::render_cancelled_dates_inputs( $event_descriptor );
		
		self::render_event_dates_calendar( $event_descriptor );
		
	} // function

	/**
	 * Render the calendar for a repeating event's dates
	 * @param Internal_Event_Descriptor $event_descriptor
	 */
	private static function render_cancelled_dates_inputs( $event_descriptor ) {
		echo '<div class="recurring-event-input-list cancel-dates-container">';
		
			echo '<input type="hidden" name="cancel_recurring_event_dates_input_flag" value="1">';

			$input_list = Form_Input_List::create();
			$list_classes = 'recurring-event-input-list event-cancel-dates-input-list';
			$input_list->add_list_classes( $list_classes );
		

			$events_array = $event_descriptor->get_event_object_array();
			$options_array = array();
		
			foreach( $events_array as $event ) {
				$event_start_date = $event->get_start_date_time_object();
				if ( ! empty( $event_start_date ) ) {
					$event_date_label = $event->get_start_date_string_in_display_format();
					$event_date_data = $event->get_start_date_string_in_data_format();
					$options_array[ $event_date_label ] = $event_date_data;
				} // endif
			} // endfor

			$cancel_dates = $event_descriptor->get_cancelled_event_dates();
			$selected = array();
			foreach( $cancel_dates as $cancel_date_time ) {
				$date_str = $cancel_date_time->format( Event_Key::EVENT_DATE_FORMAT );
				$selected[] = $date_str;
			} // endfor
		
			$select_classes = 'combobox recur-event-dates-change-listener recur-event-dates-multi-select';
			$label = __( 'Mark these dates as "Cancelled"', 'reg-man-rc' );
			$name = 'recur_cancel_dates[]';
			$hint = '';
			$classes = '';
			$addn_attrs = "multiple=\"multiple\" class=\"$select_classes\"";
			$is_required = FALSE;
			$input_list->add_select_input( $label, $name, $options_array, $selected, $hint, $classes, $is_required, $addn_attrs );
		
			$input_list->render();
		
		echo '</div>';
	} // function

	/**
	 * Render the calendar for a repeating event's dates
	 * @param Internal_Event_Descriptor $event_descriptor
	 */
	private static function render_event_dates_calendar( $event_descriptor ) {

		$input_list = Form_Input_List::create();
		$list_classes = 'recurring-event-input-list event-dates-calendar';
		$input_list->add_list_classes( $list_classes );
		
		$label = __( 'Event dates', 'reg-man-rc' );
		$calendar_fieldset = Form_Input_List::create();
		$calendar = Calendar::get_event_descriptor_calendar( $event_descriptor );
		$calendar_view = Calendar_View::create( $calendar );
		ob_start();
			$calendar_view->render();
		$calendar_content = ob_get_clean();
		$calendar_fieldset->add_custom_html_input( '', '', $calendar_content );
		$input_list->add_fieldset( $label, $calendar_fieldset );

		$input_list->render();
		
	} // function

	/**
	 * Get an array of keys for the days of the week starting with the correct start of week day for this installation
	 * @return string[]
	 */
	private static function get_week_day_keys_array() {
		$keys_array = array( 'SU', 'MO', 'TU', 'WE','TH', 'FR', 'SA' );
		$result = array();
		$start_of_week = get_option( 'start_of_week' );
		$index = $start_of_week;
		for( $count = 0; $count < 7; $count++ ) {
			$index = ( $start_of_week + $count ) % 7;
			$result[] = $keys_array[ $index ];
		} // endfor
		return $result;
	} // function
	
	/**
	 * Get an array of days of the week starting with the correct start of week day for this installation and keyed by
	 *  RRULE standard BYDAY keys, e.g. 'SU', 'MO' etc.
	 * @return string[][]
	 */
	private static function get_week_days_array() {
		$result = array();
		$keys = self::get_week_day_keys_array();
		$labels_array = array(
			'SU'	=> __( 'Sunday'		, 'reg-man-rc' ),
			'MO'	=> __( 'Monday'		, 'reg-man-rc' ),
			'TU'	=> __( 'Tuesday'	, 'reg-man-rc' ),
			'WE'	=> __( 'Wednesday'	, 'reg-man-rc' ),
			'TH'	=> __( 'Thursday'	, 'reg-man-rc' ),
			'FR'	=> __( 'Friday'		, 'reg-man-rc' ),
			'SA'	=> __( 'Saturday'	, 'reg-man-rc' ),
		);
		foreach( $keys as $key ) {
			$result[ $key ] = $labels_array[ $key ];
		} // endfor
		
		return $result;
	} // function
	
	/**
	 * Get an array of days of the month ordered by the correct start of week day for this installation and keyed by
	 *  RRULE standard BYDAY keys, e.g. '1SU' => 'First Sunday', '1MO' => 'First Monday' ... '-1SA' => 'Last Saturday'
	 * @return string[][]
	 */
	private static function get_days_of_month_array() {
		
		$result = array(); 
		
		// Get the week days keys in the correct order based on start_of_week
		$week_days_array = self::get_week_days_array();
		
		$weeks_array = array(
				'1'		=> __( 'First'	, 'reg-man-rc' ), 
				'2'		=> __( 'Second'	, 'reg-man-rc' ),
				'3'		=> __( 'Third'	, 'reg-man-rc' ),
				'4'		=> __( 'Fourth'	, 'reg-man-rc' ),
				'5'		=> __( 'Fifth'	, 'reg-man-rc' ),
				'-1'	=> __( 'Last'	, 'reg-man-rc' ),
		);
		
		/* Translators: %1$s is a week ordinal like "Third", %2$s is a day of the week like "Friday" */
		$format = _x( '%1$s %2$s', 'A format for combining a week ordinal number and week day in a month, like "Third Friday"', 'reg-man-rc' );
		foreach( $weeks_array as $week_num => $week_label ) {
			
			foreach( $week_days_array as $day_key => $day_label ) {
				
				$label = sprintf( $format, $week_label, $day_label ); // E.g. "Third Friday"
				$val = $week_num . $day_key; // E.g. "3FR"
				$result[ $label ] = $val;
				
			} // endfor
			
		} // endfor

		return $result;
		
	} // function
	
	/**
	 * Render the inputs for recur weekly on days of the week
	 * @param	Form_Input_List	$input_list
	 * @param	Recurrence_Rule	$rrule
	 */
	private static function render_recur_weekly_on_inputs( $input_list, $rrule) {
		
		$weekly_on_input_list = Form_Input_List::create();
		$weekly_on_input_list->set_style_compact();

		$curr_by_day_array = isset( $rrule ) ? $rrule->get_by_day() : array();
		
		$name = 'recur_weekly_by_day[]';
		$hint = '';
		$classes = 'recur-rule-input rrule-weekly-by-day-input';

		$week_days_array = self::get_week_days_array(); // Gets them in the correct order
		foreach( $week_days_array as $val => $label ) {
			$is_checked = in_array( $val, $curr_by_day_array );
			$weekly_on_input_list->add_checkbox_input( $label, $name, $val, $is_checked, $hint, $classes );
		} // endfor

		$label = __( 'Repeat on day(s) of week', 'reg-man-rc' );
		$hint = __( 'Default is event date day of week', 'reg-man-rc' );
		$classes = 'recur-rule-repeat-on-input-list WEEKLY';
		$is_required = FALSE;
		$input_list->add_fieldset( $label, $weekly_on_input_list, $hint, $classes, $is_required );
		
	} // function

	/**
	 * Render the inputs for recur monthly on certain days
	 * @param	Form_Input_List	$input_list
	 * @param	Recurrence_Rule	$rrule
	 */
	private static function render_recur_monthly_on_inputs( $input_list, $rrule) {
		
		$is_monthly_selected = isset( $rrule ) ? ( $rrule->get_frequency() == 'MONTHLY' ) : FALSE;
		
		$curr_by_day_array = ( $is_monthly_selected && isset( $rrule ) ) ? $rrule->get_by_day() : array();

		$select_options_array = self::get_days_of_month_array();
		
		$label = __( 'Day(s) of month', 'reg-man-rc' );
		$name = 'recur_monthly_by_day[]';
		$selected = $curr_by_day_array;
		$hint = '';
		$addn_attrs = 'multiple="multiple" class="combobox"';
		
		$label = __( 'Repeat on day(s) of month', 'reg-man-rc' );
		$hint = __( 'Default is event date day of month', 'reg-man-rc' );
		$classes = 'recur-rule-input rrule-monthly-by-day-input recur-rule-repeat-on-input-list MONTHLY';
		$is_required = FALSE;
		$input_list->add_select_input( $label, $name, $select_options_array, $selected, $hint, $classes, $is_required, $addn_attrs );
		
	} // function

	/**
	 * Render the inputs for recur monthly on certain days
	 * @param	Form_Input_List	$input_list
	 * @param	Recurrence_Rule	$rrule
	 */
	private static function render_recur_yearly_on_inputs( $input_list, $rrule) {
		
		$yearly_on_input_list = Form_Input_List::create();
		$yearly_on_input_list->set_style_compact();
		
		$is_yearly_selected = isset( $rrule ) ? ( $rrule->get_frequency() == 'YEARLY' ) : FALSE;

		$curr_by_month_array = ( $is_yearly_selected && isset( $rrule ) ) ? $rrule->get_by_month() : array();
		$curr_by_day_array = ( $is_yearly_selected && isset( $rrule ) ) ? $rrule->get_by_day() : array();

		// Month of year
		$select_options_array = array(
				__( 'January', 'reg-man-rc' )	=> 1,
				__( 'February', 'reg-man-rc' )	=> 2,
				__( 'March', 'reg-man-rc' )		=> 3,
				__( 'April', 'reg-man-rc' )		=> 4,
				__( 'May', 'reg-man-rc' )		=> 5,
				__( 'June', 'reg-man-rc' )		=> 6,
				__( 'July', 'reg-man-rc' )		=> 7,
				__( 'August', 'reg-man-rc' )	=> 8,
				__( 'September', 'reg-man-rc' )	=> 9,
				__( 'October', 'reg-man-rc' )	=> 10,
				__( 'November', 'reg-man-rc' )	=> 11,
				__( 'December', 'reg-man-rc' )	=> 12,
		);
		$label = __( 'Month(s) of year', 'reg-man-rc' );
		$name = 'recur_yearly_by_month[]';
		$selected = $curr_by_month_array;
		$hint = '';
		$classes = 'recur-rule-input rrule-yearly-by-month-input';
		$is_required = FALSE;
		$addn_attrs = 'multiple="multiple" class="combobox"';
		$yearly_on_input_list->add_select_input( $label, $name, $select_options_array, $selected, $hint, $classes, $is_required, $addn_attrs );
		
		// Days of month
		$select_options_array = self::get_days_of_month_array();		
		$label = __( 'Day(s) of month', 'reg-man-rc' );
		$name = 'recur_yearly_by_day[]';
		$selected = $curr_by_day_array;
		$hint = '';
		$classes = 'recur-rule-input rrule-yearly-by-day-input';
		$is_required = FALSE;
		$addn_attrs = 'multiple="multiple" class="combobox"';
		$yearly_on_input_list->add_select_input( $label, $name, $select_options_array, $selected, $hint, $classes, $is_required, $addn_attrs );
		
		$label = __( 'Repeat on day(s) of year', 'reg-man-rc' );
		$hint = __( 'Default is month and day of event date', 'reg-man-rc' );
		$classes = 'recur-rule-repeat-on-input-list YEARLY';
		$is_required = FALSE;
		$input_list->add_fieldset( $label, $yearly_on_input_list, $hint, $classes, $is_required );
		
	} // function

	/**
	 * Render the event date meta box
	 * @param \WP_Post $post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_venue_metabox( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="event_venue_input_flag" value="TRUE">';

		$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post->ID );
		if ( isset( $event ) && ( $event instanceof Internal_Event_Descriptor ) ) {
			$curr_venue = $event->get_event_venue();
		} else {
			$curr_venue = NULL;
		} // endif

		// Add the marker data for all venues so I can update the map when the user changes venue
		echo '<script class="reg-man-rc-event-venue-marker-json">'; // Script tag for map markers as json
			$venue_array = Venue::get_all_venues();
			echo Map_View::get_marker_json_data( $venue_array, Map_View::MAP_TYPE_OBJECT_PAGE );
		echo '</script>';

		$input_list = Form_Input_List::create();

		$label = __( 'Select the venue',  'reg-man-rc' );
		$name = 'event_venue_select';
		$hint = '';
		$classes = '';
		$input_id = 'event_venue_select_input_id';
		$selected_id = ( isset( $curr_venue ) && ( $curr_venue instanceof Venue ) ) ? $curr_venue->get_id() : NULL;
		ob_start();
			self::render_venue_select( $name, $input_id, $selected_id );
		$select_html = ob_get_clean();
		$is_required = FALSE;
		$input_list->add_custom_html_input( $label, $name, $select_html, $hint, $classes, $is_required, $input_id );

		ob_start();
			Venue_Admin_View::render_venue_location_input( $curr_venue, $include_name_input = TRUE );
		$add_html = ob_get_clean();
		$input_list->add_custom_html_input( '', '', $add_html );

		$input_list->render();

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


	private static function render_venue_select( $input_name, $input_id, $selected_id ) {

		$venues = Venue::get_all_venues();

		// Disabled to start with until it is initialized on the client side
		$style = 'style="width:100%;"'; // otherwise the input is based on currently selected text!
		echo "<select class=\"combobox\" name=\"$input_name\" id=\"$input_id\" autocomplete=\"off\" disabled=\"disabled\" $style>";

			$label = __( 'This event has no venue', 'reg-man-rc' );
			$html_name= esc_html( $label );
			$selected = ( empty( $selected_id ) ) ? 'selected="selected"' : '';
			echo "<option value=\"0\" class=\"select_option_none\" $selected>$html_name</option>";

			if ( ! empty( $venues ) ) {
					foreach ( $venues as $venue ) {
						$id = $venue->get_id();
						$venue_label = $venue->get_name();
						$html_label = esc_html( $venue_label );
						$selected = selected( $id, $selected_id, $echo = FALSE );
						echo "<option value=\"$id\" $selected>$html_label</option>";
					} // endfor
			} // endif

			$label = __( 'Add a new venue', 'reg-man-rc' );
			$html_name= esc_html( $label );
			$selected = '';
			echo "<option value=\"-1\" class=\"select_option_add\" $selected>$html_name</option>";

		echo '</select>';
	} // function

	public static function rewrite_enter_title_here( $input ) {
		// Change the placeholder text for "Enter Title Here" if the specified post is mine
		if ( Internal_Event_Descriptor::POST_TYPE === get_post_type() ) {
			$input = __( 'Enter the event title here', 'reg-man-rc' );
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
		$messages[ Internal_Event_Descriptor::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Event updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Event updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Event restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Event published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Event saved.'),
				8 => sprintf( __('Event submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Event scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Event draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
		);
		return $messages;
	} // function

	public static function filter_admin_UI_columns( $columns ) {
		$venue_key			= Venue::POST_TYPE;
//		$fixer_station_key	= 'taxonomy-' . Fixer_Station::TAXONOMY_NAME;
		$event_category_key	= 'taxonomy-' . Event_Category::TAXONOMY_NAME;
		$category_heading = Settings::get_is_allow_event_multiple_categories() ? __( 'Categories', 'reg-man-rc' ) : __( 'Category', 'reg-man-rc' );
		$result = array(
			'cb'				=> $columns['cb'],
			'title'				=> __( 'Title', 'reg-man-rc' ),
			'event_status'		=> __( 'Status', 'reg-man-rc' ),
			'event_class'		=> __( 'Visibility', 'reg-man-rc' ),
			'event_date'		=> __( 'Date & Time', 'reg-man-rc' ),
			'is_recurring'		=> __( 'Repeats', 'reg-man-rc' ),
			$venue_key			=> __( 'Venue', 'reg-man-rc' ),
			$event_category_key	=> $category_heading,
			'fixer_stations'	=> __( 'Fixer Stations', 'reg-man-rc' ),
			'item_count'		=> __( 'Items', 'reg-man-rc' ),
			'fixer_count'		=> __( 'Fixers', 'reg-man-rc' ),
			'non_fixer_count'	=> __( 'Non-fixers', 'reg-man-rc' ),
			'comments'			=> $columns[ 'comments' ],
			'date'				=> __( 'Last Update', 'reg-man-rc' ),
			'author'			=> __( 'Owner', 'reg-man-rc' ),
		);
		if ( ! Settings::get_is_allow_recurring_events() ) {
			unset( $result[ 'is_recurring' ] );
		} // endif
		return $result;
	} // function

	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$result = $em_dash; // show em-dash by default
		$event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_id( $post_id );

		if ( $event_desc !== NULL ) {
			switch ( $column_name ) {

				case 'event_date':
					$start_date = $event_desc->get_event_start_date_time();
					$end_date = $event_desc->get_event_end_date_time();
					$result = Event::create_label_for_event_dates_and_times( $start_date, $end_date );
					break;

				case Venue::POST_TYPE:
					$venue = $event_desc->get_event_venue();
					if ( isset( $venue) ) {
						$filter_array = array( Internal_Event_Descriptor::VENUE_META_KEY => $venue->get_id() );
						$href = self::get_admin_view_href( $filter_array );
						$venue_label = esc_html( $venue->get_name() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $venue_label );
					} else {
						$result = $em_dash;
					} // endif
					break;

				case 'is_recurring':
					$rrule = $event_desc->get_event_recurrence_rule();
					$result = ! empty( $rrule ) ? esc_html( $rrule->get_frequency_as_translated_text() ) : $em_dash;
					break;

				case 'event_status':
					$status = $event_desc->get_event_status();
					$result = isset( $status ) ? esc_html( $status->get_name() ) : $em_dash;
					break;

				case 'event_class':
					$class = $event_desc->get_event_class();
					$id = isset( $class ) ? $class->get_id() : '';
					switch( $id ) {

						case Event_Class::PUBLIC:
						case Event_Class::PRIVATE:
							$result = esc_html( $class->get_name() );
							break;
							
						case Event_Class::CONFIDENTIAL:
							$result = esc_html__( 'Private', 'reg-man-rc' );
							break;

						default:
							$result = $em_dash;
							break;
							
					} // endswitch
					break;

				case 'fixer_stations':
					$stations_array = $event_desc->get_event_fixer_station_array();
					$result = ! empty( $stations_array ) ? self::get_station_list( $stations_array ) : $em_dash;
					break;

				case 'item_count':
					$is_recurring = $event_desc->get_event_is_recurring();
					if ( $is_recurring ) {
						$result = $em_dash;
					} else {
						$event_date_time = $event_desc->get_event_start_date_time();
						$event_key_obj = Event_Key::create( $event_date_time, $event_desc->get_event_descriptor_id() );
						$events_collection = Events_Collection::create_for_single_event_key( $event_key_obj->get_as_string() );
						$group_by = Item_Stats_Collection::GROUP_BY_TOTAL;
						$stats_collection = Item_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
						$totals_array = array_values( $stats_collection->get_all_stats_array() );
						$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
						$total = isset( $total_stats ) ? $total_stats->get_item_count() : NULL;
						$result = ! empty( $total ) ? $total : $em_dash;
					} // endif
					break;

				case 'fixer_count':
					$is_recurring = $event_desc->get_event_is_recurring();
					if ( $is_recurring ) {
						$result = $em_dash;
					} else {
						$event_date_time = $event_desc->get_event_start_date_time();
						$event_key_obj = Event_Key::create( $event_date_time, $event_desc->get_event_descriptor_id() );
						$events_collection = Events_Collection::create_for_single_event_key( $event_key_obj->get_as_string() );
						$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL_FIXERS;
						$stats_collection = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
						$totals_array = array_values( $stats_collection->get_all_stats_array() );
						$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
						$total = isset( $total_stats ) ? $total_stats->get_head_count() : NULL;
						$result = ! empty( $total ) ? $total : $em_dash;
					} // endif
					break;
					
				case 'non_fixer_count':
					$is_recurring = $event_desc->get_event_is_recurring();
					if ( $is_recurring ) {
						$result = $em_dash;
					} else {
						$event_date_time = $event_desc->get_event_start_date_time();
						$event_key_obj = Event_Key::create( $event_date_time, $event_desc->get_event_descriptor_id() );
						$events_collection = Events_Collection::create_for_single_event_key( $event_key_obj->get_as_string() );
						$group_by = Volunteer_Stats_Collection::GROUP_BY_TOTAL_NON_FIXERS;
						$stats_collection = Volunteer_Stats_Collection::create_for_events_collection( $events_collection, $group_by );
						$totals_array = array_values( $stats_collection->get_all_stats_array() );
						$total_stats = isset( $totals_array[ 0 ] ) ? $totals_array[ 0 ] : NULL;
						$total = isset( $total_stats ) ? $total_stats->get_head_count() : NULL;
						$result = ! empty( $total ) ? $total : $em_dash;
					} // endif
					break;
					
			} // endswitch
		} // endif
		echo $result;
	} // function

	/**
	 * Get a list of fixer stations to be shown in the admin interface
	 * @param	Fixer_Station[]	$stations_array	An array of Fixer_Station objects
	 * @return string
	 */
	private static function get_station_list( $stations_array ) {
		ob_start();
			echo '<ul class="custom-post-type-admin-fixer-station-list">';
				foreach( $stations_array as $station ) {
					$station_text = $station->get_name();
					$id = $station->get_id();
					echo '<li class="fixer-station-item fixer-station-text" data-id="' . $id . '">';
						echo '<span class="reg-man-rc-custom-post-details-text">';
							echo $station_text;
						echo '</span>';
					echo '</li>';
				} // endfor
			echo '</ul>';
		$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Get an href for the admin page for this type and include the specified filters
	 * @param	string[][]	$filter_array	An array of filter keys and values
	 * @return	string		An escaped href attribute suitable for use in an anchor tag
	 */
	public static function get_admin_view_href( $filter_array = array() ) {
		$post_type = Internal_Event_Descriptor::POST_TYPE;
		$base_url = admin_url( 'edit.php' );
		$query_data = array(
				'post_type'		=> $post_type,
		);
		$query_data = array_merge( $query_data, $filter_array ); // Add any filters
		$query = http_build_query( $query_data );
		$result = esc_attr( "$base_url?$query" );
		return $result;
	} // function

	/**
	 * Add my columns to the list of sortable columns.
	 * This is called during the manage_edit-TAXONOMY_sortable_columns filter hook.
	 * @param	string[]	$columns	The array of columns to be made sortable
	 * @return	string[]	$columns	The array of columns to be made sortable
	 */
	public static function add_sortable_columns( $columns ) {
		$columns[ 'event_date' ] = 'event_date';
//		$columns[ Venue::POST_TYPE ] = Venue::POST_TYPE;
		return $columns;
	} // function

	/**
	 * Modify the filters user interface for the list of my custom posts.
	 * @param	string	$post_type
	 * @return	NULL
	 */
	public static function modify_posts_filters_UI( $post_type ) {
		if ( is_admin() && ( $post_type == Internal_Event_Descriptor::POST_TYPE ) ) {

			// Add a filter for the venue
			$all_venues = Venue::get_all_venues();
			$filter_name = Internal_Event_Descriptor::VENUE_META_KEY;
			$curr_venue = isset( $_REQUEST[ $filter_name ] ) ? $_REQUEST[ $filter_name ] : 0;
			echo "<select name=\"$filter_name\" class=\"reg-man-rc-filter postform\">";

				$name = esc_html( __( 'All Venues', 'reg-man-rc' ) );
				$selected = selected( $curr_venue, 0, FALSE );
				echo "<option value=\"0\" $selected>$name</option>";

				foreach ( $all_venues as $venue ) {
					$name = esc_html( $venue->get_name() );
					$id = esc_attr( $venue->get_id() );
					$selected = selected( $curr_venue, $id, FALSE );
					echo "<option value=\"$id\" $selected>$name</option>";
				} // endif

			echo '</select>';

			// Add a filter for each of these taxonomies
			// FIXME - I would like to provide Uncategorized option but the query does not work properly
			//  Most likely I will have to intervene and alter the query to make this work
			$tax_name_array = array(
					Event_Category::TAXONOMY_NAME	=> __( '[Uncategorized]', 'reg-man-rc' ),
					Fixer_Station::TAXONOMY_NAME	=> __( '[No fixer stations]', 'reg-man-rc' ),
			);
			foreach ( $tax_name_array as $tax_name => $none_option_label ) {
				$taxonomy = get_taxonomy( $tax_name );
				$curr_id = isset( $_REQUEST[ $tax_name ] ) ? $_REQUEST[ $tax_name ] : '';
				wp_dropdown_categories( array(
					'show_option_all'	=> $taxonomy->labels->all_items,
//					'show_option_none'	=> $none_option_label,
//					'option_none_value'	=> '',
					'class'				=> 'reg-man-rc-filter postform',
					'taxonomy'			=> $tax_name,
					'name'				=> $tax_name,
					'orderby'			=> 'count',
					'order'				=> 'DESC',
					'value_field'		=> 'slug',
					'selected'			=> $curr_id,
					'hierarchical'		=> $taxonomy->hierarchical,
					'show_count'		=> FALSE,
					'hide_if_empty'		=> TRUE,
				) );
			} // endfor

		} // endif
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
			$heading = __( 'About event descriptions', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'An event description contains the details of a single event or a series of repeating events.' .
					'  It includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Title', 'reg-man-rc' );
					$msg = esc_html__(
							'The event title, e.g. "Repair Café at Toronto Reference Library".' .
							'  This will be used to identify the event on a calendar.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Status', 'reg-man-rc' );
					$msg = esc_html__(
							'The event status; one of Confirmed (the event will take place),' . 
							' Tentative (the even has not yet been confirmed),' . 
							' or Cancelled (the event will not take place).',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Visibility', 'reg-man-rc' );
					$msg = esc_html__(
							'Published events are Public and visible to everyone; this is the normal visibility.' .
							'  Draft and privately published events are Private and are only visible on the administrative calendar.' .
							'  Public events may be shown on public calendars including upcoming events and the volunteer area calendar.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Date & Time', 'reg-man-rc' );
					$msg = esc_html__(
							'The date of the event and the time it starts and ends.',
//							'  For repeating events, this field shows the first possible event date and the start and end times for all events.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Repeats', 'reg-man-rc' );
					$msg = esc_html__(
							'An event description can define a single event or a repeating event, e.g. "Repeat every month until the end of the year".',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Venue', 'reg-man-rc' );
					$msg = esc_html__( 'The title of the venue for the event, e.g. "Toronto Reference Library".', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Category', 'reg-man-rc' );
					$msg = esc_html__( 'The event category, e.g. "Repair Café".', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Fixer Stations', 'reg-man-rc' );
					$msg = esc_html__( 'The list of fixer stations that will be set up at this event, e.g. "Appliances & Housewares, Computers, Bikes".', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
/*
					$title = esc_html__( 'Items', 'reg-man-rc' );
					$msg = esc_html__( 'A count of items registered for this event', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Fixers', 'reg-man-rc' );
					$msg = esc_html__( 'A count of fixers registered to attend this event', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Non-Fixers', 'reg-man-rc' );
					$msg = esc_html__( 'A count of non-fixer volunteers registered to attend this event', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Owner', 'reg-man-rc' );
					$msg = esc_html__( 'The event owner (author) who has authority to modify this event', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
*/
				echo '</dl>';
			echo '</p>';
/*
			echo '<p>';
				$msg = __(
					'When you register an item and begin typing the description, the system' .
					' will find and display matching item suggestions.' .
					'  For example, if you type "light" the system may show item suggestions like "Lamp", "Bike light" and "Nightlight".',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
*/

		$result = ob_get_clean();
		return $result;
	} // function
	
} // class
