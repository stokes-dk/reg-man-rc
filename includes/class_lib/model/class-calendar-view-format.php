<?php
namespace Reg_Man_RC\Model;

use Reg_Man_RC\View\Map_View;

/**
 * An instance of this class represents a view format that can be shown on a calendar,
 *  for example a month as a grid of days.
 *
 * @since	v0.1.0
 *
 */
class Calendar_View_Format {

	const MONTH_GRID_VIEW	= 'month_grid';	// dayGridMonth for FullCalendar
	const MONTH_LIST_VIEW	= 'month_list';	// listMonth
	const MAP_VIEW			= 'map';		// Google map

	private static $ALL_VIEWS_ARRAY;

	private $id;
	private $name;
	private $desc;
	private $full_calendar_name;

	private function __construct() { }

	public static function get_all_calendar_view_formats() {
		if ( !isset( self::$ALL_VIEWS_ARRAY ) ) {

			$month_grid = new self();
			$month_grid->id						= self::MONTH_GRID_VIEW;
			$month_grid->name					= __( 'Traditional Calendar', 'reg-man-rc' );
			$month_grid->desc					= __( 'A typical grid of days.', 'reg-man-rc' );
			$month_grid->full_calendar_name		= 'dayGridMonth';

			$month_list = new self();
			$month_list->id						= self::MONTH_LIST_VIEW;
			$month_list->name					= __( 'Event List', 'reg-man-rc' );
			$month_list->desc					= __( 'A list of events.', 'reg-man-rc' );
			$month_list->full_calendar_name		= 'listMonth';

			self::$ALL_VIEWS_ARRAY = array(
				self::MONTH_GRID_VIEW	=> $month_grid,
				self::MONTH_LIST_VIEW	=> $month_list,
			);

			if ( Map_View::get_is_map_view_enabled() ) {
				$map_view = new self();
				$map_view->id						= self::MAP_VIEW;
				$map_view->name						= __( 'Event Map', 'reg-man-rc' );
				$map_view->desc						= __( 'Events shown on a map', 'reg-man-rc' );
				$map_view->full_calendar_name		= 'custom_map_view';
				self::$ALL_VIEWS_ARRAY[ self::MAP_VIEW ] = $map_view;
			} // endif
		} // endif
		return self::$ALL_VIEWS_ARRAY;
	} // function

	/**
	 * Get the view format with the specified ID which must be one of the constants defined in this class.
	 *
	 * @param	int|string			$view_format_id		The ID of the view format to be returned.
	 * The ID must be one of the constants defined in this class.
	 * If the specified ID is unrecognized, NULL will be returned.
	 * @return	Calendar_View_Format|NULL	An instance of this class with the specified ID, or NULL if the ID is not recognized.
	 *
	 * @since v0.1.0
	 */
	public static function get_view_format_by_id( $view_format_id ) {
		$all = self::get_all_calendar_view_formats();
		$result = isset( $all[ $view_format_id ] ) ? $all[ $view_format_id ] : NULL;
		return $result;
	} // function

	/**
	 * Get the ID of this object
	 *
	 * @return	int	The ID of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_id() {
		return $this->id;
	} // function

	/**
	 * Get the name of this object
	 *
	 * @return	string	The name of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_name() {
		return $this->name;
	} // function

	/**
	 * Get the description of this object
	 *
	 * @return	string	The description of this event status
	 *
	 * @since v0.1.0
	 */
	public function get_description() {
		return $this->desc;
	} // function

	/**
	 * Get the name used internally by FullCalendar for this view.
	 * This is used to initialize FullCalendar on the client side.
	 *
	 * @return	string	The name of this view used internally by FullCalendar
	 *
	 * @since v0.1.0
	 */
	public function get_full_calendar_name() {
		return $this->full_calendar_name;
	} // function


} // class
