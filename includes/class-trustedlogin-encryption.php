<?php 
/**
 * Class: TrustedLogin Encryption 
 *
 * Provides the ability for encrypted payloads to **only** be opened by vendor-side plugin, and not TrustedLogin.
 *
 * @package trustedlogin-vendor
 * @version 0.1.0
 **/
class TrustedLogin_Encryption {

	private $key_option_name;

	public function __construct() {

		/**
		* Filter allows site admins to change the site option key for storing the keys data.
		*
		* @since 0.8.0
		* 
		* @param string
		* @param TrustedLogin_Encryption $this
		**/
		$this->key_option_name = apply_filters( 'trustedlogin/encryption/keys-option', 'trustedlogin_keys', $this );

	}
	
	/**
	* Returns the existing/saved key set.
	* 
	* @since 0.8.0
	* 
	* @return stdClass|false If keys exist, returns the stdClass of keys. If not, returns false. 
	**/
	private function get_keys(){

		$keys = get_site_option( $this->key_option_name );

		if ( false !== $keys ){
			$keys = json_decode( $keys );
		}
		
		/**
		* Filter allows site admins to change where the key is fetched from.
		*
		* @param stdClass 	$keys
		* @param TrustedLogin_Encryption $this
		**/
		return apply_filters( 'trustedlogin/encryption/get-keys', $keys, $this );
	}

	/**
	* Creats a new public/private key set.
	*
	* @since 0.8.0 
	* 
	* @return stdClass 	$keys {
	* 	The keys to save.
	* 	
	* 	@type string $private_key The private key used for decrypting.
	* 	@type string $public_key  The public key used for encrypting.
	* }
	**/
	private function create_keys() {

		$config = array(
		    'digest_alg' => 'sha512',
		    'private_key_bits' => 4096,
		    'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);
		   
		// Create the private and public key
		$res = openssl_pkey_new($config);

		// Extract the private key from $res to $private_key
		openssl_pkey_export($res, $private_key);

		// Extract the public key from $res to $public_key
		$public_key = openssl_pkey_get_details($res);
		$public_key = $public_key['key'];

		$keys = (object) array( 'private_key' => $private_key, 'public_key' => $public_key );

		return $keys;
	}

	/**
	* Saves the key pair to the local database for future use.
	*
	* @since 0.8.0 
	*
	* @see TrustedLogin_Encryption::create_keys()
	*
	* @param   stdClass  The keys to save. 
	* @return  mixed  True if keys saved. WP_Error if not.
	**/
	private function update_keys( $keys ){

		if ( empty( $keys ) ){
			return new WP_Error( 'empty_keys', 'Keys cannot be empty' );
		}

		$keys_db_ready = json_encode( $keys );
	
		$saved = update_site_option( $this->key_option_name, $keys_db_ready );
		
		if ( ! $saved ){
			return new WP_Error( 'db_error','Could not save keys to database' );
		}

		return true;

	}

	/**
	* Returns a public key for encryption.
	*
	* Used for sending to client-side plugin (via SaaS) to encrypt envelopes with before sending to Vault.
	*
	* @since 0.8.0 
	*
	* @returns string 	A public key in which to encrypt 
	**/
	public function get_public_key(){

		$public_key = false;
		$keys = $this->get_keys();

		if ( $keys ){
			
			if ( property_exists( $keys, 'public_key' ) ){
				$public_key = $keys->public_key;
			}
			
		} 

		if ( $public_key ){
			return $public_key;
		}

		$keys = $this->create_keys();
		$saved = $this->update_keys( $keys );

		if ( is_wp_error( $saved )){
			return $saved;
		}

		return $keys->public_key;

	}

	/**
	* Decrypts an encrypted payload.
	*
	* @since 0.8.0
	*
	* @param  string  $encrypted_payload  Base 64 encoded string that needs to be decrypted.
	* @return string|WP_Error If successful the decrypted string (could be a JSON string), otherwise WP_Error.
	**/
	public function decrypt( $encrypted_payload ){

		$decrypted_payload = '';

		$keys = $this->get_keys();

		if ( !$keys || !property_exists( $keys, 'private_key' ) ){
			return new WP_Error( 'key_error', 'Cannot get keys from the local DB.' );
		} 

		if ( empty( $encrypted_payload ) ){
			return new WP_Error( 'data_empty', 'Will not decrypt an empty payload.' );
		}

		$encrypted_payload = base64_decode( $encrypted_payload );

		if ( false == $encrypted_payload ){
			// Data was not successfully base64_decode'd
			return new WP_Error( 'data_malformated', 'Encrypted data needed to be base64 encoded.' );
		}

		openssl_private_decrypt($encrypted_payload, $decrypted_payload, $keys->private_key, OPENSSL_PKCS1_OAEP_PADDING);

		if ( empty( $decrypted_payload )){
			return new WP_Error( 'decryption_failed', 'Decryption failed.' );
		}

		return $decrypted_payload;

	}
}