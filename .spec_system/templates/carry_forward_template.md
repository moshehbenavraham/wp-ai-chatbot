# Carry-Forward Template

**Version**: `1.0`
**Source Schema**: `phase00-session02-domain-taxonomy-feature-schema/feature_unit_schema.md`
**Source Taxonomy**: `phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md`

---

## Instructions

A carry-forward register tracks unresolved items that cannot be fully resolved in the current session or phase. Items include deferred decisions, unresolved conflicts, assumptions requiring future validation, and cross-domain dependencies. Every item must have an owner, priority, mitigation plan, and target phase for resolution.

**When to produce**: During `/implement` when a decision is deferred, an assumption is recorded, or a conflict cannot be resolved within session scope.
**When to consume**: During `/plansession` for the target phase/session to ensure unresolved items are addressed. During `/carryforward` for cross-phase handoff.

---

## Register Metadata (Required)

| Field | Type | Required | Description |
|---|---|---|---|
| `register_id` | string | yes | Unique identifier: `CFR-<phaseNN>-<sessionNN>`. Example: `CFR-P01-S01`. |
| `session_id` | string | yes | Session that produced this register. Must match a valid session ID. |
| `phase` | integer | yes | Phase number (0-based). |
| `created_date` | string | yes | ISO 8601 date: `YYYY-MM-DD`. |
| `last_updated` | string | yes | ISO 8601 date of most recent item addition or status change. |
| `decision_log_id` | string | no | `log_id` of the related decision log, if carry-forward items originate from deferred decisions. |

---

## Carry-Forward Item Structure (Repeatable)

Repeat this structure for each unresolved item.

### Item: `<item_id>`

| Field | Type | Required | Description |
|---|---|---|---|
| `item_id` | string | yes | Unique identifier: `CFI-<domain_abbrev>-<seq>`. Example: `CFI-RAG-001`. |
| `item_type` | enum | yes | `deferred_decision`, `unresolved_conflict`, `assumption`, or `dependency`. |
| `source_session` | string | yes | Session ID where this item was first identified. |
| `domain_id` | enum | yes | Primary domain from `domain_taxonomy.md`. |
| `secondary_domains` | array | no | Additional affected domains. Max 2 values. |
| `description` | string | yes | 2-5 sentences describing the unresolved item, its context, and why it cannot be resolved now. |
| `priority` | enum | yes | `blocking`, `high`, `medium`, or `low`. See priority definitions below. |
| `status` | enum | yes | `open`, `mitigated`, or `resolved`. |
| `owner` | string | yes | Responsible party or session ID for resolution. Use `unassigned` if not yet determined. |
| `decision_id` | string | no | `decision_id` from decision log when this item originates from a deferred decision. |
| `related_anchor_ids` | array | no | Anchor IDs from `anchor_index.md` relevant to this item. |
| `mitigation` | string | conditional | Required when status is `mitigated`. Describes interim measures in place. |
| `resolution_criteria` | string | yes | Specific, testable conditions that must be true for this item to be marked `resolved`. |
| `target_phase` | integer | yes | Phase number where resolution is expected. |
| `target_session` | string | no | Specific session ID for resolution, if known. |
| `created_date` | string | yes | ISO 8601 date when item was first recorded. |
| `resolved_date` | string | no | ISO 8601 date when item was resolved. Required when status is `resolved`. |
| `resolution_notes` | string | no | How the item was ultimately resolved. Required when status is `resolved`. |

---

## Priority Definitions

| Priority | Definition | Action Required |
|---|---|---|
| `blocking` | Prevents downstream sessions from starting or completing. Must be resolved before target phase begins. | Immediate escalation. Cannot proceed past current phase gate without resolution. |
| `high` | Significantly impacts quality or scope of downstream work. Should be resolved early in target phase. | Schedule in first session of target phase. |
| `medium` | Affects design choices but workarounds exist. Can be resolved mid-phase. | Schedule before phase midpoint. |
| `low` | Minor refinement or optimization opportunity. Can be deferred without material impact. | Schedule before phase exit gate. |

---

## Register Summary Section (Required)

Include at the end of every carry-forward register.

| Field | Type | Required | Description |
|---|---|---|---|
| `total_items` | integer | yes | Count of all items in the register. |
| `open_count` | integer | yes | Items with status `open`. |
| `mitigated_count` | integer | yes | Items with status `mitigated`. |
| `resolved_count` | integer | yes | Items with status `resolved`. |
| `blocking_count` | integer | yes | Items with priority `blocking`. |

### Items by Target Phase

| Target Phase | Blocking | High | Medium | Low | Total |
|---|---|---|---|---|---|
| `<phase_number>` | `<count>` | `<count>` | `<count>` | `<count>` | `<count>` |

---

## Worked Examples

### Example 1: Cross-Domain Dependency Conflict

#### Item: `CFI-WOO-001`

| Field | Value |
|---|---|
| `item_id` | `CFI-WOO-001` |
| `item_type` | `dependency` |
| `source_session` | `phase01-session01-provider-domain-scoring` |
| `domain_id` | `DOM-WOOCOMMERCE` |
| `secondary_domains` | `["DOM-RAG"]` |
| `description` | WooCommerce product context enrichment in AxiaChat (`ANC-WOO-AXI-001`) is tightly coupled to the RAG retrieval pipeline (`ANC-RAG-AXI-001`). If the RAG decision adopts MxChat's knowledge manager instead, the WooCommerce enrichment logic must be re-integrated into a different retrieval substrate. This cross-domain dependency cannot be resolved until both RAG and WooCommerce domain decisions are finalized. |
| `priority` | `high` |
| `status` | `open` |
| `owner` | `phase01-session03-woocommerce-domain-scoring` |
| `related_anchor_ids` | `["ANC-WOO-AXI-001", "ANC-RAG-AXI-001", "ANC-RAG-MXC-001"]` |
| `resolution_criteria` | RAG domain winner is selected and WooCommerce enrichment integration path is validated against the chosen retrieval architecture. |
| `target_phase` | `1` |
| `target_session` | `phase01-session03-woocommerce-domain-scoring` |
| `created_date` | `2026-03-15` |

---

### Example 2: Deferred Decision With Mitigation Plan

#### Item: `CFI-TOL-001`

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-tools-domain-scoring` |
| `domain_id` | `DOM-TOOLS` |
| `description` | Tools domain decision deferred because neither AxiaChat nor MxChat tools implementations meet the adopt threshold (>=3.8). AxiaChat's tool registry (`ANC-TOL-AXI-001`) scored 3.45 with security concerns; MxChat's intent framework (`ANC-TOL-MXC-001`) scored 2.85 with DIM-SECURITY below floor. A security hardening investigation is needed before final selection. |
| `priority` | `high` |
| `status` | `mitigated` |
| `owner` | `phase02-session01-architecture-contracts` |
| `decision_id` | `DEC-TOL-001` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001", "ANC-TOL-MXC-001", "ANC-TOL-BAA-NA"]` |
| `mitigation` | Preliminary architecture contracts will use AxiaChat tool registry patterns as the reference baseline while security investigation proceeds. No tools integration will be committed until the hardened design is approved. |
| `resolution_criteria` | Security review of AxiaChat tool registry completes with DIM-SECURITY >= 3 after proposed hardening, or an alternative architecture is designed that meets the security floor requirement. |
| `target_phase` | `2` |
| `created_date` | `2026-03-20` |

---

### Example 3: Assumption Requiring Future Validation

#### Item: `CFI-TOL-002`

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-002` |
| `item_type` | `assumption` |
| `source_session` | `phase01-session02-tools-domain-scoring` |
| `domain_id` | `DOM-TOOLS` |
| `secondary_domains` | `["DOM-INTEGRATIONS"]` |
| `description` | It is assumed that the unified plugin's tool registry can support MCP-based external tool invocation without requiring a separate integration subsystem. This assumption is based on AxiaChat's MCP client manager (`ANC-INT-AXI-001`) being architecturally compatible with the tool callback pattern. If this assumption is invalid, a dedicated integration bridge between tools and MCP will need to be designed. |
| `priority` | `medium` |
| `status` | `open` |
| `owner` | `phase02-session01-architecture-contracts` |
| `decision_id` | `DEC-TOL-001` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001", "ANC-INT-AXI-001"]` |
| `resolution_criteria` | Architecture spike confirms MCP tool invocation can route through the unified tool registry callback mechanism, or a separate integration bridge design is produced and approved. |
| `target_phase` | `2` |
| `created_date` | `2026-03-20` |

---

## Validation Checklist

Before finalizing a carry-forward register, verify:

- [ ] `register_id` follows `CFR-<phaseNN>-<sessionNN>` format.
- [ ] Every `item_id` follows `CFI-<domain_abbrev>-<seq>` format and is unique within the register.
- [ ] Every item has `priority`, `status`, `owner`, `resolution_criteria`, and `target_phase`.
- [ ] Items with `status: mitigated` include `mitigation` text.
- [ ] Items with `status: resolved` include `resolved_date` and `resolution_notes`.
- [ ] Items with `item_type: deferred_decision` include `decision_id` referencing the decision log.
- [ ] All `domain_id` values use canonical IDs from `domain_taxonomy.md`.
- [ ] All `anchor_id` references exist in `anchor_index.md`.
- [ ] Register summary counts match actual item statuses.
- [ ] Items by target phase table is complete and accurate.
