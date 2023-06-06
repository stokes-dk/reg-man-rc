<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\View\Volunteer_Registration_List_View;

/**
 * The administrative view for Volunteer
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Admin_View {

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

		// Change the placeholder text for "Enter Title Here"
		add_filter( 'enter_title_here', array(__CLASS__, 'rewrite_enter_title_here') );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'update_post_messages') );

		// Filter columns in the admin UI term list
		add_filter( 'manage_' . Volunteer::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the columns in the list
		add_action( 'manage_' . Volunteer::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Register my columns as sortable
		add_filter( 'manage_edit-' . Volunteer::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

		// Remove the dates filter by returning an empty array of dates
		add_filter( 'months_dropdown_results' , array( __CLASS__, 'remove_dates_filter' ), 10, 2 );

	} // function

	/**
	 * Add the meta boxes for Volunteers
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {
		if ( $post_type == Volunteer::POST_TYPE ) {

			add_meta_box(
					'custom-metabox-volunteer-details',							// Unique ID for the element
					__( 'Details', 'reg-man-rc' ),								// Box title
					array( __CLASS__, 'render_volunteer_details_meta_box' ),	// Content callback, must be of type callable
					Volunteer::POST_TYPE, 										// Post type for this meta box
					'side', 													// Meta box position
					'high'														// Meta box priority
			);

			add_meta_box(
					'custom-metabox-volunteer-is-public',						// Unique ID for the element
					__( 'Public Profile', 'reg-man-rc' ),						// Box title
					array( __CLASS__, 'render_volunteer_is_public_meta_box' ),	// Content callback, must be of type callable
					Volunteer::POST_TYPE, 										// Post type for this meta box
					'side',														// Meta box position
					'high'														// Meta box priority
			);

			$new_id = Volunteer::POST_TYPE . '-fixer-station-metabox';
			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Preferred Fixer Station', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer::POST_TYPE,
					'side', // section to place the metabox (normal, side or advanced)
					'default' // priority within the section (high, low or default)
			);

			$new_id = Volunteer::POST_TYPE . '-volunteer-roles-metabox';
			$view = Volunteer_Role_Admin_View::create();
			$label = __( 'Preferred Volunteer Roles', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer::POST_TYPE,
					'side',		// section to place the metabox (normal, side or advanced)
					'default'	// priority within the section (high, low or default)
			);

			add_meta_box(
					'custom-metabox-volunteer-proxy',							// Unique ID for the element
					__( 'Registration Proxy', 'reg-man-rc' ),					// Box title
					array( __CLASS__, 'render_volunteer_proxy_meta_box' ),		// Content callback, must be of type callable
					Volunteer::POST_TYPE, 										// Post type for this meta box
					'side', 													// Meta box position
					'default'													// Meta box priority
			);


			add_meta_box(
					'custom-metabox-volunteer-reg-list',						// Unique ID for the element
					__( 'Event Registrations', 'reg-man-rc' ),					// Box title
					array( __CLASS__, 'render_volunteer_reg_list_meta_box' ),	// Content callback, must be of type callable
					Volunteer::POST_TYPE, 										// Post type for this meta box
					'normal', 													// Meta box position
					'high'													// Meta box priority
			);
		} // endif
	} // function

	/**
	 * Render the metabox for choosing whether the Volunteer has a public profile page
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_volunteer_is_public_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_is_public_selection_flag" value="TRUE">';

		$volunteer = Volunteer::get_volunteer_by_id( $post->ID );

		$input_list = Form_Input_List::create();

		$label = __( 'Profile this volunteer on the public website?', 'reg-man-rc' );
		$name = 'volunteer_public_profile';
		$options = array(
				__( 'Yes, include this volunteer\'s profile on the public website', 'reg-man-rc' )		=> 'TRUE',
				__( 'No, DO NOT show this volunteer on the website', 'reg-man-rc' )			=> 'FALSE'
		);
		$curr_val = $volunteer->get_has_public_profile();
		$selected = $curr_val ? 'TRUE' : 'FALSE';
		$hint = __( 'Public profiles NEVER contain identifying personal information like full name or email address.', 'reg-man-rc' );
		$input_list->add_radio_group( $label, $name, $options, $selected, $hint );

		$input_list->render();

	} // function

	/**
	 * Render the metabox for list of registrations for this volunteer
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_volunteer_reg_list_meta_box( $post ) {
		$volunteer = Volunteer::get_volunteer_by_id( $post->ID );
		if ( isset( $volunteer ) ) {
			$reg_array = $volunteer->get_registration_descriptors();
			$list_view = Volunteer_Registration_List_View::create( $reg_array );
			$list_view->render();
		} // endif
	} // function

	/**
	 * Render the details metabox for the volunteer
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_volunteer_details_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_details_input_flag" value="TRUE">';

		$volunteer = Volunteer::get_volunteer_by_id( $post->ID );
//		echo '<pre>'; print_r( $volunteer ); echo '</pre>';

		$input_list = Form_Input_List::create();

			$info_html = __( 'This information is for internal records only and is never shown on the public website.', 'reg-man-rc' );
			$input_list->add_information( '', $info_html );

			$label = __( 'Full name', 'reg-man-rc' );
			$input_name = 'volunteer_full_name';
			$classes = 'required';
			$val = isset( $volunteer ) ? $volunteer->get_full_name() : '';
			$input_list->add_text_input( $label, $input_name, $val );

			$label = __( 'Email address', 'reg-man-rc' );
			$input_name = 'volunteer_email';
			$val = isset( $volunteer ) ? $volunteer->get_email() : '';
			$input_list->add_email_input( $label, $input_name, $val );

			$label = __( 'WordPress User', 'reg-man-rc' );
			$input_name = 'wp_user';
			$wp_user = isset( $volunteer ) ? $volunteer->get_wp_user() : NULL;
			$display_name = ! empty( $wp_user ) ? $wp_user->display_name : __( '[ none ]', 'reg-man-rc' );
			$input_list->add_information( $label, $display_name );

		$input_list->render();

	} // function

	/**
	 * Render the details metabox for the volunteer
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_volunteer_proxy_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_proxy_input_flag" value="TRUE">';

		$volunteer = Volunteer::get_volunteer_by_id( $post->ID );
//		echo '<pre>'; print_r( $volunteer ); echo '</pre>';

		$input_list = Form_Input_List::create();

			ob_start();

				$input_name = 'volunteer_proxy';
				$current_proxy_id = isset( $volunteer ) ? $volunteer->get_my_proxy_volunteer_id() : 0;

				// Disabled to start with until it is initialized on the client side
				echo "<select class=\"combobox\" name=\"$input_name\" autocomplete=\"off\"  disabled=\"disabled\" >";

					$label = __( 'This volunteer has no proxy', 'reg-man-rc' );
					$html_name = esc_html( $label );
					$selected = ( empty( $current_proxy_id ) ) ? 'selected="selected"' : '';
					echo "<option value=\"0\" class=\"select_option_none\" $selected>$html_name</option>";

					$volunteers_array = Volunteer::get_all_volunteers();
					foreach ( $volunteers_array as $curr_vol ) {
						$id = $curr_vol->get_id();
						if ( $id == $volunteer->get_id() ) {
							continue; // a volunteer should not be proxy to herself : )
						} // endif
//						$name = $curr_vol->get_full_name();
						$name = $curr_vol->get_label();
						$html_name = esc_html( $name );
						$selected = selected( $id, $current_proxy_id, $echo = FALSE );
						echo "<option value=\"$id\" $selected>$html_name</option>";
					} // endfor

				echo '</select>';
			$proxy_input_content = ob_get_clean();

			$label = __( 'Proxy', 'reg-man-rc' );
			$hint = __(
					'Allow the selected proxy to register this volunteer for events.' .
					'  This may be used to allow the proxy to register their spouse, for example,' .
					' or when the current volunteer has no email address and cannot access the volunteer area.',
					'reg-man-rc' );
			$input_list->add_custom_html_input( $label, $input_name, $proxy_input_content, $hint );

		$input_list->render();

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
				( $screen->post_type == Volunteer::POST_TYPE ) &&
				( empty( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
	} // function

	/**
	 * Change the helper text shown in the title input so that it's more descriptive for this type
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function rewrite_enter_title_here( $input ) {
		// Change the placeholder text for "Enter Title Here" if the specified post is mine
		if ( Volunteer::POST_TYPE === get_post_type() ) {
			return __( 'Enter the volunteer\'s preferred public name here, e.g. first name and last initial', 'reg-man-rc' );
		} // endif
		return $input;
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
		$messages[ Volunteer::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Volunteer updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Volunteer updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Volunteer restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Volunteer published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Volunteer saved.'),
				8 => sprintf( __('Volunteer submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Volunteer scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Volunteer draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
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
			'cb'						=> $columns[ 'cb' ],
			'title'						=> __( 'Public Name',				'reg-man-rc' ),
			'is_public'					=> __( 'Public Profile',			'reg-man-rc' ),
			'full_name'					=> __( 'Full Name',					'reg-man-rc' ),
			'email'						=> __( 'Email',						'reg-man-rc' ),
			'proxy'						=> __( 'Proxy',						'reg-man-rc' ),
			$fixer_station_tax_col		=> __( 'Preferred Fixer Station',	'reg-man-rc' ),
			'is_apprentice'				=> __( 'Apprentice',				'reg-man-rc' ),
			$volunteer_role_tax_col		=> __( 'Preferred Roles',			'reg-man-rc' ),
			'reg_count'					=> __( 'Events',					'reg-man-rc' ),
			'wp_user'					=> __( 'WP User',					'reg-man-rc' ),
			'date'						=> __( 'Last Update',				'reg-man-rc' ),
			'author'					=> __( 'Author',					'reg-man-rc' ),
		);
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
		$volunteer = Volunteer::get_volunteer_by_id( $post_id );
		if ( $volunteer !== NULL ) {
			switch ( $column_name ) {

				case 'title':
					$public_name = $volunteer->get_public_name();
					$result = ! empty( $public_name ) ? esc_html( $public_name ) : $em_dash;
					break;

				case 'is_public':
					$is_public = $volunteer->get_has_public_profile();
					$val = $is_public ? __( 'Yes', 'reg-man-rc' ) : $em_dash; //__( 'No', 'reg-man-rc' );
					$result = esc_html( $val );
					break;

				case 'full_name':
					$full_name = $volunteer->get_full_name();
					$result = ! empty( $full_name ) ? esc_html( $full_name ) : $em_dash;
					break;

				case 'email':
					$email = $volunteer->get_email();
					if ( ! empty( $email ) ) {
						$email_attr = esc_attr( $email );
						$email_html = esc_html( $email );
						$result = "<a href=\"mailto:$email_attr\">$email_html</a>";
					} // endif
					break;

				case 'proxy':
					$proxy_id = $volunteer->get_my_proxy_volunteer_id();
					if ( ! empty( $proxy_id ) ) {
						$proxy_volunteer = Volunteer::get_volunteer_by_id( $proxy_id );
						$result = ! empty( $proxy_volunteer ) ? esc_html( $proxy_volunteer->get_full_name() ) : $em_dash;
					} // endif
					break;
				
/* FIXME - Not currently used
				case 'access_key':
					$access_key = $volunteer->get_access_key();
					$result = ! empty( $access_key ) ? esc_html( $access_key ) : $em_dash;
					break;
*/
				case 'is_apprentice':
					$is_apprentice = $volunteer->get_is_fixer_apprentice();
					$result = $is_apprentice ? __( 'Yes', 'reg-man-rc' ) : $em_dash;
					break;

				case 'reg_count':
					$reg_array = $volunteer->get_registration_descriptors();
					$result = ! empty( $reg_array ) ? esc_html( count( $reg_array ) ) : $em_dash;
					break;

				case 'wp_user':
					$wp_user = $volunteer->get_wp_user();
					if ( ! empty( $wp_user ) ) {
						$display_name = $wp_user->display_name;
						$result = $display_name;
					} // endif
					break;
					
				default:
					$result = $em_dash;
					break;
					
			} // endswitch
		} // endif
		echo $result;
	} // function


	/**
	 * Add my columns to the list of sortable columns.
	 * This is called during the manage_edit-TAXONOMY_sortable_columns filter hook.
	 * @param	string[]	$columns	The array of columns to be made sortable
	 * @return	string[]	$columns	The array of columns to be made sortable
	 */
	public static function add_sortable_columns( $columns ) {
		$columns[ 'is_public' ] = 'is_public';
		return $columns;
	} // function



	/**
	 * Modify the filters user interface for the list of my custom posts.
	 * @param	string	$post_type
	 * @return	NULL
	 */
	public static function modify_posts_filters_UI( $post_type ) {

		if ( is_admin() && ( $post_type == Volunteer::POST_TYPE ) ) {

			// Add a filter for each of these taxonomies
			$tax_name_array = array( Fixer_Station::TAXONOMY_NAME, Volunteer_Role::TAXONOMY_NAME );
			foreach ( $tax_name_array as $tax_name ) {
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
					'hide_if_empty'		=> TRUE,
				) );
			} // endfor

		} // endif
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
		if ( $post_type === Volunteer::POST_TYPE ) {
			$result = array();
		} else {
			$result = $months;
		} // endif
		return $result;
	} // function

} // class
