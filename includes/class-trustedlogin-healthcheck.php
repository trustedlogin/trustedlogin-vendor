<?php
/**
 * Class: TrustedLogin HealthCheck
 *
 * @package trustedlogin-vendor
 * @version 0.2.0
 **/

namespace TrustedLogin\Vendor;

use \WP_Error;
use \Exception;
use const TRUSTEDLOGIN_PLUGIN_VERSION;

class HealthCheck {

	use Debug_Logging;

	/**
	 * Array of tests to run for HealthCheck
	 * 
	 * @since 1.0.0
	 * @var requirements
	 */
	private $requirements = array(
		'versions' => array(
			'php' 		=> array( 'min' => '5.4', 'callback' => '\phpversion' ),
			'wordpress'	=> array( 'min' => '4.0', 'callback' => __NAMESPACE__.'\HealthCheck::get_wp_version' ),
		),
		'functions' => array(
			'\random_bytes_sdas',
			'\sodium_crypto_box_keypair',
		),
		'constants' => array(
			'SODIUM_CRYPTO_BOX_NONCEBYTES',
		),
		'callbacks' => array(
			'Encryption' => 'get_key',
		)
	);

	/**
	 * TrustedLogin\Vendor\HealthCheck constructor
	 */
	public function __construct(){

		$this->set_defaults();

	}

	/**
	 * Sets up the required tests array 
	 */
	public function set_defaults(){

		/**
		 * Filter: allows for the addition of more healtchecks
		 * 
		 * @since 1.0.0
		 * @param array
		 */
		$this->requirements = apply_filters( 'trustedlogin\vendor\healthcheck\requirements', $this->requirements );

	}

	/**
	 * Runs the required checks
	 *
	 * @since 1.0.0
	 *
	 * @param  bool  Whether to return all check results.
	 *
	 * @return true|array|WP_Error  True if all tests pass and $return_checks is false, else an array of test results. 
	 * 								WP_Error if issue.
	 */
	public function run_checks( $return_checks = false ){

		$all_tests = array(
			'all_passed' => true,
		);

		foreach ( $this->requirements as $key => $tests ){
			$callback = 'check_'. $key ;
			$results = $this->$callback();

			if ( ! $results['all_passed'] ){

				if ( ! $return_checks ){
					return new WP_Error( 
						$key.'_checks_failed', 
						sprintf( 
							__('%s checks failed.', 'trustedlogin-vendor'),
							$key
						)
					);
				}

				$all_tests['all_passed'] = false;
			}

			$all_tests[ $key ] = $results;	
		}

		if ( $return_checks ){
			return $all_tests;
		}

		return true; 

	}

	public function run_check( $slug ){

		$steps = explode( '/', $slug );

		if ( 2 < count( $steps ) ){ 
			return false; 
		}

		$key   = $steps[0];
		$tests = $steps[1];

		if ( ! array_key_exists( $key,  $this->requirements ) ){
			return new WP_Error( 'tests-error', sprintf( __('No tests found for key: %s', 'trustedlogin-vendor'), $key ) );
		}

		if ( ! array_key_exists( $tests, $this->requirements[ $key ] ) ){
			return new WP_Error( 'tests-error', sprintf( __('No tests found for tests: %s', 'trustedlogin-vendor'), $tests ) );
		}

		$current_tests = $this->requirements[ $key ];

		foreach ( $current_tests as $current_key => $current_test ){
			if ( $current_key !== $tests ){
				unset( $current_tests[ $current_key ] );
			}
		}


	}

	public function build_notices( $check_results ){

		$notices = array();

		foreach ( $check_results as $key => $results ){

			// skip over the all_passed value
			if ( 'all_passed' == $key ) continue;

			// skip over group if all tests passed
			if ( $results['all_passed'] ) continue;

			foreach ( $results as $subkey => $result ){

				// skip over non-negative results
				if ( $result || 'all_passed' == $subkey ) continue;

				$notices[] = sprintf( 
					__( 'Notice: %s check failed for %s.', 'trustedlogin-vendor' ),
					/* %s */ $key,
					/* %s */ $subkey
				);
			}
			
		}

		return $notices;

	}

	/**
	 * Helper: Fetches and returns the current WP version
	 *
	 * @since 1.0.0 
	 * @return string
	 */
	static function get_wp_version(){
		global $wp_version;
		return $wp_version;
	}

	/**
	* Callback for the versions tests
	* 
	* @since 1.0.0
	* @uses $requirements['versions']
	*
	* @return array  $results [
	* 	@var  bool  $all_passed  - whether all tests in this checker passed
	*   @var  bool  ${ $key }    - results of the tests for $key
	* ]
	*/
	private function check_versions(){

		$results = array(
			'all_passed' => true,
		);
		
		foreach ( $this->requirements['versions'] as $key => $test ){
			$passed = version_compare( $test['callback'](), $test['min'], '>=' );

			if ( !$passed ){
				$results['all_passed'] = false;
			}

			$results[ $key ] = $passed;
		}

		$this->dlog( "results: " . print_r( $results, true ), __METHOD__ );

		return $results;

	}

	/**
	* Callback for the functions tests
	* 
	* @since 1.0.0
	* @uses $requirements['functions']
	*
	* @return array  $results [
	* 	@var  bool  $all_passed  - whether all tests in this checker passed
	*   @var  bool  ${ $key }    - results of the tests for $key
	* ]
	*/
	private function check_functions(){

		$results = array(
			'all_passed' => true,
		);

		foreach ( $this->requirements['functions'] as $function ){

			$passed = function_exists( $function );

			if ( ! $passed ){
				$results['all_passed'] = false;
			}

			$results[ $function ] = $passed;

		}

		$this->dlog( "results: ". print_r( $results, true ), __METHOD__ );

		return $results; 
	}

	/**
	* Callback for the constants tests
	* 
	* @since 1.0.0
	* @uses $requirements['constants']
	*
	* @return array  $results [
	* 	@var  bool  $all_passed  - whether all tests in this checker passed
	*   @var  bool  ${ $key }    - results of the tests for $key
	* ]
	*/
	private function check_constants(){

		$results = array(
			'all_passed' => true,
		);

		foreach ( $this->requirements['constants'] as $constant ){

			$passed = defined( $constant );

			if ( ! $passed ){
				$results['all_passed'] = false;
			}

			$results[ $constant ] = $passed;

		}

		$this->dlog( "results: ". print_r( $results, true ), __METHOD__ );

		return $results; 
	}

	/**
	* Callback for the callbacks tests
	* 
	* @since 1.0.0
	* @uses $requirements['callbacks']
	*
	* @return array  $results [
	* 	@var  bool  $all_passed  - whether all tests in this checker passed
	*   @var  bool  ${ $key }    - results of the tests for $key
	* ]
	*/
	private function check_callbacks(){

		$results = array(
			'all_passed' => true,
		);

		foreach ( $this->requirements['callbacks'] as $class => $callback ){

			$classname = __NAMESPACE__.'\\'.$class;

			if ( ! class_exists( $classname ) ){
				$results['all_passed'] = false;
				continue;
			}

			$init_class = new $classname();
			$passed = $init_class->$callback();

			if ( ! $passed || is_wp_error( $passed ) ){
				$results['all_passed'] = false;
			}

			$results[ $callback ] = $passed;

		}

		$this->dlog( "results: ". print_r( $results, true ), __METHOD__ );

		return $results; 
	}



}