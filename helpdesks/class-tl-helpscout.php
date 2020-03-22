<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class HelpScout extends HelpDesk {

	const name = 'Help Scout';

	const slug = 'helpscout';

	const version = '0.1.0';

	const is_active = true;

	private $debug_mode = true;

	private $secret = '';

	public function __construct() {
		parent::__construct();

		$this->secret = '';//$this->tls_settings_get_value('tls_' . self::slug . '_secret');
	}

	public function add_extra_settings() {

		add_settings_field(
			'tls_' . self::slug . '_secret',
			self::name . ' ' . __( 'Secret Key', 'tl-support-side' ),
			array( $this, 'secret_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_' . self::slug . '_url',
			self::name . ' ' . __( 'Webhook URL', 'tl-support-side' ),
			array( $this, 'url_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

	}

	public function secret_field_render()
    {

        $this->tls_settings_render_input_field('tls_' . self::slug . '_secret', 'password', false);

    }

    public function url_field_render()
    {
        $url = add_query_arg('action', self::slug . '_webhook', admin_url('admin-ajax.php'));

        echo '<input readonly="readonly" type="text" value="' . $url . '" class="regular-text widefat">';
    }

    public function webhook_endpoint() {

        $signature = (isset($_SERVER['X-HELPSCOUT-SIGNATURE'])) ? $_SERVER['X-HELPSCOUT-SIGNATURE'] : null;
        $data = file_get_contents('php://input');

        if (!$this->helpscout_verify_source($data, $signature)) {
        	#wp_send_json_error(array('message' => 'Unauthorized'), 401);
        }

        $licenses = array();

        $this->dlog("data: $data", __METHOD__);

        $data_obj = json_decode($data, false);

        $email = sanitize_email($data_obj->customer->email);

	    $email = 'zack@gravityview.co';

	    if ( empty( $email ) ) {
		    wp_send_json_error(array('message' => 'Unauthorized'), 401);
	    }

        $saas_auth = $this->tls_settings_get_value('tls_account_key');

        if ( ! $saas_auth ) {
	        $error = __( 'Please make sure the TrustedLogin API Key setting is entered.', 'tl-support-side' );
	        $this->dlog( $error, __METHOD__ );
	        wp_send_json_error( array( 'message' => $error ) );
        }


        $saas_attr = array('type' => 'saas', 'auth' => $saas_auth, 'debug_mode' => $this->debug_mode);
        $saas_api = new \TL_API_Handler($saas_attr);

	    // Get licenses
	    $license_generator = License_Generators::get_active();

	    $licenses = $license_generator->get_license_keys_by_email( $email );

	    /**
	     * Filter: allow for other addons to generate the licenses array
	     *
	     * @since 0.6.0
	     * @param array $licenses (
	     *   @see $response from saas_api to /Sites/?accessKey=<accessKey>
	     * )
	     * @param string $email
	     * @return Array
	     **/
	    $licenses = apply_filters('trusted_login_get_licenses', $licenses, $email);

        $for_vault = array();
        $item_html = '';

        $html_template = '<ul class="c-sb-list c-sb-list--two-line">%1$s</ul>';
        $item_template = '<li class="c-sb-list-item"><a href="%1$s">%2$s %3$s</a></li>';
        $no_items_template = '<li class="c-sb-list-item">%1$s</li>';

        foreach ( $licenses as $license ) {

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
                    esc_url(site_url('/' . Endpoint::redirect_endpoint . '/' . $vault_set['keyStoreID'])),
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


    public function helpscout_verify_source($data, $signature)
    {
        if (!$this->has_secret() || is_null($signature)) {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha1', $data, $this->secret, true));
        return $signature == $calculated;
    }

	public function has_secret() {

		if ( ! isset( $this->secret ) || empty( $this->secret ) ) {
			return false;
		}

		return true;
	}

}

$hl = new HelpScout();