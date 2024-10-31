<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Stats\Visitor_Registration_Descriptor_Factory;

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
		add_filter( 'post_updated_messages', array(__CLASS__, 'handle_post_updated_messages') );

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

			add_meta_box(
					'custom-metabox-visitor-reg-list',							// Unique ID for the element
					__( 'Event Registrations', 'reg-man-rc' ),					// Box title
					array( __CLASS__, 'render_visitor_reg_list_meta_box' ),		// Content callback, must be of type callable
					Visitor::POST_TYPE, 										// Post type for this meta box
					'normal', 													// Meta box position
					'default'													// Meta box priority
			);

		} // endif
	} // function


	/**
	 * Render the metabox for list of registrations for this visitor
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_visitor_reg_list_meta_box( $post ) {
		$visitor = Visitor::get_visitor_by_id( $post->ID );
		if ( isset( $visitor ) ) {
			$reg_array = Visitor_Registration_Descriptor_Factory::get_visitor_registrations_for_visitor( $visitor );
			$list_view = Visitor_Registration_List_View::create( $reg_array );
			$list_view->render();
		} // endif
	} // function

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
			$val = isset( $visitor ) ? $visitor->get_full_name() : '';
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$input_list->add_text_input( $label, $input_name, $val, $hint, $classes, $is_required );

			$label = __( 'Email address', 'reg-man-rc' );
			$input_name = 'visitor_email';
			$val = isset( $visitor ) ? $visitor->get_email() : '';
			$hint = '';
			$classes = '';
			$is_required = FALSE;
			$input_list->add_email_input( $label, $input_name, $val, $hint, $classes, $is_required );

			$label = __( 'Join Mailing List?', 'reg-man-rc' );
			$input_name = 'visitor_join_mail_list';
			$val = isset( $visitor ) ? $visitor->get_email() : '';
			$options = array( __( 'Yes', 'reg-man-rc' ) => 1, __( 'No', 'reg-man-rc' ) => 0 );
			$selected = isset( $visitor ) && $visitor->get_is_join_mail_list() ? 1 : 0;
			$hint = NULL;
			$classes = NULL;
			$is_required = TRUE;
			$custom_label = NULL;
			$custom_value = NULL;
			$is_compact = TRUE;
			$input_list->add_radio_group( $label, $input_name, $options, $selected, $hint, $classes, $is_required, $custom_label, $custom_value, $is_compact );
			
			$label = __( 'First Event Attended', 'reg-man-rc' );
			$input_name = 'visitor_first_event_key';
			// Make sure we can get the actual first event for this visitor
			$first_event_key = isset( $visitor ) ? $visitor->get_first_event_key() : NULL;
			$first_event = isset( $first_event_key ) ? Event::get_event_by_key( $first_event_key ) : NULL;
			$selected_key = isset( $first_event ) ? $first_event->get_key_string() : NULL;
			ob_start();
				$classes = '';
				$calendar = Calendar::get_admin_calendar();
				$name = esc_html( __( '[ Not known ]', 'reg-man-rc' ) );
				$selected = ( empty( $selected_key ) ) ? 'selected="selected"' : '';
				$first_option = "<option value=\"0\" $selected>$name</option>";
				$is_required = TRUE;
				$events_array = NULL; // We want to show all events
				Event_View::render_event_select( $input_name, $classes, $calendar, $selected_key, $events_array, $first_option, $is_required );
			$input_html = ob_get_clean();
			$input_list->add_custom_html_input( $label, $input_name, $input_html );
			
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
	public static function handle_post_updated_messages( $messages ) {
		global $post, $post_ID;
//		$permalink = get_permalink( $post_ID );
		/* translators: %1$s is a date, %2$s is a time. */
		$date_time_format = sprintf( _x('%1$s at %2$s', 'Displaying a date and time', 'reg-man-rc' ),
										get_option( 'date_format' ), get_option('time_format') );
		$date = date_i18n( $date_time_format, strtotime( $post->post_date ) );
		$messages[ Visitor::POST_TYPE ] = array(
				0 => '',
				1 => __( 'Visitor updated.', 'reg-man-rc' ),
				2 => __( 'Custom field updated.', 'reg-man-rc' ),
				3 => __( 'Custom field deleted.', 'reg-man-rc' ),
				4 => __( 'Visitor updated.', 'reg-man-rc' ),
				5 => isset($_GET['revision']) ? sprintf( __( 'Visitor restored to revision from %s', 'reg-man-rc' ), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => __( 'Visitor published.', 'reg-man-rc' ),
				7 => __( 'Visitor saved.', 'reg-man-rc' ),
				8 => __( 'Visitor submitted.', 'reg-man-rc' ),
				9 => sprintf( __( 'Visitor scheduled for: <strong>%1$s</strong>', 'reg-man-rc' ) , $date ),
				10 => __( 'Visitor draft updated.', 'reg-man-rc' ),
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
			'title'						=> __( 'Public Name',			'reg-man-rc' ),
//			'id'						=> __( 'ID',					'reg-man-rc' ),
			'full_name'					=> __( 'Full Name',				'reg-man-rc' ),
			'email'						=> __( 'Email',					'reg-man-rc' ),
			'is_join_mail_list'			=> __( 'Join Mailing List?',	'reg-man-rc' ),
			'first_event'				=> __( 'First Event',			'reg-man-rc' ),
			'event_count'				=> __( 'Events',				'reg-man-rc' ),
			'item_count'				=> __( 'Items',					'reg-man-rc' ),
			'date'						=> __( 'Last Update',			'reg-man-rc' ),
			'author'					=> __( 'Author',				'reg-man-rc' ),
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
/*
				case 'id':
					$result = $visitor->get_id();
					break;
*/
				
				case 'title':
					$public_name = $visitor->get_public_name();
					$result = ! empty( $public_name ) ? esc_html( $public_name ) : $em_dash;
					break;

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

				case 'is_join_mail_list':
					$is_join = $visitor->get_is_join_mail_list();
					$result = $is_join ? __( 'Yes', 'reg-man-rc' ) : $em_dash;
					break;

				case 'first_event':
					$first_event_key = $visitor->get_first_event_key();
					$event = isset( $first_event_key ) ? Event::get_event_by_key( $first_event_key ) : NULL;
					$result = isset( $event ) ? $event->get_label() : $em_dash;
					break;
					
				case 'event_count':
					$result = $visitor->get_event_count();
					break;
					
				case 'item_count':
					$result = $visitor->get_item_count();
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
			$heading = __( 'About Visitors', 'reg-man-rc' );
			
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'A visitor record contains the details of a visitor who registered one or more items at an event.' .
					'  It includes the following:',
					'reg-man-rc'
				);
				echo esc_html( $msg );

				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Public Name', 'reg-man-rc' );
					$msg = esc_html__(
							'The name used to represent this visitor publicly like in the visitor registration list.' .
							'  This is usually the visitor\'s first name or first name and last initial.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Full Name', 'reg-man-rc' );
					$msg = esc_html__(
							'The visitor\'s full name.' .
							'  Note that this is for internal records only and is never shown on the public website.' .
							'  Also, note that this data is encrypted in the database and is not searchable.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Email', 'reg-man-rc' );
					$msg = esc_html__(
							'The visitor\'s email address if known.' .
							'  Note that this is for internal records only and is never shown on the public website.' .
							'  Also, note that this data is encrypted in the database and is not searchable.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Join Mailing List?', 'reg-man-rc' );
					$msg = esc_html__(
							'A flag set to TRUE if this visitor has requested to join the organization\'s mailing list.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'First Event', 'reg-man-rc' );
					$msg = esc_html__(
							'The first event this visitor attended, if known.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Events', 'reg-man-rc' );
					$msg = esc_html__(
							'A count or list of events this visitor has attended.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
					$title = esc_html__( 'Author', 'reg-man-rc' );
					$msg = esc_html__(
							'The author of this visitor record.' .
							'  Note that the author of the record has authority to view the visitor\'s email address.',
							'reg-man-rc' );
					printf( $item_format, $title, $msg );
					
				echo '</dl>';
			echo '</p>';

		$result = ob_get_clean();
		return $result;
	} // function
	
} // class
