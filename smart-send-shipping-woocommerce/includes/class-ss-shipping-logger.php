<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SS_Shipping_Logger {

	/**
	 * @var String
	 */
	private $debug;

	/**
	 * WC_SS_Shipping_Logger constructor.
	 *
	 * @param WC_XR_debug $debug
	 */
	public function __construct( $debug ) {
		$this->debug = $debug;
	}

	/**
	 * Check if logging is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {

		// Check if debug is on
		if ( 'yes' === $this->debug ) {
			return true;
		}

		return false;
	}

	/**
	 * Write the message to log
	 *
	 * @param String $message
	 */
	public function write( $message ) {

		// Check if enabled
		if ( $this->is_enabled() ) {

			// Logger object
			$wc_logger = new WC_Logger();

			// Add to logger
			$wc_logger->add( 'SS_Shipping_', $message );
		}

	}

	public function get_log_url() {
		$log_path = wc_get_log_file_path( 'SS_Shipping_' );
		$upload_path = wp_upload_dir();

		$log_url = str_replace( $upload_path['basedir'], $upload_path['baseurl'], $log_path );

		return $log_url;
	}

}