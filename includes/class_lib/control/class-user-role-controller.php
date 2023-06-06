<?php
namespace Reg_Man_RC\Control;


/**
 * The user role controller
 *
 * This class provides the controller function for working with user roles
 *
 * @since v0.1.0
 *
 */
class User_Role_Controller {

	// Events co-ordinator - add/remove events
	const EVENTS_COORDINATOR_ROLE_NAME = 'reg_man_rc_events_coordinator';

	// Item registrar - add/remove items
	const ITEM_REGISTRAR_ROLE_NAME = 'reg_man_rc_item_registrar';

	// Volunteer co-ordinator - add/remove volunteers and volunteer registrations
	const VOLUNTEER_COORDINATOR_ROLE_NAME = 'reg_man_rc_volunteer_coordinator';

	// Volunteer - author/modify their own volunteer post or volunteer registrations
	const VOLUNTEER_ROLE_NAME = 'reg_man_rc_volunteer';

	// Event capabilities allows people to work with events, venues and calendars ( e.g. Events Coordinator )
	const EVENT_CAPABILITY_TYPE_SINGULAR 			= 'reg_man_rc_event';
	const EVENT_CAPABILITY_TYPE_PLURAL 				= 'reg_man_rc_events';

	// Item Registration capabilities allows people to work with item registrations ( e.g. Item Registrar )
	const ITEM_REG_CAPABILITY_TYPE_SINGULAR 		= 'reg_man_rc_item';
	const ITEM_REG_CAPABILITY_TYPE_PLURAL 			= 'reg_man_rc_items';

	// Volunteer capabilities allows people to work with volunteers ( E.g. Volunteer Coordinator )
	const VOLUNTEER_CAPABILITY_TYPE_SINGULAR 	= 'reg_man_rc_volunteer';
	const VOLUNTEER_CAPABILITY_TYPE_PLURAL 		= 'reg_man_rc_volunteers';

	// Volunteer Registration capabilities allows people to work with volunteer registrations ( E.g. Volunteer Coordinator )
	const VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR 	= 'reg_man_rc_volunteer_reg';
	const VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL 		= 'reg_man_rc_volunteer_regs';


	public static function handle_plugin_activation() {

		// Create the new roles for the plugin
		self::add_roles();

		// Define the capabilities for the roles
		self::add_capabilities();

	} // function

	public static function handle_plugin_deactivation() {

		// Remove the capabilities for the roles
		self::remove_capabilities();

		// Remove the roles for the plugin
		self::remove_roles();

	} // function

	private static function get_role_capabilities_for_type( $role_type, $cap_type_singular, $cap_type_plural ) {
		// TODO: Add manage_categories and moderate_comments ???  We could allow some roles to create new Item Types for example
		switch( $role_type ) {

			case 'administrator' :
				$result = array(
					'read'						=> 'read',
					'edit_post'					=> 'edit_'				. $cap_type_singular,
					'read_post'					=> 'read_'				. $cap_type_singular,
					'delete_post'				=> 'delete_'			. $cap_type_singular,
					'create_posts'				=> 'create_'			. $cap_type_plural,
					'delete_others_posts'		=> 'delete_others_'		. $cap_type_plural,
					'delete_posts'				=> 'delete_'			. $cap_type_plural,
					'delete_private_posts'		=> 'delete_private_'	. $cap_type_plural,
					'delete_published_posts'	=> 'delete_published_'	. $cap_type_plural,
					'edit_others_posts'			=> 'edit_others_'		. $cap_type_plural,
					'edit_posts'				=> 'edit_'				. $cap_type_plural,
					'edit_private_posts'		=> 'edit_private_'		. $cap_type_plural,
					'edit_published_posts'		=> 'edit_published_'	. $cap_type_plural,
					'publish_posts'				=> 'publish_'			. $cap_type_plural,
					'read_private_posts'		=> 'read_private_'		. $cap_type_plural,
					);
				break;

			case 'editor' :
				$result = array(
					'read'						=> 'read',
					'edit_post'					=> 'edit_'				. $cap_type_singular,
					'read_post'					=> 'read_'				. $cap_type_singular,
					'delete_post'				=> 'delete_'			. $cap_type_singular,
					'create_posts'				=> 'create_'			. $cap_type_plural,
					'delete_others_posts'		=> 'delete_others_'		. $cap_type_plural,
					'delete_posts'				=> 'delete_'			. $cap_type_plural,
					'delete_private_posts'		=> 'delete_private_'	. $cap_type_plural,
					'delete_published_posts'	=> 'delete_published_'	. $cap_type_plural,
					'edit_others_posts'			=> 'edit_others_'		. $cap_type_plural,
					'edit_posts'				=> 'edit_'				. $cap_type_plural,
					'edit_private_posts'		=> 'edit_private_'		. $cap_type_plural,
					'edit_published_posts'		=> 'edit_published_'	. $cap_type_plural,
					'publish_posts'				=> 'publish_'			. $cap_type_plural,
					'read_private_posts'		=> 'read_private_'		. $cap_type_plural,
					);
				break;

			case 'author' :
				$result = array(
					'read'						=> 'read',
					'edit_post'					=> 'edit_'				. $cap_type_singular,
					'read_post'					=> 'read_'				. $cap_type_singular,
					'delete_post'				=> 'delete_'			. $cap_type_singular,
					'create_posts'				=> 'create_'			. $cap_type_plural,
					'delete_posts'				=> 'delete_'			. $cap_type_plural,
					'delete_published_posts'	=> 'delete_published_'	. $cap_type_plural,
					'edit_posts'				=> 'edit_'				. $cap_type_plural,
					'edit_published_posts'		=> 'edit_published_'	. $cap_type_plural,
					'publish_posts'				=> 'publish_'			. $cap_type_plural,
					);
				break;

			default :
				$result = array(
					'read'	=> 'read',
				);
		} // endswitch
		return $result;
	} // function

	private static function get_administrator_role_capabilities() {

		// An administrator has full control over all of our custom types
		$event_reg_caps = self::get_role_capabilities_for_type(
				'administrator',
				self::EVENT_CAPABILITY_TYPE_SINGULAR, self::EVENT_CAPABILITY_TYPE_PLURAL );

		$volunteer_caps = self::get_role_capabilities_for_type(
				'administrator',
				self::VOLUNTEER_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_CAPABILITY_TYPE_PLURAL );

		$volunteer_reg_caps = self::get_role_capabilities_for_type(
				'administrator',
				self::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		$item_caps = self::get_role_capabilities_for_type(
				'administrator',
				self::ITEM_REG_CAPABILITY_TYPE_SINGULAR, self::ITEM_REG_CAPABILITY_TYPE_PLURAL );

		$result = array_merge(
						array_values( $event_reg_caps ),
						array_values( $volunteer_caps ),
						array_values( $volunteer_reg_caps ),
						array_values( $item_caps )
					);

		return $result;
	} // function

	private static function get_volunteer_role_capabilities() {

		// Volunteers (fixers and non-fixer volunteers) act as authors of volunteer and item registration records.
		// They can create their own posts for volunteer registration and item registration custom post type.
		// So they can register for an event or register their own items for an event but not modify other people's posts.
		$volunteer_reg_caps = self::get_role_capabilities_for_type(
				'author',
				self::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		$item_caps = self::get_role_capabilities_for_type(
				'author',
				self::ITEM_REG_CAPABILITY_TYPE_SINGULAR, self::ITEM_REG_CAPABILITY_TYPE_PLURAL );

		$result = array_merge( array_values( $volunteer_reg_caps ), array_values( $item_caps ) );

		return $result;
	} // function

	private static function get_events_coordinator_role_capabilities() {

		// The events coordinator can modify any posts related to events.
		// They can create new event custom posts or modify other people's events
		$event_caps = self::get_role_capabilities_for_type(
				'editor',
				self::EVENT_CAPABILITY_TYPE_SINGULAR, self::EVENT_CAPABILITY_TYPE_PLURAL );

		$result = array_values( $event_caps );

		return $result;
	} // function


	private static function get_item_registrar_role_capabilities() {

		// The item registrar acts as an author for their own volunteer registration records,
		//  and they act as an editor for item registrations, creating new records and editing other people's records.
		$volunteer_reg_caps = self::get_role_capabilities_for_type(
				'author',
				self::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		$item_caps = self::get_role_capabilities_for_type(
				'editor',
				self::ITEM_REG_CAPABILITY_TYPE_SINGULAR, self::ITEM_REG_CAPABILITY_TYPE_PLURAL );

		$result = array_merge( array_values( $volunteer_reg_caps ), array_values( $item_caps ) );

		return $result;
	} // function


	private static function get_volunteer_coordinator_role_capabilities() {

		// The volunteer coordinator acts as an editor for volunteer records and volunteer registrations.
		// They can create their own records and they can edit other people's
		$volunteer_caps = self::get_role_capabilities_for_type(
				'editor',
				self::VOLUNTEER_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_CAPABILITY_TYPE_PLURAL );

		$volunteer_reg_caps = self::get_role_capabilities_for_type(
				'editor',
				self::VOLUNTEER_REG_CAPABILITY_TYPE_SINGULAR, self::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );

		$result = array_merge( array_values( $volunteer_caps ), array_values( $volunteer_reg_caps ) );

		return $result;
	} // function


	private static function add_roles() {

		// Basic volunteer role
		$display_name = __( 'Fixer / Volunteer (Repair Café)', 'reg-man-rc' );
		add_role( self::VOLUNTEER_ROLE_NAME,
			$display_name,
			array(
				'read'			=> TRUE,
				'edit_posts'	=> FALSE,
				'delete_posts'	=> FALSE,
				'publish_posts'	=> FALSE,
				'upload_files'	=> FALSE,
			)
		);

		// Volunteer coordinator role
		$display_name = __( 'Volunteer Coordinator (Repair Café)', 'reg-man-rc' );
		add_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME,
			$display_name,
			array(
				'read'			=> TRUE,
				'edit_posts'	=> FALSE,
				'delete_posts'	=> FALSE,
				'publish_posts'	=> FALSE,
				'upload_files'	=> TRUE,
			)
		);

		// Item registrar role
		$display_name = __( 'Item Registrar (Repair Café)', 'reg-man-rc' );
		add_role( self::ITEM_REGISTRAR_ROLE_NAME,
			$display_name,
			array(
				'read'			=> TRUE,
				'edit_posts'	=> FALSE,
				'delete_posts'	=> FALSE,
				'publish_posts'	=> FALSE,
				'upload_files'	=> TRUE,
			)
		);

		// Events coordinator role
		$display_name = __( 'Events Coordinator (Repair Café)', 'reg-man-rc' );
		add_role( self::EVENTS_COORDINATOR_ROLE_NAME,
			$display_name,
			array(
				'read'			=> TRUE,
				'edit_posts'	=> FALSE,
				'delete_posts'	=> FALSE,
				'publish_posts'	=> FALSE,
				'upload_files'	=> TRUE,
			)
		);

	} // function

	private static function remove_roles() {

		// Volunteer role
		remove_role( self::VOLUNTEER_ROLE_NAME );

		// Volunteer coordinator role
		remove_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME );

		// Item registrar role
		remove_role( self::ITEM_REGISTRAR_ROLE_NAME );

		// Events coordinator role
		remove_role( self::EVENTS_COORDINATOR_ROLE_NAME );

	} // function

	private static function add_capabilities() {
		// Grant capabilities to the admin role
		$role = get_role( 'administrator' );
		$caps = self::get_administrator_role_capabilities();
		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		} // endfor

		// Volunteer role
		$role = get_role( self::VOLUNTEER_ROLE_NAME );
		$caps = self::get_volunteer_role_capabilities();
		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		} // endfor

		// Volunteer coordinator role
		$role = get_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME );
		$caps = self::get_volunteer_coordinator_role_capabilities();
		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		} // endfor

		// Item registrar role
		$role = get_role( self::ITEM_REGISTRAR_ROLE_NAME );
		$caps = self::get_item_registrar_role_capabilities();
		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		} // endfor

		// Events coordinator role
		$role = get_role( self::EVENTS_COORDINATOR_ROLE_NAME );
		$caps = self::get_events_coordinator_role_capabilities();
		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		} // endfor

	} // function

	private static function remove_capabilities() {
		// Remove the capabilities from administrator
		$role = get_role( 'administrator' );
		if ( isset( $role ) ) {
			$caps = self::get_administrator_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

		// Remove capabilities from the volunteer role
		$role = get_role( self::VOLUNTEER_ROLE_NAME );
		if ( isset( $role ) ) {
			$caps = self::get_volunteer_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

		// Remove capabilities from the volunteer coordinator role
		$role = get_role( self::VOLUNTEER_COORDINATOR_ROLE_NAME );
		if ( isset( $role ) ) {
			$caps = self::get_volunteer_coordinator_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

		// Remove capabilities from the item registrar role
		$role = get_role( self::ITEM_REGISTRAR_ROLE_NAME );
		if ( isset( $role ) ) {
			$caps = self::get_item_registrar_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

		// Remove capabilities from the events coordinator role
		$role = get_role( self::EVENTS_COORDINATOR_ROLE_NAME );
		if ( isset( $role ) ) {
			$caps = self::get_events_coordinator_role_capabilities();
			foreach ( $caps as $capability ) {
				$role->remove_cap( $capability );
			} // endfor
		} // endif

	} // function

} // class