<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.2.0
 * Author: trustedlogin.com
 * Author URI: https://trustedlogin.com
 * Text Domain: tl-support-side
 *
 * Copyright: © 2019 trustedlogin
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'includes/trait-debug-logging.php';

class TrustedLogin_Support_Side
{

    use TL_Debug_Logging;

    private $debug_mode;

    public function __construct()
    {
        global $wpdb;

        /**
         * @todo move debug_mode to plugin options
         **/
        $this->debug_mode = true;

        define('TL_DB_VERSION', '0.1.2');

        // Setup the Audit Log DB
        $this->audit_db_table = $wpdb->prefix . 'tl_audit_log';
        register_activation_hook(__FILE__, array($this, 'audit_db_init'));
        add_action('plugins_loaded', array($this, 'audit_db_maybe_update'));

    }

    /**
     * API Wrapper: Get the envelope for a specified site ID
     *
     * @since 0.2.0
     * @param String $site_id - unique identifier of a site
     * @return Array|false
     **/
    public function api_get_envelope($site_id)
    {
        if (empty($site_id)) {
            $this->dlog('Error: site_id cannot be empty.', __METHOD__);
            return false;
        }

        /**
         * @todo ping TL using the API key provided, to return $store_token
         * @todo use $store_token to get envelope from Vault
         **/

        $token = $this->api_get_token();

        $envelope = $this->api_send(TF_VAULT_URL, null, 'GET', $token);

        $success = ($envelope) ? 'Succcessful' : 'Failed';

        $this->audit_db_save($site_id, 'requested', $success);
    }

    /**
     * API Helper: Get Token for encrypted storage from the TrustedLogin API
     *
     * @todo complete this
     **/
    public function api_get_token()
    {
        // Get Auth token from settings
        $auth = '';

        $response = $this->api_send(TF_API_URL, $data, 'POST', $auth);

        return false;

    }

    /**
     * API Helper: Send $data to $url, using $method and $auth.
     *
     * @todo complete this
     * @param String $url
     * @param Array $data
     * @param String $method ('POST','GET','DELETE')
     * @param String $auth - API key
     * @return response
     **/
    public function api_send($url, $data, $method, $auth)
    {
        $this->dlog("url: $url | data: " . print_r($data, true) . " method: $method ");
        return false;
    }

    /**
     * Helper function: Extract redirect url from encrypted envelope.
     *
     * @since 0.1.0
     * @param Array $envelope - received from encrypted TrustedLogin storage
     * @return String|false
     **/
    public function envelope_to_url($envelope)
    {
        if (!is_array($envelope)) {
            $this->dlog('Error: envelope not an array. e:' . print_r($envelope, true), __METHOD__);
            return false;
        }

        if (!array_key_exists('identifier', $envelope)
            || !array_key_exists('siteurl', $envelope)
            || !array_key_exists('endpoint', $envelope)) {
            $this->dlog('Error: malformed envelope. e:' . print_r($envelope, true), __METHOD__);
            return false;
        }

        $url = $envelope['siteurl'] . '/' . $envelope['endpoint'] . '/' . $envelope['identifier'];

        return $url;

    }

    /**
     * Helper Function: Get recent audit entries
     *
     * @since 0.1.0
     * @param Int $limit
     * @return Array
     **/
    public function audit_db_fetch($limit = 10)
    {
        global $wpdb;

        $query = "
            SELECT *
            FROM " . $this->audit_db_table . "
            LIMIT " . $limit;

        return $wpdb->get_results($query);
    }

    /**
     * Helper Function: Save as a row in the audit_db_table
     *
     * @since 0.1.0
     * @param String $site_id - md5 hash identifier of the site being supported
     * @param String $action - what action is being logged (eg 'requested','redirected')
     * @param String $note - an optional string for adding context or extra info to the log
     * @return Boolean - if this was saved to audit_db_table
     **/
    public function audit_db_save($site_id, $action, $note = null)
    {
        global $wpdb;

        $user_id = get_current_user_id();

        if (0 == $user_id) {
            $this->dlog('Error: user_id = 0', __METHOD__);
            return;
        }

        $inserted = $wpdb->insert(
            $this->audit_db_table,
            array(
                'time' => current_time('mysql'),
                'user_id' => $user_id,
                'site_id' => sanitize_text_field($site_id),
                'notes' => sanitize_text_field($note),
                'action' => sanitize_text_field($action),
            )
        );

        if (!$inserted) {
            $this->dlog('Error: Could not save this to audit log. u:' . $user_id . ' | s:' . $site_id . ' | a:' . $action, __METHOD__);
            return false;
        } else {
            return true;
        }

    }

    /**
     * Activation Hook: Initialise a DB table to hold the TrustedLogin audit log.
     *
     * @since 0.1.0
     **/
    public function audit_db_init()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . $this->audit_db_table . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  user_id bigint(20) NOT NULL,
				  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				  site_id char(32) NOT NULL,
				  action varchar(55) NOT NULL,
				  notes text NULL,
				  PRIMARY KEY  (id)
				) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        add_option('tl_db_version', TL_DB_VERSION);
    }

    /**
     * Action:  Maybe update the audit log database table if the plugin was updated via WP-admin.
     *
     * @since 0.1.0
     **/
    public function audit_db_maybe_update()
    {

        if (get_site_option('tl_db_version') != TL_DB_VERSION) {
            $this->audit_db_init();
        }
    }

}

$init_tl = new TrustedLogin_Support_Side();
