# Tools Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S04-TOL` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-TOOLS"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S04-002` |

---

## Entry: `DEC-TOL-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-TOL-001` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Tool/action registration` |
| `capability_summary` | Registration system for defining callable tools or triggerable actions with validation, schema enforcement, and registry management. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-TOL-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | `FEAT-AXI-TOL-001` | `implemented` |
| `SRC-MXC` | `ANC-TOL-MXC-001` | `mxchat-basic/js/mxchat_actions.js`, `mxchat-basic/includes/admin-actions-page.php` | `FEAT-MXC-TOL-001` | `implemented` |
| `SRC-BAA` | `ANC-TOL-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-TOL-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 5 | 4.65 |
| `SRC-MXC` | 3 | 4 | 4 | 4 | 3 | 3.55 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's tool registration API is the clear winner with formal JSON Schema validation, provider-specific normalization, global registry with lifecycle hooks, and macro grouping. The 1.10-point gap over MxChat reflects the fundamental architecture difference: SRC-AXI implements a tool-calling protocol (AI model invokes tools), while SRC-MXC implements intent matching (user messages trigger actions). Both patterns have value in a unified plugin. Modification: adopt SRC-AXI's tool registration API as the primary tool-calling mechanism, and port SRC-MXC's intent/action concept as a complementary "user-intent actions" subsystem that bridges to the tool registry for actions that can also be AI-invoked. |
| `modification_notes` | Use SRC-AXI registration API for all AI-model-invoked tools. Port SRC-MXC intent matching as a separate "user actions" layer that can optionally bridge to tool registry. Ensure both systems share a unified admin surface. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-SECURITY", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Tool registration API ports cleanly from SRC-AXI. Intent/action bridging to tool registry is net-new design work for Phase 02. Macro system ports with tools API. |
| `carry_forward_ids` | `["CFI-TOL-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-TOL-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-TOL-002` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Execution engine` |
| `capability_summary` | Runtime mechanism for executing tools via provider API calls (function calling, tool use) or intent matching against user messages, including multi-provider support and execution lifecycle. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-TOL-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | `FEAT-AXI-TOL-002` | `implemented` |
| `SRC-MXC` | `ANC-TOL-MXC-001` | `mxchat-basic/js/mxchat_actions.js` | `FEAT-MXC-TOL-001` | `implemented` |
| `SRC-BAA` | `ANC-TOL-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-TOL-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 |
| `SRC-MXC` | 3 | 3 | 3 | 3 | 3 | 3.00 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's provider-native tool injection is the only implementation that supports AI-model-invoked tool calling across multiple providers (OpenAI, Claude, Gemini). The filter-based injection pattern enables clean per-provider customization. MxChat's intent matching operates at a different layer (user message preprocessing, not provider tool calling). Modification: adopt SRC-AXI's provider-specific tool injection as the primary execution engine. Add tool execution lifecycle hooks (pre-execute, post-execute) that are not yet present in SRC-AXI. Integrate timeout and retry logic from the tool registration schema. |
| `modification_notes` | Adopt SRC-AXI provider injection filters. Add pre/post-execute lifecycle hooks. Implement configurable timeout per tool. Add execution result caching for idempotent tools. Keep provider-specific normalization. |
| `carry_forward_ids` | `["CFI-TOL-002"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-RUNTIME", "DOM-SECURITY"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Provider injection filters are WordPress-native and port cleanly. Tool execution lifecycle hooks are net-new. Provider-specific schema normalization must track API changes. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-TOL-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-TOL-003` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Schema validation and tool lifecycle` |
| `capability_summary` | Tool definition validation, macro registry for tool grouping, source tracking for external tool providers, and per-bot capability settings for access control. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-TOL-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php`, `axiachat-ai/includes/add-ons/ai-tools/api.php` | `FEAT-AXI-TOL-003`, `FEAT-AXI-TOL-004` | `implemented` |
| `SRC-MXC` | `ANC-TOL-MXC-001` | `mxchat-basic/includes/admin-actions-page.php` | `FEAT-MXC-TOL-002` | `implemented` |
| `SRC-BAA` | `ANC-TOL-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-TOL-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 4 | 4 | 4 | 4.30 |
| `SRC-MXC` | 3 | 3 | 4 | 4 | 3 | 3.35 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's macro registry and capability settings provide the strongest tool lifecycle management. Macro source tracking (local/MCP/webhook) with cascade delete is uniquely valuable for managing tools from external sources. Per-bot capability settings enable fine-grained access control. MxChat's type category system is a useful pattern for organizing tools visually. Modification: adopt SRC-AXI's macro registry and capability settings, integrate MxChat's category/type organization for admin UI, and formalize the macro schema for external consumers. |
| `modification_notes` | Adopt SRC-AXI macro registry with source tracking. Adopt SRC-AXI per-bot capability settings. Use SRC-MXC category system for visual tool organization in admin. Formalize macro schema documentation. |
| `carry_forward_ids` | `["CFI-TOL-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-INTEGRATIONS", "DOM-SECURITY"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Macro table schema from SRC-AXI ports directly. Capability settings pattern is options-based and straightforward. Category system needs UI design. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-TOL-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-TOL-004` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Admin controls` |
| `capability_summary` | Administration interface for tool/action management including configuration, visual management, access control settings, and user experience. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-TOL-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | `FEAT-AXI-TOL-004` | `implemented` |
| `SRC-MXC` | `ANC-TOL-MXC-001` | `mxchat-basic/includes/admin-actions-page.php` | `FEAT-MXC-TOL-002` | `implemented` |
| `SRC-BAA` | `ANC-TOL-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-TOL-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 3 | 4 | 3.85 |
| `SRC-MXC` | 4 | 4 | 4 | 5 | 3 | 4.00 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat has the strongest admin UI for tool/action management with split-panel layout, mobile responsiveness, two-step editor, category filtering, bulk operations, and dashboard documentation. This is the only capability where SRC-MXC wins (4.00 vs 3.85). AxiaChat's admin is more configuration-oriented (options-based settings). Modification: adopt SRC-MXC's admin UI patterns (split-panel, category filtering, mobile-responsive) as the visual framework for the unified tool management page, while backing it with SRC-AXI's tool registration API and capability settings. |
| `modification_notes` | Use SRC-MXC's split-panel UI pattern for tool management admin. Integrate SRC-AXI's capability settings into the UI (per-bot tool access, domain restrictions). Add tool execution history/logs. Adapt MxChat's type cards for tool categories. |
| `carry_forward_ids` | `["CFI-TOL-004"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | MxChat admin UI patterns are vanilla JS with WordPress escaping -- portable to unified plugin. AxiaChat capability settings are options-based and integrate easily. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-TOL-001

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-TOOLS` |
| `description` | Design unified tool registration architecture: adopt SRC-AXI's tool-calling API as primary mechanism and port SRC-MXC's intent/action concept as a complementary "user actions" subsystem. Define how user-intent actions bridge to the tool registry for dual invocation (AI-invoked and user-triggered). |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-TOL-001` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001", "ANC-TOL-MXC-001"]` |
| `resolution_criteria` | Unified tool registry supports both AI-model-invoked tools (function calling) and user-intent-triggered actions (meaning matching). Registration API documented. Intent-to-tool bridging defined. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-TOL-002

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-TOOLS` |
| `secondary_domains` | `["DOM-RUNTIME", "DOM-PROVIDER"]` |
| `description` | Design tool execution lifecycle with pre/post-execute hooks, configurable timeouts per tool, retry logic, and execution result caching for idempotent tools. Ensure provider-specific schema normalization tracks API changes. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-TOL-002` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001"]` |
| `resolution_criteria` | Tool execution has pre/post hooks for logging and validation. Per-tool timeout enforced. Provider schema normalization is versioned. Execution timing logged. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-TOL-003

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-TOOLS` |
| `secondary_domains` | `["DOM-INTEGRATIONS"]` |
| `description` | Formalize macro registry schema for external consumers. Document tool grouping API for MCP servers and webhook providers. Ensure cascade delete works across integration boundaries. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-TOL-003` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001"]` |
| `resolution_criteria` | Macro schema documented with all fields, validation rules, and source tracking. External tool providers can register macros via documented API. Cascade delete tested. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-TOL-004

| Field | Value |
|---|---|
| `item_id` | `CFI-TOL-004` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-TOOLS` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `description` | Design unified tool management admin UI: merge SRC-MXC split-panel layout with SRC-AXI capability settings. Include tool execution history, category organization, and per-bot access control configuration. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-TOL-004` |
| `related_anchor_ids` | `["ANC-TOL-AXI-001", "ANC-TOL-MXC-001"]` |
| `resolution_criteria` | Tool management page uses split-panel layout with tool list, editor, and capability settings. Categories organize tools visually. Per-bot access control configurable. Mobile responsive. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-TOOLS x DOM-SECURITY

- Tool call validation (DEC-SEC-001) is a security gate for tool execution. Malformed tool calls must be caught before provider dispatch.
- Per-bot capability settings (DEC-TOL-003) are a security access control mechanism.
- Tool execution must pass through moderation and rate limiting (DEC-SEC-003).

### DOM-TOOLS x DOM-RUNTIME

- Tool execution is part of the runtime message lifecycle. Provider calls with tools return tool_use responses that trigger callback execution.
- Tool pending handshake (SRC-AXI pattern) creates a multi-turn conversation flow within a single user request.

### DOM-TOOLS x DOM-PROVIDER

- Provider-specific tool injection (DEC-TOL-002) depends on provider adapter interface from DEC-PRO-001.
- Schema normalization per provider is critical for cross-provider compatibility.
- Provider tool-calling protocol support varies: OpenAI (function calling), Claude (tool use), Gemini (function declarations, REST API limitations).

### DOM-TOOLS x DOM-INTEGRATIONS

- MCP server tools are registered via macro system (DEC-TOL-003) with source tracking.
- Webhook-triggered tools also use macro system for grouping.
- Cascade delete when integration sources removed depends on macro source_ref tracking.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-TOL-001` | Tool/action registration | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.65 |
| `DEC-TOL-002` | Execution engine | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.45 |
| `DEC-TOL-003` | Schema validation and tool lifecycle | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.30 |
| `DEC-TOL-004` | Admin controls | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.00 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 4 |
| Adopt-As-Is | 0 |
| Adopt-With-Modifications | 4 |
| Reject/Defer | 0 |
| Domain Winner | `SRC-AXI` (3 of 4 capabilities) |
| Dominant Maturity | `production_stable` |
| Key Risks | Dual tool paradigm integration (tool calling + intent matching); provider API evolution; macro schema formalization |
| Cross-Domain Dependencies | `DOM-PROVIDER`, `DOM-SECURITY`, `DOM-RUNTIME`, `DOM-INTEGRATIONS`, `DOM-ADMIN` |
| Carry-Forward Items | `CFI-TOL-001`, `CFI-TOL-002`, `CFI-TOL-003`, `CFI-TOL-004` |
