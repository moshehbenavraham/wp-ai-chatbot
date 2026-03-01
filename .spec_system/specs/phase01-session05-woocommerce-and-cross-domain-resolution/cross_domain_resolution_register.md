# Cross-Domain Conflict Resolution Register

**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Sources**: Cross-domain interaction index, all domain decision logs (sessions 01-05)
**Created**: 2026-03-01

---

## Register Summary

| Resolution ID | Conflict | Domains | Severity | Status |
|--------------|---------|---------|----------|--------|
| RES-XD-001 | API Key Encryption Ownership | ADMIN, SECURITY | medium | resolved |
| RES-XD-002 | Tool Schema Normalization | TOOLS, PROVIDER | high | resolved |
| RES-XD-003 | Security Policy Injection in Context Assembly | SECURITY, RAG | medium | resolved |
| RES-XD-004 | Moderation Pipeline Ownership | SECURITY, RUNTIME | low | resolved |
| RES-XD-005 | Session Ownership Validation | RUNTIME, SECURITY | low | resolved |
| RES-XD-006 | Admin Key Management vs Provider Key Storage | ADMIN, PROVIDER | low | resolved |
| RES-XD-007 | WooCommerce Product Context vs RAG Content Indexing | WOOCOMMERCE, RAG | medium | resolved |
| RES-XD-008 | Embedding Provider Registration | RAG, PROVIDER | medium | resolved |
| RES-XD-009 | Tool Registration Source Tracking | TOOLS, INTEGRATIONS | medium | resolved |
| RES-XD-010 | Admin Diagnostics vs Security Audit Dashboard | ADMIN, SECURITY | low | resolved |
| RES-XD-011 | WooCommerce Cart as Tool or Runtime Endpoint | WOOCOMMERCE, TOOLS, RUNTIME | medium | resolved |

**Total conflicts**: 11
**Resolved**: 11
**Deferred**: 0
**Unresolved**: 0

---

## T010: Conflict Catalog

### Conflicts Identified from Interaction Index

7 conflict candidates identified in `cross_domain_interaction_index.md` (CC-001 through CC-007). Additional conflicts CC-008 through CC-011 identified during resolution analysis.

---

## T011: Provider-Runtime-Security-Migration Boundary Conflicts

### RES-XD-001: API Key Encryption Ownership

**Conflict ID**: CC-001
**Domains**: DOM-ADMIN (DEC-ADM-002) vs DOM-SECURITY (DEC-SEC-002)
**Severity**: medium

**Description**: Both DEC-ADM-002 (admin option management) and DEC-SEC-002 (privacy controls) claim API key encryption at rest (AES-256-GCM) as their domain responsibility. DEC-ADM-002 frames it as secure option storage; DEC-SEC-002 frames it as privacy control. Both reference the same SRC-AXI implementation.

**Resolution**: **Split responsibility with security as owner.**
- **DOM-SECURITY owns the encryption module**: The `aichat_encrypt()` / `aichat_decrypt()` functions, key derivation, IV generation, and cryptographic implementation belong to the security domain. This is a security primitive.
- **DOM-ADMIN owns the storage interface**: The admin option management layer calls security encryption/decryption transparently when storing/retrieving API keys. Admin knows *which* fields are sensitive; security knows *how* to protect them.
- **Interface contract**: Admin calls `security_encrypt($value)` on save and `security_decrypt($value)` on load. Security domain provides the implementation.

**Rationale**: Encryption is a security concern by nature. Admin is the consumer, not the owner, of cryptographic operations. This aligns with the principle of least responsibility and prevents admin code from containing cryptographic implementation details.

**Affected Decisions**: DEC-ADM-002 (admin stores encrypted values), DEC-SEC-002 (security provides encryption), CFI-ADM-002 and CFI-SEC-002 (both reference encryption migration).

**Winner Impact**: None -- both domain winners unchanged. This is an interface clarification.

---

### RES-XD-002: Tool Schema Normalization

**Conflict ID**: CC-002
**Domains**: DOM-TOOLS (DEC-TOL-001) vs DOM-PROVIDER (DEC-PRO-002)
**Severity**: high

**Description**: Tool registration API (DEC-TOL-001) produces JSON Schema tool definitions. Provider adapters (DEC-PRO-002) consume tools in provider-specific formats (OpenAI function calling, Claude tool use, Gemini function declarations). Schema normalization could live in either domain:
- **Option A**: Tools domain pre-normalizes for each provider (tools know about providers).
- **Option B**: Provider adapters normalize on consumption (providers know about tool schema).

**Resolution**: **Option B -- Provider adapters normalize on consumption.**
- **DOM-TOOLS owns the canonical tool schema**: Tools register with a single, provider-agnostic JSON Schema format. The tool registration API (DEC-TOL-001) produces one canonical shape.
- **DOM-PROVIDER owns per-provider normalization**: Each provider adapter transforms the canonical tool schema into its required format (OpenAI functions, Claude tools, Gemini declarations) at dispatch time.
- **Interface contract**: Tools provide `get_tools_for_context($bot_id)` returning canonical schemas. Provider adapters call `normalize_tools($canonical_tools)` internally before API dispatch.

**Rationale**: Tools should not have knowledge of individual provider formats (violates single responsibility). Provider adapters already handle provider-specific API differences (request format, auth, streaming). Adding tool schema normalization to adapters is a natural extension. This also supports future providers without modifying the tools domain.

**Affected Decisions**: DEC-TOL-001 (canonical schema only), DEC-TOL-002 (execution engine receives canonical), DEC-PRO-002 (adapters add normalization responsibility).

**Winner Impact**: None -- both domain winners (SRC-AXI for both) are unchanged. SRC-AXI's existing implementation already partially follows this pattern (provider-specific tool injection in adapters).

---

### RES-XD-004: Moderation Pipeline Ownership

**Conflict ID**: CC-004
**Domains**: DOM-SECURITY (DEC-SEC-003) vs DOM-RUNTIME (DEC-RUN-001)
**Severity**: low

**Description**: Content moderation (honeypot, rate limiting, spam signatures, banned words, OpenAI Moderation API) is scored as a security capability (DEC-SEC-003: adopt_as_is, 4.50), but moderation executes within the runtime message lifecycle pre-processing chain (DEC-RUN-001).

**Resolution**: **Security defines policy; runtime executes pipeline.**
- **DOM-SECURITY owns moderation policy**: The moderation rules (banned word lists, spam signatures, rate limit thresholds, moderation API configuration) are security-domain concerns.
- **DOM-RUNTIME owns the execution pipeline**: The message lifecycle pre-processing chain that invokes moderation checks in sequence is a runtime concern.
- **Interface contract**: Security provides `check_moderation($message, $context)` callable. Runtime invokes it as a pre-processing step in the message pipeline.

**Rationale**: This is the standard policy-vs-execution separation. Security expertise defines what to block; runtime orchestration determines when and how to check. Neither domain's winner changes.

**Affected Decisions**: DEC-SEC-003 (security policy, unchanged), DEC-RUN-001 (runtime pipeline, unchanged).

**Winner Impact**: None.

---

### RES-XD-005: Session Ownership Validation

**Conflict ID**: CC-005
**Domains**: DOM-RUNTIME (DEC-RUN-002) vs DOM-SECURITY
**Severity**: low

**Description**: Session ownership validation (IP/user binding, automatic reset on mismatch) is scored as a runtime capability (DEC-RUN-002, SRC-MXC winner) but is fundamentally a security control against session hijacking.

**Resolution**: **Runtime owns session handler; security validates ownership.**
- **DOM-RUNTIME owns the session lifecycle**: Session creation, persistence, loading, and cleanup are runtime concerns (DEC-RUN-002).
- **DOM-SECURITY provides the ownership validation check**: The IP/user binding check and anti-hijacking logic are security primitives. Runtime calls `security_validate_session_ownership($session)` during session load.
- **Interface contract**: Runtime calls security validation on session load. Security returns pass/fail + recommended action (reset, block, warn).

**Rationale**: The session handler is a runtime component, but the security validation it performs is a security concern. This follows the same policy-vs-execution pattern as RES-XD-004.

**Affected Decisions**: DEC-RUN-002 (session handler, unchanged), DEC-SEC-001 (input validation, unchanged).

**Winner Impact**: None.

---

### RES-XD-006: Admin Key Management vs Provider Key Storage

**Conflict ID**: CC-006
**Domains**: DOM-ADMIN (DEC-ADM-002) vs DOM-PROVIDER (DEC-PRO-002)
**Severity**: low

**Description**: API key management (storage, validation, connectivity testing) crosses admin and provider boundaries. Admin stores and encrypts keys; provider adapters consume and decrypt for API calls.

**Resolution**: **Natural interface boundary -- no conflict.**
- **DOM-ADMIN owns key persistence**: Stores API keys in `wp_options` with encryption (via security module per RES-XD-001). Provides admin UI for key entry, validation, and connectivity testing.
- **DOM-PROVIDER owns key consumption**: Provider adapters retrieve keys via `aichat_get_setting()` (which auto-decrypts). Provider adapters use keys for API authentication.
- **Interface contract**: `aichat_get_setting($key)` returns decrypted value. Admin stores via `aichat_set_setting($key, $value)` which encrypts.

**Rationale**: This is a natural storage-vs-consumption boundary. Admin is the write side; provider is the read side. No actual conflict exists -- the interaction is a well-defined interface.

**Affected Decisions**: No changes needed.

**Winner Impact**: None.

---

## T012: RAG-Tools-Integrations Boundary Conflicts

### RES-XD-003: Security Policy Injection in Context Assembly

**Conflict ID**: CC-003
**Domains**: DOM-SECURITY (DEC-SEC-003) vs DOM-RAG (DEC-RAG-004)
**Severity**: medium

**Description**: Security policies (content restrictions, role-based access rules, template token allowlists) are injected into the RAG context assembly step. DEC-RAG-004 (context assembly, SRC-AXI, 4.65) scores this injection as RAG coverage. DEC-SEC-003 (content moderation, SRC-AXI, 4.50) references it as security coverage.

**Resolution**: **Security defines policies; RAG applies them during assembly.**
- **DOM-SECURITY owns policy definitions**: Security role restrictions, content access rules, template token allowlists, and security injection templates are security-domain artifacts.
- **DOM-RAG owns the assembly pipeline**: The context assembly step (DEC-RAG-004) applies security policies as part of its assembly logic. RAG knows where to inject; security knows what to inject.
- **Interface contract**: RAG calls `security_get_context_policies($user_context)` during assembly. Security returns policy fragments to inject into the assembled context.

**Rationale**: Consistent with RES-XD-004 and RES-XD-005 (policy-vs-execution pattern). The assembly pipeline is RAG's responsibility; the policy content is security's.

**Affected Decisions**: DEC-RAG-004 (assembly applies policies), DEC-SEC-003 (security provides policies). Scores unchanged for both.

**Winner Impact**: None.

---

### RES-XD-008: Embedding Provider Registration

**Conflict ID**: CC-008 (identified during resolution analysis)
**Domains**: DOM-RAG (DEC-RAG-005) vs DOM-PROVIDER (DEC-PRO-001)
**Severity**: medium

**Description**: Embedding providers (OpenAI, Voyage AI, etc.) generate vectors for RAG indexing and retrieval. DEC-RAG-005 scores embedding integration as a RAG capability. DEC-PRO-001 defines the provider registry. Should embedding providers register through the general provider registry or through a RAG-specific embedding registry?

**Resolution**: **Embedding providers register through the provider registry with an `embedding` capability flag.**
- **DOM-PROVIDER owns the registry**: The provider registry (DEC-PRO-001) is the single registration point for all external AI services, including embedding providers. Embedding providers register with a `capabilities: ['embedding']` flag.
- **DOM-RAG owns embedding consumption**: RAG queries the provider registry for `embedding`-capable providers and uses them for vector generation.
- **Interface contract**: `provider_registry->get_providers('embedding')` returns embedding-capable providers. RAG calls `provider->generate_embedding($text)` via the standard adapter interface.

**Rationale**: Maintaining a single provider registry avoids duplicate registration and config surfaces. The provider registry already handles multi-provider scenarios. Adding an embedding capability flag is minimal overhead.

**Affected Decisions**: DEC-PRO-001 (registry adds capability flags), DEC-RAG-005 (RAG consumes via registry).

**Winner Impact**: None -- registry extension is additive.

---

### RES-XD-009: Tool Registration Source Tracking

**Conflict ID**: CC-009 (identified during resolution analysis)
**Domains**: DOM-TOOLS (DEC-TOL-001, DEC-TOL-003) vs DOM-INTEGRATIONS (DEC-INT-001, DEC-INT-002)
**Severity**: medium

**Description**: MCP tools register via the macro system with `source=mcp`. Webhook tools use `source=webhook`. Cascade delete on integration source removal. Both tools and integrations domains reference this source tracking -- tools for lifecycle management, integrations for source lifecycle.

**Resolution**: **Tools owns the registry; integrations owns the sources.**
- **DOM-TOOLS owns the tool/macro registry**: All tools (local, MCP, webhook) are registered in a single registry with source metadata. Cascade delete logic lives in the registry.
- **DOM-INTEGRATIONS owns source lifecycle**: When an MCP server is disconnected or a webhook is removed, integrations notifies the tool registry to cascade-delete tools from that source.
- **Interface contract**: Integrations calls `tool_registry->remove_by_source($source_type, $source_id)` on source removal. Tools handles the cascade.

**Rationale**: The tool registry is the single source of truth for tool definitions. Integrations manages the external sources that contribute tools. This avoids the registry having knowledge of integration lifecycle details.

**Affected Decisions**: DEC-TOL-003 (macro registry, source tracking unchanged), DEC-INT-001 (MCP client, source lifecycle unchanged).

**Winner Impact**: None.

---

## T013: Admin-WooCommerce and Remaining Cross-Domain Conflicts

### RES-XD-007: WooCommerce Product Context vs RAG Content Indexing

**Conflict ID**: CC-007
**Domains**: DOM-WOOCOMMERCE (DEC-WOO-001) vs DOM-RAG (DEC-RAG-001, DEC-RAG-003)
**Severity**: medium

**Description**: SRC-AXI indexes WooCommerce products via the RAG pipeline (product indexing as part of content ingestion). SRC-BAA has an independent keyword-based product search (not RAG-dependent). The boundary between "product context for chat" (WooCommerce domain) and "content indexing" (RAG domain) must be defined for the unified system.

**Resolution**: **Layered ownership -- RAG indexes; WooCommerce enriches and specializes.**
- **DOM-RAG owns content indexing pipeline**: Products, when indexed, flow through the standard RAG ingestion pipeline (DEC-RAG-001) like any other content type. RAG handles chunking, embedding, and vector storage.
- **DOM-WOOCOMMERCE owns product-specific operations**: WC-specific search (keyword-based, attribute search, taxonomy search), product data enrichment (live price/stock/SKU), product card rendering, and commerce operations (cart, orders) belong to the WooCommerce domain.
- **Interface contract**:
  - RAG indexes products as content (using WC-aware indexing options from DEC-WOO-002).
  - WooCommerce provides `enrich_product_context($results)` to add live WC data to RAG results containing products.
  - WooCommerce provides standalone product search as a fallback when RAG infrastructure is not configured.
- **Boundary rule**: If a feature requires `class_exists('WooCommerce')`, it belongs to DOM-WOOCOMMERCE. If it works with any content type (pages, posts, products), it belongs to DOM-RAG.

**Rationale**: Products are both content (indexable, searchable) and commerce objects (with price, stock, cart operations). RAG treats them as content; WooCommerce treats them as commerce objects. The enrichment layer bridges both without duplication.

**Affected Decisions**: DEC-RAG-001 (indexes products as content), DEC-WOO-001 (provides enrichment and standalone search), CFI-WOO-001 (hybrid retrieval design).

**Winner Impact**: None -- both domain winners support this layered approach.

---

### RES-XD-010: Admin Diagnostics vs Security Audit Dashboard

**Conflict ID**: CC-010 (identified during resolution analysis)
**Domains**: DOM-ADMIN (DEC-ADM-003) vs DOM-SECURITY (DEC-SEC-005)
**Severity**: low

**Description**: DEC-ADM-003 calls for a unified diagnostics dashboard (streaming test, analytics, log viewer). DEC-SEC-005 calls for audit logging integration with structured logger and an admin security dashboard. Both reference admin-surface dashboards with overlapping logging concerns.

**Resolution**: **Single admin diagnostics page with security tab.**
- **DOM-ADMIN owns the diagnostics page structure**: The admin diagnostics page (DEC-ADM-003) provides the page skeleton, tab navigation, and general diagnostics (connectivity tests, analytics, system info).
- **DOM-SECURITY provides a security tab/panel**: Security audit logs, security event categories, and security-specific metrics render as a tab or panel within the admin diagnostics page. Security registers its panel via the diagnostic panel registration hook (DEC-ADM-003 modification).
- **Interface contract**: Admin provides `register_diagnostic_panel($id, $title, $renderer)`. Security calls it to add its audit log panel.

**Rationale**: One admin page is better than two overlapping ones. The diagnostic panel registration hook (already planned in DEC-ADM-003) supports extensibility by security and other domains.

**Affected Decisions**: DEC-ADM-003 (diagnostics page hosts security panel), DEC-SEC-005 (security provides panel content).

**Winner Impact**: None.

---

### RES-XD-011: WooCommerce Cart as Tool or Runtime Endpoint

**Conflict ID**: CC-011 (identified during resolution analysis)
**Domains**: DOM-WOOCOMMERCE (DEC-WOO-003) vs DOM-TOOLS (DEC-TOL-001) vs DOM-RUNTIME (DEC-RUN-001)
**Severity**: medium

**Description**: WooCommerce cart operations (add to cart, order history, checkout) could be implemented as:
- **Option A**: AJAX endpoints in the runtime message pipeline (current SRC-MXC approach)
- **Option B**: Registered tools via the tool registration API (DEC-TOL-001), invoked by the AI model via function calling
- **Option C**: Both -- AJAX for direct user actions, tools for AI-initiated actions

**Resolution**: **Option C -- Dual exposure.**
- **DOM-WOOCOMMERCE owns the cart operation implementations**: The actual `add_to_cart()`, `get_orders()`, `cart_status()` logic lives in the WooCommerce domain.
- **DOM-TOOLS exposes cart operations as AI tools**: Cart operations register as tools via DEC-TOL-001, allowing AI models to invoke them through function calling.
- **DOM-RUNTIME exposes cart operations as AJAX endpoints**: Direct chat-UI actions (e.g., "Add to Cart" button on product card) use AJAX endpoints in the runtime pipeline.
- **Both call the same WooCommerce domain implementation**: No duplication of business logic.

**Rationale**: AI-initiated actions (model decides to add product to cart based on conversation) and user-initiated actions (clicking "Add to Cart" on a product card) are both valid interaction patterns. Using the same underlying implementation ensures consistent behavior.

**Affected Decisions**: DEC-WOO-003 (cart operations), DEC-TOL-001 (tool registration for WC operations), DEC-RUN-001 (AJAX endpoints).

**Winner Impact**: None -- additive capability.

---

## Resolution Status Summary

| Status | Count |
|--------|-------|
| Resolved | 11 |
| Deferred | 0 |
| Unresolved | 0 |

### Resolution Patterns Applied

1. **Policy-vs-Execution** (RES-XD-001, RES-XD-003, RES-XD-004, RES-XD-005): Domain A defines policy/rules; Domain B executes/applies them. Clean interface contract.
2. **Registry Centralization** (RES-XD-002, RES-XD-008, RES-XD-009): Single registry with capability flags rather than parallel domain-specific registries.
3. **Natural Interface Boundary** (RES-XD-006): No actual conflict -- well-defined read/write interface.
4. **Layered Ownership** (RES-XD-007): Both domains contribute at different abstraction levels (content vs. commerce).
5. **Hosting Pattern** (RES-XD-010): Host domain provides page structure; contributing domain registers panels/tabs.
6. **Dual Exposure** (RES-XD-011): Same implementation exposed through multiple interaction patterns.

### No Winner Changes

All 11 resolutions clarify domain boundaries and interface contracts without changing any domain winner selections from sessions 01-05. The conflict-free domain winner map can proceed with the original winners.
