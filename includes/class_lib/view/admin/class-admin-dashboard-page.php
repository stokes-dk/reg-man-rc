<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\Model\Error_Log;

/**
 * The administrative view for initializing the plugin
 *
 * @since	v0.1.0
 *
 */
class Admin_Dashboard_Page {
	const PAGE_SLUG = 'reg-man-rc-admin-dashboard';

	private $notification_count;

	private function __constructor() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Register this view
	 */
	public static function register() {

		$view = self::create();

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		$notice_count = $view->get_notification_count();
		if ( $notice_count > 0 ) {
			add_action( 'admin_notices', array( $view, 'render_admin_notices' ) );
		} // endif

	} // function

	/**
	 * Render the contents for this page.
	 * This is called when the admin menu page is created.
	 * @see	Admin_Menu_Page
	 */
	public function render_page_content() {
		echo '<div class="wrap">';
			$heading = __( 'Registration Manager for Repair Café', 'reg-man-rc' );
			echo "<h1 class=\"wp-heading-inline\">$heading</h1>";
			echo '<hr class="wp-header-end">';

			echo '<div class="reg-man-rc-dashboard-container">';
				$this->render_tabs();
			echo '</div>';
		echo '</div>';
	} // function

	private function render_tabs() {

		$events_title 	= esc_html__( 'All Events', 'reg-man-rc' );
//		$data_title 	= esc_html__( 'Event Data', 'reg-man-rc' );

		$format = '<li class="reg-man-rc-tab-list-item"><a href="#tab-%1$s"><i class="dashicons dashicons-%3$s"></i><span>%2$s</span></a></li>';
		echo '<div class="reg-man-rc-tabs-container">';
			echo '<ul>';
				printf( $format, 'events',	$events_title,	'calendar-alt' );
//				printf( $format, 'data',	$data_title,	'database' );
			echo '</ul>';
			echo '<div id="tab-events" class="tab-panel" data-name="events">';
				self::render_events();
			echo '</div>';
//			echo '<div id="tab-events" class="tab-panel" data-name="events">';
//				self::render_data();
//			echo '</div>';
		echo '</div>';
	} // function

	/**
	 * Render admin notices
	 */
	public function render_admin_notices() {
		global $pagenow;
		if ( $pagenow === 'admin.php' ) {
			$slugs = array( Admin_Menu_Page::MENU_SLUG, self::PAGE_SLUG );
			$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
			if ( in_array( $page, $slugs ) ) {
				if ( Event_Category_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_event_category_dashboard_notice();
				} // endif
				if ( Item_Type_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_item_types_dashboard_notice();
				} // endif
				if ( Fixer_Station_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_fixer_station_dashboard_notice();
				} // endif
				if ( Volunteer_Role_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_volunteer_roles_dashboard_notice();
				} // endif
				if ( Item_Suggestion_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_item_suggestions_dashboard_notice();
				} // endif
			} // endif
		} // endif
	} // function

	private function render_event_category_dashboard_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No event categories are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Event_Category::get_admin_url();
			$text = __( 'Manage event categories', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_fixer_station_dashboard_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No fixer stations are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Fixer_Station::get_admin_url();
			$text = __( 'Manage fixer stations', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_volunteer_roles_dashboard_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No volunteer roles are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Volunteer_Role::get_admin_url();
			$text = __( 'Manage volunteer roles', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_item_types_dashboard_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No item types are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Item_Type::get_admin_url();
			$text = __( 'Manage item types', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_item_suggestions_dashboard_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No item suggestions are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Item_Suggestion::get_admin_url();
			$text = __( 'Manage item suggestions', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	/**
	 * Return a count of notifications shown on the dashboard
	 */
	public function get_notification_count() {
		if ( ! isset( $this->notification_count ) ) {
			$this->notification_count = 0;
/* FIXME - removing this because we have exactly ONE default event category and it's created automatically
			if ( Event_Category_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
*/
			if ( Fixer_Station_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
			if ( Volunteer_Role_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
			if ( Item_Type_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
			if ( Item_Suggestion_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
		} // endif
		return $this->notification_count;
	} // function

	private function render_events() {
		echo '<div class="reg-man-rc-dashboard-calendar-container">';
			$calendar = Calendar::get_admin_calendar();
			$view = Calendar_View::create( $calendar );
			$view->render();
		echo '</div>';
	} // function

	private function render_data() {
		echo '<div class="reg-man-rc-dashboard-data-page-container">';
			echo 'data!';
		echo '</div>';
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts( $hook_suffix ) {
		global $pagenow;
		if ( $pagenow === 'admin.php' ) {
			$slugs = array( Admin_Menu_Page::MENU_SLUG, self::PAGE_SLUG );
			$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
			if ( in_array( $page, $slugs ) ) {
				Scripts_And_Styles::enqueue_base_admin_script_and_styles();
				Scripts_And_Styles::enqueue_ajax_forms();
				Scripts_And_Styles::enqueue_fullcalendar();
				Scripts_And_Styles::enqueue_google_maps();
			} // endif
		} // endif
	} // function


} // function