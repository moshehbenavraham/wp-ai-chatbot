# Provider Scored Comparison

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Domain**: `DOM-PROVIDER`
**Context Pack**: `CP-P01-S01-001`
**Scoring Rubric**: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Created**: 2026-03-01

---

## Scoring Scale Reference

| Score | Meaning |
|---|---|
| 0 | Not present or unusable |
| 1 | Bare-minimum with major limits |
| 2 | Partial with clear constraints |
| 3 | Solid for common use cases |
| 4 | Strong with low friction |
| 5 | Best-in-class among reference plugins |

**Weighted Total**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

---

## Capability 1: Provider Registry and Adapter Dispatch

Comparison of how each source organizes provider management and routing.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-PRO-001` | `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 | `adopt` |
| `FEAT-MXC-PRO-001` | `SRC-MXC` | 4 | 3 | 3 | 3 | 2 | 3.10 | `investigate` |
| `FEAT-BAA-PRO-001` | `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 | `investigate` |

### Evidence Notes

#### FEAT-AXI-PRO-001 - SRC-AXI

- Coverage: 5 - Full Singleton+Factory registry with dynamic registration hook, interface validation, alias normalization, enable/disable toggling, instance caching, and debug logging. Covers all provider lifecycle needs.
- Reliability: 4 - Instance caching by config hash prevents redundant construction. Exception handling on instantiation. No retry at registry level (handled per-provider).
- Security: 4 - Interface compliance validation prevents arbitrary class instantiation. Class existence check before registration. No direct API key exposure. Debug logging gated behind `AICHAT_DEBUG`.
- Operability: 4 - `get_stats()` provides runtime diagnostics. Debug logging traces registration and cache events. Clear error messages for missing classes and interface violations.
- Extensibility: 5 - `aichat_register_providers` hook allows add-on registration. New providers added without modifying dispatch. Enable/disable per provider. Config-hash cache supports multi-tenant scenarios.

- Weighted Summary: 4.45 (strong adopt candidate)
- Recommendation: adopt
- Residual Risks: Singleton pattern limits testability; no health-check probe per provider.

#### FEAT-MXC-PRO-001 - SRC-MXC

- Coverage: 4 - Routes to 5 providers (OpenAI, Claude, Gemini, DeepSeek, xAI Grok) via model prefix detection. Supports bot-specific option overrides. Covers more providers than SRC-AXI.
- Reliability: 3 - No formal provider lifecycle management. Monolithic class (10632 lines) creates maintenance risk. Provider routing mixed with session/RAG/moderation logic.
- Security: 3 - Nonce and session ownership checks in request handler. API key retrieval from options. No interface validation on provider paths.
- Operability: 3 - Provider selection via model prefix is implicit rather than explicit. No registry diagnostics. Configuration spread across 70+ options.
- Extensibility: 2 - Adding a new provider requires editing the monolithic integrator class. No registration hook or interface contract. Tight coupling between routing and business logic.

- Weighted Summary: 3.10 (investigate - structural concerns)
- Recommendation: investigate
- Residual Risks: Monolithic coupling makes provider-level testing and extension impractical without refactoring.

#### FEAT-BAA-PRO-001 - SRC-BAA

- Coverage: 3 - Simple switch-case dispatch to 4 providers (Gemini, OpenAI, Claude, Grok). Functional but minimal. No model resolution, no alias handling.
- Reliability: 3 - Direct `wp_remote_post()` calls with consistent error response shape. No fallback chain. No retry logic. Hardcoded parameters limit adaptability.
- Security: 3 - API key retrieval from options with empty-key validation. No interface contract to exploit. Minimal attack surface due to simplicity.
- Operability: 3 - Provider selection via dropdown in admin. Clear error messages. But hardcoded max_tokens/temperature not configurable without code changes.
- Extensibility: 2 - Adding a provider means adding a new method and switch case. No hook, no registry, no interface. Hardcoded parameters resist configuration.

- Weighted Summary: 2.85 (investigate - minimal but functional)
- Recommendation: investigate
- Residual Risks: Hardcoded parameters and no extensibility mechanism limit future growth.

---

## Capability 2: Provider-Specific API Adapters

Comparison of individual provider adapter implementations.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-PRO-002` | `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 | `adopt` |
| `FEAT-AXI-PRO-003` | `SRC-AXI` | 5 | 5 | 4 | 4 | 5 | 4.65 | `adopt` |
| `FEAT-AXI-PRO-004` | `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 | `adopt` |
| `FEAT-MXC-PRO-002` | `SRC-MXC` | 4 | 4 | 3 | 3 | 3 | 3.50 | `investigate` |
| `FEAT-BAA-PRO-001` | `SRC-BAA` | 3 | 3 | 3 | 2 | 2 | 2.70 | `reject` |

### Evidence Notes

#### FEAT-AXI-PRO-002 - SRC-AXI (OpenAI)

- Coverage: 5 - Dual-API routing (Chat Completions + Responses API for GPT-5.x). Multi-round tool calling. Handshake pattern for client-side execution. Cost calculation. Leaked function_call cleanup.
- Reliability: 4 - Empty response validation. WP_Error checking. Token field normalization. No built-in retry (handled at caller level). Timeout: 45-60s.
- Security: 4 - API key validation with agency proxy fallback. No key exposure in logs. Uses `wp_json_encode()` and `wp_remote_post()`.
- Operability: 4 - Debug logging for API calls. Cost tracking per model. Clear error messages. Model detection is automatic.
- Extensibility: 5 - Tool execution via trait. Filterable tool rounds (`aichat_max_tool_rounds`). Web search injection via filter (`aichat_openai_responses_tools`). Agency proxy support.

- Weighted Summary: 4.45 (strong adopt)
- Recommendation: adopt
- Residual Risks: No built-in retry at adapter level; relies on caller.

#### FEAT-AXI-PRO-003 - SRC-AXI (Claude)

- Coverage: 5 - Full Anthropic Messages API implementation. Multi-round tool calling with handshake. DB-backed tool state. Web search. Correct system prompt handling (separate field).
- Reliability: 5 - 4-tier fallback chain. Exponential backoff retry (3 attempts for 429/503/529). 404 triggers next model in chain. Empty response validation.
- Security: 4 - API key validation. DB tool state uses `$wpdb->prepare()`. Tool state auto-cleanup (one-time use). No key exposure.
- Operability: 4 - Debug logging with model name. Retry count tracking. Clear error reporting per attempt.
- Extensibility: 5 - Filterable tools (`aichat_claude_messages_tools`). Configurable max retries (`aichat_claude_max_retries`). Tool execution via trait.

- Weighted Summary: 4.65 (strong adopt - best individual adapter)
- Recommendation: adopt
- Residual Risks: Fallback chain only for Anthropic; not generalized.

#### FEAT-AXI-PRO-004 - SRC-AXI (Gemini)

- Coverage: 5 - Full Gemini API with native message format. Thinking mode per model tier. Streaming endpoint. Multi-tool support (native + custom). Web search via native `googleSearch` tool.
- Reliability: 4 - Pricing from centralized registry. Thinking mode auto-configuration. No explicit fallback chain (Gemini has 3-tier in registry but adapter usage unclear from excerpt).
- Security: 4 - API key via URL parameter (Gemini standard). No key in headers. Uses centralized registry for defaults.
- Operability: 4 - Thinking mode auto-managed. Model-specific configuration. Pricing integration.
- Extensibility: 5 - Tool injection via `aichat_gemini_tools` filter. Native tool combination. Tool execution via trait.

- Weighted Summary: 4.45 (strong adopt)
- Recommendation: adopt
- Residual Risks: API key in URL parameter is Gemini standard but less secure than header-based auth.

#### FEAT-MXC-PRO-002 - SRC-MXC (Streaming)

- Coverage: 4 - Full SSE streaming for 5 providers with cURL WRITEFUNCTION callbacks. Provider-specific chunk parsing. Headers management.
- Reliability: 4 - Output buffer control. Headers sent only once. Provider-specific parsing handles format differences.
- Security: 3 - API keys in cURL headers. No additional security layer. Streaming responses not sanitized during transit.
- Operability: 3 - Streaming test facility in admin. But streaming logic embedded in monolithic class.
- Extensibility: 3 - Provider-specific parsing functions could be extracted. Currently hardcoded per provider.

- Weighted Summary: 3.50 (investigate)
- Recommendation: investigate
- Residual Risks: Streaming logic tightly coupled to integrator class.

#### FEAT-BAA-PRO-001 - SRC-BAA (API Dispatch)

- Coverage: 3 - 4 providers with basic API calls. Conversation history support. Context injection. Safety filter handling (Gemini).
- Reliability: 3 - Consistent error shape. WP_Error handling. But no retry, no fallback, hardcoded parameters.
- Security: 3 - API key from options. Empty-key check. Standard `wp_remote_post()`.
- Operability: 2 - Hardcoded temperature/max_tokens. No diagnostics. No cost tracking. Configuration only via code changes.
- Extensibility: 2 - No hooks. No interface. Adding provider requires new method + switch case modification.

- Weighted Summary: 2.70 (reject - below threshold)
- Recommendation: reject
- Residual Risks: Hardcoded parameters and no extensibility make this unsuitable as baseline.

---

## Capability 3: Model Registry and Resolution

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-PRO-005` | `SRC-AXI` | 5 | 5 | 4 | 5 | 5 | 4.85 | `adopt` |
| `FEAT-MXC-PRO-003` | `SRC-MXC` | 3 | 3 | 4 | 4 | 3 | 3.35 | `investigate` |
| `FEAT-BAA-PRO-003` | `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 | `reject` |

### Evidence Notes

#### FEAT-AXI-PRO-005 - SRC-AXI

- Coverage: 5 - 35+ models across 3 providers. Context window, max output, pricing, aliases, deprecated IDs, fallback order, thinking/multimodal flags, tags. Most comprehensive catalog.
- Reliability: 5 - Static cache. Fallback chains (Anthropic 4-tier, Gemini 3-tier). Model resolution handles exact/alias/prefix/default. Legacy pricing bridge for backward compatibility.
- Security: 4 - No direct security concerns. Filterable but filters run in WP context.
- Operability: 5 - JS payload builder for admin UI. Tag labels ([NEW], [RECOMMENDED]). Per-provider defaults. Legacy pricing compatibility.
- Extensibility: 5 - Filterable via `aichat_model_registry` hook. Add models without code changes. Alias and deprecated mapping support migrations.

- Weighted Summary: 4.85 (strong adopt - highest score in session)
- Recommendation: adopt
- Residual Risks: None significant. Registry is well-designed and extensible.

#### FEAT-MXC-PRO-003 - SRC-MXC

- Coverage: 3 - Provider config and streaming test. Default model configurable. But no centralized model catalog; model detection by prefix.
- Reliability: 3 - Streaming test validates connectivity. But no model resolution, no fallback chains, no alias handling.
- Security: 4 - Nonce and capability checks on AJAX handlers. API key sanitization. Per-field input validation.
- Operability: 4 - Admin UI with per-provider API key management. Streaming test facility. 70+ configurable options.
- Extensibility: 3 - Options-based configuration. No model registry hook. New models recognized automatically by prefix.

- Weighted Summary: 3.35 (investigate)
- Recommendation: investigate
- Residual Risks: Lack of centralized model catalog means model metadata (limits, pricing) must be managed elsewhere.

#### FEAT-BAA-PRO-003 - SRC-BAA

- Coverage: 2 - 20+ models in HTML select options. Labels with descriptions. But no programmatic catalog, no metadata, no resolution.
- Reliability: 3 - Simple dropdown selection is reliable. But no validation that selected model exists at provider.
- Security: 3 - Proper escaping (`esc_attr`, `checked`, `selected`). Password input for API keys.
- Operability: 3 - Clean settings tab with conditional visibility per provider. User-friendly labels.
- Extensibility: 2 - Adding models requires editing HTML template. No hook or filter.

- Weighted Summary: 2.55 (reject)
- Recommendation: reject
- Residual Risks: Hardcoded model lists will become stale without manual updates.

---

## Capability 4: Error Handling and Provider Resilience

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-PRO-003` | `SRC-AXI` | 5 | 5 | 4 | 4 | 4 | 4.50 | `adopt` |
| `FEAT-MXC-PRO-001` | `SRC-MXC` | 3 | 3 | 3 | 3 | 2 | 2.85 | `investigate` |
| `FEAT-BAA-PRO-002` | `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 | `investigate` |

### Evidence Notes

#### FEAT-AXI-PRO-003 - SRC-AXI (Claude as exemplar)

- Coverage: 5 - Fallback chain, exponential backoff, retryable error classification (429/503/529), 404 model-not-found triggers next in chain, empty response validation, token normalization.
- Reliability: 5 - 3-attempt retry with backoff. 4-tier fallback chain. Explicit handling per HTTP status code.
- Security: 4 - Error messages logged without exposing API keys. Debug gated behind constant.
- Operability: 4 - Per-attempt logging with model name. Retry count tracking.
- Extensibility: 4 - Configurable max retries via filter. Fallback chain from registry. But pattern not generalized to all providers.

- Weighted Summary: 4.50 (adopt)
- Recommendation: adopt
- Residual Risks: Retry/fallback pattern only implemented for Claude; OpenAI and Gemini adapters lack equivalent resilience.

#### FEAT-MXC-PRO-001 - SRC-MXC

- Coverage: 3 - Basic HTTP error handling. Rate limit responses. Ban detection via moderation module.
- Reliability: 3 - Error response sent to client. No retry or fallback mechanism.
- Security: 3 - Error messages sanitized via `esc_html__()`.
- Operability: 3 - Error logging via `error_log()`. No structured error diagnostics.
- Extensibility: 2 - No configurable error handling. Fixed error paths.

- Weighted Summary: 2.85 (investigate)
- Recommendation: investigate
- Residual Risks: No resilience mechanisms; single-point-of-failure per provider call.

#### FEAT-BAA-PRO-002 - SRC-BAA

- Coverage: 3 - Gemini safety filters, quota detection (429), overload detection, HTTP status classification. Consistent error shape.
- Reliability: 3 - User-friendly error suggestions. But no retry, no fallback.
- Security: 3 - No stack traces or internal details exposed in errors.
- Operability: 3 - Helpful error messages for common failures (quota, config issues).
- Extensibility: 2 - Error handling hardcoded per provider method. No hooks.

- Weighted Summary: 2.85 (investigate)
- Recommendation: investigate
- Residual Risks: Same as SRC-MXC: no resilience mechanisms.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 5.0 | 4.5 | 4.0 | 4.3 | 5.0 | 4.56 | `adopt` |
| `SRC-MXC` | 3.5 | 3.3 | 3.3 | 3.3 | 2.5 | 3.18 | `investigate` |
| `SRC-BAA` | 2.8 | 3.0 | 3.0 | 2.8 | 2.0 | 2.72 | `reject` |

**Notes on aggregation**: Averages computed across the 4 capabilities scored above, giving equal weight to each capability. SRC-AXI scores are driven by dedicated adapter architecture and centralized model registry. SRC-MXC has functional completeness but structural limitations. SRC-BAA is minimal and hardcoded.
