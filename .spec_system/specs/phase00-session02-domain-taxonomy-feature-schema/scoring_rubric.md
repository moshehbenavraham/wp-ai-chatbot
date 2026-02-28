# Scoring Rubric Draft

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Ready for Phase 01 scoring sessions
**Intended Consumer**: Phase 01 adopt/reject capability matrix sessions

---

## Purpose

Provide a lightweight, repeatable rubric for comparing candidate capabilities before winner selection.

This rubric is intentionally directional (not mathematically rigid) so teams can compare domains consistently while still recording qualitative rationale.

---

## Scoring Scale

| Score | Meaning |
|---|---|
| 0 | Not present or unusable for the target domain. |
| 1 | Bare-minimum support with major reliability or operability limits. |
| 2 | Partial support with clear constraints or unresolved risks. |
| 3 | Solid support for common use cases with manageable tradeoffs. |
| 4 | Strong support with low operational friction and good safeguards. |
| 5 | Best-in-class support among reference plugins for this capability type. |

---

## Dimensions

| Dimension ID | Dimension Name | Weight Intent | What to Assess | Evidence Source |
|---|---|---|---|---|
| `DIM-COVERAGE` | Capability coverage | 30% | Breadth and clarity of domain behavior delivered (including edge handling). | anchor + feature map + source path |
| `DIM-RELIABILITY` | Runtime reliability | 20% | Failure behavior, resiliency patterns, lifecycle stability, and maintenance burden. | runtime flow + migration/lifecycle evidence |
| `DIM-SECURITY` | Security posture | 20% | Trust-boundary controls, validation/sanitization quality, and privacy risk profile. | nonce/capability/sanitization and security controls |
| `DIM-OPERABILITY` | Admin and maintenance operability | 15% | Configuration UX, diagnostics, and operational clarity for maintainers. | admin surface + settings + logs/tools |
| `DIM-EXTENSIBILITY` | Integration and extension fit | 15% | Ease of extension (hooks/tools/channels) and compatibility with future architecture. | integrations/tools/extensibility anchors |

Weights are intent guidance for Phase 01 and can be adjusted only with explicit decision-log rationale.

---

## Threshold Guidance

| Decision Band | Aggregate Score Guidance | Intent |
|---|---|---|
| `adopt` | `>= 3.8` and no dimension below `3`, with `DIM-SECURITY >= 3` | strong baseline candidate for winner selection |
| `investigate` | `2.8 - 3.7`, or any single dimension below `3` with mitigations possible | needs targeted follow-up or normalization before final selection |
| `reject` | `< 2.8`, or `DIM-SECURITY <= 2`, or critical parity gaps without mitigation path | not suitable for baseline adoption in current phase |

---

## Tie-Break Rules

1. Prefer the option with higher `DIM-SECURITY` when aggregate scores are within `0.2`.
2. If still tied, prefer lower `dependency_profile` burden from `feature_unit_schema.md`.
3. If still tied, prefer clearer migration compatibility and fewer N/A parity gaps for adjacent domains.

---

## Scoring Procedure

1. Confirm feature entries use canonical taxonomy and schema fields.
2. Score each dimension from 0-5 with a one-line evidence note.
3. Compute weighted summary:
   - `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`
4. Assign recommendation band (`adopt`, `investigate`, `reject`).
5. Record assumptions and unresolved risks in session decision log.

---

## Evidence Logging Template

```markdown
### Capability Score: <feature_id>

- Coverage: <0-5> - <evidence>
- Reliability: <0-5> - <evidence>
- Security: <0-5> - <evidence>
- Operability: <0-5> - <evidence>
- Extensibility: <0-5> - <evidence>

- Weighted Summary: <numeric result and short interpretation>
- Recommendation: <adopt | investigate | reject>
- Residual Risks: <known gaps or N/A parity constraints>
```
