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

	const GRID_VIEW		= 'grid_view';	// dayGridMonth or multiMonth for FullCalendar, depending on duration
	const LIST_VIEW		= 'list_view';	// FullCalendar list type
	const MAP_VIEW		= 'map_view';	// Google map

	private static $ALL_VIEWS_ARRAY;

	private $id;
	private $name;
	private $desc;

	private function __construct() { }

	public static function get_all_calendar_view_formats() {
		if ( !isset( self::$ALL_VIEWS_ARRAY ) ) {

			$grid_view = new self();
			$grid_view->id						= self::GRID_VIEW;
			$grid_view->name					= __( 'Calendar', 'reg-man-rc' );
			$grid_view->desc					= __( 'A typical grid of days.', 'reg-man-rc' );

			$list_view = new self();
			$list_view->id						= self::LIST_VIEW;
			$list_view->name					= __( 'List', 'reg-man-rc' );
			$list_view->desc					= __( 'A list of events.', 'reg-man-rc' );

			self::$ALL_VIEWS_ARRAY = array(
				self::GRID_VIEW	=> $grid_view,
				self::LIST_VIEW	=> $list_view,
			);

			if ( Map_View::get_is_map_view_enabled() ) {
				$map_view = new self();
				$map_view->id						= self::MAP_VIEW;
				$map_view->name						= __( 'Map', 'reg-man-rc' );
				$map_view->desc						= __( 'Events shown on a map', 'reg-man-rc' );
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

} // class
