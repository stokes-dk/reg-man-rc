<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this interface provides the details required to create an entry on a calendar, including title, start date & time and so on.
 *
 * @since v0.1.0
 *
 */
interface Calendar_Entry {

	const DATE_FORMAT = 'Y-m-d H:i';

	/**
	 * Get the entry ID as a string.  This should be unique for a calendar that may contain multiple entries.
	 * @param string $calendar_type		One of the Calendar_Type_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_id( $calendar_type );

	/**
	 * Get the entry title as a string, e.g. "Toronto Reference Library".
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return string
	 * @since v0.1.0
	 */
	public function get_calendar_entry_title( $calendar_type );

	/**
	 * Get the entry's start date and time as a string, e.g. "2021-09-28 12:00".
	 *
	 * This interface provides the DATE_FORMAT constant which can be used to format a DateTime object correctly.
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return string	The entry's start date and time.
	 * Note that if no valid date & time string is provided by the implementor then the calendar will not display the entry.
	 * @since v0.1.0
	 */
	public function get_calendar_entry_start_date_time_string( $calendar_type );

	/**
	 * Get the entry's end date and time as a string, e.g. "2021-09-28 16:00".
	 *
	 * This interface provides the DATE_FORMAT constant which can be used to format a DateTime object correctly.
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return string	The entry's end date and time.
	 * Note that if no valid date & time string is provided by the implementor then the calendar will not display the entry.
	 * @since v0.1.0
	 */
	public function get_calendar_entry_end_date_time_string( $calendar_type );

	/**
	 * Get the colour used for the map entry or NULL if the default colour should be used.
	 * The result can be any string that is a valid colour in CSS.
	 * For example, '#f00', 'red', 'rgba( 255, 0, 0, 0.5)'.
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_colour( $calendar_type );

	/**
	 * Get the html class names to be assigned to the entry or NULL if no classes are needed.
	 * Multiple class names should be contained in a single string separated by spaces.
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_class_names( $calendar_type );

	/**
	 * Get the content shown in the info window for the entry including any necessary html markup or NULL if no info is needed.
	 * @param	string	$calendar_type	The type of calendar.  One of the CALENDAR_TYPE_* constants defined by Calendar
	 * @return	string|NULL
	 * @since v0.1.0
	 */
	public function get_calendar_entry_info( $calendar_type );

} // interface