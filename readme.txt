=== All-in-One Post SEO Webhook & API Manager ===
Contributors: dpsmediajsc
Tags: webhook, seo, rankmath, yoast, api, automation, post, integration
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete webhook management solution with SEO integration, API endpoints, and automation tools for WordPress posts.

== Description ==

All-in-One Post SEO Webhook & API Manager is a comprehensive plugin that provides complete control over webhook notifications, SEO keyword management, and API integration for WordPress posts. Developed by DPS.MEDIA JSC with 7+ years of experience in digital marketing and AI automation.

**Key Features:**

🔧 **Webhook Management**
* Automatic webhook notifications for post status changes
* Configurable post types and trigger statuses
* Secure webhook authentication with HMAC signatures
* Manual webhook triggering capabilities
* Webhook connection testing tools

🎯 **SEO Integration**
* Support for RankMath SEO plugin
* Support for Yoast SEO plugin
* Dual plugin mode with fallback logic
* Automatic keyword setting from webhook responses
* Primary and secondary keyword management

🌐 **REST API Endpoints**
* `/wp-json/aipswam/v1/keywords/{post_id}` - Get keywords for a post
* `/wp-json/aipswam/v1/webhooks/trigger/{post_id}` - Trigger webhook for a post
* `/wp-json/aipswam/v1/logs` - Get webhook logs
* Full CRUD operations for webhook management

⚡ **Performance & Security**
* Async webhook requests for better performance
* Advanced caching system
* Input sanitization and validation
* Nonce protection for admin forms
* Comprehensive logging and error handling
* Capability-based access control

🎛️ **Admin Interface**
* Tabbed settings interface with 5 sections
* Real-time webhook testing
* Manual trigger interface
* API documentation
* About section with company information

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/all-in-one-post-seo-webhook-api-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings→SEO Webhook API screen to configure the plugin

== Configuration ==

After installation, navigate to **Settings → SEO Webhook API** to configure:

1. **General Settings**: Set your webhook URL and optional secret key
2. **Triggers**: Choose which post types and statuses should trigger webhooks
3. **SEO Integration**: Select your preferred SEO plugin (RankMath, Yoast, or both)
4. **Advanced**: Configure timeouts, API access, and manual triggers
5. **Tools**: Test connections, trigger webhooks manually, and view API documentation

== Frequently Asked Questions ==

= How do I set up the webhook? =

Go to Settings > SEO Webhook API and enter your webhook URL in the General tab. You can also add an optional secret key for authentication.

= What format should my webhook return? =

Your webhook should return JSON in this format:
```json
{
    "keywords": ["primary keyword", "secondary keyword 1", "secondary keyword 2"],
    "post_id": 123
}
```

= Which SEO plugins are supported? =

The plugin supports:
* RankMath SEO (primary)
* Yoast SEO (secondary/fallback)
* Both plugins simultaneously with intelligent fallback

= Is this plugin secure? =

Yes, the plugin includes comprehensive security measures:
* HMAC signature verification for webhook authentication
* Nonce protection for all admin forms
* Capability-based access control
* Input sanitization and validation
* SQL injection prevention

= Can I trigger webhooks manually? =

Yes! The plugin includes a manual trigger interface in the Tools tab where you can select any post and send a webhook immediately.

== Screenshots ==

1. Main admin interface with tabbed navigation
2. Webhook configuration settings
3. Post type and status trigger selection
4. SEO plugin integration settings
5. Webhook testing and manual trigger tools

== Changelog ==

= 2.3.0 =
* Fixed webhook trigger logic for pending to publish transitions
* Removed webhook secret feature for simplified configuration
* Improved settings saving logic across admin tabs
* Fixed JSON response issues in admin interface
* Enhanced debugging capabilities for webhook troubleshooting
* Optimized REST endpoint registration to prevent conflicts
* Removed duplicate webhook hooks to prevent race conditions
* Added comprehensive error logging for better diagnostics

= 2.0 =
* Complete rebranding to All-in-One Post SEO Webhook & API Manager
* Added dual SEO plugin support (RankMath + Yoast)
* Implemented comprehensive REST API endpoints
* Added manual webhook triggering capabilities
* Enhanced admin interface with tabbed navigation
* Added webhook testing and debugging tools
* Improved security with HMAC signatures
* Performance optimizations with caching
* Added comprehensive logging system
* Developed by DPS.MEDIA JSC with enterprise-grade features

= 1.2 =
* Initial release as Pending Post Webhook & RankMath Integration

== Upgrade Notice ==

= 2.0 =
Major update with complete rebranding, new features, and improved architecture. Please backup your database before upgrading.

== About DPS.MEDIA JSC ==

Since 2017, DPS.MEDIA JSC has been a leading provider of digital marketing and AI automation solutions. With a focus on comprehensive digital transformation, we have served over 5,400 SME customers, helping them leverage cutting-edge technology for business growth.

**Our Expertise:**
* Digital Marketing Strategy & Implementation
* AI & Automation Solutions
* Enterprise Workflow Integration
* Content Creation & Management
* E-commerce Optimization

**Why Choose Us:**
* ✅ 7+ years industry experience
* ✅ 5,400+ satisfied customers
* ✅ Expert team of digital specialists
* ✅ Cutting-edge technology solutions
* ✅ Results-driven approach

📍 56 Nguyễn Đình Chiểu, Phường Tân Định, Thành phố Hồ Chí Minh, Việt Nam
📞 0961545445
🌐 https://dps.media/