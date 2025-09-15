<?php
/**
 * Plugin Name: All-in-One Post SEO Webhook & API Manager
 * Plugin URI: https://wordpress.org/plugins/all-in-one-post-seo-webhook-api-manager/
 * Description: Complete webhook management solution with SEO integration, API endpoints, and automation tools for WordPress posts
 * Version: 2.2.9
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 * Author: DPS.MEDIA JSC
 * Author URI: https://dps.media/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: all-in-one-post-seo-webhook-api-manager
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIPSWAM_VERSION', '2.2.9');
define('AIPSWAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPSWAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPSWAM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once AIPSWAM_PLUGIN_DIR . 'includes/class-aipswam-secure-webhook-handler.php';
require_once AIPSWAM_PLUGIN_DIR . 'includes/class-aipswam-working-admin.php';
require_once AIPSWAM_PLUGIN_DIR . 'includes/class-aipswam-simple-logger.php';
require_once AIPSWAM_PLUGIN_DIR . 'includes/simple-keyword-endpoint.php';

// Initialize the plugin
function aipswam_init_plugin() {
    new AIPSWAM_Secure_Webhook_Handler();
    new AIPSWAM_Working_Admin();
    new AIPSWAM_Simple_Logger();
}

// Hook for plugin initialization
add_action('plugins_loaded', 'aipswam_init_plugin');

// Check for upgrades on each load
add_action('plugins_loaded', 'aipswam_check_upgrade');

// Force version update if needed
add_action('init', 'aipswam_force_version_update');
function aipswam_force_version_update() {
    $current_version = get_option('aipswam_version', '0');
    if ($current_version !== AIPSWAM_VERSION) {
        update_option('aipswam_version', AIPSWAM_VERSION);
    }
}

// Register REST API fields for SEO keywords
add_action('rest_api_init', 'aipswam_register_rest_fields');

function aipswam_register_rest_fields() {
    // Register for all public post types
    $post_types = get_post_types(array('public' => true));

    foreach ($post_types as $post_type) {
        // RankMath keywords
        register_rest_field($post_type, 'rankmath_keywords', array(
            'get_callback' => 'aipswam_get_rankmath_keywords',
            'update_callback' => null,
            'schema' => null,
        ));

        // Yoast keywords
        register_rest_field($post_type, 'yoast_keywords', array(
            'get_callback' => 'aipswam_get_yoast_keywords',
            'update_callback' => null,
            'schema' => null,
        ));

        // Yoast focus keyword (single field)
        register_rest_field($post_type, 'yoast_focuskw', array(
            'get_callback' => 'aipswam_get_yoast_focuskw',
            'update_callback' => null,
            'schema' => null,
        ));

        // RankMath focus keyword (single field)
        register_rest_field($post_type, 'rankmath_focuskw', array(
            'get_callback' => 'aipswam_get_rankmath_focuskw',
            'update_callback' => null,
            'schema' => null,
        ));

        // Combined keywords (based on active plugin)
        register_rest_field($post_type, 'seo_keywords', array(
            'get_callback' => 'aipswam_get_seo_keywords',
            'update_callback' => null,
            'schema' => null,
        ));
    }
}

/**
 * Get RankMath keywords for REST API
 */
function aipswam_get_rankmath_keywords($post_arr) {
    $post_id = $post_arr['id'];

    if (!class_exists('RankMath') && !function_exists('rank_math')) {
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
 * Get Yoast keywords for REST API
 */
function aipswam_get_yoast_keywords($post_arr) {
    $post_id = $post_arr['id'];

    if (!defined('WPSEO_VERSION') && !class_exists('WPSEO_Meta')) {
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
 * Get Yoast focus keyword for REST API
 */
function aipswam_get_yoast_focuskw($post_arr) {
    $post_id = $post_arr['id'];

    if (!defined('WPSEO_VERSION') && !class_exists('WPSEO_Meta')) {
        return '';
    }

    return get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
}

/**
 * Get RankMath focus keyword for REST API
 */
function aipswam_get_rankmath_focuskw($post_arr) {
    $post_id = $post_arr['id'];

    if (!class_exists('RankMath') && !function_exists('rank_math')) {
        return '';
    }

    return get_post_meta($post_id, 'rank_math_focus_keyword', true);
}

/**
 * Get SEO keywords for active plugin
 */
function aipswam_get_seo_keywords($post_arr) {
    $post_id = $post_arr['id'];
    $seo_plugin = get_option('aipswam_seo_plugin', 'rankmath');

    switch ($seo_plugin) {
        case 'rankmath':
            return aipswam_get_rankmath_keywords($post_arr);
        case 'yoast':
            return aipswam_get_yoast_keywords($post_arr);
        case 'both':
            $rankmath_keywords = aipswam_get_rankmath_keywords($post_arr);
            if (!empty($rankmath_keywords['primary'])) {
                return $rankmath_keywords;
            }
            return aipswam_get_yoast_keywords($post_arr);
        default:
            return array('primary' => '', 'secondary' => array());
    }
}

// Upgrade check function
function aipswam_check_upgrade() {
    $installed_version = get_option('aipswam_version', '0');

    // Force version update if mismatch
    if ($installed_version !== AIPSWAM_VERSION) {
        aipswam_upgrade($installed_version, AIPSWAM_VERSION);
        update_option('aipswam_version', AIPSWAM_VERSION);
    }
}

// Upgrade function
function aipswam_upgrade($old_version, $new_version) {
    // Handle upgrades here
    // For example, if you need to add a new option in version 2.1.0
    if (version_compare($old_version, '2.1.0', '<')) {
        // Do something
    }
}

// Activation hook
register_activation_hook(__FILE__, 'aipswam_activate');
function aipswam_activate() {
    // Create default options
    add_option('aipswam_webhook_url', '');
    add_option('aipswam_webhook_secret', wp_generate_password(32, false));
    add_option('aipswam_seo_plugin', 'rankmath');
    add_option('aipswam_enabled_post_types', array('post'));
    add_option('aipswam_trigger_statuses', array('pending', 'publish'));
    add_option('aipswam_webhook_timeout', 10);
    add_option('aipswam_version', AIPSWAM_VERSION);

    // Generate secure random endpoints
    $random_endpoint = 'kw_' . bin2hex(random_bytes(8));
    add_option('aipswam_random_endpoint', $random_endpoint);

    $webhook_endpoint = 'wh_' . bin2hex(random_bytes(8));
    add_option('aipswam_webhook_endpoint', $webhook_endpoint);

    // Set default capabilities
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('process_webhooks');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'aipswam_deactivate');
function aipswam_deactivate() {
    // Clean up capabilities
    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap('process_webhooks');
    }
}

// Helper function to manually set keywords
function aipswam_set_keywords($post_id, $keywords) {
    global $aipswam_webhook_handler;
    if ($aipswam_webhook_handler) {
        return $aipswam_webhook_handler->set_keywords_from_webhook($post_id, $keywords);
    }
    return false;
}