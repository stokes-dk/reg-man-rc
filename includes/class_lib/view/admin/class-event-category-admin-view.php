<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Control\Admin\Event_Category_Admin_Controller;
use Reg_Man_RC\View\Ajax_Form;

/**
 * The administrative view for event category
 *
 * @since	v0.1.0
 *
 */
class Event_Category_Admin_View {

	const INPUT_NAME = 'event_category';

	private function __construct() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Hide the slug field in the add form
		add_action( Event_Category::TAXONOMY_NAME . '_add_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );
		add_action( Event_Category::TAXONOMY_NAME . '_edit_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );

		// Render the input field in the admin UI for adding a new term using "_add_form_fields" hook
		add_action( Event_Category::TAXONOMY_NAME . '_add_form_fields', array( __CLASS__, 'render_add_term_admin_fields' ), 10, 1 );

		// Render the input field in the admin UI for updating them term using "_edit_form_fields" hook
		add_action( Event_Category::TAXONOMY_NAME . '_edit_form_fields', array( __CLASS__, 'render_edit_term_admin_fields' ), 10, 2 );

		// Modify columns in the admin UI term list
		add_filter( 'manage_edit-' . Event_Category::TAXONOMY_NAME . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put values into my columns in the term list
		add_filter( 'manage_' . Event_Category::TAXONOMY_NAME . '_custom_column', array( __CLASS__, 'get_admin_UI_column_values' ), 10, 3 );

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
				( ( $screen->base == 'edit-tags' ) || ( $screen->base == 'term' ) ) &&
				( $screen->taxonomy == Event_Category::TAXONOMY_NAME ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
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
	 * Render a metabox for the specified post.
	 * This function is assigned as the meta_box_cb (meta box callback) when the Event_Category custom taxonomy is registered.
	 * @param	\WP_Post	$post
	 */
	public function render_post_metabox( $post ) {
		switch ( $post->post_type ) {
			case Internal_Event_Descriptor::POST_TYPE:
				$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
				$this->render_metabox_for_event( $event );
				break;
			case Calendar::POST_TYPE:
				$calendar = Calendar::get_calendar_by_id( $post->ID );
				$this->render_metabox_for_calendar( $calendar );
				break;
		} // switch
	} // function

	/**
	 * Render the metabox for an internal event descriptor
	 * @param	Internal_Event_Descriptor	$event
	 */
	private function render_metabox_for_event( $event ) {

		// We need a flag to distinguish the case where no event categories were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="event_category_selection_flag" value="TRUE">';

		$default_event_category = Event_Category::get_default_event_category();

		$cat_obj_array = ( ! empty( $event ) ) ? $event->get_event_category_object_array() : array( $default_event_category );
		$selected_id_array = array();
		foreach ( $cat_obj_array as $category ) {
			$selected_id_array[] = $category->get_id();
		} // endfor
		$event_categories = Event_Category::get_all_event_categories();
		$input_name = esc_attr( self::INPUT_NAME );

		$is_multi_category_allowed = Settings::get_is_allow_event_multiple_categories();
		if ( $is_multi_category_allowed ) {
			self::render_checkboxes( $input_name, $event_categories, $selected_id_array );
		} else {
			self::render_radio_buttons( $input_name, $event_categories, $selected_id_array );
		} // endif

	} // function

	private function render_checkboxes( $input_name, $event_categories, $selected_id_array ) {
		$format =
			'<div><label title="%1$s">' .
				'<input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s autocomplete="off">' .
				'<span>%4$s</span>' .
			'</label></div>';
		foreach ( $event_categories as $category ) {
			$id = $category->get_id();
			$name = $category->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
	} // function

	private function render_radio_buttons( $input_name, $event_categories, $selected_id_array ) {
		$format =
			'<div><label title="%1$s">' .
				'<input type="radio" name="' . $input_name . '" value="%2$s" %3$s autocomplete="off">' .
				'<span>%4$s</span>' .
			'</label></div>';

		// Render a radio button for each category
		foreach ( $event_categories as $category ) {
			$id = $category->get_id();
			$name = $category->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
/* FIXME - there are no uncategorized events any more
		// Add uncategorized option
		$id = 0;
		$name = __( 'Uncategorized', 'reg-man-rc' );
		$html_name = esc_html( $name );
		$attr_name = esc_attr( $name );
		$checked = empty( $selected_id_array ) ? 'checked="checked"' : '';
		printf( $format, $attr_name, $id, $checked, $html_name );
*/
	} // function

	/**
	 * Render the categories metabox for a calendar custom post type
	 * @param	Calendar	$calendar	The calendar object whose metabox is being rendered
	 * @return	void
	 * @since	v0.1.0
	 */
	private function render_metabox_for_calendar( $calendar ) {

		// We need a flag to distinguish the case where no event categories were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="event_category_selection_flag" value="TRUE">';

		$default_event_category = Event_Category::get_default_event_category();

		$cat_obj_array = ! empty( $calendar ) ? $calendar->get_event_category_array() : array( $default_event_category );
		$selected_id_array = array();
		foreach ( $cat_obj_array as $category ) {
			$selected_id_array[] = $category->get_id();
		} // endfor
//		Error_Log::var_dump( $selected_id_array, $cat_obj_array );

//		$show_uncategorized = ( !empty( $calendar ) ) ? $calendar->get_show_uncategorized_events() : FALSE;

//		$any_selected = empty( $selected_id_array ) && ! $show_uncategorized;

		$event_categories = Event_Category::get_all_event_categories();

		// The category metabox for a calendar allows the user to chose which event categories will be shown in the calendar.
		// The choices are ANY or some subset.

		$format =
			'<div class="%6$s"><label>' .
				'<input type="%1$s" name="%5$s" value="%2$s" %3$s autocomplete="off">' .
				'<span>%4$s</span>' .
			'</label></div>';

			$type = 'checkbox';
			$input_name = esc_attr( self::INPUT_NAME ) . '[]';
			$class = 'calendar-event-category-selection calendar-event-category-checkbox';
			foreach ( $event_categories as $category ) {
				$value = $category->get_id();
				$name = $category->get_name();
				$label = esc_html( $name );
				$checked = in_array( $value, $selected_id_array ) ? 'checked="checked"' : '';
				printf( $format, $type, $value, $checked, $label, $input_name, $class );
			} // endfor

		$msg = __( 'Select the event categories to be included in this calendar', 'reg-man-rc' );
		echo '<p>' . $msg . '</p>';

	} // function

	/**
	 * Render the additional fields to display in the "Add New..." term input form
	 * @param	string	$taxonomy_slug	The slug for this taxonomy. Note that the hook includes the taxonomy name.
	 */
	public static function render_add_term_admin_fields( $taxonomy_slug ) {

		// Colour
		$input_id = 'event-category-colour-input';
		$input_name = 'event-category-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate events of this category on a calendar or map.', 'reg-man-rc' );
		echo '<div class="form-field">';

			echo "<label for=\"$input_id\">$label</label>";

			echo "<input type=\"color\" id=\"$input_id\" name=\"$input_name\" value=\"\">";

			echo "<p class=\"description\">$desc</p>";

		echo '</div>';

		// Calendars
		$calendar_array = Calendar::get_all_calendars();
		if ( ! empty( $calendar_array ) ) {
			$input_id = 'event-category-calendar';
			$input_name = 'event-category-calendar[]';
			$label = __( 'Calendars', 'reg-man-rc' );
			$desc = __( 'Select the calendars that should display events of this category.', 'reg-man-rc' );
			echo '<div class="form-field">';

				echo "<label for=\"$input_id\">$label</label>";

				self::render_calendar_select_input( $input_name, array() );

				echo "<p class=\"description\">$desc</p>";
			echo '</div>';
		} // endif

	} // function

	private static function render_calendar_select_input( $input_name, $selected_id_array ) {
		$format =
			'<div><label title="%1$s">' .
				'<input type="checkbox" name="' . $input_name . '" value="%2$s" %3$s autocomplete="off">' .
				'<span>%4$s</span>' .
			'</label></div>';
		$calendar_array = Calendar::get_all_calendars();

		echo '<input type="hidden" name="event-category-calendar-selection-flag" value="TRUE">';

		foreach( $calendar_array as $calendar ) {
			$name = $calendar->get_name();
			$id = esc_attr( $calendar->get_id() );
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
	} // function

	/**
	 * Render the additional fields to display in the "Edit..." term input form
	 * @param	\WP_Term	$term	The term being edited
	 * @param	string		$taxonomy_slug	The slug for the current taxonomy
	 */
	public static function render_edit_term_admin_fields( $term, $taxonomy_slug ) {

		$event_category = Event_Category::get_event_category_by_id( $term->term_id );

		if ( ! empty( $event_category ) ) {
			// Colour
			$input_id = 'event-category-colour-input';
			$input_name = 'event-category-colour';
			$label = __( 'Colour', 'reg-man-rc' );
			$desc = __( 'The colour used to indicate events of this category on a calendar or map.', 'reg-man-rc' );
			$colour = ! empty( $event_category ) ? $event_category->get_colour() : '';
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

			// Calendar
			$input_id = 'event-category-calendars-input';
			$label = __( 'Calendars', 'reg-man-rc' );
			$desc = __( 'To modify the calendars that display this event category, edit the calendars directly.', 'reg-man-rc' );
			$admin_url = add_query_arg(
					array(
						'post_type' => Calendar::POST_TYPE
					),
					admin_url( 'edit.php' )
			);
			$link_text = esc_html__( 'Edit calendars', 'reg-man-rc' );
			$link_format = '<a href="%2$s" target="_blank">%1$s</a>';
			echo '<tr class="form-field term-group-wrap">';
				echo '<th scope="row">';
					echo "<label for=\"$input_id\">$label</label>";
				echo '</th>';
				echo '<td>';
					printf( $link_format, $link_text, $admin_url );
					echo "<p class=\"description\">$desc</p>";
				echo '</td>';
			echo '</tr>';

			if ( Settings::get_is_show_external_names() ) {
				// External names
				$input_id = 'event-category-ext-names-input';
				$input_name = 'event-category-ext-names';
				$label = __( 'Alternate Names', 'reg-man-rc' );
				$desc = __( 'Enter a pipe-separated list of alternate names for this event category, e.g. "Name 1 | Name 2".', 'reg-man-rc' );
				$ext_names = ! empty( $event_category ) ? $event_category->get_external_names() : '';
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
			} // endif
		} // endif
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
				'colour'					=> __( 'Colour', 'reg-man-rc' ),
				'calendar_names'			=> __( 'Calendars', 'reg-man-rc' ),
				'ext_names'					=> __( 'Alternate Names', 'reg-man-rc' ),
				'event_count'				=> __( 'Event Count', 'reg-man-rc' ),
//				'calendar_count'			=> __( 'Calendar Count', 'reg-man-rc' ),
//				'posts'						=> __( 'Event Count', 'reg-man-rc' ), // This goes to Posts page
//				'slug'						=> $columns['slug'],
			);
			if ( ! Settings::get_is_show_external_names() ) {
				unset( $result[ 'ext_names' ] );
			} // endif
		} // endif
		return $result;
	} // function

	public static function get_admin_UI_column_values( $content, $column_name, $term_id ) {
		$event_category = Event_Category::get_event_category_by_id( $term_id );
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
		switch ( $column_name ) {

			case 'colour':
				if ( ! empty( $event_category ) ) {
					$colour = $event_category->get_colour();
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

			case 'calendar_names':
				if ( ! empty( $event_category ) ) {
					$calendar_array = $event_category->get_calendar_array();
					if ( ! empty( $calendar_array ) ) {
						$name_array = array();
						$glue = _x( ', ', 'A separator used to concatenate a list', 'reg-man-rc' );
						foreach( $calendar_array as $calendar ) {
							$name_array[] = $calendar->get_name();
						} // endfor
						$content = ! empty( $name_array ) ? implode( $glue, $name_array ) : $em_dash;
					} else {
						$content = $em_dash;
					} // endif
				} // endif
				break;

			case 'ext_names':
				if ( ! empty( $event_category ) ) {
					$ext_names = $event_category->get_external_names();
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

			case 'event_count':
				$count_format = '<div class="tax-term-count"><a href="edit.php?%1$s=%2$s&post_type=%3$s">%4$s</a></div>';
				$count = $event_category->get_internal_event_descriptor_count();
				$taxonomy = Event_Category::TAXONOMY_NAME;
				$slug = $event_category->get_slug();
				$post_type = Internal_Event_Descriptor::POST_TYPE;
				$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
				$content .= $val;
				break;

			case 'calendar_count':
				$count_format = '<div class="tax-term-count"><a href="edit.php?%1$s=%2$s&post_type=%3$s">%4$s</a></div>';
				$count = $event_category->get_calendar_count();
				$taxonomy = Event_Category::TAXONOMY_NAME;
				$slug = $event_category->get_slug();
				$post_type = Calendar::POST_TYPE;
				$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
				$content .= $val;
				break;

		} // endswitch
		return $content;
	} // function


} // class
