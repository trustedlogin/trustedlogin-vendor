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
	 * @var $requirements
	 * @since 1.0.0
	 */
	private $requirements = array(
		'versions'  => array(
			'php'       => array(
				'min'      => '7.0',
				'callback' => '\phpversion',
			),
			'wordpress' => array(
				'min'      => '4.2',
				'callback' => __NAMESPACE__ . '\HealthCheck::get_wp_version',
			),
		),
		'functions' => array(
			'random_bytes' => array(
				'callback' => '\random_bytes',
			),
			'sodium'       => array(
				'callback' => '\sodium_crypto_box_keypair',
			),
		),
		'constants' => array(
			'sodium' => array(
				'callback' => 'SODIUM_CRYPTO_BOX_NONCEBYTES',
			),
		),
		'callbacks' => array(
			'encryption_get_public_key' => array(
				'callback' => array( __NAMESPACE__ . '\\Encryption', 'get_public_key' ),
			),
		)
	);

	/**
	 * TrustedLogin\Vendor\HealthCheck constructor
	 */
	public function __construct() {

		$this->add_hooks();

		$this->set_defaults();

	}

	public function add_hooks() {

		if ( did_action( 'trustedlogin/vendor/healthcheck/add_hooks/after' ) ) {
			return;
		}

		add_filter( 'trustedlogin/vendor/healthcheck/requirements', array( $this, 'set_translateable_data' ) );
		add_filter( 'site_status_tests', array( $this, 'add_wp_tests' ) );

		do_action( 'trustedlogin/vendor/healthcheck/add_hooks/after' );
	}

	/**
	 * Filter for adding TrustedLogin tests to the WP Site Health.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/site_status_tests/
	 *
	 * @param array $tests
	 *
	 * @return $tests
	 */
	function add_wp_tests( $tests ) {

		foreach ( $this->requirements as $key => $required_tests ) {

			if ( ! in_array( $key, array( 'versions', 'functions', 'constants', 'callbacks' ) ) ) {
				continue;
			}

			$test_name = 'trustedlogin_' . $key;
			$callback  = 'site_health_check_' . $key;


			$tests['direct'][ $test_name ] = array(
				'label' => sprintf( __( 'TrustedLogin %1$s Test', 'trustedlogin-vendor' ), $key ),
				'test'  => array( $this, $callback ),
			);

		}

		return $tests;
	}

	/**
	 * Callback for returning the WP Site Health results for our `versions` tests.
	 *
	 * @return $result
	 */
	function site_health_check_versions() {

		$test_results = $this->check_versions();

		$result = array(
			'label'       => __( 'Minimum versions required were met.', 'trustedlogin-vendor' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'TrustedLogin',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'This site meets the minimum version requirements to run the TrustedLogin Vendor plugin.', 'trustedlogin-vendor' )
			),
			'actions'     => '',
			'test'        => 'trustedlogin_versions',
		);

		if ( ! $test_results['all_passed'] ) {

			$failed_tests = '';
			$action_links = '';
			foreach ( $test_results as $key => $test_result ) {

				if ( 'all_passed' === $key ) {
					continue;
				}

				if ( ! $test_result ) {
					$title        = ( isset( $this->requirements['versions'][ $key ]['title'] ) )
						? esc_html( $this->requirements['versions'][ $key ]['title'] ) : esc_html( $key );
					$failed_tests .= sprintf(
						__( 'Minimum required version of %s is %s. ', 'trustedlogin-vendor' ),
						$title,
						$this->requirements['versions'][ $key ]['min']
					);

					if ( isset( $this->requirements['versions'][ $key ]['action_url'] ) ) {
						$action_links .= sprintf(
							'<a href="%s">%s</a>',
							esc_url( $this->requirements['versions'][ $key ]['action_url'] ),
							sprintf( __( 'Update %s', 'trustedlogin-vendor' ), $this->requirements['versions'][ $key ]['title'] )
						);
					}
				}
			}

			$result['status']         = 'critical';
			$result['label']          = __( 'Minimum versions required were NOT met.', 'trustedlogin-vendor' );
			$result['description']    = sprintf(
				'<p>%s</p><p>%s</p>',
				__( 'Unfortunately the mimumum required versions were not met.' ),
				$failed_tests
			);
			$result['badge']['color'] = 'red';
			if ( ! empty( $action_links ) ) {
				$result['actions'] .= sprintf(
					'<p>%s</p>',
					$action_links
				);
			}

		}

		return $result;

	}

	/**
	 * Callback for returning the WP Site Health results for our `constants` tests.
	 *
	 * @return $result
	 */
	function site_health_check_constants() {

		$test_results = $this->check_constants();

		$result = array(
			'label'       => __( 'Required constants were found.', 'trustedlogin-vendor' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'TrustedLogin',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'TrustedLogin Vendor plugin requires a number of constants to work, all were found and checked.', 'trustedlogin-vendor' )
			),
			'actions'     => '',
			'test'        => 'trustedlogin_constants',
		);

		if ( ! $test_results['all_passed'] ) {

			$failed_tests = '';
			$action_links = '';
			foreach ( $test_results as $key => $test_result ) {

				if ( 'all_passed' === $key ) {
					continue;
				}

				if ( ! $test_result ) {
					$title        = ( isset( $this->requirements['constants'][ $key ]['title'] ) )
						? esc_html( $this->requirements['constants'][ $key ]['title'] ) : esc_html( $key );
					$failed_tests .= sprintf(
						__( 'Constant for %s (%s) could not be found/checked. ', 'trustedlogin-vendor' ),
						sprintf( '<strong>%s</strong>', $title ),
						$this->requirements['constants'][ $key ]['callback']
					);
					if ( isset( $this->requirements['constants'][ $key ]['action_url'] ) ) {
						$action_links .= sprintf(
							'<a href="%s">%s</a>',
							esc_url( $this->requirements['constants'][ $key ]['action_url'] ),
							sprintf( __( 'Update %s', 'trustedlogin-vendor' ), $this->requirements['constants'][ $key ]['title'] )
						);
					}
				}
			}

			$result['status']         = 'critical';
			$result['label']          = __( 'Required constants were not found.', 'trustedlogin-vendor' );
			$result['description']    = sprintf(
				'<p>%s</p><p>%s</p>',
				__( 'TrustedLogin Vendor plugin requires a number of constants to work, some could not be found or tested.' ),
				$failed_tests
			);
			$result['badge']['color'] = 'red';
			if ( ! empty( $action_links ) ) {
				$result['actions'] .= sprintf(
					'<p>%s</p>',
					$action_links
				);
			}

		}

		return $result;

	}

	/**
	 * Callback for returning the WP Site Health results for our `functions` tests.
	 *
	 * @return $result
	 */
	function site_health_check_functions() {

		$test_results = $this->check_functions();

		$result = array(
			'label'       => __( 'Required functions are available.', 'trustedlogin-vendor' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'TrustedLogin',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'TrustedLogin requires certain PHP and WordPress functions to work securely. These were all found and tested.', 'trustedlogin-vendor' )
			),
			'actions'     => '',
			'test'        => 'trustedlogin_functions',
		);

		if ( ! $test_results['all_passed'] ) {

			$failed_tests = '';
			$action_links = '';
			foreach ( $test_results as $key => $test_result ) {

				if ( 'all_passed' === $key ) {
					continue;
				}

				if ( ! $test_result ) {
					$title        = ( isset( $this->requirements['functions'][ $key ]['title'] ) )
						? esc_html( $this->requirements['functions'][ $key ]['title'] ) : esc_html( $key );
					$failed_tests .= sprintf(
						__( 'Function %s (%s) could not be found or checked. ', 'trustedlogin-vendor' ),
						sprintf( '<strong>%s</strong>', $title ),
						$this->requirements['functions'][ $key ]['callback']
					);
					if ( isset( $this->requirements['functions'][ $key ]['action_url'] ) ) {
						$action_links .= sprintf(
							'<a href="%s">%s</a>',
							esc_url( $this->requirements['functions'][ $key ]['action_url'] ),
							sprintf( __( 'Update %s', 'trustedlogin-vendor' ), $this->requirements['functions'][ $key ]['title'] )
						);
					}
				}
			}

			$result['status']         = 'critical';
			$result['label']          = __( 'Required modules missing.', 'trustedlogin-vendor' );
			$result['badge']['color'] = 'red';
			$result['description']    = sprintf(
				'<p>%s</p><p>%s</p><pre>%s</pre>',
				__( 'The following functions could not be found or tested.' ),
				$failed_tests,
				print_r( $test_results, true )
			);
			if ( ! empty( $action_links ) ) {
				$result['actions'] .= sprintf(
					'<p>%s</p>',
					$action_links
				);
			}

		}

		return $result;

	}

	/**
	 * Callback for returning the WP Site Health results for our `callbacks` tests.
	 *
	 * @return $result
	 */
	function site_health_check_callbacks() {

		$test_results = $this->check_callbacks();

		$result = array(
			'label'       => __( 'Required callbacks tested.', 'trustedlogin-vendor' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'TrustedLogin',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'TrustedLogin tested built-in callbacks and they are working as expected.', 'trustedlogin-vendor' )
			),
			'actions'     => '',
			'test'        => 'trustedlogin_callbacks',
		);

		if ( ! $test_results['all_passed'] ) {

			$failed_tests = '';
			$action_links = '';
			foreach ( $test_results as $key => $test_result ) {

				if ( 'all_passed' === $key ) {
					continue;
				}

				if ( ! $test_result ) {
					$title        = ( isset( $this->requirements['callbacks'][ $key ]['title'] ) )
						? esc_html( $this->requirements['callbacks'][ $key ]['title'] ) : esc_html( $key );
					$failed_tests .= sprintf(
						__( 'Callback function for %s could not be found or checked. ', 'trustedlogin-vendor' ),
						sprintf( '<strong>%s</strong>', $title )
					);
					if ( isset( $this->requirements['callbacks'][ $key ]['action_url'] ) ) {
						$action_links .= sprintf(
							'<a href="%s">%s</a>',
							esc_url( $this->requirements['callbacks'][ $key ]['action_url'] ),
							sprintf( __( 'Update %s', 'trustedlogin-vendor' ), $this->requirements['callbacks'][ $key ]['title'] )
						);
					}
				}
			}

			$result['status']         = 'critical';
			$result['label']          = __( 'Required callbacks failed.', 'trustedlogin-vendor' );
			$result['description']    = sprintf(
				'<p>%s</p><p>%s</p><pre>%s</pre>',
				__( 'TrustedLogin requires certain URLs be accessible in order to function properly.', 'trustedlogin-vendor' ),
				$failed_tests,
				print_r( $test_results, true )
			);
			$result['badge']['color'] = 'red';
			if ( ! empty( $action_links ) ) {
				$result['actions'] .= sprintf(
					'<p>%s</p>',
					$action_links
				);
			}

		}

		return $result;

	}

	/**
	 * Sets up the required tests array
	 */
	public function set_defaults() {

		/**
		 * Filter: allows for the addition of more healtchecks
		 *
		 * @since 1.0.0
		 *
		 * @param array
		 */
		$this->requirements = apply_filters( 'trustedlogin/vendor/healthcheck/requirements', $this->requirements );

	}

	/**
	 * Adds translateable or dynamic data to the requirements.
	 *
	 * @param array $requirements
	 */
	function set_translateable_data( $requirements ) {

		// versions
		$requirements['versions']['php']['title']            = __( 'PHP', 'trustedlogin-vendor' );
		$requirements['versions']['wordpress']['title']      = __( 'WordPress' );
		$requirements['versions']['wordpress']['action_url'] = admin_url( 'update-core.php' );

		// callbacks
		$requirements['callbacks']['encryption_get_public_key']['title'] = __( 'Encryption::get_public_key', 'trustedlogin-vendor' );

		return $requirements;
	}

	/**
	 * Runs the required checks
	 *
	 * @since 1.0.0
	 *
	 * @param bool  Whether to return all check results.
	 *
	 * @return true|array|WP_Error  True if all tests pass and $return_results is false, else an array of test results.
	 *                                WP_Error if issue.
	 */
	public function run_all_checks( $return_results = false ) {

		$all_tests = array(
			'all_passed' => true,
		);

		foreach ( $this->requirements as $key => $tests ) {
			$callback = 'check_' . $key;
			$results  = $this->$callback( $tests );

			if ( ! $results['all_passed'] ) {

				if ( ! $return_results ) {
					return new WP_Error(
						$key . '_checks_failed',
						sprintf(
							__( '%s checks failed.', 'trustedlogin-vendor' ),
							$key
						)
					);
				}

				$all_tests['all_passed'] = false;
			}

			$all_tests[ $key ] = $results;
		}

		if ( $return_results ) {
			return $all_tests;
		}

		return true;

	}

	public function run_single_check( $slug ) {

		$steps = explode( '/', $slug );

		if ( 2 < count( $steps ) ) {
			return new WP_Error( 'healthcheck-slug-error', __( 'Healthcheck slug not formatted correctly' ) );
		}

		$key   = $steps[0];
		$tests = $steps[1];

		if ( ! array_key_exists( $key, $this->requirements ) ) {
			return new WP_Error( 'tests-error', sprintf( __( 'No tests found for key: %s', 'trustedlogin-vendor' ), $key ) );
		}

		if ( ! array_key_exists( $tests, $this->requirements[ $key ] ) ) {
			return new WP_Error( 'tests-error', sprintf( __( 'No tests found for tests: %s', 'trustedlogin-vendor' ), $tests ) );
		}

		$current_tests = $this->requirements[ $key ];

		foreach ( $current_tests as $current_key => $current_test ) {
			if ( $current_key !== $tests ) {
				unset( $current_tests[ $current_key ] );
			}
		}

		$callback = 'check_' . $key;
		$results  = $this->$callback( $current_tests );

		return $results;


	}

	public function build_notices( $check_results ) {

		$notices = array();

		foreach ( $check_results as $key => $results ) {

			// skip over the all_passed value
			if ( 'all_passed' === $key ) {
				continue;
			}

			// skip over group if all tests passed
			if ( $results['all_passed'] ) {
				continue;
			}

			foreach ( $results as $subkey => $result ) {

				// skip over non-negative results
				if ( $result || 'all_passed' === $subkey ) {
					continue;
				}

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
	static function get_wp_version() {
		global $wp_version;

		return $wp_version;
	}

	/**
	 * Callback for the versions tests
	 *
	 * @var string $key The key for each test.
	 *    [
	 * @var  string $min The minimum version we expect/need.
	 * @var  string $callback The callback to use to fetch the current version string.
	 *    ]
	 * ]
	 *
	 * @var  bool $all_passed Whether all tests in this checker passed
	 * @var  bool  ${ $key }    Results of the tests for $key
	 * ]
	 * @since 1.0.0
	 * @uses version_compare
	 *
	 * @param array $tests Array of version tests  [
	 *
	 * @return array  $results [
	 */
	private function check_versions( $tests = array() ) {

		if ( ! is_array( $tests ) || empty( $tests ) ) {
			$tests = $this->requirements['versions'];
		}

		$results = array(
			'all_passed' => true,
		);

		foreach ( $tests as $key => $test ) {
			$passed = version_compare( $test['callback'](), $test['min'], '>=' );

			if ( ! $passed ) {
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
	 * @var  $function  The function to check.
	 * ]
	 *
	 * @var  bool $all_passed Whether all tests in this checker passed
	 * @var  bool  ${ $key }    Results of the tests for $key
	 * ]
	 * @since 1.0.0
	 * @uses function_exists()
	 *
	 * @param array $tests Array of functions to check [
	 *
	 * @return array  $results [
	 */
	private function check_functions( $tests = array() ) {

		if ( ! is_array( $tests ) || empty( $tests ) ) {
			$tests = $this->requirements['functions'];
		}

		$results = array(
			'all_passed' => true,
		);

		foreach ( $tests as $key => $check ) {

			$passed = function_exists( $check['callback'] );

			if ( ! $passed ) {
				$results['all_passed'] = false;
			}

			$results[ $key ] = $passed;

		}

		$this->dlog( "results: " . print_r( $results, true ), __METHOD__ );

		return $results;
	}

	/**
	 * Callback for the constants tests
	 *
	 * @param array $tests Array of constants to check
	 *
	 * @return array $results {
	 *   @type  bool $all_passed Whether all tests in this checker passed
	 *   @type  bool ${ $key } Results of the tests for $key
	 * }
	 */
	private function check_constants( $tests = array() ) {

		if ( ! is_array( $tests ) || empty( $tests ) ) {
			$tests = $this->requirements['constants'];
		}

		$results = array(
			'all_passed' => true,
		);

		foreach ( $tests as $key => $check ) {

			$passed = defined( $check['callback'] );

			if ( ! $passed ) {
				$results['all_passed'] = false;
			}

			$results[ $key ] = $passed;

		}

		$this->dlog( "results: " . print_r( $results, true ), __METHOD__ );

		return $results;
	}

	/**
	 * Callback for the callbacks tests
	 *
	 * @param array $tests Array of callbacks to check.
	 *
	 * @return array $results {
	 *   @type  bool $all_passed Whether all tests in this checker passed
	 *   @type  bool ${ $key } Results of the tests for $key
	 * }
	 */
	private function check_callbacks( $tests = array() ) {

		if ( ! is_array( $tests ) || empty( $tests ) ) {
			$tests = $this->requirements['callbacks'];
		}

		$results = array(
			'all_passed' => true,
		);

		foreach ( $tests as $key => $check ) {

			if ( is_array( $check['callback'] ) ) {
				$classname = $check['callback'][0];
				$callback  = $check['callback'][1];
			} else {
				$classname = $check['callback'];
				$callback  = false;
			}

			if ( ! class_exists( $classname ) ) {
				$results['all_passed'] = false;
				$results[ $key ]       = false;
				continue;
			}

			if ( ! $callback ) {
				$results[ $key ] = true;
				continue;
			}

			$init_class = new $classname();

			$method_exists = method_exists( $init_class, $callback );

			if ( ! $method_exists ) {
				$results['all_passed'] = false;
				$results[ $key ]       = false;
				continue;
			}

			$passed = $init_class->$callback();

			if ( ! $passed || is_wp_error( $passed ) ) {
				$results['all_passed'] = false;
			}

			$results[ $callback ] = $passed;
		}

		$this->dlog( "results: " . print_r( $results, true ), __METHOD__ );

		return $results;
	}


}
