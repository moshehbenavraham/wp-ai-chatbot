# Feature Unit Schema Contract

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Ready for extraction and scoring sessions

---

## Purpose

Define the canonical schema used to capture one feature unit from source plugins in a consistent, comparable structure.

The schema is intentionally compact so Session 03 templates and Phase 01 scoring can reuse it without adding local variants.

---

## Required Fields

| Field | Type | Required | Allowed Values | Validation Guidance | Example |
|---|---|---|---|---|---|
| `feature_id` | string | yes | `FEAT-(AXI|MXC|BAA)-[A-Z0-9]+-[0-9]{3}` | Must be unique per session and stable across revisions. | `FEAT-AXI-PRO-001` |
| `source_plugin` | enum | yes | `SRC-AXI`, `SRC-MXC`, `SRC-BAA` | Must match source root path and anchor prefix. | `SRC-MXC` |
| `source_anchor_id` | string | yes | `ANC-*` | Must exist in Session 01 `anchor_index.md`. | `ANC-RAG-MXC-001` |
| `source_path` | string | yes | repo-relative path | Must resolve under source plugin root and not `vendor/`. | `mxchat-basic/admin/class-knowledge-manager.php` |
| `domain_id` | enum | yes | `DOM-PROVIDER`, `DOM-RUNTIME`, `DOM-RAG`, `DOM-TOOLS`, `DOM-ADMIN`, `DOM-SECURITY`, `DOM-WOOCOMMERCE`, `DOM-INTEGRATIONS`, `DOM-MIGRATION` | Must match canonical taxonomy in `domain_taxonomy.md`. | `DOM-RAG` |
| `capability_name` | string | yes | non-empty text | 3-80 chars, sentence fragment, no trailing punctuation required. | `Queue-based knowledge ingestion` |
| `capability_summary` | string | yes | 1-3 sentences | State behavior, scope, and important guardrails. | `Processes knowledge imports through queue jobs with retry-aware stages.` |
| `implementation_status` | enum | yes | `implemented`, `na_no_matching_implementation` | `na_no_matching_implementation` requires `na_reason`. | `implemented` |
| `maturity_level` | enum | yes | `production_stable`, `production_limited`, `beta_or_addon`, `experimental`, `na_no_matching_implementation` | Must align with evidence in source code/feature maps. | `production_limited` |
| `dependency_profile` | enum | yes | `wordpress_core_only`, `plugin_internal_modules`, `external_ai_provider_required`, `optional_external_service`, `ecommerce_stack_required`, `paid_addon_or_license` | Choose dominant operational dependency, not every possible dependency. | `external_ai_provider_required` |
| `risk_level` | enum | yes | `low`, `medium`, `high`, `critical` | Reflect operational/security risk if capability fails or is misused. | `medium` |

---

## Optional Fields

| Field | Type | Required | Allowed Values | Validation Guidance | Example |
|---|---|---|---|---|---|
| `secondary_domains` | array<enum> | no | same domain enum list as `domain_id` | Max 2 values; cannot duplicate primary domain. | `["DOM-SECURITY"]` |
| `evidence_refs` | array<string> | no | markdown path refs | Use feature map sections and anchor notes when available. | `["docs/mxchat-basic-feature-map.md#43-storage-and-retrieval"]` |
| `confidence_score` | number | no | `0.0` to `1.0` | Use one decimal place (`0.0`, `0.1`, ...). | `0.8` |
| `na_reason` | string | conditional | free text | Required when `implementation_status` is `na_no_matching_implementation`. | `No dedicated tools registry comparable to AxiaChat or MxChat intent callbacks.` |
| `normalization_notes` | string | no | free text | Record alias mappings or disambiguation decisions. | `Mapped "knowledge database" label to canonical rag domain.` |

---

## Enum Sets and Normalization Guidance

### Maturity enum (`maturity_level`)

| Value | Definition | Typical Signal |
|---|---|---|
| `production_stable` | Widely used path with mature controls and low churn risk. | Core lifecycle/runtime functionality with established safeguards. |
| `production_limited` | Production path exists but has known limits or conditional behavior. | Works in common paths with edge constraints or optional modules. |
| `beta_or_addon` | Feature exists primarily behind add-on, premium, or newer extension path. | Labeled as add-on, premium, or recently introduced expansion path. |
| `experimental` | Early-stage or unstable behavior with uncertain operational guarantees. | Frequent changes, known inconsistencies, or provisional integration. |
| `na_no_matching_implementation` | Canonical domain has no equivalent implementation in source. | Explicit N/A parity case documented with anchor evidence. |

### Dependency enum (`dependency_profile`)

| Value | Definition |
|---|---|
| `wordpress_core_only` | No special dependency beyond standard WordPress stack. |
| `plugin_internal_modules` | Depends on multiple plugin-internal modules/subsystems. |
| `external_ai_provider_required` | Requires external LLM provider API availability. |
| `optional_external_service` | External service can enhance behavior but is not mandatory. |
| `ecommerce_stack_required` | Requires WooCommerce objects/configuration to be meaningful. |
| `paid_addon_or_license` | Capability availability or completeness depends on paid add-on/license. |

### Risk enum (`risk_level`)

| Value | Definition |
|---|---|
| `low` | Failure has limited user or operational impact and straightforward recovery. |
| `medium` | Failure can degrade user outcomes or support quality; mitigation exists. |
| `high` | Failure can cause significant business/runtime impact or repeated support burden. |
| `critical` | Failure can compromise trust boundaries, data safety, or core uptime expectations. |

---

## Normalization Rules

### IDs and references

1. `feature_id` must be deterministic and source-prefixed (`AXI`, `MXC`, `BAA`).
2. `source_anchor_id` must use exact Session 01 anchor IDs; no ad hoc anchor names.
3. `domain_id` must map exactly to the taxonomy set; aliases are not allowed in final entries.

### Text formatting

1. Use sentence case for `capability_name`.
2. Keep `capability_summary` factual and source-backed; avoid scoring language.
3. Keep all enum values lowercase snake_case.

### Legacy label mapping

| Legacy Label | Canonical Target |
|---|---|
| `knowledge_base`, `retrieval`, `context` | `DOM-RAG` |
| `actions`, `function calling`, `macro` | `DOM-TOOLS` |
| `live agent`, `external channel`, `mcp` | `DOM-INTEGRATIONS` |
| `upgrade`, `compatibility`, `deprecation migration` | `DOM-MIGRATION` |

---

## Validation Checklist

- [ ] All required fields present.
- [ ] `feature_id`, `source_anchor_id`, and `domain_id` follow canonical patterns.
- [ ] Maturity, dependency, and risk values use approved enum sets.
- [ ] N/A entries use `implementation_status: na_no_matching_implementation` and include `na_reason`.
- [ ] Source path and anchor align to the same source plugin.

---

## Serialization Examples

### Implemented capability example

```json
{
  "feature_id": "FEAT-MXC-RAG-001",
  "source_plugin": "SRC-MXC",
  "source_anchor_id": "ANC-RAG-MXC-001",
  "source_path": "mxchat-basic/admin/class-knowledge-manager.php",
  "domain_id": "DOM-RAG",
  "capability_name": "Queue-based knowledge ingestion",
  "capability_summary": "Knowledge imports are processed through queue-aware workflows and admin controls for ingestion reliability.",
  "implementation_status": "implemented",
  "maturity_level": "production_limited",
  "dependency_profile": "plugin_internal_modules",
  "risk_level": "medium",
  "secondary_domains": ["DOM-ADMIN"]
}
```

### Explicit N/A example

```json
{
  "feature_id": "FEAT-BAA-TOL-001",
  "source_plugin": "SRC-BAA",
  "source_anchor_id": "ANC-TOL-BAA-NA",
  "source_path": "baachal/baachal.php",
  "domain_id": "DOM-TOOLS",
  "capability_name": "Dedicated tools registry parity",
  "capability_summary": "Baachal exposes provider chat flows but no dedicated tools or macro registry comparable to AxiaChat/MxChat.",
  "implementation_status": "na_no_matching_implementation",
  "maturity_level": "na_no_matching_implementation",
  "dependency_profile": "plugin_internal_modules",
  "risk_level": "low",
  "na_reason": "No standalone tools subsystem with callable schema registration."
}
```
