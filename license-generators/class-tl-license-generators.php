<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class License_Generators {

	/**
	 * Get the first active license generator for the site
	 *
	 * You can use it like this:
	 * $license_generator->get_license_keys_by_email( 'zack@gravityview.co' )
	 *
	 * @return \TrustedLogin\EDD|false
	 */
	static public function get_active() {
		$all = self::get_all();

		if ( empty( $all ) ) {
			return false;
		}

		$class_name = array_pop( array_keys( $all ) );

		if ( ! class_exists( $class_name ) ) {
			return false;
		}

		return new $class_name;
	}
	
	static public function get_all() {

		$license_generators = apply_filters( 'trustedlogin_license_generators', array() );

		return $license_generators;
	}

}