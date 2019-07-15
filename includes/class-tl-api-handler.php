<?php

/**
 * Class: TrustedLogin API Handler
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class TL_API_Handler
{

    /**
     * @since 0.1.0
     * @var String - the type of API Handler we're working with. Possible options: 'saas', 'vault'
     **/
    private $type;

    /**
     * @since 0.1.0
     * @var String - the url for the API being queried.
     **/
    private $api_url;

    /**
     * @since 0.1.0
     * @var String - API version
     **/
    private $api_version;

    /**
     * @since 0.1.0
     * @var String - The API/Auth Key for authenticating API calls
     **/
    private $auth_key;

    /**
     * @since 0.1.0
     * @var Boolean - whether an Auth token is required.
     **/
    private $auth_required;

    /**
     * @since 0.1.0
     * @var String - The type of Header to use for sending the token
     **/
    private $auth_header_type;

    /**
     * @since 0.1.0
     * @var Boolean - whether or not debug logging is enabled.
     **/
    private $debug_mode;

    use TL_Debug_Logging;

    public function __construct($data)
    {

        $this->type = (isset($data->type)) ? $data->type : null;

        $this->auth = (isset($data->auth)) ? $data->auth : null;

        $this->debug_mode = (isset($data->debug_mode)) ? $data->debug_mode : false;

        switch ($this->type) {
            case 'saas':
                $this->api_version = 'v1';
                $this->api_url = 'https://app.trustedlogin.com/api/' . $this->api_version . '/';
                $this->auth_header_type = 'Authorization';
                $this->auth_required = true;
                break;
            case 'vault':
                $this->api_version = 'v1';
                $this->api_url = 'https://vault.trustedlogin.com/' . $this->api_version . '/';
                $this->auth_header_type = 'X-Vault-Token';
                $this->auth_required = true;
                break;
        }

    }

    public function set_auth($auth)
    {
        $this->auth_key = $auth;
    }

    /**
     * Prepare API call and return result
     *
     * @since 0.4.1
     * @param String $type - where the API is being prepared for (either 'saas' or 'vault')
     * @param String $endpoint - the API endpoint to be pinged
     * @param Array $data - the data variables being synced
     * @param String $method - HTTP RESTful method ('POST','GET','DELETE','PUT','UPDATE')
     * @return Array|false - response from the RESTful API
     **/
    public function api_prepare($endpoint, $data, $method)
    {

        $additional_headers = array();
        $url = $this->api_url . $endpoint;

        if (!empty($this->auth)) {
            $additional_headers[$this->auth_header_type] = $this->auth;
        }

        if ($this->auth_required && empty($additional_headers)) {
            $this->dlog("Auth required for " . $this->type . " API call", __METHOD__);
            return false;
        }

        $this->dlog("Sending $method API call to $url", __METHOD__);

        $api_response = $this->api_send($url, $data, $method, $additional_headers);

        return $this->handle_response($api_response);

    }

    public function handle_response($api_response)
    {

        if (empty($api_response) || !is_array($api_response)) {
            $this->dlog('Malformed api_response received:' . print_r($api_response, true), __METHOD__);
            return false;
        }

        // first check the HTTP Response code
        $response_code = wp_remote_retrieve_response_code($api_response);

        switch ($response_code) {
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

        $body = json_decode(wp_remote_retrieve_body($api_response));

        if (empty($body) || !is_object($body)) {
            $this->dlog('No body received:' . print_r($body, true), __METHOD__);
            return false;
        }

        if (isset($body->errors)) {
            foreach ($body->errors as $error) {
                $this->dlog("Error from Vault: $error", __METHOD__);
            }
            return false;
        }

        return $body;

    }

    /**
     * API Function: send the API request
     *
     * @since 0.4.0
     * @param String $url - the complete url for the REST API request
     * @param Array $data
     * @param Array $addition_header - any additional headers required for auth/etc
     * @return Array|false - wp_remote_post response or false if fail
     **/
    public function api_send($url, $data, $method, $additional_headers)
    {

        if (!in_array($method, array('POST', 'PUT', 'GET', 'PUSH', 'DELETE'))) {
            $this->dlog("Error: Method not in allowed array list ($method)", __METHOD__);
            return false;
        }

        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        if (!empty($additional_headers)) {
            $headers = array_merge($headers, $additional_headers);
        }

        $post_attr = array(
            'method' => $method,
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array(),
        );

        if ($data) {
            $post_attr['body'] = json_encode($data);
        }

        $response = wp_remote_post($url, $post_attr);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->dlog(__METHOD__ . " - Something went wrong: $error_message");
            return false;
        } else {
            $this->dlog(__METHOD__ . " - result " . print_r($response['response'], true));
        }

        return $response;

    }

}
