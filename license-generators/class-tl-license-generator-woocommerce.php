<?php
namespace TrustedLogin;

class WooCommerce extends License_Generator {

	protected $name = 'WooCommerce';

	protected $slug = 'woocommerce';

	public function is_enabled() {
		return function_exists( 'WC' );
	}

	/**
	 * @todo Not yet implemented
	 */
	public function has_licensing() {}

	/**
	 * @todo Not yet implemented
	 * @param string $email
	 */
	public function get_license_keys_by_email( $email = '' ) {}
}

new WooCommerce();