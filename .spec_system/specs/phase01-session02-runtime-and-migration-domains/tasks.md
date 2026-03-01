# Task Checklist

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Total Tasks**: 20
**Estimated Duration**: 3-4 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0102]` = Session reference (phase 01, session 02)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 4 | 4 | 0 |
| Implementation | 10 | 10 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **20** | **20** | **0** |

---

## Setup (2 tasks)

Initial configuration and prerequisite verification.

- [x] T001 [S0102] Verify prerequisites met: Session 01 complete, scoring rubric operational, capability matrix template available, feature unit schema accessible (`phase01-session01-scoring-framework-and-provider-domain/`)
- [x] T002 [S0102] Create session output file stubs for all 8 deliverables plus implementation-notes.md (`phase01-session02-runtime-and-migration-domains/`)

---

## Foundation (4 tasks)

Context pack assembly and feature unit extraction for both domains.

- [x] T003 [S0102] [P] Assemble runtime domain context pack with bounded excerpts from ANC-RUN-AXI-001 (`axiachat-ai/includes/class-aichat-ajax.php`, `axiachat-ai/assets/js/aichat-frontend.js`), ANC-RUN-MXC-001 (`mxchat-basic/includes/class-mxchat-public.php`, `mxchat-basic/js/chat-script.js`, `mxchat-basic/includes/class-mxchat-integrator.php`), ANC-RUN-BAA-001 (`baachal/baachal.php`, `baachal/assets/chatbot.js`, `baachal/templates/chatbot-widget.php`) -- token budget ~48k (`runtime_context_pack.md`)
- [x] T004 [S0102] [P] Assemble migration domain context pack with bounded excerpts from ANC-MIG-AXI-001 (`axiachat-ai/axiachat-ai.php`), ANC-MIG-MXC-001 (`mxchat-basic/mxchat-basic.php`), ANC-MIG-BAA-001 (`baachal/baachal.php`, `baachal/uninstall.php`) -- token budget ~48k (`migration_context_pack.md`)
- [x] T005 [S0102] [P] Extract runtime domain feature entries (3-6 per source, ~9-18 total) following feature_unit_schema contract with anchor citations and maturity/risk/dependency fields; use explicit N/A entries where source lacks capability (`runtime_feature_entries.md`)
- [x] T006 [S0102] [P] Extract migration domain feature entries (3-6 per source, ~9-18 total) following feature_unit_schema contract with anchor citations and maturity/risk/dependency fields; use explicit N/A entries where source lacks capability (`migration_feature_entries.md`)

---

## Implementation (10 tasks)

Scoring capabilities and recording domain decisions.

### DOM-RUNTIME Scoring (5 tasks)

- [x] T007 [S0102] Score runtime capability 1: Message lifecycle and request handling -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; reference ANC-RUN-AXI-001 (`axiachat-ai/includes/class-aichat-ajax.php`), ANC-RUN-MXC-001 (`mxchat-basic/includes/class-mxchat-public.php`), ANC-RUN-BAA-001 (`baachal/baachal.php`) (`runtime_scored_comparison.md`)
- [x] T008 [S0102] Score runtime capability 2: Session handling and conversation state -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; reference ANC-RUN-AXI-001, ANC-RUN-MXC-001, ANC-RUN-BAA-001 (`runtime_scored_comparison.md`)
- [x] T009 [S0102] Score runtime capability 3: Streaming and real-time delivery -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; note cross-domain link to DOM-PROVIDER (CFI-PRO-001) (`runtime_scored_comparison.md`)
- [x] T010 [S0102] Score runtime capability 4: Frontend state management and UX runtime -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; reference JS assets from all 3 sources (`runtime_scored_comparison.md`)
- [x] T011 [S0102] Record runtime domain decisions (adopt/modify/reject per capability) with rationale, carry-forward items (CFI-RUN-*), and cross-domain interaction notes for DOM-PROVIDER, DOM-ADMIN, DOM-SECURITY, DOM-MIGRATION (`runtime_decision_log.md`)

### DOM-MIGRATION Scoring (5 tasks)

- [x] T012 [S0102] Score migration capability 1: Plugin lifecycle management (activate/deactivate/uninstall) -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; reference ANC-MIG-AXI-001 (`axiachat-ai/axiachat-ai.php`), ANC-MIG-MXC-001 (`mxchat-basic/mxchat-basic.php`), ANC-MIG-BAA-001 (`baachal/baachal.php`, `baachal/uninstall.php`) (`migration_scored_comparison.md`)
- [x] T013 [S0102] Score migration capability 2: Schema versioning and upgrade routines -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; use explicit N/A entries if source lacks versioned schema upgrades (`migration_scored_comparison.md`)
- [x] T014 [S0102] Score migration capability 3: Settings migration, compatibility repair, and data cleanup -- score SRC-AXI, SRC-MXC, SRC-BAA across 5 dimensions with evidence notes; note cross-domain link to DOM-ADMIN (`migration_scored_comparison.md`)
- [x] T015 [S0102] Record migration domain decisions (adopt/modify/reject per capability) with rationale, carry-forward items (CFI-MIG-*), and cross-domain interaction notes for DOM-RUNTIME, DOM-ADMIN, DOM-SECURITY (`migration_decision_log.md`)
- [x] T016 [S0102] Record cross-domain interaction summary: DOM-RUNTIME x DOM-MIGRATION bidirectional dependencies, plus interactions with DOM-PROVIDER (Session 01), DOM-RAG, DOM-ADMIN (Sessions 03+) (`runtime_decision_log.md`, `migration_decision_log.md`)

---

## Testing (4 tasks)

Verification and quality assurance.

- [x] T017 [S0102] Validate scoring consistency: verify weighted totals match formula (cov*0.30 + rel*0.20 + sec*0.20 + ops*0.15 + ext*0.15), band assignments correct, evidence notes present for every score (`runtime_scored_comparison.md`, `migration_scored_comparison.md`)
- [x] T018 [S0102] Validate feature entry completeness: verify all feature_unit_schema fields populated, anchor citations present, N/A entries have na_reason, 3-6 features per source per domain (`runtime_feature_entries.md`, `migration_feature_entries.md`)
- [x] T019 [S0102] Validate ASCII encoding and Unix LF line endings on all session files; verify canonical vocabulary (DOM-*, ANC-*, SRC-*, FEAT-*, DEC-*, CFI-*) used throughout (`phase01-session02-runtime-and-migration-domains/`)
- [x] T020 [S0102] Update implementation-notes.md with task completion log, key findings, and session summary (`implementation-notes.md`)

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All tests passing (N/A -- documentation session)
- [x] All files ASCII-encoded
- [x] implementation-notes.md updated
- [x] Ready for `/validate`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
