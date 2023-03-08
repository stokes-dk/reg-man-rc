<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\View\Admin\Item_Suggestion_Admin_View;

/**
 * An autocomplete suggestion for an item.
 *
 * An instance of this class contains the information related to an item suggestion
 *  including its description, alternate descriptions and item type.
 * For example, if the user types "light" the suggestions might include:
 *   Lamp (Appliance)
 *   Bike light (Bike)
 * This assumes that "light" is an alternate description for the "Lamp" suggestion which has
 *  an item type of "Appliance".
 *
 * @since v0.1.0
 *
 */
class Item_Suggestion {

	const POST_TYPE = 'reg-man-rc-item-sugg'; // Note there is a 20 character limit for post type

	private $post;
	private $item_desc;
	private $alt_desc_list_text;
	private $fixer_station;
	private $fixer_station_name;
	private $item_type;
	private $item_type_name;
	private $label;

	private static $DEFAULT_DATA; // Used to store default suggestion data when needed

	/**
	 * Instantiate and return a new instance of this class using the specified post data
	 *
	 * @param	\WP_Post	$post	The post data for the new venue
	 * @return	Item_Suggestion
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post so I can't process it
		} else {
			$result = new self();
			$result->post = $post;
		} // endif
		return $result;
	} // function

	/**
	 * Get all item suggestions.
	 *
	 * This method will return an array of instances of this class describing all item suggestions defined under this plugin.
	 *
	 * @return	Item_Suggestion[]
	 */
	public static function get_all_item_suggestions() {
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
			$item_suggestion = self::instantiate_from_post( $post );
			if ( $item_suggestion !== NULL ) {
				$result[] = $item_suggestion;
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
		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
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
	 * Get a single item suggestion using its ID
	 *
	 * This method will return a single instance of this class for the item suggestion with the specified ID.
	 * If the ID is not found, this method will return NULL
	 *
	 * @param	int|string	$item_suggestion_id	The ID of the item suggestion to be returned
	 * @return	Item_Suggestion
	 */
	public static function get_item_suggestion_by_id( $item_suggestion_id ) {
		$post = get_post( $item_suggestion_id );
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
	 * Get the autocomplete suggestions list as an array of arrays like:
	 *   array(
	 *   	array( 'item_desc' => 'Toaster', 'item_alt_desc' => '', 'type_name' => 'Appliance', 'type_id' => 12 ),
	 *   	array( 'item_desc' => 'Lamp', 'item_alt_desc' => 'Light', 'type_name' => 'Appliance', 'type_id' => 12 )
	 *   )
	 */
	public static function get_item_autocomplete_suggestions() {
		// FIXME - These need to contain Item Type and Fixer Station
		// We probably need to join the terms tables twice to get both
		// See Item Statistics to see how to join tables properly, i.e. term_rels in ( term_tax_id array )
		global $wpdb;

		$posts_table = $wpdb->posts;
		$meta_table = $wpdb->postmeta;
		$term_rels_table = $wpdb->term_relationships;
		$term_tax_table = $wpdb->term_taxonomy;
		$terms_table = $wpdb->terms;
		$item_type_tax_name = Item_Type::TAXONOMY_NAME;
		$item_type_tt_ids = Item_Type::get_all_term_taxonomy_ids();
		$station_tax_name = Fixer_Station::TAXONOMY_NAME;
		$station_tt_ids = Fixer_Station::get_all_term_taxonomy_ids();

		$item_post_type = self::POST_TYPE;

		$select = 'post_title AS item_desc, post_content AS item_alt_desc, ' .
					'item_type_tr.term_taxonomy_id as type_id, ' .
					'station_tr.term_taxonomy_id as station_id';
//					'item_type_t.name as type_name, item_type_tr.term_taxonomy_id as type_id, ' .
//					'station_t.name as type_name, station_tr.term_taxonomy_id as station_id';
		$from =	" $posts_table AS p ";
		// Join term relationships for item type
		$from .=
			" LEFT JOIN $term_rels_table AS item_type_tr ON p.ID = item_type_tr.object_id " .
			'   AND item_type_tr.term_taxonomy_id IN ( ' . implode( ',', $item_type_tt_ids ) . ' ) ' .
			" LEFT JOIN $term_tax_table AS item_type_tt ON item_type_tt.term_taxonomy_id = item_type_tr.term_taxonomy_id " .
			" LEFT JOIN $terms_table AS item_type_t ON item_type_t.term_id = item_type_tt.term_id ";
		// Join term relationships for fixer station
		$from .=
			" LEFT JOIN $term_rels_table AS station_tr ON p.ID = station_tr.object_id " .
			'   AND station_tr.term_taxonomy_id IN ( ' . implode( ',', $station_tt_ids ) . ' ) ' .
			" LEFT JOIN $term_tax_table AS station_tt ON station_tt.term_taxonomy_id = station_tr.term_taxonomy_id " .
			" LEFT JOIN $terms_table AS station_t ON station_t.term_id = station_tt.term_id ";

		$where = " p.post_type = '$item_post_type' AND p.post_status ='publish' ";
		$order_by = ' item_desc ';

		$query = "SELECT $select FROM $from WHERE $where ORDER BY $order_by";

		$data_array = $wpdb->get_results( $query, ARRAY_A );

		$result = is_array( $data_array ) ? $data_array : array();

		return $result;
	} // function


	/**
	 * Create a new item suggestion
	 *
	 * @param	string				$item_desc		The description for the new item suggestion, e.g. "Blender"
	 * @param	string|string[]		$alt_desc		A string or array of strings containing the alternate descriptions for the suggestion, e.g. [ 'Osterizer', 'Magic bullet' ]
	 * @param	Item_Typoe			$item_type		The item type for the item suggestion
	 * @param	Fixer_Station		$fixer_station	The fixer station for item suggestion
	 * @return	Item_Suggestion|NULL
	 */
	public static function create_new_item_suggestion( $item_desc, $alt_desc, $item_type, $fixer_station ) {
//		Error_Log::var_dump( $item_desc, $alt_desc, $item_type, $fixer_station );
		$args = array(
				'post_title'	=> $item_desc,
				'post_status'	=> 'publish',
				'post_type'		=> self::POST_TYPE,
		);
		if ( ! empty( $alt_desc ) ) {
			$alt_desc_text = is_array( $alt_desc ) ? implode( ', ', $alt_desc ) : $alt_desc;
			$args[ 'post_content' ] = $alt_desc_text;
		} // endif

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( 'Unable to create item suggestion', $post_id );
			$result = NULL;
		} else {
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			if ( ! empty( $result ) ) {
				$result->set_item_type( $item_type );
				$result->set_fixer_station( $fixer_station );
			} // endif
		} // endif

		return $result;

	} // function


	/**
	 * Get the URL for the admin UI for this taxonomy
	 * @return string
	 */
	public static function get_admin_url() {
		$post_type = self::POST_TYPE;
		$base_url = admin_url( 'edit.php' );
		$result = add_query_arg( array( 'post_type' => $post_type ), $base_url );
		return $result;
	} // function



	/**
	 * Get the post for this item suggestion.
	 * @return	\WP_Post		The post for this item suggestion
	 * @since v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this item suggestion.
	 * @return	int		The post ID for this item suggestion
	 * @since v0.1.0
	 */
	private function get_post_id() {
		return $this->get_post()->ID;
	} // function

	/**
	 * Get the post status for this item suggestion.
	 * @return	int		The post status for this item suggestion
	 * @since v0.1.0
	 */
	private function get_post_status() {
		return $this->get_post()->post_status;
	} // function

	/**
	 * Get the ID of this item suggestion.  The ID is the post ID.
	 * @return	int		The ID for the item suggestion
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->get_post_id();
	} // function

	/**
	 * Get the description of the item represented by this suggestion.
	 * The description is the post title.
	 * @return	string		The description of the item suggestion
	 * @since v0.1.0
	 */
	public function get_description() {
		if ( ! isset( $this->item_desc ) ) {
			$this->item_desc = $this->get_post()->post_title;
		} // endif
		return $this->item_desc;
	} // function

	/**
	 * Get the comma-separated list of alternate descriptions for this item suggestion.
	 * The alternate descriptions are used to match item suggestions based on user input.
	 * For example, if the user enters "light" and there exists an item suggestion "Lamp" containing
	 *  alternate descriptions "Desk Lamp, Light" then it will match the user input and be
	 *  presented as an autocomplete suggestion.
	 * The alternate descriptions are stored in the post content as a comma-separated list.
	 * @return	string	The list of alternate descriptions for this item suggestion.
	 * @since v0.1.0
	 */
	public function get_alternate_description_list_text() {
		if ( ! isset( $this->alt_desc_list_text ) ) {
			$this->alt_desc_list_text = $this->get_post()->post_content;
		} // endif
		return $this->alt_desc_list_text;
	} // function

	/**
	 * Get the fixer Station for this item suggestion
	 * @return	Fixer_Station	The fixer station assigned to this item suggestion
	 * @since	v0.1.0
	 */
	public function get_fixer_station() {
		if ( ! isset( $this->fixer_station ) ) {
			$stations = Fixer_Station::get_fixer_stations_for_post( $this->get_post_id() );
			$this->fixer_station = ( is_array( $stations ) && isset( $stations[ 0 ] ) ) ? $stations[ 0 ] : NULL;
		} // endif
		return $this->fixer_station;
	} // function

	/**
	 * Assign the fixer station for this item suggestion
	 * @param	Fixer_Station	$fixer_station	The fixer station to be assigned to this item suggestion
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_fixer_station( $fixer_station ) {
		$fixer_station_id = ( $fixer_station instanceof Fixer_Station ) ? $fixer_station->get_id() : NULL;
		$suggestion_id = $this->get_id();
		if ( $fixer_station_id == NULL ) {
			// If the new station id is NULL then that means to unset or remove the current setting
			wp_delete_object_term_relationships( $suggestion_id, Fixer_Station::TAXONOMY_NAME );
		} else {
			$fixer_station_id = intval( $fixer_station_id );
			$terms_array = array ( $fixer_station_id );
			wp_set_post_terms( $suggestion_id, $terms_array, Fixer_Station::TAXONOMY_NAME );
		} // endif
		$this->fixer_station = NULL; // reset my internal var so it can be re-acquired
		$this->fixer_station_name = NULL; // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get the name of this item suggestion's fixer station, like 'Appliances & Housewares'.
	 * @return	string	This item suggestion's fixer station name
	 * @since	v0.1.0
	 */
	public function get_fixer_station_name() {
		if ( ! isset( $this->fixer_station_name ) ) {
			$station = $this->get_fixer_station();
			$this->fixer_station_name = isset( $station ) ? $station->get_name() : NULL;
		} // endif
		return $this->fixer_station_name;
	} // function


	/**
	 * Get the item type for this item suggestion
	 * @return	Item_Type	The type of this item suggestion
	 * @since	v0.1.0
	 */
	public function get_item_type() {
		if ( ! isset( $this->item_type ) ) {
			$item_types = Item_Type::get_item_types_for_post( $this->get_post_id() );
			$this->item_type = ( is_array( $item_types ) && isset( $item_types[ 0 ] ) ) ? $item_types[ 0 ] : NULL;
		} // endif
		return $this->item_type;
	} // function

	/**
	 * Assign the item type for this item suggestion
	 * @param	Item_Type	$item_type	The item type to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_item_type( $item_type ) {
		$item_type_id = ( $item_type instanceof Item_Type ) ? $item_type->get_id() : NULL;
		$suggestion_id = $this->get_id();
		if ( $item_type_id == NULL ) {
			// If the new type id is NULL then that means to unset or remove the current setting
			wp_delete_object_term_relationships( $suggestion_id, Item_Type::TAXONOMY_NAME );
		} else {
			$item_type_id = intval( $item_type_id );
			$terms_array = array ( $item_type_id );
			wp_set_post_terms( $suggestion_id, $terms_array, Item_Type::TAXONOMY_NAME );
		} // endif
		$this->item_type = NULL; // reset my internal var so it can be re-acquired
		$this->item_type_name = NULL; // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get the name of this item suggestion's type, like 'Appliance'.
	 * @return	string	This item suggestion's type name
	 * @since	v0.1.0
	 */
	public function get_item_type_name() {
		if ( ! isset( $this->item_type_name ) ) {
			$item_type = $this->get_item_type();
			$this->item_type_name = isset( $item_type ) ? $item_type->get_name() : NULL;
		} // endif
		return $this->item_type_name;
	} // function


	/**
	 * Get a label that can be used to represent this suggestion.
	 * @return	string		A label for this suggestion, e.g. "Toaster (Appliance)"
	 * @since v0.1.0
	 */
	public function get_label() {
		if ( ! isset( $this->label ) ) {
			$desc = $this->get_description();
			$type_name = $this->get_item_type_name();
			/* translators: %1$s is an item suggestion description, e.g. "Toaster", %2$s is its item type name, e.g. "Appliance" */
			$format = _x( '%1$s (%2$s)', 'A format for showing an item suggestion including its description and item type', 'reg-man-rc' );
			$result = sprintf( $format, $desc, $type_name );
		} // endif
		return $result;
	} // function

	/**
	 *  Register the custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {
		$labels = array(
				'name'					=> _x( 'Item Suggestions', 'Item Suggestion post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Item Suggestion', 'Item Suggestion post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Item Suggestion' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Item Suggestion', 'reg-man-rc'),
				'new_item'				=> __( 'New Item Suggestion', 'reg-man-rc'),
				'all_items'				=> __( 'Item Suggestions', 'reg-man-rc'),
				'view_item'				=> __( 'View Item Suggestion', 'reg-man-rc'),
				'search_items'			=> __( 'Search Item Suggestions', 'reg-man-rc'),
				'not_found'				=> __( 'No items found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'No items found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __('Item Suggestions', 'reg-man-rc')
		);
		$capability_singular = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Item Suggestions', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.
				'menu_icon'				=> 'dashicons-lightbulb',
				'hierarchical'			=> FALSE, // Can each post have a parent?
/**
 * supports options are	'title', 'editor' (post content), 'author', 'thumbnail', 'excerpt', 'trackbacks',
 *							'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats'
 */
				'supports'				=> array( 'title' ),
				'taxonomies'			=> array(
												Item_Type::TAXONOMY_NAME,
												Fixer_Station::TAXONOMY_NAME,
				),
				'has_archive'			=> FALSE, // is there an archive page?
				'rewrite'				=> FALSE,
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		$post_type = register_post_type( self::POST_TYPE, $args );

		if ( is_wp_error( $post_type ) ) {
			$msg = __( 'Failure to register post for Item Suggestion', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $post_type );
		} else {
			// Note that I must register the taxonomy before assigning a notification bubble to its menu item
			// This is because I need to check if the taxonomy is empty to determine if a bubble is needed
			//  but I can't check if it's empty until it has been registered
			$notice_count = Item_Suggestion_Admin_View::get_is_show_create_defaults_admin_notice() ? 1 : 0;
			if ( $notice_count > 0 ) {
				$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
				$post_type->labels->all_items .= $notification_bubble;
			} // endif
		} // endif
	} // function


	/**
	 * Get the html content shown to the administrator in the "About" help for this post type
	 * @return string
	 */
	public static function get_about_content() {
		ob_start();
			$heading = __( 'About item suggestions', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'Item suggestions help reduce typing when registering new items.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When you register an item and begin typing the description, the system' .
					' will find and display matching item suggestions.' .
					'  For example, if you type "light" the system may show item suggestions like "Lamp", "Bike light" and "Nightlight".',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'An item suggestion contains a description like "Lamp",' .
					' a comma-separated list of alternate descriptions like "Light, Desk lamp",' .
					' a default item type like "Electrical / Electronic",' .
					' and a default fixer station for the item like "Appliances & Housewares".',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Item suggestions will pop up when a user begins typing an item description during registration.' .
					'  Selecting a suggestion will fill in the fields for the item.' .
					'  After selection the fields may be modified if necessary.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Because item suggestions refer to fixer stations and item types, you should create those first ' .
					' before creating your item suggestions.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Returns data that represent the default item suggestions used to perform initial configuration of the plugin.
	 * @return	string[][]
	 */
	public static function get_default_suggestion_data() {
		if ( ! isset( self::$DEFAULT_DATA ) ) {
			self::$DEFAULT_DATA = Item_Suggestion_Data::get_default_suggestion_data();
		} // endif
		return self::$DEFAULT_DATA;
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