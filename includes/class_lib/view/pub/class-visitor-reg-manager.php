<?php
namespace Reg_Man_RC\View\Pub;
// NOTE that Public is a reserved word and cannot be used in a namespace.  We use Pub instead

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Control\Visitor_Registration_Controller;
use Reg_Man_RC\Model\Event_Key;
use Reg_Man_RC\Model\Item_Suggestion;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Control\Template_Controller;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\View\Calendar_View;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\View\Ajax_Form;

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

	const POST_ID_OPTION_KEY = 'reg-man-rc-reg-man-page-post-id';

	private $event; // the currently selected event, if any

	private static $VISITOR_REG_PAGE_POST; // The post containing the visitor registration page

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
			
			echo '<h2 class="login-title">' . __( 'You must be logged in to use this page', 'reg-man-rc' ) . '</h2>';
			echo '<div class="login-form-container">';
				wp_login_form();
			echo '</div>';
			
		} else { // User is logged in so show the page content

			if ( ! self::get_can_current_user_access_visitor_registration_manager() ) {

				// User is logged in BUT does not have capability to create items
				echo '<h2 class="login-title">' . 
						__( 'You are not authorized to register visitors', 'reg-man-rc' ) .
					'</h2>';

			} else {
				
				// User is logged in and has capability so show the page content
				$this->render_view_content();

			} // endif
		} // endif

	} // function

	public static function get_can_current_user_access_visitor_registration_manager() {
		$capability = 'publish_' . User_Role_Controller::ITEM_REG_CAPABILITY_TYPE_PLURAL;
		$result = current_user_can( $capability );
		return $result;
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

			self::render_event_selection_display( );

		} else {
			
			// The person has selected an event
			$can_register = $event->get_is_current_user_able_to_register_items();
			
			if ( ! $can_register ) {
				
				self::render_event_display( $event );

			} else {
				
				$event_date = $event->get_start_date_time_object();
				$today = new \DateTime( 'now', wp_timezone() );
				$diff = ( $event_date !== NULL ) ? $event_date->diff( $today ) : NULL;
				if ( ! isset( $diff) || ( $diff->days !== 0 ) ) {
					$date_format = get_option('date_format');
					$event_date_text = ( $event_date !== NULL ) ? $event_date->format( $date_format ) : __( 'Event Date Missing', 'reg-man-rc' );
					$today_text = $today->format( $date_format );
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

				self::render_event_display( $event );
				
				echo '<div class="reg-man-rc-swappable-container">';
					
					// Registration List
					echo '<div class="reg-man-rc-swappable-element reg-man-rc-visitor-reg-list-container">';
						self::render_registration_list( $event );
					echo '</div>';

					// Add Visitor form
					echo '<div class="reg-man-rc-swappable-element reg-man-rc-visitor-add-visitor-container">';
						$form = Visitor_Reg_Ajax_Form::create( $event );
						$form->render();
					echo '</div>';

					// Add Visitor details (add item to visitor)
					echo '<div class="reg-man-rc-swappable-element reg-man-rc-visitor-details-container">';
						$form = Single_Visitor_Details_View::create( $event );
						$form->render();
					echo '</div>';
					
					// Add item details (update item) 
					echo '<div class="reg-man-rc-swappable-element reg-man-rc-item-details-container">';
						$form = Single_Item_Details_View::create();
						$form->render();
					echo '</div>';
					
					// Thank you dialog
					self::render_registration_thank_you_message_dialog();
		
		
		//			self::render_add_item_to_visitor_dialog( $event );
		//			$this->renderSurveyDialog($event);
		//			$this->renderSurveyThankYouMessageDialog();

				echo '</div>';

			} // endif
	
		} // endif

		echo '</div>';

	} // function


	private function render_event_selection_display() {

		$calendar_view = Calendar_View::create_for_visitor_registration_calendar();
		$calendar_view->render();

		if ( current_user_can( 'create_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {
		
			echo '<div class="reg-man-rc-visitor-reg-event-not-on-calendar">';
				$heading = __( 'If the event is not on the calendar', 'reg-man-rc' );
				echo '<h2>' . $heading . '</h2>';
				echo '<p>';
//					echo __( 'Add an event', 'reg-man-rc' );
//				echo '</p>';
//				echo '<p>';
					$button_text = __( 'Add Event&hellip;', 'reg-man-rc' );
					$button_label = $button_text;
					echo '<button class="reg-man-rc-button visitor-reg-add-event">' . $button_label . '</button>';
				echo '</p>';
				
				self::render_add_event_dialog();
				
			echo '</div>';
			
		} // endif
	} // function

	/**
	 * Render the event display
	 * @param	Event	$event
	 */
	private static function render_event_display( $event ) {
		
		// Add the event date and title to the specified input list as well as a hidden input for its key
		$event_key = ($event !== NULL) ? $event->get_key_string() : NULL;
		echo '<input type="hidden" name="hidden-event-key" value="' . $event_key . '">'; // we need this again later
		$event_label = $event->get_label(); // This includes the date
		$launch_url = self::get_page_permalink();

		$event_display_format = '<h2 class="visitor-reg-manager-event-display">%1$s</h2>';
		
		if ( $event->get_is_current_user_able_to_register_items() ) {

//			$link_text = _x( '(change)', 'Text for a link to change events', 'reg-man-rc' );
//			$change_event = "<a class=\"change-event-link\" href=\"$launch_url\">$link_text</a>";

			echo '<div class="visitor-reg-manager-event-header">';
				echo '<h2 class="visitor-reg-manager-event-display">' . $event_label . '</h2>';
//				echo $change_event;
			echo '</div>';
			
		} else {

			printf( $event_display_format, $event_label );
			
			$link_text = __( 'Please select another event', 'reg-man-rc' );
			$change_event = "<a class=\"change-event-link\" href=\"$launch_url\">$link_text</a>";

			/* Translators: %1$s is is a link to choose a different event */
			$format = __( 'You are not authorized to register visitors for this event.  %1$s', 'reg-man-rc' );
			
			printf( $format, $change_event );
			
		} // endif
				
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
	
	private static function render_add_event_dialog() {
		$title = __( 'Add Event', 'reg-man-rc' );
		echo "<div class=\"visitor-reg-add-event-dialog dialog-container\" title=\"$title\">";
		
			$input_list = Form_Input_List::create();
			$input_list->set_required_inputs_flagged( FALSE );
			
			$label = __( 'Event title', 'reg-man-rc' );
			$name = 'event-title';
			$val = '';
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$addn_attrs = 'size="40"';
			$input_list->add_text_input( $label, $name, $val, $hint, $classes, $is_required, $addn_attrs );
			
			$label = __( 'Event date', 'reg-man-rc' );
			$name = 'event-date';
			$val = '';
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$input_list->add_date_input( $label, $name, $val, $hint, $classes, $is_required );
			
			$label = __( 'Event category', 'reg-man-rc' );
			$name = 'event-category';
			$selected = NULL;
			$hint = '';
			$classes = '';
			$is_required = TRUE;
			$visitor_reg_calendar = Calendar::get_visitor_registration_calendar();
			$categories = $visitor_reg_calendar->get_event_category_array();
			$options = array();
			foreach( $categories as $category ) {
				$options[ $category->get_name() ] = $category->get_id();
			} // endfor
			$input_list->add_radio_group( $label, $name, $options, $selected, $hint, $classes, $is_required );
			
			$label = __( 'Cancel', 'reg-man-rc' );
			$type = 'button';
			$classes = 'visitor-reg-add-event-dialog-cancel-button reg-man-rc-button';
			$input_list->add_form_button( $label, $type, $classes );
			
			$label = __( 'Add Event', 'reg-man-rc' );
			$type = 'submit';
			$classes = 'visitor-reg-add-event-dialog-submit-button reg-man-rc-button';
			$input_list->add_form_button( $label, $type, $classes );
			
			$ajax_action = Visitor_Registration_Controller::ADD_EVENT_AJAX_ACTION;
			$method = 'POST';
			$classes = 'visitor-reg-add-event-form';
			$ajax_form = Ajax_Form::create( $ajax_action, $method, $classes );
			$ajax_form->add_input_list_to_form_content( $input_list );
			$ajax_form->render();
			
		echo '</div>';
	} // function
	
	private static function render_registration_thank_you_message_dialog() {
		$title = __( 'Registration Complete', 'reg-man-rc' );
		$msg = __( 'Thank you and enjoy the event!', 'reg-man-rc' );
		echo "<div class=\"reg-manager-message-dialog dialog-container reg-man-rc-visitor-registration-thank-you-dialog\" title=\"$title\">";
		echo "<p>$msg</p>";
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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_wp_enqueue_scripts_and_styles' ) );

		// Add a filter to change the content written for my page
		add_filter( 'the_content', array(__CLASS__, 'modify_post_content') );

	} // function

	/**
	 * Modify the contents for my page
	 * @param	string	$content	The post content retrieved from the database
	 * @return	string	The post content modified for my page
	 * @since	v0.1.0
	 */
	public static function modify_post_content( $content ) {
		global $post;
		$result = $content; // return the original content by default
		if ( ( $post->ID == self::get_post_id() ) && in_the_loop() && is_main_query() ) {
			if ( ! post_password_required( $post ) ) {
				$result = self::get_manager_content();
			} // endif
		} // endif
		return $result;
	} // function

	/**
	 * Get the content for the manager
	 *
	 * This method is called automatically when the Visitor Registration Manager page is rendered 
	 *
	 * @return	string	The contents of the Visitor Registration Manager
	 *
	 * @since	v0.1.0
	 */
	public static function get_manager_content() {
		// Returns the contents for the manager

		ob_start();
			$view = self::create();
			$view->render();
		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Conditionally enqueue the correct scripts and styles for this user interface
	 *
	 * This method is triggered by the wp_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_wp_enqueue_scripts_and_styles() {
		global $post;
		$visitor_reg_post_id = self::get_post_id();
		if ( ( $post instanceof \WP_Post ) && ( $post->ID == $visitor_reg_post_id ) ) {
			self::enqueue_scripts_and_styles();
		} // endif
	} // function

	/**
	 * Enqueue the correct scripts when showing this view
	 */
	private static function enqueue_scripts_and_styles() {
		Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
		Scripts_And_Styles::enqueue_public_registration_scripts_and_styles();
		Scripts_And_Styles::enqueue_fullcalendar(); // For Calendar on event select
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
	public static function get_post_id() {
		$post = self::get_post();
		$result = isset( $post ) ? $post->ID : NULL;
		return $result;
	} // function

	/**
	 * Get the post for the visitor registration manager page
	 * @return \WP_Post
	 */
	public static function get_post() {
		if ( ! isset( self::$VISITOR_REG_PAGE_POST ) ) {

			// Get the post from post ID stored in the options table
			$post_id = get_option( self::POST_ID_OPTION_KEY );
			self::$VISITOR_REG_PAGE_POST = ! empty( $post_id ) ? get_post( $post_id ) : NULL;
			
			// If that post has been deleted then re-create it
			if ( empty( self::$VISITOR_REG_PAGE_POST ) ) {

				// This will create the post and store the ID in the options table
				// If it fails, there's nothing more I can do
				$post_id = self::insert_page();
				self::$VISITOR_REG_PAGE_POST = ! empty( $post_id ) ? get_post( $post_id ) : NULL;
				
			} // endif

		} // endif

		return self::$VISITOR_REG_PAGE_POST;
	} // function

	/**
	 * Get the permalink for the visitor registration manager page
	 * @return string|\WP_Error
	 */
	public static function get_page_permalink() {
		$post_id = self::get_post_id();
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



	/** 
	 * Insert the post (page) for the visitor registration manager
	 * @return int	The post ID
	 */
	private static function insert_page() {
		// Create my page in the database
		$title = __( 'Visitor Registration', 'reg-man-rc' );
		$content = '';
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
			update_option( self::POST_ID_OPTION_KEY, $post_id );
			$result = $post_id;
		} else { // We got a post id of 0 so there was an error
			$fail_msg = __( 'Failed to insert for visitor registration page', 'reg-man-rc' );
			Error_Log::log_wp_error( $fail_msg, $post_id ); // post_id is a WP_Error in this case
			$result = NULL;
		} // endif
		return $result;
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