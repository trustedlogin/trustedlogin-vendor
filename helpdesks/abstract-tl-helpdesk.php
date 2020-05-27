<?php

namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
abstract class HelpDesk {

	use Debug_Logging;

	/**
	 * @var string The secret to verify requests from HelpScout
	 * @since 0.1.0
	 **/
	private $secret;

	/**
	 * @var bool Whether our debug logging is activated
	 * @since 0.1.0
	 **/
	private $debug_mode;

	/**
	 * @var array The current TrustedLogin settings
	 * @since 0.1.0
	 **/
	private $options;

	/**
	 * @var array - the default TrustedLogin settings
	 * @since 0.1.0
	 **/
	private $default_options;

	public function __construct() {

		add_filter( 'trustedlogin_supported_helpdesks', array( $this, 'add_supported_helpdesk' ) );

		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'add_extra_settings' ) );

		add_action( 'wp_ajax_' . static::SLUG . '_webhook', array( $this, 'webhook_endpoint' ) );
		add_action( 'wp_ajax_nopriv_' . static::SLUG . '_webhook', array( $this, 'webhook_endpoint' ) );

	}

	public function is_active() {

		$active_helpdesk = array();//$this->tls_settings_get_selected_helpdesk();

		return in_array( static::SLUG, $active_helpdesk, true );
	}

	/**
	 * @param array $helpdesks
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

	public function add_extra_settings() {
	}

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
