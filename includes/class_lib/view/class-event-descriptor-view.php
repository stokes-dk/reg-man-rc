<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Event_Descriptor;
use Reg_Man_RC\Model\Event_Status;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\View\Object_View\Map_Section;
use Reg_Man_RC\View\Object_View\List_Section;
use Reg_Man_RC\View\Object_View\Abstract_Object_View;
use Reg_Man_RC\View\Object_View\Event_Descriptor_Item_Provider;
use Reg_Man_RC\View\Object_View\List_Item;
use Reg_Man_RC\View\Object_View\Object_View;

/**
 * An instance of this class provides rendering for an Internal_Event_Descriptor object.
 * For consistency, it also handles the rendering for Event objects (delegated by Event_View).
 *
 * @since	v0.1.0
 *
 */
class Event_Descriptor_View extends Abstract_Object_View {

	const DEFAULT_PAGE_SLUG		= 'rc-events';

	private $event_descriptor;
	private $item_provider;

//	private $range_min_date_time; // When showing event dates this will be the earliest date shown
//	private $range_max_date_time; // When showing event dates this will be the latest date shown

	/**
	 * A protected constructor forces users to use one of the factory methods
	 */
	private function __construct() {
	} // constructor

	/**
	 * A factory method to create an instance of this class to display the page content for an event descriptor.
	 * @param	Event_Descriptor	$event_descriptor		The event descriptor object shown in this view.
	 * @return	Event_Descriptor_View		An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create_for_page_content( $event_descriptor ) {
		$result = new self();
		$result->event_descriptor = $event_descriptor;
		$result->set_object_page_type( Object_View::OBJECT_PAGE_TYPE_EVENT_DESCRIPTOR );
		return $result;
	} // function


	/**
	 * A factory method to create an instance of this class to display an event descriptor.
	 * @param	Event_Descriptor	$event_descriptor	The event descriptor object shown in this view.
	 * @param	string				$map_type			The type of map being shown in this view
	 * @return	Event_Descriptor_View		An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create_for_map_info_window( $event_descriptor, $map_type ) {
		$result = new self();
		$result->event_descriptor = $event_descriptor;
		$result->set_info_window_map_type( $map_type );
		return $result;
	} // function

	/**
	 * Get the event descriptor for this view
	 * @return Event_Descriptor
	 */
	private function get_event_descriptor() {
		return $this->event_descriptor;
	} // funciton

	/**
	 * Get the event descriptor item provider for this view
	 * @return Event_Descriptor_Item_Provider
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {
			$this->item_provider = Event_Descriptor_Item_Provider::create( $this->get_event_descriptor(), $this );
		} // endif
		return $this->item_provider;
	} // funciton

	/**
	 * Get the section (if any) to be rendered after the title
	 * @return Object_View_Section	The section to be displayed after the title
	 */
	public function get_object_view_after_title_section() {
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_after_title_item_names_array();
		$result = List_Section::create( $item_provider, $item_names );
		return $result;
	} // function

	/**
	 * Get the array of main content sections.
	 * @return Object_View_Section[]
	 */
	public function get_object_view_main_content_sections_array() {
		$result = array();
		$event_descriptor = $this->get_event_descriptor();

		if ( $this->get_is_object_page() ) {
			// Map
			$result[] = Map_Section::create( $event_descriptor );
		} // endif

		// Details section
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_details_item_names_array();
		$result[] = List_Section::create( $item_provider, $item_names );

		return $result;

	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_after_title_item_names_array() {
		$result = array(
				List_Item::EVENT_STATUS,
				List_Item::EVENT_VISIBILITY,
		);
		return $result;
	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_details_item_names_array() {

		if ( $this->get_is_object_page() ) {
			// We are rendering the main page content
			$result = array(
					List_Item::EVENT_CATEGORIES,
					List_Item::EVENT_UPCOMING_DATES,
					List_Item::LOCATION_NAME,
					List_Item::LOCATION_ADDRESS,
					List_Item::GET_DIRECTIONS_LINK,
					List_Item::EVENT_FIXER_STATIONS,
					List_Item::EVENT_DESCRIPTION,
					List_Item::VENUE_DESCRIPTION,
			);
		} elseif( $this->get_is_map_info_window() ) {
			// We are rendering the info window for a map
			$result = $this->get_details_item_names_array_for_map();

		} else {
			// We are rendering the info window on a calendar
			$result = array(); // you can't show an event descriptor on a calendar - there's no date!

		} // endif
		return $result;
	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_details_item_names_array_for_map() {
		$map_type = $this->get_info_window_map_type();
		switch( $map_type ) {

			case Map_View::MAP_TYPE_OBJECT_PAGE:
				// We're showing an info window on the object's page
				$result = array(
						List_Item::EVENT_CATEGORIES,
						List_Item::LOCATION_NAME,
						List_Item::LOCATION_ADDRESS,
						List_Item::GET_DIRECTIONS_LINK,
				);
				break;

			default:
				$result = array(
						List_Item::EVENT_CATEGORIES,
						List_Item::EVENT_UPCOMING_DATES,
						List_Item::LOCATION_NAME,
						List_Item::LOCATION_ADDRESS,
						List_Item::GET_DIRECTIONS_LINK,
						List_Item::EVENT_FIXER_STATIONS,
						List_Item::MORE_DETAILS_LINK,
				);
				break;

		} // endswitch

		return $result;

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

		if ( ! is_admin() ) {
			// conditionally add my scripts and styles correctly for front end
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

			// Add a filter to change the title for this type
			add_filter( 'the_title', array(__CLASS__, 'modify_post_title'), 10, 2 );

			// Add a filter to change the content written for this type
			add_filter( 'the_content', array(__CLASS__, 'modify_post_content') );

			// Add a filter to change the excerpt written for this type
			add_filter( 'the_excerpt', array(__CLASS__, 'modify_post_excerpt') );

			// Filter the previous / next links
			add_filter( 'previous_post_link', array( __CLASS__, 'filter_adjacent_link' ), 10, 5 );
			add_filter( 'next_post_link',  array( __CLASS__, 'filter_adjacent_link' ), 10, 5 );

		} // endif

	} // function

	/**
	 * Filters the adjacent post link.
	 *
	 * @since 0.0.1
	 *
	 * @param string	$output		The adjacent post link.
	 * @param string	$format		Link anchor format.
	 * @param string	$link		Link permalink format.
	 * @param \WP_Post	$post		The adjacent post.
	 * @param string	$adjacent	Whether the post is previous or next.
	 */
	public static function filter_adjacent_link( $output, $format, $link, $post, $adjacent ) {
		// We need to remove adjacent post links because there may be internal and external events.
		// Trying to figure out the next/previous event is hard to do.
//		Error_Log::var_dump( $output, $format, $link, $post );
		if ( self::get_is_internal_event_page() ) {
			$result = FALSE;
		} else {
			$result = $output;
		} // endif
		return $result;
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface on the frontend if we're on the right page
	 *
	 * This method is called automatically when scripts are enqueued.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		if ( self::get_is_internal_event_page() ) {
			// Register the scripts and styles I need
			\Reg_Man_RC\Control\Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
			\Reg_Man_RC\Control\Scripts_And_Styles::enqueue_google_maps();
		} // endif
	} // function

	/**
	 * Modify the title for posts of this type so that it includes the status for unconfirmed events,
	 *  e.g. "(Tentative) Event Title"
	 * @param	string	$title	The post title retrieved from the database
	 * @return	string	The post content modified for my custom post type
	 * @since	v0.1.0
	 */
	public static function modify_post_title( $title, $id ) {
		global $post;
		$result = $title; // return the original title by default
		if ( self::get_is_internal_event_page() && is_main_query() && in_the_loop() ) {
			if ( post_password_required( $post ) ) {
				// TODO: What if it is password protected?
			} else {
				$event_descriptor = self::get_internal_event_descriptor_for_post( $post );
				if ( $event_descriptor !== NULL ) {
					$status = $event_descriptor->get_event_status();
					$status_id = ! empty( $status ) ? $status->get_id() : NULL;
					if ( $status_id !== Event_Status::CONFIRMED ) {
						$status_name = $status->get_name();
						/* translators: %1$s is the status of an event, %2$s is the title of an event. */
						$format = _x( '(%1$s) %2$s', 'A title for an event that includes its status like "(Tentative) Repair Cafe"', 'reg-man-rc' );
						$result = sprintf( $format, $status_name, $title );
					} // endif
				} // endif
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Modify the contents shown for posts of this type so that I can format it and show the right details
	 * @param	string	$content	The post content retrieved from the database
	 * @return	string	The post content modified for my custom post type
	 * @since	v0.1.0
	 */
	public static function modify_post_content( $content ) {
		global $post, $wp_query;
		$result = $content; // return the original content by default
		if ( self::get_is_internal_event_page() ) {
			if ( post_password_required( $post ) ) {
				// TODO: What if it is password protected?
			} else {
				$event_descriptor = self::get_internal_event_descriptor_for_post( $post );
				if ( ! $event_descriptor->get_event_is_recurring() ) {
					// For non-repeating events I will show the content for the first (and only) event
					$event = self::get_first_event_for_descriptor( $event_descriptor );
				} else {
					// For repeating events I will attempt to get the recurrence event out of the page request
					// If a recurrence ID is found then I'll show the content for that single event (one date)
					// If no recurrence ID is specified then I'll show the content for the whole descriptor (all dates)
					$rcr_id = get_query_var( Event_Key::RECUR_ID_QUERY_ARG_NAME, FALSE );
//					Error_Log::var_dump( $rcr_id );
					if ( ! empty( $rcr_id ) ) {
						$evt_id = $event_descriptor->get_event_descriptor_id();
						$prv_id = $event_descriptor->get_provider_id();
						$event_key = Event_Key::create( $evt_id, $prv_id, $rcr_id );
						$event = Event::get_event_by_key( $event_key );
					} else {
						$event = NULL;
					} // endif
				} // endif
				if ( isset( $event ) ) {
					// If we have an event then show its details
					$view = Event_View::create_for_page_content( $event );
					$result = $view->get_object_view_content();
				} else {
					// Otherwise show the details for the event descriptor
					$view = Event_Descriptor_View::create_for_page_content( $event_descriptor );
					$result = $view->get_object_view_content();
				} // endif
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Modify the excerpt shown for posts of this type
	 * @param	string	$excerpt	The post excerpt determined by Wordpress
	 * @return	string	The post excerpt modified for my custom post type
	 * @since	v0.1.0
	 */
	public static function modify_post_excerpt( $excerpt ) {
		global $post;
		$result = $excerpt; // return the original content by default

		// TODO: Do I need to check if I'm on archive page, search page etc?

		if ( post_password_required( $post ) ) {
			// TODO: What if it is password protected?
		} else {
			$event_descriptor = self::get_internal_event_descriptor_for_post( $post );
			if ( isset( $event_descriptor ) ) {

				// For the excerpt the view is always the event descriptor
				$view = self::create( $event_descriptor );

			} // endif

			if ( isset( $view ) ) {
				$result = $view->get_event_descriptor_excerpt();
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get the internal event descriptor object for the specified post
	 * @param \WP_Post	$post
	 * @return NULL|Internal_Event_Descriptor
	 */
	private static function get_internal_event_descriptor_for_post( $post ) {
		if ( ! isset( $post ) ) {
			$result = NULL;
		} else {
			$post_type = $post->post_type;
			if ( $post_type == Internal_Event_Descriptor::POST_TYPE ) {
				$result = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
			} else {
				$result = NULL;
			} // endif
		} // endif
		return $result;
	} // function


	/**
	 * Get a boolean flag indicating whether the current page is for an internal event descriptor
	 * @result	boolean	A flag set to TRUE if the current page is single post page showing an internal event descriptor
	 */
	private static function get_is_internal_event_page() {
		$internal_events_post_type = Internal_Event_Descriptor::POST_TYPE;
		if ( is_singular( $internal_events_post_type ) ) {
			global $wp_query, $wp;
			$internal_query_var = get_query_var( $internal_events_post_type, FALSE );
			$result = ( $internal_query_var !== FALSE );
		} else {
			$result = FALSE;
		} // endif
		return $result;
	} // function

	/**
	 * Get the first event object in the series of events for this descriptor.
	 * @return	Event		The event object shown in this view.
	 * @since	v0.1.0
	 */
	private function get_first_event() {
		if ( ! isset( $this->first_event ) ) {
			$event_descriptor = $this->get_event_descriptor();
			$this->first_event = self::get_first_event_for_descriptor( $event_descriptor );
		} // endif
		return $this->first_event;
	} // function

	// I also need to do this in the class context
	private static function get_first_event_for_descriptor( $event_descriptor ) {
		$event_array = Event::get_events_array_for_event_descriptor( $event_descriptor );
		$event_array = array_values( $event_array ); // The original array is keyed by event key
		$result = isset( $event_array[ 0 ] ) ? $event_array[ 0 ] : NULL;
		return $result;
	} // function

	/**
	 * Render the event descriptor view excerpt
	 * @return	void
	 * @since	v0.1.0
	 */
	private function get_event_descriptor_excerpt() {
		ob_start();

			// Note that the page will have displayed the title at this point

			$event_descriptor = $this->get_event_descriptor();

			if ( ! $event_descriptor->get_event_is_recurring() ) {
				$start_date = $event_descriptor->get_event_start_date_time();
				$end_date = $event_descriptor->get_event_end_date_time();
				$date_label = Event::create_label_for_event_dates_and_times( $start_date, $end_date );
				echo '<p>' . $date_label . '</p>';
			} else {
				$event = $this->get_first_event();
				if ( isset( $event ) ) {
					$start_date = $event->get_start_date_time_local_timezone_object();
					$end_date = $event->get_end_date_time_local_timezone_object();
					$date_label = Event::create_label_for_event_dates_and_times( $start_date, $end_date );
				} // endif

				if ( isset( $date_label ) ) {
					if ( count( $event_array ) > 1 ) {
						/* Translators: %s is the date and time of the first event for a repeating event */
						$format = __( '%s (and other dates)', 'reg-man-rc' );
						$date_label = sprintf( $format, $date_label );
					} // endif
					echo '<p>' . $date_label . '</p>';
				} // endif
			} // endif

			$url = $event_descriptor->get_event_page_url();
			if ( ! empty( $url ) ) {
				echo '<p>';
					$link_text = esc_html__( 'Event details &raquo;', 'reg-man-rc' );
					echo "<a class=\"event-descriptor-details-event-page-link\" href=\"$url\">$link_text</a>";
				echo '</p>';
			} // endif

		$result = ob_get_clean();
		return $result;

	} // function

} // class