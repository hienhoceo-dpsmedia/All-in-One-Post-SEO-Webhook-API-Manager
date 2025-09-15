<?php
/**
 * Simple REST API callback for getting keywords
 * This replaces the complex function that's causing 500 errors
 */

// Only register endpoint if not already registered
if (!function_exists('aipswam_simple_get_keywords')) {
    function aipswam_simple_get_keywords($request) {
    // Get all published posts
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
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

            // Simple keyword detection
            $keyword = '';

            // Try Rank Math first
            if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
                $keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            }

            // Try Yoast if no Rank Math keyword
            if (empty($keyword) && defined('WPSEO_VERSION')) {
                $keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            }

            // Add to results if keyword found
            if (!empty($keyword)) {
                $keywords_data[] = array(
                    'post_id' => $post_id,
                    'title' => $title,
                    'keyword' => $keyword
                );
            }
        }
    }

    wp_reset_postdata();
    return new WP_REST_Response($keywords_data);
  }
}

// Register the simple endpoint
add_action('rest_api_init', function () {
    // Get existing endpoint or create new one
    $random_endpoint = get_option('aipswam_random_endpoint');
    if (empty($random_endpoint)) {
        $random_endpoint = 'kw_' . bin2hex(random_bytes(8));
        update_option('aipswam_random_endpoint', $random_endpoint);
    }

    register_rest_route('aipswam', '/' . $random_endpoint, array(
        'methods' => 'GET',
        'callback' => 'aipswam_simple_get_keywords',
        'permission_callback' => '__return_true'
    ));
});