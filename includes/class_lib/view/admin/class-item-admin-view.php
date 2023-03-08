<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Model\Calendar;

/**
 * The administrative view for Item
 *
 * @since	v0.1.0
 *
 */
class Item_Admin_View {

	/**
	 * Set up the necessary hooks, filters etc. for this administrative view
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Change the placeholder text for "Enter Title Here"
		add_filter( 'enter_title_here', array(__CLASS__, 'rewrite_enter_title_here') );

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'update_post_messages') );

		// Add a column to the admin UI term list
		add_filter( 'manage_' . Item::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the columns in the term list
		add_action( 'manage_' . Item::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Set up the meta boxes
		add_action( 'add_meta_boxes' , array( __CLASS__, 'add_meta_boxes' ), 10, 2 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

		// Remove the dates filter by returning an empty array of dates
		add_filter( 'months_dropdown_results' , array( __CLASS__, 'remove_dates_filter' ), 10, 2 );

	} // function

	/**
	 * Add the meta boxes for Items
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {

		if ( $post_type == Item::POST_TYPE ) {
			$new_id = Item::POST_TYPE . '-event-metabox';
			$render_fn = array( __CLASS__, 'render_event_meta_box' );
			add_meta_box(
					$new_id,						// Unique ID for the element
					__( 'Event', 'reg-man-rc' ),	// Box title
					$render_fn,						// Content callback, must be of type callable
					Item::POST_TYPE, 				// Post type for this meta box
					'side', 						// Meta box position
					'high'							// Meta box priority
	        );

			$new_id = Item::POST_TYPE . '-visitor-metabox';
			$render_fn = array( __CLASS__, 'render_visitor_meta_box' );
			add_meta_box(
					$new_id,						// Unique ID for the element
					__( 'Visitor', 'reg-man-rc' ),	// Box title
					$render_fn,						// Content callback, must be of type callable
					Item::POST_TYPE, 				// Post type for this meta box
					'side', 						// Meta box position
					'high'							// Meta box priority
	        );

			// Item Type metabox - if item types are defined
			if ( ! empty( Item_Type::get_all_item_types() ) ) {
				$view = Item_Type_Admin_View::create();
				$new_id = Item::POST_TYPE . '-item_type-metabox';
				$render_fn = array( $view, 'render_post_metabox' );
				add_meta_box(
						$new_id,								// Unique ID for the element
						__( 'Item Type', 'reg-man-rc' ),		// Box title
						$render_fn,								// Content callback, must be of type callable
						Item::POST_TYPE, 						// Post type for this meta box
						'side',									// Meta box position
						'high'									// Meta box priority
		        );
			} // endif

			$view = Fixer_Station_Admin_View::create();
			$new_id = Item::POST_TYPE . '-fixer-station-metabox';
			$render_fn = array( $view, 'render_post_metabox' );
			add_meta_box(
					$new_id,								// Unique ID for the element
					__( 'Fixer Station', 'reg-man-rc' ),	// Box title
					$render_fn,								// Content callback, must be of type callable
					Item::POST_TYPE, 						// Post type for this meta box
					'side',									// Meta box position
					'high'									// Meta box priority
	        );

			$new_id = Item::POST_TYPE . '-status-metabox';
			$render_fn = array( __CLASS__, 'render_status_meta_box' );
			add_meta_box(
					$new_id,							// Unique ID for the element
					__( 'Item Status', 'reg-man-rc' ),	// Box title
					$render_fn,							// Content callback, must be of type callable
					Item::POST_TYPE, 					// Post type for this meta box
					'side',								// Meta box position
					'high'								// Meta box priority
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

		$item = Item::get_item_by_id( $post->ID );
		$selected_key = isset( $item ) ? $item->get_event_key() : NULL;
		$input_name = 'item_event';

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="item_event_selection_flag" value="TRUE">';

		$classes = '';
		$calendar = Calendar::get_admin_calendar();
		$name = esc_html( __( '-- Please select --', 'reg-man-rc' ) );
		$selected = ( empty( $selected_key ) ) ? 'selected="selected"' : '';
		$first_option = "<option value=\"\" disabled=\"disabled\" $selected>$name</option>";
		$is_required = TRUE;
		Event_View::render_event_select( $input_name, $classes, $calendar, $selected_key, $first_option, $is_required );

	} // function

	/**
	 * Render the meta box for the visitor
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_visitor_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="item_visitor_input_flag" value="TRUE">';

		$item = Item::get_item_by_id( $post->ID );
		$visitor = isset( $item ) ? $item->get_visitor() : NULL;
		$visitor_id = isset( $visitor ) ? $visitor->get_id() : NULL;

		echo '<div class="reg-man-rc-item visitor-metabox reg-man-rc-metabox">';

			$input_list = Form_Input_List::create();

			$name = 'item_visitor_select';
			$id = 'item_visitor_select_id';
			ob_start();
				self::render_visitor_select( $name, $id, $visitor_id );
			$select_html = ob_get_clean();
			$label = '';
			$hint = '';
			$classes = '';
			$is_required = FALSE;
			$input_list->add_custom_html_input( $label, $name, $select_html, $hint, $classes, $is_required, $id );

			$visitor_details_list = Form_Input_List::create();
			$visitor_details_list->set_required_inputs_flagged( TRUE );

				$label = __( 'Public name', 'reg-man-rc' );
				$name = 'visitor_public_name';
				$val = '';
				$hint = __( 'The name used to refer to the visitor publicly at an event, e.g. first name or first name and last initial', 'reg-man-rc' );
				$classes = '';
				$is_required = TRUE;
				$visitor_details_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required );

				$info_html = __( 'The following personal information is collected for internal records only and is never shown on the public website.', 'reg-man-rc' );
				$visitor_details_list->add_information( '', $info_html );

				$label = __( 'Is this the visitor\'s first event?', 'reg-man-rc' );
				$name = 'is_visitor_first_event';
				$val = 'YES';
				$checked = FALSE;
				$visitor_details_list->add_checkbox_input( $label, $name, $val, $checked );

				$label = __( 'Full Name', 'reg-man-rc' );
				$name = 'visitor_full_name';
				$val = '';
				$hint = '';
				$classes = '';
				$visitor_details_list->add_text_input( $label, $name, $val, $hint, $classes );

				$label = __( 'Email', 'reg-man-rc' );
				$name = 'visitor_email';
				$val = '';
				$hint = '';
				$classes = '';
				$visitor_details_list->add_text_input( $label, $name, $val, $hint, $classes );

				$label = __( 'Join mailing list?', 'reg-man-rc' );
				$name = 'visitor_join_mail_list';
				$val = 'YES';
				$checked = FALSE;
				$hint = '';
				$classes = '';
				$visitor_details_list->add_checkbox_input( $label, $name, $val, $checked );

			$label = __( 'Visitor details', 'reg-man-rc' );
			$is_open = TRUE;
			$hint = '';
			$classes = 'visitor-details-container';
			$is_required = FALSE;

			$input_list->add_fieldset( $label, $visitor_details_list, $hint, $classes, $is_required );

			$input_list->render();

		echo '</div>';

	} // function


	private static function render_visitor_select( $input_name, $input_id, $selected_id = NULL ) {

		// Disabled to start with until it is initialized on the client side
		echo "<select required=\"required\" class=\"combobox\" name=\"$input_name\" id=\"$input_id\" autocomplete=\"off\"  disabled=\"disabled\" >";

//			$label = __( 'No visitor', 'reg-man-rc' );
//			$label_attr = esc_attr( $label );
//			echo "<optgroup label=\"$label_attr\">";
//				$label = __( 'No visitor selected', 'reg-man-rc' );
				$label = __( '-- Please select --', 'reg-man-rc' );
				$html_name = esc_html( $label );
				$selected = ( empty( $selected_id ) ) ? 'selected="selected"' : '';
				echo "<option value=\"\" disabled=\"disabled\" $selected>$html_name</option>";
//			echo '</optgroup>';

			$visitor_array = Visitor::get_all_visitors();
//		Error_Log::var_dump( $visitor_array );

			if ( ! empty( $visitor_array ) ) {
//				$label = __( 'Select returning visitor', 'reg-man-rc' );
//				$label_attr = esc_attr( $label );
//				echo "<optgroup label=\"$label_attr\">";
					foreach ( $visitor_array as $visitor ) {
						$id = $visitor->get_id();
						$label = $visitor->get_label();
						$html_label = esc_html( $label );
						$selected = selected( $id, $selected_id, $echo = FALSE );
						$id_attr = esc_attr( $id );
						echo "<option value=\"$id_attr\" $selected>$html_label</option>";
					} // endfor
//				echo '</optgroup>';
			} // endif

//			$label = __( 'Add visitor', 'reg-man-rc' );
//			$label_attr = esc_attr( $label );
//			echo "<optgroup label=\"$label_attr\">";
				$label = __( 'Add a new visitor', 'reg-man-rc' );
				$html_name = esc_html( $label );
				$selected = '';
				echo "<option value=\"-1\" class=\"select_option_add\" $selected>$html_name</option>";
//			echo '</optgroup>';

		echo '</select>';
	} // function



	/**
	 * Render the meta box for the item status
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_status_meta_box( $post ) {

		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="item_status_input_flag" value="TRUE">';

		$item = Item::get_item_by_id( $post->ID );
		$curr_item_status = ( ! empty( $item ) ) ? $item->get_status() : Item_Status::get_default_item_status();
		$curr_status_id = $curr_item_status->get_id();

		$options = Item_Status::get_all_item_statuses();
		$input_name = 'status_id';
		$input_id = Item::POST_TYPE . '-status-input';

		$format = '<div><label title="%1$s"><input type="radio" name="' . $input_name . '" value="%2$s" %3$s><span>%4$s</span></label></div>';
		foreach ( $options as $status ) {
			$id = $status->get_id();
			$name = $status->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = checked( $id, $curr_status_id, $echo = FALSE );
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor

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
				( $screen->post_type == Item::POST_TYPE ) &&
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
		if ( Item::POST_TYPE === get_post_type() ) {
			return __( 'Enter the description of the item here, e.g. Lamp', 'reg-man-rc' );
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
		$messages[ Item::POST_TYPE ] = array(
				0 => '',
				1 => sprintf( __('Item updated. <a href="%s">View</a>'), esc_url( $permalink ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __('Item updated.'),
				5 => isset($_GET['revision']) ? sprintf( __('Item restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => sprintf( __('Item published. <a href="%s">View</a>'), esc_url( $permalink ) ),
				7 => __('Item saved.'),
				8 => sprintf( __('Item submitted. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				9 => sprintf( __('Item scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>'), $date, esc_url( $permalink ) ),
				10 => sprintf( __('Item draft updated. <a target="_blank" href="%s">Preview</a>'), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
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
			'cb'				=> $columns[ 'cb' ],
			'title'				=> __( 'Description', 'reg-man-rc' ),
			'event'				=> __( 'Event', 'reg-man-rc' ),
			'visitor'			=> __( 'Visitor', 'reg-man-rc' ),
			'item_type'			=> __( 'Item Type', 'reg-man-rc' ),
			'fixer_station'		=> __( 'Fixer Station', 'reg-man-rc' ),
			'item_status'		=> __( 'Status', 'reg-man-rc' ),
			'date'				=> __( 'Last Update', 'reg-man-rc' ),
			'author'			=> __( 'Author', 'reg-man-rc' ),
		);
		if ( Settings::get_is_allow_item_comments() ) {
			$result[ 'comments' ]	= $columns[ 'comments' ];
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
		$item = Item::get_item_by_id( $post_id );
		if ( $item !== NULL ) {
			switch ( $column_name ) {
				case 'event':
					$event = $item->get_event();
					if ( $event !== NULL ) {
						$filter_array = array( Item::EVENT_META_KEY			=> $event->get_key() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $event->get_label() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
					} else {
						$result = $em_dash;
					} // endif
					break;
				case 'visitor':
					$visitor = $item->get_visitor();
					if ( $visitor !== NULL ) {
						$filter_array = array( Item::VISITOR_META_KEY		=> $visitor->get_id() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $visitor->get_label() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
					} else {
						$result = $em_dash;
					} // endif
					break;
				case 'item_type':
					$item_type = $item->get_item_type();
					if ( isset( $item_type) ) {
						$filter_array = array( Item_Type::TAXONOMY_NAME		=> $item_type->get_slug() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $item_type->get_name() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
					} else {
						$result = $em_dash;
					} // endif
					break;
				case 'fixer_station':
					$fixer_station = $item->get_fixer_station();
					if ( isset( $fixer_station) ) {
						$filter_array = array( Fixer_Station::TAXONOMY_NAME	=> $fixer_station->get_slug() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $fixer_station->get_name() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
					} else {
						$result = $em_dash;
					} // endif
					break;
				case 'item_status':
					$status = $item->get_status();
					if ( ( $status !== NULL ) && ( $status->get_id() !== Item_Status::REGISTERED ) ) {
						$result = esc_html( $status->get_name() );
					} else {
						$result = $em_dash;
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
	 * Get an href for the admin page for this type and include the specified filters
	 * @param	string[][]	$filter_array	An array of filter keys and values
	 * @return	string		An escaped href attribute suitable for use in an anchor tag
	 */
	public static function get_admin_view_href( $filter_array = array() ) {
		$post_type = Item::POST_TYPE;
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

		if ( is_admin() && ( $post_type == Item::POST_TYPE ) ) {

			// Add a filter for the events
			echo '<span class="combobox-container">';
				$filter_name = Item::EVENT_META_KEY;
				$curr_event = isset( $_REQUEST[ $filter_name ] ) ? wp_unslash( $_REQUEST[ $filter_name ] ) : 0;
				$classes = 'reg-man-rc-filter postform';
				$calendar = Calendar::get_admin_calendar();
				$name = esc_html( __( 'All Events', 'reg-man-rc' ) );
				$selected = selected( $curr_event, 0, FALSE );
				$first_option = "<option value=\"0\" $selected>$name</option>";
				$is_required = TRUE;
				Event_View::render_event_select( $filter_name, $classes, $calendar, $curr_event, $first_option, $is_required );
			echo '</span>';

			// Add a filter for visitors
			$all_visitors = Visitor::get_all_visitors();

			$filter_name = Item::VISITOR_META_KEY;
			$curr_visitor = isset( $_REQUEST[ $filter_name ] ) ? wp_unslash( $_REQUEST[ $filter_name ] ) : 0;
			echo '<span class="combobox-container">';
				// Disabled to start with until it is initialized on the client side
				echo "<select class=\"combobox reg-man-rc-filter postform\" name=\"$filter_name\" autocomplete=\"off\" disabled=\"disabled\">";

					$name = esc_html( __( 'All Visitors', 'reg-man-rc' ) );
					$selected = selected( $curr_visitor, 0, FALSE );
					echo "<option value=\"0\" $selected>$name</option>";

					foreach ( $all_visitors as $visitor ) {
						$name = esc_html( $visitor->get_full_name() );
						$id = $visitor->get_id();
						$id_attr = esc_attr( $id );
						$selected = selected( $curr_visitor, $id, FALSE );
						echo "<option value=\"$id_attr\" $selected>$name</option>";
					} // endif

				echo '</select>';
			echo '</span>';

			// Add a filter for each of these taxonomies
			// FIXME - I would like to provide Uncategorized option but the query does not work properly
			//  Most likely I will have to intervene and alter the query to make this work
			$tax_name_array = array(
					Item_Type::TAXONOMY_NAME		=> __( '[No item type]', 'reg-man-rc' ),
					Fixer_Station::TAXONOMY_NAME	=> __( '[No fixer station]', 'reg-man-rc' ),
			);
			foreach ( $tax_name_array as $tax_name => $none_option_label ) {
				$taxonomy = get_taxonomy( $tax_name );
				$curr_id = isset( $_REQUEST[ $tax_name ] ) ? $_REQUEST[ $tax_name ] : '';
				wp_dropdown_categories( array(
					'show_option_all'	=> $taxonomy->labels->all_items,
//					'show_option_none'	=> $none_option_label,
//					'option_none_value'	=> -1,
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
		if ( $post_type === Item::POST_TYPE ) {
			$result = array();
		} else {
			$result = $months;
		} // endif
		return $result;
	} // function

} // class
