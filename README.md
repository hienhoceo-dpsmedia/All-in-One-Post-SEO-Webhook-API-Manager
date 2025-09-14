# All-in-One Post SEO Webhook & API Manager

Complete webhook management solution with SEO integration, API endpoints, and automation tools for WordPress posts.

## Features

- **Webhook Triggers**: Automatically send webhooks when post status changes
- **SEO Integration**: Works with RankMath and Yoast SEO plugins
- **REST API**: External API endpoints for keyword retrieval and webhook management
- **Manual Trigger**: Admin interface for manual webhook sending
- **Comprehensive Logging**: Track all webhook activities with automated cleanup
- **Security**: HMAC signature verification and proper access controls

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- RankMath SEO or Yoast SEO plugin (optional but recommended)

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Select the ZIP file and install
4. Activate the plugin

## Configuration

1. Go to Settings → SEO Webhook API
2. Configure your webhook URL and optional secret key
3. Select which post types should trigger webhooks
4. Choose your preferred SEO plugin integration
5. Test your webhook connection

## API Endpoints

### REST API (when enabled)

- `GET /wp-json/aipswam/v1/keywords/{post_id}` - Get SEO keywords for a post
- `POST /wp-json/aipswam/v1/webhooks/trigger/{post_id}` - Trigger webhook for a post
- `GET /wp-json/aipswam/v1/logs` - Get webhook logs

### Webhook Processing

- `POST /wp-admin/admin-ajax.php?action=process_webhook_response` - Process webhook responses with keyword data

## Security Features

- Input sanitization and validation
- CSRF protection with nonces
- Proper capability checks
- SQL injection prevention
- Webhook signature verification
- XSS protection throughout

## Webhook Payload Format

```json
{
  "post_id": 123,
  "post_title": "Sample Post Title",
  "post_content": "Post content...",
  "post_excerpt": "Post excerpt",
  "post_url": "https://example.com/sample-post/",
  "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit",
  "post_type": "post",
  "post_status": "publish",
  "author": "Admin User",
  "categories": ["Category 1", "Category 2"],
  "tags": ["tag1", "tag2"],
  "featured_image": "https://example.com/image.jpg",
  "timestamp": "2025-09-14 10:30:00",
  "trigger_event": "post_published"
}
```

## Response Processing

The plugin can process webhook responses containing keyword data:

```json
{
  "post_id": 123,
  "keywords": ["primary keyword", "secondary keyword 1", "secondary keyword 2"]
}
```

## Development

This plugin follows WordPress coding standards and includes:

- Proper security measures
- Database optimization with caching
- Comprehensive error handling
- Multilingual support ready

## Support

For support and feature requests, please use the GitHub issues section.

## License

GPLv2 or later - see [LICENSE.txt](LICENSE.txt) for details.

## About DPS.MEDIA JSC

Since 2017, DPS.MEDIA JSC has been a leading provider of digital marketing and AI automation solutions. With a focus on comprehensive digital transformation, we have served over 5,400 SME customers, helping them leverage cutting-edge technology for business growth.

**Contact Information:**
- 📍 56 Nguyễn Đình Chiểu, Phường Tân Định, Thành phố Hồ Chí Minh, Việt Nam
- 📞 0961545445
- 🌐 https://dps.media/