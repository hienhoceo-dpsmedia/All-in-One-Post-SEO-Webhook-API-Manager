<?php
/**
 * Admin Class
 *
 * @package AllInOnePostSEOWebhookAPIManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPSWAM_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu for configuration
     */
    public function add_admin_menu() {
        add_options_page(
            __('All-in-One Post SEO Webhook & API Manager', 'all-in-one-post-seo-webhook-api-manager'),
            __('SEO Webhook API', 'all-in-one-post-seo-webhook-api-manager'),
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

        register_setting('aipswam_settings', 'aipswam_enable_manual_trigger', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ));

        register_setting('aipswam_settings', 'aipswam_enable_rest_api', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
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
            wp_enqueue_script('aipswam-admin', AIPSWAM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AIPSWAM_VERSION, true);

            wp_localize_script('aipswam-admin', 'aipswamAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aipswam_admin_nonce'),
                'postTypes' => get_post_types(array('public' => true), 'objects'),
                'statuses' => get_post_statuses(),
                'strings' => array(
                    'selectPost' => __('Select a post...', 'all-in-one-post-seo-webhook-api-manager'),
                ),
            ));
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
     * Admin settings page
     */
    public function admin_page() {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));
        $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');
        $enable_manual_trigger = get_option('aipswam_enable_manual_trigger', true);
        $enable_rest_api = get_option('aipswam_enable_rest_api', true);
        $webhook_timeout = get_option('aipswam_webhook_timeout', 10);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('All-in-One Post SEO Webhook & API Manager', 'all-in-one-post-seo-webhook-api-manager'); ?></h1>

            <div class="aipswam-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'all-in-one-post-seo-webhook-api-manager'); ?></a>
                    <a href="#triggers" class="nav-tab"><?php _e('Triggers', 'all-in-one-post-seo-webhook-api-manager'); ?></a>
                    <a href="#seo" class="nav-tab"><?php _e('SEO Integration', 'all-in-one-post-seo-webhook-api-manager'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'all-in-one-post-seo-webhook-api-manager'); ?></a>
                    <a href="#tools" class="nav-tab"><?php _e('Tools', 'all-in-one-post-seo-webhook-api-manager'); ?></a>
                </nav>

                <div class="tab-content">
                    <!-- General Settings -->
                    <div id="general" class="tab-pane active">
                        <form method="post" action="options.php">
                            <?php settings_fields('aipswam_settings'); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Webhook URL', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <input type="url" name="aipswam_webhook_url"
                                               value="<?php echo esc_attr(get_option('aipswam_webhook_url', '')); ?>"
                                               class="regular-text" required />
                                        <p class="description">
                                            <?php echo esc_html__('Enter the webhook URL where posts will be sent', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Webhook Secret', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <input type="text" name="aipswam_webhook_secret"
                                               value="<?php echo esc_attr(get_option('aipswam_webhook_secret', '')); ?>"
                                               class="regular-text" />
                                        <p class="description">
                                            <?php echo esc_html__('Optional: Secret key for webhook authentication', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(); ?>
                        </form>
                    </div>

                    <!-- Trigger Settings -->
                    <div id="triggers" class="tab-pane">
                        <form method="post" action="options.php">
                            <?php settings_fields('aipswam_settings'); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Enabled Post Types', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <?php
                                        $post_types = get_post_types(array('public' => true), 'objects');
                                        foreach ($post_types as $post_type) {
                                            $checked = in_array($post_type->name, $enabled_post_types) ? 'checked' : '';
                                            echo '<label><input type="checkbox" name="aipswam_enabled_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ' . esc_html($post_type->labels->name) . '</label><br>';
                                        }
                                        ?>
                                        <p class="description">
                                            <?php echo esc_html__('Select which post types should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Trigger Statuses', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <?php
                                        $statuses = get_post_statuses();
                                        foreach ($statuses as $status => $label) {
                                            $checked = in_array($status, $trigger_statuses) ? 'checked' : '';
                                            echo '<label><input type="checkbox" name="aipswam_trigger_statuses[]" value="' . esc_attr($status) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
                                        }
                                        ?>
                                        <p class="description">
                                            <?php echo esc_html__('Select which post status changes should trigger webhooks', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(); ?>
                        </form>
                    </div>

                    <!-- SEO Integration -->
                    <div id="seo" class="tab-pane">
                        <form method="post" action="options.php">
                            <?php settings_fields('aipswam_settings'); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('SEO Plugin', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <select name="aipswam_seo_plugin">
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
                            <?php submit_button(); ?>
                        </form>
                    </div>

                    <!-- Advanced Settings -->
                    <div id="advanced" class="tab-pane">
                        <form method="post" action="options.php">
                            <?php settings_fields('aipswam_settings'); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Webhook Timeout', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <input type="number" name="aipswam_webhook_timeout"
                                               value="<?php echo esc_attr($webhook_timeout); ?>"
                                               min="1" max="30" />
                                        <p class="description">
                                            <?php echo esc_html__('Timeout in seconds for webhook requests (1-30)', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Enable Manual Trigger', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="aipswam_enable_manual_trigger"
                                                   value="1" <?php checked($enable_manual_trigger); ?>>
                                            <?php echo esc_html__('Enable manual webhook trigger interface', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php echo esc_html__('Enable REST API', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="aipswam_enable_rest_api"
                                                   value="1" <?php checked($enable_rest_api); ?>>
                                            <?php echo esc_html__('Enable REST API endpoints for keyword retrieval', 'all-in-one-post-seo-webhook-api-manager'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(); ?>
                        </form>
                    </div>

                    <!-- Tools -->
                    <div id="tools" class="tab-pane">
                        <h3><?php echo esc_html__('Webhook Testing', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                        <button id="aipswam-test-webhook" class="button button-secondary">
                            <?php echo esc_html__('Test Webhook Connection', 'all-in-one-post-seo-webhook-api-manager'); ?>
                        </button>
                        <div id="aipswam-test-result"></div>

                        <h3><?php echo esc_html__('Manual Webhook Trigger', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                        <div class="aipswam-manual-trigger">
                            <select id="aipswam-post-select">
                                <option value=""><?php echo esc_html__('Select a post...', 'all-in-one-post-seo-webhook-api-manager'); ?></option>
                            </select>
                            <button id="aipswam-trigger-manual" class="button button-primary">
                                <?php echo esc_html__('Send Webhook', 'all-in-one-post-seo-webhook-api-manager'); ?>
                            </button>
                            <div id="aipswam-trigger-result"></div>
                        </div>

                        <h3><?php echo esc_html__('API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                        <div class="aipswam-api-info">
                            <h4><?php echo esc_html__('Webhook Processing Endpoint', 'all-in-one-post-seo-webhook-api-manager'); ?></h4>
                            <code><?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=process_webhook_response</code>

                            <h4><?php echo esc_html__('REST API Endpoints', 'all-in-one-post-seo-webhook-api-manager'); ?></h4>
                            <?php if ($enable_rest_api): ?>
                                <p><?php echo esc_html__('Available endpoints:', 'all-in-one-post-seo-webhook-api-manager'); ?></p>
                                <ul>
                                    <li><code>/wp-json/aipswam/v1/keywords/{post_id}</code> - Get keywords for a post</li>
                                    <li><code>/wp-json/aipswam/v1/webhooks/trigger/{post_id}</code> - Trigger webhook for a post</li>
                                    <li><code>/wp-json/aipswam/v1/logs</code> - Get webhook logs</li>
                                </ul>
                            <?php else; ?>
                                <p><?php echo esc_html__('REST API endpoints are disabled. Enable them in Advanced settings.', 'all-in-one-post-seo-webhook-api-manager'); ?></p>
                            <?php endif; ?>
                        </div>

                        <h3><?php echo esc_html__('About DPS.MEDIA JSC', 'all-in-one-post-seo-webhook-api-manager'); ?></h3>
                        <div class="aipswam-about">
                            <p><strong>🏢 About DPS.MEDIA JSC</strong></p>
                            <p>Since 2017, DPS.MEDIA JSC has been a leading provider of digital marketing and AI automation solutions. With a focus on comprehensive digital transformation, we have served over 5,400 SME customers, helping them leverage cutting-edge technology for business growth.</p>

                            <p><strong>Our Expertise:</strong></p>
                            <ul>
                                <li>Digital Marketing Strategy & Implementation</li>
                                <li>AI & Automation Solutions</li>
                                <li>Enterprise Workflow Integration</li>
                                <li>Content Creation & Management</li>
                                <li>E-commerce Optimization</li>
                            </ul>

                            <p><strong>Why Choose Us:</strong></p>
                            <ul>
                                <li>✅ 7+ years industry experience</li>
                                <li>✅ 5,400+ satisfied customers</li>
                                <li>✅ Expert team of digital specialists</li>
                                <li>✅ Cutting-edge technology solutions</li>
                                <li>✅ Results-driven approach</li>
                            </ul>

                            <p><strong>Contact Information:</strong></p>
                            <p>📍 56 Nguyễn Đình Chiểu, Phường Tân Định, Thành phố Hồ Chí Minh, Việt Nam<br>
                            📞 0961545445<br>
                            🌐 <a href="https://dps.media/" target="_blank">https://dps.media/</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}