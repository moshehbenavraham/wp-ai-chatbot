# PRD Phase 01: Canonical Capability Matrix and Scoring

**Status**: In Progress
**Sessions**: 6 (initial estimate)
**Estimated Duration**: 6-10 days

**Progress**: 1/6 sessions (17%)

---

## Overview

Phase 01 builds the canonical capability matrix by extracting, scoring, and selecting winner implementations across all 9 domains defined in Phase 00. Using the scoring rubric (5 dimensions, weighted), feature-unit schema, and anchor index from Phase 00, each domain is systematically evaluated across the three source plugins (`axiachat-ai/`, `mxchat-basic/`, `baachal/`). The phase culminates in a consolidated winner selection and initial "Ultimate v1 scope" boundary that governs Phase 02 architecture decisions.

All extraction and scoring work uses bounded context packs assembled from the Phase 00 anchor index. No full repository re-reads are permitted.

---

## Progress Tracker

| Session | Name | Status | Est. Tasks | Validated |
|---------|------|--------|------------|-----------|
| 01 | Scoring Framework and Provider Domain | Complete (2026-03-01) | 18 | PASS |
| 02 | Runtime and Migration Domains | Not Started | ~16 | - |
| 03 | RAG and Admin Domains | Not Started | ~18 | - |
| 04 | Security, Tools, and Integrations Domains | Not Started | ~20 | - |
| 05 | WooCommerce and Cross-Domain Resolution | Not Started | ~18 | - |
| 06 | Winner Consolidation and Phase Handoff | Not Started | ~15 | - |

---

## Completed Sessions

- **Session 01**: Scoring Framework and Provider Domain (2026-03-01)
  - Validated scoring framework end-to-end with provider domain
  - Created reusable capability matrix template for all 9 domains
  - Scored provider capabilities: SRC-AXI winner (4.56 weighted avg)
  - 4 decisions: 2 adopt-as-is, 2 adopt-with-modifications
  - 2 carry-forward items for Phase 02 architecture

---

## Upcoming Sessions

- Session 02: Runtime and Migration Domains

---

## Objectives

1. Extract and score capabilities from all 9 canonical domains across 3 source plugins using Phase 00 artifacts.
2. Produce a canonical capability matrix with source attribution (adopt / adopt-with-modifications / reject per capability).
3. Resolve all cross-domain "which plugin behavior wins?" conflicts with documented rationale.
4. Define the initial "Ultimate v1 scope" boundary separating MVP from deferred capabilities.
5. Produce a handoff bundle enabling Phase 02 architecture work without re-reading source repositories.

---

## Prerequisites

- Phase 00 completed (all gates passed per `phase01_readiness_checklist.md`)
- Domain taxonomy stable: 9 canonical domains with `DOM-*` IDs
- Scoring rubric stable: 5 dimensions, weighted, with decision thresholds
- Anchor index available: 27 anchors across 9 domains, 3 sources
- Context pack, decision log, and carry-forward templates ready for reuse

---

## Technical Considerations

### Architecture
Phase 01 is extraction-and-decision focused. No implementation code is produced. All outputs are structured documents that serve as inputs to Phase 02 architecture sessions. The scoring matrix must be deterministic and auditable -- every decision must cite anchor evidence and scoring dimensions.

### Technologies
- Markdown scoring matrices under `.spec_system/PRD/phase_01/`
- Phase 00 templates: `context_pack_template.md`, `decision_log_template.md`, `carry_forward_template.md`
- Phase 00 schemas: `feature_unit_schema.md`, `scoring_rubric.md`, `domain_taxonomy.md`
- Anchor index from `specs/phase00-session01-*/anchor_index.md`

### Risks
- **Scoring subjectivity**: Mitigate with mandatory evidence notes per dimension and tie-break rules from scoring rubric.
- **Domain overlap ambiguity**: Mitigate with domain boundary rules and primary-domain precedence from taxonomy.
- **Context budget overrun**: Mitigate with bounded context packs (curated excerpts, not full files) and domain-sliced sessions.
- **N/A parity gaps distorting scores**: Mitigate with explicit N/A policy and adjusted comparison when a source lacks coverage.

### Relevant Considerations
- [P00] **Anchor-first extraction**: Continue using anchor IDs as the primary entry point for context assembly. Do not start from raw feature maps.
- [P00] **Canonical vocabulary with alias tables**: All scoring outputs must use `DOM-*` IDs and canonical names. No ad-hoc domain labels.
- [P00] **Explicit N/A policy**: Baachal lacks `tools` and `integrations` domains. Score these as `N/A (no matching implementation)` with rationale, not as zero scores.
- [P00] **Template-driven bounded input**: Use context pack template for all source code reads. Enforce budget declarations per session.

---

## Success Criteria

Phase complete when:
- [ ] All 6 sessions completed
- [ ] Every major domain (9 of 9) has a selected baseline with scored rationale
- [ ] No unresolved "which plugin behavior wins?" conflicts remain
- [ ] Canonical capability matrix is consolidated with source attribution
- [ ] Initial "Ultimate v1 scope" boundary is defined (MVP vs deferred)
- [ ] Phase 02 handoff bundle is produced with architecture-ready inputs

---

## Dependencies

### Depends On
- Phase 00: Program Setup and Extraction Schema (complete)

### Enables
- Phase 02: Target Architecture and Module Contracts
