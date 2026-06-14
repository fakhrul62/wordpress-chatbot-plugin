=== WP AI Chat ===
Contributors: fakhrulalam
Tags: ai, chatbot, chat, knowledge base, support
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-aware AI chat widget for WordPress with a crawlable knowledge base, optional OpenAI API key support, and free online fallback providers.

== Description ==

WP AI Chat adds a configurable chat widget to your WordPress site. It can crawl published pages and posts into a local knowledge base, use that knowledge in prompts, and answer visitor questions through an optional OpenAI API key or free online fallback providers.

Features:

* Crawl pages and posts into a local knowledge base.
* Add custom facts or Q&A entries manually.
* Optional OpenAI API key with encrypted storage.
* AI modes: Free only, OpenAI with free fallback, or OpenAI only.
* Free online fallback providers when no API key is configured or the primary provider fails.
* Configurable widget design, placement, visibility, tone, language, and content rules.
* Response caching with scheduled cleanup.
* Public chat endpoint with IP-based rate limiting.
* Optional attribution control, disabled by default.

Note: Crawled page content is injected into AI context. Only crawl content you trust, because adversarial text in site content can influence model behavior.

== Installation ==

1. Upload the `wp-aichat` folder to `/wp-content/plugins/`.
2. Activate WP AI Chat from the Plugins screen.
3. Open AI Chat > AI Configuration to configure provider behavior.
4. Open AI Chat > Knowledge Base and run a crawl.
5. Adjust widget design and placement as needed.

== Frequently Asked Questions ==

= Do I need an API key? =

No. The plugin includes free online fallback providers. For more reliable 24/7 responses, add an OpenAI API key in AI Chat > AI Configuration.

= Is the API key exposed to visitors? =

No. API keys are stored server-side, encrypted at rest where OpenSSL is available, and are never sent to frontend JavaScript.

= Does it crawl WooCommerce products? =

No. Product crawling is intentionally excluded.

== Changelog ==

= 1.0.21 =
* Improved fallback answer voice so local fallback speaks as the site instead of saying "Here is what I found."
* Tightened fallback confidence checks and filtered job/salary snippets from pricing answers.

= 1.0.20 =
* Added explicit AI mode selection.
* Shared OpenAI-compatible provider request helper.
* Added prompt override knowledge placeholder support.
* Added cache normalization and knowledge-version cache invalidation.
* Added token-budgeted knowledge injection and prompt-injection notice.
* Added optional attribution setting.

= 1.0.19 =
* Hardened API key handling and admin settings responses.
* Added encrypted OpenAI key storage and masked admin display.
* Moved deterministic knowledge extractors behind AI provider attempts.
* Added provider circuit breaker, reduced HTTP timeouts, fallback testing, and opt-in failure logging.
* Added custom knowledge entries, knowledge caching, large table warning, and cache cleanup cron.

= 1.0.18 =
* Improved chat widget opening animation and mobile placement.

= 1.0.17 =
* Added optional OpenAI key flow and knowledge text cleanup.
