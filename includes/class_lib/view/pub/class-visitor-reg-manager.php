<?php
namespace Reg_Man_RC\View\Pub;

// NOTE that Public is a reserved word and cannot be used in a namespace.  We use Pub instead

/**
 * The visitor registration manager user interface
 *
 * This class provides the user interface for registering visitors and their items when they come to an event.
 *
 * @since v0.1.0
 *
 */
class Visitor_Reg_Manager {
	/**
	 * The slug for the visitor registration manager page
	 */
	const PAGE_SLUG = 'rc-reg';
	/**
	 * The option key used to find the post id for the visitor registration manager page.
	 * We will store the post id in the Wordpress options table so that we can always find the page later,
	 * for example when we need to delete the page.
	 */
	const POST_ID_OPTION_KEY = 'reg-man-rc-visitor-reg-manager-post-id';
	/**
	 * The shortcode used to render the visitor registration manager
	 */
	const SHORTCODE = 'reg-man-rc-visitor-reg-manager';

	/**
	 * A private constructor forces users of this class to use one of the factory methods
	 */
	private function __construct() { }

	/**
	 * Create an instance of this class
	 * @return object An instance of this class
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Render the visitor registration manager
	 *
	 * This method is called automatically when the shortcode is inserted into a page.
	 * This method really only renders a login form if the user is not logged in and then delegates rendering
	 * the majority of the view to another method
	 *
	 * @return	void
	 *
	 * @since	v0.1.0
	 */
	private function render() {
		if ( ! is_user_logged_in() ) { //user is NOT logged in, show the login form
			echo '<h2 class="login-title">' . __('You must be logged in to use this page', 'reg-man-rc') . '</h2>';
			echo '<div class="login-form-container">';
				wp_login_form();
			echo '</div>';
		} else { // User is logged in so show the page content
			$this->render_view_content();
		} // endif
	} // function

	/**
	 * Render the visitor registration manager view contents
	 *
	 * This method is called automatically when the shortcode is inserted into a page
	 *
	 * @return	void
	 *
	 * @since	v0.1.0
	 */
	private function render_view_content() {
		echo 'Hello!!!!';
	} // function


	/**
	 * Generate the shortcode result
	 *
	 * This method is called automatically when the shortcode is inserted into a page
	 *
	 * @return	string	The contents of the Visitor Registration Manager view
	 *
	 * @since	v0.1.0
	 */
	public static function get_shortcode_content() {
		// Returns the contents for the shortcode.  WP will insert the result into the page.
		add_action('wp_enqueue_scripts', array( __CLASS__, 'do_enqueue_scripts' ) ); // add my scripts and styles

		ob_start();
			$me = self::create();
			$me->render();
		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Enqueue the correct scripts for this user interface
	 *
	 * This method is called automatically when the plugin is activated.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function do_enqueue_scripts() {
//		wp_enqueue_script( 'repair-cafe-reg-js' );
//		wp_enqueue_style( 'repair-cafe-reg-css' );
//		wp_enqueue_style( 'dashicons' );
//		wp_enqueue_script( 'jquery-ui-datepicker' ); // My Create Event dialog needs a datepicker
	} // function

	/**
	 * Perform the necessary steps to register this view with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function register() {
		// This function is called by the plugin's main file to setup the shortcode
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'get_shortcode_content' ) ); // create my shortcode

		// Add handler methods for my form posts. This is only used when the user must select an event
		// Handle event selection, Logged-in users (Priv) and not logged in (NoPriv)
//		add_action( 'admin_post_' . self::EVENT_SELECT_FORM_POST_ACTION, array(__CLASS__, 'handleEventSelectFormPostPriv') );
//		add_action( 'admin_post_nopriv_'  . self::EVENT_SELECT_FORM_POST_ACTION, array(__CLASS__, 'handleEventSelectFormPostNoPriv') );
	} // function


	/**
	 * Perform the necessary steps during plugin activation
	 *
	 * This method is called automatically when the plugin is activated.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function do_plugin_activation() {
		self::insertPage(); // create the page that the form will be on
	} // function

	/**
	 * Perform the necessary steps during plugin deactivation
	 *
	 * This method is called automatically when the plugin is deactivated.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function do_plugin_deactivation() {
		self::deletePage();
	} // function

	/**
	 * Internal function used to insert the visitor registration manager page during plugin activation
	 *
	 * @return mixed	Returns the result of wp_insert_post() which will be the post ID on success or a WP_Error object on failure
	 *
	 * @since	v0.1.0
	 */
	private static function insertPage() {
		$content = '[' . self::SHORTCODE . ']';
		$pagePostArgs = array(
			'post_title'		=> __('Visitor Registration Manager', 'reg-man-rc'),
			'post_content'	 	=> $content,
			'post_status'		=> 'publish',
			'post_name'			=> self::PAGE_SLUG,
			'post_type'			=> 'page',
			'comment_status' 	=> 'closed',
			'ping_status'		=> 'closed',
			'page_template'		=> \Reg_Man_RC\Control\Template_Controller::MINIMAL_TEMPLATE_SLUG,
		);
		$post_id = wp_insert_post( $pagePostArgs ); // create the post and get the id
		if (is_int($post_id)) {
			if (0 !== $post_id) { // We got a valid post id, save it so I can delete the page later
				update_option( self::POST_ID_OPTION_KEY, $post_id, $autoload = FALSE );
			} else { // We got a post id of 0 so there was an error
				/* translators: %1$s is the current class name, %2$s is the current function name. */
				error_log( sprintf( __(
					'ERROR: Unable to create page for visitor registration manager in %1$s::%2$s.  wp_insert_post() returned invalid post ID of 0', 'reg-man-rc' ),
					__CLASS__, __FUNCTION__ ) );
			} // endif
		} else { // We got a non-int error object, log it
			$post_id_print = print_r($post_id, $return = TRUE);
			/* translators: %1$s is the current class name, %2$s is the current function name, %3$s is a description of an error. */
			error_log( sprintf( __(
				'ERROR: Unable to create page for visitor registration manager in %1$s::%2$s.  wp_insert_post() returned an error: %3$s', 'reg-man-rc' ),
				__CLASS__, __FUNCTION__, $post_id_print ) );
		} // endif
		return $post_id;
	} // function

	/**
	 * Internal function used to delete the visitor registration manager page during plugin deactivation
	 *
	 * @return boolean	Returns TRUE on success, FALSE on failure
	 *
	 * @since	v0.1.0
	 */
	private static function deletePage() {
		$post_id = get_option( self::POST_ID_OPTION_KEY ); // get the post id so I can delete the page
		if (FALSE === $post_id) { // unable to get the option value for the page's post id, so can't delete it
			/* translators: %1$s is the current class name, %2$s is the current function name, %3$s is a post id number. */
			error_log( sprintf( __(
				'ERROR: Unable to delete page for visitor registration manager in %1$s::%2$s.  get_option() returned FALSE for option: %s: %3$s', 'reg-man-rc' ),
				__CLASS__, __FUNCTION__, $post_id ) );
			$result = FALSE;
		} else { // delete the page, really delete not just move to trash
			$del_result = wp_delete_post( $post_id, $force_delete = TRUE );
			if (FALSE === $del_result) { // delete didn't work
				/* translators: %1$s is the current class name, %2$s is the current function name, %3$s is a post id number. */
				error_log( sprintf( __(
					'ERROR: Unable to delete page for visitor registration manager in %1$s::%2$s.  wp_delete_post() FALSE for option: %s: %3$s', 'reg-man-rc' ),
					__CLASS__, __FUNCTION__, $post_id ) );
				$result = FALSE;
			} else {
				$del_result = delete_option( self::POST_ID_OPTION_KEY ); // remove the option value
				if (FALSE === $del_result) {
					/* translators: %1$s is the current class name, %2$s is the current function name, %3$s is an option key. */
					error_log( sprintf( __(
						'WARNING: Unable to delete option value visitor registration manager post id in %1$s::%2$s.  delete_option() FALSE for option: %s: %3$s', 'reg-man-rc' ),
						__CLASS__, __FUNCTION__, self::POST_ID_OPTION_KEY ) );
					$result = FALSE;
				} else {
					$result = TRUE;
				} // endif
			} // endif
		} // endif
		return $result;
	} // function

} // class