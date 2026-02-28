# Decision Log Template

**Version**: `1.0`
**Source Schema**: `phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Source Taxonomy**: `phase00-session02-domain-taxonomy-feature-schema/domain_taxonomy.md`

---

## Instructions

A decision log records adopt, modify, or reject outcomes for capabilities compared across source plugins. Each entry traces back to source anchors and scoring evidence, and forward to carry-forward items when decisions are deferred. The log ensures every implementation choice has documented rationale, impact assessment, and anchor-backed evidence.

**When to produce**: During `/implement` for sessions that score and select capabilities.
**When to consume**: During subsequent `/plansession` to inform scope, and during `/implement` for downstream sessions that depend on prior decisions.

---

## Log Metadata (Required)

| Field | Type | Required | Description |
|---|---|---|---|
| `log_id` | string | yes | Unique identifier: `DL-<phaseNN>-<sessionNN>`. Example: `DL-P01-S01`. |
| `session_id` | string | yes | Session that produced this log. Must match a valid session ID. |
| `phase` | integer | yes | Phase number (0-based). |
| `domain_scope` | array | yes | List of `domain_id` values covered by decisions in this log. |
| `created_date` | string | yes | ISO 8601 date: `YYYY-MM-DD`. |
| `last_updated` | string | yes | ISO 8601 date of most recent entry addition or revision. |
| `context_pack_id` | string | yes | `pack_id` of the context pack that informed these decisions. Must reference a valid context pack. |

---

## Decision Entry Structure (Repeatable)

Repeat this structure for each capability decision.

### Entry: `<decision_id>`

#### Identification Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `decision_id` | string | yes | Unique identifier: `DEC-<domain_abbrev>-<seq>`. Example: `DEC-PRO-001`. |
| `domain_id` | enum | yes | Canonical domain from `domain_taxonomy.md`. |
| `capability_name` | string | yes | Brief name of the capability being decided. 3-80 chars. |
| `capability_summary` | string | yes | 1-3 sentences describing the behavior under comparison. |

#### Source Comparison Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `source_anchors` | object | yes | Anchor citations per source plugin. See format below. |
| `scoring_summary` | object | yes | Per-dimension scores for each source. See format below. |

**Source Anchors Format** (one entry per source plugin):

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `<anchor_id or N/A>` | `<path>` | `<feature_id or N/A>` | `implemented` or `na_no_matching_implementation` |
| `SRC-MXC` | `<anchor_id or N/A>` | `<path>` | `<feature_id or N/A>` | `implemented` or `na_no_matching_implementation` |
| `SRC-BAA` | `<anchor_id or N/A>` | `<path>` | `<feature_id or N/A>` | `implemented` or `na_no_matching_implementation` |

**Scoring Summary Format** (per source, aligned with `scoring_rubric.md`):

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<computed>` |
| `SRC-MXC` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<computed>` |
| `SRC-BAA` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<computed>` |

**Weighted total formula**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

#### Outcome Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `outcome` | enum | yes | `adopt_as_is`, `adopt_with_modifications`, or `reject_defer`. |
| `winner` | string | conditional | Required when outcome is `adopt_as_is` or `adopt_with_modifications`. Value: `SRC-AXI`, `SRC-MXC`, or `SRC-BAA`. |
| `recommendation_band` | enum | yes | From scoring rubric: `adopt` (>=3.8), `investigate` (2.8-3.7), or `reject` (<2.8). |
| `rationale` | string | yes | 2-5 sentences explaining why this outcome was chosen. Must reference specific scoring dimensions and evidence. |
| `modification_notes` | string | conditional | Required when outcome is `adopt_with_modifications`. Describes what changes are needed and why. |
| `reject_reason` | string | conditional | Required when outcome is `reject_defer`. Explains why no source is suitable and what would change the decision. |
| `impact_assessment` | object | yes | See format below. |
| `carry_forward_ids` | array | no | List of `item_id` values from carry-forward template for deferred items or unresolved risks. |

**Impact Assessment Format**:

| Field | Type | Required | Description |
|---|---|---|---|
| `affected_domains` | array | yes | List of `domain_id` values impacted by this decision beyond the primary domain. |
| `risk_level` | enum | yes | `low`, `medium`, `high`, or `critical`. From `feature_unit_schema.md` risk enum. |
| `maturity_level` | enum | yes | Winner's maturity: `production_stable`, `production_limited`, `beta_or_addon`, `experimental`, or `na_no_matching_implementation`. |
| `dependency_profile` | enum | yes | Winner's dependency: `wordpress_core_only`, `plugin_internal_modules`, `external_ai_provider_required`, `optional_external_service`, `ecommerce_stack_required`, `paid_addon_or_license`. |
| `migration_notes` | string | no | Notes on migration or compatibility implications. |

---

## Tie-Break Documentation (When Applicable)

When scores are within 0.2 of each other, document the tie-break resolution:

| Field | Type | Required | Description |
|---|---|---|---|
| `tie_break_applied` | boolean | yes | Whether a tie-break rule was invoked. |
| `tie_break_rule` | string | conditional | Which rule resolved the tie. Options: `security_preference`, `dependency_burden`, `migration_compatibility`. |
| `tie_break_rationale` | string | conditional | Explanation of how the rule was applied. |

---

## Worked Examples

### Example 1: Adopt-As-Is Winner

#### Entry: `DEC-PRO-001`

**Identification**:

| Field | Value |
|---|---|
| `decision_id` | `DEC-PRO-001` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider registry and model routing` |
| `capability_summary` | Central dispatch contract for routing chat requests to OpenAI, Claude, Gemini, or other providers with model catalog and fallback behavior. |

**Source Anchors**:

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-PRO-AXI-001` | `axiachat-ai/includes/class-aichat-provider-registry.php` | `FEAT-AXI-PRO-001` | `implemented` |
| `SRC-MXC` | `ANC-PRO-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-PRO-001` | `implemented` |
| `SRC-BAA` | `ANC-PRO-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-PRO-001` | `implemented` |

**Scoring Summary**:

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | `5` | `4` | `4` | `4` | `5` | `4.45` |
| `SRC-MXC` | `4` | `4` | `3` | `3` | `3` | `3.50` |
| `SRC-BAA` | `3` | `3` | `3` | `3` | `2` | `2.85` |

**Outcome**:

| Field | Value |
|---|---|
| `outcome` | `adopt_as_is` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat provides the most complete provider registry with dedicated class-based abstraction, model catalog management, and extensible provider registration. Its registry pattern supports adding new providers without modifying dispatch logic. Security controls and operational diagnostics are well-integrated. |

**Impact Assessment**:

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |

---

### Example 2: Adopt-With-Modifications Winner

#### Entry: `DEC-RAG-001`

**Identification**:

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-001` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Knowledge ingestion and retrieval pipeline` |
| `capability_summary` | Ingestion, chunking, indexing, and retrieval of knowledge content for grounding chatbot responses with relevant context. |

**Source Anchors**:

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-001` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/admin/class-knowledge-manager.php` | `FEAT-MXC-RAG-001` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-001` | `implemented` |

**Scoring Summary**:

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | `4` | `3` | `4` | `3` | `3` | `3.50` |
| `SRC-MXC` | `4` | `4` | `3` | `4` | `4` | `3.80` |
| `SRC-BAA` | `2` | `3` | `3` | `2` | `2` | `2.40` |

**Outcome**:

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's knowledge manager provides the best queue-based ingestion reliability and admin operability. However, its security posture (DIM-SECURITY=3) needs hardening with input validation patterns from AxiaChat's contexto functions before adoption. |
| `modification_notes` | Adopt MxChat queue-based ingestion architecture. Integrate AxiaChat-style input sanitization on ingestion payloads and retrieval query parameters. Add chunk-size validation guards from AxiaChat evidence. |

**Impact Assessment**:

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-ADMIN", "DOM-WOOCOMMERCE"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `carry_forward_ids` | `["CFI-RAG-001"]` |

---

### Example 3: Reject/Defer With Explicit N/A Source

#### Entry: `DEC-TOL-001`

**Identification**:

| Field | Value |
|---|---|
| `decision_id` | `DEC-TOL-001` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Dedicated tools and macro registry` |
| `capability_summary` | Structured callable tools/macros that extend chatbot behavior beyond direct answer generation, with schema validation and callback execution. |

**Source Anchors**:

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-TOL-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | `FEAT-AXI-TOL-001` | `implemented` |
| `SRC-MXC` | `ANC-TOL-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-TOL-001` | `implemented` |
| `SRC-BAA` | `ANC-TOL-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-TOL-001` | `na_no_matching_implementation` |

**Scoring Summary**:

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | `4` | `3` | `3` | `3` | `4` | `3.45` |
| `SRC-MXC` | `3` | `3` | `2` | `3` | `3` | `2.85` |
| `SRC-BAA` | `0` | `0` | `0` | `0` | `0` | `0.00` |

**Outcome**:

| Field | Value |
|---|---|
| `outcome` | `reject_defer` |
| `recommendation_band` | `investigate` |
| `reject_reason` | Neither AxiaChat nor MxChat tools implementations reach the adopt threshold (>=3.8). AxiaChat's tool registry has the strongest coverage but DIM-SECURITY=3 needs investigation. MxChat's intent/action framework has DIM-SECURITY=2 which falls below the security floor. Baachal has no matching implementation. Decision deferred to Phase 02 after security hardening investigation. |
| `carry_forward_ids` | `["CFI-TOL-001", "CFI-TOL-002"]` |

**Impact Assessment**:

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-INTEGRATIONS", "DOM-RUNTIME"]` |
| `risk_level` | `high` |
| `maturity_level` | `beta_or_addon` |
| `dependency_profile` | `paid_addon_or_license` |

---

## Validation Checklist

Before finalizing a decision log, verify:

- [ ] `log_id` follows `DL-<phaseNN>-<sessionNN>` format.
- [ ] Every `decision_id` follows `DEC-<domain_abbrev>-<seq>` format and is unique within the log.
- [ ] Every entry cites source anchors for all three plugins (or explicit N/A with `na_no_matching_implementation`).
- [ ] Scoring uses all five dimensions from `scoring_rubric.md` with correct weights.
- [ ] Weighted totals are correctly computed.
- [ ] `recommendation_band` aligns with threshold guidance: adopt >=3.8, investigate 2.8-3.7, reject <2.8.
- [ ] `outcome` is one of: `adopt_as_is`, `adopt_with_modifications`, `reject_defer`.
- [ ] `modification_notes` provided when outcome is `adopt_with_modifications`.
- [ ] `reject_reason` provided when outcome is `reject_defer`.
- [ ] `carry_forward_ids` reference valid items in the carry-forward template.
- [ ] All `domain_id` values use canonical IDs from `domain_taxonomy.md`.
- [ ] All `feature_id` values follow `FEAT-(AXI|MXC|BAA)-[A-Z0-9]+-[0-9]{3}` format.
- [ ] Impact assessment uses enums from `feature_unit_schema.md`.
