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
use Reg_Man_RC\View\Stats\Ajax_Chart_View;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Control\Admin\Supplemental_Event_Data_Admin_Controller;
use Reg_Man_RC\Model\Stats\Repairs_Chart_Model;
use Reg_Man_RC\Model\Stats\Items_Chart_Model;
use Reg_Man_RC\Model\Stats\Volunteers_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_Chart_Model;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative event calendar page showing ALL events known to the system
 *
 * @since	v0.1.0
 *
 */
class Admin_Event_Calendar_Page {
	const PAGE_SLUG = 'reg-man-rc-admin-event-calendar';

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
			$event = self::get_event_from_request();
			self::$PAGE_NAME = ! empty( $event ) ? self::PAGE_NAME_EVENT : self::PAGE_NAME_HOME;
		} // endif
		return self::$PAGE_NAME;
	} // function
	
	/**
	 * Get the event object from the current request if one exists, otherwise NULL
	 * @return Event|NULL
	 */
	public static function get_event_from_request() {
		if ( ! isset( self::$EVENT ) ) {
			$event_key = self::get_event_key_from_request();
			self::$EVENT = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
			if ( ! empty( $event_key ) && empty( self::$EVENT ) ) {
				// We have an event key but the event could not be found, so create a placeholder
				self::$EVENT = Event::create_placeholder_event( $event_key );
			} // endif
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
		
		echo '<div class="wrap">';
		
			$page_name = $this->get_page_name_from_request();
			switch( $page_name ) {
				
				case self::PAGE_NAME_HOME:
				default:
					/* Translators: %1$s is the site title */
					$head_format = __( 'Administrative Calendar for %1$s', 'reg-man-rc' );
					$site_name = get_bloginfo();
					$heading = sprintf( $head_format, $site_name );
					break;
					
				case self::PAGE_NAME_EVENT:
					/* Translators: %1$s is a link to the main admin calendar, %2$s is a label to identify an event */
					$head_format = _x( '%1$s &rsaquo; %2$s',
						'The title for a page showing an event details inside the admin calendar', 'reg-man-rc' );
					$href = self::get_href_for_main_page();
					$title = __( 'Admin Calendar', 'reg-man-rc' );
					$main_page_link = "<a href=\"$href\">$title</a>";
					$event = $this->get_event_from_request();
					$event_label = $event->get_label();
					$heading = sprintf( $head_format, $main_page_link, $event_label );
					break;
					
			} // endswitch
		
			
			echo "<h1 class=\"wp-heading-inline\">$heading</h1>";
			echo '<hr class="wp-header-end">';

			echo '<div class="reg-man-rc-admin-calendar-page-content">';
				$this->render_main_content();
			echo '</div>';
			
		echo '</div>';
		
	} // function

	
	private function render_main_content() {
		
		echo '<div class="reg-man-rc-admin-calendar-page-body">';
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
					$this->render_event_category_admin_notice();
				} // endif
				if ( Fixer_Station_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_fixer_station_admin_notice();
				} // endif
				if ( Volunteer_Role_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_volunteer_roles_admin_notice();
				} // endif
				if ( Item_Suggestion_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_item_suggestions_admin_notice();
				} // endif
				if ( Item_Type_Admin_View::get_is_show_create_defaults_admin_notice() ) {
					$this->render_item_types_admin_notice();
				} // endif
			} // endif
		} // endif
	} // function

	private function render_event_category_admin_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No event categories are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Event_Category::get_admin_url();
			$text = __( 'Manage event categories', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_fixer_station_admin_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No fixer stations are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Fixer_Station::get_admin_url();
			$text = __( 'Manage fixer stations', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_volunteer_roles_admin_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No volunteer roles are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Volunteer_Role::get_admin_url();
			$text = __( 'Manage volunteer roles', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_item_types_admin_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No item types are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Item_Type::get_admin_url();
			$text = __( 'Manage item types', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	private function render_item_suggestions_admin_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
			$heading = __( 'No item suggestions are defined', 'reg-man-rc' );
			echo "<p><b>$heading</b></p>";
			$url = Item_Suggestion::get_admin_url();
			$text = __( 'Manage item suggestions', 'reg-man-rc' );
			echo "<p><a href=\"$url\">$text</a></p>";
		echo '</div>';
	} // funciton

	/**
	 * Return a total count of notifications shown
	 */
	public function get_notification_count() {
		if ( ! isset( $this->notification_count ) ) {
			$this->notification_count = 0;
			if ( Event_Category_Admin_View::get_is_show_create_defaults_admin_notice() ) {
				$this->notification_count++;
			} // endif
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
		echo '<div class="reg-man-rc-admin-calendar-container">';
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
		
		$event_key = $event->get_key_string();
		$classes = 'reg-man-rc-admin-event-calendar-event-view-container reg-man-rc-admin-stats-view-container reg-man-rc-admin-single-event-stats';
		$data = "data-event-key=\"$event_key\"";
		
		// We will show the items, visitors and volunteers tabs to all users who normally see it
		// But some users will not have access to this particular event in which case a message will be shown
		// Note that the visitors tab really shows visitor registrations which is part of item registration capability
		$is_show_items_tab = current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL );
		$is_show_volunteers_tab = current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
		
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
			
			echo '<div class="reg-man-rc-tabs-container" data-save-active-tab="main-active-tab">';
			
				echo '<ul>';
					printf( $format, 'details',		$details_title,		'text-page' );
					printf( $format, 'charts',		$charts_title,		'chart-bar' );
					printf( $format, 'fixed',		$fixed_title,		'admin-tools' );
					if ( $is_show_items_tab ) {
						printf( $format, 'items',		$items_title,		'clipboard' );
						printf( $format, 'visitors',	$visitors_title,	'groups' );
					} // endif
					if ( $is_show_volunteers_tab ) {
						printf( $format, 'volunteers',	$volunteers_title,	'admin-users' );
					} // endif
				echo '</ul>';
				
				echo '<div id="tab-details" class="tab-panel" data-name="details">';
					self::render_event_details( $event );
				echo '</div>';
				
				echo '<div id="tab-charts" class="tab-panel" data-name="charts">';
					self::render_event_charts( $event );
				echo '</div>';
				
				echo '<div id="tab-fixed" class="tab-panel" data-name="fixed">';
					self::render_items_fixed_tab_content( $event );
				echo '</div>';
				
				if ( $is_show_items_tab ) {

					echo '<div id="tab-items" class="tab-panel" data-name="items">';
						self::render_items_tab_content( $event );
					echo '</div>';
					echo '<div id="tab-visitors" class="tab-panel" data-name="visitors">';
						self::render_visitors_tab_content( $event );
					echo '</div>';
				} // endif

				if ( $is_show_volunteers_tab ) {

					echo '<div id="tab-volunteers" class="tab-panel" data-name="volunteers">';
						self::render_volunteers_tab_content( $event );
					echo '</div>';
					
				} // endif
			
			echo '</div>';
			
		echo '</div>';
		
		// Supplemental data dialogs
		$this->render_supplemental_items_dialog( $event );
		$this->render_supplemental_visitors_dialog( $event );
		$this->render_supplemental_volunteers_dialog( $event );
		
		// Import data dialogs
		// TODO: This is a work in progress
		$this->render_import_items_dialog( $event );
		
	} // function
	
	/**
	 * Render the specified event details
	 * @param Event	$event
	 */
	private function render_event_details( $event ) {
		
		$view = Event_View::create_for_page_content( $event, Object_View::OBJECT_PAGE_TYPE_ADMIN_CALENDAR_EVENT_DETAILS );
		$content = $view->get_object_view_content();
		
		echo $content;
		
	} // function
	
	/**
	 * Render the specified event charts
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
	 * Render the specified event items fixed tab
	 * @param Event	$event
	 */
	private function render_items_fixed_tab_content( $event ) {

		$view = Items_Fixed_Admin_Table_View::create( $event );
		$view->render();

	} // function
	
	/**
	 * Render the specified event items tab
	 * @param Event	$event
	 */
	private function render_items_tab_content( $event ) {

		if ( $event->get_is_current_user_able_to_register_items() ) {

			$view = Items_Admin_Table_View::create( $event );
			$view->render();

		} else {
			
			echo __( 'You are not authorized to register items for this event.', 'reg-man-rc' );
			
		} // endif
		
	} // function
	
	/**
	 * Render the specified event visitors tab
	 * @param Event	$event
	 */
	private function render_visitors_tab_content( $event ) {
		
		// Note that the visitors tab really shows visitor registrations which is part of item registration
		if ( $event->get_is_current_user_able_to_register_items() ) {
			
			$view = Visitor_Admin_Table_View::create( $event );
			$view->render();

		} else {

			echo __( 'You are not authorized to register visitors for this event.', 'reg-man-rc' );
			
		} // endif

	} // function
	
	/**
	 * Render the specified event volunteers tab
	 * @param Event	$event
	 */
	private function render_volunteers_tab_content( $event ) {

		if ( $event->get_is_current_user_able_to_register_volunteers() ) {

			$view = Volunteer_Registration_Admin_Table_View::create( $event );
			$view->render();

		} else {
			
			echo __( 'You are not authorized to register volunteers for this event.', 'reg-man-rc' );
			
		} // endif

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
				Scripts_And_Styles::enqueue_admin_calendar_scripts_and_styles();
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
			$event_key = $event->get_key_string();
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

	/**
	 * Render the dialog for supplemental items
	 * @param Event $event
	 */
	private static function render_import_items_dialog( $event ) {
		$title = __( 'Import Items', 'reg-man-rc' );

		echo "<div class=\"import-data-dialog import-items-dialog dialog-container\" title=\"$title\">";

			// I need to render this dynamically on the client every time the dialog is opened (or closed)
			$view = Item_Import_Admin_View::create();
			$view->set_event_key_string( $event->get_key_string() );
			$view->render_form();

		echo '</div>';
		
	} // function

		/**
	 * Get the set of tabs to be shown in the help for this taxonomy
	 * @return array
	 */
	public static function get_help_tabs() {
		
		$page_name = self::get_page_name_from_request();
		switch( $page_name ) {
			
			case self::PAGE_NAME_HOME:
			default:
				$result = array(
					array(
						'id'		=> 'reg-man-rc-about',
						'title'		=> __( 'About', 'reg-man-rc' ),
						'content'	=> self::get_events_calendar_about_content(),
					),
				);
				break;
				
			case self::PAGE_NAME_EVENT:
				$result = array(
					array(
						'id'		=> 'reg-man-rc-about',
						'title'		=> __( 'About', 'reg-man-rc' ),
						'content'	=> self::get_single_event_about_content(),
					),
				);
				break;
				
		} // endswitch
			
		return $result;
	} // function

	/**
	 * Get the html content shown to the administrator in the "About" help for this taxonomy
	 * @return string
	 */
	private static function get_events_calendar_about_content() {
		ob_start();
			$heading = __( 'About the administrative calendar', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'The admin calendar shows the events defined in the system including private events (hidden from the public)' .
					' and all instances of repeating events.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Select an event in the calendar to see a pop-up summary. ',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			echo '<p>';
				$msg = __(
					'Select the "More details" link in the pop-up to see more information' .
					' including a list of volunteers registered to attend the event.',
					'reg-man-rc'
				);
				echo esc_html( $msg );
			echo '</p>';
			$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Get the html content shown to the administrator in the "About" help for this taxonomy
	 * @return string
	 */
	private static function get_single_event_about_content() {

		// We will show the items, visitors and volunteers tabs to all users who normally see it
		// But some users will not have access to this particular event in which case a message will be shown
		// Note that the visitors tab really shows visitor registrations which is part of item registration capability
		$is_show_items_tab = current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL );
		$is_show_volunteers_tab = current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL );
		
		ob_start();
			$heading = __( 'About the single event administrative view', 'reg-man-rc' );
			echo "<h2>$heading</h2>";
			echo '<p>';
				$msg = __(
					'The single event view shows the details of an event or an instance of a recurring event.' .
					' It contains the following tabs:',
					'reg-man-rc'
				);
				echo esc_html( $msg );
				$item_format = '<dt>%1$s</dt><dd>%2$s</dd>';
				echo '<dl>';

					$title = esc_html__( 'Details', 'reg-man-rc' );
					$msg = esc_html__( 'A summary of the event details.', 'reg-man-rc' );
					printf( $item_format, $title, $msg );

					$title = esc_html__( 'Charts', 'reg-man-rc' );
					$msg = esc_html__( 'Graphical representations of some of the data collected for the event.', 'reg-man-rc' );
					printf( $item_format, $title, $msg );

					$title = esc_html__( 'Repairs', 'reg-man-rc' );
					$msg = esc_html__( 'A table showing statistics of the repairs performed at the event.', 'reg-man-rc' );
					printf( $item_format, $title, $msg );

					if ( $is_show_items_tab ) {

						$title = esc_html__( 'Items', 'reg-man-rc' );
						$msg = esc_html__( 'A table showing the items registered for the event.', 'reg-man-rc' );
						printf( $item_format, $title, $msg );

						$title = esc_html__( 'Visitors', 'reg-man-rc' );
						$msg = esc_html__(
									'A table showing the visitors who brought items to the event.' .
									'  To protect their privacy you may not be able to see personal information like full name or email address.',
									'reg-man-rc'
								);
						printf( $item_format, $title, $msg );
						
					} // endif
					
					if ( $is_show_volunteers_tab ) {

						$title = esc_html__( 'Volunteers', 'reg-man-rc' );
						$msg = esc_html__(
									'A table showing the volunteers who registered for the event.' .
									'  To protect their privacy you may not be able to see personal information like full name or email address.',
									'reg-man-rc'
								);
						printf( $item_format, $title, $msg );

					} // endif
					
				echo '</dl>';
			echo '</p>';

			if ( $is_show_volunteers_tab ) {
				echo '<p>';
					$msg = __(
						'If you are an event organizer you can get a list of email addresses for volunteer registered to attend your event.' .
						'  Press the "Emails..." button inside the "Volunteers" tab to launch a dialog box containing the emails.',
						'reg-man-rc'
					);
					echo esc_html( $msg );
				echo '</p>';
			} // endif
			
			$result = ob_get_clean();
		return $result;
	} // function


} // function