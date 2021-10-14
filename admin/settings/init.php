<?php
//Register assets for TrustedLogin
add_action('init', function () {
    $handle = 'settings';
    $assets = include dirname(__FILE__, 3). "/build/admin-page-$handle.asset.php";
    $dependencies = $assets['dependencies'];
    wp_register_script(
        $handle,
        plugins_url("/build/admin-page-$handle.js", dirname(__FILE__, 2)),
        $dependencies,
        $assets['version']
    );
});

//Enqueue assets for TrustedLogin on admin page only
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_settings' != $hook) {
        return;
    }
    wp_enqueue_script('settings');
});

//Register TrustedLogin menu page
add_action('admin_menu', function () {
    add_menu_page(
        __('TrustedLogin', 'trustedlogin-vendor'),
        __('TrustedLogin', 'trustedlogin-vendor'),
        'manage_options',
        'settings',
        function () {
            //React root
            echo '<div id="settings"></div>';
        }
    );
});
