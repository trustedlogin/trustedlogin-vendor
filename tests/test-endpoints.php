<?php
/**
 * Class AuditLogTest
 *
 * @package Tl_Support_Side
 */

/**
 * Tests for Audit Logging
 */
class EndpointsTest extends WP_UnitTestCase {

	/** @var TrustedLogin_Support_Side */
	private $TL;

	private $endpoint;


	/**
	 * AuditLogTest constructor.
	 */
	public function setUp() {
		$this->TL = new TrustedLogin\Vendor\Plugin();
		$this->TL->setup();

		$settings = new ReflectionProperty( $this->TL, 'settings' );
		$settings->setAccessible( true );
		$settings_value = $settings->getValue( $this->TL );

		$this->endpoint = new TrustedLogin\Vendor\Endpoint( $settings_value );
	}

	/**
	 * @covers Endpoint::register_endpoints
	 */
	function test_register_endpoints() {

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/trustedlogin/v1', $routes, 'route should exist when TL is setup' );
		$this->assertArrayHasKey( '/trustedlogin/v1/healthcheck', $routes, 'route should exist when TL is setup' );
		$this->assertArrayHasKey( '/trustedlogin/v1/public_key', $routes, 'route should exist when TL is setup' );
		$this->assertArrayHasKey( '/trustedlogin/v1/signature_key', $routes, 'route should exist when TL is setup' );
	}

	/**
	 * @covers Endpoint::public_key_callback
	 */
	function test_public_key_callback() {

		$request = new WP_REST_Request();

		$result = $this->endpoint->public_key_callback( $request );

		$this->assertTrue( $result instanceof WP_REST_Response );

		$this->assertEquals( 200, $result->status );

		$data = $result->get_data();

		$this->assertArrayHasKey( 'publicKey', $data );


		### What about when the key isn't fetchable?

		add_filter( 'trustedlogin/encryption/get-keys', function () {
			return new WP_Error( 'just_messin', 'Messing with expectations!' );
		});

		$error = $this->endpoint->public_key_callback( $request );

		$this->assertTrue( $error instanceof WP_REST_Response );

		// Error 501
		$this->assertEquals( 501, $error->status );

		$data = $error->get_data();

		// And no data
		$this->assertEmpty( $data );

		remove_all_filters( 'trustedlogin/encryption/get-keys' );
	}

	/**
	 * Modify WordPress's query internals as if a given URL has been requested.
	 *
	 * @see GVFuture_Test in GravityView tests
	 *
	 * @param string $url The URL for the request.
	 */
	function go_to( $url ) {
		// note: the WP and WP_Query classes like to silently fetch parameters
		// from all over the place (globals, GET, etc), which makes it tricky
		// to run them more than once without very carefully clearing everything
		$_GET = $_POST = array();
		foreach (array('query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow') as $v) {
			if ( isset( $GLOBALS[$v] ) ) unset( $GLOBALS[$v] );
		}
		$parts = parse_url($url);
		if (isset($parts['scheme'])) {
			$req = isset( $parts['path'] ) ? $parts['path'] : '';
			if (isset($parts['query'])) {
				$req .= '?' . $parts['query'];
				// parse the url query vars into $_GET
				parse_str($parts['query'], $_GET);
			}
		} else {
			$req = $url;
		}
		if ( ! isset( $parts['query'] ) ) {
			$parts['query'] = '';
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset($_SERVER['PATH_INFO']);

		self::flush_cache();

		unset($GLOBALS['wp_query'], $GLOBALS['wp_the_query']);
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

		$public_query_vars  = $GLOBALS['wp']->public_query_vars;
		$private_query_vars = $GLOBALS['wp']->private_query_vars;

		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->public_query_vars  = $public_query_vars;
		$GLOBALS['wp']->private_query_vars = $private_query_vars;

		_cleanup_query_vars();

		$GLOBALS['wp']->main($parts['query']);
	}

}
