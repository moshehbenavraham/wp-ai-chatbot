# Session Specification

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session extracts, scores, and selects winners for the Runtime (DOM-RUNTIME) and Migration (DOM-MIGRATION) domains -- the two foundational domains that govern chat request lifecycle and upgrade compatibility. These domains are paired because runtime behavior directly shapes migration constraints: how sessions are stored, how request state is managed, and how streaming is implemented all determine what must be preserved or migrated during plugin upgrades.

Using the scoring framework and capability matrix template validated in Session 01, this session assembles bounded context packs from all three reference plugins, extracts feature units per the feature_unit_schema contract, scores capabilities across the 5-dimension rubric (Coverage, Reliability, Security, Operability, Extensibility), and records adopt/investigate/reject decisions with rationale and cross-domain interaction notes.

The outputs feed directly into Sessions 03-05 (remaining domain scoring) and Session 06 (winner consolidation). Runtime decisions will shape Phase 05 (Chat Runtime implementation) and migration decisions will shape Phase 10 (Migration and Compatibility).

---

## 2. Objectives

1. Assemble bounded context packs for DOM-RUNTIME and DOM-MIGRATION from all 3 reference plugins within token budget constraints.
2. Extract 3-6 feature units per source per domain following the feature_unit_schema contract with complete field coverage.
3. Score all extracted capabilities across 5 dimensions using the validated rubric, with one-line evidence per dimension per score.
4. Record domain-level decisions (adopt/adopt-with-modifications/reject) with rationale, carry-forward items, and cross-domain interaction notes for downstream sessions.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase01-session01-scoring-framework-and-provider-domain` - Provides validated scoring rubric, capability matrix template, feature entry format, decision log format, and framework validation notes

### Required Tools/Knowledge
- Scoring rubric (5 dimensions, weighted formula, band thresholds) from Session 01
- Capability matrix template (`capability_matrix_template.md`) from Session 01
- Feature unit schema from Phase 00 (`feature_unit_schema.md`)
- Domain taxonomy and anchor registry from Phase 00

### Environment Requirements
- Read access to all three reference plugin directories
- No build tools, Docker, or runtime environment needed (documentation-only session)

---

## 4. Scope

### In Scope (MVP)
- Business operator needs reliable chat request lifecycle -- extract and score runtime message handling, session management, streaming, and frontend state patterns from all 3 sources
- Business operator needs safe plugin upgrades -- extract and score migration lifecycle, schema versioning, settings migration, and uninstall cleanup from all 3 sources
- Context packs bounded within ~48k token budget per domain with curated excerpts (not raw file dumps)
- Cross-domain interaction notes between DOM-RUNTIME, DOM-MIGRATION, and other scored/pending domains

### Out of Scope (Deferred)
- Chat UX/frontend visual design decisions -- *Reason: Phase 02+ architecture concern*
- Actual migration code or schema implementation -- *Reason: Phase 03 (skeleton) and Phase 10 (migration)*
- Domains other than DOM-RUNTIME and DOM-MIGRATION -- *Reason: Sessions 03-05 scope*
- Provider-layer integration details -- *Reason: Already decided in Session 01 (DOM-PROVIDER)*

---

## 5. Technical Approach

### Architecture
This is a documentation and analysis session. No application code is produced. The work follows a structured extraction-scoring-decision pipeline established in Session 01.

### Design Patterns
- **Anchor-first extraction**: Start from defined ANC-* anchor paths to prevent unbounded file browsing
- **Bounded context packs**: Curated excerpts scoped to relevant symbols/sections within token budgets
- **Template-driven scoring**: Reuse capability_matrix_template.md structure for consistent cross-session comparison
- **Evidence-grounded scoring**: One-line evidence per dimension per score, referencing observable code behavior

### Technology Stack
- Markdown for all deliverables (ASCII-only, Unix LF)
- Scoring rubric: 5 dimensions (Coverage 30%, Reliability 20%, Security 20%, Operability 15%, Extensibility 15%)
- Band thresholds: adopt >= 3.8, investigate 2.8-3.7, reject < 2.8

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `runtime_context_pack.md` | Bounded source excerpts for DOM-RUNTIME from 3 plugins | ~300 |
| `runtime_feature_entries.md` | Feature unit entries for DOM-RUNTIME (3-6 per source) | ~250 |
| `runtime_scored_comparison.md` | 5-dimension scoring across 3 sources for runtime capabilities | ~350 |
| `runtime_decision_log.md` | Adopt/modify/reject decisions with rationale and cross-domain notes | ~300 |
| `migration_context_pack.md` | Bounded source excerpts for DOM-MIGRATION from 3 plugins | ~250 |
| `migration_feature_entries.md` | Feature unit entries for DOM-MIGRATION (3-6 per source) | ~200 |
| `migration_scored_comparison.md` | 5-dimension scoring across 3 sources for migration capabilities | ~300 |
| `migration_decision_log.md` | Adopt/modify/reject decisions with rationale and cross-domain notes | ~250 |
| `implementation-notes.md` | Task progress log and session notes | ~100 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Runtime domain has scored entries for all 3 sources across 5 dimensions
- [ ] Runtime winner selected with documented rationale per capability
- [ ] Migration domain has scored entries for all 3 sources across 5 dimensions
- [ ] Migration winner selected with documented rationale per capability
- [ ] Cross-domain interactions between runtime and migration noted
- [ ] Cross-domain interactions with DOM-PROVIDER (Session 01) noted
- [ ] Carry-forward items recorded for Phase 02+ modifications

### Testing Requirements
- [ ] All scoring uses validated rubric (dimensions, weights, thresholds)
- [ ] Feature entries follow feature_unit_schema contract
- [ ] Evidence notes provided for every dimension score
- [ ] N/A entries use explicit na_reason (if any source lacks capability)

### Non-Functional Requirements
- [ ] Context packs within ~48k token budget per domain
- [ ] 3-6 features per source per domain (medium granularity)

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Canonical vocabulary used throughout (DOM-RUNTIME, DOM-MIGRATION, SRC-AXI, SRC-MXC, SRC-BAA)
- [ ] Code follows project conventions per CONVENTIONS.md

---

## 8. Implementation Notes

### Key Considerations
- Runtime domain is broad -- focus on 4 logical capabilities: message lifecycle, session handling, streaming, frontend state management
- Migration domain maps to 3 logical capabilities: plugin lifecycle, schema versioning, settings/data migration
- SRC-BAA has minimal migration infrastructure (simple activate/deactivate) -- expect N/A or low scores in some areas
- SRC-MXC has the deepest streaming implementation -- may score well on that specific capability even if overall runtime score is lower
- Cross-capability aggregation should use simple averaging (same method as Session 01)

### Potential Challenges
- **Runtime scope creep**: Runtime touches many domains (provider, admin, security). Keep focus on request lifecycle and state management; note cross-domain interactions but do not score non-runtime concerns.
- **Migration feature sparsity**: Some sources may have minimal migration code. Use explicit N/A entries with rationale rather than inflating thin implementations.
- **Streaming classification**: Streaming bridges DOM-RUNTIME and DOM-PROVIDER. Score streaming delivery mechanism under runtime; provider-specific SSE parsing was already noted in Session 01 (CFI-PRO-001).

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned #1): Continue using ANC-* anchor paths as extraction starting points to prevent context bloat
- **Canonical vocabulary with alias tables** (Lesson Learned #2): Use DOM-RUNTIME and DOM-MIGRATION consistently; normalize any alternate labels through alias table
- **Explicit N/A policy** (Lesson Learned #5): If a source lacks a migration or runtime capability, create explicit N/A entries with na_reason rather than omitting
- **Template-driven bounded input** (Lesson Learned #3): Context packs must cite anchors and declare token budgets per slice

---

## 9. Testing Strategy

### Unit Tests
- N/A (documentation-only session)

### Integration Tests
- N/A (documentation-only session)

### Manual Testing
- Verify all scoring follows rubric dimensions and weights
- Verify weighted totals and band assignments are arithmetically correct
- Verify feature entries have complete field coverage per schema
- Cross-reference decision rationale against scored evidence

### Edge Cases
- Source plugin with no formal migration code (expect SRC-BAA) -- handle with explicit N/A entries
- Capability that spans multiple domains (streaming) -- score delivery mechanism under runtime, note provider-layer dependency
- Capability with identical scores across sources -- break tie with evidence specificity and modification cost

---

## 10. Dependencies

### External Libraries
- None (documentation-only session)

### Other Sessions
- **Depends on**: `phase01-session01-scoring-framework-and-provider-domain` (scoring rubric, matrix template, feature schema)
- **Depended by**: `phase01-session03-rag-and-admin-domains`, `phase01-session04-security-tools-and-integrations-domains`, `phase01-session05-woocommerce-and-cross-domain-resolution`, `phase01-session06-winner-consolidation-and-phase-handoff`

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

## Next Steps

Run `/implement` to begin AI-led implementation.
