<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Stats\Ajax_Chart_View;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Model\Stats\Item_Stats_Collection;
use Reg_Man_RC\Model\Stats\Events_Chart_Model;
use Reg_Man_RC\Model\Stats\Repairs_Chart_Model;
use Reg_Man_RC\Model\Stats\Items_Chart_Model;
use Reg_Man_RC\Model\Stats\Volunteers_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_And_Volunteers_Chart_Model;
use Reg_Man_RC\Model\Stats\Visitors_Chart_Model;
use Reg_Man_RC\Control\User_Role_Controller;

/**
 * The administrative view for statistics
 *
 * @since	v0.1.0
 *
 */
class Admin_Stats_View {

	const PAGE_SLUG = 'reg-man-rc-admin-stats';

	// There are different types of scope used to show stats: Show all stats, show stats for a certain year, event category etc.
	// To allow a simple selection, options are grouped together and option values may contain a marker, e.g.
	//  'evt:1234' for a single event, or 'year:2018' for a year, or 'evt-cat:2345' for an event category.
	// The following private variables are used to construct that select
	private static $SCOPE_TYPE_SEPARATOR	= ':'; // Used to separate scope type from value, e.g. 'year:2018'
	private static $YEAR_TYPE				= 'year';		// Used to flag a scope select value as a year
	private static $EVENT_TYPE				= 'evt';		// Used to flag a scope select value as a single event
	private static $EVENT_CATEGORY_TYPE		= 'evt-cat';	// Used to flag a scope select value as an event category

	public static function register() {

		// Regsiter to enqueue the necessary scripts and styles as needed
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

	} // function

	private function __constructor() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Render the Stats Page
	 */
	public static function render_stats_page() {
		echo '<div class="wrap">';
			$heading = __( 'Repair Café Statistics', 'reg-man-rc' );
			echo "<h1 class=\"wp-heading-inline\">$heading</h1>";
			echo '<hr class="wp-header-end">';
			$view = self::create();
			$view->render();
		echo '</div>';
	} // function


	public function render() {

		echo '<div class="reg-man-rc-admin-stats-view-container reg-man-rc-admin-filtered-stats">';

			echo '<div class="reg-man-rc-stats-view-filter-container">';
				$this->render_filter();
			echo '</div>';

			echo '<div class="reg-man-rc-stats-view-details-container">';
				$this->render_stats_view_details();
			echo '</div>';

		echo '</div>';
	} // function

	private function render_filter() {
		$filter = Event_Filter_Input_Form::create();
		$filter->render();

	} // function

	private function render_stats_view_details() {

		$summary_title		= esc_html__( 'Summary', 'reg-man-rc' );
		$fixers_title		= esc_html__( 'Fixers', 'reg-man-rc' );
		$non_fixers_title	= esc_html__( 'Non-Fixers', 'reg-man-rc' );
		$fixed_title		= esc_html__( 'Repairs', 'reg-man-rc' );
		$map_title			= esc_html__( 'Map', 'reg-man-rc' );
		$events_title		= esc_html__( 'Events', 'reg-man-rc' );
		$items_title		= esc_html__( 'Items', 'reg-man-rc' );
		$visitors_title		= esc_html__( 'Visitors', 'reg-man-rc' );
		$vol_reg_title		= esc_html__( 'Volunteers', 'reg-man-rc' );

		$format =
				'<li class="reg-man-rc-tab-list-item">' . 
					'<a href="#tab-%1$s" class="reg-man-rc-icon-text-container">' . 
						'<i class="icon dashicons dashicons-%3$s"></i><span class="text">%2$s</span>' . 
					'</a>' . 
				'</li>';

		// Set up the tabs for the current user (depending on their role's capabilities)
		$tabs_array = array();
		$tabs_array[] = 'summary';
		$tabs_array[] = 'fixed' ;
		
		if ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'fixers';
			$tabs_array[] = 'non-fixers';
		} // endif
		
		if ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'map';
		} // endif
		
		if ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'events';
		} // endif
		
		if ( current_user_can( 'edit_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'items';
		} // endif
		
		if ( current_user_can( 'edit_' . User_Role_Controller::VISITOR_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'visitors';
		} // endif
		
		if ( current_user_can( 'edit_' . User_Role_Controller::VOLUNTEER_REG_CAPABILITY_TYPE_PLURAL ) ) {
			$tabs_array[] = 'vol-reg';
		} // endif
		
		echo '<div class="reg-man-rc-tabs-container">';
			echo '<ul>';
				if ( in_array( 'summary', $tabs_array ) ) {
					printf( $format, 'summary',			$summary_title,			'chart-bar' );
				} // endif
				if ( in_array( 'fixed', $tabs_array ) ) {
					printf( $format, 'fixed',			$fixed_title,			'admin-tools' );
				} // endif
				if ( in_array( 'fixers', $tabs_array ) ) {
					printf( $format, 'fixers',			$fixers_title,			'chart-bar' );
				} // endif
				if ( in_array( 'non-fixers', $tabs_array ) ) {
					printf( $format, 'non-fixers',		$non_fixers_title,		'chart-bar' );
				} // endif
				if ( in_array( 'map', $tabs_array ) ) {
					printf( $format, 'map',				$map_title,				'location-alt' );
				} // endif
				if ( in_array( 'events', $tabs_array ) ) {
					printf( $format, 'events',			$events_title,			'calendar' );
				} // endif
				if ( in_array( 'items', $tabs_array ) ) {
					printf( $format, 'items',			$items_title,			'clipboard' );
				} // endif
				if ( in_array( 'visitors', $tabs_array ) ) {
					printf( $format, 'visitors'	,		$visitors_title,		'groups' );
				} // endif
				if ( in_array( 'vol-reg', $tabs_array ) ) {
					printf( $format, 'vol-reg',			$vol_reg_title,			'admin-users' );
				} // endif
			echo '</ul>';

			if ( in_array( 'summary', $tabs_array ) ) {
				echo '<div id="tab-summary" class="tab-panel" data-name="summary">';
					self::render_summary_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'fixed', $tabs_array ) ) {
				echo '<div id="tab-fixed" class="tab-panel" data-name="fixed">';
					self::render_fixed_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'fixers', $tabs_array ) ) {
				echo '<div id="tab-fixers" class="tab-panel" data-name="fixers">';
					self::render_fixers_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'non-fixers', $tabs_array ) ) {
				echo '<div id="tab-non-fixers" class="tab-panel" data-name="non_fixers">';
					self::render_non_fixers_tab();
				echo '</div>';
			} // endif

			if ( in_array( 'map', $tabs_array ) ) {
				echo '<div id="tab-map" class="tab-panel" data-name="map">';
					self::render_map_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'events', $tabs_array ) ) {
				echo '<div id="tab-events" class="tab-panel" data-name="events">';
					self::render_events_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'items', $tabs_array ) ) {
				echo '<div id="tab-items" class="tab-panel" data-name="items">';
					self::render_items_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'visitors', $tabs_array ) ) {
				echo '<div id="tab-visitors" class="tab-panel" data-name="visitors">';
					self::render_visitors_tab();
				echo '</div>';
			} // endif
			
			if ( in_array( 'vol-reg', $tabs_array ) ) {
				echo '<div id="tab-vol-reg" class="tab-panel" data-name="volunteer_registration">';
					self::render_volunteer_registration_tab();
				echo '</div>';
			} // endif
			
		echo '</div>';
	} // function

	private static function render_summary_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Events', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Events_Chart_Model::CHART_TYPE );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Repairs', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Repairs_Chart_Model::CHART_TYPE_DETAILED );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Visitors & Volunteers', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Visitors_And_Volunteers_Chart_Model::CHART_TYPE );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

		echo '</div>';

	} // function

	private static function render_fixed_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$fixed_table = Items_Fixed_Admin_Table_View::create();
			$fixed_table->render();
		echo '</div>';
	} // function

	private static function render_events_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$events_table = Events_Admin_Table_View::create();
			$events_table->render();
		echo '</div>';
	} // function

	private static function render_items_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$items_table = Items_Admin_Table_View::create();
			$items_table->render();
		echo '</div>';
	} // function

	private static function render_visitors_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$visitor_table = Visitor_Admin_Table_View::create();
			$visitor_table->render();
		echo '</div>';
	} // function

	private static function render_volunteer_registration_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$vol_reg_table = Volunteer_Registration_Admin_Table_View::create();
			$vol_reg_table->render();
		echo '</div>';
	} // function

	private static function render_fixers_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Fixers per Event', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_FIXERS_PER_EVENT );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Items per Event', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Items_Chart_Model::CHART_TYPE_ITEMS_BY_FIXER_STATION );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Items per Fixer', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_ITEMS_PER_FIXER_BY_STATION );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

		echo '</div>';

	} // function

	private static function render_non_fixers_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Non-Fixer Volunteers per Event', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_NON_FIXERS_PER_EVENT );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Visitors per Event', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Visitors_Chart_Model::CHART_TYPE );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

			$label = esc_html__( 'Visitors per Non-Fixer Volunteer', 'reg-man-rc' );
			$box = Ajax_Chart_View::create( $label, Volunteers_Chart_Model::CHART_TYPE_VISITORS_PER_VOLUNTEER_ROLE );
			$box->set_classes( 'event-filter-change-listener' );
			$box->render();

		echo '</div>';
	} // function

	private static function render_map_tab() {

		$map_view = Map_View::create_for_admin_stats();
		$no_markers_message = __( 'No events', 'reg-man-rc' );
		$map_view->set_no_markers_message( $no_markers_message );
		$map_view->set_show_missing_location_message( TRUE );
		$form_id = $map_view->get_map_marker_ajax_form_id();
		$data = "data-map-marker-ajax-form-id=\"$form_id\"";

		echo "<div class=\"reg-man-rc-admin-events-map-container event-filter-change-listener\" $data>";

			$map_view->render();

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
		if ( $pagenow = 'admin.php' ) {
			$slugs = array( Admin_Menu_Page::MENU_SLUG, self::PAGE_SLUG );
			$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
			if ( in_array( $page, $slugs ) ) {
				Scripts_And_Styles::enqueue_stats_view_script_and_styles();
				Scripts_And_Styles::enqueue_base_admin_script_and_styles();
				Scripts_And_Styles::enqueue_google_maps();
			} // endif
		} // endif
	} // function

} // class
