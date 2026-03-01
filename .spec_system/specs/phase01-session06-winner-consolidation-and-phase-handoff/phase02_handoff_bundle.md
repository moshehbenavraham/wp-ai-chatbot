# Phase 02 Handoff Bundle

**Session ID**: `phase01-session06-winner-consolidation-and-phase-handoff`
**Purpose**: Architecture-ready inputs for Phase 02 sessions
**Created**: 2026-03-01
**Sources**: All Phase 01 decision logs, domain_winner_map.md, cross_domain_resolution_register.md, carry_forward_register.md

---

## 1. Per-Domain Winner Summaries

### DOM-PROVIDER (SRC-AXI, 4/4)

**Architecture Foundation**: Singleton+Factory provider registry with interface compliance validation.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Provider Registry | `axiachat-ai/includes/class-aichat-provider-registry.php` | Singleton with `aichat_register_providers` hook, config-hash instance caching |
| Adapter Interface | `axiachat-ai/includes/providers/class-openai-provider.php` et al. | `AIChat_Provider_Interface` with per-provider adapter classes |
| Model Registry | `axiachat-ai/includes/model-registry.php` | 35+ models, resolution pipeline (exact -> alias -> prefix -> default), `aichat_model_registry` filter |
| Error Resilience | `axiachat-ai/includes/providers/class-claude-provider.php` | Exponential backoff retry (3 attempts), 4-tier fallback chain |

**Modifications for Phase 02**:
- Integrate MxChat SSE streaming into per-adapter architecture (CFI-PRO-001)
- Generalize Claude retry/fallback to all adapters (CFI-PRO-002)
- Add DeepSeek and Grok adapter classes
- Add `embedding` capability flag to registry (per RES-XD-008)

---

### DOM-RUNTIME (Mixed: SRC-AXI + SRC-MXC)

**Architecture Foundation**: AXI pipeline dispatching through provider registry, MXC user-facing layers.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Message Pipeline | `axiachat-ai/includes/class-aichat-ajax.php` | Pre-processing chain, provider dispatch, tool-pending handshake |
| Session Management | `mxchat-basic/includes/class-mxchat-integrator.php` | Ownership validation (IP/user binding), per-bot isolation |
| SSE Streaming | `mxchat-basic/includes/class-mxchat-integrator.php` | cURL WRITEFUNCTION backend, Fetch API frontend, 5-provider parsing |
| Frontend Runtime | `mxchat-basic/js/chat-script.js` | Multi-bot instances, streaming DOM updates, error handling |

**Modifications for Phase 02**:
- Integrate page context injection into pipeline (CFI-RUN-001)
- Design unified session storage table (CFI-RUN-002)
- Extract streaming into modular transport layer (CFI-RUN-003)
- Design combined frontend with BAA JS hooks (CFI-RUN-004)

---

### DOM-MIGRATION (Mixed: SRC-BAA + SRC-MXC)

**Architecture Foundation**: BAA lifecycle pattern with MXC version-conditional migration system.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Plugin Lifecycle | `baachal/baachal.php`, `baachal/uninstall.php` | Three-hook coverage, capability checks, data preservation flag, fallback uninstall |
| Version Migration | `mxchat-basic/mxchat-basic.php` | Stored version option, page-load check, 10+ migrations across 6+ versions |
| Settings Migration | `mxchat-basic/mxchat-basic.php` | Deprecated model mapping, orphaned data cleanup, cron lifecycle |

**Modifications for Phase 02**:
- Design combined lifecycle with extension hooks (CFI-MIG-001)
- Formalize migration registry (CFI-MIG-002)
- Create generic option migration framework (CFI-MIG-003)

---

### DOM-RAG (SRC-AXI, 4/5)

**Architecture Foundation**: AXI retrieval/assembly architecture with MXC ingestion infrastructure.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Ingestion Queue | `mxchat-basic/admin/class-knowledge-manager.php` | DB-backed queue, 8+ sources, priority ordering, retry/stall detection |
| Content Cleaning | `axiachat-ai/includes/contexto-functions.php` | Elementor walker, shortcode expansion, web scraper |
| Chunking | `axiachat-ai/includes/contexto-functions.php` | Overlap-based splitting, separate DB column storage |
| Retrieval | `axiachat-ai/includes/contexto-functions.php` | 4 modes (local/Pinecone/page/auto), Pinecone security |
| Context Assembly | `axiachat-ai/includes/contexto-functions.php` | Security policy injection, template tokens (allowlist), suggestion generation |
| Embedding | `axiachat-ai/includes/contexto-functions.php` | Override filter, per-context provider persistence |

**Modifications for Phase 02**:
- Merge ingestion pipelines (CFI-RAG-001)
- Unified chunking module (CFI-RAG-002)
- ANN index for scalable retrieval (CFI-RAG-003)
- Embedding provider through provider registry (CFI-RAG-004)

---

### DOM-ADMIN (Mixed: SRC-MXC + SRC-AXI)

**Architecture Foundation**: MXC multi-page Settings API with AXI security controls.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Settings Pages | `mxchat-basic/includes/class-mxchat-admin.php` | 6 pages, WP Settings API, sidebar navigation, template rendering |
| Encrypted Storage | `axiachat-ai/includes/settings.php` | AES-256-GCM, per-value random IVs, `aichat_get_setting()` with auto-decrypt |
| Diagnostics | `mxchat-basic/includes/class-mxchat-admin.php` | SSE streaming test, analytics charts, Slack test |
| Operational Controls | `axiachat-ai/includes/settings.php` | Embed origins whitelist, GDPR overlay, content moderation, usage limits |
| Admin UX | `mxchat-basic/js/mxchat-admin.js` | Auto-save with debouncing, model selector modals, visual feedback |

**Modifications for Phase 02**:
- Decompose monolithic class into per-page controllers (CFI-ADM-001)
- Encrypt all API key fields with migration (CFI-ADM-002)
- Unified diagnostics with panel registration hook (CFI-ADM-003)
- Unified operational controls without license API (CFI-ADM-004)
- Vanilla JS rewrite (CFI-ADM-005)

---

### DOM-SECURITY (SRC-AXI, 5/5)

**Architecture Foundation**: AXI defense-in-depth with custom domain sanitizers and 5-layer moderation.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Sanitization | `axiachat-ai/includes/sanitize-helpers.php` | Custom domain sanitizers, bounded int, JSON payload validation |
| Auth Enforcement | `axiachat-ai/includes/class-aichat-ajax.php` | Nonce + capability dual-check, per-bot capability settings |
| Moderation | `axiachat-ai/includes/moderation.php` | Honeypot, rate limiting, spam signatures, banned words, OpenAI Moderation API |
| Privacy | `axiachat-ai/includes/class-aichat-ajax.php` | Encrypted keys, IP privacy toggle, daily usage limits |
| Audit Logging | `axiachat-ai/includes/class-aichat-ajax.php` | Conditional debug, moderation tracking, tool timing, webhook DB logging |

**Modifications for Phase 02**:
- Unified sanitization module (CFI-SEC-001)
- API key encryption with migration (CFI-SEC-002)
- Privacy and usage limit admin UI (CFI-SEC-003)
- Structured logger integration (CFI-SEC-004)

---

### DOM-TOOLS (SRC-AXI, 3/4) -- Deferred

**Architecture Foundation**: AXI tool-calling API with JSON Schema validation and macro registry.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Tool Registration | `axiachat-ai/includes/add-ons/ai-tools/api.php` | JSON Schema validation, global registry, lifecycle hooks |
| Execution Engine | `axiachat-ai/includes/add-ons/ai-tools/api.php` | Provider-native injection, per-provider normalization |
| Macro Registry | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` | Source tracking (local/MCP/webhook), cascade delete, per-bot capabilities |
| Admin Controls | `mxchat-basic/includes/admin-actions-page.php` | Split-panel layout, category filtering, mobile-responsive |

**Phase 02 Architecture Decisions Needed**:
- Canonical tool schema (provider-agnostic, per RES-XD-002)
- Intent-to-tool bridging design (CFI-TOL-001)
- Tool execution lifecycle hooks (CFI-TOL-002)
- WooCommerce cart ops as tools (per RES-XD-011)

---

### DOM-INTEGRATIONS (SRC-AXI, 2/3) -- Deferred

**Architecture Foundation**: AXI MCP client and webhook executor with MXC marketplace UI.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| MCP Client | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` | JSON-RPC sessions, lazy connection, tool discovery |
| Webhook Executor | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` | HTTP methods, SSRF protection, placeholder substitution, DB logging |
| Marketplace UI | `mxchat-basic/includes/class-mxchat-addons.php` | Visual cards, status detection, deprecation management |

**Phase 02 Architecture Decisions Needed**:
- MCP transport expansion: SSE and stdio (CFI-INT-001)
- Event-driven webhook triggers (CFI-INT-002)
- Unified extension model (CFI-INT-003)

---

### DOM-WOOCOMMERCE (SRC-BAA, 4/5) -- Deferred

**Architecture Foundation**: BAA product context and presentation with MXC cart operations.

| Key Pattern | Source File | Description |
|------------|-----------|-------------|
| Product Context | `baachal/baachal.php` | 4-stage keyword search, dynamic term extraction, AI formatting |
| Catalog Operations | `baachal/baachal.php`, `baachal/admin/tabs/search.php` | Admin-configurable search, cache management |
| Cart Operations | `mxchat-basic/includes/class-mxchat-woocommerce.php` | AJAX add-to-cart, order history, cart status |
| Product Cards | `baachal/baachal.php` | Semantic HTML, image+price+link, filter hooks |
| HPOS Compatibility | `baachal/baachal.php` | `FeaturesUtil::declare_compatibility()`, cache invalidation |

**Phase 02 Architecture Decisions Needed**:
- RAG-WooCommerce boundary (per RES-XD-007)
- Hybrid retrieval: keyword + vector (CFI-WOO-001)
- Cart security hardening (CFI-WOO-003)
- Dual exposure: AJAX + tools (per RES-XD-011)

---

## 2. Cross-Domain Resolution Patterns

Six resolution patterns were identified in Phase 01 session 05. These are reusable architecture patterns for Phase 02 module boundary design.

### Pattern 1: Policy-vs-Execution

**Applied in**: RES-XD-001, RES-XD-003, RES-XD-004, RES-XD-005

**Description**: Domain A defines policy/rules/configuration; Domain B executes/applies them in its pipeline.

**Interface contract**: Domain B calls `domain_a_get_policy($context)` at execution time. Domain A returns policy artifacts. Domain B applies them.

**Application guidance for Phase 02**:
- Security defines moderation rules; Runtime executes moderation checks
- Security defines encryption module; Admin calls it for key storage
- Security defines context policies; RAG injects them during assembly
- Security defines session validation; Runtime calls it during session load
- Use this pattern whenever two domains share a behavioral concern but have different roles (definition vs execution)

### Pattern 2: Registry Centralization

**Applied in**: RES-XD-002, RES-XD-008, RES-XD-009

**Description**: A single registry serves as the canonical source of truth. Contributing domains register items; consuming domains query items. No parallel registries.

**Interface contract**: Registry provides `register($item)`, `get_items($filter)`, `remove_by_source($source)`. Contributors call `register()`; consumers call `get_items()`.

**Application guidance for Phase 02**:
- Provider registry is the single registration point for AI providers AND embedding providers
- Tool registry is the single registration point for local tools, MCP tools, AND webhook tools
- Provider adapters normalize tool schemas on consumption (not tools domain normalizing per-provider)
- Use this pattern whenever multiple sources contribute items that are consumed by a single pipeline

### Pattern 3: Natural Interface Boundary

**Applied in**: RES-XD-006

**Description**: Two domains interact through a well-defined read/write interface. No conflict exists -- the interaction is a standard storage-vs-consumption boundary.

**Application guidance for Phase 02**:
- Admin stores API keys (write side); Provider reads API keys (read side)
- The getter/setter interface is the boundary: `set_setting()` encrypts; `get_setting()` decrypts
- Use this pattern when domains have a clear unidirectional data flow

### Pattern 4: Layered Ownership

**Applied in**: RES-XD-007

**Description**: Both domains contribute at different abstraction levels. One handles the generic case; the other handles domain-specific enrichment.

**Interface contract**: Generic domain processes items through its pipeline. Specialized domain provides enrichment callback. Boundary rule: if it requires domain-specific knowledge (e.g., `class_exists('WooCommerce')`), it belongs to the specialized domain.

**Application guidance for Phase 02**:
- RAG indexes products as generic content; WooCommerce enriches results with live price/stock
- WooCommerce provides standalone fallback when RAG infrastructure is not configured
- Use this pattern when a domain-specific module needs to augment a generic pipeline

### Pattern 5: Hosting Pattern

**Applied in**: RES-XD-010

**Description**: Host domain provides the page/surface structure; contributing domain registers panels/tabs/sections within it.

**Interface contract**: Host provides `register_panel($id, $title, $renderer)`. Contributor calls it to add its content.

**Application guidance for Phase 02**:
- Admin diagnostics page hosts security audit panel
- Admin settings pages host per-domain configuration sections
- Use this pattern for shared admin surfaces where multiple domains need representation

### Pattern 6: Dual Exposure

**Applied in**: RES-XD-011

**Description**: Same underlying implementation exposed through multiple interaction patterns for different triggering contexts.

**Interface contract**: Core domain owns the business logic. Multiple surface domains expose it through their respective interaction models (AJAX, tool calling, REST, etc.).

**Application guidance for Phase 02**:
- WooCommerce cart operations exposed as both AJAX endpoints (user-initiated) and tools (AI-initiated)
- Both call the same WooCommerce domain implementation
- Use this pattern when the same operation can be triggered by different actors (user vs AI vs webhook)

---

## 3. Open Items and Integration Points

### High-Priority Carry-Forward Items (18)

These items require architecture decisions in Phase 02 and should be addressed early:

| CFI ID | Domain | Description | Cross-Domain Links |
|--------|--------|-------------|-------------------|
| CFI-PRO-001 | PROVIDER | SSE streaming integration into per-adapter architecture | CFI-RUN-003 |
| CFI-PRO-002 | PROVIDER | Generalize retry/fallback to all adapters | -- |
| CFI-RUN-001 | RUNTIME | Page context injection + handler replacement hook | DEC-PRO-001 |
| CFI-RUN-002 | RUNTIME | Unified session storage design | RES-XD-005 |
| CFI-RUN-003 | RUNTIME | Modular streaming transport layer | CFI-PRO-001 |
| CFI-MIG-001 | MIGRATION | Combined plugin lifecycle with extension hooks | RES-XD-001 |
| CFI-MIG-002 | MIGRATION | Formalized migration registry | -- |
| CFI-RAG-001 | RAG | Merged ingestion pipeline | RES-XD-007 |
| CFI-RAG-003 | RAG | ANN index for scalable retrieval | CFI-WOO-001 |
| CFI-ADM-001 | ADMIN | Admin class decomposition | RES-XD-010 |
| CFI-ADM-002 | ADMIN | API key encryption for all fields | RES-XD-001, CFI-SEC-002 |
| CFI-ADM-004 | ADMIN | Unified operational controls | CFI-WOO-002 |
| CFI-SEC-001 | SECURITY | Unified sanitization library | -- |
| CFI-SEC-002 | SECURITY | API key encryption at rest + migration | RES-XD-001, CFI-ADM-002 |
| CFI-TOL-001 | TOOLS | Unified tool registration architecture | RES-XD-002, RES-XD-011 |
| CFI-TOL-002 | TOOLS | Tool execution lifecycle with hooks | RES-XD-002 |
| CFI-INT-001 | INTEGRATIONS | MCP transport expansion (SSE, stdio) | -- |
| CFI-WOO-003 | WOOCOMMERCE | Validated cart operations | RES-XD-011, CFI-TOL-001 |

### Cross-Domain Integration Points

These are the critical interfaces that Phase 02 architecture must define:

| Integration Point | Domains | Interface Contract | Resolution Pattern |
|------------------|---------|-------------------|-------------------|
| Provider dispatch | RUNTIME -> PROVIDER | `provider_registry->get_provider($slug)->send($messages, $params)` | Natural interface |
| Tool injection | TOOLS -> PROVIDER | `provider_adapter->normalize_tools($canonical_tools)` | Registry centralization |
| Moderation gate | SECURITY -> RUNTIME | `security->check_moderation($message, $context)` | Policy-vs-execution |
| Session validation | SECURITY -> RUNTIME | `security->validate_session_ownership($session)` | Policy-vs-execution |
| Context policies | SECURITY -> RAG | `security->get_context_policies($user_context)` | Policy-vs-execution |
| Key encryption | SECURITY -> ADMIN | `security->encrypt($value)` / `security->decrypt($value)` | Policy-vs-execution |
| Embedding lookup | RAG -> PROVIDER | `provider_registry->get_providers('embedding')` | Registry centralization |
| Product enrichment | WOOCOMMERCE -> RAG | `wc->enrich_product_context($results)` | Layered ownership |
| Cart as tool | WOOCOMMERCE -> TOOLS | `tool_registry->register($wc_cart_tools)` | Dual exposure |
| Tool source tracking | INTEGRATIONS -> TOOLS | `tool_registry->remove_by_source($type, $id)` | Registry centralization |
| Diagnostics panels | SECURITY -> ADMIN | `admin->register_diagnostic_panel($id, $title, $renderer)` | Hosting pattern |
| Extension lifecycle | INTEGRATIONS -> TOOLS | Cascade delete on source removal | Registry centralization |

### Risk Areas for Phase 02

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Monolithic admin decomposition | High -- 9036-line class must be split without breaking admin UX | Incremental extraction: one page controller at a time |
| Session storage migration | High -- moving from wp_options to dedicated table affects all 3 sources | Design migration framework first (CFI-MIG-002), then session table |
| jQuery to vanilla JS rewrite | Medium -- 3824 lines of admin JS must be rewritten | Phase 02 can maintain jQuery initially; vanilla rewrite in Phase 03 |
| ANN index decision | Medium -- SQLite VSS vs HNSW vs mandatory Pinecone | Benchmark with real data; defer final decision until RAG module contract |
| Cart security hardening | Medium -- lowest-scored capability (3.15) with known gaps | Implement validation checks before exposing cart operations |
| MCP transport expansion | Low -- HTTP-only current state works; SSE/stdio can be phased | Ship HTTP transport first; add SSE/stdio as incremental transport modules |
