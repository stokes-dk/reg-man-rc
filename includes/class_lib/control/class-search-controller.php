<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\View\Pub\Volunteer_Area;

/**
 * The controller for searching
 *
 * This class provides the controller function for searches
 *
 * @since v0.5.0
 *
 */
class Search_Controller {

	/**
	 * Register this controller
	 */
	public static function register() {
		
		// Add action handler for pre_get_posts so that we can remove certain pages or posts from search results
		add_action( 'pre_get_posts', array( __CLASS__, 'handle_pre_get_posts' ) );
		
	} // function

	/**
	 * Handle the pre_get_posts action hook
	 * @param \WP_Query $query
	 * @return \WP_Query
	 */
	public static function handle_pre_get_posts( $query ) {
		
		if ( ! is_admin() && $query->is_main_query() ) {

			if ( $query->is_search ) {
				
				$hide_post_ids_array = array();
				
				if ( Settings::get_is_hide_visitor_registration_page_from_search() ) {
					$hide_post_ids_array[] = Visitor_Reg_Manager::get_post_id();
				} // endif
				
				if ( Settings::get_is_hide_volunteer_area_page_from_search() ) {
					$hide_post_ids_array[] = Volunteer_Area::get_post_id();
				} // endif
				
				if ( ! empty( $hide_post_ids_array ) ) {
					$query->set( 'post__not_in', $hide_post_ids_array );
				} // endif
				
			} // endif

		} // endif
		return $query;
	} // function
	
} // class