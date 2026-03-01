# Session Specification

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Phase**: 00 - Program Setup and Extraction Schema
**Status**: Not Started
**Created**: 2026-02-28

---

## 1. Session Overview

This session defines the canonical language and structure that all later extraction and scoring work will rely on. It converts the Session 01 reference inventory into a stable taxonomy of domains and a normalized feature-unit schema that can be applied consistently across `axiachat-ai/`, `mxchat-basic/`, and `baachal/`.

The output of this session is a planning artifact bundle, not plugin runtime code. By fixing domain names, schema fields, and scoring dimensions now, downstream sessions can compare capabilities, reason about dependencies, and document decisions without repeated interpretation drift.

This work is the direct prerequisite for Session 03 template design and Phase 01 capability scoring. It reduces context bloat by turning broad source references into constrained, reusable structures.

---

## 2. Objectives

1. Define a canonical domain taxonomy with stable names, definitions, and boundary rules for all Phase 00 planning outputs.
2. Produce a feature-unit schema that standardizes IDs, source attribution, capability description, maturity, dependencies, and risk fields.
3. Draft a lightweight scoring rubric that Phase 01 sessions can apply consistently for adopt/reject decisions.
4. Validate that taxonomy and schema artifacts are reusable by Session 03 without requiring full repository re-reads.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - provides source inventory, anchor index baseline, and traceability framing.

### Required Tools/Knowledge
- Familiarity with `.spec_system/PRD/PRD.md` and `.spec_system/PRD/phase_00/` session definitions
- Working knowledge of feature-map documents in `docs/`
- Alignment with naming and testing guidance in `.spec_system/CONVENTIONS.md`

### Environment Requirements
- Repository root available at `/home/aiwithapex/projects/wp-ai-chatbot`
- Read access to `axiachat-ai/`, `mxchat-basic/`, `baachal/`, and `docs/*-feature-map.md`
- Write access to `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/`

---

## 4. Scope

### In Scope (MVP)
- Planning operator defines canonical domains for provider, runtime, RAG, tools, admin, security, WooCommerce, and integrations - includes stable naming and boundary notes.
- Planning operator defines the feature-unit schema contract - includes field definitions, allowed values, and validation guidance.
- Planning operator drafts scoring dimensions for later winner-selection sessions - includes weighting intent and decision thresholds.
- Planning operator verifies taxonomy and schema consistency against all three source plugin references - includes explicit `N/A (no matching implementation)` handling where needed.

### Out of Scope (Deferred)
- Final capability winner selection by domain - *Reason: assigned to Phase 01 matrix sessions.*
- Runtime/module contract design for production implementation - *Reason: assigned to Phase 02 architecture sessions.*
- Any edits to `axiachat-ai/`, `mxchat-basic/`, or `baachal/` - *Reason: source repositories are reference-only.*

---

## 5. Technical Approach

### Architecture
Use a documentation-first artifact pipeline in a single session directory under `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/`. Build taxonomy and schema artifacts in deterministic section order so later sessions can consume them directly and diff changes safely.

The schema package will be layered: domain taxonomy, feature schema contract, feature-entry template, scoring rubric, and taxonomy-to-schema crosswalk. A dedicated validation artifact will capture quality checks, reuse readiness, and encoding verification.

### Design Patterns
- **Controlled vocabulary pattern**: avoid domain-label drift by fixing canonical terms and aliases.
- **Schema-first extraction pattern**: define structure before scoring to improve consistency and reduce interpretation risk.
- **Traceability-by-anchor pattern**: tie taxonomy and schema examples to concrete source references across all three projects.

### Technology Stack
- Markdown artifacts in `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/`
- Structured schema tables and examples for deterministic parsing by later sessions
- `rg`/shell validation commands for consistency and ASCII/LF checks

### Reference Anchors
- `docs/axiachat-ai-feature-map.md`
- `docs/mxchat-basic-feature-map.md`
- `docs/baachal-feature-map.md`
- `axiachat-ai/axiachat-ai.php`
- `axiachat-ai/includes/model-registry.php`
- `mxchat-basic/mxchat-basic.php`
- `mxchat-basic/includes/class-mxchat-integrator.php`
- `baachal/baachal.php`
- `baachal/includes/content-indexer.php`

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md` | Canonical domains, definitions, boundaries, and naming rules | ~140 |
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/feature_unit_schema.md` | Field-level schema contract for feature extraction entries | ~160 |
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/feature_entry_template.md` | Reusable feature-entry template with source-attributed examples | ~90 |
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md` | Lightweight scoring dimensions and decision-threshold guidance | ~110 |
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/taxonomy_schema_crosswalk.md` | Crosswalk between taxonomy terms and schema enums/fields | ~85 |
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/phase00_session02_validation.md` | Validation evidence for completeness, consistency, and encoding | ~70 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/implementation-notes.md` | Record assumptions, decisions, quality checks, and unresolved items | ~50 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Canonical taxonomy includes the required domains with clear definitions and non-overlapping boundaries.
- [ ] Feature-unit schema includes required fields (`ID`, `source plugin`, `capability`, `maturity`, `dependencies`, `risk`) plus validation guidance.
- [ ] Scoring rubric defines reusable dimensions and threshold intent for later adopt/reject decisions.
- [ ] Crosswalk artifact maps taxonomy labels to schema fields and collision-resolution rules.

### Testing Requirements
- [ ] Structured validation checklist confirms taxonomy stability and schema completeness.
- [ ] Manual reuse review confirms Session 03 template work can consume artifacts without rework.

### Non-Functional Requirements
- [ ] Artifacts are deterministic, readable, and aligned with naming conventions in `.spec_system/CONVENTIONS.md`.
- [ ] Documentation remains bounded and avoids full-repository rediscovery requirements.

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions

---

## 8. Implementation Notes

### Key Considerations
- Preserve strict read-only handling for `axiachat-ai/`, `mxchat-basic/`, and `baachal/`; these are source anchors only.
- Keep terminology synchronized with PRD domain language to avoid downstream mapping drift.
- Require explicit `N/A (no matching implementation)` markers when a domain capability is absent in one source project.

### Potential Challenges
- **Domain overlap ambiguity**: mitigate with boundary notes and explicit collision-resolution rules in the crosswalk.
- **Schema overgrowth**: mitigate by prioritizing MVP-required fields and deferring optional metadata.
- **Inconsistent source interpretation**: mitigate by citing representative anchors in each artifact section.

---

## 9. Testing Strategy

### Unit Tests
- Validate that each schema field has type, required/optional status, and validation notes.
- Validate taxonomy entries have unique IDs, canonical names, and non-conflicting aliases.

### Integration Tests
- Verify taxonomy terms map cleanly into schema fields and enums using the crosswalk artifact.
- Verify all required domains are represented across references to `axiachat-ai/`, `mxchat-basic/`, and `baachal/`, with explicit `N/A` markers where applicable.

### Manual Testing
- Review artifacts as if starting Session 03 and confirm templates can be authored without re-discovery.
- Cross-check terms and field names against PRD Phase 00 objectives and session stub deliverables.

### Edge Cases
- A capability appears to fit multiple domains - apply primary-domain rule plus secondary-tag note.
- One source plugin lacks a domain implementation - record explicit `N/A (no matching implementation)` marker.
- Two field names imply the same semantic meaning - consolidate under one canonical schema field and document alias mapping.

---

## 10. Dependencies

### External Libraries
- None

### Other Sessions
- **Depends on**: `phase00-session01-reference-inventory-baseline`
- **Depended by**: `phase00-session03-context-pack-decision-log-templates`, `phase00-session04-phase-readiness-handoff-bundle`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
