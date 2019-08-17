<?php
namespace TrustedLogin;

abstract class License_Generator {

	protected $name;

	protected $slug;

	/**
	 * EDD constructor.
	 */
	public function __construct() {

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_filter( 'trustedlogin_license_generators', array( $this, 'register' ) );
	}

	/**
	 * When a license generator is enabled, add it to the list
	 *
	 * @param array $generators
	 *
	 * @return array
	 */
	public function register( $generators = array() ) {

		$generators[ $this->slug ] = $this->name;

		return $generators;
	}

	/**
	 * @return bool
	 */
	abstract public function is_enabled();

	/**
	 * Get an array of license keys
	 * 
	 * @return string[]
	 */
	abstract public function get_license_keys_by_email( $email );

}