<?php
namespace Reg_Man_RC\View;

use Reg_Man_RC\Model\Event_Filter;
use Reg_Man_RC\Model\Event;
use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Model\Internal_Event_Descriptor;

/**
 * An instance of this class provides an input form for an event filter
 *
 * @since	v0.1.0
 *
 */
class Event_Filter_Input_Form {

	const ANY_YEAR = '-1'; // The value (used in year filter select) for the option to show stats for any year.
	const ALL_CATEGORIES = '-1'; // The value (used in category filter select) for ALL categories option.
	const REPAIR_CATEGORIES = '-2'; // The value for all repair categories option - categories where items are registered and repaired.
//	const UNCATEGORIZED = '0'; // The value (used in category filter select) for the uncategorized option.
	const ANY_EVENT_AUTHOR = 0;
	
	const YEAR_INPUT_NAME			= 'event_filter_year';
	const CATEGORY_INPUT_NAME		= 'event_filter_category';
	const EVENT_AUTHOR_INPUT_NAME	= 'event_filter_author';
	
	private function __construct() { }

	/**
	 * Create a new instance of this class
	 * @return Event_Filter_Input_Form
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	public function render() {
		// I need to get all the events to create the filter
		$sorter = Event_Filter::create();
		$sorter->set_sort_order( Event_Filter::SORT_BY_DATE_ASCENDING );
		$all_events = Event::get_all_events_by_filter( $sorter );

		$input_list = Form_Input_List::create();

			$filter_input_list = Form_Input_List::create();
			$filter_input_list->set_style_compact();
			$filter_input_list->add_list_classes( 'event_filter_inputs' );

			// Year
			$label = __( 'Year', 'reg-man-rc' );
			$name = self::YEAR_INPUT_NAME;
			$years_array = self::get_event_years( $all_events );
			$all_time_label = __( 'All Time', 'reg-man-rc' );
			$options = array( $all_time_label => self::ANY_YEAR ) + $years_array; // Put all first
			$filter_input_list->add_select_input( $label, $name, $options );

			// Category
			$label = __( 'Category', 'reg-man-rc' );
			$name = self::CATEGORY_INPUT_NAME;
			$options = array();
			$category_array = Event_Category::get_all_event_categories();
			$repair_category_array = array();
			foreach ( $category_array as $category ) {
				if ( $category->get_is_accept_item_registration() ) {
					$options[ $category->get_name() ] = $category->get_id();
					$repair_category_array[] = $category;
				} // endif
			} // endfor
//			Error_Log::var_dump( $repair_category_array );
			
			// I will also add an option for "All Repair Event Categories" if that group is different from All Categories
			if ( count( $repair_category_array ) !== count( $category_array ) ) {
				$all_repair_cats = __( 'All Repair Event Categories', 'reg-man-rc' );
				$options[ $all_repair_cats ] = self::REPAIR_CATEGORIES;
			} // endif

			// I will add an option for "All Categories"
			$all_cats = __( 'All Categories', 'reg-man-rc' );
			$options[ $all_cats ] = self::ALL_CATEGORIES;
			
			$selected = self::ALL_CATEGORIES;
			
//			$uncat_label = __( 'Uncategorized Events', 'reg-man-rc' );
//			$options[ $uncat_label ] = self::UNCATEGORIZED;

			$filter_input_list->add_select_input( $label, $name, $options, $selected );
			
			// Event Author
			$label = __( 'Event Owner', 'reg-man-rc' );
			$name = self::EVENT_AUTHOR_INPUT_NAME;
			$options = array();
			if ( current_user_can( 'edit_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

				// This user can author events so add "Mine"
				$current_user_id = get_current_user_id();
				$author_name = __( 'Mine', 'reg-man-rc' );
				$options[ $author_name ] = $current_user_id;
				$selected = $current_user_id;
				
				if ( current_user_can( 'edit_others_' . User_Role_Controller::EVENT_CAPABILITY_TYPE_PLURAL ) ) {

					// This user can edit other people's events so show all authors
					$all_event_editors = Internal_Event_Descriptor::get_event_editors_array();
					foreach( $all_event_editors as $editor_id => $editor_name ) {
						if ( $editor_id !== $current_user_id ) {
							$options[ $editor_name ] = $editor_id;
						} // endif
					} // endfor
					
					$author_name = __( 'All', 'reg-man-rc' );
					$options[ $author_name ] = self::ANY_EVENT_AUTHOR;
					$selected = self::ANY_EVENT_AUTHOR; // Select all by default

				} // endif
				
			} else {

				// This user cannot author events so show only "All"
				$author_name = __( 'All', 'reg-man-rc' );
				$options[ $author_name ] = self::ANY_EVENT_AUTHOR;
				$selected = self::ANY_EVENT_AUTHOR;
				
			} // endif
			$filter_input_list->add_select_input( $label, $name, $options, $selected );
			
		$label = __( 'Filter Events', 'reg-man-rc' );
		$input_list->add_fieldset( $label, $filter_input_list );
		$input_list->render();
	} // function

	/**
	 * Get an array of years that the specified events occur in
	 * @param Event[] $all_events
	 * @return string[]
	 */
	private static function get_event_years( $all_events ) {
		$result = array();
		foreach( $all_events as $event ) {
			$start_date = $event->get_start_date_time_object();
			$year = ( ! empty( $start_date ) ) ? $start_date->format( 'Y' ) : NULL;
			if ( ! empty( $year ) ) {
				$result[ $year ] = $year;
			} // endif
		} // endfor
		return $result;
	} // functionn

	/**
	 * Get an event filter based on the specified request data
	 * @param	string[]	$request_data	The data provided by the request, e.g. $_REQUEST
	 * @return	NULL|Event_Filter	An Event_Filter object that can be used to get the correct set of events based on the request data
	 */
	public static function get_filter_object_from_request( $request_data ) {

		$filter_year 		= isset( $request_data[ self::YEAR_INPUT_NAME ] )			? $request_data[ self::YEAR_INPUT_NAME ]			: NULL;
		$filter_category_id	= isset( $request_data[ self::CATEGORY_INPUT_NAME ] )		? $request_data[ self::CATEGORY_INPUT_NAME ]		: NULL;
		$filter_author_id	= isset( $request_data[ self::EVENT_AUTHOR_INPUT_NAME ] )	? $request_data[ self::EVENT_AUTHOR_INPUT_NAME ]	: NULL;
		
		if ( ( empty( $filter_year )		|| ( $filter_year			=== self::ANY_YEAR ) ) &&
			 ( empty( $filter_category_id )	|| ( $filter_category_id	=== self::ALL_CATEGORIES ) ) &&
			 ( empty( $filter_author_id )	|| ( $filter_author_id		=== self::ANY_EVENT_AUTHOR ) ) ) {
			// In this case we won't do any filtering of events
			$filter = NULL;
		} else {

			$filter = Event_Filter::create();

			// Year
			if ( ! empty( $filter_year ) && ( $filter_year !== self::ANY_YEAR ) ) {
				$filter->set_accept_dates_in_year( $filter_year );
			} // endif
			
			// Category
			if ( ! empty( $filter_category_id ) && ( $filter_category_id !== self::ALL_CATEGORIES ) ) {
				switch( $filter_category_id ) {

					case self::ALL_CATEGORIES:
						break;

					case self::REPAIR_CATEGORIES:
						// This means show only events categorized as repair events
						$event_categories = Event_Category::get_all_event_categories();
						$repair_cats_array = array();
						foreach ( $event_categories as $category ) {
							if ( $category->get_is_accept_item_registration() ) {
								$repair_cats_array[] = $category->get_name();
							} // endif
						} // endfor
						$filter->set_accept_category_names( $repair_cats_array ); // Show these categories
//						$filter->set_accept_uncategorized_events( TRUE ); // Show uncategorized ones as well since it's not clear if they are repair events
						break;

//					case self::UNCATEGORIZED:
//						// This means show only uncategorized events
//						$filter->set_accept_category_names( array() ); // Don't show any categories (besides uncategorized)
//						$filter->set_accept_uncategorized_events( TRUE ); // Show uncategorized ones
//						break;

					default:
						$category = Event_Category::get_event_category_by_id( $filter_category_id );
						if ( isset( $category ) ) {
							$name = $category->get_name();
							$aka = $category->get_external_names();
							$cat_names = array( $name ) + $aka;
							$filter->set_accept_category_names( $cat_names );
						} // endif
						break;

				} // endswitch
			} // endif
			
			// Author
			if ( ! empty( $filter_author_id ) && ( $filter_author_id !== self::ANY_EVENT_AUTHOR ) ) {
				$filter->set_accept_event_author_id( $filter_author_id );
			} // endif
			
		} // endif

		$result = $filter;
		return $result;

	} // function



} // class