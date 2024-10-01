<?php
namespace Reg_Man_RC\Control\Comments;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Event;

/**
 * This class handles contoller function for comments int the volunteer area.
 *
 * @since v0.4.0
 *
 */
class Volunteer_Area_Comments_Controller {
	
	const COMMENT_META_EVENT_KEY = 'reg-man-rc-comment-event-key';
	
	private function __construct() {
	} // constructor

	/**
	 * Perform the necessary steps to register this controller with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function register() {

		// Filter whether comments are allowed. We only want comments when the $_GET arg includes an event
		add_filter( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 10, 2 );
		
		// Filter the count of comments on the page
		add_filter( 'get_comments_number', array( __CLASS__, 'filter_get_comments_number' ), 10, 2 );

		// Handle the pre_get_comments action to modify the query for getting comments
		add_action( 'pre_get_comments', array( __CLASS__, 'handle_pre_get_comments' ), 10, 1 );

		// Filter the default arguments for the comment form, including the input fields
		add_filter( 'comment_form_defaults', array( __CLASS__, 'filter_comment_form_default_args' ), 10, 1 );

		// Filter the submit field for the comment form to display
		add_filter( 'comment_form_submit_field', array( __CLASS__, 'filter_comment_form_submit_field' ), 10, 2 );

		// Filter the comments page number link for the current request
		add_filter( 'get_comments_pagenum_link', array( __CLASS__, 'filter_comments_pagenum_link' ), 10, 1 );
		
		// Filter the comment link so we can make it point to the right page
		add_filter( 'get_comment_link', array( __CLASS__, 'filter_get_comment_link' ), 10, 4 );
				
		// Intercept comment post so we can add our metadata
		add_action( 'comment_post', array( __CLASS__, 'handle_comment_post' ), 10, 1 );

		// Filter where the browser is redirected after a comment post so we can get back to the right page
		add_filter( 'comment_post_redirect', array( __CLASS__, 'filter_comment_post_redirect' ), 10, 2 );

		// Add a column to the admin UI for the comment list
		$type = 'edit-comments'; // don't ask me why this call requires 'edit-' prefix and the other does not but it works this way
		add_filter( 'manage_' . $type . '_columns', array( __CLASS__, 'filter_admin_UI_columns' ) );
		
		// Modify the values in the columns in the comments list for my page
		$type = 'comments';
		add_action( 'manage_' . $type . '_custom_column', array( __CLASS__, 'render_admin_UI_column_values' ), 10, 2 );
		
	} // function

	/**
	 * Set up the columns to show in the main admin list for comments
	 * @param	string[]	$columns	The default associative array of IDs (key) and titles (value) for the columns
	 * @return	string[]				The same associative array updated with the specific columns and titles to use for this type
	 * @since	v0.1.0
	 */
	public static function filter_admin_UI_columns( $columns ) {
		
		$is_allow_vol_area_comments = Settings::get_is_allow_volunteer_area_comments();
		
		if ( $is_allow_vol_area_comments ) {
			
			// In this case I need to add a column to indicate the event page under the volunteer area
			// But I do not want to undo another plugin's updates to the columns so I will insert mine and leave the rest
			$result = array();
			foreach( $columns as $name => $title ) {
				$result[ $name ] = $title;
				if ( $name == 'response' ) {
					// I will add my column after the response column
					$result[ 'reg-man-rc-vol-subpage' ] = __( 'Volunteer Area Event Page', 'reg-man-rc' );
				} // endif
			} // endfor
			
		} else {
			
			$result = $columns;
			
		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Render the column values in the main admin list for comments
	 * @param	string[]	$column_name	The ID of the column whose value is to be shown
	 * @param	int|string	$comment_id		The ID of the comment whose column value is to be shown
	 * @return	string		The column value to be shown
	 * @since	v0.1.0
	 */
	public static function render_admin_UI_column_values( $column_name, $comment_id ) {
//		Error_Log::var_dump( $column_name, $comment_id );

		$result = __( '—', 'reg-man-rc' ); // use em-dash as result by default
		
		$comment = \WP_Comment::get_instance( $comment_id );
		$comment_post_id = $comment->comment_post_ID;
		$volunteer_area_page = Volunteer_Area::get_post_id();

		if ( $comment_post_id == $volunteer_area_page ) {

			$event_key = get_comment_meta( $comment_id, self::COMMENT_META_EVENT_KEY, $single = TRUE );

			$event = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;

			if ( ! empty( $event ) ) {

				$href = Volunteer_Area::get_href_for_event_page( $event );
				$title = esc_html( $event->get_label() );
				
				$link = "<a href=\"$href\">$title</a>";
				
				$result = $link;
				
			} // endif

		} // endif
		
		echo $result;

	} // function
	
	/**
	 * Filter whether comments are open
	 * @param boolean $is_open
	 * @param int $post_id
	 * @return boolean
	 */
	public static function filter_comments_open( $is_open, $post_id ) {
		
		// We allow comments when there is a volunteer and we're on an event page
		// But we also only allow comments when the administrator has checked "Allow Comments" for the page!
		// TODO: Should we respect system setting for Automatically close comments are X days?
		
		if ( ! $is_open ) {

			// We will only allow comments if the admin has already allowed comments for Volunteer Area page
			// $is_open is FALSE if the admin has turned off comments in the page settings, so we will respect that
			$result = $is_open;
			
		} else {

			// $is_open is TRUE so we need to further restrict comments if we're in the Volunteer Area
			$vol_area_post_id = Volunteer_Area::get_post_id();
			if ( $post_id == $vol_area_post_id ) {

				if ( ! Settings::get_is_allow_volunteer_area_comments() ) {

					$result = FALSE; // comments disallowed by admin
					
				} elseif ( empty( Volunteer::get_volunteer_for_current_request() ) ) {
					
					$result = FALSE; // No volunteer or comments not allowed, no comments
					
				} else {
					
					// Comments are only allowed on event sub-pages which always include an event key
					$event_key = Volunteer_Area::get_event_key_from_request();
					// Error_Log::var_dump( $post_id, $is_open, $event_key, $_REQUEST );
					$result = ! empty( $event_key );
					
				} // endif

			} else {

				$result = $is_open;
		
			} // endif

		} // endif
		
		return $result;
	} // function
	
	/**
	 * Filter whether comments are open
	 * @param int $count
	 * @param int $post_id
	 * @return boolean
	 */
	public static function filter_get_comments_number( $count, $post_id ) {

		$vol_area_post_id = Volunteer_Area::get_post_id();
		if ( $post_id == $vol_area_post_id ) {

			$event_key = Volunteer_Area::get_event_key_from_request();

			if ( empty( $event_key ) ) {

				$result = 0; // no event key, no comments

			} else {

				$args = array(
						'post_id'		=> $post_id,
						'status'		=> 'approve',
						'type'			=> 'comment',
						'meta_key'		=> self::COMMENT_META_EVENT_KEY,
						'meta_value'	=> $event_key,
				);
				$query = new \WP_Comment_Query( $args );
				$comments_array = $query->get_comments();
//				Error_Log::var_dump( $query, $comments_array );
				$result = count( $comments_array );
				
			} // endif
			
		} else {
			
			$result = $count;
			
		} // endif
		
		return $result;
		
	} // function

	/**
	 * Handle the pre_get_comments action to modify the query for getting comments
	 * @param \WP_Comment_Query $query
	 */
	public static function handle_pre_get_comments( $query ) {
		
//		Error_Log::var_dump( $query );
		global $post;
		
		$post_id = isset( $post ) ? $post->ID : NULL;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {
			
			$event_key = Volunteer_Area::get_event_key_from_request();
			
//			Error_Log::var_dump( $event_key );
			if ( ! empty( $event_key ) ) {
				
				$query->query_vars[ 'meta_query' ] = array(
						'relation'=> 'AND',
						array(
								'key'		=> self::COMMENT_META_EVENT_KEY,
								'value'		=> $event_key,
								'compare'	=> '=',
						),
				);

			} // endif
			
		} // endif
		
	} // function

	/**
	 * Get the comment for fields for the specified volunteer
	 * @param Volunteer $volunteer
	 */
	private static function get_comment_form_fields( $volunteer ) {
		
		$public_name = $volunteer->get_public_name();
		
		$hidden_field_format = '<input id="%1$s" name="%1$s" type="hidden" value="%2$s">';

		$email = Volunteer::get_volunteer_email_for_current_request();
		
//		Error_Log::var_dump( $volunteer );

		// The email address is always included (regardless of settings) so that we know which volunteer made the comment
		$author_field = sprintf( $hidden_field_format, 'author', $public_name );
		$email_field = sprintf( $hidden_field_format, 'email', $email );
		
		$result = array(
				'author'	=> $author_field,
				'email'		=> $email_field,
				'cookies'	=> '', // There's no need for the cookie acceptance in our case
		);

		return $result;
		
	} // function
	
	/**
	 * Filter the array of comment form default arguments so we can add and change what we need
	 * @param string[] $args
	 * @return string[]
	 */
	public static function filter_comment_form_default_args( $args ) {

		global $post;
		
		$post_id = isset( $post ) ? $post->ID : NULL;
		
		$result = $args;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {
			
			$volunteer = Volunteer::get_volunteer_for_current_request();
			
			if ( ! empty( $volunteer ) ) {
				
				$result[ 'fields' ] = self::get_comment_form_fields( $volunteer );
//		Error_Log::var_dump( $result[ 'fields' ] );
				
				$result[ 'title_reply' ] = __( 'Public comments', 'reg-man-rc' );
				
				$para_format = '<p class="comment-%1$s">%2$s</p>';
				
				$visibility_note = __( 'These comments are visible to other volunteers and will include your name as shown.' , 'reg-man-rc' );
				$visibility_para = sprintf( $para_format, 'notes', $visibility_note );

				$result[ 'comment_notes_before' ] = $visibility_para;
				$result[ 'logged_in_as' ] = $visibility_para; // Show this to logged in users too
				
				$comment_input_format =
					'<label for="comment">%1$s</label>' .
					'<textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required="required"></textarea>';
				$comment_input_label = $volunteer->get_public_name(); //__( 'Comment', 'reg-man-rc' );
				$comment_input = sprintf( $comment_input_format, $comment_input_label );
				$result[ 'comment_field' ] = sprintf( $para_format, 'form-comment', $comment_input );

				// The must log in note is only shown when the discussion settings say that users need to log in to comment
				$must_login_note = __( 'Volunteers must log in with a user ID and password to post comments.', 'reg-man-rc' );
				$result[ 'must_log_in' ] = $must_login_note;
				
				// Note comment approval requirements if any
				$is_comment_moderation =	boolval( get_option( 'comment_moderation' ) );
				$is_comment_prev_appr =		boolval( get_option( 'comment_previously_approved' ) );
				
//				Error_Log::var_dump( $is_comment_moderation, $is_comment_prev_appr );
				
				if ( $is_comment_moderation || $is_comment_prev_appr ) {

					if ( $is_comment_moderation ) {
						$note = __( 'All comments must be approved before being shown.', 'reg-man-rc' );
					} else {
						$note = __( 'Comments from new users must be approved before being shown.', 'reg-man-rc' );
					} // endif

					$result[ 'comment_notes_after' ] = $note;
					
				} // endif
				
			} // endif
			
		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Filters the submit field for the comment form to display.
	 *
	 * The submit field includes the submit button, hidden fields for the
	 * comment form, and any wrapper markup.
	 * 
	 * @param string	$submit_field
	 * @param string[]	$args
	 * @return string
	 */
	public static function filter_comment_form_submit_field( $submit_field, $args ) {
//		Error_Log::var_dump( $submit_field );
		
		global $post;
		
		$post_id = isset( $post ) ? $post->ID : NULL;
		
		$result = $submit_field;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {
			
			$event_key = Volunteer_Area::get_event_key_from_request();
			
			if ( ! empty( $event_key ) ) {
				
				$hidden_field_format = '<input name="%1$s" type="hidden" value="%2$s">';
				
				 // The event key input name MUST be the query arg or comments_open won't know that we're on an event page!
				$event_key_input_name = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
				$event_key_field = sprintf( $hidden_field_format, $event_key_input_name, $event_key );

				$result .= $event_key_field; // Just add our field at the end
				
			} // endif
			
		} // endif
		
		return $result;
		
	} // function
	
	/**
	 * Filter the comments pagenum link so we can include our get arguments
	 * @param string $link
	 * @return string
	 */
	public static function filter_comments_pagenum_link( $link ) {
//		Error_Log::var_dump( $link );

		global $post;
		
		$post_id = isset( $post ) ? $post->ID : NULL;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {
							
			$event_key = Volunteer_Area::get_event_key_from_request();
			$get_arg_name = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
			
			$result = ! empty( $event_key ) ? add_query_arg( $get_arg_name, $event_key, $link ) : $link;

		} else {
			
			$result = $link;
			
		} // endif

		return $result;
		
	} // function

	/**
	 * Filter the array of comment form default arguments so we can add and change what we need
	 * @param string		$link
	 * @param \WP_Comment	$comment
	 * @param string[]		$args
	 * @param int			$cpage
	 * 
	 * @return string
	 */
	public static function filter_get_comment_link( $link, $comment, $args, $cpage ) {
//		Error_Log::var_dump( $link, $comment, $args, $cpage );
		global $post;
		
		$post_id = isset( $post ) ? $post->ID : NULL;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {
							
			$event_key = Volunteer_Area::get_event_key_from_request();
			$get_arg_name = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
			
			$result = ! empty( $event_key ) ? add_query_arg( $get_arg_name, $event_key, $link ) : $link;

		} else {
			
			$result = $link;
			
		} // endif

		return $result;

	} // function
	
	/**
	 * Handle the action of saving a comment so we can insert our metadata
	 * @param int $comment_id
	 */
	public static function handle_comment_post( $comment_id ) {
		
		// For comments where the POST contains an event key, add that meta data
		
//		Error_Log::var_dump( $comment_id, $_POST );
		$event_key_post_key = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
		$event_key = isset( $_POST[ $event_key_post_key ] ) ? $_POST[ $event_key_post_key ] : NULL;

		// Comments posted by our plugin contain an event key
		if ( isset( $event_key ) ) {
			
			$meta_key = self::COMMENT_META_EVENT_KEY;
			update_comment_meta( $comment_id, $meta_key, $event_key );
			
			if ( is_user_logged_in() ) {
				
				// For a logged in user, the comment should include their public name and not their full user name as it is in the User ID
				
				$volunteer = Volunteer::get_volunteer_for_current_wp_user();
				
				if ( isset( $volunteer ) ) {
					$args = array(
							'comment_ID'		=> $comment_id,
							'comment_author'	=> $volunteer->get_public_name(),
					);
					wp_update_comment( $args );
				} // endif
				
			} // endif
			
		} // endif
		
	} // function

	/**
	 * Filter where the browser is redirected after a comment post
	 * If we're in the volunteer area, for example, the client should go back to the same page it came from,
	 *  e.g. an event page, rather than the post's permalink which would be the volunteer area main page.
	 * To do this, we'll add the event key from the POST to the location specified by the caller
	 * @param string		$location
	 * @param \WP_Comment	$comment
	 * @return string
	 */
	public static function filter_comment_post_redirect( $location, $comment ) {

//		Error_Log::var_dump( $location, $_POST, $comment );
		
		$post_id = $comment->comment_post_ID;
		
		// If we are on the volunteer area page then we'll do the filtering
		$vol_area_post_id = Volunteer_Area::get_post_id();
		
		if ( $post_id == $vol_area_post_id ) {

			$arg_name = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
			$event_key = isset( $_POST[ $arg_name ] ) ? $_POST[ $arg_name ] : NULL;

			$result = ! empty( $event_key ) ? add_query_arg( $arg_name, $event_key, $location ) : $location;
			
		} else {
			
			$result = $location;
			
		} // endif
		
		return $result;
		
	} // function

	
} // class