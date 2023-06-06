<?php
namespace Reg_Man_RC\View\Pub;
// NOTE that Public is a reserved word and cannot be used in a namespace.  We use Pub instead

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\Control\Volunteer_Controller;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\Control\Volunteer_Registration_Controller;
use Reg_Man_RC\View\Volunteer_Registration_View;
use Reg_Man_RC\View\Volunteer_Registration_Form;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Calendar;

/**
 * The volunteer area user interface.
 * This class provides the user interface for registering volunteers to attend events.
 *
 * @since v0.1.0
 *
 */
class Volunteer_Area {
	/** The default slug for the volunteer area page */
	const DEFAULT_PAGE_SLUG = 'volunteer-area';

	const POST_ID_OPTION_KEY = 'reg-man-rc-volunteer-area-page-post-id';

	/** The shortcode used to render the volunteer area on any page */
	const SHORTCODE = 'rc-volunteer-area';

	const PAGE_NAME_GET_KEY			= 'rc-page';

	const PAGE_NAME_HOME			= 'home';
	const PAGE_NAME_EVENT			= 'event';
	const PAGE_NAME_PREFERENCES		= 'pref';

	private static $CURR_PAGE_SUBTITLE; // The title for the current sub page, like preferences or an event

	private static $PAGE_NAME; // The name of the page currently being viewed, e.g. Settings
	private static $EVENT; // The event currently being viewed, if one exists
	
	private static $VOL_AREA_POST; // The post containing the volunteer area

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
	 * Render the view
	 *
	 * This method is called automatically when the shortcode is inserted into a page.
	 * This method really only renders a login form if the user is not logged in and then delegates rendering
	 * the majority of the view to another method
	 *
	 * @return	void
	 *
	 * @since	v0.1.0
	 */
	public function render() {

		echo '<div class="volunteer-area-container">';

			$volunteer = Volunteer::get_current_volunteer();

			if ( ! empty( $volunteer ) ) {

				// We have the volunteer, render the main protal content
				$this->render_main_content();

			} else {

				// We need to ask for the volunteer
				$this->render_volunteer_login_form();

			} // endif

		echo '</div>';

	} // function


	private function render_volunteer_login_form() {

		echo '<div class="volunteer-area-login-form-container">';

			$ajax_action = Volunteer_Controller::AJAX_VOLUNTEER_LOGIN_ACTION;
			$form_method = 'POST';
			$form_classes = 'volunteer-area-login-form';

			$form_content = self::get_volunteer_login_form_content();
			$ajax_form = Ajax_Form::create( $ajax_action, $form_method, $form_classes, $form_content );
			// Because the form contents are replaced on the client side, I need to add a new nonce in the input list every time
			$ajax_form->set_include_nonce_fields( FALSE );

			$ajax_form->render();

		echo '</div>';
	} // function

	/**
	 * Get the content for this form
	 * @param	boolean	$is_open	TRUE if the details section should be open, FALSE otherwise
	 * @return string
	 */
	public static function get_volunteer_login_form_content( $email = '', $is_remember_me = FALSE, $is_require_password = FALSE, $event_key = NULL ) {

		// If no event key was specified and the current request includes an event key
		//  then we will forward the user on to it after they login
		if ( empty( $event_key ) ) {
			$event_key = self::get_event_key_from_request();
		} // endif

		$input_list = self::get_volunteer_login_input_list( $email, $is_remember_me, $is_require_password, $event_key );
		ob_start();
			// Add a nonce
			$ajax_action = Volunteer_Controller::AJAX_VOLUNTEER_LOGIN_ACTION;
			wp_nonce_field( $ajax_action ); // Note that this renders the nonce in place
			// Render the input list
			$input_list->render();
		$result = ob_get_clean();

		return $result;
	} // function

	private static function get_volunteer_login_input_list( $email = '', $is_remember_me = FALSE, $is_require_password = FALSE, $event_key = '' ) {

		$input_list = Form_Input_List::create();

		if ( ! empty( $event_key ) ) {
			$name = 'event-key';
			$value = $event_key;
			$input_list->add_hidden_input( $name, $value );
		} // endif

		$label = __( 'Volunteer Email', 'reg-man-rc' );
		$name = 'vol-email';
		$value = $email;

		if ( ! $is_require_password ) {
			$is_required = TRUE;
			$hint = esc_html__( 'Enter the email address you use to register for events', 'reg-man-rc' );
			$classes = '';
			$addn_attrs = 'autofocus="autofocus"';
			$input_list->add_email_input( $label, $name, $value, $hint, $classes, $is_required, $addn_attrs );

		} else {

			$link_text = __( 'Change volunteer email', 'reg-man-rc' );
			$href = self::get_href_for_main_page();
			$link = "<a href=\"$href\">$link_text</a>";
			/* Translators %1$s is a link to use a different user ID */
			$hint_format = __( 'Not you?  %1$s', 'reg-man-rc' );
			$hint = sprintf( $hint_format, $link );
			$classes = '';
			$is_required = TRUE;
			$addn_attrs = 'readonly="readonly"';
			$input_list->add_email_input( $label, $name, $value, $hint, $classes, $is_required, $addn_attrs );

			$label = __( 'Password', 'reg-man-rc' );
			$name = 'vol-pwd';
			$value = '';
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$addn_attrs = 'autofocus="autofocus"';
			$input_list->add_password_input( $label, $name, $value, $hint, $classes, $is_required, $addn_attrs );

		} // endif

		$label = __( 'Remember me', 'reg-man-rc' );
		$name = 'is-remember';
		$hint = ''; //__( 'Return to this page later without entering your email address', 'reg-man-rc' );
		$value = 1;
		$is_checked = $is_remember_me ? 1 : 0;
		$input_list->add_checkbox_input( $label, $name, $value, $is_checked, $hint );

		$label = $is_require_password ? __( 'Login', 'reg-man-rc' ) : __( 'Continue', 'reg-man-rc' );
		$type = 'submit';
		$classes = 'button reg-man-rc-button';
		$input_list->add_form_button( $label, $type, $classes );

		$input_list->set_button_container_classes( 'volunteer-area-button-container' );

		$input_list->set_required_inputs_flagged( FALSE );

		return $input_list;

	} // function


	private function render_main_content() {

		$volunteer = Volunteer::get_current_volunteer();
		if ( empty( $volunteer ) ) {
			return; // <== EXIT POINT!!! Defensive
		} // endif

		echo '<div class="reg-man-rc-volunteer-area-header">';
			self::render_header();
		echo '</div>';

		echo '<div class="reg-man-rc-volunteer-area-body">';
			$page_name = $this->get_page_name_from_request();
			switch( $page_name ) {

				case self::PAGE_NAME_HOME:
				default:
					$this->render_calendar();
					break;

				case self::PAGE_NAME_EVENT:
					$event = $this->get_event_from_request();
					$this->render_event( $event );
					break;

				case self::PAGE_NAME_PREFERENCES:
					$this->render_preferences();
					break;

			} // endswitch
		echo '</div>';

	} // function

	private function render_header() {

		// The back to calendar page
		$page_name = $this->get_page_name_from_request();
		if ( $page_name !== self::PAGE_NAME_HOME ) {
			$href = self::get_href_for_main_page();
			echo '<div class="reg-man-rc-volunteer-area-header-part volunteer-area-title-back-link">';
				$text = esc_html__( 'Calendar', 'reg-man-rc' );
				$icon = '<span class="volunteer-area-title-back-link-icon dashicons dashicons-arrow-left-alt2"></span>';
				echo "<a href=\"$href\">$icon<span class=\"volunteer-area-title-back-link-text\">$text</a>";
			echo '</div>';
		} else {
			echo '<div class="reg-man-rc-volunteer-area-header-part"></div>'; // To push the user to the right
		} // endif

		// User
		echo '<div class="reg-man-rc-volunteer-area-header-part">';
			$this->render_header_user();
		echo '</div>';

	} // function

	private function render_header_user() {
		$volunteer = Volunteer::get_current_volunteer();
		if ( isset( $volunteer ) ) {
			$public_name = $volunteer->get_public_name();
			
			// Note that the volunteer object is in the public space here and DOES NOT contain the original email
			// We must get the email adderss out of the cookie
			$email = Volunteer::get_volunteer_email_cookie();

			$item_format =
				'<li class="reg-man-rc-dropdown-menu-item %4$s">' .
					'<a href="%2$s">' .
						'<span class="dashicons dashicons-%3$s reg-man-rc-dropdown-menu-item-icon"></span>' .
						'<span class="reg-man-rc-dropdown-menu-item-text">%1$s</span>' .
					'</a>' .
				'</li>';

			// Create menu items
			$page_name = $this->get_page_name_from_request();

			// Preferences
			if ( $page_name !== self::PAGE_NAME_PREFERENCES ) {
				$items = array();
				$text = esc_html__( 'Preferences', 'reg-man-rc' );
				$href = self::get_href_for_volunteer_preferences();
				$items[] = array(
						$text,
						$href,
						'admin-generic',
						'reg-man-rc-volunteer-area-preferences-button'
				);
			} // endif

			// Exit
			if ( Volunteer::get_is_exist_registered_user_for_email( $email ) ) {
				// Registered users should log out the normal way
				// TODO: We could add a "Logout" option here
			} else {
				// We will only add the "Exit" option for volunteers who are not registered users
				$text = esc_html__( 'Exit', 'reg-man-rc' );
				$href = Volunteer_Controller::get_volunteer_area_exit_href();
				$items[] = array(
						$text,
						$href,
						'exit',
						'reg-man-rc-volunteer-area-exit-button'
				);
			} // endif

			// Menu container
			echo '<div class="volunteer-area-user-container reg-man-rc-dropdown-menu-container">';

				// Menu trigger (user name)
				echo '<div class="volunteer-area-user-name-container reg-man-rc-dropdown-menu-trigger">';
					echo '<span class="volunteer-area-volunteer-name dropdown-menu-trigger-title">'. $public_name . '</span>';

					// Trigger icon
					if ( ! empty( $items ) ) {
						echo '<span class="dashicons dashicons-arrow-down dropdown-menu-trigger-icon"></span>';
					} // endif

				echo '</div>';

				// Menu items
				if ( ! empty( $items ) ) {
					echo '<ul class="volunteer-area-user-actions-container reg-man-rc-dropdown-menu-content">';
						foreach( $items as $item ) {
							vprintf( $item_format, $item );
						} // endfor
					echo '</ul>';
				} // endif
			echo '</div>';
		} // endif
	} // function

	/**
	 * Render my calendar
	 */
	private function render_calendar() {

		if ( Settings::get_is_allow_volunteer_registration_quick_signup() ) {
			// The calendar info windows contain a button for "quick sign up"
			// Pressing that button should submit a form already on the page
			// I will render that form here
			$this->render_quick_signup_form();
		} // endif

		// Render the calendar
		$view = Calendar_View::create_for_volunteer_registration_calendar();
		$view->render();

	} // function

	/**
	 * Render the "Quick signup" form
	 */
	private function render_quick_signup_form() {

		$volunteer = Volunteer::get_current_volunteer();

		$ajax_action = Volunteer_Registration_Controller::AJAX_VOLUNTEER_REGISTRATION_ACTION;
		$classes = 'reg-man-rc-volunteer-area-quick-signup-form no-busy'; // I will use calendar's busy indicator
		$form = Ajax_Form::create( $ajax_action );
		$form->set_form_classes( $classes );

		// Quick Signup marker
		$quick_signup_input = '<input type="hidden" name="is-quick-signup" value="1">';

		// Event
		$event_input = '<input type="hidden" name="event-key" value="">';

		// Is register (always 1)
		$is_register_input = '<input type="hidden" name="is-register" value="1">';

		// Volunteer
		$volunteer_id = $volunteer->get_id();
		$volunteer_id_input = "<input type=\"hidden\" name=\"volunteer-id\" value=\"$volunteer_id\">";

		// Use volunteer's defaults for fixer station, apprentice, volunteer role
		$use_defaults_input = '<input type="hidden" name="use-volunteer-defaults" value="1">';

		$form->add_form_content( "$quick_signup_input $event_input $volunteer_id_input $is_register_input $use_defaults_input" );

		$form->render();

	} // function

	/**
	 * Render the specified event
	 * @param Event	$event
	 */
	private function render_event( $event ) {

		$volunteer = Volunteer::get_current_volunteer();
		if ( empty( $volunteer ) || empty( $event ) ) {
			return; // <== EXIT POINT! Defensive
		} // endif

		$view = Volunteer_Registration_View::create_for_page_content( $event );
		$content = $view->get_object_view_content();

		echo $content;

	} // function

	/**
	 * Render the volunteer prefernces page
	 */
	private function render_preferences() {
		$volunteer = Volunteer::get_current_volunteer();
		$pref_form = Volunteer_Registration_Form::create_for_volunteer_preferences( NULL );
		$ajax_form = $pref_form->get_ajax_form();
		$ajax_form->render();
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

		// Add my scripts and styles
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_wp_enqueue_scripts_for_shortcode' ) );

		// Create my shortcode
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'get_shortcode_content' ) );

		// Filter query vars to include mine
		add_filter( 'query_vars', array( __CLASS__, 'filter_query_vars' ), 10, 1 );

		// Filter query vars to include mine
		add_filter( 'the_title', array( __CLASS__, 'filter_the_title' ), 10, 2 );

	} // function

	/**
	 * Filter the title for the volunteer area so we can add a page subtitle for an event
	 * @param string $post_title
	 * @param int $post_id
	 * @return string
	 */
	public static function filter_the_title( $post_title, $post_id ) {

		$vol_area_post_id = self::get_post_id();
		
		// In the loop to make sure we don't change the title in the menu
		if ( $post_id == $vol_area_post_id && in_the_loop() ) {
			
			$sub_page_title = self::get_current_page_subtitle();
			
			if ( ! empty( $sub_page_title ) ) {
				
				/* Translators: %1$s is a sub page title inside the volunteer area, %2$s is the volunteer area main title */
				$format = _x( '%1$s — %2$s', 'A title for a subpage in the volunteer area', 'reg-man-rc' );
				$result = sprintf( $format, $sub_page_title, $post_title );

			} else {
				
				$result = $post_title;
				
			} // endif
			
		} else {
			
			$result = $post_title;
			
		} // endif
		
		return $result;
		
	} // function

	public static function filter_query_vars( $public_query_vars ) {
		$public_query_vars[] = self::PAGE_NAME_GET_KEY;
		return $public_query_vars;
	} // function

	/**
	 * Get the page name form the current request
	 * @return	string		The name of the page requested
	 */
	private static function get_page_name_from_request() {
		if ( ! isset( self::$PAGE_NAME ) ) {
			$event_key = self::get_event_key_from_request();
			if ( ! empty( $event_key ) ) {
				// If an event is specified then we're on an event page
				self::$PAGE_NAME = self::PAGE_NAME_EVENT;
			} else {
				$page_arg = isset( $_REQUEST[ self::PAGE_NAME_GET_KEY ] ) ? $_REQUEST[ self::PAGE_NAME_GET_KEY ] : NULL;
				$valid_page_names = array(
						self::PAGE_NAME_PREFERENCES
				);
				self::$PAGE_NAME = in_array( $page_arg, $valid_page_names ) ? $page_arg : self::PAGE_NAME_HOME;
			} // endif
		} // endif
		return self::$PAGE_NAME;
	} // function

	public static function get_event_from_request() {
		if ( ! isset( self::$EVENT ) ) {
			$event_key = self::get_event_key_from_request();
			self::$EVENT = ! empty( $event_key ) ? Event::get_event_by_key( $event_key ) : NULL;
		} // endif
		return self::$EVENT;
	} // function

	/**
	 * Get the event key from the current request if one exists, otherwise NULL
	 * @return NULL|string
	 */
	public static function get_event_key_from_request() {
		$key = Event_Key::EVENT_KEY_QUERY_ARG_NAME;
		$result = isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : NULL;
		return $result;
	} // function

	/**
	 * Get the current page subtitle
	 * @return string
	 */
	private static function get_current_page_subtitle() {
		if ( ! isset( self::$CURR_PAGE_SUBTITLE ) ) {
			$page_name = self::get_page_name_from_request();
			switch( $page_name ) {

				case self::PAGE_NAME_HOME:
				default:
					self::$CURR_PAGE_SUBTITLE = ''; // A subtitle is not necessary on the home page
					break;

				case self::PAGE_NAME_EVENT:
					$event = self::get_event_from_request();
					self::$CURR_PAGE_SUBTITLE = $event->get_label();
					break;

				case self::PAGE_NAME_PREFERENCES:
					self::$CURR_PAGE_SUBTITLE = __( 'Preferences', 'reg-man-rc' );
					break;

			} // endswitch

		} // endif
		return self::$CURR_PAGE_SUBTITLE;
	} // function

	/**
	 * Generate content for the shortcode
	 *
	 * This method is called automatically when the shortcode is inserted into a page
	 *
	 * @return	string	The contents of the Visitor Registration Manager view
	 *
	 * @since	v0.1.0
	 */
	public static function get_shortcode_content() {
		// Returns the contents for the shortcode.  WP will insert the result into the page.

		ob_start();
			$me = self::create();
			$me->render();
		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface if the shortcode is present
	 *
	 * This method is triggered by the wp_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_wp_enqueue_scripts_for_shortcode() {
		global $post;
		if ( ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			self::enqueue_scripts();
		} // endif
	} // function

	/**
	 * Enqueue the correct scripts when showing this view
	 */
	public static function enqueue_scripts() {
//		Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
//		Scripts_And_Styles::enqueue_ajax_forms();
		Scripts_And_Styles::enqueue_volunteer_area_scripts_and_styles();
		Scripts_And_Styles::enqueue_fullcalendar();
	} // function

	/**
	 * Handle plugin activation.
	 * This function is called by the plugin controller during plugin activation
	 */
	public static function handle_plugin_activation() {
		self::insert_page(); // create the page for this view
	} // function

	/**
	 * Handle plugin deactivation.
	 * This function is called by the plugin controller during plugin deactivation
	 */
	public static function handle_plugin_deactivation() {
		self::delete_page();
	} // function

	/**
	 * Get the post ID for this page
	 * @return string
	 */
	public static function get_post_id() {
		$post = self::get_post();
		$result = isset( $post ) ? $post->ID : NULL;
		return $result;
	} // function

	/**
	 * Get the post for volunteer area
	 * @return \WP_Post
	 */
	public static function get_post() {
		
		if ( ! isset( self::$VOL_AREA_POST ) ) {

			// Get the post from post ID stored in the options table
			$post_id = get_option( self::POST_ID_OPTION_KEY );
			self::$VOL_AREA_POST = ! empty( $post_id ) ? get_post( $post_id ) : NULL;
			
			// If that post has been deleted then re-create it
			if ( empty( self::$VOL_AREA_POST ) ) {

				// This will create the post and store the ID in the options table
				// If it fails, there's nothing more I can do
				$post_id = self::insert_page();
				self::$VOL_AREA_POST = ! empty( $post_id ) ? get_post( $post_id ) : NULL;
				
			} // endif

		} // endif

		return self::$VOL_AREA_POST;
		
	} // function

	/**
	 * Get the URL for this page
	 * @return string|\WP_Error
	 */
	public static function get_href_for_main_page() {
		$post_id = get_option( self::POST_ID_OPTION_KEY );
		$result = ! empty( $post_id ) ? get_page_link( $post_id ) : '';
		return $result;
	} // function

	/**
	 * Get the href for a link that allows the volunteer to change their preferences
	 * @return string
	 */
	public static function get_href_for_volunteer_preferences() {
		$base_url = urldecode( self::get_href_for_main_page() );
		$args = array( self::PAGE_NAME_GET_KEY => self::PAGE_NAME_PREFERENCES );
		$result = add_query_arg( $args, $base_url );
		return $result;
	} // function

	/**
	 * Get the permalink for a volunteer area event page
	 * @param	Event	$event	The event whose area page is to be returned
	 * @return string|NULL
	 */
	public static function get_href_for_event_page( $event ) {
		$base_url = urldecode( self::get_href_for_main_page() );
		if ( ! isset( $event ) ) {
			$result = NULL; // Defensive
		} else {
			$vol_reg_cal = Calendar::get_volunteer_registration_calendar();
			$is_on_cal = isset( $vol_reg_cal ) ? $vol_reg_cal->get_is_event_contained_in_calendar( $event ) : FALSE;
			if ( ! $is_on_cal ) {
				$result = NULL;
			} else {
				$event_key = $event->get_key();
				$args = array( Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key );
				$result = add_query_arg( $args, $base_url );
			} // endif
		} // endif
		return $result;
	} // function

	private static function insert_page() {
		// Create my page in the database
		$title = __( 'Volunteer Area', 'reg-man-rc' );
		$content = '[' . self::SHORTCODE . ']';
//		$template = Template_Controller::MINIMAL_TEMPLATE_SLUG;
		$page_post = array(
			'post_title'		=> $title,
			'post_content'	 	=> $content,
//			'page_template'		=> $template,
			'post_status'		=> 'publish',
			'post_name'			=> self::DEFAULT_PAGE_SLUG,
			'post_type'			=> 'page',
			'comment_status' 	=> 'closed',
			'ping_status'		=> 'closed',
		);
		$post_id = wp_insert_post( $page_post, $wp_error = TRUE ); // create the post and get the id
		if ( is_int( $post_id ) ) {
			update_option( self::POST_ID_OPTION_KEY, $post_id );
		} else { // We got a post id of 0 so there was an error
			$fail_msg = __( 'Failed to insert for visitor registration manager', 'reg-man-rc' );
			Error_Log::log_wp_error( $fail_msg, $post_id ); // post_id is a WP_Error in this case
		} // endif
	} // function

	private static function delete_page() {
		// Delete the page to hold this form
		$post_id = get_option( self::POST_ID_OPTION_KEY ); // get the post id so I can delete the page
		if ( FALSE === $post_id ) { // unable to get the option value for the page's post id, so can't delete it
			/* translators: %s is an option key */
			$format = __( 'Cannot delete visitor registration page because get_option() returned FALSE for option: %s', 'reg-man-rc' );
			$msg = sprintf( $format, self::POST_ID_OPTION_KEY );
			Error_Log::log_msg( $msg );
			$result = FALSE;
		} else {
			// delete the page, really delete not just move to trash
			$del_post_result = wp_delete_post( $post_id, $force_delete = TRUE );
			if ( FALSE === $del_post_result ) { // delete didn't work
				/* translators: %s is a post ID */
				$format = __( 'Cannot delete visitor registration page because wp_delete_post() returned FALSE for post ID: %s', 'reg-man-rc' );
				$msg = sprintf( $format, $post_id );
				Error_Log::log_msg( $msg );
				$result = FALSE;
			} else {
				$del_option_result = delete_option( self::POST_ID_OPTION_KEY ); // remove the option value
				if ( FALSE === $del_option_result ) {
					/* translators: %s is replaced with an option key */
					$format = __( 'delete_option() returned FALSE for option key: %s', 'reg-man-rc' );
					$msg = sprintf( $format, self::POST_ID_OPTION_KEY );
					Error_Log::log_msg( $msg );
					$result = FALSE;
				} else {
					$result = TRUE;
				} // endif
			} // endif
		} // endif
		return $result;
	} // function

} // class