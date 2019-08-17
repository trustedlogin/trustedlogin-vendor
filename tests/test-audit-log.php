<?php
/**
 * Class AuditLogTest
 *
 * @package Tl_Support_Side
 */

/**
 * Tests for Audit Logging
 */
class AuditLogTest extends WP_UnitTestCase {

	/** @var TrustedLogin_Support_Side */
	private $TL;

	private $user_factory;

	private $users = array();

	/**
	 * AuditLogTest constructor.
	 */
	public function __construct() {
		$this->TL = new TrustedLogin_Support_Side;
		$this->TL->setup();

		$this->user_factory = new WP_UnitTest_Factory_For_User();

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

		// Only users with manage_options should be able to see logs (for now)
		wp_set_current_user( 0 );
		$this->assertWPError( $this->TL->audit_log->get_log_entries(), 'Request should fail when user is not logged-in' );
		$this->assertEquals( $this->TL->audit_log->get_log_entries()->get_error_code(), 'unauthorized', 'Request should fail when user is not logged-in' );

		wp_set_current_user( $this->users['subscriber']->ID );
		$this->assertWPError( $this->TL->audit_log->get_log_entries(), 'Request should fail when user does not have manage_options' );
		$this->assertEquals( $this->TL->audit_log->get_log_entries()->get_error_code(), 'unauthorized', 'Request should fail when user does not have manage_options' );

		wp_set_current_user( $this->users['admin']->ID );

		$current_user = wp_get_current_user();
		$this->assertTrue( $current_user->has_cap( 'manage_options' ) );

		$this->assertEquals( array(), $this->TL->audit_log->get_log_entries(), 'The log should be empty before adding things…' );

		$microtime = microtime();
		$this->TL->audit_log->insert( $microtime, 'added', 'This is a note' );

		$log_entries = $this->TL->audit_log->get_log_entries();
		$this->assertCount( 1, $log_entries, 'There should only be one item in the log' );

		$this->assertEquals( $microtime, $log_entries[0]->tl_site_id, 'The note did not contain the expected site ID passed by $microtime' );

		$i = 0;
		while( $i < 20 ) {
			$i++;
			$this->TL->audit_log->insert( $microtime, 'added', 'This is a note' );
		}

		$this->assertCount( 10, $this->TL->audit_log->get_log_entries(), 'The limit failed' );

		$this->assertCount( 20, $this->TL->audit_log->get_log_entries( 20 ), 'The limit failed' );

		$this->assertCount( 13, $this->TL->audit_log->get_log_entries( 13 ), 'The limit failed' );
	}

	/**
	 * @covers TrustedLogin_Audit_Log::insert
	 */
	function test_insert() {

		wp_set_current_user( 0 );
		$this->assertNull( $this->TL->audit_log->insert( '12', 'added', 'This is a note' ), 'Insert should fail when user is not logged-in' );

		wp_set_current_user( $this->users['admin']->ID );

		$this->assertTrue( $this->users['admin']->has_cap( 'manage_options' ) );

		$this->assertTrue( $this->TL->audit_log->insert( '12', 'added', 'This is a note' ) );
	}
}