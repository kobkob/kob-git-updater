<?php
declare(strict_types=1);

namespace KobGitUpdater\Utils;

/**
 * Logger utility for debugging and monitoring
 */
class Logger {

	private string $prefix;

	public function __construct( string $prefix = 'kob_git_updater' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Log error messages
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Log info messages
	 */
	public function info( string $message, array $context = array() ): void {
		if ( ! $this->should_log_info() ) {
			return;
		}

		$this->log( 'INFO', $message, $context );
	}

	/**
	 * Log debug messages
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->should_log_debug() ) {
			return;
		}

		$this->log( 'DEBUG', $message, $context );
	}

	/**
	 * Log warning messages
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * Internal log method
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->should_log() ) {
			return;
		}

		$log_message = sprintf( '[%s][%s] %s', $this->prefix, $level, $message );

		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Check if logging is enabled
	 */
	private function should_log(): bool {
		return defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Check if info logging is enabled
	 */
	private function should_log_info(): bool {
		return $this->should_log() && defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Check if debug logging is enabled
	 */
	private function should_log_debug(): bool {
		return $this->should_log_info() &&
				( defined( 'GIU_DEBUG' ) && GIU_DEBUG );
	}
}
