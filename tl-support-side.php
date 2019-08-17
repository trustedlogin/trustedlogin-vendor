<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.7.0
 * Author: trustedlogin.com
 * Author URI: https://trustedlogin.com
 * Text Domain: tl-support-side
 *
 * Copyright: © 2019 TrustedLogin
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

define( 'TRUSTEDLOGIN_PLUGIN_VERSION', '0.7.0' );

require_once plugin_dir_path(__FILE__) . 'includes/trait-debug-logging.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-options.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-licensing.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-endpoint.php';
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

	/**
	 * @var TrustedLogin_Endpoint
	 */
    private $endpoint;

    public function __construct() {}

    public function setup() {
	    global $wpdb;

	    $this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;

	    $this->audit_log = new TrustedLogin_Audit_Log();

	    $this->endpoint = new TrustedLogin_Endpoint();

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

	    $this->debug_mode = $this->tls_settings_is_toggled('tls_debug_enabled');

	    add_action('plugins_loaded', array($this, 'init_helpdesk_integration'));
    }

    public function load_helpdesks() {

    	include_once plugin_dir_path( __FILE__ ) . 'helpdesks/abstract-tl-helpdesk.php';

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
    delete_option('tl_permalinks_flushed');
}
