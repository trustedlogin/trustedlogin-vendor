<?php
namespace TrustedLogin;

class License_Generator_EDD implements License_Generator {

	const name = 'Easy Digital Downloads';

	const slug = 'edd';

	public function get_license_keys_by_email( $email = '' ) {

		$keys = array();
		$_u   = get_user_by( 'email', $email );

		if ( ! $_u ) {
			return $keys;
		}

		$licenses = \edd_software_licensing()->get_license_keys_of_user( $_u->ID, 0, 'all', true );

		foreach ( $licenses as $license ) {
			$children = \edd_software_licensing()->get_child_licenses( $license->ID );
			if ( $children ) {
				foreach ( $children as $child ) {
					$keys[] = \edd_software_licensing()->get_license_key( $child->ID );
				}
			}

			$keys[] = \edd_software_licensing()->get_license_key( $license->ID );
		}

		return $keys;
	}

	public function is_enabled() {
		return function_exists( 'edd' );
	}
}