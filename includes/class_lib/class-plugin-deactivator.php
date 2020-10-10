<?php
namespace Reg_Man_RC;

class Plugin_Deactivator {
	public static function deactivate() {
		error_log('Made it to Plugin_Deactivator::deactivate');
	} // function
} // class