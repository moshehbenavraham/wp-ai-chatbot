# Session 03: Provider Orchestration and Model Registry Contracts

**Session ID**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 2-4 hours

---

## Objective

Define the provider interface, registry dispatcher pattern, request/response payload schemas, model registry data model, streaming transport layer, and error handling contracts for the multi-provider AI orchestration subsystem.

---

## Scope

### In Scope (MVP)
- Provider interface definition (based on AXI winner pattern with modifications)
- Unified request/response payload schemas (provider-agnostic chat request contract)
- Provider registry dispatcher with singleton+factory pattern
- Model registry data model with deprecation metadata and fallback chains
- Streaming transport layer design (SSE + modular transport, resolving CFI-PRO-001/CFI-RUN-003 circular dependency)
- Error handling, retry logic, and 4-tier fallback contracts
- Cost accounting hooks and usage attribution interface
- Provider adapter contract (OpenAI, Claude, Gemini, DeepSeek, Grok extensibility)

### Out of Scope
- Actual provider adapter implementations (Phase 04)
- Frontend streaming consumption (session 04)
- Embedding provider interface (session 05)

---

## Prerequisites

- [ ] Session 01 completed (module map with provider module location)
- [ ] Session 02 completed (storage contracts for model registry and usage data)
- [ ] Phase 01 DOM-PROVIDER winner summary reviewed (SRC-AXI 4/4, handoff modifications)
- [ ] Carry-forward items reviewed: CFI-PRO-001 (SSE streaming), CFI-PRO-002 (retry/fallback)

---

## Deliverables

1. Provider interface specification (PHP interface with method signatures, doc contracts)
2. Request/response payload schema (JSON schema for unified chat request and response)
3. Provider registry dispatcher design (registration, lookup, validation, instantiation)
4. Model registry data model (model catalog structure, deprecation/fallback metadata)
5. Streaming transport layer specification (SSE protocol, chunk format, error signaling)
6. Error handling and fallback contract (retry policy, fallback chain, circuit breaker semantics)
7. Cost accounting interface specification (per-request metering, attribution model)

---

## Success Criteria

- [ ] Same chat request contract works across all supported providers (as per Phase 01 exit criteria)
- [ ] Streaming co-design with runtime (CFI-PRO-001/CFI-RUN-003) is resolved at interface level
- [ ] Model deprecation auto-migration path is designed and testable
- [ ] Provider adapter contract is clear enough for parallel implementation of multiple adapters
- [ ] Error handling covers network failures, rate limits, auth errors, and model-specific errors
- [ ] Carry-forward items CFI-PRO-001 and CFI-PRO-002 are fully addressed
