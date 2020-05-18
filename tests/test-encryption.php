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
class EncryptionTest extends WP_UnitTestCase {

	/** @var TrustedLogin_Support_Side */
	private $TL;

	/** @var TrustedLogin_Encryption  */
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
		add_filter( 'trustedlogin/encryption/keys-option', function() {
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
	 * @covers TrustedLogin_Encryption::get_keys()
	 * @covers TrustedLogin_Encryption::generate_keys()
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

		add_filter( 'trustedlogin/encryption/get-keys', '__return_zero' );

		$zero = $method_get_keys->invoke( $this->encryption, true );

		$this->assertEquals( 0, $zero, 'trustedlogin/encryption/get-keys filter failed' );

		remove_all_filters( 'trustedlogin/encryption/get-keys' );
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

		$nonces = $this->encryption->create_identity_nonce();

		$this->assertTrue( is_array( $nonces ), 'create_identity_nonce should return an array' );

		$this->assertArrayHasKey( 'nonce', $nonces, 'create_identity_nonce return array should contain a nonce key' );
		$this->assertArrayHasKey( 'signed', $nonces, 'create_identity_nonce return array should contain a signed key' );

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

		$this->assertNotEquals( 'sodium-error', $decrypted->get_error_code(), 'The sodium process requires specific parameters that were not met.' );

		$this->assertEquals( 'decryption_failed', $decrypted->get_error_code() );

		// TODO: Actual decryption test :facepalm:
	}

}
