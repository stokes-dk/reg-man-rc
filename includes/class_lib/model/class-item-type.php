<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Item_Type_Admin_View;


/**
 * An instance of this class represents a type used to classify items.
 *
 * Some typical item types are: Appliance, Electronics, Clothing, Jewellery, and Bike.
 *
 * @since	v0.1.0
 *
 */
class Item_Type {

	const TAXONOMY_NAME = 'reg-man-rc-item-type';

	const UNSPECIFIED_ITEM_TYPE_ID	= 0; // An ID used to indicate that the item type is not specified

	const COLOUR_META_KEY			= self::TAXONOMY_NAME . '-colour';
	const ORDER_INDEX_META_KEY 		= self::TAXONOMY_NAME . '-order-index';
	const EXTERNAL_NAMES_META_KEY	= self::TAXONOMY_NAME . '-external-names';

	private $term_id;
	private $name;
	private $slug;
	private $description;
	private $fixer_station;
	private $count;
	private $colour;
	private $external_names; // An array of string names used to refer to this item type externally
	// $external_names are used to map item types from external systems like our legacy data to current item types
	// E.g. "Household Item" => "Housewares"

	private static $all_item_types; // an arry of all item types used for caching and finding types by id
	private static $all_types_by_name; // an array of all item types indexed by name used to find types by name
	private static $term_tax_ids_array; // an array of all term taxonomy IDs in this taxonomy

	private function __construct() { }

	/**
	 * Create a new instance of this class using the supplied data object
	 *
	 * @param	\WP_Term		$term	The object containing the data for this instance
	 * @return	Item_Type		An instance of this class
	 *
	 * @since v0.1.0
	 */
	private static function instantiate_from_term( $term ) {
		if ( !is_object( $term ) ) {
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
	 * Create a new item type
	 * @param string		$name
	 * @param string		$description
	 * @param string		$colour
	 * @return NULL|Item_Type	The newly created item type or NULL if there was an error
	 */
	public static function create_item_type( $name, $description, $colour ) {
		$existing = self::get_item_type_by_name( $name );
		if ( ! empty( $existing ) ) {
			/* translators: %s is the name of an item type */
			$err_msg = sprintf( __( 'Failed to create item type: %s because the name already exists', 'reg-man-rc' ), $name );
			Error_Log::log_msg( $err_msg, $name );
			$result = NULL;
		} else {
			$args = array();
			if ( ! empty( $description ) ) {
				$args[ 'description' ] = $description;
			} // endif
			$insert_result = wp_insert_term( $name, self::TAXONOMY_NAME, $args );
			if ( is_wp_error( $insert_result ) ) {
				/* translators: %s is the name of an item type */
				$err_msg = sprintf( __( 'Failed to insert item type: %s', 'reg-man-rc' ), $name );
				Error_Log::log_wp_error( $err_msg, $insert_result );
				$result = NULL;
			} else {
				$term_id = $insert_result[ 'term_id' ];
				$term = get_term( $term_id, self::TAXONOMY_NAME );
				$result = self::instantiate_from_term( $term );
				if ( ! empty( $colour ) ) {
					$result->set_colour( $colour );
				} // endif
				self::$all_item_types = NULL; // Allow this to be re-acquired
				self::$all_types_by_name = NULL; // Allow this to be re-acquired
			} // endif
		} // endif
		return $result;
	} // function


	/**
	 * Get all item types defined to the system
	 *
	 * @return	Item_Type[]	An array of instances of this class representing all item types
	 *
	 * @since v0.1.0
	 */
	public static function get_all_item_types() {
		if ( ! isset( self::$all_item_types ) ) {
			self::$all_item_types = array();
			$term_array = get_terms( array(
					'taxonomy'		=> self::TAXONOMY_NAME,
					'hide_empty'	=> FALSE // We want all, including those used by no post
			) );
			foreach ( $term_array as $term ) {
				$term = self::instantiate_from_term( $term );
				if ( $term !== NULL ) {
					self::$all_item_types[ $term->term_id ] = $term;
				} // endif
				/* TODO: The following is to support custom ordering */
//				uasort( self::$all_item_types, function( $type1, $type2 ) {
//					return ( $type1->get_order_index() <=> $type2->get_order_index() );
//				});
			} // endfor
		} // endif
		return self::$all_item_types;
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


	private static function get_all_item_types_by_name_array() {
		if ( ! isset( self::$all_types_by_name ) ) {
			$all_types = self::get_all_item_types();
			self::$all_types_by_name = array();
			foreach ( $all_types as $item_type ) {
				$name = $item_type->get_name();
				$name = wp_specialchars_decode( $name ); // A & B should be found using A &amp; B and vice-versa
				self::$all_types_by_name[ $name ] = $item_type;
				// we will also allow callers to find an item type using one of its external names
				$ext_names_array = $item_type->get_external_names();
				foreach ( $ext_names_array as $external_name ) {
					$external_name = wp_specialchars_decode( $external_name );
					if ( ! isset( self::$all_types_by_name[ $external_name ] ) ) {
						// if an external name matches an exiting internal name then don't overwrite it
						self::$all_types_by_name[ $external_name ] = $item_type;
					} // endif
				} // endfor
			} // endfor
		} // endif
		return self::$all_types_by_name;
	} // function

	/**
	 * Get the item type with the specified name
	 *
	 * @param	string	$type_name	The name of the item type to be returned
	 * @return	Item_Type	An instance of this class with the specified name or NULL if the item type does not exist
	 * Note that this will match using external names as well the internal name.
	 * Use $result->get_name() to get the internal name of the matching item type
	 *
	 * @since v0.1.0
	 */
	public static function get_item_type_by_name( $type_name ) {
		$type_name = wp_specialchars_decode( $type_name ); // A & B should be found using A &amp; B and vice-versa
		$types_array = self::get_all_item_types_by_name_array();
		$result = ( ! empty( $type_name ) && isset( $types_array[ $type_name ] ) ) ? $types_array[ $type_name ] : NULL;
		return $result;
	} // function

	/**
	 * Get the item type with the specified ID
	 *
	 * @param	int|string	$item_type_id	The ID of the item type to be returned
	 * @return	Item_Type	An instance of this class with the specified ID or NULL if the item type does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_item_type_by_id( $item_type_id ) {
		$all_types = self::get_all_item_types();
		$result = isset( $all_types[ $item_type_id ] ) ? $all_types[ $item_type_id ] : NULL;
		return $result;
	} // function

	/**
	 * Get the item types assigned to a specific post like an internal event descriptor or an item.
	 *
	 * Note that a post like Item will expect 0 or 1 item types but this method returns an array.
	 * In that case the caller should ignore any array entries beyond the first element and the Item class
	 *  should take repsonsibility for ensuring that there is only one item type associated with each item.
	 *
	 * @param	int|string	$post_id	The ID of the post whose fixer stations are to be returned
	 * @return	Item_Type[]				An array of instances of this class representing all item types assigned to the specified post
	 *
	 * @since v0.1.0
	 */
	public static function get_item_types_for_post( $post_id ) {
		$result = array();
		$term_ids = wp_get_post_terms( $post_id, self::TAXONOMY_NAME, array( 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			$all_types = self::get_all_item_types();
			foreach ( $term_ids as $id ) {
				if ( isset( $all_types[ $id ] ) ) {
					$result[] = $all_types[ $id ];
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
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this item type
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->term_id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this item type
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
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
	 * Get the count of terms in this taxonomy
	 *
	 * @return	int		The count of terms in this taxonomy
	 *
	 * @since v0.1.0
	 */
	private function get_count() {
		return $this->count;
	} // function


	/**
	 * Get the count of Items with this item type
	 *
	 * @return	int		The count of Items assigned this item type
	 *
	 * @since v0.1.0
	 */
	public function get_item_count() {
		$args = array(
			'post_type'			=> Item::POST_TYPE,
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

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;

	} // function
	
	/**
	 * Get the colour for this item type.
	 * This is used to colour code graphs.
	 *
	 * @return	string	The CSS colour used to indicate this item type.
	 *
	 * @since v0.1.0
	 */
	public function get_colour() {
		if ( ! isset( $this->colour ) )  {
			$meta = get_term_meta( $this->get_id(), self::COLOUR_META_KEY, $single = TRUE );
			if ( ( $meta !== FALSE ) && ( $meta !== NULL ) ) {
				$this->colour = $meta;
			} // endif
		} // endif
		return $this->colour;
	} // function

	/**
	 * Set the colour for this item type
	 *
	 * @param	string	$colour		The CSS colour used to indicate this item type.
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
	 * Get the array of external names for this item type.
	 * This is used to allow mapping external names like "Household Item" to internal item types like "Housewares"
	 *
	 * @return	string[]	The array of external names used to reference this item type
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
	 * Set the external names for this item type
	 *
	 * @param	string[]	$external_names		The array of external names used to refer to this item type
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
	 * Handle plugin initialization
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
				'name' 							=> __( 'Item Type','reg-man-rc' ),
				'singular_name'					=> __( 'Item Type', 'reg-man-rc' ),
				'menu_name'						=> __( 'Item Types', 'reg-man-rc' ),
				'all_items'						=> __( 'All Item Types', 'reg-man-rc' ),
				'edit_item'						=> __( 'Edit Item Type', 'reg-man-rc' ),
				'view_item'						=> __( 'View Item Type', 'reg-man-rc' ),
				'update_item'					=> __( 'Update Item Type', 'reg-man-rc' ),
				'add_new_item'					=> __( 'Add New Item Type', 'reg-man-rc' ),
				'new_item_name'					=> __( 'New Item Type', 'reg-man-rc' ),
				'parent_item'					=> NULL,
				'parent_item_colon'				=> NULL,
				'search_items'					=> NULL,// __( 'Search Item Types', 'reg-man-rc' ),
				'popular_items'					=> NULL,// __( 'Popular Item Types', 'reg-man-rc' ),
				'separate_items_with_commas'	=> NULL,// __( 'Separate item types with commas', 'reg-man-rc' ),
				'add_or_remove_items'			=> NULL,// __( 'Add or remove item types', 'reg-man-rc' ),
				'choose_from_most_used'			=> NULL,// __( 'Choose from the most used item types', 'reg-man-rc' ),
				'not_found'						=> NULL,// __( 'Item Type not found', 'reg-man-rc' ),
		);

		$args = array(
				'labels'				=> $labels,
				'description'			=> __( 'Item\'s type', 'reg-man-rc' ),
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
				'show_admin_column'		=> TRUE,	// show values as column in in admin post listing screens
				'show_tagcloud'			=> FALSE,	// whether to allow the Tag Cloud widget to use this taxonomy
				'show_in_quick_edit'	=> FALSE,	// whether to show default taxonomy editor in the quick edit UI
				'meta_box_cb'			=> FALSE, // a callback for the metabox, FALSE means don't show one at all
				// 'update_count_callback'	=> array(__CLASS__, 'update_post_term_count'),
				'query_var'				=> TRUE,
				'rewrite'				=> FALSE,
		);
		$taxonomy = register_taxonomy( self::TAXONOMY_NAME, Item::POST_TYPE, $args );

		if ( is_wp_error( $taxonomy ) ) {
			$msg = __( 'Failure to register taxonomy for Item Types', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $taxonomy );
		} else {
			// Note that I must register the taxonomy before assigning a notification bubble to its menu item
			// This is because I need to check if the taxonomy is empty to determine if a bubble is needed
			//  but I can't check if it's empty until it has been registered
			$notice_count = Item_Type_Admin_View::get_is_show_create_defaults_admin_notice() ? 1 : 0;
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
	public static function get_default_types_data() {
		$result = array();
		$result[] = array(
			'name'			=> __( 'Electrical / Electronic', 'reg-man-rc' ),
			'description'	=> __( 'An item that requires electricity to operate including corded, battery or solar-powered items', 'reg-man-rc' ),
			'fixer_station'	=> '',
			'colour'		=> '#fb9d19',
		);
		$result[] = array(
			'name'			=> __( 'Non-Electrical', 'reg-man-rc' ),
			'description'	=> __( 'An item that does not require electricity', 'reg-man-rc' ),
			'fixer_station'	=> '',
			'colour'		=> '#1a9d9a',
		);

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
