# PRD Phase 02: Target Architecture and Module Contracts

**Status**: In Progress
**Sessions**: 7 (initial estimate)
**Estimated Duration**: 14-28 days

**Progress**: 2/7 sessions (29%)

---

## Overview

Phase 02 translates the Phase 01 canonical capability matrix and winner selections into a concrete, implementable plugin architecture. This phase produces module maps, interface contracts, payload schemas, storage designs, and migration strategies -- all the architectural decisions needed before Phase 03 begins coding.

All design work is grounded in the Phase 01 handoff bundle (per-domain winner summaries, 33 carry-forward items in 4 tiers, 26 MVP capabilities, 6 cross-domain resolution patterns). No implementation code is produced in this phase -- only structured design documents that enable parallel implementation tracks in Phases 03+.

The 7 sessions progress from foundational decisions (module layout, data layer) through domain-specific contracts (provider, runtime, RAG) to cross-cutting concerns (security, admin, migration) and final handoff.

---

## Progress Tracker

| Session | Name | Status | Est. Tasks | Validated |
|---------|------|--------|------------|-----------|
| 01 | Module Map, Directory Architecture, and Bootstrap Design | Complete (2026-03-01) | 20 | PASS |
| 02 | Data Layer, Schema Design, and Migration Framework | Complete (2026-03-01) | 22 | PASS |
| 03 | Provider Orchestration and Model Registry Contracts | Not Started | ~18 | - |
| 04 | Chat Runtime, Session Model, and Frontend Contracts | Not Started | ~20 | - |
| 05 | RAG Pipeline, Knowledge Base, and Embedding Contracts | Not Started | ~18 | - |
| 06 | Security, Admin Contracts, and Observability | Not Started | ~18 | - |
| 07 | Migration Strategy, Extension Points, and Phase Handoff | Not Started | ~16 | - |

---

## Completed Sessions

- **Session 01** (2026-03-01): Module Map, Directory Architecture, and Bootstrap Design -- 20/20 tasks, 7 architecture documents, all 38 capabilities mapped to module homes
- **Session 02** (2026-03-01): Data Layer, Schema Design, and Migration Framework -- 22/22 tasks, 7 specification documents, 3 carry-forward items resolved (CFI-MIG-002, CFI-SEC-002, CFI-RUN-002)

---

## Upcoming Sessions

- Session 03: Provider Orchestration and Model Registry Contracts

---

## Objectives

1. Define the complete plugin module map with explicit boundaries, dependency rules, and directory layout.
2. Design data layer contracts (DB schemas, options conventions, migration framework) that support all 26 MVP capabilities.
3. Produce interface contracts for each major subsystem (provider, runtime, RAG, admin, security) with payload schemas and error semantics.
4. Design extension points that accommodate the 12 deferred capabilities without requiring MVP rework.
5. Define migration strategy outlines for transitioning from each of the three source plugins.
6. Resolve all carry-forward circular dependencies (streaming co-design, jQuery-vs-vanilla decision) at the architecture level.
7. Produce a Phase 03 handoff bundle enabling skeleton implementation without re-reading source repositories.

---

## Prerequisites

- Phase 01 completed (all 6 sessions, all exit criteria PASS)
- Canonical capability matrix available (38 capabilities, 9 domains, scored winners)
- v1 scope boundary defined (26 MVP, 12 deferred)
- Phase 02 handoff bundle with per-domain architecture inputs
- Carry-forward register with 33 items in 4 priority tiers
- 6 cross-domain resolution patterns documented

---

## Technical Considerations

### Architecture
Phase 02 is design-focused. All outputs are structured architecture documents (module maps, interface definitions, schema designs, contract specifications). These documents must be precise enough that Phase 03 implementation sessions can code against them without ambiguity. Every contract must cite the Phase 01 winner selection and carry-forward items it addresses.

### Technologies
- Markdown architecture documents under `.spec_system/PRD/phase_02/`
- Phase 01 artifacts: `canonical_capability_matrix.md`, `v1_scope_boundary.md`, `phase02_handoff_bundle.md`, `carry_forward_register_final.md`
- Reference source anchors from master PRD Phase 02 section:
  - `axiachat-ai/includes/interfaces/interface-aichat-provider.php`
  - `axiachat-ai/includes/class-aichat-provider-registry.php`
  - `baachal/includes/content-indexer.php`
  - `baachal/admin/settings-page.php`
  - `mxchat-basic/includes/class-mxchat-public.php`
  - `mxchat-basic/admin/class-ajax-handler.php`

### Risks
- **Over-abstraction**: Designing interfaces that are too generic delays implementation. Mitigate by grounding every contract in concrete Phase 01 winner patterns.
- **Circular dependency deadlock**: CFI-PRO-001/CFI-RUN-003 (streaming) and CFI-RUN-004/CFI-ADM-005 (frontend) require co-design. Mitigate with dedicated resolution in sessions 03-04.
- **Contract drift from reality**: Interface designs may not survive contact with actual source code patterns. Mitigate by requiring anchor citations and realistic code examples in contracts.
- **Scope creep into implementation**: Architecture sessions may be tempted to start coding. Mitigate by enforcing "no implementation code" rule -- only interface stubs and schema definitions.

### Relevant Considerations
- [P00] **Anchor-first extraction**: Continue using anchor IDs as primary entry points for design decisions. Reference specific source implementations, not abstract patterns.
- [P00] **Canonical vocabulary with alias tables**: All architecture documents must use `DOM-*` domain IDs and canonical capability names from Phase 01.
- [P00] **Template-driven bounded input**: Use context packs for any source code reads needed during design work.
- [P01] **Cross-domain resolution patterns**: Apply the 6 reusable patterns (policy-vs-execution, registry centralization, natural interface boundary, layered ownership, hosting, dual exposure) when designing module interactions.
- [P01] **Carry-forward tier ordering**: Tier 1 (foundation) items must be addressed before Tier 2 (infrastructure). Session ordering reflects this dependency chain.

---

## Success Criteria

Phase complete when:
- [ ] All 7 sessions completed
- [ ] Module map covers all 26 MVP capabilities with clear ownership boundaries
- [ ] Interface contracts exist for every major subsystem (provider, runtime, RAG, admin, security)
- [ ] Data layer design covers all required tables, options, and transients
- [ ] Migration strategy outlines exist for all 3 source plugins
- [ ] All 33 carry-forward items are addressed or explicitly deferred with rationale
- [ ] Circular dependencies (streaming, frontend) are resolved at the architecture level
- [ ] Extension points for deferred capabilities (tools, integrations, WooCommerce) are designed
- [ ] Interfaces are fixed enough to begin implementation in parallel tracks (Phase 03+)
- [ ] High-risk decisions are documented with fallback options

---

## Dependencies

### Depends On
- Phase 01: Canonical Capability Matrix and Scoring (complete)

### Enables
- Phase 03: Core Plugin Skeleton, Lifecycle, and Data Foundations
