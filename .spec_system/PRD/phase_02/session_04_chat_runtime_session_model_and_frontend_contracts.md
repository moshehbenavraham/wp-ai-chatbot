# Session 04: Chat Runtime, Session Model, and Frontend Contracts

**Session ID**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Status**: Not Started
**Estimated Tasks**: ~20
**Estimated Duration**: 3-4 hours

---

## Objective

Design the chat runtime pipeline architecture, session storage model, AJAX/REST endpoint contracts, frontend widget/shortcode rendering, frontend state management, and input/output safety contracts.

---

## Scope

### In Scope (MVP)
- Message lifecycle pipeline architecture (pre-processing chain, provider dispatch, post-processing)
- Session model and storage interface (dedicated table from session 02)
- AJAX/REST endpoint contracts (request/response formats, auth requirements)
- Widget and shortcode rendering contracts (embed modes, configuration options)
- Frontend state management architecture (vanilla JS decision, resolving CFI-RUN-004/CFI-ADM-005)
- Input sanitization and output rendering contracts (HTML escaping, markdown rendering)
- Streaming delivery to frontend (consuming provider SSE from session 03)
- Page context injection design (CFI-RUN-001)
- Anti-spam and rate limiting integration points

### Out of Scope
- Provider-side streaming (session 03, already designed)
- Knowledge retrieval integration (session 05)
- Moderation pipeline details (session 06)
- Actual CSS/HTML templates (Phase 05)

---

## Prerequisites

- [ ] Session 01 completed (module map with runtime module location)
- [ ] Session 02 completed (session storage table schema, message table schema)
- [ ] Session 03 completed (streaming transport layer and provider dispatch contracts)
- [ ] Phase 01 DOM-RUNTIME winner summary reviewed (AXI message pipeline, MXC streaming, BAA hooks)
- [ ] Carry-forward items reviewed: CFI-RUN-001 (page context), CFI-RUN-002 (session storage), CFI-RUN-003 (streaming transport), CFI-RUN-004 (frontend)

---

## Deliverables

1. Message lifecycle pipeline specification (stages, hook points, data flow)
2. Session model contract (session creation, resumption, expiry, storage interface)
3. REST/AJAX endpoint specifications (routes, methods, auth, request/response schemas)
4. Widget and shortcode rendering contract (configuration params, output structure)
5. Frontend state management specification (vanilla JS architecture, event model, DOM update strategy)
6. Input/output safety contract (sanitization rules, rendering rules, XSS prevention)
7. Page context injection specification (what context, when injected, handler hooks)

---

## Success Criteria

- [ ] Message pipeline supports multi-turn chat with session persistence
- [ ] Frontend architecture decision (vanilla JS) resolves CFI-RUN-004/CFI-ADM-005 circular dependency
- [ ] Endpoint contracts are complete enough for parallel frontend/backend implementation
- [ ] Streaming delivery from provider to frontend is end-to-end designed
- [ ] Input sanitization covers all OWASP-relevant injection vectors
- [ ] Carry-forward items CFI-RUN-001 through CFI-RUN-004 are fully addressed
