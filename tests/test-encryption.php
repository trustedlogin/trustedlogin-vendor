<?php
/**
 * Class EncryptionTest
 *
 * @package Tl_Support_Side
 */

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
	public function __construct() {
		$this->TL = new TrustedLogin_Support_Side;
		$this->TL->setup();

		$this->encryption = new TrustedLogin_Encryption();
	}

	/**
	 * @covers TrustedLogin_Encryption::generate_keys
	 */
	function test_generate_keys() {

		$property = new ReflectionProperty( $this->encryption, 'key_option_name' );
		$property->setAccessible( true );
		$option_name = $property->getValue( $this->encryption );

		$this->assertEmpty( get_site_option( $option_name ) );

		$method = new ReflectionMethod( 'TrustedLogin_Encryption', 'generate_keys' );
		$method->setAccessible( true );

		$keys = $method->invoke( $this->encryption, false );

		// Don't set keys yet (passed false above)
		$this->assertEmpty( get_site_option( $option_name ) );

		$this->assertTrue( is_object( $keys ), 'create_keys should return an object' );
		$this->assertObjectHasAttribute( 'public_key', $keys, 'public_key should be returned by create_keys ');
		$this->assertObjectHasAttribute( 'private_key', $keys, 'private_key should be returned by create_keys ');

		$keys = $method->invoke( $this->encryption, true );

		$stored_value = get_site_option( $option_name );

		// Don't set keys yet (passed false above)
		$this->assertNotEmpty( $stored_value );

		$this->assertEquals( json_encode( $keys ), $stored_value );
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


		add_filter( 'trustedlogin/encryption/keys-option', function() {
			return 'should_be_filtered';
		});

		$Encryption_Class = new TrustedLogin_Encryption();
		$property = new ReflectionProperty( $Encryption_Class, 'key_option_name' );
		$property->setAccessible( true );
		$setting_name = $property->getValue( $Encryption_Class );
		$this->assertEquals( $setting_name, 'should_be_filtered' );
	}

	/**
	 * @covers TrustedLogin_Encryption::get_keys
	 */
	function test_get_keys() {

		$method_create_keys = new ReflectionMethod( 'TrustedLogin_Encryption', 'create_keys' );
		$method_create_keys->setAccessible( true );

		/** @see TrustedLogin_Encryption::create_keys() */
		$create_keys = $method_create_keys->invoke( $this->encryption );

		$keys = get_site_option( $this->key_option_name );

		/** @see TrustedLogin_Encryption::get_keys() */
		$method = new ReflectionMethod( 'TrustedLogin_Encryption', 'get_keys' );
		$method->setAccessible( true );
		$keys = $method->invoke( $this->encryption, 'get_keys' );

		$this->assertTrue( is_object( $keys ), 'get_keys should return an object' );
		$this->assertObjectHasAttribute( 'public_key', $keys, 'public_key should be returned by get_keys ');
		$this->assertObjectHasAttribute( 'private_key', $keys, 'private_key should be returned by get_keys ');

	}

	/**
	 * @covers TrustedLogin_Encryption::get_public_key
	 */
	function test_get_public_key() {

		$public_key = $this->encryption->get_public_key();

		$this->assertTrue( is_string( $public_key ) );

		$this->assertContains( '-----BEGIN PUBLIC KEY-----', $public_key );
	}

	/**
	 * @covers TrustedLogin_Encryption::create_identity_nonce
	 */
	function test_create_identity_nonce() {

		$nonces = $this->encryption->create_identity_nonce();

		$this->assertTrue( is_array( $nonces ), 'create_identity_nonce should return an array' );

		$this->assertArrayHasKey( 'nonce', $nonces, 'create_identity_nonce return array should contain a nonce key' );
		$this->assertArrayHasKey( 'signed', $nonces, 'create_identity_nonce return array should contain a signed key' );

	}

	/**
	 * Tests to make sure the public key can be used to decrypt the nonce
	 */
	function test_decrypt_nonce(){

		$nonces = $this->encryption->create_identity_nonce();

		$this->assertNotWPError( $nonces );

		/** @see TrustedLogin_Encryption::get_keys() */
		$method = new ReflectionMethod( 'TrustedLogin_Encryption', 'get_keys' );
		$method->setAccessible( true );
		$keys = $method->invoke( $this->encryption, 'get_keys' );

		openssl_public_decrypt( base64_decode( $nonces['signed'] ), $decrypted, $keys->public_key, OPENSSL_PKCS1_PADDING );

		$this->assertIsString( $decrypted, 'openssl_public_decrypt should return a string' );

		$nonce_decoded = base64_decode( $nonces['nonce'] );

		$this->assertIsString( $nonce_decoded, 'base64_decode( nonces[nonce] ) should return a string' );

		$this->assertEquals( $decrypted, $nonce_decoded, 'decrypting nonces[signed] should equal nonces[nonce]' );

	}




}
