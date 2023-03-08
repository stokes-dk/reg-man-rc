<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The administrative view for Visitor
 *
 * @since	v0.1.0
 *
 */
class Visitor_Admin_View {

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
		add_filter( 'manage_' . Visitor::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the columns in the list
		add_action( 'manage_' . Visitor::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Register my columns as sortable
		add_filter( 'manage_edit-' . Visitor::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

		// Remove the dates filter by returning an empty array of dates
		add_filter( 'months_dropdown_results' , array( __CLASS__, 'remove_dates_filter' ), 10, 2 );

	} // function

	/**
	 * Add the meta boxes for Visitors
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {
		if ( $post_type == Visitor::POST_TYPE ) {

			add_meta_box(
					'custom-metabox-visitor-details',							// Unique ID for the element
					__( 'Details', 'reg-man-rc' ),								// Box title
					array( __CLASS__, 'render_visitor_details_meta_box' ),		// Content callback, must be of type callable
					Visitor::POST_TYPE, 										// Post type for this meta box
					'side', 													// Meta box position
					'high'														// Meta box priority
		    );
/*
			add_meta_box(
					'custom-metabox-visitor-is-public',						// Unique ID for the element
					__( 'Public Profile', 'reg-man-rc' ),						// Box title
					array( __CLASS__, 'render_visitor_is_public_meta_box' ),	// Content callback, must be of type callable
					Visitor::POST_TYPE, 										// Post type for this meta box
					'side',														// Meta box position
					'high'														// Meta box priority
	        );
*/

/* FIXME - is this useful? It shows a table with all the events for this visitor
			add_meta_box(
					'custom-metabox-visitor-reg-list',						// Unique ID for the element
					__( 'Event Registrations', 'reg-man-rc' ),					// Box title
					array( __CLASS__, 'render_visitor_reg_list_meta_box' ),	// Content callback, must be of type callable
					Visitor::POST_TYPE, 										// Post type for this meta box
					'normal', 													// Meta box position
					'default'													// Meta box priority
			);
*/
		} // endif
	} // function

	/**
	 * Render the metabox for choosing whether the Visitor has a public profile page
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
/*
	public static function render_visitor_is_public_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="visitor_is_public_selection_flag" value="TRUE">';

		$visitor = Visitor::get_visitor_by_id( $post->ID );

		$input_list = Form_Input_List::create();

		$label = __( 'Profile this visitor on the public website?', 'reg-man-rc' );
		$name = 'visitor_public_profile';
		$options = array(
				__( 'Yes, include this visitor\'s profile on the public website', 'reg-man-rc' )		=> 'TRUE',
				__( 'No, DO NOT show this visitor on the website', 'reg-man-rc' )			=> 'FALSE'
		);
		$curr_val = $visitor->get_has_public_profile();
		$selected = $curr_val ? 'TRUE' : 'FALSE';
		$hint = __( 'Public profiles NEVER contain identifying personal information like full name or email address.', 'reg-man-rc' );
		$input_list->add_radio_group( $label, $name, $options, $selected, $hint );

		$input_list->render();

	} // function
*/

	/**
	 * Render the metabox for list of registrations for this visitor
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
/*
	public static function render_visitor_reg_list_meta_box( $post ) {
		$visitor = Visitor::get_visitor_by_id( $post->ID );
		if ( isset( $visitor ) ) {
			$reg_array = $visitor->get_registration_descriptors();
			$list_view = Visitor_Registration_List_View::create( $reg_array );
			$list_view->render();
		} // endif
	} // function
*/
	/**
	 * Render the details metabox for the visitor
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_visitor_details_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="visitor_details_input_flag" value="TRUE">';

		$visitor = Visitor::get_visitor_by_id( $post->ID );
//		echo '<pre>'; print_r( $visitor ); echo '</pre>';

		$input_list = Form_Input_List::create();

			$info_html = __( 'This information is for internal records only and is never shown on the public website.', 'reg-man-rc' );
			$input_list->add_information( '', $info_html );

			$label = __( 'Full name', 'reg-man-rc' );
			$input_name = 'visitor_full_name';
			$classes = 'required';
			$val = isset( $visitor ) ? $visitor->get_full_name() : '';
			$input_list->add_text_input( $label, $input_name, $val );

			$label = __( 'Email address', 'reg-man-rc' );
			$input_name = 'visitor_email';
			$val = isset( $visitor ) ? $visitor->get_email() : '';
			$input_list->add_email_input( $label, $input_name, $val );

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
				( $screen->post_type == Visitor::POST_TYPE ) &&
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
		if ( Visitor::POST_TYPE === get_post_type() ) {
			return __( 'Enter the visitor\'s preferred public name here, e.g. first name and last initial', 'reg-man-rc' );
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
		$messages[ Visitor::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Visitor updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Visitor updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Visitor restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Visitor published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Visitor saved.'),
				8 => sprintf( __('Visitor submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Visitor scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Visitor draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
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
		$result = array(
			'cb'						=> $columns[ 'cb' ],
			'title'						=> __( 'Public Name',				'reg-man-rc' ),
//			'is_public'					=> __( 'Public Profile',			'reg-man-rc' ),
			'full_name'					=> __( 'Full Name',					'reg-man-rc' ),
			'email'						=> __( 'Email',						'reg-man-rc' ),
//			'access_key'				=> __( 'Key',						'reg-man-rc' ),
//			'reg_count'					=> __( 'Events',					'reg-man-rc' ),
			'is_join_mail_list'			=> __( 'Join Mailing List?',		'reg-man-rc' ),
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
		$visitor = Visitor::get_visitor_by_id( $post_id );
		if ( $visitor !== NULL ) {
			switch ( $column_name ) {

				case 'title':
					$public_name = $visitor->get_public_name();
					$result = ! empty( $public_name ) ? esc_html( $public_name ) : $em_dash;
					break;
/*
				case 'is_public':
					$is_public = $visitor->get_has_public_profile();
					$val = $is_public ? __( 'Yes', 'reg-man-rc' ) : $em_dash; //__( 'No', 'reg-man-rc' );
					$result = esc_html( $val );
					break;
*/
				case 'full_name':
					$full_name = $visitor->get_full_name();
					$result = ! empty( $full_name ) ? esc_html( $full_name ) : $em_dash;
					break;

				case 'email':
					$email = $visitor->get_email();
					if ( ! empty( $email ) ) {
						$email_attr = esc_attr( $email );
						$email_html = esc_html( $email );
						$result = "<a href=\"mailto:$email_attr\">$email_html</a>";
					} // endif
					break;

/*
				case 'access_key':
					$access_key = $visitor->get_access_key();
					$result = ! empty( $access_key ) ? esc_html( $access_key ) : $em_dash;
					break;

				case 'reg_count':
					$reg_array = $visitor->get_registration_descriptors();
					$result = ! empty( $reg_array ) ? esc_html( count( $reg_array ) ) : $em_dash;
					break;
*/
				case 'is_join_mail_list':
					$is_join = $visitor->get_is_join_mail_list();
					$result = $is_join ? __( 'Yes', 'reg-man-rc' ) : $em_dash;
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

		if ( is_admin() && ( $post_type == Visitor::POST_TYPE ) ) {


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
		if ( $post_type === Visitor::POST_TYPE ) {
			$result = array();
		} else {
			$result = $months;
		} // endif
		return $result;
	} // function

} // class
