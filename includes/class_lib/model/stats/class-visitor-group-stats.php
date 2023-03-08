<?php
namespace Reg_Man_RC\Model\Stats;

/**
 * An instance of this class represents stats about how many visitors attended an event or events.
 *
 * @since	v0.1.0
 *
 */
class Visitor_Group_Stats {

	private $group_name;
	private $first_time_count;
	private $returning_count;
	private $return_status_unknown_count;
	private $visitor_count;
	private $provided_email_count;
	private $join_mail_list_count;

	private function __construct() { }

	public static function create( $group_name, $first_time_count, $returning_count, $return_status_unknown_count, $provided_email_count, $join_mail_list_count ) {
		$result = new self();
		$result->group_name = $group_name;
		$result->first_time_count = $first_time_count;
		$result->returning_count = $returning_count;
		$result->return_status_unknown_count = $return_status_unknown_count;
		$result->provided_email_count = $provided_email_count;
		$result->join_mail_list_count = $join_mail_list_count;
		return $result;
	} // function

	public function get_group_name() {
		return $this->group_name;
	} // function

	public function get_first_time_count() {
		if ( ! isset( $this->first_time_count ) ) {
			$reg_stats = $this->get_registered_stats();
			$sup_stats = $this->get_supplemental_stats();
			$this->first_time_count = $reg_stats->get_first_time_count() + $sup_stats->get_first_time_count();
		} // endif
		return $this->first_time_count;
	} // function

	public function get_returning_count() {
		if ( ! isset( $this->returning_count ) ) {
			$reg_stats = $this->get_registered_stats();
			$sup_stats = $this->get_supplemental_stats();
			$this->returning_count = $reg_stats->get_returning_count() + $sup_stats->get_returning_count();
		} // endif
		return $this->returning_count;
	} // function

	public function get_return_status_unknown_count() {
		if ( ! isset( $this->return_status_unknown_count ) ) {
			$reg_stats = $this->get_registered_stats();
			$sup_stats = $this->get_supplemental_stats();
			$this->return_status_unknown_count = $reg_stats->get_return_status_unknown_count() + $sup_stats->get_return_status_unknown_count();
		} // endif
		return $this->return_status_unknown_count;
	} // function

	public function get_visitor_count() {
		if ( ! isset( $this->visitor_count ) ) {
			$first = $this->get_first_time_count();
			$return = $this->get_returning_count();
			$unknown = $this->get_return_status_unknown_count();
			$this->visitor_count = $first + $return + $unknown;
		} // endif
		return $this->visitor_count;
	} // function

	public function get_provided_email_count() {
		if ( ! isset( $this->provided_email_count ) ) {
			$reg_stats = $this->get_registered_stats();
			$sup_stats = $this->get_supplemental_stats();
			$this->provided_email_count = $reg_stats->get_provided_email_count() + $sup_stats->get_provided_email_count();
		} // endif
		return $this->provided_email_count;
	} // function

	public function get_join_mail_list_count() {
		if ( ! isset( $this->join_mail_list_count ) ) {
			$reg_stats = $this->get_registered_stats();
			$sup_stats = $this->get_supplemental_stats();
			$this->join_mail_list_count = $reg_stats->get_join_mail_list_count() + $sup_stats->get_join_mail_list_count();
		} // endif
		return $this->join_mail_list_count;
	} // function

} // class