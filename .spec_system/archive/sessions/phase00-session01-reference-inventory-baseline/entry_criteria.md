# Anchor Promotion Criteria

**Session ID**: `phase00-session01-reference-inventory-baseline`  
**Applies To**: `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates`

---

## Promotion Objective

Promote only anchors that reduce full-repository rereads while preserving architectural and behavioral traceability for Phase 00 follow-on sessions.

---

## Mandatory Eligibility Checks

An anchor is promotable only if all checks pass:

1. **Canonical Source**: Path is within `axiachat-ai/`, `mxchat-basic/`, or `baachal/`.
2. **Traceability**: Anchor has valid `anchor_id`, `source_id`, and mapped inventory ID(s).
3. **Behavioral Value**: Anchor represents executable logic, contract boundary, or migration/security control.
4. **Domain Fit**: Anchor maps to one of the approved Phase 00 domains.
5. **Read-Only Compliance**: Anchor does not require modifying source repositories.

---

## Weighted Scoring Model

Score each eligible anchor from 0 to 2 per criterion.

| Criterion | 0 | 1 | 2 |
|---|---|---|---|
| Contract Clarity | No clear API/scope boundary | Partial boundary or mixed concerns | Clear class/function boundary with stable purpose |
| Runtime Centrality | Rarely used or peripheral | Important but not central | High-traffic control point in runtime or orchestration |
| Reuse Value | Low reuse in Session 02/03 | Some downstream reuse | Directly reused in both Session 02 and Session 03 |
| Traceability Quality | Weak or ambiguous mapping | Adequate mapping | Exact path + rationale + inventory crosswalk |
| Stability | Experimental/churn-heavy | Mixed stability | Mature or established behavior surface |

**Maximum Score**: 10

---

## Promotion Decision Rules

- **Promoted**: score `>= 8` and no rejection gate hit.
- **Candidate**: score `6-7` and no rejection gate hit.
- **Deferred**: score `<= 5` or missing required evidence.
- **N/A**: domain equivalence does not exist for a source and is explicitly justified.

---

## Rejection Gates

Reject (or mark deferred) if any condition is true:

1. Anchor points to vendor-only implementation detail with no project-specific behavior.
2. Anchor duplicates another anchor without adding distinct scope or rationale.
3. Anchor is UI-only decoration with no architectural or behavioral relevance.
4. Anchor relies on inferred behavior not supported by available code/docs.
5. Anchor cannot be mapped to `source_inventory.json` and `traceability_matrix.md`.

---

## Evidence Requirements

Each promoted or candidate anchor must include:

- `anchor_id`
- `source_id`
- `path`
- concise rationale sentence
- promotion status
- at least one mapped inventory ID in `traceability_matrix.md`

---

## Session Output Contract

By end of Session 01:

- All promoted/candidate anchors must be traceable to inventory IDs.
- All domains must have either mapped anchors or explicit N/A statements.
- Session 02 and Session 03 should be able to start from this anchor set without full repository rescans.
