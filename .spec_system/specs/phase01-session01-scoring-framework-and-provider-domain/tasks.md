# Task Checklist

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Total Tasks**: 18
**Estimated Duration**: 2-3 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0101]` = Session reference (phase 01, session 01)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 5 | 5 | 0 |
| Implementation | 8 | 8 | 0 |
| Testing | 3 | 3 | 0 |
| **Total** | **18** | **18** | **0** |

---

## Setup (2 tasks)

Initial verification and environment preparation.

- [x] T001 [S0101] Verify Phase 00 prerequisites accessible: scoring rubric (`specs/phase00-session02-*/scoring_rubric.md`), anchor index (`specs/phase00-session01-*/anchor_index.md`), feature unit schema (`specs/phase00-session02-*/feature_unit_schema.md`), context pack template (`templates/context_pack_template.md`), decision log template (`templates/decision_log_template.md`)
- [x] T002 [S0101] Create session output directory and initialize empty deliverable files: `capability_matrix_template.md`, `provider_context_pack.md`, `provider_feature_entries.md`, `provider_scored_comparison.md`, `provider_decision_log.md`, `framework_validation_notes.md`, `implementation-notes.md`

---

## Foundation (5 tasks)

Matrix structure creation and context pack assembly.

- [x] T003 [S0101] Create canonical capability matrix structure (`capability_matrix_template.md`) supporting all 9 domains, integrating feature_unit_schema fields, scoring rubric dimensions, and decision thresholds; Ref: `specs/phase00-session02-*/scoring_rubric.md`, `specs/phase00-session02-*/feature_unit_schema.md`
- [x] T004 [S0101] [P] Assemble provider domain context pack from SRC-AXI: extract bounded excerpts from `axiachat-ai/includes/class-aichat-provider-registry.php`, `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `axiachat-ai/includes/providers/class-gemini-provider.php`, `axiachat-ai/includes/model-registry.php` using context pack template (`templates/context_pack_template.md`); Ref: ANC-PRO-AXI-001
- [x] T005 [S0101] [P] Assemble provider domain context pack from SRC-MXC: extract bounded excerpts from `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php` using context pack template; Ref: ANC-PRO-MXC-001
- [x] T006 [S0101] [P] Assemble provider domain context pack from SRC-BAA: extract bounded excerpts from `baachal/baachal.php`, `baachal/admin/tabs/general.php` using context pack template; Ref: ANC-PRO-BAA-001
- [x] T007 [S0101] Consolidate 3-source context packs into unified `provider_context_pack.md` with budget declaration and anchor citations per context pack template contract

---

## Implementation (8 tasks)

Feature extraction, scoring, and decision recording.

- [x] T008 [S0101] [P] Extract provider feature units from axiachat-ai sources into `provider_feature_entries.md` using feature_unit_schema.md contract (provider registry, OpenAI adapter, Claude adapter, Gemini adapter, model registry); Ref: ANC-PRO-AXI-001, `specs/phase00-session02-*/feature_unit_schema.md`
- [x] T009 [S0101] [P] Extract provider feature units from mxchat-basic sources into `provider_feature_entries.md` using feature_unit_schema.md contract (integrator pattern, admin provider config); Ref: ANC-PRO-MXC-001, `specs/phase00-session02-*/feature_unit_schema.md`
- [x] T010 [S0101] [P] Extract provider feature units from baachal sources into `provider_feature_entries.md` using feature_unit_schema.md contract (inline provider logic, general settings); Ref: ANC-PRO-BAA-001, `specs/phase00-session02-*/feature_unit_schema.md`
- [x] T011 [S0101] Score SRC-AXI provider capabilities across 5 dimensions (coverage, reliability, security, operability, extensibility) with evidence notes per scoring rubric into `provider_scored_comparison.md`; Ref: `specs/phase00-session02-*/scoring_rubric.md`
- [x] T012 [S0101] Score SRC-MXC provider capabilities across 5 dimensions with evidence notes per scoring rubric into `provider_scored_comparison.md`; Ref: `specs/phase00-session02-*/scoring_rubric.md`
- [x] T013 [S0101] Score SRC-BAA provider capabilities across 5 dimensions with evidence notes per scoring rubric into `provider_scored_comparison.md`; Ref: `specs/phase00-session02-*/scoring_rubric.md`
- [x] T014 [S0101] Select provider domain winner and record adopt/modify/reject decisions per capability into `provider_decision_log.md` using decision log template; include tie-break rationale if applicable; Ref: `templates/decision_log_template.md`
- [x] T015 [S0101] Record cross-domain interaction notes for DOM-PROVIDER boundaries with DOM-RUNTIME, DOM-SECURITY, DOM-ADMIN, and DOM-MIGRATION; append to `provider_decision_log.md` for consumption by sessions 02-05

---

## Testing (3 tasks)

Validation and quality assurance.

- [x] T016 [S0101] Validate all feature entries in `provider_feature_entries.md` against feature_unit_schema.md contract: required fields present, enum values valid, feature_id and anchor_id formats correct, N/A entries have na_reason; Ref: `specs/phase00-session02-*/feature_unit_schema.md`
- [x] T017 [S0101] Record framework validation notes in `framework_validation_notes.md`: rubric usability, matrix reusability for sessions 02-05, evidence logging clarity, any recommended adjustments for subsequent sessions
- [x] T018 [S0101] Validate ASCII encoding on all deliverable files and verify Unix LF line endings; confirm canonical vocabulary (DOM-*, ANC-*, SRC-*, FEAT-*) used consistently throughout all outputs

---

## Completion Checklist

Before marking session complete:

- [ ] All tasks marked `[x]`
- [ ] All feature entries validate against schema contract
- [ ] Matrix structure tested for 3-source and 2-source (with N/A) domain compatibility
- [ ] Provider winner selected with scored evidence and decision log
- [ ] implementation-notes.md updated
- [ ] Ready for `/validate`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
