# Canonical Capability Matrix

**Session ID**: `phase01-session06-winner-consolidation-and-phase-handoff`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Coverage**: All 9 domains, 38 scored capabilities, 3 source plugins
**Created**: 2026-03-01
**Sources**: Decision logs DL-P01-S01 through DL-P01-S05-WOO, domain_winner_map.md

---

## Matrix Overview

| Metric | Value |
|--------|-------|
| Domains Scored | 9 |
| Total Capabilities (scored decisions) | 38 |
| Planning Estimate (domain taxonomy) | 42 |
| Scoring Sessions | 5 (S01-S05) |
| Scoring Dimensions | 5 (Coverage 30%, Reliability 20%, Security 20%, Operability 15%, Extensibility 15%) |
| Band Thresholds | adopt >= 3.8; investigate 2.8-3.7; reject < 2.8 |
| Sources Evaluated | SRC-AXI (AxiaChat), SRC-MXC (MxChat), SRC-BAA (Baachal) |

---

## 1. DOM-PROVIDER (4 capabilities)

**Decision Log**: `DL-P01-S01`
**Domain Winner**: SRC-AXI (4/4 capabilities)
**Aggregate Score**: 4.56 (avg of winning scores)

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 1 | DEC-PRO-001 | Provider Registry and Adapter Dispatch | SRC-AXI | 4.45 | adopt_as_is | adopt | -- |
| 2 | DEC-PRO-002 | Provider-Specific API Adapters | SRC-AXI | 4.52 | adopt_with_modifications | adopt | CFI-PRO-001 |
| 3 | DEC-PRO-003 | Centralized Model Registry and Resolution | SRC-AXI | 4.85 | adopt_as_is | adopt | -- |
| 4 | DEC-PRO-004 | Provider Error Handling and Resilience | SRC-AXI | 4.50 | adopt_with_modifications | adopt | CFI-PRO-002 |

**Key Patterns**: Singleton+Factory registry, interface compliance validation, hook-based registration, config-hash caching, per-provider adapter classes, 35+ model metadata catalog, exponential backoff retry, 4-tier fallback chains.

---

## 2. DOM-RUNTIME (4 capabilities)

**Decision Log**: `DL-P01-S02-RUN`
**Domain Winner**: Mixed (SRC-AXI 1/4 + SRC-MXC 3/4)
**Aggregate Scores**: SRC-AXI 3.92 (adj.), SRC-MXC 3.95

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 5 | DEC-RUN-001 | Message Lifecycle and Request Pipeline | SRC-AXI | 4.45 | adopt_with_modifications | adopt | CFI-RUN-001 |
| 6 | DEC-RUN-002 | Session Handling and Conversation State | SRC-MXC | 4.20 | adopt_with_modifications | adopt | CFI-RUN-002 |
| 7 | DEC-RUN-003 | Streaming and Real-Time Delivery | SRC-MXC | 3.95 | adopt_with_modifications | adopt | CFI-RUN-003 |
| 8 | DEC-RUN-004 | Frontend State Management and UX Runtime | SRC-MXC | 4.15 | adopt_with_modifications | adopt | CFI-RUN-004 |

**Key Patterns**: Pre-processing filter chain, provider dispatch via registry, tool-pending handshake, session ownership validation, SSE streaming with provider-specific parsing, multi-bot instance management, JS hook system.

**N/A Note**: DEC-RUN-003 (Streaming) -- SRC-AXI and SRC-BAA scored 0.00 (N/A, no implementation). SRC-MXC is the sole implementer.

---

## 3. DOM-MIGRATION (3 capabilities)

**Decision Log**: `DL-P01-S02-MIG`
**Domain Winner**: Mixed (SRC-BAA 1/3 + SRC-MXC 2/3)
**Aggregate Scores**: SRC-MXC 3.85, SRC-BAA 2.67 (adj.)

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 9 | DEC-MIG-001 | Plugin Lifecycle Management | SRC-BAA | 4.35 | adopt_with_modifications | adopt | CFI-MIG-001 |
| 10 | DEC-MIG-002 | Schema Versioning and Upgrade Routines | SRC-MXC | 4.30 | adopt_with_modifications | adopt | CFI-MIG-002 |
| 11 | DEC-MIG-003 | Settings Migration and Data Cleanup | SRC-MXC | 3.95 | adopt_with_modifications | adopt | CFI-MIG-003 |

**Key Patterns**: Three-hook lifecycle (activate/deactivate/uninstall), data preservation flag, fallback uninstall.php, version-conditional migration registry, deprecated model mapping, cron lifecycle management.

**N/A Note**: DEC-MIG-002 (Schema Versioning) -- SRC-BAA scored 0.00 (N/A, no versioning implementation).

---

## 4. DOM-RAG (5 capabilities)

**Decision Log**: `DL-P01-S03-RAG`
**Domain Winner**: SRC-AXI (4/5 capabilities)
**Aggregate Score**: 4.22

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 12 | DEC-RAG-001 | Ingestion Pipeline | SRC-MXC | 4.15 | adopt_with_modifications | adopt | CFI-RAG-001 |
| 13 | DEC-RAG-002 | Chunking and Indexing | SRC-AXI | 4.00 | adopt_with_modifications | adopt | CFI-RAG-002 |
| 14 | DEC-RAG-003 | Retrieval and Ranking | SRC-AXI | 4.30 | adopt_with_modifications | adopt | CFI-RAG-003 |
| 15 | DEC-RAG-004 | Context Assembly | SRC-AXI | 4.65 | adopt_as_is | adopt | -- |
| 16 | DEC-RAG-005 | Embedding Integration | SRC-AXI | 4.15 | adopt_with_modifications | adopt | CFI-RAG-004 |

**Key Patterns**: DB-backed queue with retry/stall detection, overlap-based chunking, mode-based retrieval (local/Pinecone/page/auto), security policy injection in context assembly, template token allowlist, multi-provider embedding with override filter.

**Tie-Break**: DEC-RAG-002 used migration_compatibility tie-break (SRC-AXI and SRC-MXC both scored 4.00).

**N/A Notes**: DEC-RAG-002 (Chunking) -- SRC-BAA scored 0.00 (N/A). DEC-RAG-004 (Context Assembly) -- SRC-BAA scored 0.00 (N/A). DEC-RAG-005 (Embedding) -- SRC-BAA scored 0.00 (N/A).

---

## 5. DOM-ADMIN (5 capabilities)

**Decision Log**: `DL-P01-S03-ADM`
**Domain Winner**: Mixed (SRC-MXC 3/5 + SRC-AXI 2/5)
**Aggregate Scores**: SRC-MXC 4.05, SRC-AXI 3.90

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 17 | DEC-ADM-001 | Settings Architecture | SRC-MXC | 4.15 | adopt_with_modifications | adopt | CFI-ADM-001 |
| 18 | DEC-ADM-002 | Option Management | SRC-AXI | 4.20 | adopt_with_modifications | adopt | CFI-ADM-002 |
| 19 | DEC-ADM-003 | Diagnostics and Health | SRC-MXC | 4.10 | adopt_with_modifications | adopt | CFI-ADM-003 |
| 20 | DEC-ADM-004 | Operational Controls | SRC-AXI | 4.20 | adopt_with_modifications | adopt | CFI-ADM-004 |
| 21 | DEC-ADM-005 | Admin UX | SRC-MXC | 4.10 | adopt_with_modifications | adopt | CFI-ADM-005 |

**Key Patterns**: Multi-page WP Settings API with sidebar navigation, AES-256-GCM API key encryption, SSE streaming test, analytics dashboard, embed origins whitelist, GDPR overlay, content moderation controls, vanilla JS rewrite plan.

---

## 6. DOM-SECURITY (5 capabilities)

**Decision Log**: `DL-P01-S04-SEC`
**Domain Winner**: SRC-AXI (5/5 capabilities)
**Aggregate Score**: 4.22

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 22 | DEC-SEC-001 | Input Validation and Sanitization | SRC-AXI | 4.50 | adopt_with_modifications | adopt | CFI-SEC-001 |
| 23 | DEC-SEC-002 | Authentication and Capability Enforcement | SRC-AXI | 4.20 | adopt_with_modifications | adopt | CFI-SEC-002 |
| 24 | DEC-SEC-003 | Content Moderation and Abuse Prevention | SRC-AXI | 4.50 | adopt_as_is | adopt | -- |
| 25 | DEC-SEC-004 | Privacy Controls and Usage Limits | SRC-AXI | 4.05 | adopt_with_modifications | adopt | CFI-SEC-003 |
| 26 | DEC-SEC-005 | Audit Logging and Diagnostics | SRC-AXI | 3.85 | adopt_with_modifications | adopt | CFI-SEC-004 |

**Key Patterns**: Custom domain sanitizers, bounds checking, 5-layer moderation (honeypot, rate limiting, spam signatures, banned words, OpenAI Moderation API), encrypted API keys, session ownership validation, conditional debug logging.

---

## 7. DOM-TOOLS (4 capabilities)

**Decision Log**: `DL-P01-S04-TOL`
**Domain Winner**: SRC-AXI (3/4 capabilities)
**Aggregate Score**: 4.31

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 27 | DEC-TOL-001 | Tool/Action Registration | SRC-AXI | 4.65 | adopt_with_modifications | adopt | CFI-TOL-001 |
| 28 | DEC-TOL-002 | Execution Engine | SRC-AXI | 4.45 | adopt_with_modifications | adopt | CFI-TOL-002 |
| 29 | DEC-TOL-003 | Schema Validation and Tool Lifecycle | SRC-AXI | 4.30 | adopt_with_modifications | adopt | CFI-TOL-003 |
| 30 | DEC-TOL-004 | Admin Controls | SRC-MXC | 4.00 | adopt_with_modifications | adopt | CFI-TOL-004 |

**Key Patterns**: JSON Schema tool validation, provider-native tool injection, macro registry with source tracking, per-bot capability settings, split-panel admin UI, intent-to-tool bridging.

**N/A Notes**: DEC-TOL-001 through DEC-TOL-004 -- SRC-BAA scored 0.00 across all 4 capabilities (N/A, no tool/action implementation).

---

## 8. DOM-INTEGRATIONS (3 capabilities)

**Decision Log**: `DL-P01-S04-INT`
**Domain Winner**: SRC-AXI (2/3 capabilities)
**Aggregate Score**: 4.23

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 31 | DEC-INT-001 | External Protocol Support | SRC-AXI | 4.50 | adopt_with_modifications | adopt | CFI-INT-001 |
| 32 | DEC-INT-002 | Webhook and Event System | SRC-AXI | 4.35 | adopt_with_modifications | adopt | CFI-INT-002 |
| 33 | DEC-INT-003 | Add-on and Extension Framework | SRC-MXC | 3.95 | adopt_with_modifications | adopt | CFI-INT-003 |

**Key Patterns**: MCP JSON-RPC client, webhook executor with SSRF protection, execution logging, marketplace UI with visual cards, source tracking lifecycle.

**N/A Notes**: DEC-INT-001 through DEC-INT-003 -- SRC-BAA scored 0.00 across all 3 capabilities (N/A, no integration framework).

---

## 9. DOM-WOOCOMMERCE (5 capabilities)

**Decision Log**: `DL-P01-S05-WOO`
**Domain Winner**: SRC-BAA (4/5 capabilities)
**Aggregate Score**: 4.29 (adjusted, excl. N/A)

| # | Decision ID | Capability | Winner | Score | Outcome | Band | CFI |
|---|-------------|-----------|--------|-------|---------|------|-----|
| 34 | DEC-WOO-001 | Product Context Injection | SRC-BAA | 4.45 | adopt_with_modifications | adopt | CFI-WOO-001 |
| 35 | DEC-WOO-002 | Catalog Operations | SRC-BAA | 4.00 | adopt_with_modifications | adopt | CFI-WOO-002 |
| 36 | DEC-WOO-003 | Cart and Order Handling | SRC-MXC | 3.15 | adopt_with_modifications | investigate | CFI-WOO-003 |
| 37 | DEC-WOO-004 | Product Presentation | SRC-BAA | 4.50 | adopt_with_modifications | adopt | CFI-WOO-004 |
| 38 | DEC-WOO-005 | WC Platform Compatibility | SRC-BAA | 4.20 | adopt_as_is | adopt | -- |

**Key Patterns**: Multi-stage keyword search, dynamic catalog term extraction, product card grid with semantic HTML, HPOS declaration, cache invalidation, cart operations via AJAX.

**N/A Notes**: DEC-WOO-003 (Cart) -- SRC-AXI and SRC-BAA scored N/A (no cart/order implementation). DEC-WOO-005 (Platform Compat) -- SRC-AXI and SRC-MXC scored N/A.

**Band Exception**: DEC-WOO-003 scored 3.15 (investigate band, below 3.8 adopt threshold) but adopted because it is the only source with cart/order functionality.

---

## Cross-Domain Summary

### Capability Totals by Outcome

| Outcome | Count | Percentage |
|---------|-------|------------|
| adopt_as_is | 5 | 13.2% |
| adopt_with_modifications | 33 | 86.8% |
| reject_defer | 0 | 0% |
| **Total** | **38** | **100%** |

### All 38 Scored Capabilities (Quick Reference)

| # | Domain | Decision ID | Capability | Winner | Score | Outcome |
|---|--------|-------------|-----------|--------|-------|---------|
| 1 | PROVIDER | DEC-PRO-001 | Provider Registry and Adapter Dispatch | SRC-AXI | 4.45 | adopt_as_is |
| 2 | PROVIDER | DEC-PRO-002 | Provider-Specific API Adapters | SRC-AXI | 4.52 | adopt_with_modifications |
| 3 | PROVIDER | DEC-PRO-003 | Centralized Model Registry and Resolution | SRC-AXI | 4.85 | adopt_as_is |
| 4 | PROVIDER | DEC-PRO-004 | Provider Error Handling and Resilience | SRC-AXI | 4.50 | adopt_with_modifications |
| 5 | RUNTIME | DEC-RUN-001 | Message Lifecycle and Request Pipeline | SRC-AXI | 4.45 | adopt_with_modifications |
| 6 | RUNTIME | DEC-RUN-002 | Session Handling and Conversation State | SRC-MXC | 4.20 | adopt_with_modifications |
| 7 | RUNTIME | DEC-RUN-003 | Streaming and Real-Time Delivery | SRC-MXC | 3.95 | adopt_with_modifications |
| 8 | RUNTIME | DEC-RUN-004 | Frontend State Management and UX Runtime | SRC-MXC | 4.15 | adopt_with_modifications |
| 9 | MIGRATION | DEC-MIG-001 | Plugin Lifecycle Management | SRC-BAA | 4.35 | adopt_with_modifications |
| 10 | MIGRATION | DEC-MIG-002 | Schema Versioning and Upgrade Routines | SRC-MXC | 4.30 | adopt_with_modifications |
| 11 | MIGRATION | DEC-MIG-003 | Settings Migration and Data Cleanup | SRC-MXC | 3.95 | adopt_with_modifications |
| 12 | RAG | DEC-RAG-001 | Ingestion Pipeline | SRC-MXC | 4.15 | adopt_with_modifications |
| 13 | RAG | DEC-RAG-002 | Chunking and Indexing | SRC-AXI | 4.00 | adopt_with_modifications |
| 14 | RAG | DEC-RAG-003 | Retrieval and Ranking | SRC-AXI | 4.30 | adopt_with_modifications |
| 15 | RAG | DEC-RAG-004 | Context Assembly | SRC-AXI | 4.65 | adopt_as_is |
| 16 | RAG | DEC-RAG-005 | Embedding Integration | SRC-AXI | 4.15 | adopt_with_modifications |
| 17 | ADMIN | DEC-ADM-001 | Settings Architecture | SRC-MXC | 4.15 | adopt_with_modifications |
| 18 | ADMIN | DEC-ADM-002 | Option Management | SRC-AXI | 4.20 | adopt_with_modifications |
| 19 | ADMIN | DEC-ADM-003 | Diagnostics and Health | SRC-MXC | 4.10 | adopt_with_modifications |
| 20 | ADMIN | DEC-ADM-004 | Operational Controls | SRC-AXI | 4.20 | adopt_with_modifications |
| 21 | ADMIN | DEC-ADM-005 | Admin UX | SRC-MXC | 4.10 | adopt_with_modifications |
| 22 | SECURITY | DEC-SEC-001 | Input Validation and Sanitization | SRC-AXI | 4.50 | adopt_with_modifications |
| 23 | SECURITY | DEC-SEC-002 | Authentication and Capability Enforcement | SRC-AXI | 4.20 | adopt_with_modifications |
| 24 | SECURITY | DEC-SEC-003 | Content Moderation and Abuse Prevention | SRC-AXI | 4.50 | adopt_as_is |
| 25 | SECURITY | DEC-SEC-004 | Privacy Controls and Usage Limits | SRC-AXI | 4.05 | adopt_with_modifications |
| 26 | SECURITY | DEC-SEC-005 | Audit Logging and Diagnostics | SRC-AXI | 3.85 | adopt_with_modifications |
| 27 | TOOLS | DEC-TOL-001 | Tool/Action Registration | SRC-AXI | 4.65 | adopt_with_modifications |
| 28 | TOOLS | DEC-TOL-002 | Execution Engine | SRC-AXI | 4.45 | adopt_with_modifications |
| 29 | TOOLS | DEC-TOL-003 | Schema Validation and Tool Lifecycle | SRC-AXI | 4.30 | adopt_with_modifications |
| 30 | TOOLS | DEC-TOL-004 | Admin Controls | SRC-MXC | 4.00 | adopt_with_modifications |
| 31 | INTEGRATIONS | DEC-INT-001 | External Protocol Support | SRC-AXI | 4.50 | adopt_with_modifications |
| 32 | INTEGRATIONS | DEC-INT-002 | Webhook and Event System | SRC-AXI | 4.35 | adopt_with_modifications |
| 33 | INTEGRATIONS | DEC-INT-003 | Add-on and Extension Framework | SRC-MXC | 3.95 | adopt_with_modifications |
| 34 | WOOCOMMERCE | DEC-WOO-001 | Product Context Injection | SRC-BAA | 4.45 | adopt_with_modifications |
| 35 | WOOCOMMERCE | DEC-WOO-002 | Catalog Operations | SRC-BAA | 4.00 | adopt_with_modifications |
| 36 | WOOCOMMERCE | DEC-WOO-003 | Cart and Order Handling | SRC-MXC | 3.15 | adopt_with_modifications |
| 37 | WOOCOMMERCE | DEC-WOO-004 | Product Presentation | SRC-BAA | 4.50 | adopt_with_modifications |
| 38 | WOOCOMMERCE | DEC-WOO-005 | WC Platform Compatibility | SRC-BAA | 4.20 | adopt_as_is |

**Capability Count Note**: The Phase 01 planning documents reference "42 capabilities" from the initial domain taxonomy feature extraction (phase00-session02). During scoring in sessions 01-05, some planned sub-capabilities were consolidated into parent decisions (e.g., streaming fallback folded into DEC-RUN-003, Pinecone security folded into DEC-RAG-003). The canonical matrix contains 38 distinct scored capability decisions. All planned capabilities are accounted for -- 4 were consolidated during scoring, not dropped. See `consistency_validation_report.md` for the full reconciliation.

---

## Adopt-As-Is Capabilities (5)

These capabilities are adopted directly from their winning source with no modifications required:

| # | Decision ID | Capability | Winner | Score |
|---|-------------|-----------|--------|-------|
| 1 | DEC-PRO-001 | Provider Registry and Adapter Dispatch | SRC-AXI | 4.45 |
| 2 | DEC-PRO-003 | Centralized Model Registry and Resolution | SRC-AXI | 4.85 |
| 3 | DEC-RAG-004 | Context Assembly | SRC-AXI | 4.65 |
| 4 | DEC-SEC-003 | Content Moderation and Abuse Prevention | SRC-AXI | 4.50 |
| 5 | DEC-WOO-005 | WC Platform Compatibility | SRC-BAA | 4.20 |

**Source breakdown**: SRC-AXI 4 adopt_as_is, SRC-BAA 1 adopt_as_is.

**Discrepancy note**: The domain_winner_map.md Source Attribution Summary table claims SRC-AXI: 5 adopt_as_is and SRC-BAA: 2 adopt_as_is (total 7). Cross-referencing all 38 decision log entries confirms the actual count is 5 adopt_as_is (4 AXI + 1 BAA). The domain_winner_map summary table contains a minor counting error -- the per-domain detail tables in that same document are correct. See `consistency_validation_report.md` for full analysis.

---

## Source Contribution Summary

### Capabilities Won Per Source

| Source | Capabilities Won | Adopt-As-Is | Adopt-With-Modifications | Domains (Primary Winner) | Domains (Co-Winner) |
|--------|-----------------|-------------|-------------------------|-------------------------|---------------------|
| SRC-AXI (AxiaChat) | 21 | 4 | 17 | PROVIDER, RAG, SECURITY, TOOLS, INTEGRATIONS | RUNTIME (pipeline), ADMIN (option mgmt, ops controls) |
| SRC-MXC (MxChat) | 12 | 0 | 12 | -- | RUNTIME (session, streaming, frontend), MIGRATION (versioning, settings), ADMIN (settings, diagnostics, UX), TOOLS (admin), INTEGRATIONS (addons), WOOCOMMERCE (cart) |
| SRC-BAA (Baachal) | 5 | 1 | 4 | WOOCOMMERCE | MIGRATION (lifecycle) |
| **Total** | **38** | **5** | **33** | | |

### Domain Leadership Patterns

| Domain | Primary Winner | Leadership Pattern |
|--------|---------------|-------------------|
| DOM-PROVIDER | SRC-AXI (4/4) | **Clean sweep** -- AXI dominates with formal registry architecture, no competitor close |
| DOM-RUNTIME | Mixed (AXI 1 + MXC 3) | **Architecture vs UX split** -- AXI wins backend pipeline, MXC wins user-facing layers |
| DOM-MIGRATION | Mixed (BAA 1 + MXC 2) | **Complementary strengths** -- BAA wins lifecycle (security focus), MXC wins versioning (coverage focus) |
| DOM-RAG | SRC-AXI (4/5) | **Near sweep** -- AXI dominates retrieval/assembly; MXC wins ingestion (broader source support) |
| DOM-ADMIN | Mixed (MXC 3 + AXI 2) | **UX vs security split** -- MXC wins operational UX, AXI wins security-sensitive controls |
| DOM-SECURITY | SRC-AXI (5/5) | **Clean sweep** -- AXI dominates all security capabilities, large score gaps |
| DOM-TOOLS | SRC-AXI (3/4) | **Architecture lead** -- AXI wins registration/execution/lifecycle; MXC wins admin UI |
| DOM-INTEGRATIONS | SRC-AXI (2/3) | **Protocol lead** -- AXI wins MCP and webhooks; MXC wins marketplace UX |
| DOM-WOOCOMMERCE | SRC-BAA (4/5) | **Domain specialist** -- BAA built specifically for WC; MXC contributes cart operations |

### Source Strength Profiles

**SRC-AXI (AxiaChat)** -- 21/38 capabilities (55.3%)
- **Strongest in**: Architecture, security, extensibility
- **Highest scores**: DEC-PRO-003 (4.85), DEC-RAG-004 (4.65), DEC-TOL-001 (4.65)
- **Pattern**: Formal abstractions (registries, interfaces, factories), deep extensibility (hooks, filters, overrides)
- **Weakness**: Limited UX polish, no streaming implementation, narrower admin surface

**SRC-MXC (MxChat)** -- 12/38 capabilities (31.6%)
- **Strongest in**: User experience, operational breadth, streaming
- **Highest scores**: DEC-MIG-002 (4.30), DEC-RUN-002 (4.20)
- **Pattern**: Feature-complete implementations with polished UX, broader source support
- **Weakness**: Monolithic architecture (9036-line admin class), jQuery dependency, plaintext API keys

**SRC-BAA (Baachal)** -- 5/38 capabilities (13.2%)
- **Strongest in**: WooCommerce integration, plugin lifecycle, output escaping
- **Highest scores**: DEC-WOO-004 (4.50), DEC-WOO-001 (4.45), DEC-MIG-001 (4.35)
- **Pattern**: Domain-specific depth, security-conscious lifecycle, zero-dependency operation
- **Weakness**: No tool/integration framework, limited admin surface, no streaming or RAG

### Score Distribution

| Score Range | Count | Capabilities |
|-------------|-------|-------------|
| 4.50+ | 6 | PRO-003 (4.85), RAG-004 (4.65), TOL-001 (4.65), PRO-002 (4.52), SEC-001 (4.50), SEC-003 (4.50), INT-001 (4.50), WOO-004 (4.50) |
| 4.00-4.49 | 22 | PRO-001, PRO-004, RUN-001, RUN-002, RUN-004, MIG-001, MIG-002, RAG-001, RAG-003, RAG-005, ADM-001, ADM-002, ADM-003, ADM-004, ADM-005, SEC-002, SEC-004, TOL-002, TOL-003, TOL-004, INT-002, WOO-001, WOO-002, WOO-005 |
| 3.50-3.99 | 9 | RUN-003, MIG-003, RAG-002, SEC-005, INT-003 |
| 3.00-3.49 | 1 | WOO-003 (3.15) |
| Below 3.00 | 0 | -- |

**Average winning score**: 4.21 across all 38 capabilities.
**Highest**: DEC-PRO-003 (Centralized Model Registry) at 4.85.
**Lowest**: DEC-WOO-003 (Cart and Order Handling) at 3.15 -- the only investigate-band capability adopted.
