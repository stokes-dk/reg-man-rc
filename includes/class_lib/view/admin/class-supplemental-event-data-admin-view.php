<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Stats\Item_Statistics;
use Reg_Man_RC\Model\Stats\Visitor_Statistics;
use Reg_Man_RC\Model\Stats\Volunteer_Statistics;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Stats\Supplemental_Item;

/**
 * The administrative view for Supplemental Event Data
 *
 * @since	v0.1.0
 *
 */
class Supplemental_Event_Data_Admin_View {

	private static $SUPPORTED_POST_TYPES;

	private $post;
	private $event_key;
	private $sup_data;

	private function __construct() {
	} // function

	/**
	 * Create a new instance of this view for the specified post
	 * @param	\WP_Post	$post	The post whose view is being constructed
	 * @return	self
	 * @since 	v0.1.0
	 */
	private static function create_for_post( $post ) {
		$result = new self();
		$result->post = $post;
		return $result;
	} // function

	/**
	 * Get the post for this view for the specified post
	 * @return	\WP_Post	$post	The post for this view
	 * @since 	v0.1.0
	 */
	private function get_post() {
		return $this->post;
	} // function

	private function get_event_key() {
		if ( ! isset( $this->event_key ) ) {
			$post = $this->get_post();
			switch( $post->post_type ) {

				case Internal_Event_Descriptor::POST_TYPE:
					$event_desc = Internal_Event_Descriptor::get_internal_event_descriptor_by_event_id( $post->ID );
					if ( isset( $event_desc ) ) {
						if ( ! $event_desc->get_event_is_recurring() ) {
							$event_id = $event_desc->get_event_descriptor_id();
							$provider_id = $event_desc->get_provider_id();
							$this->event_key = Event_Key::create( $event_id, $provider_id );
						} // endif
					} // endif
					break;

			} // endswitch
		} // endif
		return $this->event_key;
	} // function

	public static function get_supported_post_types() {
		if ( ! isset( self::$SUPPORTED_POST_TYPES ) ) {
			self::$SUPPORTED_POST_TYPES = array();
			self::$SUPPORTED_POST_TYPES[] = Internal_Event_Descriptor::POST_TYPE;
		} // endif
		return self::$SUPPORTED_POST_TYPES;
	} // function

	/**
	 * Set up the necessary hooks, filters etc. for this administrative view
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// Set up the meta boxes
		add_action( 'add_meta_boxes' , array( __CLASS__, 'add_meta_boxes' ), 10, 2 );

	} // function

	/**
	 * Add the meta boxes for Supplemental Event Data
	 * @param	string	$post_type	The type for the current post being edited
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function add_meta_boxes( $post_type, $post ) {
		$valid_post_types = self::get_supported_post_types();
		if ( in_array( $post_type, $valid_post_types ) ) {
			$view = self::create_for_post( $post );

			$new_id = 'reg-man-rc-sup-data-visitors-metabox';
			$render_fn = array( $view, 'render_visitor_data_meta_box' );
			add_meta_box(
					$new_id,							// Unique ID for the element
					__( 'Visitors', 'reg-man-rc' ),		// Box title
					$render_fn,							// Content callback, must be of type callable
					$post_type,							// Post type for this meta box
					'normal', 							// Meta box position
					'high'								// Meta box priority
	        );

			$new_id = 'reg-man-rc-sup-data-items-metabox';
			$render_fn = array( $view, 'render_item_data_meta_box' );
			add_meta_box(
					$new_id,							// Unique ID for the element
					__( 'Items', 'reg-man-rc' ),		// Box title
					$render_fn,							// Content callback, must be of type callable
					$post_type,							// Post type for this meta box
					'normal', 							// Meta box position
					'high'								// Meta box priority
	        );

			$new_id = 'reg-man-rc-sup-data-volunteers-metabox';
			$render_fn = array( $view, 'render_volunteer_data_meta_box' );
			add_meta_box(
					$new_id,							// Unique ID for the element
					__( 'Volunteers', 'reg-man-rc' ),	// Box title
					$render_fn,							// Content callback, must be of type callable
					$post_type,							// Post type for this meta box
					'normal', 							// Meta box position
					'high'								// Meta box priority
	        );

		} // endif

	} // function

	/**
	 * Render the meta box for the visitor data
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public function render_visitor_data_meta_box( $post ) {

		$event_key = $this->get_event_key();
		if ( ! empty( $event_key ) ) {
			echo '<input type="hidden" name="sup_data_visitor_event_key" value="' . $event_key . '">';
			echo '<div class="reg-man-rc-sup-item-data">';
				$title = __( 'Supplemental Visitor Data', 'reg-man-rc' );
				echo '<h3>' . $title . '</h3>';
				$this->render_supplemental_visitor_data_table();
			echo '</div>';

		} // endif

	} // function

	/**
	 * Render the meta box for the item data
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public function render_item_data_meta_box( $post ) {
		$event_key = $this->get_event_key();
		if ( ! empty( $event_key ) ) {
			echo '<input type="hidden" name="sup_data_item_event_key" value="' . $event_key . '">';
			echo '<div class="reg-man-rc-sup-visitor-data">';
				$title = __( 'Supplemental Item Data', 'reg-man-rc' );
				echo '<h3>' . $title . '</h3>';
				$this->render_supplemental_item_data_table();
			echo '</div>';

		} // endif
	} // function

	/**
	 * Render the meta box for the fixer data
	 * @param	\WP_Post	$post
	 * @return	void
	 * @since 	v0.1.0
	 */
	public function render_volunteer_data_meta_box( $post ) {
		$event_key = $this->get_event_key();
		if ( ! empty( $event_key ) ) {
			echo '<input type="hidden" name="sup_data_volunteer_event_key" value="' . $event_key . '">';

			echo '<div class="reg-man-rc-sup-fixer-data">';
				$title = __( 'Supplemental Fixer Data', 'reg-man-rc' );
				echo '<h3>' . $title . '</h3>';
				$this->render_supplemental_fixer_data_table();
			echo '</div>';

			echo '<div class="reg-man-rc-sup-non-fixer-data">';
				$title = __( 'Supplemental Non-Fixer Data', 'reg-man-rc' );
				echo '<h3>' . $title . '</h3>';
				$this->render_supplemental_non_fixer_data_table();
			echo '</div>';

		} // endif
	} // function

	/**
	 * Render the table for the item data
	 * @return	void
	 * @since 	v0.1.0
	 */
	private function render_supplemental_visitor_data_table() {

		$event_key = $this->get_event_key();
		$group_by = Visitor_Statistics::GROUP_BY_TOTAL;
		$statistics = Visitor_Statistics::create_for_event_key_array( array( $event_key->get_as_string() ), $group_by );
		$stats_array = array_values( $statistics->get_supplemental_stats_array() ); // there should be just one
		$stats_obj = isset( $stats_array[ 0 ] ) ? $stats_array[ 0 ] : NULL; // all we need is the first element

		$head_format = '<th>%1$s</th>';
		$row_name_format = '<td>%1$s</td>';
		$input_format = '<td class="reg-man-rc-input-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional visitors who attended the event but were not registered to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
				$heading = __( 'First Time Visitors', 'reg-man-rc' );
				printf( $head_format, $heading );

				$heading = __( 'Returning Visitors', 'reg-man-rc' );
				printf( $head_format, $heading );

				$heading = __( 'Visitors whose return status is not known', 'reg-man-rc' );
				printf( $head_format, $heading );

			echo '</thead>';
			echo '<tbody>';

				echo '<tr>';

					$name = "first_time_visitors";
					$val = isset( $stats_obj ) ? $stats_obj->get_first_time_count() : 0;
					printf( $input_format, $name, $val );

					$name = "returning_visitors";
					$val = isset( $stats_obj ) ? $stats_obj->get_returning_count() : 0;
					printf( $input_format, $name, $val );

					$name = "unknown_visitors";
					$val = isset( $stats_obj ) ? $stats_obj->get_return_status_unknown_count() : 0;
					printf( $input_format, $name, $val );

				echo '</tr>';

			echo '</tbody>';
		echo '</table>';

	} // function

	/**
	 * Render the table for the item data
	 * @return	void
	 * @since 	v0.1.0
	 */
	private function render_supplemental_item_data_table() {

		$event_key = $this->get_event_key();
		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
		$event_key_array = array( $event_key_string );
		$group_by = Item_Statistics::GROUP_BY_ITEM_TYPE;
		$statistics = Item_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$item_group_data = $statistics->get_supplemental_stats_array();

//		Error_Log::var_dump( $item_group_data );

		$col_count = 6; // number of columns in the table, needed for colspan later
		$head_format = '<th class="%2$s">%1$s</th>';
//		$row_name_format = '<td>%1$s</td>';
//		$input_format = '<td class="reg-man-rc-input-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional items brought to the event but not registered to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table reg-man-rc-sup-item-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
				$heading = __( 'Item Type', 'reg-man-rc' );
				$subheading = '';
				printf( $head_format, $heading, 'select-col' );

				$heading = __( 'Fixer Station', 'reg-man-rc' );
				$subheading = '';
				printf( $head_format, $heading, 'select-col' );

				$heading = __( 'Fixed', 'reg-man-rc' );
				printf( $head_format, $heading, 'num-col' );

				$heading = __( 'Repairable', 'reg-man-rc' );
				printf( $head_format, $heading, 'num-col' );

				$heading = __( 'End of Life', 'reg-man-rc' );
				printf( $head_format, $heading, 'num-col' );

				$heading = __( 'Repair outcome not reported', 'reg-man-rc' );
				printf( $head_format, $heading, 'num-col' );

			echo '</thead>';

			echo '<tbody>';

				$data_array = Supplemental_Item::get_supplemental_stats_for_event( $event_key_string );
				foreach ( $data_array as $data ) {
					$type_id	= isset( $data[ 'item_type_id' ] )		? $data[ 'item_type_id' ]		: 0;
					$station_id	= isset( $data[ 'fixer_station_id' ] )	? $data[ 'fixer_station_id' ]	: 0;
					$fixed		= isset( $data[ 'fixed_count' ] )		? $data[ 'fixed_count' ]		: 0;
					$repairable	= isset( $data[ 'repairable_count' ] )	? $data[ 'repairable_count' ]	: 0;
					$eol		= isset( $data[ 'eol_count' ] )			? $data[ 'eol_count' ]			: 0;
					$unknown	= isset( $data[ 'unreported_count' ] )	? $data[ 'unreported_count' ]	: 0;
					$this->render_supplemental_item_data_row( $station_id, $type_id, $fixed, $repairable, $eol, $unknown );
				} // endfor

				$this->render_supplemental_item_data_row( 0, 0, 0, 0, 0, 0, $is_template = TRUE );

				$label = __( 'Add new row', 'reg-man-rc' );
				echo '<tr class="sup-item-add-button-row">';
					echo "<td colspan=\"$col_count\" class=\"\">";
						echo "<button type=\"button\" class=\"button sup-item-add-row-button\">$label</button>";
					echo '</td>';
				echo '</tr>';

			echo '</tbody>';

		echo '</table>';

	} // function

	private function render_supplemental_item_data_row( $station_id, $type_id, $fixed, $repairable, $eol, $unknown, $is_template = FALSE ) {

		$classes = $is_template ? 'reg-man-rc-sup-item-data-template' : '';
		$input_format = '<td class="reg-man-rc-input-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';

		echo "<tr class=\"reg-man-rc-sup-item-data-row $classes\">";

			// Item Type
			$options = Item_Type::get_all_item_types();
			$input_name = esc_attr( 'sup_items_type[]' );
			$no_type_text = __( '[Unknown item type]', 'reg-man-rc' );
			$no_select_value = 0;
			echo '<td>';
				if ( $is_template ) {
					// For the template we need a select
					echo "<select name=\"$input_name\" autocomplete=\"off\">";
						$html_name = esc_html( $no_type_text );
						$selected = selected( $no_select_value, $type_id, $echo = FALSE );
						echo "<option value=\"$no_select_value\" $selected>$html_name</option>";
						foreach ( $options as $option ) {
							$name = $option->get_name();
							$id = $option->get_id();
							$default_station = $option->get_fixer_station();
							$default_station_id = isset( $default_station ) ? $default_station->get_id() : '-1';
							$html_name = esc_html( $name );
							$selected = selected( $id, $type_id, $echo = FALSE );
							echo "<option value=\"$id\" data-fixer-station=\"$station_id\" $selected>$html_name</option>";
						} // endfor
					echo '</select>';
				} else {
					// For existing rows we will output just the name and a hidden input
					$type = Item_Type::get_item_type_by_id( $type_id );
					$type_name = isset( $type ) ? $type->get_name() : $no_type_text;
					echo "<input type=\"hidden\" name=\"$input_name\" value=\"$type_id\">";
					echo $type_name;
				} // endif
			echo '</td>';

			// Fixer Station
			$options = Fixer_Station::get_all_fixer_stations();
			$input_name = esc_attr( 'sup_items_station[]' );
			$no_station_text = __( '[Unknown fixer station]', 'reg-man-rc' );
			$no_select_value = 0;
			echo '<td>';
				if ( $is_template ) {
					echo "<select name=\"$input_name\" autocomplete=\"off\">";
						$html_name = esc_html( $no_station_text );
						$selected = selected( $no_select_value, $station_id, $echo = FALSE );
						echo "<option value=\"$no_select_value\" $selected>$html_name</option>";
						foreach ( $options as $option ) {
							$name = $option->get_name();
							$id = $option->get_id();
							$html_name = esc_html( $name );
							$selected = selected( $id, $station_id, $echo = FALSE );
							echo "<option value=\"$id\" $selected>$html_name</option>";
						} // endfor
					echo '</select>';
				} else {
					// For existing rows we will output just the name and a hidden input
					$station = Fixer_Station::get_fixer_station_by_id( $station_id );
					$station_name = isset( $station ) ? $station->get_name() : $no_station_text;
					echo "<input type=\"hidden\" name=\"$input_name\" value=\"$station_id\">";
					echo $station_name;
				} // endif
			echo '</td>';

			$name = 'fixed_items[]';
			printf( $input_format, $name, $fixed );

			$name = 'repairable_items[]';
			printf( $input_format, $name, $repairable );

			$name = 'eol_items[]';
			printf( $input_format, $name, $eol );

			$name = 'unknown_items[]';
			printf( $input_format, $name, $unknown );

		echo '</tr>';


	} // function

	/**
	 * Render the table for the supplemental fixer data
	 * @return	void
	 * @since 	v0.1.0
	 */
	private function render_supplemental_fixer_data_table() {

		$event_key = $this->get_event_key();
		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
		$event_key_array = array( $event_key_string );
		$group_by = Volunteer_Statistics::GROUP_BY_FIXER_STATION;
		$statistics = Volunteer_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$fixer_group_data = $statistics->get_supplemental_stats_array();

		$head_format = '<th>%1$s</th>';
		$row_name_format = '<td>%1$s</td>';
		$input_format = '<td class="reg-man-rc-input-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional fixers who attended the event but did not register to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
				$heading = __( 'Fixer Station', 'reg-man-rc' );
				$subheading = '';
				printf( $head_format, $heading );

				$heading = __( 'Head Count', 'reg-man-rc' );
				printf( $head_format, $heading );

				echo '</thead>';
			echo '<tbody>';
				$stations = Fixer_Station::get_all_fixer_stations();
				foreach( $stations as $fixer_station ) {
					echo '<tr>';

						$id = $fixer_station->get_id();
						$fixer_data = isset( $fixer_group_data[ $id ] ) ? $fixer_group_data[ $id ] : NULL;

						$title = $fixer_station->get_name();
						printf( $row_name_format, $title );

						$name = "fixer_head_count[$id]";
						$head_count = isset( $fixer_data ) ? $fixer_data->get_head_count() : 0;
						printf( $input_format, $name, $head_count );

					echo '</tr>';
				} // endfor

				echo '<tr>';
					$id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
					$fixer_data = isset( $fixer_group_data[ $id ] ) ? $fixer_group_data[ $id ] : NULL;

					$title = __( 'Fixer station not known', 'reg-man-rc' );
					printf( $row_name_format, $title );

					$name = "fixer_head_count[$id]";
					$head_count = isset( $fixer_data ) ? $fixer_data->get_head_count() : 0;
					printf( $input_format, $name, $head_count );

				echo '</tr>';

			echo '</tbody>';
		echo '</table>';

	} // function


	/**
	 * Render the table for the supplemental fixer data
	 * @return	void
	 * @since 	v0.1.0
	 */
	private function render_supplemental_non_fixer_data_table() {

		$event_key = $this->get_event_key();
		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
		$event_key_array = array( $event_key_string );
		$group_by = Volunteer_Statistics::GROUP_BY_VOLUNTEER_ROLE;
		$statistics = Volunteer_Statistics::create_for_event_key_array( $event_key_array, $group_by );
		$non_fixer_group_data = $statistics->get_supplemental_stats_array();

		$head_format = '<th>%1$s</th>';
		$row_name_format = '<td>%1$s</td>';
		$input_format = '<td class="reg-man-rc-input-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional non-fixer volunteers who attended the event but did not register to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
				$heading = __( 'Volunteer Role', 'reg-man-rc' );
				$subheading = '';
				printf( $head_format, $heading );

				$heading = __( 'Head Count', 'reg-man-rc' );
				printf( $head_format, $heading );

				echo '</thead>';
			echo '<tbody>';
				$roles = Volunteer_Role::get_all_volunteer_roles();
				foreach( $roles as $volunteer_role ) {
					echo '<tr>';

						$id = $volunteer_role->get_id();
						$volunteer_data = isset( $non_fixer_group_data[ $id ] ) ? $non_fixer_group_data[ $id ] : NULL;

						$title = $volunteer_role->get_name();
						printf( $row_name_format, $title );

						$name = "non_fixer_head_count[$id]";
						$head_count = isset( $volunteer_data ) ? $volunteer_data->get_head_count() : 0;
						printf( $input_format, $name, $head_count );

					echo '</tr>';
				} // endfor

				echo '<tr>';
					$id = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID;
					$volunteer_data = isset( $non_fixer_group_data[ $id ] ) ? $non_fixer_group_data[ $id ] : NULL;

					$title = __( 'Volunteer role not known', 'reg-man-rc' );
					printf( $row_name_format, $title );

					$name = "non_fixer_head_count[$id]";
					$head_count = isset( $volunteer_data ) ? $volunteer_data->get_head_count() : 0;
					printf( $input_format, $name, $head_count );

				echo '</tr>';

			echo '</tbody>';
		echo '</table>';

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
		$post_types_array = self::get_supported_post_types();
		if ( is_object( $screen ) &&
				( in_array( $screen->post_type, $post_types_array ) ) &&
				( empty( $screen->taxonomy ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
		} // endif
	} // function

} // class
