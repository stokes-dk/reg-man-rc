<?php
namespace Reg_Man_RC\View\Pub;
// NOTE that Public is a reserved word and cannot be used in a namespace.  We use Pub instead

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Control\Admin\Visitor_Registration_Admin_Controller;
use Reg_Man_RC\Model\Item;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Control\Template_Controller;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\View\Event_View;

/**
 * The visitor registration manager user interface
 *
 * This class provides the user interface for registering visitors and their items when they come to an event.
 *
 * @since v0.1.0
 *
 */
class Visitor_Reg_Manager {
	/** The slug for the visitor registration manager page */
	const DEFAULT_PAGE_SLUG = 'rc-reg';

	const PAGE_ID_OPTION_KEY = 'reg-man-rc-reg-man-page-post-id';

	/** The shortcode used to render the visitor registration manager on any page */
	const SHORTCODE = 'rc-visitor-reg-manager';

	private $event; // the currently selected event, if any

	private static $IS_REG_MANAGER_PAGE; // A flag to indicate whether the current page is this page

	private static $PAGE_URL; // The permalink for this page

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
	 * Get a boolean flag indicating whether the current page is the virtual page for the registration manager
	 * @return	boolean		TRUE if the current page is the virtual page for the registration manager, FALSE otherwise
	 */
	public static function get_is_registration_manager_page() {
		if ( ! isset( self::$IS_REG_MANAGER_PAGE ) ) {
			global $post;
			$my_post_id = get_option( self::PAGE_ID_OPTION_KEY ); // get the post id for my page
//			Error_Log::var_dump( $post->ID, $my_post_id );

			self::$IS_REG_MANAGER_PAGE = ( $my_post_id !== FALSE ) && ( $my_post_id == $post->ID );

		} // endif
		return self::$IS_REG_MANAGER_PAGE;
	} // function

	/**
	 * Get the currently selected event
	 * @return	\Reg_Man_RC\Model\Event	An event instance if a valid one was specified in the GET arguments, NULL otherwise
	 */
	private function get_event() {
		if ( ! isset( $this->event ) ) {
			// If there is an event key specified in the GET args then then look for that event
			$key_string = isset( $_GET[ Event_Key::EVENT_KEY_QUERY_ARG_NAME ] ) ? $_GET[ Event_Key::EVENT_KEY_QUERY_ARG_NAME ] : NULL;
			$event_key = isset( $key_string ) ? Event_Key::create_from_string( $key_string ) : NULL;
			$this->event = ( $event_key === NULL ) ? NULL : Event::get_event_by_key( $event_key );
		} // endif
		return $this->event;
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
	public function render() {
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
		echo '<div class="visitor-reg-manager-container">';
		// If there is an event in the GET args then show that event's registration.  Otherwise show the event select
		$event = $this->get_event();
		if ( ( $event === NULL ) || ( $event === FALSE ) ) {
			// If there's no event then we need to get one

			$action = esc_url( admin_url('admin-post.php') );
			echo "<form action=\"$action\" method=\"POST\" class=\"visitor-reg-manager-event-select-form\">";
				echo '<input type="hidden" name="action" value="' . Visitor_Registration_Admin_Controller::EVENT_SELECT_FORM_POST_ACTION . '">'; // required for admin-post
				self::render_event_select( );
			echo '</form>';
//			if (self::getIsStandaloneServer()) { // these are forms so must be rendered outside above form
//				self::renderStandaloneDownloadEventsDialog();
//				self::renderStandaloneCreateEventDialog();
//			} // endif
		} else {
			// The person has selected an event
			$event_date = $event->get_start_date_time_object();
			$today = new \DateTime( 'now', wp_timezone() );
			$diff = ( $event_date !== NULL ) ? $event_date->diff( $today ) : -1;
			if ( $diff->days !== 0 ) {
				$date_format = get_option('date_format');
				$event_date_text = ( $event_date !== NULL ) ? $event_date->format( $date_format ) : __( 'Event Date Missing', 'reg-man-rc' );
				$today_text = $today->format( $date_format );
//				$launch_url = site_url( Settings::get_registration_manager_page_slug() );
				$launch_url = self::get_page_permalink();
				$link_text = __( 'Visitor Registration Manager', 'reg-man-rc' );
				$link = "<a href=\"$launch_url\">$link_text</a>";

				/* translators: %s is replaced with a date */
				$msg = '<p>' . sprintf( __( 'The event you selected is scheduled for: <b>%s</b>', 'reg-man-rc' ), $event_date_text) . '</p>';
				/* translators: %s is replaced with a date */
				$msg .= '<p>' . sprintf( __('Today\'s date is: %s', 'reg-man-rc' ), $today_text) . '</p>';
				$msg .= '<p>' . __( 'Please make sure you have selected the right event', 'reg-man-rc' ) . '</p>';
				/* translators: %s is replaced with a link to a different page */
				$msg .= '<p>' . sprintf( __('To choose a different event go here: %s', 'reg-man-rc'), $link ) . '</p>';
				$title = __( 'Warning: Check Event Date', 'reg-man-rc' );
				echo "<div class=\"form-user-message-dialog\" title=\"$title\">$msg</div>";
			} // endif

			// Render the data for autocomplete of items (used by registration dialog and add item to visitor)
			echo '<script class="visitor-reg-item-autocomplete-data" type="application/json">'; // json data for autocomplete
				$suggestion_array = Item_Suggestion::get_item_autocomplete_suggestions();
				echo json_encode( $suggestion_array );
			echo '</script>';

			// render the list and all the dialogs etc.
			self::render_event_display( $event );
			self::render_registration_list( $event );
			self::render_registration_dialog( $event );
			self::render_registration_thank_you_message_dialog();

//			$this->renderIsFixedDialog($event);

			self::render_add_item_to_visitor_dialog( $event );
//			$this->renderSurveyDialog($event);
//			$this->renderSurveyThankYouMessageDialog();

		} // endif
		echo '</div>';
	} // function


	private function render_event_select() {

		if ( Settings::get_is_visitor_registration_event_select_using_calendar() ) {

			$calendar_view = Calendar_View::create_for_visitor_registration_calendar();
			$calendar_view->render();

		} else {

			$input_list = \Reg_Man_RC\View\Form_Input_List::create();
			$label = __( 'Register visitors for this event', 'reg-man-rc' );
			$hint = __( 'Select the event then press the button to start registering visitors.', 'reg-man-rc' );

			$input_name = 'event-select';
			$classes = '';
			$selected_event_key = '';
			$calendar = Calendar::get_visitor_registration_calendar();
			ob_start();
				Event_View::render_event_select( $input_name, $classes, $calendar, $selected_event_key );
			$html = ob_get_clean();
			$input_list->add_custom_html_input( $label, 'event-select-custom-input', $html, $hint );

			$input_list->render();

			$buttonText = __('Launch Visitor Registration', 'reg-man-rc');
			echo '<div class="form-input-buttons">';
				echo '<button type="submit" class="form-button reg-manager-launch" name="reg-manager-launch">' . $buttonText . '</button>';
			echo '</div>';
		} // endif
	} // function

	/**
	 * Render the event display
	 * @param	Event	$event
	 */
	private static function render_event_display( $event ) {
		// Add the event date and title to the specified input list as well as a hidden input for its key
		$event_key = ($event !== NULL) ? $event->get_key() : NULL;
		echo '<input type="hidden" name="hidden-event-key" value="' . $event_key . '">'; // we need this again later
		$name = $event->get_label();
		$date_time = $event->get_end_date_time_local_timezone_object();
		$date_format = get_option('date_format');
		$date_text = ($date_time !== NULL) ? $date_time->format($date_format) : __( 'Event date missing', 'reg-man-rc' );
//		$launch_url = site_url( Settings::get_registration_manager_page_slug() );
		$launch_url = self::get_page_permalink();
		$link_text = __( '(change event)', 'reg-man-rc' );
		$change_event = "<a class=\"change-event-link\" href=\"$launch_url\">$link_text</a>";
		echo "<div class=\"form-event-display\">$date_text : $name $change_event</div>";
	} // function

	/**
	 * Render the registration list
	 * @param Event $event
	 */
	private static function render_registration_list( $event ) {
		echo '<div class="datatable-container reg-manager-datatable-container">';
			$list = Visitor_List_View::create( $event, 'visitor-reg-manager-table' );
			$list->render();
		echo '</div>';
	} // endif

	private static function render_registration_dialog( $event ) {
		$title = __( 'Visitor Registration', 'reg-man-rc' );
		echo "<div class=\"visitor-reg-dialog dialog-container\" title=\"$title\">";
			$form = Visitor_Reg_Ajax_Form::create( $event );
			$form->render();
		echo '</div>';
	} // function

	private static function render_registration_thank_you_message_dialog() {
		$title = __( 'Registration Complete', 'reg-man-rc' );
		$msg = __( 'Thank you and enjoy the event!', 'reg-man-rc' );
		echo "<div class=\"reg-manager-message-dialog dialog-container reg-man-rc-visitor-registration-thank-you-dialog\" title=\"$title\">";
		echo "<p>$msg</p>";
		echo '</div>';
	} // function

	private static function render_add_item_to_visitor_dialog( $event ) {
		$title = __( 'Add Item to Visitor Registration', 'reg-man-rc' );
		echo "<div class=\"add-item-to-visitor-reg-dialog dialog-container\" title=\"$title\">";
			$form = Add_Item_To_Visitor_Ajax_Form::create( $event );
			$form->render();
		echo '</div>';
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

		 // conditionally add my scripts and styles on the right page
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_wp_enqueue_scripts_for_shortcode' ) );

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'get_shortcode_content' ) ); // create my shortcode

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
		Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
		Scripts_And_Styles::enqueue_public_registration_scripts_and_styles();
		if ( Settings::get_is_visitor_registration_event_select_using_calendar() ) {
			Scripts_And_Styles::enqueue_fullcalendar(); // For Calendar on event select
		} else {
			Scripts_And_Styles::enqueue_select2();
		} // endif
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
	 * Get the post ID for the visitor registration manager page
	 * @return string
	 */
	public static function get_page_id() {
		$result = get_option( self::PAGE_ID_OPTION_KEY );
		return $result;
	} // function

	/**
	 * Get the permalink for the visitor registration manager page
	 * @return string|WP_Error
	 */
	public static function get_page_permalink() {
		$post_id = get_option( self::PAGE_ID_OPTION_KEY );
		$result = ! empty( $post_id ) ? get_page_link( $post_id ) : '';
		return $result;
	} // function


	public static function get_event_registration_href( $event_key_string ) {
		$visitor_reg_page = self::get_page_permalink();
		$url_parts = parse_url( $visitor_reg_page );
		$target_page = sprintf( '%s://%s%s', $url_parts[ 'scheme' ], $url_parts[ 'host' ], $url_parts[ 'path' ] );

//		$key_string = ( isset( $_POST[ 'event-select' ] ) ) ? wp_unslash( $_POST[ 'event-select' ] ) : '';
//		if ( ! empty( $key_string ) ) {
			$event_key = Event_Key::create_from_string( $event_key_string );
			$query_arg_array = array(
					Event_Key::EVENT_KEY_QUERY_ARG_NAME => $event_key->get_as_string()
				);
			$target_page = add_query_arg( $query_arg_array, $target_page );
//		} // endif

		return $target_page;
	} // function



	private static function insert_page() {
		// Create my page in the database
		$title = __( 'Visitor Registration Manager', 'reg-man-rc' );
		$content = '[' . self::SHORTCODE . ']';
		$template = Template_Controller::MINIMAL_TEMPLATE_SLUG;
		$page_post = array(
			'post_title'		=> $title,
			'post_content'	 	=> $content,
			'page_template'		=> $template,
			'post_status'		=> 'publish',
			'post_name'			=> self::DEFAULT_PAGE_SLUG,
			'post_type'			=> 'page',
			'comment_status' 	=> 'closed',
			'ping_status'		=> 'closed',
		);
		$post_id = wp_insert_post( $page_post, $wp_error = TRUE ); // create the post and get the id
		if ( is_int( $post_id ) ) {
			update_option( self::PAGE_ID_OPTION_KEY, $post_id );
		} else { // We got a post id of 0 so there was an error
			$fail_msg = __( 'Failed to insert for visitor registration manager', 'reg-man-rc' );
			Error_Log::log_wp_error( $fail_msg, $post_id ); // post_id is a WP_Error in this case
		} // endif
	} // function

	private static function delete_page() {
		// Delete the page to hold this form
		$post_id = get_option( self::PAGE_ID_OPTION_KEY ); // get the post id so I can delete the page
		if ( FALSE === $post_id ) { // unable to get the option value for the page's post id, so can't delete it
			/* translators: %s is an option key */
			$format = __( 'Cannot delete visitor registration page because get_option() returned FALSE for option: %s', 'reg-man-rc' );
			$msg = sprintf( $format, self::PAGE_ID_OPTION_KEY );
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
				$del_option_result = delete_option( self::PAGE_ID_OPTION_KEY ); // remove the option value
				if ( FALSE === $del_option_result ) {
					/* translators: %s is replaced with an option key */
					$format = __( 'delete_option() returned FALSE for option key: %s', 'reg-man-rc' );
					$msg = sprintf( $format, self::PAGE_ID_OPTION_KEY );
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