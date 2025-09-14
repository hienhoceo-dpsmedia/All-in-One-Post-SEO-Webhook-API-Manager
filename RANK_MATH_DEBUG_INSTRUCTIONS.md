# Rank Math Keyword Storage Debug Instructions

## Overview

This debug version includes comprehensive logging to help identify why multiple keywords are not being stored correctly in Rank Math. The debug version tests multiple approaches and provides detailed logs to understand what's happening.

## Files Modified

1. **`includes/class-aipswam-secure-webhook-handler.php`**
   - Enhanced `set_rankmath_keywords()` function with extensive debugging
   - Enhanced `get_current_keywords()` function with detailed logging
   - Added `debug_parse_rank_math_keywords()` helper function

2. **`debug-rank-math.php`** (NEW)
   - Standalone test script to manually test keyword storage
   - Provides web interface for testing and viewing results

## What the Debug Version Tests

### Approach 1: Standard Rank Math Format
- Primary keyword: `rank_math_focus_keyword` (string)
- Secondary keywords: `rank_math_focus_keywords` (JSON array format)

### Approach 2: Alternative JSON Format
- Keywords: `rank_math_keywords` (simple JSON array)

### Approach 3: Comma-Separated Format
- Keywords: `rank_math_secondary_keywords` (comma-separated string)

### Approach 4: Rank Math API Functions
- Attempts to use Rank Math's internal `save_post_meta()` method

## How to Use

### Method 1: Use the Debug Test Script

1. **Copy the test script to your WordPress root directory:**
   ```bash
   cp debug-rank-math.php /path/to/your/wordpress/site/
   ```

2. **Access the debug tool in your browser:**
   ```
   https://yoursite.com/debug-rank-math.php
   ```

3. **Test a specific post:**
   ```
   https://yoursite.com/debug-rank-math.php?post_id=123
   ```

4. **Click "Test Keyword Storage" to run the debug test**

### Method 2: Use Existing Webhook Functionality

1. **Trigger a webhook with multiple keywords** using your existing webhook system
2. **Check the WordPress error logs** for debug information

### Method 3: Manual Testing via Code

```php
// Add this to your theme's functions.php or a custom plugin

function test_rank_math_keywords() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $aipswam_webhook_handler;

    if (!$aipswam_webhook_handler) {
        require_once('wp-content/plugins/all-in-one-post-seo-webhook-api-manager/includes/class-aipswam-secure-webhook-handler.php');
        $aipswam_webhook_handler = new AIPSWAM_Secure_Webhook_Handler();
    }

    $test_keywords = array(
        'main keyword',
        'secondary keyword 1',
        'additional keyword 2'
    );

    $post_id = 123; // Change to your test post ID
    $result = $aipswam_webhook_handler->set_keywords_from_webhook($post_id, $test_keywords);

    echo "Test completed. Check error logs for details.";
}

// Execute the test
test_rank_math_keywords();
```

## Debug Log Information

The debug version logs extensive information to help identify issues:

### Storage Logs (Prefix: "RANKMATH DEBUG:")
- Rank Math version and plugin status
- Existing meta fields before storage
- Raw keywords received
- Each storage attempt with results
- JSON format validation
- Final verification of stored values

### Retrieval Logs (Prefix: "KEYWORD DEBUG:")
- All Rank Math meta fields found
- Processing of each field
- JSON parsing results
- Final retrieved keywords

## How to View Debug Logs

### 1. WordPress Error Log
Check your WordPress error log, typically located at:
- `/var/log/apache2/error_log` (Apache)
- `/var/log/nginx/error.log` (Nginx)
- `wp-content/debug.log` (if WP_DEBUG_LOG is enabled)

### 2. Using WP-CLI
```bash
wp log show --lines=100 | grep "RANKMATH DEBUG"
wp log show --lines=100 | grep "KEYWORD DEBUG"
```

### 3. Using a Plugin
Install and activate a log viewer plugin like:
- WP Debug Log
- Log Deprecated Notices
- Debug Bar

## What to Look For in the Logs

### Successful Storage
```
RANKMATH DEBUG: Standard primary keyword update result: success
RANKMATH DEBUG: Standard secondary keywords update result: success
RANKMATH DEBUG: JSON stored: [{"keyword":"secondary keyword 1","score":58}]
```

### JSON Format Issues
```
RANKMATH DEBUG: rank_math_focus_keywords is NOT valid JSON. Error: Syntax error
```

### Missing Keywords
```
RANKMATH DEBUG: Existing rank_math_focus_keywords:
KEYWORD DEBUG: No keywords retrieved
```

### Alternative Field Detection
```
RANKMATH DEBUG: Alternative simple JSON update result: success
KEYWORD DEBUG: Processing alternative field: rank_math_keywords
```

## Expected Rank Math Format

Based on Rank Math documentation, the expected format is:

### Primary Keyword
```php
// String value
update_post_meta($post_id, 'rank_math_focus_keyword', 'main keyword');
```

### Secondary Keywords
```php
// JSON array format
$json = '[{"keyword":"secondary keyword 1","score":58},{"keyword":"secondary keyword 2","score":58}]';
update_post_meta($post_id, 'rank_math_focus_keywords', $json);
```

## Common Issues and Solutions

### Issue 1: Only Primary Keyword Shows
**Symptom:** Primary keyword works, but secondary keywords don't appear
**Possible Causes:**
- Invalid JSON format
- Wrong meta field name
- Rank Math version compatibility

### Issue 2: No Keywords Stored
**Symptom:** Keywords don't appear in any meta fields
**Possible Causes:**
- Rank Math not active
- Permission issues
- Plugin conflicts

### Issue 3: Keywords Not Retrieved
**Symptom:** Keywords are stored but not retrieved by the plugin
**Possible Causes:**
- JSON parsing issues
- Wrong field names in retrieval logic
- Caching issues

## Next Steps After Debugging

1. **Identify which approach works** from the debug logs
2. **Update the production code** with the working approach
3. **Remove debug logging** from production
4. **Test thoroughly** with real webhook data

## Troubleshooting

If the debug script doesn't work:

1. **Check file permissions** on debug-rank-math.php
2. **Verify WordPress path** in the require_once statement
3. **Ensure administrator privileges** for the user
4. **Check for plugin conflicts** by deactivating other SEO plugins

For additional support, check the debug logs and share them with the plugin developer.