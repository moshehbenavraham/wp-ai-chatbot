# Session 01: Scoring Framework and Provider Domain

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 2-4 hours

---

## Objective

Operationalize the Phase 00 scoring rubric into a working matrix structure and complete the first domain scoring (DOM-PROVIDER) to validate the framework end-to-end before scaling to remaining domains.

---

## Scope

### In Scope (MVP)
- Validate scoring rubric operational readiness with a trial scoring pass
- Create the canonical capability matrix structure (master spreadsheet/document format)
- Assemble provider domain context packs from all 3 sources using anchor index
- Extract provider feature units from `axiachat-ai/`, `mxchat-basic/`, `baachal/`
- Score provider capabilities across 5 dimensions (coverage, reliability, security, operability, extensibility)
- Record provider domain decisions (adopt / adopt-with-modifications / reject per capability)
- Produce provider domain decision log using template

### Out of Scope
- Scoring of any domain other than DOM-PROVIDER
- Implementation code or architecture decisions
- Modifications to Phase 00 scoring rubric weights (unless blocking issue found)

---

## Prerequisites

- [ ] Phase 00 handoff bundle reviewed (`specs/phase00-session04-*/handoff_bundle.md`)
- [ ] Scoring rubric available (`specs/phase00-session02-*/scoring_rubric.md`)
- [ ] Anchor index available (`specs/phase00-session01-*/anchor_index.md`)
- [ ] Context pack template available (`templates/context_pack_template.md`)
- [ ] Decision log template available (`templates/decision_log_template.md`)

---

## Reference Anchors

- `ANC-PRO-AXI-001`: `axiachat-ai/includes/class-aichat-provider-registry.php`, `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `axiachat-ai/includes/providers/class-gemini-provider.php`, `axiachat-ai/includes/model-registry.php`
- `ANC-PRO-MXC-001`: `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php`
- `ANC-PRO-BAA-001`: `baachal/baachal.php`, `baachal/admin/tabs/general.php`

---

## Deliverables

1. Canonical capability matrix structure (template for all 9 domains)
2. Provider domain context pack (bounded excerpts from 3 sources)
3. Provider domain feature extraction entries (per `feature_unit_schema.md`)
4. Provider domain scored comparison (5 dimensions per source)
5. Provider domain decision log (adopt/modify/reject per capability)
6. Framework validation notes (rubric usability observations)

---

## Success Criteria

- [ ] Matrix structure supports all 9 domains and is reusable for sessions 02-05
- [ ] Provider domain has scored entries for all 3 sources across 5 dimensions
- [ ] Provider winner selected with documented rationale and evidence
- [ ] Decision log follows template contract
- [ ] No scoring rubric gaps identified that would block subsequent sessions
