# Context Pack Template

**Version**: `1.0`
**Source Schema**: `phase00-session01-reference-inventory-baseline/anchor_metadata_schema.md`
**Source Taxonomy**: `phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md`

---

## Instructions

A context pack is a bounded collection of source-anchored excerpts assembled for a specific session. It controls what an AI assistant or reviewer reads before making implementation or scoring decisions. Every context pack must declare its budget, scope its slices by domain, and cite anchors for every included excerpt.

**When to produce**: During `/plansession` for each new session that reads source plugin code.
**When to consume**: During `/implement` as the primary input context for task execution.

---

## Pack Metadata (Required)

| Field | Type | Required | Description |
|---|---|---|---|
| `pack_id` | string | yes | Unique identifier: `CP-<phaseNN>-<sessionNN>-<seq>`. Example: `CP-P01-S01-001`. |
| `session_id` | string | yes | Target session consuming this pack. Must match a valid session ID. |
| `phase` | integer | yes | Phase number (0-based). |
| `created_date` | string | yes | ISO 8601 date: `YYYY-MM-DD`. |
| `author` | string | yes | Who assembled the pack (AI session ID or human name). |
| `context_budget_tokens` | integer | yes | Maximum token budget for this pack. Phase guidance: 45k-95k per phase. |
| `domain_scope` | array | yes | List of `domain_id` values included. Must use canonical IDs from `domain_taxonomy.md`. |
| `source_plugins` | array | yes | Source plugin IDs included: subset of `SRC-AXI`, `SRC-MXC`, `SRC-BAA`. |

---

## Bounded-Input Rules

These rules control what may and may not be included in a context pack.

1. **Budget declaration is mandatory**: Every pack must declare `context_budget_tokens`. No pack may exceed its declared budget.
2. **Domain boundary constraint**: Each slice must belong to exactly one `domain_id`. Cross-domain content must be split into separate slices or explicitly tagged with `secondary_domains`.
3. **Anchor citation required**: Every slice must cite at least one `anchor_id` from `anchor_index.md`. Slices without anchor citations are rejected.
4. **Three-source coverage or explicit N/A**: For each domain in `domain_scope`, the pack must include slices from all three source plugins (`SRC-AXI`, `SRC-MXC`, `SRC-BAA`) or mark missing sources as `N/A (no matching implementation)` with rationale.
5. **No raw repository dumps**: Slices must be curated excerpts, not full-file copies. Include only the symbol, scope, or section relevant to the session objective.
6. **Anchor metadata fidelity**: All `anchor_id`, `source_id`, `domain_id`, and `source_path` values must match canonical schemas exactly. No aliases or ad-hoc identifiers.

---

## Domain Slice Structure (Repeatable Section)

Repeat this section once per domain included in `domain_scope`.

### Domain: `<domain_id>` - `<canonical_name>`

**Slice Count**: `<N>` slices for this domain
**Sources Covered**: `<list of source_id values or N/A annotations>`

#### Slice <N>

| Field | Type | Required | Description |
|---|---|---|---|
| `slice_id` | string | yes | Unique within pack: `<pack_id>-<domain_abbrev>-<seq>`. Example: `CP-P01-S01-001-PRO-01`. |
| `anchor_id` | string | yes | Must exist in `anchor_index.md`. Format: `ANC-<domain>-<source>-<seq>`. |
| `source_id` | string | yes | Canonical source: `SRC-AXI`, `SRC-MXC`, or `SRC-BAA`. |
| `source_path` | string | yes | Repository-relative path to the anchored file. |
| `symbol_or_scope` | string | yes | Class, function, hook, or module scope being excerpted. |
| `excerpt_summary` | string | yes | 1-3 sentence summary of what this excerpt contributes to the session objective. |
| `excerpt_lines` | string | no | Optional line range hint: `L42-L98`. |
| `token_estimate` | integer | no | Estimated token count for this slice. |
| `secondary_domains` | array | no | Additional domain tags if cross-domain behavior is material. Max 2 values. |
| `notes` | string | no | Additional context for consumers. |

#### N/A Entry (When Source Has No Matching Implementation)

| Field | Type | Required | Description |
|---|---|---|---|
| `slice_id` | string | yes | Same format as above with `-NA` suffix. Example: `CP-P01-S01-001-TOL-BAA-NA`. |
| `anchor_id` | string | yes | Use the N/A anchor if one exists (e.g., `ANC-TOL-BAA-NA`), or cite the best-available anchor used to confirm absence. |
| `source_id` | string | yes | The source plugin with no matching implementation. |
| `source_path` | string | yes | Path to the file/scope inspected to confirm absence. |
| `na_status` | string | yes | Exact text: `N/A (no matching implementation)`. |
| `na_rationale` | string | yes | One sentence explaining why no equivalent exists in this source. |

---

## Pack Summary Section (Required)

Include at the end of every context pack.

| Field | Type | Required | Description |
|---|---|---|---|
| `total_slices` | integer | yes | Count of all slices (including N/A entries). |
| `total_token_estimate` | integer | yes | Sum of all slice token estimates. Must not exceed `context_budget_tokens`. |
| `domain_coverage` | table | yes | Matrix showing domain x source coverage status. |
| `gaps_and_risks` | array | no | Known gaps, asymmetries, or risks affecting downstream decisions. |

### Domain Coverage Matrix Format

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `<domain_id>` | `<slice_id or N/A>` | `<slice_id or N/A>` | `<slice_id or N/A>` |

---

## Worked Example

### Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S01-001` |
| `session_id` | `phase01-session01-provider-domain-scoring` |
| `phase` | `1` |
| `created_date` | `2026-03-15` |
| `author` | `plansession-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-PROVIDER"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

### Domain: `DOM-PROVIDER` - `provider`

**Slice Count**: 3 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

#### Slice 1

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-01` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/class-aichat-provider-registry.php` |
| `symbol_or_scope` | `AIChat_Provider_Registry` |
| `excerpt_summary` | Central provider dispatch contract handling OpenAI, Claude, and Gemini routing with model catalog and fallback behavior. |
| `token_estimate` | `14500` |

#### Slice 2

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-02` |
| `anchor_id` | `ANC-PRO-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `symbol_or_scope` | provider routing methods |
| `excerpt_summary` | Consolidated multi-provider chat call handling and model selection within the integrator runtime boundary. |
| `token_estimate` | `12800` |

#### Slice 3

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-03` |
| `anchor_id` | `ANC-PRO-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | `call_gemini_api`, `call_openai_api`, `call_claude_api`, `call_grok_api` |
| `excerpt_summary` | Compact per-provider API implementations useful for baseline contract comparison across four provider targets. |
| `token_estimate` | `8200` |

### Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `3` |
| `total_token_estimate` | `35500` |

#### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-PROVIDER` | `CP-P01-S01-001-PRO-01` | `CP-P01-S01-001-PRO-02` | `CP-P01-S01-001-PRO-03` |

#### Gaps and Risks

- Baachal provider functions are inline in main plugin file rather than a dedicated registry class; scoring may need to account for structural maturity differences.

---

## Validation Checklist

Before finalizing a context pack, verify:

- [ ] `pack_id` follows `CP-<phaseNN>-<sessionNN>-<seq>` format.
- [ ] `context_budget_tokens` is declared and total does not exceed it.
- [ ] Every slice cites a valid `anchor_id` from `anchor_index.md`.
- [ ] Every domain in `domain_scope` has three-source coverage or explicit N/A entries.
- [ ] All `domain_id` values use canonical IDs from `domain_taxonomy.md`.
- [ ] All `source_id` values match `SRC-AXI`, `SRC-MXC`, or `SRC-BAA`.
- [ ] Domain coverage matrix is complete and accurate.
- [ ] No raw file dumps; all slices are curated excerpts.
