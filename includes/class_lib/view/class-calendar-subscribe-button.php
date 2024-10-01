<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\View\Pub\Volunteer_Area;
use Reg_Man_RC\Model\Volunteer;

class Calendar_Subscribe_Button {

	// The rel attributes prevent the link from passing referrer info and so on
	const SAFE_LINK_FORMAT = '<a href="%2$s" %3$s rel="noopener noreferrer nofollow noindex">%1$s</a>';
	
	private $calendar;
	private $feed_url;
	private $webcal_feed_url;
	
	private function __construct() {
	} // function
	
	/**
	 * Create an instance of this class for the specified calendar
	 * @param Calendar $calendar
	 * @return Calendar_Subscribe_Button
	 */
	public static function create_for_calendar( $calendar ) {
		$result = new self();
		$result->calendar = $calendar;
		return $result;
	} // function
	
	/**
	 * Render this view
	 */
	public function render() {
		$calendar = $this->get_calendar();
		$type = $calendar->get_calendar_type();
		if ( $type === Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
			$label = __( 'Subscribe to my events', 'reg-man-rc' );
		} else {
			$label = __( 'Subscribe to this calendar', 'reg-man-rc' );
		} // endif
		$item_format = '<li>%1$s</li>';
		echo '<details class="reg-man-rc-calendar-subscribe reg-man-rc-button">';
			echo "<summary>$label</summary>";
			echo '<ul>';
				printf( $item_format, $this->get_google_calendar_link() );
				printf( $item_format, $this->get_icalendar_link() );
				printf( $item_format, $this->get_outlook_365_link() );
				printf( $item_format, $this->get_outlook_live_link() );
				printf( $item_format, $this->get_copy_feed_url_button() );
			echo '</ul>';
		echo '</details>';
	} // function
	
	/**
	 * Get the calendar
	 * @return Calendar
	 */
	private function get_calendar() {
		return $this->calendar;
	} // function
	
	private function get_feed_name() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_name();
		return $result;
	} // function
	
	private function get_feed_url() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_url();
		
		if ( $calendar->get_calendar_type() == Calendar::CALENDAR_TYPE_VOLUNTEER_REG ) {
			$volunteer = Volunteer::get_volunteer_for_current_request();
			if ( ! empty( $volunteer ) ) {
				// Add my volunteer id to the URL
				$vol_id = $volunteer->get_icalendar_feed_volunteer_id();
				$query = parse_url( $result, PHP_URL_QUERY );
				$result .= ( empty( $query ) ) ? "?vol-id=$vol_id" : "&vol-id=$vol_id";
			} // endif
		} // endif
		
		return $result;
	} // function
	
	private function get_feed_title() {
		$calendar = $this->get_calendar();
		$result = $calendar->get_icalendar_feed_title();
		return $result;
	} // function
	
	private function get_webcal_feed_url() {
		if ( ! isset( $this->webcal_feed_url ) ) {
			$url = $this->get_feed_url();
			// Replace the scheme (http or https, before ://) with 'webcal'
			$scheme = 'webcal';
			$this->webcal_feed_url = preg_replace( '#^\w+://#', $scheme . '://', $url );
		} // endif
		return $this->webcal_feed_url;
	} // function
	
	private function get_google_calendar_link() {
		$label = __( 'Google Calendar', 'reg-man-rc' );
		$url_format = 'https://www.google.com/calendar/render?cid=%1$s';
		$feed_url = $this->get_webcal_feed_url();
		$link_url = sprintf( $url_format, urlencode( $feed_url ) );
		$addn_attrs = 'target="_blank"';
		$result = sprintf( self::SAFE_LINK_FORMAT, $label, $link_url, $addn_attrs );
		return $result;
	} // function

	private function get_icalendar_link() {
		$label = __( 'iCalendar', 'reg-man-rc' );
		$feed_url = $this->get_webcal_feed_url();
		$addn_attrs = '';
		$result = sprintf( self::SAFE_LINK_FORMAT, $label, $feed_url, $addn_attrs );
		return $result;
	} // function

	private function get_copy_feed_url_button() {
		$label = __( 'Copy feed URL to clipboard', 'reg-man-rc' );
		$feed_url = $this->get_feed_url();
		$button_format = '<button class="reg-man-rc-ical-feed-copy-to-clipboard reg-man-rc-button" data-url="%2$s">%1$s</button>';
		$result = sprintf( $button_format, $label, esc_attr( $feed_url ) );
		return $result;
	} // function

	private function get_outlook_365_link() {
		$label = __( 'Outlook 365', 'reg-man-rc' );
		$url_format = 'https://outlook.office.com/owa?path=/calendar/action/compose&rru=addsubscription&url=%1$s&name=%2$s';
		$feed_url = $this->get_webcal_feed_url();
		$feed_title = $this->get_feed_title();
		$link_url = sprintf( $url_format, urlencode( $feed_url ), urlencode( $feed_title ) );
		$addn_attrs = 'target="_blank"';
		$result = sprintf( self::SAFE_LINK_FORMAT, $label, $link_url, $addn_attrs );
		return $result;
	} // function
	
	// https://outlook.office.com/owa?path=/calendar/action/compose&rru=addsubscription&url={ics_feed}&name={calendar_name}
	
	private function get_outlook_live_link() {
		$label = __( 'Outlook Live', 'reg-man-rc' );
		$url_format = 'https://outlook.live.com/owa?path=/calendar/action/compose&rru=addsubscription&url=%1$s&name=%2$s';
		$feed_url = $this->get_webcal_feed_url();
		$feed_title = $this->get_feed_title();
		$link_url = sprintf( $url_format, urlencode( $feed_url ), urlencode( $feed_title ) );
		$addn_attrs = 'target="_blank"';
		$result = sprintf( self::SAFE_LINK_FORMAT, $label, $link_url, $addn_attrs );
		return $result;
	} // function
	
	// https://outlook.live.com/owa?path=/calendar/action/compose&rru=addsubscription&url={ics_feed}&name={calendar_name}
} // class