<?php
namespace Reg_Man_RC\Control;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Stats\ORDS_Feed_Writer;

/**
 * The Open Repair Data Standard (ORDS) controller
 *
 * This class provides the controller function for handling the ORDS feed
 *
 * @since v0.9.5
 *
 */
class ORDS_Feed_Controller {

	/**
	 * Register this controller
	 * 
	 * @since 0.1.0
	 */
	public static function register() {

		if ( Settings::get_is_create_ORDS_feed() ) {
			$feed_name = Settings::get_ORDS_feed_name();
			$writer = ORDS_Feed_Writer::create();
			$feed_action_name = add_feed( $feed_name, array( $writer, 'handle_feed_request' ) );
		} // endif
		
	} // function
	
} // class