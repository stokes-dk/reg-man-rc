<?php
namespace Reg_Man_RC\Model\Encryption;

use Reg_Man_RC\Model\Error_Log;

/**
 * This class provides the OpenSSL implementation for encrypting and decrypting sensitive data
 *
 * @since	v0.5.0
 *
 */
class Open_SSL_Encryption implements Encryption_Strategy {
	
	private $method;
	private $key;
	private $salt;
	
	/**
	 * Create an instance of this class
	 * @return Open_SSL_Encryption
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function
	
	private function __construct() {
		
		if ( ! extension_loaded( 'openssl' ) ) {

			Error_Log::log_msg( 'ERROR: Cannot encrypt data because openssl extension is not loaded.', 'reg-man-rc' );

		} else {

			// N.B. The following constant must NEVER be changed because encrypted data in the database will become unreadable.
			$this->method = 'aes-128-ctr';

			if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
				$this->key = LOGGED_IN_KEY;
			} else {
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encryption key is missing.', 'reg-man-rc' );
			} // endif
			
			if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
				$this->salt = LOGGED_IN_SALT;
			} else {
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encryption salt is missing.', 'reg-man-rc' );
			} // endif
			
		} // endif
		
	} // function
	
	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Encryption\Encryption_Strategy::encrypt()
	 */
	public function encrypt( $msg ) {
		
		$key = $this->key;
		$salt = $this->salt;
			
		if ( ! isset( $key ) || ! isset( $salt ) ) {
			
			$result = $msg;
			
		} else {
			
			$salted_data = $msg . $salt;
			$method = $this->method;
			$options = 0; // no options needed
			
			$init_vector_len = openssl_cipher_iv_length( $method );
			$init_vector = openssl_random_pseudo_bytes( $init_vector_len );
			
			$encrypted_data = openssl_encrypt( $salted_data, $method, $key, $options, $init_vector );
			
			if ( $encrypted_data === FALSE ) {
				
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encrypt function failed.', 'reg-man-rc' );
				$result = $msg;
				
			} else {
				
				$result = base64_encode( $init_vector . $encrypted_data );
				
			} // endif
			
		} // endif
		
		return $result;
		
	} // function

	/**
	 * {@inheritDoc}
	 * @see \Reg_Man_RC\Model\Encryption\Encryption_Strategy::decrypt()
	 */
	public function decrypt( $encrypted_msg ) {
		
		$method = $this->method;
		$key = $this->key;
		$salt = $this->salt;
		
		if ( ! isset( $encrypted_msg) || ! isset( $key ) || ! isset( $salt ) ) {
			
			$result = $encrypted_msg;
			
		} else {

			$strict = TRUE;
			$encrypted_data = base64_decode( $encrypted_msg, $strict );
			
			$options = 0; // no options needed
			
			$init_vector_len = openssl_cipher_iv_length( $method );
			$init_vector = substr( $encrypted_data, 0, $init_vector_len );
			
			$encrypted_data = substr( $encrypted_data, $init_vector_len );
			
			$decrypted_data = openssl_decrypt( $encrypted_data, $method, $key, $options, $init_vector );

			if ( $decrypted_data === FALSE ) {

				Error_Log::log_msg( 'ERROR: Cannot decrypt data because decrypt function failed.', 'reg-man-rc' );
				$result = $encrypted_msg;
				
			} else {

				$salt_len = strlen( $salt );
				$decrypted_salt = substr( $decrypted_data, - $salt_len ); // negative ofset returns last n chars of string
				if ( $decrypted_salt !== $salt ) {

					Error_Log::log_msg( 'ERROR: Cannot decrypt data because salt is incorrect.', 'reg-man-rc' );
					$result = $encrypted_msg;
				
				} else {
					
					$result = substr( $decrypted_data, 0, - $salt_len ); // 0 offset and negative length returns first chars up to len 

				} // endif
				
			} // endif
			
		} // endif
			
		return $result;
		
	} // function

	
} // class