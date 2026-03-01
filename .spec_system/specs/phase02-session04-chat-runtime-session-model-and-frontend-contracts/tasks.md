# Task Checklist

**Session ID**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Total Tasks**: 20
**Estimated Duration**: 3-4 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0204]` = Session reference (phase 02, session 04)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 4 | 4 | 0 |
| Implementation | 10 | 10 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **20** | **20** | **0** |

---

## Setup (2 tasks)

Initial verification and directory preparation.

- [x] T001 [S0204] Verify prerequisites met: confirm sessions 01-03 deliverables accessible, review CFI-RUN-001/002/003/004 and CFI-ADM-005 status, confirm reference anchors readable (`axiachat-ai/includes/class-aichat-ajax.php`, `mxchat-basic/includes/class-mxchat-public.php`, `baachal/baachal.php`)
- [x] T002 [S0204] Create deliverable directory structure under `.spec_system/specs/phase02-session04-chat-runtime-session-model-and-frontend-contracts/deliverables/`

---

## Foundation (4 tasks)

Architectural decisions and structural definitions that downstream tasks depend on.

- [x] T003 [S0204] [P] Define message lifecycle pipeline stages and hook taxonomy -- enumerate pre-processing stages (validation, session resolution, rate limiting, spam, moderation, context injection), dispatch stage (provider invocation, tool continuation), and post-processing stages (sanitization, rendering, cost tracking, logging); name all `wpaic_*` hooks with argument signatures (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 131-391, `baachal/baachal.php` AJAX registration)
- [x] T004 [S0204] [P] Define session lifecycle states and transition rules -- map states (created, active, closed, expired) with allowed transitions, ownership validation strategy (anonymous vs authenticated), session token format, and expiry policy; cross-reference `wp_wpaic_sessions` schema from session 02 (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 55-80 UUID generation, `mxchat-basic/js/chat-script.js` lines 47-56 cookie sessions, `baachal/assets/chatbot.js` lines 6-20 localStorage sessions)
- [x] T005 [S0204] [P] Resolve CFI-RUN-004/CFI-ADM-005 vanilla JS frontend architecture decision -- document IIFE/revealing module pattern, instance registry design, `wp_localize_script` configuration injection, and event model; rationale for no-jQuery; confirm this unblocks both frontend and admin JS (ref: `mxchat-basic/js/chat-script.js` lines 7-79 MxChatInstances registry, `baachal/assets/chatbot.js` lines 2-20 state management, `axiachat-ai/assets/js/aichat-frontend.js` jQuery plugin pattern)
- [x] T006 [S0204] Define endpoint routing and authentication model -- decide AJAX vs REST API per endpoint type, nonce strategy (action-specific names, generation via `wp_create_nonce`), capability requirements (public vs admin), rate limiting integration points (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 55 nonce validation, `mxchat-basic/includes/class-mxchat-public.php` shortcode AJAX setup, `baachal/baachal.php` AJAX registration)

---

## Implementation (10 tasks)

Contract document authoring -- each task produces or extends a deliverable.

- [x] T007 [S0204] Write message lifecycle pipeline specification (`deliverables/message_lifecycle_pipeline.md`) -- document all pipeline stages with data flow diagrams, hook points with signatures, early-exit conditions, anti-spam integration (honeypot, rate limiter from `lib/rate-limiter.php`, spam signature, moderation hooks), and tool continuation two-phase model (ref: `axiachat-ai/includes/class-aichat-ajax.php` full pipeline lines 1-800, `baachal/baachal.php` hook-driven AJAX, `mxchat-basic/includes/class-mxchat-public.php` N/A -- no server pipeline in public class)
- [x] T008 [S0204] Write session model contract (`deliverables/session_model_contract.md`) -- session CRUD operations, resumption logic, ownership validation (dual anonymous/authenticated strategy), history retrieval with configurable depth, token/cost accumulation, expiry and cleanup via WP-Cron, interface methods for runtime module (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 55-80 and 340-342 history assembly, `mxchat-basic/js/chat-script.js` lines 47-56 per-bot sessions, `baachal/assets/chatbot.js` lines 441-468 history loading; consumes session 02 `wp_wpaic_sessions` + `wp_wpaic_messages` schemas)
- [x] T009 [S0204] Write REST/AJAX endpoint specifications (`deliverables/endpoint_specifications.md`) -- chat message send (AJAX, public), chat streaming (AJAX/SSE, public), session create/resume/close (REST, public), session history retrieval (REST, public), session delete (REST, admin); include route, method, auth, nonce, request schema with field types and validation, response schema, error codes (ref: `axiachat-ai/includes/class-aichat-ajax.php` `wp_ajax_/wp_ajax_nopriv_` registration, `mxchat-basic/includes/class-mxchat-public.php` `mxchat_handle_chat_request`, `baachal/baachal.php` `baachal_message` action)
- [x] T010 [S0204] Write widget and shortcode rendering contract (`deliverables/widget_shortcode_contract.md`) -- shortcode attributes (`[wpaic_chat]` with bot, layout, position, title, placeholder overrides), data-attribute injection for frontend JS, inline vs floating embed modes, multi-instance support, bot resolution priority (shortcode attr > page meta > global default > first bot), template hook points (ref: `axiachat-ai/includes/shortcode.php` lines 34-309 data attributes, `baachal/templates/chatbot-widget.php` lines 7-182 hooks and options, `mxchat-basic/includes/class-mxchat-public.php` lines 164-249 multi-bot and theme)
- [x] T011 [S0204] Write frontend state management specification (`deliverables/frontend_state_management.md`) -- vanilla JS IIFE module, `WPAICChat` global namespace, instance registry (per-widget), session token management (localStorage with cookie fallback), message state (DOM-canonical with metadata tracking), UI states (idle, sending, streaming, error, offline), custom DOM events (`wpaic:message-sent`, `wpaic:response-received`, `wpaic:stream-chunk`, `wpaic:error`), `wp_localize_script` config shape (ref: `mxchat-basic/js/chat-script.js` lines 7-79 instance registry, `baachal/assets/chatbot.js` lines 94-211 event system, `axiachat-ai/assets/js/aichat-frontend.js` jQuery instance pattern)
- [x] T012 [S0204] Write streaming delivery contract (`deliverables/streaming_delivery_contract.md`) -- SSE consumption via `EventSource` API, normalized chunk processing (consuming session 03 `{type, content}` / `{type, usage}` / `{type, done}` format), progressive DOM rendering (append text nodes, not innerHTML replacement), connection lifecycle (open, chunk, done, error, timeout, reconnect policy), HTTP header requirements, output buffer flush strategy, WordPress/server compatibility notes (ref: session 03 `streaming_transport_layer.md` SSE protocol; `mxchat-basic/js/chat-script.js` lines 408-415 streaming detection and temporary message, `axiachat-ai/assets/js/aichat-frontend.js` N/A -- uses AJAX polling, `baachal/assets/chatbot.js` N/A -- no streaming)
- [x] T013 [S0204] Write input/output safety contract (`deliverables/input_output_safety.md`) -- input sanitization (PHP: `sanitize_textarea_field`, `wp_unslash`, length limits; JS: trim, escapeHtml for user display), output rendering (PHP: `wp_kses` with explicit allowlist, markdown-to-HTML conversion rules; JS: DOMParser with allowed tag/attribute allowlist), XSS prevention (safe href patterns, forced `target="_blank" rel="noopener noreferrer"` on links), content security policy recommendations (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 131-200 sanitization, `mxchat-basic/js/chat-script.js` lines 284-399 DOMParser sanitization, `baachal/assets/chatbot.js` lines 284-400 DOMParser sanitization)
- [x] T014 [S0204] Write page context injection specification (`deliverables/page_context_injection.md`) -- resolves CFI-RUN-001; context types (page URL, title, main content text, custom data attributes), extraction rules (CSS selector priority, content stripping, character limit), injection point in pipeline (pre-processing stage after session resolution), handler hooks for extension, opt-in/opt-out controls, privacy considerations (ref: `mxchat-basic/js/chat-script.js` lines 208-311 `getPageContext()` extraction with selector priority, `axiachat-ai/includes/class-aichat-ajax.php` lines 297-305 `page_id` context mode, `baachal/assets/chatbot.js` N/A -- no page context injection)
- [x] T015 [S0204] Write anti-spam and rate limiting integration section in pipeline spec -- extend `deliverables/message_lifecycle_pipeline.md` with detailed anti-spam stages: honeypot field contract, rate limiter integration (consuming `lib/rate-limiter.php`), duplicate message detection, message length validation (configurable max, default 4000 chars), captcha filter hook, moderation hook chain with soft-fail behavior (ref: `axiachat-ai/includes/class-aichat-ajax.php` lines 131-200 honeypot/captcha/rate-limit/spam/moderation pipeline, `mxchat-basic/includes/class-mxchat-public.php` N/A -- no server-side spam checks, `baachal/baachal.php` N/A -- no spam pipeline)
- [x] T016 [S0204] Cross-reference all contracts and verify CFI item resolution -- trace CFI-RUN-001 (page context) to `page_context_injection.md`, CFI-RUN-002 (session storage, resolved session 02) to `session_model_contract.md`, CFI-RUN-003 (streaming, resolved session 03) to `streaming_delivery_contract.md`, CFI-RUN-004 + CFI-ADM-005 (frontend arch) to `frontend_state_management.md`; verify no contract gaps or contradictions; add cross-reference table to `implementation-notes.md`

---

## Testing (4 tasks)

Verification and quality assurance of contract documents.

- [x] T017 [S0204] Validate endpoint contracts enable parallel implementation -- verify every endpoint has unambiguous request/response schemas, confirm frontend can implement against schemas without backend, confirm backend can implement without frontend assumptions, check for missing error codes or edge cases
- [x] T018 [S0204] Validate streaming end-to-end design -- trace complete path from provider chunk emission (session 03 `Streamable_Provider_Interface` callback) through runtime SSE output to frontend `EventSource` consumption to DOM rendering; identify any gaps in the chain
- [x] T019 [S0204] Verify ASCII encoding and Unix LF line endings on all deliverable files
- [x] T020 [S0204] Write implementation notes (`deliverables/../implementation-notes.md`) -- session summary, key architectural decisions, CFI resolution table, lessons learned, recommendations for Phase 05 implementers

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All 8 deliverable documents written
- [x] All CFI items (RUN-001, RUN-004, ADM-005) resolved in contracts
- [x] All files ASCII-encoded
- [x] Unix LF line endings
- [x] implementation-notes.md updated
- [x] Every deliverable cites reference anchors from >= 2 source plugins
- [x] Ready for `/validate`

---

## Next Steps

Run `/validate` to verify session completeness.
