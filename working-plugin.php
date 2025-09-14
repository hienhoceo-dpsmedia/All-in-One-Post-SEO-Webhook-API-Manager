<?php
/**
 * Plugin Name: All-in-One Post SEO Webhook & API Manager - Working Version
 * Plugin URI: https://dps.media/
 * Description: Working version without database dependencies
 * Version: 2.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 * Author: DPS.MEDIA JSC
 * Author URI: https://dps.media/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: all-in-one-post-seo-webhook-api-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIPSWAM_VERSION', '2.0');
define('AIPSWAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPSWAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPSWAM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Simple Webhook Handler Class (No Database)
class AIPSWAM_Simple_Webhook_Handler {

    private $webhook_url;
    private $webhook_secret;
    private $webhook_timeout;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('publish_post', array($this, 'handle_new_published_post'), 10, 2);
    }

    public function init() {
        $this->webhook_url = get_option('aipswam_webhook_url', '');
        $this->webhook_secret = get_option('aipswam_webhook_secret', '');
        $this->webhook_timeout = get_option('aipswam_webhook_timeout', 10);
    }

    public function handle_post_status_change($new_status, $old_status, $post) {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));

        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }

        if (!in_array($new_status, $trigger_statuses)) {
            return;
        }

        $this->send_to_webhook($post);
    }

    public function handle_new_published_post($post_id, $post) {
        if ($post->post_type !== 'post') {
            return;
        }

        $this->send_to_webhook($post);
    }

    private function send_to_webhook($post) {
        if (empty($this->webhook_url)) {
            return;
        }

        $post_data = $this->prepare_post_data($post);
        $headers = $this->get_webhook_headers($post_data);

        $args = array(
            'method' => 'POST',
            'timeout' => $this->webhook_timeout,
            'headers' => $headers,
            'body' => json_encode($post_data)
        );

        wp_remote_post($this->webhook_url, $args);
    }

    private function prepare_post_data($post) {
        return array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_url' => get_permalink($post->ID),
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'timestamp' => current_time('mysql'),
            'trigger_event' => $post->post_status === 'publish' ? 'post_published' : 'post_pending'
        );
    }

    private function get_webhook_headers($data) {
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress-AIPSWAM/' . AIPSWAM_VERSION,
            'X-Webhook-Source' => home_url(),
        );

        if (!empty($this->webhook_secret)) {
            $payload = json_encode($data);
            $signature = hash_hmac('sha256', $payload, $this->webhook_secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        return $headers;
    }
}

// Simple Admin Class
class AIPSWAM_Simple_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_options_page(
            'All-in-One Post SEO Webhook & API Manager',
            'SEO Webhook API',
            'manage_options',
            'aipswam-settings',
            array($this, 'admin_page')
        );
    }

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

        register_setting('aipswam_settings', 'aipswam_webhook_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));
    }

    public function sanitize_post_types_array($value) {
        if (!is_array($value)) {
            return array('post');
        }

        $valid_post_types = get_post_types(array('public' => true));
        return array_intersect($value, array_keys($valid_post_types));
    }

    public function sanitize_statuses_array($value) {
        if (!is_array($value)) {
            return array('pending', 'publish');
        }

        $valid_statuses = array_keys(get_post_statuses());
        return array_intersect($value, $valid_statuses);
    }

    public function admin_page() {
        $enabled_post_types = get_option('aipswam_enabled_post_types', array('post'));
        $trigger_statuses = get_option('aipswam_trigger_statuses', array('pending', 'publish'));
        $webhook_timeout = get_option('aipswam_webhook_timeout', 10);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('All-in-One Post SEO Webhook & API Manager', 'all-in-one-post-seo-webhook-api-manager'); ?></h1>

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
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
function aipswam_init_plugin() {
    new AIPSWAM_Simple_Webhook_Handler();
    new AIPSWAM_Simple_Admin();
}

// Hook for plugin initialization
add_action('plugins_loaded', 'aipswam_init_plugin');

// Activation hook
register_activation_hook(__FILE__, 'aipswam_activate');

function aipswam_activate() {
    // Create default options
    add_option('aipswam_webhook_url', '');
    add_option('aipswam_webhook_secret', wp_generate_password(32, false));
    add_option('aipswam_enabled_post_types', array('post'));
    add_option('aipswam_trigger_statuses', array('pending', 'publish'));
    add_option('aipswam_webhook_timeout', 10);

    // Add success notice
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>' .
             esc_html__('All-in-One Post SEO Webhook & API Manager has been activated successfully!', 'all-in-one-post-seo-webhook-api-manager') .
             '</p></div>';
    });
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'aipswam_deactivate');

function aipswam_deactivate() {
    // Clean up if needed
}