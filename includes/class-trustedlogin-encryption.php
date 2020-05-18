<?php

namespace TrustedLogin\Vendor;

use WP_Error;

/**
 * Class: TrustedLogin Encryption
 *
 * Provides the ability for encrypted payloads to **only** be opened by vendor-side plugin, and not TrustedLogin.
 *
 * @package trustedlogin-vendor
 * @version 0.1.0
 */
class Encryption {

	use Debug_Logging;

	private $key_option_name = 'trustedlogin_keys';


	public function __construct() {

		/**
		 * Filter allows site admins to change the site option key for storing the keys data.
		 *
		 * @todo Validate string is short enough to be stored in database
		 * @since 0.8.0
		 *
		 * @param Encryption $this
		 * @param string
		 */
		$this->key_option_name = apply_filters( 'trustedlogin/encryption/keys-option', $this->key_option_name, $this );

	}

	/**
	 * Returns the existing/saved key set.
	 *
	 * @since 0.8.0
	 *
	 * @param bool $generate_if_not_set If keys aren't saved in the database, should create using {@see generate_keys}?
	 *
	 * @return stdClass|WP_Error If keys exist, returns the stdClass of keys. Otherwise, WP_Error explaning things.
	 */
	private function get_keys( $generate_if_not_set = true ) {

		$keys = false;
		$value = get_site_option( $this->key_option_name );

		if ( $value ) {
			$keys = json_decode( $value );

			if( ! $keys ) {
				$this->dlog( "Keys were not decoded properly: " . print_r( $value, true ), __METHOD__ );
			}
		}

		if ( ! $keys && $generate_if_not_set ) {
			$keys = $this->generate_keys( true );
		}

		/**
		 * Filter allows site admins to change where the key is fetched from.
		 *
		 * @param stdClass|WP_Error $keys
		 * @param Encryption $this
		 */
		return apply_filters( 'trustedlogin/encryption/get-keys', $keys, $this );
	}

	/**
	 * Creats a new public/private key set.
	 *
	 * @since 0.8.0
	 *
	 * @param bool $update Whether to update the database with the new keys. Default: true
	 *
	 * @return  stdClass|WP_Error  $keys or WP_Error if any issues
	 *    $keys = [
	 *        'private_key' 	 => (string)  The private key used for encrypt/decrypt.
	 *        'public_key' 		 => (string)  The public key used for encrypt/decrypt.
	 *        'sign_public_key'  => (string)  The public key used for signing/verifying.
	 *        'sign_private_key' => (string)  The private key used for signing/verifying.
	 *    ]
	 */
	private function generate_keys( $update = true ) {

		if ( ! function_exists( 'sodium_crypto_box_keypair' ) ) {
			return new \WP_Error( 'sodium_not_exists', 'Sodium isn\'t loaded. Upgrade to PHP 7.0 or WordPress 5.2 or higher.' );
		}

		try {

			// Keeping named $bob_{name} for clarity while implementing:
			// https://paragonie.com/book/pecl-libsodium/read/05-publickey-crypto.md
			$bob_box_kp 	    = \sodium_crypto_box_keypair();
			$bob_box_secretkey  = \sodium_crypto_box_secretkey( $bob_box_kp );
			$bob_box_publickey  = \sodium_crypto_box_publickey( $bob_box_kp );

			$bob_sign_kp 		= \sodium_crypto_sign_keypair();
			$bob_sign_publickey = \sodium_crypto_sign_publickey( $bob_sign_kp );
			$bob_sign_secretkey = \sodium_crypto_sign_secretkey( $bob_sign_kp );

			$keys = (object) array(
				'private_key' 	   => \sodium_bin2hex( $bob_box_secretkey ),
				'public_key'	   => \sodium_bin2hex( $bob_box_publickey ),
				'sign_private_key' => \sodium_bin2hex( $bob_sign_secretkey),
				'sign_public_key'  => \sodium_bin2hex( $bob_sign_publickey)
			);

			if( $update ) {
				$updated = $this->update_keys( $keys );

				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			return $keys;

		} catch ( \SodiumException $e ) {
		    return new WP_Error('sodium-error', $e->getMessage() );
	    }
	}

	/**
	 * Saves the key pair to the local database for future use.
	 *
	 * @since 0.8.0
	 *
	 * @see Encryption::create_keys()
	 *
	 * @param stdClass $keys The keys to save.
	 *
	 * @return true|WP_Error True if keys saved. WP_Error if not.
	 */
	private function update_keys( $keys ) {

		$keys_db_ready = json_encode( $keys );

		if ( ! $keys_db_ready ) {
			return new \WP_Error( 'json_error', 'Could not encode keys to JSON.', $keys );
		}

		// Instead of update_site_option(), which can return false if value didn't change, success is much clearer
		// when deleting and checking whether adding worked
		delete_site_option( $this->key_option_name );

		$saved = add_site_option( $this->key_option_name, $keys_db_ready );

		if ( ! $saved ) {
			return new \WP_Error( 'db_error', 'Could not save keys to database.' );
		}

		return true;
	}

	/**
	 * Gets a specific public cryptographic key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key_slug  The slug of the key to fetch.
	 *                          Options are 'public_key' (default), 'sign_public_key'.
	 *
	 * @return string|WP_Error  Returns key if found, otherwise WP_Error.
	 */
	public function get_public_key( $key_slug = 'public_key' ){

		$keys = $this->get_keys();

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		if ( ! in_array( $key_slug, array( 'public_key', 'sign_public_key' ) ) ){
			return new \WP_Error( 'not_public_key', 'This function can only return public keys' );
		}

		if ( ! $keys || ! is_object( $keys ) || ! property_exists( $keys, $key_slug ) ) {
			return new \WP_Error( 'get_key_failed', \sprintf('Could not get %s from get_key.', $key_slug ) );
		}

		return $keys->{$key_slug};
	}

	/**
	 * Gets a specific private cryptographic key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key_slug  The slug of the key to fetch.
	 *                          Options are 'public_key' (default), 'sign_public_key'.
	 *
	 * @return string|WP_Error  Returns key if found, otherwise WP_Error.
	 */
	private function get_private_key( $key_slug = 'private_key' ){

		$keys = $this->get_keys();

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		if ( ! in_array( $key_slug, array( 'private_key', 'sign_private_key' ) ) ){
			return new \WP_Error( 'not_public_key', 'This function can only return private keys' );
		}

		if ( ! $keys || ! is_object( $keys ) || ! property_exists( $keys, $key_slug ) ) {
			return new \WP_Error( 'get_key_failed', \sprintf('Could not get %s from get_key.', $key_slug ) );
		}

		return $keys->{$key_slug};
	}

	/**
	 * Decrypts an encrypted payload.
	 *
	 * @since 0.8.0
	 * @since 1.0.0 - Added $nonce and $client_public_key params
	 *
	 * @uses \sodium_crypto_box_keypair_from_secretkey_and_publickey()
	 * @uses \sodium_crypto_box_open()
	 *
	 * @param string $encrypted_payload Base 64-encoded string that needs to be decrypted.
	 * @param string $nonce Single use nonce for a specific Client.
	 * @param string $client_public_key The public key from the Client plugin that generated the envelope.
	 *
	 * @return string|WP_Error If successful the decrypted string (could be a JSON string), otherwise WP_Error.
	 */
	public function decrypt( $encrypted_payload, $nonce, $client_public_key ) {

		if ( ! function_exists( 'sodium_crypto_box_open' ) ) {
			return new \WP_Error( 'sodium_not_exists', 'Sodium isn\'t loaded. Upgrade to PHP 7.0 or WordPress 5.2 or higher.' );
		}

		try {

			$private_key = $this->get_private_key();

			if ( is_wp_error( $private_key ) ) {
				return new \WP_Error( 'key_error', 'Cannot decrypt: can\'t get private keys from the local DB.', $private_key );
			}

			if ( empty( $encrypted_payload ) ) {
				return new \WP_Error( 'data_empty', 'Will not decrypt an empty payload.' );
			}

			$encrypted_payload = base64_decode( $encrypted_payload );

			if ( false === $encrypted_payload ) {
				// Data was not successfully base64_decode'd
				return new \WP_Error( 'data_malformated', 'Encrypted data must be base64 encoded.' );
			}

			$decryption_key = \sodium_crypto_box_keypair_from_secretkey_and_publickey( $private_key, $client_public_key );

			$decrypted_payload = \sodium_crypto_box_open( $encrypted_payload, $nonce, $decryption_key );

			if ( empty( $decrypted_payload ) ) {
				return new \WP_Error( 'decryption_failed', 'Decryption failed.' );
			}

			return $decrypted_payload;

		} catch ( \SodiumException $e ) {
		    return new WP_Error('sodium-error', $e->getMessage() );
	    }

	}

	/**
	 * Returns an pair of values to verify identity.
	 *
	 * This pair acts as a signature, helping to verify that this site is indeed the sender of the data.
	 *
	 * @since 0.8.0
	 *
	 * @return  array|WP_Error  $identity or WP_Error if any issues
	 *    $identity = [
	 *        'nonce'  => (string)  A base64 encoded random string
	 *        'signed' => (string)  The `nonce` encrypted with this site's Private Key, also base64 encoded.
	 *    ]
	 */
	public function create_identity_nonce() {

		$identity = array();

		$unsigned_nonce = $this->generate_nonce();

		if ( is_wp_error( $unsigned_nonce ) ) {
			return $unsigned_nonce;
		}

		$key = $this->get_private_key( 'sign_private_key' );

		if ( is_wp_error( $key ) ){
			return $key;
		}

		$signed_nonce = $this->sign( $unsigned_nonce, $key );

		if ( is_wp_error( $signed_nonce ) ) {
			return $signed_nonce;
		}

		$verified = $this->verify_signature( $signed_nonce, $unsigned_nonce );

		if ( is_wp_error( $verified ) ){
			return $verified;
		}

		$identity['nonce']  = base64_encode( $unsigned_nonce );
		$identity['signed'] = base64_encode( $signed_nonce );

		return $identity;
	}

	/**
	* Verifies if signature validates correctly
	*
	* @since 1.0.0
	*
	* @param string $signed_nonce
	* @param string $unsigned_nonce
	*
	* @return bool|WP_Error  True if signature validates correctly, otherwise false. Returns WP_Error on issue.
	*/
	private function verify_signature( $signed_nonce, $unsigned_nonce ){

		try {

			$sign_public_key = $this->get_public_key('sign_public_key');

			if ( is_wp_error( $sign_public_key ) ){
				return $sign_public_key;
			}

			$message_valid = \sodium_crypto_sign_verify_detached(
				$signed_nonce,
				$unsigned_nonce,
				\sodium_hex2bin( $sign_public_key )
			);
			$this->dlog( "message_valid: ". print_r( $message_valid , true ), __METHOD__ );

			if ( ! $message_valid ){
				return new WP_Error( 'signature-failure', 'Signature will not pass verification');
			}

			return $message_valid;

		} catch ( \SodiumException $e ) {
		    return new WP_Error('sodium-error', $e->getMessage() );
	    }
	}

	/**
	 * Generates a cryptographic nonce .
	 *
	 * @since 1.0.0
	 *
	 * @uses \random_bytes()
	 *
	 * @return string|WP_Error  If generated, a nonce. Otherwise a WP_Error.
	 */
	private function generate_nonce(){

		if ( ! function_exists( 'sodium_bin2hex' ) ) {
			return new \WP_Error( 'sodium_not_exists', 'Sodium isn\'t loaded. Upgrade to PHP 7.0 or WordPress 5.2 or higher.' );
		}

		try {

			return \sodium_bin2hex( \random_bytes( SODIUM_CRYPTO_BOX_NONCEBYTES ) );

		} catch ( \SodiumException $e ) {
		    return new WP_Error('sodium-error', $e->getMessage() );
	    }

	}

	/**
	 * Signs a string using the Signature Private Key provided by the plugin/theme developers' server.
	 *
	 * @since 0.8.0
	 * @since 1.0.0
	 *
	 * @uses \sodium_crypto_sign_detached for signing.
	 *
	 * @param string $data Data to encrypt.
	 * @param string $key Key to use to encrypt the data.
	 *
	 * @return string|WP_Error  Signed value or WP_Error on failure.
	 */
	private function sign( $data, $key ) {

		try {

			if ( empty( $data ) || empty( $key ) ) {
				return new \WP_Error( 'no_data', 'No data provided.' );
			}

			if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
				return new \WP_Error( 'sodium_not_exists', 'Sodium isn\'t loaded. Upgrade to PHP 7.0 or WordPress 5.2 or higher.' );
			}

			$signed = \sodium_crypto_sign_detached( $data, \sodium_hex2bin( $key ) );

			return $signed;

		} catch ( \SodiumException $e ) {
			return new WP_Error( 'sodium-error', $e->getMessage() );
		}
	}
}
