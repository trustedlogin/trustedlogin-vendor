<?php
/**
 * Login by Site Key page
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
class SiteKey_Login {

	use Debug_Logging;

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

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Adds a submenu page when the user is authorized to log in to customer sites
	 *
	 * @return void
	 */
	public function add_admin_menu() {

		$endpoint = new Endpoint( $this->settings );

		if( ! $endpoint->auth_verify_user() ) {
			return;
		}

		add_submenu_page(
			'trustedlogin_vendor',
			__( 'TrustedLogin with Site Key', 'trustedlogin-vendor' ),
			__( 'Log In with Site Key', 'trustedlogin-vendor' ),
			'manage_options', // TODO: Custom capabilities!
			'trustedlogin_log',
			array( $this, 'accesskey_page' )
		);
	}

	/**
	 * Settings page output for logging into a customer's site via an AccessKey
	 *
	 * @since 1.0.0
	 */
	public function accesskey_page(){

		$endpoint = new Endpoint( $this->settings );

		if ( ! $endpoint->auth_verify_user() ){
			return;
		}

		wp_enqueue_style( 'trustedlogin-settings' );

		$output = sprintf(
			'<div class="trustedlogin-dialog accesskey">
				  <img src="%1$s" width="400" alt="TrustedLogin">
				  <form method="GET">
					  <input type="text" name="ak" id="trustedlogin-access-key" placeholder="%2$s" />
					  <button type="submit" id="trustedlogin-go" class="button button-large trustedlogin-proceed">%3$s</button>
					  <input type="hidden" name="action" value="ak-redirect" />
					  <input type="hidden" name="page" value="%4$s" />
				  </form>
				</div>',
			/* %1$s */ plugins_url( 'assets/trustedlogin-logo.png', TRUSTEDLOGIN_PLUGIN_FILE ),
			/* %2$s */ esc_html__('Paste key received from customer', 'trustedlogin-vendor'),
			/* %3$s */ esc_html__('Login to Site', 'trustedlogin-vendor'),
			/* $4$s */ esc_attr( \sanitize_title( $_GET['page'] ) )
		);

		echo $output;
	}

	public function maybe_handle_accesskey() {

		if ( ! isset( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'trustedlogin_accesskey' ){
			return;
		}

		if ( ! isset( $_REQUEST['ak'] ) ){
			return;
		}

		$access_key = sanitize_text_field( $_REQUEST['ak'] );

		if ( empty( $access_key ) ){
			return;
		}

		$endpoint = new Endpoint( $this->settings );
		$endpoint->maybe_redirect_support( $access_key );

	}

}
