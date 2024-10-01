<?php
namespace Reg_Man_RC\Model\Encryption;

interface Encryption_Strategy {
	
	/**
	 * Get the encrypted version of the specified message
	 * @param	string $msg
	 * @return	string
	 */
	public function encrypt( $msg );
	
	/**
	 * Get the decrypted version of the specified message
	 * @param	string $encrypted_msg
	 * @return	string
	 */
	public function decrypt( $encrypted_msg );
	
} // function