<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 */
class HelpScout extends HelpDesk {

    use Debug_Logging;

	const name = 'Help Scout';

	const slug = 'helpscout';

	const version = '0.1.0';

	const is_active = true;

    /**
     * @var string The secret to verify requests from HelpScout
     * @since 0.1.0
     **/
    private $secret;

    /**
     * @var boolean Whether our debug logging is activated
     * @since 0.1.0
     **/
    private $debug_mode;

    /**
     * @var array Current TrustedLogin settings
     * @since 0.1.0
     **/
    private $options;

    /**
     * @var array Default TrustedLogin settings
     * @since 0.1.0
     **/
    private $default_options;

    /**
     * @var stdClass This helpdesk's settings
     * @since 0.1.0
     **/
    private $details;

    public function __construct() {

	    $this->details = (object) array(
		    'name'    => __( 'Help Scout', 'tl-support-side' ),
		    'slug'    => 'helpscout',
		    'version' => '0.1.0',
	    );

	    $this->settings   = new Settings();
	    $this->secret     = $this->settings->get_setting( self::slug . '_secret' );
	    $this->debug_mode = $this->settings->debug_mode_enabled();

	    add_action( 'admin_init', array( $this, 'add_extra_settings' ) );

	    add_action( 'wp_ajax_' . self::slug . '_webhook', array( $this, 'webhook_endpoint' ) );
	    add_action( 'wp_ajax_nopriv_' . self::slug . '_webhook', array( $this, 'webhook_endpoint' ) );

    }

    /**
     * Checks that the secret/api_key for helpscout is set in settings panel.
     *
     * @since 0.1.0
     *
     * @return bool  Whether the secret is set and not empty.
     */
    public function has_secret(){
        if ( ! isset( $this->secret ) || empty( $this->secret )) {
            return false;
        }

        return true;
    }

    /**
     * Appends extra settings into the TrustedLogin plugin settings page.
     *
     * @since 0.1.0
     */
    public function add_extra_settings() {

	    $settings = new Settings();

	    if ( self::slug === $settings->get_setting( 'helpdesk' ) ) {

		    add_settings_field(
			    'trustedlogin_vendor_' . self::slug . '_secret',
			    self::name . ' ' . __( 'Secret Key', 'tl-support-side' ),
			    array( $this, 'secret_field_render' ),
			    'trustedlogin_vendor_options',
			    'trustedlogin_vendor_options_section'
		    );

		    add_settings_field(
			    'trustedlogin_vendor_' . self::slug . '_url',
			    sprintf( __( '%s Callback URL', 'tl-support-side' ), self::name ),
			    array( $this, 'url_field_render' ),
			    'trustedlogin_vendor_options',
			    'trustedlogin_vendor_options_section'
		    );

	    }
    }

    /**
     * Renders the settings field for the helpdesk secret/api_key
     */
    public function secret_field_render( $field ){
        $this->settings->render_input_field(self::slug . '_secret', 'password', false);
    }

    /**
     * Renders the settings field for the helpdesk url
     */
    public function url_field_render(){

        $url = add_query_arg('action', self::slug . '_webhook', admin_url('admin-ajax.php'));

        echo '<input readonly="readonly" type="text" value="' . esc_url( $url ) . '" class="regular-text widefat code">';

    }

    /**
     * Generates the output for the helpscout widget.
     *
     * Checks the `$_SERVER` array for the signature and verifies the source before checking for licenses matching to users email.
     *
     * @uses self::helpscout_verify_source()
     *
     * @since 0.1.0
     * @since 0.9.2 - added the status of licenses to output
     *
     * @return a JSON response back to an Ajax request.
     */
    public function webhook_endpoint(){

	    $signature = null;

	    if ( isset( $_SERVER['X-HELPSCOUT-SIGNATURE'] ) ) {
		    $signature = $_SERVER['X-HELPSCOUT-SIGNATURE'];
	    } elseif ( isset( $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ) ) {
		    $signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
	    } elseif ( function_exists( 'apache_request_headers' ) ) {
		    $headers = apache_request_headers();
		    if ( isset( $headers['X-HelpScout-Signature'] ) ) {
			    $signature = $headers['X-HelpScout-Signature'];
		    }
	    }

        $data = file_get_contents( 'php://input' );

        if ( ! $this->helpscout_verify_source( $data, $signature ) ) {
            wp_send_json( array(
            	'html' => '<p class="red">Unauthorized.</p><p>Verify your site\'s TrustedLogin Settings match the Help Scout widget settings.</p>'
            ), 401 );
        }

        $licenses = array();
        $data_obj = json_decode( $data, false );
        $email    = sanitize_email( $data_obj->customer->email );

        if ( false === ( $licenses = get_transient( 'trustedlogin_licenses_'.md5( $email ) ) ) ){

            if ( $this->is_edd_store() && ! empty( $email ) ) {

                if ( $this->has_edd_licensing() ) {
                    $licenses = $this->edd_get_licenses( $email );
                }

            }

            if ( $licenses ){
                set_transient( 'trustedlogin_licenses_'.md5( $email ), $licenses, DAY_IN_SECONDS );
            }

        }

        /**
         * Filter: allow for other addons to generate the licenses array
         *
         * @since 0.6.0
         * @param array $licenses [
         *   @var  object  $license [
         *     @var  string  status  The status of the license.
         *     @var  string  key     The license key.
         *   ]
         * ]
         * @param string $email
         * @return array
         **/
        $licenses = apply_filters( 'trustedlogin/vendor/customers/licenses', $licenses, $email );

        $account_id = $this->settings->get_setting( 'account_id' );
        $saas_auth  = $this->settings->get_setting( 'account_key' );
        $public_key = $this->settings->get_setting( 'public_key' );

        if ( ! $saas_auth || ! $public_key ) {
            $error = __( 'Please make sure the TrustedLogin API Key setting is entered.', 'tl-support-side' );
            $this->dlog( $error, __METHOD__ );
            wp_send_json_error( array( 'message' => $error ) );
        }

        $saas_attr = (object) array( 'type' => 'saas', 'auth' => $saas_auth, 'debug_mode' => $this->debug_mode );
        $saas_api = new API_Handler($saas_attr);

        $for_vault = array();
        $item_html = '';


        /**
         * Filter: Allows for changing the html output of the wrapper html elements.
         *
         * @param string  $html
         */
        $html_template = apply_filters( 
            'trustedlogin/vendor/helpdesk/'. self::slug. '/template/wrapper', 
            '<ul class="c-sb-list c-sb-list--two-line">%1$s</ul>'
        );

        /**
         * Filter: Allows for changing the html output of the individual items html elements.
         *
         * @param string  $html
         */
        $item_template = apply_filters( 
            'trustedlogin/vendor/helpdesk/'. self::slug. '/template/item', 
            '<li class="c-sb-list-item"><a href="%1$s">%2$s %3$s</a> (%4$s)</li>'
        );

        /**
         * Filter: Allows for changing the html output of the html elements when no items found.
         *
         * @param string  $html
         */
        $no_items_template = apply_filters( 
            'trustedlogin/vendor/helpdesk/'. self::slug. '/template/no-items', 
            '<li class="c-sb-list-item">%1$s</li>'
        );

        $endpoint = 'accounts/' . $account_id . '/sites/';
        $method   = 'GET';
        $data     = array( 'accessKeys' => array() );

        $statuses = array();

        foreach ( $licenses as $license ) {

            $data['accessKeys'][] = $license->key;
            $statuses[ $license->key ] = $license->status;

        } // foreach($licenses)

        if ( ! empty( $data['accessKeys']) ){

            /**
             * Expected result
             *
             * @var $response [
             *   "<license_key>" => [ <secrets> ]
             * ]
             **/
            $response = $saas_api->call( $endpoint, $data, $method );

            $this->dlog( "Response: " . print_r( $response, true ), __METHOD__ );

            if ( ! empty( $response ) ){
                foreach ( $response as $key => $secrets ){
                    foreach ( $secrets as $secret ){
                         $item_html .= sprintf(
                            $item_template,
                            $this->build_action_url( $secret ),
                            __( 'TrustedLogin for ', 'tl-support-side' ),
                            $key,
                            $statuses[ $key ]
                        );
                    }
                }
            }

            $this->dlog( "item_html: ". $item_html, __METHOD__ );

        } else {

            $this->dlog( "No accessKeys found. ", __METHOD__ );

        }

        if ( empty ( $item_html ) ) {
            $item_html = sprintf(
                $no_items_template,
                __( 'No TrustedLogin sessions authorized for this user.', 'tl-support-side' )
            );
        }

        $return_html = sprintf( $html_template, $item_html );

        wp_send_json( array('html' => $return_html ), 200 );

    }

    /**
     * Checks if Easy Digital Downloads Licensing is enabled.
     *
     * @since 0.1.0
     *
     * @return bool  Whether the `edd_software_licensing` function exists.
     */
    public function has_edd_licensing(){
        return function_exists('edd_software_licensing');
    }

    /**
     * Gets any EDD licenses attached to an email address
     *
     * @since 0.1.0
     *
     * @param  string  $email  The email to check for EDD licenses
     *
     * @return EDD_SL_License[]|false  Array of licenses or false if none are found.
     **/
    public function edd_get_licenses( $email ) {

        $licenses = array();
        $user     = get_user_by( 'email', $email );

        if ( $user ) {

            $licenses = edd_software_licensing()->get_license_keys_of_user( $user->ID, 0, 'all', true );

            foreach ( $licenses as $license ) {
                $children = edd_software_licensing()->get_child_licenses( $license->ID );
                if ( $children ) {
                    foreach ( $children as $child ) {
                        $licenses[] = edd_software_licensing()->get_license( $child->ID );
                    }
                }

                $licenses[] = edd_software_licensing()->get_license( $license->ID );
            }
        }

        return ( ! empty( $licenses ) ) ? $licenses : false;
    }

    /**
     * Verifies the source of the Widget AJAX request is from helpscout
     *
     * @since 0.1.0
     *
     * @param  string  $data provided via `PHP://input`.
     * @param  string  $signature provided via `$_SERVER` attribute.
     *
     * @return bool  if the calculated hash matches the signature provided.
     */
    public function helpscout_verify_source( $data, $signature ) {

	    if ( ! $this->has_secret() ) {
		    $this->dlog( 'No secret is set.', __METHOD__ );
		    return false;
	    }

	    if( is_null( $signature ) ) {
		    $this->dlog( 'No signature provided. Here is the $_SERVER output' . print_r( $_SERVER, true ), __METHOD__ );
		    return false;
	    }

	    $calculated = base64_encode( hash_hmac( 'sha1', $data, $this->secret, true ) );

	    return $signature == $calculated;
    }

    /**
     * Checks if the current site is an EDD store
     *
     * @since 0.2.0
     * @return Boolean
     **/
    public function is_edd_store()
    {
	    return function_exists( 'edd' );
    }

}

$hl = new HelpScout();
