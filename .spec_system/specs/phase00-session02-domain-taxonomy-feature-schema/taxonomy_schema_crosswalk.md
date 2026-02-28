# Taxonomy to Schema Crosswalk

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Ready for extraction/scoring reuse

---

## Purpose

Map canonical taxonomy domains to schema fields and enum values so extraction entries stay consistent across sources.

---

## Domain to Schema Mapping

| Canonical Domain | Domain ID | Primary Schema Field(s) | Related Enum Fields | Required Anchor Types | Notes |
|---|---|---|---|---|---|
| provider | `DOM-PROVIDER` | `domain_id`, `capability_name`, `source_anchor_id` | `dependency_profile`, `risk_level`, `maturity_level` | provider/integration anchor with model/provider behavior | Usually `external_ai_provider_required`; may include migration secondary tags for model deprecation handling. |
| runtime | `DOM-RUNTIME` | `domain_id`, `capability_summary`, `source_path` | `maturity_level`, `risk_level` | runtime/request pipeline anchor | Secondary `DOM-SECURITY` common when nonce/session checks are embedded. |
| rag | `DOM-RAG` | `domain_id`, `capability_summary`, `evidence_refs` | `dependency_profile`, `maturity_level`, `risk_level` | rag/indexing/retrieval anchor | Map labels such as "knowledge base", "context", and "indexing" to this domain. |
| tools | `DOM-TOOLS` | `domain_id`, `implementation_status`, `na_reason` (if needed) | `maturity_level`, `dependency_profile` | tool/action/macro anchor or explicit N/A anchor | Baachal parity gap is represented with explicit N/A entry. |
| admin | `DOM-ADMIN` | `domain_id`, `capability_name`, `source_path` | `maturity_level`, `risk_level` | admin/settings anchor | Prefer this domain when behavior is configuration or diagnostics focused. |
| security | `DOM-SECURITY` | `domain_id`, `capability_summary`, `source_anchor_id` | `risk_level`, `maturity_level` | security/trust-boundary anchor | If security controls are incidental to another domain, keep as secondary domain. |
| woocommerce | `DOM-WOOCOMMERCE` | `domain_id`, `capability_name`, `source_path` | `dependency_profile`, `risk_level` | WooCommerce-specific anchor | Usually `ecommerce_stack_required` or `optional_external_service` dependency profile. |
| integrations | `DOM-INTEGRATIONS` | `domain_id`, `implementation_status`, `source_anchor_id` | `dependency_profile`, `maturity_level` | MCP/webhook/handoff channel anchor | Baachal non-provider integration parity uses explicit N/A anchor. |
| migration | `DOM-MIGRATION` | `domain_id`, `capability_summary`, `source_path` | `maturity_level`, `risk_level` | migration/upgrade anchor | Included for compatibility continuity and Phase 01 upgrade-risk scoring. |

---

## Alias and Label Normalization

| Non-Canonical Label | Canonical Domain | Rule |
|---|---|---|
| provider routing, model registry, model catalog | `DOM-PROVIDER` | Use provider domain when model/provider selection or API adapter behavior is primary. |
| chat pipeline, widget runtime, message loop, stream chat | `DOM-RUNTIME` | Use runtime domain for live request/response lifecycle behavior. |
| knowledge base, retrieval, context indexing, chunking | `DOM-RAG` | Consolidate all retrieval/indexing terms under RAG domain. |
| actions, intents callback, macro, function calling | `DOM-TOOLS` | Use tools domain for callable behavior frameworks. |
| settings page, admin tabs, diagnostics panel | `DOM-ADMIN` | Use admin domain for operator-facing configuration surfaces. |
| nonce checks, capability guard, moderation, sanitization | `DOM-SECURITY` | Use security domain when trust-boundary enforcement is primary behavior. |
| product retrieval, cart helpers, product cards | `DOM-WOOCOMMERCE` | Use WooCommerce domain for commerce entity behavior. |
| MCP, Slack handoff, Telegram webhook, custom webhooks | `DOM-INTEGRATIONS` | Use integrations domain for external system/channel connectors. |
| schema migration, deprecated model remap, upgrade repair | `DOM-MIGRATION` | Use migration domain for version transition logic and compatibility routines. |

---

## Collision Handling

### Collision Type A: Multiple domains appear valid

Apply deterministic precedence from taxonomy policy:

`provider -> runtime -> rag -> tools -> admin -> security -> woocommerce -> integrations -> migration`

Then capture non-primary domains in `secondary_domains`.

### Collision Type B: Same capability appears with different labels

1. Normalize source terms through alias table above.
2. Use canonical `domain_id` in output entries only.
3. Record original label mapping in `normalization_notes` when it affects interpretation.

### Collision Type C: Missing capability in one source

1. Create explicit feature entry with `implementation_status: na_no_matching_implementation`.
2. Use exact report phrase `N/A (no matching implementation)` in human-readable artifacts.
3. Include `na_reason` and best available anchor/scope showing parity gap.

---

## N/A Handling Contract

- Human-readable outputs use exact phrase: `N/A (no matching implementation)`.
- Machine-readable schema uses: `implementation_status: na_no_matching_implementation`.
- Every N/A entry records source plugin, domain id, anchor/scope, and concise rationale.
- N/A entries remain in scoring matrices and receive low/zero coverage rather than omission.

---

## Validation Checklist

- [ ] Every taxonomy domain maps to at least one schema field.
- [ ] Alias rules are deterministic and one-way.
- [ ] Collision handling rules reference the primary-domain policy.
- [ ] N/A handling is explicit and reusable across sessions.
- [ ] Domain mappings align with `domain_taxonomy.md` and `feature_unit_schema.md`.
