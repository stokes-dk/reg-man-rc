<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * Implementors of this interface provide the detail used to render an object in a template.
 */
interface Object_View {

	const OBJECT_PAGE_TYPE_EVENT							= 'event-page';
	const OBJECT_PAGE_TYPE_EVENT_DESCRIPTOR					= 'event-descriptor-page';
	const OBJECT_PAGE_TYPE_VENUE							= 'venue-page';
	const OBJECT_PAGE_TYPE_VOLUNTEER_REGISTRATION			= 'volunteer-registration-page';
	const OBJECT_PAGE_TYPE_ADMIN_DASHBOARD_EVENT_DETAILS	= 'admin-dashboard-event-page';
	const OBJECT_PAGE_TYPE_VISITOR_REGISTRATION				= 'visitor-registration-page';
	
	// TODO: Others?  We have these objects but they doesn't use this interface right now
//	const OBJECT_PAGE_TYPE_ITEM				= 'item-object';
//	const OBJECT_PAGE_TYPE_VOLUNTEER		= 'volunteer-object';

	/**
	 * Get the flag indicating whether this view is for an object's page, e.g. the page showing an event.
	 * @return	boolean	TRUE if this view is for an object's page, FALSE otherwise
	 */
	public function get_is_object_page();

	/**
	 * Get the object type for this view when the view is for an object page like an event's main page.
	 * @return	string|NULL	The type of object being shown in this view when it's for an object page like event.
	 * The value is one of the OBJECT_PAGE_TYPE_* constants declared in Object_View.
	 */
	public function get_object_page_type();

	/**
	 * Get the flag indicating whether this view is for a calendar info window.
	 * @return	boolean	TRUE if this view is for a calendar info window, FALSE otherwise
	 */
	public function get_is_calendar_info_window();

	/**
	 * Get the calendar type when this view is for a calendar info window.
	 * @return	string	The calendar type when this view is for a calendar info window, NULL otherwise
	 */
	public function get_info_window_calendar_type();

	/**
	 * Get the flag indicating whether this view is for a map info window.
	 * @return	boolean	TRUE if this view is for a map info window, FALSE otherwise
	 */
	public function get_is_map_info_window();

	/**
	 * Get the map type when this view is for a map info window.
	 * @return	string	The map type when this view is for a map info window, NULL otherwise
	 */
	public function get_info_window_map_type();

	/**
	 * Get the title displayed for this object view.  May be any html or plain text.
	 * @return string
	 */
	public function get_object_view_title();

	/**
	 * Get the section (if any) to be rendered after the title
	 * @return Object_View_Section	The section to be displayed after the title
	 */
	public function get_object_view_after_title_section();

	/**
	 * Get the array of main content sections.
	 * @return Object_View_Section[]
	 */
	public function get_object_view_main_content_sections_array();

} // class