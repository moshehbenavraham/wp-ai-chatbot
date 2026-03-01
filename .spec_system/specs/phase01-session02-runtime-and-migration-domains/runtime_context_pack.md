# Runtime Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S02-001` |
| `session_id` | `phase01-session02-runtime-and-migration-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-RUNTIME"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-RUNTIME` - `runtime`

**Slice Count**: 8 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat AJAX Request Handler

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-01` |
| `anchor_id` | `ANC-RUN-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `symbol_or_scope` | `AIChat_Ajax::process_message()`, `AIChat_Ajax::get_history()`, `AIChat_Ajax::handle_tool_continuation()` |
| `excerpt_summary` | Central AJAX handler for chat message lifecycle. Receives user messages via `wp_ajax_aichat_process_message`, applies pre-processing filters (captcha, honeypot, rate limiting, spam/moderation), builds message array with system prompt + history + current message, dispatches to provider registry, handles tool-pending state for GPT-5 handshake pattern, processes response through markdown rendering and HTML sanitization, returns structured JSON. Session history via `get_history()` with configurable max messages (default 20) and context length (4096 chars). Optional conversation logging to `wp_aichat_conversations` table with user_id, tokens, costs. |
| `excerpt_lines` | `L1-L650` (estimated) |
| `token_estimate` | `7500` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | Extensive filter/action hook system: `aichat_validate_captcha`, `aichat_pre_process_message`, `aichat_messages_before_provider`, `aichat_maybe_intercept_request`, `aichat_provider_override`, `aichat_after_response`. Nonce verified via `wp_verify_nonce($_POST['nonce'], 'aichat_ajax')`. No native streaming -- full-text JSON responses only. |

---

### Slice 2 - AxiaChat Frontend JavaScript

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-02` |
| `anchor_id` | `ANC-RUN-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/assets/js/aichat-frontend.js` |
| `symbol_or_scope` | Chat widget initialization, message send/receive, session management, UI state |
| `excerpt_summary` | jQuery-based frontend managing chat widget lifecycle. Session ID stored in cookie `aichat_sid` (UUID, 30-day expiry). DOM structure: `.aichat-messages` container with `.user-message` and `.bot-message` divs, typing indicator, suggestion chips, error displays. Double-submit prevention via `data('aichatInFlight')` flag. Usage limit tracking via `data('aichatLimited')`. Thinking stages: 0s (dots), 3s ("Thinking"), 6s ("Still working"). Draggable widget via jQuery drag handlers. Config via `AIChatVars` localization object. Auto-scroll on message append. Input via Enter key or button click. |
| `excerpt_lines` | `L1-L800` (estimated) |
| `token_estimate` | `5500` |
| `notes` | No streaming support in frontend -- waits for complete AJAX response. Suggestion chips rendered from response data. Error messages displayed as `.bot-message.error` divs. |

---

### Slice 3 - MxChat Public Handler

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-03` |
| `anchor_id` | `ANC-RUN-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-public.php` |
| `symbol_or_scope` | `MxChat_Public::enqueue_scripts()`, `MxChat_Public::render_chatbot()`, shortcode handlers |
| `excerpt_summary` | Public-facing class handling chatbot rendering and asset enqueuing. Registers shortcodes for widget embedding. Enqueues frontend JS/CSS with localized configuration via `wp_localize_script()`. Renders chat widget HTML with per-bot configuration (colors, labels, toolbar options). Multi-bot support through bot ID routing. Passes session ID, nonce, AJAX URL, and feature flags to frontend. |
| `excerpt_lines` | `L1-L400` (estimated) |
| `token_estimate` | `3000` |
| `notes` | Widget rendering only -- request handling delegated to integrator class. Multi-bot instance support. |

---

### Slice 4 - MxChat Integrator Runtime Methods

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-04` |
| `anchor_id` | `ANC-RUN-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `symbol_or_scope` | `mxchat_handle_chat_request()`, `mxchat_stream_chat()`, session management methods, history methods |
| `excerpt_summary` | Monolithic integrator (10632 lines) handling all runtime request processing. Dual entry points: `mxchat_handle_chat_request` (standard AJAX) and `mxchat_stream_chat` (SSE streaming). Message intake: sanitize input, validate session ownership (IP/user check with `mxchat_session_owner_{session_id}`), build context from page content/title if contextual awareness enabled, retrieve conversation history from `mxchat_history_{session_id}` options. Provider dispatch via model prefix detection (gpt-, claude-, gemini-, deepseek-, grok-). Full SSE streaming via cURL WRITEFUNCTION callbacks with provider-specific chunk parsing (OpenAI, Claude, Gemini, DeepSeek, Grok formats). Session ownership triggers reset on IP mismatch. Rate limiting and moderation (MX_Chat_Moderation) integrated inline. Chat mode tracking (AI/agent/livechat) stored per session. |
| `excerpt_lines` | Selected methods (~2000 lines of runtime-relevant code) |
| `token_estimate` | `9000` |
| `secondary_domains` | `["DOM-SECURITY", "DOM-PROVIDER"]` |
| `notes` | Session stored in WP options keyed by `mxchat_history_{session_id}`. Per-bot sessions via separate cookie per bot instance. Persistence filtering via `sessionStartTimestamp`. Streaming fallback: if stream fails, retries non-streaming. |

---

### Slice 5 - MxChat Frontend Chat Script

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-05` |
| `anchor_id` | `ANC-RUN-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/js/chat-script.js` |
| `symbol_or_scope` | `MxChatInstances`, `callMxChatStream()`, `appendMessage()`, `updateStreamingMessage()`, input handlers |
| `excerpt_summary` | jQuery + Fetch API frontend with full SSE streaming support. Instance registry (`MxChatInstances`) tracks per-bot state: sessionId (cookie per bot), isStreaming flag, sessionStartTimestamp, processedMessageIds (dedup for polling). Streaming via native Fetch API with ReadableStream: `callMxChatStream()` concatenates chunks into `accumulatedContent`, updates DOM incrementally via `updateStreamingMessage()`. Completion signal: `data: [DONE]\n\n` or end-of-stream. Non-streaming fallback if response is JSON instead of SSE. DOM: `#chat-box-{botId}` messages, `#chat-input-{botId}` textarea with auto-resize, `.mxchat-popular-questions` chips, PDF/Word upload toolbar. State: `disableChatInput()`/`enableChatInput()` lock during response. HTML output sanitized via `sanitizeBotHtml()` with DOMParser whitelist. |
| `excerpt_lines` | `L1-L1500` (estimated) |
| `token_estimate` | `8000` |
| `notes` | Most sophisticated frontend among 3 sources. Multi-bot instance management. Real-time streaming DOM updates. File upload support. Status code mapping: 403 (session expired), 429 (rate limit), 500 (server error). |

---

### Slice 6 - Baachal Main Plugin Runtime

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-06` |
| `anchor_id` | `ANC-RUN-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | `Baachal::handle_message()`, `Baachal::get_conversation_history()`, `Baachal::save_message()`, AJAX handler |
| `excerpt_summary` | Single-class plugin (2569 lines) handling chat messages via `baachal_message` AJAX action. Message intake: sanitize via `sanitize_text_field()`, validate length (1000 char max), generate/validate session ID (`chat_{uuid}` format). Custom handler support via `baachal_custom_message_handler` filter. Conversation history from custom post type `baachal_conversation` with post meta `_chat_messages` (array of `{type, message, timestamp}`). Memory window configurable via `baachal_memory_limit` filter (default 10 messages). Max 100 messages per conversation via `baachal_message_limit`. Message persistence controlled by `baachal_message_persistence` option. Provider dispatch via `call_ai_api()`. Response filtered through `baachal_bot_response` hook. WooCommerce product cards appended if integration detected. Both user and bot messages saved to conversation post meta. |
| `excerpt_lines` | `L100-L500` (runtime methods) |
| `token_estimate` | `5000` |
| `secondary_domains` | `["DOM-WOOCOMMERCE"]` |
| `notes` | No streaming. Custom post type for conversation storage (unlike WP options in AXI/MXC). Extensive PHP hook system for message lifecycle. |

---

### Slice 7 - Baachal Frontend Chatbot JS

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-07` |
| `anchor_id` | `ANC-RUN-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/assets/chatbot.js` |
| `symbol_or_scope` | Session management, message send/receive, UI state, hook system |
| `excerpt_summary` | jQuery frontend with localStorage-based session management. Session ID in localStorage (`baachal_session_id`) or generated UUID. UI state: `isOpen` (widget visibility), `isLoading` (request in-flight). DOM: `#chatbot-toggle` button, `#chatbot-container`, `#chatbot-messages` (appends `.{type}-message` divs), `#chatbot-input` textarea, `#chatbot-loading` indicator. Auto-scroll on message append. Textarea auto-resize. Clear history button triggers session reset. User messages escaped via `escapeHtml()`. Bot messages sanitized via `sanitizeBotHtml()` using DOMParser whitelist (safe href patterns: http/https, mailto, tel, /, #). Extensive JS hook system: `baachal_before_send_message`, `baachal_response_received`, `baachal_filter_bot_response`, `baachal_before_open/close`, `baachal_after_open/close`, `baachal_before_add_message`, `baachal_after_add_message`. |
| `excerpt_lines` | `L1-L500` (estimated) |
| `token_estimate` | `4500` |
| `notes` | Simplest frontend but most extensible hook system among JS frontends. No streaming. localStorage session (vs. cookie in AXI/MXC). |

---

### Slice 8 - Baachal Chat Widget Template

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-001-RUN-08` |
| `anchor_id` | `ANC-RUN-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/templates/chatbot-widget.php` |
| `symbol_or_scope` | Widget HTML template |
| `excerpt_summary` | PHP template rendering the chatbot widget HTML. Configurable position (bottom-right/left), title, welcome message, placeholder text. Color theming via inline CSS variables from admin options. Toggle button with customizable icon. Messages container with welcome message. Input area with textarea and send button. Loading indicator. Proper escaping: `esc_attr()` for attributes, `esc_html()` for text content. CSS animation for widget open/close. Responsive design with max-width constraints. |
| `excerpt_lines` | `L1-L120` (estimated) |
| `token_estimate` | `1500` |
| `notes` | Clean separation of template from logic (unlike AXI/MXC which render inline). |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `8` |
| `total_token_estimate` | `44000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-RUNTIME` | `CP-P01-S02-001-RUN-01`, `RUN-02` | `CP-P01-S02-001-RUN-03`, `RUN-04`, `RUN-05` | `CP-P01-S02-001-RUN-06`, `RUN-07`, `RUN-08` |

### Gaps and Risks

- **Streaming asymmetry**: SRC-MXC has full SSE streaming (cURL backend + Fetch frontend); SRC-AXI and SRC-BAA have no streaming. This is the single largest capability gap in the runtime domain.
- **Session storage divergence**: SRC-AXI uses WP options, SRC-MXC uses WP options with per-bot cookies, SRC-BAA uses custom post type with localStorage. Migration must reconcile these patterns.
- **Frontend complexity**: SRC-MXC frontend is most sophisticated (multi-bot instances, streaming, file upload) but also most coupled to backend. SRC-BAA has cleanest hook system but least functionality.
- **Monolithic risk**: SRC-MXC integrator class (10632 lines) mixes runtime, provider, RAG, moderation, and session logic. Extraction of runtime-specific patterns requires careful scoping.
- **Tool-pending pattern**: SRC-AXI's GPT-5 handshake (tool_pending -> client execution -> continuation AJAX) is unique and adds runtime complexity not present in other sources.
