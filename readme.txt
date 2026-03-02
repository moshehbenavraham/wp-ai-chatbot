=== WP AI Chatbot ===
Contributors: aiwithapex
Tags: chatbot, ai, openai, claude, gemini
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3
License: MIT
License URI: https://opensource.org/licenses/MIT

Provider-agnostic AI chatbot for WordPress with RAG knowledge base, tool calling, and streaming support.

== Description ==

WP AI Chatbot is the ultimate AI chatbot plugin for WordPress. Connect any major AI provider, build a knowledge base from your content, and give your visitors an intelligent assistant — all from your WordPress dashboard.

**Key Features:**

* **Multi-provider support** — OpenAI (GPT-4o, o1, o3), Anthropic Claude, Google Gemini, DeepSeek, xAI Grok
* **Real-time streaming** — Server-Sent Events (SSE) for instant, token-by-token responses
* **RAG knowledge base** — Ingest posts, pages, PDFs, and web pages; retrieval-augmented generation with cosine similarity search
* **Tool calling / function calling** — Webhook tools, MCP (Model Context Protocol) servers, macro routing
* **Customizable appearance** — 8 preset color palettes, custom hex overrides, floating or inline widget mode
* **GDPR-ready** — Consent banner, data export/erasure via WP Privacy tools, configurable retention policy
* **EU AI Act compliant** — Non-dismissible AI disclosure notice (Article 50)
* **Migration tools** — One-click migration from axiachat-ai, mxchat-basic, and baachal plugins
* **WooCommerce compatible** — HPOS-aware, product search integration
* **Accessible** — WCAG 2.2 AA: keyboard navigation, focus traps, screen reader support, skip links
* **Secure** — AES-256-GCM API key encryption, rate limiting, content moderation, SSRF protection, audit logging

**Admin Dashboard:**

* General settings with provider configuration
* Knowledge base management with ingestion pipeline
* Tools page (webhooks, MCP servers, routing rules)
* Appearance customizer with live palette preview
* Security & privacy settings
* Transcripts & usage statistics with CSV export
* Diagnostics with health checks and system info
* Migration wizard with dry-run and rollback

== Installation ==

1. Upload the `wp-ai-chatbot` folder to the `/wp-content/plugins/` directory, or install via the WordPress plugin screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **WP AI Chatbot > General** and enter your AI provider API key.
4. Select a default provider and model.
5. The chat widget automatically appears on your site in floating mode. Use the `[wpaic_chatbot]` shortcode for inline mode.

== Frequently Asked Questions ==

= Which AI providers are supported? =

OpenAI (GPT-4o, GPT-4, o1, o3-mini), Anthropic Claude (3.5 Sonnet, Opus, Haiku), Google Gemini (2.0 Flash, 1.5 Pro), DeepSeek (V3, R1), and xAI Grok (2, 3).

= Do I need an API key? =

Yes. You need an API key from at least one supported provider. Enter it in **WP AI Chatbot > General**.

= Is it GDPR compliant? =

The plugin includes a configurable GDPR consent banner, integrates with WordPress Privacy tools for data export and erasure, and supports automatic session retention cleanup. See the admin guide for full configuration.

= Can I use it with WooCommerce? =

Yes. The plugin detects WooCommerce and is compatible with High-Performance Order Storage (HPOS). Product search tools can be enabled via the Tools page.

= How do I add my own content to the knowledge base? =

Go to **WP AI Chatbot > Knowledge Base**. You can ingest WordPress posts/pages, upload PDFs via the Media Library, or add web URLs for crawling. The plugin chunks your content, generates embeddings, and uses them for retrieval-augmented generation.

= Can I migrate from another chatbot plugin? =

Yes. The plugin includes built-in migrators for axiachat-ai, mxchat-basic, and baachal. Go to **WP AI Chatbot > Migration** to detect installed plugins and run a dry-run before migrating.

== Screenshots ==

1. Chat widget in floating mode with streaming response
2. Admin general settings — provider and model configuration
3. Knowledge base management with ingestion status
4. Appearance customizer with palette preview
5. Security settings with rate limiting and moderation

== Changelog ==

= 1.0.2 =
* Added minified JS/CSS assets for production
* Bundled production Composer dependencies in release ZIP
* Added WordPress.org readme.txt
* Generated i18n .pot template
* Updated GA checklist to reflect completed phases

= 1.0.1 =
* Added wpaic_structured_log() global function (fixes audit/security logging)
* Added tool_calls column to messages table for tool call history
* Tool call history visible in admin Transcripts > Inspect > Actions tab
* PHPStan clean pass (zero errors)
* Updated known-limitations.md for v1.0.1 accuracy
* Added Dependabot configuration and weekly operations CI workflow

= 1.0.0 =
* Initial release
* Multi-provider support (OpenAI, Claude, Gemini, DeepSeek, Grok)
* SSE streaming with AJAX fallback
* RAG knowledge base with PDF and web scraping
* Tool calling with webhook and MCP support
* GDPR consent, data export/erasure, retention policy
* EU AI Act AI disclosure notice
* Migration from axiachat-ai, mxchat-basic, baachal
* Full admin dashboard (7 pages)
* WCAG 2.2 AA accessibility
* CI/CD pipeline with 6 workflows

== Upgrade Notice ==

= 1.0.2 =
Production build improvements: minified assets, bundled Composer dependencies, i18n support.
