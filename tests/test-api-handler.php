<?php
/**
 * Class APIHandlerTest
 *
 * @package Tl_Support_Side
 */

/**
 * Tests for Audit Logging
 */
class APIHandlerTest extends WP_UnitTestCase {

	/** @var TrustedLogin_Support_Side */
	private $TL;

	/**
	 * APIHandlerTest constructor.
	 */
	public function __construct() {
		$this->TL = new TrustedLogin_Support_Side;
		$this->TL->setup();
	}

	/**
	 * @covers API_Handler::__construct
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
