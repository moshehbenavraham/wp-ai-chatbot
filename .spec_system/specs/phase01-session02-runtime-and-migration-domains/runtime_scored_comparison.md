# Runtime Scored Comparison

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Domain**: `DOM-RUNTIME`
**Context Pack**: `CP-P01-S02-001`
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

## Capability 1: Message Lifecycle and Request Handling

Comparison of how each source processes chat messages from intake to response.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RUN-001` | `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 | `adopt` |
| `FEAT-MXC-RUN-001` | `SRC-MXC` | 4 | 3 | 4 | 3 | 3 | 3.50 | `investigate` |
| `FEAT-BAA-RUN-001` | `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.15 | `investigate` |

### Evidence Notes

#### FEAT-AXI-RUN-001 - SRC-AXI

- Coverage: 5 - Complete pipeline: pre-processing filter chain (captcha, honeypot, rate limiting, spam, moderation), message construction with system prompt + history + context, provider dispatch via registry, tool-pending handshake for GPT-5, post-processing (markdown, HTML sanitization, suggestions). Most comprehensive message lifecycle.
- Reliability: 4 - Structured error handling with validation errors (400/403/404), moderation blocks with flag, provider failures logged with request UUID. Missing: no streaming means longer wait times on slow providers.
- Security: 4 - Nonce verification (`wp_verify_nonce`), input sanitization (`sanitize_textarea_field`, `sanitize_title`, `sanitize_key`), output sanitization (`wp_kses_post`), rate limit filter, captcha/honeypot hooks. No capability checks (intentional for public access).
- Operability: 4 - Debug logging via `aichat_log_debug()` with request UUID for tracing. Configurable bot slugs. Clear error messages. Conversation logging with token/cost tracking.
- Extensibility: 5 - Extensive hook system: `aichat_validate_captcha`, `aichat_pre_process_message`, `aichat_messages_before_provider`, `aichat_maybe_intercept_request`, `aichat_provider_override`, `aichat_after_response`. Can completely override provider selection, intercept requests, and modify responses.

- Weighted Summary: 4.45 (strong adopt candidate)
- Recommendation: adopt
- Residual Risks: No streaming means full-text wait; tool-pending handshake adds complexity for GPT-5 models.

#### FEAT-MXC-RUN-001 - SRC-MXC

- Coverage: 4 - Dual-mode handling (standard AJAX + streaming). Contextual awareness (page content/title injection). Vision processing flags. Multi-bot support with per-bot options. Chat mode tracking (AI/agent/livechat). But all logic in monolithic 10632-line class.
- Reliability: 3 - Streaming fallback if SSE fails. Rate limiting and moderation integrated. But monolithic class creates maintenance risk and makes isolated testing difficult.
- Security: 4 - Nonce verification, session ownership validation (IP/user check with automatic reset on mismatch), input sanitization. Session hijacking prevention is a strength not found in other sources.
- Operability: 3 - Implicit provider selection via model prefix (not as clear as registry dispatch). No structured request tracing. 70+ configuration options spread across multiple surfaces.
- Extensibility: 3 - Filter hooks for pre-processing and bot options. Multi-bot addon integration. But adding new request handling modes requires modifying the monolithic integrator class.

- Weighted Summary: 3.50 (investigate - solid but structurally constrained)
- Recommendation: investigate
- Residual Risks: Monolithic class makes runtime pipeline changes risky. Provider routing mixed with session/RAG/moderation logic.

#### FEAT-BAA-RUN-001 - SRC-BAA

- Coverage: 3 - Simple AJAX handler with message validation, session management, custom handler filter, provider dispatch, WooCommerce integration. Functional but minimal pipeline -- no tool support, no contextual awareness, no multi-bot.
- Reliability: 3 - Consistent error responses. Message length validation (1000 char max). But no rate limiting, no moderation, no retry logic.
- Security: 3 - Nonce verification, input sanitization (`sanitize_text_field`), session ID format validation. No IP tracking or session ownership validation.
- Operability: 3 - Clean error messages. Debug mode for technical details. Simple configuration. But no request tracing, no token/cost tracking.
- Extensibility: 4 - Rich hook system: `baachal_custom_message_handler` (complete handler replacement), `baachal_before_process_message`, `baachal_api_params`, `baachal_bot_response`, `baachal_before_save_message`, `baachal_should_save_message`. More hooks per line of code than any other source.

- Weighted Summary: 3.15 (investigate - clean but limited)
- Recommendation: investigate
- Residual Risks: No rate limiting, no moderation, no contextual awareness. Simple architecture limits multi-provider resilience.

---

## Capability 2: Session Handling and Conversation State

Comparison of how each source manages chat sessions and conversation history.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RUN-002` | `SRC-AXI` | 4 | 4 | 3 | 4 | 3 | 3.65 | `investigate` |
| `FEAT-MXC-RUN-002` | `SRC-MXC` | 5 | 4 | 5 | 3 | 3 | 4.20 | `adopt` |
| `FEAT-BAA-RUN-002` | `SRC-BAA` | 4 | 3 | 3 | 3 | 4 | 3.45 | `investigate` |

### Evidence Notes

#### FEAT-AXI-RUN-002 - SRC-AXI

- Coverage: 4 - Session in WP options keyed by session_id. History retrieval with configurable max messages (default 20) and context length (4096 chars). Bot-slug routing for multi-bot. Conversation logging table with tokens/costs. Missing: no session ownership validation.
- Reliability: 4 - Stable option-based storage. History truncation prevents unbounded growth. Conversation logging provides audit trail. Cookie-based session (30-day expiry).
- Security: 3 - Session ID sanitized via dedicated function. No session ownership validation -- any client with the session ID can access history. No IP binding.
- Operability: 4 - Token and cost tracking per conversation. Configurable history limits per bot. Clear session identification.
- Extensibility: 3 - Bot-slug routing allows multi-bot. History builder is configurable. But no hooks for session lifecycle events (create/restore/expire).

- Weighted Summary: 3.65 (investigate)
- Recommendation: investigate
- Residual Risks: No session ownership validation; session ID in cookie could be shared or stolen.

#### FEAT-MXC-RUN-002 - SRC-MXC

- Coverage: 5 - WP options with per-bot cookies. Session ownership validation (IP/user check). Persistence filtering via sessionStartTimestamp. Chat mode tracking per session (AI/agent/livechat). Multi-bot instance isolation. Session reset on ownership mismatch. Most complete session management.
- Reliability: 4 - Ownership validation prevents stale sessions. Automatic reset on IP change. Persistence filtering handles both persistent and ephemeral modes. But options-based storage can grow unbounded without cleanup.
- Security: 5 - Session ownership validation with `mxchat_session_owner_{session_id}` comparing IP and user. Automatic reset prevents session hijacking. Nonce on all session operations. Best security posture for sessions among all sources.
- Operability: 3 - Multiple session-related options per conversation (history, owner, mode). No centralized session management UI. Session state spread across many WP options.
- Extensibility: 3 - Per-bot session isolation. Chat mode support. But no hooks for session lifecycle events. Session storage tightly coupled to WP options.

- Weighted Summary: 4.20 (adopt)
- Recommendation: adopt
- Residual Risks: Options-based storage at scale (many concurrent sessions) could strain `wp_options` table. No session expiration or cleanup mechanism.

#### FEAT-BAA-RUN-002 - SRC-BAA

- Coverage: 4 - Custom post type for conversations with post meta messages array. Session metadata (session_id, user_id, user_ip, user_agent). Memory window (default 10). Max messages (100). Persistence toggle. localStorage session on frontend. Different architectural choice (CPT vs options).
- Reliability: 3 - CPT storage leverages WordPress post lifecycle. Max message limit prevents unbounded growth. But no session ownership validation, no IP binding on subsequent requests.
- Security: 3 - Session ID validated (format check). User IP and user agent stored as metadata. But no ownership enforcement on subsequent requests -- stored for audit only.
- Operability: 3 - Conversations visible as WordPress posts (queryable, deletable). Memory window and max message limits configurable. But no admin UI for session management beyond post editing.
- Extensibility: 4 - Hooks for message save (`baachal_before_save_message`, `baachal_should_save_message`), history filtering (`baachal_conversation_history`), message limits (`baachal_message_limit`, `baachal_memory_limit`). Most extensible session system via hooks.

- Weighted Summary: 3.45 (investigate)
- Recommendation: investigate
- Residual Risks: CPT storage may not scale well for high-volume chat. No session ownership enforcement. localStorage session on frontend less secure than server-side cookie.

---

## Capability 3: Streaming and Real-Time Delivery

Comparison of how each source delivers responses in real-time.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RUN-003` | `SRC-AXI` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |
| `FEAT-MXC-RUN-003` | `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 | `adopt` |
| `FEAT-BAA-RUN-003` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-RUN-003 - SRC-AXI

- Coverage: 0 - No streaming implementation in the runtime AJAX pipeline. Full-text JSON responses only.
- Reliability: 0 - N/A (not implemented).
- Security: 0 - N/A (not implemented).
- Operability: 0 - N/A (not implemented).
- Extensibility: 0 - N/A (not implemented).

- Weighted Summary: 0.00 (reject - not implemented)
- Recommendation: reject
- Residual Risks: N/A entry. Provider-level streaming exists (Gemini endpoint) but is not exposed through the runtime pipeline.

#### FEAT-MXC-RUN-003 - SRC-MXC

- Coverage: 5 - Full SSE streaming for 5 providers. Backend: cURL WRITEFUNCTION with provider-specific chunk parsing. Frontend: Fetch API with ReadableStream. Headers: Content-Type text/event-stream, Cache-Control no-cache, X-Accel-Buffering no. Completion signal: `data: [DONE]\n\n`. Automatic non-streaming fallback.
- Reliability: 4 - Output buffer control prevents premature flushing. Provider-specific parsing handles format differences (OpenAI, Claude, Gemini, DeepSeek, Grok). Fallback to non-streaming if SSE fails. But streaming errors mid-stream can leave partial content.
- Security: 3 - API keys in cURL headers (standard). Streaming responses not sanitized during transit (sanitized on frontend after accumulation). SSE format could theoretically inject script if parsing is incorrect.
- Operability: 4 - Streaming test facility in admin validates real-time delivery per provider. Frontend accumulation shows incremental progress. Error detection during stream.
- Extensibility: 3 - Provider-specific parsing functions could be extracted. Currently hardcoded per provider in integrator class. Adding a new provider's streaming format requires modifying the monolithic class.

- Weighted Summary: 3.95 (adopt)
- Recommendation: adopt
- Residual Risks: Streaming logic embedded in monolithic class. Mid-stream error handling could be more robust. SSE responses not sanitized during transit.

**Cross-domain note**: Streaming delivery mechanism scored here under DOM-RUNTIME. Provider-specific SSE parsing was noted in Session 01 (CFI-PRO-001) as a DOM-PROVIDER concern. The boundary: DOM-RUNTIME owns the SSE transport and frontend rendering; DOM-PROVIDER owns the provider-specific chunk format parsing.

#### FEAT-BAA-RUN-003 - SRC-BAA

- Coverage: 0 - No streaming capability. All responses are synchronous full-text JSON.
- Reliability: 0 - N/A (not implemented).
- Security: 0 - N/A (not implemented).
- Operability: 0 - N/A (not implemented).
- Extensibility: 0 - N/A (not implemented).

- Weighted Summary: 0.00 (reject - not implemented)
- Recommendation: reject
- Residual Risks: N/A entry. Absence of streaming means longer perceived wait times for users.

---

## Capability 4: Frontend State Management and UX Runtime

Comparison of how each source manages frontend widget state and user interaction.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RUN-004` | `SRC-AXI` | 4 | 4 | 3 | 4 | 3 | 3.65 | `investigate` |
| `FEAT-MXC-RUN-004` | `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 | `adopt` |
| `FEAT-BAA-RUN-004` | `SRC-BAA` | 3 | 3 | 4 | 3 | 5 | 3.50 | `investigate` |

### Evidence Notes

#### FEAT-AXI-RUN-004 - SRC-AXI

- Coverage: 4 - Cookie-based session. Double-submit prevention. Usage limit tracking. Thinking stages (progressive feedback). Suggestion chips. Draggable widget. Auto-scroll. Config via localization. Missing: no streaming UI, no file upload, no multi-bot instances.
- Reliability: 4 - `aichatInFlight` flag prevents double-submit. Cookie session persists across page loads (30-day). Thinking stages provide user feedback during long waits. Auto-scroll reliable.
- Security: 3 - User input displayed as text (not HTML). Error messages in DOM but no explicit XSS sanitization on frontend (relies on server `wp_kses_post`).
- Operability: 4 - Progressive thinking indicators reduce perceived wait time. Suggestion chips guide interaction. Draggable widget for positioning. Config-driven via `AIChatVars`.
- Extensibility: 3 - Config via localized vars. But no JS hook system. Widget behavior not customizable from outside. Adding new UI features requires modifying the script.

- Weighted Summary: 3.65 (investigate)
- Recommendation: investigate
- Residual Risks: No streaming UI means users see loading indicator for full request duration. No JS hook system limits addon customization.

#### FEAT-MXC-RUN-004 - SRC-MXC

- Coverage: 5 - Multi-bot instance registry (`MxChatInstances`). Real-time streaming DOM updates. Per-bot session cookies. Popular question chips. PDF/Word file upload. Message dedup via processedMessageIds. Textarea auto-resize. Status code error mapping. Most feature-complete frontend.
- Reliability: 4 - Instance registry prevents state conflicts between bots. Streaming accumulation handles partial chunks. `disableChatInput()`/`enableChatInput()` prevents interaction during processing. Session dedup prevents duplicate message display.
- Security: 4 - User messages escaped via `escapeHtml()`. Bot HTML sanitized via `sanitizeBotHtml()` DOMParser whitelist. File upload with MIME validation. Session cookie per bot.
- Operability: 4 - Real-time streaming provides immediate feedback. Popular questions reduce typing friction. File upload toolbar. Error messages with status code context.
- Extensibility: 3 - `customMxChatFilter` for JS-side filtering. Bot options override. But tight coupling to MxChat backend API format. No documented JS hook API.

- Weighted Summary: 4.15 (adopt)
- Recommendation: adopt
- Residual Risks: Complex multi-bot state management. Heavy jQuery dependency. Tight coupling to backend SSE format.

#### FEAT-BAA-RUN-004 - SRC-BAA

- Coverage: 3 - localStorage session. Widget toggle. Loading indicator. Textarea auto-resize. Message display with typing. Clear history. Missing: no streaming, no file upload, no multi-bot, no suggestion chips.
- Reliability: 3 - `isLoading` flag prevents double-submit. localStorage persists across sessions. Auto-scroll. But no fallback for localStorage unavailability.
- Security: 4 - `escapeHtml()` for user messages. `sanitizeBotHtml()` DOMParser whitelist with safe href pattern validation (http/https, mailto, tel, /, #). Best client-side HTML sanitization with explicit safe protocol list.
- Operability: 3 - Simple, clean widget. Clear history button. But no streaming feedback, no status indicators beyond loading.
- Extensibility: 5 - Most extensive JS hook system: `baachal_before_send_message`, `baachal_response_received`, `baachal_filter_bot_response`, `baachal_before/after_open`, `baachal_before/after_close`, `baachal_before/after_add_message`, `baachal_filter_error_message`, `baachal_filter_connection_error`. Every interaction point is hookable.

- Weighted Summary: 3.50 (investigate)
- Recommendation: investigate
- Residual Risks: Minimal UI features. No streaming means loading-indicator-only feedback. localStorage session less secure than httpOnly cookie.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 3.25 | 3.00 | 2.50 | 3.00 | 2.75 | 2.94 | `investigate` |
| `SRC-MXC` | 4.75 | 3.75 | 4.00 | 3.50 | 3.00 | 3.95 | `adopt` |
| `SRC-BAA` | 2.50 | 2.25 | 2.50 | 2.25 | 3.25 | 2.54 | `reject` |

**Notes on aggregation**: Averages computed across the 4 capabilities scored above, giving equal weight to each capability. SRC-AXI's aggregate is pulled down significantly by the streaming N/A (0 scores). If streaming is excluded, SRC-AXI averages 3.92 (adopt). SRC-MXC leads due to streaming implementation and session security. SRC-BAA's aggregate is pulled down by streaming N/A and limited coverage.

**Important caveat**: The streaming capability has outsized impact on aggregates. SRC-AXI and SRC-BAA score 0 across all dimensions for streaming, which pulls their averages below their actual non-streaming capability quality. The domain winner assessment in the decision log should weight this appropriately.
