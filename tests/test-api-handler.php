<?php
/**
 * Class APIHandlerTest
 *
 * @package Tl_Support_Side
 */

use TrustedLogin\Vendor\API_Handler;

/**
 * Tests for Audit Logging
 */
class APIHandlerTest extends WP_UnitTestCase {

	/** @var TrustedLogin\Vendor\Plugin() */
	private $TL;

	/**
	 * APIHandlerTest constructor.
	 */
	public function setUp() {
		$this->TL = new TrustedLogin\Vendor\Plugin();
		$this->TL->setup();
	}

	/**
	 * @covers API_Handler::__construct
	 * @covers API_Handler::get_api_url
	 * @covers API_Handler::get_auth_header_type
	 */
	public function test_constuct() {

		$saas_api_handler = new API_Handler( 'type=saas' );

		$this->assertEquals( 'https://app.trustedlogin.com/api/', $saas_api_handler->get_api_url() );

		add_filter( 'trustedlogin/api-url/saas', $filter_saas_url = function() { return 'https://www.duck.com'; } );
		$this->assertEquals( 'https://www.duck.com', $saas_api_handler->get_api_url() );
		remove_filter( 'trustedlogin/api-url/saas', $filter_saas_url );

		$this->assertEquals( 'Authorization', $saas_api_handler->get_auth_header_type() );

	}
}
