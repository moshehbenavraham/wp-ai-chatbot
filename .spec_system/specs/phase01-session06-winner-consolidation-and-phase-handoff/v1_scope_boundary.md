# Ultimate v1 Scope Boundary

**Session ID**: `phase01-session06-winner-consolidation-and-phase-handoff`
**Purpose**: Classify each scored capability as v1-in-scope (MVP) or v1-deferred with rationale
**Created**: 2026-03-01
**Reference**: PRD.md MVP requirements and deferred requirements

---

## Scope Classification Framework

The PRD defines 5 MVP requirement areas and 3 deferred areas:

**MVP (v1 in-scope)**:
1. Deterministic plugin lifecycle behavior
2. Provider orchestration and model registry
3. Secure chat runtime behavior
4. Retrieval pipeline foundations
5. Admin controls for configuration and operations

**Deferred (v1+)**:
1. Advanced tool, macro, and intent ecosystems beyond prioritized high-value integrations
2. Extended WooCommerce and business workflow modules beyond initial validated scope
3. Full breadth of optional integrations from all source plugins

---

## Scope Classification Summary

| Classification | Count | Percentage |
|---------------|-------|------------|
| v1-in-scope (MVP) | 26 | 68.4% |
| v1-deferred | 12 | 31.6% |
| **Total** | **38** | **100%** |

---

## v1-in-scope Capabilities (26)

### DOM-PROVIDER -- 4/4 in-scope

All provider capabilities are MVP per PRD requirement 2 (provider orchestration and model registry).

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 1 | DEC-PRO-001 | Provider Registry and Adapter Dispatch | SRC-AXI | 4.45 | Core infrastructure -- all AI provider interaction depends on this |
| 2 | DEC-PRO-002 | Provider-Specific API Adapters | SRC-AXI | 4.52 | Required for multi-provider support (OpenAI, Claude, Gemini) |
| 3 | DEC-PRO-003 | Centralized Model Registry | SRC-AXI | 4.85 | Admin UI and runtime model resolution depend on this |
| 4 | DEC-PRO-004 | Provider Error Handling | SRC-AXI | 4.50 | Production reliability requires error handling from day 1 |

### DOM-RUNTIME -- 4/4 in-scope

All runtime capabilities are MVP per PRD requirement 3 (secure chat runtime behavior).

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 5 | DEC-RUN-001 | Message Lifecycle and Request Pipeline | SRC-AXI | 4.45 | Core chat functionality -- the primary user-facing feature |
| 6 | DEC-RUN-002 | Session Handling | SRC-MXC | 4.20 | Chat persistence and conversation state are expected baseline |
| 7 | DEC-RUN-003 | Streaming and Real-Time Delivery | SRC-MXC | 3.95 | Critical UX -- streaming reduces perceived latency from seconds to sub-second |
| 8 | DEC-RUN-004 | Frontend State Management | SRC-MXC | 4.15 | Chat widget is the user-facing surface; must ship complete |

### DOM-MIGRATION -- 3/3 in-scope

All migration capabilities are MVP per PRD requirement 1 (deterministic plugin lifecycle).

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 9 | DEC-MIG-001 | Plugin Lifecycle Management | SRC-BAA | 4.35 | Activate/deactivate/uninstall are required WordPress behaviors |
| 10 | DEC-MIG-002 | Schema Versioning and Upgrade | SRC-MXC | 4.30 | Safe upgrades are non-negotiable for production plugins |
| 11 | DEC-MIG-003 | Settings Migration and Cleanup | SRC-MXC | 3.95 | Settings migration ensures clean upgrade paths |

### DOM-RAG -- 5/5 in-scope

All RAG capabilities are MVP per PRD requirement 4 (retrieval pipeline foundations).

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 12 | DEC-RAG-001 | Ingestion Pipeline | SRC-MXC | 4.15 | Knowledge base population is prerequisite for RAG functionality |
| 13 | DEC-RAG-002 | Chunking and Indexing | SRC-AXI | 4.00 | Required for ingested content to be searchable |
| 14 | DEC-RAG-003 | Retrieval and Ranking | SRC-AXI | 4.30 | Core RAG query-time functionality |
| 15 | DEC-RAG-004 | Context Assembly | SRC-AXI | 4.65 | Prompt construction is the bridge between RAG and provider |
| 16 | DEC-RAG-005 | Embedding Integration | SRC-AXI | 4.15 | Vector generation required for semantic search |

### DOM-ADMIN -- 5/5 in-scope

All admin capabilities are MVP per PRD requirement 5 (admin controls for configuration).

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 17 | DEC-ADM-001 | Settings Architecture | SRC-MXC | 4.15 | Plugin must have a configuration surface from day 1 |
| 18 | DEC-ADM-002 | Option Management | SRC-AXI | 4.20 | Secure API key storage is non-negotiable |
| 19 | DEC-ADM-003 | Diagnostics and Health | SRC-MXC | 4.10 | Operators need connectivity testing and system info |
| 20 | DEC-ADM-004 | Operational Controls | SRC-AXI | 4.20 | Usage limits, moderation config, GDPR are required |
| 21 | DEC-ADM-005 | Admin UX | SRC-MXC | 4.10 | Admin interaction quality drives product adoption |

### DOM-SECURITY -- 5/5 in-scope

All security capabilities are MVP -- security is a default requirement per CONVENTIONS.md and PRD.

| # | Decision ID | Capability | Winner | Score | Rationale |
|---|-------------|-----------|--------|-------|-----------|
| 22 | DEC-SEC-001 | Input Validation and Sanitization | SRC-AXI | 4.50 | Trust boundary enforcement is mandatory |
| 23 | DEC-SEC-002 | Authentication and Capability Enforcement | SRC-AXI | 4.20 | Access control is mandatory for admin endpoints |
| 24 | DEC-SEC-003 | Content Moderation and Abuse Prevention | SRC-AXI | 4.50 | Public chat endpoint requires abuse prevention |
| 25 | DEC-SEC-004 | Privacy Controls and Usage Limits | SRC-AXI | 4.05 | GDPR and usage limits are regulatory/operational requirements |
| 26 | DEC-SEC-005 | Audit Logging | SRC-AXI | 3.85 | Security event logging is required for incident response |

---

## v1-deferred Capabilities (12)

### DOM-TOOLS -- 4/4 deferred

Deferred per PRD: "Advanced tool, macro, and intent ecosystems beyond prioritized high-value integrations." Phase 02 module contracts explicitly list "tool/macro/intent execution" as deferred.

| # | Decision ID | Capability | Winner | Score | Deferral Reason | Target |
|---|-------------|-----------|--------|-------|-----------------|--------|
| 27 | DEC-TOL-001 | Tool/Action Registration | SRC-AXI | 4.65 | PRD deferred: advanced tool ecosystems. Requires provider tool-calling protocol integration and intent bridging design. | Phase 02 |
| 28 | DEC-TOL-002 | Execution Engine | SRC-AXI | 4.45 | PRD deferred: advanced tool ecosystems. Depends on tool registration architecture (DEC-TOL-001). | Phase 02 |
| 29 | DEC-TOL-003 | Schema Validation and Lifecycle | SRC-AXI | 4.30 | PRD deferred: advanced tool ecosystems. Macro registry formalization depends on architecture decisions. | Phase 02 |
| 30 | DEC-TOL-004 | Admin Controls | SRC-MXC | 4.00 | PRD deferred: advanced tool ecosystems. Admin UI depends on tool registration and execution engine. | Phase 02 |

### DOM-INTEGRATIONS -- 3/3 deferred

Deferred per PRD: "Full breadth of optional integrations from all source plugins in early releases."

| # | Decision ID | Capability | Winner | Score | Deferral Reason | Target |
|---|-------------|-----------|--------|-------|-----------------|--------|
| 31 | DEC-INT-001 | External Protocol Support | SRC-AXI | 4.50 | PRD deferred: full integration breadth. MCP transport expansion needed. | Phase 02 |
| 32 | DEC-INT-002 | Webhook and Event System | SRC-AXI | 4.35 | PRD deferred: full integration breadth. Event-driven webhook system is net-new architecture. | Phase 02 |
| 33 | DEC-INT-003 | Add-on and Extension Framework | SRC-MXC | 3.95 | PRD deferred: full integration breadth. Unified extension model is Phase 02 architecture work. | Phase 02 |

### DOM-WOOCOMMERCE -- 5/5 deferred

Deferred per PRD: "Extended WooCommerce and business workflow modules beyond initial validated scope." Phase 02 module contracts explicitly list "WooCommerce enrichment" as deferred except initial scope (which is just WC detection and basic conditional loading, not product features).

| # | Decision ID | Capability | Winner | Score | Deferral Reason | Target |
|---|-------------|-----------|--------|-------|-----------------|--------|
| 34 | DEC-WOO-001 | Product Context Injection | SRC-BAA | 4.45 | PRD deferred: extended WooCommerce. Requires RAG pipeline integration (dependency). | Phase 02 |
| 35 | DEC-WOO-002 | Catalog Operations | SRC-BAA | 4.00 | PRD deferred: extended WooCommerce. Scalable product matching needs architecture. | Phase 02 |
| 36 | DEC-WOO-003 | Cart and Order Handling | SRC-MXC | 3.15 | PRD deferred: extended WooCommerce. Lowest-scored capability; needs significant security hardening. | Phase 02 |
| 37 | DEC-WOO-004 | Product Presentation | SRC-BAA | 4.50 | PRD deferred: extended WooCommerce. Depends on product context injection and cart operations. | Phase 02 |
| 38 | DEC-WOO-005 | WC Platform Compatibility | SRC-BAA | 4.20 | PRD deferred: extended WooCommerce. HPOS declaration is trivial but has no value without WC features. | Phase 02 |

---

## Scope Boundary Rationale Summary

### Why these 26 capabilities are MVP

The 26 MVP capabilities form a **complete, self-contained chatbot** that can:
1. Register and dispatch to multiple AI providers (Provider domain)
2. Handle chat messages with session persistence and streaming (Runtime domain)
3. Ingest, index, and retrieve knowledge base content (RAG domain)
4. Provide a full admin configuration surface with secure API key management (Admin domain)
5. Enforce security controls at every trust boundary (Security domain)
6. Safely install, upgrade, and uninstall with data preservation (Migration domain)

This matches all 5 PRD MVP requirements.

### Why these 12 capabilities are deferred

The 12 deferred capabilities are in 3 domains that the PRD explicitly marks as "beyond initial validated scope":
1. **Tools** (4): Require architecture design for dual invocation model (AI tool calling + user intent matching) -- net-new design work
2. **Integrations** (3): MCP transport expansion, event-driven webhooks, and unified extension model are all architecture-phase concerns
3. **WooCommerce** (5): Depend on RAG pipeline being established first, need security hardening (cart ops), and the PRD explicitly defers "extended WooCommerce modules"

### Dependency ordering supports this boundary

All deferred capabilities depend on MVP capabilities:
- Tools depend on Provider (DEC-PRO-001 registry) and Runtime (DEC-RUN-001 pipeline)
- Integrations depend on Tools (DEC-TOL-001 registration for MCP/webhook tools)
- WooCommerce depends on RAG (DEC-RAG-001 ingestion for product indexing) and Runtime (DEC-RUN-001 for cart AJAX)

The MVP capabilities have no dependencies on deferred capabilities.

---

## Phase 02 Targeting

All 12 deferred capabilities target Phase 02 (Architecture and Module Contracts). Phase 02 will produce module contracts, interface definitions, and implementation plans for these capabilities building on the MVP foundation.

| Phase 02 Priority | Capabilities | Rationale |
|-------------------|-------------|-----------|
| Early (architecture-defining) | DEC-TOL-001, DEC-INT-001 | Tool registration and MCP protocol define integration architecture |
| Mid (depends on architecture) | DEC-TOL-002, DEC-TOL-003, DEC-INT-002, DEC-WOO-001, DEC-WOO-005 | Execution engines and product context depend on registration/protocol |
| Late (depends on earlier deferred) | DEC-TOL-004, DEC-INT-003, DEC-WOO-002, DEC-WOO-003, DEC-WOO-004 | Admin UIs, marketplace, and WC operations depend on core deferred capabilities |
