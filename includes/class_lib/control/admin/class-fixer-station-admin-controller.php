<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Supplemental_Item;
use Reg_Man_RC\Model\Stats\Supplemental_Volunteer_Registration;

/**
 * The fixer station controller used in the admin backend
 *
 * This class provides the controller function for working with fixer stations
 *
 * @since v0.1.0
 *
 */
class Fixer_Station_Admin_Controller {

	const CREATE_DEFAULTS_FORM_ACTION	= 'reg-man-rc-create-default-fixer-stations-action';


	/**
	 * Register this controller.
	 *
	 * This method is called by the Plugin_Controller during initialization.
	 *
	 * @since v0.1.0
	 */
	public static function register() {

		// Add handler methods for my form posts.
		// Handle create defaults form post
		add_action( 'admin_post_' . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_create_defaults_priv') );
		add_action( 'admin_post_nopriv_'  . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_post_no_priv') );

		// Save the field value when the new term is created using "created_" hook
		add_action( 'created_' . Fixer_Station::TAXONOMY_NAME, array( __CLASS__, 'save_new_term_fields' ), 10, 2 );

		// Save the field value when the term is updated using "edited_" hook
		add_action( 'edited_' . Fixer_Station::TAXONOMY_NAME, array(__CLASS__, 'update_term_admin_fields' ), 10, 2 );

		// Handle the event when a term is deleted using "delete_" hook
		add_action( 'delete_' . Fixer_Station::TAXONOMY_NAME, array(__CLASS__, 'handle_delete_term'), 10, 4 );

	} // function

	/**
	 * Handle delete term event for my taxonomy
	 * @param	int			$term_id
	 * @param	int			$term_taxonomy_id
	 * @param	\WP_Term	$deleted_term
	 * @param	array		$object_ids
	 */
	public static function handle_delete_term( $term_id, $term_taxonomy_id, $deleted_term, $object_ids ) {
//		Error_Log::var_dump( $term_id, $term_taxonomy_id, $deleted_term, $object_ids );
		Supplemental_Item::handle_fixer_station_deleted( $term_id );
		Supplemental_Volunteer_Registration::handle_fixer_station_deleted( $term_id );
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
				Settings::set_is_object_type_init_skipped( Fixer_Station::TAXONOMY_NAME, TRUE );
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
		$icon_id_array =	isset( $_POST[ 'icon_id' ] ) 	? $_POST[ 'icon_id' ]	: array();
		$colour_array =		isset( $_POST[ 'colour' ] ) 	? $_POST[ 'colour' ]	: array();
//		Error_Log::var_dump( $create_array, $name_array, $desc_array, $icon_id_array, $colour_array );

		foreach( $create_array as $index => $create_val ) {
			if ( $create_val == '1' ) {
				$name = isset( $name_array[ $index ] ) ? trim( $name_array[ $index ] ) : NULL;
				if ( ! empty( $name ) ) {
					// Do not create a duplicate of an existing categories
					$existing = Fixer_Station::get_fixer_station_by_name( $name );
					if ( empty( $existing ) ) {
						$desc = isset( $desc_array[ $index ] ) ? trim( $desc_array[ $index ] ) : NULL;
						$colour = isset( $colour_array[ $index ] ) ? trim( $colour_array[ $index ] ) : NULL;
						$icon_id = isset( $icon_id_array[ $index ] ) ? trim( $icon_id_array[ $index ] ) : NULL;
						$station = Fixer_Station::create_fixer_station( $name, $desc, $colour );
						if ( isset( $station ) ) {
							$station->set_icon_image_attachment_id_array( array( $icon_id ) );
						} // endif
					} // endif
				} // endif
			} // endif
		} // endfor

	} // function


	public static function save_new_term_fields( $term_id, $term_taxonomy_id ) {
		$fixer_station = Fixer_Station::get_fixer_station_by_id( $term_id );
		if ( ! empty( $fixer_station ) ) {

			$icon_flag = isset( $_POST[ 'fixer-station-icon-selection' ] );
			if ( $icon_flag ) {
				$attach_id_array = isset( $_POST[ 'media-library-attachment-id' ] ) ? $_POST['media-library-attachment-id'] : NULL;
				$fixer_station->set_icon_image_attachment_id_array( $attach_id_array );
			} // endif

			$colour = isset( $_POST['fixer-station-colour'] ) ? trim( $_POST['fixer-station-colour'] ) : NULL;
			$fixer_station->set_colour( $colour );

		} // endif

	} // function

	/**
	 * Update the fixer station based on the data in the POST.
	 *
	 * This method is triggered by the edited_{$taxomony} action.
	 *
	 * @since v0.1.0
	 */
	public static function update_term_admin_fields( $term_id, $term_taxonomy_id ) {
		$fixer_station = Fixer_Station::get_fixer_station_by_id( $term_id );
		if ( ! empty( $fixer_station ) ) {

			$icon_flag = isset( $_POST[ 'fixer-station-icon-selection' ] );
			if ( $icon_flag ) {
				$attach_id_array = isset( $_POST[ 'media-library-attachment-id' ] ) ? $_POST['media-library-attachment-id'] : NULL;
				$fixer_station->set_icon_image_attachment_id_array( $attach_id_array );
			} // endif

			$colour = isset( $_POST['fixer-station-colour'] ) ? trim( $_POST['fixer-station-colour'] ) : NULL;
			if ( isset( $colour ) ) {
				// If no colour is selected then just leave it as-is
				$fixer_station->set_colour( $colour );
			} // endif

			$names_text = isset( $_POST[ 'fixer-station-ext-names' ] ) ? trim( $_POST[ 'fixer-station-ext-names' ] ) : NULL;
			if ( isset( $names_text ) ) {
				$names_array = explode( '|', $names_text );
				$trimmed_names = array();
				foreach ( $names_array as $name ) {
					$trimmed_name = trim( $name );
					if ( ! empty( $trimmed_name ) ) {
						$trimmed_names[] = $trimmed_name;
					} // endif
				} // endfor
				$fixer_station->set_external_names( $trimmed_names );
			} // endif

		} // endif
	} // function
} // class