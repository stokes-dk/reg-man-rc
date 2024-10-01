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

	const EVENT_META_KEY				= self::POST_TYPE . '-event';
	const VISITOR_META_KEY				= self::POST_TYPE . '-visitor';
	const STATUS_META_KEY				= self::POST_TYPE . '-status';
	const DATE_TIME_ENQUEUED_META_KEY 	= self::POST_TYPE . '-date-time-enqueued';
	
	const DATE_FORMAT = 'Y-m-d H:i:s'; // Used to store the date/time the item was enqueued 
	
	// FIXME - we need support for appointment times

	private $post;
	private $post_id;
	private $item_desc; // A short description of the item indicating what it is, e.g. Coffee Maker, Pants, Necklace
	private $event_key_string; // The key for the Event this item is registered for
	private $event; // The Event this item is registered for
	private $placeholder_event; // The placehoder event if the actual event for this item does not exist
	private $visitor_id; // The ID of the Visitor object for the person who registered the item
	private $visitor; // The Visitor object for the person who registered the item

	// Note that the following visitor properties are used to implement Item_Descriptor
	private $visitor_display_name; // The displayable name of the visitor who registered the item
	private $visitor_full_name; // The full name of the visitor who registered the item
	private $visitor_public_name; // The public name of the visitor who registered the item
	private $visitor_email;
	private $visitor_is_first_time;
	private $visitor_is_join_mail_list;

	private $item_type_name; // The name of this item's type, like 'Appliance'
	private $item_type;  // The type of item as an instance of the Item_Type class
//	private $brand; // The (optional) brand or manufacturer of the item
//	private $model; // The (optional) model number of the item
//	private $year; // The (optional) year of manufacture of the item
//	private $weight; // The (optional) weight of the item
//	private $problem_desc; // A description of the problem with the item (provided by fixer)
	private $fixer_station_name; // The name of this item's fixer station, like 'Appliances & Housewares'
	private $fixer_station; // The fixer station this item has been assigned to
	private $item_status; // The Item_Status object for the item's status, e.g. registered, fixed, repairable etc.
	private $date_time_enqueued; // A string representing the date & time the item's status was changed to "In Queue"
		// Note that this time is used to prioritize items in the queue, i.e. first come first served
//	private $item_priority; // A relative priority of this item vs others the same visitor brought,
		// e.g. A visitor may want to have their radio fixed first and jeans fixed second
		// The default priority of items is simply the order they were registered but the visitor may
		// wish to re-prioritize items after registration

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
				$result->set_item_status( $item_status );
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
		
		$args = array(
				'post_type'				=> self::POST_TYPE,
				'posts_per_page'		=> -1, // get all
				'orderby'				=> 'post_title',
				'order'					=> 'ASC',
				'ignore_sticky_posts'	=> 1 // TRUE here means do not move sticky posts to the start of the result set
				
		);

		$query = new \WP_Query( $args );
		$post_array = $query->posts;

		foreach ( $post_array as $post ) {
			$item = self::instantiate_from_post( $post );
			if ( $item !== NULL ) {
				$result[] = $item;
			} // endif
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
		return $result;

	} // function

	/**
	 * Get the items registered to the specified event
	 * @param	string|Event_Key	$event_key	The key for the event whose items are to be returned
	 * @return	Item[]	An array of instances of this class or an empty array
	 * 	if the event has no items registered
	 * @since	v0.1.0
	 */
	public static function get_items_registered_for_event( $event_key, $fixer_station_id = NULL ) {
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
					'orderby'			=> 'ID',
					'order'				=> 'ASC',
					'meta_key'			=> self::EVENT_META_KEY,
					'meta_query'		=> array(
								array(
										'key'		=> self::EVENT_META_KEY,
										'value'		=> $key_string,
										'compare'	=> '=',
								)
					)
			);

			if ( ! empty( $fixer_station_id ) ) {
					$args[ 'tax_query' ]	= array(
							array(
									'taxonomy'	=> Fixer_Station::TAXONOMY_NAME,
									'field'		=> 'term_id',
									'terms'		=> $fixer_station_id,
							)
					);
			} // endif
			
			$query = new \WP_Query( $args );
			$post_array = $query->posts;

			foreach ( $post_array as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor

//			wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
			
		} // endif

		return $result;
	} // function

	/**
	 * Get the items registered to the specified array of events
	 * @param	string[]	$event_keys_array	An array of event keys for the events whose items are to be returned
	 *  OR NULL to return all items
	 * @return	Item[]	An array of instances of this class or an empty array if no items registered for the specified events
	 * @since	v0.1.0
	 */
	public static function get_items_registered_for_event_keys_array( $event_keys_array ) {
		
		if ( is_array( $event_keys_array ) && ( count( $event_keys_array ) == 0 ) ) {

			$result = array(); // The request is for an empty array of events so return an empty result array

		} else {

			// Otherwise, the request is for ALL events (event keys array is NULL) or some subset

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
			$post_array = $query->posts;
			
			foreach ( $post_array as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor
			
//			wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
			
		} // endif

		return $result;

	} // function

	/**
	 * Get the items registered by the specified visitor to the specified event
	 * @param	string				$visitor_id	The ID of the visitor whose items are to be returned
	 * @param	string|Event_Key	$event_key	The key for the event whose items are to be returned
	 * @return	Item[]	An array of instances of this class or an empty array if the event has no items registered by the visitor
	 * @since	v0.1.0
	 */
	public static function get_items_registered_by_visitor_for_event( $visitor_id, $event_key ) {
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
					'orderby'			=> 'ID',
					'order'				=> 'ASC',
					'meta_query'		=> array(
							
							'relation'	=> 'AND',

							array(
									'key'		=> self::VISITOR_META_KEY,
									'value'		=> $visitor_id,
									'compare'	=> '=',
							),

							array(
									'key'		=> self::EVENT_META_KEY,
									'value'		=> $key_string,
									'compare'	=> '=',
							)
					)
			);

			$query = new \WP_Query( $args );
			$post_array = $query->posts;

			foreach ( $post_array as $post ) {
				$result[] = self::instantiate_from_post( $post );
			} // endfor

//			wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
			
		} // endif

		return $result;
	} // function
	

	/**
	 * Get an array of event key strings for items registered to events in the specified date range
	 * @param string $min_key_date_string
	 * @param string $max_key_date_string
	 * @return string[]
	 */
	public static function get_event_key_strings_for_items_in_date_range( $min_key_date_string, $max_key_date_string ) {

		$result = array();
		$args = array(
				'post_type'			=> self::POST_TYPE,
				'posts_per_page'	=>	-1, // Get all posts
		);

		$args[ 'meta_key' ]		= self::EVENT_META_KEY;
		$args[ 'meta_query' ]	= array(
					array(
							'key'		=> self::EVENT_META_KEY,
							'value'		=> array( $min_key_date_string, $max_key_date_string ),
							'compare'	=> 'BETWEEN',
					)
			);

		$query = new \WP_Query( $args );
		$posts = $query->posts;
		foreach ( $posts as $post ) {
			$result[] = get_post_meta( $post->ID, self::EVENT_META_KEY, $single = TRUE );
		} // endfor

//		wp_reset_postdata(); // Required after using WP_Query() ONLY if also using query->the_post() !
		
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
	 * Get the ID of this item
	 * @return	string	The ID of this item
	 * @since	v0.9.5
	 */
	public function get_item_id() {
		return $this->get_id();
	} // function

	/**
	 * Get the description of this item
	 * @return	string	The description of this item
	 * @since	v0.1.0
	 */
	public function get_item_description() {
		return $this->item_desc;
	} // function

	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	public function get_event() {
		if ( ! isset( $this->event ) ) {
			$key = $this->get_event_key_string();
			$this->event = isset( $key ) ? Event::get_event_by_key( $key ) : NULL;
		} // endif
		return $this->event;
	} // function

	public function get_event_key_string() {
		if ( ! isset( $this->event_key_string ) ) {
			$val = get_post_meta( $this->get_post_id(), self::EVENT_META_KEY, $single = TRUE );
			$this->event_key_string = ( ( $val !== FALSE ) && ( $val !== NULL ) && ( $val !== '' ) ) ? $val : NULL;
		} // endif
		return $this->event_key_string;
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
		unset( $this->event_key_string ); // allow it to be re-acquired
	} // function

	/**
	 * Get the event for which this item was registered
	 * @return	Event	The event for which this item was registered.
	 * @since	v0.1.0
	 */
	private function get_event_or_placeholder() {
		$result = $this->get_event();
		if ( ! isset( $result ) ) {
			if ( ! isset( $this->placeholder_event ) ) {
				$event_key_string = $this->get_event_key_string();
				if ( ! empty( $event_key_string ) ) {
					$this->placeholder_event = Event::create_placeholder_event( $event_key_string );
				} // endif
			} // endif
			$result = $this->placeholder_event;
		} // endif
		return $result;
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
	 * Get the most descriptive name available to the current user in the current context for display purposes.
	 * If we're rendering the admin interface and the user can view the full name then
	 *   it will be returned (if known), otherwise the public name is used
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_display_name() {
		
		if ( ! isset( $this->visitor_display_name ) ) {
			
			$visitor = $this->get_visitor();
			
			$event = $this->get_event_or_placeholder();
			
			$user_can_register_items_for_any_event =
					current_user_can( 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL );

			$user_can_register_items_for_this_event = ! empty( $event ) ? $event->get_is_current_user_able_to_register_items() : FALSE;

			if ( is_admin() && ( $user_can_register_items_for_any_event || $user_can_register_items_for_this_event ) ) {
			
				$this->visitor_display_name = isset( $visitor ) ? $visitor->get_display_name() : NULL;
				
			} else {
				
				$this->visitor_display_name = isset( $visitor ) ? $visitor->get_public_name() : NULL;
					
			} // endif
			
		} // endif

		return $this->visitor_display_name;

	} // function

	/**
	 * Get the full name of the visitor who registered the item.
	 * @return	string		The visitor's full name.
	 * @since	v0.1.0
	 */
	public function get_visitor_full_name() {
		if ( ! isset( $this->visitor_full_name ) ) {
			$visitor = $this->get_visitor();
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
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_email()
	 */
	public function get_visitor_email() {
		
		if ( ! isset( $this->visitor_email ) ) {
			
			$visitor = $this->get_visitor();
			
			$event = $this->get_event_or_placeholder();
			
			$user_can_register_items_for_any_event =
					current_user_can( 'edit_others_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL );

			$user_can_register_items_for_this_event = ! empty( $event ) ? $event->get_is_current_user_able_to_register_items() : FALSE;

			if ( is_admin() && ( $user_can_register_items_for_any_event || $user_can_register_items_for_this_event ) ) {
			
				$this->visitor_email = isset( $visitor ) ? $visitor->get_email() : NULL;

			} else {
			
				$this->visitor_email = '';
				
			} // endif
			
		} // endif

		return $this->visitor_email;

	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_is_first_time()
	 */
	public function get_visitor_is_first_time() {
		
		if ( ! isset( $this->visitor_is_first_time ) ) {
			
			$visitor = $this->get_visitor();
			
			$first_event_key = isset( $visitor ) ? $visitor->get_first_event_key() : NULL;
			
			$item_event_key = $this->get_event_key_string();
			
			$this->visitor_is_first_time = ( ! empty( $first_event_key ) ) && ( $item_event_key == $first_event_key );
			
		} // endif
		
		return $this->visitor_is_first_time;
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Stats\Item_Descriptor::get_visitor_is_join_mail_list()
	 */
	public function get_visitor_is_join_mail_list() {
		if ( ! isset( $this->visitor_is_join_mail_list ) ) {
			
			$visitor = $this->get_visitor();
			
			$this->visitor_is_first_time = isset( $visitor ) ? $visitor->get_is_join_mail_list() : NULL;
			
		} // endif
		
		return $this->visitor_is_join_mail_list;
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
	 * Get the status for this item, i.e. Fixed, Repairable etc.
	 *
	 * @return	Item_Status	The status object assigned to this item.
	 * If no status has been assigned to the item then the system default item status will be returned
	 *
	 * @since v0.1.0
	 */
	public function get_item_status() {
		if ( ! isset( $this->item_status ) ) {
			$status_id = get_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $single = TRUE );
			if ( ( $status_id !== FALSE ) && ( $status_id !== NULL ) && ( $status_id != '' ) ) {
				$this->item_status = Item_Status::get_item_status_by_id( $status_id );
			} else {
				$this->item_status = Item_Status::get_default_item_status();
			} // endif
		} // endif
		return $this->item_status;
	} // function

	/**
	 * Assign the status for this item indicating wheter or not it has been fixed
	 * @param	Item_Status	$item_status	The status to be assigned to this item
	 * @return	void
	 * @since	v0.1.0
	 */
	public function set_item_status( $item_status ) {
		$status_id = ( $item_status instanceof Item_Status ) ? $item_status->get_id() : NULL;
		$default_status_id = Item_Status::get_default_item_status_id();
		if ( ( $status_id == NULL ) || ( $status_id == $default_status_id ) )  {
			// The new value is empty or is the default (Standby) so we should remove the metadata
			delete_post_meta( $this->get_post_id(), self::STATUS_META_KEY );
			// We'll also remove the date/time the item entered queue because it has not yet entered (i.e. sent back)
			$this->set_date_time_enqueued( NULL );
		} else {
			update_post_meta( $this->get_post_id(), self::STATUS_META_KEY, $status_id );
			if ( $status_id === Item_Status::IN_QUEUE ) {
				$this->set_date_time_enqueued( gmdate( self::DATE_FORMAT ) );
			} // endif
		} // endif
		unset( $this->item_status ); // allow it to be re-acquired
	} // function

	/**
	 * Get a string representing the date and time the item's status was changed to In Queue
	 * This allows items in the queue to be served in order of arrival
	 * @return string
	 */
	public function get_date_time_enqueued() {
		if ( ! isset( $this->date_time_enqueued ) ) {
			$date_time_string = get_post_meta( $this->get_post_id(), self::DATE_TIME_ENQUEUED_META_KEY, $single = TRUE );
			if ( ! empty( $date_time_string ) ) {
				$this->date_time_enqueued = $date_time_string;
			} // endif
		} // endif
		return $this->date_time_enqueued;
	} // function
	
	/**
	 * Set the date/time the item was enqueued
	 * @param	string	$date_time_string
	 */
	private function set_date_time_enqueued( $date_time_string ) {

		if ( empty( $date_time_string ) )  {
			// The new value is empty or is the default so we should remove the metadata
			delete_post_meta( $this->get_post_id(), self::DATE_TIME_ENQUEUED_META_KEY );
			$this->date_time_enqueued = NULL;
		} else {
			// We will assign the current date and time as the time enqueued for this item
			// First, we'll check to make sure we don't overwrite a previous date/time assigned
			// If the item was mistakenly assigned "Fixed" but then put back in the queue, it should not lose its place
			$existing_date_time = $this->get_date_time_enqueued();
			if ( ! isset( $existing_date_time ) ) {
				$this->date_time_enqueued = gmdate( self::DATE_FORMAT );
				update_post_meta( $this->get_post_id(), self::DATE_TIME_ENQUEUED_META_KEY, $this->date_time_enqueued );
			} // endif
		} // endif
		
	} // function
	
	/**
	 * Returns TRUE if the current user has authority to modify the registration details like repair status, fixer station,
	 * or item type
	 * @return boolean
	 */
	public function get_can_current_user_update_visitor_registration_details() {
		
		// Note that people who can edit items are allowed to update the details of ANY item through the Visitor Reg system
		// We could restrict it to make sure that the user has authored this item, or has edit_others authority but if we
		//  did that then when multiple volunteers are working at the same event they may have difficulties modifying each
		//  other's items.  The user should only get to the Visitor Reg Manager page for an event they are authorized to
		//  register items for.  After that, the user should be able to update any item in the list for that event.
		$result = current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL );
		return $result;
		
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
		$supports = array( 
				'title',
//				'editor',
				'thumbnail',
//				'comments',
				'author'
		);
		$capability_singular = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_SINGULAR;
		$capability_plural = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		$args = array(
				'labels'				=> $labels,
				'description'			=> 'Items', // Internal description, not visible externally
				'public'				=> FALSE, // is it publicly visible? e.g. does it have its own page?
				'exclude_from_search'	=> TRUE, // exclude from regular search results?
				'publicly_queryable'	=> FALSE, // is it queryable? e.g. ?post_type=item
				'show_ui'				=> TRUE, // is there a default UI for managing these in wp-admin?
				// There is no reason to make these available in REST or to use Block editor
				'show_in_rest'			=> FALSE, // is it accessible via REST, TRUE is required for the Gutenberg editor!!!
				'show_in_nav_menus'		=> FALSE, // available for selection in navigation menus?
				'show_in_menu'			=> Admin_Menu_Page::get_CPT_show_in_menu( $capability_plural ), // Where to show in admin menu? The main menu page will determine this
				'show_in_admin_bar'		=> FALSE, // Whether to include this post type in the admin bar
				'menu_position'			=> Admin_Menu_Page::get_menu_position(), // Menu order position
				'menu_icon'				=> $icon,
				'hierarchical'			=> FALSE, // Can each post have a parent?
				'supports'				=> $supports,
				'taxonomies'			=> array(
												Item_Type::TAXONOMY_NAME,
												Fixer_Station::TAXONOMY_NAME,
				),
				'has_archive'			=> FALSE, // is there an archive page?
				// TODO - Do items really have their own page, what's the right url?
				'rewrite'				=> FALSE,
/*
					array(
						'slug'			=> Settings::get_items_slug(),
						'with_front'	=> FALSE,
					),
*/
				// Specifying capability_type restricts access to ONLY the roles that are granted these capabilities
				// Removing capability_type defaults to (post) and means, for example, if you can edit posts you can edit this CPT
				'capability_type'		=> array( $capability_singular, $capability_plural ),
				'map_meta_cap'			=> TRUE, // FALSE is the default but this arg is not needed when specifying capabilities
//				'can_export'			=> FALSE,
		);
		register_post_type( self::POST_TYPE, $args );

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