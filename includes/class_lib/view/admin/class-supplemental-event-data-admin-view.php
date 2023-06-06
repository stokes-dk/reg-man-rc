<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Stats\Visitor_Stats_Collection;
use Reg_Man_RC\Model\Stats\Volunteer_Stats_Collection;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Item_Stats;

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
			
			// We need a single valid event key in order to show these metaboxes
			// If there is no event, e.g. it's a repeating event, then don't add them
			$event_key = $view->get_event_key();

			if ( ! empty( $event_key ) ) {
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
			echo '<div class="reg-man-rc-sup-item-data">';
				self::render_supplemental_visitor_data_table( $event_key );
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
			echo '<div class="reg-man-rc-sup-visitor-data">';
				$this->render_supplemental_item_data_table( $event_key );
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
			$this->render_supplemental_volunteer_data_table( $event_key );
		} // endif
	} // function

	/**
	 * Render the table for the item data
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_supplemental_visitor_data_table( $event_key ) {

		echo '<input type="hidden" name="sup_data_visitor_event_key" value="' . $event_key . '">';

		$group_by = Visitor_Stats_Collection::GROUP_BY_TOTAL;
		$stats_collection = Visitor_Stats_Collection::create_for_event_key_array( array( $event_key->get_as_string() ), $group_by );
		$reg_stats_array = array_values( $stats_collection->get_all_registered_stats_array() ); // there should be just one
		$sup_stats_array = array_values( $stats_collection->get_supplemental_stats_array() ); // there should be just one
		$reg_stats_obj = isset( $reg_stats_array[ 0 ] ) ? $reg_stats_array[ 0 ] : NULL; // all we need is the first element
		$sup_stats_obj = isset( $sup_stats_array[ 0 ] ) ? $sup_stats_array[ 0 ] : NULL; // all we need is the first element
		
		$head_format = '<th>%1$s</th>';
		$data_format = '<td class="reg-man-rc-sup-data-table-cell">%1$s</td>';
		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional visitors who attended the event but were not registered to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
			
				echo '<tr>';
					$heading = __( 'Visitor Type', 'reg-man-rc' );
					printf( $head_format, $heading );
	
					$heading = __( 'Registered', 'reg-man-rc' );
					printf( $head_format, $heading );

					$heading = __( 'Supplemental', 'reg-man-rc' );
					printf( $head_format, $heading );

				echo '</tr>';
					
			echo '</thead>';

			echo '<tbody>';

				// First Time
				echo '<tr>';

					$heading = __( 'First Time Visitors', 'reg-man-rc' );
					printf( $head_format, $heading );
	
					$name = "first_time_visitors";
					$val = isset( $reg_stats_obj ) ? $reg_stats_obj->get_first_time_count() : 0;
					printf( $data_format, $val );

					$name = "first_time_visitors";
					$val = isset( $sup_stats_obj ) ? $sup_stats_obj->get_first_time_count() : 0;
					printf( $input_format, $name, $val );

				echo '</tr>';

				// Returning
				echo '<tr>';

					$heading = __( 'Returning Visitors', 'reg-man-rc' );
					printf( $head_format, $heading );
	
					$name = "returning_visitors";
					$val = isset( $reg_stats_obj ) ? $reg_stats_obj->get_returning_count() : 0;
					printf( $data_format, $val );
					
					$name = "returning_visitors";
					$val = isset( $sup_stats_obj ) ? $sup_stats_obj->get_returning_count() : 0;
					printf( $input_format, $name, $val );

				echo '</tr>';

				// Return status not known
				echo '<tr>';

					$heading = __( 'Visitors whose return status was not reported', 'reg-man-rc' );
					printf( $head_format, $heading );
				
					$name = "unknown_visitors";
					$val = isset( $reg_stats_obj ) ? $reg_stats_obj->get_return_status_unknown_count() : 0;
					printf( $data_format, $val );
					
					$name = "unknown_visitors";
					$val = isset( $sup_stats_obj ) ? $sup_stats_obj->get_return_status_unknown_count() : 0;
					printf( $input_format, $name, $val );

			echo '</tbody>';
		echo '</table>';

	} // function

	/**
	 * Render the table for the item data
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_supplemental_item_data_table( $event_key ) {
		echo '<input type="hidden" name="sup_data_item_event_key" value="' . $event_key . '">';
		
		$group_by = Item_Stats_Collection::GROUP_BY_STATION_AND_TYPE;
		$stats_collection = Item_Stats_Collection::create_for_event_key_array( array( $event_key->get_as_string() ), $group_by );
		$reg_stats_array = $stats_collection->get_all_registered_stats_array();
		$sup_stats_array = $stats_collection->get_supplemental_stats_array();
//		Error_Log::var_dump( $reg_stats_array );
		
		$format =
				'<li class="reg-man-rc-tab-list-item">' . 
					'<a href="#tab-%1$s" class="reg-man-rc-icon-text-container">' . 
						'</i><span class="text">%2$s</span>' . 
					'</a>' . 
				'</li>';

		$all_fixer_stations = Fixer_Station::get_all_fixer_stations();
		$unspecified_station_id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
		$unspecified_station_name = __( 'Fixer station not reported', 'reg-man-rc' );
		
		echo '<div class="reg-man-rc-tabs-container">';
			echo '<ul>';

				// All fixer stations
				foreach( $all_fixer_stations as $fixer_station ) {
					$id = $fixer_station->get_id();
					$name = $fixer_station->get_name();
					printf( $format, $id, $name );
				} // endfor
				
				// Unspecified station
				printf( $format, $unspecified_station_id, $unspecified_station_name );
				
			echo '</ul>';
			
			// All fixer stations
			foreach( $all_fixer_stations as $fixer_station ) {
				$id = $fixer_station->get_id();
				$name = $fixer_station->get_name();
				echo "<div id=\"tab-$id\" class=\"tab-panel\" data-name=\"$id\">";
					self::render_supplemental_fixer_station_item_data_table( $id, $name, $reg_stats_array, $sup_stats_array );
				echo '</div>';
			} // endfor

			// Unspecified station
			$id = $unspecified_station_id;
			$name = $unspecified_station_name;
			echo "<div id=\"tab-$id\" class=\"tab-panel\" data-name=\"$id\">";
				self::render_supplemental_fixer_station_item_data_table( $id, $name, $reg_stats_array, $sup_stats_array );
			echo '</div>';
			
		echo '</div>';
		
	} // function

	/**
	 * Render the table for the item data
	 * @param	int				$station_id
	 * @param	string			$station_name
	 * @param	Item_Stats[]	$reg_stats_array
	 * @param	Item_Stats[]	$sup_stats_array
	 * @return	void
	 * @since 	v0.3.0
	 */
	public static function render_supplemental_fixer_station_item_data_table( $station_id, $station_name, $reg_stats_array, $sup_stats_array ) {
		
		$repair_status_head_format = '<th class="reg-man-rc-sup-data-table-head-repair-status" title="%1$s">%1$s</th>';
		$item_type_head_format = '<th class="reg-man-rc-sup-data-table-head-item-type" title="%1$s">%1$s</th>';
		$head_format = '<th>%1$s</th>';
		$data_format = '<td class="reg-man-rc-sup-data-table-cell">%1$s</td>';
		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';

		if ( $station_id !== Fixer_Station::UNSPECIFIED_FIXER_STATION_ID ) {
			/* Translators: %1$s is the name of a fixer station */
			$caption_format = __( 'Use this table to record additional items brought to the <b>%1$s</b> fixer station but not registered to the system', 'reg-man-rc' );
			$caption = sprintf( $caption_format, $station_name );
		} else {
			$caption = __( 'Use this table to record additional items brought to the event and not registered to the system, and whose fixer station was not reported', 'reg-man-rc' );
		} // endif
		
		$all_item_types = Item_Type::get_all_item_types();
		
		echo '<table class="reg-man-rc-input-group-table">';
			echo '<caption>' . $caption . '</caption>';
			echo '<thead>';
			
				echo '<tr>';

					$heading = __( 'Item Type', 'reg-man-rc' );
					echo "<th rowspan=\"2\">$heading</th>";
					
					$heading = __( 'Registered', 'reg-man-rc' );
					echo "<th colspan=\"4\">$heading</th>";
					
					$heading = __( 'Supplemental', 'reg-man-rc' );
					echo "<th colspan=\"4\">$heading</th>";
					
				echo '</tr>';
				
				echo '<tr>';

					$fixed_head			= __( 'Fixed', 'reg-man-rc' );
					$repairable_head	= __( 'Repairable', 'reg-man-rc' );
					$eol_head			= __( 'End of Life', 'reg-man-rc' );
					$unknown_head		= __( 'Outcome not Reported', 'reg-man-rc' );

					// Registered
					printf( $repair_status_head_format, $fixed_head );
					printf( $repair_status_head_format, $repairable_head );
					printf( $repair_status_head_format, $eol_head );
					printf( $repair_status_head_format, $unknown_head );
					
					// Supplemental
					printf( $repair_status_head_format, $fixed_head );
					printf( $repair_status_head_format, $repairable_head );
					printf( $repair_status_head_format, $eol_head );
					printf( $repair_status_head_format, $unknown_head );
					
				echo '</tr>';
				
			echo '</thead>';

			echo '<tbody>';

				// All item types
				foreach( $all_item_types as $item_type ) {
					$type_name = $item_type->get_name();
					$type_id = $item_type->get_id();
					$stats_index = "$station_id|$type_id";
					$reg_data = isset( $reg_stats_array[ $stats_index ] ) ? $reg_stats_array[ $stats_index ] : NULL;
					$sup_data = isset( $sup_stats_array[ $stats_index ] ) ? $sup_stats_array[ $stats_index ] : NULL;
//					Error_Log::var_dump( $station_id, $type_id, $reg_data );
					echo '<tr>';
						
						printf( $item_type_head_format, $type_name );
						
						$fixed_count = isset( $reg_data ) ? $reg_data->get_fixed_count() : 0;
						printf( $data_format, $fixed_count );
						
						$repairable_count = isset( $reg_data ) ? $reg_data->get_repairable_count() : 0;;
						printf( $data_format, $repairable_count );
						
						$eol_count = isset( $reg_data ) ? $reg_data->get_end_of_life_count() : 0;
						printf( $data_format, $eol_count );
						
						$unknown_count = isset( $reg_data ) ? ( $reg_data->get_item_count() - $fixed_count - $repairable_count - $eol_count ) : 0;
						printf( $data_format, $unknown_count );
						
						$fixed_count = isset( $sup_data ) ? $sup_data->get_fixed_count() : 0;
						$name = "fixed_count[$station_id][$type_id]";
						printf( $input_format, $name, $fixed_count );
						
						$repairable_count = isset( $sup_data ) ? $sup_data->get_repairable_count() : 0;
						$name = "repairable_count[$station_id][$type_id]";
						printf( $input_format, $name, $repairable_count );
						
						$eol_count = isset( $sup_data ) ? $sup_data->get_end_of_life_count() : 0;
						$name = "eol_count[$station_id][$type_id]";
						printf( $input_format, $name, $eol_count );
						
						$unknown_count = isset( $sup_data ) ? ( $sup_data->get_item_count() - $fixed_count - $repairable_count - $eol_count ) : 0;
						$name = "unknown_count[$station_id][$type_id]";
						printf( $input_format, $name, $unknown_count );
						
					echo '</tr>';
				} // endfor
				
				// Unknown Item Type
				echo '<tr>';
					$type_name = __( 'Item type not reported', 'reg-man-rc' );
					$type_id = Item_Type::UNSPECIFIED_ITEM_TYPE_ID;
					$stats_index = "$station_id|$type_id";
					$reg_data = isset( $reg_stats_array[ $stats_index ] ) ? $reg_stats_array[ $stats_index ] : NULL;
					$sup_data = isset( $sup_stats_array[ $stats_index ] ) ? $sup_stats_array[ $stats_index ] : NULL;
					
					printf( $item_type_head_format, $type_name );
					
					$fixed_count = isset( $reg_data ) ? $reg_data->get_fixed_count() : 0;
					printf( $data_format, $fixed_count );
					
					$repairable_count = isset( $reg_data ) ? $reg_data->get_repairable_count() : 0;;
					printf( $data_format, $repairable_count );
					
					$eol_count = isset( $reg_data ) ? $reg_data->get_end_of_life_count() : 0;
					printf( $data_format, $eol_count );
					
					$unknown_count = isset( $reg_data ) ? ( $reg_data->get_item_count() - $fixed_count - $repairable_count - $eol_count ) : 0;
					printf( $data_format, $unknown_count );
					
					$fixed_count = isset( $sup_data ) ? $sup_data->get_fixed_count() : 0;
					$name = "fixed_count[$station_id][$type_id]";
					printf( $input_format, $name, $fixed_count );
					
					$repairable_count = isset( $sup_data ) ? $sup_data->get_repairable_count() : 0;
					$name = "repairable_count[$station_id][$type_id]";
					printf( $input_format, $name, $repairable_count );
					
					$eol_count = isset( $sup_data ) ? $sup_data->get_end_of_life_count() : 0;
					$name = "eol_count[$station_id][$type_id]";
					printf( $input_format, $name, $eol_count );
					
					$unknown_count = isset( $sup_data ) ? ( $sup_data->get_item_count() - $fixed_count - $repairable_count - $eol_count ) : 0;
					$name = "unknown_count[$station_id][$type_id]";
					printf( $input_format, $name, $unknown_count );
						
				echo '</tr>';
					
			echo '</tbody>';

		echo '</table>';

	} // function

	/**
	 * Render the table for the item data
	 * @return	void
	 * @since 	v0.1.0
	 */
/*
	public static function render_supplemental_item_data_table( $event_key ) {

		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
//		$event_key_array = array( $event_key_string );
//		$group_by = Item_Stats_Collection::GROUP_BY_ITEM_TYPE;
//		$stats_collection = Item_Stats_Collection::create_for_event_key_array( $event_key_array, $group_by );
//		$item_group_data = $stats_collection->get_supplemental_stats_array();

//		Error_Log::var_dump( $item_group_data );

		$col_count = 6; // number of columns in the table, needed for colspan later
		$head_format = '<th class="%2$s">%1$s</th>';
//		$row_head_format = '<td>%1$s</td>';
//		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional items brought to the event but not registered to the system', 'reg-man-rc' );
		echo '<table class="reg-man-rc-input-group-table reg-man-rc-sup-item-table">';

			echo '<caption>' . $caption . '</caption>';

			echo '<thead>';
				echo '<tr>';
					$heading = __( 'Item Type', 'reg-man-rc' );
					printf( $head_format, $heading, 'select-col' );
	
					$heading = __( 'Fixer Station', 'reg-man-rc' );
					printf( $head_format, $heading, 'select-col' );
	
					$heading = __( 'Fixed', 'reg-man-rc' );
					printf( $head_format, $heading, 'num-col' );
	
					$heading = __( 'Repairable', 'reg-man-rc' );
					printf( $head_format, $heading, 'num-col' );
	
					$heading = __( 'End of Life', 'reg-man-rc' );
					printf( $head_format, $heading, 'num-col' );
	
					$heading = __( 'Repair outcome not reported', 'reg-man-rc' );
					printf( $head_format, $heading, 'num-col' );
				echo '</tr>';
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
					self::render_supplemental_item_data_row( $station_id, $type_id, $fixed, $repairable, $eol, $unknown );
				} // endfor

				self::render_supplemental_item_data_row( 0, 0, 0, 0, 0, 0, $is_template = TRUE );

				$label = __( 'Add new row', 'reg-man-rc' );
				echo '<tr class="sup-item-add-button-row">';
					echo "<td colspan=\"$col_count\" class=\"\">";
						echo "<button type=\"button\" class=\"button sup-item-add-row-button\">$label</button>";
					echo '</td>';
				echo '</tr>';

			echo '</tbody>';

		echo '</table>';

	} // function
*/
/*
	private static function render_supplemental_item_data_row( $station_id, $type_id, $fixed, $repairable, $eol, $unknown, $is_template = FALSE ) {

		$classes = $is_template ? 'reg-man-rc-sup-item-data-template' : '';
		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';

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
//							$default_station = $option->get_fixer_station();
//							$default_station_id = isset( $default_station ) ? $default_station->get_id() : '-1';
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
*/
	/**
	 * Render the table for the volunteer data
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_supplemental_volunteer_data_table( $event_key ) {
		
		echo '<input type="hidden" name="sup_data_volunteer_event_key" value="' . $event_key . '">';
		
		$fixers_title 		= esc_html__( 'Fixers', 'reg-man-rc' );
		$non_fixers_title 	= esc_html__( 'Non-Fixers', 'reg-man-rc' );
		
		$format =
				'<li class="reg-man-rc-tab-list-item">' . 
					'<a href="#tab-%1$s" class="reg-man-rc-icon-text-container">' . 
						'<span class="text">%2$s</span>' . 
					'</a>' . 
				'</li>';
		echo '<div class="reg-man-rc-tabs-container">';
			echo '<ul>';
				printf( $format, 'fixers',		$fixers_title );
				printf( $format, 'non-fixers',	$non_fixers_title );
			echo '</ul>';
			
			echo '<div id="tab-fixers" class="tab-panel" data-name="fixers">';
				self::render_supplemental_fixer_data_table( $event_key );
			echo '</div>';

			echo '<div id="tab-non-fixers" class="tab-panel" data-name="non-fixers">';
				self::render_supplemental_non_fixer_data_table( $event_key );
			echo '</div>';
			
		echo '</div>';
			
	} // function

	/**
	 * Render the table for the supplemental fixer data
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_supplemental_fixer_data_table( $event_key ) {

		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
		$event_key_array = array( $event_key_string );
		$group_by = Volunteer_Stats_Collection::GROUP_BY_FIXER_STATION;
		$stats_collection = Volunteer_Stats_Collection::create_for_event_key_array( $event_key_array, $group_by );
		$reg_group_data = $stats_collection->get_all_registered_stats_array();
		$sup_group_data = $stats_collection->get_supplemental_stats_array();
//		Error_Log::var_dump( $sup_group_data );

		$head_format = '<th>%1$s</th>';
		$data_format = '<td class="reg-man-rc-sup-data-table-cell">%1$s</td>';
		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional fixers and apprentices who attended the event but did not register to the system', 'reg-man-rc' );

		echo '<table class="reg-man-rc-input-group-table">';

			echo '<caption>' . $caption . '</caption>';

			echo '<thead>';

				echo '<tr>';
					$heading = __( 'Fixer Station', 'reg-man-rc' );
					echo "<th rowspan=\"2\">$heading</th>";
	
					$heading = __( 'Registered', 'reg-man-rc' );
					echo "<th colspan=\"2\">$heading</th>";

					$heading = __( 'Supplemental', 'reg-man-rc' );
					echo "<th colspan=\"2\">$heading</th>";
				echo '</tr>';

				echo '<tr>';
					$fixer_heading = __( 'Fixer', 'reg-man-rc' );
					$apprentice_heading = __( 'Apprentice', 'reg-man-rc' );

					printf( $head_format, $fixer_heading );
	
					printf( $head_format, $apprentice_heading );
					
					printf( $head_format, $fixer_heading );
	
					printf( $head_format, $apprentice_heading );
				echo '</tr>';

			echo '</thead>';
			
			echo '<tbody>';
				$stations = Fixer_Station::get_all_fixer_stations();
				foreach( $stations as $fixer_station ) {
					echo '<tr>';

						$id = $fixer_station->get_id();
						$reg_data = isset( $reg_group_data[ $id ] ) ? $reg_group_data[ $id ] : NULL;
						$sup_data = isset( $sup_group_data[ $id ] ) ? $sup_group_data[ $id ] : NULL;

						$title = $fixer_station->get_name();
						printf( $head_format, $title );

						$reg_head_count = isset( $reg_data ) ? $reg_data->get_head_count() : 0;
						$reg_appr_count = isset( $reg_data ) ? $reg_data->get_apprentice_count() : 0;
						$reg_fixer_count = $reg_head_count - $reg_appr_count;
						printf( $data_format, $reg_fixer_count );
						
						printf( $data_format, $reg_appr_count );
						
						$sup_head_count = isset( $sup_data ) ? $sup_data->get_head_count() : 0;
						$sup_appr_count = isset( $sup_data ) ? $sup_data->get_apprentice_count() : 0;
						$sup_fixer_count = $sup_head_count - $sup_appr_count;
						$name = "fixer_head_count[$id]";
						printf( $input_format, $name, $sup_fixer_count );

						$name = "apprentice_head_count[$id]";
						printf( $input_format, $name, $sup_appr_count );

					echo '</tr>';
				} // endfor

				echo '<tr>';
					$id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
					$reg_data = isset( $reg_group_data[ $id ] ) ? $reg_group_data[ $id ] : NULL;
					$sup_data = isset( $sup_group_data[ $id ] ) ? $sup_group_data[ $id ] : NULL;

					$title = __( 'Fixer station not reported', 'reg-man-rc' );
					printf( $head_format, $title );

					$reg_head_count = isset( $reg_data ) ? $reg_data->get_head_count() : 0;
					$reg_appr_count = isset( $reg_data ) ? $reg_data->get_apprentice_count() : 0;
					$reg_fixer_count = $reg_head_count - $reg_appr_count;
					printf( $data_format, $reg_fixer_count );
					
					printf( $data_format, $reg_appr_count );

					$sup_head_count = isset( $sup_data ) ? $sup_data->get_head_count() : 0;
					$sup_appr_count = isset( $sup_data ) ? $sup_data->get_apprentice_count() : 0;
					$sup_fixer_count = $sup_head_count - $sup_appr_count;
					$name = "fixer_head_count[$id]";
					printf( $input_format, $name, $sup_fixer_count );

					$name = "apprentice_head_count[$id]";
					printf( $input_format, $name, $sup_appr_count );

					echo '</tr>';

			echo '</tbody>';
		echo '</table>';

	} // function


	/**
	 * Render the table for the supplemental fixer data
	 * @return	void
	 * @since 	v0.1.0
	 */
	public static function render_supplemental_non_fixer_data_table( $event_key ) {

		$event_key_string = isset( $event_key ) ? $event_key->get_as_string() : NULL;
		$event_key_array = array( $event_key_string );
		$group_by = Volunteer_Stats_Collection::GROUP_BY_VOLUNTEER_ROLE;
		$stats_collection = Volunteer_Stats_Collection::create_for_event_key_array( $event_key_array, $group_by );
		$reg_group_data = $stats_collection->get_all_registered_stats_array();
		$sup_group_data = $stats_collection->get_supplemental_stats_array();

		$head_format = '<th>%1$s</th>';
		$data_format = '<td class="reg-man-rc-sup-data-table-cell">%1$s</td>';
		$input_format = '<td class="reg-man-rc-sup-data-table-cell"><input autocomplete="off" type="number" name="%1$s" value="%2$s" min="0" size="4"></td>';
		$caption = __( 'Use this table to record additional non-fixer volunteers who attended the event but did not register to the system', 'reg-man-rc' );

		echo '<table class="reg-man-rc-input-group-table">';

			echo '<caption>' . $caption . '</caption>';

			echo '<thead>';
				echo '<tr>';
					$heading = __( 'Volunteer Role', 'reg-man-rc' );
					printf( $head_format, $heading );
	
					$heading = __( 'Registered', 'reg-man-rc' );
					printf( $head_format, $heading );
					
					$heading = __( 'Supplemental', 'reg-man-rc' );
					printf( $head_format, $heading );
				echo '</tr>';
			echo '</thead>';

			echo '<tbody>';
				$roles = Volunteer_Role::get_all_volunteer_roles();
				foreach( $roles as $volunteer_role ) {
					echo '<tr>';

						$id = $volunteer_role->get_id();
						$reg_data = isset( $reg_group_data[ $id ] ) ? $reg_group_data[ $id ] : NULL;
						$sup_data = isset( $sup_group_data[ $id ] ) ? $sup_group_data[ $id ] : NULL;

						$title = $volunteer_role->get_name();
						printf( $head_format, $title );
						
						$reg_head_count = isset( $reg_data ) ? $reg_data->get_head_count() : 0;
						printf( $data_format, $reg_head_count );

						$name = "non_fixer_head_count[$id]";
						$sup_head_count = isset( $sup_data ) ? $sup_data->get_head_count() : 0;
						printf( $input_format, $name, $sup_head_count );

					echo '</tr>';
				} // endfor

				echo '<tr>';
					$id = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID;
					$reg_data = isset( $reg_group_data[ $id ] ) ? $reg_group_data[ $id ] : NULL;
					$sup_data = isset( $sup_group_data[ $id ] ) ? $sup_group_data[ $id ] : NULL;
					
					$title = __( 'Volunteer role not reported', 'reg-man-rc' );
					printf( $head_format, $title );

					$reg_head_count = isset( $reg_data ) ? $reg_data->get_head_count() : 0;
					printf( $data_format, $reg_head_count );

					$name = "non_fixer_head_count[$id]";
					$sup_head_count = isset( $sup_data ) ? $sup_data->get_head_count() : 0;
					printf( $input_format, $name, $sup_head_count );

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
