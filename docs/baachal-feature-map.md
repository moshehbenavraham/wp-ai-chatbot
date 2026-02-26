# Baachal AI Chatbot - Comprehensive Feature Map

Generated from code-level inspection of `baachal/` in this workspace.

This document maps implemented features from code (not marketing-only claims).

---

## Plugin Overview

| Field | Value |
|---|---|
| Plugin Name | Baachal AI Chatbot |
| Version | 1.0.4 |
| Main File | `baachal/baachal.php` |
| Text Domain | `baachal` |
| WordPress Requirement | 5.0+ |
| PHP Requirement | 7.4+ |
| WooCommerce Requirement | 3.0+ (for commerce features) |
| Primary Capability | Multi-provider AI chat with WooCommerce + website content context |

---

## 1) Core Architecture and Lifecycle

### 1.1 Bootstrap and initialization

Implemented in `baachal/baachal.php`:

- Defines constants:
  - `BAACHAL_VERSION`
  - `BAACHAL_PLUGIN_URL`
  - `BAACHAL_PLUGIN_PATH`
- Loads `includes/content-indexer.php`.
- Declares WooCommerce HPOS compatibility (`custom_order_tables`).
- Registers lifecycle hooks:
  - activation
  - deactivation
  - uninstall
- Registers frontend/admin/AJAX hooks through a filterable hook map (`baachal_register_hooks`).
- Registers `baachal_conversation` custom post type at `init`.

### 1.2 Activation/deactivation/uninstall behavior

- **Activation**:
  - registers CPT
  - creates content index table
  - seeds defaults (if options missing)
  - migrates legacy single-provider settings
  - flushes rewrite rules
- **Deactivation**:
  - clears transients
  - flushes rewrite rules
- **Uninstall**:
  - respects `baachal_preserve_data_on_uninstall`
  - optional full cleanup of:
    - `baachal_%` options
    - `baachal_content_index` table
    - all `baachal_conversation` posts
    - transients (`_transient_baachal_%` and timeout rows)

---

## 2) AI Provider and Model Features

### 2.1 Provider support

Supported providers:

- Google Gemini
- OpenAI
- Anthropic Claude
- xAI Grok

Provider option key: `baachal_ai_provider`.

### 2.2 Model catalogs (admin UI)

From `admin/tabs/general.php`:

- **Gemini**
  - `gemini-2.5-pro`
  - `gemini-2.5-flash`
  - `gemini-2.5-flash-lite`
  - `gemini-2.0-flash`
  - `gemini-2.0-flash-lite`
- **OpenAI**
  - `gpt-5`
  - `gpt-5-pro`
  - `gpt-5-mini`
  - `gpt-5-nano`
  - `gpt-4.1`
  - `gpt-4o`
- **Claude**
  - `claude-sonnet-4-5-20250929`
  - `claude-opus-4-1-20250805`
  - `claude-sonnet-4-20250514`
  - `claude-3-7-sonnet-20250219`
  - `claude-3-5-haiku-20241022`
- **Grok**
  - `grok-4-fast`
  - `grok-4-fast-reasoning`
  - `grok-4-fast-non-reasoning`

### 2.3 Provider API implementation

Implemented in `Baachal` methods:

- `call_gemini_api()`
- `call_openai_api()`
- `call_claude_api()`
- `call_grok_api()`

Shared behavior:

- API key required per selected provider (`baachal_<provider>_api_key`).
- Conversation history included when conversational memory is enabled.
- Site context assembled through `get_website_context()` and prepended/injected into provider request shape.

### 2.4 Legacy migration logic

- Migrates old `baachal_gemini_model` to new `baachal_ai_model`.
- Maps deprecated Gemini values (for older installations) to newer defaults.
- Ensures provider is set to `gemini` where needed for upgrades.

---

## 3) Chat Runtime and Conversation Features

### 3.1 Frontend widget behavior

Implemented by:

- `templates/chatbot-widget.php`
- `assets/chatbot.js`
- `assets/chatbot.css`

Behavior:

- Floating launcher with open/close transitions.
- Optional clear-history button in header.
- Welcome message shown as first bot message.
- Message input + send button.
- Enter key to send.
- Input auto-resize.
- Loading indicator while awaiting response.
- Auto-scroll to newest message.

### 3.2 Session model

- Client-generated session IDs: `chat_` + UUIDv4.
- Stored in `localStorage` key: `baachal_session_id`.
- Server validates format: `^chat_[a-f0-9\-]{36}$`.
- No PHP sessions used.

### 3.3 Message persistence and history

- Toggle: `baachal_message_persistence`.
- Messages stored per conversation post in `_chat_messages` meta.
- Frontend loads existing history via `baachal_get_chat_history`.
- Clear history via `baachal_clear_chat_history`:
  - empties stored messages
  - frontend starts a new session ID

### 3.4 Conversation memory for AI context

- Toggle: `baachal_conversation_memory`.
- Depth option: `baachal_memory_limit` (1..50 via admin validation).
- History is transformed to assistant/user role pairs before provider calls.

### 3.5 Message safety and formatting

- User input is sanitized (`sanitize_text_field`) and length-limited to 1000 chars.
- Bot response rendering in JS includes:
  - safe HTML parsing with allowlisted tags/attrs
  - markdown-style link conversion: `[text](url)` -> `<a ...>`
- Product-card HTML append pipeline for WooCommerce recommendations.

---

## 4) WooCommerce Feature Set

### 4.1 Commerce context injection

When WooCommerce integration is enabled (`baachal_woocommerce_integration`):

- Product context is appended to AI system context.
- If no direct relevant products are found, falls back to sample/popular products.

### 4.2 Product retrieval strategy

`search_products_by_keywords()` uses staged retrieval:

1. Exact phrase search on product titles.
2. Weighted keyword search (title gets more weight than content).
3. Attribute/variation metadata search.
4. Taxonomy search (`product_cat`, `product_tag`).
5. Fallback popular/recent products if needed.

### 4.3 Dynamic term extraction

`get_dynamic_product_terms()` builds query vocabulary from:

- categories
- attributes
- tags
- repeated multi-word title phrases

Controls:

- `baachal_max_terms`
- `baachal_min_term_length`
- `baachal_cache_duration`
- `baachal_exclude_terms`

Cache:

- transient `baachal_dynamic_terms`
- invalidated on product/content/taxonomy/attribute changes and settings updates

### 4.4 Product card rendering

For matched products, plugin builds structured card data and renders HTML grid:

- image (with placeholder fallback)
- product name
- price
- link to product page
- stock-aware messaging
- attributes/categories summary in AI context

Key filters:

- `baachal_product_card_data`
- `baachal_product_cards_html`

---

## 5) Content Indexing and Website Search

### 5.1 Index storage model

Custom table: `{wp_prefix}baachal_content_index`

Columns:

- `id` (PK)
- `post_id`
- `post_type`
- `title`
- `content`
- `excerpt`
- `url`
- `last_updated`

Indexes:

- key on `post_id`
- key on `post_type`
- FULLTEXT on (`title`, `content`, `excerpt`)

### 5.2 Indexing lifecycle

Implemented in `includes/content-indexer.php`:

- auto-index on `save_post` (if enabled)
- delete from index on `delete_post`
- bulk rebuild via `index_all_content()`

Configuration:

- enable toggle: `baachal_content_indexing_enabled`
- auto-index toggle: `baachal_auto_index`
- post-type allowlist: `baachal_indexable_post_types`
- max retrieval count: `baachal_content_max_results`

### 5.3 Search behavior

Search order:

1. FULLTEXT natural-language query.
2. Fallback LIKE query with title/content/excerpt relevance weighting.

Admin includes:

- index stats (total, per-type, last update)
- manual reindex action
- test-search action

### 5.4 Per-content exclusion control

- Side meta box on selected post types.
- Meta key: `_baachal_exclude_from_index`.

---

## 6) Admin Interface and Settings Surface

### 6.1 Admin pages and tabs

Settings page: `Settings > Baachal AI bot`.

Tabs:

- General
- WooCommerce
- Product Search
- Content Indexing
- UI Styling
- Advanced

Admin JS features:

- tab persistence in `localStorage` (`baachal_active_tab`)
- provider-specific API key/model group toggling
- model auto-select on provider change
- color picker with presets + HEX synchronization
- AJAX actions for reindex/test-search/cache-clear

### 6.2 Validation and sanitization highlights

- Provider allowlist: `gemini|openai|claude|grok`.
- API key formats:
  - Gemini/Grok: `^[A-Za-z0-9_-]+$`
  - OpenAI: `^sk-[A-Za-z0-9_-]+$`
  - Claude: `^sk-ant-[A-Za-z0-9_-]+$`
- Title max length: 100 chars.
- Welcome message max length: 500 chars.
- Model format regex: `^[a-zA-Z0-9\.-]+$`.
- Numeric bounds enforced for search/memory/content limits.

---

## 7) Security and Privacy Controls

- Nonce verification on chat/history/clear + admin AJAX endpoints.
- Capability checks (`manage_options`) for admin-only operations.
- Strict session-id format validation.
- Message input length limit and sanitization.
- SQL safety via `prepare()` in query-heavy paths.
- Output escaping and `wp_kses_post` use in widget rendering.
- Preserve-or-delete behavior on uninstall (`baachal_preserve_data_on_uninstall`).

---

## 8) Performance and Caching

- Product query result transient:
  - key pattern: `baachal_products_<md5>_<limit>`
  - TTL: 1 hour
- Dynamic term transient:
  - key: `baachal_dynamic_terms`
  - TTL configurable (`baachal_cache_duration`)
- Automatic cache clearing on:
  - product CRUD/trash restore
  - term create/edit/delete
  - WooCommerce attribute create/update/delete
  - settings save

---

## 9) Extensibility Surface

### 9.1 WordPress actions

- `baachal_init`
- `baachal_before_init`
- `baachal_after_init`
- `baachal_plugin_activated`
- `baachal_plugin_deactivated`
- `baachal_plugin_uninstalled`
- `baachal_after_enqueue_scripts`
- `baachal_before_widget_render`
- `baachal_after_widget_render`
- `baachal_after_successful_response`
- `baachal_after_error_response`
- `baachal_after_save_message`
- `baachal_before_save_settings`
- `baachal_save_additional_settings`
- `baachal_before_settings_tabs`
- `baachal_after_settings_tabs`
- `baachal_settings_tab_content`
- `baachal_before_settings_submit`
- `baachal_after_settings_submit`
- `baachal_after_settings_form`
- `baachal_before_chatbot_widget`
- `baachal_before_toggle_button`
- `baachal_after_toggle_button`
- `baachal_before_chatbot_header`
- `baachal_chatbot_header_buttons_before`
- `baachal_chatbot_header_buttons_after`
- `baachal_after_chatbot_header`
- `baachal_before_welcome_message`
- `baachal_after_welcome_message`
- `baachal_before_input_area`
- `baachal_after_input_area`
- `baachal_before_container_end`
- `baachal_after_chatbot_container`
- `baachal_after_chatbot_widget`

### 9.2 WordPress filters

- `baachal_register_hooks`
- `baachal_js_dependencies`
- `baachal_css_dependencies`
- `baachal_localized_data`
- `baachal_custom_css`
- `baachal_is_enabled`
- `baachal_before_process_message`
- `baachal_custom_message_handler`
- `baachal_api_params`
- `baachal_bot_response`
- `baachal_error_response`
- `baachal_should_save_message`
- `baachal_before_save_message`
- `baachal_message_limit`
- `baachal_conversation_title`
- `baachal_memory_limit`
- `baachal_conversation_history`
- `baachal_important_product_terms`
- `baachal_product_card_data`
- `baachal_product_cards_html`
- `baachal_setting_enabled`
- `baachal_setting_ai_provider`
- `baachal_setting_gemini_api_key`
- `baachal_setting_openai_api_key`
- `baachal_setting_claude_api_key`
- `baachal_setting_grok_api_key`
- `baachal_settings_tabs`
- `baachal_welcome_message`
- `baachal_show_clear_history`
- `baachal_chatbot_title`
- `baachal_chat_icon_content`
- `baachal_close_icon_content`
- `baachal_input_placeholder`
- `baachal_send_button_content`
- `baachal_loading_content`

### 9.3 JavaScript event hooks (`assets/chatbot.js`)

- `baachal_before_open`
- `baachal_after_open`
- `baachal_before_close`
- `baachal_after_close`
- `baachal_before_send_message` (cancelable/modifiable)
- `baachal_sending_started`
- `baachal_response_received`
- `baachal_response_success`
- `baachal_response_error`
- `baachal_connection_error`
- `baachal_before_add_message`
- `baachal_after_add_message`
- `baachal_filter_error_message` (triggerHandler)
- `baachal_filter_connection_error` (triggerHandler)

---

## 10) Data Model and Technical Surface

### 10.1 Custom post type

`baachal_conversation`

- admin UI enabled
- capability-gated to `manage_options`
- stores metadata:
  - `_session_id`
  - `_user_id`
  - `_user_ip`
  - `_user_agent`
  - `_chat_messages`

### 10.2 Additional post meta

- `_baachal_exclude_from_index` for index opt-out.

### 10.3 AJAX endpoint inventory

- `baachal_message` (`priv` + `nopriv`)
- `baachal_get_chat_history` (`priv` + `nopriv`)
- `baachal_clear_chat_history` (`priv` + `nopriv`)
- `clear_baachal_cache` (`priv`)
- `baachal_test_content_search` (`priv`)
- `baachal_reindex_content` (`priv`)

### 10.4 REST and cron

- No custom REST routes in this plugin.
- No dedicated WP-Cron schedules/jobs in this plugin.

---

## 11) Option Registry (Core)

| Option | Default | Validation / Sanitization | Notes |
|---|---|---|---|
| `baachal_enabled` | `1` | checkbox sanitization | master on/off |
| `baachal_ai_provider` | `gemini` | allowlist | active provider |
| `baachal_ai_model` | `gemini-2.5-flash` | regex model format | selected model |
| `baachal_gemini_api_key` | empty | regex format check | provider key |
| `baachal_openai_api_key` | empty | regex format check | provider key |
| `baachal_claude_api_key` | empty | regex format check | provider key |
| `baachal_grok_api_key` | empty | regex format check | provider key |
| `baachal_title` | `Baachal AI Bot` | max 100 chars | widget header title |
| `baachal_welcome_message` | default greeting | max 500 chars | first bot message |
| `baachal_primary_color` | `#007cba` | `sanitize_hex_color` | theme color |
| `baachal_secondary_color` | `#f0f0f1` seeded | `sanitize_hex_color` | see inconsistency notes |
| `baachal_position` | `bottom-right` | text sanitize | widget placement |
| `baachal_size` | `medium` | text sanitize | widget width preset |
| `baachal_border_radius` | `12` seeded | integer | see inconsistency notes |
| `baachal_chat_height` | `400` | integer | 200..600 in UI |
| `baachal_font_size` | `14` | integer | 10..20 in UI |
| `baachal_animation_enabled` | `1` | checkbox | UI transitions |
| `baachal_woocommerce_integration` | `1` | checkbox | commerce mode |
| `baachal_message_persistence` | `1` | checkbox | save/restore chats |
| `baachal_show_clear_history` | `1` | checkbox | header clear button |
| `baachal_max_terms` | `50` | clamped int | extraction limit |
| `baachal_min_term_length` | `3` | clamped int | term token threshold |
| `baachal_cache_duration` | `DAY_IN_SECONDS` | clamped int | dynamic term cache ttl |
| `baachal_exclude_terms` | stopword list | textarea -> array | dynamic term filtering |
| `baachal_content_indexing_enabled` | `1` | checkbox | website content context |
| `baachal_auto_index` | `1` | checkbox | reindex on save |
| `baachal_content_max_results` | `5` | clamped int | search result cap |
| `baachal_indexable_post_types` | `post,page` | array sanitize | index scope |
| `baachal_preserve_data_on_uninstall` | `1` | checkbox | cleanup behavior |
| `baachal_debug_mode` | `0` | checkbox | verbose errors/log hints |
| `baachal_conversation_memory` | `0` | checkbox | include prior turns |
| `baachal_memory_limit` | `10` | clamped int | history depth |

---

## 12) Key Files

- `baachal/baachal.php` - main plugin runtime, APIs, WooCommerce logic, hooks, settings wiring.
- `baachal/includes/content-indexer.php` - indexing schema + search engine.
- `baachal/admin/settings-page.php` - settings save/validation + tab container.
- `baachal/admin/tabs/*.php` - tab-specific admin UIs.
- `baachal/templates/chatbot-widget.php` - frontend widget markup and hook points.
- `baachal/assets/chatbot.js` - frontend interaction, events, chat AJAX flow.
- `baachal/assets/admin.js` - admin tab behavior + AJAX tools.
- `baachal/uninstall.php` - uninstall cleanup fallback.

---

## 13) Known Implementation Inconsistencies (Important)

These are code-level inconsistencies to be aware of:

1. **Secondary color default mismatch**
   - seeded default: `#f0f0f1`
   - runtime/settings fallback: `#f1f1f1`
2. **Border radius default mismatch**
   - seeded default: `12`
   - runtime/settings fallback: `15`
3. **Max terms range mismatch**
   - UI hints: 10..200
   - save validation clamps to 1..50
4. **Cache duration range mismatch**
   - UI hints up to 1 week
   - save validation clamps to 60..86400 seconds
5. **AI model sanitize fallback**
   - fallback value: `gemini-2.0-flash-exp`
   - not present in current model dropdown list

---

## 14) Version Notes (from plugin/readme context)

- `1.0.4`: conversational memory and richer product-card flow improvements.
- `1.0.3`: SQL/session/security hardening.
- `1.0.2`: multi-provider expansion.
- `1.0.1`: stability improvements.
- `1.0.0`: initial Gemini + WooCommerce + indexing release.
