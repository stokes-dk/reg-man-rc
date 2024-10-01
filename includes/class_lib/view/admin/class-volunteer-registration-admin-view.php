<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative view for Volunteer Registration
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Registration_Admin_View {

	/**
	 * Set up the necessary hooks, filters etc. for this administrative view
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Add the metabox for the location
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 2 );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'update_post_messages') );

		// Add a column to the admin UI term list
		add_filter( 'manage_' . Volunteer_Registration::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the columns in the term list
		add_action( 'manage_' . Volunteer_Registration::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Register my columns as sortable
		add_filter( 'manage_edit-' . Volunteer_Registration::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

		// Remove the dates filter by returning an empty array of dates
		add_filter( 'months_dropdown_results' , array( __CLASS__, 'remove_dates_filter' ), 10, 2 );

	} // function

	/**
	 * Add my columns to the list of sortable columns.
	 * This is called during the manage_edit-TAXONOMY_sortable_columns filter hook.
	 * @param	string[]	$columns	The array of columns to be made sortable
	 * @return	string[]	The array of columns to be made sortable
	 */
	public static function add_sortable_columns( $columns ) {
		$fixer_station_tax_col = 'taxonomy-' . Fixer_Station::TAXONOMY_NAME;
//		$vol_role_tax_col = 'taxonomy-' . Volunteer_Role::TAXONOMY_NAME;
		$columns[ $fixer_station_tax_col ] = $fixer_station_tax_col;
//		$columns[ $vol_role_tax_col ] = $vol_role_tax_col;
		return $columns;
	} // function

	/**
	 * Add the meta boxes for Volunteer Registrations
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {

		if ( $post_type == Volunteer_Registration::POST_TYPE ) {

			$new_id = Volunteer_Registration::POST_TYPE . '-event-metabox';
			$render_fn = array( __CLASS__, 'render_event_meta_box' );
			add_meta_box(
					$new_id,							// Unique ID for the element
					__( 'Event', 'reg-man-rc' ),		// Box title
					$render_fn,							// Content callback, must be of type callable
					Volunteer_Registration::POST_TYPE, 	// Post type for this meta box
					'normal',							// Meta box position
					'high'								// Meta box priority
			);

			$new_id = Volunteer_Registration::POST_TYPE . '-volunteer-metabox';
			$render_fn = array( __CLASS__, 'render_volunteer_meta_box' );
			add_meta_box(
					$new_id,									// Unique ID for the element
					__( 'Fixer / Volunteer', 'reg-man-rc' ),	// Box title
					$render_fn,									// Content callback, must be of type callable
					Volunteer_Registration::POST_TYPE, 			// Post type for this meta box
					'normal',									// Meta box position
					'high'										// Meta box priority
			);

			// Comments
			$new_id = Volunteer_Registration::POST_TYPE . '-comments-metabox';
			$render_fn = array( __CLASS__, 'render_comments_metabox' );
			add_meta_box(
					$new_id,								// Unique ID for the element
					__( 'Private Notes', 'reg-man-rc' ),	// Box title
					$render_fn,								// Content callback, must be of type callable
					Volunteer_Registration::POST_TYPE, 		// Post type for this meta box
					'normal',								// Meta box position
					'high'									// Meta box priority
			);

			$new_id = Volunteer_Registration::POST_TYPE . '-fixer-station-metabox';
			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Fixer Station', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer_Registration::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'high' // priority within the section (high, low or default)
			);

			$new_id = Volunteer_Registration::POST_TYPE . '-volunteer-roles-metabox';
			$view = Volunteer_Role_Admin_View::create();
			$label = __( 'Volunteer Roles', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer_Registration::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'high' // priority within the section (high, low or default)
			);
		} // endif
	} // function

	
	/**
	 * Render the meta box for the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_event_meta_box( $post ) {

		// We need a flag to distinguish the case where no event statuses were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_reg_event_input_flag" value="TRUE">';

		$vol_reg = Volunteer_Registration::get_registration_by_id( $post->ID );
		$volunteer = isset( $vol_reg ) ? $vol_reg->get_volunteer() : NULL;
		$volunteer_id = isset( $volunteer ) ? $volunteer->get_id() : NULL;
		$event_key = isset( $vol_reg ) ? $vol_reg->get_event_key_string() : '';
		
		$data = "data-original-event=\"$event_key\" data-original-vol-id=\"$volunteer_id\"";
		
		echo "<div class=\"reg-man-rc-volunteer-reg event-metabox reg-man-rc-metabox\" $data>";
			$reg = Volunteer_Registration::get_registration_by_id( $post->ID );
			$event = isset( $reg ) ? $reg->get_event() : NULL;
			$selected_key = isset( $event ) ? $event->get_key_string() : NULL;
			$input_name = 'volunteer_registration_event';

			$classes = '';
			$calendar = Calendar::get_admin_calendar();
			$name = esc_html( __( '-- Please select --', 'reg-man-rc' ) );
			$selected = empty( $selected_key ) ? 'selected="selected"' : '';
			$first_option = "<option value=\"\" disabled=\"disabled\" $selected>$name</option>";
			$is_required = TRUE;
			$events_array = Event::get_events_array_current_user_can_register_volunteers();
			Event_View::render_event_select( $input_name, $classes, $calendar, $selected_key, $events_array, $first_option, $is_required, $reg );
		echo '</div>';

	} // function


	/**
	 * Render the meta box for the event
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_volunteer_meta_box( $post ) {

		// We need a flag to distinguish the case where no volunteer was chosen by the user
		//  versus the case where no options were presented at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_reg_volunteer_input_flag" value="TRUE">';

		// If this WP user is not allowed to edit volunteer registrations OR this WP User is attempting
		// to edit a registration for a different volunteer then we should never get here -- the system
		// should prevent that
		
		// If this registration is for the current WP user then the user can modify the volunteer details or comments
		$vol_reg = Volunteer_Registration::get_registration_by_id( $post->ID );
		$volunteer = isset( $vol_reg ) ? $vol_reg->get_volunteer() : NULL;
		$volunteer_id = isset( $volunteer ) ? $volunteer->get_id() : NULL;
		
		echo '<div class="reg-man-rc-volunteer-reg volunteer-metabox reg-man-rc-metabox">';

			$volunter_id_input_name = 'volunteer_registration_volunteer';
			$volunteer_id_input_id = 'vol-reg-volunteer-input';

			// Users who are allowed to edit volunteers can see a full list of volunteers and select one
			if ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {

				self::render_volunteer_select( $volunter_id_input_name, $volunteer_id_input_id, $volunteer_id );
				
			} else {

				// Otherwise the metabox should contain only the volunteer represented by the current WP User
				// If there is no volunteer assigned to this record then it may be a newly created one
				// So let's make sure we use the current user
				if ( empty( $volunteer ) ) {

					$volunteer = Volunteer::get_volunteer_for_current_wp_user();
					$volunteer_id = isset( $volunteer ) ? $volunteer->get_id() : NULL;
					
				} // endif

				if ( ! empty( $volunteer ) ) {
					
					// Pass the volunteer ID as a hidden input
					echo "<input type=\"hidden\" name=\"$volunter_id_input_name\" value=\"$volunteer_id\">";
	
					// Include a flag to indicate that the details should be updated
					echo '<input type="hidden" name="volunteer_reg_volunteer_details_update_flag" value="TRUE">';
	
					self::render_volunteer_details_inputs( '', $volunteer );
				
				} // endif

			} // endif

		echo '</div>';

	} // function
	
	/**
	 * Render the inputs for the volunteer details like full name and email
	 * @param string	$classes
	 * @param Volunteer	$volunteer
	 */
	private static function render_volunteer_details_inputs( $list_classes, $volunteer = NULL ) {

			$input_list = Form_Input_List::create();
			$input_list->add_list_classes( $list_classes );

			$label = __( 'Public name', 'reg-man-rc' );
			$name = 'volunteer_public_name';
			$val = isset( $volunteer ) ? $volunteer->get_public_name() : '';
			$hint = __( 'The name used in public, e.g. first name and last intial', 'reg-man-rc' );
			$classes = '';
			$is_required = isset( $volunteer ); // It can't be a required input when it is normally hidden from view
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required );

			$label = __( 'Full name', 'reg-man-rc' );
			$name = 'volunteer_full_name';
			$val = isset( $volunteer ) ? $volunteer->get_full_name() : '';
			$hint = __( 'This is never visible on the website', 'reg-man-rc' );
			$classes = '';
			$is_required = isset( $volunteer ); // It can't be a required input when it is normally hidden from view;
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required );

			$label = __( 'Email', 'reg-man-rc' );
			$name = 'volunteer_email';
			$val = isset( $volunteer ) ? $volunteer->get_email() : '';
			$hint = __( 'This is never visible on the website', 'reg-man-rc' );
			$classes = '';
			$is_required = isset( $volunteer ); // It can't be a required input when it is normally hidden from view;
			$addn_attrs = isset( $volunteer ) ? 'readonly="readonly"' : '';
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

			$input_list->set_required_inputs_flagged( FALSE );
			$input_list->render();
		
	} // function

	private static function render_volunteer_select( $input_name, $input_id, $selected_id = NULL ) {

		$volunteer_list = Volunteer::get_all_volunteers();

		$format = '<option value="%2$s" %3$s data-station="%4$s" data-roles="%5$s" data-is-apprentice="%6$s">%1$s</option>';
		// Disabled to start with until it is initialized on the client side
		echo "<select required=\"required\" class=\"combobox\" name=\"$input_name\" id=\"$input_id\" autocomplete=\"off\" disabled=\"disabled\">";

			// The empty valued selection MUST be first to make HTML5 required attribute work correctly
			if ( empty( $selected_id ) ) {
				$label = esc_html__( '-- Please select --', 'reg-man-rc' );
				$val = '';
				$attrs = ( empty( $selected_id ) ) ? 'selected="selected"' : '';
				$attrs .= '  disabled="disabled"';
				printf( $format, $label, $val, $attrs, '0', '', 'false' );
			} // endif

			if ( ! empty( $volunteer_list ) ) {
				foreach ( $volunteer_list as $volunteer ) {
					$id = $volunteer->get_id();
					$label = esc_html( $volunteer->get_label() );
					$selected = selected( $id, $selected_id, $echo = FALSE );
					$station = $volunteer->get_preferred_fixer_station();
					$station_id = isset( $station ) ? $station->get_id() : '0';
					$is_apprentice = $volunteer->get_is_fixer_apprentice() ? 'true' : 'false';
					$roles = $volunteer->get_preferred_roles();
					$role_ids = array();
					foreach ( $roles as $role ) {
						$role_ids[] = $role->get_id();
					} // endfor
					$role_ids = implode( ',', $role_ids );
					printf( $format, $label, $id, $selected, $station_id, $role_ids, $is_apprentice );
				} // endfor
			} // endif

			$label = __( 'Add a new volunteer', 'reg-man-rc' );
			$html_name= esc_html( $label );
			$selected = '';
			echo "<option value=\"-1\" class=\"select_option_add\" $selected>$html_name</option>";

		echo '</select>';
		
		// The select includes an option to add a new volunteer
		// The following renders those inputs
		self::render_volunteer_details_inputs( 'add-volunteer-input-list' );

	} // function

	/**
	 * Render the comments metabox for the specified post
	 * @param	\WP_Post	$post
	 */
	public static function render_comments_metabox( $post ) {
		if ( $post->post_type === Volunteer_Registration::POST_TYPE ) {

			// We need a flag to distinguish the case where no user input is provided
			//  versus the case where no inputs were shown at all like in quick edit mode
			echo '<input type="hidden" name="alt_desc_input_flag" value="TRUE">';
			
			// If this registration is for the current WP user then the user can modify the comments
			$vol_reg = Volunteer_Registration::get_registration_by_id( $post->ID );
			$volunteer = isset( $vol_reg ) ? $vol_reg->get_volunteer() : NULL;
			$is_current_wp_user = isset( $volunteer ) ? $volunteer->get_is_instance_for_current_wp_user() : FALSE;
			
			$input_list = Form_Input_List::create();
			$label = __( 'Private note from volunteer', 'reg-man-rc' );

			// Note that we use the name 'post_content' so that the comment will automatically be saved there
			$name = 'post_content';
			$val = isset( $vol_reg ) ? $vol_reg->get_volunteer_registration_comments() : '';
			$hint = '';
			$classes = 'full-width'; // We want a wide text input here
			$is_required = FALSE;
			
			// If this registration is for the current WP user then the user can modify the comments
			$addn_attrs = $is_current_wp_user ? '' : 'readonly="readonly"';
			$rows = 2;
			$input_list->add_text_area_input( $label, $name, $rows, $val, $hint, $classes, $is_required, $addn_attrs );

			$input_list->render();
		} // function
	} // function


	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) &&
				( $screen->post_type == Volunteer_Registration::POST_TYPE ) &&
				( empty( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
	} // function

	/**
	 * Set up the messages for this post type
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function update_post_messages( $messages ) {
		global $post, $post_ID;
		$permalink = get_permalink( $post_ID );
		/* translators: %1$s is a date, %2$s is a time. */
		$date_time_format = sprintf( _x('%1$s at %2$s', 'Displaying a date and time', 'reg-man-rc' ),
										get_option( 'date_format' ), get_option('time_format') );
		$date = date_i18n( $date_time_format, strtotime( $post->post_date ) );
		$messages[ Volunteer_Registration::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Registration updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Registration updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Registration restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Registration published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Registration saved.'),
				8 => sprintf( __('Registration submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Registration scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Registration draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
		);
		return $messages;
	} // function

	/**
	 * Set up the columns to show in the main admin list for this type
	 * @param	string[]	$columns	The default associative array of IDs (key) and titles (value) for the columns
	 * @return	string[]				The same associative array updated with the specific columns and titles to use for this type
	 * @since	v0.1.0
	 */
	public static function filter_admin_UI_columns( $columns ) {

		$fixer_station_tax_col = 'taxonomy-' . Fixer_Station::TAXONOMY_NAME;
		$volunteer_role_tax_col = 'taxonomy-' . Volunteer_Role::TAXONOMY_NAME;

		$result = array(
			'cb'						=> $columns['cb'],
			'title'						=> __( 'ID', 'reg-man-rc' ),
			'event'						=> __( 'Event', 'reg-man-rc' ),
			'volunteer'					=> __( 'Volunteer', 'reg-man-rc' ),
			$fixer_station_tax_col		=> __( 'Fixer Station', 'reg-man-rc' ),
			'is-apprentice'				=> __( 'Apprentice', 'reg-man-rc' ),
			$volunteer_role_tax_col		=> __( 'Volunteer Roles', 'reg-man-rc' ),
			'email'						=> __( 'Email', 'reg-man-rc' ),
			'date'						=> __( 'Last Update', 'reg-man-rc' ),
			'vol-reg-comments'			=> __( 'Volunteer Note', 'reg-man-rc' ),
			'author'					=> __( 'Author', 'reg-man-rc' ),
		);
		
		$can_read_private = current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
		if ( $can_read_private ) {
			
			// Only show the private note to users who can read private
//			$result[ 'vol-reg-comments' ]	= __( 'Volunteer Note', 'reg-man-rc' );
			
			// Don't show the author unless this user can read private, otherwise it may give away the user's name
			$result[ 'author' ]				= __( 'Author', 'reg-man-rc' );
			
		} // endif
		
		return $result;
	} // function

	/**
	 * Render the column values in the main admin list for this type
	 * @param	string[]	$column_name	The ID of the column whose value is to be shown
	 * @param	int|string	$post_id		The ID of the post whose column value is to be shown
	 * @return	string		The column value to be shown
	 * @since	v0.1.0
	 */
	public static function render_admin_UI_column_values( $column_name, $post_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$result = $em_dash; // Show a dash by default
		$registration = Volunteer_Registration::get_registration_by_id( $post_id );
		if ( $registration !== NULL ) {
			switch ( $column_name ) {

				case 'volunteer':
					$volunteer_name = $registration->get_volunteer_display_name();
					if ( ! empty( $volunteer_name ) ) {
						$label = esc_html( $volunteer_name );
						$result = $label;
					} else {
						$result = $em_dash;
					} // endif
					break;

				case 'event':
					$event = $registration->get_event();
					$key_string = $registration->get_event_key_string();
					// If there is no event then maybe this registration's event has been removed
					// Try creating a placeholder
					if ( empty( $event ) && ! empty( $key_string ) )  {
						$event = Event::create_placeholder_event( $key_string );
					} // endif
					if ( $event !== NULL ) {
						$label = esc_html( $event->get_label() );
						if ( $event->get_is_current_user_able_to_register_volunteers() ) {
							$filter_array = array( Volunteer_Registration::EVENT_META_KEY		=> $event->get_key_string() );
							$href = self::get_admin_view_href( $filter_array );
							$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
							$result = sprintf( $link_format, $href, $label );
						} else {
							$result = $label;
						} // endif
					} // endif
					break;

				case 'is-apprentice':
					$is_apprentice = $registration->get_is_fixer_apprentice();
					$result = $is_apprentice ? __( 'Yes', 'reg-man-rc' ) : $em_dash;
					break;

				case 'email':
					// Note that Volunteer will handle checking the user's access to the email
					$volunteer = $registration->get_volunteer(); 
					$email = isset( $volunteer ) ? $volunteer->get_email() : NULL;
					$result = ! empty( $email ) ? $email : $em_dash;
					break;

				case 'vol-reg-comments':
					$volunteer = $registration->get_volunteer();
					$is_current_user = isset( $volunteer ) ? $volunteer->get_is_instance_for_current_wp_user() : FALSE;
					$can_read_private = current_user_can( 'read_private_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
					if ( $is_current_user || $can_read_private ) {
						$comments = $registration->get_volunteer_registration_comments();
					} else {
						$comments = NULL;
					} // endif
					$result = ! empty( $comments ) ? $comments : $em_dash;
					break;
					
				default:
					$result = $em_dash;
					break;

			} // endswitch
		} // endif
		echo $result;
	} // function

	/**
	 * Get an href for the admin page for this type and include the specified filters
	 * @param	string[][]	$filter_array	An array of filter keys and values
	 * @return	string		An escaped href attribute suitable for use in an anchor tag
	 */
	public static function get_admin_view_href( $filter_array = array() ) {
		$post_type = Volunteer_Registration::POST_TYPE;
		$base_url = admin_url( 'edit.php' );
		$query_data = array(
				'post_type'		=> $post_type,
		);
		$query_data = array_merge( $query_data, $filter_array ); // Add any filters
		$query = http_build_query( $query_data );
		$result = esc_attr( "$base_url?$query" );
		return $result;
	} // function


	/**
	 * Modify the filters user interface for the list of my custom posts.
	 * @param	string	$post_type
	 * @return	NULL
	 */
	public static function modify_posts_filters_UI( $post_type ) {

		if ( is_admin() && ( $post_type == Volunteer_Registration::POST_TYPE ) ) {

			// Add a filter for events
			self::render_events_filter();

			// Add a filter for volunteers
			self::render_volunteers_filter();

			// Add a filter for each of these taxonomies
			$tax_name_array = array( Fixer_Station::TAXONOMY_NAME, Volunteer_Role::TAXONOMY_NAME );
			foreach ( $tax_name_array as $tax_name ) {
				self::render_taxonomy_filter( $tax_name );
			} // endfor

		} // endif
	} // function
	
	private static function render_events_filter() {
		echo '<span class="combobox-container">';
			$filter_name = Volunteer_Registration::EVENT_META_KEY;
			$curr_event = isset( $_REQUEST[ $filter_name ] ) ? wp_unslash( $_REQUEST[ $filter_name ] ) : 0;
			$classes = 'reg-man-rc-filter postform';
			$calendar = Calendar::get_admin_calendar();
			$name = esc_html( __( 'All Events', 'reg-man-rc' ) );
			$selected = selected( $curr_event, 0, FALSE );
			$first_option = "<option value=\"0\" $selected>$name</option>";
			$is_required = TRUE;
			$events_array = Event::get_events_array_current_user_can_register_volunteers();
			Event_View::render_event_select( $filter_name, $classes, $calendar, $curr_event, $events_array, $first_option, $is_required );
		echo '</span>';
	} // function
	
	private static function render_volunteers_filter() {
		
		$all_volunteers = Volunteer::get_all_volunteers();
		$span_class = 'combobox-container';
		$select_class = 'combobox';
		$select_disabled = 'disabled="disabled"'; // Disabled to start with until it is initialized on the client side

		$filter_name = Volunteer_Registration::VOLUNTEER_META_KEY;
		$curr_event = isset( $_REQUEST[ $filter_name ] ) ? wp_unslash( $_REQUEST[ $filter_name ] ) : 0;
		echo "<span class=\"$span_class\">";
		
			echo "<select class=\"$select_class reg-man-rc-filter postform\" name=\"$filter_name\" $select_disabled>";

				$name = esc_html( __( 'All Volunteers', 'reg-man-rc' ) );
				$selected = selected( $curr_event, 0, FALSE );
				echo "<option value=\"0\" $selected>$name</option>";

				foreach ( $all_volunteers as $volunteer ) {
					$name = $volunteer->get_label();
					$name = esc_html( $name );
					$id = $volunteer->get_id();
					$id_attr = esc_attr( $id );
					$selected = selected( $curr_event, $id, FALSE );
					echo "<option value=\"$id_attr\" $selected>$name</option>";
				} // endif

			echo '</select>';
		echo '</span>';
		
	} // function
	
	/**
	 * Render a filter for the named taxonomy
	 * @param string $tax_name
	 */
	private static function render_taxonomy_filter( $tax_name ) {
		$taxonomy = get_taxonomy( $tax_name );
		$curr_id = isset( $_REQUEST[ $tax_name ] ) ? $_REQUEST[ $tax_name ] : '';
		wp_dropdown_categories( array(
			'show_option_all'	=> $taxonomy->labels->all_items,
			'class'				=> 'reg-man-rc-filter postform',
			'taxonomy'			=> $tax_name,
			'name'				=> $tax_name,
			'orderby'			=> 'count',
			'order'				=> 'DESC',
			'value_field'		=> 'slug',
			'selected'			=> $curr_id,
			'hierarchical'		=> $taxonomy->hierarchical,
			'show_count'		=> FALSE,
			'hide_if_empty'		=> TRUE, // Hide the filter completely if there are no posts using the taxonomy
		) );
	} // function

	/**
	 * Remove the dates filter for my custom post type.
	 * This is called during the months_dropdown_results filter hook.
	 * @param	object[]	$months		The current list of months options for the filter produced by Wordpress
	 * @param	string		$post_type	The current post type
	 * @return	object[]	The list of months I want to show in the filter.
	 * Returning an empty array has the effect of removing the filter altogether which is what we want for our custom post type.
	 */
	public static function remove_dates_filter( $months, $post_type ) {
		if ( $post_type === Volunteer_Registration::POST_TYPE ) {
			$result = array();
		} else {
			$result = $months;
		} // endif
		return $result;
	} // function

	/**
	 * Get the set of tabs to be shown in the help for this type
	 * @return array
	 */
	public static function get_help_tabs() {
		$result = array(
			array(
				'id'		=> 'reg-man-rc-about',
				'title'		=> __( 'About', 'reg-man-rc' ),
				'content'	=> self::get_about_content(),
			),
		);
		return $result;
	} // function
	
	/**
	 * Get the html content shown to the administrator in the "About" help for this post type
	 * @return string
	 */
	private static function get_about_content() {
		ob_start();
			$heading = __( 'About Fixer / Volunteer Event Registrations', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A fixer / volunteer event registration represents a volunteer who registered to attend an event.' .
					'  It includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'ID', 'reg-man-rc' );
					$msg = esc_html__(
							'A unique ID for the registration record.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Event', 'reg-man-rc' );
					$msg = esc_html__( 'The event the volunteer registered to attend.', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Volunteer', 'reg-man-rc' );
					$msg = esc_html__( 'The volunteer who registered to attend the event.', 'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Fixer Station', 'reg-man-rc' );
					$msg = esc_html__(
							'The fixer station for this volunteer, e.g. "Appliances & Housewares".' .
							'  If the volunteer is a non-fixer then this field is empty.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Apprentice', 'reg-man-rc' );
					$msg = esc_html__(
							'A volunteer who will work as a fixer apprentice.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Volunteer Roles', 'reg-man-rc' );
					$msg = esc_html__(
							'A list of non-fixer roles this volunteer will play at the event, e.g. "Setup & Cleanup, Refreshments".',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Email', 'reg-man-rc' );
					$msg = esc_html__(
							'The volunteer\'s email address, if one is supplied and the current user has authority to view it.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Volunteer Note', 'reg-man-rc' );
					$msg = esc_html__(
							'A note entered by the volunteer and visible only to system administrators and event organizers.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
				echo '</dl>';
			echo '</p>';

		$result = ob_get_clean();
		return $result;
	} // function
	
} // class
