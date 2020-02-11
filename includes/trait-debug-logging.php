<?php

// v2.0.0

trait TL_Debug_Logging {

	/**
	 * Helper that checks if debugging is enabled in current and deprecated formats.
	 *
	 * @since 0.9.0
	 *
	 * @return bool
	 */
	function debugging_enabled() {

		if ( property_exists( $this, 'settings' ) ) {
			return (bool) $this->settings->debug_mode_enabled();
		}

		if ( property_exists( $this, 'debug_mode' ) ) {
			return (bool) $this->debug_mode;
		}
	}

	/**
	 * Plugin Helper: Debug logging within the plugin folder.
	 *
	 * @since 0.1.0
	 *
	 * @param String $text
	 *
	 * @return void
	 */
	function dlog( $text, $method = null ) {

		if ( ! $this->debugging_enabled() ) {
			return;
		}
		// open log file
		try {
			$filename = "tl-debug-log.txt";
			$fh       = fopen( plugin_dir_path( dirname( __FILE__ ) ) . $filename, "a" );

			if ( false == $fh ) {
				error_log( __METHOD__ . " - Could not open log file: " . plugin_dir_path( __FILE__ ) . $filename, 0 );
				throw new Exception( '(ewi) Could not open log file.' );
			}

			if ( ! is_null( $method ) ) {
				$text = '' . $method . ' => ' . $text;
			}

			$fw = fwrite( $fh, date( "d-m-Y, H:i" ) . " - $text\n" );

			if ( false == $fw ) {
				error_log( __METHOD__ . " - Could not write file!", 0 );
			} else {
				fclose( $fh );
			}

		} catch ( Exception $e ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( __METHOD__ . ' - ' . $text );
			}
		}

	}
}