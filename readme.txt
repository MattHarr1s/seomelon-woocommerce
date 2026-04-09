=== SEOMelon - AI SEO & Business Intelligence ===
Contributors: sandiasoftware
Tags: seo, woocommerce, ai, aeo, content optimization
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0-beta
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO advisor that researches your competitors, generates optimized content for Google and AI answer engines, and delivers business insights.

== Description ==

SEOMelon connects your WordPress site to the SEOMelon AI platform to deliver actionable SEO and AEO (Answer Engine Optimization) recommendations.

**What SEOMelon does:**

* Syncs your WooCommerce products, blog posts, and pages to the SEOMelon API for analysis
* Scans your content against competitors and current search trends
* Generates AI-optimized meta titles, descriptions, and FAQ schema
* Creates AEO-ready content designed to be cited by ChatGPT, Perplexity, and other AI assistants
* Delivers business insights: keyword gaps, catalog opportunities, seasonal trends, and quick wins
* Writes generated content back to your active SEO plugin (Yoast, Rank Math, SEOPress, or All in One SEO)
* Adds SEO score columns to your product, post, and page list tables
* Outputs JSON-LD FAQ schema on the front end automatically

**Supported SEO plugins:**

SEOMelon detects and integrates with your existing SEO plugin:

* Yoast SEO / Yoast SEO Premium
* Rank Math
* SEOPress
* All in One SEO

If no SEO plugin is installed, generated content is stored in SEOMelon custom meta fields for future use.

**Works with or without WooCommerce:**

* With WooCommerce: Analyze and optimize products, posts, pages, and categories
* Without WooCommerce: Analyze and optimize posts, pages, and categories

== Installation ==

1. Upload the `seomelon` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen.
3. Navigate to WooCommerce > SEOMelon (or Tools > SEOMelon if WooCommerce is not active).
4. Enter your API key from [seomelon.app](https://seomelon.app/settings) and click "Test Connection."
5. Select which content types to sync and save your settings.
6. Click "Sync All Content" to send your content to the SEOMelon API.
7. Click "Scan All" to analyze your content, then "Generate All" to create AI-optimized suggestions.
8. Review suggestions on the detail page and click "Apply" to write them back to your site.

== Frequently Asked Questions ==

= Do I need a SEOMelon account? =

Yes. Sign up at [seomelon.app](https://seomelon.app) to get your API key. A free tier is available.

= Does this plugin modify my theme files? =

No. SEOMelon writes meta titles and descriptions through your existing SEO plugin's fields. FAQ schema is output via a standard `wp_head` hook as JSON-LD, requiring no theme modifications.

= What data is sent to the SEOMelon API? =

Content titles, descriptions, URLs, images, and current meta titles and descriptions are sent for analysis. No customer data, order information, or personal information is transmitted.

= Can I use this without WooCommerce? =

Yes. Without WooCommerce, the plugin optimizes your blog posts, pages, and categories.

= Which SEO plugins are supported? =

Yoast SEO, Rank Math, SEOPress, and All in One SEO. The plugin automatically detects which one is active.

== Screenshots ==

1. Dashboard showing synced content with SEO scores
2. Content detail page with current vs suggested metadata
3. Settings page with API connection and content type configuration
4. Business insights with categorized recommendations
5. SEO score column in WooCommerce products list

== Changelog ==

= 1.1.0-beta =
* Stripe billing integration with 3-tier pricing (Free / Growth $24.99 / Advisor $79.99)
* One-click connect flow (no API key copy-paste required)
* Post-checkout success/cancel notifications
* Plan tier synced from API to local WP options
* Gamification: health score, achievements, streak tracking, weather status
* Google Search Console integration
* Multi-language SEO generation (12 languages)
* Competitive intelligence insights

= 1.0.0 =
* Initial release
* WooCommerce product, post, page, and category sync
* AI-powered SEO content generation
* AEO (Answer Engine Optimization) descriptions
* FAQ schema generation and JSON-LD output
* SEO plugin auto-detection (Yoast, Rank Math, SEOPress, AIOSEO)
* Business insights dashboard
* SEO score columns in admin list tables
* Auto-sync scheduling (daily/weekly)

== Upgrade Notice ==

= 1.0.0 =
Initial release of SEOMelon for WordPress.
