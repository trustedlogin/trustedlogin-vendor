<?php

namespace TrustedLogin\Vendor;

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

		$this->dlog( "Keys: " . print_r( $keys, true ), __METHOD__ );

		/**
		 * Filter allows site admins to change where the key is fetched from.
		 *
		 * @param stdClass|\WP_Error $keys
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
	 * @return stdClass|WP_Error $keys {
	 *   The keys to save.
	 *
	 *   @type string $private_key The private key used for decrypting.
	 *   @type string $public_key The public key used for encrypting.
	 * } or WP_Error
	 */
	private function generate_keys( $update = true ) {

		$config = array(
			'digest_alg'       => 'sha512',
			'private_key_bits' => 4096,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);

		// Create the private and public key
		$res = openssl_pkey_new( $config );

		if ( ! $res ) {
			return new WP_Error( 'openssl_error_privatekey', 'Could not generate a private key using OpenSSL.' );
		}

		// Extract the private key from $res to $private_key
		$private_key_success = openssl_pkey_export( $res, $private_key );

		if ( ! $private_key_success || empty( $private_key ) ) {
			return new WP_Error( 'openssl_error_privatekey_export', 'Could not extract a private key using OpenSSL.' );
		}

		// Extract the public key from $res to $public_key
		$public_key = openssl_pkey_get_details( $res );

		if( ! $public_key || ! isset( $public_key['key'] ) ) {
			return new WP_Error( 'openssl_error_publickey', 'Could not get public key details using OpenSSL.' );
		}

		$public_key = $public_key['key'];

		$keys = (object) array(
			'private_key' => $private_key,
			'public_key' => $public_key
		);

		if( $update ) {
			$updated = $this->update_keys( $keys );

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		return $keys;
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
			return new WP_Error( 'json_error', 'Could not encode keys to JSON.', $keys );
		}

		// Instead of update_site_option(), which can return false if value didn't change, success is much clearer
		// when deleting and checking whether adding worked
		delete_site_option( $this->key_option_name );

		$saved = add_site_option( $this->key_option_name, $keys_db_ready );

		if ( ! $saved ) {
			return new WP_Error( 'db_error', 'Could not save keys to database.' );
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
	 * @returns string|WP_Error A public key in which to encrypt, or an error
	 */
	public function get_public_key() {

		$keys = $this->get_keys();

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		if ( $keys && is_object( $keys ) && isset( $keys->public_key ) ) {
			return $keys->public_key;
		}

		return new WP_Error( 'get_keys_failed', 'Could not get public get_keys stored invalid JSON.');
	}

	/**
	 * Decrypts an encrypted payload.
	 *
	 * @since 0.8.0
	 *
	 * @param string $encrypted_payload Base 64 encoded string that needs to be decrypted.
	 *
	 * @return string|WP_Error If successful the decrypted string (could be a JSON string), otherwise WP_Error.
	 */
	public function decrypt( $encrypted_payload ) {

		$decrypted_payload = '';

		$keys = $this->get_keys();

		if ( ! $keys || ! isset( $keys->private_key ) ) {
			return new WP_Error( 'key_error', 'Cannot get keys from the local DB.' );
		}

		if ( empty( $encrypted_payload ) ) {
			return new WP_Error( 'data_empty', 'Will not decrypt an empty payload.' );
		}

		$encrypted_payload = base64_decode( $encrypted_payload );

		if ( false == $encrypted_payload ) {
			// Data was not successfully base64_decode'd
			return new WP_Error( 'data_malformated', 'Encrypted data needed to be base64 encoded.' );
		}

		/**
		 * Note about encryption padding:
		 *
		 * Public Key Encryption (ie that can only be decrypted with a secret private_key) uses `OPENSSL_PKCS1_OAEP_PADDING`.
		 * Private Key Signing (ie verified by decrypting with known public_key) uses `OPENSSL_PKCS1_PADDING`
		 */
		openssl_private_decrypt( $encrypted_payload, $decrypted_payload, $keys->private_key, OPENSSL_PKCS1_OAEP_PADDING );

		if ( empty( $decrypted_payload ) ) {
			return new WP_Error( 'decryption_failed', 'Decryption failed.' );
		}

		return $decrypted_payload;

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

		$keys = $this->get_keys( true );

		if ( ! $keys || ! property_exists( $keys, 'private_key' ) ) {
			return new WP_Error( 'key_error', 'Cannot get keys from the local DB.' );
		}

		$identity = array();

		$pseudo_random = wp_generate_password( 32, true, true );

		$identity['nonce']  = base64_encode( $pseudo_random );
		$identity['signed'] = $this->sign( $pseudo_random, $keys->private_key );

		if ( is_wp_error( $identity['signed'] ) ) {
			return $identity['signed'];
		}

		return $identity;
	}

	/**
	 * Encrypts a string using the Public Key provided by the plugin/theme developers' server.
	 *
	 * @since 0.8.0
	 *
	 * @uses `openssl_public_encrypt()` for encryption.
	 *
	 * @param string $data Data to encrypt.
	 * @param string $key Key to use to encrypt the data.
	 *
	 * @return string|WP_Error  Encrypted envelope or WP_Error on failure.
	 */
	private function encrypt( $data, $key ) {

		if ( empty( $data ) || empty( $key ) ) {
			return new WP_Error( 'no_data', 'No data provided.' );
		}

		/**
		 * Note about encryption padding:
		 *
		 * Public Key Encryption (ie that can only be decrypted with a secret private_key) uses `OPENSSL_PKCS1_OAEP_PADDING`.
		 * Private Key Signing (ie verified by decrypting with known public_key) uses `OPENSSL_PKCS1_PADDING`
		 */
		openssl_public_encrypt( $data, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING );

		if ( empty( $encrypted ) ) {

			$error_string = '';
			while ( $msg = openssl_error_string() ) {
				$error_string .= "\n" . $msg;
			}

			return new WP_Error (
				'encryption_failed',
				sprintf(
					'Could not encrypt envelope. Errors from openssl: %1$s',
					$error_string
				)
			);
		}

		$encrypted = base64_encode( $encrypted );

		return $encrypted;
	}

	/**
	 * Encrypts a string using the Private Key provided by the plugin/theme developers' server.
	 *
	 * @since 0.8.0
	 *
	 * @uses `openssl_private_encrypt()` for encryption.
	 *
	 * @param string $data Data to encrypt.
	 * @param string $key Key to use to encrypt the data.
	 *
	 * @return string|WP_Error  Base64 encoded encrypted value or WP_Error on failure.
	 */
	private function sign( $data, $key ) {

		if ( empty( $data ) || empty( $key ) ) {
			return new WP_Error( 'no_data', 'No data provided.' );
		}

		/**
		 * Note about encryption padding:
		 *
		 * Public Key Encryption (ie that can only be decrypted with a secret private_key) uses `OPENSSL_PKCS1_OAEP_PADDING`.
		 * Private Key Signing (ie verified by decrypting with known public_key) uses `OPENSSL_PKCS1_PADDING`
		 */
		openssl_private_encrypt( $data, $encrypted, $key, OPENSSL_PKCS1_PADDING );

		if ( empty( $encrypted ) ) {

			$error_string = '';
			while ( $msg = openssl_error_string() ) {
				$error_string .= "\n" . $msg;
			}

			return new WP_Error (
				'encryption_failed',
				sprintf(
					'Could not sign data. Errors from openssl: %1$s',
					$error_string
				)
			);
		}

		$encrypted = base64_encode( $encrypted );

		return $encrypted;
	}
}
