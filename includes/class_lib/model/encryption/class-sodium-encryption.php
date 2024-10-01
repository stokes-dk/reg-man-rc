<?php
namespace Reg_Man_RC\Model\Encryption;

use Reg_Man_RC\Model\Error_Log;

/**
 * This class provides the OpenSSL implementation for encrypting and decrypting sensitive data
 *
 * @since	v0.5.0
 *
 */
class Sodium_Encryption implements Encryption_Strategy {

	private $key;
	
	/**
	 * Create an instance of this class
	 * @return Sodium_Encryption
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function
	
	private function __construct() {
		
		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {

			$this->key = mb_substr( LOGGED_IN_KEY, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, '8bit' );
			
		} else {
			
			Error_Log::log_msg( 'ERROR: Cannot encrypt data because encryption key is missing.', 'reg-man-rc' );
			
		} // endif

	} // function
	
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Encryption\Encryption_Strategy::encrypt()
	 */
	public function encrypt( $msg ) {
		
		$key = $this->key;		

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		
		$cipher_msg = $nonce . sodium_crypto_secretbox( $msg, $nonce, $key );
		
		return sodium_bin2base64( $cipher_msg, SODIUM_BASE64_VARIANT_ORIGINAL );
		
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Encryption\Encryption_Strategy::decrypt()
	 */
	public function decrypt( $encrypted_msg ) {
		
		$key = $this->key;

		if ( empty( $encrypted_msg ) || ! isset( $key ) ) {
			
			$result = NULL;
			
		} else {
		
			$decoded_msg = sodium_base642bin( $encrypted_msg, SODIUM_BASE64_VARIANT_ORIGINAL );
			if ( $decoded_msg === FALSE ) {
				
				Error_Log::log_msg( __( 'ERROR: Unable to decode encrypted message', 'reg-man-rc' ) );
				$result = NULL;
				
			} else {
			
				$nonce = mb_substr( $decoded_msg, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
				$cipher_text = mb_substr( $decoded_msg, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );
				
				$result = sodium_crypto_secretbox_open( $cipher_text, $nonce, $key );
				
				if ( $result === FALSE ) {
	
					Error_Log::log_msg( __( 'ERROR: Unable to decrypt message', 'reg-man-rc' ) );
					$result = NULL;
					
				} // endif
			
			} // endif
			
		} // endif
		
		return $result;

	} // function
	
} // function