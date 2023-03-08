<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Internal_Event_Descriptor;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Visitor;

/**
 * The controller for the block editor (Gutenberg) in the administrative interface.
 *
 * This class provides the controller function for determining whether the block editor (or classic editor) is used.
 *
 * @since v0.1.0
 *
 */
class Block_Editor_Admin_Controller {

	public static function register() {
		if ( is_admin() ) {
			if ( Settings::get_is_use_block_editor() !== NULL ) {
				// Add a filter hook to determine whether the block editor is used for various post types
				add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'get_use_block_editor_for_post_type' ), 10, 2 );
			} // endif

			// Add a filter to remove the default taxonomy metaboxes in the Block Editor
			// Note that Gutenberg ignores the meta_box_cb = FALSE setting and inserts a metabox anyway
			// So I have to add this filter to remove it
			add_filter( 'rest_prepare_taxonomy', array( __CLASS__, 'handle_rest_prepare_taxonomy' ), 10, 3 );

		} // endif
	} // function

	/**
	 * Add my settings fields for permalinks
	 *
	 * @return	void
	 *
	 * @since v0.1.0
	 *
	 */
	public static function get_use_block_editor_for_post_type( $is_use_block_editor, $post_type ) {
		$my_post_types = array(
				Calendar::POST_TYPE,
				Internal_Event_Descriptor::POST_TYPE,
				Item::POST_TYPE,
				Venue::POST_TYPE,
				Visitor::POST_TYPE,
				Volunteer::POST_TYPE,
				Volunteer_Registration::POST_TYPE,
		);
		if ( in_array( $post_type, $my_post_types ) ) {
			$setting = Settings::get_is_use_block_editor();
			$is_use_block_editor = ( $setting !== NULL ) ? $setting : $is_use_block_editor;
		} // endif
		return $is_use_block_editor;
	} // function

	/**
	 * Handle the rest_prepare_taxonomy filter to allow me to hide meta boxes that should not be there
	 * @param	\WP_REST_Response	$response
	 * @param	\WP_Taxonomy		$taxonomy
	 * @param	\WP_REST_Request	$request
	 * @return unknown
	 */
	public static function handle_rest_prepare_taxonomy( $response, $taxonomy, $request ) {
		$my_taxonomies = array(
				Event_Category::TAXONOMY_NAME,
				Fixer_Station::TAXONOMY_NAME,
				Item_Type::TAXONOMY_NAME,
				Volunteer_Role::TAXONOMY_NAME,
		);
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		// Context is edit in the editor
		if( ( $context === 'edit' ) && in_array( $taxonomy->name, $my_taxonomies ) && ( $taxonomy->meta_box_cb === FALSE ) ) {
			$data_response = $response->get_data();
			$data_response['visibility']['show_ui'] = FALSE;
			$response->set_data( $data_response );
		} // endif
		return $response;
	} // function

} // class