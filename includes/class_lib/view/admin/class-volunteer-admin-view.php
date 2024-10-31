<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\View\Volunteer_Registration_List_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\User_Role_Controller;

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

		// Add filter for post row actions so I can replace "Trash"
		// TODO: This is work in progress
		// I want to intercept 'Trash' and present a dialog that allows the user to move registration records to
		//  another volunteer before this one gets trashed
		add_filter( 'post_row_actions', array( __CLASS__, 'handle_post_row_actions' ), 10, 2 );
		
		// Register hook to insert my remove dialog onto the page
		// Note that the dialog contains a form so must be rendered outside the open <form> tag that encloses the entire
		//  table including the tablenav sections, so I can't use the 'manage_posts_extra_tablenav' action here
		// TODO: This is incomplete work in progress
		add_action( 'admin_notices', array( __CLASS__, 'handle_admin_notices' ) );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'handle_post_updated_messages') );

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
	 * Filter the actions shown in the admin interface for a post row
	 * @param	string[]	$actions
	 * @param	\WP_Post	$post
	 * @return	string[]
	 */
	public static function handle_post_row_actions( $actions, $post ) {

		$result = $actions;
		
		// To avoid orphaned registrations we will replace the usual 'Trash' option and add our own 'Remove'
		
		if ( $post->post_type === Volunteer::POST_TYPE ) { 

			if ( isset( $actions[ 'trash' ] ) ) {
			
				// Normal view containing 'Trash'
				
				unset( $result[ 'trash' ] ); // Remove the usual trash operation
	
				$label = __( 'Trash&hellip;', 'reg-man-rc' );
				$title = __( 'Remove registration records and trash this volunteer record', 'reg-man-rc' );
				
				$format = '<button class="reg-man-rc-remove-cpt-button volunteer-remove-button button-link" type="button" data-record-id="%3$s" title="%2$s">%1$s</button>';
				
				$result[ 'reg-man-rc-trash' ] = sprintf( $format, $label, $title, $post->ID );

			} elseif ( isset( $actions[ 'delete' ] ) ) {
				
				// Trash view containing 'Delete Permanently'
				
			} // endif
	
		} // endif
		
		return $result;
	} // function

	/**
	 * Insert our remove dialog onto the page
	 */
	public static function handle_admin_notices() {
		self::render_remove_dialog();
	} // function

	/**
	 * Insert our remove dialog onto the page
	 */
	private static function render_remove_dialog() {

		global $pagenow;
		$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : '';
//		Error_Log::var_dump( $pagenow, $post_type );

		if ( ( $pagenow === 'edit.php' ) && ( $post_type === Volunteer::POST_TYPE ) ) {
		
			$title = __( 'Trash Volunteer', 'reg-man-rc' );
			
			echo "<div class=\"reg-man-rc-remove-cpt-dialog remove-volunteer-dialog dialog-container\" title=\"$title\">";
	
				$view = Remove_Volunteer_Form::create();
				$view->render();
	
			echo '</div>';

		} // endif
		
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

			$new_id = Volunteer::POST_TYPE . '-fixer-station-metabox';
			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Preferred Fixer Station', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer::POST_TYPE,
					'normal',	// section to place the metabox (normal, side or advanced)
					'high'		// priority within the section (high, low or default)
			);

			$new_id = Volunteer::POST_TYPE . '-volunteer-roles-metabox';
			$view = Volunteer_Role_Admin_View::create();
			$label = __( 'Preferred Volunteer Roles', 'reg-man-rc' );
			add_meta_box(
					$new_id,
					$label,
					array( $view, 'render_post_metabox' ),
					Volunteer::POST_TYPE,
					'normal',	// section to place the metabox (normal, side or advanced)
					'high'		// priority within the section (high, low or default)
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
					'default'													// Meta box priority
			);
		} // endif
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
			$val = isset( $volunteer ) ? $volunteer->get_full_name() : '';
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$input_list->add_text_input( $label, $input_name, $val, $hint, $classes, $is_required );

			$label = __( 'Email address', 'reg-man-rc' );
			$input_name = 'volunteer_email';
			$val = isset( $volunteer ) ? $volunteer->get_email() : '';
			$hint = '';
			$classes = '';
			$is_required = FALSE;
			$input_list->add_email_input( $label, $input_name, $val, $hint, $classes, $is_required );

			$wp_user_display_name = isset( $volunteer ) ? $volunteer->get_wp_user_display_name() : NULL;
			if ( ! empty( $wp_user_display_name ) ) {
				$label = __( 'WordPress User', 'reg-man-rc' );
				$input_name = 'wp_user';
				$input_list->add_information( $label, $wp_user_display_name );
			} // endif

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
			Scripts_And_Styles::enqueue_admin_cpt_scripts();
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
	public static function handle_post_updated_messages( $messages ) {
		global $post, $post_ID;
//		$permalink = get_permalink( $post_ID );
		/* translators: %1$s is a date, %2$s is a time. */
		$date_time_format = sprintf( _x( '%1$s at %2$s', 'Displaying a date and time', 'reg-man-rc' ),
										get_option( 'date_format' ), get_option('time_format') );
		$date = date_i18n( $date_time_format, strtotime( $post->post_date ) );
		$messages[ Volunteer::POST_TYPE ] = array(
				0 => '',
				1 => __( 'Volunteer updated.', 'reg-man-rc' ),
				2 => __( 'Custom field updated.', 'reg-man-rc' ),
				3 => __( 'Custom field deleted.', 'reg-man-rc' ),
				4 => __( 'Volunteer updated.', 'reg-man-rc' ),
				5 => isset($_GET['revision']) ? sprintf( __( 'Volunteer restored to revision from %s', 'reg-man-rc' ), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => __( 'Volunteer published.', 'reg-man-rc' ),
				7 => __( 'Volunteer saved.', 'reg-man-rc' ),
				8 => __( 'Volunteer submitted.', 'reg-man-rc' ),
				9 => sprintf( __( 'Volunteer scheduled for: <strong>%1$s</strong>', 'reg-man-rc' ) , $date ),
				10 => __( 'Volunteer draft updated.', 'reg-man-rc' ),
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
			'full_name'					=> __( 'Full Name',					'reg-man-rc' ),
			'email'						=> __( 'Email',						'reg-man-rc' ),
			'proxy'						=> __( 'Proxy',						'reg-man-rc' ),
			$fixer_station_tax_col		=> __( 'Preferred Fixer Station',	'reg-man-rc' ),
			'is_apprentice'				=> __( 'Apprentice',				'reg-man-rc' ),
			$volunteer_role_tax_col		=> __( 'Preferred Roles',			'reg-man-rc' ),
			'reg_count'					=> __( 'Events',					'reg-man-rc' ),
			'wp_user'					=> __( 'WP User',					'reg-man-rc' ),
			'vol_area_login'			=> __( 'Last Login to Volunteer Area',	'reg-man-rc' ),
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
						$result = ! empty( $proxy_volunteer ) ? esc_html( $proxy_volunteer->get_display_name() ) : $em_dash;
					} // endif
					break;
				
				case 'is_apprentice':
					$is_apprentice = $volunteer->get_is_fixer_apprentice();
					$result = $is_apprentice ? __( 'Yes', 'reg-man-rc' ) : $em_dash;
					break;

				case 'reg_count':
					$reg_count = $volunteer->get_registration_descriptor_count();
					$result = ! empty( $reg_count ) ? esc_html( $reg_count ) : $em_dash;
					break;

				case 'wp_user':
					$display_name = $volunteer->get_wp_user_display_name();
					$result = ! empty( $display_name ) ? $display_name : $em_dash;
					break;
					
				case 'vol_area_login':
					$datetime = $volunteer->get_volunteer_area_last_login_datetime();
					$date_format = get_option( 'date_format' );
					$result = ! empty( $datetime ) ? $datetime->format( $date_format ) : $em_dash;
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
			$heading = __( 'About Fixers & Volunteers', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A fixer & volunteer record contains the details of a single fixer or non-fixer volunteer.' .
					'  It includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Public Name', 'reg-man-rc' );
					$msg = esc_html__(
							'The name used to represent this volunteer.' .
							'  This is usually the volunteer\'s first name or first name and last initial.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Full Name', 'reg-man-rc' );
					$msg = esc_html__(
							'The volunteer\'s full name.' .
							'  Note that this is for internal records only and is never shown on the public website.' .
							'  Also, note that this data is encrypted in the database and is not searchable.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Email', 'reg-man-rc' );
					$msg = esc_html__(
							'The volunteer\'s email address if known.' .
							'  Note that this is for internal records only and is never shown on the public website.' .
							'  Also, note that this data is encrypted in the database and is not searchable.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Proxy', 'reg-man-rc' );
					$msg = esc_html__(
							'Another volunteer who is authorized to act as a proxy and register this volunteer for events.' .
							'  This can be used when a volunteer has no email address and so cannot access the volunteer area' .
							' or when two volunteers, like a married couple, usually attend events together and want to be able to register each other.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Preferred Fixer Station', 'reg-man-rc' );
					$msg = esc_html__(
							'The fixer station this volunteer prefers, e.g. "Appliances & Housewares".' .
							'  This fixer station will be automatically assigned for the volunteer when they register for an event.' .
							'  If the volunteer is a non-fixer then this field is empty.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Apprentice', 'reg-man-rc' );
					$msg = esc_html__(
							'A volunteer who will work as a fixer apprentice.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Preferred Volunteer Roles', 'reg-man-rc' );
					$msg = esc_html__(
							'A list of non-fixer roles this volunteer prefers to play at the event, e.g. "Setup & Cleanup, Refreshments".' .
							'  These roles will be automatically assigned for the volunteer when they register for an event.' .
							'  This field may be empty for fixer volunteers.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Events', 'reg-man-rc' );
					$msg = esc_html__(
							'A count or list of events this volunteer has registered to attend.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'WP User', 'reg-man-rc' );
					$msg = esc_html__(
							'The WordPress User ID with the same email address as this volunteer.' .
							'  Note that if a volunteer has an associated WordPress User ID then they will be required' .
							' to provide the password when they access the volunteer area.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Author', 'reg-man-rc' );
					$msg = esc_html__(
							'The author of this volunteer record.' .
							'  Note that the author of the record has authority to view the volunteer\'s email address.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
				echo '</dl>';
			echo '</p>';

		$result = ob_get_clean();
		return $result;
	} // function
	
	
} // class
