<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Fixer_Station;
use Reg_Man_RC\Model\Volunteer_Role;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Object_View\Object_View;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\View\Stats\Ajax_Chart_View;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Control\Admin\Supplemental_Event_Data_Admin_Controller;
use Reg_Man_RC\Model\Stats\Repairs_Chart_Model;
use Reg_Man_RC\Model\Stats\Items_Chart_Model;
use Reg_Man_RC\Model\Stats\Volunteers_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_Chart_Model;

/**
 * The administrative view for initializing the plugin
 *
 * @since	v0.1.0
 *
 */
class Admin_Dashboard_Page {
	const PAGE_SLUG = 'reg-man-rc-admin-dashboard';

	const PAGE_NAME_HOME			= 'home';
	const PAGE_NAME_EVENT			= 'event';
	
	private $curr_page_title; // The title for the current page
	
	private static $PAGE_NAME; // The name of the page currently being viewed
	private static $EVENT; // The event currently being viewed, if one exists
	
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
	 * Get the page name form the current request
	 * @return	string		The name of the page requested
	 */
	private static function get_page_name_from_request() {
		if ( ! isset( self::$PAGE_NAME ) ) {
			$event_key = self::get_event_key_from_request();
			if ( ! empty( $event_key ) ) {
				// If an event is specified then we're on an event page
				self::$PAGE_NAME = self::PAGE_NAME_EVENT;
			} else {
				self::$PAGE_NAME = self::PAGE_NAME_HOME;
			} // endif
		} // endif
		return self::$PAGE_NAME;
	} // function
	
	public static function get_event_from_request() {
		if ( ! isset( self::$EVENT ) ) {
			$event_key = self::get_event_key_from_request();
			self::$EVENT = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
		} // endif
		return self::$EVENT;
	} // function
	
	/**
	 * Get the event key from the current request if one exists, otherwise NULL
	 * @return NULL|string
	 */
	public static function get_event_key_from_request() {
		$key = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
		$result = isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : NULL;
		return $result;
	} // function
	
	
	/**
	 * Render the contents for this page.
	 * This is called when the admin menu page is created.
	 * @see	Admin_Menu_Page
	 */
	public function render_page_content() {
		echo get_site_url();
		echo '<div class="wrap">';
			$heading = __( 'Registration Manager for Repair Café', 'reg-man-rc' );
			echo "<h1 class=\"wp-heading-inline\">$heading</h1>";
			echo '<hr class="wp-header-end">';

			echo '<div class="reg-man-rc-dashboard-container">';
				$this->render_main_content();
			echo '</div>';
		echo '</div>';
	} // function

	
	private function render_main_content() {
		
		echo '<div class="reg-man-rc-admin-dashboard-header">';
			self::render_header();
		echo '</div>';
		
		echo '<div class="reg-man-rc-admin-dashboard-body">';
			$page_name = $this->get_page_name_from_request();
			switch( $page_name ) {
				
				case self::PAGE_NAME_HOME:
				default:
					$this->render_events_calendar();
					break;
					
				case self::PAGE_NAME_EVENT:
					$event = $this->get_event_from_request();
					$this->render_event( $event );
					break;
					
			} // endswitch
		echo '</div>';
		
	} // function
	
	private function render_header() {
		
		// The back to calendar page
		$page_name = $this->get_page_name_from_request();
		if ( $page_name !== self::PAGE_NAME_HOME ) {
			$href = self::get_href_for_main_page();
			echo '<div class="reg-man-rc-admin-dashboard-header-part admin-dashboard-title-back-link">';
				$text = esc_html__( 'Calendar', 'reg-man-rc' );
				$icon = '<span class="admin-dashboard-title-back-link-icon dashicons dashicons-arrow-left-alt2"></span>';
				echo "<a href=\"$href\">$icon<span class=\"admin-dashboard-title-back-link-text\">$text</a>";
			echo '</div>';
		} // endif
		
		// Title section
		echo '<div class="reg-man-rc-admin-dashboard-header-part admin-dashboard-title-text">';
			echo $this->get_current_page_title();
		echo '</div>';
		
	} // function
	
	
	/**
	 * Get the current page title
	 * @return string
	 */
	private function get_current_page_title() {
		if ( ! isset( $this->curr_page_title ) ) {
			$page_name = $this->get_page_name_from_request();
			switch( $page_name ) {
				
				case self::PAGE_NAME_HOME:
				default:
					$this->curr_page_title = ''; // A title is not really necessary here
					break;
					
				case self::PAGE_NAME_EVENT:
					$event = $this->get_event_from_request();
					$this->curr_page_title = $event->get_label();
					break;
					
			} // endswitch
			
		} // endif
		return $this->curr_page_title;
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

	private function render_events_calendar() {
		echo '<div class="reg-man-rc-dashboard-calendar-container">';
			$calendar = Calendar::get_admin_calendar();
			$view = Calendar_View::create( $calendar );
			$view->render();
		echo '</div>';
	} // function

	
	/**
	 * Render the specified event
	 * @param Event	$event
	 */
	private function render_event( $event ) {
		
		if ( empty( $event ) ) {
			return; // <== EXIT POINT! Defensive
		} // endif
		
		$event_key = $event->get_key();
		$classes = 'reg-man-rc-dashboard-event-view-container reg-man-rc-admin-stats-view-container reg-man-rc-admin-single-event-stats';
		$data = "data-event-key=\"$event_key\"";
		echo "<div class=\"$classes\" $data>";
		
			$details_title 		= esc_html__( 'Details', 'reg-man-rc' );
			$charts_title 		= esc_html__( 'Charts', 'reg-man-rc' );
			$fixed_title		= esc_html__( 'Repairs', 'reg-man-rc' );
			$items_title 		= esc_html__( 'Items', 'reg-man-rc' );
			$visitors_title 	= esc_html__( 'Visitors', 'reg-man-rc' );
			$volunteers_title 	= esc_html__( 'Volunteers', 'reg-man-rc' );
			
			$format =
					'<li class="reg-man-rc-tab-list-item">' . 
						'<a href="#tab-%1$s" class="reg-man-rc-icon-text-container">' . 
							'<i class="icon dashicons dashicons-%3$s"></i><span class="text">%2$s</span>' . 
						'</a>' . 
					'</li>';
			echo '<div class="reg-man-rc-tabs-container">';
				echo '<ul>';
					printf( $format, 'details',		$details_title,		'text-page' );
					printf( $format, 'charts',		$charts_title,		'chart-bar' );
					printf( $format, 'fixed',		$fixed_title,		'admin-tools' );
					printf( $format, 'items',		$items_title,		'clipboard' );
					printf( $format, 'visitors',	$visitors_title,	'groups' );
					printf( $format, 'volunteers',	$volunteers_title,	'admin-users' );
				echo '</ul>';
				
				echo '<div id="tab-details" class="tab-panel" data-name="details">';
					self::render_event_details( $event );
				echo '</div>';
				
				echo '<div id="tab-charts" class="tab-panel" data-name="charts">';
					self::render_event_charts( $event );
				echo '</div>';
				
				echo '<div id="tab-fixed" class="tab-panel" data-name="fixed">';
					$view = Items_Fixed_Admin_Table_View::create( Item_Stats_Collection::GROUP_BY_FIXER_STATION );
					$view->render();
				echo '</div>';
				
				echo '<div id="tab-items" class="tab-panel" data-name="items">';
					$view = Items_Admin_Table_View::create();
					$view->set_is_event_column_hidden( TRUE ); // There is only 1 event in this case
					$view->render();
				echo '</div>';
				
				echo '<div id="tab-visitors" class="tab-panel" data-name="visitors">';
					$view = Visitor_Admin_Table_View::create();
					$view->set_is_event_column_hidden( TRUE ); // There is only 1 event in this case
					$view->render();
				echo '</div>';
			
				echo '<div id="tab-volunteers" class="tab-panel" data-name="volunteers">';
					$view = Volunteer_Registration_Admin_Table_View::create();
					$view->set_is_event_column_hidden( TRUE ); // There is only 1 event in this case
					$view->render();
				echo '</div>';
			
			echo '</div>';
			
		echo '</div>';
		
		$this->render_supplemental_items_dialog( $event );
		$this->render_supplemental_visitors_dialog( $event );
		$this->render_supplemental_volunteers_dialog( $event );
		
	} // function
	
	/**
	 * Render the specified event details
	 * @param Event	$event
	 */
	private function render_event_details( $event ) {
		
		$view = Event_View::create_for_page_content( $event, Object_View::OBJECT_PAGE_TYPE_ADMIN_DASHBOARD_EVENT_DETAILS );
		$content = $view->get_object_view_content();
		
		echo $content;
		
	} // function
	
	/**
	 * Render the specified event details
	 * @param Event	$event
	 */
	private function render_event_charts( $event ) {
		
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Repairs', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Repairs_Chart_Model::CHART_TYPE_DETAILED );
			$box->render();

			$label = esc_html__( 'Items', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Items_Chart_Model::CHART_TYPE_ITEMS_BY_FIXER_STATION );
			$box->render();

			$label = esc_html__( 'Visitors', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Visitors_Chart_Model::CHART_TYPE );
			$box->render();

			$label = esc_html__( 'Fixers', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_FIXERS_PER_EVENT );
			$box->render();

			$label = esc_html__( 'Items per Fixer', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION );
			$box->render();

			$label = esc_html__( 'Non-Fixer Volunteers', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_NON_FIXERS_PER_EVENT );
			$box->render();

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
				Scripts_And_Styles::enqueue_admin_dashboard_scripts_and_styles();
			} // endif
		} // endif
	} // function

	/**
	 * Get the URL for this page
	 * @return string
	 */
	public static function get_href_for_main_page() {
		$slug = 'admin.php?page=' . self::PAGE_SLUG;
		$result = admin_url( $slug );
		return $result;
	} // function
	
	/**
	 * Get the permalink for a volunteer area event page
	 * @param	Event	$event					The event whose area page is to be returned
	 * @return string
	 */
	public static function get_href_for_event_page( $event ) {
		$base_url = urldecode( self::get_href_for_main_page() );
		if ( ! isset( $event ) ) {
			$result = $base_url; // Defensive
		} else {
			$event_key = $event->get_key();
			$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
			$result = add_query_arg( $args, $base_url );
		} // endif
		return $result;
	} // function
	
	/**
	 * Render the dialog for supplemental items
	 * @param Event $event
	 */
	private static function render_supplemental_items_dialog( $event ) {
		$title = __( 'Supplemental Item Data', 'reg-man-rc' );
		$event_key_obj = $event->get_key_object();

		echo "<div class=\"supplemental-data-dialog supplemental-item-dialog dialog-container\" title=\"$title\">";
		
			// Form Content
			ob_start();
				echo '<div class="supplemental-table-container">';
					Supplemental_Event_Data_Admin_View::render_supplemental_item_data_table( $event_key_obj );
				echo '</div>';
			$form_content = ob_get_clean();
			
			// Ajax form container
			$ajax_action = Supplemental_Event_Data_Admin_Controller::UPDATE_ITEM_DATA_AJAX_ACTION;
			$form_method = 'POST';
			$form_classes = 'reg-man-rc-supplemental-data-ajax-form';
			$form = Ajax_Form::create( $ajax_action, $form_method, $form_classes, $form_content );
			$form->render();

		echo '</div>';
		
	} // function

	/**
	 * Render the dialog for supplemental visitors
	 * @param Event $event
	 */
	private static function render_supplemental_visitors_dialog( $event ) {
		$title = __( 'Supplemental Visitor Data', 'reg-man-rc' );
		$event_key_obj = $event->get_key_object();
		echo "<div class=\"supplemental-data-dialog supplemental-visitor-dialog dialog-container\" title=\"$title\">";

			// Form content
			ob_start();
				echo '<div class="supplemental-table-container">';
					Supplemental_Event_Data_Admin_View::render_supplemental_visitor_data_table( $event_key_obj );
				echo '</div>';
			$form_content = ob_get_clean();
			
			// Ajax form container
			$ajax_action = Supplemental_Event_Data_Admin_Controller::UPDATE_VISITOR_DATA_AJAX_ACTION;
			$form_method = 'POST';
			$form_classes = 'reg-man-rc-supplemental-data-ajax-form';
			$form = Ajax_Form::create( $ajax_action, $form_method, $form_classes, $form_content );
			$form->render();

		echo '</div>';
	} // function

	/**
	 * Render the dialog for supplemental visitors
	 * @param Event $event
	 */
	private static function render_supplemental_volunteers_dialog( $event ) {
		$title = __( 'Supplemental Volunteer Data', 'reg-man-rc' );
		$event_key_obj = $event->get_key_object();
		echo "<div class=\"supplemental-data-dialog supplemental-volunteer-dialog dialog-container\" title=\"$title\">";
		
			// Form content
			ob_start();
				echo '<div class="supplemental-table-container">';
					Supplemental_Event_Data_Admin_View::render_supplemental_volunteer_data_table( $event_key_obj );
				echo '</div>';
			$form_content = ob_get_clean();
			
			// Ajax form container
			$ajax_action = Supplemental_Event_Data_Admin_Controller::UPDATE_VOLUNTEER_DATA_AJAX_ACTION;
			$form_method = 'POST';
			$form_classes = 'reg-man-rc-supplemental-data-ajax-form';
			$form = Ajax_Form::create( $ajax_action, $form_method, $form_classes, $form_content );
			$form->render();

		echo '</div>';
	} // function

	

} // function