<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.5.0
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
require_once plugin_dir_path(__FILE__) . 'includes/trait-options.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-tl-api-handler.php';

class TrustedLogin_Support_Side
{

    use TL_Debug_Logging;
    use TL_Options;

    /**
     * @var String - the x.x.x value of the current plugin version.
     * @since 0.1.0
     **/
    private $plugin_version;

    /**
     * @var Boolean - whether or not to save a local text log
     * @see TL_Debug_logging trait
     * @since 0.1.0
     **/
    private $debug_mode;

    /**
     * @var String - the endpoint used to redirect Support Agents to Client WP admin panels
     * @since 0.3.0
     **/
    private $endpoint;

    /**
     * @var Array - the default settings for our plugin
     * @see TL_Options trait
     * @since 0.4.0
     **/
    private $default_options;

    /**
     * @var String - where the TrustedLogin settings should sit in menu.
     * @see TL_Options trait
     * @see Filter: trustedlogin_menu_location
     * @since 0.4.0
     **/
    private $menu_location;

    /**
     * @var Array - current site's TrustedLoging settings
     * @since 0.4.0
     **/
    private $options;

    public function __construct()
    {
        global $wpdb;

        $this->plugin_version = '0.5.0';

        define('TL_DB_VERSION', '0.1.3');

        $this->endpoint = apply_filters('trustedlogin_redirect_endpoint', 'trustedlogin');

        // Setup the Plugin Settings

        /**
         * Filter: Where in the menu the TrustedLogin Options should go.
         * Added to allow devs to move options item under 'Settings' menu item in wp-admin to keep things neat.
         *
         * @since 0.4.0
         * @param String either 'main' or 'submenu'
         **/
        $this->menu_location = apply_filters('trustedlogin_menu_location', 'main');

        $this->tls_settings_set_defaults();
        add_action('admin_menu', array($this, 'tls_settings_add_admin_menu'));
        add_action('admin_init', array($this, 'tls_settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'tls_settings_scripts'));

        // Setup the Audit Log DB
        $this->audit_db_table = $wpdb->prefix . 'tl_audit_log';
        register_activation_hook(__FILE__, array($this, 'audit_db_init'));
        add_action('plugins_loaded', array($this, 'audit_db_maybe_update'));
        add_action('trustedlogin_after_settings_form', array($this, 'audit_maybe_output'), 10);

        // Endpoint Hooks
        add_action('init', array($this, 'endpoint_add'), 10);
        add_action('template_redirect', array($this, 'endpoint_maybe_redirect'), 99);
        add_filter('query_vars', array($this, 'endpoint_add_var'));

        $this->debug_mode = $this->tls_settings_is_toggled('tls_debug_enabled');

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

        if (false == ($tokens = get_option('tl_tmp_tokens', false))) {
            $tokens = $this->api_get_tokens();
        }

        $this->audit_db_save($site_id, 'requested');

        /**
         * @todo - remove dummy testing data
         **/
        $tokens = array(
            'name' => 'Team Thunder',
            'status' => 'active',
            'publicKey' => '1234-56789', //used in client plugin
            'deleteToken' => '12345-1111', //vault token for delete site policy
            'writeToken' => '12345-1111', //vault token for write policy
        );

        if ($tokens) {
            $key_store = (isset($tokens['name'])) ? sanitize_title($tokens['name']) : 'secret';
            $auth = (isset($tokens['publicKey'])) ? $tokens['publicKey'] : null;

            $vault_attr = (object) array('type' => 'vault', 'auth' => $auth, 'debug_mode' => $this->debug_mode);
            $vault_api = new TL_API_Handler($vault_attr);

            /**
             * @var Array $envelope (
             *   String $siteurl
             *   String $identifier
             *   String $endpoint
             *   Int $expiry - the time() of when this Support User will decay
             * )
             **/
            $envelope = $vault_api->api_prepare($key_store . '/' . $site_id, null, 'GET');
        } else {
            $this->dlog("Error: Didn't recieve tokens.", __METHOD__);
            $envelope = false;
        }

        /**
         * @todo - remove dummy testing data
         **/

        $envelope = array(
            'siteurl' => 'http://localhost/tl-dev/',
            'identifier' => 'BWxhqNTdgxEpyNyYymOqQLitHRPXyE190DWPFibA6OwfiO85KUmjJOIOdOhvFfO4',
            'endpoint' => 'e5357dbb06bd0a1847da8ff0d926a6f8',
            'expiry' => time() + (7 * DAY_IN_SECONDS),
        );

        $success = ($envelope) ? __('Succcessful', 'tl-support-side') : __('Failed', 'tl-support-side');

        $this->audit_db_save($site_id, 'received', $success);

        return $envelope;

    }

    /**
     * API Helper: Get Token for encrypted storage from the TrustedLogin API
     *
     * @todo complete this
     **/
    public function api_get_tokens()
    {
        // Get Auth token from settings
        $auth = $this->tls_settings_get_value('tls_account_key');
        $account_id = $this->tls_settings_get_value('tls_account_id');

        if (empty($auth) || empty($account_id)) {
            $this->dlog("no auth or account_id provided", __METHOD__);
            return false;
        }

        $endpoint = 'accounts/' . $account_id;

        $saas_attr = (object) array('type' => 'saas', 'auth' => $auth, 'debug_mode' => $this->debug_mode);
        $saas_api = new TL_API_Handler($saas_attr);
        $data = null;

        $response = $saas_api->api_prepare($endpoint, $data, 'GET');

        if ($response) {
            if (isset($response->status) && 'active' == $response->status) {
                update_option('tl_tmp_tokens', (array) $response);
                return (array) $response;
            } else {
                $this->dlog("TrustedLogin Account not active", __METHOD__);
            }
        }

        /**
         * Expected Response from /v1/accounts/<account_id>:
         * "name":"Team Thunder",
         * "status": "active",
         *  "publicKey": "1234-56789", //used in client plugin
         *  "deleteToken: "12345-1111",//vault token for delete site policy
         *  "writeToken: "12345-1111",//vault token for write policy
         **/

        $this->dlog("Response: " . print_r($response, true), __METHOD__);

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

        if (is_object($envelope)) {
            $envelope = (array) $envelope;
        }

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
            ORDER BY id DESC
            LIMIT " . $limit;

        return $wpdb->get_results($query);
    }

    public function audit_db_build_output($log_array)
    {

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

        foreach ($log_array as $log_item) {
            $ret .= '<tr>';
            $ret .= '<td>' . $log_item->id . '</td>';
            $ret .= '<td>' . get_user_by('id', $log_item->user_id)->display_name . '</td>';
            $ret .= '<td>' . $log_item->tl_site_id . '</td>';
            $ret .= '<td>' . $log_item->time . '</td>';
            $ret .= '<td>' . $log_item->action . '</td>';
            $ret .= '<td>' . $log_item->notes . '</td>';
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

    public function audit_maybe_output()
    {

        if ($this->tls_settings_is_toggled('tls_output_audit_log')) {
            $log = $this->audit_db_fetch();

            /**
             * @todo - fix this
             **/
            echo '<h1 class="wp-heading-inline">Last Audit Log Entries</span></h1>';

            if (0 < count($log)) {
                echo $this->audit_db_build_output($log);
            } else {

                echo __("No Audit Log items to show yet.", 'tl-support-side');
            }
        }
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

        $this->dlog("sid: $site_id, action: $action, note: $note", __METHOD__);

        $user_id = get_current_user_id();

        if (0 == $user_id) {
            $this->dlog('Error: user_id = 0', __METHOD__);
            return;
        }

        $values = array(
            'time' => current_time('mysql'),
            'user_id' => $user_id,
            'tl_site_id' => sanitize_text_field($site_id),
            'notes' => sanitize_text_field($note),
            'action' => sanitize_text_field($action),
        );

        $this->dlog("Values: " . print_r($values, true), __METHOD__);

        $inserted = $wpdb->insert(
            $this->audit_db_table,
            $values
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
				  tl_site_id char(32) NOT NULL,
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

    /**
     * Hooked Action: Add a specified endpoint to WP when plugin is active
     *
     * @since 0.3.0
     **/
    public function endpoint_add()
    {

        if ($this->endpoint && !get_option('fl_permalinks_flushed')) {
            $endpoint_regex = '^' . $this->endpoint . '/([^/]+)/?$';
            $this->dlog("Endpoint Regex: $endpoint_regex", __METHOD__);
            add_rewrite_rule(
                // ^p/(d+)/?$
                $endpoint_regex,
                'index.php?' . $this->endpoint . '=$matches[1]',
                'top');
            $this->dlog("Endpoint " . $this->endpoint . " added.", __METHOD__);
            flush_rewrite_rules(false);
            $this->dlog("Rewrite rules flushed.", __METHOD__);
            update_option('fl_permalinks_flushed', 1);
        }
        return;
    }

    /**
     * Filter: Add our custom variable to endpoint queries to hold the identifier
     *
     * @since 0.3.0
     * @param Array $vars
     * @return Array
     **/
    public function endpoint_add_var($vars)
    {

        if ($this->endpoint) {
            $vars[] = $this->endpoint;

            $this->dlog("Endpoint var " . $this->endpoint . " added", __METHOD__);
        }

        return $vars;

    }

    /**
     * Hooked Action: Check if the endpoint is hit and has a valid identifier before automatically logging in support agent
     *
     * @since 0.3.0
     **/
    public function endpoint_maybe_redirect()
    {

        if ($this->endpoint) {
            $identifier = get_query_var($this->endpoint, false);

            if (!empty($identifier)) {
                $this->maybe_redirect_support($identifier);
            }
        }

    }

    /**
     * Helper: If all checks pass, redirect support agent to client site's admin panel
     *
     * @since 0.4.0
     * @param String $identifier collected via endpoint
     *   @see endpoint_maybe_redirect()
     * @return null
     **/
    public function maybe_redirect_support($identifier)
    {

        $this->dlog("Got here. ID: $identifier", __METHOD__);

        // first check if user can be redirected.
        if (!$this->auth_verify_user()) {
            $this->dlog("User cannot be redirected.", __METHOD__);
            return;
        }

        // then get the envelope
        $envelope = $this->api_get_envelope($identifier);

        $url = ($envelope) ? $this->envelope_to_url($envelope) : false;

        if ($url) {
            // then redirect
            $this->audit_db_save($identifier, 'redirected', __('Succcessful', 'tl-support-side'));
            wp_redirect($url, 302);
            exit;
        }
    }

    /**
     * Helper: Check if the current user can be redirected to the client site
     *
     * @since 0.4.0
     * @return Boolean
     **/
    public function auth_verify_user()
    {

        if (!is_user_logged_in()) {
            return false;
        }

        $_usr = get_userdata(get_current_user_id());
        $user_roles = $_usr->roles;

        if (!is_array($user_roles)) {
            return false;
        }

        $required_roles = $this->tls_settings_get_approved_roles();

        $intersect = array_intersect($required_roles, $user_roles);

        if (0 < count($intersect)) {
            return true;
        }

        return false;
    }

}

$init_tl = new TrustedLogin_Support_Side();
