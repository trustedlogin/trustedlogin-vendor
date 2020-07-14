<?php
/**
 * Defines the generic help desk functionality
 *
 * @package TrustedLogin\Vendor
 */

namespace TrustedLogin\Vendor;

/**
 * Class HelpDesk
 *
 * @package TrustedLogin\Vendor
 */
abstract class HelpDesk {

	use Debug_Logging;

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
	 * @var bool
	 * @since 0.1.0
	 */
	protected $debug_mode;

	/**
	 * The current TrustedLogin settings
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $options;

	protected $settings;

	/**
	 * The default TrustedLogin settings
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private $default_options;

	/**
	 * HelpDesk constructor.
	 */
	public function __construct() {

		$this->settings   = new Settings();

		add_filter( 'trustedlogin/vendor/settings/helpdesks', array( $this, 'add_supported_helpdesk' ) );

		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'add_extra_settings' ) );

		add_action( 'wp_ajax_' . static::SLUG . '_webhook', array( $this, 'webhook_endpoint' ) );
		add_action( 'wp_ajax_nopriv_' . static::SLUG . '_webhook', array( $this, 'webhook_endpoint' ) );

	}

	/**
	 * Returns whether the current help desk is active
	 *
	 * @return bool
	 */
	public function is_active() {
		return static::SLUG === $this->settings->get_setting( 'helpdesk' );
	}

	/**
	 * Add a help desk to the list of supported options
	 *
	 * @param array $helpdesks Array of active help desks {
	 *   @type string $title Name of the help desk
	 *   @type bool   $active Whether the help desk is enabled
	 * }
	 *
	 * @return array
	 */
	public function add_supported_helpdesk( $helpdesks = array() ) {

		$helpdesks[ static::SLUG ] = array(
			'title'  => static::NAME,
			'active' => static::IS_ACTIVE,
		);

		return $helpdesks;
	}

	/**
	 * Add settings for each help desk using {@see add_settings_field()}
	 */
	public function add_extra_settings() {
	}

	/**
	 * Override validation of requests sent by the help desk in-app widgets
	 */
	public function webhook_endpoint() {
	}

	/**
	 * Builds a URL for helpdesk request and redirect actions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action What action the link should do. eg 'support_redirect'.
	 * @param string $access_key (Optional) The key for the access being requested.
	 *
	 * @return string The url with GET variables.
	 */
	public function build_action_url( $action, $access_key = '' ) {

		if ( empty( $action ) ) {
			return new \WP_Error( 'variable-missing', 'Cannot build helpdesk action URL without a specified action' );
		}

		$endpoint = Endpoint::REDIRECT_ENDPOINT;

		$args = array(
			$endpoint  => 1,
			'action'   => $action,
			'provider' => static::SLUG,
		);

		if ( $access_key ) {
			$args['ak'] = $access_key;
		}

		$url = add_query_arg( $args, site_url() );

		return esc_url( $url );
	}

}
