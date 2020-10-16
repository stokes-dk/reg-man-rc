<?php
namespace Reg_Man_RC\Control;

/**
 * Facilitates the registration and enqueueing of scripts and styles for the plugin
 *
 * This class contains the methods used to register scripts and styles (js and css) for the admin and public interfaces.
 * It also supplies methods to facilitate enqueueing those scripts and styles.
 *
 * @version v0.1.0
 * @since v0.1.0
 *
 */
class Scripts_And_Styles {

	// The following handles are used internally to register and enqueue styles and scripts
	private static $JQUERY_STYLE_HANDLE = 'reg-man-rc-jquery-theme';

	private static $DATATABLES_STYLE_HANDLE = 'reg-man-rc-datatables-style';
	private static $DATATABLES_SCRIPT_HANDLE = 'reg-man-rc-datatables-script';
	private static $DATATABLES_ROWGROUP_STYLE_HANDLE = 'reg-man-rc-datatables-rowgroup-style';
	private static $DATATABLES_ROWGROUP_SCRIPT_HANDLE = 'reg-man-rc-datatables-rowgroup-script';

	private static $CHARTJS_STYLE_HANDLE = 'reg-man-rc-chartjs-style';
	private static $CHARTJS_SCRIPT_HANDLE = 'reg-man-rc-chartjs-script';

	private static $JSTREE_STYLE_HANDLE = 'reg-man-rc-jstree-style';
	private static $JSTREE_SCRIPT_HANDLE = 'reg-man-rc-jstree-script';

	/**
	 * Called automatically by the plugin to register the scripts and styles for the admin interface
	 *
	 * This function is called during the admin_enqueue_scripts hook to register the scripts and styles
	 * used by the plugin in the admin interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 * @see 	Scripts_And_Styles::register_scripts_and_styles()
	 */
	public static function register_admin_scripts_and_styles() {
		self::register_shared_scripts_and_styles();
		self::register_chartjs();
		self::register_jstree();
	} // function

	/**
	 * Called automatically by the plugin to register the scripts and styles for the public interface
	 *
	 * This function is called during the wp_enqueue_scripts hook to register the scripts and styles
	 * used by the plugin in the public interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register_public_scripts_and_styles() {
		self::register_shared_scripts_and_styles();
	} // function

	/**
	 * Internal function to register the scripts and styles shared by both the the public interface and the admin interface
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_shared_scripts_and_styles() {
		// The following scripts and styles are used in both the public and admin interface sides
		self::register_jquery_theme();
		self::register_datatables();
		self::register_datatables_rowgroup_extension();
	} // function

	/**
	 * Internal function to register the jquery theme
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_jquery_theme() {
		$version = '1.11.3';
		$src = plugins_url("external_lib/jquery/ui/$version/themes/smoothness/jquery-ui.css", 'reg_man_rc');
		wp_register_style(
			self::$JQUERY_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
	} // function

	/**
	 * Enqueue the JQuery theme
	 *
	 * @return	void
	 * @since	0.1.0
	 */
	public static function enqueue_jquery_theme() {
		wp_enqueue_style( self::$JQUERY_STYLE_HANDLE );
	} // function

	/**
	 * Internal function to register the Datatables style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_datatables() {
		$version = '1.10.16';
		$src = plugins_url("external_lib/datatables/$version/datatables.min.css", 'reg_man_rc');
		wp_register_style(
			self::$DATATABLES_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/$version/datatables.min.js", 'reg_man_rc');
		wp_register_script(
			self::$DATATABLES_SCRIPT_HANDLE,
			$src,
			$dependencies = array('jquery'),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Internal function to register the Datatables RowGroup extension style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_datatables_rowgroup_extension() {
		$version = '1.1.1';
		$src = plugins_url("external_lib/datatables/RowGroup/$version/rowGroup.datatables.min.css", 'reg_man_rc');
		wp_register_style(
			self::$DATATABLES_ROWGROUP_STYLE_HANDLE,
			$src,
			$dependencies = array( self::$DATATABLES_STYLE_HANDLE ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/RowGroup/$version/datatables.rowGroup.min.js", 'reg_man_rc');
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
	 * This function can be called by any user interface that uses Datatables.
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
	 * This function can be called by any user interface that uses the RowGroup extension for Datatables.
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
	 * Internal function to register the Chart.js style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_chartjs() {
		$version = '2.9.3';
		$src = plugins_url("external_lib/Chart.js/$version/Chart.min.css", 'reg_man_rc');
		wp_register_style(
			self::$CHARTJS_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/$version/Chart.bundle.min.js", 'reg_man_rc');
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
	 * This function can be called by any admin-side user interface that uses Chart.js.
	 * Calling this function will cause the correct styles and scripts to be enqueued.
	 * NOTE that Chart.js is only registered for the admin interface.  If this method is called when building a page
	 * for the public interface it will do nothing.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_chartjs() {
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			wp_enqueue_script( self::$CHARTJS_SCRIPT_HANDLE );
			wp_enqueue_style( self::$CHARTJS_STYLE_HANDLE );
		} else {
			/* translators: %1$s is the current class name, %2$s is the current function name. */
			error_log( sprintf( __('ERROR: Cannot enqueue chart.js on public interface.  %1$s::%2$s', 'reg-man-rc' ), __CLASS__, __FUNCTION__ ) );
		} // endif
	} // function

	/**
	 * Internal function to register the jstree style and script
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	private static function register_jstree() {
		$version = '3.3.9';
		$src = plugins_url("external_lib/jstree/$version/themes/default/style.min.css", 'reg_man_rc');
		wp_register_style(
			self::$JSTREE_STYLE_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$media = 'all'
		);
		$src = plugins_url("external_lib/datatables/$version/jstree.min.js", 'reg_man_rc');
		wp_register_script(
			self::$JSTREE_SCRIPT_HANDLE,
			$src,
			$dependencies = array( ),
			$version,
			$footer = FALSE
		);
	} // function

	/**
	 * Enqueue jstree scripts and styles
	 *
	 * This function can be called by any admin-side user interface that uses jstree.
	 * Calling this function will cause the correct styles and scripts to be enqueued.
	 * NOTE that jstree is only registered for the admin interface.  If this method is called when building a page
	 * for the public interface it will do nothing.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function enqueue_jstree() {
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			wp_enqueue_script( self::$JSTREE_SCRIPT_HANDLE );
			wp_enqueue_style( self::$JSTREE_STYLE_HANDLE );
		} else {
			/* translators: %1$s is the current class name, %2$s is the current function name. */
			error_log( sprintf( __('ERROR: Cannot enqueue jstree on public interface.  %1$s::%2$s', 'reg-man-rc' ), __CLASS__, __FUNCTION__ ) );
		} // endif
	} // function

} // class