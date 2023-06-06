<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area.  It defines constants for the plugin version and bootstrap filename,
 * sets up the autoloader for classes and tells the plugin controller to register
 * the appropriate hooks and filters.
 *
 * xxx@link			http://example.com
 * @version			v0.5.0
 * @since			v0.1.0
 *
 * @wordpress-plugin
 * Plugin Name:		Registration Manager for Repair Café
 * xxxPlugin URI:	PLUGIN SITE HERE
 * Description:		Allows a repair café organization to create a calendar of events, register items and volunteers and view statistics.
 * Version:			0.5.0
 * Author:			David Stokes
 * xxxAuthor URI:	YOUR SITE HERE
 * License:			GPL-2.0+
 * License URI:		http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:		reg-man-rc
 * Domain Path:		/languages
 */

namespace Reg_Man_RC;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
} // endif

/**
 *  Define the plugin version constant.  This is under my namespace and can be accessed as \Reg_Man_RC\PLUGIN_VERSION
 */
const PLUGIN_VERSION = '0.5.0';

/**
 *  Define the plugin bootstrap filename.  This is used to do things like get a plugin_url()
 */
const PLUGIN_BOOTSTRAP_FILENAME = __FILE__;

/**
 *  Set up my class autoloader -- This must be ahead of everything else that uses a class
 */
require_once realpath( plugin_dir_path( __FILE__ ) ) . '/includes/class_lib/class-plugin-autoloader.php';
spl_autoload_register( '\Reg_Man_RC\Plugin_Autoloader::autoload' );

/**
 *  Set up the composer autoloader -- This must also be ahead of everything else that uses a class
 */
if ( is_readable( realpath( plugin_dir_path( __FILE__ ) ) . '/external_lib/Recurr/vendor/autoload.php' ) ) {
	require realpath( plugin_dir_path( __FILE__ ) ) . '/external_lib/Recurr/vendor/autoload.php';
} // endif

/**
 * Set up the action and filter hooks for the plugin
 */
\Reg_Man_RC\Control\Plugin_Controller::register();
