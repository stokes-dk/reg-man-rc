<?php
namespace Reg_Man_RC\Model;


/**
 * An instance of this class represents a category for an event, e.g. Repair Café, Mini Café.
 *
 * @since	v0.1.0
 *
 */
class Event_Category {

	const TAXONOMY_NAME 			= 'reg-man-rc-event-category';

	const COLOUR_META_KEY				= self::TAXONOMY_NAME . '-colour';
	const DEFAULT_COLOUR				= '#cccccc'; // Default colour for an event category with no colour assigned
	const DEFAULT_TERM_COLOUR			= '#1a9d9a'; // Colour for the default term, i.e. "Repair Cafe"
	const EXTERNAL_NAMES_META_KEY		= self::TAXONOMY_NAME . '-external-names';

	private static $DEFAULT_TERM_ID; // Store the ID for the default term for this taxonomy
	private static $DEFAULT_EVENT_CATEGORY; // Store the default category

	private $term_id;
	private $name;
	private $slug;
	private $description;
	private $calendar_array; // An array of calendars in which this category is visible
	private $count;
	private $colour;
	private $external_names; // An array of string names used to refer to this event category externally
	// $external_names are used to map item types from external systems like our legacy data to internal names

	private $is_accept_item_reg; // TRUE if this category is in the visitor registration calendar
	private $is_accept_volunteer_reg; // TRUE if this category is in the volunteer registration calendar

	private static $all_categories; // an array of categories used for caching and to find a category by id
	private static $all_categories_by_name_array; // array of indexed by name, used to find a category by its name

	private static $item_registration_categories; // an array of categories that allow item registration
	private static $volunteer_registration_categories; // an array of categories that allow volunteer registration

	private function __construct() { }

	/**
	 * Instantiate this class using the supplied data object
	 *
	 * @param	\WP_Term			$term	The object containing the data for this instance
	 * @return	Event_Category	An instance of this class
	 *
	 * @since v0.1.0
	 */
	private static function instantiate_from_term( $term ) {
		if ( !( $term instanceof \WP_Term ) ) {
			$result = NULL; // The data is not an object so I can't process it
		} else {
			$term_id = isset( $term->term_id ) ? intval( $term->term_id ) : NULL; // defensive, this must always be set
			$taxonomy = isset( $term->taxonomy ) ? $term->taxonomy : NULL;
			if ( ( $taxonomy !== self::TAXONOMY_NAME ) || ( $term_id === NULL ) ) {
	 			$result = NULL;  // The taxonomy is not right or the term id is missing
	 		} else {
		 		$result = new self();
		 		$result->term_id = $term_id;
		 		$result->name = isset( $term->name ) ? $term->name : NULL;
		 		$result->slug = isset( $term->slug ) ? $term->slug : NULL;
		 		$result->description = isset( $term->description ) ? $term->description : NULL;
		 		$result->count = isset( $term->count ) ? $term->count : NULL;
	 		} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Create a new event category
	 * @param string $name
	 * @param string $description
	 * @param string $colour
	 * @return NULL|Event_Category	The newly created event category or NULL if there was an error
	 */
	public static function create_event_category( $name, $description, $colour ) {
		$existing = self::get_event_category_by_name( $name );
		if ( ! empty( $existing ) ) {
			/* translators: %s is the name of an event category */
			$err_msg = sprintf( __( 'Failed to create event category: %s because the name already exists', 'reg-man-rc' ), $name );
			Error_Log::log_msg( $err_msg, $name );
			$result = NULL;
		} else {
			$args = array();
			if ( ! empty( $description ) ) {
				$args[ 'description' ] = $description;
			} // endif
			$insert_result = wp_insert_term( $name, self::TAXONOMY_NAME, $args );
			if ( is_wp_error( $insert_result ) ) {
				/* translators: %s is the name of an event category */
				$err_msg = sprintf( __( 'Failed to insert event category: %s', 'reg-man-rc' ), $name );
				Error_Log::log_wp_error( $err_msg, $insert_result );
				$result = NULL;
			} else {
				$term_id = $insert_result[ 'term_id' ];
				$term = get_term( $term_id, self::TAXONOMY_NAME );
				$category = self::instantiate_from_term( $term );
				if ( ! empty( $colour ) ) {
					$category->set_colour( $colour );
				} // endif

				$result = $category;
				self::$all_categories = NULL; // Allow this to be re-acquired
				self::$all_categories_by_name_array = NULL; // Allow this to be re-acquired
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get all event categories defined to the system
	 *
	 * @return	Event_Category[]	An array of instances of this class representing all event categories
	 *
	 * @since v0.1.0
	 */
	public static function get_all_event_categories() {
		if ( ! isset( self::$all_categories ) ) {
			self::$all_categories = array();
			$args = array(
					'taxonomy'		=> self::TAXONOMY_NAME,
					'hide_empty'	=> FALSE, // We want all, including those used by no post
			);
			$term_array = get_terms( $args );
			foreach ( $term_array as $term ) {
				$category = self::instantiate_from_term( $term );
				if ( $category !== NULL ) {
					self::$all_categories[ $category->get_id() ] = $category;
				} // endif
			} // endfor
//			Error_Log::var_dump( $term_array, self::$all_categories );
		} // endif
		return self::$all_categories;
	} // function

	/**
	 * Get the event category with the specified ID
	 *
	 * @param	int|string	$event_category_id	The ID of the event category to be returned
	 * @return	Event_Category	An instance of this class with the specified ID or NULL if the event category does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_event_category_by_id( $event_category_id ) {
		$all_categories = self::get_all_event_categories();
		$result = isset( $all_categories[ $event_category_id ] ) ? $all_categories[ $event_category_id ] : NULL;
		return $result;
	} // function

	private static function get_all_categories_by_name_array() {
		if ( ! isset( self::$all_categories_by_name_array ) ) {
			$all_cats = self::get_all_event_categories();
			self::$all_categories_by_name_array = array();
			foreach ( $all_cats as $category ) {
				$name = $category->get_name();
				$name = wp_specialchars_decode( $name ); // A & B should be found using A &amp; B and vice-versa
				self::$all_categories_by_name_array[ $name ] = $category;
				// we will also allow callers to find a category using one of its external names
				$ext_names_array = $category->get_external_names();
				foreach ( $ext_names_array as $external_name ) {
					$external_name = wp_specialchars_decode( $external_name );
					if ( ! isset( self::$all_categories_by_name_array[ $external_name ] ) ) {
						// if an external name matches an exiting internal name then don't overwrite it
						self::$all_categories_by_name_array[ $external_name ] = $category;
					} // endif
				} // endfor
			} // endfor
		} // endif
		return self::$all_categories_by_name_array;
	} // function

	/**
	 * Get the event category with the specified name
	 *
	 * @param	string	$category_name	The name of the event category to be returned
	 * @return	Event_Category|NULL		An instance of this class with the specified name or NULL if the event category does not exist
	 * Note that this will match using external names as well the internal name.
	 * Use $result->get_name() to get the internal name of the matching event category.
	 *
	 * @since v0.1.0
	 */
	public static function get_event_category_by_name( $category_name ) {
		$category_name = wp_specialchars_decode( $category_name ); // A & B should be found using A &amp; B and vice-versa
		$cats_by_name = self::get_all_categories_by_name_array();
		$result = isset( $cats_by_name[ $category_name ] ) ? $cats_by_name[ $category_name ] : NULL;
		return $result;
	} // function

	/**
	 * Get the event categories assigned to a specific post like a internal event descriptor or a calendar
	 *
	 * @param	int|string	$post_id	The ID of the post whose fixer stations are to be returned
	 * @return	Event_Category[]		An array of instances of this class representing all categories assigned to the specified post
	 *
	 * @since v0.1.0
	 */
	public static function get_event_categories_for_post( $post_id ) {
		$result = array();
		$term_ids = wp_get_post_terms( $post_id, self::TAXONOMY_NAME, array( 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			$all_categories = self::get_all_event_categories();
			foreach ( $term_ids as $id ) {
				if ( isset( $all_categories[ $id ] ) ) {
					$result[] = self::get_event_category_by_id( $id );
				} // endif
			} // endfor
		} // endif
		return $result;
	} // function

	/**
	 * Get the URL for the admin UI for this taxonomy
	 * @return string
	 */
	public static function get_admin_url() {
		$taxonomy = self::TAXONOMY_NAME;
		$base_url = admin_url( 'edit-tags.php?' );
		$result = add_query_arg( array( 'taxonomy' => $taxonomy ), $base_url );
		return $result;
	} // function

	/**
	 * Get the ID for the default term for this taxonomy
	 * @return	int	The ID for the default term for this taxonomy
	 */
	public static function get_default_term_id() {
		if ( ! isset( self::$DEFAULT_TERM_ID ) ) {
			// The term ID is stored in the options table
			$id = get_option( 'default_term_' . self::TAXONOMY_NAME );
			if ( ! empty( $id ) ) {
				self::$DEFAULT_TERM_ID = intval( $id );
			} // endif
		} // endif
		return self::$DEFAULT_TERM_ID;
	} // function

	/**
	 * Get the default event category
	 * @return	Event_Category	The default event category to be used when nothing else is assigned
	 */
	public static function get_default_event_category() {
		if ( ! isset( self::$DEFAULT_EVENT_CATEGORY ) ) {
			$event_category_id = self::get_default_term_id();
			if ( ! empty( $event_category_id ) ) {
				self::$DEFAULT_EVENT_CATEGORY = self::get_event_category_by_id( $event_category_id );
			} // endif
		} // endif
		return self::$DEFAULT_EVENT_CATEGORY;
	} // function

	/**
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this event category
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->term_id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this event category
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this object
	 *
	 * @return	string	The description of this event category
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->description;
	} // function

	/**
	 * Get the array of calendar objects that include this event category
	 *
	 * @return	Calendar[]	An array of Calendar objects that include this event category
	 *
	 * @since v0.1.0
	 */
	public function get_calendar_array() {
		if ( ! isset( $this->calendar_array ) ) {
			$this->calendar_array = Calendar::get_calendars_with_event_category( $this );
		} // endif
		return $this->calendar_array;
	} // function

	/**
	 * Get the slug of this object.  This is used to search for posts that refer to this fixer station.
	 *
	 * @return	string	The slug of this fixer station, e.g. clothing
	 *
	 * @since v0.1.0
	 */
	public function get_slug() {
		return $this->slug;
	} // function

	/**
	 * Get the count of events of this event category
	 *
	 * @return	int		The count of items using this event category
	 *
	 * @since v0.1.0
	 */
	public function get_count() {
		return $this->count;
	} // function

	/**
	 * Get the count of Internal_Event_Descriptors using this event category
	 *
	 * @return	int		The count of Internal_Event_Descriptors using this event category
	 *
	 * @since v0.1.0
	 */
	public function get_internal_event_descriptor_count() {
		$args = array(
			'post_type'			=> Internal_Event_Descriptor::POST_TYPE,
			'posts_per_page'	=> -1, // Get all posts
			'tax_query' => array(
				array(
					'taxonomy'	=> self::TAXONOMY_NAME,
					'field'		=> 'term_id',
					'terms'		=> $this->get_id()
				),
			),
		);
		$query = new \WP_Query( $args );
		$result = $query->found_posts;
		wp_reset_postdata(); // Required after using WP_Query()
		return $result;
	} // function

	/**
	 * Get the count of Calendars using this event category
	 *
	 * @return	int		The count of Calendars using this event category
	 *
	 * @since v0.1.0
	 */
	public function get_calendar_count() {
		$args = array(
			'post_type'			=> Calendar::POST_TYPE,
			'posts_per_page'	=> -1, // Get all posts
			'tax_query' => array(
				array(
					'taxonomy'	=> self::TAXONOMY_NAME,
					'field'		=> 'term_id',
					'terms'		=> $this->get_id()
				),
			),
		);
		$query = new \WP_Query( $args );
		$result = $query->found_posts;
		wp_reset_postdata(); // Required after using WP_Query()
		return $result;
	} // function

	/**
	 * Get the colour for events of this category.  This is used to colour code event categories on maps and graphs.
	 *
	 * @return	string	The CSS colour used to indicate this category.
	 *
	 * @since v0.1.0
	 */
	public function get_colour() {
		if ( ! isset( $this->colour ) )  {
			$meta = get_term_meta( $this->get_id(), self::COLOUR_META_KEY, $single = TRUE);
			if ( ( $meta !== FALSE ) && ( $meta !== NULL ) && ( $meta !== '' ) ) {
				$this->colour = $meta;
			} else {
				$default_id = self::get_default_term_id();
//				Error_Log::var_dump( $default_id, $this->get_id() );
				if ( $this->get_id() == $default_id ) {
					// If this is the default term then use its colour
					$this->colour = self::DEFAULT_TERM_COLOUR;
				} else {
					// If there is no colour assigned then use the default (grey)
					$this->colour = self::DEFAULT_COLOUR;
				} // endif
			} // endif
		} // endif
		return $this->colour;
	} // function

	/**
	 * Set the colour for events of this category
	 *
	 * @param	string	$colour		The CSS colour used to indicate this category.
	 *
	 * @since v0.1.0
	 */
	public function set_colour( $colour ) {
		if ( ( $colour === '' ) || ( $colour === NULL ) || ( $colour === FALSE ) ) {
			delete_term_meta( $this->get_id(), self::COLOUR_META_KEY );
		} else {
			// We need to make sure there is only one value so if none exists add it, otherwise update it
			$curr = get_term_meta( $this->get_id(), self::COLOUR_META_KEY, $single = TRUE );
			if ( ( $curr === '' ) || ( $curr === NULL ) || ( $curr === FALSE ) ) {
				add_term_meta( $this->get_id(), self::COLOUR_META_KEY, $colour );
			} else {
				update_term_meta( $this->get_id(), self::COLOUR_META_KEY, $colour, $curr );
			} // endif
		} // endif
		unset( $this->colour ); // Allow this to be re-acquired
	} // function

	/**
	 * Get the flag indicating whether this category is for events where items can be registered.
	 * TRUE for usual repair café events, FALSE for non-repair events like volunteer appreciation events.
	 *
	 * @return	boolean		TRUE if this category is for events where items can be registered for repair, FALSE otherwise.
	 *
	 * @since v0.1.0
	 */
	public function get_is_accept_item_registration() {
		if ( ! isset( $this->is_accept_item_reg ) )  {
			$this->is_accept_item_reg = FALSE; // Assume false and then set to TRUE as needed
			$visitor_reg_calendar = Calendar::get_visitor_registration_calendar();
			if ( isset( $visitor_reg_calendar ) ) {
				$category_array = $visitor_reg_calendar->get_event_category_array();
				$my_id = $this->get_id();
				foreach( $category_array as $event_category ) {
					if ( $event_category->get_id() == $my_id ) {
						$this->is_accept_item_reg = TRUE;
						break;
					} // endif
				} // endfor
			} // endif
		} // endif
		return $this->is_accept_item_reg;
	} // function

	/**
	 * Get the flag indicating whether this category is for events where fixers and volunteers can be registered.
	 * TRUE for usual repair café events.  May be FALSE for an event category where volunteers do not register to attend.
	 *
	 * @return	boolean		TRUE if this category is for events where volunteers and fixers can be register, FALSE otherwise.
	 *
	 * @since v0.1.0
	 */
	public function get_is_accept_volunteer_registration() {
		if ( ! isset( $this->is_accept_vol_reg ) )  {
			$this->is_accept_item_reg = FALSE; // Assume false and then set to TRUE as needed
			$volunteer_reg_calendar = Calendar::get_volunteer_registration_calendar();
			if ( isset( $volunteer_reg_calendar ) ) {
				$category_array = $volunteer_reg_calendar->get_event_category_array();
				$my_id = $this->get_id();
				foreach( $category_array as $event_category ) {
					if ( $event_category->get_id() == $my_id ) {
						$this->is_accept_item_reg = TRUE;
						break;
					} // endif
				} // endfor
			} // endif
		} // endif
		return $this->is_accept_vol_reg;
	} // function



	/**
	 * Get the array of external names for this event category.
	 * This is used to allow mapping external names like "Full Event" to internal item types like "Repair Cafe"
	 *
	 * @return	string[]	The array of external names used to reference this event category
	 *
	 * @since v0.1.0
	 */
	public function get_external_names() {
		if ( ! isset( $this->external_names ) )  {
			$meta = get_term_meta( $this->get_id(), self::EXTERNAL_NAMES_META_KEY, $single = FALSE );
			if ( ( $meta !== FALSE ) && ( $meta !== NULL ) ) {
				$this->external_names = $meta;
			} // endif
		} // endif
		return $this->external_names;
	} // function

	/**
	 * Set the external names for this event category
	 *
	 * @param	string[]	$external_names		The array of external names used to refer to this event category
	 *
	 * @since v0.1.0
	 */
	public function set_external_names( $external_names_array ) {
		// first remove all the old names then insert the new ones
		delete_term_meta( $this->get_id(), self::EXTERNAL_NAMES_META_KEY );
		if ( is_array( $external_names_array) ) {
			foreach( $external_names_array as $name ) {
				add_term_meta( $this->get_id(), self::EXTERNAL_NAMES_META_KEY, $name );
			} // endfor
		} // endif
		unset( $this->external_names ); // Allow this to be re-acquired
	} // function

	/**
	 * Register event category as a taxonomy
	 *
	 * This method is called by the plugin controller
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function register() {

		// Register new taxonomy
		$labels = array(
				'name' 							=> __( 'Event Categories','reg-man-rc' ),
				'singular_name'					=> __( 'Event Category', 'reg-man-rc' ),
				'menu_name'						=> __( 'Event Categories', 'reg-man-rc' ),
				'all_items'						=> __( 'All Event Categories', 'reg-man-rc' ),
				'edit_item'						=> __( 'Edit Event Category', 'reg-man-rc' ),
				'view_item'						=> __( 'View Event Category', 'reg-man-rc' ),
				'update_item'					=> __( 'Update Event Category', 'reg-man-rc' ),
				'add_new_item'					=> __( 'Add New Event Category', 'reg-man-rc' ),
				'new_item_name'					=> __( 'New Event Category', 'reg-man-rc' ),
				'parent_item'					=> __( 'Parent Event Category', 'reg-man-rc' ),
				'parent_item_colon'				=> __( 'Parent Event Category:', 'reg-man-rc' ),
				'search_items'					=> NULL,// __( 'Search Event Categories', 'reg-man-rc' ),
				'popular_items'					=> NULL,// __( 'Popular Event Categories', 'reg-man-rc' ),
				'separate_items_with_commas'	=> NULL,// __( 'Separate event categories with commas', 'reg-man-rc' ),
				'add_or_remove_items'			=> NULL,// __( 'Add or remove event categories', 'reg-man-rc' ),
				'choose_from_most_used'			=> NULL,// __( 'Choose from the most used event categories', 'reg-man-rc' ),
				'not_found'						=> NULL,// __( 'Event Category not found', 'reg-man-rc' ),
		);

		$args = array(
				'labels'				=> $labels,
				'description'			=> __( 'Event\'s category', 'reg-man-rc' ),
				'hierarchical'			=> FALSE,	// Does each one have a parent and a heirarchy?
				'public'				=> TRUE,	// whether it's intended for public use - must be TRUE to enable polylang translations
				'publicly_queryable'	=> FALSE,	// can it be queried
				'show_ui'				=> TRUE,	// does it have a UI for managing it
				// Note that if we set show_in_rest to TRUE then the block editor will show a metabox in post types
				//  that use this taxonom REGARDLESS of the setting for meta_box_cb
				// There is a way around that using the 'rest_prepare_taxonomy' filter if absolutely necessary
				'show_in_rest'			=> FALSE,	// is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_menu'			=> TRUE,	// TRUE means show in submenu under its associated post type
				'show_in_nav_menus'		=> FALSE,	// available for selection in navigation menus?
				'show_admin_column'		=> TRUE,	// show values as column in admin post listing screens
				'show_tagcloud'			=> FALSE,	// whether to allow the Tag Cloud widget to use this taxonomy
				'show_in_quick_edit'	=> FALSE,	// whether to show default taxonomy editor in the quick edit UI
				'meta_box_cb'			=> FALSE,	// This will be created by the custom post types as required
				// 'update_count_callback'	=> array(__CLASS__, 'update_post_term_count'),
				'query_var'				=> TRUE,
				'rewrite'				=> FALSE,
		);

		// Add an argument for the default term but only if it has not yet been created
		$default_term_id = self::get_default_term_id();
		if ( empty( $default_term_id ) ) {
			$args[ 'default_term' ] =  array(
					'name'			=> __( 'Repair Café', 'reg-man-rc' ),
					'slug'			=> __( 'repair-cafe', 'reg-man-rc' ),
					'description'	=> __( 'A full repair café event with all or most fixer stations', 'reg-man-rc' ),
			);
		} // endif

		$post_types = array(
				Calendar::POST_TYPE,
				Internal_Event_Descriptor::POST_TYPE,
		);
		$taxonomy = register_taxonomy( self::TAXONOMY_NAME, $post_types, $args );

		if ( is_wp_error( $taxonomy ) ) {
			$msg = __( 'Failure to register taxonomy for Event Categories', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $taxonomy );
		} else {
/* FIXME - removing this because we have exactly ONE default category that is created automatically
			// Note that I must register the taxonomy before assigning a notification bubble to its menu item
			// This is because I need to check if the taxonomy is empty to determine if a bubble is needed
			//  but I can't check if it's empty until it has been registered
			$notice_count = Event_Category_Admin_View::get_is_show_create_defaults_admin_notice() ? 1 : 0;
			if ( $notice_count > 0 ) {
				$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
				$taxonomy->labels->menu_name .= $notification_bubble;
			} // endif
*/
		} // endif

	} // function

	/**
	 * Get the html content shown to the administrator in the "About" help for this taxonomy
	 * @return string
	 */
	public static function get_about_content() {
		ob_start();
			$heading = __( 'About event categories', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'Events categories allow you to define different types of events.' .
					'  For example, you may have a category for regular repair café events, one for small events and another for volunteer appreciation events.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Events can be categorized in any way you like.' .
					'  The default category is "Repair Café".  It can be renamed if necessary but it cannot be removed.' .
					'  An event with no category is automatically assigned the default.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When you create a calendar you can specify which event categories it shows and which are excluded,' .
					' and each event on the calendar is colour-coded to indicate its category.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';

			$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Returns data that represent the default categories used to perform initial configuration of the plugin.
	 * @return	string[][]
	 */
/* FIXME - removing this because we have exactly ONE default event category and it's created automatically
	public static function get_default_category_data() {
		$result = array();
		$result[] = array(
			'name'			=> __( 'Repair Café', 'reg-man-rc' ),
			'description'	=> __( 'A full repair café event with all or most fixer stations', 'reg-man-rc' ),
			'colour'		=> '#1a9d9a',
			'is_item_reg'	=> TRUE,
			'is_vol_reg'	=> TRUE,
		);
		$result[] = array(
			'name'			=> __( 'Midi Event', 'reg-man-rc' ),
			'description'	=> __( 'A medium-sized repair event with a few fixer stations', 'reg-man-rc' ),
			'colour'		=> '#fb9d19',
			'is_item_reg'	=> TRUE,
			'is_vol_reg'	=> TRUE,
		);
		$result[] = array(
			'name'			=> __( 'Mini Event', 'reg-man-rc' ),
			'description'	=> __( 'A small repair event with one or two fixer stations', 'reg-man-rc' ),
			'colour'		=> '#de2d37',
			'is_item_reg'	=> TRUE,
			'is_vol_reg'	=> TRUE,
		);
		$result[] = array(
			'name'			=> __( 'Volunteer Event', 'reg-man-rc' ),
			'description'	=> __( 'A non-repair event for volunteers only like a volunteer appreciation dinner', 'reg-man-rc' ),
			'colour'		=> '#0f6eb0',
			'is_item_reg'	=> FALSE,
			'is_vol_reg'	=> TRUE,
		);
		return $result;
	} // function
*/
	/**
	 * Handle plugin uninstall
	 *
	 * This method is called by the plugin controller
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function handle_plugin_uninstall() {
		// When the plugin is uninstalled, remove all terms under my taxonomy then unregister my taxonomy
		// N.B. get_terms() doesn't work at this point because my plugin is not active so my taxonomy is not registered
		$args = array(
				'taxonomy'			   => self::TAXONOMY_NAME,
				'hide_empty'			 => FALSE,
		);
		$query = new \WP_Term_Query( $args );
		foreach( $query->get_terms() as $term ){
			wp_delete_term( $term->term_id, self::TAXONOMY_NAME );
		} // endfor
		unregister_taxonomy( self::TAXONOMY_NAME );
	} // function

} // class
