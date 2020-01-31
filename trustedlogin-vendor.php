<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.8.0
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

define( 'TRUSTEDLOGIN_PLUGIN_VERSION', '0.8.0' );

require_once plugin_dir_path(__FILE__) . 'includes/trait-debug-logging.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-options.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-licensing.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-endpoint.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tl-api-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-audit-log.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-trustedlogin-encryption.php';

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
	 * @var TrustedLogin_Audit_Log
	 * @since 0.7.0
	 */
    public $audit_log;

	/**
	 * @var TrustedLogin_Endpoint
	 */
    private $endpoint;

    /**
    * @var TrustedLogin_Settings
    **/
    private $settings;

    public function __construct() {}

    public function setup() {
	    global $wpdb;

	    $this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;

        $this->settings = new TrustedLogin_Settings( $this->plugin_version );

        $this->endpoint = new TrustedLogin_Endpoint( $this->settings );

        $this->audit_log = new TrustedLogin_Audit_Log( $this->settings );

	    // Setup the Plugin Settings
        if ( is_admin() ){
            $this->settings->admin_init();
        }

	    add_action('plugins_loaded', array($this, 'init_helpdesk_integration'));
    }

    public function init_helpdesk_integration()
    {
    	
	    // Load all field files automatically
	    foreach ( glob( plugin_dir_path( __FILE__ ) . 'helpdesks/class-*.php' ) as $helpdesk ) {
		    include_once $helpdesk;
	    }
    }

}

$init_tl = new TrustedLogin_Support_Side();
$init_tl->setup();

register_deactivation_hook(__FILE__, 'trustedlogin_supportside_deactivate' );

function trustedlogin_supportside_deactivate()
{
    delete_option('tl_permalinks_flushed');
}
