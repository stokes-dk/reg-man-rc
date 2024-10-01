<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Venue;

/**
 * This class renders a section of user roles settings page related to a role's capabilities
 *
 */
class User_Role_Settings_Section {
	
	private $role_name;
	private $role;
	
	private $tab_id;
	private $page_slug;
	
	private $section_id;
		
	private $section_title;

	private function __construct() { }
	
	/**
	 * Create an instance of this class
	 * @param string $role_name
	 * @return self
	 */
	public static function create( $role_name ) {

		$result = new self();

		$result->role_name = $role_name;

		$result->tab_id = Settings_Admin_Page::ROLES_AND_CAPS_TAB_ID;
		$result->page_slug = Settings_Admin_Page::MENU_SLUG;
		
		return $result;
		
	} // function;
	
	private function get_role_name() {
		return $this->role_name;
	} // function
	
	/**
	 * Get the WP_Role object
	 * @return \WP_Role|NULL
	 */
	private function get_role() {
		if ( ! isset( $this->role ) ) {
			$this->role = get_role( $this->get_role_name() );
		} // endif
		return $this->role;
	} // function
	
	private function get_tab_id() {
		return $this->tab_id;
	} // function
	
	private function get_option_group() {
		$result = $this->get_tab_id(); // For simplicity, the option group is the tab ID
		return $result;
	} // function
	
	private function get_page_slug() {
		return $this->page_slug;
	} // function
	
	private function get_section_id() {
		if ( ! isset( $this->section_id ) ) {
			$tab_id = $this->get_tab_id();
			$role_name = $this->get_role_name();
			$this->section_id = "{$tab_id}-{$role_name}";
		} // endif
		return $this->section_id;
	} // function
	
	/**
	 * Get the title for this section
	 * @return string
	 */
	private function get_section_title() {

		if ( ! isset( $this->section_title ) ) {

			$wp_roles = wp_roles();
			$role_display_names_array = $wp_roles->get_names();
			$role_name = $this->get_role_name();
			$role_title = isset( $role_display_names_array[ $role_name ] ) ? $role_display_names_array[ $role_name ] : $role_name;
		
			$user_count = $this->get_role_user_count();

			/* Translators: %s is a count of users with a specific role */
			$role_count_text = sprintf( _n( '%s user', '%s users', $user_count ), number_format_i18n( $user_count ) );
			
			if ( $user_count > 0 ) {
				$users_href = admin_url( "users.php?role={$role_name}" );
				$role_count_text = "<a href=\"{$users_href}\">$role_count_text</a>";
			} // endif
			
			/* Translators %1$s is a role name, %2$s is a count of users of that role */
			$title_format = _x( '%1$s ( %2$s )', 'A role name with a count of users', 'reg-man-rc' );
			
			$this->section_title = sprintf( $title_format, $role_title, $role_count_text );
			
		} // endif
		
		return $this->section_title;
		
	} // function

	/**
	 * Add a settings section for this user role
	 */
	public function add_settings_section() {
		
		$role = $this->get_role();
		if ( ! empty( $role ) ) {
			
			$section_id = $this->get_section_id();
			$section_title = $this->get_section_title();
			$desc_fn = array( $this, 'render_section_description' ); // used to echo description content between heading and fields
			$page_slug = $this->get_page_slug();
			add_settings_section( $section_id, $section_title, $desc_fn, $page_slug );
	
			$this->add_capabilities_settings();
		} // endif
	} // function

	/**
	 * Add a settings section for this user role
	 */
	private function add_capabilities_settings() {

		$role_name = $this->get_role_name();
		
		$option_group = $this->get_option_group();
		$option_name = "{$role_name}_capabilities";
		
		$page_slug = $this->get_page_slug();
		$section_id = $this->get_section_id();

		$title = __( 'Capabilities', 'reg-man-rc' );
		
		$render_fn = array( $this, 'render_capabilities_settings' );
		add_settings_field( $option_name, $title, $render_fn, $page_slug, $section_id );
		
		$args = array( 'sanitize_callback' => array( $this, 'sanitize_capabilities_settings' ) );
		register_setting( $option_group, $option_name, $args );

	} // function
	
	/**
	 * Render the capabilities settings
	 */
	public function render_capabilities_settings( $value ) {
		
		$role_name = $this->get_role_name();

		switch( $role_name ) {
			
			case User_Role_Controller::VOLUNTEER_ROLE_NAME:
			default:
				echo __( 'This role has no special capabilities.', 'reg-man-rc' );
				break;
			
			case User_Role_Controller::EVENT_COORDINATOR_ROLE_NAME:
				$this->render_events_capability_setting();
				$this->render_posts_capability_setting();
				break;
			
			case User_Role_Controller::VOLUNTEER_COORDINATOR_ROLE_NAME:
				$this->render_volunteer_registrations_capability_setting();
				break;
			
			case User_Role_Controller::ITEM_REGISTRAR_ROLE_NAME:
				$this->render_items_capability_setting();
				break;
				
			case User_Role_Controller::VOLUNTEER_COORDINATOR_ROLE_NAME:
				$this->render_volunteer_registrations_capability_setting();
				break;
				
			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				$this->render_events_capability_setting();
				$this->render_volunteer_registrations_capability_setting();
				$this->render_items_capability_setting();
				$this->render_posts_capability_setting();
				break;
				
		} // endswitch
		
		
	} // function
	
	private function get_visitor_role_type_for_items_role_type( $item_role_type ) {
		
	} // function
	/**
	 * Handle the updated value submitted by the user
	 * @param string[] $value
	 * @return boolean
	 */
	public function sanitize_capabilities_settings( $value ) {

		$role_name = $this->get_role_name();

//		Error_Log::var_dump( $role_name, $value );

		if ( ! is_array( $value ) ) {
			return; // EXIT POINT!! There's nothing to do here, no cpas specified, this may be the volunteer role
		} // endif

		// First we need to fill out all the post types and role types based on the setting values
		// When Event post type is specified, we need to include Venue
		// When Item post type is specified, we need to include Visitor
		// When Voluteer_Reg post type is specified, we need to include Volunteer
		
		$role_type_array = array();
		
		foreach( $value as $post_type => $role_type ) {

			$role_type_array[ $post_type ] = $role_type;
			
			switch( $post_type ) {
				
				case Internal_Event_Descriptor::POST_TYPE;
					$venue_role_type = $role_type;
					$role_type_array[ Venue::POST_TYPE ] = $venue_role_type;
					break;
				
				case Item::POST_TYPE;
					if ( $role_type === User_Role_Controller::ROLE_TYPE_AUTHOR ) {
						$visitor_role_type = User_Role_Controller::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE;
					} else {
						$visitor_role_type = $role_type;
					} // endif
					$role_type_array[ Visitor::POST_TYPE ] = $visitor_role_type;
					break;
				
				case Volunteer_Registration::POST_TYPE;
					if ( $role_type === User_Role_Controller::ROLE_TYPE_AUTHOR ) {
						$volunteer_role_type = User_Role_Controller::ROLE_TYPE_AUTHOR_PLUS_READ_PRIVATE;
					} else {
						$volunteer_role_type = $role_type;
					} // endif
					$role_type_array[ Volunteer::POST_TYPE ] = $volunteer_role_type;
					break;
			} // endswitch
			
		} // endfor
		
		switch( $role_name ) {

			case User_Role_Controller::ITEM_REGISTRAR_ROLE_NAME:
				User_Role_Controller::update_item_registrar_role_capabilities( $role_type_array );
				break;
				
			case User_Role_Controller::EVENT_COORDINATOR_ROLE_NAME:
				User_Role_Controller::update_event_coordinator_role_capabilities( $role_type_array );
				break;
				
			case User_Role_Controller::VOLUNTEER_COORDINATOR_ROLE_NAME:
				User_Role_Controller::update_volunteer_coordinator_role_capabilities( $role_type_array );
				break;
				
			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				User_Role_Controller::update_community_event_leader_role_capabilities( $role_type_array );
				break;
				
			default:
				// do nothing
				break;
				
		} // function

		return FALSE; // This will prevent WP from storing the value in the options table, which is what I want here

	} // function
	

	/**
	 * Get a count of users for the current role
	 * @return	int
	 */
	private function get_role_user_count() {

		$role_name = $this->get_role_name();
		
		$args = array(
				'role'		=> $role_name,
				'orderby'	=> 'user_nice_name',
				'order'		=> 'ASC',
				'fields'	=> 'display_name',
		);
		$users_array = get_users( $args );
		$result = count( $users_array );
		return $result;
		
	} // function
	
	/**
	 * Render the description for this section
	 */
	public function render_section_description() {
		
		$role_name = $this->get_role_name();
		
		switch( $role_name ) {

			case User_Role_Controller::VOLUNTEER_ROLE_NAME:
				$post_type = Volunteer::POST_TYPE;
				$post_type_object = get_post_type_object( $post_type );
				$label = $post_type_object->label;
				$admin_href = admin_url( "edit.php?post_type={$post_type}" );
				$admin_link = "<a href=\"{$admin_href}\">$label</a>";
				/* Translators: %1$s is a link to the admin page for fixers & volunteers */
				$desc = __(
					'A role that can be assigned to a WordPress user created for a Fixer or Volunteer.' .
					'  If a Fixer or Volunteer\'s email address matches a user in the system then they will' .
					' be required to provide their user password to access the Volunteer Area.' .
					'  The fixers and volunteers defined to the system are here: %1$s.  '
					, 'reg-man-rc' );
				echo sprintf( $desc, $admin_link );
				break;
				
			case User_Role_Controller::ITEM_REGISTRAR_ROLE_NAME:
				$desc = __(
					'Responsible for registering visitors and their items (usually during an event), and recording repair outcomes when possible.'
					, 'reg-man-rc' );
				echo $desc;
				break;
				
			case User_Role_Controller::EVENT_COORDINATOR_ROLE_NAME:
				$desc = __(
					'Responsible for maintaining the calendar of events.' .
					'  This can include creating events, and creating posts to publicize events.'
					, 'reg-man-rc' );
				echo $desc;
				break;
				
			case User_Role_Controller::VOLUNTEER_COORDINATOR_ROLE_NAME:
				$desc = __(
					'Responsible for maintaining the list of volunteers and helping with volunteer event registration.' .
					'  This can include creating new fixer & volunteer records, and creating fixer / volunteer registrations.'
					, 'reg-man-rc' );
				echo $desc;
				break;
				
			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				$desc = __( 'Responsible for running events in their community.  ' .
					'This can include creating events, registering volunteers, registering visitors and their items, ' .
					'and creating posts to publicize events.', 'reg-man-rc' );
				echo $desc;
				break;
				
		} // endswitch
	} // function

	private function get_capability_setting_label( $role_type, $post_type_name, $post_type = NULL ) {
		
		switch( $role_type ) {
			
			case User_Role_Controller::ROLE_TYPE_EDITOR:
				/* Translators: %1$s is the name of the post type "Events" */
				$label_format = __( 'Manage all %1$s - Full control over all %1$s including those created by other users', 'reg-man-rc' );
				break;
			
			case User_Role_Controller::ROLE_TYPE_AUTHOR:
				if ( ( $post_type == Item::POST_TYPE ) || ( $post_type == Volunteer_Registration::POST_TYPE ) ) {
					/* Translators: %1$s is the name of the post type "Events" */
					$label_format = __( 'Create, edit and register %1$s - cannot modify those of other users', 'reg-man-rc' );
				} else {
					/* Translators: %1$s is the name of the post type "Events" */
					$label_format = __( 'Create, edit and publish %1$s - cannot modify those of other users', 'reg-man-rc' );
				} // endif
				break;
			
			case User_Role_Controller::ROLE_TYPE_CONTRIBUTOR:
				/* Translators: %1$s is the name of the post type "Events" */
				$label_format = __( 'Submit %1$s for approval - can create and edit %1$s but must be published by another user like system administrator', 'reg-man-rc' );
				break;

			case User_Role_Controller::ROLE_TYPE_NONE:
			default:
				/* Translators: %1$s is the name of the post type "Events" */
				$label_format = __( 'Cannot work with %1$s', 'reg-man-rc' );
				break;
			
		} // endswitch
		
		$result = sprintf( $label_format, $post_type_name );
		
		return $result;
		
	} // function
	/**
	 * Render the inputs for authoring events
	 */
	public function render_events_capability_setting() {
		
		$post_type = Internal_Event_Descriptor::POST_TYPE;
		$cap_type_plural = User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL;
		
		$post_type_object = get_post_type_object( $post_type );
		$post_type_name = $post_type_object->labels->name;
		
		$editor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_EDITOR, $post_type_name );
		$author_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_AUTHOR, $post_type_name );
		$contributor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_CONTRIBUTOR, $post_type_name );
		$none_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_NONE, $post_type_name );
		
		$role_name = $this->get_role_name();
		
		switch( $role_name ) {

			case User_Role_Controller::EVENT_COORDINATOR_ROLE_NAME:
				$options = array(
						User_Role_Controller::ROLE_TYPE_EDITOR		=> $editor_label,
						User_Role_Controller::ROLE_TYPE_AUTHOR		=> $author_label,
						User_Role_Controller::ROLE_TYPE_CONTRIBUTOR	=> $contributor_label,
				);
				break;

			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				// Note that CEL must be able to work events or else giving them edit_items means they can
				// register items to other people's events, like Registrar, which is not what we want
				$options = array(
						User_Role_Controller::ROLE_TYPE_AUTHOR		=> $author_label,
						User_Role_Controller::ROLE_TYPE_CONTRIBUTOR	=> $contributor_label,
//						User_Role_Controller::ROLE_TYPE_NONE		=> $none_label,
				);
				break;

			default:
				$options = array(
						User_Role_Controller::ROLE_TYPE_NONE		=> $none_label,
				);
				break;
				
		} // endswitch

		$role = $this->get_role();
		
		if ( $role->has_cap( "edit_others_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_EDITOR;
			
		} elseif( $role->has_cap( "publish_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_AUTHOR;
			
		} elseif( $role->has_cap( "edit_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_CONTRIBUTOR;
			
		} else {
			
			$selected = User_Role_Controller::ROLE_TYPE_NONE;
			
		} // endif
			
		$this->render_capabilities_inputs_for_post_type( $post_type, $post_type_name, $options, $selected );
		
	} // function
	
	/**
	 * Render the inputs for authoring events
	 */
	public function render_items_capability_setting() {
		
		$post_type = Item::POST_TYPE;
		$cap_type_plural = User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		
		$post_type_name = __( 'Visitors & Items', 'reg-man-rc' );
		
		$editor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_EDITOR, $post_type_name, $post_type );
		$author_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_AUTHOR, $post_type_name, $post_type );
//		$contributor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_CONTRIBUTOR, $post_type_name, $post_type );
		$none_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_NONE, $post_type_name, $post_type );
		
		$role_name = $this->get_role_name();
		
		switch( $role_name ) {

			case User_Role_Controller::ITEM_REGISTRAR_ROLE_NAME:
				$options = array(
						User_Role_Controller::ROLE_TYPE_EDITOR						=> $editor_label,
						User_Role_Controller::ROLE_TYPE_AUTHOR						=> $author_label,
				);
				break;

			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				$options = array(
//						User_Role_Controller::ROLE_TYPE_EDITOR						=> $editor_label,
						User_Role_Controller::ROLE_TYPE_AUTHOR						=> $author_label,
						User_Role_Controller::ROLE_TYPE_NONE						=> $none_label,
				);
				break;

			default:
				$options = array(
						User_Role_Controller::ROLE_TYPE_NONE						=> $none_label,
				);
				break;
				
		} // endswitch

		$role = $this->get_role();
		
		if ( $role->has_cap( "edit_others_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_EDITOR;
			
		} elseif( $role->has_cap( "publish_{$cap_type_plural}" ) ) {

			$selected = User_Role_Controller::ROLE_TYPE_AUTHOR;
				
		} elseif( $role->has_cap( "edit_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_CONTRIBUTOR;
			
		} else {
			
			$selected = User_Role_Controller::ROLE_TYPE_NONE;
			
		} // endif
			
		$this->render_capabilities_inputs_for_post_type( $post_type, $post_type_name, $options, $selected );
		
	} // function
	
	/**
	 * Render the inputs for authoring events
	 */
	public function render_volunteer_registrations_capability_setting() {
		
		$post_type = Volunteer_Registration::POST_TYPE;
		$cap_type_plural = User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL;
		
		$post_type_name = __( 'Fixers & Volunteers', 'reg-man-rc' );
		
		$editor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_EDITOR, $post_type_name, $post_type );
		$author_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_AUTHOR, $post_type_name, $post_type );
//		$contributor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_CONTRIBUTOR, $post_type_name, $post_type );
		$none_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_NONE, $post_type_name, $post_type );
		
		$role_name = $this->get_role_name();
		
		switch( $role_name ) {

			case User_Role_Controller::VOLUNTEER_COORDINATOR_ROLE_NAME:
				$options = array(
						User_Role_Controller::ROLE_TYPE_EDITOR						=> $editor_label,
						User_Role_Controller::ROLE_TYPE_AUTHOR						=> $author_label,
				);
				break;

			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				$options = array(
//						User_Role_Controller::ROLE_TYPE_EDITOR						=> $editor_label,
						User_Role_Controller::ROLE_TYPE_AUTHOR						=> $author_label,
						User_Role_Controller::ROLE_TYPE_NONE						=> $none_label,
				);
				break;

			default:
				$options = array(
						User_Role_Controller::ROLE_TYPE_NONE						=> $none_label,
				);
				break;
				
		} // endswitch

		$role = $this->get_role();
		
		if ( $role->has_cap( "edit_others_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_EDITOR;
			
		} elseif( $role->has_cap( "publish_{$cap_type_plural}" ) ) {

			$selected = User_Role_Controller::ROLE_TYPE_AUTHOR;
				
		} elseif( $role->has_cap( "edit_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_CONTRIBUTOR;
			
		} else {
			
			$selected = User_Role_Controller::ROLE_TYPE_NONE;
			
		} // endif
			
		$this->render_capabilities_inputs_for_post_type( $post_type, $post_type_name, $options, $selected );
		
	} // function
	
	/**
	 * Render the inputs for authoring posts
	 */
	public function render_posts_capability_setting() {
		
		$post_type = 'post';
		$cap_type_plural = 'posts';
		
		$post_type_object = get_post_type_object( $post_type );
		$post_type_name = $post_type_object->labels->name;
		
//		$editor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_EDITOR, $post_type_name );
		$author_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_AUTHOR, $post_type_name );
		$contributor_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_CONTRIBUTOR, $post_type_name );
		$none_label = self::get_capability_setting_label( User_Role_Controller::ROLE_TYPE_NONE, $post_type_name );		
		
		$role_name = $this->get_role_name();
		
		switch( $role_name ) {

			case User_Role_Controller::EVENT_COORDINATOR_ROLE_NAME:
				$options = array(
						User_Role_Controller::ROLE_TYPE_AUTHOR		=> $author_label,
						User_Role_Controller::ROLE_TYPE_CONTRIBUTOR	=> $contributor_label,
						User_Role_Controller::ROLE_TYPE_NONE		=> $none_label,
				);
				break;

			case User_Role_Controller::COMMUNITY_EVENT_LEADER_ROLE_NAME:
				$options = array(
						User_Role_Controller::ROLE_TYPE_AUTHOR		=> $author_label,
						User_Role_Controller::ROLE_TYPE_CONTRIBUTOR	=> $contributor_label,
						User_Role_Controller::ROLE_TYPE_NONE		=> $none_label,
				);
				break;

			default:
				$options = array(
						User_Role_Controller::ROLE_TYPE_NONE		=> $none_label,
				);
				break;
				
		} // endswitch

		$role = $this->get_role();
		
		if ( $role->has_cap( "edit_others_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_EDITOR;
			
		} elseif( $role->has_cap( "publish_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_AUTHOR;
			
		} elseif( $role->has_cap( "edit_{$cap_type_plural}" ) ) {
			
			$selected = User_Role_Controller::ROLE_TYPE_CONTRIBUTOR;
			
		} else {
			
			$selected = User_Role_Controller::ROLE_TYPE_NONE;
			
		} // endif
			
		$this->render_capabilities_inputs_for_post_type( $post_type, $post_type_name, $options, $selected );
		
	} // function
	
	/**
	 * Render the inputs for authoring posts of a certain type
	 */
	private function render_capabilities_inputs_for_post_type( $post_type, $post_type_name, $options, $selected ) {

		$role_name = $this->get_role_name();
		
		$input_name = "{$role_name}_capabilities[{$post_type}]";
		
		$input_id_format = "{$role_name}_capabilities_{$post_type}_" . '%s';
		
		$radio_class = '';
		$input_format =
				'<div class="user-role-cpt-capabilites-input-container">' .
					'<input id="%1$s" type="radio" value="%2$s" %3$s' . " name=\"{$input_name}\" class=\"$radio_class\" autocomplete=\"off\"/>" .
					'<label for="%1$s">%4$s</label>' .
				'</div>';

		echo '<div class="user-role-cpt-capabilites-container">';
		
			echo '<h4 class="user-role-cpt-capabilities-heading">' . $post_type_name . '</h4>';
		
			foreach( $options as $value => $label ) {

				$id = sprintf( $input_id_format, $value );
				$checked = ( $value === $selected ) ? 'checked="checked"' : '';
				printf( $input_format, $id, $value, $checked, $label );

			} // endfor
		
		echo '</div>';
		
	} // function
	
} // class