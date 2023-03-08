<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Error_Log;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteers
 *
 * @since v0.1.0
 *
 */
class Volunteer_Admin_Controller {

	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

		// Add an action listener for post delete so that I can clean up any orphaned volunteer side table records
		add_action( 'before_delete_post', array( __CLASS__, 'handle_before_delete_post' ), 10, 2 );

	} // function

	/**
	 * Handle a post save event for my post type
	 *
	 * @param	int			$post_id	The ID of the post being saved
	 * @param	\WP_Post	$post		The post being saved
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function handle_post_save( $post_id, $post, $is_update ) {
		$curr_status = $post->post_status;
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $curr_status == 'trash' ) {
			// Don't do anything during an autosave or if the post is being trashed
			return;
		} else {
			$volunteer = Volunteer::get_volunteer_by_id( $post_id );
			if ( ! empty( $volunteer ) ) {

				// Update the public profile setting
				if ( isset( $_POST[ 'volunteer_is_public_selection_flag' ] ) ) {
					$val = isset( $_POST[ 'volunteer_public_profile' ] ) ? $_POST[ 'volunteer_public_profile' ] : 'FALSE';
					$new_status = ( $val == 'TRUE' ) ? 'publish' : 'private';
					// I need to remove the action to call this method so it does not loop infinitely
					remove_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );
					if ( $is_update ) {
						// Change the status of this post if we're doing an updated
						$args = array(
							'ID' 			=> $post_id,
							'post_status'	=> $new_status
						);
					} else {
						// If we're not doing an update then let the status be draft
						$args = array(
							'ID' 			=> $post_id,
							'post_status'	=> 'draft'
						);
					} // endif
					wp_update_post( $args );
				} // endif

				// Update the fixer station
				if ( isset( $_POST[ 'fixer_station_selection_flag' ] ) ) {
					$fixer_station_id = isset( $_POST[ 'fixer_station' ] ) ? $_POST[ 'fixer_station' ] : 0;
					$fixer_station = isset( $fixer_station_id ) ? Fixer_Station::get_fixer_station_by_id( $fixer_station_id ) : NULL;
					$volunteer->set_preferred_fixer_station( $fixer_station );
					$is_apprentice = ( isset( $_POST[ 'is_apprentice' ] ) && isset( $fixer_station ) ) ? TRUE : FALSE;
					$volunteer->set_is_fixer_apprentice( $is_apprentice );
				} // endif

				// Update the roles
				if ( isset( $_POST[ 'volunteer_role_selection_flag' ] ) ) {
					$role_ids = isset( $_POST[ 'volunteer_role' ] ) ? $_POST[ 'volunteer_role' ] : array();
					$new_roles = array();
					foreach ( $role_ids as $role_id ) {
						$role = Volunteer_Role::get_volunteer_role_by_id( $role_id );
						if ( isset( $role ) ) {
							$new_roles[] = $role;
						} // endif
					} // endfor
					$volunteer->set_preferred_roles( $new_roles );
				} // endif

				// Update the personal info
				if ( isset( $_POST[ 'volunteer_details_input_flag' ] ) ) {

					// Full name and email
					$full_name = isset( $_POST[ 'volunteer_full_name' ] ) ? stripslashes( trim( $_POST[ 'volunteer_full_name' ] ) ) : NULL;
					$email = isset( $_POST[ 'volunteer_email' ] ) ? stripslashes( trim( $_POST[ 'volunteer_email' ] ) ) : NULL;
					$volunteer->set_personal_info( $full_name, $email );

				} // endif

				// Update the proxy
				if ( isset( $_POST[ 'volunteer_proxy_input_flag' ] ) ) {

					//Proxy
					$proxy_id = isset( $_POST[ 'volunteer_proxy' ] ) ? $_POST[ 'volunteer_proxy' ] : NULL;
					$volunteer->set_my_proxy_volunteer_id( $proxy_id );

				} // endif

			} // endif
		} // endif
	} // function

	/**
	 * Modify the query to based on the filter settings.
	 * This is called during the pre_get_posts action hook.
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) && ( $post_type == Volunteer::POST_TYPE ) ) {

			// Filter the taxonomies as necessary
			$tax_query_array = array();
			$tax_name_array = array( Fixer_Station::TAXONOMY_NAME, Volunteer_Role::TAXONOMY_NAME );

			foreach ( $tax_name_array as $tax_name ) {
				$selected = isset( $_REQUEST[ $tax_name ] ) ? $_REQUEST[ $tax_name ] : 0;
				if ( ! empty( $selected ) ) {
					$tax_query_array[] =
							array(
									'taxonomy'	=> $tax_name,
									'field'		=> 'slug',
									'terms'		=> array( $selected )
							);
				} // endif
			} // endfor

			if ( ! empty( $tax_query_array ) ) {
				$query->query_vars[ 'tax_query' ] = $tax_query_array;
			} // endif

		} // endif

		return $query;

	} // function

	/**
	 * Handle cleanup when deleting my custom post and make sure there are no orphaned volunteer side table records
	 * @param	int			$post_id	The ID of the post being deleted
	 * @param	\WP_Post	$post		The post being deleted
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function handle_before_delete_post( $post_id, $post ) {
		if ( $post->post_type === Volunteer::POST_TYPE ) {
			$volunteer = Volunteer::get_volunteer_by_id( $post_id );
			if ( isset( $volunteer ) ) {
				$volunteer->delete_personal_info();
			} // endif
		} // endif
	} // function

} // class