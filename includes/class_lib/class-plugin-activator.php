<?php
namespace Reg_Man_RC;
use Reg_Man_RC\Model\Event;

class Plugin_Activator {
	public static function activate() {
		error_log( 'INFO: Executing ' . __CLASS__ . '::' . __FUNCTION__ );
	} // function
} // class