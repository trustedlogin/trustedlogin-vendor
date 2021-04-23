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
	 * WordPress admin slug for access key login
	 */
	const PAGE_SLUG = 'trustedlogin_access_key';

	/**
	 * Name of form nonce
	 */
	const NONCE_NAME = '_tl_ak_nonce';

	/**
	 * Name of form nonce action
	 */
	const NONCE_ACTION = 'ak-redirect';

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

		add_action( 'admin_init', array( $this, 'maybe_handle_accesskey' ) );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Adds a submenu page when the user is authorized to log in to customer sites
	 *
	 * @return void
	 */
	public function add_admin_menu() {

		if( ! $this->settings->exists() ) {
			return;
		}

		$endpoint = new Endpoint( $this->settings );

		if( ! $endpoint->auth_verify_user() ) {
			return;
		}

		add_submenu_page(
			'trustedlogin_vendor',
			__( 'TrustedLogin with Site Key', 'trustedlogin-vendor' ),
			__( 'Log In with Site Key', 'trustedlogin-vendor' ),
			'manage_options', // TODO: Custom capabilities!
			self::PAGE_SLUG,
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
				  <img src="%s" width="400" alt="TrustedLogin">
				  <form method="post" target="_blank">
					  <input name="ak" type="text" id="trustedlogin-access-key" placeholder="%s" />
					  <button type="submit" id="trustedlogin-go" class="button button-large trustedlogin-proceed">%s</button>
					  %s
				  </form>
				</div>',
			esc_url( plugins_url( 'assets/trustedlogin-logo.png', TRUSTEDLOGIN_PLUGIN_FILE ) ),
			esc_html__('Paste key received from customer', 'trustedlogin-vendor'),
			esc_html__('Login to Site', 'trustedlogin-vendor'),
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, true, false )
		);

		echo $output;
	}

	/**
	 *
	 */
	public function maybe_handle_accesskey() {

		if ( empty( $_REQUEST['ak'] ) ) {
			return;
		}

		if ( empty( $_REQUEST[ self::NONCE_NAME ] ) ){
			return;
		}

		// Referred from same screen?
		if( add_query_arg( array() ) !== wp_get_raw_referer() ) {
			$this->dlog( 'Referrer does not match; could be insecure request.',__METHOD__ );
			return;
		}

		// Valid nonce?
		$valid = wp_verify_nonce( $_REQUEST[ self::NONCE_NAME ], self::NONCE_ACTION );

		if ( ! $valid ) {
			$this->dlog( 'Nonce is invalid; could be insecure request. Refresh the page and try again.',__METHOD__ );
			return;
		}

		$access_key = sanitize_text_field( $_REQUEST['ak'] );

		$endpoint = new Endpoint( $this->settings );

		$site_ids = $endpoint->api_get_secret_ids( $access_key );

		if ( is_wp_error( $endpoint ) ){
			add_action( 'admin_notices', function () use ( $site_ids ) {
				echo '<div class="error"><h3>' . esc_html__( 'Could not log in to site using access key.', 'trustedlogin-vendor' ) . '</h3>' . wpautop( esc_html( $site_ids->get_error_message() ) ) . '</div>';
			} );

			return;
		}

		if ( empty( $site_ids ) ){
			add_action( 'admin_notices', function () {
				echo '<div class="error"><h3>' . esc_html__( 'Could not log in to site using access key.', 'trustedlogin-vendor' ) . '</h3>' . wpautop( esc_html__( 'No sites found.', 'trustedlogin-vendor' ) ) . '</div>';
			} );

			return;
		}

		/**
		 * TODO: Add handling for multiple siteIds
		 * @see  https://github.com/trustedlogin/trustedlogin-vendor/issues/47
		 */

		$envelope = $endpoint->api_get_envelope( $site_ids[0] );

		// Print error
		if ( is_wp_error( $envelope ) ) {

			add_action( 'admin_notices', function () use ( $envelope ) {
				echo '<div class="error"><h3>' . esc_html__( 'Could not log in to site using access key.', 'trustedlogin-vendor' ) . '</h3>' . wpautop( esc_html( $envelope->get_error_message() ) ) . '</div>';
			} );

			return;
		}

		$endpoint->maybe_redirect_support( $access_key, $envelope );
	}

}
