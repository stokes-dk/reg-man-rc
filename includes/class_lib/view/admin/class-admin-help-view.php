<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item_Suggestion;

/**
 * The administrative view for event category
 *
 * @since	v0.1.0
 *
 */
class Admin_Help_View {

	public static function register() {

		// Use the load action to put "Help" tabs in the right places
		add_action( 'load-edit.php', array( __CLASS__, 'handle_load_edit') );
		add_action( 'load-edit-tags.php', array( __CLASS__, 'handle_load_edit') );

	} // function

	private static function get_current_screen_type_name( $screen = NULL ) {
		if ( empty( $screen ) ) {
			$screen = get_current_screen();
		} // endif
		switch( $screen->base ) {
			case 'edit':
				$type_name = $screen->post_type;
				break;
			case 'edit-tags':
				$type_name = $screen->taxonomy;
				break;
			default:
				$type_name = NULL;
		} // endswitch
		return $type_name;
	} // function

	public static function handle_load_edit() {
		$screen = get_current_screen();
		$type_name = self::get_current_screen_type_name( $screen );
		$about_supported_types_array = array(
				Item_Suggestion::POST_TYPE		=> [ Item_Suggestion::class,	'get_about_content' ],
				Event_Category::TAXONOMY_NAME	=> [ Event_Category::class,		'get_about_content' ],
				Item_Type::TAXONOMY_NAME		=> [ Item_Type::class,			'get_about_content' ],
				Fixer_Station::TAXONOMY_NAME	=> [ Fixer_Station::class,		'get_about_content' ],
				Volunteer_Role::TAXONOMY_NAME	=> [ Volunteer_Role::class,		'get_about_content' ],
		);
		if ( array_key_exists( $type_name, $about_supported_types_array ) ) {
			$args = array(
				'id'		=> 'reg-man-rc-about',
				'title'		=> 'About',
				'content'	=> call_user_func( $about_supported_types_array[ $type_name ] ),
			);
//		Error_Log::var_dump( $args );
			$screen->add_help_tab( $args );
		} // endif
	} // function

} // class