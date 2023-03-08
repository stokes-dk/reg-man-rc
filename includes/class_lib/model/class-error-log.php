<?php
namespace Reg_Man_RC\Model;

class Error_Log {

	private static final function get_caller_location() {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
		// The backtrace contains the call stack as an array of associative arrays.
		// It can be read like: "File X at line Y called function F in class C"
		// To print out the right details I need info from the first two elements.
		// The array looks like this:
		// [0] => ( the call to this method, we're not interested in this one )
		//		'file'		=> File that called this method (will be this file since I'm only called from within this classs
		//		'line'		=> Line that called this method
		//		'function'	=> The function that was called, so backtrace[0]['function'] contains THIS METHOD
		//		'class'		=> The class that was called, so backtrace[0]['class'] contains THIS CLASS
		//		'type'		=> The type of call, '->' for instance method, '::' for static method - always '::' because this method is static
		// [1] => ( the call to the logging method, e.g. Error_Log::log_msg(), I'll use the line number here )
		//		'file'		=> File originating the previous call (the file that called the logging method)
		//		'line'		=> Line originating the previous call (I will print this)
		//		'function'	=> The function that was called, so backtrace[1]['function'] contains the logging method called
		//		'class'		=> The class that was called, so backtrace[1]['class'] contains THIS CLASS
		//		'type'		=> The type of call, '->' for instance method, '::' for static method
		// [2] => ( the call to the method that called the logging method, this is the frame of most interest )
		//		'file'		=> File originating the previous call (the file that called the logging method)
		//		'line'		=> Line originating the previous call
		//		'function'	=> The function that was called, so backtrace[2]['function'] contains the method I want to print out
		//		'class'		=> The class that was called, so backtrace[2]['class'] contains the class I want to print out
		//		'type'		=> The type of call, '->' for instance method, '::' for static method (I will print this out)
		$class = 	( isset( $backtrace[2] ) && isset( $backtrace[2]['class'] ) ) 		? $backtrace[2]['class']	: $backtrace[2]['file'];
		$function = ( isset( $backtrace[2] ) && isset( $backtrace[2]['function'] ) )	? $backtrace[2]['function'] : 'NO FUNCTION';
		$line = 	( isset( $backtrace[1] ) && isset( $backtrace[1]['line'] ) )		? $backtrace[1]['line']		: 'NO LINE';
		$type = 	( isset( $backtrace[2] ) && isset( $backtrace[2]['type'] ) )		? $backtrace[2]['type'] 	: ' - ';

		$result = "{$class}{$type}{$function}() [line {$line}]";

		return $result;

	} // function

	public static final function log_msg( $msg ) {
		$prefix = self::get_caller_location();
		error_log( "$prefix $msg" );
	} // function

	public static final function log_exception( $msg, $exception ) {
		$prefix = self::get_caller_location();
		error_log( "$prefix $msg : $exception" );
	} // function

	/**
	 * Log a WP_Error to the error log
	 * @param	\WP_Error	$wp_error
	 */
	public static final function log_wp_error( $msg, $wp_error ) {
		$prefix = self::get_caller_location();
		error_log( "$prefix $msg.  WP_Error : " . print_r( $wp_error->get_error_messages() ) );
	} // function


	/**
	 * Dump zero or more values to the error log along with the class, method and line number of the caller.
	 * E.g. var_dump( $var ) will log a message like:
	 *   Namespace\Control\My_Class->a_method() [line 114]
	 *   string(5) "Value"
	 * @param	mixed	$values	A list of values to dump
	 */
	public static function var_dump( ...$values ) {

		ob_start();
			$prefix = self::get_caller_location();
			echo $prefix;
			echo PHP_EOL;
			foreach ( $values as $cur_val ) {
				var_dump( $cur_val );
			} // endfor
		$dump = ob_get_clean();
		error_log( $dump );

	} // function

	public static function log_backtrace() {
		$prefix = self::get_caller_location();
		ob_start();
			debug_print_backtrace();
		$dump = ob_get_clean();
		error_log( "$prefix\n$dump" );
	} // function

} // class