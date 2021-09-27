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

	const ACCESS_KEY_ACTION_NAME = 'tl_access_key_login';

	const ACCESS_KEY_INPUT_NAME = 'ak';

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

		add_action( 'wp_ajax_' . self::ACCESS_KEY_ACTION_NAME, array( $this, 'handle_ajax' ) );

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
			__( 'Access Key Log-In', 'trustedlogin-vendor' ),
			$this->settings->get_support_capability(),
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
		wp_enqueue_script( 'trustedlogin-access-keys', plugins_url( '/assets/trustedlogin-access-keys.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			TRUSTEDLOGIN_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'trustedlogin-access-keys', 'tl_access_keys', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'debug' => $this->settings->debug_mode_enabled(),
			'action_name' => self::ACCESS_KEY_ACTION_NAME,
			'input_name' => self::ACCESS_KEY_INPUT_NAME,
		) );

		$output = sprintf(
			'<div class="trustedlogin-dialog accesskey">
				  <img src="%s" width="400" alt="TrustedLogin">
				  <form method="post" id="trustedlogin-access-key-login">
				  	  <input type="hidden" name="action" value="%s" />
					  <input name="%s" type="text" id="trustedlogin-access-key" placeholder="%s" required aria-required="true" minlength="32" maxlength="64" autofocus />
					  <button type="submit" id="trustedlogin-go" class="button button-large button-primary trustedlogin-proceed">%s</button>
					  %s
				  </form>
				  <div class="trustedlogin-response-container">
				  	<div class="trustedlogin-response__success"></div>
				  	<div class="trustedlogin-response__error"></div>
				  </div>
				</div>',
			esc_url( plugins_url( 'assets/trustedlogin-logo.png', TRUSTEDLOGIN_PLUGIN_FILE ) ),
			self::ACCESS_KEY_ACTION_NAME,
			self::ACCESS_KEY_INPUT_NAME,
			esc_html__('Paste key received from customer', 'trustedlogin-vendor'),
			esc_html__('Log Into Site', 'trustedlogin-vendor'),
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, true, false )
		);

		echo $output;
	}

	/**
	 * Verifies the $_POST request by the Access Key login form.
	 *
	 * @return bool|WP_Error
	 */
	private function verify_grant_access_request() {

		if ( empty( $_REQUEST[ self::ACCESS_KEY_INPUT_NAME ] ) ) {
			$this->log( 'No access key sent.',__METHOD__, 'error' );
			return new WP_Error('no_access_key', esc_html__( 'No access key was sent with the request.', 'trustedlogin-vendor' ) );
		}

		if ( empty( $_REQUEST[ self::NONCE_NAME ] ) ){
			$this->log( 'No nonce set. Insecure request.',__METHOD__, 'error' );
			return new WP_Error('no_nonce', esc_html__( 'No nonce was sent with the request.', 'trustedlogin-vendor' ) );
		}

		if ( empty( $_REQUEST['_wp_http_referer'] ) ) {
			$this->log( 'No referrer set; could be insecure request.',__METHOD__, 'error' );
			return new WP_Error('no_referrer', esc_html__( 'The referrer was not set for the request.', 'trustedlogin-vendor' ) );
		}

		// Referred from same screen?
		if( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) !== site_url( wp_get_raw_referer() ) ) {
			$this->log( 'Referrer does not match; could be insecure request.',__METHOD__, 'error' );
			return new WP_Error('no_access_key', esc_html__( 'The referrer does not match the expected source of the request.', 'trustedlogin-vendor' ) );
		}

		// Valid nonce?
		$valid = wp_verify_nonce( $_REQUEST[ self::NONCE_NAME ], self::NONCE_ACTION );

		if ( ! $valid ) {
			$this->log( 'Nonce is invalid; could be insecure request. Refresh the page and try again.',__METHOD__, 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Processes the AJAX request.
	 *
	 * Sends JSON responses.
	 *
	 */
	function handle_ajax() {

		$verified = $this->verify_grant_access_request();

		if ( ! $verified || is_wp_error( $verified ) ) {
			wp_send_json_error( $verified );
		}

		$access_key = sanitize_text_field( $_REQUEST[ self::ACCESS_KEY_INPUT_NAME ] );

		$endpoint = new Endpoint( $this->settings );

		// First check if user can be here at all.
		if ( ! $endpoint->auth_verify_user() ) {
			$this->log( 'User cannot be redirected due to Endpoint::auth_verify_user() returning false.', __METHOD__, 'warning' );
			return;
		}

		$site_ids = $endpoint->api_get_secret_ids( $access_key );

		if ( is_wp_error( $site_ids ) ){
			wp_send_json_error( $site_ids );
		}

		if ( empty( $site_ids ) ){
			wp_send_json_error( esc_html__( 'No sites were found matching the access key.', 'trustedlogin-vendor' ), 404 );
		}

		/**
		 * TODO: Add handling for multiple siteIds
		 * @see  https://github.com/trustedlogin/trustedlogin-vendor/issues/47
		 */
		$envelope = $endpoint->api_get_envelope( $site_ids[0] );

		// Print error
		if ( is_wp_error( $envelope ) ) {
			wp_send_json_error( $envelope );
		}

		$parts = $endpoint->envelope_to_url( $envelope, true );

		if ( is_wp_error( $parts ) ) {
			wp_send_json_error( $parts );
		}

		wp_send_json( $parts );
	}

}
