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
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Event_Class;

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
		} // endif
	} // function

	/**
	 * Add event meta boxes
	 * @param	string		$post_type
	 * @param	\WP_Post	$post
	 */
	public static function add_event_meta_boxes( $post_type, $post ) {
		if ( $post_type == Internal_Event_Descriptor::POST_TYPE ) {

			add_meta_box(
				'reg-man-rc-event-date-metabox',
				__( 'Event Date and Time', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_date_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'high' // priority within the section (high, low or default)
			);

			add_meta_box(
				'reg-man-rc-event-status-metabox',
				__( 'Event Status', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_status_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'high' // priority within the section (high, low or default)
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
				'high' // priority within the section (high, low or default)
			);

			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Fixer Stations', 'reg-man-rc' );
			add_meta_box(
				'reg-man-rc-event-fixer-station-metabox',
				$label,
				array( $view, 'render_post_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
				'high' // priority within the section (high, low or default)
			);

			add_meta_box(
				'reg-man-rc-event-class-metabox',
				__( 'Event Class', 'reg-man-rc' ),
				array( __CLASS__, 'render_event_class_metabox' ),
				Internal_Event_Descriptor::POST_TYPE,
				'side', // section to place the metabox (normal, side or advanced)
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

		} // endif
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
		echo '<input type="hidden" name="item_status_input_flag" value="TRUE">';

		$desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
		$status = isset( $desc ) ? $desc->get_event_status() : NULL;
		$selected_id = isset( $status ) ? $status->get_id() : Event_Status::get_default_event_status_id();
		self::render_status_radio_buttons( $selected_id );
	} // function

	private static function render_status_radio_buttons( $selected_id ) {
		$all_status = Event_Status::get_all_event_statuses();
		$input_name = 'event_status';
		// Note that we can't use the same input id for multiple radio buttons
		$format =
			'<div><label title="%1$s">' .
				'<input type="radio" name="' . $input_name . '" value="%2$s" %3$s>' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $all_status as $status ) {
			$id = $status->get_id();
			$name = $status->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = checked( $id, $selected_id, $echo = FALSE );
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Render the event class metabox for the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_class_metabox( $post ) {
		$desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
		$class = isset( $desc ) ? $desc->get_event_class() : NULL;
		$selected_id = isset( $class ) ? $class->get_id() : Event_Class::PUBLIC;
		self::render_class_radio_buttons( $selected_id );

		$msg = __( 'The event class is determined automatically based on the published status and visibility.',
				'reg-man-rc' );
		echo '<p>' . $msg . '</p>';
	} // function

	private static function render_class_radio_buttons( $selected_id ) {

		$event_classes = Event_Class::get_all_event_classes();
		$input_name = 'event_class';

		$format =
			'<div><label title="%1$s">' .
				'<input type="radio" name="' . $input_name . '" value="%2$s" %3$s autocomplete="off" disabled="disabled">' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $event_classes as $class_obj ) {
			$id = $class_obj->get_id();
			$name = $class_obj->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = checked( $id, $selected_id, $echo = FALSE );
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
	} // function


	/**
	 * Render the event date meta box
	 * @param \WP_Post $post
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function render_event_date_metabox( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="event_dates_input_flag" value="TRUE">';

		$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
		if ( isset( $event ) && ( $event instanceof Internal_Event_Descriptor ) ) {
			$start_date_time = $event->get_event_start_date_time();
			$end_date_time = $event->get_event_end_date_time();
		} else {
			$start_date_time = NULL;
			$end_date_time = NULL;
		} // endif

		$input_list = Form_Input_List::create();
		$input_list->set_style_compact();

		$date_input_format = 'Y-m-d'; // The date input requires the value to be formated using ISO 8601
		$time_input_format = 'H:i';   // The time input requires the value to be formated using 24-hour clock with leading zeros

		$label = __( 'Event Date', 'reg-man-rc' );
		$name = 'event_start_date';
		$val = isset( $start_date_time ) ? $start_date_time->format( $date_input_format ) : '';
		$hint = '';
		$classes = '';
		$is_required = TRUE;
		$input_list->add_date_input( $label, $name, $val, $hint, $classes, $is_required );

		$label = __( 'Start Time', 'reg-man-rc' );
		$name = 'event_start_time';
		$val = isset( $start_date_time ) ? $start_date_time->format( $time_input_format ) : Settings::get_default_event_start_time();
		$hint = '';
		$classes = '';
		$is_required = TRUE;
		$addn_attrs = 'step="1800"'; // 60 seconds * 30 minutes
		$input_list->add_time_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		$label = __( 'End Time', 'reg-man-rc' );
		$name = 'event_end_time';
		$val = isset( $end_date_time ) ? $end_date_time->format( $time_input_format ) : Settings::get_default_event_end_time();
		$hint = '';
		$classes = '';
		$is_required = TRUE;
		$addn_attrs = 'step="1800"'; // 60 seconds * 30 minutes
		$input_list->add_time_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

		$input_list->render();

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

		$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
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
		echo "<select class=\"combobox\" name=\"$input_name\" id=\"$input_id\" autocomplete=\"off\"  disabled=\"disabled\" >";

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

	/**
	 * Render the event items meta box
	 * @param \WP_Post $post
	 * @return	void
	 * @since	v0.1.0
	 */
/* FIXME - this was intended to show the items for the event but not sure it's useful / needed
	public static function render_event_items_metabox( $post ) {
		$event_descriptor = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
		if ( ! isset( $event_descriptor ) ) {
			// This is likely an auto-draft (just being created) so there's no date, no event key etc.
			// We should probably not even show the metabox in this case
			echo __( 'There is no registration data associated with this event', 'reg-man-rc' );
		} else {
			$events_array = $event_descriptor->get_event_object_array();
			foreach( $events_array as $event ) {
				$event_key = $event->get_key();
				$item_array = Item::get_items_registered_for_event( $event_key );
				var_dump( $item_array );
			} // endfor
		} // endif
	} // function
*/
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
			'title'				=> __( 'Summary', 'reg-man-rc' ),
			'event_status'		=> __( 'Status', 'reg-man-rc' ),
			'event_class'		=> __( 'Class', 'reg-man-rc' ),
			'event_date'		=> __( 'Date & Time', 'reg-man-rc' ),
			'is_recurring'		=> __( 'Repeats', 'reg-man-rc' ),
			$venue_key			=> __( 'Venue', 'reg-man-rc' ),
			$event_category_key	=> $category_heading,
//			$fixer_station_key	=> __( 'Fixer Stations', 'reg-man-rc' ),
			'fixer_stations'	=> __( 'Fixer Stations', 'reg-man-rc' ),
			'date'				=> __( 'Last Update', 'reg-man-rc' ),
			'author'			=> __( 'Author', 'reg-man-rc' ),
		);
		if ( ! Settings::get_is_allow_recurring_events() ) {
			unset( $result[ 'is_recurring' ] );
		} // endif
		if ( Settings::get_is_allow_event_comments() ) {
			$result[ 'comments' ]	= $columns[ 'comments' ];
		} // endif
		return $result;
	} // function

	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$result = $em_dash; // show em-dash by default
		$event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post_id );

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
					$result = isset( $class ) ? esc_html( $class->get_name() ) : $em_dash;
					break;

				case 'fixer_stations':
					$stations_array = $event_desc->get_event_fixer_station_array();
					$result = ! empty( $stations_array ) ? self::get_station_list( $stations_array ) : $em_dash;
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
					$icon_url = $station->get_icon_url();
					$station_text = $station->get_name();
					$name_attr = esc_attr( $station_text );
					$id = $station->get_id();
					if ( ! empty( $icon_url ) ) {
						echo '<li class="fixer-station-item fixer-station-icon" data-id="' . $id . '">';
							echo "<img src=\"$icon_url\" title=\"$name_attr\" alt=\"$name_attr\">";
						echo '</li>';
					} else {
						echo '<li class="fixer-station-item fixer-station-text" data-id="' . $id . '">';
							echo '<span class="reg-man-rc-custom-post-details-text">';
								echo $station_text;
							echo '</span>';
						echo '</li>';
					} // endif
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

} // class
