<?php
/**
 * Audit Log functionality
 *
 * @package TrustedLogin\Vendor
 */

namespace TrustedLogin\Vendor;

use \WP_Error;

// Exit if accessed directly!
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustedLogin_Audit_Log
 *
 * @package TrustedLogin\Vendor
 */
class TrustedLogin_Audit_Log {

	use Debug_Logging;

	/**
	 * Version of the Audit Log DB schema
	 */
	const DB_VERSION = '0.1.3';

	const DB_TABLE_NAME = 'tl_audit_log';

	/**
	 * The settings for the Vendor plugin, which include whether to enable logging
	 *
	 * @var Settings
	 * @since 0.9.0
	 */
	private $settings;

	/**
	 * TrustedLogin_Audit_Log constructor.
	 *
	 * @param Settings $settings_instance Settings for the help desk.
	 */
	public function __construct( Settings $settings_instance ) {

		$this->settings = $settings_instance;

		register_activation_hook( __FILE__, array( $this, 'init' ) );

		// Priority should be greater than 10 (needed for unit tests).
		add_action( 'plugins_loaded', array( $this, 'maybe_update_schema' ), 11 );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Adds a submenu page when the Activity Log setting is enabled
	 *
	 * @return void
	 */
	public function add_admin_menu() {

		if( ! $this->settings->exists() ) {
			return;
		}

		$audit_log_enabled = $this->settings->setting_is_toggled( 'enable_audit_log' );

		if ( ! $audit_log_enabled ) {
			return;
		}

		add_submenu_page(
			'trustedlogin_vendor',
			__( 'Activity Log', 'trustedlogin-vendor' ),
			__( 'Activity Log', 'trustedlogin-vendor' ),
			'manage_options', // TODO: Custom capabilities!
			'trustedlogin_activity_log',
			array( $this, 'output_log' )
		);
	}

	/**
	 * Returns the wp-prefixed table name for the audit log
	 *
	 * @return string
	 */
	public function get_db_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::DB_TABLE_NAME;
	}

	/**
	 * Action:  Maybe update the audit log database table if the plugin was updated via WP-admin.
	 *
	 * @since 0.1.0
	 */
	public function maybe_update_schema() {

		if ( version_compare( get_site_option( 'tl_db_version' ), self::DB_VERSION, '<' ) ) {
			$this->init();
		}

		if ( defined( 'DOING_TL_VENDOR_TESTS' ) && DOING_TL_VENDOR_TESTS ) {
			$this->init();
		}
	}

	/**
	 * Activation Hook: Initialise a DB table to hold the TrustedLogin audit log.
	 *
	 * @since 0.1.0
	 */
	public function init() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = 'CREATE TABLE ' . $this->get_db_table_name() . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  user_id bigint(20) NOT NULL,
				  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				  tl_site_id char(32) NOT NULL,
				  action varchar(55) NOT NULL,
				  notes text NULL,
				  PRIMARY KEY  (id)
				) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'tl_db_version', self::DB_VERSION );
	}

	/**
	 * Renders HTML output of audit log entries, if enabled
	 *
	 * @uses TrustedLogin_Audit_Log::build_output()
	 *
	 * @return void
	 */
	public function output_log() {

		printf( '<h1 class="wp-heading-inline">%s</h1>', esc_html__( 'Latest Activity', 'trustedlogin-vendor' ) );

		$entries = $this->get_log_entries();

		if ( count( $entries ) ) {
			echo '<h2>' . esc_html__( 'Each row represents a user attempting to log into a customer site.', 'trustedlogin-vendor' ) . '</h2>';
			echo $this->build_output( $entries );
		} else {
			echo '<h2>' . esc_html__( 'No activity yet!', 'trustedlogin-vendor' ) . '</h2>';
		}
	}

	/**
	 * Generates audit log HTML output
	 *
	 * @todo Convert to WP_Table_List
	 *
	 * @param \stdClass[] $log_array Array of audit log items to render with id, display_name, tl_site_id, time, action, notes.
	 *
	 * @return string
	 */
	public function build_output( $log_array ) {
		$ret = '<div class="wrap">';

		$ret .= '<table class="wp-list-table widefat fixed striped posts">';
		$ret .= '<thead>
		<tr>
	        <th scope="col" id="time" class="column-time">' . esc_html__( 'Time', 'trustedlogin-vendor' ) . '</th>
	        <th scope="col" id="user-id" class="column-user-id">' . esc_html__( 'User', 'trustedlogin-vendor' ) . '</th>
	        <th scope="col" id="site-id" class="column-site-id">' . esc_html__( 'Site ID & Vault Secret ID', 'trustedlogin-vendor' ) . '</th>
	        <th scope="col" id="action" class="column-action">' . esc_html__( 'Action', 'trustedlogin-vendor' ) . '</th>
	        <th scope="col" id="notes" class="column-notes">' . esc_html__( 'Notes', 'trustedlogin-vendor' ) . '</th>
        </tr>
        </thead>';
        $ret .= '<tbody id="the-list">';

		foreach ( $log_array as $log_item ) {

			$log_user = get_user_by( 'id', $log_item->user_id );

			$ret .= '<tr>';
			$ret .= '<th scope="row">' . esc_html( $log_item->time ) . '</th>';
			$ret .= '<td>' . esc_html( $log_user->display_name ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->tl_site_id ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->action ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->notes ) . '</td>';
			$ret .= '</tr>';
		}

		$ret .= '</tbody>';
		$ret .= '</table>';
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Save a row in the audit_db_table if audit logging is enabled
	 *
	 * @since 0.1.0
	 *
	 * @param string $site_id md5 hash identifier of the site being supported.
	 * @param string $action The action being logged (eg 'requested','redirected').
	 * @param string $note Optional string for adding context or extra info to the log.
	 *
	 * @return boolean|null True: saved; false: not saved, error; null: logged-out user (ID 0)
	 */
	public function insert( $site_id, $action, $note = null ) {
		global $wpdb;

		$enabled = $this->settings->setting_is_toggled( 'enable_audit_log' );

		if ( ! $enabled ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( empty( $user_id ) ) {
			$this->dlog( 'Error: user_id = 0', __METHOD__ );

			return null;
		}

		$values = array(
			'time'       => current_time( 'mysql' ),
			'user_id'    => (int) $user_id,
			'tl_site_id' => sanitize_text_field( $site_id ),
			'notes'      => sanitize_text_field( $note ),
			'action'     => sanitize_text_field( $action ),
		);

		$inserted = $wpdb->insert(
			$this->get_db_table_name(),
			$values
		);

		if ( ! $inserted ) {
			$this->dlog( 'Error: Could not save this to audit log. u:' . $user_id . ' | s:' . $site_id . ' | a:' . $action, __METHOD__ );

			return false;
		}

		return true;
	}

	/**
	 * Get recent audit entries
	 *
	 * @since 0.1.0
	 *
	 * @param int $limit Number of log entries to retrieve.
	 *
	 * @return array
	 */
	public function get_log_entries( $limit = 25 ) {
		global $wpdb;

		// TODO: Add custom capabilities!
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'unauthorized', 'You must have manage_options capability to view audit log entries' );
		}

		$query = $wpdb->prepare( 'SELECT * FROM `' . $this->get_db_table_name() . '` ORDER BY `id` DESC LIMIT %d', $limit );

		return $wpdb->get_results( $query );
	}
}
