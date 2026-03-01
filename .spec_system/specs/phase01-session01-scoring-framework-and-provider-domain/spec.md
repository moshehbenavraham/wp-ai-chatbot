# Session Specification

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session operationalizes the Phase 00 scoring rubric into a working capability matrix structure and completes the first domain scoring (DOM-PROVIDER) to validate the framework end-to-end before scaling to the remaining 8 domains. The provider domain is chosen first because it underpins every AI interaction in the plugin and has the richest reference material across all three source plugins.

The session produces two categories of deliverables: (1) a reusable matrix structure that sessions 02-05 will populate for their respective domains, and (2) a complete provider domain scoring with context packs, feature extraction entries, scored comparisons, and a decision log selecting the winner. Both categories exercise the full Phase 00 artifact chain -- anchor index, context pack template, feature unit schema, scoring rubric, and decision log template -- proving the pipeline works before committing to high-volume scoring.

By the end of this session, the team will have a validated, repeatable scoring workflow and a concrete provider domain winner with documented rationale, setting the standard for all subsequent domain sessions.

---

## 2. Objectives

1. Create a canonical capability matrix structure that supports all 9 domains, is reusable across sessions 02-05, and integrates with Phase 00 schema and scoring artifacts.
2. Assemble bounded provider domain context packs from all 3 source plugins using anchor index entries (ANC-PRO-AXI-001, ANC-PRO-MXC-001, ANC-PRO-BAA-001).
3. Extract provider feature units from all 3 sources using the feature_unit_schema.md contract and score them across 5 dimensions (coverage, reliability, security, operability, extensibility).
4. Select the provider domain winner with documented rationale and record adopt/modify/reject decisions per capability using the decision log template.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - Provides anchor index (27 anchors, 9 domains, 3 sources)
- [x] `phase00-session02-domain-taxonomy-feature-schema` - Provides scoring rubric, feature unit schema, domain taxonomy
- [x] `phase00-session03-context-pack-decision-log-templates` - Provides context pack, decision log, and carry-forward templates
- [x] `phase00-session04-phase-readiness-handoff-bundle` - Provides handoff bundle, readiness checklist, and artifact index

### Required Tools/Knowledge
- Phase 00 anchor index for provider domain source code entry points
- Phase 00 scoring rubric with 5 dimensions and decision thresholds
- Phase 00 feature unit schema for extraction contract
- Phase 00 templates (context pack, decision log, carry-forward)

### Environment Requirements
- Read access to reference plugin source directories (axiachat-ai/, mxchat-basic/, baachal/)
- Write access to .spec_system/specs/ for session deliverables

---

## 4. Scope

### In Scope (MVP)
- Scoring rubric operational validation with a trial scoring pass - Confirm rubric dimensions, weights, and thresholds work for real capabilities
- Canonical capability matrix structure creation - Reusable format for all 9 domains across sessions 02-05
- Provider domain context pack assembly from 3 sources - Bounded excerpts using anchor index, not full file reads
- Provider feature unit extraction from axiachat-ai - Provider registry, individual providers (OpenAI, Claude, Gemini), model registry
- Provider feature unit extraction from mxchat-basic - Integrator class, admin configuration
- Provider feature unit extraction from baachal - Main bootstrap provider logic, general settings tab
- Provider capability scoring across 5 dimensions - Coverage, reliability, security, operability, extensibility per source
- Provider domain winner selection and decision log - Adopt/modify/reject per capability with evidence

### Out of Scope (Deferred)
- Scoring of any domain other than DOM-PROVIDER - *Reason: Sessions 02-05 handle remaining 8 domains*
- Implementation code or architecture decisions - *Reason: Phase 02 scope*
- Modifications to Phase 00 scoring rubric weights - *Reason: Only if blocking issue found during validation*

---

## 5. Technical Approach

### Architecture
This is an extraction-and-decision session. No application code is produced. All outputs are structured markdown documents following Phase 00 template contracts. The capability matrix is designed as a domain-indexed document structure where each domain occupies a section with standardized feature entries, scored comparisons, and decision records.

### Design Patterns
- **Template-driven extraction**: All context packs, feature entries, and decisions follow Phase 00 templates to ensure consistency across sessions
- **Anchor-first assembly**: Context packs are assembled starting from anchor index entries, not raw file browsing
- **Bounded-input scoring**: Source excerpts are curated to relevant symbols/sections, not full file dumps
- **Evidence-backed scoring**: Every dimension score requires a one-line evidence note citing source behavior

### Technology Stack
- Markdown documents under `.spec_system/specs/`
- Phase 00 schemas and templates from `.spec_system/templates/` and Phase 00 session directories

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `capability_matrix_template.md` | Reusable matrix structure for all 9 domains | ~120 |
| `provider_context_pack.md` | Bounded source excerpts for DOM-PROVIDER from 3 plugins | ~250 |
| `provider_feature_entries.md` | Feature unit entries per feature_unit_schema contract | ~200 |
| `provider_scored_comparison.md` | 5-dimension scoring for each source with evidence | ~180 |
| `provider_decision_log.md` | Adopt/modify/reject decisions per capability with rationale | ~150 |
| `framework_validation_notes.md` | Rubric usability and matrix reusability observations | ~60 |
| `implementation-notes.md` | Session implementation notes and observations | ~40 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Matrix structure supports all 9 domains and is reusable for sessions 02-05
- [ ] Provider domain has scored entries for all 3 sources across 5 dimensions
- [ ] Provider winner selected with documented rationale and evidence
- [ ] Decision log follows template contract from Phase 00

### Testing Requirements
- [ ] All feature entries validate against feature_unit_schema.md contract
- [ ] All scoring entries use approved dimension IDs and weight formula
- [ ] Cross-check: feature IDs, anchor IDs, and domain IDs match canonical sets

### Non-Functional Requirements
- [ ] Context packs use bounded excerpts, not full file dumps
- [ ] No scoring rubric gaps identified that would block subsequent sessions

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Canonical vocabulary used throughout (DOM-*, ANC-*, SRC-*, FEAT-*)

---

## 8. Implementation Notes

### Key Considerations
- Provider domain has the richest reference material -- AxiaChat has dedicated provider classes, MxChat has an integrator pattern, Baachal has inline provider logic. Expect different abstraction levels.
- The matrix template must accommodate both 3-source and 2-source (with N/A) domains since tools and integrations have Baachal gaps.
- Scoring is directional, not mathematically rigid -- evidence quality matters more than decimal precision.

### Potential Challenges
- **Abstraction level variance**: AxiaChat has a full provider registry with individual classes per provider; Baachal handles providers inline. Mitigation: Score based on behavior outcomes, not code organization.
- **Feature granularity decisions**: Deciding whether "multi-provider support" is one feature or many. Mitigation: Follow the capability_name guidance (3-80 chars, sentence fragment) and break by distinct behavior boundaries.
- **Context budget discipline**: Provider domain anchors reference 8+ files across 3 sources. Mitigation: Extract only the provider-relevant symbols and behaviors, not entire file contents.

### Relevant Considerations
- [P00] **Anchor-first extraction**: Start context assembly from ANC-PRO-* entries in the anchor index, not from browsing source directories.
- [P00] **Canonical vocabulary with alias tables**: All outputs must use DOM-PROVIDER, not "AI providers" or "model layer."
- [P00] **Template-driven bounded input**: Use context_pack_template.md with budget declarations. Enforce three-source coverage rule.
- [P00] **Explicit N/A policy**: If any provider sub-capability is absent in a source, use `na_no_matching_implementation` with rationale rather than scoring as zero.

---

## 9. Testing Strategy

### Unit Tests
- N/A (no application code produced)

### Integration Tests
- N/A (no application code produced)

### Manual Testing
- Verify matrix template can accommodate a domain with 3-source full coverage (provider)
- Verify matrix template can accommodate a domain with 2-source + N/A (tools, integrations)
- Verify scored comparison weighted formula matches rubric specification
- Verify decision log entries follow template contract

### Edge Cases
- Provider capability that exists in only 1 of 3 sources (e.g., Gemini provider adapter only in AxiaChat)
- Provider capability at different maturity levels across sources (production_stable vs beta_or_addon)
- Provider sub-capability that spans secondary domains (e.g., provider + security for API key handling)

---

## 10. Dependencies

### External Libraries
- None (documentation-only session)

### Other Sessions
- **Depends on**: phase00-session01 (anchor index), phase00-session02 (rubric, schema, taxonomy), phase00-session03 (templates), phase00-session04 (handoff bundle)
- **Depended by**: phase01-session02 through phase01-session06 (all require validated matrix structure and scoring workflow)

---

## Reference Anchors

### Provider Domain Source Anchors
- `ANC-PRO-AXI-001`: `axiachat-ai/includes/class-aichat-provider-registry.php`, `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `axiachat-ai/includes/providers/class-gemini-provider.php`, `axiachat-ai/includes/model-registry.php`
- `ANC-PRO-MXC-001`: `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php`
- `ANC-PRO-BAA-001`: `baachal/baachal.php`, `baachal/admin/tabs/general.php`

### Phase 00 Artifact Anchors
- Scoring rubric: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
- Feature unit schema: `specs/phase00-session02-domain-taxonomy-feature-schema/feature_unit_schema.md`
- Domain taxonomy: `specs/phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md`
- Anchor index: `specs/phase00-session01-reference-inventory-baseline/anchor_index.md`
- Context pack template: `templates/context_pack_template.md`
- Decision log template: `templates/decision_log_template.md`
- Carry-forward template: `templates/carry_forward_template.md`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
