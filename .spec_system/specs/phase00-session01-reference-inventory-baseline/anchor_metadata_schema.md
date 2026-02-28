# Anchor Metadata Schema

**Session ID**: `phase00-session01-reference-inventory-baseline`  
**Version**: `1.0`

---

## Purpose

This schema defines the required metadata fields for each anchor listed in `anchor_index.md`.  
It ensures deterministic selection, scoring, and traceability in downstream sessions.

---

## Required Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `anchor_id` | string | yes | Stable identifier using `ANC-<domain>-<source>-<seq>` format. |
| `source_id` | string | yes | Canonical source reference (`SRC-AXI`, `SRC-MXC`, `SRC-BAA`). |
| `domain` | enum | yes | Capability domain in Phase 00 taxonomy. |
| `path` | string | yes | Repository-relative path to the anchored file. |
| `symbol_or_scope` | string | yes | Main class, function, hook, or module scope represented by the anchor. |
| `anchor_type` | enum | yes | Entry kind: `bootstrap`, `runtime`, `rag`, `admin`, `security`, `integration`, `migration`, `ui`, `storage`. |
| `rationale` | string | yes | Why this anchor is high-value for downstream extraction. |
| `evidence` | string | yes | Brief implementation evidence statement from inspected code/docs. |
| `promotion_status` | enum | yes | `candidate`, `promoted`, `deferred`, or `na`. |
| `session_targets` | array[string] | yes | Intended downstream sessions (for this phase: Session 02 and Session 03). |

---

## Optional Fields

| Field | Type | Description |
|---|---|---|
| `line_hint` | string | Optional line/scope hint for faster source navigation. |
| `dependencies` | array[string] | Related anchors, modules, or prerequisites. |
| `risks` | array[string] | Known caveats or mismatch notes tied to the anchor. |
| `confidence` | enum | `high`, `medium`, `low` confidence in anchor utility. |
| `notes` | string | Additional context when needed. |

---

## Enumerations

### Domain

- `provider`
- `runtime`
- `rag`
- `tools`
- `admin`
- `security`
- `woocommerce`
- `integrations`
- `migration`

### Promotion Status

- `candidate`
- `promoted`
- `deferred`
- `na`

### Confidence

- `high`
- `medium`
- `low`

---

## ID and Formatting Rules

- `anchor_id` must be unique across the full session output.
- `source_id` must exist in `source_inventory.json`.
- `domain` values must match the approved domain enum exactly.
- `path` must point to an existing file in one of the read-only source repositories.
- Use ASCII-only content with Unix LF line endings.

---

## Validation Rules

1. Every anchor row in `anchor_index.md` must include all required fields.
2. Every promoted or candidate anchor must appear in `traceability_matrix.md`.
3. Every domain must include at least one anchor or explicit `N/A` justification.
4. Session target mapping must include at least one of:
   - `phase00-session02-domain-taxonomy-feature-schema`
   - `phase00-session03-context-pack-decision-log-templates`
