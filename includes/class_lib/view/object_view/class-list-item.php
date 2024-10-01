<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * An instance of this class represents an item (in a list) shown in an object view like an event's location
 *
 */
class List_Item {

	const EVENT_STATUS					= 'event-status';
	const EVENT_VISIBILITY				= 'event-visibility';
	const EVENT_PLACEHOLDER_INFO		= 'event-placeholder-info';
	const EVENT_CATEGORIES				= 'event-categories';
	const EVENT_DATE					= 'event-date';
	const EVENT_UPCOMING_DATES			= 'event-upcoming-dates';
	const EVENT_FIXER_STATIONS			= 'event-fixer-stations';
	const EVENT_DESCRIPTION				= 'event-description';

	const LOCATION_ADDRESS				= 'location-address';
	const LOCATION_NAME					= 'location-name';
	const GET_DIRECTIONS_LINK			= 'location-get-directions';

	const VENUE_DESCRIPTION				= 'venue-description';

	const VOLUNTEER_REG_STATUS			= 'volunteer-reg-status';
	const VOLUNTEER_REG_DETAILS_LINK	= 'volunteer-reg-details';

	const ADMIN_EVENT_VIEW_LINK				= 'admin-event-view-link';
	const ADMIN_EVENT_EDIT_LINK				= 'admin-event-edit-link';
	const ADMIN_EVENT_ITEMS					= 'admin-event-items';
	const ADMIN_EVENT_ITEMS_ITEMIZED		= 'admin-event-items-itemized';
	const ADMIN_EVENT_VOLUNTEERS			= 'admin-event-volunteers';
	const ADMIN_EVENT_VOLUNTEERS_ITEMIZED	= 'admin-event-volunteers-itemized';
	const ADMIN_EVENT_VOL_AREA_LINK			= 'admin-vol-area-link';
	const ADMIN_EVENT_MORE_DETAILS_LINK		= 'admin-event-more-details-link';
	const ADMIN_RECUR_DATE_CANCEL_BUTTON	= 'admin-recur-date-cancel-button';
	
	const VISITOR_REG_LAUNCH_LINK		= 'visitor-reg-launch';

	const MORE_DETAILS_LINK				= 'general-more-details';

	private $classes;
	private $icon;
	private $icon_title;
	private $item_content;
	private $additional_content;

	// Callers should use one of the static factory methods
	private function __construct() {
	} // function

	/**
	 * Render an item in the list of object details
	 * @param string	$item_content	The content for the item, e.g. "789 Yonge St, Toronto, ON M4W 2G8"
	 * @param string	$icon			The name of the dashicons icon to be used for this item, e.g. 'location-alt'
	 * @param string	$icon_title		The rollover title for the icon, e.g. 'Location'
	 * @param string	$classes		Optional classes to be applied to the item
	 * @param string	$addn_content	Optional additional content to be rendered as part of the item after the main item content
	 */
	public static function create( $item_content, $icon = '', $icon_title = '', $classes = '', $addn_content = '' ) {
		$result = new self();
		$result->item_content = $item_content;
		$result->icon = $icon;
		$result->icon_title = $icon_title;
		$result->classes = $classes;
		$result->additional_content = $addn_content;
		return $result;
	} // function

	/**
	 * Get the content for this item
	 * @return string
	 */
	private function get_item_content() {
		$result = $this->item_content;
		return $result;
	} // function

	/**
	 * Get the icon for this item
	 * @return string
	 */
	private function get_icon() {
		$result = $this->icon;
		return $result;
	} // function

	/**
	 * Get the icon_title for this item
	 * @return string
	 */
	private function get_icon_title() {
		$result = $this->icon_title;
		return $result;
	} // function

	/**
	 * Get the classes for this item
	 * @return string
	 */
	private function get_classes() {
		$result = $this->classes;
		return $result;
	} // function

	/**
	 * Get the classes for this item
	 * @return string
	 */
	private function get_additional_content() {
		$result = $this->additional_content;
		return $result;
	} // function

	/**
	 * Render this item
	 */
	public function render_item() {
		$classes = $this->get_classes();
		echo "<li class=\"reg-man-rc-object-view-item $classes\">";
			$classes = $this->get_classes();
			$icon = $this->get_icon();
			$icon_title = $this->get_icon_title();
			$item_content = $this->get_item_content();
			$addn_content = $this->get_additional_content();
			if ( ! empty( $icon ) ) {
				$title_attr = ! empty( $icon_title ) ? 'title="' . esc_attr( $icon_title ) . '"' : '';
				echo "<span class=\"reg-man-rc-object-view-item-icon dashicons dashicons-$icon\" $title_attr></span>";
			} // endif
			echo '<div class="reg-man-rc-object-view-item-content-container">';
				echo "<div class=\"reg-man-rc-object-view-item-content\">$item_content</div>";
				if ( ! empty( $addn_content ) ) {
					echo '<div class="reg-man-rc-object-view-item-additional-content">';
						echo $addn_content;
					echo '</div>';
				} // endif
			echo '</div>';
		echo '</li>';
	} // function

	/**
	 * Create an item with a link to a page to see more details.
	 * @param	string		$href		The href attribute for the link
	 * @param	string		$link_text	The text for the link.  By default this is "More details".
	 * @return	List_Item	The "More details" link item
	 * @since	v0.1.0
	 */
	public static function create_more_details_link_item( $href, $link_text = NULL ) {
		if ( ! empty( $href ) ) {
			if ( empty( $link_text ) ) {
				$link_text = __( 'More details', 'reg-man-rc' );
			} // endif
			$icon = 'text-page';
			$icon_title = __( 'More details', 'reg-man-rc' );
			$link = "<a class=\"object-view-more-details-link\" href=\"$href\">$link_text</a>";
			$classes = 'reg-man-rc-object-view-details-more-details-link-item';
			$result = List_Item::create( $link, $icon, $icon_title, $classes );
		} else {
			$result = NULL;
		} // endif
		return $result;
	} // function

} // class