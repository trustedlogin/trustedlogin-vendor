<?php
namespace TrustedLogin\Vendor;

use \WP_Error;
use \Exception;

/**
 * Class: TrustedLogin API Handler
 *
 * @package tl-support-side
 * @version 0.1.0
 */
class API_Handler {

	/**
	 * @since 0.1.0
	 * @var string - API version
	 */
	const saas_api_version = 'v1';

	/**
	 * @since 0.1.0
	 * @var string - the url for the API being queried.
	 */
	private $api_url = 'https://app.trustedlogin.com/api/';

	/**
	 * @since 0.1.0
	 * @var string - The API/Auth Key for authenticating API calls
	 */
	private $auth_key;

	/**
	 * @since 0.1.0
	 * @var Boolean - whether an Auth token is required.
	 */
	private $auth_required = true;

	/**
	 * @since 0.1.0
	 * @var string - The type of Header to use for sending the token
	 */
	private $auth_header_type = 'Authorization';

	/**
	 * @since 0.8.0
	 * @var array - Additional headers added to the TL_API_Handler instance. Eg for adding 'X-TL-TOKEN' values.
	 */
	private $additional_headers = array();

    /**
     * @since 0.1.0
     * @var Boolean - whether or not debug logging is enabled.
     **/
    private $debug_mode = false;

    use Debug_Logging;

    public function __construct( $data ) {

	    $defaults = array(
		    'auth' => null,
		    'debug_mode' => false,
	    );

    	$atts = wp_parse_args( $data, $defaults );

        $this->type = $atts['type'];

        $this->auth_key = $atts['auth'];

        $this->debug_mode = (bool) $atts['debug_mode'];
	}

	/**
	 * @return string
	 */
	public function get_api_url() {

		$url = apply_filters( 'trustedlogin/vendor/api/url', $this->api_url );

		return $url;
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
	 * @return array|false - response from the RESTful API
	 */
	public function call( $endpoint, $data, $method ) {

		$additional_headers = $this->get_additional_headers();

		$url = $this->api_url . $endpoint;

		if ( ! empty( $this->auth_key ) ) {
			$additional_headers[ $this->auth_header_type ] = $this->auth_key;
		}

		if ( $this->auth_required && empty( $additional_headers ) ) {
			$this->dlog( "Auth required for API call", __METHOD__ );

			return false;
		}

		$this->dlog( "Sending $method API call to $url", __METHOD__ );

		$api_response = $this->api_send( $url, $data, $method, $additional_headers );

		return $this->handle_response( $api_response );

	}

	/**
	 * Verfies the provided credentials.
	 *
	 * @since 0.9.1
	 *
	 * @return true|WP_Error If 204 status received, returns true, otherwise a WP_Error for the status code provided.
	 */
	public function verify( $account_id ='' ){

		$account_id = intval( $account_id );

		if ( 0 == $account_id ){
			return new WP_Error(
				'verify-failed',
				__('No account ID provided.', 'trustedlogin-vendor' )
			);
		}

		$url 	  = $this->api_url . 'accounts/' . $account_id ;
        $method   = 'GET';
        $body     = null;
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
	    			__('Account not found, please check the ID provided.', 'trustedlogin-vendor' )
	    		);
	    		break;
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

	    return true;

	}

	/**
	 * Handles the response for API calls
	 *
	 * @since 0.4.1
	 *
	 * @param array|false|WP_Error $api_response The result from `$this->api_send()`.
	 *
	 * @return stdObject|bool  Either `json_decode()` of the result's body, or true if status == 204 or false if empty body or error.
	 */
	public function handle_response( $api_response ) {

		if ( is_wp_error( $api_response ) ) {
			return false; // Logging intentionally left out; already logged in api_send()
		}

		if ( empty( $api_response ) || ! is_array( $api_response ) ) {
			$this->dlog( 'Malformed api_response received:' . print_r( $api_response, true ), __METHOD__ );

			return false;
		}

		// first check the HTTP Response code
		$response_code = wp_remote_retrieve_response_code( $api_response );

		switch ( $response_code ) {
			case 204:
				// does not return any body content, so can bounce out successfully here
				return true;
				break;
			case 403:
				// Problem with Token
				// TODO: Handle this
			case 404:
			default:
		}

		$body = json_decode( wp_remote_retrieve_body( $api_response ) );

		if ( empty( $body ) || ! is_object( $body ) ) {
			$this->dlog( 'No body received:' . print_r( $body, true ), __METHOD__ );

			return false;
		}

		if ( isset( $body->errors ) ) {
			foreach ( $body->errors as $error ) {
				$this->dlog( "Error from API: $error", __METHOD__ );
			}

			return false;
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
	 * @param array $addition_headers Any additional headers to send in request (required for auth/etc)
	 *
	 * @return array|false|WP_Error - wp_remote_post response, false if invalid HTTP method, WP_Error if request errors
	 */
	public function api_send( $url, $data, $method, $additional_headers ) {

		if ( ! in_array( $method, array( 'POST', 'PUT', 'GET', 'PUSH', 'DELETE' ) ) ) {
			$this->dlog( "Error: Method not in allowed array list ($method)", __METHOD__ );

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

			$this->dlog( sprintf( "%s - Something went wrong (%s): %s", __METHOD__, $response->get_error_code(), $response->get_error_message() ) );

			return $response;
		}

		$this->dlog( __METHOD__ . " - result " . print_r( $response['response'], true ) );

		return $response;

	}
}
