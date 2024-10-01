<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Pub\Visitor_Reg_Manager;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Model\Settings;

class Admin_Bar_Controller {
	
	/**
	 * Register this controller
	 * 
	 * @since 0.0.5
	 */
	public static function register() {

		// Filter to hide the admin bar
		add_filter( 'show_admin_bar' , array( __CLASS__, 'filter_show_admin_bar' ), 10, 1 );

		add_action( 'admin_bar_menu', array( __CLASS__, 'handle_admin_bar_menu' ), 200, 1 );
		
	} // function
	
	/**
	 * Handle 'show_admin_bar' filter so we can hide the admin bar for certain users based on their roles and capabilities
	 * @param	boolean	$is_show_admin_bar
	 * @return	boolean
	 * @since 0.0.5
	 */
	public static function filter_show_admin_bar( $is_show_admin_bar ) {

		$user = wp_get_current_user();
		$hide_admin_bar_roles = Settings::get_hide_admin_bar_roles();
//		Error_Log::var_dump( $user->roles, $hide_admin_bar_roles, array_diff( $user->roles, $hide_admin_bar_roles ) );

		// If the user's roles are ONLY those in the array of roles whose admin bar is hidden, then we'll hide it
		if ( empty( array_diff( $user->roles, $hide_admin_bar_roles ) ) ) {
			$result = FALSE;
		} else {
			$result = $is_show_admin_bar;
		} // endif

		return $result;
		
	} // function
	
	/**
	 * Get the default array of roles whose admin bar should be hidden
	 * @return	string[]
	 * @since 0.0.5
	 */
	public static function get_default_hide_admin_bar_roles() {
		return array( User_Role_Controller::VOLUNTEER_ROLE_NAME );
	} // function
	
	/**
	 * Handle 'show_admin_bar' filter so we can hide the admin bar for certain users based on their role capabilities
	 * @param	\WP_Admin_Bar	$wp_admin_bar
	 * 
	 * @since 0.0.5
	 */
	public static function handle_admin_bar_menu( $wp_admin_bar ) {

		// Visitor Registration link
		$visitor_reg_post = Visitor_Reg_Manager::get_post();
		if ( isset( $visitor_reg_post ) && Visitor_Reg_Manager::get_can_current_user_access_visitor_registration_manager() ) {
		
			$id = 'reg_man_rc_admin_bar_visitor_reg_link';
			$parent = 'site-name'; // The main link to the site
			$title = 'Visitor Registration';
			$href = get_page_link( $visitor_reg_post->ID );
			
			$args = array(
					'id'		=> $id,
					'title'		=> $title,
					'parent'	=> $parent,
					'href'		=> $href,
			);
			
			$wp_admin_bar->add_menu( $args );

		} // endif
			
		// Volunteer Area link
		$volunteer_area_post = Volunteer_Area::get_post();
		if ( isset( $volunteer_area_post ) ) {
		
			$id = 'reg_man_rc_admin_bar_volunteer_area_link';
			$parent = 'site-name'; // The main link to the site
			$title = 'Volunteer Area';
			$href = get_page_link( $volunteer_area_post->ID );
			
			$args = array(
					'id'		=> $id,
					'title'		=> $title,
					'parent'	=> $parent,
					'href'		=> $href,
			);
			
			$wp_admin_bar->add_menu( $args );

		} // endif
			
	} // function
	
} // class