<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\Model\Error_Log;

/**
 * Provides a foundation for implementing the Object_View interface.
 */
abstract class Abstract_Object_View implements Object_View {

	private $abstract_object_view_title;

	protected $object_page_type;
	protected $info_window_calendar_type;
	protected $info_window_map_type;

	/**
	 * This provides a convenience for getting the contents of an object view already rendered by the template.
	 */
	public function get_object_view_content() {
		$template = Object_View_Template::create( $this );
		$result = $template->get_content();
		return $result;
	} // function

	/**
	 * Get the flag indicating whether this view is for an object's page, e.g. the page showing an event.
	 * @return	boolean	TRUE if this view is for an object's page, FALSE otherwise
	 */
	public function get_is_object_page() {
		return isset( $this->object_page_type );
	} // function

	/**
	 * Get the object type for this view when the view is for an object page like an event's main page.
	 * @return	string|NULL	The type of object being shown in this view when it's for an object page like event.
	 * The value is one of the OBJECT_PAGE_TYPE_* constants declared in Object_View.
	 */
	public function get_object_page_type() {
		return $this->object_page_type;
	} // function

	/**
	 * Set the object type for this view when the view is for an object page like an event's main page.
	 * Subclasses will assign this value when necessary.
	 * @return	string	$object_page_type	The object page type for this view when it's showing an object's main page.
	 * The value is one of the OBJECT_PAGE_TYPE_* constants declared in Object_View.
	 */
	protected function set_object_page_type( $object_page_type ) {
		$this->object_page_type = $object_page_type;
	} // function

	/**
	 * Get the flag indicating whether this view is for a calendar info window.
	 * @return	boolean	TRUE if this view is for a calendar info window, FALSE otherwise
	 */
	public function get_is_calendar_info_window() {
		return isset( $this->info_window_calendar_type );
	} // function

	/**
	 * Get the calendar type when this view is for a calendar info window.
	 * @return	string	The calendar type when this view is for a calendar info window, NULL otherwise
	 */
	public function get_info_window_calendar_type() {
		return $this->info_window_calendar_type;
	} // function

	/**
	 * Set the calendar type when this view is for a calendar info window.
	 * Subclasses will assign this value when necessary.
	 * @param	string	$info_window_calendar_type	The calendar type when this view is for a calendar info window
	 */
	protected function set_info_window_calendar_type( $info_window_calendar_type ) {
		$this->info_window_calendar_type = $info_window_calendar_type;
	} // function

	/**
	 * Get the flag indicating whether this view is for a map info window.
	 * @return	boolean	TRUE if this view is for a map info window, FALSE otherwise
	 */
	public function get_is_map_info_window() {
		return isset( $this->info_window_map_type );
	} // function

	/**
	 * Get the map type when this view is for a map info window.
	 * @return	string	The map type when this view is for a map info window, NULL otherwise
	 */
	public function get_info_window_map_type() {
		return $this->info_window_map_type;
	} // function

	/**
	 * Set the map type when this view is for a map info window.
	 * Subclasses will assign this value when necessary.
	 * @param	string	$info_window_map_type	The map type when this view is for a map info window
	 */
	protected function set_info_window_map_type( $info_window_map_type ) {
		$this->info_window_map_type = $info_window_map_type;
	} // function

	/**
	 * Get the title for this object view.
	 * Subclasses may override or use the provided setter method.
	 * @return string
	 */
	public function get_object_view_title() {
		return $this->abstract_object_view_title;
	} // function

	/**
	 * Set the title and optional href
	 * @param	string			$title			The title for this post
	 * @param	string			$title_href		An optional href to render the title as a link
	 */
	protected function set_title( $title, $title_href = NULL ) {
		if ( empty( $title_href ) ) {
			$this->abstract_object_view_title = esc_html( $title );
		} else {
			$title = esc_html( $title );
			$this->abstract_object_view_title = "<a href=\"$title_href\">$title</a>";
		} // endif
	} // function

} // class