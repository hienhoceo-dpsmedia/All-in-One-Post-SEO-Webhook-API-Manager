<?php
/**
 * Simple Logger Class for All-in-One Post SEO Webhook & API Manager
 *
 * @package AIPSWAM
 * @version 2.2.9
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Logger Class
 */
class AIPSWAM_Simple_Logger {

    /**
     * Log directory
     *
     * @var string
     */
    private $log_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/aipswam-logs/';

        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level (info, error, warning)
     * @return void
     */
    public function log($message, $level = 'info') {
        $log_file = $this->log_dir . 'aipswam-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Log info message
     *
     * @param string $message Info message
     * @return void
     */
    public function info($message) {
        $this->log($message, 'info');
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @return void
     */
    public function error($message) {
        $this->log($message, 'error');
    }

    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @return void
     */
    public function warning($message) {
        $this->log($message, 'warning');
    }

    /**
     * Get log entries
     *
     * @param int $limit Number of entries to retrieve
     * @return array
     */
    public function get_logs($limit = 100) {
        $log_file = $this->log_dir . 'aipswam-' . date('Y-m-d') . '.log';

        if (!file_exists($log_file)) {
            return array();
        }

        $logs = array();
        $lines = file($log_file);

        if ($lines) {
            $lines = array_slice($lines, -$limit);

            foreach ($lines as $line) {
                $logs[] = trim($line);
            }
        }

        return $logs;
    }

    /**
     * Clear logs
     *
     * @return void
     */
    public function clear_logs() {
        $log_files = glob($this->log_dir . 'aipswam-*.log');

        foreach ($log_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}