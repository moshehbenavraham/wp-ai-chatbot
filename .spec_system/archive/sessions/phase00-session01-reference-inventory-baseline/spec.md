# Session Specification

**Session ID**: `phase00-session01-reference-inventory-baseline`
**Phase**: 00 - Program Setup and Extraction Schema
**Status**: Not Started
**Created**: 2026-02-28

---

## 1. Session Overview

This session establishes the first bounded working set for Phase 00 by cataloging the canonical source repositories and the highest-value entry points needed for later extraction work. The output is a reusable inventory that prevents repeated full-repository scanning in downstream sessions.

The session also introduces a domain-grouped anchor index with explicit metadata and promotion criteria. This gives Session 02 and Session 03 a stable substrate for taxonomy and template design, while preserving traceability to original source files in `axiachat-ai/`, `mxchat-basic/`, and `baachal/`.

Because this is a planning artifact session, implementation is documentation-focused and must remain inside `.spec_system/specs/phase00-session01-reference-inventory-baseline/`. No source code changes are in scope, and all references to canonical plugin repositories remain read-only.

---

## 2. Objectives

1. Produce a complete source inventory baseline for all three canonical plugins and their feature-map anchors.
2. Create a domain-grouped anchor index with balanced source coverage and explicit metadata fields.
3. Define promotion criteria for moving anchors into later decision sessions.
4. Validate that outputs are reusable inputs for Session 02 and Session 03 without full repo re-reads.

---

## 3. Prerequisites

### Required Sessions
- None (initial execution session for Phase 00)

### Required Tools/Knowledge
- Working knowledge of the master PRD phase model and reference-only policy
- Familiarity with feature-map documents in `docs/`
- Understanding of naming and documentation practices in `.spec_system/CONVENTIONS.md`

### Environment Requirements
- Repository root available at `/home/aiwithapex/projects/wp-ai-chatbot`
- Read access to `axiachat-ai/`, `mxchat-basic/`, and `baachal/`
- Existing feature maps: `docs/axiachat-ai-feature-map.md`, `docs/mxchat-basic-feature-map.md`, `docs/baachal-feature-map.md`

---

## 4. Scope

### In Scope (MVP)
- Operator creates a source inventory table for all three canonical plugins - includes root structure and key entry-point references.
- Operator builds a domain-grouped anchor index - includes provider, runtime, RAG, tools, admin, security, WooCommerce, integrations, and migration anchor coverage.
- Operator defines anchor metadata fields and promotion criteria - ensures downstream sessions can score and select anchors consistently.
- Operator validates inventory completeness and reuse readiness - confirms Session 02 and Session 03 can proceed with bounded inputs.

### Out of Scope (Deferred)
- Capability winner selection and scoring decisions - *Reason: assigned to Phase 01.*
- Architecture contract design and module interface locking - *Reason: assigned to Phase 02.*
- Any edits to reference repositories - *Reason: source projects are read-only by policy.*

---

## 5. Technical Approach

### Architecture
The session uses a documentation-first pipeline within one spec workspace directory. Inputs are the PRD, phase stub, UX PRD (context only), and feature maps. Outputs are structured markdown and JSON artifacts: an inventory baseline, anchor index, metadata schema notes, and explicit promotion criteria.

Artifacts are built with deterministic ordering by source project and domain. Each anchor entry includes normalized identifiers and traceable source paths so later sessions can consume anchors directly without rediscovery.

### Design Patterns
- **Bounded context pack pattern**: constrain reading scope to curated anchors for downstream efficiency.
- **Traceability-first documentation**: each inventory item maps to source anchors for reproducibility.
- **Incremental artifact layering**: setup -> schema foundation -> anchor population -> validation evidence.

### Technology Stack
- Markdown documentation files under `.spec_system/specs/phase00-session01-reference-inventory-baseline/`
- JSON for structured inventory data interchange
- Shell and ripgrep validation for completeness and encoding checks

### Reference Anchors
- `axiachat-ai/axiachat-ai.php`
- `axiachat-ai/readme.txt`
- `mxchat-basic/mxchat-basic.php`
- `mxchat-basic/readme.txt`
- `baachal/baachal.php`
- `baachal/readme.txt`

---

## 6. Deliverables

### Files to Create

| File | Purpose | Est. Lines |
|------|---------|------------|
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/reference_inventory.md` | Source inventory baseline and keyed plugin entry points | ~140 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/source_inventory.json` | Machine-readable inventory data for downstream sessions | ~120 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/anchor_index.md` | Domain-grouped anchor index with balanced source coverage | ~180 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/anchor_metadata_schema.md` | Field definitions and metadata conventions for anchors | ~80 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/entry_criteria.md` | Promotion criteria for candidate anchors | ~70 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/traceability_matrix.md` | Crosswalk between inventory IDs and anchor IDs | ~90 |
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/phase00_session01_validation.md` | Coverage, quality, ASCII, and LF verification evidence | ~70 |

### Files to Modify

| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/specs/phase00-session01-reference-inventory-baseline/implementation-notes.md` | Record assumptions, decisions, and completion evidence | ~40 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Inventory includes all three canonical source plugins and linked feature-map references.
- [ ] Anchor index covers all declared Phase 00 domains with source-balanced anchors.
- [ ] Entry criteria define promotion rules for Session 02 and Session 03 readiness.
- [ ] Traceability matrix links all anchor IDs to source inventory IDs.

### Testing Requirements
- [ ] Coverage validation completed against inventory and anchor domain checklist
- [ ] Manual review confirms artifacts are directly reusable by Session 02 and Session 03

### Non-Functional Requirements
- [ ] Session artifacts minimize re-reading by providing bounded, indexed references.
- [ ] Documentation is deterministic, readable, and aligned with project naming conventions.

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions

---

## 8. Implementation Notes

### Key Considerations
- Apply the reference-only rule strictly; all source plugin paths are read-only anchors.
- Keep naming and structure conventions from `.spec_system/CONVENTIONS.md` to maintain consistency.
- Preserve deterministic ordering of source inventory and anchor sections for diff-friendly updates.
- Security and GDPR files report no open findings; keep this session focused on baseline planning outputs.

### Potential Challenges
- **Anchor sprawl**: mitigate by limiting to high-value entry points and explicitly deferring lower-value anchors.
- **Uneven domain coverage**: mitigate with per-domain completeness checks across all three source plugins.
- **Ambiguous anchor quality**: mitigate with explicit promotion criteria and traceability matrix requirements.

---

## 9. Testing Strategy

### Unit Tests
- Validate `source_inventory.json` structure against documented metadata fields.
- Validate that each anchor ID is unique and referenced by exactly one inventory source grouping.

### Integration Tests
- Verify anchor index sections align with Phase 00 domain list in `PRD.md`.
- Verify traceability matrix resolves anchor IDs back to concrete source paths.

### Manual Testing
- Walk through inventory and anchor files as if starting Session 02 planning.
- Confirm no additional full repository read is needed to identify core anchors.

### Edge Cases
- Domain has no matching implementation in one source plugin - mark explicitly as `N/A (no matching implementation)`.
- Same source file appears in multiple domains - ensure each usage has distinct rationale metadata.
- Feature-map discrepancy versus source tree - record discrepancy in implementation notes before finalizing.

---

## 10. Dependencies

### External Libraries
- None

### Other Sessions
- **Depends on**: None
- **Depended by**: `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates`, `phase00-session04-phase-readiness-handoff-bundle`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
