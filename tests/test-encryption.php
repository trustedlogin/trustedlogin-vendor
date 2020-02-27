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
	 * @covers TrustedLogin_Encryption::create_keys
	 */
	function test_create_keys() {

		$method = new ReflectionMethod( 'TrustedLogin_Encryption', 'create_keys' );
		$method->setAccessible( true );
		$keys = $method->invoke( $this->encryption, 'create_keys' );

		$this->assertTrue( is_object( $keys ), 'create_keys should return an object' );
		$this->assertObjectHasAttribute( 'public_key', $keys, 'public_key should be returned by create_keys ');
		$this->assertObjectHasAttribute( 'private_key', $keys, 'private_key should be returned by create_keys ');

	}

	/**
	 * @covers TrustedLogin_Encryption::get_keys
	 */
	function test_get_keys() {

		$method = new ReflectionMethod( 'TrustedLogin_Encryption', 'create_keys' );
		$method->setAccessible( true );
		$create_keys = $method->invoke( $this->encryption, 'create_keys' );

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

		$this->assertIsString( $public_key );
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
