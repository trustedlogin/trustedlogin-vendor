<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.6.0
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

define( 'TRUSTEDLOGIN_PLUGIN_VERSION', '0.6.0' );

require_once plugin_dir_path(__FILE__) . 'includes/trait-debug-logging.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-options.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-licensing.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-tl-api-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-audit-log.php';

class TrustedLogin_Support_Side
{

    use TL_Debug_Logging;
    use TL_Options;
    use TL_Licensing;

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

	/**
	 * @var TrustedLogin_Audit_Log
	 * @since 0.7.0
	 */
    public $audit_log;

    public function __construct() {}

    public function setup() {
	    global $wpdb;

	    $this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;

	    $this->endpoint = apply_filters('trustedlogin_redirect_endpoint', 'trustedlogin' );

	    $this->audit_log = new TrustedLogin_Audit_Log();

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

	    // Endpoint Hooks
	    add_action('init', array($this, 'endpoint_add'), 10);
	    add_action('template_redirect', array($this, 'endpoint_maybe_redirect'), 99);
	    add_filter('query_vars', array($this, 'endpoint_add_var'));

	    $this->debug_mode = $this->tls_settings_is_toggled('tls_debug_enabled');

	    add_action('plugins_loaded', array($this, 'init_helpdesk_integration'));

	    add_action('rest_api_init', array($this, 'tlapi_register_endpoints'));
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

        $this->audit_log->insert($site_id, 'requested');

        if ($tokens) {
            $key_store = (isset($tokens['name'])) ? sanitize_title($tokens['name']) : 'secret';
            $auth = (isset($tokens['readKey'])) ? $tokens['readKey'] : null;

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

        $success = ($envelope) ? __('Succcessful', 'tl-support-side') : __('Failed', 'tl-support-side');

        $this->audit_log->insert($site_id, 'received', $success);

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
            $this->audit_log->insert($identifier, 'redirected', __('Succcessful', 'tl-support-side'));
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

    public function init_helpdesk_integration()
    {


	    // Load all field files automatically
	    foreach ( glob( plugin_dir_path( __FILE__ ) . 'helpdesks/class-*.php' ) as $helpdesk ) {
		    include_once $helpdesk;
	    }
    }

    public function tlapi_register_endpoints()
    {

        $tl_api_endpoint = apply_filters('trustedlogin_api_endpoint','trustedlogin/v1');
        register_rest_route($tl_api_endpoint, '/verify', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'tlapi_verify_callback'),
            'args' => array(
                'key' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'type' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_title',
                    'validate_callback' => array($this,'tl_api_validate_type'),
                ),
                'siteurl' => array(
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ));

    }

    public function tlapi_verify_callback(WP_REST_Request $request){

        $key = $request->get_param('key');
        $type = $request->get_param('type');
        $siteurl = $request->get_param('siteurl');

        $check = $this->get_licenses_by('key',$key);

        $this->dlog("Check: ".print_r($check,true),__METHOD__);

        $response = new WP_REST_Response();
        if (!$check){
            $response->set_status(404);
        } else {
            $response->set_status(200);
        }

        return $response;

    }

    /**
     * Helper: Determines if eCommerce platform is acceptable
     *
     * @since 0.8.0
     * @param string $param - The parameter value being validated
     * @param WP_REST_Request $request
     * @param int $key
     * @return bool
     **/
    public function tl_api_validate_type($param, $request, $key){

        $types = apply_filters('trustedlogin_api_ecom_types',array('EDD','WooCommerce'));

        return in_array($param, $types);
    }

}

$init_tl = new TrustedLogin_Support_Side();
$init_tl->setup();

register_deactivation_hook(__FILE__, 'trustedlogin_supportside_deactivate' );

function trustedlogin_supportside_deactivate()
{
    delete_option('fl_permalinks_flushed');
}
