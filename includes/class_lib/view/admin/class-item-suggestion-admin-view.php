<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Control\Admin\Item_Suggestion_Admin_Controller;

/**
 * The administrative view for Item Suggestions
 *
 * @since	v0.1.0
 *
 */
class Item_Suggestion_Admin_View {

	/**
	 * Set up the necessary hooks, filters etc. for this administrative view
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Show my admin notice to create the defaults when necessary
		if ( self::get_is_show_create_defaults_admin_notice() ) {
			add_action( 'admin_notices', array( __CLASS__, 'handle_admin_notices' ) );
		} // endif

		// Change the placeholder text for "Enter Title Here"
		add_filter( 'enter_title_here', array(__CLASS__, 'rewrite_enter_title_here') );

		// Note that screen settings are modified by the Screen_Settings_Admin_View class

		// Change the messages that are shown when the post is updated
		add_filter( 'post_updated_messages', array(__CLASS__, 'handle_post_updated_messages') );

		// Add a column to the admin UI
		add_filter( 'manage_' . Item_Suggestion::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the columns in the admin UI
		add_action( 'manage_' . Item_Suggestion::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );

		// Set up the meta boxes
		add_action( 'add_meta_boxes' , array( __CLASS__, 'add_meta_boxes' ), 10, 2 );

		// Insert the post filtering UI
		add_action( 'restrict_manage_posts', array( __CLASS__, 'modify_posts_filters_UI' ) );

	} // function

	/**
	 * Handle the admin notices action hook
	 */
	public static function handle_admin_notices() {
		global $pagenow;
		if ( $pagenow === 'edit.php' ) {
			$cpt_name = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : '';
			if ( $cpt_name === Item_Suggestion::POST_TYPE ) {
				if ( self::get_is_show_create_defaults_admin_notice() ) {
					self::render_no_suggestions_admin_notice();
				} // endif
			} // endif
		} // endif
	} // function

	/**
	 * Return a flag to indicate whether to show an admin notice to create the defaults for this custom post type.
	 * This will be TRUE when there are no posts of this custom type published,
	 *  AND the administrator has not chosen to skip this step,
	 *  AND there is at least one default suggestion that can be created (meaning at least one default fixer type exists)
	 * @return boolean	TRUE if an admin notice should be displayed, FALSE otherwise
	 */
	public static function get_is_show_create_defaults_admin_notice() {
		$count_obj = wp_count_posts( Item_Suggestion::POST_TYPE );
		$count = isset( $count_obj->publish ) ? $count_obj->publish : 0;
		$result = ( ( $count === 0 ) &&
					( ! Settings::get_is_object_type_init_skipped( Item_Suggestion::POST_TYPE ) ) &&
//					( ! empty( Fixer_Station::get_all_term_taxonomy_ids() ) ) && // This avoids loading the cache
					( ! empty( Item_Suggestion::get_default_suggestion_data() ) ) );
		return $result;
	} // function


	/**
	 * Render admin notice for no categories
	 */
	private static function render_no_suggestions_admin_notice() {
		echo '<div class="notice notice-warning">';
			$heading = __( 'No item suggestions are defined', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			self::render_create_defaults_form();
		echo '</div>';
	} // function

	/**
	 * Render the form for creating the default event categories
	 */
	private static function render_create_defaults_form() {

		$all_item_types = Item_Type::get_all_item_types();
		$all_fixer_stations = Fixer_Station::get_all_fixer_stations();
		$default_array = Item_Suggestion::get_default_suggestion_data();

		if ( empty( $all_fixer_stations ) || empty( $all_item_types ) ) {
			$warning = __(
				'Note that item suggestions refer to item types and fixer stations.' .
				'  Item types are optional but you should define your fixer stations before creating item suggestions.',
				'reg-man-rc' );
			echo "<h3>$warning</h3>";
		} // endif

		if ( ! empty( $default_array ) && ! empty( $all_fixer_stations ) ) {

			echo '<p>';
				$label = __( 'The following are typical item suggestions.', 'reg-man-rc' );
				echo '<b>' . esc_html( $label ) . '</b>';
			echo '</p>';

			echo '<p>';
				$label = __( 'Items are grouped to make it easier for you to assign the default item type and fixer station for all items in each group.', 'reg-man-rc' );
				echo esc_html( $label );
			echo '</p>';

			$sugg_groups = array(); // I will break the defaults up by their group name
			$default_type_array = array(); // Each group will have a default item type
			$default_station_array = array(); // Each group will have a default fixer station
			foreach ( $default_array as $index => $suggestion ) {
				$group = esc_html( $suggestion[ 'group_name' ] );
				if ( ! isset( $sugg_groups[ $group ] ) ) {
					$sugg_groups[ $group ] = array();
				} // endif
				$sugg_groups[ $group ][ $index ] = $suggestion;
				if ( ! isset( $default_type_array[ $group ] ) && isset( $suggestion[ 'item_type' ] ) ) {
					$type = Item_Type::get_item_type_by_name( $suggestion[ 'item_type' ] );
					if ( isset( $type ) ) {
						$default_type_array[ $group ] = $type->get_id();
					} // endif
				} // endif
				if ( ! isset( $default_station_array[ $group ] ) && isset( $suggestion[ 'fixer_station' ] ) ) {
					$station = Fixer_Station::get_fixer_station_by_name( $suggestion[ 'fixer_station' ] );
					if ( isset( $station ) ) {
						$default_station_array[ $group ] = $station->get_id();
					} // endif
				} // endif
			} // endfor

			$none_label = __( '[None]', 'reg-man-rc' );
			$group_item_type_options = array();
			$group_item_type_options[ $none_label ] = 0;
			foreach ( $all_item_types as $item_type ) {
				$id = $item_type->get_id();
				$name = esc_html( $item_type->get_name() );
				$group_item_type_options[ $name ] = $id;
			} // endfor

			$group_fixer_station_options = array();
			foreach ( $all_fixer_stations as $fixer_station ) {
				$id = $fixer_station->get_id();
				$name = esc_html( $fixer_station->get_name() );
				$group_fixer_station_options[ $name ] = $id;
			} // endfor

			$create_item_label = esc_html__( 'Create', 'reg-man-rc' );
			$sugg_format =
				'<tr>' .
					'<td class="checkbox">' .
						'<label>' .
//							'<input type="checkbox" autocomplete="off" name="create[%1$s]" class="create_suggestion" value="1" checked="checked">' .
							'<input type="checkbox" autocomplete="off" name="create[%1$s]" class="create_suggestion" value="%4$s">' .
							$create_item_label .
						'</label>' .
					'</td>' .
					'<td class="text-input">' .
						'<input type="text" required="required" autocomplete="off" name="name[%1$s]" value="%2$s" size="20">' .
					'</td>' .
					'<td class="text-input">' .
						'<input type="text" autocomplete="off" name="alt_names[%1$s]" value="%3$s" size="40">' .
					'</td>' .
				'</tr>';

			$method = 'POST';
			$form_action = esc_url( admin_url( 'admin-post.php' ) );
			$admin_post_action = Item_Suggestion_Admin_Controller::CREATE_DEFAULTS_FORM_ACTION;
			$classes = 'reg-man-rc-admin-create-defaults-form reg-man-rc-admin-notice-form';

			$create_selected_label = esc_html( __( 'Create Selected', 'reg-man-rc' ) );
			$item_name_label = esc_html( __( 'Item Description', 'reg-man-rc' ) );
			$alt_name_label = esc_html( __( 'Alternate Description(s)', 'reg-man-rc' ) );

			echo "<form class=\"$classes\" method=\"$method\" action=\"$form_action\">";
				echo "<input type=\"hidden\" name=\"action\" value=\"$admin_post_action\">"; // required for admin-post

				$default_group_option_name = __( 'None', 'reg-man-rc' ); // Default for Item Type or Fixer Station

				$main_input_list = Form_Input_List::create();

				$select_all_label = __( 'Select All', 'reg-man-rc' );
				$select_none_label = __( 'Deselect All', 'reg-man-rc' );
				$create_button_container =
						'<div class="reg-man-rc-admin-create-defaults-button-container">' .
							'<button type="button" class="select-all-button button">' . $select_all_label . '</button>' .
							'<button type="button" class="select-none-button button">' . $select_none_label . '</button>' .
						'</div>';

				$table_head =
						'<tr>' .
							'<th>' . $create_selected_label . $create_button_container .
							'<th>' . $item_name_label . '</th>' .
							'<th>' . $alt_name_label . '</th>' .
						'</tr>';

				$group_index = 0;
				foreach( $sugg_groups as $group_name => $sugg_array ) {

					$group_count = count( $sugg_array );
					/* Translators: %1$s is the name of a category of item suggestions, %2$s is a count of items in the category */
					$group_heading_format = _n( '%1$s (%2$s item)', '%1$s (%2$s items)', $group_count, 'reg-man-rc' );
					$group_label = sprintf( $group_heading_format, $group_name, $group_count );

					$group_input_list = Form_Input_List::create();
					if ( ! empty( $all_item_types ) ) {
						$label = __( 'Item type for this group', 'reg-man-rc' );
						$name = "group_default_item_type[$group_name]";
						$selected = isset( $default_type_array[ $group_name ] ) ? $default_type_array[ $group_name ] : NULL;
						$hint = __( 'Items in this group are assigned the selected item type', 'reg-man-rc' );
						$group_input_list->add_select_input( $label, $name, $group_item_type_options, $selected, $hint );
					} // endif
					if ( ! empty( $all_fixer_stations ) ) {
						$label = __( 'Fixer station for this group', 'reg-man-rc' );
						$name = "group_default_fixer_station[$group_name]";
						$selected = isset( $default_station_array[ $group_name ] ) ? $default_station_array[ $group_name ] : NULL;
						$hint = __( 'Items in this group are assigned to the selected fixer station', 'reg-man-rc' );
						$group_input_list->add_select_input( $label, $name, $group_fixer_station_options, $selected, $hint );
					} // endif

					ob_start();

						echo '<table class="reg-man-rc-admin-create-defaults-table reg-man-rc-admin-create-item-sugg-table">';

							echo $table_head;

							foreach ( $sugg_array as $item_index => $suggestion ) {
								$desc = esc_html( $suggestion[ 'item_desc' ] );
								$alt_desc = esc_html( $suggestion[ 'alt_desc' ] );
								$group_name = $suggestion[ 'group_name' ];
	//							printf( $sugg_format, $item_index, $desc, $alt_desc, $item_type_select, $fixer_station_select, $group_name );
								printf( $sugg_format, $item_index, $desc, $alt_desc, $group_name );
							} // endfor

						echo '</table>';
						$group_index++;
					$table_contents = ob_get_clean();

					$label = '';
					$name = '';
					$group_input_list->add_custom_html_input( $label, $name, $table_contents );

					$main_input_list->add_fieldset( $group_label, $group_input_list );

				} // endfor

				$main_input_list->render();

				echo '</p>';
					$label = __( 'Note that you can always add others or change and delete these later', 'reg-man-rc' );
					echo '<b>' . esc_html( $label ) . '</b>';
				echo '</p>';

				echo '<div class="reg-man-rc-button-container">';
					$button_format = '<button type="submit" name="button_action" value="%3$s" class="reg-man-rc-button %2$s">%1$s</button>';

					$class = 'skip_defaults';
					$value = 'skip';
					$label = __( 'Skip This', 'reg-man-rc' );
					printf( $button_format, $label, $class, $value );

					$class = 'create_defaults';
					$value = 'create';
					$label = __( 'Create Selected', 'reg-man-rc' );
					printf( $button_format, $label, $class, $value );

				echo '</div>';
			echo '</form>';
		} // endif

	} // function

	private static function get_fixer_station_select( $input_name, $selected_name, $default_option_name ) {
		$result = "<select name=\"$input_name\" autocomplete=\"off\" required=\"required\">";
			$selected_station = Fixer_Station::get_fixer_station_by_name( $selected_name );
			$selected_id = isset( $selected_station ) ? $selected_station->get_id() : 0;
			$id = 0;
			$selected_attr = ( $id == $selected_id ) ? 'selected="selected"' : '';
			$result .= "<option value=\"$id\" $selected_attr>$default_option_name</option>";
			$all_fixer_stations = Fixer_Station::get_all_fixer_stations();
			foreach ( $all_fixer_stations as $fixer_station ) {
				$id = $fixer_station->get_id();
				$name = esc_html( $fixer_station->get_name() );
				$selected_attr = ( $id == $selected_id ) ? 'selected="selected"' : '';
				$result .= "<option value=\"$id\" $selected_attr>$name</option>";
			} // endfor
		$result .= '</select>';
		return $result;
	} // function

	private static function get_item_type_select( $input_name, $selected_name, $default_option_name ) {
		$result = "<select name=\"$input_name\" autocomplete=\"off\" required=\"required\">";
			$selected_type = Item_Type::get_item_type_by_name( $selected_name );
			$selected_id = isset( $selected_type ) ? $selected_type->get_id() : 0;
			$id = 0;
			$selected_attr = ( $id == $selected_id ) ? 'selected="selected"' : '';
			$result .= "<option value=\"$id\" $selected_attr>$default_option_name</option>";
			$all_item_types = Item_Type::get_all_item_types();
			foreach ( $all_item_types as $item_type ) {
				$id = $item_type->get_id();
				$name = esc_html( $item_type->get_name() );
				$selected_attr = ( $id == $selected_id ) ? 'selected="selected"' : '';
				$result .= "<option value=\"$id\" $selected_attr>$name</option>";
			} // endfor
		$result .= '</select>';
		return $result;
	} // function



	/**
	 * Add the meta boxes for Item Suggestions
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {

		if ( $post_type == Item_Suggestion::POST_TYPE ) {

			// Alternate descriptions
			$new_id = Item_Suggestion::POST_TYPE . '-alt-desc-metabox';
			$render_fn = array( __CLASS__, 'render_alt_desc_metabox' );
			add_meta_box(
					$new_id,								// Unique ID for the element
					__( 'Alternate Descriptions', 'reg-man-rc' ),		// Box title
					$render_fn,								// Content callback, must be of type callable
					Item_Suggestion::POST_TYPE, 			// Post type for this meta box
					'normal',								// Meta box position
					'high'									// Meta box priority
			);

			// Fixer Station
			$view = Fixer_Station_Admin_View::create();
			$new_id = Item_Suggestion::POST_TYPE . '-fixer-station-metabox';
			$render_fn = array( $view, 'render_post_metabox' );
			add_meta_box(
					$new_id,								// Unique ID for the element
					__( 'Fixer Station', 'reg-man-rc' ),	// Box title
					$render_fn,								// Content callback, must be of type callable
					Item_Suggestion::POST_TYPE, 			// Post type for this meta box
					'side',									// Meta box position
					'high'									// Meta box priority
			);

			// Item Type
			$all_item_types = Item_Type::get_all_item_types();
			if ( ! empty( $all_item_types ) )  {
				$view = Item_Type_Admin_View::create();
				$new_id = Item_Suggestion::POST_TYPE . '-item_type-metabox';
				$render_fn = array( $view, 'render_post_metabox' );
				add_meta_box(
						$new_id,								// Unique ID for the element
						__( 'Item Type', 'reg-man-rc' ),		// Box title
						$render_fn,								// Content callback, must be of type callable
						Item_Suggestion::POST_TYPE, 			// Post type for this meta box
						'side',									// Meta box position
						'high'									// Meta box priority
				);
			} // endif
			
		} // endif
		
	} // function

	/**
	 * Render the alternate descriptions metabox for the specified post
	 * @param	\WP_Post	$post
	 */
	public static function render_alt_desc_metabox( $post ) {
		if ( $post->post_type === Item_Suggestion::POST_TYPE ) {

			// We need a flag to distinguish the case where no user input is provided
			//  versus the case where no inputs were shown at all like in quick edit mode
			echo '<input type="hidden" name="alt_desc_input_flag" value="TRUE">';
			$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post->ID );
			$input_list = Form_Input_List::create();
			$label = __( 'Alternate description list', 'reg-man-rc' );
			// Note that we use the name 'post_content' so that this will automatically be saved there
			$name = 'post_content';
			$val = isset( $item_suggestion ) ? $item_suggestion->get_alternate_description_list_text() : '';
			$hint = __( 'Enter a comma-separated list of alternate descriptions.  E.g. For "Lamp" you might enter "Light, Desklight"', 'reg-man-rc' );
			$classes = 'full-width'; // We want a wide text input here
			$is_required = FALSE;
			$addn_attrs = '';
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );

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
				( $screen->post_type == Item_Suggestion::POST_TYPE ) &&
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
		if ( Item_Suggestion::POST_TYPE === get_post_type() ) {
			return __( 'Enter the description of the item suggestion here, e.g. Lamp', 'reg-man-rc' );
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
		$messages[ Item_Suggestion::POST_TYPE ] = array(
				0 => '',
				1 => __( 'Item Suggestion updated.', 'reg-man-rc' ),
				2 => __( 'Custom field updated.', 'reg-man-rc' ),
				3 => __( 'Custom field deleted.', 'reg-man-rc' ),
				4 => __( 'Item Suggestion updated.', 'reg-man-rc' ),
				5 => isset($_GET['revision']) ? sprintf( __( 'Item Suggestion restored to revision from %s', 'reg-man-rc' ), wp_post_revision_title( (int) $_GET['revision'], FALSE ) ) : FALSE,
				6 => __( 'Item Suggestion published.', 'reg-man-rc' ),
				7 => __( 'Item Suggestion saved.', 'reg-man-rc' ),
				8 => __( 'Item Suggestion submitted.', 'reg-man-rc' ),
				9 => sprintf( __( 'Item Suggestion scheduled for: <strong>%1$s</strong>', 'reg-man-rc' ) , $date ),
				10 => __( 'Item Suggestion draft updated.', 'reg-man-rc' ),
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
			'aka'				=> __( 'Alternate Descriptions', 'reg-man-rc' ),
			'item_type'			=> __( 'Item Type', 'reg-man-rc' ),
			'fixer_station'		=> __( 'Fixer Station', 'reg-man-rc' ),
			'date'				=> __( 'Last Update', 'reg-man-rc' ),
			'author'			=> __( 'Author', 'reg-man-rc' ),
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
		$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post_id );
		if ( $item_suggestion !== NULL ) {
			switch ( $column_name ) {
				case 'aka':
					$alt_desc = $item_suggestion->get_alternate_description_list_text();
					$result = ! empty( $alt_desc) ? esc_html( $alt_desc ) : $em_dash;
					break;
				case 'item_type':
					$item_type = $item_suggestion->get_item_type();
					if ( isset( $item_type) ) {
						$filter_array = array( Item_Type::TAXONOMY_NAME => $item_type->get_slug() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $item_type->get_name() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
					} else {
						$result = $em_dash;
					} // endif
					break;

				case 'fixer_station':
					$fixer_station = $item_suggestion->get_fixer_station();
					if ( isset( $fixer_station) ) {
						$filter_array = array( Fixer_Station::TAXONOMY_NAME => $fixer_station->get_slug() );
						$href = self::get_admin_view_href( $filter_array );
						$label = esc_html( $fixer_station->get_name() );
						$link_format = '<div class="cpt-filter-link"><a href="%1$s">%2$s</a></div>';
						$result = sprintf( $link_format, $href, $label );
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
		$post_type = Item_Suggestion::POST_TYPE;
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

		if ( is_admin() && ( $post_type == Item_Suggestion::POST_TYPE ) ) {

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
		if ( $post_type === Item_Suggestion::POST_TYPE ) {
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
			$heading = __( 'About item suggestions', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'Item suggestions help reduce typing when registering new items.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'When you register an item and begin typing the description, the system' .
					' will find and display matching item suggestions.' .
					'  For example, if you type "light" the system may show item suggestions like "Lamp", "Bike light" and "Nightlight".',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'An item suggestion contains a description like "Lamp",' .
					' a comma-separated list of alternate descriptions like "Light, Desk lamp",' .
					' a default item type like "Electrical / Electronic",' .
					' and a default fixer station for the item like "Appliances & Housewares".',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Item suggestions will pop up when a user begins typing an item description during registration.' .
					'  Selecting a suggestion will fill in the fields for the item.' .
					'  After selection the fields may be modified if necessary.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Because item suggestions refer to fixer stations and item types, you should create those first ' .
					' before creating your item suggestions.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			$result = ob_get_clean();
		return $result;
	} // function

	
} // class
