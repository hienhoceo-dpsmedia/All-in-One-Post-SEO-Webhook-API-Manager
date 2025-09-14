<?php
/**
 * Plugin Name: All-in-One Post SEO Webhook & API Manager - Simple
 * Plugin URI: https://dps.media/
 * Description: Simple version of the webhook plugin
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

// Simple admin menu
add_action('admin_menu', 'aipswam_simple_admin_menu');

function aipswam_simple_admin_menu() {
    add_options_page(
        'SEO Webhook API',
        'SEO Webhook',
        'manage_options',
        'aipswam-simple',
        'aipswam_simple_admin_page'
    );
}

function aipswam_simple_admin_page() {
    ?>
    <div class="wrap">
        <h1>All-in-One Post SEO Webhook & API Manager</h1>
        <p>Simple version - Basic functionality working!</p>

        <form method="post" action="options.php">
            <?php settings_fields('aipswam_simple_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Webhook URL</th>
                    <td>
                        <input type="url" name="aipswam_webhook_url"
                               value="<?php echo esc_attr(get_option('aipswam_webhook_url', '')); ?>"
                               class="regular-text" />
                        <p class="description">Enter your webhook URL</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'aipswam_simple_register_settings');

function aipswam_simple_register_settings() {
    register_setting('aipswam_simple_settings', 'aipswam_webhook_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => '',
    ));
}

// Activation hook
register_activation_hook(__FILE__, 'aipswam_simple_activate');

function aipswam_simple_activate() {
    add_option('aipswam_webhook_url', '');
    add_option('aipswam_webhook_secret', wp_generate_password(32, false));

    // Add admin notice
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>Simple SEO Webhook plugin activated successfully!</p></div>';
    });
}