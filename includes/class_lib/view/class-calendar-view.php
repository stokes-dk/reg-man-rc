<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Control\Calendar_Controller;
use Reg_Man_RC\Model\Calendar_View_Format;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\View\Object_View\Details_Disclosure_Object_View_Template;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Event_Class;

/**
 * An instance of this class provides a user interfrace for a calendar that appears on the public-facing (frontend) side of the website.
 *
 * @since	v0.1.0
 *
 */
class Calendar_View {

	/** The shortcode used to render a calendar */
	const CALENDAR_SHORTCODE	= 'rc-calendar';

	/** The shortcode used to show the next event on a calendar */
	const NEXT_EVENT_SHORTCODE	= 'rc-next-event';

	/** The shortcode used to show a set of upcoming events on a calendar */
	const UPCOMING_EVENTS_SHORTCODE	= 'rc-upcoming-events';

	private $calendar; // The calendar object shown in this view
	private $view_format_ids_array; // an array of IDs for Calendar_View_Format objects defining which views to show
	private $duration_ids_array; // an array of IDs for Calendar_Duration objects defining which durations to show
	
	/** A private constructor forces users to use one of the factory methods */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class.
	 * @param	Calendar	$calendar	The calendar object shown in this view.
	 * @return	Calendar_View
	 * @since	v0.1.0
	 */
	public static function create( $calendar ) {
		$result = new self();
		$result->calendar = $calendar;
//		$result->calendar_id = $calendar->get_id();
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class specifically for the visitor registration calendar.
	 * @return	Calendar_View
	 * @since	v0.1.0
	 */
	public static function create_for_visitor_registration_calendar() {
		$result = new self();
		$result->calendar = Calendar::get_visitor_registration_calendar();
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class specifically for the volunteer registration calendar.
	 * @return	Calendar_View
	 * @since	v0.1.0
	 */
	public static function create_for_volunteer_registration_calendar() {
		$result = new self();
		$result->calendar = Calendar::get_volunteer_registration_calendar();
		return $result;
	} // function

	private function get_calendar() {
		return $this->calendar;
	} // function

	/**
	 * Set the calendar object for this view.
	 * @param	\Reg_Man_RC\Model\Calendar	$calendar	The calendar object to be shown in this view.
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_calendar( $calendar ) {
		if ( $calendar instanceof Calendar ) {
			$this->calendar = $calendar;
		} // endif
	} // function

	private function get_view_format_ids_array() {
		if ( isset( $this->view_format_ids_array ) ) {
			$result = $this->view_format_ids_array;
		} else {
			$calendar = $this->get_calendar();
			$result = ! empty( $calendar ) ? $calendar->get_view_format_ids_array() : Calendar::get_default_view_format_ids_array();
		} // endif
		return $result;
	} // function

	private function get_duration_ids_array() {
		if ( isset( $this->duration_ids_array ) ) {
			$result = $this->duration_ids_array;
		} else {
			$calendar = $this->get_calendar();
			$result = ! empty( $calendar ) ? $calendar->get_duration_ids_array() : Calendar::get_default_duration_ids_array();
		} // endif
		return $result;
	} // function
	
	private function get_week_start() {
		$result = get_option( 'start_of_week' ); // Use the setting for the site
		return $result;
	} // function

	private function get_language() {
		$locale = get_locale();
		$parts = explode( '_', $locale );
		$result = $parts[0];
		return $result;
	} // function

	private function get_fullcalendar_json_feed_url() {
		$result = esc_attr( Calendar_Controller::get_fullcalendar_json_events_feed_url() );;
		return $result;
	} // function

	/**
	 * Render the calendar view
	 * @return	void
	 * @since	v0.1.0
	 */
	public function render() {

		// I'll pass certain settings information into the calendar
		$calendar = $this->get_calendar();
		if ( ! isset( $calendar ) ) {
			return; // <== EXIT POINT!!! Defensive
		} // endif

		$calendar_id = $calendar->get_id();
		$start_of_week = esc_attr( $this->get_week_start() );
		$lang = esc_attr( $this->get_language() );
		$feed_url = esc_attr( $this->get_fullcalendar_json_feed_url() );

		$view_ids = $this->get_view_format_ids_array();
		$view_data = esc_attr( implode( ',' , $view_ids ) );

		$duration_ids = $this->get_duration_ids_array();
		$duration_data = esc_attr( implode( ',' , $duration_ids ) );

		$is_render_map = ( Map_View::get_is_map_view_enabled() && in_array( Calendar_View_Format::MAP_VIEW, $view_ids ) );

		$is_show_past_events = $calendar->get_is_show_past_events();
		$is_show_past_events_data = $is_show_past_events ? 'true' : 'false';

		$calendar_type = $calendar->get_calendar_type();
//		Error_Log::var_dump( $calendar_type, $calendar_id );

		$multi_month_min_width = $calendar->get_multi_month_min_width();
		
		// Data
		$data_array = array();
		$data_array[] = "data-calendar-type=\"$calendar_type\"";
		$data_array[] = "data-calendar-id=\"$calendar_id\"";
		$data_array[] = "data-views=\"$view_data\"";
		$data_array[] = "data-durations=\"$duration_data\"";
		$data_array[] = "data-week-start=\"$start_of_week\"";
		$data_array[] = "data-lang=\"$lang\"";
		$data_array[] = "data-feed-url=\"$feed_url\"";
		$data_array[] = "data-is-show-past-events=\"$is_show_past_events_data\"";
		$data_array[] = "data-multi-month-min-width=\"$multi_month_min_width\"";
		
		// Conditionally add buttons for authors ( All | Mine )
		if ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) && (
				$calendar_type == Calendar::CALENDAR_TYPE_ADMIN ||
				$calendar_type == Calendar::CALENDAR_TYPE_VOLUNTEER_REG ||
				$calendar_type == Calendar::CALENDAR_TYPE_VISITOR_REG
				) ) {

			$data_array[] = 'data-authors="author_all,author_mine"';
			if ( ! current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
				$data_array[] = 'data-default-author="author_mine"';
			} // endif
			
		} // endif
		
		// Note that including a nonce allows the REST API to determine the user
		// If no nonce is present in the request then WordPress will always execute the request with no user
		// If the calendar requires a nonce then we must supply it here, it will be verified in the request handler
		// If this user can read private events then we'll include a nonce so that those events will show up
		$can_read_private = current_user_can( 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL );
		if ( $calendar->get_is_nonce_required_for_fullcalendar_feed() || $can_read_private ) {
			$data_array[] = 'data-wp-nonce="' . wp_create_nonce( 'wp_rest' ) . '"';
		} // endif

		$data = implode( ' ', $data_array );

		// I will set up the container as a listener for ajax form events from my map's form which is in the footer
		echo '<div class="reg-man-rc-calendar-container reg-man-rc-ajax-form-listener">';

			// This is a busy spinner which is shown when the map is loading
			echo '<div class="reg-man-rc-calendar-loading-indicator"></div>';

			echo "<div class=\"reg-man-rc-calendar-view\" $data>";
			echo '</div>';

			if ( $is_render_map ) {
				$map_view = Map_View::create_for_calendar( $calendar );
				$map_ajax_form_id = $map_view->get_map_marker_ajax_form_id();
				$no_markers_message = __( 'No events for the selected timeframe', 'reg-man-rc'  );
				$map_view->set_no_markers_message( $no_markers_message );
				$map_view->set_show_missing_location_message( TRUE );
				$data = "data-map-marker-ajax-form-id=\"$map_ajax_form_id\"";
				echo "<div class=\"reg-man-rc-calendar-map-container\" $data>";
					$map_view->render();
				echo '</div>';
			} // endif
			
			echo "<div class=\"reg-man-rc-calendar-footer\">";
				$this->render_legend();
				if ( $calendar->get_icalendar_is_show_subscribe_button() ) {
					$this->render_subscribe();
				} // endif
			echo '</div>';
				
		echo '</div>';
		
	} // function

	private function render_legend() {
		// Make sure the legend is hidden until the calendar is drawn
		echo '<div class="reg-man-rc-calendar-legend" style="display:none">';
			$title = esc_html( __( 'Legend', 'reg-man-rc' ) );
			echo "<h4 class=\"reg-man-rc-calendar-legend-title\">$title</h4>";
			$calendar = $this->get_calendar();
			$categories = $calendar->get_event_category_array();

			echo '<ul class="reg-man-rc-calendar-legend-list">';
				$item_format =
					'<li class="reg-man-rc-calendar-legend-item %4$s">' .
						'<div class="legend-item-container">' .
							'<div class="legend-item-part legend-item-dot" style="background:%3$s"></div>' .
							'<div class="legend-item-part legend-item-title" title="%2$s">%1$s</div>' .
						'</div>';
					'</li>';

				$default_colour = Event_Category::DEFAULT_COLOUR;

				$calendar_type = $calendar->get_calendar_type();

				// Additional items for special calendars
				if ( $calendar_type === Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
					$name = esc_html__( 'Registered to attend', 'reg-man-rc' );
					$desc = esc_attr__( 'An event you are registered to attend', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'vol-reg-registered';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif

				// Usual category items
				foreach( $categories as $category ) {
					$name = esc_html( $category->get_name() );
					$desc = esc_attr( $category->get_description() );
					$colour = $category->get_colour();
					$classes = '';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endfor

				/* Translators: %1$s is a status marker text like "TENTATIVE", %2$s is an event summary */
				$label_with_marker_format = _x( '%1$s %2$s', 'A calendar entry title for an event with its status, e.g. TENTATIVE Reference Library Repair Cafe', 'reg-man-rc' );
				
				if ( $calendar->get_is_show_past_events() ) {
					// Past events
					$name = esc_html__( 'Past event', 'reg-man-rc' );
					$desc = esc_attr__( 'An event in the past', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'completed';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif

				if ( in_array( Event_Status::TENTATIVE, $calendar->get_event_status_array() ) ) {
					// Tentative events
					$name = esc_html__( 'Tentative event', 'reg-man-rc' );
/* Reduce clutter and don't show the marker text
					$tentative_status = Event_Status::get_event_status_by_id( Event_Status::TENTATIVE );
					$marker_text = $tentative_status->get_event_marker_text();
					if ( ! empty( $marker_text ) ) {
						$name = sprintf( $label_with_marker_format, $marker_text, $name );
					} // endif
*/
					$desc = esc_attr__( 'A tentatively scheduled event', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'tentative';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif

				if ( in_array( Event_Class::PRIVATE, $calendar->get_event_class_array() ) ) {
					// Private events
					if ( current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ||
						 current_user_can( 'read_private' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
						// There will only be private events if there's a user who can read them
						$name = esc_html__( 'Draft or privately published event', 'reg-man-rc' );
/* Reduce clutter and don't show the marker text
						$private_class = Event_Class::get_event_class_by_id( Event_Class::PRIVATE );
						$marker_text = $private_class->get_event_marker_text();
						if ( ! empty( $marker_text ) ) {
							$name = sprintf( $label_with_marker_format, $marker_text, $name );
						} // endif
*/
						$desc = esc_attr__( 'A draft or privately published event not visible to the public', 'reg-man-rc' );
						$colour = $default_colour;
						$classes = 'private-class';
						printf( $item_format, $name, $desc, $colour, $classes );
					} // endif
				} // endif
				
				if ( in_array( Event_Status::CANCELLED, $calendar->get_event_status_array() ) ) {
					// Cancelled events
					$name = esc_html__( 'Cancelled event', 'reg-man-rc' );
/* Reduce clutter and don't show the marker text
					$cancelled_status = Event_Status::get_event_status_by_id( Event_Status::CANCELLED );
					$marker_text = $cancelled_status->get_event_marker_text();
					if ( ! empty( $marker_text ) ) {
						$name = sprintf( $label_with_marker_format, $marker_text, $name );
					} // endif
*/
					$time_text = _x( 'Time', 'A placehold for an event time used in the calendar legend', 'reg-man-rc' );
					$name = "<span class=\"event-time\">$time_text</span>$name";
					$desc = esc_attr__( 'An event that has been cancelled', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'cancelled';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif
				
				// More additional items for special calendars
				if ( $calendar_type === Calendar::CALENDAR_TYPE_ADMIN ) {
					$name = esc_html__( 'Placeholder for orphaned registrations', 'reg-man-rc' );
					$desc = esc_attr__( 'Items or volunteers are registered for an event that cannot be found', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'event-placeholder';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif
				
				if ( $calendar_type === Calendar::CALENDAR_TYPE_EVENT_DESCRIPTOR ) {
					$name = esc_html__( 'Registered items and/or volunteers', 'reg-man-rc' );
					$desc = esc_attr__( 'Items and/or volunteers are registered for this event', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'event-with-registrations';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif
				
			echo '</ul>';
			
		echo '</div>';
	} // function

	private function render_subscribe() {
		$calendar = $this->get_calendar();
		$feed_name = $calendar->get_icalendar_feed_name();
		if ( ! empty( $feed_name ) ) {
			echo '<div class="reg-man-rc-calendar-subscribe-container">';
				$button = Calendar_Subscribe_Button::create_for_calendar( $calendar );
				$button->render();
			echo '</div>';
		} // function
	} // function

	/**
	 * Perform the necessary steps to register this view with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function register() {

		// add my scripts and styles correctly for front end
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		if ( ! is_admin() ) {
			// create my shortcode for a calendar
			add_shortcode( self::CALENDAR_SHORTCODE, array( __CLASS__, 'get_calendar_shortcode_content' ) );

			// create my shortcode to show the next event
			add_shortcode( self::NEXT_EVENT_SHORTCODE, array( __CLASS__, 'get_next_event_shortcode_content' ) );

			// create my shortcode to show the upcoming events
			add_shortcode( self::UPCOMING_EVENTS_SHORTCODE, array( __CLASS__, 'get_upcoming_events_shortcode_content' ) );

			// Add a filter to change the content written for this type
			add_filter( 'the_content', array(__CLASS__, 'modify_post_content') );

		} // endif

	} // function

	/**
	 * Modify the contents shown for posts of this type
	 */
	public static function modify_post_content( $content ) {
		if ( is_single() && in_the_loop() && is_main_query() ) {
			global $post;
			if ( $post->post_type == Calendar::POST_TYPE ) {
				$calendar = Calendar::get_calendar_by_post_id( $post->ID );
				if ( $calendar !== NULL ) {
					// Register the scripts and styles I need
					\Reg_Man_RC\Control\Scripts_And_Styles::enqueue_fullcalendar();

					ob_start();
						echo '<div class="reg-man-rc-calendar-post-content">';
							$view = self::create( $calendar );
							$view->render();
						echo '</div>';
					$details = ob_get_clean();
					$content .= $details;
				} // endif
			} // endif
		} // endif
		return $content;
	} // function

	/**
	 * Get the content for the calendar shortcode
	 *
	 * This method is called automatically when the shortcode is on the current page.
	 *
	 * @return	string	The contents of the Calendar shortcode.  Wordpress will insert this into the page.
	 * @since	v0.1.0
	 */
	public static function get_calendar_shortcode_content( $attributes ) {

		// If the shortcode is used in a widget then my scripts and styles may not be enqueued, so make sure here
		Scripts_And_Styles::enqueue_fullcalendar();
		
		$attribute_values = shortcode_atts( array(
			'calendar'	=> NULL,
		), $attributes );

		$error_array = array();
		if ( ! isset( $attribute_values[ 'calendar' ] ) ) {
			$error_array[] = __( 'Please specify a calendar ID or slug in the shortcode.', 'reg-man-rc' );
		} else {
			$calendar_id = $attribute_values[ 'calendar' ];
			$calendar = Calendar::get_calendar_by_post_id( $calendar_id );
			if ( ! isset( $calendar ) ) {
				// Try finding the calendar by its slug
				$calendar = Calendar::get_calendar_by_slug( $calendar_id );
			} // endif
			if ( isset( $calendar ) ) {
				$view = self::create( $calendar ); // create the view to show the calendar
			} else {
				/* translators: %1$s is an invalid event status name specified in the calendar shortcode */
				$msg = __( 'Could not find the calendar specified in the shortcode: "%1$s"', 'reg-man-rc' );
				$error_array[] = sprintf( $msg, $calendar_id );
			} // endif
		} // endif

		ob_start();
			// If there are any errors, show them to the author
			if ( ! empty( $error_array ) ) {
				global $post, $current_user;
				// If there is an error in the shortcode and current user is the author then I will show them their errors
				if ( is_user_logged_in() && $current_user->ID == $post->post_author )  {
					foreach( $error_array as $error ) {
						echo '<div class="reg-man-rc shortcode-error">' . $error . '</div>';
					} // endfor
				} // endif
			} // endif

			if ( isset( $view ) ) {
				$view->render();
			} // endif

		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Get the content for the next event shortcode
	 *
	 * This method is called automatically when the shortcode is on the current page.
	 *
	 * @return	string	The contents of the next event shortcode.  Wordpress will insert this into the page.
	 * @since	v0.1.0
	 */
	public static function get_next_event_shortcode_content( $attributes ) {

		// If the shortcode is used in a widget then my scripts and styles may not be enqueued, so make sure here
		Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
		
		$attribute_values = shortcode_atts( array(
			'calendar'			=> NULL,
			'title'				=> __( 'Next event', 'reg-man-rc' ),
			'no-event-title'	=> __( 'No upcoming events scheduled', 'reg-man-rc' ),
		), $attributes );

		$error_array = array();
		if ( ! isset( $attribute_values[ 'calendar' ] ) ) {
			$error_array[] = __( 'Please specify a calendar ID in the shortcode.', 'reg-man-rc' );
		} else {
			$calendar_id = $attribute_values[ 'calendar' ];
			$calendar = Calendar::get_calendar_by_post_id( $calendar_id );
			if ( ! isset( $calendar ) ) {
				// Try finding the calendar by its slug
				$calendar = Calendar::get_calendar_by_slug( $calendar_id );
			} // endif
			if ( ! isset( $calendar ) ) {
				$calendar = NULL;
				/* translators: %1$s is an invalid event status name specified in the calendar shortcode */
				$msg = __( 'Could not find the calendar specified in the shortcode: "%1$s"', 'reg-man-rc' );
				$error_array[] = sprintf( $msg, $calendar_id );
			} // endif
		} // endif

		ob_start();
			// If there are any errors, show them to the author
			if ( ! empty( $error_array ) ) {
				global $post, $current_user;
				// If there is an error in the shortcode and current user is the author then I will show them their errors
				if ( is_user_logged_in() && $current_user->ID == $post->post_author )  {
					foreach( $error_array as $error ) {
						echo '<div class="reg-man-rc shortcode-error">' . $error . '</div>';
					} // endfor
				} // endif
			} // endif

			if ( isset( $calendar ) ) {
				$events_array = $calendar->get_upcoming_calendar_events( $count = 1, $is_exclude_cancelled = TRUE );
				$event = isset( $events_array[ 0 ] ) ? $events_array[ 0 ] : NULL;
				echo '<div class="reg-man-rc-shortcode-container next-event">';
					if ( ! isset( $event ) ) {
						$title = $attribute_values[ 'no-event-title' ];
						echo $title;
					} else {
						$title = $attribute_values[ 'title' ];
						/* Translators: %1$s is a fixed title like "Next event", %2$s is an event description */
						$text_format = _x( '%1$s: %2$s',
								'A format for a title and an event description like "Next event: Wed Sept 21 @ Central Library"',
								'reg-man-rc' );
						$event_title = $event->get_label();
						$url = $event->get_event_page_url();
						echo "<a class=\"reg-man-rc-next-event-shortcode-link\" href=\"$url\">";
							printf( $text_format, $title, $event_title );
						echo '</a>';
					} // endif
				echo '</div>';
			} // endif

		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Get the content for the upcoming events shortcode
	 *
	 * This method is called automatically when the shortcode is on the current page.
	 *
	 * @return	string	The contents of the next event shortcode.  Wordpress will insert this into the page.
	 * @since	v0.1.0
	 */
	public static function get_upcoming_events_shortcode_content( $attributes ) {

		// If the shortcode is used in a widget then my scripts and styles may not be enqueued, so make sure here
		Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
		
		$default_upcoming_events_count = 3; // Used when the count is not specified or not valid
		
		$attribute_values = shortcode_atts( array(
			'calendar'			=> NULL,
			'count'				=> $default_upcoming_events_count,
		), $attributes );

		$error_array = array();
		if ( ! isset( $attribute_values[ 'calendar' ] ) ) {
			$error_array[] = __( 'Please specify a calendar ID in the shortcode.', 'reg-man-rc' );
		} else {
			$calendar_id = $attribute_values[ 'calendar' ];
			$calendar = Calendar::get_calendar_by_post_id( $calendar_id );
			if ( ! isset( $calendar ) ) {
				// Try finding the calendar by its slug
				$calendar = Calendar::get_calendar_by_slug( $calendar_id );
			} // endif
			if ( ! isset( $calendar ) ) {
				$calendar = NULL;
				/* translators: %1$s is an invalid event status name specified in the calendar shortcode */
				$msg = __( 'Could not find the calendar specified in the shortcode: "%1$s"', 'reg-man-rc' );
				$error_array[] = sprintf( $msg, $calendar_id );
			} // endif
		} // endif
		
		$count = $attribute_values[ 'count' ];
		if ( intval( $count ) != $count ) {
			/* translators: %1$s is an invalid event count specified in the calendar shortcode, %2$s is the default count */
			$msg = __( 'The specified count "%1$s" is not a valid number.  Using default count of %2$s.', 'reg-man-rc' );
			$error_array[] = sprintf( $msg, $count, $default_upcoming_events_count );
			$count = $default_upcoming_events_count;
		} // endif
		
		ob_start();
			// If there are any errors, show them to the author
			if ( ! empty( $error_array ) ) {
				global $post, $current_user;
				// If there is an error in the shortcode and current user is the author then I will show them their errors
				if ( is_user_logged_in() && $current_user->ID == $post->post_author )  {
					foreach( $error_array as $error ) {
						echo '<div class="reg-man-rc shortcode-error">' . $error . '</div>';
					} // endfor
				} // endif
			} // endif

			if ( isset( $calendar ) ) {
				$events_array = $calendar->get_upcoming_calendar_events( $count );
				
				// We may have two events on the same day so we need to group events by their date
				$event_dates_array = array(); // create an array of dates
				foreach( $events_array as $event ) {
					$date_time = $event->get_start_date_time_object();
					$date = $date_time->format( 'Ymd' );
					if ( ! isset( $event_dates_array[ $date ] ) ) {
						$event_dates_array[ $date ] = array( $event );
					} else {
						$event_dates_array[ $date ][] = $event;
					} // endfor
				} // endfor

				$day_label_format =
					'<div class="reg-man-rc-upcoming-events-day-label-container" title="%4$s">' .
						'<div class="month">%1$s</div>' .
						'<div class="day-of-month">%2$s</div>' .
						'<div class="day-of-week">%3$s</div>' .
					'</div>';
				
				echo '<div class="reg-man-rc-shortcode-container upcoming-events">';
				$wp_date_format = get_option( 'date_format' );
				
				foreach( $event_dates_array as $date => $curr_date_events_array ) {
					echo '<div class="reg-man-rc-upcoming-events-date-container">';

						$curr_date = new \DateTime( $date );
						$day_of_week = $curr_date->format( 'l' ); // l => Full name, D => 3 letters
						$day_of_month = $curr_date->format( 'j' );
						$month = $curr_date->format( 'F' ); // F => Full name, M => 3 letters
						$full_date = $curr_date->format( $wp_date_format );
						printf( $day_label_format, $month, $day_of_month, $day_of_week, $full_date );
						
						echo '<div class="reg-man-rc-upcoming-events-event-group-container">';

							$template = Details_Disclosure_Object_View_Template::create();
							$classes = 'reg-man-rc-upcoming-events-event-container reg-man-rc-info-window-container';
							$template->set_classes( $classes );
							foreach( $curr_date_events_array as $event ) {
								$event_view = Event_View::create_for_calendar_agenda_entry( $event );
								$template->set_object_view( $event_view );
								$content = $template->get_content();
								echo $content;
							} // endfor
						echo '</div>'; // Event group container

					echo '</div>'; // Date container
				} // endfor
				echo '</div>';
			} // endif

		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface on the frontend if the shortcode is present
	 *
	 * This method is called automatically when scripts are enqueued.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		global $post;
		if ( $post instanceof \WP_Post ) {
			if ( has_shortcode( $post->post_content, self::CALENDAR_SHORTCODE ) ) {
				Scripts_And_Styles::enqueue_fullcalendar();
			} // endif
			if ( has_shortcode( $post->post_content, self::NEXT_EVENT_SHORTCODE ) ) {
				Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
			} // endif
			if ( has_shortcode( $post->post_content, self::UPCOMING_EVENTS_SHORTCODE ) ) {
				Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
			} // endif
		} // endif
	} // function

} // class