<?php
/**
 * SECURE Webhook Handler Class with Enhanced Security
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Secure_Webhook_Handler {

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

        // MORE SECURE: Only allow authenticated admin requests
        add_action('wp_ajax_process_webhook_response', array($this, 'process_webhook_response'));

        // Remove nopriv access - this was the security vulnerability
        // add_action('wp_ajax_nopriv_process_webhook_response', array($this, 'process_webhook_response'));

        add_action('wp_ajax_aipswam_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_aipswam_trigger_manual', array($this, 'ajax_trigger_manual'));
        add_action('wp_ajax_aipswam_get_posts', array($this, 'ajax_get_posts'));

        // Add capability check
        add_filter('user_has_cap', array($this, 'check_webhook_capabilities'), 10, 3);

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

        // Initialize random endpoint if not exists
        if (!get_option('aipswam_random_endpoint')) {
            add_option('aipswam_random_endpoint', $this->generate_random_endpoint());
        }

        // Initialize webhook endpoint if not exists
        if (!get_option('aipswam_webhook_endpoint')) {
            add_option('aipswam_webhook_endpoint', 'wh_' . bin2hex(random_bytes(8)));
        }

        // Add REST API endpoints if enabled
        if (get_option('aipswam_enable_rest_api', true)) {
            add_action('rest_api_init', array($this, 'register_rest_routes'));
        }

        // Add manual trigger interface if enabled
        if (get_option('aipswam_enable_manual_trigger', true)) {
            add_action('admin_footer', array($this, 'add_manual_trigger_interface'));
        }
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
        error_log(sprintf('Sending webhook for post ID %d - Status: %s', $post->ID, strtoupper($new_status)));

        $this->send_to_webhook($post);
    }

    /**
     * Handle new published posts
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function handle_new_published_post($post_id, $post) {
        error_log(sprintf('New published post detected: Post ID %d', $post_id));

        // Check if already sent to avoid duplicates
        $cache_key = $this->cache_key_prefix . 'published_' . $post_id;
        if (get_transient($cache_key)) {
            error_log(sprintf('Skipping duplicate webhook send for post ID %d', $post_id));
            return;
        }

        $this->send_to_webhook($post);
        set_transient($cache_key, current_time('timestamp'), 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Send webhook to external service
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
     * Prepare post data for webhook
     *
     * @param WP_Post $post Post object
     * @return array
     */
    private function prepare_post_data($post) {
        $data = array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'author' => $post->post_author,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'permalink' => get_permalink($post->ID),
            'keywords' => $this->get_current_keywords($post->ID)
        );

        // Add custom fields if any
        $custom_fields = get_post_custom($post->ID);
        if (!empty($custom_fields)) {
            $data['custom_fields'] = array();
            foreach ($custom_fields as $key => $values) {
                if (strpos($key, '_') === 0) {
                    continue; // Skip internal fields
                }
                $data['custom_fields'][$key] = $values;
            }
        }

        return $data;
    }

    /**
     * Get current keywords for post - DEBUG VERSION
     *
     * @param int $post_id Post ID
     * @return array
     */
    private function get_current_keywords($post_id) {
        $keywords = array();

        // DEBUG: Add comprehensive logging for keyword retrieval
        error_log('=== KEYWORD RETRIEVAL DEBUG: STARTING FOR POST ' . $post_id . ' ===');

        // Auto-detect active SEO plugin
        if ($this->is_rankmath_active()) {
            error_log('KEYWORD DEBUG: Rank Math is active');

            // Get ALL possible Rank Math keyword fields
            $all_fields = array(
                'rank_math_focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
                'rank_math_focus_keywords' => get_post_meta($post_id, 'rank_math_focus_keywords', true),
                'rank_math_keywords' => get_post_meta($post_id, 'rank_math_keywords', true),
                'rank_math_secondary_keywords' => get_post_meta($post_id, 'rank_math_secondary_keywords', true),
                'rank_math_primary_keyword' => get_post_meta($post_id, 'rank_math_primary_keyword', true)
            );

            error_log('KEYWORD DEBUG: All Rank Math meta fields:');
            foreach ($all_fields as $field => $value) {
                error_log("  - $field: " . substr(print_r($value, true), 0, 200));
            }

            // Primary keyword (standard field)
            $primary = $all_fields['rank_math_focus_keyword'];
            if (!empty($primary)) {
                $keywords[] = $primary;
                error_log('KEYWORD DEBUG: Added primary keyword: ' . $primary);
            }

            // Secondary keywords (JSON format)
            $secondary = $all_fields['rank_math_focus_keywords'];
            if (!empty($secondary)) {
                error_log('KEYWORD DEBUG: Processing secondary keywords from rank_math_focus_keywords');
                $this->debug_parse_rank_math_keywords($secondary, 'rank_math_focus_keywords', $keywords);
            }

            // Alternative fields that might contain keywords
            $alternative_fields = array(
                'rank_math_keywords' => $all_fields['rank_math_keywords'],
                'rank_math_secondary_keywords' => $all_fields['rank_math_secondary_keywords']
            );

            foreach ($alternative_fields as $field_name => $field_value) {
                if (!empty($field_value)) {
                    error_log("KEYWORD DEBUG: Processing alternative field: $field_name");
                    $this->debug_parse_rank_math_keywords($field_value, $field_name, $keywords);
                }
            }

        } elseif ($this->is_yoast_active()) {
            error_log('KEYWORD DEBUG: Yoast is active');

            $primary = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $secondary = get_post_meta($post_id, '_yoast_wpseo_focuskeywords', true);

            error_log('KEYWORD DEBUG: Yoast primary keyword: ' . print_r($primary, true));
            error_log('KEYWORD DEBUG: Yoast secondary keywords: ' . print_r($secondary, true));

            if (!empty($primary)) {
                $keywords[] = $primary;
                error_log('KEYWORD DEBUG: Added Yoast primary keyword: ' . $primary);
            }

            if (!empty($secondary)) {
                error_log('KEYWORD DEBUG: Processing Yoast secondary keywords');
                $this->debug_parse_rank_math_keywords($secondary, '_yoast_wpseo_focuskeywords', $keywords);
            }
        } else {
            error_log('KEYWORD DEBUG: No SEO plugin active');
        }

        $final_keywords = array_unique(array_filter($keywords));
        error_log('KEYWORD DEBUG: Final keywords after deduplication: ' . print_r($final_keywords, true));
        error_log('=== KEYWORD RETRIEVAL DEBUG: COMPLETED ===');

        return $final_keywords;
    }

    /**
     * Debug helper to parse Rank Math keyword formats
     *
     * @param mixed $data The keyword data to parse
     * @param string $source_name Name of the source field
     * @param array &$keywords Reference to keywords array to add to
     */
    private function debug_parse_rank_math_keywords($data, $source_name, &$keywords) {
        error_log("KEYWORD DEBUG: Parsing data from $source_name, type: " . gettype($data));

        if (is_string($data)) {
            // Check if it's JSON format first
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                error_log("KEYWORD DEBUG: $source_name is valid JSON with " . count($decoded) . " items");
                // Extract keywords from JSON format
                foreach ($decoded as $item) {
                    if (isset($item['keyword']) && !empty($item['keyword'])) {
                        $keywords[] = $item['keyword'];
                        error_log("KEYWORD DEBUG: Added keyword from JSON: " . $item['keyword']);
                    } elseif (is_string($item) && !empty($item)) {
                        $keywords[] = $item;
                        error_log("KEYWORD DEBUG: Added string from JSON: " . $item);
                    }
                }
            } else {
                error_log("KEYWORD DEBUG: $source_name is not valid JSON (error: " . json_last_error_msg() . "), treating as string");
                // Fallback: treat as comma-separated string
                $parts = array_map('trim', explode(',', $data));
                foreach ($parts as $part) {
                    if (!empty($part)) {
                        $keywords[] = $part;
                        error_log("KEYWORD DEBUG: Added keyword from comma-separated string: " . $part);
                    }
                }
            }
        } elseif (is_array($data)) {
            error_log("KEYWORD DEBUG: $source_name is already an array with " . count($data) . " items");
            // Already an array, extract keywords
            foreach ($data as $item) {
                if (is_array($item) && isset($item['keyword'])) {
                    $keywords[] = $item['keyword'];
                    error_log("KEYWORD DEBUG: Added keyword from array item: " . $item['keyword']);
                } elseif (is_string($item)) {
                    $keywords[] = $item;
                    error_log("KEYWORD DEBUG: Added string from array: " . $item);
                }
            }
        }
    }

    /**
     * Get webhook headers with security
     *
     * @param array $data Webhook data
     * @return array
     */
    private function get_webhook_headers($data) {
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'AIPSWAM-Webhook/' . AIPSWAM_VERSION
        );

        // Add signature if secret is configured
        if (!empty($this->webhook_secret)) {
            $payload = json_encode($data);
            $signature = hash_hmac('sha256', $payload, $this->webhook_secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        return $headers;
    }

    /**
     * SECURE webhook response processing - ADMIN ONLY
     */
    public function process_webhook_response() {
        // SECURITY: Only allow authenticated administrators
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Administrator privileges required', 'all-in-one-post-seo-webhook-api-manager')), 403);
        }

        // Verify nonce for CSRF protection
        if (!check_ajax_referer('aipswam_webhook_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Invalid security token', 'all-in-one-post-seo-webhook-api-manager')), 403);
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
        $data = json_decode($response_body, true);

        if (!$data || !isset($data['keywords']) || !isset($data['post_id'])) {
            error_log('Invalid webhook response format');
            wp_send_json_error(array('message' => __('Invalid data format', 'all-in-one-post-seo-webhook-api-manager')), 400);
        }

        $post_id = intval($data['post_id']);
        $keywords = $data['keywords'];

        // Validate post exists and user can edit it
        $post = get_post($post_id);
        if (!$post || !current_user_can('edit_post', $post_id)) {
            error_log(sprintf('Invalid post ID or insufficient permissions: %d', $post_id));
            wp_send_json_error(array('message' => __('Invalid post or insufficient permissions', 'all-in-one-post-seo-webhook-api-manager')), 403);
        }

        if (!is_array($keywords) || empty($keywords)) {
            error_log('No keywords provided or invalid format');
            wp_send_json_error(array('message' => __('Invalid keywords format', 'all-in-one-post-seo-webhook-api-manager')), 400);
        }

        // Set keywords using the active SEO plugin
        $result = $this->set_keywords_from_webhook($post_id, $keywords);

        if ($result) {
            error_log(sprintf('SEO keywords set successfully for post ID: %d', $post_id));
            wp_send_json_success(array(
                'message' => __('Keywords updated successfully', 'all-in-one-post-seo-webhook-api-manager'),
                'post_id' => $post_id,
                'keywords' => $keywords
            ));
        } else {
            error_log(sprintf('Failed to set SEO keywords for post ID: %d', $post_id));
            wp_send_json_error(array('message' => __('Failed to update keywords', 'all-in-one-post-seo-webhook-api-manager')), 500);
        }
    }

    /**
     * Set keywords from webhook response
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    public function set_keywords_from_webhook($post_id, $keywords) {
        // Auto-detect active SEO plugin instead of relying on settings
        if ($this->is_rankmath_active()) {
            return $this->set_rankmath_keywords($post_id, $keywords);
        } elseif ($this->is_yoast_active()) {
            return $this->set_yoast_keywords($post_id, $keywords);
        } else {
            // No SEO plugin active
            error_log('No SEO plugin active for keyword update');
            return false;
        }
    }

    /**
     * Set RankMath keywords - DEBUG VERSION
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    private function set_rankmath_keywords($post_id, $keywords) {
        if (!$this->is_rankmath_active()) {
            error_log('RANKMATH DEBUG: Rank Math not active for post ID: ' . $post_id);
            return false;
        }

        try {
            error_log('=== RANKMATH DEBUG: STARTING SIMPLIFIED KEYWORD UPDATE ===');
            error_log('RANKMATH DEBUG: Post ID: ' . $post_id);
            error_log('RANKMATH DEBUG: Keywords received: ' . print_r($keywords, true));

            // Clean and validate keywords
            $keywords = array_filter(array_map('trim', $keywords));
            if (empty($keywords)) {
                error_log('RANKMATH DEBUG: No valid keywords after filtering');
                return false;
            }

            // METHOD 1: Single keyword (primary)
            $primary_keyword = $keywords[0];
            $result1 = update_post_meta($post_id, 'rank_math_focus_keyword', $primary_keyword);
            error_log('RANKMATH DEBUG: Primary keyword set: ' . $primary_keyword . ' (' . ($result1 ? 'SUCCESS' : 'FAILED') . ')');

            // METHOD 2: Multiple keywords handling (simplified approach from working plugin)
            if (count($keywords) > 1) {
                // Format 1: Comma-separated string (some RankMath versions prefer this)
                $keywords_string = implode(', ', $keywords);
                $result2 = update_post_meta($post_id, 'rank_math_focus_keyword', $keywords_string);
                error_log('RANKMATH DEBUG: Comma-separated keywords set: ' . $keywords_string . ' (' . ($result2 ? 'SUCCESS' : 'FAILED') . ')');

                // Format 2: Array of additional keywords (current RankMath format)
                $additional_keywords = array_slice($keywords, 1);
                $result3 = update_post_meta($post_id, 'rank_math_focus_keywords', $additional_keywords);
                error_log('RANKMATH DEBUG: Additional keywords array set: ' . print_r($additional_keywords, true) . ' (' . ($result3 ? 'SUCCESS' : 'FAILED') . ')');

                // Format 3: Enable pillar content for multiple keywords (triggers RankMath display)
                $result4 = update_post_meta($post_id, 'rank_math_pillar_content', 'on');
                error_log('RANKMATH DEBUG: Pillar content enabled: (' . ($result4 ? 'SUCCESS' : 'FAILED') . ')');
            } else {
                // Single keyword - clear additional fields
                update_post_meta($post_id, 'rank_math_focus_keywords', '');
                update_post_meta($post_id, 'rank_math_pillar_content', 'off');
                error_log('RANKMATH DEBUG: Single keyword mode - cleared additional fields');
            }

            // METHOD 3: Update RankMath internal data
            $this->update_rankmath_internal_data($post_id, $keywords);

            // Log final state
            $final_primary = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $final_secondary = get_post_meta($post_id, 'rank_math_focus_keywords', true);
            $final_pillar = get_post_meta($post_id, 'rank_math_pillar_content', true);

            error_log('RANKMATH DEBUG: Final state:');
            error_log('  - Primary keyword: ' . $final_primary);
            error_log('  - Additional keywords: ' . print_r($final_secondary, true));
            error_log('  - Pillar content: ' . $final_pillar);

            return true;
        } catch (Exception $e) {
            error_log('RANKMATH DEBUG: Error in set_rankmath_keywords: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update RankMath internal data (simplified from working plugin)
     */
    private function update_rankmath_internal_data($post_id, $keywords) {
        // Set timestamp for last update
        update_post_meta($post_id, 'rank_math_keywords_updated', current_time('mysql'));
        error_log('RANKMATH DEBUG: Updated timestamp for post ' . $post_id);

        // Trigger RankMath reanalysis if possible
        if (function_exists('rank_math_reanalyze_post')) {
            rank_math_reanalyze_post($post_id);
            error_log('RANKMATH DEBUG: Triggered RankMath reanalysis for post ' . $post_id);
        }

        // Clear any existing RankMath cache
        if (class_exists('RankMath\Cache')) {
            \RankMath\Cache::purge_post($post_id);
            error_log('RANKMATH DEBUG: Cleared RankMath cache for post ' . $post_id);
        }
    }

    /**
     * Set Yoast keywords
     *
     * @param int $post_id Post ID
     * @param array $keywords Keywords array
     * @return bool
     */
    private function set_yoast_keywords($post_id, $keywords) {
        if (!$this->is_yoast_active()) {
            return false;
        }

        $primary_keyword = !empty($keywords[0]) ? $keywords[0] : '';
        $secondary_keywords = array_slice($keywords, 1);

        // Update primary keyword
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $primary_keyword);

        // Store additional keywords in JSON format for consistency
        if (!empty($secondary_keywords)) {
            $secondary_json = array();
            foreach ($secondary_keywords as $keyword) {
                $secondary_json[] = array(
                    'keyword' => $keyword,
                    'score' => 0 // Yoast doesn't use scores but keeping for consistency
                );
            }
            update_post_meta($post_id, '_yoast_wpseo_focuskeywords', wp_json_encode($secondary_json));
        } else {
            // Clear secondary keywords if none provided
            update_post_meta($post_id, '_yoast_wpseo_focuskeywords', '');
        }

        return true;
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
     * Check if RankMath is active
     *
     * @return bool
     */
    private function is_rankmath_active() {
        return class_exists('RankMath') || function_exists('rank_math');
    }

    /**
     * Check if Yoast is active
     *
     * @return bool
     */
    private function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta');
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // SECURE: Simple endpoint to get all posts with SEO keywords
        $random_endpoint = get_option('aipswam_random_endpoint', $this->generate_random_endpoint());
        register_rest_route('aipswam', $random_endpoint, array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_simple_keywords'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));

        // SECURE: Webhook processing endpoint with random string
        $webhook_endpoint = get_option('aipswam_webhook_endpoint', 'wh_' . bin2hex(random_bytes(8)));
        register_rest_route('aipswam', $webhook_endpoint, array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_webhook_processing'),
            'permission_callback' => '__return_true' // Public access with random URL
        ));
    }

    /**
     * REST API permission check
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function rest_permission_check($request) {
        // Allow public access for simple endpoint
        // Note: This is less secure but allows external access without authentication
        return true;
    }

    /**
     * REST API: Get keywords for a post
     */
    public function rest_get_keywords($request) {
        $post_id = $request->get_param('post_id');

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('rest_forbidden', __('You do not have permission to access this resource.'), array('status' => 403));
        }

        $keywords = $this->get_current_keywords($post_id);

        return new WP_REST_Response(array(
            'post_id' => $post_id,
            'keywords' => $keywords,
            'seo_plugin' => get_option('aipswam_seo_plugin', 'rankmath')
        ));
    }

    /**
     * REST API: Trigger webhook for a post
     */
    public function rest_trigger_webhook($request) {
        $post_id = $request->get_param('post_id');

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('rest_forbidden', __('You do not have permission to access this resource.'), array('status' => 403));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('rest_post_invalid', __('Invalid post ID.'), array('status' => 404));
        }

        $this->send_to_webhook($post);

        return new WP_REST_Response(array(
            'message' => __('Webhook triggered successfully', 'all-in-one-post-seo-webhook-api-manager'),
            'post_id' => $post_id
        ));
    }

    /**
     * REST API: Get webhook logs
     */
    public function rest_get_logs($request) {
        global $aipswam_logger;

        if (!$aipswam_logger) {
            return new WP_Error('logger_not_found', __('Logger not available', 'all-in-one-post-seo-webhook-api-manager'), array('status' => 500));
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
        ));
    }

    /**
     * AJAX: Test webhook connection
     */
    public function ajax_test_webhook() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'all-in-one-post-seo-webhook-api-manager')));
        }

        $webhook_url = sanitize_text_field($_POST['webhook_url']);
        $webhook_secret = sanitize_text_field($_POST['webhook_secret']);

        if (empty($webhook_url)) {
            wp_send_json_error(array('message' => __('Webhook URL is required', 'all-in-one-post-seo-webhook-api-manager')));
        }

        // Test data
        $test_data = array(
            'test' => true,
            'timestamp' => current_time('timestamp'),
            'message' => 'Webhook connection test'
        );

        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'AIPSWAM-Test/' . AIPSWAM_VERSION
        );

        if (!empty($webhook_secret)) {
            $signature = hash_hmac('sha256', json_encode($test_data), $webhook_secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        $args = array(
            'method' => 'POST',
            'timeout' => 15,
            'headers' => $headers,
            'body' => json_encode($test_data)
        );

        $response = wp_remote_post($webhook_url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Connection failed', 'all-in-one-post-seo-webhook-api-manager'),
                'error' => $response->get_error_message()
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        wp_send_json_success(array(
            'message' => __('Connection successful', 'all-in-one-post-seo-webhook-api-manager'),
            'response_code' => $response_code,
            'response_body' => $response_body
        ));
    }

    /**
     * AJAX: Trigger webhook manually
     */
    public function ajax_trigger_manual() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'all-in-one-post-seo-webhook-api-manager')));
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'all-in-one-post-seo-webhook-api-manager')));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'all-in-one-post-seo-webhook-api-manager')));
        }

        $this->send_to_webhook($post);

        wp_send_json_success(array(
            'message' => __('Webhook triggered successfully', 'all-in-one-post-seo-webhook-api-manager'),
            'post_id' => $post_id
        ));
    }

    /**
     * AJAX: Get posts for manual trigger
     */
    public function ajax_get_posts() {
        check_ajax_referer('aipswam_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'all-in-one-post-seo-webhook-api-manager')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $search
        );

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = array(
                    'ID' => get_the_ID(),
                    'post_title' => get_the_title(),
                    'post_date' => get_the_date(),
                    'permalink' => get_permalink()
                );
            }
        }

        wp_reset_postdata();

        wp_send_json_success(array(
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page
        ));
    }

    /**
     * Add manual trigger interface
     */
    public function add_manual_trigger_interface() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // This would be implemented in the admin interface
        // For now, we'll skip the implementation to keep it simple
    }

    /**
     * Generate random endpoint
     *
     * @return string
     */
    private function generate_random_endpoint() {
        return 'kw_' . bin2hex(random_bytes(8));
    }

    /**
     * REST API: Webhook processing with random endpoint
     */
    public function rest_webhook_processing($request) {
        $data = $request->get_json_params();

        if (!$data || !isset($data['post_id']) || !isset($data['keywords'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid data format. Required: post_id, keywords'
            ), 400);
        }

        $post_id = intval($data['post_id']);
        $keywords = $data['keywords'];

        // Validate post exists
        $post = get_post($post_id);
        if (!$post) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Post not found'
            ), 404);
        }

        // Set keywords
        $result = $this->set_keywords_from_webhook($post_id, $keywords);

        if ($result) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Keywords updated successfully',
                'post_id' => $post_id,
                'keywords' => $keywords
            ));
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update keywords'
            ), 500);
        }
    }

    /**
     * REST API: Simple get all posts with SEO keywords
     */
    public function rest_get_simple_keywords($request) {
        try {
            // Auto-detect active SEO plugin
            $active_plugin = 'none';
        if ($this->is_rankmath_active()) {
            $active_plugin = 'rankmath';
        } elseif ($this->is_yoast_active()) {
            $active_plugin = 'yoast';
        }

        // Get all published posts
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get all posts
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);
        $keywords_data = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();

                // Get keywords based on active plugin
                if ($active_plugin === 'rankmath') {
                    // Get primary keyword
                    $primary = get_post_meta($post_id, 'rank_math_focus_keyword', true);
                    if (!empty($primary)) {
                        $keywords_data[] = array(
                            'post_id' => $post_id,
                            'title' => $title,
                            'keyword' => $primary
                        );
                        error_log("Found primary keyword for post $post_id: $primary");
                    }

                    // Get additional/related keywords from multiple possible Rank Math fields
                    $additional_sources = array(
                        'rank_math_focus_keywords',  // JSON format with focus keywords
                        'rank_math_keywords',         // General keywords field
                        'rank_math_secondary_keywords' // Additional keywords field
                    );

                    foreach ($additional_sources as $meta_key) {
                        $additional = get_post_meta($post_id, $meta_key, true);
                        if (!empty($additional)) {
                            error_log("Found keywords in $meta_key for post $post_id: " . substr($additional, 0, 200));

                            if (is_string($additional)) {
                                // Check if it's JSON format
                                $decoded = json_decode($additional, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    // It's JSON, extract keywords
                                    foreach ($decoded as $item) {
                                        if (isset($item['keyword']) && !empty($item['keyword'])) {
                                            $keywords_data[] = array(
                                                'post_id' => $post_id,
                                                'title' => $title,
                                                'keyword' => $item['keyword']
                                            );
                                        }
                                    }
                                } else {
                                    // It's not JSON, split by commas and clean up
                                    $additional_keywords = array_map('trim', explode(',', $additional));
                                    foreach ($additional_keywords as $keyword) {
                                        if (!empty($keyword) && strpos($keyword, '{"') === false && strpos($keyword, '"score"') === false) {
                                            $keywords_data[] = array(
                                                'post_id' => $post_id,
                                                'title' => $title,
                                                'keyword' => $keyword
                                            );
                                        }
                                    }
                                }
                            } elseif (is_array($additional)) {
                                foreach ($additional as $keyword) {
                                    if (!empty($keyword)) {
                                        $keywords_data[] = array(
                                            'post_id' => $post_id,
                                            'title' => $title,
                                            'keyword' => $keyword
                                        );
                                    }
                                }
                            }
                        }
                    }
                } elseif ($active_plugin === 'yoast') {
                    // Get primary keyword
                    $primary = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
                    if (!empty($primary)) {
                        $keywords_data[] = array(
                            'post_id' => $post_id,
                            'title' => $title,
                            'keyword' => $primary
                        );
                    }

                    // Get additional keywords (if stored in custom field)
                    $additional = get_post_meta($post_id, '_yoast_wpseo_focuskeywords', true);
                    if (!empty($additional)) {
                        if (is_string($additional)) {
                            // Check if it's JSON format
                            $decoded = json_decode($additional, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                // It's JSON, extract keywords
                                foreach ($decoded as $item) {
                                    if (isset($item['keyword']) && !empty($item['keyword'])) {
                                        $keywords_data[] = array(
                                            'post_id' => $post_id,
                                            'title' => $title,
                                            'keyword' => $item['keyword']
                                        );
                                    }
                                }
                            } else {
                                // It's not JSON, split by commas and clean up
                                $additional_keywords = array_map('trim', explode(',', $additional));
                                foreach ($additional_keywords as $keyword) {
                                    if (!empty($keyword) && strpos($keyword, '{"') === false && strpos($keyword, '"score"') === false) {
                                        $keywords_data[] = array(
                                            'post_id' => $post_id,
                                            'title' => $title,
                                            'keyword' => $keyword
                                        );
                                    }
                                }
                            }
                        } elseif (is_array($additional)) {
                            foreach ($additional as $keyword) {
                                if (!empty($keyword)) {
                                    $keywords_data[] = array(
                                        'post_id' => $post_id,
                                        'title' => $title,
                                        'keyword' => $keyword
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        wp_reset_postdata();

        return new WP_REST_Response($keywords_data);
        } catch (Exception $e) {
            return new WP_REST_Response(array('error' => $e->getMessage()), 500);
        }
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
}