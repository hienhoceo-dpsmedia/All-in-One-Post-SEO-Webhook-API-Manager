<?php
/**
 * Debug Rank Math Keyword Storage
 *
 * This script helps debug Rank Math keyword storage issues.
 * Upload to WordPress root directory and access via browser.
 */

// Include WordPress
require_once('wp-config.php');

if (!current_user_can('manage_options')) {
    die('You need admin permissions to access this debug script.');
}

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if (!$post_id) {
    // Show form to select post
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Rank Math Debug</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .form-group { margin: 10px 0; }
            input, button { padding: 5px; }
            pre { background: #f5f5f5; padding: 10px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <h1>Rank Math Keyword Storage Debug</h1>

        <div class="form-group">
            <label>Post ID to test:</label>
            <input type="number" id="postId" value="570">
            <button onclick="window.location.href='?post_id=' + document.getElementById('postId').value">Debug Post</button>
        </div>

        <h2>Recent Posts</h2>
        <?php
        $recent_posts = get_posts(array('numberposts' => 10));
        foreach ($recent_posts as $post) {
            echo '<div>';
            echo '<strong>ID:</strong> ' . $post->ID . ' - ';
            echo '<a href="?post_id=' . $post->ID . '">' . esc_html($post->post_title) . '</a>';
            echo '</div>';
        }
        ?>
    </body>
    </html>
    <?php
    exit;
}

// Debug specific post
$post = get_post($post_id);
if (!$post) {
    die('Post not found.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Rank Math Debug - Post <?php echo $post_id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { background-color: #d4edda; }
        .error { background-color: #f8d7da; }
        .info { background-color: #d1ecf1; }
        pre { background: #f5f5f5; padding: 10px; margin: 10px 0; overflow-x: auto; }
        .keyword-form { margin: 15px 0; }
        input, button { padding: 8px; margin: 5px; }
    </style>
</head>
<body>
    <h1>Rank Math Debug - Post: <?php echo esc_html($post->post_title); ?> (ID: <?php echo $post_id; ?>)</h1>

    <div class="section info">
        <h2>Post Information</h2>
        <strong>Title:</strong> <?php echo esc_html($post->post_title); ?><br>
        <strong>Status:</strong> <?php echo $post->post_status; ?><br>
        <strong>URL:</strong> <a href="<?php echo get_permalink($post_id); ?>" target="_blank"><?php echo get_permalink($post_id); ?></a>
    </div>

    <div class="section info">
        <h2>Test Keyword Storage</h2>
        <form method="post" class="keyword-form">
            <input type="hidden" name="test_keywords" value="1">
            <input type="text" name="keywords" placeholder="Enter keywords separated by commas" style="width: 300px;" value="test keyword 1, test keyword 2, test keyword 3">
            <button type="submit">Test Keyword Storage</button>
        </form>
    </div>

    <?php
    // Handle keyword test
    if (isset($_POST['test_keywords']) && !empty($_POST['keywords'])) {
        $keywords_array = array_map('trim', explode(',', $_POST['keywords']));

        // Use the plugin's keyword storage function
        if (class_exists('AIPSWAM_Secure_Webhook_Handler')) {
            global $aipswam_webhook_handler;
            if ($aipswam_webhook_handler) {
                $result = $aipswam_webhook_handler->set_keywords_from_webhook($post_id, $keywords_array);

                echo '<div class="section ' . ($result ? 'success' : 'error') . '">';
                echo '<h2>Keyword Storage Test Result</h2>';
                echo '<strong>Result:</strong> ' . ($result ? 'SUCCESS' : 'FAILED') . '<br>';
                echo '<strong>Keywords attempted:</strong> ' . esc_html(implode(', ', $keywords_array)) . '<br>';
                echo '</div>';
            } else {
                echo '<div class="section error">';
                echo '<h2>Error</h2>';
                echo 'Plugin handler not available.';
                echo '</div>';
            }
        } else {
            echo '<div class="section error">';
            echo '<h2>Error</h2>';
            echo 'Plugin class not found.';
            echo '</div>';
        }
    }
    ?>

    <div class="section info">
        <h2>Current Rank Math Meta Fields</h2>
        <?php
        $rank_math_fields = array(
            'rank_math_focus_keyword',
            'rank_math_focus_keywords',
            'rank_math_keywords',
            'rank_math_secondary_keywords'
        );

        foreach ($rank_math_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            echo '<div><strong>' . $field . ':</strong><br>';
            echo '<pre>' . esc_html(print_r($value, true)) . '</pre></div>';
        }

        // Show all meta fields with 'rank_math'
        global $wpdb;
        $all_rank_math_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE 'rank_math_%'",
            $post_id
        ));

        if ($all_rank_math_meta) {
            echo '<h3>All Rank Math Meta Fields</h3>';
            foreach ($all_rank_math_meta as $meta) {
                echo '<div><strong>' . esc_html($meta->meta_key) . ':</strong><br>';
                echo '<pre>' . esc_html(substr(print_r($meta->meta_value, true), 0, 500)) . '</pre></div>';
            }
        }
        ?>
    </div>

    <div class="section info">
        <h2>Rank Math Keyword Retrieval Test</h2>
        <form method="post">
            <input type="hidden" name="test_retrieval" value="1">
            <button type="submit">Test How Rank Math Retrieves Keywords</button>
        </form>

        <?php
        if (isset($_POST['test_retrieval'])) {
            echo '<h3>Retrieval Test Results:</h3>';

            // Test different ways Rank Math might retrieve keywords
            $primary = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $secondary_json = get_post_meta($post_id, 'rank_math_focus_keywords', true);
            $secondary_simple = get_post_meta($post_id, 'rank_math_keywords', true);
            $secondary_comma = get_post_meta($post_id, 'rank_math_secondary_keywords', true);

            echo '<div><strong>Primary Keyword:</strong> ' . esc_html($primary) . '</div>';

            echo '<div><strong>Secondary (JSON format):</strong><br>';
            if ($secondary_json) {
                $decoded = json_decode($secondary_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (isset($item['keyword'])) {
                            echo '- ' . esc_html($item['keyword']) . '<br>';
                        }
                    }
                } else {
                    echo 'Invalid JSON or empty';
                }
            } else {
                echo 'No data';
            }
            echo '</div>';

            echo '<div><strong>Secondary (Simple JSON):</strong><br>';
            if ($secondary_simple) {
                $decoded = json_decode($secondary_simple, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $keyword) {
                        echo '- ' . esc_html($keyword) . '<br>';
                    }
                } else {
                    echo 'Invalid JSON or empty';
                }
            } else {
                echo 'No data';
            }
            echo '</div>';

            echo '<div><strong>Secondary (Comma-separated):</strong><br>';
            if ($secondary_comma) {
                $keywords = explode(',', $secondary_comma);
                foreach ($keywords as $keyword) {
                    echo '- ' . esc_html(trim($keyword)) . '<br>';
                }
            } else {
                echo 'No data';
            }
            echo '</div>';

            // Test if Rank Math has internal functions
            if (function_exists('rank_math')) {
                echo '<div><strong>Rank Math Functions Available:</strong> YES</div>';
            } else {
                echo '<div><strong>Rank Math Functions Available:</strong> NO</div>';
            }

            // Check Rank Math version compatibility
            if (defined('RANK_MATH_VERSION')) {
                echo '<div><strong>Rank Math Version:</strong> ' . RANK_MATH_VERSION . '</div>';
                if (version_compare(RANK_MATH_VERSION, '1.0.200', '>=')) {
                    echo '<div style="color: orange;">⚠️ Rank Math 1.0.200+ may have changed keyword storage format</div>';
                }
            }
        }
        ?>
    </div>

    <div class="section info">
        <h2>Plugin Information</h2>
        <?php
        echo '<strong>Rank Math Active:</strong> ' . (defined('RANK_MATH_VERSION') ? 'YES (' . RANK_MATH_VERSION . ')' : 'NO') . '<br>';
        echo '<strong>Yoast Active:</strong> ' . (defined('WPSEO_VERSION') ? 'YES (' . WPSEO_VERSION . ')' : 'NO') . '<br>';
        echo '<strong>Our Plugin Active:</strong> ' . (defined('AIPSWAM_VERSION') ? 'YES (' . AIPSWAM_VERSION . ')' : 'NO') . '<br>';
        echo '<strong>Our Plugin Version in DB:</strong> ' . get_option('aipswam_version', 'not set') . '<br>';
        ?>
    </div>

    <div class="section">
        <a href="debug-rank-math.php">← Back to Post List</a>
    </div>
</body>
</html>