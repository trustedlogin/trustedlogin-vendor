<?php

/**
 * Class: TrustedLogin API Handler
 *
 * @package tl-support-side
 * @version 0.1.0
 */
class TL_API_Handler {

	/**
	 * @since 0.1.0
	 * @var String - API version
	 */
	const saas_api_version = 'v1';

	/**
	 * @since 0.1.0
	 * @var String - the type of API Handler we're working with. Possible options: 'saas'
	 */
	private $type;

	/**
	 * @since 0.1.0
	 * @var String - the url for the API being queried.
	 */
	private $api_url;

	/**
	 * @since 0.1.0
	 * @var String - The API/Auth Key for authenticating API calls
	 */
	private $auth_key;

	/**
	 * @since 0.1.0
	 * @var Boolean - whether an Auth token is required.
	 */
	private $auth_required = true;

	/**
	 * @since 0.1.0
	 * @var String - The type of Header to use for sending the token
	 */
	private $auth_header_type;

	/**
	 * @since 0.8.0
	 * @var Array - Additional headers added to the TL_API_Handler instance. Eg for adding 'X-TL-TOKEN' values.
	 */
	private $additional_headers = array();

	/**
	 * @since 0.1.0
	 * @var Boolean - whether or not debug logging is enabled.
	 */
	private $debug_mode = false;

	use TL_Debug_Logging;

	public function __construct( $data ) {

		$defaults = array(
			'type'       => null,
			'auth'       => null,
			'debug_mode' => false,
		);

		$atts = wp_parse_args( $data, $defaults );

		$this->type = $atts['type'];

		$this->auth_key = $atts['auth'];

		$this->debug_mode = (bool) $atts['debug_mode'];

		switch ( $this->type ) {
			case 'saas':
				$this->api_url          = apply_filters( 'trustedlogin/api-url/saas', 'https://app.trustedlogin.com/api/' );
				$this->auth_header_type = 'Authorization';
				break;
		}

	}

	/**
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
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
	 * @return Array|false
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
	 * @param String $endpoint - the API endpoint to be pinged
	 * @param Array $data - the data variables being synced
	 * @param String $method - HTTP RESTful method ('POST','GET','DELETE','PUT','UPDATE')
	 *
	 * @param String $type - where the API is being prepared for ('saas')
	 *
	 * @return Array|false - response from the RESTful API
	 */
	public function call( $endpoint, $data, $method ) {

		$additional_headers = $this->get_additional_headers();

		$url = $this->api_url . $endpoint;

		if ( ! empty( $this->auth_key ) ) {
			$additional_headers[ $this->auth_header_type ] = $this->auth_key;
		}

		if ( $this->auth_required && empty( $additional_headers ) ) {
			$this->dlog( "Auth required for " . $this->type . " API call", __METHOD__ );

			return false;
		}

		$this->dlog( "Sending $method API call to $url", __METHOD__ );

		$api_response = $this->api_send( $url, $data, $method, $additional_headers );

		return $this->handle_response( $api_response );

	}

	public function handle_response( $api_response ) {

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
				// maybe do something here to handle this
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
	 * @param Array $data
	 * @param Array $addition_header - any additional headers required for auth/etc
	 *
	 * @param String $url - the complete url for the REST API request
	 *
	 * @return Array|false - wp_remote_post response or false if fail
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

		$post_attr = array(
			'method'      => $method,
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'cookies'     => array(),
		);

		if ( $data ) {
			$post_attr['body'] = json_encode( $data );
		}

		$response = wp_remote_post( $url, $post_attr );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->dlog( __METHOD__ . " - Something went wrong: $error_message" );

			return false;
		} else {
			$this->dlog( __METHOD__ . " - result " . print_r( $response['response'], true ) );
		}

		return $response;

	}
}