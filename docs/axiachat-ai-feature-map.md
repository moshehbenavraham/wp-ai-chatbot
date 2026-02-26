# AxiaChat AI - Comprehensive Feature Map

Generated from code-level inspection of `axiachat-ai/` in this workspace.

This document maps implemented behavior from code (not marketing-only claims), including runtime behavior, storage, endpoints, admin tooling, and verified mismatches.

---

## Placeholder

File rebuild in progress.
# AxiaChat AI - Comprehensive Feature Map

Generated from code-level inspection of `axiachat-ai/` in this workspace.

This document maps implemented behavior from code (not marketing-only claims), including runtime behavior, storage, endpoints, admin tooling, and verified mismatches.

---

## Plugin Overview

| Field | Value |
|---|---|
| Plugin Name | AxiaChat AI - Free AI Chatbot (Answers Customers Automatically) |
| Version | 3.0.2 |
| Plugin URI | `https://axiachat.org` |
| Main File | `axiachat-ai/axiachat-ai.php` |
| Text Domain | `axiachat-ai` |
| WordPress Requirement | 5.0+ |
| PHP Requirement | 7.4+ |
| Core Purpose | Multi-provider AI chatbot with RAG, tool execution, and business add-ons |

---

## 1) Core Platform Architecture

### 1.1 Bootstrap and module loading

`axiachat-ai/axiachat-ai.php`:

- Defines plugin constants (`AICHAT_VERSION`, plugin paths/URLs, debug constants).
- Initializes licensing/gating layer (`includes/freemius-init.php`).
- Loads core runtime modules:
  - provider adapters + provider registry
  - model registry
  - settings, shortcode, core AJAX runtime
  - bots/training/context modules
  - usage/log modules
  - cron/background processors
- Loads add-ons conditionally (option and license gates):
  - AI Tools, MCP, Leads, Appointments, Custom Webhooks, LiveChat, Web Scraper
- Provides script i18n helpers (`aichat_enqueue_script_i18n`, `aichat_register_script_i18n`).

### 1.2 Lifecycle behavior

- **Activation**
  - Creates/migrates core tables with `dbDelta`.
  - Seeds default options.
  - Ensures a default bot exists.
  - Syncs local tools to DB (when AI Tools is active).
  - Defers test-page creation using transient flag.
- **Deactivation**
  - Keeps data, does not drop tables.
- **Uninstall**
  - Deletes only a small option subset (`aichat_openai_api_key`, `aichat_chat_color`, `aichat_position`).
  - Does not remove plugin tables.

### 1.3 Runtime migration passes

- Upgrade checks run on `plugins_loaded` and `admin_init`.
- Adds missing columns and repairs missing tables for existing installs.
- Migrates old capability name (`openai_web_search` -> `web_search`).

### 1.4 Provider abstraction

Implemented in:

- `includes/interfaces/interface-aichat-provider.php`
- `includes/class-aichat-provider-registry.php`
- `includes/providers/class-openai-provider.php`
- `includes/providers/class-claude-provider.php`
- `includes/providers/class-gemini-provider.php`

Behavior:

- Shared provider interface with registry-based dispatch.
- Provider-specific request orchestration and retries.
- Tool continuation (`tool_pending`) support in provider flows.

---

## 2) AI Provider and Model Features

### 2.1 Supported chat providers

- OpenAI
- Anthropic Claude
- Google Gemini

### 2.2 Central model registry

`includes/model-registry.php` is the canonical model source for:

- model IDs, labels, tags
- context windows and output token guidance
- multimodal and thinking flags
- pricing metadata
- aliases/deprecated IDs
- fallback metadata

This registry is reused across admin model UI and cost calculations.

### 2.3 Cost integration

- `includes/usage-functions.php` computes micro-USD costs.
- Daily usage aggregates are upserted in `wp_aichat_usage_daily`.

### 2.4 Embedding model behavior

RAG embedding paths support OpenAI and Gemini, with Claude fallback routing to OpenAI for embedding where needed.

---

## 3) Chat Runtime and Frontend Experience

### 3.1 Deployment modes

- Global floating widget (`AIChat_Core` rendered in `wp_footer`).
- Inline shortcode embedding (`[aichat]`).
- Multi-bot rendering by slug.
- Global widget is skipped when shortcode instance exists.

### 3.2 Frontend UI behavior

From `includes/shortcode.php` + `assets/js/aichat-frontend.js`:

- Per-bot title/role/placeholder/send label.
- Avatar/icon support.
- Minimize/super-minimize/close controls.
- Suggestions UI (count + style).
- Conversation clear/reset behavior.
- Optional per-bot history persistence.

### 3.3 Session and client state

- Session cookie: `aichat_sid`.
- GDPR cookie: `aichat_gdpr_ok`.
- Widget visual state in `sessionStorage`.

### 3.4 Voice and consent

- Voice input via `SpeechRecognition` / `webkitSpeechRecognition`.
- GDPR consent gate bubble before standard chat flow when enabled.

### 3.5 Core AJAX chat pipeline

Endpoints:

- `aichat_process_message` (`priv` + `nopriv`)
- `aichat_get_history` (`priv` + `nopriv`)

Runtime checks:

- nonce verification
- optional captcha hook (`aichat_validate_captcha`)
- honeypot blocking
- rate limit + cooldown
- spam signature checks
- moderation checks

### 3.6 Tool continuation flow

`includes/class-aichat-ajax.php` supports multi-round tool orchestration:

- model may return `status = tool_pending`
- frontend sends continuation request with tool outputs
- continuation paths for OpenAI/Claude/Gemini
- tool state persistence in `wp_aichat_tool_states`

### 3.7 Markdown/rendering safety

- Server-side markdown rendering (`Parsedown` when available, fallback parser otherwise).
- Supports headings, emphasis, inline/fenced code, lists, links.
- Final HTML sanitized (`wp_kses`/`wp_kses_post`).
- Frontend adds URL linkify and adjacent link de-duplication.

### 3.8 External embed loader

`assets/js/aichat-embed-loader.js`:

- Fetches nonce/UI map from `/?aichat_embed_nonce=1`.
- Supports bot selection via query parameter (`bots=...`).
- Uses Shadow DOM when available.
- Applies branding payload from endpoint response.

---

## 4) Knowledge Base and RAG System

### 4.1 Context model and retrieval

Main modules:

- `includes/contexto-functions.php`
- `includes/contexto-ajax-create.php`
- `includes/contexto-ajax-modify.php`
- `includes/contexto-ajax-settings.php`

Features:

- Context entities in `wp_aichat_contexts`.
- Local embedding retrieval and remote Pinecone-style retrieval paths.
- Query-time context assembly (`aichat_get_context_for_question`).

### 4.2 Ingestion sources

- WordPress posts/pages
- WooCommerce products
- uploaded PDF/TXT files
- web pages via Web Scraper add-on

### 4.3 Indexing options

Per-context indexing controls include:

- excerpt
- URL
- featured image URL
- taxonomy fields
- WooCommerce static fields (short description, attributes)
- custom meta keys

### 4.4 Chunking and indexing behavior

- Chunk defaults approximately 1000 words with 180 overlap.
- Multi-chunk storage in `wp_aichat_chunks`.
- Unique key on `(post_id, id_context, chunk_index)`.

### 4.5 WooCommerce dynamic enrichment

At retrieval time, product rows can be enriched with:

- live price/regular price
- SKU
- stock status

### 4.6 Background processing and autosync

`includes/aichat-cron.php` provides:

- cursor-based embedding batches
- lock-based concurrency control
- rescue scan for pending/in-progress contexts
- hourly autosync for modified/new content
- orphan chunk cleanup

### 4.7 PDF extraction pipeline

`includes/contexto-pdf-ajax.php` + `includes/pdf-ai-vision.php`:

- Upload entities in `aichat_upload`
- chunk entities in `aichat_upload_chunk`
- local parsing plus AI vision OCR fallback (Imagick-based)

### 4.8 Web Scraper add-on

`includes/add-ons/web-scraper/*`:

- URL fetch/crawl endpoints
- BFS crawl with depth/page limits
- URL normalization and non-content skip rules
- DOM/XPath extraction with fallback strategies
- import into same upload/chunk pipeline

---

## 5) AI Tools and Macro Platform

### 5.1 Tool registry and execution

From `includes/add-ons/ai-tools/api.php` and execution trait:

- Tool registration with schema normalization (`aichat_register_tool_safe`)
- bot-level capability settings
- unified execution flow with call logging

### 5.2 Macro system

`includes/add-ons/ai-tools/macro-api.php`:

- macro persistence in `wp_aichat_macros`
- source/source_ref metadata
- macro expansion to atomic tools
- source-based cleanup for add-on managed macros

### 5.3 Provider-native web search injection

When `web_search` macro is enabled:

- OpenAI: injects native `web_search`
- Claude: injects `web_search_20250305`
- Gemini: injects `google_search` only when REST constraints allow it

Gemini REST limitation is explicitly handled:

- if function declarations already exist, plugin skips `google_search` injection.

### 5.4 Admin AI Tools surface

`includes/add-ons/ai-tools/admin-*.php`:

- bot capability selector
- rule/config save-load
- tool listing and test runner
- execution logs UI

### 5.5 Built-in sample tools/macros

`includes/add-ons/ai-tools/tools-sample.php`:

- `web_search` macro scaffold
- admin email tool
- client email tool with authorization/rate-limit controls
- local tool DB sync routine

---

## 6) Business Add-ons

### 6.1 Leads

`includes/add-ons/leads/*`:

- Destinations:
  - internal table (`wp_aichat_leads`)
  - CPT (`aichat_lead`)
  - Contact Form 7
  - WPForms
  - Google Sheets (OAuth + client)
- Admin operations: settings, status updates, delete/bulk delete, CSV export.
- Lead-save tool integration in chat flows.
- Cleanup handler exists (`cleanup_old_leads`) but scheduling mismatch is documented in section 13.

### 6.2 Appointments

`includes/add-ons/appointments/*`:

- Adapter architecture:
  - internal
  - Google Calendar
  - Bookly
  - Amelia
  - SSA
- Tables:
  - `wp_aichat_appointments`
  - `wp_aichat_appointment_slots`
- Booking/availability/cancel tool flows.
- Hourly reminder cron (`aichat_appointments_send_reminders`).

### 6.3 MCP

`includes/add-ons/mcp/*`:

- HTTP transport architecture with client manager.
- Lazy server initialization strategy.
- Admin server CRUD plus connection/tool test endpoints.
- Tool sync into `wp_aichat_tools`.
- Dynamic MCP tool proxy registration + macro generation.
- Plan-based server limits.

STDIO note:

- `class-stdio-transport.php` is intentionally non-functional; STDIO support is removed in favor of HTTP-only transport.

### 6.4 Custom Webhooks

`includes/add-ons/custom-webhooks/*`:

- Definitions stored in `wp_aichat_custom_webhooks`.
- Per-webhook configuration:
  - method/url/headers
  - params schema + static params
  - optional conversation history
  - success/error messages
- Runtime executor with placeholder replacement and SSRF-oriented host blocking.
- Each webhook can be exposed as tool + macro.

### 6.5 LiveChat

`includes/add-ons/livechat/*`:

- Tables:
  - `wp_aichat_livechat_sessions`
  - `wp_aichat_livechat_messages`
- Session states:
  - `bot`
  - `pending_agent`
  - `agent_connected`
  - `closed`
- Handoff/take/return/close workflows.
- Admin dashboard polling and visitor-side polling/message AJAX.
- REST integrations for Slack, Telegram, and generic webhook.
- Professional-plan gating in loader.

---

## 7) Usage, Logs, and Diagnostics

### 7.1 Usage and cost

Modules:

- `includes/usage.php`
- `includes/usage-functions.php`
- `includes/usage-ajax.php`

Capabilities:

- today/7d/30d KPI summary
- timeseries payloads
- top model costs
- last conversations list
- pricing tables tab derived from model registry data

### 7.2 Conversation logs

Modules:

- `includes/logs.php`
- `includes/logs-detail.php`

Capabilities:

- session filters/list view
- transcript detail view
- conversation delete workflows

### 7.3 Debug logs and diagnostics export

From `axiachat-ai.php`:

- log tail/download/clear AJAX endpoints
- diagnostics export endpoint
- logs stored in uploads path (`axiachat-ai/`)

---

## 8) Admin Surface Map

### 8.1 Main admin pages

Top-level menu slug: `aichat-settings`

Key pages include:

- Settings
- Bots
- Training
- Setup Wizard
- Logs
- Usage / Cost
- AI Tools
- Leads
- Appointments
- MCP Servers
- LiveChat

### 8.2 Hidden/internal pages

- training instructions/context subpages
- context create/modify/settings/import pages
- logs detail
- AI Tools logs
- connect installer page

---

## 9) Data Model and Endpoint Inventory

### 9.1 Core tables provisioned by core plugin

- `wp_aichat_contexts`
- `wp_aichat_conversations`
- `wp_aichat_chunks`
- `wp_aichat_usage_daily`
- `wp_aichat_tool_states`
- `wp_aichat_bots`
- `wp_aichat_tool_calls`
- `wp_aichat_macros`
- `wp_aichat_tools`

### 9.2 Add-on tables

- `wp_aichat_leads`
- `wp_aichat_appointments`
- `wp_aichat_appointment_slots`
- `wp_aichat_livechat_sessions`
- `wp_aichat_livechat_messages`
- `wp_aichat_custom_webhooks`

### 9.3 Referenced table with missing provisioning

- `wp_aichat_webhook_logs` is referenced by webhook executor logging, but no create/migrate routine exists in core or custom-webhooks loader.

### 9.4 Custom post types

- `aichat_upload`
- `aichat_upload_chunk`
- `aichat_lead`

### 9.5 REST endpoints

Under `aichat/v1`:

- `/livechat/slack`
- `/livechat/slack/events`
- `/livechat/telegram`
- `/livechat/webhook`

### 9.6 AJAX endpoint modules

AJAX handlers are implemented in:

- `includes/class-aichat-ajax.php`
- `includes/bots_ajax.php`
- `includes/training-ajax.php`
- `includes/easy-config.php`
- `includes/contexto-ajax-create.php`
- `includes/contexto-ajax-modify.php`
- `includes/contexto-ajax-settings.php`
- `includes/contexto-pdf-ajax.php`
- `includes/usage-ajax.php`
- `includes/add-ons/ai-tools/admin-ajax.php`
- `includes/add-ons/mcp/admin-ajax.php`
- `includes/add-ons/web-scraper/admin-ajax.php`
- `includes/add-ons/leads/admin-ajax.php`
- `includes/add-ons/appointments/admin-ajax.php`
- `includes/add-ons/custom-webhooks/admin-ajax.php`
- `includes/add-ons/livechat/admin-ajax.php`

---

## 10) Cron and Background Jobs

- Custom schedule: `oneminute`
- Custom schedule: `aichat_hourly`
- Recurring hook: `aichat_cron_process_contexts`
- Single-event worker: `aichat_process_embeddings_batch`
- Recurring hook: `aichat_autosync_hourly`
- Recurring hook: `aichat_cleanup_tool_states`
- Appointments reminder hook: `aichat_appointments_send_reminders` (hourly)

---

## 11) Security, Privacy, and Governance

- Nonce verification across AJAX and admin actions.
- Capability checks for admin-only operations.
- API keys encrypted at rest in options (AES-256-GCM envelope).
- Moderation controls:
  - banned words
  - blocked IP/CIDR
  - optional OpenAI moderation API
- Abuse controls:
  - honeypot
  - rate limiting/cooldown
  - spam signature detection
  - adaptive temporary IP blocks after repeated moderation failures
- Embed-origin allowlist + CORS enforcement for embed/AJAX contexts.
- Custom webhook executor blocks localhost/private-network targets.
- Markdown output is sanitized after render.

---

## 12) Extensibility Surface (selected)

Representative filters/actions:

- Chat/runtime:
  - `aichat_pre_process_message`
  - `aichat_provider_override`
  - `aichat_messages_before_provider`
  - `aichat_maybe_intercept_request`
  - `aichat_response_pre_persist`
  - `aichat_response_data`
- RAG/prompting:
  - `aichat_generate_embedding_override`
  - `aichat_embedding_provider`
  - `aichat_system_prompt`
  - `aichat_security_policy`
  - `aichat_index_text_parts`
- Provider/tool integration:
  - `aichat_openai_responses_tools`
  - `aichat_claude_messages_tools`
  - `aichat_gemini_tools`
  - `aichat_tool_execute`

---

## 13) Known Implementation Inconsistencies (Important)

1. **Daily-total behavior value mismatch (`hide` vs `hidden`)**
   - UI and sanitizer use `hide`.
   - Runtime check compares against `hidden`.
   - Result: "hide widget completely" behavior does not execute as intended.

2. **LiveChat frontend-localize filter is never applied**
   - LiveChat registers `add_filter('aichat_frontend_localize_data', ...)`.
   - Core localization does not call `apply_filters('aichat_frontend_localize_data', ...)`.
   - Result: LiveChat frontend data extension path is inactive.

3. **Leads cleanup hook is unscheduled**
   - Leads manager hooks cleanup to `aichat_daily_cleanup`.
   - No schedule registration/event exists for this hook.
   - Result: retention cleanup does not run automatically.

4. **Webhook logs table missing creation**
   - Webhook executor writes to `wp_aichat_webhook_logs` if present.
   - No create/migrate routine for this table exists in inspected code.
   - Result: webhook execution logging is non-functional by default.

5. **MCP STDIO transport is stub-only**
   - `class-stdio-transport.php` remains present but intentionally fails (`removed` notice).
   - Result: effective MCP transport architecture is HTTP-only.

---

## 14) Key High-Value Files

- `axiachat-ai/axiachat-ai.php` - bootstrap, lifecycle, menus, migrations, diagnostics.
- `includes/class-aichat-ajax.php` - chat runtime and tool orchestration.
- `includes/contexto-functions.php` - RAG chunking, embeddings, retrieval.
- `includes/model-registry.php` - model metadata and pricing source.
- `includes/settings.php` - options, sanitization, encrypted key helpers.
- `includes/aichat-cron.php` - background indexing/autosync maintenance jobs.
- `includes/add-ons/ai-tools/api.php` - tool registry and native search injection.
- `includes/add-ons/mcp/class-mcp-client-manager.php` - MCP lifecycle/execution manager.
- `includes/add-ons/livechat/class-livechat-manager.php` - handoff session/message orchestration.
- `includes/add-ons/custom-webhooks/class-webhook-executor.php` - webhook runtime and logging path.

---

## 15) Practical Coverage Summary

From code, `axiachat-ai/` currently provides:

- Multi-provider AI chat runtime.
- Multi-bot deployment and configuration (global widget + shortcode).
- RAG ingestion/index/retrieval over site content, uploads, and scraped pages.
- Tool/macro platform with provider-native search integration.
- Business add-ons (leads, appointments, MCP, custom webhooks, livechat).
- Usage/cost analytics, conversation logs, and diagnostics tooling.
- Security/compliance controls (encryption, moderation, abuse controls, origin restrictions, consent gate).
- License-aware gating for premium feature surfaces.


# AxiaChat AI - Comprehensive Feature Map

Generated from code-level inspection of `axiachat-ai/` in this workspace.

This document maps implemented behavior from code (not marketing-only claims), including runtime behavior, storage, endpoints, admin tooling, and verified mismatches.

---

## Plugin Overview

| Field | Value |
|---|---|
| Plugin Name | AxiaChat AI - Free AI Chatbot (Answers Customers Automatically) |
| Version | 3.0.2 |
| Plugin URI | `https://axiachat.org` |
| Main File | `axiachat-ai/axiachat-ai.php` |
| Text Domain | `axiachat-ai` |
| WordPress Requirement | 5.0+ |
| PHP Requirement | 7.4+ |
| Core Purpose | Multi-provider AI chatbot with RAG, tool execution, and business add-ons |

---

## 1) Core Platform Architecture

### 1.1 Bootstrap and module loading

`axiachat-ai/axiachat-ai.php`:

- Defines plugin constants (`AICHAT_VERSION`, plugin paths/URLs, debug constants).
- Initializes license/gating layer (`includes/freemius-init.php`).
- Loads core runtime modules:
  - providers + provider registry
  - model registry
  - settings, shortcode, core AJAX
  - bots/training/context modules
  - usage/log modules
  - cron/background processors
- Loads add-ons conditionally (by options/license checks):
  - AI Tools
  - MCP
  - Leads
  - Appointments
  - Custom Webhooks
  - LiveChat
  - Web Scraper
- Provides script i18n helpers (`aichat_enqueue_script_i18n`, `aichat_register_script_i18n`).

### 1.2 Activation/deactivation/uninstall behavior

- **Activation**
  - Creates/migrates core tables with `dbDelta`.
  - Seeds defaults.
  - Ensures a default bot exists.
  - Syncs local tools into DB if AI Tools is active.
  - Defers test-page creation using transient flag.
- **Deactivation**
  - Keeps data (no table/option cleanup).
- **Uninstall**
  - Deletes a minimal option set only (`aichat_openai_api_key`, `aichat_chat_color`, `aichat_position`).
  - Does not drop plugin tables.

### 1.3 Runtime migrations and repair routines

- Upgrade checks run on `plugins_loaded` and `admin_init`.
- Adds missing table columns and creates missing tables where needed.
- Migrates old web-search capability key (`openai_web_search` -> `web_search`).
- Performs bot schema adjustments over time (`tools_json`, UI fields, etc.).

### 1.4 Provider abstraction

Implemented in:

- `includes/interfaces/interface-aichat-provider.php`
- `includes/class-aichat-provider-registry.php`
- `includes/providers/class-openai-provider.php`
- `includes/providers/class-claude-provider.php`
- `includes/providers/class-gemini-provider.php`

Behavior:

- Shared provider interface with registry-based dispatch.
- Provider-specific execution and retries handled by adapters.
- Tool continuation (`tool_pending`) support in provider flows.

---

## 2) AI Provider and Model Features

### 2.1 Supported chat providers

- OpenAI
- Anthropic Claude
- Google Gemini

### 2.2 Central model registry

`includes/model-registry.php` is the canonical model source for:

- model IDs, labels, tags
- context windows and output token guidance
- multimodal/thinking flags
- pricing metadata
- aliases/deprecated IDs
- fallback metadata

This same registry feeds admin model UI payloads and cost logic.

### 2.3 Cost tracking integration

- `includes/usage-functions.php` computes micro-USD cost per turn.
- Daily usage is upserted into `wp_aichat_usage_daily`.
- Usage pages read aggregated metrics and model-level costs.

### 2.4 Embedding model behavior

RAG embeddings support OpenAI and Gemini paths, with Claude embedding fallback logic routed to OpenAI where required.

---

## 3) Chat Runtime and Frontend Experience

### 3.1 Deployment modes

- Global floating widget (`AIChat_Core` in `wp_footer`).
- Inline shortcode embedding (`[aichat]`).
- Multi-bot rendering by slug.
- Global widget is skipped when shortcode instance is detected.

### 3.2 Frontend UI behavior

From `includes/shortcode.php` + `assets/js/aichat-frontend.js`:

- Per-bot title/role/placeholder/send label.
- Avatar/icon support.
- Minimize/super-minimize/close controls.
- Suggestion chips (count + style).
- Conversation clear/reset.
- Per-bot history persistence setting.

### 3.3 Session and client state

- Session cookie: `aichat_sid`.
- GDPR cookie: `aichat_gdpr_ok`.
- Widget visual state in `sessionStorage` (per bot key).

### 3.4 Voice and consent features

- Voice input via `SpeechRecognition` / `webkitSpeechRecognition`.
- GDPR consent gate bubble before normal chat flow when enabled.

### 3.5 Core AJAX chat pipeline

Endpoints:

- `aichat_process_message` (`priv` + `nopriv`)
- `aichat_get_history` (`priv` + `nopriv`)

Checks in runtime:

- nonce verification
- optional captcha filter (`aichat_validate_captcha`)
- honeypot blocking
- rate limiting and cooldown
- spam signature checks
- moderation checks

### 3.6 Tool continuation flow (`tool_pending`)

`includes/class-aichat-ajax.php` supports multi-round tool orchestration:

- model may return `status = tool_pending`
- frontend sends continuation request with tool outputs
- continuation implemented for OpenAI/Claude/Gemini paths
- tool state persisted in `wp_aichat_tool_states`

### 3.7 Markdown/rendering safety

- Server-side markdown rendering with `Parsedown` when available, fallback parser otherwise.
- Supports headings, emphasis, inline/fenced code, lists, links.
- Final HTML sanitized with allowlisted tags (`wp_kses`/`wp_kses_post`).
- Frontend adds URL linkify + adjacent duplicate-link cleanup.

### 3.8 External embed loader

`assets/js/aichat-embed-loader.js`:

- Gets nonce/UI map from `/?aichat_embed_nonce=1`.
- Supports selecting bots via query (`bots=...`).
- Uses Shadow DOM when available.
- Applies optional branding payload from endpoint.

---

## 4) Knowledge Base and RAG System

### 4.1 Context model and retrieval

Main modules:

- `includes/contexto-functions.php`
- `includes/contexto-ajax-create.php`
- `includes/contexto-ajax-modify.php`
- `includes/contexto-ajax-settings.php`

Features:

- Context entities in `wp_aichat_contexts`.
- Local embedding retrieval and remote Pinecone-style retrieval paths.
- Context assembly for prompt building (`aichat_get_context_for_question`).

### 4.2 Ingestion sources

- WordPress posts/pages
- WooCommerce products
- uploaded PDF/TXT files
- web pages from Web Scraper add-on

### 4.3 Indexing option surface

Per-context indexing controls include:

- excerpt
- URL
- featured image URL
- taxonomy fields
- WooCommerce static fields (short description/attributes)
- custom meta keys

### 4.4 Chunking and indexing behavior

- Chunk defaults approximately 1000 words with 180 overlap.
- Multi-chunk storage in `wp_aichat_chunks`.
- Unique key on `(post_id, id_context, chunk_index)`.

### 4.5 WooCommerce dynamic enrichment at query time

For product rows, context can be enriched with live:

- price/regular price
- SKU
- stock status

### 4.6 Background processing and autosync

`includes/aichat-cron.php` provides:

- cursor-based embedding batches
- locking for concurrent safety
- rescue scan job for pending/in-progress contexts
- hourly autosync for modified/new content
- orphan chunk cleanup

### 4.7 PDF extraction pipeline

`includes/contexto-pdf-ajax.php` + `includes/pdf-ai-vision.php`:

- Upload entities in `aichat_upload`
- chunk entities in `aichat_upload_chunk`
- local parsing + AI vision OCR fallback (Imagick-based)

### 4.8 Web Scraper add-on

`includes/add-ons/web-scraper/*`:

- URL fetch/crawl endpoints
- BFS crawl with depth/page limits
- URL normalization and skip rules for non-content routes
- DOM/XPath extraction with fallback strategies
- import into same upload/chunk ingestion pipeline

---

## 5) AI Tools and Macro Platform

### 5.1 Tool registry and execution

From `includes/add-ons/ai-tools/api.php` and execution trait:

- Tool registration with schema normalization (`aichat_register_tool_safe`)
- bot-level capability settings
- unified execution flow with call logging

### 5.2 Macro system

`includes/add-ons/ai-tools/macro-api.php`:

- macro persistence in `wp_aichat_macros`
- source/source_ref metadata
- macro expansion to atomic tools
- source-based cleanup for add-on managed macros

### 5.3 Provider-native web search injection

When `web_search` macro is enabled:

- OpenAI: injects native `web_search`
- Claude: injects `web_search_20250305`
- Gemini: injects `google_search` only when REST constraints allow it

Gemini REST limitation is explicitly handled:

- if function declarations are already present, plugin skips `google_search` injection.

### 5.4 Admin AI Tools surface

`includes/add-ons/ai-tools/admin-*.php`:

- bot capability selector
- rule/config save-load
- tool listing and test runner
- execution logs UI

### 5.5 Built-in sample tools/macros

`includes/add-ons/ai-tools/tools-sample.php`:

- `web_search` macro scaffold
- admin email tool
- client email tool with auth/rate-limit controls
- local tool DB sync routine

---

## 6) Business Add-ons

### 6.1 Leads

`includes/add-ons/leads/*`:

- Destinations:
  - internal table (`wp_aichat_leads`)
  - CPT (`aichat_lead`)
  - Contact Form 7
  - WPForms
  - Google Sheets (OAuth + client)
- Admin: settings, status updates, delete/bulk delete, CSV export.
- Lead-saving tool integration in chat workflows.
- Cleanup handler exists (`cleanup_old_leads`) but scheduling mismatch is noted in section 13.

### 6.2 Appointments

`includes/add-ons/appointments/*`:

- Adapter architecture:
  - internal
  - Google Calendar
  - Bookly
  - Amelia
  - SSA
- Tables:
  - `wp_aichat_appointments`
  - `wp_aichat_appointment_slots`
- Booking/availability/cancel tool flows.
- Hourly reminder cron (`aichat_appointments_send_reminders`).

### 6.3 MCP

`includes/add-ons/mcp/*`:

- HTTP transport architecture with client manager.
- Lazy server initialization strategy.
- Admin server CRUD + connection/tool test endpoints.
- Tool sync to `wp_aichat_tools`.
- Dynamic MCP tool proxy registration + macro generation.
- Plan-based MCP server limits.

STDIO note:

- `class-stdio-transport.php` is intentionally non-functional; STDIO support is removed in favor of HTTP-only transport.

### 6.4 Custom Webhooks

`includes/add-ons/custom-webhooks/*`:

- Definitions stored in `wp_aichat_custom_webhooks`.
- Per-webhook configuration:
  - method/url/headers
  - params schema + static params
  - optional conversation history
  - success/error messages
- Runtime executor with placeholder replacement and SSRF-oriented host blocking.
- Each webhook can be exposed as tool + macro.

### 6.5 LiveChat

`includes/add-ons/livechat/*`:

- Tables:
  - `wp_aichat_livechat_sessions`
  - `wp_aichat_livechat_messages`
- Session states:
  - `bot`
  - `pending_agent`
  - `agent_connected`
  - `closed`
- Handoff, take, return-to-bot, close workflows.
- Admin dashboard with polling.
- Visitor-side polling/message AJAX.
- REST integration endpoints for Slack/Telegram/generic webhook.
- Professional-plan gating in loader.

---

## 7) Usage, Logs, and Diagnostics

### 7.1 Usage and cost

Modules:

- `includes/usage.php`
- `includes/usage-functions.php`
- `includes/usage-ajax.php`

Capabilities:

- today/7d/30d KPI summary
- timeseries payloads
- top model costs
- last conversations list
- pricing tables tab derived from model registry data

### 7.2 Conversation logs

Modules:

- `includes/logs.php`
- `includes/logs-detail.php`

Capabilities:

- session filters and list view
- transcript detail view
- conversation delete workflows

### 7.3 Debug logs and diagnostics export

From `axiachat-ai.php`:

- log file tail/download/clear AJAX endpoints
- diagnostics export endpoint
- logs stored under uploads path (`axiachat-ai/`)

---

## 8) Admin Surface Map

### 8.1 Main admin pages

Top-level menu: `aichat-settings`

Key pages:

- Settings
- Bots
- Training
- Setup Wizard
- Logs
- Usage / Cost
- AI Tools
- Leads
- Appointments
- MCP Servers
- LiveChat

### 8.2 Hidden/internal pages

- training instructions/context pages
- context create/modify/settings/import pages
- logs detail
- AI Tools logs
- connect installer page

---

## 9) Data Model and Endpoint Inventory

### 9.1 Core tables provisioned by core plugin

- `wp_aichat_contexts`
- `wp_aichat_conversations`
- `wp_aichat_chunks`
- `wp_aichat_usage_daily`
- `wp_aichat_tool_states`
- `wp_aichat_bots`
- `wp_aichat_tool_calls`
- `wp_aichat_macros`
- `wp_aichat_tools`

### 9.2 Add-on tables

- `wp_aichat_leads`
- `wp_aichat_appointments`
- `wp_aichat_appointment_slots`
- `wp_aichat_livechat_sessions`
- `wp_aichat_livechat_messages`
- `wp_aichat_custom_webhooks`

### 9.3 Referenced table with missing provisioning

- `wp_aichat_webhook_logs` is referenced by webhook executor logging, but no creation routine exists in core or custom-webhooks loader (see section 13).

### 9.4 Custom post types

- `aichat_upload`
- `aichat_upload_chunk`
- `aichat_lead`

### 9.5 REST endpoints

Under `aichat/v1`:

- `/livechat/slack`
- `/livechat/slack/events`
- `/livechat/telegram`
- `/livechat/webhook`

### 9.6 AJAX endpoint modules

AJAX handlers are implemented in:

- `includes/class-aichat-ajax.php`
- `includes/bots_ajax.php`
- `includes/training-ajax.php`
- `includes/easy-config.php`
- `includes/contexto-ajax-create.php`
- `includes/contexto-ajax-modify.php`
- `includes/contexto-ajax-settings.php`
- `includes/contexto-pdf-ajax.php`
- `includes/usage-ajax.php`
- `includes/add-ons/ai-tools/admin-ajax.php`
- `includes/add-ons/mcp/admin-ajax.php`
- `includes/add-ons/web-scraper/admin-ajax.php`
- `includes/add-ons/leads/admin-ajax.php`
- `includes/add-ons/appointments/admin-ajax.php`
- `includes/add-ons/custom-webhooks/admin-ajax.php`
- `includes/add-ons/livechat/admin-ajax.php`

---

## 10) Cron and Background Jobs

- Custom schedule: `oneminute`
- Custom schedule: `aichat_hourly`
- Recurring hook: `aichat_cron_process_contexts`
- Single-event worker: `aichat_process_embeddings_batch`
- Recurring hook: `aichat_autosync_hourly`
- Recurring hook: `aichat_cleanup_tool_states`
- Appointments reminder hook: `aichat_appointments_send_reminders` (hourly)

---

## 11) Security, Privacy, and Governance

- Nonce verification across AJAX/admin actions.
- Capability checks for admin-only operations.
- API keys encrypted at rest (AES-256-GCM envelope).
- Moderation controls:
  - banned words
  - blocked IP/CIDR
  - optional OpenAI moderation API
- Abuse controls:
  - honeypot
  - rate limiting/cooldown
  - spam signature detection
  - adaptive temporary IP blocks after repeated moderation failures
- Embed-origin allowlist + CORS enforcement for embed/AJAX contexts.
- Custom webhook executor blocks localhost/private-network targets.
- Markdown output sanitized after render.

---

## 12) Extensibility Surface (selected)

Representative filters/actions include:

- Chat/runtime:
  - `aichat_pre_process_message`
  - `aichat_provider_override`
  - `aichat_messages_before_provider`
  - `aichat_maybe_intercept_request`
  - `aichat_response_pre_persist`
  - `aichat_response_data`
- RAG/prompting:
  - `aichat_generate_embedding_override`
  - `aichat_embedding_provider`
  - `aichat_system_prompt`
  - `aichat_security_policy`
  - `aichat_index_text_parts`
- Provider/tool integration:
  - `aichat_openai_responses_tools`
  - `aichat_claude_messages_tools`
  - `aichat_gemini_tools`
  - `aichat_tool_execute`

---

## 13) Known Implementation Inconsistencies (Important)

1. **Daily-total behavior value mismatch (`hide` vs `hidden`)**
   - UI/sanitizer uses `hide`.
   - runtime branch checks `hidden`.
   - Result: "hide widget completely" does not execute as intended.

2. **LiveChat frontend-localize filter is never applied**
   - LiveChat adds `add_filter('aichat_frontend_localize_data', ...)`.
   - Core localization does not call `apply_filters('aichat_frontend_localize_data', ...)`.
   - Result: LiveChat frontend data extension path is inactive.

3. **Leads cleanup hook is unscheduled**
   - Leads manager hooks cleanup to `aichat_daily_cleanup`.
   - No schedule registration/event for this hook exists.
   - Result: retention cleanup does not run automatically.

4. **Webhook logs table missing creation**
   - Custom webhook executor writes to `wp_aichat_webhook_logs` if present.
   - No creation routine for this table exists in inspected code.
   - Result: webhook execution logging is non-functional by default.

5. **MCP STDIO transport is stub-only**
   - `class-stdio-transport.php` remains present but intentionally fails (`removed` notice).
   - Result: effective architecture is HTTP-only MCP transport.

---

## 14) Key High-Value Files

- `axiachat-ai/axiachat-ai.php` - bootstrap, lifecycle, menus, migrations, diagnostics.
- `includes/class-aichat-ajax.php` - chat runtime and tool orchestration.
- `includes/contexto-functions.php` - RAG chunking/embedding/retrieval.
- `includes/model-registry.php` - model metadata and pricing source.
- `includes/settings.php` - options, sanitization, encrypted key helpers.
- `includes/aichat-cron.php` - background indexing/autosync maintenance jobs.
- `includes/add-ons/ai-tools/api.php` - tool registry and native web-search injection.
- `includes/add-ons/mcp/class-mcp-client-manager.php` - MCP client lifecycle and execution.
- `includes/add-ons/livechat/class-livechat-manager.php` - handoff session/message orchestration.
- `includes/add-ons/custom-webhooks/class-webhook-executor.php` - webhook runtime and optional logging.

---

## 15) Practical Coverage Summary

From code, `axiachat-ai/` currently provides:

- Multi-provider AI chat runtime.
- Multi-bot management and deployment (global widget + shortcode).
- RAG ingestion/index/retrieval across site content, uploads, and web scraping.
- Tool/macro platform with provider-native search integration.
- Business automation add-ons (leads, appointments, MCP, custom webhooks, livechat).
- Usage/cost analytics, conversation logs, and diagnostics tooling.
- Security/compliance controls (encryption, moderation, abuse controls, origin restrictions, consent gate).
- License-aware gating for premium surfaces.
# AxiaChat AI - Comprehensive Feature Map

Generated from code-level inspection of `axiachat-ai/` in this workspace.

This document maps implemented behavior from code (not marketing-only claims), including runtime behavior, storage, endpoints, admin tooling, and verified inconsistencies.

---

## Plugin Overview

| Field | Value |
|---|---|
| Plugin Name | AxiaChat AI - Free AI Chatbot (Answers Customers Automatically) |
| Version | 3.0.2 |
| Plugin URI | `https://axiachat.org` |
| Main File | `axiachat-ai/axiachat-ai.php` |
| Text Domain | `axiachat-ai` |
| WordPress Requirement | 5.0+ |
| PHP Requirement | 7.4+ |
| Core Purpose | Multi-provider AI chatbot with RAG, tools/macros, and add-ons for leads, appointments, MCP, webhooks, and live chat |

---

## 1) Core Platform Architecture

### 1.1 Bootstrap and module loading

`axiachat-ai/axiachat-ai.php`:

- Defines core constants (`AICHAT_VERSION`, plugin paths/URLs, debug constants).
- Initializes Freemius/license layer (`includes/freemius-init.php`).
- Loads core modules:
  - providers and provider registry
  - model registry
  - settings, shortcode, AJAX runtime
  - bots, training/context modules
  - usage and logs modules
  - cron/background processors
- Loads add-ons conditionally from options/license checks:
  - AI Tools
  - MCP
  - Leads
  - Appointments
  - Custom Webhooks
  - LiveChat
  - Web Scraper
  - Andromeda Connect installer helper
- Registers script i18n helpers (`aichat_enqueue_script_i18n`, `aichat_register_script_i18n`).

### 1.2 Activation, deactivation, uninstall behavior

- **Activation (`aichat_activation`)**
  - Creates/migrates core tables with `dbDelta`.
  - Seeds defaults/options.
  - Ensures default bot exists.
  - Syncs local tools to DB (if AI Tools loaded).
  - Defers test page creation with transient flag.
- **Deactivation**
  - Intentionally keeps data; no destructive cleanup.
- **Uninstall**
  - Deletes a minimal option subset only (`aichat_openai_api_key`, `aichat_chat_color`, `aichat_position`).
  - Does not drop plugin tables.

### 1.3 Runtime migrations and repair passes

- Migration checks run on `plugins_loaded` and `admin_init`:
  - adds missing columns in existing tables
  - ensures tables exist after upgrades
  - web-search capability migration (`openai_web_search` -> `web_search`)
  - bot schema column additions (`tools_json`, UI fields, etc.)

### 1.4 Provider abstraction layer

Implemented in:

- `includes/interfaces/interface-aichat-provider.php`
- `includes/class-aichat-provider-registry.php`
- `includes/providers/class-openai-provider.php`
- `includes/providers/class-claude-provider.php`
- `includes/providers/class-gemini-provider.php`

Behavior:

- Common provider interface with registry-based dispatch.
- Provider-specific execution paths with shared orchestration by AJAX runtime.
- Tool continuation support (`tool_pending`) across supported providers.

---

## 2) AI Provider and Model Features

### 2.1 Supported providers

Supported chat providers:

- OpenAI
- Anthropic Claude
- Google Gemini

### 2.2 Central model registry

`includes/model-registry.php` is the single source of truth for:

- canonical model IDs
- labels and tags (`new`, `recommended`, `legacy`, etc.)
- context windows and output token guidance
- multimodal/thinking capability flags
- pricing metadata
- aliases/deprecated IDs
- fallback ordering metadata

Registry payload is localized into admin bot tooling (`model_registry`) for UI consistency.

### 2.3 Pricing and cost calculation integration

- Usage/cost modules consume registry-derived pricing.
- `aichat_calc_cost_micros()` calculates per-turn cost in micro-USD.
- Daily aggregates are upserted in `wp_aichat_usage_daily`.

### 2.4 Embedding model/provider behavior

RAG embeddings support:

- OpenAI embedding models
- Gemini embedding model path
- Claude embedding path fallback to OpenAI when needed

---

## 3) Chat Runtime and Frontend Experience

### 3.1 Deployment modes

- Global floating widget rendered from `wp_footer` (`AIChat_Core`).
- Inline embedding via shortcode `[aichat]`.
- Multiple bot instances by slug.
- Global widget auto-suppressed when a shortcode instance exists on page.

### 3.2 Frontend widget behavior

From `includes/shortcode.php` and `assets/js/aichat-frontend.js`:

- Configurable title, role text, placeholder, send label, dimensions.
- Avatar and icon support.
- Minimize/super-minimize/close behavior.
- Suggestions UI chips with per-bot styling and count.
- Conversation clear/reset UX.
- Optional history persistence toggle per bot.

### 3.3 Session and state model

- Session ID cookie: `aichat_sid` (30-day persistence behavior in JS).
- GDPR cookie: `aichat_gdpr_ok`.
- Widget visual state persisted in `sessionStorage` (per bot key).

### 3.4 Voice and consent UX

- Voice input via `SpeechRecognition` / `webkitSpeechRecognition`.
- GDPR consent gate displayed in chat stream when enabled.

### 3.5 AJAX chat pipeline

Core endpoints:

- `aichat_process_message` (`priv` + `nopriv`)
- `aichat_get_history` (`priv` + `nopriv`)

Runtime checks:

- nonce verification
- optional captcha hook (`aichat_validate_captcha`)
- honeypot rejection
- rate limiting + cooldown
- spam signature checks
- moderation checks

### 3.6 Tool-calling continuation flow

`class-aichat-ajax.php` supports multi-round tool workflows:

- first response may return `status = tool_pending`
- frontend runs continuation call with tool outputs
- supports OpenAI/Claude/Gemini continuation paths
- tool state storage uses `wp_aichat_tool_states` (not transients) to avoid cache race issues

### 3.7 Markdown/rendering pipeline

- Server-side markdown-to-HTML conversion (`Parsedown` when available, fallback parser otherwise).
- Supports headings, emphasis, inline/fenced code, lists, links.
- Final output sanitized with allowlisted HTML (`wp_kses`/`wp_kses_post`).
- Frontend applies URL linkification and adjacent-link deduping.

### 3.8 External embed loader

`assets/js/aichat-embed-loader.js`:

- Fetches nonce/UI config via `/?aichat_embed_nonce=1`.
- Supports embedding selected bot slugs (`bots` query support).
- Uses Shadow DOM when available, with fallback handling.
- Applies optional branding metadata from endpoint response.

---

## 4) Knowledge Base and RAG Features

### 4.1 Context and retrieval model

Implemented mainly in `includes/contexto-functions.php` and context AJAX modules:

- Context entities in `wp_aichat_contexts`.
- Retrieval modes include local embeddings and remote Pinecone-style flow.
- Query-time context assembly for prompt building (`aichat_get_context_for_question`).

### 4.2 Content ingestion sources

Code supports ingestion from:

- WordPress posts/pages
- WooCommerce products
- uploaded PDF/TXT files
- web pages via Web Scraper add-on

### 4.3 Index text construction options

Per-context indexing options include:

- excerpt inclusion
- URL inclusion
- featured image URL inclusion
- selected taxonomies
- WooCommerce static fields (short description, attributes)
- custom meta keys

### 4.4 Chunking and embeddings

- Chunk defaults: approximately 1000 words with 180 overlap.
- Chunks stored in `wp_aichat_chunks`.
- Multi-chunk indexing with unique `(post_id, id_context, chunk_index)`.

### 4.5 WooCommerce dynamic enrichment at query time

For product results, runtime context can append:

- live price
- regular/sale signals
- SKU
- stock status

### 4.6 Background processing and autosync

`includes/aichat-cron.php`:

- cursor-based batch embedding processor
- lock handling for concurrent safety
- context progress updates and recovery/rescue cron
- autosync of modified/new posts for opted-in contexts
- orphan chunk cleanup

### 4.7 PDF extraction pipeline

`includes/contexto-pdf-ajax.php` + `includes/pdf-ai-vision.php`:

- uploads registered under `aichat_upload`
- chunk posts under `aichat_upload_chunk`
- local text extraction path + AI vision/OCR fallback (Imagick-based)

### 4.8 Web Scraper add-on

From `includes/add-ons/web-scraper/*`:

- URL fetch and crawl endpoints.
- BFS crawler with depth/page limits.
- URL normalization and non-content path/extension filtering.
- DOM/XPath extraction with noise stripping and fallback extraction strategies.
- scraped pages imported through same upload/chunk pipeline.

---

## 5) AI Tools and Macro Platform

### 5.1 Tool registry and execution

`includes/add-ons/ai-tools/api.php` and tool execution trait:

- safe tool registration with schema normalization (`aichat_register_tool_safe`)
- registry retrieval (`aichat_get_registered_tools`)
- capability settings per bot
- execution plumbing and tool call trace persistence

### 5.2 Macro system

`includes/add-ons/ai-tools/macro-api.php`:

- macro definitions persisted in `wp_aichat_macros`
- source/source_ref metadata support
- macro expansion into atomic tools
- source-based macro deletion (important for MCP cleanup)

### 5.3 Provider-native web search injection

When `web_search` macro is selected:

- OpenAI: injects native `web_search` tool
- Claude: injects `web_search_20250305` with max-use control
- Gemini: injects `google_search` only when REST limitations permit

Important Gemini limitation handled in code:

- if function declarations are already present, `google_search` injection is skipped for REST API compatibility.

### 5.4 Built-in sample tools/macros

`includes/add-ons/ai-tools/tools-sample.php` registers local examples:

- `web_search` macro scaffold
- admin email tool
- client email tool with authorization and rate limiting controls
- local tool-to-DB sync routine

### 5.5 Admin tools interface

`includes/add-ons/ai-tools/admin-*.php`:

- bot capability selection UI
- rule storage/load
- capability config save/load
- dynamic tool test runner
- tool execution logs screen

---

## 6) Business Add-ons

### 6.1 Leads Capture

`includes/add-ons/leads/*`:

- destination adapters:
  - internal table (`wp_aichat_leads`)
  - CPT destination (`aichat_lead`)
  - Contact Form 7
  - WPForms
  - Google Sheets (OAuth + API client)
- admin list/settings/export/status operations
- AI tool integration for lead save flows
- retention cleanup handler (`cleanup_old_leads`)

### 6.2 Appointments

`includes/add-ons/appointments/*`:

- adapters:
  - internal scheduler
  - Google Calendar
  - Bookly
  - Amelia
  - SSA
- AI tooling for availability, booking, cancellation
- data tables:
  - `wp_aichat_appointments`
  - `wp_aichat_appointment_slots`
- reminder cron (`aichat_appointments_send_reminders`)
- admin list/calendar/settings/help flows

### 6.3 MCP (Model Context Protocol)

`includes/add-ons/mcp/*`:

- HTTP transport stack and client manager
- lazy server initialization model
- server config CRUD in admin
- tools discovery, testing, execution
- sync MCP tools into `wp_aichat_tools`
- register MCP tool proxies and server-level macros
- auth modes: none, bearer, API key header, custom headers
- plan-based server count limit

STDIO status:

- `class-stdio-transport.php` is intentionally non-functional and documents removal of STDIO in favor of hardened HTTP-only architecture.

### 6.4 Custom Webhooks

`includes/add-ons/custom-webhooks/*`:

- webhook definitions table: `wp_aichat_custom_webhooks`
- per-webhook config:
  - slug/label/AI description/activity label
  - URL/method/headers
  - dynamic schema + static params
  - optional history inclusion
  - success/error templates
- runtime executor with placeholder replacement
- SSRF-oriented host blocking (`localhost`, private ranges)
- each webhook can be exposed as tool + macro

### 6.5 LiveChat (human handoff)

`includes/add-ons/livechat/*`:

- session/message tables:
  - `wp_aichat_livechat_sessions`
  - `wp_aichat_livechat_messages`
- session statuses:
  - `bot`
  - `pending_agent`
  - `agent_connected`
  - `closed`
- handoff flow from bot to agent, plus return/close lifecycle
- admin dashboard for agent queue and messaging
- frontend polling and visitor messaging endpoints
- integrations:
  - Slack interactive/events routes
  - Telegram webhook and callback handling
  - generic webhook route
- Professional-plan gating in loader

### 6.6 Andromeda Connect installer helper

`includes/addon-connect-installer.php` provides installer guidance for companion channel plugin workflows (WhatsApp/Telegram bridge context).

---

## 7) Usage Analytics, Logs, and Diagnostics

### 7.1 Usage and cost

Modules:

- `includes/usage.php`
- `includes/usage-functions.php`
- `includes/usage-ajax.php`

Capabilities:

- KPI summary (today/7d/30d)
- usage timeseries payloads for charts
- top-model cost table
- last conversations list
- pricing tables tab derived from model registry pricing

### 7.2 Conversation logs

Modules:

- `includes/logs.php`
- `includes/logs-detail.php`

Capabilities:

- session list filters and pagination
- transcript drill-down
- conversation deletion UI/actions

### 7.3 Debug logs and diagnostics export

From `axiachat-ai.php` and settings assets:

- file-based logs in uploads directory (`axiachat-ai/`)
- AJAX endpoints for tail/download/clear
- diagnostics ZIP export endpoint with environment + log details

---

## 8) Admin Surface and Settings UX

### 8.1 Main menu and major pages

Top-level: `AxiaChat AI` (`aichat-settings`)

Submenus include:

- Settings
- Bots
- Training
- Setup Wizard
- Logs
- Usage / Cost
- AI Tools (when enabled)
- Leads
- Appointments
- LiveChat (via add-on)
- MCP Servers (via add-on)

Hidden/internal pages include:

- training instructions/context pages
- context create/modify/settings/import pages
- logs detail
- AI tools logs
- add-on installer pages

### 8.2 Settings tabs and controls

Primary tab groups:

- General
- Usage and Moderation
- GDPR
- Add-ons
- Advanced

Examples of implemented controls:

- global bot enable/slug
- API keys (encrypted at rest)
- usage limits and behaviors
- moderation settings
- GDPR consent text/button
- debug/log controls
- security policy prompt prefix
- embed origin allowlist

### 8.3 Easy Config wizard

Wizard AJAX supports:

- setup data retrieval
- prompt preview
- content discovery
- context create/index operations
- file upload
- API key save
- bot save and global-bot save

---

## 9) Technical Inventory

### 9.1 Core tables provisioned by core plugin

Created/migrated from `axiachat-ai.php`:

- `wp_aichat_contexts`
- `wp_aichat_conversations`
- `wp_aichat_chunks`
- `wp_aichat_usage_daily`
- `wp_aichat_tool_states`
- `wp_aichat_bots`
- `wp_aichat_tool_calls`
- `wp_aichat_macros`
- `wp_aichat_tools`

### 9.2 Add-on tables

- Leads: `wp_aichat_leads`
- Appointments: `wp_aichat_appointments`, `wp_aichat_appointment_slots`
- LiveChat: `wp_aichat_livechat_sessions`, `wp_aichat_livechat_messages`
- Custom Webhooks: `wp_aichat_custom_webhooks`

### 9.3 Referenced-but-not-provisioned table

- `wp_aichat_webhook_logs` is referenced by custom webhook executor logging, but no creation routine is present in core or custom-webhooks loader (verified mismatch; see section 13).

### 9.4 Custom post types

- `aichat_upload`
- `aichat_upload_chunk`
- `aichat_lead` (via Leads CPT adapter)

### 9.5 REST endpoints

From `includes/add-ons/livechat/rest-api.php`:

- `POST /aichat/v1/livechat/slack`
- `POST /aichat/v1/livechat/slack/events`
- `POST /aichat/v1/livechat/telegram`
- `POST /aichat/v1/livechat/webhook`

### 9.6 AJAX endpoint surface (grouped)

#### Core chat and bots

- `aichat_process_message` (`priv` + `nopriv`)
- `aichat_get_history` (`priv` + `nopriv`)
- `aichat_bots_list`
- `aichat_bot_create`
- `aichat_bot_update`
- `aichat_bot_duplicate`
- `aichat_bot_reset`
- `aichat_bot_delete`

#### Training/context/PDF/easy-config

- `aichat_training_save_instructions`
- `aichat_training_save_context_sources`
- `aichat_training_set_bot_context`
- `aichat_training_save_advanced`
- `aichat_load_contexts`
- `aichat_update_context_name`
- `aichat_delete_context`
- `aichat_update_progress`
- `aichat_search_context_chunks`
- `aichat_get_context_meta`
- `aichat_autosync_run_now`
- `aichat_browse_context_chunks`
- `aichat_get_indexing_options`
- `aichat_save_indexing_options`
- `aichat_modify_list_documents`
- `aichat_modify_remove_documents`
- `aichat_modify_add_documents`
- `aichat_modify_load_items`
- `aichat_process_context`
- `aichat_load_items`
- `aichat_upload_file`
- `aichat_list_uploads`
- `aichat_parse_upload`
- `aichat_get_chunks_for_upload`
- `aichat_delete_upload`
- `aichat_easycfg_get_data`
- `aichat_easycfg_preview_prompt`
- `aichat_easycfg_discover`
- `aichat_easycfg_create_context`
- `aichat_easycfg_index_batch`
- `aichat_easycfg_index_text`
- `aichat_easycfg_upload_file`
- `aichat_easycfg_save_api_key`
- `aichat_easycfg_status`
- `aichat_easycfg_save_bot`
- `aichat_easycfg_save_global_bot`

#### Usage and logs

- `aichat_get_usage_summary`
- `aichat_get_usage_timeseries`
- `aichat_get_last_conversations`
- `aichat_get_log_tail`
- `aichat_download_log`
- `aichat_clear_log`
- `aichat_export_diagnostics`

#### AI Tools

- `aichat_tools_get_rules`
- `aichat_tools_save_rules`
- `aichat_tools_get_bot_tools`
- `aichat_tools_save_bot_tools`
- `aichat_tools_get_capability_settings`
- `aichat_tools_save_capability_settings`
- `aichat_tools_list_all_tools`
- `aichat_tools_run_tool`

#### MCP

- `aichat_mcp_get_server`
- `aichat_mcp_save_server`
- `aichat_mcp_delete_server`
- `aichat_mcp_toggle_server`
- `aichat_mcp_test_server`
- `aichat_mcp_list_servers_for_test`
- `aichat_mcp_list_tools`
- `aichat_mcp_run_tool`
- `aichat_mcp_get_server_tools_status`
- `aichat_mcp_save_tools_status`

#### Web Scraper

- `aichat_webscraper_fetch_urls`
- `aichat_webscraper_crawl_site`

#### Leads

- `aichat_leads_save_settings`
- `aichat_leads_save_email_template`
- `aichat_leads_get_lead`
- `aichat_leads_update_status`
- `aichat_leads_delete`
- `aichat_leads_bulk_delete`
- `aichat_leads_export`
- `aichat_leads_get_cf7_fields`
- `aichat_leads_get_wpforms_fields`
- `aichat_leads_gsheets_disconnect`
- `aichat_leads_gsheets_test`
- `aichat_leads_gsheets_save`

#### Appointments

- `aichat_appointments_save_settings`
- `aichat_appointments_gcal_disconnect`
- `aichat_appointments_get`
- `aichat_appointments_update_status`
- `aichat_appointments_cancel`
- `aichat_appointments_update`
- `aichat_appointments_calendar`

#### Custom Webhooks

- `aichat_custom_webhooks_list`
- `aichat_custom_webhooks_get`
- `aichat_custom_webhooks_save`
- `aichat_custom_webhooks_delete`
- `aichat_custom_webhooks_test`
- `aichat_custom_webhooks_toggle`

#### LiveChat

- `aichat_livechat_poll` (`priv` + `nopriv`)
- `aichat_livechat_visitor_message` (`priv` + `nopriv`)
- `aichat_livechat_get_sessions`
- `aichat_livechat_take_session`
- `aichat_livechat_send_message`
- `aichat_livechat_get_messages`
- `aichat_livechat_close_session`
- `aichat_livechat_return_to_bot`
- `aichat_livechat_toggle_availability`
- `aichat_livechat_create_test`
- `aichat_livechat_save_settings`
- `aichat_livechat_save_email_template`
- `aichat_livechat_telegram_set_webhook`
- `aichat_livechat_telegram_test`

### 9.7 Cron schedules and jobs

From `includes/aichat-cron.php` and add-ons:

- custom schedule: `oneminute`
- custom schedule: `aichat_hourly`
- recurring hook: `aichat_cron_process_contexts` (every minute)
- single-event worker hook: `aichat_process_embeddings_batch`
- recurring hook: `aichat_autosync_hourly`
- recurring hook: `aichat_cleanup_tool_states` (hourly)
- appointments recurring hook: `aichat_appointments_send_reminders` (hourly)

---

## 10) Option and State Surfaces

### 10.1 Key option groups

- Provider/API keys and model settings.
- Global widget settings and per-bot defaults.
- Usage limits and moderation configuration.
- GDPR and consent text.
- Add-on enablement flags.
- Branding/white-label related fields.
- Embed origin allowlist.
- Security policy/system prompt policy string.

### 10.2 Runtime/session state stores

- Cookies:
  - `aichat_sid`
  - `aichat_gdpr_ok`
- `sessionStorage`:
  - widget visual state keys per bot
- Transients/options:
  - rate-limit and adaptive blocking markers
  - processing locks/cursors/progress for context batching
  - setup/test-page helper flags

---

## 11) Security, Privacy, and Governance Controls

- Nonce verification for AJAX and admin actions.
- Capability checks (`manage_options`) on admin-sensitive endpoints.
- API keys encrypted at rest in options (AES-256-GCM envelope).
- Moderation controls:
  - banned words (custom + default lists)
  - blocked IP/CIDR matching
  - optional OpenAI moderation endpoint
- Anti-abuse controls:
  - honeypot
  - request cooldown/window rate limiting
  - duplicate message and spam-signature checks
  - adaptive temporary IP blocking on repeated moderation failures
- Embed origin allowlist and CORS enforcement for cross-origin AJAX/embed nonce endpoint.
- Custom webhook SSRF-oriented host filtering (localhost/private ranges blocked).
- Prompt-level security policy injection support.
- Safe HTML output sanitation after markdown processing.

---

## 12) Extensibility Surface (selected)

Representative hooks/filters used across the plugin:

- Chat orchestration:
  - `aichat_pre_process_message`
  - `aichat_provider_override`
  - `aichat_messages_before_provider`
  - `aichat_maybe_intercept_request`
  - `aichat_response_pre_persist`
  - `aichat_response_data`
- RAG/context:
  - `aichat_generate_embedding_override`
  - `aichat_embedding_provider`
  - `aichat_system_prompt`
  - `aichat_security_policy`
  - `aichat_index_text_parts`
- Tools/provider integration:
  - `aichat_openai_responses_tools`
  - `aichat_claude_messages_tools`
  - `aichat_gemini_tools`
  - `aichat_tool_execute`
- Other extension points:
  - `aichat_widget_branding`
  - `aichat_model_registry`

---

## 13) Known Implementation Inconsistencies (Important)

Code-verified mismatches and risks:

1. **Daily total behavior value mismatch (`hide` vs `hidden`)**
   - UI + sanitizer store/expect `hide`.
   - Chat runtime check compares against `hidden`.
   - Impact: "hide widget completely" behavior does not trigger as intended.

2. **LiveChat frontend-localize filter is never applied**
   - LiveChat registers `add_filter('aichat_frontend_localize_data', ...)`.
   - Core localization in `AIChat_Core` does not call `apply_filters('aichat_frontend_localize_data', ...)`.
   - Impact: LiveChat extra frontend data path is effectively dead.

3. **Leads cleanup hook is unscheduled**
   - Leads manager attaches cleanup to `aichat_daily_cleanup`.
   - No schedule registration or `wp_schedule_event(..., 'aichat_daily_cleanup')` exists.
   - Impact: automated leads cleanup never runs unless externally triggered.

4. **Webhook logs table is referenced but not created**
   - Custom webhook executor writes to `wp_aichat_webhook_logs` when present.
   - No creation routine for this table is present in core or custom-webhooks loader.
   - Impact: webhook execution logging is non-functional by default.

5. **MCP STDIO transport remains as stub class only**
   - `class-stdio-transport.php` exists but intentionally always fails and states support removal.
   - Impact: any expectation of live STDIO transport support is incorrect; HTTP-only is the real supported architecture.

---

## 14) Key High-Value Files

- `axiachat-ai/axiachat-ai.php` - bootstrap, lifecycle, core tables, admin menus, migrations, debug endpoints.
- `includes/class-aichat-ajax.php` - core chat runtime, moderation/rate-limit gates, provider/tool orchestration.
- `includes/contexto-functions.php` - chunking, embedding generation, retrieval, prompt context builders.
- `includes/model-registry.php` - central model metadata and pricing registry.
- `includes/settings.php` - option registration/sanitization and encrypted key handling.
- `includes/aichat-cron.php` - background indexing/autosync and tool-state cleanup jobs.
- `includes/add-ons/ai-tools/api.php` - registry API and provider-native search tool injection.
- `includes/add-ons/mcp/class-mcp-client-manager.php` - MCP connection and tool execution manager.
- `includes/add-ons/livechat/class-livechat-manager.php` - handoff session/message lifecycle.
- `includes/add-ons/custom-webhooks/class-webhook-executor.php` - webhook execution and logging path.

---

## 15) Practical Coverage Summary

From code, `axiachat-ai/` currently provides:

- Multi-provider chatbot runtime (OpenAI/Claude/Gemini).
- Multi-bot admin management with per-bot UI and behavior settings.
- RAG pipeline with chunking, embeddings, and context retrieval.
- Training ingestion from WP content, uploads, and scraped web pages.
- Tool/macro execution platform with provider-native web search injection.
- Business add-ons for leads, appointments, MCP, custom webhooks, and live handoff.
- Usage/cost analytics, conversation logs, and diagnostics export.
- Security/compliance controls (encryption, moderation, abuse throttling, origin restrictions, consent gating).
- License-aware feature gating across premium capability surfaces.
# AxiaChat AI — Comprehensive Feature Map

Generated from code-level inspection of `axiachat-ai/` in this workspace.

This map is organized by capability area, then by implementation modules/files.

---

## Scope

- Plugin folder analyzed: `axiachat-ai/`
- Primary sources:
  - Core PHP modules under `axiachat-ai/includes/`
  - Add-ons under `axiachat-ai/includes/add-ons/`
  - Main bootstrap `axiachat-ai/axiachat-ai.php`
  - Admin/frontend assets under `axiachat-ai/assets/`
  - Product docs in `axiachat-ai/readme.txt`
- Goal: enumerate implemented features (not marketing-only claims), including runtime behavior, admin UX, integrations, security/compliance controls, and data model.

---

## 1) Core Platform Architecture

### 1.1 Plugin bootstrap and lifecycle

Implemented in `axiachat-ai/axiachat-ai.php`:

- Defines plugin constants (`AICHAT_VERSION`, paths/URLs, debug flags).
- Loads dependencies (Composer/vendor, Parsedown, provider interfaces, provider classes, model registry).
- Registers activation/deactivation/uninstall hooks.
- Creates and migrates plugin tables (core + tooling + bot config).
- Registers providers in a central registry (OpenAI, Claude, Gemini).
- Loads major modules:
  - Chat runtime
  - Settings
  - Bots
  - Training/context
  - Usage/logging
  - Optional add-ons (AI Tools, MCP, Leads, Appointments, LiveChat, Web Scraper, custom webhooks, etc.).
- Handles first-run behavior:
  - Easy Config post-activation flow
  - test/preview page creation
- Adds admin menus/submenus and hidden detail pages.

### 1.2 Provider abstraction layer

Implemented across:
- `includes/interfaces/interface-aichat-provider.php`
- `includes/class-aichat-provider-registry.php`
- `includes/providers/class-openai-provider.php`
- `includes/providers/class-claude-provider.php`
- `includes/providers/class-gemini-provider.php`
- `includes/model-registry.php`

Features:

- Provider adapter pattern with a shared interface.
- Central provider registry (singleton) to register/retrieve active providers.
- Canonical model metadata registry:
  - model IDs
  - aliases
  - context windows
  - pricing metadata
  - fallback order metadata
- Provider-specific handling:
  - OpenAI: Chat Completions and Responses API routing (including GPT-5 style models)
  - Claude: Messages API + fallback chain behavior
  - Gemini: native message/parts format, safety/thinking controls, retry quirks handling.

### 1.3 Chat request orchestration pipeline

Implemented in `includes/class-aichat-ajax.php`:

- Frontend message intake via AJAX.
- Validation/sanitization + nonce checks.
- Anti-abuse gating:
  - honeypot
  - rate limits
  - spam signature checks
  - moderation checks
- Bot resolution (global bot or per-shortcode/per-request bot).
- Optional context retrieval (RAG) and prompt assembly.
- Provider routing + model selection.
- Tool-call continuation flow support (including multi-round patterns).
- Response post-processing:
  - markdown-safe rendering
  - suggestion chips extraction
  - structured response payload for UI.
- Optional conversation persistence and usage accounting.

---

## 2) Chatbot UX & Deployment

### 2.1 Frontend widget and inline embedding

Implemented in:
- `includes/class-aichat-core.php`
- `includes/shortcode.php`
- `assets/js/aichat-frontend.js`
- `assets/css/aichat-frontend.css`

Features:

- Floating global widget mode.
- Inline bot embedding via shortcode (`[aichat ...]`).
- Multiple bot instances/selectable bot by slug.
- Window behavior controls (open/minimized/close patterns).
- Per-bot history persistence behavior (restore/no-restore on refresh based on bot setting).
- Clear-conversation UX in widget flows.
- Responsive behavior for desktop/mobile.
- UI customization through bot/global settings:
  - colors
  - avatars
  - positioning
  - chip/suggestion styles
  - branding footer controls (plan-gated).

### 2.2 External website embedding (JS loader)

Implemented in:
- `assets/js/aichat-embed-loader.js`
- settings under `includes/settings.php` + `includes/settings/tab-general.php`
- origin controls in bootstrap helpers

Features:

- Script-based embedding outside WordPress pages.
- Origin allowlist (`aichat_embed_allowed_origins`) with sanitization.
- Embed nonce endpoint integration.
- Security behavior: disallow embed requests from non-allowlisted origins.

### 2.3 Test/preview workflow

Implemented via:
- bootstrap helper routines
- admin pages (`Bots`, preview pages, test page links)

Features:

- Hidden test page creation.
- Bot preview/testing before public deployment.
- Preview mode integration from bot management UI.

---

## 3) Bot Management & Prompting

### 3.1 Multi-bot CRUD

Implemented in:
- `includes/bots.php`
- `includes/bots_ajax.php`

Features:

- Create/edit/delete bots.
- Duplicate bot.
- Reset bot to defaults.
- Protect default/last bot from unsafe deletion scenarios.
- Per-bot configuration:
  - provider/model
  - temperature/behavior settings
  - UI appearance options
  - tools/macros selections (`tools_json`)
  - context linkage and mode controls.
- Plan-aware limits on number of bots.

### 3.2 Instructions editor (training prompt)

Implemented in:
- `includes/training.php`
- `includes/training-instructions.php`
- `includes/training-ajax.php`
- `includes/templates-prompt.php`

Features:

- Manual instruction editing.
- Guided prompt building flow (tone/type/length style inputs).
- Prompt templates and instruction helper UX.
- Save/load instructions per bot.

### 3.3 Easy Config Wizard

Implemented in:
- `includes/easy-config.php`
- `includes/easy-config-data.php`
- `assets/js/easy-config.js`

Features:

- First-run guided setup.
- Site content discovery.
- API key capture/status checks.
- Prompt preview generation.
- Context creation/reuse and indexing kickoff.
- Bot creation/update and linkage in one flow.

---

## 4) Knowledge Base / RAG System

### 4.1 Context model and retrieval logic

Implemented in:
- `includes/contexto-functions.php`
- `includes/contexto-settings.php`
- `includes/contexto-create.php`
- `includes/contexto-modify.php`
- `includes/contexto-ajax-*.php`

Features:

- Context entities with configurable retrieval modes (embeddings/page-specific/none).
- Storage strategies including local vectors and optional remote Pinecone-backed contexts.
- Chunking of source content.
- Embedding generation.
- Similarity search over chunks.
- Context enrichment for query-time answer generation.
- Advanced indexing controls (excerpt, URL, featured image, taxonomies, selected custom meta keys).
- WooCommerce-aware enrichment paths (e.g., dynamic commerce fields when enabled).
- Context management UI (create, update, inspect, search chunks, autosync operations).

### 4.2 Supported ingestion sources

Implemented across training/context modules:

- WordPress posts/pages.
- WooCommerce products (with dedicated enrichment pathways).
- PDF documents.
- TXT/plain text uploads.
- Manual text input/notes during context setup flows.
- External URLs/web pages (via Web Scraper add-on).

### 4.3 PDF extraction pipeline (including OCR fallback)

Implemented in:
- `includes/contexto-pdf-ajax.php`
- `includes/pdf-ai-vision.php`
- `includes/contexto-pdf-template.php`

Features:

- Upload and process PDF/TXT files.
- Extraction fallbacks:
  - local extraction tools/parsers
  - AI-vision/OCR fallback for scanned PDFs.
- Creates `aichat_upload` + `aichat_upload_chunk` records for RAG indexing.

### 4.4 Web Scraper add-on (external pages -> context)

Implemented in:
- `includes/add-ons/web-scraper/loader.php`
- `includes/add-ons/web-scraper/scraper-core.php`
- `includes/add-ons/web-scraper/scraper-crawler.php`
- `includes/add-ons/web-scraper/admin-ajax.php`

Features:

- URL list fetch/import.
- Content extraction engine with:
  - DOM/XPath parsing
  - anti-blocking request headers/UA strategy
  - aggressive noise stripping
  - multi-strategy article detection fallback chain
  - excerpt generation and warnings for short/no content.
- Domain-bounded crawler/spider:
  - BFS crawl
  - depth/page limits
  - extension/path skips for non-content endpoints
  - URL normalization and deduping.
- Imported pages are transformed into the same upload/chunk pipeline used by PDF/TXT ingestion.

---

## 5) AI Tools Platform (Capabilities/Macros)

### 5.1 Tool registry API

Implemented in:
- `includes/add-ons/ai-tools/api.php`
- `includes/traits/trait-aichat-tool-execution.php`

Features:

- Register function/custom tools with schema validation and normalization.
- Store/retrieve registered tool definitions globally.
- Capability settings per bot (e.g., domain constraints for web search).
- Tool execution lifecycle helpers + persistence of tool call logs.

### 5.2 Macro system

Implemented in:
- `includes/add-ons/ai-tools/macro-api.php`

Features:

- Macro definition (`name`, `label`, `description`, tool list).
- Macro persistence in DB table.
- Expansion of selected macros to atomic tools.
- Source/source_ref support for dynamic providers (e.g., MCP-generated macros).
- Macro cleanup helpers for source removal workflows.

### 5.3 Provider-native web search capability injection

Implemented in `includes/add-ons/ai-tools/api.php` and sample registrations:

- OpenAI native web search injection when macro enabled.
- Claude native web search injection (`web_search_20250305`) with optional allowed domains + max uses.
- Gemini `google_search` injection with REST API limitation guard (avoids unsupported multi-tool combinations when function declarations are present).
- Optional system prompt policy hint injection for allowed domain behavior.

### 5.4 Admin tools management UI

Implemented in:
- `includes/add-ons/ai-tools/admin-settings.php`
- `includes/add-ons/ai-tools/admin-ajax.php`
- `includes/add-ons/ai-tools/admin-logs.php`
- `assets/js/tools.js`

Features:

- Per-bot capability selection UI.
- Tool rules map storage per bot.
- Per-capability settings save/load endpoints.
- Test Tools runner:
  - list tools
  - dynamic schema form
  - execute tool and view result.
- Tool execution logs table view.

### 5.5 Built-in sample tools/macros

Implemented in `includes/add-ons/ai-tools/tools-sample.php`:

- Web search macro scaffold (`web_search`).
- Admin notification email tool.
- Client email tool with default-deny authorization gate + rate limiting + safer from-domain handling.
- One-time local tools sync into unified tools table.

### 5.6 SSA-specific AI tools extension

Implemented in:
- `includes/add-ons/ai-tools/simply-schedule-appointments/*`

Features:

- Service listing.
- Availability lookup.
- Appointment creation/confirmation paths against Simply Schedule Appointments internals.
- Macros for SSA booking/availability workflows.

---

## 6) Add-ons: Business Automation Modules

### 6.1 Leads Capture add-on

Implemented in `includes/add-ons/leads/*`:

- Destination adapters:
  - internal DB (`wp_aichat_leads`)
  - WordPress CPT (`aichat_lead`)
  - Contact Form 7
  - WPForms
  - Google Sheets (OAuth + API client).
- AI tool integration (`save_lead`) with destination-aware schemas/descriptions.
- Admin interface:
  - settings
  - lead list
  - status updates
  - bulk delete
  - CSV export
  - integration field mapping fetchers.
- Notifications + webhook support.
- Data retention cleanup.
- Plan-based limits and gating.

### 6.2 Appointments add-on

Implemented in `includes/add-ons/appointments/*`:

- Adapter architecture (`AIChat_Appointments_Adapter_Interface`) with pluggable destinations:
  - internal calendar
  - Google Calendar (OAuth + API client)
  - Bookly
  - Amelia
  - SSA.
- Tool integration:
  - availability check
  - booking
  - cancellation
  - service/staff discovery (for external systems).
- Admin pages:
  - list
  - calendar
  - settings
  - help
- Reminder cron + customizable email templates.
- Working hours, slot duration, advance windows, buffers.
- Optional webhook dispatch.
- Plan-based booking limits + integration gating.

### 6.3 MCP (Model Context Protocol) add-on

Implemented in `includes/add-ons/mcp/*`:

- HTTP-only MCP transport (STDIO intentionally removed/hardened).
- Transport abstraction + manager with lazy connection model.
- MCP handshake and `tools/list` discovery.
- Tool sync into unified `aichat_tools` table.
- Dynamic MCP tool registration into AI Tools registry (proxy callbacks).
- Macro auto-generation per MCP server (`mcp_<server>`).
- Auth modes for server connections:
  - none
  - bearer token
  - API key with custom header name
  - arbitrary custom headers JSON.
- Admin UI:
  - server CRUD
  - connection test
  - tools test execution
  - per-tool enable/disable.
- Plan-aware server count limits (free vs paid).

### 6.4 Custom Webhooks add-on

Implemented in `includes/add-ons/custom-webhooks/*`:

- Custom webhook definitions stored in dedicated table (`wp_aichat_custom_webhooks`).
- Visual admin builder for:
  - slug/label/AI description
  - HTTP URL/method
  - custom headers
  - dynamic parameter schema
  - static params with placeholder interpolation
  - optional conversation history inclusion
  - success/error user-facing message templates.
- Runtime executor:
  - payload composition
  - security blocks for localhost/private-network hosts
  - HTTP request execution
  - result normalization
  - optional execution logging.
- Auto-register each webhook as:
  - atomic tool (`webhook_<slug>`)
  - macro (`custom_webhook_<slug>`).
- Free-tier limit and paid unlimited gating.

### 6.5 Live Chat / Human Handoff add-on

Implemented in `includes/add-ons/livechat/*`:

- Session state model:
  - `bot`
  - `pending_agent`
  - `agent_connected`
  - `closed`.
- Data tables:
  - `wp_aichat_livechat_sessions`
  - `wp_aichat_livechat_messages`.
- Tool integration (`transfer_to_human`) and macro (`human_handoff`).
- Bot-blocking behavior during handoff states (visitor messages routed to livechat queue instead of AI provider).
- Agent dashboard in WP admin:
  - active/pending sessions
  - take session
  - send messages
  - return to bot
  - close session
  - real-time polling.
- Frontend polling endpoint for visitor-side updates.
- Auto-handoff keyword triggers and queueing behavior controls.
- Wait-time/timeout/offline/welcome message configuration.
- External channel integration:
  - Slack webhook + interactive actions + quick-reply modal
  - Slack events endpoint
  - Telegram webhook + callback button handling + reply routing.
- Additional notifications:
  - email notifications with customizable templates/placeholders
  - optional custom handoff webhook.
- Availability controls:
  - manual toggle
  - schedule-based working hours.
- Professional-plan license gating.

### 6.6 Andromeda Connect installer helper

Implemented in `includes/addon-connect-installer.php`:

- Guided installer page for companion plugin (`andromeda-connect`) to enable WhatsApp/Telegram channel bridges.
- Detects installed/active version and provides install/activate guidance links.

---

## 7) Usage, Cost Tracking, and Logs

### 7.1 Usage and cost analytics

Implemented in:
- `includes/usage.php`
- `includes/usage-functions.php`
- `includes/usage-ajax.php`
- `assets/js/usage.js`

Features:

- KPI and trend data for token/cost consumption.
- Daily aggregation table support.
- Last conversations view.
- Model pricing-aware cost calculation using model registry metadata.
- Time-series charting and model-level usage/cost inspection UX.

### 7.2 Conversation logs

Implemented in:
- `includes/logs.php`
- `includes/logs-detail.php`

Features:

- Session list, filtering, and drill-down transcript views.
- Conversation deletion handlers.
- Bot/session/user/date-level inspection workflows.

### 7.3 Debug logs and diagnostics export

Implemented via core bootstrap + settings advanced tab:

- Runtime logging toggles (`AICHAT_DEBUG` constant and option-based debug enablement).
- Log file tail/read/download/clear AJAX endpoints.
- Diagnostics ZIP export:
  - environment information
  - logs
  - troubleshooting bundle (excluding API secrets).

---

## 8) Security, Privacy, and Governance Features

Implemented across core settings/runtime:

- Nonce + capability checks on admin and frontend write operations.
- Prepared SQL usage patterns in internal queries.
- API key encryption at-rest:
  - AES-256-GCM payload format
  - decrypt-on-read helpers.
- Moderation layer:
  - banned words
  - blocked IPs/CIDR
  - optional OpenAI external moderation.
- Anti-abuse controls:
  - rate limiting
  - honeypot
  - spam signature checks.
- GDPR consent gate:
  - configurable text/button
  - optional enforcement before chat starts.
- Logging controls:
  - conversation logging on/off
  - IP-related behavior tied to usage limit paths.
- Security policy prompt prefix configuration and reset controls.
- Safe markdown rendering path with allowlist hardening.
- Local-bundled vendor UI assets (no runtime CDN dependency requirement).
- Data ownership pattern: request orchestration runs from the site server with owner-supplied API keys.
- Developer extension hooks and filters for custom integrations.

---

## 9) Licensing / Freemium Controls

Implemented in `includes/freemius-init.php` and add-on-specific gating:

- Freemius SDK initialization with fallback local license key format validation.
- Feature ID-based capability checks (bots, MCP/webhooks limits, integrations, livechat, white-label, etc.).
- Plan-level helpers (`starter`, `standard`, `professional`).
- Hard limits/entitlement checks applied in add-on UIs and save handlers:
  - max bots
  - max MCP servers
  - max custom webhooks
  - leads/appointments limits
  - integration and livechat access
  - JS embed and white-label related feature checks.

---

## 10) Scheduling / Background Jobs

Implemented in `includes/aichat-cron.php` and add-on managers:

- Custom schedules (`aichat_hourly`, `aichat_daily`).
- Context/embedding batch processing jobs.
- Autosync handlers.
- Tool-state cleanup job(s).
- Leads retention cleanup (daily).
- Appointment reminder dispatch job.

---

## 11) Data Model (Database + CPTs)

### 11.1 Core plugin tables (created/migrated by core)

- `wp_aichat_contexts`
- `wp_aichat_conversations`
- `wp_aichat_chunks`
- `wp_aichat_usage_daily`
- `wp_aichat_tool_states`
- `wp_aichat_bots`
- `wp_aichat_tool_calls`
- `wp_aichat_macros`
- `wp_aichat_tools`
- `wp_aichat_webhook_logs`

### 11.2 Add-on tables

- Leads:
  - `wp_aichat_leads`
- Appointments:
  - `wp_aichat_appointments`
  - `wp_aichat_appointment_slots`
- LiveChat:
  - `wp_aichat_livechat_sessions`
  - `wp_aichat_livechat_messages`
- Custom Webhooks:
  - `wp_aichat_custom_webhooks`

### 11.3 Custom post types

- `aichat_upload` (uploaded source containers for knowledge ingestion)
- `aichat_upload_chunk` (chunk records for source text segments)
- `aichat_lead` (optional lead destination as WP content object)

---

## 12) Admin Surface Map (Menus / Screens)

Main and hidden admin screens include:

- Settings (`aichat-settings`)
  - General
  - Usage & Moderation
  - GDPR
  - Add-ons & branding
  - Advanced/debug
- Bots (`aichat-bots-settings`)
- Training hub and subpages
  - instructions
  - context
- Easy Config Wizard
- Logs
- Usage / Cost
- AI Tools
  - bot skills
  - custom webhooks tab
  - test tools
  - tools logs
- Leads
- Appointments
- MCP Servers
- Live Chat dashboard/settings/help
- Andromeda Connect installer page.

---

## 13) Endpoint Surface Summary

### 13.1 AJAX patterns

The plugin exposes extensive `wp_ajax_*` (and selected `wp_ajax_nopriv_*`) endpoints for:

- chat processing
- settings save/load
- bot CRUD
- training/context ingest actions
- PDF/TXT ingestion
- web-scraper fetch/crawl
- usage analytics payloads
- logs operations (view/download/clear/export diagnostics)
- leads and appointments management
- AI tools management and tool testing
- MCP server/tool management
- livechat visitor and agent polling/message exchange.

### 13.2 REST endpoints

LiveChat defines REST webhooks under `aichat/v1`:

- `/livechat/slack`
- `/livechat/slack/events`
- `/livechat/telegram`
- `/livechat/webhook` (generic external inbound with header key check).

---

## 14) Key Module-to-Feature Mapping (High-Value Files)

- `axiachat-ai/axiachat-ai.php`
  - bootstrap, hooks, module loading, table creation/migrations, diagnostics/log operations.
- `includes/class-aichat-ajax.php`
  - core chat runtime, provider/tool orchestration, moderation/rate-limit gates.
- `includes/model-registry.php`
  - canonical provider/model metadata and pricing context.
- `includes/contexto-functions.php`
  - chunking, embeddings, retrieval logic.
- `includes/easy-config.php`
  - guided onboarding and first indexing workflows.
- `includes/add-ons/ai-tools/api.php`
  - tool registry API and provider-native web-search injection logic.
- `includes/add-ons/appointments/class-appointments-manager.php`
  - appointments core orchestration and notifications.
- `includes/add-ons/leads/class-leads-manager.php`
  - leads destination abstraction + retention/export/stats.
- `includes/add-ons/mcp/class-mcp-client-manager.php`
  - MCP transport/session/discovery/execution manager.
- `includes/add-ons/custom-webhooks/class-webhook-executor.php`
  - user-defined webhook execution engine with security checks.
- `includes/add-ons/livechat/class-livechat-manager.php`
  - human handoff session lifecycle and external notification hooks.
- `includes/add-ons/web-scraper/scraper-core.php`
  - robust external content extraction for RAG ingestion.

---

## 15) Notes on Readme-Documented vs Code-Verified Features

- The majority of major features advertised in `readme.txt` are traceable in code.
- A few statements in readme/changelog are roadmap/edition-contextual; implementation may depend on:
  - plan/license checks
  - enabled add-ons
  - third-party plugin presence (Bookly/Amelia/SSA/WPForms/CF7)
  - external API credentials and network accessibility.

---

## 16) Practical Feature Coverage Checklist

The plugin currently provides all of the following capability classes:

- Multi-provider AI chat runtime
- Multi-bot management
- RAG/context ingestion + retrieval
- File and web content training pipelines
- Tool/macro execution platform
- Business automation add-ons (leads/appointments/webhooks/MCP/livechat)
- Usage controls and analytics
- Security/privacy/compliance controls
- Freemium/plan-based feature gating
- Operational debugging and diagnostics export
- i18n/l10n-ready admin/frontend strings with translation support.

---

## 17) Suggested Next Mapping Passes (optional)

For even deeper documentation granularity, a follow-up map can add:

- full option key registry (all `get_option` / `update_option` keys by module)
- full endpoint catalog (exact action name -> file/function map)
- hook/filter catalog for developers
- schema-level data dictionary (columns/types/defaults for each table).

