<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Admin\Item_Type_Admin_Controller;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The administrative view for item type
 *
 * @since	v0.1.0
 *
 */
class Item_Type_Admin_View {

	const INPUT_NAME = 'item_type';
	const INPUT_ID = 'item-type-input';

	private function __construct() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Show my admin notice to create the defaults when necessary
		if ( self::get_is_show_create_defaults_admin_notice() ) {
			add_action( 'admin_notices', array( __CLASS__, 'handle_admin_notices' ) );
		} // endif

		// Hide the slug field in the add form
		add_action( Item_Type::TAXONOMY_NAME . '_add_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );
		add_action( Item_Type::TAXONOMY_NAME . '_edit_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );

		// Render the input field in the admin UI for adding a new term using "_add_form_fields" hook
		add_action( Item_Type::TAXONOMY_NAME . '_add_form_fields', array( __CLASS__, 'render_add_term_admin_fields' ), 10, 1 );

		// Render the input field in the admin UI for updating them term using "_edit_form_fields" hook
		add_action( Item_Type::TAXONOMY_NAME . '_edit_form_fields', array( __CLASS__, 'render_edit_term_admin_fields' ), 10, 2 );

		// Modify columns in the admin UI term list
		add_filter( 'manage_edit-' . Item_Type::TAXONOMY_NAME . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ), 10, 1 );

		// Put values into my columns in the term list
		add_filter( 'manage_' . Item_Type::TAXONOMY_NAME . '_custom_column', array( __CLASS__, 'get_admin_UI_column_values' ), 10, 3 );

		// Add something to allow re-ordering
//		add_action( 'after-' . Item_Type::TAXONOMY_NAME . '-table', array( __CLASS__, 'render_pre_add_form' ), 10, 1 );
//		add_action( Item_Type::TAXONOMY_NAME . '_pre_add_form', array( __CLASS__, 'render_pre_add_form' ), 10, 1 );

		// Register my columns as sortable
//		add_filter( 'manage_edit-' . Item_Type::TAXONOMY_NAME . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ), 10, 1 );

		// Tell WP how to do the sorting for my columns
		// FIXME - Should this really use get_terms hook?  For now I will not do any ordering
//		add_action( 'pre_get_terms', array( __CLASS__, 'modify_query_for_sort' ) );

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
				( $screen->base == 'edit-tags' ) &&
				( $screen->taxonomy == Item_Type::TAXONOMY_NAME ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
	} // function


	/**
	 * Handle the admin notices action hook
	 */
	public static function handle_admin_notices() {
		global $pagenow;
		if ( $pagenow === 'edit-tags.php' ) {
			$tax_name = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : '';
			if ( $tax_name === Item_Type::TAXONOMY_NAME ) {
				if ( self::get_is_show_create_defaults_admin_notice() ) {
					self::render_no_categories_admin_notice();
				} // endif
			} // endif
		} // endif
	} // function

	/**
	 * Render admin notice for no categories
	 */
	private static function render_no_categories_admin_notice() {
		echo '<div class="notice notice-warning">';
			$heading = __( 'No item types are defined', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			self::render_create_defaults_form();
		echo '</div>';
	} // function


	/**
	 * Return a flag to indicate whether to show an admin notice for this taxonomy if it's empty
	 * @return boolean	TRUE if an admin notice should be displayed, FALSE otherwise
	 */
	public static function get_is_show_create_defaults_admin_notice() {
		// We need to do this earlier without initializing the cache so just get the terms here
		$args = array(
				'taxonomy'		=> Item_Type::TAXONOMY_NAME,
				'hide_empty'	=> FALSE, // We want all, including those used by no post
		);
		$term_array = get_terms( $args );
		$result = ( empty( $term_array ) && ( ! Settings::get_is_object_type_init_skipped( Item_Type::TAXONOMY_NAME ) ) );
		return $result;
	} // function


	/**
	 * Hide the slug form field for my taxonomy
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function hide_slug_form_field( $taxonomy_slug ) {
		echo '<style>.term-slug-wrap { display:none; }</style>';
	} // function


	/**
	 * Render the form for creating the default event categories
	 */
	private static function render_create_defaults_form() {

		$default_array = Item_Type::get_default_types_data();

		if ( ! empty( $default_array ) ) {

			$create_label = esc_html( __( 'Create', 'reg-man-rc' ) );
			$row_format =
				'<tr>' .
					'<td class="checkbox">' .
						'<label>' .
							'<input type="checkbox" autocomplete="off" name="create[%1$s]" value="1" checked="checked">' .
							$create_label .
						'</label>' .
					'</td>' .
					'<td class="text-input">' .
						'<input type="text" required="required" autocomplete="off" name="name[%1$s]" value="%2$s" size="20">' .
					'</td>' .
					'<td class="textarea-input">' .
						'<textarea rows="2" cols="50" autocomplete="off" name="desc[%1$s]">%3$s</textarea>' .
					'</td>' .
					'<td class="colour-input">' .
						'<input type="color" autocomplete="off" name="colour[%1$s]" value="%4$s">' .
					'</td>' .
				'</tr>';

			echo '<p>';
				$label = __( 'The following are typical item types you may choose to create to help get you started quickly', 'reg-man-rc' );
				echo '<b>' . esc_html( $label ) . '</b>';
			echo '</p>';

			$method = 'POST';
			$form_action = esc_url( admin_url( 'admin-post.php' ) );
			$admin_post_action = Item_Type_Admin_Controller::CREATE_DEFAULTS_FORM_ACTION;
			$classes = 'reg-man-rc-admin-create-defaults-form reg-man-rc-admin-notice-form';

			echo "<form class=\"$classes\" method=\"$method\" action=\"$form_action\">";
				echo "<input type=\"hidden\" name=\"action\" value=\"$admin_post_action\">"; // required for admin-post
				echo '<table class="reg-man-rc-admin-create-defaults-table">';
					echo '<tr>';
						echo '<th>' . esc_html( __( 'Create Selected', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Name', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Description', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Colour Code', 'reg-man-rc' ) ) . '</th>';
					echo '</tr>';
					$index = 0;
					foreach ( $default_array as $type ) {
						$name = esc_html( $type[ 'name' ] );
						$desc = esc_html( $type[ 'description' ] );
						$colour = $type[ 'colour' ];
						printf( $row_format, $index, $name, $desc, $colour  );
						$index++;
					} // endfor
				echo '</table>';

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


	/**
	 * Render the metabox for the specified post
	 * @param	\WP_Post	$post
	 */
	public function render_post_metabox( $post ) {
		switch( $post->post_type ) {
			case Item::POST_TYPE:
				$item = Item::get_item_by_id( $post->ID );
				$item_type = ( ! empty( $item ) ) ? $item->get_item_type() : NULL;
				$selected_id = ( ! empty( $item_type ) ) ? $item_type->get_id() : NULL;
				$this->render_metabox_input( $selected_id );
				break;
			case Item_Suggestion::POST_TYPE:
				$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post->ID );
				$item_type = ( ! empty( $item_suggestion ) ) ? $item_suggestion->get_item_type() : NULL;
				$selected_id = ( !empty( $item_type ) ) ? $item_type->get_id() : NULL;
				$this->render_metabox_input( $selected_id );
				break;
		} // endswitch
	} // function

	private function render_metabox_input( $selected_id ) {
		// We need a flag to distinguish the case where no user input is provided
		//  versus the case where no inputs were shown at all like in quick edit mode
		echo '<input type="hidden" name="item_type_input_flag" value="TRUE">';

		echo '<div class="reg-man-rc-item item-type-metabox reg-man-rc-metabox">';
			if ( count( Item_Type::get_all_item_types() ) <= 3 ) {
				$this->render_radio( $selected_id );
			} else {
				$no_select_name = _x( '-- Please Select --', 'An option label to indicate that no selection has been made', 'reg-man-rc' );
				$no_select_value = '-1';
				$this->render_select( $selected_id, $no_select_name, $no_select_value );
			} // endif
		echo '</div>';
	} // endif

	private function render_radio( $selected_id  ) {
		$options = Item_Type::get_all_item_types();
		$input_name = esc_attr( self::INPUT_NAME );
		$input_id = esc_attr( self::INPUT_ID );

		$input_list = Form_Input_List::create();

		$format =
			'<label class="reg-man-rc-metabox-radio-label">' .
				'<input type="radio" data-fixer-station="" name="%3$s" value="%2$s" class="radio" %4$s required="required" autocomplete="off"><span>%1$s</span>' .
			'</label>';

		foreach ( $options as $option ) {
			$name = $option->get_name();
			$id = $option->get_id();
			$station = $option->get_fixer_station();
			$station_id = isset( $station ) ? $station->get_id() : '-1';
			$html_name = esc_html( $name );
			$checked = checked( $id, $selected_id, $echo = FALSE );
			printf( $format, $name, $id, $input_name, $checked );
		} // endfor

	} // function

	private function render_select( $selected_id, $no_select_name = NULL, $no_select_value = NULL  ) {
		$options = Item_Type::get_all_item_types();
		$input_name = esc_attr( self::INPUT_NAME );
		$input_id = esc_attr( self::INPUT_ID );
		echo "<select id=\"$input_id\" name=\"$input_name\" required=\"required\" autocomplete=\"off\">";
			if ( $no_select_name !== NULL ) {
				$selected = selected( NULL, $selected_id, $echo = FALSE );
				$html_name = esc_html( $no_select_name );
				echo "<option data-fixer-station=\"\" value=\"$no_select_value\" $selected disabled=\"disabled\">$html_name</option>";
			} // endif
			foreach ( $options as $option ) {
				$name = $option->get_name();
				$id = $option->get_id();
				$station = $option->get_fixer_station();
				$station_id = isset( $station ) ? $station->get_id() : '-1';
				$html_name = esc_html( $name );
				$selected = selected( $id, $selected_id, $echo = FALSE );
				echo "<option value=\"$id\" data-fixer-station=\"$station_id\" $selected>$html_name</option>";
			} // endfor
		echo '</select>';
	} // function

	private function render_radio_buttons( $selected_id ) {
		$options = Item_Type::get_all_item_types();
		$input_name = esc_attr( self::INPUT_NAME );
		$input_id = esc_attr( self::INPUT_ID );
		foreach ( $options as $option ) {
			$name = $option->get_name();
			$id = $option->get_id();
			$attr_name = esc_attr( $name );
			$html_name = esc_html( $name );
			$checked = checked( $id, $selected_id, $echo = FALSE );
			echo '<div>';
				echo "<label title=\"$attr_name\">";
					echo "<input type=\"radio\" name=\"$input_name\" value=\"$id\" $checked>";
					echo "<span>$html_name</span>";
				echo '</label>';
			echo '</div>';
		} // endfor
	} // function

	public static function render_add_term_admin_fields( $taxonomy_slug ) {

		// Fixer Station
		$stations = Fixer_Station::get_all_fixer_stations();
		if ( !empty( $stations ) ) {
			$input_id = Fixer_Station_Admin_View::INPUT_ID;
			$view = Fixer_Station_Admin_View::create();
			$label = __( 'Default Fixer Station', 'reg-man-rc' );
			$desc = __( 'The fixer station automatically assigned to items of this type.', 'reg-man-rc' );
			echo '<div class="form-field">';
				echo "<label for=\"$input_id\">";
					echo $label;
				echo '</label>';
				$view->render_input_for_item_type( NULL );
				echo '<p class="description">';
					echo $desc;
				echo '</p>';
			echo '</div>';
		} // endif

		// Colour
		$input_id = 'item-type-colour-input';
		$input_name = 'item-type-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this item type on a chart or graph.', 'reg-man-rc' );
		echo '<div class="form-field">';
			echo "<label for=\"$input_id\">";
				echo $label;
			echo '</label>';
			echo "<input type=\"color\" id=\"$input_id\" name=\"$input_name\" value=\"\">";
			echo '<p class="description">';
				echo $desc;
			echo '</p>';
		echo '</div>';
	} // function

	public static function render_edit_term_admin_fields( $term, $taxonomy_slug ) {

		$item_type = Item_Type::get_item_type_by_id( $term->term_id );

		$stations = Fixer_Station::get_all_fixer_stations();

		if ( ! empty( $stations ) ) {
			$input_id = Fixer_Station_Admin_View::INPUT_ID;
			$view = Fixer_Station_Admin_View::create();
			$fixer_station = !empty( $item_type ) ? $item_type->get_fixer_station() : NULL;
			$selected_id = !empty( $fixer_station ) ? $fixer_station->get_id() : NULL;
			$label = __( 'Default Fixer Station', 'reg-man-rc' );
			$desc = __( 'The fixer station automatically assigned to items of this type.', 'reg-man-rc' );
			echo '<tr class="form-field term-group-wrap">';
				echo '<th scope="row">';
					echo "<label for=\"$input_id\">";
						echo $label;
					echo '</label>';
				echo '</th>';
				echo '<td>';
					$view->render_input_for_item_type( $selected_id );
					echo '<p class="description">';
						echo $desc;
					echo '</p>';
				echo '</td>';
			echo '</tr>';
		} // endif

		$input_id = 'item-type-colour-input';
		$input_name = 'item-type-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this item type on a chart or graph.', 'reg-man-rc' );
		$colour = !empty( $item_type ) ? $item_type->get_colour() : '';
		echo '<tr class="form-field term-group-wrap">';
			echo '<th scope="row">';
				echo "<label for=\"$input_id\">";
					echo $label;
				echo '</label>';
			echo '</th>';
			echo '<td>';
			echo "<input type=\"color\" id=\"$input_id\" name=\"$input_name\" value=\"$colour\">";
			echo '<p class="description">';
					echo $desc;
				echo '</p>';
			echo '</td>';
		echo '</tr>';

		$input_id = 'item-type-ext-names-input';
		$input_name = 'item-type-ext-names';
		$label = __( 'Alternate Names', 'reg-man-rc' );
		$desc = __( 'Enter a pipe-separated list of alternate names used in external registration data for this item type, e.g. "Name 1 | Name 2".', 'reg-man-rc' );
		$ext_names = ! empty( $item_type ) ? $item_type->get_external_names() : '';
		echo '<tr class="form-field term-group-wrap">';
			echo '<th scope="row">';
				echo "<label for=\"$input_id\">";
					echo $label;
				echo '</label>';
			echo '</th>';
			echo '<td>';
			$names_text = implode( ' | ', $ext_names );
			echo "<input type=\"text\" id=\"$input_id\" name=\"$input_name\" value=\"$names_text\">";
			echo '<p class="description">';
					echo $desc;
				echo '</p>';
			echo '</td>';
		echo '</tr>';

	} // function

	public static function filter_admin_UI_columns( $columns ) {
		if ( empty( $columns ) ) {
			 // This hook is triggered on the term edit page with empty columns for some reason so don't fail
			$result = $columns;
		} else {
			$result = array(
				'cb'						=> $columns['cb'],
				'name'						=> $columns['name'],
				'description'				=> $columns['description'],
				'fixer_station'				=> __( 'Default Fixer Station', 'reg-man-rc' ),
				'colour'					=> __( 'Colour', 'reg-man-rc' ),
//				'order_index'				=> __( 'Custom Order', 'reg-man-rc' ),
				'ext_names'					=> __( 'Alternate Names', 'reg-man-rc' ),
				'item_count'				=> __( 'Item Count', 'reg-man-rc' ),
//				'slug'						=> $columns['slug'],
			);
			if ( ! Settings::get_is_show_external_names() ) {
				unset( $result[ 'ext_names' ] );
			} // endif
		} // endif
		return $result;
	} // function

	public static function get_admin_UI_column_values( $content, $column_name, $term_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		$count_format = '<div class="tax-term-count"><a href="edit.php?%1$s=%2$s&post_type=%3$s">%4$s</a></div>';
		$item_type = Item_Type::get_item_type_by_id( $term_id );

		switch ( $column_name ) {

			case 'fixer_station':
				if ( !empty( $item_type ) ) {
					$fixer_station = $item_type->get_fixer_station();
					if ( $fixer_station instanceof \Reg_Man_RC\Model\Fixer_Station ) {
						$name = $fixer_station->get_name();
						$val = "<span class=\"fixer_station_custom_col\">$name</span>";
					} else {
						$screen_reader_name = __( 'No Fixer Station', 'reg-man-rc' );
						$val = '<span aria-hidden="true" class="fixer_station_custom_col">—</span>';
						$val .= "<span class=\"screen-reader-text\">$screen_reader_name</span>";
					} // endif
					$content .= $val;
				} // endif
				break;

			case 'colour':
				if ( !empty( $item_type ) ) {
					$colour = $item_type->get_colour();
					if ( ! empty( $colour ) ) {
						$val = "<span class=\"reg-man-rc-colour-display\" style=\"background-color: $colour\">";
					} else {
						$screen_reader_name = __( 'No colour', 'reg-man-rc' );
						$val = '<span aria-hidden="true" class="reg_man_rc_colour_display">—</span>';
						$val .= "<span class=\"screen-reader-text\">$screen_reader_name</span>";
					} // endif
					$content .= $val;
				} // endif
				break;

			case 'ext_names':
				if ( ! empty( $item_type ) ) {
					$ext_names = $item_type->get_external_names();
					if ( ! empty( $ext_names ) && is_array( $ext_names ) ) {
						$names = implode( ' | ', $ext_names );
						$val = "<span class=\"external_names_custom_col\">$names</span>";
					} else {
						$screen_reader_name = __( 'No Alternate Names', 'reg-man-rc' );
						$val = '<span aria-hidden="true" class="external_names_custom_col">' . $em_dash . '</span>';
						$val .= "<span class=\"screen-reader-text\">$screen_reader_name</span>";
					} // endif
					$content .= $val;
				} // endif
				break;

			case 'item_count':
				if ( ! empty( $item_type ) ) {
					$count = $item_type->get_item_count();
					$taxonomy = Item_Type::TAXONOMY_NAME;
					$slug = $item_type->get_slug();
					$post_type = Item::POST_TYPE;
					$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
					$content .= $val;
				} // endif
				break;
/*
			case 'order_index':
				$order_index = Term_Order_Controller::get_term_order_index( $term_id );
				$content = ( ! empty( $order_index ) ) ? $order_index : $em_dash;
				break;
*/
		} // endswitch
		return $content;
	} // function

	/**
	 * Add my columns to the list of sortable columns.
	 * This is called during the manage_edit-TAXONOMY_sortable_columns filter hook.
	 * @param	string[]	$columns	The array of columns to be made sortable
	 * @return	string[]	$columns	The array of columns to be made sortable
	 */
/* FIXME - This is to show the sort order column and allow it to be sortable
	public static function add_sortable_columns( $columns ) {
		$columns[ 'order_index' ] = Term_Order_Controller::ORDER_INDEX_ORDER_BY;
		return $columns;
	} // function
*/
/**
	 * Modify the query for the terms so we can sort on my columns.
	 * This is called during the pre_get_terms action hook.
	 * @param	\WP_Term_Query	$query
	 * @return	\WP_Term_Query	$query
	 */
/*
	public static function modify_query_for_sort( $query ) {
		if ( is_admin() ) {
			global $pagenow;

			// order by the sort index column if nothing else specified
			$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : Term_Order_Controller::ORDER_INDEX_ORDER_BY; // Default to my sort order
			$order = isset( $_GET['order'] ) ? $_GET['order'] : 'ASC';
			$tax_array = $query->query_vars[ 'taxonomy' ];
			$taxonomy = isset( $tax_array[0] ) ? $tax_array[0] : '';

			if ( ( $pagenow == 'edit-tags.php' ) &&
				 ( $taxonomy == Item_Type::TAXONOMY_NAME ) && ( $orderby == Term_Order_Controller::ORDER_INDEX_ORDER_BY ) ) {

				// set orderby to the "order_clause" in the meta_query
				$query->query_vars['orderby'] = 'order_clause';
				$query->query_vars['order'] = $order;
				$meta_key = Item_Type::ORDER_INDEX_META_KEY;
				// the OR relation and the NOT EXISTS clause allow for terms without a meta_value at all
				$args = array(
						'relation' => 'OR',
						'order_clause' => array(
							'key'	=> $meta_key,
							'type'	=> 'NUMERIC'
						),
						array(
							'key'		=> $meta_key,
							'compare'	=> 'NOT EXISTS'
						)
				);
				$query->meta_query = new \WP_Meta_Query( $args );
			} // endif
		} // endif
		return $query;
	} // function
*/
} // class
