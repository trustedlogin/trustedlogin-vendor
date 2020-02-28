<?php
/**
 * Class: TrustedLogin Settings
 *
 * @package trustedlogin-vendor
 * @version 0.2.0
 **/

class TrustedLogin_Settings {

	/**
	 * @since 0.1.0
	 **@var Boolean - whether or not to save a local text log
	 */
	protected $debug_mode;

	/**
	 * @since 0.1.0
	 **@var Array - the default settings for our plugin
	 */
	private $default_options;

	/**
	 * @since 0.1.0
	 **@see Filter: trustedlogin_menu_location
	 * @var String - where the TrustedLogin settings should sit in menu.
	 */
	private $menu_location;

	/**
	 * @var Array - current site's TrustedLogin settings
	 * @since 0.1.0
	 **/
	private $options;

	/**
	 * @since 0.1.0
	 **@var String - the x.x.x value of the current plugin version. Used for versioning of settings page assets.
	 */
	private $plugin_version;

	public function __construct( $plugin_version = null ) {

		$this->set_defaults();

		$this->plugin_version = ( is_null( $plugin_version ) ) ? '0.0.0' : $plugin_version;

	}

	public function admin_init() {

		/**
		 * Filter: Where in the menu the TrustedLogin Options should go.
		 * Added to allow devs to move options item under 'Settings' menu item in wp-admin to keep things neat.
		 *
		 * @since 0.1.0
		 *
		 * @param String either 'main' or 'submenu'
		 **/
		$this->menu_location = apply_filters( 'trustedlogin_menu_location', 'main' );

		add_action( 'admin_menu', array( $this, 'tls_settings_add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'tls_settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'tls_settings_scripts' ) );

	}

	public function debug_mode_enabled() {

		return (bool) $this->debug_mode;

	}

	public function set_defaults() {
		if ( property_exists( $this, 'default_options' ) ) {
			$this->default_options = apply_filters( 'trustedlogin_default_settings', array(
				'tls_account_id'       => "",
				'tls_account_key'      => "",
				'tls_public_key'       => "",
				'tls_helpdesk'         => array(),
				'tls_approved_roles'   => array( 'administrator' ),
				'tls_debug_enabled'    => 'on',
				'tls_output_audit_log' => 'off',
			) );
		}
		if ( property_exists( $this, 'menu_location' ) ) {
			$this->menu_location = 'main'; // change to 'submenu' to add under Setting tab
		}

		$this->options = get_option( 'tls_settings', $this->default_options );

		$this->debug_mode = $this->tls_settings_is_toggled( 'tls_debug_enabled' );
	}

	public function tls_settings_add_admin_menu() {

		$args = array(
			'submenu_page' => 'options-general.php',
			'menu_title'   => __( 'TrustedLogin Settings', 'tl-support-side' ),
			'page_title'   => __( 'TrustedLogin', 'tl-support-side' ),
			'capabilities' => 'manage_options',
			'slug'         => 'tls_settings',
			'callback'     => array( $this, 'tls_settings_options_page' ),
			'icon'         => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDEzOS4zIDIyMC43IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAxMzkuMyAyMjAuNyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48c3R5bGUgdHlwZT0idGV4dC9jc3MiPi5zdDB7ZmlsbDojMDEwMTAxO308L3N0eWxlPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im00Mi4yIDY5Ljd2LTIxLjZjMC0xNS4yIDEyLjMtMjcuNSAyNy41LTI3LjUgMTUuMSAwIDI3LjUgMTIuMyAyNy41IDI3LjV2MjEuNmM3LjUgMC41IDE0LjUgMS4yIDIwLjYgMi4xdi0yMy43YzAtMjYuNS0yMS42LTQ4LjEtNDguMS00OC4xLTI2LjYgMC00OC4yIDIxLjYtNDguMiA0OC4xdjIzLjdjNi4yLTAuOSAxMy4yLTEuNiAyMC43LTIuMXoiLz48cmVjdCBjbGFzcz0ic3QwIiB4PSIyMS41IiB5PSI2Mi40IiB3aWR0aD0iMjAuNiIgaGVpZ2h0PSIyNS41Ii8+PHJlY3QgY2xhc3M9InN0MCIgeD0iOTcuMSIgeT0iNjIuNCIgd2lkdGg9IjIwLjYiIGhlaWdodD0iMjUuNSIvPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im02OS43IDc1LjNjLTM4LjUgMC02OS43IDQuOS02OS43IDEwLjh2NTRoNTYuOXYtOS44YzAtMi41IDEuOC0zLjYgNC0yLjNsMjguMyAxNi40YzIuMiAxLjMgMi4yIDMuMyAwIDQuNmwtMjguMyAxNi40Yy0yLjIgMS4zLTQgMC4yLTQtMi4zdi05LjhoLTU2Ljl2MTIuN2MwIDM4LjUgNDcuNSA1NC44IDY5LjcgNTQuOHM2OS43LTE2LjMgNjkuNy01NC44di03OS45Yy0wLjEtNS45LTMxLjMtMTAuOC02OS43LTEwLjh6bTAgMTIyLjRjLTIzIDAtNDIuNS0xNS4zLTQ4LjktMzYuMmgxNC44YzUuOCAxMy4xIDE4LjkgMjIuMyAzNC4xIDIyLjMgMjAuNSAwIDM3LjItMTYuNyAzNy4yLTM3LjJzLTE2LjctMzcuMi0zNy4yLTM3LjJjLTE1LjIgMC0yOC4zIDkuMi0zNC4xIDIyLjNoLTE0LjhjNi40LTIwLjkgMjUuOS0zNi4yIDQ4LjktMzYuMiAyOC4yIDAgNTEuMSAyMi45IDUxLjEgNTEuMS0wLjEgMjguMi0yMyA1MS4xLTUxLjEgNTEuMXoiLz48L3N2Zz4=',
		);

		if ( 'submenu' === $this->menu_location ) {
			add_submenu_page( $args['submenu_page'], $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'] );
		} else {
			add_menu_page( $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'], $args['icon'] );
		}

	}

	public function tls_settings_init() {

		register_setting( 'TLS_plugin_options', 'tls_settings', ['sanitize_callback' => [ $this, 'verify_api_details'] ] );

		add_settings_section(
			'tls_options_section',
			__( 'Settings for how your site and support agents are connected to TrustedLogin', 'tl-support-side' ),
			array( $this, 'tls_settings_section_callback' ),
			'TLS_plugin_options'
		);

		add_settings_field(
			'tls_account_id',
			__( 'TrustedLogin Account ID ', 'tl-support-side' ),
			array( $this, 'tls_settings_account_id_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_account_key',
			__( 'TrustedLogin API Key ', 'tl-support-side' ),
			array( $this, 'tls_settings_account_key_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_public_key',
			__( 'TrustedLogin Public Key ', 'tl-support-side' ),
			array( $this, 'tls_settings_public_key_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_approved_roles',
			__( 'Which WP roles can automatically be logged into customer sites?', 'tl-support-side' ),
			array( $this, 'tls_settings_approved_roles_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_helpdesk',
			__( 'Which helpdesk software are you using?', 'tl-support-side' ),
			array( $this, 'tls_settings_helpdesks_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_debug_enabled',
			__( 'Enable debug logging?', 'tl-support-side' ),
			array( $this, 'tls_settings_debug_enabled_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

		add_settings_field(
			'tls_output_audit_log',
			__( 'Display Audit Log below?', 'tl-support-side' ),
			array( $this, 'tls_settings_output_audit_log_field_render' ),
			'TLS_plugin_options',
			'tls_options_section'
		);

	}

	/**
	 * Hooks into settings sanitization to verify API details
	 *
	 * Note: Although hooked up to `sanitize_callback`, this function does NOT sanitize data provided.
	 *
	 * @uses `add_settings_error()` to set an alert for verification failures/errors and success message when API creds verified.
	 *
	 * @since 0.9.1
	 *
	 * @param Array $input Data saved on Settings page.
	 *
	 * @return Array Output of sanitized data.
	 */
	public function verify_api_details( $input ){

		if ( ! isset( $_POST) || ! isset( $_POST['tls_settings'] ) ){
			return $input;
		}

		$api_creds_verified = false;

		try {

			$checks = array(
				'tls_account_key' => __('Private Key', 'trustedlogin'),
				'tls_account_id'  => __('Account ID', 'trustedlogin'),
				'tls_public_key'  => __('Public Key', 'trustedlogin'),
			);

			foreach ( $checks as $key => $title ){
				if ( !isset( $_POST['tls_settings'][$key] ) ){
					throw new Exception( sprintf( __('No %s provided.', 'trustedlogin'), $title ) );
				}
			}

			$account_id = intval( $_POST['tls_settings']['tls_account_id'] );
			$saas_auth  = sanitize_text_field( $_POST['tls_settings']['tls_account_key'] );
			$debug_mode = ( isset( $_POST['tls_settings']['tls_debug_enabled'] ) ) ? true : false;
			$public_key = sanitize_text_field( $_POST['tls_settings']['tls_public_key'] );

			$saas_attr = (object) array( 'type' => 'saas', 'auth' => $saas_auth, 'debug_mode' => $debug_mode );

			$saas_api  = new TL_API_Handler( $saas_attr );

			/**
	        * @var String  $saas_token  Additional SaaS Token for authenticating API queries.
	        * @see https://github.com/trustedlogin/trustedlogin-ecommerce/blob/master/docs/user-remote-authentication.md
	        **/
	        $saas_token  = hash( 'sha256', $public_key . $saas_auth );
	        $token_added = $saas_api->set_additional_header( 'X-TL-TOKEN', $saas_token );

	        if ( ! $token_added ){
	            $error = __( 'Error setting X-TL-TOKEN header', 'tl-support-side' );
	            $this->dlog( $error , __METHOD__ );
	            throw new Exception( $error );
	        }

	        $verified = $saas_api->verify( $account_id );

	        if ( is_wp_error( $verified ) ){
	        	throw new Exception( $verified->get_error_message() );
	        }

	        $api_creds_verified = true;

		} catch ( Exception $e ){

			$error = sprintf(
				__('Could not verify TrustedLogin credentials. %s', 'trustedlogin'),
				esc_html__( $e->getMessage() )
			);

			add_settings_error(
	            'TLS_plugin_options',
	            'trustedlogin_auth',
	            $error,
	            'error'
	        );
		}

		if ( $api_creds_verified ){
			add_settings_error(
	            'TLS_plugin_options',
	            'trustedlogin_auth',
	            __( 'TrustedLogin API credentials verified.', 'trustedlogin' ),
	            'updated'
	        );
		}

		return $input;
	}

	public function tls_settings_account_key_field_render() {

		$this->tls_settings_render_input_field( 'tls_account_key', 'password', true );

	}

	public function tls_settings_public_key_field_render() {

		$this->tls_settings_render_input_field( 'tls_public_key', 'text', true );

	}

	public function tls_settings_account_id_field_render() {
		$this->tls_settings_render_input_field( 'tls_account_id', 'text', true );
	}

	public function tls_settings_render_input_field( $setting, $type = 'text', $required = false ) {
		if ( ! in_array( $type, array( 'password', 'text' ) ) ) {
			$type = 'text';
		}

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : '';

		$set_required = ( $required ) ? 'required' : '';

		$output = '<input id="' . $setting . '" name="tls_settings[' . $setting . ']" type="' . $type . '" value="' . $value . '" class="regular-text ltr" ' . $set_required . '>';

		echo $output;
	}

	public function tls_settings_approved_roles_field_render() {

		$roles          = get_editable_roles();
		$selected_roles = $this->get_approved_roles();

		$select = "<select name='tls_settings[tls_approved_roles][]' id='tls_approved_roles' class='postform regular-text ltr' multiple='multiple' regular-text ltr>";

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

	public function tls_settings_helpdesks_field_render() {

		/**
		 * Filter: The array of TrustLogin supported HelpDesks
		 *
		 * @since 0.1.0
		 *
		 * @param Array ('slug'=>'Title')
		 **/
		$helpdesks = apply_filters( 'trustedlogin_supported_helpdesks', array(
			''          => array(
				'title'  => __( 'Select Your Helpdesk Software', 'tl-support-side' ),
				'active' => false
			),
			'helpscout' => array( 'title' => __( 'HelpScout', 'tl-support-side' ), 'active' => true ),
			'intercom'  => array( 'title' => __( 'Intercom', 'tl-support-side' ), 'active' => false ),
			'helpspot'  => array( 'title' => __( 'HelpSpot', 'tl-support-side' ), 'active' => false ),
			'drift'     => array( 'title' => __( 'Drift', 'tl-support-side' ), 'active' => false ),
			'gosquared' => array( 'title' => __( 'GoSquared', 'tl-support-side' ), 'active' => false ),
		) );

		$selected_helpdesk = $this->tls_settings_get_selected_helpdesk();

		$select = "<select name='tls_settings[tls_helpdesk][]' id='tls_helpdesk' class='postform regular-text ltr'>";

		foreach ( $helpdesks as $key => $helpdesk ) {

			if ( in_array( $key, $selected_helpdesk ) ) {
				$selected = "selected='selected'";
			} else {
				$selected = "";
			}

			$title = $helpdesk['title'];

			if ( ! $helpdesk['active'] && ! empty( $key ) ) {
				$title    .= ' (' . __( 'Coming Soon', 'tl-support-side' ) . ')';
				$disabled = 'disabled="disabled"';
			} else {
				$disabled = '';
			}

			$select .= "<option value='" . $key . "' " . $selected . " " . $disabled . ">" . $title . "</option>";

		}

		$select .= "</select>";

		echo $select;

	}

	public function tls_settings_debug_enabled_field_render() {

		$this->tls_settings_output_toggle( 'tls_debug_enabled' );

	}

	public function tls_settings_output_audit_log_field_render() {

		$this->tls_settings_output_toggle( 'tls_output_audit_log' );

	}

	public function tls_settings_output_toggle( $setting ) {

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : 'off';

		$select = '<label class="switch">
                    <input class="switch-input" name="tls_settings[' . $setting . ']" id="' . $setting . '" type="checkbox" ' . checked( $value, 'on', false ) . '/>
                    <span class="switch-label" data-on="On" data-off="Off"></span>
                    <span class="switch-handle"></span>
                </label>';
		echo $select;
	}

	public function tls_settings_section_callback() {
		do_action( 'trustedlogin_section_callback' );
	}

	public function tls_settings_options_page() {

		wp_enqueue_script( 'chosen' );
		wp_enqueue_style( 'chosen' );
		wp_enqueue_script( 'trustedlogin-settings' );
		wp_enqueue_style( 'trustedlogin-settings' );

		echo '<form method="post" action="options.php">';

		echo sprintf( '<h1>%1$s</h1>', __( 'TrustedLogin Settings', 'tl-support-side' ) );

		settings_errors( 'TLS_plugin_options' );

		do_action( 'trustedlogin_before_settings_sections' );

		settings_fields( 'TLS_plugin_options' );
		do_settings_sections( 'TLS_plugin_options' );

		do_action( 'trustedlogin_after_settings_sections' );

		submit_button();

		echo "</form>";

		do_action( 'trustedlogin_after_settings_form' );

	}

	public function tls_settings_scripts() {

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
	 * @return Mixed     The value of the setting, or false if it's not found.
	 **/
	public function get_setting( $setting_name ) {

		if ( empty( $setting_name ) ) {
			return new WP_Error( 'input-error', __( 'Cannot fetch empty setting name', 'trustedlogin' ) );
		}

		switch ( $setting_name ) {
			case 'approved_roles':
				return $this->tls_settings_get_selected_values( 'tls_approved_roles' );
				break;
			case 'helpdesk':
				return $this->tls_settings_get_selected_values( 'tls_helpdesk' );
				break;
			case 'debug_enabled':
				return $this->tls_settings_is_toggled( 'tls_debug_enabled' );
				break;
			default:
				return $value = ( array_key_exists( $setting_name, $this->options ) ) ? $this->options[ $setting_name ] : false;
		}

	}

	public function get_approved_roles() {
		return $this->tls_settings_get_selected_values( 'tls_approved_roles' );
	}

	public function tls_settings_get_selected_helpdesk() {
		return $this->tls_settings_get_selected_values( 'tls_helpdesk' );
	}

	public function tls_settings_get_selected_values( $setting ) {
		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : array();

		return maybe_unserialize( $value );
	}

	public function tls_settings_is_toggled( $setting ) {
		return ( array_key_exists( $setting, $this->options ) ) ? true : false;
	}

	public function tls_settings_get_value( $setting ) {
		return $value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : false;
	}

}