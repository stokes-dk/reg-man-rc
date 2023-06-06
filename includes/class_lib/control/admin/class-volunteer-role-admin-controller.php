<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Term_Order_Controller;

/**
 * The volunteer controller
 *
 * This class provides the controller function for working with volunteer roles
 *
 * @since v0.1.0
 *
 */
class Volunteer_Role_Admin_Controller {

	const CREATE_DEFAULTS_FORM_ACTION	= 'reg-man-rc-create-default-volunteer-roles-action';

	public static function register() {

		// Handle create defaults form post
		add_action( 'admin_post_' . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_create_defaults_priv') );
		add_action( 'admin_post_nopriv_'  . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_post_no_priv') );

		// Save the field value when the new term is created using "created_" hook
		add_action( 'created_' . Volunteer_Role::TAXONOMY_NAME, array( __CLASS__, 'save_new_term_fields' ), 10, 2 );

		// Save the field value when the term is updated using "edited_" hook
		add_action( 'edited_' . Volunteer_Role::TAXONOMY_NAME, array(__CLASS__, 'update_term_admin_fields'), 10, 2 );

	} // function

	/**
	 * Handle non-logged-in user attempting to post.
	 * We will just return them to the referring page which will require login
	 */
	public static function handle_post_no_priv() {
		// You must be logged in to post data.  This person is not logged in (hence ...NoPriv)
		//	so just send them back to where they came from (the form page) it will require login
		$ref_page = $_SERVER[ 'HTTP_REFERER' ]; // The referer page is where the request came from
		header( "Location: $ref_page" );
	} // function

	/**
	 * Handle a logged-in user posting a request to create the defaults for this taxonomy
	 */
	public static function handle_create_defaults_priv() {
		// This user is logged in so go ahead and handle this request
//		Error_Log::var_dump( $_POST );
		$button_action = isset( $_POST[ 'button_action' ] ) ? $_POST[ 'button_action' ] : '';
//		$create_index_array = isset( $_POST[ 'create_index' ] ) ? $_POST[ 'create_index' ] : array();
//		Error_Log::var_dump( $button_action, $create_index_array );

		switch ( $button_action ) {
			case 'create':
				self::create_defaults();
				break;
			case 'skip':
				Settings::set_is_object_type_init_skipped( Volunteer_Role::TAXONOMY_NAME, TRUE );
				break;
		} // endswitch

		// Send them back to the same page when we're done
		$ref_page = $_SERVER['HTTP_REFERER']; // The referer page is where the request came from
		header( "Location: $ref_page" );
	} // function

	private static function create_defaults() {
		$create_array =		isset( $_POST[ 'create' ] )		? $_POST[ 'create' ]	: array();
		$name_array =		isset( $_POST[ 'name' ] )		? $_POST[ 'name' ]		: array();
		$desc_array =		isset( $_POST[ 'desc' ] )		? $_POST[ 'desc' ]		: array();
		$colour_array =		isset( $_POST[ 'colour' ] ) 	? $_POST[ 'colour' ]	: array();
//		Error_Log::var_dump( $create_array, $name_array, $desc_array, $colour_array );
		$order_index = 1; // assign the order
		foreach( $create_array as $index => $create_val ) {
			if ( $create_val == '1' ) {
				$name = isset( $name_array[ $index ] ) ? trim( $name_array[ $index ] ) : NULL;
				if ( ! empty( $name ) ) {
					// Do not create a duplicate of an existing term
					$existing = Volunteer_Role::get_volunteer_role_by_name( $name );
					if ( empty( $existing ) ) {
						$desc = isset( $desc_array[ $index ] ) ? trim( $desc_array[ $index ] ) : NULL;
						$colour = isset( $colour_array[ $index ] ) ? trim( $colour_array[ $index ] ) : NULL;
						$role = Volunteer_Role::create_volunteer_role( $name, $desc, $colour );
						if ( isset( $role ) ) {
							$term_id = $role->get_id();
							Term_Order_Controller::set_term_order_index( $term_id, $order_index++ );
						} // endif
					} // endif
				} // endif
			} // endif
		} // endfor
		if ( $order_index > 1 ) {
			Term_Order_Controller::set_is_taxonomy_using_custom_order( Volunteer_Role::TAXONOMY_NAME, TRUE );
		} // endif
	} // function


	public static function save_new_term_fields( $term_id, $term_taxonomy_id ) {
		$volunteer_role = Volunteer_Role::get_volunteer_role_by_id( $term_id );

		if ( ! empty( $volunteer_role ) ) {

			$colour = isset( $_POST['volunteer-role-colour'] ) ? trim( $_POST['volunteer-role-colour'] ) : NULL;
			$volunteer_role->set_colour( $colour );

		} // endif
	} // function

	public static function update_term_admin_fields( $term_id, $term_taxonomy_id ) {
		$volunteer_role = Volunteer_Role::get_volunteer_role_by_id( $term_id );

		if ( ! empty( $volunteer_role ) ) {

			$colour = isset( $_POST['volunteer-role-colour'] ) ? trim( $_POST['volunteer-role-colour'] ) : NULL;
			if ( isset( $colour ) ) {
				// If no colour is selected then just leave it as-is
				$volunteer_role->set_colour( $colour );
			} // endif

			$names_text = isset( $_POST[ 'volunteer-role-ext-names' ] ) ? trim( $_POST[ 'volunteer-role-ext-names' ] ) : NULL;
			if ( isset( $names_text ) ) {
				$names_array = explode( '|', $names_text );
				$trimmed_names = array();
				foreach ( $names_array as $name ) {
					$trimmed_name = trim( $name );
					if ( ! empty( $trimmed_name ) ) {
						$trimmed_names[] = $trimmed_name;
					} // endif
				} // endfor
				$volunteer_role->set_external_names( $trimmed_names );
			} // endif

		} // endif
	} // function

} // class