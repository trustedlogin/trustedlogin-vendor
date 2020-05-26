<?php
/**
 * Class AuditLogTest
 *
 * @package Tl_Support_Side
 */

use TrustedLogin\Vendor\Plugin;
use TrustedLogin\Vendor\Endpoint;
use TrustedLogin\Vendor\TrustedLogin_Audit_Log;

/**
 * Tests for Audit Logging
 */
class AuditLogTest extends WP_UnitTestCase {

	/** @var TrustedLogin_Support_Side */
	private $TL;

	/** @var Endpoint */
	private $Endpoint;

	/** @var TrustedLogin_Audit_Log */
	private $audit_log;

	private $user_factory;

	private $users = array();

	/**
	 * AuditLogTest constructor.
	 */
	public function setUp() {
		$this->TL = new Plugin();
		$this->TL->setup();

		$settings = new ReflectionProperty( $this->TL, 'settings' );
		$settings->setAccessible( true );
		$settings_value = $settings->getValue( $this->TL );

		$this->Endpoint = new Endpoint( $settings_value );

		$audit_log = new ReflectionProperty( $this->Endpoint, 'audit_log' );
		$audit_log->setAccessible( true );
		$audit_log_value = $audit_log->getValue( $this->Endpoint );

		$this->audit_log = $audit_log_value;

		$this->user_factory = new WP_UnitTest_Factory_For_User();

		$this->setup_users();

		parent::setUp();
	}

	/**
	 * Clear the audit logs after each test
	 */
	public function tearDown() {
		global $wpdb;

		$sql = 'TRUNCATE TABLE ' . $this->audit_log->get_db_table_name();

		$wpdb->query( $sql );

		parent::tearDown();
	}

	function setup_users() {
		$admin = $this->user_factory->create( array(
				'user_login' => md5( microtime() ),
				'user_email' => md5( microtime() ) . '@trustedlogin.tests',
				'role' => 'administrator' )
		);

		$editor = $this->user_factory->create( array(
				'user_login' => md5( microtime() ),
				'user_email' => md5( microtime() ) . '@trustedlogin.tests',
				'role' => 'editor' )
		);

		$subscriber = $this->user_factory->create( array(
				'user_login' => md5( microtime() ),
				'user_email' => md5( microtime() ) . '@trustedlogin.tests',
				'role' => 'subscriber' )
		);

		$this->users = array(
			'admin' => get_user_by( 'id', $admin ),
			'editor' => get_user_by( 'id', $editor ),
			'subscriber' => get_user_by( 'id', $subscriber ),
		);
	}

	/**
	 * @covers TrustedLogin_Audit_Log::get_log_entries
	 * @covers TrustedLogin_Audit_Log::insert
	 */
	function test_get_log_entries() {

		$this->setup_users();

		// Only users with manage_options should be able to see logs (for now)
		wp_set_current_user( 0 );
		$this->assertWPError( $this->audit_log->get_log_entries(), 'Request should fail when user is not logged-in' );
		$this->assertEquals( $this->audit_log->get_log_entries()->get_error_code(), 'unauthorized', 'Request should fail when user is not logged-in' );

		wp_set_current_user( $this->users['subscriber']->ID );
		$this->assertWPError( $this->audit_log->get_log_entries(), 'Request should fail when user does not have manage_options' );
		$this->assertEquals( $this->audit_log->get_log_entries()->get_error_code(), 'unauthorized', 'Request should fail when user does not have manage_options' );

		wp_set_current_user( $this->users['admin']->ID );

		$current_user = wp_get_current_user();
		$this->assertTrue( $current_user->has_cap( 'manage_options' ) );

		$this->assertEquals( array(), $this->audit_log->get_log_entries(), 'The log should be empty before adding things…' );

		$microtime = microtime();
		$this->audit_log->insert( $microtime, 'added', 'This is a note' );

		$log_entries = $this->audit_log->get_log_entries();
		$this->assertCount( 1, $log_entries, 'There should only be one item in the log' );

		$this->assertEquals( $microtime, $log_entries[0]->tl_site_id, 'The note did not contain the expected site ID passed by $microtime' );

		$i = 0;
		while( $i < 50 ) {
			$i++;
			$this->audit_log->insert( $microtime, 'added', 'This is a note' );
		}

		$this->assertCount( 25, $this->audit_log->get_log_entries(), 'The limit failed' );

		$this->assertCount( 20, $this->audit_log->get_log_entries( 20 ), 'The limit failed' );

		$this->assertCount( 13, $this->audit_log->get_log_entries( 13 ), 'The limit failed' );
	}

	/**
	 * @covers TrustedLogin_Audit_Log::insert
	 */
	function test_insert() {

		$this->setup_users();

		wp_set_current_user( 0 );
		$this->assertNull( $this->audit_log->insert( '12', 'added', 'This is a note' ), 'Insert should fail when user is not logged-in' );

		wp_set_current_user( $this->users['admin']->ID );

		$this->assertTrue( $this->users['admin']->has_cap( 'manage_options' ) );

		$this->assertTrue( $this->audit_log->insert( '12', 'added', 'This is a note' ) );
	}
}
