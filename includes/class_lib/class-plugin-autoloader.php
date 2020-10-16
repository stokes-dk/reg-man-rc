<?php
namespace Reg_Man_RC;

/**
 * The autoloader function for the plugin
 *
 * This class contains the method used to autoload classes for the plugin.  It is registered as a class autoloader in the plugin's
 * bootstrap file.
 *
 * @since v0.1.0
 *
 */
class Plugin_Autoloader {
	/**
	 * Autoloader function to load classes on demand according to PSR-4 specification
	 *
	 * This function is registered by the plugin's bootstrap file to autoload classes.
	 * If the specified `$class_name` does not start with the namespace for this plugin (Reg_Man_RC)
	 * then the function will return FALSE (which will allow other autoloaders to attempt to load the class);
	 * otherwise this function will attempt to find the class file and include it.
	 * All class files are stored under the 'includes/class_ilb' directory of the plugin
	 * and their namespace parts (excluding the plugin root namespace, Reg_Man_RC) represent subdirectories.
	 * Class file names adhere to the Wordpress file naming conventions for classes: underscores are converted to hyphes,
	 * filenames are all lowercase, filenames start with 'class-' and end with '.php'.
	 * For example, the class 'Reg_Man_RC\Model\Event is loaded from the file: includes\class_lib\model\class-event.php.
	 *
	 * @since	v0.1.0
	 * @param	string	$class_name	The name of the class to be loaded
	 * @return	boolean	TRUE if `$class_name` starts with the correct namespace and the class file is found, FALSE otherwise
	 *
	 */
	public static function autoload($class_name) {
		$namespace = __NAMESPACE__; // look for classes that start with our namespace
		$length = strlen( $namespace );
		if (strncmp($namespace, $class_name, $length) !== 0) {
			$result = FALSE; // this class name does not start with our namespace so we can't load it
		} else {
			// get the root directory under which all my classes are located
			$class_root = realpath( plugin_dir_path( __FILE__ ) );
			// get the relative class path name for the class to be loaded
			$relative_class_path = substr($class_name, $length); // everything after our namespace indicates the relative path to the desired class
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
				/* translators: %1$s is a class name, %2$s is file path. */
				error_log( sprintf( __('ERROR: Unable to load class %1$s.  FILE DOES NOT EXIST: %2$s', 'reg-man-rc' ), $class_name, $class_file_path ) );
				$result = FALSE;
			} // endif
		} // endif
		return $result;
	} // function
} // class