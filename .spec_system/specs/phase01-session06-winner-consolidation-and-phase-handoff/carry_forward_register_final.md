# Carry-Forward Register (Final)

**Session ID**: `phase01-session06-winner-consolidation-and-phase-handoff`
**Purpose**: Finalized register of all Phase 01 carry-forward items, organized by implementation priority for Phase 02 architecture planning
**Source**: `phase01-session05-woocommerce-and-cross-domain-resolution/carry_forward_register.md` (33 items)
**Created**: 2026-03-01

---

## Register Summary

| Priority | Count | Percentage |
|----------|-------|------------|
| High | 18 | 54.5% |
| Medium | 15 | 45.5% |
| **Total** | **33** | **100%** |

All 33 items target Phase 02. All items status: open.

---

## Domain Distribution

| Domain | CFI Count | High | Medium | CFI IDs |
|--------|-----------|------|--------|---------|
| DOM-PROVIDER | 2 | 2 | 0 | CFI-PRO-001, CFI-PRO-002 |
| DOM-RUNTIME | 4 | 3 | 1 | CFI-RUN-001 to CFI-RUN-004 |
| DOM-MIGRATION | 3 | 2 | 1 | CFI-MIG-001 to CFI-MIG-003 |
| DOM-RAG | 4 | 2 | 2 | CFI-RAG-001 to CFI-RAG-004 |
| DOM-ADMIN | 5 | 3 | 2 | CFI-ADM-001 to CFI-ADM-005 |
| DOM-SECURITY | 4 | 2 | 2 | CFI-SEC-001 to CFI-SEC-004 |
| DOM-TOOLS | 4 | 2 | 2 | CFI-TOL-001 to CFI-TOL-004 |
| DOM-INTEGRATIONS | 3 | 1 | 2 | CFI-INT-001 to CFI-INT-003 |
| DOM-WOOCOMMERCE | 4 | 1 | 3 | CFI-WOO-001 to CFI-WOO-004 |
| **Total** | **33** | **18** | **15** | |

---

## Implementation Priority Ordering

Items are ordered by recommended Phase 02 implementation sequence. Items within the same tier can be parallelized.

### Tier 1: Foundation Architecture (8 items)

These items define core architecture patterns that other items depend on. Must be addressed in early Phase 02 architecture sessions.

| # | CFI ID | Domain | Decision | Description | Dependencies |
|---|--------|--------|----------|-------------|-------------|
| 1 | CFI-MIG-002 | DOM-MIGRATION | DEC-MIG-002 | Migration registry with versioned callbacks, execution logging, schema validation | None (foundational) |
| 2 | CFI-SEC-001 | DOM-SECURITY | DEC-SEC-001 | Unified sanitization library (AXI domain sanitizers + BAA bounds + MXC validation) | None (foundational) |
| 3 | CFI-ADM-001 | DOM-ADMIN | DEC-ADM-001 | Decompose monolithic admin into per-page controllers (<600 LOC), hook extensibility | None (foundational) |
| 4 | CFI-SEC-002 | DOM-SECURITY | DEC-SEC-002 | API key encryption at rest (AES-256-GCM), migration from plaintext, key rotation | RES-XD-001 |
| 5 | CFI-ADM-002 | DOM-ADMIN | DEC-ADM-002 | Apply AES-256-GCM to all key fields, field-by-field sanitization, regex validation | CFI-SEC-002 |
| 6 | CFI-MIG-001 | DOM-MIGRATION | DEC-MIG-001 | Combined plugin lifecycle (BAA uninstall + AXI multi-table + MXC cron), extension hooks | RES-XD-001, CFI-MIG-002 |
| 7 | CFI-TOL-001 | DOM-TOOLS | DEC-TOL-001 | Unified tool registration (AXI tool-calling + MXC intent/action), intent-to-tool bridging | RES-XD-002 |
| 8 | CFI-RAG-001 | DOM-RAG | DEC-RAG-001 | Unified ingestion pipeline (MXC queue + AXI content cleaning), queue table schema | RES-XD-007 |

### Tier 2: Core Infrastructure (10 items)

These items build on Tier 1 architecture decisions. Can proceed once their specific dependencies are resolved.

| # | CFI ID | Domain | Decision | Description | Dependencies |
|---|--------|--------|----------|-------------|-------------|
| 9 | CFI-PRO-001 | DOM-PROVIDER | DEC-PRO-002 | MXC SSE streaming in AXI adapter architecture, DeepSeek/Grok adapters | CFI-RUN-003 |
| 10 | CFI-PRO-002 | DOM-PROVIDER | DEC-PRO-004 | Generalize retry/fallback to all adapters, BAA user-friendly error messages | None |
| 11 | CFI-RUN-001 | DOM-RUNTIME | DEC-RUN-001 | MXC contextual awareness + BAA handler-replacement hook in AXI message pipeline | None |
| 12 | CFI-RUN-002 | DOM-RUNTIME | DEC-RUN-002 | Unified session storage (dedicated table), ownership validation, token tracking | RES-XD-005 |
| 13 | CFI-RUN-003 | DOM-RUNTIME | DEC-RUN-003 | Modular streaming layer, transit-time sanitization, tool-pending fallback | CFI-PRO-001 (circular -- design together) |
| 14 | CFI-MIG-003 | DOM-MIGRATION | DEC-MIG-003 | Generic option migration framework (key mapping, value transforms) | CFI-MIG-002 |
| 15 | CFI-RAG-002 | DOM-RAG | DEC-RAG-002 | Unified chunking (overlap, configurable size, deterministic IDs, clean storage) | CFI-RAG-001 |
| 16 | CFI-RAG-003 | DOM-RAG | DEC-RAG-003 | ANN index for retrieval (SQLite VSS / HNSW / Pinecone), 50K+ chunk scalability | CFI-RAG-001, CFI-RAG-002 |
| 17 | CFI-TOL-002 | DOM-TOOLS | DEC-TOL-002 | Tool execution lifecycle (pre/post hooks, timeouts, retry, caching) | CFI-TOL-001, RES-XD-002 |
| 18 | CFI-INT-001 | DOM-INTEGRATIONS | DEC-INT-001 | MCP client transport expansion (SSE, stdio), connection pooling, reconnection | None |

### Tier 3: Feature Enrichment (9 items)

These items add features on top of established infrastructure. Lower urgency, higher polish.

| # | CFI ID | Domain | Decision | Description | Dependencies |
|---|--------|--------|----------|-------------|-------------|
| 19 | CFI-RUN-004 | DOM-RUNTIME | DEC-RUN-004 | Combined frontend architecture (MXC streaming + AXI thinking + BAA hooks), jQuery decision | CFI-ADM-005 |
| 20 | CFI-RAG-004 | DOM-RAG | DEC-RAG-005 | Unified embedding providers (Voyage AI, bot-specific keys, dimension normalization) | RES-XD-008 |
| 21 | CFI-ADM-003 | DOM-ADMIN | DEC-ADM-003 | Unified diagnostics dashboard (streaming test, analytics, log viewer, panels) | CFI-ADM-001, RES-XD-010 |
| 22 | CFI-ADM-004 | DOM-ADMIN | DEC-ADM-004 | Unified operational controls (embed origins, GDPR, moderation, multi-bot, rate limits) | CFI-ADM-001 |
| 23 | CFI-ADM-005 | DOM-ADMIN | DEC-ADM-005 | Admin UX vanilla JS rewrite (no jQuery), auto-save, model selector, wp.i18n | CFI-ADM-001, CFI-RUN-004 (circular -- design together) |
| 24 | CFI-SEC-003 | DOM-SECURITY | DEC-SEC-004 | Privacy and usage limit configuration (daily limits, IP privacy, memory cap) | RES-XD-004 |
| 25 | CFI-SEC-004 | DOM-SECURITY | DEC-SEC-005 | Audit logging with structured logger, retention policy, admin security panel | RES-XD-010, CFI-ADM-003 |
| 26 | CFI-TOL-003 | DOM-TOOLS | DEC-TOL-003 | Macro registry schema formalization, tool grouping API, cascade delete testing | CFI-TOL-001, RES-XD-009 |
| 27 | CFI-INT-002 | DOM-INTEGRATIONS | DEC-INT-002 | Event-driven webhook system (chatbot events, async queue, custom placeholders) | RES-XD-009 |

### Tier 4: Extended Capabilities (6 items)

These items depend on multiple Tier 2-3 items and represent the most complex feature additions.

| # | CFI ID | Domain | Decision | Description | Dependencies |
|---|--------|--------|----------|-------------|-------------|
| 28 | CFI-TOL-004 | DOM-TOOLS | DEC-TOL-004 | Tool management admin UI (split-panel, capability settings, execution history) | CFI-TOL-001, CFI-TOL-002, CFI-ADM-001 |
| 29 | CFI-INT-003 | DOM-INTEGRATIONS | DEC-INT-003 | Unified extension model (addons + MCP + webhooks), marketplace UI, health monitoring | CFI-ADM-001, CFI-INT-001 |
| 30 | CFI-WOO-001 | DOM-WOOCOMMERCE | DEC-WOO-001 | Hybrid product retrieval (keyword + vector), query-time price/stock enrichment | CFI-RAG-001, CFI-RAG-003 |
| 31 | CFI-WOO-002 | DOM-WOOCOMMERCE | DEC-WOO-002 | Product extraction with cached index, configurable matching, admin scope controls | CFI-WOO-001, CFI-ADM-004 |
| 32 | CFI-WOO-003 | DOM-WOOCOMMERCE | DEC-WOO-003 | Validated cart operations (existence/visibility checks, variable products, checkout) | CFI-TOL-001, RES-XD-011 |
| 33 | CFI-WOO-004 | DOM-WOOCOMMERCE | DEC-WOO-004 | Product card grid with interactive elements, per-session context tracking | CFI-WOO-003, CFI-RUN-004 |

---

## Cross-Domain Resolution References

The following cross-domain resolutions (from session 05) affect carry-forward item implementation:

| Resolution | Pattern | Affected CFI Items |
|------------|---------|-------------------|
| RES-XD-001 | Policy-vs-Execution | CFI-SEC-002, CFI-ADM-002, CFI-MIG-001 |
| RES-XD-002 | Natural Interface Boundary | CFI-TOL-001, CFI-TOL-002 |
| RES-XD-004 | Policy-vs-Execution | CFI-SEC-003 |
| RES-XD-005 | Layered Ownership | CFI-RUN-002 |
| RES-XD-007 | Registry Centralization | CFI-RAG-001, CFI-WOO-001 |
| RES-XD-008 | Registry Centralization | CFI-RAG-004 |
| RES-XD-009 | Hosting Pattern | CFI-TOL-003, CFI-INT-002 |
| RES-XD-010 | Hosting Pattern | CFI-ADM-003, CFI-SEC-004 |
| RES-XD-011 | Dual Exposure | CFI-WOO-003 |

---

## Circular Dependencies

Two pairs of CFI items have circular dependencies that require co-design:

1. **CFI-PRO-001 <-> CFI-RUN-003**: Streaming in provider adapters and modular streaming layer must be designed together as a single architecture unit.
2. **CFI-RUN-004 <-> CFI-ADM-005**: Frontend chat architecture and admin UX both depend on jQuery-vs-vanilla decision; design together.

**Recommendation**: Address each circular pair in a single Phase 02 architecture session.

---

## Phase 02 Targeting Notes

### Session Planning Guidance

| Phase 02 Session Focus | Recommended CFI Items | Estimated Complexity |
|------------------------|----------------------|---------------------|
| Security Architecture | CFI-SEC-001, CFI-SEC-002, CFI-ADM-002 | High (encryption, migration) |
| Plugin Lifecycle | CFI-MIG-001, CFI-MIG-002, CFI-MIG-003 | Medium (registry pattern) |
| Admin Decomposition | CFI-ADM-001, CFI-ADM-003, CFI-ADM-004 | High (multi-file refactor) |
| Provider + Streaming | CFI-PRO-001, CFI-PRO-002, CFI-RUN-003 | High (circular deps) |
| Runtime Core | CFI-RUN-001, CFI-RUN-002 | Medium |
| Frontend Architecture | CFI-RUN-004, CFI-ADM-005 | High (circular deps, jQuery decision) |
| RAG Pipeline | CFI-RAG-001, CFI-RAG-002, CFI-RAG-003 | High (ANN index decision) |
| RAG Embeddings | CFI-RAG-004 | Medium |
| Tool System | CFI-TOL-001, CFI-TOL-002, CFI-TOL-003, CFI-TOL-004 | High (net-new architecture) |
| Integrations | CFI-INT-001, CFI-INT-002, CFI-INT-003 | Medium-High |
| WooCommerce | CFI-WOO-001 to CFI-WOO-004 | High (multi-dependency) |
| Security Operations | CFI-SEC-003, CFI-SEC-004 | Medium |

### Critical Path

The longest dependency chain is:
```
CFI-MIG-002 -> CFI-MIG-001 -> (lifecycle ready)
CFI-SEC-001 -> CFI-SEC-002 -> CFI-ADM-002 -> (encryption ready)
CFI-RAG-001 -> CFI-RAG-002 -> CFI-RAG-003 -> CFI-WOO-001 -> CFI-WOO-002
CFI-TOL-001 -> CFI-TOL-002 -> CFI-WOO-003 -> CFI-WOO-004
```

WooCommerce features (Tier 4) are at the end of the longest chains, confirming PRD deferral is correct.

---

## Completeness Verification

| Check | Result |
|-------|--------|
| Total items | 33 (matches session 05 register) |
| High priority items | 18 (matches) |
| Medium priority items | 15 (matches) |
| All items have source decision | YES |
| All items target Phase 02 | YES |
| All items status open | YES |
| No items silently dropped | YES (verified against sessions 01-05) |
| Cross-domain resolutions linked | YES (9 of 11 resolutions affect CFI items) |
