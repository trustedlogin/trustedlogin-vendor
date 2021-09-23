<?php
/**
 * Class: TrustedLogin Settings
 *
 * @package trustedlogin-vendor
 * @version 0.2.0
 */

namespace TrustedLogin\Vendor;

use \WP_Error;
use \Exception;
use const TRUSTEDLOGIN_PLUGIN_VERSION;
use function selected;

class Settings {

	const SETTING_NAME = 'trustedlogin_vendor';

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
		'private_key'      => '',
		'public_key'       => '',
		'helpdesk'         => array( 'helpscout' ),
		'approved_roles'   => array( 'administrator' ),
		'debug_enabled'    => 'on',
		'enable_audit_log' => 'on',
	);

	/**
	 * @var string $menu_location Where the TrustedLogin settings should sit in menu. Options: 'main', or 'submenu' to add under Setting tab
	 * @see Filter: trustedlogin_menu_location
	 */
	private $menu_location = 'main';

	/**
	 * @var array Current site's TrustedLogin settings
	 * @since 0.1.0
	 */
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

	/**
	 * Returns the capability a WordPress user has where they are considered an administrator by TrustedLogin
	 *
	 * @return string 'delete_sites' if multisite, 'manage_options' otherwise.
	 */
	public static function get_admin_capability() {
		return is_multisite() ? 'delete_sites' : 'manage_options'; // If multisite, require super admin
	}

	/**
	 * Returns the capability a WordPress user has where they are considered a support agent by TrustedLogin
	 * With this capability, they are able to log into
	 *
	 * @since 1.0
	 * @todo: Custom capabilities!
	 *
	 * @return string
	 */
	public static function get_support_capability() {
		return 'manage_options';
	}

	public function add_hooks() {

		if ( did_action( 'trustedlogin/vendor/add_hooks/after' ) ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );

		do_action( 'trustedlogin/vendor/add_hooks/after' );
	}

	public function debug_mode_enabled() {

		return (bool) $this->debug_mode;

	}

	public function set_defaults() {


		/**
		 * Filter: Manipulate default options
		 *
		 * @since 1.0.0
		 *
		 * @see   `default_options` private variable.
		 *
		 * @param array
		 */
		$this->default_options = apply_filters( 'trustedlogin/vendor/settings/default', $this->default_options );

		$this->options = get_option( 'trustedlogin_vendor', $this->default_options );

		$this->debug_mode = $this->setting_is_toggled( 'debug_enabled' );

		/**
		 * Filter: Where in the menu the TrustedLogin Options should go.
		 * Added to allow devs to move options item under 'Settings' menu item in wp-admin to keep things neat.
		 *
		 * @since 1.0.0
		 *
		 * @param String either 'main' or 'submenu'
		 */
		$this->menu_location = apply_filters( 'trustedlogin/vendor/settings/menu-location', 'main' );
	}

	public function add_admin_menu() {

		$args = array(
			'submenu_page' => 'options-general.php',
			'menu_title'   => __( 'Settings', 'trustedlogin-vendor' ),
			'page_title'   => __( 'TrustedLogin', 'trustedlogin-vendor' ),
			'capabilities' => self::get_admin_capability(),
			'slug'         => 'trustedlogin_vendor',
			'callback'     => array( $this, 'settings_options_page' ),
			'icon'         => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDEzOS4zIDIyMC43IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAxMzkuMyAyMjAuNyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48c3R5bGUgdHlwZT0idGV4dC9jc3MiPi5zdDB7ZmlsbDojMDEwMTAxO308L3N0eWxlPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im00Mi4yIDY5Ljd2LTIxLjZjMC0xNS4yIDEyLjMtMjcuNSAyNy41LTI3LjUgMTUuMSAwIDI3LjUgMTIuMyAyNy41IDI3LjV2MjEuNmM3LjUgMC41IDE0LjUgMS4yIDIwLjYgMi4xdi0yMy43YzAtMjYuNS0yMS42LTQ4LjEtNDguMS00OC4xLTI2LjYgMC00OC4yIDIxLjYtNDguMiA0OC4xdjIzLjdjNi4yLTAuOSAxMy4yLTEuNiAyMC43LTIuMXoiLz48cmVjdCBjbGFzcz0ic3QwIiB4PSIyMS41IiB5PSI2Mi40IiB3aWR0aD0iMjAuNiIgaGVpZ2h0PSIyNS41Ii8+PHJlY3QgY2xhc3M9InN0MCIgeD0iOTcuMSIgeT0iNjIuNCIgd2lkdGg9IjIwLjYiIGhlaWdodD0iMjUuNSIvPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Im02OS43IDc1LjNjLTM4LjUgMC02OS43IDQuOS02OS43IDEwLjh2NTRoNTYuOXYtOS44YzAtMi41IDEuOC0zLjYgNC0yLjNsMjguMyAxNi40YzIuMiAxLjMgMi4yIDMuMyAwIDQuNmwtMjguMyAxNi40Yy0yLjIgMS4zLTQgMC4yLTQtMi4zdi05LjhoLTU2Ljl2MTIuN2MwIDM4LjUgNDcuNSA1NC44IDY5LjcgNTQuOHM2OS43LTE2LjMgNjkuNy01NC44di03OS45Yy0wLjEtNS45LTMxLjMtMTAuOC02OS43LTEwLjh6bTAgMTIyLjRjLTIzIDAtNDIuNS0xNS4zLTQ4LjktMzYuMmgxNC44YzUuOCAxMy4xIDE4LjkgMjIuMyAzNC4xIDIyLjMgMjAuNSAwIDM3LjItMTYuNyAzNy4yLTM3LjJzLTE2LjctMzcuMi0zNy4yLTM3LjJjLTE1LjIgMC0yOC4zIDkuMi0zNC4xIDIyLjNoLTE0LjhjNi40LTIwLjkgMjUuOS0zNi4yIDQ4LjktMzYuMiAyOC4yIDAgNTEuMSAyMi45IDUxLjEgNTEuMS0wLjEgMjguMi0yMyA1MS4xLTUxLjEgNTEuMXoiLz48L3N2Zz4=',
		);

		if ( 'submenu' === $this->menu_location ) {
			add_submenu_page( $args['submenu_page'], $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'] );
		} else {
			add_menu_page(
				$args['menu_title'],
				$args['page_title'],
				$args['capabilities'],
				$args['slug'],
				$args['callback'],
				$args['icon']
			);

			add_submenu_page(
				$args['slug'],
				$args['page_title'],
				$args['menu_title'],
				$args['capabilities'],
				$args['slug'],
				$args['callback']
			);

		}

	}

	public function admin_init() {

		register_setting(
			'trustedlogin_vendor_options',
			'trustedlogin_vendor',
			array( 'sanitize_callback' => array( $this, 'verify_api_details' ) )
		);

		add_settings_section(
			'trustedlogin_vendor_options_section',
			'',
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
			'public_key',
			__( 'TrustedLogin Public Key ', 'trustedlogin-vendor' ),
			array( $this, 'public_key_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'private_key',
			__( 'TrustedLogin Private Key ', 'trustedlogin-vendor' ),
			array( $this, 'private_key_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'approved_roles',
			__( 'What user roles provide support?', 'trustedlogin-vendor' ) . '<span class="description">' . esc_html__( 'Which users should be able to log into customers&rsquo; sites if they have an Access Key?', 'trustedlogin-vendor' ) . '</span>',
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
			__( 'Enable debug logging?', 'trustedlogin-vendor' ) . '<span class="description">' . sprintf( esc_html__( 'When enabled, logs will be saved to the %s directory.', 'trustedlogin-vendor' ), '<code>wp-content/uploads/trustedlogin-logs</code>' ) . '</span>',
			array( $this, 'debug_enabled_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_field(
			'trustedlogin_vendor_enable_audit_log',
			__( 'Enable Activity Log?', 'trustedlogin-vendor' ) . '<span class="description">' . sprintf( esc_html__( 'Activity Log shows a log of users attempting to log into customer sites using Access Keys.', 'trustedlogin-vendor' ), '<code>wp-content/uploads/trustedlogin-logs</code>' ) . '</span>',
			array( $this, 'enable_audit_log_field_render' ),
			'trustedlogin_vendor_options',
			'trustedlogin_vendor_options_section'
		);

		add_settings_section(
			'trustedlogin_vendor_danger_zone',
			esc_html__( 'Danger Zone', 'trustedlogin-vendor' ),
			array( $this, 'section_callback' ),
			'trustedlogin_vendor_danger'
		);

		add_settings_field(
			'trustedlogin_vendor_reset_keys',
			__( 'Reset encryption keys?', 'trustedlogin-vendor' ).'<span class="howto">' . esc_html__( 'If you reset the encryption keys, all previous authorized logins will be inaccessible.', 'trustedlogin-vendor' ) . '</span>',
			array( $this, 'reset_encryption_field_render' ),
			'trustedlogin_vendor_danger',
			'trustedlogin_vendor_danger_zone'
		);

	}

	/**
	 * Returns whether required settings are set yet. Does not check whether they are valid.
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->settings_get_value( 'account_id' ) && $this->settings_get_value( 'private_key' ) && $this->settings_get_value( 'public_key' );
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
	 * @param array $input Data saved on Settings page.
	 *
	 *
	 * @return array Output of sanitized data.
	 *
	 * @throws Exception When account ID isn't numeric or error setting X-TL-TOKEN header
	 */
	public function verify_api_details( $input ) {

		if ( ! isset( $input['account_id'] ) ) {
			return $input;
		}

		static $api_creds_verified = false;

		if ( $api_creds_verified ) {
			return $input;
		}

		try {

			if ( ! is_numeric( $input['account_id'] ) ) {
				throw new Exception( __( 'Account ID must be numeric', 'trustedlogin-vendor' ) );
			}

			$account_id = intval( $input['account_id'] );
			$private_key  = sanitize_text_field( $input['private_key'] );

			// Decrypt the private key if it's submitted when already-encrypted
			$decrypted_private_key = Encryption::decrypt( $private_key );

			if ( $decrypted_private_key ) {
				$private_key = $decrypted_private_key;
			}

			$public_key = sanitize_text_field( $input['public_key'] );
			$debug_mode = isset( $input['debug_enabled'] );

			$saas_attr = array(
				'private_key' => $private_key,
				'public_key'  => $public_key,
				'debug_mode'  => $debug_mode
			);

			$saas_api = new API_Handler( $saas_attr );

			$x_tl_token  = $saas_api->get_x_tl_token();

			if ( is_wp_error( $x_tl_token ) ) {
				throw new Exception( __( 'Error getting X-TL-TOKEN header', 'trustedlogin-vendor' ) );
			}

			$token_added = $saas_api->set_additional_header( 'X-TL-TOKEN', $x_tl_token );

			if ( ! $token_added ) {
				throw new Exception( __( 'Error setting X-TL-TOKEN header', 'trustedlogin-vendor' ) );
			}

			$verified = $saas_api->verify( $account_id );

			update_site_option( 'trustedlogin_vendor_config', $verified );

			if ( $verified && ! is_wp_error( $verified ) ) {
				// Encrypt the private key at rest
				$input['private_key'] = Encryption::encrypt( $private_key );
			}

		} catch ( Exception $e ) {

			$error = sprintf(
				esc_html__( 'Could not verify TrustedLogin credentials: %s', 'trustedlogin-vendor' ),
				esc_html( $e->getMessage() )
			);

			add_settings_error(
				'trustedlogin_vendor_options',
				'trustedlogin_auth',
				$error,
				'error'
			);
		}

		return $input;
	}

	/**
	 * Returns the decrypted private key setting.
	 *
	 * @since 1.0
	 *
	 * @return string|bool Returns the decrypted private key. If decryption fails (salt or key has changed?), returns false.
	 */
	public function get_private_key() {
		try {
			return Encryption::decrypt( $this->get_setting( 'private_key' ) );
		} catch ( Exception $exception ) {
			return false;
		}
	}

	/**
	 * Renders the private key as well as a button to modify if already set.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function private_key_field_render() {

		$this->render_input_field( 'private_key', 'password', array( 'required' => 'required' ) );

		$status = get_site_option( 'trustedlogin_vendor_config' );

		if ( empty( $status ) || is_wp_error( $status ) ) {
			return;
		}

		echo '<p class="description" id="private_key_message">' . esc_html__( 'The private key has been encrypted.', 'trustedlogin-vendor' ) . '</p>';

		ob_start();
		?>
		<div style="margin-top: .5em">
			<button type="button" id='toggle-private_key-readonly' class="button button-secondary button-small"><?php esc_html_e( 'Modify Private Key', 'trustedlogin-vendor' ); ?></button>
		</div>

		<script>

			jQuery( '#private_key' ).attr( 'readonly', function() {
				return jQuery( this ).val().length ? 'readonly' : '';
			} ).hide();

			jQuery( '#toggle-private_key-readonly' ).on( 'click', function( e ) {
				e.preventDefault();
				jQuery( '#private_key_message' ).hide();
				jQuery('#private_key')
					.show()
					.attr( 'readonly', null )
					.attr( 'required', null )
					.attr( 'placeholder', '<?php esc_attr_e( 'Enter a new private key', 'trustedlogin-vendor' ); ?>' )
					.val('')
					.trigger('focus');
				jQuery( this ).attr( 'disabled', 'disabled' );
				return false;
			});
		</script>
		<?php

		echo ob_get_clean();
	}

	public function public_key_field_render() {
		$this->render_input_field( 'public_key', 'text', array( 'required' ) );
	}

	public function account_id_field_render() {
		$this->render_input_field( 'account_id', 'number', array( 'required' ) );
	}

	public function render_input_field( $setting, $type = 'text', $atts = array() ) {

		if ( ! in_array( $type, array( 'password', 'text', 'number' ) ) ) {
			$type = 'text';
		}

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : '';

		$atts_output = '';
		foreach ( $atts as $attr => $att_value ) {
			if ( is_int( $attr ) ) {
				$attr = $att_value;
			}
			$atts_output .= sprintf( '%1$s="%2$s"', esc_attr( $attr ), esc_attr( $att_value ) );
		}

		$output = '<input id="' . esc_attr( $setting ) . '" name="' . self::SETTING_NAME . '[' . esc_attr( $setting ) . ']" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" class="regular-text ltr" ' . $atts_output . '>';

		echo $output;
	}

	public function approved_roles_field_render() {

		$roles          = get_editable_roles();
		$selected_roles = $this->get_approved_roles();

		// I mean, really. No one wants this.
		unset( $roles['subscriber'] );

		$select = '<select name="' . self::SETTING_NAME . '[approved_roles][]" size="5" id="trustedlogin_vendor_approved_roles" class="regular-text chosen" multiple="multiple" regular-text>';

		foreach ( $roles as $role_slug => $role_info ) {

			$selected = selected( true, in_array( $role_slug, $selected_roles, true ), false );

			$select .= "<option value='" . $role_slug . "' " . $selected . ">" . $role_info['name'] . "</option>";
		}

		$select .= "</select>";

		echo $select;

	}

	public function helpdesks_field_render() {

		/**
		 * Filter: The array of TrustLogin supported HelpDesks
		 *
		 * @var string $title ,    Translated title of the Helpdesk software, and title of dropdown option.
		 * @var bool $active ,    If false, the Helpdesks Solution is not shown in the dropdown options for selection.
		 *        ],
		 * ]
		 * @since 0.1.0
		 *
		 * @param array [
		 *        $slug => [                    Slug is the identifier of the Helpdesk software, and is the value of the dropdown option.
		 */
		$helpdesks = apply_filters( 'trustedlogin/vendor/settings/helpdesks', array(
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

		$select = '<select name="' . self::SETTING_NAME . '[helpdesk][]" id="helpdesk" class="postform regular-text ltr">';

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

		$this->settings_output_toggle( 'debug_enabled' );

	}

	public function enable_audit_log_field_render() {

		$this->settings_output_toggle( 'enable_audit_log' );

	}

	public function reset_encryption_field_render() {

		$other_attributes = array(
			'id' => 'trustedlogin-reset-button',
		);

		submit_button( __( 'Reset Keys', 'trustedlogin-vendor' ), 'is-destructive is-primary button-large', 'trustedlogin-reset-button', false, $other_attributes );;
	}

	public function settings_output_toggle( $setting ) {

		$value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : 'off';

		$select = '<label class="switch">
                    <input class="switch-input" name="' . self::SETTING_NAME . '[' . $setting . ']" id="' . $setting . '" type="checkbox" ' . checked( $value, 'on', false ) . '/>
                    <span class="switch-label" data-on="On" data-off="Off"></span>
                    <span class="switch-handle"></span>
                </label>';
		echo $select;
	}

	public function section_callback() {
		do_action( 'trustedlogin/vendor/settings/section-callback' );
	}

	public function settings_options_page() {

		wp_enqueue_script( 'trustedlogin-settings' );
		wp_enqueue_style( 'trustedlogin-settings' );

		echo '<form method="post" action="options.php">';

		printf( '<img src="%s" width="400" alt="TrustedLogin">', esc_url( plugins_url( 'assets/trustedlogin-logo.png', TRUSTEDLOGIN_PLUGIN_FILE ) ) );

		echo sprintf( '<h1 class="screen-reader-text">%1$s</h1>', __( 'TrustedLogin Settings', 'trustedlogin-vendor' ) );

		settings_errors( 'trustedlogin_vendor_options' );

		$status = get_site_option( 'trustedlogin_vendor_config' );

		switch( true ) {
			case is_wp_error( $status ):
				echo '<div class="notice notice-error">';
				echo '<h2>⚠️ ' . esc_html__( 'Could not verify TrustedLogin credentials.', 'trustedlogin-vendor' ) . '</h2>';
				echo '<h3 class="description">' . esc_html( $status->get_error_message() ) . '</h3>';
				echo '</div>';
				break;
			case is_object( $status ):
				echo '<div class="notice notice-success">';
				echo '<h2>✅ ' . esc_html__( 'You&rsquo;re connected to TrustedLogin!', 'trustedlogin-vendor' ) . '</h2>';

				if ( isset( $status->id ) ) {
					$url = sprintf( 'https://app.trustedlogin.com/settings/teams/%d', $status->id );
					$link_text = sprintf( esc_html__( 'Manage the %s team at TrustedLogin.com', 'trustedlogin-vendor' ), '<strong>' . esc_html( $status->name ) . '</strong>' );
				} else {
					$url = 'https://app.trustedlogin.com/login';
					$link_text = __( 'Log in to TrustedLogin', 'trustedlogin-vendor' );
				}

				echo '<h3 class="description"><a href="' . esc_url( $url ) .'">' . $link_text . '</a></h3>';
				echo '</div>';
				break;
			case ( false === $status ):
				echo '<div class="notice notice-success">';
				echo '<h2>' . esc_html__( 'Connect your site to the TrustedLogin service.', 'trustedlogin-vendor' ) . '</h2>';
				echo '<h3 class="description"><a href="https://app.trustedlogin.com">' . esc_html__( 'Sign up at TrustedLogin.com', 'trustedlogin-vendor') . '</a></h3>';
				echo '</div>';
				break;
		}


		do_action( 'trustedlogin/vendor/settings/sections/before' );

		settings_fields( 'trustedlogin_vendor_options' );

		do_settings_sections( 'trustedlogin_vendor_options' );

		do_action( 'trustedlogin/vendor/settings/sections/after' );

		submit_button( esc_html__( 'Update Settings', 'trustedlogin-vendor' ), 'primary button-hero');

		echo '</form>';

		echo '<div id="trustedlogin-danger-zone" class="notice notice-error">';
		do_settings_sections( 'trustedlogin_vendor_danger' );
		echo '</div>';
		do_action( 'trustedlogin/vendor/settings/form/after' );

	}

	public function register_scripts() {

		wp_register_style(
			'trustedlogin-vendor-chosen',
			plugins_url( '/assets/chosen/chosen.min.css', dirname( __FILE__ ) )
		);
		wp_register_script(
			'trustedlogin-vendor-chosen',
			plugins_url( '/assets/chosen/chosen.jquery.min.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			false,
			true
		);

		wp_register_style( 'trustedlogin-settings',
			plugins_url( '/assets/trustedlogin-settings.css', dirname( __FILE__ ) ),
			array( 'trustedlogin-vendor-chosen' ),
			$this->plugin_version
		);

		wp_register_script( 'trustedlogin-settings',
			plugins_url( '/assets/trustedlogin-settings.js', dirname( __FILE__ ) ),
			array( 'jquery', 'trustedlogin-vendor-chosen' ),
			$this->plugin_version,
			true
		);

		$redirect_url = add_query_arg(
			array(
				'page'   => ( isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : null ),
				'action' => 'reset_keys'
			),
			admin_url( 'admin.php' )
		);

		$settings_args = array(
			'reset_keys_url' => wp_nonce_url( $redirect_url, 'reset-keys' ),
			'lang'           => array(
				'confirm_reset' => __( 'Are you sure? Resetting encryption keys will irrevokably disable ALL existing TrustedLogin authentications.', 'trustedlogin-vendor' ),
			),
		);

		wp_localize_script( 'trustedlogin-settings', 'tl_obj', $settings_args );

	}

	/**
	 * Returns the value of a setting
	 *
	 * @since 0.2.0
	 *
	 * @param String $setting_name The name of the setting to get the value for
	 *
	 * @return mixed     The value of the setting, or false if it's not found.
	 */
	public function get_setting( $setting_name ) {

		if ( empty( $setting_name ) ) {
			return new WP_Error( 'input-error', __( 'Cannot fetch empty setting name', 'trustedlogin-vendor' ) );
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
		return array_key_exists( $setting, $this->options ) ? true : false;
	}

	public function settings_get_value( $setting ) {
		return $value = ( array_key_exists( $setting, $this->options ) ) ? $this->options[ $setting ] : false;
	}

	private function is_vendor_settings_page() {
		return 'trustedlogin_vendor' !== sanitize_text_field( $_REQUEST['page'] );
	}

	/**
	 * Responds to actions piped through the URL to our settings page.
	 *
	 * @return void
	 */
	public function handle_admin_actions() {

		if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['page'] ) ) {
			return;
		}

		if ( $this->is_vendor_settings_page() ) {
			return;
		}

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		$action = sanitize_text_field( $_REQUEST['action'] );

		if ( 'reset_complete' === $action ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-warning warning"><h2>' . esc_html__( 'Encryption keys reset.', 'trustedlogin-vendor' ) . '</h2><p>' . esc_html__( 'All previous authorizations are now inaccessible via TrustedLogin', 'trustedlogin-vendor' ) . '</p></div>';
			} );

			return;
		}

		if ( 'reset_keys' !== $action ) {
			return;
		}

		// Will normally die(), but can return value.
		$nonce_check = check_admin_referer( 'reset-keys' );

		if ( ! $nonce_check ) {
			return;
		}

		$reset = $this->reset_encryption_keys();

		if ( is_wp_error( $reset ) ) {
			add_action( 'admin_notices', function () use ( $reset ) {
				echo '<div class="notice notice-error error"><h3>' . esc_html__( 'Encryption keys reset.', 'trustedlogin-vendor' ) . '</h3>' . wpautop( esc_html( $reset->get_error_message() ) ) . '</div>';
			} );

			return;
		}

		/**
		 * Redirect to avoid resetting keys on subsequent saves.
		 */
		$redirect_url = add_query_arg(
			array(
				'page'   => sanitize_text_field( $_GET['page'] ),
				'action' => 'reset_complete'
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
	}

	/**
	 * Resets the encryption key via admin action.
	 *
	 * @return true|WP_Error If successfully returns true, otherwise WP_Error.
	 */
	private function reset_encryption_keys() {

		$trustedlogin_encryption = new Encryption();

		return $trustedlogin_encryption->reset_keys();
	}

}
