# Session Specification

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session extracts, scores, and selects winners for the Security (DOM-SECURITY), Tools (DOM-TOOLS), and Integrations (DOM-INTEGRATIONS) domains. These three domains share cross-cutting trust-boundary concerns: security gates guard tool execution and integration endpoints, tools invoke capabilities that must pass sanitization and capability checks, and integrations bridge external systems that require authentication and audit controls.

Pairing these domains recognizes their operational coupling and the known parity gaps for Baachal. Tools and Integrations are domains where Baachal has no standalone implementation, requiring explicit N/A scoring entries following the policy validated in earlier sessions. Security, by contrast, is present in all three sources but expressed differently -- from dedicated moderation and sanitization helpers in AxiaChat, to inline nonce/capability patterns in MxChat, to minimal but consistent guards in Baachal.

The session follows the scoring workflow validated in sessions 01-03: anchor-first context pack assembly, feature extraction per the feature_unit_schema, 5-dimension weighted scoring, and decision logs with cross-domain notes and carry-forward items. This brings the scored domain count to 8 of 9, with only WooCommerce remaining for session 05.

---

## 2. Objectives

1. Assemble bounded context packs for DOM-SECURITY from all 3 source plugins using defined anchor paths
2. Extract, score, and select winners for Security capabilities (nonce checks, capability enforcement, sanitization/escaping, moderation, privacy controls, audit trails)
3. Assemble bounded context packs for DOM-TOOLS from AxiaChat and MxChat (Baachal scored as explicit N/A)
4. Extract, score, and select winners for Tools capabilities (callable tools, macro registry, schema validation, execution callbacks, tool lifecycle)
5. Assemble bounded context packs for DOM-INTEGRATIONS from AxiaChat and MxChat (Baachal scored as explicit N/A)
6. Extract, score, and select winners for Integrations capabilities (MCP client, webhooks, external channels, add-on framework)
7. Document cross-domain interaction points (Security-Tools trust boundaries, Security-Integrations auth gates) and carry-forward items for Phase 02

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - Anchor index with ANC-* IDs for all domains
- [x] `phase00-session02-domain-taxonomy-feature-schema` - Domain taxonomy (DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS definitions), feature_unit_schema, scoring rubric
- [x] `phase00-session03-context-pack-decision-log-templates` - Context pack template, decision log template, carry-forward template
- [x] `phase00-session04-phase-readiness-handoff-bundle` - Phase readiness validation, handoff bundle
- [x] `phase01-session01-scoring-framework-and-provider-domain` - Validated scoring framework, capability_matrix_template
- [x] `phase01-session02-runtime-and-migration-domains` - Pattern confirmed across runtime and migration domains
- [x] `phase01-session03-rag-and-admin-domains` - RAG and Admin domains scored; Admin cross-domain notes relevant to security controls

### Required Tools/Knowledge
- Feature maps: `docs/axiachat-ai-feature-map.md`, `docs/mxchat-basic-feature-map.md`, `docs/baachal-feature-map.md`
- Scoring rubric from `phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
- Capability matrix template from `phase01-session01-scoring-framework-and-provider-domain/capability_matrix_template.md`
- Context pack and decision log templates from `phase00-session03`
- N/A scoring policy from `phase00-session02-domain-taxonomy-feature-schema`

### Environment Requirements
- Read access to reference plugin directories: `axiachat-ai/`, `mxchat-basic/`, `baachal/`
- No build tools or runtime environments required (documentation-only session)

---

## 4. Scope

### In Scope (MVP)
- Security domain context packs -- bounded excerpts from 3 source plugins covering nonce checks, capability enforcement, sanitization/escaping, moderation, privacy controls, and audit trails
- Security feature unit extraction -- FEAT-*-SEC-* entries per feature_unit_schema contract
- Security scored comparison -- 5-dimension weighted scoring for all Security capabilities across 3 sources
- Security decision log -- DEC-SEC-* entries with adopt/modify/reject decisions and rationale
- Tools domain context packs -- bounded excerpts from AxiaChat and MxChat covering callable tools, macro registry, schema validation, execution callbacks (Baachal scored as explicit N/A)
- Tools feature unit extraction -- FEAT-*-TOL-* entries per feature_unit_schema contract
- Tools scored comparison -- 5-dimension weighted scoring for Tools capabilities across 2 sources + 1 N/A
- Tools decision log -- DEC-TOL-* entries with adopt/modify/reject decisions and rationale
- Integrations domain context packs -- bounded excerpts from AxiaChat and MxChat covering MCP client, webhooks, external channels, add-on framework (Baachal scored as explicit N/A)
- Integrations feature unit extraction -- FEAT-*-INT-* entries per feature_unit_schema contract
- Integrations scored comparison -- 5-dimension weighted scoring for Integrations capabilities across 2 sources + 1 N/A
- Integrations decision log -- DEC-INT-* entries with adopt/modify/reject decisions and rationale
- Cross-domain interaction notes (Security-Tools, Security-Integrations, Security-Runtime trust boundaries)
- Carry-forward items (CFI-SEC-*, CFI-TOL-*, CFI-INT-*) for Phase 02

### Out of Scope (Deferred)
- Security architecture or hardening implementation - *Reason: Phase 09*
- Tool execution engine design - *Reason: Phase 02+ architecture, Phase 07 implementation*
- Integration adapter implementation - *Reason: Phase 07*
- Domains other than DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS - *Reason: Covered by other Phase 01 sessions*
- Security cross-cutting implementation across other domains - *Reason: Phase 09 hardening*

---

## 5. Technical Approach

### Architecture
This is a documentation and analysis session. No code is produced. The technical approach is the established scoring workflow:

1. **Context pack assembly**: Read anchor paths for each domain, extract bounded code excerpts with budget tracking (~48k tokens per 3-source domain, ~32k tokens per 2-source domain)
2. **Feature extraction**: Identify discrete capabilities per source, assign FEAT-* IDs, populate all schema fields
3. **Scoring**: Apply 5-dimension weighted rubric (Coverage 30%, Reliability 20%, Security 20%, Operability 15%, Extensibility 15%)
4. **Decision logging**: Select winners per capability with rationale, flag modifications needed, create CFI-* items

### Design Patterns
- **Anchor-first extraction**: Start from ANC-* paths defined in session stub, not unbounded browsing
- **Bounded context packs**: Budget-tracked excerpts prevent context bloat
- **Explicit N/A policy**: Baachal Tools and Integrations get `N/A (no matching implementation)` entries with rationale
- **Cross-domain notes**: Security-to-Tools and Security-to-Integrations trust boundary tracking
- **2-source comparison**: Tools and Integrations follow the validated 2-source + N/A pattern

### Technology Stack
- Markdown documentation artifacts
- Canonical vocabulary from domain taxonomy (DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS, SRC-AXI, SRC-MXC, SRC-BAA)

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `security_context_pack.md` | Bounded source excerpts for DOM-SECURITY from 3 plugins | ~220 |
| `security_feature_entries.md` | FEAT-*-SEC-* entries per feature_unit_schema | ~250 |
| `security_scored_comparison.md` | 5-dimension scoring for Security capabilities x 3 sources | ~280 |
| `security_decision_log.md` | DEC-SEC-* decisions with rationale and CFI items | ~350 |
| `tools_context_pack.md` | Bounded source excerpts for DOM-TOOLS from 2 plugins + N/A | ~160 |
| `tools_feature_entries.md` | FEAT-*-TOL-* entries per feature_unit_schema (2 sources + N/A) | ~200 |
| `tools_scored_comparison.md` | 5-dimension scoring for Tools capabilities x 2 sources + N/A | ~220 |
| `tools_decision_log.md` | DEC-TOL-* decisions with rationale and CFI items | ~280 |
| `integrations_context_pack.md` | Bounded source excerpts for DOM-INTEGRATIONS from 2 plugins + N/A | ~160 |
| `integrations_feature_entries.md` | FEAT-*-INT-* entries per feature_unit_schema (2 sources + N/A) | ~200 |
| `integrations_scored_comparison.md` | 5-dimension scoring for Integrations capabilities x 2 sources + N/A | ~220 |
| `integrations_decision_log.md` | DEC-INT-* decisions with rationale and CFI items | ~280 |
| `implementation-notes.md` | Task execution log and session notes | ~250 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Security domain has scored entries for all 3 sources across 5 dimensions
- [ ] Security winner selected with documented rationale
- [ ] Security cross-cutting concerns noted for all other domains (Provider, Runtime, RAG, Admin, Tools, Integrations)
- [ ] Tools domain has scored entries for AxiaChat and MxChat, explicit N/A for Baachal
- [ ] Tools winner selected with rationale (2-source comparison)
- [ ] Integrations domain has scored entries for AxiaChat and MxChat, explicit N/A for Baachal
- [ ] Integrations winner selected with rationale (2-source comparison)
- [ ] All feature entries comply with feature_unit_schema contract
- [ ] All scores follow the validated 5-dimension weighted rubric
- [ ] Explicit N/A entries for Baachal Tools and Integrations with rationale

### Testing Requirements
- [ ] Cross-reference validation: all FEAT IDs unique and follow naming convention
- [ ] Score arithmetic verified: weighted totals match dimension scores
- [ ] Decision log entries reference correct FEAT and ANC IDs
- [ ] Carry-forward items (CFI-*) have actionable descriptions for Phase 02
- [ ] N/A entries follow established policy from domain taxonomy

### Non-Functional Requirements
- [ ] Context budget per domain stays within target (~48k for Security, ~32k for Tools and Integrations)
- [ ] All artifacts follow canonical vocabulary from domain taxonomy

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Consistent structure with sessions 01-03 deliverables

---

## 8. Implementation Notes

### Key Considerations
- Security is a cross-cutting domain that touches all other domains -- scope DOM-SECURITY to the dedicated security infrastructure (moderation helpers, sanitization libraries, capability check patterns, nonce enforcement, privacy controls), not domain-specific security usage
- Tools domain is 2-source only: AxiaChat has rich tool/macro APIs, MxChat has action/intent patterns, Baachal has no standalone tools subsystem
- Integrations domain is 2-source only: AxiaChat has MCP client and webhook executor, MxChat has add-on framework, Baachal has no integration subsystem beyond provider connections
- Security-Tools trust boundary: tool execution must pass capability and sanitization gates before callbacks fire
- Security-Integrations trust boundary: external channel and webhook endpoints need auth, nonce, and payload validation

### Potential Challenges
- **Security scope breadth**: Security patterns are embedded throughout all source plugins, not isolated to dedicated files. Mitigation: focus on dedicated security infrastructure files (moderation.php, sanitize-helpers.php, capability checks in AJAX handlers) rather than tracing every inline check
- **Tools vs Integrations boundary**: Some capabilities (e.g., webhook execution) could be classified under either domain. Mitigation: use the domain taxonomy canonical definitions -- Tools = callable capabilities within chat context, Integrations = external system bridges
- **2-source comparison fairness**: With only 2 sources, winner selection is binary. Mitigation: focus on which patterns are stronger for the unified plugin's needs, not just "more features wins"

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned #1): Start from ANC-SEC-*, ANC-TOL-*, ANC-INT-* paths defined in session stub
- **Explicit N/A policy** (Lesson Learned #5): Baachal N/A entries for Tools and Integrations must include rationale, not silent omission
- **Canonical vocabulary** (Lesson Learned #2): Normalize all references through alias table -- use DOM-SECURITY not "security domain", DOM-TOOLS not "tools domain"

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
- Verify Baachal N/A entries have rationale per N/A policy

### Edge Cases
- AxiaChat security has multiple sub-systems (moderation, sanitize-helpers, AJAX capability checks) -- ensure grouped scoring, not fragmented
- MxChat security is inline in AJAX handler rather than separated -- extract the patterns, not just the file structure
- Baachal security is minimal but consistent (nonce + capability checks in settings page) -- score fairly against more elaborate systems
- AxiaChat tools has two distinct sub-systems (AI tools API + macro API) -- score as grouped capability
- MxChat actions/intents overlap with both Tools and Runtime domains -- classify per canonical taxonomy boundary

---

## 10. Dependencies

### External Libraries
- None (documentation-only session)

### Other Sessions
- **Depends on**: `phase01-session01` (scoring framework, capability_matrix_template), `phase01-session02` (pattern validation, runtime domain cross-refs), `phase01-session03` (RAG/Admin cross-domain notes relevant to security and tools)
- **Depended by**: `phase01-session05` (WooCommerce domain may reference security and integration patterns), `phase01-session06` (winner consolidation aggregates all domain decisions)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
