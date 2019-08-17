<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin API Handler
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class Endpoint {

	use \TL_Debug_Logging;
	use \TL_Options;
	use \TL_Licensing;

	// TODO: Remove
	private $debug_mode = true;

	/**
	 * @var String - the endpoint used to redirect Support Agents to Client WP admin panels
	 * @since 0.3.0
	 **/
	const redirect_endpoint = 'trustedlogin';

	/**
	 * @var string
	 * @since 0.7.0
	 */
	const rest_endpoint = 'trustedlogin/v1';

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'maybe_endpoint_redirect' ), 99 );
		add_filter( 'query_vars', array( $this, 'endpoint_add_var' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {

		register_rest_route( self::rest_endpoint, '/verify', array(
			'methods'  => WP_REST_Server::READABLE,
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

	}

	public function verify_callback( WP_REST_Request $request ) {

		$key     = $request->get_param( 'key' );
		$type    = $request->get_param( 'type' );
		$siteurl = $request->get_param( 'siteurl' );

		$check = $this->get_licenses_by( 'key', $key );

		$this->dlog( "Check: " . print_r( $check, true ), __METHOD__ );

		$response = new WP_REST_Response();
		if ( ! $check ) {
			$response->set_status( 404 );
		} else {
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
	 * @param WP_REST_Request $request
	 * @param int $key
	 *
	 * @return bool
	 **/
	public function validate_callback( $param, $request = null, $key = null ) {

		$types = apply_filters( 'trustedlogin_api_ecom_types', array( 'EDD', 'WooCommerce' ) );

		return in_array( $param, $types, true );
	}

	/**
	 * Hooked Action: Add a specified endpoint to WP when plugin is active
	 *
	 * @since 0.3.0
	 **/
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
	 * @param Array $vars
	 *
	 * @return Array
	 **/
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
	 * Hooked Action: Check if the endpoint is hit and has a valid identifier before automatically logging in support agent
	 *
	 * @since 0.3.0
	 **/
	public function maybe_endpoint_redirect() {

		$identifier = get_query_var( self::redirect_endpoint, false );

		if ( empty( $identifier ) ) {
			return;
		}

		$this->maybe_redirect_support( $identifier );
	}


	/**
	 * Helper: If all checks pass, redirect support agent to client site's admin panel
	 *
	 * @since 0.4.0
	 *
	 * @param String $identifier collected via endpoint
	 *
	 * @see endpoint_maybe_redirect()
	 * @return null
	 **/
	public function maybe_redirect_support( $identifier ) {

		$this->dlog( "Got here. ID: $identifier", __METHOD__ );

		// first check if user can be redirected.
		if ( ! $this->auth_verify_user() ) {
			$this->dlog( "User cannot be redirected.", __METHOD__ );

			return;
		}

		// then get the envelope
		$envelope = $this->api_get_envelope( $identifier );

		$url = ( $envelope ) ? $this->envelope_to_url( $envelope ) : false;

		if ( $url ) {
			// then redirect
			$this->audit_log->insert( $identifier, 'redirected', __( 'Succcessful', 'tl-support-side' ) );
			wp_redirect( $url, 302 );
			exit;
		}
	}

	/**
	 * API Wrapper: Get the envelope for a specified site ID
	 *
	 * @since 0.2.0
	 *
	 * @param String $site_id - unique identifier of a site
	 *
	 * @return Array|false
	 **/
	public function api_get_envelope( $site_id ) {
		if ( empty( $site_id ) ) {
			$this->dlog( 'Error: site_id cannot be empty.', __METHOD__ );

			return false;
		}

		/**
		 * @todo ping TL using the API key provided, to return $store_token
		 * @todo use $store_token to get envelope from Vault
		 **/

		if ( false == ( $tokens = get_option( 'tl_tmp_tokens', false ) ) ) {
			$tokens = $this->api_get_tokens();
		}

		$this->audit_log->insert( $site_id, 'requested' );

		if ( $tokens ) {
			$key_store = ( isset( $tokens['name'] ) ) ? sanitize_title( $tokens['name'] ) : 'secret';
			$auth      = ( isset( $tokens['readKey'] ) ) ? $tokens['readKey'] : null;

			$vault_attr = array( 'type' => 'vault', 'auth' => $auth, 'debug_mode' => $this->debug_mode );
			$vault_api  = new TL_API_Handler( $vault_attr );

			/**
			 * @var Array $envelope (
			 *   String $siteurl
			 *   String $identifier
			 *   String $endpoint
			 *   Int $expiry - the time() of when this Support User will decay
			 * )
			 **/
			$envelope = $vault_api->call( $key_store . '/' . $site_id, null, 'GET' );
		} else {
			$this->dlog( "Error: Didn't recieve tokens.", __METHOD__ );
			$envelope = false;
		}

		$success = ( $envelope ) ? __( 'Succcessful', 'tl-support-side' ) : __( 'Failed', 'tl-support-side' );

		$this->audit_log->insert( $site_id, 'received', $success );

		return $envelope;

	}

	/**
	 * API Helper: Get Token for encrypted storage from the TrustedLogin API
	 *
	 * @todo complete this
	 **/
	public function api_get_tokens() {
		// Get Auth token from settings
		$auth       = $this->tls_settings_get_value( 'tls_account_key' );
		$account_id = $this->tls_settings_get_value( 'tls_account_id' );

		if ( empty( $auth ) || empty( $account_id ) ) {
			$this->dlog( "no auth or account_id provided", __METHOD__ );

			return false;
		}

		$endpoint = 'accounts/' . $account_id;

		$saas_attr = array( 'type' => 'saas', 'auth' => $auth, 'debug_mode' => $this->debug_mode );
		$saas_api  = new TL_API_Handler( $saas_attr );
		$data      = null;

		$response = $saas_api->call( $endpoint, $data, 'GET' );

		if ( $response ) {
			if ( isset( $response->status ) && 'active' == $response->status ) {
				update_option( 'tl_tmp_tokens', (array) $response );

				return (array) $response;
			} else {
				$this->dlog( "TrustedLogin Account not active", __METHOD__ );
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

		$this->dlog( "Response: " . print_r( $response, true ), __METHOD__ );

		return false;

	}

	/**
	 * Helper function: Extract redirect url from encrypted envelope.
	 *
	 * @since 0.1.0
	 *
	 * @param Array $envelope - received from encrypted TrustedLogin storage
	 *
	 * @return String|false
	 **/
	public function envelope_to_url( $envelope ) {

		if ( is_object( $envelope ) ) {
			$envelope = (array) $envelope;
		}

		if ( ! is_array( $envelope ) ) {
			$this->dlog( 'Error: envelope not an array. e:' . print_r( $envelope, true ), __METHOD__ );

			return false;
		}

		if ( ! array_key_exists( 'identifier', $envelope )
		     || ! array_key_exists( 'siteurl', $envelope )
		     || ! array_key_exists( 'endpoint', $envelope ) ) {
			$this->dlog( 'Error: malformed envelope. e:' . print_r( $envelope, true ), __METHOD__ );

			return false;
		}

		$url = $envelope['siteurl'] . '/' . $envelope['endpoint'] . '/' . $envelope['identifier'];

		return $url;

	}

	/**
	 * Helper: Check if the current user can be redirected to the client site
	 *
	 * @since 0.4.0
	 * @return Boolean
	 **/
	public function auth_verify_user() {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$_usr       = get_userdata( get_current_user_id() );
		$user_roles = $_usr->roles;

		if ( ! is_array( $user_roles ) ) {
			return false;
		}

		$required_roles = $this->tls_settings_get_approved_roles();

		$intersect = array_intersect( $required_roles, $user_roles );

		if ( 0 < count( $intersect ) ) {
			return true;
		}

		return false;
	}

}

new Endpoint();