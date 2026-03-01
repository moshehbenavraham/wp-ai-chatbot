# Session Specification

**Session ID**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session designs the chat runtime pipeline architecture, session storage model, endpoint contracts, frontend rendering and state management, and input/output safety specifications. It bridges the provider layer contracts (session 03) to the user-facing chat experience, defining how messages flow from user input through pre-processing, provider dispatch, and post-processing back to the rendered response.

The session resolves two critical carry-forward items: CFI-RUN-001 (page context injection) and CFI-RUN-004/CFI-ADM-005 (frontend architecture decision -- vanilla JS). It consumes the streaming transport layer from session 03 and the session/message table schemas from session 02, producing contracts detailed enough for parallel frontend and backend implementation in Phase 05.

This is the largest contract session in Phase 02 because the chat runtime sits at the intersection of every other subsystem: providers, sessions, security, RAG context, and the frontend. Getting these contracts right prevents costly rework during implementation.

---

## 2. Objectives

1. Define a multi-stage message lifecycle pipeline with explicit hook points for pre-processing (validation, moderation, rate limiting), provider dispatch, and post-processing (sanitization, rendering, logging).
2. Specify session creation, resumption, ownership validation, expiry, and storage interface contracts that consume the `wp_wpaic_sessions` and `wp_wpaic_messages` table schemas from session 02.
3. Produce REST/AJAX endpoint specifications with request/response schemas, authentication models, and nonce strategies complete enough for parallel frontend/backend implementation.
4. Resolve CFI-RUN-004/CFI-ADM-005 by defining the vanilla JS frontend architecture with instance management, event model, and DOM update strategy.
5. Design input sanitization and output rendering safety contracts covering all OWASP-relevant injection vectors.
6. Specify page context injection (CFI-RUN-001) with context types, extraction rules, and handler hooks.
7. Define SSE streaming delivery from runtime to frontend, consuming the provider-side streaming contract from session 03.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase02-session01-module-map-directory-architecture-and-bootstrap` - Module map with `includes/runtime/` location, naming conventions (`wpaic_` prefix), bootstrap sequence, dependency graph (runtime depends on provider, security, rag)
- [x] `phase02-session02-data-layer-schema-design-and-migration-framework` - `wp_wpaic_sessions` table schema (UUID token, ownership validation, token/cost tracking), `wp_wpaic_messages` table (paired message/response rows), options schema (64 keys), transient patterns (13 types)
- [x] `phase02-session03-provider-orchestration-and-model-registry-contracts` - `Provider_Interface`, `Streamable_Provider_Interface`, chat payload schemas, streaming transport layer (SSE protocol, normalized chunks), error handling/fallback contract, cost accounting interface

### Required Knowledge
- Phase 01 DOM-RUNTIME winner summary (AXI message pipeline, MXC streaming, BAA hooks)
- Carry-forward items: CFI-RUN-001 (page context), CFI-RUN-002 (resolved -- session storage), CFI-RUN-003 (resolved -- streaming transport), CFI-RUN-004 (frontend architecture), CFI-ADM-005 (admin JS architecture)

### Environment Requirements
- Access to reference plugin source code (read-only)
- Session 01-03 deliverable documents in `.spec_system/specs/`

---

## 4. Scope

### In Scope (MVP)
- Message lifecycle pipeline architecture (pre-processing chain, provider dispatch, post-processing) - combines AXI's multi-stage validation with BAA's hook-driven extensibility
- Session model contract consuming `wp_wpaic_sessions` / `wp_wpaic_messages` schemas - unified session format replacing 3 divergent source patterns (CFI-RUN-002 resolved in session 02)
- AJAX/REST endpoint contracts for chat message send, streaming, session create/resume/close/history - with nonce, capability, and auth requirements
- Widget and shortcode rendering contracts (inline and floating embed modes, configuration params, data attributes) - best-of from AXI shortcode attributes + BAA template hooks + MXC multi-bot support
- Vanilla JS frontend architecture decision resolving CFI-RUN-004/CFI-ADM-005 - instance registry, event model, DOM update strategy
- Input sanitization contract (validation rules, length limits, escaping) and output rendering contract (markdown, HTML allowlist, XSS prevention) - unified from all three source patterns
- Streaming delivery to frontend (consuming provider SSE from session 03, rendering chunks, connection lifecycle)
- Page context injection design (CFI-RUN-001) - combining MXC's contextual awareness extraction with AXI's page_id mode
- Anti-spam and rate limiting integration points in the message pipeline

### Out of Scope (Deferred)
- Provider-side streaming implementation details - *Reason: designed in session 03*
- Knowledge retrieval and RAG context assembly - *Reason: session 05 scope*
- Content moderation pipeline details - *Reason: session 06 scope*
- Actual CSS/HTML templates and visual design - *Reason: Phase 05 implementation*
- Tool/action execution within message pipeline - *Reason: Phase 07 scope (contract hooks defined but not detailed)*
- Voice input/output - *Reason: post-MVP feature*
- Email blocker / pre-chat gating forms - *Reason: Phase 05 implementation detail*
- GDPR consent modal implementation - *Reason: Phase 09 privacy hardening*

---

## 5. Technical Approach

### Architecture
The chat runtime pipeline follows a linear multi-stage architecture inspired by AXI's validated pipeline with BAA's hook-driven extensibility. The pipeline has three macro-stages:

1. **Pre-processing**: Input validation, session resolution, rate limiting, spam checks, moderation, page context injection
2. **Provider dispatch**: Message construction, history assembly, provider invocation (sync or streaming), tool continuation
3. **Post-processing**: Response sanitization, markdown rendering, cost tracking, conversation logging, hook execution

Each stage exposes WordPress action/filter hooks for extensibility without modifying core pipeline code.

### Design Patterns
- **Pipeline pattern**: Sequential stages with early-exit on validation failure (AXI pattern)
- **Instance registry**: Frontend manages per-widget state via a singleton registry object (MXC pattern)
- **Event-driven extensibility**: WordPress hooks at each pipeline stage + custom DOM events on frontend (BAA pattern)
- **Contract-first**: Endpoint schemas defined with enough detail for parallel frontend/backend implementation

### Technology Stack
- PHP >=7.4 (WordPress plugin backend)
- Vanilla JavaScript (no jQuery dependency -- resolves CFI-RUN-004/CFI-ADM-005)
- WordPress AJAX (`admin-ajax.php`) for chat endpoints
- Server-Sent Events (SSE) for streaming responses
- WordPress REST API for session management endpoints

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `message_lifecycle_pipeline.md` | Pipeline stages, hook points, data flow, anti-spam integration | ~450 |
| `session_model_contract.md` | Session creation, resumption, ownership, expiry, storage interface | ~350 |
| `endpoint_specifications.md` | REST/AJAX routes, auth, request/response schemas for chat and session management | ~500 |
| `widget_shortcode_contract.md` | Embed modes, configuration params, data attributes, output structure | ~300 |
| `frontend_state_management.md` | Vanilla JS architecture, instance registry, event model, DOM updates | ~400 |
| `streaming_delivery_contract.md` | SSE consumption lifecycle, chunk rendering, connection management | ~300 |
| `input_output_safety.md` | Sanitization rules, validation, HTML allowlist, XSS prevention | ~350 |
| `page_context_injection.md` | Context types, extraction rules, injection points, handler hooks | ~250 |
| `implementation-notes.md` | Session summary, key decisions, lessons learned | ~100 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set `current_session`, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Message pipeline supports multi-turn chat with session persistence via `wp_wpaic_sessions` / `wp_wpaic_messages`
- [ ] Frontend architecture decision (vanilla JS) resolves CFI-RUN-004/CFI-ADM-005 circular dependency
- [ ] Endpoint contracts include complete request/response schemas with field types, validation rules, and error codes
- [ ] Streaming delivery is end-to-end designed from provider SSE chunks to frontend DOM rendering
- [ ] Input sanitization covers all OWASP-relevant injection vectors (XSS, HTML injection, script injection)
- [ ] Page context injection (CFI-RUN-001) specifies what context is extracted, size limits, and handler hooks
- [ ] Anti-spam integration points are defined in the pipeline (honeypot, rate limiting, moderation hooks)

### Testing Requirements
- [ ] Contracts cross-referenced against CFI-RUN-001 through CFI-RUN-004 for completeness
- [ ] Endpoint schemas validated for parallel implementation readiness (no ambiguous fields)
- [ ] Streaming end-to-end path traced from provider chunk emission to frontend DOM update

### Non-Functional Requirements
- [ ] All contracts reference session 02 table schemas and session 03 provider contracts explicitly
- [ ] Every deliverable cites reference anchors from at least 2 of 3 source plugins

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Consistent use of `wpaic_` prefix in all hook, option, and endpoint names
- [ ] File paths match session 01 module map conventions

---

## 8. Implementation Notes

### Key Considerations
- The runtime module sits at dependency level 3 in the module graph (depends on provider, security, rag) -- contracts must define clean interfaces to each dependency without circular imports
- Session ownership validation uses dual strategy from session 02: `(session_token, ip_address, user_agent_hash)` for anonymous users, `(session_token, user_id)` for authenticated users
- Streaming requires HTTP header control (`Content-Type: text/event-stream`) before any output -- this constrains endpoint architecture
- Tool continuation requires a two-phase request model (initial request returns `tool_pending`, follow-up sends tool results) -- borrowed from AXI pattern

### Potential Challenges
- **Vanilla JS without build tools**: Must design module pattern that works in browser without bundling -- use IIFE or revealing module pattern with `wp_localize_script` for configuration injection
- **SSE through WordPress**: `admin-ajax.php` + output buffering + server timeouts may interfere with SSE -- document required server configuration
- **Anonymous session security**: Balancing session resumption convenience against session hijacking -- ownership validation must be strict without being brittle on mobile networks

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned #1): Every contract document must cite concrete reference anchors so Phase 05 implementation can proceed without re-reading full source repositories
- **Explicit N/A policy** (Lesson Learned #5): Where a source plugin lacks equivalent functionality for a contract element, document `N/A (no matching implementation)` with rationale

### Behavioral Quality Focus
Checklist active: No (session produces contract documents, not application code)
Note: BQC requirements are embedded into contract specifications so Phase 05 implementers inherit them:
- Endpoint specs include error states, rate limit responses, and timeout handling
- Session contract includes cleanup on scope exit, state reset on re-entry
- Frontend spec includes loading, empty, error, and offline states
- Input/output safety contract includes validation at system boundaries

---

## 9. Testing Strategy

### Contract Validation
- Cross-reference every pipeline stage against AXI's validated pipeline to ensure no stages are missing
- Verify session model contract covers all state transitions from session 02 table schema
- Trace data flow end-to-end: user input -> frontend -> endpoint -> pipeline -> provider -> response -> frontend render

### Completeness Checks
- Every endpoint has: route, method, auth requirement, nonce strategy, request schema, response schema, error codes
- Every hook has: name, arguments, return type, when it fires, example usage
- Every frontend event has: name, detail payload, when dispatched, cancellable flag

### Cross-Reference
- CFI-RUN-001: Page context injection fully specified
- CFI-RUN-002: Session model uses `wp_wpaic_sessions` schema (already resolved in session 02)
- CFI-RUN-003: Streaming delivery consumes session 03 SSE transport (already resolved in session 03)
- CFI-RUN-004 + CFI-ADM-005: Vanilla JS architecture decision documented with rationale

### Edge Cases
- Anonymous session on mobile network (IP changes mid-session)
- Concurrent messages from same session (race condition handling)
- Streaming connection dropped mid-response
- Provider timeout during tool continuation phase
- Widget rendered multiple times on same page (multi-instance)

---

## 10. Dependencies

### External Libraries
- None (vanilla JS frontend, WordPress PHP backend)

### Reference Anchors

#### Message Pipeline
- `axiachat-ai/includes/class-aichat-ajax.php` - Primary pipeline pattern (multi-stage validation, provider dispatch, tool continuation)
- `mxchat-basic/includes/class-mxchat-public.php` - Multi-bot support, email blocker, theme system
- `baachal/baachal.php` - AJAX registration, hook-driven extensibility

#### Frontend & Widget
- `axiachat-ai/includes/shortcode.php` - Shortcode attributes, data-attribute injection, GDPR setup, honeypot
- `axiachat-ai/assets/js/aichat-frontend.js` - Instance management, session handling, voice I/O, GDPR consent
- `mxchat-basic/js/chat-script.js` - Instance registry pattern, per-bot sessions, page context extraction, streaming detection
- `baachal/templates/chatbot-widget.php` - Template structure, hook points, option-driven configuration
- `baachal/assets/chatbot.js` - Toggle state, localStorage sessions, event system, HTML sanitization

#### Session & Streaming
- `axiachat-ai/includes/class-aichat-ajax.php` (lines 55-127) - Session ID validation, UUID generation, tool continuation
- `mxchat-basic/js/chat-script.js` (lines 47-56) - Cookie-based per-bot session management
- `baachal/assets/chatbot.js` (lines 6-20) - localStorage session persistence

#### Input/Output Safety
- `axiachat-ai/includes/class-aichat-ajax.php` (lines 131-200) - Honeypot, captcha, rate limit, spam signature, moderation pipeline
- `mxchat-basic/js/chat-script.js` (lines 284-399) - DOMParser HTML sanitization with allowlist
- `baachal/assets/chatbot.js` (lines 284-400) - DOMParser HTML sanitization, escapeHtml fallback

### Other Sessions
- **Depends on**: `phase02-session01` (module map), `phase02-session02` (data layer), `phase02-session03` (provider contracts)
- **Depended by**: `phase02-session05` (RAG pipeline -- context assembly hooks), `phase02-session06` (security/admin -- moderation pipeline hooks), `phase02-session07` (migration -- session migration paths)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
