# PRD Phase 00: Program Setup and Extraction Schema

**Status**: In Progress
**Sessions**: 4 (initial estimate)
**Estimated Duration**: 4-7 days

**Progress**: 1/4 sessions (25%)

---

## Overview

Phase 00 establishes the planning framework for all implementation phases. The phase creates a stable extraction taxonomy, feature-unit schema, and reusable context packs so later sessions can make decisions using bounded inputs instead of re-reading full source repositories.

---

## Progress Tracker

| Session | Name | Status | Est. Tasks | Validated |
|---------|------|--------|------------|-----------|
| 01 | Reference Inventory Baseline | Complete | ~14-18 | 2026-02-28 |
| 02 | Domain Taxonomy and Feature Schema | Not Started | ~14-20 | - |
| 03 | Context Pack and Decision Log Templates | Not Started | ~12-18 | - |
| 04 | Phase Readiness and Handoff Bundle | Not Started | ~12-16 | - |

---

## Completed Sessions

- Session 01: Reference Inventory Baseline - Completed 2026-02-28

---

## Upcoming Sessions

- Session 02: Domain Taxonomy and Feature Schema

---

## Objectives

1. Establish a framework that prevents context bloat and extraction drift.
2. Define stable taxonomy and feature-unit schemas for downstream phases.
3. Produce a reusable reference anchor index across the three canonical source plugins.

---

## Prerequisites

- Master PRD baseline approved for Phase 00.
- Feature map documents are available and readable.
- Read-only boundary for source repositories is confirmed.

---

## Technical Considerations

### Architecture
Phase 00 artifacts should be documentation-first and normalized for reuse in `/plansession`. Outputs should separate raw extraction notes from normalized capability decisions to avoid decision drift.

### Technologies
- Markdown templates under `.spec_system/PRD/phase_00/`
- JSON-compatible field schemas for scoring and dependency metadata
- Reference anchors from `axiachat-ai/`, `mxchat-basic/`, and `baachal/`

### Risks
- Extraction drift between sessions: enforce explicit anchor-backed decisions and carry-forward notes.
- Scope creep into implementation details: keep all deliverables process and schema focused.
- Inconsistent vocabulary across domains: maintain a single canonical taxonomy dictionary.

---

## Success Criteria

Phase complete when:
- [ ] All 4 sessions completed
- [ ] Domain taxonomy and feature-unit schema are stable and reviewed
- [ ] Reference anchor index supports phase planning without full repo re-reads
- [ ] Context pack, decision log, and carry-forward templates are ready for reuse in Phase 01

---

## Dependencies

### Depends On
- Initial PRD and feature maps completed

### Enables
- Phase 01: Canonical Capability Matrix and Scoring
