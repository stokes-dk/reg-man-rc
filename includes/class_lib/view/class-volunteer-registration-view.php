<?php
namespace Reg_Man_RC\View;
use Reg_Man_RC\Model\Volunteer_Registration;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\View\Object_View\Object_View;
use Reg_Man_RC\View\Object_View\Map_Section;
use Reg_Man_RC\View\Object_View\List_Section;
use Reg_Man_RC\View\Object_View\List_Item;
use Reg_Man_RC\View\Object_View\Ajax_Form_Section;
use Reg_Man_RC\View\Object_View\Tabs_Section;
use Reg_Man_RC\View\Object_View\Abstract_Object_View;
use Reg_Man_RC\View\Object_View\Volunteer_Registration_Item_Provider;
use Reg_Man_RC\View\Object_View\Event_Descriptor_Item_Provider;
use Reg_Man_RC\View\Object_View\Object_View_Section;
use Reg_Man_RC\Model\Settings;

/**
 * An instance of this class provides rendering for a volunteer registration for an event.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Registration_View extends Abstract_Object_View {

	private $event;
	private $item_provider;

	/**
	 * A protected constructor forces users of this class to use one of the factory methods
	 */
	private function __construct() {
	} // function

	/**
	 * A factory method to create an instance of this class to display the page content for this object.
	 * @param	Event	$event			The event shown in this view.
	 * @return	Volunteer_Registration_View	An instance of this class which can be rendered to the page.
	 * @since	v0.1.0
	 */
	public static function create_for_page_content( $event ) {
		$result = new self();
		$result->set_event( $event );
		$result->set_object_page_type( Object_View::OBJECT_PAGE_TYPE_VOLUNTEER_REGISTRATION );
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class to display the info window content on a calendar
	 * @param	Event	$event		The event object shown in this view.
	 * @return	Volunteer_Registration_View
	 * @since	v0.1.0
	 */
	public static function create_for_calendar_info_window( $event, $cal_type = Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
		$result = new self();
		$result->set_info_window_calendar_type( $cal_type );
		$result->set_event( $event );
		$result->set_title( $event->get_summary() );
		return $result;
	} // function

	/**
	 * A factory method to create an instance of this class to display the page content for an event descriptor.
	 * @param	Event	$event		The event object shown in this view.
	 * @return	Volunteer_Registration_View
	 * @since	v0.1.0
	 */
	public static function create_for_map_info_window( $event, $map_type = Map_View::MAP_TYPE_CALENDAR_EVENTS ) {
		$result = new self();
		$result->set_info_window_map_type( $map_type );
		$result->set_event( $event );
		$result->set_title( $event->get_summary() );
		return $result;
	} // function

	/**
	 * Get the event object for this view.
	 * @return	Event	The event object shown in this view.
	 * @since	v0.1.0
	 */
	public function get_event() {
		return $this->event;
	} // function

	/**
	 * Set the event object for this view.
	 * @param	Event	$event	 The event object shown in this view.
	 * @since	v0.1.0
	 */
	private function set_event( $event ) {
		$this->event = $event;
	} // function

	/**
	 * Get the event descriptor item provider for this view
	 * @return Event_Descriptor_Item_Provider
	 */
	private function get_item_provider() {
		if ( ! isset( $this->item_provider ) ) {
			$this->item_provider = Volunteer_Registration_Item_Provider::create( $this->get_event(), $this );
		} // endif
		return $this->item_provider;
	} // function

	/**
	 * Get the section (if any) to be rendered after the title
	 * @return Object_View_Section	The section to be displayed after the title
	 */
	public function get_object_view_after_title_section() {
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_after_title_item_names_array();
		$result = List_Section::create( $item_provider, $item_names );
		return $result;
	} // function

	/**
	 * Get the array of main content sections.
	 * @return Object_View_Section[]
	 */
	public function get_object_view_main_content_sections_array() {

		$result = array();

		$event = $this->get_event();

		if ( $this->get_is_object_page() ) {

			$volunteer = Volunteer::get_volunteer_for_current_request();
			$form = $this->get_full_registration_form( $volunteer );
			$form_section = Ajax_Form_Section::create( $form );

			// Add forms for my proxies if any
			$proxies = $volunteer->get_proxy_for_array();
			foreach( $proxies as $proxy ) {
				$form = $this->get_full_registration_form( $proxy );
				$form_section->add_ajax_form( $form );
			} // endfor

			if ( ! Map_View::get_is_map_view_enabled() ) {

				// If there is no map view enabled then we don't need tabs
				$result[] = $form_section;

			} else {

				// Map section
				$map_marker = $event;
				$map_section = Map_Section::create( $map_marker );

				// Tabs section
				$tabs_section = Tabs_Section::create();
				$tabs_section->add_tab( __( 'Registration', 'reg-man-rc' ), $form_section );
				$tabs_section->add_tab( __( 'Map', 'reg-man-rc' ), $map_section );

				$result[] = $tabs_section;

			} // endif


		} // endif

		// Details section
		$item_provider = $this->get_item_provider();
		$item_names = $this->get_details_item_names_array();
		$result[] = List_Section::create( $item_provider, $item_names );

		return $result;

	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_after_title_item_names_array() {
		if ( $this->get_is_object_page() ) {
			$result = array(
					List_Item::EVENT_STATUS,
					List_Item::EVENT_VISIBILITY,
			);
		} else {
			$result = array(
					List_Item::EVENT_STATUS,
					List_Item::EVENT_VISIBILITY,
					List_Item::VOLUNTEER_REG_STATUS,
			);
		} // endif
		return $result;
	} // function

	/**
	 * Get the item names array for the details section
	 */
	private function get_details_item_names_array() {

		if ( $this->get_is_object_page() ) {
			$result = array(
					List_Item::EVENT_CATEGORIES,
					List_Item::EVENT_DATE,
					List_Item::LOCATION_NAME,
					List_Item::LOCATION_ADDRESS,
					List_Item::GET_DIRECTIONS_LINK,
					List_Item::EVENT_FIXER_STATIONS,
					List_Item::EVENT_DESCRIPTION,
					List_Item::VENUE_DESCRIPTION,
			);
		} else {
			$result = array(
					List_Item::EVENT_CATEGORIES,
					List_Item::EVENT_DATE,
					List_Item::LOCATION_NAME,
					List_Item::LOCATION_ADDRESS,
					List_Item::GET_DIRECTIONS_LINK,
					List_Item::EVENT_FIXER_STATIONS,
					List_Item::VOLUNTEER_REG_DETAILS_LINK,
			);
		} // endif
		return $result;
	} // function
	
/* FIXME - not used
	private function render_full_registration_form() {
		if ( $this->get_is_render_full_registration_form() ) {
			$event = $this->get_event();

			$is_event_complete = $event->get_is_event_complete();

			// Registration status (and compact form if necessary)
			if ( ! $is_event_complete ) {
				$icon = 'aside'; // No icon
				$icon_title = __( 'Registration Form', 'reg-man-rc' );
				$html_summary = esc_html__( 'View / change registration', 'reg-man-rc' );

				$form = $this->get_full_registration_form();

				ob_start();
					$open = 'open="open"';
					echo "<details class=\"reg-man-rc-object-view-volunteer-registration-form-details\" $open>";
						echo '<summary>';
							echo "<span class=\"reg-man-rc-object-view-details-text\">$html_summary</span>";
						echo '</summary>';
						$form->render();
					echo '</details>';
				$item_content = ob_get_clean();

				$classes = 'reg-man-rc-object-view-event-date-group';
				$this->render_detail_item( $icon, $icon_title, $item_content, $classes );

			} // endif

		} // endif

	} // function
*/
	/**
	 * Get the full registration form
	 * @param	Volunteer	$volunteer	The volunteer whose registration form is being shown
	 * @return	Ajax_Form
	 */
	private function get_full_registration_form( $volunteer ) {

		$event = $this->get_event();
		$form = Volunteer_Registration_Form::create( $volunteer, $event );
		$result = $form->get_ajax_form();
		return $result;

	} // function


	/**
	 * Create a compact registration form for the specified event
	 * @param Event $event
	 * @return string
	 */
	private static function create_compact_registration_form( $event ) {
		$vol_reg = $event->get_volunteer_registration_for_current_request();
		$is_registered = ! empty( $vol_reg );

		if ( $is_registered ) {

			// Volunteer is already registered, provide a link to view / change registration
			$href = Volunteer_Area::get_href_for_event_page( $event );
			$classes = 'reg-man-rc-volunteer-area-view-registration-link reg-man-rc-icon-text-container';
			$label_text = esc_html__( 'View / Change', 'reg-man-rc' );
			$label = "<span class=\"dashicons dashicons-welcome-write-blog icon\"></span><span class=\"text\">$label_text</span>";
			$result = "<a href=\"$href\" class=\"$classes\">$label</a>";

		} else {

			if ( Settings::get_is_allow_volunteer_registration_quick_signup() ) {
				// Allow volunteer to register in one click
				// This is actually just a button, the form is rendered by the main page and submitted on the client side
				$event_key = $event->get_key_string();
				$label_text = esc_html__( 'Register Now', 'reg-man-rc' );
				$label = "<span class=\"dashicons dashicons-welcome-write-blog icon\"></span><span class=\"text\">$label_text</span>";
				$classes = 'reg-man-rc-button reg-man-rc-volunteer-area-quick-signup-button reg-man-rc-icon-text-container';
				$data = "data-event-key=\"$event_key\"";
				$result = "<button type=\"button\" class=\"$classes\" $data>$label</button>";

			} else {
				// Provide a link to view the event page and register
				$href = Volunteer_Area::get_href_for_event_page( $event );
				$classes = 'reg-man-rc-volunteer-area-view-registration-link reg-man-rc-icon-text-container';
				$label_text = esc_html__( 'View / Register', 'reg-man-rc' );
				$label = "<span class=\"dashicons dashicons-welcome-write-blog icon\"></span><span class=\"text\">$label_text</span>";
				$result = "<a href=\"$href\" class=\"$classes\">$label</a>";
				
			} // endif

		} // endif

		$result = "<div class=\"reg-man-rc-volunteer-area-quick-signup-container\">$result</div>";
		return $result;
	} // function


	/**
	 * Create a registration status label for the specified event
	 * @param	Event		$event
	 * @param	boolean		$is_registered
	 * @param	Volunteer	$volunteer		An optional volunteer whose status is being displayed.
	 * When supplied the message will read like "Dave is registered..."
	 * If NULL then the message will read like "You are registered..."
	 */
	public static function create_registration_status_label( $event, $is_registered, $volunteer = NULL ) {

		$is_event_complete = $event->get_is_event_complete();
		$is_event_cancelled = $event->get_is_event_cancelled();

		if ( ! $is_event_complete ) {
			// Future event
			if ( ! $is_event_cancelled ) {
				// Not cancelled
				if ( $is_registered ) {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s is registered to attend', 'A volunteer who is registered for a future event', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You are registered to attend', 'A volunteer who is registered for a future event', 'reg-man-rc' );
					} // endif
				} else {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s is not registered to attend', 'A volunteer who is not registered for a future event', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You are not registered to attend', 'A volunteer who is not registered for a future event', 'reg-man-rc' );
					} // endif
				} // endif
			} else {
				// Cancelled
				if ( $is_registered ) {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s had been registered to attend', 'A volunteer who is registered for a future event that has been cancelled', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You had been registered to attend', 'A volunteer who is registered for a future event that has been cancelled', 'reg-man-rc' );
					} // endif
				} else {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s had not been registered to attend', 'A volunteer who is not registered for a future event that has been cancelled', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You had not been registered to attend', 'A volunteer who is not registered for a future event that has been cancelled', 'reg-man-rc' );
					} // endif
				} // endif
			} // endif

		} else {
			// Past event
			if ( ! $is_event_cancelled ) {
				// Not cancelled
				if ( $is_registered ) {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s was registered to attend', 'A volunteer who was registered for a past event', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You were registered to attend', 'A volunteer who was registered for a past event', 'reg-man-rc' );
					} // endif
				} else {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s was not registered to attend', 'A volunteer who was not registered for a past event', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You were not registered to attend', 'A volunteer who was not registered for a past event', 'reg-man-rc' );
					} // endif
				} // endif
			} else {
				// Cancelled
				if ( $is_registered ) {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s was registered to attend', 'A volunteer who was registered for a past event that has been cancelled', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You were registered to attend', 'A volunteer who was registered for a past event that has been cancelled', 'reg-man-rc' );
					} // endif
				} else {
					if ( isset( $volunteer ) ) {
						/* Translators: %1$s is the name of a volunteer like "Dave" */
						$format = _x( '%1$s was not registered to attend', 'A volunteer who was not registered for a past event that has been cancelled', 'reg-man-rc' );
						$result = sprintf( $format, $volunteer->get_public_name() );
					} else {
						$result = _x( 'You were not registered to attend', 'A volunteer who was not registered for a past event that has been cancelled', 'reg-man-rc' );
					} // endif
				} // endif
			} // endif
		} // endif

		return $result;
	} // function


	/**
	 * Create an item with the volunteer's registration status for the specified volunteer event
	 * @param Event							$event
	 * @param Volunteer_Registration_View	$object_view
	 * @return \Reg_Man_RC\View\Object_View\List_Item
	 */
	public static function create_registration_status_item( $event, $object_view ) {

		$vol_reg = $event->get_volunteer_registration_for_current_request();

		$is_event_complete = $event->get_is_event_complete();
		$is_registered = ! empty( $vol_reg );

		$icon_title = __( 'Volunteer registration status' );
		$classes = 'reg-man-rc-volunteer-reg-event-status';
		$classes .= $is_registered ? ' reg-man-rc-volunteer-reg-event-status-registered' : ' reg-man-rc-volunteer-reg-event-status-not-registered';

		// Registration status (and compact form if necessary)
		if ( $is_event_complete ) {

			if ( $is_registered ) {
				// For past events the volunteer registered for, just display an indicator
				$text = __( 'You were registered to attend', 'reg-man-rc' );
				$icon = 'yes';
				$result = List_Item::create( $text, 'yes', $icon_title, $classes );
			} else {
				// If the volunteer did not register for a past then show nothing
				$result = NULL;
			} // endif
			
		} else {
			
			// This is an upcoming event
			$text = self::create_registration_status_label( $event, $is_registered );
			$icon = ( $is_registered ) ? 'yes' : 'no';

			$is_allow_reg = $event->get_is_allow_volunteer_registration();
			// Add compact form if necessary
			if ( ! $object_view->get_is_object_page() && $is_allow_reg ) {
				$addn_content = self::create_compact_registration_form( $event );
			} else {
				$addn_content = NULL;
			} // endif

			$result = List_Item::create( $text, $icon, $icon_title, $classes, $addn_content );

		} // endif

		return $result;
	} // function

	/**
	 * Create an item with a link to the volunteer registration details page
	 * @return	List_Item
	 * @since	v0.1.0
	 */
	public static function create_volunteer_reg_details_link_item( $event ) {
		$href = Volunteer_Area::get_href_for_event_page( $event );
		$link_text = __( 'More details', 'reg-man-rc' );
		$result = List_Item::create_more_details_link_item( $href, $link_text );
		return $result;
	} // function


} // class