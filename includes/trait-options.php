<?php
trait TL_Options
{

    public function tls_settings_set_defaults()
    {
        if (property_exists($this, 'default_options')) {
            $this->default_options = apply_filters('trustedlogin_default_settings', array(
                'tls_account_id' => "",
                'tls_account_key' => "",
                'tls_helpdesk' => array(),
                'tls_approved_roles' => array('administrator'),
                'tls_debug_enabled' => 'on',
                'tls_output_audit_log' => 'off',
            ));
        }
        if (property_exists($this, 'menu_location')) {
            $this->menu_location = 'main'; // change to 'submenu' to add under Setting tab
        }

        $this->options = get_option('tls_settings', $this->default_options);
    }

    public function tls_settings_add_admin_menu()
    {

        $args = array(
            'submenu_page' => 'options-general.php',
            'menu_title' => __('TL Settings', 'tl-support-side'),
            'page_title' => __('TrustedLogin', 'tl-support-side'),
            'capabilities' => 'manage_options',
            'slug' => 'tls_settings',
            'callback' => array($this, 'tls_settings_options_page'),
            'icon' => 'dashicons-tickets-alt',
        );
        if ('submenu' == $this->menu_location) {
            add_submenu_page($args['submenu_page'], $args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback']);
        } else {
            add_menu_page($args['menu_title'], $args['page_title'], $args['capabilities'], $args['slug'], $args['callback'], $args['icon']);
        }

    }

    public function tls_settings_init()
    {

        register_setting('TLS_plugin_options', 'tls_settings');

        add_settings_section(
            'tls_options_section',
            __('Settings for how your site and support agents are connected to TrustedLogin', 'tl-support-side'),
            array($this, 'tls_settings_section_callback'),
            'TLS_plugin_options'
        );

        add_settings_field(
            'tls_account_id',
            __('TrustedLogin Account ID ', 'tl-support-side'),
            array($this, 'tls_settings_account_id_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_account_key',
            __('TrustedLogin API Key ', 'tl-support-side'),
            array($this, 'tls_settings_account_key_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_approved_roles',
            __('Which roles can automatically be logged into customer sites?', 'tl-support-side'),
            array($this, 'tls_settings_approved_roles_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_helpdesk',
            __('Which helpdesk software are you using?', 'tl-support-side'),
            array($this, 'tls_settings_helpdesks_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_debug_enabled',
            __('Enable debug logging?', 'tl-support-side'),
            array($this, 'tls_settings_debug_enabled_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

        add_settings_field(
            'tls_output_audit_log',
            __('Display Audit Log below?', 'tl-support-side'),
            array($this, 'tls_settings_output_audit_log_field_render'),
            'TLS_plugin_options',
            'tls_options_section'
        );

    }

    public function tls_settings_account_key_field_render()
    {

        $this->tls_settings_render_input_field('tls_account_key', 'password', true);

    }

    public function tls_settings_account_id_field_render()
    {
        $this->tls_settings_render_input_field('tls_account_id', 'text', true);
    }

    public function tls_settings_render_input_field($setting, $type = 'text', $required = false)
    {
        if (!in_array($type, array('password', 'text'))) {
            $type = 'text';
        }

        $value = (array_key_exists($setting, $this->options)) ? $this->options[$setting] : '';

        $set_required = ($required) ? 'required' : '';

        $output = '<input id="' . $setting . '" name="tls_settings[' . $setting . ']" type="' . $type . '" value="' . $value . '" class="regular-text ltr" ' . $set_required . '>';

        echo $output;
    }

    public function tls_settings_approved_roles_field_render()
    {

        $roles = get_editable_roles();
        $selected_roles = $this->tls_settings_get_approved_roles();

        $select = "<select name='tls_settings[tls_approved_roles][]' id='tls_approved_roles' class='postform regular-text ltr' multiple='multiple' regular-text ltr>";

        foreach ($roles as $role_slug => $role_info) {

            if (in_array($role_slug, $selected_roles)) {
                $selected = "selected='selected'";
            } else {
                $selected = "";
            }
            $select .= "<option value='" . $role_slug . "' " . $selected . ">" . $role_info['name'] . "</option>";

        }

        $select .= "</select>";

        echo $select;

    }

    public function tls_settings_helpdesks_field_render()
    {

        /**
         * Filter: The array of TrustLogin supported HelpDesks
         *
         * @since 0.4.0
         * @param Array ('slug'=>'Title')
         **/
        $helpdesks = apply_filters('trustedlogin_supported_helpdesks', array(
            '' => __('Select Your Helpdesk Software', 'tl-support-side'),
            'intercom' => __('Intercom', 'tl-support-side'),
            'helpspot' => __('HelpSpot', 'tl-support-side'),
            'helpscout' => __('HelpScout', 'tl-support-side'),
            'drift' => __('Drift', 'tl-support-side'),
            'gosquared' => __('GoSquared', 'tl-support-side'),
        ));

        $selected_helpdesk = $this->tls_settings_get_selected_helpdesk();

        $select = "<select name='tls_settings[tls_helpdesk][]' id='tls_helpdesk' class='postform regular-text ltr'>";

        foreach ($helpdesks as $key => $title) {

            if (in_array($key, $selected_helpdesk)) {
                $selected = "selected='selected'";
            } else {
                $selected = "";
            }
            $select .= "<option value='" . $key . "' " . $selected . ">" . $title . "</option>";

        }

        $select .= "</select>";

        echo $select;

    }

    public function tls_settings_debug_enabled_field_render()
    {

        $this->tls_settings_output_toggle('tls_debug_enabled');

    }

    public function tls_settings_output_audit_log_field_render()
    {

        $this->tls_settings_output_toggle('tls_output_audit_log');

    }

    public function tls_settings_output_toggle($setting)
    {

        $value = (array_key_exists($setting, $this->options)) ? $this->options[$setting] : 'off';

        $select = '<label class="switch">
                    <input class="switch-input" name="tls_settings[' . $setting . ']" id="' . $setting . '" type="checkbox" ' . checked($value, 'on', false) . '/>
                    <span class="switch-label" data-on="On" data-off="Off"></span>
                    <span class="switch-handle"></span>
                </label>';
        echo $select;
    }

    public function tls_settings_section_callback()
    {
        do_action('trustedlogin_section_callback');
    }

    public function tls_settings_options_page()
    {

        wp_enqueue_script('chosen');
        wp_enqueue_style('chosen');
        wp_enqueue_script('trustedlogin-settings');
        wp_enqueue_style('trustedlogin-settings');

        echo '<form method="post" action="options.php">';

        echo sprintf('<h1>%1$s</h1>', __('TrustedLogin Settings', 'tl-support-side'));

        do_action('trustedlogin_before_settings_sections');

        settings_fields('TLS_plugin_options');
        do_settings_sections('TLS_plugin_options');

        do_action('trustedlogin_after_settings_sections');

        submit_button();

        echo "</form>";

        do_action('trustedlogin_after_settings_form');

    }

    public function tls_settings_scripts()
    {
        wp_register_style('chosen', plugins_url('/assets/chosen/chosen.min.css', dirname(__FILE__)));
        wp_register_script('chosen', plugins_url('/assets/chosen/chosen.jquery.min.js', dirname(__FILE__)), array('jquery'), false, true);

        wp_register_style('trustedlogin-settings',
            plugins_url('/assets/trustedlogin-settings.css', dirname(__FILE__)),
            array(),
            $this->plugin_version
        );
        wp_register_script('trustedlogin-settings',
            plugins_url('/assets/trustedlogin-settings.js', dirname(__FILE__)),
            array('jquery'),
            $this->plugin_version,
            true
        );
    }

    public function tls_settings_get_approved_roles()
    {
        return $this->tls_settings_get_selected_values('tls_approved_roles');
    }

    public function tls_settings_get_selected_helpdesk()
    {
        return $this->tls_settings_get_selected_values('tls_helpdesk');
    }

    public function tls_settings_get_selected_values($setting)
    {
        $value = (array_key_exists($setting, $this->options)) ? $this->options[$setting] : array();
        return maybe_unserialize($value);
    }

    public function tls_settings_is_toggled($setting)
    {
        return (array_key_exists($setting, $this->options)) ? true : false;
    }

    public function tls_settings_get_value($setting)
    {
        return $value = (array_key_exists($setting, $this->options)) ? $this->options[$setting] : false;
    }

}
