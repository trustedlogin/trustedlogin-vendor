<?php 
/**
 * Class: TrustedLogin Encryption 
 *
 * Provides the ability for encrypted payloads to **only** be opened by vendor-side plugin, and not TrustedLogin.
 *
 * @package trustedlogin-vendor
 * @version 0.1.0
 **/
class TrustedLogin_Encryption
{

	private $key_option_name;

	public function __construct(){

		/**
		* Filter allows site_admins to change the site option key for storing the keys data.
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
	private function get_existing_keys(){

		$keys = get_site_option( $this->key_option_name );

		if ( false !== $keys ){
			$keys = json_decode( $keys );
		}
		
		return apply_filters( 'trustedlogin/encryption/get-keys', $keys );
	}

	/**
	* Checks if keys have been already been generated and saved.
	*
	* @since 0.8.0 
	* 
	* @return boolean
	**/
	private function are_keys_set(){

		$keys = $this->get_existing_keys();

		if ( false == $keys || is_wp_error( $keys )){
			$is_set = false;
		} else {
			$is_set = true;
		}

		/**
		* Filter allows for extending if key sets are set.
		*
		* Usually used in reference to if new keys need to be generated and saved.
		* 
		* @since 0.8.0 
		*
		* @return boolean If keys exist.
		**/
		return apply_filters( "trustedlogin/encryption/are-keys-set", $is_set );
	}

	/**
	* Creats a new public/private key set.
	*
	* @since 0.8.0 
	* 
	* @return stdClass 	$keys {
	* 	The keys to save.
	* 	
	* 	@type string $priKey The private key used for decrypting.
	* 	@type string $pubKey The public key used for encrypting.
	* }
	**/
	private function create_new_keys(){

		$config = array(
		    "digest_alg" => "sha512",
		    "private_key_bits" => 4096,
		    "private_key_type" => OPENSSL_KEYTYPE_RSA,
		);
		   
		// Create the private and public key
		$res = openssl_pkey_new($config);

		// Extract the private key from $res to $priKey
		openssl_pkey_export($res, $priKey);

		// Extract the public key from $res to $pubKey
		$pubKey = openssl_pkey_get_details($res);
		$pubKey = $pubKey["key"];

		$keys = (object) array( 'priKey'=> $priKey, 'pubKey' => $pubKey );

		return $keys;
	}

	/**
	* Saves the key pair to the local database for future use.
	*
	* @since 0.8.0 
	*
	* @see TrustedLogin_Encryption::create_new_keys()
	*
	* @param   stdClass  The keys to save. 
	* @return  mixed  True if keys saved. WP_Error if not.
	**/
	private function save_keys( $keys ){

		if ( empty( $keys ) ){
			return new WP_Error( 'empty_keys', 'Keys cannot be empty' );
		}

		$keys_db_ready = json_encode( $keys );

		if ($this->are_keys_set()){
			$saved = update_site_option( $this->key_option_name, $keys_db_ready );
		} else {
			$saved = add_site_option( $this->key_option_name, $keys_db_ready );
		}

		if ( !$saved ){
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
	public function return_public_key(){

		if ( $this->are_keys_set() ){

			// Keys are set so let's fetch and return the public key

			$keys = $this->get_existing_keys();

			if ( !is_wp_error( $keys )){
				return $keys->pubKey;
			} else {
				return $keys;
			}

		} else {

			// Keys are not set yet, so we'll create new ones and return the public key

			$keys = $this->create_new_keys();
			$saved = $this->save_keys( $keys );

			if ( is_wp_error( $saved )){
				return $saved;
			}

			return $keys->pubKey;
		}

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

		if ( !$this->are_keys_set() ){
			return new WP_Error( 'no_keys', 'Cannot decrypt, as no keys exist yet.' );
		}

		$keys = $this->get_existing_keys();

		if ( !$keys || !property_exists( $keys, 'privKey' ) ){
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

		openssl_private_decrypt($encrypted_payload, $decrypted_payload, $keys->privKey, OPENSSL_PKCS1_OAEP_PADDING);

		if ( empty( $decrypted_payload )){
			return new WP_Error( 'decryption_failed', 'Decryption failed.' );
		}

		return $decrypted_payload;

	}
}