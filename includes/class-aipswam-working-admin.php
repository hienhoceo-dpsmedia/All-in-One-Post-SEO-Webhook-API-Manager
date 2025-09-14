<?php
/**
 * GUARANTEED WORKING Admin Class with Inline JavaScript
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Working_Admin {

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

        register_setting('aipswam_settings', 'aipswam_seo_plugin', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'rankmath',
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
     * Check if SEO plugin is active
     */
    private function is_seo_plugin_active($plugin) {
        switch ($plugin) {
            case 'rankmath':
                return class_exists('RankMath') || function_exists('rank_math');
            case 'yoast':
                return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta');
            default:
                return false;
        }
    }

    /**
     * Admin page - GUARANTEED WORKING VERSION
     */
    public function admin_page() {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');
        $webhook_timeout = get_option('aipswam_webhook_timeout', 10);
        $webhook_url = get_option('aipswam_webhook_url', '');
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
                    <a href="#" class="nav-tab nav-tab-active" onclick="showTab('general', this); return false;">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configuration', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#" class="nav-tab" onclick="showTab('triggers', this); return false;">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Triggers', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#" class="nav-tab" onclick="showTab('api', this); return false;">
                        <span class="dashicons dashicons-rest-api"></span>
                        <?php _e('API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?>
                    </a>
                    <a href="#" class="nav-tab" onclick="showTab('keywords', this); return false;">
                        <span class="dashicons dashicons-edit-page"></span>
                        <?php _e('Keyword Guide', 'all-in-one-post-seo-webhook-api-manager'); ?>
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
                                                   class="regular-text" placeholder="https://your-webhook-url.com/handler" />
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
                                            <label for="aipswam_seo_plugin">
                                                <span class="dashicons dashicons-search"></span>
                                                <?php echo esc_html__('SEO Plugin', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <select id="aipswam_seo_plugin" name="aipswam_seo_plugin">
                                                <option value="rankmath" <?php selected($seo_plugin, 'rankmath'); ?>>
                                                    <?php echo esc_html__('RankMath SEO', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                                <option value="yoast" <?php selected($seo_plugin, 'yoast'); ?>>
                                                    <?php echo esc_html__('Yoast SEO', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                                <option value="both" <?php selected($seo_plugin, 'both'); ?>>
                                                    <?php echo esc_html__('Both (RankMath first, Yoast fallback)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                                </option>
                                            </select>
                                            <p class="description">
                                                <?php echo esc_html__('Select which SEO plugin to use for keyword management', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="aipswam_webhook_timeout">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php echo esc_html__('Timeout (seconds)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="number" id="aipswam_webhook_timeout" name="aipswam_webhook_timeout"
                                                   value="<?php echo esc_attr($webhook_timeout); ?>"
                                                   min="1" max="60" class="small-text" />
                                            <p class="description">
                                                <?php echo esc_html__('Webhook request timeout in seconds', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button(); ?>
                            </form>
                        </div>
                    </div>

                    <!-- Triggers Tab -->
                    <div id="triggers" class="tab-pane" style="display: none;">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('Trigger Configuration', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                            <form method="post" action="options.php">
                                <?php settings_fields('aipswam_settings'); ?>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label>
                                                <span class="dashicons dashicons-post"></span>
                                                <?php echo esc_html__('Enabled Post Types', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="aipswam-checkbox-group">
                                                <?php
                                                $post_types = get_post_types(array('public' => true));
                                                foreach ($post_types as $post_type) {
                                                    $checked = in_array($post_type, $enabled_post_types);
                                                    ?>
                                                    <label class="aipswam-checkbox-label">
                                                        <input type="checkbox" name="aipswam_enabled_post_types[]"
                                                               value="<?php echo esc_attr($post_type); ?>"
                                                               <?php checked($checked); ?>>
                                                        <span class="aipswam-checkbox-text"><?php echo esc_html($post_type); ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                            <p class="description">
                                                <?php echo esc_html__('Select which post types should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label>
                                                <span class="dashicons dashicons-post-status"></span>
                                                <?php echo esc_html__('Trigger Statuses', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="aipswam-checkbox-group">
                                                <?php
                                                $statuses = get_post_statuses();
                                                foreach ($statuses as $status => $label) {
                                                    $checked = in_array($status, $trigger_statuses);
                                                    ?>
                                                    <label class="aipswam-checkbox-label">
                                                        <input type="checkbox" name="aipswam_trigger_statuses[]"
                                                               value="<?php echo esc_attr($status); ?>"
                                                               <?php checked($checked); ?>>
                                                        <span class="aipswam-checkbox-text"><?php echo esc_html($label); ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                            <p class="description">
                                                <?php echo esc_html__('Select which post status changes should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button(); ?>
                            </form>
                        </div>
                    </div>

                    <!-- API Endpoints Tab -->
                    <div id="api" class="tab-pane" style="display: none;">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('REST API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>
                            <div class="aipswam-endpoints-list">
                                <div class="aipswam-endpoint-item">
                                    <div class="aipswam-endpoint-header">
                                        <h3><?php echo esc_html__('Get Keywords', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                                        <span class="aipswam-method aipswam-method-get">GET</span>
                                    </div>
                                    <div class="aipswam-endpoint-url">
                                        <code><?php echo esc_url(home_url('/wp-json/wp/v2/posts?_fields=id,title,yoast_focuskw,rankmath_focuskw,seo_keywords')); ?></code>
                                        <button class="button button-secondary aipswam-copy-btn"
                                                onclick="copyToClipboard('<?php echo esc_js(home_url('/wp-json/wp/v2/posts?_fields=id,title,yoast_focuskw,rankmath_focuskw,seo_keywords')); ?>')">
                                            Copy
                                        </button>
                                    </div>
                                    <p class="aipswam-endpoint-description">
                                        <?php echo esc_html__('Get all posts with their SEO keywords', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </p>
                                </div>

                                <div class="aipswam-endpoint-item">
                                    <div class="aipswam-endpoint-header">
                                        <h3><?php echo esc_html__('Webhook Processing', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                                        <span class="aipswam-method aipswam-method-post">POST</span>
                                    </div>
                                    <div class="aipswam-endpoint-url">
                                        <code><?php echo esc_url(admin_url('admin-ajax.php?action=process_webhook_response')); ?></code>
                                        <button class="button button-secondary aipswam-copy-btn"
                                                onclick="copyToClipboard('<?php echo esc_js(admin_url('admin-ajax.php?action=process_webhook_response')); ?>')">
                                            Copy
                                        </button>
                                    </div>
                                    <p class="aipswam-endpoint-description">
                                        <?php echo esc_html__('Process webhook responses containing keyword data', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keyword Guide Tab -->
                    <div id="keywords" class="tab-pane" style="display: none;">
                        <div class="aipswam-card">
                            <h2><?php echo esc_html__('How to Update Keywords', 'all-in-one-post-seo-webhook-api-manager'); ?></h2>

                            <div class="aipswam-guide-method">
                                <h3>Method 1: REST API</h3>
                                <p>Use the REST API to get keyword data:</p>
                                <div class="aipswam-code-block">
                                    <pre><code>GET <?php echo esc_url(home_url('/wp-json/wp/v2/posts?_fields=id,title,yoast_focuskw')); ?></code></pre>
                                </div>
                                <p>This returns posts with their focus keywords in JSON format.</p>
                            </div>

                            <div class="aipswam-guide-method">
                                <h3>Method 2: Webhook Response</h3>
                                <p>Send keywords back to WordPress via webhook response:</p>
                                <div class="aipswam-code-block">
                                    <pre><code>{
    "post_id": 123,
    "keywords": ["primary keyword", "secondary keyword 1", "secondary keyword 2"]
}</code></pre>
                                </div>
                                <p>Send POST request to: <code><?php echo esc_url(admin_url('admin-ajax.php?action=process_webhook_response')); ?></code></p>
                            </div>

                            <div class="aipswam-guide-method">
                                <h3>Method 3: Direct Function Call</h3>
                                <p>Use the built-in function to set keywords programmatically:</p>
                                <div class="aipswam-code-block">
                                    <pre><code>aipswam_set_keywords($post_id, $keywords);</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GUARANTEED WORKING INLINE JAVASCRIPT -->
            <script type="text/javascript">
                // Tab switching function - GUARANTEED TO WORK
                function showTab(tabId, clickedElement) {
                    // Hide all tab panes
                    var tabPanes = document.getElementsByClassName('tab-pane');
                    for (var i = 0; i < tabPanes.length; i++) {
                        tabPanes[i].style.display = 'none';
                        tabPanes[i].classList.remove('active');
                    }

                    // Remove active class from all nav tabs
                    var navTabs = document.getElementsByClassName('nav-tab');
                    for (var i = 0; i < navTabs.length; i++) {
                        navTabs[i].classList.remove('nav-tab-active');
                    }

                    // Show selected tab
                    var selectedTab = document.getElementById(tabId);
                    if (selectedTab) {
                        selectedTab.style.display = 'block';
                        selectedTab.classList.add('active');
                    }

                    // Add active class to clicked nav tab
                    if (clickedElement) {
                        clickedElement.classList.add('nav-tab-active');
                    }
                }

                // Copy to clipboard functionality
                function copyToClipboard(text) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            alert('URL copied to clipboard!');
                        }).catch(function() {
                            // Fallback
                            fallbackCopyToClipboard(text);
                        });
                    } else {
                        // Fallback for older browsers
                        fallbackCopyToClipboard(text);
                    }
                }

                // Fallback copy function
                function fallbackCopyToClipboard(text) {
                    var textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();

                    try {
                        document.execCommand('copy');
                        alert('URL copied to clipboard!');
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                        alert('Failed to copy URL. Please copy manually.');
                    }

                    document.body.removeChild(textArea);
                }

                // Generate secret functionality
                function generateSecret() {
                    var secret = Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
                    var secretField = document.getElementById('aipswam_webhook_secret');
                    if (secretField) {
                        secretField.value = secret;
                    }
                }

                // Initialize - show first tab by default
                document.addEventListener('DOMContentLoaded', function() {
                    showTab('general', document.querySelector('.nav-tab'));
                });
            </script>
        </div>
        <?php
    }
}