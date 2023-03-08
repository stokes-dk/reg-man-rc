<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Admin\Event_Category_Admin_Controller;
use Reg_Man_RC\Control\Admin\Fixer_Station_Admin_Controller;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The administrative view for fixer station
 *
 * @since	v0.1.0
 *
 */
class Fixer_Station_Admin_View {

	const INPUT_NAME = 'fixer_station';
	const INPUT_ID = 'fixer-station-input';

	private function __construct() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public static function register() {

		// Hide the slug field in the add form
		add_action( Fixer_Station::TAXONOMY_NAME . '_add_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );
		add_action( Fixer_Station::TAXONOMY_NAME . '_edit_form', array( __CLASS__, 'hide_slug_form_field' ), 10, 1 );

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

		// Show my admin notice to create the defaults when necessary
		if ( self::get_is_show_create_defaults_admin_notice() ) {
			add_action( 'admin_notices', array( __CLASS__, 'handle_admin_notices' ) );
		} // endif

		// Render the input field in the admin UI for adding a new term using "_add_form_fields" hook
		add_action( Fixer_Station::TAXONOMY_NAME . '_add_form_fields', array( __CLASS__, 'render_add_term_admin_fields' ), 10, 1 );

		// Render the input field in the admin UI for updating them term using "_edit_form_fields" hook
		add_action( Fixer_Station::TAXONOMY_NAME . '_edit_form_fields', array( __CLASS__, 'render_edit_term_admin_fields' ), 10, 2 );

		// Add my columns to the admin UI term list
		add_filter( 'manage_edit-' . Fixer_Station::TAXONOMY_NAME . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );

		// Put the values into my columns in the term list
		add_filter( 'manage_' . Fixer_Station::TAXONOMY_NAME . '_custom_column', array( __CLASS__, 'filter_admin_UI_column_values' ), 10, 3 );

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
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) && ( $screen->taxonomy == Fixer_Station::TAXONOMY_NAME ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			// I need to allow the user to select an image from the media library
			// To enable that I will enqueue the Wordpress media scripts, styles etc.
			wp_enqueue_media();
		} // endif
	} // function

	/**
	 * Handle the admin notices action hook
	 */
	public static function handle_admin_notices() {
		global $pagenow;
		if ( $pagenow === 'edit-tags.php' ) {
			$tax_name = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : '';
			if ( $tax_name === Fixer_Station::TAXONOMY_NAME ) {
				if ( self::get_is_show_create_defaults_admin_notice() ) {
					self::render_no_stations_admin_notice();
				} // endif
			} // endif
		} // endif
	} // function

	/**
	 * Render admin notice for no categories
	 */
	private static function render_no_stations_admin_notice() {
		echo '<div class="notice notice-warning">';
			$heading = __( 'No fixer stations are defined', 'reg-man-rc' );
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
				'taxonomy'		=> Fixer_Station::TAXONOMY_NAME,
				'hide_empty'	=> FALSE, // We want all, including those used by no post
		);
		$term_array = get_terms( $args );
		$result = ( empty( $term_array ) && ( ! Settings::get_is_object_type_init_skipped( Fixer_Station::TAXONOMY_NAME ) ) );
		return $result;
	} // function

	/**
	 * Render the form for creating the default event categories
	 */
	private static function render_create_defaults_form() {

		$default_array = Fixer_Station::get_default_fixer_station_data();

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
					'<td class="">' .
						'%4$s' .
					'</td>' .
					'<td class="colour-input">' .
						'<input type="color" autocomplete="off" name="colour[%1$s]" value="%5$s">' .
					'</td>' .
				'</tr>';

			echo '<p>';
				$label = __( 'The following are typical fixer stations you may choose to create to help get you started quickly', 'reg-man-rc' );
				echo '<b>' . esc_html( $label ) . '</b>';
			echo '</p>';

			$method = 'POST';
			$form_action = esc_url( admin_url( 'admin-post.php' ) );
			$admin_post_action = Fixer_Station_Admin_Controller::CREATE_DEFAULTS_FORM_ACTION;
			$classes = 'reg-man-rc-admin-create-defaults-form reg-man-rc-admin-notice-form';

			echo "<form class=\"$classes\" method=\"$method\" action=\"$form_action\">";
				echo "<input type=\"hidden\" name=\"action\" value=\"$admin_post_action\">"; // required for admin-post
				echo '<table class="reg-man-rc-admin-create-defaults-table">';
					echo '<tr>';
						echo '<th>' . esc_html( __( 'Create Selected', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Name', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Description', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Icon', 'reg-man-rc' ) ) . '</th>';
						echo '<th>' . esc_html( __( 'Colour Code', 'reg-man-rc' ) ) . '</th>';
					echo '</tr>';
//					$colour_format = '<span class="reg-man-rc-colour-display" style="background-color: %1$s"></span>';
					$index = 0;
					foreach ( $default_array as $default ) {
						$name = esc_html( $default[ 'name' ] );
						$desc = esc_html( $default[ 'description' ] );
						$colour = $default[ 'colour' ];
						$icon_input_name = "icon_id[$index]";
						$icon_attach_id = $default[ 'icon_id' ];
						$icon_url = isset( $icon_attach_id ) ? wp_get_attachment_image_url( $icon_attach_id ) : NULL;
						ob_start();
							self::render_icon_input_field( $icon_input_name, $icon_attach_id, $icon_url );
						$icon_col = ob_get_clean();
//						$icon_col = ''; // until we find the right attachment etc.
//						if ( isset( $icon_attach_id ) ) {
//							$icon_url = wp_get_attachment_image_url( $icon_attach_id );
//							$icon_col = "<span class=\"reg-man-rc-fixer-station-term-list-icon\"><img src=\"$icon_url\"></span>";
//							$icon_col .= "<input type=\"hidden\" name=\"icon_id[$index]\" value=\"$icon_attach_id\">";
//						} // endif
						printf( $row_format, $index, $name, $desc, $icon_col, $colour );
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
	 * @param \WP_Post $post
	 */
	public function render_post_metabox( $post ) {
		switch( $post->post_type ) {

			case Item::POST_TYPE:
				$item = Item::get_item_by_id( $post->ID );
				$fixer_station = ( ! empty( $item ) ) ? $item->get_fixer_station() : NULL;
				$selected = ( ! empty( $fixer_station ) ) ? $fixer_station->get_id() : NULL;
				self::render_input_for_item( $selected );
				break;

			case Item_Suggestion::POST_TYPE:
				$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post->ID );
				$fixer_station = ( ! empty( $item_suggestion ) ) ? $item_suggestion->get_fixer_station() : NULL;
				$selected = ( ! empty( $fixer_station ) ) ? $fixer_station->get_id() : NULL;
				self::render_input_for_item_suggestion( $selected );
				break;

			case Internal_Event_Descriptor::POST_TYPE:
				$event = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
				$selected_id_array = array();
				$stations = isset( $event ) ? $event->get_event_fixer_station_array() : Fixer_Station::get_all_fixer_stations();
				foreach ( $stations as $station ) {
					$selected_id_array[] = $station->get_id();
				} // endfor
				$is_non_repair_event = $event->get_event_is_non_repair();
				self::render_checkboxes_for_event( $selected_id_array, $is_non_repair_event );
				break;

			case Volunteer::POST_TYPE:
				$volunteer = Volunteer::get_volunteer_by_id( $post->ID );
				$fixer_station = ( ! empty( $volunteer ) ) ? $volunteer->get_preferred_fixer_station() : NULL;
				$selected = ( !empty( $fixer_station ) ) ? $fixer_station->get_id() : NULL;
				$is_apprentice = ( ! empty( $volunteer ) ) ? $volunteer->get_is_fixer_apprentice() : FALSE;
				self::render_input_for_volunteer( $selected, $is_apprentice );
				break;

			case Volunteer_Registration::POST_TYPE:
				$reg = Volunteer_Registration::get_registration_by_id( $post->ID );
				$fixer_station = ( ! empty( $reg ) ) ? $reg->get_fixer_station() : NULL;
				$selected = ( !empty( $fixer_station ) ) ? $fixer_station->get_id() : NULL;
				$is_apprentice = ( ! empty( $reg ) ) ? $reg->get_is_fixer_apprentice() : FALSE;
				self::render_input_for_volunteer_registration( $selected, $is_apprentice );
				break;

		} // endswitch
	} // function

	public function render_input_for_item_type( $selected_id ) {
		$none_label = __( 'None', 'reg-man-rc' );
		$none_value = '0';
		$this->render_radio_buttons( $selected_id, $none_label, $none_value );
	} // function

	private function render_input_for_item( $selected_id ) {
		echo '<div class="reg-man-rc-item fixer-station-metabox reg-man-rc-metabox">';
			$default_name = __( '-- Please Select --', 'reg-man-rc' );
			$default_value = '-1';
			$this->render_select( $selected_id, $default_name, $default_value, $is_required = TRUE );
		echo '</div>';
	} // function

	private function render_input_for_item_suggestion( $selected_id ) {
		echo '<div class="reg-man-rc-item-suggestion fixer-station-metabox reg-man-rc-metabox">';
			$default_name = __( '-- Please Select --', 'reg-man-rc' );
			$default_value = '-1';
			$this->render_select( $selected_id, $default_name, $default_value, $is_required = TRUE );
		echo '</div>';
	} // function

	private function render_input_for_volunteer( $selected_id, $is_apprentice ) {
		// I will use the same inputs here as for a volunteer event registration
		$this->render_input_for_volunteer_registration( $selected_id, $is_apprentice );
	} // function

	private function render_input_for_volunteer_registration( $selected_id, $is_apprentice ) {
		$none_label = __( 'None (will not fix items at this event)', 'reg-man-rc' );
		$none_value = '0';
		$this->render_radio_buttons( $selected_id, $none_label, $none_value );
		$id = 'reg-man-rc-vol-reg-is-apprentice';
		$text = __( 'Apprentice Fixer', 'reg-man-rc' );
		$checked = $is_apprentice ? 'checked="checked"' : '';
		echo "<div class=\"$id\">"; // I will re-use the same ID as the class for this group
			echo "<input type=\"checkbox\" id=\"$id\" name=\"is_apprentice\" value=\"TRUE\" $checked>";
			echo "<label for=\"$id\">$text</label>";
		echo '</div>';
	} // function

	private function render_select( $selected_id, $default_name = NULL, $default_value = NULL, $is_required = FALSE ) {

		$stations = Fixer_Station::get_all_fixer_stations();
		if ( empty( $stations ) ) {
			$text = __( 'No fixer stations are defined', 'reg-man-rc' );
			echo "<span>$text</span>";

		} else {

			// We need a flag to distinguish the case where no fixer stations were chosen by the user
			//  versus the case where no inputs were presented at all like in quick edit mode
			echo '<input type="hidden" name="fixer_station_selection_flag" value="TRUE">';

			$input_name = esc_attr( self::INPUT_NAME );
			$input_id = esc_attr( self::INPUT_ID );
			$required = $is_required ? 'required="required"' : '';
			echo "<select id=\"$input_id\" name=\"$input_name\" $required>";
				if ( $default_name !== NULL ) {
					$selected = selected( NULL, $selected_id, $echo = FALSE );
					$html_name = esc_html( $default_name );
					$disabled = $is_required ? 'disabled="disabled"' : '';
					echo "<option value=\"$default_value\" $disabled $selected>$html_name</option>";
				} // endif
				foreach ( $stations as $fixer_station ) {
					$id = $fixer_station->get_id();
					$name = $fixer_station->get_name();
					$html_name = esc_html( $name );
					$selected = selected( $id, $selected_id, $echo = FALSE );
					echo "<option value=\"$id\" $selected>$html_name</option>";
				} // endfor
			echo '</select>';
		} // endif
	} // function

	private function render_radio_buttons( $selected_id, $default_name = NULL, $default_value = NULL  ) {

		$stations = Fixer_Station::get_all_fixer_stations();
		if ( empty( $stations ) ) {
			$text = __( 'No fixer stations are defined', 'reg-man-rc' );
			echo "<span>$text</span>";

		} else {

			// We need a flag to distinguish the case where no fixer stations were chosen by the user
			//  versus the case where no inputs were presented at all like in quick edit mode
			echo '<input type="hidden" name="fixer_station_selection_flag" value="TRUE">';

			$input_name = esc_attr( self::INPUT_NAME );
			$format = '<div><label title="%1$s"><input type="radio" name="' . $input_name . '" value="%2$s" %3$s><span>%4$s</span></label></div>';
			if ( $default_name !== NULL ) {
				$html_name = esc_html( $default_name );
				$attr_name = esc_attr( $default_name );
				$checked = checked( NULL, $selected_id, $echo = FALSE );
				printf( $format, $attr_name, $default_value, $checked, $html_name );
			} // endif
			foreach ( $stations as $fixer_station ) {
				$id = $fixer_station->get_id();
				$name = $fixer_station->get_name();
				$html_name = esc_html( $name );
				$attr_name = esc_attr( $name );
				$checked = checked( $id, $selected_id, $echo = FALSE );
				printf( $format, $attr_name, $id, $checked, $html_name );
			} // endfor
		} // endif
	} // function

	private function render_checkboxes_for_event( $selected_id_array, $is_non_repair ) {

		echo '<div class="reg-man-rc-event-fixer-station-metabox reg-man-rc-metabox">';

			$input_name = esc_attr( self::INPUT_NAME );
			$stations = Fixer_Station::get_all_fixer_stations();
			if ( empty( $stations ) ) {
				$text = __( 'No fixer stations are defined', 'reg-man-rc' );
				echo "<span>$text</span>";

			} else {
				$input_list = Form_Input_List::create();

				// We need a flag to distinguish the case where no fixer stations were chosen by the user
				//  versus the case where no inputs were presented at all like in quick edit mode
				$name = 'fixer_station_selection_flag';
				$value = 'TRUE';
				$input_list->add_hidden_input( $name, $value );

				foreach ( $stations as $fixer_station ) {
					$label = $fixer_station->get_name();
					$name = self::INPUT_NAME . '[]';
					$value = $fixer_station->get_id();
					$is_checked = in_array( $value, $selected_id_array );
					$hint = '';
					$classes = '';
					$is_required = FALSE;
					$addn_attrs = $is_non_repair ? 'disabled="disabled"' : '';
					$input_list->add_checkbox_input( $label, $name, $value, $is_checked, $hint, $classes, $is_required, $addn_attrs );
				} // endfor

				// Add a checkbox to indicate that it's a non-repair event
				$label = __( 'Non-repair event', 'reg-man-rc' );
				$name = 'is_non_repair_event';
				$value = 'TRUE';
				$hint = __( 'Items will not be repaired at this event', 'reg-man-rc' );
				$classes = 'reg-man-rc-non-repair-event-input';
				$input_list->add_checkbox_input( $label, $name, $value, $is_non_repair, $hint, $classes );

				$input_list->render();
			} // endif

		echo '</div>';
	} // function

	private function render_checkboxes_for_event_old( $selected_id_array, $is_non_repair ) {

		$input_name = esc_attr( self::INPUT_NAME );
		$stations = Fixer_Station::get_all_fixer_stations();
		if ( empty( $stations ) ) {
			$text = __( 'No fixer stations are defined', 'reg-man-rc' );
			echo "<span>$text</span>";

		} else {

			// We need a flag to distinguish the case where no fixer stations were chosen by the user
			//  versus the case where no inputs were presented at all like in quick edit mode
			echo '<input type="hidden" name="fixer_station_selection_flag" value="TRUE">';
			$format = '<div><label title="%1$s"><input type="checkbox" name="' . $input_name . '[]" value="%2$s" %3$s><span>%4$s</span></label></div>';
			foreach ( $stations as $fixer_station ) {
				$id = $fixer_station->get_id();
				$name = $fixer_station->get_name();
				$html_name = esc_html( $name );
				$attr_name = esc_attr( $name );
				$checked = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
				printf( $format, $attr_name, $id, $checked, $html_name );
			} // endfor

			$id = 'is-non-repair-event-id';
			$text = __( 'Non-repair event', 'reg-man-rc' );
			$checked = $is_non_repair ? 'checked="checked"' : '';
			echo "<div class=\"$id\">"; // I will re-use the same ID as the class for this group
				echo "<input type=\"checkbox\" id=\"$id\" name=\"is_non_repair\" value=\"TRUE\" $checked>";
				echo "<label for=\"$id\">$text</label>";
			echo '</div>';

		} // endif
	} // function

	/**
	 * Render the icon input field for the fixer station
	 * @param	Fixer_Station		$fixer_station	The fixer station whose input field is being rendered or NULL if no station.
	 */
	private static function render_icon_input_field( $input_name, $attach_id = NULL, $img_url = NULL ) {
		echo '<div class="media-library-select-container fixer-station-icon">';

			$img = isset( $img_url ) ? "<img src=\"$img_url\">" : '';
			echo "<div class=\"media-library-img-container\">$img</div>";

			$value = isset( $attach_id ) ? $attach_id : '';

			$class = 'media-library-attachment-id'; // needed in js to find the input
			echo "<input type=\"hidden\" class=\"$class\" name=\"$input_name\" value=\"$value\">";

			$button_format = '<button type="button" name="%2$s" id="%3$s" class="%4$s">%1$s</button>';

			$button_label = __( 'Select…', 'reg-man-rc' );
			$input_id = 'fixer-station-icon-select';
			$input_name = 'fixer-station-icon-select';
			$classes = 'media-library-launch media-library-select button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

			$button_label = __( 'Change…', 'reg-man-rc' );
			$input_id = 'fixer-station-icon-change';
			$input_name = 'fixer-station-icon-change';
			$classes = 'media-library-launch media-library-change button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

			$button_label = __( 'Remove', 'reg-man-rc' );
			$input_id = 'fixer-station-icon-remove';
			$input_name = 'fixer-station-icon-remove';
			$classes = 'media-library-remove button'; // "button" is for Wordpress styling
			printf( $button_format, $button_label, $input_name, $input_id, $classes );

		echo '</div>';
	} // function

	public static function render_add_term_admin_fields( $taxonomy_slug ) {
		// Note that I cannot add "Item Types" input to the add term form for Fixer Station
		//  because when the term is added the whole table would need to be refreshed
		//  for example if an Item type is moved from one fixer station to another.
		//  Wordpress uses Ajax and only inserts the new row into the table leaving the remaining data incorrect

		$label = __( 'Icon', 'reg-man-rc' );
		$desc = __( 'Select the icon used to represent this fixer station.', 'reg-man-rc' );
		echo '<div class="form-field">';
			echo "<label for=\"\">";
				echo $label;
			echo '</label>';
			// To distinguish quick edit or user removing the attachment ID I need to
			//  pass a hidden marker to flag that the icon should be modified
			echo '<input type="hidden" name="fixer-station-icon-selection" value="TRUE">';
			$input_name = 'media-library-attachment-id';
			self::render_icon_input_field( $input_name );
			echo '<p class="description">';
				echo $desc;
			echo '</p>';
		echo '</div>';

		$input_id = 'fixer-station-colour-input';
		$input_name = 'fixer-station-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this fixer station on a chart or graph', 'reg-man-rc' );
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

		$fixer_station = Fixer_Station::get_fixer_station_by_id( $term->term_id );

		// Icon
		$label = __( 'Icon', 'reg-man-rc' );
		$desc = __( 'Select the icon used to represent this fixer station.', 'reg-man-rc' );
		echo '<tr class="form-field term-group-wrap">';
			echo '<th scope="row">';
				echo "<label for=\"\">";
					echo $label;
				echo '</label>';
			echo '</th>';
			echo '<td>';

			// To distinguish quick edit or user removing the attachment ID I need to
			//  pass a hidden marker to flag that the icon should be modified
			echo '<input type="hidden" name="fixer-station-icon-selection" value="TRUE">';
			$attach_id = isset( $fixer_station ) ? $fixer_station->get_icon_attachment_id() : NULL;
			$img_url = isset( $fixer_station ) ? $fixer_station->get_icon_url() : NULL;
			$input_name = 'media-library-attachment-id';
			self::render_icon_input_field( $input_name, $attach_id, $img_url );

			echo '<p class="description">';
					echo $desc;
				echo '</p>';
			echo '</td>';
		echo '</tr>';

		// Colour
		$input_id = 'fixer-station-colour-input';
		$input_name = 'fixer-station-colour';
		$label = __( 'Colour', 'reg-man-rc' );
		$desc = __( 'The colour used to indicate this fixer station on a chart or graph', 'reg-man-rc' );
		$colour = ! empty( $fixer_station ) ? $fixer_station->get_colour() : '';
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

		// Item Types
/* FIXME - I don't think we need this
		$types = Item_Type::get_all_item_types();
		// If there are no item types to choose from then don't display this input
		if ( !empty( $types ) ) {
			$selected_item_types = !empty( $fixer_station ) ? $fixer_station->get_item_types_array() : NULL;
			$selected_id_array = array();
			foreach ( $selected_item_types as $item_type ) {
				$selected_id_array[] = $item_type->get_id();
			} // endfor
			$label = __( 'Item Types', 'reg-man-rc' );
			$legend = __( 'Select item types for this fixer station', 'reg-man-rc' );
			$desc = __( 'Make this the default fixer station for items of these types.', 'reg-man-rc' );
			echo '<tr class="form-field term-group-wrap">';
				echo '<th scope="row">';
					echo '<label for="item-types-input">';
						echo $label;
					echo '</label>';
				echo '</th>';
				echo '<td>';
					// I can't update the item types in the quick editor so I have to add a special marker
					// here to indicate that the item types should be updated during normal editing.
					// This will be absent during quick editing so in that case the item types won't be modified
					echo '<input type="hidden" name="update_item_types" value="TRUE">';
					echo '<fieldset class="reg-man-rc-admin-fieldset" id="item-types-input">';
						echo "<legend>$legend</legend>";
						self::render_item_types_select( $types, $selected_id_array );
					echo '</fieldset>';
					echo '<p class="description">';
						echo $desc;
					echo '</p>';
				echo '</td>';
			echo '</div>';
		} // endif
*/
		// External names
		if ( Settings::get_is_show_external_names() ) {
			$input_id = 'fixer-station-ext-names-input';
			$input_name = 'fixer-station-ext-names';
			$label = __( 'Alternate Names', 'reg-man-rc' );
			$desc = __( 'Enter a pipe-separated list of alternate names for this fixer station, e.g. "Name 1 | Name 2".', 'reg-man-rc' );
			$ext_names = ! empty( $fixer_station ) ? $fixer_station->get_external_names() : '';
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

	} // function

	private static function render_item_types_select( $types, $selected_id_array ) {
		$format  = '<div class="item-type-checkbox-container">';
		$format .=		'<label class="item-type-checkbox-label">';
		$format .=			'<input type="checkbox" value="%2$s" name="item_types[]" %3$s>';
		$format .= 			'<span>%1$s</span>';
		$format .= 		'</label>';
		$format .= '</div>';
		foreach ( $types as $item_type ) {
			$id = $item_type->get_id();
			$name = $item_type->get_name();
			$html_name = esc_html( $name );
			$selected = in_array( $id, $selected_id_array ) ? 'checked="checked"' : '';
			printf( $format, $html_name, $id, $selected );
		} // endfor
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
				'icon'						=> __( 'Icon', 'reg-man-rc' ),
				'colour'					=> __( 'Colour', 'reg-man-rc' ),
//				'item_types'				=> __( 'Item Types', 'reg-man-rc' ),
				'ext_names'					=> __( 'Alternate Names', 'reg-man-rc' ),
				'item_count'				=> __( 'Items', 'reg-man-rc' ),
				'event_count'				=> __( 'Events', 'reg-man-rc' ),
				'fixer_count'				=> __( 'Fixers', 'reg-man-rc' ),
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
		$count_format = '<div class="tax-term-count"><a href="edit.php?%1$s=%2$s&post_type=%3$s">%4$s</a></div>';
		$fixer_station = Fixer_Station::get_fixer_station_by_id( $term_id );
		switch ( $column_name ) {
			case 'item_types':
				$types_array = ! empty( $fixer_station ) ? $fixer_station->get_item_types_array() : array();
				$item_type_names_array = array();
				foreach ( $types_array as $item_type ) {
					$item_type_names_array[] = $item_type->get_name();
				} // endfor
				$val = ! empty( $item_type_names_array ) ? implode( ', ', $item_type_names_array ) : $em_dash;
				$content .= esc_attr( $val );
				break;
			case 'icon':
				if ( ! empty( $fixer_station ) ) {
					$url = $fixer_station->get_icon_url();
					if ( ! empty( $url ) ) {
						$val = "<span class=\"reg-man-rc-fixer-station-term-list-icon\"><img src=\"$url\"></span>";
					} else {
						$screen_reader_name = __( 'No icon', 'reg-man-rc' );
						$val = '<span aria-hidden="true" class="reg-man-rc-fixer-station-term-list-no-icon">' . $em_dash . '</span>';
						$val .= "<span class=\"screen-reader-text\">$screen_reader_name</span>";
					} // endif
					$content .= $val;
				} // endif
				break;
			case 'ext_names':
				if ( ! empty( $fixer_station ) ) {
					$ext_names = $fixer_station->get_external_names();
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
			case 'colour':
				if ( ! empty( $fixer_station ) ) {
					$colour = $fixer_station->get_colour();
					if ( ! empty( $colour ) ) {
						$val = "<span class=\"reg-man-rc-colour-display\" style=\"background-color: $colour\">";
					} else {
						$screen_reader_name = __( 'No colour', 'reg-man-rc' );
						$val = '<span aria-hidden="true" class="reg_man_rc_colour_display">' . $em_dash . '</span>';
						$val .= "<span class=\"screen-reader-text\">$screen_reader_name</span>";
					} // endif
					$content .= $val;
				} // endif
				break;
			case 'item_count':
				if ( ! empty( $fixer_station ) ) {
					$count = $fixer_station->get_item_count();
					$taxonomy = Fixer_Station::TAXONOMY_NAME;
					$slug = $fixer_station->get_slug();
					$post_type = Item::POST_TYPE;
					$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
					$content .= $val;
				} // endif
				break;
			case 'event_count':
				if ( ! empty( $fixer_station ) ) {
					$count = $fixer_station->get_internal_event_descriptor_count();
					$taxonomy = Fixer_Station::TAXONOMY_NAME;
					$slug = $fixer_station->get_slug();
					$post_type = Internal_Event_Descriptor::POST_TYPE;
					$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
					$content .= $val;
				} // endif
				break;
			case 'fixer_count':
				if ( ! empty( $fixer_station ) ) {
					$count = $fixer_station->get_fixer_count();
					$taxonomy = Fixer_Station::TAXONOMY_NAME;
					$slug = $fixer_station->get_slug();
					$post_type = Volunteer::POST_TYPE;
					$val = sprintf( $count_format, $taxonomy, $slug, $post_type, $count );
					$content .= $val;
				} // endif
				break;
		} // endswitch
		return $content;
	} // function

} // class
