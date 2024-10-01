<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Ajax_Form_Response;
use Reg_Man_RC\View\Admin\Remove_Volunteer_Form;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteers
 *
 * @since v0.1.0
 *
 */
class Volunteer_Admin_Controller {
	
	const GET_REMOVE_FORM_CONTENT	= 'reg_man_rc_volunteer_get_remove_form_content';
	const REMOVE_VOLUNTEER			= 'reg_man_rc_remove_volunteer';
	
	/**
	 * Register this view
	 */
	public static function register() {

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );
		
		add_action( 'wp_ajax_' . self::GET_REMOVE_FORM_CONTENT, array( __CLASS__, 'get_remove_form_content' ) );

	} // function

	
	/**
	 * Handle an AJAX request to get the remove form content for a specific record.
	 *
	 * @return	void
	 */
	public static function get_remove_form_content() {

		$form_response = Ajax_Form_Response::create();
		
		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_REQUEST[ 'formData' ] ) ? $_REQUEST[ 'formData' ] : NULL;
		$form_data = array();
		parse_str( $serialized_form_data, $form_data );

		$nonce				= isset( $form_data[ '_wpnonce' ] )		? $form_data[ '_wpnonce' ] : '';
		$record_id			= isset( $form_data[ 'record-id' ] )	? $form_data[ 'record-id' ] : NULL;
		
//		Error_Log::var_dump( $form_data, $nonce, $record_id );
		$is_valid_nonce = wp_verify_nonce( $nonce, self::GET_REMOVE_FORM_CONTENT );
		$volunteer = Volunteer::get_volunteer_by_id( $record_id );
		
		if ( ! $is_valid_nonce || empty( $volunteer ) ) {

			if ( ! $is_valid_nonce ) {
				$err_msg = __( 'Your security token has expired.  Please refresh the page and try again.', 'reg-man-rc' );
				$form_response->add_error( '_wpnonce', '', $err_msg );
			} // endif

			if ( empty( $volunteer ) ) {
				if ( empty( $record_id ) ) {
					$err_msg = sprintf( __( 'No volunteer ID was specified.', 'reg-man-rc' ), $record_id );
					$form_response->add_error( 'record-id', '', $err_msg );
				} else {
					/* Translators: %1$s is a volunteer ID */
					$err_msg = sprintf( __( 'The volunteer could not be found with ID %1$s.', 'reg-man-rc' ), $record_id );
					$form_response->add_error( 'record-id', '', $err_msg );
				} // endif
			} // endif

		} else {
			
			// TODO: This is work in progress to replace "Trash"
			$view = Remove_Volunteer_Form::create( $volunteer );
			$content = $view->get_remove_form_content();
			
			$form_response->set_html_data( $content );
			
		} // endif
		
		echo json_encode( $form_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!
		
	} // class
	
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

				// Update the post status (privacy setting)
				$new_status = 'private'; // Always private status
				
				// I need to remove this action from save_post to avoid an infinite loop
				remove_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

				// Modify the post status
				wp_update_post( array(
					'ID' 			=> $post_id,
					'post_status'	=> $new_status
				) );
				
				// Add the action back
				add_action( 'save_post_' . Volunteer::POST_TYPE, array( __CLASS__, 'handle_post_save' ), 10, 3 );
					
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

} // class