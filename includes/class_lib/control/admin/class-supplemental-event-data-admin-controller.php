<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Admin\Supplemental_Event_Data_Admin_View;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Stats\Supplemental_Volunteer_Registration;
use Reg_Man_RC\Model\Stats\Supplemental_Item;
use Reg_Man_RC\Model\Stats\Supplemental_Visitor_Registration;

/**
 * The supplemental event data controller
 *
 * This class provides the controller function for working with supplemental event data
 *
 * @since v0.1.0
 *
 */
class Supplemental_Event_Data_Admin_Controller {

	public static function register() {

		$post_types = Supplemental_Event_Data_Admin_View::get_supported_post_types();
		foreach( $post_types as $post_type ) {
			// Add an action hook to upate our custom fields when a post is saved
			add_action( 'save_post_' . $post_type, array( __CLASS__, 'handle_post_save' ) );
		} // endfor

	} // function

/**
 * Handle a post save event for my post type
 *
 * @param	int		$post_id	The ID of the post being saved
 * @return	void
 *
 * @since v0.1.0
 *
 */
	public static function handle_post_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Don't do anything during an autosave
			return;
		} else {

			// Update the supplemental visitor data if it was supplied
			$event_key = isset( $_POST['sup_data_visitor_event_key'] ) ? wp_unslash( $_POST['sup_data_visitor_event_key'] ) : NULL;
			if ( ! empty( $event_key ) ) {
				$first_time = isset( $_POST[ 'first_time_visitors' ] ) ? $_POST[ 'first_time_visitors' ] : 0;
				$returning = isset( $_POST[ 'returning_visitors' ] ) ? $_POST[ 'returning_visitors' ] : 0;
				$unknown = isset( $_POST[ 'unknown_visitors' ] ) ? $_POST[ 'unknown_visitors' ] : 0;
				Supplemental_Visitor_Registration::set_supplemental_visitor_reg_counts( $event_key, $first_time, $returning, $unknown );
			} // endif

			// Update the item data
			$event_key = isset( $_POST['sup_data_item_event_key'] ) ? wp_unslash( $_POST['sup_data_item_event_key'] ) : NULL;
			if ( ! empty( $event_key ) ) {

				$item_types			= isset( $_POST[ 'sup_items_type'] )	? $_POST[ 'sup_items_type']		: array();
				$fixer_stations		= isset( $_POST[ 'sup_items_station'] )	? $_POST[ 'sup_items_station']	: array();
				$fixed_items		= isset( $_POST[ 'fixed_items' ] )		? $_POST[ 'fixed_items' ]		: array();
				$repairable_items	= isset( $_POST[ 'repairable_items' ] )	? $_POST[ 'repairable_items' ]	: array();
				$eol_items			= isset( $_POST[ 'eol_items' ] )		? $_POST[ 'eol_items' ]			: array();
				$unknown_items		= isset( $_POST[ 'unknown_items' ] )	? $_POST[ 'unknown_items' ]		: array();

//				Error_Log::var_dump( $item_types, $fixer_stations, $fixed_items, $repairable_items, $eol_items, $unknown_items );

				// Check the input numbers and combine any duplicate rows, i.e. item type and fixer station are the same
				// To do this, I'll create an associative array keyed by a concatenation of item type and fixer station
				$data_array = array();
				$index = 0; // all the arrays are indexed from 0
				foreach( $item_types as $item_type_id ) {
					$station_id 		= isset( $fixer_stations[ $index ] )	? $fixer_stations[ $index ]		: 0;
					$fixed_count 		= isset( $fixed_items[ $index ] )		? $fixed_items[ $index ]		: 0;
					$repairable_count	= isset( $repairable_items[ $index ] )	? $repairable_items[ $index ]	: 0;
					$eol_count			= isset( $eol_items[ $index ] )			? $eol_items[ $index ]			: 0;
					$unknown_count		= isset( $unknown_items[ $index ] )		? $unknown_items[ $index ]		: 0;
					$key = $item_type_id . ',' . $station_id;
//					Error_Log::var_dump( $item_type_id, $station_id, $fixed_count, $repairable_count, $eol_count, $unknown_count );
					if ( isset( $data_array[ $key ] ) ) {
						$record = $data_array[ $key ];
					} else {
						$record = array(
								'item_type_id'		=> $item_type_id,
								'station_id'		=> $station_id,
								'fixed_count'		=> 0,
								'repairable_count'	=> 0,
								'eol_count'			=> 0,
								'unknown_count'		=> 0
						);
					} // endif
					$record[ 'fixed_count' ]		+= $fixed_count;
					$record[ 'repairable_count' ]	+= $repairable_count;
					$record[ 'eol_count' ]			+= $eol_count;
					$record[ 'unknown_count' ]		+= $unknown_count;
					$data_array[ $key ] = $record;
					$index++;
				} // endfor

//				Error_Log::var_dump( $data_array );

				foreach( $data_array as $record ) {
					$type_id		= isset( $record[ 'item_type_id' ] )		? $record[ 'item_type_id' ] 	: 0;
					$station_id		= isset( $record[ 'station_id' ] )			? $record[ 'station_id' ] 		: 0;
					$fixed			= isset( $record[ 'fixed_count' ] )			? $record[ 'fixed_count' ] 		: 0;
					$repairable		= isset( $record[ 'repairable_count' ] )	? $record[ 'repairable_count' ]	: 0;
					$eol			= isset( $record[ 'eol_count' ] )			? $record[ 'eol_count' ]		: 0;
					$unknown		= isset( $record[ 'unknown_count' ] )		? $record[ 'unknown_count' ]	: 0;
					Supplemental_Item::set_supplemental_item_counts( $event_key, $type_id, $station_id, $fixed, $repairable, $eol, $unknown );
				} // endfor
			} // endif

			// Update the volunteer data
			$event_key = isset( $_POST['sup_data_volunteer_event_key'] ) ? wp_unslash( $_POST['sup_data_volunteer_event_key'] ) : NULL;
			if ( ! empty( $event_key ) ) {

				// Update Fixers
				$fixer_stations = Fixer_Station::get_all_fixer_stations();
				$fixer_head_count = isset( $_POST[ 'fixer_head_count' ] ) ? $_POST[ 'fixer_head_count' ] : array();
				// Update each of the known fixer stations
				foreach ( $fixer_stations as $station ) {
					$id = $station->get_id();
					$head_count = isset( $fixer_head_count[ $id ] ) ? $fixer_head_count[ $id ] : 0;
					Supplemental_Volunteer_Registration::set_supplemental_fixer_count( $event_key, $id, $head_count );
				} // endfor

				//Update any unknown fixer station count
				$id = Fixer_Station::UNSPECIFIED_FIXER_STATION_ID;
				$head_count = isset( $fixer_head_count[ $id ] ) ? $fixer_head_count[ $id ] : 0;
				Supplemental_Volunteer_Registration::set_supplemental_fixer_count( $event_key, $id, $head_count );


				// Update Non-fixers
				$roles = Volunteer_Role::get_all_volunteer_roles();
				$volunteer_head_count = isset( $_POST[ 'non_fixer_head_count' ] ) ? $_POST[ 'non_fixer_head_count' ] : array();
				// Update each of the known volunteer roles
				foreach ( $roles as $volunteer_role ) {
					$id = $volunteer_role->get_id();
					$head_count = isset( $volunteer_head_count[ $id ] ) ? $volunteer_head_count[ $id ] : 0;
					Supplemental_Volunteer_Registration::set_supplemental_non_fixer_count( $event_key, $id, $head_count );
				} // endfor

				//Update any unknown volunteer role count
				$id = Volunteer_Role::UNSPECIFIED_VOLUNTEER_ROLE_ID;
				$head_count = isset( $volunteer_head_count[ $id ] ) ? $volunteer_head_count[ $id ] : 0;
				Supplemental_Volunteer_Registration::set_supplemental_non_fixer_count( $event_key, $id, $head_count );

			} // endif

		} // endif

	} // function

} // class