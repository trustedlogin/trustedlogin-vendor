<?php
/**
 * Class: TrustedLogin Settings
 *
 * @package trustedlogin-vendor
 * @version 0.2.0
 **/

namespace TrustedLogin\Vendor;

use const TRUSTEDLOGIN_PLUGIN_VERSION;
use function selected;

class Settings {

	/**
	 * @var boolean $debug_mode Whether or not to save a local text log
	 * @since 0.1.0
	 */
	protected $debug_mode;

	/**
	 * @var array $default_options The default settings for our plugin
	 * @since 0.1.0
	 */
	private $default_options = array(
		'account_id'       => '',
		'account_key'      => '',
		'public_key'       => '',
		'helpdesk'         => array(),
		'approved_roles'   => array( 'administrator' ),
		'debug_enabled'    => 'on',
		'output_audit_log' => 'off',
	);

	/**
	 * @var string $menu_location Where the TrustedLogin settings should sit in menu. Options: 'main', or 'submenu' to add under Setting tab
	 * @see Filter: trustedlogin_menu_location
	 */
	private $menu_location = 'main';

	/**
	 * @var array Current site's TrustedLogin settings
	 * @since 0.1.0
	 **/
	private $options;

	/**
	 * @var string $plugin_version Used for versioning of settings page assets.
	 * @since 0.1.0
	 */
	private $plugin_version;

	public function __construct() {

		$this->set_defaults();

		$this->plugin_version = TRUSTEDLOGIN_PLUGIN_VERSION;

		$this->add_hooks();
	}

	public function add_hooks() {

		/**
		 * Filter: Where in the menu the TrustedLogin Options should go.
		 * Added to allow devs to move options item under 'Settings' menu item in wp-admin to keep things neat.
		 *
		 * @since 0.1.0
		 *
		 * @param String either 'main' or 'submenu'
		 **/
		$this->menu_location = apply_filters( 'trustedlogin_menu_location', 'main' );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

	}

	public function debug_mode_enabled() {

		return (bool) $this->debug_mode;

	}

	public function set_defaults() {

		$this->default_options = apply_filters( 'trustedlogin_default_settings', $this->default_options );

		$this->options = get_option( 'trustedlogin_vendor', $this->default_options );

		$this->debug_mode = $this->setting_is_toggled( 'debug_enabled' );
	}

	public function add_admin_menu() {

		$args = array(
			'submenu_page' => 'options-general.php',
			'menu_title'   => __( 'TrustedLogin Settings', 'trustedlogin-vendor' ),
			'page_title'   => __( 'TrustedLogin', 'trustedlogin-vendor' ),
			'capabilities' => 'manage_options',
			'slug'         => 'trustedlogin_vendor',
			'callback'     => array( $this, 'settings_options_page' ),
			'icon'         => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDEzOS4zIDIyMC43IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAxMzkuMyAyMjAuNyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48c3R5bGUgdHlwZT0idGV4dC9jc3MiPi5zdDB7ZmlsbDojMDEwMTAxO308L3N0eWxlPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im00Mi4yIDY5Ljd2LTIxLjZjMC0xNS4yIDEyLjMtMjcuNSAyNy41LTI3LjUgMTUuMSAwIDI3LjUgMTIuMyAyNy41IDI3LjV2MjEuNmM3LjUgMC41IDE0LjUgMS4yIDIwLjYgMi4xdi0yMy43YzAtMjYuNS0yMS42LTQ4LjEtNDguMS00OC4xLTI2LjYgMC00OC4yIDIxLjYtNDguMiA0OC4xdjIzLjdjNi4yLTAuOSAxMy4yLTEuNiAyMC43LTIuMXoiLz48cmVjdCBjbGFzcz0ic3QwIiB4PSIyMS41IiB5PSI2Mi40IiB3aWR0aD0iMjAuNiIgaGVpZ2h0PSIyNS41Ii8+PHJlY3QgY2xhc3M9InN0MCIgeD0iOTcuMSIgeT0iNjIuNCIgd2lkdGg9IjIwLjYiIGhlaWdodD0iMjUuNSIvPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im02OS43IDc1LjNjLTM4LjUgMC02OS43IDQuOS02OS43IDEwLjh2NTRoNTYuOXYtOS44YzAtMi41IDEuOC0zLjYgNC0yLjNsMjguMyAxNi40YzIuMiAxLjMgMi4yIDMuMyAwIDQuNmwtMjguMyAxNi40Yy0yLjIgMS4zLTQgMC4yLTQtMi4zdi05LjhoLTU2Ljl2MTIuN2MwIDM4LjUgNDcuNSA1NC44IDY5LjcgNTQuOHM2OS43LTE2LjMgNjkuNy01NC44di03OS45Yy0wLjEtNS45LTMxLjMtMTAuOC02OS43LTEwLjh6bTAgMTIyLjRjLTIzIDAtNDIuNS0xNS4zLTQ4LjktMzYuMmgxNC44YzUuOCAxMy4xIDE4LjkgMjIuMyAzNC4xIDIyLjMgMjAuNSAwIDM3LjItMTYuNyAzNy4yLTM3LjJzLTE2LjctMzcuMi0zNy4yLTM3LjJjLTE1LjIgMC0yOC4zIDkuMi0zNC4xIDIyLjNoLTE0LjhjNi40LTIwLjkgMjUuOS0zNi4yIDQ4LjktMzYuMiAyOC4yIDAgNTEuMSAyMi45IDUxLjEgNTEuMS0wLjEgMjguMi0yMyA1MS4xLTUxLjEgNTEuMXoiLz48L3N2Zz4=',
		);

		if ( 'submenu' === $this->menu_location ) {
			add_submenu_page( $args['submenu_page'], $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'] );
		} else {
			add_menu_page( $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'], $args['icon'] );
		}

	}

	public function admin_init() {

		register_setting( 'trustedlogin_vendor_options', 'trustedlogin_vendor', array( 'sanitize_callback' => array( $this, 'verify_api_details' ) ) );

		add_settings_section(
			'trustedlogin_vendor_options_section',
			__( 'Settings for how your site and support agents are connected to TrustedLogin', 'trustedlogin-vendor' ),
			array( $this, 'section_callback' ),
			'trustedlogin_vendor_options'
		);

		add_settings_field(
			'account_id',
			__( 'TrustedLogin Account ID ', 'trustedlogin-vendor' ),
			array( $this, 'account_id_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'account_key',
			__( 'TrustedLogin API Key ', 'trustedlogin-vendor' ),
			array( $this, 'account_key_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'public_key',
			__( 'TrustedLogin Public Key ', 'trustedlogin-vendor' ),
			array( $this, 'public_key_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'approved_roles',
			__( 'Which WP roles can automatically be logged into customer sites?', 'trustedlogin-vendor' ),
			array( $this, 'approved_roles_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'trustedlogin_vendor_helpdesk',
			__( 'Which helpdesk software are you using?', 'trustedlogin-vendor' ),
			array( $this, 'helpdesks_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'trustedlogin_vendor_debug_enabled',
			__( 'Enable debug logging?', 'trustedlogin-vendor' ),
			array( $this, 'debug_enabled_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'trustedlogin_vendor_output_audit_log',
			__( 'Display Audit Log below?', 'trustedlogin-vendor' ),
			array( $this, 'output_audit_log_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

	}

	/**
	 * Hooks into settings sanitization to verify API details
	 *
	 * Note: Although hooked up to `sanitize_callback`, this function does NOT sanitize data provided.
	 *
	 * @since 0.9.1
	 *
	 * @uses `add_settings_error()` to set an alert for verification failures/errors and success message when API creds verified.
	 *
	 * @param Array $input Data saved on Settings page.
	 *
	 * @return Array Output of sanitized data.
	 */
	public function verify_api_details( $input ) {

		if ( ! isset( $_POST ) || ! isset( $_POST['trustedlogin_vendor'] ) ) {
			return $input;
		}

		$api_creds_verified = false;

		try {

			$checks = array(
				'account_key' => __( 'Private Key', 'trustedlogin' ),
				'account_id'  => __( 'Account ID', 'trustedlogin' ),
				'public_key'  => __( 'Public Key', 'trustedlogin' ),
			);

			foreach ( $checks as $key => $title ) {
				if ( ! isset( $_POST['tls_settings'][ $key ] ) ) {
					throw new Exception( sprintf( __( 'No %s provided.', 'trustedlogin' ), $title ) );
				}
			}

			$account_id = intval( $_POST['trustedlogin_vendor']['tls_account_id'] );
			$saas_auth  = sanitize_text_field( $_POST['trustedlogin_vendor']['account_key'] );
			$debug_mode = ( isset( $_POST['trustedlogin_vendor']['debug_enabled'] ) ) ? true : false;
			$public_key = sanitize_text_field( $_POST['trustedlogin_vendor']['public_key'] );

			$saas_attr = (object) array( 'type' => 'saas', 'auth' => $saas_auth, 'debug_mode' => $debug_mode );

			$saas_api = new TL_API_Handler( $saas_attr );

			/**
			 * @var string $saas_token Additional SaaS Token for authenticating API queries.
			 * @see https://github.com/trustedlogin/trustedlogin-ecommerce/blob/master/docs/user-remote-authentication.md
			 **/
			$saas_token  = hash( 'sha256', $public_key . $saas_auth );
			$token_added = $saas_api->set_additional_header( 'X-TL-TOKEN', $saas_token );

			if ( ! $token_added ) {
				$error = __( 'Error setting X-TL-TOKEN header', 'tl-support-side' );
				$this->dlog( $error, __METHOD__ );
				throw new Exception( $error );
			}

			$verified = $saas_api->verify( $account_id );

			if ( is_wp_error( $verified ) ) {
				throw new Exception( $verified->get_error_message() );
			}

			$api_creds_verified = true;

		} catch ( Exception $e ) {

			$error = sprintf(
				__( 'Could not verify TrustedLogin credentials. %s', 'trustedlogin' ),
				esc_html__( $e->getMessage() )
			);

			add_settings_error(
				'trustedlogin_vendor_options',
				'trustedlogin_auth',
				$error,
				'error'
			);
		}

		if ( $api_creds_verified ) {
			add_settings_error(
				'trustedlogin_vendor_options',
				'trustedlogin_auth',
				__( 'TrustedLogin API credentials verified.', 'trustedlogin' ),
				'updated'
			);
		}

		return $input;
	}

	public function account_key_field_render() {

		$this->render_input_field( 'trustedlogin_vendor_account_key', 'password', true );

	}

	public function public_key_field_render() {

		$this->render_input_field( 'trustedlogin_vendor_public_key', 'text', true );

	}

	public function account_id_field_render() {
		$this->render_input_field( 'trustedlogin_vendor_account_id', 'text', true );
	}

	public function render_input_field( $setting, $type = 'text', $required = false ) {
		if ( ! in_array( $type, array( 'password', 'text' ) ) ) {
			$type = 'text';
		}

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : '';

		$set_required = ( $required ) ? 'required' : '';

		$output = '<input id="' . esc_attr( $setting ) . '" name="trustedlogin_vendor[' . esc_attr( $setting ) . ']" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" class="regular-text ltr" ' . esc_attr( $set_required ) . '>';

		echo $output;
	}

	public function approved_roles_field_render() {

		$roles          = get_editable_roles();
		$selected_roles = $this->get_approved_roles();

		$select = "<select name='trustedlogin_vendor[approved_roles][]' id='trustedlogin_vendor_approved_roles' class='postform regular-text ltr' multiple='multiple' regular-text ltr>";

		foreach ( $roles as $role_slug => $role_info ) {

			if ( in_array( $role_slug, $selected_roles ) ) {
				$selected = "selected='selected'";
			} else {
				$selected = "";
			}
			$select .= "<option value='" . $role_slug . "' " . $selected . ">" . $role_info['name'] . "</option>";

		}

		$select .= "</select>";

		echo $select;

	}

	public function helpdesks_field_render() {

		/**
		 * Filter: The array of TrustLogin supported HelpDesks
		 *
		 * @since 0.1.0
		 *
		 * @param Array ('slug'=>'Title')
		 **/
		$helpdesks = apply_filters( 'trustedlogin_supported_helpdesks', array(
			''          => array(
				'title'  => __( 'Select Your Helpdesk Software', 'trustedlogin-vendor' ),
				'active' => false
			),
			'helpscout' => array( 'title' => __( 'HelpScout', 'trustedlogin-vendor' ), 'active' => true ),
			'intercom'  => array( 'title' => __( 'Intercom', 'trustedlogin-vendor' ), 'active' => false ),
			'helpspot'  => array( 'title' => __( 'HelpSpot', 'trustedlogin-vendor' ), 'active' => false ),
			'drift'     => array( 'title' => __( 'Drift', 'trustedlogin-vendor' ), 'active' => false ),
			'gosquared' => array( 'title' => __( 'GoSquared', 'trustedlogin-vendor' ), 'active' => false ),
		) );

		$selected_helpdesk = $this->get_setting( 'helpdesk' );

		$select = "<select name='trustedlogin_vendor[helpdesk][]' id='helpdesk' class='postform regular-text ltr'>";

		foreach ( $helpdesks as $key => $helpdesk ) {

			$selected = selected( $selected_helpdesk, $key, false );

			$title = $helpdesk['title'];

			if ( ! $helpdesk['active'] && ! empty( $key ) ) {
				$title    .= ' (' . __( 'Coming Soon', 'trustedlogin-vendor' ) . ')';
				$disabled = ' disabled="disabled"';
			} else {
				$disabled = '';
			}

			$select .= sprintf( '<option value="%s"%s%s>%s</option>', esc_attr( $key ), esc_attr( $selected ), esc_attr( $disabled ), esc_html( $title ) );

		}

		$select .= "</select>";

		echo $select;

	}

	public function debug_enabled_field_render() {

		$this->settings_output_toggle( 'trustedlogin_vendor_debug_enabled' );

	}

	public function output_audit_log_field_render() {

		$this->settings_output_toggle( 'trustedlogin_vendor_output_audit_log' );

	}

	public function settings_output_toggle( $setting ) {

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : 'off';

		$select = '<label class="switch">
                    <input class="switch-input" name="trustedlogin_vendor[' . $setting . ']" id="' . $setting . '" type="checkbox" ' . checked( $value, 'on', false ) . '/>
                    <span class="switch-label" data-on="On" data-off="Off"></span>
                    <span class="switch-handle"></span>
                </label>';
		echo $select;
	}

	public function section_callback() {
		do_action( 'trustedlogin_section_callback' );
	}

	public function settings_options_page() {

		wp_enqueue_script( 'chosen' );
		wp_enqueue_style( 'chosen' );
		wp_enqueue_script( 'trustedlogin-settings' );
		wp_enqueue_style( 'trustedlogin-settings' );

		echo '<form method="post" action="options.php">';

		echo sprintf( '<h1>%1$s</h1>', __( 'TrustedLogin Settings', 'trustedlogin-vendor' ) );

		settings_errors( 'trustedlogin_vendor_options' );

		do_action( 'trustedlogin_before_settings_sections' );

		settings_fields( 'trustedlogin_vendor_options' );

		do_settings_sections( 'trustedlogin_vendor_options' );

		do_action( 'trustedlogin_after_settings_sections' );

		submit_button();

		echo '</form>';

		do_action( 'trustedlogin_after_settings_form' );

	}

	public function register_scripts() {

		wp_register_style(
			'chosen',
			plugins_url( '/assets/chosen/chosen.min.css', dirname( __FILE__ ) )
		);
		wp_register_script(
			'chosen',
			plugins_url( '/assets/chosen/chosen.jquery.min.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			false,
			true
		);

		wp_register_style( 'trustedlogin-settings',
			plugins_url( '/assets/trustedlogin-settings.css', dirname( __FILE__ ) ),
			array(),
			$this->plugin_version
		);

		wp_register_script( 'trustedlogin-settings',
			plugins_url( '/assets/trustedlogin-settings.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$this->plugin_version,
			true
		);
	}

	/**
	 * Returns the value of a setting
	 *
	 * @since 0.2.0
	 *
	 * @param String $setting_name The name of the setting to get the value for
	 *
	 * @return mixed     The value of the setting, or false if it's not found.
	 **/
	public function get_setting( $setting_name ) {

		if ( empty( $setting_name ) ) {
			return new WP_Error( 'input-error', __( 'Cannot fetch empty setting name', 'trustedlogin' ) );
		}

		switch ( $setting_name ) {
			case 'approved_roles':
				return $this->get_selected_values( 'approved_roles' );
				break;
			case 'helpdesk':
				$helpdesk = $this->get_selected_values( 'helpdesk' );
				return empty( $helpdesk ) ? null : $helpdesk[0];
				break;
			case 'debug_enabled':
				return $this->setting_is_toggled( 'debug_enabled' );
				break;
			default:
				return $value = ( array_key_exists( $setting_name, $this->options ) ) ? $this->options[ $setting_name ] : false;
		}

	}

	public function get_approved_roles() {
		return $this->get_selected_values( 'approved_roles' );
	}

	public function get_selected_values( $setting ) {
		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : array();

		return maybe_unserialize( $value );
	}

	public function setting_is_toggled( $setting ) {
		return in_array( $setting, $this->options, true ) ? true : false;
	}

	public function settings_get_value( $setting ) {
		return $value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : false;
	}

}
