# Session Specification

**Session ID**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session defines the complete contract surface for the multi-provider AI orchestration subsystem -- the central nervous system that connects the unified plugin to external AI services. Every chat request, model selection, streaming response, error recovery, and cost tracking decision flows through the interfaces designed here.

The provider orchestration contracts build directly on the module map from session 01 (which placed provider code under `includes/provider/`) and the data layer schemas from session 02 (which defined `wpaic_usage_daily` for cost tracking and `wpaic_tool_states` for tool execution persistence). This session formalizes the PHP interfaces, payload schemas, registry patterns, streaming protocol, error handling strategy, and cost accounting hooks that all provider adapters must implement in Phase 04.

The design adopts AxiaChat's provider interface and registry dispatcher as the winning pattern (DEC-PRO-001/DEC-PRO-003 from Phase 01), integrates MxChat's SSE streaming approach (resolving CFI-PRO-001), generalizes Claude's retry/fallback pattern across all adapters (resolving CFI-PRO-002), and incorporates Baachal's user-friendly error messaging into the normalized error contract.

---

## 2. Objectives

1. Define a PHP provider interface contract with method signatures clear enough for parallel adapter implementation in Phase 04.
2. Specify unified request/response payload schemas that decouple all consumers (runtime, admin, tools) from provider-specific formats.
3. Design the provider registry dispatcher with singleton+factory pattern, hook-driven registration, and instance caching.
4. Formalize the model registry data model with deprecation metadata, alias resolution, and ordered fallback chains.
5. Specify the SSE streaming transport layer contract, resolving the CFI-PRO-001/CFI-RUN-003 circular dependency at the interface boundary.
6. Define the error handling and retry/fallback contract covering network failures, rate limits, auth errors, and model-specific errors.
7. Specify the cost accounting interface for per-request metering and daily usage aggregation against session 02's `wpaic_usage_daily` table.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase02-session01-module-map-directory-architecture-and-bootstrap` - Provider module location (`includes/provider/`), namespace (`WPAIC\Provider`), bootstrap sequence, hook registration pattern
- [x] `phase02-session02-data-layer-schema-design-and-migration-framework` - Storage contracts for `wpaic_usage_daily` table, `wpaic_tool_states` table, encrypted options schema for API keys, options naming conventions

### Required Tools/Knowledge
- Phase 01 DOM-PROVIDER winner decisions (DEC-PRO-001 through DEC-PRO-004)
- Carry-forward items CFI-PRO-001 (SSE streaming) and CFI-PRO-002 (retry/fallback)
- Reference provider implementations from all three source plugins

### Environment Requirements
- Read access to `axiachat-ai/`, `mxchat-basic/`, `baachal/` reference repositories
- No runtime environment needed (design/contract session)

---

## 4. Scope

### In Scope (MVP)
- Provider interface definition with method signatures, return types, and doc contracts -- based on AxiaChat's `AIChat_Provider_Interface` with modifications
- Streamable provider interface extending base for SSE-capable adapters -- resolving CFI-PRO-001/CFI-RUN-003 boundary
- Unified request payload schema (provider-agnostic chat request) -- normalizing across OpenAI, Anthropic, Gemini, DeepSeek, Grok formats
- Unified response payload schema (normalized response with usage, finish reason, tool calls) -- single consumer contract
- Provider registry dispatcher design (singleton+factory, hook registration, instance caching, alias normalization) -- based on AxiaChat pattern
- Model registry data model (in-memory PHP array, filterable, static per request) -- catalog structure with deprecation/fallback metadata
- Model resolution pipeline (exact match, alias, deprecated ID, prefix, provider default, global default) -- deterministic 6-step lookup
- Streaming transport layer specification (SSE protocol, per-provider chunk formats, error signaling, lifecycle management) -- integrating MxChat's cURL WRITEFUNCTION approach
- Error handling contract (4-tier taxonomy, retryable error detection, exponential backoff, circuit breaker semantics) -- generalizing AxiaChat Claude adapter pattern
- Fallback chain contract (per-provider ordered model chains, skip-current logic, exhaustion behavior) -- from AxiaChat model registry
- Cost accounting interface (per-request metering via `calculate_cost()`, daily aggregation via `wpaic_track_usage()`) -- connecting to session 02 storage
- Provider adapter contract template (concrete requirements checklist for Phase 04 implementors)

### Out of Scope (Deferred)
- Actual provider adapter implementations (OpenAI, Claude, Gemini, DeepSeek, Grok) -- *Reason: Phase 04 scope*
- Frontend streaming consumption and rendering -- *Reason: Session 04 scope*
- Embedding provider interface for RAG pipeline -- *Reason: Session 05 scope*
- Admin UI for provider configuration and model selection -- *Reason: Session 06 scope*
- Tool execution engine and macro routing -- *Reason: Phase 07 scope*

---

## 5. Technical Approach

### Architecture
The provider subsystem follows a layered contract design:

1. **Interface Layer**: `Provider_Interface` and `Streamable_Provider_Interface` define the adapter contract
2. **Registry Layer**: `Provider_Registry` manages adapter registration, lookup, and lifecycle
3. **Model Layer**: `wpaic_get_model_registry()` provides static model metadata with resolution pipeline
4. **Transport Layer**: SSE streaming protocol with per-provider chunk parsing delegation
5. **Error Layer**: Unified error taxonomy with retry/backoff and fallback chain orchestration
6. **Metering Layer**: Cost calculation and usage tracking hooks

### Design Patterns
- **Interface Segregation**: Base interface for sync chat, extended interface for streaming -- adapters opt in to streaming
- **Singleton + Factory**: Registry is singleton, creates adapter instances via factory with config injection
- **Strategy**: Each adapter encapsulates provider-specific API translation behind a common contract
- **Chain of Responsibility**: Fallback chains cascade through model alternatives on failure
- **Observer**: Hook-driven registration (`wpaic_register_providers`) and filterable registry (`wpaic_model_registry`)

### Technology Stack
- PHP >=7.4 (interface definitions, no runtime code in this session)
- WordPress hooks and filters API (registration and extensibility patterns)
- JSON Schema (payload format specifications)
- SSE protocol (streaming transport specification)

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `provider_interface_contract.md` | Provider interface + Streamable interface PHP signatures, doc contracts, compliance rules | ~200 |
| `chat_payload_schemas.md` | Unified request/response JSON schemas with field-level docs, provider mapping tables | ~250 |
| `provider_registry_dispatcher.md` | Registry singleton+factory design, registration API, instance caching, alias normalization | ~180 |
| `model_registry_data_model.md` | Model catalog structure, resolution pipeline, fallback chains, deprecation handling | ~220 |
| `streaming_transport_layer.md` | SSE protocol spec, per-provider chunk formats, error signaling, lifecycle management | ~200 |
| `error_handling_fallback_contract.md` | Error taxonomy, retry/backoff policy, circuit breaker, fallback chains, user-friendly messages | ~220 |
| `cost_accounting_interface.md` | Metering interface, daily aggregation, pricing model, connection to `wpaic_usage_daily` | ~150 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| (none) | This session creates new contract documents only | - |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Same chat request contract works across all supported providers (OpenAI, Claude, Gemini, DeepSeek, Grok)
- [ ] Provider interface is clear enough for parallel adapter implementation without ambiguity
- [ ] Model resolution pipeline is deterministic with documented behavior for every input class
- [ ] Streaming contract defines provider-to-runtime boundary clearly (resolving CFI-PRO-001/CFI-RUN-003)
- [ ] Error handling covers network failures, rate limits (429), auth errors (401/403), overload (503/529), and model-specific errors
- [ ] Fallback chain logic handles exhaustion gracefully with user-friendly terminal messages
- [ ] Cost accounting connects to session 02's `wpaic_usage_daily` schema without schema changes

### Testing Requirements
- [ ] Each contract includes testability notes (what can be unit tested, what needs integration testing)
- [ ] Provider interface includes compliance validation rules (for registry to enforce)

### Non-Functional Requirements
- [ ] All contracts use `wpaic_` prefix for hooks, filters, functions, and options per CONVENTIONS.md
- [ ] Contracts use `snake_case` for PHP functions, `PascalCase` for classes, `camelCase` for JS per CONVENTIONS.md
- [ ] Error payloads include enough context for debugging without exposing secrets per CONVENTIONS.md

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions
- [ ] Carry-forward items CFI-PRO-001 and CFI-PRO-002 are fully resolved
- [ ] Every contract document cites reference anchors from all three source plugins

---

## 8. Implementation Notes

### Key Considerations
- Provider interface must support PHP >=7.4 syntax (no union types, no enums, no named arguments)
- Model registry is in-memory (PHP array), not a database table -- per session 02 decision
- API keys use AES-256-GCM encryption stored in `wp_options` -- per session 02 encrypted options schema
- Streaming is adapter-level responsibility; runtime consumes a uniform SSE stream

### Potential Challenges
- **CFI-PRO-001/CFI-RUN-003 circular dependency**: Mitigated by defining the streaming boundary in this session -- providers emit SSE, runtime coordinates lifecycle. Session 04 will consume this contract.
- **PHP 7.4 interface limitations**: No return type narrowing in interfaces. Use doc-block contracts with `@return` annotations for type guidance.
- **Provider-specific chunk formats**: OpenAI, Anthropic, and Gemini have different SSE chunk structures. The transport layer must define a normalization contract.
- **Fallback chain vs. user intent**: Automatic fallback may switch to a cheaper/less capable model. Contract must define when fallback is appropriate vs. when failure should surface.

### Reference Anchors

#### AxiaChat (Primary winner pattern)
- `axiachat-ai/includes/interfaces/interface-aichat-provider.php` -- Provider interface contract
- `axiachat-ai/includes/class-aichat-provider-registry.php` -- Registry dispatcher pattern
- `axiachat-ai/includes/model-registry.php` -- Model catalog and resolution
- `axiachat-ai/includes/providers/class-openai-provider.php` -- OpenAI adapter (dual-API routing, tool calling)
- `axiachat-ai/includes/providers/class-claude-provider.php` -- Claude adapter (retry/backoff exemplar, tool state persistence)
- `axiachat-ai/includes/providers/class-gemini-provider.php` -- Gemini adapter

#### MxChat (SSE streaming reference)
- `mxchat-basic/includes/class-mxchat-integrator.php` -- SSE streaming via cURL WRITEFUNCTION, 5-provider dispatch, per-provider chunk parsing
- `mxchat-basic/includes/class-mxchat-admin.php` -- Provider configuration and model selection

#### Baachal (User-friendly error reference)
- `baachal/baachal.php` -- Provider functions, user-friendly error messaging (quota, overload, config hints)
- `baachal/admin/tabs/general.php` -- Provider key and model validation UX

### Relevant Considerations
<!-- From CONSIDERATIONS.md -->
- **Anchor-first extraction**: All contract documents must cite specific reference file paths from source plugins. Every design decision traces to observed patterns.
- **Canonical vocabulary with alias tables**: Provider IDs, model IDs, and error codes must use canonical naming from PRD domain taxonomy. Alias tables handle backward compatibility.
- **Explicit N/A policy**: Where a source plugin lacks an equivalent capability (e.g., Baachal has no streaming, no tool calling), mark as `N/A (no matching implementation)` in reference tables.

---

## 9. Testing Strategy

### Unit Tests
- Provider interface compliance validation (registry rejects non-conforming classes)
- Model resolution pipeline (exact, alias, deprecated, prefix, provider default, global default)
- Fallback chain logic (skip current, exhaustion, empty chain)
- Cost calculation (microcent arithmetic, null for free models)

### Integration Tests
- Registry registration via hook (`wpaic_register_providers`)
- Model registry filtering via `wpaic_model_registry` filter
- Usage tracking insert/upsert against `wpaic_usage_daily` table

### Manual Testing
- Review each contract document for completeness and internal consistency
- Cross-reference contracts against session 02 storage schemas
- Verify streaming boundary is consumable by session 04 prerequisites

### Edge Cases
- Provider with no models in registry (empty provider)
- Deprecated model with no fallback chain
- All fallback models fail (chain exhaustion)
- Streaming connection drops mid-response
- Zero-cost model (local/proxy) with null pricing
- Unknown provider ID in registry lookup
- Concurrent model registry filter modifications (race condition prevention)

---

## 10. Dependencies

### External Libraries
- None (contract session -- no runtime dependencies)

### Other Sessions
- **Depends on**: `phase02-session01` (module map, namespaces), `phase02-session02` (storage schemas, encrypted options)
- **Depended by**: `phase02-session04` (streaming consumption, provider dispatch from runtime), `phase02-session05` (embedding provider interface extends patterns here), `phase04-*` (all provider adapter implementations)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
