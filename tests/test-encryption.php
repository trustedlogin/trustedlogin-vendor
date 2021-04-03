<?php
/**
 * Class EncryptionTest
 *
 * @package Tl_Support_Side
 */

use TrustedLogin\Vendor\Encryption;

/**
 * Tests for Audit Logging
 */
class EncryptionTest extends \WP_UnitTestCase {

	/** @var TrustedLogin\Vendor\Plugin */
	private $TL;

	/** @var TrustedLogin\Encryption  */
	private $encryption;

	/**
	 * AuditLogTest constructor.
	 */
	public function setUp() {
		$this->TL = new TrustedLogin\Vendor\Plugin();
		$this->TL->setup();

		$this->encryption = new TrustedLogin\Vendor\Encryption();
	}

	/**
	 * @covers Encryption::generate_keys
	 */
	function test_generate_keys() {

		$property = new ReflectionProperty( $this->encryption, 'key_option_name' );
		$property->setAccessible( true );
		$option_name = $property->getValue( $this->encryption );

		$this->assertEmpty( get_site_option( $option_name ) );

		$method = new ReflectionMethod( 'TrustedLogin\Vendor\Encryption', 'generate_keys' );
		$method->setAccessible( true );

		$keys = $method->invoke( $this->encryption, false );

		// Don't set keys yet (passed false above)
		$this->assertEmpty( get_site_option( $option_name ) );

		$this->assertTrue( is_object( $keys ), 'create_keys should return an object' );
		$this->assertObjectHasAttribute( 'public_key', $keys, 'public_key should be returned by create_keys ');
		$this->assertObjectHasAttribute( 'private_key', $keys, 'private_key should be returned by create_keys ');

		// Now we set keys
		$keys = $method->invoke( $this->encryption, true );

		$stored_value = get_site_option( $option_name );

		$this->assertNotEmpty( $stored_value );
		$this->assertEquals( json_encode( $keys ), $stored_value );

		delete_site_option( $option_name );
	}

	/**
	 * @covers TrustedLogin_Encryption::__construct()
	 * @throws ReflectionException
	 */
	function test_key_setting_name_filter() {
		$property = new ReflectionProperty( $this->encryption, 'key_option_name' );
		$property->setAccessible( true );
		$setting_name = $property->getValue( $this->encryption );
		$this->assertEquals( $setting_name, 'trustedlogin_keys' );
		delete_site_option( $setting_name );


		// Test what happens when filtering the setting name
		add_filter( 'trustedlogin/vendor/encryption/keys-option', function() {
			return 'should_be_filtered';
		});

		$Encryption_Class = new TrustedLogin\Vendor\Encryption();
		$property = new ReflectionProperty( $Encryption_Class, 'key_option_name' );
		$property->setAccessible( true );
		$setting_name = $property->getValue( $Encryption_Class );
		$this->assertEquals( $setting_name, 'should_be_filtered' );

		delete_site_option( $setting_name );
	}

	private function delete_key_option() {
		$property = new ReflectionProperty( $this->encryption, 'key_option_name' );
		$property->setAccessible( true );
		$setting_name = $property->getValue( $this->encryption );
		delete_site_option( $setting_name );
	}

	/**
	 * @covers Encryption::get_keys()
	 * @covers Encryption::generate_keys()
	 */
	function test_get_keys() {

		$method_generate_keys = new ReflectionMethod( 'TrustedLogin\Vendor\Encryption', 'generate_keys' );
		$method_generate_keys->setAccessible( true );

		/** @see TrustedLogin_Encryption::get_keys() */
		$method_get_keys = new ReflectionMethod( 'TrustedLogin\Vendor\Encryption', 'get_keys' );
		$method_get_keys->setAccessible( true );

		$this->delete_key_option();

		$keys = $method_get_keys->invoke( $this->encryption, false );
		$this->assertFalse( $keys, 'When $generate_if_not_set is false, there should be no keys' );

		/** @see TrustedLogin\Vendor\Encryption::generate_keys() */
		$generated_keys = $method_generate_keys->invoke( $this->encryption, true );

		$keys = $method_get_keys->invoke( $this->encryption, false, 'But there should be keys after they have been created.' );

		$this->assertEquals( $keys, $generated_keys, 'And when the keys are already generated, they should match the DB-stored ones' );

		$this->delete_key_option();

		$keys = $method_get_keys->invoke( $this->encryption, true );

		$this->assertTrue( is_object( $keys ), 'And there should be keys if $generate_if_not_set is true' );
		$this->assertObjectHasAttribute( 'public_key', $keys, 'public_key should be returned by get_keys ');
		$this->assertObjectHasAttribute( 'private_key', $keys, 'private_key should be returned by get_keys ');

		add_filter( 'trustedlogin/vendor/encryption/get-keys', '__return_zero' );

		$zero = $method_get_keys->invoke( $this->encryption, true );

		$this->assertEquals( 0, $zero, 'trustedlogin/vendor/encryption/get-keys filter failed' );

		remove_all_filters( 'trustedlogin/vendor/encryption/get-keys' );
	}

	/**
	 * @covers TrustedLogin\Vendor\Encryption::get_public_key
	 */
	function test_get_public_key() {

		$public_key = $this->encryption->get_public_key();

		$this->assertTrue( is_string( $public_key ) );

		$this->assertEquals( 64, strlen( $public_key ) );
	}

	/**
	 * @covers TrustedLogin\Vendor\Encryption::create_identity_nonce
	 * @covers TrustedLogin\Vendor\Encryption::verify_signature()
	 */
	function test_create_identity_nonce() {

		// Load in Sodium_Compat
		include_once plugin_dir_path( TRUSTEDLOGIN_PLUGIN_FILE ) . 'vendor/autoload.php';

		$nonces = $this->encryption->create_identity_nonce();

		$this->assertTrue( is_array( $nonces ), 'create_identity_nonce should return an array' );

		$this->assertArrayHasKey( 'nonce', $nonces, 'create_identity_nonce return array should contain a nonce key' );
		$this->assertArrayHasKey( 'signed', $nonces, 'create_identity_nonce return array should contain a signed key' );

		$unsigned_nonce = base64_decode( $nonces['nonce'] );
		$signed_nonce = base64_decode( $nonces['signed'] );

		$this->assertEquals( \ParagonIE_Sodium_Compat::CRYPTO_SIGN_BYTES, strlen( $signed_nonce ) );

		/** @see TrustedLogin_Encryption::get_keys() */
		$method_verify_signature = new ReflectionMethod( 'TrustedLogin\Vendor\Encryption', 'verify_signature' );
		$method_verify_signature->setAccessible( true );
		$verified = $method_verify_signature->invoke( $this->encryption, $signed_nonce, $unsigned_nonce );
		$this->assertNotWPError( $verified );

		/** @var WP_Error $type_error */
		$type_error = $method_verify_signature->invoke( $this->encryption, 1, $unsigned_nonce );
		$this->assertWPError( $type_error, 'Integer values should not be allowed by sodium_crypto_sign_verify_detached(). This should have thrown an error.' );
		$this->assertEquals( 'sodium-type-error', $type_error->get_error_code() );

		/** @var WP_Error $wp_error */
		$wp_error = $method_verify_signature->invoke( $this->encryption, 'asdasdsad', $unsigned_nonce );
		$this->assertWPError( $wp_error, 'The signed nonce was made up; this should not have passed.' );
		$this->assertEquals( 'sodium-error', $wp_error->get_error_code() );

		add_filter( 'trustedlogin/vendor/encryption/get-keys', $bad_range_key = function( $keys ) {

			$keys->sign_public_key = 'should be 64 bytes long...';

			return $keys;
		});

		/** @var WP_Error $wp_error */
		$wp_error = $method_verify_signature->invoke( $this->encryption, $signed_nonce, $unsigned_nonce );
		$this->assertWPError( $wp_error, 'The key was not the correct number of characters; this should not have passed.' );
		$this->assertEquals( 'sodium-range-error', $wp_error->get_error_code() );

		remove_filter( 'trustedlogin/vendor/encryption/get-keys', $bad_range_key );

		/** @var WP_Error $wp_error */
		$wp_error = $method_verify_signature->invoke( $this->encryption, $signed_nonce, str_shuffle( $unsigned_nonce ) );
		$this->assertWPError( $wp_error, 'The nonce was modified, so this should not have passed.' );
		$this->assertEquals( 'signature-failure', $wp_error->get_error_code() );

	}

	/**
	 * Tests to make sure the decryption doesn't fail because of sodium issues
	 *
	 * @todo Update this test to actually check whether it can decrypt properly...
	 *
	 * @covers TrustedLogin\Vendor\Encryption::create_identity_nonce
	 * @uses \TrustedLogin\Vendor\Encryption::get_keys
	 */
	function test_decrypt_passes_sodium_at_least(){

		$nonces = $this->encryption->create_identity_nonce();

		/** @see TrustedLogin\Vendor\Encryption::get_keys() */
		$method = new ReflectionMethod( 'TrustedLogin\Vendor\Encryption', 'get_keys' );
		$method->setAccessible( true );
		$keys = $method->invoke( $this->encryption, true );

		$this->assertObjectHasAttribute( 'public_key', $keys );

		$nonce = \sodium_bin2hex( \random_bytes( SODIUM_CRYPTO_BOX_NONCEBYTES ) );

		$decrypted = $this->encryption->decrypt( 'Very encrypted.', $nonce, $keys->public_key );

		$this->assertNotEquals( 'sodium-error', $decrypted->get_error_code(), 'The sodium process requires specific parameters that were not met: ' . $decrypted->get_error_message() );

		$this->assertEquals( 'decryption_failed', $decrypted->get_error_code(), $decrypted->get_error_message() );

		// TODO: Actual decryption test :facepalm:
	}

}
