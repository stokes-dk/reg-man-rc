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
 * @since             0.1.0
 * @package           Reg_Man_Rc
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

// Set up my class autoloader -- This must be ahead of anything that uses a class
spl_autoload_register( 'Reg_Man_RC\reg_man_rc_autoloader' );
function reg_man_rc_autoloader( $class_name ) {
	$prefix = 'Reg_Man_RC'; // look for classes that start with our prefix
	$length = strlen( $prefix );
	if (strncmp($prefix, $class_name, $length) !== 0) {
		$result = FALSE; // this class name does not start with our prefix so we can't load it
	} else {
		// get the root directory under which all my classes are located
		$class_root = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class_lib';
		// get the relative class path name for the class to be loaded
		$relative_class_path = substr($class_name, $length); // everything after our prefix indicates the relative path to the desired class
		// replace namespace separators with directory separators
		$relative_class_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class_path);
		$path_parts = pathinfo($relative_class_path); // break this path into its parts
		$relative_dir = $path_parts['dirname'];
		$class_dir = $class_root . DIRECTORY_SEPARATOR . $relative_dir;
		// Remove any trailing slashes
		$class_dir = rtrim($class_dir, DIRECTORY_SEPARATOR);
		$class_base_name = $path_parts['basename'];
		// The class file is all in lowercase with underscores replaced with hyphens.  It starts with '-class' and ends with '.php'
		// So the file for My_Widget would be class-my-widget.php
		$class_base_name = 'class-' . str_replace( '_', '-', strtolower( $class_base_name ) ) . '.php';
		$class_file_path = $class_dir . DIRECTORY_SEPARATOR . $class_base_name;
		if (file_exists($class_file_path)) {
			require_once $class_file_path;
			$result = TRUE;
		} else {
			error_log("ERROR: Unable to load class $class_name.  FILE DOES NOT EXIST: $class_file_path");
			$result = FALSE;
		} // endif
	} // endif
	return $result;
} // function

// Set up the hook for plugin activation, deactivation
register_activation_hook(__FILE__, array('Reg_Man_RC\Plugin_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Reg_Man_RC\Plugin_Deactivator', 'deactivate'));
