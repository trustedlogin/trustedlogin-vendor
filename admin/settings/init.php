<?php
//Register assets for TrustedLogin
add_action('init', function () {
    $handle = 'tl-vendor-settings';
    $assets = include dirname(__FILE__, 3). "/build/admin-page-settings.asset.php";
    $dependencies = $assets['dependencies'];
    wp_register_script(
        $handle,
        plugins_url("/build/admin-page-settings.js", dirname(__FILE__, 2)),
        $dependencies,
        $assets['version']
    );
	wp_register_style(
		$handle,
		plugins_url("/build/style-admin-page-settings.css", dirname(__FILE__, 2)),
		['wp-components']
	);
});

//Enqueue assets for TrustedLogin on admin page only
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_settings' != $hook) {
        return;
    }
	$handle = 'tl-vendor-settings';
    wp_enqueue_script($handle);
	wp_enqueue_style($handle);

});

//Register TrustedLogin menu page
add_action('admin_menu', function () {
    add_menu_page(
        __('TrustedLogin', 'trustedlogin-vendor'),
        __('TrustedLogin', 'trustedlogin-vendor'),
        'manage_options',
        'settings',
        function () {
			echo '<div id="tl-vendor-settings">';
			printf( '<img src="%s" alt="%s" width="400" />',
				esc_url(
					plugins_url( 'assets/trustedlogin-logo.png', TRUSTEDLOGIN_PLUGIN_FILE )
				),
				esc_attr( __( 'TrustedLogin Logo' ) )
			);
			//React root
            echo '<div id="tl-vendor-settings-app"></div>';
			echo '</div><!-- /#tl-vendor-settings-->';
        }
    );
});
