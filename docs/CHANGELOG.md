# Changelog

All notable changes to the Ultimate WP AI Chatbot plugin are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Usage:** When completing work, append entries under the `[Unreleased]` section.
> Move entries to a versioned heading at release time.

---

## [Unreleased]

### Added

#### Infrastructure & Tooling (Phase 00–02)
- Project scaffolding: Biome, PHP-CS-Fixer, PHPStan, PHPUnit, Vitest
- Structured logger (PHP + JS) in `lib/`
- Health endpoint (`lib/health-endpoint.php`)
- Rate limiter (`lib/rate-limiter.php`)
- Backup manager (`lib/backup-manager.php`)
- CI/CD: quality, test, security, integration, release workflows
- Git hooks via husky + lint-staged
- Phase 00: reference inventory baseline, domain taxonomy, context pack and decision log templates
- Phase 01: canonical capability matrix and scoring across all domains, winner consolidation
- Phase 02: architecture contracts — module map, DB schema, provider/chat/RAG/security/admin/migration contracts

#### Phase 03 — Core Plugin Skeleton, Lifecycle, and Data Foundations
- Plugin bootstrap (`wp-ai-chatbot.php`): 17 WPAIC_* constants, Phase A/B init, version guards, lifecycle hooks
- Autoloader (`includes/autoloader.php`): spl_autoload_register; WPAIC\ → includes/{domain}/{type}-*.php
- Plugin singleton (`includes/class-plugin.php`): filterable hook array, WC detection + HPOS, cron schedule, cache exclusion, class_exists guards for phase-gated modules
- Lifecycle manager (`includes/migration/class-lifecycle-manager.php`): activate (version guard, migrations, seed defaults, schedule cron), deactivate (unschedule), uninstall (preserve flag, drop tables, delete options/transients/user_meta)
- Uninstall handler (`uninstall.php`): WP_UNINSTALL_PLUGIN guard + Lifecycle_Manager::uninstall()
- Migration registry (`includes/migration/class-migration-registry.php`): semver-ordered runner, transient lock, self-bootstrapping 0.0.1, wpaic_register_migrations hook, schema validation
- Core DB schema: 7 MVP tables (wpaic_sessions, wpaic_messages, wpaic_knowledge, wpaic_chunks, wpaic_queue, wpaic_usage_daily, wpaic_bots)
- WP-Cron foundation: wpaic_cleanup_sessions (hourly) + wpaic_process_queue (every 5 min)
- Directory scaffold: all MVP + deferred stub dirs and .gitkeep files
- Encryption (`includes/security/class-encryption.php`): AES-256-GCM with versioned base64 JSON envelope, WPAIC_MASTER_KEY or wp_salt fallback, graceful OpenSSL degradation
- Sanitizer (`includes/security/class-sanitizer.php`): 12 TYPE_* constants, sanitize_map(), wpaic_sanitizer_types filter
- Auth enforcer (`includes/security/class-auth-enforcer.php`): check_ajax() + check_rest() dual-check, 12 ENDPOINT_* constants, verify_session_ownership(), rate limiter before nonce on nopriv
- Audit logger (`includes/security/class-audit-logger.php`): 15 EVENT_* constants, routes to structured logger (subsystem=audit), query() reads log files reversed
- Provider interface + streamable provider interface (`includes/provider/`)
- Provider registry (`includes/provider/class-provider-registry.php`): singleton, hook-driven lazy init, alias map, config cache, CR-01 through CR-05 validation, get_stats()
- OpenAI adapter (`includes/provider/adapters/class-openai-adapter.php`): Chat Completions API, exponential backoff, cURL SSE streaming, microcent cost accounting, 8-model pricing table
- Admin page base (`includes/admin/class-admin-page.php`): abstract base with tabs, render helpers
- Admin router (`includes/admin/class-admin-router.php`): singleton, 7 submenu pages, per-page asset loading, dispatch system
- Settings manager (`includes/admin/class-settings-manager.php`): transparent encrypt/decrypt, get_masked(), sensitive_sanitize_callback()
- General settings page (`includes/admin/pages/class-general-page.php`): provider select, masked API key inputs, model field

#### Phase 04 — Provider Orchestration and Model Registry
- Claude adapter (`includes/provider/adapters/class-claude-adapter.php`): Anthropic Messages API, SSE streaming with per-event parsing, tool_use block extraction, 11-model pricing table
- Gemini adapter (`includes/provider/adapters/class-gemini-adapter.php`): Google Generative Language API, :streamGenerateContent?alt=sse, thinkingConfig for 2.5/pro models, functionDeclarations tool format, 8-model pricing table
- Model registry (`includes/provider/class-model-registry.php`): 25+ models across 5 providers, deprecated flag + successor chain, resolve_model() auto-migration, wpaic_model_registry_models filter
- Provider key validation REST endpoint: POST /wp-ai-chatbot/v1/providers/validate (token-free GET model listing probe)
- Provider registration wired in class-plugin.php (register_core_providers hook)

#### Phase 05 — Chat Runtime, Sessioning, UX, and Safety Controls
- Message pipeline (`includes/runtime/class-message-pipeline.php`): 4-stage pipeline (11-step pre-process, context assembly, sync/SSE dispatch, post-process), static handle_ajax() entry point
- Session manager (`includes/runtime/class-session-manager.php`): CRUD + REST routes, UUID v4 tokens, rolling 24h TTL, ownership validation, cron cleanup with retention
- AJAX endpoints: wp_ajax_wpaic_chat + nopriv variant → Message_Pipeline::handle_ajax()
- SSE streaming: dispatch_stream() with chunk callback, finalization event with full HTML + suggestions, fallback to sync AJAX
- REST endpoints: POST/GET/DELETE /sessions, GET /sessions/{token}/history
- Widget template (`templates/chatbot-widget.php`): floating launcher + chat panel, inline SVG icons, multi-instance via static $instance_counter, honeypot + GDPR banner
- Shortcode `[wpaic_chatbot]`: merges atts with option defaults, lazy-enqueues assets; floating widget via on_frontend_footer()
- Vanilla JS frontend (`assets/js/chatbot.js`): window.WPAICChat namespace, instance registry, DOM state machine (idle/open/typing/streaming), SSE via fetch ReadableStream with sync fallback
- CSS design system (`assets/css/chatbot.css`): deep slate palette, floating/inline modes, mobile full-height sheet, prefers-reduced-motion, typing dots animation, streaming cursor
- Input sanitization pipeline: PHP (sanitize_textarea_field, UUID regex, configurable max length) + JS (textContent for user, DOMParser allowlist for bot)
- Output formatter (`includes/runtime/class-output-formatter.php`): suggestion extraction, markdown→HTML, wp_kses 28-tag allowlist, link safety
- Anti-spam: honeypot (hidden field, aria-hidden), rate limiter integration, MD5 duplicate detection (10s transient)
- Moderation hook chain: wpaic_moderate_message filter at priorities 5/10/15
- Page context injection: JS sends page_url + page_title, wpaic_inject_page_context filter
- GDPR consent inline gating: consent banner with policy link, localStorage per bot_slug
- Multi-instance support: unique IDs per widget, session storage scoped per bot_slug

#### Phase 06 — Knowledge Base, RAG, and Content Ingestion
- Content source interface + registry (`includes/rag/interface-content-source.php`, `class-content-source-registry.php`)
- Posts source adapter (`includes/rag/adapters/class-posts-source.php`): WP posts/pages/CPTs, apply_filters('the_content'), HTML→plaintext
- Chunking engine (`includes/rag/class-chunker.php`): paragraph→sentence→word fallback, overlap prefix, token estimate
- Embedding provider interface + registry (`includes/rag/interface-embedding-provider.php`, `class-embedding-registry.php`)
- OpenAI embedding adapter (`includes/rag/adapters/class-openai-embedding.php`): text-embedding-3-small/large/ada-002, batch API, microcent pricing
- Knowledge index (`includes/rag/class-knowledge-index.php`): upsert ON DUPLICATE KEY, cosine similarity search in PHP, wpaic_rag_search_results filter for external vector DB
- Ingestion pipeline (`includes/rag/class-ingestion-pipeline.php`): queue-based, atomic claim (UPDATE+UUID4), stall recovery 300s, 3 retries, wpaic_ingestion_batch_size filter
- Retrieval engine (`includes/rag/class-retrieval-engine.php`): hooks wpaic_rag_context, embeds query, 3000-char context cap
- Knowledge admin page (`includes/admin/pages/class-knowledge-page.php`): context list table, REST-driven ingest/refresh, global RAG settings
- 3 knowledge REST endpoints: POST /knowledge, POST /knowledge/{id}/ingest, GET /knowledge/{id}/status

#### Phase 07 — Actions, Tools, Macros, and Integrations
- Tool interface (`includes/tools/interface-tool-interface.php`): get_id, get_name, get_description, get_schema, get_permission, execute, to_provider_definition
- Tool registry (`includes/tools/class-tool-registry.php`): singleton, lazy via wpaic_register_tools, per-bot tool enablement, wpaic_tools_default_enabled filter
- Tool executor (`includes/tools/class-tool-executor.php`): permission model (none/user/admin + filter), audit logging, MAX_OUTPUT_LENGTH=8000 truncation
- Tool continuation (`includes/tools/class-tool-continuation.php`): provider-agnostic two-phase loop, OpenAI/Claude format normalisation, MAX_LOOPS=5
- Webhook tool adapter (`includes/tools/adapters/class-webhook-tool.php`): HTTP GET/POST, SSRF guard (blocks localhost/RFC-1918), placeholder substitution, timeout cap 30s
- MCP client (`includes/mcp/class-mcp-client.php`): MCP 2025-06-18 protocol, JSON-RPC 2.0 over HTTP, connect() handshake, list_tools(), execute_tool()
- MCP registry (`includes/mcp/class-mcp-registry.php`): singleton, server CRUD, lazy client factory, sync_server_tools() live discovery + cache
- MCP tool adapter (`includes/mcp/class-mcp-tool-adapter.php`): composite ID, maps MCP inputSchema to Tool_Interface
- Tools admin page (`includes/admin/pages/class-tools-page.php`): webhook CRUD table, MCP server table with test/sync
- 9 REST endpoints under /wp-ai-chatbot/v1/tools/{webhooks,mcp}
- Message pipeline updated: injects tool definitions when wpaic_tools_enabled=1, streaming auto-disabled when tools present, Tool_Continuation::run() on finish_reason=tool_calls

#### Phase 08 — WooCommerce and Business Workflows
- WC products source (`includes/woocommerce/class-wc-products-source.php`): Content_Source adapter for product catalog (name, SKU, price, categories, stock, descriptions)
- WC context provider (`includes/woocommerce/class-wc-context-provider.php`): injects product page, cart contents, recent orders into system prompt via wpaic_rag_context
- WC cart tool (`includes/woocommerce/class-wc-cart-tool.php`): wpaic_wc_add_to_cart, permission=none
- WC orders tool (`includes/woocommerce/class-wc-orders-tool.php`): wpaic_wc_get_orders, permission=user
- Lead manager (`includes/integrations/class-lead-manager.php`): CRUD, CSV export, stats, IP hashing, email notification, retention cleanup
- Lead capture tool (`includes/integrations/class-lead-capture-tool.php`): wpaic_capture_lead, all fields optional
- Livechat manager (`includes/integrations/class-livechat-manager.php`): state machine (bot→pending_agent→agent_connected→closed), email notification
- Livechat handoff tool (`includes/integrations/class-livechat-handoff-tool.php`): wpaic_livechat_handoff, reads session_id from context
- Chat admin page (`includes/admin/pages/class-chat-page.php`): 4 tabs (WooCommerce/Lead Capture/Live Agent/Leads Inbox), CSV export, 5 REST endpoints
- DB migrations 1.1.0: wpaic_leads + wpaic_livechat_sessions tables
- wpaic_daily_cleanup cron (daily): lead retention + session retention

#### Phase 09 — Security, Privacy, Performance, and Observability Hardening
- Privacy manager (`includes/security/class-privacy-manager.php`): WP personal data exporter/eraser for sessions + leads, configurable retention policy, daily cron cleanup
- Content moderator (`includes/security/class-content-moderator.php`): keyword blocklist on wpaic_moderate_message filter, configurable response message, audit logging
- Security admin page (`includes/admin/pages/class-security-page.php`): 3 tabs — Security (rate limiting + moderation), Privacy (retention + anonymise), Moderation (blocklist + response)
- Diagnostics admin page (`includes/admin/pages/class-diagnostics-page.php`): 3 tabs — Audit Logs, Health Check (10 live checks), System Info (copyable debug dump)
- Rate limiting hardening: configurable max requests / window via Security_Page (wpaic_security_rate_limit_max, wpaic_security_rate_limit_window)

#### Phase 10 — Migration, Compatibility, and Release Candidate
- AxiaChat migrator (`includes/migration/class-axiachat-migrator.php`): 6 option mappings, 3-canary detect(), dry-run/live/rollback
- MxChat migrator (`includes/migration/class-mxchat-migrator.php`): 10 option mappings from mxchat_options array, infers provider_default
- Baachal migrator (`includes/migration/class-baachal-migrator.php`): 13 option mappings, provider normalisation (grok→xai, anthropic→claude, google→gemini), WC + RAG settings
- Migration runner (`includes/migration/class-migration-runner.php`): detect_all(), get_mapping(), run(), rollback(); backup snapshots with MAX_BACKUPS=5
- Migration admin page (`includes/admin/pages/class-migration-page.php`): 4 tabs (Overview + per-source), detection cards, mapping table with conflict badges, Dry-Run/Run Live/Rollback buttons
- 4 migration REST endpoints: GET /migration, GET /migration/{source}/mapping, POST /migration/{source}/run, POST /migration/{source}/rollback

#### Batch A — Deferred Provider Adapters and Quick Wins
- OpenAI-Compatible abstract base class (`includes/provider/adapters/class-openai-compatible-adapter.php`): shared HTTP dispatch, SSE parsing, exponential backoff retry, error normalization, streaming with provider-specific hook points (`post_process_response`, `post_process_sse_event`, `build_stream_summary`)
- DeepSeek adapter (`includes/provider/adapters/class-deepseek-adapter.php`): extends OpenAI_Compatible_Adapter, 2 models (deepseek-chat, deepseek-reasoner), reasoning_content extraction and reasoning_tokens tracking
- Grok/xAI adapter (`includes/provider/adapters/class-grok-adapter.php`): extends OpenAI_Compatible_Adapter, 3 models (grok-3, grok-3-mini, grok-beta)
- DeepSeek + Grok provider key validation probes in `probe_provider()` (with `xai` alias for Grok)
- WooCommerce products registered as RAG content source (`register_core_content_sources()`) when WooCommerce is active

### Decisions

> Architectural decisions researched and finalized during Phases 00–10. Sources:
> 3 reference plugins (axiachat-ai, mxchat-basic, baachal) + industry analysis
> (Tidio, WPBot, Botpress, Boei, ChatBot.com) + legal review (EU AI Act, GDPR,
> CCPA/SB 1001).

- **GA feature scope**: No additional features beyond Phases 03–10 required; only gap was AI disclosure statement (added to Phase 11)
- **Migration scope**: Settings-only migration (no chat history or index migration); auto-detect + dry-run + conflict badges + rollback exceeds industry standard
- **Retention policy**: 90 days for chat transcripts (`wpaic_data_retention_days`, default 90; 0 = indefinite), 30 days for operational/audit logs, 5 years for consent records, indefinite for anonymized analytics — aligned with GDPR industry consensus (Quickchat AI, PremAI, Zendesk)
- **External integrations**: 5 AI providers + WooCommerce + webhooks + MCP covers GA; appointment booking, Slack/Telegram, Pinecone deferred post-GA
- **Baseline sources per deferred capability**: appointment scheduling (AxiaChat), transcripts/usage screen (MxChat), PDF content source (AxiaChat), preset color palettes (Baachal), Slack handoff (MxChat), per-bot tool assignment (AxiaChat)
- **Brand identity**: Neutral defaults + preset palettes; ship with dark/teal scheme (`--wpaic-accent #22D3EE`, `--wpaic-bg-deep #0F172A`); 6–10 preset palettes (Baachal pattern) + full hex customization; optional removable "Powered by" footer
- **Legal/compliance copy**: 3 default editable texts — (a) AI disclosure: *"You are chatting with an AI assistant. Your messages are processed to generate responses."* (non-dismissible, `wpaic_ai_disclosure_text`); (b) GDPR consent banner (blocks input until accepted); (c) Retention notice for Security settings + privacy policy addendum
- **Quota policy**: Show message + disable input, never hide widget; customizable via `wpaic_rate_limit_message`; optional fallback to email/phone/live agent escalation

### Changed
- OpenAI adapter (`includes/provider/adapters/class-openai-adapter.php`): refactored from standalone 662-line class to thin subclass of OpenAI_Compatible_Adapter (~70 lines), retaining only OpenAI-specific constants

### Fixed

### Removed

---

<!--
## [0.1.0] - YYYY-MM-DD

### Added
- First tagged release items go here

[Unreleased]: https://github.com/OWNER/wp-ai-chatbot/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/OWNER/wp-ai-chatbot/releases/tag/v0.1.0
-->
