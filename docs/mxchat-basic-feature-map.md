# MxChat Basic - Comprehensive Feature Map

Generated from code-level inspection of `mxchat-basic/` in this workspace.

This document maps implemented features from code, including runtime behavior, storage, endpoints, and admin tooling.

---

## Plugin Overview

| Field | Value |
|---|---|
| Plugin Name | MxChat |
| Version | 3.0.9 |
| Plugin URI | `https://mxchat.ai/` |
| Main File | `mxchat-basic/mxchat-basic.php` |
| Text Domain | `mxchat` |
| Core Purpose | Multi-provider AI chatbot with RAG, actions/intents, live-agent handoff, and content tooling |

---

## 1) Core Platform Architecture

### 1.1 Bootstrap and class loading

`mxchat-basic.php`:

- Defines `MXCHAT_VERSION` from plugin header.
- Loads translations from:
  1. `/wp-content/languages/plugins/mxchat-<locale>.mo`
  2. plugin fallback `/languages`
- Includes core classes:
  - `class-mxchat-integrator.php`
  - `class-mxchat-admin.php`
  - `class-mxchat-public.php`
  - `class-mxchat-utils.php`
  - `class-mxchat-user.php`
  - `class-mxchat-meta-box.php`
  - `class-mxchat-chunker.php`
  - `class-mxchat-word-handler.php`
  - `class-mxchat-content-generator.php`
  - admin managers (`class-ajax-handler.php`, `class-pinecone-manager.php`, `class-knowledge-manager.php`)

### 1.2 Lifecycle handling

- Activation (`mxchat_activate`):
  - creates/migrates all core tables
  - ensures extra columns for older installs
  - sets up cron jobs
  - stores plugin version
- Deactivation (`mxchat_deactivate`):
  - clears scheduled hooks
  - clears fallback rate-limit flags
- Runtime update safety:
  - `mxchat_check_for_update()` runs on load
  - auto-recreates critical tables if missing
  - executes versioned migrations
  - includes additional admin-init migration guard (`mxchat_check_and_run_migrations`)

### 1.3 Caching-plugin compatibility layer

`mxchat-basic.php` adds compatibility filters for:

- WP Rocket
- LiteSpeed Cache
- Autoptimize
- SG Optimizer
- W3 Total Cache
- WP Super Cache

Goal: prevent chatbot JS/CSS/AJAX from breaking due to defer/minify/combine optimizations.

---

## 2) AI Provider and Model Features

### 2.1 Supported providers

Code paths in `class-mxchat-integrator.php` and `class-mxchat-admin.php` support:

- OpenAI
- Anthropic Claude
- xAI Grok
- DeepSeek
- Google Gemini
- OpenRouter (dynamic model catalog)

### 2.2 Model catalog (current admin-allowed set)

From admin model callback + model sanitizer:

- **OpenAI**
  - `gpt-5.2`
  - `gpt-5.1-chat-latest`
  - `gpt-5.1-2025-11-13`
  - `gpt-5`
  - `gpt-5-mini`
  - `gpt-5-nano`
- **Claude**
  - `claude-opus-4-6`
  - `claude-sonnet-4-6`
  - `claude-opus-4-5`
  - `claude-sonnet-4-5-20250929`
  - `claude-opus-4-1-20250805`
  - `claude-haiku-4-5-20251001`
  - `claude-opus-4-20250514`
  - `claude-sonnet-4-20250514`
- **xAI / Grok**
  - `grok-4-0709`
  - `grok-4-1-fast-reasoning`
  - `grok-4-1-fast-non-reasoning`
  - `grok-3-beta`
  - `grok-3-fast-beta`
  - `grok-3-mini-beta`
  - `grok-3-mini-fast-beta`
  - `grok-2`
- **DeepSeek**
  - `deepseek-chat`
- **Gemini**
  - `gemini-3-pro-preview`
  - `gemini-3-flash-preview`
  - `gemini-2.5-pro`
  - `gemini-2.5-flash`
  - `gemini-2.5-flash-lite`
  - `gemini-2.0-flash` (deprecated)
  - `gemini-2.0-flash-lite` (deprecated)
  - `gemini-1.5-pro` (deprecated)
  - `gemini-1.5-flash` (deprecated)
- **OpenRouter**
  - `openrouter` selector mode + dynamic model fetch from OpenRouter API

### 2.3 Embedding model support

- `text-embedding-ada-002`
- `text-embedding-3-small`
- `text-embedding-3-large`
- `voyage-3-large`
- `gemini-embedding-001`

Migration included:

- `gemini-embedding-exp-03-07` -> `gemini-embedding-001`.

### 2.4 Model deprecation migrations

`mxchat_migrate_deprecated_models()` auto-updates deprecated models:

- legacy Claude models -> modern Claude targets (notably Opus 4.6 / Haiku 4.5)
- deprecated GPT-4/3.5 families -> GPT-5.1 Chat Latest or GPT-5 Mini

Admin notices are generated for automatic migrations.

---

## 3) Chat Runtime and Frontend Experience

### 3.1 Widget/shortcode deployment

`class-mxchat-public.php`:

- Shortcode: `[mxchat_chatbot]`
- Auto-append floating bot on `wp_footer`
- Per-page hide control via post meta `_mxchat_hide_chatbot`
- Supports floating and embedded modes
- Supports per-bot render via `bot_id`

### 3.2 Session and conversation state model

Frontend (`js/chat-script.js`) + integrator:

- Session cookie per bot: `mxchat_session_id_<bot_id>`
- Per-session storage in `wp_options`:
  - `mxchat_history_<session>`
  - `mxchat_mode_<session>`
  - `mxchat_email_<session>`
  - `mxchat_name_<session>`
  - `mxchat_agent_name_<session>`
  - `mxchat_originating_page_<session>`
  - `mxchat_session_owner_<session>`
- Session ownership checks prevent cross-user session reuse.
- Supports auto-reset when ownership mismatch is detected.

### 3.3 Chat modes

- AI mode (default)
- Agent mode (`waiting_for_agent` / handoff flows)
- Mode indicator in frontend UI updates from explicit response metadata.

### 3.4 Streaming and fallback behavior

- Streaming entrypoint via `mxchat_stream_chat` AJAX and provider-specific stream functions.
- SSE-like chunk handling in frontend fetch stream parser.
- Supports stream and non-stream paths with automatic fallback handling.
- Includes streaming compatibility testing tools in admin.

### 3.5 Frontend UI/UX features

- Intro message + customizable title/placeholder text
- Popular questions (3 base + additional dynamic list)
- Optional pre-chat message + dismissal state
- Optional email gate and optional name field
- PDF/Word upload toolbar buttons
- Live-agent visual message style support
- Link tracking for bot/agent links
- Originating page tracking
- Markdown/HTML rendering pipeline:
  - headings
  - links/mailto/phone links
  - fenced code blocks
  - inline code
  - copy button for code blocks
- Admin-only testing panel/debug hooks (when enabled)

### 3.6 Script loading optimization modes

`script_loading_strategy` supports:

- `default`
- `defer`
- `delay_1s`
- `delay_3s`
- `delay_5s`
- `on_interaction`

Delayed strategies inject a custom loader and hydrate `window.mxchatChat`.

---

## 4) Knowledge Base and RAG Features

### 4.1 Storage and retrieval

Primary KB table: `mxchat_system_prompt_content` with embeddings and metadata.

RAG capabilities include:

- local vector similarity search in WordPress DB
- Pinecone retrieval path
- OpenAI Vector Store retrieval path
- configurable similarity threshold
- configurable unique-source cap (`rag_sources_limit`)
- optional citation-link output control (`citation_links_toggle`)
- contextual-awareness toggle for page context injection

### 4.2 Ingestion sources

Knowledge manager supports ingestion from:

- WordPress posts/pages/CPT
- sitemap XML URLs
- single URLs
- PDFs (including queued page-by-page processing)
- ACF fields (post-save hook integration)
- whitelisted custom meta fields

### 4.3 Queue processing subsystem

Queue tables:

- `mxchat_processing_queue`
- `mxchat_queue_meta`

Features:

- queue item status lifecycle (`pending`, `processing`, `completed`, `failed`)
- retries and error recording
- metadata per queue
- status polling endpoints
- clear/retry workflows

### 4.4 Role restrictions and taxonomy-role mapping

Features:

- role restriction field on KB entries
- per-entry role updates via AJAX
- tag-to-role mapping endpoints for access control workflows
- `mxchat_pinecone_roles` table includes `bot_id` for multi-bot scoping

---

## 5) Intents, Actions, and Tool-like Behaviors

### 5.1 Intent framework

Backed by `mxchat_intents` table:

- intent label
- phrase set
- embedding vector
- callback function
- similarity threshold
- enabled/disabled state
- `enabled_bots` scope (multi-bot)

### 5.2 Intent/admin operations

Supported operations:

- create/edit/delete intents
- enable/disable intents
- bulk delete
- threshold updates
- list retrieval for actions UI

### 5.3 Built-in behavior domains used by intent callbacks

Code paths include:

- email capture + Loops integration
- image generation (OpenAI images endpoint)
- Brave web search + Brave image search
- PDF discussion workflows
- Word document discussion workflows
- WooCommerce actions
- live agent transfer workflows (Slack/Telegram)

---

## 6) Live Agent Integration (Slack + Telegram)

### 6.1 Slack

- REST routes:
  - `/mxchat/v1/agent-response`
  - `/mxchat/v1/slack-interaction`
  - `/mxchat/v1/slack-messages`
- Verifies Slack signature (`X-Slack-Signature` + timestamp).
- Handoff flow can:
  - create channel per session
  - persist `mxchat_channel_<session>`
  - auto-invite configured agents
  - send conversation context + user message
  - update chat mode to `agent`

### 6.2 Telegram

- REST route: `/mxchat/v1/telegram-webhook`
- Secret token verification via `X-Telegram-Bot-Api-Secret-Token`.
- Handoff flow can:
  - create forum topic per session
  - persist topic/group options
  - send context and latest message
  - support close commands (e.g., `#close`, `#end`, `#disconnect`, `#done`)

### 6.3 Shared live-agent behavior

- waiting/notification responses to user
- mode indicator updates in frontend
- polling for new agent messages
- customizable away/notification messages

---

## 7) WooCommerce Features

Implemented across `class-mxchat-woocommerce.php` and integrator logic:

- Add-to-cart via AJAX
- Fetch user order history
- Product ID extraction from natural language:
  - product name
  - slug
  - SKU
- Last discussed product transient tracking
- Cart-state related helpers
- Product-aware response flows when commerce integration is enabled

---

## 8) Content Generator Subsystem

`includes/class-mxchat-content-generator.php`:

- Admin AJAX API:
  - generate
  - progress poll
  - mini-chat edit
  - history
  - post load/delete/status update
  - save generator settings
- Background generation flow:
  - planning step
  - optional image generation (parallel cURL multi support)
  - HTML + CSS output handling
  - sanitize and persist content
  - set featured image
  - set SEO-related meta fields
- Generated-content post metadata:
  - `_mxchat_generated`
  - `_mxchat_prompt`
  - `_mxchat_content_css`
  - `_mxchat_image_ids`
- Frontend CSS injection for generated posts (`wp_head`)
- Theme/template adjustments (full-width, title visibility, etc.)

---

## 9) Admin Surface and Operational Tooling

### 9.1 Admin pages

Top-level menu: `MxChat`

Subpages:

- Settings
- Knowledge
- Transcripts
- Actions
- Content
- Pro & Extensions (activation/extensions surface)

### 9.2 Operational tools

- Streaming compatibility test
- OpenAI Vector Store connection test
- Slack connection test
- API key checks
- Debug mode toggle
- Debug log read/clear
- Export settings
- Reset all settings
- Transcript export (CSV)
- Transcript translation persistence + retrieval

### 9.3 Add-on catalog surface (referenced)

`class-mxchat-addons.php` catalogs Pro extension cards (themes, moderation, forms, multi-bot manager, etc.) for upsell/install workflows.

---

## 10) Technical Inventory

### 10.1 Database tables (core)

| Table | Purpose |
|---|---|
| `mxchat_chat_transcripts` | canonical chat log storage (includes `rag_context`) |
| `mxchat_system_prompt_content` | KB chunks/content + embeddings + role/content type metadata |
| `mxchat_intents` | intent/action definitions and thresholds |
| `mxchat_url_clicks` | clicked-link analytics |
| `mxchat_pinecone_roles` | role restrictions by vector and bot |
| `mxchat_processing_queue` | background processing queue |
| `mxchat_queue_meta` | queue metadata key/value |
| `mxchat_transcript_translations` | stored transcript translations by language |

### 10.2 REST endpoints

- `GET/POST /mxchat/v1/stream` (SSE-like stream source for agent mode)
- `POST /mxchat/v1/agent-response`
- `POST /mxchat/v1/slack-interaction`
- `POST /mxchat/v1/slack-messages`
- `POST /mxchat/v1/telegram-webhook`

### 10.3 AJAX handlers (grouped)

#### Chat runtime and frontend

- `mxchat_handle_chat_request`
- `mxchat_stream_chat`
- `mxchat_fetch_conversation_history`
- `mxchat_fetch_new_messages`
- `mxchat_get_current_chat_mode`
- `mxchat_start_fresh_session`
- `mxchat_track_url_click`
- `mxchat_track_originating_page`
- `mxchat_dismiss_pre_chat_message`
- `mxchat_check_pre_chat_message_status`
- `mxchat_handle_save_email_and_response`
- `mxchat_check_email_provided`
- `mxchat_upload_pdf`
- `mxchat_remove_pdf`
- `mxchat_upload_word`
- `mxchat_remove_word`
- `mxchat_check_word_status`

#### WooCommerce

- `mxchat_add_to_cart`
- `mxchat_fetch_user_orders`

#### Knowledge base and queue

- `mxchat_get_status_updates`
- `mxchat_dismiss_completed_status`
- `mxchat_get_content_list`
- `mxchat_process_selected_content`
- `mxchat_save_inline_prompt`
- `mxchat_delete_pinecone_prompt`
- `mxchat_delete_chunks_by_url`
- `mxchat_delete_wordpress_prompt`
- `mxchat_bulk_delete_knowledge`
- `mxchat_update_role_restriction`
- `mxchat_get_next_queue_item`
- `mxchat_process_queue_item`
- `mxchat_get_queue_status`
- `mxchat_clear_queue`
- `mxchat_retry_failed`
- `mxchat_get_recent_entries`
- `mxchat_detect_sitemaps`
- `mxchat_refresh_pinecone_entries`
- `mxchat_paginate_entries`
- `mxchat_mark_queue_complete`
- `mxchat_add_tag_role_mapping`
- `mxchat_delete_tag_role_mapping`
- `mxchat_get_tag_role_mappings`
- `mxchat_bulk_update_tag_roles`

#### Actions/intents admin

- `mxchat_fetch_actions_list`
- `mxchat_toggle_action_status`
- `mxchat_bulk_delete_actions`
- `mxchat_add_intent_ajax`
- `mxchat_edit_intent_ajax`
- `mxchat_delete_intent_ajax`
- `mxchat_toggle_action`
- `mxchat_update_intent_threshold`

#### Admin/settings/license/debug/tools

- `mxchat_save_setting`
- `mxchat_save_prompts_setting`
- `migrate_pinecone_settings`
- `mxchat_save_selected_bot`
- `mxchat_check_api_keys`
- `mxchat_fetch_openrouter_models`
- `mxchat_test_streaming_actual`
- `mxchat_test_streaming`
- `mxchat_test_vectorstore_connection`
- `mxchat_test_slack_connection`
- `mxchat_get_system_info`
- `mxchat_get_similarity_threshold`
- `mxchat_get_kb_status`
- `mxchat_get_rag_context`
- `mxchat_toggle_debug_mode`
- `mxchat_get_debug_log`
- `mxchat_clear_debug_log`
- `mxchat_export_settings`
- `mxchat_reset_all_settings`
- `mxchat_handle_activate_license`
- `mxchat_check_license_status`
- `mxchat_deactivate_license`
- `mxchat_delete_chat_history`
- `mxchat_fetch_chat_history`
- `mxchat_fetch_conversation`
- `mxchat_export_transcripts`
- `mxchat_translate_messages`
- `mxchat_get_transcript_translation`
- `dismiss_live_agent_notice`
- `dismiss_theme_migration_notice`

#### Content generator

- `mxchat_generate_content`
- `mxchat_content_edit`
- `mxchat_content_progress`
- `mxchat_save_content_setting`
- `mxchat_content_history`
- `mxchat_load_post_for_edit`
- `mxchat_delete_content`
- `mxchat_update_post_status`
- `mxchat_generate_content_background`

### 10.4 Cron jobs and schedules

Primary scheduled hooks:

- `mxchat_reset_rate_limits` (hourly target)
- `mxchat_cleanup_old_transcripts` (daily, if enabled)
- `mxchat_send_delayed_transcript` (per-session delayed email)

Additional:

- custom schedule registration: `one_minute`
- fallback rate-limit cleanup path when WP-Cron is unavailable (`mxchat_use_fallback_rate_limits` + next-check option)

---

## 11) Option and State Surfaces

### 11.1 Main settings option (`mxchat_options`) - major key groups

- **API keys**
  - `api_key` (OpenAI)
  - `claude_api_key`
  - `xai_api_key`
  - `deepseek_api_key`
  - `gemini_api_key`
  - `voyage_api_key`
  - `openrouter_api_key`
  - `brave_api_key`
  - `loops_api_key`
- **Model/config**
  - `model`
  - `openrouter_selected_model`
  - `openrouter_selected_model_name`
  - `embedding_model`
  - `enable_streaming_toggle`
  - `enable_web_search`
  - `temperature`
  - `max_tokens`
- **RAG tuning**
  - `similarity_threshold`
  - `rag_sources_limit`
  - `contextual_awareness_toggle`
  - `citation_links_toggle`
- **Rate limiting**
  - `rate_limit_logged_out`
  - `role_rate_limits`
  - `rate_limit_message`
  - `rate_limits` (bot-aware structures in runtime)
- **UX / appearance**
  - title, intro, placeholder, button/icon colors
  - `pre_chat_message`
  - `popular_question_1..3`
  - `additional_popular_questions`
  - `chat_toolbar_toggle`
  - `mode_indicator_*`
  - `chat_persistence_toggle`
  - `script_loading_strategy`
  - `show_frontend_debugger`
- **Collection/integration**
  - `enable_email_block`
  - `enable_name_field`
  - `name_field_placeholder`
  - `loops_mailing_list`
- **Live agent**
  - `live_agent_status`
  - `live_agent_webhook_url`
  - `live_agent_secret_key`
  - `live_agent_bot_token`
  - `telegram_status`
  - `telegram_bot_token`
  - `telegram_group_id`
  - `telegram_webhook_secret`
  - notification/away message keys
- **Post visibility control**
  - `post_type_visibility_mode`
  - `post_type_visibility_list`

### 11.2 Additional option groups

- `mxchat_transcripts_options` (notifications, retention, auto-email behavior)
- `mxchat_prompts_options` (knowledge/pinecone toggles and related prompts)
- `mxchat_pinecone_addon_options`
- `mxchat_openai_vectorstore_options`
- `mxchat_theme_options`

### 11.3 Session/state options and transients

Frequent runtime state:

- option keys prefixed by:
  - `mxchat_history_`
  - `mxchat_mode_`
  - `mxchat_email_`
  - `mxchat_name_`
  - `mxchat_agent_name_`
  - `mxchat_session_owner_`
  - `mxchat_originating_page_`
  - `mxchat_channel_`
  - `mxchat_telegram_topic_`
- transients for:
  - PDF/Word uploads + embeddings
  - queue status IDs
  - last discussed product
  - processing status helpers

---

## 12) Security and Privacy Controls

- Nonce checks across admin/frontend AJAX endpoints.
- Capability checks for admin operations (`manage_options` etc.).
- Session ownership verification for history/chat/upload operations.
- Webhook verification:
  - Slack HMAC signature verification
  - Telegram secret token verification
- Input sanitization:
  - `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `esc_url_raw`, `wp_kses_post`
- SQL query hardening with prepared statements in critical paths.
- PDF URL acceptance checks include URL validation and HTTPS guard in runtime handling.
- Brave API fetch path uses safe remote request handling.
- Role/timeframe-based rate limiting with reset logic and fallback cleanup.

---

## 13) Upgrade and Migration Behavior

Implemented migrations include:

- missing-column repair for transcripts table (`rag_context`, user metadata)
- add `role_restriction` to KB table
- add `enabled_bots` to intents table
- add `bot_id` to `mxchat_pinecone_roles`
- add `content_type` to KB table
- queue table creation for background processing
- URL column widening to `TEXT`
- translation table creation migration
- deprecated model migrations (Claude/OpenAI)
- Gemini embedding model migration
- live-agent status normalization
- upgrade notice flags for specific release events (e.g., theme/live-agent transitions)

---

## 14) Key File Structure

```text
mxchat-basic/
├── mxchat-basic.php
├── admin/
│   ├── class-ajax-handler.php
│   ├── class-knowledge-manager.php
│   └── class-pinecone-manager.php
├── includes/
│   ├── class-mxchat-integrator.php
│   ├── class-mxchat-admin.php
│   ├── class-mxchat-public.php
│   ├── class-mxchat-content-generator.php
│   ├── class-mxchat-woocommerce.php
│   ├── class-mxchat-word-handler.php
│   ├── class-mxchat-addons.php
│   ├── admin-*.php page templates
│   └── pdf-parser/
├── js/
│   ├── chat-script.js
│   ├── mxchat-admin.js
│   ├── knowledge-processing.js
│   ├── mxchat_actions.js
│   ├── mxchat_transcripts.js
│   └── mxchat-content.js
├── css/
│   ├── chat-style.css
│   ├── admin-style.css
│   ├── knowledge-style.css
│   ├── actions.css
│   └── admin-content.css
└── languages/mxchat.pot
```

---

## 15) Practical Coverage Summary

From code, `mxchat-basic/` currently provides:

- Multi-provider chat runtime (OpenAI/Claude/xAI/DeepSeek/Gemini/OpenRouter)
- Streaming + fallback response pipeline
- RAG over local DB, Pinecone, and OpenAI Vector Store paths
- Knowledge ingestion from WP content, URLs/sitemaps, PDFs, and DOCX
- Intent/action engine with per-bot action scoping
- Live-agent handoff for Slack and Telegram
- WooCommerce transactional helpers (cart/orders/product intent)
- Content generation/editing subsystem with AI + image workflows
- Transcript analytics/translation/debug/export operational tooling
- Migration-safe schema/version management and caching-plugin compatibility controls
