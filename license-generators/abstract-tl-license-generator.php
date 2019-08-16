<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin - HelpScout Integration
 *
 * @package tl-support-side
 * @version 0.1.0
 **/
class License_Generators {

	static public function get_license_generators() {

		$license_generators = apply_filters( 'trustedlogin_license_generators', array() );

		return $license_generators;
	}

}