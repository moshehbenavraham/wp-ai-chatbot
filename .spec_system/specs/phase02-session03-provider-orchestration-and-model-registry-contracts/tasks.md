# Task Checklist

**Session ID**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Total Tasks**: 19
**Estimated Duration**: 2.5-3.5 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0203]` = Session reference (phase 02, session 03)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 5 | 5 | 0 |
| Implementation | 8 | 8 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **19** | **19** | **0** |

---

## Setup (2 tasks)

Initial verification and environment preparation.

- [x] T001 [S0203] Verify prerequisites met: confirm session 01 module map (`includes/provider/` location, `WPAIC\Provider` namespace) and session 02 storage schemas (`wpaic_usage_daily`, `wpaic_tool_states`, encrypted options) are accessible and consistent
  - Ref: `specs/phase02-session01-*/directory_structure.md`, `specs/phase02-session02-*/database_table_schemas.md`, `specs/phase02-session02-*/encrypted_storage_spec.md`
- [x] T002 [S0203] Review carry-forward items CFI-PRO-001 (SSE streaming co-design with CFI-RUN-003) and CFI-PRO-002 (generalized retry/fallback) from Phase 01 handoff; document resolution approach in implementation notes
  - Ref: `specs/phase01-session06-*/` (carry-forward bundle)

---

## Foundation (5 tasks)

Core interface definitions and payload schemas.

- [x] T003 [S0203] [P] Define provider interface contract: `Provider_Interface` with `__construct(array $config)`, `get_id(): string`, `chat(array $messages, array $params): array`, `calculate_cost(array $usage, string $model): ?int`; include compliance validation rules and doc-block contracts (`provider_interface_contract.md`)
  - Ref: `axiachat-ai/includes/interfaces/interface-aichat-provider.php`, `mxchat-basic/includes/class-mxchat-integrator.php` (N/A -- no interface), `baachal/baachal.php` (N/A -- no interface)
- [x] T004 [S0203] [P] Define streamable provider interface: `Streamable_Provider_Interface extends Provider_Interface` with `stream_chat(array $messages, array $params): void`; define SSE output contract, chunk delegation hooks, and streaming lifecycle signals (`provider_interface_contract.md`)
  - Ref: `mxchat-basic/includes/class-mxchat-integrator.php` (cURL WRITEFUNCTION SSE), `axiachat-ai/includes/providers/class-openai-provider.php` (non-streaming), `baachal/baachal.php` (N/A -- no streaming)
- [x] T005 [S0203] [P] Define unified chat request payload schema: provider-agnostic JSON schema with `messages`, `model`, `temperature`, `max_tokens`, `tools`, `stream`, `system_prompt`, `metadata` fields; include provider mapping tables showing field translation per provider (`chat_payload_schemas.md`)
  - Ref: `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `mxchat-basic/includes/class-mxchat-integrator.php`, `baachal/baachal.php`
- [x] T006 [S0203] [P] Define unified chat response payload schema: normalized JSON with `message`, `usage` (prompt/completion/total tokens), `finish_reason`, `tool_calls`, `error`, `provider_id`, `model_id`, `request_id` fields; document field presence rules per finish reason (`chat_payload_schemas.md`)
  - Ref: `axiachat-ai/includes/providers/class-openai-provider.php` (response normalization), `axiachat-ai/includes/providers/class-claude-provider.php`, `baachal/baachal.php` (minimal response format)
- [x] T007 [S0203] Define provider registry dispatcher: singleton `Provider_Registry` with `register()`, `get()`, `is_available()`, `list_providers()`; document hook-driven registration via `wpaic_register_providers`, instance caching by ID+config hash, alias normalization map, enable/disable toggling (`provider_registry_dispatcher.md`)
  - Ref: `axiachat-ai/includes/class-aichat-provider-registry.php`, `mxchat-basic/includes/class-mxchat-integrator.php` (inline dispatch -- anti-pattern), `baachal/baachal.php` (switch-case dispatch -- anti-pattern)

---

## Implementation (8 tasks)

Detailed contract specifications for registry, streaming, errors, and cost.

- [x] T008 [S0203] [P] Design model registry data model: in-memory PHP array structure with fields `id`, `provider`, `label`, `context_window`, `max_output`, `pricing` (input/output per 1M in microcents), `aliases`, `deprecated_ids`, `is_default`, `supports_thinking`, `multimodal`, `supports_tools`, `fallback_order`; document `wpaic_model_registry` filter extensibility (`model_registry_data_model.md`)
  - Ref: `axiachat-ai/includes/model-registry.php`, `mxchat-basic/includes/class-mxchat-admin.php` (model lists), `baachal/admin/tabs/general.php` (model selection)
- [x] T009 [S0203] [P] Design model resolution pipeline: deterministic 6-step lookup (exact match, alias match, deprecated ID match, prefix match, provider default, global default); document each step with examples and edge cases; define `wpaic_resolve_model(string $model_id, string $provider_hint): string` signature (`model_registry_data_model.md`)
  - Ref: `axiachat-ai/includes/model-registry.php` (alias + prefix resolution), `mxchat-basic/includes/class-mxchat-integrator.php` (prefix-based detection), `baachal/baachal.php` (prefix-based detection)
- [x] T010 [S0203] Design fallback chain contract: per-provider ordered model arrays, `wpaic_get_fallback_chain(string $provider_id): array`, skip-current-model logic, chain exhaustion behavior (return user-friendly terminal error), `wpaic_fallback_chain` filter for extensibility (`model_registry_data_model.md`)
  - Ref: `axiachat-ai/includes/providers/class-claude-provider.php` (fallback chain logic), `mxchat-basic/includes/class-mxchat-integrator.php` (N/A -- no fallback), `baachal/baachal.php` (N/A -- no fallback)
- [x] T011 [S0203] Design SSE streaming transport layer: define SSE chunk format (`data: {JSON}\n\n`), per-provider chunk parsing delegation (OpenAI `delta.content`, Anthropic `content_block_delta`, Gemini `candidates[].content`), error event signaling (`event: error`), connection lifecycle (open, chunk, done, error, timeout), PHP output buffering requirements (`streaming_transport_layer.md`)
  - Ref: `mxchat-basic/includes/class-mxchat-integrator.php` (cURL WRITEFUNCTION SSE implementation), `axiachat-ai/includes/providers/class-openai-provider.php` (non-streaming reference), `baachal/baachal.php` (N/A -- no streaming)
- [x] T012 [S0203] [P] Design error handling contract: define 4-tier error taxonomy (network/transport, authentication/authorization, rate-limit/quota, model/content), standard error payload shape `['code' => int, 'type' => string, 'message' => string, 'retryable' => bool, 'user_message' => string]`, retryable error detection by HTTP status codes (`error_handling_fallback_contract.md`)
  - Ref: `axiachat-ai/includes/providers/class-claude-provider.php` (error classification), `baachal/baachal.php` (user-friendly error messages), `mxchat-basic/includes/class-mxchat-integrator.php` (basic error handling)
- [x] T013 [S0203] [P] Design retry and backoff contract: exponential backoff (base 2: 1s, 2s, 4s), 3 max attempts, retryable status codes (429, 503, 529), non-retryable fast-fail (401, 403, 400), optional circuit breaker semantics (trip after N failures in window, half-open probe), `wpaic_retry_policy` filter for customization (`error_handling_fallback_contract.md`)
  - Ref: `axiachat-ai/includes/providers/class-claude-provider.php` (exponential backoff exemplar), `mxchat-basic/includes/class-mxchat-integrator.php` (N/A -- no retry), `baachal/baachal.php` (N/A -- no retry)
- [x] T014 [S0203] Design cost accounting interface: `calculate_cost(array $usage, string $model): ?int` returns microcents using registry pricing data; `wpaic_track_usage()` function signature connecting to session 02's `wpaic_usage_daily` table with UPSERT semantics; define metering hook `wpaic_after_provider_request` for decoupled tracking; null handling for free/local models (`cost_accounting_interface.md`)
  - Ref: `axiachat-ai/includes/providers/class-openai-provider.php` (cost calculation), `axiachat-ai/includes/usage.php` (usage tracking), `mxchat-basic/includes/class-mxchat-integrator.php` (N/A -- no cost tracking), `baachal/baachal.php` (N/A -- no cost tracking)
- [x] T015 [S0203] Define provider adapter contract template: concrete requirements checklist for Phase 04 implementors covering interface compliance, constructor config shape, API endpoint URLs, request header requirements, response normalization mapping, streaming chunk format, tool calling protocol, timeout values, and testability requirements (`provider_interface_contract.md`)
  - Ref: `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `axiachat-ai/includes/providers/class-gemini-provider.php`, `mxchat-basic/includes/class-mxchat-integrator.php`, `baachal/baachal.php`

---

## Testing (4 tasks)

Validation and cross-reference verification.

- [x] T016 [S0203] Cross-reference all contracts against carry-forward items: verify CFI-PRO-001 (SSE streaming) is fully resolved by streaming transport layer + streamable interface; verify CFI-PRO-002 (retry/fallback) is fully resolved by error handling + fallback chain contracts; document resolution status in implementation notes
  - Ref: `specs/phase01-session06-*/` (carry-forward items)
- [x] T017 [S0203] [P] Validate contract consistency with session 02 storage schemas: verify `wpaic_usage_daily` column names match cost accounting fields; verify `wpaic_tool_states` schema supports tool calling contract; verify encrypted options keys match provider ID naming; verify `wpaic_` prefix used consistently
  - Ref: `specs/phase02-session02-*/database_table_schemas.md`, `specs/phase02-session02-*/encrypted_storage_spec.md`, `specs/phase02-session02-*/options_schema.md`
- [x] T018 [S0203] [P] Validate streaming boundary for session 04 consumption: confirm provider-side SSE contract is self-contained and session 04 can define runtime-side consumption independently; document interface boundary in streaming transport layer spec; resolve CFI-RUN-003 dependency at interface level
  - Ref: `.spec_system/PRD/phase_02/session_04_chat_runtime_session_model_and_frontend_contracts.md`
- [x] T019 [S0203] Verify all deliverable files are ASCII-encoded with Unix LF line endings; verify every contract document cites reference anchors from all three source plugins; verify `wpaic_` prefix and naming conventions per CONVENTIONS.md; update implementation-notes.md with session summary
  - Ref: `.spec_system/CONVENTIONS.md`

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All 7 contract documents created and internally consistent
- [x] CFI-PRO-001 and CFI-PRO-002 fully resolved
- [x] All files ASCII-encoded
- [x] implementation-notes.md updated
- [x] Ready for `/validate`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
