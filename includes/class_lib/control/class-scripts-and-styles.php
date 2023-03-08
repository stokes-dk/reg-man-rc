<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Settings;

/**
 * Facilitates the registration and enqueueing of scripts and styles for the plugin
 *
 * This class contains the methods used to register scripts and styles (js and css) for the admin and public interfaces.
 * It also supplies methods to facilitate enqueueing those scripts and styles.
 *
 * @since v0.1.0
 *
 */
class Scripts_And_Styles {

	// The following handles are used internally to register and enqueue styles and scripts
	private static $SHARED_BASE_SCRIPT_HANDLE = 'reg-man-rc-shared-base-script';
	private static $SHARED_BASE_STYLE_HANDLE = 'reg-man-rc-shared-base-style';

	private static $PUBLIC_BASE_STYLE_HANDLE = 'reg-man-rc-public-style';
	private static $PUBLIC_BASE_SCRIPT_HANDLE = 'reg-man-rc-public-script';

	private static $PUBLIC_REGISTRATION_STYLE_HANDLE = 'reg-man-rc-public-registration-style';
	private static $PUBLIC_REGISTRATION_SCRIPT_HANDLE = 'reg-man-rc-public-registration-script';

	private static $VOLUNTEER_AREA_STYLE_HANDLE = 'reg-man-rc-volunteer-area-style';
	private static $VOLUNTEER_AREA_SCRIPT_HANDLE = 'reg-man-rc-volunteer-area-script';

	private static $BASE_ADMIN_STYLE_HANDLE = 'reg-man-rc-base-admin-style';
	private static $BASE_ADMIN_SCRIPT_HANDLE = 'reg-man-rc-base-admin-script';

	private static $ADMIN_REST_SCRIPT_HANDLE = 'reg-man-rc-admin-rest-script';

	private static $TERM_ORDER_ADMIN_STYLE_HANDLE = 'reg-man-rc-term-order-admin-style';
	private static $TERM_ORDER_ADMIN_SCRIPT_HANDLE = 'reg-man-rc-term-order-admin-script';

	private static $STATS_VIEW_STYLE_HANDLE = 'reg-man-rc-stats-view-style';
	private static $STATS_VIEW_SCRIPT_HANDLE = 'reg-man-rc-stats-view-script';

	private static $AJAX_FORMS_SCRIPT_HANDLE = 'reg-man-rc-ajax-forms-script';

	private static $JQUERY_STYLE_HANDLE = 'reg-man-rc-jquery-theme';

	private static $GOOGLE_MAPS_API_SCRIPT_HANDLE = 'reg-man-rc-google-maps-api-script';
	private static $GOOGLE_MAPS_INTERNAL_SCRIPT_HANDLE = 'reg-man-rc-google-maps-internal-script';
	private static $GOOGLE_MAPS_INTERNAL_STYLE_HANDLE = 'reg-man-rc-google-maps-internal-style';

	private static $DATATABLES_STYLE_HANDLE = 'reg-man-rc-datatables-style';
	private static $DATATABLES_SCRIPT_HANDLE = 'reg-man-rc-datatables-script';
	private static $DATATABLES_ROWGROUP_STYLE_HANDLE = 'reg-man-rc-datatables-rowgroup-style';
	private static $DATATABLES_ROWGROUP_SCRIPT_HANDLE = 'reg-man-rc-datatables-rowgroup-script';

//	private static $CHARTJS_STYLE_HANDLE = 'reg-man-rc-chartjs-style';
	private static $CHARTJS_SCRIPT_HANDLE = 'reg-man-rc-chartjs-script';

//	private static $JSTREE_STYLE_HANDLE = 'reg-man-rc-jstree-style';
//	private static $JSTREE_SCRIPT_HANDLE = 'reg-man-rc-jstree-script';

	private static $FULLCALENDAR_STYLE_HANDLE = 'reg-man-rc-fullcalendar-style';
	private static $FULLCALENDAR_SCRIPT_HANDLE = 'reg-man-rc-fullcalendar-script';
	private static $FULLCALENDAR_LOCALES_SCRIPT_HANDLE = 'reg-man-rc-fullcalendar-locales-script';
	private static $FULLCALENDAR_INTERNAL_SCRIPT_HANDLE = 'reg-man-rc-fullcalendar-internal-script';
	private static $FULLCALENDAR_INTERNAL_STYLE_HANDLE = 'reg-man-rc-fullcalendar-internal-style';

	private static $SELECT2_STYLE_HANDLE = 'reg-man-rc-select2-style';
	private static $SELECT2_SCRIPT_HANDLE = 'reg-man-rc-select2-script';

	// Tooltipster is used to show popup details of events in fullcalendar
	private static $TOOLTIPSTER_STYLE_HANDLE = 'reg-man-rc-tooltipster-style';
	private static $TOOLTIPSTER_SHADOW_STYLE_HANDLE = 'reg-man-rc-tooltipster-shadow-style';
	private static $TOOLTIPSTER_SCRIPT_HANDLE = 'reg-man-rc-tooltipster-script';

	/**
	 * Register the scripts and styles for the plugin
	 *
	 * This function is called during the admin_enqueue_scripts hook to register the scripts and styles
	 * used by the plugin in the admin interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {
		// Register the elements used only on  back end or front end, depending on what's being rendered right now
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			// Register the admin scripts and styles
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_admin_scripts_and_styles' ) );
		} else {
			// Register the public scripts and styles
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_public_scripts_and_styles' ) );
		} // endif
	} // function

	/**
	 * Register the scripts and styles for the admin interface
	 *
	 * This method is called during the admin_enqueue_scripts hook to register the scripts and styles
	 * used by the plugin in the admin interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register_admin_scripts_and_styles() {
		self::register_shared_scripts_and_styles();
		self::register_base_admin_scripts_and_styles();
//		self::register_admin_rest_script();
		self::register_term_order_admin_scripts_and_styles();
		self::register_chartjs();
//		self::register_jstree();
	} // function

	/**
	 * Register the scripts and styles for the public interface
	 *
	 * This method is called during the wp_enqueue_scripts hook to register the scripts and styles
	 * used by the plugin in the public interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register_public_scripts_and_styles() {
		self::register_shared_scripts_and_styles();
		self::register_public_base_scripts_and_styles();
		self::register_public_registration_scripts_and_styles();
		self::register_volunteer_area_scripts_and_styles();
	} // function

	/**
	 * Internal method to register the scripts and styles shared by both the the public interface and the admin interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_shared_scripts_and_styles() {
		// The following scripts and styles are used in both the public and admin interface sides
		self::register_shared_base_scripts_and_styles();
		self::register_jquery_theme();
		self::register_datatables();
		self::register_datatables_rowgroup_extension();
		self::register_ajax_forms();
		self::register_google_maps();
		self::register_tooltipster();
		self::register_fullcalendar();
		self::register_select2();
		self::register_stats_view();
	} // function

	/**
	 * Internal method to register the shared base javascript and stylesheet
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_shared_base_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'shared/shared-base.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$SHARED_BASE_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					'wp-i18n',
					'jquery',
					self::$SELECT2_SCRIPT_HANDLE,
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'shared/shared-base.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$SHARED_BASE_STYLE_HANDLE,
			$src,
			$dependencies = array(
					'dashicons',
					self::$SELECT2_STYLE_HANDLE,
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the shared stylesheet
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_shared_stylesheet() {
		wp_enqueue_style( self::$SHARED_BASE_STYLE_HANDLE );
	} // function


	/**
	 * Internal method to register the public stylesheet
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_public_base_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'public/public-base.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$PUBLIC_BASE_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'public/public-base.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$PUBLIC_BASE_STYLE_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_STYLE_HANDLE,
					self::$JQUERY_STYLE_HANDLE,
					'dashicons',
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Internal method to register the public scripts and styles for the registration pages
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_public_registration_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'public/public-registration.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$PUBLIC_REGISTRATION_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					'jquery',
					'jquery-ui-core',
					'jquery-ui-tooltip',
					'jquery-ui-dialog',
					'jquery-ui-autocomplete',
					'jquery-ui-accordion',
					self::$SHARED_BASE_SCRIPT_HANDLE,
					self::$AJAX_FORMS_SCRIPT_HANDLE,
					self::$DATATABLES_SCRIPT_HANDLE,
					self::$DATATABLES_ROWGROUP_SCRIPT_HANDLE,
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'public/public-registration.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$PUBLIC_REGISTRATION_STYLE_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_STYLE_HANDLE,
					self::$PUBLIC_BASE_STYLE_HANDLE,
					self::$DATATABLES_STYLE_HANDLE,
					self::$DATATABLES_ROWGROUP_STYLE_HANDLE,
					self::$JQUERY_STYLE_HANDLE,
					'dashicons',
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Internal method to register the public scripts and styles for the registration pages
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_volunteer_area_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;

		// Script
		$src = plugins_url( 'public/volunteer-area.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$VOLUNTEER_AREA_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					self::$PUBLIC_BASE_SCRIPT_HANDLE,
					self::$AJAX_FORMS_SCRIPT_HANDLE,
					'jquery-ui-tabs',
			),
			$version,
			$footer = FALSE
		);

		// Style
		$src = plugins_url( 'public/volunteer-area.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$VOLUNTEER_AREA_STYLE_HANDLE,
			$src,
			$dependencies = array(
					self::$PUBLIC_BASE_STYLE_HANDLE,
					self::$SHARED_BASE_STYLE_HANDLE,
					self::$JQUERY_STYLE_HANDLE,
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the public base scripts and stylesheets
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_public_base_scripts_and_styles() {
		wp_enqueue_script( self::$PUBLIC_BASE_SCRIPT_HANDLE );
		wp_enqueue_style( self::$PUBLIC_BASE_STYLE_HANDLE );
	} // function

	/**
	 * Enqueue the public registration scripts and stylesheets
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_public_registration_scripts_and_styles() {

		wp_enqueue_script( self::$PUBLIC_REGISTRATION_SCRIPT_HANDLE );

		// This script contains I18 functions and will need translations
		wp_set_script_translations( self::$PUBLIC_REGISTRATION_SCRIPT_HANDLE, 'reg-man-rc' );

		wp_enqueue_style( self::$PUBLIC_REGISTRATION_STYLE_HANDLE );
	} // function

	/**
	 * Enqueue the volunteer area scripts and stylesheets
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_volunteer_area_scripts_and_styles() {
		wp_enqueue_script( self::$VOLUNTEER_AREA_SCRIPT_HANDLE );
		wp_enqueue_style( self::$VOLUNTEER_AREA_STYLE_HANDLE );
	} // function

	/**
	 * Internal method to register the admin javascript and stylesheet
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_base_admin_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'admin/admin-base.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$BASE_ADMIN_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_SCRIPT_HANDLE,
					self::$DATATABLES_SCRIPT_HANDLE,
					'jquery-ui-tabs',
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'admin/admin-base.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$BASE_ADMIN_STYLE_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_STYLE_HANDLE,
					self::$JQUERY_STYLE_HANDLE,
					self::$DATATABLES_STYLE_HANDLE,
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the base admin scripts and styles
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_base_admin_script_and_styles() {
		wp_enqueue_script( self::$BASE_ADMIN_SCRIPT_HANDLE );
		// This script contains I18 functions and will need translations
		wp_set_script_translations( self::$BASE_ADMIN_SCRIPT_HANDLE, 'reg-man-rc' );
		wp_enqueue_style( self::$BASE_ADMIN_STYLE_HANDLE );
	} // function

	/**
	 * Internal method to register the admin REST javascript
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
/* FIXME - I'm not sure if this is needed
	private static function register_admin_rest_script() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'admin/admin-rest.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$ADMIN_REST_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					'wp-api', // The wordpress backbone javascript client for the REST api
			),
			$version,
			$footer = FALSE
		);
	} // function
*/
	/**
	 * Enqueue the base admin scripts and styles
	 *
	 * @return	void
	 * @since	0.1.0
	 */
/*
	public static function enqueue_admin_rest_script() {
		wp_enqueue_script( self::$ADMIN_REST_SCRIPT_HANDLE );
	} // function
*/

	/**
	 * Internal method to register the admin javascript and stylesheet for term ordering
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_term_order_admin_scripts_and_styles() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'admin/admin-term-order.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$TERM_ORDER_ADMIN_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					self::$BASE_ADMIN_SCRIPT_HANDLE,
					self::$AJAX_FORMS_SCRIPT_HANDLE,
//					'jquery', 'jquery-ui-core',
					'jquery-ui-dialog',
					'jquery-ui-sortable',
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'admin/admin-term-order.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$TERM_ORDER_ADMIN_STYLE_HANDLE,
			$src,
			$dependencies = array(
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the term ordering admin scripts and styles
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_term_order_admin_scripts_and_styles() {
		wp_enqueue_script( self::$TERM_ORDER_ADMIN_SCRIPT_HANDLE );
		// This script contains I18 functions and will need translations
		wp_set_script_translations( self::$TERM_ORDER_ADMIN_SCRIPT_HANDLE, 'reg-man-rc' );
		wp_enqueue_style( self::$TERM_ORDER_ADMIN_STYLE_HANDLE );
	} // function



	/**
	 * Internal method to register the stats view javascript and stylesheet
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_stats_view() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'shared/stats-view.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$STATS_VIEW_SCRIPT_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_SCRIPT_HANDLE,
					self::$AJAX_FORMS_SCRIPT_HANDLE,
					self::$SELECT2_SCRIPT_HANDLE,
					self::$CHARTJS_SCRIPT_HANDLE,
					self::$DATATABLES_SCRIPT_HANDLE,
					'jquery-ui-tabs',
			),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( 'shared/stats-view.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$STATS_VIEW_STYLE_HANDLE,
			$src,
			$dependencies = array(
					self::$SHARED_BASE_STYLE_HANDLE,
//					self::$CHARTJS_STYLE_HANDLE, // NOTE THAT there is no CHARTJS style
					self::$DATATABLES_STYLE_HANDLE,
					self::$JQUERY_STYLE_HANDLE,
			),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the stats view scripts and styles
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_stats_view_script_and_styles() {
		wp_enqueue_script( self::$STATS_VIEW_SCRIPT_HANDLE );
		wp_enqueue_style( self::$STATS_VIEW_STYLE_HANDLE );
	} // function

	/**
	 * Internal method to register the Ajax forms script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_ajax_forms() {
		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'shared/ajax-forms.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$AJAX_FORMS_SCRIPT_HANDLE,
			$src,
			$dependencies = array( self::$SHARED_BASE_SCRIPT_HANDLE ),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Enqueue Ajax forms scripts
	 *
	 * This method can be called by any user interface that uses an ajax form.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_ajax_forms() {
		wp_enqueue_script( self::$AJAX_FORMS_SCRIPT_HANDLE );
		// This script contains I18 functions and will need translations
		wp_set_script_translations( self::$AJAX_FORMS_SCRIPT_HANDLE, 'reg-man-rc' );
		wp_enqueue_style( self::$SHARED_BASE_STYLE_HANDLE ); // Ajax forms also requires some shared styles
	} // function

	private static function register_jquery_theme() {
		$version = '1.12.1';
		$src = plugins_url("external_lib/jquery/ui/$version/themes/smoothness/jquery-ui.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_style(
			self::$JQUERY_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
	} // function

	private static function register_google_maps() {
		if ( Map_View::get_is_map_view_enabled() ) {
			$key = Settings::get_maps_api_key();
			$url = "https://maps.googleapis.com/maps/api/js";
			$args = array();
			$args['key'] = $key;
			$args['libraries'] = 'places';
			$locale_parts = explode( '_', get_locale() );
			$count = count( $locale_parts );
			if ( $count == 1 ) {
				$args[ 'language' ] = $locale_parts[0];
			} elseif ( $count == 2 ) {
				$args[ 'language' ] = $locale_parts[0];
				$args[ 'region' ] = $locale_parts[1];
			} // endif

			$version = FALSE; // Google determines the right version
			$url = add_query_arg( $args, $url );
			wp_register_script(
				self::$GOOGLE_MAPS_API_SCRIPT_HANDLE,
				$url,
				$dependencies = array( self::$SHARED_BASE_SCRIPT_HANDLE ),
				$version,
				$footer = FALSE
			);

			$version = \Reg_Man_RC\PLUGIN_VERSION;
			$src = plugins_url( 'shared/google-maps.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
			wp_register_script(
				self::$GOOGLE_MAPS_INTERNAL_SCRIPT_HANDLE,
				$src,
				$dependencies = array(
					self::$GOOGLE_MAPS_API_SCRIPT_HANDLE,
					self::$AJAX_FORMS_SCRIPT_HANDLE, // We use Ajax forms to dynamically load markers
				),
				$version,
				$footer = FALSE
			);

			$version = \Reg_Man_RC\PLUGIN_VERSION;
			$src = plugins_url( 'shared/google-maps.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
			wp_register_style(
				self::$GOOGLE_MAPS_INTERNAL_STYLE_HANDLE,
				$src,
				$dependencies = array( ),
				$version,
				$media = 'all'
			);
		} // endif
	} // function

	/**
	 * Enqueue google maps script
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_google_maps() {
		if ( Map_View::get_is_map_view_enabled() ) {
			wp_enqueue_script( self::$GOOGLE_MAPS_API_SCRIPT_HANDLE );
			wp_enqueue_script( self::$GOOGLE_MAPS_INTERNAL_SCRIPT_HANDLE );
			// This script contains I18 functions and will need translations
			wp_set_script_translations( self::$GOOGLE_MAPS_INTERNAL_SCRIPT_HANDLE, 'reg-man-rc' );
			wp_enqueue_style( self::$GOOGLE_MAPS_INTERNAL_STYLE_HANDLE );
		} // endif
	} // function

	/**
	 * Internal method to register the Datatables style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_datatables() {
		$version = '1.10.16';
		$src = plugins_url("external_lib/datatables/$version/datatables.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_style(
			self::$DATATABLES_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/$version/datatables.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_script(
			self::$DATATABLES_SCRIPT_HANDLE,
			$src,
			$dependencies = array( 'jquery' ),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Internal method to register the Datatables RowGroup extension style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_datatables_rowgroup_extension() {
		$version = '1.1.1';
		$src = plugins_url("external_lib/datatables/RowGroup/$version/rowGroup.datatables.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_style(
			self::$DATATABLES_ROWGROUP_STYLE_HANDLE,
			$src,
			$dependencies = array( self::$DATATABLES_STYLE_HANDLE ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/RowGroup/$version/datatables.rowGroup.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_script(
			self::$DATATABLES_ROWGROUP_SCRIPT_HANDLE,
			$src,
			$dependencies = array( self::$DATATABLES_SCRIPT_HANDLE ),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Enqueue Datatables scripts and styles
	 *
	 * This method can be called by any user interface that uses Datatables.
	 * Calling this function will cause the correct styles and scripts to be enqueued.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_datatables() {
		wp_enqueue_script( self::$DATATABLES_SCRIPT_HANDLE );
		wp_enqueue_style( self::$DATATABLES_STYLE_HANDLE );
	} // function

	/**
	 * Enqueue Datatables RowGroup extension scripts and styles
	 *
	 * This method can be called by any user interface that uses the RowGroup extension for Datatables.
	 * Calling this function will cause the correct styles and scripts to be enqueued.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_datatables_rowgroup_extension() {
		wp_enqueue_script( self::$DATATABLES_ROWGROUP_SCRIPT_HANDLE );
		wp_enqueue_style( self::$DATATABLES_ROWGROUP_STYLE_HANDLE );
	} // function

	/**
	 * Internal method to register the Chart.js style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_chartjs() {
		$version = '3.5.0';
/*
		$src = plugins_url("external_lib/Chart.js/$version/Chart.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_style(
			self::$CHARTJS_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
*/
		$src = plugins_url("external_lib/Chart.js/$version/chart.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_script(
			self::$CHARTJS_SCRIPT_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Enqueue Chart.js scripts and styles
	 *
	 * Calling this function will cause the correct styles and scripts to be enqueued.
	 * NOTE that Chart.js is only registered for the admin interface.  If this method is called when building a page
	 * for the public interface it will do nothing.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_chartjs() {
//		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			wp_enqueue_script( self::$CHARTJS_SCRIPT_HANDLE );
//			wp_enqueue_style( self::$CHARTJS_STYLE_HANDLE );
//		} else {
//			$msg = __( 'ERROR: Cannot enqueue chart.js on public interface.', 'reg-man-rc' );
//			Error_Log::log_msg( $msg );
//		} // endif
	} // function

	/**
	 * Internal method to register the jstree style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
/* FIXME - not using JSTree
	private static function register_jstree() {
		$version = '3.3.9';
		$src = plugins_url("external_lib/jstree/$version/themes/default/style.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_style(
			self::$JSTREE_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/$version/jstree.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME);
		wp_register_script(
			self::$JSTREE_SCRIPT_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$footer = FALSE
		);
	} // function
	public static function enqueue_jstree() {
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			wp_enqueue_script( self::$JSTREE_SCRIPT_HANDLE );
			wp_enqueue_style( self::$JSTREE_STYLE_HANDLE );
		} else {
			$msg = __('ERROR: Cannot enqueue jstree on public interface.', 'reg-man-rc' );
			Error_Log::log_msg( $msg );
		} // endif
	} // function
*/

	/**
	 * Internal method to register the ToolTipster style and scripts
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_tooltipster() {
		$version = '3.0';
		$src = plugins_url( "external_lib/Tooltipster/v{$version}/dist/js/tooltipster.bundle.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$TOOLTIPSTER_SCRIPT_HANDLE,
			$src,
			$dependencies = array( 'jquery' ),
			$version,
			$footer = FALSE
		);

		$src = plugins_url( "external_lib/Tooltipster/v{$version}/dist/css/tooltipster.bundle.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$TOOLTIPSTER_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);

		$src = plugins_url( "external_lib/Tooltipster/v{$version}/dist/css/plugins/tooltipster/sideTip/themes/tooltipster-sideTip-shadow.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$TOOLTIPSTER_SHADOW_STYLE_HANDLE,
			$src,
			$dependencies = array( self::$TOOLTIPSTER_STYLE_HANDLE ),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Internal method to register the FullCalendar style and scripts
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_fullcalendar() {
		$version = '5.5.0';
		$src = plugins_url( "external_lib/FullCalendar/$version/lib/main.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$FULLCALENDAR_SCRIPT_HANDLE,
			$src,
			$dependencies = array( 'jquery' ),
			$version,
			$footer = FALSE
		);

		$src = plugins_url( "external_lib/FullCalendar/$version/lib/locales-all.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$FULLCALENDAR_LOCALES_SCRIPT_HANDLE,
			$src,
			$dependencies = array( 'jquery'  ),
			$version,
			$footer = FALSE
		);

		$src = plugins_url( "external_lib/FullCalendar/$version/lib/main.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$FULLCALENDAR_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);

		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'shared/fullcalendar.js', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		$dependencies = array(
			self::$FULLCALENDAR_SCRIPT_HANDLE,
			self::$TOOLTIPSTER_SCRIPT_HANDLE,
			self::$SHARED_BASE_SCRIPT_HANDLE,
		);
		if ( Map_View::get_is_map_view_enabled() ) {
			$dependencies[] = self::$GOOGLE_MAPS_INTERNAL_SCRIPT_HANDLE;
		} // endif
		wp_register_script(
			self::$FULLCALENDAR_INTERNAL_SCRIPT_HANDLE,
			$src,
			$dependencies,
			$version,
			$footer = FALSE
		);

		$version = \Reg_Man_RC\PLUGIN_VERSION;
		$src = plugins_url( 'shared/fullcalendar.css', \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		$dependencies = array(
			self::$SHARED_BASE_STYLE_HANDLE,
			self::$TOOLTIPSTER_SHADOW_STYLE_HANDLE,
			self::$TOOLTIPSTER_STYLE_HANDLE
		);
		if ( Map_View::get_is_map_view_enabled() ) {
			$dependencies[] = self::$GOOGLE_MAPS_INTERNAL_STYLE_HANDLE;
		} // endif
		wp_register_style(
			self::$FULLCALENDAR_INTERNAL_STYLE_HANDLE,
			$src,
			$dependencies,
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue FullCalendar scripts and styles
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_fullcalendar() {
		wp_enqueue_script( self::$FULLCALENDAR_SCRIPT_HANDLE );
		wp_enqueue_script( self::$FULLCALENDAR_LOCALES_SCRIPT_HANDLE );
		wp_enqueue_script( self::$FULLCALENDAR_INTERNAL_SCRIPT_HANDLE );
		// This script contains I18 functions and will need translations
		wp_set_script_translations( self::$FULLCALENDAR_INTERNAL_SCRIPT_HANDLE, 'reg-man-rc' );
		wp_enqueue_style( self::$FULLCALENDAR_STYLE_HANDLE );
		wp_enqueue_style( self::$FULLCALENDAR_INTERNAL_STYLE_HANDLE );
		self::enqueue_google_maps();
	} // function

	/**
	 * Internal method to register the Select2 style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_select2() {
		$version = '4.1.0';
		$src = plugins_url( "external_lib/select2/$version/dist/js/select2.full.min.js", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_script(
			self::$SELECT2_SCRIPT_HANDLE,
			$src,
			$dependencies = array( 'jquery' ),
			$version,
			$footer = FALSE
		);
		$src = plugins_url( "external_lib/select2/$version/dist/css/select2.min.css", \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		wp_register_style(
			self::$SELECT2_STYLE_HANDLE,
			$src,
			$dependencies = array( self::$JQUERY_STYLE_HANDLE ),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue select2 scripts and styles
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_select2() {
		wp_enqueue_script( self::$SELECT2_SCRIPT_HANDLE );
		wp_enqueue_style( self::$SELECT2_STYLE_HANDLE );
	} // function

} // class