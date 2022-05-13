<?php
/**
 * Plugin Name: TrustedLogin Support Plugin
 * Plugin URI: https://www.trustedlogin.com
 * Description: Authenticate support team members to securely log them in to client sites via TrustedLogin
 * Version: 0.10.0
 * Requires PHP: 5.4
 * Author: Katz Web Services, Inc.
 * Author URI: https://www.trustedlogin.com
 * Text Domain: trustedlogin-vendor
 * License: GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Copyright: © 2020 Katz Web Services, Inc.
 */
namespace TrustedLogin\Vendor;
if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

define( 'TRUSTEDLOGIN_PLUGIN_VERSION', '0.10.0' );
define( 'TRUSTEDLOGIN_PLUGIN_FILE', __FILE__ );
if( ! defined( 'TRUSTEDLOGIN_API_URL')){
	define( 'TRUSTEDLOGIN_API_URL', 'https://app.trustedlogin.com/api/v1/' );
}


/** @define "$path" "./" */
$path = plugin_dir_path(__FILE__);

require_once $path . 'includes/trait-debug-logging.php';
require_once $path . 'includes/trait-licensing.php';
require_once $path . 'includes/class-trustedlogin-team-settings.php';
require_once $path . 'includes/class-trustedlogin-settings.php';
require_once $path . 'includes/class-trustedlogin-settings-api.php';
require_once $path . 'admin/settings/init.php';
require_once $path . 'includes/class-trustedlogin-sitekey-login.php';
require_once $path . 'includes/class-trustedlogin-endpoint.php';
require_once $path . 'includes/class-tl-api-handler.php';
require_once $path . 'includes/class-trustedlogin-audit-log.php';
require_once $path . 'includes/class-trustedlogin-encryption.php';

require_once $path . 'includes/class-trustedlogin-healthcheck.php';

require_once $path . 'vendor/autoload.php';

class Plugin {

	use \TrustedLogin\Vendor\Debug_Logging;
	use \TrustedLogin\Vendor\Licensing;

	/**
	 * @since 0.1.0
	 * @var String - the x.x.x value of the current plugin version.
	 */
	private $plugin_version;

	/**
	 * @var \TrustedLogin\Vendor\Endpoint
	 */
	private $endpoint;

	/**
	 * @var \TrustedLogin\Vendor\Settings
	 */
	private $settings;

	public function __construct() {
		$this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;
	}

	/**
	 * @return void
	 */
	public function setup() {
		global $wpdb;

		/*
		 * Filter allows site admins to override SSL check on dev/testing servers.
		 * This should NEVER be used on production environments.
		 */
		if ( ! is_ssl() && ! defined( 'DOING_TL_VENDOR_TESTS') ) {

			// If SSL not enabled, show alert and don't load the plugin.
			add_action( 'admin_notices', array( $this, 'ssl_admin_notice' ) );

			return;
		}

		$this->settings = new Settings();

		$this->endpoint = new Endpoint( $this->settings );

		$this->load_helpdesks();

		new SiteKey_Login( $this->settings );

		new HealthCheck();
	}

	/*
	 * Alerts the user that this TrustedLogin plugin can only run on sites with SSL enabled.
	 *
	 * @since 0.9.1
	 */
	public function ssl_admin_notice() {
	    $class = 'notice notice-error';
	    $message = __( 'The TrustedLogin plugin is NOT enabled. SSL is required to securely interact with TrustedLogin servers.', 'trustedlogin-vendor' );

	    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	public function load_helpdesks() {

		include_once plugin_dir_path( __FILE__ ) . 'helpdesks/abstract-tl-helpdesk.php';

		// Load all field files automatically
		foreach ( glob( plugin_dir_path( __FILE__ ) . 'helpdesks/class-*.php' ) as $helpdesk ) {
			include_once $helpdesk;
		}
	}

}

add_action( 'plugins_loaded', function() {
	$init_tl = new Plugin();
	$init_tl->setup();
} );

register_deactivation_hook( __FILE__, 'trustedlogin_vendor_deactivate' );

function trustedlogin_vendor_deactivate() {
	delete_option( 'tl_permalinks_flushed' );
	delete_option( 'trustedlogin_vendor_config' );
}
