<?php

if ( ! defined('ABSPATH') ) {
    exit;
}
// Exit if accessed directly

class TrustedLogin_Audit_Log {

	use TL_Debug_Logging;

	/**
	 * Version of the Audit Log DB schema
	 */
	const db_version = '0.1.3';

	const db_table_name = 'tl_audit_log';

	/**
	* @var TrustedLogin_Settings
	* @since 0.9.0
	**/
	private $settings;

	public function __construct( TrustedLogin_Settings $settings_instance ) {

		$this->settings = $settings_instance;

		register_activation_hook( __FILE__, array( $this, 'init' ) );

		add_action( 'plugins_loaded', array( $this, 'maybe_update_schema' ) );

		add_action( 'trustedlogin_after_settings_form', array( $this, 'maybe_output_log' ), 10 );
	}

	protected function get_db_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::db_table_name;
	}

	/**
	 * Action:  Maybe update the audit log database table if the plugin was updated via WP-admin.
	 *
	 * @since 0.1.0
	 **/
	public function maybe_update_schema() {

		if ( version_compare( get_site_option( 'tl_db_version' ), self::db_version, '<' ) ) {
			$this->init();
		}
	}

	/**
	 * Activation Hook: Initialise a DB table to hold the TrustedLogin audit log.
	 *
	 * @since 0.1.0
	 **/
	public function init() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . $this->get_db_table_name() . " (
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

		add_option( 'tl_db_version', self::db_version );
	}

	public function maybe_output_log() {

		$audit_log_enabled = $this->settings->tls_settings_is_toggled( 'tls_output_audit_log' );

		if ( ! $audit_log_enabled ) {
			return;
		}

		$entries = $this->get_log_entries();

		/**
		 * @todo - fix this
		 **/
		echo '<h1 class="wp-heading-inline">Last Audit Log Entries</h1>';

		if ( 0 < count( $entries ) ) {
			echo $this->build_output( $entries );
		} else {

			echo __( "No Audit Log items to show yet.", 'tl-support-side' );
		}
	}

	/**
	 * @param $log_array
	 *
	 * @todo Convert to WP_Table_List
	 *
	 * @return string
	 */
	public function build_output( $log_array ) {
		$ret = '<div class="wrap">';

		$ret .= '<table class="wp-list-table widefat fixed striped posts">';
		$ret .= '<thead><tr>
        <th scope="col" id="audit-id" class="manage-column column-audit-id column-primary sortable desc"><a href="#"><span>ID</span><span class="sorting-indicator"></span></a></th>
        <th scope="col" id="user-id" class="manage-column column-user-id column-primary sortable desc"><a href="#"><span>User</span><span class="sorting-indicator"></span></a></th>
        <th scope="col" id="site-id" class="manage-column column-site-id column-primary sortable desc"><a href="#"><span>Site ID</span><span class="sorting-indicator"></span></a></th>
        <th scope="col" id="time" class="manage-column column-time column-primary sortable desc"><a href="#"><span>Time</span><span class="sorting-indicator"></span></a></th>
        <th scope="col" id="action" class="manage-column column-action column-primary sortable desc"><a href="#"><span>Action</span><span class="sorting-indicator"></span></a></th>
        <th scope="col" id="notes" class="manage-column column-notes column-primary sortable desc"><a href="#"><span>Notes</span><span class="sorting-indicator"></span></a></th>
        </tr></thead><tbody id="the-list">';

		foreach ( $log_array as $log_item ) {

			$log_user = get_user_by( 'id', $log_item->user_id );

			$ret .= '<tr>';
			$ret .= '<td>' . esc_html( $log_item->id ) . '</td>';
			$ret .= '<td>' . esc_html( $log_user->display_name ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->tl_site_id ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->time ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->action ) . '</td>';
			$ret .= '<td>' . esc_html( $log_item->notes ) . '</td>';
			$ret .= '</tr>';
		}

		$ret .= '</tbody>';

		// $ret .= '<tfoot>';
		// $ret .= '<tr class="subtotal"><td>Monthly Total</td><td id="scr-total">' . $sales_rows['total'] . '</td></tr>';
		// $ret .= '</tfoot>';

		$ret .= '</table>';

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Helper Function: Save as a row in the audit_db_table
	 *
	 * @since 0.1.0
	 *
	 * @param String $site_id - md5 hash identifier of the site being supported
	 * @param String $action - what action is being logged (eg 'requested','redirected')
	 * @param String $note - an optional string for adding context or extra info to the log
	 *
	 * @return boolean|null - True: saved; false: not saved, error; null: logged-out user (ID 0)
	 **/
	public function insert( $site_id, $action, $note = null ) {
		global $wpdb;

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
	 * Helper Function: Get recent audit entries
	 *
	 * @since 0.1.0
	 *
	 * @param Int $limit
	 *
	 * @return Array
	 **/
	public function get_log_entries( $limit = 10 ) {
		global $wpdb;

		// TODO: Add custom capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'unauthorized', 'You must have manage_options capability to view audit log entries' );
		}

		$query = "
            SELECT *
            FROM `" . $this->get_db_table_name() . "`
            ORDER BY `id` DESC
            LIMIT " . (int) $limit;

		return $wpdb->get_results( $query );
	}
}