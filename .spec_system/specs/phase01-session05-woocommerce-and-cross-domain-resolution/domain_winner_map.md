# Domain Winner Map

**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Coverage**: All 9 domains from Phase 01 scoring (sessions 01-05)
**Status**: Conflict-free (11 cross-domain conflicts resolved, 0 unresolved)
**Created**: 2026-03-01

---

## Winner Map Summary

| Domain | Winner | Band | Aggregate Score | Caps Won | Source Session |
|--------|--------|------|----------------|----------|---------------|
| DOM-PROVIDER | SRC-AXI | adopt | 4.56 | 4/4 | S01 |
| DOM-RUNTIME | Mixed (SRC-AXI + SRC-MXC) | adopt | AXI 3.92*, MXC 3.95 | AXI 1/4, MXC 3/4 | S02 |
| DOM-MIGRATION | Mixed (SRC-BAA + SRC-MXC) | adopt | MXC 3.85, BAA 2.67** | BAA 1/3, MXC 2/3 | S02 |
| DOM-RAG | SRC-AXI | adopt | 4.22 | 4/5 | S03 |
| DOM-ADMIN | Mixed (SRC-MXC + SRC-AXI) | adopt | MXC 4.05, AXI 3.90 | MXC 3/5, AXI 2/5 | S03 |
| DOM-SECURITY | SRC-AXI | adopt | 4.22 | 5/5 | S04 |
| DOM-TOOLS | SRC-AXI | adopt | 4.31 | 3/4 | S04 |
| DOM-INTEGRATIONS | SRC-AXI | adopt | 4.23 | 2/3 | S04 |
| DOM-WOOCOMMERCE | SRC-BAA | adopt | 4.29*** | 4/5 | S05 |

\* SRC-AXI runtime aggregate (3.92) excludes streaming N/A; raw aggregate is 2.94.
\** SRC-BAA migration aggregate (2.67) depressed by versioning N/A (scored 0.00).
\*** SRC-BAA WooCommerce aggregate (4.29) is adjusted excluding N/A capabilities; raw is 3.43.

---

## Detailed Domain Entries

### DOM-PROVIDER
**Decision Log**: `DL-P01-S01` (session 01)
**Winner**: SRC-AXI
**Aggregate Score**: 4.56
**Band**: adopt (>= 3.8, no dimension below 3, DIM-SECURITY >= 3)

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Provider Registry and Adapter Dispatch | SRC-AXI | 4.45 | adopt_as_is |
| Provider-Specific API Adapters | SRC-AXI | 4.52 | adopt_with_modifications |
| Centralized Model Registry | SRC-AXI | 4.85 | adopt_as_is |
| Provider Error Handling | SRC-AXI | 4.50 | adopt_with_modifications |

**CFI Items**: CFI-PRO-001, CFI-PRO-002
**Cross-Domain Resolutions**: RES-XD-002 (tool schema), RES-XD-006 (key storage), RES-XD-008 (embedding registry)

---

### DOM-RUNTIME
**Decision Log**: `DL-P01-S02-RUN` (session 02)
**Winner**: Mixed -- SRC-AXI (pipeline) + SRC-MXC (session, streaming, frontend)
**Aggregate Scores**: SRC-AXI 3.92 (adj.), SRC-MXC 3.95
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Message Lifecycle and Request Pipeline | SRC-AXI | 4.45 | adopt_with_modifications |
| Session Handling | SRC-MXC | 4.20 | adopt_with_modifications |
| Streaming and Real-Time Delivery | SRC-MXC | 3.95 | adopt_with_modifications |
| Frontend State Management | SRC-MXC | 4.15 | adopt_with_modifications |

**CFI Items**: CFI-RUN-001 through CFI-RUN-004
**Cross-Domain Resolutions**: RES-XD-004 (moderation pipeline), RES-XD-005 (session ownership)

---

### DOM-MIGRATION
**Decision Log**: `DL-P01-S02-MIG` (session 02)
**Winner**: Mixed -- SRC-BAA (lifecycle) + SRC-MXC (versioning, settings)
**Aggregate Scores**: SRC-MXC 3.85, SRC-AXI 3.02
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Plugin Lifecycle Management | SRC-BAA | 4.35 | adopt_with_modifications |
| Schema Versioning and Upgrade | SRC-MXC | 4.30 | adopt_with_modifications |
| Settings Migration and Cleanup | SRC-MXC | 3.95 | adopt_with_modifications |

**CFI Items**: CFI-MIG-001 through CFI-MIG-003
**Cross-Domain Resolutions**: RES-XD-001 (encryption cleanup on uninstall)

---

### DOM-RAG
**Decision Log**: `DL-P01-S03-RAG` (session 03)
**Winner**: SRC-AXI
**Aggregate Score**: 4.22
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Ingestion Pipeline | SRC-MXC | 4.15 | adopt_with_modifications |
| Chunking and Indexing | SRC-AXI | 4.00 | adopt_with_modifications |
| Retrieval and Ranking | SRC-AXI | 4.30 | adopt_with_modifications |
| Context Assembly | SRC-AXI | 4.65 | adopt_as_is |
| Embedding Integration | SRC-AXI | 4.15 | adopt_with_modifications |

**CFI Items**: CFI-RAG-001 through CFI-RAG-004
**Cross-Domain Resolutions**: RES-XD-003 (security policy injection), RES-XD-007 (WC product indexing), RES-XD-008 (embedding provider)

---

### DOM-ADMIN
**Decision Log**: `DL-P01-S03-ADM` (session 03)
**Winner**: Mixed -- SRC-MXC (3/5) + SRC-AXI (2/5)
**Aggregate Scores**: SRC-MXC 4.05, SRC-AXI 3.90
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Settings Architecture | SRC-MXC | 4.15 | adopt_with_modifications |
| Option Management | SRC-AXI | 4.20 | adopt_with_modifications |
| Diagnostics and Health | SRC-MXC | 4.10 | adopt_with_modifications |
| Operational Controls | SRC-AXI | 4.20 | adopt_with_modifications |
| Admin UX | SRC-MXC | 4.10 | adopt_with_modifications |

**CFI Items**: CFI-ADM-001 through CFI-ADM-005
**Cross-Domain Resolutions**: RES-XD-001 (encryption ownership), RES-XD-010 (diagnostics hosting)

---

### DOM-SECURITY
**Decision Log**: `DL-P01-S04-SEC` (session 04)
**Winner**: SRC-AXI
**Aggregate Score**: 4.22
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Input Validation and Sanitization | SRC-AXI | 4.50 | adopt_with_modifications |
| Authentication and Capability Enforcement | SRC-AXI | 4.20 | adopt_with_modifications |
| Content Moderation and Abuse Prevention | SRC-AXI | 4.50 | adopt_as_is |
| Privacy Controls and Usage Limits | SRC-AXI | 4.05 | adopt_with_modifications |
| Audit Logging and Diagnostics | SRC-AXI | 3.85 | adopt_with_modifications |

**CFI Items**: CFI-SEC-001 through CFI-SEC-004
**Cross-Domain Resolutions**: RES-XD-001 (encryption ownership), RES-XD-003 (security policy), RES-XD-004 (moderation), RES-XD-005 (session ownership)

---

### DOM-TOOLS
**Decision Log**: `DL-P01-S04-TOL` (session 04)
**Winner**: SRC-AXI
**Aggregate Score**: 4.31
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Tool/Action Registration | SRC-AXI | 4.65 | adopt_with_modifications |
| Execution Engine | SRC-AXI | 4.45 | adopt_with_modifications |
| Schema Validation and Tool Lifecycle | SRC-AXI | 4.30 | adopt_with_modifications |
| Admin Controls | SRC-MXC | 4.00 | adopt_with_modifications |

**CFI Items**: CFI-TOL-001 through CFI-TOL-004
**Cross-Domain Resolutions**: RES-XD-002 (schema normalization), RES-XD-009 (source tracking), RES-XD-011 (WC cart as tool)

---

### DOM-INTEGRATIONS
**Decision Log**: `DL-P01-S04-INT` (session 04)
**Winner**: SRC-AXI
**Aggregate Score**: 4.23
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| External Protocol Support | SRC-AXI | 4.50 | adopt_with_modifications |
| Webhook and Event System | SRC-AXI | 4.35 | adopt_with_modifications |
| Add-on and Extension Framework | SRC-MXC | 3.95 | adopt_with_modifications |

**CFI Items**: CFI-INT-001 through CFI-INT-003
**Cross-Domain Resolutions**: RES-XD-009 (source tracking)

---

### DOM-WOOCOMMERCE
**Decision Log**: `DL-P01-S05-WOO` (session 05)
**Winner**: SRC-BAA
**Aggregate Score**: 4.29 (adjusted, excl. N/A)
**Band**: adopt

| Capability | Winner | Score | Outcome |
|-----------|--------|-------|---------|
| Product Context Injection | SRC-BAA | 4.45 | adopt_with_modifications |
| Catalog Operations | SRC-BAA | 4.00 | adopt_with_modifications |
| Cart and Order Handling | SRC-MXC | 3.15 | adopt_with_modifications |
| Product Presentation | SRC-BAA | 4.50 | adopt_with_modifications |
| WC Platform Compatibility | SRC-BAA | 4.20 | adopt_as_is |

**CFI Items**: CFI-WOO-001 through CFI-WOO-004
**Cross-Domain Resolutions**: RES-XD-007 (RAG boundary), RES-XD-011 (cart dual exposure)

---

## Source Attribution Summary

### Capabilities Won by Source

| Source | Domains Won (Primary) | Capabilities Won | Adopt-As-Is | Adopt-With-Modifications |
|--------|----------------------|-------------------|-------------|-------------------------|
| SRC-AXI | PROVIDER, RAG, SECURITY, TOOLS, INTEGRATIONS | 24 | 5 | 19 |
| SRC-MXC | (partial: RUNTIME, MIGRATION, ADMIN) | 11 | 0 | 11 |
| SRC-BAA | WOOCOMMERCE, (partial: MIGRATION) | 7 | 2 | 5 |

### Source Contribution Patterns

- **SRC-AXI (AxiaChat)**: Dominant across 5 domains. Strongest in architecture, security, and extensibility. Provider registry, RAG pipeline, security controls, tool registration, and MCP protocol support are all AXI-primary.
- **SRC-MXC (MxChat)**: Co-winner in 3 domains. Strongest in UX, streaming, and operational features. Session handling, streaming transport, admin settings, cart/order operations, and marketplace UI are MXC-primary.
- **SRC-BAA (Baachal)**: Primary winner for WooCommerce, contributor to migration. Strongest in product context, presentation, and platform compatibility. Keyword search, product cards, HPOS compatibility, plugin lifecycle, and cache invalidation are BAA-primary.

---

## Conflict-Free Verification

### Cross-Domain Resolution Status

| Resolution ID | Status |
|--------------|--------|
| RES-XD-001 through RES-XD-011 | All resolved |

**Unresolved conflicts**: 0
**Winner changes from resolutions**: 0
**Deferred conflicts**: 0

### Carry-Forward Completeness

| Metric | Value |
|--------|-------|
| Total CFI items | 33 |
| From sessions 01-04 | 29 (all present) |
| From session 05 | 4 (new) |
| Items silently dropped | 0 |
| All items target Phase 02 | YES |

---

## Ready for Session 06

This domain winner map is conflict-free and ready for consolidation in `phase01-session06-winner-consolidation-and-phase-handoff`. All 9 domains scored, all cross-domain conflicts resolved, all carry-forward items cataloged.
