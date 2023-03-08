<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Control\Calendar_Controller;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Control\Map_Controller;
use Reg_Man_RC\Model\Calendar_View_Format;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Cookie;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Calendar_Entry;
use Reg_Man_RC\Model\Event_Status;

/**
 * An instance of this class provides a user interfrace for a calendar that appears on the public-facing (frontend) side of the website.
 *
 * @since	v0.1.0
 *
 */
class Calendar_View {

	/** The shortcode used to render a calendar */
	const CALENDAR_SHORTCODE	= 'rc-calendar';

	/** The shortcode used to render a calendar */
	const NEXT_EVENT_SHORTCODE	= 'rc-next-event';

	private $calendar; // The calendar object shown in this view
	private $view_format_ids_array; // an array of IDs for Calendar_View_Format objects defining which views to show

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
		$result->calendar_id = $calendar->get_id();
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
			$result = ( ! empty( $calendar ) ) ? $calendar->get_view_format_ids_array() : Calendar::get_default_view_format_ids_array();
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

	private function get_feed_url() {
		$result = esc_attr( Calendar_Controller::get_json_events_feed_url() );;
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
		$feed_url = esc_attr( $this->get_feed_url() );

		$view_ids = $this->get_view_format_ids_array();
		$full_calendar_names_array = array();
		foreach( $view_ids as $view_format_id ) {
			$view_format = Calendar_View_Format::get_view_format_by_id( $view_format_id );
			if ( isset( $view_format ) ) {
				$full_calendar_names_array[] = $view_format->get_full_calendar_name();
			} // endif
		} // endfor
		// Note that a space after the comma will cause the buttons to be separated, no space groups them
		$view_data = esc_attr( implode( ',' , $full_calendar_names_array ) );

		// If the user has been to this page then their last view will be saved in a cookie
		$cookie_view = Cookie::get_cookie( 'reg-man-rc-calendar-view' );
		if ( isset( $cookie_view ) && in_array( $cookie_view, $full_calendar_names_array ) ) {
			$initial_view = $cookie_view;
		} else {
			$initial_view = isset( $full_calendar_names_array[ 0 ] ) ? $full_calendar_names_array[ 0 ] : NULL;
		} // endif

		$is_render_map = ( Map_View::get_is_map_view_enabled() && in_array( Calendar_View_Format::MAP_VIEW, $view_ids ) );

		$is_show_past_events = $calendar->get_is_show_past_events();
		$is_show_past_events_data = $is_show_past_events ? 'true' : 'false';

		// Data
		$data_array = array();
		$data_array[] = "data-calendar-id=\"$calendar_id\"";
		$data_array[] = "data-views=\"$view_data\"";
		$data_array[] = "data-week-start=\"$start_of_week\"";
		$data_array[] = "data-lang=\"$lang\"";
		$data_array[] = "data-feed-url=\"$feed_url\"";
		$data_array[] = "data-is-show-past-events=\"$is_show_past_events_data\"";
		$data_array[] = 'data-wp-nonce="' . wp_create_nonce( 'wp_rest' ) . '"';
		if ( isset( $initial_view ) ) {
			$data_array[] = "data-initial-view=\"$initial_view\"";
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
			$this->render_legend();
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

				if ( $calendar->get_is_show_past_events() ) {
					// Past events
					$name = esc_html__( 'Past event', 'reg-man-rc' );
					$desc = esc_attr__( 'An event in the past', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'completed';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif

				if ( in_array( Event_Status::CANCELLED, $calendar->get_default_status_array() ) ) {
					// Cancelled events
					$name = esc_html__( 'Cancelled event', 'reg-man-rc' );
					$desc = esc_attr__( 'An event that has been cancelled', 'reg-man-rc' );
					$colour = $default_colour;
					$classes = 'cancelled';
					printf( $item_format, $name, $desc, $colour, $classes );
				} // endif

			echo '</ul>';
		echo '</div>';
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
				$calendar = Calendar::get_calendar_by_id( $post->ID );
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

		$attribute_values = shortcode_atts( array(
			'calendar'	=> NULL,
		), $attributes );

		$error_array = array();
		if ( ! isset( $attribute_values[ 'calendar' ] ) ) {
			$error_array[] = __( 'Please specify a calendar ID or slug in the shortcode.', 'reg-man-rc' );
		} else {
			$calendar_id = $attribute_values[ 'calendar' ];
			$calendar = Calendar::get_calendar_by_id( $calendar_id );
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

		$attribute_values = shortcode_atts( array(
			'calendar'			=> NULL,
			'title'				=> __( 'Next event', 'reg-man-rc' ),
			'no-event-title'	=> '',
		), $attributes );

		$error_array = array();
		if ( ! isset( $attribute_values[ 'calendar' ] ) ) {
			$error_array[] = __( 'Please specify a calendar ID in the shortcode.', 'reg-man-rc' );
		} else {
			$calendar_id = $attribute_values[ 'calendar' ];
			$calendar = Calendar::get_calendar_by_id( $calendar_id );
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
				$event = $calendar->get_next_calendar_event();
				echo '<wp-block-button class="reg-man-rc-next-event-shortcode-button">';
					if ( ! isset( $event ) ) {
						$title = $attribute_values[ 'no-event-title' ];
						echo $title;
					} else {
						$title = $attribute_values[ 'title' ];
						/* Translators: %1$s is a fixed title like "Next event", %2$s is an event description */
						$text_format = _x( '%1$s: %2$s',
								'A format to a title and an event description like "Next event: Wed Sept 21 @ Central Library"',
								'reg-man-rc' );
						$event_title = $event->get_label();
						$url = $event->get_event_page_url();
						echo "<a class=\"wp-block-button__link\" href=\"$url\">";
							printf( $text_format, $title, $event_title );
						echo '</a>';
					} // endif
				echo '</wp-block-button>';
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
		} // endif
	} // function

} // class