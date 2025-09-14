<?php
/**
 * Logger Class
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Logger {

    /**
     * Table name
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'webhook_logs';

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('aipswam_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'aipswam_cleanup_logs');
        }

        // Add cleanup action
        add_action('aipswam_cleanup_logs', array($this, 'cleanup_old_logs'));

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
        global $wpdb;

        $data = array(
            'post_id' => $post_id,
            'webhook_url' => $webhook_url,
            'status' => $status,
            'response_code' => $response_code,
            'created_at' => current_time('mysql')
        );

        $format = array('%d', '%s', '%s', '%d', '%s');

        $wpdb->insert($this->table_name, $data, $format);
    }

    /**
     * Get logs with pagination
     *
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @return array
     */
    public function get_logs($limit = 50, $offset = 0) {
        global $wpdb;

        $limit = intval($limit);
        $offset = intval($offset);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get total log count
     *
     * @return int
     */
    public function get_log_count() {
        global $wpdb;

        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Get logs for a specific post
     *
     * @param int $post_id Post ID
     * @param int $limit Number of logs to retrieve
     * @return array
     */
    public function get_logs_for_post($post_id, $limit = 10) {
        global $wpdb;

        $post_id = intval($post_id);
        $limit = intval($limit);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE post_id = %d
            ORDER BY created_at DESC
            LIMIT %d",
            $post_id,
            $limit
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Delete old logs (older than 30 days)
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            )
        );
    }

    /**
     * Delete logs for a specific post
     *
     * @param int $post_id Post ID
     */
    public function delete_logs_for_post($post_id) {
        global $wpdb;

        $wpdb->delete(
            $this->table_name,
            array('post_id' => $post_id),
            array('%d')
        );
    }

    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Get log statistics
     *
     * @return array
     */
    public function get_log_stats() {
        global $wpdb;

        $stats = array();

        // Total logs
        $stats['total'] = $this->get_log_count();

        // Success count
        $stats['success'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'success'"
        );

        // Error count
        $stats['error'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'error'"
        );

        // Recent logs (last 24 hours)
        $stats['recent'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
                24
            )
        );

        return $stats;
    }
}