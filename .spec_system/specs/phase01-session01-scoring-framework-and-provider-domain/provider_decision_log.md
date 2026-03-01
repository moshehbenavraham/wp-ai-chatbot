# Provider Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S01` |
| `session_id` | `phase01-session01-scoring-framework-and-provider-domain` |
| `phase` | `1` |
| `domain_scope` | `["DOM-PROVIDER"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S01-001` |

---

## Entry: `DEC-PRO-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-PRO-001` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider registry and adapter dispatch` |
| `capability_summary` | Central dispatch contract for registering, instantiating, and routing requests to AI provider adapters with lifecycle management, caching, and interface validation. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-PRO-AXI-001` | `axiachat-ai/includes/class-aichat-provider-registry.php` | `FEAT-AXI-PRO-001` | `implemented` |
| `SRC-MXC` | `ANC-PRO-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-PRO-001` | `implemented` |
| `SRC-BAA` | `ANC-PRO-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-PRO-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 |
| `SRC-MXC` | 4 | 3 | 3 | 3 | 2 | 3.10 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_as_is` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat provides the only formal provider registry with Singleton+Factory pattern, interface compliance validation (`AIChat_Provider_Interface`), dynamic registration via hook (`aichat_register_providers`), and config-hash instance caching. This architecture allows new providers to be added without modifying dispatch logic. SRC-MXC and SRC-BAA lack any abstraction layer, making their provider routing rigid and untestable. The extensibility gap (SRC-AXI: 5 vs SRC-MXC: 2, SRC-BAA: 2) is the decisive differentiator. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | SRC-MXC and SRC-BAA provider routing will need refactoring to implement the registry pattern. MxChat's 5-provider support (DeepSeek, Grok) will need new adapter classes registered via the hook. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-PRO-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-PRO-002` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider-specific API adapters` |
| `capability_summary` | Individual provider adapter implementations handling API call construction, message format translation, tool calling, streaming, cost calculation, and provider-specific error handling for OpenAI, Claude, and Gemini. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-PRO-AXI-001` | `axiachat-ai/includes/providers/class-openai-provider.php`, `class-claude-provider.php`, `class-gemini-provider.php` | `FEAT-AXI-PRO-002`, `FEAT-AXI-PRO-003`, `FEAT-AXI-PRO-004` | `implemented` |
| `SRC-MXC` | `ANC-PRO-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-PRO-002` | `implemented` |
| `SRC-BAA` | `ANC-PRO-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-PRO-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4.3 | 4 | 4 | 5 | 4.52 |
| `SRC-MXC` | 4 | 4 | 3 | 3 | 3 | 3.50 |
| `SRC-BAA` | 3 | 3 | 3 | 2 | 2 | 2.70 |

**Note**: SRC-AXI reliability is averaged across 3 adapters (OpenAI: 4, Claude: 5, Gemini: 4).

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's per-provider adapter classes provide the strongest foundation: interface compliance, tool calling via shared trait, dual-API routing (OpenAI), fallback chains (Claude), native tool support (Gemini). The class-per-provider pattern enables independent testing and provider-specific optimization. SRC-MXC's streaming implementation is superior and should be integrated. SRC-BAA is below threshold. |
| `modification_notes` | Adopt SRC-AXI adapter architecture. Integrate SRC-MXC's SSE streaming pattern into each adapter (currently SRC-AXI handles streaming at transport layer, not adapter level). Add DeepSeek and Grok adapter classes using the registry pattern. Generalize Claude's retry/fallback pattern to OpenAI and Gemini adapters. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-SECURITY", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | MxChat's streaming logic needs extraction from integrator class into per-adapter streaming methods. DeepSeek and Grok adapters are net-new implementations. |
| `carry_forward_ids` | `["CFI-PRO-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-PRO-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-PRO-003` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Centralized model registry and resolution` |
| `capability_summary` | Single source of truth for model metadata (IDs, pricing, context windows, fallback chains, aliases, deprecated mappings) used by adapters, admin UI, and cost tracking. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-PRO-AXI-001` | `axiachat-ai/includes/model-registry.php` | `FEAT-AXI-PRO-005` | `implemented` |
| `SRC-MXC` | `ANC-PRO-MXC-001` | `mxchat-basic/includes/class-mxchat-admin.php` | `FEAT-MXC-PRO-003` | `implemented` |
| `SRC-BAA` | `ANC-PRO-BAA-001` | `baachal/admin/tabs/general.php` | `FEAT-BAA-PRO-003` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 5 | 4 | 5 | 5 | 4.85 |
| `SRC-MXC` | 3 | 3 | 4 | 4 | 3 | 3.35 |
| `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_as_is` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's model registry is the clear winner with 35+ models, comprehensive per-model metadata (context window, pricing, aliases, deprecated mappings, fallback chains), model resolution pipeline (exact -> alias -> prefix -> default), and JS payload builder for admin UI. This is the highest-scored capability in the session (4.85). SRC-MXC has no centralized catalog; SRC-BAA has hardcoded HTML options. The registry's `aichat_model_registry` filter hook ensures extensibility. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-RUNTIME", "DOM-MIGRATION"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | MxChat and Baachal model lists will be replaced by registry lookups. MxChat's per-provider API key management should integrate with registry for model-to-provider resolution. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-PRO-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-PRO-004` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider error handling and resilience` |
| `capability_summary` | Retry logic, fallback chains, error classification, and user-facing error messaging for provider API failures. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-PRO-AXI-001` | `axiachat-ai/includes/providers/class-claude-provider.php` | `FEAT-AXI-PRO-003` | `implemented` |
| `SRC-MXC` | `ANC-PRO-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-PRO-001` | `implemented` |
| `SRC-BAA` | `ANC-PRO-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-PRO-002` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 5 | 4 | 4 | 4 | 4.50 |
| `SRC-MXC` | 3 | 3 | 3 | 3 | 2 | 2.85 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's Claude adapter demonstrates the strongest resilience pattern: exponential backoff retry (3 attempts), retryable error classification (429/503/529), and 4-tier fallback chain. However, this pattern is only implemented for Claude, not OpenAI or Gemini. The modification is to generalize this resilience pattern across all provider adapters. SRC-BAA's Gemini-specific error messaging (safety filters, quota, overload) should also be incorporated as user-facing error enhancements. |
| `modification_notes` | Generalize Claude's retry + fallback pattern into a shared trait or base class method applicable to all provider adapters. Incorporate SRC-BAA's user-friendly error suggestions (quota, retry, config check) into error response formatting. |
| `carry_forward_ids` | `["CFI-PRO-002"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Retry/fallback logic needs to be extracted from Claude adapter into reusable pattern. Fallback chains need registry entries for all providers (currently only Anthropic and Gemini have them). |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-PRO-001

| Field | Value |
|---|---|
| `item_id` | `CFI-PRO-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session01-scoring-framework-and-provider-domain` |
| `domain_id` | `DOM-PROVIDER` |
| `description` | Integrate MxChat SSE streaming pattern into AxiaChat per-adapter architecture and add DeepSeek/Grok adapter classes. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-PRO-002` |
| `related_anchor_ids` | `["ANC-PRO-AXI-001", "ANC-PRO-MXC-001"]` |
| `resolution_criteria` | Streaming works per-adapter with provider-specific chunk parsing; DeepSeek and Grok adapters registered via `aichat_register_providers` hook. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-PRO-002

| Field | Value |
|---|---|
| `item_id` | `CFI-PRO-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session01-scoring-framework-and-provider-domain` |
| `domain_id` | `DOM-PROVIDER` |
| `description` | Generalize Claude's retry/fallback resilience pattern to OpenAI and Gemini adapters and incorporate Baachal's user-friendly error messaging. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-PRO-004` |
| `related_anchor_ids` | `["ANC-PRO-AXI-001", "ANC-PRO-BAA-001"]` |
| `resolution_criteria` | All provider adapters have configurable retry with backoff, fallback chains from registry, and user-friendly error messages for common failures (quota, overload, config). |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-PROVIDER x DOM-RUNTIME

- Provider adapters are invoked from the runtime request pipeline. The adapter interface contract (`AIChat_Provider_Interface`) defines the boundary: runtime passes messages and params, adapter returns normalized response.
- Streaming behavior (SRC-MXC) bridges provider and runtime: SSE headers, chunked output, and provider-specific parsing all operate at the provider-runtime boundary.
- Sessions 02 (runtime) must honor the provider adapter interface when scoring runtime pipeline quality.

### DOM-PROVIDER x DOM-SECURITY

- Provider adapters handle API key validation and proxy fallback (SRC-AXI). API keys are retrieved from WP options; no adapter stores keys internally.
- Claude adapter persists tool state to DB using `$wpdb->prepare()` -- this is a security-relevant data path.
- Error responses must not expose API keys, internal paths, or stack traces (all sources comply).
- Session 04 (security) should evaluate provider auth patterns as part of trust boundary scoring.

### DOM-PROVIDER x DOM-ADMIN

- Model registry drives admin UI model selection (SRC-AXI JS payload, SRC-MXC options, SRC-BAA HTML selects).
- Provider API key management is an admin concern that crosses into provider domain for validation and connectivity testing.
- Session 03 (admin) should score admin-side provider configuration as DOM-ADMIN primary with DOM-PROVIDER secondary.

### DOM-PROVIDER x DOM-MIGRATION

- Model registry alias and deprecated mappings (SRC-AXI) directly support migration: deprecated model IDs resolve to current canonical IDs.
- Fallback chains provide runtime migration path when models are retired.
- SRC-BAA hardcoded model lists will need migration to registry-based lookups.
- Session 02 (migration) should reference provider registry decisions when scoring model migration patterns.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-PRO-001` | Provider registry and adapter dispatch | `SRC-AXI` | `adopt_as_is` | `adopt` | 4.45 |
| `DEC-PRO-002` | Provider-specific API adapters | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.52 |
| `DEC-PRO-003` | Centralized model registry and resolution | `SRC-AXI` | `adopt_as_is` | `adopt` | 4.85 |
| `DEC-PRO-004` | Provider error handling and resilience | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.50 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 4 |
| Adopt-As-Is | 2 |
| Adopt-With-Modifications | 2 |
| Reject/Defer | 0 |
| Domain Winner | `SRC-AXI` |
| Dominant Maturity | `production_stable` |
| Key Risks | MxChat streaming integration; generalized retry/fallback pattern; DeepSeek/Grok adapter creation |
| Cross-Domain Dependencies | `DOM-RUNTIME`, `DOM-SECURITY`, `DOM-ADMIN`, `DOM-MIGRATION` |
| Carry-Forward Items | `CFI-PRO-001`, `CFI-PRO-002` |
