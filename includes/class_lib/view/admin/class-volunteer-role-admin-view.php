<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Admin\Volunteer_Role_Admin_Controller;

/**
 * The administrative view for fixer station
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Role_Admin_View {

	const INPUT_NAME = 'volunteer_role';
	const INPUT_ID = 'fixer-station-input';

	private function __construct() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

		// Show my admin notice to create the defaults when necessary
		if ( self::get_is_show_create_defaults_admin_notice() ) {
			add_action( 'admin_notices', array( __CLASS__, 'handle_admin_notices' ) );
		} // endif

		// Hide the slug field in the add form
		add_action( Volunteer_Role::TAXONOMY_NAME . '_add_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );
		add_action( Volunteer_Role::TAXONOMY_NAME . '_edit_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );

		// Render the input field in the admin UI for adding a new term using "_add_form_fields" hook
		add_action( Volunteer_Role::TAXONOMY_NAME . '_add_form_fields', array( __CLASS__, 'render_add_term_admin_fields' ), 10, 1 );

		// Render the input field in the admin UI for updating them term using "_edit_form_fields" hook
		add_action( Volunteer_Role::TAXONOMY_NAME . '_edit_form_fields', array( __CLASS__, 'render_edit_term_admin_fields' ), 10, 2 );

		// Add a column to the admin UI term list
		add_filter('manage_edit-' . Volunteer_Role::TAXONOMY_NAME . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into the postal abbrev column in the term list
		add_filter('manage_' . Volunteer_Role::TAXONOMY_NAME . '_custom_column', array( __CLASS__, 'filter_admin_UI_column_values' ), 10, 3 );

	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) && ( $screen->taxonomy == Volunteer_Role::TAXONOMY_NAME ) ) {
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
			if ( $tax_name === Volunteer_Role::TAXONOMY_NAME ) {
				if ( self::get_is_show_create_defaults_admin_notice() ) {
					self::render_no_roles_admin_notice();
				} // endif
			} // endif
		} // endif
	} // function

	/**
	 * Render admin notice for no roles
	 */
	private static function render_no_roles_admin_notice() {
		echo '<div class="notice notice-warning">';
			$heading = __( 'No event categories are defined', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			self::render_create_defaults_form();
		echo '</div>';
	} // function



	/**
	 * Render the metabox for the specified post
	 * @param \WP_Post $post
	 */
	public function render_post_metabox( $post ) {
		if ( $post->post_type === Volunteer::POST_TYPE ) {

			$volunteer = Volunteer::get_volunteer_by_id( $post->ID );
			$selected_roles = ( ! empty( $volunteer ) ) ? $volunteer->get_preferred_roles() : array();
			self::render_checkboxes( $selected_roles );

		} elseif ( $post->post_type === Volunteer_Registration::POST_TYPE ) {

			$reg = Volunteer_Registration::get_registration_by_id( $post->ID );
			$selected_roles = ( ! empty( $reg ) ) ? $reg->get_volunteer_roles_array() : array();
			self::render_checkboxes( $selected_roles );

		} // endif
	} // function


	private function render_checkboxes( $selected_roles ) {
		$all_roles = Volunteer_Role::get_all_volunteer_roles();
		$input_name = esc_attr( self::INPUT_NAME );

		$selected_id_array = array(); // an array of the ids for the selected roles to make checking easier
		foreach ( $selected_roles as $role ) {
			$selected_id_array[] = $role->get_id();
		} // endfor

		// We need a flag to distinguish the case where no roles were chosen by the user
		//  versus the case where no checkboxes were presented at all like in quick edit mode
		echo '<input type="hidden" name="volunteer_role_selection_flag" value="TRUE">';

		$format = '<div><label title="%1$s"><input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s><span>%4$s</span></label></div>';
		foreach ( $all_roles as $role ) {
			$id = $role->get_id();
			$name = $role->get_name();
			$html_name = esc_html( $name );
			$attr_name = esc_attr( $name );
			$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $attr_name, $id, $checked, $html_name );
		} // endfor
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
	 * Return a flag to indicate whether to show an admin notice for this taxonomy if it's empty
	 * @return boolean	TRUE if an admin notice should be displayed, FALSE otherwise
	 */
	public static function get_is_show_create_defaults_admin_notice() {
		// We need to do this earlier without initializing the cache so just get the terms here
		$args = array(
				'taxonomy'		=> Volunteer_Role::TAXONOMY_NAME,
				'hide_empty'	=> FALSE, // We want all, including those used by no post
		);
		$term_array = get_terms( $args );
		$result = ( empty( $term_array ) && ( ! Settings::get_is_object_type_init_skipped( Volunteer_Role::TAXONOMY_NAME ) ) );
		return $result;
	} // function


	/**
	 * Render the form for creating the default event categories
	 */
	private static function render_create_defaults_form() {

		$default_array = Volunteer_Role::get_default_volunteer_roles_data();

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
				$label = __( 'The following are typical volunteer roles you may choose to create to help get you started quickly', 'reg-man-rc' );
				echo '<b>' . esc_html( $label ) . '</b>';
			echo '</p>';

			$method = 'POST';
			$form_action = esc_url( admin_url( 'admin-post.php' ) );
			$admin_post_action = Volunteer_Role_Admin_Controller::CREATE_DEFAULTS_FORM_ACTION;
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
					$colour_format = '<span class="reg-man-rc-colour-display" style="background-color: %1$s"></span>';
					$index = 0;
					foreach ( $default_array as $category ) {
						$name = esc_html( $category[ 'name' ] );
						$desc = esc_html( $category[ 'description' ] );
						$colour = $category[ 'colour' ];
//						$colour_swatch = ! empty( $colour ) ? sprintf( $colour_format, $colour ) : '';
						printf( $row_format, $index, $name, $desc, $colour );
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



	public static function render_add_term_admin_fields( $taxonomy_slug ) {
		$input_id = 'volunteer-role-colour-input';
		$input_name = 'volunteer-role-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this volunteer role on a chart or graph', 'reg-man-rc' );
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

		$volunteer_role = Volunteer_Role::get_volunteer_role_by_id( $term->term_id );

		$input_id = 'volunteer-role-colour-input';
		$input_name = 'volunteer-role-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this volunteer role on a chart or graph', 'reg-man-rc' );
		$colour = ! empty( $volunteer_role ) ? $volunteer_role->get_colour() : '';
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

		$input_id = 'volunteer-role-ext-names-input';
		$input_name = 'volunteer-role-ext-names';
		$label = __( 'Alternate Names', 'reg-man-rc' );
		$desc = __( 'Enter a pipe-separated list of alternate names for this volunteer role, e.g. "Name 1 | Name 2".', 'reg-man-rc' );
		$ext_names = ! empty( $volunteer_role ) ? $volunteer_role->get_external_names() : '';
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
				'colour'					=> __( 'Colour', 'reg-man-rc' ),
				'ext_names'					=> __( 'Alternate Names', 'reg-man-rc' ),
				'volunteer_count'			=> __( 'Volunteer Count', 'reg-man-rc' ),
//				'slug'						=> $columns['slug'],
			);
			if ( ! Settings::get_is_show_external_names() ) {
				unset( $result[ 'ext_names' ] );
			} // endif
		} // endif
		return $result;
	} // function

	public static function filter_admin_UI_column_values( $content, $column_name, $term_id ) {
		$em_dash = __( '—', 'reg-man-rc' ); // an em-dash is used by Wordpress for empty fields
//		$count_format = '<div class="tax-term-count"><a href="edit.php?taxonomy=%1$s&term=%2$s&post_type=%3$s">%4$s</a></div>';
		$count_format = '<div class="tax-term-count"><a href="edit.php?%1$s=%2$s&post_type=%3$s">%4$s</a></div>';
		$volunteer_role = Volunteer_Role::get_volunteer_role_by_id( $term_id );
		switch ( $column_name ) {
			case 'colour':
				if ( ! empty( $volunteer_role ) ) {
					$colour = $volunteer_role->get_colour();
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
				if ( ! empty( $volunteer_role ) ) {
					$ext_names = $volunteer_role->get_external_names();
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
			case 'volunteer_count':
				$count = $volunteer_role->get_volunteer_count();
				$taxonomy = Volunteer_Role::TAXONOMY_NAME;
				$slug = $volunteer_role->get_slug();
				$post_type = Volunteer::POST_TYPE;
				$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
				$content .= $val;
				break;
		} // endswitch
		return $content;
	} // function

} // class
