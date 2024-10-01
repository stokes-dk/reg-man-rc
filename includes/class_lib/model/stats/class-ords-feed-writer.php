<?php
namespace Reg_Man_RC\Model\Stats;

use Reg_Man_RC\Model\Events_Collection;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Item_Status;
use Reg_Man_RC\Model\Item_Type;
use Reg_Man_RC\Model\Error_Log;

/**
 * An object used to write a feed for the Open Repair Data Standard
 *
 * @since v0.9.5
 *
 */

class ORDS_Feed_Writer {

//	const VERSION				= '0.3';
	const DATE_FORMAT			= 'Y-m-d'; // The format used for dates
	const DEFAULT_FEED_NAME 	= 'open-repair-data';
	const DEFAULT_COUNTRY_CODE	= 'CAN';
	
	private function __construct() {
	} // function
	
	/**
	 * Create an instance of this class
	 * @return self
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Get the array of item descriptors for this feed
	 * @return Item_Descriptor[]
	 */
	private function get_item_descriptors_array() {

		$is_include_placeholder_events = TRUE;
		$events_collection = Events_Collection::create_for_all_events( $is_include_placeholder_events );
		$result = Item_Descriptor_Factory::get_item_descriptors_for_events_collection( $events_collection );

		return $result;
		
	} // function
	
	private function get_feed_name() {
		$result = Settings::get_ORDS_feed_name();
		return $result;
	} // function
	
	private function get_file_name() {
		$blog_name = get_bloginfo( 'name' );
		$result = $blog_name . ' ORDS.csv';
		$result = sanitize_file_name( $result );
		return $result;
	} // function
	
	/**
	 * Handle a request for this feed
	 */
	public function handle_feed_request() {
		
		$data = $this->get_feed_content();
		
		if ( isset( $_REQUEST[ 'preview' ] ) ) {
			header( 'Content-type: text/html' );
			echo "<textarea readonly='true' style='width:100%; height:100%'>$data</textarea>";
		} else {
			$filename = self::get_file_name();
			header( 'Content-Description: File Transfer' );
			header( "Content-Disposition: attachment; filename=$filename" );
			header( 'Content-type: text/csv' );
			// header( 'Pragma: 0' );
			// header( 'Expires: 0' );
			echo $data;
		} // endif
		
	} // function
	
	private static function get_accepted_item_type_names() {
		$ids_array = Settings::get_ORDS_feed_item_type_ids_array();
		$all_item_types = Item_Type::get_all_item_types();
		$result = array();
		foreach ( $all_item_types as $item_type ) {
			$id = $item_type->get_id();
			$type_name = $item_type->get_name();
			$is_accepted = ! empty( $ids_array ) ? in_array( $id, $ids_array ) : TRUE;
			if ( $is_accepted ) {
				$result[] = $type_name;
			} // endif
		} // endfor
		return $result;
	} // function
	
	private static function get_data_provider() {
		// TODO - Should this be a setting?
		$result = get_bloginfo( 'name' );
		return $result;
	} // function
	
	private static function get_country_code() {
		$result = Settings::get_ORDS_feed_country_code();
		return $result;
	} // function
	
	private function get_feed_content() {

		$item_desc_array = $this->get_item_descriptors_array();
//		Error_Log::var_dump( count( $item_desc_array ) );
		
		$col_heads = array(
				'id',
				'partner_product_category',
				// 'product_category',
				// 'product_category_id',
				// 'brand',
				// 'year_of_manufacture',
				// 'product_age',
				'repair_status',
				'problem',
				// 'repair_barrier_if_end_of_life',
				// 'group_identifier',
				// 'session_id',
				'event_date',
				'country',
				'data_provider',
		);
		
		ob_start();
		
			$fp = fopen( 'php://output', 'w' );
			
			fputcsv( $fp, $col_heads );

			$data_provider = self::get_data_provider();
			$country = self::get_country_code(); // 3-letter code required
			$problem = ''; // We don't collect it but the field is required
			$accepted_item_types = self::get_accepted_item_type_names();
//			Error_Log::var_dump( $accepted_item_types );
			
			foreach( $item_desc_array as $item_desc ) {
				
				$id = $item_desc->get_item_ID();
				$partner_product_category = $item_desc->get_item_description();
				$event = $item_desc->get_event();
				$item_type = $item_desc->get_item_type_name();
				
				if ( ! empty( $id ) && ! empty( $partner_product_category ) && isset( $event ) && in_array( $item_type, $accepted_item_types ) ) {
					
					$item_status = $item_desc->get_item_status();
					$repair_status = isset( $item_status ) ? $item_status->get_ORDS_name() : Item_Status::ORDS_UNKNOWN;
					// $session_id =  isset( $event ) ? $event->get_key_string() : NULL;
					$start_date_time = isset( $event ) ? $event->get_start_date_time_object() : NULL;
					$event_date = isset( $start_date_time ) ? $start_date_time->format( self::DATE_FORMAT ) : '';
					$fields = array(
							$id,
							$partner_product_category,
							// 'product_category',
							// 'product_category_id',
							// 'brand',
							// 'year_of_manufacture',
							// 'product_age',
							$repair_status,
							$problem,
							// 'repair_barrier_if_end_of_life',
							// 'group_identifier',
							// $session_id,
							$event_date,
							$country,
							$data_provider,
					);
	
					fputcsv( $fp, $fields );
					
				} // endif
				
			} // endfor
			
			fclose( $fp );

		$result = ob_get_clean();
		
		return $result;
		
	} // function
	
	
} // class