# Session Specification

**Session ID**: `phase01-session05-woocommerce-and-cross-domain-resolution`
**Phase**: 01 - Canonical Capability Matrix and Scoring
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session completes the final domain scoring (DOM-WOOCOMMERCE) and resolves all cross-domain conflicts accumulated during sessions 01-04. It produces a conflict-free domain winner map covering all 9 domains, ready for consolidation in session 06.

WooCommerce is the last unscored domain. All three source plugins implement WooCommerce-adjacent capabilities -- product context injection, catalog operations, cart/order awareness, and recommendation patterns -- but with substantially different architectural approaches. Scoring this domain requires careful attention to how WooCommerce features interact with provider, runtime, RAG, and tools domains already scored.

The cross-domain resolution track is equally critical. Sessions 01-04 each flagged cross-domain interaction notes and carry-forward items in their decision logs. This session reviews every such note, identifies conflicts where domain boundaries overlap or contradict, and produces explicit resolutions. The output is a verified, conflict-free winner map that session 06 can consolidate without revisiting individual domain decisions.

---

## 2. Objectives

1. Assemble bounded WooCommerce context packs from all 3 source plugins with anchor citations
2. Extract WooCommerce feature units with FEAT-*-WOO-* identifiers covering product context, catalog ops, cart/order handling, and recommendations
3. Score WooCommerce capabilities across the 5 weighted dimensions (coverage, reliability, security, operability, extensibility)
4. Select WooCommerce domain winner with documented rationale and source attribution
5. Compile a complete cross-domain interaction index from sessions 01-04 decision logs
6. Identify and resolve all cross-domain conflicts where domain boundaries overlap or contradict
7. Produce a cross-domain conflict resolution register with rationale per resolution
8. Update the carry-forward register with any items deferred to Phase 02+
9. Deliver a conflict-free domain winner map covering all 9 domains

---

## 3. Prerequisites

### Required Sessions
- [x] `phase01-session01-scoring-framework-and-provider-domain` - Scoring framework, DIM weights, DOM-PROVIDER scored
- [x] `phase01-session02-runtime-and-migration-domains` - DOM-RUNTIME and DOM-MIGRATION scored
- [x] `phase01-session03-rag-and-admin-domains` - DOM-RAG and DOM-ADMIN scored
- [x] `phase01-session04-security-tools-and-integrations-domains` - DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS scored

### Required Tools/Knowledge
- Scoring framework and 5-dimension rubric from session 01
- Decision log format and FEAT/DEC/CFI/ANC ID conventions from sessions 01-04
- Cross-domain interaction notes embedded in prior decision logs

### Environment Requirements
- Access to all 3 reference plugin directories (read-only)
- Access to all prior session deliverables under `.spec_system/specs/`

---

## 4. Scope

### In Scope (MVP)
- WooCommerce domain context pack assembly with bounded excerpts from 3 sources
- WooCommerce feature unit extraction (product context, catalog operations, cart/order handling, recommendations)
- WooCommerce 5-dimension scoring and winner selection with decision log
- Cross-domain interaction notes compilation from all sessions 01-04 decision logs
- Cross-domain conflict identification and resolution across all 9 domains
- Conflict resolution register with per-conflict rationale
- Updated carry-forward register with deferred cross-domain items
- Conflict-free domain winner map (all 9 domains, no unresolved "which wins?" conflicts)

### Out of Scope (Deferred)
- WooCommerce integration implementation - *Reason: Phase 08 scope*
- Final v1 scope boundary definition - *Reason: Session 06 scope*
- Architecture contract design for WooCommerce module - *Reason: Phase 02 scope*
- Modifying any domain scores from prior sessions - *Reason: Only conflict resolution outcomes, not rescoring*

---

## 5. Technical Approach

### Architecture
This session follows the same extraction-scoring-decision pipeline established in sessions 01-04. WooCommerce domain processing uses the standard 3-source context pack, feature extraction, 5-dimension scoring, and decision log workflow.

The cross-domain resolution track introduces a new analysis layer that reads across all prior decision logs to identify interaction points and conflicts. Resolution follows a structured register format with explicit rationale, affected domains, and carry-forward linkage.

### Design Patterns
- **Anchor-first extraction**: All code excerpts cite stable ANC-* identifiers
- **Bounded context packs**: Token-budgeted excerpts prevent context bloat
- **5-dimension weighted scoring**: DIM-COVERAGE (30%), DIM-RELIABILITY (20%), DIM-SECURITY (20%), DIM-OPERABILITY (15%), DIM-EXTENSIBILITY (15%)
- **Explicit N/A policy**: Missing capabilities get explicit N/A entries with rationale
- **Cross-domain resolution register**: Structured conflict-resolution format with bidirectional domain references

### Technology Stack
- Markdown deliverables (ASCII-only, Unix LF)
- Reference plugin source code (PHP, JavaScript) for excerpt extraction

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `woocommerce_context_pack.md` | Bounded code excerpts from 3 sources for DOM-WOOCOMMERCE | ~150 |
| `woocommerce_feature_entries.md` | Feature unit extraction with FEAT-*-WOO-* IDs | ~200 |
| `woocommerce_scored_comparison.md` | 5-dimension weighted scoring for WooCommerce capabilities | ~250 |
| `woocommerce_decision_log.md` | Winner selection with DEC-WOO-* entries and rationale | ~350 |
| `cross_domain_interaction_index.md` | Compiled interaction notes from sessions 01-04 | ~200 |
| `cross_domain_resolution_register.md` | Conflict resolutions with rationale across all 9 domains | ~300 |
| `carry_forward_register.md` | Deferred items for Phase 02+ with CFI-* IDs | ~100 |
| `domain_winner_map.md` | Conflict-free winner map covering all 9 domains | ~150 |
| `implementation-notes.md` | Task execution log with timestamps and notes | ~250 |
| `validation.md` | Final validation report | ~150 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] WooCommerce domain has scored entries for all 3 sources across 5 dimensions
- [ ] WooCommerce winner selected with documented rationale and source attribution
- [ ] All cross-domain interaction notes from sessions 01-04 are compiled and indexed
- [ ] All cross-domain conflicts are resolved or explicitly deferred with rationale
- [ ] Domain winner map covers all 9 domains without unresolved "which wins?" conflicts
- [ ] Carry-forward register captures all items deferred to Phase 02+
- [ ] All FEAT/DEC/CFI/ANC IDs follow established naming conventions

### Testing Requirements
- [ ] WooCommerce scoring arithmetic verified (weighted totals match dimension scores)
- [ ] Domain winner map cross-referenced against all 9 domain decision logs
- [ ] Carry-forward register completeness validated

### Non-Functional Requirements
- [ ] Context pack within token budget (~48k target for 3-source domain)
- [ ] All deliverables consistent with sessions 01-04 format conventions

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions

---

## 8. Implementation Notes

### Key Considerations
- WooCommerce capabilities span multiple sub-concerns: product context injection into chat, catalog query operations, cart/order state awareness, and recommendation/upsell patterns
- Baachal has the most focused WooCommerce implementation (dedicated tab and product context patterns), while AxiaChat distributes WooCommerce-adjacent features across add-ons (appointments, leads)
- MxChat has explicit WooCommerce class but also embeds commerce awareness in the integrator

### Potential Challenges
- **WooCommerce boundary definition**: Product context injection overlaps with DOM-RAG (content indexing) and DOM-RUNTIME (context assembly). Must clearly delineate what belongs to DOM-WOOCOMMERCE vs. adjacent domains.
- **Cross-domain conflict volume**: 8 domains x potential pairwise conflicts could surface many items. Prioritize structural conflicts over minor overlap notes.
- **Carry-forward completeness**: Must verify that every CFI-* from sessions 01-04 is either resolved or carried forward -- none silently dropped.

### Relevant Considerations
- [L1] **Anchor-first extraction**: Continue the proven anchor-first pattern to prevent ID drift during WooCommerce feature extraction
- [L3] **Template-driven bounded input**: Apply token budgets to WooCommerce context pack as done in prior sessions
- [L5] **Explicit N/A policy**: Ensure any source lacking a WooCommerce sub-capability gets explicit N/A entries with rationale
- [L6] **Bidirectional template references**: Cross-domain resolution register must maintain bidirectional links to source decision logs

---

## 9. Testing Strategy

### Unit Tests
- Verify WooCommerce weighted score arithmetic (dimension scores x weights = correct totals)
- Verify FEAT/DEC/ANC ID uniqueness and naming convention compliance

### Integration Tests
- Cross-reference domain winner map entries against source decision logs from all 6 sessions (01-05)
- Verify carry-forward register entries trace back to source decisions

### Manual Testing
- Review WooCommerce context pack excerpts for relevance and bounded scope
- Verify cross-domain resolution rationale is coherent and non-contradictory

### Edge Cases
- WooCommerce features that are purely DOM-RAG or DOM-RUNTIME in nature (must classify correctly)
- Cross-domain conflicts where both sides have valid but incompatible winner recommendations
- Carry-forward items from sessions 01-04 that may have been implicitly resolved by later session decisions

---

## 10. Dependencies

### External Libraries
- None (documentation/scoring session)

### Other Sessions
- **Depends on**: `phase01-session01` through `phase01-session04` (all 8 domains scored)
- **Depended by**: `phase01-session06-winner-consolidation-and-phase-handoff` (requires conflict-free winner map)

---

## 11. Reference Anchors

### WooCommerce Domain
- `ANC-WOO-AXI-001`: `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php` - Business workflow features adjacent to WooCommerce
- `ANC-WOO-AXI-002`: `axiachat-ai/includes/add-ons/leads/class-leads-manager.php` - Lead capture and conversion tracking
- `ANC-WOO-MXC-001`: `mxchat-basic/includes/class-mxchat-woocommerce.php` - Dedicated WooCommerce integration class
- `ANC-WOO-MXC-002`: `mxchat-basic/includes/class-mxchat-integrator.php` - Commerce-aware integrator patterns
- `ANC-WOO-BAA-001`: `baachal/admin/tabs/woocommerce.php` - WooCommerce settings and product context configuration
- `ANC-WOO-BAA-002`: `baachal/baachal.php` - Product context injection into chat runtime

### Cross-Domain (synthesized from prior sessions)
- Session 01 decision logs: DOM-PROVIDER winner and cross-domain notes
- Session 02 decision logs: DOM-RUNTIME and DOM-MIGRATION winners and cross-domain notes
- Session 03 decision logs: DOM-RAG and DOM-ADMIN winners and cross-domain notes
- Session 04 decision logs: DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS winners and cross-domain notes

---

## Next Steps

Run `/implement` to begin AI-led implementation.
