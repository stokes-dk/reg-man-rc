<?php

namespace Reg_Man_RC\Model;

use Reg_Man_RC\Model\Event_Descriptor;

/**
 * This class provides a mechanism for temporarily overriding certain settings of an instance of Event_Descriptor without
 * triggering any updates to the database or other permanent changes.
 *
 */
class Mutable_Event_Descriptor_Proxy implements Event_Descriptor {

	private $event_descriptor;

	private $event_start_date_time;
	private $event_end_date_time;
	private $is_event_recurring;
	private $recurrence_rule;
	private $cancel_date_strings_array;
	
	private function __construct() {
	} // function
	
	/**
	 * Create an instance of this class using the specified event descriptor
	 * @param Event_Descriptor $event_descriptor
	 */
	public static function create( $event_descriptor ) {
		$result = new self();
		$result->event_descriptor = $event_descriptor;
		return $result;
	} // function
	
	/**
	 * Get the underlying event descriptor
	 * @return Event_Descriptor
	 */
	private function get_event_descriptor() {
		return $this->event_descriptor;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_author_id()
	 */
	public function get_event_author_id() {
		return $this->get_event_descriptor()->get_event_author_id();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_categories()
	 */
	public function get_event_categories() {
		return $this->get_event_descriptor()->get_event_categories();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_class()
	 */
	public function get_event_class() {
		return $this->get_event_descriptor()->get_event_class();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_description()
	 */
	public function get_event_description() {
		return $this->get_event_descriptor()->get_event_description();
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_descriptor_id()
	 */
	public function get_event_descriptor_id() {
		return $this->get_event_descriptor()->get_event_descriptor_id();
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_edit_url()
	 */
	public function get_event_edit_url() {
		return $this->get_event_descriptor()->get_event_edit_url();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_end_date_time()
	 */
	public function get_event_end_date_time() {
		if ( ! isset( $this->event_end_date_time ) ) {
			return $this->get_event_descriptor()->get_event_end_date_time();
		} else {
			return $this->event_end_date_time;
		} // endif
	} // function

	/**
	 * Override the end date/time for the event
	 * @param \DateTime	$end_date_time
	 */
	public function set_event_end_date_time( $end_date_time ) {
		$this->event_end_date_time = $end_date_time;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_exclusion_dates()
	 */
	public function get_event_exclusion_dates() {
		return $this->get_event_descriptor()->get_event_exclusion_dates();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_fixer_station_array()
	 */
	public function get_event_fixer_station_array() {
		return $this->get_event_descriptor()->get_event_fixer_station_array();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_geo()
	 */
	public function get_event_geo() {
		return $this->get_event_descriptor()->get_event_geo();
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_inclusion_dates()
	 */
	public function get_event_inclusion_dates() {
		return $this->get_event_descriptor()->get_event_inclusion_dates();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_is_non_repair()
	 */
	public function get_event_is_non_repair() {
		return $this->get_event_descriptor()->get_event_is_non_repair();
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_is_recurring()
	 */
	public function get_event_is_recurring() {
		if ( isset( $this->is_event_recurring ) ) {
			return ( $this->is_event_recurring && ! empty( $this->recurrence_rule ) );
		} else {
			return $this->get_event_descriptor()->get_event_is_recurring();
		} // endif
	} // function
	
	/**
	 * Override the setting for whether this event is recurring
	 * @param boolean	$is_event_recurring
	 */
	public function set_event_is_recurring( $is_event_recurring ) {
		$this->is_event_recurring = $is_event_recurring;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_location()
	 */
	public function get_event_location() {
		return $this->get_event_descriptor()->get_event_location();
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_page_url()
	 */
	public function get_event_page_url( $recur_date = NULL ) {
		return $this->get_event_descriptor()->get_event_page_url();
		
	}

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_recurrence_rule()
	 */
	public function get_event_recurrence_rule() {
		if ( isset( $this->recurrence_rule ) ) {
			return $this->recurrence_rule;
		} else {
			return $this->get_event_descriptor()->get_event_recurrence_rule();
		} // endif
	} // function

	/**
	 * Override the setting for the recurrence rule
	 * @param Recurrence_Rule	$recurrence_rule
	 */
	public function set_event_recurrence_rule( $recurrence_rule ) {
		$this->recurrence_rule = $recurrence_rule;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_start_date_time()
	 */
	public function get_event_start_date_time() {
		if ( ! isset( $this->event_start_date_time ) ) {
			return $this->get_event_descriptor()->get_event_start_date_time();
		} else {
			return $this->event_start_date_time;
		} // endif
	} // function
	
	/**
	 * Override the start date/time for the event
	 * @param \DateTime	$start_date_time
	 */
	public function set_event_start_date_time( $start_date_time ) {
		$this->event_start_date_time = $start_date_time;
	} // function

	private function get_cancelled_event_date_strings_array() {
		if ( ! isset( $this->cancel_date_strings_array ) ) {
			$event_descriptor = $this->get_event_descriptor();
			if ( $event_descriptor instanceof Internal_Event_Descriptor ) {
				$this->cancel_date_strings_array = $event_descriptor->get_cancelled_event_date_strings_array();
			} else {
				$this->cancel_date_strings_array = array();
			} // endif
		} // endif
		return $this->cancel_date_strings_array;
	} // function

	/**
	 * Override the cancelled event dates
	 * @param string[]	
	 */
	public function set_cancelled_event_date_strings_array( $cancel_date_strings_array ) {
		$this->cancel_date_strings_array = $cancel_date_strings_array;
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_status()
	 */
	public function get_event_status( $event_date = NULL ) {
		
		// Get the status for the descriptor
		$result = $this->get_event_descriptor()->get_event_status( NULL );

		// If the event is cancelled then return that status regardless of the event date
		if ( $result->get_id() !== Event_Status::CANCELLED ) {

			// For a recurring event, we need to check if the specified instance has been cancelled
			if ( $this->get_event_is_recurring() && ! empty( $event_date ) ) {
				$date_str = $event_date->format( Event_Key::EVENT_DATE_FORMAT );
				$cancelled_dates = $this->get_cancelled_event_date_strings_array();
				if ( in_array( $date_str, $cancelled_dates ) ) {
					$result = Event_Status::get_event_status_by_id( Event_Status::CANCELLED );
				} // endif
			} // endif
		
		} // endif
		
		return $result;
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_summary()
	 */
	public function get_event_summary() {
		return $this->get_event_descriptor()->get_event_summary();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_uid()
	 */
	public function get_event_uid() {
		return $this->get_event_descriptor()->get_event_uid();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_event_venue()
	 */
	public function get_event_venue() {
		return $this->get_event_descriptor()->get_event_venue();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Event_Descriptor::get_provider_id()
	 */
	public function get_provider_id() {
		return $this->get_event_descriptor()->get_provider_id();
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_colour()
	 */
	public function get_map_marker_colour( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_colour($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_geographic_position()
	 */
	public function get_map_marker_geographic_position( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_geographic_position($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_id()
	 */
	public function get_map_marker_id( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_id($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_info()
	 */
	public function get_map_marker_info( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_info($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_label()
	 */
	public function get_map_marker_label( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_label($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_location()
	 */
	public function get_map_marker_location( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_location($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_opacity()
	 */
	public function get_map_marker_opacity( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_opacity($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_title()
	 */
	public function get_map_marker_title( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_title($map_type);
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Map_Marker::get_map_marker_zoom_level()
	 */
	public function get_map_marker_zoom_level( $map_type ) {
		return $this->get_event_descriptor()->get_map_marker_zoom_level($map_type);
	} // function

} // class

