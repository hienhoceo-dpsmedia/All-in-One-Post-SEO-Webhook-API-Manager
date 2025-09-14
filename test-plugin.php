<?php
/**
 * Plugin Name: Test Plugin - Minimal Version
 * Plugin URI: https://dps.media/
 * Description: Minimal test version to check basic functionality
 * Version: 1.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 * Author: DPS.MEDIA JSC
 * Author URI: https://dps.media/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: test-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple test function
function test_plugin_init() {
    // Just add a simple admin notice
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>Test plugin is working!</p></div>';
    });
}

// Initialize
add_action('plugins_loaded', 'test_plugin_init');