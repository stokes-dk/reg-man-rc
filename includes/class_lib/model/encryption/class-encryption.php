<?php
namespace Reg_Man_RC\Model\Encryption;

use Reg_Man_RC\Model\Error_Log;

/**
 * This class provides methods for encrypting and decrypting sensitive data so it can be safely stored in the database.
 *
 * @since	v0.5.0
 *
 */
class Encryption {
	
	const ENCRYPTION_TYPE_SETTING_KEY	= 'reg-man-rc-encryption-type';
	const ENCRYPTION_TYPE_SODIUM		= 'reg-man-rc-encryption-type-sodium';
	const ENCRYPTION_TYPE_OPEN_SSL		= 'reg-man-rc-encryption-type-open-ssl';
	
	private static $SINGLETON;

	private $encryption_strategy;
	
	private $hash_salt;
	private $hash_method;
	private $hash_length;
	
	private function __construct() {

		// Set up encryption
		$encryption_type = self::get_encryption_type();
		
		switch( $encryption_type ) {

			case self::ENCRYPTION_TYPE_SODIUM:
				$this->encryption_strategy = Sodium_Encryption::create();
				break;
			
			case self::ENCRYPTION_TYPE_OPEN_SSL:
				$this->encryption_strategy = Open_SSL_Encryption::create();
				break;
				
			default:
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because no known encryption method is available.', 'reg-man-rc' );
				break;
				
		} // endswitch
			
		
		// Set up hashing
		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			
			$this->hash_salt = LOGGED_IN_SALT;
			
		} else {
			
			Error_Log::log_msg( 'WARNING: Hash salt is missing.', 'reg-man-rc' );
			$this->hash_salt = '';
			
		} // endif
		
		// N.B. The following constants must NEVER be changed because hashed data in the database will become unsearchable.
		$this->hash_method = 'sha256';
		$this->hash_length = 6;
			
	} // function
	
	/**
	 * Get the type of encryption we are using on this platform.
	 * This will be established once and stored as an option. It should never change after that.
	 * @return string
	 */
	private static function get_encryption_type() {
		
		$result = trim( strval( get_option( self::ENCRYPTION_TYPE_SETTING_KEY ) ) );
		
		// Once the encryption type is determined, it is stored as an option and should never change
		if ( empty( $result ) ) {

			if ( defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' ) ) {
				
				$result = self::ENCRYPTION_TYPE_SODIUM;
				
			} elseif ( extension_loaded( 'openssl' ) ) {

				$result = self::ENCRYPTION_TYPE_OPEN_SSL;
				
			} else {
	
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because no known encryption strategy is available.', 'reg-man-rc' );
	
			} // endif

			if ( ! empty( $result ) ) {
				update_option( self::ENCRYPTION_TYPE_SETTING_KEY, $result );
			} // endif
			
		} // endif
		
		return $result;
		
	} // function

	/**
	 * Get the encrypted version of the specified message
	 * @param	string $msg
	 * @return	string
	 */
	public static function encrypt( $msg ) {
		$singleton = self::get_singleton();
		$strategy = $singleton->encryption_strategy;
		$result = isset( $strategy ) ? $strategy->encrypt( $msg ) : $msg;
		return $result;
	} // function
	
	/**
	 * Get the decrypted version of the specified message
	 * @param	string $encrypted_msg
	 * @return	string
	 */
	public static function decrypt( $encrypted_msg ) {
		$singleton = self::get_singleton();
		$strategy = $singleton->encryption_strategy;
		$result = isset( $strategy ) ? $strategy->decrypt( $encrypted_msg ) : $encrypted_msg;
		return $result;
	} // function
	
	
/* Open SSL implementaiton
	private function __construct() {
		
		if ( ! extension_loaded( 'openssl' ) ) {

			Error_Log::log_msg( 'ERROR: Cannot encrypt data because openssl extension is not loaded.', 'reg-man-rc' );

		} else {

			// N.B. The following constant must NEVER be changed because encrypted data in the database will become unreadable.
			$this->method = 'aes-128-ctr';

			if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
				$this->key = LOGGED_IN_KEY;
				Error_Log::var_dump( mb_strlen( $this->key, '8bit' ) );
			} else {
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encryption key is missing.', 'reg-man-rc' );
			} // endif
			
			if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
				$this->salt = LOGGED_IN_SALT;
			} else {
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encryption salt is missing.', 'reg-man-rc' );
			} // endif
			
			// N.B. The following constants must NEVER be changed because hashed data in the database will become unsearchable.
			$this->hash_method = 'sha256';
			$this->hash_length = 6;
			
		} // endif
		
	} // function
*/
	
	private static function get_singleton() {
		
		if ( ! isset( self::$SINGLETON ) ) {
			
			self::$SINGLETON = new self();
			
		} // endif
		
		return self::$SINGLETON;
		
	} // function
	
	/**
	 * Get the encrypted version of the specified message
	 * @param	string $msg
	 * @return	string
	 */
/* FIXME - Sodiaum implementation
	public static function encrypt( $msg ) {
		
		$singleton = self::get_singleton();
		$key = $singleton->key;		

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		
		$cipher_msg = $nonce . sodium_crypto_secretbox( $msg, $nonce, $key );
		
		return sodium_bin2base64( $cipher_msg, SODIUM_BASE64_VARIANT_ORIGINAL );

	} // function
*/
	/**
	 * Get the decrypted version of the specified message
	 * @param	string $encrypted_msg
	 * @return	string
	 */
/* FIXME - SODIUM
	public static function decrypt( $encrypted_msg ) {
		
		$singleton = self::get_singleton();
		$key = $singleton->key;

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
*/
	
/* Open SSL implementation
	public static function encrypt( $data ) {
		
		// FIXME - these are the recommended method for encryption:
		//  according to: https://make.wordpress.org/core/2019/05/17/security-in-5-2/
//		sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $message, $additional_data, $nonce, $key );
//		sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, $additional_data, $nonce, $key );

		$singleton = self::get_singleton();
		$method = $singleton->method;
		$key = $singleton->key;
		$salt = $singleton->salt;
			
		if ( ! isset( $key ) || ! isset( $salt ) ) {
			
			$result = $data;
			
		} else {
			
			$salted_data = $data . $salt;
			$options = 0; // no options needed
			
			$init_vector_len = openssl_cipher_iv_length( $method );
			$init_vector = openssl_random_pseudo_bytes( $init_vector_len );
			
			$encrypted_data = openssl_encrypt( $salted_data, $method, $key, $options, $init_vector );
			
			if ( $encrypted_data === FALSE ) {
				
				Error_Log::log_msg( 'ERROR: Cannot encrypt data because encrypt function failed.', 'reg-man-rc' );
				$result = $data;
				
			} else {
				
				$result = base64_encode( $init_vector . $encrypted_data );
				
			} // endif
			
		} // endif
		
		return $result;
		
	} // function
	
	public static function decrypt( $data ) {
		
		$singleton = self::get_singleton();
		$method = $singleton->method;
		$key = $singleton->key;
		$salt = $singleton->salt;
		
		if ( ! isset( $data) || ! isset( $key ) || ! isset( $salt ) ) {
			
			$result = $data;
			
		} else {

			$strict = TRUE;
			$encrypted_data = base64_decode( $data, $strict );
			
			$options = 0; // no options needed
			
			$init_vector_len = openssl_cipher_iv_length( $method );
			$init_vector = substr( $encrypted_data, 0, $init_vector_len );
			
			$encrypted_data = substr( $encrypted_data, $init_vector_len );
			
			$decrypted_data = openssl_decrypt( $encrypted_data, $method, $key, $options, $init_vector );

			if ( $decrypted_data === FALSE ) {

				Error_Log::log_msg( 'ERROR: Cannot decrypt data because decrypt function failed.', 'reg-man-rc' );
				$result = $data;
				
			} else {

				$salt_len = strlen( $salt );
				$decrypted_salt = substr( $decrypted_data, - $salt_len ); // negative ofset returns last n chars of string
				if ( $decrypted_salt !== $salt ) {

					Error_Log::log_msg( 'ERROR: Cannot decrypt data because salt is incorrect.', 'reg-man-rc' );
					$result = $data;
				
				} else {
					
					$result = substr( $decrypted_data, 0, - $salt_len ); // 0 offset and negative length returns first chars up to len 

				} // endif
				
			} // endif
			
		} // endif
			
		return $result;
		
	} // function
*/

	/**
	 * Get a hashcode for the specified data that can be used to facilitate searching encrypted data.
	 * @param 	string	$data
	 * @return	string	A hexadecimal string hash code of fixed length 
	 */
	public static function hash( $data ) {
		
		$singleton = self::get_singleton();

		$salt = $singleton->hash_salt;

		if ( ! isset( $salt ) ) {
			
			$salt = ''; // We can still produce a hashcode but this is a system problem that will already have been reported
			
		} // endif

		// N.B. The following constants must NEVER be changed because hashed data in the database will become unsearchable.
		$hash_method = $singleton->hash_method;
		$hash_length = $singleton->hash_length;
		
		$salted_data = $data . $salt;
		$hash = hash( $hash_method, $salted_data );

		$result = ( substr( $hash, 0, $hash_length ) );
			
		return $result;
		
	} // function

} // class