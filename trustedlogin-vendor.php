<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.9.0
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

define( 'TRUSTEDLOGIN_PLUGIN_VERSION', '0.9.0-refactor' );

/** @define "$path" "./" */
$path = plugin_dir_path(__FILE__);

require_once $path . 'includes/trait-debug-logging.php';
require_once $path . 'includes/trait-licensing.php';

require_once $path . 'includes/class-trustedlogin-settings.php';
require_once $path . 'includes/class-trustedlogin-endpoint.php';
require_once $path . 'includes/class-tl-api-handler.php';
require_once $path . 'includes/class-trustedlogin-audit-log.php';
require_once $path . 'includes/class-trustedlogin-encryption.php';

class TrustedLogin_Support_Side {

	use TrustedLogin\Vendor\Debug_Logging;
	use \TrustedLogin\Vendor\Licensing;

	/**
	 * @since 0.1.0
	 * @var String - the x.x.x value of the current plugin version.
	 */
	private $plugin_version;

	/**
	 * @var TrustedLogin\Vendor\Endpoint
	 */
	private $endpoint;

	/**
	 * @var \TrustedLogin\Vendor\Settings
	 */
	private $settings;

	public function __construct() {}

	public function setup() {
		global $wpdb;

		$this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;

		$this->settings = new TrustedLogin\Vendor\Settings();

		$this->endpoint = new TrustedLogin\Vendor\Endpoint( $this->settings );

		// Setup the Plugin Settings
		if ( is_admin() ) {
			$this->settings->admin_init();
		}

		add_action( 'plugins_loaded', array( $this, 'init_helpdesk_integration' ) );
	}

	public function init_helpdesk_integration() {

		include_once plugin_dir_path( __FILE__ ) . 'helpdesks/abstract-tl-helpdesk.php';

		// Load all field files automatically
		foreach ( glob( plugin_dir_path( __FILE__ ) . 'helpdesks/class-*.php' ) as $helpdesk ) {
			include_once $helpdesk;
		}
	}

}

$init_tl = new TrustedLogin_Support_Side();
$init_tl->setup();

register_deactivation_hook(__FILE__, 'trustedlogin_supportside_deactivate' );

function trustedlogin_supportside_deactivate() {
    delete_option('tl_permalinks_flushed');
}
