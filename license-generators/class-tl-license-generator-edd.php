<?php
namespace TrustedLogin;

class EDD extends License_Generator {

	protected $name = 'Easy Digital Downloads';

	protected $slug = 'edd';

	public function is_enabled() {
		return function_exists( '\edd' );
	}

	public function get_license_keys_by_email( $email = '' ) {

		if ( ! $this->is_enabled() ) {
			return;
		}

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
}

new EDD();