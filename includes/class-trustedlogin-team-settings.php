<?php
/**
 * Class: TrustedLogin Team Settings
 *
 * @package trustedlogin-vendor
 * @version 0.10.0
 */

namespace TrustedLogin\Vendor;


/**
 * Object-representation of one Team's settings.
 */
class TeamSettings {



	/**
	 * @var array
	 * @since 0.10.0
	 */
	protected $defaults = [
		'account_id'       => '',
		'private_key'      => '',
		'api_key'       => '',
		'helpdesk'         => [ 'helpscout' ],
		'approved_roles'   => [ 'administrator' ],
		'debug_enabled'    => 'on',
		'enable_audit_log' => 'on',
	];


	/**
	 * @var array
	 * @since 0.10.0
	 */
	protected $values;


	/**
	 * @since 0.10.0
	 *
	 * @param array $values Values to set
	 */
	public function __construct(array $values = [])
	{
		$this->reset($values);

	}

	/**
	 * Get all values as array.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function to_array(){
		return $this->values;
	}

	/**
	 * Reset all values
	 *
	 * @since 0.10.0
	 *
	 * @param array $values Values to set
	 * @return $this
	 */
	public function reset(array $values ){
		$this->values = [];
		foreach ($this->defaults as $key => $default) {
			if( isset($values[$key])){
				$this->values[$key] = $values[$key];
			}else{
				$this->values[$key] = $default;
			}
		}
		return $this;
	}

	/**
	 * Set a value
	 *
	 * @since 0.10.0
	 *
	 * @param string $key Setting to set
	 * @param mixed $value The new value
	 * @return $this
	 */
	public function set($key, $value){
		if( $this->valid($key)){
			$this->values[$key] = $value;
		}else{
			throw new \Exception('Invalid key');
		}
		return $this;
	}

	/**
	 * Get a value
	 *
	 * @since 0.10.0
	 * @param string $key Setting to get
	 * @return mixed
	 */
	public function get($key){
		if( $this->valid($key)){
			return $this->values[$key];
		}
		throw new \Exception('Invalid key');
	}

	/**
	 * Check if key is valid
	 *
	 * @since 0.10.0
	 * @param string $key Setting to get
	 * @return bool
	 */
	public function valid($key){
		return array_key_exists($key, $this->defaults);
	}

}
