<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Settings;

/**
 * The item controller
 *
 * This class provides the controller function for working with items
 *
 * @since v0.1.0
 *
 */
class Item_Suggestion_Admin_Controller {

	const CREATE_DEFAULTS_FORM_ACTION	= 'reg-man-rc-create-default-item-suggestions-action';

	public static function register() {

		// Add handler methods for my form posts.
		// Handle create defaults form post
		add_action( 'admin_post_' . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_create_defaults_priv') );
		add_action( 'admin_post_nopriv_'  . self::CREATE_DEFAULTS_FORM_ACTION, array(__CLASS__, 'handle_post_no_priv') );

		// Add an action hook to upate our custom fields when a post is saved
		add_action( 'save_post_' . Item_Suggestion::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );

		// Check to make sure that an item type is selected and show an error message if not
		add_action( 'edit_form_top', array( __CLASS__, 'show_required_field_error_msg' ) );

		// Tell WP how to do the filtering for my taxonomies
		add_action( 'pre_get_posts', array( __CLASS__, 'modify_query_for_filters' ) );

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
				Settings::set_is_object_type_init_skipped( Item_Suggestion::POST_TYPE, TRUE );
				break;
		} // endswitch

		// Send them back to the same page when we're done
		$ref_page = $_SERVER['HTTP_REFERER']; // The referer page is where the request came from
		header( "Location: $ref_page" );
	} // function

	private static function create_defaults() {
		$group_types =		isset( $_POST[ 'group_default_item_type' ] )		? $_POST[ 'group_default_item_type' ]		: array();
		$group_stations =	isset( $_POST[ 'group_default_fixer_station' ] )	? $_POST[ 'group_default_fixer_station' ]	: array();
		Error_Log::var_dump( $group_types, $group_stations );

		$create_array =		isset( $_POST[ 'create' ] )			? $_POST[ 'create' ]		: array();
//		$group_name_array =	isset( $_POST[ 'group_name' ] )		? $_POST[ 'group_name' ]	: array();
		$name_array =		isset( $_POST[ 'name' ] )			? $_POST[ 'name' ]			: array();
		$alt_names_array =	isset( $_POST[ 'alt_names' ] )		? $_POST[ 'alt_names' ]		: array();
//		$item_type_array =	isset( $_POST[ 'item_type' ] )	 	? $_POST[ 'item_type' ]		: array();
//		$station_array =	isset( $_POST[ 'fixer_station' ] ) 	? $_POST[ 'fixer_station' ]	: array();
//		Error_Log::var_dump( $create_array, $group_name_array, $name_array, $alt_names_array );

		// Note that the group name is the value of the create checkbox
		foreach( $create_array as $index => $group_name ) {
			if ( ! empty( $group_name ) ) {
				$desc 			= isset( $name_array[ $index ] )			? trim( $name_array[ $index ] ) 		: NULL;
				$alt_desc		= isset( $alt_names_array[ $index ] )		? trim( $alt_names_array[ $index ] )	: NULL;
				$type_id		= isset( $group_types[ $group_name ] )		? $group_types[ $group_name ]			: NULL;
				$station_id		= isset( $group_stations[ $group_name ] )	? $group_stations[ $group_name ]		: NULL;
//				Error_Log::var_dump( $desc, $alt_desc, $type_id, $station_id );
				$item_type = Item_Type::get_item_type_by_id( $type_id );
				$station = Fixer_Station::get_fixer_station_by_id( $station_id );
				$suggestion = Item_Suggestion::create_new_item_suggestion( $desc, $alt_desc, $item_type, $station );
			} // endif
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
			$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post_id );
			if ( ! empty( $item_suggestion ) ) {

				// If there are any missing required settings then I will set this post to DRAFT status later
				$set_post_status_draft = FALSE;

				// Note that we update the alternate descriptions are already updated at this point because
				//  the input is named 'post_content' so it is saved automatically by Wordpress

				// Update the item type for this item.  An item type is required
				if ( isset( $_POST[ 'item_type_input_flag' ] ) ) {
					$item_type = isset( $_POST['item_type'] ) ? Item_Type::get_item_type_by_id( $_POST['item_type'] ) : NULL;
					if ( empty( $item_type ) ) {
						$item_suggestion->set_item_type( NULL );
						$set_post_status_draft = TRUE;
					} else {
						$item_suggestion->set_item_type( $item_type );
					} // endif
				} // endif

				// update the fixer station if one is selected
				if ( isset( $_POST[ 'fixer_station_selection_flag' ] ) ) {
					$fixer_station_id = isset( $_POST['fixer_station'] ) ? $_POST['fixer_station'] : '';
					$station = Fixer_Station::get_fixer_station_by_id( $fixer_station_id );
					$item_type = $item_suggestion->get_item_type();
					if ( empty( $station ) ) {
						$item_suggestion->set_fixer_station( NULL );
						$set_post_status_draft = TRUE;
					} else {
						$item_suggestion->set_fixer_station( $station );
					} // endif
				} // endif

				if ( $set_post_status_draft ) {
					// Unhook this function so it doesn't loop infinitely
					remove_action( 'save_post_' . Item_Suggestion::POST_TYPE, array( __CLASS__, 'handle_post_save' ) );
					// Set the post status to draft since it doesn't have a valid item type
					$post_data = array(
						'ID'		  => $post_id,
						'post_status' => 'draft',
					);
					wp_update_post( $post_data );
				} // endif

			} // endif

		} // endif

	} // function

	/**
	 * Display a message at the top of the form if there is a missing required field
	 *
	 * @param	\WP_Post	$post	The ID of the post being saved
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function show_required_field_error_msg( $post ) {
		if ( Item_Suggestion::POST_TYPE === get_post_type( $post ) && 'auto-draft' !== get_post_status( $post ) ) {

			$item_suggestion = Item_Suggestion::get_item_suggestion_by_id( $post->ID );

			if ( ! empty( $item_suggestion ) ) {

				$error_format = '<div class="error below-h2"><p>%s</p></div>';
				
				if ( empty( $item_suggestion->get_item_type() ) && ! empty( Item_Type::get_all_item_types() ) ) {
					printf( $error_format, esc_html__( __( 'Item type is required.', 'reg-man-rc' ) ) );
				} // endif

				if ( empty( $item_suggestion->get_fixer_station() ) ) {
					printf( $error_format, esc_html__( __( 'Fixer station is required.', 'reg-man-rc' ) ) );
				} // endif

			} // endif

		} // endif
	} // function

	/**
	 * Modify the query to show only events with the specified terms
	 * This is called during the pre_get_posts action hook.
	 * @param	\WP_Query	$query
	 * @return	\WP_Query	$query
	 */
	public static function modify_query_for_filters( $query ) {
		global $pagenow;
		$post_type = isset( $query->query['post_type'] ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query()  && ( $pagenow == 'edit.php' ) &&
			 ( $post_type == Item_Suggestion::POST_TYPE ) ) {

			// Filter the taxonomies as necessary
			$tax_query_array = array();

			// Filter by Item Type
			$type_tax_name = Item_Type::TAXONOMY_NAME;
			$selected_slug = isset( $_REQUEST[ $type_tax_name ] ) ? $_REQUEST[ $type_tax_name ] : 0;
			if ( ! empty( $selected_slug ) ) {
				$tax_query_array[] =
						array(
								'taxonomy'	=> $type_tax_name,
								'field'		=> 'slug',
								'terms'		=> array( $selected_slug )
						);
			} // endif

//			Error_Log::var_dump( $tax_query_array );
//			Error_Log::var_dump( $query );

			if ( ! empty( $tax_query_array ) ) {
				$query->query_vars[ 'tax_query' ] = $tax_query_array;
			} // endif

		} // endif

		return $query;

	} // function

} // class