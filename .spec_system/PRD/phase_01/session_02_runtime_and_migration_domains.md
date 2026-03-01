# Session 02: Runtime and Migration Domains

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Status**: Not Started
**Estimated Tasks**: ~16
**Estimated Duration**: 2-4 hours

---

## Objective

Extract, score, and select winners for the Runtime (DOM-RUNTIME) and Migration (DOM-MIGRATION) domains -- the two foundational domains that govern chat request lifecycle and upgrade compatibility.

---

## Scope

### In Scope (MVP)
- Assemble runtime domain context packs from all 3 sources
- Extract runtime feature units (message lifecycle, session handling, streaming, state management)
- Score runtime capabilities across 5 dimensions
- Record runtime domain decisions
- Assemble migration domain context packs from all 3 sources
- Extract migration feature units (upgrade routines, schema changes, compatibility repair)
- Score migration capabilities across 5 dimensions
- Record migration domain decisions

### Out of Scope
- Chat UX/frontend implementation decisions (Phase 02+)
- Actual migration code or schema design
- Domains other than DOM-RUNTIME and DOM-MIGRATION

---

## Prerequisites

- [ ] Session 01 completed (matrix structure validated)
- [ ] Capability matrix template operational
- [ ] Scoring rubric operational (validated in Session 01)

---

## Reference Anchors

### Runtime
- `ANC-RUN-AXI-001`: `axiachat-ai/includes/class-aichat-ajax.php`, `axiachat-ai/assets/js/aichat-frontend.js`
- `ANC-RUN-MXC-001`: `mxchat-basic/includes/class-mxchat-public.php`, `mxchat-basic/js/chat-script.js`, `mxchat-basic/includes/class-mxchat-integrator.php`
- `ANC-RUN-BAA-001`: `baachal/baachal.php`, `baachal/assets/chatbot.js`, `baachal/templates/chatbot-widget.php`

### Migration
- `ANC-MIG-AXI-001`: `axiachat-ai/axiachat-ai.php`
- `ANC-MIG-MXC-001`: `mxchat-basic/mxchat-basic.php`
- `ANC-MIG-BAA-001`: `baachal/baachal.php`, `baachal/uninstall.php`

---

## Deliverables

1. Runtime domain context pack (bounded excerpts from 3 sources)
2. Runtime domain feature extraction entries
3. Runtime domain scored comparison and decision log
4. Migration domain context pack (bounded excerpts from 3 sources)
5. Migration domain feature extraction entries
6. Migration domain scored comparison and decision log

---

## Success Criteria

- [ ] Runtime domain has scored entries for all 3 sources across 5 dimensions
- [ ] Runtime winner selected with documented rationale
- [ ] Migration domain has scored entries for all 3 sources across 5 dimensions
- [ ] Migration winner selected with documented rationale
- [ ] Cross-domain interactions between runtime and migration noted for Session 05
