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
	 * @covers TL_API_Handler::__construct
	 */
	public function test_constuct() {

		$saas_api_handler = new TL_API_Handler( 'type=saas' );

		$this->assertEquals( 'https://app.trustedlogin.com/api/' . $saas_api_handler::saas_api_version . '/', $saas_api_handler->get_api_url() );

		$this->assertEquals( 'Authorization', $saas_api_handler->get_auth_header_type() );

		$vault_api_handler = new TL_API_Handler( 'type=vault' );

		$this->assertEquals( 'https://vault.trustedlogin.com/' . $vault_api_handler::vault_api_version . '/', $vault_api_handler->get_api_url() );

		$this->assertEquals( 'X-Vault-Token', $vault_api_handler->get_auth_header_type() );

	}
}
