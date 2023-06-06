<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Volunteer_Role_Admin_View;

/**
 * An instance of this class represents a volunteer role.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Role {

	const TAXONOMY_NAME = 'reg-man-rc-volunteer-role';

	const UNSPECIFIED_VOLUNTEER_ROLE_ID	= 0; // An ID used to indicate that the volunteer role is not specified

	const COLOUR_META_KEY			= self::TAXONOMY_NAME . '-colour';
	const EXTERNAL_NAMES_META_KEY	= self::TAXONOMY_NAME . '-external-names';

	private $term_id;
	private $name;
	private $slug;
	private $description;
	private $count;
	private $colour;
	private $external_names; // An array of names used to refer to this volunteer role externally

	private static $all_volunteer_roles;
	private static $all_roles_by_name;
	private static $term_tax_ids_array; // an array of all term taxonomy IDs in this taxonomy

	private function __construct() { }

	/**
	 * Create a new instance of this class using the supplied data object
	 *
	 * @param	\WP_Term			$term	The object containing the data for this instance
	 * @return	Volunteer_Role	An instance of this class
	 *
	 * @since v0.1.0
	 */
	private static function instantiate_from_term( $term ) {
		if ( !( $term instanceof \WP_Term ) ) {
			$result = NULL; // The data is not an object so I can't process it
		} else {
			$term_id = isset( $term->term_id ) ? intval( $term->term_id ) : NULL;
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
	 * Create a new volunteer role
	 * @param string		$name
	 * @param string		$description
	 * @param string		$colour
	 * @return NULL|Item_Type	The newly created volunteer role or NULL if there was an error
	 */
	public static function create_volunteer_role( $name, $description, $colour ) {
		$existing = self::get_volunteer_role_by_name( $name );
		if ( ! empty( $existing ) ) {
			/* translators: %s is the name of an volunteer role */
			$err_msg = sprintf( __( 'Failed to create volunteer role: %s because the name already exists', 'reg-man-rc' ), $name );
			Error_Log::log_msg( $err_msg, $name );
			$result = NULL;
		} else {
			$args = array();
			if ( ! empty( $description ) ) {
				$args[ 'description' ] = $description;
			} // endif
			$insert_result = wp_insert_term( $name, self::TAXONOMY_NAME, $args );
			if ( is_wp_error( $insert_result ) ) {
				/* translators: %s is the name of an volunteer role */
				$err_msg = sprintf( __( 'Failed to insert volunteer role: %s', 'reg-man-rc' ), $name );
				Error_Log::log_wp_error( $err_msg, $insert_result );
				$result = NULL;
			} else {
				$term_id = $insert_result[ 'term_id' ];
				$term = get_term( $term_id, self::TAXONOMY_NAME );
				$result = self::instantiate_from_term( $term );
				if ( ! empty( $colour ) ) {
					$result->set_colour( $colour );
				} // endif
				self::$all_volunteer_roles = NULL; // Allow this to be re-acquired
				self::$all_roles_by_name = NULL; // Allow this to be re-acquired
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get all volunteer roles defined to the system
	 *
	 * @return	Volunteer_Role[]	An array of instances of this class representing all volunteer roles
	 *
	 * @since v0.1.0
	 */
	public static function get_all_volunteer_roles() {
		if ( ! isset( self::$all_volunteer_roles ) ) {
			// Returns an array of instances of this class
			self::$all_volunteer_roles = array();
			$terms_array = get_terms( array(
					'taxonomy'		=> self::TAXONOMY_NAME,
					'hide_empty'	=> FALSE // We want all, including those used by no post
			) );
			foreach ( $terms_array as $term ) {
				$role = self::instantiate_from_term( $term );
				if ( $role !== NULL ) {
					self::$all_volunteer_roles[ $role->get_id() ] = $role;
				} // endif
			} // endfor
		} // endif
		return self::$all_volunteer_roles;
	} // function

	/**
	 * Get all term taxonomy IDs for this taxonomy
	 *
	 * @return	int[]	An array of term taxonomy IDs for all the terms defined to this taxonomy
	 *
	 * @since v0.1.0
	 */
	public static function get_all_term_taxonomy_ids() {
		if ( ! isset( self::$term_tax_ids_array ) ) {
			self::$term_tax_ids_array = get_terms( array(
					'taxonomy'		=> self::TAXONOMY_NAME,
					'hide_empty'	=> FALSE, // We want all, including those used by no post
					'fields'		=> 'tt_ids' // We only want term taxonomy IDs, returned as an array
			) );
		} // endif
		return self::$term_tax_ids_array;
	} // function

	/**
	 * Get the volunteer role with the specified ID
	 *
	 * @param	int|string		$volunteer_role_id	The ID of the volunteer role to be returned
	 * @return	Volunteer_Role	An instance of this class with the specified ID or NULL if the volunteer role does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_volunteer_role_by_id( $volunteer_role_id ) {
		$all_roles = self::get_all_volunteer_roles();
		$result = isset( $all_roles[ $volunteer_role_id ] ) ? $all_roles[ $volunteer_role_id ] : NULL;
		return $result;
	} // function

	private static function get_all_volunteer_roles_by_name_array() {
		if ( ! isset( self::$all_roles_by_name ) ) {
			$all_roles = self::get_all_volunteer_roles();
			self::$all_roles_by_name = array();
			foreach ( $all_roles as $role ) {
				$name = $role->get_name();
				$name = wp_specialchars_decode( $name ); // A & B should be found using A &amp; B and vice-versa
				self::$all_roles_by_name[ $name ] = $role;
				// we will also allow callers to find a role using one of its external names
				$ext_names_array = $role->get_external_names();
				foreach ( $ext_names_array as $external_name ) {
					$external_name = wp_specialchars_decode( $external_name );
					if ( ! isset( self::$all_roles_by_name[ $external_name ] ) ) {
						// if an external name matches an exiting internal name then don't overwrite it
						self::$all_roles_by_name[ $external_name ] = $role;
					} // endif
				} // endfor
			} // endfor
		} // endif
		return self::$all_roles_by_name;
	} // function

	/**
	 * Get the volunteer role with the specified name
	 *
	 * @param	string	$volunteer_role_name	The name of the role to be returned
	 * @return	Volunteer_Role	An instance of this class with the specified name or NULL if the role does not exist
	 * Note that this will match using external names as well the internal name.
	 * Use $result->get_name() to get the internal name of the matching volunteer role
	 *
	 * @since v0.1.0
	 */
	public static function get_volunteer_role_by_name( $volunteer_role_name ) {
		$volunteer_role_name = wp_specialchars_decode( $volunteer_role_name ); // A & B should be found using A &amp; B and vice-versa
		$roles_array = self::get_all_volunteer_roles_by_name_array();
		$result = ( ! empty( $roles_array ) && isset( $roles_array[ $volunteer_role_name ] ) ) ? $roles_array[ $volunteer_role_name ] : NULL;
		return $result;
	} // function

	/**
	 * Get the volunteer roles assigned to a specific post like an volunteer registration
	 *
	 * @param	int|string	$post_id	The ID of the post whose volunteer roles are to be returned
	 * @return	Fixer_Station[]			An array of instances of this class representing all volunteer roles assigned to the specified post
	 *
	 * @since v0.1.0
	 */
	public static function get_volunteer_roles_for_post( $post_id ) {
		$result = array();
		$term_ids = wp_get_post_terms( $post_id, self::TAXONOMY_NAME, array( 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			$all_roles = self::get_all_volunteer_roles();
			foreach ( $term_ids as $id ) {
				if ( isset( $all_roles[ $id ] ) ) {
					$result[] = $all_roles[ $id ];
				} // endif
			} // endfor
		} // endif
		return $result;
	} // function

	/**
	 * Assign the volunteer roles for the specified post
	 *
	 * @param	int								$post_id			The ID of the post whose volunteer roles are to be assigned.
	 * @param	Volunteer_Role[]|Volunteer_Role	$volunteer_roles	The array of volunteer roles being assigned to this post.
	 * As a convenience, the caller may specify one object or an array of objects.
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function set_volunteer_roles_for_post( $post_id, $volunteer_roles ) {
		if ( ! is_array( $volunteer_roles ) ) {
			$volunteer_roles = array( $volunteer_roles );
		} // endif
		$new_ids = array();
		foreach( $volunteer_roles as $role ) {
			if ( $role instanceof Volunteer_Role ) {
				$new_ids[] = intval( $role->get_id() );
			} // endif
		} // endif
		if ( empty( $new_ids ) ) {
			// If the set of new ids is empty then that means to unset or remove the fixer station
			wp_delete_object_term_relationships( $post_id, Volunteer_Role::TAXONOMY_NAME );
		} else {
			wp_set_post_terms( $post_id, $new_ids, Volunteer_Role::TAXONOMY_NAME );
		} // endif
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
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this volunteer role
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->term_id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this volunteer role, e.g. Clothing
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the slug of this object.  This is used to search for posts that refer to this volunteer role.
	 *
	 * @return	string	The slug of this volunteer role, e.g. greeter
	 *
	 * @since v0.1.0
	 */
	public function get_slug() {
		return $this->slug;
	} // function

	/**
	 * Get the count of items assigned to this volunteer role
	 *
	 * @return	int		The count of items using this volunteer role
	 *
	 * @since v0.1.0
	 */
	public function get_count() {
		return $this->count;
	} // function

	/**
	 * Get the count of Volunteers whose preferred role is this one
	 *
	 * @return	int		The count of Volunteers whose preferred role is this one
	 *
	 * @since v0.1.0
	 */
	public function get_volunteer_count() {
		$args = array(
			'post_type'			=> Volunteer::POST_TYPE,
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
	 * Get the colour for this volunteer rp;es.
	 * This is used to colour code volunteer roles on charts and graphs.
	 *
	 * @return	string	The CSS colour used to indicate this volunteer role.
	 *
	 * @since v0.1.0
	 */
	public function get_colour() {
		if ( ! isset( $this->colour ) )  {
			$meta = get_term_meta( $this->get_id(), self::COLOUR_META_KEY, $single = TRUE);
			if ( ( $meta !== FALSE ) && ( $meta !== NULL ) ) {
				$this->colour = $meta;
			} // endif
		} // endif
		return $this->colour;
	} // function

	/**
	 * Set the colour for this volunteer role
	 *
	 * @param	string	$colour		The CSS colour used to indicate this volunteer role.
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
	 * Get the array of external names for this volunteer role.
	 * This is used to allow mapping external names like "Food" to internal item types like "Refreshments"
	 *
	 * @return	string[]	The array of external names used to reference this role
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
	 * Set the external names for this role
	 *
	 * @param	string[]	$external_names		The array of external names used to refer to this role
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
	 * Register this custom taxonomy
	 *
	 * This method is called by the plugin controller
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function register() {
		// Register new taxonomy
		// Note that the 'name' label is used as the metabox title and volunteer roles are usually multi-select
		//  so using the plural for the name works best
		$labels = array(
				'name' 							=> __( 'Volunteer Roles','reg-man-rc' ),
				'singular_name'					=> __( 'Volunteer Role', 'reg-man-rc' ),
				'menu_name'						=> __( 'Volunteer Roles', 'reg-man-rc' ),
				'all_items'						=> __( 'All Volunteer Roles', 'reg-man-rc' ),
				'edit_item'						=> __( 'Edit Volunteer Role', 'reg-man-rc' ),
				'view_item'						=> __( 'View Volunteer Role', 'reg-man-rc' ),
				'update_item'					=> __( 'Update Volunteer Role', 'reg-man-rc' ),
				'add_new_item'					=> __( 'Add New Volunteer Role', 'reg-man-rc' ),
				'new_item_name'					=> __( 'New Volunteer Role', 'reg-man-rc' ),
				'parent_item'					=> NULL,
				'parent_item_colon'				=> NULL,
				'search_items'					=> NULL,// __( 'Search Volunteer Roles', 'reg-man-rc' ),
				'popular_items'					=> NULL,// __( 'Popular Volunteer Roles', 'reg-man-rc' ),
				'separate_items_with_commas'	=> NULL,// __( 'Separate roles with commas', 'reg-man-rc' ),
				'add_or_remove_items'			=> NULL,// __( 'Add or remove roles', 'reg-man-rc' ),
				'choose_from_most_used'			=> NULL,// __( 'Choose from the most used roles', 'reg-man-rc' ),
				'not_found'						=> NULL,// __( 'Volunteer Role not found', 'reg-man-rc' ),
		);

		$args = array(
				'labels'				=> $labels,
				'description'			=> __( 'A role for a volunteer at an event, e.g. Registration', 'reg-man-rc' ),
				'hierarchical'			=> FALSE, // Does each one have a parent and a heirarchy?
				'public'				=> TRUE, // whether it's intended for public use - must be TRUE to enable polylang translations
				'publicly_queryable'	=> FALSE, // can it be queried
				'show_ui'				=> TRUE, // does it have a UI for managing it
				// Note that if we set show_in_rest to TRUE then the block editor will show a metabox in post types
				//  that use this taxonom REGARDLESS of the setting for meta_box_cb
				// There is a way around that using the 'rest_prepare_taxonomy' filter if absolutely necessary
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_menu'			=> TRUE, // TRUE means show in submenu under its associated post type
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_admin_column'		=> TRUE, // show values as column in in admin post listing screens
				'show_tagcloud'			=> FALSE, // whether to allow the Tag Cloud widget to use this taxonomy
				'show_in_quick_edit'	=> FALSE, // whether to this taxonomy in the quick edit UI
				'meta_box_cb'			=> FALSE, // a callback for the metabox
				// 'update_count_callback'	=> array(__CLASS__, 'update_post_term_count'),
				'query_var'				=> TRUE,
				'rewrite'				=> FALSE,
		);

		$post_types = array(
				Volunteer::POST_TYPE,
				Volunteer_Registration::POST_TYPE,
		);
		$taxonomy = register_taxonomy( self::TAXONOMY_NAME, $post_types, $args );

		if ( is_wp_error( $taxonomy ) ) {
			$msg = __( 'Failure to register taxonomy for Volunteer Roles', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $taxonomy );
		} else {
			// Note that I must register the taxonomy before assigning a notification bubble to its menu item
			// This is because I need to check if the taxonomy is empty to determine if a bubble is needed
			//  but I can't check if it's empty until it has been registered
			$notice_count = Volunteer_Role_Admin_View::get_is_show_create_defaults_admin_notice() ? 1 : 0;
			if ( $notice_count > 0 ) {
				$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
				$taxonomy->labels->menu_name .= $notification_bubble;
			} // endif
		} // endif

	} // function


	/**
	 * Returns data that represent the default categories used to perform initial configuration of the plugin.
	 * @return	string[][]
	 */
	public static function get_default_volunteer_roles_data() {
		$result = array();
		$result[] = array(
			'name'			=> __( 'Setup & Cleanup', 'reg-man-rc' ),
			'description'	=> __( 'Help setup the venue before the event and clean up after', 'reg-man-rc' ),
			'colour'		=> '#dbdd46',
		);
		$result[] = array(
			'name'			=> __( 'Registration', 'reg-man-rc' ),
			'description'	=> __( 'Register visitors and their items', 'reg-man-rc' ),
			'colour'		=> '#0f6eb0',
		);
		$result[] = array(
			'name'			=> __( 'Greeter', 'reg-man-rc' ),
			'description'	=> __( 'Meet, greet and direct visitors', 'reg-man-rc' ),
			'colour'		=> '#610f40',
		);
		$result[] = array(
			'name'			=> __( 'Refreshments', 'reg-man-rc' ),
			'description'	=> __( 'Manage food and beverages', 'reg-man-rc' ),
			'colour'		=> '#1a9d9a',
		);
		$result[] = array(
			'name'			=> __( 'Photographer', 'reg-man-rc' ),
			'description'	=> __( 'Take photos and post to website and social media', 'reg-man-rc' ),
			'colour'		=> '#fb9d19',
		);
		return $result;
	} // function

	/**
	 * Get the html content shown to the administrator in the "About" help for this taxonomy
	 * @return string
	 */
	public static function get_about_content() {
		ob_start();
			$heading = __( 'About volunteer roles', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A volunteer role is a task or set of tasks for a volunteer at an event;' .
					' for example, Setup & Cleanup, Registration, or Photographer.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When a volunteer registers for an event they will specify which roles (if any) they prefer to perform.' .
					'  A volunteer may perform more than one role at an event; for example, Setup & Cleanup and Photographer.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'A fixer may register for a volunteer role like Setup & Cleanup (which is performed before and after the event)' .
					' and also register to repair items at a fixer station during the event.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Volunteer roles are colour coded for display in statistical charts.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			$result = ob_get_clean();
		return $result;
	} // function

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
				'taxonomy'		=> self::TAXONOMY_NAME,
				'hide_empty'	=> FALSE,
		);
		$query = new \WP_Term_Query( $args );
		foreach( $query->get_terms() as $term ){
			wp_delete_term( $term->term_id, self::TAXONOMY_NAME );
		} // endfor
		unregister_taxonomy( self::TAXONOMY_NAME );
	} // function

} // class
