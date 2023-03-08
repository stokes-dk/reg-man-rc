<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\View\Chart_View;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Event_Filter_Input_Form;
use Reg_Man_RC\Control\Map_Controller;
use Reg_Man_RC\Model\Stats\Item_Statistics;

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

		echo '<div class="reg-man-rc-stats-view-container">';

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
		$vol_reg_title		= esc_html__( 'Volunteers', 'reg-man-rc' );
		$visitors_title		= esc_html__( 'Visitors', 'reg-man-rc' );

		$format = '<li class="reg-man-rc-tab-list-item"><a href="#tab-%1$s"><i class="dashicons dashicons-%3$s"></i><span>%2$s</span></a></li>';
		echo '<div class="reg-man-rc-tabs-container">';
			echo '<ul>';
				printf( $format, 'summary',			$summary_title,			'chart-bar' );
				printf( $format, 'fixed',			$fixed_title,			'editor-table' );
				printf( $format, 'fixers',			$fixers_title,			'chart-bar' );
				printf( $format, 'non-fixers',		$non_fixers_title,		'chart-bar' );
				printf( $format, 'map',				$map_title,				'location-alt' );
				printf( $format, 'events',			$events_title,			'editor-table' );
				printf( $format, 'items',			$items_title,			'editor-table' );
				printf( $format, 'vol-reg',			$vol_reg_title,			'editor-table' );
				printf( $format, 'visitors'	,		$visitors_title,		'editor-table' );
			echo '</ul>';

			echo '<div id="tab-summary" class="tab-panel" data-name="summary">';
				self::render_summary_tab();
			echo '</div>';

			echo '<div id="tab-fixed" class="tab-panel" data-name="fixed">';
				self::render_fixed_tab();
			echo '</div>';

			echo '<div id="tab-fixers" class="tab-panel" data-name="fixers">';
				self::render_fixers_tab();
			echo '</div>';

			echo '<div id="tab-non-fixers" class="tab-panel" data-name="non_fixers">';
				self::render_non_fixers_tab();
			echo '</div>';

			echo '<div id="tab-map" class="tab-panel" data-name="map">';
				self::render_map_tab();
			echo '</div>';

			echo '<div id="tab-events" class="tab-panel" data-name="events">';
				self::render_events_tab();
			echo '</div>';

			echo '<div id="tab-items" class="tab-panel" data-name="items">';
				self::render_items_tab();
			echo '</div>';

			echo '<div id="tab-vol-reg" class="tab-panel" data-name="volunteer_registration">';
				self::render_volunteer_registration_tab();
			echo '</div>';

			echo '<div id="tab-visitors" class="tab-panel" data-name="visitors">';
				self::render_visitors_tab();
			echo '</div>';

		echo '</div>';
	} // function

	private static function render_summary_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Events', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'event', 'event_category' );
			$box->render();

			$label = esc_html__( 'Repairs', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'fixed', 'summary' );
			$box->render();

			$label = esc_html__( 'Visitors & Volunteers', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'people', 'summary' );
			$box->render();

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

	private static function render_fixed_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$fixed_table = Items_Fixed_Admin_Table_View::create( Item_Statistics::GROUP_BY_FIXER_STATION );
			$fixed_table->render();
		echo '</div>';
	} // function

	private static function render_volunteer_registration_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$vol_reg_table = Volunteer_Registration_Admin_Table_View::create();
			$vol_reg_table->render();
		echo '</div>';
	} // function

	private static function render_visitors_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';
			$visitor_table = Visitor_Admin_Table_View::create();
			$visitor_table->render();
		echo '</div>';
	} // function

	private static function render_fixers_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Fixers per Event', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'fixer', 'fixer_station' );
			$box->render();

			$label = esc_html__( 'Items per Event', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'item', 'fixer_station' );
			$box->render();

			$label = esc_html__( 'Items per Fixer', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'items-per-fixer', 'fixer_station' );
			$box->render();

		echo '</div>';

	} // function

	private static function render_non_fixers_tab() {
		echo '<div class="reg-man-rc-chart-group-container">';

			$label = esc_html__( 'Non-Fixer Volunteers per Event', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'non-fixer', 'volunteer_role' );
			$box->render();

			$label = esc_html__( 'Visitors per Event', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'visitor', 'summary' );
			$box->render();

			$label = esc_html__( 'Visitors per Non-Fixer Volunteer', 'reg-man-rc' );
			$box = Chart_View::create( $label, 'visitors-per-volunteer', 'volunteer_role' );
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
