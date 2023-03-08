<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Model\Error_Log;

/**
 * The visitor controller
 *
 * This class provides the controller function associated with the Visitor custom post type.
 * It handles AJAX POST operations for things like visitor login.
 * When getting a Visitor from the database we must join our side table that contains the
 *  visitor's personal data including full name and email address.
 *  This data is sensitive and is not stored in the main WP posts table.
 *
 * @since v0.1.0
 *
 */
class Visitor_Controller {

//	const AJAX_VISITOR_LOGIN_ACTION	= 'reg_man_rc_visitor_login_ajax';
//	const VISITOR_AREA_EXIT_ACTION	= 'reg_man_rc_visitor_area_exit';

	/**
	 *  Register the Visitor controller.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// Filter the clauses for visitors so that we can join our side table
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 1000, 2 );

		// Add handler methods for my form posts.
		// Handle user login post.
		// This is not a normal login form because visitors may identify themselves by email only, and may not have a password
		// If a logged-in user, like the system administrator is also be a visitor
		// So we need to accept both "priv" and "no_priv" posts.
		// This is normally done by a non-logged-in user.
//		add_action( 'wp_ajax_' . self::AJAX_VISITOR_LOGIN_ACTION, array(__CLASS__, 'handle_login_form_post' ) );
//		add_action( 'wp_ajax_nopriv_'  . self::AJAX_VISITOR_LOGIN_ACTION, array(__CLASS__, 'handle_login_form_post' ) );

		// Add handler for area exit action.  Users just supply an email so they don't really log out
		// The action is set up using admin_post but it's really a GET triggered by clicking a link
//		$action = self::VISITOR_AREA_EXIT_ACTION;
//		add_action( "admin_post_{$action}", array( __CLASS__, 'handle_visitor_area_exit_action' ) );
//		add_action( "admin_post_nopriv_{$action}", array( __CLASS__, 'handle_visitor_area_exit_action' ) );

	} // function

	/**
	 * Get the href for a link that allows the visitor to exit the area
	 * @return string
	 */
/*
	public static function get_visitor_area_exit_href() {
		$action = self::VISITOR_AREA_EXIT_ACTION;
		$result = admin_url( "admin-post.php?action={$action}" );
		return $result;
	} // function
*/

	/**
	 * Handle the post of the visitor login form from the visitor area.
	 * This may be a login (which includes a password) or non-login which is just an email address.
	 * If an email address is specified and that address corresponds to a visitor
	 */
/*
	public static function handle_login_form_post() {

		// The form data is serialized and put into the formData post argument that Wordpress will pass to me
		// I need to deserialze it into a regular associative array
		$serialized_form_data = isset( $_POST[ 'formData' ] ) ? $_POST[ 'formData' ] : NULL;
		$form_data_array = array();
		parse_str( $serialized_form_data, $form_data_array );
//		Error_Log::var_dump( $form_data_array );
		$ajax_response = Ajax_Form_Response::create();
		$email			= isset( $form_data_array[ 'vol-email' ] )		? trim( $form_data_array[ 'vol-email' ] )	: NULL;
		$pwd			= isset( $form_data_array[ 'vol-pwd' ] )		? trim( $form_data_array[ 'vol-pwd' ] )		: NULL;
		$is_remember	= isset( $form_data_array[ 'is-remember' ] )	? $form_data_array[ 'is-remember' ] == '1'	: FALSE;
		$event_key		= isset( $form_data_array[ 'event-key' ] )		? trim( $form_data_array[ 'event-key' ] )	: NULL;
		$nonce			= isset( $form_data_array[ '_wpnonce' ] )		? $form_data_array[ '_wpnonce' ]			: NULL;

		$visitor		= ! empty( $email ) ? Visitor::get_visitor_by_email( $email )	 : NULL;

//		Error_Log::var_dump( $email, $is_remember );

		if ( ! wp_verify_nonce( $nonce, self::AJAX_VISITOR_LOGIN_ACTION ) ) {

			$error_msg = __( 'Invalid or missing security code.  Please refresh this page and try again.', 'reg-man-rc' );
			$ajax_response->add_error( '_wpnonce', $nonce, $error_msg );

		} elseif( empty( $email ) ) {

			$error_msg = __( 'An email address is required', 'reg-man-rc' );
			$ajax_response->add_error( 'vol-email', $email, $error_msg );

		} elseif( empty( $visitor ) ) {

			$error_msg = __( 'Visitor email not found. ', 'reg-man-rc' );
			$ajax_response->add_error( 'vol-email', $email, $error_msg );

		} else {

			// We have a visitor, check if we need a password and if so, was the correct one was supplied

			$is_password_required = Visitor::get_is_login_required_for_email( $email );

			// If everything is fine then this is the page I will redirect to
			$redirect_url = Visitor_Area::get_href_for_main_page();
			// If we have an event key then add that arg to the redirect
			if ( isset( $event_key ) ) {
				$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
				$redirect_url = add_query_arg( $args, $redirect_url );
			} // endif
//			Error_Log::var_dump( $redirect_url );

			if ( $is_password_required ) {

				if ( $pwd === NULL ) {

					// No password field was presented in the form so create the form and return it
					$pwd_form_content = Visitor_Area::get_visitor_login_form_content( $email, $is_remember, $is_password_required, $event_key );
					$ajax_response->set_html_data( $pwd_form_content );

				} else {

					// If a password was supplied we need to do a WP signon
					$credentials = array(
							'user_login'	=> $email,
							'user_password'	=> $pwd,
							'remember'		=> $is_remember,
					);
					$signon_result = wp_signon( $credentials );
					if ( is_wp_error( $signon_result ) ) {

						// The login failed so return an error message
						$error_msg = __( 'The email address or password is not correct.', 'reg-man-rc' );
						$ajax_response->add_error( 'vol-pwd', $email, $error_msg );

					} else {

						Visitor::set_visitor_email_cookie( $email, $is_remember );
						$ajax_response->set_redirect_url( $redirect_url );

					} // endif

				} // endif

			} else {

				// No password needed, we have the visitor so redirect them

				// There are no errors so we will identify the visitor by storing a cookie
				Visitor::set_visitor_email_cookie( $email, $is_remember );

				$ajax_response->set_redirect_url( $redirect_url );

			} // endif

		} // endif

		echo json_encode( $ajax_response->jsonSerialize() );
		wp_die(); // THIS IS REQUIRED!

	} // function
*/
	/**
	 * Handled the admin_post action for visitor area exit
	 */
/*
	public static function handle_visitor_area_exit_action() {
		// Do the actual logout
		Visitor_Controller::handle_visitor_logout();
		// Return the caller to the area main page
		$redirect_url = Visitor_Area::get_href_for_main_page();
		$event_key = Visitor_Area::get_event_key_from_request();
		if ( ! empty( $event_key ) ) {
			$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
			$redirect_url = add_query_arg( $args, $redirect_url );
		} // endif
		wp_safe_redirect( $redirect_url );
//		header( "Location: $redirect_url" );
	} // function
*/

	/**
	 * Perform a logout for the visitor.
	 * @return boolean	TRUE if the logout was successful
	 */
/*
	public static function handle_visitor_logout() {
		$result = Visitor::set_visitor_email_cookie( NULL );
		return $result;
	} // function
*/
	/**
	 * Filter the clauses of the WP_Query when we're dealing with visitors.
	 *
	 * The parts of the query we will modify are these:
	 *   join 	To join our side table
	 *   fields	To add the fields from our side table
	 *
	 * @param	string[]	$clauses
	 * @param	\WP_Query	$query
	 * @return string
	 */
	public static function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;
		$post_type = Visitor::POST_TYPE;

//	Error_Log::log_msg( 'Filter posts clauses called' );
//	Error_Log::var_dump( $clauses, $query );

		$capability = 'read_private_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;

		// Update the clauses if necessary
		if ( is_admin() && current_user_can( $capability ) &&
			$query->is_main_query() && ( $query->get( 'post_type' ) == $post_type ) ) {
/*
			$orderby_key = self::get_orderby();
			if ( $orderby_key == 'is_public' ) {
				$order = $query->get( 'order' );
				$order_clause = " ( CASE WHEN {$wpdb->posts}.post_status='publish' THEN 0 ELSE 1 END ) ";
				$clauses[ 'orderby' ] = " $order_clause $order";
			} // endif
*/
//	Error_Log::log_msg( 'Joining visitor side table' );

			$visitor_table = $wpdb->prefix . Visitor::VISITOR_SIDE_TABLE_NAME;
			$alias = 'reg_man_rc_visitor';

			$join = "LEFT JOIN $visitor_table AS $alias";
			$join .= " ON ( {$wpdb->posts}.ID = {$alias}.post_id )";
			$clauses[ 'join' ] .= ' ' . $join;

			$fields = 	'1 as visitor_table_joined, ' .
						"$alias.full_name as visitor_full_name, " .
						"$alias.email as visitor_email";
			$clauses[ 'fields' ] .= ', ' . $fields;

			if ( is_search() ) {
				$query_vars = $query->query_vars;
				$search_string = isset( $query_vars[ 's' ] ) ? $query_vars[ 's' ] : NULL;
				if ( ( $search_string !== NULL ) && ( $search_string != '' ) ) {
					$search_terms = self::get_search_terms_from_string( $search_string );
					$parts_array = array(); // create an array of parts so we can search all terms and all places
					foreach ( $search_terms as $term ) {
						$esc_term = $wpdb->esc_like( $term );
//						$esc_term = addcslashes( $term, '_%' );
						$parts_array[] = "{$wpdb->posts}.post_title LIKE '%$esc_term%'";
						$parts_array[] = "{$wpdb->posts}.post_excerpt LIKE '%$esc_term%'";
						$parts_array[] = "{$wpdb->posts}.post_content LIKE '%$esc_term%'";
						$parts_array[] = "{$alias}.full_name LIKE '%$esc_term%'";
						$parts_array[] = "{$alias}.email LIKE '%$esc_term%'";
					} // endfor
					// Create the search clause for the search terms
					$new_wheres[] = implode( ' OR ', $parts_array );
					$search_where = implode( ' AND ', $new_wheres ); // Combine the where clauses with AND

					// Always search only inside our post type
					$post_where = " {$wpdb->posts}.post_type='$post_type' ";

					// Make sure that status is something valid
					$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
					$status_parts = array();
					foreach ( $statuses as $status ) {
						$status_parts[] = " {$wpdb->posts}.post_status='$status' ";
					} // endfor
					$status_where = ' ( ' . implode( 'OR', $status_parts ) . ' ) ';

					// Assign the complete where clause
					$clauses[ 'where' ] = " AND $post_where AND $status_where AND ( $search_where )";
				} // endif
			} // endif

		} // endif

//		Error_Log::var_dump( $clauses );

		return $clauses;
	} // function

	private static function get_orderby() {
		$result = isset( $_REQUEST[ 'orderby' ] ) ? $_REQUEST[ 'orderby' ] : 'is_public';
		return $result;
	} // function


	private static function get_search_terms_from_string( $search_string, $remove_stopwords = TRUE ) {
		$result = preg_split( '/\s+/', $search_string, -1, PREG_SPLIT_NO_EMPTY ); // split search string into terms separated by whitespace
		return $result;
	} // function


} // class