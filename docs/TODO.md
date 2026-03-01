# Ultimate WP AI Chatbot — Master Worklist

> **Standalone tracking file.** This is the single source of truth for
> implementation progress. Update status checkboxes here as work is completed
> and record milestones in `docs/CHANGELOG.md`.
>
> **Origins:** Derived from the PRD and UX-PRD during the spec phase.
> Phase-02 architecture contracts informed the task breakdown.
> Reference plugins (`axiachat-ai/`, `mxchat-basic/`, `baachal/`) are read-only.

---

## Status Key

- [x] Complete
- [-] In Progress
- [ ] Planned / Not Started

---

## Phase 00 — Program Setup and Extraction Schema [COMPLETE]

- [x] Reference inventory baseline
- [x] Domain taxonomy and feature schema
- [x] Context pack and decision log templates
- [x] Phase readiness and handoff bundle

**Reference files**:
- `axiachat-ai/axiachat-ai.php`, `axiachat-ai/readme.txt`
- `mxchat-basic/mxchat-basic.php`, `mxchat-basic/readme.txt`
- `baachal/baachal.php`, `baachal/readme.txt`

---

## Phase 01 — Canonical Capability Matrix and Scoring [COMPLETE]

- [x] Scoring framework and provider domain
- [x] Runtime and migration domains
- [x] RAG and admin domains
- [x] Security, tools, and integrations domains
- [x] WooCommerce and cross-domain resolution
- [x] Winner consolidation and phase handoff

**Reference files**:
- `axiachat-ai/includes/model-registry.php`, `axiachat-ai/includes/class-aichat-ajax.php`
- `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php`
- `baachal/admin/tabs/general.php`, `baachal/admin/tabs/search.php`

---

## Phase 02 — Target Architecture and Module Contracts [COMPLETE]

- [x] Session 01: Module map, directory architecture, bootstrap
- [x] Session 02: Data layer schema design and migration framework
- [x] Session 03: Provider orchestration and model registry contracts
- [x] Session 04: Chat runtime, session model, and frontend contracts
- [x] Session 05: RAG pipeline, knowledge base, and embedding contracts
- [x] Session 06: Security, admin contracts, and observability
- [x] Session 07: Migration strategy, extension points, and phase handoff

**Key deliverables** (contracts for parallel Phase 03+ implementation):
- Module map with dependency graph
- DB table schemas (`wp_wpaic_sessions`, `wp_wpaic_messages`, etc.), migration runner
- Provider interface, registry, streaming transport layer, cost accounting
- Chat pipeline stages, endpoint specs, frontend state management, SSE delivery
- RAG retrieval interface, embedding contract, knowledge source adapters
- Security/audit contracts, admin surface contracts, observability hooks
- Migration paths from each source plugin, extension point registry

**Reference files**:
- `axiachat-ai/includes/interfaces/interface-aichat-provider.php`, `axiachat-ai/includes/class-aichat-provider-registry.php`
- `mxchat-basic/includes/class-mxchat-public.php`, `mxchat-basic/admin/class-ajax-handler.php`
- `baachal/includes/content-indexer.php`, `baachal/admin/settings-page.php`

---

## Phase 03 — Core Plugin Skeleton, Lifecycle, and Data Foundations [-]

> Implementation order: Bootstrap -> Lifecycle+DB -> Security -> Provider -> Runtime -> Admin

### Sessions 01-02: Bootstrap, Lifecycle, DB Schema [COMPLETE 2026-03-01]

- [x] Plugin bootstrap, constants, autoloading, hook registration
  - `wp-ai-chatbot.php` — 17 WPAIC_* constants, Phase A/B init, version guards, lifecycle hooks.
  - `includes/autoloader.php` — spl_autoload_register; WPAIC\ → includes/{domain}/{type}-*.php
  - `includes/class-plugin.php` — Plugin singleton; filterable hook array (Baachal pattern);
    WC detection + HPOS; cron schedule; cache exclusion; class_exists guards for Phase 05+ modules.
- [x] Activation / deactivation / uninstall lifecycle with data safety checks
  - `includes/migration/class-lifecycle-manager.php` — activate() (wp_die version guard,
    run_all_pending, seed defaults + default bot, schedule cron); deactivate() (unschedule);
    uninstall() (preserve flag, drop tables in dependency order, delete options/transients/user_meta).
  - `uninstall.php` — WP_UNINSTALL_PLUGIN guard + Lifecycle_Manager::uninstall().
- [x] Core DB schema creation and versioned migration runner
  - `includes/migration/class-migration-registry.php` — semver-ordered runner; transient lock;
    self-bootstrapping 0.0.1 (wp_wpaic_migrations); wpaic_register_migrations hook; schema validation.
  - `Lifecycle_Manager::register_core_migrations()` — 1.0.0 migrations: wpaic_sessions,
    wpaic_messages, wpaic_knowledge, wpaic_chunks, wpaic_queue, wpaic_usage_daily, wpaic_bots.
- [x] WP-Cron scheduling foundation
  - wpaic_cleanup_sessions (hourly) + wpaic_process_queue (every 5 min); custom interval registered.
- [x] Directory scaffold — all MVP + deferred stub dirs and .gitkeep files created.

### Sessions 03-05: Security, Provider, Admin [COMPLETE 2026-03-01]

- [x] Security foundation: class-encryption.php (AES-256-GCM), class-sanitizer.php (12 TYPE_*),
  class-auth-enforcer.php (nonce+capability), class-audit-logger.php
  - `includes/security/class-encryption.php` — AES-256-GCM with versioned base64 JSON envelope; key from WPAIC_MASTER_KEY or wp_salt('auth') fallback; graceful OpenSSL degradation with admin notice
  - `includes/security/class-sanitizer.php` — 12 TYPE_* constants (TYPE_MESSAGE, TYPE_SYSTEM_PROMPT, TYPE_API_KEY, TYPE_SLUG, TYPE_INT, TYPE_FLOAT, TYPE_BOOL, TYPE_URL, TYPE_HTML_RESTRICTED, TYPE_FILENAME, TYPE_IP, TYPE_JSON); sanitize_map(); wpaic_sanitizer_types filter for extensions
  - `includes/security/class-auth-enforcer.php` — check_ajax() + check_rest() dual-check; 12 ENDPOINT_* constants via nonce_action(); verify_session_ownership(); rate limiter before nonce on nopriv (ACR-01 through ACR-07)
  - `includes/security/class-audit-logger.php` — 15 EVENT_* constants; routes to lib/structured-logger.php (subsystem=audit); query() reads log files reversed for admin panel; no raw IPs/keys/nonces in log entries
- [x] Provider registry + OpenAI adapter
  - `includes/provider/interface-provider.php` — Provider_Interface (get_id, chat, calculate_cost)
  - `includes/provider/interface-streamable-provider.php` — Streamable_Provider_Interface extends Provider_Interface (stream_chat, supports_streaming)
  - `includes/provider/class-provider-registry.php` — singleton; hook-driven lazy init via wpaic_register_providers; alias map (anthropic→claude, google→gemini, xai→grok); config cache by ID+hash; CR-01 through CR-05 validation; get_stats() for health endpoint
  - `includes/provider/adapters/class-openai-adapter.php` — implements both interfaces; Chat Completions API; exponential backoff retry; cURL SSE streaming with per-chunk callback; calculate_cost() in microcents; 8-model pricing table; fallback to sync when cURL unavailable
- [x] Admin skeleton: Admin_Router, Settings_Manager, General page
  - `includes/admin/class-admin-page.php` — abstract Admin_Page base (get_slug, get_title, register_settings, render, get_tabs, enqueue_page_assets); render_page_header/footer/settings_form helpers
  - `includes/admin/class-admin-router.php` — singleton; register_menus() adds 7 submenu pages; enqueue_assets() per-page; dispatch_current_page() → dispatch(slug) → controller->render(); bootstrap_default_pages() lazy
  - `includes/admin/class-settings-manager.php` — singleton; transparent get/set with encrypt on write, decrypt on read; get_masked() for key display; sensitive_sanitize_callback() and sanitize_callback() builder methods
  - `includes/admin/pages/class-general-page.php` — slug=uwpaic; register_setting for provider_default, default_model, timeout, max_retries, 5 encrypted API key fields; renders provider select + masked API key inputs + model field

**Quality gates**: PHP-CS-Fixer ✓, PHPStan level 0 ✓, php -l ✓ (all 12 files)

**Notes for next session**:
- WPAIC_VERSION is now 1.0.0
- PHPStan runs at level 0 with --memory-limit=2G on includes/ (reference plugins cause OOM on full run with 4G)
- Admin router currently bootstraps only General_Page; Providers_Page and remaining pages are phase-gated stubs
- OpenAI adapter uses 1_000_000 integer literal (PHP 7.4+ feature) — requires PHP 7.4+ minimum which matches plugin header
- Audit_Logger::query() reads up to 7 days of daily log files to satisfy limit; newest entries first
- str_starts_with() replaced with strpos() === 0 in provider registry and OpenAI adapter for PHP 7.4 compatibility
- Numeric literal separators (1_000_000) are fine — supported since PHP 7.4

**Reference files**:
- `axiachat-ai/axiachat-ai.php` -- activation hooks, version check, dependency guard
- `axiachat-ai/includes/aichat-cron.php` -- cron scheduling pattern
- `mxchat-basic/mxchat-basic.php` -- bootstrap, capabilities, admin menu
- `mxchat-basic/includes/class-mxchat-utils.php` -- utility helpers
- `baachal/baachal.php` -- lean bootstrap, AJAX registration
- `baachal/uninstall.php` -- clean uninstall with option/table cleanup

**UX touchpoints** (from `PRD_UX.md`):
- Flow 6: Lifecycle change and recovery (preflight checks, risk summary, rollback)
- Lifecycle Status Card on Dashboard (upgrade warnings, validation checkpoints)

---

## Phase 04 — Provider Orchestration and Model Registry [COMPLETE 2026-03-01]

- [x] `Provider_Interface` + `Streamable_Provider_Interface` implementation (Phase 03)
- [x] Provider registry dispatcher (register, resolve, fallback) (Phase 03)
- [x] OpenAI adapter — `includes/provider/adapters/class-openai-adapter.php` (Phase 03)
- [x] Claude/Anthropic adapter — `includes/provider/adapters/class-claude-adapter.php`
- [x] Gemini adapter — `includes/provider/adapters/class-gemini-adapter.php`
- [x] Model registry with deprecation metadata and auto-migration — `includes/provider/class-model-registry.php`
- [x] Provider key validation REST endpoint — `POST /wp-ai-chatbot/v1/providers/validate` (in class-plugin.php)
- [x] Provider registration wired in class-plugin.php (`register_core_providers` hook on `wpaic_register_providers`)
- [x] Streaming transport layer (SSE protocol, normalized chunks) — implemented in both Claude and Gemini adapters
- [ ] Cost accounting hooks (wpaic_usage_recorded action) — deferred to Phase 05 runtime pipeline
- [ ] DeepSeek adapter (stub model registry entries added; full adapter deferred to next session)
- [ ] Grok/xAI adapter (stub model registry entries added; full adapter deferred to next session)

**Key deliverables (2026-03-01)**:
- Claude adapter: Anthropic Messages API, SSE streaming, content_block_delta parsing, tool_use blocks, model pricing
- Gemini adapter: Google Generative Language API, SSE streaming via :streamGenerateContent?alt=sse, thinkingConfig for 2.5/pro models, functionDeclarations tools format
- Model Registry singleton: 25+ models across 5 providers with pricing, context window, capabilities, deprecated flag + successor chain, filterable via `wpaic_model_registry_models`
- Provider validation endpoint: GET model listing probe (no tokens consumed) for openai/claude/gemini; admin-only permission

**Notes for next session**:
- PHPStan level 0 ✓, PHP-CS-Fixer ✓, php -l ✓ (all 4 files)
- Model registry defaults: openai→gpt-4o-mini, claude→claude-haiku-4-5-20251001, gemini→gemini-2.5-flash
- Claude streaming uses per-event SSE parsing: message_start (input tokens), content_block_delta (text), message_delta (stop_reason + output tokens)
- Gemini streaming uses buffer-based parsing of newline-delimited `data:` lines from :streamGenerateContent?alt=sse
- Gemini 2.5-pro models require thinkingConfig (cannot disable); flash/lite models disable thinking by default (budget=0)
- `wpaic_register_providers` hook fires lazily on first Provider_Registry access (not at init time)
- Provider validation uses token-free model listing endpoints: OpenAI GET /v1/models, Claude GET /v1/models, Gemini GET /v1beta/models?key=
- Cost accounting (`wpaic_usage_recorded` action) deferred to Phase 05 when Message_Pipeline dispatches providers
- DeepSeek and Grok have model registry entries but no adapter classes yet; full adapters are Phase 04 next-session or Phase 05 task

**Reference files**:
- `axiachat-ai/includes/class-aichat-provider-registry.php` -- registry + dispatcher
- `axiachat-ai/includes/providers/class-openai-provider.php` -- OpenAI adapter
- `axiachat-ai/includes/providers/class-claude-provider.php` -- Claude adapter, tool continuation
- `axiachat-ai/includes/providers/class-gemini-provider.php` -- Gemini adapter
- `mxchat-basic/includes/class-mxchat-integrator.php` -- broad provider coverage, model lists
- `mxchat-basic/includes/class-mxchat-admin.php` -- model config admin
- `baachal/baachal.php` -- simple provider dispatch
- `baachal/admin/tabs/general.php` -- provider key + model selection UI

**UX touchpoints** (from `PRD_UX.md`):
- Screen: Provider and Models (`admin.php?page=uwpaic-providers`)
- Provider Card component (expandable, credential status, test action)
- Provider selection conditionally reveals matching credentials/models (Baachal pattern)
- `baachal/assets/admin.js` -- conditional field reveal on provider change

---

## Phase 05 — Chat Runtime, Sessioning, UX, and Safety Controls

- [ ] Message lifecycle pipeline (pre-processing, dispatch, post-processing)
- [ ] Session model implementation (create, resume, close, expire, cleanup)
- [ ] AJAX endpoint: chat message send (`wp_ajax_nopriv_wpaic_send_message`)
- [ ] SSE endpoint: streaming response delivery
- [ ] REST endpoints: session CRUD, history retrieval
- [ ] Widget rendering (`[wpaic_chat]` shortcode, floating/inline modes)
- [ ] Vanilla JS frontend: IIFE module, instance registry, DOM state machine
- [ ] Input sanitization pipeline (PHP + JS)
- [ ] Output rendering (markdown-to-HTML, `wp_kses` allowlist, safe links)
- [ ] Anti-spam: honeypot, rate limiter integration, duplicate detection
- [ ] Moderation hook chain (pre-send content filtering)
- [ ] Page context injection (URL, title, content extraction)
- [ ] GDPR consent inline gating
- [ ] Tool continuation two-phase model (tool_pending -> continuation)
- [ ] Multi-instance support (multiple widgets per page)

**Reference files -- Backend pipeline**:
- `axiachat-ai/includes/class-aichat-ajax.php` -- full 7-stage pipeline: nonce, session, honeypot, rate limit, spam, moderation, dispatch, post-process
  - Lines 55-80: session validation, UUID generation
  - Lines 131-200: honeypot, captcha, rate limit, spam signature, moderation
  - Lines 287-326: context resolution with mode selection
  - Lines 328-376: message assembly with history
  - Lines 586-667: tool selection and registration
  - Lines 678-850: provider call and response handling
  - Lines 858-924: suggestions extraction, markdown render, HTML sanitization
- `mxchat-basic/includes/class-mxchat-public.php` -- shortcode, multi-bot AJAX setup
- `baachal/baachal.php` -- AJAX registration, hook-driven extensibility

**Reference files -- Frontend**:
- `axiachat-ai/assets/js/aichat-frontend.js` -- jQuery instance management, session cookie (30-day), voice I/O, tool pending handoff, GDPR consent, suggestions chips
- `axiachat-ai/includes/shortcode.php` -- shortcode attributes (bot, layout, position, title, etc.), 30+ data-attributes, honeypot field, GDPR localization
- `mxchat-basic/js/chat-script.js` -- instance registry (`MxChatInstances`), per-bot cookie sessions, page context extraction (`getPageContext()`), streaming detection, DOMParser HTML sanitization
  - Lines 7-79: instance registry pattern
  - Lines 47-56: cookie-based per-bot session management
  - Lines 208-311: page context extraction with CSS selector priority
  - Lines 284-399: DOMParser sanitization with tag/attribute allowlist
  - Lines 408-415: streaming detection and temporary message
- `baachal/assets/chatbot.js` -- toggle state, localStorage sessions, event system, `escapeHtml` fallback, DOMParser sanitization
  - Lines 2-20: state management, localStorage session persistence
  - Lines 94-211: event system
  - Lines 284-400: DOMParser HTML sanitization
  - Lines 441-468: history loading
- `baachal/templates/chatbot-widget.php` -- template structure, hook points, option-driven config

**UX touchpoints** (from `PRD_UX.md`):
- Flow 2: Visitor chat question to conversion action
- Screen: Frontend Launcher (fixed position, unread badge, status indicator)
- Screen: Frontend Chat Panel (header, transcript body, input composer, citations, CTA chips)
- Screen: Runtime and Safety (`admin.php?page=uwpaic-runtime`)
- Screen: Widget and Shortcode (`admin.php?page=uwpaic-widget`)
- Signature Moment: staged reveal (answer fade-in, source chips animate, 300-450ms)
- Streaming + fallback: auto-fallback to non-stream on failure, clear placeholders
- Consent Bubble: inline policy message, disable input until accepted
- Quota Banner: low/near-limit/reached states with progress indicator
- Suggestion chips with disabled/in-progress styling
- Chat error messages visually distinct from assistant responses
- Responsive: mobile full-height sheet, desktop 380-420px panel
- WCAG 2.2 AA: keyboard nav, screen reader landmarks, focus management
- `prefers-reduced-motion` support
- Touch targets: min 44x44px, 8px separation

---

## Phase 06 — Knowledge Base, RAG, and Content Ingestion

- [ ] Content source adapters (posts, pages, products, manual, web, PDF)
- [ ] Chunking engine (text splitting, overlap, metadata tagging)
- [ ] Embedding pipeline (provider-agnostic embedding interface)
- [ ] Indexing storage (local DB vectors or external vector store adapter)
- [ ] Queue workers for background ingestion with retry/backoff
- [ ] Retrieval ranking and context assembly for chat pipeline
- [ ] Knowledge admin: source list, config drawers, ingest action bar
- [ ] Inline test search console in admin
- [ ] Index stats and cache management

**Reference files**:
- `axiachat-ai/includes/contexto-functions.php` -- retrieval functions, context scoring
- `axiachat-ai/includes/contexto-create.php` -- content ingestion, chunking
- `axiachat-ai/includes/contexto-modify.php` -- index updates
- `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php` -- web content scraping
- `mxchat-basic/admin/class-knowledge-manager.php` -- knowledge admin, queue processing
- `mxchat-basic/includes/class-mxchat-chunker.php` -- text chunking strategies
- `mxchat-basic/js/knowledge-processing.js` -- client-side processing UI
- `baachal/includes/content-indexer.php` -- lightweight site index, exclusion controls
- `baachal/admin/tabs/content.php` -- content config, index stats
- `baachal/admin/tabs/search.php` -- search config, cache management

**UX touchpoints** (from `PRD_UX.md`):
- Flow 3: Operator knowledge ingestion and reindex
- Screen: Knowledge Sources (`admin.php?page=uwpaic-knowledge`)
- Screen: Queue and Index Jobs (`admin.php?page=uwpaic-jobs`)
- Source Configuration Drawer component (inline edit)
- Job Row component (progress + retry/cancel + error expansion)
- Inline index stats and test-search console (Baachal pattern)
- Cache status display with guarded clear action
- Polling: 3-5s for job queues, up to 10s for deep indexing

---

## Phase 07 — Actions, Tools, Macros, and Integrations

- [ ] Tool/action contract and execution engine
- [ ] Tool registry with permission model
- [ ] Macro/intent routing layer
- [ ] Tool continuation protocol (provider-agnostic)
- [ ] External integration adapters (prioritized set)
- [ ] MCP client integration
- [ ] Webhook executor
- [ ] Full audit trail for tool executions

**Reference files**:
- `axiachat-ai/includes/add-ons/ai-tools/api.php` -- tool registration, execution
- `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` -- macro expansion
- `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` -- MCP client
- `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` -- webhook execution
- `mxchat-basic/js/mxchat_actions.js` -- client-side action handling
- `mxchat-basic/includes/admin-actions-page.php` -- action admin config
- `mxchat-basic/includes/class-mxchat-addons.php` -- addon/integration framework
- `baachal/baachal.php` -- hooks/filter extension points
- `baachal/admin/tabs/advanced.php` -- advanced config

**UX touchpoints** (from `PRD_UX.md`):
- Screen: Actions and Integrations (`admin.php?page=uwpaic-actions`) -- *deferred/phase-gated*
- Action catalog, permission matrix, test runner
- Tool execution feedback: sequential activity labels (AxiaChat pattern)
- WooCommerce integration detection notices (Baachal pattern)

---

## Phase 08 — WooCommerce and Business Workflows

- [ ] Product-aware retrieval and recommendation responses
- [ ] WooCommerce context enrichment (product data, cart, order status)
- [ ] Lead capture flow
- [ ] Appointment scheduling integration
- [ ] Live-agent handoff framework
- [ ] Business workflow admin controls and reporting

**Reference files**:
- `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php` -- appointment scheduling
- `axiachat-ai/includes/add-ons/leads/class-leads-manager.php` -- lead capture
- `axiachat-ai/includes/add-ons/livechat/class-livechat-manager.php` -- live agent handoff
- `mxchat-basic/includes/class-mxchat-woocommerce.php` -- WooCommerce integration
- `mxchat-basic/includes/class-mxchat-integrator.php` -- integration orchestration
- `baachal/admin/tabs/woocommerce.php` -- WooCommerce product context config
- `baachal/baachal.php` -- product-first context patterns

**UX touchpoints** (from `PRD_UX.md`):
- CTA Action Chip in chat panel (route to conversion action)
- WooCommerce detected/missing notices, disable unavailable toggles (Baachal pattern)

---

## Phase 09 — Security, Privacy, Performance, and Observability Hardening

- [ ] Security review closure: nonce, capability, sanitization, escaping audit
- [ ] Privacy controls: data retention policies, export, redaction
- [ ] GDPR compliance: consent management, data erasure
- [ ] Cache compatibility and performance profiling
- [ ] Structured logging and diagnostics export
- [ ] Alerting hooks and operational monitoring
- [ ] Rate limiting hardening under load

**Reference files**:
- `axiachat-ai/includes/moderation.php` -- content moderation pipeline
- `axiachat-ai/includes/sanitize-helpers.php` -- sanitization utilities
- `axiachat-ai/includes/logs.php` -- logging infrastructure
- `axiachat-ai/includes/usage.php` -- usage tracking and limits
- `mxchat-basic/admin/class-ajax-handler.php` -- AJAX security patterns
- `mxchat-basic/mxchat-basic.php` -- capability checks
- `mxchat-basic/includes/admin-transcripts-page.php` -- transcript review and privacy
- `baachal/baachal.php` -- security patterns
- `baachal/admin/settings-page.php` -- admin security controls
- `baachal/includes/content-indexer.php` -- data lifecycle

**UX touchpoints** (from `PRD_UX.md`):
- Screen: Security and Privacy (`admin.php?page=uwpaic-security`)
- Retention policy cards, export panel, access log table
- Flow 4: Operator diagnostics and runtime tuning
- Screen: Transcripts and Usage (`admin.php?page=uwpaic-transcripts`)
- Context Inspection Modal (Sources + Actions tabs)
- Translation Toolbar (optional module, default off)
- Master-detail split panel (desktop) / overlay (mobile)

---

## Phase 10 — Migration, Compatibility, and Release Candidate

- [ ] Migration path: `axiachat-ai` settings and data
- [ ] Migration path: `mxchat-basic` settings and data
- [ ] Migration path: `baachal` settings and data
- [ ] Backward-compatibility shims where required
- [ ] Dry-run migration with conflict detection
- [ ] Release candidate package and test matrix
- [ ] Rollback plan and disaster recovery runbook

**Reference files**:
- `axiachat-ai/axiachat-ai.php` -- activation data, option keys
- `axiachat-ai/includes/model-registry.php` -- model config to migrate
- `mxchat-basic/mxchat-basic.php` -- activation data, option keys
- `mxchat-basic/includes/class-mxchat-admin.php` -- admin config structure
- `baachal/baachal.php` -- option keys, activation data
- `baachal/uninstall.php` -- cleanup reference for migration inverse

**UX touchpoints** (from `PRD_UX.md`):
- Flow 5: Legacy migration to unified plugin
- Screen: Migration Assistant (`admin.php?page=uwpaic-migration`)
- Migration Mapping Table component (source-to-target, conflict badges)
- Dry-run report, rollback checkpoint
- Post-migration validation report

---

## Phase 11 — Validation, Documentation, and GA Readiness

- [ ] End-to-end validation report
- [ ] Admin/user documentation
- [ ] Migration guides (per source plugin)
- [ ] Known limitations and deferred backlog
- [ ] GA checklist sign-off

**Reference files**:
- `axiachat-ai/includes/logs.php`, `axiachat-ai/includes/usage-functions.php`
- `axiachat-ai/assets/js/usage.js`
- `mxchat-basic/includes/admin-transcripts-page.php`, `mxchat-basic/js/mxchat_transcripts.js`
- `mxchat-basic/readme.txt`
- `baachal/readme.txt`, `baachal/languages/baachal.pot`

---

## Cross-Cutting UX Requirements (from PRD_UX.md)

These apply across multiple phases and should be tracked as ongoing concerns.

### Design System

- [ ] Color tokens: deep slate `#0F172A` / `#111827` / `#1F2937`, accent `#22D3EE`, signals `#22C55E` / `#F59E0B` / `#EF4444`
- [ ] Typography: Space Grotesk (display), Inter (body), JetBrains Mono (code), scale 1.25
- [ ] Spacing scale: 4/8/12/16/24/32/48/64/96px
- [ ] Three-level elevation model (flat, card, modal)

### Interaction Patterns

- [ ] Forms: inline + on-submit validation, field-level errors, sticky success toast
- [ ] Modals for destructive actions only; drawers for routine edits
- [ ] Loading: skeletons for lists, inline spinners for toggles, progressive for large views
- [ ] Toasts for transient success; persistent banners for system issues
- [ ] Autosave: inline spinner then 800ms success state (MxChat pattern, `mxchat-basic/js/mxchat-admin.js`)
- [ ] Long operations: disable trigger, swap label, inline progress bar if > 3s

### Accessibility (WCAG 2.2 AA)

- [ ] Full keyboard navigation, no traps, skip links in admin
- [ ] Screen reader: semantic landmarks, explicit labels, polite live regions
- [ ] Color contrast AA on all states (default, hover, focus, disabled)
- [ ] Focus management: return to trigger on modal close
- [ ] `prefers-reduced-motion`: replace animations with opacity transitions
- [ ] `aria-disabled` for gated controls, `aria-pressed` for binary toggles

### Responsive Breakpoints

- [ ] `< 640px` mobile: full-height chat sheet, single-column admin forms
- [ ] `640-1024px` tablet: two-column admin, wider chat composer
- [ ] `> 1024px` desktop: full data density, 380-420px chat panel

### Animation Constraints

- [ ] Max 3 simultaneous animations per viewport region
- [ ] Min 0.6s for scroll-triggered reveals
- [ ] Cubic-bezier easing only (no linear)
- [ ] 60fps target under 6x CPU throttle for chat interactions

---

## Open Questions (from PRD)

1. Minimum feature set for GA beyond phases 03-06?
2. Mandatory migration scenarios for GA: settings-only, history, indexes, or all?
3. Default retention policy for chat transcripts and operational logs?
4. External integrations required before GA vs deferred post-GA?
5. Primary baseline source plugin per deferred capability?

## Open UX Questions (from PRD_UX.md)

1. Brand identity for GA: predefined palette or plugin-default neutral?
2. Legal/compliance copy for transcript consent, retention notice, AI disclosure?
3. Default quota policy: disable interactions with message, or hide widget entirely?

---

## Infrastructure Already in Place

| Component | Status | File |
|-----------|--------|------|
| Health endpoint | Done | `lib/health-endpoint.php` |
| Rate limiter | Done | `lib/rate-limiter.php` |
| Backup manager | Done | `lib/backup-manager.php` |
| Release workflow | Done | `.github/workflows/release.yml` |
| Quality CI | Done | `.github/workflows/quality.yml` |
| Test CI | Done | `.github/workflows/test.yml` |
| Security CI | Done | `.github/workflows/security.yml` |
| Integration CI | Done | `.github/workflows/integration.yml` |
| Structured logger | Done | `lib/structured-logger.php`, `lib/structured-logger.js` |
| Git hooks | Done | `.husky/pre-commit` |
