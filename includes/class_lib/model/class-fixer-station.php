<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Fixer_Station_Admin_View;
use Reg_Man_RC\Control\Admin\Fixer_Station_Admin_Controller;

/**
 * An instance of this class represents a fixer station.
 *
 * @since	v0.1.0
 *
 */
class Fixer_Station {

	const TAXONOMY_NAME				= 'reg-man-rc-fixer-station';

	const UNSPECIFIED_FIXER_STATION_ID	= 0; // An ID used to indicate that the fixer station is not specified

	const COLOUR_META_KEY			= self::TAXONOMY_NAME . '-colour';
	const ICON_ATTACH_ID_META_KEY	= self::TAXONOMY_NAME . '-icon-id';
	const EXTERNAL_NAMES_META_KEY	= self::TAXONOMY_NAME . '-external-names';

	// Default Fixer Stations come with their own icon.  Those icons are loaded into the media library as attachments.
	// Each attchment contains metadata to indicate which fixer station the icon is used for.
	// The following meta key is used to store the station name in the icon attachment
	const ICON_STATION_NAME_META_KEY	= self::TAXONOMY_NAME . '-icon-for';

	private $term_id;
	private $name;
	private $slug;
	private $description;
	private $count;
	private $item_types_array;
	private $colour;
	private $icon_attachment_id;
	private $icon_url;
	private $external_names; // An array of string names used to refer to this fixer station externally
	// $external_names are used to map item types from external systems like our legacy data to internal names

	private static $types_mapping;
	private static $all_stations; // A cached array of all fixer stations indexed by their ID
	private static $all_stations_by_name; // an array of all fixer stations indexed by name used to find stations by name
	private static $term_tax_ids_array; // an array of all term taxonomy IDs in this taxonomy

	private function __construct() { }

	/**
	 * Create a new instance of this class using the supplied data object
	 *
	 * @param	WP_Term			$term	The object containing the data for this instance
	 * @return	Fixer_Station	An instance of this class
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
	 * Create a new item type
	 * @param string		$name
	 * @param string		$description
	 * @param Item_Type[]	$item_type_array
	 * @param string		$colour
	 * @return NULL|Fixer_Station	The newly created fixer station or NULL if there was an error
	 */
	public static function create_fixer_station( $name, $description, $colour ) {
		$existing = self::get_fixer_station_by_name( $name );
		if ( ! empty( $existing ) ) {
			/* translators: %s is the name of an fixer station */
			$err_msg = sprintf( __( 'Failed to create fixer station: %s because the name already exists', 'reg-man-rc' ), $name );
			Error_Log::log_msg( $err_msg, $name );
			$result = NULL;
		} else {
			$args = array();
			if ( ! empty( $description ) ) {
				$args[ 'description' ] = $description;
			} // endif
			$insert_result = wp_insert_term( $name, self::TAXONOMY_NAME, $args );
			if ( is_wp_error( $insert_result ) ) {
				/* translators: %s is the name of an fixer station */
				$err_msg = sprintf( __( 'Failed to insert fixer station: %s', 'reg-man-rc' ), $name );
				Error_Log::log_wp_error( $err_msg, $insert_result );
				$result = NULL;
			} else {
				$term_id = $insert_result[ 'term_id' ];
				$term = get_term( $term_id, self::TAXONOMY_NAME );
				$result = self::instantiate_from_term( $term );
				if ( ! empty( $colour ) ) {
					$result->set_colour( $colour );
				} // endif
				self::$all_stations = NULL; // Allow this to be re-acquired
				self::$all_stations_by_name = NULL; // Allow this to be re-acquired
			} // endif
		} // endif
		return $result;
	} // function


	/**
	 * Get all fixer stations defined to the system
	 *
	 * @return	Fixer_Station[]	An array of instances of this class representing all fixer stations
	 *
	 * @since v0.1.0
	 */
	public static function get_all_fixer_stations() {
//		Error_Log::log_backtrace();
		if ( ! isset( self::$all_stations ) ) {
			// Returns an array of instances of this class
			self::$all_stations = array();
			$terms_array = get_terms( array(
					'taxonomy'		=> self::TAXONOMY_NAME,
					'hide_empty'	=> FALSE // We want all, including those used by no post
			) );
			foreach ( $terms_array as $term ) {
				$station = self::instantiate_from_term( $term );
				if ( $station !== NULL ) {
					self::$all_stations[ $station->get_id() ] = $station;
				} // endif
			} // endfor
		} // endif
		return self::$all_stations;
	} // function

	/**
	 * Get all term taxonomy IDs for this taxonomy
	 *
	 * @return	int[]	An array of term taxonomy IDs for all the terms defined to this taxonomy
	 *
	 * @since v0.1.0
	 */
	public static function get_all_term_taxonomy_ids() {
//		Error_Log::log_backtrace();
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
	 * Get the fixer station with the specified ID
	 *
	 * @param	int|string		$fixer_station_id	The ID of the fixer station to be returned
	 * @return	Fixer_Station	An instance of this class with the specified ID or NULL if the fixer station does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_fixer_station_by_id( $fixer_station_id ) {
		$all_stations = self::get_all_fixer_stations();
		$result = isset( $all_stations[ $fixer_station_id ] ) ? $all_stations[ $fixer_station_id ] : NULL;
		return $result;
	} // function

	private static function get_all_fixer_station_by_name_array() {
		if ( ! isset( self::$all_stations_by_name ) ) {
			$all_stations = self::get_all_fixer_stations();
			self::$all_stations_by_name = array();
			foreach ( $all_stations as $station ) {
				$name = $station->get_name();
				$name = wp_specialchars_decode( $name ); // A & B should be found using A &amp; B and vice-versa
				self::$all_stations_by_name[ $name ] = $station;
				// we will also allow callers to find an fixer station using one of its external names
				$ext_names_array = $station->get_external_names();
				foreach ( $ext_names_array as $external_name ) {
					$external_name = wp_specialchars_decode( $external_name );
					if ( ! isset( self::$all_stations_by_name[ $external_name ] ) ) {
						// if an external name matches an exiting internal name then don't overwrite it
						self::$all_stations_by_name[ $external_name ] = $station;
					} // endif
				} // endfor
			} // endfor
		} // endif
		return self::$all_stations_by_name;
	} // function

	/**
	 * Get the fixer station with the specified name
	 *
	 * @param	string	$fixer_station_name	The name of the fixer station to be returned
	 * @return	Fixer_Station	An instance of this class with the specified name or NULL if the station does not exist
	 * Note that this will match using external names as well the internal name.
	 * Use $result->get_name() to get the internal name of the matching station
	 *
	 * @since v0.1.0
	 */
	public static function get_fixer_station_by_name( $fixer_station_name ) {
		$fixer_station_name = wp_specialchars_decode( $fixer_station_name ); // A & B should be found using A &amp; B and vice-versa
		$stations_array = self::get_all_fixer_station_by_name_array();
		$result = ( ! empty( $fixer_station_name ) && isset( $stations_array[ $fixer_station_name ] ) ) ? $stations_array[ $fixer_station_name ] : NULL;
		return $result;
	} // function

	/**
	 * Get the fixer station with the specified slug
	 *
	 * @param	string			$fixer_station_slug		The slug of the fixer station to be returned
	 * @return	Fixer_Station	An instance of this class with the specified slug or NULL if the fixer station does not exist
	 *
	 * @since v0.1.0
	 */
	public static function get_fixer_station_by_slug( $fixer_station_slug ) {
		// rather than constructing a new instance I'll just look in my cache for the one with the right slug

		$result = NULL; // We'll return this if we don't find the slug

		$all_stations = self::get_all_fixer_stations();

		foreach ( $all_stations as $station ) {
			if ( $station->slug === $fixer_station_slug ) {
				$result = $station;
				break;
			} // endif
		} // endfor

		return $result;

	} // function

	/**
	 * Get the fixer stations assigned to a specific post like an internal event descriptor or an item registration
	 *
	 * @param	int|string	$post_id	The ID of the post whose fixer stations are to be returned
	 * @return	Fixer_Station[]			An array of instances of this class representing all fixer stations assigned to the specified post
	 *
	 * @since v0.1.0
	 */
	public static function get_fixer_stations_for_post( $post_id ) {
		$result = array();
		$term_ids = wp_get_post_terms( $post_id, self::TAXONOMY_NAME, array( 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			$all_stations = self::get_all_fixer_stations();
			foreach ( $term_ids as $id ) {
				if ( isset( $all_stations[ $id ] ) ) {
					$result[] = $all_stations[ $id ];
				} // endif
			} // endfor
		} // endif
		return $result;
	} // function

	/**
	 * Assign the fixer stations for the specified post
	 *
	 * @param	int								$post_id		The ID of the post whose fixer stations are to be assigned.
	 * @param	Fixer_Station[]|Fixer_Station	$fixer_stations	The array of fixer stations to be assigned to this post.
	 * As a convenience, the caller may specify one object or an array of objects.
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public static function set_fixer_stations_for_post( $post_id, $fixer_stations ) {
		if ( ! is_array( $fixer_stations ) ) {
			$fixer_stations = array( $fixer_stations );
		} // endif
		$new_ids = array();
		foreach( $fixer_stations as $fixer_station ) {
			if ( $fixer_station instanceof Fixer_Station ) {
				$new_ids[] = intval( $fixer_station->get_id() );
			} // endif
		} // endif
		if ( empty( $new_ids ) ) {
			// If the set of new ids is empty then that means to unset or remove the fixer station
			wp_delete_object_term_relationships( $post_id, Fixer_Station::TAXONOMY_NAME );
		} else {
			wp_set_post_terms( $post_id, $new_ids, Fixer_Station::TAXONOMY_NAME );
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
	 * @return	int	The ID of this fixer station
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->term_id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this fixer station, e.g. Clothing
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
	 * Get the total count of custom posts using this fixer station, including Items and Events or any other posts using this term
	 *
	 * @return	int		The count of posts using this fixer station
	 *
	 * @since v0.1.0
	 */
	public function get_count() {
		return $this->count;
	} // function

	/**
	 * Get the count of Items using this fixer stations
	 *
	 * @return	int		The count of Items using this fixer station
	 *
	 * @since v0.1.0
	 */
	public function get_item_count() {
		$item_types = Item_Type::get_item_types_by_default_fixer_station( $this->get_id() );
		$item_type_ids = array();
		foreach ( $item_types as $type ) {
			$item_type_ids[] = $type->get_id();
		} // endfor

		$tax_query = array(
				'relation' => 'OR',

						array(
								'taxonomy'	=> self::TAXONOMY_NAME,
								'field'		=> 'id',
								'terms'		=> array( $this->get_id() )
						),

						array(
								'relation' => 'AND',
								array(
										'taxonomy'	=> self::TAXONOMY_NAME,
										'field'		=> 'id',
										'operator'	=> 'NOT EXISTS'
								),
								array(
										'taxonomy'	=> Item_Type::TAXONOMY_NAME,
										'field'		=> 'id',
										'terms'		=> $item_type_ids
								),
						)
			);

		$args = array(
			'post_type'			=> Item::POST_TYPE,
			'posts_per_page'	=> -1, // Get all posts
			'tax_query'			=> $tax_query,
		);
		$query = new \WP_Query( $args );
		$result = $query->found_posts;
		wp_reset_postdata(); // Required after using WP_Query()
		return $result;

	} // function

	/**
	 * Get the count of Internal_Event_Descriptors using this fixer stations
	 *
	 * @return	int		The count of Internal_Event_Descriptors using this fixer station
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
	 * Get the count of Volunteer records whose preferred fixer station is this one
	 *
	 * @return	int		The count of Volunteer records whose preferred fixer station is this one
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_count() {
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
	 * Get an array of item types where this fixer station is assigned as their default
	 *
	 * @return	Item_Type[]	An array of items whose default fixer station is this one
	 *
	 * @since v0.1.0
	 */
	public function get_item_types_array() {
		if ( !isset( $this->item_types_array ) ) {
			$mapping = self::get_item_types_mapping();
			$name = $this->get_name();
			$this->item_types_array = isset( $mapping[ $name ] ) ? $mapping[ $name ] : array();
		} // endif
		return $this->item_types_array;
	} // function

	/**
	 * Set the array of item types where this fixer station is assigned as their default
	 *
	 * @param	Item_Type[]		$item_types_array	An array of items whose default fixer station is to be assigned as this one
	 * @return	void
	 * @since v0.1.0
	 */
	public function set_item_types_array( $item_types_array ) {
		if ( $item_types_array === NULL ) {
			$item_types_array = array(); // NULL should be considered the same as an empty array
		} // endif
		if ( is_array( $item_types_array ) ) {
			// The easiest way to do this is to remove any old assignments and then create the new ones
			$station_name = $this->get_name();
			$my_types = self::get_item_types_array();
			foreach ( $my_types as $item_type ) {
				$item_type->set_fixer_station_id( NULL ); // This means remove the fixer station
			} // endfor
			$station_id = $this->get_id();
			foreach ( $item_types_array as $item_type_id ) {
				$item_type = Item_Type::get_item_type_by_id( $item_type_id );
				if ( !empty( $item_type ) ) {
					$item_type->set_fixer_station_id( $station_id );
				} // endif
			} // endfor
		} // endif
	} // function

	/**
	 * Get an array of mappings from fixer stations to the item types
	 *
	 * @return	array[string]Item_Type[]	An associative array mapping fixer station names to the
	 * set of item types who use it as their default
	 *
	 * @since v0.1.0
	 */
	private static function get_item_types_mapping() {
		if ( !isset( self::$types_mapping ) ) {
			self::$types_mapping = array();
			$types_array = Item_Type::get_all_item_types();
			foreach ( $types_array as $item_type ) {
				$station = $item_type->get_fixer_station();
				if ( !empty( $station ) ) {
					$station_name = $station->get_name();
					if ( !isset( self::$types_mapping[ $station_name ] ) ) {
						self::$types_mapping[ $station_name ] = array( $item_type );
					} else {
						array_push( self::$types_mapping[ $station_name ], $item_type );
					} // endif
				} // endif
			} // endfor
		} // endif
		return self::$types_mapping;
	} // function


	/**
	 * Get the icon attachment ID for this fixer station.
	 * This is used to graphically represent the fixer station in event details.
	 *
	 * @return	string	The attachment ID for the icon used to represent this fixer station.
	 *
	 * @since v0.1.0
	 */
	public function get_icon_attachment_id() {
		if ( ! isset( $this->icon_attachment_id ) )  {
			$meta = get_term_meta( $this->get_id(), self::ICON_ATTACH_ID_META_KEY, $single = TRUE);
			if ( ( $meta !== FALSE ) && ( $meta !== NULL ) ) {
				$this->icon_attachment_id = $meta;
			} // endif
		} // endif
		return $this->icon_attachment_id;
	} // function

	/**
	 * Get the URL for the icon for this fixer station.
	 * This is used to graphically represent the fixer station in event details.
	 *
	 * @return	string	The URL for the icon used to represent this fixer station.
	 *
	 * @since v0.1.0
	 */
	public function get_icon_url() {
		if ( ! isset( $this->icon_url ) ) {
			$attach_id = $this->get_icon_attachment_id();
			if ( isset( $attach_id ) ) {
				$url = wp_get_attachment_image_url( $attach_id );
				if ( ! empty( $url ) ) {
					$this->icon_url = $url;
				} // endif
			} // endif
		} // endif
		return $this->icon_url;
	} // function

	/**
	 * Set the icon attachment ID for this fixer station.
	 *
	 * @param	int|string	$attachment_id	The attachment ID for the icon used to represent this fixer station.
	 *
	 * @since v0.1.0
	 */
	public function set_icon_attachment_id( $attachment_id ) {
		if ( ( $attachment_id === '' ) || ( $attachment_id === NULL ) || ( $attachment_id === FALSE ) ) {
			delete_term_meta( $this->get_id(), self::ICON_ATTACH_ID_META_KEY );
		} else {
			// We need to make sure there is only one value so if none exists add it, otherwise update it
			$curr = get_term_meta( $this->get_id(), self::ICON_ATTACH_ID_META_KEY, $single = TRUE );
			if ( ( $curr === '' ) || ( $curr === NULL ) || ( $curr === FALSE ) ) {
				add_term_meta( $this->get_id(), self::ICON_ATTACH_ID_META_KEY, $attachment_id );
			} else {
				update_term_meta( $this->get_id(), self::ICON_ATTACH_ID_META_KEY, $attachment_id, $curr );
			} // endif
		} // endif
		unset( $this->icon_attachment_id ); // Allow this to be re-acquired
		unset( $this->icon_url ); // Allow this to be re-acquired
	} // function

	/**
	 * Get the colour for this fixer station.
	 * This is used to colour code fixer stations on charts and graphs.
	 *
	 * @return	string	The CSS colour used to indicate this fixer station.
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
	 * Set the colour for this fixer station
	 *
	 * @param	string	$colour		The CSS colour used to indicate this fixer station.
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
	 * Get the array of external names for this fixer station.
	 * This is used to allow mapping external names like "Appliances & Households" to internal names like "Appliances and Housewares"
	 *
	 * @return	string[]	The array of external names used to reference this fixer station
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
	 * Set the external names for this fixer station
	 *
	 * @param	string[]	$external_names		The array of external names used to refer to this fixer station
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
				'name' 							=> __( 'Fixer Station','reg-man-rc' ),
				'singular_name'					=> __( 'Fixer Station', 'reg-man-rc' ),
				'menu_name'						=> __( 'Fixer Stations', 'reg-man-rc' ),
				'all_items'						=> __( 'All Fixer Stations', 'reg-man-rc' ),
				'edit_item'						=> __( 'Edit Fixer Station', 'reg-man-rc' ),
				'view_item'						=> __( 'View Fixer Station', 'reg-man-rc' ),
				'update_item'					=> __( 'Update Fixer Station', 'reg-man-rc' ),
				'add_new_item'					=> __( 'Add New Fixer Station', 'reg-man-rc' ),
				'new_item_name'					=> __( 'New Fixer Station', 'reg-man-rc' ),
				'parent_item'					=> NULL,
				'parent_item_colon'				=> NULL,
				'search_items'					=> NULL,// __( 'Search Fixer Stations', 'reg-man-rc' ),
				'popular_items'					=> NULL,// __( 'Popular Fixer Stations', 'reg-man-rc' ),
				'separate_items_with_commas'	=> NULL,// __( 'Separate item types with commas', 'reg-man-rc' ),
				'add_or_remove_items'			=> NULL,// __( 'Add or remove item types', 'reg-man-rc' ),
				'choose_from_most_used'			=> NULL,// __( 'Choose from the most used item types', 'reg-man-rc' ),
				'not_found'						=> NULL,// __( 'Fixer Station not found', 'reg-man-rc' ),
		);

		$args = array(
				'labels'				=> $labels,
				'description'			=> __( 'A station where fixers work at an event, e.g. Jewellery', 'reg-man-rc' ),
				'hierarchical'			=> FALSE, // Does each one have a parent and a heirarchy?
				'public'				=> TRUE, // whether it's intended for public use - must be TRUE to enable polylang translations
				'publicly_queryable'	=> FALSE, // can it be queried
				'show_ui'				=> TRUE, // does it have a UI for managing it
				'show_in_rest'			=> TRUE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
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
				Item::POST_TYPE,
				Internal_Event_Descriptor::POST_TYPE,
				Volunteer_Registration::POST_TYPE,
				Volunteer::POST_TYPE,
		);
		$taxonomy = register_taxonomy( self::TAXONOMY_NAME, $post_types, $args );

		if ( is_wp_error( $taxonomy ) ) {
			$msg = __( 'Failure to register taxonomy for Fixer Stations', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $taxonomy );
		} else {
			// Note that I must register the taxonomy before assigning a notification bubble to its menu item
			// This is because I need to check if the taxonomy is empty to determine if a bubble is needed
			//  but I can't check if it's empty until it has been registered
			$notice_count = Fixer_Station_Admin_View::get_is_show_create_defaults_admin_notice() ? 1 : 0;
			if ( $notice_count > 0 ) {
				$notification_bubble = '<span class="awaiting-mod">' . $notice_count . '</span>';
				$taxonomy->labels->menu_name .= $notification_bubble;
			} // endif
		} // endif



	} // function

	/**
	 * Get the html content shown to the administrator in the "About" help for this taxonomy
	 * @return string
	 */
	public static function get_about_content() {
		ob_start();
			$heading = __( 'About fixer stations', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A fixer station is an area at an event for fixers who repair certain kinds of items;' .
					' for example, Appliances & Housewares.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When an item is registered at an event it will be assigned to a fixer station. ' .
					' For example, a lamp would normally be assigned to the Appliances & Housewares fixer station. ',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When you create an event you will specify which fixer stations it will have' .
					' and the event description on the website will list those stations.' .
					' This lets visitors know what kinds of items to bring to an event,' .
					' and it lets volunteers know what kinds of fixers are needed.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Fixer stations are colour coded for display in statistical charts.',
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
	public static function get_default_fixer_station_data() {
		$result = array();

		$appliances_title = _x( 'Appliances & Housewares', 'Fixer station', 'reg-man-rc' );
		$bikes_title = _x( 'Bikes', 'Fixer station', 'reg-man-rc' );
		$books_title = _x( 'Books & Paper', 'Fixer station', 'reg-man-rc' );
		$clothing_title = _x( 'Clothing & Textiles', 'Fixer station', 'reg-man-rc' );
		$computers_title = _x( 'Computers', 'Fixer station', 'reg-man-rc' );
		$jewellery_title = _x( 'Jewellery', 'Fixer station', 'reg-man-rc' );

		$result[] = array(
			'name'			=> $appliances_title,
			'description'	=> __( 'Small appliances, tools, furniture, home electronics and general housewares', 'reg-man-rc' ),
			'colour'		=> '#610f40',
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Appliances & Housewares', $appliances_title, 'lamp.png' ),
		);
		$result[] = array(
			'name'			=> $bikes_title,
			'description'	=> __( 'Bicycles', 'reg-man-rc' ),
			'colour'		=> '#de2d37',
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Bikes', $bikes_title, 'bike.png' ),
		);
		$result[] = array(
			'name'			=> $books_title,
			'description'	=> __( 'Books and paper', 'reg-man-rc' ),
			'colour'		=> '#0f6eb0',
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Books', $books_title, 'book.png' ),
		);
		$result[] = array(
			'name'			=> $clothing_title,
			'description'	=> __( 'Clothing, linens and other textiles', 'reg-man-rc' ),
			'colour'		=> '#1a9d9a',
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Clothing & Textiles', $clothing_title, 'shirt.png' ),
		);
		$result[] = array(
			'name'			=> $computers_title,
			'description'	=> __( 'Computers, laptops, tablets, smartphones and other smart devices', 'reg-man-rc' ),
			'colour'		=> '#fb9d19',
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Computers', $computers_title, 'laptop.png' ),
		);
		$result[] = array(
			'name'			=> $jewellery_title,
			'description'	=> __( 'Jewellery and metalwork', 'reg-man-rc' ),
//			'colour'		=> '#ea4a25', // This is the RCT colour but it's too close to the one for bikes
			'colour'		=> '#dbdd46', // this is a gold colour which seems more appropriate
			'icon_id'		=> self::get_default_fixer_station_icon_attachment_id( 'Jewellery', $jewellery_title, 'necklace.png' ),
		);
		return $result;
	} // function

	private static function get_default_fixer_station_icon_attachment_id( $name, $title, $file_name ) {
//		Error_Log::var_dump( $name, $title, $file_name );

		$args = array(
				'post_type'			=> 'attachment',
				'post_status'		=> 'any', // attachments normally have post status of inherit, but I don't care
				'posts_per_page'	=> -1, // get all
				'meta_key'			=> self::ICON_STATION_NAME_META_KEY,
				'meta_query'		=> array(
						'key'		=> self::ICON_STATION_NAME_META_KEY,
						'value'		=> $name,
						'compare'	=> '=',
				)
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

//		Error_Log::var_dump( $query, $posts );
		if ( is_wp_error( $posts ) ) {
			$msg = __( 'Error getting default fixer station icon attachment', 'reg-man-rc' );
			Error_Log::log_wp_error( $msg, $posts );
			$result = NULL;
		} elseif ( empty( $posts ) || ! isset( $posts[ 0 ] ) ) {
			$result = NULL;
		} else {
			$post = $posts[ 0 ];
			$result = isset( $post ) ? $post->ID : NULL;
		} // endif
		wp_reset_postdata(); // Required after using WP_Query()

		if ( empty( $result ) ) {
			// Try to create it if we didn't find it
			$result = self::create_default_fixer_station_icon_media_attachment_id( $name, $title, $file_name );
		} // endif
//		Error_Log::var_dump( $result );
		return $result;
	} // function

	private static function create_default_fixer_station_icon_media_attachment_id( $name, $title, $file_name ) {
		$result = NULL;
//		Error_Log::var_dump( $name, $title, $file_name );

		$icon_rel_dir = 'images/icons';
		$icon_root = plugin_dir_path( \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME ) . $icon_rel_dir;
		$source_file_path = "$icon_root/$file_name";

		if ( ! file_exists( $source_file_path ) ) {
			/* translators: %1$s is title for an attachment, %2$s is the attachment file path */
			$err_msg = sprintf( __( 'Cannot create attachment for %1$s because the file does not exist: %2$s', 'reg-man-rc' ), $title, $source_file_path );
			Error_Log::log_msg( $err_msg );
			$result = NULL;
		} else {
			// Apparently I MUST copy the file to the wp-uploads directory
			// I will copy my files into a separate subdirectory to avoid overwriting any of the user's content
			$uploads_dir_info = wp_upload_dir();
			$uploads_base_dir = $uploads_dir_info[ 'basedir' ];
			$target_subdir = 'reg_man_rc_icons';
			$target_dir_path = $uploads_base_dir . DIRECTORY_SEPARATOR . $target_subdir;

			$mkdir_result = wp_mkdir_p( $target_dir_path ); // will only make the directory if it doesn't exist
			if ( ! $mkdir_result ) {
				/* translators: %s is a file directory path */
				$err_msg = sprintf( __( 'Failed to create media library directory for fixer station icons: %s', 'reg-man-rc' ), $target_dir_path );
				Error_Log::log_msg( $err_msg );
				$result = NULL;
			} else {
				$target_file_path = $target_dir_path . DIRECTORY_SEPARATOR . $file_name;
				if ( ! file_exists( $target_file_path ) ) {
					copy( $source_file_path, $target_file_path );
				} // endif
				$file_type = wp_check_filetype( $target_file_path );
				$attach_args = array(
					'post_mime_type'	=> $file_type[ 'type' ],
					'post_title'		=> $title,
					'post_content'		=> '',
					'post_status'		=> 'inherit',
				);
				$attach_id = wp_insert_attachment( $attach_args, $target_file_path, $parent = 0, $wp_error = TRUE );
				if ( is_wp_error( $attach_id ) ) {
					/* translators: %s is the name of a fixer station */
					$err_msg = sprintf( __( 'Failed to insert icon media attachment for default fixer station: %s', 'reg-man-rc' ), $title );
					Error_Log::log_wp_error( $err_msg, $attach_id );
					$result = NULL;
				} else {
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					$attach_data = wp_generate_attachment_metadata( $attach_id, $target_file_path );
					wp_update_attachment_metadata( $attach_id, $attach_data );
					add_post_meta( $attach_id, self::ICON_STATION_NAME_META_KEY, $name );
					$result = $attach_id;
				} // endif
			} // endif
		} // endif
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
		$query = new WP_Term_Query( $args );
		foreach( $query->get_terms() as $term ){
			wp_delete_term( $term->term_id, self::TAXONOMY_NAME );
		} // endfor
		unregister_taxonomy( self::TAXONOMY_NAME );
	} // function

} // class
