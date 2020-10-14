<?php
namespace Reg_Man_RC;

class Plugin_Deactivator {
	public static function deactivate() {
		error_log( 'INFO: Executing ' . __CLASS__ . '::' . __FUNCTION__ );
	} // function
} // class