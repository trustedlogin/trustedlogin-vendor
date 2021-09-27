<?php
/**
 * Adds support for the Help Scout helpdesk
 *
 * @package TrustedLogin\Vendor\HelpDesks
 */

namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @version 0.1.0
 */
class HelpScout extends HelpDesk {

	use Debug_Logging;

	const NAME = 'Help Scout';

	const SLUG = 'helpscout';

	const VERSION = '0.1.0';

	const IS_ACTIVE = true;

	/**
	 * The secret to verify requests from HelpScout
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected $secret;

	/**
	 * Whether our debug logging is activated
	 *
	 * @var boolean
	 * @since 0.1.0
	 */
	protected $debug_mode;

	/**
	 * Current TrustedLogin settings
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $options;

	/**
	 * Default TrustedLogin settings
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $default_options;

	/**
	 * This helpdesk's settings
	 *
	 * @var Settings
	 * @since 0.1.0
	 */
	protected $settings;

	/**
	 * HelpScout constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->secret     = $this->settings->get_setting( self::SLUG . '_secret' );
		$this->debug_mode = $this->settings->debug_mode_enabled();
	}

	/**
	 * Checks that the secret/api_key for helpscout is set in settings panel.
	 *
	 * @since 0.1.0
	 *
	 * @return bool  Whether the secret is set and not empty.
	 */
	public function has_secret() {

		if ( ! isset( $this->secret ) || empty( $this->secret ) ) {
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

		if ( self::SLUG !== $this->settings->get_setting( 'helpdesk' ) ) {
			return;
		}

		add_settings_field(
			'trustedlogin_vendor_' . self::SLUG . '_secret',
			self::NAME . ' ' . esc_html__( 'Secret Key', 'trustedlogin-vendor' ),
			array( $this, 'secret_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'trustedlogin_vendor_' . self::SLUG . '_url',
			// translators: %s is replaced with the name of the help desk.
			sprintf( esc_html__( '%s Callback URL', 'trustedlogin-vendor' ), self::NAME ),
			array( $this, 'url_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);
	}

	/**
	 * Renders the settings field for the helpdesk secret/api_key
	 */
	public function secret_field_render() {
		$this->settings->render_input_field( self::SLUG . '_secret', 'password', array() );
	}

	/**
	 * Renders the settings field for the helpdesk url
	 */
	public function url_field_render() {

		$url = add_query_arg( 'action', self::SLUG . '_webhook', admin_url( 'admin-ajax.php' ) );

		echo '<input readonly="readonly" type="text" value="' . esc_url( $url ) . '" class="regular-text widefat code">';
	}

	/**
	 * Generates the output for the helpscout widget.
	 *
	 * Checks the `$_SERVER` array for the signature and verifies the source before checking for licenses matching to users email.
	 *
	 * @since 0.1.0
	 * @since 0.9.2 - added the status of licenses to output
	 *
	 * @uses self::verify_request()
	 *
	 * @return void Sends JSON response back to an Ajax request via wp_send_json()
	 */
	public function webhook_endpoint() {

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

		if ( ! $data || ! $this->verify_request( $data, $signature ) ) {
			$error_text  = '<p class="red">' . esc_html__( 'Unauthorized.', 'trustedlogin-vendor' ) . '</p>';
			$error_text .= '<p>' . esc_html__( 'Verify your site\'s TrustedLogin Settings match the Help Scout widget settings.', 'trustedlogin-vendor' ) . '</p>';
			wp_send_json( array( 'html' => $error_text ), 401 );
		}

		$data_obj = json_decode( $data, false );

		if ( isset( $data_obj->customer->emails ) && is_array( $data_obj->customer->emails ) ) {
			$customer_emails = $data_obj->customer->emails;
		} elseif ( isset( $data_obj->customer->email ) ) {
			$customer_emails = array ( $data_obj->customer->email );
		} else {
			$customer_emails = false;
		}

		if ( is_null( $data_obj ) || ! $customer_emails ) {
			$error_text  = '<p class="red">' . esc_html__( 'Unable to Process.', 'trustedlogin-vendor' ) . '</p>';
			$error_text .= '<p>' . esc_html__( 'The help desk sent corrupted customer data. Please try refreshing the page.', 'trustedlogin-vendor' ) . '</p>';
			wp_send_json( array( 'html' => $error_text ), 400 );
		}

		$return_html = $this->get_widget_response( $customer_emails );

		wp_send_json( array( 'html' => $return_html ), 200 );

	}

	/**
	 * Returns license keys associated with customer email addresses.
	 *
	 * @todo Move to using License_Generator class.
	 *
	 * @param array $customer_emails Array of email addresses Help Scout associates with the customer.
	 *
	 * @return array Array of license keys associated with the passed emails.
	 */
	private function get_licenses_by_emails( $customer_emails ) {

		$licenses = array();
		foreach ( $customer_emails as $customer_email ) {
			$email    = sanitize_email( $customer_email );

			$_licenses_for_email = get_transient( 'trustedlogin_licenses_' . md5( $email ) );

			if ( false === $_licenses_for_email ) {
				$_licenses_for_email = $this->edd_get_licenses( $email );
			}

			if ( ! empty( $_licenses_for_email ) ) {

				set_transient( 'trustedlogin_licenses_' . md5( $email ), $_licenses_for_email, DAY_IN_SECONDS );

				$licenses = array_merge( $licenses, $_licenses_for_email );
			}
		}

		/**
		 * Filter: allow for other addons to generate the licenses array
		 *
		 * @since 0.6.0
		 *
		 * @param \EDD_SL_License[]|false $licenses
		 * @param string $email
		 *
		 * @return array
		 */
		return apply_filters( 'trustedlogin/vendor/customers/licenses', $licenses, $customer_emails );
	}

	/**
	 * @param array $customer_emails
	 *
	 * @return string
	 */
	private function get_widget_response( $customer_emails ) {

		$licenses = $this->get_licenses_by_emails( $customer_emails );

		$account_id = $this->settings->get_setting( 'account_id' );
		$private_key  = $this->settings->get_private_key();
		$api_key = $this->settings->get_setting( 'api_key' );

		if ( ! $private_key || ! $api_key ) {
			$error = esc_html__( 'TrustedLogin has not been properly configured: both the API Key and Private Key must be entered.', 'trustedlogin-vendor' );

			$this->log( $error, __METHOD__ );

			// translators: %s is replaced with the domain name of the website
			$error = sprintf( '<h4 class="red">%s</h4><p><a href="%s">%s</a></p>',
				$error,
				esc_url( admin_url( 'admin.php?page=trustedlogin_vendor' ) ),
				sprintf( esc_html__( 'Fix the issue on %s', 'trustedlogin-vendor' ), get_site_url() )
			);

			return $error;
		}

		$saas_attr = array(
			'type'       => 'saas',
			'private_key' => $private_key,
			'debug_mode' => $this->debug_mode,
		);

		$saas_api = new API_Handler( $saas_attr );

		$item_html = '';

		/**
		 * Filter: Allows for changing the html output of the wrapper html elements.
		 *
		 * @param string $html
		 */
		$html_template = apply_filters(
			'trustedlogin/vendor/helpdesk/' . self::SLUG . '/template/wrapper',
			'<ul class="c-sb-list c-sb-list--two-line">%1$s</ul>'.
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . SiteKey_Login::PAGE_SLUG ) ) . '"><i class="icon-gear"></i>' . esc_html__( 'Go to Access Key Log-In', 'trustedlogin-vendor' ) . '</a>'
		);

		/**
		 * Filter: Allows for changing the html output of the individual items html elements.
		 *
		 * @param string $html
		 */
		$item_template = apply_filters(
			'trustedlogin/vendor/helpdesk/' . self::SLUG . '/template/item',
			'<li class="c-sb-list-item"><span class="c-sb-list-item__label">%4$s <span class="c-sb-list-item__text"><a href="%1$s" target="_blank" title="%3$s"><i class="icon-pointer"></i> %2$s</a></span></span></li>'
		);

		/**
		 * Filter: Allows for changing the html output of the html elements when no items found.
		 *
		 * @param string $html
		 */
		$no_items_template = apply_filters(
			'trustedlogin/vendor/helpdesk/' . self::SLUG . '/template/no-items',
			'<li class="c-sb-list-item">%1$s</li>'
		);

		$endpoint = 'accounts/' . $account_id . '/sites/';
		$method   = 'POST';
		$data     = array( 'searchKeys' => array() );

		$statuses = array();

		foreach ( $licenses as $license ) {

			// We look up the licenses by their hash, not plaintext
			$license_hash = hash( 'sha256', $license->key );

			if ( ! in_array( $license_hash, $data['searchKeys'], true ) ) {
				$data['searchKeys'][]      = $license_hash;
			}

			$statuses[ $license_hash ] = $license->status;
		} // foreach($licenses)

		if ( ! empty( $data['searchKeys'] ) ) {

			/**
			 * Expected result
			 *
			 * @var array|\WP_Error $response [
			 *   "<license_key>" => [ <secrets> ]
			 * ]
			 */
			$response = $saas_api->call( $endpoint, $data, $method );

			if ( is_wp_error( $response ) ) {
				$item_html = $response->get_error_message();
			} else {

				$this->log( 'Response: ' . print_r( $response, true ), __METHOD__ );

				if ( ! empty( $response ) ) {
					foreach ( $response as $key => $secrets ) {

						if ( ! is_array( $secrets ) ) {
							continue;
						}

						$secrets_reversed = array_reverse( $secrets, true );

						foreach ( $secrets_reversed as $secret ) {

							$url = $this->build_action_url( 'support_redirect', $secret );

							if ( is_wp_error( $url ) ) {
								$this->log( 'Error building item HTML. ' . $url->get_error_code() . ': ' . $url->get_error_message() );
								continue;
							}

							$item_html .= sprintf(
								$item_template,
								$url,
								esc_html__( 'Access Website', 'trustedlogin-vendor' ),
								sprintf( esc_html__( 'Access Key: %s', 'trustedlogin-vendor' ), $key ),
								sprintf( esc_html__( 'License is %s', 'trustedlogin-vendor' ), ucwords( esc_html( $statuses[ $key ] ) ) )
							);
						}
					}
				}
			}

			$this->log( 'item_html: ' . $item_html, __METHOD__ );

		} else {

			$this->log( 'No license keys found for email ' . esc_attr( $email ), __METHOD__ );

		}

		if ( empty( $item_html ) ) {
			$item_html = sprintf(
				$no_items_template,
				esc_html__( 'No TrustedLogin sessions authorized for this user.', 'trustedlogin-vendor' )
			);
		}

		return sprintf( $html_template, $item_html );
	}

	/**
	 * Checks if Easy Digital Downloads Licensing is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @return bool  Whether the `edd_software_licensing` function exists.
	 */
	public function has_edd_licensing() {
		return function_exists( 'edd_software_licensing' );
	}

	/**
	 * Gets any EDD licenses attached to an email address
	 *
	 * @since 0.1.0
	 *
	 * @param string $email The email to check for EDD licenses.
	 * @param array  $licenses Array of license keys
	 *
	 * @return \EDD_SL_License[]|false  Array of licenses or false if none are found.
	 */
	public function edd_get_licenses( $email ) {

		if ( ! function_exists( 'EDD' ) ) {
			$this->log( 'EDD is not loaded.', __METHOD__ );
			return false;
		}

		// EDD exists but somehow isn't instantiated.
		if( ! class_exists( '\EDD_Customer' ) ) {
			EDD();
		}

		// Sanity check.
		if( ! class_exists( '\EDD_Customer' ) ) {
			$this->log( 'EDD_Customer is not found.', __METHOD__ );
			return false;
		}

		$Customer = new \EDD_Customer( $email );

		if ( ! $Customer ) {
			$this->log( 'No customer exists for email.', __METHOD__ );
			return false;
		}

		$edd_licenses = edd_software_licensing()->get_license_keys_of_user( $Customer->user_id );

		$licenses = array();

		foreach ( $edd_licenses as $license ) {
			$children = edd_software_licensing()->get_child_licenses( $license->ID );
			if ( $children ) {
				foreach ( $children as $child ) {
					$licenses[] = edd_software_licensing()->get_license( $child->ID );
				}
			}

			$licenses[] = edd_software_licensing()->get_license( $license->ID );
		}

		return $licenses;
	}

	/**
	 * Verifies the source of the Widget AJAX request is from Help Scout
	 *
	 * @since 0.1.0
	 *
	 * @param string $data provided via `PHP://input`.
	 * @param string $signature provided via `$_SERVER` attribute.
	 *
	 * @return bool  if the calculated hash matches the signature provided.
	 */
	private function verify_request( $data, $signature = null ) {

		if ( ! $this->has_secret() ) {
			$this->log( 'No secret is set.', __METHOD__ );

			return false;
		}

		if ( is_null( $signature ) ) {
			$this->log( 'No signature provided. Here is the $_SERVER output' . print_r( $_SERVER, true ), __METHOD__ );

			return false;
		}

		$calculated = base64_encode( hash_hmac( 'sha1', $data, $this->secret, true ) );

		return hash_equals( $signature, $calculated );
	}

	/**
	 * Checks if the current site is an EDD store
	 *
	 * @since 0.2.0
	 * @return Boolean
	 */
	public function is_edd_store() {
		return function_exists( 'edd' );
	}

}

$hl = new HelpScout();
