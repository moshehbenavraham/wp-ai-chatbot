# Session Specification

**Session ID**: `phase00-session03-context-pack-decision-log-templates`
**Phase**: 00 - Program Setup and Extraction Schema
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session creates the three reusable template artifacts that standardize how context, decisions, and unresolved items flow between sessions and phases. Sessions 01 and 02 established the reference inventory, domain taxonomy, feature-unit schema, and scoring rubric. Session 03 packages that vocabulary into operational templates so every future session can assemble bounded context packs, record adopt/modify/reject decisions with traceable rationale, and track carry-forward items without inventing ad-hoc formats.

These templates are the last structural prerequisite before Session 04 assembles the Phase 00 handoff bundle. Once in place, Phase 01 capability scoring sessions can consume context packs directly and produce decision logs that feed the Phase 02 architecture contracts. The templates enforce the PRD's mandatory reference rule by requiring source anchor citations in every context slice and decision entry.

---

## 2. Objectives

1. Deliver a context pack template that enforces bounded-input rules, domain-scoped slicing, and mandatory source anchor citations per the PRD reference policy.
2. Deliver a decision log template that captures adopt-as-is, adopt-with-modifications, and reject/defer outcomes with structured rationale and impact fields.
3. Deliver a carry-forward template that tracks unresolved conflicts, assumptions, mitigation plans, and phase-assignment targets.
4. Provide a usage guide that documents cross-template relationships and workflow integration points for `/plansession` and `/implement` consumers.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - Provides anchor metadata schema, source inventory, and anchor index
- [x] `phase00-session02-domain-taxonomy-feature-schema` - Provides domain taxonomy, feature-unit schema, scoring rubric, and taxonomy-schema crosswalk

### Required Tools/Knowledge
- Familiarity with 9 canonical domain IDs from `domain_taxonomy.md`
- Understanding of anchor metadata fields from `anchor_metadata_schema.md`
- Understanding of feature-unit schema fields from `feature_unit_schema.md`

### Environment Requirements
- Access to `.spec_system/specs/phase00-session01-reference-inventory-baseline/` artifacts
- Access to `.spec_system/specs/phase00-session02-domain-taxonomy-feature-schema/` artifacts

---

## 4. Scope

### In Scope (MVP)
- Context pack template with bounded-input rules and domain-scoped section structure - enforces PRD mandatory reference rule
- Decision log template with adopt/modify/reject entry format and structured rationale - aligned with scoring rubric dimensions
- Carry-forward template with unresolved-item register, mitigation tracking, and phase-assignment - supports cross-phase traceability
- Template usage guide documenting relationships and workflow integration - enables `/plansession` and `/implement` consumption
- Worked examples embedded in each template using real domain vocabulary and anchor patterns from sessions 01-02

### Out of Scope (Deferred)
- Populating templates with complete phase data - *Reason: Session 04 handoff bundle scope*
- Approving final cross-phase decisions - *Reason: Phase 01 capability scoring scope*
- Automated template validation tooling - *Reason: Not needed for Phase 00 exit criteria*
- Template versioning or migration between template formats - *Reason: Premature; templates are new*

---

## 5. Technical Approach

### Architecture
Templates are standalone Markdown documents stored in `.spec_system/templates/` for project-wide reuse. Each template defines required fields, optional fields, format rules, and at least one worked example. Field names and domain vocabulary are drawn directly from the Session 01 anchor metadata schema and Session 02 domain taxonomy and feature-unit schema.

### Design Patterns
- **Structured Markdown with field contracts**: Required vs optional fields clearly marked, with type hints and allowed values referencing canonical enums
- **Example-driven specification**: Each template includes at least one realistic worked example to eliminate ambiguity
- **Cross-reference by ID**: Templates reference each other by field name (e.g., decision log entries reference anchor_ids from context packs, carry-forward entries reference decision_ids)

### Technology Stack
- Markdown (ASCII-only, Unix LF)
- No runtime dependencies; templates are consumed by AI assistants and human reviewers

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `.spec_system/templates/context_pack_template.md` | Reusable context pack template with bounded-input rules | ~130 |
| `.spec_system/templates/decision_log_template.md` | Decision log template with adopt/modify/reject format | ~110 |
| `.spec_system/templates/carry_forward_template.md` | Carry-forward template with unresolved-item tracking | ~90 |
| `.spec_system/templates/template_usage_guide.md` | Cross-template relationships and workflow guide | ~70 |
| `specs/.../validation.md` | Template validation evidence | ~60 |
| `specs/.../implementation-notes.md` | Session summary and lessons | ~40 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Context pack template enforces bounded-input rules with explicit domain-scoped sections
- [ ] Context pack template requires source anchor citations per PRD mandatory reference rule
- [ ] Decision log template supports adopt-as-is, adopt-with-modifications, and reject/defer outcomes
- [ ] Decision log template includes rationale and impact fields aligned with scoring rubric dimensions
- [ ] Carry-forward template tracks unresolved items with priority, owner, mitigation, and target-phase fields
- [ ] All templates use canonical domain IDs and vocabulary from Session 02 domain taxonomy
- [ ] All templates reference anchor metadata fields from Session 01 schema
- [ ] Worked examples are realistic and internally consistent across all three templates
- [ ] Template usage guide documents workflow integration for `/plansession` and `/implement`

### Testing Requirements
- [ ] Cross-reference validation confirms field consistency across all three templates
- [ ] Context pack template validated against PRD Section "Mandatory reference rule"
- [ ] Decision log template validated against scoring rubric dimensions from Session 02

### Non-Functional Requirements
- [ ] Templates are reusable by `/plansession` without rework (PRD Session 03 success criterion)
- [ ] Required fields enforce anchor-backed decisions and explicit unresolved-item tracking (PRD Session 03 success criterion)

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Field names consistent with Session 01/02 canonical vocabulary

---

## 8. Implementation Notes

### Key Considerations
- Templates must be self-contained: a reader should not need to open Session 01/02 artifacts to understand the template structure, but field names must match canonical schemas exactly
- Bounded-input rules in the context pack template are critical for controlling context budget in later phases (45k-95k targets per phase)
- Decision log entries must support three-way comparison (all three source plugins) since most Phase 01 decisions will compare implementations across axiachat-ai, mxchat-basic, and baachal

### Potential Challenges
- **Field proliferation**: Too many required fields reduces adoption -- mitigation: keep required fields minimal, use optional fields for enrichment
- **Vocabulary drift**: Templates using different terms than Session 01/02 schemas -- mitigation: explicit cross-reference table in usage guide
- **Example quality**: Weak examples lead to inconsistent usage -- mitigation: use real anchor IDs and domain IDs from existing artifacts

### Relevant Considerations
<!-- CONSIDERATIONS.md has no active concerns yet; none apply -->

---

## 9. Testing Strategy

### Unit Tests
- N/A (documentation artifacts, not code)

### Integration Tests
- Validate that context pack template fields map to anchor_metadata_schema fields
- Validate that decision log template fields map to scoring_rubric dimensions
- Validate that carry-forward template fields reference decision log entry IDs correctly

### Manual Testing
- Walk through a hypothetical Phase 01 session using all three templates end-to-end
- Verify templates produce consistent output when filled with sample data

### Edge Cases
- Context pack for a domain where one source plugin has N/A (no matching implementation)
- Decision log entry for reject/defer with no winner selected
- Carry-forward item with cross-domain dependencies spanning multiple sessions

---

## 10. Dependencies

### External Libraries
- None

### Other Sessions
- **Depends on**: `phase00-session01-reference-inventory-baseline`, `phase00-session02-domain-taxonomy-feature-schema`
- **Depended by**: `phase00-session04-phase-readiness-handoff-bundle`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
