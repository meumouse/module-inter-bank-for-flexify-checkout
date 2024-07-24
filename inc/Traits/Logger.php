<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Traits;

defined('ABSPATH') || exit;

/**
 * 
 */
trait Logger {
  /**
   * $source
   *
   * @var string
   */
  public $source;

  /**
   * Save only critical logs
   *
   * @var string
   */
  public $critical_only;


  /**
   * $log
   *
   * @var WC_Logger
   */
  public static $log;

  /**
   * 
   */
  public function set_logger_source( $set, $critical_only = true ) {
    $this->source = $set;
    $this->critical_only = $critical_only;
  }


  /**
	 * Log an event
	 *
   * @since 1.0.0
	 * @param string $message | Log message
	 * @param string $level | Optional, defaults to info, valid levels: emergency|alert|critical|error|warning|notice|info|debug.
	 */
  public function log( $message, $level = 'info' ) {
    if ( ! $this->source ) {
      return;
    }

    if ( $this->critical_only && ! in_array( $level, [ 'emergency', 'alert', 'critical' ] ) ) {
      return;
    }

    $message = is_string( $message ) ? $message : print_r( $message, true );

		if ( ! isset( self::$log ) ) {
			self::$log = wc_get_logger();
		}

		self::$log->log( $level, $message, array( 'source' => $this->source ) );
  }
}