<?php
namespace Reg_Man_RC\Control;


use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Item_Suggestion;

/**
 * The user role controller
 *
 * This class provides the controller function for working with user roles
 *
 * @since v0.1.0
 *
 */
class User_Role_Controller {

	// Volunteer - author/modify their own volunteer post or volunteer registrations
	const VOLUNTEER_ROLE_NAME				= 'reg_man_rc_volunteer';
	
	// Item registrar - add/remove items
	const ITEM_REGISTRAR_ROLE_NAME			= 'reg_man_rc_item_registrar';

	// Events co-ordinator - add/remove events
	const EVENT_COORDINATOR_ROLE_NAME		= 'reg_man_rc_event_coordinator';

	// Volunteer co-ordinator - add/remove volunteers and volunteer registrations
	const VOLUNTEER_COORDINATOR_ROLE_NAME	= 'reg_man_rc_volunteer_coordinator';

	// COMMUNITY_EVENT_LEADER <- This is what Paul and Fern are calling it for now
	const COMMUNITY_EVENT_LEADER_ROLE_NAME	= 'reg_man_rc_community_event_leader';
	
	// Event capabilities allows people to work with events ( e.g. Event Coordinator )
	const EVENT_CAPABILITY_TYPE_SINGULAR 			= 'reg_man_rc_event';
	const EVENT_CAPABILITY_TYPE_PLURAL 				= 'reg_man_rc_events';

	// Venue capabilities allows people to work with venues ( e.g. Event Coordinator )
	const VENUE_CAPABILITY_TYPE_SINGULAR 			= 'reg_man_rc_venue';
	const VENUE_CAPABILITY_TYPE_PLURAL 				= 'reg_man_rc_venues';

	// Calendar capabilities allows people to work with calendars ( e.g. administrator )
	const CALENDAR_CAPABILITY_TYPE_SINGULAR 		= 'reg_man_rc_calendar';
	const CALENDAR_CAPABILITY_TYPE_PLURAL 			= 'reg_man_rc_calendars';

	// Item Registration capabilities allows people to work with item registrations, visitors and item suggestions ( e.g. Item Registrar )
	const ITEM_REG_CAPABILITY_TYPE_SINGULAR 		= 'reg_man_rc_item';
	const ITEM_REG_CAPABILITY_TYPE_PLURAL 			= 'reg_man_rc_items';

	// Visitor capabilities allows people to work with visitors ( e.g. Item Registrar )
	const VISITOR_CAPABILITY_TYPE_SINGULAR 			= 'reg_man_rc_visitor';
	const VISITOR_CAPABILITY_TYPE_PLURAL 			= 'reg_man_rc_visitors';

	// Item suggestion capabilities allows people to work with item suggestions ( e.g. Item Registrar )
	const ITEM_SUGGESTION_CAPABILITY_TYPE_SINGULAR 	= 'reg_man_rc_item_suggestion';
	const ITEM_SUGGESTION_CAPABILITY_TYPE_PLURAL 	= 'reg_man_rc_item_suggestions';

	// Volunteer capabilities allows people to work with volunteers and volunteer registrations ( E.g. Volunteer Coordinator )
	const VOLUNTEER_CAPABILITY_TYPE_SINGULAR 		= 'reg_man_rc_volunteer';
	const VOLUNTEER_CAPABILITY_TYPE_PLURAL 			= 'reg_man_rc_volunteers';

	// Volunteer Registration capabilities allows people to work with volunteer registrations ( E.g. Volunteer Coordinator )
	const VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR 	= 'reg_man_rc_volunteer_reg';
	const VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL 		= 'reg_man_rc_volunteer_regs';

	// These constants are used to apply groups of capabilities to roles
	const ROLE_TYPE_ADMINISTRATOR				= 'administrator';
	const ROLE_TYPE_EDITOR						= 'editor';
	const ROLE_TYPE_AUTHOR						= 'author';
	const ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE	= 'author_plus_read_private';
	const ROLE_TYPE_CONTRIBUTOR					= 'contributor';
	const ROLE_TYPE_NONE						= 'none';
	
	/**
	 * Handle plugin activation
	 */
	public static function handle_plugin_activation() {

		// Add our capabilities to the admin role
		self::add_capabilities_for_admin();
		
		// Create the new roles for the plugin
		self::create_default_roles();

	} // function

	/**
	 * Handle plugin deactivation
	 */
	public static function handle_plugin_deactivation() {

		// Remove the capabilities for the roles
		self::remove_all_capabilities();

		// Remove the roles for the plugin
		self::remove_all_roles();

	} // function
	
	/**
	 * Update the volunteer role's capabilities
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	public static function update_volunteer_role_capabilities( $role_type_array ) {
		
		self::update_role_capabilities( self::VOLUNTEER_ROLE_NAME, $role_type_array );
		
	} // function

	/**
	 * Update the item registrar role's capabilities
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	public static function update_item_registrar_role_capabilities( $role_type_array ) {
		
		self::update_role_capabilities( self::ITEM_REGISTRAR_ROLE_NAME, $role_type_array );
		
	} // function

	/**
	 * Update the event coordinator role's capabilities
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	public static function update_event_coordinator_role_capabilities( $role_type_array ) {
		
		self::update_role_capabilities( self::EVENT_COORDINATOR_ROLE_NAME, $role_type_array );
		
	} // function

	/**
	 * Update the volunteer coordinator role's capabilities
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	public static function update_volunteer_coordinator_role_capabilities( $role_type_array ) {
		
		self::update_role_capabilities( self::VOLUNTEER_COORDINATOR_ROLE_NAME, $role_type_array );
		
	} // function

	/**
	 * Update the community event leader role's capabilities
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	public static function update_community_event_leader_role_capabilities( $role_type_array ) {
		
		self::update_role_capabilities( self::COMMUNITY_EVENT_LEADER_ROLE_NAME, $role_type_array );
		
	} // function

	/**
	 * Update the role's capabilities
	 * @param	string		$role_name
	 * @param	string[][]	$role_type_array	An array of ROLE_TYPE_* constants keyed by post type
	 */
	private static function update_role_capabilities( $role_name, $role_type_array ) {
		// The WordPress docs say:
		// To alter the capabilities list in bulk: remove the role using remove_role() and
		//  add it again using add_role() with the new capabilities.
		
		// Remove the role
		remove_role( $role_name );
		
		// Get the new capabilities
		$caps_array = array();
		
		foreach( $role_type_array as $post_type => $role_type ) {
			
			$post_type_caps = self::get_role_capabilities_for_post_type( $post_type, $role_type );
			
			$caps_array = array_merge( $caps_array, $post_type_caps );
			
		} // endfor
		
		$base_caps = self::get_default_base_capabilities();
		
		$caps_array = array_merge( $caps_array, $base_caps );

		// Add the role back
		switch( $role_name ) {
			
			case self::VOLUNTEER_ROLE_NAME:
				self::create_volunteer_role( $caps_array );
				break;
				
			case self::ITEM_REGISTRAR_ROLE_NAME:
				self::create_item_registrar_role( $caps_array );
				break;
				
			case self::EVENT_COORDINATOR_ROLE_NAME:
				self::create_event_coordinator_role( $caps_array );
				break;
				
			case self::VOLUNTEER_COORDINATOR_ROLE_NAME:
				self::create_volunteer_coordinator_role( $caps_array );
				break;
				
			case self::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				self::create_community_event_leader_role( $caps_array );
				break;
				
		} // endswitch
		
	} // function
	

	/**
	 * Get an array of capabilities based on a post type and role type
	 * @param string $post_type	Any post type like "reg-man-rc-item"
	 * @param string $role_type One of the ROLE_TYPE_* constants declared in this class like ROLE_TYPE_AUTHOR
	 * @return string[][]
	 */
	private static function get_role_capabilities_for_post_type( $post_type, $role_type ) {

		switch( $post_type ) {
			
			case 'post':
				$cap_type_singular 	= 'post';
				$cap_type_plural	= 'posts';
				break;

			case Internal_Event_Descriptor::POST_TYPE:
				$cap_type_singular 	= self::EVENT_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::EVENT_CAPABILITY_TYPE_PLURAL;
				break;

			case Venue::POST_TYPE:
				$cap_type_singular 	= self::VENUE_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::VENUE_CAPABILITY_TYPE_PLURAL;
				break;

			case Calendar::POST_TYPE:
				$cap_type_singular 	= self::CALENDAR_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::CALENDAR_CAPABILITY_TYPE_PLURAL;
				break;

			case Item::POST_TYPE:
				$cap_type_singular 	= self::ITEM_REG_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::ITEM_REG_CAPABILITY_TYPE_PLURAL;
				break;

			case Item_Suggestion::POST_TYPE:
				$cap_type_singular 	= self::ITEM_SUGGESTION_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::ITEM_SUGGESTION_CAPABILITY_TYPE_PLURAL;
				break;
				
			case Visitor::POST_TYPE:
				$cap_type_singular 	= self::VISITOR_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::VISITOR_CAPABILITY_TYPE_PLURAL;
				break;

			case Volunteer::POST_TYPE:
				$cap_type_singular 	= self::VOLUNTEER_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::VOLUNTEER_CAPABILITY_TYPE_PLURAL;
				break;

			case Volunteer_Registration::POST_TYPE:
				$cap_type_singular 	= self::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR;
				$cap_type_plural	= self::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;
				break;

			default:
				/* Translators: %1$s is a post type like "reg-man-rc-item" */
				$msg_format = __( 'Unable to get role capabilities for unknown post type: %1$s', 'reg-man-rc' );
				Error_Log::log_msg( sprintf( $msg_format, $post_type ) );
				break;

		} // endswitch

		if ( ! isset( $cap_type_singular ) || ! isset( $cap_type_plural ) ) {
			
			$result = array(); // Defensive
			
		} else {
			
			switch( $role_type ) {
	
				case self::ROLE_TYPE_ADMINISTRATOR :
					$result = array(
						'edit_'				. $cap_type_singular	=> TRUE,
						'read_'				. $cap_type_singular	=> TRUE,
						'delete_'			. $cap_type_singular	=> TRUE,
						'create_'			. $cap_type_plural		=> TRUE,
						'delete_others_'	. $cap_type_plural		=> TRUE,
						'delete_'			. $cap_type_plural		=> TRUE,
						'delete_private_'	. $cap_type_plural		=> TRUE,
						'delete_published_'	. $cap_type_plural		=> TRUE,
						'edit_others_'		. $cap_type_plural		=> TRUE,
						'edit_'				. $cap_type_plural		=> TRUE,
						'edit_private_'		. $cap_type_plural		=> TRUE,
						'edit_published_'	. $cap_type_plural		=> TRUE,
						'publish_'			. $cap_type_plural		=> TRUE,
						'read_private_'		. $cap_type_plural		=> TRUE,
					);
					break;
	
				case self::ROLE_TYPE_EDITOR :
					$result = array(
						'edit_'				. $cap_type_singular	=> TRUE,
						'read_'				. $cap_type_singular	=> TRUE,
						'delete_'			. $cap_type_singular	=> TRUE,
						'create_'			. $cap_type_plural		=> TRUE,
						'delete_others_'	. $cap_type_plural		=> TRUE,
						'delete_'			. $cap_type_plural		=> TRUE,
						'delete_private_'	. $cap_type_plural		=> TRUE,
						'delete_published_'	. $cap_type_plural		=> TRUE,
						'edit_others_'		. $cap_type_plural		=> TRUE,
						'edit_'				. $cap_type_plural		=> TRUE,
						'edit_private_'		. $cap_type_plural		=> TRUE,
						'edit_published_'	. $cap_type_plural		=> TRUE,
						'publish_'			. $cap_type_plural		=> TRUE,
						'read_private_'		. $cap_type_plural		=> TRUE,
					);
					break;
	
				case self::ROLE_TYPE_AUTHOR :
					$result = array(
						'edit_'				. $cap_type_singular	=> TRUE,
						'read_'				. $cap_type_singular	=> TRUE,
						'delete_'			. $cap_type_singular	=> TRUE,
						'create_'			. $cap_type_plural		=> TRUE,
						'delete_'			. $cap_type_plural		=> TRUE,
						'delete_published_'	. $cap_type_plural		=> TRUE,
						'edit_'				. $cap_type_plural		=> TRUE,
						'edit_published_'	. $cap_type_plural		=> TRUE,
						'publish_'			. $cap_type_plural		=> TRUE,
					);
					break;
	
				case self::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE :
					$result = array(
						'edit_'				. $cap_type_singular	=> TRUE,
						'read_'				. $cap_type_singular	=> TRUE,
						'delete_'			. $cap_type_singular	=> TRUE,
						'create_'			. $cap_type_plural		=> TRUE,
						'delete_'			. $cap_type_plural		=> TRUE,
						'delete_published_'	. $cap_type_plural		=> TRUE,
						'edit_'				. $cap_type_plural		=> TRUE,
						'edit_published_'	. $cap_type_plural		=> TRUE,
						'publish_'			. $cap_type_plural		=> TRUE,
						'read_private_'		. $cap_type_plural		=> TRUE,
					);
					break;
	
				case self::ROLE_TYPE_CONTRIBUTOR :
					$result = array(
						'edit_'				. $cap_type_singular	=> TRUE,
						'read_'				. $cap_type_singular	=> TRUE,
						'delete_'			. $cap_type_singular	=> TRUE,
						'create_'			. $cap_type_plural		=> TRUE,
						'delete_'			. $cap_type_plural		=> TRUE,
						'edit_'				. $cap_type_plural		=> TRUE,
					);
					break;
	
				default :
					$result = array();
					break;
					
			} // endswitch

		} // endif

		return $result;
		
	} // function

	private static function get_administrator_role_capabilities() {

		// An administrator has full control over all of our custom types
		$event_caps = self::get_role_capabilities_for_post_type( Internal_Event_Descriptor::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$venue_caps = self::get_role_capabilities_for_post_type( Venue::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$calendar_caps = self::get_role_capabilities_for_post_type( Calendar::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$item_caps = self::get_role_capabilities_for_post_type( Item::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$visitor_caps = self::get_role_capabilities_for_post_type( Visitor::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$item_sug_caps = self::get_role_capabilities_for_post_type( Item_Suggestion::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$volunteer_caps = self::get_role_capabilities_for_post_type( Volunteer::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$volunteer_reg_caps = self::get_role_capabilities_for_post_type( Volunteer_Registration::POST_TYPE, self::ROLE_TYPE_ADMINISTRATOR );

		$result = array_merge(
						$event_caps,
						$venue_caps,
						$calendar_caps,
						$item_caps,
						$visitor_caps,
						$item_sug_caps,
						$volunteer_caps,
						$volunteer_reg_caps
					);
		
		return $result;

	} // function
	
	private static function get_default_base_capabilities() {
		
		$base_caps = array(
				'read'			=> TRUE,
//				'upload_files'	=> TRUE,
			);
	
		return $base_caps; 
		
	} // function

	private static function get_volunteer_role_default_capabilities() {

		$base_caps = self::get_default_base_capabilities();
		
		$result = $base_caps; // Volunteers don't need any special capabilities

		return $result;

	} // function

	private static function get_item_registrar_role_default_capabilities() {

		// The item registrar works with items, visitors and item suggestions
		
		$base_caps = self::get_default_base_capabilities();
		
		$item_caps = self::get_role_capabilities_for_post_type( Item::POST_TYPE, self::ROLE_TYPE_AUTHOR );

		$visitor_caps = self::get_role_capabilities_for_post_type( Visitor::POST_TYPE, self::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE );

		$item_suggestion_caps = self::get_role_capabilities_for_post_type( Item_Suggestion::POST_TYPE, self::ROLE_TYPE_AUTHOR );

		$result = array_merge(
				$base_caps,
				$item_caps,
				$visitor_caps,
				$item_suggestion_caps,
		);

		return $result;
	} // function


	private static function get_event_coordinator_role_default_capabilities() {

		$base_caps = self::get_default_base_capabilities();
		
		// The Event Coordinator works with events or venues
		$event_caps = self::get_role_capabilities_for_post_type( Internal_Event_Descriptor::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		
		$venue_caps = self::get_role_capabilities_for_post_type( Venue::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		
		// The Event Coordinator can also author posts (about new events)
		$post_caps = self::get_role_capabilities_for_post_type( 'post', self::ROLE_TYPE_AUTHOR );

		$result = array_merge(
				$base_caps,
				$event_caps,
				$venue_caps,
				$post_caps,
		);

		return $result;
	} // function


	private static function get_volunteer_coordinator_role_default_capabilities() {

		// The volunteer coordinator works with volunteer and volunteer registrations
		
		$base_caps = self::get_default_base_capabilities();
		
		$volunteer_caps = self::get_role_capabilities_for_post_type( Volunteer::POST_TYPE, self::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE );
		
		$volunteer_reg_caps = self::get_role_capabilities_for_post_type( Volunteer_Registration::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		
		$result = array_merge(
				$base_caps,
				$volunteer_caps,
				$volunteer_reg_caps,
		);

		return $result;
	} // function


	private static function get_community_event_leader_role_default_capabilities() {

		// The community event leader handles everything related to events in their community
		// including events, venues, items, visitors, volunteers, volunteer registrations and posts

		$base_caps = self::get_default_base_capabilities();
		
		// There is currently a bug ( #50284 ) that prevents the user from adding a new CPT when its page is a submenu
		//   and the user's role does not have 'edit_posts' capability.
		// Allowing this capability means that these users see all our pages under the single "Repair Cafe" menu item 
		$post_caps = self::get_role_capabilities_for_post_type( 'post', self::ROLE_TYPE_CONTRIBUTOR );

		// They can author new events and venues BUT CANNOT touch other people's
		$event_caps = self::get_role_capabilities_for_post_type( Internal_Event_Descriptor::POST_TYPE, self::ROLE_TYPE_AUTHOR );

		$venue_caps = self::get_role_capabilities_for_post_type( Venue::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		
		// Note that they cannot modify any event calendars which are managed by the administrator
		
		// They can author item registrations and contribute (but not publish) visitors
		$item_reg_caps = self::get_role_capabilities_for_post_type( Item::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		
		// They also need to be able to read private visitor posts so they can see all the visitors and their info
		$visitor_caps = self::get_role_capabilities_for_post_type( Visitor::POST_TYPE, self::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE );
		
		// They can register volunteers
		$volunteer_reg_caps = self::get_role_capabilities_for_post_type( Volunteer_Registration::POST_TYPE, self::ROLE_TYPE_AUTHOR );
		// They also need to be able to read private volunteer registration posts so they can see all registration info
		
		// They can create (but not publish) their own volunteer records and register volunteers
		$volunteer_caps = self::get_role_capabilities_for_post_type( Volunteer::POST_TYPE, self::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE );

		$result = array_merge(
				$base_caps,
				$post_caps,
				$event_caps,
				$venue_caps,
				$item_reg_caps,
				$visitor_caps,
				$volunteer_caps,
				$volunteer_reg_caps
		);

		return $result;
	} // function
	
	private static function create_volunteer_role( $capabilities = NULL ) {
		$display_name = __( 'Fixer / Volunteer', 'reg-man-rc' );
		if ( empty( $capabilities ) ) {
			$capabilities = self::get_volunteer_role_default_capabilities();
		} // endif
		add_role( self::VOLUNTEER_ROLE_NAME, $display_name, $capabilities );
	} // function

	private static function create_item_registrar_role( $capabilities = NULL ) {
		$display_name = __( 'Visitor Registrar', 'reg-man-rc' );
		if ( empty( $capabilities ) ) {
			$capabilities = self::get_item_registrar_role_default_capabilities();
		} // endif
		add_role( self::ITEM_REGISTRAR_ROLE_NAME, $display_name, $capabilities );
	} // function

	private static function create_event_coordinator_role( $capabilities = NULL ) {
		$display_name = __( 'Event Coordinator', 'reg-man-rc' );
		if ( empty( $capabilities ) ) {
			$capabilities = self::get_event_coordinator_role_default_capabilities();
		} // endif
		add_role( self::EVENT_COORDINATOR_ROLE_NAME, $display_name, $capabilities );
	} // function

	private static function create_volunteer_coordinator_role( $capabilities = NULL ) {
		$display_name = __( 'Volunteer Coordinator', 'reg-man-rc' );
		if ( empty( $capabilities ) ) {
			$capabilities = self::get_volunteer_coordinator_role_default_capabilities();
		} // endif
		add_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME, $display_name, $capabilities );
	} // function

	private static function create_community_event_leader_role( $capabilities = NULL ) {
		$display_name = __( 'Community Event Leader', 'reg-man-rc' );
		if ( empty( $capabilities ) ) {
			$capabilities = self::get_community_event_leader_role_default_capabilities();
		} // endif
		add_role( self::COMMUNITY_EVENT_LEADER_ROLE_NAME, $display_name, $capabilities );
	} // function

	private static function add_capabilities_for_admin() {
		// Grant capabilities to the admin role
		$role = get_role( 'administrator' );
		$caps = self::get_administrator_role_capabilities();
		foreach ( $caps as $capability => $value ) {
			$role->add_cap( $capability );
		} // endfor
	} // function
	
	private static function create_default_roles() {

		// Basic volunteer role
		self::create_volunteer_role();

		// Item registrar role
		self::create_item_registrar_role();

		// Event Coordinator role
		self::create_event_coordinator_role();

		// Volunteer coordinator role
		self::create_volunteer_coordinator_role();

		// Community Event Leader role
		self::create_community_event_leader_role();

	} // function

	private static function remove_all_roles() {

		// Volunteer role
		remove_role( self::VOLUNTEER_ROLE_NAME );

		// Volunteer coordinator role
		remove_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME );

		// Item registrar role
		remove_role( self::ITEM_REGISTRAR_ROLE_NAME );

		// Event Coordinator role
		remove_role( self::EVENT_COORDINATOR_ROLE_NAME );

		// Event Coordinator role
		remove_role( self::COMMUNITY_EVENT_LEADER_ROLE_NAME );

	} // function

	private static function remove_all_capabilities() {
		
		// Remove all of our capabilities from administrator (this will get rid of all capabilities from the system)
		$role = get_role( 'administrator' );
		if ( isset( $role ) ) {
			$caps = self::get_administrator_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

	} // function

} // class