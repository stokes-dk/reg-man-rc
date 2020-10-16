<?php
/**
 * * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * xxx@link              http://example.com
 * @since             v0.1.0
 *
 * @wordpress-plugin
 * Plugin Name:     Registration Manager for Repair Café
 * xxxPlugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Version:         0.1.0
 * Author:          David Stokes
 * xxxAuthor URI:      YOUR SITE HERE
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     reg-man-rc
 * Domain Path:     /languages
 */

namespace Reg_Man_RC;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
} // endif

/**
 *  Set up my class autoloader -- This must be ahead of everything else that uses a class
 */
require_once realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class_lib/class-plugin-autoloader.php';
spl_autoload_register( 'Reg_Man_RC\Plugin_Autoloader::autoload' );

/**
 * Set up the action and filter hooks for the plugin
 */
\Reg_Man_RC\Control\Plugin_Controller::register(__FILE__);

