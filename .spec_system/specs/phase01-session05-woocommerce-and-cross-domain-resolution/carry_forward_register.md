# Carry-Forward Register

**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Purpose**: Consolidated register of all deferred items from Phase 01 sessions 01-05
**Created**: 2026-03-01

---

## Register Summary

| Source Session | CFI Count | High | Medium | Status |
|---------------|-----------|------|--------|--------|
| Session 01 (Provider) | 2 | 2 | 0 | all open |
| Session 02 (Runtime, Migration) | 7 | 5 | 2 | all open |
| Session 03 (RAG, Admin) | 9 | 4 | 5 | all open |
| Session 04 (Security, Tools, Integrations) | 11 | 6 | 5 | all open |
| Session 05 (WooCommerce) | 4 | 1 | 3 | all open |
| **Total** | **33** | **18** | **15** | **all open** |

---

## Session 01: DOM-PROVIDER (2 items)

### CFI-PRO-001
**Domain**: DOM-PROVIDER
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-PRO-002
**Description**: Integrate MxChat SSE streaming pattern into AxiaChat per-adapter architecture and add DeepSeek/Grok adapter classes.
**Resolution Criteria**: Streaming works per-adapter with provider-specific chunk parsing; DeepSeek and Grok adapters registered via `aichat_register_providers` hook.
**Cross-Domain Links**: CFI-RUN-003 (streaming transport), DEC-RUN-003 (streaming)
**Status**: open

### CFI-PRO-002
**Domain**: DOM-PROVIDER
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-PRO-004
**Description**: Generalize Claude's retry/fallback resilience pattern to OpenAI and Gemini adapters and incorporate Baachal's user-friendly error messaging.
**Resolution Criteria**: All provider adapters have configurable retry with backoff, fallback chains from registry, and user-friendly error messages for common failures.
**Cross-Domain Links**: None
**Status**: open

---

## Session 02: DOM-RUNTIME (4 items), DOM-MIGRATION (3 items)

### CFI-RUN-001
**Domain**: DOM-RUNTIME
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-RUN-001
**Description**: Integrate SRC-MXC contextual awareness (page content/title injection) and SRC-BAA complete-handler-replacement hook into SRC-AXI message pipeline architecture.
**Resolution Criteria**: Runtime pipeline supports page context injection and complete handler replacement via hooks.
**Cross-Domain Links**: DEC-PRO-001 (provider dispatch)
**Status**: open

### CFI-RUN-002
**Domain**: DOM-RUNTIME
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-RUN-002
**Description**: Design unified session storage (dedicated table vs options vs CPT) with SRC-MXC ownership validation, SRC-AXI token/cost tracking, SRC-BAA lifecycle hooks, and session expiration/cleanup.
**Resolution Criteria**: Session storage with per-session isolation, ownership validation, token tracking, lifecycle hooks, and configurable expiration.
**Cross-Domain Links**: RES-XD-005 (session ownership validation boundary with security)
**Status**: open

### CFI-RUN-003
**Domain**: DOM-RUNTIME
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-RUN-003
**Description**: Extract SRC-MXC streaming transport into modular layer integrated with SRC-AXI provider registry. Add transit-time response sanitization and tool-pending handshake compatibility.
**Resolution Criteria**: Modular streaming module works with all provider adapters, sanitizes output during transit, and falls back to non-streaming for tool rounds.
**Cross-Domain Links**: CFI-PRO-001 (streaming in adapters)
**Status**: open

### CFI-RUN-004
**Domain**: DOM-RUNTIME
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-RUN-004
**Description**: Design combined frontend architecture with SRC-MXC streaming/multi-bot, SRC-AXI thinking stages/suggestions, and SRC-BAA JS hook system. Evaluate jQuery dependency vs vanilla JS.
**Resolution Criteria**: Frontend architecture spec with component breakdown, event system, and technology decision (jQuery vs vanilla).
**Cross-Domain Links**: CFI-ADM-005 (admin UX vanilla JS rewrite)
**Status**: open

### CFI-MIG-001
**Domain**: DOM-MIGRATION
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-MIG-001
**Description**: Design combined plugin lifecycle with SRC-BAA uninstall pattern (capability check, data preservation flag, fallback file), SRC-AXI multi-table activation, and SRC-MXC cron lifecycle. Add lifecycle hooks for extension registration.
**Resolution Criteria**: Unified activate/deactivate/uninstall with data preservation, multi-table schema, cron lifecycle, and extension hooks.
**Cross-Domain Links**: RES-XD-001 (encryption cleanup on uninstall)
**Status**: open

### CFI-MIG-002
**Domain**: DOM-MIGRATION
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-MIG-002
**Description**: Formalize SRC-MXC version-conditional migration into migration registry with ordered callbacks, migration logging, schema validation, and optional rollback support.
**Resolution Criteria**: Migration registry with versioned callbacks, execution logging, post-migration validation, and documented rollback procedure.
**Cross-Domain Links**: None
**Status**: open

### CFI-MIG-003
**Domain**: DOM-MIGRATION
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-MIG-003
**Description**: Create generic option migration framework (key mapping, value transforms, deprecated model mapping) integrated with version registry. Include SRC-BAA data preservation flag and SRC-MXC cron lifecycle.
**Resolution Criteria**: Option migration framework with key mapping, value transforms, and integration with CFI-MIG-002 version registry.
**Cross-Domain Links**: CFI-MIG-002 (version registry)
**Status**: open

---

## Session 03: DOM-RAG (4 items), DOM-ADMIN (5 items)

### CFI-RAG-001
**Domain**: DOM-RAG
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-RAG-001
**Description**: Merge MxChat queue-based ingestion (priority ordering, retry, stall detection) with AxiaChat content cleaning (Elementor walker, shortcode expansion, web scraper). Design unified queue table schema.
**Resolution Criteria**: Unified ingestion pipeline with queue table, priority ordering, retry logic, and content cleaning.
**Cross-Domain Links**: RES-XD-007 (WooCommerce product indexing via RAG pipeline)
**Status**: open

### CFI-RAG-002
**Domain**: DOM-RAG
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-RAG-002
**Description**: Design unified chunking module with overlap-based splitting (AXI), admin-configurable chunk size (MXC), deterministic vector IDs (MXC), clean DB column storage (AXI).
**Resolution Criteria**: Chunking module with configurable overlap, chunk size, deterministic IDs, and clean storage schema.
**Cross-Domain Links**: None
**Status**: open

### CFI-RAG-003
**Domain**: DOM-RAG
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-RAG-003
**Description**: Replace brute-force O(n) local cosine similarity with ANN index. Evaluate SQLite VSS, HNSW in PHP, or mandatory Pinecone for production. Must scale to 50K+ chunks.
**Resolution Criteria**: ANN index implementation or Pinecone-required architecture decision with documented trade-offs.
**Cross-Domain Links**: CFI-WOO-001 (hybrid product retrieval may use vector search)
**Status**: open

### CFI-RAG-004
**Domain**: DOM-RAG
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-RAG-005
**Description**: Unify embedding provider architecture: add Voyage AI adapter, bot-specific key resolution, dimension normalization, align with provider registry (DEC-PRO-001).
**Resolution Criteria**: Embedding providers registered through provider registry with capability flags (per RES-XD-008).
**Cross-Domain Links**: RES-XD-008 (embedding provider registration through provider registry)
**Status**: open

### CFI-ADM-001
**Domain**: DOM-ADMIN
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-ADM-001
**Description**: Decompose monolithic admin class into per-page controllers (<600 LOC each). Integrate Baachal hook-based extensibility. Design multi-page sidebar navigation.
**Resolution Criteria**: Admin decomposed into per-page controllers, each under 600 LOC, with hook-based extensibility.
**Cross-Domain Links**: RES-XD-010 (diagnostics page hosts security panel)
**Status**: open

### CFI-ADM-002
**Domain**: DOM-ADMIN
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-ADM-002
**Description**: Apply AES-256-GCM encryption to all provider key fields. Port comprehensive field-by-field sanitization. Add regex API key format validation. Design plaintext-to-encrypted migration path.
**Resolution Criteria**: All API key fields encrypted at rest with migration from plaintext. Admin calls security encryption module (per RES-XD-001).
**Cross-Domain Links**: RES-XD-001 (security owns encryption module, admin owns storage interface), CFI-SEC-002 (security-side encryption)
**Status**: open

### CFI-ADM-003
**Domain**: DOM-ADMIN
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-ADM-003
**Description**: Unified diagnostics dashboard -- streaming test, analytics, log viewer, extensible panel registration hook. Extend analytics to RAG pipeline metrics. Sanitize API keys from error responses.
**Resolution Criteria**: Diagnostics page with panel registration hook, general + security panels (per RES-XD-010).
**Cross-Domain Links**: RES-XD-010 (security panel in diagnostics), CFI-SEC-004 (security audit panel)
**Status**: open

### CFI-ADM-004
**Domain**: DOM-ADMIN
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-ADM-004
**Description**: Unify operational controls -- embed origins whitelist, GDPR overlay, content moderation, multi-bot support, per-role rate limits, transcript management, WooCommerce toggles. Remove external license API dependency.
**Resolution Criteria**: Unified operational controls admin page with all listed features, no external license dependency.
**Cross-Domain Links**: CFI-WOO-002 (WooCommerce catalog config in admin)
**Status**: open

### CFI-ADM-005
**Domain**: DOM-ADMIN
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-ADM-005
**Description**: Rewrite admin UX in vanilla JS (no jQuery). Auto-save with per-field debouncing via native fetch(). Model selector modals reimplemented. localStorage tab persistence. wp.i18n for translations.
**Resolution Criteria**: Admin JS rewritten without jQuery dependency. All UX features working with vanilla JS.
**Cross-Domain Links**: CFI-RUN-004 (frontend jQuery evaluation)
**Status**: open

---

## Session 04: DOM-SECURITY (4 items), DOM-TOOLS (4 items), DOM-INTEGRATIONS (3 items)

### CFI-SEC-001
**Domain**: DOM-SECURITY
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-SEC-001
**Description**: Design unified sanitization library merging AXI custom domain sanitizers, BAA granular bounds checking, and MXC whitelist model validation into a reusable module.
**Resolution Criteria**: Sanitization module with domain-specific sanitizers, bounds checking, and model validation.
**Cross-Domain Links**: None
**Status**: open

### CFI-SEC-002
**Domain**: DOM-SECURITY
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-SEC-002
**Description**: Implement API key encryption at rest with migration routine for existing plaintext keys. Design key rotation strategy. Integrate with provider registry for transparent decryption.
**Resolution Criteria**: Security encryption module (per RES-XD-001) with migration, rotation, and transparent decryption.
**Cross-Domain Links**: RES-XD-001 (security owns encryption), CFI-ADM-002 (admin-side storage)
**Status**: open

### CFI-SEC-003
**Domain**: DOM-SECURITY
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-SEC-004
**Description**: Design privacy and usage limit configuration: daily limits with configurable behavior (hard/soft), IP privacy toggle, conversation memory cap, admin UI.
**Resolution Criteria**: Privacy and usage limits configurable via admin with enforcement in runtime.
**Cross-Domain Links**: RES-XD-004 (security policy, runtime execution)
**Status**: open

### CFI-SEC-004
**Domain**: DOM-SECURITY
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-SEC-005
**Description**: Integrate audit logging with structured logger. Define log levels, retention policy, security event categories. Plan admin security dashboard.
**Resolution Criteria**: Security audit logging via structured logger, panel in admin diagnostics (per RES-XD-010).
**Cross-Domain Links**: RES-XD-010 (security panel in diagnostics), CFI-ADM-003 (diagnostics page)
**Status**: open

### CFI-TOL-001
**Domain**: DOM-TOOLS
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-TOL-001
**Description**: Design unified tool registration architecture: AXI tool-calling API as primary + MXC intent/action as complementary "user actions" subsystem. Define intent-to-tool bridging.
**Resolution Criteria**: Canonical tool schema (per RES-XD-002) with provider-agnostic registration. Intent/action subsystem bridged to tool registry.
**Cross-Domain Links**: RES-XD-002 (canonical schema, provider normalizes), RES-XD-011 (WC cart ops as tools)
**Status**: open

### CFI-TOL-002
**Domain**: DOM-TOOLS
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-TOL-002
**Description**: Design tool execution lifecycle with pre/post hooks, configurable timeouts, retry logic, execution result caching. Provider schema normalization versioned.
**Resolution Criteria**: Tool execution engine with lifecycle hooks, timeouts, and caching. Provider normalization handled by adapters (per RES-XD-002).
**Cross-Domain Links**: RES-XD-002 (provider normalizes tool schema)
**Status**: open

### CFI-TOL-003
**Domain**: DOM-TOOLS
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-TOL-003
**Description**: Formalize macro registry schema for external consumers. Document tool grouping API for MCP servers and webhook providers. Test cascade delete across integration boundaries.
**Resolution Criteria**: Documented macro registry schema. Cascade delete on source removal (per RES-XD-009).
**Cross-Domain Links**: RES-XD-009 (tools registry, integrations source lifecycle)
**Status**: open

### CFI-TOL-004
**Domain**: DOM-TOOLS
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-TOL-004
**Description**: Design unified tool management admin UI: merge MXC split-panel layout with AXI capability settings. Include tool execution history, category organization, per-bot access control.
**Resolution Criteria**: Tool management admin page with split-panel layout, capability settings, and execution history.
**Cross-Domain Links**: CFI-ADM-001 (admin decomposition)
**Status**: open

### CFI-INT-001
**Domain**: DOM-INTEGRATIONS
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-INT-001
**Description**: Expand MCP client transport beyond HTTP: add SSE and stdio transports. Design connection pooling and reconnection strategy. Keep lazy connection pattern.
**Resolution Criteria**: MCP client supports SSE and stdio transports with connection pooling.
**Cross-Domain Links**: None
**Status**: open

### CFI-INT-002
**Domain**: DOM-INTEGRATIONS
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-INT-002
**Description**: Design event-driven webhook system: define chatbot events (on_message, on_tool_call, on_moderation_block, on_session_end), async execution via queue, custom placeholders, SSRF protection on all URLs.
**Resolution Criteria**: Webhook event system with defined events, async queue, and SSRF protection.
**Cross-Domain Links**: RES-XD-009 (webhook tools source lifecycle)
**Status**: open

### CFI-INT-003
**Domain**: DOM-INTEGRATIONS
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-INT-003
**Description**: Design unified extension model: single admin surface showing WordPress addons, MCP servers, webhooks. Merge MXC marketplace visuals with AXI source tracking lifecycle. Extension health monitoring.
**Resolution Criteria**: Unified extension admin surface with marketplace UI, source tracking, and health monitoring.
**Cross-Domain Links**: CFI-ADM-001 (admin decomposition)
**Status**: open

---

## Session 05: DOM-WOOCOMMERCE (4 items)

### CFI-WOO-001
**Domain**: DOM-WOOCOMMERCE
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-WOO-001
**Description**: Design hybrid product retrieval combining BAA keyword search (zero-dependency baseline) with AXI/MXC vector search (when RAG infrastructure available). Ensure query-time data enrichment for price/stock freshness.
**Resolution Criteria**: Product retrieval supports keyword-only mode and keyword+vector mode. Price/stock enrichment applied at query time.
**Cross-Domain Links**: RES-XD-007 (RAG-WooCommerce boundary), CFI-RAG-003 (ANN index)
**Status**: open

### CFI-WOO-002
**Domain**: DOM-WOOCOMMERCE
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-WOO-002
**Description**: Reimplement product-from-message extraction with scalable matching (cached product index, configurable thresholds). Integrate SRC-AXI product selection modes for admin control over chatbot product scope.
**Resolution Criteria**: Product extraction from messages with caching, configurable matching, and admin product scope controls.
**Cross-Domain Links**: CFI-ADM-004 (WooCommerce toggles in admin)
**Status**: open

### CFI-WOO-003
**Domain**: DOM-WOOCOMMERCE
**Priority**: high
**Target Phase**: Phase 02
**Source Decision**: DEC-WOO-003
**Description**: Implement validated cart operations (product existence/visibility/purchasability checks, variable product support, quantity), per-session product context tracking, and checkout redirect. Remove production `error_log()` calls.
**Resolution Criteria**: Cart operations with full validation, variable product support, per-session context tracking, checkout redirect.
**Cross-Domain Links**: RES-XD-011 (dual exposure as AJAX + tools), CFI-TOL-001 (tool registration for WC ops)
**Status**: open

### CFI-WOO-004
**Domain**: DOM-WOOCOMMERCE
**Priority**: medium
**Target Phase**: Phase 02
**Source Decision**: DEC-WOO-004
**Description**: Enhance product card grid with interactive elements (add-to-cart buttons, quick-view). Add per-session product context tracking for conversational follow-up.
**Resolution Criteria**: Product cards with interactive elements and per-session context tracking.
**Cross-Domain Links**: CFI-WOO-003 (cart operations), CFI-RUN-004 (frontend architecture)
**Status**: open

---

## Completeness Verification

### Sessions 01-04 CFI Items Accounted For

| Session | CFI IDs | Count | All Present |
|---------|---------|-------|-------------|
| Session 01 | CFI-PRO-001, CFI-PRO-002 | 2 | YES |
| Session 02 | CFI-RUN-001 through CFI-RUN-004, CFI-MIG-001 through CFI-MIG-003 | 7 | YES |
| Session 03 | CFI-RAG-001 through CFI-RAG-004, CFI-ADM-001 through CFI-ADM-005 | 9 | YES |
| Session 04 | CFI-SEC-001 through CFI-SEC-004, CFI-TOL-001 through CFI-TOL-004, CFI-INT-001 through CFI-INT-003 | 11 | YES |

**Result**: All 29 CFI items from sessions 01-04 are present in this register. Zero items silently dropped.

### Cross-Domain Resolution Linkage

11 cross-domain resolutions (RES-XD-001 through RES-XD-011) have been linked to affected CFI items where applicable. No resolution generated new CFI items (all resolutions clarified boundaries without requiring additional implementation work).

### Priority Distribution

| Priority | Count | Percentage |
|----------|-------|------------|
| High | 18 | 55% |
| Medium | 15 | 45% |
| Low | 0 | 0% |
| **Total** | **33** | **100%** |

All items target Phase 02.
