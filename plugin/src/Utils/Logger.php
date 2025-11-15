<?php
declare(strict_types=1);

namespace KobGitUpdater\Utils;

/**
 * Logger utility for debugging and monitoring
 */
class Logger
{
    private string $prefix;

    public function __construct(string $prefix = 'kob_git_updater')
    {
        $this->prefix = $prefix;
    }

    /**
     * Log error messages
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log info messages
     */
    public function info(string $message, array $context = []): void
    {
        if (!$this->shouldLogInfo()) {
            return;
        }
        
        $this->log('INFO', $message, $context);
    }

    /**
     * Log debug messages
     */
    public function debug(string $message, array $context = []): void
    {
        if (!$this->shouldLogDebug()) {
            return;
        }
        
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log warning messages
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Internal log method
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $logMessage = sprintf('[%s][%s] %s', $this->prefix, $level, $message);
        
        if (!empty($context)) {
            $logMessage .= ' Context: ' . wp_json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Check if logging is enabled
     */
    private function shouldLog(): bool
    {
        return defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }

    /**
     * Check if info logging is enabled
     */
    private function shouldLogInfo(): bool
    {
        return $this->shouldLog() && defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if debug logging is enabled
     */
    private function shouldLogDebug(): bool
    {
        return $this->shouldLogInfo() && 
               (defined('GIU_DEBUG') && GIU_DEBUG);
    }
}