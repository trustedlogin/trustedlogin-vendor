<?php
namespace TrustedLogin\Vendor;

use \WP_Error;
use \Exception;

/**
 * Class: TrustedLogin API Handler
 *
 * @version 0.1.0
 */
class API_Handler {

	/**
	 * @var string Current API version.
	 */
	const API_VERSION = 'v1';

	/**
	 * @var string The url for the API being queried.
	 */
	private $api_url = 'https://app.trustedlogin.com/api/v1/';

	/**
	 * @var string The API private key for authenticating API calls
	 */
	private $private_key;

	/**
	 * @var bool Whether an Auth token is required.
	 */
	private $auth_required = true;

	/**
	 * @var string The type of Header to use for sending the token
	 */
	private $auth_header_type = 'Authorization';

	/**
	 * @var array Additional headers added to the TL_API_Handler instance. Eg for adding 'X-TL-TOKEN' values.
	 */
	private $additional_headers = array();

    /**
     * @var bool Whether or not debug logging is enabled
     */
    private $debug_mode = false;

    use Debug_Logging;

    public function __construct( $data ) {

		$defaults = array(
			'private_key' => null,
			'debug_mode' => false,
			'type' => 'saas',
		);

		$atts = wp_parse_args( $data, $defaults );

		$this->type = $atts['type'];

		$this->private_key = $atts['private_key'];

		$this->debug_mode = (bool) $atts['debug_mode'];

    }

	/**
	 * @internal
	 * @return string Full versioned API url, with trailing slash.
	 */
	public function get_api_url() {
		return apply_filters( 'trustedlogin/api-url/saas', $this->api_url );
	}

	/**
	 * @return string
	 */
	public function get_auth_header_type() {
		return $this->auth_header_type;
	}

	/**
	 * @return array
	 */
	public function get_additional_headers() {
		return $this->additional_headers;
	}

	/**
	 * Sets the Header authorization type
	 *
	 * @since 0.8.0
	 *
	 * @param string $value The Header value to add.
	 *
	 * @param string $key The Header key to add.
	 *
	 * @return array|false
	 */
	public function set_additional_header( $key, $value ) {

		if ( empty( $key ) || empty( $value ) ) {
			return false;
		}

		$this->additional_headers[ $key ] = $value;

		return $this->additional_headers;

	}


	/**
	 * Prepare API call and return result
	 *
	 * @since 0.4.1
	 *
	 * @param string $endpoint - the API endpoint to be pinged
	 * @param array $data - the data variables being synced
	 * @param string $method - HTTP RESTful method ('POST','GET','DELETE','PUT','UPDATE')
	 *
	 * @param string $type - where the API is being prepared for ('saas')
	 *
	 * @return object|bool|WP_Error - response from the RESTful API
	 */
	public function call( $endpoint, $data, $method ) {

		$additional_headers = $this->get_additional_headers();

		$url = $this->get_api_url() . $endpoint;

			$additional_headers[ $this->auth_header_type ] = 'Bearer ' . $this->auth_key;
		if ( ! empty( $this->private_key ) ) {
		}

		if ( $this->auth_required && empty( $additional_headers ) ) {
			$this->log( "Auth required for API call", __METHOD__, 'error' );

			return false;
		}

		$this->log( "Sending $method API call to $url", __METHOD__, 'debug' );

		$api_response = $this->api_send( $url, $data, $method, $additional_headers );

		return $this->handle_response( $api_response );
	}

	/**
	 * Verifies the provided credentials.
	 *
	 * @since 0.9.1
	 *
	 * @return \stdClass|WP_Error If valid status received, returns object with a few details, otherwise a WP_Error for the status code provided.
	 */
	public function verify( $account_id = '' ){

		$account_id = intval( $account_id );

		if ( 0 === $account_id ){
			return new WP_Error(
				'verify-failed',
				__('No account ID provided.', 'trustedlogin-vendor' )
			);
		}

		$url 	  = $this->get_api_url() . 'accounts/' . $account_id ;
        $method   = 'POST';
        $body     = array(
        	'api_endpoint' => get_rest_url(),
         );
        $headers  = $this->get_additional_headers();

        $verification = $this->api_send( $url, $body, $method, $headers );

        if( is_wp_error( $verification ) ) {
	        return new WP_Error (
		        $verification->get_error_code(),
		        __('We could not verify your TrustedLogin credentials, please try save settings again.', 'trustedlogin-vendor' ),
		        $verification->get_error_message()
	        );
        }

        if ( ! $verification ) {
	    	return new WP_Error (
	    		'verify-failed',
	    		__('We could not verify your TrustedLogin credentials, please try save settings again.', 'trustedlogin-vendor' )
	    	);
	    }

	    $status = wp_remote_retrieve_response_code( $verification );

	    switch ( $status ){
	    	case 400:
		    case 403:
	    		return new WP_Error(
	    			'verify-failed-' . $status,
	    			__('Could not verify private/public keys, please confirm the provided keys.', 'trustedlogin-vendor' )
	    		);
	    		break;
	    	case 404:
	    		return new WP_Error(
	    			'verify-failed-404',
	    			__( 'Account not found, please check the ID provided.', 'trustedlogin-vendor' )
	    		);
	    		break;
	    	case 405:
	    		return new WP_Error(
	    			'verify-failed-405',
	    			sprintf(
	    				__('Incorrect method (%1$s) used for %2$s', 'trustedlogin-vendor'),
	    				/* %1$s */ $method,
	    				/* %2$s */ $url
	    			)
	    		);
	    	case 500:
	    		return new WP_Error(
	    			'verify-failed-500',
	    			sprintf( __('Status %d returned', 'trustedlogin-vendor' ), $status )
	    		);
	    		break;
	    }

	    $body = wp_remote_retrieve_body( $verification );

	    $body = json_decode( $body );

	    if( ! $body ) {
		    return new WP_Error(
			    'verify-failed',
			    __('Your TrustedLogin account is not active, please login to activate your account.', 'trustedlogin-vendor' )
		    );
	    }

	    if ( isset( $body->status ) && 'active' !== $body->status ){
	    	return new WP_Error(
    			'verify-failed-inactive',
    			__('Your TrustedLogin account is not active, please login to activate your account.', 'trustedlogin-vendor' )
    		);
	    }

	    if( isset( $body->error ) && $body->error ) {
		    return new WP_Error(
			    'verify-failed-other',
			    sprintf( __('Please contact support (Error Status #%d)', 'trustedlogin-vendor' ), $status )
		    );
	    }

	    return $body;
	}

	/**
	 * Handles the response for API calls
	 *
	 * @since 0.4.1
	 *
	 * @param array|false|WP_Error $api_response The result from `$this->api_send()`.
	 *
	 * @return object|true|WP_Error  Either `json_decode()` of the result's body, or true if status === 204 (successful response, but no sites found) or WP_Error if empty body or error.
	 */
	public function handle_response( $api_response ) {

		if ( is_wp_error( $api_response ) ) {
			return $api_response; // Logging intentionally left out; already logged in api_send()
		}

		if ( empty( $api_response ) || ! is_array( $api_response ) ) {

			$this->log( 'Malformed api_response received:' . print_r( $api_response, true ), __METHOD__, 'error' );

			return new WP_Error( 'malformed_response', __( 'Malformed API response received.', 'trustedlogin-vendor' ) );
		}

		// first check the HTTP Response code
		$response_code = wp_remote_retrieve_response_code( $api_response );

		// successful response, but no sites found. does not return any body content, so can bounce out successfully here
		if( 204 === $response_code ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $api_response );

		$body = json_decode( $body );

		if ( empty( $body ) || ! is_object( $body ) ) {
			$this->log( 'No body received:' . print_r( $body, true ), __METHOD__, 'error' );

			return new WP_Error( 'empty_body', __( 'No body received.', 'trustedlogin-vendor' ) );
		}

		$body_message = isset( $body->message ) ? $body->message : null;

		switch ( $response_code ) {
			case 424:
				$this->log( 'Error Getting Signature Key from Vendor: ' . print_r( $api_response, true ), __METHOD__, 'error' );
				return new WP_Error( 'signature_key_error', $body_message );
			case 410:
				$this->log( 'Error Getting Signature Key from Vendor: ' . print_r( $api_response, true ), __METHOD__, 'error' );
				return new WP_Error( 'gone', 'This support request is gone. Please create a new request. (SecretNotFoundInVaultException)' );
			case 403:
				// Problem with Token
				// TODO: Handle this
			case 404:
				return new WP_Error( 'not_found', __( 'Not found.', 'trustedlogin-vendor' ) );
			default:
		}

		if ( isset( $body->errors ) ) {
			$errors = implode( '', (array) $body->errors );

			$this->log( "Error from API: {$errors}", __METHOD__, 'error' );

			return new WP_Error( 'api_errors', sprintf( __( 'Errors returned from API: %s', 'trustedlogin-vendor' ), $errors ) );
		}

		return $body;

	}

	/**
	 * API Function: send the API request
	 *
	 * @since 0.4.0
	 *
	 * @param string $url The complete url for the REST API request
	 * @param mixed $data Data to send as JSON-encoded request body
	 * @param string $method HTTP request method (must be 'POST', 'PUT', 'GET', 'PUSH', or 'DELETE')
	 * @param array $additional_headers Any additional headers to send in request (required for auth/etc)
	 *
	 * @return array|false|WP_Error - wp_remote_post response, false if invalid HTTP method, WP_Error if request errors
	 */
	public function api_send( $url, $data, $method, $additional_headers ) {

		if ( ! in_array( $method, array( 'POST', 'PUT', 'GET', 'PUSH', 'DELETE' ) ) ) {
			$this->log( "Error: Method not in allowed array list ($method)", __METHOD__, 'error' );

			return false;
		}

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $additional_headers ) ) {
			$headers = array_merge( $headers, $additional_headers );
		}

		$request_atts = array(
			'method'      => $method,
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'cookies'     => array(),
		);

		if ( $data ) {
			$request_atts['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $url, $request_atts );

		if ( is_wp_error( $response ) ) {

			$this->log( sprintf( "%s - Something went wrong (%s): %s", __METHOD__, $response->get_error_code(), $response->get_error_message() ), __METHOD__, 'error' );

			return $response;
		}

		$this->log( __METHOD__ . " - result " . print_r( $response['response'], true ) );

		return $response;

	}
}
