<?php
/**
 * Simple Logger Class - No Database Required
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Simple_Logger {

    /**
     * Simple logging to error log instead of database
     *
     * @param string $message Log message
     * @param string $level Log level
     */
    public static function log($message, $level = 'info') {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('[AIPSWAM ' . strtoupper($level) . '] ' . $message);
        }
    }

    /**
     * Log webhook success
     *
     * @param int $post_id Post ID
     * @param string $webhook_url Webhook URL
     */
    public static function log_webhook_success($post_id, $webhook_url) {
        self::log("Webhook sent successfully for post ID {$post_id} to {$webhook_url}", 'success');
    }

    /**
     * Log webhook error
     *
     * @param int $post_id Post ID
     * @param string $webhook_url Webhook URL
     * @param string $error Error message
     */
    public static function log_webhook_error($post_id, $webhook_url, $error) {
        self::log("Webhook failed for post ID {$post_id} to {$webhook_url}: {$error}", 'error');
    }

    /**
     * Log debug information
     *
     * @param string $message Debug message
     */
    public static function log_debug($message) {
        self::log($message, 'debug');
    }

    /**
     * Get log count (returns 0 for simple logger)
     *
     * @return int
     */
    public static function get_log_count() {
        return 0;
    }

    /**
     * Get logs (returns empty array for simple logger)
     *
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @return array
     */
    public static function get_logs($limit = 50, $offset = 0) {
        return array();
    }

    /**
     * Constructor for backward compatibility
     */
    public function __construct() {
        // Make instance globally available for backward compatibility
        global $aipswam_logger;
        $aipswam_logger = $this;
    }

    /**
     * Instance methods that delegate to static methods
     */
    public function log($post_id, $webhook_url, $status, $response_code = null, $message = '') {
        if ($status === 'success') {
            self::log_webhook_success($post_id, $webhook_url);
        } else {
            self::log_webhook_error($post_id, $webhook_url, $message ?: "Response code: {$response_code}");
        }
    }

    public function get_logs($limit = 50, $offset = 0) {
        return self::get_logs($limit, $offset);
    }

    public function get_log_count() {
        return self::get_log_count();
    }
}