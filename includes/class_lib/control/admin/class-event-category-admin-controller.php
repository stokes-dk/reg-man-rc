<?php
namespace Reg_Man_RC\Control\Admin;

use Reg_Man_RC\Model\Event_Category;
use Reg_Man_RC\Model\Calendar;
use Reg_Man_RC\Model\Error_Log;

/**
 * The event category controller
 *
 * This class provides the controller function for working with event categories
 *
 * @since v0.1.0
 *
 */
class Event_Category_Admin_Controller {

	public static function register() {

		// Save the field value when the new term is created using "created_" hook
		add_action( 'created_' . Event_Category::TAXONOMY_NAME, array( __CLASS__, 'save_new_term_fields' ), 10, 2 );

		// Save the field value when the term is updated using "edited_" hook
		add_action( 'edited_' . Event_Category::TAXONOMY_NAME, array(__CLASS__, 'update_term_admin_fields' ), 10, 2 );

	} // function

	public static function save_new_term_fields( $term_id, $term_taxonomy_id ) {
		$event_category = Event_Category::get_event_category_by_id( $term_id );
		if ( ! empty( $event_category ) ) {

			// Colour
			$colour = isset( $_POST[ 'event-category-colour' ] ) ? trim( $_POST[ 'event-category-colour' ] ) : NULL;
			$event_category->set_colour( $colour );

			// Calendars
			$calendar_id_array = isset( $_POST[ 'event-category-calendar' ] ) ? $_POST[ 'event-category-calendar' ] : array();
			foreach( $calendar_id_array as $calendar_id ) {
				$calendar = Calendar::get_calendar_by_post_id( $calendar_id );
				if ( isset( $calendar ) ) {
					$calendar->add_event_category( $event_category );
				} // endif
			} // foreach

		} // endif
	} // function

	public static function update_term_admin_fields( $term_id, $term_taxonomy_id ) {

		$event_category = Event_Category::get_event_category_by_id( $term_id );
		if ( ! empty( $event_category ) ) {

			// Colour
			$colour = isset( $_POST['event-category-colour'] ) ? trim( $_POST['event-category-colour'] ) : NULL;
			if ( isset( $colour ) ) {
				// If no colour is selected then just leave it as-is
				$event_category->set_colour( $colour );
			} // endif

			// Calendars
			$all_calendars_array = Calendar::get_all_calendars();
			$selected_calendar_id_array = isset( $_POST[ 'event-category-calendar' ] ) ? $_POST[ 'event-category-calendar' ] : array();
			foreach( $all_calendars_array as $calendar ) {
				$calendar_id = $calendar->get_id();
				if ( in_array( $calendar_id, $selected_calendar_id_array ) ) {
					$calendar->add_event_category( $event_category );
				} else {
					$calendar->remove_event_category( $event_category );
				} // endif
			} // endfor
			
			// External names
			$names_text = isset( $_POST[ 'event-category-ext-names' ] ) ? trim( $_POST[ 'event-category-ext-names' ] ) : NULL;
			if ( isset( $names_text ) ) {
				$names_array = explode( '|', $names_text );
				$trimmed_names = array();
				foreach ( $names_array as $name ) {
					$trimmed_name = trim( $name );
					if ( ! empty( $trimmed_name ) ) {
						$trimmed_names[] = $trimmed_name;
					} // endif
				} // endfor
				$event_category->set_external_names( $trimmed_names );
			} // endif

		} // endif

	} // function

} // class