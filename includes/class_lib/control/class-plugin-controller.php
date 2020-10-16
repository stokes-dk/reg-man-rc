<?php
namespace Reg_Man_RC\Control;

/**
 * Sets up the action and filter hooks for the plugin like plugin activation, deactivation, init hook and so on
 *
 * @since	v0.1.0
 *
 */
class Plugin_Controller {
	/**
	 * Initialize plugin action and filter hooks.
	 *
	 * This method is called by the plugin's bootstrap file to set up the plugin's action and filter hooks.
	 * This is the only method in this class that should be called directly, all other methods are invoked
	 * automatically by an action or filter hook.
	 *
	 * @return	void
	 * @param	string	$plugin_bootstrap_filename	The filename (__FILE__) of the plugin's bootstrap file
	 *
	 * @since	v0.1.0
	 */
	public static function register($plugin_bootstrap_filename) {
		register_activation_hook($plugin_bootstrap_filename, array(__CLASS__, 'do_plugin_activation'));
		register_deactivation_hook($plugin_bootstrap_filename, array(__CLASS__, 'do_plugin_deactivation'));
		add_action('init', array(__CLASS__, 'do_init'));
		Template_Controller::initialize();
	} // function

	/**
	 * Activate the plugin
	 *
	 * This method is called automatically when the plugin is activated.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function do_plugin_activation() {
		\Reg_Man_RC\View\Pub\Visitor_Reg_Manager::do_plugin_activation();
	} // function

	/**
	 * Deactivate the plugin
	 *
	 * This method is called automatically when the plugin is deactivated.
	 *
	 * @since v0.1.0
	 */
	public static function do_plugin_deactivation() {
		\Reg_Man_RC\View\Pub\Visitor_Reg_Manager::do_plugin_deactivation();
	} // function

	/**
	 * Perform plugin initialization for init hook.
	 *
	 * This method is called automatically when the init hook is triggered.
	 *
	 * @since v0.1.0
	 */
	public static function do_init() {
		self::load_textdomain();
		if ( is_admin() ) { // is_admin() ONLY checks if the dashboard is being rendered, not if user is admin
			// Register hooks for the admin side of the interface
			add_action( 'admin_enqueue_scripts', array('Reg_Man_RC\Control\Scripts_And_Styles', 'register_admin_scripts_and_styles') );
		} else {
			add_action( 'wp_enqueue_scripts', array('Reg_Man_RC\Control\Scripts_And_Styles', 'register_public_scripts_and_styles' ) );
			\Reg_Man_RC\View\Pub\Visitor_Reg_Manager::register();
		} // endif
	} // function

	/**
	 * Load the plugin text domain for translation
	 *
	 * @since v0.1.0
	 */
	private static function load_textdomain() {
		$lang_dir = dirname( dirname( dirname( dirname( plugin_basename(__FILE__) ) ) ) ) . '/languages';
		load_plugin_textdomain('reg-man-rc', FALSE, $lang_dir);
	} // function
} // class