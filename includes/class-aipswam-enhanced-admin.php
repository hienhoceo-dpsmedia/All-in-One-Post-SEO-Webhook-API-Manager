<?php
/**
 * Enhanced Admin Class with better UX
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Enhanced_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'All-in-One Post SEO Webhook & API Manager',
            'SEO Webhook API',
            'manage_options',
            'aipswam-settings',
            array($this, 'admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aipswam_settings', 'aipswam_webhook_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));

        register_setting('aipswam_settings', 'aipswam_webhook_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting('aipswam_settings', 'aipswam_enabled_post_types', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_post_types_array'),
            'default' => array('post'),
        ));

        register_setting('aipswam_settings', 'aipswam_trigger_statuses', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_statuses_array'),
            'default' => array('pending', 'publish'),
        ));

        register_setting('aipswam_settings', 'aipswam_seo_plugin', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'rankmath',
        ));

        register_setting('aipswam_settings', 'aipswam_webhook_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook === 'settings_page_aipswam-settings') {
            wp_enqueue_style('aipswam-admin', AIPSWAM_PLUGIN_URL . 'assets/css/admin.css', array(), AIPSWAM_VERSION);
        }
    }

    /**
     * Sanitize post types array
     */
    public function sanitize_post_types_array($value) {
        if (!is_array($value)) {
            return array('post');
        }

        $valid_post_types = get_post_types(array('public' => true));
        return array_intersect($value, array_keys($valid_post_types));
    }

    /**
     * Sanitize statuses array
     */
    public function sanitize_statuses_array($value) {
        if (!is_array($value)) {
            return array('pending', 'publish');
        }

        $valid_statuses = array_keys(get_post_statuses());
        return array_intersect($value, $valid_statuses);
    }

    /**
     * Get API endpoints
     */
    private function get_api_endpoints() {
        $endpoints = array();

        // Webhook processing endpoint
        $endpoints[] = array(
            'name' => 'Webhook Processing',
            'method' => 'POST',
            'url' => admin_url('admin-ajax.php?action=process_webhook_response'),
            'description' => 'Process webhook responses containing keyword data'
        );

        // REST API endpoints (if available)
        if (function_exists('rest_get_url_prefix')) {
            $rest_base = rest_get_url_prefix();

            $endpoints[] = array(
                'name' => 'Get Keywords',
                'method' => 'GET',
                'url' => home_url("{$rest_base}/aipswam/v1/keywords/{post_id}"),
                'description' => 'Retrieve SEO keywords for a specific post'
            );

            $endpoints[] = array(
                'name' => 'Trigger Webhook',
                'method' => 'POST',
                'url' => home_url("{$rest_base}/aipswam/v1/webhooks/trigger/{post_id}"),
                'description' => 'Manually trigger webhook for a specific post'
            );
        }

        return $endpoints;
    }

    /**
     * Get keyword update guide
     */
    private function get_keyword_update_guide() {
        $guide = array();

        // Method 1: Webhook Response
        $guide[] = array(
            'method' => 'Webhook Response',
            'description' => 'Send keywords back to WordPress via webhook response',
            'endpoint' => admin_url('admin-ajax.php?action=process_webhook_response'),
            'format' => 'JSON',
            'example' => array(
                'post_id' => 123,
                'keywords' => array('primary keyword', 'secondary keyword 1', 'secondary keyword 2')
            ),
            'security' => 'Include X-Webhook-Signature header with HMAC-SHA256'
        );

        // Method 2: Direct Function Call
        $guide[] = array(
            'method' => 'Direct Function Call',
            'description' => 'Use the built-in function to set keywords programmatically',
            'function' => 'aipswam_set_keywords($post_id, $keywords)',
            'example' => "<?php\naipswam_set_keywords(123, array('SEO keyword', 'another keyword'));\n?>",
            'note' => 'Available globally after plugin activation'
        );

        // Method 3: Manual via Admin
        $guide[] = array(
            'method' => 'Manual via Post Editor',
            'description' => 'Set keywords directly using RankMath or Yoast SEO plugins',
            'steps' => array(
                'Edit the post in WordPress admin',
                'Scroll down to SEO section (RankMath or Yoast)',
                'Enter focus keyword and additional keywords',
                'Save/Update the post'
            ),
            'note' => 'Requires RankMath or Yoast SEO plugin to be active'
        );

        return $guide;
    }

    /**
     * Admin page with enhanced UI
     */
    public function admin_page() {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');
        $webhook_timeout = get_option('aipswam_webhook_timeout', 10);
        $webhook_url = get_option('aipswam_webhook_url', '');
        $api_endpoints = $this->get_api_endpoints();
        $keyword_guide = $this->get_keyword_update_guide();
        ?>
        <div class="wrap">
            <div class="aipswam-header">
                <h1>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php echo esc_html__('All-in-One Post SEO Webhook & API Manager', 'all-in-one-post-seo-webhook-api-manager'); ?>
                </h1>
                <p class="aipswam-subtitle">
                    <?php echo esc_html__('Automate your SEO workflow with webhook triggers and API integration', 'all-in-one-post-seo-webhook-api-manager'); ?>
                </p>
            </div>

            <div class="aipswam-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active" data-target="general">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configuration', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#triggers" class="nav-tab" data-target="triggers">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Triggers', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#api" class="nav-tab" data-target="api">
                        <span class="dashicons dashicons-rest-api"></span>
                        <?php _e('API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#keywords" class="nav-tab" data-target="keywords">
                        <span class="dashicons dashicons-edit-page"></span>
                        <?php _e('Keyword Guide', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#webhook-format" class="nav-tab" data-target="webhook-format">
                        <span class="dashicons dashicons-code-standards"></span>
                        <?php _e('Webhook Format', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                </nav>

                <div class="tab-content">
                    <!-- Configuration Tab -->
                    <div id="general" class="tab-pane active">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('Basic Configuration', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                            <form method="post" action="options.php">
                                <?php settings_fields('aipswam_settings'); ?>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_webhook_url">
                                                <span class="dashicons dashicons-admin-links"></span>
                                                <?php echo esc_html__('Webhook URL', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="url" id="aipswam_webhook_url" name="aipswam_webhook_url"
                                                   value="<?php echo esc_attr($webhook_url); ?>"
                                                   class="regular-text" placeholder="https://your-webhook-url.com/handler"
                                                   <?php echo empty($webhook_url) ? '' : 'readonly'; ?> />
                                            <p class="description">
                                                <?php echo esc_html__('Enter the webhook URL where posts will be sent', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                            <?php if (!empty($webhook_url)): ?>
                                                <div class="aipswam-status aipswam-status-success">
                                                    <span class="dashicons dashicons-yes-alt"></span>
                                                    <?php echo esc_html__('Webhook URL is configured', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_webhook_secret">
                                                <span class="dashicons dashicons-lock"></span>
                                                <?php echo esc_html__('Webhook Secret', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="aipswam_webhook_secret" name="aipswam_webhook_secret"
                                                   value="<?php echo esc_attr(get_option('aipswam_webhook_secret', '')); ?>"
                                                   class="regular-text" />
                                            <p class="description">
                                                <?php echo esc_html__('Optional: Secret key for webhook authentication (HMAC-SHA256)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                            <button type="button" class="button button-secondary" onclick="generateSecret()">
                                                <span class="dashicons dashicons-refresh"></span>
                                                <?php echo esc_html__('Generate New Secret', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_webhook_timeout">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php echo esc_html__('Timeout', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="number" id="aipswam_webhook_timeout" name="aipswam_webhook_timeout"
                                                   value="<?php echo esc_attr($webhook_timeout); ?>"
                                                   min="1" max="30" />
                                            <p class="description">
                                                <?php echo esc_html__('Webhook request timeout in seconds (1-30)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button(__('Save Configuration', 'all-in-one-post-seo-webhook-api-manager'), 'primary', 'submit', true, array('id' => 'save-config')); ?>
                            </form>
                        </div>
                    </div>

                    <!-- Triggers Tab -->
                    <div id="triggers" class="tab-pane">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('Webhook Triggers', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                            <form method="post" action="options.php">
                                <?php settings_fields('aipswam_settings'); ?>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_enabled_post_types">
                                                <span class="dashicons dashicons-post-status"></span>
                                                <?php echo esc_html__('Enabled Post Types', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="aipswam-checkbox-group">
                                                <?php
                                                $post_types = get_post_types(array('public' => true), 'objects');
                                                foreach ($post_types as $post_type) {
                                                    $checked = in_array($post_type->name, $enabled_post_types) ? 'checked' : '';
                                                    echo '<label class="aipswam-checkbox-label">';
                                                    echo '<input type="checkbox" name="aipswam_enabled_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . '>';
                                                    echo '<span class="aipswam-checkbox-text">' . esc_html($post_type->labels->name) . '</span>';
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                            <p class="description">
                                                <?php echo esc_html__('Select which post types should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_trigger_statuses">
                                                <span class="dashicons dashicons-flag"></span>
                                                <?php echo esc_html__('Trigger Statuses', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="aipswam-checkbox-group">
                                                <?php
                                                $statuses = get_post_statuses();
                                                foreach ($statuses as $status => $label) {
                                                    $checked = in_array($status, $trigger_statuses) ? 'checked' : '';
                                                    echo '<label class="aipswam-checkbox-label">';
                                                    echo '<input type="checkbox" name="aipswam_trigger_statuses[]" value="' . esc_attr($status) . '" ' . $checked . '>';
                                                    echo '<span class="aipswam-checkbox-text">' . esc_html($label) . '</span>';
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                            <p class="description">
                                                <?php echo esc_html__('Select which post status changes should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_seo_plugin">
                                                <span class="dashicons dashicons-search"></span>
                                                <?php echo esc_html__('SEO Plugin', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <select id="aipswam_seo_plugin" name="aipswam_seo_plugin" class="regular-text">
                                                <option value="rankmath" <?php selected($seo_plugin, 'rankmath'); ?>>
                                                    <?php echo esc_html__('RankMath SEO', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                                <option value="yoast" <?php selected($seo_plugin, 'yoast'); ?>>
                                                    <?php echo esc_html__('Yoast SEO', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                                <option value="both" <?php selected($seo_plugin, 'both'); ?>>
                                                    <?php echo esc_html__('Both (Priority: RankMath)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                            </select>
                                            <p class="description">
                                                <?php echo esc_html__('Choose which SEO plugin to use for keyword integration', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button(__('Save Trigger Settings', 'all-in-one-post-seo-webhook-api-manager'), 'primary', 'submit', true, array('id' => 'save-triggers')); ?>
                            </form>
                        </div>
                    </div>

                    <!-- API Endpoints Tab -->
                    <div id="api" class="tab-pane">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('Available API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                            <div class="aipswam-endpoints-list">
                                <?php foreach ($api_endpoints as $endpoint): ?>
                                    <div class="aipswam-endpoint-item">
                                        <div class="aipswam-endpoint-header">
                                            <h3><?php echo esc_html($endpoint['name']); ?></h3>
                                            <span class="aipswam-method aipswam-method-<?php echo strtolower($endpoint['method']); ?>">
                                                <?php echo esc_html($endpoint['method']); ?>
                                            </span>
                                        </div>
                                        <div class="aipswam-endpoint-url">
                                            <code><?php echo esc_html($endpoint['url']); ?></code>
                                            <button type="button" class="button button-small aipswam-copy-btn" data-url="<?php echo esc_attr($endpoint['url']); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php echo esc_html__('Copy', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </button>
                                        </div>
                                        <p class="aipswam-endpoint-description">
                                            <?php echo esc_html($endpoint['description']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="aipswam-info-box">
                                <h3><?php echo esc_html__('Authentication', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                                <p><?php echo esc_html__('For secure webhook processing, include the X-Webhook-Signature header with HMAC-SHA256 signature using your webhook secret.', 'all-in-one-post-seo-webhook-api-manager'); ?></p>
                                <code>X-Webhook-Signature: sha256=<?php echo esc_html(hash_hmac('sha256', 'payload', 'your-secret-key')); ?></code>
                            </div>
                        </div>
                    </div>

                    <!-- Keyword Guide Tab -->
                    <div id="keywords" class="tab-pane">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('How to Update Keywords', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>

                            <?php foreach ($keyword_guide as $method): ?>
                                <div class="aipswam-guide-method">
                                    <h3><?php echo esc_html($method['method']); ?></h3>
                                    <p><?php echo esc_html($method['description']); ?></p>

                                    <?php if (isset($method['endpoint'])): ?>
                                        <div class="aipswam-endpoint-url">
                                            <strong><?php echo esc_html__('Endpoint:'); ?></strong>
                                            <code><?php echo esc_html($method['endpoint']); ?></code>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($method['function'])): ?>
                                        <div class="aipswam-code-block">
                                            <strong><?php echo esc_html__('Function:'); ?></strong>
                                            <pre><code><?php echo esc_html($method['function']); ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($method['example']) && is_array($method['example'])): ?>
                                        <div class="aipswam-code-block">
                                            <strong><?php echo esc_html__('Example Format:'); ?></strong>
                                            <pre><code><?php echo json_encode($method['example'], JSON_PRETTY_PRINT); ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($method['example']) && is_string($method['example'])): ?>
                                        <div class="aipswam-code-block">
                                            <strong><?php echo esc_html__('Example:'); ?></strong>
                                            <pre><code><?php echo esc_html($method['example']); ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($method['steps'])): ?>
                                        <div class="aipswam-steps">
                                            <strong><?php echo esc_html__('Steps:'); ?></strong>
                                            <ol>
                                                <?php foreach ($method['steps'] as $step): ?>
                                                    <li><?php echo esc_html($step); ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($method['note'])): ?>
                                        <div class="aipswam-note">
                                            <span class="dashicons dashicons-info"></span>
                                            <?php echo esc_html($method['note']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Webhook Format Tab -->
                    <div id="webhook-format" class="tab-pane">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('Webhook Payload Format', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>

                            <div class="aipswam-code-block">
                                <pre><code>{
  "post_id": 123,
  "post_title": "Sample Post Title",
  "post_content": "Post content here...",
  "post_excerpt": "Post excerpt",
  "post_url": "https://example.com/sample-post/",
  "post_type": "post",
  "post_status": "publish",
  "author": "Admin User",
  "categories": ["Category 1", "Category 2"],
  "tags": ["tag1", "tag2"],
  "featured_image": "https://example.com/image.jpg",
  "timestamp": "2025-09-14 10:30:00",
  "trigger_event": "post_published"
}</code></pre>
                            </div>

                            <div class="aipswam-info-box">
                                <h3><?php echo esc_html__('Expected Response Format', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                                <p><?php echo esc_html__('To update keywords, respond with JSON containing the post ID and keywords array:', 'all-in-one-post-seo-webhook-api-manager'); ?></p>

                                <div class="aipswam-code-block">
                                    <pre><code>{
  "post_id": 123,
  "keywords": [
    "primary keyword",
    "secondary keyword 1",
    "secondary keyword 2"
  ]
}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Dashboard -->
            <div class="aipswam-dashboard">
                <h2><?php echo esc_html__('Plugin Status', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                <div class="aipswam-status-grid">
                    <div class="aipswam-status-item">
                        <span class="dashicons dashicons-admin-links <?php echo !empty($webhook_url) ? 'status-active' : 'status-inactive'; ?>"></span>
                        <strong><?php echo esc_html__('Webhook URL'); ?></strong>
                        <span><?php echo !empty($webhook_url) ? esc_html__('Configured') : esc_html__('Not Configured'); ?></span>
                    </div>
                    <div class="aipswam-status-item">
                        <span class="dashicons dashicons-post-status <?php echo !empty($enabled_post_types) ? 'status-active' : 'status-inactive'; ?>"></span>
                        <strong><?php echo esc_html__('Post Types'); ?></strong>
                        <span><?php echo count($enabled_post_types); ?> <?php echo esc_html__('enabled'); ?></span>
                    </div>
                    <div class="aipswam-status-item">
                        <span class="dashicons dashicons-search <?php echo $this->is_seo_plugin_active($seo_plugin) ? 'status-active' : 'status-inactive'; ?>"></span>
                        <strong><?php echo esc_html__('SEO Plugin'); ?></strong>
                        <span><?php echo esc_html($seo_plugin); ?></span>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Tab functionality
                    $('.nav-tab').on('click', function(e) {
                        e.preventDefault();

                        var target = $(this).data('target');

                        $('.nav-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');

                        $('.tab-pane').removeClass('active');
                        $('#' + target).addClass('active');
                    });

                    // Copy to clipboard functionality
                    $('.aipswam-copy-btn').on('click', function() {
                        var url = $(this).data('url');
                        navigator.clipboard.writeText(url).then(function() {
                            alert('<?php echo esc_js__('URL copied to clipboard!', 'all-in-one-post-seo-webhook-api-manager'); ?>');
                        });
                    });

                    // Generate secret functionality
                    window.generateSecret = function() {
                        var secret = Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
                        $('#aipswam_webhook_secret').val(secret);
                    };
                });
            </script>
        </div>
        <?php
    }

    /**
     * Check if SEO plugin is active
     */
    private function is_seo_plugin_active($plugin) {
        switch ($plugin) {
            case 'rankmath':
                return class_exists('RankMath') || function_exists('rank_math');
            case 'yoast':
                return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta');
            case 'both':
                return $this->is_seo_plugin_active('rankmath') || $this->is_seo_plugin_active('yoast');
            default:
                return false;
        }
    }
}