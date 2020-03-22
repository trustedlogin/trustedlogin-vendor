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
	 * @var Array - the default TrustedLogin settings
	 * @since 0.1.0
	 **/
	private $default_options;

	public function __construct() {

		$this->secret = '';//$this->tls_settings_get_value( 'tls_' . static::slug . '_secret' );

		$this->debug_mode = '';//$this->tls_settings_is_toggled( 'tls_debug_enabled' );

		add_filter( 'trustedlogin_supported_helpdesks', array( $this, 'add_supported_helpdesk' ) );

		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'add_extra_settings' ) );

		add_action( 'wp_ajax_' . static::slug . '_webhook', array( $this, 'webhook_endpoint' ) );
		add_action( 'wp_ajax_nopriv_' . static::slug . '_webhook', array( $this, 'webhook_endpoint' ) );

	}

	public function is_active() {

		$active_helpdesk = array();//$this->tls_settings_get_selected_helpdesk();

		return in_array( static::slug, $active_helpdesk, true );
	}

	public function add_supported_helpdesk( $helpdesks = array() ) {

		$helpdesks[ static::slug ] = array(
			'title' => static::name,
			'active' => static::is_active,
		);

		return $helpdesks;
	}

	public function add_extra_settings() {
	}

	public function webhook_endpoint() {

	}

}