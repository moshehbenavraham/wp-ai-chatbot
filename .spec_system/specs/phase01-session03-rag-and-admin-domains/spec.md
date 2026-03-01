# Session Specification

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session extracts, scores, and selects winners for the RAG (DOM-RAG) and Admin (DOM-ADMIN) domains -- the knowledge pipeline and operational control surface that are central to the plugin's value proposition. RAG capabilities determine how the chatbot grounds responses in business-specific content, while Admin capabilities define how operators configure, monitor, and tune the system.

These two domains are paired because they share a strong operational coupling: admin controls are the primary surface through which operators manage the knowledge pipeline. Scoring them together ensures cross-domain interaction points (e.g., admin UI for ingestion settings, index status, and retrieval tuning) are captured in decision rationale rather than discovered later.

The session follows the scoring workflow validated in sessions 01-02: anchor-first context pack assembly, feature extraction per the feature_unit_schema, 5-dimension weighted scoring, and decision logs with cross-domain notes and carry-forward items.

---

## 2. Objectives

1. Assemble bounded context packs for DOM-RAG from all 3 source plugins using defined anchor paths
2. Extract, score, and select winners for RAG capabilities (ingestion, chunking, indexing, retrieval, context assembly, embeddings)
3. Assemble bounded context packs for DOM-ADMIN from all 3 source plugins using defined anchor paths
4. Extract, score, and select winners for Admin capabilities (settings pages, option registration, diagnostics, operational controls)
5. Document RAG-Admin interaction points and carry-forward items for Phase 02 architecture

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - Anchor index with ANC-* IDs for all domains
- [x] `phase00-session02-domain-taxonomy-feature-schema` - Domain taxonomy (DOM-RAG, DOM-ADMIN definitions), feature_unit_schema, scoring rubric
- [x] `phase00-session03-context-pack-decision-log-templates` - Context pack template, decision log template, carry-forward template
- [x] `phase00-session04-phase-readiness-handoff-bundle` - Phase readiness validation, handoff bundle
- [x] `phase01-session01-scoring-framework-and-provider-domain` - Validated scoring framework, capability_matrix_template
- [x] `phase01-session02-runtime-and-migration-domains` - Pattern confirmed across 2 additional domains (runtime, migration)

### Required Tools/Knowledge
- Feature maps: `docs/axiachat-ai-feature-map.md`, `docs/mxchat-basic-feature-map.md`, `docs/baachal-feature-map.md`
- Scoring rubric from `phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
- Capability matrix template from `phase01-session01-scoring-framework-and-provider-domain/capability_matrix_template.md`
- Context pack and decision log templates from `phase00-session03`

### Environment Requirements
- Read access to reference plugin directories: `axiachat-ai/`, `mxchat-basic/`, `baachal/`
- No build tools or runtime environments required (documentation-only session)

---

## 4. Scope

### In Scope (MVP)
- RAG domain context packs -- bounded excerpts from 3 source plugins covering ingestion, chunking, indexing, retrieval, embeddings, and context assembly
- RAG feature unit extraction -- FEAT-*-RAG-* entries per feature_unit_schema contract
- RAG scored comparison -- 5-dimension weighted scoring for all RAG capabilities across 3 sources
- RAG decision log -- DEC-RAG-* entries with adopt/modify/reject decisions and rationale
- Admin domain context packs -- bounded excerpts from 3 source plugins covering settings, options, diagnostics, and operational controls
- Admin feature unit extraction -- FEAT-*-ADM-* entries per feature_unit_schema contract
- Admin scored comparison -- 5-dimension weighted scoring for all Admin capabilities across 3 sources
- Admin decision log -- DEC-ADM-* entries with adopt/modify/reject decisions and rationale
- Cross-domain interaction notes (RAG-Admin coupling points)
- Carry-forward items (CFI-RAG-*, CFI-ADM-*) for Phase 02

### Out of Scope (Deferred)
- RAG architecture or storage implementation decisions - *Reason: Phase 02+ (architecture phase)*
- Admin UI wireframes or layout decisions - *Reason: Phase 02+ (architecture phase)*
- Domains other than DOM-RAG and DOM-ADMIN - *Reason: Covered by other Phase 01 sessions*
- Embedding provider selection or vector DB choices - *Reason: Phase 02 architecture decisions*

---

## 5. Technical Approach

### Architecture
This is a documentation and analysis session. No code is produced. The technical approach is the established scoring workflow:

1. **Context pack assembly**: Read anchor paths for each domain, extract bounded code excerpts with budget tracking (~48k tokens per domain target)
2. **Feature extraction**: Identify discrete capabilities per source, assign FEAT-* IDs, populate all schema fields
3. **Scoring**: Apply 5-dimension weighted rubric (Coverage 30%, Reliability 20%, Security 20%, Operability 15%, Extensibility 15%)
4. **Decision logging**: Select winners per capability with rationale, flag modifications needed, create CFI-* items

### Design Patterns
- **Anchor-first extraction**: Start from ANC-* paths, not unbounded browsing
- **Bounded context packs**: Budget-tracked excerpts prevent context bloat
- **Explicit N/A policy**: Missing capabilities get `N/A (no matching implementation)` entries with rationale
- **Cross-domain notes**: Bidirectional dependency tracking between RAG and Admin

### Technology Stack
- Markdown documentation artifacts
- Canonical vocabulary from domain taxonomy (DOM-RAG, DOM-ADMIN, SRC-AXI, SRC-MXC, SRC-BAA)

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `rag_context_pack.md` | Bounded source excerpts for DOM-RAG from 3 plugins | ~200 |
| `rag_feature_entries.md` | FEAT-*-RAG-* entries per feature_unit_schema | ~250 |
| `rag_scored_comparison.md` | 5-dimension scoring for RAG capabilities x 3 sources | ~280 |
| `rag_decision_log.md` | DEC-RAG-* decisions with rationale and CFI items | ~350 |
| `admin_context_pack.md` | Bounded source excerpts for DOM-ADMIN from 3 plugins | ~180 |
| `admin_feature_entries.md` | FEAT-*-ADM-* entries per feature_unit_schema | ~230 |
| `admin_scored_comparison.md` | 5-dimension scoring for Admin capabilities x 3 sources | ~250 |
| `admin_decision_log.md` | DEC-ADM-* decisions with rationale and CFI items | ~320 |
| `implementation-notes.md` | Task execution log and session notes | ~250 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] RAG domain has scored entries for all 3 sources across 5 dimensions
- [ ] RAG winner selected with documented rationale (highest complexity domain)
- [ ] Admin domain has scored entries for all 3 sources across 5 dimensions
- [ ] Admin winner selected with documented rationale
- [ ] RAG-Admin interaction points noted (admin controls for knowledge pipeline)
- [ ] All feature entries comply with feature_unit_schema contract
- [ ] All scores follow the validated 5-dimension weighted rubric
- [ ] Explicit N/A entries for missing capabilities with rationale

### Testing Requirements
- [ ] Cross-reference validation: all FEAT IDs unique and follow naming convention
- [ ] Score arithmetic verified: weighted totals match dimension scores
- [ ] Decision log entries reference correct FEAT and ANC IDs
- [ ] Carry-forward items (CFI-*) have actionable descriptions for Phase 02

### Non-Functional Requirements
- [ ] Context budget per domain stays within ~48k token target
- [ ] All artifacts follow canonical vocabulary from domain taxonomy

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Consistent structure with sessions 01-02 deliverables

---

## 8. Implementation Notes

### Key Considerations
- RAG is the highest-complexity domain in the project -- AxiaChat has the deepest ingestion pipeline (contexto-functions, web scraper, background indexing), MxChat has queue processing and Pinecone integration, Baachal has lightweight site indexing
- Admin domain spans configuration, diagnostics, and operational controls -- all 3 sources have distinct approaches to settings registration and UI organization
- RAG-Admin coupling is strong: knowledge pipeline status, ingestion controls, and retrieval tuning are all admin-surface concerns

### Potential Challenges
- **RAG scope breadth**: AxiaChat's RAG surface is large (contexto-functions, contexto-create, contexto-modify, web scraper). Mitigation: focus on capability boundaries, not exhaustive code reading
- **Admin boundary ambiguity**: Admin features overlap with every other domain (provider settings, runtime config, RAG controls). Mitigation: scope DOM-ADMIN to settings infrastructure, option registration, diagnostics, and operational controls -- not domain-specific settings UI
- **Feature granularity for RAG**: Many sub-capabilities (ingestion sources, chunking strategies, embedding providers, retrieval ranking). Mitigation: group into 4-5 scored capabilities rather than scoring each sub-feature independently

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned #1): Start from ANC-RAG-* and ANC-ADM-* paths defined in session stub, not unbounded browsing
- **Explicit N/A policy** (Lesson Learned #5): Baachal has limited RAG depth -- ensure N/A entries are created with rationale rather than omitted
- **Canonical vocabulary** (Lesson Learned #2): Normalize all domain and capability references through the alias table before recording

---

## 9. Testing Strategy

### Unit Tests
- N/A (documentation-only session)

### Integration Tests
- N/A (documentation-only session)

### Manual Testing
- Verify all FEAT-* IDs are unique and follow `FEAT-{SRC}-{DOM}-{NNN}` convention
- Verify all DEC-* IDs are unique and follow `DEC-{DOM}-{NNN}` convention
- Verify weighted score arithmetic: `(cov*0.30) + (rel*0.20) + (sec*0.20) + (ops*0.15) + (ext*0.15)`
- Verify all anchor references (ANC-*) exist in session 01 anchor index
- Verify cross-domain notes reference valid domain IDs
- Verify carry-forward items have actionable Phase 02 descriptions

### Edge Cases
- AxiaChat RAG has multiple sub-systems (contexto, web scraper, background jobs) -- ensure these are scored as grouped capabilities, not fragmented
- Baachal has minimal RAG (content-indexer only) -- ensure explicit N/A entries for missing sub-capabilities
- Admin settings that are domain-specific (e.g., provider API key fields) belong to their respective domains, not DOM-ADMIN

---

## 10. Dependencies

### External Libraries
- None (documentation-only session)

### Other Sessions
- **Depends on**: `phase01-session01` (scoring framework, capability_matrix_template), `phase01-session02` (pattern validation)
- **Depended by**: `phase01-session04` (Security, Tools, Integrations domains may reference RAG/Admin interaction points), `phase01-session05` (WooCommerce domain references RAG retrieval patterns), `phase01-session06` (winner consolidation aggregates all domain decisions)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
