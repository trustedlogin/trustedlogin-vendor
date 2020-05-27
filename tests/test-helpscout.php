<?php
/**
 * Class HelpScoutTest
 *
 * @package \TrustedLogin\Vendor
 */

use TrustedLogin\Vendor\Plugin;

/**
 * Tests for Audit Logging
 */
class HelpScoutTest extends WP_UnitTestCase {

	/** @var Plugin */
	private $TL;

	/**
	 * @var \TrustedLogin\Vendor\HelpScout
	 */
	private $HelpScout;

	/**
	 * AuditLogTest constructor.
	 */
	public function setUp() {
		$this->TL = new Plugin();
		$this->TL->setup();

		$this->HelpScout = new TrustedLogin\Vendor\HelpScout();

		parent::setUp();
	}

	function test_verify_request() {

		$verify_request = new ReflectionMethod( get_class( $this->HelpScout ), 'verify_request' );
		$verify_request->setAccessible( true );
		$this->assertFalse( $this->HelpScout->has_secret() );

		$secret = 'asdasdjfiogasdoigesougbseofnad';
		$data   = 'NO ONE SHOULD EVER READ THIS SECRET DATA WITHOUT THE SECRET KEY';
		$expected = base64_encode( hash_hmac( 'sha1', $data, $secret, true ) );

		$this->assertFalse( $verify_request->invoke( $this->HelpScout, $data, $expected ), 'No secret; should fail.' );

		update_option( 'trustedlogin_vendor', array(
			'helpdesk' => array( 'helpscout' ),
			'helpscout_secret' => $secret,
		));

		// After setting a secret, re-instantiate the class
		$this->HelpScout = new \TrustedLogin\Vendor\HelpScout();
		$verify_request = new ReflectionMethod( get_class( $this->HelpScout ), 'verify_request' );
		$verify_request->setAccessible( true );

		$this->assertTrue( $this->HelpScout->has_secret() );

		$this->assertTrue( $verify_request->invoke( $this->HelpScout, $data, $expected ), 'The secrets match; this should have worked' );
		$this->assertFalse( $verify_request->invoke( $this->HelpScout, 'asd', $expected ) );
		$this->assertFalse( $verify_request->invoke( $this->HelpScout, $data, 'asdasd' ) );
		$this->assertFalse( $verify_request->invoke( $this->HelpScout, $data, 1 ) );
		$this->assertFalse( $verify_request->invoke( $this->HelpScout, 1, $expected ) );

		// Let's break some things
		update_option( 'trustedlogin_vendor', array(
			'helpdesk' => array( 'helpscout' ),
			'helpscout_secret' => 'INVALID SECRET, OH NO!',
		));

		// After setting a secret, re-instantiate the class
		$this->HelpScout = new \TrustedLogin\Vendor\HelpScout();
		$verify_request = new ReflectionMethod( get_class( $this->HelpScout ), 'verify_request' );
		$verify_request->setAccessible( true );

		$this->assertFalse( $verify_request->invoke( $this->HelpScout, 1, $expected ) );
	}
}

