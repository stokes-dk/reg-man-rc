<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Admin\Admin_Menu_Page;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Stats\Item_Descriptor;

/**
 * An instance of this class represents an item registered by a visitor for an event
 *
 * @since	v0.1.0
 *
 */
class Item implements Item_Descriptor {

	const POST_TYPE				= 'reg-man-rc-item';
	const DEFAULT_PAGE_SLUG		= 'rc-items';

	const EVENT_META_KEY			= self::POST_TYPE . '-event';
	const VISITOR_META_KEY			= self::POST_TYPE . '-visitor';
	const STATUS_META_KEY			= self::POST_TYPE . '-status';
	const PRE_REG_META_KEY			= self::POST_TYPE . '-is-pre-registered';
	
	// FIXME - we need support for appointment times

	private $post;
	private $post_id;
	private $item_desc; // A short description of the item indicating what it is, e.g. Coffee Maker, Pants, Necklace
//	private $item_details; // Optional details about the item provided by the visitor
	private $is_pre_registered; // A flag indicating whether the item was pre-registered before an event
	private $event_key; // The key for the Event this item is registered for
	private $event; // The Event this item is registered for
	private $visitor_id; // The ID of the Visitor object for the person who registered the item
	private $visitor; // The Visitor object for the person who registered the item

	// Note that the following visitor name properties are used to implement Item_Descriptor
	private $visitor_full_name; // The full name of the visitor who registered the item
	private $visitor_public_name; // The public name of the visitor who registered the item

	private $item_type_name; // The name of this item's type, like 'Appliance'
	private $item_type;  // The type of item as an instance of the Item_Type class
//	private $brand; // The (optional) brand or manufacturer of the item
//	private $model; // The (optional) model number of the item
//	private $year; // The (optional) year of manufacture of the item
//	private $weight; // The (optional) weight of the item
//	private $problem_desc; // A description of the problem with the item (provided by fixer)
	private $fixer_station_name; // The name of this item's fixer station, like 'Appliances & Housewares'
	private $fixer_station; // The fixer station this item has been assigned to
	private $status_name; // The item's status as a string
	private $status; // The Item_Status object for the item's status, e.g. registered, fixed, repairable etc.
	private $is_repair_outcome_reported; // TRUE when the item's repair outcome has been reported, FALSE otherwise
	private $item_priority; // A relative priority of this item vs others the same visitor brought,
		// e.g. A visitor may want to have their radio fixed first and jeans fixed second
		// The default priority of items is simply the order they were registered but the visitor may
		// wish to re-prioritize items after registration
	private $external_id; // Used for uploading records created on a standalone server
		// When a record is created this is NULL.  When (if) it is uploaded to the public server this will be
		//  assigned to the id of the new record created on the public server, marking it is uploaded

	private function __construct() { }

	/**
	 * Create an instance of this class using the data provided
	 * @param	\WP_Post $post
	 * @return	Item|NULL
	 * @since	v0.1.0
	 */
	private static function instantiate_from_post( $post ) {
		if ( ! ( $post instanceof \WP_Post ) || ( $post->post_type !== self::POST_TYPE ) ) {
			$result = NULL; // The argument is not a post or not the right type so I can't process it
		} else {
			$result = new self();
			$result->post = $post;
			$result->post_id = $post->ID;
			$result->item_desc = isset( $post->post_title ) ? $post->post_title : NULL;
//			$result->problem_desc = isset( $post->post_excerpt ) ? $post->post_excerpt : NULL;
		} // endif
		return $result;
	} // function

	/**
	 * Create a new item
	 *
	 * @param	string				$item_desc				The short description of the new item, e.g. "Toaster"
	 * @param	Fixer_Station		$fixer_station			The fixer station for the new item
	 * @param	Item_Type			$item_type				The item type for the new item
	 * @param	Event_Key|string	$event_key				The key for the event the item is registered for
	 * @param	Visitor				$visitor				The Visitor who registered the item
	 * @param	Item_Status			$item_status			(optional) TRUE if the visitor wants to join the mailing list for the repair cafe, FALSE otherwise
	 * @return	Item|NULL
	 */
	public static function create_new_item( $item_desc, $fixer_station, $item_type, $event_key, $visitor, $item_status = NULL ) {

		$args = array(
				'post_title'	=> $item_desc,
				'post_status'	=> 'publish',
				'post_type'		=> self::POST_TYPE,
		);

		$post_id = wp_insert_post( $args, $wp_error = TRUE );

		if ( $post_id instanceof \WP_Error ) {
			Error_Log::log_wp_error( __( 'Unable to create new item', 'reg-man-rc' ), $post_id );
			$result = NULL;
		} else {
			$post = get_post( $post_id );
			$result = self::instantiate_from_post( $post );
			if ( ! empty( $result ) ) {
				$result->set_fixer_station( $fixer_station );
				$result->set_item_type( $item_type );
				$result->set_event_key( $event_key );
				$result->set_visitor( $visitor );
				$result->set_status( $item_status );
			} // endif
		} // endif

		return $result;

	} // function

	/**
	 * Get the item with the specified ID.
	 * @param	string|int	$item_id	The ID of the item to be returned
	 * @return	Item|NULL	An instance of this class with the specified ID
	 * 	if it exists in the database, or NULL if it does not exist.
	 * @since	v0.1.0
	 */
	public static function get_item_by_id( $item_id ) {
		$post = get_post( $item_id );
		$result = self::instantiate_from_post( $post );
		return $result;
	} // function

	/**
	 * Get all Items.
	 * @return Item[]	An array of instances of this class or an empty array if no Items exist
	 * @since	v0.1.0
	 */
	public static function get_all_items( ) {
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
			$item = self::instantiate_from_post( $post );
			if ( $item !== NULL ) {
				$result[] = $item;
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
	 * Get the items registered to the specified event
	 * @param	string|Event_Key	$event_key	The key for the event whose items are to be returned
	 * @return	Item[]	An array of instances of this class or an empty array
	 * 	if the event has no items registered
	 * @since	v0.1.0
	 */
	public static function get_items_registered_for_event( $event_key ) {
		$result = array();
		if ( is_string( $event_key ) ) {
			$key_string = $event_key; // the argument is the string I want
		} else {
			$key_string = ( $event_key instanceof Event_Key ) ? $event_key->get_as_string() : NULL;
		} // endif
		if ( ! empty ( $key_string ) ) {
			$args = array(
					'post_type'			=> self::POST_TYPE,
					'posts_per_page'	=>	-1, // Get all posts
					'meta_key'			=> self::EVENT_META_KEY,
					'meta_query'		=> array(
								array(
										'key'		=> self::EVENT_META_KEY,
										'value'		=> $key_string,
										'compare'	=> '=',
								)
					)
			);
			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif
		return $result;
	} // function

	/**
	 * Get the items registered to the specified array of events
	 * @param	string[]	$event_key_array	An array of event keys for the events whose items are to be returned
	 *  OR NULL to return all items
	 * @return	Item[]	An array of instances of this class or an empty array if no items registered for the specified events
	 * @since	v0.1.0
	 */
	public static function get_items_registered_for_event_keys_array( $event_keys_array ) {
		if ( is_array( $event_keys_array) && ( count( $event_keys_array ) == 0 ) ) {
			$result = array(); // The request is for an empty set of events so return an empty set
		} else {
			$result = array();
			$args = array(
					'post_type'			=> self::POST_TYPE,
					'posts_per_page'	=>	-1, // Get all posts
			);

			if ( is_array( $event_keys_array ) ) {
				$args[ 'meta_key' ]		= self::EVENT_META_KEY;
				$args[ 'meta_query' ]	= array(
							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $event_keys_array,
									'compare'	=> 'IN',
							)
					);
			} // endif

			$query = new \WP_Query( $args );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor
			wp_reset_postdata(); // Required after using WP_Query()
		} // endif

		return $result;
	} // function

	/**
	 * Get the ID of this item
	 * @return	string	The ID of this item
	 * @since	v0.1.0
	 */
	public function get_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the post object for this item
	 * @return	\WP_Post	The post object containing the information for this item.
	 * @since	v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	/**
	 * Get the post ID of this item
	 * @return	string	The post ID of the custom post containing the information for this item.
	 * @since	v0.1.0
	 */
	private function get_post_id() {
		return $this->post_id;
	} // function

	/**
	 * Get the description of this item
	 * @return	string	The description of this item which may contain html formating
	 * @since	v0.1.0
	 */
	public function get_item_description() {
		return $this->item_desc;
	} // function

	/**
	 * Get a flag indicating whether this item was pre-registered
	 * @return	boolean		A flag set to TRUE if the item was pre-registered and FALSE otherwise
	 * @since	v0.1.0
	 */
	public function get_is_pre_registered() {
		if ( !isset( $this->is_pre_registered ) ) {
			$val = get_post_meta( $this->get_post_id(), self::PRE_REG_META_KEY, $single = TRUE );
			$this->is_pre_registered = ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) ? TRUE : FALSE;
		} // endif
		return $this->is_pre_registered;
	} // function

	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			$key = $this->get_event_key();
			$this->event = isset( $key ) ? Event::get_event_by_key( $key ) : NULL;
		} // endif
		return $this->event;
	} // function

	public function get_event_key() {
		if ( ! isset( $this->event_key ) ) {
			$val = get_post_meta( $this->get_post_id(), self::EVENT_META_KEY, $single = TRUE );
			$this->event_key = ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) ? $val : NULL;
		} // endif
		return $this->event_key;
	} // function

	/**
	 * Assign the event key for this item
	 * @param	string|Event_Key	$event_key	The event key to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_event_key( $event_key ) {
		if ( empty( $event_key ) ) {
			// The new value is empty so we can remove the metadata
			delete_post_meta( $this->get_post_id(), self::EVENT_META_KEY );
		} else {
			$event_key_string = is_string( $event_key ) ? $event_key : $event_key->__toString();
			update_post_meta( $this->get_post_id(), self::EVENT_META_KEY, $event_key_string );
		} // endif
		unset( $this->event ); // allow it to be re-acquired
		unset( $this->event_key ); // allow it to be re-acquired
	} // function

	/**
	 * Get the ID of the visitor record for the person who registered the item.
	 * @return	int		The ID of the visitor who registered the item.
	 * @since	v0.1.0
	 */
	private function get_visitor_id() {
		if ( ! isset( $this->visitor_id ) ) {
			$val = get_post_meta( $this->get_post_id(), self::VISITOR_META_KEY, $single = TRUE );
			$this->visitor_id = ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) ? $val : NULL;
		} // endif
		return $this->visitor_id;
	} // function

	/**
	 * Get the visitor record for the person who registered the item.
	 * @return	Visitor		The visitor who registered the item.
	 * @since	v0.1.0
	 */
	public function get_visitor() {
		if ( ! isset( $this->visitor ) ) {
			$visitor_id = $this->get_visitor_id();
			$this->visitor = ( $visitor_id !== NULL ) ? Visitor::get_visitor_by_id( $visitor_id ) : NULL;
		} // endif
		return $this->visitor;
	} // function

	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name() {
		if ( ! isset( $this->visitor_full_name ) ) {
			$visitor = $this->get_visitor();
			// Note that the visitor class takes care of ensuring that the user has access to visitor's full name
			$this->visitor_full_name = isset( $visitor ) ? $visitor->get_full_name() : '';
		} // endif
		return $this->visitor_full_name;
	} // function

	/**
	 * Get the public name of the visitor who registered the item.
	 * This is a name for the visitor that can be used in public like first name and last initial.
	 * @return	string		The visitor's public name.
	 * @since	v0.1.0
	 */
	public function get_visitor_public_name() {
		if ( ! isset( $this->visitor_public_name ) ) {
			$visitor = $this->get_visitor();
			$this->visitor_public_name = isset( $visitor ) ? $visitor->get_public_name() : '';
		} // endif
		return $this->visitor_public_name;
	} // function

	/**
	 * Assign the visitor record for this item indicating who registered the item
	 * @param	Visitor	$visitor	The visitor record object to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_visitor( $visitor ) {
		$visitor_id = ( $visitor instanceof Visitor ) ? $visitor->get_id() : NULL;
		if ( $visitor_id == NULL ) {
			// The new value is empty so we should remove the metadata
			delete_post_meta( $this->get_post_id(), self::VISITOR_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::VISITOR_META_KEY, $visitor_id );
		} // endif
		unset( $this->visitor_id ); // allow it to be re-acquired
		unset( $this->visitor ); // allow it to be re-acquired
	} // function

	/**
	 * Get the name of this item's type, like 'Appliance'.
	 * @return	string	This item's type name
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
	 * Get the item type for this item
	 * @return	Item_Type	The type of this item
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
	 * Assign the item type for this item
	 * @param	Item_Type	$item_type	The item type to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_item_type( $item_type ) {
		$item_type_id = ( $item_type instanceof Item_Type ) ? $item_type->get_id() : NULL;
		$item_id = $this->get_id();
		if ( $item_type_id == NULL ) {
			// If the new type id is NULL then that means to unset or remove the current setting
			wp_delete_object_term_relationships( $item_id, Item_Type::TAXONOMY_NAME );
		} else {
			$item_type_id = intval( $item_type_id );
			$terms_array = array ( $item_type_id );
			wp_set_post_terms( $item_id, $terms_array, Item_Type::TAXONOMY_NAME );
		} // endif
		$this->item_type = NULL; // reset my internal var so it can be re-acquired
		$this->item_type_name = NULL; // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get the name of the fixer station assigned to this item
	 *
	 * @return	string	The name of the fixer station assigned to this item or NULL if no fixer station is assigned
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_station_name() {
		if ( ! isset( $this->fixer_station_name ) ) {
			$station = $this->get_fixer_station();
			$this->fixer_station_name = isset( $station ) ? $station->get_name() : NULL;
		} //endif
		return $this->fixer_station_name;
	} // function

	/**
	 * Get the fixer station assigned to this item
	 *
	 * @return	Fixer_Station	The fixer station object assigned to this item or NULL if no fixer station is assigned
	 *
	 * @since v0.1.0
	 */
	public function get_fixer_station() {
		if ( ! isset( $this->fixer_station ) ) {
			$station_array = Fixer_Station::get_fixer_stations_for_post( $this->get_post_id() );
			$this->fixer_station = ( is_array( $station_array ) && isset( $station_array[ 0 ] ) ) ? $station_array[ 0 ] : NULL;
/*
			// If there is no fixer station explicity assigned then use the default for the item type
			if ( ! isset( $this->fixer_station ) ) {
				$item_type = $this->get_item_type();
				$this->fixer_station = isset( $item_type ) ? $item_type->get_fixer_station() : NULL;
			} // endif
*/
		} // endif
		return $this->fixer_station;
	} // function

	/**
	 * Assign the fixer station for this item
	 *
	 * @param	Fixer_Station	$fixer_station	The fixer station being assigned to this item
	 * @return	void
	 *
	 * @since v0.1.0
	 */
	public function set_fixer_station( $fixer_station ) {
		$fixer_station_id = ( $fixer_station instanceof Fixer_Station ) ? $fixer_station->get_id() : NULL;
		$item_id = $this->get_id();
		if ( $fixer_station_id == NULL ) {
			// If the new station id is NULL then that means to unset or remove the fixer station
			wp_delete_object_term_relationships( $item_id, Fixer_Station::TAXONOMY_NAME );
		} else {
			$fixer_station_id = intval( $fixer_station_id );
			$terms_array = array ( $fixer_station_id );
			wp_set_post_terms( $item_id, $terms_array, Fixer_Station::TAXONOMY_NAME );
		} // endif
		$this->fixer_station = NULL; // reset my internal var so it can be re-acquired
		$this->fixer_station_name = NULL; // reset my internal var so it can be re-acquired
	} // function

	/**
	 * Get the status name for this item, i.e. Fixed, Repairable etc.
	 *
	 * @return	string	The name of the status assigned to this item.
	 *
	 * @since v0.1.0
	 */
	public function get_status_name() {
		if ( ! isset( $this->status_name ) ) {
			$status = $this->get_status();
			$this->status_name = isset( $status ) ? $status->get_name() : NULL;
		} // endif
		return $this->status_name;
	} // function

	/**
	 * Get the status for this item, i.e. Fixed, Repairable etc.
	 *
	 * @return	Item_Status	The status object assigned to this item.
	 * If no status has been assigned to the item then the system default item status will be returned
	 *
	 * @since v0.1.0
	 */
	public function get_status() {
		if ( ! isset( $this->status ) ) {
			$status_id = get_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $single = TRUE );
			if ( ( $status_id !== FALSE ) && ( $status_id !== NULL ) && ( $status_id != '' ) ) {
				$this->status = Item_Status::get_item_status_by_id( $status_id );
			} else {
				$this->status = Item_Status::get_default_item_status();
			} // endif
		} // endif
		return $this->status;
	} // function
	
	public function get_is_repair_outcome_reported() {
		if ( ! isset( $this->is_repair_outcome_reported ) ) {
			$status = $this->get_status();
			$this->is_repair_outcome_reported = $status->get_is_repair_outcome_status();
		} // endif
		return $this->is_repair_outcome_reported;
	} // function

	/**
	 * Assign the status for this item indicating wheter or not it has been fixed
	 * @param	Item_Status	$item_status	The status to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_status( $item_status ) {
		$status_id = ( $item_status instanceof Item_Status ) ? $item_status->get_id() : NULL;
		if ( $status_id == NULL ) {
			// The new value is empty so we should remove the metadata
			delete_post_meta( $this->get_post_id(), self::STATUS_META_KEY );
		} else {
			update_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $status_id );
		} // endif
		unset( $this->status ); // allow it to be re-acquired
		unset( $this->status_name ); // allow it to be re-acquired
	} // function

	/**
	 * Get a string indicating the source of this descriptor
	 *
	 * @return	string	A string indicating where this descriptor came from, e.g. 'registration', 'supplemental'
	 *
	 * @since v0.1.0
	 */
	public function get_item_descriptor_source() {
		return __( 'registered', 'reg-man-rc' );
	} // function

	/**
	 *  Register the Item custom post type during plugin init.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// Add an action listener for post delete so that I can clean up any orphaned visitor records
		add_action( 'before_delete_post', array( __CLASS__, 'handle_before_delete_post' ), 10, 2 );

		$labels = array(
				'name'					=> _x( 'Items', 'Item post type general name', 'reg-man-rc'),
				'singular_name'			=> _x( 'Item', 'Item post type singular name', 'reg-man-rc'),
				'add_new'				=> __( 'Add New', 'reg-man-rc'),
				'add_new_item'			=> __( 'Add New Item' , 'reg-man-rc' ),
				'edit_item'				=> __( 'Edit Item', 'reg-man-rc'),
				'new_item'				=> __( 'New Item', 'reg-man-rc'),
				'all_items'				=> __( 'Items', 'reg-man-rc'), // This is the menu item title
				'view_item'				=> __( 'View Item', 'reg-man-rc'),
				'search_items'			=> __( 'Search Items', 'reg-man-rc'),
				'not_found'				=> __( 'No items found', 'reg-man-rc'),
				'not_found_in_trash'	=> __( 'No items found in the trash', 'reg-man-rc'),
				'parent_item_colon'		=> '',
				'menu_name'				=> __( 'Items', 'reg-man-rc' )
		);

		$icon = 'dashicons-clipboard';
		$supports = array( 'title', 'editor', 'thumbnail', 'comments' );
		$capability_singular = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Items', // Internal description, not visible externally
				'public'				=> TRUE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> FALSE, // exclude from regular search results?
				'publicly_queryable'	=> TRUE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				// There is no reason to make these available in REST or to use Block editor
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> 5, // Menu order position.  5 is below Posts
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> $supports,
				'taxonomies'			=> array(
												Item_Type::TAXONOMY_NAME,
												Fixer_Station::TAXONOMY_NAME,
				),
				'has_archive'			=> FALSE, // is there an archive page?
				// FIXME - Do items really have their own page, what's the right url?
				'rewrite'				=> array(
					'slug'			=> Settings::get_items_slug(),
					'with_front'	=> FALSE,
				),
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
		);
		register_post_type( self::POST_TYPE, $args );

	} // function

	/**
	 * Handle cleanup when deleting my custom post and make sure there are no orphaned visitor records
	 * @param	int			$post_id	The ID of the post being deleted
	 * @param	\WP_Post	$post		The post being deleted
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_before_delete_post( $post_id, $post ) {
		if ( $post->post_type === self::POST_TYPE ) {
			$visitor_id = get_post_meta( $post_id, self::VISITOR_META_KEY, $single = TRUE );
			if ( ! empty( $visitor_id ) ) {
				global $wpdb;
				// I will check if there is any metadata record using this id
				$table  = $wpdb->postmeta;
				$select = "SELECT meta_value FROM $table WHERE meta_key = %s AND meta_value = %s AND post_id != %s LIMIT 1";
				$data = $wpdb->get_var( $wpdb->prepare( $select, self::VISITOR_META_KEY, $visitor_id, $post_id ) );
				if ( $data === NULL ) {
					Visitor::delete_visitor( $visitor_id );
				} // endif
			} // endif
		} // endif
	} // function

	/**
	 * Perform the necessary steps for this class during plugin uninstall
	 * @return	void
	 * @since	v0.1.0
	 */
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