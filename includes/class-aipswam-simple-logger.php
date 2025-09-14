<?php
/**
 * Simple Logger Class (Database-Free)
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Simple_Logger {

    /**
     * Constructor
     */
    public function __construct() {
        // Make instance globally available
        global $aipswam_logger;
        $aipswam_logger = $this;
    }

    /**
     * Log webhook request/response
     *
     * @param int $post_id Post ID
     * @param string $webhook_url Webhook URL
     * @param string $status Status (success/error)
     * @param int $response_code HTTP response code
     * @param string $message Additional message
     */
    public function log($post_id, $webhook_url, $status, $response_code = null, $message = '') {
        $log_entry = sprintf(
            '[%s] Post ID: %d | URL: %s | Status: %s | Code: %s | Message: %s',
            current_time('mysql'),
            $post_id,
            $webhook_url,
            $status,
            $response_code ? $response_code : 'N/A',
            $message
        );

        error_log('AIPSWAM Webhook: ' . $log_entry);
    }

    /**
     * Get logs (simplified version)
     *
     * @param int $limit Number of logs to retrieve
     * @return array
     */
    public function get_logs($limit = 50) {
        // Return empty array as we're using error_log
        return array();
    }

    /**
     * Clear logs (no-op for simple logger)
     */
    public function clear_logs() {
        // No operation needed for simple logger
        return true;
    }

    /**
     * Static log method for backward compatibility
     *
     * @param int $post_id Post ID
     * @param string $webhook_url Webhook URL
     * @param string $status Status (success/error)
     * @param int $response_code HTTP response code
     * @param string $message Additional message
     */
    public static function log_webhook($post_id, $webhook_url, $status, $response_code = null, $message = '') {
        global $aipswam_logger;
        if ($aipswam_logger) {
            $aipswam_logger->log($post_id, $webhook_url, $status, $response_code, $message);
        }
    }

    /**
     * Get log count (always 0 for simple logger)
     *
     * @return int
     */
    public function get_log_count() {
        return 0;
    }
}