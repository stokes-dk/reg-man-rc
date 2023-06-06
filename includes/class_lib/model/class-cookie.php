<?php
namespace Reg_Man_RC\Model;

/**
 * This class provides an interface for working with cookies.
 *
 * @since v0.1.0
 *
 */
class Cookie {

	public static function get_cookie( $name ) {
		$result = isset( $_COOKIE[ $name ] ) ? $_COOKIE[ $name ] : NULL;
		return $result;
	} // function

	/**
	 * Set a cookie.
	 * @param string	$name			The name of the cookie.
	 * @param string	$value			The value of the cookie.
	 * 									This value is stored on the clients computer; do not store sensitive information.
	 * @param string	$expires		The number of seconds from now the cookie expires.
	 * 									The caller may use a constant like YEAR_IN_SECONDS, MONTH_IN_SECONDS etc.
	 * 									If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes).
	 * @param string	$path			The path on the server in which the cookie will be available on.
	 * 									If set to '/', the cookie will be available within the entire domain.
	 * 									If set to '/foo/', the cookie will only be available within the /foo/ directory
	 * 									and all sub-directories such as /foo/bar/ of domain.
	 * @param string	$domain			The (sub)domain that the cookie is available to.
	 * @param boolean	$is_secure		Indicates that the cookie should only be transmitted over
	 * 									a secure HTTPS connection from the client.
	 * @param boolean	$is_http_only	When TRUE the cookie is made accessible ONLY through HTTP protocol.
	 * @param string	$same_site		One of 'None', 'Lax' or 'Strict'.
	 * 									If using 'None' then $is_secure must be set to TRUE.
	 * @return	boolean		The result from setcookie() which is FALSE if output exists prior to calling this function
	 * 						and TRUE if the cookie was set successfully.
	 * 						A TRUE result does not guarantee that the user accepted the cookie
	 */
	public static function set_cookie( $name, $value, $expires = 0, $path = COOKIEPATH, $domain = COOKIE_DOMAIN,
			$is_secure = FALSE, $is_http_only = FALSE, $same_site = 'Lax' ) {
		if ( $expires != 0 ) {
			$expires = current_time( 'timestamp' ) + $expires;
		} // endif
		if ( version_compare( PHP_VERSION, '7.3', '>=' ) ) {
			// Version 7.3 and higher supports the samesite argument which will be required in future so use it if possible
			$options = array(
					'expires'	=> $expires,
					'path'		=> $path,
					'domain'	=> $domain,
					'secure'	=> $is_secure,
					'httponly'	=> $is_http_only,
					'samesite'	=> $same_site
			);
			$result = setcookie( $name, $value, $options );
		} else {
			// Versions before 7.3 will not use the samesite argument
			$result = setcookie( $name, $value, $expires, $path, $domain, $is_secure, $is_http_only );
		} // endif
		if ( $result == FALSE ) {
			$format = __( 'ERROR: Failed to set cookie %1$s', 'reg-man-rc' );
			$msg = sprintf( $format, $name );
			Error_Log::log_msg( $msg );
			Error_Log::var_dump( func_get_args() ); // show all the function arguments
		} // endif
		return $result;
	} // function

	public static function remove_cookie( $cookie_name, $path = COOKIEPATH, $domain = COOKIE_DOMAIN ) {
		$expires = current_time( 'timestamp' ) - DAY_IN_SECONDS; // go back a day to make sure it's removed
		$value = '';
		$result = setcookie( $cookie_name, $value, $expires, $path, $domain );
		if ( $result == FALSE ) {
			$format = __( 'ERROR: Failed to remove cookie %1$s', 'reg-man-rc' );
			$msg = sprintf( $format, $cookie_name );
			Error_Log::log_msg( $msg );
			Error_Log::var_dump( func_get_args() ); // show all the function arguments
		} // endif
		return $result;
	} // function
} // class