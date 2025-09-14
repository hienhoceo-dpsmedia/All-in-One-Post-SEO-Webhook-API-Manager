<?php
/**
 * Webhook Handler Class
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Webhook_Handler {

    /**
     * Webhook URL
     * @var string
     */
    private $webhook_url;

    /**
     * Webhook secret
     * @var string
     */
    private $webhook_secret;

    /**
     * Cache key prefix
     * @var string
     */
    private $cache_key_prefix = 'aipswam_';

    /**
     * Webhook timeout
     * @var int
     */
    private $webhook_timeout;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('publish_post', array($this, 'handle_new_published_post'), 10, 2);
        add_action('wp_ajax_process_webhook_response', array($this, 'process_webhook_response'));
        add_action('wp_ajax_nopriv_process_webhook_response', array($this, 'process_webhook_response'));
        add_action('wp_ajax_aipswam_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_aipswam_trigger_manual', array($this, 'ajax_trigger_manual'));
        add_action('wp_ajax_aipswam_get_posts', array($this, 'ajax_get_posts'));

        // Add capability check
        add_filter('user_has_cap', array($this, 'check_webhook_capabilities'), 10, 3);

        // Add REST API endpoints if enabled
        if (get_option('aipswam_enable_rest_api', true)) {
            add_action('rest_api_init', array($this, 'register_rest_routes'));
        }

        // Add manual trigger interface if enabled
        if (get_option('aipswam_enable_manual_trigger', true)) {
            add_action('admin_footer', array($this, 'add_manual_trigger_interface'));
        }

        // Make instance globally available
        global $aipswam_webhook_handler;
        $aipswam_webhook_handler = $this;
    }

    /**
     * Initialize plugin
     */
    public function init() {
        $this->webhook_url = get_option('aipswam_webhook_url', '');
        $this->webhook_secret = get_option('aipswam_webhook_secret', wp_generate_password(32, false));
        $this->webhook_timeout = get_option('aipswam_webhook_timeout', 10);
    }

    /**
     * Handle post status changes
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));

        // Check if this post type is enabled
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }

        // Check if this status change should trigger a webhook
        if (!in_array($new_status, $trigger_statuses)) {
            return;
        }

        // Log the transition for debugging
        error_log(sprintf('Post ID %d: Status changed from "%s" to "%s"', $post->ID, $old_status, $new_status));

        // Send webhook for the status change
        error_log(sprintf('Sending webhook for post ID %d - Status: %s', $post->ID, strtoupper($new_status)));
        $this->send_to_webhook($post);
    }

    /**
     * Handle new posts created directly as published
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function handle_new_published_post($post_id, $post) {
        // Only handle posts (not pages or custom post types)
        if ($post->post_type !== 'post') {
            return;
        }

        error_log(sprintf('New published post detected: Post ID %d', $post_id));

        // Use cache-based duplicate prevention
        $cache_key = $this->cache_key_prefix . 'published_' . $post_id;
        $already_sent = get_transient($cache_key);

        if ($already_sent) {
            error_log(sprintf('Skipping duplicate webhook send for post ID %d', $post_id));
            return;
        }

        // Set transient to prevent duplicate sends for 1 minute
        set_transient($cache_key, true, MINUTE_IN_SECONDS);

        $this->send_to_webhook($post);
    }

    /**
     * Send post data to webhook
     *
     * @param WP_Post $post Post object
     */
    private function send_to_webhook($post) {
        if (empty($this->webhook_url)) {
            error_log('Webhook URL not configured');
            return;
        }

        // Check cache to prevent duplicate sends
        $cache_key = $this->cache_key_prefix . 'sent_' . $post->ID;
        $last_sent = get_transient($cache_key);
        if ($last_sent) {
            error_log(sprintf('Skipping duplicate webhook send for post ID %d', $post->ID));
            return;
        }

        // Prepare post data
        $post_data = $this->prepare_post_data($post);

        // Add security headers
        $headers = $this->get_webhook_headers($post_data);

        $args = array(
            'method' => 'POST',
            'timeout' => $this->webhook_timeout,
            'blocking' => false, // Async request for better performance
            'headers' => $headers,
            'body' => json_encode($post_data)
        );

        error_log(sprintf('Sending webhook for post ID %d to URL: %s', $post->ID, $this->webhook_url));

        $response = wp_remote_request($this->webhook_url, $args);

        // Cache the sent timestamp
        set_transient($cache_key, current_time('timestamp'), 5 * MINUTE_IN_SECONDS);

        // If blocking request is needed for immediate response processing
        if ($response && !is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 200) {
                error_log(sprintf('Webhook sent successfully for post ID: %d (Status: %s)', $post->ID, $post->post_status));

                // Process response if it contains keywords
                $response_body = wp_remote_retrieve_body($response);
                if ($response_body) {
                    $this->process_webhook_response_data($response_body);
                }
            } else {
                error_log(sprintf('Webhook failed with response code: %d for post ID: %d', $response_code, $post->ID));
            }
        }
    }

    /**
     * Process webhook response (AJAX endpoint)
     */
    public function process_webhook_response() {
        // Verify nonce for authenticated requests
        if (!current_user_can('manage_options')) {
            // For webhook requests, verify signature
            $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
            if (!$this->verify_webhook_signature($signature)) {
                wp_send_json_error(array('message' => __('Unauthorized', 'all-in-one-post-seo-webhook-api-manager')), 401);
            }
        }

        // Check if any SEO plugin is active
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');
        if (!$this->is_seo_plugin_active($seo_plugin)) {
            wp_send_json_error(array('message' => __('No SEO plugin is active', 'all-in-one-post-seo-webhook-api-manager')), 400);
        }

        // Get and validate input
        $input = file_get_contents('php://input');
        if (empty($input)) {
            wp_send_json_error(array('message' => __('No input data provided', 'all-in-one-post-seo-webhook-api-manager')), 400);
        }

        $this->process_webhook_response_data($input);
    }

    /**
     * Process webhook response data
     *
     * @param string $response_body Response body
     */
    private function process_webhook_response_data($response_body) {
        if (!$this->is_rankmath_active()) {
            error_log('RankMath plugin is not active');
            return;
        }

        $data = json_decode($response_body, true);

        if (!$data || !isset($data['keywords']) || !isset($data['post_id'])) {
            error_log('Invalid webhook response format');
            return;
        }

        $post_id = intval($data['post_id']);
        $keywords = $data['keywords'];

        if (!is_array($keywords) || empty($keywords)) {
            error_log('No keywords provided or invalid format');
            return;
        }

        // Validate post exists
        $post = get_post($post_id);
        if (!$post) {
            error_log(sprintf('Post not found: %d', $post_id));
            return;
        }

        // Set SEO keywords for active plugin
        $result = $this->set_seo_keywords($post_id, $keywords);

        if ($result) {
            error_log(sprintf('SEO keywords set successfully for post ID: %d', $post_id));
            wp_send_json_success(array(
                'message' => __('Keywords set successfully', 'all-in-one-post-seo-webhook-api-manager'),
                'post_id' => $post_id,
                'keywords' => $keywords
            ));
        } else {
            error_log(sprintf('Failed to set RankMath focus keywords for post ID: %d', $post_id));
            wp_send_json_error(array(
                'message' => __('Failed to set keywords', 'all-in-one-post-seo-webhook-api-manager'),
                'post_id' => $post_id
            ));
        }
    }

    /**
     * Set SEO keywords for active plugin
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    private function set_seo_keywords($post_id, $keywords) {
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');

        error_log(sprintf('Setting keywords for post %d using %s: %s', $post_id, $seo_plugin, print_r($keywords, true)));

        switch ($seo_plugin) {
            case 'rankmath':
                return $this->set_rankmath_keywords($post_id, $keywords);
            case 'yoast':
                return $this->set_yoast_keywords($post_id, $keywords);
            case 'both':
                // Try RankMath first, fallback to Yoast
                $rankmath_result = $this->set_rankmath_keywords($post_id, $keywords);
                if ($rankmath_result) {
                    return true;
                }
                return $this->set_yoast_keywords($post_id, $keywords);
            default:
                return false;
        }
    }

    /**
     * Set RankMath focus keywords
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    private function set_rankmath_keywords($post_id, $keywords) {
        if (!$this->is_rankmath_active()) {
            return false;
        }

        // Method 1: Standard single keyword
        $primary_keyword = $keywords[0];
        update_post_meta($post_id, 'rank_math_focus_keyword', $primary_keyword);

        // Method 2: For multiple keywords, try different formats
        if (count($keywords) > 1) {
            // Format 1: Comma-separated string (some RankMath versions)
            $keywords_string = implode(', ', $keywords);
            update_post_meta($post_id, 'rank_math_focus_keyword', $keywords_string);

            // Format 2: Array of additional keywords
            $additional_keywords = array_slice($keywords, 1);
            update_post_meta($post_id, 'rank_math_focus_keywords', $additional_keywords);

            // Format 3: Enable pillar content for multiple keywords
            update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        }

        // Update RankMath internal data
        $this->update_rankmath_internal_data($post_id, $keywords);

        return true;
    }

    /**
     * Set Yoast SEO focus keywords
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    private function set_yoast_keywords($post_id, $keywords) {
        if (!$this->is_yoast_active()) {
            return false;
        }

        // Yoast primarily supports single focus keyword
        $primary_keyword = $keywords[0];
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $primary_keyword);

        // Set additional keywords if available (Yoast Premium feature)
        if (count($keywords) > 1 && defined('WPSEO_PREMIUM')) {
            $additional_keywords = array_slice($keywords, 1);
            update_post_meta($post_id, '_yoast_wpseo_focuskeywords', $additional_keywords);
        }

        // Update Yoast score
        if (function_exists('wpseo_get_value')) {
            // Trigger Yoast analysis refresh
            delete_post_meta($post_id, '_yoast_wpseo_meta-robots-adv');
            delete_post_meta($post_id, '_yoast_wpseo_linkdex');
        }

        return true;
    }

    /**
     * Update RankMath internal data
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     */
    private function update_rankmath_internal_data($post_id, $keywords) {
        // Set timestamp for last update
        update_post_meta($post_id, 'rank_math_keywords_updated', current_time('mysql'));

        // Trigger RankMath reanalysis if possible
        if (function_exists('rank_math_reanalyze_post')) {
            rank_math_reanalyze_post($post_id);
        }
    }

    /**
     * Check if RankMath is active
     *
     * @return bool
     */
    private function is_rankmath_active() {
        return class_exists('RankMath') || function_exists('rank_math');
    }

    /**
     * Check if Yoast SEO is active
     *
     * @return bool
     */
    private function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta');
    }

    /**
     * Check if SEO plugin is active
     *
     * @param string $plugin Plugin name
     * @return bool
     */
    private function is_seo_plugin_active($plugin) {
        switch ($plugin) {
            case 'rankmath':
                return $this->is_rankmath_active();
            case 'yoast':
                return $this->is_yoast_active();
            case 'both':
                return $this->is_rankmath_active() || $this->is_yoast_active();
            default:
                return false;
        }
    }

    /**
     * Get SEO keywords from active plugin
     *
     * @param int $post_id Post ID
     * @return array
     */
    public function get_seo_keywords($post_id) {
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');

        switch ($seo_plugin) {
            case 'rankmath':
                return $this->get_rankmath_keywords($post_id);
            case 'yoast':
                return $this->get_yoast_keywords($post_id);
            case 'both':
                // Try RankMath first, fallback to Yoast
                $rankmath_keywords = $this->get_rankmath_keywords($post_id);
                if (!empty($rankmath_keywords['primary'])) {
                    return $rankmath_keywords;
                }
                return $this->get_yoast_keywords($post_id);
            default:
                return array('primary' => '', 'secondary' => array());
        }
    }

    /**
     * Get RankMath keywords
     *
     * @param int $post_id Post ID
     * @return array
     */
    private function get_rankmath_keywords($post_id) {
        if (!$this->is_rankmath_active()) {
            return array('primary' => '', 'secondary' => array());
        }

        $primary = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        $secondary = get_post_meta($post_id, 'rank_math_focus_keywords', true);

        if (is_string($secondary)) {
            $secondary = array_map('trim', explode(',', $secondary));
        }

        return array(
            'primary' => $primary,
            'secondary' => is_array($secondary) ? $secondary : array()
        );
    }

    /**
     * Get Yoast keywords
     *
     * @param int $post_id Post ID
     * @return array
     */
    private function get_yoast_keywords($post_id) {
        if (!$this->is_yoast_active()) {
            return array('primary' => '', 'secondary' => array());
        }

        $primary = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        $secondary = get_post_meta($post_id, '_yoast_wpseo_focuskeywords', true);

        if (is_string($secondary)) {
            $secondary = array_map('trim', explode(',', $secondary));
        }

        return array(
            'primary' => $primary,
            'secondary' => is_array($secondary) ? $secondary : array()
        );
    }

    /**
     * Prepare post data for webhook
     *
     * @param WP_Post $post Post object
     * @return array
     */
    private function prepare_post_data($post) {
        // Cache post meta queries
        $categories = wp_cache_get('post_categories_' . $post->ID);
        if ($categories === false) {
            $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
            wp_cache_set('post_categories_' . $post->ID, $categories, 12 * HOUR_IN_SECONDS);
        }

        $tags = wp_cache_get('post_tags_' . $post->ID);
        if ($tags === false) {
            $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
            wp_cache_set('post_tags_' . $post->ID, $tags, 12 * HOUR_IN_SECONDS);
        }

        return array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_url' => get_permalink($post->ID),
            'edit_url' => get_edit_post_link($post->ID, 'raw'),
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'categories' => $categories,
            'tags' => $tags,
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'),
            'timestamp' => current_time('mysql'),
            'trigger_event' => $post->post_status === 'publish' ? 'post_published' : 'post_pending'
        );
    }

    /**
     * Get webhook headers with security
     *
     * @param array $data Post data
     * @return array
     */
    private function get_webhook_headers($data) {
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => sprintf('WordPress-AIPSWAM/%s', AIPSWAM_VERSION),
            'X-Webhook-Source' => home_url(),
        );

        if (!empty($this->webhook_secret)) {
            $payload = json_encode($data);
            $signature = hash_hmac('sha256', $payload, $this->webhook_secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        return $headers;
    }

    /**
     * Verify webhook signature
     *
     * @param string $signature Signature from header
     * @return bool
     */
    private function verify_webhook_signature($signature) {
        if (empty($this->webhook_secret) || empty($signature)) {
            return false;
        }

        $payload = file_get_contents('php://input');
        $expected_signature = hash_hmac('sha256', $payload, $this->webhook_secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Check webhook capabilities
     *
     * @param array $allcaps All capabilities
     * @param array $cap Required capability
     * @param array $args Arguments
     * @return array
     */
    public function check_webhook_capabilities($allcaps, $cap, $args) {
        if (isset($cap[0]) && $cap[0] === 'process_webhooks') {
            $allcaps['process_webhooks'] = current_user_can('manage_options');
        }
        return $allcaps;
    }

    /**
     * Manual function to set keywords (for testing)
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    public function set_keywords_manually($post_id, $keywords) {
        return $this->set_seo_keywords($post_id, $keywords);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Get keywords for a post
        register_rest_route('aipswam/v1', '/keywords/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_keywords'),
            'permission_callback' => array($this, 'rest_permission_check'),
            'args' => array(
                'post_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Trigger webhook for a post
        register_rest_route('aipswam/v1', '/webhooks/trigger/(?P<post_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_trigger_webhook'),
            'permission_callback' => array($this, 'rest_permission_check'),
            'args' => array(
                'post_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Get webhook logs
        register_rest_route('aipswam/v1', '/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_logs'),
            'permission_callback' => array($this, 'rest_permission_check'),
            'args' => array(
                'limit' => array(
                    'default' => 50,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                ),
                'offset' => array(
                    'default' => 0,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                )
            )
        ));
    }

    /**
     * REST API permission check
     */
    public function rest_permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * REST API: Get keywords for a post
     */
    public function rest_get_keywords($request) {
        $post_id = $request->get_param('post_id');
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }

        $keywords = $this->get_seo_keywords($post_id);
        return new WP_REST_Response($keywords, 200);
    }

    /**
     * REST API: Trigger webhook for a post
     */
    public function rest_trigger_webhook($request) {
        $post_id = $request->get_param('post_id');
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }

        $result = $this->send_to_webhook($post);

        if ($result) {
            return new WP_REST_Response(array(
                'message' => 'Webhook triggered successfully',
                'post_id' => $post_id
            ), 200);
        } else {
            return new WP_Error('webhook_failed', 'Failed to trigger webhook', array('status' => 500));
        }
    }

    /**
     * REST API: Get webhook logs
     */
    public function rest_get_logs($request) {
        global $aipswam_logger;

        if (!$aipswam_logger) {
            return new WP_Error('logger_not_found', 'Logger not available', array('status' => 500));
        }

        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');

        $logs = $aipswam_logger->get_logs($limit, $offset);
        $total = $aipswam_logger->get_log_count();

        return new WP_REST_Response(array(
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ), 200);
    }

    /**
     * AJAX: Test webhook connection
     */
    public function ajax_test_webhook() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }

        $webhook_url = get_option('aipswam_webhook_url', '');
        if (empty($webhook_url)) {
            wp_send_json_error(array('message' => 'Webhook URL not configured'), 400);
        }

        $test_data = array(
            'test' => true,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url()
        );

        $args = array(
            'method' => 'POST',
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-AIPSWAM/' . AIPSWAM_VERSION
            ),
            'body' => json_encode($test_data)
        );

        $response = wp_remote_request($webhook_url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        wp_send_json_success(array(
            'message' => 'Webhook connection successful',
            'response_code' => $response_code,
            'response_body' => $response_body
        ));
    }

    /**
     * AJAX: Trigger manual webhook
     */
    public function ajax_trigger_manual() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'), 404);
        }

        $result = $this->send_to_webhook($post);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Webhook triggered successfully',
                'post_id' => $post_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to trigger webhook'));
        }
    }

    /**
     * AJAX: Get posts for manual trigger
     */
    public function ajax_get_posts() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }

        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $posts = get_posts(array(
            'post_type' => $enabled_post_types,
            'post_status' => 'any',
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $post_list = array();
        foreach ($posts as $post) {
            $post_list[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'date' => $post->post_date
            );
        }

        wp_send_json_success($post_list);
    }

    /**
     * Add manual trigger interface
     */
    public function add_manual_trigger_interface() {
        // This is handled in the admin page template
    }
}