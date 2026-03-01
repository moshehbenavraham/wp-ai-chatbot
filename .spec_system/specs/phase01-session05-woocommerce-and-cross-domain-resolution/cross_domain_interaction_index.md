# Cross-Domain Interaction Index

**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Sources**: Decision logs from sessions 01-04
**Domains Covered**: All 9 (PROVIDER, RUNTIME, MIGRATION, RAG, ADMIN, SECURITY, TOOLS, INTEGRATIONS, WOOCOMMERCE)
**Created**: 2026-03-01

---

## Index Summary

| Interaction Pair | Source Session(s) | Notes Count | Conflict Potential |
|-----------------|-------------------|-------------|-------------------|
| PROVIDER x RUNTIME | S01, S02 | 4 | medium |
| PROVIDER x SECURITY | S01, S04 | 3 | low |
| PROVIDER x ADMIN | S01, S03 | 3 | low |
| PROVIDER x MIGRATION | S01, S02 | 2 | low |
| PROVIDER x TOOLS | S04 | 2 | medium |
| PROVIDER x INTEGRATIONS | S04 | 2 | low |
| RUNTIME x SECURITY | S02, S04 | 4 | medium |
| RUNTIME x ADMIN | S02, S03 | 3 | low |
| RUNTIME x MIGRATION | S02 | 2 | low |
| RUNTIME x TOOLS | S04 | 2 | medium |
| MIGRATION x ADMIN | S02, S03 | 2 | low |
| MIGRATION x SECURITY | S02 | 2 | low |
| MIGRATION x PROVIDER | S02 | 1 | low |
| RAG x PROVIDER | S03 | 3 | medium |
| RAG x ADMIN | S03 | 8 | medium |
| RAG x SECURITY | S03, S04 | 4 | medium |
| ADMIN x PROVIDER | S03 | 4 | low |
| ADMIN x RUNTIME | S03 | 4 | low |
| ADMIN x SECURITY | S03, S04 | 5 | medium |
| SECURITY x TOOLS | S04 | 3 | medium |
| SECURITY x INTEGRATIONS | S04 | 4 | medium |
| TOOLS x RUNTIME | S04 | 2 | low |
| TOOLS x PROVIDER | S04 | 3 | high |
| TOOLS x INTEGRATIONS | S04 | 3 | medium |
| INTEGRATIONS x ADMIN | S04 | 3 | low |

**Total interaction pairs**: 25
**High conflict potential**: 1
**Medium conflict potential**: 11
**Low conflict potential**: 13

---

## Session 01: DOM-PROVIDER Cross-Domain Notes

### CDI-S01-001: PROVIDER x RUNTIME
**Source**: DEC-PRO-001, DEC-PRO-002
**Description**: Provider adapters invoked from runtime pipeline via `AIChat_Provider_Interface`. Streaming bridges provider and runtime -- SSE headers, chunked output, and provider-specific parsing all require coordination between provider adapter implementation and runtime message lifecycle.
**Implication**: Runtime (session 02) must honor adapter interface when designing message pipeline. Streaming (DEC-RUN-003) depends on per-adapter streaming endpoints (CFI-PRO-001).

### CDI-S01-002: PROVIDER x SECURITY
**Source**: DEC-PRO-002, DEC-PRO-004
**Description**: API key validation and proxy fallback in adapters touch security. Claude adapter persists tool state to DB via `$wpdb->prepare()`. Error responses must not expose API keys, internal paths, or stack traces.
**Implication**: Security domain (session 04) must account for provider-level key handling. API key encryption (DEC-SEC-002/DEC-ADM-002) affects provider registry key retrieval.

### CDI-S01-003: PROVIDER x ADMIN
**Source**: DEC-PRO-003
**Description**: Model registry drives admin UI model selection dropdowns. Provider API key management crosses into provider domain for validation and connectivity testing.
**Implication**: Admin domain (session 03) UI depends on provider model registry data. Agency proxy config is admin-configured, provider-consumed.

### CDI-S01-004: PROVIDER x MIGRATION
**Source**: DEC-PRO-003
**Description**: Model registry contains alias and deprecated mappings that support migration. Fallback chains provide runtime migration path when models retire. Provider API key storage format changes must go through migration framework.
**Implication**: Migration domain must handle model alias changes and provider key format changes.

---

## Session 02: DOM-RUNTIME and DOM-MIGRATION Cross-Domain Notes

### CDI-S02-001: RUNTIME x PROVIDER
**Source**: DEC-RUN-001, DEC-RUN-003
**Description**: Runtime dispatches to provider adapters via registry (DEC-PRO-001). Streaming requires coordination with per-adapter streaming endpoints (CFI-PRO-001). Tool-pending handshake requires runtime to detect tool state and manage continuation flow.
**Implication**: Strong bidirectional dependency. Runtime pipeline architecture must be adapter-interface-aware.

### CDI-S02-002: RUNTIME x SECURITY
**Source**: DEC-RUN-001, DEC-RUN-002
**Description**: Session ownership validation (DEC-RUN-002) is a security control (IP/user binding, automatic reset). Nonce verification, input/output sanitization are runtime-embedded security controls. Rate limiting hooks connect to `lib/rate-limiter.php`.
**Implication**: Security controls are embedded in runtime, not separate. Session hijacking prevention is joint runtime-security concern.

### CDI-S02-003: RUNTIME x ADMIN
**Source**: DEC-RUN-001, DEC-RUN-004
**Description**: Widget config flows from admin to frontend via `wp_localize_script()`. Conversation logging produces data for admin dashboards. Bot-specific options are admin-configured but runtime-consumed.
**Implication**: Admin settings drive runtime behavior. Per-bot config boundary is admin-runtime interface.

### CDI-S02-004: RUNTIME x MIGRATION
**Source**: DEC-RUN-002
**Description**: Session storage format changes create migration requirements. Streaming addition changes frontend asset requirements.
**Implication**: Session schema changes must be migration-managed. Streaming is net-new capability (no migration from prior state).

### CDI-S02-005: MIGRATION x RUNTIME
**Source**: DEC-MIG-002, DEC-MIG-003
**Description**: Session storage format (runtime) determines migration scope. Streaming state must survive upgrades.
**Implication**: Bidirectional with CDI-S02-004. Migration registry must know runtime schema versions.

### CDI-S02-006: MIGRATION x PROVIDER
**Source**: DEC-MIG-003
**Description**: Model registry (DEC-PRO-003) supports migration via alias/deprecated mappings. Provider API key storage format changes must go through migration framework. Deprecated model mapping across providers.
**Implication**: Provider model changes trigger migration entries. Key format changes are migration-critical.

### CDI-S02-007: MIGRATION x ADMIN
**Source**: DEC-MIG-001, DEC-MIG-003
**Description**: Admin settings are primary target of option migrations. Data preservation flag is admin-configured. Cron lifecycle creates scheduled admin operations.
**Implication**: Admin option names/formats must be migration-stable or explicitly migrated.

### CDI-S02-008: MIGRATION x SECURITY
**Source**: DEC-MIG-001
**Description**: Uninstall must ensure complete cleanup of sensitive data (API keys, conversations) when user opts out of preservation. Migration routines touching security options must preserve existing values. GDPR compliance requires full personal data removal capability.
**Implication**: Migration-security interaction is critical for GDPR. Uninstall cleanup scope depends on data preservation flag.

---

## Session 03: DOM-RAG and DOM-ADMIN Cross-Domain Notes

### CDI-S03-001: RAG x PROVIDER
**Source**: DEC-RAG-004, DEC-RAG-005
**Description**: Embedding generation depends on provider APIs. Context assembly references MCP tools. Agency proxy bridges embedding calls. Embedding provider architecture must align with provider registry (DEC-PRO-001).
**Implication**: RAG embedding is a provider-consuming capability. Provider registry must support embedding-specific providers (not just chat providers).

### CDI-S03-002: RAG x ADMIN (8 interaction points)
**Source**: DEC-RAG-001 through DEC-RAG-005
**Description**:
1. Ingestion triggers (admin initiates indexing)
2. Index status and monitoring (admin dashboard)
3. Chunking configuration (admin settings)
4. Retrieval tuning (admin configurable)
5. Embedding provider selection (admin UI)
6. Knowledge entry management (admin CRUD)
7. Auto-sync controls (admin toggle)
8. Security policy injection (admin-defined, RAG-consumed)
**Implication**: RAG is the most admin-coupled domain. Admin decomposition (CFI-ADM-001) must include RAG management pages.

### CDI-S03-003: RAG x SECURITY
**Source**: DEC-RAG-003, DEC-RAG-004
**Description**: Security policy injection into context assembly. Template token allowlist registry prevents arbitrary function execution. Role-based content access in retrieval. Pinecone endpoint sanitization.
**Implication**: RAG context assembly is a security enforcement point. Security policy injection (DEC-SEC-003) overlaps with RAG context assembly (DEC-RAG-004).

### CDI-S03-004: ADMIN x PROVIDER
**Source**: DEC-ADM-002, DEC-ADM-004
**Description**: API key management (storage, validation, connectivity testing). Model selection UIs depend on provider registry. Provider switching in admin. Agency proxy configuration.
**Implication**: Admin key encryption (DEC-ADM-002) must be transparent to provider registry key retrieval.

### CDI-S03-005: ADMIN x RUNTIME
**Source**: DEC-ADM-004, DEC-ADM-005
**Description**: Chat configuration, usage limits enforcement, per-role rate limits, post type visibility, email gating. All admin-configured, runtime-enforced.
**Implication**: Admin is the configuration source; runtime is the enforcement point.

### CDI-S03-006: ADMIN x SECURITY
**Source**: DEC-ADM-002, DEC-ADM-004
**Description**: API key encryption at rest (AES-256-GCM). Embed origins whitelist. Content moderation configuration. GDPR consent overlay. Security policy customization. Capability checks on all admin endpoints.
**Implication**: Admin and security share encryption responsibility. API key encryption appears in both DEC-ADM-002 and DEC-SEC-002 -- potential ownership conflict.

---

## Session 04: DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS Cross-Domain Notes

### CDI-S04-001: SECURITY x PROVIDER
**Source**: DEC-SEC-001, DEC-SEC-002
**Description**: API key encryption affects provider registry key retrieval. Provider-specific tool injection filters pass through security validation.
**Implication**: Security encryption must be transparent to provider adapters. Same concern as CDI-S03-004.

### CDI-S04-002: SECURITY x RUNTIME
**Source**: DEC-SEC-001, DEC-SEC-003
**Description**: Moderation pipeline is primary runtime security gate. Rate limiting per session/bot. Input sanitization is first security layer in runtime pipeline.
**Implication**: Security moderation is embedded in runtime message lifecycle (DEC-RUN-001). Not separable.

### CDI-S04-003: SECURITY x RAG
**Source**: DEC-SEC-001
**Description**: Context assembly security policy injection. Template token allowlist registry. Role-based content access. Pinecone endpoint sanitization.
**Implication**: Overlaps with CDI-S03-003. Security policy ownership must be clarified.

### CDI-S04-004: SECURITY x ADMIN
**Source**: DEC-SEC-002, DEC-SEC-004
**Description**: Capability enforcement on all admin endpoints. Privacy/usage limit config needs admin UI. Audit dashboard is admin feature. Hook parameter filtering.
**Implication**: Security features need admin surfaces. Admin must enforce security's capability requirements.

### CDI-S04-005: SECURITY x TOOLS
**Source**: DEC-SEC-001, DEC-SEC-003
**Description**: Tool call structure validation is a security gate. Per-bot capability settings control tool access. Tool execution passes through moderation and rate limiting gates.
**Implication**: Security validates tool calls before execution. Tools domain depends on security for access control.

### CDI-S04-006: SECURITY x INTEGRATIONS
**Source**: DEC-SEC-001
**Description**: SSRF protection for webhooks. MCP server auth validation. External payload sanitization. Webhook execution logging as audit trail.
**Implication**: Integrations must pass security validation for all external connections.

### CDI-S04-007: TOOLS x RUNTIME
**Source**: DEC-TOL-001, DEC-TOL-002
**Description**: Tool execution happens within runtime message lifecycle. Tool-pending handshake creates multi-turn flow in runtime pipeline.
**Implication**: Tools are runtime-embedded. Tool execution engine is invoked by runtime dispatcher.

### CDI-S04-008: TOOLS x PROVIDER
**Source**: DEC-TOL-001, DEC-TOL-002
**Description**: Provider-specific tool injection depends on provider adapter interface (DEC-PRO-001). Schema normalization is critical -- varies by provider (OpenAI function calling, Claude tool use, Gemini function declarations).
**Implication**: **HIGH CONFLICT POTENTIAL**. Tool schema normalization must align with provider adapter contracts. Tool registration API (DEC-TOL-001) must produce schemas compatible with each provider's tool format.

### CDI-S04-009: TOOLS x INTEGRATIONS
**Source**: DEC-TOL-001, DEC-TOL-003
**Description**: MCP tools registered via macro system with `source=mcp`. Webhook tools use `source=webhook`. Cascade delete on integration source removal.
**Implication**: Tools and integrations share tool registration. Source tracking in macro registry bridges both domains.

### CDI-S04-010: INTEGRATIONS x ADMIN
**Source**: DEC-INT-003
**Description**: Extension marketplace is an admin page. MCP server config and webhook management need admin CRUD. Extension health monitoring in admin.
**Implication**: Integrations surfaces are admin pages. Admin decomposition must include integrations management.

### CDI-S04-011: INTEGRATIONS x PROVIDER
**Source**: DEC-INT-001
**Description**: MCP tools consumed via provider tool-calling protocol. External AI services are integration-level providers. Webhook metadata includes provider context.
**Implication**: MCP tool consumption bridges integrations and provider domains via tool-calling protocol.

---

## Interaction Heatmap

Domains sorted by interaction count (incoming + outgoing):

| Domain | Interactions | Primary Partners |
|--------|-------------|-----------------|
| ADMIN | 14 | RAG (8), SECURITY (5), PROVIDER (4), RUNTIME (4) |
| SECURITY | 13 | ADMIN (5), RUNTIME (4), RAG (4), TOOLS (3) |
| RUNTIME | 11 | PROVIDER (4), SECURITY (4), ADMIN (3), TOOLS (2) |
| PROVIDER | 10 | RUNTIME (4), ADMIN (3), SECURITY (3), TOOLS (3) |
| RAG | 9 | ADMIN (8), SECURITY (4), PROVIDER (3) |
| TOOLS | 8 | PROVIDER (3), SECURITY (3), INTEGRATIONS (3), RUNTIME (2) |
| MIGRATION | 7 | ADMIN (2), SECURITY (2), RUNTIME (2), PROVIDER (1) |
| INTEGRATIONS | 7 | SECURITY (4), ADMIN (3), TOOLS (3), PROVIDER (2) |
| WOOCOMMERCE | 0 | (to be scored in this session) |

---

## Identified Conflict Candidates

### CC-001: API Key Encryption Ownership
**Domains**: ADMIN (DEC-ADM-002) vs SECURITY (DEC-SEC-002)
**Issue**: Both decision logs claim API key encryption at rest as their domain. DEC-ADM-002 places it in admin option management. DEC-SEC-002 places it in privacy controls. Both reference AES-256-GCM.
**Severity**: medium -- implementation is the same, but ownership affects where the code lives.

### CC-002: Tool Schema Normalization
**Domains**: TOOLS (DEC-TOL-001) vs PROVIDER (DEC-PRO-002)
**Issue**: Tool registration API produces JSON Schema (tools domain), but providers consume tool definitions in provider-specific formats. Schema normalization could live in tool registration (pre-normalize for all providers) or in provider adapters (normalize on consumption).
**Severity**: high -- affects API contract design between tool registration and provider dispatch.

### CC-003: Security Policy Injection in Context Assembly
**Domains**: SECURITY (DEC-SEC-003) vs RAG (DEC-RAG-004)
**Issue**: Security policies injected into RAG context assembly. Both domains reference this as their responsibility. DEC-RAG-004 scores it as RAG coverage; DEC-SEC-003 scores it as security coverage.
**Severity**: medium -- both sides are correct; the injection point is in RAG, the policy definition is in security.

### CC-004: Moderation Pipeline Ownership
**Domains**: SECURITY (DEC-SEC-003) vs RUNTIME (DEC-RUN-001)
**Issue**: Content moderation (honeypot, rate limiting, spam, banned words, OpenAI Moderation API) is scored as security capability, but the execution happens within runtime message lifecycle pre-processing.
**Severity**: low -- natural split: security defines policy, runtime executes pipeline.

### CC-005: Session Ownership Validation
**Domains**: RUNTIME (DEC-RUN-002) vs SECURITY
**Issue**: Session ownership validation (IP/user binding, automatic reset) is scored as runtime capability but is fundamentally a security control against session hijacking.
**Severity**: low -- scored in runtime because it's embedded in session handler. Security domain references it as cross-domain note.

### CC-006: Admin Key Management vs Provider Key Storage
**Domains**: ADMIN (DEC-ADM-002) vs PROVIDER (DEC-PRO-002)
**Issue**: API key management (storage, validation, testing) crosses admin and provider boundaries. Admin stores and encrypts; provider adapters consume and decrypt.
**Severity**: low -- natural interface boundary. Admin is persistence layer; provider is consumption layer.

### CC-007: WooCommerce Product Context vs RAG Content Indexing
**Domains**: WOOCOMMERCE vs RAG
**Issue**: SRC-AXI indexes WooCommerce products via RAG pipeline (contexto-functions.php). SRC-BAA has independent product search (not RAG-based). Boundary between "product context for chat" (WooCommerce) and "content indexing" (RAG) must be defined.
**Severity**: medium -- affects which domain owns product-in-context features.
