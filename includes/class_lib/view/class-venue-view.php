<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Object_View\List_Item;
use Reg_Man_RC\View\Object_View\Map_Section;
use Reg_Man_RC\View\Object_View\List_Section;
use Reg_Man_RC\View\Object_View\Abstract_Object_View;
use Reg_Man_RC\View\Object_View\Location_Item_Provider;
use Reg_Man_RC\View\Object_View\Venue_Item_Provider;
use Reg_Man_RC\View\Object_View\Object_View;

/**
 * An instance of this class provides a user interface for an venue.
 *
 * @since	v0.1.0
 *
 */
class Venue_View extends Abstract_Object_View {

	private $venue; // The venue object shown in this view
	private $item_provider;

	/**
	 * A protected constructor forces users to use one of the factory methods
	 */
	private function __construct() {
	} // constructor

	/**
	 * Get an instance of this class that will provide the contents for the object's page
	 * @param	Venue	$venue
	 * @return	Venue_View
	 */
	public static function create_for_page_content( $venue ) {
		$result = new self();
		$result->venue = $venue;
		$result->set_object_page_type( Object_View::OBJECT_PAGE_TYPE_VENUE );
		return $result;
	} // function

	/**
	 * Get an instance of this class that will provide the contents for the object's info window in a map
	 * @param	Venue	$venue
	 * @return	Venue_View
	 */
	public static function create_for_map_info_window( $venue, $map_type ) {
		$result = new self();
		$result->venue = $venue;
		$result->set_title( $venue->get_name() );
		$result->set_info_window_map_type( $map_type );
		return $result;
	} // function

	/**
	 * Get the venue object for this view.
	 * @return	Venue		The venue object shown in this view.
	 * @since	v0.1.0
	 */
	private function get_venue() {
		return $this->venue;
	} // function

	/**
	 * Get the venue item provider for this view
	 * @return	Venue_Item_Provider		The venue item provider
	 * @since	v0.1.0
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {
			$this->item_provider = Venue_Item_Provider::create( $this->get_venue(), $this );
		} // endif
		return $this->item_provider;

	} // function

	/**
	 * Set the venue object
	 * @param Venue $venue
	 * @since	v0.1.0
	 */
	private function set_venue( $venue ) {
		$this->venue = $venue;
	} // function

	/**
	 * Get the title displayed for this object view.  May be any html or plain text.
	 * @return string
	 */
/* use superclass
	public function get_object_view_title() {

	} // function
*/

	/**
	 * Get the section (if any) to be rendered after the title
	 * @return Object_View_Section	The section to be displayed after the title
	 */
	public function get_object_view_after_title_section() {
		return NULL;
	} // function

	/**
	 * Get the array of main content sections.
	 * @return Object_View_Section[]
	 */
	public function get_object_view_main_content_sections_array() {
		$result = array();
		$venue = $this->get_venue();

		if ( $this->get_is_object_page() ) {
			// Map
			$result[] = Map_Section::create( $venue );
			$place_name = NULL; // Note that the venue's name is already shown in the title on a venue page
		} else {
			$place_name = $venue->get_name();
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
	private function get_details_item_names_array() {
		$result = array(
				List_Item::LOCATION_ADDRESS,
				List_Item::GET_DIRECTIONS_LINK,
		);
		if( $this->get_is_object_page() ) {
			$result[] = List_Item::VENUE_DESCRIPTION;
		} // endif
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

		// conditionally add my scripts and styles correctly for front end
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Add a filter to change the content written for this type
		add_filter( 'the_content', array(__CLASS__, 'modify_post_content') );

		// Add a filter to change the excerpt written for this type
		add_filter( 'the_excerpt', array(__CLASS__, 'modify_post_excerpt') );

	} // function

	/**
	 * Modify the contents shown for posts of this type so that I can format it and show the right details
	 * @param	string	$content	The post content retrieved from the database
	 * @return	string	The post content modified for my custom post type
	 * @since	v0.1.0
	 */
	public static function modify_post_content( $content ) {
		global $post;
		$result = $content; // return the original content by default
		if ( is_single() && in_the_loop() && is_main_query() ) {
			if ( $post->post_type == Venue::POST_TYPE ) {
				if ( ! post_password_required( $post ) ) {
					$venue = Venue::get_venue_by_id( $post->ID );
					if ( $venue !== NULL ) {
						$view = self::create_for_page_content( $venue );
						$result = $view->get_object_view_content();
					} // endif
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
		if ( $post->post_type == Venue::POST_TYPE ) {
			if ( ! post_password_required( $post ) ) {
				$venue = Venue::get_venue_by_id( $post->ID );
				if ( $venue !== NULL ) {
					ob_start();
						echo '<div class="reg-man-rc-custom-post-content">';
							$view = new self();
							$view->set_venue( $venue );
							$view->render_excerpt();
						echo '</div>';
					$result = ob_get_clean();
				} // endif
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get a flag indicating whether the current page is showing a venue
	 */
	private static function get_is_venue_page() {
		$internal_venue_post_type = Venue::POST_TYPE;
		if ( is_singular( $internal_venue_post_type ) ) {
			global $wp_query, $wp;
			$internal_query_var = get_query_var( $internal_venue_post_type, FALSE );
			$result = ( $internal_query_var !== FALSE );
		} else {
			$result = FALSE;
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
		if ( self::get_is_venue_page() ) {
			// Register the scripts and styles I need
			\Reg_Man_RC\Control\Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
			\Reg_Man_RC\Control\Scripts_And_Styles::enqueue_google_maps();
		} // endif
	} // function

	/**
	 * Render the details for this view
	 * @since	v0.1.0
	 */
	protected function render_details_list_items() {

		// Location
		$this->render_location();

		// Get directions
		$this->render_get_directions_link();

		// TODO: Upcoming event dates

		// Venue description
		$this->render_venue_description();

	} // function

	/**
	 * Render the excerpt for this object
	 * @since	v0.1.0
	 */
	private function render_excerpt() {
		$venue = $this->get_venue();
		if ( isset( $venue ) ) {

			$location = $venue->get_location();
			echo '<p>' . $location . '</p>';

			$url = $venue->get_page_url();
			if ( ! empty( $url ) ) {
				echo '<p>';
					$link_text = esc_html__( 'Venue details &raquo;', 'reg-man-rc' );
					echo "<a class=\"venue-details-page-link\" href=\"$url\">$link_text</a>";
				echo '</p>';
			} // endif
		} // endif
	} // function

} // class