# Template Usage Guide

**Version**: `1.0`
**Templates Covered**: `context_pack_template.md`, `decision_log_template.md`, `carry_forward_template.md`

---

## Purpose

This guide documents how the three session templates relate to each other, when each is produced and consumed, and how field names map back to canonical schemas from Sessions 01 and 02.

---

## Cross-Template Relationships

### Template Dependency Chain

```
context_pack_template.md
        |
        | (informs decisions via anchor-backed evidence)
        v
decision_log_template.md
        |
        | (deferred decisions and unresolved items flow to)
        v
carry_forward_template.md
```

### Reference Direction

| From Template | To Template | Link Field | Relationship |
|---|---|---|---|
| Decision Log | Context Pack | `context_pack_id` -> `pack_id` | Each decision log cites the context pack that provided its evidence. |
| Decision Log | Carry-Forward | `carry_forward_ids` -> `item_id` | Deferred decisions create carry-forward items with traceable `decision_id`. |
| Carry-Forward | Decision Log | `decision_id` -> `decision_id` | Carry-forward items originating from deferred decisions reference the source decision entry. |
| Context Pack | Anchor Index | `anchor_id` -> `anchor_id` | Every context slice cites an anchor from Session 01 `anchor_index.md`. |
| Decision Log | Anchor Index | `source_anchors[].anchor_id` -> `anchor_id` | Each decision entry cites source anchors per plugin. |
| Carry-Forward | Anchor Index | `related_anchor_ids` -> `anchor_id` | Carry-forward items may reference anchors for context. |

---

## Canonical Workflow

### When Each Template Is Produced and Consumed

```
/plansession (new session)
    |
    +--> PRODUCE: context_pack_template.md
    |    (Assemble bounded context from source anchors)
    |
    v
/implement (execute session tasks)
    |
    +--> CONSUME: context_pack (read as primary input)
    |
    +--> PRODUCE: decision_log_template.md
    |    (Record adopt/modify/reject for each capability)
    |
    +--> PRODUCE: carry_forward_template.md
    |    (Record deferred items and unresolved risks)
    |
    v
/validate (verify session completeness)
    |
    +--> CONSUME: all three templates
    |    (Cross-reference for completeness and consistency)
    |
    v
/carryforward (phase transition)
    |
    +--> CONSUME: carry_forward registers from all sessions in phase
    |    (Merge unresolved items into next phase input)
    |
    +--> PRODUCE: updated carry_forward register
         (Items resolved during phase marked resolved; remaining flow forward)
```

### Session Lifecycle Summary

| Phase | Step | Template | Action |
|---|---|---|---|
| Planning | `/plansession` | Context Pack | Produce: assemble domain-scoped slices with anchor citations |
| Execution | `/implement` | Context Pack | Consume: read as bounded input for task execution |
| Execution | `/implement` | Decision Log | Produce: record capability decisions with scoring evidence |
| Execution | `/implement` | Carry-Forward | Produce: record deferred items and assumptions |
| Verification | `/validate` | All three | Consume: cross-reference for completeness |
| Transition | `/carryforward` | Carry-Forward | Consume + Update: merge and resolve items across phases |

---

## Field Name Concordance Table

### Shared ID Fields Across Templates

| Field Name | Used In | Format | Source Schema | Purpose |
|---|---|---|---|---|
| `anchor_id` | Context Pack, Decision Log, Carry-Forward | `ANC-<domain>-<source>-<seq>` | `anchor_metadata_schema.md` | Links to specific source code location in reference plugins |
| `source_id` | Context Pack, Decision Log | `SRC-AXI`, `SRC-MXC`, `SRC-BAA` | `source_inventory.json` | Identifies which reference plugin |
| `domain_id` | All three | `DOM-PROVIDER` ... `DOM-MIGRATION` | `domain_taxonomy.md` | Canonical domain classification |
| `feature_id` | Decision Log | `FEAT-(AXI\|MXC\|BAA)-[A-Z0-9]+-[0-9]{3}` | `feature_unit_schema.md` | Links to extracted feature unit |
| `pack_id` | Context Pack, Decision Log | `CP-<phaseNN>-<sessionNN>-<seq>` | Context Pack Template | Identifies a specific context pack |
| `decision_id` | Decision Log, Carry-Forward | `DEC-<domain_abbrev>-<seq>` | Decision Log Template | Identifies a specific decision entry |
| `item_id` | Carry-Forward, Decision Log | `CFI-<domain_abbrev>-<seq>` | Carry-Forward Template | Identifies a specific carry-forward item |
| `log_id` | Decision Log | `DL-<phaseNN>-<sessionNN>` | Decision Log Template | Identifies a decision log instance |
| `register_id` | Carry-Forward | `CFR-<phaseNN>-<sessionNN>` | Carry-Forward Template | Identifies a carry-forward register instance |
| `slice_id` | Context Pack | `<pack_id>-<domain_abbrev>-<seq>` | Context Pack Template | Identifies a specific context slice |
| `session_id` | All three | phase/session format | Spec System | Links to the owning session |

### Enum Fields Shared Across Templates

| Enum Name | Values | Source Schema | Used In |
|---|---|---|---|
| Domain ID | `DOM-PROVIDER`, `DOM-RUNTIME`, `DOM-RAG`, `DOM-TOOLS`, `DOM-ADMIN`, `DOM-SECURITY`, `DOM-WOOCOMMERCE`, `DOM-INTEGRATIONS`, `DOM-MIGRATION` | `domain_taxonomy.md` | All three templates |
| Source Plugin | `SRC-AXI`, `SRC-MXC`, `SRC-BAA` | `source_inventory.json` | Context Pack, Decision Log |
| Implementation Status | `implemented`, `na_no_matching_implementation` | `feature_unit_schema.md` | Decision Log |
| Maturity Level | `production_stable`, `production_limited`, `beta_or_addon`, `experimental`, `na_no_matching_implementation` | `feature_unit_schema.md` | Decision Log |
| Risk Level | `low`, `medium`, `high`, `critical` | `feature_unit_schema.md` | Decision Log, Carry-Forward (priority) |
| Dependency Profile | `wordpress_core_only`, `plugin_internal_modules`, `external_ai_provider_required`, `optional_external_service`, `ecommerce_stack_required`, `paid_addon_or_license` | `feature_unit_schema.md` | Decision Log |
| Scoring Dimensions | `DIM-COVERAGE`, `DIM-RELIABILITY`, `DIM-SECURITY`, `DIM-OPERABILITY`, `DIM-EXTENSIBILITY` | `scoring_rubric.md` | Decision Log |
| Recommendation Band | `adopt`, `investigate`, `reject` | `scoring_rubric.md` | Decision Log |
| Decision Outcome | `adopt_as_is`, `adopt_with_modifications`, `reject_defer` | Decision Log Template | Decision Log |
| Item Type | `deferred_decision`, `unresolved_conflict`, `assumption`, `dependency` | Carry-Forward Template | Carry-Forward |
| Item Priority | `blocking`, `high`, `medium`, `low` | Carry-Forward Template | Carry-Forward |
| Item Status | `open`, `mitigated`, `resolved` | Carry-Forward Template | Carry-Forward |
| N/A Text | `N/A (no matching implementation)` | `domain_taxonomy.md` | Context Pack, Decision Log |

---

## Cross-Template Traceability Rules

1. **Anchor traceability**: Every `anchor_id` cited in any template must exist in Session 01 `anchor_index.md`.
2. **Decision-to-context traceability**: Every decision log must cite its source `context_pack_id`.
3. **Carry-forward-to-decision traceability**: Every `deferred_decision` carry-forward item must include the `decision_id` that produced it.
4. **Decision-to-carry-forward traceability**: Decision entries with `carry_forward_ids` must reference valid `item_id` values in the carry-forward register.
5. **Domain consistency**: The same `domain_id` value must be used consistently across all three templates when referring to the same domain.
6. **N/A consistency**: When a source has no matching implementation, use `N/A (no matching implementation)` in human-readable fields and `na_no_matching_implementation` in enum fields.

---

## Schema Source Reference

| Schema Artifact | Session | Path | Key Fields Used |
|---|---|---|---|
| Anchor Metadata Schema | 01 | `specs/phase00-session01-reference-inventory-baseline/anchor_metadata_schema.md` | `anchor_id`, `source_id`, `domain`, `path`, `symbol_or_scope`, `anchor_type`, `promotion_status` |
| Source Inventory | 01 | `specs/phase00-session01-reference-inventory-baseline/source_inventory.json` | `source_id`, `plugin`, `root_path`, `entry_points` |
| Anchor Index | 01 | `specs/phase00-session01-reference-inventory-baseline/anchor_index.md` | `anchor_id`, `source_id`, `path`, `symbol_or_scope`, `promotion_status` |
| Domain Taxonomy | 02 | `specs/phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md` | `domain_id`, canonical names, boundary rules, N/A policy |
| Feature Unit Schema | 02 | `specs/phase00-session02-domain-taxonomy-feature-schema/feature_unit_schema.md` | `feature_id`, `maturity_level`, `risk_level`, `dependency_profile`, `implementation_status` |
| Scoring Rubric | 02 | `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md` | Dimension IDs, weights, threshold bands, tie-break rules |
| Taxonomy-Schema Crosswalk | 02 | `specs/phase00-session02-domain-taxonomy-feature-schema/taxonomy_schema_crosswalk.md` | Domain-to-field mappings, alias normalization, collision handling |
