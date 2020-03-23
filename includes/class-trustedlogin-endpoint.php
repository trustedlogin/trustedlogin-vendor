<?php

namespace TrustedLogin\Vendor;

use \WP_REST_Request;
use \WP_REST_Response;

/**
 * Class: TrustedLogin API Handler
 *
 * @package tl-support-side
 * @version 0.1.0
 */
class Endpoint {

	use Debug_Logging;
	use Licensing;

	/**
	 * @var String - the endpoint used to redirect Support Agents to Client WP admin panels
	 * @since 0.3.0
	 */
	const redirect_endpoint = 'trustedlogin';

	/**
	 * @var string
	 * @since 0.7.0
	 */
	const rest_endpoint = 'trustedlogin/v1';

	/**
	 * @since 0.9.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var TrustedLogin_Audit_Log
	 * @since 0.9.0
	 */
	private $audit_log;

	/**
	 * TrustedLogin_Endpoint constructor.
	 */
	public function __construct( Settings $settings_instance ) {

		$this->settings = $settings_instance;

		$this->audit_log = new TrustedLogin_Audit_Log( $this->settings );

		add_action( 'init', array( $this, 'maybe_add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'maybe_endpoint_redirect' ), 99 );
		add_filter( 'query_vars', array( $this, 'endpoint_add_var' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * @return TrustedLogin_Audit_Log
	 */
	public function get_audit_log() {
		return $this->audit_log;
	}

	public function register_endpoints() {

		register_rest_route( self::rest_endpoint, '/verify', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'verify_callback' ),
			'args'     => array(
				'key'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'type'    => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_title',
					'validate_callback' => array( $this, 'validate_callback' ),
				),
				'siteurl' => array(
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				),
			),
		) );

		register_rest_route( self::rest_endpoint, '/public_key', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'public_key_callback' ),
		) );

	}

	/**
	 * Returns the Public Key for this specific vendor/plugin.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function public_key_callback( \WP_REST_Request $request ) {

		$trustedlogin_encryption = new Encryption();
		$public_key              = $trustedlogin_encryption->get_public_key();

		$response = new \WP_REST_Response();

		if ( ! is_wp_error( $public_key ) ) {
			$data = array( 'publicKey' => $public_key );
			$response->set_data( $data );
			$response->set_status( 200 );
		} else {
			$response->set_status( 501 );
		}

		return $response;

	}

	/**
	 * Verifies that the site has a license and can indeed request support.
	 *
	 * @since 0.3.0 Initial build
	 * @since 0.8.0 Added `TrustedLogin_Encryption->get_public_key()` data to response.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function verify_callback( \WP_REST_Request $request ) {

		$key     = $request->get_param( 'key' );
		$type    = $request->get_param( 'type' );
		$siteurl = $request->get_param( 'siteurl' );

		$license_generator = License_Generators::get_active();

		$check = $this->get_licenses_by( 'key', $key );

		$this->dlog( "Check: " . print_r( $check, true ), __METHOD__ );

		$response = new \WP_REST_Response();

		if ( ! $check ) {
			$response->set_status( 404 );
		} else {

			$data = array();

			$trustedlogin_encryption = new Encryption();
			$public_key              = $trustedlogin_encryption->get_public_key();

			if ( ! is_wp_error( $public_key ) ) {
				$data['publicKey'] = $public_key;
				$response->set_data( $data );
			}

			$response->set_status( 200 );
		}

		return $response;

	}

	/**
	 * Helper: Determines if eCommerce platform is acceptable
	 *
	 * @since 0.8.0
	 *
	 * @param string $param - The parameter value being validated
	 * @param \WP_REST_Request $request
	 * @param int $key
	 *
	 * @return bool
	 */
	public function validate_callback( $param, $request = null, $key = null ) {

		$types = apply_filters( 'trustedlogin_api_ecom_types', array( 'EDD', 'WooCommerce' ) );

		return in_array( $param, $types, true );
	}

	/**
	 * Hooked Action: Add a specified endpoint to WP when plugin is active
	 *
	 * @since 0.3.0
	 */
	public function maybe_add_rewrite_rule() {

		if ( get_option( 'tl_permalinks_flushed' ) ) {
			return;
		}

		$endpoint_regex = '^' . self::redirect_endpoint . '/([^/]+)/?$'; // ^p/(d+)/?$

		$this->dlog( "Endpoint Regex: $endpoint_regex", __METHOD__ );

		add_rewrite_rule(
			$endpoint_regex,
			'index.php?' . self::redirect_endpoint . '=$matches[1]',
			'top' );

		$this->dlog( "Endpoint " . self::redirect_endpoint . " added.", __METHOD__ );

		flush_rewrite_rules( false );

		$this->dlog( "Rewrite rules flushed.", __METHOD__ );

		update_option( 'tl_permalinks_flushed', 1 );
	}

	/**
	 * Filter: Add our custom variable to endpoint queries to hold the identifier
	 *
	 * @since 0.3.0
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public function endpoint_add_var( $vars = array() ) {

		// Only add once
		if ( in_array( self::redirect_endpoint, $vars, true ) ) {
			return $vars;
		}

		$vars[] = self::redirect_endpoint;

		$this->dlog( "Endpoint var " . self::redirect_endpoint . " added", __METHOD__ );

		return $vars;
	}

	/**
	 * Hooked Action: Check if the endpoint is hit and has a valid secret_id before automatically logging in support agent
	 *
	 * @since 0.3.0
	 */
	public function maybe_endpoint_redirect() {

		$secret_id = get_query_var( self::redirect_endpoint, false );

		if ( empty( $secret_id ) ) {
			return;
		}

		$this->maybe_redirect_support( $secret_id );
	}


	/**
	 * Helper: If all checks pass, redirect support agent to client site's admin panel
	 *
	 * @since 0.4.0
	 * @since 0.8.0 Added `Encryption->decrypt()` to decrypt envelope from Vault.
	 *
	 * @see endpoint_maybe_redirect()
	 *
	 * @param string $secret_id collected via endpoint
	 *
	 * @return null
	 */
	public function maybe_redirect_support( $secret_id ) {

		$this->dlog( "Got here. ID: $secret_id", __METHOD__ );

		// first check if user can be redirected.
		if ( ! $this->auth_verify_user() ) {
			$this->dlog( "User cannot be redirected.", __METHOD__ );

			return;
		}

		// then get the envelope
		$envelope = $this->api_get_envelope( $secret_id );

		if ( is_wp_error( $envelope ) ) {
			$this->dlog( 'Error: ' . $envelope->get_error_message(), __METHOD__ );
			$this->audit_log->insert( $secret_id, 'failed', $envelope->get_error_message() );
			wp_redirect( get_site_url(), 302 );
			exit;
		}

		$url = ( $envelope ) ? $this->envelope_to_url( $envelope ) : false;

		global $init_tl;

		if ( is_wp_error( $url ) ) {
			$this->audit_log->insert( $secret_id, 'failed', $url->get_error_message() );
			wp_redirect( get_site_url(), 302 );
			exit;
		}

		if ( $url ) {
			// then redirect
			$this->audit_log->insert( $secret_id, 'redirected', __( 'Succcessful', 'trustedlogin-vendor' ) );
			wp_redirect( $url, 302 );
			exit;
		}

		$this->dlog( "Got to end of function, with no action.", __METHOD__ );
	}

	/**
	 * API Wrapper: Get the envelope for a specified site ID
	 *
	 * @since 0.2.0
	 *
	 * @param string $site_id - unique secret_id of a site
	 *
	 * @return array|false
	 */
	public function api_get_envelope( $secret_id ) {
		if ( empty( $secret_id ) ) {
			$this->dlog( 'Error: secret_id cannot be empty.', __METHOD__ );

			return new WP_Error( 'data-error', __( 'Site ID cannot be empty', 'trustedlogin-vendor' ) );
		}

		/**
		 * @var The data array that will be sent to TrustedLogin to request a site's envelope
		 */
		$data = array();

		// Let's grab the user details. Logged in status already confirmed in maybe_redirect_support();
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return new WP_Error( 'auth-error', __( 'User not logged in.', 'trustedlogin-vendor' ) );
		}
		$data['user'] = array( 'id' => $current_user->ID, 'name' => $current_user->display_name );

		// make sure we have the auth details from the settings page before continuing.
		$saas_auth  = $this->settings->get_setting( 'account_key' );
		$account_id = $this->settings->get_setting( 'account_id' );
		$public_key = $this->settings->get_setting( 'public_key' );

		if ( empty( $saas_auth ) || empty( $account_id ) || empty( $public_key ) ) {
			$this->dlog( "no api_key, public_key or account_id provided", __METHOD__ );

			return new WP_Error( 'setup-error', __( 'No auth, public key or account_id data found', 'trustedlogin-vendor' ) );
		}

		// Then let's get the identity verification pair to confirm the site is the one sending the request.
		$trustedlogin_encryption = new Encryption();
		$auth_nonce              = $trustedlogin_encryption->create_identity_nonce();

		if ( is_wp_error( $auth_nonce ) ) {
			return $auth_nonce;
		}

		$data['nonce']       = $auth_nonce['nonce'];
		$data['signedNonce'] = $auth_nonce['signed'];

		$this->audit_log->insert( $secret_id, 'requested' );

		$endpoint = 'sites/' . $secret_id . '/get-envelope';


		$saas_attr = array(
			'type'       => 'saas',
			'auth'       => $saas_auth,
			'debug_mode' => $this->settings->debug_mode_enabled()
		);
		$saas_api  = new TL_API_Handler( $saas_attr );


		/**
		 * @see https://github.com/trustedlogin/trustedlogin-ecommerce/blob/master/docs/user-remote-authentication.md
		 * @var string $saas_token Additional SaaS Token for authenticating API queries.
		 */
		$saas_token  = hash( 'sha256', $public_key . $saas_auth );
		$token_added = $saas_api->set_additional_header( 'X-TL-TOKEN', $saas_token );

		if ( ! $token_added ) {
			$error = __( 'Error setting X-TL-TOKEN header', 'trustedlogin-vendor' );
			$this->dlog( $error, __METHOD__ );

			return new WP_Error( 'x-tl-token-error', $error );
		}

		/**
		 * @var array $envelope (
		 * @type string $siteurl The site url. Double encrypted.
		 * @type string $identifier The support-agent unique ID. Double encrypted.
		 * @type string $endpoint The unique endpoint for auto-login. Double encrypted.
		 * @type int $expiry The time() of when this Support User will decay
		 * )
		 */
		$envelope = $saas_api->call( $endpoint, $data, 'GET' );

		$success = ( ! is_wp_error( $envelope ) ) ? __( 'Succcessful', 'trustedlogin-vendor' ) : __( 'Failed', 'trustedlogin-vendor' );

		$this->audit_log->insert( $secret_id, 'received', $success );

		return $envelope;

	}

	/**
	 * Helper function: Extract redirect url from encrypted envelope.
	 *
	 * @since 0.1.0
	 *
	 * @param array $envelope Received from encrypted TrustedLogin storage {
	 *
	 * @type string $siteurl Encrypted site URL
	 * @type string $identifier Encrypted site identifier, used to generate endpoint
	 * }
	 *
	 * @return string|false
	 */
	public function envelope_to_url( $envelope ) {

		if ( is_object( $envelope ) ) {
			$envelope = (array) $envelope;
		}

		if ( ! is_array( $envelope ) ) {
			$this->dlog( 'Error: envelope not an array. e:' . print_r( $envelope, true ), __METHOD__ );

			return new WP_Error( 'malformed_envelope', 'The data received is not formatted correctly' );
		}

		if ( ! array_key_exists( 'identifier', $envelope ) || ! array_key_exists( 'siteurl', $envelope ) ) {
			$this->dlog( 'Error: malformed envelope. e:' . print_r( $envelope, true ), __METHOD__ );

			return new WP_Error( 'malformed_envelope', 'The data received is not formatted correctly' );
		}

		$trustedlogin_encryption = new Encryption();

		try {
			$parts = array(
				'siteurl'    => $trustedlogin_encryption->decrypt( $envelope['siteurl'] ),
				'identifier' => $trustedlogin_encryption->decrypt( $envelope['identifier'] ),
			);

		} catch ( Exception $e ) {

			return new WP_Error( $e->getCode(), $e->getMessage() );

		}

		$parts['endpoint'] = md5( $parts['siteurl'] . $parts['identifier'] );

		$url = $parts['siteurl'] . '/' . $parts['endpoint'] . '/' . $parts['identifier'];

		return $url;

	}

	/**
	 * Helper: Check if the current user can be redirected to the client site
	 *
	 * @since 0.4.0
	 * @return bool
	 */
	public function auth_verify_user() {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$_usr       = get_userdata( get_current_user_id() );
		$user_roles = $_usr->roles;

		if ( ! is_array( $user_roles ) ) {
			return false;
		}

		$required_roles = $this->settings->get_approved_roles();

		$intersect = array_intersect( $required_roles, $user_roles );

		if ( 0 < count( $intersect ) ) {
			return true;
		}

		return false;
	}

}
