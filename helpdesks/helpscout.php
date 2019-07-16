<?php

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class TL_HelpScout
{

    use TL_Options;
    use TL_Debug_Logging;

    /**
     * @var String - the secret to verify requests from HelpScout
     * @since 0.1.0
     **/
    private $secret;

    /**
     * @var Boolean - whether our debug logging is activated
     * @since 0.1.0
     **/
    private $debug_mode;

    /**
     * @var Array - the current TrustedLogin settings
     * @since 0.1.0
     **/
    private $options;

    /**
     * @var Array - the default TrustedLogin settings
     * @since 0.1.0
     **/
    private $default_options;

    /**
     * @var stdClass - this helpdesk's settings
     * @since 0.1.0
     **/
    private $details;

    public function __construct()
    {

        $this->details = (object) array(
            'name' => __('HelpScout', 'tl-support-side'),
            'slug' => 'helpscout',
            'version' => '0.1.0',
        );

        $this->tls_settings_set_defaults();

        $this->secret = $this->tls_settings_get_value('tls_' . $this->details->slug . '_secret');

        $this->debug_mode = $this->tls_settings_is_toggled('tls_debug_enabled');

        add_action('admin_init', array($this, 'add_extra_settings'));

        add_action('wp_ajax_' . $this->details->slug . '_webhook', array($this, 'webhook_endpoint'));
        add_action('wp_ajax_nopriv_' . $this->details->slug . '_webhook', array($this, 'webhook_endpoint'));

    }

    public function has_secret()
    {
        if (!isset($this->secret) || empty($this->secret)) {
            return false;
        }

        return true;
    }

    public function add_extra_settings()
    {

        add_settings_field(
            'tls_' . $this->details->slug . '_secret',
            $this->details->name . ' ' . __('Secret Key', 'tl-support-side'),
            array($this, 'helpscout_secret_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_' . $this->details->slug . '_url',
            $this->details->name . ' ' . __('Webhook URL', 'tl-support-side'),
            array($this, 'helpscout_url_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

    }

    public function helpscout_secret_field_render()
    {

        $this->tls_settings_render_input_field('tls_' . $this->details->slug . '_secret', 'password', false);

    }

    public function helpscout_url_field_render()
    {

        $url = add_query_arg('action', $this->details->slug . '_webhook', admin_url('admin-ajax.php'));
        echo '<input readonly="readonly" type="text" value="' . $url . '" class="regular-text ltr">';

    }

    public function webhook_endpoint()
    {

        $signature = (isset($_SERVER['X-HELPSCOUT-SIGNATURE'])) ? $_SERVER['X-HELPSCOUT-SIGNATURE'] : null;
        $data = file_get_contents('php://input');

        if (!$this->helpscout_verify_source($data, $signature)) {

            wp_send_json_error(array('message' => 'Unauthorized'), 401);
        }

        $licenses = array();

        $this->dlog("data: $data", __METHOD__);

        $data_obj = json_decode($data, false);

        $email = sanitize_email($data_obj->customer->email);

        if ($this->is_edd_store() && !empty($email)) {

            if ($this->has_edd_licensing()) {
                $licenses = $this->edd_get_licenses($email);
            }

        }

        $saas_auth = $this->tls_settings_get_value('tls_account_key');

        if (!$saas_auth) {
            $error = __('Please make sure the TrustedLogin API Key setting is entered.', 'tl-support-side');
            $this->dlog($error, __METHOD__);
            wp_send_json_error(array('message' => $error));
        }

        $saas_attr = (object) array('type' => 'saas', 'auth' => $saas_auth, 'debug_mode' => $this->debug_mode);
        $saas_api = new TL_API_Handler($saas_attr);

        $for_vault = array();
        $item_html = '';

        $html_template = '<ul class="c-sb-list c-sb-list--two-line">%1$s</ul>';
        $item_template = '<li class="c-sb-list-item"><a href="%1$s">%2$s %3$s</a></li>';
        $no_items_template = '<li class="c-sb-list-item">%1$s</li>';
        $url_endpoint = apply_filters('trustedlogin_redirect_endpoint', 'trustedlogin');

        /**
         * Filter: allow for other addons to generate the licenses array
         *
         * @since 0.6.0
         * @param Array $licenses (
         *   @see $response from saas_api to /Sites/?accessKey=<accessKey>
         * )
         * @param String $email
         * @return Array
         **/
        $licenses = apply_filters('trusted_login_get_licenses', $licenses, $email);

        foreach ($licenses as $license) {

            // check licenses for TrustedLogin Sites via SaaS app.

            $endpoint = 'Sites/?accessKey=' . $license;
            $method = 'GET';
            $body = null;

            /**
             * Expected result
             *
             * @var $response [
             *   String $keyStoreID - the id of the secret in Key Store
             *   String $siteURL - the site the secret was generated on
             *   String $accountNamespace - the namepsace of the Vendor in TL SaaS
             *   String $deleteKey - the token to use to Revoke Site from SaaS
             * ]
             **/
            $response = $saas_api->api_prepare($endpoint, $data, $method);

            $this->dlog("Response: " . print_r($response, true), __METHOD__);

            if ($response) {
                if (isset($response->keyStoreID) && isset($response->accountNamespace)) {
                    $for_vault[] = (array) $response;
                }
            }

        } // foreach($licenses)

        if (!empty($for_vault)) {
            foreach ($for_vault as $vault_set) {
                $item_html .= sprintf(
                    $item_template,
                    esc_url(site_url('/' . $url_endpoint . '/' . $vault_set['keyStoreID'])),
                    __('TrustedLogin to', 'tl-support-side'),
                    esc_url($vault_set['siteURL'])
                );
            }
        }

        if (empty($item_html)) {
            $item_html = sprintf(
                $no_items_template,
                __('No TrustedLogin sessions authorized for this user.', 'tl-support-side')
            );
        }

        $return_html = sprintf($html_template, $item_html);

        wp_send_json_success(array('html' => $return_html));

    }

    public function has_edd_licensing()
    {
        return function_exists('edd_software_licensing');
    }

    public function edd_get_licenses($email)
    {

        $keys = array();
        $_u = get_user_by('email', $email);

        if ($_u) {

            $licenses = edd_software_licensing()->get_license_keys_of_user($_u->ID, 0, 'all', true);

            foreach ($licenses as $license) {
                $children = edd_software_licensing()->get_child_licenses($license->ID);
                if ($children) {
                    foreach ($children as $child) {
                        $keys[] = edd_software_licensing()->get_license_key($child->ID);
                    }
                }

                $keys[] = edd_software_licensing()->get_license_key($license->ID);
            }
        }

        return (!empty($keys)) ? $keys : false;
    }

    public function helpscout_verify_source($data, $signature)
    {
        if (!$this->has_secret() || is_null($signature)) {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha1', $data, $this->secret, true));
        return $signature == $calculated;
    }

    /**
     * Helper function: Check if the current site is an EDD store
     *
     * @since 0.2.0
     * @return Boolean
     **/
    public function is_edd_store()
    {
        return class_exists('Easy Digital Downloads');
    }

}

$hl = new TL_HelpScout();
