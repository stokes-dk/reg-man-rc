<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\View\Venue_View;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * Describes an event venue defined internally by this plugin.
 *
 * An instance of this class contains the information related to an event venue including its name and location.
 *
 * @since v0.1.0
 *
 */
class Venue implements Map_Marker {

	const POST_TYPE				= 'reg-man-rc-venue';
	const DEFAULT_PAGE_SLUG		= 'rc-venues';

	private static $LOCATION_META_KEY	= self::POST_TYPE . '-location';
	private static $GEO_META_KEY		= self::POST_TYPE . '-geo';
	private static $MAP_ZOOM_META_KEY	= self::POST_TYPE . '-map-zoom';

	private $post_id; // the post ID for this venue when it is a custom post type and not a placeholder venue
	private $name;
	private $description;
	private $location;
	private $geo;
	private $url;
	private $map_zoom;

	/**
	 * Instantiate and return a new instance of this class using the specified post data
	 *
	 * @param	\WP_Post	$post	The post data for the new venue
	 * @return	Venue
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post so I can't process it
		} else {
			$result = new self();
			$result->post_id		= $post->ID;
			$result->name			= $post->post_title;
			$result->description	= $post->post_content;
		} // endif
		return $result;
	} // function

	/**
	 * Get all venues, including those whose post status is publish, pending, draft, future or private.
	 *
	 * This method will return an array of instances of this class describing all venues defined under this plugin
	 * whose status is one of publish, pending, draft, future or private.
	 *
	 * @return	Venue[]
	 */
	public static function get_all_venues() {
		$result = array();
		$statuses = self::get_visible_statuses();
		$post_array = get_posts( array(
						'post_type'				=> self::POST_TYPE,
						'post_status'			=> $statuses,
						'posts_per_page'		=> -1, // get all
						'orderby'				=> 'post_title',
						'order'					=> 'ASC',
						'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
		) );
		foreach ( $post_array as $post ) {
			$venue = self::instantiate_from_post( $post );
			if ( $venue !== NULL ) {
				$result[] = $venue;
			} // endif
		} // endfor
		return $result;
	} // function

	/**
	 * Get an array of post statuses that indicates what is visible to the current user.
	 * @param boolean	$is_look_in_trash	A flag set to TRUE if posts in trash should be visible.
	 * @return string[]
	 */
	private static function get_visible_statuses( $is_look_in_trash = FALSE ) {
		$capability = 'read_private_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		if ( current_user_can( $capability ) ) {
			$result = array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ); // don't get auto-draft
			if ( $is_look_in_trash ) {
				$result[] = 'trash';
			} // endif
		} else {
			$result = array( 'publish' );
		} // endif
		return $result;
	} // function


	/**
	 * Get a single venue using its venue ID
	 *
	 * This method will return a single instance of this class describing the venue with the specified ID.
	 * If the venue is not found, this method will return NULL
	 *
	 * @param	int|string	$venue_id	The ID of the venue to be returned
	 * @return	Venue
	 */
	public static function get_venue_by_id( $venue_id ) {
		$post = get_post( $venue_id );
		if ( $post !== NULL ) {
			$post_type = $post->post_type; // make sure that the given post is the right type, there's no reason it shouldn't be
			if ( $post_type == self::POST_TYPE ) {
				$status = $post->post_status;
				$visible = self::get_visible_statuses();
				if ( in_array( $status, $visible ) ) {
					$result = self::instantiate_from_post( $post );
				} else {
					$result = NULL; // The post status is not visible in the currenct context
				} // endif
			} else {
				$result = NULL;
			} // endif
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Get the venue with the specified name.
	 *
	 * @param	string|NULL	$venue_name
	 * @return	Venue	The venue with the specified name or NULL if no venue has the specified location
	 */
	public static function get_venue_by_name( $venue_name ) {
		$result = NULL;
		if ( ! empty( $venue_name ) ) {
			$statuses = self::get_visible_statuses();
			$args = array(
					'title'						=> $venue_name,
					'post_type'					=> self::POST_TYPE,
					'post_status'				=> $statuses,
					'posts_per_page'			=> 1, // only get one
					'ignore_sticky_posts'		=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'update_post_term_cache'	=> false,
					'update_post_meta_cache'	=> false,
					'no_found_rows'				=> true, // Do not calculate the number of rows found
					'orderby'					=> 'post_date ID',
					'order'						=> 'ASC',
			);
			$query = new \WP_Query( $args );
			$posts = $query->posts;
			if ( is_array( $posts ) && isset( $posts[ 0 ] ) ) {
//				$post = get_post( $posts[ 0 ] );
				$result = self::instantiate_from_post( $posts[ 0 ] );
			} // endif
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif
		return $result;

	} // function


	/**
	 * Get the venue with the specified location.
	 * If more than one venue has this location, only the first is returned.
	 *
	 * @param	string|NULL	$location
	 * @return	Venue	The venue with the specified location or NULL if no venue has the specified location
	 */
	public static function get_venue_by_location( $location ) {
		$result = NULL;
		if ( ! empty( $location ) ) {
			$statuses = self::get_visible_statuses();
			$args = array(
					'post_type'				=> self::POST_TYPE,
					'post_status'			=> $statuses,
					'posts_per_page'		=> 1, // only get one
					'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
					'meta_key'				=> self::$LOCATION_META_KEY,
					'meta_query'			=> array(
								array(
										'key'		=> self::$LOCATION_META_KEY,
										'value'		=> $location,
										'compare'	=> '=',
								)
					)
			);

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			if ( is_array( $posts ) && isset( $posts[ 0 ] ) ) {
				$result = self::instantiate_from_post( $posts[ 0 ] );
			} // endif
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif
		return $result;

	} // function





	/**
	 * Create a new venue
	 *
	 * @param	string					$name		The name of the new venue, e.g. "Toronto Reference Libarary"
	 * @param	string					$location	The location of the new venue, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param	Geographic_Position		$geo		The new location for the venue
	 * @param	int						$map_zoom	The zoom level for a map showing this venue by itself
	 * @return	Venue|null
	 */
	public static function create_new_venue( $name, $location, $geo = NULL, $map_zoom = NULL ) {

		$args = array(
				'post_title'	=> $name,
				'post_status'	=> 'publish',
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( 'Unable to create venue', $post_id );
			$result = NULL;
		} else {
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			if ( ! empty( $result ) ) {
				$result->set_location( $location );
				if ( ! empty( $geo ) ) {
					$result->set_geo( $geo );
				} // endif
				if ( ! empty( $map_zoom ) ) {
					$result->set_map_marker_zoom_level( $map_zoom );
				} // endif
			} // endif
		} // endif

		return $result;

	} // function

	/**
	 * Get the post ID of this venue.
	 * @return	int
	 * @since v0.1.0
	 */
	private function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the ID of this venue.  The ID is the post ID.
	 * @return	int		The ID for the venue
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the name of this venue.  The name is the post title.
	 * @return	string		The name of the venue
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this venue.  The description is the post content.
	 * @return	string		The description of the venue
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->description;
	} // function

	/**
	 * Get the venue's location.
	 * @return	string		The venue's location as a string, normally an address.
	 * @since v0.1.0
	 */
	public function get_location() {
		if ( ! isset( $this->location ) ) {
			$post_id = $this->get_post_id();
			if ( ! empty( $post_id ) ) {
				$meta = get_post_meta( $post_id, self::$LOCATION_META_KEY, $single = TRUE );
				$this->location = ( ! empty( $meta ) ) ? $meta : NULL;
			} // endif
		} // endif
		return $this->location;
	} // function

	/**
	 * Set the venue's location.
	 * @param	string	$location	The new location for the venue
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_location( $location ) {
		$post_id = $this->get_post_id();
		if ( ! empty( $post_id ) ) {

			if ( empty( $location ) ) {
				// The new value is empty so we can remove the metadata
				delete_post_meta($post_id, self::$LOCATION_META_KEY );
			} else {
				update_post_meta( $post_id, self::$LOCATION_META_KEY, $location );
			} // endif

		} // endif

		$this->location = $location;

	} // function

	/**
	 * Get the venue's geographic position (latitude and longitude) as an instance of the Geographic_Position class
	 *  or NULL if the position is not known.
	 *
	 * @return	Geographic_Position	The venue's position (co-ordinates) used to map the venue if available, otherwise NULL.
	 * @since v0.1.0
	 */
	public function get_geo() {
		if ( !isset( $this->geo ) ) {
			$post_id = $this->get_post_id();
			if ( ! empty( $post_id ) ) {
				$meta = get_post_meta( $post_id, self::$GEO_META_KEY, $single = TRUE );
				$this->geo = ( !empty( $meta ) ) ? Geographic_Position::create_from_iCalendar_string( $meta ) : NULL;
			} // endif
		} // endif
		return $this->geo;
	} // function

	/**
	 * Set the venue's geographic position.
	 * @param	Geographic_Position		$position	The new location for the venue
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_geo( $position ) {
		$post_id = $this->get_post_id();
		if ( ! empty( $post_id ) ) {
			if ( empty( $position ) ) {
				// The new value is empty so we can remove the metadata
				delete_post_meta( $post_id, self::$GEO_META_KEY );
			} else {
				$val = $position->get_as_iCalendar_string();
				update_post_meta( $post_id, self::$GEO_META_KEY, $val );
			} // endif
		} // endif
		$this->geo = $position;
	} // function

	/**
	 * Get the url for the venue page
	 * @return	string		The url for the page that shows this venue
	 * @since v0.1.0
	 */
	public function get_page_url() {
		if ( ! isset( $this->url ) ) {
			$post_id = $this->get_post_id();
			if ( isset( $post_id ) ) {
				$this->url = get_permalink( $post_id );
			} // endif
		} // endif
		return $this->url;
	} // function


	/**
	 * Get the marker title as a string.
	 * This string is shown on the map when the user hovers over the marker, similar to an element's title attribute.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_title( $map_type ) {
		return $this->get_name();
	} // function

	/**
	 * Get the marker label as a string.  May return NULL if no label is required.
	 * This string, if provided, is shown as text next to the marker.
	 * It can be used to indicate some special condition or information about the marker, e.g. "Event Cancelled"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_label( $map_type ) {
		$result = NULL;
		return $result;
	} // function

	/**
	 * Get the marker location as a string, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL	The marker location if it is known, otherwise NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_location( $map_type ) {
		return $this->get_location();
	} // function

	/**
	 * Get the marker ID as a string.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string
	 * @since v0.1.0
	 */
	public function get_map_marker_id( $map_type ) {
		return self::POST_TYPE . '-' . $this->get_id();
	} // function

	/**
	 * Get the marker position as an instance of Geographic_Position.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	Geographic_Position	The geographic position of the map marker
	 * @since v0.1.0
	 */
	public function get_map_marker_geographic_position( $map_type ) {
		$result = $this->get_geo();
		return $result;
	} // function


	/**
	 * Get the zoom level for the map when the venue is shown on a map by itself.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return	int	The zoom level for the map when this venue is shown by itself.
	 * @since v0.1.0
	 */
	public function get_map_marker_zoom_level( $map_type ) {
		if ( !isset( $this->map_zoom ) ) {
			$meta = get_post_meta( $this->get_post_id(), self::$MAP_ZOOM_META_KEY, $single = TRUE );
			$this->map_zoom = ( !empty( $meta ) ) ? intval( $meta ) : NULL;
		} // endif
		return $this->map_zoom;
	} // function

	/**
	 * Set the zoom level for the map when the venue is shown on a map by itself.
	 * @param	int	$zoom	The zoom level for a map showing this venue by itself
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_map_marker_zoom_level( $zoom ) {
		if ( empty( $zoom ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::$MAP_ZOOM_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::$MAP_ZOOM_META_KEY, intval( $zoom ) );
		} // endif
		unset( $this->map_zoom ); // allow it to be re-acquired
	} // function

	/**
	 * Get the colour used for the map marker or NULL if the default colour should be used.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_colour( $map_type ) {
		return NULL;
	} // function

	/**
	 * Get the opacity used for the map marker or NULL if the default opacity of 1 should be used.
	 * The result must be a number between 0 and 1, zero being completely transparent, 1 being completely opaque.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|float|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_opacity( $map_type ) {
		return NULL;
	} // endif

	/**
	 * Get the content shown in the info window for the marker including any necessary html markup or NULL if no info is needed.
	 * @param string	$map_type	The type of map.  One of the MAP_TYPE_* constants declared by Map_View.
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_map_marker_info( $map_type ) {
		$view = Venue_View::create_for_map_info_window( $this, $map_type );
		$result = $view->get_object_view_content();
		return $result;
	} // function

	/**
	 *  Register the custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {
		$labels = array(
				'name'					=> _x( 'Venues', 'Venue post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Venue', 'Venue post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Venue' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Venue', 'reg-man-rc'),
				'new_item'				=> __( 'New Venue', 'reg-man-rc'),
				'all_items'				=> __( 'Venues', 'reg-man-rc'),
				'view_item'				=> __( 'View Venue', 'reg-man-rc'),
				'search_items'			=> __( 'Search Venues', 'reg-man-rc'),
				'not_found'				=> __( 'No venues found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'No venues found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __('Venues', 'reg-man-rc')
		);
		$capability_singular = User_Role_Controller::EVENT_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Venues', // Internal description, not visible externally
				'public'				=> TRUE, // is it publicly visible?
				'exclude_from_search'	=> FALSE, // exclude from regular search results?
				'publicly_queryable'	=> TRUE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.
				'menu_icon'				=> 'dashicons-location-alt',
				'hierarchical'			=> FALSE, // Can each post have a parent?
/**
 * supports options are	'title', 'editor' (post content), 'author', 'thumbnail', 'excerpt', 'trackbacks',
 *							'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats'
 */
				'supports'				=> array( 'title', 'editor', 'thumbnail' ),
				'taxonomies'			=> array( ),
				'has_archive'			=> FALSE, // is there an archive page?
				'rewrite'				=> array(
					'slug'			=> Settings::get_venues_slug(),
					'with_front'	=> FALSE,
				),
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		register_post_type( self::POST_TYPE, $args );

	} // function

	public static function handle_plugin_uninstall() {
		// When the plugin is uninstalled, remove all posts of my type
		$stati = get_post_stati(); // I need all post statuses
		$posts = get_posts(array(
				'post_type'			=> self::POST_TYPE,
				'post_status'		=> $stati,
				'posts_per_page'	=> -1 // get all
		));
		foreach ($posts as $post) {
			wp_delete_post($post->ID);
		} // endfor
	} // function

} // class